<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id_number = trim($_POST['id_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($id_number) || empty($password)) {
        header("Location: login.php?error=Please fill in all fields");
        exit();
    }
    
    // CHECK FOR HARDCODED ADMIN
    if ($id_number === '23756257' && $password === 'pigmea23') {
        // Admin login successful
        $_SESSION['user_id'] = 'admin';
        $_SESSION['id_number'] = '23756257';
        $_SESSION['user_name'] = 'Admin Jazelle';
        $_SESSION['user_email'] = 'admin@ccs.edu';
        $_SESSION['role'] = 'admin';
        $_SESSION['is_admin'] = true;
        
        header("Location: admin_dashboard.php");
        exit();
    }
    
    try {
        // Regular user login from database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['id_number'] = $user['id_number'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['course'] = $user['course'];
            $_SESSION['year_level'] = $user['year_level'];
            $_SESSION['role'] = 'student';
            $_SESSION['is_admin'] = false;
            
            if ($remember) {
                setcookie('remember_id', $id_number, time() + (86400 * 30), "/");
            } else {
                setcookie('remember_id', '', time() - 3600, "/");
            }
            
            header("Location: dashboard.php");
            exit();
            
        } else {
            header("Location: login.php?error=Invalid ID number or password");
            exit();
        }
        
    } catch(PDOException $e) {
        header("Location: login.php?error=Login failed. Please try again.");
        exit();
    }
    
} else {
    header("Location: login.php");
    exit();
}
?>