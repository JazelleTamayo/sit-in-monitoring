<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/get_announcements.php'; // Add this for notification functions

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/login.php");
    exit();
}

$id_number = trim($_POST['id_number'] ?? '');
$password  = $_POST['password']  ?? '';
$remember  = isset($_POST['remember']);

// ── Validate empty fields ──────────────────────────────────────────────────
if (empty($id_number) || empty($password)) {
    header("Location: ../pages/login.php?error=" . urlencode("Please fill in all fields."));
    exit();
}

// ── Hardcoded admin check ──────────────────────────────────────────────────
if ($id_number === '23756257' && $password === 'pigmea23') {
    $_SESSION['user_id']    = 'admin';
    $_SESSION['id_number']  = '23756257';
    $_SESSION['user_name']  = 'Admin Jazelle';
    $_SESSION['user_email'] = 'admin@ccs.edu';
    $_SESSION['role']       = 'admin';
    $_SESSION['is_admin']   = true;

    header("Location: ../pages/admin_dashboard.php?success=" . urlencode("Login successful! Welcome back, Admin Jazelle!"));
    exit();
}

// ── Regular user login from database ──────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? LIMIT 1");
    $stmt->execute([$id_number]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id']            = $user['id'];
        $_SESSION['id_number']          = $user['id_number'];
        $_SESSION['user_name']          = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email']         = $user['email'];
        $_SESSION['course']             = $user['course'];
        $_SESSION['year_level']         = $user['year_level'];
        $_SESSION['address']            = $user['address'];
        $_SESSION['role']               = 'student';
        $_SESSION['is_admin']           = false;
        $_SESSION['sessions_remaining'] = $user['sessions'] ?? 30;

        // ============ ADD WELCOME NOTIFICATION FOR FIRST TIME USERS ============
        // Check if user already has a welcome notification
        $checkQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND type = 'welcome'";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':user_id', $user['id']);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            addNotification($user['id'], 'welcome', 'Welcome to CCS Sit-in System', 
                            'Welcome! You can now reserve laboratories and track your sit-in sessions.', 
                            'dashboard.php');
        }
        // ============ END OF WELCOME NOTIFICATION ============

        // Remember-me cookie (30 days)
        if ($remember) {
            setcookie('remember_id', $id_number, time() + (86400 * 30), "/", "", false, true);
        } else {
            setcookie('remember_id', '', time() - 3600, "/");
        }

        $first_name = htmlspecialchars($user['first_name']);
        header("Location: ../pages/dashboard.php?success=" . urlencode("Welcome back, {$first_name}! You have successfully logged in."));
        exit();

    } else {
        header("Location: ../pages/login.php?error=" . urlencode("Invalid ID number or password."));
        exit();
    }

} catch (PDOException $e) {
    error_log("Login PDOException: " . $e->getMessage());
    header("Location: ../pages/login.php?error=" . urlencode("Login failed. Please try again."));
    exit();
}
?>