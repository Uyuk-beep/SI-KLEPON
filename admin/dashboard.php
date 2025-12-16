<?php
require_once '../config.php';
Session::start();

// Cek login dan pastikan admin di awal file
if (!Session::isAdmin()) {
    Session::set('error', 'Anda harus login sebagai admin untuk mengakses halaman ini.');
    redirect('login.php');
}

// --- PENGAMBILAN NOTIFIKASI SESSION ---
$success = Session::get('success', '');
Session::remove('success');
// ------------------------------------

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();

// Double check - jika admin tidak ditemukan di database
if (!$admin) {
    Session::destroy();
    redirect('login.php');
}

// AJAX endpoint: return pengajuan per bulan untuk tahun tertentu (dipanggil via fetch)
if (isset($_GET['chart_year_ajax']) && !empty($_GET['chart_year_ajax'])) {
    $year = intval($_GET['chart_year_ajax']);
    if ($year < 2000 || $year > 2100) $year = date('Y');
    
    try {
        // Query untuk data bulanan
        $rows = Database::query(
            "SELECT
                MONTH(tgl_pengajuan) as bulan,
                COUNT(CASE WHEN status IN ('Diajukan User', 'Verifikasi Kasi') THEN 1 END) as diproses,
                COUNT(CASE WHEN status = 'selesai' THEN 1 END) as diterima,
                COUNT(CASE WHEN status = 'Ditolak' THEN 1 END) as ditolak
             FROM pengajuan_surat
             WHERE YEAR(tgl_pengajuan) = ?
             GROUP BY bulan",
            [$year]
        )->fetchAll();
        $data = [
            'diproses' => array_fill(0, 12, 0),
            'diterima' => array_fill(0, 12, 0)
            // 'ditolak' bisa ditambahkan jika ingin ditampilkan di grafik
        ];
        $total_diproses = 0;
        $total_diterima = 0;

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $bulan_index = intval($row['bulan']) - 1;
                $data['diproses'][$bulan_index] = intval($row['diproses']);
                $data['diterima'][$bulan_index] = intval($row['diterima']);
                $total_diproses += $data['diproses'][$bulan_index];
                $total_diterima += $data['diterima'][$bulan_index];
            }
        }
        $data['total_diproses'] = $total_diproses;
        $data['total_diterima'] = $total_diterima;

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode($data);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode([
            'diproses' => array_fill(0,12,0),
            'diterima' => array_fill(0,12,0),
            'total_diproses' => 0,
            'total_diterima' => 0
        ]);
    }
    
    exit;
}

// --- PENGAMBILAN DATA UNTUK HALAMAN UTAMA ---
$current_year = date('Y');

// Data untuk summary cards - hanya total user yang perlu di-load di awal.
// Sisanya akan di-load oleh AJAX untuk konsistensi dengan grafik.
$summary = Database::query("SELECT COUNT(*) as total_user FROM user")->fetch();

$totalUser = $summary['total_user'] ?? 0;

// Data untuk dropdown tahun pada chart
$chartYears = Database::query("SELECT DISTINCT YEAR(tgl_pengajuan) as tahun FROM pengajuan_surat WHERE tgl_pengajuan IS NOT NULL ORDER BY tahun DESC")->fetchAll();
if (empty($chartYears)) $chartYears = [['tahun' => $current_year]];

// --- LOGIKA UNTUK MENCEGAH KEDIPAN SIDEBAR ---
$body_class = '';
if (isset($_COOKIE['sidebarOpen']) && $_COOKIE['sidebarOpen'] === 'false') $body_class .= ' sidebar-closed';
$body_class .= ' no-transition'; // Selalu tambahkan no-transition saat load untuk mencegah glitch animasi
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <link rel="apple-touch-icon" href="../assets/img/logopky.gif">
    <title>Dashboard Admin - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* CSS spesifik halaman bisa ditaruh di sini jika ada */
    </style>
</head>
<body class="bg-gray-100 <?= trim($body_class) ?>">
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.remove('no-transition');
        });
    </script>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 md:hidden z-30 pointer-events-none transition-opacity duration-300"></div>

    <div class="flex">
        
        <?php require_once 'sidebar_admin.php'; ?>

        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <?php require_once 'navbar_admin.php'; ?>

            <main class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8 flex-grow">

                <!-- 3 Kotak Ringkasan Pengajuan (Diadaptasi dari user/dashboard.php) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5 lg:gap-8 mb-8 sm:mb-10 lg:mb-12">
                    <div class="bg-yellow-50 rounded-lg sm:rounded-xl shadow-md sm:shadow-lg p-4 sm:p-5 lg:p-6 border-l-4 border-yellow-500 h-full">
                        <div class="flex items-center justify-between gap-2 sm:gap-3">
                            <div>
                                <p class="text-gray-600 text-xs sm:text-sm lg:text-base font-medium">Pengajuan Perlu Diproses</p>
                                <p id="summary-diproses" class="text-2xl sm:text-3xl lg:text-4xl font-bold text-yellow-600 mt-1">0</p>
                            </div>
                            <div class="bg-yellow-100 p-3 sm:p-4 lg:p-5 rounded-full flex-shrink-0">
                                <i class="fas fa-clock text-2xl sm:text-3xl lg:text-3xl text-yellow-600"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Total Pengajuan</p>
                    </div>

                    <div class="bg-green-50 rounded-lg sm:rounded-xl shadow-md sm:shadow-lg p-4 sm:p-5 lg:p-6 border-l-4 border-green-500 h-full">
                        <div class="flex items-center justify-between gap-2 sm:gap-3">
                            <div>
                                <p class="text-gray-600 text-xs sm:text-sm lg:text-base font-medium">Pengajuan Selesai</p>
                                <p id="summary-diterima" class="text-2xl sm:text-3xl lg:text-4xl font-bold text-green-600 mt-1">0</p>
                            </div>
                            <div class="bg-green-100 p-3 sm:p-4 lg:p-5 rounded-full flex-shrink-0">
                                <i class="fas fa-check text-2xl sm:text-3xl lg:text-3xl text-green-600"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Total Pengajuan</p>
                    </div>

                    <div class="bg-blue-50 rounded-lg sm:rounded-xl shadow-md sm:shadow-lg p-4 sm:p-5 lg:p-6 border-l-4 border-blue-500 h-full">
                        <div class="flex items-center justify-between gap-2 sm:gap-3">
                            <div>
                                <p class="text-gray-600 text-xs sm:text-sm lg:text-base font-medium">Total Pengguna</p>
                                <p id="summary-user" class="text-2xl sm:text-3xl lg:text-4xl font-bold text-blue-600 mt-1"><?= $totalUser ?? 0 ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 sm:p-4 lg:p-5 rounded-full flex-shrink-0">
                                <i class="fas fa-users text-2xl sm:text-3xl lg:text-3xl text-blue-600"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Pengguna Terdaftar</p>
                    </div>
                </div>

                <!-- Grafik Pengajuan Per-Bulan -->
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6 mb-6">
                    <div class="flex flex-col items-center justify-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">Grafik Jumlah Pengajuan Berdasarkan Status</h3>
                        <div class="flex items-center gap-2">
                            <label for="chartYear" class="text-sm text-gray-600">Pilih Tahun:</label>
                            <select id="chartYear" class="border rounded px-3 py-2 text-sm font-medium">
                                <?php 
                                $selectedYear = $_GET['chart_year_ajax'] ?? date('Y');
                                foreach ($chartYears as $yearData): ?>
                                    <option value="<?= $yearData['tahun'] ?>" <?= ($yearData['tahun'] == $selectedYear) ? 'selected' : '' ?>><?= $yearData['tahun'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="pengajuanChart"></canvas>
                    </div>
                </div>


            </main>

        </div>
    </div>
    
    <script>
        // --- SweetAlert2 Notifikasi Popup dari PHP Session ---
        <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Login Berhasil!',
            text: '<?= addslashes($success) ?>',
            timer: 3000,
            showConfirmButton: false,
            timerProgressBar: true
        });
        <?php endif; ?>
    </script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('pengajuanChart');
            if (!ctx) return;

            const canvasCtx = ctx.getContext('2d');
            const monthLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

            const chartConfig = {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [
                        {
                            label: 'Perlu Diproses',
                            data: new Array(12).fill(0),
                            backgroundColor: 'rgba(251, 191, 36, 0.8)', // yellow-400
                            borderColor: 'rgba(251, 191, 36, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Diterima',
                            data: new Array(12).fill(0),
                            backgroundColor: 'rgba(34, 197, 94, 0.8)', // green-500
                            borderColor: 'rgba(34, 197, 94, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 } }
                    }
                }
            };

            const pengajuanChart = new Chart(canvasCtx, chartConfig);

            async function loadChartForYear(year) {
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('chart_year_ajax', year);
                    
                    const res = await fetch(url.toString());
                    if (!res.ok) {
                        console.error('HTTP error, status:', res.status);
                        return;
                    }
                    
                    const data = await res.json();
                    if (data && typeof data === 'object' && data.diproses && data.diterima) {
                        // Perbarui data grafik
                        pengajuanChart.data.datasets[0].data = data.diproses;
                        pengajuanChart.data.datasets[1].data = data.diterima;
                        pengajuanChart.update();

                        // Perbarui juga kotak ringkasan agar sinkron dengan tahun yang dipilih
                        document.getElementById('summary-diproses').textContent = data.total_diproses;
                        document.getElementById('summary-diterima').textContent = data.total_diterima;
                    }
                } catch (err) {
                    console.error('Gagal memuat data chart:', err);
                }
            }

            const yearSelect = document.getElementById('chartYear');
            if (yearSelect) {
                // Load initial data
                loadChartForYear(yearSelect.value);
                
                // Listen for year change
                yearSelect.addEventListener('change', () => {
                    loadChartForYear(yearSelect.value);
                });
            }
        });
    </script>
</body>
</html>