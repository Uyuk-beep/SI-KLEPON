<?php
require_once '../config.php';
Session::start();

// Cek login dan pastikan admin
if (!Session::isAdmin()) {
    Session::set('error', 'Anda harus login sebagai admin untuk mengakses halaman ini.');
    redirect('login.php');
}

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();

// --- PENGAMBILAN NOTIFIKASI SESSION ---
$success = Session::get('success', '');
Session::remove('success');
$error = Session::get('error', '');
Session::remove('error');
// ------------------------------------

// --- LOGIKA AKSI FORM (EDIT & HAPUS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $pengajuanId = $_POST['pengajuan_id'] ?? 0;

    if ($action === 'edit') {
        $idJenisSurat = filter_input(INPUT_POST, 'id_jenis_surat', FILTER_VALIDATE_INT);
        $alamat = sanitize($_POST['alamat'] ?? '');
        $keterangan = sanitize($_POST['keterangan'] ?? '');

        if (empty($idJenisSurat) || empty($alamat) || empty($pengajuanId)) {
            Session::set('error', 'Data tidak lengkap untuk memperbarui pengajuan.');
        } else {
            $currentData = Database::query("SELECT * FROM pengajuan_surat WHERE pengajuan_id = ?", [$pengajuanId])->fetch();
            if (!$currentData) {
                Session::set('error', 'Pengajuan tidak ditemukan.');
            } else {
                $updateError = false;
                $fotoKtp = $currentData['foto_ktp'];
                $fotoKk = $currentData['foto_kk'];
                $fotoFormulir = $currentData['foto_formulir'];
                $fotoLainnya = $currentData['foto_lainnya'];

                function handleReplaceUpload($fileKey, $directory, &$newFileName, $oldFileName, &$error) {
                    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadFile($_FILES[$fileKey], $directory);
                        if ($uploadResult) {
                            if ($oldFileName && file_exists('../uploads/' . $oldFileName)) {
                                unlink('../uploads/' . $oldFileName);
                            }
                            $newFileName = $uploadResult;
                        } else {
                            $error = true;
                            Session::set('error', 'Gagal mengganti file ' . $fileKey);
                        }
                    }
                }

                handleReplaceUpload('edit_foto_ktp', 'ktp', $fotoKtp, $currentData['foto_ktp'], $updateError);
                if (!$updateError) handleReplaceUpload('edit_foto_kk', 'kk', $fotoKk, $currentData['foto_kk'], $updateError);
                if (!$updateError) handleReplaceUpload('edit_foto_formulir', 'formulir', $fotoFormulir, $currentData['foto_formulir'], $updateError);
                if (!$updateError) handleReplaceUpload('edit_foto_lainnya', 'lainnya', $fotoLainnya, $currentData['foto_lainnya'], $updateError);

                if (!$updateError) {
                    $sql = "UPDATE pengajuan_surat SET id_jenis_surat = ?, alamat = ?, keterangan = ?, foto_ktp = ?, foto_kk = ?, foto_formulir = ?, foto_lainnya = ? WHERE pengajuan_id = ?";
                    $params = [$idJenisSurat, $alamat, $keterangan, $fotoKtp, $fotoKk, $fotoFormulir, $fotoLainnya, $pengajuanId];
                    if (Database::query($sql, $params)) {
                        Session::set('success', 'Pengajuan surat berhasil diperbarui.');
                    } else {
                        Session::set('error', 'Gagal memperbarui pengajuan surat.');
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $pengajuanData = Database::query("SELECT foto_ktp, foto_kk, foto_formulir, foto_lainnya FROM pengajuan_surat WHERE pengajuan_id = ?", [$pengajuanId])->fetch();
        if ($pengajuanData) {
            if (Database::query("DELETE FROM pengajuan_surat WHERE pengajuan_id = ?", [$pengajuanId])) {
                if ($pengajuanData['foto_ktp'] && file_exists('../uploads/' . $pengajuanData['foto_ktp'])) unlink('../uploads/' . $pengajuanData['foto_ktp']);
                if ($pengajuanData['foto_kk'] && file_exists('../uploads/' . $pengajuanData['foto_kk'])) unlink('../uploads/' . $pengajuanData['foto_kk']);
                if ($pengajuanData['foto_formulir'] && file_exists('../uploads/' . $pengajuanData['foto_formulir'])) unlink('../uploads/' . $pengajuanData['foto_formulir']);
                if ($pengajuanData['foto_lainnya'] && file_exists('../uploads/' . $pengajuanData['foto_lainnya'])) unlink('../uploads/' . $pengajuanData['foto_lainnya']);
                Session::set('success', 'Pengajuan berhasil dihapus.');
            } else {
                Session::set('error', 'Gagal menghapus pengajuan.');
            }
        }
    } elseif ($action === 'upload_surat') {
        $fileSurat = null;
        $uploadError = false;
        if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['file_surat'], 'surat_selesai');
            if ($uploadResult) {
                $fileSurat = $uploadResult;
            } else {
                $uploadError = true;
                Session::set('error', 'Gagal mengunggah file surat.');
            }
        } else {
            $uploadError = true;
            Session::set('error', 'File surat belum dipilih atau terjadi error.');
        }
        if (!$uploadError) {
            if ($admin['role'] === 'super_admin') {
                // Lurah hanya mengunggah/mengganti file, status tidak berubah
                Database::query("UPDATE pengajuan_surat SET file_surat = ? WHERE pengajuan_id = ?", [$fileSurat, $pengajuanId]);
                Session::set('success', 'File surat berhasil diunggah.');
            } else {
                // Kasi hanya mengunggah file, status tidak berubah
                Database::query("UPDATE pengajuan_surat SET file_surat = ? WHERE pengajuan_id = ?", [$fileSurat, $pengajuanId]);
                Session::set('success', 'File surat berhasil diunggah. Silakan ajukan ke Lurah.');
            }
        }
    } elseif ($action === 'selesaikan_kasi') {
        // Aksi baru untuk Kasi menyelesaikan pengajuan
        if ($admin['role'] === 'admin') {
            Database::query("UPDATE pengajuan_surat SET status = 'Selesai' WHERE pengajuan_id = ?", [$pengajuanId]);
            Session::set('success', 'Pengajuan telah ditandai sebagai Selesai.');
        } 
    }
    // Membangun query string untuk redirect agar state tetap terjaga
    $queryParams = http_build_query([
        'search' => $_POST['current_search'] ?? '',
        'page' => $_POST['current_page'] ?? 1
    ]);
    redirect('admin/pengajuan.php?' . $queryParams);
}

// --- PENGAMBILAN DATA UNTUK TABEL ---
$sql = "SELECT p.*, u.nama as nama_user, u.nik, u.email as email_user, u.no_telepon as telepon_user, js.nama_surat
        FROM pengajuan_surat p
        JOIN user u ON p.user_id = u.user_id
        LEFT JOIN jenis_surat js ON p.id_jenis_surat = js.id_jenis_surat";

// Kasi melihat surat yang perlu diajukan ke lurah atau yang dikembalikan oleh lurah
$whereClauses = ["p.status = 'Verifikasi Kasi'"]; // Filter yang benar sudah ada

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}

$sql .= " ORDER BY p.tgl_pengajuan DESC";

$pengajuanList = Database::query($sql)->fetchAll();
$no = 1; // Inisialisasi nomor urut

// --- DATA DUMMY UNTUK TESTING PAGINATION ---
$use_dummy_data = false; // Ganti menjadi false untuk menonaktifkan
if ($use_dummy_data && count($pengajuanList) < 10) {
    for ($i = 1; $i <= 50; $i++) {
        $pengajuanList[] = [
            'pengajuan_id' => 90000 + $i,
            'nama_surat' => 'Surat Keterangan Dummy ' . $i,
            'user_id' => 5000 + $i,
            'nama_user' => 'Warga Dummy ' . $i,
            'email_user' => 'dummy' . $i . '@example.com',
            'telepon_user' => '081234567890',
            'alamat' => 'Jl. Dummy No. ' . $i,
            'tgl_pengajuan' => date('Y-m-d H:i:s', strtotime("-$i hours")),
            'status' => 'Verifikasi Kasi',
            'foto_ktp' => '',
            'foto_kk' => '',
            'foto_formulir' => '',
            'foto_lainnya' => '',
            'file_surat' => '',
            'keterangan' => 'Ini adalah data dummy untuk testing.',
            'alasan_penolakan' => ''
        ];
    }
}
// -------------------------------------------

// Mengambil SEMUA daftar jenis surat untuk modal edit admin
$jenisSuratOptions = Database::query("SELECT id_jenis_surat, nama_surat FROM jenis_surat ORDER BY nama_surat ASC")->fetchAll();

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
    <title>Manajemen Pengajuan - <?= SITE_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <link rel="apple-touch-icon" href="../assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .preview-img { max-width: 100%; max-height: 60vh; object-fit: contain; }
        /* Menambahkan scrollbar pada dropdown jika opsinya banyak */
        .custom-select {
            max-height: 15rem; /* 240px */
        }
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
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-1">Pengajuan Surat</h2>
                    <p class="text-sm text-gray-500 mb-4">Daftar pengajuan yang telah diterima dan siap untuk diproses lebih lanjut.</p>

                    <!-- Filter dan Search -->
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 pb-4 border-b gap-4">
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
                            <input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="Cari jenis surat atau nama..." class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Tabel Pengajuan -->
                    <?php if (count($pengajuanList) > 0): ?>
                    <div class="w-full"> <!-- Wrapper untuk konsistensi layout -->
                        <div id="tableContainer" class="overflow-x-auto relative border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200" id="pengajuanTable">
                                <thead class="bg-gray-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jenis Surat</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No Telepon</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Alamat</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Tgl Pengajuan</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">KTP</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">KK</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Formulir</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Lainnya</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">File Surat</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Keterangan</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                    <?php foreach ($pengajuanList as $p): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle"></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 align-middle"><?= htmlspecialchars($p['nama_surat'] ?? 'N/A') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle"><?= htmlspecialchars($p['user_id'] ?? '') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['nama_user'] ?? '') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['email_user'] ?? '') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['telepon_user'] ?? '') ?></td>
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
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <?php if (!empty($p['file_surat'])): ?><button onclick='showDocumentPreview("<?= htmlspecialchars($p['file_surat']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button><?php else: echo '-'; endif; ?>
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-800 truncate" style="max-width: 200px;"><?= !empty($p['keterangan']) ? htmlspecialchars($p['keterangan']) : '-' ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle">
                                            <?php if ($p['status'] === 'Verifikasi Kasi'): ?>
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-cyan-100 text-cyan-800">
                                                    <span class="mr-2"><i class="fas fa-spinner animate-spin"></i></span>
                                                    <span>Verifikasi Kasi</span>
                                                </span>
                                            <?php elseif ($p['status'] === 'Verifikasi Lurah'): ?>
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                                    <span class="mr-2"><i class="fas fa-spinner animate-spin"></i></span>
                                                    <span>Verifikasi Lurah</span>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex items-center justify-center space-x-2">
                                                <button onclick="showModal('modalDetail', <?= htmlspecialchars(json_encode($p)) ?>)" title="Lihat Detail" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-eye mr-1"></i>Detail</button>                                            
                                                
                                                <?php if ($admin['role'] !== 'super_admin' && $p['status'] === 'Verifikasi Kasi'): ?>
                                                    <button onclick="showModal('editModal', <?= htmlspecialchars(json_encode($p)) ?>)" title="Edit Pengajuan" class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-edit mr-1"></i>Edit</button>
                                                    <button onclick="showModal('uploadModal', <?= $p['pengajuan_id'] ?>)" title="Upload File Surat" class="inline-flex items-center px-3 py-2 bg-purple-100 hover:bg-purple-600 text-purple-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-upload mr-1"></i>Upload Surat</button>
                                                    <button onclick="confirmSelesaikan(<?= $p['pengajuan_id'] ?>, 'kasi')" title="Selesaikan Pengajuan" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                        <i class="fas fa-check-circle mr-1"></i>Selesaikan
                                                    </button>
                                                <?php endif; ?>

                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
                        <p>Tidak ada data pengajuan yang cocok dengan pencarian Anda.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-folder-open text-6xl mb-4"></i>
                        <p class="text-lg">Tidak ada data pengajuan yang ditemukan.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
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

    <!-- Modal Edit Pengajuan -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Edit Pengajuan Surat</h3>
                <button onclick="closeModal('editModal')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div> 
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="pengajuan_id" id="edit_pengajuan_id">
                <input type="hidden" name="current_page" id="edit_current_page">
                <div class="space-y-4">
                    <div>
                        <label for="edit_jenis_surat" class="block font-semibold mb-2">Jenis Surat <span class="text-red-500">*</span></label>
                        <select name="id_jenis_surat" id="edit_jenis_surat" required class="w-full px-4 py-2 border rounded-lg bg-white custom-select" size="1">
                            <?php foreach ($jenisSuratOptions as $opsi): ?>
                                <option value="<?= htmlspecialchars($opsi['id_jenis_surat']) ?>"><?= htmlspecialchars($opsi['nama_surat']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="edit_alamat" class="block font-semibold mb-2">Alamat <span class="text-red-500">*</span></label>
                        <input type="text" name="alamat" id="edit_alamat" required maxlength="255" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_keterangan" class="block font-semibold mb-2">Keterangan (Opsional)</label>
                        <textarea name="keterangan" id="edit_keterangan" rows="3" maxlength="500" class="w-full px-4 py-2 border rounded-lg"></textarea>
                    </div>
                    <hr class="my-4">
                    <p class="text-sm text-gray-600 mb-2">Ganti file lampiran (kosongkan jika tidak ingin diubah):</p>
                    <div><label class="block font-semibold mb-2 text-sm">Ganti Foto KTP</label><input type="file" name="edit_foto_ktp" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block font-semibold mb-2 text-sm">Ganti Foto KK</label><input type="file" name="edit_foto_kk" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block font-semibold mb-2 text-sm">Ganti Foto Formulir</label><input type="file" name="edit_foto_formulir" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg"></div>
                    <div><label class="block font-semibold mb-2 text-sm">Ganti Foto Lainnya</label><input type="file" name="edit_foto_lainnya" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg"></div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Simpan</button>
                    <button type="button" onclick="closeModal('editModal')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Upload Surat -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full">
            <h3 class="text-xl font-bold mb-4" id="uploadModalTitle">Upload File Surat</h3>
            <form method="POST" action="pengajuan.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_surat">
                <input type="hidden" name="pengajuan_id" id="upload_pengajuan_id">
                <input type="hidden" name="current_page" id="upload_current_page">
                <label for="file_surat" class="block font-semibold mb-2">Pilih File Surat (Hanya PDF)</label>
                <input type="file" name="file_surat" id="file_surat" accept=".pdf" required class="w-full px-4 py-2 border rounded-lg">
                <p class="text-xs text-gray-500 mt-1">File ini akan dapat dilihat dan diunduh oleh Lurah.</p>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Upload File</button>
                    <button type="button" onclick="closeModal('uploadModal')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">Batal</button>
                </div>
            </form>
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- SweetAlert2 Notifikasi ---
        <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= addslashes($success) ?>', timer: 3000, showConfirmButton: false });
        <?php endif; ?>
        <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= addslashes($error) ?>', timer: 5000, showConfirmButton: true });
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
                    `;
                    document.getElementById('detailContent').innerHTML = content; 
                } else if (modalId === 'editModal' && data) {
                    document.getElementById('edit_pengajuan_id').value = data.pengajuan_id;
                    document.getElementById('edit_alamat').value = data.alamat;
                    document.getElementById('edit_keterangan').value = data.keterangan;
                    // Menyimpan state halaman saat ini
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentPage = urlParams.get('page') || '1';
                    document.getElementById('edit_current_page').value = currentPage;

                    // Atur nilai dropdown jenis surat dengan cara yang lebih andal
                    const selectElement = document.getElementById('edit_jenis_surat');
                    const targetIdJenisSurat = data.id_jenis_surat;
                    selectElement.value = targetIdJenisSurat;
                    if (selectElement.value != targetIdJenisSurat) {
                        selectElement.value = ""; // Jika tidak ditemukan, kosongkan
                    }

                } else if (modalId === 'uploadModal' && data) {
                    document.getElementById('upload_pengajuan_id').value = data;
                    const form = modal.querySelector('form');
                    const title = document.getElementById('uploadModalTitle');
                    form.action = 'pengajuan.php'; // Tetap di halaman ini untuk Kasi
                    title.textContent = 'Upload File Surat';
                    // Menyimpan state halaman saat ini
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentPage = urlParams.get('page') || '1';
                    document.getElementById('upload_current_page').value = currentPage;
                }
                modal.classList.remove('hidden');
            }
        }

        function confirmSelesaikan(id, role) {
            Swal.fire({
                title: 'Selesaikan Pengajuan?',
                text: "Anda yakin ingin menandai pengajuan ini sebagai 'Selesai'? Pengajuan akan dipindahkan ke arsip.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Selesaikan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'pengajuan.php';                    
                    form.innerHTML = `<input type="hidden" name="action" value="selesaikan_kasi"><input type="hidden" name="pengajuan_id" value="${id}">`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function showDocumentPreview(fileName, data = null, docType = 'Dokumen') {
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
                if (fileName === data.file_surat) {
                    downloadFileName = `${data.nama_surat} - ${data.nama_user}.${fileExtension}`;
                } else if (fileName === data.foto_ktp) {
                    downloadFileName = `KTP - ${data.nama_user}.${fileExtension}`;
                } else if (fileName === data.foto_kk) {
                    downloadFileName = `KK - ${data.nama_user}.${fileExtension}`;
                } else if (fileName === data.foto_formulir) {
                    downloadFileName = `Formulir - ${data.nama_user}.${fileExtension}`;
                } else if (fileName === data.foto_lainnya) {
                    downloadFileName = `Lampiran Lainnya - ${data.nama_user}.${fileExtension}`;
                }
            }

            downloadBtn.href = fileUrl;
            downloadBtn.download = downloadFileName;
            modal.classList.remove('hidden');
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

        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('tableEntries')) {
                applyFilters();
            }
            // Panggil applyFilters untuk render awal, termasuk penomoran
            applyFilters();
        });
    </script>
</body>
</html>