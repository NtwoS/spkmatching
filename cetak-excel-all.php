<?php
session_start();



// Membatasi halaman sebelum login
if (!isset($_SESSION['login'])) {
    echo "<script>
            alert('Anda belum login');
            document.location.href = 'index.php';
        </script>";
    exit;
}

// Fungsi untuk menghitung nilai akhir (60% CF + 40% SF)
function calculateFinalScore($cf, $sf) {
    return ($cf * 0.6) + ($sf * 0.4);
}

// Fungsi Gap & Bobot
if (!function_exists('calculateGap')) {
    function calculateGap($nilai) {
        return $nilai; // sesuaikan logika jika perlu
    }
}
if (!function_exists('calculateBobot')) {
    function calculateBobot($gap) {
        return $gap; // sesuaikan logika jika perlu
    }
}

// Koneksi database
include 'config/database.php';

// Fungsi select()
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

// Inisialisasi variabel
$final_results = [];
$data_hasilsiswa = [];

// Ambil data
$data_hasilsiswa = select("SELECT * FROM datasiswa ORDER BY id_siswa DESC");

// Kelompokkan data hanya jika ada data
if (!empty($data_hasilsiswa)) {
    $grouped_data = [];
    foreach ($data_hasilsiswa as $hasilsiswa) {
        foreach ($hasilsiswa as $key => $value) {
            if (strpos($key, 'perusahaan') === 0) {
                $i = (int) substr($key, 10);
                $perusahaan_key = 'perusahaan'.$i;
                $soal_key = 'soal'.$i;
                $factor_key = 'factor'.$i;

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
                    $grouped_data[$perusahaan][$siswa]['factor'][$i-1] = $hasilsiswa[$factor_key];
                }
                if (isset($hasilsiswa[$soal_key])) {
                    $gap = calculateGap($hasilsiswa[$soal_key]);
                    $bobot = calculateBobot($gap);
                    $grouped_data[$perusahaan][$siswa]['soal'][$i-1] = $bobot;
                }
            }
        }
    }

    // Hitung nilai akhir untuk setiap siswa di setiap perusahaan
    foreach ($grouped_data as $perusahaan => $siswaData) {
        $final_results[$perusahaan] = [];
        
        foreach ($siswaData as $siswa => $data) {
            $cf_sum = 0; $cf_count = 0;
            $sf_sum = 0; $sf_count = 0;

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

            $final_results[$perusahaan][] = [
                'siswa' => $siswa,
                'nis' => $data['nis'],
                'final_score' => $final_score,
                'cf' => $cf,
                'sf' => $sf
            ];
        }
        
        // Urutkan descending berdasarkan nilai akhir
        usort($final_results[$perusahaan], fn($a, $b) => $b['final_score'] <=> $a['final_score']);
    }
} else {
    // Jika tidak ada data, set final_results sebagai array kosong
    $final_results = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Laporan ranking siswa berdasarkan hasil profile matching.">
    <title>Laporan Ranking Siswa | SPK Matching</title>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <link rel="icon" href="img/smk.jpg">
    <style>
        :root {
            --ink: #18242f;
            --muted: #667581;
            --line: #dbe4e5;
            --canvas: #edf3f1;
            --surface: #ffffff;
            --surface-soft: #f5f8f7;
            --accent: #176b58;
            --accent-dark: #105344;
            --accent-soft: #dcebe7;
            --shadow: 0 24px 60px rgba(25, 65, 57, 0.12);
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-width: 320px;
            color: var(--ink);
            background:
                radial-gradient(circle at 8% 2%, rgba(23, 107, 88, 0.11), transparent 24rem),
                linear-gradient(rgba(24, 36, 47, 0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(24, 36, 47, 0.025) 1px, transparent 1px),
                var(--canvas);
            background-size: auto, 28px 28px, 28px 28px, auto;
            font-family: "Outfit", "Segoe UI", sans-serif;
            font-size: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        button, select, input {
            font: inherit;
        }

        .skip-link {
            position: fixed;
            top: 0.75rem;
            left: 0.75rem;
            z-index: 10;
            padding: 0.7rem 1rem;
            color: #fff;
            background: var(--accent-dark);
            border-radius: 0.5rem;
            transform: translateY(-150%);
            transition: transform 180ms ease;
        }

        .skip-link:focus {
            transform: translateY(0);
        }

        .topbar {
            border-bottom: 1px solid rgba(219, 228, 229, 0.9);
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(14px);
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: min(100% - 2rem, 1180px);
            min-height: 4.5rem;
            margin-inline: auto;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--ink);
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
        }

        .brand-mark {
            display: grid;
            width: 2.25rem;
            height: 2.25rem;
            place-items: center;
            color: #fff;
            background: var(--accent);
            border-radius: 0.65rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 2.5rem;
            padding: 0 0.85rem;
            color: var(--muted);
            border-radius: 0.55rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 180ms ease, background-color 180ms ease, transform 180ms ease;
        }

        .back-link:hover {
            color: var(--accent-dark);
            background: var(--accent-soft);
        }

        .back-link:active {
            transform: translateY(1px);
        }

        .back-link:focus-visible,
        .company-dropdown:focus-visible,
        .dataTables_wrapper .dt-button:focus-visible,
        .dataTables_wrapper .dataTables_paginate .paginate_button:focus-visible,
        .dataTables_wrapper .dataTables_filter input:focus-visible {
            outline: 3px solid rgba(23, 107, 88, 0.24);
            outline-offset: 2px;
        }

        .page-shell {
            width: min(100% - 2rem, 1180px);
            margin: 0 auto;
            padding: 4.5rem 0 5.5rem;
        }

        .page-intro {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
            gap: 2rem;
            margin-bottom: 2.25rem;
        }

        .eyebrow {
            margin: 0 0 0.65rem;
            color: var(--accent);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        h1 {
            max-width: 18ch;
            margin: 0;
            font-size: clamp(2.25rem, 5vw, 4.5rem);
            font-weight: 600;
            letter-spacing: -0.055em;
            line-height: 0.98;
            text-wrap: balance;
        }

        .intro-copy {
            max-width: 31rem;
            margin: 1rem 0 0;
            color: var(--muted);
            font-size: 1rem;
            text-wrap: pretty;
        }

        .report-date {
            min-width: 10.5rem;
            padding: 1rem 1.1rem;
            border-left: 2px solid var(--accent);
            color: var(--muted);
            background: rgba(255, 255, 255, 0.56);
            font-size: 0.82rem;
        }

        .report-date strong {
            display: block;
            margin-top: 0.18rem;
            color: var(--ink);
            font-size: 1rem;
            font-variant-numeric: tabular-nums;
        }

        .control-panel {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(17rem, 28rem);
            align-items: center;
            gap: 2rem;
            margin-bottom: 1rem;
            padding: 1.25rem 1.4rem;
            color: #fff;
            background: var(--ink);
            border-radius: 1.15rem 1.15rem 0.45rem 0.45rem;
            box-shadow: 0 16px 34px rgba(24, 36, 47, 0.16);
        }

        .control-title {
            display: block;
            font-size: 1rem;
            font-weight: 600;
        }

        .control-description {
            margin: 0.2rem 0 0;
            color: #b7c4cb;
            font-size: 0.85rem;
        }

        .select-wrap {
            position: relative;
        }

        .select-wrap::after {
            position: absolute;
            top: 50%;
            right: 1rem;
            width: 0.55rem;
            height: 0.55rem;
            border-right: 2px solid var(--accent);
            border-bottom: 2px solid var(--accent);
            content: "";
            pointer-events: none;
            transform: translateY(-70%) rotate(45deg);
        }

        .company-dropdown {
            width: 100%;
            min-height: 3.1rem;
            padding: 0 2.75rem 0 1rem;
            color: var(--ink);
            background: #fff;
            border: 1px solid transparent;
            border-radius: 0.65rem;
            cursor: pointer;
            appearance: none;
            transition: border-color 180ms ease, box-shadow 180ms ease;
        }

        .company-dropdown:hover {
            border-color: #91b5ab;
        }

        .company-dropdown:focus-visible {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(23, 107, 88, 0.14);
        }

        .tabcontent {
            display: none;
        }

        .tabcontent.is-active {
            display: block;
            animation: panel-in 260ms ease both;
        }

        @keyframes panel-in {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .ranking-panel {
            overflow: hidden;
            background: var(--surface);
            border-radius: 0.45rem 0.45rem 1.25rem 1.25rem;
            box-shadow: var(--shadow);
        }

        .panel-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 2rem 2rem 1.45rem;
            border-bottom: 1px solid var(--line);
        }

        .panel-kicker {
            margin: 0 0 0.25rem;
            color: var(--accent);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .company-title {
            margin: 0;
            font-size: clamp(1.35rem, 3vw, 1.85rem);
            font-weight: 600;
            letter-spacing: -0.025em;
            line-height: 1.15;
            text-wrap: balance;
        }

        .student-count {
            flex: 0 0 auto;
            padding: 0.45rem 0.7rem;
            color: var(--accent-dark);
            background: var(--accent-soft);
            border-radius: 0.45rem;
            font-size: 0.82rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .dataTables_wrapper {
            padding: 1.35rem 2rem 2rem;
        }

        .table-tools,
        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .table-tools {
            margin-bottom: 1.15rem;
        }

        .table-footer {
            margin-top: 1rem;
        }

        .dataTables_wrapper .dt-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .dataTables_wrapper .dt-button {
            min-height: 2.55rem;
            margin: 0;
            padding: 0.55rem 0.78rem;
            color: var(--accent-dark) !important;
            background: var(--surface-soft) !important;
            border: 1px solid var(--line) !important;
            border-radius: 0.5rem !important;
            box-shadow: none !important;
            font-size: 0.82rem;
            font-weight: 600;
            transition: color 180ms ease, background-color 180ms ease, border-color 180ms ease, transform 180ms ease;
        }

        .dataTables_wrapper .dt-button:hover:not(.disabled) {
            color: #fff !important;
            background: var(--accent) !important;
            border-color: var(--accent) !important;
        }

        .dataTables_wrapper .dt-button:active:not(.disabled) {
            transform: translateY(1px);
        }

        .dataTables_wrapper .dataTables_filter {
            float: none;
            text-align: left;
        }

        .dataTables_wrapper .dataTables_filter label {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 500;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: min(16rem, 36vw);
            min-height: 2.55rem;
            margin: 0;
            padding: 0 0.8rem;
            color: var(--ink);
            background: var(--surface-soft);
            border: 1px solid var(--line);
            border-radius: 0.5rem;
            transition: border-color 180ms ease, box-shadow 180ms ease;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--accent);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(23, 107, 88, 0.12);
        }

        .table-scroll {
            overflow-x: auto;
        }

        table.dataTable {
            width: 100% !important;
            margin: 0 !important;
            border-collapse: collapse !important;
            font-variant-numeric: tabular-nums;
        }

        table.dataTable.no-footer {
            border-bottom: 0;
        }

        table.dataTable thead th {
            padding: 0.8rem 1rem;
            color: var(--muted);
            background: var(--surface-soft);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            font-size: 0.73rem;
            font-weight: 600;
            letter-spacing: 0.065em;
            text-transform: uppercase;
        }

        table.dataTable tbody td {
            padding: 1rem;
            color: #34424d;
            border-bottom: 1px solid #e8eeee;
            font-size: 0.92rem;
        }

        table.dataTable tbody tr:last-child td {
            border-bottom: 0;
        }

        table.dataTable tbody tr {
            background: #fff;
            transition: background-color 160ms ease;
        }

        table.dataTable tbody tr:hover {
            background: #f3f8f6;
        }

        table.dataTable th:first-child,
        table.dataTable td:first-child,
        table.dataTable th:nth-child(4),
        table.dataTable td:nth-child(4),
        table.dataTable th:last-child,
        table.dataTable td:last-child {
            text-align: center;
        }

        table.dataTable td:nth-child(2) {
            color: var(--ink);
            font-weight: 600;
        }

        table.dataTable td:nth-child(3) {
            font-family: ui-monospace, "Cascadia Code", Consolas, monospace;
            font-size: 0.85rem;
        }

        table.dataTable td:nth-child(4) {
            color: var(--accent-dark);
            font-weight: 700;
        }

        table.dataTable td:last-child {
            font-weight: 600;
        }

        .dataTables_wrapper .dataTables_info {
            float: none;
            padding: 0;
            color: var(--muted);
            font-size: 0.82rem;
        }

        .dataTables_wrapper .dataTables_paginate {
            float: none;
            padding: 0;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            min-width: 2.25rem;
            min-height: 2.25rem;
            margin-left: 0.25rem;
            padding: 0.42rem 0.65rem;
            color: var(--muted) !important;
            background: transparent !important;
            border: 1px solid transparent !important;
            border-radius: 0.45rem;
            transition: color 160ms ease, background-color 160ms ease, border-color 160ms ease;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            color: var(--accent-dark) !important;
            background: var(--accent-soft) !important;
            border-color: var(--accent-soft) !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            color: #fff !important;
            background: var(--accent) !important;
            border-color: var(--accent) !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            color: #a8b2b8 !important;
            background: transparent !important;
        }

        .empty-state {
            display: grid;
            max-width: 42rem;
            margin: 0 auto;
            padding: 4.5rem 2rem;
            place-items: center;
            text-align: center;
            background: rgba(255, 255, 255, 0.74);
            border-radius: 1.25rem;
            box-shadow: var(--shadow);
        }

        .empty-mark {
            display: grid;
            width: 4.5rem;
            height: 4.5rem;
            margin-bottom: 1.25rem;
            place-items: center;
            color: var(--accent);
            background: var(--accent-soft);
            border-radius: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .empty-state h2 {
            margin: 0 0 0.5rem;
            font-size: 1.45rem;
            letter-spacing: -0.025em;
        }

        .empty-state p {
            max-width: 34rem;
            margin: 0;
            color: var(--muted);
        }

        .print-title {
            display: none;
        }

        @media (max-width: 760px) {
            .page-shell {
                padding: 3rem 0 4rem;
            }

            .page-intro,
            .control-panel {
                grid-template-columns: 1fr;
            }

            .page-intro {
                gap: 1.35rem;
            }

            .report-date {
                min-width: 0;
            }

            .control-panel {
                gap: 1rem;
            }

            .panel-heading,
            .table-tools,
            .table-footer {
                align-items: stretch;
                flex-direction: column;
            }

            .panel-heading,
            .dataTables_wrapper {
                padding-inline: 1.15rem;
            }

            .student-count {
                align-self: flex-start;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
            }

            .dataTables_wrapper .dataTables_filter label {
                align-items: stretch;
                flex-direction: column;
            }

            .dataTables_wrapper .dataTables_paginate {
                text-align: left;
            }
        }

        @media (max-width: 480px) {
            .topbar-inner,
            .page-shell {
                width: min(100% - 1.25rem, 1180px);
            }

            .brand-label {
                display: none;
            }

            h1 {
                font-size: 2.45rem;
            }

            .dataTables_wrapper .dt-button {
                flex: 1 1 calc(33.333% - 0.45rem);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        @media print {
            @page {
                margin: 1.5cm;
            }

            body {
                color: #17212b;
                background: #fff;
                font-family: Arial, sans-serif;
                font-size: 11pt;
            }

            .skip-link,
            .topbar,
            .page-intro,
            .control-panel,
            .table-tools,
            .table-footer {
                display: none !important;
            }

            .page-shell {
                width: 100%;
                padding: 0;
            }

            .print-title {
                display: block;
                margin-bottom: 1.25rem;
                padding-bottom: 0.8rem;
                border-bottom: 2px solid #176b58;
                font-size: 16pt;
                font-weight: 700;
            }

            .ranking-panel {
                overflow: visible;
                border-radius: 0;
                box-shadow: none;
            }

            .panel-heading {
                padding: 0 0 0.8rem;
            }

            .dataTables_wrapper {
                padding: 0;
            }

            table.dataTable thead th,
            table.dataTable tbody td {
                padding: 0.5rem 0.65rem;
                color: #17212b;
                background: #fff;
                border-bottom: 1px solid #cad4d1;
            }
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke isi laporan</a>

    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="home.php" aria-label="Kembali ke dashboard SPK Matching">
                <span class="brand-mark" aria-hidden="true">SPK</span>
                <span class="brand-label">Sistem Pendukung Keputusan</span>
            </a>
            <a class="back-link" href="ranking.php">
                <span aria-hidden="true">&#8592;</span>
                Kembali ke ranking
            </a>
        </div>
    </header>

    <main id="main-content" class="page-shell">
        <header class="page-intro">
            <div>
                <p class="eyebrow">Dokumen hasil seleksi</p>
                <h1>Laporan ranking siswa</h1>
                <p class="intro-copy">Tinjau peringkat siswa per perusahaan, lalu cetak atau ekspor data sesuai kebutuhan.</p>
            </div>
            <div class="report-date">
                Tanggal laporan
                <strong><?= date('d/m/Y') ?></strong>
            </div>
        </header>

        <?php if (!empty($final_results)): ?>
            <section class="control-panel" aria-labelledby="company-filter-title">
                <div>
                    <span id="company-filter-title" class="control-title">Pilih perusahaan</span>
                    <p class="control-description">Data dan opsi ekspor akan menyesuaikan pilihan Anda.</p>
                </div>
                <div class="select-wrap">
                    <select id="company-select" class="company-dropdown" aria-label="Pilih perusahaan">
                    <?php 
                    $company_index = 0;
                    foreach ($final_results as $perusahaan => $data):
                        $tab_id = 'company-' . $company_index;
                    ?>
                        <option value="<?= $tab_id ?>" data-company-name="<?= htmlspecialchars($perusahaan) ?>">
                            <?= htmlspecialchars($perusahaan) ?>
                        </option>
                    <?php $company_index++; endforeach; ?>
                    </select>
                </div>
            </section>

            <div id="print-company-title" class="print-title"></div>

            <?php 
            $company_index = 0;
            foreach ($final_results as $perusahaan => $data):
                $tab_id = 'company-' . $company_index;
            ?>
                <section id="<?= $tab_id ?>" class="tabcontent ranking-panel<?= $company_index === 0 ? ' is-active' : '' ?>" aria-labelledby="title-<?= $tab_id ?>">
                    <header class="panel-heading">
                        <div>
                            <p class="panel-kicker">Perusahaan tujuan</p>
                            <h2 id="title-<?= $tab_id ?>" class="company-title"><?= htmlspecialchars($perusahaan) ?></h2>
                        </div>
                        <span class="student-count"><?= count($data) ?> siswa</span>
                    </header>

                    <table id="table-<?= $tab_id ?>" class="display nowrap">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th>NIS</th>
                                <th>Nilai Akhir</th>
                                <th>Ranking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($data as $row): 
                            ?>
                                <tr>
                                    <td><?= $no ?></td>
                                    <td><?= htmlspecialchars($row['siswa']) ?></td>
                                    <td><?= htmlspecialchars($row['nis']) ?></td>
                                    <td><?= number_format($row['final_score'], 2) ?></td>
                                    <td><?= $no ?></td>
                                </tr>
                            <?php 
                            $no++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </section>
            <?php $company_index++; endforeach; ?>
        <?php else: ?>
            <section class="empty-state" aria-labelledby="empty-title">
                <div class="empty-mark" aria-hidden="true">0</div>
                <h2 id="empty-title">Belum ada data ranking</h2>
                <p>Data akan tampil di sini setelah penilaian siswa dan perusahaan tersedia.</p>
            </section>
        <?php endif; ?>
    </main>

    <script>
    function showSelectedTab() {
        var companySelect = document.getElementById('company-select');
        if (!companySelect) return;

        var selectedValue = companySelect.value;
        var selectedOption = companySelect.options[companySelect.selectedIndex];
        var companyName = selectedOption.getAttribute('data-company-name');

        document.querySelectorAll('.tabcontent').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.id === selectedValue);
        });

        document.getElementById('print-company-title').textContent = 'Laporan ranking siswa — ' + companyName;

        if ($.fn.dataTable) {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
        }
    }

    var companySelect = document.getElementById('company-select');
    if (companySelect) companySelect.addEventListener('change', showSelectedTab);

    function customizePrint(win, companyName, reportDate) {
        var doc = win.document;
        var table = doc.querySelector('table');

        doc.title = 'Laporan Ranking Siswa - ' + companyName;
        doc.body.innerHTML = [
            '<header class="document-header">',
                '<div class="document-brand"><strong>SPK MATCHING</strong><span>LAPORAN RANKING</span></div>',
                '<div class="document-hero">',
                    '<div><small>HASIL SELEKSI SISWA</small><h1>Laporan ranking siswa</h1><p id="print-company"></p></div>',
                    '<div class="document-date"><small>TANGGAL LAPORAN</small><strong>' + reportDate + '</strong></div>',
                '</div>',
            '</header>',
            '<main class="document-table"></main>',
            '<footer>Dicetak pada ' + reportDate + '<span>SPK Matching</span></footer>'
        ].join('');
        doc.getElementById('print-company').textContent = companyName;
        doc.querySelector('.document-table').appendChild(table);
        $(doc.head).append('<style>' +
            '@page{size:A4 portrait;margin:18mm 13mm 16mm}' +
            '*{box-sizing:border-box}' +
            'body{margin:0;color:#34424d;background:#fff;font-family:Arial,sans-serif;font-size:9pt;-webkit-print-color-adjust:exact;print-color-adjust:exact}' +
            '.document-header{margin:0 0 18px}' +
            '.document-brand{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}' +
            '.document-brand strong{color:#176b58;font-size:10px;letter-spacing:1.2px}' +
            '.document-brand span{color:#667581;font-size:8px;letter-spacing:1px}' +
            '.document-hero{display:grid;grid-template-columns:minmax(0,1fr) 108px;gap:20px;align-items:start}' +
            '.document-hero small,.document-date small{display:block;color:#176b58;font-size:7px;font-weight:700;letter-spacing:1.4px}' +
            '.document-hero h1{margin:7px 0 6px;color:#18242f;font-size:22px;line-height:1.1;letter-spacing:-.6px}' +
            '.document-hero p{max-width:460px;margin:0;color:#56656f;font-size:10px;line-height:1.4}' +
            '.document-date{padding-left:14px;border-left:2px solid #176b58}' +
            '.document-date small{margin-bottom:7px;color:#7b898f;letter-spacing:1px}' +
            '.document-date strong{color:#18242f;font-size:11px}' +
            'table{width:100%!important;border-collapse:collapse!important;border:0!important}' +
            'thead{display:table-header-group}' +
            'tr{page-break-inside:avoid}' +
            'th{padding:9px!important;color:#fff!important;background:#18242f!important;border:0!important;font-size:8px!important;text-align:left!important;text-transform:uppercase;letter-spacing:.4px}' +
            'td{padding:9px!important;color:#34424d!important;background:#fff!important;border:0!important;border-bottom:1px solid #dbe4e5!important;font-size:9px!important}' +
            'tbody tr:nth-child(even) td{background:#f3f7f6!important}' +
            'th:nth-child(1),th:nth-child(4),th:nth-child(5),td:nth-child(1),td:nth-child(4),td:nth-child(5){text-align:center!important}' +
            'td:nth-child(2),td:nth-child(4){font-weight:700!important}' +
            'td:nth-child(4){color:#105344!important}' +
            'footer{position:fixed;right:0;bottom:-10mm;left:0;display:flex;justify-content:space-between;color:#7b898f;font-size:8px}' +
        '</style>');
    }

    function customizePdf(doc, companyName, reportDate) {
        var table = doc.content.find(function (item) {
            return item.table;
        });

        doc.pageSize = 'A4';
        doc.pageMargins = [40, 72, 40, 52];
        doc.info = {
            title: 'Laporan Ranking Siswa - ' + companyName,
            subject: 'Hasil ranking siswa berdasarkan profile matching',
            creator: 'SPK Matching'
        };
        doc.defaultStyle = {
            font: 'Roboto',
            fontSize: 9,
            color: '#34424d'
        };
        doc.header = {
            margin: [40, 24, 40, 0],
            columns: [
                { text: 'SPK MATCHING', color: '#176b58', bold: true, fontSize: 10, characterSpacing: 1.2 },
                { text: 'LAPORAN RANKING', color: '#667581', fontSize: 8, characterSpacing: 1, alignment: 'right' }
            ]
        };
        doc.footer = function (currentPage, pageCount) {
            return {
                margin: [40, 14, 40, 0],
                columns: [
                    { text: 'Dicetak pada ' + reportDate, color: '#7b898f', fontSize: 8 },
                    { text: 'Halaman ' + currentPage + ' dari ' + pageCount, color: '#7b898f', fontSize: 8, alignment: 'right' }
                ]
            };
        };

        if (!table) return;

        table.table.headerRows = 1;
        table.table.dontBreakRows = true;
        table.table.widths = [30, '*', 82, 68, 54];
        table.table.body.forEach(function (row, rowIndex) {
            row.forEach(function (cell, columnIndex) {
                if (typeof cell !== 'object') {
                    row[columnIndex] = { text: cell };
                    cell = row[columnIndex];
                }

                cell.margin = [0, rowIndex === 0 ? 3 : 2, 0, rowIndex === 0 ? 3 : 2];
                cell.alignment = [0, 3, 4].includes(columnIndex) ? 'center' : 'left';

                if (rowIndex === 0) {
                    cell.fillColor = '#18242f';
                    cell.color = '#ffffff';
                    cell.bold = true;
                    cell.fontSize = 8;
                } else {
                    cell.fillColor = rowIndex % 2 === 0 ? '#f3f7f6' : '#ffffff';
                    if (columnIndex === 1 || columnIndex === 3) cell.bold = true;
                    if (columnIndex === 3) cell.color = '#105344';
                }
            });
        });
        table.layout = {
            hLineWidth: function (index, node) {
                return index === 0 || index === 1 || index === node.table.body.length ? 1 : 0.5;
            },
            hLineColor: function (index) {
                return index <= 1 ? '#18242f' : '#dbe4e5';
            },
            vLineWidth: function () { return 0; },
            paddingLeft: function () { return 9; },
            paddingRight: function () { return 9; },
            paddingTop: function () { return 6; },
            paddingBottom: function () { return 6; }
        };

        doc.content = [
            {
                margin: [0, 4, 0, 18],
                columns: [
                    {
                        width: '*',
                        stack: [
                            { text: 'HASIL SELEKSI SISWA', color: '#176b58', bold: true, fontSize: 8, characterSpacing: 1.4, margin: [0, 0, 0, 7] },
                            { text: 'Laporan ranking siswa', color: '#18242f', bold: true, fontSize: 22, margin: [0, 0, 0, 6] },
                            { text: companyName, color: '#56656f', fontSize: 10 }
                        ]
                    },
                    {
                        width: 108,
                        margin: [12, 2, 0, 0],
                        stack: [
                            { text: 'TANGGAL LAPORAN', color: '#7b898f', fontSize: 7, characterSpacing: 1, margin: [0, 0, 0, 6] },
                            { text: reportDate, color: '#18242f', bold: true, fontSize: 11 }
                        ]
                    }
                ]
            },
            table
        ];
    }

    $(document).ready(function() {
        <?php 
        if (!empty($final_results)) {
            $company_index = 0;
            foreach ($final_results as $perusahaan => $data):
                $tab_id = 'company-' . $company_index;
                $export_title = json_encode('Laporan Ranking Siswa - ' . $perusahaan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
            $('#table-<?= $tab_id ?>').DataTable({
                dom: '<"table-tools"<"export-actions"B><"table-search"f>><"table-scroll"rt><"table-footer"ip>',
                buttons: [
                    {
                        extend: 'print',
                        text: 'Cetak',
                        title: <?= $export_title ?>,
                        messageTop: 'Tanggal: <?= date('d/m/Y') ?>',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4]
                        },
                        customize: function (win) {
                            customizePrint(win, <?= json_encode($perusahaan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, '<?= date('d/m/Y') ?>');
                        }
                    },
                    {
                        extend: 'pdf',
                        text: 'PDF',
                        title: <?= $export_title ?>,
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4]
                        },
                        customize: function (doc) {
                            customizePdf(doc, <?= json_encode($perusahaan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, '<?= date('d/m/Y') ?>');
                        }
                    },
                    {
                        extend: 'excel',
                        text: 'Excel',
                        title: <?= $export_title ?>,
                        messageTop: 'Tanggal: <?= date('d/m/Y') ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    { extend: 'copy', text: 'Salin' },
                    { extend: 'csv', text: 'CSV' }
                ],
                responsive: true,
                order: [[3, 'desc']],
                pageLength: 10,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json',
                    search: 'Cari siswa',
                    searchPlaceholder: 'Nama atau NIS'
                }
            });
        <?php 
            $company_index++;
            endforeach; 
        }
        ?>

        showSelectedTab();
    });
    </script>
</body>
</html>
