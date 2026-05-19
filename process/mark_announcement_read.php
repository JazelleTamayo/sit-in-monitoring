<?php
session_start();
require_once __DIR__ . '/../includes/get_announcements.php';

// Check if user is logged in and announcement_id is provided
if (!isset($_SESSION['user_id']) || !isset($_POST['announcement_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Mark the announcement as read
$result = markAnnouncementAsRead($_SESSION['user_id'], $_POST['announcement_id']);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark as read']);
}
?>