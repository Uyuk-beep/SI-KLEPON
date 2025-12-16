// User dashboard JS (moved from inline <script>)
// Handles sidebar open/close and responsive behavior

document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.remove('sidebar-closed-on-load');
});

var sidebar = document.getElementById('user-sidebar');
var toggleButtonDesktop = document.getElementById('sidebar-toggle-desktop');
var mainContentWrapper = document.getElementById('main-content-wrapper');
var userNavbar = document.getElementById('user-navbar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

var sidebarOpen = (localStorage.getItem('sidebarOpen') === 'true') || false;

if (window.innerWidth < 768) {
    sidebarOpen = false;
} else if (localStorage.getItem('sidebarOpen') === null) {
    sidebarOpen = true;
}

function setSidebarState(open) {
    sidebarOpen = open;
    localStorage.setItem('sidebarOpen', open);
    document.cookie = 'sidebarOpen=' + open + '; path=/';

    if (open) {
        sidebar.classList.add('open-sidebar');
        sidebar.classList.remove('closed-sidebar', '-translate-x-full');
        userNavbar.classList.remove('navbar-closed-sidebar');
        if (window.innerWidth >= 768) {
            mainContentWrapper.style.marginLeft = '256px';
            userNavbar.style.width = 'calc(100% - 256px)';
            userNavbar.style.left = '256px';
        } else {
            sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
        }
    } else {
        sidebar.classList.remove('open-sidebar');
        if (window.innerWidth >= 768) {
            sidebar.classList.add('closed-sidebar');
            mainContentWrapper.style.marginLeft = '64px';
            userNavbar.classList.add('navbar-closed-sidebar');
            userNavbar.style.width = 'calc(100% - 64px)';
            userNavbar.style.left = '64px';
        } else {
            sidebar.classList.add('-translate-x-full');
            mainContentWrapper.style.marginLeft = '';
        }
        sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
    }
}

// Event listener untuk tombol toggle
if (toggleButtonDesktop) {
    toggleButtonDesktop.addEventListener('click', function () {
        if (window.innerWidth < 768) {
            setSidebarState(!sidebarOpen);
            if (sidebarOpen) {
                sidebarOverlay.classList.add('opacity-50', 'pointer-events-auto');
            } else {
                sidebarOverlay.classList.remove('opacity-50', 'pointer-events-auto');
            }
        } else {
            setSidebarState(!sidebarOpen);
        }
    });
}

// Event listener untuk overlay (hanya di mobile)
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function () {
        setSidebarState(false);
    });
}

window.addEventListener('resize', function () {
    if (window.innerWidth >= 768) {
        setSidebarState(sidebarOpen);
        if (sidebarOverlay) sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
    } else {
        setSidebarState(false);
    }
});

setSidebarState(sidebarOpen);
