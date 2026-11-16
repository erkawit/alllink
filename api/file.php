<?php
/**
 * เปิด/ดาวน์โหลดไฟล์แนบ — file.php?id=1 (ดาวน์โหลด) | file.php?id=1&inline=1 (แสดงในเบราว์เซอร์)
 * เนื้อไฟล์อ่านจากตาราง link_files ใน Neon (ไฟล์เดิมจาก XAMPP อ่านจากโฟลเดอร์ api/_files)
 */
require_once __DIR__ . '/_lib/config.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT file_original, file_stored, file_mime, is_visible, file_visible FROM links WHERE id=?');
$st->execute([$id]);
$row = $st->fetch();

if (!$row || !$row['file_stored'] || (!is_admin() && (!$row['is_visible'] || !$row['file_visible']))) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}
session_write_close();

$content = link_file_content($id, $row['file_stored']);
if ($content === null) {
    http_response_code(404);
    exit('ไม่พบเนื้อไฟล์ในระบบ');
}

$ext = strtolower(pathinfo($row['file_stored'], PATHINFO_EXTENSION));
$inlineTypes = [
    'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
];
$inline = !empty($_GET['inline']) && isset($inlineTypes[$ext]);
$mime = $inline ? $inlineTypes[$ext] : 'application/octet-stream';

$name = $row['file_original'] ?: ('file.' . $ext);
$ascii = preg_replace('~[^A-Za-z0-9._-]~', '_', $name);

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($content));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Cache-Control: private, max-age=300');
echo $content;
