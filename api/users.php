<?php
require_once __DIR__ . '/_lib/config.php';
if (!is_admin()) {
    header('Location: index.php');
    exit;
}
$pageTitle = 'จัดการผู้ใช้';
$pageScripts = ['assets/users.js'];
require __DIR__ . '/_lib/header.php';
?>

<div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="index.php" class="hover:text-brand-600"><i class="fa-solid fa-house"></i> หน้าแรก</a>
    <i class="fa-solid fa-chevron-right text-[10px]"></i>
    <span class="text-slate-700">จัดการผู้ใช้</span>
</div>

<section class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center gap-3 px-5 pt-5">
        <div class="mr-auto">
            <h1 class="text-lg font-semibold text-slate-800"><i class="fa-solid fa-users-gear text-brand-500"></i> ผู้ใช้งานระบบหลังบ้าน</h1>
            <p class="text-sm text-slate-500">
                <span class="type-badge bg-indigo-100 text-indigo-700">ผู้ดูแลระบบ</span> เพิ่ม/ลบ/แก้ไขรายการ และจัดการผู้ใช้ได้ ·
                <span class="type-badge bg-slate-200 text-slate-700">เจ้าหน้าที่</span> ใช้งานและบันทึกรายการโปรดไว้ในบัญชี
            </p>
        </div>
        <button type="button" id="btnAddUser" class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-medium rounded-xl px-4 py-2.5 shadow-sm transition">
            <i class="fa-solid fa-user-plus"></i> เพิ่มผู้ใช้
        </button>
    </div>
    <div class="p-5 overflow-x-auto">
        <table id="usersTable" class="display w-full" style="width:100%">
            <thead>
            <tr>
                <th>ชื่อผู้ใช้</th>
                <th>ชื่อ-นามสกุล</th>
                <th>สิทธิ์</th>
                <th class="text-center">สถานะ</th>
                <th>เข้าใช้งานล่าสุด</th>
                <th class="text-center">จัดการ</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/_lib/footer.php'; ?>
