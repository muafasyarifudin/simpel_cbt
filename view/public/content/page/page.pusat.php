<?php
/**
 * simpel_cbt - Dashboard Administrator (Modern Minimalist Hybrid)
 */
require_once __DIR__ . '/../../../../model/config/config.conn.php';
require_once __DIR__ . '/../../../../model/helper/cbt.helper.php';
require_once __DIR__ . '/../../../../model/helper/auth.helper.php';
require_once __DIR__ . '/../../../../model/helper/auth_bridge.helper.php';

// Pastikan admin login
check_admin_login();
$adminUser = get_logged_admin();

// Ambil statistik sistem
$stats = cbt_get_stats($conn);

// Ambil list kategori untuk dropdown
$listKategori = [];
$rK = mysqli_query($conn, "SELECT * FROM cbt_kategori ORDER BY nama_kategori ASC");
if ($rK) {
    while ($dK = mysqli_fetch_assoc($rK)) {
        $listKategori[] = $dK;
    }
}

// Ambil list jadwal untuk dropdown
$listJadwal = [];
$rJ = mysqli_query($conn, "SELECT * FROM cbt_jadwal ORDER BY id_jadwal DESC");
if ($rJ) {
    while ($dJ = mysqli_fetch_assoc($rJ)) {
        $listJadwal[] = $dJ;
    }
}

// Data Auth Bridge
$authBridgeCfg = get_auth_bridge_config();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <script>
    (() => {
        const nativeFetch = window.fetch.bind(window);
        const token = <?= json_encode(csrf_token()) ?>;
        window.fetch = (input, options = {}) => {
            const method = String(options.method || 'GET').toUpperCase();
            if (method === 'POST') {
                const headers = new Headers(options.headers || {});
                headers.set('X-CSRF-Token', token);
                options = { ...options, headers };
            }
            return nativeFetch(input, options);
        };
    })();
        let pesertaCache = [];
        const opEsc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
        function showToast(message, type = 'success') {
            const colors = {success:'#16a34a', danger:'#dc2626', error:'#dc2626', warning:'#d97706'};
            if (window.Toastify) Toastify({text:String(message),duration:3500,close:true,gravity:'top',position:'right',stopOnFocus:true,style:{background:colors[type]||'#4f46e5'}}).showToast();
            else alert(message);
        }
        async function opJson(action, options = {}) {
            const res = await fetch('model/ajax/cbt/operations_api.php?action=' + action, options);
            const data = await res.json();
            if (!res.ok && data.status !== 'success') throw new Error(data.msg || 'Permintaan gagal');
            return data;
        }
        async function loadPeserta() {
            const el=document.getElementById('tbodyPeserta'); if(!el)return;
            try { const d=await opJson('list_peserta&search='+encodeURIComponent(document.getElementById('searchPeserta')?.value||'')); pesertaCache=d.data; const assignment=document.getElementById('assignmentPeserta');if(assignment)assignment.innerHTML='<option value="">Pilih peserta</option>'+d.data.map(p=>`<option value="${p.id_peserta}">${opEsc(p.no_peserta)} · ${opEsc(p.nama_lengkap)}</option>`).join('');
                el.innerHTML=d.data.length?d.data.map((p,i)=>`<tr><td><strong>${opEsc(p.no_peserta)}</strong></td><td>${opEsc(p.nama_lengkap)}</td><td>${opEsc(p.kelompok||'-')}<br><small>${opEsc(p.nama_ruang||'Tanpa ruang')}</small></td><td>${p.status==1?'<span class="badge bg-success">Aktif</span>':'<span class="badge bg-secondary">Nonaktif</span>'}</td><td><button class="btn btn-sm btn-light" onclick="editPeserta(${i})">Edit</button> <button class="btn btn-sm btn-outline-danger" onclick="deletePeserta(${p.id_peserta})">Hapus</button></td></tr>`).join(''):'<tr><td colspan="5" class="text-center text-muted">Belum ada peserta resmi.</td></tr>';
            } catch(e){el.innerHTML=`<tr><td colspan="5" class="text-danger">${opEsc(e.message)}</td></tr>`;}
        }
        function editPeserta(i){const p=pesertaCache[i];document.getElementById('pesertaId').value=p.id_peserta;document.getElementById('pesertaNo').value=p.no_peserta;document.getElementById('pesertaNama').value=p.nama_lengkap;document.getElementById('pesertaEmail').value=p.email||'';document.getElementById('pesertaKelompok').value=p.kelompok||'';document.getElementById('pesertaRuang').value=p.id_ruang||'';}
        function resetPesertaForm(){document.getElementById('formPeserta')?.reset();document.getElementById('pesertaId').value='';}
        async function deletePeserta(id){if(!confirm('Hapus peserta ini?'))return;const fd=new FormData();fd.append('id_peserta',id);try{const d=await opJson('delete_peserta',{method:'POST',body:fd});showToast(d.msg,'success');loadPeserta();}catch(e){showToast(e.message,'danger');}}
        async function loadRuang(){try{const d=await opJson('list_ruang');const select=document.getElementById('pesertaRuang');if(select)select.innerHTML='<option value="">Tanpa ruang</option>'+d.data.map(r=>`<option value="${r.id_ruang}">${opEsc(r.nama_ruang)} (${opEsc(r.kode_ruang)})</option>`).join('');const box=document.getElementById('listRuang');if(box)box.innerHTML=d.data.map(r=>`<span class="badge bg-light text-dark border me-2 mb-2">${opEsc(r.kode_ruang)} · ${opEsc(r.nama_ruang)} · ${r.kapasitas} kursi</span>`).join('')||'<span class="text-muted">Belum ada ruang.</span>';}catch(e){showToast(e.message,'danger');}}
        let auditPage=1, auditPerPage=10, auditSearch='', auditSearchTimer=null;
        function auditPageButtons(meta){let start=Math.max(1,meta.page-2),end=Math.min(meta.pages,start+4);start=Math.max(1,end-4);let html=`<button class="btn btn-sm btn-light border" ${meta.page<=1?'disabled':''} onclick="changeAuditPage(${meta.page-1})"><i class="bx bx-chevron-left"></i></button>`;for(let i=start;i<=end;i++)html+=`<button class="btn btn-sm ${i===meta.page?'btn-primary':'btn-light border'}" onclick="changeAuditPage(${i})">${i}</button>`;html+=`<button class="btn btn-sm btn-light border" ${meta.page>=meta.pages?'disabled':''} onclick="changeAuditPage(${meta.page+1})"><i class="bx bx-chevron-right"></i></button>`;return html;}
        function changeAuditPage(page){auditPage=Math.max(1,page);loadOperational();}
        function changeAuditSize(value){auditPerPage=parseInt(value,10)||10;auditPage=1;loadOperational();}
        function searchAudit(value){clearTimeout(auditSearchTimer);auditSearchTimer=setTimeout(()=>{auditSearch=value.trim();auditPage=1;loadOperational();},350);}
        async function loadOperational(){try{const auditQuery=`list_audit&page=${auditPage}&per_page=${auditPerPage}&search=${encodeURIComponent(auditSearch)}`;const [s,a,p]=await Promise.all([opJson('summary'),opJson(auditQuery),opJson('list_pengumuman')]);['opPeserta','opRuang','opAudit'].forEach((id,i)=>{const el=document.getElementById(id);if(el)el.textContent=[s.data.peserta,s.data.ruang,s.data.audit][i]});const upload=document.getElementById('opUpload');if(upload)upload.textContent=s.data.upload_writable?'Siap':'Bermasalah';const tbody=document.getElementById('tbodyAudit');if(tbody)tbody.innerHTML=a.data.length?a.data.map(x=>`<tr><td>${opEsc(x.created_at)}</td><td>${opEsc(x.username)}<br><small>${opEsc(x.role)}</small></td><td><span class="badge bg-light text-dark border">${opEsc(x.aksi)}</span></td><td>${opEsc(x.entitas||'-')} #${opEsc(x.id_entitas||'-')}</td><td>${opEsc(x.ip_address||'-')}</td></tr>`).join(''):'<tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada audit yang sesuai.</td></tr>';const info=document.getElementById('auditInfo');if(info)info.textContent=`Menampilkan ${a.meta.from}-${a.meta.to} dari ${a.meta.total} aktivitas`;const pages=document.getElementById('auditPagination');if(pages)pages.innerHTML=auditPageButtons(a.meta);const list=document.getElementById('listPengumuman');if(list)list.innerHTML=p.data.map(x=>`<div class="border rounded p-2 mb-2 d-flex justify-content-between gap-2"><div><strong>${opEsc(x.judul)}</strong><br><small>${opEsc(x.isi)}</small></div><button class="btn btn-sm btn-outline-danger align-self-start" onclick="deleteAnnouncement(${x.id_pengumuman})" title="Hapus"><i class="bx bx-trash"></i></button></div>`).join('')||'<small class="text-muted">Belum ada pengumuman.</small>';}catch(e){showToast(e.message,'danger');}}
        async function deleteAnnouncement(id){if(!confirm('Hapus pengumuman ini?'))return;const fd=new FormData();fd.append('id_pengumuman',id);try{const d=await opJson('delete_pengumuman',{method:'POST',body:fd});showToast(d.msg,'success');loadOperational();}catch(e){showToast(e.message,'danger');}}
        document.getElementById('formPeserta')?.addEventListener('submit',async e=>{e.preventDefault();try{const d=await opJson('save_peserta',{method:'POST',body:new FormData(e.target)});showToast(d.msg,'success');resetPesertaForm();loadPeserta();}catch(x){showToast(x.message,'danger');}});
        document.getElementById('formRuang')?.addEventListener('submit',async e=>{e.preventDefault();try{const d=await opJson('save_ruang',{method:'POST',body:new FormData(e.target)});showToast(d.msg,'success');e.target.reset();loadRuang();}catch(x){showToast(x.message,'danger');}});
        document.getElementById('formPengumuman')?.addEventListener('submit',async e=>{e.preventDefault();try{const d=await opJson('save_pengumuman',{method:'POST',body:new FormData(e.target)});showToast(d.msg,'success');e.target.reset();loadOperational();}catch(x){showToast(x.message,'danger');}});
        document.getElementById('formRestore')?.addEventListener('submit',async e=>{e.preventDefault();if(!confirm('Pemulihan akan mengganti data database saat ini. Lanjutkan?'))return;try{const d=await opJson('restore_backup',{method:'POST',body:new FormData(e.target)});alert(d.msg);location.reload();}catch(x){showToast(x.message,'danger');}});
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('formPeserta')?.addEventListener('submit',async e=>{e.preventDefault();try{const d=await opJson('save_peserta',{method:'POST',body:new FormData(e.target)});showToast(d.msg,'success');resetPesertaForm();loadPeserta();}catch(x){showToast(x.message,'danger');}});
            document.getElementById('formRuang')?.addEventListener('submit',async e=>{e.preventDefault();try{const d=await opJson('save_ruang',{method:'POST',body:new FormData(e.target)});showToast(d.msg,'success');e.target.reset();loadRuang();}catch(x){showToast(x.message,'danger');}});
            document.getElementById('formPengumuman')?.addEventListener('submit',async e=>{e.preventDefault();const button=e.submitter||e.target.querySelector('button[type="submit"],button');if(button?.disabled)return;const old=button?.innerHTML;if(button){button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';}try{const d=await opJson('save_pengumuman',{method:'POST',body:new FormData(e.target)});showToast(d.msg,d.duplicate?'warning':'success');if(!d.duplicate)e.target.reset();await loadOperational();}catch(x){showToast(x.message,'danger');}finally{if(button){button.disabled=false;button.innerHTML=old;}}});
            document.getElementById('formRestore')?.addEventListener('submit',async e=>{e.preventDefault();if(!confirm('Pemulihan akan mengganti data database saat ini. Lanjutkan?'))return;try{const d=await opJson('restore_backup',{method:'POST',body:new FormData(e.target)});alert(d.msg);location.reload();}catch(x){showToast(x.message,'danger');}});
            document.getElementById('formAssignment')?.addEventListener('submit',async e=>{e.preventDefault();try{const d=await opJson('assign_peserta',{method:'POST',body:new FormData(e.target)});showToast(d.msg,'success');}catch(x){showToast(x.message,'danger');}});
            document.getElementById('formImportPeserta')?.addEventListener('submit',async e=>{e.preventDefault();try{const d=await opJson('import_peserta',{method:'POST',body:new FormData(e.target)});showToast(d.msg,'success');e.target.reset();loadPeserta();}catch(x){showToast(x.message,'danger');}});
        });
    </script>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pusat Kontrol - SIMPEL CBT</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet" />

    <!-- Icons & Bootstrap 5 -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Toastify & SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-subtle: #eef2ff;
            --bg-canvas: #f8fafc;
            --sidebar-bg: #ffffff;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --border-subtle: #e2e8f0;
            --card-radius: 14px;
            --header-height: 68px;
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 72px;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            min-height: 100vh;
            margin: 0;
            display: flex;
        }

        /* Layout Grid */
        .app-layout {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Sidebar Modern Minimalist */
        .sidebar-modern {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 1000;
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-brand {
            height: var(--header-height);
            box-sizing: border-box;
            padding: 0 16px 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-subtle);
            background-color: var(--sidebar-bg);
        }

        .brand-logo-group {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
        }

        .btn-sidebar-toggle {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            background: #ffffff;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0;
            flex-shrink: 0;
        }

        .btn-sidebar-toggle:hover {
            background-color: var(--primary-subtle);
            color: var(--primary);
            border-color: #c7d2fe;
        }

        .toggle-icon {
            font-size: 1.25rem;
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            line-height: 1;
        }

        /* Collapsed Sidebar Rules */
        .sidebar-collapsed .sidebar-modern {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-collapsed .sidebar-brand {
            padding: 0 10px;
            justify-content: center;
        }

        .sidebar-collapsed .brand-logo-group {
            display: none !important;
        }

        .sidebar-collapsed .btn-sidebar-toggle {
            width: 38px;
            height: 38px;
        }

        .sidebar-collapsed .btn-sidebar-toggle .toggle-icon {
            transform: rotate(180deg);
        }

        .sidebar-collapsed .nav-category {
            display: none !important;
        }

        .sidebar-collapsed .sidebar-nav {
            padding: 16px 8px;
        }

        .sidebar-collapsed .nav-link-modern {
            justify-content: center;
            padding: 11px 0;
            margin: 4px auto;
            width: 44px;
            border-radius: 10px;
        }

        .sidebar-collapsed .nav-link-modern span {
            display: none !important;
        }

        .sidebar-collapsed .nav-link-modern i {
            font-size: 1.35rem;
            margin: 0;
        }

        .sidebar-collapsed .sidebar-footer {
            padding: 14px 8px;
            justify-content: center;
        }

        .sidebar-collapsed .sidebar-footer .footer-user-meta {
            display: none !important;
        }

        .sidebar-collapsed .sidebar-footer .footer-logout-btn {
            display: none !important;
        }

        .brand-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .brand-title {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.4px;
            color: var(--text-heading);
            margin: 0;
        }

        .brand-title span {
            color: var(--primary);
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex-grow: 1;
            overflow-y: auto;
        }

        .nav-category {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            padding: 12px 10px 6px;
        }

        .nav-link-modern {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            color: var(--text-muted);
            font-size: 0.88rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease;
            margin-bottom: 2px;
            cursor: pointer;
        }

        .nav-link-modern i {
            font-size: 1.2rem;
            color: var(--text-muted);
            transition: color 0.15s ease;
        }

        .nav-link-modern:hover {
            color: var(--text-heading);
            background-color: var(--bg-canvas);
        }

        .nav-link-modern:hover i {
            color: var(--primary);
        }

        .nav-link-modern.active {
            background-color: var(--primary-subtle);
            color: var(--primary);
            font-weight: 600;
        }

        .nav-link-modern.active i {
            color: var(--primary);
        }

        .sidebar-footer {
            padding: 14px 16px;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Main Content Container */
        .main-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* Topbar Header */
        .topbar-modern {
            height: var(--header-height);
            box-sizing: border-box;
            background: #ffffff;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 990;
        }

        .topbar-vr {
            width: 1px;
            height: 28px;
            background-color: var(--border-subtle);
        }

        /* Profile Avatar Dropdown */
        .profile-dropdown-wrapper {
            position: relative;
        }

        .avatar-profile-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 4px 8px 4px 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .avatar-profile-btn:hover,
        .avatar-profile-btn[aria-expanded="true"] {
            background-color: #f1f5f9;
            border-color: var(--border-subtle);
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            position: relative;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
            flex-shrink: 0;
        }

        .avatar-circle.sm {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            font-size: 0.8rem;
        }

        .avatar-circle.lg {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            font-size: 1.05rem;
        }

        .avatar-online-dot {
            position: absolute;
            bottom: -1px;
            right: -1px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #10b981;
            border: 2px solid #ffffff;
        }

        .avatar-info {
            line-height: 1.2;
        }

        .avatar-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-heading);
        }

        .avatar-role {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .avatar-arrow {
            font-size: 1.1rem;
            color: var(--text-muted);
            transition: transform 0.2s ease;
        }

        .avatar-profile-btn[aria-expanded="true"] .avatar-arrow {
            transform: rotate(180deg);
        }

        /* Dropdown Menu Card */
        .profile-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 240px;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            padding: 6px;
            z-index: 1050;
            display: none;
            animation: dropdownFadeIn 0.18s ease forwards;
        }

        .profile-dropdown-menu.show {
            display: block;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-header-box {
            padding: 10px 12px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dropdown-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-heading);
            line-height: 1.2;
        }

        .dropdown-badge {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .dropdown-item-divider {
            height: 1px;
            background-color: var(--border-subtle);
            margin: 4px 6px;
        }

        .dropdown-action-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            color: var(--text-body);
            font-size: 0.84rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .dropdown-action-item i {
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .dropdown-action-item:hover {
            background-color: var(--bg-canvas);
            color: var(--text-heading);
        }

        .dropdown-action-item.logout-action:hover {
            background-color: #fef2f2;
        }

        .page-heading-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
        }

        .content-body {
            padding: 28px;
            flex-grow: 1;
        }

        .tab-pane-content {
            display: none;
        }
        .tab-pane-content.active {
            display: block;
        }

        /* Modern KPI Metric Cards */
        .metric-card {
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--card-radius);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
        }

        .metric-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .metric-number {
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.5px;
            margin: 0;
        }

        .metric-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        /* Custom Modern Card */
        .card-modern {
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--card-radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header-modern {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
        }

        .card-header-modern h5 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
        }

        /* Table Styling */
        .table-modern {
            width: 100% !important;
            min-width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }

        .table-responsive {
            width: 100% !important;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Mobile Hamburger & Close Buttons */
        .btn-mobile-toggle {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            background: #ffffff;
            color: var(--text-heading);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
        }
        .btn-mobile-toggle:hover {
            background-color: var(--bg-canvas);
        }

        .btn-close-mobile {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            background: #f8fafc;
            color: var(--text-heading);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
        }

        /* Responsive Mobile Overhaul */
        @media (max-width: 991.98px) {
            .sidebar-modern {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                bottom: 0 !important;
                width: 280px !important;
                height: 100vh !important;
                z-index: 1100 !important;
                transform: translateX(-100%) !important;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
                background-color: #ffffff !important;
            }

            .sidebar-modern.show {
                transform: translateX(0) !important;
            }

            .main-container {
                width: 100% !important;
                min-width: 100% !important;
                margin-left: 0 !important;
            }

            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(15, 23, 42, 0.5);
                backdrop-filter: blur(3px);
                z-index: 1095;
                display: none;
                opacity: 0;
                transition: opacity 0.25s ease;
            }

            .sidebar-backdrop.show {
                display: block;
                opacity: 1;
            }

            .topbar-modern {
                padding: 0 14px !important;
                gap: 8px;
            }

            .page-heading-title {
                font-size: 0.92rem !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 160px;
            }

            .content-body {
                padding: 16px 12px !important;
            }

            .card-header-modern {
                padding: 14px 16px !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }

            .card-header-modern > div:last-child {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
        }

        .table-modern thead th {
            background-color: var(--bg-canvas);
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-subtle);
            padding: 12px 18px;
        }

        .table-modern tbody td {
            padding: 14px 18px;
            vertical-align: middle;
            color: var(--text-body);
            font-size: 0.88rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-modern tbody tr:hover td {
            background-color: #fafbfc;
        }

        .badge-soft-primary {
            background-color: #eef2ff;
            color: #4f46e5;
            border: 1px solid #e0e7ff;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .badge-soft-success {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .badge-soft-warning {
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .badge-soft-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .btn-action-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-subtle);
            background: #ffffff;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-action-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-subtle);
            background: #ffffff;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
            padding: 0;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-action-icon:hover {
            background-color: var(--primary-subtle);
            color: var(--primary);
            border-color: #c7d2fe;
        }

        .btn-action-icon.text-warning {
            color: #b45309 !important;
        }
        .btn-action-icon.text-warning:hover {
            background-color: #fffbeb !important;
            color: #92400e !important;
            border-color: #fde68a !important;
        }

        .btn-action-icon.text-danger {
            color: #dc2626 !important;
        }
        .btn-action-icon.text-danger:hover {
            background-color: #fef2f2 !important;
            color: #b91c1c !important;
            border-color: #fecaca !important;
        }

        .btn-action-icon-old:hover {
            background-color: var(--bg-canvas);
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-action-icon.text-danger:hover {
            background-color: #fef2f2;
            color: #ef4444;
            border-color: #fca5a5;
        }

        .btn-primary-modern {
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }

        .btn-primary-modern:hover {
            background-color: var(--primary-hover);
            color: #ffffff;
        }

        .token-chip {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--primary);
            background-color: var(--primary-subtle);
            border: 1px solid #e0e7ff;
            border-radius: 6px;
            padding: 3px 8px;
        }
            /* Toastify default library style with clean close button */
        .toastify {
            border-radius: 8px !important;
            font-family: inherit !important;
            font-size: 0.84rem !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12) !important;
            padding: 9px 16px !important;
            z-index: 99999 !important;
        }
        .toast-close {
            margin-left: 10px !important;
            opacity: 0.85 !important;
            cursor: pointer !important;
            font-weight: bold !important;
            padding: 0 4px !important;
        }
        .toast-close:hover {
            opacity: 1 !important;
        }
        .participant-detail-popup{padding:0!important;border-radius:18px!important;overflow:hidden;font-family:'Plus Jakarta Sans',sans-serif!important;color:#334155!important}.participant-detail-container{margin:0!important;text-align:left!important}.participant-detail-popup .swal2-close{color:#64748b!important;top:14px!important;right:14px!important}.detail-profile{display:flex;align-items:center;gap:16px;padding:24px 28px;background:linear-gradient(135deg,#f8fafc,#eef2ff);border-bottom:1px solid #e2e8f0}.detail-avatar{width:58px;height:58px;border-radius:16px;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;box-shadow:0 8px 20px rgba(79,70,229,.22);flex:none}.detail-identity{min-width:0;flex:1}.detail-identity h3{font-size:20px;margin:0 0 5px;font-weight:700;color:#0f172a}.detail-id,.detail-exam{display:inline-flex;align-items:center;gap:6px;font-size:13px;margin-right:14px;color:#475569}.detail-id{font-weight:600;color:#4f46e5}.detail-session{display:flex;flex-direction:column;align-items:flex-end;gap:5px}.detail-session small{display:flex;align-items:center;gap:5px;color:#64748b}.detail-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:18px 28px}.detail-metrics>div{padding:13px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.detail-metrics span{display:block;font-size:20px;font-weight:700;color:#0f172a}.detail-metrics small{color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em}.detail-metrics .is-danger{background:#fef2f2;border-color:#fecaca}.detail-metrics .is-danger span{color:#dc2626}.detail-tabs{display:flex;gap:6px;padding:0 28px;border-bottom:1px solid #e2e8f0;list-style:none}.detail-tabs button{border:0;background:transparent;padding:11px 14px;color:#64748b;font-size:13px;font-weight:600;border-bottom:2px solid transparent;display:flex;align-items:center;gap:7px}.detail-tabs button.active{color:#4f46e5;border-bottom-color:#4f46e5}.detail-tabs b{background:#eef2ff;border-radius:10px;padding:1px 7px;font-size:11px}.detail-tab-content{padding:18px 28px 5px}.detail-table-wrap{max-height:390px;border:1px solid #e2e8f0;border-radius:12px}.detail-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13px;table-layout:fixed}.detail-table th{position:sticky;top:0;z-index:1;background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em;padding:12px;text-align:left;border-bottom:1px solid #e2e8f0}.detail-table td{padding:12px;border-bottom:1px solid #eef2f7;vertical-align:middle;line-height:1.45;overflow-wrap:anywhere}.detail-table tr:last-child td{border-bottom:0}.detail-table tbody tr:hover{background:#fafbff}.detail-number{text-align:center;font-weight:700;color:#64748b}.detail-question{font-weight:400;color:#334155}.answer-chip{display:inline-flex;width:30px;height:30px;align-items:center;justify-content:center;border-radius:8px;background:#f1f5f9;color:#94a3b8;font-weight:600}.answer-chip.filled{background:#eef2ff;color:#4f46e5}.detail-status{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:20px;font-size:11px;font-weight:600;text-transform:capitalize}.detail-status.success{background:#ecfdf5;color:#059669}.detail-status.danger{background:#fef2f2;color:#dc2626}.detail-status.warning{background:#fffbeb;color:#d97706}.detail-status.muted{background:#f1f5f9;color:#64748b}.detail-empty{text-align:center!important;padding:40px!important;color:#64748b}.detail-empty i{font-size:25px;display:block;color:#10b981;margin-bottom:5px}.btn-detail-close{background:#4f46e5!important;border-radius:9px!important;font:600 13px 'Plus Jakarta Sans',sans-serif!important;padding:10px 22px!important}.participant-detail-popup .swal2-actions{margin:14px 0 20px!important}.detail-media{margin:16px 28px 4px;padding-top:14px;border-top:1px solid #e2e8f0}.detail-live{display:grid;grid-template-columns:170px 1fr;gap:12px;align-items:start;margin-bottom:14px}.detail-live>div{display:flex;align-items:center;gap:7px;font-size:12px}.detail-live-image{position:relative;width:240px;padding:0;border:0;border-radius:10px;overflow:hidden;background:#0f172a;cursor:zoom-in}.detail-live-image img{display:block;width:100%;max-height:150px;object-fit:cover}.detail-live-image span{position:absolute;right:8px;bottom:8px;padding:4px 8px;border-radius:7px;background:rgba(15,23,42,.78);color:#fff;font-size:11px}.live-dot{width:8px;height:8px;border-radius:50%;background:#ef4444;box-shadow:0 0 0 4px #fee2e2}.detail-media-title{display:flex;align-items:center;gap:7px;font-weight:700;color:#0f172a;margin-bottom:10px}.detail-media-title span{background:#eef2ff;color:#4f46e5;border-radius:12px;padding:2px 8px;font-size:11px}.detail-media-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;max-height:240px;overflow:auto}.detail-media-item{appearance:none;padding:0;text-align:left;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;color:#334155;background:#fff;cursor:zoom-in;transition:border-color .15s,transform .15s}.detail-media-item:hover{border-color:#818cf8;transform:translateY(-1px)}.detail-media-item img{display:block;width:100%;height:105px;object-fit:cover;background:#0f172a}.detail-media-item div{padding:7px}.detail-media-item b,.detail-media-item small{display:block;font-size:11px}.detail-media-item small{color:#64748b}.detail-media-empty{text-align:center;color:#64748b;padding:20px}.proctor-lightbox{position:fixed;inset:0;z-index:200000;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(15,23,42,.86);backdrop-filter:blur(5px)}.proctor-lightbox-card{width:min(1100px,96vw);max-height:94vh;overflow:hidden;border-radius:16px;background:#fff;box-shadow:0 25px 70px rgba(0,0,0,.4)}.proctor-lightbox-head{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;color:#0f172a;font-size:13px;font-weight:600}.proctor-lightbox-head button{display:grid;place-items:center;width:34px;height:34px;border:0;border-radius:9px;background:#f1f5f9;color:#475569;font-size:22px}.proctor-lightbox-body{display:flex;align-items:center;justify-content:center;padding:0 16px 16px;background:#fff}.proctor-lightbox-body img{display:block;max-width:100%;max-height:calc(94vh - 76px);object-fit:contain;border-radius:10px;background:#0f172a}@media(max-width:700px){.detail-profile{align-items:flex-start;padding:20px;flex-wrap:wrap}.detail-session{width:100%;align-items:flex-start;margin-left:74px}.detail-metrics{grid-template-columns:repeat(2,1fr);padding:14px 20px}.detail-tabs{padding:0 16px;overflow:auto}.detail-tab-content{padding:14px}.participant-detail-popup{width:96vw!important}.detail-table{min-width:720px}.detail-media{margin:12px}.detail-media-grid{grid-template-columns:repeat(2,1fr)}.detail-live{grid-template-columns:1fr}.detail-live-image{width:100%}.proctor-lightbox{padding:10px}}
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
</head>

<body>
    <div class="app-layout">
        <!-- Mobile Backdrop Overlay -->
        <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeMobileSidebar()"></div>
        
        <!-- Sidebar Minimalist -->
        <aside class="sidebar-modern">
            
            <div class="sidebar-brand">
                <div class="brand-logo-group">
                    <div class="brand-icon">
                        <i class="bx bx-select-multiple"></i>
                    </div>
                    <h2 class="brand-title">SIMPEL <span>CBT</span></h2>
                </div>
                <button type="button" class="btn-close-mobile d-lg-none ms-auto" onclick="closeMobileSidebar()" aria-label="Tutup Menu"><i class="bx bx-x fs-5"></i></button>
                <button type="button" class="btn-sidebar-toggle" id="btnToggleSidebar" title="Ciutkan / Lebarkan Menu" aria-label="Toggle Sidebar">
                    <i class="bx bx-chevron-left toggle-icon" id="sidebarToggleIcon"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                
                <?php if (($adminUser['role'] ?? '') === 'admin'): ?>
                <!-- ================= KHUSUS ADMINISTRATOR PUSAT ================= -->
                <div class="nav-category">Navigasi Utama</div>
                <a class="nav-link-modern active" id="nav-dashboard" onclick="switchTab('dashboard')" title="Dashboard">
                    <i class="bx bx-home-alt"></i>
                    <span>Dashboard</span>
                </a>

                <div class="nav-category">Bank Data & Ujian</div>
                <a class="nav-link-modern" id="nav-kategori" onclick="switchTab('kategori')" title="Kategori Soal">
                    <i class="bx bx-folder"></i>
                    <span>Kategori Soal</span>
                </a>
                <a class="nav-link-modern" id="nav-soal" onclick="switchTab('soal')" title="Bank Soal">
                    <i class="bx bx-book-open"></i>
                    <span>Bank Soal</span>
                </a>
                <a class="nav-link-modern" id="nav-jadwal" onclick="switchTab('jadwal')" title="Jadwal & Token">
                    <i class="bx bx-calendar-event"></i>
                    <span>Jadwal & Token</span>
                </a>
                <a class="nav-link-modern" id="nav-peserta" onclick="switchTab('peserta')" title="Peserta & Ruang">
                    <i class="bx bx-group"></i><span>Peserta & Ruang</span>
                </a>

                <div class="nav-category">Pengawasan & Nilai</div>
                <a class="nav-link-modern" id="nav-monitoring" onclick="switchTab('monitoring')" title="Live Monitoring">
                    <i class="bx bx-broadcast"></i>
                    <span>Live Monitoring</span>
                </a>
                <a class="nav-link-modern" id="nav-rekap" onclick="switchTab('rekap')" title="Rekap & Hasil">
                    <i class="bx bx-bar-chart-alt-2"></i>
                    <span>Rekap & Hasil</span>
                </a>

                <div class="nav-category">Hak Akses & Pengguna</div>
                <a class="nav-link-modern" id="nav-users" onclick="switchTab('users')" title="Pengguna & Proctor">
                    <i class="bx bx-user-pin"></i>
                    <span>Pengguna & Proctor</span>
                </a>
                <a class="nav-link-modern" id="nav-operasional" onclick="switchTab('operasional')" title="Operasional Sistem">
                    <i class="bx bx-shield-quarter"></i><span>Operasional Sistem</span>
                </a>
                <a class="nav-link-modern" id="nav-audit" onclick="switchTab('audit')" title="Audit Aktivitas">
                    <i class="bx bx-history"></i><span>Audit Aktivitas</span>
                </a>

                <div class="nav-category">Integrasi Eksternal</div>
                <a class="nav-link-modern" id="nav-authbridge" onclick="switchTab('authbridge')" title="Auth Bridge (DB)">
                    <i class="bx bx-link-alt"></i>
                    <span>Auth Bridge (DB)</span>
                </a>

                <?php else: ?>
                <!-- ================= KHUSUS PENGAWAS / PROCTOR RUANGAN ================= -->
                <div class="nav-category">Console Pengawas Ruangan</div>
                <a class="nav-link-modern active" id="nav-monitoring" onclick="switchTab('monitoring')" title="Live Monitoring Ruang">
                    <i class="bx bx-broadcast"></i>
                    <span>Live Monitoring Ruang</span>
                </a>
                <a class="nav-link-modern" id="nav-rekap" onclick="switchTab('rekap')" title="Rekap Nilai Peserta">
                    <i class="bx bx-bar-chart-alt-2"></i>
                    <span>Rekap Nilai Peserta</span>
                </a>
                <?php endif; ?>

            </nav>

            <div class="sidebar-footer">
                <div class="d-flex align-items-center gap-2 footer-user-box">
                    <div class="avatar-circle sm">
                        <span class="avatar-letter"><?= strtoupper(substr($adminUser['nama_lengkap'] ?: 'P', 0, 1)) ?></span>
                    </div>
                    <div class="footer-user-meta">
                        <div class="fw-bold small text-dark lh-1"><?= htmlspecialchars($adminUser['nama_lengkap']) ?></div>
                        <span class="text-muted" style="font-size: 0.72rem;"><?= (($adminUser["role"] ?? "") === "admin") ? "Administrator Pusat" : "Pengawas Ruangan" ?></span>
                    </div>
                </div>
                <a href="index.php?m=logout" class="text-muted footer-logout-btn" title="Keluar dari Pusat">
                    <i class="bx bx-log-out fs-5"></i>
                </a>
            </div>

        </aside>

        <!-- Main Content Area -->
        <div class="main-container">
            
            <!-- Topbar Header -->
            <header class="topbar-modern">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn-mobile-toggle d-lg-none" id="btnMobileToggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu"><i class="bx bx-menu fs-4"></i></button>
                    <h4 class="page-heading-title" id="topbarTitle">Dashboard Overview</h4>
                    <span class="badge-soft-success small d-none d-sm-inline-flex align-items-center gap-1">
                        <i class="bx bx-check-circle"></i> Sistem Aktif
                    </span>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <?php 
                    $bMode = $authBridgeCfg['mode'] ?? 'standalone';
                    $bLabel = ($bMode === 'external_db') ? 'DB: ' . ($authBridgeCfg['external_db']['database'] ?? 'db_spmb2') : strtoupper($bMode);
                    ?>
                    <span class="badge-soft-primary small d-none d-lg-inline-flex align-items-center gap-1">
                        <i class="bx bx-data"></i> <?= htmlspecialchars($bLabel) ?>
                    </span>
                    <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-secondary d-none d-md-flex align-items-center gap-1">
                        <i class="bx bx-window-open"></i> Portal Peserta
                    </a>

                    <div class="topbar-vr d-none d-sm-block"></div>

                    <!-- Profile Avatar Dropdown -->
                    <div class="profile-dropdown-wrapper">
                        <button type="button" class="avatar-profile-btn" id="avatarProfileBtn" aria-expanded="false" title="Menu Akun Pusat">
                            <div class="avatar-circle">
                                <span class="avatar-letter"><?= strtoupper(substr($adminUser['nama_lengkap'] ?: 'P', 0, 1)) ?></span>
                                <span class="avatar-online-dot"></span>
                            </div>
                            <div class="avatar-info d-none d-md-flex flex-column text-start">
                                <span class="avatar-name"><?= htmlspecialchars($adminUser['nama_lengkap']) ?></span>
                                <span class="avatar-role">Pusat CBT</span>
                            </div>
                            <i class="bx bx-chevron-down avatar-arrow" id="avatarArrow"></i>
                        </button>

                        <!-- Dropdown Menu Card -->
                        <div class="profile-dropdown-menu" id="profileDropdownMenu">
                            <div class="dropdown-header-box">
                                <div class="avatar-circle lg">
                                    <span class="avatar-letter"><?= strtoupper(substr($adminUser['nama_lengkap'] ?: 'P', 0, 1)) ?></span>
                                </div>
                                <div class="dropdown-meta">
                                    <div class="dropdown-name"><?= htmlspecialchars($adminUser['nama_lengkap']) ?></div>
                                    <div class="dropdown-badge">@<?= htmlspecialchars($adminUser['username']) ?> · Pusat CBT</div>
                                </div>
                            </div>
                            <div class="dropdown-item-divider"></div>
                            <?php if (($adminUser['role'] ?? '') === 'admin'): ?>
                            <a href="javascript:void(0)" onclick="switchTab('dashboard'); closeProfileDropdown();" class="dropdown-action-item">
                                <i class="bx bx-home-alt text-secondary"></i>
                                <span>Dashboard Utama</span>
                            </a>
                            <a href="javascript:void(0)" onclick="switchTab('authbridge'); closeProfileDropdown();" class="dropdown-action-item">
                                <i class="bx bx-link-alt text-primary"></i>
                                <span>Status Auth Bridge</span>
                            </a>
                            <a href="javascript:void(0)" onclick="switchTab('jadwal'); closeProfileDropdown();" class="dropdown-action-item">
                                <i class="bx bx-calendar-check text-indigo"></i>
                                <span>Jadwal & Token Ujian</span>
                            </a>
                            <a href="javascript:void(0)" onclick="switchTab('monitoring'); closeProfileDropdown();" class="dropdown-action-item">
                                <i class="bx bx-broadcast text-success"></i>
                                <span>Live Monitoring Sesi</span>
                            </a>
                            <?php else: ?>
                            <a href="javascript:void(0)" onclick="switchTab('monitoring'); closeProfileDropdown();" class="dropdown-action-item">
                                <i class="bx bx-broadcast text-success"></i>
                                <span>Live Monitoring Ruangan</span>
                            </a>
                            <a href="javascript:void(0)" onclick="switchTab('rekap'); closeProfileDropdown();" class="dropdown-action-item">
                                <i class="bx bx-bar-chart-alt-2 text-primary"></i>
                                <span>Rekap Nilai Peserta</span>
                            </a>
                            <?php endif; ?>
                            <div class="dropdown-item-divider"></div>
                            <a href="index.php?m=logout" class="dropdown-action-item logout-action">
                                <i class="bx bx-log-out text-danger"></i>
                                <span class="text-danger fw-semibold">Keluar Sistem</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Body View Container -->
            <main class="content-body">
                
                

<?php if (($adminUser["role"] ?? "") === "admin"): ?>
<!-- VIEW 1: DASHBOARD -->
                <div id="view-dashboard" class="tab-pane-content active">
                    
                    <!-- KPI Metric Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="metric-card d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-label">Total Butir Soal</div>
                                    <div class="metric-number"><?= (int)$stats['total_soal'] ?></div>
                                    <small class="text-success fw-semibold"><i class="bx bx-check"></i> Siap Diujikan</small>
                                </div>
                                <div class="metric-icon-wrap" style="background: #eef2ff; color: #4f46e5;">
                                    <i class="bx bx-book-content"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="metric-card d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-label">Paket Jadwal</div>
                                    <div class="metric-number"><?= (int)$stats['total_jadwal'] ?></div>
                                    <small class="text-muted">Aktif & Berjalan</small>
                                </div>
                                <div class="metric-icon-wrap" style="background: #f0fdf4; color: #059669;">
                                    <i class="bx bx-calendar-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="metric-card d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-label">Sedang Ujian</div>
                                    <div class="metric-number text-warning"><?= (int)$stats['total_sedang_ujian'] ?></div>
                                    <small class="text-warning fw-semibold"><i class="bx bx-loader"></i> Peserta Realtime</small>
                                </div>
                                <div class="metric-icon-wrap" style="background: #fffbeb; color: #d97706;">
                                    <i class="bx bx-user-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="metric-card d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-label">Selesai Ujian</div>
                                    <div class="metric-number"><?= (int)$stats['total_selesai'] ?></div>
                                    <small class="text-muted">Riwayat Hasil</small>
                                </div>
                                <div class="metric-icon-wrap" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0;">
                                    <i class="bx bx-task"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Monitoring Summary Table -->
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <h5>Aktivitas Peserta Terbaru</h5>
                            <button class="btn btn-sm btn-outline-secondary" onclick="loadMonitoringList()">
                                <i class="bx bx-refresh me-1"></i> Segarkan
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>No Peserta</th>
                                        <th>Nama Peserta</th>
                                        <th>Paket Ujian</th>
                                        <th>Status Sesi</th>
                                        <th>Nilai Akhir</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyMonitoringRecent">
                                    <tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Memuat aktivitas...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- VIEW 2: KATEGORI SOAL -->
                <div id="view-kategori" class="tab-pane-content">
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <h5>Daftar Kategori / Subtes</h5>
                            <button class="btn-primary-modern" onclick="modalTambahKategori()">
                                <i class="bx bx-plus"></i> Tambah Kategori
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Kategori</th>
                                        <th>Kode</th>
                                        <th>Deskripsi</th>
                                        <th class="text-center">Total Soal</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyKategori">
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Memuat kategori...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- VIEW 3: BANK SOAL -->
                <div id="view-soal" class="tab-pane-content">
                    <div class="card-modern">
                        <div class="card-header-modern flex-wrap gap-2">
                            <h5>Bank Soal CBT</h5>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <select class="form-select form-select-sm" id="filterSoalKategori" onchange="loadSoalList()" style="width: 200px;">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($listKategori as $k): ?>
                                        <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control form-control-sm" id="searchSoalKeyword" placeholder="Cari butir soal..." onkeyup="loadSoalList()" style="width: 200px;">
                                <!-- Export Dropdown -->
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bx bx-export"></i> Ekspor Soal
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px; font-size: 0.85rem;">
                                        <li><h6 class="dropdown-header text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Pilihan Format Ekspor</h6></li>
                                        <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerExportSoal('moodle_xml')"><i class="bx bx-code-alt text-warning me-2 fs-6"></i>Moodle XML (.xml)</a></li>
                                        <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerExportSoal('aiken')"><i class="bx bx-file-blank text-primary me-2 fs-6"></i>Format Aiken (.txt)</a></li>
                                        <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerExportSoal('csv')"><i class="bx bx-spreadsheet text-success me-2 fs-6"></i>Format Excel / CSV (.csv)</a></li>
                                        <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerExportSoal('json')"><i class="bx bx-data text-secondary me-2 fs-6"></i>Format Backup JSON (.json)</a></li>
                                    </ul>
                                </div>

                                <!-- Import Button -->
                                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" onclick="openImportModal()">
                                    <i class="bx bx-import"></i> Impor Soal
                                </button>

                                <button class="btn-primary-modern" onclick="modalTambahSoal()">
                                    <i class="bx bx-plus"></i> Tambah Soal
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Pertanyaan</th>
                                        <th>Kategori</th>
                                        <th class="text-center">Gambar</th>
                                        <th class="text-center">Kunci</th>
                                        <th class="text-center">Bobot</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodySoal">
                                    <tr><td colspan="7" class="text-center py-4 text-muted">Memuat butir soal...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- VIEW 4: JADWAL & TOKEN -->
                <div id="view-jadwal" class="tab-pane-content">
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <h5>Paket Jadwal & Token Ujian</h5>
                            <button class="btn-primary-modern" onclick="modalTambahJadwal()">
                                <i class="bx bx-plus"></i> Buat Paket Ujian
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Ujian</th>
                                        <th>Kode</th>
                                        <th>Tipe</th>
                                        <th>Durasi</th>
                                        <th>Token Ujian</th>
                                        <th>Passing Grade</th>
                                        <th class="text-center">Peserta</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyJadwal">
                                    <tr><td colspan="10" class="text-center py-4 text-muted">Memuat jadwal ujian...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php endif; /* end admin dashboard-jadwal */ ?>
<!-- VIEW 5: LIVE MONITORING -->
                <div id="view-monitoring" class="tab-pane-content">
                    <div class="card-modern">
                        <div class="card-header-modern flex-wrap gap-2">
                            <div>
                                <h5>Live Monitoring Pengawasan Ujian</h5>
                                <small class="text-muted">Data ter-update otomatis setiap 10 detik</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select form-select-sm" id="filterMonitoringJadwal" onchange="loadMonitoringList()" style="width: 220px;">
                                    <option value="">Semua Paket Jadwal</option>
                                    <?php foreach ($listJadwal as $j): ?>
                                        <option value="<?= $j['id_jadwal'] ?>"><?= htmlspecialchars($j['nama_ujian']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline-secondary" onclick="loadMonitoringList()">
                                    <i class="bx bx-refresh me-1"></i> Segarkan
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>No Peserta</th>
                                        <th>Nama Peserta</th>
                                        <th>Paket Ujian</th>
                                        <th>Mulai</th>
                                        <th>Sisa Waktu</th>
                                        <th>Progres</th>
                                        <th>Skor</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Aksi Pengawas</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyMonitoring">
                                    <tr><td colspan="10" class="text-center py-4 text-muted">Memuat data pengawasan...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- VIEW 6: REKAP NILAI -->
                <div id="view-rekap" class="tab-pane-content">
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <h5>Rekap Nilai & Analisis Butir Soal</h5>
                            <button class="btn btn-sm btn-outline-success d-flex align-items-center gap-1" onclick="exportRekapExcel()">
                                <i class="bx bx-export"></i> Ekspor ke Excel (.XLSX)
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pertanyaan</th>
                                        <th>Kategori</th>
                                        <th class="text-center">Kunci</th>
                                        <th class="text-center">Tingkat Kesulitan</th>
                                        <th class="text-center">Status Soal</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyAnalisis">
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Memuat analisis butir soal...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if (($adminUser["role"] ?? "") === "admin"): ?>
<!-- VIEW 7: AUTH BRIDGE -->
                <div id="view-peserta" class="tab-pane-content">
                    <div class="row g-3">
                        <div class="col-lg-4"><div class="card-modern p-3"><h5>Tambah / Ubah Peserta</h5>
                            <form id="formPeserta" class="mt-3"><input type="hidden" name="id_peserta" id="pesertaId">
                                <input class="form-control mb-2" name="no_peserta" id="pesertaNo" placeholder="Nomor peserta" required>
                                <input class="form-control mb-2" name="nama_lengkap" id="pesertaNama" placeholder="Nama lengkap" required>
                                <input class="form-control mb-2" name="password" placeholder="PIN/password (kosong = tidak diubah)" type="password">
                                <input class="form-control mb-2" name="email" id="pesertaEmail" placeholder="Email (opsional)">
                                <input class="form-control mb-2" name="kelompok" id="pesertaKelompok" placeholder="Kelas/kelompok">
                                <select class="form-select mb-2" name="id_ruang" id="pesertaRuang"><option value="">Tanpa ruang</option></select>
                                <input type="hidden" name="status" value="1"><button class="btn btn-primary w-100">Simpan Peserta</button>
                                <button type="button" class="btn btn-light w-100 mt-2" onclick="resetPesertaForm()">Bersihkan</button>
                            </form><hr><h6>Impor CSV</h6><form id="formImportPeserta"><input class="form-control mb-2" type="file" name="peserta_file" accept=".csv" required><small class="text-muted d-block mb-2">Kolom: no_peserta,nama_lengkap,password,email,kelompok</small><button class="btn btn-outline-primary w-100">Impor Peserta</button></form></div></div>
                        <div class="col-lg-8"><div class="card-modern"><div class="card-header-modern"><h5>Daftar Peserta Resmi</h5><input id="searchPeserta" class="form-control form-control-sm" style="max-width:240px" placeholder="Cari..." oninput="loadPeserta()"></div>
                            <div class="table-responsive"><table class="table table-modern"><thead><tr><th>Nomor</th><th>Nama</th><th>Kelompok/Ruang</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="tbodyPeserta"></tbody></table></div>
                        </div></div>
                        <div class="col-12"><div class="card-modern p-3"><h5>Kelola Ruang</h5><form id="formRuang" class="row g-2 mt-1">
                            <input type="hidden" name="id_ruang"><div class="col-md-2"><input class="form-control" name="kode_ruang" placeholder="Kode" required></div><div class="col-md-3"><input class="form-control" name="nama_ruang" placeholder="Nama ruang" required></div>
                            <div class="col-md-3"><input class="form-control" name="lokasi" placeholder="Lokasi"></div><div class="col-md-2"><input class="form-control" type="number" name="kapasitas" placeholder="Kapasitas"></div><div class="col-md-2"><button class="btn btn-primary w-100">Simpan</button></div></form><div id="listRuang" class="mt-3"></div>
                            <hr><h6>Penugasan Peserta ke Jadwal</h6><form id="formAssignment" class="row g-2"><div class="col-md-5"><select class="form-select" name="id_peserta" id="assignmentPeserta" required><option value="">Pilih peserta</option></select></div><div class="col-md-5"><select class="form-select" name="id_jadwal" required><option value="">Pilih jadwal</option><?php foreach($listJadwal as $j): ?><option value="<?= (int)$j['id_jadwal'] ?>"><?= htmlspecialchars($j['nama_ujian']) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-success w-100">Tugaskan</button></div></form>
                        </div></div>
                    </div>
                </div>

                <div id="view-operasional" class="tab-pane-content">
                    <div class="row g-3"><div class="col-md-3"><div class="metric-card"><div class="metric-label">Peserta</div><div class="metric-number" id="opPeserta">-</div></div></div><div class="col-md-3"><div class="metric-card"><div class="metric-label">Ruang</div><div class="metric-number" id="opRuang">-</div></div></div><div class="col-md-3"><div class="metric-card"><div class="metric-label">Audit Log</div><div class="metric-number" id="opAudit">-</div></div></div><div class="col-md-3"><div class="metric-card"><div class="metric-label">Status Upload</div><div class="metric-number fs-5" id="opUpload">-</div></div></div></div>
                    <div class="row g-3 mt-1"><div class="col-lg-6"><div class="card-modern p-3"><h5>Backup & Pemulihan</h5><a class="btn btn-primary mt-2" href="model/export/backup_database.php"><i class="bx bx-download"></i> Unduh Backup SQL</a><form id="formRestore" class="mt-3"><input class="form-control mb-2" type="file" name="backup_file" accept=".sql" required><input class="form-control mb-2" name="confirmation" placeholder="Ketik PULIHKAN"><button class="btn btn-outline-danger">Pulihkan Database</button></form></div></div>
                    <div class="col-lg-6"><div class="card-modern p-3"><h5>Pengumuman Peserta</h5><form id="formPengumuman"><input class="form-control mb-2" name="judul" placeholder="Judul" required><textarea class="form-control mb-2" name="isi" placeholder="Isi pengumuman" required></textarea><input type="hidden" name="target" value="semua"><button type="submit" class="btn btn-primary">Terbitkan</button></form><div id="listPengumuman" class="mt-3"></div></div></div></div>
                </div>
                <div id="view-audit" class="tab-pane-content"><div class="card-modern"><div class="card-header-modern flex-wrap gap-2"><h5>Audit Aktivitas Sistem</h5><div class="d-flex gap-2 ms-auto"><div class="input-group input-group-sm" style="width:240px"><span class="input-group-text bg-white"><i class="bx bx-search"></i></span><input class="form-control" placeholder="Cari aktivitas..." oninput="searchAudit(this.value)"></div><select class="form-select form-select-sm" style="width:80px" onchange="changeAuditSize(this.value)"><option>10</option><option>25</option><option>50</option><option>100</option></select><button class="btn btn-sm btn-light border" onclick="loadOperational()"><i class="bx bx-refresh"></i></button></div></div><div class="table-responsive"><table class="table table-modern"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>IP</th></tr></thead><tbody id="tbodyAudit"></tbody></table></div><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 border-top"><small class="text-muted" id="auditInfo">Memuat data...</small><div class="d-flex gap-1" id="auditPagination"></div></div></div></div>

                <div id="view-authbridge" class="tab-pane-content">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card-modern h-100">
                                <div class="card-header-modern">
                                    <h5>Status Integrasi Database Klien</h5>
                                </div>
                                <div class="p-4">
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="text-muted fw-medium" style="width: 170px;">Mode Adapter:</td>
                                            <td><span class="badge-soft-primary"><?= strtoupper(($authBridgeCfg['mode'] ?? 'STANDALONE')) ?></span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-medium">Database Target:</td>
                                            <td><strong class="text-dark"><?= htmlspecialchars($authBridgeCfg['external_db']['db_name'] ?? '-') ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-medium">Tabel Peserta:</td>
                                            <td><code><?= htmlspecialchars($authBridgeCfg['external_db']['table_user'] ?? '-') ?></code></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-medium">Kolom Nomor / NIK:</td>
                                            <td><code><?= htmlspecialchars($authBridgeCfg['external_db']['column_username'] ?? '-') ?></code></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-medium">Kolom Nama:</td>
                                            <td><code><?= htmlspecialchars($authBridgeCfg['external_db']['column_name'] ?? '-') ?></code></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-medium">Proteksi Password:</td>
                                            <td><?= ($authBridgeCfg['require_password'] ?? false) ? '<span class="badge-soft-warning">Wajib</span>' : '<span class="badge-soft-success">Direct NIK</span>' ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card-modern h-100">
                                <div class="card-header-modern">
                                    <h5>Uji Live Lookup Peserta</h5>
                                </div>
                                <div class="p-4">
                                    <p class="text-muted small">Coba masukkan nomor identitas pendaftar dari database klien untuk memverifikasi kesiapan integrasi.</p>
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" id="testNoPeserta" placeholder="Nomor peserta..." value="9880030521621001">
                                        <button class="btn btn-primary" type="button" onclick="testLookupAuthBridge()" style="background-color: var(--primary); border: none;">
                                            <i class="bx bx-search"></i> Uji
                                        </button>
                                    </div>
                                    <div id="testResultBox" style="display: none;" class="p-3 rounded-3 mt-3 border">
                                        <div class="fw-bold small mb-1" id="testResultHeader"></div>
                                        <div class="fs-5 fw-bold text-dark" id="testResultName"></div>
                                        <div class="small text-muted mt-1" id="testResultDetail"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 7: MANAJEMEN PENGGUNA & PROCTOR -->
                <div id="view-users" class="tab-pane-content">
                    
                    <!-- KPI Summary -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="metric-card d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-label">Total Pengguna Petugas</div>
                                    <div class="metric-number" id="kpiUserTotal">0</div>
                                    <small class="text-muted">Akses Dashboard & Pengawas</small>
                                </div>
                                <div class="metric-icon-wrap" style="background: #eef2ff; color: #4f46e5;">
                                    <i class="bx bx-group"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="metric-card d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-label">Administrator Pusat</div>
                                    <div class="metric-number text-primary" id="kpiUserAdmin">0</div>
                                    <small class="text-muted">Akses Penuh Seluruh Sistem</small>
                                </div>
                                <div class="metric-icon-wrap" style="background: #f1f5f9; color: #1e293b;">
                                    <i class="bx bx-shield-quarter"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="metric-card d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="metric-label">Pengawas / Proctor</div>
                                    <div class="metric-number text-success" id="kpiUserPengawas">0</div>
                                    <small class="text-muted">Console Pengawasan Ruangan</small>
                                </div>
                                <div class="metric-icon-wrap" style="background: #ecfdf5; color: #059669;">
                                    <i class="bx bx-broadcast"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="card-modern">
                        <div class="card-header-modern d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h5 class="mb-1 fw-bold text-dark">Daftar Pengguna Administrator & Pengawas Ruang</h5>
                                <span class="text-muted small">Kelola hak akses pengawas ruangan, kredensial proctor, dan akun administrator CBT.</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" onclick="loadUsersList()">
                                    <i class="bx bx-refresh"></i> Segarkan
                                </button>
                                <button type="button" class="btn-primary-modern" onclick="openAddUserModal()">
                                    <i class="bx bx-user-plus"></i> Tambah Pengguna Baru
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No</th>
                                        <th style="width: 220px;">Username</th>
                                        <th>Nama Lengkap</th>
                                        <th style="width: 200px;">Hak Akses (Role)</th>
                                        <th style="width: 180px;">Tanggal Terdaftar</th>
                                        <th class="text-center" style="width: 130px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyUsers">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Memuat data pengguna...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            
<?php endif; /* end admin authbridge-users */ ?>
</main>
        </div>

    </div>

    <!-- ==================== MODAL USER FORM ==================== -->
    <div class="modal fade" id="modalUserForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 14px;">
                <form id="formUserModal" onsubmit="saveUserForm(event)">
                    <div class="modal-header border-bottom">
                        <h6 class="modal-title fw-bold" id="modalUserFormTitle">Tambah Pengguna Baru</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" id="user_id_admin" name="id_admin" value="0">
                        
                        <div class="mb-3">
                            <label class="form-label" for="user_username">Username Login <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-modern" id="user_username" name="username" placeholder="Contoh: proctor_lab1" required>
                            <small class="text-muted" style="font-size: 0.76rem;">Gunakan huruf kecil tanpa spasi.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="user_nama">Nama Lengkap Petugas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-modern" id="user_nama" name="nama_lengkap" placeholder="Contoh: Budi Santoso, S.Kom" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="user_role">Peran & Hak Akses (Role) <span class="text-danger">*</span></label>
                            <select class="form-control-modern" id="user_role" name="role" required>
                                <option value="pengawas">Pengawas / Proctor Ruangan (Console Pengawasan)</option>
                                <option value="admin">Administrator Pusat (Akses Penuh Seluruh Sistem)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="user_password" id="labelUserPassword">Kata Sandi <span class="text-danger">*</span></label>
                            <input type="password" class="form-control-modern" id="user_password" name="password" placeholder="Minimal 5 karakter">
                            <small class="text-muted" id="hintUserPassword" style="font-size: 0.76rem;">Minimal 5 karakter.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn-primary-modern">Simpan Pengguna</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL GANTI PASSWORD ==================== -->
    <div class="modal fade" id="modalChangePassword" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 14px;">
                <form id="formChangePassword" onsubmit="submitChangePassword(event)">
                    <div class="modal-header border-bottom">
                        <h6 class="modal-title fw-bold">Ganti Kata Sandi</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" id="pwd_id_admin" value="0">
                        <div class="mb-2">
                            <small class="text-muted d-block">Pengguna:</small>
                            <div class="fw-bold text-dark" id="pwd_username_text">-</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pwd_new">Kata Sandi Baru</label>
                            <input type="password" class="form-control-modern" id="pwd_new" placeholder="Minimal 5 karakter" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn-primary-modern">Perbarui Sandi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- ==================== MODAL IMPORT SOAL (MOODLE / AIKEN / CSV) ==================== -->
    <div class="modal fade" id="modalImportSoal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow" style="border-radius: 14px;">
                <div class="modal-header border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                            <i class="bx bx-import"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold mb-0">Impor Bank Soal (Moodle / Aiken / Excel)</h6>
                            <small class="text-muted">Mendukung format Aiken Moodle, Moodle XML, CSV/Excel, dan JSON.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Nav Tabs for Import Mode -->
                    <ul class="nav nav-pills mb-3 p-1 bg-light rounded-3" id="pills-import-tab" role="tablist">
                        <li class="nav-item flex-fill text-center" role="presentation">
                            <button class="nav-link active w-100 py-2 fw-semibold" id="tab-import-text" data-bs-toggle="pill" data-bs-target="#content-import-text" type="button" role="tab">
                                <i class="bx bx-paste me-1"></i> Format Aiken / Teks Cepat (Copy-Paste)
                            </button>
                        </li>
                        <li class="nav-item flex-fill text-center" role="presentation">
                            <button class="nav-link w-100 py-2 fw-semibold" id="tab-import-file" data-bs-toggle="pill" data-bs-target="#content-import-file" type="button" role="tab">
                                <i class="bx bx-cloud-upload me-1"></i> Unggah Berkas File (.txt / .xml / .csv)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="pills-import-tabContent">
                        <!-- TAB 1: PASTE AIKEN TEXT -->
                        <div class="tab-pane fade show active" id="content-import-text" role="tabpanel">
                            <form id="formImportAikenText" onsubmit="submitImportText(event)">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-bold">Target Kategori Soal <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="importTextKategori" required>
                                            <?php foreach ($listKategori as $k): ?>
                                                <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold">Bobot Nilai Default</label>
                                        <input type="number" class="form-control form-control-sm" id="importTextBobot" value="10" min="1" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label small fw-bold mb-0">Tempelkan Teks Soal Format Aiken:</label>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small" onclick="loadAikenSample()">
                                            <i class="bx bx-file me-1"></i> Muat Contoh Format Aiken
                                        </button>
                                    </div>
                                    <textarea class="form-control font-monospace" id="aikenTextContent" rows="8" placeholder="Contoh format Aiken:&#10;Ibu kota Indonesia adalah...&#10;A. Jakarta&#10;B. Surabaya&#10;C. Bandung&#10;D. Medan&#10;ANSWER: A" style="font-size: 0.82rem; line-height: 1.5;" onkeyup="previewAikenCount()"></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded-3 mb-3">
                                    <span class="small text-muted" id="aikenDetectedBadge">
                                        <i class="bx bx-search-alt"></i> Belum ada soal yang terdeteksi
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewAikenCount()">
                                        <i class="bx bx-scan"></i> Hitung Butir Soal
                                    </button>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn-primary-modern" id="btnSubmitImportText">
                                        <i class="bx bx-check"></i> Proses Impor ke Bank Soal
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 2: UPLOAD FILE -->
                        <div class="tab-pane fade" id="content-import-file" role="tabpanel">
                            <form id="formImportFile" onsubmit="submitImportFile(event)">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-bold">Target Kategori Soal <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="importFileKategori" required>
                                            <?php foreach ($listKategori as $k): ?>
                                                <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold">Bobot Nilai Default</label>
                                        <input type="number" class="form-control form-control-sm" id="importFileBobot" value="10" min="1" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Pilih Berkas Soal (.xml / .txt / .csv / .json) <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="fileSoalUpload" accept=".xml,.txt,.csv,.json" required>
                                    <small class="text-muted mt-1 d-block" style="font-size: 0.76rem;">
                                        Mendukung berkas Moodle XML (.xml), Aiken Plain Text (.txt), Spreadsheet (.csv), atau Cadangan JSON (.json).
                                    </small>
                                </div>

                                <div class="p-3 bg-light rounded-3 mb-4">
                                    <div class="fw-bold small text-dark mb-1"><i class="bx bx-download me-1 text-primary"></i> Template Berkas Contoh:</div>
                                    <div class="d-flex flex-wrap gap-3">
                                        <a href="model/export/soal_import_export.php?action=template&type=csv" class="small text-decoration-none">
                                            <i class="bx bx-spreadsheet text-success"></i> Unduh Template Excel / CSV
                                        </a>
                                        <a href="model/export/soal_import_export.php?action=template&type=aiken" class="small text-decoration-none">
                                            <i class="bx bx-file text-primary"></i> Unduh Contoh Aiken (.txt)
                                        </a>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn-primary-modern" id="btnSubmitImportFile">
                                        <i class="bx bx-cloud-upload"></i> Unggah & Impor Berkas
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ==================== MODAL KATEGORI ==================== -->
    <div class="modal fade" id="modalKategori" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 14px;">
                <form id="formKategori">
                    <div class="modal-header border-bottom">
                        <h6 class="modal-title fw-bold" id="modalKategoriTitle">Tambah Kategori Baru</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" id="kat_id" name="id_kategori">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nama Kategori / Subtes</label>
                            <input type="text" class="form-control" id="kat_nama" name="nama_kategori" placeholder="Contoh: Tes Potensi Skolastik" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Kode Singkatan</label>
                            <input type="text" class="form-control" id="kat_kode" name="kode_kategori" placeholder="Contoh: TPS" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Deskripsi (Opsional)</label>
                            <textarea class="form-control" id="kat_deskripsi" name="deskripsi" rows="2" placeholder="Keterangan kategori"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary" style="background-color: var(--primary); border: none;">Simpan Kategori</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL SOAL ==================== -->
    <div class="modal fade" id="modalSoal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 14px;">
                <form id="formSoal" enctype="multipart/form-data">
                    <div class="modal-header border-bottom">
                        <h6 class="modal-title fw-bold" id="modalSoalTitle">Tambah Butir Soal Baru</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" id="soal_id" name="id_soal">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label small fw-bold">Nama Sesi Ujian</label><input type="text" class="form-control" id="jadwal_nama_sesi" name="nama_sesi" value="Sesi Utama" placeholder="Contoh: Sesi 1 Pagi"></div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Kategori Soal</label>
                                <select class="form-select" id="soal_kategori" name="id_kategori" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($listKategori as $k): ?>
                                        <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold">Kunci Jawaban</label>
                                <select class="form-select" id="soal_kunci" name="kunci_jawaban" required>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                    <option value="E">E</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold">Bobot Poin</label>
                                <input type="number" class="form-control" id="soal_bobot" name="bobot_nilai" value="5" min="1" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pertanyaan</label>
                            <textarea class="form-control" id="soal_pertanyaan" name="pertanyaan" rows="4" placeholder="Tulis butir pertanyaan di sini..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Gambar Pendukung (Opsional)</label>
                            <input type="file" class="form-control form-control-sm" id="soal_gambar" name="gambar" accept="image/*">
                            <div id="previewGambarLama" class="mt-2" style="display: none;">
                                <img id="imgPreviewOld" src="" alt="preview" class="img-thumbnail" style="max-height: 100px;">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Opsi A</label>
                                <input type="text" class="form-control" id="soal_opsi_a" name="opsi_a" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Opsi B</label>
                                <input type="text" class="form-control" id="soal_opsi_b" name="opsi_b" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Opsi C</label>
                                <input type="text" class="form-control" id="soal_opsi_c" name="opsi_c" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Opsi D</label>
                                <input type="text" class="form-control" id="soal_opsi_d" name="opsi_d" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Opsi E (Opsional)</label>
                                <input type="text" class="form-control" id="soal_opsi_e" name="opsi_e">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pembahasan (Opsional)</label>
                            <textarea class="form-control" id="soal_pembahasan" name="pembahasan" rows="2" placeholder="Uraian pembahasan kunci jawaban..."></textarea>
                        </div>

                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary" style="background-color: var(--primary); border: none;">Simpan Butir Soal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL JADWAL ==================== -->
    <div class="modal fade" id="modalJadwal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 14px;">
                <form id="formJadwal">
                    <div class="modal-header border-bottom">
                        <h6 class="modal-title fw-bold" id="modalJadwalTitle">Buat Paket Ujian Baru</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" id="jadwal_id" name="id_jadwal">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Nama Paket Ujian</label>
                                <input type="text" class="form-control" id="jadwal_nama" name="nama_ujian" placeholder="Contoh: Seleksi Mandiri CBT 2026" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold">Kode Ujian</label>
                                <input type="text" class="form-control" id="jadwal_kode" name="kode_ujian" placeholder="Contoh: CBT-01" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold">Tipe Ujian</label>
                                <select class="form-select" id="jadwal_tipe" name="tipe_ujian">
                                    <option value="standar">Standar (Single)</option>
                                    <option value="multi_subtes">Multi Subtes</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Kategori Utama</label>
                                <select class="form-select" id="jadwal_kategori" name="id_kategori">
                                    <option value="">-- Semua Kategori --</option>
                                    <?php foreach ($listKategori as $k): ?>
                                        <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold">Durasi (Menit)</label>
                                <input type="number" class="form-control" id="jadwal_durasi" name="durasi_menit" value="90" min="5" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold">Passing Grade</label>
                                <input type="number" class="form-control" id="jadwal_pass" name="passing_grade" value="65" min="0" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Tanggal & Jam Mulai</label>
                                <input type="datetime-local" class="form-control" id="jadwal_tgl_mulai" name="tgl_mulai" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Tanggal & Jam Selesai</label>
                                <input type="datetime-local" class="form-control" id="jadwal_tgl_selesai" name="tgl_selesai" required>
                            </div>
                        </div>

                        <div class="row align-items-center">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Token Ujian</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace fw-bold text-uppercase" id="jadwal_token" name="token_ujian" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateToken()">
                                        <i class="bx bx-sync"></i> Acak
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold">Status Ujian</label>
                                <select class="form-select" id="jadwal_status" name="status_ujian">
                                    <option value="aktif">Aktif</option>
                                    <option value="draft">Draft</option><option value="selesai">Selesai</option><option value="arsip">Arsip</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="jadwal_acak_soal" name="acak_soal" value="1" checked>
                                    <label class="form-check-label small" for="jadwal_acak_soal">Acak Soal</label>
                                </div>
                            </div>
                        </div>
                        <div class="row"><div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="jadwal_wajib_peserta" name="wajib_peserta_terdaftar" value="1"><label class="form-check-label small">Hanya peserta yang ditugaskan</label></div></div>
                        <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="jadwal_tampil_hasil" name="tampilkan_hasil" value="1" checked><label class="form-check-label small">Tampilkan hasil ke peserta</label></div></div>
                        <div class="col-md-4"><label class="form-label small">Maks. perangkat</label><input class="form-control form-control-sm" type="number" id="jadwal_maks_perangkat" name="maks_perangkat" value="1" min="1" max="5"></div></div>

                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary" style="background-color: var(--primary); border: none;">Simpan Paket Ujian</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script Logic CRUD CBT -->
    <script>
        function notify(msg, type = 'success') {
            let bgGradient = '#10b981';
            if (type === 'error' || type === 'danger') bgGradient = '#ef4444';
            if (type === 'primary') bgGradient = '#4f46e5';

            Toastify({
                text: msg,
                duration: 2200,
                close: true,
                gravity: "top",
                position: "right",
                stopOnFocus: true,
                style: { 
                    background: bgGradient,
                    borderRadius: "8px",
                    fontWeight: "500",
                    fontSize: "0.84rem"
                }
            }).showToast();
        }

        const tabTitles = {
            'dashboard': 'Dashboard Overview',
            'kategori': 'Manajemen Kategori Soal',
            'soal': 'Bank Soal CBT',
            'jadwal': 'Jadwal & Token Ujian',
            'monitoring': 'Live Monitoring Pengawasan',
            'rekap': 'Rekap Hasil & Analisis',
            'authbridge': 'Universal Auth Bridge Adapter',
            'users': 'Manajemen Pengguna & Proctor Ruangan',
            'peserta': 'Peserta Resmi & Ruang Ujian',
            'operasional': 'Operasional Sistem',
            'audit': 'Audit Aktivitas Sistem'
        };

        const currentRole = '<?= $adminUser["role"] ?? "admin" ?>';
        const allowedTabsPengawas = ['monitoring', 'rekap'];

        function switchTab(tabName) {
            if (!tabName) {
                tabName = (currentRole === 'pengawas') ? 'monitoring' : 'dashboard';
            }

            // Keamanan sisi client: jika role pengawas mencoba akses selain monitoring & rekap
            if (currentRole === 'pengawas' && !allowedTabsPengawas.includes(tabName)) {
                tabName = 'monitoring';
            }
            document.querySelectorAll('.tab-pane-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-link-modern').forEach(el => el.classList.remove('active'));

            const targetView = document.getElementById('view-' + tabName);
            const targetNav = document.getElementById('nav-' + tabName);

            if (targetView) targetView.classList.add('active');
            if (targetNav) targetNav.classList.add('active');

            if (tabTitles[tabName]) {
                document.getElementById('topbarTitle').innerText = tabTitles[tabName];
            }

            // Persist active tab in URL hash and localStorage
            if (history.replaceState) {
                history.replaceState(null, null, '#' + tabName);
            } else {
                window.location.hash = tabName;
            }
            localStorage.setItem('simpel_cbt_active_tab', tabName);

            // Close mobile sidebar automatically
            closeMobileSidebar();

            if (tabName === 'monitoring') loadMonitoringList();
            if (tabName === 'rekap') loadAnalisisList();
            if (tabName === 'users') loadUsersList();
            if (tabName === 'soal') loadSoalList();
            if (tabName === 'jadwal') loadJadwalList();
            if (tabName === 'kategori') loadKategoriList();
            if (tabName === 'peserta') { loadPeserta(); loadRuang(); }
            if (tabName === 'operasional') loadOperational();
            if (tabName === 'audit') loadOperational();
        }

        // Mobile Sidebar Controls
        function toggleMobileSidebar() {
            const sidebar = document.querySelector('.sidebar-modern');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('show');
            if (backdrop) backdrop.classList.toggle('show');
        }

        function closeMobileSidebar() {
            const sidebar = document.querySelector('.sidebar-modern');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Restore active tab from URL hash or localStorage
            let currentHash = window.location.hash.replace('#', '').trim();
            let savedTab = localStorage.getItem('simpel_cbt_active_tab') || '';
            let targetTab = 'dashboard';

            // Jika user adalah pengawas, default tab adalah monitoring
            const userRole = '<?= $adminUser["role"] ?? "admin" ?>';
            if (userRole === 'pengawas') {
                targetTab = 'monitoring';
            }

            if (currentHash && document.getElementById('view-' + currentHash)) {
                targetTab = currentHash;
            } else if (savedTab && document.getElementById('view-' + savedTab)) {
                // Pastikan role pengawas tidak restore ke tab yang disembunyikan
                if (userRole === 'pengawas' && !['monitoring', 'rekap'].includes(savedTab)) {
                    targetTab = 'monitoring';
                } else {
                    targetTab = savedTab;
                }
            }

            switchTab(targetTab);

            // Listen for browser back/forward or hash change
            window.addEventListener('hashchange', function() {
                let h = window.location.hash.replace('#', '').trim();
                if (h && document.getElementById('view-' + h)) {
                    switchTab(h);
                }
            });

            loadKategoriList();
            loadUsersList();
            loadSoalList();
            loadJadwalList();
            loadMonitoringList();
            loadAnalisisList();

            setInterval(() => {
                if (document.getElementById('view-monitoring').classList.contains('active') ||
                    document.getElementById('view-dashboard').classList.contains('active')) {
                    loadMonitoringList(true);
                }
            }, 10000);
        });

        // 1. KATEGORI
        function loadKategoriList() {
            fetch('model/ajax/cbt/kategori_crud.php?action=list')
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('tbodyKategori');
                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada kategori soal.</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach((k, idx) => {
                    html += `
                        <tr>
                            <td>${idx + 1}</td>
                            <td class="fw-semibold text-dark">${k.nama_kategori}</td>
                            <td><span class="badge-soft-secondary">${k.kode_kategori}</span></td>
                            <td><small class="text-muted">${k.deskripsi || '-'}</small></td>
                            <td class="text-center font-monospace fw-bold">${k.total_soal}</td>
                            <td class="text-center">
                                <button class="btn-action-icon me-1" onclick="editKategori(${k.id_kategori})" title="Edit"><i class="bx bx-edit-alt"></i></button>
                                <button class="btn-action-icon text-danger" onclick="deleteKategori(${k.id_kategori})" title="Hapus"><i class="bx bx-trash"></i></button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
        }

        function modalTambahKategori() {
            document.getElementById('formKategori').reset();
            document.getElementById('kat_id').value = '';
            document.getElementById('modalKategoriTitle').innerText = 'Tambah Kategori Baru';
            new bootstrap.Modal(document.getElementById('modalKategori')).show();
        }

        function editKategori(id) {
            fetch('model/ajax/cbt/kategori_crud.php?action=detail&id_kategori=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const k = data.data;
                    document.getElementById('kat_id').value = k.id_kategori;
                    document.getElementById('kat_nama').value = k.nama_kategori;
                    document.getElementById('kat_kode').value = k.kode_kategori;
                    document.getElementById('kat_deskripsi').value = k.deskripsi || '';
                    document.getElementById('modalKategoriTitle').innerText = 'Edit Kategori';
                    new bootstrap.Modal(document.getElementById('modalKategori')).show();
                }
            });
        }

        document.getElementById('formKategori').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('model/ajax/cbt/kategori_crud.php?action=save', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    notify(data.msg);
                    bootstrap.Modal.getInstance(document.getElementById('modalKategori')).hide();
                    loadKategoriList();
            loadUsersList();
                } else {
                    notify(data.msg, 'error');
                }
            });
        });

        function deleteKategori(id) {
            Swal.fire({
                title: 'Hapus Kategori?',
                text: 'Pastikan tidak ada soal yang terhubung ke kategori ini.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                confirmButtonText: 'Ya, Hapus'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('id_kategori', id);
                    fetch('model/ajax/cbt/kategori_crud.php?action=delete', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            notify(data.msg);
                            loadKategoriList();
            loadUsersList();
                        } else {
                            notify(data.msg, 'error');
                        }
                    });
                }
            });
        }

        // 2. BANK SOAL
        function loadSoalList() {
            const kat = document.getElementById('filterSoalKategori').value;
            const search = document.getElementById('searchSoalKeyword').value;

            fetch(`model/ajax/cbt/soal_crud.php?action=list&id_kategori=${kat}&search=${encodeURIComponent(search)}`)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('tbodySoal');
                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Belum ada butir soal.</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach((s, idx) => {
                    html += `
                        <tr>
                            <td>${idx + 1}</td>
                            <td>${s.pertanyaan_preview}</td>
                            <td><span class="badge-soft-primary">${s.nama_kategori}</span></td>
                            <td class="text-center">${s.gambar ? '<i class="bx bx-image text-success fs-5"></i>' : '<span class="text-muted">-</span>'}</td>
                            <td class="text-center font-monospace fw-bold text-primary">${s.kunci_jawaban}</td>
                            <td class="text-center">${s.bobot_nilai}</td>
                            <td class="text-center">
                                <button class="btn-action-icon me-1" onclick="editSoal(${s.id_soal})" title="Edit"><i class="bx bx-edit-alt"></i></button>
                                <button class="btn-action-icon text-danger" onclick="deleteSoal(${s.id_soal})" title="Hapus"><i class="bx bx-trash"></i></button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
        }

        function modalTambahSoal() {
            document.getElementById('formSoal').reset();
            document.getElementById('soal_id').value = '';
            document.getElementById('previewGambarLama').style.display = 'none';
            document.getElementById('modalSoalTitle').innerText = 'Tambah Butir Soal Baru';
            new bootstrap.Modal(document.getElementById('modalSoal')).show();
        }

        function editSoal(id) {
            fetch('model/ajax/cbt/soal_crud.php?action=detail&id_soal=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const s = data.data;
                    document.getElementById('soal_id').value = s.id_soal;
                    document.getElementById('soal_kategori').value = s.id_kategori;
                    document.getElementById('soal_pertanyaan').value = s.pertanyaan;
                    document.getElementById('soal_kunci').value = s.kunci_jawaban;
                    document.getElementById('soal_bobot').value = s.bobot_nilai;
                    document.getElementById('soal_opsi_a').value = s.opsi_a;
                    document.getElementById('soal_opsi_b').value = s.opsi_b;
                    document.getElementById('soal_opsi_c').value = s.opsi_c;
                    document.getElementById('soal_opsi_d').value = s.opsi_d;
                    document.getElementById('soal_opsi_e').value = s.opsi_e || '';
                    document.getElementById('soal_pembahasan').value = s.pembahasan || '';

                    const pBox = document.getElementById('previewGambarLama');
                    if (s.gambar) {
                        document.getElementById('imgPreviewOld').src = '../uploads/cbt/' + s.gambar;
                        pBox.style.display = 'block';
                    } else {
                        pBox.style.display = 'none';
                    }

                    document.getElementById('modalSoalTitle').innerText = 'Edit Butir Soal';
                    new bootstrap.Modal(document.getElementById('modalSoal')).show();
                }
            });
        }

        document.getElementById('formSoal').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('model/ajax/cbt/soal_crud.php?action=save', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    notify(data.msg);
                    bootstrap.Modal.getInstance(document.getElementById('modalSoal')).hide();
                    loadSoalList();
                } else {
                    notify(data.msg, 'error');
                }
            });
        });

        function deleteSoal(id) {
            Swal.fire({
                title: 'Hapus Soal Ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                confirmButtonText: 'Ya, Hapus'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('id_soal', id);
                    fetch('model/ajax/cbt/soal_crud.php?action=delete', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            notify(data.msg);
                            loadSoalList();
                        } else {
                            notify(data.msg, 'error');
                        }
                    });
                }
            });
        }

        // 3. JADWAL
        function loadJadwalList() {
            fetch('model/ajax/cbt/jadwal_crud.php?action=list')
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('tbodyJadwal');
                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">Belum ada paket ujian.</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach((j, idx) => {
                    const badgeStatus = (j.status_ujian === 'aktif') ? 'badge-soft-success' : 'badge-soft-secondary';
                    html += `
                        <tr>
                            <td>${idx + 1}</td>
                            <td class="fw-semibold text-dark">${j.nama_ujian}</td>
                            <td><span class="badge-soft-secondary">${j.kode_ujian}</span></td>
                            <td><span class="badge-soft-primary">${j.tipe_ujian}</span></td>
                            <td>${j.durasi_menit} mnt</td>
                            <td><span class="token-chip">${j.token_ujian}</span></td>
                            <td>${j.passing_grade}</td>
                            <td class="text-center font-monospace">${j.total_peserta}</td>
                            <td class="text-center"><span class="${badgeStatus}">${j.status_ujian}</span></td>
                            <td class="text-center">
                                <button class="btn-action-icon me-1" onclick="editJadwal(${j.id_jadwal})" title="Edit"><i class="bx bx-edit-alt"></i></button>
                                <button class="btn-action-icon text-danger" onclick="deleteJadwal(${j.id_jadwal})" title="Hapus"><i class="bx bx-trash"></i></button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
        }

        function modalTambahJadwal() {
            document.getElementById('formJadwal').reset();
            document.getElementById('jadwal_id').value = '';
            generateToken();

            const now = new Date();
            const future = new Date();
            future.setDate(now.getDate() + 30);

            document.getElementById('jadwal_tgl_mulai').value = now.toISOString().slice(0, 16);
            document.getElementById('jadwal_tgl_selesai').value = future.toISOString().slice(0, 16);

            document.getElementById('modalJadwalTitle').innerText = 'Buat Paket Ujian Baru';
            new bootstrap.Modal(document.getElementById('modalJadwal')).show();
        }

        function generateToken() {
            fetch('model/ajax/cbt/jadwal_crud.php?action=generate_token')
            .then(res => res.json())
            .then(data => {
                if (data.token) {
                    document.getElementById('jadwal_token').value = data.token;
                }
            });
        }

        function editJadwal(id) {
            fetch('model/ajax/cbt/jadwal_crud.php?action=detail&id_jadwal=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const j = data.data;
                    document.getElementById('jadwal_id').value = j.id_jadwal;
                    document.getElementById('jadwal_nama').value = j.nama_ujian;
                    document.getElementById('jadwal_kode').value = j.kode_ujian;
                    document.getElementById('jadwal_tipe').value = j.tipe_ujian;
                    document.getElementById('jadwal_kategori').value = j.id_kategori || '';
                    document.getElementById('jadwal_durasi').value = j.durasi_menit;
                    document.getElementById('jadwal_tgl_mulai').value = j.tgl_mulai.replace(' ', 'T');
                    document.getElementById('jadwal_tgl_selesai').value = j.tgl_selesai.replace(' ', 'T');
                    document.getElementById('jadwal_pass').value = j.passing_grade;
                    document.getElementById('jadwal_token').value = j.token_ujian;
                    document.getElementById('jadwal_status').value = j.status_ujian;
                    document.getElementById('jadwal_acak_soal').checked = (j.acak_soal == 1);
                    document.getElementById('jadwal_wajib_peserta').checked = (j.wajib_peserta_terdaftar == 1);
                    document.getElementById('jadwal_tampil_hasil').checked = (j.tampilkan_hasil == 1);
                    document.getElementById('jadwal_maks_perangkat').value = j.maks_perangkat || 1;
                    document.getElementById('jadwal_nama_sesi').value = j.nama_sesi || 'Sesi Utama';

                    document.getElementById('modalJadwalTitle').innerText = 'Edit Paket Ujian';
                    new bootstrap.Modal(document.getElementById('modalJadwal')).show();
                }
            });
        }

        document.getElementById('formJadwal').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('model/ajax/cbt/jadwal_crud.php?action=save', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    notify(data.msg);
                    bootstrap.Modal.getInstance(document.getElementById('modalJadwal')).hide();
                    loadJadwalList();
                } else {
                    notify(data.msg, 'error');
                }
            });
        });

        function deleteJadwal(id) {
            Swal.fire({
                title: 'Hapus Paket Ujian?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                confirmButtonText: 'Ya, Hapus'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('id_jadwal', id);
                    fetch('model/ajax/cbt/jadwal_crud.php?action=delete', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            notify(data.msg);
                            loadJadwalList();
                        } else {
                            notify(data.msg, 'error');
                        }
                    });
                }
            });
        }

        // 4. LIVE MONITORING
        function loadMonitoringList(silent = false) {
            const jadwalId = document.getElementById('filterMonitoringJadwal').value;
            fetch('model/ajax/cbt/monitoring_api.php?action=list_peserta&id_jadwal=' + jadwalId)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('tbodyMonitoring');
                const tbodyRecent = document.getElementById('tbodyMonitoringRecent');

                if (!data.data || data.data.length === 0) {
                    const emptyRow = '<tr><td colspan="10" class="text-center py-4 text-muted">Belum ada peserta yang mengikuti ujian.</td></tr>';
                    tbody.innerHTML = emptyRow;
                    if (tbodyRecent) tbodyRecent.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada aktivitas peserta.</td></tr>';
                    return;
                }

                let html = '';
                let htmlRecent = '';

                data.data.forEach((p, idx) => {
                    const isMengerjakan = (p.status_sesi === 'sedang_mengerjakan');
                    const isSuspended = (p.status_sesi === 'ditangguhkan');
                    const badgeSesi = isMengerjakan
                        ? '<span class="badge-soft-warning"><i class="bx bx-loader-circle bx-spin me-1"></i> Mengerjakan</span>'
                        : isSuspended ? '<span class="badge bg-danger-subtle text-danger"><i class="bx bx-lock me-1"></i> Ditangguhkan</span>'
                        : '<span class="badge-soft-success"><i class="bx bx-check me-1"></i> Selesai</span>';

                    const sisaMenit = Math.max(0, Math.floor(p.sisa_detik / 60));

                    html += `
                        <tr>
                            <td>${idx + 1}</td>
                            <td class="font-monospace fw-bold">${p.no_pendaftaran}</td>
                            <td>${p.nama_peserta || '-'}</td>
                            <td><small class="text-muted">${p.nama_ujian}</small><br><span class="badge bg-light text-dark border">${opEsc(p.nama_sesi||'Sesi Utama')}</span></td>
                            <td><small>${p.waktu_mulai ? p.waktu_mulai.substring(11, 16) : '-'}</small></td>
                            <td class="font-monospace">${isMengerjakan ? sisaMenit + ' mnt' : '-'}</td>
                            <td><span class="badge-soft-primary">${p.jawaban_terisi} / ${p.total_soal_sesi}</span></td>
                            <td class="fw-bold">${p.nilai_akhir !== null ? p.nilai_akhir : '-'}</td>
                            <td class="text-center">${badgeSesi}</td>
                            <td class="text-center">
                                <button class="btn-action-icon text-primary me-1 position-relative" title="Detail Pengerjaan & Pelanggaran" onclick="participantDetail(${p.id_sesi})"><i class="bx bx-detail"></i>${p.total_pelanggaran>0?`<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px">${p.total_pelanggaran}</span>`:''}</button>
                                ${isMengerjakan ? `
                                    <button class="btn-action-icon text-danger me-1" title="Paksa Selesai" onclick="forceFinish(${p.id_sesi})"><i class="bx bx-stop-circle"></i></button>
                                    <button class="btn-action-icon me-1" title="Reset Sesi" onclick="resetSesi(${p.id_sesi})"><i class="bx bx-reset"></i></button>
                                ` : isSuspended ? `
                                    <button class="btn-action-icon me-1 text-warning" title="Buka Kembali Sesi" onclick="resetSesi(${p.id_sesi})"><i class="bx bx-lock-open-alt"></i></button>
                                ` : `
                                    <a href="../print.php?id_sesi=${p.id_sesi}" target="_blank" class="btn-action-icon me-1" title="Cetak Hasil"><i class="bx bx-printer"></i></a>
                                    <button class="btn-action-icon text-danger" title="Hapus Sesi" onclick="hapusSesi(${p.id_sesi})"><i class="bx bx-trash"></i></button>
                                `}
                            </td>
                        </tr>
                    `;

                    if (idx < 5) {
                        htmlRecent += `
                            <tr>
                                <td>${idx + 1}</td>
                                <td class="font-monospace fw-bold">${p.no_pendaftaran}</td>
                                <td>${p.nama_peserta || '-'}</td>
                                <td><small class="text-muted">${p.nama_ujian}</small></td>
                                <td>${badgeSesi}</td>
                                <td class="fw-bold">${p.nilai_akhir !== null ? p.nilai_akhir : '-'}</td>
                            </tr>
                        `;
                    }
                });

                tbody.innerHTML = html;
                if (tbodyRecent) tbodyRecent.innerHTML = htmlRecent;
                if (!silent) notify('Data monitoring diperbarui');
            });
        }

        function forceFinish(id) {
            Swal.fire({
                title: 'Paksa Selesaikan Ujian?',
                text: 'Sesi ujian peserta akan dihentikan dan skor langsung dihitung.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                confirmButtonText: 'Ya, Selesaikan'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('id_sesi', id);
                    fetch('model/ajax/cbt/monitoring_api.php?action=force_finish', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            notify(data.msg);
                            loadMonitoringList();
                        } else {
                            notify(data.msg, 'error');
                        }
                    });
                }
            });
        }

        async function participantDetail(id) {
            Swal.fire({title:'Memuat detail peserta...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
            try {
                const res=await fetch('model/ajax/cbt/monitoring_api.php?action=participant_detail&id_sesi='+id);const d=await res.json();if(d.status!=='success')throw new Error(d.msg||'Gagal memuat detail.');
                const labels={tab_hidden:'Pindah tab/minimize',window_blur:'Jendela kehilangan fokus',fullscreen_exit:'Keluar fullscreen',copy:'Mencoba copy',paste:'Mencoba paste',context_menu:'Klik kanan',print_screen:'Tombol screenshot',camera_denied:'Kamera ditolak',camera_unavailable:'Kamera tidak tersedia',screen_denied:'Berbagi layar ditolak',screen_share_stopped:'Berbagi layar dihentikan'};
                const activeViolations=d.violations.filter(v=>!v.resolved_at).length;
                const answered=d.answers.filter(a=>a.jawaban_dipilih).length, correct=d.answers.filter(a=>a.is_benar==1).length;
                const violations=d.violations.length?d.violations.map(v=>`<tr><td class="text-nowrap">${opEsc(v.created_at)}</td><td><span class="detail-status danger">${opEsc(labels[v.jenis]||v.jenis)}</span>${v.resolved_at?'<div class="small text-success mt-1">Sudah ditangani</div>':''}</td><td>${opEsc(v.detail||'-')}</td></tr>`).join(''):'<tr><td colspan="3" class="detail-empty"><i class="bx bx-check-shield"></i><span>Tidak ada pelanggaran tercatat</span></td></tr>';
                const answers=d.answers.length?d.answers.map(a=>`<tr><td class="detail-number">${a.urutan}</td><td class="detail-question">${opEsc(a.pertanyaan_preview)}</td><td class="text-center"><span class="answer-chip ${a.jawaban_dipilih?'filled':''}">${opEsc(a.jawaban_dipilih||'—')}</span></td><td>${a.jawaban_dipilih?(a.is_benar==1?'<span class="detail-status success"><i class="bx bx-check"></i> Benar</span>':'<span class="detail-status danger"><i class="bx bx-x"></i> Salah</span>'):'<span class="detail-status muted">Kosong</span>'}</td><td class="text-nowrap small">${opEsc(a.waktu_simpan||'-')}</td></tr>`).join(''):'<tr><td colspan="5" class="detail-empty">Belum ada jawaban</td></tr>';
                const initial=opEsc((d.session.nama_peserta||d.session.no_pendaftaran||'?').charAt(0).toUpperCase());
                const mediaUrl=m=>'model/ajax/cbt/monitoring_api.php?action=proctor_media&id_media='+m.id_media;
                const latestCamera=d.media?.find(m=>m.media_type==='camera');
                const mediaSection=d.media?.length?`<div class="detail-media">${latestCamera?`<div class="detail-live"><div><span class="live-dot"></span><b>Kamera peserta · diperbarui otomatis</b></div><button type="button" class="detail-live-image" onclick="viewProctorImage('${mediaUrl(latestCamera)}','Kamera peserta · ${opEsc(latestCamera.captured_at)}')"><img id="liveCameraImage" src="${mediaUrl(latestCamera)}&t=${Date.now()}" alt="Kamera peserta"><span><i class="bx bx-expand-alt"></i> Perbesar</span></button></div>`:''}<div class="detail-media-title"><i class="bx bx-images"></i> Riwayat Snapshot <span>${d.media.length}</span></div><div class="detail-media-grid">${d.media.map(m=>`<button type="button" onclick="viewProctorImage('${mediaUrl(m)}','${m.media_type==='camera'?'Kamera':'Layar'} · ${opEsc(m.captured_at)}')" class="detail-media-item"><img src="${mediaUrl(m)}" alt="Snapshot ${opEsc(m.media_type)}"><div><b>${m.media_type==='camera'?'Kamera':'Layar'}</b><small>${opEsc(m.captured_at)}</small></div></button>`).join('')}</div></div>`:`<div class="detail-media detail-media-empty"><i class="bx bx-camera-off"></i> Belum ada snapshot kamera atau layar untuk sesi ini.</div>`;
                Swal.fire({width:'min(1180px,96vw)',customClass:{popup:'participant-detail-popup',htmlContainer:'participant-detail-container',confirmButton:'btn-detail-close'},showCloseButton:true,titleText:'',html:`<div class="detail-profile"><div class="detail-avatar">${initial}</div><div class="detail-identity"><h3>${opEsc(d.session.nama_peserta||'Peserta')}</h3><div class="detail-id"><i class="bx bx-id-card"></i>${opEsc(d.session.no_pendaftaran)}</div><div class="detail-exam"><i class="bx bx-book-open"></i>${opEsc(d.session.nama_ujian)}</div></div><div class="detail-session"><span class="detail-status ${d.session.status_sesi==='sedang_mengerjakan'?'warning':d.session.status_sesi==='ditangguhkan'?'danger':'success'}">${opEsc(d.session.status_sesi.replaceAll('_',' '))}</span><small><i class="bx bx-time"></i>${opEsc(d.session.waktu_mulai)}</small><small><i class="bx bx-map"></i>IP ${opEsc(d.session.ip_address||'-')}</small></div></div><div class="detail-metrics"><div><span>${d.answers.length}</span><small>Total Soal</small></div><div><span>${answered}</span><small>Terjawab</small></div><div><span>${correct}</span><small>Benar</small></div><div class="${activeViolations?'is-danger':''}"><span>${activeViolations}/4</span><small>Pelanggaran Aktif</small></div></div><ul class="nav detail-tabs" role="tablist"><li><button class="active" data-bs-toggle="tab" data-bs-target="#detailAnswers"><i class="bx bx-list-check"></i> Detail Jawaban <b>${d.answers.length}</b></button></li><li><button data-bs-toggle="tab" data-bs-target="#detailViolations"><i class="bx bx-shield-x"></i> Pelanggaran <b>${d.violations.length}</b></button></li></ul><div class="tab-content detail-tab-content"><div class="tab-pane fade show active" id="detailAnswers"><div class="table-responsive detail-table-wrap"><table class="detail-table"><thead><tr><th style="width:56px">No</th><th>Soal</th><th style="width:90px">Jawaban</th><th style="width:110px">Status</th><th style="width:155px">Tersimpan</th></tr></thead><tbody>${answers}</tbody></table></div></div><div class="tab-pane fade" id="detailViolations"><div class="table-responsive detail-table-wrap"><table class="detail-table"><thead><tr><th style="width:170px">Waktu</th><th style="width:220px">Jenis Pelanggaran</th><th>Detail</th></tr></thead><tbody>${violations}</tbody></table></div></div></div>`,confirmButtonText:'Tutup Detail'});
                setTimeout(()=>document.querySelector('.participant-detail-container')?.insertAdjacentHTML('beforeend',mediaSection),0);
                const livePoll=setInterval(async()=>{if(!document.querySelector('.participant-detail-popup')){clearInterval(livePoll);return;}try{const r=await fetch('model/ajax/cbt/monitoring_api.php?action=participant_detail&id_sesi='+id);const fresh=await r.json();const camera=fresh.media?.find(m=>m.media_type==='camera');const img=document.getElementById('liveCameraImage');if(camera&&img)img.src=mediaUrl(camera)+'&t='+Date.now();}catch(e){}},10000);
            } catch(e){Swal.fire('Gagal',e.message,'error');}
        }

        function viewProctorImage(url, label) {
            document.getElementById('proctorImageViewer')?.remove();
            const viewer=document.createElement('div');viewer.id='proctorImageViewer';viewer.className='proctor-lightbox';
            viewer.innerHTML=`<div class="proctor-lightbox-card" role="dialog" aria-modal="true" aria-label="Preview snapshot"><div class="proctor-lightbox-head"><span>${opEsc(label||'Snapshot pengawasan')}</span><button type="button" aria-label="Tutup preview" onclick="closeProctorImage()"><i class="bx bx-x"></i></button></div><div class="proctor-lightbox-body"><img src="${opEsc(url)}" alt="${opEsc(label||'Snapshot pengawasan')}"></div></div>`;
            viewer.addEventListener('click',event=>{if(event.target===viewer)closeProctorImage();});document.body.appendChild(viewer);
        }
        function closeProctorImage(){document.getElementById('proctorImageViewer')?.remove();}

        function resetSesi(id) {
            Swal.fire({
                title: 'Reset Sesi Peserta?',
                text: 'Peserta akan diberikan waktu tambahan dan dapat melanjutkan ujian kembali.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                confirmButtonText: 'Ya, Reset'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('id_sesi', id);
                    fetch('model/ajax/cbt/monitoring_api.php?action=reset_sesi', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            notify(data.msg);
                            loadMonitoringList();
                        } else {
                            notify(data.msg, 'error');
                        }
                    });
                }
            });
        }

        function hapusSesi(id) {
            Swal.fire({
                title: 'Hapus Riwayat Sesi?',
                text: 'Seluruh rekam jawaban peserta ini akan dihapus permanen.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Ya, Hapus'
            }).then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('id_sesi', id);
                    fetch('model/ajax/cbt/monitoring_api.php?action=hapus_sesi', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            notify(data.msg);
                            loadMonitoringList();
                        } else {
                            notify(data.msg, 'error');
                        }
                    });
                }
            });
        }

        // 5. REKAP & EXPORT
        function exportRekapExcel() {
            window.open('model/export/export_rekap.php', '_blank');
        }

        function loadAnalisisList() {
            fetch('model/ajax/cbt/soal_crud.php?action=list')
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('tbodyAnalisis');
                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada butir soal.</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach(s => {
                    html += `
                        <tr>
                            <td>${s.id_soal}</td>
                            <td>${s.pertanyaan_preview}</td>
                            <td><span class="badge-soft-primary">${s.nama_kategori}</span></td>
                            <td class="text-center font-monospace fw-bold text-primary">${s.kunci_jawaban}</td>
                            <td class="text-center"><span class="badge-soft-secondary">Sedang</span></td>
                            <td class="text-center"><span class="badge-soft-success">Aktif</span></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
        }

        // 6. TEST LOOKUP AUTH BRIDGE
        function testLookupAuthBridge() {
            const noPeserta = document.getElementById('testNoPeserta').value.trim();
            if (!noPeserta) return;

            fetch('model/ajax/cbt/exam_api.php?action=lookup_peserta&no_peserta=' + encodeURIComponent(noPeserta))
            .then(res => res.json())
            .then(data => {
                const box = document.getElementById('testResultBox');
                const hdr = document.getElementById('testResultHeader');
                const name = document.getElementById('testResultName');
                const dtl = document.getElementById('testResultDetail');

                if (data.status === 'success') {
                    box.style.backgroundColor = '#f0fdf4';
                    box.style.borderColor = '#bbf7d0';
                    hdr.className = 'fw-bold small mb-1 text-success';
                    hdr.innerHTML = '<i class="bx bx-check-circle me-1"></i> Terhubung & Terverifikasi:';
                    name.textContent = data.name;
                    dtl.innerHTML = `Username: <code>${data.username}</code> • Mode: <code>${data.mode}</code>`;
                    box.style.display = 'block';
                } else {
                    box.style.backgroundColor = '#fef2f2';
                    box.style.borderColor = '#fecaca';
                    hdr.className = 'fw-bold small mb-1 text-danger';
                    hdr.innerHTML = '<i class="bx bx-error-circle me-1"></i> Tidak Ditemukan:';
                    name.textContent = data.msg || 'Peserta tidak terdaftar di database target.';
                    dtl.textContent = '';
                    box.style.display = 'block';
                }
            });
        }

        // --- SIDEBAR COLLAPSE & EXPAND LOGIC ---
        const btnToggleSidebar = document.getElementById('btnToggleSidebar');

        function applySidebarState(isCollapsed) {
            if (isCollapsed) {
                document.body.classList.add('sidebar-collapsed');
                if (btnToggleSidebar) btnToggleSidebar.setAttribute('title', 'Lebarkan Menu');
            } else {
                document.body.classList.remove('sidebar-collapsed');
                if (btnToggleSidebar) btnToggleSidebar.setAttribute('title', 'Ciutkan Menu');
            }
        }

        // Restore saved preference
        const savedSidebarState = localStorage.getItem('simpel_cbt_sidebar_collapsed');
        if (savedSidebarState === '1') {
            applySidebarState(true);
        }

        if (btnToggleSidebar) {
            btnToggleSidebar.addEventListener('click', function(e) {
                e.stopPropagation();
                const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('simpel_cbt_sidebar_collapsed', isCollapsed ? '1' : '0');
                applySidebarState(isCollapsed);
            });
        }

        // --- PROFILE DROPDOWN LOGIC ---
        const avatarProfileBtn = document.getElementById('avatarProfileBtn');
        const profileDropdownMenu = document.getElementById('profileDropdownMenu');

        function closeProfileDropdown() {
            if (profileDropdownMenu) {
                profileDropdownMenu.classList.remove('show');
            }
            if (avatarProfileBtn) {
                avatarProfileBtn.setAttribute('aria-expanded', 'false');
            }
        }

        function toggleProfileDropdown(e) {
            if (e) e.stopPropagation();
            if (!profileDropdownMenu) return;
            const isShowing = profileDropdownMenu.classList.toggle('show');
            if (avatarProfileBtn) {
                avatarProfileBtn.setAttribute('aria-expanded', isShowing ? 'true' : 'false');
            }
        }

        if (avatarProfileBtn) {
            avatarProfileBtn.addEventListener('click', toggleProfileDropdown);
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (profileDropdownMenu && profileDropdownMenu.classList.contains('show')) {
                if (!e.target.closest('.profile-dropdown-wrapper')) {
                    closeProfileDropdown();
                }
            }
        });

        // Close dropdown on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileDropdown();
            }
        });

        // ==================== USER MANAGEMENT JAVASCRIPT ====================
        let cachedUsersList = [];

        function loadUsersList() {
            fetch('model/ajax/cbt/user_crud.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    cachedUsersList = data.data;
                    renderUsersTable(data.data);
                    if (data.summary) {
                        document.getElementById('kpiUserTotal').textContent = data.summary.total;
                        document.getElementById('kpiUserAdmin').textContent = data.summary.total_admin;
                        document.getElementById('kpiUserPengawas').textContent = data.summary.total_pengawas;
                    }
                }
            })
            .catch(err => console.error('Error load users:', err));
        }

        function renderUsersTable(users) {
            const tbody = document.getElementById('tbodyUsers');
            if (!tbody) return;

            if (!users || users.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bx bx-info-circle fs-3 d-block mb-1 text-secondary"></i>Belum ada data pengguna.</td></tr>`;
                return;
            }

            let html = '';
            users.forEach((u, idx) => {
                const isCurrent = u.is_current ? '<span class="badge-soft-primary" style="font-size: 0.65rem; padding: 2px 6px; font-weight: 700;">Akun Anda</span>' : '';
                const roleBadge = u.role === 'admin' 
                    ? '<span class="badge-soft-primary"><i class="bx bx-shield-quarter"></i> Administrator Pusat</span>' 
                    : '<span class="badge-soft-success"><i class="bx bx-broadcast"></i> Pengawas Ruang</span>';

                const initial = (u.nama_lengkap || u.username).substring(0, 1).toUpperCase();
                const roleAvatarBg = u.role === 'admin' 
                    ? 'linear-gradient(135deg, #4f46e5 0%, #6366f1 100%)' 
                    : 'linear-gradient(135deg, #059669 0%, #10b981 100%)';

                let formattedDate = '-';
                if (u.created_at) {
                    try {
                        const d = new Date(u.created_at.replace(/-/g, '/'));
                        formattedDate = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    } catch (e) {
                        formattedDate = u.created_at;
                    }
                }

                html += `
                    <tr>
                        <td class="text-center text-muted fw-semibold">${idx + 1}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle sm" style="background: ${roleAvatarBg};">
                                    <span>${initial}</span>
                                </div>
                                <div>
                                    <div class="font-monospace fw-bold text-dark lh-sm">@${u.username}</div>
                                    ${isCurrent ? `<div class="mt-1">${isCurrent}</div>` : ''}
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">${u.nama_lengkap}</div>
                            <small class="text-muted" style="font-size: 0.74rem;">Petugas Sistem CBT</small>
                        </td>
                        <td>${roleBadge}</td>
                        <td class="small text-muted font-monospace">${formattedDate}</td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center align-items-center gap-1">
                                <button type="button" class="btn-action-icon" title="Edit Data Pengguna" onclick="openEditUserModal(${u.id_admin})">
                                    <i class="bx bx-edit"></i>
                                </button>
                                <button type="button" class="btn-action-icon text-warning" title="Ganti Kata Sandi" onclick="openChangePasswordModal(${u.id_admin}, '${u.username}')">
                                    <i class="bx bx-key"></i>
                                </button>
                                ${!u.is_current && u.id_admin !== 1 ? `
                                <button type="button" class="btn-action-icon text-danger" title="Hapus Pengguna" onclick="deleteUser(${u.id_admin}, '${u.nama_lengkap}')">
                                    <i class="bx bx-trash"></i>
                                </button>` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function openAddUserModal() {
            document.getElementById('modalUserFormTitle').textContent = 'Tambah Pengguna Baru';
            document.getElementById('user_id_admin').value = '0';
            document.getElementById('user_username').value = '';
            document.getElementById('user_username').removeAttribute('readonly');
            document.getElementById('user_nama').value = '';
            document.getElementById('user_role').value = 'pengawas';
            document.getElementById('user_password').value = '';
            document.getElementById('user_password').setAttribute('required', 'required');
            document.getElementById('labelUserPassword').innerHTML = 'Kata Sandi <span class="text-danger">*</span>';
            document.getElementById('hintUserPassword').textContent = 'Minimal 5 karakter.';

            const modal = new bootstrap.Modal(document.getElementById('modalUserForm'));
            modal.show();
        }

        function openEditUserModal(idAdmin) {
            const u = cachedUsersList.find(item => item.id_admin == idAdmin);
            if (!u) return;

            document.getElementById('modalUserFormTitle').textContent = 'Edit Data Pengguna';
            document.getElementById('user_id_admin').value = u.id_admin;
            document.getElementById('user_username').value = u.username;
            document.getElementById('user_nama').value = u.nama_lengkap;
            document.getElementById('user_role').value = u.role;
            document.getElementById('user_password').value = '';
            document.getElementById('user_password').removeAttribute('required');
            document.getElementById('labelUserPassword').innerHTML = 'Kata Sandi Baru (Opsional)';
            document.getElementById('hintUserPassword').textContent = 'Kosongkan jika tidak ingin mengubah kata sandi.';

            const modal = new bootstrap.Modal(document.getElementById('modalUserForm'));
            modal.show();
        }

        function saveUserForm(e) {
            e.preventDefault();
            const idAdmin = document.getElementById('user_id_admin').value;
            const username = document.getElementById('user_username').value.trim();
            const nama = document.getElementById('user_nama').value.trim();
            const role = document.getElementById('user_role').value;
            const password = document.getElementById('user_password').value;

            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('id_admin', idAdmin);
            formData.append('username', username);
            formData.append('nama_lengkap', nama);
            formData.append('role', role);
            if (password) formData.append('password', password);

            fetch('model/ajax/cbt/user_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('modalUserForm')).hide();
                    Toastify({
                        text: data.msg,
                        duration: 3000,
                        gravity: "top",
                    close: true,
                        position: "right",
                        style: { background: "#4f46e5" }
                    }).showToast();
                    loadUsersList();
                } else {
                    Swal.fire('Peringatan', data.msg, 'warning');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Gagal menyimpan data pengguna.', 'error');
            });
        }

        function openChangePasswordModal(idAdmin, username) {
            document.getElementById('pwd_id_admin').value = idAdmin;
            document.getElementById('pwd_username_text').textContent = '@' + username;
            document.getElementById('pwd_new').value = '';

            const modal = new bootstrap.Modal(document.getElementById('modalChangePassword'));
            modal.show();
        }

        function submitChangePassword(e) {
            e.preventDefault();
            const idAdmin = document.getElementById('pwd_id_admin').value;
            const newPassword = document.getElementById('pwd_new').value.trim();

            if (newPassword.length < 5) {
                Swal.fire('Peringatan', 'Kata sandi minimal 5 karakter!', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('id_admin', idAdmin);
            formData.append('new_password', newPassword);

            fetch('model/ajax/cbt/user_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('modalChangePassword')).hide();
                    Toastify({
                        text: data.msg,
                        duration: 3000,
                        gravity: "top",
                    close: true,
                        position: "right",
                        style: { background: "#4f46e5" }
                    }).showToast();
                } else {
                    Swal.fire('Gagal', data.msg, 'error');
                }
            });
        }

        function deleteUser(idAdmin, nama) {
            Swal.fire({
                title: 'Hapus Pengguna Ini?',
                html: `Apakah Anda yakin ingin menghapus akun <strong>${nama}</strong> dari sistem?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id_admin', idAdmin);

                    fetch('model/ajax/cbt/user_crud.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Toastify({
                                text: data.msg,
                                duration: 3000,
                                gravity: "top",
                    close: true,
                                position: "right",
                                style: { background: "#4f46e5" }
                            }).showToast();
                            loadUsersList();
                        } else {
                            Swal.fire('Gagal', data.msg, 'error');
                        }
                    });
                }
            });
        }

        // ==================== IMPORT / EXPORT SOAL JAVASCRIPT ====================
        function triggerExportSoal(format) {
            const katId = document.getElementById('filterSoalKategori').value || 0;
            const url = `model/export/soal_import_export.php?action=export&format=${encodeURIComponent(format)}&id_kategori=${katId}`;
            window.location.href = url;
            Toastify({
                text: "Memulai proses ekspor berkas soal (" + format.toUpperCase() + ")...",
                duration: 3000,
                gravity: "top",
                    close: true,
                position: "right",
                style: { background: "#4f46e5" }
            }).showToast();
        }

        function openImportModal() {
            const modal = new bootstrap.Modal(document.getElementById('modalImportSoal'));
            modal.show();
        }

        function loadAikenSample() {
            const sample = `Lambang sila pertama Pancasila adalah...
A. Rantai Emas
B. Bintang Emas
C. Pohon Beringin
D. Kepala Banteng
E. Padi dan Kapas
ANSWER: B

Perangkat keras komputer yang bertindak sebagai otak pemrosesan utama adalah...
A. Motherboard
B. Harddisk Drive
C. Central Processing Unit (CPU)
D. Power Supply
E. Random Access Memory (RAM)
ANSWER: C

Bahasa pemrograman yang biasa digunakan untuk membangun struktur dasar halaman web adalah...
A. Python
B. HTML
C. C++
D. Ruby
E. Swift
ANSWER: B`;
            document.getElementById('aikenTextContent').value = sample;
            previewAikenCount();
        }

        function previewAikenCount() {
            const text = document.getElementById('aikenTextContent').value;
            const matches = text.match(/^ANSWER:\s*[A-Ea-e]/gmi);
            const count = matches ? matches.length : 0;
            const badge = document.getElementById('aikenDetectedBadge');
            if (count > 0) {
                badge.innerHTML = `<i class="bx bx-check-circle text-success me-1"></i> <strong class="text-success">${count} butir soal</strong> terdeteksi siap diimpor!`;
            } else {
                badge.innerHTML = `<i class="bx bx-search-alt me-1"></i> Belum ada soal yang terdeteksi`;
            }
        }

        function submitImportText(e) {
            e.preventDefault();
            const text = document.getElementById('aikenTextContent').value.trim();
            const katId = document.getElementById('importTextKategori').value;
            const bobot = document.getElementById('importTextBobot').value;

            if (!text) {
                Swal.fire('Peringatan', 'Silakan masukkan teks soal format Aiken!', 'warning');
                return;
            }

            const btn = document.getElementById('btnSubmitImportText');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengimpor...';

            const formData = new FormData();
            formData.append('action', 'import_text');
            formData.append('raw_text', text);
            formData.append('id_kategori', katId);
            formData.append('bobot_nilai', bobot);

            fetch('model/export/soal_import_export.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-check"></i> Proses Impor ke Bank Soal';
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('modalImportSoal')).hide();
                    document.getElementById('aikenTextContent').value = '';
                    Swal.fire('Berhasil!', data.msg, 'success');
                    loadSoalList();
                } else {
                    Swal.fire('Gagal Impor', data.msg, 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-check"></i> Proses Impor ke Bank Soal';
                console.error(err);
                Swal.fire('Error', 'Terjadi kesalahan saat memproses impor.', 'error');
            });
        }

        function submitImportFile(e) {
            e.preventDefault();
            const fileInput = document.getElementById('fileSoalUpload');
            const katId = document.getElementById('importFileKategori').value;
            const bobot = document.getElementById('importFileBobot').value;

            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire('Peringatan', 'Silakan pilih berkas file yang ingin diimpor!', 'warning');
                return;
            }

            const btn = document.getElementById('btnSubmitImportFile');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengunggah...';

            const formData = new FormData();
            formData.append('action', 'import_file');
            formData.append('file_soal', fileInput.files[0]);
            formData.append('id_kategori', katId);
            formData.append('bobot_nilai', bobot);

            fetch('model/export/soal_import_export.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-cloud-upload"></i> Unggah & Impor Berkas';
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('modalImportSoal')).hide();
                    fileInput.value = '';
                    Swal.fire('Berhasil!', data.msg, 'success');
                    loadSoalList();
                } else {
                    Swal.fire('Gagal Impor', data.msg, 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-cloud-upload"></i> Unggah & Impor Berkas';
                console.error(err);
                Swal.fire('Error', 'Terjadi kesalahan saat mengunggah berkas.', 'error');
            });
        }
    </script>
</body>
</html>
