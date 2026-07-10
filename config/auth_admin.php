<?php

if(session_status() == PHP_SESSION_NONE){
    session_start();
}

// Cegah browser menyimpan cache halaman terproteksi
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if(!isset($_SESSION['role'])){
    header("Location: ../auth/login.php");
    exit;
}

if($_SESSION['role'] != "admin"){
    header("Location: ../auth/login.php");
    exit;
}