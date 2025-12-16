<?php
require_once 'config.php'; // Memuat konfigurasi dan session
Session::start();
 
// --- AJAX Endpoint ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $kategori = $_GET['kategori'] ?? '';
    $year = $_GET['tahun'] ?? '';

    // Jika kategori dipilih, ambil tahun yang tersedia untuk kategori itu
    $available_years = [];
    if ($kategori) {
        $available_years = Database::query("SELECT DISTINCT tahun FROM statistik WHERE kategori = ? ORDER BY tahun DESC", [$kategori])->fetchAll(PDO::FETCH_COLUMN);
    }

    // Jika tahun tidak ada di daftar yang tersedia (atau kosong), pilih tahun terbaru
    if (!in_array($year, $available_years)) {
        $year = $available_years[0] ?? date('Y');
    }

    // Ambil semua kategori unik untuk dropdown utama
    $kategori_list = Database::query("SELECT DISTINCT kategori FROM statistik ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    
    // Ambil data statistik berdasarkan kategori dan tahun yang aktif
    $statistik_data = [];
    if ($kategori) {
        $statistik_data = Database::query(
            "SELECT label, jumlah, updated_at FROM statistik WHERE kategori = ? AND tahun = ? ORDER BY id ASC",
            [$kategori, $year]
        )->fetchAll();
    }
    $total = array_sum(array_column($statistik_data, 'jumlah'));
    
    $last_updated = null;
    foreach ($statistik_data as $row) {
        if (!empty($row['updated_at']) && ($last_updated === null || $row['updated_at'] > $last_updated)) {
            $last_updated = $row['updated_at'];
        }
    }

    echo json_encode([
        'kategori_list' => $kategori_list,
        'available_years' => $available_years,
        'active_kategori' => $kategori,
        'statistik_data' => $statistik_data,
        'total' => $total,
        'last_updated' => $last_updated
    ]);
    exit;
}
// --- End of AJAX Endpoint ---


// Ambil semua kategori unik untuk dropdown utama
$kategori_list = Database::query("SELECT DISTINCT kategori FROM statistik ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

// Tentukan kategori yang aktif
$active_kategori = $_GET['kategori'] ?? ($kategori_list[0] ?? '');

// Ambil tahun yang tersedia untuk kategori yang aktif
$available_years_data = [];
if ($active_kategori) {
    $available_years_data = Database::query("SELECT DISTINCT tahun FROM statistik WHERE kategori = ? ORDER BY tahun DESC", [$active_kategori])->fetchAll(PDO::FETCH_COLUMN);
}

$active_year = $_GET['tahun'] ?? ($available_years_data[0] ?? date('Y'));

// Ambil data statistik berdasarkan kategori dan tahun yang aktif
$statistik_data = [];
if ($active_kategori) {
    $statistik_data = Database::query(
        "SELECT label, jumlah, updated_at FROM statistik WHERE kategori = ? AND tahun = ? ORDER BY id ASC",
        [$active_kategori, $active_year]
    )->fetchAll();
}
$total = array_sum(array_column($statistik_data, 'jumlah'));

$initial_last_updated = null;
foreach ($statistik_data as $row) {
    if (!empty($row['updated_at']) && ($initial_last_updated === null || $row['updated_at'] > $initial_last_updated)) {
        $initial_last_updated = $row['updated_at'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik - <?= SITE_NAME ?></title>

    <link rel="icon" type="image/x-icon" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/logopky.gif">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Mengembalikan gaya scrollbar default untuk tabel statistik */
        #tabel-container .overflow-x-auto {
            scrollbar-width: auto; /* Firefox: 'auto' adalah default */
            scrollbar-color: initial; /* Firefox: kembalikan ke warna default */
        }

        #tabel-container .overflow-x-auto::-webkit-scrollbar {
            width: initial; /* Webkit: kembalikan ke lebar default */
            height: initial; /* Webkit: kembalikan ke tinggi default */
        }

        #tabel-container .overflow-x-auto::-webkit-scrollbar-track {
            background: initial; /* Webkit: kembalikan ke background default */
        }

        #tabel-container .overflow-x-auto::-webkit-scrollbar-thumb {
            background-color: initial; /* Webkit: kembalikan ke warna default */
            border-radius: initial; /* Webkit: kembalikan ke radius default */
            border: initial; /* Webkit: hapus border kustom */
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

    <div class="relative py-16 md:py-24 overflow-hidden">
         <div class="absolute inset-0">
             <img src="assets/img/tulisanDepan.jpeg" alt="Background Statistik" class="w-full h-full object-cover">
              <div class="absolute inset-0 bg-black bg-opacity-50"></div>
         </div>

         <div class="relative container mx-auto px-4 text-center text-white">
             <h1 class="text-5xl font-bold mb-4 tracking-tight">
                  Statistik
             </h1>
             <p class="text-xl mb-6 leading-relaxed">
                  Data dan informasi statistik dari Kelurahan Kalampangan
             </p>
             <div class="flex justify-center">
                 <nav class="inline-flex items-center space-x-2 text-sm bg-white/20 backdrop-blur-sm px-6 py-3 rounded-full border border-white/30">
                      <a href="index.php" class="flex items-center text-white hover:text-gray-200 transition-colors">
                          <i class="fas fa-home mr-2"></i>
                          Beranda
                      </a>
                      <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                      <span class="text-white font-semibold">Statistik</span>
                 </nav>
             </div>
         </div>
    </div>

    <div class="container mx-auto px-4 py-8 md:py-12">
        <?php if (!empty($kategori_list)): ?>
            <!-- Filter Section -->
            <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-200">
                <div id="filter-form" class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
                        <label for="kategori" class="text-sm font-medium text-gray-700 whitespace-nowrap">Pilih Kategori:</label>
                        <select name="kategori" id="kategori" class="w-full sm:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($kategori_list as $kategori): ?>
                                <option value="<?= htmlspecialchars($kategori) ?>" <?= ($kategori === $active_kategori) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kategori) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
                        <label for="tahun" class="text-sm font-medium text-gray-700">Tahun:</label>
                        <select name="tahun" id="tahun" class="w-full sm:w-32 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($available_years_data as $year): ?>
                                <option value="<?= $year ?>" <?= ($year == $active_year) ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Divider -->
                <hr class="my-6 border-gray-200">

                <!-- Chart and Table Section -->
                <div class="grid md:grid-cols-2 gap-6 md:gap-8">
                    <!-- Kotak Grafik Batang (Bar Chart) -->
                    <div class="bg-gray-50 p-4 sm:p-6 rounded-xl border border-gray-200 h-full flex flex-col min-w-0">
                        <h3 class="text-xl font-semibold text-gray-800 text-center mb-4">Diagram Batang</h3>
                        <div class="relative flex-grow flex items-center justify-center h-72 sm:h-80 min-w-0">
                            <?php if (!empty($statistik_data)): ?>
                                <canvas id="statistikBarChart"></canvas>
                            <?php else: ?>
                                <div class="text-center py-12 text-gray-500"><i class="fas fa-chart-bar text-5xl mb-4"></i><p>Data tidak tersedia.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kotak Grafik Lingkaran (Pie Chart) -->
                    <div class="bg-gray-50 p-4 sm:p-6 rounded-xl border border-gray-200 h-full flex flex-col min-w-0">
                        <h3 class="text-xl font-semibold text-gray-800 text-center mb-4">Diagram Lingkaran</h3>
                        <div class="relative flex-grow flex items-center justify-center h-72 sm:h-80 min-w-0">
                            <?php if (!empty($statistik_data)): ?>
                                <canvas id="statistikPieChart"></canvas>
                            <?php else: ?>
                                <div class="text-center py-12 text-gray-500"><i class="fas fa-chart-pie text-5xl mb-4"></i><p>Data tidak tersedia.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kotak Tabel Detail -->
                    <div id="tabel-container" class="bg-gray-50 p-4 sm:p-6 rounded-xl flex flex-col md:col-span-2 border border-gray-200 min-w-0">
                        <h3 class="text-xl font-semibold text-gray-800 text-center mb-2">Tabel Detail Statistik</h3>
                        <p id="tabel-title" class="text-center text-gray-500 mb-4 text-sm">Data <?= htmlspecialchars($active_kategori) ?> Tahun <?= htmlspecialchars($active_year) ?></p>
                        <p id="last-updated-info" class="text-center text-xs text-gray-400 mb-4 italic"></p>
                        <div class="overflow-x-auto border rounded-lg bg-white">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-sm font-bold text-gray-600 uppercase tracking-wider align-middle w-auto">Keterangan</th>
                                        <th class="px-6 py-3 text-center text-sm font-bold text-gray-600 uppercase tracking-wider align-middle w-40">Jumlah</th>
                                        <th class="px-6 py-3 text-right text-sm font-bold text-gray-600 uppercase tracking-wider align-middle w-40">Persentase</th>
                                    </tr>
                                </thead>
                                <tbody id="tabel-body" class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($statistik_data)): ?>
                                        <?php foreach ($statistik_data as $data): ?>
                                            <tr>
                                                <td class="px-6 py-4 text-sm font-medium text-gray-900 align-middle"><?= htmlspecialchars($data['label']) ?></td>
                                                <td class="px-6 py-4 text-sm text-gray-600 text-center align-middle"><?= number_format($data['jumlah']) ?></td>
                                                <td class="px-6 py-4 text-sm text-gray-600 text-right align-middle"><?= $total > 0 ? number_format(($data['jumlah'] / $total) * 100, 2) . '%' : '0.00%' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center py-12 text-gray-500">Data tabel tidak tersedia.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="bg-gray-100">
                                    <tr id="tabel-footer">
                                        <th class="px-6 py-3 text-left text-sm font-bold text-gray-700 uppercase align-middle">Total</th>
                                        <th class="px-6 py-3 text-center text-sm font-bold text-gray-700 align-middle"><?= number_format($total) ?></th>
                                        <th class="px-6 py-3 text-right text-sm font-bold text-gray-700 uppercase align-middle">100%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center"><i class="fas fa-inbox text-5xl mb-4 text-gray-400"></i><h2 class="text-xl font-semibold text-gray-700">Belum Ada Data Statistik</h2><p class="mt-2 text-gray-500">Saat ini belum ada data statistik yang tersedia untuk ditampilkan.</p></div>
        <?php endif; ?>
    </div>

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

    <button id="scrollToTopBtn" class="fixed bottom-6 right-6 z-50 p-3 bg-gradient-to-r from-blue-600 to-violet-500 text-white rounded-full shadow-lg hover:from-blue-700 hover:to-violet-600 focus:outline-none transition-all duration-300 invisible opacity-0">
        <i class="fas fa-arrow-up text-lg"></i>
    </button>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let barChartInstance = null;
            let pieChartInstance = null;

            const statistikData = <?= json_encode($statistik_data) ?>;
            const initialLastUpdated = <?= json_encode($initial_last_updated) ?>;

            function createOrUpdateCharts(statistikData) {
                const labels = statistikData.map(item => item.label);
                const data = statistikData.map(item => item.jumlah);
                const backgroundColors = ['rgba(59, 130, 246, 0.7)', 'rgba(239, 68, 68, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(16, 185, 129, 0.7)', 'rgba(139, 92, 246, 0.7)', 'rgba(236, 72, 153, 0.7)', 'rgba(107, 114, 128, 0.7)'];
                const borderColors = ['rgba(59, 130, 246, 1)', 'rgba(239, 68, 68, 1)', 'rgba(245, 158, 11, 1)', 'rgba(16, 185, 129, 1)', 'rgba(139, 92, 246, 1)', 'rgba(236, 72, 153, 1)', 'rgba(107, 114, 128, 1)'];
                
                // 1. Diagram Batang
                const barCtx = document.getElementById('statistikBarChart').getContext('2d');
                if (barChartInstance) {
                    // Update data grafik yang sudah ada
                    barChartInstance.data.labels = labels;
                    barChartInstance.data.datasets[0].data = data;
                    barChartInstance.update();
                } else {
                    // Buat grafik baru jika belum ada
                    barChartInstance = new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Jumlah',
                                data: data,
                                backgroundColor: backgroundColors.map(c => c.replace('0.7', '0.5')),
                                borderColor: borderColors,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: true, position: 'top' } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                        }
                    });
                }

                // 2. Diagram Lingkaran
                const pieCtx = document.getElementById('statistikPieChart').getContext('2d');
                if (pieChartInstance) {
                    // Update data grafik yang sudah ada
                    pieChartInstance.data.labels = labels;
                    pieChartInstance.data.datasets[0].data = data;
                    pieChartInstance.update();
                } else {
                    // Buat grafik baru jika belum ada
                    pieChartInstance = new Chart(pieCtx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Jumlah',
                                data: data,
                                backgroundColor: backgroundColors,
                                borderColor: 'rgba(255, 255, 255, 0.5)',
                                borderWidth: 2
                            }]
                        },
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false, 
                            plugins: {
                                legend: { 
                                    position: window.innerWidth < 768 ? 'bottom' : 'right', // Bawah di mobile, Kanan di desktop
                                    align: 'center',  // Pusatkan blok legenda
                                    rtl: false,       // Paksa perataan dari tengah untuk wrapping yang lebih baik
                                    labels: {
                                        boxWidth: 12 // Perkecil kotak warna legenda
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            const data = context.chart.data.datasets[0].data;
                                            const total = data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? ((value / total) * 100).toFixed(2) + '%' : '0.00%';
                                            
                                            return `${label}: ${value} (${percentage})`;
                                        }
                                    }
                                }
                            },
                            layout: {
                                padding: {
                                    bottom: 10 // Beri sedikit ruang di bawah
                                }
                            }
                        }
                    });
                }
            }

            // --- AJAX Logic for Filters ---
            const tahunSelect = document.getElementById('tahun');
            const kategoriSelect = document.getElementById('kategori');

            async function updateStatistik() {
                const selectedKategori = kategoriSelect.value;
                const selectedTahun = tahunSelect.value;

                const url = `statistik.php?ajax=1&kategori=${selectedKategori}&tahun=${selectedTahun}`;
                try {
                    const response = await fetch(url);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const data = await response.json();

                    // Update URL di browser tanpa reload
                    const newUrl = `statistik.php?tahun=${selectedTahun}&kategori=${data.active_kategori}`;
                    window.history.pushState({path: newUrl}, '', newUrl);

                    // Update dropdown kategori
                    // (Dropdown kategori utama tidak perlu diubah karena sudah berisi semua kategori)

                    // Update dropdown TAHUN berdasarkan kategori yang dipilih
                    tahunSelect.innerHTML = '';
                    data.available_years.forEach(year => {
                        const option = document.createElement('option');
                        option.value = year;
                        option.textContent = year; // htmlspecialchars is not needed here
                        if (year == selectedTahun) option.selected = true;
                        tahunSelect.appendChild(option);
                    });

                    // Update judul tabel
                    document.querySelector('h3.text-xl.font-semibold.text-gray-800.text-center.mb-2').textContent = `Tabel Detail Statistik`;
                    document.getElementById('tabel-title').textContent = `Data ${data.active_kategori} Tahun ${selectedTahun}`;
                    
                    // Update info last updated
                    if (data.last_updated) {
                        const date = new Date(data.last_updated);
                        document.getElementById('last-updated-info').textContent = 'Terakhir diperbarui: ' + date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
                    } else {
                        document.getElementById('last-updated-info').textContent = '';
                    }

                    // Update isi tabel
                    const tabelBody = document.getElementById('tabel-body');
                    tabelBody.innerHTML = '';
                    if (data.statistik_data.length > 0) {
                        data.statistik_data.forEach(item => {
                            const percentage = data.total > 0 ? ((item.jumlah / data.total) * 100).toFixed(2) + '%' : '0.00%';
                            const row = `
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 align-middle">${item.label}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 text-center align-middle">${new Intl.NumberFormat('id-ID').format(item.jumlah)}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 text-right align-middle">${percentage}</td>
                                </tr>
                            `;
                            tabelBody.innerHTML += row;
                        });
                    } else {
                        tabelBody.innerHTML = '<tr><td colspan="3" class="text-center py-12 text-gray-500">Data tabel tidak tersedia.</td></tr>';
                    }

                    // Update footer tabel
                    const tabelFooter = document.getElementById('tabel-footer');
                    tabelFooter.cells[1].innerHTML = new Intl.NumberFormat('id-ID').format(data.total);

                    // Panggil fungsi untuk menggambar/memperbarui grafik
                    createOrUpdateCharts(data.statistik_data);

                } catch (error) {
                    console.error('Gagal memuat data statistik:', error);
                }
            }

            tahunSelect.addEventListener('change', updateStatistik);
            kategoriSelect.addEventListener('change', updateStatistik);

            // Update posisi legenda pie chart saat ukuran window berubah
            window.addEventListener('resize', () => {
                if (pieChartInstance) {
                    pieChartInstance.options.plugins.legend.position = window.innerWidth < 768 ? 'bottom' : 'right';
                    pieChartInstance.update();
                }
            });
            // Panggil fungsi untuk menggambar grafik saat halaman pertama kali dimuat
            if (statistikData.length > 0) {
                createOrUpdateCharts(statistikData);
            }
            
            // Set initial last updated
            if (initialLastUpdated) {
                const date = new Date(initialLastUpdated);
                document.getElementById('last-updated-info').textContent = 'Terakhir diperbarui: ' + date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            }
        });

    </script>

    <script>
        // Skrip untuk navbar, dropdown, dan scroll to top
        const scrollToTopBtn = document.getElementById('scrollToTopBtn');
        const navbar = document.getElementById('navbar');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuButton = document.getElementById('mobile-menu-button');

        const backgroundMusic = document.getElementById('background-music');
        let musicStarted = false;

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

        scrollToTopBtn.addEventListener('click', (e) => {
            e.preventDefault(); 
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        if (menuButton) {
            const menuIcon = menuButton.querySelector('i');
            menuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                const isMenuOpen = mobileMenu.classList.contains('max-h-0');
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
        setupDropdown('mobile-statistik-button', 'mobile-statistik-submenu', 'mobile-statistik-icon');

    </script>
</body>
</html>