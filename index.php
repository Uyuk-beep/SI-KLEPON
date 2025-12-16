<?php
require_once 'config.php';
Session::start();

// Ambil 3 pengumuman terbaru
// Query ini sudah diperbaiki untuk tidak menyertakan kolom views, status, dan tanggal yang hilang.
$pengumumanQuery = Database::query(
    "SELECT pengumuman_id, judul, deskripsi, foto, created_at
    FROM pengumuman 
    ORDER BY created_at DESC LIMIT 3" 
);
$pengumuman = $pengumumanQuery->fetchAll();

// Ambil 3 kegiatan terbaru
$kegiatanQuery = Database::query(
    "SELECT kegiatan_id, judul, deskripsi, foto, created_at 
    FROM kegiatan 
    ORDER BY created_at DESC LIMIT 3"
);
$kegiatan = $kegiatanQuery->fetchAll();

// Ambil data struktur organisasi
$struktur_organisasi = Database::query(
    "SELECT nama, posisi, foto FROM struktur ORDER BY urutan ASC"
)->fetchAll();

// Pisahkan pimpinan (elemen pertama) dari anggota
$pimpinan = null;
$anggota = [];
if (!empty($struktur_organisasi)) {
    $pimpinan = array_shift($struktur_organisasi);
    $anggota = $struktur_organisasi;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>

    <link rel="icon" type="image/x-icon" href="assets/img/logopky.gif">
    <link rel="icon" type="type/png" sizes="32x32" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/logopky.gif">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            /* Mengatur background body secara keseluruhan */
            background-color: #f8f9fa; 
        }
        html {
            scrollbar-width: auto; /* atau 'thin' */
            scrollbar-color: #0284c7 #f1f5f9; /* thumb and track color */
        }
        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background-color: #0284c7;
            border-radius: 20px;
            border: 3px solid #f1f5f9;
        }
        ::-webkit-scrollbar-thumb:hover {
            background-color: #0369a1;
        }

        :root {
            --primary-100: #e0f2fe;
            --primary-200: #bae6fd;
            --primary-300: #7dd3fc;
            --primary-500: #0ea5e9;
            --primary-600: #0284c7;
            --primary-800: #075985;
            --secondary-100: #ede9fe;
            --secondary-500: #8b5cf6;
        }

        .gradient-text {
            background-image: linear-gradient(to right, var(--primary-500), var(--secondary-500));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .glass {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .img-hover {
            transition: transform 0.3s ease;
        }
        .img-hover:hover {
            transform: scale(1.05);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        .animate-fade-in {
            animation: fadeIn 1s ease-out forwards;
        }
        .animate-float {
            animation: float 4s ease-in-out infinite;
        }

        #particles-js-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

    /* --- CSS Tambahan untuk Card Visi & Misi --- */
    .visi-misi-card {
        background-color: #ffffff;
        border-radius: 1.5rem; /* rounded-3xl */
        overflow: hidden;
        --tw-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --tw-shadow-colored: 0 10px 15px -3px var(--tw-shadow-color), 0 4px 6px -4px var(--tw-shadow-color);
        box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        transition: transform 0.3s ease;
    }

    .visi-misi-header {
        background-image: linear-gradient(to right, var(--primary-500), var(--secondary-500)); 
        background-color: var(--primary-500);
        color: white;
        padding: 1.5rem;
        text-align: center;
    }

    .visi-misi-content {
        /* Background SVG diubah menjadi pola catur 2x2 */
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><rect fill='%230ea5e9' fill-opacity='0.030' width='50' height='50' x='0' y='0'></rect><rect fill='%230ea5e9' fill-opacity='0.030' width='50' height='50' x='50' y='50'></rect></svg>");
        
        /* Mengatur ukuran agar pola terlihat lebih kecil dan renggang seperti catur */
        background-size: 50px 50px; 
        
        padding: 2.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem; 
    }

    .visi-misi-item {
        /* Garis vertikal: Keduanya (Visi dan Misi) menggunakan warna ini */
        border-left: 5px solid var(--primary-500); 
        padding-left: 1.5rem;
        padding-bottom: 0.5rem; 
    }

    /* Definisikan warna teks kustom */
    .visi-misi-text {
        /* Menerapkan warna rgb(3 105 161) */
        color: rgb(3 105 161 / var(--tw-text-opacity, 1)); 
    }

    /* Kustomisasi untuk judul section yang tampak seperti tombol Masuk/Dashboard */
    .section-title-button {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1.5rem;
        background-image: linear-gradient(to right, var(--primary-600), var(--secondary-500));
        color: white;
        font-size: 1rem;
        font-weight: 700;
        border-radius: 9999px; /* rounded-full */
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        /* Membuatnya tidak bisa diklik */
        pointer-events: none; 
        user-select: none;
        cursor: default;
    }
    .section-title-button .icon {
        margin-right: 0.5rem;
    }
    </style>
    
</head>
<body>

    <audio id="background-music" src="assets/music/musikdayak.mp3" loop></audio>

    <nav id="navbar" class="bg-white/70 backdrop-blur-md text-gray-800 shadow-lg sticky top-0 z-50 transition-transform duration-300 ease-in-out border-b border-gray-200">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between py-3">
                
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <img src="assets/img/logopky.gif" alt="Logo PKY" class="h-10 sm:h-12 w-auto">
                    <img src="assets/img/siklepon.png" alt="Logo Kelurahan" class="h-8 sm:h-10 w-auto">
                    <div class="hidden sm:block">
                        <h1 class="text-base sm:text-lg font-bold text-[rgb(7,89,133)]">
                            Sistem Informasi Kelurahan Lewat Pelayanan Online
                        </h1>
                        <p class="text-xs sm:text-sm text-[rgb(7,89,133)] font-medium">
                            Kelurahan Kalampangan
                        </p>
                    </div>
                </div>

                <div class="hidden lg:flex items-center">
                    <a href="index.php" class="font-bold text-[rgb(7,89,133)] hover:text-blue-600 text-base px-3 py-2">Beranda</a>
                    
                    <div class="relative group">
                        <span class="font-bold text-[rgb(7,89,133)] hover:text-blue-600 text-base flex items-center cursor-default px-3 py-2">
                            Berita
                            <i class="fas fa-chevron-down ml-1 text-xs transition-transform duration-200 group-hover:rotate-180"></i>
                        </span>
                        <div class="absolute hidden group-hover:block bg-white shadow-lg rounded-lg pt-2 w-48 z-50 border border-gray-100 overflow-hidden text-[rgb(7,89,133)]">
                            <a href="pengumuman.php" class="block px-4 py-2 text-sm hover:bg-gray-100">Berita Pengumuman</a>
                            <a href="kegiatan.php" class="block px-4 py-2 text-sm hover:bg-gray-100">Berita Kegiatan</a>
                        </div>
                    </div>
                    
                    <a href="statistik.php" class="font-bold text-[rgb(7,89,133)] hover:text-blue-600 text-base px-3 py-2">Statistik</a>
                    
                    <a href="tentang.php" class="font-bold text-[rgb(7,89,133)] hover:text-blue-600 text-base px-3 py-2">Tentang</a>
                    <a href="Pengajuan.php" class="font-bold text-[rgb(7,89,133)] hover:text-blue-600 text-base px-3 py-2">Pengajuan</a>

                    <div class="ml-6">
                        <?php if (Session::isLoggedIn()): ?>
                            <?php if (Session::isAdmin()): ?>
                                <a href="admin/dashboard.php" class="inline-flex items-center py-2 px-5 bg-gradient-to-r from-blue-600 to-violet-500 hover:from-blue-700 hover:to-violet-600 text-white text-sm font-bold rounded-full transition duration-200 shadow hover:shadow-md">
                                    <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                                </a>
                            <?php else: ?>
                                <a href="user/dashboard.php" class="inline-flex items-center py-2 px-5 bg-gradient-to-r from-blue-600 to-violet-500 hover:from-blue-700 hover:to-violet-600 text-white text-sm font-bold rounded-full transition duration-200 shadow hover:shadow-md">
                                    <i class="fas fa-user mr-1"></i> Dashboard
                                </a>
                            <?php endif; ?>
                            <?php else: ?>
                            <a href="login.php" class="inline-flex items-center py-2 px-5 bg-gradient-to-r from-blue-600 to-violet-500 hover:from-blue-700 hover:to-violet-600 text-white text-sm font-bold rounded-full transition duration-200 shadow hover:shadow-md">
                                <i class="fas fa-sign-in-alt mr-1"></i> Masuk
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="lg:hidden flex items-center">
                    <button id="mobile-menu-button" class="text-[rgb(7,89,133)] focus:outline-none">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>

            </div>
        </div>

        <div id="mobile-menu" class="lg:hidden bg-white/70 backdrop-blur-md max-h-0 overflow-hidden transition-all duration-300 ease-in-out border-t border-white/[.18]">
            <div class="p-4">
                <a href="index.php" class="block py-2 text-sm hover:bg-gray-100/50 rounded font-bold text-[rgb(7,89,133)]">Beranda</a>
                
                <div>
                    <button id="mobile-berita-button" class="w-full flex justify-between items-center py-2 text-sm font-bold text-left text-[rgb(7,89,133)] hover:bg-gray-100/50 rounded">
                        <span>Berita</span>
                        <i id="mobile-berita-icon" class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                    </button>
                    <div id="mobile-berita-submenu" class="pl-4 max-h-0 overflow-hidden transition-all duration-300 bg-white rounded-lg">
                        <a href="pengumuman.php" class="block py-2 pl-2 pr-2 text-sm hover:bg-gray-100/50 rounded text-[rgb(7,89,133)]">Berita Pengumuman</a>
                        <a href="kegiatan.php" class="block py-2 pl-2 pr-2 text-sm hover:bg-gray-100/50 rounded text-[rgb(7,89,133)]">Berita Kegiatan</a>
                    </div>
                </div>

                <a href="statistik.php" class="block py-2 text-sm hover:bg-gray-100/50 rounded font-bold text-[rgb(7,89,133)]">Statistik</a>
                
                <a href="tentang.php" class="block py-2 text-sm hover:bg-gray-100/50 rounded font-bold text-[rgb(7,89,133)]">Tentang</a>
                <a href="Pengajuan.php" class="block py-2 text-sm hover:bg-gray-100/50 rounded font-bold text-[rgb(7,89,133)]">Pengajuan</a>
                <hr class="border-white/20 my-2">
                
                <div class="text-center pt-2">
                    <?php if (Session::isLoggedIn()): ?>
                        <?php if (Session::isAdmin()): ?>
                            <a href="admin/dashboard.php" class="inline-flex items-center py-2 px-6 bg-gradient-to-r from-blue-600 to-violet-500 hover:from-blue-700 hover:to-violet-600 text-white text-sm font-bold rounded-full transition duration-200 shadow hover:shadow-md">
                                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard Admin
                            </a>
                        <?php else: ?>
                            <a href="user/dashboard.php" class="inline-flex items-center py-2 px-6 bg-gradient-to-r from-blue-600 to-violet-500 hover:from-blue-700 hover:to-violet-600 text-white text-sm font-bold rounded-full transition duration-200 shadow hover:shadow-md">
                                <i class="fas fa-user mr-1"></i> Dashboard
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="inline-flex items-center py-2 px-6 bg-gradient-to-r from-blue-600 to-violet-500 hover:from-blue-700 hover:to-violet-600 text-white text-sm font-bold rounded-full transition duration-200 shadow hover:shadow-md">
                            <i class="fas fa-sign-in-alt mr-1"></i> Masuk
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="relative py-24 overflow-hidden bg-gradient-to-bl from-sky-50 to-sky-100"> 
        <div id="particles-js-container"></div>
        <div class="container mx-auto px-4 relative z-10">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-12 md:mb-0 animate-fade-in text-center md:text-left">
                    <span class="text-[var(--primary-600)] font-semibold tracking-wider">Kota Palangka Raya</span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mt-4 mb-6 leading-tight">
                        Kelurahan <span class="gradient-text">Kalampangan</span>
                    </h1>
                    <p class="text-lg md:text-xl text-[var(--primary-800)] mb-8 max-w-lg">
                        Membangun masyarakat yang sejahtera melalui pelayanan prima dan pemerintahan yang transparan.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start">
    <a href="tentang.php" class="bg-gradient-to-r from-[var(--primary-500)] to-[var(--secondary-500)] text-white hover:opacity-90 px-6 py-3 rounded-full font-medium transition transform hover:scale-105 shadow-lg whitespace-nowrap">
        Tentang Kami <i class="fas fa-arrow-right ml-2"></i>
    </a>
    
    <a href="<?= Session::isLoggedIn() ? 'user/dashboard.php' : 'login.php' ?>" class="bg-white text-[var(--primary-600)] border-2 border-[var(--primary-200)] hover:border-[var(--primary-300)] px-6 py-3 rounded-full font-medium transition transform hover:scale-105 shadow-sm whitespace-nowrap">
        <?= Session::isLoggedIn() ? 'Mulai Layanan' : 'Ajukan Permohonan' ?> <i class="fas fa-file-alt ml-2"></i>
    </a>
</div>
                </div>
                
                <div class="md:w-1/2 flex justify-center">
                    <div class="relative">
                        <div class="absolute -top-6 -left-6 w-32 h-32 bg-[var(--secondary-100)] rounded-full opacity-70 animate-float"></div>
                        <div class="absolute -bottom-6 -right-6 w-32 h-32 bg-[var(--primary-100)] rounded-full opacity-70 animate-float" style="animation-delay: 2s;"></div>
                        <div class="relative glass rounded-2xl overflow-hidden shadow-2xl">
                            <img src="assets/img/walkot.png" alt="Kantor Kelurahan Kalampangan" class="w-full h-auto max-h-96 object-cover img-hover">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="py-16 bg-gradient-to-r from-primary-50 to-white">
        <div class="container mx-auto px-4">
            <div class="visi-misi-card max-w-5xl mx-auto shadow-xl">
                <div class="visi-misi-header">
                    <h2 class="text-3xl md:text-4xl font-bold">Visi & Misi</h2>
                    <p class="text-white/90 mt-1 font-bold">Pemerintahan Kota Palangka Raya</p>
                </div>
                <div class="visi-misi-content">
                    
                    <div class="visi-misi-item">
                        <h3 class="text-2xl font-bold mb-3 visi-misi-text">Visi</h3> 
                        <p class="visi-misi-text"> 
                            "Terwujudnya Palangka Raya semakin Maju, Modern, Berkelanjutan dan Lebih KEREN"
                        </p>
                    </div>
                    
                    <div class="visi-misi-item">
                        <h3 class="text-2xl font-bold mb-3 visi-misi-text">Misi</h3> 
                        <p class="visi-misi-text"> 
                            Mewujudkan Tata Kelola Pemerintahan yang Baik Berbasis Keangka Regulasi, Pelayanan Publik Bermutu, Kreatif, Inovatif, Responsif, Reformatif, Kolaboratif Berbasis Teknologi Sistem Informasi, Komunikasi dan Digitalisasi untuk Memperkuat Perwujudan Smart Governance
                        </p>
                    </div>
                    
                    <div class="flex justify-center mt-6">
                        <div class="w-24 rounded-full" 
                            style="
                                background-image: linear-gradient(to right, #0ea5e9, #8b5cf6); 
                                height: 5px; 
                            ">
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white"> 
    <div class="container mx-auto px-4">
        
        <div class="pb-16 pt-8"> 
            <div class="flex flex-col lg:flex-row items-center justify-between gap-12 p-0">
                
                <div class="lg:w-5/12 p-0 lg:pr-10 animate-fade-in flex flex-col items-center lg:items-start mx-auto lg:mx-0" style="animation-delay: 0.1s;">
                    
                    <div class="mb-4"> 
                        <div class="flex justify-center lg:justify-start">
                             <div class="section-title-button inline-flex mb-4"> 
                                 <i class="fas fa-tools icon"></i> Layanan
                             </div>
                        </div>
                        
                        <h2 class="text-4xl font-bold text-[var(--primary-800)] mt-0 text-center lg:text-left">
                            Fitur <span class="gradient-text">Unggulan</span> Kami
                        </h2>
                    </div>
                    
                    <p class="text-lg mb-6 text-[rgb(3,105,161)] text-center lg:text-left">
                        Nikmati berbagai kemudahan dalam mengakses layanan publik Kelurahan Kalampangan secara digital.
                    </p>
                </div>
                
                <div class="lg:w-6/12 mt-10 lg:mt-0 flex justify-center lg:justify-center animate-fade-in" style="animation-delay: 0.3s;">
    
                   <div class="relative overflow-hidden rounded-2xl shadow-2xl cursor-pointer w-full max-w-lg max-h-[24rem] transition-transform duration-300 ease-in-out">
    
                        <img src="assets/img/gedung.jpeg" 
                            alt="Ilustrasi Layanan Digital" 
                            class="w-full h-full object-cover transition-transform duration-300 ease-in-out transform hover:scale-105"
                            onerror="this.onerror=null;this.src='https://via.placeholder.com/600x450?text=Gambar+Fitur+Unggulan+SI-KLEPON';"
                        >
                   </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <div class="bg-white p-6 sm:p-8 rounded-lg shadow-lg transition duration-300 ease-in-out text-center hover:-translate-y-1 hover:shadow-2xl flex flex-col h-full">
                <div class="bg-blue-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-alt text-4xl text-blue-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3">Pengajuan Surat Online</h3>
                <p class="text-gray-600 mb-4 flex-grow">Ajukan berbagai jenis surat keterangan secara online tanpa harus datang ke kantor.</p>
                <a href="Pengajuan.php" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-800 transition duration-150 mt-auto justify-center">
                    Ajukan Sekarang <i class="fas fa-arrow-right ml-2 text-lg"></i>
                </a>
            </div>
            
            <div class="bg-white p-6 sm:p-8 rounded-lg shadow-lg transition duration-300 ease-in-out text-center hover:-translate-y-1 hover:shadow-2xl flex flex-col h-full">
                <div class="bg-green-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-search text-4xl text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3">Tracking Status</h3>
                <p class="text-gray-600 mb-4 flex-grow">Pantau status pengajuan Anda secara real-time kapan saja dan dimana saja.</p>
                <a href="login.php" class="inline-flex items-center text-sm font-semibold text-green-600 hover:text-green-800 transition duration-150 mt-auto justify-center">
                    Lihat Status <i class="fas fa-arrow-right ml-2 text-lg"></i>
                </a>
            </div>
            
            <div class="bg-white p-6 sm:p-8 rounded-lg shadow-lg transition duration-300 ease-in-out text-center hover:-translate-y-1 hover:shadow-2xl flex flex-col h-full">
                <div class="bg-purple-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bullhorn text-4xl text-purple-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3">Informasi Terkini</h3>
                <p class="text-gray-600 mb-4 flex-grow">Lihat berita dan pengumuman terbaru dari kelurahan.</p>
                <a href="pengumuman.php" class="inline-flex items-center text-sm font-semibold text-purple-600 hover:text-purple-800 transition duration-150 mt-auto justify-center">
                    Lihat Informasi <i class="fas fa-arrow-right ml-2 text-lg"></i>
                </a>
            </div>

            <div class="bg-white p-6 sm:p-8 rounded-lg shadow-lg transition duration-300 ease-in-out text-center hover:-translate-y-1 hover:shadow-2xl flex flex-col h-full">
                <div class="bg-orange-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-comments text-4xl text-orange-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-3">Kirim Pertanyaan</h3>
                <p class="text-gray-600 mb-4 flex-grow">Kontak kami jika ada keluhan atau pertanyaan.</p>                <a href="#kontak" class="inline-flex items-center text-sm font-semibold text-orange-600 hover:text-orange-800 transition duration-150 mt-auto justify-center">
                    Kontak Pertanyaan <i class="fas fa-arrow-right ml-2 text-lg"></i>
                </a>
            </div>

        </div>
    </div>
</section>

<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <div class="section-title-button mx-auto">
                <i class="fas fa-bullhorn icon"></i> Pengumuman
            </div>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mt-4">
                Pengumuman Kelurahan
            </h2>
            <p class="text-lg mt-2 max-w-3xl mx-auto" style="color: rgb(3, 105, 161);">Informasi dan pengumuman resmi dari Kelurahan Kalampangan.</p>
        </div>
        
        <?php if (!empty($pengumuman)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($pengumuman as $item): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition animate-fade-in hover:-translate-y-1 hover:shadow-2xl flex flex-col h-full">
                    <div class="w-full h-48 overflow-hidden relative">
                        <?php if ($item['foto']): ?>
                        <img src="uploads/<?= htmlspecialchars($item['foto']) ?>"
                              alt="<?= htmlspecialchars($item['judul']) ?>"
                              class="w-full h-full object-cover transition-transform duration-300 ease-in-out transform hover:scale-105"
                              onerror="this.onerror=null;this.src='https://via.placeholder.com/600x400?text=Gambar+Pengumuman+Tidak+Ditemukan';">
                        <?php else: ?>
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center text-gray-500 font-semibold">
                            Tidak Ada Gambar
                        </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 left-0 text-white p-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.8);">
                            <i class="fas fa-calendar-alt text-xs mr-1"></i>
                            <span class="text-sm font-medium">
                                <?= function_exists('formatTanggal') ? formatTanggal($item['created_at']) : $item['created_at'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-6 flex flex-col flex-grow">
                        <h3 class="text-xl font-bold mb-3 text-gray-800 line-clamp-3"><?= htmlspecialchars(html_entity_decode($item['judul'])) ?></h3>
                        <p class="text-[rgb(3,105,161)] mb-4 flex-grow break-words line-clamp-3"><?= strip_tags($item['deskripsi']) ?></p>
                        <a href="pengumuman.php?id=<?= $item['pengumuman_id'] ?>" class="mt-auto text-blue-600 hover:text-blue-800 font-semibold self-start">
                            Baca Selengkapnya <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="max-w-4xl mx-auto bg-white p-8 rounded-2xl shadow-lg border border-gray-200 text-center">
                <div class="text-center text-gray-500">
                    <i class="fas fa-bullhorn text-5xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700">Belum Ada Pengumuman</h3>
                    <p class="mt-2">Saat ini belum ada pengumuman terbaru yang dipublikasikan.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-10">
            <a href="pengumuman.php" class="inline-flex items-center py-3 px-8 bg-gradient-to-r from-[var(--primary-600)] to-[var(--secondary-500)] text-white text-base font-bold rounded-full transition duration-300 shadow-lg hover:opacity-90 transform hover:scale-105">
                Lihat Semua Pengumuman <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</section>

    <section class="py-16 bg-white"> 
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <div class="section-title-button mx-auto">
                    <i class="fas fa-running icon"></i> Kegiatan
                </div>
                
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mt-4">
                    Kegiatan Kelurahan
                </h2>
                <p class="text-lg mt-2 max-w-3xl mx-auto" style="color: rgb(3, 105, 161);">Dokumentasi berbagai kegiatan dan program yang telah dilaksanakan di Kelurahan Kalampangan.</p>
            </div>
            
            <?php if (!empty($kegiatan)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($kegiatan as $item): ?>
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition animate-fade-in hover:-translate-y-1 hover:shadow-2xl flex flex-col h-full">
                        <div class="w-full h-48 overflow-hidden relative">
                            <?php if ($item['foto']): ?>
                            <img src="uploads/<?= htmlspecialchars($item['foto']) ?>"
                                  alt="<?= htmlspecialchars($item['judul']) ?>"
                                  class="w-full h-full object-cover transition-transform duration-300 ease-in-out transform hover:scale-105"
                                  onerror="this.onerror=null;this.src='https://via.placeholder.com/600x400?text=Gambar+Kegiatan+Tidak+Ditemukan';">
                            <?php else: ?>
                            <div class="w-full h-48 bg-gray-200 flex items-center justify-center text-gray-500 font-semibold">
                                Tidak Ada Gambar
                            </div>
                            <?php endif; ?>
                            <div class="absolute bottom-0 left-0 text-white p-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.8);">
                                <i class="fas fa-calendar-alt text-xs mr-1"></i>
                                <span class="text-sm font-medium">
                                    <?= function_exists('formatTanggal') ? formatTanggal($item['created_at']) : $item['created_at'] ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-6 flex flex-col flex-grow">
                            <h3 class="text-xl font-bold mb-3 text-gray-800 line-clamp-3"><?= htmlspecialchars(html_entity_decode($item['judul'])) ?></h3>
                            <p class="text-[rgb(3,105,161)] mb-4 flex-grow break-words line-clamp-3"><?= strip_tags($item['deskripsi']) ?></p>
                            <a href="kegiatan.php?id=<?= $item['kegiatan_id'] ?>" class="mt-auto text-blue-600 hover:text-blue-800 font-semibold self-start">
                                Baca Selengkapnya <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="max-w-4xl mx-auto bg-white p-8 rounded-2xl shadow-lg border border-gray-200 text-center">
                    <div class="text-center text-gray-500">
                        <i class="fas fa-running text-5xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-700">Belum Ada Kegiatan</h3>
                        <p class="mt-2">Saat ini belum ada dokumentasi kegiatan terbaru yang dipublikasikan.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-10">
                <a href="kegiatan.php" class="inline-flex items-center py-3 px-8 bg-gradient-to-r from-[var(--primary-600)] to-[var(--secondary-500)] text-white text-base font-bold rounded-full transition duration-300 shadow-lg hover:opacity-90 transform hover:scale-105">
                    Lihat Semua Kegiatan <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12" id="struktur">
                <div class="section-title-button mx-auto">
                    <i class="fas fa-sitemap icon"></i> Struktur Organisasi
                </div>
                <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mt-4">
                    Tim Pemerintahan
                </h2>
                <p class="text-lg mt-2 max-w-3xl mx-auto" style="color: rgb(3, 105, 161);">
                    Kenali tim yang bertanggung jawab dalam memberikan pelayanan terbaik untuk masyarakat Kalampangan.
                </p>
            </div>
            
            <?php if ($pimpinan): ?>
            <div class="max-w-5xl mx-auto text-center">
                <!-- Pimpinan -->
                <div class="flex justify-center mb-12">
                    <div class="relative w-72 h-96 rounded-lg shadow-lg overflow-hidden group">
                        <?php if ($pimpinan['foto']): ?>
                            <img src="uploads/<?= htmlspecialchars($pimpinan['foto']) ?>" alt="<?= htmlspecialchars($pimpinan['nama']) ?>" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                <i class="fas fa-user-tie text-6xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 left-0 p-4 text-white w-full" style="text-align: left !important;">
                            <h4 class="font-bold truncate mb-1" style="font-size: 1rem; line-height: 1.5rem; color: white !important;"><?= htmlspecialchars($pimpinan['nama']) ?></h4>
                            <p class="text-sm line-clamp-2" style="line-height: 1.25rem; color: white !important;"><?= htmlspecialchars($pimpinan['posisi']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Garis penghubung -->
                <?php if (!empty($anggota)): ?>
                <div class="h-12 w-1 bg-gray-300 mx-auto mb-12"></div>
                <?php endif; ?>

                <!-- Anggota -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6 justify-center">
                    <?php foreach ($anggota as $item): ?>
                    <div class="relative w-full h-80 rounded-lg shadow-md overflow-hidden group">
                        <?php if ($item['foto']): ?>
                            <img src="uploads/<?= htmlspecialchars($item['foto']) ?>" alt="<?= htmlspecialchars($item['nama']) ?>" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                <i class="fas fa-user text-5xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 left-0 p-3 text-white w-full" style="text-align: left !important;">
                            <h5 class="font-bold truncate mb-1" style="font-size: 0.875rem; line-height: 1.25rem; color: white !important;"><?= htmlspecialchars($item['nama']) ?></h5>
                            <p class="text-xs line-clamp-2" style="line-height: 1rem; color: white !important;"><?= htmlspecialchars($item['posisi']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="max-w-4xl mx-auto bg-white p-8 rounded-2xl shadow-lg border border-gray-200 text-center">
                <div class="text-center text-gray-500">
                    <i class="fas fa-sitemap text-5xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700">Struktur Organisasi Belum Tersedia</h3>
                    <p class="mt-2">Data struktur organisasi sedang dalam proses pembaruan.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-16 bg-white" id="lokasi">
        <div class="container mx-auto px-4">
            <div class="max-w-[85rem] mx-auto rounded-xl shadow-2xl overflow-hidden bg-white p-6 border-4 border-white" style="text-align: left;">
                <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Lokasi Kelurahan</h2>
                <p class="text-base mt-2" style="color: rgb(3, 105, 161);">Jl. Mahir Mahar, Kec. Sabangau. Kota Palangka Raya</p>
                <div class="mt-4 text-left">
                    <a href="https://www.google.com/maps/search/?api=1&query=Kantor+Kelurahan+Kalampangan" target="_blank" rel="noopener noreferrer" class="inline-flex items-center transition-colors hover:opacity-80" style="color: rgb(3, 105, 161);">
                        <i class="fas fa-map-marked-alt mr-2"></i> Buka di Google Maps
                    </a>
                </div>
            </div>
            <div class="rounded-lg overflow-hidden border border-gray-200">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d14623.136306367101!2d114.00966299099473!3d-2.2796201466186186!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2de353089e89e807%3A0x150e730e4c7482ee!2sKantor%20Kelurahan%20Kalampangan!5e0!3m2!1sid!2sid!4v1762786524168!5m2!1sid!2sid" 
                        width="100%" 
                        height="500" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- Bagian Hubungi Kami -->
    <section class="py-16 bg-white" id="kontak">
        <div class="container mx-auto px-4">
            <div class="max-w-[85rem] mx-auto grid grid-cols-1 lg:grid-cols-2 items-start rounded-xl shadow-2xl overflow-hidden">
                <!-- Kolom Kiri: Informasi Kontak -->
                <div class="bg-gradient-to-br from-blue-500 to-violet-500 text-white p-8 h-full">
                    <h2 class="text-3xl font-bold mb-6">Hubungi Kami</h2>
                    <p class="mb-8">
                        Kami siap membantu dan menjawab pertanyaan Anda seputar layanan Kelurahan Kalampangan.
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="bg-white/20 p-3 rounded-full mr-4">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Alamat</h4>
                                <p>
Jl. Mahir Mahar, Kec. Sabangau, Kota Palangka Raya</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="bg-white/20 p-3 rounded-full mr-4">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Telepon</h4>
                                <p>08xx-xxxx-xxxx</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="bg-white/20 p-3 rounded-full mr-4">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Email</h4>
                                <p>Kelurahan Kalampangan</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Formulir Kirim Pesan -->
                <div class="bg-white p-8">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6">Kirim Pesan</h3>
                    <form id="whatsappForm">
                        <div class="mb-4">
                            <label for="name" class="block text-gray-700 mb-2">Nama Lengkap</label>
                            <input type="text" id="name" name="name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="message" class="block text-gray-700 mb-2">Pesan</label>
                            <textarea id="message" name="message" rows="4" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <button type="button" class="send w-full bg-gradient-to-r from-blue-500 to-violet-500 text-white py-3 rounded-full font-medium hover:opacity-90 transition shadow-md">
                            Kirim Pesan <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                        <div id="text-info" class="mt-4"></div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-gray-800 text-white py-14">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <div>
                <div class="flex items-center space-x-3 mb-4">
                    <img src="assets/img/logopky.gif" alt="Logo PKY" class="h-10 w-auto">
                    <div>
                        <h3 class="text-xl font-bold text-white">Kelurahan Kalampangan</h3>
                    </div>
                </div>
                <p class="text-gray-300 text-base leading-relaxed">
                    Membangun masyarakat yang sejahtera melalui pelayanan prima dan pemerintahan yang transparan.
                </p>
            </div>
            
            <div>
                <h3 class="text-xl font-bold mb-4">Menu</h3>
                <ul class="space-y-2">
                    <li><a href="index.php" class="text-gray-300 hover:text-white transition-colors">Beranda</a></li>
                    <li><a href="pengumuman.php" class="text-gray-300 hover:text-white transition-colors">Berita</a></li>
                    <li><a href="statistik.php" class="text-gray-300 hover:text-white transition-colors">Statistik</a></li> 
                    <li><a href="tentang.php" class="text-gray-300 hover:text-white transition-colors">Tentang</a></li>
                    <li><a href="Pengajuan.php" class="text-gray-300 hover:text-white transition-colors">Pengajuan</a></li>
                </ul>
            </div>
            
            <div>
                <h3 class="text-xl font-bold mb-4">Kontak</h3>
                <p class="text-gray-300 flex items-start mb-2">
                    <i class="fas fa-map-marker-alt w-5 text-center mr-2 flex-shrink-0 pt-1"></i>
                    <span>Jl. Mahir Mahar, Kec. Sabangau, Kota Palangka Raya, Provinsi kalimantan Tengah</span>
                </p>
                <p class="text-gray-300 flex items-start mb-2">
                    <i class="fas fa-phone w-5 text-center mr-2 flex-shrink-0 pt-1"></i>
                    <span>08xx-xxxx-xxxx</span>
                </p>
                <p class="text-gray-300 flex items-start mb-2">
                    <i class="fas fa-envelope w-5 text-center mr-2 flex-shrink-0 pt-1"></i>
                    <span>Kelurahan Kalampangan</span>
                </p>
            </div>

            <div>
                <h3 class="text-xl font-bold mb-4">Jam Layanan</h3>
                <p class="text-gray-300 flex items-start mb-2">
                    <i class="fas fa-clock w-5 text-center mr-2 flex-shrink-0 pt-1"></i>
                    <span>Senin - Jumat: 08:00 - 16:00 WIB</span>
                </p>
                <p class="text-gray-300 flex items-start">
                    <i class="fas fa-globe w-5 text-center mr-2 flex-shrink-0 pt-1"></i>
                    <span>Layanan Online: 24/7</span>
                </p>
            </div>

        </div>
        <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
            <p>&copy; <?= date('Y') ?> Kelurahan Kalampangan. Hak Cipta Dilindungi Undang-Undang</p>
        </div>
    </div>
</footer>

    <button id="scrollToTopBtn" 
            class="fixed bottom-6 right-6 z-50 p-3 bg-gradient-to-r from-blue-600 to-violet-500 text-white rounded-full shadow-lg hover:from-blue-700 hover:to-violet-600 focus:outline-none transition-all duration-300 invisible opacity-0">
        <i class="fas fa-arrow-up text-lg"></i>
    </button>
    
        <script>
        const navbar = document.getElementById('navbar');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuButton = document.getElementById('mobile-menu-button');
        const menuIcon = menuButton.querySelector('i');
        const scrollToTopBtn = document.getElementById('scrollToTopBtn');

        const mobileBeritaButton = document.getElementById('mobile-berita-button');
        const mobileBeritaSubmenu = document.getElementById('mobile-berita-submenu');
        const mobileBeritaIcon = document.getElementById('mobile-berita-icon');

        if (mobileBeritaButton) {
            mobileBeritaButton.addEventListener('click', () => {
                const isOpen = mobileBeritaSubmenu.classList.contains('max-h-0');
                // Menutup submenu lain jika ada
                document.querySelectorAll('[id$="-submenu"]').forEach(el => {
                    if (el.id !== 'mobile-berita-submenu') {
                        el.classList.add('max-h-0');
                        el.classList.remove('max-h-96');
                    }
                });
                document.querySelectorAll('[id$="-icon"]').forEach(el => {
                    if (el.id !== 'mobile-berita-icon') {
                        el.classList.remove('rotate-180');
                    }
                });

                if (isOpen) {
                    mobileBeritaSubmenu.classList.remove('max-h-0');
                    mobileBeritaSubmenu.classList.add('max-h-96');
                    mobileBeritaIcon.classList.add('rotate-180');
                } else {
                    mobileBeritaSubmenu.classList.remove('max-h-96');
                    mobileBeritaSubmenu.classList.add('max-h-0');
                    mobileBeritaIcon.classList.remove('rotate-180');
                }
            });
        }
        
        // Cek jika elemen statistik ada sebelum menambahkan event listener
        const mobileStatistikButton = document.getElementById('mobile-statistik-button');
        if (mobileStatistikButton) {
            const mobileStatistikSubmenu = document.getElementById('mobile-statistik-submenu');
            const mobileStatistikIcon = document.getElementById('mobile-statistik-icon');
            
            mobileStatistikButton.addEventListener('click', () => {
                const isOpen = mobileStatistikSubmenu.classList.contains('max-h-0');
                // Menutup submenu lain
                document.querySelectorAll('[id$="-submenu"]').forEach(el => {
                    if (el.id !== 'mobile-statistik-submenu') {
                        el.classList.add('max-h-0');
                        el.classList.remove('max-h-96');
                    }
                });
                document.querySelectorAll('[id$="-icon"]').forEach(el => {
                    if (el.id !== 'mobile-statistik-icon') {
                        el.classList.remove('rotate-180');
                    }
                });

                if (isOpen) {
                    mobileStatistikSubmenu.classList.remove('max-h-0');
                    mobileStatistikSubmenu.classList.add('max-h-96');
                    mobileStatistikIcon.classList.add('rotate-180');
                } else {
                    mobileStatistikSubmenu.classList.remove('max-h-96');
                    mobileStatistikSubmenu.classList.add('max-h-0');
                    mobileStatistikIcon.classList.remove('rotate-180');
                }
            });
        }


        const backgroundMusic = document.getElementById('background-music');
        let musicStarted = false; 

        function playMusicOnFirstClick() {
            if (!musicStarted) {
                backgroundMusic.play().then(() => {
                    musicStarted = true;
                    document.body.removeEventListener('click', playMusicOnFirstClick);
                }).catch(error => {
                    console.warn("Autoplay musik diblokir:", error);
                });
            }
        }
        document.body.addEventListener('click', playMusicOnFirstClick);
        
        menuButton.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mobileMenu.classList.contains('max-h-0')) {
                mobileMenu.classList.remove('max-h-0');
                mobileMenu.classList.add('max-h-96');
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times'); 
                navbar.classList.remove('-translate-y-full'); 
            } else {
                mobileMenu.classList.remove('max-h-96');
                mobileMenu.classList.add('max-h-0');
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });

        document.addEventListener('click', function(event) {
            if (!mobileMenu.classList.contains('max-h-0') && !navbar.contains(event.target)) {
                mobileMenu.classList.remove('max-h-96');
                mobileMenu.classList.add('max-h-0');
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });

        // --- Navbar & Scroll Logic (diadaptasi dari pengumuman.php) ---
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            // Navbar hide/show on scroll (HANYA UNTUK DESKTOP)
            if (window.innerWidth >= 1024) { // 1024px adalah breakpoint 'lg' Tailwind
                if (scrollTop > lastScrollTop && scrollTop > navbar.offsetHeight) {
                    // Scroll Down
                    navbar.classList.add('-translate-y-full');
                    if (mobileMenu && !mobileMenu.classList.contains('max-h-0')) {
                        mobileMenu.classList.add('max-h-0');
                        mobileMenu.classList.remove('max-h-96');
                        const menuIcon = menuButton.querySelector('i');
                        if(menuIcon) {
                            menuIcon.classList.add('fa-bars');
                            menuIcon.classList.remove('fa-times');
                        }
                    }
                } else {
                    // Scroll Up
                    navbar.classList.remove('-translate-y-full');
                }
            }
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;

            // Scroll to Top button visibility
            if (scrollTop > 300) { 
                scrollToTopBtn.classList.remove('invisible', 'opacity-0');
                scrollToTopBtn.classList.add('visible', 'opacity-100');
            } else {
                scrollToTopBtn.classList.remove('visible', 'opacity-100');
                scrollToTopBtn.classList.add('invisible', 'opacity-0');
            }
        }, false);
        // --- End Navbar & Scroll Logic ---

        scrollToTopBtn.addEventListener('click', (e) => {
            e.preventDefault(); 
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // --- WhatsApp Form Logic ---
        const sendButton = document.querySelector('.send');
        if (sendButton) {
            sendButton.addEventListener('click', function() {
                const nameInput = document.getElementById('name');
                const emailInput = document.getElementById('email');
                const messageInput = document.getElementById('message');
                
                if (nameInput.value.trim() === "" || messageInput.value.trim() === "") {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'Nama dan Pesan harus diisi!',
                        confirmButtonColor: '#0284c7'
                    });
                    return;
                }

                // Fungsi yang akan dijalankan ketika pengguna kembali ke tab ini
                const onTabFocus = () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Pesan anda telah terkirim melalui gmail.',
                        confirmButtonColor: '#0284c7'
                    });

                    // Kosongkan input form
                    nameInput.value = '';
                    emailInput.value = '';
                    messageInput.value = '';

                    // Hapus event listener agar tidak berjalan lagi
                    window.removeEventListener('focus', onTabFocus);
                };

                // Tambahkan event listener untuk mendeteksi saat tab kembali aktif
                window.addEventListener('focus', onTabFocus);

                const recipientEmail = "example@gmail.com"; // Ganti dengan alamat email tujuan
                const subject = `Pesan dari ${nameInput.value} melalui Website SI-KLEPON`;
                const body = `Nama: ${nameInput.value}\nEmail: ${emailInput.value}\n\nPesan:\n${messageInput.value}`;
                const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${recipientEmail}&su=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

                window.open(gmailUrl, '_blank');
            });
        }
    </script>

    <script src="assets/js/particles.min.js"></script>
    <script>
        particlesJS("particles-js-container", { 
            "particles": {
                "number": {
                    "value": 100,
                    "density": {
                        "enable": false,
                        "value_area": 500
                    }
                },
                "color": {
                    "value": "#ff0000" 
                },
                "shape": {
                    "type": "circle"
                }
                    ,
                "opacity": {
                    "value": 0.7,
                    "random": false,
                    "anim": {
                        "enable": false
                    }
                },
                "size": {
                    "value": 3,
                    "random": true,
                    "anim": {
                        "enable": true,
                        "speed": 1,
                        "size_min": 0.5,
                        "size_min": 0.5,
                        "sync": false
                    }
                },
                "line_linked": {
                    "enable": true,
                    "distance": 180,
                    "color": "#FF0000", 
                    "opacity": 0.15,
                    "width": 1
                },
                "move": {
                    "enable": true,
                    "speed": 0.5,
                    "direction": "none",
                    "random": false,
                    "straight": false,
                    "out_mode": "out",
                    "bounce": false
                }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": {
                        "enable": false
                    },
                    "onclick": {
                        "enable": false
                    },
                    "resize": true
                }
            },
            "retina_detect": true
        });
    </script>

</body>
</html>
</html>        