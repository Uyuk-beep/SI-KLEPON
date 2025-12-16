<?php
require_once 'config.php';
Session::start();

// Jika sudah login, redirect ke dashboard
if (Session::isLoggedIn()) {
    redirect(Session::isAdmin() ? 'admin/dashboard.php' : 'user/dashboard.php');
}

$error = '';
$success = ''; // Variabel ini tidak akan digunakan untuk alert, tapi disimpan di session
$registration_successful = false; // Flag untuk trigger JS modal

// Ambil nilai POST untuk ditampilkan kembali di form jika ada error
$username_val = htmlspecialchars($_POST['username'] ?? '');
$nik_val = htmlspecialchars($_POST['nik'] ?? '');
$nama_val = htmlspecialchars($_POST['nama_lengkap'] ?? '');
$no_hp_val = htmlspecialchars($_POST['no_hp'] ?? '');
$email_val = htmlspecialchars($_POST['email'] ?? '');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $nik = sanitize($_POST['nik'] ?? '');
    $nama = sanitize($_POST['nama_lengkap'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $no_hp = sanitize($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi
    if (empty($username) || empty($nik) || empty($nama) || empty($email) || empty($password) || empty($no_hp)) {
        $error = 'Semua kolom harus diisi';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username hanya boleh berisi huruf, angka, dan underscore (_).';
    } elseif (strlen($nik) !== 16 || !ctype_digit($nik)) {
        $error = 'NIK harus 16 digit angka';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        // Cek NIK dan Email sudah terdaftar atau belum
        $checkNik = Database::query("SELECT user_id FROM user WHERE nik = ?", [$nik])->fetch();
        $checkUsername = Database::query("SELECT user_id FROM user WHERE username = ?", [$username])->fetch();
        $checkEmail = Database::query("SELECT user_id FROM user WHERE email = ?", [$email])->fetch();

        if ($checkNik) {
            $error = 'NIK sudah terdaftar';
        } elseif ($checkUsername) {
            $error = 'Username sudah terdaftar, silakan gunakan username lain.';
        } elseif ($checkEmail) {
            $error = 'Email sudah terdaftar';
        } else {
            // Insert user baru
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO user (nama, nik, username, no_telepon, email, password)
                    VALUES (?, ?, ?, ?, ?, ?)";

            try {
                Database::query($sql, [$nama, $nik, $username, $no_hp, $email, $hashedPassword]);

                Session::set('success', 'Registrasi berhasil!'); // Tetap set session sbg fallback
                $registration_successful = true; // Set flag untuk JavaScript
                // Kosongkan nilai form setelah sukses
                $username_val = $nik_val = $nama_val = $no_hp_val = $email_val = '';


            } catch (Exception $e) {
                error_log("Registrasi Error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat registrasi. Silakan coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - <?= SITE_NAME ?></title>

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
        /* Style untuk modal agar tidak ikut scroll */
        #successModal {
             overscroll-behavior: contain;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2 text-center sr-only">Formulir Registrasi</h2>
                <p class="text-3xl font-medium text-gray-800 mb-6 text-center">Daftar Akun</p>

                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <input type="text" id="nama_lengkap" name="nama_lengkap" required
                                   maxlength="100"
                                   value="<?= $nama_val ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Nama Lengkap">
                        </div>

                        <div class="md:col-span-2">
                            <input type="text" id="nik" name="nik" required
                                   maxlength="16" pattern="[0-9]{16}" inputmode="numeric"
                                   title="NIK harus terdiri dari 16 digit angka."
                                   value="<?= $nik_val ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="NIK">
                        </div>
                        <div class="md:col-span-2">
                            <input type="text" id="username" name="username" required
                                   maxlength="50" pattern="[a-zA-Z0-9_]+"
                                   title="Username hanya boleh berisi huruf, angka, dan underscore (_)."
                                   value="<?= $username_val ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Username">
                        </div>
 
                        <div class="md:col-span-2">
                            <input type="tel" id="no_hp" name="no_hp" required
                                   maxlength="20" pattern="[0-9]*" inputmode="numeric"
                                   value="<?= $no_hp_val ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="No Telepon">
                        </div>

                        <div class="md:col-span-2">
                            <input type="email" id="email" name="email" required
                                   maxlength="50"
                                   value="<?= $email_val ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Email">
                        </div>

                        <div class="md:col-span-2">
                            <div class="relative">
                                <input type="password" id="password" name="password" required
                                       minlength="6" maxlength="50"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Password">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 text-gray-500 hover:text-gray-700 focus:outline-none">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition mt-6">
                        <i class="fas fa-user-plus mr-2"></i>Daftar Sekarang
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Sudah punya akun?
                        <a href="login.php" class="text-gray-600 hover:text-blue-600">
                            Masuk di sini
                        </a>
                    </p>
                    <a href="index.php" class="inline-block mt-4 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali ke Beranda
                    </a>
                </div>
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

    </script>

    <?php if ($registration_successful): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Registrasi Berhasil!',
            confirmButtonText: 'OK',
            allowOutsideClick: false, // Mencegah user menutup dengan klik di luar
            allowEscapeKey: false // Mencegah user menutup dengan tombol Esc
        }).then((result) => {
            // Jika user menekan tombol OK
            if (result.isConfirmed) {
                window.location.href = 'login.php';
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>