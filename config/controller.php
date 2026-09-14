<?php

//Function Menampilkan Siswa
function select($query)
{
    //panggil koneksi
    global $db;

    $result = mysqli_query($db, $query);
    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function ensure_pengaturan_pendaftaran_table()
{
    global $db;
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    if (!isset($db)) {
        return false;
    }

    $query = "CREATE TABLE IF NOT EXISTS pengaturan_pendaftaran (
        id INT NOT NULL PRIMARY KEY,
        status_pendaftaran ENUM('buka', 'tutup') NOT NULL DEFAULT 'buka',
        nis_min VARCHAR(30) NOT NULL DEFAULT '0',
        nis_max VARCHAR(30) NOT NULL DEFAULT '999999999999999999999999999999',
        hanya_terkalkulasi TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $ready = mysqli_query($db, $query) !== false;
    return $ready;
}

function normalisasi_nis_pendaftaran($nis)
{
    $nis = trim((string) $nis);

    if (!preg_match('/^[0-9]{1,30}$/D', $nis)) {
        return null;
    }

    return ltrim($nis, '0') ?: '0';
}

function bandingkan_nis_pendaftaran($nis_a, $nis_b)
{
    if (strlen($nis_a) !== strlen($nis_b)) {
        return strlen($nis_a) <=> strlen($nis_b);
    }

    return strcmp($nis_a, $nis_b);
}

function get_pengaturan_pendaftaran()
{
    global $db;

    if (!ensure_pengaturan_pendaftaran_table()) {
        return null;
    }

    mysqli_query($db, "INSERT IGNORE INTO pengaturan_pendaftaran
        (id, status_pendaftaran, nis_min, nis_max, hanya_terkalkulasi)
        VALUES (1, 'buka', '0', '999999999999999999999999999999', 0)");

    $result = mysqli_query($db, "SELECT status_pendaftaran, nis_min, nis_max, hanya_terkalkulasi
        FROM pengaturan_pendaftaran WHERE id = 1 LIMIT 1");

    if (!$result) {
        return null;
    }

    $setting = mysqli_fetch_assoc($result);
    if (!$setting) {
        return null;
    }

    $setting['hanya_terkalkulasi'] = (int) $setting['hanya_terkalkulasi'];
    return $setting;
}

function update_pengaturan_pendaftaran($post)
{
    global $db;

    if (!ensure_pengaturan_pendaftaran_table()) {
        return [
            'success' => false,
            'message' => 'Pengaturan pendaftaran gagal disimpan karena tabel belum dapat dibuat.'
        ];
    }

    $nis_min = normalisasi_nis_pendaftaran($post['nis_min'] ?? '');
    $nis_max = normalisasi_nis_pendaftaran($post['nis_max'] ?? '');

    if ($nis_min === null || $nis_max === null) {
        return [
            'success' => false,
            'message' => 'NIS Awal dan NIS Akhir wajib berupa angka maksimal 30 digit.'
        ];
    }

    if (bandingkan_nis_pendaftaran($nis_min, $nis_max) > 0) {
        return [
            'success' => false,
            'message' => 'NIS Awal tidak boleh lebih besar daripada NIS Akhir.'
        ];
    }

    $status = ($post['status_pendaftaran'] ?? '') === 'buka' ? 'buka' : 'tutup';
    $hanya_terkalkulasi = isset($post['hanya_terkalkulasi']) ? 1 : 0;
    $query = "UPDATE pengaturan_pendaftaran
        SET status_pendaftaran = ?, nis_min = ?, nis_max = ?, hanya_terkalkulasi = ?
        WHERE id = 1";
    $stmt = mysqli_prepare($db, $query);

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Pengaturan pendaftaran gagal disimpan.'
        ];
    }

    mysqli_stmt_bind_param($stmt, 'sssi', $status, $nis_min, $nis_max, $hanya_terkalkulasi);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return [
        'success' => $success,
        'message' => $success
            ? 'Pengaturan pendaftaran berhasil disimpan.'
            : 'Pengaturan pendaftaran gagal disimpan.'
    ];
}

function validasi_pendaftaran_siswa($nis)
{
    global $db;

    $nis_normal = normalisasi_nis_pendaftaran($nis);
    $nis_tampil = trim((string) $nis);

    if ($nis_normal === null) {
        return 'NIS harus berupa angka maksimal 30 digit.';
    }

    $setting = get_pengaturan_pendaftaran();
    if ($setting === null) {
        return 'Pengaturan pendaftaran belum dapat dibaca. Silakan coba lagi nanti.';
    }

    if ($setting['status_pendaftaran'] !== 'buka') {
        return 'Pendaftaran akun siswa PKL saat ini sedang ditutup oleh Admin.';
    }

    $nis_min = normalisasi_nis_pendaftaran($setting['nis_min']);
    $nis_max = normalisasi_nis_pendaftaran($setting['nis_max']);
    if ($nis_min === null || $nis_max === null || bandingkan_nis_pendaftaran($nis_min, $nis_max) > 0) {
        return 'Rentang NIS pendaftaran belum diatur dengan benar oleh Admin.';
    }

    if (bandingkan_nis_pendaftaran($nis_normal, $nis_min) < 0
        || bandingkan_nis_pendaftaran($nis_normal, $nis_max) > 0) {
        return "NIS {$nis_tampil} tidak memenuhi syarat rentang pendaftaran PKL yang ditentukan oleh sekolah.";
    }

    if ((int) $setting['hanya_terkalkulasi'] === 1) {
        $stmt = mysqli_prepare($db, 'SELECT 1 FROM datasiswa WHERE nis = ? LIMIT 1');
        if (!$stmt) {
            return 'Data kalkulasi siswa belum dapat diperiksa. Silakan coba lagi nanti.';
        }

        mysqli_stmt_bind_param($stmt, 's', $nis_normal);
        $executed = mysqli_stmt_execute($stmt);
        $stored = $executed && mysqli_stmt_store_result($stmt);
        $exists = $stored && mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if (!$stored) {
            return 'Data kalkulasi siswa belum dapat diperiksa. Silakan coba lagi nanti.';
        }

        if (!$exists) {
            return "NIS {$nis_tampil} belum terdaftar dalam kalkulasi penempatan PKL.";
        }
    }

    return null;
}

function get_pengaturan_csrf_token()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['pengaturan_csrf_token'])) {
        $_SESSION['pengaturan_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['pengaturan_csrf_token'];
}

function validasi_pengaturan_csrf_token($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    return is_string($token)
        && isset($_SESSION['pengaturan_csrf_token'])
        && hash_equals($_SESSION['pengaturan_csrf_token'], $token);
}

//Function Menambahkan Data siswa
function create_siswa($post)
{
    global $db;

    $siswa = $post['siswa'];
    $nis = $post['nis'];
    $soal_jawaban = [];
    $perusahaan = [];
    $factor = [];

    $data_soal = select("SELECT * FROM soal ORDER BY id_soal DESC");

    foreach ($data_soal as $index => $soal) {
        $soal_key = "soal_" . ($index + 1);
        if (isset($post[$soal_key])) {
            $soal_jawaban[] = $post[$soal_key];
            $perusahaan[] = $soal['perusahaan'];
            $factor[] = $soal['factor'];
        }
    }


    $columns = ["siswa", "nis"];
    $values = ["'$siswa'", "'$nis'"];

    for ($i = 0; $i < count($soal_jawaban); $i++) {
        $columns[] = "soal" . ($i + 1);
        $columns[] = "perusahaan" . ($i + 1);
        $columns[] = "factor" . ($i + 1);
        $values[] = "'" . $soal_jawaban[$i] . "'";
        $values[] = "'" . $perusahaan[$i] . "'";
        $values[] = "'" . $factor[$i] . "'";
    }

    $columns_str = implode(", ", $columns);
    $values_str = implode(", ", $values);


    $query = "INSERT INTO datasiswa ($columns_str) VALUES ($values_str)";

    mysqli_query($db, $query);

    return mysqli_affected_rows($db);
}
// Function Delete Hasil
function delete_hasil($nis)
{
    global $db;

    //query hapus data kriteria
    $query = "DELETE FROM datasiswa WHERE nis = $nis";

    mysqli_query($db, $query);

    return mysqli_affected_rows($db);

}

//Function Menambahkan Soal
function create_soal($post)
{
    global $db;
    $perusahaan = $post["perusahaan"];
    $soal = $post["soal"];
    $jawaban_a = $post["jawaban_a"];
    $value_a = $post["value_a"];
    $jawaban_b = $post["jawaban_b"];
    $value_b = $post["value_b"];
    $jawaban_c = $post["jawaban_c"];
    $value_c = $post["value_c"];
    $jawaban_d = $post["jawaban_d"];
    $value_d = $post["value_d"];
    $factor = $post["factor"];

    //query tambah data
    $query = "INSERT INTO soal VALUES(null,'$perusahaan','$soal', '$jawaban_a', '$value_a', '$jawaban_b', '$value_b','$jawaban_c', '$value_c', '$jawaban_d', '$value_d','$factor')";

    mysqli_query($db, $query);

    return mysqli_affected_rows($db);
}

//Function Mengubah Data Soal
function ubah_soal($post)
{
    global $db;

    $id_soal = $post["id_soal"];
    $perusahaan = $post["perusahaan"];
    $factor = $post["factor"];
    $soal = $post["soal"];
    $a = $post["jawaban_a"];
    $value_a = $post["value_a"];
    $b = $post["jawaban_b"];
    $value_b = $post["value_b"];
    $c = $post["jawaban_c"];
    $value_c = $post["value_c"];
    $d = $post["jawaban_d"];
    $value_d = $post["value_d"];

    // Update query
    $query = "UPDATE soal SET 
            perusahaan = '$perusahaan',
            factor = '$factor',
            soal = '$soal', 
            jawaban_a = '$a', 
            value_a = '$value_a', 
            jawaban_b = '$b',
            value_b = '$value_b', 
            jawaban_c = '$c', 
            value_c = '$value_c',
            jawaban_d = '$d',
            value_d = '$value_d'
            WHERE id_soal = '$id_soal'";

    return mysqli_query($db, $query);
}

// Function Delete Soal
function delete_soal($id_soal)
{

    global $db;

    //query hapus data kriteria
    $query = "DELETE FROM soal WHERE id_soal = $id_soal";

    mysqli_query($db, $query);

    return mysqli_affected_rows($db);
}

// Function to calculate GAP
function calculateGap($nilai_siswa)
{
    $nilai_standar = 4;
    $nilai_siswa = is_numeric($nilai_siswa) ? (float) $nilai_siswa : 0;
    return $nilai_siswa - $nilai_standar;
}

// function untuk menghitung bobot
function calculateBobot($gap)
{
    switch ($gap) {
        case 0:
            return 4;
        case 1:
            return 3.5;
        case -1:
            return 3;
        case 2:
            return 2.5;
        case -2:
            return 2;
        case 3:
            return 1.5;
        case -3:
            return 1;
        default:
            return 0; // Default bobot jika gap tidak sesuai
    }
}

// function untuk menghitung rata-rata nilai soal dengan faktor "core"
// Fungsi untuk menghitung rata-rata nilai core
function calculateCoreAverage($grouped_data, $selected_perusahaan)
{
    if (!isset($grouped_data[$selected_perusahaan])) {
        return 0;
    }

    $core_sum = 0;
    $core_count = 0;

    foreach ($grouped_data[$selected_perusahaan] as $siswa => $data) {
        foreach ($data['factor'] as $index => $factor) {
            if ($factor === 'core' && isset($data['soal'][$index])) {
                $core_sum += $data['soal'][$index];
                $core_count++;
            }
        }
    }

    return $core_count > 0 ? $core_sum / $core_count : 0;
}

// Fungsi untuk menghitung rata-rata nilai secondary
function calculateSecondaryAverage($grouped_data, $selected_perusahaan)
{
    if (!isset($grouped_data[$selected_perusahaan])) {
        return 0;
    }

    $secondary_sum = 0;
    $secondary_count = 0;

    foreach ($grouped_data[$selected_perusahaan] as $siswa => $data) {
        foreach ($data['factor'] as $index => $factor) {
            if ($factor === 'secondary' && isset($data['soal'][$index])) {
                $secondary_sum += $data['soal'][$index];
                $secondary_count++;
            }
        }
    }

    return $secondary_count > 0 ? $secondary_sum / $secondary_count : 0;
}

// function Tambah akun
function create_akun($post)
{
    global $db;

    $nis = mysqli_real_escape_string($db, $post['nis']);
    $nama = mysqli_real_escape_string($db, $post['nama']);
    $username = mysqli_real_escape_string($db, $post['username']);
    $email = mysqli_real_escape_string($db, $post['email']);
    $password = mysqli_real_escape_string($db, $post['password']);
    $level = mysqli_real_escape_string($db, $post['level']);

    // enskripsi password

    $password = password_hash($password, PASSWORD_DEFAULT);

    // query tambah data
    $query = "INSERT INTO akun (nis, nama, username, email, password, level) VALUES (?, ?, ?, ?, ?, ?)";

    if ($stmt = mysqli_prepare($db, $query)) {

        mysqli_stmt_bind_param($stmt, "ssssss",$nis , $nama, $username, $email, $password, $level);

        mysqli_stmt_execute($stmt);

        $affected_rows = mysqli_stmt_affected_rows($stmt);

        mysqli_stmt_close($stmt);

        return $affected_rows;
    } else {

        return 0;
    }
}

//function Delete Akun
function delete_akun($id_akun)
{

    global $db;

    //query hapus data kriteria
    $query = "DELETE FROM akun WHERE id_akun = $id_akun";

    mysqli_query($db, $query);

    return mysqli_affected_rows($db);
}

// function Ubah akun
function update_akun($post) {
    global $db;
    
    // Melakukan escape dan persiapan data
    $id_akun = (int)$post['id_akun']; // Mengubah ke integer untuk keamanan
     $nis = mysqli_real_escape_string($db, $post['nis']);
    $nama = mysqli_real_escape_string($db, $post['nama']);
    $username = mysqli_real_escape_string($db, $post['username']);
    $email = mysqli_real_escape_string($db, $post['email']);
    $level = mysqli_real_escape_string($db, $post['level']);
    
    // Menyiapkan parameter dan bagian query awal
    $params = [$nis,$nama, $username, $email, $level]; // Array parameter
    $types = "sssss"; // Tipe data parameter (string, string, string, string)
    $query = "UPDATE akun SET nis = ?, nama = ?, username = ?, email = ?, level = ?";
    
    if (!empty($post['password'])) {
        $password = password_hash($post['password'], PASSWORD_DEFAULT); 
        $query .= ", password = ?"; 
        $types .= "s";
        $params[] = $password; 
    }
    
    $query .= " WHERE id_akun = ?";
    $types .= "i"; 
    $params[] = $id_akun; 
    
    $stmt = mysqli_prepare($db, $query);
    if ($stmt) {
       
        mysqli_stmt_bind_param($stmt, $types, ...$params); 
        mysqli_stmt_execute($stmt);
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affected_rows;
    }
    
    // Mengembalikan 0 jika gagal
    return 0;
}

// function Tambah perusahaan
function create_perusahaan($post)
{
    global $db;

    $perusahaan = mysqli_real_escape_string($db, $post['perusahaan']);
    $jurusan = mysqli_real_escape_string($db, $post['jurusan']);



    // query tambah data
    $query = "INSERT INTO perusahaan (perusahaan, jurusan) VALUES (?, ?)";

    if ($stmt = mysqli_prepare($db, $query)) {

        mysqli_stmt_bind_param($stmt, "ss", $perusahaan, $jurusan);

        mysqli_stmt_execute($stmt);

        $affected_rows = mysqli_stmt_affected_rows($stmt);

        mysqli_stmt_close($stmt);

        return $affected_rows;
    } else {

        return 0;
    }
}

//function Delete Perusahaan
function delete_perusahaan($id_perusahaan)
{

    global $db;

    //query hapus data kriteria
    $query = "DELETE FROM perusahaan WHERE id_perusahaan = $id_perusahaan";

    mysqli_query($db, $query);

    return mysqli_affected_rows($db);
}

//function Ubah Perusahaan
function update_perusahaan($post) {
    global $db;

    // Escape and prepare data
    $id_perusahaan = (int)$post['id_perusahaan']; // Convert to integer for security
    $perusahaan = mysqli_real_escape_string($db, $post['perusahaan']);
    $jurusan = mysqli_real_escape_string($db, $post['jurusan']);

    // Update query
    $query = "UPDATE perusahaan SET perusahaan = ?, jurusan = ? WHERE id_perusahaan = ?";

    if ($stmt = mysqli_prepare($db, $query)) {
        // Bind parameters
        mysqli_stmt_bind_param($stmt, "ssi", $perusahaan, $jurusan, $id_perusahaan);
        
        // Execute statement
        mysqli_stmt_execute($stmt);
        
        // Get affected rows
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        
        // Close statement
        mysqli_stmt_close($stmt);
        
        return $affected_rows;
    } else {
        return 0;
    }
}



?>
