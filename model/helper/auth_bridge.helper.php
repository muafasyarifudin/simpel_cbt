<?php
/**
 * simpel_cbt - Universal Authentication Bridge Helper
 */

if (!function_exists('get_auth_bridge_config')) {
    function get_auth_bridge_config() {
        $configFile = __DIR__ . '/../config/config.auth_bridge.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        return ['mode' => 'standalone'];
    }
}

if (!function_exists('auth_bridge_verify_user')) {
    /**
     * Memverifikasi peserta berdasarkan mode autentikasi yang dipilih
     * 
     * @param string $username Nomor peserta / User ID / Email
     * @param string $password Password peserta (opsional)
     * @return array ['valid' => bool, 'username' => string, 'name' => string, 'msg' => string, 'raw' => array]
     */
    function auth_bridge_verify_user($username, $password = '') {
        $config = get_auth_bridge_config();
        $mode   = $config['mode'] ?? 'standalone';
        $uTrim  = trim($username);

        if (empty($uTrim)) {
            return ['valid' => false, 'msg' => 'Nomor Peserta / Username wajib diisi.'];
        }

        // 1. MODE STANDALONE
        if ($mode === 'standalone') {
            return [
                'valid'    => true,
                'username' => $uTrim,
                'name'     => $uTrim,
                'mode'     => 'standalone',
                'raw'      => []
            ];
        }

        // 2. MODE EXTERNAL DATABASE (Mode 2)
        if ($mode === 'external_db') {
            $dbCfg = $config['external_db'] ?? [];
            $extHost = $dbCfg['host'] ?? 'localhost';
            $extUser = $dbCfg['user'] ?? 'root';
            $extPass = $dbCfg['pass'] ?? '';
            $extDb   = $dbCfg['database'] ?? '';
            $extTbl  = $dbCfg['table'] ?? 'users';

            if (empty($extDb) || empty($extTbl)) {
                return ['valid' => false, 'msg' => 'Konfigurasi database eksternal belum lengkap.'];
            }

            // Buka koneksi ke database klien
            $extConn = @mysqli_connect($extHost, $extUser, $extPass, $extDb);
            if (!$extConn) {
                return ['valid' => false, 'msg' => 'Gagal terhubung ke database klien (' . $extDb . '): ' . mysqli_connect_error()];
            }
            mysqli_set_charset($extConn, "utf8mb4");

            $uEsc = mysqli_real_escape_string($extConn, $uTrim);

            // Bangun klausa WHERE berdasarkan username_columns
            $userCols = (array)($dbCfg['username_columns'] ?? ['username']);
            $whereParts = [];
            foreach ($userCols as $col) {
                $cClean = preg_replace('/[^A-Za-z0-9_]/', '', $col);
                if (!empty($cClean)) {
                    $whereParts[] = "`$cClean` = '$uEsc'";
                }
            }

            if (empty($whereParts)) {
                @mysqli_close($extConn);
                return ['valid' => false, 'msg' => 'Kolom username klien belum ditentukan.'];
            }

            $whereSql = implode(' OR ', $whereParts);
            $q = "SELECT * FROM `{$extTbl}` WHERE ($whereSql) LIMIT 1";
            $res = mysqli_query($extConn, $q);

            if (!$res || mysqli_num_rows($res) === 0) {
                @mysqli_close($extConn);
                return [
                    'valid' => false, 
                    'msg'   => "Nomor Peserta / ID '$uTrim' tidak ditemukan di database klien ({$extDb}.{$extTbl})."
                ];
            }

            $userRow = mysqli_fetch_assoc($res);

            // Validasi Password jika require_password = true
            if (!empty($dbCfg['require_password'])) {
                $passCol = $dbCfg['password_column'] ?? 'password';
                $storedPass = $userRow[$passCol] ?? '';
                $passType = $dbCfg['password_hash'] ?? 'auto';
                $passValid = false;

                if ($passType === 'bcrypt' || $passType === 'auto') {
                    if (password_verify($password, $storedPass)) {
                        $passValid = true;
                    }
                }
                if (!$passValid && ($passType === 'md5' || $passType === 'auto')) {
                    if (md5($password) === $storedPass) {
                        $passValid = true;
                    }
                }
                if (!$passValid && ($passType === 'plain' || $passType === 'auto')) {
                    if ($password === $storedPass) {
                        $passValid = true;
                    }
                }

                if (!$passValid) {
                    @mysqli_close($extConn);
                    return ['valid' => false, 'msg' => 'Password yang Anda masukkan salah.'];
                }
            }

            // Filter status jika ada
            if (!empty($dbCfg['status_column']) && !empty($dbCfg['status_allowed'])) {
                $statCol = $dbCfg['status_column'];
                $allowed = (array)$dbCfg['status_allowed'];
                $userStat = $userRow[$statCol] ?? null;
                if (!in_array((string)$userStat, array_map('strval', $allowed))) {
                    @mysqli_close($extConn);
                    return ['valid' => false, 'msg' => 'Akun Anda belum memenuhi syarat/status untuk mengikuti ujian ini.'];
                }
            }

            // Ambil Nama Lengkap
            $nameCol = $dbCfg['name_column'] ?? 'nama';
            $resolvedName = $userRow[$nameCol] ?? $uTrim;

            // Ambil Identitas Login Utama
            $primaryUserCol = $userCols[0] ?? 'username';
            $resolvedUsername = $userRow[$primaryUserCol] ?? $uTrim;

            @mysqli_close($extConn);

            return [
                'valid'    => true,
                'username' => $resolvedUsername,
                'name'     => $resolvedName,
                'mode'     => 'external_db',
                'raw'      => $userRow
            ];
        }

        // 3. MODE REST API (Mode 3)
        if ($mode === 'api') {
            $apiCfg = $config['api'] ?? [];
            $url    = $apiCfg['verify_url'] ?? '';

            if (empty($url)) {
                return ['valid' => false, 'msg' => 'Endpoint REST API belum dikonfigurasi.'];
            }

            $payload = json_encode([
                'username' => $uTrim,
                'password' => $password,
                'api_key'  => $apiCfg['api_key'] ?? ''
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, (int)($apiCfg['timeout_sec'] ?? 5));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $resData = json_decode($response, true);
                if (!empty($resData['valid'])) {
                    return [
                        'valid'    => true,
                        'username' => $resData['username'] ?? $uTrim,
                        'name'     => $resData['name'] ?? $uTrim,
                        'mode'     => 'api',
                        'raw'      => $resData
                    ];
                }
                return ['valid' => false, 'msg' => $resData['msg'] ?? 'Autentikasi API ditolak.'];
            }
            return ['valid' => false, 'msg' => 'Gagal menghubungi server autentikasi eksternal (HTTP ' . $httpCode . ').'];
        }

        return ['valid' => false, 'msg' => 'Mode autentikasi tidak dikenali.'];
    }
}