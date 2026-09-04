<?php
/**
 * simpel_cbt - Question Import/Export API
 * Supports:
 * - Moodle XML (.xml)
 * - Aiken Format (.txt)
 * - CSV / Excel (.csv)
 * - JSON Backup (.json)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.conn.php';
require_once __DIR__ . '/../helper/auth.helper.php';

require_api_login(['admin']);
require_csrf();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // ==========================================
    // 1. EXPORT SOAL
    // ==========================================
    case 'export':
        $format     = strtolower(trim($_GET['format'] ?? 'csv'));
        $idKategori = (int)($_GET['id_kategori'] ?? 0);

        $where = "WHERE 1=1";
        if ($idKategori > 0) {
            $where .= " AND s.id_kategori = $idKategori";
        }

        $query = mysqli_query($conn, "
            SELECT s.*, k.nama_kategori, k.kode_kategori 
            FROM cbt_soal s 
            LEFT JOIN cbt_kategori k ON s.id_kategori = k.id_kategori 
            $where 
            ORDER BY s.id_soal ASC
        ");

        $soalList = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $soalList[] = $row;
        }

        $timestamp = date('Ymd_His');

        // A. EXPORT MOODLE XML
        if ($format === 'moodle_xml' || $format === 'xml') {
            header('Content-Type: application/xml; charset=utf-8');
            header("Content-Disposition: attachment; filename=soal_cbt_moodle_{$timestamp}.xml");

            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            $xml .= "<quiz>\n";

            foreach ($soalList as $idx => $s) {
                $qNum = $idx + 1;
                $questionText = htmlspecialchars($s['pertanyaan']);
                $kunci = strtoupper(trim($s['kunci_jawaban']));
                $bobot = (int)$s['bobot_nilai'] ?: 1;

                $xml .= "  <question type=\"multichoice\">\n";
                $xml .= "    <name><text>Soal " . $qNum . " - " . htmlspecialchars($s['kode_kategori'] ?? 'CBT') . "</text></name>\n";
                $xml .= "    <questiontext format=\"html\">\n";
                $xml .= "      <text><![CDATA[" . $s['pertanyaan'] . "]]></text>\n";
                $xml .= "    </questiontext>\n";
                $xml .= "    <generalfeedback format=\"html\">\n";
                $xml .= "      <text><![CDATA[" . ($s['pembahasan'] ?? '') . "]]></text>\n";
                $xml .= "    </generalfeedback>\n";
                $xml .= "    <defaultgrade>" . $bobot . "</defaultgrade>\n";
                $xml .= "    <single>true</single>\n";
                $xml .= "    <shuffleanswers>true</shuffleanswers>\n";
                $xml .= "    <answernumbering>abc</answernumbering>\n";

                $opsiArr = [
                    'A' => $s['opsi_a'],
                    'B' => $s['opsi_b'],
                    'C' => $s['opsi_c'],
                    'D' => $s['opsi_d'],
                    'E' => $s['opsi_e']
                ];

                foreach ($opsiArr as $huruf => $teksOpsi) {
                    if (empty(trim($teksOpsi))) continue;
                    $fraction = ($huruf === $kunci) ? 100 : 0;
                    $xml .= "    <answer fraction=\"{$fraction}\" format=\"html\">\n";
                    $xml .= "      <text><![CDATA[" . $teksOpsi . "]]></text>\n";
                    $xml .= "    </answer>\n";
                }

                $xml .= "  </question>\n";
            }

            $xml .= "</quiz>\n";
            echo $xml;
            exit;
        }

        // B. EXPORT AIKEN FORMAT (.txt)
        else if ($format === 'aiken' || $format === 'txt') {
            header('Content-Type: text/plain; charset=utf-8');
            header("Content-Disposition: attachment; filename=soal_cbt_aiken_{$timestamp}.txt");

            $out = "";
            foreach ($soalList as $s) {
                // Pertanyaan
                $out .= trim(strip_tags($s['pertanyaan'])) . "\n";
                // Opsi
                $out .= "A. " . trim(strip_tags($s['opsi_a'])) . "\n";
                $out .= "B. " . trim(strip_tags($s['opsi_b'])) . "\n";
                $out .= "C. " . trim(strip_tags($s['opsi_c'])) . "\n";
                $out .= "D. " . trim(strip_tags($s['opsi_d'])) . "\n";
                if (!empty(trim($s['opsi_e']))) {
                    $out .= "E. " . trim(strip_tags($s['opsi_e'])) . "\n";
                }
                // Kunci
                $out .= "ANSWER: " . strtoupper(trim($s['kunci_jawaban'])) . "\n\n";
            }
            echo $out;
            exit;
        }

        // C. EXPORT JSON BACKUP
        else if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header("Content-Disposition: attachment; filename=soal_cbt_backup_{$timestamp}.json");
            echo json_encode([
                'app'          => 'SIMPEL CBT',
                'version'      => '3.0',
                'exported_at'  => date('Y-m-d H:i:s'),
                'total_soal'   => count($soalList),
                'data'         => $soalList
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // D. EXPORT CSV / EXCEL (Default)
        else {
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=soal_cbt_excel_{$timestamp}.csv");

            // Output BOM for Excel UTF-8 compatibility
            echo "\xEF\xBB\xBF";
            $fp = fopen('php://output', 'w');

            // Header kolom
            fputcsv($fp, ['No', 'Kategori', 'Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Pilihan E', 'Kunci Jawaban', 'Bobot Nilai', 'Pembahasan']);

            foreach ($soalList as $idx => $s) {
                fputcsv($fp, [
                    $idx + 1,
                    $s['nama_kategori'] ?? '',
                    $s['pertanyaan'],
                    $s['opsi_a'],
                    $s['opsi_b'],
                    $s['opsi_c'],
                    $s['opsi_d'],
                    $s['opsi_e'] ?? '',
                    strtoupper($s['kunci_jawaban']),
                    $s['bobot_nilai'],
                    $s['pembahasan'] ?? ''
                ]);
            }
            fclose($fp);
            exit;
        }
        break;

    // ==========================================
    // 2. IMPORT TEKS (FORMAT AIKEN CEPAT)
    // ==========================================
    case 'import_text':
        $rawText    = trim($_POST['raw_text'] ?? '');
        $idKategori = (int)($_POST['id_kategori'] ?? 1);
        $bobot      = (int)($_POST['bobot_nilai'] ?? 10);
        if ($bobot <= 0) $bobot = 10;

        if (empty($rawText)) {
            echo json_encode(['status' => 'error', 'msg' => 'Teks soal tidak boleh kosong!']);
            exit;
        }

        $parsed = parseAikenFormat($rawText);
        if (empty($parsed)) {
            echo json_encode(['status' => 'error', 'msg' => 'Format tidak valid atau tidak ada soal yang terdeteksi. Pastikan setiap soal memiliki pilihan (A, B, C, D) dan baris ANSWER: X.']);
            exit;
        }

        $inserted = 0;
        foreach ($parsed as $item) {
            $qEsc = mysqli_real_escape_string($conn, $item['pertanyaan']);
            $aEsc = mysqli_real_escape_string($conn, $item['opsi_a']);
            $bEsc = mysqli_real_escape_string($conn, $item['opsi_b']);
            $cEsc = mysqli_real_escape_string($conn, $item['opsi_c']);
            $dEsc = mysqli_real_escape_string($conn, $item['opsi_d']);
            $eEsc = mysqli_real_escape_string($conn, $item['opsi_e'] ?? '');
            $kEsc = mysqli_real_escape_string($conn, $item['kunci']);

            $sql = "INSERT INTO cbt_soal (id_kategori, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, bobot_nilai, status) 
                    VALUES ($idKategori, '$qEsc', '$aEsc', '$bEsc', '$cEsc', '$dEsc', '$eEsc', '$kEsc', $bobot, 1)";
            if (mysqli_query($conn, $sql)) {
                $inserted++;
            }
        }

        echo json_encode([
            'status' => 'success',
            'msg'    => "Berhasil mengimpor {$inserted} butir soal ke dalam Bank Soal!",
            'count'  => $inserted
        ]);
        break;

    // ==========================================
    // 3. IMPORT FILE (AIKEN, MOODLE XML, CSV, JSON)
    // ==========================================
    case 'import_file':
        if (!isset($_FILES['file_soal']) || $_FILES['file_soal']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'msg' => 'File tidak terunggah atau terjadi kesalahan!']);
            exit;
        }

        $idKategori = (int)($_POST['id_kategori'] ?? 1);
        $bobot      = (int)($_POST['bobot_nilai'] ?? 10);
        if ($bobot <= 0) $bobot = 10;

        $fileName = $_FILES['file_soal']['name'];
        $tmpName  = $_FILES['file_soal']['tmp_name'];
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $content  = file_get_contents($tmpName);

        $inserted = 0;

        // A. MOODLE XML
        if ($ext === 'xml') {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml) {
                echo json_encode(['status' => 'error', 'msg' => 'File XML tidak valid atau rusak.']);
                exit;
            }

            foreach ($xml->question as $q) {
                if ((string)$q['type'] !== 'multichoice') continue;
                $pertanyaan = trim((string)$q->questiontext->text);
                if (empty($pertanyaan)) continue;

                $opsiList = [];
                $kunci = 'A';
                $char = 'A';
                foreach ($q->answer as $ans) {
                    $fraction = (float)$ans['fraction'];
                    $text = trim((string)$ans->text);
                    if ($fraction > 50) {
                        $kunci = $char;
                    }
                    $opsiList[$char] = $text;
                    $char = chr(ord($char) + 1);
                    if ($char > 'E') break;
                }

                $qEsc = mysqli_real_escape_string($conn, $pertanyaan);
                $aEsc = mysqli_real_escape_string($conn, $opsiList['A'] ?? '');
                $bEsc = mysqli_real_escape_string($conn, $opsiList['B'] ?? '');
                $cEsc = mysqli_real_escape_string($conn, $opsiList['C'] ?? '');
                $dEsc = mysqli_real_escape_string($conn, $opsiList['D'] ?? '');
                $eEsc = mysqli_real_escape_string($conn, $opsiList['E'] ?? '');

                $sql = "INSERT INTO cbt_soal (id_kategori, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, bobot_nilai, status) 
                        VALUES ($idKategori, '$qEsc', '$aEsc', '$bEsc', '$cEsc', '$dEsc', '$eEsc', '$kunci', $bobot, 1)";
                if (mysqli_query($conn, $sql)) $inserted++;
            }
        }

        // B. CSV FILE
        else if ($ext === 'csv') {
            $handle = fopen($tmpName, 'r');
            // Check BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            $header = fgetcsv($handle); // skip header row

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 7) continue;
                // Format: No, Kategori, Pertanyaan, OpsiA, OpsiB, OpsiC, OpsiD, OpsiE, Kunci, Bobot, Pembahasan
                // OR Format: Pertanyaan, OpsiA, OpsiB, OpsiC, OpsiD, OpsiE, Kunci, Bobot
                if (is_numeric($row[0])) {
                    // Ada kolom No dan Kategori
                    $pertanyaan = trim($row[2] ?? '');
                    $opsiA      = trim($row[3] ?? '');
                    $opsiB      = trim($row[4] ?? '');
                    $opsiC      = trim($row[5] ?? '');
                    $opsiD      = trim($row[6] ?? '');
                    $opsiE      = trim($row[7] ?? '');
                    $kunci      = strtoupper(trim($row[8] ?? 'A'));
                    $bVal       = (int)($row[9] ?? $bobot);
                    $pemb       = trim($row[10] ?? '');
                } else {
                    $pertanyaan = trim($row[0] ?? '');
                    $opsiA      = trim($row[1] ?? '');
                    $opsiB      = trim($row[2] ?? '');
                    $opsiC      = trim($row[3] ?? '');
                    $opsiD      = trim($row[4] ?? '');
                    $opsiE      = trim($row[5] ?? '');
                    $kunci      = strtoupper(trim($row[6] ?? 'A'));
                    $bVal       = (int)($row[7] ?? $bobot);
                    $pemb       = trim($row[8] ?? '');
                }

                if (empty($pertanyaan) || empty($opsiA) || empty($opsiB)) continue;
                if (!in_array($kunci, ['A', 'B', 'C', 'D', 'E'])) $kunci = 'A';

                $qEsc = mysqli_real_escape_string($conn, $pertanyaan);
                $aEsc = mysqli_real_escape_string($conn, $opsiA);
                $bEsc = mysqli_real_escape_string($conn, $opsiB);
                $cEsc = mysqli_real_escape_string($conn, $opsiC);
                $dEsc = mysqli_real_escape_string($conn, $opsiD);
                $eEsc = mysqli_real_escape_string($conn, $opsiE);
                $pmEsc = mysqli_real_escape_string($conn, $pemb);

                $sql = "INSERT INTO cbt_soal (id_kategori, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, bobot_nilai, pembahasan, status) 
                        VALUES ($idKategori, '$qEsc', '$aEsc', '$bEsc', '$cEsc', '$dEsc', '$eEsc', '$kunci', $bVal, '$pmEsc', 1)";
                if (mysqli_query($conn, $sql)) $inserted++;
            }
            fclose($handle);
        }

        // C. JSON FILE
        else if ($ext === 'json') {
            $json = json_decode($content, true);
            $items = $json['data'] ?? (is_array($json) ? $json : []);
            foreach ($items as $s) {
                if (empty($s['pertanyaan'])) continue;
                $qEsc = mysqli_real_escape_string($conn, $s['pertanyaan']);
                $aEsc = mysqli_real_escape_string($conn, $s['opsi_a'] ?? '');
                $bEsc = mysqli_real_escape_string($conn, $s['opsi_b'] ?? '');
                $cEsc = mysqli_real_escape_string($conn, $s['opsi_c'] ?? '');
                $dEsc = mysqli_real_escape_string($conn, $s['opsi_d'] ?? '');
                $eEsc = mysqli_real_escape_string($conn, $s['opsi_e'] ?? '');
                $kEsc = strtoupper(trim($s['kunci_jawaban'] ?? 'A'));
                $bVal = (int)($s['bobot_nilai'] ?? $bobot);

                $katTarget = (int)($s['id_kategori'] ?? $idKategori);

                $sql = "INSERT INTO cbt_soal (id_kategori, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, bobot_nilai, status) 
                        VALUES ($katTarget, '$qEsc', '$aEsc', '$bEsc', '$cEsc', '$dEsc', '$eEsc', '$kEsc', $bVal, 1)";
                if (mysqli_query($conn, $sql)) $inserted++;
            }
        }

        // D. AIKEN TEXT FILE (.txt)
        else {
            $parsed = parseAikenFormat($content);
            foreach ($parsed as $item) {
                $qEsc = mysqli_real_escape_string($conn, $item['pertanyaan']);
                $aEsc = mysqli_real_escape_string($conn, $item['opsi_a']);
                $bEsc = mysqli_real_escape_string($conn, $item['opsi_b']);
                $cEsc = mysqli_real_escape_string($conn, $item['opsi_c']);
                $dEsc = mysqli_real_escape_string($conn, $item['opsi_d']);
                $eEsc = mysqli_real_escape_string($conn, $item['opsi_e'] ?? '');
                $kEsc = mysqli_real_escape_string($conn, $item['kunci']);

                $sql = "INSERT INTO cbt_soal (id_kategori, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, bobot_nilai, status) 
                        VALUES ($idKategori, '$qEsc', '$aEsc', '$bEsc', '$cEsc', '$dEsc', '$eEsc', '$kEsc', $bobot, 1)";
                if (mysqli_query($conn, $sql)) $inserted++;
            }
        }

        echo json_encode([
            'status' => 'success',
            'msg'    => "File '{$fileName}' berhasil diproses. Sebanyak {$inserted} butir soal berhasil diimpor ke sistem!",
            'count'  => $inserted
        ]);
        break;

    // ==========================================
    // 4. DOWNLOAD TEMPLATE
    // ==========================================
    case 'template':
        $type = $_GET['type'] ?? 'csv';
        if ($type === 'aiken') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename=contoh_format_aiken_moodle.txt');
            echo "Apa fungsi utama dari sistem RAM pada komputer?\n";
            echo "A. Menyimpan data secara permanen saat komputer mati\n";
            echo "B. Menyimpan data dan instruksi sementara saat program berjalan\n";
            echo "C. Memproses instruksi grafis 3 dimensi\n";
            echo "D. Menghubungkan komputer ke jaringan internet\n";
            echo "E. Mengatur pasokan daya listrik ke motherboard\n";
            echo "ANSWER: B\n\n";
            echo "Protokol standar yang digunakan untuk transfer data web secara aman dan terenkripsi adalah...\n";
            echo "A. HTTP\n";
            echo "B. FTP\n";
            echo "C. HTTPS\n";
            echo "D. SMTP\n";
            echo "E. TELNET\n";
            echo "ANSWER: C\n";
            exit;
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=template_import_soal_cbt.csv');
            echo "\xEF\xBB\xBF";
            $fp = fopen('php://output', 'w');
            fputcsv($fp, ['Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Pilihan E', 'Kunci Jawaban', 'Bobot Nilai', 'Pembahasan']);
            fputcsv($fp, [
                'Ibu kota negara Indonesia yang baru di Kalimantan Timur adalah...',
                'Nusantara',
                'Balikpapan',
                'Samarinda',
                'Banjarmasin',
                'Pontianak',
                'A',
                '10',
                'Ibu Kota Nusantara (IKN) terletak di Kalimantan Timur.'
            ]);
            fputcsv($fp, [
                'HTML merupakan singkatan dari...',
                'Hyperlink Text Machine Language',
                'Hyper Text Markup Language',
                'High Text Modern Language',
                'Home Tool Markup Language',
                'Hyper Transfer Markup Language',
                'B',
                '10',
                'HTML adalah Hyper Text Markup Language.'
            ]);
            fclose($fp);
            exit;
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'msg' => 'Aksi tidak valid.']);
        break;
}

/**
 * Helper: Parse Format Aiken Moodle
 */
function parseAikenFormat($text) {
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $questions = [];
    $currentQ = null;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) {
            continue;
        }

        // Cek ANSWER: X
        if (preg_match('/^ANSWER:\s*([A-Ea-e])/i', $trimmed, $mAns)) {
            if ($currentQ && !empty($currentQ['pertanyaan']) && !empty($currentQ['opsi_a'])) {
                $currentQ['kunci'] = strtoupper($mAns[1]);
                $questions[] = $currentQ;
                $currentQ = null;
            }
            continue;
        }

        // Cek Pilihan Jawaban: A. / A) / A -
        if (preg_match('/^([A-Ea-e])[\.\)\-]\s*(.*)$/i', $trimmed, $mOpsi)) {
            if ($currentQ) {
                $letter = strtolower($mOpsi[1]);
                $currentQ['opsi_' . $letter] = trim($mOpsi[2]);
            }
            continue;
        }

        // Pertanyaan Soal
        if (!$currentQ) {
            $currentQ = [
                'pertanyaan' => $trimmed,
                'opsi_a'     => '',
                'opsi_b'     => '',
                'opsi_c'     => '',
                'opsi_d'     => '',
                'opsi_e'     => '',
                'kunci'      => 'A'
            ];
        } else {
            // Lanjutan pertanyaan jika multi-line
            $currentQ['pertanyaan'] .= "\n" . $trimmed;
        }
    }

    return $questions;
}
PHP;

file_put_contents('C:/xampp/htdocs/simpel_cbt/api/soal_import_export.php', $soalImportExportCode);
echo "Written api/soal_import_export.php successfully.\n";
