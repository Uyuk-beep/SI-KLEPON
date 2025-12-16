<?php
require_once 'config.php';
Session::start();

// Ambil ID kegiatan jika ada
$kegiatanId = $_GET['id'] ?? null;

if ($kegiatanId) {
    // Detail kegiatan
    $kegiatan = Database::query(
        "SELECT k.kegiatan_id, k.judul, k.deskripsi, k.foto, k.created_at
         FROM kegiatan k
         WHERE k.kegiatan_id = ?",
        [$kegiatanId]
    )->fetch();

    if (!$kegiatan) {
        // Jika tidak ditemukan, redirect ke halaman daftar kegiatan
        redirect('kegiatan.php');
    }
} else {
    // Daftar kegiatan
    $search = $_GET['search'] ?? '';

    $sql = "SELECT k.kegiatan_id, k.judul, k.deskripsi, k.foto, k.created_at
             FROM kegiatan k
             WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (k.judul LIKE ? OR k.deskripsi LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY k.created_at DESC";

    $daftar_kegiatan = Database::query($sql, $params)->fetchAll();
    
    // TIDAK ADA LOOP UNTUK MENAMBAH DATA DUMMY
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $kegiatanId && $kegiatan ? 'Detail Kegiatan' : 'Kegiatan' ?> - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/logopky.gif">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <?php if ($kegiatanId && $kegiatan): ?>
    <link href="https://cdn.jsdelivr.net/npm/@tailwindcss/typography@0.5.x/dist/typography.min.css" rel="stylesheet">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
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

    </style>

</head>
<body>
    
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

    <?php if ($kegiatanId && $kegiatan): ?>
        <!-- Tampilan Detail Kegiatan -->
        <div class="container mx-auto px-4 py-8 md:py-12">
            <nav class="flex justify-center mb-8">
                <div class="inline-flex items-center space-x-2 text-sm bg-white text-gray-500 px-6 py-3 rounded-full shadow-md border border-gray-200">
                    <a href="index.php" class="flex items-center hover:text-blue-600 transition-colors"><i class="fas fa-home mr-2"></i>Beranda</a>
                    <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                    <a href="kegiatan.php" class="hover:text-blue-600 transition-colors">Kegiatan</a>
                    <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                    <span class="font-bold text-gray-800">Detail Kegiatan</span>
                </div>
            </nav>

            <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
                 <div class="p-6 md:p-8">
                     <h1 class="text-2xl md:text-4xl font-bold text-gray-900 mb-4 break-words">
                         <?= htmlspecialchars(html_entity_decode($kegiatan['judul'])) ?>
                     </h1>

                     <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-gray-500 mb-6 pb-6 border-b">
                          <span class="inline-flex items-center bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
                               <i class="fas fa-user-edit mr-2"></i>
                               Admin
                          </span>
                          <span class="inline-flex items-center bg-green-100 text-green-800 text-sm font-medium px-3 py-1 rounded-full">
                               <i class="fas fa-calendar-alt mr-2"></i>
                               <?= function_exists('formatTanggal') ? formatTanggal($kegiatan['created_at']) : $kegiatan['created_at'] ?>
                          </span>
                     </div>

                     <?php if ($kegiatan['foto']): ?>
                     <img src="uploads/<?= htmlspecialchars($kegiatan['foto']) ?>"
                          alt="<?= htmlspecialchars(html_entity_decode($kegiatan['judul'])) ?>"
                          class="w-full h-auto max-h-[500px] object-cover rounded-lg shadow-md mb-6">
                     <?php endif; ?>

                     <article class="prose prose-base max-w-none text-gray-700 break-words">
                         <?= $kegiatan['deskripsi'] ?>
                     </article>

                     <div class="mt-8 pt-6 border-t">
                         <a href="kegiatan.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-base font-semibold">
                             <i class="fas fa-arrow-left mr-1"></i> Kembali
                         </a>
                     </div>
                 </div>
             </div>
        </div>

    <?php else: ?>
        <!-- Tampilan Daftar Kegiatan -->
        <div class="relative py-16 md:py-24 overflow-hidden">
             <div class="absolute inset-0">
                 <img src="assets/img/tulisanDepan.jpeg"
                      alt="Background Kegiatan"
                      class="w-full h-full object-cover">
                  <div class="absolute inset-0 bg-black bg-opacity-50"></div>
             </div>

             <div class="relative container mx-auto px-4 text-center text-white">
                 <h1 class="text-5xl font-bold mb-4 tracking-tight">
                      Kegiatan
                 </h1>
                 <p class="text-xl mb-6 leading-relaxed">
                      Informasi terbaru tentang kegiatan Kelurahan Kalampangan
                 </p>

                 <div class="flex justify-center">
                     <nav class="inline-flex items-center space-x-2 text-sm bg-white/20 backdrop-blur-sm px-6 py-3 rounded-full border border-white/30">
                          <a href="index.php" class="flex items-center text-white hover:text-gray-200 transition-colors">
                              <i class="fas fa-home mr-2"></i> Beranda
                          </a>
                          <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                          <span class="text-white font-semibold">Kegiatan</span>
                     </nav>
                 </div>
             </div>
        </div>

        <div class="container mx-auto px-4 py-8 md:py-12">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-6 border-b pb-4 gap-4">
                    <div class="flex items-center space-x-2">
                        <label for="tableEntries" class="text-sm font-medium text-gray-700">Tampilkan</label>
                        <select id="tableEntries" onchange="changeEntries()" class="border border-gray-300 rounded-lg shadow-sm text-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                            <option value="6">6</option>
                            <option value="9" selected>9</option>
                            <option value="12">12</option>
                            <option value="-1">Semua</option>
                        </select>
                        <span class="text-sm font-medium text-gray-700">data</span>
                    </div>
                    
                    <div class="relative w-full sm:w-auto">
                        <input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="Cari kegiatan..." class="w-full sm:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>

                <?php if (isset($daftar_kegiatan) && count($daftar_kegiatan) > 0): ?>
                <div id="tableBody" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 mt-6">
                 <?php foreach ($daftar_kegiatan as $item): ?>
                 <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition animate-fade-in hover:-translate-y-1 hover:shadow-2xl flex flex-col h-full">
                    <div class="w-full h-48 overflow-hidden relative">
                        <?php if ($item['foto']): ?>
                        <a href="kegiatan.php?id=<?= $item['kegiatan_id'] ?>">
                            <img src="uploads/<?= htmlspecialchars($item['foto']) ?>"
                                alt="<?= htmlspecialchars($item['judul']) ?>"
                                class="w-full h-full object-cover transition-transform duration-300 ease-in-out transform hover:scale-105"
                                onerror="this.onerror=null;this.src='https://via.placeholder.com/600x400?text=Gambar+Tidak+Ditemukan';">
                        </a>
                        <?php else: ?>
                        <a href="kegiatan.php?id=<?= $item['kegiatan_id'] ?>" class="w-full h-48 bg-gray-200 flex items-center justify-center text-gray-500 font-semibold">
                            <i class="fas fa-running text-4xl text-gray-400"></i>
                        </a>
                        <?php endif; ?>

                        <div class="absolute bottom-0 left-0 text-white p-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.8);">
                            <i class="fas fa-calendar-alt text-xs mr-1"></i>
                            <span class="text-sm font-medium">
                                <?= function_exists('formatTanggal') ? formatTanggal($item['created_at'], 'd M Y') : $item['created_at'] ?>
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
 
                <div class="flex flex-col sm:flex-row justify-between items-center pt-8 mt-8 border-t border-gray-200 gap-4 sm:gap-0">
                    <div id="showingInfo" class="text-sm text-gray-700"></div>
                <div class="flex space-x-1" id="paginationControls">
                    <button id="prevBtn" onclick="changePage(currentPage - 1)" disabled class="px-3 py-1 border border-gray-300 rounded-lg text-gray-500 bg-white hover:bg-gray-100 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="hidden lg:inline">Sebelumnya</span>
                        <span class="lg:hidden">&lt;</span>
                    </button>
                    <span id="pageButtons" class="flex space-x-1"></span>
                    <button id="nextBtn" onclick="changePage(currentPage + 1)" disabled class="px-3 py-1 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-100 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="hidden lg:inline">Berikutnya</span>
                        <span class="lg:hidden">&gt;</span>
                    </button>
                </div>
                </div>

                <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Tidak Ada Kegiatan</h3>
                    <p class="text-gray-500">
                        <?php if (!empty($search)): ?>
                            Tidak ada kegiatan yang cocok dengan kriteria pencarian Anda.
                        <?php else: ?>
                            Belum ada kegiatan yang dipublikasikan saat ini.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<footer class="bg-gray-800 text-white py-14">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8">
                
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
        // Script untuk navbar, dropdown, dan scroll to top
        const scrollToTopBtn = document.getElementById('scrollToTopBtn');
        const navbar = document.getElementById('navbar');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuButton = document.getElementById('mobile-menu-button');

        // --- Navbar & Scroll Logic ---
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
                        mobileMenu.classList.remove('max-h-[500px]');
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
            } else {
                scrollToTopBtn.classList.add('invisible', 'opacity-0');
            }
        }, false);
        // --- End Navbar & Scroll Logic ---

        if (menuButton) {
            const menuIcon = menuButton.querySelector('i');
            menuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                const isMenuOpen = mobileMenu.classList.contains('max-h-0');
                if (mobileMenu && menuIcon) {
                    if (isMenuOpen) {
                        mobileMenu.classList.remove('max-h-0');
                        mobileMenu.classList.add('max-h-[500px]'); // Sesuaikan tinggi jika perlu
                        menuIcon.classList.remove('fa-bars');
                        menuIcon.classList.add('fa-times');
                    } else {
                        mobileMenu.classList.remove('max-h-[500px]');
                        mobileMenu.classList.add('max-h-0');
                        menuIcon.classList.remove('fa-times');
                        menuIcon.classList.add('fa-bars');
                    }
                }
            });
        }

        function setupDropdown(buttonId, submenuId, iconId) {
            const button = document.getElementById(buttonId);
            const submenu = document.getElementById(submenuId);
            const icon = document.getElementById(iconId);

            if (button) {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = submenu.classList.contains('max-h-0');
                    
                    // Tutup submenu lain
                    document.querySelectorAll('[id$="-submenu"]').forEach(el => {
                        if (el.id !== submenuId) {
                            el.classList.add('max-h-0');
                            el.classList.remove('max-h-96');
                        }
                    });
                    document.querySelectorAll('[id$="-icon"]').forEach(el => {
                        if (el.id !== iconId) {
                            el.classList.remove('rotate-180');
                        }
                    });

                    if (isOpen) {
                        submenu.classList.remove('max-h-0');
                        submenu.classList.add('max-h-96');
                        if (icon) icon.classList.add('rotate-180');
                    } else {
                        submenu.classList.remove('max-h-96');
                        submenu.classList.add('max-h-0');
                        if (icon) icon.classList.remove('rotate-180');
                    }
                });
            }
        }

        setupDropdown('mobile-berita-button', 'mobile-berita-submenu', 'mobile-berita-icon');
        setupDropdown('mobile-statistik-button', 'mobile-statistik-submenu', 'mobile-statistik-icon');

        document.addEventListener('click', function(event) {
            if (mobileMenu && !mobileMenu.classList.contains('max-h-0') && !navbar.contains(event.target)) {
                mobileMenu.classList.add('max-h-0');
                mobileMenu.classList.remove('max-h-[500px]');
                const menuIcon = menuButton.querySelector('i');
                menuIcon.classList.add('fa-bars');
                menuIcon.classList.remove('fa-times');
            }
        });

        // Scroll to top button
        scrollToTopBtn.addEventListener('click', (e) => {
            e.preventDefault(); 
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // --- LOGIKA PAGINASI DAN FILTER ---
        const tableBody = document.getElementById('tableBody');
        const allItems = tableBody ? Array.from(tableBody.children) : [];
        let filteredItems = [];
        
        let currentPage = 1;
        let entriesPerPage = 9;

        function updatePaginationControls(totalFiltered) {
            const currentEntriesPerPage = (entriesPerPage === -1) ? totalFiltered : entriesPerPage;
            const totalPages = Math.ceil(totalFiltered / currentEntriesPerPage);
            const startEntry = (currentPage - 1) * currentEntriesPerPage + 1;
            const endEntry = Math.min(currentPage * currentEntriesPerPage, totalFiltered);

            const showingInfo = document.getElementById('showingInfo');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const paginationControls = document.getElementById('paginationControls');

            if (totalFiltered === 0) {
                showingInfo.textContent = 'Menampilkan 0 dari 0 data';
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;
                if (paginationControls) paginationControls.classList.add('hidden');
                return;
            }

            showingInfo.innerHTML = `Menampilkan ${startEntry} - ${endEntry} dari ${totalFiltered} data`;
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage === totalPages;
            
            // --- Logic to generate page number buttons ---
            const pageButtonsContainer = document.getElementById('pageButtons');
            if (pageButtonsContainer) {
                pageButtonsContainer.innerHTML = '';
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);

                if (currentPage <= 3) {
                    endPage = Math.min(5, totalPages);
                }
                if (currentPage > totalPages - 3) {
                    startPage = Math.max(1, totalPages - 4);
                }

                for (let i = startPage; i <= endPage; i++) {
                    const pageButton = document.createElement('button');
                    pageButton.textContent = i;
                    pageButton.onclick = () => changePage(i);
                    pageButton.className = `px-3 py-1 border rounded-lg text-sm transition-colors ${
                        i === currentPage 
                        ? 'border-blue-600 bg-blue-600 text-white' 
                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100'
                    }`;
                    pageButtonsContainer.appendChild(pageButton);
                }
            }
            // --- End logic for page number buttons ---

            if (totalPages <= 1 || entriesPerPage === -1) {
                 paginationControls.classList.add('hidden');
            } else {
                 paginationControls.classList.remove('hidden');
            }
        }

        function applyFilters() {
            const searchText = document.getElementById('tableSearch').value.toLowerCase();
            
            filteredItems = allItems.filter(item => {
                const itemText = item.textContent.toLowerCase();
                return itemText.includes(searchText);
            });
            
            const currentEntriesPerPage = (entriesPerPage === -1) ? filteredItems.length : entriesPerPage;
            
            const maxPage = Math.max(1, Math.ceil(filteredItems.length / currentEntriesPerPage));
            if (currentPage > maxPage) {
                currentPage = 1;
            }

            const start = (currentPage - 1) * currentEntriesPerPage;
            const end = start + currentEntriesPerPage;
            
            tableBody.innerHTML = ''; // Kosongkan container
            
            if (filteredItems.length === 0) {
                tableBody.innerHTML = `<div class="sm:col-span-2 lg:col-span-3 text-center py-12 text-gray-500">
                    <i class="fas fa-search text-5xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Tidak Ada Hasil</h3>
                    <p>Tidak ada kegiatan yang cocok dengan pencarian Anda.</p>
                </div>`;
            } else {
                filteredItems.slice(start, end).forEach(item => {
                    tableBody.appendChild(item);
                });
            }

            // Panggil updatePaginationControls setelah konten di-render
            updatePaginationControls(filteredItems.length);
        }

        window.changeEntries = function() {
            const selectElement = document.getElementById('tableEntries');
            entriesPerPage = parseInt(selectElement.value);
            currentPage = 1;
            applyFilters();
        };

        window.filterTable = function() {
            currentPage = 1;
            applyFilters();
        };

        window.changePage = function(newPage) {
            const currentEntriesPerPage = (entriesPerPage === -1) ? filteredItems.length : entriesPerPage;
            const totalPages = Math.ceil(filteredItems.length / currentEntriesPerPage);
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                applyFilters();
            }
        };

        // Inisialisasi
        if (tableBody) {
            filteredItems = [...allItems];
            applyFilters();
        }
    </script>
</body>
</html>
