<?php
/**
 * simpel_cbt - Export Rekapitulasi Nilai Peserta ke Format Excel (.xls)
 */
require_once __DIR__ . '/../config/config.conn.php';
require_once __DIR__ . '/../helper/cbt.helper.php';
require_once __DIR__ . '/../helper/auth.helper.php';
require_api_login(['admin', 'pengawas']);

$id_jadwal = (int)($_GET['id_jadwal'] ?? 0);

$where = "WHERE 1=1";
$jadwalName = "Semua_Jadwal";

if ($id_jadwal > 0) {
    $where .= " AND s.id_jadwal = $id_jadwal";
    $qJ = mysqli_query($conn, "SELECT nama_ujian FROM cbt_jadwal WHERE id_jadwal = $id_jadwal LIMIT 1");
    if ($rJ = mysqli_fetch_assoc($qJ)) {
        $jadwalName = preg_replace('/[^A-Za-z0-9_-]/', '_', $rJ['nama_ujian']);
    }
}

$filename = "Rekap_Nilai_CBT_" . $jadwalName . "_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$q = "SELECT s.*, j.nama_ujian, j.passing_grade, j.durasi_menit,
             h.total_soal, h.total_dijawab, h.jumlah_benar, h.jumlah_salah, h.jumlah_kosong, h.nilai_akhir, h.status_kelulusan
      FROM cbt_sesi s
      JOIN cbt_jadwal j ON s.id_jadwal = j.id_jadwal
      LEFT JOIN cbt_hasil h ON s.id_sesi = h.id_sesi
      $where
      ORDER BY s.id_sesi DESC";
$res = mysqli_query($conn, $q);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11pt; }
        th { background-color: #2563eb; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; padding: 10px; }
        td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .lulus { background-color: #d1fae5; color: #065f46; font-weight: bold; text-align: center; }
        .tidak-lulus { background-color: #fee2e2; color: #991b1b; font-weight: bold; text-align: center; }
        .mengerjakan { background-color: #fef3c7; color: #92400e; font-weight: bold; text-align: center; }
        .title { font-size: 16pt; font-weight: bold; text-align: center; margin-bottom: 5px; }
        .subtitle { font-size: 11pt; text-align: center; color: #555; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="title">REKAPITULASI HASIL UJIAN COMPUTER-BASED TEST (CBT)</div>
    <div class="subtitle">Paket Ujian: <?= htmlspecialchars(str_replace('_', ' ', $jadwalName)) ?> | Waktu Unduh: <?= date('d/m/Y H:i:s') ?></div>

    <table border="1">
        <thead>
            <tr>
                <th>No</th>
                <th>No. Peserta</th>
                <th>Nama Peserta</th>
                <th>Paket Ujian</th>
                <th>Waktu Mulai</th>
                <th>Waktu Selesai</th>
                <th>Total Soal</th>
                <th>Dijawab</th>
                <th>Benar</th>
                <th>Salah</th>
                <th>Kosong</th>
                <th>Nilai Akhir</th>
                <th>Passing Grade</th>
                <th>Status Kelulusan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            if ($res && mysqli_num_rows($res) > 0):
                while ($row = mysqli_fetch_assoc($res)): 
                    $statusClass = '';
                    $statusLabel = $row['status_kelulusan'] ?? '-';
                    if ($row['status_sesi'] === 'sedang_mengerjakan') {
                        $statusClass = 'mengerjakan';
                        $statusLabel = 'Sedang Ujian';
                    } elseif ($statusLabel === 'LULUS') {
                        $statusClass = 'lulus';
                    } elseif ($statusLabel === 'TIDAK_LULUS') {
                        $statusClass = 'tidak-lulus';
                    }
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['no_pendaftaran']) ?></td>
                <td><?= htmlspecialchars($row['nama_peserta'] ?: '-') ?></td>
                <td><?= htmlspecialchars($row['nama_ujian']) ?></td>
                <td class="text-center"><?= !empty($row['waktu_mulai']) ? date('d/m/Y H:i', strtotime($row['waktu_mulai'])) : '-' ?></td>
                <td class="text-center"><?= !empty($row['waktu_selesai']) ? date('d/m/Y H:i', strtotime($row['waktu_selesai'])) : '-' ?></td>
                <td class="text-center"><?= (int)($row['total_soal'] ?? 0) ?></td>
                <td class="text-center"><?= (int)($row['total_dijawab'] ?? 0) ?></td>
                <td class="text-center"><?= (int)($row['jumlah_benar'] ?? 0) ?></td>
                <td class="text-center"><?= (int)($row['jumlah_salah'] ?? 0) ?></td>
                <td class="text-center"><?= (int)($row['jumlah_kosong'] ?? 0) ?></td>
                <td class="text-center" style="font-weight: bold;"><?= number_format((float)($row['nilai_akhir'] ?? 0), 2) ?></td>
                <td class="text-center"><?= number_format((float)$row['passing_grade'], 2) ?></td>
                <td class="<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></td>
            </tr>
            <?php 
                endwhile; 
            else: 
            ?>
            <tr>
                <td colspan="14" class="text-center" style="padding: 20px;">Tidak ada data hasil ujian yang ditemukan.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
