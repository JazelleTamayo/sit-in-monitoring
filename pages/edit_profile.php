<?php
session_start();

// Auth guard - redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please login first"));
    exit();
}

// Redirect admin to admin dashboard
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: admin_dashboard.php");
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

$pageTitle = "Edit Profile - CCS Sit-in System";
$extraCSS = "dashboard";
$basePath = "../";

// Get current user data from database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If user not found in database, redirect
if (!$user) {
    header("Location: dashboard.php?error=" . urlencode("User not found"));
    exit();
}

// Handle photo upload (store temporarily)
$temp_photo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_temp_photo'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../assets/temp_uploads/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file = $_FILES['profile_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file['type'], $allowed_types)) {
            $_SESSION['temp_photo_error'] = "Only JPG, JPEG, and PNG files are allowed.";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['temp_photo_error'] = "File size must be less than 2MB.";
        } else {
            // Delete old temp files for this user
            foreach (glob($upload_dir . "temp_{$user_id}.*") as $old_temp) {
                unlink($old_temp);
            }
            
            $temp_filename = "temp_{$user_id}.{$file_ext}";
            $destination = $upload_dir . $temp_filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $_SESSION['temp_photo'] = $temp_filename;
                $_SESSION['temp_photo_success'] = "Photo uploaded! Click 'Save Changes' to apply.";
            } else {
                $_SESSION['temp_photo_error'] = "Failed to upload image.";
            }
        }
    }
    header("Location: edit_profile.php");
    exit();
}

// Handle form submission - save everything including photo
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get form data
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update user information
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET last_name = ?, first_name = ?, middle_name = ?, email = ?, address = ? 
                WHERE id = ?
            ");
            $update_stmt->execute([$last_name, $first_name, $middle_name, $email, $address, $user_id]);
            
            // Check if there's a temp photo to save
            if (isset($_SESSION['temp_photo']) && !empty($_SESSION['temp_photo'])) {
                $temp_dir = __DIR__ . '/../assets/temp_uploads/';
                $final_dir = __DIR__ . '/../assets/uploads/';
                $temp_file = $temp_dir . $_SESSION['temp_photo'];
                
                if (file_exists($temp_file)) {
                    // Delete old profile images
                    foreach (['jpg', 'png', 'jpeg'] as $ext) {
                        $old_file = $final_dir . "profile_{$user_id}.{$ext}";
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    // Get file extension from temp file
                    $ext = pathinfo($temp_file, PATHINFO_EXTENSION);
                    $final_filename = "profile_{$user_id}.{$ext}";
                    $final_destination = $final_dir . $final_filename;
                    
                    // Move temp file to final location
                    if (rename($temp_file, $final_destination)) {
                        // Clear temp session
                        unset($_SESSION['temp_photo']);
                        unset($_SESSION['temp_photo_success']);
                    }
                }
            }
            
            $pdo->commit();
            
            // Update session data - INCLUDE MIDDLE NAME
            $full_name = trim($first_name . ($middle_name ? ' ' . $middle_name : '') . ' ' . $last_name);
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_email'] = $email;
            $_SESSION['address'] = $address;
            
            $success_message = "Profile updated successfully!";
            
            // Refresh user data
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Profile update error: " . $e->getMessage());
            $error_message = "Failed to update profile. Please try again.";
        }
    } else {
        $error_message = implode(", ", $errors);
    }
}

// Get temp photo messages
$temp_photo_success = $_SESSION['temp_photo_success'] ?? '';
$temp_photo_error = $_SESSION['temp_photo_error'] ?? '';
unset($_SESSION['temp_photo_success'], $_SESSION['temp_photo_error']);

// Check which photo to display (temp or final)
$display_photo = '';
$temp_dir = __DIR__ . '/../assets/temp_uploads/';
$final_dir = __DIR__ . '/../assets/uploads/';

if (isset($_SESSION['temp_photo']) && file_exists($temp_dir . $_SESSION['temp_photo'])) {
    $display_photo = '../assets/temp_uploads/' . $_SESSION['temp_photo'] . '?v=' . time();
} else {
    foreach (['jpg', 'png', 'jpeg'] as $ext) {
        $file = "profile_{$user_id}.{$ext}";
        if (file_exists($final_dir . $file)) {
            $display_photo = '../assets/uploads/' . $file . '?v=' . time();
            break;
        }
    }
}

// Get full name with middle name for display
$display_fullname = trim($user['first_name'] . ($user['middle_name'] ? ' ' . $user['middle_name'] : '') . ' ' . $user['last_name']);
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/user_navigation.php'; ?>

<!-- Edit Profile Container -->
<div class="dashboard-container">
    <div class="dashboard-main" style="max-width: 800px; margin: 0 auto;">
        
        <!-- Page Header -->
        <div class="dashboard-header">
            <h1>Edit Profile</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- Photo Upload Temp Messages -->
        <?php if ($temp_photo_success): ?>
            <div class="alert success" style="background: rgba(16,185,129,0.1); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.2); padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle"></i>
                <?php echo $temp_photo_success; ?>
            </div>
        <?php endif; ?>

        <?php if ($temp_photo_error): ?>
            <div class="alert error" style="background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $temp_photo_error; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Update Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert success" style="background: rgba(16,185,129,0.1); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.2); padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert error" style="background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- ── PROFILE PICTURE CARD ───────────────────────────────────────── -->
        <div class="card" style="padding: 30px; margin-bottom: 20px;">
            <div class="card-header" style="margin-bottom: 25px;">
                <div class="card-title">
                    <i class="fas fa-camera"></i>
                    <h2>Profile Picture</h2>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 30px; flex-wrap: wrap;">

                <!-- Avatar with camera overlay -->
                <div class="profile-image-wrapper" style="flex-shrink: 0;">
                    <div class="profile-image">
                        <?php if ($display_photo): ?>
                            <img src="<?php echo $display_photo; ?>" alt="Profile" id="profileImage">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                    <!-- Camera overlay -->
                    <div class="profile-upload-overlay"
                         onclick="document.getElementById('profileUpload').click();"
                         title="Change photo">
                        <i class="fas fa-camera"></i>
                    </div>
                    <!-- Upload form - stores temporarily -->
                    <form id="uploadForm" action="" method="POST" enctype="multipart/form-data">
                        <input type="file" name="profile_image" id="profileUpload"
                               accept="image/jpeg,image/png,image/jpg" style="display:none;"
                               onchange="uploadTempPhoto()">
                        <input type="hidden" name="upload_temp_photo" value="1">
                    </form>
                </div>

                <!-- Instructions -->
                <div style="flex: 1; min-width: 200px;">
                    <p style="color: #f1f5f9; font-size: 0.95rem; font-weight: 600; margin: 0 0 8px 0;">
                        <?php echo htmlspecialchars($display_fullname); ?>
                    </p>
                    <p style="color: #64748b; font-size: 0.82rem; margin: 0 0 16px 0;">
                        <?php echo htmlspecialchars($user['id_number']); ?>
                        &nbsp;&mdash;&nbsp;
                        <?php echo htmlspecialchars($user['course']); ?>
                    </p>
                    <button type="button"
                            onclick="document.getElementById('profileUpload').click();"
                            style="background: rgba(96,165,250,0.12); color: #60a5fa; border: 1px solid rgba(96,165,250,0.25); padding: 10px 22px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.25s ease;"
                            onmouseover="this.style.background='rgba(96,165,250,0.22)'; this.style.borderColor='rgba(96,165,250,0.5)';"
                            onmouseout="this.style.background='rgba(96,165,250,0.12)'; this.style.borderColor='rgba(96,165,250,0.25)';">
                        <i class="fas fa-upload"></i>
                        Upload New Photo
                    </button>
                    <p style="color: #475569; font-size: 0.75rem; margin: 10px 0 0 0;">
                        <i class="fas fa-info-circle" style="margin-right: 4px;"></i>
                        Photo will only be saved when you click "Save Changes"
                    </p>
                </div>

            </div>
        </div><!-- /.profile picture card -->

        <!-- Edit Profile Form -->
        <div class="card" style="padding: 30px;">
            <div class="card-header" style="margin-bottom: 25px;">
                <div class="card-title">
                    <i class="fas fa-user-edit"></i>
                    <h2>Personal Information</h2>
                </div>
            </div>

            <form method="POST" action="" style="width: 100%;">
                <!-- ID Number (Read-only) -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-id-card" style="margin-right: 5px; color: #60a5fa;"></i>
                        ID Number
                    </label>
                    <input type="text" 
                           value="<?php echo htmlspecialchars($user['id_number']); ?>" 
                           style="width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #94a3b8; font-size: 0.92rem; cursor: not-allowed;"
                           readonly disabled>
                    <small style="color: #475569; font-size: 0.75rem; margin-top: 5px; display: block;">ID number cannot be changed</small>
                </div>

                <!-- Last Name -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-user" style="margin-right: 5px; color: #60a5fa;"></i>
                        Last Name <span style="color: #f87171;">*</span>
                    </label>
                    <input type="text" 
                           name="last_name"
                           value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                           style="width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #f1f5f9; font-size: 0.92rem; transition: all 0.2s ease;"
                           onfocus="this.style.borderColor='rgba(96,165,250,0.45)'; this.style.background='rgba(255,255,255,0.08)';"
                           onblur="this.style.borderColor='rgba(255,255,255,0.08)'; this.style.background='rgba(255,255,255,0.05)';"
                           required>
                </div>

                <!-- First Name -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-user" style="margin-right: 5px; color: #60a5fa;"></i>
                        First Name <span style="color: #f87171;">*</span>
                    </label>
                    <input type="text" 
                           name="first_name"
                           value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                           style="width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #f1f5f9; font-size: 0.92rem; transition: all 0.2s ease;"
                           onfocus="this.style.borderColor='rgba(96,165,250,0.45)'; this.style.background='rgba(255,255,255,0.08)';"
                           onblur="this.style.borderColor='rgba(255,255,255,0.08)'; this.style.background='rgba(255,255,255,0.05)';"
                           required>
                </div>

                <!-- Middle Name -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-user" style="margin-right: 5px; color: #60a5fa;"></i>
                        Middle Name
                    </label>
                    <input type="text" 
                           name="middle_name"
                           value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>" 
                           style="width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #f1f5f9; font-size: 0.92rem; transition: all 0.2s ease;"
                           onfocus="this.style.borderColor='rgba(96,165,250,0.45)'; this.style.background='rgba(255,255,255,0.08)';"
                           onblur="this.style.borderColor='rgba(255,255,255,0.08)'; this.style.background='rgba(255,255,255,0.05)';">
                </div>

                <!-- Course (Read-only) -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-graduation-cap" style="margin-right: 5px; color: #60a5fa;"></i>
                        Course
                    </label>
                    <input type="text" 
                           value="<?php echo htmlspecialchars($user['course']); ?>" 
                           style="width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #94a3b8; font-size: 0.92rem; cursor: not-allowed;"
                           readonly disabled>
                    <small style="color: #475569; font-size: 0.75rem; margin-top: 5px; display: block;">Contact admin to change course</small>
                </div>

                <!-- Year Level (Read-only) -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-layer-group" style="margin-right: 5px; color: #60a5fa;"></i>
                        Year Level
                    </label>
                    <input type="text" 
                           value="<?php 
                                $year = $user['year_level'];
                                $suffix = $year == 1 ? 'st' : ($year == 2 ? 'nd' : ($year == 3 ? 'rd' : 'th'));
                                echo $year . $suffix . ' Year'; 
                           ?>" 
                           style="width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #94a3b8; font-size: 0.92rem; cursor: not-allowed;"
                           readonly disabled>
                </div>

                <!-- Email -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-envelope" style="margin-right: 5px; color: #60a5fa;"></i>
                        Email <span style="color: #f87171;">*</span>
                    </label>
                    <input type="email" 
                           name="email"
                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                           style="width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #f1f5f9; font-size: 0.92rem; transition: all 0.2s ease;"
                           onfocus="this.style.borderColor='rgba(96,165,250,0.45)'; this.style.background='rgba(255,255,255,0.08)';"
                           onblur="this.style.borderColor='rgba(255,255,255,0.08)'; this.style.background='rgba(255,255,255,0.05)';"
                           required>
                </div>

                <!-- Address -->
                <div style="margin-bottom: 30px;">
                    <label style="display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-map-marker-alt" style="margin-right: 5px; color: #60a5fa;"></i>
                        Address
                    </label>
                    <input type="text" 
                           name="address"
                           value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" 
                           style="width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #f1f5f9; font-size: 0.92rem; transition: all 0.2s ease;"
                           onfocus="this.style.borderColor='rgba(96,165,250,0.45)'; this.style.background='rgba(255,255,255,0.08)';"
                           onblur="this.style.borderColor='rgba(255,255,255,0.08)'; this.style.background='rgba(255,255,255,0.05)';">
                </div>

                <!-- Buttons - Centered -->
                <div style="display: flex; gap: 15px; justify-content: center; align-items: center; margin-top: 20px;">
                    <button type="submit" 
                            name="update_profile"
                            style="background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; border: none; padding: 14px 32px; border-radius: 999px; font-size: 0.95rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 20px rgba(37,99,235,0.4); transition: all 0.25s ease;"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 36px rgba(37,99,235,0.55)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(37,99,235,0.4)';">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                    
                    <a href="dashboard.php" 
                       style="background: rgba(255,255,255,0.07); color: #94a3b8; text-decoration: none; padding: 14px 32px; border-radius: 999px; font-size: 0.95rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.08); transition: all 0.25s ease;"
                       onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.color='#f1f5f9';"
                       onmouseout="this.style.background='rgba(255,255,255,0.07)'; this.style.color='#94a3b8';">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function uploadTempPhoto() {
        const form = document.getElementById('uploadForm');
        const overlay = document.querySelector('.profile-upload-overlay');
        const saved = overlay.innerHTML;

        overlay.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        overlay.style.pointerEvents = 'none';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '', true);

        xhr.onload = function () {
            overlay.innerHTML = saved;
            overlay.style.pointerEvents = 'auto';
            if (xhr.status === 200) {
                location.reload();
            }
        };

        xhr.onerror = function () {
            overlay.innerHTML = saved;
            overlay.style.pointerEvents = 'auto';
            alert('Upload failed. Please try again.');
        };

        xhr.send(new FormData(form));
    }
</script>

<?php include '../includes/footer.php'; ?>