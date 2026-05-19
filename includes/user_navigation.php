<?php
// ─── includes/user_navigation.php ───────────────────────────────────
// User navigation menu - for logged-in users ONLY

// Include functions for notification count
require_once __DIR__ . '/get_announcements.php';
$totalUnreadCount = isset($_SESSION['user_id']) ? 
    (getUnreadAnnouncementCount($_SESSION['user_id']) + getUnreadNotificationCount($_SESSION['user_id'])) : 0;
?>

<!-- User Navigation Bar - for logged-in users ONLY -->
<nav class="navbar">
    <div class="nav-container">
        <div class="logo">
            <i class="fas fa-laptop-code"></i>
            <span>CCS <span class="logo-highlight">Student</span></span>
        </div>
        
        <ul class="nav-menu">
            <li><a href="<?= $basePath ?>pages/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Home
            </a></li>
            <li class="nav-item">
                <a href="<?= $basePath ?>pages/notifications.php" class="notification-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($totalUnreadCount > 0): ?>
                        <span class="nav-notification-badge"><?php echo $totalUnreadCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="<?= $basePath ?>pages/edit_profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'edit_profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a></li>
            <li><a href="<?= $basePath ?>pages/history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> History
            </a></li>
            <li><a href="<?= $basePath ?>pages/reservation.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reservation.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Reservation
            </a></li>
            <li><a href="<?= $basePath ?>pages/logout.php" class="btn-logout-nav">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a></li>
        </ul>
        
        <div class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <a href="<?= $basePath ?>pages/dashboard.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Home
    </a>
    <a href="<?= $basePath ?>pages/notifications.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
        <i class="fas fa-bell"></i> Notifications
        <?php if ($totalUnreadCount > 0): ?>
            <span class="mobile-notification-badge"><?php echo $totalUnreadCount; ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= $basePath ?>pages/edit_profile.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'edit_profile.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-edit"></i> Edit Profile
    </a>
    <a href="<?= $basePath ?>pages/history.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
        <i class="fas fa-history"></i> History
    </a>
    <a href="<?= $basePath ?>pages/reservation.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'reservation.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-check"></i> Reservation
    </a>
    <a href="<?= $basePath ?>pages/logout.php" class="mobile-link logout">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<style>
/* ===== MATCHES HOME navbar.css DESIGN ===== */

.navbar {
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: fixed;
    width: 100%;
    top: 0;
    z-index: 1000;
    padding: 1rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 4px 24px rgba(0,0,0,0.3);
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ===== LOGO ===== */
.logo {
    font-size: 1.3rem;
    font-weight: 700;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.logo i {
    font-size: 1.5rem;
    color: #60a5fa;
}

.logo-highlight {
    background: linear-gradient(135deg, #60a5fa, #a78bfa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ===== DESKTOP NAV MENU ===== */
.nav-menu {
    display: flex;
    list-style: none;
    gap: 1.8rem;
    align-items: center;
    margin: 0;
    padding: 0;
}

.nav-menu li a {
    text-decoration: none;
    color: #94a3b8;
    font-weight: 500;
    font-size: 0.92rem;
    transition: all 0.2s ease;
    position: relative;
    padding: 0.4rem 0;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.nav-menu li a i {
    margin-right: 0.4rem;
    font-size: 0.9rem;
}

.nav-menu li a:hover {
    color: #f1f5f9;
}

.nav-menu li a.active {
    color: #60a5fa;
    font-weight: 600;
}

.nav-menu li a.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, #60a5fa, #a78bfa);
    border-radius: 2px;
}

/* ===== LOGOUT BUTTON ===== */
.btn-logout-nav {
    padding: 0.45rem 1.2rem !important;
    border-radius: 999px !important;
    font-size: 0.88rem !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.4rem !important;
    border: 1.5px solid rgba(239,68,68,0.25) !important;
    background: rgba(239,68,68,0.15) !important;
    color: #fca5a5 !important;
    text-decoration: none;
}

.btn-logout-nav:hover {
    background: rgba(239,68,68,0.25) !important;
    transform: translateY(-1px);
}

/* Notification Badge */
.notification-link {
    position: relative;
}

.nav-notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 50%;
    min-width: 18px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.mobile-notification-badge {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 50%;
    min-width: 18px;
    display: inline-block;
    text-align: center;
    margin-left: auto;
}

/* ===== MENU TOGGLE (Mobile) ===== */
.menu-toggle {
    display: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
    transition: color 0.2s ease;
}

.menu-toggle:hover {
    color: #f1f5f9;
}

/* ===== MOBILE MENU — drops down from top like home ===== */
.mobile-menu {
    display: none;
    position: fixed;
    top: 61px;
    left: 0;
    width: 100%;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    padding: 0.8rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    z-index: 999;
    transform: translateY(-110%);
    transition: transform 0.3s ease;
}

.mobile-menu.active {
    transform: translateY(0);
}

.mobile-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: #94a3b8;
    border-radius: 10px;
    transition: all 0.2s ease;
    font-size: 0.92rem;
    font-weight: 500;
    margin-bottom: 2px;
}

.mobile-link i {
    margin-right: 0.8rem;
    width: 18px;
    color: #60a5fa;
    font-size: 0.9rem;
}

.mobile-link:hover {
    background: rgba(255,255,255,0.07);
    color: #f1f5f9;
}

.mobile-link.active {
    color: #60a5fa;
    background: rgba(37,99,235,0.1);
}

.mobile-link.logout {
    color: #fca5a5;
}

.mobile-link.logout i {
    color: #fca5a5;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .nav-menu { display: none; }
    .menu-toggle { display: block; }
    .mobile-menu { display: block; }
}

@media (max-width: 768px) {
    .navbar { padding: 0.75rem 0; }
    .logo { font-size: 1.1rem; }
    .logo i { font-size: 1.3rem; }
}
</style>

<script>
// Mobile menu — slide down from top (matches home page behavior)
const menuToggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');

if (menuToggle) {
    menuToggle.addEventListener('click', function() {
        mobileMenu.classList.toggle('active');
    });
}

// Close when a link is clicked
document.querySelectorAll('.mobile-link').forEach(link => {
    link.addEventListener('click', function() {
        mobileMenu.classList.remove('active');
    });
});

// Close on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        mobileMenu.classList.remove('active');
    }
});
</script>