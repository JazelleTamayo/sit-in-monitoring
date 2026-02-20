<?php
session_start();
$pageTitle = "Home - CCS Sit-in Monitoring System";
$extraCSS = ""; // No extra CSS needed for homepage
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navigation.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <div class="welcome-card">
            <div class="college-badge">
                <span class="est">EST. 1983</span>
                <h1>COLLEGE OF COMPUTER STUDIES</h1>
            </div>
            
            <h2 class="welcome-title">Sit-in Monitoring System</h2>
            
            <div class="motto">
                <p class="latin">INCEPTUM. INNOVATIO. NUMERIS.</p>
                <p class="english">Beginning. Innovation. Numbers.</p>
            </div>

            <?php if(!isset($_SESSION['user_id'])): ?>
                <div class="action-buttons">
                    <a href="login.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </a>
                    <a href="register.php" class="btn-register">
                        <i class="fas fa-user-plus"></i>
                        Register
                    </a>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn-dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        Go to Dashboard
                    </a>
                </div>
            <?php endif; ?>

            <div class="features-mini">
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Student Portal</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Sit-in Management</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>History Tracking</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Real-time Monitoring</span>
                </div>
            </div>
        </div>

        <!-- About Section -->
        <section id="about" class="about-section">
            <h2>About the System</h2>
            <div class="about-content">
                <p>The CCS Sit-in Monitoring System is designed to streamline the process of computer laboratory usage for students of the College of Computer Studies. It provides an efficient way to track and manage student sit-in sessions.</p>
            </div>
        </section>

        <!-- Community Section -->
        <section id="community" class="community-section">
            <h2>Our Community</h2>
            <div class="community-grid">
                <div class="community-card">
                    <i class="fas fa-users"></i>
                    <h3>500+ Students</h3>
                    <p>Active users in the system</p>
                </div>
                <div class="community-card">
                    <i class="fas fa-desktop"></i>
                    <h3>5 Laboratories</h3>
                    <p>Fully equipped computer labs</p>
                </div>
                <div class="community-card">
                    <i class="fas fa-clock"></i>
                    <h3>24/7 Access</h3>
                    <p>Round the clock availability</p>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>