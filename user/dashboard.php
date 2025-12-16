<?php
require_once '../config.php';
Session::start();

// Cek login dan pastikan user (bukan admin)
if (!Session::has('user_id') || Session::isAdmin()) {
    Session::set('error', 'Silakan login sebagai User terlebih dahulu');
    redirect('login.php');
}

// --- PENGAMBILAN NOTIFIKASI SESSION ---
$success = Session::get('success', '');
Session::remove('success');
// ------------------------------------

$userId = Session::getUserId();

try {
    // Ambil data user
    $user = Database::query("SELECT * FROM user WHERE user_id = ?", [$userId])->fetch();
    // Double check - jika user tidak ditemukan (misal user_id di session tidak valid)
    if (!$user) {
        Session::destroy();
        Session::set('error', 'Akun pengguna tidak ditemukan. Silakan login kembali.');
        redirect('login.php');
    }
} catch (PDOException $e) {
    // Jika tabel 'users' tidak ditemukan, redirect ke login dengan pesan error
    Session::set('error', 'Terjadi masalah database. Tabel pengguna tidak ditemukan.');
    redirect('login.php');
}

// Inisialisasi statistik dengan nilai default 0
$totalPengajuan = 0;
$pengajuanPending = 0;
$pengajuanDiterima = 0;
$pengajuanDitolak = 0;

try {
    // Statistik
    $pengajuanDiproses = Database::query(
        "SELECT COUNT(*) as total FROM pengajuan_surat WHERE user_id = ? AND status IN ('Diajukan User', 'Verifikasi Kasi')", 
        [$userId]
    )->fetch()['total'];

    $pengajuanDiterima = Database::query(
        "SELECT COUNT(*) as total FROM pengajuan_surat WHERE user_id = ? AND status = 'Selesai'",
        [$userId]
    )->fetch()['total'];

    $pengajuanDitolak = Database::query(
        "SELECT COUNT(*) as total FROM pengajuan_surat WHERE user_id = ? AND status = 'Ditolak'", 
        [$userId]
    )->fetch()['total'];

} catch (PDOException $e) {
    // Jika tabel tidak ditemukan, set nilai default ke 0
    error_log("Database Error in user dashboard: " . $e->getMessage());
    $totalPengajuan = 0;
    $pengajuanDiproses = 0;
    $pengajuanDiterima = 0;
    $pengajuanDitolak = 0;
}

// Pengajuan terbaru
$pengajuanTerbaru = []; // Inisialisasi sebagai array kosong
try {
    $pengajuanTerbaru = Database::query(
        "SELECT p.*, js.nama_surat FROM pengajuan_surat p 
         LEFT JOIN jenis_surat js ON p.id_jenis_surat = js.id_jenis_surat
         WHERE p.user_id = ? ORDER BY p.tgl_pengajuan DESC
         LIMIT 5",
        [$userId]
    )->fetchAll();
} catch (PDOException $e) {
    error_log("Database Error fetching recent submissions in user dashboard: " . $e->getMessage());
}

// Notifikasi belum dibaca
$notifikasi = [];
$unreadCount = 0;
try {
    $notifikasi = Database::query(
        "SELECT * FROM notifikasi WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 5",
        [$userId]
    )->fetchAll();
    $unreadCount = count($notifikasi);
} catch (PDOException $e) {
    // Jika tabel notifikasi tidak ada, biarkan array notifikasi kosong
    error_log("Database Error in user dashboard (notifikasi): " . $e->getMessage());
}

// --- LOGIKA UNTUK MENCEGAH KEDIPAN SIDEBAR (TERPUSAT) ---
$body_class = '';
// Defaultnya adalah sidebar tertutup (compact), untuk konsistensi dengan halaman admin.
// Sidebar akan terbuka jika cookie 'sidebarOpen' secara eksplisit bernilai 'true'.
if (!isset($_COOKIE['sidebarOpen']) || $_COOKIE['sidebarOpen'] !== 'true') {
    $body_class .= ' sidebar-closed';
}
$body_class .= ' no-transition'; // Selalu tambahkan no-transition saat load
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengguna - <?= SITE_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <link rel="apple-touch-icon" href="../assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 <?= trim($body_class) ?>">

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 md:hidden z-30 pointer-events-none opacity-0 transition-opacity duration-300"></div>

    <div class="flex">
        <!-- Sidebar User -->
        <?php require_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <?php require_once 'navbar.php'; ?>

            <div class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5 lg:gap-6 mb-8">
                    <div class="bg-yellow-50 rounded-lg md:rounded-xl shadow-md md:shadow-lg p-4 sm:p-5 lg:p-6 border-l-4 border-yellow-500 h-full">
                        <div class="flex items-center justify-between gap-2 md:gap-3">
                            <div>
                                <p class="text-gray-600 text-xs md:text-sm lg:text-base font-medium">Pengajuan Sedang Diproses</p>
                                <p class="text-2xl md:text-3xl lg:text-4xl font-bold text-yellow-600 mt-1"><?= $pengajuanDiproses ?></p>
                            </div>
                            <div class="bg-yellow-100 p-3 md:p-4 lg:p-5 rounded-full flex-shrink-0">
                                <i class="fas fa-clock text-2xl md:text-3xl lg:text-3xl text-yellow-600"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Pengajuan</p>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg md:rounded-xl shadow-md md:shadow-lg p-4 sm:p-5 lg:p-6 border-l-4 border-green-500 h-full">
                        <div class="flex items-center justify-between gap-2 md:gap-3">
                            <div>
                                <p class="text-gray-600 text-xs md:text-sm lg:text-base font-medium">Pengajuan Selesai</p>
                                <p class="text-2xl md:text-3xl lg:text-4xl font-bold text-green-600 mt-1"><?= $pengajuanDiterima ?></p>
                            </div>
                            <div class="bg-green-100 p-3 md:p-4 lg:p-5 rounded-full flex-shrink-0">
                                <i class="fas fa-check text-2xl md:text-3xl lg:text-3xl text-green-600"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Pengajuan</p>
                    </div>
                    
                    <div class="bg-red-50 rounded-lg md:rounded-xl shadow-md md:shadow-lg p-4 sm:p-5 lg:p-6 border-l-4 border-red-500 h-full">
                        <div class="flex items-center justify-between gap-2 md:gap-3">
                            <div>
                                <p class="text-gray-600 text-xs md:text-sm lg:text-base font-medium">Pengajuan Ditolak</p>
                                <p class="text-2xl md:text-3xl lg:text-4xl font-bold text-red-600 mt-1"><?= $pengajuanDitolak ?></p>
                            </div>
                            <div class="bg-red-100 p-3 md:p-4 lg:p-5 rounded-full flex-shrink-0">
                                <i class="fas fa-times text-2xl md:text-3xl lg:text-3xl text-red-600"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Pengajuan</p>
                    </div>
                </div>
                
                <!-- Kotak Syarat Pengajuan Surat -->
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <!-- Baris Atas: Judul dan Tombol -->
                    <div class="flex flex-col md:flex-row items-center justify-between gap-2 md:gap-3">
                        <h3 class="text-base md:text-lg lg:text-xl font-bold text-gray-800 text-center md:text-left">Syarat Pengajuan Surat</h3>
                        <!--Ganti Link Google Drive ke Link yang memilki file formulir untuk pengajuan surat -->
                        <a href="Copy paste disini" target="_blank" rel="noopener noreferrer" class="bg-blue-600 text-white px-3 md:px-4 lg:px-5 py-2 md:py-2.5 rounded-lg hover:bg-blue-700 font-semibold whitespace-nowrap transition-colors duration-200 text-xs sm:text-sm lg:text-base"> 
                            <i class="fas fa-download mr-1 md:mr-2"></i>Unduh Formulir
                        </a>
                    </div>
                    <!-- Baris Bawah: Deskripsi -->
                    <div class="mt-4">
                        <p class="text-xs sm:text-sm text-gray-600 text-center md:text-left">Berikut adalah syarat yang harus dipenuhi untuk mengajukan surat:</p>
                        
                        <!-- Grid untuk 10 Kotak Syarat -->
                         
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3 lg:gap-4 mt-3 sm:mt-4">
                            <!-- Kotak 1 -->
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan Usaha (SKU)</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP & KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Pas Foto 3x4 / 4x6 (2 lbr)</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                            <!-- Kotak 2 -->
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan Tidak Mampu (SKTM)</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                            <!-- Kotak 3-10 Responsif -->
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan Kematian</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan Kelahiran</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan Belum Menikah</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan untuk Menikah</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Pengajuan PBB Baru</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Sertifikat/IMB</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir yang sudah di isi</span></li>
                                </ul>
                            </div>
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan Ahli Waris</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan Berkelakuan Baik</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>KTP & KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                            <div class="bg-gray-50 p-2.5 sm:p-3 lg:p-4 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                <h4 class="font-bold text-gray-800 text-xs sm:text-sm mb-1.5 sm:mb-2 border-b pb-1.5 sm:pb-2">Surat Keterangan Domisili</h4>
                                <ul class="text-xs text-gray-600 space-y-0.5 sm:space-y-1 mt-1.5 sm:mt-2">
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KTP</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Foto KK</span></li>
                                    <li class="flex items-start gap-1.5"><i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0 text-xs sm:text-sm"></i><span>Formulir (TTD RT)</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div> <!-- Penutup div.flex -->

    
    <script>
        // --- SweetAlert2 Notifikasi Popup dari PHP Session ---
        <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Login Berhasil!',
            text: '<?= addslashes($success) ?>',
            timer: 3000,
            showConfirmButton: false
        });
        <?php endif; ?>
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.remove('no-transition');
        });
    </script>
</body>
</html>