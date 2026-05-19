<?php
// Get pending reservation count for the badge
$pendingReservations = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'");
    $pendingReservations = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    // Table might not exist – ignore
}
?>
<!-- Admin Navigation Bar -->
<nav class="navbar">
    <div class="nav-container">
        <div class="logo">
            <i class="fas fa-crown" style="color: #fbbf24;"></i>
            <span>CCS <span class="logo-highlight">Admin</span></span>
        </div>
        
        <ul class="nav-menu">
            <li><a href="admin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Home
            </a></li>

            <li><a href="admin_search.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_search.php' ? 'active' : ''; ?>">
                <i class="fas fa-search"></i> Search
            </a></li>

            <li><a href="admin_students.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_students.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Students
            </a></li>

            <!-- Sit-in Dropdown -->
            <li class="nav-dropdown">
                <a href="#" class="nav-dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['admin_sitin.php','admin_sitin_records.php','admin_sitin_reports.php','admin_feedback_reports.php']) ? 'active' : ''; ?>"
                   onclick="toggleDropdown(event, this)">
                    <i class="fas fa-clock"></i> Sit-in <i class="fas fa-chevron-down dropdown-arrow"></i>
                </a>
                <ul class="dropdown-menu" id="sitinDropdown">
                    <li><a href="admin_sitin.php"><i class="fas fa-clock"></i> Sit-in</a></li>
                    <li><a href="admin_sitin_records.php"><i class="fas fa-list-alt"></i> View Sit-in Records</a></li>
                    <li><a href="admin_sitin_reports.php"><i class="fas fa-chart-bar"></i> Sit-in Reports</a></li>
                    <li><a href="admin_feedback_reports.php"><i class="fas fa-comment-alt"></i> Feedback Reports</a></li>
                </ul>
            </li>

            <li><a href="admin_lab_management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_lab_management.php' ? 'active' : ''; ?>">
                <i class="fas fa-laptop"></i> Labs
            </a></li>

            <li><a href="admin_announcements.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_announcements.php' ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i> Announcements
            </a></li>

            <!-- Reservation link with badge -->
            <li>
                <a href="admin_reservation.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_reservation.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> Reservation
                    <?php if ($pendingReservations > 0): ?>
                        <span class="badge-pending"><?php echo $pendingReservations; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li>
                <a href="logout.php" class="btn-logout-nav">
                    <i class="fas fa-sign-out-alt"></i> Log out
                </a>
            </li>
        </ul>
        
        <div class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</nav>

<!-- Mobile Menu for Admin -->
<div class="mobile-menu" id="mobileMenu">
    <a href="admin_dashboard.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Home
    </a>
    <a href="admin_search.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_search.php' ? 'active' : ''; ?>">
        <i class="fas fa-search"></i> Search
    </a>
    <a href="admin_students.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_students.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> Students
    </a>
    <a href="admin_sitin.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_sitin.php' ? 'active' : ''; ?>">
        <i class="fas fa-clock"></i> Sit-in
    </a>
    <a href="admin_sitin_records.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_sitin_records.php' ? 'active' : ''; ?>">
        <i class="fas fa-list-alt"></i> View Sit-in Records
    </a>
    <a href="admin_sitin_reports.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_sitin_reports.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar"></i> Sit-in Reports
    </a>
    <a href="admin_feedback_reports.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_feedback_reports.php' ? 'active' : ''; ?>">
        <i class="fas fa-comment-alt"></i> Feedback Reports
    </a>
    <a href="admin_lab_management.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_lab_management.php' ? 'active' : ''; ?>">
        <i class="fas fa-laptop"></i> Labs
    </a>
    <a href="admin_announcements.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_announcements.php' ? 'active' : ''; ?>">
        <i class="fas fa-bullhorn"></i> Announcements
    </a>
    <a href="admin_reservation.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_reservation.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-check"></i> Reservation
        <?php if ($pendingReservations > 0): ?>
            <span class="badge-pending"><?php echo $pendingReservations; ?></span>
        <?php endif; ?>
    </a>
    <a href="logout.php" class="mobile-link logout">
        <i class="fas fa-sign-out-alt"></i> Log out
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

/* Dropdown toggle active underline should not show the ::after line */
.nav-dropdown-toggle.active::after {
    display: none;
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
    /* Override the default ::after underline */
}

.btn-logout-nav::after {
    display: none !important;
}

/* ===== DROPDOWN ===== */
.nav-dropdown {
    position: relative;
}

.nav-dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    user-select: none;
}

.dropdown-arrow {
    font-size: 0.65rem;
    margin-left: 2px;
    transition: transform 0.25s ease;
}

.nav-dropdown-toggle.dropdown-open .dropdown-arrow {
    transform: rotate(180deg);
}

.nav-dropdown .dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 12px);
    left: 50%;
    transform: translateX(-50%);
    background: rgba(10, 15, 30, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 14px;
    min-width: 210px;
    padding: 8px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.5);
    z-index: 9999;
    list-style: none;
}

.nav-dropdown .dropdown-menu.open {
    display: block;
    animation: dropdownFade 0.2s ease forwards;
}

@keyframes dropdownFade {
    from { opacity: 0; transform: translateX(-50%) translateY(-6px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}

.nav-dropdown .dropdown-menu li { list-style: none; }

.nav-dropdown .dropdown-menu li a {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 1rem;
    color: #94a3b8;
    text-decoration: none;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
    white-space: nowrap;
    position: static; /* no underline pseudo-element */
}

.nav-dropdown .dropdown-menu li a::after {
    display: none !important;
}

.nav-dropdown .dropdown-menu li a:hover {
    background: rgba(255,255,255,0.07);
    color: #f1f5f9;
}

.nav-dropdown .dropdown-menu li a i {
    color: #60a5fa;
    width: 16px;
    font-size: 0.85rem;
    margin-right: 0;
}

.nav-dropdown .dropdown-menu li:not(:last-child) {
    border-bottom: 1px solid rgba(255,255,255,0.05);
    margin-bottom: 2px;
    padding-bottom: 2px;
}

/* ===== PENDING BADGE ===== */
.badge-pending {
    background: #fbbf24;
    color: #1e293b;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 0.7rem;
    font-weight: 700;
    margin-left: 4px;
    display: inline-block;
    line-height: 1.2;
}

.mobile-link .badge-pending {
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
function toggleDropdown(e, el) {
    e.preventDefault();
    e.stopPropagation();

    const menu = el.nextElementSibling;
    const isOpen = menu.classList.contains('open');

    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    document.querySelectorAll('.nav-dropdown-toggle.dropdown-open').forEach(t => t.classList.remove('dropdown-open'));

    if (!isOpen) {
        menu.classList.add('open');
        el.classList.add('dropdown-open');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.nav-dropdown')) {
        document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.nav-dropdown-toggle.dropdown-open').forEach(t => t.classList.remove('dropdown-open'));
    }
});

// Mobile menu — slide down from top (matches home page behavior)
const menuToggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');

if (menuToggle) {
    menuToggle.addEventListener('click', function() {
        mobileMenu.classList.toggle('active');
    });
}

document.querySelectorAll('.mobile-link').forEach(link => {
    link.addEventListener('click', function() {
        mobileMenu.classList.remove('active');
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        mobileMenu.classList.remove('active');
    }
});
</script>