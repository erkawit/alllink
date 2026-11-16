<?php
/** @var string $pageTitle */
$u = current_user();
$boot = [
    'csrf'  => csrf_token(),
    'user'  => $u,
    'maxMb' => MAX_UPLOAD_MB,
    'exts'  => ALLOWED_EXT,
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(($pageTitle ?? '') ? $pageTitle . ' · ' . APP_NAME : APP_NAME . ' · ' . APP_SUBTITLE) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"IBM Plex Sans Thai"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: { brand: { 50:'#eef2ff',100:'#e0e7ff',500:'#4f46e5',600:'#4338ca',700:'#3730a3',900:'#1e1b4b' } }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/app.css?v=<?= APP_VERSION ?>">
    <script>window.UDPT = <?= json_encode($boot, JSON_UNESCAPED_UNICODE) ?>;</script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col">

<header class="bg-gradient-to-r from-brand-900 via-brand-700 to-indigo-600 text-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap items-center gap-4">
        <a href="index.php" class="flex items-center gap-3 mr-auto">
            <div class="w-12 h-12 rounded-xl bg-white/15 ring-1 ring-white/30 flex items-center justify-center text-xl">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <div class="text-xl font-bold tracking-wide leading-tight"><?= h(APP_NAME) ?></div>
                <div class="text-sm text-indigo-100"><?= h(APP_SUBTITLE) ?></div>
            </div>
        </a>

        <?php if ($u): ?>
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($u['role'] === 'admin'): ?>
                    <a href="users.php" class="hdr-btn"><i class="fa-solid fa-users-gear"></i><span>จัดการผู้ใช้</span></a>
                <?php endif; ?>
                <button type="button" onclick="UDPTAuth.changePassword()" class="hdr-btn"><i class="fa-solid fa-key"></i><span>เปลี่ยนรหัสผ่าน</span></button>
                <div class="flex items-center gap-2 bg-white/10 rounded-xl pl-3 pr-1 py-1 ring-1 ring-white/20">
                    <div class="text-right leading-tight">
                        <div class="text-sm font-semibold"><?= h($u['fullname'] ?: $u['username']) ?></div>
                        <div class="text-[11px] text-indigo-100"><?= $u['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'เจ้าหน้าที่' ?></div>
                    </div>
                    <button type="button" onclick="UDPTAuth.logout()" class="w-9 h-9 rounded-lg bg-white/15 hover:bg-rose-500 transition" title="ออกจากระบบ">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <button type="button" onclick="UDPTAuth.login()" class="hdr-btn bg-white !text-brand-700 hover:!bg-indigo-50">
                <i class="fa-solid fa-right-to-bracket"></i><span>เข้าสู่ระบบ</span>
            </button>
        <?php endif; ?>
    </div>
</header>

<main class="flex-1 w-full max-w-7xl mx-auto px-4 py-6">
