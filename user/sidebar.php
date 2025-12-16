<?php
// File: d:\xampp\htdocs\SI-KLEPON\user\sidebar.php

if (!isset($user) || !is_array($user)) {
    // Fallback jika variabel $user tidak ada, untuk mencegah error.
    echo "Sidebar error: Data pengguna tidak ditemukan.";
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

// Mendapatkan nama file halaman saat ini untuk menandai menu aktif
$currentPage = basename($_SERVER['PHP_SELF']);

$activeClass = 'bg-blue-50 text-blue-600 font-semibold';
$inactiveClass = 'text-gray-700 hover:bg-gray-50';

?>
<!-- CSS untuk mencegah layout shift dari SweetAlert2 -->
<style>
    body.swal2-shown {
        padding-right: 0 !important;
    }
    /* Sembunyikan scrollbar tapi tetap fungsional untuk mencegah layout shift */
    /* Mencegah pergeseran layout dengan selalu menampilkan ruang untuk scrollbar */
    html {
        overflow-y: scroll;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none;  /* IE and Edge */
    }
    html::-webkit-scrollbar { display: none; /* Chrome, Safari, Opera */ }

    /* --- Sidebar Layout & Transition (DIPINDAHKAN KE SINI) --- */
    /* Transisi hanya untuk sidebar utama dan konten */

    /* State: Sidebar Terbuka (Default) */
    #user-sidebar { width: 256px; }
    #main-content-wrapper { margin-left: 256px; }
    #user-navbar { width: calc(100% - 256px); left: 256px; position: fixed; }

    /* State: Sidebar Tertutup */
    body.sidebar-closed #user-sidebar { width: 64px; }
    body.sidebar-closed #main-content-wrapper { margin-left: 64px; }
    body.sidebar-closed #user-navbar { width: calc(100% - 64px); left: 64px; position: fixed; }

    /* State: Sidebar Tertutup - Konten di dalam sidebar */
    body.sidebar-closed .sidebar-text,
    body.sidebar-closed .sidebar-logo-text {
        opacity: 0; width: 0; overflow: hidden; display: none;
    }
    body.sidebar-closed .sidebar-header { justify-content: center; }
    body.sidebar-closed .sidebar-logo-container { width: 64px; justify-content: center; }
    body.sidebar-closed .nav-link-item { justify-content: center; padding-left: 0; padding-right: 0; }
    body.sidebar-closed .sidebar-icon { margin-right: 0; }
    body.sidebar-closed .sidebar-menu-container { 
        padding-top: 0.5rem; 
        padding-bottom: 0.5rem; 
        gap: 0.75rem; /* 12px, setara gap-3 */
    }
    body.sidebar-closed .sidebar-group-title { display: none; }

    /* State: Mobile (Layar < 768px) */
    @media (max-width: 767px) {
        #user-sidebar { display: none; }
        #user-sidebar.open { display: block; }
        #main-content-wrapper,
        body.sidebar-closed #main-content-wrapper { margin-left: 0; }
        #user-navbar,
        body.sidebar-closed #user-navbar { width: 100%; left: 0; }
    }

    /* Override compact styles specifically for mobile view when sidebar is open */
    @media (max-width: 767px) {
        body.sidebar-closed #user-sidebar.open {
            width: 256px; /* Force full width */
        }
        body.sidebar-closed #user-sidebar.open .sidebar-text,
        body.sidebar-closed #user-sidebar.open .sidebar-logo-text {
            opacity: 1; width: auto; overflow: visible; display: block;
        }
        body.sidebar-closed #user-sidebar.open .sidebar-header,
        body.sidebar-closed #user-sidebar.open .sidebar-logo-container {
            justify-content: flex-start;
        }
        body.sidebar-closed #user-sidebar.open .nav-link-item {
            justify-content: flex-start; padding-left: 1rem; padding-right: 1rem;
        }
        body.sidebar-closed #user-sidebar.open .sidebar-icon {
            margin-right: 0.75rem;
        }
    }
</style>
<div id="user-sidebar" class="bg-white shadow-xl h-screen fixed top-0 left-0 z-40">
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
            <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text sidebar-group-title">Utama</h3>
            <a href="dashboard.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'dashboard.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-tachometer-alt sidebar-icon mr-3"></i><span class="sidebar-text">Dashboard</span>
            </a>
            <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text sidebar-group-title">Menu Utama</h3>
            <a href="pengajuan_surat.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'pengajuan_surat.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-file-alt sidebar-icon mr-3"></i><span class="sidebar-text">Pengajuan Surat</span>
            </a>
            <a href="arsip_surat.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $currentPage == 'arsip_surat.php' ? $activeClass : $inactiveClass ?>">
                <i class="fas fa-archive sidebar-icon mr-3"></i><span class="sidebar-text">Arsip Surat</span>
            </a>
            <h3 class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text sidebar-group-title">Lainnya</h3>
            <a href="../index.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $inactiveClass ?>">
                <i class="fas fa-globe sidebar-icon mr-3"></i><span class="sidebar-text">Beranda</span>
            </a>
            <a href="../logout.php" class="flex items-center px-4 py-2.5 rounded-lg nav-link-item <?= $inactiveClass ?>" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="fas fa-sign-out-alt sidebar-icon mr-3"></i><span class="sidebar-text">Keluar</span>
            </a>
        </nav>
    </div>
</div>

<!-- Skrip Sidebar Terpusat -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('user-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const desktopToggleButton = document.getElementById('sidebar-toggle-desktop');
    const mobileToggleButton = document.getElementById('sidebar-toggle-mobile');

    // --- LOGIKA UNTUK DESKTOP (COLLAPSE) ---
    function setDesktopSidebarState(isClosed) {
        // Simpan state ke cookie/localStorage
        document.cookie = `sidebarOpen=${!isClosed}; path=/; max-age=31536000`;
        localStorage.setItem('sidebarOpen', !isClosed);

        if (isClosed) {
            document.body.classList.add('sidebar-closed');
        } else {
            document.body.classList.remove('sidebar-closed');
        }
    }

    if (desktopToggleButton) {
        desktopToggleButton.addEventListener('click', () => {
            const isCurrentlyClosed = document.body.classList.contains('sidebar-closed');
            setDesktopSidebarState(!isCurrentlyClosed);
        });
    }

    // --- LOGIKA UNTUK MOBILE (SHOW/HIDE) ---
    function setMobileSidebarState(isOpen) {
        if (!sidebar || !overlay) return;

        if (isOpen) {
            sidebar.classList.add('open');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
        } else {
            sidebar.classList.remove('open');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }
    }

    if (mobileToggleButton) {
        mobileToggleButton.addEventListener('click', (e) => {
            e.stopPropagation();
            const isCurrentlyOpen = sidebar.classList.contains('open');
            setMobileSidebarState(!isCurrentlyOpen);
        });
    }

    // Overlay hanya untuk menutup sidebar mobile
    if (overlay) {
        overlay.addEventListener('click', () => {
            setMobileSidebarState(false);
        });
    }
});
</script>