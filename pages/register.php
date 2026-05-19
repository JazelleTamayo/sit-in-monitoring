<?php
session_start();
$pageTitle = "Register - CCS Sit-in System";
$basePath = "../";
$extraCSS = "register";
$extraJS = "register";
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/navigation.php'; ?>

<div class="register-page">
    <div class="register-container">

        <!-- Header -->
        <div class="register-header">
            <h2>Create Account</h2>
            <p class="subtitle">Join the CCS Sit-in Monitoring System</p>
        </div>

        <!-- Alerts -->
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

        <!-- Registration Form -->
        <form action="../process/register_process.php" method="POST" id="registerForm">

            <!-- Personal Information -->
            <div class="form-section">
                <h3><i class="fas fa-user"></i> Personal Information</h3>

                <div class="form-row">
                    <div class="input-group">
                        <label for="idNumber">ID Number <span class="required">*</span></label>
                        <input type="text"
                               id="idNumber"
                               name="id_number"
                               placeholder="e.g., 2024-0001"
                               value="<?php echo isset($_GET['id_number']) ? htmlspecialchars($_GET['id_number']) : ''; ?>"
                               required>
                        <small class="hint">Format: YYYY-XXXX</small>
                    </div>

                    <div class="input-group">
                        <label for="course">Course <span class="required">*</span></label>
                        <select id="course" name="course" required>
                            <option value="">Select Course</option>
                            <option value="BSIT">BS Information Technology</option>
                            <option value="BSCS">BS Computer Science</option>
                            <option value="BSIS">BS Information Systems</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label for="lastName">Last Name <span class="required">*</span></label>
                        <input type="text"
                               id="lastName"
                               name="last_name"
                               placeholder="Enter last name"
                               value="<?php echo isset($_GET['last_name']) ? htmlspecialchars($_GET['last_name']) : ''; ?>"
                               required>
                    </div>

                    <div class="input-group">
                        <label for="firstName">First Name <span class="required">*</span></label>
                        <input type="text"
                               id="firstName"
                               name="first_name"
                               placeholder="Enter first name"
                               value="<?php echo isset($_GET['first_name']) ? htmlspecialchars($_GET['first_name']) : ''; ?>"
                               required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label for="middleName">Middle Name</label>
                        <input type="text"
                               id="middleName"
                               name="middle_name"
                               placeholder="Optional"
                               value="<?php echo isset($_GET['middle_name']) ? htmlspecialchars($_GET['middle_name']) : ''; ?>">
                    </div>

                    <div class="input-group">
                        <label for="yearLevel">Year Level <span class="required">*</span></label>
                        <select id="yearLevel" name="year_level" required>
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="form-section">
                <h3><i class="fas fa-lock"></i> Account Information</h3>

                <div class="form-row">
                    <div class="input-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email"
                               id="email"
                               name="email"
                               placeholder="example@email.com"
                               value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>"
                               required>
                    </div>

                    <div class="input-group">
                        <label for="address">Address</label>
                        <input type="text"
                               id="address"
                               name="address"
                               placeholder="Enter your address"
                               value="<?php echo isset($_GET['address']) ? htmlspecialchars($_GET['address']) : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   placeholder="Min. 8 characters"
                                   required>
                            <i class="fas fa-eye-slash toggle-password"></i>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar"></div>
                        </div>
                        <ul class="password-requirements">
                            <li id="req-length"><i class="fas fa-circle"></i> At least 8 characters</li>
                            <li id="req-number"><i class="fas fa-circle"></i> At least 1 number</li>
                            <li id="req-upper"><i class="fas fa-circle"></i> At least 1 uppercase letter</li>
                        </ul>
                    </div>

                    <div class="input-group">
                        <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password"
                                   id="confirmPassword"
                                   name="confirm_password"
                                   placeholder="Confirm your password"
                                   required>
                            <i class="fas fa-eye-slash toggle-password"></i>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" name="register" class="btn-register">
                <i class="fas fa-user-plus"></i> Create Account
            </button>

            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>

        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>