<?php
/**
 * simpel_cbt - Portal Ujian Peserta (Modern Minimalist Hybrid)
 */
require_once __DIR__ . '/../../../model/config/config.conn.php';
require_once __DIR__ . '/../../../model/helper/cbt.helper.php';
require_once __DIR__ . '/../../../model/helper/auth_bridge.helper.php';

// Ambil paket jadwal ujian yang aktif
$now = date('Y-m-d H:i:s');
$qJadwal = mysqli_query($conn, "SELECT * FROM cbt_jadwal WHERE status_ujian = 'aktif' AND tgl_selesai >= '$now' ORDER BY id_jadwal DESC");
$jadwalList = [];
if ($qJadwal) {
    while ($r = mysqli_fetch_assoc($qJadwal)) {
        $jadwalList[] = $r;
    }
}

// Cek konfigurasi auth bridge
$authConfig = get_auth_bridge_config();
$needPass = ($authConfig['require_password'] ?? false);
$authMode = ($authConfig['mode'] ?? 1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Portal Ujian Peserta - SIMPEL CBT</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet" />

    <!-- Icons & Bootstrap 5 -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-subtle: #eef2ff;
            --bg-canvas: #f8fafc;
            --text-heading: #0f172a;
            --text-body: #475569;
            --text-muted: #94a3b8;
            --border-subtle: #e2e8f0;
            --card-radius: 16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            margin: 0;
        }

        .login-card {
            width: 100%;
            max-width: 410px;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--card-radius);
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            padding: 36px 32px 30px;
            transition: all 0.25s ease;
        }

        /* Bespoke Minimalist Brand Emblem */
        .brand-logo-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .brand-title {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text-heading);
            margin: 0;
            line-height: 1.2;
        }

        .brand-title span {
            color: var(--primary);
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-heading);
            margin-bottom: 6px;
        }

        .input-group-modern {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-modern .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 1.15rem;
            pointer-events: none;
            transition: color 0.2s ease;
            z-index: 4;
        }

        .form-control-modern {
            width: 100%;
            height: 46px;
            background-color: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 0 14px 0 42px;
            font-size: 0.92rem;
            color: var(--text-heading);
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control-modern::placeholder {
            color: #cbd5e1;
        }

        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        .form-control-modern:focus + .input-icon,
        .input-group-modern:focus-within .input-icon {
            color: var(--primary);
        }

        /* Token Input */
        .token-modern {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 4px;
            text-align: center;
            text-transform: uppercase;
            color: var(--primary);
            background-color: var(--primary-subtle);
            border: 1.5px dashed rgba(79, 70, 229, 0.35);
            padding: 0 14px 0 42px;
        }

        .token-modern:focus {
            border-style: solid;
            border-color: var(--primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        /* Candidate Lookup Cards */
        .verified-badge {
            display: none;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.82rem;
            margin-top: 8px;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.2s ease;
        }

        .unverified-badge {
            display: none;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.82rem;
            margin-top: 8px;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-3px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Button */
        .btn-modern {
            width: 100%;
            height: 48px;
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
            transition: all 0.2s ease;
            margin-top: 6px;
        }

        .btn-modern:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
            color: #ffffff;
        }

        .btn-modern:active {
            transform: scale(0.99);
        }

        .btn-modern:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .btn-eye-toggle {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            font-size: 1.15rem;
        }

        .btn-eye-toggle:hover {
            color: var(--text-heading);
        }

        .footer-note {
            text-align: center;
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px solid var(--border-subtle);
        }

        .footer-note a {
            color: var(--text-muted);
            font-size: 0.82rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s ease;
        }

        .footer-note a:hover {
            color: var(--primary);
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
</head>

<body>
    <div class="login-card">
        
        <!-- Header -->
        <div class="brand-logo-wrap">
            <div class="brand-icon">
                <i class="bx bx-select-multiple"></i>
            </div>
            <div>
                <h1 class="brand-title">SIMPEL <span>CBT</span></h1>
                <span class="small text-muted">Portal Ujian Berbasis Komputer</span>
            </div>
        </div>

        <form id="formStartExam" autocomplete="off">
            
            <!-- NOMOR PESERTA / USER ID -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="no_peserta">Nomor Peserta / NIK</label>
                    <span class="text-muted" style="font-size: 0.72rem;">Verifikasi Otomatis</span>
                </div>
                <div class="input-group-modern">
                    <input type="text" class="form-control-modern" id="no_peserta" name="no_peserta" placeholder="Masukkan nomor peserta..." required autofocus />
                    <i class="bx bx-user input-icon"></i>
                </div>
                <!-- Status Lookup -->
                <div class="verified-badge" id="boxVerified">
                    <i class="bx bx-check-circle fs-5"></i>
                    <div>Terdaftar: <strong id="txtVerifiedName">-</strong></div>
                </div>
                <div class="unverified-badge" id="boxUnverified">
                    <i class="bx bx-info-circle fs-5"></i>
                    <div id="txtUnverifiedMsg">Nomor Peserta tidak ditemukan.</div>
                </div>
                <input type="hidden" id="nama_peserta" name="nama_peserta" />
            </div>

            <!-- PASSWORD (JIKA BUTUH) -->
            <?php if ($needPass): ?>
            <div class="mb-3">
                <label class="form-label" for="auth_password">Password Akun</label>
                <div class="input-group-modern">
                    <input type="password" class="form-control-modern" id="auth_password" name="auth_password" placeholder="Password akun Anda" required style="padding-right: 42px;" />
                    <i class="bx bx-lock-alt input-icon"></i>
                    <button type="button" class="btn-eye-toggle" id="btnTogglePwd" title="Lihat password">
                        <i class="bx bx-hide" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- PAKET UJIAN -->
            <div class="mb-3">
                <label class="form-label" for="id_jadwal">Pilih Paket Ujian</label>
                <div class="input-group-modern">
                    <select class="form-control-modern" id="id_jadwal" name="id_jadwal" required style="cursor: pointer;">
                        <?php if (count($jadwalList) === 0): ?>
                            <option value="">-- Belum ada ujian aktif --</option>
                        <?php else: ?>
                            <?php if (count($jadwalList) > 1): ?>
                                <option value="">-- Pilih Paket Ujian --</option>
                            <?php endif; ?>
                            <?php foreach ($jadwalList as $j): ?>
                                <option value="<?= $j['id_jadwal'] ?>" <?= (count($jadwalList) === 1) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($j['nama_ujian']) ?> (<?= $j['durasi_menit'] ?> Menit)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <i class="bx bx-calendar-check input-icon"></i>
                </div>
            </div>

            <!-- TOKEN UJIAN -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="token_ujian">Token Ujian</label>
                    <span class="text-muted" style="font-size: 0.72rem;">Dari Pengawas Ruang</span>
                </div>
                <div class="input-group-modern">
                    <input type="text" class="form-control-modern token-modern" id="token_ujian" name="token_ujian" placeholder="TOKEN" maxlength="15" required />
                    <i class="bx bx-key input-icon"></i>
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <button type="submit" class="btn-modern" id="btnSubmit">
                <i class="bx bx-right-arrow-circle fs-5"></i>
                <span>Mulai Ujian</span>
            </button>

            <!-- FOOTER -->
            <div class="footer-note">
                <a href="index.php?m=login-pusat">
                    <i class="bx bx-shield-quarter"></i> Portal Pengawas & Pusat CBT
                </a>
            </div>

        </form>

    </div>

    <script>
        // Toggle Password
        const btnTogglePwd = document.getElementById('btnTogglePwd');
        if (btnTogglePwd) {
            btnTogglePwd.addEventListener('click', function() {
                const input = document.getElementById('auth_password');
                const icon = document.getElementById('eyeIcon');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'bx bx-show';
                } else {
                    input.type = 'password';
                    icon.className = 'bx bx-hide';
                }
            });
        }

        // Realtime Candidate Lookup
        let lookupTimer = null;
        document.getElementById('no_peserta').addEventListener('input', function() {
            clearTimeout(lookupTimer);
            const val = this.value.trim();
            const boxV = document.getElementById('boxVerified');
            const boxU = document.getElementById('boxUnverified');

            if (val.length < 3) {
                boxV.style.display = 'none';
                boxU.style.display = 'none';
                document.getElementById('nama_peserta').value = '';
                return;
            }

            lookupTimer = setTimeout(() => {
                fetch('api/exam_api.php?action=lookup_peserta&no_peserta=' + encodeURIComponent(val))
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        boxV.style.display = 'flex';
                        boxU.style.display = 'none';
                        document.getElementById('txtVerifiedName').textContent = data.name;
                        document.getElementById('nama_peserta').value = data.name;
                    } else {
                        boxV.style.display = 'none';
                        boxU.style.display = 'flex';
                        document.getElementById('txtUnverifiedMsg').textContent = data.msg || 'Nomor peserta tidak terdaftar.';
                        document.getElementById('nama_peserta').value = '';
                    }
                })
                .catch(() => {});
            }, 350);
        });

        // Submit Form
        document.getElementById('formStartExam').addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('btnSubmit');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memverifikasi...';

            const formData = new FormData(this);

            fetch('api/exam_api.php?action=start', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalContent;

                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sesi Ujian Siap!',
                        text: data.msg,
                        timer: 1300,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'exam.php?id_sesi=' + data.id_sesi;
                    });
                } else if (data.status === 'already_finished') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Ujian Selesai',
                        text: data.msg,
                        showCancelButton: true,
                        confirmButtonText: '<i class="bx bx-printer me-1"></i> Cetak Hasil Nilai',
                        cancelButtonText: 'Tutup'
                    }).then(res => {
                        if (res.isConfirmed && data.id_sesi) {
                            window.location.href = 'print.php?id_sesi=' + data.id_sesi;
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Masuk',
                        text: data.msg || 'Silakan periksa kembali data pendaftaran Anda.'
                    });
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalContent;
                Swal.fire({
                    icon: 'error',
                    title: 'Gangguan Koneksi',
                    text: 'Tidak dapat terhubung ke server CBT.'
                });
            });
        });
    </script>
</body>
</html>