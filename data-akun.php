<?php
session_start();

// Membatasi halaman sebelum login
if (!isset($_SESSION['login'])) {
    echo "<script>
            alert('Anda belum login');
            document.location.href = 'login.php';
        </script>";
    exit;
}



include 'layout/header.php';

if (isset($_GET['id_akun'])) {
    $id_akun = (int) $_GET['id_akun'];

    if (delete_akun($id_akun) > 0) {
        echo "<script>
                alert('Data Akun Berhasil Dihapus');
                document.location.href = 'data-akun.php';
        </script>";
    } else {
        echo "<script>
                alert('Data Akun Gagal Dihapus');
                document.location.href = 'data-akun.php';
        </script>";
    }
}

$data_akun = select("SELECT * FROM akun");

// jika tombol tambah di tekan jalankan script
if (isset($_POST['tambah'])) {
    if (create_akun($_POST) > 0) {
        echo "<script>
        alert('Data Akun Di Tambahkan');
        document.location.href = 'data-akun.php';
      </script>";
    } else {
        echo "<script>
        alert('Data Akun Gagal Di Tambahkan');
        document.location.href = 'data-akun.php';
      </script>";
    }
}

// jika tombol ubah di tekan jalankan script
if (isset($_POST['ubah'])) {
    if (update_akun($_POST) > 0) {
        echo "<script>
        alert('Data Akun Berhasil Diperbaharui');
        document.location.href = 'data-akun.php';
      </script>";
    } else {
        echo "<script>
        alert('Data Akun Gagal Di Ubah');
        document.location.href = 'data-akun.php';
      </script>";
    }
}
?>

<div class="container mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-users-cog text-primary me-3"></i> Manajemen Akun
            </h2>
            <p class="text-secondary mb-0">Kelola data login siswa dan administrator sistem</p>
        </div>
        <button type="button" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 500;" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-user-plus me-2"></i> Tambah Akun
        </button>
    </div>

    <!-- Table Card -->
    <div class="custom-table-container p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="datatable">
                <thead>
                    <tr>
                        <th width="5%" class="text-center">No</th>
                        <th width="15%" class="text-center">NIS</th>
                        <th width="20%" class="text-center">Nama</th>
                        <th width="15%"class="text-center">Username</th>
                        <th width="20%"class="text-center">Email</th>
                        <th width="10%"class="text-center">Level</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; ?>
                    <?php foreach ($data_akun as $akun): ?>
                        <tr>
                            <td class="text-center fw-medium text-secondary"><?= $no++; ?></td>
                            <td><?= htmlspecialchars($akun['nis']) == '0' ? '-' : htmlspecialchars($akun['nis']); ?></td>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($akun['nama']); ?></td>
                            <td class="text-secondary"><?= htmlspecialchars($akun['username']); ?></td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($akun['email']); ?>" class="text-decoration-none" style="color: #4361ee;">
                                    <?= htmlspecialchars($akun['email']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if($akun['level'] == 1): ?>
                                    <span class="badge bg-primary px-3 py-2 rounded-pill">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-success px-3 py-2 rounded-pill">Siswa</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-light text-primary me-1 rounded-3" data-bs-toggle="modal"
                                    data-bs-target="#modalUbah<?= $akun['id_akun']; ?>" title="Ubah Data">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-light text-danger rounded-3" data-bs-toggle="modal"
                                    data-bs-target="#modalHapus<?= $akun['id_akun']; ?>" title="Hapus Data">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div class="modal-header text-white border-0 p-4 pb-3" style="background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);">
                <h5 class="modal-title fw-bold" id="modalTambahLabel"><i class="fas fa-user-plus me-2"></i> Tambah Akun Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    
                    <div class="mb-4">
                        <label for="levelTambah" class="form-label fw-semibold text-secondary small text-uppercase">Level Akses</label>
                        <select name="level" id="levelTambah" class="form-select border-0 shadow-sm" style="background-color: #f8fafc;" required onchange="handleLevelChange()">
                            <option value="1">Administrator</option>
                            <option value="2" selected>Siswa</option>
                        </select>
                    </div>

                    <div class="mb-4" id="nisContainer">
                        <label for="nis" class="form-label fw-semibold text-secondary small text-uppercase">Nomor Induk Siswa (NIS)</label>
                        <input type="text" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" id="nis" name="nis" pattern="[0-9]*" inputmode="numeric" placeholder="Masukkan NIS Siswa" required>
                    </div>

                    <div class="mb-4">
                        <label for="nama" class="form-label fw-semibold text-secondary small text-uppercase">Nama Lengkap</label>
                        <input type="text" name="nama" id="nama" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" placeholder="Masukkan Nama Lengkap..." required>
                    </div>

                    <div class="mb-4">
                        <label for="username" class="form-label fw-semibold text-secondary small text-uppercase">Username</label>
                        <input type="text" name="username" id="username" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" placeholder="Masukkan Username..." required>
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label fw-semibold text-secondary small text-uppercase">Alamat Email</label>
                        <input type="email" name="email" id="email" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" placeholder="contoh@gmail.com" required>
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label fw-semibold text-secondary small text-uppercase">Password</label>
                        <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                            <input type="password" name="password" id="password" class="form-control border-0" style="background-color: #f8fafc;" placeholder="Minimal 6 karakter" required minlength="6">
                            <button class="btn border-0" type="button" id="togglePassword" style="background-color: #f8fafc; color: #64748b;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-3 text-secondary fw-medium" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4 rounded-3 shadow-sm fw-medium">
                        Simpan Akun
                    </button>
                </div>
            </form>
            
            <script>
                document.getElementById('togglePassword').addEventListener('click', function () {
                    const passwordInput = document.getElementById('password');
                    const icon = this.querySelector('i');

                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            </script>
        </div>
    </div>
</div>

<script>
    function handleLevelChange() {
        const levelSelect = document.getElementById('levelTambah');
        const nisField = document.getElementById('nis');
        const nisContainer = document.getElementById('nisContainer');

        if (levelSelect.value === '1') { // Jika Admin dipilih
            nisField.value = '0';
            nisContainer.style.display = 'none';
        } else { // Jika Siswa dipilih
            nisField.value = '';
            nisContainer.style.display = 'block';
        }
    }

    // Jalankan fungsi saat modal dibuka untuk mengatur kondisi awal
    document.getElementById('modalTambah').addEventListener('show.bs.modal', function () {
        handleLevelChange();
        const form = this.querySelector('form');
        form.reset();
        
        // Ensure eye icon resets correctly
        const passwordInput = document.getElementById('password');
        const icon = document.getElementById('togglePassword').querySelector('i');
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    });
</script>

<!-- Modal Hapus -->
<?php foreach ($data_akun as $akun): ?>
    <div class="modal fade" id="modalHapus<?= $akun['id_akun']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                <div class="modal-body p-5 text-center">
                    <div class="mb-4 d-inline-flex align-items-center justify-content-center text-danger rounded-circle" style="width: 80px; height: 80px; font-size: 2.5rem; background-color: #fee2e2;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-3">Hapus Akun?</h4>
                    <p class="text-secondary mb-4">Anda yakin ingin menghapus data akun atas nama <strong><?= htmlspecialchars($akun['nama']); ?></strong>? Tindakan ini tidak dapat dibatalkan.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-light px-4 py-2 rounded-3 text-secondary fw-medium" data-bs-dismiss="modal">Batal</button>
                        <a href="data-akun.php?id_akun=<?= $akun['id_akun']; ?>" class="btn btn-danger px-4 py-2 rounded-3 shadow-sm fw-medium">
                            <i class="fas fa-trash-alt me-2"></i> Ya, Hapus
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Modal Ubah -->
<?php foreach ($data_akun as $akun): ?>
    <div class="modal fade" id="modalUbah<?= $akun['id_akun']; ?>" tabindex="-1" aria-labelledby="modalUbahLabel<?= $akun['id_akun']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                <div class="modal-header border-0 p-4 pb-3" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                    <h5 class="modal-title fw-bold" id="modalUbahLabel<?= $akun['id_akun']; ?>"><i class="fas fa-user-edit me-2"></i> Ubah Data Akun</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="id_akun" value="<?= $akun['id_akun'] ?>">

                        <div class="mb-4">
                            <label for="level<?= $akun['id_akun']; ?>" class="form-label fw-semibold text-secondary small text-uppercase">Level Akses</label>
                            <select name="level" id="level<?= $akun['id_akun']; ?>" class="form-select border-0 shadow-sm" style="background-color: #f8fafc;" required onchange="toggleNisField('<?= $akun['id_akun']; ?>')">
                                <?php $level = $akun['level']; ?>
                                <option value="1" <?= $level == '1' ? 'selected' : '' ?>>Administrator</option>
                                <option value="2" <?= $level == '2' ? 'selected' : '' ?>>Siswa</option>
                            </select>
                        </div>

                        <div class="mb-4" id="nisFieldContainer<?= $akun['id_akun']; ?>" style="<?= $level == '1' ? 'display: none;' : '' ?>">
                            <label for="nis<?= $akun['id_akun']; ?>" class="form-label fw-semibold text-secondary small text-uppercase">NIS</label>
                            <input type="text" name="nis" id="nis<?= $akun['id_akun']; ?>" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" value="<?= $level == '1' ? '0' : htmlspecialchars($akun['nis']); ?>" required>
                        </div>

                        <div class="mb-4">
                            <label for="nama<?= $akun['id_akun']; ?>" class="form-label fw-semibold text-secondary small text-uppercase">Nama Lengkap</label>
                            <input type="text" name="nama" id="nama<?= $akun['id_akun']; ?>" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" value="<?= htmlspecialchars($akun['nama']); ?>" required>
                        </div>

                        <div class="mb-4">
                            <label for="username<?= $akun['id_akun']; ?>" class="form-label fw-semibold text-secondary small text-uppercase">Username</label>
                            <input type="text" name="username" id="username<?= $akun['id_akun']; ?>" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" value="<?= htmlspecialchars($akun['username']); ?>" required>
                        </div>

                        <div class="mb-4">
                            <label for="email<?= $akun['id_akun']; ?>" class="form-label fw-semibold text-secondary small text-uppercase">Email</label>
                            <input type="email" name="email" id="email<?= $akun['id_akun']; ?>" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" value="<?= htmlspecialchars($akun['email']); ?>" required>
                        </div>

                        <div class="mb-2">
                            <label for="password<?= $akun['id_akun']; ?>" class="form-label fw-semibold text-secondary small text-uppercase d-flex justify-content-between">
                                <span>Password</span>
                                <span class="badge bg-secondary opacity-75 rounded-pill" style="font-size: 0.65rem; text-transform: none; letter-spacing: 0;">Masukkan sandi</span>
                            </label>
                            <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                <input type="password" name="password" id="password<?= $akun['id_akun']; ?>" class="form-control border-0" style="background-color: #f8fafc;" required minlength="6">
                                <button class="btn border-0" type="button" id="togglePassword<?= $akun['id_akun']; ?>" style="background-color: #f8fafc; color: #64748b;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <script>
                            document.getElementById('togglePassword<?= $akun['id_akun']; ?>').addEventListener('click', function () {
                                const passwordInput = document.getElementById('password<?= $akun['id_akun']; ?>');
                                const icon = this.querySelector('i');

                                if (passwordInput.type === 'password') {
                                    passwordInput.type = 'text';
                                    icon.classList.remove('fa-eye');
                                    icon.classList.add('fa-eye-slash');
                                } else {
                                    passwordInput.type = 'password';
                                    icon.classList.remove('fa-eye-slash');
                                    icon.classList.add('fa-eye');
                                }
                            });
                        </script>

                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4 rounded-3 text-secondary fw-medium" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button type="submit" name="ubah" class="btn btn-success px-4 rounded-3 shadow-sm fw-medium">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>


<script>
    // Function for Ubah modals
    function toggleNisField(id) {
        const levelSelect = document.getElementById('level' + id);
        const nisField = document.getElementById('nis' + id);
        const nisFieldContainer = document.getElementById('nisFieldContainer' + id);

        if (levelSelect.value === '1') { // Admin selected
            nisField.value = '0';
            nisFieldContainer.style.display = 'none';
        } else {
            nisFieldContainer.style.display = 'block';
        }
    }

    // Initialize all Ubah modals when shown
    document.querySelectorAll('.modal[id^="modalUbah"]').forEach(modal => {
        modal.addEventListener('show.bs.modal', function () {
            const modalId = this.id.replace('modalUbah', '');
            toggleNisField(modalId);
        });
    });
</script>



<?php
include 'layout/footer.php';