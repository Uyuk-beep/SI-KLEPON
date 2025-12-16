<?php
require_once '../config.php';
Session::start();

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();

// Cek apakah yang login adalah admin atau super admin
if (!in_array($admin['role'], ['super_admin', 'admin'])) {
    Session::set('error', 'Anda tidak memiliki hak akses ke halaman ini.');
    redirect('admin/dashboard.php');
}

$success = Session::get('success', ''); Session::remove('success');
$error = Session::get('error', ''); Session::remove('error');

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $editAdminId = $_POST['admin_id'] ?? 0;
            $username = sanitize($_POST['username'] ?? '');
            $nama = sanitize($_POST['nama'] ?? '');
            $nip = sanitize($_POST['nip'] ?? '');
            $noTelepon = sanitize($_POST['no_telepon'] ?? '');
            $jabatan = sanitize($_POST['jabatan'] ?? '');
            $role = $_POST['role'] ?? 'admin';
            $password = $_POST['password'] ?? '';

            // --- Validasi Input ---
            if (empty($username) || empty($nama) || empty($jabatan) || empty($nip)) {
                throw new Exception('Username, Nama, NIP, dan Jabatan wajib diisi.');
            }
            if ($action === 'add' && empty($password)) {
                throw new Exception('Password harus diisi untuk akun baru.');
            }
            if (!empty($password) && strlen($password) < 6) {
                throw new Exception('Password minimal harus 6 karakter.');
            }

            // --- Validasi Hak Akses ---
            if ($admin['role'] === 'admin') {
                if ($role === 'super_admin') {
                    throw new Exception('Anda tidak memiliki izin untuk membuat atau mengubah akun menjadi Super Admin.');
                }
                if ($action === 'edit') {
                    $targetAdmin = Database::query("SELECT role FROM admin WHERE admin_id = ?", [$editAdminId])->fetch();
                    if ($targetAdmin && $targetAdmin['role'] === 'super_admin') {
                        throw new Exception('Anda tidak dapat mengedit data Super Admin.');
                    }
                }
            }

            // --- Cek Duplikasi Username ---
            $checkSql = "SELECT admin_id FROM admin WHERE username = ? AND admin_id != ?";
            $checkParams = [$username, $editAdminId];
            if ($action === 'add') {
                $checkSql = "SELECT admin_id FROM admin WHERE username = ?";
                $checkParams = [$username];
            }
            if (Database::query($checkSql, $checkParams)->fetch()) {
                throw new Exception('Username sudah digunakan. Silakan gunakan username lain.');
            }

            // --- Validasi Role Super Admin (saat edit) ---
            if ($action === 'edit') {
                $targetAdmin = Database::query("SELECT role FROM admin WHERE admin_id = ?", [$editAdminId])->fetch();
                if ($targetAdmin && $targetAdmin['role'] === 'super_admin' && $role !== 'super_admin') {
                    $superAdminCount = Database::query("SELECT COUNT(*) FROM admin WHERE role = 'super_admin'")->fetchColumn();
                    if ($superAdminCount <= 1) {
                        throw new Exception('Tidak dapat mengubah role. Harus ada minimal satu Super Admin dalam sistem.');
                    }
                }
            }

            // --- Proses Database ---
            if ($action === 'add') {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO admin (username, password, nama, nip, no_telepon, jabatan, role) VALUES (?, ?, ?, ?, ?, ?, ?)";
                Database::query($sql, [$username, $hashedPassword, $nama, $nip, $noTelepon, $jabatan, $role]);
                Session::set('success', 'Admin/Petugas berhasil ditambahkan.');
            } else { // Edit
                $params = [$username, $nama, $nip, $noTelepon, $jabatan, $role];
                $sql = "UPDATE admin SET username = ?, nama = ?, nip = ?, no_telepon = ?, jabatan = ?, role = ?";
                
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql .= " WHERE admin_id = ?";
                $params[] = $editAdminId;
                
                Database::query($sql, $params);
                Session::set('success', 'Admin/Petugas berhasil diperbarui.');
            }

        } elseif ($action === 'delete') {
        $deleteAdminId = $_POST['admin_id'] ?? 0;
        
        $targetAdmin = Database::query("SELECT role FROM admin WHERE admin_id = ?", [$deleteAdminId])->fetch();

        if ($deleteAdminId == $adminId) {
            throw new Exception('Anda tidak dapat menghapus akun Anda sendiri.');
        } elseif (!$targetAdmin) {
            throw new Exception('Akun yang akan dihapus tidak ditemukan.');
        } elseif ($targetAdmin['role'] === 'super_admin') {
            throw new Exception('Akun Super Admin tidak dapat dihapus untuk menjaga integritas sistem.');
        } else {
            Database::query("DELETE FROM admin WHERE admin_id = ?", [$deleteAdminId]);
            Session::set('success', 'Admin/Petugas berhasil dihapus.');
        }
    }
    } catch (Exception $e) {
        Session::set('error', $e->getMessage());
    }

    redirect('admin/admin_users.php');
}

$adminList = Database::query("SELECT * FROM admin ORDER BY admin_id ASC")->fetchAll();

// --- DATA DUMMY UNTUK TESTING PAGINATION ---
$use_dummy_data = false; // Ganti menjadi false untuk menonaktifkan
if ($use_dummy_data) {
    for ($i = 1; $i <= 100; $i++) {
        $adminList[] = [
            'admin_id' => 1000 + $i,
            'nama' => 'Admin Dummy ' . $i,
            'nip' => '199001012020121' . str_pad($i, 3, '0', STR_PAD_LEFT),
            'username' => 'dummyadmin' . $i,
            'no_telepon' => '081234567' . str_pad($i, 3, '0', STR_PAD_LEFT),
            'jabatan' => 'Staf Dummy',
            'role' => 'admin'
        ];
    }
}
// -------------------------------------------

$no = 1; // Inisialisasi nomor urut

// --- LOGIKA UNTUK MENCEGAH KEDIPAN SIDEBAR (TERPUSAT) ---
$body_class = '';
if (isset($_COOKIE['sidebarOpen']) && $_COOKIE['sidebarOpen'] === 'false') $body_class .= ' sidebar-closed';
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
    <title>Kelola Admin & Petugas - <?= SITE_NAME ?></title>
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
        <?php require_once 'sidebar_admin.php'; // Sidebar ?>

        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <?php require_once 'navbar_admin.php'; // Navbar ?>

            <main class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8 flex-grow">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6 mb-6">
                    <div class="flex flex-col lg:flex-row justify-between lg:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Data Admin</h2>
                            <p class="text-sm text-gray-500 mt-1 mb-2">Kelola akun untuk Admin dan Super Admin.</p>
                        </div>
                        <button onclick="showModal('add')" class="w-full lg:w-auto bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Tambah Admin
                        </button>
                    </div>

                    <!-- Filter dan Search -->
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 border-b pb-4 gap-4">
                        <div class="flex items-center space-x-2">
                            <label for="tableEntries" class="text-sm font-medium text-gray-700">Tampilkan</label>
                            <select id="tableEntries" onchange="changeEntries()" class="border border-gray-300 rounded-lg shadow-sm text-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="-1">Semua</option>
                            </select>
                            <span class="text-sm font-medium text-gray-700">data</span>
                        </div>
                        <div class="relative w-full lg:w-auto"><input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="Cari..." class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10"><i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i></div>
                    </div>

            <!-- Tabel -->
            <?php if (count($adminList) > 0): ?>
            <div id="tableContainer" class="overflow-x-auto relative border rounded-lg">
                <table id="myTable" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">NIP</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Username</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No Telepon</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jabatan</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" id="tableBody">
                        <?php foreach ($adminList as $adm): ?>
                        <tr class="hover:bg-gray-50"> 
                            <td class="px-3 py-4 text-sm text-gray-600 text-center align-middle"></td> <!-- No. diisi oleh JS -->
                            <td class="px-3 py-4 text-sm text-gray-900 align-middle whitespace-nowrap truncate" style="max-width: 200px;"><?= htmlspecialchars($adm['nama']) ?></td>
                            <td class="px-3 py-4 text-sm text-gray-600 align-middle"><?= htmlspecialchars($adm['nip'] ?: '-') ?></td>
                            <td class="px-3 py-4 text-sm text-gray-600 align-middle"><?= htmlspecialchars($adm['username'] ?: '-') ?></td>
                            <td class="px-3 py-4 text-sm text-gray-600 align-middle"><?= htmlspecialchars($adm['no_telepon'] ?: '-') ?></td>
                            <td class="px-3 py-4 text-sm text-gray-600 align-middle"><?= htmlspecialchars($adm['jabatan'] ?: '-') ?></td>
                            <td class="px-3 py-4 align-middle text-center">
                                <span class="px-2 py-1 rounded text-xs font-semibold whitespace-nowrap <?php
                                    echo match($adm['role']) {
                                        'super_admin' => 'bg-purple-100 text-purple-800',
                                        'admin' => 'bg-blue-100 text-blue-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                ?>">
                                    <?= ucfirst(str_replace('_', ' ', $adm['role'])) ?>
                                </span>
                            </td>
                            <td class="px-3 py-4 text-sm font-medium text-center whitespace-nowrap align-middle">
                                <div class="flex items-center justify-center space-x-2">
                                    <button onclick='showDetailModal(<?= htmlspecialchars(json_encode($adm)) ?>)' title="Detail" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                        <i class="fas fa-eye mr-1"></i>Detail
                                    </button>
                                    <?php
                                    // Admin tidak bisa edit super admin
                                    $canEdit = !($admin['role'] === 'admin' && $adm['role'] === 'super_admin');
                                    if ($canEdit):
                                    ?>
                                    <button onclick='editModal(<?= htmlspecialchars(json_encode($adm)) ?>)' title="Edit" class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </button>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-400 rounded-lg text-xs font-medium cursor-not-allowed" title="Tidak dapat mengedit Super Admin"><i class="fas fa-edit mr-1"></i>Edit</span>
                                    <?php endif; ?>

                                    <?php
                                    // Tidak bisa hapus diri sendiri & admin tidak bisa hapus super admin
                                    $canDelete = ($adm['admin_id'] != $admin['admin_id']) && !($admin['role'] === 'admin' && $adm['role'] === 'super_admin');
                                    if ($canDelete):
                                    ?>
                                        <form method="POST" class="inline" onsubmit="confirmDelete(event, 'admin/petugas')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="admin_id" value="<?= $adm['admin_id'] ?>">
                                            <button type="submit" title="Hapus" class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                <i class="fas fa-trash mr-1"></i>Hapus
                                            </button>
                                        </form>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-400 rounded-lg text-xs font-medium cursor-not-allowed" title="Aksi tidak diizinkan"><i class="fas fa-trash mr-1"></i>Hapus</span>
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
            <div id="noResultsMessage" class="hidden text-center py-12 text-gray-500"><i class="fas fa-search text-5xl mb-4"></i><h3 class="text-xl font-semibold text-gray-600">Data Tidak Ditemukan</h3><p>Tidak ada data yang cocok dengan pencarian Anda.</p></div>
            <?php else: ?>
            <div class="text-center py-12 text-gray-500"><i class="fas fa-user-shield text-6xl mb-4"></i><p>Tidak ada data admin/petugas yang terdaftar.</p></div>
            <?php endif; ?>
        </div>
    </main>
    </div>

    <!-- Modal Detail -->
    <div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Detail Akun</h3>
                <button onclick="closeModal('detailModal')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <div id="detailContent" class="space-y-3"></div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('detailModal')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Tambah Akun Baru</h3>
                <button onclick="closeModal('addModal')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="space-y-4">
                    <div>
                        <label for="add_nama" class="block font-semibold mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" id="add_nama" required maxlength="100" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan nama lengkap">
                    </div>
                    <div>
                        <label for="add_nip" class="block font-semibold mb-2">NIP <span class="text-red-500">*</span></label>
                        <input type="text" name="nip" id="add_nip" required maxlength="18" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan NIP">
                    </div>
                    <div>
                        <label for="add_username" class="block font-semibold mb-2">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="add_username" required maxlength="50" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan username">
                    </div>
                    <div>
                        <label for="add_no_telepon" class="block font-semibold mb-2">No. Telepon</label>
                        <input type="tel" name="no_telepon" id="add_no_telepon" maxlength="20" class="w-full px-4 py-2 border rounded-lg" placeholder="Masukkan nomor telepon">
                    </div>
                    <div>
                        <label for="add_jabatan" class="block font-semibold mb-2">Jabatan <span class="text-red-500">*</span></label>
                        <input type="text" name="jabatan" id="add_jabatan" required maxlength="100" class="w-full px-4 py-2 border rounded-lg" placeholder="Contoh: Kasi Pemerintahan">
                    </div>
                    <div>
                        <label for="role" class="block font-semibold mb-2">Role</label>
                        <select name="role" id="add_role" class="w-full px-4 py-2 border rounded-lg">
                            <option value="admin">Admin</option>
                            <?php if ($admin['role'] === 'super_admin'): ?>
                            <option value="super_admin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label for="add_password" class="block font-semibold mb-2">Password <span class="text-red-500">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="add_password" required minlength="6" maxlength="50" class="w-full px-4 py-2 border rounded-lg pr-10" placeholder="Minimal 6 karakter">
                            <span class="input-group-text" onclick="togglePasswordVisibility('add_password')"><i class="fas fa-eye"></i></span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Simpan</button>
                    <button type="button" onclick="closeModal('addModal')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Edit Akun</h3>
                <button onclick="closeModal('editModal')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                <div class="space-y-4">
                    <div>
                        <label for="edit_nama" class="block font-semibold mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" id="edit_nama" required maxlength="100" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_nip" class="block font-semibold mb-2">NIP <span class="text-red-500">*</span></label>
                        <input type="text" name="nip" id="edit_nip" required maxlength="18" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_username" class="block font-semibold mb-2">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="edit_username" required maxlength="50" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_no_telepon" class="block font-semibold mb-2">No. Telepon</label>
                        <input type="tel" name="no_telepon" id="edit_no_telepon" maxlength="20" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_jabatan" class="block font-semibold mb-2">Jabatan <span class="text-red-500">*</span></label>
                        <input type="text" name="jabatan" id="edit_jabatan" required maxlength="100" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="edit_role" class="block font-semibold mb-2">Role</label>
                        <select name="role" id="edit_role" class="w-full px-4 py-2 border rounded-lg">
                            <option value="admin">Admin</option>
                            <?php if ($admin['role'] === 'super_admin'): ?>
                            <option value="super_admin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label for="edit_password" class="block font-semibold mb-2">Password Baru</label>
                        <div class="input-group">
                            <input type="password" name="password" id="edit_password" minlength="6" maxlength="50" class="w-full px-4 py-2 border rounded-lg pr-10" placeholder="Kosongkan jika tidak diubah">
                            <span class="input-group-text" onclick="togglePasswordVisibility('edit_password')"><i class="fas fa-eye"></i></span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Simpan</button>
                    <button type="button" onclick="closeModal('editModal')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= addslashes($success) ?>', timer: 3000, showConfirmButton: false });
        <?php endif; ?>
        <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= addslashes($error) ?>', timer: 5000, showConfirmButton: true });
        <?php endif; ?>

        function showDetailModal(data) {
            const content = `
                <div><p class="font-semibold">Nama Lengkap:</p><p class="p-2 bg-gray-50 rounded">${data.nama || '-'}</p></div>
                <div><p class="font-semibold">NIP:</p><p class="p-2 bg-gray-50 rounded">${data.nip || '-'}</p></div>
                <div><p class="font-semibold">Username:</p><p class="p-2 bg-gray-50 rounded">${data.username || '-'}</p></div>
                <div><p class="font-semibold">Jabatan:</p><p class="p-2 bg-gray-50 rounded">${data.jabatan || '-'}</p></div>
                <div><p class="font-semibold">No. Telepon:</p><p class="p-2 bg-gray-50 rounded">${data.no_telepon || '-'}</p></div>
                <div><p class="font-semibold">Role:</p><p class="p-2 bg-gray-50 rounded">${data.role ? data.role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : '-'}</p></div>
            `;
            document.getElementById('detailContent').innerHTML = content;
            document.getElementById('detailModal').classList.remove('hidden');
        }

        function showModal(action) {
            if (action === 'add') {
                document.getElementById('addModal').classList.remove('hidden');
                document.getElementById('add_nama').value = '';
                document.getElementById('add_nip').value = '';
                document.getElementById('add_username').value = '';
                document.getElementById('add_no_telepon').value = '';
                document.getElementById('add_jabatan').value = '';
                document.getElementById('add_role').value = 'admin';
                document.getElementById('add_password').value = '';
            }
        }

        function editModal(data) {
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('edit_admin_id').value = data.admin_id;
            document.getElementById('edit_nama').value = data.nama;
            document.getElementById('edit_nip').value = data.nip;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_no_telepon').value = data.no_telepon || '';
            document.getElementById('edit_jabatan').value = data.jabatan;
            document.getElementById('edit_role').value = data.role;
            document.getElementById('edit_password').value = '';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
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

        function confirmDelete(event, type) {
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

        // --- LOGIKA TABEL, PAGINASI, SEARCH ---
        const tableBody = document.getElementById('tableBody');
        const allRows = tableBody ? Array.from(tableBody.getElementsByTagName('tr')) : [];
        let filteredRows = [...allRows];
        let currentPage = 1;
        let entriesPerPage = 10;

        function updatePaginationControls() {
            const totalFiltered = filteredRows.length;
            const currentEntriesPerPage = (entriesPerPage === -1) ? totalFiltered : parseInt(entriesPerPage);
            const totalPages = Math.ceil(totalFiltered / currentEntriesPerPage);
            const startEntry = (currentPage - 1) * currentEntriesPerPage + 1;
            const endEntry = Math.min(currentPage * currentEntriesPerPage, totalFiltered);

            const showingInfo = document.getElementById('showingInfo');
            const pageButtonsContainer = document.getElementById('pageButtons');
            const paginationControls = document.getElementById('paginationControls');
            const paginationContainer = document.getElementById('paginationContainer');

            if (totalFiltered === 0) {
                if (showingInfo) showingInfo.textContent = 'Menampilkan 0 dari 0 data';
                if (paginationControls) paginationControls.classList.add('hidden');
                if (paginationContainer) paginationContainer.classList.add('hidden');
                return;
            }
            
            if (paginationContainer) paginationContainer.classList.remove('hidden');

            if (totalPages <= 1 || entriesPerPage === -1) {
                if (paginationControls) paginationControls.classList.add('hidden');
                if (showingInfo) showingInfo.innerHTML = `Menampilkan ${totalFiltered} dari ${totalFiltered} data`;
            } else {
                if (paginationControls) paginationControls.classList.remove('hidden');
                if (showingInfo) showingInfo.innerHTML = `Menampilkan ${startEntry} hingga ${endEntry} dari ${totalFiltered} data`;
            }

            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage === totalPages;
            
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
            const searchText = document.getElementById('tableSearch') ? document.getElementById('tableSearch').value.toLowerCase() : '';
            const tableContainer = document.getElementById('tableContainer');
            const noResultsMessage = document.getElementById('noResultsMessage');
            const paginationContainer = document.getElementById('paginationContainer');

            filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(searchText));

            if (filteredRows.length === 0) {
                if (tableContainer) tableContainer.classList.add('hidden');
                if (paginationContainer) paginationContainer.classList.add('hidden');
                if (noResultsMessage) noResultsMessage.classList.remove('hidden');
            } else {
                if (tableContainer) tableContainer.classList.remove('hidden');
                if (paginationContainer) paginationContainer.classList.remove('hidden');
                if (noResultsMessage) noResultsMessage.classList.add('hidden');

                const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : parseInt(entriesPerPage);
                const maxPage = Math.max(1, Math.ceil(filteredRows.length / currentEntriesPerPage));
                if (currentPage > maxPage) currentPage = 1;

                const start = (currentPage - 1) * currentEntriesPerPage;
                const end = (entriesPerPage === -1) ? filteredRows.length : start + currentEntriesPerPage;

                tableBody.innerHTML = '';
                filteredRows.slice(start, end).forEach((row, index) => {
                    row.cells[0].textContent = start + index + 1;
                    tableBody.appendChild(row);
                });
            }
            updatePaginationControls();
        }

        window.changeEntries = function() {
            entriesPerPage = document.getElementById('tableEntries') ? parseInt(document.getElementById('tableEntries').value) : 10;
            currentPage = 1;
            applyFilters();
        };

        window.filterTable = function() {
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
        document.addEventListener('DOMContentLoaded', () => { if (document.getElementById('myTable')) { applyFilters(); } });
    </script>
</body>
</html>