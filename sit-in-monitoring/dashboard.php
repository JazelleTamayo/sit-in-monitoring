<?php
session_start();

// CHECK IF USER IS LOGGED IN - ADD THIS!
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=Please login first");
    exit();
}

$pageTitle = "Dashboard - CCS Sit-in System";
$extraCSS = "dashboard";
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-card">
        <div class="welcome-header">
            <i class="fas fa-smile-wink"></i>
            <h1>Welcome to CCS Sit-in System!</h1>
            <p class="subtitle">You have successfully logged in.</p>
        </div>

        <div class="user-info">
            <div class="info-box">
                <i class="fas fa-user-graduate"></i>
                <div class="info-details">
                    <span class="label">Student ID</span>
                    <span class="value"><?php echo isset($_SESSION['id_number']) ? htmlspecialchars($_SESSION['id_number']) : '2024-12345'; ?></span>
                </div>
            </div>
            <div class="info-box">
                <i class="fas fa-book"></i>
                <div class="info-details">
                    <span class="label">Course</span>
                    <span class="value">BS Information Technology</span>
                </div>
            </div>
            <div class="info-box">
                <i class="fas fa-clock"></i>
                <div class="info-details">
                    <span class="label">Year Level</span>
                    <span class="value">2nd Year</span>
                </div>
            </div>
        </div>

        <div class="temp-message">
            <i class="fas fa-tools"></i>
            <h3>Temporary Dashboard</h3>
            <p>This is a temporary page. The full dashboard features will be available soon.</p>
        </div>

        <div class="quick-actions">
            <h4>Quick Actions</h4>
            <div class="action-grid">
                <div class="action-item">
                    <i class="fas fa-desktop"></i>
                    <span>Sit-in</span>
                </div>
                <div class="action-item">
                    <i class="fas fa-history"></i>
                    <span>History</span>
                </div>
                <div class="action-item">
                    <i class="fas fa-calendar"></i>
                    <span>Schedule</span>
                </div>
                <div class="action-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </div>
            </div>
        </div>

        <div class="logout-section">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>