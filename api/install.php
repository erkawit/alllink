<?php
/**
 * ติดตั้งฐานข้อมูล UDTC-Portal บน Neon (PostgreSQL)
 * - สร้างตาราง (database/neon/schema.sql) — รันซ้ำได้ ไม่ลบข้อมูลเดิม
 * - เพิ่มข้อมูลเริ่มต้น (database/neon/seed.sql) เฉพาะเมื่อยังไม่มีผู้ใช้/ลิงก์
 * เพื่อความปลอดภัย: เมื่อติดตั้งแล้ว (มีผู้ใช้ในระบบ) หน้านี้จะใช้ได้เฉพาะผู้ดูแลระบบที่ล็อกอินอยู่
 */
define('UDPT_NO_SESSION', true);
require_once __DIR__ . '/_lib/config.php';

$steps = [];
$error = null;
$locked = false;

function sql_file(string $name): string
{
    foreach ([__DIR__ . '/../database/neon/' . $name, __DIR__ . '/_lib/' . $name] as $p) {
        if (is_file($p)) return (string)file_get_contents($p);
    }
    throw new RuntimeException('ไม่พบไฟล์ ' . $name);
}

try {
    $pdo = db();
    $steps[] = 'เชื่อมต่อ Neon สำเร็จ (' . $pdo->query('SELECT current_database()')->fetchColumn() . ')';

    $installed = (bool)$pdo->query("SELECT to_regclass('public.users') IS NOT NULL")->fetchColumn()
        && (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;

    if ($installed) {
        // ติดตั้งแล้ว — อนุญาตเฉพาะผู้ดูแลระบบ (ใช้ session ปกติ)
        session_set_save_handler(new DbSessionHandler(), true);
        session_name('UDPTSESSID');
        session_start();
        if ((($_SESSION['user']['role'] ?? '') !== 'admin')) {
            $locked = true;
        }
    }

    if (!$locked) {
        $pdo->exec(sql_file('schema.sql'));
        $steps[] = 'สร้าง/ตรวจสอบตาราง users, links, link_files, favorites, sessions';

        $before = (int)$pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
        $pdo->exec(sql_file('seed.sql'));
        $after = (int)$pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
        $steps[] = $installed ? 'มีบัญชีผู้ใช้อยู่แล้ว (ข้าม)' : 'สร้างบัญชีผู้ดูแลระบบ: admin / admin1234';
        $steps[] = $before === 0 ? 'นำเข้ารายการลิงก์เริ่มต้น ' . $after . ' รายการ' : 'มีรายการลิงก์อยู่แล้ว ' . $before . ' รายการ (ข้าม)';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>ติดตั้ง <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;600&display=swap" rel="stylesheet">
    <style>body{font-family:'IBM Plex Sans Thai',sans-serif}</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-lg">
    <h1 class="text-2xl font-semibold text-slate-800">ติดตั้ง <?= h(APP_NAME) ?></h1>
    <p class="text-slate-500 mb-6"><?= h(APP_SUBTITLE) ?> · Neon PostgreSQL</p>
    <ul class="space-y-2 mb-6">
        <?php foreach ($steps as $s): ?>
            <li class="flex gap-2 text-slate-700"><span class="text-emerald-600">✔</span><?= h($s) ?></li>
        <?php endforeach; ?>
        <?php if ($locked): ?>
            <li class="flex gap-2 text-amber-700"><span>🔒</span>ระบบติดตั้งแล้ว — ต้องเข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบก่อนจึงจะรันซ้ำได้</li>
        <?php endif; ?>
    </ul>
    <a href="/" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-5 py-2">ไปหน้าแรก</a>
</div>
<script>
<?php if ($error): ?>
Swal.fire({icon:'error', title:'ติดตั้งไม่สำเร็จ', html: <?= json_encode('ตรวจสอบค่า DATABASE_URL ของ Neon ใน Environment Variables<br><small>' . h($error) . '</small>', JSON_UNESCAPED_UNICODE) ?>});
<?php elseif (!$locked): ?>
Swal.fire({icon:'success', title:'ติดตั้งเรียบร้อย', html:'บัญชีเริ่มต้น <b>admin</b> / <b>admin1234</b><br><small>กรุณาเข้าสู่ระบบแล้วเปลี่ยนรหัสผ่านทันที</small>'});
<?php endif; ?>
</script>
</body>
</html>
