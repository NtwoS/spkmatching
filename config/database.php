<!-- <?php

$host = "localhost";
$username = "root";
$password = "";
$database = "profile7";

$koneksi = new mysqli($host,$username,$password,$database);

if(!$koneksi) {
    echo "database tidak terkoneksi";
} else {
    echo "database terkoneksi";
}

?> -->

<?php

$db = mysqli_connect('localhost', 'root', '', 'profile7');

// if(!$db) {
//     echo "database tidak terkoneksi";
// } else {
//     echo 'database terkoneksi';
// }