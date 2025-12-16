<?php
require_once '../config.php';
Session::start();

// Cek login dan pastikan admin
if (!Session::isAdmin()) {
    Session::set('error', 'Anda harus login sebagai admin untuk mengakses halaman ini.'); // Baris ini mungkin tidak akan pernah dieksekusi jika redirect bekerja
    redirect('login.php');
}

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();

// Ambil notifikasi dari session
$success = Session::get('success', '');
Session::remove('success');
$error = Session::get('error', '');
Session::remove('error');

// --- LOGIKA AKSI FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $pengajuanId = $_POST['pengajuan_id'] ?? 0;

    // Ambil data pengajuan untuk validasi
    $pengajuan = Database::query("SELECT * FROM pengajuan_surat WHERE pengajuan_id = ?", [$pengajuanId])->fetch();

    if (!$pengajuan) {
        Session::set('error', 'Pengajuan tidak ditemukan.');
        redirect('admin/berkas_masuk.php');
    }

    switch ($action) {
        case 'terima_kasi':
            if ($pengajuan['status'] === 'Diajukan User') { // Status yang benar sudah ada
                Database::query("UPDATE pengajuan_surat SET status = 'Verifikasi Kasi' WHERE pengajuan_id = ?", [$pengajuanId]);
                Session::set('success', 'Pengajuan telah diterima.');
            }
            break;

        case 'tolak':
            $alasan = sanitize($_POST['alasan_penolakan'] ?? 'Tidak ada alasan spesifik.');
            if ($pengajuan['status'] === 'Diajukan User') { // Penolakan oleh Kasi, status menjadi Ditolak
                Database::query("UPDATE pengajuan_surat SET status = 'Ditolak', alasan_penolakan = ? WHERE pengajuan_id = ?", [$alasan, $pengajuanId]);
                Session::set('success', 'Pengajuan telah ditolak dan dikembalikan ke pengguna.');
            }
            break;
    }
    // Redirect untuk membersihkan POST request. Default ke berkas_masuk.php jika tidak ada URL spesifik.
    if (isset($_POST['redirect_url'])) {
        redirect($_POST['redirect_url']);
    } else {
        $queryParams = http_build_query([
            'page' => $_POST['current_page'] ?? 1
        ]);
        redirect('admin/berkas_masuk.php?' . $queryParams);
    }
}

// Query untuk mengambil data berkas masuk berdasarkan peran
$sql = "SELECT p.*, u.nama as nama_pengaju, u.nik, js.nama_surat
        FROM pengajuan_surat p
        JOIN user u ON p.user_id = u.user_id
        LEFT JOIN jenis_surat js ON p.id_jenis_surat = js.id_jenis_surat
        WHERE 1=1";
$pageTitle = "Berkas Masuk";
$pageDescription = "Daftar pengajuan surat yang perlu diverifikasi.";
$sql .= " AND p.status = 'Diajukan User'"; // Filter yang benar sudah ada

$sql .= " ORDER BY p.tgl_pengajuan DESC";
$pengajuan = Database::query($sql)->fetchAll();

// --- DATA DUMMY UNTUK TESTING PAGINATION ---
$use_dummy_data = false; // Ganti menjadi false untuk menonaktifkan
if ($use_dummy_data && count($pengajuan) < 10) {
    for ($i = 1; $i <= 50; $i++) {
        $pengajuan[] = [
            'pengajuan_id' => 80000 + $i,
            'nama_surat' => 'Surat Pengantar Dummy ' . $i,
            'user_id' => 4000 + $i,
            'nama_pengaju' => 'Pengaju Dummy ' . $i,
            'email_pengaju' => 'pengaju' . $i . '@example.com',
            'no_telepon' => '089876543210',
            'alamat' => 'Jl. Percobaan No. ' . $i,
            'tgl_pengajuan' => date('Y-m-d H:i:s', strtotime("-$i days")),
            'status' => 'Diajukan User',
            'foto_ktp' => '',
            'foto_kk' => '',
            'foto_formulir' => '',
            'foto_lainnya' => '',
            'file_surat' => '',
            'keterangan' => 'Data dummy berkas masuk.',
            'alasan_penolakan' => ''
        ];
    }
}
// -------------------------------------------

// --- LOGIKA UNTUK MENCEGAH KEDIPAN SIDEBAR (TERPUSAT) ---
$body_class = '';
if (isset($_COOKIE['sidebarOpen']) && $_COOKIE['sidebarOpen'] === 'false') { $body_class .= ' sidebar-closed'; }
$body_class .= ' no-transition'; // Selalu tambahkan no-transition saat load
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <link rel="apple-touch-icon" href="../assets/img/logopky.gif">

    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Sembunyikan scrollbar tapi tetap fungsional */
        html {
            overflow-y: scroll; /* Mencegah kedipan/layout shift */
            scrollbar-width: none; /* Untuk Firefox */
            -ms-overflow-style: none;  /* Untuk Internet Explorer dan Edge */
        }
        html::-webkit-scrollbar {
            width: 0;  /* Untuk Chrome, Safari, dan Opera */
            height: 0;
        }
        .preview-img { max-width: 100%; max-height: 60vh; object-fit: contain; }
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
        <!-- Sidebar -->
        <?php require_once 'sidebar_admin.php'; ?>

        <!-- Main Content -->
        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <?php require_once 'navbar_admin.php'; ?>

            <main class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8 flex-grow">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($pageTitle) ?></h2>
                            <p class="text-sm text-gray-500 mb-2"><?= htmlspecialchars($pageDescription) ?></p>
                        </div>
                    </div>

                    <!-- Filter dan Search -->
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 pb-4 border-b border-gray-200 gap-4">
                        <div class="flex items-center space-x-2">
                            <label for="tableEntries" class="text-sm font-medium text-gray-700">Tampilkan</label>
                            <select id="tableEntries" onchange="changeEntries()" class="border border-gray-300 rounded-lg shadow-sm text-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="-1">Semua</option>
                            </select>
                            <span class="text-sm font-medium text-gray-700">data</span>
                        </div>
                        <div class="relative w-full lg:w-auto">
                            <input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="Cari nama, NIK, atau jenis surat..."
                                class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <?php if (count($pengajuan) > 0): ?>
                    <div id="tableContainer" class="overflow-x-auto relative border rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200" id="pengajuanTable">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                            <?php $no = 1; ?>
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                                <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jenis Surat</th>
                                <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama</th>
                                <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No Telepon</th>
                                <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Alamat</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Tgl Pengajuan</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">KTP</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">KK</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Formulir</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Lainnya</th>
                                <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Keterangan</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($pengajuan as $p): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4 text-sm text-gray-800 truncate text-center align-middle"></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['nama_surat'] ?? 'N/A') ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-800 truncate text-center align-middle"><?= htmlspecialchars($p['user_id'] ?? '') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['nama_pengaju'] ?? '') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['email_pengaju'] ?? '') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['no_telepon'] ?? '') ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-800 truncate" style="max-width: 150px;"><?= htmlspecialchars($p['alamat'] ?? '-') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500 text-center align-middle"><?= formatTanggal($p['tgl_pengajuan'], 'd M Y') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <?php if (!empty($p['foto_ktp'])): ?><button onclick='showDocumentPreview("<?= htmlspecialchars($p['foto_ktp']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button><?php else: echo '-'; endif; ?>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <?php if (!empty($p['foto_kk'])): ?><button onclick='showDocumentPreview("<?= htmlspecialchars($p['foto_kk']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button><?php else: echo '-'; endif; ?>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <?php if (!empty($p['foto_formulir'])): ?><button onclick='showDocumentPreview("<?= htmlspecialchars($p['foto_formulir']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button><?php else: echo '-'; endif; ?>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center">
                                        <?php if (!empty($p['foto_lainnya'])): ?><button onclick='showDocumentPreview("<?= htmlspecialchars($p['foto_lainnya']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button><?php else: echo '-'; endif; ?>
                                    </td>
                                    <td class="px-3 py-4 text-sm text-gray-800 truncate" style="max-width: 200px;"><?= !empty($p['keterangan']) ? htmlspecialchars($p['keterangan']) : '-' ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle">
                                        <?php
                                            $status = $p['status'];
                                            $statusText = 'Tidak Diketahui';
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            $isLoading = false;
                                            $isSuccess = false;
                                            $isRejected = false;

                                            switch ($status) {
                                                case 'Diajukan User': // Status yang benar sudah ada
                                                    $statusText = 'Menunggu Konfirmasi Kasi';
                                                    $statusClass = 'bg-blue-100 text-blue-800';
                                                    $isLoading = true;
                                                    break;
                                                case 'selesai':
                                                    $statusText = 'Selesai';
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    $isSuccess = true;
                                                    break;
                                                case 'Ditolak':
                                                    $statusText = 'Ditolak';
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    $isRejected = true;
                                                    break;
                                                case 'Verifikasi Lurah':
                                                    $statusText = 'Verifikasi Lurah';
                                                    $statusClass = 'bg-purple-100 text-purple-800';
                                                    $isLoading = true;
                                                    break;
                                                case 'Diajukan Kasi':
                                                    $statusText = 'Menunggu Konfirmasi Lurah';
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    $isLoading = true;
                                                    break;
                                                default:
                                                    $statusText = ucfirst($status);
                                                    break;
                                            }
                                        ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                            <?php if ($isLoading): ?>
                                                <span class="mr-2"><i class="fas fa-spinner animate-spin"></i></span>
                                                <span><?= $statusText ?></span>
                                            <?php elseif ($isSuccess): ?>
                                                <span class="mr-2"><i class="fas fa-check-circle"></i></span>
                                                <span><?= $statusText ?></span>
                                            <?php elseif ($isRejected): ?>
                                                <span class="mr-2"><i class="fas fa-times-circle"></i></span>
                                                <span><?= $statusText ?></span>
                                            <?php else: ?>
                                                <?= $statusText ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button onclick="showModal('modalDetail', <?= htmlspecialchars(json_encode($p)) ?>)" title="Lihat Detail" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-eye mr-1"></i>Detail</button>
                                             <?php if ($p['status'] === 'Diajukan User' && $admin['role'] !== 'super_admin'): ?>
                                                <button onclick="confirmAction(<?= $p['pengajuan_id'] ?>, 'terima_kasi', 'Terima Pengajuan?', 'Pengajuan akan diteruskan untuk diproses lebih lanjut.')" title="Terima Pengajuan" class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-check mr-1"></i>Terima</button>
                                                <button onclick="rejectModal(<?= $p['pengajuan_id'] ?>)" title="Tolak Pengajuan" class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-times mr-1"></i>Tolak</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table> 
                    </div>
                    <!-- Kontrol Paginasi -->
                    <div id="paginationContainer" class="flex flex-col sm:flex-row justify-between items-center pt-4 mt-4 border-t border-gray-200 gap-4 sm:gap-0">
                        <div id="showingInfo" class="text-sm text-gray-700 text-center sm:text-left"></div>
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
                    <div id="noResultsMessage" class="hidden text-center py-12 text-gray-500">
                        <i class="fas fa-search text-5xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">Data Tidak Ditemukan</h3>
                        <p>Tidak ada berkas masuk yang cocok dengan pencarian Anda.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-16 text-gray-500">
                        <i class="fas fa-inbox text-6xl mb-4"></i>
                        <p class="text-xl font-semibold">Tidak ada berkas</p>
                        <?php if ($admin['role'] === 'super_admin'): ?>
                            <p>Tidak ada berkas yang menunggu tindakan dari Anda saat ini.</p>
                        <?php else: ?>
                            <p>Tidak ada berkas baru yang perlu diverifikasi. Semua sudah diteruskan.</p>
                        <?php endif; ?>
                    </div>

                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Preview Dokumen -->
    <div id="documentPreviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-screen flex flex-col">
            <div class="relative flex justify-center items-center mb-4"><h3 class="text-xl font-bold text-center">Preview Dokumen</h3><button onclick="closeModal('documentPreviewModal')" class="absolute right-0 text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button></div>
            <div id="documentPreviewContent" class="flex-grow overflow-auto text-center mb-4"></div>
            <div class="flex justify-end items-center gap-4 border-t pt-4">
                <a id="downloadButton" href="#" download target="_blank" class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700"><i class="fas fa-download mr-2"></i>Unduh Dokumen</a>
                <button type="button" onclick="closeModal('documentPreviewModal')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Kembali</button>
            </div>
        </div>
    </div>


    <!-- Modal Detail -->
    <div id="modalDetail" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-xl font-bold text-gray-800">Detail Pengajuan</h3>
                <button onclick="closeModal('modalDetail')" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <div id="detailContent" class="space-y-3"></div>
            <div class="mt-6 flex justify-end"><button type="button" onclick="closeModal('modalDetail')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Tutup</button></div>
        </div>
    </div>

    <!-- Modal Tolak Pengajuan -->
    <div id="tolakModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold mb-4">Tolak Pengajuan</h3>
            <form method="POST">
                <input type="hidden" name="action" value="tolak">
                <input type="hidden" name="pengajuan_id" id="tolak_pengajuan_id">
                <input type="hidden" name="current_page" id="tolak_current_page">
                <label for="alasan_penolakan" class="block font-semibold mb-2">Alasan Penolakan</label>
                <textarea name="alasan_penolakan" id="alasan_penolakan" rows="4" maxlength="500" class="w-full px-4 py-2 border rounded-lg" required placeholder="Jelaskan alasan penolakan..."></textarea>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">Tolak Pengajuan</button>
                    <button type="button" onclick="closeModal('tolakModal')" class="flex-1 bg-gray-300 text-gray-800 py-2 rounded-lg hover:bg-gray-400">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Upload Surat -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full">
            <h3 class="text-xl font-bold mb-4">Upload Surat Selesai</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_surat">
                <input type="hidden" name="pengajuan_id" id="upload_pengajuan_id">
                <label for="file_surat" class="block font-semibold mb-2">Pilih File Surat (PDF)</label>
                <input type="file" name="file_surat" id="file_surat" accept=".pdf,.doc,.docx" required class="w-full px-4 py-2 border rounded-lg">
                <p class="text-xs text-gray-500 mt-1">File ini akan dapat diunduh oleh pengguna.</p>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Upload File</button>
                    <button type="button" onclick="closeModal('uploadModal')" class="flex-1 bg-gray-300 text-gray-800 py-2 rounded-lg hover:bg-gray-400">Batal</button>
                </div>
            </form>
        </div>
    </div>




    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        // SweetAlert2 Notifikasi
        <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= addslashes($success) ?>', timer: 3000, showConfirmButton: false });
        <?php endif; ?>
        <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= addslashes($error) ?>' });
        <?php endif; ?>

        // --- Logika Modal ---
        function showModal(modalId, data = null) {
            const modal = document.getElementById(modalId);
            if (modal) {
                if (modalId === 'modalDetail' && data) {
                    const content = `
                        <div><p class="font-semibold">Jenis Surat:</p><p class="p-2 bg-gray-50 rounded">${data.nama_surat || '-'}</p></div>
                        <div><p class="font-semibold">Nama Pengaju:</p><p class="p-2 bg-gray-50 rounded">${data.nama_pengaju || '-'}</p></div>
                        <div><p class="font-semibold">Tanggal:</p><p class="p-2 bg-gray-50 rounded">${new Date(data.tgl_pengajuan).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</p></div>
                        <div><p class="font-semibold">Alamat:</p><p class="p-2 bg-gray-50 rounded break-words">${data.alamat || '-'}</p></div>
                        ${data.keterangan ? `<div><p class="font-semibold">Keterangan:</p><p class="p-2 bg-gray-50 rounded break-words">${data.keterangan}</p></div>` : ''}
                        ${data.alasan_penolakan ? `<div><p class="font-semibold text-red-600">Alasan Penolakan:</p><p class="p-2 bg-red-50 rounded border-l-4 border-red-500">${data.alasan_penolakan}</p></div>` : ''}
                        ${data.file_surat ? `<div><p class="font-semibold text-green-600">Surat Selesai:</p><a href="../uploads/${data.file_surat}" download class="text-green-600 hover:underline"><i class="fas fa-download mr-1"></i>Unduh Surat</a></div>` : ''}
                    `;
                    document.getElementById('detailContent').innerHTML = content;
                } else if (modalId === 'tolakModal' && data) {
                    document.getElementById('tolak_pengajuan_id').value = data;
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentPage = urlParams.get('page') || '1';
                    document.getElementById('tolak_current_page').value = currentPage;
                } else if (modalId === 'uploadModal' && data) {
                    document.getElementById('upload_pengajuan_id').value = data;
                }
                modal.classList.remove('hidden');
            }
        }

        function showDocumentPreview(fileName, data = null) {
            const modal = document.getElementById('documentPreviewModal');
            const contentArea = document.getElementById('documentPreviewContent');
            const downloadBtn = document.getElementById('downloadButton');
            const fileUrl = `../uploads/${fileName}`;
            const fileExtension = fileName.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                contentArea.innerHTML = `<img src="${fileUrl}" alt="Preview Dokumen" class="preview-img mx-auto">`;
            } else if (fileExtension === 'pdf') {
                contentArea.innerHTML = `<iframe src="${fileUrl}" width="100%" height="500px"></iframe>`;
            } else {
                contentArea.innerHTML = `<div class="text-center p-8"><i class="fas fa-file-alt text-6xl text-gray-400"></i><p class="mt-4">Preview tidak tersedia.</p></div>`;
            }

            let downloadFileName = fileName; // Default
            if (data) {
                const fileExtension = fileName.split('.').pop();
                if (fileName === data.foto_ktp) {
                    downloadFileName = `KTP - ${data.nama_pengaju}.${fileExtension}`;
                } else if (fileName === data.foto_kk) {
                    downloadFileName = `KK - ${data.nama_pengaju}.${fileExtension}`;
                } else if (fileName === data.foto_formulir) {
                    downloadFileName = `Formulir - ${data.nama_pengaju}.${fileExtension}`;
                } else if (fileName === data.foto_lainnya) {
                    downloadFileName = `Lampiran Lainnya - ${data.nama_pengaju}.${fileExtension}`;
                }
            }

            downloadBtn.href = fileUrl;
            downloadBtn.download = downloadFileName;
            modal.classList.remove('hidden');
        }

        function confirmAction(id, action, title, text) {
            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Lanjutkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentPage = urlParams.get('page') || '1';

                    form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="pengajuan_id" value="${id}"><input type="hidden" name="current_page" value="${currentPage}">`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function rejectModal(id, action = 'tolak', title = 'Tolak Pengajuan', text = 'Tuliskan alasan mengapa pengajuan ini ditolak...') {
            Swal.fire({
                title: title,
                input: 'textarea',
                inputLabel: 'Alasan Penolakan',
                inputPlaceholder: text,
                inputAttributes: { 'aria-label': 'Tuliskan alasan penolakan' },
                showCancelButton: true,
                confirmButtonText: 'Tolak',
                confirmButtonColor: '#d33',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    if (!value) { return 'Anda harus memberikan alasan penolakan!' }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentPage = urlParams.get('page') || '1';

                    form.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="pengajuan_id" value="${id}"><input type="hidden" name="alasan_penolakan" value="${result.value}"><input type="hidden" name="current_page" value="${currentPage}">`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // --- LOGIKA TABEL, PAGINASI, SEARCH (diadaptasi dari users.php) ---
        const tableBody = document.getElementById('tableBody');
        const allRows = tableBody ? Array.from(tableBody.getElementsByTagName('tr')) : [];
        let filteredRows = [...allRows];
        let currentPage = 1;
        let entriesPerPage = 10;

        function updatePaginationControls() {
            const totalFiltered = filteredRows.length;
            const currentEntriesPerPage = (entriesPerPage === -1) ? totalFiltered : entriesPerPage;
            const totalPages = Math.ceil(totalFiltered / currentEntriesPerPage);
            const startEntry = (currentPage - 1) * currentEntriesPerPage + 1;
            const endEntry = Math.min(currentPage * currentEntriesPerPage, totalFiltered);

            const showingInfo = document.getElementById('showingInfo');
            const pageButtonsContainer = document.getElementById('pageButtons');
            const paginationControls = document.getElementById('paginationControls');

            if (totalFiltered === 0) {
                if (showingInfo) showingInfo.textContent = 'Menampilkan 0 dari 0 data';
                if (paginationControls) paginationControls.classList.add('hidden');
                return;
            }
            
            if (totalPages <= 1 || entriesPerPage === -1) {
                if (paginationControls) paginationControls.classList.add('hidden');
                if (showingInfo) showingInfo.innerHTML = `Menampilkan ${totalFiltered} dari ${totalFiltered} data`;
            } else {
                if (paginationControls) paginationControls.classList.remove('hidden');
                if (showingInfo) showingInfo.innerHTML = `Menampilkan ${startEntry} hingga ${endEntry} dari ${totalFiltered} data`;
            }

            if (document.getElementById('prevBtn')) document.getElementById('prevBtn').disabled = currentPage === 1;
            if (document.getElementById('nextBtn')) document.getElementById('nextBtn').disabled = currentPage === totalPages;
            
            if (pageButtonsContainer) {
                pageButtonsContainer.innerHTML = '';
                if (totalPages > 1) {
                    let startPage = Math.max(1, currentPage - 2);
                    let endPage = Math.min(totalPages, currentPage + 2);
                    if (currentPage <= 3) endPage = Math.min(5, totalPages);
                    if (currentPage > totalPages - 3) startPage = Math.max(1, totalPages - 4);

                    for (let i = startPage; i <= endPage; i++) {
                        const pageButton = document.createElement('button');
                        pageButton.textContent = i;
                        pageButton.onclick = () => changePage(i);
                        pageButton.className = `px-3 py-1 border rounded-lg text-sm transition-colors ${i === currentPage ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100'}`;
                        pageButtonsContainer.appendChild(pageButton);
                    }
                }
            }
        }

        function applyFilters() {
            const searchText = document.getElementById('tableSearch').value.toLowerCase();
            const tableContainer = document.getElementById('tableContainer');
            const noResultsMessage = document.getElementById('noResultsMessage');
            const paginationContainer = document.getElementById('paginationContainer');

            filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(searchText));
            
            const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : entriesPerPage;
            const maxPage = Math.max(1, Math.ceil(filteredRows.length / currentEntriesPerPage));
            if (currentPage > maxPage) currentPage = 1;

            if (filteredRows.length === 0) {
                if(tableContainer) tableContainer.classList.add('hidden');
                if(paginationContainer) paginationContainer.classList.add('hidden');
                if(noResultsMessage) noResultsMessage.classList.remove('hidden');
            } else {
                if(tableContainer) tableContainer.classList.remove('hidden');
                if(paginationContainer) paginationContainer.classList.remove('hidden');
                if(noResultsMessage) noResultsMessage.classList.add('hidden');
                const start = (currentPage - 1) * currentEntriesPerPage;
                const end = start + currentEntriesPerPage;
                tableBody.innerHTML = ''; // Kosongkan tbody
                filteredRows.slice(start, end).forEach((row, index) => {
                    row.cells[0].textContent = start + index + 1;
                    tableBody.appendChild(row);
                });
            }
            updatePaginationControls();
        }

        window.changeEntries = function() {
            entriesPerPage = parseInt(document.getElementById('tableEntries').value);
            currentPage = 1;
            applyFilters();
        };

        window.filterTable = function() {
            currentPage = 1;
            applyFilters();
        };

        window.changePage = function(newPage) {
            const totalPages = Math.ceil(filteredRows.length / ((entriesPerPage === -1) ? filteredRows.length : entriesPerPage));
            if (newPage < 1 || newPage > totalPages) return;
            currentPage = newPage;
            applyFilters();
        };

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('tableEntries')) {
                applyFilters();
            }
            // Panggil applyFilters untuk render awal, termasuk penomoran
            applyFilters();
        });

    </script>
</body>
</html>
