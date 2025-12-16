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

// --- LOGIKA UNTUK MENCEGAH KEDIPAN SIDEBAR (TERPUSAT) ---
$body_class = '';
// Defaultnya adalah sidebar tertutup (compact), untuk konsistensi dengan halaman admin.
// Sidebar akan terbuka jika cookie 'sidebarOpen' secara eksplisit bernilai 'true'.
if (!isset($_COOKIE['sidebarOpen']) || $_COOKIE['sidebarOpen'] !== 'true') {
    $body_class .= ' sidebar-closed';
}
$body_class .= ' no-transition'; // Selalu tambahkan no-transition saat load

$sql = "SELECT p.*, js.nama_surat
        FROM pengajuan_surat p 
        LEFT JOIN jenis_surat js ON p.id_jenis_surat = js.id_jenis_surat
        WHERE p.user_id = ? AND p.status = 'Selesai'";
$params = [$userId];

$sql .= " ORDER BY p.tgl_pengajuan DESC";

$pengajuanList = Database::query($sql, $params)->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsip Surat - <?= SITE_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <link rel="apple-touch-icon" href="../assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <?php require_once 'navbar.php'; ?>

            <!-- Page Content -->
            <div class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6">
                    <!-- Header -->
                    <div class="flex flex-col md:flex-row justify-between md:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800 text-center md:text-left">Arsip Surat</h2>
                            <p class="text-sm text-gray-500 mt-1 text-center md:text-left">Lihat dan unduh surat-surat yang telah selesai diproses.</p>
                        </div>
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
                            <input type="text" id="tableSearch" onkeyup="renderTable()" placeholder="Cari surat..." class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Tabel Pengajuan -->
                    <?php if (count($pengajuanList) > 0): ?>
                    <!-- Wrapper untuk memastikan overflow bekerja tanpa merusak layout flex parent. -->
                    <div class="w-full">
                      <!-- Kontainer dengan scrollbar horizontal -->
                      <div class="overflow-x-auto relative border rounded-lg" id="table-container">
                        <table class="min-w-full divide-y divide-gray-200" id="arsipTable">
                                <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr id="tableHeader">
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jenis Surat</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No Telepon</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Alamat</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">File Surat</th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($pengajuanList as $p): ?>
                                <tr class="hover:bg-gray-50" data-searchable>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle"></td> <!-- No. diisi oleh JS -->
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['nama_surat'] ?? 'N/A') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle"><?= htmlspecialchars($p['user_id'] ?? '') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 truncate" style="max-width: 200px;"><?= htmlspecialchars($p['nama_pengaju'] ?? '') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 truncate" style="max-width: 200px;"><?= htmlspecialchars($p['email_pengaju'] ?? '') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($p['no_telepon'] ?? '') ?></td>
                                    <td class="px-3 py-4 text-sm text-gray-800 truncate" style="max-width: 150px;"><?= htmlspecialchars($p['alamat'] ?? '-') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatTanggal($p['tgl_pengajuan'], 'd M Y') ?></td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-800 text-center align-middle">
                                        <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1.5"></i> Selesai
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center align-middle">
                                        <?php if (!empty($p['file_surat']) && file_exists(UPLOAD_DIR . $p['file_surat'])): ?>
                                            <button onclick='showDocumentPreview("<?= htmlspecialchars($p['file_surat']) ?>", <?= htmlspecialchars(json_encode($p)) ?>)' class="inline-flex items-center px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200 text-xs font-medium">Lihat</button>
                                        <?php elseif (!empty($p['file_surat'])): ?>
                                            <span class="text-xs text-red-500 italic">Tidak ada</span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <?php if (!empty($p['file_surat']) && file_exists(UPLOAD_DIR . $p['file_surat'])): ?>
                                            <?php
                                                // Membuat nama file yang deskriptif
                                                $downloadFileName = htmlspecialchars($p['nama_surat'] . ' - ' . $p['nama_pengaju'] . '.pdf');
                                            ?>
                                            <a href="../uploads/<?= htmlspecialchars($p['file_surat']) ?>" download="<?= $downloadFileName ?>" title="Unduh Surat" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition duration-200 text-xs font-medium">
                                                <i class="fas fa-download mr-2"></i>Unduh Surat
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500 italic">File surat tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                      </div>
                    </div>

                    <div id="noResultsMessage" class="hidden text-center py-12 text-gray-500">
                        <i class="fas fa-search text-5xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600">Data Tidak Ditemukan</h3>
                        <p>Tidak ada arsip yang cocok dengan pencarian Anda.</p>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-center pt-4 mt-4 border-t border-gray-200 gap-4 md:gap-0">
                        <div id="showingInfo" class="text-sm text-gray-700 text-center md:text-left">
                            <!-- Info paginasi -->
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
                        <i class="fas fa-archive text-6xl mb-4"></i>
                        <p class="text-lg">Arsip surat Anda masih kosong.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Preview Dokumen -->
    <div id="documentPreviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] p-4">
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
</body>
<script>
    let allRows = [];
    let currentPage = 1;
    let entriesPerPage = 10;

    document.addEventListener('DOMContentLoaded', function() {
        const tableBody = document.getElementById('tableBody');
        if (tableBody && tableBody.rows.length > 0) {
            allRows = Array.from(tableBody.getElementsByTagName('tr'));
            const entriesSelect = document.getElementById('tableEntries');
            if (entriesSelect) {
                entriesPerPage = parseInt(entriesSelect.value, 10);
            }
            renderTable();
        }
    });

    function renderTable() {
        const tableBody = document.getElementById('tableBody');
        if (!tableBody) return;

        const tableContainer = document.getElementById('table-container');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const paginationContainer = document.querySelector('.flex.justify-between.items-center.pt-4');
        
        const searchText = document.getElementById('tableSearch').value.toLowerCase();
        const filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(searchText));

        const totalEntries = filteredRows.length;

        if (totalEntries === 0) {
            if(tableContainer) tableContainer.classList.add('hidden');
            if(paginationContainer) paginationContainer.classList.add('hidden');
            if(noResultsMessage) noResultsMessage.classList.remove('hidden');
            return;
        }

        if(tableContainer) tableContainer.classList.remove('hidden');
        if(paginationContainer) paginationContainer.classList.remove('hidden');
        if(noResultsMessage) noResultsMessage.classList.add('hidden');

        const effectiveEntriesPerPage = entriesPerPage === -1 ? totalEntries : entriesPerPage;
        const totalPages = Math.ceil(totalEntries / effectiveEntriesPerPage);

        // Ambil kontainer tombol paginasi
        const paginationControls = document.getElementById('paginationControls');

        // Sembunyikan tombol paginasi jika hanya ada satu halaman atau jika "Semua" dipilih
        if (totalPages <= 1 || entriesPerPage === -1) {
            if (paginationControls) paginationControls.style.display = 'none';
        } else {
            if (paginationControls) paginationControls.style.display = 'flex';
        }

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }

        const start = (currentPage - 1) * effectiveEntriesPerPage;
        const end = start + effectiveEntriesPerPage;
        const paginatedRows = filteredRows.slice(start, end);

        tableBody.innerHTML = '';
        paginatedRows.forEach(row => {
            // Update nomor urut berdasarkan halaman saat ini
            const rowIndex = filteredRows.indexOf(row);
            row.cells[0].textContent = start + (filteredRows.indexOf(row)) + 1;
            tableBody.appendChild(row);
        });
        updatePaginationControls(totalPages);
        updateShowingInfo(start, end, totalEntries);
    }

    function updateShowingInfo(start, end, total) {
        const infoDiv = document.getElementById('showingInfo');
        const endValue = Math.min(end, total);
        infoDiv.textContent = `Menampilkan ${start + 1} hingga ${endValue} dari ${total} data`;
    }

    function updatePaginationControls(totalPages) {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const pageButtonsContainer = document.getElementById('pageButtons');

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;

        pageButtonsContainer.innerHTML = '';

        // Logika untuk membuat tombol halaman (disamakan dengan pengajuan_surat.php)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (currentPage <= 3) {
            endPage = Math.min(5, totalPages);
        }
        if (currentPage > totalPages - 3) {
            startPage = Math.max(1, totalPages - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            pageButtonsContainer.appendChild(createPageButton(i));
        }
    }

    function createPageButton(pageNumber) {
        const button = document.createElement('button');
        button.textContent = pageNumber;
        button.onclick = () => changePage(pageNumber);
        button.className = `px-3 py-1 border border-gray-300 rounded-lg text-sm ${
            currentPage === pageNumber 
            ? 'bg-blue-500 text-white' 
            : 'bg-white text-gray-700 hover:bg-gray-100'
        }`;
        return button;
    }

    function changePage(page) {
        currentPage = page;
        renderTable();
    }

    function changeEntries() {
        entriesPerPage = parseInt(document.getElementById('tableEntries').value, 10);
        currentPage = 1; // Reset ke halaman pertama
        renderTable();
    }

    function showDocumentPreview(fileName, data = null) {
        const modal = document.getElementById('documentPreviewModal');
        const contentArea = document.getElementById('documentPreviewContent');
        const downloadBtn = document.getElementById('downloadButton');
        const fileUrl = `../uploads/${fileName}`;
        const fileExtension = fileName.split('.').pop().toLowerCase();

        if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
            contentArea.innerHTML = `<img src="${fileUrl}" alt="Preview Dokumen" class="max-w-full max-h-[60vh] object-contain mx-auto">`;
        } else if (fileExtension === 'pdf') {
            contentArea.innerHTML = `<iframe src="${fileUrl}" width="100%" height="500px"></iframe>`;
        } else {
            contentArea.innerHTML = `<div class="text-center p-8"><i class="fas fa-file-alt text-6xl text-gray-400"></i><p class="mt-4">Preview tidak tersedia untuk tipe file ini.</p></div>`;
        }

        let downloadFileName = fileName;
        if (data && fileName === data.file_surat) {
            downloadFileName = `${data.nama_surat} - ${data.nama_pengaju}.pdf`;
        }

        downloadBtn.href = fileUrl;
        downloadBtn.download = downloadFileName;

        modal.classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('hidden');
    }
</script>
</html>