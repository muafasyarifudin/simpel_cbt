<?php
/**
 * simpel_cbt - CBT Helper Functions
 */

if (!function_exists('cbt_clean_input')) {
    function cbt_clean_input($conn, $data) {
        if (is_array($data)) {
            return array_map(function($item) use ($conn) {
                return cbt_clean_input($conn, $item);
            }, $data);
        }
        return mysqli_real_escape_string($conn, trim($data ?? ''));
    }
}

if (!function_exists('cbt_get_stats')) {
    /**
     * Mengambil statistik ringkas CBT untuk dashboard admin
     */
    function cbt_get_stats($conn) {
        $stats = [
            'total_kategori' => 0,
            'total_soal'     => 0,
            'total_jadwal'   => 0,
            'total_peserta'  => 0,
            'peserta_lulus'  => 0
        ];

        $rKat = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cbt_kategori WHERE status = 1");
        if ($rKat) { $stats['total_kategori'] = (int)mysqli_fetch_assoc($rKat)['cnt']; }

        $rSoal = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cbt_soal WHERE status = 1");
        if ($rSoal) { $stats['total_soal'] = (int)mysqli_fetch_assoc($rSoal)['cnt']; }

        $rJadwal = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cbt_jadwal WHERE status_ujian = 'aktif'");
        if ($rJadwal) { $stats['total_jadwal'] = (int)mysqli_fetch_assoc($rJadwal)['cnt']; }

        $rPeserta = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cbt_sesi");
        if ($rPeserta) { $stats['total_peserta'] = (int)mysqli_fetch_assoc($rPeserta)['cnt']; }

        $rLulus = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cbt_hasil WHERE status_kelulusan = 'LULUS'");
        if ($rLulus) { $stats['peserta_lulus'] = (int)mysqli_fetch_assoc($rLulus)['cnt']; }

        return $stats;
    }
}

if (!function_exists('cbt_get_jadwal_subtes')) {
    function cbt_get_jadwal_subtes($conn, $id_jadwal) {
        $id_jadwal = (int)$id_jadwal;
        $q = "SELECT js.*, k.nama_kategori, k.kode_kategori 
              FROM cbt_jadwal_subtes js 
              LEFT JOIN cbt_kategori k ON js.id_kategori = k.id_kategori 
              WHERE js.id_jadwal = $id_jadwal 
              ORDER BY js.urutan ASC, js.id_subtes ASC";
        $res = mysqli_query($conn, $q);
        $data = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('cbt_calculate_score')) {
    /**
     * Menghitung nilai akhir sesi ujian CBT berdasarkan jawaban peserta (Mendukung Multi-Subtes)
     */
    function cbt_calculate_score($conn, $id_sesi) {
        $id_sesi = (int)$id_sesi;

        // Ambil info sesi dan jadwal
        $qSesi = "SELECT s.*, j.passing_grade, j.durasi_menit, j.tipe_ujian 
                  FROM cbt_sesi s 
                  JOIN cbt_jadwal j ON s.id_jadwal = j.id_jadwal 
                  WHERE s.id_sesi = $id_sesi LIMIT 1";
        $rSesi = mysqli_query($conn, $qSesi);
        $sesi = mysqli_fetch_assoc($rSesi);
        if (!$sesi) {
            return false;
        }

        // Ambil semua jawaban peserta, kunci jawaban soal, dan relasi subtes
        $qJwb = "SELECT j.*, so.kunci_jawaban, so.bobot_nilai, js.nama_subtes 
                 FROM cbt_jawaban j 
                 JOIN cbt_soal so ON j.id_soal = so.id_soal 
                 LEFT JOIN cbt_jadwal_subtes js ON j.id_subtes = js.id_subtes 
                 WHERE j.id_sesi = $id_sesi";
        $rJwb = mysqli_query($conn, $qJwb);

        $totalSoal = 0;
        $totalDijawab = 0;
        $jumlahBenar = 0;
        $jumlahSalah = 0;
        $jumlahKosong = 0;
        $totalSkorDiperoleh = 0;
        $totalBobotMaksimal = 0;

        $subtesStats = [];

        while ($row = mysqli_fetch_assoc($rJwb)) {
            $totalSoal++;
            $bobot = (float)($row['bobot_nilai'] ?: 1);
            $totalBobotMaksimal += $bobot;

            $idSubtes = (int)($row['id_subtes'] ?? 0);
            $namaSubtes = $row['nama_subtes'] ?? 'Umum';

            if (!isset($subtesStats[$idSubtes])) {
                $subtesStats[$idSubtes] = [
                    'id_subtes'     => $idSubtes,
                    'nama_subtes'   => $namaSubtes,
                    'total_soal'    => 0,
                    'benar'         => 0,
                    'salah'         => 0,
                    'kosong'        => 0,
                    'skor_diperoleh'=> 0,
                    'bobot_maksimal'=> 0
                ];
            }

            $subtesStats[$idSubtes]['total_soal']++;
            $subtesStats[$idSubtes]['bobot_maksimal'] += $bobot;

            $jawabanPeserta = strtoupper(trim($row['jawaban_dipilih'] ?? ''));
            $kunci = strtoupper(trim($row['kunci_jawaban'] ?? ''));

            if ($jawabanPeserta === '') {
                $jumlahKosong++;
                $subtesStats[$idSubtes]['kosong']++;
                $isBenar = 0;
                $skor = 0;
            } elseif ($jawabanPeserta === $kunci) {
                $totalDijawab++;
                $jumlahBenar++;
                $subtesStats[$idSubtes]['benar']++;
                $isBenar = 1;
                $skor = $bobot;
                $totalSkorDiperoleh += $bobot;
                $subtesStats[$idSubtes]['skor_diperoleh'] += $bobot;
            } else {
                $totalDijawab++;
                $jumlahSalah++;
                $subtesStats[$idSubtes]['salah']++;
                $isBenar = 0;
                $skor = 0;
            }

            // Update baris jawaban dengan status benar & skor
            $idJawaban = (int)$row['id_jawaban'];
            mysqli_query($conn, "UPDATE cbt_jawaban 
                                 SET is_benar = $isBenar, skor_diperoleh = $skor 
                                 WHERE id_jawaban = $idJawaban");
        }

        // Skala nilai akhir 0 - 100
        $nilaiAkhir = 0.00;
        if ($totalBobotMaksimal > 0) {
            $nilaiAkhir = round(($totalSkorDiperoleh / $totalBobotMaksimal) * 100, 2);
        }

        // Tentukan kelulusan terhadap passing grade
        $passingGrade = (float)$sesi['passing_grade'];
        $statusKelulusan = ($nilaiAkhir >= $passingGrade) ? 'LULUS' : 'TIDAK_LULUS';

        // Hitung nilai akhir per subtes
        $breakdownList = [];
        foreach ($subtesStats as $sId => $sData) {
            $sNilai = 0.00;
            if ($sData['bobot_maksimal'] > 0) {
                $sNilai = round(($sData['skor_diperoleh'] / $sData['bobot_maksimal']) * 100, 2);
            }
            $sData['nilai'] = $sNilai;
            $breakdownList[] = $sData;
        }

        $catatanJson = null;
        if ($sesi['tipe_ujian'] === 'multi_subtes' || count($breakdownList) > 1) {
            $catatanJson = json_encode([
                'tipe_ujian'   => $sesi['tipe_ujian'],
                'subtes_count' => count($breakdownList),
                'breakdown'    => $breakdownList
            ]);
        }
        $catatanEsc = $catatanJson ? "'" . mysqli_real_escape_string($conn, $catatanJson) . "'" : "NULL";

        // Simpan atau update ke tabel cbt_hasil
        $idJadwal = (int)$sesi['id_jadwal'];
        $noDaftar = mysqli_real_escape_string($conn, $sesi['no_pendaftaran']);
        $namaPeserta = mysqli_real_escape_string($conn, $sesi['nama_peserta'] ?? '');

        $checkHasil = mysqli_query($conn, "SELECT id_hasil FROM cbt_hasil WHERE id_sesi = $id_sesi LIMIT 1");
        if (mysqli_num_rows($checkHasil) > 0) {
            $qUp = "UPDATE cbt_hasil SET 
                        total_soal = $totalSoal,
                        total_dijawab = $totalDijawab,
                        jumlah_benar = $jumlahBenar,
                        jumlah_salah = $jumlahSalah,
                        jumlah_kosong = $jumlahKosong,
                        nilai_akhir = $nilaiAkhir,
                        status_kelulusan = '$statusKelulusan',
                        catatan = $catatanEsc
                    WHERE id_sesi = $id_sesi";
            mysqli_query($conn, $qUp);
        } else {
            $qIns = "INSERT INTO cbt_hasil 
                        (id_sesi, id_jadwal, no_pendaftaran, nama_peserta, total_soal, total_dijawab, jumlah_benar, jumlah_salah, jumlah_kosong, nilai_akhir, status_kelulusan, catatan) 
                     VALUES 
                        ($id_sesi, $idJadwal, '$noDaftar', '$namaPeserta', $totalSoal, $totalDijawab, $jumlahBenar, $jumlahSalah, $jumlahKosong, $nilaiAkhir, '$statusKelulusan', $catatanEsc)";
            mysqli_query($conn, $qIns);
        }

        // Update status sesi menjadi 'selesai'
        $now = date('Y-m-d H:i:s');
        mysqli_query($conn, "UPDATE cbt_sesi SET status_sesi = 'selesai', waktu_selesai = '$now', sisa_detik = 0, sisa_detik_subtes = 0 WHERE id_sesi = $id_sesi");

        return [
            'total_soal'       => $totalSoal,
            'total_dijawab'    => $totalDijawab,
            'jumlah_benar'     => $jumlahBenar,
            'jumlah_salah'     => $jumlahSalah,
            'jumlah_kosong'    => $jumlahKosong,
            'nilai_akhir'      => $nilaiAkhir,
            'passing_grade'    => $passingGrade,
            'status_kelulusan' => $statusKelulusan,
            'tipe_ujian'       => $sesi['tipe_ujian'],
            'breakdown'        => $breakdownList
        ];
    }
}

if (!function_exists('cbt_get_analisis_soal')) {
    /**
     * Menghitung analisis butir soal (tingkat kesulitan & daya serap)
     */
    function cbt_get_analisis_soal($conn, $id_kategori = 0) {
        $where = "WHERE s.status = 1";
        if ($id_kategori > 0) {
            $where .= " AND s.id_kategori = $id_kategori";
        }

        $q = "SELECT s.id_soal, s.pertanyaan, s.kunci_jawaban, k.nama_kategori,
                     COUNT(j.id_jawaban) as total_dikerjakan,
                     SUM(CASE WHEN j.is_benar = 1 THEN 1 ELSE 0 END) as jumlah_benar,
                     SUM(CASE WHEN j.jawaban_dipilih IS NOT NULL AND j.jawaban_dipilih != '' AND j.is_benar = 0 THEN 1 ELSE 0 END) as jumlah_salah,
                     SUM(CASE WHEN j.jawaban_dipilih IS NULL OR j.jawaban_dipilih = '' THEN 1 ELSE 0 END) as jumlah_kosong
              FROM cbt_soal s
              JOIN cbt_kategori k ON s.id_kategori = k.id_kategori
              LEFT JOIN cbt_jawaban j ON s.id_soal = j.id_soal
              $where
              GROUP BY s.id_soal
              ORDER BY s.id_soal ASC";
        $res = mysqli_query($conn, $q);
        $result = [];

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $total = (int)$row['total_dikerjakan'];
                $benar = (int)$row['jumlah_benar'];
                $tingkatKesulitan = 0;
                $kategoriKesulitan = 'Belum Ada Data';

                if ($total > 0) {
                    $pIndex = round($benar / $total, 2);
                    $tingkatKesulitan = $pIndex;
                    if ($pIndex < 0.30) {
                        $kategoriKesulitan = 'Sukar';
                    } elseif ($pIndex <= 0.70) {
                        $kategoriKesulitan = 'Sedang';
                    } else {
                        $kategoriKesulitan = 'Mudah';
                    }
                }

                $row['pertanyaan_preview'] = mb_strimwidth(strip_tags($row['pertanyaan']), 0, 80, '...');
                $row['index_kesulitan'] = $tingkatKesulitan;
                $row['kategori_kesulitan'] = $kategoriKesulitan;
                $result[] = $row;
            }
        }
        return $result;
    }
}

if (!function_exists('tglIndoFormatted')) {
    function tglIndoFormatted($datetime, $withTime = false) {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
            return '-';
        }
        $ts = strtotime($datetime);
        if (!$ts) return $datetime;

        $bulanIndo = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $hariIndo = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
        ];

        $hari = $hariIndo[date('l', $ts)] ?? '';
        $tgl = date('d', $ts);
        $bln = $bulanIndo[(int)date('m', $ts)] ?? '';
        $thn = date('Y', $ts);

        $out = "$hari, $tgl $bln $thn";
        if ($withTime) {
            $out .= ' ' . date('H:i', $ts) . ' WIB';
        }
        return $out;
    }
}