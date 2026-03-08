<?php
session_start();
$pageTitle = "Login - CCS Sit-in System";
$extraCSS = "login";
$extraJS = "login";
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navigation.php'; ?>

<div class="login-page">
    <div class="login-container">
        <!-- Left Side - College Info -->
        <div class="login-left">
            <div class="left-content">
                <div class="college-logo">
                    <img src="assets/images/ccs.png" alt="CCS">
                </div>
                <h2>College of Computer Studies</h2>
                <p class="motto">INCEPTUM. INNOVATIO. NUMERIS.</p>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="form-container">
                <h2>Welcome Back!</h2>
                <p class="subtitle">Please login to your account</p>

                <!-- PHP Error/Success Messages -->
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['success'])): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                <?php endif; ?>

                <form action="login_process.php" method="POST">
                    <div class="form-group">
                        <label>ID Number</label>
                        <input type="text" 
                               name="id_number" 
                               placeholder="Enter ID number"
                               value="<?php echo isset($_COOKIE['remember_id']) ? htmlspecialchars($_COOKIE['remember_id']) : ''; ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-field">
                            <input type="password" 
                                   name="password" 
                                   placeholder="Enter password"
                                   required>
                            <i class="fas fa-eye-slash toggle-pwd"></i>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="remember">
                            <input type="checkbox" name="remember" <?php echo isset($_COOKIE['remember_id']) ? 'checked' : ''; ?>>
                            <span>Remember me</span>
                        </label>
                        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" name="login" class="login-btn">LOGIN</button>

                    <div class="register-text">
                        Don't have account? <a href="register.php">Register Now</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>