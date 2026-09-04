<?php
/**
 * simpel_cbt - Login Pusat CBT (Modern Minimalist Hybrid)
 */
require_once __DIR__ . '/../../../model/config/config.conn.php';
require_once __DIR__ . '/../../../model/helper/auth.helper.php';

// Jika sudah login sebagai admin, langsung ke index
if (is_admin_logged_in()) {
    $current = get_logged_admin();
    if ($current['role'] === 'admin') {
        header("Location: index.php?m=pusat");
        exit;
    }
}

$errorMsg = '';
$isHtmlError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginCsrf = (string)($_POST['_csrf'] ?? '');
    if ($loginCsrf === '' || !hash_equals(csrf_token(), $loginCsrf)) {
        $errorMsg = 'Sesi keamanan kedaluwarsa. Silakan muat ulang halaman.';
    }
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($errorMsg)) {
        // Pesan validasi CSRF sudah disiapkan.
    } elseif (empty($username) || empty($password)) {
        $errorMsg = 'Username dan Password wajib diisi!';
    } else {
        $uEsc = mysqli_real_escape_string($conn, $username);
        $q = mysqli_query($conn, "SELECT * FROM cbt_admin WHERE username = '$uEsc' LIMIT 1");
        $admin = mysqli_fetch_assoc($q);

        $loginKey = hash('sha256', strtolower($username) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $attempt = $_SESSION['login_attempts'][$loginKey] ?? ['count' => 0, 'since' => time()];
        if (time() - (int)$attempt['since'] > 900) {
            $attempt = ['count' => 0, 'since' => time()];
        }
        if ((int)$attempt['count'] >= 5) {
            $errorMsg = 'Terlalu banyak percobaan login. Coba kembali dalam 15 menit.';
        } elseif ($admin && password_verify($password, $admin['password'])) {
            unset($_SESSION['login_attempts'][$loginKey]);
            session_regenerate_id(true);
            $_SESSION['simpel_cbt_admin_id']   = $admin['id_admin'];
            $_SESSION['simpel_cbt_admin_user'] = $admin['username'];
            $_SESSION['simpel_cbt_admin_nama'] = $admin['nama_lengkap'];
            $_SESSION['simpel_cbt_admin_role'] = $admin['role'];

            // Otomatis arahkan ke dashboard pusat jika admin, atau ke console jika pengawas
            if ($admin['role'] === 'admin') {
                header("Location: index.php?m=pusat");
            } else {
                header("Location: index.php?m=pusat#monitoring");
            }
            exit;
        } else {
            $attempt['count']++;
            $_SESSION['login_attempts'][$loginKey] = $attempt;
            $errorMsg = 'Username atau password yang Anda masukkan salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Login Pusat CBT - SIMPEL CBT</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Icons & Bootstrap 5 -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
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
        }

        .brand-logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
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

        .role-badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            background-color: #f1f5f9;
            color: #475569;
            margin-top: 3px;
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

        .btn-modern {
            width: 100%;
            height: 48px;
            background-color: #1e293b;
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
            box-shadow: 0 2px 4px rgba(30, 41, 59, 0.2);
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .btn-modern:hover {
            background-color: #0f172a;
            box-shadow: 0 4px 10px rgba(30, 41, 59, 0.3);
            color: #ffffff;
        }

        .btn-modern:active {
            transform: scale(0.99);
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
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .footer-note a {
            color: var(--text-muted);
            font-size: 0.82rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
        
        <div class="brand-logo-wrap">
            <div class="brand-icon">
                <i class="bx bx-shield-quarter"></i>
            </div>
            <div>
                <h1 class="brand-title">SIMPEL <span>CBT</span></h1>
                <div class="role-badge-tag">
                    <i class="bx bx-building-house"></i> Portal Administrator Pusat
                </div>
            </div>
        </div>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-start gap-2" role="alert">
                <i class="bx bx-error-circle fs-5 mt-1 flex-shrink-0"></i>
                <div class="lh-sm">
                    <?= $isHtmlError ? $errorMsg : htmlspecialchars($errorMsg) ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php?m=login-pusat" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="mb-3">
                <label class="form-label" for="username">Username Petugas / Pengawas</label>
                <div class="input-group-modern">
                    <input
                        type="text"
                        class="form-control-modern"
                        id="username"
                        name="username"
                        placeholder="Username admin atau pengawas..."
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required
                        autofocus
                    />
                    <i class="bx bx-user input-icon"></i>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="input-group-modern">
                    <input
                        type="password"
                        class="form-control-modern"
                        id="password"
                        name="password"
                        placeholder="••••••••••••"
                        required
                        style="padding-right: 42px;"
                    />
                    <i class="bx bx-lock-alt input-icon"></i>
                    <button type="button" class="btn-eye-toggle" id="btnTogglePwd" title="Lihat password">
                        <i class="bx bx-hide" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-modern">
                <i class="bx bx-log-in fs-5"></i>
                <span>Masuk ke Sistem CBT</span>
            </button>

            <div class="footer-note">
                
                <a href="index.php">
                    <i class="bx bx-arrow-back"></i> Kembali ke Halaman Ujian Peserta
                </a>
            </div>

        </form>

    </div>

    <script>
        const btnTogglePwd = document.getElementById('btnTogglePwd');
        if (btnTogglePwd) {
            btnTogglePwd.addEventListener('click', function() {
                const input = document.getElementById('password');
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
    </script>
</body>
</html>
