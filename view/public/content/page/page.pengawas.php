<?php
/**
 * simpel_cbt - Console Pengawas & Proctor Ruangan (Modern Minimalist Hybrid)
 */
require_once __DIR__ . '/../../../../model/config/config.conn.php';
require_once __DIR__ . '/../../../../model/helper/cbt.helper.php';
require_once __DIR__ . '/../../../../model/helper/auth.helper.php';

// Pastikan sesi pengawas valid
check_pengawas_login();
$pengawasUser = get_logged_admin();

// Ambil list jadwal untuk filter
$listJadwal = [];
$rJ = mysqli_query($conn, "SELECT * FROM cbt_jadwal ORDER BY id_jadwal DESC");
if ($rJ) {
    while ($dJ = mysqli_fetch_assoc($rJ)) {
        $listJadwal[] = $dJ;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Console Pengawas Ruangan - SIMPEL CBT</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet" />

    <!-- Icons & Bootstrap 5 -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- SweetAlert2 & Toastify -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        :root {
            --primary: #059669;
            --primary-hover: #047857;
            --primary-subtle: #ecfdf5;
            --bg-canvas: #f8fafc;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --border-subtle: #e2e8f0;
            --card-radius: 14px;
            --header-height: 68px;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            min-height: 100vh;
            margin: 0;
        }

        /* Topbar Header (Aliged with design system) */
        .topbar-proctor {
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

        .brand-proctor {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }

        .brand-title {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.4px;
            color: var(--text-heading);
            margin: 0;
            line-height: 1.2;
        }

        .brand-title span {
            color: var(--primary);
        }

        .role-tag {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-subtle);
            padding: 2px 7px;
            border-radius: 6px;
            display: inline-block;
        }

        /* Nav Pills Modern */
        .nav-proctor {
            display: flex;
            gap: 6px;
            background-color: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
        }

        .nav-proctor-btn {
            border: none;
            background: transparent;
            padding: 7px 16px;
            border-radius: 7px;
            font-size: 0.86rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }

        .nav-proctor-btn.active {
            background-color: #ffffff;
            color: var(--text-heading);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }

        .nav-proctor-btn:hover:not(.active) {
            color: var(--text-heading);
        }

        /* Profile Dropdown */
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
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            position: relative;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
            flex-shrink: 0;
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
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
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

        .dropdown-action-item:hover {
            background-color: var(--bg-canvas);
            color: var(--text-heading);
        }

        .dropdown-action-item.logout-action:hover {
            background-color: #fef2f2;
        }

        /* Container Content */
        .container-proctor {
            max-width: 1380px;
            margin: 0 auto;
            padding: 28px 24px;
        }

        /* Metric Cards */
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
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.5px;
            line-height: 1.2;
            margin-bottom: 4px;
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

        /* Modern Table Card */
        .card-modern {
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--card-radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-modern-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #ffffff;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-modern-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-modern {
            width: 100%;
            margin-bottom: 0;
        }

        .table-modern th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 12px 18px;
            border-bottom: 1px solid var(--border-subtle);
        }

        .table-modern td {
            padding: 13px 18px;
            vertical-align: middle;
            font-size: 0.87rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-modern tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Badges */
        .badge-soft-success {
            background-color: #ecfdf5;
            color: #047857;
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.76rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-soft-primary {
            background-color: #eef2ff;
            color: #4f46e5;
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.76rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-soft-warning {
            background-color: #fffbeb;
            color: #b45309;
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.76rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-soft-danger {
            background-color: #fef2f2;
            color: #dc2626;
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.76rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-timer-pill {
            font-family: 'JetBrains Mono', monospace;
            background-color: #f8fafc;
            border: 1px solid var(--border-subtle);
            color: #0f172a;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .btn-action-proctor {
            padding: 5px 10px;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-reset-session {
            background-color: #fffbeb;
            color: #b45309;
            border-color: #fde68a;
        }

        .btn-reset-session:hover {
            background-color: #fef3c7;
            color: #92400e;
        }

        .btn-finish-session {
            background-color: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .btn-finish-session:hover {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .tab-pane-view {
            display: none;
        }
        .tab-pane-view.active {
            display: block;
        }
    </style>
</head>

<body>

    <!-- Header Topbar -->
    <header class="topbar-proctor">
        <div class="brand-proctor">
            <div class="brand-icon">
                <i class="bx bx-broadcast"></i>
            </div>
            <div>
                <h1 class="brand-title">SIMPEL <span>CBT</span></h1>
                <span class="role-tag"><i class="bx bx-shield-quarter"></i> Console Pengawas Ruangan</span>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="nav-proctor d-none d-md-flex">
            <button type="button" class="nav-proctor-btn active" id="btnTabMonitoring" onclick="switchProctorTab('monitoring')">
                <i class="bx bx-broadcast"></i> Live Monitoring Sesi
            </button>
            <button type="button" class="nav-proctor-btn" id="btnTabRekap" onclick="switchProctorTab('rekap')">
                <i class="bx bx-bar-chart-alt-2"></i> Rekap Nilai Ruangan
            </button>
        </div>

        <!-- Right Side -->
        <div class="d-flex align-items-center gap-3">
            <span class="badge-soft-success small d-none d-lg-inline-flex align-items-center gap-1">
                <i class="bx bx-check-circle"></i> Sistem Live
            </span>

            <!-- Profile Dropdown -->
            <div class="profile-dropdown-wrapper">
                <button type="button" class="avatar-profile-btn" id="avatarProfileBtn" aria-expanded="false" title="Menu Pengawas">
                    <div class="avatar-circle">
                        <span><?= strtoupper(substr($pengawasUser['nama_lengkap'] ?: 'P', 0, 1)) ?></span>
                        <span class="avatar-online-dot"></span>
                    </div>
                    <div class="avatar-info d-none d-md-flex flex-column text-start">
                        <span class="avatar-name"><?= htmlspecialchars($pengawasUser['nama_lengkap']) ?></span>
                        <span class="avatar-role">Pengawas Ujian</span>
                    </div>
                    <i class="bx bx-chevron-down avatar-arrow" id="avatarArrow"></i>
                </button>

                <!-- Dropdown Menu Card -->
                <div class="profile-dropdown-menu" id="profileDropdownMenu">
                    <div class="dropdown-header-box">
                        <div class="avatar-circle lg">
                            <span><?= strtoupper(substr($pengawasUser['nama_lengkap'] ?: 'P', 0, 1)) ?></span>
                        </div>
                        <div class="dropdown-meta">
                            <div class="dropdown-name"><?= htmlspecialchars($pengawasUser['nama_lengkap']) ?></div>
                            <div class="dropdown-badge">@<?= htmlspecialchars($pengawasUser['username']) ?> · Pengawas</div>
                        </div>
                    </div>
                    <div class="dropdown-item-divider"></div>
                    <?php if ($pengawasUser['role'] === 'admin'): ?>
                        <a href="../pusat/index.php" class="dropdown-action-item">
                            <i class="bx bx-building-house text-primary"></i>
                            <span>Buka Portal Pusat</span>
                        </a>
                        <div class="dropdown-item-divider"></div>
                    <?php endif; ?>
                    <a href="index.php?m=logout-pengawas" class="dropdown-action-item logout-action">
                        <i class="bx bx-log-out text-danger"></i>
                        <span class="text-danger fw-semibold">Keluar dari Pengawas</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Body Container -->
    <main class="container-proctor">

        <!-- Metrics KPI -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="metric-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="metric-label">Sedang Mengerjakan</div>
                        <div class="metric-number text-primary" id="kpiActiveCount">0</div>
                        <small class="text-muted"><i class="bx bx-pulse text-success"></i> Realtime di Ruangan</small>
                    </div>
                    <div class="metric-icon-wrap" style="background: #eef2ff; color: #4f46e5;">
                        <i class="bx bx-user-voice"></i>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="metric-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="metric-label">Selesai Ujian</div>
                        <div class="metric-number text-success" id="kpiFinishedCount">0</div>
                        <small class="text-muted">Lembar Dikumpulkan</small>
                    </div>
                    <div class="metric-icon-wrap" style="background: #ecfdf5; color: #059669;">
                        <i class="bx bx-check-double"></i>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="metric-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="metric-label">Total Peserta Masuk</div>
                        <div class="metric-number" id="kpiTotalCount">0</div>
                        <small class="text-muted">Sesi Terdaftar</small>
                    </div>
                    <div class="metric-icon-wrap" style="background: #f1f5f9; color: #475569;">
                        <i class="bx bx-group"></i>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="metric-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="metric-label">Perlu Bantuan / Reset</div>
                        <div class="metric-number text-warning" id="kpiHelpCount">0</div>
                        <small class="text-muted">Kendala Teknis / PC Hang</small>
                    </div>
                    <div class="metric-icon-wrap" style="background: #fffbeb; color: #b45309;">
                        <i class="bx bx-support"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB VIEW 1: LIVE MONITORING -->
        <div id="view-monitoring" class="tab-pane-view active">
            
            <div class="card-modern">
                <div class="card-modern-header">
                    <div class="card-modern-title">
                        <i class="bx bx-broadcast text-success fs-5"></i>
                        <span>Pemantauan Langsung Peserta Ujian Ruangan</span>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Filter Jadwal -->
                        <select class="form-select form-select-sm" id="selectJadwalMonitoring" style="width: 240px;" onchange="loadLiveMonitoring()">
                            <option value="0">-- Semua Jadwal Aktif --</option>
                            <?php foreach ($listJadwal as $j): ?>
                                <option value="<?= $j['id_jadwal'] ?>" <?= $j['status'] == 1 ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($j['nama_ujian']) ?> (<?= htmlspecialchars($j['token']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Refresh indicator -->
                        <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" onclick="loadLiveMonitoring()">
                            <i class="bx bx-refresh" id="refreshIcon"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>No. Peserta</th>
                                <th>Nama Peserta</th>
                                <th>Paket Ujian</th>
                                <th>Waktu Mulai</th>
                                <th>Sisa Waktu</th>
                                <th>Progres Soal</th>
                                <th>Status</th>
                                <th>IP Address</th>
                                <th class="text-center" style="width: 170px;">Aksi Pengawas</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyLiveMonitoring">
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                                    Memuat data monitoring peserta...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small text-muted">
                        <i class="bx bx-info-circle me-1"></i> Data diperbarui otomatis setiap <strong>5 detik</strong>.
                    </div>
                    <div class="small text-muted" id="lastUpdatedTime">
                        Terakhir diperbarui: -
                    </div>
                </div>
            </div>

        </div>

        <!-- TAB VIEW 2: REKAP NILAI RUANGAN -->
        <div id="view-rekap" class="tab-pane-view">
            <div class="card-modern">
                <div class="card-modern-header">
                    <div class="card-modern-title">
                        <i class="bx bx-bar-chart-alt-2 text-primary fs-5"></i>
                        <span>Rekapitulasi Nilai Ujian Ruangan</span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" onclick="loadRekapData()">
                            <i class="bx bx-refresh"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>No. Peserta</th>
                                <th>Nama Peserta</th>
                                <th>Paket Ujian</th>
                                <th>Jawaban Benar</th>
                                <th>Jawaban Salah</th>
                                <th>Skor Nilai</th>
                                <th>Status Akhir</th>
                                <th class="text-center" style="width: 120px;">Cetak Hasil</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyRekap">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Memuat data rekap nilai...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <script>
        // --- NAVIGATION TABS ---
        function switchProctorTab(tabName) {
            document.querySelectorAll('.tab-pane-view').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-proctor-btn').forEach(el => el.classList.remove('active'));

            const targetView = document.getElementById('view-' + tabName);
            if (targetView) targetView.classList.add('active');

            if (tabName === 'monitoring') {
                document.getElementById('btnTabMonitoring').classList.add('active');
                loadLiveMonitoring();
            } else if (tabName === 'rekap') {
                document.getElementById('btnTabRekap').classList.add('active');
                loadRekapData();
            }
        }

        // --- PROFILE DROPDOWN LOGIC ---
        const avatarProfileBtn = document.getElementById('avatarProfileBtn');
        const profileDropdownMenu = document.getElementById('profileDropdownMenu');

        function closeProfileDropdown() {
            if (profileDropdownMenu) profileDropdownMenu.classList.remove('show');
            if (avatarProfileBtn) avatarProfileBtn.setAttribute('aria-expanded', 'false');
        }

        function toggleProfileDropdown(e) {
            if (e) e.stopPropagation();
            if (!profileDropdownMenu) return;
            const isShowing = profileDropdownMenu.classList.toggle('show');
            if (avatarProfileBtn) avatarProfileBtn.setAttribute('aria-expanded', isShowing ? 'true' : 'false');
        }

        if (avatarProfileBtn) avatarProfileBtn.addEventListener('click', toggleProfileDropdown);

        document.addEventListener('click', function(e) {
            if (profileDropdownMenu && profileDropdownMenu.classList.contains('show')) {
                if (!e.target.closest('.profile-dropdown-wrapper')) closeProfileDropdown();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeProfileDropdown();
        });

        // --- LIVE MONITORING ENGINE ---
        let pollTimer = null;

        function loadLiveMonitoring() {
            const jadwalId = document.getElementById('selectJadwalMonitoring').value;
            const icon = document.getElementById('refreshIcon');
            if (icon) icon.classList.add('bx-spin');

            fetch('model/ajax/cbt/monitoring_api.php?action=get_live&id_jadwal=' + jadwalId)
            .then(res => res.json())
            .then(data => {
                if (icon) icon.classList.remove('bx-spin');
                if (data.status === 'success') {
                    renderMonitoringTable(data.data);
                    updateKpis(data.data);
                    document.getElementById('lastUpdatedTime').textContent = 'Terakhir diperbarui: ' + new Date().toLocaleTimeString('id-ID');
                }
            })
            .catch(err => {
                if (icon) icon.classList.remove('bx-spin');
                console.error('Monitoring error:', err);
            });
        }

        function renderMonitoringTable(sessions) {
            const tbody = document.getElementById('tbodyLiveMonitoring');
            if (!tbody) return;

            if (!sessions || sessions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bx bx-info-circle fs-3 text-secondary mb-2 d-block"></i>
                            Tidak ada peserta yang sedang aktif atau terdaftar pada sesi ujian ini.
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            sessions.forEach((s, idx) => {
                let statusBadge = '';
                if (s.status_ujian === 'sedang') {
                    statusBadge = '<span class="badge-soft-primary"><i class="bx bx-pulse"></i> Mengerjakan</span>';
                } else if (s.status_ujian === 'selesai') {
                    statusBadge = '<span class="badge-soft-success"><i class="bx bx-check-circle"></i> Selesai</span>';
                } else {
                    statusBadge = '<span class="badge-soft-warning"><i class="bx bx-time"></i> ' + s.status_ujian + '</span>';
                }

                const sisaMenit = Math.max(0, Math.floor(s.sisa_detik / 60));
                const sisaDetik = Math.max(0, s.sisa_detik % 60);
                const timerStr = String(sisaMenit).padStart(2, '0') + ':' + String(sisaDetik).padStart(2, '0');

                // Aksi Pengawas
                let actionBtn = '';
                if (s.status_ujian === 'sedang') {
                    actionBtn = `
                        <div class="d-flex gap-1 justify-content-center">
                            <button type="button" class="btn-action-proctor btn-reset-session" title="Buka Kunci / Izinkan Login Kembali (PC Hang)" onclick="resetSesiPeserta(${s.id_sesi}, '${s.nama_peserta}')">
                                <i class="bx bx-reset"></i> Buka Kunci
                            </button>
                            <button type="button" class="btn-action-proctor btn-finish-session" title="Selesaikan Ujian Peserta Secara Paksa" onclick="selesaikanSesiPaksa(${s.id_sesi}, '${s.nama_peserta}')">
                                <i class="bx bx-check"></i> Selesai
                            </button>
                        </div>
                    `;
                } else {
                    actionBtn = `<span class="small text-muted">- Selesai -</span>`;
                }

                html += `
                    <tr>
                        <td>${idx + 1}</td>
                        <td class="font-monospace fw-bold text-dark">${s.no_peserta}</td>
                        <td class="fw-semibold text-dark">${s.nama_peserta}</td>
                        <td><span class="small text-muted">${s.nama_ujian}</span></td>
                        <td class="small text-secondary font-monospace">${s.waktu_mulai ? s.waktu_mulai.substring(11, 16) : '-'}</td>
                        <td>
                            <span class="badge-timer-pill">${timerStr}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                    <div class="progress-bar bg-primary" style="width: ${s.persentase_jawaban}%;"></div>
                                </div>
                                <span class="small font-monospace">${s.jumlah_terjawab}/${s.total_soal}</span>
                            </div>
                        </td>
                        <td>${statusBadge}</td>
                        <td class="small font-monospace text-muted">${s.ip_address || '127.0.0.1'}</td>
                        <td class="text-center">${actionBtn}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function updateKpis(sessions) {
            let active = 0, finished = 0, help = 0;
            if (sessions) {
                sessions.forEach(s => {
                    if (s.status_ujian === 'sedang') {
                        active++;
                        if (s.sisa_detik < 300) help++; // sisa waktu kurang 5 menit
                    } else if (s.status_ujian === 'selesai') {
                        finished++;
                    }
                });
            }
            document.getElementById('kpiActiveCount').textContent = active;
            document.getElementById('kpiFinishedCount').textContent = finished;
            document.getElementById('kpiTotalCount').textContent = sessions ? sessions.length : 0;
            document.getElementById('kpiHelpCount').textContent = help;
        }

        // --- RESET SESI (BUKA KUNCI PESERTA TERKENDALA) ---
        function resetSesiPeserta(idSesi, namaPeserta) {
            Swal.fire({
                title: 'Buka Kunci Sesi Peserta?',
                html: `Apakah Anda ingin membuka kunci sesi ujian <strong>${namaPeserta}</strong>?<br><br><small class="text-muted">Gunakan fitur ini jika komputer peserta mati lampu, restart, atau browser tertutup. Jawaban yang sudah diisi <strong>TETAP AMAN TERSIMPAN</strong> dan peserta dapat login ulang.</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="bx bx-check"></i> Ya, Buka Kunci',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('model/ajax/cbt/monitoring_api.php?action=reset_session&id_sesi=' + idSesi, { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Toastify({
                                text: "Kunci sesi peserta " + namaPeserta + " berhasil dibuka. Peserta dapat login kembali.",
                                duration: 4000,
                                gravity: "top",
                                position: "right",
                                style: { background: "#059669" }
                            }).showToast();
                            loadLiveMonitoring();
                        } else {
                            Swal.fire('Info', data.msg, 'info');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', 'Gagal membuka kunci sesi.', 'error');
                    });
                }
            });
        }

        // --- SELESAIKAN SESI PAKSA ---
        function selesaikanSesiPaksa(idSesi, namaPeserta) {
            Swal.fire({
                title: 'Selesaikan Ujian Paksa?',
                html: `Apakah Anda yakin ingin menyelesaikan ujian untuk <strong>${namaPeserta}</strong> secara paksa? Sesi ujian akan ditutup dan nilai dihitung otomatis.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Selesaikan Ujian',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('model/ajax/cbt/monitoring_api.php?action=force_finish&id_sesi=' + idSesi, { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Toastify({
                                text: "Sesi ujian " + namaPeserta + " berhasil diselesaikan.",
                                duration: 3000,
                                gravity: "top",
                                position: "right",
                                style: { background: "#059669" }
                            }).showToast();
                            loadLiveMonitoring();
                        } else {
                            Swal.fire('Gagal', data.msg, 'error');
                        }
                    });
                }
            });
        }

        // --- REKAP DATA & CETAK ---
        function loadRekapData() {
            fetch('model/ajax/cbt/monitoring_api.php?action=get_live&id_jadwal=0')
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('tbodyRekap');
                if (!tbody) return;

                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted">Belum ada peserta yang mengikuti ujian.</td></tr>`;
                    return;
                }

                let html = '';
                data.data.forEach((s, idx) => {
                    html += `
                        <tr>
                            <td>${idx + 1}</td>
                            <td class="font-monospace fw-bold">${s.no_peserta}</td>
                            <td class="fw-semibold text-dark">${s.nama_peserta}</td>
                            <td><span class="small text-muted">${s.nama_ujian}</span></td>
                            <td class="text-center text-success font-monospace fw-bold">${s.jumlah_benar || 0}</td>
                            <td class="text-center text-danger font-monospace">${s.jumlah_salah || 0}</td>
                            <td class="text-center font-monospace fw-bold fs-6 text-primary">${s.nilai_total || 0}</td>
                            <td>
                                ${s.status_ujian === 'selesai' ? '<span class="badge-soft-success">Selesai</span>' : '<span class="badge-soft-primary">Mengerjakan</span>'}
                            </td>
                            <td class="text-center">
                                <a href="../print.php?id_sesi=${s.id_sesi}" target="_blank" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                                    <i class="bx bx-printer"></i> Cetak
                                </a>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
        }

        // Inisialisasi awal & polling setiap 5 detik
        document.addEventListener('DOMContentLoaded', function() {
            loadLiveMonitoring();
            pollTimer = setInterval(loadLiveMonitoring, 5000);
        });
    </script>
</body>
</html>