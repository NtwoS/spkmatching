<?php
session_start();

if (!isset($_SESSION['login'])) {
    echo "<script>alert('Anda belum login'); document.location.href = 'index.php';</script>";
    exit;
}

include 'config/database.php';
if (!function_exists('select')) {
    function select($sql) {
        global $db;
        $result = mysqli_query($db, $sql);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

function calculateFinalScore($cf, $sf) {
    return ($cf * 0.6) + ($sf * 0.4);
}
if (!function_exists('calculateGap')) {
    function calculateGap($nilai) { return $nilai; }
}
if (!function_exists('calculateBobot')) {
    function calculateBobot($gap) { return $gap; }
}

// Ambil & olah data
$data_hasilsiswa = select("SELECT * FROM datasiswa ORDER BY id_siswa DESC");
$final_results = [];

if (!empty($data_hasilsiswa)) {
    $grouped_data = [];
    foreach ($data_hasilsiswa as $hasilsiswa) {
        foreach ($hasilsiswa as $key => $value) {
            if (strpos($key, 'perusahaan') === 0) {
                $i = (int) substr($key, 10);
                $perusahaan = $hasilsiswa['perusahaan' . $i];
                $siswa = $hasilsiswa['siswa'];
                $nis = $hasilsiswa['nis'];

                if (empty(trim($perusahaan))) continue;

                if (!isset($grouped_data[$perusahaan])) $grouped_data[$perusahaan] = [];
                if (!isset($grouped_data[$perusahaan][$siswa])) {
                    $grouped_data[$perusahaan][$siswa] = ['soal' => [], 'factor' => [], 'nis' => $nis];
                }

                if (isset($hasilsiswa['factor' . $i])) {
                    $grouped_data[$perusahaan][$siswa]['factor'][$i - 1] = $hasilsiswa['factor' . $i];
                }
                if (isset($hasilsiswa['soal' . $i])) {
                    $grouped_data[$perusahaan][$siswa]['soal'][$i - 1] = calculateBobot(calculateGap($hasilsiswa['soal' . $i]));
                }
            }
        }
    }

    foreach ($grouped_data as $perusahaan => $siswaData) {
        $final_results[$perusahaan] = [];
        foreach ($siswaData as $siswa => $data) {
            $cf_sum = 0; $cf_count = 0; $sf_sum = 0; $sf_count = 0;
            foreach ($data['factor'] as $index => $factor) {
                if ($factor === 'core' && isset($data['soal'][$index])) {
                    $cf_sum += $data['soal'][$index]; $cf_count++;
                } elseif ($factor === 'secondary' && isset($data['soal'][$index])) {
                    $sf_sum += $data['soal'][$index]; $sf_count++;
                }
            }
            $cf = $cf_count > 0 ? $cf_sum / $cf_count : 0;
            $sf = $sf_count > 0 ? $sf_sum / $sf_count : 0;
            $final_results[$perusahaan][] = [
                'siswa' => $siswa,
                'nis' => $data['nis'],
                'final_score' => calculateFinalScore($cf, $sf)
            ];
        }
        usort($final_results[$perusahaan], fn($a, $b) => $b['final_score'] <=> $a['final_score']);
    }
}

// Mode cetak: 1 perusahaan atau semua perusahaan
$data_perusahaan_db = select("SELECT perusahaan FROM perusahaan");
$full_companies = [];
if (!empty($data_perusahaan_db)) {
    foreach ($data_perusahaan_db as $dp) {
        $c = trim($dp['perusahaan']);
        if (!empty($c)) $full_companies[] = $c;
    }
}
$full_names_map = [];
foreach (array_keys($final_results) as $comp_key) {
    $comp_trimmed = trim($comp_key);
    $matched = false;
    foreach ($full_companies as $fc) {
        if ($comp_trimmed === $fc || strpos($fc, $comp_trimmed) === 0) {
            $full_names_map[$comp_key] = $fc;
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $full_names_map[$comp_key] = $comp_key;
    }
}

$mode = $_GET['mode'] ?? '';
$param_perusahaan = trim($_GET['perusahaan'] ?? '');
$is_all_mode = ($mode === 'all' || $param_perusahaan === 'all');

$selected_perusahaan = '';
if (!$is_all_mode) {
    if (!empty($param_perusahaan)) {
        if (isset($final_results[$param_perusahaan])) {
            $selected_perusahaan = $param_perusahaan;
        } else {
            foreach ($final_results as $k => $v) {
                if (trim($k) === $param_perusahaan || (isset($full_names_map[$k]) && $full_names_map[$k] === $param_perusahaan)) {
                    $selected_perusahaan = $k;
                    break;
                }
            }
        }
    }
    if (empty($selected_perusahaan) && !empty($final_results)) {
        $selected_perusahaan = array_key_first($final_results);
    }
}
$selected_display_name = !empty($selected_perusahaan) ? ($full_names_map[$selected_perusahaan] ?? $selected_perusahaan) : 'Semua Perusahaan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_all_mode ? 'Laporan Ranking Siswa (Semua Perusahaan) | SPK Matching' : 'Laporan Ranking Siswa - ' . htmlspecialchars($selected_display_name) . ' | SPK Matching' ?></title>
    <link rel="icon" href="img/smk.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            color: #34424d; 
        }
        .card { 
            border: none; 
            border-radius: 14px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        }

        /* Styling TOMBOL EKSPOR agar berwarna cerah, jelas, dan TIDAK ABU-ABU */
        .dt-buttons {
            display: flex !important;
            gap: 8px !important;
            flex-wrap: wrap !important;
            margin-bottom: 12px !important;
        }
        .dt-buttons .dt-button,
        .dt-buttons .btn {
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 0.85rem !important;
            padding: 0.45rem 0.95rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            text-decoration: none !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06) !important;
            transition: all 0.2s ease !important;
            opacity: 1 !important;
        }

        /* Tombol Excel: Hijau */
        .dt-buttons .buttons-excel {
            background-color: #198754 !important;
            color: #ffffff !important;
            border: 1px solid #198754 !important;
        }
        .dt-buttons .buttons-excel:hover {
            background-color: #146c43 !important;
        }

        /* Tombol PDF: Merah */
        .dt-buttons .buttons-pdf {
            background-color: #dc3545 !important;
            color: #ffffff !important;
            border: 1px solid #dc3545 !important;
        }
        .dt-buttons .buttons-pdf:hover {
            background-color: #b02a37 !important;
        }

        /* Tombol Print/Cetak: Biru */
        .dt-buttons .buttons-print {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            border: 1px solid #0d6efd !important;
        }
        .dt-buttons .buttons-print:hover {
            background-color: #0b5ed7 !important;
        }

        /* Tombol CSV: Cyan */
        .dt-buttons .buttons-csv {
            background-color: #0891b2 !important;
            color: #ffffff !important;
            border: 1px solid #0891b2 !important;
        }
        .dt-buttons .buttons-csv:hover {
            background-color: #0e7490 !important;
        }

        /* Tombol Copy: Ungu */
        .dt-buttons .buttons-copy {
            background-color: #7c3aed !important;
            color: #ffffff !important;
            border: 1px solid #7c3aed !important;
        }
        .dt-buttons .buttons-copy:hover {
            background-color: #6d28d9 !important;
        }

        .dt-buttons i {
            color: #ffffff !important;
        }

        /* Header Laporan Resmi (Sesuai Desain Gambar 3) */
        .report-header {
            margin-bottom: 20px;
        }
        .report-brand {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        .report-brand strong {
            color: #0d6efd;
            font-size: 11px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }
        .report-brand span {
            color: #667581;
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .report-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }
        .report-hero small {
            display: block;
            color: #0d6efd;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .report-hero h1 {
            color: #18242f;
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 6px;
            line-height: 1.1;
            letter-spacing: -0.5px;
        }
        .report-hero p.company-name {
            color: #18242f;
            font-size: 13px;
            margin: 0;
            line-height: 1.4;
            text-transform: uppercase;
            font-weight: 700;
            max-width: 680px;
            letter-spacing: 0.2px;
        }
        .report-date-box {
            border-left: 2px solid #0d6efd;
            padding-left: 14px;
            min-width: 120px;
            flex-shrink: 0;
        }
        .report-date-box small {
            color: #7b898f;
            font-size: 7.5px;
            font-weight: 700;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .report-date-box strong {
            color: #18242f;
            font-size: 12px;
            font-weight: 700;
        }

        /* Tabel Presisi Sesuai Gambar 3 */
        table.table-report {
            width: 100% !important;
            border-collapse: collapse !important;
            border: none !important;
            font-family: Arial, sans-serif !important;
            font-size: 9pt !important;
        }
        table.table-report thead th {
            background-color: #18242f !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 8pt !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            padding: 9px 10px !important;
            border: none !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        table.table-report tbody td {
            padding: 7px 10px !important;
            color: #34424d !important;
            background-color: #ffffff !important;
            border: none !important;
            border-bottom: 1px solid #e9ecef !important;
            font-size: 9pt !important;
            vertical-align: middle !important;
        }
        table.table-report tbody tr:nth-child(even) td {
            background-color: #f8f9fa !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Kolom Spesifik */
        table.table-report .col-no { text-align: center !important; width: 6%; }
        table.table-report .col-siswa { font-weight: 700 !important; color: #18242f !important; text-align: left !important; width: 32%; }
        table.table-report .col-nis { font-family: ui-monospace, "Cascadia Code", monospace !important; color: #56656f !important; text-align: left !important; width: 18%; }
        table.table-report .col-nilai { text-align: center !important; font-weight: 700 !important; color: #0a58ca !important; width: 14%; }
        table.table-report .col-ranking { text-align: center !important; font-weight: 600 !important; color: #18242f !important; width: 14%; }
        table.table-report .col-keterangan { text-align: center !important; width: 16%; }

        /* Media Print Khusus */
        @media print { 
            @page {
                size: A4 portrait;
                margin: 14mm 12mm 14mm 12mm;
            }
            html, body {
                background: #ffffff !important;
                color: #34424d !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                background: transparent !important;
            }
            /* Hilangkan tombol, filter, search saat cetak */
            .no-print,
            .dt-buttons,
            .dataTables_filter,
            .dataTables_length,
            .dataTables_paginate,
            .dataTables_info,
            button {
                display: none !important;
            }
            
            /* KONTROL PENTING: Jangan cetak perusahaan yang tersembunyi! */
            .company-section.is-hidden {
                display: none !important;
            }
            .company-section.is-active {
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            /* Hanya beri page break jika mencetak mode SEMUA perusahaan */
            body.print-all-mode .company-section.is-active {
                page-break-after: always !important;
                break-after: page !important;
            }
            body.print-all-mode .company-section.is-active:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
        }
    </style>
</head>
<body class="py-4 <?= $is_all_mode ? 'print-all-mode' : '' ?>">
    <div class="container">
        <!-- Top Action Bar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 no-print">
            <div class="d-flex align-items-center gap-2">
                <a href="ranking.php" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Ranking
                </a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill">
                    <i class="fas fa-calendar-alt me-1 text-primary"></i> Tanggal: <?= date('d/m/Y') ?>
                </span>
            </div>
        </div>

        <!-- Filter & Control Panel (Hanya Muncul di Layar Web) -->
        <div class="card p-4 mb-4 no-print">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="img/smk.jpg" alt="Logo SMK" style="width: 55px; height: 55px; object-fit: contain;">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">Laporan Ranking Siswa PKL</h4>
                        <p class="text-muted small mb-0">SMK Negeri 1 Pinrang — Cetak & Ekspor Hasil Profile Matching</p>
                    </div>
                </div>

                <!-- Pilihan Mode: 1 Perusahaan atau Semua Perusahaan -->
                <div style="min-width: 360px;">
                    <label for="companySelect" class="form-label small text-muted text-uppercase fw-bold mb-1">
                        <i class="fas fa-filter me-1 text-primary"></i> Pilihan Tampilan & Cetak:
                    </label>
                    <select id="companySelect" class="form-select rounded-3 shadow-sm fw-semibold" onchange="switchCompany(this.value)">
                        <option value="all" <?= $is_all_mode ? 'selected' : '' ?>>
                            📑 CETAK SEMUA PERUSAHAAN (<?= count($final_results) ?> Mitra Sekaligus)
                        </option>
                        <optgroup label="Cetak 1 Perusahaan Spesifik:">
                            <?php foreach (array_keys($final_results) as $p): 
                                $p_display = $full_names_map[$p] ?? $p;
                            ?>
                                <option value="<?= htmlspecialchars($p) ?>" <?= (!$is_all_mode && $selected_perusahaan === $p) ? 'selected' : '' ?>>
                                    🏢 <?= htmlspecialchars($p_display) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>

            <!-- Tombol Cetak Semua Perusahaan Sekaligus -->
            <div id="allModeBanner" class="mt-3 pt-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2" style="<?= $is_all_mode ? '' : 'display: none !important;' ?>">
                <div class="small text-secondary">
                    <i class="fas fa-info-circle text-primary me-1"></i> Mode <strong>Semua Perusahaan</strong> aktif. Menampilkan <?= count($final_results) ?> mitra. Setiap perusahaan akan dicetak pada lembar tersendiri (format Gambar 3).
                </div>
                <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                    <i class="fas fa-print me-2"></i> Cetak Semua Perusahaan Sekaligus
                </button>
            </div>
        </div>

        <!-- Bagian Tabel per Perusahaan (Sesuai Layout Gambar 3) -->
        <?php foreach ($final_results as $p => $siswas): 
            $is_current = ($is_all_mode || $selected_perusahaan === $p);
            $comp_full_name = $full_names_map[$p] ?? $p;
        ?>
            <div class="company-section card p-4 mb-4 <?= $is_current ? 'is-active' : 'is-hidden' ?>" 
                 id="section-<?= md5($p) ?>" 
                 data-company="<?= htmlspecialchars($p) ?>"
                 data-fullname="<?= htmlspecialchars($comp_full_name) ?>"
                 style="<?= $is_current ? '' : 'display: none;' ?>">
                
                <!-- Header Dokumen (Persis Seperti Gambar 3) -->
                <div class="report-header">
                    <div class="report-brand">
                        <strong>SPK MATCHING</strong>
                        <span>LAPORAN RANKING</span>
                    </div>
                    <div class="report-hero">
                        <div>
                            <small>HASIL SELEKSI SISWA</small>
                            <h1>Laporan ranking siswa</h1>
                            <p class="company-name"><?= htmlspecialchars($comp_full_name) ?></p>
                        </div>
                        <div class="report-date-box">
                            <small>TANGGAL LAPORAN</small>
                            <strong><?= date('d/m/Y') ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Tabel Semua Siswa (Tanpa Terpotong Pagination) -->
                <div class="table-responsive">
                    <table class="table table-report datatable-export" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="col-no">NO</th>
                                <th class="col-siswa">NAMA SISWA</th>
                                <th class="col-nis">NIS</th>
                                <th class="col-nilai">NILAI AKHIR</th>
                                <th class="col-ranking">RANKING</th>
                                <th class="col-keterangan">KETERANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $kuota = 5;
                            foreach ($siswas as $row): 
                                $is_top = ($no <= $kuota);
                            ?>
                                <tr>
                                    <td class="col-no"><?= $no ?></td>
                                    <td class="col-siswa"><?= htmlspecialchars($row['siswa']) ?></td>
                                    <td class="col-nis"><?= htmlspecialchars($row['nis']) ?></td>
                                    <td class="col-nilai"><?= number_format($row['final_score'], 2) ?></td>
                                    <td class="col-ranking"><?= $no ?></td>
                                    <td class="col-keterangan">
                                        <?php if ($is_top): ?>
                                            <span style="color: #198754; font-weight: 600;">Diterima</span>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">Belum Lolos</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php $no++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script>
        function switchCompany(selected) {
            if (selected === 'all') {
                $('body').addClass('print-all-mode');
                $('.company-section').removeClass('is-hidden').addClass('is-active').show();
                $('#allModeBanner').show();
                document.title = 'Laporan Ranking Siswa (Semua Perusahaan) | SPK Matching';
                if (window.history.replaceState) {
                    window.history.replaceState(null, '', 'cetak-excel-all.php?mode=all');
                }
            } else {
                $('body').removeClass('print-all-mode');
                $('#allModeBanner').hide();
                $('.company-section').removeClass('is-active').addClass('is-hidden').hide();
                
                var $activeSec = $('.company-section').filter(function() {
                    return $(this).attr('data-company') === selected;
                });
                if ($activeSec.length === 0) {
                    $activeSec = $('.company-section').filter(function() {
                        return $(this).attr('data-company').trim() === selected.trim();
                    });
                }
                $activeSec.removeClass('is-hidden').addClass('is-active').show();
                
                var fullName = $activeSec.attr('data-fullname') || $activeSec.find('.company-name').text().trim() || selected;
                document.title = 'Laporan Ranking Siswa - ' + fullName + ' | SPK Matching';
                if (window.history.replaceState) {
                    window.history.replaceState(null, '', 'cetak-excel-all.php?perusahaan=' + encodeURIComponent(selected));
                }
            }
            if ($.fn.dataTable) {
                $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
            }
        }

        $(document).ready(function() {
            $('.company-section').each(function() {
                var $sec = $(this);
                var compName = $sec.attr('data-fullname') || $sec.find('.company-name').text().trim();
                
                $sec.find('table.table-report').DataTable({
                    paging: false, // Menampilkan seluruh siswa tanpa terpotong pagination
                    info: false,
                    dom: '<"d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3 no-print"Bf>rt',
                    buttons: [
                        { 
                            extend: 'excel', 
                            text: '<i class="fas fa-file-excel me-1"></i> Excel', 
                            className: 'btn buttons-excel btn-sm',
                            title: 'Laporan Ranking Siswa - ' + compName,
                            messageTop: 'Mitra DU/DI: ' + compName + ' | SMK Negeri 1 Pinrang'
                        },
                        { 
                            extend: 'pdf', 
                            text: '<i class="fas fa-file-pdf me-1"></i> PDF', 
                            className: 'btn buttons-pdf btn-sm',
                            title: 'Laporan Ranking Siswa - ' + compName,
                            messageTop: 'Mitra DU/DI: ' + compName + ' | Tanggal: <?= date('d/m/Y') ?>',
                            orientation: 'portrait',
                            pageSize: 'A4'
                        },
                        { 
                            text: '<i class="fas fa-print me-1"></i> Cetak', 
                            className: 'btn buttons-print btn-sm',
                            action: function(e, dt, node, config) {
                                window.print();
                            }
                        },
                        { 
                            extend: 'csv', 
                            text: '<i class="fas fa-file-csv me-1"></i> CSV', 
                            className: 'btn buttons-csv btn-sm',
                            title: 'Laporan Ranking Siswa - ' + compName
                        },
                        { 
                            extend: 'copy', 
                            text: '<i class="fas fa-copy me-1"></i> Salin', 
                            className: 'btn buttons-copy btn-sm' 
                        }
                    ],
                    language: {
                        search: "Cari Siswa:",
                        searchPlaceholder: "Ketik nama siswa/NIS..."
                    }
                });
            });
        });
    </script>
</body>
</html>