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

// Kosongkan dan hapus semua session
$_SESSION = [];
session_unset();
session_destroy();

// Redirect ke halaman login
header("Location: login.php");
exit;




