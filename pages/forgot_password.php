<?php
session_start();
$pageTitle = "Forgot Password - CCS Sit-in System";
$basePath  = "../";
$extraCSS  = "login";
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/navigation.php'; ?>

<div class="login-page">
    <div class="login-container">

        <div class="login-left">
            <div class="left-content">
                <div class="college-logo">
                    <img src="../assets/images/ccs.png" alt="CCS">
                </div>
                <h2>College of Computer Studies</h2>
                <p class="motto">INCEPTUM · INNOVATIO · NUMERIS</p>
            </div>
        </div>

        <div class="login-right">
            <div class="form-container">

                <?php if (isset($_GET['step']) && $_GET['step'] === 'reset'): ?>
                    <h2>Reset Password</h2>
                    <p class="subtitle">Enter your new password below.</p>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="/sit-in-monitoring-main/process/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="reset">
                        <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($_GET['id_number'] ?? ''); ?>">

                        <div class="form-group">
                            <label>New Password</label>
                            <div class="password-field">
                                <input type="password" name="new_password" placeholder="Enter new password" required minlength="6">
                                <i class="fas fa-eye-slash toggle-pwd"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="password-field">
                                <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="6">
                                <i class="fas fa-eye-slash toggle-pwd"></i>
                            </div>
                        </div>

                        <button type="submit" class="login-btn">
                            <i class="fas fa-lock"></i> Reset Password
                        </button>
                        <div class="register-text">Remembered it? <a href="login.php">Back to Login</a></div>
                    </form>

                <?php elseif (isset($_GET['step']) && $_GET['step'] === 'verify'): ?>
                    <h2>Verify Your Email</h2>
                    <p class="subtitle">Enter the email linked to ID <strong><?php echo htmlspecialchars($_GET['id_number'] ?? ''); ?></strong>.</p>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="/sit-in-monitoring-main/process/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="verify">
                        <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($_GET['id_number'] ?? ''); ?>">

                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="Enter your registered email" required>
                        </div>

                        <button type="submit" class="login-btn">
                            <i class="fas fa-envelope"></i> Verify Email
                        </button>
                        <div class="register-text"><a href="forgot_password.php"><i class="fas fa-arrow-left"></i> Start over</a></div>
                    </form>

                <?php else: ?>
                    <h2>Forgot Password?</h2>
                    <p class="subtitle">Enter your ID number and we'll verify your account.</p>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="/sit-in-monitoring-main/process/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="lookup">

                        <div class="form-group">
                            <label>ID Number</label>
                            <input type="text" name="id_number" placeholder="Enter your student ID number" required>
                        </div>

                        <button type="submit" class="login-btn">
                            <i class="fas fa-search"></i> Find My Account
                        </button>
                        <div class="register-text">Remembered it? <a href="login.php">Back to Login</a></div>
                    </form>

                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>