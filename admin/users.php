<?php
require_once '../config.php';
Session::start();

if (!Session::isAdmin()) {
    redirect('login.php');
}

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();


// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? 0;
    
    // Fitur verifikasi/tolak dinonaktifkan karena kolom status_verifikasi tidak ada
    if ($action === 'delete') {
        // Cek apakah pengguna memiliki pengajuan surat
        $submissionCount = Database::query("SELECT COUNT(*) FROM pengajuan_surat WHERE user_id = ?", [$userId])->fetchColumn();

        if ($submissionCount > 0) {
            Session::set('error', 'Pengguna tidak dapat dihapus karena memiliki riwayat pengajuan surat. Ini untuk menjaga integritas data laporan dan arsip.');
        } else {
            if ($userId > 0 && Database::query("DELETE FROM user WHERE user_id = ?", [$userId])) {
                Session::set('success', 'Pengguna berhasil dihapus.');
            }
        }
    } elseif ($action === 'clear_history') {
        if ($userId > 0) {
            // 1. Ambil semua file terkait sebelum menghapus record
            $filesToDelete = Database::query("SELECT foto_ktp, foto_kk, foto_formulir, foto_lainnya, file_surat FROM pengajuan_surat WHERE user_id = ?", [$userId])->fetchAll();

            // 2. Hapus record dari database
            Database::query("DELETE FROM pengajuan_surat WHERE user_id = ?", [$userId]);

            // 3. Hapus file fisik dari server
            foreach ($filesToDelete as $fileGroup) {
                foreach ($fileGroup as $fileName) {
                    if (!empty($fileName) && file_exists('../uploads/' . $fileName)) {
                        @unlink('../uploads/' . $fileName);
                    }
                }
            }
            Session::set('success', 'Riwayat pengajuan pengguna berhasil dibersihkan.');
        } else {
            Session::set('error', 'ID Pengguna tidak valid.');
        }
    } elseif ($action === 'add') {
        $nama = sanitize($_POST['nama'] ?? '');
        $nik = sanitize($_POST['nik'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $no_telepon = sanitize($_POST['no_telepon'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($nama) || empty($nik) || empty($username) || empty($email) || empty($password)) {
            $error = 'Nama, NIK, Username, Email, dan Password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal harus 6 karakter.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } else {
            $checkNik = Database::query("SELECT user_id FROM user WHERE nik = ?", [$nik])->fetch();
            $checkUsername = Database::query("SELECT user_id FROM user WHERE username = ?", [$username])->fetch();
            $checkEmail = Database::query("SELECT user_id FROM user WHERE email = ?", [$email])->fetch();
            if ($checkNik) {
                $error = 'NIK sudah terdaftar. Silakan gunakan NIK lain.';
            } elseif ($checkUsername) {
                $error = 'Username sudah terdaftar. Silakan gunakan username lain.';
            } elseif ($checkEmail) {
                $error = 'Email sudah terdaftar. Silakan gunakan email lain.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO user (nama, nik, username, email, no_telepon, password) VALUES (?, ?, ?, ?, ?, ?)";
                if (Database::query($sql, [$nama, $nik, $username, $email, $no_telepon, $hashedPassword])) {
                    Session::set('success', 'Pengguna baru berhasil ditambahkan.');
                } else {
                    $error = 'Gagal menambahkan pengguna ke database.';
                }
            }
        }
    } elseif ($action === 'edit') {
        $userId = $_POST['user_id'] ?? 0;
        $nama = sanitize($_POST['nama'] ?? '');
        $nik = sanitize($_POST['nik'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $no_telepon = sanitize($_POST['no_telepon'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($nama) || empty($nik) || empty($username) || empty($email) || empty($userId)) {
            $error = 'Nama, NIK, Username, dan Email wajib diisi.';
        } elseif (!empty($password) && strlen($password) < 6) {
            $error = 'Password baru minimal harus 6 karakter.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } else {
            $checkNik = Database::query("SELECT user_id FROM user WHERE nik = ? AND user_id != ?", [$nik, $userId])->fetch();
            $checkUsername = Database::query("SELECT user_id FROM user WHERE username = ? AND user_id != ?", [$username, $userId])->fetch();
            $checkEmail = Database::query("SELECT user_id FROM user WHERE email = ? AND user_id != ?", [$email, $userId])->fetch();

            if ($checkNik) {
                $error = 'NIK sudah terdaftar pada pengguna lain.';
            } elseif ($checkUsername) {
                $error = 'Username sudah terdaftar pada pengguna lain.';
            } elseif ($checkEmail) {
                $error = 'Email sudah terdaftar pada pengguna lain.';
            } else {
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    Database::query("UPDATE user SET nama=?, nik=?, username=?, email=?, no_telepon=?, password=? WHERE user_id=?", [$nama, $nik, $username, $email, $no_telepon, $hashedPassword, $userId]);
                } else {
                    Database::query("UPDATE user SET nama=?, nik=?, username=?, email=?, no_telepon=? WHERE user_id=?", [$nama, $nik, $username, $email, $no_telepon, $userId]);
                }
                Session::set('success', 'Data pengguna berhasil diperbarui.');
            }
        }
    }

    if ($error) {
        Session::set('error', $error);
    }
    redirect('admin/users.php'); // Redirect setelah operasi POST
}

$success = Session::get('success', ''); Session::remove('success');
$error = Session::get('error', ''); Session::remove('error');
// Filter
$search = $_GET['search'] ?? '';
$params = [];

// Query users dengan join untuk menghitung riwayat pengajuan
$sql = "SELECT u.*, COUNT(p.pengajuan_id) as submission_count
        FROM user u
        LEFT JOIN pengajuan_surat p ON u.user_id = p.user_id
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (u.nama LIKE ? OR u.nik LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY u.user_id ORDER BY u.user_id DESC";
$users = Database::query($sql, $params)->fetchAll();

// --- DATA DUMMY UNTUK TESTING PAGINATION ---
$use_dummy_data = false; // Ganti menjadi false untuk menonaktifkan
if ($use_dummy_data) {
    for ($i = 1; $i <= 100; $i++) {
        $users[] = [
            'user_id' => 1000 + $i,
            'nama' => 'Pengguna Dummy ' . $i,
            'nik' => '123456789012345' . $i,
            'username' => 'dummyuser' . $i,
            'no_telepon' => '081234567' . str_pad($i, 3, '0', STR_PAD_LEFT),
            'email' => 'dummy' . $i . '@example.com',
            'submission_count' => rand(0, 5)
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

    <title>Kelola User - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Custom styles for password toggle */
        .input-group { position: relative; }
        .input-group .input-group-text {
            position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6b7280;
        }
        .truncate-text {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
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
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6 mb-6">
                    <div class="flex flex-col lg:flex-row justify-between lg:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Data Pengguna</h2>
                            <p class="text-sm text-gray-500 mt-1 mb-2">Kelola data pengguna yang terdaftar di sistem.</p>
                        </div>
                        <button onclick="showModal('add')" class="w-full lg:w-auto bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Tambah Pengguna
                        </button>
                    </div>
                    <!-- Filter dan Search -->
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 pb-4 border-b gap-4">
                        <div class="flex items-center space-x-2">
                            <label for="tableEntries" class="text-sm font-medium text-gray-700">Tampilkan</label>
                            <select id="tableEntries" onchange="changeEntries()" 
                                class="border border-gray-300 rounded-lg shadow-sm text-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="-1">Semua</option>
                            </select>
                            <span class="text-sm font-medium text-gray-700">data</span>
                        </div>
                        <div class="relative w-full lg:w-auto">
                            <input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="Cari..."
                                class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
        
                    <!-- Tabel Users -->
                    <?php if (count($users) > 0): ?>
                    <div class="overflow-x-auto relative border rounded-lg">
                        <table id="myTable" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider" style="max-width: 150px;">NIK</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No Telepon</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Total Pengajuan</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4 text-sm text-gray-600 text-center align-middle"></td> <!-- No. diisi oleh JS -->
                                    <td class="px-3 py-4 text-sm text-gray-900 align-middle truncate-text" style="max-width: 200px;"><?= htmlspecialchars($user['nama']) ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle truncate-text" style="max-width: 150px;"><?= htmlspecialchars($user['nik'] ?: '-') ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle"><?= htmlspecialchars($user['username'] ?: '-') ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle"><?= htmlspecialchars($user['no_telepon'] ?: '-') ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle"><?= htmlspecialchars($user['email']) ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle text-center"><?= htmlspecialchars($user['submission_count']) ?></td>
                                    <td class="px-3 py-4 text-sm font-medium text-center whitespace-nowrap align-middle">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button onclick="showDetail(<?= htmlspecialchars(json_encode($user)) ?>)" title="Detail" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                <i class="fas fa-eye mr-1"></i>Detail
                                            </button>
                                            <button onclick="editModal(<?= htmlspecialchars(json_encode($user)) ?>)" title="Edit" class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                <i class="fas fa-edit mr-1"></i>Edit
                                            </button>
                                            <?php if ($user['submission_count'] > 0): ?>
                                                <form method="POST" class="inline" onsubmit="confirmClearHistory(event, <?= $user['user_id'] ?>, '<?= htmlspecialchars($user['nama']) ?>')">
                                                    <input type="hidden" name="action" value="clear_history">
                                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                    <button type="submit" title="Bersihkan Riwayat Pengajuan" class="inline-flex items-center px-3 py-2 bg-yellow-100 hover:bg-yellow-600 text-yellow-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                        <i class="fas fa-broom mr-1"></i>Bersihkan
                                                    </button>
                                                </form>
                                                <span class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-400 rounded-lg text-xs font-medium cursor-not-allowed" title="Hapus riwayat terlebih dahulu"><i class="fas fa-trash mr-1"></i>Hapus</span>
                                            <?php else: ?>
                                            <form method="POST" class="inline" onsubmit="confirmDelete(event, <?= $user['user_id'] ?>, 'pengguna')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" title="Hapus" class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                    <i class="fas fa-trash mr-1"></i>Hapus
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div> 
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
                        <p>Tidak ada data yang cocok dengan pencarian Anda.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-users text-6xl mb-4"></i>
                        <p>Tidak ada pengguna</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>    
    </div>

    <!-- Modal Detail -->
    <div id="modalDetail" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Detail Pengguna</h3>
                <button onclick="closeModal('modalDetail')" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div id="modalContent" class="text-sm"></div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('modalDetail')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Form Tambah Pengguna -->
    <div id="modalForm" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-bold">Tambah Pengguna Baru</h3>
                <button onclick="closeModal('modalForm')" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                
                <div class="space-y-4">
                    <div>
                        <label for="add_nama" class="block font-semibold mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" id="add_nama" required maxlength="100" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan nama lengkap">
                    </div>
                    <div>
                        <label for="add_nik" class="block font-semibold mb-2">NIK <span class="text-red-500">*</span></label>
                        <input type="text" name="nik" id="add_nik" required maxlength="16" pattern="[0-9]{16}" title="NIK harus 16 digit angka" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan 16 digit NIK">
                    </div>
                    <div>
                        <label for="add_username" class="block font-semibold mb-2">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="add_username" required maxlength="50" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan username">
                    </div>
                    <div>
                        <label for="add_email" class="block font-semibold mb-2">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="add_email" required maxlength="100" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan email">
                    </div>
                    <div>
                        <label for="add_no_telepon" class="block font-semibold mb-2">No. Telepon</label>
                        <input type="tel" name="no_telepon" id="add_no_telepon" maxlength="20" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan nomor telepon">
                    </div>
                    <div>
                        <label for="add_password" class="block font-semibold mb-2">Password <span class="text-red-500">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="add_password" required minlength="6" maxlength="50" class="w-full px-4 py-2 border rounded-lg pr-10" placeholder="Minimal 6 karakter">
                            <span class="input-group-text" onclick="togglePasswordVisibility('add_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                    <button type="button" onclick="closeModal('modalForm')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Edit Pengguna -->
    <div id="modalEdit" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Edit Pengguna</h3>
                <button onclick="closeModal('modalEdit')" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_nama" class="block font-semibold mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" id="edit_nama" required maxlength="100" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_nik" class="block font-semibold mb-2">NIK <span class="text-red-500">*</span></label>
                        <input type="text" name="nik" id="edit_nik" required maxlength="16" pattern="[0-9]{16}" title="NIK harus 16 digit angka" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_username" class="block font-semibold mb-2">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="edit_username" required maxlength="50" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_email" class="block font-semibold mb-2">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="edit_email" required maxlength="100" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_no_telepon" class="block font-semibold mb-2">No. Telepon</label>
                        <input type="tel" name="no_telepon" id="edit_no_telepon" maxlength="20" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_password" class="block font-semibold mb-2">Password Baru</label>
                        <div class="input-group">
                            <input type="password" name="password" id="edit_password" minlength="6" maxlength="50" class="w-full px-4 py-2 border rounded-lg pr-10" placeholder="Kosongkan jika tidak diubah">
                            <span class="input-group-text" onclick="togglePasswordVisibility('edit_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Simpan</button>
                    <button type="button" onclick="closeModal('modalEdit')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- SweetAlert2 Notifikasi Popup dari PHP Session ---
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
        function showDetail(user) {
            const content = `
                <div class="space-y-3">
                    <div><p class="font-semibold">Nama Lengkap:</p><p class="p-2 bg-gray-50 rounded">${user.nama || '-'}</p></div>
                    <div><p class="font-semibold">NIK:</p><p class="p-2 bg-gray-50 rounded">${user.nik || '-'}</p></div>
                    <div><p class="font-semibold">Username:</p><p class="p-2 bg-gray-50 rounded">${user.username || '-'}</p></div>
                    <div><p class="font-semibold">Email:</p><p class="p-2 bg-gray-50 rounded">${user.email || '-'}</p></div>
                    <div><p class="font-semibold">No. Telepon:</p><p class="p-2 bg-gray-50 rounded">${user.no_telepon || '-'}</p></div>
                </div>
            `;
            document.getElementById('modalContent').innerHTML = content;
            document.getElementById('modalDetail').classList.remove('hidden');
        }

        function showModal(action) {
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = 'Tambah Pengguna Baru';
            document.getElementById('modalForm').classList.remove('hidden');
            
            // Reset form
            document.getElementById('add_nama').value = '';
            document.getElementById('add_nik').value = '';
            document.getElementById('add_username').value = '';
            document.getElementById('add_email').value = '';
            document.getElementById('add_no_telepon').value = '';
            document.getElementById('add_password').value = '';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function editModal(user) {
            document.getElementById('modalEdit').classList.remove('hidden');

            // Isi form dengan data pengguna
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_nama').value = user.nama;
            document.getElementById('edit_nik').value = user.nik;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_no_telepon').value = user.no_telepon || '';
            document.getElementById('edit_password').value = ''; // Kosongkan password
        }

        function confirmDelete(event, id, type) {
            event.preventDefault(); 
            const form = event.target.closest('form');

            Swal.fire({
                title: 'Konfirmasi Hapus?',
                text: `Anda yakin ingin menghapus ${type} ini secara permanen?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!', 
                cancelButtonText: 'Batal' 
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function confirmClearHistory(event, id, name) {
            event.preventDefault(); 
            const form = event.target.closest('form');

            Swal.fire({
                title: 'Bersihkan Riwayat?',
                html: `Anda yakin ingin menghapus <b>semua riwayat pengajuan</b> milik pengguna <b>${name}</b>? Aksi ini tidak dapat dibatalkan.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Bersihkan!', 
                cancelButtonText: 'Batal' 
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function togglePasswordVisibility(fieldId) {
            const input = document.getElementById(fieldId);
            const icon = input.closest('.input-group').querySelector('.input-group-text i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // --- LOGIKA TABEL, PAGINASI, SEARCH ---
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
                if (document.getElementById('prevBtn')) document.getElementById('prevBtn').disabled = true;
                if (document.getElementById('nextBtn')) document.getElementById('nextBtn').disabled = true;
                if (pageButtonsContainer) pageButtonsContainer.innerHTML = '';
                if (paginationControls) paginationControls.classList.add('hidden');
                return;
            }
            
            if (showingInfo) showingInfo.innerHTML = `Menampilkan ${startEntry} hingga ${endEntry} dari ${totalFiltered} data`;

            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage === totalPages;
            
            if (pageButtonsContainer) {
                pageButtonsContainer.innerHTML = ''; // Kosongkan tombol
                    let startPage, endPage;
                    const maxButtons = 5;

                    startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
                    endPage = Math.min(totalPages, startPage + maxButtons - 1);
                    startPage = Math.max(1, endPage - maxButtons + 1);

                    for (let i = startPage; i <= endPage; i++) {
                        const pageButton = document.createElement('button');
                        pageButton.textContent = i;
                        pageButton.onclick = () => changePage(i);
                        pageButton.className = `px-3 py-1 border rounded-lg text-sm transition-colors ${i === currentPage ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100'}`;
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

        function applyFilters() {
            const searchText = document.getElementById('tableSearch').value.toLowerCase();
            const tableContainer = document.querySelector('.overflow-x-auto');
            const noResultsMessage = document.getElementById('noResultsMessage');
            const paginationContainer = document.getElementById('paginationContainer');

            filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(searchText));
            
            const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : parseInt(entriesPerPage);
            const maxPage = Math.max(1, Math.ceil(filteredRows.length / currentEntriesPerPage));
            if (currentPage > maxPage) currentPage = 1;

            if (filteredRows.length === 0) {
                if(tableContainer) tableContainer.classList.add('hidden');
                if(paginationContainer) paginationContainer.classList.add('hidden');
                if(noResultsMessage) noResultsMessage.classList.remove('hidden');
                tableBody.innerHTML = ''; // Pastikan tabel kosong
            } else {
                if(tableContainer) tableContainer.classList.remove('hidden');
                if(paginationContainer) paginationContainer.classList.remove('hidden');
                if(noResultsMessage) noResultsMessage.classList.add('hidden');

                const start = (currentPage - 1) * currentEntriesPerPage;
                const end = (entriesPerPage === -1) ? filteredRows.length : start + currentEntriesPerPage;
                
                tableBody.innerHTML = ''; // Selalu kosongkan tabel sebelum mengisi ulang
                filteredRows.slice(start, end).forEach((row, index) => {
                    row.cells[0].textContent = start + index + 1;
                    row.style.display = ''; // Pastikan baris terlihat
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
            // Reset ke halaman 1 setiap kali filter pencarian digunakan
            currentPage = 1;
            applyFilters();
        };

        window.changePage = function(newPage) {
            const totalPages = Math.ceil(filteredRows.length / ((entriesPerPage === -1) ? filteredRows.length : entriesPerPage));
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                applyFilters();
            }
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            filteredRows = [...allRows];
            applyFilters();
        });

    </script>

</body>
</html>