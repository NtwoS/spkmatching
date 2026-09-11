<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
if (!function_exists('calculateFinalScore')) {
    function calculateFinalScore($cf, $sf)
    {
        return ($cf * 0.6) + ($sf * 0.4);
    }
}

// Mengambil data dari database
$data_hasilsiswa = select("SELECT * FROM datasiswa ORDER BY id_siswa DESC");
$data_perusahaan_raw = select("SELECT * FROM perusahaan ORDER BY id_perusahaan DESC");

// Mengelompokkan data siswa berdasarkan perusahaan dan nama siswa
$grouped_data = [];
$all_students = [];

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

            if (!isset($all_students[$siswa])) {
                $all_students[$siswa] = [
                    'siswa' => $siswa,
                    'nis' => $nis
                ];
            }

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

// Kuota per perusahaan (default: 5, bisa diubah ke 4 melalui filter)
$kuota = isset($_GET['kuota']) ? (int) $_GET['kuota'] : 5;
if ($kuota < 1) $kuota = 5;

// Hitung skor akhir untuk semua pasangan (siswa, perusahaan)
$all_matches = [];
$scores_by_company = []; // [perusahaan][siswa] = score

foreach ($grouped_data as $perusahaan => $siswas) {
    $scores_by_company[$perusahaan] = [];
    foreach ($siswas as $siswa => $data) {
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

        $all_matches[] = [
            'siswa' => $siswa,
            'nis' => $data['nis'],
            'perusahaan' => $perusahaan,
            'score' => $final_score
        ];

        $scores_by_company[$perusahaan][$siswa] = $final_score;
    }

    // Urutkan nilai tiap perusahaan untuk tahu ranking lokal siswa di perusahaan tsb
    arsort($scores_by_company[$perusahaan]);
}

// -------------------------------------------------------------
// ALGORITMA PENEMPATAN OPTIMAL (1 SISWA = 1 PERUSAHAAN)
// -------------------------------------------------------------
// Urutkan semua pasangan dari skor kecocokan tertinggi ke terendah
usort($all_matches, function ($a, $b) {
    return $b['score'] <=> $a['score'];
});

$assigned_students = [];      // siswa => [perusahaan, score, nis, rank_in_company]
$company_assignments = [];   // perusahaan => list of siswa
$company_slot_count = [];    // perusahaan => count

foreach (array_keys($grouped_data) as $p) {
    $company_assignments[$p] = [];
    $company_slot_count[$p] = 0;
}

// Alokasi tahap 1: Tempatkan siswa ke perusahaan dengan kecocokan tertinggi jika kuota masih ada
foreach ($all_matches as $match) {
    $s = $match['siswa'];
    $p = $match['perusahaan'];

    if (isset($assigned_students[$s])) {
        continue; // Siswa sudah mendapatkan tempat
    }

    if ($company_slot_count[$p] < $kuota) {
        // Hitung ranking siswa di perusahaan tersebut
        $rank_in_company = 1;
        foreach ($scores_by_company[$p] as $comp_siswa => $comp_score) {
            if ($comp_siswa === $s) break;
            $rank_in_company++;
        }

        $assigned_students[$s] = [
            'siswa' => $s,
            'nis' => $match['nis'],
            'perusahaan' => $p,
            'score' => $match['score'],
            'rank_in_company' => $rank_in_company
        ];

        $company_assignments[$p][] = [
            'siswa' => $s,
            'nis' => $match['nis'],
            'score' => $match['score'],
            'rank_in_company' => $rank_in_company
        ];

        $company_slot_count[$p]++;
    }
}

// Siswa yang belum teralokasi (jika ada)
$unassigned_students = [];
foreach ($all_students as $siswa => $info) {
    if (!isset($assigned_students[$siswa])) {
        // Cari skor tertinggi yang diperoleh siswa ini
        $best_score = 0;
        $best_comp = '-';
        foreach ($grouped_data as $p => $siswas) {
            if (isset($scores_by_company[$p][$siswa]) && $scores_by_company[$p][$siswa] > $best_score) {
                $best_score = $scores_by_company[$p][$siswa];
                $best_comp = $p;
            }
        }
        $unassigned_students[$siswa] = [
            'siswa' => $siswa,
            'nis' => $info['nis'],
            'best_score' => $best_score,
            'best_perusahaan' => $best_comp
        ];
    }
}

// Urutkan siswa yang sudah ditempatkan berdasarkan nama
ksort($assigned_students);

$total_siswa = count($all_students);
$total_diterima = count($assigned_students);
$total_belum = count($unassigned_students);
$total_perusahaan = count($grouped_data);
?>

<div class="container-fluid container-xl mt-4 mb-5">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill fw-semibold">
                    <i class="fas fa-network-wired me-1"></i> Sistem Distribusi Penempatan
                </span>
            </div>
            <h2 class="fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="fas fa-map-marked-alt text-primary me-3"></i> Pemetaan & Penempatan Siswa PKL
            </h2>
            <p class="text-secondary mb-0">Pemetaan resmi setiap siswa ke satu perusahaan mitra berdasarkan nilai kecocokan tertinggi (1 Siswa = 1 Tempat PKL).</p>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <!-- Filter Kuota -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle px-3 py-2 shadow-sm rounded-3 fw-medium bg-white" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-sliders-h me-1 text-primary"></i> Kuota: <strong><?= $kuota ?> Siswa / Perusahaan</strong>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                    <li><h6 class="dropdown-header">Pilih Batas Kuota Perusahaan</h6></li>
                    <li><a class="dropdown-item <?= $kuota == 4 ? 'active' : '' ?>" href="penempatan.php?kuota=4"><i class="fas fa-user-friends me-2"></i> Kuota 4 Siswa</a></li>
                    <li><a class="dropdown-item <?= $kuota == 5 ? 'active' : '' ?>" href="penempatan.php?kuota=5"><i class="fas fa-users me-2"></i> Kuota 5 Siswa (Standar)</a></li>
                    <li><a class="dropdown-item <?= $kuota == 6 ? 'active' : '' ?>" href="penempatan.php?kuota=6"><i class="fas fa-user-plus me-2"></i> Kuota 6 Siswa</a></li>
                </ul>
            </div>
            
            <!-- Tombol Cetak / Ekspor -->
            <button onclick="printTabelPenempatan()" class="btn btn-success px-3 py-2 shadow-sm rounded-3 fw-medium">
                <i class="fas fa-print me-1"></i> Cetak Hasil
            </button>
        </div>
    </div>

    <!-- Statistik KPI Cards -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Total Siswa Terdaftar</span>
                        <h3 class="fw-bold text-dark mt-1 mb-0"><?= $total_siswa ?></h3>
                        <small class="text-primary fw-medium"><i class="fas fa-user-graduate me-1"></i> Data Siswa Aktif</small>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width: 52px; height: 52px; font-size: 1.4rem;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Perusahaan Mitra</span>
                        <h3 class="fw-bold text-dark mt-1 mb-0"><?= $total_perusahaan ?></h3>
                        <small class="text-info fw-medium"><i class="fas fa-building me-1"></i> Kuota max: <?= $total_perusahaan * $kuota ?> Kursi</small>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info" style="width: 52px; height: 52px; font-size: 1.4rem;">
                        <i class="fas fa-briefcase"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Siswa Ditempatkan</span>
                        <h3 class="fw-bold text-success mt-1 mb-0"><?= $total_diterima ?></h3>
                        <small class="text-success fw-medium"><i class="fas fa-check-circle me-1"></i> <?= $total_siswa > 0 ? round(($total_diterima / $total_siswa) * 100) : 0 ?>% Sukses Terplot</small>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width: 52px; height: 52px; font-size: 1.4rem;">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Belum Ditempatkan</span>
                        <h3 class="fw-bold <?= $total_belum > 0 ? 'text-danger' : 'text-secondary' ?> mt-1 mb-0"><?= $total_belum ?></h3>
                        <small class="<?= $total_belum > 0 ? 'text-danger' : 'text-muted' ?> fw-medium">
                            <i class="fas <?= $total_belum > 0 ? 'fa-exclamation-circle' : 'fa-check' ?> me-1"></i> <?= $total_belum > 0 ? 'Perlu Alokasi Manual' : 'Semua Terdistribusi' ?>
                        </small>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center <?= $total_belum > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-secondary bg-opacity-10 text-secondary' ?>" style="width: 52px; height: 52px; font-size: 1.4rem;">
                        <i class="fas <?= $total_belum > 0 ? 'fa-user-clock' : 'fa-smile' ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Solusi & Saran (Accordion/Collapse Box) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 no-print" style="background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%); border-left: 5px solid #4361ee !important;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width: 40px; height: 40px;">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Panduan Kebijakan: Penanganan Siswa & Kuota PKL</h5>
                        <p class="text-secondary small mb-0">
                            Sistem secara otomatis mendistribusikan siswa ke perusahaan terbaiknya berdasarkan skor kecocokan tertinggi dengan batas kuota <strong><?= $kuota ?> siswa per perusahaan</strong>. 
                            Pelajari bagaimana cara menangani siswa yang belum mendapat tempat PKL.
                        </p>
                    </div>
                </div>
                <button class="btn btn-sm btn-primary px-3 py-2 rounded-pill text-nowrap fw-medium" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSaran" aria-expanded="false" aria-controls="collapseSaran">
                    <i class="fas fa-info-circle me-1"></i> Baca Solusi & Saran
                </button>
            </div>

            <div class="collapse mt-3 pt-3 border-top border-primary border-opacity-10" id="collapseSaran">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 bg-white rounded-3 shadow-sm h-100 border">
                            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-arrows-split-up-and-left me-2"></i> 1. Alokasi Otomatis (Second-Best)</h6>
                            <p class="small text-secondary mb-0">
                                Total kapasitas 20 perusahaan adalah <?= $total_perusahaan * $kuota ?> siswa. Jika siswa kalah bersaing di perusahaan pilihan pertama, sistem otomatis memindahkannya ke perusahaan berikutnya yang kuotanya masih tersedia.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white rounded-3 shadow-sm h-100 border">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-users-cog me-2"></i> 2. Penambahan Kuota Khusus</h6>
                            <p class="small text-secondary mb-0">
                                Sekolah dapat mengajukan penambahan kuota dari 4 menjadi 5 siswa pada instansi/perusahaan yang bersedia menampung lebih banyak siswa PKL.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white rounded-3 shadow-sm h-100 border">
                            <h6 class="fw-bold text-success mb-2"><i class="fas fa-school me-2"></i> 3. Magang Internal / TEFA</h6>
                            <p class="small text-secondary mb-0">
                                Jika ada siswa yang nilainya belum memenuhi kriteria industri manapun, siswa dapat diarahkan melaksanakan magang di unit produksi atau laboratorium komputer internal sekolah (Teaching Factory).
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 no-print" id="penempatanTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-4 py-2 rounded-pill fw-semibold shadow-sm" id="siswa-tab" data-bs-toggle="pill" data-bs-target="#tab-siswa" type="button" role="tab">
                <i class="fas fa-id-card me-2"></i> Hasil Penempatan Siswa (<?= $total_diterima ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 py-2 rounded-pill fw-semibold shadow-sm" id="perusahaan-tab" data-bs-toggle="pill" data-bs-target="#tab-perusahaan" type="button" role="tab">
                <i class="fas fa-building me-2"></i> Distribusi per Perusahaan (<?= $total_perusahaan ?>)
            </button>
        </li>
        <?php if ($total_belum > 0): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 py-2 rounded-pill fw-semibold shadow-sm text-danger" id="unassigned-tab" data-bs-toggle="pill" data-bs-target="#tab-unassigned" type="button" role="tab">
                <i class="fas fa-user-clock me-2"></i> Belum Ditempatkan (<?= $total_belum ?>)
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="penempatanTabContent">
        
        <!-- TAB 1: PENEMPATAN PER SISWA -->
        <div class="tab-pane fade show active" id="tab-siswa" role="tabpanel" aria-labelledby="siswa-tab">
            <div class="custom-table-container p-4 bg-white rounded-4 shadow-sm">
                <!-- Header Web Biasa -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 pb-3 border-bottom gap-2 no-print">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Daftar Definitif Penempatan Siswa</h5>
                        <p class="text-secondary small mb-0">Setiap siswa telah diplot ke tepat 1 perusahaan mitra terbaik.</p>
                    </div>
                    <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill">
                        Menampilkan <?= count($assigned_students) ?> Siswa
                    </span>
                </div>

                <!-- Header Dokumen Khusus Hasil Cetak (Kop Resmi) -->
                <div class="print-only mb-3">
                    <div class="d-flex align-items-center justify-content-center gap-3 pb-2" style="display: flex !important; align-items: center; justify-content: center; gap: 15px;">
                        <img src="img/smk.jpg" alt="Logo SMK" style="width: 65px; height: 65px; object-fit: contain;">
                        <div class="text-center" style="text-align: center;">
                            <h4 class="fw-bold mb-0 text-uppercase" style="margin: 0; font-size: 14pt; font-weight: 800; color: #000; letter-spacing: 0.5px;">SMK NEGERI 1 PINRANG</h4>
                            <h5 class="fw-bold mb-0 text-uppercase" style="margin: 2px 0 0 0; font-size: 11pt; font-weight: 700; color: #000; letter-spacing: 0.5px;">DAFTAR PENEMPATAN SISWA PRAKTEK KERJA LAPANGAN (PKL)</h5>
                        </div>
                    </div>
                    <div style="border-bottom: 2px solid #000; border-top: 1px solid #000; height: 4px; margin-top: 4px; margin-bottom: 12px;"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="datatablePenempatan" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="text-center col-no" width="6%">No</th>
                                <th class="col-siswa" style="min-width: 180px;">Nama Siswa</th>
                                <th class="col-nis text-start" width="15%">NIS</th>
                                <th class="col-perusahaan" style="min-width: 250px;">Perusahaan Diterima</th>
                                <th class="text-center col-nilai" width="10%">Nilai Akhir</th>
                                <th class="text-center col-status" width="12%">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php foreach ($assigned_students as $student): ?>
                                <tr>
                                    <td class="text-center fw-semibold col-no text-dark"><?= $no++ ?></td>
                                    <td class="col-siswa fw-bold text-dark">
                                        <?= htmlspecialchars($student['siswa']) ?>
                                    </td>
                                    <td class="col-nis text-start text-dark">
                                        <?= htmlspecialchars($student['nis']) ?>
                                    </td>
                                    <td class="col-perusahaan text-dark">
                                        <?= htmlspecialchars($student['perusahaan']) ?>
                                    </td>
                                    <td class="text-center col-nilai fw-bold text-dark">
                                        <?= number_format($student['score'], 2) ?>
                                    </td>
                                    <td class="text-center col-status">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fw-semibold">
                                            Diterima
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: DISTRIBUSI PER PERUSAHAAN -->
        <div class="tab-pane fade" id="tab-perusahaan" role="tabpanel" aria-labelledby="perusahaan-tab">
            <div class="row g-4">
                <?php $comp_no = 1; ?>
                <?php foreach ($company_assignments as $company_name => $students_in_comp): ?>
                    <?php 
                    $terisi = count($students_in_comp);
                    $persentase = round(($terisi / $kuota) * 100);
                    $is_full = ($terisi >= $kuota);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <span class="badge bg-light text-muted border px-2 py-1 rounded">#<?= $comp_no++ ?></span>
                                    <?php if ($terisi > 0): ?>
                                        <span class="badge <?= $is_full ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle' ?> px-2 py-1 rounded-pill">
                                            <i class="fas <?= $is_full ? 'fa-lock' : 'fa-door-open' ?> me-1"></i> <?= $is_full ? 'Kuota Penuh' : 'Masih Ada Kuota' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">
                                            Kosong
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="fw-bold text-dark mb-1" style="min-height: 42px; line-height: 1.3;">
                                    <?= htmlspecialchars($company_name) ?>
                                </h6>
                                <div class="d-flex justify-content-between align-items-center small text-secondary mt-2 mb-1">
                                    <span>Keterisian Kuota</span>
                                    <span class="fw-bold text-dark"><?= $terisi ?> / <?= $kuota ?> Siswa</span>
                                </div>
                                <div class="progress" style="height: 6px; border-radius: 10px;">
                                    <div class="progress-bar <?= $is_full ? 'bg-danger' : 'bg-primary' ?>" role="progressbar" style="width: <?= min($persentase, 100) ?>%"></div>
                                </div>
                            </div>

                            <div class="card-body px-4 pb-4 pt-3">
                                <h6 class="small fw-bold text-uppercase text-secondary mb-2">
                                    <i class="fas fa-users me-1"></i> Siswa yang Diterima (<?= $terisi ?>)
                                </h6>
                                <?php if (!empty($students_in_comp)): ?>
                                    <ul class="list-group list-group-flush small">
                                        <?php foreach ($students_in_comp as $idx => $s_item): ?>
                                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center bg-transparent border-light">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-light text-secondary border rounded-circle" style="width: 22px; height: 22px; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem;">
                                                        <?= $idx + 1 ?>
                                                    </span>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($s_item['siswa']) ?></div>
                                                        <span class="text-muted" style="font-size: 0.78rem;">NIS: <?= htmlspecialchars($s_item['nis']) ?></span>
                                                    </div>
                                                </div>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                                    <?= number_format($s_item['score'], 2) ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted bg-light rounded-3">
                                        <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                                        <p class="small mb-0">Belum ada siswa yang dialokasikan ke sini.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 3: SISWA BELUM DITEMPATKAN -->
        <?php if ($total_belum > 0): ?>
        <div class="tab-pane fade" id="tab-unassigned" role="tabpanel" aria-labelledby="unassigned-tab">
            <div class="custom-table-container p-4 bg-white rounded-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold text-danger mb-1"><i class="fas fa-exclamation-triangle me-2"></i> Siswa Menunggu Alokasi / Waiting List</h5>
                        <p class="text-secondary small mb-0">Siswa ini belum mendapatkan kuota di perusahaan pilihan pertamanya karena kuota telah terisi penuh.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Nama Siswa</th>
                                <th>NIS</th>
                                <th>Nilai Tertinggi</th>
                                <th>Perusahaan Pilihan Awal</th>
                                <th>Rekomendasi Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $un_no = 1; ?>
                            <?php foreach ($unassigned_students as $un_s): ?>
                                <tr>
                                    <td><?= $un_no++ ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($un_s['siswa']) ?></td>
                                    <td><?= htmlspecialchars($un_s['nis']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill fw-bold">
                                            <?= number_format($un_s['best_score'], 2) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($un_s['best_perusahaan']) ?></td>
                                    <td>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill">
                                            <i class="fas fa-exchange-alt me-1"></i> Alihkan ke Mitra dengan Sisa Kuota
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- DataTables initialization & Print Script -->
<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#datatablePenempatan').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json',
                search: "Cari Siswa / Perusahaan:",
                searchPlaceholder: "Ketik nama siswa/mitra..."
            },
            pageLength: 10,
            responsive: false
        });
    }
});

// Fungsi khusus cetak agar hanya tabel siswa yang dicetak dan semua baris ditampilkan
function printTabelPenempatan() {
    // Pastikan tab siswa aktif
    var triggerEl = document.querySelector('#siswa-tab');
    if (triggerEl) {
        var tab = bootstrap.Tab.getOrCreateInstance(triggerEl);
        tab.show();
    }
    
    // Tampilkan semua baris DataTables sebelum print
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#datatablePenempatan')) {
        var dt = $('#datatablePenempatan').DataTable();
        var origLen = dt.page.len();
        dt.page.len(-1).draw();
        
        setTimeout(function() {
            window.print();
            dt.page.len(origLen).draw();
        }, 300);
    } else {
        window.print();
    }
}

// Antisipasi jika user menekan Ctrl + P langsung
window.addEventListener('beforeprint', function() {
    var triggerEl = document.querySelector('#siswa-tab');
    if (triggerEl) {
        var tab = bootstrap.Tab.getOrCreateInstance(triggerEl);
        tab.show();
    }
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#datatablePenempatan')) {
        window.dtOriginalLen = $('#datatablePenempatan').DataTable().page.len();
        $('#datatablePenempatan').DataTable().page.len(-1).draw();
    }
});

window.addEventListener('afterprint', function() {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#datatablePenempatan') && window.dtOriginalLen !== undefined) {
        $('#datatablePenempatan').DataTable().page.len(window.dtOriginalLen).draw();
    }
});
</script>

<style>
.print-only {
    display: none !important;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm 10mm 8mm 10mm;
    }

    /* Sembunyikan semua elemen web yang tidak perlu */
    .navbar, 
    .no-print, 
    #penempatanTabs, 
    #tab-perusahaan, 
    #tab-unassigned, 
    .dataTables_filter, 
    .dataTables_length, 
    .dataTables_paginate, 
    .dataTables_info,
    footer,
    .modal {
        display: none !important;
    }

    .print-only {
        display: block !important;
    }

    html, body {
        background: #ffffff !important;
        color: #000000 !important;
        font-family: Arial, Helvetica, sans-serif !important;
        font-size: 8.5pt !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .container, .container-fluid, .container-xl {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .tab-content {
        margin: 0 !important;
        padding: 0 !important;
    }

    #tab-siswa {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .custom-table-container {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
        width: 100% !important;
    }

    .table-responsive {
        overflow: visible !important;
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Tabel presisi pas 1 halaman lebar A4 bersih, rapi, dan tidak kotor */
    #datatablePenempatan {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        font-size: 9pt !important;
        margin: 0 !important;
        border: 1px solid #000000 !important;
    }

    #datatablePenempatan th, 
    #datatablePenempatan td {
        min-width: 0 !important;
        padding: 6px 8px !important;
        border: 1px solid #000000 !important;
        color: #000000 !important;
        white-space: normal !important;
        word-wrap: break-word !important;
        word-break: break-word !important;
        vertical-align: middle !important;
        line-height: 1.3 !important;
    }

    #datatablePenempatan thead th {
        background-color: #f2f2f2 !important;
        color: #000000 !important;
        font-weight: bold !important;
        text-align: center !important;
        font-size: 9pt !important;
        border: 1px solid #000000 !important;
    }

    /* Pembagian persentase kolom pas 100% tanpa kolom peringkat */
    #datatablePenempatan .col-no { width: 6% !important; text-align: center !important; color: #000000 !important; }
    #datatablePenempatan .col-siswa { width: 26% !important; text-align: left !important; color: #000000 !important; }
    #datatablePenempatan .col-nis { width: 14% !important; text-align: left !important; color: #000000 !important; font-family: inherit !important; }
    #datatablePenempatan .col-perusahaan { width: 38% !important; text-align: left !important; color: #000000 !important; }
    #datatablePenempatan .col-nilai { width: 8% !important; text-align: center !important; color: #000000 !important; }
    #datatablePenempatan .col-status { width: 8% !important; text-align: center !important; color: #000000 !important; }

    #datatablePenempatan tr {
        page-break-inside: avoid !important;
    }

    /* Poloskan teks badge saat cetak */
    .badge {
        border: none !important;
        background: transparent !important;
        color: #000000 !important;
        padding: 0 !important;
        font-size: 9pt !important;
        font-weight: normal !important;
    }
    .badge i, 
    td .rounded-circle, 
    td i {
        display: none !important;
    }
}
</style>

<?php
include 'layout/footer.php';
?>
