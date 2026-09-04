<?php
session_start();

// Limit access before login
if (!isset($_SESSION['login'])) {
    echo "<script>
            alert('Anda belum login');
            document.location.href = 'login.php';
        </script>";
    exit;
}

include 'layout/header.php';

// Get all questions data
$data_soal = select("SELECT * FROM soal ORDER BY id_soal DESC");

// Handle question deletion
if (isset($_GET['id_soal'])) {
    $id_soal = (int) $_GET['id_soal'];

    if (delete_soal($id_soal) > 0) {
        echo "<script>
                alert('Data Soal Berhasil Dihapus');
                document.location.href = 'data-soal.php';
            </script>";
    } else {
        echo "<script>
                alert('Data Soal Gagal Dihapus');
                document.location.href = 'data-soal.php';
            </script>";
    }
}
?>

<div class="container-fluid container-xl mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-book-open text-primary me-3"></i> Bank Soal
            </h2>
            <p class="text-secondary mb-0">Kelola daftar pertanyaan dan nilai bobot untuk setiap instrumen penilaian</p>
        </div>
        <div class="d-flex gap-2">
            <a href="input-data.php" class="btn btn-light border shadow-sm px-3 py-2 text-secondary" style="border-radius: 10px; font-weight: 500;">
                <i class="fas fa-list me-2"></i> Lihat Soal
            </a>
            <a href="input-soal.php" class="btn btn-primary px-3 py-2 shadow-sm" style="border-radius: 10px; font-weight: 500;">
                <i class="fas fa-plus me-2"></i> Tambah Soal
            </a>
        </div>
    </div>

    <!-- Table Card -->
    <div class="custom-table-container p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="datatable" style="white-space: nowrap; font-size: 0.9rem;">
                <thead>
                    <tr>
                        <th class="text-center text-secondary">No</th>
                        <th class="text-secondary" style="min-width: 250px;">Perusahaan</th>
                        <th class="text-secondary" style="min-width: 250px;">Soal</th>
                        <th class="text-secondary">Jawaban A (Val)</th>
                        <th class="text-secondary">Jawaban B (Val)</th>
                        <th class="text-secondary">Jawaban C (Val)</th>
                        <th class="text-secondary">Jawaban D (Val)</th>
                        <th class="text-secondary text-center">Factor</th>
                        <th class="text-center text-secondary">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; ?>
                    <?php foreach ($data_soal as $soal): ?>
                        <tr>
                            <td class="text-center fw-medium text-secondary"><?= $no++; ?></td>
                            <td>
                                <span class="badge bg-primary text-white text-wrap px-2 py-1 rounded border border-primary-subtle">
                                    <?= htmlspecialchars($soal['perusahaan']); ?>
                                </span>
                            </td>
                            <td class="fw-medium text-dark text-wrap" style="min-width: 250px; line-height: 1.4;">
                                <?= htmlspecialchars($soal['soal']); ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($soal['jawaban_a']); ?> 
                                <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($soal['value_a']); ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($soal['jawaban_b']); ?> 
                                <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($soal['value_b']); ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($soal['jawaban_c']); ?> 
                                <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($soal['value_c']); ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($soal['jawaban_d']); ?> 
                                <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($soal['value_d']); ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-soft-success fs-6 text-success px-2 py-1 rounded">
                                    <?= htmlspecialchars($soal['factor']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="ubah-soal.php?id_soal=<?= $soal['id_soal']; ?>" class="btn btn-sm btn-light text-primary rounded-3" title="Ubah Data">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-light text-danger rounded-3" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $soal['id_soal']; ?>" title="Hapus Data">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Hapus (Pindah keluar dari tabel) -->
<?php foreach ($data_soal as $soal): ?>
    <div class="modal fade" id="modalHapus<?= $soal['id_soal']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                <div class="modal-body p-5 text-center">
                    <div class="mb-4 d-inline-flex align-items-center justify-content-center text-danger rounded-circle" style="width: 80px; height: 80px; font-size: 2.5rem; background-color: #fee2e2;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-3">Hapus Soal?</h4>
                    <p class="text-secondary mb-2">Anda yakin ingin menghapus data soal ini?</p>
                    <div class="bg-light p-3 rounded-3 mb-4 text-start mt-3 border">
                        <small class="text-muted d-block mb-1">Pertanyaan:</small>
                        <span class="fst-italic">"<?= htmlspecialchars(strlen($soal['soal']) > 100 ? substr($soal['soal'], 0, 100) . '...' : $soal['soal']); ?>"</span>
                    </div>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-light px-4 py-2 rounded-3 text-secondary fw-medium" data-bs-dismiss="modal">Batal</button>
                        <a href="data-soal.php?id_soal=<?= $soal['id_soal']; ?>" class="btn btn-danger px-4 py-2 rounded-3 shadow-sm fw-medium">
                            <i class="fas fa-trash-alt me-2"></i> Ya, Hapus
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
include 'layout/footer.php';
?>