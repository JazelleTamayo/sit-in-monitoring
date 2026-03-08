<?php
session_start();

// CHECK IF USER IS LOGGED IN
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=Please login first");
    exit();
}

$pageTitle = "Dashboard - CCS Sit-in System";
$extraCSS = "dashboard";

// Check if user is admin
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-card">
        <div class="welcome-header">
            <i class="fas <?php echo $is_admin ? 'fa-crown' : 'fa-smile-wink'; ?>" style="<?php echo $is_admin ? 'color: #ffd700;' : ''; ?>"></i>
            <h1><?php echo $is_admin ? 'Admin Dashboard' : 'Welcome to CCS Sit-in System!'; ?></h1>
            <p class="subtitle">
                <?php if($is_admin): ?>
                    Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>!
                <?php else: ?>
                    You have successfully logged in.
                <?php endif; ?>
            </p>
        </div>

        <div class="user-info">
            <div class="info-box">
                <i class="fas fa-id-card"></i>
                <div class="info-details">
                    <span class="label">ID Number</span>
                    <span class="value"><?php echo isset($_SESSION['id_number']) ? htmlspecialchars($_SESSION['id_number']) : 'N/A'; ?></span>
                </div>
            </div>
            <div class="info-box">
                <i class="fas fa-envelope"></i>
                <div class="info-details">
                    <span class="label">Email</span>
                    <span class="value"><?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : 'N/A'; ?></span>
                </div>
            </div>
            <div class="info-box">
                <i class="fas <?php echo $is_admin ? 'fa-user-shield' : 'fa-book'; ?>"></i>
                <div class="info-details">
                    <span class="label"><?php echo $is_admin ? 'Role' : 'Course'; ?></span>
                    <span class="value">
                        <?php 
                        if($is_admin) {
                            echo 'Administrator';
                        } else {
                            echo isset($_SESSION['course']) ? htmlspecialchars($_SESSION['course']) : 'BS Information Technology';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="temp-message" style="<?php echo $is_admin ? 'background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);' : ''; ?>">
            <i class="fas <?php echo $is_admin ? 'fa-shield-alt' : 'fa-tools'; ?>"></i>
            <h3><?php echo $is_admin ? 'Admin Access' : 'Temporary Dashboard'; ?></h3>
            <p><?php echo $is_admin ? 'You are logged in as administrator.' : 'This is a temporary page. The full dashboard features will be available soon.'; ?></p>
        </div>

        <div class="quick-actions">
            <h4><?php echo $is_admin ? 'Admin Actions' : 'Quick Actions'; ?></h4>
            <div class="action-grid">
                <?php if($is_admin): ?>
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
                <?php else: ?>
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
                <?php endif; ?>
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