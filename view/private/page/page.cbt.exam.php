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
        #proctorPreview{position:fixed;right:16px;bottom:16px;width:150px;background:#0f172a;border:2px solid #6366f1;border-radius:12px;overflow:hidden;z-index:1050;box-shadow:0 10px 30px rgba(15,23,42,.3)}#proctorPreview span{display:flex;align-items:center;gap:5px;padding:5px 8px;color:#fff;font-size:10px;font-weight:600}#proctorPreview video{display:block;width:100%;height:90px;object-fit:cover;transform:scaleX(-1)}@media(max-width:768px){#proctorPreview{width:105px;right:8px;bottom:8px}#proctorPreview video{height:64px}}
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
        const EXAM_TYPE = <?= json_encode($sesi['tipe_ujian']) ?>;
        let soalData = [];
        let currentIndex = 0;
        let sisaDetik = <?= (int)$sesi['sisa_detik'] ?>;
        let timerInterval = null;
        let pingInterval = null;
        let fontSizeLevel = 0;
        let sessionSuspended = false;
        const pendingSaves = new Set();
        let cameraStream=null,screenStream=null,captureInterval=null,cameraVideo=null,screenVideo=null;
        const OFFLINE_KEY = 'simpel_cbt_queue_' + SESSION_ID;

        function queueOffline(action, payload) {
            const queue = JSON.parse(localStorage.getItem(OFFLINE_KEY) || '[]');
            const filtered = queue.filter(x => !(x.action === action && x.payload.id_soal === payload.id_soal));
            filtered.push({action, payload, queued_at: Date.now()});
            localStorage.setItem(OFFLINE_KEY, JSON.stringify(filtered));
            showSaveIndicator(false, true);
        }
        async function flushOfflineQueue() {
            const queue = JSON.parse(localStorage.getItem(OFFLINE_KEY) || '[]');
            if (!queue.length || !navigator.onLine) return;
            const remaining = [];
            for (const item of queue) {
                try {
                    const fd = new FormData(); Object.entries(item.payload).forEach(([k,v]) => fd.append(k,v));
                    const res = await fetch('api/exam_api.php?action=' + item.action, {method:'POST',body:fd});
                    const data = await res.json(); if (!res.ok || data.status !== 'success') throw new Error();
                } catch (e) { remaining.push(item); }
            }
            localStorage.setItem(OFFLINE_KEY, JSON.stringify(remaining));
            if (!remaining.length) showSaveIndicator(false);
        }
        function logViolation(type, detail='') {
            if(sessionSuspended)return;
            const fd=new FormData();fd.append('id_sesi',SESSION_ID);fd.append('type',type);fd.append('detail',detail);
            fetch('api/exam_api.php?action=log_violation',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
                if(data.suspended){sessionSuspended=true;clearInterval(timerInterval);clearInterval(pingInterval);stopProctoring();Swal.fire({icon:'error',title:'Ujian Ditangguhkan',html:'Anda telah mencapai <b>4 pelanggaran</b>. Jawaban tetap tersimpan, tetapi ujian dikunci sementara.<br><br>Silakan hubungi pengawas.',allowOutsideClick:false,allowEscapeKey:false,showConfirmButton:false});}
                else if(data.count>0) Swal.fire({toast:true,position:'top-end',icon:'warning',title:`Pelanggaran ${data.count} dari ${data.limit}`,showConfirmButton:false,timer:2200});
            }).catch(()=>{});
        }
        async function requestScreenShare() {
            if (!navigator.mediaDevices?.getDisplayMedia) {
                await Swal.fire({icon:'error',title:'Browser Tidak Mendukung Berbagi Layar',text:'Gunakan Chrome, Edge, atau browser modern lain agar pengawasan layar dapat diaktifkan.',confirmButtonText:'Mengerti'});
                return false;
            }
            const result = await Swal.fire({
                icon:'info',
                title:'Aktifkan Berbagi Layar',
                html:'<div class="text-start"><p>Klik tombol di bawah, pilih <b>seluruh layar</b>, lalu tekan <b>Bagikan</b>.</p><p class="mb-0 text-muted">Dialog pemilihan layar berasal langsung dari browser dan harus dibuka melalui klik Anda.</p></div>',
                confirmButtonText:'Pilih Layar & Bagikan',
                showCancelButton:true,
                cancelButtonText:'Lanjut Tanpa Berbagi',
                allowOutsideClick:false,
                allowEscapeKey:false,
                preConfirm: async () => {
                    try {
                        return await navigator.mediaDevices.getDisplayMedia({video:{displaySurface:'monitor'},audio:false});
                    } catch (error) {
                        const message = error?.name === 'NotAllowedError'
                            ? 'Berbagi layar belum diizinkan. Pilih layar lalu tekan Bagikan, atau gunakan tombol batal jika memang ingin menolak.'
                            : 'Dialog berbagi layar gagal dibuka. Silakan coba lagi.';
                        Swal.showValidationMessage(message);
                        return false;
                    }
                }
            });
            if (!result.isConfirmed) {
                logViolation('screen_denied','Peserta memilih melanjutkan tanpa berbagi layar');
                return false;
            }
            screenStream=result.value;
            screenVideo=document.createElement('video');screenVideo.srcObject=screenStream;screenVideo.muted=true;screenVideo.playsInline=true;await screenVideo.play();
            screenStream.getVideoTracks()[0]?.addEventListener('ended',()=>logViolation('screen_share_stopped','Berbagi layar dihentikan'));
            return true;
        }
        async function startSecureMode() {
            try { if(!document.fullscreenElement) await document.documentElement.requestFullscreen(); } catch(e) {}
            if(navigator.mediaDevices?.getUserMedia){try{cameraStream=await navigator.mediaDevices.getUserMedia({video:{width:{ideal:640},height:{ideal:360}},audio:false});cameraVideo=document.createElement('video');cameraVideo.srcObject=cameraStream;cameraVideo.muted=true;cameraVideo.playsInline=true;await cameraVideo.play();const preview=document.createElement('div');preview.id='proctorPreview';preview.innerHTML='<span><i class="bx bx-video-recording"></i> Proctor aktif</span>';preview.appendChild(cameraVideo);document.body.appendChild(preview);}catch(e){logViolation('camera_denied','Izin kamera ditolak');}}
            else logViolation('camera_unavailable','Kamera tidak didukung browser');
            captureProctorFrames();captureInterval=setInterval(captureProctorFrames,30000);
        }
        function uploadFrame(video,type){if(!video||video.readyState<2)return;const canvas=document.createElement('canvas');const maxWidth=640;const ratio=Math.min(1,maxWidth/video.videoWidth);canvas.width=Math.max(1,Math.round(video.videoWidth*ratio));canvas.height=Math.max(1,Math.round(video.videoHeight*ratio));canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);const fd=new FormData();fd.append('id_sesi',SESSION_ID);fd.append('media_type',type);fd.append('image_data',canvas.toDataURL('image/jpeg',.68));fetch('api/exam_api.php?action=upload_proctor_frame',{method:'POST',body:fd}).catch(()=>{});}
        function captureProctorFrames(){uploadFrame(cameraVideo,'camera');uploadFrame(screenVideo,'screen');}
        function stopProctoring(){clearInterval(captureInterval);cameraStream?.getTracks().forEach(t=>t.stop());screenStream?.getTracks().forEach(t=>t.stop());}
        function installSecurityGuards(){
            document.addEventListener('visibilitychange',()=>{if(document.hidden)logViolation('tab_hidden','Peserta berpindah tab atau meminimalkan browser');});
            document.addEventListener('fullscreenchange',()=>{if(!document.fullscreenElement)logViolation('fullscreen_exit','Keluar dari mode layar penuh');});
            document.addEventListener('copy',e=>{e.preventDefault();logViolation('copy','Percobaan menyalin konten');});
            document.addEventListener('paste',e=>{e.preventDefault();logViolation('paste','Percobaan menempel konten');});
            document.addEventListener('contextmenu',e=>{e.preventDefault();logViolation('context_menu','Klik kanan diblokir');});
            window.addEventListener('keyup',e=>{if(e.key==='PrintScreen'){navigator.clipboard?.writeText('');logViolation('print_screen','Tombol PrintScreen terdeteksi');}});
        }

        document.addEventListener('DOMContentLoaded', function() {
            window.addEventListener('keydown', handleKeyboardShortcuts);
            window.addEventListener('online', flushOfflineQueue);
            window.addEventListener('offline', () => showSaveIndicator(false, true));
            Swal.fire({icon:'warning',title:'Peraturan Ujian & Persetujuan Proctoring',width:650,html:'<div class="text-start"><p>Ujian menggunakan <b>kamera dan berbagi layar</b>. Snapshot kamera serta layar diambil berkala selama sesi dan hanya dapat dilihat admin/pengawas.</p><p>Aktivitas berikut dicatat sebagai pelanggaran:</p><ul><li>Berpindah tab atau meminimalkan browser</li><li>Keluar layar penuh atau menghentikan berbagi layar</li><li>Copy, paste, klik kanan, atau PrintScreen</li><li>Menolak izin kamera/berbagi layar</li></ul><div class="alert alert-danger mb-0"><b>Maksimal 4 pelanggaran.</b> Pelanggaran ke-4 menangguhkan ujian sampai dibuka pengawas.</div></div>',confirmButtonText:'Saya Setuju, Aktifkan & Mulai',allowOutsideClick:false,allowEscapeKey:false}).then(async()=>{await requestScreenShare();await startSecureMode();installSecurityGuards();loadExamQuestions();startTimer();pingInterval=setInterval(sendPing,25000);flushOfflineQueue();});

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
                } else if(data.status==='suspended'){
                    sessionSuspended=true;clearInterval(timerInterval);Swal.fire({icon:'error',title:'Ujian Ditangguhkan',text:data.msg,allowOutsideClick:false,allowEscapeKey:false,showConfirmButton:false});
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

            const saveRequest = fetch('api/exam_api.php?action=simpan_jawaban', {
                method: 'POST',
                body: formData
            })
            .then(async res => {const data=await res.json();if(!res.ok||data.status!=='success')throw new Error(data.msg||'Gagal menyimpan');return data;})
            .then(() => showSaveIndicator(false))
            .catch(() => queueOffline('simpan_jawaban', {id_sesi:SESSION_ID,id_soal:q.id_soal,jawaban:k}))
            .finally(()=>pendingSaves.delete(saveRequest));
            pendingSaves.add(saveRequest);
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
            }).catch(() => queueOffline('set_ragu', {id_sesi:SESSION_ID,id_soal:q.id_soal,is_ragu:q.is_ragu}));
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

        async function submitFinalExam() {
            if(pendingSaves.size){showSaveIndicator(true);await Promise.allSettled([...pendingSaves]);}
            await flushOfflineQueue();
            if (EXAM_TYPE === 'multi_subtes') { advanceSubtest(); return; }
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
                    stopProctoring();
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

        function advanceSubtest() {
            const fd=new FormData();fd.append('id_sesi',SESSION_ID);
            Swal.fire({title:'Menyelesaikan Subtes...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
            fetch('api/exam_api.php?action=next_subtes',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
                if(data.status==='next'){Swal.fire({icon:'info',title:'Subtes Berikutnya',text:data.msg,confirmButtonText:'Mulai'}).then(()=>location.reload());}
                else if(data.status==='finished'){stopProctoring();localStorage.removeItem(OFFLINE_KEY);Swal.fire({icon:'success',title:'Seluruh Ujian Selesai',text:'Jawaban Anda telah dikunci.',confirmButtonText:'Lihat Hasil'}).then(()=>location.href='print.php?id_sesi='+SESSION_ID);}
                else Swal.fire('Gagal',data.msg||'Tidak dapat berpindah subtes.','error');
            }).catch(()=>Swal.fire('Gangguan Jaringan','Coba kembali saat koneksi pulih.','error'));
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
