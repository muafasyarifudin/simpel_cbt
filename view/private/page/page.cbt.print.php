<?php
/**
 * simpel_cbt - Cetak Sertifikat & Transkrip Hasil Ujian CBT (Modern Minimalist Hybrid)
 */
require_once __DIR__ . '/../../../model/config/config.conn.php';
require_once __DIR__ . '/../../../model/helper/cbt.helper.php';
require_once __DIR__ . '/../../../model/helper/auth.helper.php';

$id_sesi = (int)($_GET['id_sesi'] ?? 0);

if ($id_sesi <= 0) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Parameter Ujian Tidak Valid</h2><p>ID Sesi tidak ditemukan.</p><a href='index.php'>Kembali ke Beranda</a></div>");
}

// Ambil data sesi & hasil
$q = "SELECT s.*, j.nama_ujian, j.passing_grade, j.durasi_menit, j.tipe_ujian,
             h.id_hasil, h.total_soal, h.total_dijawab, h.jumlah_benar, h.jumlah_salah, h.jumlah_kosong, h.nilai_akhir, h.status_kelulusan, h.catatan, h.created_at as tgl_hasil
      FROM cbt_sesi s
      JOIN cbt_jadwal j ON s.id_jadwal = j.id_jadwal
      LEFT JOIN cbt_hasil h ON s.id_sesi = h.id_sesi
      WHERE s.id_sesi = $id_sesi LIMIT 1";
$res = mysqli_query($conn, $q);
$d = mysqli_fetch_assoc($res);

if (!$d) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Data Tidak Ditemukan</h2><p>Data sesi ujian tidak ditemukan di database.</p><a href='index.php'>Kembali ke Beranda</a></div>");
}

$isOwner = (int)($_SESSION['cbt_session_id'] ?? 0) === $id_sesi;
$isStaff = is_admin_logged_in();
$providedSig = (string)($_GET['sig'] ?? '');
$validSig = $providedSig !== '' && hash_equals(exam_result_signature($id_sesi, $d['no_pendaftaran']), $providedSig);
if (!$isOwner && !$isStaff && !$validSig) {
    http_response_code(403);
    die("<div style='text-align:center;padding:50px;font-family:sans-serif'><h2>Akses Ditolak</h2><p>Tautan hasil tidak valid.</p></div>");
}

$isLulus = ($d['status_kelulusan'] === 'LULUS');
$tahunUjian = date('Y', strtotime($d['waktu_mulai'] ?? 'now'));
$noSertifikat = sprintf("CBT/SIMPEL/%s/%04d", $tahunUjian, $d['id_sesi']);
$resultSignature = exam_result_signature($id_sesi, $d['no_pendaftaran']);
$hashVerify = strtoupper(substr($resultSignature, 0, 12));

// QR Verification URL
$verifyUrl = BASE_URL . "print.php?id_sesi=" . $d['id_sesi'] . "&verify=1&sig=" . urlencode($resultSignature);
$qrApiUrl  = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Ujian - <?= htmlspecialchars($d['nama_peserta'] ?: $d['no_pendaftaran']) ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">

    <!-- Icons & Bootstrap 5 -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #4f46e5;
            --bg-canvas: #f8fafc;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --border-subtle: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            padding: 30px 16px;
            margin: 0;
        }

        .cert-card {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
            padding: 40px 42px;
        }

        .cert-header {
            border-bottom: 1px solid var(--border-subtle);
            padding-bottom: 22px;
            margin-bottom: 26px;
        }

        .brand-icon-cert {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .score-box {
            background-color: #fafbfc;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 22px;
            text-align: center;
        }

        .score-number {
            font-size: 3.2rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            letter-spacing: -1px;
            margin: 6px 0;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .cert-card {
                border: 1px solid #ccc;
                box-shadow: none;
                padding: 24px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
</head>

<body>
    <!-- Screen Actions -->
    <div class="cert-card no-print mb-3 py-2 px-3 border-0 bg-transparent shadow-none text-end">
        <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bx bx-arrow-back me-1"></i> Kembali ke Beranda
        </a>
        <button onclick="window.print()" class="btn btn-sm btn-primary" style="background-color: var(--primary); border: none;">
            <i class="bx bx-printer me-1"></i> Cetak / Simpan PDF
        </button>
    </div>

    <!-- Official Certificate / Result Sheet -->
    <div class="cert-card">
        
        <!-- Header -->
        <div class="cert-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-icon-cert">
                    <i class="bx bx-select-multiple"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">SIMPEL <span style="color: var(--primary);">CBT</span></h4>
                    <span class="text-muted small">Lembar Transkrip Resmi Hasil Ujian Komputer</span>
                </div>
            </div>
            <div class="text-end">
                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($noSertifikat) ?></span>
                <div class="text-muted small mt-1">Tanggal: <?= date('d M Y, H:i', strtotime($d['tgl_hasil'] ?: 'now')) ?> WIB</div>
            </div>
        </div>

        <!-- Identity & Score Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-7">
                <h6 class="fw-bold text-dark mb-3">Identitas Peserta Ujian</h6>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="text-muted" style="width: 140px;">No. Peserta / NIK</td>
                        <td>: <strong class="text-dark font-monospace"><?= htmlspecialchars($d['no_pendaftaran']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Nama Peserta</td>
                        <td>: <strong class="text-dark fs-6"><?= htmlspecialchars($d['nama_peserta'] ?: '-') ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Paket Ujian</td>
                        <td>: <?= htmlspecialchars($d['nama_ujian']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Durasi Ujian</td>
                        <td>: <?= $d['durasi_menit'] ?> Menit</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Waktu Selesai</td>
                        <td>: <?= $d['waktu_selesai'] ? date('d/m/Y H:i:s', strtotime($d['waktu_selesai'])) : date('d/m/Y H:i:s') ?></td>
                    </tr>
                </table>
            </div>

            <div class="col-md-5">
                <div class="score-box">
                    <div class="text-muted small fw-semibold text-uppercase">Nilai Akhir Ujian</div>
                    <div class="score-number"><?= ($d['nilai_akhir'] !== null) ? $d['nilai_akhir'] : '0' ?></div>
                    <div class="small text-muted mb-2">Passing Grade: <strong><?= $d['passing_grade'] ?></strong></div>
                    <span class="badge <?= $isLulus ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> px-3 py-2 fs-6">
                        STATUS: <?= $d['status_kelulusan'] ?: 'BELUM DIHITUNG' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Answers Breakdown -->
        <div class="card bg-light border-0 p-3 mb-4 rounded-3">
            <h6 class="fw-bold text-dark mb-2">Statistik Pengerjaan Lembar Jawaban</h6>
            <div class="row g-2 text-center">
                <div class="col-3">
                    <div class="p-2 bg-white rounded border">
                        <small class="text-muted d-block">Total Soal</small>
                        <strong class="fs-5 text-dark"><?= (int)$d['total_soal'] ?></strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 bg-white rounded border">
                        <small class="text-muted d-block">Terjawab</small>
                        <strong class="fs-5 text-primary"><?= (int)$d['total_dijawab'] ?></strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 bg-white rounded border">
                        <small class="text-muted d-block">Jawaban Benar</small>
                        <strong class="fs-5 text-success"><?= (int)$d['jumlah_benar'] ?></strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 bg-white rounded border">
                        <small class="text-muted d-block">Salah / Kosong</small>
                        <strong class="fs-5 text-danger"><?= (int)$d['jumlah_salah'] + (int)$d['jumlah_kosong'] ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification & Footer -->
        <div class="row align-items-center pt-3 border-top mt-4">
            <div class="col-8">
                <div class="small text-muted">
                    <div>Dokumen ini diterbitkan secara sah oleh sistem <strong>SIMPEL CBT</strong>.</div>
                    <div>Pemindaian QR code di samping kanan memvalidasi keaslian berkas transkrip nilai ini.</div>
                    <div class="font-monospace mt-1">Hash Validasi: <?= $hashVerify ?></div>
                </div>
            </div>
            <div class="col-4 text-end">
                <img src="<?= $qrApiUrl ?>" alt="QR Code" class="img-thumbnail rounded" style="width: 90px; height: 90px;">
            </div>
        </div>

    </div>
</body>
</html>
