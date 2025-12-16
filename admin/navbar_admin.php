<?php
// File: d:\xampp\htdocs\SI-KLEPON\admin\navbar_admin.php

if (!isset($admin) || !is_array($admin)) {
    echo "Navbar error: Data admin tidak ditemukan.";
    return;
}

?>
<nav class="bg-white shadow-lg z-20" id="admin-navbar">
    <div class="px-4 md:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-3 text-gray-800 h-full">
                <button id="sidebar-toggle-desktop" class="text-gray-600 hover:text-gray-900 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
            </div>
            <div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false">
                <button @click="open = !open" class="flex items-center focus:outline-none p-2 rounded-lg hover:bg-gray-50">
                    <div class="text-gray-800 text-right hidden sm:block mr-3">
                        <p class="font-semibold text-sm"><?= htmlspecialchars($admin['nama']) ?></p>
                        <p class="text-xs text-gray-600"><?= htmlspecialchars(ucfirst($admin['role'])) ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0 shadow-md">
                        <?= getInitials($admin['nama']) ?>
                    </div>
                </button>
                <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 top-12 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 origin-top-right" x-cloak style="display: none;">
                    <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-blue-600 bg-white hover:bg-gray-100"><i class="fas fa-user-circle mr-2"></i> Profil</a>
                    <a href="../logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fas fa-sign-out-alt mr-2"></i> Keluar</a>
                    <form id="logout-form" action="../logout.php" method="POST" style="display: none;"></form>
                </div>
            </div>
        </div>
    </div>
</nav>