# Simpel CBT (Computer-Based Test) - Standalone Application

Aplikasi Ujian Online Berbasis Komputer (CBT) mandiri, cepat, responsif, dan siap pakai (*plug-and-play*) untuk lingkungan sekolah, kampus, bimbingan belajar, maupun instansi.

---

## 🌟 Fitur Utama

### 1. Portal Peserta Ujian (`/` dan `/exam.php`)
- **Login Peserta Simpel**: Masukkan Nomor Peserta/NISN/ID, Nama Lengkap, dan Token Ujian.
- **Ruang Ujian Interaktif (Edge-to-Edge Desktop & Mobile)**:
  - Timer countdown presisi dengan indikator warna (Normal, Warning < 10 menit, Danger < 2 menit).
  - Pilihan ganda interaktif dengan kartu opsi A, B, C, D, E.
  - Fitur Ragu-ragu (centang kuning pada nomor soal).
  - Palet nomor soal dengan indikator status (Terjawab, Ragu-ragu, Kosong, Soal Aktif).
  - Shortcut keyboard untuk pengerjaan cepat (A/B/C/D/E, Panah Kiri/Kanan, R untuk ragu-ragu).
  - Pengatur ukuran font (A-, A, A+) untuk aksesibilitas peserta.
  - Auto-save jawaban ke database via AJAX secara berkala tanpa reload.
  - Auto-submit otomatis saat waktu pengerjaan habis.
- **Cetak Hasil Ujian Resmi (`/print.php`)**:
  - Lembar hasil & sertifikat kelulusan format A4 resmi.
  - QR Code verifikasi dokumen digital.
  - Rincian nilai per subtes jika ujian bertahap (multi-subtes).

### 2. Panel Administrator & Pengawas (`/admin`)
- **Kredensial Default**:
  - **Username**: `admin`
  - **Password**: `admin123`
- **Dashboard & Statistik**: Ringkasan jumlah soal, kategori, paket ujian aktif, peserta ujian, dan persentase kelulusan.
- **Manajemen Kategori & Bank Soal**:
  - Tambah, edit, hapus kategori / mata uji.
  - Tambah butir soal pilihan ganda lengkap dengan upload gambar ilustrasi, pembahasan, dan bobot nilai.
  - Filter kategori dan pencarian kata kunci soal.
- **Manajemen Paket Ujian (Jadwal)**:
  - Tipe ujian standar (satu sesi) maupun bertahap (multi-subtes).
  - Pengaturan durasi pengerjaan menit, token ujian, passing grade, acak soal, dan rentang waktu ujian dibuka/ditutup.
  - Generator token acak otomatis.
- **Live Monitoring Peserta Real-time**:
  - Memantau peserta yang sedang ujian secara langsung (sisa waktu, progres soal terisi, dan nilai).
  - Fitur **Paksa Selesai (Force Finish)** untuk mengunci ujian dari jarak jauh.
  - Fitur **Reset Sesi** jika peserta mengalami kendala komputer mati/browser crash.
- **Rekap & Analisis**:
  - Unduh Rekap Nilai ke format Spreadsheet Excel (`.xls`) rapi dan lengkap.
  - Analisis butir soal & tingkat kesulitan.

---

## 🚀 Cara Menjalankan di XAMPP

1. Pastikan folder aplikasi berada di:
   `c:\xampp\htdocs\simpel_cbt`
2. Buka **XAMPP Control Panel**, lalu klik **Start** pada modul **Apache** dan **MySQL**.
3. Buka browser:
   - **Portal Peserta**: [http://localhost/simpel_cbt/](http://localhost/simpel_cbt/)
   - **Panel Admin / Pengawas**: [http://localhost/simpel_cbt/admin/](http://localhost/simpel_cbt/admin/)
4. **Inisialisasi Otomatis**: Saat pertama kali dibuka di browser, sistem secara otomatis:
   - Membuat database `simpel_cbt`.
   - Membuat seluruh tabel relasional.
   - Mengisi akun admin awal (`admin` / `admin123`).
   - Mengisi kategori dan contoh butir soal TPS/Bahasa/Umum serta jadwal simulasi siap pakai.
5. (Alternatif Manual) Anda juga dapat mengimpor file `database.sql` langsung melalui phpMyAdmin.

---

## 📁 Struktur Direktori

```
simpel_cbt/
├── config/
│   ├── database.php          # Koneksi MySQLi, Base URL dinamis & auto-provisioning DB
│   └── migration.php         # Skema DDL tabel dan seeder data awal
├── helpers/
│   ├── cbt_helper.php        # Logika perhitungan nilai, passing grade, statistik & format tanggal
│   └── auth_helper.php       # Proteksi autentikasi session admin
├── api/
│   ├── exam_api.php          # Engine ujian online (start, load_soal, simpan_jawaban, ragu, finish, ping)
│   ├── soal_crud.php         # AJAX CRUD bank soal dan upload gambar ilustrasi
│   ├── jadwal_crud.php       # AJAX CRUD jadwal ujian & generate token
│   ├── kategori_crud.php     # AJAX CRUD kategori soal
│   ├── monitoring_api.php    # Live monitoring ujian, force finish & reset sesi
│   └── export_rekap.php      # Export rekapitulasi nilai ke Excel (.xls)
├── admin/
│   ├── index.php             # Dashboard Admin CBT
│   ├── login.php             # Form login admin/pengawas
│   └── logout.php            # Logout admin
├── uploads/
│   └── cbt/                  # Direktori penyimpanan gambar butir soal
├── index.php                 # Halaman portal peserta
├── exam.php                  # Ruang pengerjaan ujian online interaktif
├── print.php                 # Cetak sertifikat hasil nilai & QR Code
├── database.sql              # Dump file SQL untuk import manual
└── README.md                 # Dokumentasi aplikasi
```

---

*Dikembangkan secara mandiri (standalone) dari arsitektur CBT SPMB v3 untuk fleksibilitas penerapan yang lebih luas.*