<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $id_number = $_POST['id_number'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($id_number) && !empty($password)) {
        
        // SET SESSION VARIABLES - THIS IS CRITICAL!
        $_SESSION['user_id'] = 1;
        $_SESSION['id_number'] = $id_number;
        
        header("Location: dashboard.php");
        exit();
        
    } else {
        header("Location: login.php?error=Invalid ID number or password");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>