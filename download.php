<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$pdo = db();

$type = $_GET['type'] ?? '';
$id = get_int('id');

switch ($type) {
    case 'lesson':
        $stmt = $pdo->prepare('SELECT file_path, file_name, course_id FROM lessons WHERE id = ?');
        break;
    case 'assignment':
        $stmt = $pdo->prepare('SELECT file_path, file_name, course_id FROM assignments WHERE id = ?');
        break;
    case 'submission':
        $stmt = $pdo->prepare('SELECT s.file_path, s.file_name, a.course_id, s.user_id FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.id = ?');
        break;
    default:
        abort(404, 'File tidak ditemukan.');
}
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || !$row['file_path']) {
    abort(404, 'File tidak ditemukan.');
}

$course = course_or_abort((int) $row['course_id'], $user);
// Jawaban siswa hanya boleh diunduh oleh pemiliknya atau guru pengampu.
if ($type === 'submission' && !can_manage_course($course, $user) && (int) $row['user_id'] !== (int) $user['id']) {
    abort(403, 'Anda tidak memiliki akses ke file ini.');
}

if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/', $row['file_path'])) {
    abort(404, 'File tidak ditemukan.');
}
$path = $config['upload_dir'] . '/' . $row['file_path'];
if (!is_file($path)) {
    abort(404, 'File tidak ditemukan di server.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
// Hanya PDF dan gambar yang boleh dibuka langsung di browser, sisanya diunduh.
$inline = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/gif'], true);
$fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $row['file_name']);

header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($row['file_name']));
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'; sandbox');
header('Cache-Control: private, max-age=0');
readfile($path);
