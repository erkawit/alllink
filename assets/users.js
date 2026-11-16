/* ===== UDTC-Portal : จัดการผู้ใช้ ===== */
(function () {
    const me = window.UDPT.user;
    let rows = [];

    const table = new DataTable('#usersTable', {
        data: [],
        language: DT_TH,
        order: [[2, 'asc'], [0, 'asc']],
        columns: [
            { data: 'username', render: (d, t, r) => t === 'display' ? `<span class="font-semibold">${esc(d)}</span>${r.id === me.id ? ' <span class="type-badge bg-emerald-100 text-emerald-700">คุณ</span>' : ''}` : d },
            { data: 'fullname', render: d => esc(d) },
            { data: 'role', render: (d, t) => t === 'display'
                ? (d === 'admin' ? '<span class="type-badge bg-indigo-100 text-indigo-700"><i class="fa-solid fa-user-shield"></i> ผู้ดูแลระบบ</span>'
                                 : '<span class="type-badge bg-slate-200 text-slate-700"><i class="fa-solid fa-user"></i> เจ้าหน้าที่</span>')
                : d },
            { data: 'is_active', className: 'text-center', render: (d, t) => t === 'display'
                ? (d ? '<span class="type-badge bg-emerald-100 text-emerald-700">ใช้งาน</span>' : '<span class="type-badge bg-rose-100 text-rose-700">ระงับ</span>')
                : d },
            { data: 'last_login', render: (d, t) => t === 'display' ? `<span class="text-sm text-slate-500">${fmtDate(d)}</span>` : (d || '') },
            { data: null, orderable: false, className: 'text-center whitespace-nowrap', render: (d, t, r) =>
                `<button type="button" class="act-btn edit" data-act="edit" data-id="${r.id}" title="แก้ไข"><i class="fa-solid fa-pen"></i></button>
                 ${r.id !== me.id ? `<button type="button" class="act-btn del ml-1" data-act="del" data-id="${r.id}" title="ลบ"><i class="fa-solid fa-trash"></i></button>` : ''}` },
        ],
    });

    async function load() {
        try {
            const r = await api('users');
            rows = r.data;
            table.clear().rows.add(rows).draw();
        } catch (e) { showError(e, 'โหลดข้อมูลผู้ใช้ไม่สำเร็จ'); }
    }

    async function openForm(row = null) {
        const isEdit = !!row, self = row && row.id === me.id;
        const r = await SwalX.fire({
            title: isEdit ? '<i class="fa-solid fa-user-pen text-indigo-500"></i> แก้ไขผู้ใช้' : '<i class="fa-solid fa-user-plus text-indigo-500"></i> เพิ่มผู้ใช้',
            width: 560, focusConfirm: false, showCancelButton: true, showLoaderOnConfirm: true,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk"></i> บันทึก',
            html: `
                <div class="f-group"><label class="f-label">ชื่อผู้ใช้ <span class="req">*</span></label>
                    <input id="u-username" class="f-input" value="${esc(row?.username)}" autocomplete="off">
                    <div class="f-help">a-z, 0-9, _ . - ความยาว 3-50 ตัว</div></div>
                <div class="f-group"><label class="f-label">ชื่อ-นามสกุล <span class="req">*</span></label>
                    <input id="u-fullname" class="f-input" value="${esc(row?.fullname)}"></div>
                <div class="f-group"><label class="f-label">รหัสผ่าน ${isEdit ? '<span class="text-slate-400 font-normal">(เว้นว่างหากไม่เปลี่ยน)</span>' : '<span class="req">*</span>'}</label>
                    <input id="u-password" type="password" class="f-input" autocomplete="new-password"></div>
                <div class="f-group"><label class="f-label">สิทธิ์การใช้งาน</label>
                    <div class="type-pick compact" id="u-roles" style="grid-template-columns:1fr 1fr">
                        <button type="button" data-role="staff" class="${row?.role !== 'admin' ? 'sel' : ''}" ${self ? 'disabled' : ''}><i class="fa-solid fa-user"></i><span>เจ้าหน้าที่</span></button>
                        <button type="button" data-role="admin" class="${row?.role === 'admin' ? 'sel' : ''}" ${self ? 'disabled' : ''}><i class="fa-solid fa-user-shield"></i><span>ผู้ดูแลระบบ</span></button>
                    </div></div>
                <div class="toggle-row"><div><div class="t">เปิดใช้งานบัญชี</div><div class="s">ปิดเพื่อระงับการเข้าสู่ระบบ</div></div>
                    <label class="switch"><input type="checkbox" id="u-active" ${!row || row.is_active ? 'checked' : ''} ${self ? 'disabled' : ''}><span class="slider"></span></label></div>`,
            didOpen: p => {
                p.querySelectorAll('#u-roles button').forEach(b => b.onclick = () =>
                    p.querySelectorAll('#u-roles button').forEach(x => x.classList.toggle('sel', x === b)));
                p.querySelector('#u-username').focus();
            },
            preConfirm: async () => {
                const p = Swal.getPopup(), v = s => p.querySelector(s).value.trim();
                const data = {
                    id: row?.id || 0, username: v('#u-username'), fullname: v('#u-fullname'),
                    password: p.querySelector('#u-password').value,
                    role: p.querySelector('#u-roles .sel')?.dataset.role || 'staff',
                    is_active: p.querySelector('#u-active').checked ? '1' : '0',
                };
                if (!data.username || !data.fullname) return Swal.showValidationMessage('กรุณากรอกชื่อผู้ใช้และชื่อ-นามสกุล');
                if (!isEdit && data.password.length < 6) return Swal.showValidationMessage('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
                try { return await api('user_save', data); } catch (e) { Swal.showValidationMessage(e.message); }
            },
            allowOutsideClick: () => !Swal.isLoading(),
        });
        if (r.isConfirmed && r.value) { Toast.fire({ icon: 'success', title: r.value.message }); load(); }
    }

    async function deleteRow(row) {
        const r = await SwalX.fire({
            icon: 'warning', title: 'ลบผู้ใช้?', html: `ต้องการลบบัญชี <b>${esc(row.username)}</b> (${esc(row.fullname)}) ใช่หรือไม่?<br><small class="text-slate-500">รายการโปรดในบัญชีนี้จะถูกลบด้วย</small>`,
            showCancelButton: true, confirmButtonText: '<i class="fa-solid fa-trash"></i> ลบ', confirmButtonColor: '#e11d48', showLoaderOnConfirm: true,
            preConfirm: async () => { try { return await api('user_delete', { id: row.id }); } catch (e) { Swal.showValidationMessage(e.message); } },
        });
        if (r.isConfirmed && r.value) { Toast.fire({ icon: 'success', title: r.value.message }); load(); }
    }

    document.querySelector('#usersTable tbody').addEventListener('click', e => {
        const el = e.target.closest('[data-act]');
        if (!el) return;
        const row = rows.find(x => x.id === +el.dataset.id);
        if (el.dataset.act === 'edit') openForm(row);
        if (el.dataset.act === 'del') deleteRow(row);
    });
    document.getElementById('btnAddUser').addEventListener('click', () => openForm());
    load();
})();
