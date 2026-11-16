/* ===== UDTC-Portal : หน้าแรก ===== */
(function () {
    const B = window.UDPT;
    const isAdmin = B.user && B.user.role === 'admin';
    const collator = new Intl.Collator('th', { sensitivity: 'base', numeric: true });

    const TYPES = {
        website:  { label: 'เว็บไซต์',            icon: 'fa-globe',  nameLabel: 'ชื่อเว็บไซต์' },
        system:   { label: 'ระบบงาน / โปรแกรม',   icon: 'fa-gears',  nameLabel: 'ชื่อระบบงานหรือโปรแกรม' },
        document: { label: 'คู่มือ / เอกสาร',      icon: 'fa-book',   nameLabel: 'ชื่อคู่มือหรือเอกสาร' },
    };

    let rows = [];
    let favs = new Set(LocalFav.get());
    let filter = 'all';
    let popId = null;
    let table;

    // ================= ตาราง =================
    function hostOf(url) { try { return new URL(url).host; } catch (e) { return url || ''; } }

    function renderName(d, t, r) {
        if (t !== 'display') return [r.name, r.url, r.file_original].filter(Boolean).join(' ');
        let html = `<div class="font-semibold text-slate-800">${esc(r.name)}`;
        if (isAdmin && !r.is_visible) html += ` <span class="type-badge bg-slate-200 text-slate-600 ml-1"><i class="fa-solid fa-eye-slash"></i> ซ่อนอยู่</span>`;
        html += `</div>`;
        if (r.url) html += `<div class="text-xs text-slate-400 truncate max-w-md">${esc(hostOf(r.url))}</div>`;
        if (r.has_file) {
            const m = fileMeta(r.file_original);
            html += `<div class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                        <i class="fa-solid ${m.icon} ${m.cls.split(' ').pop()}"></i>
                        <span class="truncate max-w-xs">${esc(r.file_original)}</span>
                        <span class="text-slate-400">· ${fmtSize(r.file_size)}</span>
                        ${isAdmin && !r.file_visible ? '<span class="type-badge bg-slate-200 text-slate-600">ไม่แสดงไฟล์</span>' : ''}
                     </div>`;
        }
        return html;
    }

    function renderLink(d, t, r) {
        if (t !== 'display') return '';
        const out = [];
        if (r.url) {
            const [cls, ic, tx] = r.type === 'website' ? ['web', 'fa-arrow-up-right-from-square', 'เปิดเว็บไซต์']
                : r.type === 'system' ? ['sys', 'fa-right-to-bracket', 'เข้าใช้งานระบบ']
                : ['doc', 'fa-link', 'เปิดลิงก์'];
            out.push(`<a href="${esc(r.url)}" target="_blank" rel="noopener noreferrer" class="link-btn ${cls}" data-act="open" data-id="${r.id}"><i class="fa-solid ${ic}"></i> ${tx}</a>`);
        }
        if (r.has_file) out.push(`<button type="button" class="link-btn file" data-act="file" data-id="${r.id}"><i class="fa-solid fa-eye"></i> ดูไฟล์</button>`);
        return `<div class="flex flex-wrap justify-center gap-1.5">${out.join('') || '<span class="text-slate-300">-</span>'}</div>`;
    }

    const columns = [
        { data: 'fav', visible: false },
        { data: 'sortIdx', visible: false },
        {
            data: null, orderable: false, className: 'text-center', render: (d, t, r) => t === 'display'
                ? `<button type="button" class="star-btn ${r.fav ? 'on' : ''}" data-act="star" data-id="${r.id}" title="${r.fav ? 'นำออกจากรายการโปรด' : 'เพิ่มเป็นรายการโปรด'}"><i class="fa-${r.fav ? 'solid' : 'regular'} fa-star"></i></button>`
                : r.fav,
        },
        { data: 'name', orderData: [1], render: renderName },
        {
            data: 'type', orderable: false, render: (d, t) => t === 'display'
                ? `<span class="type-badge type-${d}"><i class="fa-solid ${TYPES[d].icon}"></i> ${TYPES[d].label}</span>`
                : TYPES[d].label,
        },
        { data: null, orderable: false, className: 'text-center', render: renderLink },
    ];
    if (isAdmin) {
        columns.push({
            data: 'is_visible', orderable: false, className: 'text-center', render: (d, t, r) => t === 'display'
                ? `<label class="switch" title="เปิด/ปิดการแสดงผล"><input type="checkbox" data-act="toggle" data-id="${r.id}" ${d ? 'checked' : ''}><span class="slider"></span></label>`
                : d,
        });
        columns.push({
            data: null, orderable: false, className: 'text-center whitespace-nowrap', render: (d, t, r) => t === 'display'
                ? `<button type="button" class="act-btn edit" data-act="edit" data-id="${r.id}" title="แก้ไข"><i class="fa-solid fa-pen"></i></button>
                   <button type="button" class="act-btn del ml-1" data-act="del" data-id="${r.id}" title="ลบ"><i class="fa-solid fa-trash"></i></button>`
                : '',
        });
    }

    DataTable.ext.search.push((settings, data, idx, row) => {
        if (settings.nTable.id !== 'linksTable') return true;
        if (filter === 'all') return true;
        if (filter === 'fav') return !!row.fav;
        return row.type === filter;
    });

    table = new DataTable('#linksTable', {
        data: [],
        columns,
        orderFixed: { pre: [0, 'desc'] },  // รายการโปรดอยู่บนสุดเสมอ
        order: [[3, 'asc']],                // แล้วเรียงตามตัวอักษร
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, { label: 'ทั้งหมด', value: -1 }],
        language: DT_TH,
        autoWidth: false,
        rowCallback: (tr, r) => {
            tr.classList.toggle('row-fav', !!r.fav);
            tr.classList.toggle('row-hidden', isAdmin && !r.is_visible);
        },
        drawCallback: function () {
            const api = this.api();
            const all = api.rows({ search: 'applied' }).data().toArray();
            const nFav = all.filter(r => r.fav).length;
            if (filter !== 'fav' && nFav > 0) {
                const nodes = api.rows({ page: 'current' }).nodes().toArray();
                const data = api.rows({ page: 'current' }).data().toArray();
                const span = api.columns(':visible').count();
                let last = null;
                data.forEach((r, i) => {
                    const g = r.fav ? 'fav' : 'all';
                    if (g === last) return;
                    last = g;
                    const label = g === 'fav'
                        ? `<i class="fa-solid fa-star mr-1"></i> รายการโปรด (${nFav})`
                        : `<i class="fa-solid fa-list mr-1"></i> รายการทั้งหมด (${all.length - nFav})`;
                    $(nodes[i]).before(`<tr class="group-row ${g === 'fav' ? 'is-fav' : ''}"><td colspan="${span}">${label}</td></tr>`);
                });
            }
            if (popId) {
                const b = document.querySelector(`#linksTable [data-act="star"][data-id="${popId}"]`);
                if (b) b.classList.add('pop');
                popId = null;
            }
        },
    });

    function applySort() {
        const sorted = [...rows].sort((a, b) => collator.compare(a.name, b.name));
        sorted.forEach((r, i) => r.sortIdx = i);
    }

    function updateCounts() {
        const c = { all: rows.length, website: 0, system: 0, document: 0, fav: 0 };
        rows.forEach(r => { c[r.type]++; if (r.fav) c.fav++; });
        Object.entries(c).forEach(([k, v]) => { const el = document.getElementById('cnt-' + k); if (el) el.textContent = v; });
    }

    function applyFavs() {
        table.rows().every(function () {
            const d = this.data();
            const f = favs.has(d.id) ? 1 : 0;
            if (d.fav !== f) { d.fav = f; this.invalidate('data'); }
        });
        table.draw(false);
        updateCounts();
    }

    async function load() {
        try {
            const r = await api('links');
            if (Array.isArray(r.favorites)) {          // ล็อกอินอยู่: ใช้ค่าในบัญชี และเก็บสำรองในเครื่อง
                favs = new Set(r.favorites);
                LocalFav.set(r.favorites);
            }
            rows = r.data.map(x => ({ ...x, fav: favs.has(x.id) ? 1 : 0 }));
            applySort();
            table.clear().rows.add(rows).draw();
            rows = table.rows().data().toArray();   // อ้างอิงข้อมูลชุดเดียวกับตาราง
            updateCounts();
        } catch (e) {
            showError(e, 'โหลดข้อมูลไม่สำเร็จ');
        }
    }

    const findRow = id => rows.find(r => r.id === +id);

    // ================= Favorite =================
    async function toggleFav(id) {
        const row = findRow(id);
        if (!row) return;
        const on = !favs.has(row.id);
        on ? favs.add(row.id) : favs.delete(row.id);
        LocalFav.set([...favs]);
        popId = row.id;
        applyFavs();
        Toast.fire({
            icon: on ? 'success' : 'info',
            title: on ? 'เพิ่มในรายการโปรดแล้ว' : 'นำออกจากรายการโปรดแล้ว',
            text: row.name,
        });
        if (B.user) {
            try {
                const r = await api('fav_set', { link_id: row.id, state: on ? 1 : 0 });
                favs = new Set(r.favorites);
                LocalFav.set(r.favorites);
                applyFavs();
            } catch (e) {
                on ? favs.delete(row.id) : favs.add(row.id);
                LocalFav.set([...favs]);
                applyFavs();
                showError(e, 'บันทึกรายการโปรดไม่สำเร็จ');
            }
        }
    }

    // ================= ดูไฟล์ =================
    async function previewFile(row) {
        const m = fileMeta(row.file_original);
        const url = 'file.php?id=' + row.id;
        let body = '';
        let width = 640;

        if (m.kind === 'image' && m.ext !== 'svg') {
            body = `<div class="preview-box p-2"><img src="${url}&inline=1" alt=""></div>`; width = 960;
        } else if (m.kind === 'pdf') {
            body = `<div class="preview-box"><iframe src="${url}&inline=1"></iframe></div>`; width = 1000;
        } else if (m.kind === 'archive' || ['txt', 'csv'].includes(m.ext)) {
            Swal.fire({ title: 'กำลังอ่านไฟล์...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const info = await api('file_info', { id: row.id });
                if (info.entries) {
                    body = `<div class="text-sm text-slate-600 mb-2"><i class="fa-solid fa-folder-tree"></i> ไฟล์ภายใน ${info.total} รายการ${info.total > info.entries.length ? ' (แสดง ' + info.entries.length + ' รายการแรก)' : ''}</div>
                            <div class="zip-list">${info.entries.map(e => `<div><span class="truncate">${e.name.endsWith('/') ? '📁' : '📄'} ${esc(e.name)}</span><span class="text-slate-400 shrink-0">${e.name.endsWith('/') ? '' : fmtSize(e.size)}</span></div>`).join('')}</div>`;
                } else if (info.text !== null && info.text !== undefined) {
                    body = `<div class="text-sm text-slate-600 mb-2">ตัวอย่างเนื้อหา</div><div class="preview-text">${esc(info.text)}</div>`; width = 800;
                } else {
                    body = `<div class="text-center text-slate-500 py-6"><i class="fa-solid fa-box-archive text-4xl mb-2"></i><div>ไม่สามารถแสดงรายการไฟล์ภายในของไฟล์ .${esc(m.ext)} ได้ กรุณาดาวน์โหลดเพื่อเปิด</div></div>`;
                }
            } catch (e) { return showError(e, 'เปิดไฟล์ไม่สำเร็จ'); }
        } else {
            body = `<div class="text-center text-slate-500 py-6"><i class="fa-solid ${m.icon} text-5xl mb-3 ${m.cls.split(' ').pop()}"></i>
                    <div>ไฟล์ประเภทนี้ไม่สามารถแสดงตัวอย่างในเบราว์เซอร์ได้<br>กรุณาดาวน์โหลดเพื่อเปิดด้วยโปรแกรมในเครื่อง</div></div>`;
        }

        const head = `
            <div class="file-card !mt-0 mb-3">
                <div class="ic ${m.cls}"><i class="fa-solid ${m.icon}"></i></div>
                <div class="meta"><div class="nm">${esc(row.file_original)}</div>
                    <div class="sz">${m.label} (.${esc(m.ext)}) · ${fmtSize(row.file_size)} · ปรับปรุง ${fmtDate(row.updated_at)}</div></div>
                ${['image', 'pdf'].includes(m.kind) && m.ext !== 'svg' ? `<a href="${url}&inline=1" target="_blank" class="link-btn web"><i class="fa-solid fa-up-right-from-square"></i> แท็บใหม่</a>` : ''}
            </div>`;

        const r = await SwalX.fire({
            title: esc(row.name), html: head + body, width,
            showCancelButton: true, cancelButtonText: 'ปิด',
            confirmButtonText: '<i class="fa-solid fa-download"></i> ดาวน์โหลด',
        });
        if (r.isConfirmed) {
            location.href = url;
            Toast.fire({ icon: 'success', title: 'กำลังดาวน์โหลดไฟล์', text: row.file_original });
        }
    }

    // ================= เพิ่ม / แก้ไข =================
    function pickType() {
        let chosen = null;
        return SwalX.fire({
            title: 'เพิ่มรายการใหม่',
            html: `<p class="text-center text-slate-500 text-sm mb-4">เลือกประเภทของรายการที่ต้องการเพิ่ม</p>
                   <div class="type-pick">
                     ${Object.entries(TYPES).map(([k, t]) => `<button type="button" data-type="${k}"><i class="fa-solid ${t.icon}"></i><span class="font-semibold">${t.label}</span></button>`).join('')}
                   </div>`,
            showConfirmButton: false, showCancelButton: true,
            didOpen: (p) => p.querySelectorAll('[data-type]').forEach(b => b.onclick = () => { chosen = b.dataset.type; Swal.close(); }),
        }).then(() => chosen);
    }

    async function openForm(row = null) {
        let type = row ? row.type : await pickType();
        if (!type) return;
        const isEdit = !!row;

        let proto = 'https://', rest = '';
        if (row && row.url) {
            const m = row.url.match(/^(https?:\/\/)(.*)$/i);
            if (m) { proto = m[1].toLowerCase(); rest = m[2]; } else rest = row.url;
        }
        const hasOld = !!(row && row.has_file);
        let selectedFile = null, removeFile = false;

        const html = `
            <div class="f-group">
                <label class="f-label">ประเภท <span class="req">*</span></label>
                <div class="type-pick compact" id="f-types">
                    ${Object.entries(TYPES).map(([k, t]) => `<button type="button" data-type="${k}" class="${k === type ? 'sel' : ''}"><i class="fa-solid ${t.icon}"></i><span>${t.label}</span></button>`).join('')}
                </div>
            </div>
            <div class="f-group">
                <label class="f-label"><span id="f-name-label">${TYPES[type].nameLabel}</span> <span class="req">*</span></label>
                <input id="f-name" class="f-input" maxlength="255" value="${esc(row ? row.name : '')}" placeholder="เช่น ระบบสารบรรณอิเล็กทรอนิกส์">
            </div>
            <div class="f-group">
                <label class="f-label">ลิงก์ <span class="req" id="f-url-req">*</span><span id="f-url-opt" class="text-slate-400 font-normal"> (ไม่บังคับ หากแนบไฟล์)</span></label>
                <div class="url-group">
                    <select id="f-proto"><option value="https://" ${proto === 'https://' ? 'selected' : ''}>https://</option><option value="http://" ${proto === 'http://' ? 'selected' : ''}>http://</option></select>
                    <input id="f-url" value="${esc(rest)}" placeholder="www.example.go.th/path" autocomplete="off" spellcheck="false">
                </div>
                <div class="url-detect" id="f-detect"></div>
                <div class="f-help">วางลิงก์ทั้งหมดได้เลย ระบบจะตรวจว่าเป็น https:// หรือ http:// แล้วตัดส่วนนั้นออกให้อัตโนมัติ</div>
            </div>
            <div class="f-group" id="f-doc">
                <label class="f-label">ไฟล์แนบ <span class="text-slate-400 font-normal">(เอกสาร, รูปภาพ, PDF, ไฟล์บีบอัด · ไม่เกิน ${B.maxMb} MB)</span></label>
                <div id="f-old"></div>
                <div class="drop-zone" id="f-drop">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl mb-1"></i>
                    <div class="text-sm">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</div>
                    <div class="text-[11px] mt-1 text-slate-400">${B.exts.map(e => '.' + e).join(' ')}</div>
                </div>
                <input type="file" id="f-file" class="hidden" accept="${B.exts.map(e => '.' + e).join(',')}">
                <div id="f-new"></div>
                <div class="toggle-row mt-2">
                    <div><div class="t"><i class="fa-solid fa-paperclip text-violet-500"></i> แสดงไฟล์แนบให้ผู้ใช้เห็น</div><div class="s">ปิดไว้หากต้องการเก็บไฟล์ไว้แต่ยังไม่เผยแพร่</div></div>
                    <label class="switch"><input type="checkbox" id="f-file-vis" ${!row || row.file_visible ? 'checked' : ''}><span class="slider"></span></label>
                </div>
            </div>
            <div class="toggle-row">
                <div><div class="t"><i class="fa-solid fa-eye text-emerald-500"></i> แสดงรายการนี้ในหน้าแรก</div><div class="s">ค่าเริ่มต้น: เปิดแสดงผล</div></div>
                <label class="switch"><input type="checkbox" id="f-vis" ${!row || row.is_visible ? 'checked' : ''}><span class="slider"></span></label>
            </div>
            <div class="progress" id="f-prog"><div></div></div>`;

        const r = await SwalX.fire({
            title: isEdit ? '<i class="fa-solid fa-pen text-indigo-500"></i> แก้ไขรายการ' : '<i class="fa-solid fa-plus text-indigo-500"></i> เพิ่มรายการใหม่',
            html, width: 680, showCancelButton: true, focusConfirm: false,
            confirmButtonText: '<i class="fa-solid fa-floppy-disk"></i> บันทึก',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            didOpen: (p) => {
                const $ = s => p.querySelector(s);
                const urlIn = $('#f-url'), protoSel = $('#f-proto'), detect = $('#f-detect');

                function setType(t) {
                    type = t;
                    p.querySelectorAll('#f-types button').forEach(b => b.classList.toggle('sel', b.dataset.type === t));
                    $('#f-name-label').textContent = TYPES[t].nameLabel;
                    const doc = t === 'document';
                    $('#f-doc').style.display = doc ? '' : 'none';
                    $('#f-url-req').style.display = doc ? 'none' : '';
                    $('#f-url-opt').style.display = doc ? '' : 'none';
                    if (!doc && hasOld && !removeFile) detect.innerHTML = '';
                }
                p.querySelectorAll('#f-types button').forEach(b => b.onclick = () => setType(b.dataset.type));
                setType(type);

                // ---- ตรวจและตัดโปรโตคอล ----
                function showPreview(msg) {
                    const v = urlIn.value.trim();
                    detect.innerHTML = (msg ? `<div class="text-emerald-600"><i class="fa-solid fa-wand-magic-sparkles"></i> ${msg}</div>` : '')
                        + (v ? `<div class="text-slate-500">ลิงก์ที่จะบันทึก: <b class="text-indigo-600 break-all">${esc(protoSel.value + v)}</b></div>` : '');
                }
                function normalize() {
                    let v = urlIn.value.trim(), m, found = null;
                    while ((m = v.match(/^(https?):\/\//i))) { found = m[1].toLowerCase() + '://'; v = v.slice(m[0].length); }
                    if (found) {
                        protoSel.value = found;
                        urlIn.value = v;
                        showPreview(`ตรวจพบลิงก์แบบ <b>${found}</b> — เลือกโปรโตคอลและตัดออกให้แล้ว`);
                    } else showPreview('');
                }
                urlIn.addEventListener('input', normalize);
                urlIn.addEventListener('paste', () => setTimeout(normalize, 0));
                protoSel.addEventListener('change', () => showPreview(''));
                showPreview('');

                // ---- ไฟล์เดิม ----
                function renderOld() {
                    if (!hasOld) return;
                    const m = fileMeta(row.file_original);
                    $('#f-old').innerHTML = removeFile
                        ? `<div class="file-card !mt-0 mb-2 bg-rose-50 border-rose-200"><div class="meta text-sm text-rose-600"><i class="fa-solid fa-trash"></i> ไฟล์เดิมจะถูกลบเมื่อบันทึก: ${esc(row.file_original)}</div>
                             <button type="button" class="link-btn web" id="f-undo">ยกเลิก</button></div>`
                        : `<div class="file-card !mt-0 mb-2"><div class="ic ${m.cls}"><i class="fa-solid ${m.icon}"></i></div>
                             <div class="meta"><div class="nm">${esc(row.file_original)}</div><div class="sz">ไฟล์ปัจจุบัน · ${m.label} · ${fmtSize(row.file_size)}</div></div>
                             <button type="button" class="act-btn del" id="f-rm" title="ลบไฟล์เดิม"><i class="fa-solid fa-trash"></i></button></div>`;
                    const rm = $('#f-rm'), undo = $('#f-undo');
                    if (rm) rm.onclick = () => { removeFile = true; renderOld(); };
                    if (undo) undo.onclick = () => { removeFile = false; renderOld(); };
                }
                renderOld();

                // ---- เลือกไฟล์ใหม่ ----
                const fileIn = $('#f-file'), drop = $('#f-drop');
                function setFile(f) {
                    Swal.resetValidationMessage();
                    if (!f) { selectedFile = null; $('#f-new').innerHTML = ''; return; }
                    const m = fileMeta(f.name);
                    if (!B.exts.includes(m.ext)) { fileIn.value = ''; return Swal.showValidationMessage(`ไม่รองรับไฟล์ประเภท .${m.ext}`); }
                    if (f.size > B.maxMb * 1024 * 1024) { fileIn.value = ''; return Swal.showValidationMessage(`ไฟล์ต้องมีขนาดไม่เกิน ${B.maxMb} MB`); }
                    selectedFile = f;
                    const thumb = m.kind === 'image' ? `<img src="${URL.createObjectURL(f)}" alt="">` : `<i class="fa-solid ${m.icon}"></i>`;
                    $('#f-new').innerHTML = `<div class="file-card"><div class="ic ${m.cls}">${thumb}</div>
                        <div class="meta"><div class="nm">${esc(f.name)}</div><div class="sz">${hasOld ? 'ไฟล์ใหม่ (แทนที่ไฟล์เดิม)' : 'ไฟล์ที่เลือก'} · ${m.label} · ${fmtSize(f.size)}</div></div>
                        <button type="button" class="act-btn del" id="f-clear" title="นำออก"><i class="fa-solid fa-xmark"></i></button></div>`;
                    $('#f-clear').onclick = () => { fileIn.value = ''; setFile(null); };
                    if (!$('#f-name').value.trim()) $('#f-name').value = f.name.replace(/\.[^.]+$/, '');
                }
                drop.onclick = () => fileIn.click();
                fileIn.onchange = () => setFile(fileIn.files[0]);
                ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('drag'); }));
                ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('drag'); }));
                drop.addEventListener('drop', e => e.dataTransfer.files[0] && setFile(e.dataTransfer.files[0]));

                $('#f-name').focus();
            },
            preConfirm: async () => {
                const p = Swal.getPopup(), $ = s => p.querySelector(s);
                const name = $('#f-name').value.trim();
                const rest = $('#f-url').value.trim();
                if (!name) return Swal.showValidationMessage('กรุณากรอก' + TYPES[type].nameLabel);
                if (type !== 'document' && !rest) return Swal.showValidationMessage('กรุณาใส่ลิงก์');
                if (rest && /\s/.test(rest)) return Swal.showValidationMessage('ลิงก์ต้องไม่มีช่องว่าง');
                if (type === 'document' && !rest && !selectedFile && !(hasOld && !removeFile)) {
                    return Swal.showValidationMessage('กรุณาใส่ลิงก์ หรือแนบไฟล์อย่างน้อย 1 อย่าง');
                }
                const fd = new FormData();
                if (isEdit) fd.append('id', row.id);
                fd.append('type', type);
                fd.append('name', name);
                fd.append('protocol', $('#f-proto').value);
                fd.append('url', rest);
                fd.append('is_visible', $('#f-vis').checked ? '1' : '0');
                fd.append('file_visible', $('#f-file-vis').checked ? '1' : '0');
                if (type === 'document') {
                    if (removeFile) fd.append('remove_file', '1');
                    if (selectedFile) fd.append('file', selectedFile);
                }
                const prog = $('#f-prog');
                if (selectedFile && type === 'document') prog.style.display = 'block';
                try {
                    return await apiUpload('link_save', fd, pct => prog.firstElementChild.style.width = pct + '%');
                } catch (e) {
                    prog.style.display = 'none';
                    Swal.showValidationMessage(e.message);
                }
            },
        });
        if (r.isConfirmed && r.value) {
            Toast.fire({ icon: 'success', title: r.value.message, text: '' });
            await load();
        }
    }

    // ================= ลบ =================
    async function deleteRow(row) {
        const r = await SwalX.fire({
            icon: 'warning', title: 'ยืนยันการลบ?',
            html: `ต้องการลบ <b>${esc(row.name)}</b> ใช่หรือไม่?${row.has_file ? '<br><small class="text-rose-600">ไฟล์แนบจะถูกลบออกจากเซิร์ฟเวอร์ด้วย</small>' : ''}<br><small class="text-slate-500">การลบไม่สามารถย้อนกลับได้</small>`,
            showCancelButton: true, confirmButtonText: '<i class="fa-solid fa-trash"></i> ลบ', confirmButtonColor: '#e11d48',
            showLoaderOnConfirm: true,
            preConfirm: async () => { try { return await api('link_delete', { id: row.id }); } catch (e) { Swal.showValidationMessage(e.message); } },
        });
        if (r.isConfirmed && r.value) {
            favs.delete(row.id);
            LocalFav.set([...favs]);
            Toast.fire({ icon: 'success', title: r.value.message });
            await load();
        }
    }

    // ================= เปิด/ปิดการแสดงผล =================
    async function toggleVisible(row, input) {
        const want = input.checked;
        input.disabled = true;
        try {
            const r = await api('link_toggle', { id: row.id, field: 'is_visible' });
            table.rows().every(function () {
                const d = this.data();
                if (d.id === row.id) { d.is_visible = r.value; this.invalidate('data'); }
            });
            row.is_visible = r.value;
            table.draw(false);
            Toast.fire({ icon: r.value ? 'success' : 'info', title: r.value ? 'เปิดการแสดงผลแล้ว' : 'ปิดการแสดงผลแล้ว', text: row.name });
        } catch (e) {
            input.checked = !want;
            showError(e);
        } finally { input.disabled = false; }
    }

    // ================= Events =================
    const tbody = document.querySelector('#linksTable tbody');
    tbody.addEventListener('click', e => {
        const el = e.target.closest('[data-act]');
        if (!el) return;
        const row = findRow(el.dataset.id);
        if (!row) return;
        switch (el.dataset.act) {
            case 'star': toggleFav(row.id); break;
            case 'file': previewFile(row); break;
            case 'edit': openForm(row); break;
            case 'del': deleteRow(row); break;
            case 'open': Toast.fire({ icon: 'info', title: 'กำลังเปิด', text: row.name, timer: 1500 }); break;
        }
    });
    tbody.addEventListener('change', e => {
        const el = e.target.closest('[data-act="toggle"]');
        if (el) toggleVisible(findRow(el.dataset.id), el);
    });

    document.querySelectorAll('.filter-btn').forEach(btn => btn.addEventListener('click', () => {
        const f = btn.dataset.filter;
        filter = (f === filter && f !== 'all') ? 'all' : f;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.toggle('active', b.dataset.filter === filter));
        table.draw();
        if (filter === 'fav' && !rows.some(r => r.fav)) {
            SwalX.fire({ icon: 'info', title: 'ยังไม่มีรายการโปรด', html: 'กดไอคอน <i class="fa-regular fa-star text-amber-500"></i> หน้ารายการเพื่อเพิ่มเป็นรายการโปรด' });
        }
    }));

    const addBtn = document.getElementById('btnAdd');
    if (addBtn) addBtn.addEventListener('click', () => openForm());

    load();
})();
