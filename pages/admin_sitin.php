<?php
session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Sit-in - CCS Sit-in System";
$basePath  = "../";

$success_message = '';
$error_message   = '';

// ── Handle Sit In form submission ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sitin_submit'])) {
    $id_number = trim($_POST['id_number'] ?? '');
    $purpose   = trim($_POST['purpose']   ?? '');
    $lab       = trim($_POST['lab']       ?? '');
    $pc_number = trim($_POST['pc_number'] ?? '');

    if (empty($id_number) || empty($purpose) || empty($lab) || empty($pc_number)) {
        $error_message = "All fields are required including PC number.";
    } else {
        // Find the student
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? LIMIT 1");
        $stmt->execute([$id_number]);
        $student = $stmt->fetch();

        if (!$student) {
            $error_message = "No student found with ID number: " . htmlspecialchars($id_number);
        } else {
            // Check if student already has an active sit-in session
            $check = $pdo->prepare("SELECT id FROM sit_in WHERE user_id = ? AND status = 'active' LIMIT 1");
            $check->execute([$student['id']]);
            if ($check->fetch()) {
                $error_message = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . " already has an active sit-in session.";
            } elseif (($student['sessions'] ?? 30) <= 0) {
                $error_message = "This student has no remaining sessions.";
            } else {
                // Check if PC is available
                $checkPc = $pdo->prepare("
                    SELECT COUNT(*) FROM sit_in 
                    WHERE laboratory = ? AND pc_number = ? AND status = 'active' AND login_date = CURDATE()
                ");
                $checkPc->execute([$lab, $pc_number]);
                if ($checkPc->fetchColumn() > 0) {
                    $error_message = "PC $pc_number is currently occupied in Lab $lab.";
                } else {
                    // Insert into sit_in table with PC number
                    $insert = $pdo->prepare("
                        INSERT INTO sit_in (user_id, id_number, name, purpose, laboratory, pc_number, login_time, login_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), CURDATE(), 'active')
                    ");
                    $full_name = trim($student['first_name'] . ' ' . $student['last_name']);
                    $insert->execute([
                        $student['id'],
                        $id_number,
                        $full_name,
                        $purpose,
                        $lab,
                        $pc_number
                    ]);

                    // Deduct 1 session from the student
                    $pdo->prepare("UPDATE users SET sessions = sessions - 1 WHERE id = ?")
                        ->execute([$student['id']]);

                    // ── Send notification for MANUAL sit-in (NO reservation) ─────────────────
                    $notifTitle = "Sit-in Session Started";
                    $notifMessage = "You have been checked in for a sit-in session at Lab $lab on PC $pc_number. Purpose: $purpose. Please proceed to your assigned PC.";
                    $addNotif = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'sitin', ?, ?, 'dashboard.php')");
                    $addNotif->execute([$student['id'], $notifTitle, $notifMessage]);

                    $success_message = "Sit-in recorded for " . htmlspecialchars($full_name) . " on PC $pc_number!";
                }
            }
        }
    }
}

// ── Handle Timeout with Reward Points (always notify) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timeout_submit'])) {
    $sit_in_id = (int)$_POST['sit_in_id'];
    $give_points = isset($_POST['give_points']) ? (int)$_POST['give_points'] : 0;
    
    try {
        // Get sit-in record
        $stmt = $pdo->prepare("SELECT * FROM sit_in WHERE id = ? AND status = 'active'");
        $stmt->execute([$sit_in_id]);
        $sit_in = $stmt->fetch();
        
        if ($sit_in) {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update sit_in record
            $update = $pdo->prepare("UPDATE sit_in SET logout_time = NOW(), status = 'completed', reward_points_given = reward_points_given + ? WHERE id = ?");
            $update->execute([$give_points, $sit_in_id]);
            
            // Mark the associated approved reservation as completed
            $updateRes = $pdo->prepare("
                UPDATE reservations 
                SET status = 'completed' 
                WHERE id = ? AND status = 'approved'
            ");
            $updateRes->execute([$sit_in['reservation_id']]);
            
            $sessions_added = 0;
            
            if ($give_points > 0) {
                // Get current reward points
                $pointsStmt = $pdo->prepare("SELECT reward_points FROM users WHERE id = ? FOR UPDATE");
                $pointsStmt->execute([$sit_in['user_id']]);
                $current_points = $pointsStmt->fetchColumn();
                
                $new_points = $current_points + $give_points;
                
                // Check if points reached 3 or more
                if ($new_points >= 3) {
                    $sessions_added = floor($new_points / 3);
                    $new_points = $new_points % 3;
                    
                    // Update sessions
                    $updateSessions = $pdo->prepare("UPDATE users SET sessions = sessions + ? WHERE id = ?");
                    $updateSessions->execute([$sessions_added, $sit_in['user_id']]);
                }
                
                // Update reward points
                $updatePoints = $pdo->prepare("UPDATE users SET reward_points = ? WHERE id = ?");
                $updatePoints->execute([$new_points, $sit_in['user_id']]);
                
                $notifMessage = "Your sit-in session has been completed. You received $give_points reward point(s)! ";
                if ($sessions_added > 0) {
                    $notifMessage .= "🎉 Congratulations! Your reward points reached 3 and have been converted to $sessions_added extra session(s)!";
                } else {
                    $notifMessage .= "You now have $new_points reward point(s). Get 3 points to earn +1 session!";
                }
            } else {
                // No reward points given – still notify completion
                $notifMessage = "Your sit-in session has been completed. No reward points were awarded this time.";
            }
            
            // Always add notification (whether points were given or not)
            $addNotif = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'sitin', 'Sit-in Completed', ?, 'history.php')");
            $addNotif->execute([$sit_in['user_id'], $notifMessage]);
            
            $pdo->commit();
            
            $success_message = "Student timed out successfully!";
            if ($give_points > 0) {
                $success_message .= " Awarded $give_points reward point(s).";
                if ($sessions_added > 0) {
                    $success_message .= " Student gained $sessions_added bonus session(s)!";
                }
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Failed to time out: " . $e->getMessage();
    }
    
    header("Location: admin_sitin.php");
    exit();
}

// ── Fetch active sit-in sessions ──────────────────────────────────────────
$active_sessions = $pdo->query("
    SELECT s.*, u.course, u.year_level
    FROM sit_in s
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'active'
    ORDER BY s.login_time DESC
")->fetchAll();

$laboratories = ['524', '526', '528', '530', '517'];
$purposes     = ['C Programming','Java','PHP','ASP.Net','C#','Python','Research','Thesis','Capstone','Other'];
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
:root {
    --primary: #2563eb;
    --primary-light: #3b82f6;
    --accent: #0ea5e9;
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --text-label: #7dd3fc;
    --border-light: rgba(255,255,255,0.10);
    --border-hover: rgba(14,165,233,0.40);
    --shadow-md: 0 8px 32px rgba(0,0,0,0.50);
    --shadow-lg: 0 20px 60px rgba(0,0,0,0.60);
    --radius-lg: 28px;
    --radius-md: 16px;
    --radius-sm: 10px;
    --transition: all 0.25s ease;
    --card-bg: rgba(10,18,40,0.82);
    --card-bg-hover: rgba(14,24,52,0.90);
    --card-border: rgba(255,255,255,0.10);
    --card-border-hover: rgba(14,165,233,0.45);
    --success: #10b981;
    --danger: #ef4444;
}

.sitin-page-container {
    min-height: 100vh;
    padding: 1.5rem 32px 48px 32px;
    position: relative;
}

.sitin-page-container::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 5% 0%, rgba(37,99,235,0.35) 0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%),
        radial-gradient(ellipse at 75% 15%, rgba(124,58,237,0.18) 0%, transparent 38%),
        radial-gradient(ellipse at 25% 85%, rgba(16,185,129,0.12) 0%, transparent 38%);
    pointer-events: none;
    z-index: -1;
}

.sitin-main {
    max-width: 1300px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-light);
}

.page-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

.date-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: rgba(10,18,40,0.70);
    border: 1px solid var(--border-light);
    border-radius: 999px;
    color: var(--text-secondary);
    font-size: 0.875rem;
    backdrop-filter: blur(12px);
}

.date-badge i { color: var(--accent); }

.alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    border-radius: var(--radius-sm);
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
}

.alert-success {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.25);
    color: #6ee7b7;
}

.alert-error {
    background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.25);
    color: #fca5a5;
}

.dash-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-md);
    backdrop-filter: blur(24px);
    overflow: hidden;
    transition: var(--transition);
    margin-bottom: 1.5rem;
}

.dash-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: rgba(37,99,235,0.20);
    border-bottom: 1px solid var(--border-light);
}

.dash-card-header-left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.dash-card-header i { color: var(--accent); }

.dash-card-header h3 {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.dash-card-body { padding: 1.25rem; }

.btn-sitin-open {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    background: linear-gradient(135deg, var(--primary), #1d4ed8);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
}

.btn-sitin-open:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37,99,235,0.4);
}

.results-table-wrapper { overflow-x: auto; }

.results-table {
    width: 100%;
    border-collapse: collapse;
}

.results-table thead tr {
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid var(--border-light);
}

.results-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}

.results-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: var(--transition);
}

.results-table tbody tr:last-child { border-bottom: none; }

.results-table tbody tr:hover { background: rgba(255,255,255,0.04); }

.results-table td {
    padding: 1rem 1.25rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
    vertical-align: middle;
}

.td-name { color: var(--text-primary) !important; font-weight: 600; }

.td-id { color: var(--text-label) !important; font-weight: 600; font-size: 0.85rem; }

.badge {
    display: inline-block;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-active {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.25);
    color: #6ee7b7;
}

.empty-state {
    padding: 3rem;
    text-align: center;
}

.empty-state i {
    font-size: 3rem;
    color: var(--text-muted);
    opacity: 0.4;
    margin-bottom: 1rem;
    display: block;
}

.empty-state h3 { color: var(--text-primary); margin-bottom: 0.4rem; }

.empty-state p { color: var(--text-muted); font-size: 0.875rem; }

.btn-timeout {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.9rem;
    background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: var(--radius-sm);
    color: #fca5a5;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
}

.btn-timeout:hover {
    background: rgba(239,68,68,0.25);
    color: #fff;
}

/* PC Grid Styles */
#pcGrid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-height: 160px;
    overflow-y: auto;
    padding: 10px;
    background: rgba(255,255,255,0.02);
    border-radius: 12px;
    margin-top: 6px;
}

.pc-tile {
    width: 70px;
    text-align: center;
    padding: 6px 0;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s ease;
    cursor: pointer;
    border: 1px solid transparent;
}

.pc-tile.available {
    background: rgba(16,185,129,0.2);
    border-color: rgba(16,185,129,0.4);
    color: #6ee7b7;
}

.pc-tile.available:hover {
    background: rgba(16,185,129,0.4);
    transform: scale(1.02);
}

.pc-tile.reserved {
    background: rgba(239,68,68,0.15);
    border-color: rgba(239,68,68,0.3);
    color: #fca5a5;
    cursor: not-allowed;
}

.pc-tile.selected {
    background: #2563eb;
    color: white;
    border-color: white;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.5);
}

.pc-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 8px;
    font-size: 0.75rem;
    color: #94a3b8;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-circle {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.legend-circle.available {
    background: #10b981;
    box-shadow: 0 0 0 1px rgba(16,185,129,0.5);
}

.legend-circle.reserved {
    background: #ef4444;
}

/* Reward Points Modal */
.reward-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(8,14,26,0.95);
    backdrop-filter: blur(12px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.reward-modal-overlay.open {
    opacity: 1;
    visibility: visible;
}

.reward-modal {
    background: linear-gradient(135deg, #0d1829, #0a1222);
    border: 1px solid rgba(250,204,21,0.3);
    border-radius: var(--radius-lg);
    max-width: 400px;
    width: 90%;
    overflow: hidden;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
}

.reward-modal-overlay.open .reward-modal {
    transform: scale(1);
}

.reward-modal-header {
    background: linear-gradient(135deg, rgba(250,204,21,0.15), rgba(245,158,11,0.1));
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(250,204,21,0.2);
}

.reward-modal-header i {
    font-size: 3rem;
    color: #facc15;
    margin-bottom: 0.5rem;
}

.reward-modal-header h2 {
    color: var(--text-primary);
    margin: 0;
    font-size: 1.5rem;
}

.reward-modal-header p {
    color: var(--text-muted);
    margin: 0.5rem 0 0;
    font-size: 0.85rem;
}

.reward-modal-body {
    padding: 1.5rem;
}

.reward-options {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 0;
}

.reward-option {
    flex: 1;
    text-align: center;
}

.reward-option input {
    display: none;
}

.reward-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(255,255,255,0.05);
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s ease;
}

.reward-option input:checked+label {
    background: rgba(250,204,21,0.15);
    border-color: #facc15;
    transform: scale(1.02);
}

.reward-option label i {
    font-size: 1.8rem;
    color: #facc15;
}

.reward-option label span {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.reward-option label small {
    font-size: 0.7rem;
    color: var(--text-muted);
}

.reward-modal-footer {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.5rem 1.5rem;
}

.btn-cancel-reward {
    flex: 1;
    background: rgba(255,255,255,0.08);
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
    padding: 0.75rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-cancel-reward:hover {
    background: rgba(255,255,255,0.12);
    color: var(--text-primary);
}

.btn-confirm-reward {
    flex: 1;
    background: linear-gradient(135deg, #facc15, #f59e0b);
    border: none;
    color: #1a1a2e;
    padding: 0.75rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-weight: 700;
    transition: all 0.2s;
}

.btn-confirm-reward:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(250,204,21,0.4);
}

/* MODAL */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(8,14,26,0.88);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
}

.modal-overlay.open {
    opacity: 1;
    pointer-events: all;
}

.modal-box {
    background: #0d1829;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: var(--radius-lg);
    padding: 0;
    max-width: 600px;
    width: 92%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
    transform: translateY(20px);
    transition: transform 0.3s ease;
}

.modal-overlay.open .modal-box {
    transform: translateY(0);
}

.modal-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--accent), #7c3aed);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h2 {
    color: var(--text-primary);
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-header h2 i {
    color: var(--accent);
}

.modal-close {
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    color: var(--text-muted);
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.modal-close:hover {
    background: rgba(239,68,68,0.15);
    border-color: rgba(239,68,68,0.3);
    color: #fca5a5;
}

.modal-body {
    padding: 1rem 1.5rem;
    overflow-y: auto;
    flex: 1;
}




.form-group {
    margin-bottom: 0.75rem;
}

.form-label {
    display: block;
    color: var(--text-muted);
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 0.3rem;
}

.form-label i {
    color: var(--accent);
    margin-right: 4px;
}

.form-control {
    width: 100%;
    padding: 0.55rem 0.85rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 0.875rem;
    font-family: inherit;
    transition: var(--transition);
    box-sizing: border-box;
}

/* Make dropdown options visible */
.form-control option {
    background: #0d1829;
    color: #f1f5f9;
}

select.form-control {
    cursor: pointer;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    background: rgba(255,255,255,0.08);
    box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}

.form-control::placeholder {
    color: var(--text-muted);
}

.form-control[readonly],
.form-control[disabled] {
    background: rgba(255,255,255,0.02);
    color: var(--text-muted);
    cursor: not-allowed;
    border-color: rgba(255,255,255,0.05);
}

.form-control-sessions {
    color: #6ee7b7 !important;
    font-weight: 700;
    font-size: 0.95rem !important;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.form-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.75rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border-light);
}

.btn-close-modal {
    padding: 0.6rem 1.5rem;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
}

.btn-close-modal:hover {
    background: rgba(255,255,255,0.10);
    color: var(--text-primary);
}

.btn-sitin-submit {
    padding: 0.6rem 1.75rem;
    background: linear-gradient(135deg, var(--primary), #1d4ed8);
    border: none;
    border-radius: var(--radius-sm);
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-sitin-submit:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37,99,235,0.4);
}

.id-lookup-row {
    display: flex;
    gap: 0.5rem;
}

.id-lookup-row .form-control {
    flex: 1;
}

.btn-lookup {
    padding: 0.7rem 1rem;
    background: rgba(14,165,233,0.15);
    border: 1px solid rgba(14,165,233,0.3);
    border-radius: var(--radius-sm);
    color: var(--text-label);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-lookup:hover {
    background: rgba(14,165,233,0.25);
    color: #fff;
}

.lookup-error {
    font-size: 0.75rem;
    margin-top: 0.4rem;
    color: #fca5a5;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
</style>

<?php if ($success_message): ?>
<div class="alert alert-success" style="max-width:1300px;margin:0 auto 1.5rem;">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-error" style="max-width:1300px;margin:0 auto 1.5rem;">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<div class="sitin-page-container">
    <div class="sitin-main">

        <div class="page-header">
            <h1>Sit-in Management</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-header-left">
                    <i class="fas fa-clock"></i>
                    <h3>Current Sit-in Sessions</h3>
                </div>
                <button class="btn-sitin-open" onclick="openModal()">
                    <i class="fas fa-plus"></i> Sit-in
                </button>
            </div>
            <div class="dash-card-body">
                <div class="results-table-wrapper">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID Number</th>
                                <th>Student Name</th>
                                <th>Purpose</th>
                                <th>Laboratory</th>
                                <th>PC</th>
                                <th>Time In</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_sessions)): ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <i class="fas fa-chair"></i>
                                            <h3>No active sit-in sessions</h3>
                                            <p>Click the <strong>Sit-in</strong> button to start a new session.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_sessions as $i => $row): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td class="td-id"><?php echo htmlspecialchars($row['id_number']); ?></td>
                                        <td class="td-name"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                        <td><?php echo htmlspecialchars($row['laboratory']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['pc_number'] ?? '—'); ?></strong></td>
                                        <td><?php echo date('h:i A', strtotime($row['login_time'])); ?></td>
                                        <td><span class="badge badge-active">Active</span></td>
                                        <td>
                                            <button class="btn-timeout" onclick="openRewardModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                                <i class="fas fa-sign-out-alt"></i> Time Out
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Reward Points Modal -->
<div class="reward-modal-overlay" id="rewardModal">
    <div class="reward-modal">
        <form method="POST" action="">
            <input type="hidden" name="sit_in_id" id="reward_sit_in_id">
            <div class="reward-modal-header">
                <i class="fas fa-star"></i>
                <h2>Reward Points</h2>
                <p>Give 1 reward point for good performance?</p>
            </div>
            <div class="reward-modal-body">
                <div class="reward-options">
                    <div class="reward-option">
                        <input type="radio" name="give_points" id="points_1" value="1">
                        <label for="points_1">
                            <i class="fas fa-star"></i>
                            <span>1</span>
                            <small>Point</small>
                        </label>
                    </div>
                    <div class="reward-option">
                        <input type="radio" name="give_points" id="points_0" value="0" checked>
                        <label for="points_0">
                            <i class="fas fa-star-of-life"></i>
                            <span>0</span>
                            <small>No points</small>
                        </label>
                    </div>
                </div>
            </div>
            <div class="reward-modal-footer">
                <button type="button" class="btn-cancel-reward" onclick="closeRewardModal()">
                    Cancel
                </button>
                <button type="submit" name="timeout_submit" class="btn-confirm-reward">
                    <i class="fas fa-check-circle"></i> Confirm & Time Out
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SIT-IN MODAL -->
<div class="modal-overlay" id="sitinModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2><i class="fas fa-user-clock"></i> Sit-in Form</h2>
            <button class="modal-close" onclick="closeModal()" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="" id="sitinForm" style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
            <div class="modal-body">

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-id-card"></i> ID Number</label>
                    <div class="id-lookup-row">
                        <input type="text" name="id_number" id="id_number" class="form-control" placeholder="Enter student ID number" required>
                        <button type="button" class="btn-lookup" onclick="lookupStudent()">
                            <i class="fas fa-search"></i> Find
                        </button>
                    </div>
                    <div id="lookupError" class="lookup-error" style="display:none;">
                        <i class="fas fa-exclamation-circle"></i> Please enter an ID number.
                    </div>
                </div>

                <input type="hidden" name="found_user_id" id="found_user_id" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Student Name</label>
                        <input type="text" id="student_name" class="form-control" placeholder="Auto-filled after lookup" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-hourglass-half"></i> Sessions Left</label>
                        <input type="text" id="remaining_sessions" class="form-control form-control-sessions" placeholder="Auto-filled" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tasks"></i> Purpose</label>
                        <select name="purpose" id="purpose" class="form-control" required>
                            <option value="">Select Purpose</option>
                            <?php foreach ($purposes as $p): ?>
                                <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-flask"></i> Laboratory</label>
                        <select name="lab" id="labSelect" class="form-control" required>
                            <option value="">Select Laboratory</option>
                            <?php foreach ($laboratories as $l): ?>
                                <option value="<?php echo $l; ?>"><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-desktop"></i> Select PC <span style="color:#f87171;">*</span></label>
                    <div id="pcGrid">
                        <div style="font-size:0.8rem;color:#475569;">Select a laboratory first.</div>
                    </div>
                    <input type="hidden" name="pc_number" id="selectedPC" required>
                    <div class="pc-legend" style="margin-top:6px;">
                        <span class="legend-item"><span class="legend-circle available"></span> Available</span>
                        <span class="legend-item"><span class="legend-circle reserved"></span> Unavailable</span>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="submit" name="sitin_submit" class="btn-sitin-submit">
                    <i class="fas fa-sign-in-alt"></i> Sit In
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const labSelect = document.getElementById('labSelect');
    const pcGrid = document.getElementById('pcGrid');
    const selectedPcInput = document.getElementById('selectedPC');

    function loadPCs() {
        const lab = labSelect.value;
        if (!lab) {
            pcGrid.innerHTML = '<div class="text-muted">Select a laboratory first.</div>';
            selectedPcInput.value = '';
            return;
        }
        pcGrid.innerHTML = '<div class="text-muted">Loading PCs...</div>';
        fetch(`../process/get_pcs.php?lab=${encodeURIComponent(lab)}`)
            .then(res => res.json())
            .then(data => {
                pcGrid.innerHTML = '';
                if (!data.length) {
                    pcGrid.innerHTML = '<div class="text-muted">No PCs found for this lab.</div>';
                    return;
                }
                data.forEach(pc => {
                    const tile = document.createElement('div');
                    let statusClass = pc.status;
                    if (statusClass === 'broken') statusClass = 'reserved';
                    tile.className = `pc-tile ${statusClass}`;
                    tile.textContent = pc.pc_number;
                    if (pc.status === 'available') {
                        tile.addEventListener('click', () => {
                            document.querySelectorAll('#pcGrid .pc-tile').forEach(t => t.classList.remove('selected'));
                            tile.classList.add('selected');
                            selectedPcInput.value = pc.pc_number;
                        });
                    } else {
                        tile.style.cursor = 'not-allowed';
                    }
                    pcGrid.appendChild(tile);
                });
            })
            .catch(err => {
                console.error(err);
                pcGrid.innerHTML = '<div class="text-danger">Error loading PCs.</div>';
            });
    }

    labSelect.addEventListener('change', loadPCs);

    function openModal() {
        document.getElementById('sitinModal').classList.add('open');
        document.body.style.overflow = 'hidden';
        if (labSelect.value) loadPCs();
    }

    function closeModal() {
        document.getElementById('sitinModal').classList.remove('open');
        document.body.style.overflow = '';
        document.getElementById('id_number').value = '';
        document.getElementById('student_name').value = '';
        document.getElementById('remaining_sessions').value = '';
        document.getElementById('found_user_id').value = '';
        document.getElementById('labSelect').value = '';
        document.getElementById('purpose').value = '';
        document.getElementById('pcGrid').innerHTML = '<div class="text-muted">Select a laboratory first.</div>';
        document.getElementById('selectedPC').value = '';
        document.getElementById('student_name').style.color = '';
        const errorDiv = document.getElementById('lookupError');
        if (errorDiv) errorDiv.style.display = 'none';
    }

    let currentSitInId = null;

    function openRewardModal(sitInId, studentName) {
        currentSitInId = sitInId;
        document.getElementById('reward_sit_in_id').value = sitInId;
        document.getElementById('points_0').checked = true;
        document.getElementById('rewardModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeRewardModal() {
        document.getElementById('rewardModal').classList.remove('open');
        document.body.style.overflow = '';
        currentSitInId = null;
    }

    document.getElementById('sitinModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.getElementById('rewardModal').addEventListener('click', function(e) {
        if (e.target === this) closeRewardModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeRewardModal();
        }
    });

    function lookupStudent() {
        const id = document.getElementById('id_number').value.trim();
        const errorDiv = document.getElementById('lookupError');

        if (!id) {
            errorDiv.style.display = 'flex';
            return;
        } else {
            errorDiv.style.display = 'none';
        }

        const btn = document.querySelector('.btn-lookup');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finding...';
        btn.disabled = true;

        fetch(`../process/lookup_student.php?id_number=${encodeURIComponent(id)}`)
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = '<i class="fas fa-search"></i> Find';
                btn.disabled = false;

                if (data.found) {
                    document.getElementById('student_name').value = data.name;
                    document.getElementById('remaining_sessions').value = data.sessions;
                    document.getElementById('found_user_id').value = data.user_id;
                    document.getElementById('student_name').style.color = '#6ee7b7';
                } else {
                    document.getElementById('student_name').value = 'Student not found';
                    document.getElementById('remaining_sessions').value = '';
                    document.getElementById('found_user_id').value = '';
                    document.getElementById('student_name').style.color = '#fca5a5';
                }
            })
            .catch(() => {
                btn.innerHTML = '<i class="fas fa-search"></i> Find';
                btn.disabled = false;
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                errorDiv.style.display = 'flex';
            });
    }

    document.getElementById('id_number').addEventListener('input', function() {
        const errorDiv = document.getElementById('lookupError');
        if (errorDiv) errorDiv.style.display = 'none';
    });

    document.getElementById('id_number').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookupStudent();
        }
    });
</script>

<?php include '../includes/footer.php'; ?><?php
session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Sit-in - CCS Sit-in System";
$basePath  = "../";

$success_message = '';
$error_message   = '';

// ── Handle Sit In form submission ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sitin_submit'])) {
    $id_number = trim($_POST['id_number'] ?? '');
    $purpose   = trim($_POST['purpose']   ?? '');
    $lab       = trim($_POST['lab']       ?? '');
    $pc_number = trim($_POST['pc_number'] ?? '');

    if (empty($id_number) || empty($purpose) || empty($lab) || empty($pc_number)) {
        $error_message = "All fields are required including PC number.";
    } else {
        // Find the student
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? LIMIT 1");
        $stmt->execute([$id_number]);
        $student = $stmt->fetch();

        if (!$student) {
            $error_message = "No student found with ID number: " . htmlspecialchars($id_number);
        } else {
            // Check if student already has an active sit-in session
            $check = $pdo->prepare("SELECT id FROM sit_in WHERE user_id = ? AND status = 'active' LIMIT 1");
            $check->execute([$student['id']]);
            if ($check->fetch()) {
                $error_message = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . " already has an active sit-in session.";
            } elseif (($student['sessions'] ?? 30) <= 0) {
                $error_message = "This student has no remaining sessions.";
            } else {
                // Check if PC is available
                $checkPc = $pdo->prepare("
                    SELECT COUNT(*) FROM sit_in 
                    WHERE laboratory = ? AND pc_number = ? AND status = 'active' AND login_date = CURDATE()
                ");
                $checkPc->execute([$lab, $pc_number]);
                if ($checkPc->fetchColumn() > 0) {
                    $error_message = "PC $pc_number is currently occupied in Lab $lab.";
                } else {
                    // Insert into sit_in table with PC number
                    $insert = $pdo->prepare("
                        INSERT INTO sit_in (user_id, id_number, name, purpose, laboratory, pc_number, login_time, login_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), CURDATE(), 'active')
                    ");
                    $full_name = trim($student['first_name'] . ' ' . $student['last_name']);
                    $insert->execute([
                        $student['id'],
                        $id_number,
                        $full_name,
                        $purpose,
                        $lab,
                        $pc_number
                    ]);

                    // Deduct 1 session from the student
                    $pdo->prepare("UPDATE users SET sessions = sessions - 1 WHERE id = ?")
                        ->execute([$student['id']]);

                    // ── Send notification for MANUAL sit-in (NO reservation) ─────────────────
                    $notifTitle = "Sit-in Session Started";
                    $notifMessage = "You have been checked in for a sit-in session at Lab $lab on PC $pc_number. Purpose: $purpose. Please proceed to your assigned PC.";
                    $addNotif = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'sitin', ?, ?, 'dashboard.php')");
                    $addNotif->execute([$student['id'], $notifTitle, $notifMessage]);

                    $success_message = "Sit-in recorded for " . htmlspecialchars($full_name) . " on PC $pc_number!";
                }
            }
        }
    }
}

// ── Handle Timeout with Reward Points (always notify) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timeout_submit'])) {
    $sit_in_id = (int)$_POST['sit_in_id'];
    $give_points = isset($_POST['give_points']) ? (int)$_POST['give_points'] : 0;
    
    try {
        // Get sit-in record
        $stmt = $pdo->prepare("SELECT * FROM sit_in WHERE id = ? AND status = 'active'");
        $stmt->execute([$sit_in_id]);
        $sit_in = $stmt->fetch();
        
        if ($sit_in) {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update sit_in record
            $update = $pdo->prepare("UPDATE sit_in SET logout_time = NOW(), status = 'completed', reward_points_given = reward_points_given + ? WHERE id = ?");
            $update->execute([$give_points, $sit_in_id]);
            
            // Mark the associated approved reservation as completed
            $updateRes = $pdo->prepare("
                UPDATE reservations 
                SET status = 'completed' 
                WHERE id = ? AND status = 'approved'
            ");
            $updateRes->execute([$sit_in['reservation_id']]);
            
            $sessions_added = 0;
            
            if ($give_points > 0) {
                // Get current reward points
                $pointsStmt = $pdo->prepare("SELECT reward_points FROM users WHERE id = ? FOR UPDATE");
                $pointsStmt->execute([$sit_in['user_id']]);
                $current_points = $pointsStmt->fetchColumn();
                
                $new_points = $current_points + $give_points;
                
                // Check if points reached 3 or more
                if ($new_points >= 3) {
                    $sessions_added = floor($new_points / 3);
                    $new_points = $new_points % 3;
                    
                    // Update sessions
                    $updateSessions = $pdo->prepare("UPDATE users SET sessions = sessions + ? WHERE id = ?");
                    $updateSessions->execute([$sessions_added, $sit_in['user_id']]);
                }
                
                // Update reward points
                $updatePoints = $pdo->prepare("UPDATE users SET reward_points = ? WHERE id = ?");
                $updatePoints->execute([$new_points, $sit_in['user_id']]);
                
                $notifMessage = "Your sit-in session has been completed. You received $give_points reward point(s)! ";
                if ($sessions_added > 0) {
                    $notifMessage .= "🎉 Congratulations! Your reward points reached 3 and have been converted to $sessions_added extra session(s)!";
                } else {
                    $notifMessage .= "You now have $new_points reward point(s). Get 3 points to earn +1 session!";
                }
            } else {
                // No reward points given – still notify completion
                $notifMessage = "Your sit-in session has been completed. No reward points were awarded this time.";
            }
            
            // Always add notification (whether points were given or not)
            $addNotif = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'sitin', 'Sit-in Completed', ?, 'history.php')");
            $addNotif->execute([$sit_in['user_id'], $notifMessage]);
            
            $pdo->commit();
            
            $success_message = "Student timed out successfully!";
            if ($give_points > 0) {
                $success_message .= " Awarded $give_points reward point(s).";
                if ($sessions_added > 0) {
                    $success_message .= " Student gained $sessions_added bonus session(s)!";
                }
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Failed to time out: " . $e->getMessage();
    }
    
    header("Location: admin_sitin.php");
    exit();
}

// ── Fetch active sit-in sessions ──────────────────────────────────────────
$active_sessions = $pdo->query("
    SELECT s.*, u.course, u.year_level
    FROM sit_in s
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'active'
    ORDER BY s.login_time DESC
")->fetchAll();

$laboratories = ['524', '526', '528', '530', '517'];
$purposes     = ['C Programming','Java','PHP','ASP.Net','C#','Python','Research','Thesis','Capstone','Other'];
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
:root {
    --primary: #2563eb;
    --primary-light: #3b82f6;
    --accent: #0ea5e9;
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --text-label: #7dd3fc;
    --border-light: rgba(255,255,255,0.10);
    --border-hover: rgba(14,165,233,0.40);
    --shadow-md: 0 8px 32px rgba(0,0,0,0.50);
    --shadow-lg: 0 20px 60px rgba(0,0,0,0.60);
    --radius-lg: 28px;
    --radius-md: 16px;
    --radius-sm: 10px;
    --transition: all 0.25s ease;
    --card-bg: rgba(10,18,40,0.82);
    --card-bg-hover: rgba(14,24,52,0.90);
    --card-border: rgba(255,255,255,0.10);
    --card-border-hover: rgba(14,165,233,0.45);
    --success: #10b981;
    --danger: #ef4444;
}

.sitin-page-container {
    min-height: 100vh;
    padding: 1.5rem 32px 48px 32px;
    position: relative;
}

.sitin-page-container::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 5% 0%, rgba(37,99,235,0.35) 0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%),
        radial-gradient(ellipse at 75% 15%, rgba(124,58,237,0.18) 0%, transparent 38%),
        radial-gradient(ellipse at 25% 85%, rgba(16,185,129,0.12) 0%, transparent 38%);
    pointer-events: none;
    z-index: -1;
}

.sitin-main {
    max-width: 1300px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-light);
}

.page-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

.date-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: rgba(10,18,40,0.70);
    border: 1px solid var(--border-light);
    border-radius: 999px;
    color: var(--text-secondary);
    font-size: 0.875rem;
    backdrop-filter: blur(12px);
}

.date-badge i { color: var(--accent); }

.alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    border-radius: var(--radius-sm);
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
}

.alert-success {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.25);
    color: #6ee7b7;
}

.alert-error {
    background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.25);
    color: #fca5a5;
}

.dash-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-md);
    backdrop-filter: blur(24px);
    overflow: hidden;
    transition: var(--transition);
    margin-bottom: 1.5rem;
}

.dash-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: rgba(37,99,235,0.20);
    border-bottom: 1px solid var(--border-light);
}

.dash-card-header-left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.dash-card-header i { color: var(--accent); }

.dash-card-header h3 {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.dash-card-body { padding: 1.25rem; }

.btn-sitin-open {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    background: linear-gradient(135deg, var(--primary), #1d4ed8);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
}

.btn-sitin-open:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37,99,235,0.4);
}

.results-table-wrapper { overflow-x: auto; }

.results-table {
    width: 100%;
    border-collapse: collapse;
}

.results-table thead tr {
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid var(--border-light);
}

.results-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}

.results-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: var(--transition);
}

.results-table tbody tr:last-child { border-bottom: none; }

.results-table tbody tr:hover { background: rgba(255,255,255,0.04); }

.results-table td {
    padding: 1rem 1.25rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
    vertical-align: middle;
}

.td-name { color: var(--text-primary) !important; font-weight: 600; }

.td-id { color: var(--text-label) !important; font-weight: 600; font-size: 0.85rem; }

.badge {
    display: inline-block;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-active {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.25);
    color: #6ee7b7;
}

.empty-state {
    padding: 3rem;
    text-align: center;
}

.empty-state i {
    font-size: 3rem;
    color: var(--text-muted);
    opacity: 0.4;
    margin-bottom: 1rem;
    display: block;
}

.empty-state h3 { color: var(--text-primary); margin-bottom: 0.4rem; }

.empty-state p { color: var(--text-muted); font-size: 0.875rem; }

.btn-timeout {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.9rem;
    background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: var(--radius-sm);
    color: #fca5a5;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
}

.btn-timeout:hover {
    background: rgba(239,68,68,0.25);
    color: #fff;
}

/* PC Grid Styles */
#pcGrid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-height: 160px;
    overflow-y: auto;
    padding: 10px;
    background: rgba(255,255,255,0.02);
    border-radius: 12px;
    margin-top: 6px;
}

.pc-tile {
    width: 70px;
    text-align: center;
    padding: 6px 0;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s ease;
    cursor: pointer;
    border: 1px solid transparent;
}

.pc-tile.available {
    background: rgba(16,185,129,0.2);
    border-color: rgba(16,185,129,0.4);
    color: #6ee7b7;
}

.pc-tile.available:hover {
    background: rgba(16,185,129,0.4);
    transform: scale(1.02);
}

.pc-tile.reserved {
    background: rgba(239,68,68,0.15);
    border-color: rgba(239,68,68,0.3);
    color: #fca5a5;
    cursor: not-allowed;
}

.pc-tile.selected {
    background: #2563eb;
    color: white;
    border-color: white;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.5);
}

.pc-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 8px;
    font-size: 0.75rem;
    color: #94a3b8;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-circle {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.legend-circle.available {
    background: #10b981;
    box-shadow: 0 0 0 1px rgba(16,185,129,0.5);
}

.legend-circle.reserved {
    background: #ef4444;
}

/* Reward Points Modal */
.reward-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(8,14,26,0.95);
    backdrop-filter: blur(12px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.reward-modal-overlay.open {
    opacity: 1;
    visibility: visible;
}

.reward-modal {
    background: linear-gradient(135deg, #0d1829, #0a1222);
    border: 1px solid rgba(250,204,21,0.3);
    border-radius: var(--radius-lg);
    max-width: 400px;
    width: 90%;
    overflow: hidden;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
}

.reward-modal-overlay.open .reward-modal {
    transform: scale(1);
}

.reward-modal-header {
    background: linear-gradient(135deg, rgba(250,204,21,0.15), rgba(245,158,11,0.1));
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(250,204,21,0.2);
}

.reward-modal-header i {
    font-size: 3rem;
    color: #facc15;
    margin-bottom: 0.5rem;
}

.reward-modal-header h2 {
    color: var(--text-primary);
    margin: 0;
    font-size: 1.5rem;
}

.reward-modal-header p {
    color: var(--text-muted);
    margin: 0.5rem 0 0;
    font-size: 0.85rem;
}

.reward-modal-body {
    padding: 1.5rem;
}

.reward-options {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 0;
}

.reward-option {
    flex: 1;
    text-align: center;
}

.reward-option input {
    display: none;
}

.reward-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(255,255,255,0.05);
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s ease;
}

.reward-option input:checked+label {
    background: rgba(250,204,21,0.15);
    border-color: #facc15;
    transform: scale(1.02);
}

.reward-option label i {
    font-size: 1.8rem;
    color: #facc15;
}

.reward-option label span {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.reward-option label small {
    font-size: 0.7rem;
    color: var(--text-muted);
}

.reward-modal-footer {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.5rem 1.5rem;
}

.btn-cancel-reward {
    flex: 1;
    background: rgba(255,255,255,0.08);
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
    padding: 0.75rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-cancel-reward:hover {
    background: rgba(255,255,255,0.12);
    color: var(--text-primary);
}

.btn-confirm-reward {
    flex: 1;
    background: linear-gradient(135deg, #facc15, #f59e0b);
    border: none;
    color: #1a1a2e;
    padding: 0.75rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-weight: 700;
    transition: all 0.2s;
}

.btn-confirm-reward:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(250,204,21,0.4);
}

/* MODAL */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(8,14,26,0.88);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
}

.modal-overlay.open {
    opacity: 1;
    pointer-events: all;
}

.modal-box {
    background: #0d1829;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: var(--radius-lg);
    padding: 0;
    max-width: 600px;
    width: 92%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
    transform: translateY(20px);
    transition: transform 0.3s ease;
}

.modal-overlay.open .modal-box {
    transform: translateY(0);
}

.modal-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--accent), #7c3aed);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h2 {
    color: var(--text-primary);
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-header h2 i {
    color: var(--accent);
}

.modal-close {
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    color: var(--text-muted);
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.modal-close:hover {
    background: rgba(239,68,68,0.15);
    border-color: rgba(239,68,68,0.3);
    color: #fca5a5;
}

.modal-body {
    padding: 1rem 1.5rem;
    overflow-y: auto;
    flex: 1;
}




.form-group {
    margin-bottom: 0.75rem;
}

.form-label {
    display: block;
    color: var(--text-muted);
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 0.3rem;
}

.form-label i {
    color: var(--accent);
    margin-right: 4px;
}

.form-control {
    width: 100%;
    padding: 0.55rem 0.85rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 0.875rem;
    font-family: inherit;
    transition: var(--transition);
    box-sizing: border-box;
}

/* Make dropdown options visible */
.form-control option {
    background: #0d1829;
    color: #f1f5f9;
}

select.form-control {
    cursor: pointer;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    background: rgba(255,255,255,0.08);
    box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}

.form-control::placeholder {
    color: var(--text-muted);
}

.form-control[readonly],
.form-control[disabled] {
    background: rgba(255,255,255,0.02);
    color: var(--text-muted);
    cursor: not-allowed;
    border-color: rgba(255,255,255,0.05);
}

.form-control-sessions {
    color: #6ee7b7 !important;
    font-weight: 700;
    font-size: 0.95rem !important;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.form-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.75rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border-light);
}

.btn-close-modal {
    padding: 0.6rem 1.5rem;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
}

.btn-close-modal:hover {
    background: rgba(255,255,255,0.10);
    color: var(--text-primary);
}

.btn-sitin-submit {
    padding: 0.6rem 1.75rem;
    background: linear-gradient(135deg, var(--primary), #1d4ed8);
    border: none;
    border-radius: var(--radius-sm);
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-sitin-submit:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37,99,235,0.4);
}

.id-lookup-row {
    display: flex;
    gap: 0.5rem;
}

.id-lookup-row .form-control {
    flex: 1;
}

.btn-lookup {
    padding: 0.7rem 1rem;
    background: rgba(14,165,233,0.15);
    border: 1px solid rgba(14,165,233,0.3);
    border-radius: var(--radius-sm);
    color: var(--text-label);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-lookup:hover {
    background: rgba(14,165,233,0.25);
    color: #fff;
}

.lookup-error {
    font-size: 0.75rem;
    margin-top: 0.4rem;
    color: #fca5a5;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
</style>

<?php if ($success_message): ?>
<div class="alert alert-success" style="max-width:1300px;margin:0 auto 1.5rem;">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-error" style="max-width:1300px;margin:0 auto 1.5rem;">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<div class="sitin-page-container">
    <div class="sitin-main">

        <div class="page-header">
            <h1>Sit-in Management</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-header-left">
                    <i class="fas fa-clock"></i>
                    <h3>Current Sit-in Sessions</h3>
                </div>
                <button class="btn-sitin-open" onclick="openModal()">
                    <i class="fas fa-plus"></i> Sit-in
                </button>
            </div>
            <div class="dash-card-body">
                <div class="results-table-wrapper">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID Number</th>
                                <th>Student Name</th>
                                <th>Purpose</th>
                                <th>Laboratory</th>
                                <th>PC</th>
                                <th>Time In</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_sessions)): ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <i class="fas fa-chair"></i>
                                            <h3>No active sit-in sessions</h3>
                                            <p>Click the <strong>Sit-in</strong> button to start a new session.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_sessions as $i => $row): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td class="td-id"><?php echo htmlspecialchars($row['id_number']); ?></td>
                                        <td class="td-name"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                        <td><?php echo htmlspecialchars($row['laboratory']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['pc_number'] ?? '—'); ?></strong></td>
                                        <td><?php echo date('h:i A', strtotime($row['login_time'])); ?></td>
                                        <td><span class="badge badge-active">Active</span></td>
                                        <td>
                                            <button class="btn-timeout" onclick="openRewardModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                                <i class="fas fa-sign-out-alt"></i> Time Out
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Reward Points Modal -->
<div class="reward-modal-overlay" id="rewardModal">
    <div class="reward-modal">
        <form method="POST" action="">
            <input type="hidden" name="sit_in_id" id="reward_sit_in_id">
            <div class="reward-modal-header">
                <i class="fas fa-star"></i>
                <h2>Reward Points</h2>
                <p>Give 1 reward point for good performance?</p>
            </div>
            <div class="reward-modal-body">
                <div class="reward-options">
                    <div class="reward-option">
                        <input type="radio" name="give_points" id="points_1" value="1">
                        <label for="points_1">
                            <i class="fas fa-star"></i>
                            <span>1</span>
                            <small>Point</small>
                        </label>
                    </div>
                    <div class="reward-option">
                        <input type="radio" name="give_points" id="points_0" value="0" checked>
                        <label for="points_0">
                            <i class="fas fa-star-of-life"></i>
                            <span>0</span>
                            <small>No points</small>
                        </label>
                    </div>
                </div>
            </div>
            <div class="reward-modal-footer">
                <button type="button" class="btn-cancel-reward" onclick="closeRewardModal()">
                    Cancel
                </button>
                <button type="submit" name="timeout_submit" class="btn-confirm-reward">
                    <i class="fas fa-check-circle"></i> Confirm & Time Out
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SIT-IN MODAL -->
<div class="modal-overlay" id="sitinModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2><i class="fas fa-user-clock"></i> Sit-in Form</h2>
            <button class="modal-close" onclick="closeModal()" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="" id="sitinForm" style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
            <div class="modal-body">

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-id-card"></i> ID Number</label>
                    <div class="id-lookup-row">
                        <input type="text" name="id_number" id="id_number" class="form-control" placeholder="Enter student ID number" required>
                        <button type="button" class="btn-lookup" onclick="lookupStudent()">
                            <i class="fas fa-search"></i> Find
                        </button>
                    </div>
                    <div id="lookupError" class="lookup-error" style="display:none;">
                        <i class="fas fa-exclamation-circle"></i> Please enter an ID number.
                    </div>
                </div>

                <input type="hidden" name="found_user_id" id="found_user_id" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Student Name</label>
                        <input type="text" id="student_name" class="form-control" placeholder="Auto-filled after lookup" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-hourglass-half"></i> Sessions Left</label>
                        <input type="text" id="remaining_sessions" class="form-control form-control-sessions" placeholder="Auto-filled" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tasks"></i> Purpose</label>
                        <select name="purpose" id="purpose" class="form-control" required>
                            <option value="">Select Purpose</option>
                            <?php foreach ($purposes as $p): ?>
                                <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-flask"></i> Laboratory</label>
                        <select name="lab" id="labSelect" class="form-control" required>
                            <option value="">Select Laboratory</option>
                            <?php foreach ($laboratories as $l): ?>
                                <option value="<?php echo $l; ?>"><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-desktop"></i> Select PC <span style="color:#f87171;">*</span></label>
                    <div id="pcGrid">
                        <div style="font-size:0.8rem;color:#475569;">Select a laboratory first.</div>
                    </div>
                    <input type="hidden" name="pc_number" id="selectedPC" required>
                    <div class="pc-legend" style="margin-top:6px;">
                        <span class="legend-item"><span class="legend-circle available"></span> Available</span>
                        <span class="legend-item"><span class="legend-circle reserved"></span> Unavailable</span>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="submit" name="sitin_submit" class="btn-sitin-submit">
                    <i class="fas fa-sign-in-alt"></i> Sit In
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const labSelect = document.getElementById('labSelect');
    const pcGrid = document.getElementById('pcGrid');
    const selectedPcInput = document.getElementById('selectedPC');

    function loadPCs() {
        const lab = labSelect.value;
        if (!lab) {
            pcGrid.innerHTML = '<div class="text-muted">Select a laboratory first.</div>';
            selectedPcInput.value = '';
            return;
        }
        pcGrid.innerHTML = '<div class="text-muted">Loading PCs...</div>';
        fetch(`../process/get_pcs.php?lab=${encodeURIComponent(lab)}`)
            .then(res => res.json())
            .then(data => {
                pcGrid.innerHTML = '';
                if (!data.length) {
                    pcGrid.innerHTML = '<div class="text-muted">No PCs found for this lab.</div>';
                    return;
                }
                data.forEach(pc => {
                    const tile = document.createElement('div');
                    let statusClass = pc.status;
                    if (statusClass === 'broken') statusClass = 'reserved';
                    tile.className = `pc-tile ${statusClass}`;
                    tile.textContent = pc.pc_number;
                    if (pc.status === 'available') {
                        tile.addEventListener('click', () => {
                            document.querySelectorAll('#pcGrid .pc-tile').forEach(t => t.classList.remove('selected'));
                            tile.classList.add('selected');
                            selectedPcInput.value = pc.pc_number;
                        });
                    } else {
                        tile.style.cursor = 'not-allowed';
                    }
                    pcGrid.appendChild(tile);
                });
            })
            .catch(err => {
                console.error(err);
                pcGrid.innerHTML = '<div class="text-danger">Error loading PCs.</div>';
            });
    }

    labSelect.addEventListener('change', loadPCs);

    function openModal() {
        document.getElementById('sitinModal').classList.add('open');
        document.body.style.overflow = 'hidden';
        if (labSelect.value) loadPCs();
    }

    function closeModal() {
        document.getElementById('sitinModal').classList.remove('open');
        document.body.style.overflow = '';
        document.getElementById('id_number').value = '';
        document.getElementById('student_name').value = '';
        document.getElementById('remaining_sessions').value = '';
        document.getElementById('found_user_id').value = '';
        document.getElementById('labSelect').value = '';
        document.getElementById('purpose').value = '';
        document.getElementById('pcGrid').innerHTML = '<div class="text-muted">Select a laboratory first.</div>';
        document.getElementById('selectedPC').value = '';
        document.getElementById('student_name').style.color = '';
        const errorDiv = document.getElementById('lookupError');
        if (errorDiv) errorDiv.style.display = 'none';
    }

    let currentSitInId = null;

    function openRewardModal(sitInId, studentName) {
        currentSitInId = sitInId;
        document.getElementById('reward_sit_in_id').value = sitInId;
        document.getElementById('points_0').checked = true;
        document.getElementById('rewardModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeRewardModal() {
        document.getElementById('rewardModal').classList.remove('open');
        document.body.style.overflow = '';
        currentSitInId = null;
    }

    document.getElementById('sitinModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.getElementById('rewardModal').addEventListener('click', function(e) {
        if (e.target === this) closeRewardModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeRewardModal();
        }
    });

    function lookupStudent() {
        const id = document.getElementById('id_number').value.trim();
        const errorDiv = document.getElementById('lookupError');

        if (!id) {
            errorDiv.style.display = 'flex';
            return;
        } else {
            errorDiv.style.display = 'none';
        }

        const btn = document.querySelector('.btn-lookup');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finding...';
        btn.disabled = true;

        fetch(`../process/lookup_student.php?id_number=${encodeURIComponent(id)}`)
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = '<i class="fas fa-search"></i> Find';
                btn.disabled = false;

                if (data.found) {
                    document.getElementById('student_name').value = data.name;
                    document.getElementById('remaining_sessions').value = data.sessions;
                    document.getElementById('found_user_id').value = data.user_id;
                    document.getElementById('student_name').style.color = '#6ee7b7';
                } else {
                    document.getElementById('student_name').value = 'Student not found';
                    document.getElementById('remaining_sessions').value = '';
                    document.getElementById('found_user_id').value = '';
                    document.getElementById('student_name').style.color = '#fca5a5';
                }
            })
            .catch(() => {
                btn.innerHTML = '<i class="fas fa-search"></i> Find';
                btn.disabled = false;
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                errorDiv.style.display = 'flex';
            });
    }

    document.getElementById('id_number').addEventListener('input', function() {
        const errorDiv = document.getElementById('lookupError');
        if (errorDiv) errorDiv.style.display = 'none';
    });

    document.getElementById('id_number').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookupStudent();
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
