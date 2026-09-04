<?php
/**
 * simpel_cbt - Ruang Ujian Online (Modern Minimalist Hybrid)
 */
require_once __DIR__ . '/../../../model/config/config.conn.php';
require_once __DIR__ . '/../../../model/helper/cbt.helper.php';

$id_sesi = (int)($_GET['id_sesi'] ?? ($_SESSION['cbt_session_id'] ?? 0));

if ($id_sesi <= 0 || (int)($_SESSION['cbt_session_id'] ?? 0) !== $id_sesi) {
    http_response_code(403);
    header("Location: index.php");
    exit;
}

// Cek sesi
$qSesi = mysqli_query($conn, "SELECT s.*, j.nama_ujian, j.durasi_menit, j.passing_grade, j.tipe_ujian 
                              FROM cbt_sesi s 
                              JOIN cbt_jadwal j ON s.id_jadwal = j.id_jadwal 
                              WHERE s.id_sesi = $id_sesi LIMIT 1");
$sesi = mysqli_fetch_assoc($qSesi);

if (!$sesi) {
    header("Location: index.php");
    exit;
}

if ($sesi['status_sesi'] === 'selesai') {
    header("Location: print.php?id_sesi=" . $id_sesi);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ujian CBT: <?= htmlspecialchars($sesi['nama_ujian']) ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">

    <!-- Icons & Bootstrap 5 -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-subtle: #eef2ff;
            --bg-canvas: #f8fafc;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --border-subtle: #e2e8f0;
            --card-radius: 14px;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            min-height: 100vh;
            user-select: none;
            -webkit-user-select: none;
            margin: 0;
        }

        /* Topbar Header */
        .exam-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border-subtle);
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .brand-icon-sm {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .timer-capsule {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 1px;
            border-radius: 50px;
            padding: 6px 18px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .timer-capsule.timer-normal {
            background-color: #eef2ff;
            color: #4f46e5;
            border: 1px solid #e0e7ff;
        }

        .timer-capsule.timer-warning {
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        .timer-capsule.timer-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            animation: pulseWarn 1s infinite alternate;
        }

        @keyframes pulseWarn {
            0% { transform: scale(1); }
            100% { transform: scale(1.03); }
        }

        /* Card Modern */
        .card-modern {
            background-color: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--card-radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            overflow: hidden;
        }

        .card-header-modern {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
        }

        /* Option Card */
        .choice-card {
            display: flex;
            align-items: flex-start;
            padding: 13px 18px;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            background-color: #ffffff;
            transition: all 0.15s ease;
        }

        .choice-card:hover {
            border-color: #cbd5e1;
            background-color: #f8fafc;
            transform: translateX(2px);
        }

        .choice-card.is-selected {
            border-color: var(--primary);
            background-color: #f5f3ff;
            box-shadow: 0 0 0 1px var(--primary);
        }

        .choice-key {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 14px;
            background: #f1f5f9;
            color: #475569;
            flex-shrink: 0;
            font-size: 0.85rem;
            transition: all 0.15s ease;
        }

        .choice-card.is-selected .choice-key {
            background: var(--primary);
            color: #ffffff;
        }

        .choice-text {
            flex-grow: 1;
            font-size: 0.98rem;
            line-height: 1.55;
            color: var(--text-heading);
            padding-top: 4px;
        }

        /* Palette Grid */
        .palette-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            max-height: 360px;
            overflow-y: auto;
            padding: 2px;
        }

        .btn-q-num {
            height: 40px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.88rem;
            border: 1px solid var(--border-subtle);
            background: #ffffff;
            color: var(--text-body);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-q-num:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-q-num.q-active {
            border: 2px solid var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2) !important;
        }

        .btn-q-num.q-answered {
            background-color: #ecfdf5 !important;
            color: #047857 !important;
            border-color: #a7f3d0 !important;
        }

        .btn-q-num.q-ragu {
            background-color: #fffbeb !important;
            color: #b45309 !important;
            border-color: #fde68a !important;
        }

        .btn-ragu-toggle.is-active {
            background-color: #fffbeb;
            color: #b45309;
            border-color: #fde68a;
        }

        .badge-soft-primary {
            background-color: var(--primary-subtle);
            color: var(--primary);
            border: 1px solid #e0e7ff;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .badge-soft-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid var(--border-subtle);
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
</head>

<body>
    <!-- Topbar Header -->
    <header class="exam-navbar">
        <div class="container-xxl d-flex flex-wrap align-items-center justify-content-between gap-3">
            
            <!-- Left Info -->
            <div class="d-flex align-items-center gap-3">
                <div class="brand-icon-sm">
                    <i class="bx bx-select-multiple"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($sesi['nama_ujian']) ?></h6>
                    <small class="text-muted">
                        <?= htmlspecialchars($sesi['nama_peserta'] ?: $sesi['no_pendaftaran']) ?> • <code><?= htmlspecialchars($sesi['no_pendaftaran']) ?></code>
                    </small>
                </div>
            </div>

            <!-- Timer Capsule -->
            <div class="d-flex align-items-center">
                <div id="timerBox" class="timer-capsule timer-normal">
                    <i class="bx bx-time-five"></i>
                    <span id="timerText">00:00:00</span>
                </div>
            </div>

            <!-- Controls -->
            <div class="d-flex align-items-center gap-2">
                <span id="saveStatus" class="d-none d-sm-inline-flex me-2">
                    <span class="badge-soft-secondary small d-flex align-items-center gap-1">
                        <i class="bx bx-check-double text-success"></i> Tersimpan
                    </span>
                </span>

                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustFont(-1)">A-</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustFont(0)">A</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustFont(1)">A+</button>
                </div>

                <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="confirmFinish()">
                    <i class="bx bx-log-out-circle me-1"></i> Selesai
                </button>
            </div>

        </div>
    </header>

    <!-- Main Exam Container -->
    <main class="container-xxl py-4">
        <div class="row g-4">
            
            <!-- Area Soal (8 Kolom) -->
            <div class="col-lg-8">
                <div class="card-modern">
                    
                    <div class="card-header-modern">
                        <div>
                            <span class="badge-soft-primary fs-6 fw-bold" id="lblNoSoal">Soal Nomor ...</span>
                            <span class="badge-soft-secondary ms-2" id="lblSubtes" style="display: none;">-</span>
                        </div>
                        <div class="small text-muted">
                            Bobot: <strong class="text-dark" id="lblBobot">5 Poin</strong>
                        </div>
                    </div>

                    <div class="p-4">
                        
                        <!-- Gambar Soal -->
                        <div id="imgContainer" class="mb-3 text-center" style="display: none;">
                            <img id="questionImg" src="" alt="Ilustrasi Soal" class="img-fluid rounded border p-1" style="max-height: 260px; cursor: pointer;" onclick="zoomImage(this.src)">
                            <small class="d-block text-muted mt-1"><i class="bx bx-zoom-in"></i> Klik gambar untuk memperbesar</small>
                        </div>

                        <!-- Teks Soal -->
                        <div id="questionText" class="mb-4 fs-5 text-dark fw-normal lh-base">
                            <span class="spinner-border spinner-border-sm me-2"></span> Memuat soal ujian...
                        </div>

                        <!-- Pilihan Ganda -->
                        <div id="choicesContainer"></div>

                    </div>

                    <div class="card-footer bg-light border-top p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" id="btnPrev" onclick="navigateQuestion(-1)" disabled>
                            <i class="bx bx-chevron-left me-1"></i> Sebelumnya
                        </button>

                        <button type="button" class="btn btn-sm btn-outline-warning btn-ragu-toggle px-3" id="btnRagu" onclick="toggleRagu()">
                            <i class="bx bx-flag me-1"></i> Ragu-ragu
                        </button>

                        <button type="button" class="btn btn-sm btn-primary px-4" id="btnNext" onclick="navigateQuestion(1)" style="background-color: var(--primary); border: none;">
                            Selanjutnya <i class="bx bx-chevron-right ms-1"></i>
                        </button>

                        <button type="button" class="btn btn-sm btn-success px-4" id="btnFinish" onclick="confirmFinish()" style="display: none;">
                            Kumpulkan Jawaban <i class="bx bx-check-double ms-1"></i>
                        </button>

                    </div>

                </div>
            </div>

            <!-- Palet Nomor Soal (4 Kolom) -->
            <div class="col-lg-4">
                <div class="card-modern">
                    
                    <div class="card-header-modern">
                        <h6 class="m-0 fw-bold">Nomor Soal</h6>
                        <span class="badge-soft-primary" id="lblTotalProgress">0 / 0</span>
                    </div>

                    <div class="p-3">
                        <div class="palette-grid" id="paletteGrid"></div>

                        <div class="border-top pt-3 mt-3">
                            <div class="row g-2 small">
                                <div class="col-6 d-flex align-items-center gap-2">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; background: #10b981;"></span> Terjawab (<strong id="cntDijawab">0</strong>)
                                </div>
                                <div class="col-6 d-flex align-items-center gap-2">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; background: #f59e0b;"></span> Ragu (<strong id="cntRagu">0</strong>)
                                </div>
                                <div class="col-6 d-flex align-items-center gap-2">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; background: #cbd5e1;"></span> Belum (<strong id="cntKosong">0</strong>)
                                </div>
                                <div class="col-6 d-flex align-items-center gap-2">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; border: 2px solid #4f46e5;"></span> Aktif
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="button" class="btn btn-outline-danger w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2" onclick="confirmFinish()">
                                <i class="bx bx-check-double"></i> Selesaikan Ujian
                            </button>
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- Script CBT Engine -->
    <script>
        const SESSION_ID = <?= $id_sesi ?>;
        let soalData = [];
        let currentIndex = 0;
        let sisaDetik = <?= (int)$sesi['sisa_detik'] ?>;
        let timerInterval = null;
        let pingInterval = null;
        let fontSizeLevel = 0;

        document.addEventListener('DOMContentLoaded', function() {
            loadExamQuestions();
            startTimer();

            pingInterval = setInterval(sendPing, 25000);
            window.addEventListener('keydown', handleKeyboardShortcuts);

            window.addEventListener('beforeunload', function(e) {
                e.preventDefault();
                e.returnValue = 'Ujian Anda sedang berlangsung. Yakin ingin meninggalkan halaman ini?';
            });
        });

        // 1. Load Soal
        function loadExamQuestions() {
            fetch('api/exam_api.php?action=load_soal&id_sesi=' + SESSION_ID)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    soalData = data.soal || [];
                    sisaDetik = data.sisa_detik;
                    if (soalData.length > 0) {
                        renderPalette();
                        renderCurrentQuestion();
                    } else {
                        document.getElementById('questionText').innerHTML = '<div class="alert alert-warning">Belum ada butir soal pada jadwal ujian ini.</div>';
                    }
                } else if (data.status === 'finished') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Ujian Selesai',
                        text: data.msg,
                        confirmButtonText: 'Lihat Hasil Nilai',
                        confirmButtonColor: '#4f46e5'
                    }).then(() => {
                        window.location.href = 'print.php?id_sesi=' + SESSION_ID;
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Perhatian', text: data.msg });
                }
            })
            .catch(err => console.error(err));
        }

        // 2. Render Soal Saat Ini
        function renderCurrentQuestion() {
            if (!soalData || soalData.length === 0) return;
            const q = soalData[currentIndex];

            document.getElementById('lblNoSoal').innerText = 'Soal Nomor ' + (currentIndex + 1) + ' dari ' + soalData.length;
            document.getElementById('lblBobot').innerText = (q.bobot || 5) + ' Poin';

            if (q.nama_subtes && q.nama_subtes !== 'Umum') {
                const badgeSub = document.getElementById('lblSubtes');
                badgeSub.innerText = q.nama_subtes;
                badgeSub.style.display = 'inline-block';
            } else {
                document.getElementById('lblSubtes').style.display = 'none';
            }

            // Gambar
            const imgBox = document.getElementById('imgContainer');
            const imgEl = document.getElementById('questionImg');
            if (q.gambar) {
                imgEl.src = q.gambar;
                imgBox.style.display = 'block';
            } else {
                imgBox.style.display = 'none';
            }

            // Teks Soal
            document.getElementById('questionText').innerHTML = q.pertanyaan;

            // Opsi Jawaban
            const choicesBox = document.getElementById('choicesContainer');
            choicesBox.innerHTML = '';

            const keys = ['A', 'B', 'C', 'D', 'E'];
            keys.forEach(k => {
                if (q.opsi && q.opsi[k]) {
                    const isSelected = (q.jawaban_dipilih === k);
                    const card = document.createElement('div');
                    card.className = 'choice-card' + (isSelected ? ' is-selected' : '');
                    card.onclick = () => selectAnswer(k);

                    card.innerHTML = `
                        <div class="choice-key">${k}</div>
                        <div class="choice-text">${q.opsi[k]}</div>
                    `;
                    choicesBox.appendChild(card);
                }
            });

            // Tombol Ragu
            const btnRagu = document.getElementById('btnRagu');
            if (q.is_ragu == 1) {
                btnRagu.classList.add('is-active');
                btnRagu.innerHTML = '<i class="bx bxs-flag-alt me-1"></i> Ragu-ragu';
            } else {
                btnRagu.classList.remove('is-active');
                btnRagu.innerHTML = '<i class="bx bx-flag me-1"></i> Ragu-ragu';
            }

            // Tombol Navigasi
            document.getElementById('btnPrev').disabled = (currentIndex === 0);
            if (currentIndex === soalData.length - 1) {
                document.getElementById('btnNext').style.display = 'none';
                document.getElementById('btnFinish').style.display = 'inline-flex';
            } else {
                document.getElementById('btnNext').style.display = 'inline-flex';
                document.getElementById('btnFinish').style.display = 'none';
            }

            updatePaletteUI();
        }

        // 3. Render Palet
        function renderPalette() {
            const grid = document.getElementById('paletteGrid');
            grid.innerHTML = '';

            soalData.forEach((q, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'pBtn_' + idx;
                btn.className = 'btn-q-num';
                btn.innerText = idx + 1;
                btn.onclick = () => jumpToQuestion(idx);
                grid.appendChild(btn);
            });
            updatePaletteUI();
        }

        function updatePaletteUI() {
            let dijawab = 0;
            let ragu = 0;
            let kosong = 0;

            soalData.forEach((q, idx) => {
                const btn = document.getElementById('pBtn_' + idx);
                if (!btn) return;

                btn.className = 'btn-q-num';

                if (q.jawaban_dipilih && q.jawaban_dipilih !== '') {
                    dijawab++;
                    btn.classList.add('q-answered');
                } else {
                    kosong++;
                }

                if (q.is_ragu == 1) {
                    ragu++;
                    btn.classList.add('q-ragu');
                }

                if (idx === currentIndex) {
                    btn.classList.add('q-active');
                }
            });

            document.getElementById('cntDijawab').innerText = dijawab;
            document.getElementById('cntRagu').innerText = ragu;
            document.getElementById('cntKosong').innerText = kosong;
            document.getElementById('lblTotalProgress').innerText = dijawab + ' / ' + soalData.length;
        }

        // 4. Pilih Jawaban
        function selectAnswer(k) {
            const q = soalData[currentIndex];
            q.jawaban_dipilih = k;

            const cards = document.querySelectorAll('.choice-card');
            cards.forEach(card => {
                const keyText = card.querySelector('.choice-key').innerText;
                if (keyText === k) {
                    card.classList.add('is-selected');
                } else {
                    card.classList.remove('is-selected');
                }
            });

            updatePaletteUI();
            showSaveIndicator(true);

            const formData = new FormData();
            formData.append('id_sesi', SESSION_ID);
            formData.append('id_soal', q.id_soal);
            formData.append('jawaban', k);

            fetch('api/exam_api.php?action=simpan_jawaban', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(() => showSaveIndicator(false))
            .catch(() => showSaveIndicator(false, true));
        }

        // 5. Toggle Ragu
        function toggleRagu() {
            const q = soalData[currentIndex];
            q.is_ragu = (q.is_ragu == 1) ? 0 : 1;

            const btnRagu = document.getElementById('btnRagu');
            if (q.is_ragu == 1) {
                btnRagu.classList.add('is-active');
                btnRagu.innerHTML = '<i class="bx bxs-flag-alt me-1"></i> Ragu-ragu';
            } else {
                btnRagu.classList.remove('is-active');
                btnRagu.innerHTML = '<i class="bx bx-flag me-1"></i> Ragu-ragu';
            }

            updatePaletteUI();

            const formData = new FormData();
            formData.append('id_sesi', SESSION_ID);
            formData.append('id_soal', q.id_soal);
            formData.append('is_ragu', q.is_ragu);

            fetch('api/exam_api.php?action=set_ragu', {
                method: 'POST',
                body: formData
            });
        }

        // 6. Navigasi
        function navigateQuestion(offset) {
            const newIndex = currentIndex + offset;
            if (newIndex >= 0 && newIndex < soalData.length) {
                currentIndex = newIndex;
                renderCurrentQuestion();
            }
        }

        function jumpToQuestion(idx) {
            if (idx >= 0 && idx < soalData.length) {
                currentIndex = idx;
                renderCurrentQuestion();
            }
        }

        // 7. Timer Countdown
        function startTimer() {
            updateTimerDisplay();
            timerInterval = setInterval(() => {
                sisaDetik--;
                if (sisaDetik <= 0) {
                    clearInterval(timerInterval);
                    sisaDetik = 0;
                    updateTimerDisplay();
                    timeIsUpSubmit();
                } else {
                    updateTimerDisplay();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const h = Math.floor(sisaDetik / 3600);
            const m = Math.floor((sisaDetik % 3600) / 60);
            const s = sisaDetik % 60;

            const formatted = String(h).padStart(2, '0') + ':' + 
                              String(m).padStart(2, '0') + ':' + 
                              String(s).padStart(2, '0');

            document.getElementById('timerText').innerText = formatted;
            const timerBox = document.getElementById('timerBox');

            if (sisaDetik <= 120) {
                timerBox.className = 'timer-capsule timer-danger';
            } else if (sisaDetik <= 600) {
                timerBox.className = 'timer-capsule timer-warning';
            } else {
                timerBox.className = 'timer-capsule timer-normal';
            }
        }

        // 8. Ping Heartbeat
        function sendPing() {
            const fd = new FormData();
            fd.append('id_sesi', SESSION_ID);
            fd.append('sisa_detik', sisaDetik);
            fetch('api/exam_api.php?action=ping', { method: 'POST', body: fd });
        }

        // 9. Indikator Simpan
        function showSaveIndicator(isSaving, isError = false) {
            const el = document.getElementById('saveStatus');
            if (isError) {
                el.innerHTML = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bx bx-error me-1"></i> Gagal Simpan</span>';
            } else if (isSaving) {
                el.innerHTML = '<span class="badge bg-light text-muted border"><i class="bx bx-loader bx-spin me-1"></i> Menyimpan...</span>';
            } else {
                el.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check me-1"></i> Tersimpan</span>';
            }
        }

        // 10. Shortcut Keyboard
        function handleKeyboardShortcuts(e) {
            if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;

            const key = e.key.toUpperCase();
            if (['A', 'B', 'C', 'D', 'E'].includes(key)) {
                selectAnswer(key);
            } else if (e.key === 'ArrowLeft') {
                navigateQuestion(-1);
            } else if (e.key === 'ArrowRight') {
                navigateQuestion(1);
            } else if (key === 'R') {
                toggleRagu();
            }
        }

        // 11. Selesaikan Ujian
        function confirmFinish() {
            let dijawab = 0;
            let ragu = 0;
            let kosong = 0;

            soalData.forEach(q => {
                if (q.jawaban_dipilih && q.jawaban_dipilih !== '') dijawab++;
                else kosong++;
                if (q.is_ragu == 1) ragu++;
            });

            let alertHtml = `
                <div class="text-start py-2">
                    <p class="mb-3 text-muted">Pastikan Anda telah memeriksa seluruh lembar jawaban sebelum mengakhiri sesi ujian ini.</p>
                    <div class="card bg-light border-0 p-3 mb-2 rounded-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bx bx-check-circle text-success me-1"></i> Soal Terjawab:</span>
                            <strong class="text-success">${dijawab} butir</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bx bx-flag text-warning me-1"></i> Soal Ragu-ragu:</span>
                            <strong class="text-warning">${ragu} butir</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="bx bx-x-circle text-danger me-1"></i> Belum Dijawab:</span>
                            <strong class="text-danger">${kosong} butir</strong>
                        </div>
                    </div>
                </div>
            `;

            Swal.fire({
                title: 'Selesaikan Ujian Sekarang?',
                html: alertHtml,
                icon: (kosong > 0 || ragu > 0) ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Selesaikan Ujian',
                cancelButtonText: 'Periksa Lagi',
                confirmButtonColor: '#4f46e5',
                cancelButtonColor: '#64748b'
            }).then(result => {
                if (result.isConfirmed) {
                    submitFinalExam();
                }
            });
        }

        function submitFinalExam() {
            Swal.fire({
                title: 'Menghitung Skor...',
                text: 'Mohon tunggu, lembar jawaban Anda sedang dihitung otomatis.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const fd = new FormData();
            fd.append('id_sesi', SESSION_ID);

            fetch('api/exam_api.php?action=finish', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Ujian Berhasil Diselesaikan!',
                        html: `
                            <div class="text-center py-2">
                                <h1 class="fw-bold mb-1" style="color: #4f46e5;">${data.data.nilai_akhir}</h1>
                                <p class="text-muted small mb-3">Nilai Akhir Ujian (Passing Grade: ${data.data.passing_grade})</p>
                                <span class="badge ${data.data.status_kelulusan === 'LULUS' ? 'bg-success' : 'bg-danger'} px-3 py-2 fs-6">
                                    STATUS: ${data.data.status_kelulusan}
                                </span>
                            </div>
                        `,
                        confirmButtonText: 'Cetak Hasil Nilai',
                        confirmButtonColor: '#4f46e5'
                    }).then(() => {
                        window.location.href = 'print.php?id_sesi=' + SESSION_ID;
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: data.msg });
                }
            })
            .catch(() => {
                Swal.fire({ icon: 'error', title: 'Gangguan Jaringan', text: 'Gagal terhubung ke server untuk menyelesaikan ujian.' });
            });
        }

        function timeIsUpSubmit() {
            Swal.fire({
                icon: 'warning',
                title: 'Waktu Ujian Telah Habis!',
                text: 'Sistem sedang otomatis menyimpan dan mengunci lembar jawaban...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
            setTimeout(() => submitFinalExam(), 1000);
        }

        function adjustFont(direction) {
            const el = document.getElementById('questionText');
            if (direction === 0) {
                fontSizeLevel = 0;
                el.style.fontSize = '1.15rem';
            } else {
                fontSizeLevel += direction;
                if (fontSizeLevel > 3) fontSizeLevel = 3;
                if (fontSizeLevel < -2) fontSizeLevel = -2;
                el.style.fontSize = (1.15 + (fontSizeLevel * 0.12)) + 'rem';
            }
        }

        function zoomImage(src) {
            Swal.fire({
                imageUrl: src,
                imageAlt: 'Ilustrasi Soal',
                showConfirmButton: false,
                showCloseButton: true,
                width: '75%'
            });
        }
    </script>
</body>
</html>
