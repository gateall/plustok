<?php
declare(strict_types=1);
/** 상담 목록 CSV(엑셀) 내보내기 — 상담관리와 동일 필터 적용 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util/CrmSchema.php';
require_login();

$pdo = db();
$custTable = CrmSchema::legacyCustomerTable($pdo);

// 필터 (index.php와 동일)
$fSite   = clean_str($_GET['site'] ?? '', 50);
$fStatus = clean_str($_GET['status'] ?? '', 20);
$fManager= (int)($_GET['manager'] ?? 0);
$fFrom   = clean_str($_GET['from'] ?? '', 10);
$fTo     = clean_str($_GET['to'] ?? '', 10);
$q       = clean_str($_GET['q'] ?? '', 50);

$where = []; $params = [];
if ($fSite !== '')   { $where[] = 's.site_code = :site';   $params[':site'] = $fSite; }
if ($fStatus !== '' && isset(CONSULT_STATUSES[$fStatus])) { $where[] = 'c.status = :st'; $params[':st'] = $fStatus; }
if ($fManager > 0)   { $where[] = 'c.manager_id = :mid';   $params[':mid'] = $fManager; }
if ($fFrom !== '')    { $where[] = 'c.created_at >= :from'; $params[':from'] = $fFrom . ' 00:00:00'; }
if ($fTo !== '')      { $where[] = 'c.created_at <= :to';   $params[':to'] = $fTo . ' 23:59:59'; }
if ($q !== '')        { $where[] = '(cu.name LIKE :q OR cu.phone LIKE :q OR c.consult_no LIKE :q)'; $params[':q'] = '%' . $q . '%'; }
$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT c.consult_no, c.created_at, s.site_name, s.brand, c.category, c.product_name,
               cu.name AS cust_name, cu.phone, cu.email, cu.company, cu.zipcode, cu.address,
               c.status, mg.name AS manager_name, c.memo
        FROM consults c
        LEFT JOIN {$custTable} cu ON cu.id = c.customer_id
        LEFT JOIN sites s ON s.id = c.site_id
        LEFT JOIN managers mg ON mg.id = c.manager_id
        $sqlWhere
        ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

log_activity('consult_export', null, 'rows=' . $stmt->rowCount());

$filename = 'consults_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// Excel이 UTF-8을 인식하도록 BOM 추가
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['상담번호', '접수일시', '사이트', '브랜드', '카테고리', '상품', '고객명', '연락처', '이메일', '회사', '우편번호', '주소', '상태', '담당자', '상담내용']);

while ($r = $stmt->fetch()) {
    fputcsv($out, [
        $r['consult_no'],
        $r['created_at'],
        $r['site_name'],
        $r['brand'],
        $r['category'] ?? '',
        $r['product_name'] ?? '',
        $r['cust_name'],
        $r['phone'],
        $r['email'] ?? '',
        $r['company'] ?? '',
        $r['zipcode'] ?? '',
        $r['address'] ?? '',
        CONSULT_STATUSES[$r['status']] ?? $r['status'],
        $r['manager_name'] ?? '',
        $r['memo'] ?? '',
    ]);
}
fclose($out);
exit;
