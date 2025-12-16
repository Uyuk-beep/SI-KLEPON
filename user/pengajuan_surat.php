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
$error = Session::get('error', '');
Session::remove('error');
// ------------------------------------

$userId = Session::getUserId();

// --- AMBIL DATA USER & INISIAL ---
try {
    $user = Database::query("SELECT * FROM user WHERE user_id = ?", [$userId])->fetch();
    if (!$user) {
        Session::destroy();
        Session::set('error', 'Akun pengguna tidak ditemukan.');
        redirect('login.php');
    }
} catch (PDOException $e) {
    Session::set('error', 'Terjadi masalah database. Silakan coba lagi.');
    redirect('login.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $idJenisSurat = filter_input(INPUT_POST, 'id_jenis_surat', FILTER_VALIDATE_INT);
    $alamat = sanitize($_POST['alamat'] ?? '');
    $keterangan = sanitize($_POST['keterangan'] ?? ''); // Opsional

    if (empty($idJenisSurat) || empty($alamat)) {
        Session::set('error', 'Jenis surat dan Alamat harus diisi.');
    } else {
        $fotoKtp = null;
        $fotoKk = null;
        $fotoFormulir = null;
        $fotoLainnya = null;
        $uploadError = false;

        // Fungsi untuk menangani upload file
        function handleUpload($fileKey, $directory, &$fileName, &$error) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES[$fileKey], $directory);
                if ($uploadResult) {
                    $fileName = $uploadResult;
                } else {
                    $error = true;
                    Session::set('error', 'Gagal mengunggah file ' . $fileKey);
                }
            }
        }

        handleUpload('foto_ktp', 'ktp', $fotoKtp, $uploadError);
        if (!$uploadError) handleUpload('foto_kk', 'kk', $fotoKk, $uploadError);
        if (!$uploadError) handleUpload('foto_formulir', 'formulir', $fotoFormulir, $uploadError);
        if (!$uploadError) handleUpload('foto_lainnya', 'lainnya', $fotoLainnya, $uploadError);

        if (!$uploadError) {
            $nomorPengajuan = 'P' . date('YmdHis') . $userId;
            $sql = "INSERT INTO pengajuan_surat
                        (user_id, id_jenis_surat, nama_pengaju, email_pengaju, no_telepon, alamat, keterangan, foto_ktp, foto_kk, foto_formulir, foto_lainnya, tgl_pengajuan, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Menunggu')";
            
            // Menggunakan data user yang login dan input form
            $params = [$userId, $idJenisSurat, $user['nama'], $user['email'], $user['no_telepon'], $alamat, $keterangan, $fotoKtp, $fotoKk, $fotoFormulir, $fotoLainnya];
            
            if (Database::query($sql, $params)) {
                Session::set('success', 'Pengajuan surat berhasil dibuat.');
            } else {
                Session::set('error', 'Gagal membuat pengajuan surat.');
            }
        }
    }
    // Membangun query string untuk redirect agar state tetap terjaga
    $queryParams = http_build_query([
        'search' => $_POST['current_search'] ?? '',
        'page' => $_POST['current_page'] ?? 1
    ]);
    redirect('user/pengajuan_surat.php?' . $queryParams);
}

// --- LOGIKA AJUKAN SURAT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_to_admin') {
    $pengajuanId = $_POST['pengajuan_id'] ?? 0;

    // Pastikan pengajuan milik user yang login dan statusnya valid
    $pengajuan = Database::query("SELECT status FROM pengajuan_surat WHERE pengajuan_id = ? AND user_id = ?", [$pengajuanId, $userId])->fetch();

    if ($pengajuan && ($pengajuan['status'] === 'Menunggu' || $pengajuan['status'] === 'Ditolak')) {
        // Saat diajukan ulang, hapus alasan penolakan sebelumnya
        Database::query("UPDATE pengajuan_surat SET status = 'Diajukan User', alasan_penolakan = NULL WHERE pengajuan_id = ?", [$pengajuanId]);
        Session::set('success', 'Pengajuan berhasil diajukan ke Kasi.');
    } else {
        Session::set('error', 'Gagal mengajukan surat. Pengajuan tidak ditemukan atau status tidak valid.');
    }
    // Membangun query string untuk redirect agar state tetap terjaga
    $queryParams = http_build_query([
        'search' => $_POST['current_search'] ?? '',
        'page' => $_POST['current_page'] ?? 1
    ]);
    redirect('user/pengajuan_surat.php?' . $queryParams);
}

// --- LOGIKA EDIT PENGAJUAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $pengajuanId = $_POST['pengajuan_id'] ?? 0;
    $idJenisSurat = filter_input(INPUT_POST, 'id_jenis_surat', FILTER_VALIDATE_INT);
    $alamat = sanitize($_POST['alamat'] ?? '');
    $keterangan = sanitize($_POST['keterangan'] ?? '');

    if (empty($idJenisSurat) || empty($alamat) || empty($pengajuanId)) {
        Session::set('error', 'Data tidak lengkap untuk memperbarui pengajuan.');
    } else {
        // Ambil data pengajuan yang ada, pastikan milik user yang login
        $currentData = Database::query(
            "SELECT * FROM pengajuan_surat WHERE pengajuan_id = ? AND user_id = ?",
            [$pengajuanId, $userId]
        )->fetch();

        if (!$currentData) {
            Session::set('error', 'Pengajuan tidak ditemukan atau Anda tidak memiliki akses.');
        } else {
            $updateError = false;
            $fotoKtp = $currentData['foto_ktp'];
            $fotoKk = $currentData['foto_kk'];
            $fotoFormulir = $currentData['foto_formulir'];
            $fotoLainnya = $currentData['foto_lainnya'];

            // Fungsi untuk menangani penggantian file
            function handleReplaceUpload($fileKey, $directory, &$newFileName, $oldFileName, &$error) {
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadFile($_FILES[$fileKey], $directory);
                    if ($uploadResult) {
                        // Hapus file lama jika ada
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
                $sql = "UPDATE pengajuan_surat SET 
                            id_jenis_surat = ?, alamat = ?, keterangan = ?, 
                            foto_ktp = ?, foto_kk = ?, foto_formulir = ?, foto_lainnya = ?
                        WHERE pengajuan_id = ? AND user_id = ?";
                
                $params = [$idJenisSurat, $alamat, $keterangan, $fotoKtp, $fotoKk, $fotoFormulir, $fotoLainnya, $pengajuanId, $userId];
                
                if (Database::query($sql, $params)) {
                    Session::set('success', 'Pengajuan surat berhasil diperbarui.');
                } else {
                    Session::set('error', 'Gagal memperbarui pengajuan surat.');
                }
            }
        }
    }
    // Membangun query string untuk redirect agar state tetap terjaga
    $queryParams = http_build_query([
        'search' => $_POST['current_search'] ?? '',
        'page' => $_POST['current_page'] ?? 1
    ]);
    redirect('user/pengajuan_surat.php?' . $queryParams);
}
// --- LOGIKA HAPUS PENGAJUAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $pengajuanId = $_POST['pengajuan_id'] ?? 0;

    // Ambil data file untuk dihapus dari server
    $pengajuanData = Database::query(
        "SELECT foto_ktp, foto_kk, foto_formulir, foto_lainnya FROM pengajuan_surat WHERE pengajuan_id = ? AND user_id = ?",
        [$pengajuanId, $userId]
    )->fetch();

    if ($pengajuanData) {
        // Hapus record dari database
        $deleteQuery = Database::query("DELETE FROM pengajuan_surat WHERE pengajuan_id = ?", [$pengajuanId]);

        if ($deleteQuery) {
            // Hapus file fisik jika ada
            if ($pengajuanData['foto_ktp'] && file_exists('../uploads/' . $pengajuanData['foto_ktp'])) unlink('../uploads/' . $pengajuanData['foto_ktp']);
            if ($pengajuanData['foto_kk'] && file_exists('../uploads/' . $pengajuanData['foto_kk'])) unlink('../uploads/' . $pengajuanData['foto_kk']);
            if ($pengajuanData['foto_formulir'] && file_exists('../uploads/' . $pengajuanData['foto_formulir'])) unlink('../uploads/' . $pengajuanData['foto_formulir']);
            if ($pengajuanData['foto_lainnya'] && file_exists('../uploads/' . $pengajuanData['foto_lainnya'])) unlink('../uploads/' . $pengajuanData['foto_lainnya']);
            
            Session::set('success', 'Pengajuan berhasil dihapus.');
        } else {
            Session::set('error', 'Gagal menghapus pengajuan.');
        }
    }
    // Membangun query string untuk redirect agar state tetap terjaga
    $queryParams = http_build_query([
        'search' => $_POST['current_search'] ?? '',
        'page' => $_POST['current_page'] ?? 1
    ]);
    redirect('user/pengajuan_surat.php?' . $queryParams);
}
// ------------------------------------

// --- PENGAMBILAN DATA PENGAJUAN ---
$statusFilter = $_GET['status'] ?? 'all';
$searchFilter = $_GET['search'] ?? '';
$sql = "SELECT p.*, js.nama_surat
        FROM pengajuan_surat p
        LEFT JOIN jenis_surat js ON p.id_jenis_surat = js.id_jenis_surat
        WHERE p.user_id = ?";
$params = [$userId];

$sql .= " ORDER BY p.tgl_pengajuan DESC";

// Mengambil daftar jenis surat dari tabel baru
$jenisSuratOptions = Database::query(
    "SELECT id_jenis_surat, nama_surat FROM jenis_surat WHERE is_active = 1 ORDER BY nama_surat ASC"
)->fetchAll();


$pengajuanList = Database::query($sql, $params)->fetchAll();

// --- LOGIKA UNTUK MENCEGAH KEDIPAN SIDEBAR (TERPUSAT) ---
// Meskipun sidebar user tidak collapsible, ini untuk konsistensi
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
    <title>Pengajuan Surat - <?= SITE_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <link rel="apple-touch-icon" href="../assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Style untuk modal preview dokumen */
        .preview-img {
            max-width: 100%;
            max-height: 60vh;
            object-fit: contain;
        }
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
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 md:hidden z-30 pointer-events-none opacity-0 transition-opacity duration-300"></div>

    <div class="flex">
        <?php require_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div id="main-content-wrapper" class="w-full min-h-screen flex flex-col" style="min-width: 0;">
            <?php require_once 'navbar.php'; ?>

            <!-- Page Content -->
            <div class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <!-- Header -->
                    <div class="flex flex-col md:flex-row justify-between md:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800 text-center md:text-left">Pengajuan Surat</h2>
                            <p class="text-sm text-gray-500 mt-1 text-center md:text-left">Buat, kelola, dan lacak status pengajuan surat Anda di sini.</p>
                        </div>
                        <button onclick="showModal('addModal')" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-semibold whitespace-nowrap transition-colors duration-200">
                            <i class="fas fa-plus mr-2"></i>Buat Pengajuan Surat
                        </button>
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
                            <input type="text" id="tableSearch" onkeyup="applyFiltersAndPagination()" placeholder="Cari surat..." class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Tabel Pengajuan -->
                    <?php if (count($pengajuanList) > 0): ?>
                    <!-- Wrapper untuk memastikan overflow bekerja tanpa merusak layout flex parent. -->
                    <div class="w-full">
                      <!-- Kontainer dengan scrollbar horizontal -->
                      <div class="overflow-x-auto relative border rounded-lg" id="table-container">
                        <table class="min-w-full divide-y divide-gray-200" id="pengajuanTable">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr id="tableHeader">
                                <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
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
                            <tbody class="bg-white divide-y divide-gray-200 border-b border-gray-200" id="tableBody">
                                    <?php foreach ($pengajuanList as $p): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle"></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['nama_surat'] ?? 'N/A') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle"><?= htmlspecialchars($p['user_id'] ?? '') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 truncate" style="max-width: 200px;"><?= htmlspecialchars($p['nama_pengaju'] ?? '') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['email_pengaju'] ?? '') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle"><?= htmlspecialchars($p['no_telepon'] ?? '') ?></td>
                                        <td class="px-3 py-4 text-sm text-gray-800 truncate" style="max-width: 150px;"><?= htmlspecialchars($p['alamat'] ?? '-') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500 text-center align-middle"><?= formatTanggal($p['tgl_pengajuan'], 'd M Y') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <?php if (!empty($p['foto_ktp'])): ?>
                                                <button onclick='showDocumentPreview("<?= htmlspecialchars($p['foto_ktp']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button>
                                            <?php else: echo '-'; endif; ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <?php if (!empty($p['foto_kk'])): ?>
                                                <button onclick='showDocumentPreview("<?= htmlspecialchars($p['foto_kk']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button>
                                            <?php else: echo '-'; endif; ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <?php if (!empty($p['foto_formulir'])): ?>
                                                <button onclick='showDocumentPreview("<?= htmlspecialchars($p['foto_formulir']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button>
                                            <?php else: echo '-'; endif; ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <?php if (!empty($p['foto_lainnya'])): ?>
                                                <button onclick='showDocumentPreview("<?= htmlspecialchars($p['foto_lainnya']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button>
                                            <?php else: echo '-'; endif; ?>
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-800 truncate" style="max-width: 200px;"><?= !empty($p['keterangan']) ? htmlspecialchars($p['keterangan']) : '-' ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-center align-middle">
                                            <?php
                                                $status = $p['status'];
                                                $statusText = 'Tidak Diketahui';
                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                $isLoading = false;
                                                $isSuccess = false;
                                                $isRejected = false;
 
                                                switch ($status) {
                                                    case 'Menunggu':
                                                        $statusText = 'Menunggu Diajukan';
                                                        $statusClass = 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'Diajukan User':
                                                        $statusText = 'Menunggu Konfirmasi Kasi';
                                                        $statusClass = 'bg-blue-100 text-blue-800';
                                                        $isLoading = true;
                                                        break;
                                                    case 'Verifikasi Kasi':
                                                        $statusText = 'Verifikasi Kasi';
                                                        $statusClass = 'bg-cyan-100 text-cyan-800';
                                                        $isLoading = true;
                                                        break;
                                                    case 'Selesai':
                                                        $statusText = 'Selesai';
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        $isSuccess = true;
                                                        break;
                                                    case 'Ditolak':
                                                        $statusText = 'Ditolak';
                                                        $statusClass = 'bg-red-100 text-red-800';
                                                        $isRejected = true;
                                                        break;
                                                    default:
                                                        // Jika status Menunggu dan ada alasan penolakan, anggap sebagai "Perlu Diperbaiki"
                                                        if ($status === 'Menunggu' && !empty($p['alasan_penolakan'])) {
                                                            $statusText = 'Perlu Diperbaiki';
                                                            $statusClass = 'bg-orange-100 text-orange-800';
                                                        } else {
                                                        $statusText = 'Tidak Diketahui (' . htmlspecialchars($status) . ')';
                                                        $statusClass = 'bg-gray-200 text-gray-800';
                                                        break;
                                                        }
                                                }
                                            ?>
                                        <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
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
                                        <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium" data-pengajuan-id="<?= $p['pengajuan_id'] ?>">
                                            <div class="flex items-center justify-center space-x-2">
                                                <!-- Aksi kondisional berdasarkan status -->
                                                <?php if (in_array($p['status'], ['Menunggu', 'Ditolak'])): ?>
                                                    <!-- Tampilkan 4 tombol untuk status 'Menunggu Diajukan' -->
                                                    <button onclick="showModal('detailModal', <?= htmlspecialchars(json_encode($p)) ?>)" title="Lihat Detail" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                        <i class="fas fa-eye mr-1"></i> Detail
                                                    </button>
                                                    <button onclick="submitPengajuan(<?= $p['pengajuan_id'] ?>, 'Ajukan Surat?', 'Setelah diajukan, data tidak dapat diubah lagi. Anda yakin?')" title="Ajukan" class="inline-flex items-center px-3 py-2 bg-yellow-100 hover:bg-yellow-600 text-yellow-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium cursor-pointer">
                                                        <i class="fas fa-paper-plane mr-1"></i> Ajukan
                                                    </button>
                                                    <button onclick="showModal('editModal', <?= htmlspecialchars(json_encode($p)) ?>)" title="Edit Pengajuan" class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                        <i class="fas fa-edit mr-1"></i> Edit
                                                    </button>
                                                    <form method="POST" onsubmit="deletePengajuan(event, <?= $p['pengajuan_id'] ?>)" class="inline-block">
                                                        <button type="submit" title="Hapus Pengajuan" class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                            <i class="fas fa-trash mr-1"></i> Hapus
                                                        </button> 
                                                    </form>
                                                <?php elseif (in_array($p['status'], ['Diajukan User', 'Verifikasi Kasi', 'Selesai'])): ?>
                                                    <!-- Untuk status lainnya, hanya tampilkan tombol Detail saja -->
                                                    <button onclick="showModal('detailModal', <?= htmlspecialchars(json_encode($p)) ?>)" title="Lihat Detail" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                        <i class="fas fa-eye mr-1"></i> Detail
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                            </tbody>
                        </table>
                      </div> <!-- Penutup untuk #table-container -->
                    </div> <!-- Penutup untuk .w-full -->

                    <div id="noResultsMessage" class="hidden text-center py-12 text-gray-500">
                        <i class="fas fa-search text-5xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">Data Tidak Ditemukan</h3>
                        <p>Tidak ada pengajuan yang cocok dengan pencarian Anda.</p>
                    </div>

                    <!-- Kontrol Paginasi dipindahkan ke sini agar berada di dalam blok if dan di dalam kotak putih -->
                    <div class="flex flex-col md:flex-row justify-between items-center pt-4 mt-4 border-t border-gray-200 gap-4 md:gap-0">
                        <div id="showingInfo" class="text-sm text-gray-700 text-center md:text-left">
                            <!-- Info paginasi akan di-render oleh JavaScript di sini -->
                        </div>
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
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-file-alt text-6xl mb-4"></i>
                        <p class="text-lg">Anda belum memiliki pengajuan surat.</p>
                        <p class="text-sm mt-1">Klik tombol "Buat Pengajuan Surat" untuk memulai.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div> <!-- .flex -->

    <!-- Modal Tambah Pengajuan -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto modal-content-area">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Buat Pengajuan Surat</h3>
                <button onclick="closeModal('addModal')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="current_search" value="<?= htmlspecialchars($searchFilter) ?>">
                <input type="hidden" name="current_page" id="add_current_page">
                <div class="space-y-4">
                    <div>
                        <label for="add_jenis_surat" class="block font-semibold mb-2">Jenis Surat <span class="text-red-500">*</span></label>
                        <select name="id_jenis_surat" id="add_jenis_surat" required class="w-full px-4 py-2 border rounded-lg bg-white custom-select" size="1">
                            <option value="" disabled selected>-- Pilih Jenis Surat --</option>
                            <?php foreach ($jenisSuratOptions as $opsi): ?>
                                <option value="<?= htmlspecialchars($opsi['id_jenis_surat']) ?>">
                                    <?= htmlspecialchars($opsi['nama_surat']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="add_alamat" class="block font-semibold mb-2">Alamat <span class="text-red-500">*</span></label>
                        <input type="text" name="alamat" id="add_alamat" required maxlength="255" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan alamat lengkap Anda...">
                    </div>
                    <div>
                        <label for="add_keterangan" class="block font-semibold mb-2">Keterangan (Opsional)</label>
                        <textarea name="keterangan" id="add_keterangan" rows="3" maxlength="500" class="w-full px-4 py-2 border rounded-lg" placeholder="Tambahkan keterangan jika diperlukan..."></textarea>
                    </div>
                    <div>
                        <label class="block font-semibold mb-2">Foto KTP</label>
                        <input type="file" name="foto_ktp" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block font-semibold mb-2">Foto KK</label>
                        <input type="file" name="foto_kk" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block font-semibold mb-2">Foto Formulir</label>
                        <input type="file" name="foto_formulir" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block font-semibold mb-2">Foto Lainnya</label>
                        <input type="file" name="foto_lainnya" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Format file yang diizinkan: PDF, JPG, PNG. Maksimal 5MB per file.</p>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                    <button type="button" onclick="closeModal('addModal')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">Batal</button>
                </div>
            </form>
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
                <input type="hidden" name="current_search" value="<?= htmlspecialchars($searchFilter) ?>">
                <input type="hidden" name="current_page" id="edit_current_page">
                <div class="space-y-4">
                    <div>
                        <label for="edit_jenis_surat" class="block font-semibold mb-2">Jenis Surat <span class="text-red-500">*</span></label>
                        <select name="id_jenis_surat" id="edit_jenis_surat" required class="w-full px-4 py-2 border rounded-lg bg-white custom-select" size="1">
                            <option value="" disabled selected>-- Pilih Jenis Surat --</option>
                            <?php foreach ($jenisSuratOptions as $opsi): ?>
                                <option value="<?= htmlspecialchars($opsi['id_jenis_surat']) ?>">
                                    <?= htmlspecialchars($opsi['nama_surat']) ?> 
                                </option>
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

                    <div>
                        <label class="block font-semibold mb-2 text-sm">Ganti Foto KTP</label>
                        <input type="file" name="edit_foto_ktp" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block font-semibold mb-2 text-sm">Ganti Foto KK</label>
                        <input type="file" name="edit_foto_kk" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block font-semibold mb-2 text-sm">Ganti Foto Formulir</label>
                        <input type="file" name="edit_foto_formulir" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block font-semibold mb-2 text-sm">Ganti Foto Lainnya</label>
                        <input type="file" name="edit_foto_lainnya" accept=".jpg,.jpeg,.png,.pdf" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                    <button type="button" onclick="closeModal('editModal')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail Pengajuan -->
    <div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-xl font-bold text-gray-800">Detail Pengajuan</h3>
                <button onclick="closeModal('detailModal')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <div id="detailContent" class="space-y-4 text-gray-700">
                <!-- Konten detail akan diisi oleh JavaScript -->
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('detailModal')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Preview Dokumen -->
    <div id="documentPreviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-screen flex flex-col">
            <div class="relative flex justify-center items-center mb-4">
                <h3 class="text-xl font-bold text-center">Preview Dokumen</h3>
                <button onclick="closeModal('documentPreviewModal')" class="absolute right-0 text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <div id="documentPreviewContent" class="flex-grow overflow-auto text-center mb-4">
                <!-- Konten preview akan diisi oleh JavaScript -->
            </div>
            <div class="flex justify-end items-center gap-4 border-t pt-4">
                <a id="downloadButton" href="#" download target="_blank" class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700">
                    <i class="fas fa-download mr-2"></i>Unduh Dokumen
                </a>
                <button type="button" onclick="closeModal('documentPreviewModal')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Kembali</button>
            </div>
        </div>
    </div>

    <script>
        // --- Logika Modal ---
        function showModal(modalId, data = null) {
            const modal = document.getElementById(modalId);
            if (modal) {
                if (modalId === 'detailModal' && data) { // Logika untuk menampilkan detail
                    let statusBadge = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">${data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : '-'}</span>`;
                    if (data.status === 'Selesai') statusBadge = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Selesai</span>`;
                    if (data.status === 'Menunggu') statusBadge = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Menunggu Diajukan</span>`;
                    if (data.status.includes('Konfirmasi') || data.status.includes('Verifikasi')) statusBadge = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">${data.status}</span>`;
                    if (data.status === 'Ditolak') statusBadge = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Ditolak</span>`;

                    const content = `
                        <div><p class="font-semibold">Jenis Surat:</p><p class="p-2 bg-gray-50 rounded">${data.nama_surat || '-'}</p></div>
                        <div><p class="font-semibold">Tanggal:</p><p class="p-2 bg-gray-50 rounded">${new Date(data.tgl_pengajuan).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</p></div>
                        <div><p class="font-semibold">Alamat:</p><p class="p-2 bg-gray-50 rounded break-words">${data.alamat || '-'}</p></div>
                        ${data.keterangan ? `<div><p class="font-semibold">Keterangan:</p><p class="p-2 bg-gray-50 rounded break-words">${data.keterangan}</p></div>` : ''}
                        ${(data.status === 'Ditolak' || (data.status === 'Menunggu' && data.alasan_penolakan)) && data.alasan_penolakan ? `<div><p class="font-semibold text-red-600">Alasan Penolakan:</p><p class="p-2 bg-red-50 rounded border-l-4 border-red-500">${data.alasan_penolakan}</p></div>` : ''}
                    `;
                    document.getElementById('detailContent').innerHTML = content;
                } else if (modalId === 'editModal' && data) {
                    document.getElementById('edit_pengajuan_id').value = data.pengajuan_id;
                    document.getElementById('edit_alamat').value = data.alamat;
                    document.getElementById('edit_keterangan').value = data.keterangan;
                    document.getElementById('edit_current_page').value = currentPage;

                    // DEBUG: Log data untuk memeriksa nilai jenis_surat dan opsi dropdown
                    
                    // Atur nilai dropdown jenis surat dengan cara yang lebih andal
                    const selectElement = document.getElementById('edit_jenis_surat');
                    const targetIdJenisSurat = data.id_jenis_surat;
                    selectElement.value = targetIdJenisSurat;
                    if (selectElement.value != targetIdJenisSurat) {
                        selectElement.value = ""; // Jika ID tidak ditemukan, kosongkan
                    }
                    selectElement.dispatchEvent(new Event('change'));
                } else if (modalId === 'addModal') {
                    // Set halaman saat ini untuk form tambah
                    document.getElementById('add_current_page').value = currentPage;
                }
                modal.classList.remove('hidden');
            }
        }

        function showDocumentPreview(fileName) {
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
                contentArea.innerHTML = `<div class="text-center p-8"><i class="fas fa-file-alt text-6xl text-gray-400"></i><p class="mt-4">Preview tidak tersedia untuk tipe file ini.</p></div>`;
            }

            downloadBtn.href = fileUrl;
            downloadBtn.download = fileName;

            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function submitPengajuan(id, title, text) {
            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6', // blue-500
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Ajukan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `<input type="hidden" name="action" value="submit_to_admin"><input type="hidden" name="pengajuan_id" value="${id}"><input type="hidden" name="current_page" value="${currentPage}"><input type="hidden" name="current_search" value="<?= htmlspecialchars($searchFilter) ?>">`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function deletePengajuan(event, id) {
             event.preventDefault(); // Mencegah form asli dari submit
             Swal.fire({
                title: 'Anda yakin?',
                text: "Pengajuan ini akan dihapus secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="pengajuan_id" value="${id}"><input type="hidden" name="current_page" value="${currentPage}"><input type="hidden" name="current_search" value="<?= htmlspecialchars($searchFilter) ?>">`;
                    document.body.appendChild(form);
                    form.submit();
                }
            })
        }

        // --- Notifikasi SweetAlert2 ---
        <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?= addslashes($success) ?>',
            timer: 3000,
            showConfirmButton: false
        });
        <?php endif; ?>

        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?= addslashes($error) ?>',
            timer: 5000,
            showConfirmButton: true
        });
        <?php endif; ?>
    </script>
    <script>
        // --- LOGIKA PAGINASI, FILTER, DAN SEARCH ---
        const tableBody = document.getElementById('tableBody');
        const allRows = tableBody ? Array.from(tableBody.getElementsByTagName('tr')) : [];        
        
        let currentPage = parseInt(new URLSearchParams(window.location.search).get('page')) || 1;
        let entriesPerPage = parseInt(new URLSearchParams(window.location.search).get('entries')) || 10;

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('tableEntries').value = entriesPerPage;
        });

        function updatePaginationControls() {
            const totalFiltered = allRows.length;
            const currentEntriesPerPage = (entriesPerPage === -1) ? totalFiltered : entriesPerPage;
            const totalPages = Math.ceil(totalFiltered / currentEntriesPerPage);
            const startEntry = (currentPage - 1) * currentEntriesPerPage + 1;
            const endEntry = Math.min(currentPage * currentEntriesPerPage, totalFiltered); 

            const showingInfo = document.getElementById('showingInfo');
            const prevBtn = document.getElementById('prevBtn'); // Ditambahkan
            const nextBtn = document.getElementById('nextBtn'); // Ditambahkan
            const pageButtonsContainer = document.getElementById('pageButtons'); // Ditambahkan
            const paginationControls = document.getElementById('paginationControls');

            if (totalFiltered === 0) {
                if(showingInfo) showingInfo.textContent = 'Menampilkan 0 hingga 0 dari 0 data';
                if(prevBtn) prevBtn.disabled = true;
                if(nextBtn) nextBtn.disabled = true;
                if(pageButtonsContainer) pageButtonsContainer.innerHTML = '';
                if(paginationControls) paginationControls.classList.add('hidden');
                return;
            }
            if(showingInfo) showingInfo.innerHTML = `Menampilkan ${startEntry} hingga ${endEntry} dari ${totalFiltered} data`;
            if(prevBtn) prevBtn.disabled = currentPage === 1;
            if(nextBtn) nextBtn.disabled = currentPage === totalPages;
            
            if(pageButtonsContainer) {
                pageButtonsContainer.innerHTML = ''; // Kosongkan tombol halaman
                // Logika untuk membuat tombol halaman (seperti di kegiatan.php)
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);

                if (currentPage <= 3) endPage = Math.min(5, totalPages);
                if (currentPage > totalPages - 3) startPage = Math.max(1, totalPages - 4);

                for (let i = startPage; i <= endPage; i++) {
                    const pageButton = document.createElement('button');
                    pageButton.textContent = i;
                    pageButton.onclick = (e) => {
                        e.preventDefault(); changePage(i);
                    };
                    pageButton.className = `px-3 py-1 border rounded-lg text-sm transition-colors ${
                        i === currentPage 
                        ? 'border-blue-600 bg-blue-600 text-white' 
                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100'
                    }`;
                    pageButtonsContainer.appendChild(pageButton);
                }
            }
            
            if (paginationControls) {
                if (totalPages <= 1 || entriesPerPage === -1) {
                    paginationControls.classList.add('hidden');
                } else {
                    paginationControls.classList.remove('hidden');
                }
            }
        }

        function applyFiltersAndPagination() {
            const searchText = document.getElementById('tableSearch').value.toLowerCase();
            const tableContainer = document.getElementById('table-container');
            const noResultsMessage = document.getElementById('noResultsMessage');
            const paginationContainer = document.querySelector('.flex.justify-between.items-center.pt-4');

            let filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(searchText));

            const currentEntriesPerPage = (entriesPerPage === -1) ? allRows.length : entriesPerPage;
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
            changePage(1);
        };

        window.changePage = function(newPage) {
            const urlParams = new URLSearchParams(window.location.search);
            currentPage = newPage;
            applyFiltersAndPagination();
        };

        document.addEventListener('DOMContentLoaded', () => {
            const originalRows = Array.from(tableBody.getElementsByTagName('tr'));
            allRows.splice(0, allRows.length, ...originalRows);
            filteredRows = [...allRows];

            document.getElementById('tableEntries').value = entriesPerPage;
            // Hanya jalankan paginasi jika tabel ada
            if (document.getElementById('pengajuanTable')) {
                applyFiltersAndPagination();
            }
            if (document.getElementById('tableEntries')) document.getElementById('tableEntries').value = entriesPerPage;
            if (document.getElementById('pengajuanTable')) applyFiltersAndPagination();
        });
    </script>
</body>
</html>