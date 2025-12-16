<?php
require_once 'config.php'; // Memuat konfigurasi dan session
Session::start();

// Hancurkan semua data sesi yang ada
Session::destroy();

// Arahkan pengguna kembali ke halaman login
redirect('login.php');
?>