<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Akun - QuickBrew</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/css-kasir/dashboard.css')) ?>">

    <!-- Tailwind CDN dengan prefix tw- -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            prefix: 'tw-',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#5A3E2B',
                            hover:   '#452E1E',
                            light:   '#F4ECE1',
                        },
                        page:    '#FBF8F3',
                        card:    '#FFFFFF',
                        border:  '#EFE8DF',
                        muted:   '#8C7E74',
                        main:    '#2D231C',
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                },
            },
            corePlugins: {
                preflight: false,
            },
        }
    </script>
</head>
<body>

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="kasir-sidebar">
        <div class="sidebar-brand">
            <img src="<?= e(asset('images/logo.png')) ?>" alt="QuickBrew Logo" class="brand-logo-img">
            <span class="brand-title">QuickBrew</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= e(url('kasir')) ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="<?= e(url('kasir-pesanan')) ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Pesanan</span>
            </a>
            <a href="<?= htmlspecialchars(url('kasir-menu'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                    <path d="M7 2v20"></path>
                    <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                </svg>
                <span>Menu</span>
            </a>
            <a href="#" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
                <span>Stok</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <form action="<?= e(url('logout')) ?>" method="POST" style="margin: 0; width: 100%;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="nav-link logout-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <main class="kasir-main">

        <!-- ===== Top Header ===== -->
        <header class="top-header">
            <div class="top-header-left">
                <h1>Profil Akun</h1>
                <p>Kelola informasi pribadi dan keamanan akun Anda.</p>
            </div>
            <div class="top-header-right">
                <div class="header-top-row">
                    <button type="button" class="btn-icon-bell" title="Notifikasi">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span class="bell-badge"></span>
                    </button>

                    <!-- Profil Kasir dengan Dropdown -->
                    <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                        <button type="button" class="profile-card" id="profileDropdownBtn" aria-expanded="false" title="Menu Profil Kasir">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($user['nama'] ?? 'J', 0, 1)) ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?= e($user['nama'] ?? 'Jihan Amanda') ?></span>
                                <span class="profile-role">Kasir - Profil Akun</span>
                            </div>
                            <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8C7E74" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <!-- Menu Dropdown Kasir -->
                        <div class="profile-dropdown-menu" id="profileDropdownMenu">
                            <a href="<?= e(url('kasir-profil')) ?>" class="dropdown-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span>Profil Akun</span>
                            </a>
                            <form action="<?= e(url('logout')) ?>" method="POST" style="margin: 0; width: 100%;">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit" class="dropdown-item logout-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="19" y1="12" x2="5" y2="12"></line>
                                        <polyline points="12 19 5 12 12 5"></polyline>
                                    </svg>
                                    <span>Keluar</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ===== Card 1: Informasi Profil Akun ===== -->
        <section class="profile-section-card">
            <div class="profile-card-header">
                <h2>Profil Akun</h2>
                <p>Kelola informasi profil akun anda.</p>
            </div>

            <!-- Bagian Foto Profil -->
            <div class="profile-photo-row">
                <div class="profile-avatar-large">
                    <?= strtoupper(substr($user['nama'] ?? 'J', 0, 1)) ?>
                </div>
                <button type="button" class="btn-change-photo">Ubah Foto</button>
            </div>

            <!-- Form Edit Profil -->
            <form action="#" method="POST" class="profile-form">
                <!-- Baris 1: 2 Kolom (Nama Lengkap & No Telepon) -->
                <div class="profile-form-grid-2">
                    <div class="form-group-item">
                        <label for="inputNama" class="profile-field-label">Nama Lengkap</label>
                        <input type="text" id="inputNama" name="nama" class="profile-field-input" value="<?= e($user['nama'] ?? 'Jihan Amanda') ?>" required>
                    </div>
                    <div class="form-group-item">
                        <label for="inputTelepon" class="profile-field-label">Nomor Telepon</label>
                        <input type="text" id="inputTelepon" name="telepon" class="profile-field-input" value="0858-0768-4575" placeholder="08xx-xxxx-xxxx">
                    </div>
                </div>

                <!-- Baris 2: Email (Readonly) -->
                <div class="form-group-item">
                    <label for="inputEmail" class="profile-field-label">Email</label>
                    <input type="email" id="inputEmail" name="email" class="profile-field-input profile-input-readonly" value="<?= e($user['email'] ?? 'amanda@home.quickbrew.id') ?>" readonly>
                </div>

                <!-- Baris 3: Peran (Readonly) -->
                <div class="form-group-item">
                    <label for="inputPeran" class="profile-field-label">Peran</label>
                    <input type="text" id="inputPeran" name="peran" class="profile-field-input profile-input-readonly" value="<?= e(ucfirst($user['role'] ?? 'Kasir')) ?>" readonly>
                </div>

                <!-- Tombol Simpan Perubahan (Kanan Bawah) -->
                <div class="profile-form-actions">
                    <button type="button" class="btn-save-profile">Simpan Perubahan</button>
                </div>
            </form>
        </section>

        <!-- ===== Card 2: Pengaturan Password ===== -->
        <section class="profile-section-card password-card-row">
            <div class="password-card-info">
                <h2>Ubah Password</h2>
                <p>Atur password akun Anda.</p>
            </div>
            <button type="button" class="btn-open-password-modal" id="btnOpenPasswordModal">Ganti Password</button>
        </section>

    </main>

    <!-- ==================== MODAL POPUP: UBAH PASSWORD ==================== -->
    <div class="modal-overlay" id="passwordModalOverlay">
        <div class="password-modal-box">
            <div class="password-modal-header">
                <h3>Ubah Password</h3>
                <p>Masukkan password saat ini, lalu password baru.</p>
            </div>

            <form action="#" method="POST" id="formChangePassword">
                <div class="modal-form-group">
                    <label for="currentPassword" class="modal-field-label">Password Saat Ini</label>
                    <input type="password" id="currentPassword" name="current_password" class="modal-field-input" placeholder="••••••" required>
                </div>

                <div class="modal-form-group">
                    <label for="newPassword" class="modal-field-label">Password Baru</label>
                    <input type="password" id="newPassword" name="new_password" class="modal-field-input" placeholder="••••••••" minlength="8" required>
                    <span class="modal-field-help">Minimal 8 karakter</span>
                </div>

                <div class="modal-form-group">
                    <label for="confirmPassword" class="modal-field-label">Konfirmasi Password Baru</label>
                    <input type="password" id="confirmPassword" name="confirm_password" class="modal-field-input" placeholder="••••••••" minlength="8" required>
                </div>

                <div class="password-modal-actions">
                    <button type="button" class="btn-modal-cancel-pill" id="btnCancelPasswordModal">Batal</button>
                    <button type="button" class="btn-modal-save-pill" id="btnSavePassword">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Interaktif Profil & Modal -->
    <script src="<?= e(asset('js/js-kasir/profil.js')) ?>"></script>

</body>
</html>
