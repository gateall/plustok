<?php
declare(strict_types=1);
/** 사이트관리 + API Key 발급/재발급 + 추가/수정/삭제/선택삭제 — Mobile First */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util/SiteSchema.php';
require_once __DIR__ . '/../../migrations/lib.php';
require_login();
require_role(['super', 'admin']);
$pdo = db();

$activeCol = SiteSchema::activeColumn($pdo);
$hasDomain = acep_column_exists($pdo, 'sites', 'domain');
$hasDivision = acep_column_exists($pdo, 'sites', 'division');
$hasPersona = acep_column_exists($pdo, 'sites', 'persona');
$hasConsults = acep_table_exists($pdo, 'consults');
$hasHealth = acep_table_exists($pdo, 'site_health_log');

$flash = '';
$flashKey = '';
$flashErr = '';
$failedEditId = 0;
$dbError = '';

/** 상담이 연결된 사이트인지 (연결되면 삭제 불가) */
function site_has_consults(PDO $pdo, int $id): bool
{
    if (!acep_table_exists($pdo, 'consults')) {
        return false;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM consults WHERE site_id = :id');
    $st->execute([':id' => $id]);
    return ((int)$st->fetchColumn()) > 0;
}

/** 연동 상태: ok | check | inactive */
function site_integration_key(PDO $pdo, array $row, ?array $health): string
{
    if ($activeCol = SiteSchema::activeColumn($pdo)) {
        if ((int)($row[$activeCol] ?? 0) !== 1) {
            return 'inactive';
        }
    }
    if ($health !== null && array_key_exists('is_healthy', $health)) {
        return (int)$health['is_healthy'] === 1 ? 'ok' : 'check';
    }
    return !empty($row['api_key']) ? 'ok' : 'check';
}

function site_integration_label(string $key): string
{
    return match ($key) {
        'ok' => '정상연동',
        'check' => '점검필요',
        default => '비활성',
    };
}

function site_is_active(PDO $pdo, array $row): bool
{
    $col = SiteSchema::activeColumn($pdo);
    if ($col === '') {
        return true;
    }
    return (int)($row[$col] ?? 0) === 1;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code = clean_str($_POST['site_code'] ?? '', 50);
        $name = clean_str($_POST['site_name'] ?? '', 100);
        $domain = $hasDomain ? clean_str($_POST['domain'] ?? '', 150) : '';
        $brand = clean_str($_POST['brand'] ?? '', 50);
        $division = $hasDivision ? clean_str($_POST['division'] ?? '', 50) : '';
        $persona = $hasPersona ? clean_str($_POST['persona'] ?? '', 255) : '';
        $missing = $code === '' || $name === '' || $brand === '';
        if ($hasDomain && $domain === '') {
            $missing = true;
        }
        if ($hasDivision && $division === '') {
            $missing = true;
        }
        if ($missing) {
            $flashErr = '필수 항목을 모두 입력하세요.';
        } else {
            try {
                $key = bin2hex(random_bytes(32));
                $cols = ['site_code', 'site_name', 'brand', 'api_key'];
                $vals = [':code', ':name', ':brand', ':key'];
                $params = [
                    ':code' => $code,
                    ':name' => $name,
                    ':brand' => $brand,
                    ':key' => $key,
                ];
                if ($hasDomain) {
                    $cols[] = 'domain';
                    $vals[] = ':domain';
                    $params[':domain'] = $domain;
                }
                if ($hasDivision) {
                    $cols[] = 'division';
                    $vals[] = ':division';
                    $params[':division'] = $division;
                }
                if ($hasPersona) {
                    $cols[] = 'persona';
                    $vals[] = ':persona';
                    $params[':persona'] = $persona ?: null;
                }
                if ($activeCol !== '') {
                    $cols[] = $activeCol;
                    $vals[] = ':active';
                    $params[':active'] = 1;
                }
                $pdo->prepare(
                    'INSERT INTO sites (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'
                )->execute($params);
                log_activity('site_create', 'site:' . $code);
                $flash = '사이트를 등록했습니다.';
                $flashKey = $key;
            } catch (Throwable $ex) {
                $flashErr = '등록 실패(중복 site_code일 수 있음).';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $code = clean_str($_POST['site_code'] ?? '', 50);
        $name = clean_str($_POST['site_name'] ?? '', 100);
        $domain = $hasDomain ? clean_str($_POST['domain'] ?? '', 150) : '';
        $brand = clean_str($_POST['brand'] ?? '', 50);
        $division = $hasDivision ? clean_str($_POST['division'] ?? '', 50) : '';
        $persona = $hasPersona ? clean_str($_POST['persona'] ?? '', 255) : '';
        $missing = $id <= 0 || $code === '' || $name === '' || $brand === '';
        if ($hasDomain && $domain === '') {
            $missing = true;
        }
        if ($hasDivision && $division === '') {
            $missing = true;
        }
        if ($missing) {
            $flashErr = '필수 항목을 모두 입력하세요.';
            if ($id > 0) {
                $failedEditId = $id;
            }
        } else {
            try {
                $sets = ['site_code = :code', 'site_name = :name', 'brand = :brand'];
                $params = [
                    ':code' => $code,
                    ':name' => $name,
                    ':brand' => $brand,
                    ':id' => $id,
                ];
                if ($hasDomain) {
                    $sets[] = 'domain = :domain';
                    $params[':domain'] = $domain;
                }
                if ($hasDivision) {
                    $sets[] = 'division = :division';
                    $params[':division'] = $division;
                }
                if ($hasPersona) {
                    $sets[] = 'persona = :persona';
                    $params[':persona'] = $persona ?: null;
                }
                $pdo->prepare('UPDATE sites SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
                log_activity('site_update', 'site:' . $id);
                $flash = '사이트를 수정했습니다.';
            } catch (Throwable $ex) {
                $flashErr = '수정 실패(중복 site_code일 수 있음).';
                $failedEditId = $id;
            }
        }
    } elseif ($action === 'regen') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $key = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE sites SET api_key = :k WHERE id = :id')->execute([':k' => $key, ':id' => $id]);
            log_activity('site_regen_key', 'site:' . $id);
            $flash = 'API Key를 재발급했습니다.';
            $flashKey = $key;
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $activeCol !== '') {
            $pdo->prepare('UPDATE sites SET ' . $activeCol . ' = 1 - ' . $activeCol . ' WHERE id = :id')
                ->execute([':id' => $id]);
            log_activity('site_toggle', 'site:' . $id);
            $flash = '사용여부를 변경했습니다.';
        }
    } elseif ($action === 'delete' || $action === 'bulk_delete') {
        $ids = [];
        if ($action === 'delete') {
            $ids = [(int)($_POST['id'] ?? 0)];
        } else {
            foreach ((array)($_POST['ids'] ?? []) as $v) {
                $ids[] = (int)$v;
            }
        }
        $ids = array_values(array_filter(array_unique($ids), fn($v) => $v > 0));
        $deleted = 0;
        $blocked = [];
        foreach ($ids as $id) {
            if (site_has_consults($pdo, $id)) {
                $blocked[] = $id;
                continue;
            }
            $pdo->prepare('DELETE FROM sites WHERE id = :id')->execute([':id' => $id]);
            log_activity('site_delete', 'site:' . $id);
            $deleted++;
        }
        if ($deleted > 0) {
            $flash = $deleted . '개 사이트를 삭제했습니다.';
        }
        if ($blocked) {
            $flashErr = '상담이 연결된 사이트는 삭제할 수 없습니다(사용중지를 권장). id: ' . implode(', ', $blocked);
        }
        if (!$ids) {
            $flashErr = '선택된 사이트가 없습니다.';
        }
    }
}

// 필터
$q = clean_str($_GET['q'] ?? '', 100);
$fStatus = clean_str($_GET['status'] ?? '', 20);
$fSort = clean_str($_GET['sort'] ?? '', 30);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$baseRows = [];
try {
    $select = 's.*';
    $join = '';
    $group = '';
    if ($hasConsults) {
        $select .= ', COUNT(c.id) AS consult_cnt, MAX(c.created_at) AS last_consult_at';
        $join = ' LEFT JOIN consults c ON c.site_id = s.id';
        $group = ' GROUP BY s.id';
    } else {
        $select .= ', 0 AS consult_cnt, NULL AS last_consult_at';
    }
    $orderParts = [];
    if ($hasDivision) {
        $orderParts[] = 's.division ASC';
    }
    $orderParts[] = 's.brand ASC';
    $orderParts[] = 's.site_name ASC';
    $sql = 'SELECT ' . $select . ' FROM sites s' . $join . $group . ' ORDER BY ' . implode(', ', $orderParts);
    $baseRows = $pdo->query($sql)->fetchAll();
} catch (Throwable $ex) {
    $dbError = '사이트 목록을 불러오지 못했습니다. DB 스키마를 확인하세요.';
    log_error('sites_list', $ex->getMessage());
}

$healthMap = [];
if ($hasHealth && $baseRows) {
    try {
        $ids = array_map(fn($r) => (int)$r['id'], $baseRows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare(
            "SELECT h.site_id, h.is_healthy, h.checked_at
             FROM site_health_log h
             INNER JOIN (
               SELECT site_id, MAX(checked_at) AS max_at
               FROM site_health_log
               WHERE site_id IN ($placeholders)
               GROUP BY site_id
             ) latest ON latest.site_id = h.site_id AND latest.max_at = h.checked_at"
        );
        $st->execute($ids);
        foreach ($st->fetchAll() as $h) {
            $healthMap[(int)$h['site_id']] = $h;
        }
    } catch (Throwable $ex) {
        // health is optional
    }
}

// 요약 카드 (전체 데이터 기준)
$summary = ['total' => 0, 'ok' => 0, 'check' => 0, 'inactive' => 0];
foreach ($baseRows as $r) {
    $summary['total']++;
    $iKey = site_integration_key($pdo, $r, $healthMap[(int)$r['id']] ?? null);
    if ($iKey === 'ok') {
        $summary['ok']++;
    } elseif ($iKey === 'check') {
        $summary['check']++;
    } else {
        $summary['inactive']++;
    }
}

// 검색·필터·정렬
$filtered = [];
foreach ($baseRows as $r) {
    $iKey = site_integration_key($pdo, $r, $healthMap[(int)$r['id']] ?? null);
    $isActive = site_is_active($pdo, $r);

    if ($q !== '') {
        $hay = strtolower(implode(' ', array_filter([
            $r['site_name'] ?? '',
            $r['site_code'] ?? '',
            $r['brand'] ?? '',
            $hasDomain ? ($r['domain'] ?? '') : '',
            $hasDivision ? ($r['division'] ?? '') : '',
            $hasPersona ? ($r['persona'] ?? '') : '',
        ])));
        if (!str_contains($hay, strtolower($q))) {
            continue;
        }
    }

    if ($fStatus === 'active' && !$isActive) {
        continue;
    }
    if ($fStatus === 'inactive' && $isActive) {
        continue;
    }
    if ($fStatus === 'ok' && $iKey !== 'ok') {
        continue;
    }
    if ($fStatus === 'check' && $iKey !== 'check') {
        continue;
    }

    $r['_integration'] = $iKey;
    $r['_active'] = $isActive;
    $filtered[] = $r;
}

usort($filtered, function (array $a, array $b) use ($fSort): int {
    return match ($fSort) {
        'name_desc' => strcmp((string)$b['site_name'], (string)$a['site_name']),
        'code_asc' => strcmp((string)$a['site_code'], (string)$b['site_code']),
        'consult_desc' => ((int)$b['consult_cnt']) <=> ((int)$a['consult_cnt']),
        'created_desc' => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')),
        default => strcmp((string)$a['site_name'], (string)$b['site_name']),
    };
});

$totalFiltered = count($filtered);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pageRows = array_slice($filtered, $offset, $perPage);

// 수정 대상
$editId = $failedEditId > 0 ? $failedEditId : (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM sites WHERE id = :id LIMIT 1');
    $st->execute([':id' => $editId]);
    $editRow = $st->fetch() ?: null;
}

$showCreate = isset($_GET['create']) || ($flashErr !== '' && ($_POST['action'] ?? '') === 'create');
$showPanel = $showCreate || $editRow !== null;

$queryBase = function (array $overrides = []) use ($q, $fStatus, $fSort): string {
    $params = array_filter([
        'q' => $q !== '' ? $q : null,
        'status' => $fStatus !== '' ? $fStatus : null,
        'sort' => $fSort !== '' ? $fSort : null,
    ], fn($v) => $v !== null && $v !== '');
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return $params ? ('?' . http_build_query($params)) : '';
};

$page_title = '사이트관리';
$active = 'sites';
require INC_DIR . '/header.php';
?>
<link rel="stylesheet" href="/assets/css/admin-sites.css?v=20260727-1">

<div class="sites-page">
  <header class="sites-page__header">
    <div class="sites-page__title-wrap">
      <h1 class="sites-page__title">
        사이트관리
        <span class="sites-page__count">(<?= $totalFiltered ?>개<?= $q !== '' || $fStatus !== '' ? ' / 전체 ' . $summary['total'] : '' ?>)</span>
      </h1>
      <p class="sites-page__subtitle">등록된 사이트와 API 연동 상태를 관리합니다.</p>
    </div>
    <button type="button" class="btn sites-page__add-btn" id="sites-add-toggle" aria-controls="sites-panel" aria-expanded="<?= $showPanel ? 'true' : 'false' ?>">
      + 사이트 추가
    </button>
  </header>

  <div class="sites-summary" aria-label="사이트 요약">
    <a href="/admin/sites/<?= e($queryBase(['status' => null, 'page' => null])) ?>" class="sites-summary__card<?= $fStatus === '' ? ' is-active-filter' : '' ?>">
      <div class="sites-summary__label">전체</div>
      <div class="sites-summary__value"><?= number_format($summary['total']) ?></div>
    </a>
    <a href="/admin/sites/<?= e($queryBase(['status' => 'ok', 'page' => null])) ?>" class="sites-summary__card<?= $fStatus === 'ok' ? ' is-active-filter' : '' ?>">
      <div class="sites-summary__label">정상연동</div>
      <div class="sites-summary__value sites-summary__value--ok"><?= number_format($summary['ok']) ?></div>
    </a>
    <a href="/admin/sites/<?= e($queryBase(['status' => 'check', 'page' => null])) ?>" class="sites-summary__card<?= $fStatus === 'check' ? ' is-active-filter' : '' ?>">
      <div class="sites-summary__label">점검필요</div>
      <div class="sites-summary__value sites-summary__value--warn"><?= number_format($summary['check']) ?></div>
    </a>
    <a href="/admin/sites/<?= e($queryBase(['status' => 'inactive', 'page' => null])) ?>" class="sites-summary__card<?= $fStatus === 'inactive' ? ' is-active-filter' : '' ?>">
      <div class="sites-summary__label">비활성</div>
      <div class="sites-summary__value sites-summary__value--muted"><?= number_format($summary['inactive']) ?></div>
    </a>
  </div>

  <form method="get" class="filters sites-filters">
    <div class="sites-filters__row">
      <input type="text" name="q" placeholder="사이트명·도메인·코드·브랜드 검색" value="<?= e($q) ?>" autocomplete="off">
      <select name="status" aria-label="상태 필터">
        <option value="">전체 상태</option>
        <option value="active" <?= $fStatus === 'active' ? 'selected' : '' ?>>사용</option>
        <option value="inactive" <?= $fStatus === 'inactive' ? 'selected' : '' ?>>중지</option>
        <option value="ok" <?= $fStatus === 'ok' ? 'selected' : '' ?>>정상연동</option>
        <option value="check" <?= $fStatus === 'check' ? 'selected' : '' ?>>점검필요</option>
      </select>
      <select name="sort" aria-label="정렬">
        <option value="">이름순</option>
        <option value="name_desc" <?= $fSort === 'name_desc' ? 'selected' : '' ?>>이름 역순</option>
        <option value="code_asc" <?= $fSort === 'code_asc' ? 'selected' : '' ?>>코드순</option>
        <option value="consult_desc" <?= $fSort === 'consult_desc' ? 'selected' : '' ?>>상담수 많은순</option>
        <option value="created_desc" <?= $fSort === 'created_desc' ? 'selected' : '' ?>>등록일 최신</option>
      </select>
    </div>
    <div class="sites-filters__actions">
      <button type="submit" class="btn">검색</button>
      <a href="/admin/sites/" class="btn sub">초기화</a>
    </div>
  </form>

  <?php if ($flash): ?>
    <div class="msg ok"><?= e($flash) ?><?php if ($flashKey): ?><br>API Key: <span class="mono"><?= e($flashKey) ?></span> <b>(지금 복사하세요. 다시 표시되지 않습니다)</b><?php endif; ?></div>
  <?php endif; ?>
  <?php if ($flashErr): ?><div class="msg err"><?= e($flashErr) ?></div><?php endif; ?>

  <form id="bulkform" method="post" onsubmit="return confirm('선택한 사이트를 삭제할까요? (상담이 연결된 사이트는 자동 제외)')">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="bulk_delete">
  </form>

  <div class="sites-bulk" id="sites-bulk" aria-live="polite">
    <span class="sites-bulk__info"><span id="sites-bulk-count">0</span>개 선택됨</span>
    <div class="sites-bulk__actions">
      <button type="button" class="btn sub" id="sites-bulk-clear">선택 해제</button>
      <button type="submit" form="bulkform" class="btn danger">선택 삭제</button>
    </div>
  </div>

  <!-- 추가 / 수정 패널 -->
  <div class="sites-panel" id="sites-panel"<?= $showPanel ? '' : ' hidden' ?>>
    <div class="sites-panel__head">
      <h2><?= $editRow ? '사이트 수정' : '사이트 추가' ?></h2>
      <button type="button" class="sites-panel__close" id="sites-panel-close" aria-label="닫기">&times;</button>
    </div>
    <?php if ($editRow): ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
        <div class="sites-form-grid">
          <div><label>site_code *</label><input name="site_code" value="<?= e($editRow['site_code']) ?>" required maxlength="50"></div>
          <div><label>사이트명 *</label><input name="site_name" value="<?= e($editRow['site_name']) ?>" required maxlength="100"></div>
          <?php if ($hasDomain): ?>
            <div><label>도메인 *</label><input name="domain" value="<?= e($editRow['domain'] ?? '') ?>" required maxlength="150"></div>
          <?php endif; ?>
          <div><label>브랜드 *</label><input name="brand" value="<?= e($editRow['brand'] ?? '') ?>" required maxlength="50"></div>
          <?php if ($hasDivision): ?>
            <div><label>사업부 *</label><input name="division" value="<?= e($editRow['division'] ?? '') ?>" required maxlength="50"></div>
          <?php endif; ?>
          <?php if ($hasPersona): ?>
            <div class="sites-form-span2"><label>첫인사(persona)</label><input name="persona" value="<?= e($editRow['persona'] ?? '') ?>" maxlength="255"></div>
          <?php endif; ?>
          <div class="sites-form-span2">
            <button type="submit" class="btn">수정 저장</button>
            <a href="/admin/sites/<?= ($qs = ltrim($queryBase(), '?')) !== '' ? '?' . e($qs) : '' ?>" class="btn sub">취소</a>
          </div>
        </div>
      </form>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="sites-form-grid">
          <div><label>site_code *</label><input name="site_code" placeholder="lg15441644_b" required maxlength="50"></div>
          <div><label>사이트명 *</label><input name="site_name" required maxlength="100"></div>
          <?php if ($hasDomain): ?>
            <div><label>도메인 *</label><input name="domain" placeholder="example.kr" required maxlength="150"></div>
          <?php endif; ?>
          <div><label>브랜드 *</label><input name="brand" placeholder="LG15441644" required maxlength="50"></div>
          <?php if ($hasDivision): ?>
            <div><label>사업부 *</label><input name="division" placeholder="통신가입" required maxlength="50"></div>
          <?php endif; ?>
          <?php if ($hasPersona): ?>
            <div class="sites-form-span2"><label>첫인사(persona)</label><input name="persona"></div>
          <?php endif; ?>
          <div class="sites-form-span2">
            <button type="submit" class="btn">등록 + API Key 발급</button>
          </div>
        </div>
        <p class="sites-form-hint">한 브랜드에 도메인이 여러 개면 각각 등록하세요. 등록 후 API Key는 한 번만 표시됩니다.</p>
      </form>
    <?php endif; ?>
  </div>

  <!-- Mobile card list -->
  <div class="sites-mobile-view sites-cards" aria-label="사이트 목록">
    <?php if ($dbError): ?>
      <div class="sites-empty sites-empty--error"><?= e($dbError) ?></div>
    <?php elseif (!$filtered): ?>
      <div class="sites-empty"><?= $q !== '' || $fStatus !== '' ? '검색 결과가 없습니다.' : '등록된 사이트가 없습니다.' ?></div>
    <?php endif; ?>
    <?php foreach ($filtered as $r):
        $iKey = $r['_integration'];
        $iLabel = site_integration_label($iKey);
        $isActive = $r['_active'];
        $lastConsult = $r['last_consult_at'] ? substr((string)$r['last_consult_at'], 0, 16) : '-';
        $createdAt = isset($r['created_at']) ? substr((string)$r['created_at'], 0, 16) : '-';
        $domainVal = $hasDomain ? ($r['domain'] ?? '-') : '-';
        $filterQs = ltrim($queryBase(['page' => null]), '?');
        $filterSuffix = $filterQs !== '' ? '&' . $filterQs : '';
    ?>
      <article class="site-card">
        <div class="site-card__head">
          <label class="site-card__check">
            <input type="checkbox" class="rowchk" name="ids[]" form="bulkform" value="<?= (int)$r['id'] ?>" aria-label="<?= e($r['site_name']) ?> 선택">
          </label>
          <div class="site-card__main">
            <h3 class="site-card__name"><?= e($r['site_name']) ?></h3>
            <?php if ($hasDomain): ?><div class="site-card__domain"><?= e($domainVal) ?></div><?php endif; ?>
            <div class="site-card__code"><?= e($r['site_code']) ?></div>
          </div>
        </div>
        <div class="site-card__badges">
          <span class="site-card__badge <?= $isActive ? 'site-card__badge--active' : 'site-card__badge--inactive' ?>">
            <?= $isActive ? '사용' : '중지' ?>
          </span>
          <span class="site-card__badge site-card__badge--<?= $iKey === 'ok' ? 'ok' : ($iKey === 'check' ? 'check' : 'inactive') ?>">
            <?= e($iLabel) ?>
          </span>
        </div>
        <dl class="site-card__meta">
          <div>
            <dt>상담 수</dt>
            <dd><?= number_format((int)$r['consult_cnt']) ?></dd>
          </div>
          <div>
            <dt>최근 상담</dt>
            <dd class="muted"><?= e($lastConsult) ?></dd>
          </div>
          <div>
            <dt>등록일</dt>
            <dd class="muted"><?= e($createdAt) ?></dd>
          </div>
          <?php if ($hasDivision): ?>
            <div>
              <dt>사업부</dt>
              <dd><?= e($r['division'] ?? '-') ?></dd>
            </div>
          <?php endif; ?>
        </dl>
        <div class="site-card__actions">
          <a href="/admin/site_fields/index.php?site_id=<?= (int)$r['id'] ?>" class="btn sub">상세</a>
          <a href="/admin/sites/?edit=<?= (int)$r['id'] ?><?= $filterSuffix ?>" class="btn sub">수정</a>
          <a href="/admin/consults/?site=<?= urlencode((string)$r['site_code']) ?>" class="btn sub">상담</a>
          <?php if ($activeCol !== ''): ?>
            <form method="post" onsubmit="return confirm('<?= $isActive ? '이 사이트를 중지하시겠습니까?' : '이 사이트를 다시 사용하시겠습니까?' ?>');">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn sub"><?= $isActive ? '중지' : '사용' ?></button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <!-- Desktop table -->
  <div class="sites-desktop-view tablewrap">
    <table>
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="checkall" aria-label="전체 선택" style="width:auto"></th>
          <th>사이트명</th>
          <?php if ($hasDomain): ?><th>도메인</th><?php endif; ?>
          <th>site_code</th>
          <th class="right">상담수</th>
          <th>연동상태</th>
          <th>사용</th>
          <th>최근 상담</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($dbError): ?>
          <tr><td colspan="<?= $hasDomain ? 9 : 8 ?>" class="sites-empty--error"><?= e($dbError) ?></td></tr>
        <?php elseif (!$pageRows): ?>
          <tr><td colspan="<?= $hasDomain ? 9 : 8 ?>" class="muted"><?= $q !== '' || $fStatus !== '' ? '검색 결과가 없습니다.' : '등록된 사이트가 없습니다.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($pageRows as $r):
            $iKey = $r['_integration'];
            $iLabel = site_integration_label($iKey);
            $isActive = $r['_active'];
            $lastConsult = $r['last_consult_at'] ? substr((string)$r['last_consult_at'], 0, 16) : '-';
            $filterQs = ltrim($queryBase(['page' => null]), '?');
            $filterSuffix = $filterQs !== '' ? '&' . $filterQs : '';
        ?>
          <tr>
            <td><input type="checkbox" class="rowchk" name="ids[]" form="bulkform" value="<?= (int)$r['id'] ?>" style="width:auto"></td>
            <td><?= e($r['site_name']) ?></td>
            <?php if ($hasDomain): ?><td><?= e($r['domain'] ?? '-') ?></td><?php endif; ?>
            <td class="mono"><?= e($r['site_code']) ?></td>
            <td class="right"><?= number_format((int)$r['consult_cnt']) ?></td>
            <td><span class="site-card__badge site-card__badge--<?= $iKey === 'ok' ? 'ok' : ($iKey === 'check' ? 'check' : 'inactive') ?>"><?= e($iLabel) ?></span></td>
            <td><?= $isActive ? '사용' : '<span class="muted">중지</span>' ?></td>
            <td class="muted"><?= e($lastConsult) ?></td>
            <td>
              <div class="sites-actions site-action-buttons">
                <a href="/admin/site_fields/index.php?site_id=<?= (int)$r['id'] ?>" class="btn sub">상세</a>
                <a href="/admin/sites/?edit=<?= (int)$r['id'] ?><?= $filterSuffix ?>" class="btn sub">수정</a>
                <a href="/admin/consults/?site=<?= urlencode((string)$r['site_code']) ?>" class="btn sub">상담</a>
                <form method="post" style="display:inline" onsubmit="return confirm('API Key를 재발급하면 기존 키는 즉시 무효화됩니다. 계속할까요?')">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="regen">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn sub">키재발급</button>
                </form>
                <?php if ($activeCol !== ''): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn sub"><?= $isActive ? '중지' : '사용' ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="sites-pagination" aria-label="페이지">
      <?php if ($page > 1): ?>
        <a href="/admin/sites/<?= e($queryBase(['page' => $page - 1])) ?>" class="btn sub">이전</a>
      <?php endif; ?>
      <span class="sites-pagination__info"><?= $page ?> / <?= $totalPages ?> (<?= number_format($totalFiltered) ?>건)</span>
      <?php if ($page < $totalPages): ?>
        <a href="/admin/sites/<?= e($queryBase(['page' => $page + 1])) ?>" class="btn sub">다음</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>

<script>
(function () {
  var panel = document.getElementById('sites-panel');
  var addBtn = document.getElementById('sites-add-toggle');
  var closeBtn = document.getElementById('sites-panel-close');
  var bulkBar = document.getElementById('sites-bulk');
  var bulkCount = document.getElementById('sites-bulk-count');
  var bulkClear = document.getElementById('sites-bulk-clear');
  var all = document.getElementById('checkall');
  var rowChecks = function () { return document.querySelectorAll('.rowchk'); };

  function updateBulk() {
    var n = 0;
    rowChecks().forEach(function (c) { if (c.checked) n++; });
    if (bulkCount) bulkCount.textContent = String(n);
    if (bulkBar) bulkBar.classList.toggle('is-visible', n > 0);
  }

  rowChecks().forEach(function (c) {
    c.addEventListener('change', updateBulk);
  });

  if (all) {
    all.addEventListener('change', function () {
      rowChecks().forEach(function (c) { c.checked = all.checked; });
      updateBulk();
    });
  }

  if (bulkClear) {
    bulkClear.addEventListener('click', function () {
      rowChecks().forEach(function (c) { c.checked = false; });
      if (all) all.checked = false;
      updateBulk();
    });
  }

  if (panel && addBtn) {
    function openPanel() {
      panel.hidden = false;
      addBtn.setAttribute('aria-expanded', 'true');
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function closePanel() {
      panel.hidden = true;
      addBtn.setAttribute('aria-expanded', 'false');
      if (window.location.search.match(/(?:^|[?&])(?:edit|create)=/)) {
        var base = '/admin/sites/';
        var qs = window.location.search.replace(/(?:^|[?&])(?:edit|create)=[^&]*/g, '').replace(/^&/, '?').replace(/\?&/, '?');
        if (qs === '?' || qs === '') qs = '';
        window.history.replaceState(null, '', base + qs);
      }
    }

    addBtn.addEventListener('click', function () {
      if (panel.hidden) {
        if (window.location.search.indexOf('edit=') >= 0) {
          window.location.href = '/admin/sites/?create=1';
          return;
        }
        openPanel();
      } else {
        closePanel();
      }
    });

    if (closeBtn) closeBtn.addEventListener('click', closePanel);
  }

  updateBulk();
})();
</script>
<?php require INC_DIR . '/footer.php'; ?>
