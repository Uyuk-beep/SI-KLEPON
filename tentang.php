<?php
require_once 'config.php';
Session::start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - <?= SITE_NAME ?></title>
    
    <link rel="icon" type="image/x-icon" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/logopky.gif">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/logopky.gif">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
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
<body class="bg-gray-50">

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
                        <div class="absolute hidden group-hover:block bg-white shadow-lg rounded-lg pt-2 w-48 z-50 border border-gray-100 overflow-hidden">
                            <a href="pengumuman.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Berita Pengumuman</a>
                            <a href="kegiatan.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Berita Kegiatan</a>
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
    <div class="relative py-16 md:py-24 overflow-hidden">
         <div class="absolute inset-0">
             <img src="assets/img/tulisanDepan.jpeg" alt="Background Tentang" class="w-full h-full object-cover">
              <div class="absolute inset-0 bg-black bg-opacity-50"></div>
         </div>

         <div class="relative container mx-auto px-4 text-center text-white">
             <h1 class="text-5xl font-bold mb-4 tracking-tight">
                  Tentang Kami
             </h1>
             <p class="text-xl mb-6 leading-relaxed">
                  Mengenal lebih jauh tentang Kelurahan Kalampangan
             </p>
             <div class="flex justify-center">
                 <nav class="inline-flex items-center space-x-2 text-sm bg-white/20 backdrop-blur-sm px-6 py-3 rounded-full border border-white/30">
                      <a href="index.php" class="flex items-center text-white hover:text-gray-200 transition-colors">
                          <i class="fas fa-home mr-2"></i>
                          Beranda
                      </a>
                      <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                      <span class="text-white font-semibold">Tentang</span>
                 </nav>
             </div>
         </div>
    </div>

    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
           <div class="flex flex-col md:flex-row gap-8 max-w-7xl mx-auto">
                <div class="md:w-5/12">
                    <div class="about_img overflow-hidden rounded-xl shadow-xl">
                        <img src="assets/img/gedung.jpeg" class="w-full h-auto object-cover" alt="Logo Kelurahan Kalampangan">
                    </div>
                </div>
                
                <div class="md:w-7/12">
                    <h2 class="text-3xl font-bold text-gray-800 mb-6">Kelurahan <span class="text-blue-600">Kalampangan</span></h2>
                    
                    <div class="space-y-4" style="color: rgb(3, 105, 161);">
                        <p class="text-justify leading-relaxed">
                            Kelurahan merupakan wilayah kerja lurah sebagai perangkat daerah Kabupaten di bawah Kecamatan. 
                            Dalam penyelenggaraan Pemerintah Daerah, kepala daerah dibantu oleh perangkat daerah yang 
                            terdiri dari unsur staf yang membantu penyusunan kebijakan dan koordinasi, diwadahi dalam 
                            sekretariat unsur pengawas yang di wadah dalam bentuk Inspektorat, unsur perencanaan yang 
                            diwadahi dalam bentuk badan, unsur pendukung tugas kepala daerah dalam penyusunan dan 
                            pelaksanaan kebijakan daerah yang bersifat spesifik, diwadahi dalam lembaga teknis daerah, 
                            serta unsur pelaksana urusan daerah yang diwadahi dalam dinas daerah, dan unsur penyelenggaraan 
                            pemerintahan di wilayah kecamatan dan kelurahan yang merupakan wilayah kerja camat dan lurah.
                        </p>
                        
                        <p class="text-justify leading-relaxed">
                            Dasar utama penyusunan kecamatan dan kelurahan dalam bentuk suatu organisasi merupakan 
                            pelaksanaan tugas kewenangan pemerintahan yang dilimpahkan oleh bupati kepada camat untuk 
                            menangani sebagian urusan pemerintahan kabupaten diwilayah kecamatan dan kelurahan. Selain 
                            pelaksanaan pelimpahan tugas dari bupati, camat juga melaksanakan tugas umum pemerintahan 
                            yang meliputi mengkoordinasikan kegiatan pemberdayaan masyarakat, mengkoordinasikan upaya 
                            penyelenggaraan ketentraman dan ketertiban umum, mengkoordinasikan penerapan dan penegakan 
                            peraturan perundang-undangan, mengkoordinasikan pemeliharaan sarana dan prasarana serta 
                            fasilitas pelayanan umum, mengkoordinasikan penyelenggaraan kegiatan pemerintahan di tingkat 
                            kecamatan dan membina penyelenggaraan pemerintahan di tingkat kelurahan. Camat dalam 
                            melaksanakan tugasnya bertanggung jawab kepada bupati melalui sekretaris daerah, sedangkan 
                            lurah dalam melaksanakan tugasnya bertanggung jawab kepada bupati melalui camat.
                        </p>
                        
                        <p class="text-justify leading-relaxed">
                            Pemerintahan di tingkat kelurahan perlu untuk selalu memikirkan bagaimana kondisi kelurahannya 
                            dimasa yang akan datang, sehingga kelurahan tersebut dapat berkembang dan bertambah maju 
                            sesuai dengan perkembangan zaman.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
    
    <button id="scrollToTopBtn" 
            class="fixed bottom-6 right-6 z-50 p-3 bg-gradient-to-r from-blue-600 to-violet-500 text-white rounded-full shadow-lg hover:from-blue-700 hover:to-violet-600 focus:outline-none transition-all duration-300 invisible opacity-0">
        <i class="fas fa-arrow-up text-lg"></i>
    </button>
    
    <script>
        const navbar = document.getElementById('navbar');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuButton = document.getElementById('mobile-menu-button');
        const menuIcon = menuButton ? menuButton.querySelector('i') : null;
        const scrollToTopBtn = document.getElementById('scrollToTopBtn');

        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            // Navbar hide/show on scroll (HANYA UNTUK DESKTOP)
            if (window.innerWidth >= 1024) { // 1024px adalah breakpoint 'lg' Tailwind
                if (scrollTop > lastScrollTop && scrollTop > navbar.offsetHeight) {
                    navbar.classList.add('-translate-y-full');
                } else {
                    navbar.classList.remove('-translate-y-full');
                }
            }
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            if (scrollTop > 300) { 
                scrollToTopBtn.classList.remove('invisible', 'opacity-0');
            } else {
                scrollToTopBtn.classList.add('invisible', 'opacity-0');
            }
        }, false);

        if (menuButton) {
            menuButton.addEventListener('click', () => {
                const menuIcon = menuButton ? menuButton.querySelector('i') : null;
                const isMenuOpen = mobileMenu.classList.contains('max-h-0');
                if (isMenuOpen) {
                    mobileMenu.classList.remove('max-h-0');
                    mobileMenu.classList.add('max-h-[500px]');
                    if (menuIcon) menuIcon.classList.replace('fa-bars', 'fa-times');
                } else {
                    mobileMenu.classList.remove('max-h-[500px]');
                    mobileMenu.classList.add('max-h-0');
                    if (menuIcon) menuIcon.classList.replace('fa-times', 'fa-bars');
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
                    document.querySelectorAll('[id$="-submenu"]').forEach(el => {
                        if (el.id !== submenuId) {
                            el.classList.add('max-h-0');
                            el.classList.remove('max-h-96');
                        }
                    });
                    document.querySelectorAll('[id$="-icon"]').forEach(el => {
                        if (el.id !== iconId) el.classList.remove('rotate-180');
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

        scrollToTopBtn.addEventListener('click', (e) => {
            e.preventDefault(); 
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
    </body>
</html>