<?php
/**
 * simpel_cbt - Universal Authentication Bridge Configuration
 * 
 * Pengaturan adapter autentikasi peserta ujian dinamis.
 * Siap dihubungkan ke server & database klien manapun (SPMB, SIAKAD, Dapodik, HRIS, Laravel, dll)
 * tanpa mengubah struktur database klien.
 */

return [
    /**
     * MODE AUTENTIKASI:
     * - 'standalone'  : Tanpa database user eksternal (cukup input No. Peserta, Nama, dan Token Ujian).
     * - 'external_db' : Terhubung langsung ke database sistem klien (MySQL / MariaDB).
     * - 'api'         : Terhubung via REST API webhook server klien.
     */
    'mode' => 'external_db',

    /**
     * PENGATURAN EXTERNAL DATABASE (Mode 2: 'external_db')
     */
    'external_db' => [
        // Kredensial Database Klien
        'host'     => getenv('EXT_DB_HOST') ?: 'localhost',
        'user'     => getenv('EXT_DB_USER') ?: 'root',
        'pass'     => getenv('EXT_DB_PASS') ?: '',
        'database' => getenv('EXT_DB_NAME') ?: 'db_spmb2',

        // Nama tabel user/peserta di database klien
        'table'    => 'mhs_baru',

        // Kolom login unik (bisa string tunggal atau array kolom alternatif)
        // Contoh: ['user_id', 'email'] -> peserta bisa input No. Pendaftaran ATAU Email
        'username_columns' => ['user_id', 'email'],

        // Kolom nama lengkap peserta di tabel klien
        'name_column' => 'nama',

        // Apakah login peserta wajib memasukkan password akun?
        // false: Cukup masukkan Nomor Peserta/ID + Token Ujian (Nama otomatis ditarik dari DB klien).
        // true : Wajib memasukkan Password akun yang terdaftar.
        'require_password' => false,

        // Nama kolom password di tabel klien (jika require_password = true)
        'password_column'  => 'password',

        // Algoritma hashing password klien:
        // 'auto'   : Deteksi otomatis (bcrypt / MD5 / plain text)
        // 'bcrypt' : PHP password_verify (standar Laravel / PHP modern)
        // 'md5'    : Hash MD5 bawaan sistem lama
        // 'plain'  : Plain text tanpa enkripsi
        'password_hash'    => 'auto',

        // Filter status tambahan (opsional, set null jika tidak digunakan)
        'status_column'    => null,
        'status_allowed'   => null,
    ],

    /**
     * PENGATURAN REST API BRIDGE (Mode 3: 'api')
     */
    'api' => [
        'verify_url'   => 'https://klien-domain.com/api/cbt-verify-user',
        'webhook_url'  => 'https://klien-domain.com/api/cbt-submit-score',
        'api_key'      => 'SECRET_CBT_KEY_2026',
        'timeout_sec'  => 5
    ]
];