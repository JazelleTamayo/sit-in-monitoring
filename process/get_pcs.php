<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$lab = $_GET['lab'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');
$response = [];

if ($lab && $date) {
    // Get all PCs for this lab
    $stmt = $pdo->prepare("SELECT pc_number, status FROM pcs WHERE lab = ?");
    $stmt->execute([$lab]);
    $pcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 1. Reservations that are pending OR approved (NOT completed)
    $resStmt = $pdo->prepare("
        SELECT pc_number FROM reservations 
        WHERE laboratory = ? AND reservation_date = ? AND status IN ('approved', 'pending')
    ");
    $resStmt->execute([$lab, $date]);
    $reserved = $resStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Active sit‑ins today (with a PC number)
    $sitStmt = $pdo->prepare("
        SELECT pc_number FROM sit_in 
        WHERE laboratory = ? AND login_date = ? AND status = 'active' AND pc_number IS NOT NULL
    ");
    $sitStmt->execute([$lab, $date]);
    $occupiedBySit = $sitStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Merge both blockers
    $blocked = array_unique(array_merge($reserved, $occupiedBySit));
    
    foreach ($pcs as $pc) {
        $finalStatus = $pc['status'];
        if ($finalStatus == 'available' && in_array($pc['pc_number'], $blocked)) {
            $finalStatus = 'reserved';
        }
        $response[] = [
            'pc_number' => $pc['pc_number'],
            'status' => $finalStatus
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
