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

// Fungsi untuk menghitung nilai akhir (60% CF + 40% SF)
function calculateFinalScore($cf, $sf)
{
    return ($cf * 0.6) + ($sf * 0.4);
}

// Fungsi untuk menghitung gap (jika belum ada di file lain)
if (!function_exists('calculateGap')) {
    function calculateGap($nilai)
    {
        return $nilai; // Ganti dengan logika yang sesuai
    }
}

// Fungsi untuk menghitung bobot (jika belum ada di file lain)
if (!function_exists('calculateBobot')) {
    function calculateBobot($gap)
    {
        return $gap; // Ganti dengan logika yang sesuai
    }
}

// Mengambil data dari database
$data_hasilsiswa = select("SELECT * FROM datasiswa ORDER BY id_siswa DESC");
$data_perusahaan = select("SELECT * FROM perusahaan ORDER BY id_perusahaan DESC");

// Mengelompokkan data siswa berdasarkan perusahaan dan nama siswa
$grouped_data = [];
foreach ($data_hasilsiswa as $hasilsiswa) {
    foreach ($hasilsiswa as $key => $value) {
        if (strpos($key, 'perusahaan') === 0) {
            $i = (int) substr($key, 10);
            $perusahaan_key = 'perusahaan' . $i;
            $soal_key = 'soal' . $i;
            $factor_key = 'factor' . $i;
            $perusahaan = $hasilsiswa[$perusahaan_key];
            $siswa = $hasilsiswa['siswa'];
            $nis = $hasilsiswa['nis'];

            if (empty(trim($perusahaan))) continue;

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

// Pemetaan nama lengkap perusahaan dari tabel perusahaan (agar nama resmi & tidak terpotong)
$perusahaan_names_map = [];
$full_companies = [];
if (!empty($data_perusahaan)) {
    foreach ($data_perusahaan as $dp) {
        $c = trim($dp['perusahaan']);
        if (!empty($c)) $full_companies[] = $c;
    }
}
foreach ($perusahaan_list as $ds_p) {
    $p_trim = trim($ds_p);
    $matched = false;
    foreach ($full_companies as $fc) {
        if ($p_trim === $fc || strpos($fc, $p_trim) === 0) {
            $perusahaan_names_map[$ds_p] = $fc;
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $perusahaan_names_map[$ds_p] = $ds_p;
    }
}
$selected_perusahaan_display = $perusahaan_names_map[$selected_perusahaan] ?? $selected_perusahaan;

// Menghitung nilai akhir (N(a)) dan menyiapkan data untuk ranking
$final_scores = [];
if (isset($grouped_data[$selected_perusahaan])) {
    foreach ($grouped_data[$selected_perusahaan] as $siswa => $data) {
        $cf_sum = 0;
        $cf_count = 0;
        $sf_sum = 0;
        $sf_count = 0;

        foreach ($data['factor'] as $index => $factor) {
            if ($factor === 'core' && isset($data['soal'][$index])) {
                $cf_sum += $data['soal'][$index];
                $cf_count++;
            } elseif ($factor === 'secondary' && isset($data['soal'][$index])) {
                $sf_sum += $data['soal'][$index];
                $sf_count++;
            }
        }

        $cf = $cf_count > 0 ? $cf_sum / $cf_count : 0;
        $sf = $sf_count > 0 ? $sf_sum / $sf_count : 0;
        $final_score = calculateFinalScore($cf, $sf);

        $final_scores[] = [
            'siswa' => $siswa,
            'nis' => $data['nis'],
            'final_score' => $final_score,
            'cf' => $cf,
            'sf' => $sf
        ];
    }

    usort($final_scores, function ($a, $b) {
        return $b['final_score'] <=> $a['final_score'];
    });
}
?>

<div class="container-fluid container-xl mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-trophy text-warning me-3"></i> Ranking Siswa PKL
            </h2>
            <p class="text-secondary mb-0">Peringkat siswa berdasarkan nilai akhir tertinggi ke terendah</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="cetak-excel-all.php?perusahaan=<?= urlencode($selected_perusahaan); ?>" id="btnCetak1" class="btn btn-primary px-3 py-2 shadow-sm rounded-3 fw-medium">
                <i class="fas fa-building me-1"></i> Cetak 1 Perusahaan
            </a>
            <a href="cetak-excel-all.php?mode=all" class="btn btn-success px-3 py-2 shadow-sm rounded-3 fw-medium">
                <i class="fas fa-layer-group me-1"></i> Cetak Semua Perusahaan
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px;">
        <div class="card-body p-4">
            <form method="GET" action="" class="m-0">
                <div class="col-12">
                    <label for="perusahaan" class="form-label fw-semibold text-secondary small text-uppercase mb-2">
                        <i class="fas fa-filter me-1"></i> Filter Perusahaan
                    </label>
                    <select class="form-select border-0 shadow-sm w-100" style="padding: 0.75rem 1rem; border-radius: 10px;" id="perusahaan" name="perusahaan" onchange="this.form.submit()">
                        <?php foreach ($perusahaan_list as $perusahaan):
                            if (!empty(trim($perusahaan))): 
                                $display_perusahaan = $perusahaan_names_map[$perusahaan] ?? $perusahaan;
                            ?>
                                <option value="<?= htmlspecialchars($perusahaan); ?>" <?= $selected_perusahaan == $perusahaan ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($display_perusahaan); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($grouped_data[$selected_perusahaan]) && !empty($final_scores)): ?>
        <div class="custom-table-container p-4">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-light flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill fs-6">
                        <i class="fas fa-building me-1"></i> <?= htmlspecialchars($selected_perusahaan_display); ?>
                    </span>
                    <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill">
                        <?= count($final_scores); ?> Siswa
                    </span>
                </div>
                <a href="cetak-excel-all.php?perusahaan=<?= urlencode($selected_perusahaan); ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium shadow-sm">
                    <i class="fas fa-print me-1"></i> Cetak Laporan Perusahaan Ini
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="datatable" style="white-space: nowrap;">
                    <thead>
                        <tr>
                            <th class="text-center text-secondary" width="8%">Ranking</th>
                            <th class="text-secondary" style="min-width:200px;">Nama Siswa</th>
                            <th class="text-secondary">NIS</th>
                            <th class="text-center text-secondary">Nilai Akhir</th>
                            <th class="text-center text-secondary">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Kuota siswa yang diterima per perusahaan (misal: 5 siswa teratas)
                        $kuota = 5; 
                        $no = 1; 
                        ?>
                        <?php foreach ($final_scores as $score): ?>
                            <?php
                            if ($no === 1) { $color = '#FFD700'; $icon = 'fa-trophy'; $bg = '#fffbeb'; }
                            elseif ($no === 2) { $color = '#94a3b8'; $icon = 'fa-medal'; $bg = '#f8fafc'; }
                            elseif ($no === 3) { $color = '#cd7f32'; $icon = 'fa-award'; $bg = '#fef7ec'; }
                            else { $color = '#94a3b8'; $icon = 'fa-hashtag'; $bg = 'transparent'; }
                            ?>
                            <tr style="<?= $no <= 3 ? "background-color: $bg;" : ''; ?>">
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1 fw-bold" style="color: <?= $color ?>;">
                                        <i class="fas <?= $icon; ?>"></i>
                                        <span><?= $no; ?></span>
                                    </div>
                                </td>
                                <td class="fw-bold <?= $no <= 3 ? 'text-dark' : ''; ?>"><?= htmlspecialchars($score['siswa']); ?></td>
                                <td><span class="text-secondary bg-light px-2 py-1 rounded border" style="font-family:monospace;"><?= htmlspecialchars($score['nis']); ?></span></td>
                                <td class="text-center">
                                    <span class="badge fw-bold px-3 py-2 rounded-pill" style="background-color: <?= $no === 1 ? '#FFF3CD' : ($no === 2 ? '#f1f5f9' : ($no === 3 ? '#fef7ec' : '#f1f5f9')); ?>; color: <?= $color; ?>; font-size: 0.9em; border: 1px solid <?= $color; ?>40;">
                                        <?= number_format($score['final_score'], 2); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($no <= $kuota): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-semibold">
                                            <i class="fas fa-check-circle me-1"></i> Diterima
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2 rounded-pill fw-normal">
                                            <i class="fas fa-minus-circle me-1"></i> Belum Lolos
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php $no++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm" style="border-radius: 20px;">
            <div class="card-body p-5 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-light text-secondary rounded-circle mb-3" style="width:100px;height:100px;font-size:2.5rem;"><i class="fas fa-trophy"></i></div>
                <h4 class="fw-bold text-dark mb-2">Belum Ada Ranking</h4>
                <p class="text-secondary mb-0">Belum ada data penilaian untuk perusahaan yang dipilih.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var select = document.getElementById('perusahaan');
    if (select) {
        select.addEventListener('change', function() {
            var val = this.value;
            var btn = document.getElementById('btnCetak1');
            if (btn) btn.href = 'cetak-excel-all.php?perusahaan=' + encodeURIComponent(val);
        });
    }
});
</script>

<?php
include 'layout/footer.php';
?>