<?php
session_start();
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) { exit(); }
require_once __DIR__ . '/../config/database.php';

if (!empty($_POST['password'])) {
    $stmt = $pdo->prepare("UPDATE users SET id_number=?, first_name=?, last_name=?, middle_name=?, course=?, year_level=?, email=?, address=?, password=? WHERE id=?");
    $stmt->execute([
        $_POST['id_number'], $_POST['first_name'], $_POST['last_name'],
        $_POST['middle_name'] ?? '', $_POST['course'], $_POST['year_level'],
        $_POST['email'], $_POST['address'] ?? '',
        password_hash($_POST['password'], PASSWORD_BCRYPT),
        $_POST['student_id']
    ]);
} else {
    $stmt = $pdo->prepare("UPDATE users SET id_number=?, first_name=?, last_name=?, middle_name=?, course=?, year_level=?, email=?, address=? WHERE id=?");
    $stmt->execute([
        $_POST['id_number'], $_POST['first_name'], $_POST['last_name'],
        $_POST['middle_name'] ?? '', $_POST['course'], $_POST['year_level'],
        $_POST['email'], $_POST['address'] ?? '',
        $_POST['student_id']
    ]);
}

header("Location: ../pages/admin_students.php?msg=updated");
exit();
?>