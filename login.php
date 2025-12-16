<?php
require_once 'config.php';
Session::start();

// Jika sudah login, redirect ke dashboard
if (Session::isLoggedIn()) {
    redirect(Session::isAdmin() ? 'admin/dashboard.php' : 'user/dashboard.php');
}

$error = Session::get('error', '');
Session::remove('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input dari form yang diketik pengguna
    $username = sanitize($_POST['username'] ?? ''); 
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        
        $login_success = false;

        // 1. Coba login sebagai Admin/Petugas (Cek berdasarkan username)
        $stmt_admin = Database::query(
            "SELECT * FROM admin WHERE username = ?",
            [$username]
        );
        $admin = $stmt_admin->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // LOGIN ADMIN BERHASIL
            Session::set('admin_id', $admin['admin_id']);
            Session::set('admin_name', $admin['nama']);
            Session::set('admin_role', $admin['role']);
            Session::set('admin_username', $admin['username']);
            Session::set('is_admin', true);
            Session::set('success', 'Login berhasil! Selamat datang kembali, ' . htmlspecialchars($admin['nama']) . '.');
            $login_success = true;
            header('Location: admin/dashboard.php');
            exit();

        } 
        
        // Hanya jika admin login gagal, lanjutkan ke user login
        if (!$login_success) {
            
            $stmt_user = Database::query(
                "SELECT * FROM user WHERE username = ?", 
                [$username] 
            );
            $user = $stmt_user->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Pengecekan status verifikasi dinonaktifkan
                // LOGIN USER BERHASIL
                Session::set('user_id', $user['user_id']);
                Session::set('user_name', $user['nama']);
                Session::set('user_nik', $user['nik']);
                Session::set('user_email', $user['email']);
                Session::set('is_admin', false);
                Session::set('success', 'Login berhasil! Selamat datang, ' . htmlspecialchars($user['nama']) . '.');
                $login_success = true;
                header('Location: user/dashboard.php');
                exit();
            }
        }
        
        // 3. Jika kedua login (admin dan user) gagal
        if (!$login_success) {
            $error = 'Username atau password salah, atau akun tidak ditemukan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>

    <link rel="icon" type="image/x-icon" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/logopky.gif">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/logopky.gif">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-image: url('assets/img/gedung.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
          input[type="password"] {
              padding-right: 2.5rem;
           }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-8">
            <div class="text-center mb-8">
                <img src="assets/img/logopky.gif" alt="Logo Kelurahan" class="h-24 w-auto mx-auto mb-2">
                <p class="text-lg text-gray-800 mt-1">Kelurahan Kalampangan</p>
            </div>
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-gray-700 font-semibold mb-2" for="username">
                        <i class="fas fa-user mr-2 text-gray-500"></i>Username
                    </label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Masukkan Username">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-2" for="password">
                        <i class="fas fa-lock mr-2 text-gray-500"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Masukkan Password">
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 hover:text-gray-700 focus:outline-none">
                             <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                    <i class="fas fa-sign-in-alt mr-2"></i>Masuk
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    Belum punya akun?
                    <a href="register.php" class="text-gray-600 hover:text-blue-600">Daftar di sini
                    </a>
                </p>
                <a href="index.php" class="inline-block mt-4 text-gray-600 hover:text-gray-800 transition">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>

      <script>
        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');

        togglePasswordButton.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Gagal!',
            text: '<?= addslashes($error) ?>',
            confirmButtonText: 'Coba Lagi',
            confirmButtonColor: '#3085d6'
        });
        <?php endif; ?>
    </script>
</body>
</html>