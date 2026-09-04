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
                    'gap' => [],
                    'perusahaan' => $perusahaan,
                    'nis' => $nis
                ];
            }

            // Menyimpan nilai soal dan factor jika ada
            if (isset($hasilsiswa[$factor_key])) {
                $grouped_data[$perusahaan][$siswa]['factor'][$i - 1] = $hasilsiswa[$factor_key];
            }
            if (isset($hasilsiswa[$soal_key])) {
                $gap = calculateGap($hasilsiswa[$soal_key]);
                $bobot = calculateBobot($gap);
                $grouped_data[$perusahaan][$siswa]['soal'][$i - 1] = $bobot;
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

    /* Tabel Pembobotan: layout fixed agar muat di desktop */
    .pembobotan-table {
        table-layout: fixed;
        width: 100%;
    }
    .pembobotan-table th, .pembobotan-table td {
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
        padding: 0.7rem 0.45rem;
    }
    .pembobotan-table .col-no   { width: 6%; }
    .pembobotan-table .col-nama { width: 10%; font-size: .75em; white-space: normal; word-break: break-word; }
    .pembobotan-table .col-nis  { width: 8%; }
    .pembobotan-table .col-dyn  { text-align: center; }
    .nisclass { font-size: 0.7rem; }

    #datatable th {
        font-size : .75em;
    }
</style>

<div class="container-fluid container-xl px-4 mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-weight-hanging text-primary me-3"></i> Pembobotan Nilai
            </h2>
            <p class="text-secondary mb-0">Konversi GAP menjadi nilai bobot sesuai tabel pembobotan</p>
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
                $dyn_count = count($columns_to_show) * 2;
                $static_pct = 4 + 14 + 9;
                $dyn_pct = $dyn_count > 0 ? round((100 - $static_pct) / $dyn_count, 1) : 0;
                ?>
                <table class="table table-hover pembobotan-table" id="datatable">
                    <thead>
                        <tr>
                            <th class="col-no text-center text-secondary">No</th>
                            <th class="col-nama text-secondary">Nama Siswa</th>
                            <th class="col-nis text-secondary">NIS</th>
                            <?php foreach ($columns_to_show as $index => $value): ?>
                                <th class="col-dyn text-secondary" style="width:<?= $dyn_pct; ?>%">Bobot <?= $index + 1; ?></th>
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
                                        <span class="badge bg-soft-primary text-primary px-2 py-1 rounded border border-primary-subtle">
                                            <?= isset($data['soal'][$index]) ? $data['soal'][$index] : '-'; ?>
                                        </span>
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
                <p class="text-secondary mb-0">Belum ada data pembobotan untuk perusahaan yang dipilih.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Deskripsi Pembobotan -->
<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.25);">
            <div class="modal-header border-0 p-4 pb-3" style="background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%); color: white;">
                <h5 class="modal-title fw-bold" id="staticBackdropLabel"><i class="fas fa-weight-hanging me-2"></i> Deskripsi: Pembobotan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary">Setelah didapatkan tiap GAP masing-masing siswa, setiap nilai siswa diberi <strong>bobot nilai patokan</strong> sesuai tabel berikut:</p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle border rounded-3 overflow-hidden">
                        <thead style="background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
                            <tr>
                                <th class="text-center text-secondary">No</th>
                                <th class="text-secondary">Selisih (GAP)</th>
                                <th class="text-secondary text-center">Bobot Nilai</th>
                                <th class="text-secondary">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="text-center">1</td><td class="text-center fw-bold">0</td><td class="text-center"><span class="badge bg-success text-white">4</span></td><td>Tidak ada selisih (kompetensi sesuai standar)</td></tr>
                            <tr><td class="text-center">2</td><td class="text-center fw-bold text-primary">+1</td><td class="text-center"><span class="badge bg-soft-primary text-primary border border-primary-subtle">3.5</span></td><td>Kompetensi individu kelebihan 1 tingkat</td></tr>
                            <tr><td class="text-center">3</td><td class="text-center fw-bold text-danger">-1</td><td class="text-center"><span class="badge bg-soft-danger text-danger border border-danger-subtle">3</span></td><td>Kompetensi individu kekurangan 1 tingkat</td></tr>
                            <tr><td class="text-center">4</td><td class="text-center fw-bold text-primary">+2</td><td class="text-center"><span class="badge bg-soft-primary text-primary border border-primary-subtle">2.5</span></td><td>Kompetensi individu kelebihan 2 tingkat</td></tr>
                            <tr><td class="text-center">5</td><td class="text-center fw-bold text-danger">-2</td><td class="text-center"><span class="badge bg-soft-danger text-danger border border-danger-subtle">2</span></td><td>Kompetensi individu kekurangan 2 tingkat</td></tr>
                            <tr><td class="text-center">6</td><td class="text-center fw-bold text-primary">+3</td><td class="text-center"><span class="badge bg-soft-primary text-primary border border-primary-subtle">1.5</span></td><td>Kompetensi individu kelebihan 3 tingkat</td></tr>
                            <tr><td class="text-center">7</td><td class="text-center fw-bold text-danger">-3</td><td class="text-center"><span class="badge bg-soft-danger text-danger border border-danger-subtle">1</span></td><td>Kompetensi individu kekurangan 3 tingkat</td></tr>
                        </tbody>
                    </table>
                </div>
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