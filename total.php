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

            // Skip jika perusahaan kosong
            if (empty(trim($perusahaan)))
                continue;

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

// Fungsi untuk menghitung nilai akhir (60% CF + 40% SF)
function calculateFinalScore($cf, $sf)
{
    return ($cf * 0.6) + ($sf * 0.4);
}
?>

<style>
    /* Tabel Total: layout fixed agar muat di desktop */
    .total-table {
        table-layout: fixed;
        width: 100%;
    }
    .total-table th, .total-table td {
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
        padding: 0.5rem 0.45rem;
    }
    .total-table .col-no   { width: 4%; }
    .total-table .col-nama { width: 13%; white-space: normal; word-break: break-word; }
    .total-table .col-nis  { width: 9%; }
    .total-table .col-cf   { width: 7%; text-align: center; background: linear-gradient(135deg, #dbeafe, #eff6ff); color: #1d4ed8; }
    .total-table .col-sf   { width: 7%; text-align: center; background: linear-gradient(135deg, #d1fae5, #ecfdf5); color: #065f46; }
    .total-table .col-na   { width: 7%; text-align: center; background: linear-gradient(135deg, #fef3c7, #fffbeb); color: #b45309; font-weight: 700; }
    .total-table .col-dyn  { text-align: center; }
    .nisclass { font-size: 0.8rem; }
</style>

<div class="container-fluid container-xl px-4 mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-calculator text-primary me-3"></i> Perhitungan Nilai Total
            </h2>
            <p class="text-secondary mb-0">Hasil akhir berdasarkan komposit Core Factor (60%) dan Secondary Factor (40%)</p>
        </div>
        <button type="button" class="btn btn-light border shadow-sm px-3 py-2" style="border-radius: 10px; font-weight: 500;" data-bs-toggle="modal" data-bs-target="#totalNilaiModal">
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
                if (!empty($soal) || !empty($data['factor'][$index])) { $columns_to_show[$index] = true; }
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
                $dyn_count = count($columns_to_show); // hanya kolom Bobot (bukan pasangan)
                $static_pct = 4 + 13 + 9 + 7 + 7 + 7; // No + Nama + NIS + CF + SF + N(a)
                $dyn_pct = $dyn_count > 0 ? round((100 - $static_pct) / $dyn_count, 1) : 0;
                ?>
                <table class="table table-hover total-table" id="datatable">
                    <thead>
                        <tr>
                            <th class="col-no text-center text-secondary">No</th>
                            <th class="col-nama text-secondary">Nama Siswa</th>
                            <th class="col-nis text-secondary">NIS</th>
                            <?php foreach ($columns_to_show as $index => $value): ?>
                                <th class="col-dyn text-secondary" style="width:<?= $dyn_pct; ?>%">Bobot <?= $index + 1; ?></th>
                            <?php endforeach; ?>
                            <th class="col-cf">CF (60%)</th>
                            <th class="col-sf">SF (40%)</th>
                            <th class="col-na">N(a)</th>
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
                            $final_score = calculateFinalScore($cf, $sf);
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
                                <?php endforeach; ?>
                                <td class="text-center fw-bold" style="color: #1d4ed8;"><?= number_format($cf, 2); ?></td>
                                <td class="text-center fw-bold" style="color: #065f46;"><?= number_format($sf, 2); ?></td>
                                <td class="text-center">
                                    <span class="badge fw-bold px-3 py-2 rounded-pill" style="background-color: #fef3c7; color: #b45309; font-size: 0.85em;">
                                        <?= number_format($final_score, 2); ?>
                                    </span>
                                </td>
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


<!-- Modal Deskripsi Nilai Total -->
<div class="modal fade" id="totalNilaiModal" tabindex="-1" aria-labelledby="totalNilaiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.25);">
            <div class="modal-header border-0 p-4 pb-3" style="background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%); color: white;">
                <h5 class="modal-title fw-bold" id="totalNilaiModalLabel"><i class="fas fa-calculator me-2"></i> Deskripsi: Perhitungan Nilai Total</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-primary rounded-3 mb-4">
                    <strong>Rumus Nilai Total:</strong>
                    <div class="text-center my-2 fw-bold fs-5">N(a) = (60% × NCF) + (40% × NSF)</div>
                    <ul class="small mb-0 mt-2">
                        <li><strong>N(a)</strong>: Nilai akhir siswa</li>
                        <li><strong>NCF</strong>: Nilai rata-rata <em>Core Factor</em></li>
                        <li><strong>NSF</strong>: Nilai rata-rata <em>Secondary Factor</em></li>
                    </ul>
                </div>
                <h6 class="fw-bold text-secondary mb-3 text-uppercase small">Contoh Perhitungan</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle border rounded-3 overflow-hidden">
                        <thead style="background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
                            <tr>
                                <th class="text-secondary text-center">No</th>
                                <th class="text-secondary">Nama</th>
                                <th class="text-center" style="color:#1d4ed8;">CF (60%)</th>
                                <th class="text-center" style="color:#065f46;">SF (40%)</th>
                                <th class="text-center" style="color:#b45309;">N(a)</th>
                                <th class="text-secondary small">Proses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center">1</td><td>Budi</td>
                                <td class="text-center fw-bold" style="color:#1d4ed8;">4.00</td>
                                <td class="text-center fw-bold" style="color:#065f46;">4.00</td>
                                <td class="text-center"><span class="badge fw-bold rounded-pill" style="background:#fef3c7;color:#b45309;border:1px solid #b4530940;">4.00</span></td>
                                <td class="small text-secondary">(60%×4)+(40%×4)=4</td>
                            </tr>
                            <tr>
                                <td class="text-center">2</td><td>Dani</td>
                                <td class="text-center fw-bold" style="color:#1d4ed8;">3.50</td>
                                <td class="text-center fw-bold" style="color:#065f46;">4.00</td>
                                <td class="text-center"><span class="badge fw-bold rounded-pill" style="background:#fef3c7;color:#b45309;border:1px solid #b4530940;">3.70</span></td>
                                <td class="small text-secondary">(60%×3.5)+(40%×4)=3.7</td>
                            </tr>
                            <tr>
                                <td class="text-center">3</td><td>Nina</td>
                                <td class="text-center fw-bold" style="color:#1d4ed8;">4.00</td>
                                <td class="text-center fw-bold" style="color:#065f46;">3.50</td>
                                <td class="text-center"><span class="badge fw-bold rounded-pill" style="background:#fef3c7;color:#b45309;border:1px solid #b4530940;">3.80</span></td>
                                <td class="small text-secondary">(60%×4)+(40%×3.5)=3.8</td>
                            </tr>
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

<!-- Script untuk MathJax -->
<script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
<?php
include 'layout/footer.php';
?>