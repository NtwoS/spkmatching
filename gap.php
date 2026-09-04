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
                    'gap' => [], // Tambahkan array untuk menyimpan nilai GAP
                    'perusahaan' => $perusahaan,
                    'nis' => $nis
                ];
            }

            // Menyimpan soal, factor, dan menghitung GAP jika ada
            if (isset($hasilsiswa[$soal_key])) {
                $grouped_data[$perusahaan][$siswa]['soal'][$i - 1] = $hasilsiswa[$soal_key];
                // Hitung GAP untuk setiap soal
                $grouped_data[$perusahaan][$siswa]['gap'][$i - 1] = calculateGap($hasilsiswa[$soal_key]);
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
    .factorclass { font-size: 0.9em; }

    /* Tabel GAP: layout fixed agar muat di desktop */
    .gap-table {
        table-layout: fixed;
        width: 100%;
    }
    .gap-table th, .gap-table td {
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
        padding: 0.7rem 0.45rem;
    }
    /* Kolom statis */
    .gap-table .col-no   { width: 6%; }
    .gap-table .col-nama { width: 10%; font-size: .75em; white-space: normal; word-break: break-word; }
    .gap-table .col-nis  { width: 8%; }
    .gap-table .col-dyn1  { width: 7%; }
    /* Kolom dinamis: sisa dibagi rata via PHP di bawah */
    .gap-table .col-dyn  { text-align: center; }

    .nisclass { font-size: 0.8rem; }

    #datatable th {
        font-size : .9em;
    }
</style>

<div class="container-fluid container-xl px-4 mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-not-equal text-primary me-3"></i> Pemetaan GAP
            </h2>
            <p class="text-secondary mb-0">Selisih antara nilai siswa dengan nilai standar perusahaan</p>
        </div>
        <button type="button" class="btn btn-light border shadow-sm px-3 py-2" style="border-radius: 10px; font-weight: 500;" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
            <i class="fas fa-file-alt me-2 text-primary"></i> Lihat Deskripsi
        </button>
    </div>

    <!-- Filter Section -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px;">
        <div class="card-body p-4">
            <form method="GET" action="" class="m-0">
                <div class="col-md-8 col-lg-6">
                    <label for="perusahaan" class="form-label fw-semibold text-secondary small text-uppercase mb-2">
                        <i class="fas fa-filter me-1"></i> Filter Perusahaan
                    </label>
                    <select class="form-select border-0 shadow-sm" style="padding: 0.75rem 1rem; border-radius: 10px;" id="perusahaan" name="perusahaan" onchange="this.form.submit()">
                        <?php foreach ($perusahaan_list as $perusahaan):
                            if (!empty(trim($perusahaan))): ?>
                                <option value="<?= htmlspecialchars($perusahaan); ?>" <?= $selected_perusahaan == $perusahaan ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($perusahaan); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <?php if (isset($grouped_data[$selected_perusahaan])): ?>
        <?php
        $columns_to_show = [];
        foreach ($grouped_data[$selected_perusahaan] as $siswa => $data) {
            foreach ($data['soal'] as $index => $soal) {
                $columns_to_show[$index] = true;
            }
        }
        ?>
        <div class="custom-table-container p-4">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-light">
                <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill">
                    <i class="fas fa-building me-1"></i> <?= htmlspecialchars($selected_perusahaan); ?>
                </span>
                <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill">
                    <?= count($grouped_data[$selected_perusahaan]); ?> Siswa
                </span>
            </div>
            <div class="table-responsive">
                <?php
                // Hitung lebar kolom dinamis agar total = 100%
                $dyn_count = count($columns_to_show) * 2; // pasangan GAP + Factor
                $static_pct = 3 + 14 + 8; // No + Nama + NIS
                $dyn_pct = $dyn_count > 0 ? round((100 - $static_pct) / $dyn_count, 1) : 0;
                ?>
                <table class="table table-hover gap-table" id="datatable">
                    <thead>
                        <tr>
                            <th class="col-no text-center text-secondary">No</th>
                            <th class="col-nama text-secondary">Nama Siswa</th>
                            <th class="col-nis text-secondary">NIS</th>
                            <?php foreach ($columns_to_show as $index => $value): ?>
                                <th class="col-dyn1 text-secondary" style="width:<?= $dyn_pct; ?>%">GAP <?= $index + 1; ?></th>
                                <th class="col-dyn text-secondary" style="width:<?= $dyn_pct; ?>%">Factor <?= $index + 1; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php foreach ($grouped_data[$selected_perusahaan] as $siswa => $data): ?>
                            <tr>
                                <td class="col-no text-center fw-medium text-secondary"><?= $no++; ?></td>
                                <td class="col-nama fw-bold text-dark"><?= htmlspecialchars($siswa); ?></td>
                                <td><span class="nisclass text-secondary bg-light px-2 py-1 rounded border" style="font-family:monospace;"><?= htmlspecialchars($data['nis']); ?></span></td>
                                <?php foreach ($columns_to_show as $index => $value): ?>
                                    <td class="text-center fw-semibold">
                                        <?php $gap_val = isset($data['gap'][$index]) ? $data['gap'][$index] : null; ?>
                                        <?php if ($gap_val !== null): ?>
                                            <span class="badge <?= $gap_val == 0 ? 'bg-success' : ($gap_val > 0 ? 'bg-soft-primary text-primary border border-primary-subtle' : 'bg-soft-danger text-danger border border-danger-subtle'); ?> px-2 py-1 rounded">
                                                <?= $gap_val; ?>
                                            </span>
                                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if(isset($data['factor'][$index])): ?>
                                            <span class="factorclass badge bg-soft-success text-success px-2 py-1 rounded"><?= htmlspecialchars($data['factor'][$index]); ?></span>
                                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm" style="border-radius: 20px;">
            <div class="card-body p-5 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-light text-secondary rounded-circle mb-3" style="width:100px;height:100px;font-size:2.5rem;"><i class="fas fa-folder-open"></i></div>
                <h4 class="fw-bold text-dark mb-2">Data Tidak Ditemukan</h4>
                <p class="text-secondary mb-0">Belum ada data GAP untuk perusahaan yang dipilih.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Deskripsi GAP -->
<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.25);">
            <div class="modal-header border-0 p-4 pb-3" style="background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%); color: white;">
                <h5 class="modal-title fw-bold" id="staticBackdropLabel"><i class="fas fa-not-equal me-2"></i> Deskripsi: Pemetaan GAP</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold text-center mb-3">Apa itu GAP?</h6>
                <p class="text-secondary">Gap adalah <strong>perbedaan (selisih)</strong> antara nilai yang dimiliki siswa dengan nilai standar yang ditetapkan oleh perusahaan.</p>
                <div class="alert alert-primary rounded-3 text-center fw-semibold">
                    <i class="fas fa-equals me-2"></i> GAP = Nilai Siswa &minus; Nilai Standar
                </div>
                <p class="text-secondary small mb-0">Nilai GAP positif berarti siswa <em>melebihi</em> standar, negatif berarti <em>di bawah</em> standar, dan nol berarti <em>tepat sesuai</em> standar.</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-3 text-secondary fw-medium" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php
include 'layout/footer.php';
?>