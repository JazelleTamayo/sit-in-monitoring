<?php
session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['found' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$id_number = trim($_GET['id_number'] ?? '');

if (empty($id_number)) {
    echo json_encode(['found' => false, 'error' => 'No ID provided']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, id_number, first_name, last_name, middle_name, course, year_level, sessions FROM users WHERE id_number = ? LIMIT 1");
    $stmt->execute([$id_number]);
    $student = $stmt->fetch();

    if ($student) {
        $name = trim($student['first_name'] . ' ' . $student['last_name']);

        echo json_encode([
            'found'    => true,
            'name'     => $name,
            'sessions' => $student['sessions'] ?? 30,
            'user_id'  => $student['id'],
            'course'   => $student['course'],
            'year'     => $student['year_level']
        ]);
    } else {
        echo json_encode(['found' => false]);
    }

} catch (Exception $e) {
    echo json_encode(['found' => false, 'error' => $e->getMessage()]);
}
?>