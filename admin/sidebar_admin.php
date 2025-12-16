<?php
// File: d:\xampp\htdocs\Web_SI-KLEPON\admin\sidebar_admin.php

if (!isset($admin) || !is_array($admin)) {
    // Fallback jika variabel $admin tidak ada, untuk mencegah error.
    // Sebaiknya pastikan $admin selalu di-set sebelum include file ini.
    echo "Sidebar error: Data admin tidak ditemukan.";
    return;
}

// Fungsi untuk mengambil inisial, dipindahkan ke sini agar terpusat.
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $words = explode(' ', $name);
        $initials = '';
        if (count($words) >= 2) {
            $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } else if (!empty($words[0])) {
            $initials = strtoupper(substr($words[0], 0, 2));
        }
        return $initials;
    }
}

$adminInitials = getInitials($admin['nama']);

// Mendapatkan nama file halaman saat ini untuk menandai menu aktif
$currentPage = basename($_SERVER['PHP_SELF']);

$activeClass = 'bg-blue-50 text-blue-600 font-semibold';
$inactiveClass = 'text-gray-700 hover:bg-gray-50';

?>
<!-- CSS Terpusat untuk Layout Admin -->
<style>
    /* Mencegah layout shift dari SweetAlert2 */
    body.swal2-shown {
        padding-right: 0 !important;
    }
    /* Sembunyikan scrollbar tapi tetap fungsional */
    html {
        overflow-y: scroll;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none;  /* IE and Edge */
    }
html::-webkit-scrollbar { display: none; /* Chrome, Safari, Opera */ }

/* --- Sidebar Layout & Transition --- */

/* State: Sidebar Terbuka (Default) */
#admin-sidebar { width: 256px; }
#main-content-wrapper { margin-left: 256px; }
#admin-navbar { width: calc(100% - 256px); left: 256px; position: fixed; }

/* State: Sidebar Tertutup */
body.sidebar-closed #admin-sidebar { width: 64px; }
body.sidebar-closed #main-content-wrapper { margin-left: 64px; }
body.sidebar-closed #admin-navbar { width: calc(100% - 64px); left: 64px; position: fixed; }

/* State: Sidebar Tertutup - Konten di dalam sidebar */
body.sidebar-closed .sidebar-text,
body.sidebar-closed .sidebar-logo-text {
    opacity: 0; width: 0; overflow: hidden; display: none;
}
body.sidebar-closed .sidebar-header { justify-content: center; }
body.sidebar-closed .sidebar-logo-container { width: 64px; justify-content: center; }
body.sidebar-closed #sidebar-content-container { padding-left: 0; padding-right: 0; }
body.sidebar-closed .nav-link-item { 
    justify-content: center; 
    padding-left: 0 !important; padding-right: 0 !important; 
}
body.sidebar-closed .sidebar-icon { margin-right: 0; }
body.sidebar-closed .sidebar-menu-container { 
    padding-top: 0.5rem; 
    padding-bottom: 0.5rem; 
    gap: 0.75rem; /* 12px, setara gap-3 */
}
body.sidebar-closed .sidebar-group-title { display: none; }
body.sidebar-closed .nav-link-item:hover {
    background-color: rgba(59, 130, 246, 0.1); /* bg-blue-500/10 */
    width: 32px;
    height: 36px;
    margin-left: auto;
    margin-right: auto;
}
body.sidebar-closed .nav-link-item.bg-blue-50 { /* Aturan untuk item aktif */
    background-color: rgba(59, 130, 246, 0.15) !important;
    width: 32px;
    height: 36px;
    margin-left: auto;
    margin-right: auto;
}

/* State: Mobile (Layar < 768px) */
@media (max-width: 767px) {
    #admin-sidebar { transform: translateX(-100%); }
    #admin-sidebar.open { transform: translateX(0); }
    #main-content-wrapper,
    body.sidebar-closed #main-content-wrapper { margin-left: 0; }
    #admin-navbar,
    body.sidebar-closed #admin-navbar { width: 100%; left: 0; }
}


</style>

<div id="admin-sidebar" class="bg-white shadow-xl h-screen fixed top-0 left-0 z-40">
    <div class="text-white p-4 h-16 flex items-center justify-start sidebar-header" style="background-color: #7571f9;">
        <div class="flex items-center space-x-2 overflow-hidden sidebar-logo-container">
            <img src="../assets/img/logopky.gif" alt="Logo Pky" class="h-8 w-auto flex-shrink-0 sidebar-logo-img">
            <div class="leading-none flex-grow sidebar-logo-text">
                <h2 class="text-sm font-bold whitespace-nowrap">Kelurahan Kalampangan</h2>
                <p class="text-xs font-bold whitespace-nowrap">PALANGKA RAYA</p>
            </div>
        </div>
    </div>

    <div id="sidebar-content-container" class="p-4 overflow-y-auto" style="height: calc(100vh - 4rem);">
        <nav class="flex flex-col gap-2 sidebar-menu-container">
            <!-- UTAMA -->
            <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text sidebar-group-title">UTAMA</h3>
            <a href="dashboard.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'dashboard.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-tachometer-alt sidebar-icon mr-3"></i><span class="sidebar-text">Dashboard</span>
            </a>
            <?php if ($admin['role'] === 'admin'): // Hanya tampil untuk Kasi ?>
            <a href="berkas_masuk.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'berkas_masuk.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-inbox sidebar-icon mr-3"></i><span class="sidebar-text">Berkas Masuk</span>
            </a>
            <?php endif; ?>

            <!-- MENU UTAMA -->
            <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text sidebar-group-title">MENU UTAMA</h3>
            <?php if ($admin['role'] === 'admin'): // Hanya tampil untuk Kasi ?>
            <a href="pengajuan.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'pengajuan.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-file-alt sidebar-icon mr-3"></i><span class="sidebar-text">Pengajuan Surat</span>
            </a>
            <?php endif; ?>
            <a href="arsip_surat.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'arsip_surat.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-archive sidebar-icon mr-3"></i><span class="sidebar-text">Arsip Surat</span>
            </a>
            <?php if ($admin['role'] === 'super_admin'): // Hanya tampil untuk Super Admin ?>
            <a href="jenis_surat.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'jenis_surat.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-envelope-open-text sidebar-icon mr-3"></i><span class="sidebar-text">Jenis Surat</span>
            </a>
            <?php endif; ?>
            <a href="laporan.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'laporan.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-chart-bar sidebar-icon mr-3"></i><span class="sidebar-text">Laporan Surat</span>
            </a>

            <!-- MANAJEMEN AKUN -->
            <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text sidebar-group-title">MANAJEMEN AKUN</h3>
            <?php if (in_array($admin['role'], ['super_admin', 'admin'])): ?>
            <a href="admin_users.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'admin_users.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-user-shield sidebar-icon mr-3"></i><span class="sidebar-text">Admin</span>
            </a>
            <?php endif; ?>
            <a href="users.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'users.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-users sidebar-icon mr-3"></i><span class="sidebar-text">Pengguna</span>
            </a>

            <!-- KONTEN (Hanya untuk Admin, bukan Super Admin) -->
            <?php if ($admin['role'] === 'admin'): ?>
                <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text sidebar-group-title">KONTEN</h3>
                <a href="pengumuman.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'pengumuman.php' ? $activeClass : $inactiveClass ?>">
                    <i class="fas fa-bullhorn sidebar-icon mr-3"></i><span class="sidebar-text">Pengumuman</span>
                </a>
                <a href="kegiatan.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'kegiatan.php' ? $activeClass : $inactiveClass ?>">
                    <i class="fas fa-calendar-alt sidebar-icon mr-3"></i><span class="sidebar-text">Kegiatan</span>
                </a>
                <a href="struktur.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'struktur.php' ? $activeClass : $inactiveClass ?>">
                    <i class="fas fa-sitemap sidebar-icon mr-3"></i><span class="sidebar-text">Struktur</span>
                </a>
                <a href="statistik.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'statistik.php' ? $activeClass : $inactiveClass ?>"><i class="fas fa-chart-pie sidebar-icon mr-3"></i><span class="sidebar-text">Statistik</span></a>
            <?php endif; ?>

            <!-- LAINNYA -->
            <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text sidebar-group-title">LAINNYA</h3>
            <a href="../" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $inactiveClass ?>"><i class="fas fa-home sidebar-icon mr-3"></i><span class="sidebar-text">Beranda</span></a>
            <a href="../logout.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $inactiveClass ?>">
                <i class="fas fa-sign-out-alt sidebar-icon mr-3"></i><span class="sidebar-text">Keluar</span>
            </a>
        </nav>
    </div>
</div>

<script>
// Skrip ini dijalankan segera setelah elemen sidebar di-parse untuk mencegah kedipan.
(function() {
    const sidebarContent = document.getElementById('sidebar-content-container'); // Elemen yang bisa di-scroll

    if (sidebarContent) {
        // 1. Kembalikan posisi scroll dari localStorage secepat mungkin.
        const savedScrollTop = localStorage.getItem('sidebarScrollTop');
        if (savedScrollTop !== null) {
            sidebarContent.scrollTop = parseInt(savedScrollTop, 10);
        }

        // 2. Tambahkan event listener untuk menyimpan posisi scroll saat pengguna menggulir.
        sidebarContent.addEventListener('scroll', function() {
            localStorage.setItem('sidebarScrollTop', sidebarContent.scrollTop);
        });
    }
})();
</script>

<!-- Skrip Sidebar Terpusat -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('admin-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const toggleButton = document.getElementById('sidebar-toggle-desktop');

    // Fungsi untuk mengatur state sidebar
    function setSidebarState(isOpen) {
        // Simpan state ke cookie untuk PHP dan localStorage untuk JS
        document.cookie = `sidebarOpen=${isOpen}; path=/; max-age=31536000`;
        localStorage.setItem('sidebarOpen', isOpen);

        if (isOpen) {
            document.body.classList.remove('sidebar-closed');
            sidebar.classList.add('open'); // Untuk mobile
            overlay.classList.remove('opacity-0', 'pointer-events-none');
        } else {
            document.body.classList.add('sidebar-closed');
            sidebar.classList.remove('open'); // Untuk mobile
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }
    }

    // Event listener untuk tombol toggle
    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            const currentState = !document.body.classList.contains('sidebar-closed');
            setSidebarState(!currentState);
        });
    }
    if (overlay) overlay.addEventListener('click', () => setSidebarState(false));
});
</script>