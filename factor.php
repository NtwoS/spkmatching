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
                $grouped_data[$perusahaan][$siswa]['gap'][$i - 1] = $gap;
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


// Menghitung rata-rata nilai core dan secondary untuk perusahaan yang dipilih
$core_average = calculateCoreAverage($grouped_data, $selected_perusahaan);
$secondary_average = calculateSecondaryAverage($grouped_data, $selected_perusahaan);
?>

<style>
    .factorclass { font-size: 0.9em; }

    /* Tabel Factor: layout fixed agar muat di desktop */
    .factor-table {
        table-layout: fixed;
        width: 100%;
    }
    .factor-table th, .factor-table td {
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
        padding: 0.5rem 0.45rem;
    }
    .factor-table .col-no   { width: 6%; }
    .factor-table .col-nama { width: 10%; font-size: .75em; white-space: normal; word-break: break-word; }
    .factor-table .col-nis  { width: 8%; }
    .factor-table .col-cf   { width: 5%; text-align: center; background: linear-gradient(135deg, #dbeafe, #eff6ff); color: #1d4ed8; }
    .factor-table .col-sf   { width: 5%; text-align: center; background: linear-gradient(135deg, #d1fae5, #ecfdf5); color: #065f46; }
    .factor-table .col-dyn  { text-align: center; }
    .nisclass { font-size: 0.8rem; }

    #datatable th {
        font-size : .75em;
    }

    #datatable td {
        font-size : .8em;
    }
</style>

<div class="container-fluid container-xl px-4 mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-layer-group text-primary me-3"></i> Core & Secondary Factor
            </h2>
            <p class="text-secondary mb-0">Pengelompokan dan perhitungan Core Factor (CF) dan Secondary Factor (SF)</p>
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
                if (!empty($soal) || !empty($data['factor'][$index])) {
                    $columns_to_show[$index] = true;
                }
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
                $static_pct = 4 + 14 + 9 + 7 + 7; // No + Nama + NIS + CF + SF
                $dyn_pct = $dyn_count > 0 ? round((100 - $static_pct) / $dyn_count, 1) : 0;
                ?>
                <table class="table table-hover factor-table" id="datatable">
                    <thead>
                        <tr>
                            <th class="col-no text-center text-secondary">No</th>
                            <th class="col-nama text-secondary">Nama Siswa</th>
                            <th class="col-nis text-secondary">NIS</th>
                            <?php foreach ($columns_to_show as $index => $value): ?>
                                <th class="col-dyn text-secondary" style="width:<?= $dyn_pct; ?>%">Bobot <?= $index + 1; ?></th>
                                <th class="col-dyn text-secondary" style="width:<?= $dyn_pct; ?>%">Factor <?= $index + 1; ?></th>
                            <?php endforeach; ?>
                            <th class="col-cf">CF</th>
                            <th class="col-sf">SF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php foreach ($grouped_data[$selected_perusahaan] as $siswa => $data): ?>
                            <?php
                            $cf_sum = 0; $cf_count = 0; $sf_sum = 0; $sf_count = 0;
                            foreach ($data['factor'] as $index => $factor) {
                                if ($factor === 'core' && isset($data['soal'][$index])) { $cf_sum += $data['soal'][$index]; $cf_count++; }
                                elseif ($factor === 'secondary' && isset($data['soal'][$index])) { $sf_sum += $data['soal'][$index]; $sf_count++; }
                            }
                            $cf = $cf_count > 0 ? $cf_sum / $cf_count : 0;
                            $sf = $sf_count > 0 ? $sf_sum / $sf_count : 0;
                            ?>
                            <tr>
                                <td class="col-no text-center fw-medium text-secondary"><?= $no++; ?></td>
                                <td class="col-nama fw-bold text-dark"><?= htmlspecialchars($siswa); ?></td>
                                <td><span class="nisclass text-secondary bg-light px-2 py-1 rounded border" style="font-family:monospace;"><?= htmlspecialchars($data['nis']); ?></span></td>
                                <?php foreach ($columns_to_show as $index => $value): ?>
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary px-2 py-1 rounded border border-primary-subtle">
                                            <?= isset($data['soal'][$index]) ? $data['soal'][$index] : '-'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php $fval = isset($data['factor'][$index]) ? $data['factor'][$index] : null; ?>
                                        <?php if($fval): ?>
                                            <span class="badge <?= $fval === 'core' ? 'factorclass bg-primary text-white' : 'factorclass bg-soft-success text-success border border-success-subtle'; ?> px-2 py-1 rounded">
                                                <?= htmlspecialchars($fval); ?>
                                            </span>
                                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-center fw-bold" style="color: #1d4ed8;"><?= number_format($cf, 2); ?></td>
                                <td class="text-center fw-bold" style="color: #065f46;"><?= number_format($sf, 2); ?></td>
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
                <p class="text-secondary mb-0">Belum ada data untuk perusahaan yang dipilih.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Deskripsi Factor -->
<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.25);">
            <div class="modal-header border-0 p-4 pb-3" style="background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%); color: white;">
                <h5 class="modal-title fw-bold" id="staticBackdropLabel"><i class="fas fa-layer-group me-2"></i> Deskripsi: Core & Secondary Factor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary">Setelah menentukan bobot nilai gap, selanjutnya dikelompokkan menjadi dua kelompok yaitu <em>core factor</em> dan <em>secondary factor</em>.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; border-left: 4px solid #4361ee !important;">
                            <div class="card-body">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-star me-1"></i> A. Core Factor (CF)</h6>
                                <p class="text-secondary small">Kompetensi <strong>inti</strong> siswa yang diukur melalui soal kritis/paling menentukan.</p>
                                <div class="bg-light p-2 rounded text-center my-2">
                                    <img src="img/core.png" alt="Rumus Core" class="img-fluid" style="max-height: 60px;">
                                </div>
                                <ul class="small text-secondary mb-0">
                                    <li><strong>NCF</strong>: Nilai rata-rata kompetensi inti</li>
                                    <li><strong>NC(s,p)</strong>: Total skor soal kritis</li>
                                    <li><strong>IC</strong>: Jumlah indikator core</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; border-left: 4px solid #10b981 !important;">
                            <div class="card-body">
                                <h6 class="fw-bold text-success mb-2"><i class="fas fa-circle-notch me-1"></i> B. Secondary Factor (SF)</h6>
                                <p class="text-secondary small">Kompetensi <strong>pendukung</strong> siswa melalui soal relevansi sedang.</p>
                                <div class="bg-light p-2 rounded text-center my-2">
                                    <img src="img/second.png" alt="Rumus Secondary" class="img-fluid" style="max-height: 60px;">
                                </div>
                                <ul class="small text-secondary mb-0">
                                    <li><strong>NSF</strong>: Nilai rata-rata kompetensi pendukung</li>
                                    <li><strong>NS(s,p)</strong>: Total skor soal pendukung</li>
                                    <li><strong>IS</strong>: Jumlah indikator secondary</li>
                                </ul>
                            </div>
                        </div>
                    </div>
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