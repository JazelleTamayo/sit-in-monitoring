<?php
session_start();
require_once 'config/database.php'; // Include database connection

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Get form data
    $id_number = trim($_POST['id_number'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $year_level = $_POST['year_level'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    // Check if all required fields are filled
    if (empty($id_number)) $errors[] = "ID number is required";
    if (empty($course)) $errors[] = "Course is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($year_level)) $errors[] = "Year level is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($password)) $errors[] = "Password is required";
    
    // Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check password length (min 8 characters)
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $error_string = implode(", ", $errors);
        header("Location: register.php?error=" . urlencode($error_string));
        exit();
    }
    
    try {
        // Check if ID number already exists
        $check_id = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $check_id->execute([$id_number]);
        
        if ($check_id->rowCount() > 0) {
            header("Location: register.php?error=ID number already exists");
            exit();
        }
        
        // Check if email already exists
        $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->execute([$email]);
        
        if ($check_email->rowCount() > 0) {
            header("Location: register.php?error=Email already exists");
            exit();
        }
        
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user into database
        $sql = "INSERT INTO users (id_number, course, last_name, first_name, middle_name, year_level, email, address, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
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
        
        // Registration successful
        header("Location: login.php?success=Registration successful! Please login with your credentials.");
        exit();
        
    } catch(PDOException $e) {
        // Database error
        header("Location: register.php?error=Registration failed. Please try again.");
        exit();
    }
    
} else {
    // If someone tries to access this file directly without POST
    header("Location: register.php");
    exit();
}
?>