<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$upload_dir = __DIR__ . '/../assets/uploads/';

// Create uploads folder if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../pages/edit_profile.php?error=" . urlencode("No file uploaded"));
    exit();
}

$file      = $_FILES['profile_image'];
$mime_type = mime_content_type($file['tmp_name']);
$allowed   = ['image/jpeg', 'image/png', 'image/jpg'];

if (!in_array($mime_type, $allowed)) {
    header("Location: ../pages/edit_profile.php?error=" . urlencode("Only JPG and PNG files are allowed"));
    exit();
}

if ($file['size'] > 2 * 1024 * 1024) {
    header("Location: ../pages/edit_profile.php?error=" . urlencode("File size must be under 2MB"));
    exit();
}

// Determine extension from mime type
$ext = ($mime_type === 'image/png') ? 'png' : 'jpg';

// Delete old profile images for this user
foreach (['jpg', 'png', 'jpeg'] as $old_ext) {
    $old_file = $upload_dir . "profile_{$user_id}.{$old_ext}";
    if (file_exists($old_file)) {
        unlink($old_file);
    }
}

// Save new profile image
$filename    = "profile_{$user_id}.{$ext}";
$destination = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Success - redirect back to edit profile with success message
    header("Location: ../pages/edit_profile.php?success=photo");
    exit();
} else {
    header("Location: ../pages/edit_profile.php?error=" . urlencode("Failed to save image"));
    exit();
}
?>