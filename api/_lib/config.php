<?php
/**
 * UDTC-Portal : ศูนย์กลางให้บริการระบบงานต่างๆ
 * ไฟล์ตั้งค่าหลักของระบบ (Neon PostgreSQL + Vercel)
 *
 * ค่าที่ต้องตั้งเป็น Environment Variable:
 *   DATABASE_URL   = postgresql://user:password@ep-xxxx-pooler.region.aws.neon.tech/neondb?sslmode=require
 *   (รองรับ POSTGRES_URL ที่ Vercel ตั้งให้อัตโนมัติเมื่อเชื่อม Neon ผ่าน Vercel Storage ด้วย)
 */

// ---------- ข้อมูลระบบ ----------
define('APP_NAME', 'UDTC-Portal');
define('APP_SUBTITLE', 'ศูนย์กลางให้บริการระบบงานต่างๆ');
define('APP_VERSION', '2.0.0');

// ---------- ไฟล์แนบ ----------
// ไฟล์ใหม่เก็บในฐานข้อมูล (ตาราง link_files) เพราะ Vercel ไม่มีพื้นที่เก็บไฟล์ถาวร
// Vercel จำกัดขนาด request ไว้ราว 4.5 MB จึงตั้งค่าเริ่มต้นไว้ 4 MB (ปรับได้ด้วย env MAX_UPLOAD_MB)
define('MAX_UPLOAD_MB', (float)(getenv('MAX_UPLOAD_MB') ?: 4));
// ไฟล์เดิมจากระบบ XAMPP ที่แนบมากับโค้ด (อ่านอย่างเดียว)
define('LEGACY_FILE_DIR', __DIR__ . '/../_files/');

// นามสกุลไฟล์ที่อนุญาต: เอกสาร, ภาพ, ไฟล์บีบอัด, PDF
define('ALLOWED_EXT', [
    'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv',
    'pdf',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
    'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz',
]);

define('SESSION_LIFETIME', 60 * 60 * 8); // 8 ชั่วโมง

date_default_timezone_set('Asia/Bangkok');

/** อ่าน connection string ของ Neon จาก Environment Variable */
function database_url(): string
{
    foreach (['DATABASE_URL', 'POSTGRES_URL', 'NEON_DATABASE_URL'] as $k) {
        $v = getenv($k);
        if ($v === false || $v === '') $v = $_ENV[$k] ?? $_SERVER[$k] ?? '';
        if ($v !== '') return $v;
    }
    // สำหรับรันบนเครื่อง: อ่านจากไฟล์ .env ที่รากโปรเจกต์ (ถ้ามี)
    $env = __DIR__ . '/../../.env';
    if (is_file($env)) {
        foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('~^\s*(DATABASE_URL|POSTGRES_URL)\s*=\s*"?([^"]+)"?\s*$~', $line, $m)) return trim($m[2]);
        }
    }
    throw new RuntimeException('ยังไม่ได้ตั้งค่า DATABASE_URL (connection string ของ Neon)');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $p = parse_url(database_url());
    if (!$p || empty($p['host'])) throw new RuntimeException('รูปแบบ DATABASE_URL ไม่ถูกต้อง');
    parse_str($p['query'] ?? '', $q);

    $host = $p['host'];
    $dsn = 'pgsql:host=' . $host . ';port=' . ($p['port'] ?? 5432)
         . ';dbname=' . ltrim($p['path'] ?? '/neondb', '/')
         . ';sslmode=' . ($q['sslmode'] ?? 'require');
    // libpq รุ่นเก่าบางเครื่องไม่ส่ง SNI — ระบุ endpoint ของ Neon ไว้ให้เสมอ
    if (preg_match('~^(ep-[a-z0-9-]+?)(-pooler)?\.~', $host, $m) && str_contains($host, 'neon.tech')) {
        $dsn .= ";options='endpoint=" . $m[1] . "'";
    }

    $pdo = new PDO($dsn, urldecode($p['user'] ?? ''), urldecode($p['pass'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true, // ใช้กับ Neon connection pooler (PgBouncer) ได้
    ]);
    $pdo->exec("SET TIME ZONE 'Asia/Bangkok'");
    return $pdo;
}

/**
 * เก็บ session ในฐานข้อมูล — serverless (Vercel) ไม่มีดิสก์ที่ใช้ร่วมกันระหว่างคำขอ
 */
class DbSessionHandler implements SessionHandlerInterface
{
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string|false
    {
        try {
            $st = db()->prepare('SELECT data FROM sessions WHERE id=? AND expires_at > NOW()');
            $st->execute([$id]);
            return (string)($st->fetchColumn() ?: '');
        } catch (Throwable $e) {
            return '';
        }
    }

    public function write($id, $data): bool
    {
        try {
            db()->prepare("INSERT INTO sessions (id, data, expires_at) VALUES (?, ?, NOW() + make_interval(secs => ?))
                           ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at")
                ->execute([$id, $data, SESSION_LIFETIME]);
        } catch (Throwable $e) {
            // ไม่ให้หน้าเว็บล่มหากเขียน session ไม่ได้
        }
        return true;
    }

    public function destroy($id): bool
    {
        try { db()->prepare('DELETE FROM sessions WHERE id=?')->execute([$id]); } catch (Throwable $e) {}
        return true;
    }

    public function gc($max): int|false
    {
        try { return db()->exec('DELETE FROM sessions WHERE expires_at < NOW()'); } catch (Throwable $e) { return 0; }
    }
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

if (!defined('UDPT_NO_SESSION') && session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    session_set_save_handler(new DbSessionHandler(), true);
    session_name('UDPTSESSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https(),
    ]);
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** อ่านค่า bytea จาก PDO (pdo_pgsql คืนค่าเป็น stream) */
function bytea_value($v): string
{
    if (is_resource($v)) return (string)stream_get_contents($v);
    return (string)$v;
}

/** ดึงเนื้อไฟล์แนบของรายการ: จากฐานข้อมูลก่อน ถ้าไม่มีจึงดูไฟล์เดิมที่แนบมากับโค้ด */
function link_file_content(int $linkId, ?string $stored): ?string
{
    $st = db()->prepare('SELECT data FROM link_files WHERE link_id=?');
    $st->execute([$linkId]);
    $st->bindColumn(1, $data, PDO::PARAM_LOB);
    if ($st->fetch(PDO::FETCH_BOUND)) return bytea_value($data);

    if ($stored && preg_match('~^[a-f0-9]{32}\.[a-z0-9]+$~', $stored) && is_file(LEGACY_FILE_DIR . $stored)) {
        return (string)file_get_contents(LEGACY_FILE_DIR . $stored);
    }
    return null;
}
