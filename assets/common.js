/* ===== UDTC-Portal : ฟังก์ชันใช้ร่วม ===== */
(function () {
    const B = window.UDPT;

    window.SwalX = Swal.mixin({
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ตกลง',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });

    window.Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 2200, timerProgressBar: true,
        didOpen: t => { t.onmouseenter = Swal.stopTimer; t.onmouseleave = Swal.resumeTimer; },
    });

    function toFD(data) {
        if (data instanceof FormData) return data;
        const fd = new FormData();
        Object.entries(data || {}).forEach(([k, v]) => fd.append(k, v ?? ''));
        return fd;
    }

    /** เรียก API (POST) คืนค่า JSON หรือ throw Error พร้อมข้อความภาษาไทย */
    window.api = async function (action, data = {}) {
        const fd = toFD(data);
        fd.append('csrf', B.csrf);
        let res, j;
        try {
            res = await fetch('api.php?action=' + encodeURIComponent(action), { method: 'POST', body: fd, credentials: 'same-origin' });
        } catch (e) { throw new Error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); }
        try { j = await res.json(); } catch (e) { throw new Error('เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (HTTP ' + res.status + ')'); }
        if (!j.ok) { const err = new Error(j.message || 'เกิดข้อผิดพลาด'); err.status = res.status; throw err; }
        return j;
    };

    /** อัปโหลดพร้อมแสดงความคืบหน้า */
    window.apiUpload = function (action, data, onProgress) {
        return new Promise((resolve, reject) => {
            const fd = toFD(data);
            fd.append('csrf', B.csrf);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?action=' + encodeURIComponent(action));
            xhr.upload.onprogress = e => e.lengthComputable && onProgress && onProgress(Math.round(e.loaded / e.total * 100));
            xhr.onload = () => {
                let j; try { j = JSON.parse(xhr.responseText); } catch (e) { return reject(new Error('เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (HTTP ' + xhr.status + ')')); }
                j.ok ? resolve(j) : reject(new Error(j.message || 'เกิดข้อผิดพลาด'));
            };
            xhr.onerror = () => reject(new Error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'));
            xhr.send(fd);
        });
    };

    window.esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    window.fmtSize = function (b) {
        if (b == null) return '-';
        const u = ['B', 'KB', 'MB', 'GB']; let i = 0; b = +b;
        while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
        return (i ? b.toFixed(b < 10 ? 2 : 1) : b) + ' ' + u[i];
    };

    window.fmtDate = function (s) {
        if (!s) return '-';
        const d = new Date(s.replace(' ', 'T'));
        return isNaN(d) ? s : d.toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' });
    };

    /** ไอคอนและหมวดของไฟล์จากชื่อไฟล์ */
    window.fileMeta = function (name) {
        const ext = (String(name).split('.').pop() || '').toLowerCase();
        const map = [
            [['pdf'], 'fa-file-pdf', 'bg-rose-100 text-rose-600', 'PDF', 'pdf'],
            [['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], 'fa-file-image', 'bg-fuchsia-100 text-fuchsia-600', 'รูปภาพ', 'image'],
            [['doc', 'docx', 'odt', 'rtf'], 'fa-file-word', 'bg-blue-100 text-blue-600', 'เอกสาร Word', 'doc'],
            [['xls', 'xlsx', 'ods', 'csv'], 'fa-file-excel', 'bg-emerald-100 text-emerald-600', 'สเปรดชีต', 'sheet'],
            [['ppt', 'pptx', 'odp'], 'fa-file-powerpoint', 'bg-orange-100 text-orange-600', 'งานนำเสนอ', 'slide'],
            [['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz'], 'fa-file-zipper', 'bg-yellow-100 text-yellow-700', 'ไฟล์บีบอัด', 'archive'],
            [['txt'], 'fa-file-lines', 'bg-slate-200 text-slate-600', 'ข้อความ', 'text'],
        ];
        for (const [exts, icon, cls, label, kind] of map) if (exts.includes(ext)) return { ext, icon, cls, label, kind };
        return { ext, icon: 'fa-file', cls: 'bg-slate-200 text-slate-600', label: 'ไฟล์', kind: 'other' };
    };

    // ---------- Favorite ในเครื่อง ----------
    const LS_KEY = 'udpt_favorites';
    window.LocalFav = {
        get() { try { return (JSON.parse(localStorage.getItem(LS_KEY)) || []).map(Number); } catch (e) { return []; } },
        set(ids) { try { localStorage.setItem(LS_KEY, JSON.stringify([...new Set(ids.map(Number))])); } catch (e) { } },
    };

    window.DT_TH = {
        search: '', searchPlaceholder: 'ค้นหา...',
        lengthMenu: 'แสดง _MENU_ รายการ',
        info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
        infoEmpty: 'ไม่มีรายการ', infoFiltered: '(กรองจาก _MAX_ รายการ)',
        zeroRecords: 'ไม่พบรายการที่ค้นหา', emptyTable: 'ยังไม่มีข้อมูล',
        loadingRecords: 'กำลังโหลด...', processing: 'กำลังประมวลผล...',
        paginate: { first: '«', last: '»', next: '›', previous: '‹' },
    };

    // ---------- บัญชีผู้ใช้ ----------
    window.UDPTAuth = {
        async login() {
            const r = await SwalX.fire({
                title: 'เข้าสู่ระบบ',
                html: `
                    <div class="text-center text-slate-500 text-sm mb-4">สำหรับผู้ดูแลระบบและเจ้าหน้าที่</div>
                    <div class="f-group"><label class="f-label">ชื่อผู้ใช้</label>
                        <input id="lg-user" class="f-input" autocomplete="username" placeholder="username"></div>
                    <div class="f-group"><label class="f-label">รหัสผ่าน</label>
                        <input id="lg-pass" type="password" class="f-input" autocomplete="current-password" placeholder="••••••"></div>`,
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ',
                focusConfirm: false,
                didOpen: () => {
                    const u = document.getElementById('lg-user'), p = document.getElementById('lg-pass');
                    u.focus();
                    [u, p].forEach(el => el.addEventListener('keydown', e => e.key === 'Enter' && Swal.clickConfirm()));
                },
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    const username = document.getElementById('lg-user').value.trim();
                    const password = document.getElementById('lg-pass').value;
                    if (!username || !password) return Swal.showValidationMessage('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
                    try {
                        return await api('login', { username, password, local_favs: JSON.stringify(LocalFav.get()) });
                    } catch (e) { Swal.showValidationMessage(e.message); }
                },
                allowOutsideClick: () => !Swal.isLoading(),
            });
            if (r.isConfirmed && r.value) {
                LocalFav.set(r.value.favorites || []);
                await Swal.fire({ icon: 'success', title: 'ยินดีต้อนรับ', text: r.value.user.fullname || r.value.user.username, timer: 1200, showConfirmButton: false });
                location.reload();
            }
        },

        async logout() {
            const r = await SwalX.fire({
                icon: 'question', title: 'ออกจากระบบ?', text: 'รายการโปรดของคุณจะยังถูกเก็บไว้ในเครื่องนี้',
                showCancelButton: true, confirmButtonText: 'ออกจากระบบ', confirmButtonColor: '#e11d48',
            });
            if (!r.isConfirmed) return;
            try {
                await api('logout');
                await Swal.fire({ icon: 'success', title: 'ออกจากระบบแล้ว', timer: 1000, showConfirmButton: false });
                location.href = 'index.php';
            } catch (e) { SwalX.fire({ icon: 'error', title: 'ผิดพลาด', text: e.message }); }
        },

        async changePassword() {
            const r = await SwalX.fire({
                title: 'เปลี่ยนรหัสผ่าน',
                html: `
                    <div class="f-group"><label class="f-label">รหัสผ่านเดิม <span class="req">*</span></label><input id="cp-old" type="password" class="f-input"></div>
                    <div class="f-group"><label class="f-label">รหัสผ่านใหม่ <span class="req">*</span></label><input id="cp-new" type="password" class="f-input"><div class="f-help">อย่างน้อย 6 ตัวอักษร</div></div>
                    <div class="f-group"><label class="f-label">ยืนยันรหัสผ่านใหม่ <span class="req">*</span></label><input id="cp-new2" type="password" class="f-input"></div>`,
                showCancelButton: true, confirmButtonText: 'บันทึก', focusConfirm: false, showLoaderOnConfirm: true,
                preConfirm: async () => {
                    const o = document.getElementById('cp-old').value, n = document.getElementById('cp-new').value, n2 = document.getElementById('cp-new2').value;
                    if (!o || !n) return Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบ');
                    if (n.length < 6) return Swal.showValidationMessage('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร');
                    if (n !== n2) return Swal.showValidationMessage('ยืนยันรหัสผ่านไม่ตรงกัน');
                    try { return await api('change_password', { old_password: o, new_password: n }); }
                    catch (e) { Swal.showValidationMessage(e.message); }
                },
            });
            if (r.isConfirmed && r.value) Toast.fire({ icon: 'success', title: r.value.message });
        },
    };

    /** แจ้งข้อผิดพลาด ถ้าเซสชันหมดอายุให้รีเฟรช */
    window.showError = function (e, title = 'เกิดข้อผิดพลาด') {
        return SwalX.fire({ icon: 'error', title, text: e.message || String(e) }).then(() => {
            if (e.status === 401 || e.status === 419) location.reload();
        });
    };
})();
