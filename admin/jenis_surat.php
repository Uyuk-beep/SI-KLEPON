<?php
require_once '../config.php';
Session::start();

// --- PENGAMBILAN NOTIFIKASI SESSION ---
$success = Session::get('success', '');
Session::remove('success');
$error = Session::get('error', '');
Session::remove('error');
// ------------------------------------

// Cek login dan pastikan hanya super_admin yang bisa akses
if (!Session::isAdmin() || Session::get('admin_role') !== 'super_admin') {
    Session::set('error', 'Anda tidak memiliki hak akses ke halaman ini.');
    redirect('admin/dashboard.php');
}

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id_jenis_surat'] ?? 0;
            $nama_surat = sanitize($_POST['nama_surat'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($nama_surat)) {
                throw new Exception('Nama surat wajib diisi.');
            }

            if ($action === 'add') {
                Database::query("INSERT INTO jenis_surat (nama_surat, is_active) VALUES (?, ?)", [$nama_surat, $is_active]);
                Session::set('success', 'Jenis surat berhasil ditambahkan.');
            } else {
                Database::query("UPDATE jenis_surat SET nama_surat = ?, is_active = ? WHERE id_jenis_surat = ?", [$nama_surat, $is_active, $id]);
                Session::set('success', 'Jenis surat berhasil diperbarui.');
            }
        }
    } catch (Exception $e) {
        // Cek jika error karena duplikasi nama surat
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            Session::set('error', 'Nama surat sudah ada. Silakan gunakan nama lain.');
        } else {
            Session::set('error', $e->getMessage());
        }
    }
    redirect('admin/jenis_surat.php');
}

// Ambil semua data jenis surat
$jenisSuratList = Database::query("SELECT * FROM jenis_surat ORDER BY nama_surat ASC")->fetchAll();

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
    <title>Kelola Jenis Surat - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                            <h2 class="text-2xl font-bold text-gray-800">Kelola Jenis Surat</h2>
                            <p class="text-sm text-gray-500 mt-1 mb-2">Tambah, ubah, atau hapus jenis surat yang tersedia untuk pengajuan.</p>
                        </div>
                        <button onclick="showModal('add')" class="w-full lg:w-auto bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Tambah Jenis Surat
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
                            <input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="Cari jenis surat..." class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <?php if (count($jenisSuratList) > 0): ?>
                    <div id="tableContainer" class="overflow-x-auto relative border rounded-lg">
                        <table id="myTable" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama Surat</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($jenisSuratList as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4 text-sm text-gray-600 text-center align-middle"></td>
                                    <td class="px-3 py-4 text-sm font-normal text-gray-900 align-middle"><?= htmlspecialchars($item['nama_surat']) ?></td>
                                    <td class="px-3 py-4 text-center align-middle">
                                        <?php if ($item['is_active']): ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 whitespace-nowrap">Aktif</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 whitespace-nowrap">Tidak Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-4 text-center align-middle">
                                        <button onclick='editModal(<?= json_encode($item) ?>)' title="Edit" class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-edit mr-1"></i>Edit</button>
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
                        <p>Tidak ada jenis surat yang cocok dengan pencarian Anda.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-folder-open text-6xl mb-4"></i>
                        <p>Belum ada jenis surat yang ditambahkan.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="modalForm" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-bold">Tambah Jenis Surat</h3>
                <button onclick="closeModal()" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <form method="POST" id="jenisSuratForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id_jenis_surat" id="id_jenis_surat">
                <div class="space-y-4">
                    <div>
                        <label for="nama_surat" class="block font-semibold mb-2">Nama Surat <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_surat" id="nama_surat" required maxlength="100" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Contoh: Surat Keterangan Tidak Mampu (SKTM)">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">Aktifkan jenis surat ini</label>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- SweetAlert2 Notifikasi ---
        <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= addslashes($success) ?>', timer: 2000, showConfirmButton: false });
        <?php endif; ?>
        <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Oops...', text: '<?= addslashes($error) ?>' });
        <?php endif; ?>

        // --- Logika Modal ---
        function showModal(action) {
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = 'Tambah Jenis Surat';
            document.getElementById('modalForm').classList.remove('hidden');
            document.getElementById('jenisSuratForm').reset();
            document.getElementById('id_jenis_surat').value = '';
            document.getElementById('is_active').checked = true; // Default aktif
        }

        function editModal(data) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('modalTitle').textContent = 'Edit Jenis Surat';
            document.getElementById('modalForm').classList.remove('hidden');
            document.getElementById('id_jenis_surat').value = data.id_jenis_surat;
            document.getElementById('nama_surat').value = data.nama_surat;
            document.getElementById('is_active').checked = data.is_active == 1;
        }

        function closeModal() {
            document.getElementById('modalForm').classList.add('hidden');
        }

        // --- Logika Tabel, Paginasi, dan Pencarian ---
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

            if (filteredRows.length === 0) {
                if (tableContainer) tableContainer.classList.add('hidden');
                if (paginationContainer) paginationContainer.classList.add('hidden');
                if (noResultsMessage) noResultsMessage.classList.remove('hidden');
            } else {
                if (tableContainer) tableContainer.classList.remove('hidden');
                if (paginationContainer) paginationContainer.classList.remove('hidden');
                if (noResultsMessage) noResultsMessage.classList.add('hidden');

                const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : entriesPerPage;
                const maxPage = Math.max(1, Math.ceil(filteredRows.length / currentEntriesPerPage));
                if (currentPage > maxPage) currentPage = 1;

                const start = (currentPage - 1) * currentEntriesPerPage;
                const end = start + currentEntriesPerPage;

                tableBody.innerHTML = '';
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
            if (newPage < 1 || newPage > totalPages) return;
            currentPage = newPage;
            applyFilters();
        };

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('tableEntries')) {
                applyFilters();
            }
        });
    </script>
</body>
</html>