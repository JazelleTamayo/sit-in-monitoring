<!-- Navigation Bar -->
<nav class="navbar">
    <div class="nav-container">
        <div class="logo">
            <i class="fas fa-laptop-code"></i>
            <span>CCS <span class="logo-highlight">Sit-in</span></span>
        </div>
        
        <ul class="nav-menu">
            <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Home
            </a></li>
            <li><a href="#about" class="nav-link">
                <i class="fas fa-info-circle"></i> About
            </a></li>
            <li><a href="#community" class="nav-link">
                <i class="fas fa-users"></i> Community
            </a></li>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <!-- Show when user is logged in -->
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="logout.php" class="btn-logout-nav">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            <?php else: ?>
                <!-- Show when user is not logged in -->
                <li><a href="login.php" class="btn-outline">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a></li>
                <li><a href="register.php" class="btn-solid">
                    <i class="fas fa-user-plus"></i> Register
                </a></li>
            <?php endif; ?>
        </ul>
        
        <div class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</nav>

<!-- Mobile Menu (Hidden by default) -->
<div class="mobile-menu" id="mobileMenu">
    <a href="index.php" class="mobile-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Home
    </a>
    <a href="#about" class="mobile-link">
        <i class="fas fa-info-circle"></i> About
    </a>
    <a href="#community" class="mobile-link">
        <i class="fas fa-users"></i> Community
    </a>
    
    <?php if(isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php" class="mobile-link">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="logout.php" class="mobile-link logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    <?php else: ?>
        <a href="login.php" class="mobile-link login">
            <i class="fas fa-sign-in-alt"></i> Login
        </a>
        <a href="register.php" class="mobile-link register">
            <i class="fas fa-user-plus"></i> Register
        </a>
    <?php endif; ?>
</div>