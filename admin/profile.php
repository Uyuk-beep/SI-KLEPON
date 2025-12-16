<?php
require_once '../config.php'; // Memuat konfigurasi dan session
Session::start();

// --- PENGAMBILAN NOTIFIKASI SESSION ---
$success = Session::get('success', '');
Session::remove('success');
$error = Session::get('error', '');
Session::remove('error');
// ------------------------------------

// Cek login dan pastikan admin
if (!Session::isAdmin()) {
    Session::set('error', 'Anda harus login sebagai admin untuk mengakses halaman ini.');
    redirect('login.php');
}

$adminId = Session::getAdminId();

// --- AMBIL DATA USER & VERIFIKASI ---
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?",
    [$adminId]
)->fetch();

if (!$admin) {
    Session::destroy();
    redirect('login.php');
}

// Data Placeholder/Default Handling
$admin['no_telepon'] = $admin['no_telepon'] ?? '';
$admin['username'] = $admin['username'] ?? '';

// =======================================================
// LOGIKA UPDATE PROFILE & PASSWORD
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $error_msg = '';
        $newName = sanitize($_POST['nama'] ?? '');
        $newNip = sanitize($_POST['nip'] ?? '');
        $newNoHp = sanitize($_POST['no_telepon'] ?? '');
        $newUsername = sanitize($_POST['username'] ?? '');
        $newPassword = $_POST['password'] ?? '';

        if (empty($newName) || empty($newUsername)) {
            $error_msg = 'Nama dan Username harus diisi.';
        } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
            $error_msg = 'Password baru minimal 6 karakter.';
        } else {
            // Cek jika username sudah ada (selain user saat ini)
            $existingAdmin = Database::query("SELECT admin_id FROM admin WHERE username = ? AND admin_id != ?", [$newUsername, $adminId])->fetch();
            if ($existingAdmin) {
                $error_msg = 'Username sudah digunakan oleh pengguna lain.';
            } else {
                $params = [$newName, $newNip, $newNoHp, $newUsername];
                $sql = "UPDATE admin SET nama = ?, nip = ?, no_telepon = ?, username = ?";
                
                if (!empty($newPassword)) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
                }

                $sql .= " WHERE admin_id = ?";
                $params[] = $adminId;

                if (Database::query($sql, $params)) {
                    Session::set('admin_name', $newName);
                    Session::set('success', 'Informasi profil berhasil diperbarui.');
                } else {
                    $error_msg = 'Gagal memperbarui informasi profil.';
                }
            }
        }
        if (!empty($error_msg)) Session::set('error', $error_msg);
    }

    

    redirect('admin/profile.php');
}

// Muat ulang data user setelah update
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?",
    [$adminId]
)->fetch();

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

    <title>Profil Pengguna - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Custom form styles */
        .form-grid-input { padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); width: 100%; }
        .form-label { color: #374151; font-weight: 500; }
        .input-group { position: relative; width: 100%; }
        .input-group-text { position: absolute; right: 0; padding: 0.5rem 0.75rem; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6b7280; }
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
        <?php require_once 'sidebar_admin.php'; // Memuat sidebar terpusat ?>

        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <?php require_once 'navbar_admin.php'; ?>
            
            <div class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8 flex-grow">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Profil Akun</h2>

                    <form method="POST" action="profile.php" id="form-info" class="mt-6">
                        <input type="hidden" name="action" value="update_info">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama_lengkap" class="block text-sm form-label mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                                <input type="text" name="nama" id="nama" required maxlength="100"
                                       value="<?= htmlspecialchars($admin['nama'] ?? '') ?>"
                                       class="form-grid-input">
                            </div>
                            <div>
                                <label for="nik" class="block text-sm form-label mb-1">NIP</label>
                                <input type="text" name="nip" id="nip" value="<?= htmlspecialchars($admin['nip']) ?>" required maxlength="18"
                                       class="form-grid-input">
                            </div>
                            <div>
                                <label for="username" class="block text-sm form-label mb-1">Username <span class="text-red-500">*</span></label>
                                <input type="text" name="username" id="username" required value="<?= htmlspecialchars($admin['username'] ?? '') ?>" maxlength="50"
                                       class="form-grid-input">
                            </div>
                            <div>
                                <label for="no_telepon" class="block text-sm form-label mb-1">No. Telepon</label>
                                <input type="tel" name="no_telepon" id="no_telepon" maxlength="20"
                                       value="<?= htmlspecialchars($admin['no_telepon'] ?? '') ?>"
                                       class="form-grid-input">
                            </div>
                            <div class="md:col-span-2">
                                <label for="password" class="block text-sm form-label mb-1">Ubah Password</label>
                                 <div class="input-group">
                                    <input type="password" name="password" id="password" minlength="6" maxlength="50"
                                        class="form-grid-input pr-10" placeholder="Kosongkan jika tidak diubah">
                                     <span class="input-group-text" onclick="togglePasswordVisibility('password')">
                                        <i class="fas fa-eye"></i>
                                     </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-start space-x-3 mt-8">
                            <button type="submit"
                                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-semibold">
                                <i class="fas fa-save mr-2"></i>Simpan</button>
                            <a href="dashboard.php" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 font-semibold text-center">
                                Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // FUNGSI UNTUK TOGGLE SEMBUNYIKAN/TAMPILKAN PASSWORD
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
    </script>
</body>
</html>