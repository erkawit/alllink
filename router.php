<?php
/**
 * ใช้สำหรับทดสอบบนเครื่องเท่านั้น (จำลองการ route แบบเดียวกับ vercel.json)
 *   php -S localhost:8000 router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('~^/(api|database)(/|$)~', $path)) {
    http_response_code(404);
    exit('Not found');
}
if ($path === '/' || $path === '') {
    $path = '/index.php';
}
if (preg_match('~^/(index|users|api|file|install)\.php$~', $path, $m)) {
    $_SERVER['SCRIPT_NAME'] = $path;
    require __DIR__ . '/api/' . $m[1] . '.php';
    return true;
}
if (str_starts_with($path, '/assets/') && is_file(__DIR__ . $path)) {
    return false; // ให้ built-in server ส่งไฟล์ static
}
http_response_code(404);
echo 'Not found';
