<?php

$host = "localhost";
$user = "root";
$pass = "root";
$db   = "inventaris";

date_default_timezone_set('Asia/Jakarta');

$conn = mysqli_connect($host,$user,$pass,$db);

if(!$conn){
    die("Koneksi gagal : ".mysqli_connect_error());
}

?>