<?php
session_start();

//membatasi halaman sebelum login
if (!isset($_SESSION['login'])) {
    echo "<script>
            alert('anda belum login');
            document.location.href = 'login.php';
        </script>";
    exit;
}

include 'layout/header.php';
$data_perusahaan = select("SELECT * FROM perusahaan ORDER BY id_perusahaan DESC");


// jika tombol tambah di tekan jalankan script

if (isset($_POST['tambah'])) {
    if (create_perusahaan($_POST) > 0) {
        echo "<script>
        alert('Data Perusahaan Di Tambahkan');
        document.location.href = 'perusahaan.php';
      </script>";
    } else {
        echo "<script>
        alert('Data Perusahaan Gagal Di Tambahkan');
        document.location.href = 'perusahaan.php';
      </script>";
    }
}
// tombol hapus perusahaan
if (isset($_GET['id_perusahaan'])) {
    $id_perusahaan = (int) $_GET['id_perusahaan'];

    if (delete_perusahaan($id_perusahaan) > 0) {
        echo "<script>
                alert('Data Perusahaan Berhasil Dihapus');
                document.location.href = 'perusahaan.php';
            </script>";
    } else {
        echo "<script>
                alert('Data Perusahaan Gagal Dihapus');
                document.location.href = 'perusahaan.php';
            </script>";
    }
}

// jika tombol ubah di tekan jalankan script
if (isset($_POST['ubah'])) {
    if (update_perusahaan($_POST) > 0) {
        echo "<script>
        alert('Data Perusahaan Berhasil Diperbaharui');
        document.location.href = 'perusahaan.php';
      </script>";
    } else {
        echo "<script>
        alert('Data Perusahaan Gagal Di Ubah');
        document.location.href = 'perusahaan.php';
      </script>";
    }
}

?>
<div class="container mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-building text-primary me-3"></i> Data Perusahaan
            </h2>
            <p class="text-secondary mb-0">Kelola daftar perusahaan mitra untuk tempat PKL siswa</p>
        </div>
        <button type="button" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 500;" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus me-2"></i> Tambah Perusahaan
        </button>
    </div>

    <!-- Table Card -->
    <div class="custom-table-container p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="datatable">
                <thead>
                    <tr>
                        <th width="5%" class="text-center">No</th>
                        <th width="45%">Nama Perusahaan</th>
                        <th width="35%">Jurusan</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; ?>
                    <?php foreach ($data_perusahaan as $perusahaan): ?>
                        <tr>
                            <td class="text-center fw-medium text-secondary"><?= $no++; ?></td>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($perusahaan['perusahaan']); ?></td>
                            <td>
                                <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill border border-primary-subtle">
                                    <i class="fas fa-graduation-cap me-1"></i> <?= htmlspecialchars($perusahaan['jurusan']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-light text-primary me-1 rounded-3" data-bs-toggle="modal"
                                    data-bs-target="#modalUbah<?= $perusahaan['id_perusahaan']; ?>" title="Ubah Data">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-light text-danger rounded-3" data-bs-toggle="modal"
                                    data-bs-target="#modalHapus<?= $perusahaan['id_perusahaan']; ?>" title="Hapus Data">
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

<!-- Modal Tambah Perusahaan -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div class="modal-header text-white border-0 p-4 pb-3" style="background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);">
                <h5 class="modal-title fw-bold" id="modalTambahLabel"><i class="fas fa-plus-circle me-2"></i> Tambah Mitra Perusahaan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label for="perusahaan" class="form-label fw-semibold text-secondary small text-uppercase">Nama Perusahaan</label>
                        <input type="text" name="perusahaan" id="perusahaan" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" placeholder="Masukkan Nama Perusahaan..." required>
                    </div>

                    <div class="mb-2">
                        <label for="jurusan" class="form-label fw-semibold text-secondary small text-uppercase">Jurusan Terkait</label>
                        <input type="text" name="jurusan" id="jurusan" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" placeholder="Contoh: Desain Komunikasi Visual" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-3 text-secondary fw-medium" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary px-4 rounded-3 shadow-sm fw-medium">
                        Simpan Perusahaan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<?php foreach ($data_perusahaan as $perusahaan): ?>
    <div class="modal fade" id="modalHapus<?= $perusahaan['id_perusahaan']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                <div class="modal-body p-5 text-center">
                    <div class="mb-4 d-inline-flex align-items-center justify-content-center text-danger rounded-circle" style="width: 80px; height: 80px; font-size: 2.5rem; background-color: #fee2e2;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-3">Hapus Perusahaan?</h4>
                    <p class="text-secondary mb-4">Anda yakin ingin menghapus data mitra <strong><?= htmlspecialchars($perusahaan['perusahaan']); ?></strong>? Tindakan ini dapat mempengaruhi data PKL yang terkait.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-light px-4 py-2 rounded-3 text-secondary fw-medium" data-bs-dismiss="modal">Batal</button>
                        <a href="perusahaan.php?id_perusahaan=<?= $perusahaan['id_perusahaan']; ?>" class="btn btn-danger px-4 py-2 rounded-3 shadow-sm fw-medium">
                            <i class="fas fa-trash-alt me-2"></i> Ya, Hapus
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Modal Ubah -->
<?php foreach ($data_perusahaan as $perusahaan): ?>
    <div class="modal fade" id="modalUbah<?= $perusahaan['id_perusahaan']; ?>" tabindex="-1" aria-labelledby="modalUbahLabel<?= $perusahaan['id_perusahaan']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                <div class="modal-header border-0 p-4 pb-3" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                    <h5 class="modal-title fw-bold" id="modalUbahLabel<?= $perusahaan['id_perusahaan']; ?>"><i class="fas fa-edit me-2"></i> Ubah Data Perusahaan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="id_perusahaan" value="<?= $perusahaan['id_perusahaan']; ?>">

                        <div class="mb-4">
                            <label for="perusahaan<?= $perusahaan['id_perusahaan']; ?>" class="form-label fw-semibold text-secondary small text-uppercase">Nama Perusahaan</label>
                            <input type="text" name="perusahaan" id="perusahaan<?= $perusahaan['id_perusahaan']; ?>" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" value="<?= htmlspecialchars($perusahaan['perusahaan']); ?>" required>
                        </div>

                        <div class="mb-2">
                            <label for="jurusan<?= $perusahaan['id_perusahaan']; ?>" class="form-label fw-semibold text-secondary small text-uppercase">Jurusan Terkait</label>
                            <input type="text" name="jurusan" id="jurusan<?= $perusahaan['id_perusahaan']; ?>" class="form-control border-0 shadow-sm" style="background-color: #f8fafc;" value="<?= htmlspecialchars($perusahaan['jurusan']); ?>" required>
                        </div>
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



<?php

include 'layout/footer.php';

?>