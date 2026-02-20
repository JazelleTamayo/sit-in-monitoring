<?php
session_start();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // For demo purposes, just redirect to login with success message
    header("Location: login.php?success=Registration successful! Please login with your credentials.");
    exit();
    
} else {
    // If someone tries to access this file directly without POST
    header("Location: register.php");
    exit();
}
?>