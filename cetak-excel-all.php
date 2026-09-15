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
            table-layout: auto !important;
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
            white-space: nowrap !important;
        }
        table.table-report tbody td {
            padding: 7px 10px !important;
            color: #34424d !important;
            background-color: #ffffff !important;
            border: none !important;
            border-bottom: 1px solid #e9ecef !important;
            font-size: 9pt !important;
            vertical-align: middle !important;
            white-space: normal !important;
            word-wrap: break-word !important;
        }
        table.table-report tbody tr:nth-child(even) td {
            background-color: #f8f9fa !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Kolom Spesifik - auto-fit tanpa fixed width */
        table.table-report .col-no { text-align: center !important; white-space: nowrap !important; }
        table.table-report .col-siswa { font-weight: 700 !important; color: #18242f !important; text-align: left !important; }
        table.table-report .col-nis { font-family: ui-monospace, "Cascadia Code", monospace !important; color: #56656f !important; text-align: left !important; white-space: nowrap !important; }
        table.table-report .col-nilai { text-align: center !important; font-weight: 700 !important; color: #0a58ca !important; white-space: nowrap !important; }
        table.table-report .col-ranking { text-align: center !important; font-weight: 600 !important; color: #18242f !important; white-space: nowrap !important; }
        table.table-report .col-keterangan { text-align: center !important; white-space: nowrap !important; }

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

        /* Redesign: report workspace dengan satu aksen dan hirarki yang tenang. */
        :root {
            --page-bg: #f3f6f8;
            --surface: #ffffff;
            --ink: #162532;
            --muted: #667783;
            --subtle: #8a98a3;
            --line: #e1e8ed;
            --line-strong: #cfd9e0;
            --accent: #1769e0;
            --accent-dark: #0f4fae;
            --accent-soft: #edf4ff;
            --success: #197653;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body.page-body {
            background: var(--page-bg);
            color: var(--ink);
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .page-shell {
            max-width: 1360px;
            padding-top: 1.5rem;
            padding-bottom: 3rem;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .back-link,
        .page-date {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            min-height: 40px;
            border-radius: 10px;
            font-size: .84rem;
        }
        .back-link {
            padding: .55rem .9rem;
            color: var(--muted);
            border: 1px solid var(--line-strong);
            background: rgba(255,255,255,.72);
            text-decoration: none;
            transition: border-color .2s ease, color .2s ease, background-color .2s ease, transform .2s ease;
        }
        .back-link:hover {
            color: var(--accent-dark);
            border-color: #aac5ed;
            background: var(--surface);
            transform: translateY(-1px);
        }
        .page-date {
            padding: .55rem .8rem;
            color: var(--muted);
            border: 1px solid var(--line);
            background: rgba(255,255,255,.64);
        }
        .page-date i { color: var(--accent); }
        .page-date strong { color: var(--ink); font-weight: 600; }

        .control-panel {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, .9fr);
            gap: 1.5rem 2.25rem;
            align-items: center;
            margin-bottom: 1.75rem;
            padding: 1.75rem 1.9rem 1.5rem;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--surface);
            box-shadow: 0 14px 36px rgba(38, 61, 78, .06);
        }
        .control-intro {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }
        .school-logo-frame {
            display: grid;
            flex: 0 0 52px;
            width: 52px;
            height: 52px;
            place-items: center;
            border: 1px solid #dce8f5;
            border-radius: 14px;
            background: #f6faff;
        }
        .school-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }
        .control-eyebrow,
        .control-field label {
            margin: 0 0 .35rem;
            color: var(--accent);
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .control-copy h1 {
            margin: 0;
            color: var(--ink);
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -.025em;
        }
        .control-copy p:last-child {
            margin: .25rem 0 0;
            color: var(--muted);
            font-size: .84rem;
        }
        .control-field label {
            display: block;
            color: var(--muted);
            letter-spacing: .08em;
        }
        .control-field label i { color: var(--accent); }
        .company-select {
            min-height: 46px;
            border-color: var(--line-strong) !important;
            border-radius: 10px !important;
            color: var(--ink) !important;
            font-size: .9rem;
            box-shadow: none !important;
        }
        .company-select:focus {
            border-color: #84afe9 !important;
            box-shadow: 0 0 0 .2rem rgba(23, 105, 224, .12) !important;
        }
        .mode-banner {
            display: flex !important;
            grid-column: 1 / -1;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 0 !important;
            padding-top: 1.25rem !important;
            border-top: 1px solid var(--line) !important;
        }
        .mode-banner.is-hidden { display: none !important; }
        .mode-note {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            color: var(--muted);
            font-size: .82rem;
            line-height: 1.5;
        }
        .mode-note i { margin-top: .15rem; color: var(--accent); }
        .mode-note strong { color: var(--ink); font-weight: 650; }
        .print-all-button {
            flex: 0 0 auto;
            min-height: 42px;
            padding: .6rem 1.05rem !important;
            border: 1px solid var(--accent) !important;
            border-radius: 10px !important;
            background: var(--accent) !important;
            color: #fff !important;
            font-size: .84rem !important;
            font-weight: 650 !important;
            white-space: nowrap;
            box-shadow: 0 5px 14px rgba(23, 105, 224, .18) !important;
            transition: background-color .2s ease, transform .2s ease, box-shadow .2s ease;
        }
        .print-all-button:hover {
            background: var(--accent-dark) !important;
            transform: translateY(-1px);
            box-shadow: 0 7px 18px rgba(23, 105, 224, .22) !important;
        }
        .print-all-button:active,
        .back-link:active,
        .dt-buttons .dt-button:active { transform: translateY(1px); }

        .report-card {
            margin-bottom: 1.75rem !important;
            padding: 2rem 2rem 1.5rem !important;
            border: 1px solid var(--line) !important;
            border-radius: 18px !important;
            background: var(--surface) !important;
            box-shadow: 0 14px 36px rgba(38, 61, 78, .055) !important;
        }
        .company-section.is-hidden { display: none !important; }
        .company-section.is-active { display: block; }
        .report-header { margin-bottom: 1.5rem; }
        .report-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: .95rem;
            border-bottom: 1px solid #edf1f4;
        }
        .report-brand strong,
        .report-brand span {
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .13em;
            text-transform: uppercase;
        }
        .report-brand strong { color: var(--accent); }
        .report-brand span { color: var(--subtle); }
        .report-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
            gap: 2rem;
        }
        .report-hero small,
        .report-date-box small {
            display: block;
            margin-bottom: .45rem;
            color: var(--accent);
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .report-hero h1 {
            margin: 0 0 .45rem;
            color: var(--ink);
            font-size: 1.9rem;
            font-weight: 700;
            letter-spacing: -.045em;
            line-height: 1.08;
        }
        .report-hero p.company-name {
            max-width: 78ch;
            margin: 0;
            color: #42535f;
            font-size: .93rem;
            font-weight: 600;
            letter-spacing: .01em;
            line-height: 1.5;
            text-transform: none;
        }
        .report-date-box {
            display: flex;
            min-width: 142px;
            align-self: stretch;
            flex-direction: column;
            justify-content: flex-end;
            padding-left: 1.15rem;
            border-left: 1px solid #b8d0f0;
        }
        .report-date-box small {
            margin-bottom: .3rem;
            color: var(--subtle);
            font-size: .61rem;
        }
        .report-date-box strong {
            color: var(--ink);
            font-size: .84rem;
            font-weight: 700;
        }

        .dataTables_wrapper > .no-print {
            margin-bottom: 1rem !important;
        }
        .dt-buttons {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: .45rem !important;
            margin-bottom: 0 !important;
        }
        .dt-buttons .dt-button,
        .dt-buttons .btn {
            min-height: 36px;
            padding: .45rem .75rem !important;
            border: 1px solid var(--line-strong) !important;
            border-radius: 9px !important;
            background: #fff !important;
            color: #4f606c !important;
            font-size: .76rem !important;
            font-weight: 650 !important;
            box-shadow: none !important;
            transition: border-color .2s ease, background-color .2s ease, color .2s ease, transform .2s ease;
        }
        .dt-buttons .dt-button:hover,
        .dt-buttons .btn:hover {
            border-color: #a9c5eb !important;
            background: var(--accent-soft) !important;
            color: var(--accent-dark) !important;
        }
        .dt-buttons .buttons-print {
            border-color: var(--accent) !important;
            background: var(--accent) !important;
            color: #fff !important;
        }
        .dt-buttons .buttons-print:hover {
            border-color: var(--accent-dark) !important;
            background: var(--accent-dark) !important;
            color: #fff !important;
        }
        .dt-buttons .dt-button i,
        .dt-buttons .btn i { color: currentColor !important; }
        .dt-buttons .buttons-print i { color: #fff !important; }
        .dataTables_filter label {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            color: #52636e;
            font-size: .8rem;
            font-weight: 600;
        }
        .dataTables_filter input {
            width: 220px;
            min-height: 36px;
            margin-left: 0 !important;
            padding: .45rem .7rem;
            border: 1px solid var(--line-strong) !important;
            border-radius: 9px !important;
            color: var(--ink);
            box-shadow: none !important;
        }
        .dataTables_filter input:focus {
            border-color: #84afe9 !important;
            outline: none;
            box-shadow: 0 0 0 .2rem rgba(23, 105, 224, .12) !important;
        }
        .dataTables_filter input::placeholder { color: #9aa7b1; }

        table.table-report {
            table-layout: auto !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            overflow: hidden;
            border: 1px solid var(--line) !important;
            border-radius: 12px;
            color: var(--ink);
            font-variant-numeric: tabular-nums;
        }
        table.table-report thead th {
            padding: .75rem .8rem !important;
            border: 0 !important;
            border-bottom: 1px solid var(--line-strong) !important;
            background: #f5f8fa !important;
            color: #5b6b76 !important;
            font-size: .66rem !important;
            font-weight: 700 !important;
            letter-spacing: .1em !important;
        }
        table.table-report tbody td {
            padding: .78rem .8rem !important;
            border: 0 !important;
            border-bottom: 1px solid #edf1f4 !important;
            background: #fff !important;
            color: #51616c !important;
            font-size: .84rem !important;
            vertical-align: middle !important;
        }
        table.table-report tbody tr:last-child td { border-bottom: 0 !important; }
        table.table-report tbody tr:nth-child(even) td { background: #fbfcfd !important; }
        table.table-report tbody tr:hover td { background: #f7faff !important; }
        table.table-report .col-no {
            color: var(--subtle) !important;
            font-weight: 600 !important;
            text-align: center !important;
        }
        table.table-report .col-siswa {
            color: var(--ink) !important;
            font-weight: 650 !important;
            text-align: left !important;
        }
        table.table-report .col-nis {
            color: #74828c !important;
            font-family: ui-monospace, "Cascadia Code", monospace !important;
            font-size: .78rem !important;
            letter-spacing: .02em;
            text-align: left !important;
        }
        table.table-report .col-nilai {
            color: var(--accent-dark) !important;
            font-weight: 700 !important;
            text-align: center !important;
        }
        table.table-report .col-ranking {
            color: var(--ink) !important;
            font-weight: 650 !important;
            text-align: center !important;
        }
        table.table-report .col-keterangan { text-align: center !important; }
        .status {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .78rem;
            font-weight: 650;
        }
        .status::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            content: "";
        }
        .status--accepted { color: var(--success); }
        .status--pending { color: #7a8993; }
        a:focus-visible,
        button:focus-visible,
        select:focus-visible,
        input:focus-visible {
            outline: 3px solid rgba(23, 105, 224, .28);
            outline-offset: 2px;
        }

        @media (max-width: 767.98px) {
            .page-shell {
                padding-top: 1rem;
                padding-bottom: 2rem;
            }
            .topbar { align-items: flex-start; }
            .page-date span { display: none; }
            .control-panel {
                grid-template-columns: 1fr;
                gap: 1.25rem;
                padding: 1.25rem;
            }
            .mode-banner {
                align-items: stretch;
                flex-direction: column;
            }
            .print-all-button { width: 100%; }
            .report-card { padding: 1.25rem 1rem 1rem !important; }
            .report-hero { grid-template-columns: 1fr; gap: 1rem; }
            .report-hero h1 { font-size: 1.55rem; }
            .report-date-box {
                min-width: 0;
                align-self: auto;
                padding: .85rem 0 0;
                border-top: 1px solid #dbe5ef;
                border-left: 0;
            }
            .dataTables_wrapper > .no-print { align-items: stretch !important; }
            .dt-buttons { width: 100%; }
            .dt-buttons .dt-button,
            .dt-buttons .btn { flex: 1 1 auto; }
            .dataTables_filter label { width: 100%; align-items: stretch; flex-direction: column; gap: .35rem; }
            .dataTables_filter input { width: 100%; }
        }

        @media print {
            html, body.page-body { background: #fff !important; }
            .page-shell {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
            }
            .report-card {
                padding: 0 !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            .report-brand { margin-bottom: 1.2rem; }
            .report-hero h1 { font-size: 21pt; }
            .report-hero p.company-name { font-size: 10pt; }
            .report-date-box strong { font-size: 10pt; }
            table.table-report {
                width: 100% !important;
                table-layout: auto !important;
                border: 0 !important;
                border-radius: 0;
                font-size: 9pt !important;
            }
            table.table-report thead th {
                background: #edf2f6 !important;
                color: #243542 !important;
                font-size: 8pt !important;
                white-space: nowrap !important;
            }
            table.table-report tbody td {
                padding: 7px 8px !important;
                font-size: 9pt !important;
                white-space: normal !important;
                word-wrap: break-word !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after {
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>
<body class="page-body <?= $is_all_mode ? 'print-all-mode' : '' ?>">
    <div class="container page-shell">
        <!-- Top Action Bar -->
        <div class="topbar no-print">
            <a href="ranking.php" class="back-link">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Kembali ke ranking</span>
            </a>
            <div class="page-date">
                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                <span>Tanggal laporan</span>
                <strong><?= date('d/m/Y') ?></strong>
            </div>
        </div>

        <!-- Filter & Control Panel (Hanya Muncul di Layar Web) -->
        <div class="control-panel no-print">
            <div class="control-intro">
                <div class="school-logo-frame">
                    <img src="img/smk.jpg" alt="Logo SMK Negeri 1 Pinrang" class="school-logo">
                </div>
                <div class="control-copy">
                    <p class="control-eyebrow">Profile matching</p>
                    <h1>Laporan ranking siswa</h1>
                    <p>SMK Negeri 1 Pinrang, kelola tampilan dan ekspor laporan.</p>
                </div>
            </div>

            <!-- Pilihan Mode: 1 perusahaan atau semua perusahaan -->
            <div class="control-field">
                <label for="companySelect">
                    <i class="fas fa-filter me-1" aria-hidden="true"></i> Pilih tampilan laporan
                </label>
                <select id="companySelect" class="form-select company-select" onchange="switchCompany(this.value)">
                    <option value="all" <?= $is_all_mode ? 'selected' : '' ?>>
                        Semua perusahaan (<?= count($final_results) ?> mitra)
                    </option>
                    <optgroup label="Pilih satu perusahaan">
                        <?php foreach (array_keys($final_results) as $p): 
                            $p_display = $full_names_map[$p] ?? $p;
                        ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= (!$is_all_mode && $selected_perusahaan === $p) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p_display) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <!-- Tombol Cetak Semua Perusahaan Sekaligus -->
            <div id="allModeBanner" class="mode-banner <?= $is_all_mode ? '' : 'is-hidden' ?>">
                <div class="mode-note">
                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                    <span><strong>Semua perusahaan</strong> aktif. Menampilkan <?= count($final_results) ?> mitra; setiap mitra dicetak pada halaman tersendiri.</span>
                </div>
                <button type="button" onclick="window.print()" class="btn print-all-button">
                    <i class="fas fa-print me-2" aria-hidden="true"></i> Cetak semua perusahaan
                </button>
            </div>
        </div>

        <!-- Bagian Tabel per Perusahaan (Sesuai Layout Gambar 3) -->
        <?php foreach ($final_results as $p => $siswas): 
            $is_current = ($is_all_mode || $selected_perusahaan === $p);
            $comp_full_name = $full_names_map[$p] ?? $p;
        ?>
            <div class="company-section report-card <?= $is_current ? 'is-active' : 'is-hidden' ?>" 
                 id="section-<?= md5($p) ?>" 
                 data-company="<?= htmlspecialchars($p) ?>"
                 data-fullname="<?= htmlspecialchars($comp_full_name) ?>">
                
                <!-- Header Dokumen (Persis Seperti Gambar 3) -->
                <div class="report-header">
                    <div class="report-brand">
                        <strong>SPK MATCHING</strong>
                        <span>LAPORAN RANKING</span>
                    </div>
                    <div class="report-hero">
                        <div class="report-title-block">
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
                    <table class="table table-report datatable-export" aria-label="Daftar ranking siswa <?= htmlspecialchars($comp_full_name) ?>">
                        <caption class="visually-hidden">Daftar ranking siswa untuk <?= htmlspecialchars($comp_full_name) ?></caption>
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
                                            <span class="status status--accepted">Diterima</span>
                                        <?php else: ?>
                                            <span class="status status--pending">Belum lolos</span>
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
                $('.company-section').removeClass('is-hidden').addClass('is-active');
                $('#allModeBanner').removeClass('is-hidden');
                document.title = 'Laporan Ranking Siswa (Semua Perusahaan) | SPK Matching';
                if (window.history.replaceState) {
                    window.history.replaceState(null, '', 'cetak-excel-all.php?mode=all');
                }
            } else {
                $('body').removeClass('print-all-mode');
                $('#allModeBanner').addClass('is-hidden');
                $('.company-section').removeClass('is-active').addClass('is-hidden');
                
                var $activeSec = $('.company-section').filter(function() {
                    return $(this).attr('data-company') === selected;
                });
                if ($activeSec.length === 0) {
                    $activeSec = $('.company-section').filter(function() {
                        return $(this).attr('data-company').trim() === selected.trim();
                    });
                }
                $activeSec.removeClass('is-hidden').addClass('is-active');
                
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
