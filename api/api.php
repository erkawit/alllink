<?php
/**
 * UDTC-Portal API (AJAX) — ทุกคำขอใช้ POST + CSRF token
 */
require_once __DIR__ . '/_lib/config.php';
header('Content-Type: application/json; charset=utf-8');

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function need_login(): array
{
    $u = current_user();
    if (!$u) fail('กรุณาเข้าสู่ระบบก่อนใช้งาน', 401);
    return $u;
}

function need_admin(): array
{
    $u = need_login();
    if (!is_admin()) fail('ฟังก์ชันนี้สำหรับผู้ดูแลระบบเท่านั้น', 403);
    return $u;
}

function post(string $k, $default = '')
{
    return $_POST[$k] ?? $default;
}

/** ตัด http:// หรือ https:// ที่ซ้อนอยู่ด้านหน้าออกทั้งหมด */
function strip_protocol(string $url, ?string &$found = null): string
{
    $url = trim($url);
    while (preg_match('~^(https?)://~i', $url, $m)) {
        $found = strtolower($m[1]) . '://';
        $url = substr($url, strlen($m[0]));
    }
    return trim($url);
}

/** ลบเนื้อไฟล์ของรายการออกจากฐานข้อมูล (ไฟล์เดิมที่แนบมากับโค้ดเป็นแบบอ่านอย่างเดียว) */
function delete_file_data(int $linkId): void
{
    db()->prepare('DELETE FROM link_files WHERE link_id=?')->execute([$linkId]);
}

/** บันทึกเนื้อไฟล์ลงตาราง link_files */
function save_file_data(int $linkId, string $tmpPath): void
{
    $fh = fopen($tmpPath, 'rb');
    $st = db()->prepare('INSERT INTO link_files (link_id, data) VALUES (:id, :data)
                         ON CONFLICT (link_id) DO UPDATE SET data = EXCLUDED.data, created_at = NOW()');
    $st->bindValue(':id', $linkId, PDO::PARAM_INT);
    $st->bindValue(':data', $fh, PDO::PARAM_LOB);
    $st->execute();
    fclose($fh);
}

/** รับไฟล์อัปโหลด คืนค่าข้อมูลไฟล์ หรือ null ถ้าไม่มีไฟล์ */
function handle_upload(): ?array
{
    if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES['file'];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        fail('ไฟล์มีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์กำหนด (ไม่เกิน ' . MAX_UPLOAD_MB . ' MB)');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) fail('อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . $f['error'] . ')');
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) fail('ไฟล์ต้องมีขนาดไม่เกิน ' . MAX_UPLOAD_MB . ' MB');
    if (!is_uploaded_file($f['tmp_name'])) fail('อัปโหลดไฟล์ไม่สำเร็จ');

    $original = basename(str_replace('\\', '/', $f['name']));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        fail('ไม่อนุญาตไฟล์ประเภท .' . $ext . ' (รองรับ: ' . implode(', ', ALLOWED_EXT) . ')');
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $f['tmp_name']) ?: $mime;
        finfo_close($fi);
    }
    return [
        'original' => mb_substr($original, 0, 255), 'stored' => $stored, 'mime' => $mime,
        'size' => (int)$f['size'], 'tmp' => $f['tmp_name'],
    ];
}

function user_favs(int $uid): array
{
    $st = db()->prepare('SELECT link_id FROM favorites WHERE user_id=?');
    $st->execute([$uid]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// ---------------- ตรวจสอบคำขอ ----------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);
$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals(csrf_token(), (string)$token)) fail('เซสชันหมดอายุ กรุณารีเฟรชหน้าเว็บ', 419);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // ================= AUTH =================
        case 'login': {
            $username = trim(post('username'));
            $password = (string)post('password');
            if ($username === '' || $password === '') fail('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');

            $st = db()->prepare('SELECT * FROM users WHERE lower(username)=lower(?)');
            $st->execute([$username]);
            $u = $st->fetch();
            if (!$u || !password_verify($password, $u['password_hash'])) fail('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
            if (!$u['is_active']) fail('บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');

            $_SESSION['user'] = [
                'id' => (int)$u['id'], 'username' => $u['username'],
                'fullname' => $u['fullname'], 'role' => $u['role'],
            ];
            session_save();
            db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);

            // รวม favorite ที่เก็บไว้ในเครื่องเข้ากับบัญชี
            $local = json_decode((string)post('local_favs', '[]'), true);
            if (is_array($local) && $local) {
                $ins = db()->prepare('INSERT INTO favorites (user_id, link_id) SELECT ?, id FROM links WHERE id=? ON CONFLICT DO NOTHING');
                foreach (array_slice($local, 0, 1000) as $lid) $ins->execute([$u['id'], (int)$lid]);
            }
            out(['user' => $_SESSION['user'], 'favorites' => user_favs((int)$u['id'])]);
        }

        case 'logout': {
            $_SESSION = [];
            session_save();
            out(['message' => 'ออกจากระบบแล้ว']);
        }

        case 'change_password': {
            $u = need_login();
            $old = (string)post('old_password');
            $new = (string)post('new_password');
            if (mb_strlen($new) < 6) fail('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร');
            $st = db()->prepare('SELECT password_hash FROM users WHERE id=?');
            $st->execute([$u['id']]);
            if (!password_verify($old, (string)$st->fetchColumn())) fail('รหัสผ่านเดิมไม่ถูกต้อง');
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            out(['message' => 'เปลี่ยนรหัสผ่านเรียบร้อย']);
        }

        // ================= LINKS =================
        case 'links': {
            $admin = is_admin();
            $sql = 'SELECT id, type, name, url, is_visible, file_original, file_mime, file_size, file_visible,
                           (file_stored IS NOT NULL)::int AS has_file, to_char(updated_at, \'YYYY-MM-DD HH24:MI:SS\') AS updated_at
                    FROM links' . ($admin ? '' : ' WHERE is_visible=1') . ' ORDER BY lower(name)';
            $rows = db()->query($sql)->fetchAll();
            foreach ($rows as &$r) {
                $r['id'] = (int)$r['id'];
                $r['is_visible'] = (int)$r['is_visible'];
                $r['file_visible'] = (int)$r['file_visible'];
                $r['has_file'] = (int)$r['has_file'];
                $r['file_size'] = $r['file_size'] !== null ? (int)$r['file_size'] : null;
                if (!$admin && !$r['file_visible']) { // ซ่อนไฟล์แนบจากผู้ใช้ทั่วไป
                    $r['has_file'] = 0;
                    $r['file_original'] = $r['file_mime'] = $r['file_size'] = null;
                }
            }
            $u = current_user();
            out(['data' => $rows, 'favorites' => $u ? user_favs($u['id']) : null]);
        }

        case 'link_save': {
            $u = need_admin();
            $id = (int)post('id', 0);
            $type = post('type');
            if (!in_array($type, ['website', 'system', 'document'], true)) fail('กรุณาเลือกประเภทรายการ');
            $name = trim(post('name'));
            if ($name === '') fail('กรุณากรอกชื่อเว็บไซต์ / ระบบ / เอกสาร');
            if (mb_strlen($name) > 255) fail('ชื่อยาวเกิน 255 ตัวอักษร');

            $protocol = post('protocol') === 'http://' ? 'http://' : 'https://';
            $found = null;
            $rest = strip_protocol((string)post('url'), $found);
            if ($found) $protocol = $found;
            $url = null;
            if ($rest !== '') {
                if (preg_match('~\s~u', $rest)) fail('ลิงก์ต้องไม่มีช่องว่าง');
                $url = $protocol . $rest;
                if (!parse_url($url, PHP_URL_HOST)) fail('รูปแบบลิงก์ไม่ถูกต้อง');
                if (strlen($url) > 2048) fail('ลิงก์ยาวเกินไป');
            }

            $isVisible = post('is_visible', '1') === '1' ? 1 : 0;
            $fileVisible = post('file_visible', '1') === '1' ? 1 : 0;

            $old = null;
            if ($id) {
                $st = db()->prepare('SELECT * FROM links WHERE id=?');
                $st->execute([$id]);
                $old = $st->fetch();
                if (!$old) fail('ไม่พบรายการที่ต้องการแก้ไข', 404);
            }

            $file = [
                'original' => $old['file_original'] ?? null, 'stored' => $old['file_stored'] ?? null,
                'mime' => $old['file_mime'] ?? null, 'size' => $old['file_size'] ?? null,
            ];
            $removeOld = false;
            $new = null;

            if ($type === 'document') {
                if (post('remove_file') === '1') { $removeOld = true; $file = array_fill_keys(array_keys($file), null); }
                $new = handle_upload();
                if ($new) { $removeOld = true; $file = $new; }
                if (!$url && !$file['stored']) {
                    fail('ประเภทคู่มือ/เอกสาร ต้องมีลิงก์หรือไฟล์แนบอย่างน้อย 1 อย่าง');
                }
            } else {
                if (!$url) fail('กรุณาใส่ลิงก์');
                if ($file['stored']) { $removeOld = true; $file = array_fill_keys(array_keys($file), null); }
            }

            $pdo = db();
            $pdo->beginTransaction();
            try {
                if ($id) {
                    $pdo->prepare('UPDATE links SET type=?, name=?, url=?, is_visible=?, file_original=?, file_stored=?,
                                   file_mime=?, file_size=?, file_visible=?, updated_by=? WHERE id=?')
                        ->execute([$type, $name, $url, $isVisible, $file['original'], $file['stored'], $file['mime'],
                                   $file['size'], $fileVisible, $u['id'], $id]);
                    $msg = 'บันทึกการแก้ไขเรียบร้อย';
                } else {
                    $st = $pdo->prepare('INSERT INTO links (type, name, url, is_visible, file_original, file_stored, file_mime, file_size,
                                   file_visible, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id');
                    $st->execute([$type, $name, $url, $isVisible, $file['original'], $file['stored'], $file['mime'],
                                  $file['size'], $fileVisible, $u['id'], $u['id']]);
                    $id = (int)$st->fetchColumn();
                    $msg = 'เพิ่มรายการเรียบร้อย';
                }
                if ($removeOld) delete_file_data($id);
                if ($new) save_file_data($id, $new['tmp']);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            out(['message' => $msg, 'id' => $id]);
        }

        case 'link_get': {
            need_admin();
            $st = db()->prepare('SELECT id, type, name, url, is_visible, file_original, file_mime, file_size, file_visible,
                                 (file_stored IS NOT NULL)::int AS has_file FROM links WHERE id=?');
            $st->execute([(int)post('id')]);
            $row = $st->fetch();
            if (!$row) fail('ไม่พบรายการ', 404);
            out(['data' => $row]);
        }

        case 'link_delete': {
            need_admin();
            $id = (int)post('id');
            // ไฟล์ในตาราง link_files และ favorites ถูกลบตามอัตโนมัติ (ON DELETE CASCADE)
            $st = db()->prepare('DELETE FROM links WHERE id=?');
            $st->execute([$id]);
            if (!$st->rowCount()) fail('ไม่พบรายการ', 404);
            out(['message' => 'ลบรายการเรียบร้อย']);
        }

        case 'link_toggle': {
            $u = need_admin();
            $id = (int)post('id');
            $field = post('field') === 'file_visible' ? 'file_visible' : 'is_visible';
            $st = db()->prepare("UPDATE links SET $field = 1 - $field, updated_by=? WHERE id=?");
            $st->execute([$u['id'], $id]);
            if (!$st->rowCount()) fail('ไม่พบรายการ', 404);
            $st = db()->prepare("SELECT $field FROM links WHERE id=?");
            $st->execute([$id]);
            $v = (int)$st->fetchColumn();
            out(['value' => $v, 'message' => $v ? 'เปิดการแสดงผลแล้ว' : 'ปิดการแสดงผลแล้ว']);
        }

        case 'file_info': {
            // ข้อมูลเพิ่มเติมของไฟล์ (รายการไฟล์ใน zip / ตัวอย่างข้อความ)
            $fid = (int)post('id');
            $st = db()->prepare('SELECT file_original, file_stored, is_visible, file_visible FROM links WHERE id=?');
            $st->execute([$fid]);
            $row = $st->fetch();
            if (!$row || !$row['file_stored'] || (!is_admin() && (!$row['is_visible'] || !$row['file_visible']))) fail('ไม่พบไฟล์', 404);
            $ext = strtolower(pathinfo($row['file_stored'], PATHINFO_EXTENSION));
            $info = ['entries' => null, 'text' => null];
            if (!in_array($ext, ['zip', 'txt', 'csv'], true)) out($info);
            $content = link_file_content($fid, $row['file_stored']);
            if ($content === null) fail('ไม่พบเนื้อไฟล์ในระบบ', 404);
            if ($ext === 'zip' && class_exists('ZipArchive')) {
                $path = tempnam(sys_get_temp_dir(), 'udpt');
                file_put_contents($path, $content);
                $z = new ZipArchive();
                if ($z->open($path) === true) {
                    $info['entries'] = [];
                    $info['total'] = $z->numFiles;
                    for ($i = 0; $i < min($z->numFiles, 300); $i++) {
                        $s = $z->statIndex($i);
                        $info['entries'][] = ['name' => $s['name'], 'size' => (int)$s['size']];
                    }
                    $z->close();
                }
                @unlink($path);
            } elseif (in_array($ext, ['txt', 'csv'], true)) {
                $txt = substr($content, 0, 8192);
                if (!mb_check_encoding($txt, 'UTF-8')) {
                    // ไฟล์ภาษาไทยแบบเก่า (TIS-620/CP874) — แปลงด้วย iconv ถ้ามี ไม่เช่นนั้นตัดอักขระที่อ่านไม่ได้
                    $conv = function_exists('iconv') ? @iconv('CP874', 'UTF-8//IGNORE', $txt) : false;
                    $txt = $conv !== false ? $conv : mb_scrub($txt, 'UTF-8');
                }
                $info['text'] = $txt;
            }
            out($info);
        }

        // ================= FAVORITES =================
        case 'fav_set': {
            $u = need_login();
            $lid = (int)post('link_id');
            if (post('state') === '1') {
                $st = db()->prepare('INSERT INTO favorites (user_id, link_id) SELECT ?, id FROM links WHERE id=? ON CONFLICT DO NOTHING');
                $st->execute([$u['id'], $lid]);
            } else {
                db()->prepare('DELETE FROM favorites WHERE user_id=? AND link_id=?')->execute([$u['id'], $lid]);
            }
            out(['favorites' => user_favs($u['id'])]);
        }

        // ================= USERS (ADMIN) =================
        case 'users': {
            need_admin();
            $rows = db()->query('SELECT id, username, fullname, role, is_active,
                                         to_char(last_login, \'YYYY-MM-DD HH24:MI:SS\') AS last_login,
                                         to_char(created_at, \'YYYY-MM-DD HH24:MI:SS\') AS created_at
                                  FROM users ORDER BY role, username')->fetchAll();
            foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['is_active'] = (int)$r['is_active']; }
            out(['data' => $rows]);
        }

        case 'user_save': {
            $me = need_admin();
            $id = (int)post('id', 0);
            $username = trim(post('username'));
            $fullname = trim(post('fullname'));
            $role = post('role') === 'admin' ? 'admin' : 'staff';
            $active = post('is_active', '1') === '1' ? 1 : 0;
            $password = (string)post('password');

            if (!preg_match('~^[A-Za-z0-9_.\-]{3,50}$~', $username)) fail('ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ . - ความยาว 3-50 ตัว');
            if ($fullname === '') fail('กรุณากรอกชื่อ-นามสกุล');
            if (!$id && mb_strlen($password) < 6) fail('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
            if ($id && $password !== '' && mb_strlen($password) < 6) fail('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');

            $st = db()->prepare('SELECT COUNT(*) FROM users WHERE lower(username)=lower(?) AND id<>?');
            $st->execute([$username, $id]);
            if ($st->fetchColumn()) fail('ชื่อผู้ใช้นี้มีในระบบแล้ว');

            if ($id) {
                if ($id === $me['id'] && ($role !== 'admin' || !$active)) fail('ไม่สามารถลดสิทธิ์หรือระงับบัญชีของตนเองได้');
                $sql = 'UPDATE users SET username=?, fullname=?, role=?, is_active=?' . ($password !== '' ? ', password_hash=?' : '') . ' WHERE id=?';
                $p = [$username, $fullname, $role, $active];
                if ($password !== '') $p[] = password_hash($password, PASSWORD_DEFAULT);
                $p[] = $id;
                db()->prepare($sql)->execute($p);
                if ($id === $me['id']) { $_SESSION['user']['username'] = $username; $_SESSION['user']['fullname'] = $fullname; session_save(); }
                out(['message' => 'บันทึกข้อมูลผู้ใช้เรียบร้อย']);
            }
            db()->prepare('INSERT INTO users (username, password_hash, fullname, role, is_active) VALUES (?,?,?,?,?)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullname, $role, $active]);
            out(['message' => 'เพิ่มผู้ใช้เรียบร้อย']);
        }

        case 'user_delete': {
            $me = need_admin();
            $id = (int)post('id');
            if ($id === $me['id']) fail('ไม่สามารถลบบัญชีของตนเองได้');
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            out(['message' => 'ลบผู้ใช้เรียบร้อย']);
        }

        default:
            fail('ไม่พบคำสั่ง: ' . $action, 404);
    }
} catch (PDOException $e) {
    error_log('[UDTC-Portal] ' . $e->getMessage());
    if ($e->getCode() === '42P01') fail('ยังไม่ได้ติดตั้งฐานข้อมูล กรุณาเปิดหน้า /install.php ก่อน', 500);
    fail('เกิดข้อผิดพลาดกับฐานข้อมูล กรุณาลองใหม่อีกครั้ง', 500);
} catch (RuntimeException $e) {
    fail($e->getMessage(), 500);
}
