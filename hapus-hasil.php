<?php

include 'config/app.php';

$nis = (int)$_GET['nis'];

if(delete_hasil($nis) > 0) {
    echo "<script>
            alert('Data Berhasil Dihapus');
            document.location.href = 'data-siswa.php';
    </script>";
} else {
    echo "<script>
            alert('Data Gagal Dihapus');
            document.location.href = 'data-siswa.php';
    </script>";
}
