<?php
session_start();

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please login first"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$is_admin   = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['user_name'];
$id_number  = $_SESSION['id_number'];

$pageTitle  = "Sit-in History - CCS Sit-in System";
$extraCSS   = "dashboard";
$basePath   = "../";

// ── Handle Feedback Submission ─────────────────────────────────────────────
$feedback_success = '';
$feedback_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating = (int)($_POST['rating'] ?? 5);
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        $feedback_error = "Please enter your feedback message.";
    } else {
        // Insert feedback
        $stmt = $pdo->prepare("
            INSERT INTO feedback (user_id, message, rating) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $message, $rating]);
        
        // Auto notification for student
        $notifMessage = "Thank you for your feedback! Our admin will review it shortly.";
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'feedback', 'Feedback Received', ?, 'history.php')");
        $notifStmt->execute([$user_id, $notifMessage]);
        
        $feedback_success = "Thank you for your feedback! We appreciate your input.";
    }
}

// ── Fetch overall stats for the current user ──────────────────────────────
$statsQuery = $pdo->prepare("
    SELECT
        u.reward_points                                AS current_points,
        u.sessions                                     AS remaining_sessions,
        COALESCE(SUM(s.reward_points_given), 0)        AS total_points_earned,
        COUNT(s.id)                                    AS total_completed,
        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.login_time, s.logout_time)), 0) AS total_minutes
    FROM users u
    LEFT JOIN sit_in s ON s.user_id = u.id AND s.status = 'completed'
    WHERE u.id = ?
    GROUP BY u.id
");
$statsQuery->execute([$user_id]);
$userStats = $statsQuery->fetch();

$points_to_next = 3 - ($userStats['current_points'] % 3);
if ($points_to_next == 3 && $userStats['current_points'] > 0) $points_to_next = 0;
$total_hrs  = floor($userStats['total_minutes'] / 60);
$total_mins = $userStats['total_minutes'] % 60;

// ── Pagination & search parameters ────────────────────────────────────────
$entriesRaw = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$entries    = in_array($entriesRaw, [10, 25, 50, 100]) ? $entriesRaw : 10;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $entries;
$search     = trim($_GET['search'] ?? '');

// ── Build WHERE clause ────────────────────────────────────────────────────
// Admin sees ALL records; student sees only their own
$params = [];
$where  = [];

if (!$is_admin) {
    $where[]  = "s.user_id = ?";
    $params[] = $user_id;
}

// Only show completed sit-ins in history
$where[] = "s.status = 'completed'";

if ($search !== '') {
    $where[]  = "(s.purpose LIKE ? OR s.laboratory LIKE ? OR s.name LIKE ? OR s.id_number LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Count total rows ───────────────────────────────────────────────────────
$countSql  = "SELECT COUNT(*) FROM sit_in s $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $entries));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $entries;

// ── Fetch rows with reward_points_given ────────────────────────────────────
$dataSql  = "
    SELECT s.id, s.id_number, s.name, s.purpose, s.laboratory,
           s.login_time, s.logout_time, s.login_date, s.reward_points_given,
           u.course, u.year_level
    FROM sit_in s
    JOIN users u ON s.user_id = u.id
    $whereSql
    ORDER BY s.login_date DESC, s.login_time DESC
    LIMIT ? OFFSET ?
";
$dataStmt = $pdo->prepare($dataSql);
$paramIndex = 1;
foreach ($params as $val) {
    $dataStmt->bindValue($paramIndex++, $val);
}
$dataStmt->bindValue($paramIndex++, (int)$entries, PDO::PARAM_INT);
$dataStmt->bindValue($paramIndex++, (int)$offset,  PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll();

$showingFrom = $totalRows === 0 ? 0 : $offset + 1;
$showingTo   = min($offset + $entries, $totalRows);
?>
<?php include '../includes/header.php'; ?>
<?php include ($is_admin ? '../includes/admin_navigation.php' : '../includes/user_navigation.php'); ?>

<style>
/* ── Points Summary Strip ───────────────────────────────────────────── */
.points-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.points-chip {
    background: rgba(10,18,40,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 14px;
    backdrop-filter: blur(24px);
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.2s;
}
.points-chip:hover {
    background: rgba(14,24,52,0.90);
    border-color: rgba(14,165,233,0.35);
    transform: translateY(-2px);
}
.points-chip-progress {
    grid-column: span 2;
}
.pc-icon {
    width: 42px; height: 42px;
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.pc-val {
    font-size: 1.5rem; font-weight: 800;
    color: #f1f5f9; line-height: 1;
}
.pc-unit { font-size: 0.8rem; font-weight: 600; color: #94a3b8; }
.pc-lbl  { font-size: 0.72rem; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.04em; }

.progress-track {
    height: 8px;
    background: rgba(255,255,255,0.08);
    border-radius: 999px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #facc15, #f59e0b);
    border-radius: 999px;
    transition: width 0.6s ease;
}

@media (max-width: 900px) {
    .points-chip-progress { grid-column: span 1; }
}

/* ── Page layout ────────────────────────────────────────────────────── */
.history-page {
    min-height: 100vh;
    padding: 1.5rem 24px 48px;
    position: relative;
}

.history-page::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 5%   0%,  rgba(37,99,235,0.35)  0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%),
        radial-gradient(ellipse at 75%  15%, rgba(124,58,237,0.18) 0%, transparent 38%),
        radial-gradient(ellipse at 25%  85%, rgba(16,185,129,0.12) 0%, transparent 38%);
    pointer-events: none;
    z-index: -1;
}

.history-main {
    max-width: 1400px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

/* ── Page header ────────────────────────────────────────────────────── */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.10);
}

.page-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #f1f5f9;
    letter-spacing: -0.02em;
}

.date-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: rgba(10,18,40,0.70);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 999px;
    color: #cbd5e1;
    font-size: 0.875rem;
    backdrop-filter: blur(12px);
}
.date-badge i { color: #0ea5e9; }

/* ── Alert Messages ──────────────────────────────────────────────────── */
.feedback-alert {
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
}
.feedback-alert.success {
    background: rgba(16,185,129,0.1);
    color: #6ee7b7;
    border: 1px solid rgba(16,185,129,0.2);
}
.feedback-alert.error {
    background: rgba(239,68,68,0.1);
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.2);
}

/* ── Card ───────────────────────────────────────────────────────────── */
.history-card {
    background: rgba(10,18,40,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 16px;
    backdrop-filter: blur(24px);
    overflow: hidden;
}

/* ── Controls bar ───────────────────────────────────────────────────── */
.controls-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-wrap: wrap;
    gap: 14px;
}

.entries-control {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #94a3b8;
    font-size: 0.875rem;
}

.entries-select {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    color: #f1f5f9;
    padding: 7px 12px;
    border-radius: 10px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: border-color 0.2s;
}
.entries-select:focus {
    outline: none;
    border-color: #0ea5e9;
}

.search-form {
    display: flex;
    gap: 8px;
}

.search-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    color: #f1f5f9;
    padding: 9px 15px;
    border-radius: 10px;
    width: 260px;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.search-input::placeholder { color: #64748b; }
.search-input:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}

.btn-search {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: #fff;
    border: none;
    padding: 9px 20px;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 7px;
    transition: opacity 0.2s, transform 0.2s;
    font-family: inherit;
}
.btn-search:hover { opacity: 0.88; transform: translateY(-1px); }

/* ── Table - Optimized to fit all columns ───────────────────────────── */
.table-wrapper {
    overflow-x: auto;
}

.history-table {
    width: 100%;
    min-width: 1200px;
    border-collapse: collapse;
}

.history-table thead tr {
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.history-table th {
    padding: 10px 8px;
    text-align: left;
    color: #64748b;
    font-weight: 600;
    font-size: 0.7rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}

.history-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.18s;
}
.history-table tbody tr:last-child { border-bottom: none; }
.history-table tbody tr:hover { background: rgba(255,255,255,0.04); }

.history-table td {
    padding: 10px 8px;
    color: #cbd5e1;
    font-size: 0.8rem;
    vertical-align: middle;
    white-space: nowrap;
}

.td-id   { color: #7dd3fc !important; font-weight: 700; font-size: 0.78rem; }
.td-name { color: #f1f5f9 !important; font-weight: 600; }

/* Column Widths */
.history-table th:nth-child(1), .history-table td:nth-child(1) { width: 40px; text-align: center; }
.history-table th:nth-child(2), .history-table td:nth-child(2) { width: 90px; }
.history-table th:nth-child(3), .history-table td:nth-child(3) { width: 140px; }
.history-table th:nth-child(4), .history-table td:nth-child(4) { width: 100px; }
.history-table th:nth-child(5), .history-table td:nth-child(5) { width: 100px; }
.history-table th:nth-child(6), .history-table td:nth-child(6) { width: 70px; }
.history-table th:nth-child(7), .history-table td:nth-child(7) { width: 85px; }
.history-table th:nth-child(8), .history-table td:nth-child(8) { width: 80px; }
.history-table th:nth-child(9), .history-table td:nth-child(9) { width: 80px; }
.history-table th:nth-child(10), .history-table td:nth-child(10) { width: 70px; }
.history-table th:nth-child(11), .history-table td:nth-child(11) { width: 80px; }
.history-table th:nth-child(12), .history-table td:nth-child(12) { width: 80px; }
.history-table th:nth-child(13), .history-table td:nth-child(13) { width: 95px; }

/* Reward Points Badge */
.reward-points {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: rgba(250,204,21,0.12);
    border: 1px solid rgba(250,204,21,0.25);
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #fde68a;
}
.reward-points i {
    font-size: 0.65rem;
    color: #facc15;
}

/* ── Badges ─────────────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.03em;
}
.badge-completed {
    background: rgba(14,165,233,0.12);
    border: 1px solid rgba(14,165,233,0.25);
    color: #7dd3fc;
}
.badge-active {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.25);
    color: #6ee7b7;
}

/* ── Duration pill ──────────────────────────────────────────────────── */
.duration-pill {
    display: inline-block;
    background: rgba(124,58,237,0.15);
    border: 1px solid rgba(124,58,237,0.25);
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 0.68rem;
    color: #c4b5fd;
    font-weight: 600;
}

/* ── Empty state ────────────────────────────────────────────────────── */
.empty-state {
    padding: 60px 20px;
    text-align: center;
}
.empty-state i {
    font-size: 3rem;
    color: #475569;
    display: block;
    margin-bottom: 16px;
}
.empty-state h3 { color: #f1f5f9; margin-bottom: 6px; }
.empty-state p  { color: #64748b; font-size: 0.875rem; }

/* ── Footer (pagination) ────────────────────────────────────────────── */
.table-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-top: 1px solid rgba(255,255,255,0.06);
    flex-wrap: wrap;
    gap: 12px;
}

.showing-text { color: #64748b; font-size: 0.875rem; }
.showing-text strong { color: #94a3b8; }

.pagination {
    display: flex;
    gap: 4px;
    align-items: center;
}

.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 10px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    color: #64748b;
    font-size: 0.8rem;
    text-decoration: none;
    transition: all 0.18s;
    cursor: pointer;
    font-family: inherit;
    font-weight: 500;
    white-space: nowrap;
}
.page-btn:hover:not(.disabled):not(.active) {
    background: rgba(255,255,255,0.08);
    color: #cbd5e1;
    border-color: rgba(255,255,255,0.14);
}
.page-btn.active {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: #fff;
    border-color: transparent;
    cursor: default;
}
.page-btn.disabled {
    opacity: 0.35;
    cursor: not-allowed;
    pointer-events: none;
}

/* ── Feedback Button ────────────────────────────────────────────────── */
.btn-feedback {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.7rem;
    background: rgba(96,165,250,0.15);
    border: 1px solid rgba(96,165,250,0.25);
    border-radius: 999px;
    color: #60a5fa;
    font-size: 0.65rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-feedback:hover {
    background: rgba(96,165,250,0.25);
    color: white;
}

/* ── Feedback Modal ─────────────────────────────────────────────────── */
.modal-overlay {
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

.modal-overlay.open {
    opacity: 1;
    visibility: visible;
}

.feedback-modal {
    background: linear-gradient(135deg, #0d1829, #0a1222);
    border: 1px solid rgba(96,165,250,0.3);
    border-radius: 20px;
    max-width: 500px;
    width: 90%;
    overflow: hidden;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
}

.modal-overlay.open .feedback-modal {
    transform: scale(1);
}

.feedback-modal-header {
    background: linear-gradient(135deg, rgba(96,165,250,0.15), rgba(14,165,233,0.1));
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(96,165,250,0.2);
}

.feedback-modal-header i {
    font-size: 2.5rem;
    color: #60a5fa;
    margin-bottom: 0.5rem;
}

.feedback-modal-header h2 {
    color: #f1f5f9;
    margin: 0;
    font-size: 1.3rem;
}

.feedback-modal-header p {
    color: #94a3b8;
    font-size: 0.8rem;
    margin-top: 8px;
}

.feedback-modal-body {
    padding: 1.5rem;
}

.rating-group {
    display: flex;
    gap: 12px;
    flex-direction: row-reverse;
    justify-content: center;
    margin-bottom: 20px;
}

.rating-group input {
    display: none;
}

.rating-group label {
    font-size: 2rem;
    color: #475569;
    cursor: pointer;
    transition: color 0.2s;
    margin: 0;
    padding: 0;
}

.rating-group input:checked ~ label,
.rating-group label:hover,
.rating-group label:hover ~ label {
    color: #facc15;
}

.feedback-modal-footer {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.5rem 1.5rem;
}

.btn-cancel-feedback {
    flex: 1;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.1);
    color: #94a3b8;
    padding: 0.75rem;
    border-radius: 999px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-cancel-feedback:hover {
    background: rgba(255,255,255,0.12);
    color: #f1f5f9;
}

.btn-submit-feedback {
    flex: 1;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border: none;
    color: white;
    padding: 0.75rem;
    border-radius: 999px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-submit-feedback:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(37,99,235,0.4);
}

/* Responsive */
@media (max-width: 1200px) {
    .history-main {
        padding: 0 10px;
    }
}
</style>

<div class="history-page">
    <div class="history-main">

        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <?php if ($is_admin): ?>
                    <i class="fas fa-history" style="color:#0ea5e9;margin-right:8px;"></i>Sit-in History
                <?php else: ?>
                    Sit-in History
                <?php endif; ?>
            </h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- Feedback Alert Messages -->
        <?php if ($feedback_success): ?>
            <div class="feedback-alert success">
                <i class="fas fa-check-circle"></i> <?php echo $feedback_success; ?>
            </div>
        <?php endif; ?>
        <?php if ($feedback_error): ?>
            <div class="feedback-alert error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $feedback_error; ?>
            </div>
        <?php endif; ?>

        <!-- Overall Points Summary -->
        <?php if (!$is_admin): ?>
        <div class="points-strip">
            <div class="points-chip">
                <div class="pc-icon" style="background:rgba(250,204,21,0.15);color:#facc15;">
                    <i class="fas fa-star"></i>
                </div>
                <div>
                    <div class="pc-val"><?php echo (int)$userStats['current_points']; ?> <span class="pc-unit">pts</span></div>
                    <div class="pc-lbl">Current Balance</div>
                </div>
            </div>
            <div class="points-chip">
                <div class="pc-icon" style="background:rgba(16,185,129,0.15);color:#10b981;">
                    <i class="fas fa-trophy"></i>
                </div>
                <div>
                    <div class="pc-val"><?php echo (int)$userStats['total_points_earned']; ?> <span class="pc-unit">pts</span></div>
                    <div class="pc-lbl">Total Points Earned</div>
                </div>
            </div>
            <div class="points-chip">
                <div class="pc-icon" style="background:rgba(14,165,233,0.15);color:#0ea5e9;">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="pc-val"><?php echo $total_hrs; ?>h <span class="pc-unit"><?php echo $total_mins; ?>m</span></div>
                    <div class="pc-lbl">Total Lab Time</div>
                </div>
            </div>
            <div class="points-chip">
                <div class="pc-icon" style="background:rgba(124,58,237,0.15);color:#a78bfa;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="pc-val"><?php echo (int)$userStats['total_completed']; ?></div>
                    <div class="pc-lbl">Sessions Completed</div>
                </div>
            </div>
            <div class="points-chip points-chip-progress">
                <div class="pc-icon" style="background:rgba(239,68,68,0.15);color:#f87171;">
                    <i class="fas fa-bolt"></i>
                </div>
                <div style="flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <div class="pc-lbl">Next Session Reward</div>
                        <div style="font-size:0.72rem;color:#facc15;font-weight:700;">
                            <?php echo $points_to_next > 0 ? $points_to_next . ' pt' . ($points_to_next > 1 ? 's' : '') . ' to go' : '🎉 Claim ready!'; ?>
                        </div>
                    </div>
                    <?php $pct = min(100, round(($userStats['current_points'] % 3) / 3 * 100)); ?>
                    <div class="progress-track">
                        <div class="progress-fill" style="width:<?php echo $pct; ?>%;"></div>
                    </div>
                    <div style="font-size:0.7rem;color:#64748b;margin-top:4px;">
                        <?php echo (int)$userStats['current_points'] % 3; ?> / 3 points — every 3 pts = +1 session
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card -->
        <div class="history-card">

            <!-- Controls -->
            <div class="controls-bar">
                <div class="entries-control">
                    <span>Show</span>
                    <select class="entries-select" onchange="changeEntries(this.value)">
                        <?php foreach ([10,25,50,100] as $opt): ?>
                        <option value="<?php echo $opt; ?>"
                            <?php echo $entries === $opt ? 'selected' : ''; ?>
                            style="background:#1e293b;">
                            <?php echo $opt; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span>entries</span>
                </div>

                <form method="GET" action="" class="search-form">
                    <input type="hidden" name="entries" value="<?php echo $entries; ?>">
                    <input type="hidden" name="page" value="1">
                    <input type="text"
                           name="search"
                           class="search-input"
                           placeholder="Search by name, purpose, lab..."
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <!-- Table -->
            <div class="table-wrapper">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID Number</th>
                            <th>Name</th>
                            <?php if ($is_admin): ?>
                            <th>Course / Year</th>
                            <?php endif; ?>
                            <th>Purpose</th>
                            <th>Laboratory</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Duration</th>
                            <th>Reward Points</th>
                            <th>Status</th>
                            <?php if (!$is_admin): ?>
                            <th>Feedback</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?php echo $is_admin ? 13 : 13; ?>">
                                <div class="empty-state">
                                    <i class="fas fa-database"></i>
                                    <h3>No data available</h3>
                                    <p>
                                        <?php if ($search): ?>
                                            No records match your search. <a href="history.php" style="color:#0ea5e9;">Clear search</a>
                                        <?php else: ?>
                                            No completed sit-in sessions found.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $i => $row):
                                // Calculate duration
                                $duration = '';
                                if ($row['login_time'] && $row['logout_time']) {
                                    $in  = new DateTime($row['login_time']);
                                    $out = new DateTime($row['logout_time']);
                                    $diff = $in->diff($out);
                                    if ($diff->h > 0) {
                                        $duration = $diff->h . 'h ' . $diff->i . 'm';
                                    } else {
                                        $duration = $diff->i . 'm';
                                    }
                                }
                                // Get reward points from database
                                $rewardPoints = isset($row['reward_points_given']) ? $row['reward_points_given'] : 1;
                            ?>
                            <tr>
                                <td style="color:#475569; text-align:center;"><?php echo $offset + $i + 1; ?></td>
                                <td class="td-id"><?php echo htmlspecialchars($row['id_number']); ?></td>
                                <td class="td-name"><?php echo htmlspecialchars($row['name']); ?></td>
                                <?php if ($is_admin): ?>
                                <td style="color:#94a3b8;font-size:0.78rem;">
                                    <?php echo htmlspecialchars($row['course'] ?? '—'); ?>
                                    <?php if (!empty($row['year_level'])): ?>
                                    <span style="color:#475569;"> &bull; <?php echo htmlspecialchars($row['year_level']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                <td><?php echo htmlspecialchars($row['laboratory']); ?></td>
                                <td style="color:#94a3b8; font-size:0.75rem;">
                                    <?php echo $row['login_date']
                                        ? date('M j, Y', strtotime($row['login_date']))
                                        : '—'; ?>
                                </td>
                                <td>
                                    <?php echo $row['login_time']
                                        ? date('h:i A', strtotime($row['login_time']))
                                        : '—'; ?>
                                </td>
                                <td>
                                    <?php echo $row['logout_time']
                                        ? date('h:i A', strtotime($row['logout_time']))
                                        : '<span style="color:#475569;">—</span>'; ?>
                                </td>
                                <td>
                                    <?php if ($duration): ?>
                                        <span class="duration-pill"><?php echo $duration; ?></span>
                                    <?php else: ?>
                                        <span style="color:#475569;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="reward-points">
                                        <i class="fas fa-star"></i>
                                        +<?php echo $rewardPoints; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status = $row['status'] ?? 'completed';
                                    $cls    = $status === 'active' ? 'badge-active' : 'badge-completed';
                                    echo '<span class="badge ' . $cls . '">' . ucfirst(htmlspecialchars($status)) . '</span>';
                                    ?>
                                </td>
                                <?php if (!$is_admin): ?>
                                <td>
                                    <button class="btn-feedback" onclick="openFeedbackModal()">
                                        <i class="fas fa-comment-dots"></i> Feedback
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer: showing text + pagination -->
            <div class="table-footer">
                <div class="showing-text">
                    Showing <strong><?php echo $showingFrom; ?></strong>
                    to <strong><?php echo $showingTo; ?></strong>
                    of <strong><?php echo $totalRows; ?></strong> entries
                    <?php if ($search): ?>
                        <span style="color:#475569;"> &mdash; filtered by "<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $baseUrl = '?entries=' . $entries . '&search=' . urlencode($search) . '&page=';
                    ?>

                    <!-- First -->
                    <a href="<?php echo $baseUrl . 1; ?>"
                       class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>

                    <!-- Prev -->
                    <a href="<?php echo $baseUrl . max(1, $page - 1); ?>"
                       class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-left"></i> Prev
                    </a>

                    <?php
                    // Show up to 5 page buttons centred around current page
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $start + 4);
                    $start = max(1, $end - 4);

                    for ($p = $start; $p <= $end; $p++):
                    ?>
                    <a href="<?php echo $baseUrl . $p; ?>"
                       class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                        <?php echo $p; ?>
                    </a>
                    <?php endfor; ?>

                    <!-- Next -->
                    <a href="<?php echo $baseUrl . min($totalPages, $page + 1); ?>"
                       class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        Next <i class="fas fa-angle-right"></i>
                    </a>

                    <!-- Last -->
                    <a href="<?php echo $baseUrl . $totalPages; ?>"
                       class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.history-card -->
    </div><!-- /.history-main -->
</div><!-- /.history-page -->

<!-- Feedback Modal -->
<div class="modal-overlay" id="feedbackModal">
    <div class="feedback-modal">
        <form method="POST" action="">
            <div class="feedback-modal-header">
                <i class="fas fa-comment-dots"></i>
                <h2>Share Your Feedback</h2>
                <p>How was your lab experience?</p>
            </div>
            <div class="feedback-modal-body">
                <div class="rating-group">
                    <input type="radio" name="rating" id="modal_star5" value="5" checked>
                    <label for="modal_star5"><i class="fas fa-star"></i></label>
                    
                    <input type="radio" name="rating" id="modal_star4" value="4">
                    <label for="modal_star4"><i class="fas fa-star"></i></label>
                    
                    <input type="radio" name="rating" id="modal_star3" value="3">
                    <label for="modal_star3"><i class="fas fa-star"></i></label>
                    
                    <input type="radio" name="rating" id="modal_star2" value="2">
                    <label for="modal_star2"><i class="fas fa-star"></i></label>
                    
                    <input type="radio" name="rating" id="modal_star1" value="1">
                    <label for="modal_star1"><i class="fas fa-star"></i></label>
                </div>
                
                <textarea name="message" class="form-control" rows="4" placeholder="Tell us about your experience..." style="width:100%; padding:12px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:12px; color:#f1f5f9; resize:vertical;" required></textarea>
            </div>
            <div class="feedback-modal-footer">
                <button type="button" class="btn-cancel-feedback" onclick="closeFeedbackModal()">Cancel</button>
                <button type="submit" name="submit_feedback" class="btn-submit-feedback">
                    <i class="fas fa-paper-plane"></i> Submit
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function changeEntries(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('entries', value);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}

function openFeedbackModal() {
    // Reset form
    document.getElementById('modal_star5').checked = true;
    document.querySelector('#feedbackModal textarea').value = '';
    
    document.getElementById('feedbackModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').classList.remove('open');
    document.body.style.overflow = '';
}

document.getElementById('feedbackModal').addEventListener('click', function(e) {
    if (e.target === this) closeFeedbackModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFeedbackModal();
});
</script>

<?php include '../includes/footer.php'; ?>