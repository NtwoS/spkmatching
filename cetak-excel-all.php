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
        global $koneksi;
        $result = mysqli_query($koneksi, $sql);
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
    <title>Data Ranking Siswa</title>

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    
    <!-- jQuery -->
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <link rel="icon" href="img/smk.jpg">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .dropdown-container {
            margin-bottom: 20px;
            text-align: center;
        }
        .company-dropdown {
            padding: 10px 15px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: white;
            cursor: pointer;
            width: 800px;
        }
        .company-dropdown:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }
        .tabcontent {
            display: none;
            padding: 20px 0;
            border-top: none;
            animation: fadeEffect 1s;
        }
        @keyframes fadeEffect {
            from {opacity: 0;}
            to {opacity: 1;}
        }
        .dataTables_wrapper {
            margin-top: 20px;
        }
        table.dataTable thead {
            background-color: #007bff;
            color: white;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        .company-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .print-title {
            display: none;
        }
        @media print {
            .dropdown-container, .company-dropdown {
                display: none;
            }
            .print-title {
                display: block;
                text-align: center;
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>DATA RANKING SISWA</h1>
        
        <?php if (!empty($final_results)): ?>
            <!-- Dropdown untuk memilih perusahaan -->
            <div class="dropdown-container">
                <select id="company-select" class="company-dropdown">
                    <option value="">-- Pilih Perusahaan --</option>
                    <?php 
                    $first = true;
                    foreach ($final_results as $perusahaan => $data): 
                        $tab_id = preg_replace('/[^a-zA-Z0-9]/', '_', $perusahaan);
                    ?>
                        <option value="<?= $tab_id ?>" <?= $first ? 'selected' : '' ?> data-company-name="<?= htmlspecialchars($perusahaan) ?>">
                            <?= htmlspecialchars($perusahaan) ?>
                        </option>
                    <?php 
                    $first = false;
                    endforeach; 
                    ?>
                </select>
            </div>
            
            <!-- Judul perusahaan yang akan dicetak -->
            <div id="print-company-title" class="print-title"></div>
            
            <!-- Konten untuk setiap perusahaan -->
            <?php 
            $first = true;
            foreach ($final_results as $perusahaan => $data): 
                $tab_id = preg_replace('/[^a-zA-Z0-9]/', '_', $perusahaan);
            ?>
                <div id="<?= $tab_id ?>" class="tabcontent" style="<?= $first ? 'display: block;' : '' ?>">
                    <div class="company-title">PERUSAHAAN <?= htmlspecialchars($perusahaan) ?></div>
                    <p>Tanggal: <?= date('d/m/Y') ?></p>
                    
                    <table id="table-<?= $tab_id ?>" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th>NIS</th>
                                <!-- <th>Nilai Core Factor</th>
                                <th>Nilai Secondary Factor</th> -->
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
                                    <!-- <td><?= number_format($row['cf'], 2) ?></td>
                                    <td><?= number_format($row['sf'], 2) ?></td> -->
                                    <td><?= number_format($row['final_score'], 2) ?></td>
                                    <td><?= $no ?></td>
                                </tr>
                            <?php 
                            $no++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php 
            $first = false;
            endforeach; 
            ?>
        <?php else: ?>
            <div class="no-data">
                <p>Tidak ada data siswa yang ditemukan.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Fungsi untuk menampilkan tab berdasarkan dropdown
    function showSelectedTab() {
        var selectedValue = document.getElementById("company-select").value;
        var selectedOption = document.getElementById("company-select").options[document.getElementById("company-select").selectedIndex];
        var companyName = selectedOption.getAttribute("data-company-name");
        
        // Sembunyikan semua tab content
        var tabcontent = document.getElementsByClassName("tabcontent");
        for (var i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        
        // Tampilkan tab yang dipilih
        if (selectedValue) {
            document.getElementById(selectedValue).style.display = "block";
            
            // Perbarui judul untuk dicetak
            document.getElementById("print-company-title").textContent = "DATA RANKING SISWA - PERUSAHAAN: " + companyName;
        }
    }
    
    // Event listener untuk dropdown
    document.getElementById("company-select").addEventListener("change", showSelectedTab);
    
    // Inisialisasi DataTables untuk setiap tabel
    $(document).ready(function() {
        <?php 
        if (!empty($final_results)) {
            foreach ($final_results as $perusahaan => $data): 
                $tab_id = preg_replace('/[^a-zA-Z0-9]/', '_', $perusahaan);
        ?>
            $('#table-<?= $tab_id ?>').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'print',
                        title: 'DATA RANKING SISWA - PERUSAHAAN: <?= addslashes($perusahaan) ?>',
                        messageTop: 'Tanggal: <?= date('d/m/Y') ?>',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function (win) {
                            $(win.document.body).find('h1').text('DATA RANKING SISWA - PERUSAHAAN <?= addslashes($perusahaan) ?>');
                        }
                    },
                    {
                        extend: 'pdf',
                        title: 'DATA RANKING SISWA - PERUSAHAAN: <?= addslashes($perusahaan) ?>',
                        messageTop: 'Tanggal: <?= date('d/m/Y') ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'excel',
                        title: 'DATA RANKING SISWA - PERUSAHAAN: <?= addslashes($perusahaan) ?>',
                        messageTop: 'Tanggal: <?= date('d/m/Y') ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    'copy', 'csv'
                ],
                responsive: true,
                order: [[5, 'desc']], // Default urutkan berdasarkan nilai akhir (desc)
                pageLength: 10,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
                }
            });
        <?php 
            endforeach; 
        }
        ?>
        
        // Inisialisasi judul untuk perusahaan pertama
        var firstOption = document.getElementById("company-select").options[1]; // Index 1 karena index 0 adalah placeholder
        if (firstOption) {
            var firstCompanyName = firstOption.getAttribute("data-company-name");
            document.getElementById("print-company-title").textContent = "DATA RANKING SISWA - PERUSAHAAN: " + firstCompanyName;
        }
    });
    </script>
</body>
</html>     