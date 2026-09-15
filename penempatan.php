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

<div class="container-fluid container-xl mt-4 mb-5 penempatan-page">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print page-header">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge placement-kicker">
                    <i class="fas fa-network-wired me-1"></i> Sistem Distribusi Penempatan
                </span>
            </div>
            <h2 class="fw-bold mb-1 d-flex align-items-center page-title">
                <i class="fas fa-map-marked-alt me-3"></i> Pemetaan & Penempatan Siswa PKL
            </h2>
            <p class="text-secondary mb-0 page-subtitle">Pemetaan resmi setiap siswa ke satu perusahaan mitra berdasarkan nilai kecocokan tertinggi (1 Siswa = 1 Tempat PKL).</p>
        </div>

        <div class="d-flex align-items-center gap-2 page-actions">
            <!-- Filter Kuota -->
            <div class="dropdown">
                <button class="btn dropdown-toggle placement-quota-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-sliders-h me-1 text-primary"></i> Kuota: <strong><?= $kuota ?> Siswa / Perusahaan</strong>
                </button>
                <ul class="dropdown-menu dropdown-menu-end placement-quota-menu">
                    <li><h6 class="dropdown-header">Pilih Batas Kuota Perusahaan</h6></li>
                    <li><a class="dropdown-item <?= $kuota == 4 ? 'active' : '' ?>" href="penempatan.php?kuota=4"><i class="fas fa-user-friends me-2"></i> Kuota 4 Siswa</a></li>
                    <li><a class="dropdown-item <?= $kuota == 5 ? 'active' : '' ?>" href="penempatan.php?kuota=5"><i class="fas fa-users me-2"></i> Kuota 5 Siswa (Standar)</a></li>
                    <li><a class="dropdown-item <?= $kuota == 6 ? 'active' : '' ?>" href="penempatan.php?kuota=6"><i class="fas fa-user-plus me-2"></i> Kuota 6 Siswa</a></li>
                </ul>
            </div>
            
            <!-- Tombol Cetak / Ekspor -->
            <button type="button" onclick="printTabelPenempatan()" class="btn btn-primary placement-print-button">
                <i class="fas fa-print me-1"></i> Cetak Hasil
            </button>
        </div>
    </div>

    <!-- Statistik KPI Cards -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between kpi-body">
                    <div>
                        <span class="small kpi-label">Total Siswa Terdaftar</span>
                        <h3 class="fw-bold mt-1 mb-0 kpi-value"><?= $total_siswa ?></h3>
                        <small class="fw-medium kpi-meta kpi-meta--accent"><i class="fas fa-user-graduate me-1"></i> Data Siswa Aktif</small>
                    </div>
                    <div class="d-flex align-items-center justify-content-center kpi-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between kpi-body">
                    <div>
                        <span class="small kpi-label">Perusahaan Mitra</span>
                        <h3 class="fw-bold mt-1 mb-0 kpi-value"><?= $total_perusahaan ?></h3>
                        <small class="fw-medium kpi-meta kpi-meta--accent"><i class="fas fa-building me-1"></i> Kuota max: <?= $total_perusahaan * $kuota ?> kursi</small>
                    </div>
                    <div class="d-flex align-items-center justify-content-center kpi-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between kpi-body">
                    <div>
                        <span class="small kpi-label">Siswa Ditempatkan</span>
                        <h3 class="fw-bold mt-1 mb-0 kpi-value kpi-value--success"><?= $total_diterima ?></h3>
                        <small class="fw-medium kpi-meta kpi-meta--success"><i class="fas fa-check-circle me-1"></i> <?= $total_siswa > 0 ? round(($total_diterima / $total_siswa) * 100) : 0 ?>% sukses terplot</small>
                    </div>
                    <div class="d-flex align-items-center justify-content-center kpi-icon kpi-icon--success">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between kpi-body">
                    <div>
                        <span class="small kpi-label">Belum Ditempatkan</span>
                        <h3 class="fw-bold mt-1 mb-0 kpi-value <?= $total_belum > 0 ? 'kpi-value--danger' : 'kpi-value--muted' ?>"><?= $total_belum ?></h3>
                        <small class="fw-medium kpi-meta <?= $total_belum > 0 ? 'kpi-meta--danger' : 'kpi-meta--muted' ?>">
                            <i class="fas <?= $total_belum > 0 ? 'fa-exclamation-circle' : 'fa-check' ?> me-1"></i> <?= $total_belum > 0 ? 'Perlu Alokasi Manual' : 'Semua Terdistribusi' ?>
                        </small>
                    </div>
                    <div class="d-flex align-items-center justify-content-center kpi-icon <?= $total_belum > 0 ? 'kpi-icon--danger' : 'kpi-icon--muted' ?>">
                        <i class="fas <?= $total_belum > 0 ? 'fa-user-clock' : 'fa-smile' ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Solusi & Saran (Accordion/Collapse Box) -->
    <div class="card policy-card mb-4 no-print">
        <div class="card-body p-4 policy-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="d-flex align-items-center justify-content-center flex-shrink-0 mt-1 policy-icon">
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
                <button class="btn btn-sm btn-primary text-nowrap fw-medium policy-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSaran" aria-expanded="false" aria-controls="collapseSaran">
                    <i class="fas fa-info-circle me-1"></i> Baca Solusi & Saran
                </button>
            </div>

            <div class="collapse mt-3 pt-3 border-top border-primary border-opacity-10 policy-details" id="collapseSaran">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 h-100 policy-detail">
                            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-arrows-split-up-and-left me-2"></i> 1. Alokasi Otomatis (Second-Best)</h6>
                            <p class="small text-secondary mb-0">
                                Total kapasitas 20 perusahaan adalah <?= $total_perusahaan * $kuota ?> siswa. Jika siswa kalah bersaing di perusahaan pilihan pertama, sistem otomatis memindahkannya ke perusahaan berikutnya yang kuotanya masih tersedia.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 h-100 policy-detail">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-users-cog me-2"></i> 2. Penambahan Kuota Khusus</h6>
                            <p class="small text-secondary mb-0">
                                Sekolah dapat mengajukan penambahan kuota dari 4 menjadi 5 siswa pada instansi/perusahaan yang bersedia menampung lebih banyak siswa PKL.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 h-100 policy-detail">
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
    <ul class="nav nav-pills mb-4 gap-2 no-print placement-tabs" id="penempatanTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="siswa-tab" data-bs-toggle="pill" data-bs-target="#tab-siswa" type="button" role="tab">
                <i class="fas fa-id-card me-2"></i> Hasil Penempatan Siswa (<?= $total_diterima ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="perusahaan-tab" data-bs-toggle="pill" data-bs-target="#tab-perusahaan" type="button" role="tab">
                <i class="fas fa-building me-2"></i> Distribusi per Perusahaan (<?= $total_perusahaan ?>)
            </button>
        </li>
        <?php if ($total_belum > 0): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-danger" id="unassigned-tab" data-bs-toggle="pill" data-bs-target="#tab-unassigned" type="button" role="tab">
                <i class="fas fa-user-clock me-2"></i> Belum Ditempatkan (<?= $total_belum ?>)
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="penempatanTabContent">
        
        <!-- TAB 1: PENEMPATAN PER SISWA -->
        <div class="tab-pane fade show active" id="tab-siswa" role="tabpanel" aria-labelledby="siswa-tab">
            <div class="custom-table-container p-4 placement-panel">
                <!-- Header Web Biasa -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 pb-3 gap-2 no-print placement-panel-header">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Daftar Definitif Penempatan Siswa</h5>
                        <p class="text-secondary small mb-0">Setiap siswa telah diplot ke tepat 1 perusahaan mitra terbaik.</p>
                    </div>
                    <span class="badge bg-light text-secondary border px-3 py-2">
                        Menampilkan <?= count($assigned_students) ?> Siswa
                    </span>
                </div>

                <!-- Header Dokumen Khusus Hasil Cetak (Kop Resmi) -->
                <div class="print-only mb-3 print-report-header">
                    <div class="d-flex align-items-center justify-content-center gap-3 pb-2 print-report-brand">
                        <img src="img/smk.jpg" alt="Logo SMK Negeri 1 Pinrang" class="print-report-logo">
                        <div class="text-center print-report-copy">
                            <h4 class="fw-bold mb-0 text-uppercase print-report-school">SMK NEGERI 1 PINRANG</h4>
                            <h5 class="fw-bold mb-0 text-uppercase print-report-title">DAFTAR PENEMPATAN SISWA PRAKTEK KERJA LAPANGAN (PKL)</h5>
                        </div>
                    </div>
                    <div class="print-report-rule"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="datatablePenempatan">
                        <thead>
                            <tr>
                                <th class="text-center col-no" width="6%">No</th>
                                <th class="col-siswa">Nama Siswa</th>
                                <th class="col-nis text-start" width="15%">NIS</th>
                                <th class="col-perusahaan">Perusahaan Diterima</th>
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
                                        <span class="badge placement-status">
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
                        <div class="card company-card h-100">
                            <div class="card-header pt-4 px-4 pb-2 company-card-header">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <span class="badge bg-light text-muted border px-2 py-1 rounded">#<?= $comp_no++ ?></span>
                                    <?php if ($terisi > 0): ?>
                                        <span class="badge <?= $is_full ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle' ?> px-2 py-1">
                                            <i class="fas <?= $is_full ? 'fa-lock' : 'fa-door-open' ?> me-1"></i> <?= $is_full ? 'Kuota Penuh' : 'Masih Ada Kuota' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                            Kosong
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="fw-bold mb-1 company-card-title">
                                    <?= htmlspecialchars($company_name) ?>
                                </h6>
                                <div class="d-flex justify-content-between align-items-center small text-secondary mt-2 mb-1 company-capacity">
                                    <span>Keterisian Kuota</span>
                                    <span class="fw-bold text-dark"><?= $terisi ?> / <?= $kuota ?> Siswa</span>
                                </div>
                                <div class="progress company-progress">
                                    <div class="progress-bar <?= $is_full ? 'bg-danger' : 'bg-primary' ?> company-progress-bar" role="progressbar" style="width: <?= min($persentase, 100) ?>%"></div>
                                </div>
                            </div>

                            <div class="card-body px-4 pb-4 pt-3 company-card-body">
                                <h6 class="small fw-bold text-uppercase text-secondary mb-2">
                                    <i class="fas fa-users me-1"></i> Siswa yang Diterima (<?= $terisi ?>)
                                </h6>
                                <?php if (!empty($students_in_comp)): ?>
                                    <ul class="list-group list-group-flush small">
                                        <?php foreach ($students_in_comp as $idx => $s_item): ?>
                                            <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center bg-transparent border-light">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge student-index">
                                                        <?= $idx + 1 ?>
                                                    </span>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($s_item['siswa']) ?></div>
                                                        <span class="text-muted student-nis">NIS: <?= htmlspecialchars($s_item['nis']) ?></span>
                                                    </div>
                                                </div>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
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
            <div class="custom-table-container p-4 placement-panel unassigned-panel">
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
                                        <span class="badge bg-secondary-subtle text-secondary px-3 py-2 fw-bold">
                                            <?= number_format($un_s['best_score'], 2) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($un_s['best_perusahaan']) ?></td>
                                    <td>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2">
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
        font-family: 'Inter', sans-serif !important;
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
    #datatablePenempatan .col-no { width: 5% !important; text-align: center !important; color: #000000 !important; }
    #datatablePenempatan .col-siswa { width: 20% !important; text-align: left !important; color: #000000 !important; }
    #datatablePenempatan .col-nis { width: 12% !important; text-align: left !important; color: #000000 !important; font-family: inherit !important; }
    #datatablePenempatan .col-perusahaan { width: 38% !important; text-align: left !important; color: #000000 !important; }
    #datatablePenempatan .col-nilai { width: 12% !important; text-align: center !important; color: #000000 !important; }
    #datatablePenempatan .col-status { width: 13% !important; text-align: center !important; }

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

    /* Status Diterima: teks hijau tanpa kotak/background */
    .placement-status {
        border: none !important;
        background: transparent !important;
        color: #187653 !important;
        font-weight: bold !important;
        padding: 0 !important;
        font-size: 9pt !important;
    }

    .badge i, 
    td .rounded-circle, 
    td i {
        display: none !important;
    }
}

</style>

<style>
/* UI direction: Swiss operations interface, not a collection of floating cards. */
.penempatan-page {
    --ui-ink: #17212b;
    --ui-body: #41515e;
    --ui-muted: #6f7d88;
    --ui-subtle: #93a0aa;
    --ui-line: #dbe2e8;
    --ui-soft-line: #edf1f4;
    --ui-accent: #1e40af;
    --ui-accent-soft: #eff4ff;
    max-width: 1360px;
    color: var(--ui-body);
    font-family: 'Inter', sans-serif;
}
.penempatan-page button,
.penempatan-page input,
.penempatan-page select { font-family: inherit; }
.penempatan-page .card,
.penempatan-page .badge {
    border-radius: 4px !important;
}
.penempatan-page .card { box-shadow: none !important; }
.penempatan-page .page-header {
    align-items: flex-end !important;
    margin-bottom: 2rem !important;
    padding-bottom: 1.6rem;
    border-bottom: 1px solid var(--ui-line);
}
.penempatan-page .placement-kicker {
    display: inline-flex;
    padding: 0 0 .35rem;
    border: 0;
    border-bottom: 2px solid var(--ui-accent);
    border-radius: 0;
    background: transparent;
    color: var(--ui-accent);
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.penempatan-page .page-title {
    margin-top: .75rem;
    color: var(--ui-ink) !important;
    font-size: clamp(1.85rem, 3.8vw, 2.75rem);
    font-weight: 700;
    letter-spacing: -.055em;
    line-height: 1.08;
}
.penempatan-page .page-title i {
    color: var(--ui-accent) !important;
    font-size: 1.55rem;
}
.penempatan-page .page-subtitle {
    max-width: 68ch;
    color: var(--ui-muted) !important;
    font-size: .88rem;
    line-height: 1.65;
}
.penempatan-page .page-actions {
    flex-shrink: 0;
    gap: .6rem !important;
}
.penempatan-page .placement-quota-button,
.penempatan-page .placement-print-button {
    min-height: 44px;
    border-radius: 6px !important;
    font-size: .8rem;
    font-weight: 600;
    transition: border-color .2s ease, background-color .2s ease, color .2s ease, transform .2s ease;
}
.penempatan-page .placement-quota-button {
    border: 1px solid #aebac4;
    background: #fff;
    color: var(--ui-body);
    box-shadow: none;
}
.penempatan-page .placement-quota-button:hover {
    border-color: var(--ui-accent);
    background: var(--ui-accent-soft);
    color: var(--ui-accent);
}
.penempatan-page .placement-quota-button strong { color: var(--ui-ink); }
.penempatan-page .placement-quota-menu {
    min-width: 240px;
    padding: .4rem;
    border: 1px solid var(--ui-line);
    border-radius: 6px;
    box-shadow: 0 12px 24px rgba(23, 33, 43, .12) !important;
}
.penempatan-page .placement-quota-menu .dropdown-header {
    padding: .55rem .7rem .4rem;
    color: var(--ui-subtle);
    font-size: .64rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.penempatan-page .placement-quota-menu .dropdown-item {
    padding: .62rem .7rem;
    border-radius: 4px;
    color: var(--ui-body);
    font-size: .8rem;
}
.penempatan-page .placement-quota-menu .dropdown-item:hover,
.penempatan-page .placement-quota-menu .dropdown-item.active {
    background: var(--ui-accent-soft);
    color: var(--ui-accent);
}
.penempatan-page .placement-print-button {
    padding: .62rem 1rem !important;
    border: 1px solid var(--ui-accent) !important;
    background: var(--ui-accent) !important;
    color: #fff !important;
    box-shadow: none !important;
}
.penempatan-page .placement-print-button:hover {
    border-color: #16388f !important;
    background: #16388f !important;
    transform: translateY(-1px);
}

/* Metrics are one information strip, not four competing cards. */
.penempatan-page > .row.g-3.mb-4 {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0 !important;
    margin-right: 0;
    margin-left: 0;
    border-top: 1px solid var(--ui-line);
    border-bottom: 1px solid var(--ui-line);
}
.penempatan-page > .row.g-3.mb-4 > [class*="col-"] {
    width: auto;
    padding: 0;
}
.penempatan-page > .row.g-3.mb-4 > [class*="col-"]:not(:last-child) { border-right: 1px solid var(--ui-line); }
.penempatan-page .kpi-card {
    min-height: 0;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    transition: background-color .2s ease;
}
.penempatan-page .kpi-card:hover {
    transform: none;
    background: #fbfcfd !important;
    box-shadow: none !important;
}
.penempatan-page .kpi-body {
    min-height: 124px;
    padding: 1.2rem 1.25rem !important;
}
.penempatan-page .kpi-label {
    display: block;
    color: var(--ui-muted);
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
}
.penempatan-page .kpi-value {
    margin-top: .45rem !important;
    color: var(--ui-ink) !important;
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: -.055em;
}
.penempatan-page .kpi-value--success { color: #187653 !important; }
.penempatan-page .kpi-value--danger { color: #b42332 !important; }
.penempatan-page .kpi-value--muted { color: var(--ui-muted) !important; }
.penempatan-page .kpi-meta {
    display: block;
    margin-top: .35rem;
    font-size: .68rem;
    line-height: 1.4;
}
.penempatan-page .kpi-meta--accent { color: var(--ui-accent) !important; }
.penempatan-page .kpi-meta--success { color: #187653 !important; }
.penempatan-page .kpi-meta--danger { color: #b42332 !important; }
.penempatan-page .kpi-meta--muted { color: var(--ui-muted) !important; }
.penempatan-page .kpi-icon {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    border-left: 2px solid var(--ui-accent);
    border-radius: 0;
    background: transparent;
    color: var(--ui-accent);
    font-size: 1rem;
}
.penempatan-page .kpi-icon--success { border-color: #187653; background: transparent; color: #187653; }
.penempatan-page .kpi-icon--danger { border-color: #b42332; background: transparent; color: #b42332; }
.penempatan-page .kpi-icon--muted { border-color: #9aa6af; background: transparent; color: #7b8790; }

/* Policy guidance stays visible without becoming another decorative panel. */
.penempatan-page .policy-card {
    margin-bottom: 1.6rem !important;
    border: 1px solid var(--ui-line) !important;
    border-left: 3px solid var(--ui-accent) !important;
    border-radius: 0 !important;
    background: #fff !important;
    box-shadow: none !important;
}
.penempatan-page .policy-body { padding: 1.1rem 1.25rem !important; }
.penempatan-page .policy-icon {
    width: 28px;
    height: 28px;
    border-radius: 0;
    background: transparent;
    color: var(--ui-accent);
}
.penempatan-page .policy-card h5 {
    color: var(--ui-ink) !important;
    font-size: .98rem;
    letter-spacing: -.015em;
}
.penempatan-page .policy-card p {
    color: var(--ui-muted) !important;
    font-size: .78rem;
    line-height: 1.55;
}
.penempatan-page .policy-toggle {
    min-height: 44px;
    padding: .5rem .75rem !important;
    border: 1px solid var(--ui-accent) !important;
    border-radius: 6px !important;
    background: #fff !important;
    color: var(--ui-accent) !important;
    box-shadow: none !important;
}
.penempatan-page .policy-toggle:hover {
    background: var(--ui-accent-soft) !important;
    color: var(--ui-accent) !important;
}
.penempatan-page .policy-details { border-top-color: var(--ui-line) !important; }
.penempatan-page .policy-detail {
    border: 1px solid var(--ui-line);
    border-radius: 0;
    background: #f8fafc;
}
.penempatan-page .policy-detail h6 {
    color: var(--ui-accent) !important;
    font-size: .73rem;
}
.penempatan-page .policy-detail p { font-size: .74rem; }

/* Tabs use an editorial underline instead of a row of pills. */
.penempatan-page .placement-tabs {
    gap: 1.25rem !important;
    margin-bottom: 1.25rem !important;
    border-bottom: 1px solid var(--ui-line);
}
.penempatan-page .placement-tabs .nav-link {
    min-height: 44px;
    padding: .55rem .1rem !important;
    border: 0 !important;
    border-bottom: 2px solid transparent !important;
    border-radius: 0 !important;
    background: transparent !important;
    color: var(--ui-muted);
    font-size: .79rem;
    font-weight: 600;
    box-shadow: none !important;
    transition: border-color .2s ease, color .2s ease;
}
.penempatan-page .placement-tabs .nav-link:hover {
    border-bottom-color: #9fb4e8 !important;
    color: var(--ui-accent);
}
.penempatan-page .placement-tabs .nav-link.active {
    border-bottom-color: var(--ui-accent) !important;
    background: transparent !important;
    color: var(--ui-accent) !important;
    box-shadow: none !important;
}

/* Main data surfaces are defined by rules and spacing, not shadow. */
.penempatan-page .placement-panel {
    padding: 1.45rem !important;
    border: 0 !important;
    border-top: 2px solid var(--ui-ink) !important;
    border-radius: 0 !important;
    background: #fff !important;
    box-shadow: none !important;
}
.penempatan-page .placement-panel-header {
    margin-bottom: .5rem !important;
    padding-bottom: 1rem !important;
    border-bottom: 1px solid var(--ui-line) !important;
}
.penempatan-page .placement-panel-header h5 {
    color: var(--ui-ink) !important;
    font-size: 1.08rem;
    letter-spacing: -.02em;
}
.penempatan-page .placement-panel-header p {
    color: var(--ui-muted) !important;
    font-size: .76rem;
}
.penempatan-page .placement-panel-header .badge {
    padding: .42rem .6rem !important;
    border: 1px solid var(--ui-line) !important;
    border-radius: 4px !important;
    background: #fff !important;
    color: var(--ui-muted) !important;
    font-size: .68rem;
    font-weight: 600;
}
.penempatan-page .table {
    width: 100%;
    margin: 1rem 0 0;
    border: 0 !important;
    border-radius: 0 !important;
    border-spacing: 0;
    border-collapse: separate;
    background: #fff;
    box-shadow: none !important;
    font-family: inherit;
}
.penempatan-page .table thead th {
    padding: .8rem .75rem !important;
    border: 0 !important;
    border-bottom: 2px solid var(--ui-ink) !important;
    background: #fff !important;
    color: #5d6c77 !important;
    font-size: .64rem !important;
    font-weight: 700 !important;
    letter-spacing: .1em !important;
    text-transform: uppercase;
}
.penempatan-page .table tbody td {
    padding: .95rem .75rem !important;
    border: 0 !important;
    border-bottom: 1px solid var(--ui-soft-line) !important;
    background: #fff !important;
    color: var(--ui-body) !important;
    font-size: .82rem !important;
    line-height: 1.45;
}
.penempatan-page .table tbody tr:last-child td { border-bottom: 0 !important; }
.penempatan-page .table tbody tr:hover td { background: #f8faff !important; }
.penempatan-page #datatablePenempatan .col-no { color: var(--ui-subtle) !important; }
.penempatan-page #datatablePenempatan .col-siswa {
    min-width: 180px;
    color: var(--ui-ink) !important;
    font-weight: 650;
}
.penempatan-page #datatablePenempatan .col-nis {
    color: #647480 !important;
    font-variant-numeric: tabular-nums;
}
.penempatan-page #datatablePenempatan .col-perusahaan {
    min-width: 250px;
    color: #40515e !important;
}
.penempatan-page #datatablePenempatan .col-nilai {
    color: var(--ui-accent) !important;
    font-variant-numeric: tabular-nums;
}
.penempatan-page .placement-status {
    padding: .3rem .55rem !important;
    border: 1px solid #b7dcca !important;
    border-radius: 4px !important;
    background: #f0f9f4 !important;
    color: #187653 !important;
    font-size: .68rem;
    font-weight: 650;
}

/* Company distribution follows the same flat surface language. */
.penempatan-page .company-card {
    border: 1px solid var(--ui-line) !important;
    border-radius: 0 !important;
    background: #fff !important;
    box-shadow: none !important;
}
.penempatan-page .company-card-header { background: #fff !important; }
.penempatan-page .company-card-title {
    min-height: 42px;
    color: var(--ui-ink) !important;
    font-size: .88rem;
    line-height: 1.4;
}
.penempatan-page .company-capacity { font-size: .73rem !important; }
.penempatan-page .company-capacity .fw-bold { color: var(--ui-ink) !important; }
.penempatan-page .company-progress {
    height: 4px !important;
    border-radius: 0 !important;
    background: #e6ebf0 !important;
}
.penempatan-page .company-progress-bar {
    border-radius: 0;
    background: var(--ui-accent) !important;
}
.penempatan-page .company-card-body { border-top: 1px solid var(--ui-soft-line); }
.penempatan-page .company-card-body h6 { color: var(--ui-muted) !important; }
.penempatan-page .company-card .list-group-item { border-color: var(--ui-soft-line) !important; }
.penempatan-page .company-card .badge {
    border-radius: 4px !important;
    font-size: .68rem;
}
.penempatan-page .student-index {
    display: inline-flex !important;
    width: 24px;
    height: 24px;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--ui-line) !important;
    border-radius: 4px !important;
    background: #fff !important;
    color: var(--ui-muted) !important;
    font-size: .68rem !important;
}
.penempatan-page .student-nis { font-size: .73rem; }
.penempatan-page .company-card .badge.bg-primary-subtle {
    border-color: #c4d3f8 !important;
    background: var(--ui-accent-soft) !important;
    color: var(--ui-accent) !important;
}

/* DataTables 1.x and 2.x use different search class names. */
.penempatan-page .dataTables_wrapper,
.penempatan-page .dt-container { font-family: inherit; }
.penempatan-page .dataTables_wrapper .row:first-child,
.penempatan-page .dt-layout-row:first-child {
    align-items: center;
    margin-top: .25rem;
    margin-bottom: .8rem;
}
.penempatan-page .dataTables_filter,
.penempatan-page .dt-search {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: .45rem;
}
.penempatan-page .dataTables_filter label,
.penempatan-page .dt-search label {
    color: var(--ui-muted);
    font-size: .75rem;
    font-weight: 600;
}
.penempatan-page .dataTables_filter input,
.penempatan-page .dt-search input {
    width: 220px;
    min-height: 40px;
    margin-left: 0 !important;
    padding: .45rem .65rem;
    border: 1px solid #b9c5ce !important;
    border-radius: 5px !important;
    color: var(--ui-ink);
    box-shadow: none !important;
}
.penempatan-page .dataTables_filter input:focus,
.penempatan-page .dt-search input:focus {
    border-color: var(--ui-accent) !important;
    outline: none;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, .12) !important;
}
.penempatan-page a:focus-visible,
.penempatan-page button:focus-visible,
.penempatan-page select:focus-visible,
.penempatan-page input:focus-visible {
    outline: 3px solid rgba(30, 64, 175, .28);
    outline-offset: 2px;
}
.penempatan-page button,
.penempatan-page a,
.penempatan-page select,
.penempatan-page input { touch-action: manipulation; }

@media (max-width: 991.98px) {
    .penempatan-page .page-header { align-items: flex-start !important; }
    .penempatan-page .page-actions { width: 100%; justify-content: flex-start; }
}
@media (max-width: 767.98px) {
    .penempatan-page { margin-top: 1rem !important; }
    .penempatan-page .page-header { padding-bottom: 1.25rem; }
    .penempatan-page .page-title { font-size: 1.75rem; }
    .penempatan-page .page-subtitle { font-size: .82rem; }
    .penempatan-page .page-actions { flex-wrap: wrap; }
    .penempatan-page .page-actions .dropdown { flex: 1 1 100%; }
    .penempatan-page .placement-quota-button,
    .penempatan-page .placement-print-button { width: 100%; }
    .penempatan-page > .row.g-3.mb-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .penempatan-page > .row.g-3.mb-4 > [class*="col-"]:nth-child(2) { border-right: 0; }
    .penempatan-page > .row.g-3.mb-4 > [class*="col-"]:nth-child(-n+2) { border-bottom: 1px solid var(--ui-line); }
    .penempatan-page .kpi-body { min-height: 112px; padding: 1rem !important; }
    .penempatan-page .kpi-label { font-size: .59rem; }
    .penempatan-page .kpi-value { font-size: 1.65rem; }
    .penempatan-page .kpi-icon { display: none !important; }
    .penempatan-page .policy-body { padding: 1rem !important; }
    .penempatan-page .policy-toggle { width: 100%; }
    .penempatan-page .placement-tabs {
        flex-wrap: nowrap !important;
        margin-right: -.75rem;
        padding-bottom: 0;
        overflow-x: auto;
    }
    .penempatan-page .placement-tabs .nav-link { white-space: nowrap; }
    .penempatan-page .placement-panel { padding: 1rem !important; }
    .penempatan-page .placement-panel-header { align-items: flex-start !important; }
    .penempatan-page .table-responsive { max-width: 100%; overflow-x: auto; }
    .penempatan-page #datatablePenempatan { min-width: 760px; }
    .penempatan-page .dataTables_filter,
    .penempatan-page .dt-search { align-items: stretch; flex-direction: column; }
    .penempatan-page .dataTables_filter input,
    .penempatan-page .dt-search input { width: 100%; }
}

@media print {
    html, body { font-family: 'Inter', sans-serif !important; }
    .penempatan-page { max-width: 100% !important; font-family: 'Inter', sans-serif !important; }
    .penempatan-page .placement-panel { padding: 0 !important; border: 0 !important; background: transparent !important; }
    .penempatan-page .dataTables_filter,
    .penempatan-page .dt-search,
    .penempatan-page .dataTables_length,
    .penempatan-page .dt-length,
    .penempatan-page .dataTables_paginate,
    .penempatan-page .dt-paging,
    .penempatan-page .dataTables_info,
    .penempatan-page .dt-info { display: none !important; }
    .print-report-brand {
        display: flex !important;
        align-items: center;
        justify-content: center;
        gap: 15px;
        padding-bottom: 8px !important;
    }
    .print-report-logo { width: 65px; height: 65px; object-fit: contain; }
    .print-report-copy { text-align: center; }
    .print-report-school {
        margin: 0 !important;
        color: #000 !important;
        font-size: 14pt !important;
        font-weight: 800 !important;
        letter-spacing: .5px;
    }
    .print-report-title {
        margin: 2px 0 0 !important;
        color: #000 !important;
        font-size: 11pt !important;
        font-weight: 700 !important;
        letter-spacing: .5px;
    }
    .print-report-rule {
        height: 4px;
        margin: 4px 0 12px;
        border-top: 1px solid #000;
        border-bottom: 2px solid #000;
    }
}

@media (prefers-reduced-motion: reduce) {
    .penempatan-page *,
    .penempatan-page *::before,
    .penempatan-page *::after {
        transition-duration: .01ms !important;
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
    }
}
</style>

<?php
include 'layout/footer.php';
?>
