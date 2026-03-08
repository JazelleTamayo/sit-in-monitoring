<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Delete remember me cookie if exists
if (isset($_COOKIE['remember_id'])) {
    setcookie('remember_id', '', time() - 3600, '/');
}

// Redirect to login page with success message
header("Location: login.php?success=You have been successfully logged out");
exit();
?>