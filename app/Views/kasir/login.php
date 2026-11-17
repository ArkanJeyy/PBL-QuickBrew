<?php
/**
 * View halaman login staf (Kasir & Admin).
 * Desain UI terintegrasi dengan controller autentikasi backend.
 *
 * Variabel dari AuthController::login():
 *   $error     string|null  Pesan kesalahan login
 *   $username  string       Isian username sebelumnya
 *   $redirect  string       Path tujuan balik internal (opsional)
 *   $csrfToken string       Token CSRF sesi
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Staf - <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/css-kasir/login.css')) ?>">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="brand-header">
            <span class="brand-name">QuickBrew</span>
        </div>

        <h2 class="greeting">Selamat Datang!</h2>
        <p class="subtitle">Portal Kasir & Owner</p>

        <?php if (!empty($error)): ?>
            <div role="alert" style="background-color: #fee2e2; color: #b91c1c; border: 1px solid #f87171; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; text-align: center; line-height: 1.4;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= e(url('login')) ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? csrf_token()) ?>">
            <?php if (!empty($redirect)): ?>
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($username ?? '') ?>" placeholder="Masukkan username" maxlength="50" autocomplete="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input-group">
                    <input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
                    <button type="button" class="btn-toggle-password" id="togglePassword" title="Lihat/Sembunyikan Password" aria-label="Lihat password">
                        <!-- Icon Eye Open (Default) -->
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <!-- Icon Eye Closed (Hidden by default) -->
                        <svg id="eyeOffIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="checkbox-container">
                    <input type="checkbox" name="remember"> Ingat saya
                </label>
                <a href="#" class="forgot-link">Lupa password?</a>
            </div>

            <button type="submit" class="btn-primary">Masuk Kasir</button>
        </form>

        <div class="divider">
            <span>atau</span>
        </div>

        <a href="<?= e(url('login')) ?>" class="btn-secondary">Kembali ke Beranda</a>
    </div>
</div>

<script src="<?= e(asset('js/js-kasir/login.js')) ?>"></script>
</body>
</html>
