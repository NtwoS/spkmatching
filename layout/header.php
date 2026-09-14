<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config/app.php';

$is_admin = (string) ($_SESSION['level'] ?? '') === '1';

if (isset($_POST['simpan_pengaturan'])) {
    if (!$is_admin) {
        http_response_code(403);
        exit('Akses ditolak.');
    }

    $hasil_pengaturan = validasi_pengaturan_csrf_token($_POST['csrf_token'] ?? '')
        ? update_pengaturan_pendaftaran($_POST)
        : [
            'success' => false,
            'message' => 'Permintaan pengaturan tidak valid. Silakan coba lagi.'
        ];

    $_SESSION['pengaturan_flash'] = $hasil_pengaturan['message'];
    header('Location: home.php');
    exit;
}

$pengaturan_flash = $_SESSION['pengaturan_flash'] ?? null;
unset($_SESSION['pengaturan_flash']);

$pengaturan_pendaftaran = $is_admin ? get_pengaturan_pendaftaran() : null;
$pengaturan_pendaftaran ??= [
    'status_pendaftaran' => 'buka',
    'nis_min' => '0',
    'nis_max' => '999999999999999999999999999999',
    'hanya_terkalkulasi' => 0
];

// Get only the logged-in user's data
$id_akun = (int) ($_SESSION['id_akun'] ?? 0);
$data_akun = $id_akun > 0 ? select("SELECT * FROM akun WHERE id_akun = {$id_akun}") : [];
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Pendukung Keputusan</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css">
    
    <!-- Custom Output CSS -->
    <link rel="stylesheet" type="text/css" href="css/style.css">
    
    <link rel="icon" href="img/smk.jpg">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #334155;
        }

        /* Navbar Modernization */
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 0.8rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .navbar-brand {
            font-size: 1.25rem;
            font-weight: 800;
            color: #4361ee !important;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-nav .nav-link {
            font-size: 0.95rem;
            font-weight: 600;
            color: #64748b !important;
            padding: 0.6rem 1rem;
            margin: 0 0.2rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .navbar-nav .nav-link:hover, 
        .navbar-nav .nav-link.active {
            color: #4361ee !important;
            background-color: rgba(67, 97, 238, 0.05);
        }

        /* Dropdown Modernization */
        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 0.5rem;
            margin-top: 5px;
        }

        .dropdown-item {
            color: #475569;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(67, 97, 238, 0.08);
            color: #4361ee;
        }

        /* Profile Button in Navbar */
        .nav-profile-btn {
            background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);
            color: #ffffff !important;
            border-radius: 50px;
            padding: 0.5rem 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 10px rgba(67, 97, 238, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .nav-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
            color: #ffffff !important;
        }
        
        .nav-profile-btn i {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 0.8rem;
        }

        .nav-setting-btn {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            color: #4361ee;
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .nav-setting-btn:hover {
            background-color: #eef2ff;
            color: #3f37c9;
            transform: translateY(-2px);
        }

        .setting-panel {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1rem;
        }

        .setting-panel .form-check-input:checked {
            background-color: #4361ee;
            border-color: #4361ee;
        }

        /* Modal Profile Modernization */
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);
            color: white;
            border-bottom: none;
            padding: 1.5rem;
            position: relative;
        }

        .modal-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            right: -20px;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: 0.5px;
            z-index: 1;
        }

        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            z-index: 1;
            opacity: 0.8;
        }

        .profile-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .profile-list li {
            padding: 1rem 0;
            border-bottom: 1px dashed #e2e8f0;
            display: flex;
            align-items: center;
        }

        .profile-list li:last-child {
            border-bottom: none;
        }

        .profile-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background-color: rgba(67, 97, 238, 0.08);
            color: #4361ee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 1.2rem;
            flex-shrink: 0;
        }

        .profile-label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 0.1rem;
            letter-spacing: 0.5px;
        }

        .profile-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .modal-footer {
            border-top: 1px solid #f8fafc;
            padding: 1.25rem;
            background-color: #fdfdfd;
        }

        .btn-modern {
            border-radius: 10px;
            font-weight: 600;
            padding: 0.6rem 1.25rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-modern i {
            font-size: 0.9rem;
        }

        .navbar-toggler {
            border: none;
            padding: 0.5rem;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
            background: rgba(67, 97, 238, 0.05);
            border-radius: 8px;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2867, 97, 238, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Generic Table Overrides to Match Overall Design */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin: 20px 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .table th,
        .table td {
            padding: 1rem 1.25rem;
            border: none;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .table thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
    </style>
</head>

<body>
    <!-- Start Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-lg-none" href="home.php">
                <i class="fas fa-layer-group"></i> SPK MATCHING
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($_SESSION['level'] != 2): ?>
                        <li class="nav-item">
                            <a class="nav-link" aria-current="page" href="home.php">
                                <i class="fas fa-home me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" aria-current="page" href="data-akun.php">
                                <i class="fas fa-users me-1"></i> Akun
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" aria-current="page" href="perusahaan.php">
                                <i class="fas fa-building me-1"></i> Perusahaan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" aria-current="page" href="data-soal.php">
                                <i class="fas fa-file-alt me-1"></i> Bank Soal
                            </a>
                        </li>
                    <?php endif; ?>
                 
                    <?php if ($_SESSION['level'] != 2): ?>
                        <li class="nav-item">
                            <a class="nav-link" aria-current="page" href="data-siswa.php">
                                <i class="fas fa-user-graduate me-1"></i> Data Siswa
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['gap.php', 'pembobotan.php', 'factor.php', 'total.php', 'ranking.php']) ? 'active' : ''; ?>" href="#" id="matchingDropdown" 
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-project-diagram me-1"></i> Matching
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="matchingDropdown">
                                <li><a class="dropdown-item" href="gap.php"><i class="fas fa-arrows-alt-h me-2 text-muted"></i> GAP</a></li>
                                <li><a class="dropdown-item" href="pembobotan.php"><i class="fas fa-balance-scale me-2 text-muted"></i> Pembobotan</a></li>
                                <li><a class="dropdown-item" href="factor.php"><i class="fas fa-layer-group me-2 text-muted"></i> Factor</a></li>
                                <li><a class="dropdown-item" href="total.php"><i class="fas fa-calculator me-2 text-muted"></i> Total</a></li>
                                <li><a class="dropdown-item" href="ranking.php"><i class="fas fa-trophy me-2 text-muted"></i> Ranking</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'penempatan.php' ? 'active' : ''; ?>" aria-current="page" href="penempatan.php">
                                <i class="fas fa-map-marked-alt me-1"></i> Penempatan
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-none d-lg-flex ms-auto align-items-center gap-2">
                    <a class="nav-profile-btn" data-bs-toggle="modal" data-bs-target="#profileModal" href="#">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($_SESSION['nama'] ?? '') ?>
                    </a>
                    <?php if ($is_admin): ?>
                        <button type="button" class="btn btn-light rounded-circle shadow-sm nav-setting-btn" data-bs-toggle="modal" data-bs-target="#settingModal" title="Pengaturan Pendaftaran" aria-label="Pengaturan Pendaftaran">
                            <i class="fas fa-cog"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="d-lg-none mt-3 mt-lg-0 w-100 collapse" id="profileCollapse">
                <div class="d-flex align-items-center gap-2 mt-3">
                    <a class="nav-profile-btn flex-grow-1 justify-content-center" data-bs-toggle="modal" data-bs-target="#profileModal" href="#">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($_SESSION['nama'] ?? '') ?>
                    </a>
                    <?php if ($is_admin): ?>
                        <button type="button" class="btn btn-light rounded-circle shadow-sm nav-setting-btn flex-shrink-0" data-bs-toggle="modal" data-bs-target="#settingModal" title="Pengaturan Pendaftaran" aria-label="Pengaturan Pendaftaran">
                            <i class="fas fa-cog"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <!-- End Navbar -->

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header align-items-center">
                    <h5 class="modal-title m-0" id="profileModalLabel"><i class="fas fa-id-card me-2"></i> Profil Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if (!empty($data_akun)):
                        $akun = $data_akun[0]; ?>
                        <ul class="profile-list">
                            <?php if ($_SESSION['level'] != 1): ?>
                                <li>
                                    <div class="profile-icon"><i class="fas fa-id-badge"></i></div>
                                    <div>
                                        <span class="profile-label">NIS</span>
                                        <p class="profile-value"><?= htmlspecialchars($akun['nis'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </li>
                            <?php endif; ?>
                            <li>
                                <div class="profile-icon"><i class="fas fa-user"></i></div>
                                <div>
                                    <span class="profile-label">Nama Lengkap</span>
                                    <p class="profile-value"><?= htmlspecialchars($akun['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </li>
                            <li>
                                <div class="profile-icon"><i class="fas fa-at"></i></div>
                                <div>
                                    <span class="profile-label">Username</span>
                                    <p class="profile-value"><?= htmlspecialchars($akun['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </li>
                            <li>
                                <div class="profile-icon"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <span class="profile-label">Email</span>
                                    <p class="profile-value"><?= htmlspecialchars($akun['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </li>
                            <li>
                                <div class="profile-icon"><i class="fas fa-shield-alt"></i></div>
                                <div>
                                    <span class="profile-label">Level Akses</span>
                                    <p class="profile-value">
                                        <?php if(($_SESSION['level'] ?? 0) == 1): ?>
                                            <span class="badge bg-primary px-3 py-2 rounded-pill">Administrator</span>
                                        <?php else: ?>
                                            <span class="badge bg-success px-3 py-2 rounded-pill">Siswa PKL</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </li>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-warning border-0 rounded-3 mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i> Data akun tidak ditemukan.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-light btn-modern text-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Tutup
                    </button>
                    <a href="logout.php" class="btn btn-danger btn-modern" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_admin): ?>
        <!-- Registration Settings Modal -->
        <div class="modal fade" id="settingModal" tabindex="-1" aria-labelledby="settingModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header align-items-center">
                        <h5 class="modal-title m-0" id="settingModalLabel"><i class="fas fa-cog me-2"></i> Pengaturan Pendaftaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="home.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_pengaturan_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="modal-body p-4">
                            <div class="setting-panel mb-4">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <span class="profile-label">Status Pendaftaran</span>
                                        <p class="mb-0 text-secondary small">Tentukan apakah siswa dapat membuat akun baru.</p>
                                    </div>
                                    <div class="form-check form-switch flex-shrink-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="statusPendaftaran" name="status_pendaftaran" value="buka"
                                            <?= ($pengaturan_pendaftaran['status_pendaftaran'] ?? 'buka') === 'buka' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="statusPendaftaran" id="statusPendaftaranLabel">
                                            <?= ($pengaturan_pendaftaran['status_pendaftaran'] ?? 'buka') === 'buka' ? 'Buka' : 'Tutup' ?>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark">Rentang NIS yang Diizinkan</label>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="nisMin" class="form-label small text-secondary">NIS Awal (Mulai Dari)</label>
                                        <input type="text" class="form-control" id="nisMin" name="nis_min" inputmode="numeric" pattern="[0-9]{1,30}" maxlength="30" required
                                            value="<?= htmlspecialchars((string) ($pengaturan_pendaftaran['nis_min'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="nisMax" class="form-label small text-secondary">NIS Akhir (Sampai Dengan)</label>
                                        <input type="text" class="form-control" id="nisMax" name="nis_max" inputmode="numeric" pattern="[0-9]{1,30}" maxlength="30" required
                                            value="<?= htmlspecialchars((string) ($pengaturan_pendaftaran['nis_max'] ?? '999999999999999999999999999999'), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>
                                <div class="form-text">Contoh: 70000000 sampai 99999999.</div>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="hanyaTerkalkulasi" name="hanya_terkalkulasi" value="1"
                                    <?= (int) ($pengaturan_pendaftaran['hanya_terkalkulasi'] ?? 0) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label text-secondary" for="hanyaTerkalkulasi">
                                    Hanya izinkan siswa yang sudah masuk dalam kalkulasi penempatan PKL (datasiswa).
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-light btn-modern text-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Batal
                            </button>
                            <button type="submit" name="simpan_pengaturan" value="1" class="btn btn-primary btn-modern">
                                <i class="fas fa-save"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

    <!-- Script to toggle mobile profile button -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var navbarCollapse = document.getElementById('navbarNav');
            var profileCollapse = document.getElementById('profileCollapse');
            
            if (navbarCollapse && profileCollapse) {
                // When main nav opens, open profile too
                navbarCollapse.addEventListener('show.bs.collapse', function () {
                    var bsCollapse = new bootstrap.Collapse(profileCollapse, { toggle: false });
                    bsCollapse.show();
                });
                
                // When main nav closes, close profile too
                navbarCollapse.addEventListener('hide.bs.collapse', function () {
                    var bsCollapse = new bootstrap.Collapse(profileCollapse, { toggle: false });
                    bsCollapse.hide();
                });
            }

            var statusPendaftaran = document.getElementById('statusPendaftaran');
            var statusPendaftaranLabel = document.getElementById('statusPendaftaranLabel');
            if (statusPendaftaran && statusPendaftaranLabel) {
                statusPendaftaran.addEventListener('change', function () {
                    statusPendaftaranLabel.textContent = statusPendaftaran.checked ? 'Buka' : 'Tutup';
                });
            }
        });
    </script>
    <?php if ($pengaturan_flash !== null): ?>
        <script>
            alert(<?= json_encode($pengaturan_flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
        </script>
    <?php endif; ?>
</body>
</html>
