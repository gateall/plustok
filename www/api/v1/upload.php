<?php
declare(strict_types=1);
/**
 * POST /api/v1/upload.php — 파일 첨부. (API.md 2 / STYLEGUIDE 4)
 * multipart/form-data: site_code(간접), file, file_type, consult_no(선택)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('METHOD_NOT_ALLOWED', 'POST만 허용됩니다.', 405);
}

$site = require_site_by_apikey();

if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    json_error('INVALID_PARAM', '파일이 없습니다.');
}
$f = $_FILES['file'];

if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
    json_error('PAYLOAD_TOO_LARGE', '파일 용량이 너무 큽니다.', 413);
}
if ($f['error'] !== UPLOAD_ERR_OK) {
    json_error('SERVER_ERROR', '업로드에 실패했습니다.', 500);
}
if ((int)$f['size'] > UPLOAD_MAX_BYTES) {
    json_error('PAYLOAD_TOO_LARGE', '최대 10MB까지 업로드할 수 있습니다.', 413);
}

$origName = clean_str($f['name'], 255);
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
    json_error('INVALID_PARAM', '허용되지 않은 파일 형식입니다.');
}

// MIME 이중 검증
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($f['tmp_name']) ?: 'application/octet-stream';
$allowedMime = [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf', 'application/x-hwp', 'application/haansofthwp',
    'application/octet-stream', // hwpx 등
];
if (!in_array($mime, $allowedMime, true) && strpos($mime, 'image/') !== 0) {
    json_error('INVALID_PARAM', '파일 형식(MIME)을 확인해주세요.');
}

$fileType = clean_str($_POST['file_type'] ?? '', 30);
if ($fileType !== '' && !in_array($fileType, UPLOAD_FILE_TYPES, true)) {
    $fileType = '';
}

// 저장 경로: uploads/consult/YYYY/MM/{random}.ext
$subDir = 'consult/' . date('Y') . '/' . date('m');
$destDir = UPLOAD_PATH . '/' . $subDir;
if (!is_dir($destDir) && !@mkdir($destDir, 0750, true) && !is_dir($destDir)) {
    log_error('upload.php', 'mkdir 실패: ' . $destDir);
    json_error('SERVER_ERROR', '저장 폴더 생성 실패.', 500);
}

$savedName = bin2hex(random_bytes(16)) . '.' . $ext;
$savedRel  = 'uploads/' . $subDir . '/' . $savedName;
$savedAbs  = $destDir . '/' . $savedName;

if (!move_uploaded_file($f['tmp_name'], $savedAbs)) {
    log_error('upload.php', 'move_uploaded_file 실패');
    json_error('SERVER_ERROR', '파일 저장에 실패했습니다.', 500);
}

// consult_no로 상담 연결(선택)
$consultId = null;
$consultNo = clean_str($_POST['consult_no'] ?? '', 20);
if ($consultNo !== '') {
    $stmt = db()->prepare('SELECT id FROM consults WHERE consult_no = :n LIMIT 1');
    $stmt->execute([':n' => $consultNo]);
    $cid = $stmt->fetchColumn();
    if ($cid) {
        $consultId = (int)$cid;
    }
}

$ins = db()->prepare(
    'INSERT INTO attachments (consult_id, file_type, orig_name, saved_path, mime, size_bytes)
     VALUES (:cid, :ftype, :orig, :path, :mime, :size)'
);
$ins->execute([
    ':cid' => $consultId, ':ftype' => $fileType ?: null, ':orig' => $origName,
    ':path' => $savedRel, ':mime' => $mime, ':size' => (int)$f['size'],
]);

json_success([
    'attachment_id' => (int)db()->lastInsertId(),
    'saved_path'    => $savedRel,
], '업로드되었습니다.');
