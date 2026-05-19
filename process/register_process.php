<?php
session_start();

// ─── PATH FIX ────────────────────────────────────────────────────────────────
// This file lives in:  sit-in-monitoring/process/register_process.php
// database.php lives in: sit-in-monitoring/config/database.php
// __DIR__ = absolute path to /process, so we go up one level with /../
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/register.php");
    exit();
}

// ── Collect & sanitize form data ───────────────────────────────────────────
$id_number      = trim($_POST['id_number']       ?? '');
$course         = trim($_POST['course']          ?? '');
$last_name      = trim($_POST['last_name']       ?? '');
$first_name     = trim($_POST['first_name']      ?? '');
$middle_name    = trim($_POST['middle_name']     ?? '');
$year_level     = trim($_POST['year_level']      ?? '');
$email          = trim($_POST['email']           ?? '');
$address        = trim($_POST['address']         ?? '');
$password       = $_POST['password']             ?? '';
$confirm_password = $_POST['confirm_password']   ?? '';

// ── Validation ─────────────────────────────────────────────────────────────
$errors = [];

if (empty($id_number))   $errors[] = "ID number is required";
if (empty($course))      $errors[] = "Course is required";
if (empty($last_name))   $errors[] = "Last name is required";
if (empty($first_name))  $errors[] = "First name is required";
if (empty($year_level))  $errors[] = "Year level is required";
if (empty($email))       $errors[] = "Email is required";
if (empty($address))     $errors[] = "Address is required";
if (empty($password))    $errors[] = "Password is required";

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}

if ($password !== $confirm_password) {
    $errors[] = "Passwords do not match";
}

if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters";
}

if (!empty($errors)) {
    header("Location: ../pages/register.php?error=" . urlencode(implode(", ", $errors)));
    exit();
}

// ── Database checks & insert ───────────────────────────────────────────────
try {
    // Check if ID number already exists
    $check_id = $pdo->prepare("SELECT id FROM users WHERE id_number = ? LIMIT 1");
    $check_id->execute([$id_number]);

    if ($check_id->rowCount() > 0) {
        header("Location: ../pages/register.php?error=" . urlencode("ID number already exists."));
        exit();
    }

    // Check if email already exists
    $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $check_email->execute([$email]);

    if ($check_email->rowCount() > 0) {
        header("Location: ../pages/register.php?error=" . urlencode("Email already exists."));
        exit();
    }

    // Hash password & insert new user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users 
            (id_number, course, last_name, first_name, middle_name, year_level, email, address, password)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $id_number,
        $course,
        $last_name,
        $first_name,
        $middle_name,
        $year_level,
        $email,
        $address,
        $hashed_password
    ]);

    header("Location: ../pages/login.php?success=" . urlencode("Registration successful! Please login with your credentials."));
    exit();

} catch (PDOException $e) {
    error_log("Register PDOException: " . $e->getMessage());
    header("Location: ../pages/register.php?error=" . urlencode("Registration failed. Please try again."));
    exit();
}
?>