<?php
require_once '../config.php';
Session::start();

// Cek login dan pastikan admin
if (!Session::isAdmin()) {
    Session::set('error', 'Anda harus login sebagai admin untuk mengakses halaman ini.');
    redirect('login.php');
}

$adminId = Session::getAdminId();
$admin = Database::query("SELECT * FROM admin WHERE admin_id = ?", [$adminId])->fetch();

// --- PENGAMBILAN NOTIFIKASI SESSION ---
$success = Session::get('success', '');
Session::remove('success');
$error = Session::get('error', '');
Session::remove('error');
// ------------------------------------

// --- LOGIKA AKSI FORM (HAPUS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $pengajuanId = $_POST['pengajuan_id'] ?? 0;

    if ($action === 'delete') {
        $pengajuanData = Database::query("SELECT foto_ktp, foto_kk, foto_formulir, foto_lainnya, file_surat FROM pengajuan_surat WHERE pengajuan_id = ?", [$pengajuanId])->fetch();
        if ($pengajuanData) {
            if (Database::query("DELETE FROM pengajuan_surat WHERE pengajuan_id = ?", [$pengajuanId])) {
                // Hapus semua file terkait
                if ($pengajuanData['foto_ktp'] && file_exists('../uploads/' . $pengajuanData['foto_ktp'])) unlink('../uploads/' . $pengajuanData['foto_ktp']);
                if ($pengajuanData['foto_kk'] && file_exists('../uploads/' . $pengajuanData['foto_kk'])) unlink('../uploads/' . $pengajuanData['foto_kk']);
                if ($pengajuanData['foto_formulir'] && file_exists('../uploads/' . $pengajuanData['foto_formulir'])) unlink('../uploads/' . $pengajuanData['foto_formulir']);
                if ($pengajuanData['foto_lainnya'] && file_exists('../uploads/' . $pengajuanData['foto_lainnya'])) unlink('../uploads/' . $pengajuanData['foto_lainnya']);
                if ($pengajuanData['file_surat'] && file_exists('../uploads/' . $pengajuanData['file_surat'])) unlink('../uploads/' . $pengajuanData['file_surat']);
                Session::set('success', 'Arsip surat berhasil dihapus.');
            } else {
                Session::set('error', 'Gagal menghapus arsip surat.');
            }
        }
    }
    redirect('admin/arsip_surat.php');
}

// --- PENGAMBILAN DATA UNTUK TABEL ---
// Query diperbarui untuk mengambil email dan no_telepon dari tabel user
$sql = "SELECT p.*, u.nama as nama_user, u.email as email_user, u.no_telepon as telepon_user, js.nama_surat
        FROM pengajuan_surat p
        JOIN user u ON p.user_id = u.user_id
        LEFT JOIN jenis_surat js ON p.id_jenis_surat = js.id_jenis_surat
        WHERE p.status = 'selesai'";

$sql .= " ORDER BY p.tgl_pengajuan DESC";

$pengajuanList = Database::query($sql)->fetchAll();

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
    <title>Arsip Surat - <?= SITE_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="../assets/img/logopky.gif">
    <link rel="apple-touch-icon" href="../assets/img/logopky.gif">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>.preview-img { max-width: 100%; max-height: 60vh; object-fit: contain; }
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
                    <h2 class="text-2xl font-bold text-gray-800 mb-1">Arsip Surat Selesai</h2>
                    <p class="text-sm text-gray-500 mb-4">Daftar semua surat yang telah selesai diproses dan diarsipkan.</p>

                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 pb-4 border-b gap-4">
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
                            <input type="text" id="tableSearch" onkeyup="applyFilters()" placeholder="Cari surat, nama, email..." class="w-full lg:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <?php if (count($pengajuanList) > 0): ?>
                    <div class="w-full"> <!-- Wrapper untuk konsistensi layout -->
                        <div id="tableContainer" class="overflow-x-auto relative border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200" id="pengajuanTable">
                                <thead class="bg-gray-50 sticky top-0 z-10">
                                    <tr>
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
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-4 text-sm text-gray-600 text-center align-middle"></td> <!-- No. diisi oleh JS -->
                                        <td class="px-3 py-4 text-sm text-gray-900 align-middle whitespace-nowrap"><?= htmlspecialchars($p['nama_surat'] ?? 'N/A') ?></td>
                                        <td class="px-3 py-4 text-sm text-gray-900 text-center align-middle"><?= htmlspecialchars($p['user_id']) ?></td>
                                        <td class="px-3 py-4 text-sm text-gray-900 align-middle whitespace-nowrap truncate" style="max-width: 200px;"><?= htmlspecialchars($p['nama_user']) ?></td>
                                        <td class="px-3 py-4 text-sm text-gray-900 align-middle"><?= htmlspecialchars($p['email_user'] ?? '-') ?></td>
                                        <td class="px-3 py-4 text-sm text-gray-900 align-middle"><?= htmlspecialchars($p['telepon_user'] ?? '-') ?></td>
                                        <td class="px-3 py-4 text-sm text-gray-900 align-middle truncate" style="max-width: 150px;"><?= htmlspecialchars($p['alamat'] ?? '-') ?></td>
                                        <td class="px-3 py-4 text-sm text-gray-500 text-center align-middle whitespace-nowrap"><?= formatTanggal($p['tgl_pengajuan'], 'd M Y') ?></td>
                                        <td class="px-3 py-4 text-sm text-center align-middle">
                                            <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><i class="fas fa-check-circle mr-1.5"></i> Selesai</span>
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
                                        <td class="px-3 py-4 text-sm font-medium text-center whitespace-nowrap align-middle">
                                            <div class="flex items-center justify-center space-x-2">
                                                <form method="POST" onsubmit="confirmDelete(event, <?= $p['pengajuan_id'] ?>)" class="inline-block">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="pengajuan_id" value="<?= $p['pengajuan_id'] ?>">
                                                    <button type="submit" title="Hapus Arsip" class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition duration-200 text-xs font-medium"><i class="fas fa-trash mr-1"></i>Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
                        <p>Tidak ada arsip yang cocok dengan pencarian Anda.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-archive text-6xl mb-4"></i>
                        <p class="text-lg">Arsip surat masih kosong.</p>
                        <p class="text-sm">Belum ada surat yang selesai diproses.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <div id="modalDetail" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] p-4">
        <div class="bg-white rounded-lg p-6 max-w-3xl w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-3">
                <h3 class="text-xl font-bold text-gray-800">Detail Arsip Surat</h3>
                <button onclick="closeModal('modalDetail')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <div id="detailContent" class="space-y-4"></div>
            <div class="mt-6 flex justify-end"><button type="button" onclick="closeModal('modalDetail')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Tutup</button></div>
        </div>
    </div>

    <div id="documentPreviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] p-4">
        <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-screen flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Preview Dokumen</h3>
                <button onclick="closeModal('documentPreviewModal')" class="text-gray-600 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            </div>
            <div id="documentPreviewContent" class="flex-grow overflow-auto text-center mb-4"></div>
            <div class="flex justify-end items-center gap-4 border-t pt-4">
                <a id="downloadButton" href="#" download target="_blank" class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700"><i class="fas fa-download mr-2"></i>Unduh Dokumen</a>
                <button type="button" onclick="closeModal('documentPreviewModal')" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600">Kembali</button>
            </div>
        </div>
    </div>

    <script>
        <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= addslashes($success) ?>', timer: 3000, showConfirmButton: false });
        <?php endif; ?>
        <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= addslashes($error) ?>', timer: 5000, showConfirmButton: true });
        <?php endif; ?>

        function showModal(modalId, data = null) {
            const modal = document.getElementById(modalId);
            if (modal) {
                if (modalId === 'detailModal' && data) {
                    const content = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><p class="font-semibold">Jenis Surat:</p><p class="p-2 bg-gray-50 rounded">${data.nama_surat || '-'}</p></div>
                            <div><p class="font-semibold">Nama Pengaju:</p><p class="p-2 bg-gray-50 rounded">${data.nama_pengaju || '-'}</p></div>
                            <div><p class="font-semibold">Email:</p><p class="p-2 bg-gray-50 rounded">${data.email_pengaju || '-'}</p></div>
                            <div><p class="font-semibold">No. Telepon:</p><p class="p-2 bg-gray-50 rounded">${data.no_telepon || '-'}</p></div>
                            <div><p class="font-semibold">Tanggal Pengajuan:</p><p class="p-2 bg-gray-50 rounded">${new Date(data.tgl_pengajuan).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</p></div>
                            <div><p class="font-semibold">Tanggal Selesai:</p><p class="p-2 bg-gray-50 rounded">${new Date(data.tgl_selesai).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</p></div>
                            <div class="md:col-span-2"><p class="font-semibold">Alamat:</p><p class="p-2 bg-gray-50 rounded break-words">${data.alamat || '-'}</p></div>
                            ${data.keterangan ? `<div class="md:col-span-2"><p class="font-semibold">Keterangan:</p><p class="p-2 bg-gray-50 rounded break-words">${data.keterangan}</p></div>` : ''}
                            ${data.alasan_penolakan ? `<div class="md:col-span-2"><p class="font-semibold text-red-600">Alasan Penolakan:</p><p class="p-2 bg-red-50 rounded border-l-4 border-red-500">${data.alasan_penolakan}</p></div>` : ''}
                        </div>
                        <div class="mt-4 pt-4 border-t">
                            <p class="font-semibold mb-2">Dokumen Terlampir:</p>
                            <div class="flex flex-wrap gap-2">
                                ${data.foto_ktp ? `<button onclick="showDocumentPreview('${data.foto_ktp}')" class="bg-gray-100 text-gray-700 px-3 py-1 rounded-md text-sm hover:bg-gray-200">KTP</button>` : ''}
                                ${data.foto_kk ? `<button onclick="showDocumentPreview('${data.foto_kk}')" class="bg-gray-100 text-gray-700 px-3 py-1 rounded-md text-sm hover:bg-gray-200">KK</button>` : ''}
                                ${data.foto_formulir ? `<button onclick="showDocumentPreview('${data.foto_formulir}')" class="bg-gray-100 text-gray-700 px-3 py-1 rounded-md text-sm hover:bg-gray-200">Formulir</button>` : ''}
                                ${data.foto_lainnya ? `<button onclick="showDocumentPreview('${data.foto_lainnya}')" class="bg-gray-100 text-gray-700 px-3 py-1 rounded-md text-sm hover:bg-gray-200">Lainnya</button>` : ''}
                                ${data.file_surat ? `<a href="../uploads/${data.file_surat}" download class="bg-green-100 text-green-700 px-3 py-1 rounded-md text-sm hover:bg-green-200"><i class="fas fa-download mr-1"></i> Surat Selesai</a>` : ''}
                            </div>
                        </div>
                    `;
                    document.getElementById('detailContent').innerHTML = content;
                }
                modal.classList.remove('hidden');
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function showDocumentPreview(fileName, data = null, docType = 'Dokumen') {
            const modal = document.getElementById('documentPreviewModal');
            const contentArea = document.getElementById('documentPreviewContent');
            const downloadBtn = document.getElementById('downloadButton');
            const fileUrl = `../uploads/${fileName}`;
            const fileExtension = fileName.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                contentArea.innerHTML = `<img src="${fileUrl}" alt="Preview Dokumen" class="preview-img mx-auto">`;
            } else if (fileExtension === 'pdf') {
                contentArea.innerHTML = `<iframe src="${fileUrl}" width="100%" height="500px"></iframe>`;
            } else {
                contentArea.innerHTML = `<div class="text-center p-8"><i class="fas fa-file-alt text-6xl text-gray-400"></i><p class="mt-4">Preview tidak tersedia.</p></div>`;
            }

            let downloadFileName = fileName; // Default
            if (data) {
                const fileExtension = fileName.split('.').pop();
                if (fileName === data.file_surat) {
                    downloadFileName = `${data.nama_surat} - ${data.nama_user}.${fileExtension}`;
                } else if (fileName === data.foto_ktp) {
                    downloadFileName = `KTP - ${data.nama_user}.${fileExtension}`;
                } else if (fileName === data.foto_kk) {
                    downloadFileName = `KK - ${data.nama_user}.${fileExtension}`;
                } else if (fileName === data.foto_formulir) {
                    downloadFileName = `Formulir - ${data.nama_user}.${fileExtension}`;
                } else if (fileName === data.foto_lainnya) {
                    downloadFileName = `Lampiran Lainnya - ${data.nama_user}.${fileExtension}`;
                }
            }

            downloadBtn.href = fileUrl;
            downloadBtn.download = downloadFileName;
            modal.classList.remove('hidden');
        }
        
        function confirmDelete(event, id) {
            event.preventDefault();
            const form = event.target.closest('form');
            Swal.fire({ title: 'Anda yakin?', text: "Arsip ini akan dihapus secara permanen!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Ya, hapus!', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) { form.submit(); } });
        }

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
            
            const currentEntriesPerPage = (entriesPerPage === -1) ? filteredRows.length : entriesPerPage;
            const maxPage = Math.max(1, Math.ceil(filteredRows.length / currentEntriesPerPage));
            if (currentPage > maxPage) currentPage = 1;

            if (filteredRows.length === 0) {
                if(tableContainer) tableContainer.classList.add('hidden');
                if(paginationContainer) paginationContainer.classList.add('hidden');
                if(noResultsMessage) noResultsMessage.classList.remove('hidden');
            } else {
                if(tableContainer) tableContainer.classList.remove('hidden');
                if(paginationContainer) paginationContainer.classList.remove('hidden');
                if(noResultsMessage) noResultsMessage.classList.add('hidden');
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

        function changePage(newPage) {
            const totalPages = Math.ceil(filteredRows.length / ((entriesPerPage === -1) ? filteredRows.length : entriesPerPage));
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                applyFilters();
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('tableEntries')) {
                applyFilters();
            }
        });

    </script>
</body>
</html>
