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

// Mengambil data dari database
$data_hasilsiswa = select("SELECT * FROM datasiswa ORDER BY id_siswa DESC");
$data_perusahaan = select("SELECT * FROM perusahaan ORDER BY id_perusahaan DESC");

// Mengelompokkan data siswa berdasarkan perusahaan dan nama siswa
$grouped_data = [];
foreach ($data_hasilsiswa as $hasilsiswa) {
    // Loop melalui setiap perusahaan
    foreach ($hasilsiswa as $key => $value) {
        if (strpos($key, 'perusahaan') === 0) { // Cek jika key dimulai dengan 'perusahaan'
            $i = (int) substr($key, 10); // Ekstrak nomor dari key perusahaan (misalnya 'perusahaan1' -> 1)

            $perusahaan_key = 'perusahaan' . $i;
            $soal_key = 'soal' . $i;
            $factor_key = 'factor' . $i;
            $perusahaan = $hasilsiswa[$perusahaan_key];
            $siswa = $hasilsiswa['siswa'];
            $nis = $hasilsiswa['nis'];

            // Inisialisasi struktur data jika belum ada
            if (!isset($grouped_data[$perusahaan])) {
                $grouped_data[$perusahaan] = [];
            }

            if (!isset($grouped_data[$perusahaan][$siswa])) {
                $grouped_data[$perusahaan][$siswa] = [
                    'soal' => [],
                    'factor' => [],
                    'perusahaan' => $perusahaan,
                    'nis' => $nis
                ];
            }

            // Menyimpan soal dan factor jika ada
            if (isset($hasilsiswa[$soal_key])) {
                $grouped_data[$perusahaan][$siswa]['soal'][$i - 1] = $hasilsiswa[$soal_key];
            }
            if (isset($hasilsiswa[$factor_key])) {
                $grouped_data[$perusahaan][$siswa]['factor'][$i - 1] = $hasilsiswa[$factor_key];
            }
        }
    }
}

// Mendapatkan daftar perusahaan yang tersedia (tanpa yang kosong)
$perusahaan_list = array_filter(array_keys($grouped_data), function ($perusahaan) {
    return !empty(trim($perusahaan));
});

// Menentukan perusahaan yang dipilih
$selected_perusahaan = isset($_GET['perusahaan']) ? $_GET['perusahaan'] : (count($perusahaan_list) > 0 ? reset($perusahaan_list) : '');

?>

<style>
    .faktorclass {
        font-size: .9em;
    }
</style>
<div class="container-fluid container-xl mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-user-graduate text-primary me-3"></i> Data Siswa & Hasil Evaluasi
            </h2>
            <p class="text-secondary mb-0">Lihat hasil evaluasi siswa berdasarkan perusahaan tempat PKL</p>
        </div>
    </div>

    <!-- Filter Section (Card) -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
        <div class="card-body p-4">
            <form method="GET" action="" class="m-0">
                <div class="row align-items-center">
                    <div class="col-md-8 col-lg-6">
                        <label for="perusahaan" class="form-label fw-semibold text-secondary small text-uppercase mb-2">
                            <i class="fas fa-filter me-1"></i> Filter Berdasarkan Perusahaan
                        </label>
                        <select class="form-select border-0 shadow-sm" style="background-color: #fff; cursor: pointer; padding: 0.75rem 1rem; border-radius: 10px;" id="perusahaan" name="perusahaan" onchange="this.form.submit()">
                            <?php foreach ($perusahaan_list as $perusahaan):
                                if (!empty(trim($perusahaan))): ?>
                                    <option value="<?= htmlspecialchars($perusahaan); ?>" <?= $selected_perusahaan == $perusahaan ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($perusahaan); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Section -->
    <?php if (isset($grouped_data[$selected_perusahaan])): ?>
        <div class="custom-table-container p-4">
            <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-light">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                    <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill me-2">
                        <i class="fas fa-building me-1"></i> <?= htmlspecialchars($selected_perusahaan); ?>
                    </span>
                </h5>
                <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill">
                    <?= count($grouped_data[$selected_perusahaan]); ?> Siswa
                </span>
            </div>

            <?php
            // Menentukan kolom yang harus ditampilkan
            $columns_to_show = [];
            foreach ($grouped_data[$selected_perusahaan] as $siswa => $data) {
                foreach ($data['soal'] as $index => $soal) {
                    $columns_to_show[$index] = true;
                }
            }
            ?>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="datatable" style="white-space: nowrap;">
                    <thead>
                        <tr>
                            <th class="text-center text-secondary" width="5%">No</th>
                            <th class="text-secondary" style="min-width: 200px;">Nama Siswa</th>
                            <th class="text-secondary">NIS</th>
                            <?php foreach ($columns_to_show as $index => $value): ?>
                                <th class="text-secondary text-center">Soal <?= $index + 1; ?></th>
                                <th class="text-secondary text-center">Faktor <?= $index + 1; ?></th>
                            <?php endforeach; ?>
                            <?php if ($_SESSION['level'] != 2): ?>
                                <th class="text-center text-secondary">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php foreach ($grouped_data[$selected_perusahaan] as $siswa => $data): ?>
                            <tr>
                                <td class="text-center fw-medium text-secondary"><?= $no++; ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($siswa); ?></td>
                                <td>
                                    <span class="text-secondary bg-light px-2 py-1 rounded border fs-7" style="font-family: monospace;">
                                        <?= htmlspecialchars($data['nis']); ?>
                                    </span>
                                </td>
                                <?php foreach ($columns_to_show as $index => $value): ?>
                                    <td class="text-center">
                                        <?= isset($data['soal'][$index]) ? htmlspecialchars($data['soal'][$index]) : '<span class="text-muted">-</span>'; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if(isset($data['factor'][$index])): ?>
                                            <span class="faktorclass badge bg-soft-success text-success px-2 py-1 rounded">
                                                <?= htmlspecialchars($data['factor'][$index]); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                
                                <?php if ($_SESSION['level'] != 2): ?>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-light text-danger rounded-3 p-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalHapus<?= urlencode($data['nis']); ?>"
                                            title="Hapus Data">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <!-- Empty State Component -->
        <div class="card border-0 shadow-sm" style="border-radius: 20px;">
            <div class="card-body p-5 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-light text-secondary rounded-circle mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Data Tidak Ditemukan</h4>
                <p class="text-secondary mb-0">Belum ada data hasil evaluasi siswa PKL untuk perusahaan ini.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Hapus -->
<?php foreach ($grouped_data[$selected_perusahaan] ?? [] as $siswa => $data): ?>
    <div class="modal fade" id="modalHapus<?= urlencode($data['nis']); ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                <div class="modal-body p-5 text-center">
                    <div class="mb-4 d-inline-flex align-items-center justify-content-center text-danger rounded-circle" style="width: 80px; height: 80px; font-size: 2.5rem; background-color: #fee2e2;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-3">Hapus Data Siswa?</h4>
                    <p class="text-secondary mb-4">Anda yakin ingin menghapus data hasil evaluasi atas nama <strong><?= htmlspecialchars($siswa); ?></strong>? Tindakan ini tidak dapat dibatalkan.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-light px-4 py-2 rounded-3 text-secondary fw-medium" data-bs-dismiss="modal">Batal</button>
                        <a href="hapus-hasil.php?nis=<?= urlencode($data['nis']); ?>" class="btn btn-danger px-4 py-2 rounded-3 shadow-sm fw-medium">
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