<?php
// Perlu diingat, ini adalah file di direktori 'admin/', 
// sehingga 'require_once' menuju ke luar direktori.
require_once '../config.php';
Session::start();

// --- PENGAMBILAN NOTIFIKASI SESSION ---
$success = Session::get('success', '');
Session::remove('success');
$error = Session::get('error', '');
Session::remove('error');
// ------------------------------------

if (!Session::isAdmin()) {
    redirect('login.php'); // Asumsi: login.php ada di root atau di path yang benar
}

$adminId = Session::getAdminId();

// --- AMBIL DATA ADMIN UNTUK SIDEBAR ---
$admin = Database::query("SELECT admin_id, nama, role FROM admin WHERE admin_id = ?", [$adminId])->fetch();
if (!$admin) {
    Session::destroy();
    redirect('login.php');
}
// ------------------------------------

// Handle CRUD operations 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $strukturId = $_POST['struktur_id'] ?? 0;
        $nama = trim($_POST['nama'] ?? '');
        $posisi = trim($_POST['posisi'] ?? '');
        $urutan = filter_var($_POST['urutan'] ?? 10, FILTER_VALIDATE_INT);
        
        $newFoto = '';
        $fotoUpdated = false;
        
        if (empty($nama) || empty($posisi)) {
            $error = 'Nama dan Posisi harus diisi.';
        } else {
            
            // Proses Upload Foto
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['foto'], 'struktur');
                
                if ($uploadResult) {
                    // Jika upload file BARU berhasil, baru hapus file LAMA (jika sedang mode edit)
                    if ($action === 'edit' && $strukturId > 0) {
                        $oldStruktur = Database::query("SELECT foto FROM struktur WHERE struktur_id = ?", [$strukturId])->fetch();
                        $oldFotoPath = $oldStruktur['foto'] ?? '';

                        // Hapus file lama setelah file baru berhasil di-upload
                        if (!empty($oldFotoPath) && defined('UPLOAD_DIR') && file_exists(UPLOAD_DIR . $oldFotoPath)) {
                            @unlink(UPLOAD_DIR . $oldFotoPath);
                        }
                    }

                    $newFoto = $uploadResult;
                    $fotoUpdated = true;
                } else {
                    $error = 'Gagal mengunggah gambar.';
                    Session::set('error', $error);
                }
            }
            
            // Jalankan Query CRUD
            if (empty($error)) {
                if ($action === 'add') {
                    $sql = "INSERT INTO struktur (nama, posisi, urutan, foto) 
                              VALUES (?, ?, ?, ?)";
                    $params = [$nama, $posisi, $urutan, $newFoto];
                    
                    if(Database::query($sql, $params)) {
                         Session::set('success', 'Data struktur berhasil ditambahkan');
                    } else {
                         $error = 'Gagal menambahkan data.';
                    }
                } else { // Edit
                    
                    if ($fotoUpdated) {
                        $sql = "UPDATE struktur SET nama = ?, posisi = ?, urutan = ?, foto = ? WHERE struktur_id = ?";
                        $params = [$nama, $posisi, $urutan, $newFoto, $strukturId];
                    } else {
                        $sql = "UPDATE struktur SET nama = ?, posisi = ?, urutan = ? WHERE struktur_id = ?";
                        $params = [$nama, $posisi, $urutan, $strukturId];
                    }
                    
                    if(Database::query($sql, $params)) {
                        Session::set('success', 'Data struktur berhasil diperbarui');
                    } else {
                        $error = 'Gagal memperbarui data.';
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $strukturId = $_POST['struktur_id'] ?? 0;
        
        // 1. Ambil nama file dari database SEBELUM menghapus record
        $oldStruktur = Database::query("SELECT foto FROM struktur WHERE struktur_id = ?", [$strukturId])->fetch();
        
        // 2. Hapus record dari database
        if(Database::query("DELETE FROM struktur WHERE struktur_id = ?", [$strukturId])->rowCount() > 0) {
            // 3. JIKA record berhasil dihapus, baru hapus file fisiknya
            $oldFotoPath = $oldStruktur['foto'] ?? ''; 
            if (!empty($oldFotoPath) && defined('UPLOAD_DIR') && file_exists(UPLOAD_DIR . $oldFotoPath)) {
                @unlink(UPLOAD_DIR . $oldFotoPath);
            }
            Session::set('success', 'Data struktur berhasil dihapus.');
        } else {
            Session::set('error', 'Gagal menghapus data struktur.');
        }
    }
    
    if (!empty($error)) Session::set('error', $error);
    
    redirect('admin/struktur.php'); 
}

// Ambil semua data struktur
$daftar_struktur = Database::query(
    "SELECT struktur_id, nama, posisi, urutan, foto, created_at
    FROM struktur
    ORDER BY urutan ASC, created_at DESC"
)->fetchAll();

// --- DATA DUMMY UNTUK TESTING PAGINATION ---
$use_dummy_data = false; // Ganti menjadi false untuk menonaktifkan
if ($use_dummy_data && count($daftar_struktur) < 10) {
    for ($i = 1; $i <= 50; $i++) {
        $daftar_struktur[] = [
            'struktur_id' => 9000 + $i,
            'nama' => 'Pejabat Dummy ' . $i,
            'posisi' => 'Staf Dummy ' . $i,
            'urutan' => 10 + $i,
            'foto' => '', // Biarkan kosong atau beri placeholder
            'created_at' => date('Y-m-d H:i:s', strtotime("-$i days"))
        ];
    }
}
// -------------------------------------------
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <link rel="apple-touch-icon" href="../assets/img/logopky.gif">

    <title>Struktur Organisasi - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <?php
    // --- LOGIKA UNTUK MENCEGAH KEDIPAN SIDEBAR (TERPUSAT) ---
    $body_class = '';
    if (isset($_COOKIE['sidebarOpen']) && $_COOKIE['sidebarOpen'] === 'false') { $body_class .= ' sidebar-closed'; }
    $body_class .= ' no-transition'; // Selalu tambahkan no-transition saat load
    ?>

    <style>
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
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <div class="flex flex-col lg:flex-row justify-between lg:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Data Struktur</h2>
                            <p class="text-sm text-gray-500 mt-1 mb-2">Kelola data pejabat dan staf yang akan ditampilkan pada halaman Beranda.</p>
                        </div>
                        <button onclick="showModal('add')" class="w-full lg:w-auto bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Tambah Data
                        </button>
                    </div>
                    
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 border-b pb-4 gap-4">
                        <div class="flex items-center space-x-2">
                            <label for="tableEntries" class="text-sm font-medium text-gray-700">Tampilkan</label>
                            <select id="tableEntries" onchange="changeEntries()" 
                                class="border border-gray-300 rounded-lg shadow-sm text-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                                <option value="10">10</option>
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
                    <?php if (count($daftar_struktur) > 0): ?>
                    
                   <div id="tableContainer" class="overflow-x-auto relative border rounded-lg">
                        <table id="myTable" class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                     <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Foto</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jabatan</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Dibuat</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($daftar_struktur as $s): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4 text-sm font-normal text-gray-900 text-center align-middle"></td> <!-- No. diisi oleh JS -->
                                    
                                   <td class="px-3 py-4 text-center align-middle">
                                        <?php if ($s['foto']): ?>
                                        <img src="../uploads/<?= htmlspecialchars($s['foto']) ?>"
                                            alt="<?= htmlspecialchars($s['nama']) ?>" class="w-12 h-12 object-cover rounded-md mx-auto">
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                  <td class="px-3 py-4 text-sm text-gray-900 font-normal align-middle truncate-text" style="max-width: 250px;" title="<?= htmlspecialchars($s['nama']) ?>">
                                        <?= htmlspecialchars($s['nama']) ?>
                                    </td>
                                    
                                   <td class="px-3 py-4 text-sm text-gray-600 align-middle truncate-text" style="max-width: 350px;" title="<?= htmlspecialchars($s['posisi']) ?>">
                                        <?= htmlspecialchars($s['posisi']) ?>
                                    </td>
                                    
                                    <td class="px-3 py-4 text-sm text-gray-500 whitespace-nowrap align-middle">
                                        <?= formatTanggal($s['created_at'], 'd M Y')
                                        ?>
                                    </td>
                                    
                                     <td class="px-3 py-4 text-sm font-medium text-center whitespace-nowrap align-middle">
                                        <div class="flex items-center justify-center space-x-2">
                                            
                                            <button onclick="showDetailModal(<?= htmlspecialchars(json_encode($s)) ?>)" 
                                                    title="Lihat Detail"
                                                    class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                    <i class="fas fa-eye mr-1"></i>Detail
                                            </button>

                                            <button onclick='editModal(<?= json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                                                    title="Edit"
                                                   class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                            </button>
                                            
                                            <form method="POST" class="inline" onsubmit="confirmDelete(event, <?= $s['struktur_id'] ?>, 'data struktur')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="struktur_id" value="<?= $s['struktur_id'] ?>">
                                                <button type="submit" 
                                                            title="Hapus"
                                                            class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium">
                                                            <i class="fas fa-trash mr-1"></i>Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="paginationContainer" class="flex flex-col sm:flex-row justify-between items-center pt-4 mt-4 border-t border-gray-200 gap-4 sm:gap-0">
                        <div id="showingInfo" class="text-sm text-gray-700 text-center sm:text-left">
                            </div>
                        <div class="flex space-x-1" id="paginationControls">
                            <button id="prevBtn" onclick="changePage(currentPage - 1)" disabled
                                class="px-3 py-1 border border-gray-300 rounded-lg text-gray-500 bg-white hover:bg-gray-100 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="hidden lg:inline">Sebelumnya</span>
                                <span class="lg:hidden">&lt;</span>
                            </button>
                            <span id="pageButtons" class="flex space-x-1">
                                </span>
                            <button id="nextBtn" onclick="changePage(currentPage + 1)" disabled
                                class="px-3 py-1 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-100 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="hidden lg:inline">Berikutnya</span>
                                <span class="lg:hidden">&gt;</span>
                            </button>
                        </div>
                    </div>
                    <div id="noResultsMessage" class="hidden text-center py-12 text-gray-500">
                        <i class="fas fa-search text-5xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">Data Tidak Ditemukan</h3>
                        <p>Tidak ada data struktur yang cocok dengan pencarian Anda.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-inbox text-6xl mb-4"></i>
                        <p>Belum ada data struktur</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>

    </div>

    <div id="modalForm" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-xl w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-bold">Tambah Data Struktur</h3>
                <button onclick="closeModal()" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="struktur_id" id="strukturId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block font-semibold mb-2">Nama <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" id="nama" required maxlength="100"
                               class="w-full px-4 py-2 border rounded-lg"
                               placeholder="Nama Lengkap">
                    </div>
                    
                    <div>
                        <label class="block font-semibold mb-2">Jabatan <span class="text-red-500">*</span></label>
                        <input type="text" name="posisi" id="posisi" required maxlength="100"
                               class="w-full px-4 py-2 border rounded-lg"
                               placeholder="Posisi dalam struktur organisasi">
                    </div>

                    <div>
                        <label class="block font-semibold mb-2">Urutan Tampil</label>
                        <input type="number" name="urutan" id="urutan" min="1" max="99" oninput="if(this.value.length > 2) this.value = this.value.slice(0, 2);" value="10"
                               class="w-full px-4 py-2 border rounded-lg"
                               placeholder="Angka kecil tampil lebih dulu (misal: 1 untuk Lurah)">
                    </div>

                    <div>
                        <label class="block font-semibold mb-2">Foto</label>
                        <input type="file" name="foto" id="foto" accept="image/*"
                               class="w-full px-4 py-2 border rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 5MB.</p>
                    </div>
                </div>
                
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail -->
    <div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Detail Struktur</h3>
                <button onclick="closeDetailModal()" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div id="detailContent" class="space-y-4 text-gray-700">
                <!-- Konten akan diisi oleh JavaScript -->
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeDetailModal()" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>


    <script>
        
        // --- LOGIKA MODAL EDIT/ADD ---
        function showModal(action) {
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = 'Tambah Data Struktur';
            document.getElementById('modalForm').classList.remove('hidden');
            
            document.getElementById('strukturId').value = '';
            document.getElementById('nama').value = '';
            document.getElementById('posisi').value = '';
            document.getElementById('urutan').value = '10';
            document.getElementById('foto').value = '';
        }

        function editModal(data) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('modalTitle').textContent = 'Edit Data Struktur';
            document.getElementById('modalForm').classList.remove('hidden');
            
            document.getElementById('strukturId').value = data.struktur_id;
            document.getElementById('nama').value = data.nama;
            document.getElementById('posisi').value = data.posisi;
            document.getElementById('urutan').value = data.urutan;
            document.getElementById('foto').value = '';
        }

        function closeModal() {
            document.getElementById('modalForm').classList.add('hidden');
        }
        
        // --- FUNGSI MODAL DETAIL ---
        function showDetailModal(data) {
            const formattedCreatedAt = new Date(data.created_at).toLocaleDateString('id-ID', {
                day: 'numeric', month: 'long', year: 'numeric'
            });

            const content = `
                <div class="space-y-4">
                    <div class="mb-4 text-center">
                        ${data.foto ? `
                            <img src="../uploads/${data.foto}" alt="${data.nama}" class="w-40 h-56 object-cover rounded-lg mx-auto shadow-lg border-4 border-gray-200">` :
                            `<div class="w-40 h-56 bg-gray-200 flex items-center justify-center rounded-lg mx-auto text-gray-500"><i class="fas fa-user text-5xl"></i></div>`}
                    </div>
                    
                    <div>
                        <p class="font-semibold text-gray-800">Nama:</p>
                        <p class="mt-1 p-2 bg-gray-50 rounded break-words">${data.nama}</p>
                    </div>

                    <div>
                        <p class="font-semibold text-gray-800">Posisi:</p>
                        <p class="mt-1 p-2 bg-gray-50 rounded break-words">${data.posisi}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="font-semibold text-gray-800">Urutan:</p><p class="mt-1 p-2 bg-gray-50 rounded">${data.urutan}</p></div>
                        <div><p class="font-semibold text-gray-800">Dibuat pada:</p><p class="mt-1 p-2 bg-gray-50 rounded">${formattedCreatedAt}</p></div>
                    </div>
                </div>
            `;
            document.getElementById('detailContent').innerHTML = content;
            document.getElementById('detailModal').classList.remove('hidden');
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }

        // --- LOGIKA MANUAL FILTER, SEARCH, DAN PAGINATION ---
        const tableBody = document.getElementById('tableBody');
        const allRows = tableBody ? Array.from(tableBody.getElementsByTagName('tr')) : [];
        let filteredRows = []; 
        
        let currentPage = 1;
        let entriesPerPage = 10; 
        
        function updatePaginationControls(totalFiltered, totalRows) {
            const currentEntriesPerPage = (entriesPerPage === -1) ? totalFiltered : entriesPerPage;
            const totalPages = Math.ceil(totalFiltered / currentEntriesPerPage);
            const startEntry = (currentPage - 1) * currentEntriesPerPage + 1;
            const endEntry = Math.min(currentPage * currentEntriesPerPage, totalFiltered);

            const showingInfo = document.getElementById('showingInfo');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const pageButtonsContainer = document.getElementById('pageButtons');
            const paginationControls = document.getElementById('paginationControls');

            if (totalFiltered === 0) {
                if (showingInfo) showingInfo.textContent = 'Menampilkan 0 dari 0 data';
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;
                if (pageButtonsContainer) pageButtonsContainer.innerHTML = '';
                if (paginationControls) paginationControls.classList.add('hidden');
                return;
            }
            
            if (showingInfo) showingInfo.innerHTML = `Menampilkan ${startEntry} hingga ${endEntry} dari ${totalFiltered} data`;
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage === totalPages;
            
            if (pageButtonsContainer) {
                pageButtonsContainer.innerHTML = ''; // Kosongkan tombol
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
            
            if (totalPages <= 1 || entriesPerPage === -1) {
                 paginationControls.classList.add('hidden');
            } else {
                 paginationControls.classList.remove('hidden');
            }
        }

        function applyFilters() {
            const searchText = document.getElementById('tableSearch').value.toLowerCase();
            const tableContainer = document.getElementById('tableContainer');
            const noResultsMessage = document.getElementById('noResultsMessage');
            const paginationContainer = document.getElementById('paginationContainer');
            
            filteredRows = allRows.filter(row => {
                const cellText = row.textContent.toLowerCase();
                return cellText.includes(searchText);
            });
            
            if (filteredRows.length === 0) {
                if(tableContainer) tableContainer.classList.add('hidden');
                if(paginationContainer) paginationContainer.classList.add('hidden');
                if(noResultsMessage) noResultsMessage.classList.remove('hidden');
            } else {
                if(tableContainer) tableContainer.classList.remove('hidden');
                if(paginationContainer) paginationContainer.classList.remove('hidden');
                if(noResultsMessage) noResultsMessage.classList.add('hidden');

                const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : entriesPerPage;
                const maxPage = Math.max(1, Math.ceil(filteredRows.length / currentEntriesPerPage));
                if (currentPage > maxPage) currentPage = 1;

                const start = (currentPage - 1) * currentEntriesPerPage;
                const end = start + currentEntriesPerPage;
                
                tableBody.innerHTML = ''; // Kosongkan tbody
                filteredRows.slice(start, end).forEach((row, index) => {
                    row.cells[0].textContent = start + index + 1;
                    tableBody.appendChild(row);
                });
            }
            updatePaginationControls(filteredRows.length, allRows.length);
        }

        window.changeEntries = function() {
            const selectElement = document.getElementById('tableEntries');
            entriesPerPage = parseInt(selectElement.value);
            currentPage = 1;
            applyFilters();
        };

        window.filterTable = function() {
            currentPage = 1;
            applyFilters();
        };

        window.changePage = function(newPage) {
            const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : entriesPerPage;
            const totalPages = Math.ceil(filteredRows.length / currentEntriesPerPage);
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                applyFilters();
            }
        };
        
        document.getElementById('prevBtn').onclick = () => window.changePage(currentPage - 1);
        document.getElementById('nextBtn').onclick = () => window.changePage(currentPage + 1);

        // --- SweetAlert2 Logic ---
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
        // --- END SweetAlert2 Logic ---


        // Inisialisasi tampilan awal
        window.onload = function() {
            if (document.getElementById('tableEntries')) {
                filteredRows = [...allRows];
                applyFilters(); 
            }
        };

    </script>
</body>
</html>