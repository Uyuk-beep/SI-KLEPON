<?php
require_once '../config.php';

Session::start();

// Cek login dan pastikan admin (bukan user biasa)
if (!Session::isAdmin()) {
    redirect('login.php');
}

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();

// Double check - jika admin tidak ditemukan
if (!$admin) {
    Session::destroy();
    redirect('login.php');
}

// Filter laporan
$years_for_dropdown = Database::query("SELECT DISTINCT YEAR(tgl_pengajuan) as tahun FROM pengajuan_surat WHERE tgl_pengajuan IS NOT NULL ORDER BY tahun DESC")->fetchAll();
if (empty($years_for_dropdown)) {
    $years_for_dropdown = [['tahun' => date('Y')]];
}

$default_year = $years_for_dropdown[0]['tahun'];
$tahun_filter = isset($_GET['tahun']) && is_numeric($_GET['tahun']) ? intval($_GET['tahun']) : $default_year;
$entries_filter = isset($_GET['entries']) ? intval($_GET['entries']) : 10; // Default 10


// Query data berdasarkan tipe
$sql = "SELECT 
            u.nik, 
            u.nama, 
            YEAR(p.tgl_pengajuan) AS tahun,
            COUNT(p.pengajuan_id) AS total_pengajuan,
            p.user_id
        FROM pengajuan_surat p
        JOIN user u ON p.user_id = u.user_id
        WHERE p.status = 'selesai'
        GROUP BY u.user_id, YEAR(p.tgl_pengajuan)
        ORDER BY tahun DESC, u.nama ASC";

$data = Database::query($sql)->fetchAll();

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

    <title>Laporan - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @media print {
            body {
                background-color: white !important;
            }
            #main-content-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
                min-height: auto !important;
            }
            main { padding: 0 !important; }
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
        <div class="print:hidden"><?php require_once 'sidebar_admin.php'; // Sidebar ?></div>

        <div id="main-content-wrapper" class="flex-1 min-h-screen flex flex-col" style="min-width: 0;">
            <div class="print:hidden"><?php require_once 'navbar_admin.php'; // Navbar ?></div>

            <main class="pt-20 sm:pt-24 px-4 sm:px-5 md:px-6 lg:px-8 pb-8 flex-grow print:p-0 print:m-0">
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-5 lg:p-6 printable-content">
                    <div class="header-print hidden print:block text-center mb-8">
                        <img src="<?= BASE_URL ?>/assets/img/logopky.gif" alt="Logo" class="h-20 mx-auto">
                        <div class="mt-4">
                            <h2 class="text-2xl font-bold">PEMERINTAH KOTA PALANGKA RAYA</h2>
                            <h2 class="text-2xl font-bold">KECAMATAN SABANGAU</h2>
                            <h2 class="text-3xl font-extrabold">KELURAHAN KALAMPANGAN</h2>
                            <h3 id="print-report-title" class="text-xl font-bold mt-2 uppercase"></h3>
                            <p class="text-sm">Jalan Mahir Mahar KM 18, Kecamatan Sabangau Kota Palangka Raya</p>
                            <p class="text-sm">Email: kelurahankalampangan@palangkaraya.go.id</p>
                        </div>
                        <hr class="border-t-2 border-black my-4">
                    </div>

                    <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-6 gap-4 print:hidden">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Laporan Surat</h2>
                            <p class="text-sm text-gray-500 mt-1 mb-2">Data laporan pengajuan surat selesai per pengguna.</p>
                        </div>
                        <div class="w-full sm:w-auto">
                            <button type="button" onclick="printInNewTab()" class="w-full sm:w-auto bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition duration-200 flex items-center justify-center">
                                <i class="fas fa-print mr-2"></i>Cetak Laporan
                            </button>
                        </div>
                    </div>

                    <!-- Kontrol Tabel -->
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4 print:hidden border-b pb-4">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 w-full lg:w-auto">
                            <div class="flex items-center space-x-2">
                                <label for="tableEntries" class="text-sm font-medium text-gray-700">Tampilkan</label>
                                <select id="tableEntries" onchange="changeEntries()" class="border border-gray-300 rounded-lg shadow-sm text-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="10" <?= $entries_filter == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $entries_filter == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $entries_filter == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="-1" <?= $entries_filter == -1 ? 'selected' : '' ?>>Semua</option>
                                </select>
                                <span class="text-sm font-medium text-gray-700">data</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <label for="tahun" class="text-sm font-medium text-gray-700">Tahun:</label>
                                <select id="tahun" onchange="applyFilters()" class="border border-gray-300 rounded-lg shadow-sm text-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500">
                                    <?php foreach ($years_for_dropdown as $year): ?>
                                        <option value="<?= $year['tahun'] ?>" <?= ($tahun_filter == $year['tahun']) ? 'selected' : '' ?>><?= $year['tahun'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="relative w-full lg:w-auto">
                            <input type="text" id="tableSearch" onkeyup="applyFilters()" placeholder="Cari NIK atau Nama..." class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <?php if (count($data) > 0): ?>
                    <div class="overflow-x-auto relative border rounded-lg">
                        <table id="myTable" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">NIK</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Total Pengajuan</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Tahun</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" id="tableBody">
                        <?php $no = 1; foreach ($data as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-4 text-sm text-gray-600 text-center align-middle"></td> <!-- No. diisi oleh JS -->
                            <td class="px-3 py-4 text-sm text-gray-600 align-middle"><?= htmlspecialchars($row['nik']) ?></td>
                            <td class="px-3 py-4 text-sm text-gray-900 align-middle whitespace-nowrap truncate" style="max-width: 200px;"><?= htmlspecialchars($row['nama']) ?></td>
                            <td class="px-3 py-4 text-sm text-gray-600 text-center align-middle"><?= htmlspecialchars($row['total_pengajuan']) ?></td>
                            <td class="px-3 py-4 text-sm text-gray-600 text-center align-middle"><?= htmlspecialchars($row['tahun']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer" class="flex flex-col sm:flex-row justify-between items-center pt-4 mt-4 border-t border-gray-200 gap-4 sm:gap-0 print:hidden">
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
                <i class="fas fa-search text-5xl mb-4"></i><h3 class="text-xl font-semibold text-gray-600">Data Tidak Ditemukan</h3><p>Tidak ada data yang cocok dengan pencarian Anda.</p>
            </div>
            <?php else: ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-inbox text-6xl mb-4"></i>
                <p>Tidak ada data untuk periode yang dipilih</p>
            </div>
            <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        function printInNewTab() {
            // Ambil tahun yang dipilih dari filter
            const selectedYear = document.getElementById('tahun').value;
            const reportTitle = `Laporan Pengajuan Surat Selesai Tahun ${selectedYear}`;
            const fileNameTitle = `Laporan Surat Selesai - Tahun ${selectedYear}`;

            // Kloning elemen yang akan dicetak untuk dimanipulasi tanpa mengubah halaman asli
            const printableElement = document.querySelector('.printable-content').cloneNode(true);

            // Cari elemen judul di dalam konten yang dikloning dan perbarui teksnya
            const printTitleElement = printableElement.querySelector('#print-report-title');
            if (printTitleElement) {
                printTitleElement.textContent = reportTitle;
            }
            const printContent = printableElement.innerHTML;
            const styles = Array.from(document.styleSheets)
                .map(styleSheet => {
                    try {
                        return Array.from(styleSheet.cssRules)
                            .map(rule => rule.cssText)
                            .join('');
                    } catch (e) {
                        console.warn('Could not read stylesheet:', e);
                        return '';
                    }
                })
                .join('\n');
            const printWindow = window.open('', '_blank');
            // Perbarui judul halaman untuk memengaruhi nama file saat disimpan
            printWindow.document.write(`<html><head><title>${fileNameTitle}</title>`);
            printWindow.document.write('<style>' + styles + '</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }

        // --- LOGIKA TABEL & PENCARIAN ---
         const tableBody = document.getElementById('tableBody');
         const allRows = tableBody ? Array.from(tableBody.getElementsByTagName('tr')) : [];
         let filteredRows = [...allRows];
         
         let currentPage = 1;
         let entriesPerPage = parseInt(document.getElementById('tableEntries').value);
 
         function updatePaginationControls() {
             const totalFiltered = filteredRows.length;
             const currentEntriesPerPage = (entriesPerPage === -1) ? totalFiltered : parseInt(entriesPerPage);
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
 
             const prevBtn = document.getElementById('prevBtn');
             const nextBtn = document.getElementById('nextBtn');
             if (prevBtn) prevBtn.disabled = currentPage === 1;
             if (nextBtn) nextBtn.disabled = currentPage === totalPages;
             
             if (pageButtonsContainer) {
                 pageButtonsContainer.innerHTML = ''; // Kosongkan tombol
                 if (totalPages > 1) { // Hanya tampilkan jika lebih dari 1 halaman
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
             }
         }
 
         function applyFilters() {
             const searchText = document.getElementById('tableSearch').value.toLowerCase();
             const yearFilter = document.getElementById('tahun').value;
             const tableContainer = document.querySelector('.overflow-x-auto');
             const noResultsMessage = document.getElementById('noResultsMessage');
             const paginationContainer = document.querySelector('.flex.justify-between.items-center.pt-4');
 
             const locallyFilteredRows = allRows.filter(row => {
                const rowText = row.textContent.toLowerCase(); // This is a local variable, which is correct.
                const searchMatch = rowText.includes(searchText);

                // Kolom tahun adalah kolom ke-5 (index 4)
                const yearCell = row.cells[4].textContent;
                const yearMatch = (yearCell === yearFilter);

                return searchMatch && yearMatch;
             });
             
             // Perbaikan: Perbarui variabel global `filteredRows` dengan hasil filter lokal.
             filteredRows = locallyFilteredRows;

             const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : parseInt(entriesPerPage, 10);
             const maxPage = Math.max(1, Math.ceil(locallyFilteredRows.length / currentEntriesPerPage));
             if (currentPage > maxPage) currentPage = 1;
 
             if (filteredRows.length === 0) {
                 if(tableContainer) tableContainer.classList.add('hidden');
                 if(paginationContainer) paginationContainer.classList.add('hidden');
                 if(noResultsMessage) noResultsMessage.classList.remove('hidden');
                 tableBody.innerHTML = '';
             } else {
                 if(tableContainer) tableContainer.classList.remove('hidden');
                 if(paginationContainer) paginationContainer.classList.remove('hidden');
                 if(noResultsMessage) noResultsMessage.classList.add('hidden');
 
                 const start = (currentPage - 1) * currentEntriesPerPage;
                 const end = (entriesPerPage === -1) ? filteredRows.length : start + currentEntriesPerPage;
                 
                 tableBody.innerHTML = '';
                 locallyFilteredRows.slice(start, end).forEach((row, index) => {
                     row.cells[0].textContent = start + index + 1;
                     tableBody.appendChild(row);
                 });
             }
 
             updatePaginationControls();
         } 
 
         window.changeEntries = function() {
             entriesPerPage = parseInt(document.getElementById('tableEntries').value, 10);
             currentPage = 1;
             applyFilters();
         };

         // Fungsi ini dipanggil saat dropdown tahun berubah
         window.filterByYear = function() {
            currentPage = 1;
            applyFilters();
         }
 
         window.changePage = function(newPage) {
             // Use the global filteredRows for accurate totalPages calculation
             const totalPages = Math.ceil(filteredRows.length / ((entriesPerPage === -1) ? filteredRows.length : parseInt(entriesPerPage, 10)));
             if (newPage >= 1 && newPage <= totalPages) { currentPage = newPage; applyFilters(); }
         };
         
         document.addEventListener('DOMContentLoaded', function() {
             if (document.getElementById('myTable')) {
                applyFilters(); 
             }

             // Tambahkan event listener untuk dropdown tahun
             const yearDropdown = document.getElementById('tahun');
             if (yearDropdown) {
                yearDropdown.addEventListener('change', window.filterByYear);
             }
         });
    </script>
</body>
</html>