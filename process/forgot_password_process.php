<?php
session_start();
require_once __DIR__ . '/../config/database.php';

define('BASE_URL', '/sit-in-monitoring-main');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/pages/forgot_password.php");
    exit();
}

$step = $_POST['step'] ?? '';

if ($step === 'lookup') {
    $id_number = trim($_POST['id_number'] ?? '');

    if (empty($id_number)) {
        header("Location: " . BASE_URL . "/pages/forgot_password.php?error=" . urlencode("Please enter your ID number."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ? LIMIT 1");
        $stmt->execute([$id_number]);
        $user = $stmt->fetch();

        if (!$user) {
            header("Location: " . BASE_URL . "/pages/forgot_password.php?error=" . urlencode("No account found with that ID number."));
            exit();
        }

        header("Location: " . BASE_URL . "/pages/forgot_password.php?step=verify&id_number=" . urlencode($id_number));
        exit();

    } catch (PDOException $e) {
        header("Location: " . BASE_URL . "/pages/forgot_password.php?error=" . urlencode("Something went wrong. Please try again."));
        exit();
    }
}

if ($step === 'verify') {
    $id_number = trim($_POST['id_number'] ?? '');
    $email     = trim($_POST['email']     ?? '');

    if (empty($id_number) || empty($email)) {
        header("Location: " . BASE_URL . "/pages/forgot_password.php?step=verify&id_number=" . urlencode($id_number) . "&error=" . urlencode("Please fill in all fields."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ? AND email = ? LIMIT 1");
        $stmt->execute([$id_number, $email]);
        $user = $stmt->fetch();

        if (!$user) {
            header("Location: " . BASE_URL . "/pages/forgot_password.php?step=verify&id_number=" . urlencode($id_number) . "&error=" . urlencode("The email address does not match our records."));
            exit();
        }

        header("Location: " . BASE_URL . "/pages/forgot_password.php?step=reset&id_number=" . urlencode($id_number));
        exit();

    } catch (PDOException $e) {
        header("Location: " . BASE_URL . "/pages/forgot_password.php?step=verify&id_number=" . urlencode($id_number) . "&error=" . urlencode("Something went wrong. Please try again."));
        exit();
    }
}

if ($step === 'reset') {
    $id_number        = trim($_POST['id_number']        ?? '');
    $new_password     = $_POST['new_password']           ?? '';
    $confirm_password = $_POST['confirm_password']       ?? '';

    if (empty($id_number) || empty($new_password) || empty($confirm_password)) {
        header("Location: " . BASE_URL . "/pages/forgot_password.php?step=reset&id_number=" . urlencode($id_number) . "&error=" . urlencode("Please fill in all fields."));
        exit();
    }

    if (strlen($new_password) < 6) {
        header("Location: " . BASE_URL . "/pages/forgot_password.php?step=reset&id_number=" . urlencode($id_number) . "&error=" . urlencode("Password must be at least 6 characters."));
        exit();
    }

    if ($new_password !== $confirm_password) {
        header("Location: " . BASE_URL . "/pages/forgot_password.php?step=reset&id_number=" . urlencode($id_number) . "&error=" . urlencode("Passwords do not match."));
        exit();
    }

    try {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id_number = ?");
        $stmt->execute([$hashed, $id_number]);

        header("Location: " . BASE_URL . "/pages/login.php?success=" . urlencode("Password reset successfully! You can now log in."));
        exit();

    } catch (PDOException $e) {
        header("Location: " . BASE_URL . "/pages/forgot_password.php?step=reset&id_number=" . urlencode($id_number) . "&error=" . urlencode("Something went wrong. Please try again."));
        exit();
    }
}

header("Location: " . BASE_URL . "/pages/forgot_password.php");
exit();
?>