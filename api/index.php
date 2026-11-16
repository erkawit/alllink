<?php
require_once __DIR__ . '/_lib/config.php';
$pageTitle = '';
$pageScripts = ['assets/app.js'];
require __DIR__ . '/_lib/header.php';
$isAdmin = is_admin();
?>

<!-- สรุปจำนวน -->
<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <button type="button" data-filter="all" class="stat-card filter-btn active">
        <span class="stat-icon bg-indigo-100 text-indigo-600"><i class="fa-solid fa-border-all"></i></span>
        <span><span class="stat-label">ทั้งหมด</span><span class="stat-num" id="cnt-all">0</span></span>
    </button>
    <button type="button" data-filter="website" class="stat-card filter-btn">
        <span class="stat-icon bg-sky-100 text-sky-600"><i class="fa-solid fa-globe"></i></span>
        <span><span class="stat-label">เว็บไซต์</span><span class="stat-num" id="cnt-website">0</span></span>
    </button>
    <button type="button" data-filter="system" class="stat-card filter-btn">
        <span class="stat-icon bg-emerald-100 text-emerald-600"><i class="fa-solid fa-gears"></i></span>
        <span><span class="stat-label">ระบบงาน / โปรแกรม</span><span class="stat-num" id="cnt-system">0</span></span>
    </button>
    <button type="button" data-filter="document" class="stat-card filter-btn">
        <span class="stat-icon bg-amber-100 text-amber-600"><i class="fa-solid fa-book"></i></span>
        <span><span class="stat-label">คู่มือ / เอกสาร</span><span class="stat-num" id="cnt-document">0</span></span>
    </button>
</section>

<section class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center gap-3 px-5 pt-5">
        <div class="mr-auto">
            <h1 class="text-lg font-semibold text-slate-800">รายการเว็บไซต์ ระบบงาน และเอกสาร</h1>
            <p class="text-sm text-slate-500">กด <i class="fa-solid fa-star text-amber-400"></i> เพื่อปักหมุดเป็นรายการโปรด รายการโปรดจะแสดงด้านบนเสมอ
                <?php if (!current_user()): ?><span class="text-slate-400">(บันทึกไว้ในเครื่องนี้)</span><?php endif; ?>
            </p>
        </div>
        <button type="button" data-filter="fav" class="filter-btn chip">
            <i class="fa-solid fa-star text-amber-400"></i> เฉพาะรายการโปรด <span id="cnt-fav" class="chip-num">0</span>
        </button>
        <?php if ($isAdmin): ?>
            <button type="button" id="btnAdd" class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-medium rounded-xl px-4 py-2.5 shadow-sm transition">
                <i class="fa-solid fa-plus"></i> เพิ่มรายการ
            </button>
        <?php endif; ?>
    </div>

    <div class="p-5 overflow-x-auto">
        <table id="linksTable" class="display w-full" style="width:100%">
            <thead>
            <tr>
                <th>fav</th>
                <th>sort</th>
                <th class="w-12 text-center"><i class="fa-solid fa-star"></i></th>
                <th>ชื่อเว็บไซต์ / ระบบ</th>
                <th>ประเภท</th>
                <th class="text-center">ลิงก์</th>
                <?php if ($isAdmin): ?>
                    <th class="text-center">แสดงผล</th>
                    <th class="text-center">จัดการ</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/_lib/footer.php'; ?>
