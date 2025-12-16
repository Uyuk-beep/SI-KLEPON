<?php
require_once '../config.php';
Session::start();

if (!Session::isAdmin()) {
    redirect('login.php');
}

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();
if (!$admin) {
    Session::destroy();
    redirect('login.php');
}

$success = Session::get('success', '');
$error = Session::get('error', '');
Session::remove('success');
Session::remove('error');

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? 0;
            $kategori = sanitize($_POST['kategori'] ?? '');
            $label = sanitize($_POST['label'] ?? '');
            $jumlah = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_INT);
            $tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);

            if (empty($kategori) || empty($label) || $jumlah === false || $tahun === false) {
                throw new Exception('Semua field wajib diisi dan harus valid.');
            }

            if ($action === 'add') {
                $sql = "INSERT INTO statistik (kategori, label, jumlah, tahun) VALUES (?, ?, ?, ?)";
                Database::query($sql, [$kategori, $label, $jumlah, $tahun]);
                Session::set('success', 'Data statistik berhasil ditambahkan.');
            } else {
                $sql = "UPDATE statistik SET kategori = ?, label = ?, jumlah = ?, tahun = ? WHERE id = ?";
                Database::query($sql, [$kategori, $label, $jumlah, $tahun, $id]);
                Session::set('success', 'Data statistik berhasil diperbarui.');
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            if (empty($id)) {
                throw new Exception('ID data tidak valid.');
            }
            Database::query("DELETE FROM statistik WHERE id = ?", [$id]);
            Session::set('success', 'Data statistik berhasil dihapus.');
        }
    } catch (Exception $e) {
        Session::set('error', $e->getMessage());
    }
    redirect('admin/statistik.php');
}

// Ambil semua data statistik
$statistikData = Database::query("SELECT * FROM statistik ORDER BY tahun DESC, kategori ASC, label ASC")->fetchAll();

// --- DATA DUMMY UNTUK TESTING PAGINATION ---
$use_dummy_data = false; // Ganti menjadi false untuk menonaktifkan
if ($use_dummy_data) {
    $kategori_dummy = ['Data Penduduk', 'Pekerjaan', 'Pendidikan', 'Usia'];
    for ($i = 1; $i <= 50; $i++) {
        $statistikData[] = [
            'id' => 9000 + $i,
            'kategori' => $kategori_dummy[array_rand($kategori_dummy)],
            'label' => 'Label Dummy ' . $i,
            'jumlah' => rand(10, 500),
            'tahun' => date('Y') - rand(0, 2)
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

    <title>Kelola Statistik - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
        <!-- Sidebar -->
        <?php require_once 'sidebar_admin.php'; ?>

        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <!-- Navbar -->
            <?php require_once 'navbar_admin.php'; ?>

            <!-- Main Content -->
            <main class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8 flex-grow">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <div class="flex flex-col lg:flex-row justify-between lg:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Data Statistik</h2>
                            <p class="text-sm text-gray-500 mt-1 mb-2">Kelola data statistik yang akan ditampilkan pada halaman statistik.</p>
                        </div>
                        <button onclick="showModal('add')" class="w-full lg:w-auto bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Tambah Data
                        </button>
                    </div>

                    <!-- Kontrol Tabel -->
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

                    <div id="tableContainer" class="overflow-x-auto relative border rounded-lg">
                        <table id="myTable" class="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Kategori</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Label</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Jumlah</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Tahun</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($statistikData as $data): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4 text-sm text-gray-600 text-center align-middle"></td> <!-- No. diisi oleh JS -->
                                    <td class="px-3 py-4 text-sm font-normal text-gray-900 align-middle truncate-text" style="max-width: 150px;"><?= htmlspecialchars($data['kategori']) ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle truncate-text" style="max-width: 200px;"><?= htmlspecialchars($data['label']) ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle text-center"><?= htmlspecialchars($data['jumlah']) ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-600 align-middle text-center"><?= htmlspecialchars($data['tahun']) ?></td>
                                    <td class="px-3 py-4 text-center align-middle whitespace-nowrap">
                                        <button onclick='showDetailModal(<?= htmlspecialchars(json_encode($data)) ?>)' title="Detail" class="inline-flex items-center px-3 py-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-eye mr-1"></i>Detail</button>
                                        <button onclick='editModal(<?= htmlspecialchars(json_encode($data)) ?>)' title="Edit" class="inline-flex items-center px-3 py-2 bg-green-100 hover:bg-green-600 text-green-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-edit mr-1"></i>Edit</button>
                                        <form method="POST" class="inline" onsubmit="confirmDelete(event, <?= $data['id'] ?>)">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $data['id'] ?>">
                                            <button type="submit" title="Hapus" class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-trash mr-1"></i>Hapus</button>
                                        </form>
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
                        <p>Tidak ada data statistik yang cocok dengan pencarian Anda.</p>
                    </div>
                </div>
            </main>

        </div>
    </div>

    <!-- Modal Detail -->
    <div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Detail Statistik</h3>
                <button onclick="closeModal('detailModal')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <div id="detailContent" class="space-y-3"></div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('detailModal')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="modalForm" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-bold">Tambah Data Statistik</h3>
                <button onclick="closeModal('modalForm')" class="text-gray-600 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            
            <form method="POST" id="statistikForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="dataId">
                
                <div class="space-y-4">
                    <div class="form-group">
                        <label for="kategori" class="block font-semibold mb-2">Kategori <span class="text-red-500">*</span></label>
                        <input type="text" name="kategori" id="kategori" required maxlength="100" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Contoh: Data Penduduk">

                    </div>
                    <div class="form-group">
                        <label for="label" class="block font-semibold mb-2">Label/Keterangan <span class="text-red-500">*</span></label>
                        <input type="text" name="label" id="label" required maxlength="100" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Contoh: Laki-laki, PNS, 0-5 Tahun">
                    </div>
                    <div class="form-group">
                        <label for="jumlah" class="block font-semibold mb-2">Jumlah <span class="text-red-500">*</span></label>
                        <input type="number" name="jumlah" id="jumlah" required min="0" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Masukkan angka">
                    </div>
                    <div class="form-group">
                        <label for="tahun" class="block font-semibold mb-2">Tahun <span class="text-red-500">*</span></label>
                        <input type="number" name="tahun" id="tahun" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Contoh: <?= date('Y') ?>" value="<?= date('Y') ?>">
                    </div>
                </div>
                
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                    <button type="button" onclick="closeModal('modalForm')" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">
                        Batal
                    </button>
                </div>
            </form> 
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // SweetAlert2 notifications
        <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?= addslashes($success) ?>',
            timer: 2000,
            showConfirmButton: false
        });
        <?php endif; ?>
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '<?= addslashes($error) ?>'
        });
        <?php endif; ?>

        function showDetailModal(data) {
            const content = `
                <div><p class="font-semibold">Kategori:</p><p class="p-2 bg-gray-50 rounded break-words">${data.kategori || '-'}</p></div>
                <div><p class="font-semibold">Label/Keterangan:</p><p class="p-2 bg-gray-50 rounded break-words">${data.label || '-'}</p></div>
                <div><p class="font-semibold">Jumlah:</p><p class="p-2 bg-gray-50 rounded break-words">${data.jumlah || '0'}</p></div>
                <div><p class="font-semibold">Tahun:</p><p class="p-2 bg-gray-50 rounded break-words">${data.tahun || '-'}</p></div>
            `;
            document.getElementById('detailContent').innerHTML = content;
            document.getElementById('detailModal').classList.remove('hidden');
        }

        function showModal(action) {
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = 'Tambah Data Statistik';
            document.getElementById('modalForm').classList.remove('hidden');
            document.getElementById('statistikForm').reset();
            document.getElementById('dataId').value = '';
            document.getElementById('tahun').value = new Date().getFullYear();
        }

        function editModal(data) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('modalTitle').textContent = 'Edit Data Statistik';
            document.getElementById('modalForm').classList.remove('hidden');
            
            document.getElementById('dataId').value = data.id;
            document.getElementById('kategori').value = data.kategori;
            document.getElementById('label').value = data.label;
            document.getElementById('jumlah').value = data.jumlah;
            document.getElementById('tahun').value = data.tahun;
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function confirmDelete(event, id) {
            event.preventDefault(); 
            const form = event.target.closest('form');
            Swal.fire({
                title: 'Konfirmasi Hapus?',
                text: `Anda yakin ingin menghapus data ini secara permanen?`,
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
                // Hanya tampilkan tombol jika lebih dari 1 halaman
                if (totalPages > 1) { 
                    let startPage = Math.max(1, currentPage - 2);
                    let endPage = Math.min(totalPages, currentPage + 2);

                    if (currentPage <= 3) endPage = Math.min(5, totalPages);
                    if (currentPage > totalPages - 3) startPage = Math.max(1, totalPages - 4);

                    for (let i = startPage; i <= endPage; i++) {
                        const pageButton = document.createElement('button');
                        pageButton.textContent = i;
                        pageButton.onclick = () => changePage(i);
                        pageButton.className = `px-3 py-1 border rounded-lg text-sm transition-colors ${
                            i === currentPage ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100'
                        }`;
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
        
        document.addEventListener('DOMContentLoaded', () => { if (document.getElementById('tableEntries')) applyFilters(); });
    </script>
</body>
</html>