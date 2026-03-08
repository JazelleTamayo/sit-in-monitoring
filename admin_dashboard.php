<?php
session_start();

// CHECK IF ADMIN IS LOGGED IN
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=Unauthorized access");
    exit();
}

$pageTitle = "Admin Dashboard - CCS Sit-in System";
$extraCSS = "dashboard"; // Reuse dashboard CSS for now
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-card">
        <div class="welcome-header">
            <i class="fas fa-crown" style="color: #ffd700;"></i>
            <h1>Admin Dashboard</h1>
            <p class="subtitle">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>!</p>
        </div>

        <div class="user-info">
            <div class="info-box">
                <i class="fas fa-id-card"></i>
                <div class="info-details">
                    <span class="label">Admin ID</span>
                    <span class="value"><?php echo htmlspecialchars($_SESSION['id_number']); ?></span>
                </div>
            </div>
            <div class="info-box">
                <i class="fas fa-envelope"></i>
                <div class="info-details">
                    <span class="label">Email</span>
                    <span class="value"><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                </div>
            </div>
            <div class="info-box">
                <i class="fas fa-user-shield"></i>
                <div class="info-details">
                    <span class="label">Role</span>
                    <span class="value">Administrator</span>
                </div>
            </div>
        </div>

        <div class="temp-message" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
            <i class="fas fa-shield-alt"></i>
            <h3>Admin Access Granted</h3>
            <p>You are logged in as administrator. Full admin features will be available soon.</p>
        </div>

        <div class="quick-actions">
            <h4>Admin Actions</h4>
            <div class="action-grid">
                <div class="action-item">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </div>
                <div class="action-item">
                    <i class="fas fa-clock"></i>
                    <span>Sit-in Records</span>
                </div>
                <div class="action-item">
                    <i class="fas fa-flask"></i>
                    <span>Laboratories</span>
                </div>
                <div class="action-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
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