<?php
require_once '../config.php';
Session::start();

$success = Session::get('success', '');
Session::remove('success');
$error = Session::get('error', '');
Session::remove('error');

if (!Session::isAdmin()) {
    redirect('login.php');
}

$adminId = Session::getAdminId();
$admin = Database::query("SELECT admin_id, nama, role FROM admin WHERE admin_id = ?", [$adminId])->fetch();
if (!$admin) {
    Session::destroy();
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $kegiatanId = $_POST['kegiatan_id'] ?? 0;
        $judul = trim($_POST['judul'] ?? ''); // Hanya trim, tidak sanitize
        $deskripsi = trim($_POST['deskripsi'] ?? ''); // Hanya trim, tidak sanitize
        
        $newFoto = '';
        $fotoUpdated = false;
        
        if (mb_strlen($judul) > 200) {
            $error = 'Judul kegiatan tidak boleh melebihi 200 karakter.';
        } elseif (mb_strlen($deskripsi) > 2000) {
            $error = 'Isi kegiatan tidak boleh melebihi 2000 karakter.';
        } elseif (empty($judul) || empty($deskripsi)) {
            $error = 'Judul dan isi kegiatan harus diisi.';
        } else {
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['foto'], 'kegiatan');
                if ($uploadResult) {
                    // Jika upload file BARU berhasil, baru hapus file LAMA (jika sedang mode edit)
                    if ($action === 'edit' && $kegiatanId > 0) {
                        $oldKegiatan = Database::query("SELECT foto FROM kegiatan WHERE kegiatan_id = ?", [$kegiatanId])->fetch();
                        $oldFotoPath = $oldKegiatan['foto'] ?? '';
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
            
            if (empty($error)) {
                if ($action === 'add') {
                    $sql = "INSERT INTO kegiatan (judul, deskripsi, foto) VALUES (?, ?, ?)";
                    $params = [$judul, $deskripsi, $newFoto];
                    if(Database::query($sql, $params)) {
                         Session::set('success', 'Kegiatan berhasil ditambahkan');
                    } else {
                         $error = 'Gagal menambahkan data.';
                    }
                } else { // Edit
                    if ($fotoUpdated) {
                        $sql = "UPDATE kegiatan SET judul = ?, deskripsi = ?, foto = ? WHERE kegiatan_id = ?";
                        $params = [$judul, $deskripsi, $newFoto, $kegiatanId];
                    } else {
                        $sql = "UPDATE kegiatan SET judul = ?, deskripsi = ? WHERE kegiatan_id = ?";
                        $params = [$judul, $deskripsi, $kegiatanId];
                    }
                    if(Database::query($sql, $params)) {
                        Session::set('success', 'Kegiatan berhasil diperbarui');
                    } else {
                        $error = 'Gagal memperbarui data.';
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $kegiatanId = $_POST['kegiatan_id'] ?? 0;
        // 1. Ambil nama file dari database SEBELUM menghapus record
        $oldKegiatan = Database::query("SELECT foto FROM kegiatan WHERE kegiatan_id = ?", [$kegiatanId])->fetch();
        
        // 2. Hapus record dari database
        if(Database::query("DELETE FROM kegiatan WHERE kegiatan_id = ?", [$kegiatanId])->rowCount() > 0) {
            // 3. JIKA record berhasil dihapus, baru hapus file fisiknya
            $oldFotoPath = $oldKegiatan['foto'] ?? ''; 
            if (!empty($oldFotoPath) && defined('UPLOAD_DIR') && file_exists(UPLOAD_DIR . $oldFotoPath)) {
                @unlink(UPLOAD_DIR . $oldFotoPath);
            }
            Session::set('success', 'Kegiatan berhasil dihapus.');
        } else {
            Session::set('error', 'Gagal menghapus kegiatan.');
        }
    }
    
    if (!empty($error)) Session::set('error', $error);
    redirect('admin/kegiatan.php'); 
}

$kegiatan = Database::query(
    "SELECT kegiatan_id, judul, deskripsi, foto, created_at
    FROM kegiatan
    ORDER BY created_at DESC"
)->fetchAll();

// --- DATA DUMMY UNTUK TESTING PAGINATION ---
$use_dummy_data = false; // Ganti menjadi false untuk menonaktifkan
if ($use_dummy_data && count($kegiatan) < 10) {
    for ($i = 1; $i <= 100; $i++) {
        $kegiatan[] = [
            'kegiatan_id' => 8000 + $i,
            'judul' => 'Kegiatan Dummy ' . $i,
            'deskripsi' => 'Ini adalah deskripsi untuk kegiatan dummy nomor ' . $i . '. Konten ini dibuat untuk keperluan testing pagination dan fitur lainnya.',
            'foto' => '', // Biarkan kosong atau beri path placeholder jika ada
            'created_at' => date('Y-m-d H:i:s', strtotime("-$i hours")),
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
    <title>Kelola Kegiatan - <?= SITE_NAME ?></title>
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
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 md:hidden z-30 pointer-events-none transition-opacity duration-300"></div>

    <div class="flex">        
        <?php require_once 'sidebar_admin.php'; ?>

        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <?php require_once 'navbar_admin.php'; ?>

            <main class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8 flex-grow">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <div class="flex flex-col lg:flex-row justify-between lg:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Data Kegiatan</h2>
                            <p class="text-sm text-gray-500 mt-1 mb-2">Kelola daftar kegiatan yang akan ditampilkan di halaman kegiatan.</p>
                        </div>
                        <button onclick="showModal('add')" class="w-full lg:w-auto bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Tambah Kegiatan
                        </button>
                    </div>
                    
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
                        
                        <div class="relative w-full lg:w-auto">
                            <input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="Cari..." class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <?php if (count($kegiatan) > 0): ?>
                    <div id="tableContainer" class="overflow-x-auto relative border rounded-lg">
                        <table id="myTable" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Foto</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider" style="max-width: 250px;">Judul</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider" style="max-width: 350px;">Deskripsi</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Dibuat</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($kegiatan as $p): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4 text-sm font-normal text-gray-900 text-center align-middle"></td> <!-- No. diisi oleh JS -->
                                    <td class="px-3 py-4 text-center align-middle">
                                        <?php if ($p['foto']): ?>
                                        <img src="../uploads/<?= htmlspecialchars($p['foto']) ?>" alt="<?= htmlspecialchars($p['judul']) ?>" class="w-12 h-12 object-cover rounded-md mx-auto">
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-4 text-sm text-gray-900 font-normal align-middle truncate-text" style="max-width: 250px;" title="<?= htmlspecialchars($p['judul']) ?>"><?= htmlspecialchars($p['judul']) ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle truncate-text" style="max-width: 350px;" title="<?= htmlspecialchars(strip_tags($p['deskripsi'])) ?>"><?= strip_tags($p['deskripsi']) ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-500 whitespace-nowrap align-middle"><?= formatTanggal($p['created_at'], 'd M Y') ?></td>
                                    <td class="px-3 py-4 text-sm font-medium text-center whitespace-nowrap align-middle">
                                        <div class="flex items-center justify-center space-x-2">
                                            <button onclick="showDetailModal(<?= htmlspecialchars(json_encode($p)) ?>)" title="Lihat Detail" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-eye mr-1"></i>Detail</button>
                                            <button onclick='editModal(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit" class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-edit mr-1"></i>Edit</button>
                                            <form method="POST" class="inline" onsubmit="confirmDelete(event, <?= $p['kegiatan_id'] ?>)">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="kegiatan_id" value="<?= $p['kegiatan_id'] ?>">
                                                <button type="submit" title="Hapus" class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-trash mr-1"></i>Hapus</button>
                                            </form>
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
                        <p>Tidak ada kegiatan yang cocok dengan pencarian Anda.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-inbox text-6xl mb-4"></i>
                        <p>Belum ada kegiatan</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="modalForm" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-xl w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-bold">Tambah Kegiatan</h3>
                <button onclick="closeModal()" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="kegiatan_id" id="kegiatanId">
                <div class="space-y-4">
                    <div>
                        <label class="block font-semibold mb-2">Judul <span class="text-red-500">*</span></label>
                        <input type="text" name="judul" id="judul" required maxlength="200" class="w-full px-4 py-2 border rounded-lg" placeholder="Judul kegiatan (Maks. 200 karakter)">
                    </div>
                    <div>
                        <label class="block font-semibold mb-2">Isi Kegiatan <span class="text-red-500">*</span></label>
                        <textarea name="deskripsi" id="deskripsi" required rows="8" maxlength="2000" class="w-full px-4 py-2 border rounded-lg" placeholder="Tulis isi kegiatan di sini (Maks. 2000 karakter)..."></textarea>
                    </div>
                    <div>
                        <label class="block font-semibold mb-2">Gambar</label>
                        <input type="file" name="foto" id="foto" accept="image/*" class="w-full px-4 py-2 border rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG. Maksimal 5MB.</p>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Simpan</button>
                    <button type="button" onclick="closeModal()" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">Batal</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Detail -->
    <div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-xl w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Detail Kegiatan</h3>
                <button onclick="closeDetailModal()" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <div id="detailContent" class="space-y-4 text-gray-700"></div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeDetailModal()" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Tutup</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        function showModal(action, data = null) { // --- Modal Logic ---
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = 'Tambah Kegiatan';
            document.getElementById('modalForm').classList.remove('hidden');
            document.getElementById('kegiatanId').value = '';
            document.getElementById('judul').value = '';
            document.getElementById('deskripsi').value = '';
            document.getElementById('foto').value = '';
        }

        function editModal(data) {
            if (typeof data === 'string') {
                try {
                    data = JSON.parse(data);
                } catch (e) {
                    console.error("Failed to parse JSON data for editModal:", e);
                    return;
                }
            }
             document.getElementById('formAction').value = 'edit';
            document.getElementById('modalTitle').textContent = 'Edit Kegiatan';
            document.getElementById('modalForm').classList.remove('hidden');
            document.getElementById('kegiatanId').value = data.kegiatan_id;
            document.getElementById('judul').value = data.judul;
            document.getElementById('deskripsi').value = data.deskripsi;
            document.getElementById('foto').value = '';
        }

        function closeModal() {
            document.getElementById('modalForm').classList.add('hidden');
        }

        function showDetailModal(data) {
            const formattedDeskripsi = data.deskripsi ? data.deskripsi.replace(/\n/g, '<br>') : 'Tidak ada deskripsi.';
            const content = `
                <div class="space-y-4">
                    <div class="mb-4 text-center">
                        ${data.foto ?
                            `<img src="../uploads/${data.foto}" alt="${data.judul}" class="w-full max-h-64 object-contain rounded-lg mx-auto shadow-md">` :
                            `<div class="w-full h-40 bg-gray-200 flex items-center justify-center rounded-lg text-gray-500"><i class="fas fa-image text-4xl"></i> No Image</div>`}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Judul:</p>
                        <p class="mt-1 p-2 bg-gray-50 rounded break-words">${data.judul}</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Isi Kegiatan:</p>
                        <div class="mt-1 p-2 bg-gray-50 rounded break-words prose max-w-none">${data.deskripsi}</div>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">Tanggal Dibuat:</p>
                        <p class="mt-1 p-2 bg-gray-50 rounded">${new Date(data.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</p>
                    </div>
                </div>
            `;
            document.getElementById('detailContent').innerHTML = content;
            document.getElementById('detailModal').classList.remove('hidden');
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }

        function confirmDelete(event, id) {
            event.preventDefault(); 
            const form = event.target.closest('form');
            Swal.fire({
                title: 'Konfirmasi Hapus?',
                text: "Anda yakin ingin menghapus kegiatan ini secara permanen?",
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
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const pageButtonsContainer = document.getElementById('pageButtons');
            const paginationControls = document.getElementById('paginationControls');

            if (totalFiltered === 0) {
                if(showingInfo) showingInfo.textContent = 'Menampilkan 0 dari 0 data';
                if(prevBtn) prevBtn.disabled = true;
                if(nextBtn) nextBtn.disabled = true;
                if(pageButtonsContainer) pageButtonsContainer.innerHTML = '';
                if(paginationControls) paginationControls.classList.add('hidden');
                return;
            }

            if(showingInfo) showingInfo.innerHTML = `Menampilkan ${startEntry} hingga ${endEntry} dari ${totalFiltered} data`;
            if(prevBtn) prevBtn.disabled = currentPage === 1;
            if(nextBtn) nextBtn.disabled = currentPage === totalPages;

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
            const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : entriesPerPage;
            const totalPages = Math.ceil(filteredRows.length / currentEntriesPerPage);
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                applyFilters();
            }
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.remove('no-transition');
            if (document.getElementById('tableEntries')) {
                applyFilters();
            }
        });

    </script>
</body>
</html>