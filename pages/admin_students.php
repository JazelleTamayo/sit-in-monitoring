<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Students - CCS Sit-in System";
$basePath = "../";

// ── Handle Delete (force delete) ──────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        header("Location: admin_students.php?msg=deleted");
        exit();
    } catch (PDOException $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        header("Location: admin_students.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// ── Handle Reset All Sessions ──────────────────────────────────────────────
if (isset($_POST['reset_all_sessions'])) {
    $pdo->exec("UPDATE users SET sessions = 30");
    header("Location: admin_students.php?msg=reset");
    exit();
}

// ── Handle Update Sessions for individual student ──────────────────────────
if (isset($_POST['update_sessions']) && isset($_POST['student_id']) && isset($_POST['sessions'])) {
    $student_id = (int) $_POST['student_id'];
    $sessions = (int) $_POST['sessions'];
    $pdo->prepare("UPDATE users SET sessions = ? WHERE id = ?")->execute([$sessions, $student_id]);
    header("Location: admin_students.php?msg=updated");
    exit();
}

// ── Pagination & Search ────────────────────────────────────────────────────
$entries_raw = isset($_GET['entries']) ? (int) $_GET['entries'] : 10;
$entries = in_array($entries_raw, [10, 25, 50, 100]) ? $entries_raw : 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $entries;

$where = '';
$params = [];

if (!empty($search)) {
    $where = "WHERE id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR course LIKE ? OR email LIKE ?";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like, $like];
}

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$total_stmt->execute($params);
$total_records = (int) $total_stmt->fetchColumn();
$total_pages = $total_records > 0 ? ceil($total_records / $entries) : 1;

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY last_name, first_name LIMIT $entries OFFSET $offset");
$stmt->execute($params);
$students = $stmt->fetchAll();
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
        --border-light: rgba(255, 255, 255, 0.10);
        --border-hover: rgba(14, 165, 233, 0.40);
        --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
        --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.60);
        --radius-lg: 28px;
        --radius-md: 16px;
        --radius-sm: 10px;
        --transition: all 0.25s ease;
        --card-bg: rgba(10, 18, 40, 0.82);
        --card-bg-hover: rgba(14, 24, 52, 0.90);
        --card-border: rgba(255, 255, 255, 0.10);
        --card-border-hover: rgba(14, 165, 233, 0.45);
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
    }

    .students-page-container {
        min-height: 100vh;
        padding: 1.5rem 32px 48px 32px;
        /* changed from 24px to 32px */
        position: relative;
    }

    .students-page-container::before {
        content: '';
        position: fixed;
        inset: 0;
        background:
            radial-gradient(ellipse at 5% 0%, rgba(37, 99, 235, 0.35) 0%, transparent 45%),
            radial-gradient(ellipse at 95% 100%, rgba(14, 165, 233, 0.25) 0%, transparent 45%),
            radial-gradient(ellipse at 75% 15%, rgba(124, 58, 237, 0.18) 0%, transparent 38%),
            radial-gradient(ellipse at 25% 85%, rgba(16, 185, 129, 0.12) 0%, transparent 38%);
        pointer-events: none;
        z-index: -1;
    }

    .students-main {
        max-width: 1300px;
        /* changed from 1400px to match dashboard */
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
        background: rgba(10, 18, 40, 0.70);
        border: 1px solid var(--border-light);
        border-radius: 999px;
        color: var(--text-secondary);
        font-size: 0.875rem;
        backdrop-filter: blur(12px);
    }

    .date-badge i {
        color: var(--accent);
    }

    .dash-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-md);
        backdrop-filter: blur(24px);
        overflow: hidden;
    }

    .dash-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        background: rgba(37, 99, 235, 0.20);
        border-bottom: 1px solid var(--border-light);
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .dash-card-header-left {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .dash-card-header i {
        color: var(--accent);
    }

    .dash-card-header h3 {
        color: var(--text-primary);
        font-size: 1rem;
        font-weight: 600;
        margin: 0;
    }

    .header-actions {
        display: flex;
        gap: 0.6rem;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 1.1rem;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        font-family: inherit;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), #1d4ed8);
        color: #fff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-light), var(--primary));
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
    }

    .btn-warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
    }

    .btn-warning:hover {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #f87171, #ef4444);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
    }

    .btn-sm {
        padding: 0.3rem 0.8rem;
        font-size: 0.8rem;
    }

    .table-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        flex-wrap: wrap;
        gap: 0.75rem;
        border-bottom: 1px solid var(--border-light);
    }

    .entries-control {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-muted);
        font-size: 0.875rem;
    }

    .entries-select {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
        padding: 0.35rem 0.75rem;
        font-size: 0.875rem;
        font-family: inherit;
        cursor: pointer;
    }

    .search-control {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .search-control label {
        color: var(--text-muted);
        font-size: 0.875rem;
    }

    .search-input {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
        padding: 0.35rem 0.75rem;
        font-size: 0.875rem;
        font-family: inherit;
        width: 200px;
        transition: var(--transition);
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        background: rgba(255, 255, 255, 0.08);
    }

    .search-input::placeholder {
        color: var(--text-muted);
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .students-table {
        width: 100%;
        border-collapse: collapse;
    }

    .students-table thead tr {
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid var(--border-light);
    }

    .students-table th {
        padding: 0.9rem 1.25rem;
        text-align: left;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .students-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        transition: var(--transition);
    }

    .students-table tbody tr:last-child {
        border-bottom: none;
    }

    .students-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.04);
    }

    .students-table td {
        padding: 0.9rem 1.25rem;
        color: var(--text-secondary);
        font-size: 0.9rem;
        vertical-align: middle;
    }

    .td-name {
        color: var(--text-primary) !important;
        font-weight: 600;
    }

    .td-id {
        color: var(--text-label) !important;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .badge {
        display: inline-block;
        padding: 0.2rem 0.7rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-course {
        background: rgba(37, 99, 235, 0.15);
        border: 1px solid rgba(37, 99, 235, 0.3);
        color: #93c5fd;
    }

    .badge-year {
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.25);
        color: #6ee7b7;
    }

    .badge-sessions {
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.25);
        color: #fcd34d;
    }

    .action-btns {
        display: flex;
        gap: 0.4rem;
        flex-wrap: wrap;
    }

    .table-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border-light);
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .showing-text {
        color: var(--text-muted);
        font-size: 0.875rem;
    }

    .pagination {
        display: flex;
        gap: 0.3rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.4rem 0.75rem;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--border-light);
        border-radius: 8px;
        color: var(--text-muted);
        font-size: 0.85rem;
        text-decoration: none;
        transition: var(--transition);
    }

    .page-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-primary);
    }

    .page-btn.active {
        background: linear-gradient(135deg, var(--primary), #1d4ed8);
        border-color: transparent;
        color: #fff;
        font-weight: 600;
    }

    .page-btn.disabled {
        opacity: 0.35;
        cursor: not-allowed;
        pointer-events: none;
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

    .empty-state h3 {
        color: var(--text-primary);
        margin-bottom: 0.4rem;
    }

    .empty-state p {
        color: var(--text-muted);
        font-size: 0.875rem;
    }

    /* Modals */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(8, 14, 26, 0.88);
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
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: var(--radius-lg);
        max-width: 600px;
        width: 92%;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
        transform: translateY(20px);
        transition: transform 0.3s ease;
        max-height: 90vh;
        overflow-y: auto;
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
        position: sticky;
        top: 0;
        background: #0d1829;
        z-index: 1;
    }

    .modal-header h2 {
        color: var(--text-primary);
        font-size: 1.1rem;
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
        background: rgba(255, 255, 255, 0.06);
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
        background: rgba(239, 68, 68, 0.15);
        border-color: rgba(239, 68, 68, 0.3);
        color: #fca5a5;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1.25rem 1.5rem;
        border-top: 1px solid var(--border-light);
        position: sticky;
        bottom: 0;
        background: #0d1829;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 0.4rem;
    }

    .form-label i {
        color: var(--accent);
        margin-right: 4px;
    }

    .form-control {
        width: 100%;
        padding: 0.7rem 1rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
        font-size: 0.9rem;
        font-family: inherit;
        transition: var(--transition);
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent);
        background: rgba(255, 255, 255, 0.08);
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    }

    .form-control::placeholder {
        color: var(--text-muted);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .btn-cancel {
        padding: 0.6rem 1.5rem;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm);
        color: var(--text-secondary);
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        font-family: inherit;
    }

    .btn-cancel:hover {
        background: rgba(255, 255, 255, 0.10);
        color: var(--text-primary);
    }

    .confirm-icon {
        width: 64px;
        height: 64px;
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.25);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
    }

    .confirm-icon i {
        font-size: 1.8rem;
        color: #fca5a5;
    }

    .confirm-text {
        text-align: center;
        color: var(--text-secondary);
        font-size: 0.95rem;
        line-height: 1.6;
    }

    .confirm-text strong {
        color: var(--text-primary);
    }

    select,
    .entries-select,
    .form-control select,
    select.form-control {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }

    select option,
    .entries-select option,
    .form-control option,
    select.form-control option {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }

    .entries-select {
        background: #1e293b !important;
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: var(--radius-sm);
        color: #f1f5f9 !important;
        padding: 0.35rem 0.75rem;
        font-size: 0.875rem;
        font-family: inherit;
        cursor: pointer;
    }

    .entries-select option {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }

    .modal-box select,
    .modal-box .form-control select {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }

    .modal-box select option,
    .modal-box .form-control option {
        background: #1e293b !important;
        color: #f1f5f9 !important;
    }
</style>

<div class="students-page-container">
    <div class="students-main">

        <div class="page-header">
            <h1><i class="fas fa-users" style="color:#0ea5e9;margin-right:8px;"></i> Students Information</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-header-left">
                    <i class="fas fa-users"></i>
                    <h3>Student List</h3>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openModal('addModal')">
                        <i class="fas fa-user-plus"></i> Add Student
                    </button>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="reset_all_sessions" class="btn btn-warning"
                            onclick="return confirm('Reset ALL student sessions to 30?')">
                            <i class="fas fa-redo"></i> Reset All Sessions
                        </button>
                    </form>
                </div>
            </div>

            <form method="GET" action="" id="filterForm">
                <div class="table-controls">
                    <div class="entries-control">
                        <span>Show</span>
                        <select name="entries" class="entries-select"
                            onchange="document.getElementById('filterForm').submit()">
                            <?php foreach ([10, 25, 50, 100] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo $entries == $opt ? 'selected' : ''; ?>>
                                    <?php echo $opt; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span>entries</span>
                    </div>
                    <div class="search-control">
                        <label>Search:</label>
                        <input type="text" name="search" class="search-input" placeholder="Search..."
                            value="<?php echo htmlspecialchars($search); ?>" oninput="this.form.submit()">
                        <input type="hidden" name="entries" value="<?php echo $entries; ?>">
                    </div>
                </div>
            </form>

            <div class="table-wrapper">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Year Level</th>
                            <th>Course</th>
                            <th>Email</th>
                            <th>Sessions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td class="td-id"><?php echo htmlspecialchars($s['id_number']); ?></td>
                                    <td class="td-name">
                                        <?php
                                        $mid = !empty($s['middle_name']) ? substr($s['middle_name'], 0, 1) . '. ' : '';
                                        echo htmlspecialchars($s['first_name'] . ' ' . $mid . $s['last_name']);
                                        ?>
                                    </td>
                                    <td><span class="badge badge-year">Year
                                            <?php echo htmlspecialchars($s['year_level']); ?></span></td>
                                    <td><span class="badge badge-course"><?php echo htmlspecialchars($s['course']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td>
                                        <span class="badge badge-sessions">
                                            <?php echo isset($s['sessions']) ? htmlspecialchars($s['sessions']) : '30'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-primary btn-sm"
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($s)); ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-warning btn-sm"
                                                onclick="openSessionsModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>', <?php echo $s['sessions'] ?? 30; ?>)">
                                                <i class="fas fa-hourglass-half"></i> Sessions
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                onclick="confirmDelete(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <h3>No students found</h3>
                                        <p><?php echo !empty($search) ? "No results for \"" . htmlspecialchars($search) . "\"." : "No students registered yet."; ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <div class="showing-text">
                    <?php
                    $from = $total_records ? $offset + 1 : 0;
                    $to = min($offset + $entries, $total_records);
                    echo "Showing {$from} to {$to} of {$total_records} entries";
                    if (!empty($search))
                        echo " (filtered)";
                    ?>
                </div>
                <div class="pagination">
                    <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                        href="?page=1&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"
                        href="?page=<?php echo $page - 1; ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-angle-left"></i> Prev
                    </a>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a class="page-btn <?php echo $i == $page ? 'active' : ''; ?>"
                            href="?page=<?php echo $i; ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <a class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"
                        href="?page=<?php echo $page + 1; ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>">
                        Next <i class="fas fa-angle-right"></i>
                    </a>
                    <a class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"
                        href="?page=<?php echo $total_pages; ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add Student</h2>
            <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="../process/add_student.php">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-id-card"></i> ID Number</label>
                        <input type="text" name="id_number" class="form-control" placeholder="e.g. 23756258" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-graduation-cap"></i> Course</label>
                        <select name="course" class="form-control" required>
                            <option value="">Select Course</option>
                            <option value="BSIT">BSIT</option>
                            <option value="BSCS">BSCS</option>
                            <option value="BSIS">BSIS</option>
                            <option value="ACT">ACT</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> First Name</label>
                        <input type="text" name="first_name" class="form-control" placeholder="First name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Last Name</label>
                        <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-layer-group"></i> Year Level</label>
                        <select name="year_level" class="form-control" required>
                            <option value="">Select Year</option>
                            <?php for ($y = 1; $y <= 4; $y++): ?>
                                <option value="<?php echo $y; ?>">Year <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" class="form-control" placeholder="student@email.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Address">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Set password" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-hourglass-half"></i> Initial Sessions</label>
                    <input type="number" name="sessions" class="form-control" value="30" min="0" max="99">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Student</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Edit Student</h2>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="../process/edit_student.php">
            <input type="hidden" name="student_id" id="edit_student_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-id-card"></i> ID Number</label>
                        <input type="text" name="id_number" id="edit_id_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-graduation-cap"></i> Course</label>
                        <select name="course" id="edit_course" class="form-control" required>
                            <option value="BSIT">BSIT</option>
                            <option value="BSCS">BSCS</option>
                            <option value="BSIS">BSIS</option>
                            <option value="ACT">ACT</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Middle Name</label>
                        <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-layer-group"></i> Year Level</label>
                        <select name="year_level" id="edit_year_level" class="form-control" required>
                            <?php for ($y = 1; $y <= 4; $y++): ?>
                                <option value="<?php echo $y; ?>">Year <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                    <input type="text" name="address" id="edit_address" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-lock"></i> New Password
                        <small style="color:var(--text-muted);text-transform:none;font-weight:400;">(leave blank to keep
                            current)</small>
                    </label>
                    <input type="password" name="password" class="form-control"
                        placeholder="Leave blank to keep current">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Student</button>
            </div>
        </form>
    </div>
</div>

<!-- UPDATE SESSIONS MODAL -->
<div class="modal-overlay" id="sessionsModal">
    <div class="modal-box" style="max-width: 400px;">
        <div class="modal-header">
            <h2><i class="fas fa-hourglass-half"></i> Update Sessions</h2>
            <button class="modal-close" onclick="closeModal('sessionsModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="student_id" id="sessions_student_id">
            <div class="modal-body">
                <p class="confirm-text" style="margin-bottom: 1rem;">
                    Update sessions for<br>
                    <strong id="sessions_student_name"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-hourglass-half"></i> Remaining Sessions</label>
                    <input type="number" name="sessions" id="sessions_value" class="form-control" min="0" max="99"
                        required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('sessionsModal')">Cancel</button>
                <button type="submit" name="update_sessions" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Sessions
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <h2><i class="fas fa-trash"></i> Confirm Delete</h2>
            <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <p class="confirm-text">
                Are you sure you want to delete<br>
                <strong id="delete_student_name"></strong>?<br>
                <span style="color:var(--text-muted);font-size:0.85rem;">This action cannot be undone.</span>
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
            <!-- FIX: Changed from <a> tag to <button> calling executeDelete() -->
            <button id="delete_confirm_btn" class="btn btn-danger" onclick="executeDelete()">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
    // ── Modal helpers ──────────────────────────────────────────────────────
    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }

    // Close on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
                closeModal(m.id);
            });
        }
    });

    // ── Edit modal ─────────────────────────────────────────────────────────
    function openEditModal(student) {
        document.getElementById('edit_student_id').value = student.id;
        document.getElementById('edit_id_number').value = student.id_number;
        document.getElementById('edit_first_name').value = student.first_name;
        document.getElementById('edit_last_name').value = student.last_name;
        document.getElementById('edit_middle_name').value = student.middle_name ?? '';
        document.getElementById('edit_email').value = student.email;
        document.getElementById('edit_address').value = student.address ?? '';
        document.getElementById('edit_course').value = student.course;
        document.getElementById('edit_year_level').value = student.year_level;
        openModal('editModal');
    }

    // ── Sessions modal ─────────────────────────────────────────────────────
    function openSessionsModal(id, name, sessions) {
        document.getElementById('sessions_student_id').value = id;
        document.getElementById('sessions_student_name').textContent = name;
        document.getElementById('sessions_value').value = sessions;
        openModal('sessionsModal');
    }

    // ── Delete modal — FIXED ───────────────────────────────────────────────
    var deleteTargetId = null; // stores the ID to delete

    function confirmDelete(id, name) {
        deleteTargetId = id;
        document.getElementById('delete_student_name').textContent = name;
        openModal('deleteModal');
    }

    function executeDelete() {
        if (deleteTargetId !== null) {
            window.location.href = 'admin_students.php?delete=' + deleteTargetId;
        }
    }

    // ── Result toasts after redirect ───────────────────────────────────────
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function (m) {
            return m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var urlParams = new URLSearchParams(window.location.search);
        var msg = urlParams.get('msg');
        var error = urlParams.get('error');

        if (msg === 'deleted') {
            insertResultModal(
                '<i class="fas fa-check-circle" style="color:#10b981;"></i> Success',
                'rgba(16,185,129,0.12)', 'rgba(16,185,129,0.25)',
                'fas fa-trash-alt', '#6ee7b7',
                'Student deleted successfully.'
            );
        } else if (msg === 'reset') {
            insertResultModal(
                '<i class="fas fa-check-circle" style="color:#10b981;"></i> Success',
                'rgba(16,185,129,0.12)', 'rgba(16,185,129,0.25)',
                'fas fa-redo', '#6ee7b7',
                'All sessions have been reset to 30.'
            );
        } else if (msg === 'updated') {
            insertResultModal(
                '<i class="fas fa-check-circle" style="color:#10b981;"></i> Success',
                'rgba(16,185,129,0.12)', 'rgba(16,185,129,0.25)',
                'fas fa-save', '#6ee7b7',
                'Sessions updated successfully.'
            );
        } else if (error) {
            insertResultModal(
                '<i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Error',
                'rgba(239,68,68,0.12)', 'rgba(239,68,68,0.25)',
                'fas fa-exclamation-triangle', '#fca5a5',
                'Operation failed: ' + escapeHtml(error)
            );
        }
    });

    function insertResultModal(title, bgColor, borderColor, iconClass, iconColor, message) {
        var html = '<div class="modal-overlay open" id="resultModal">' +
            '<div class="modal-box" style="max-width:400px;">' +
            '<div class="modal-header">' +
            '<h2>' + title + '</h2>' +
            '<button class="modal-close" onclick="closeResultModal()"><i class="fas fa-times"></i></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="confirm-icon" style="background:' + bgColor + '; border-color:' + borderColor + ';">' +
            '<i class="' + iconClass + '" style="color:' + iconColor + ';"></i>' +
            '</div>' +
            '<p class="confirm-text">' + escapeHtml(message) + '</p>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button class="btn btn-primary" onclick="closeResultModal()">OK</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        document.body.insertAdjacentHTML('beforeend', html);
    }

    function closeResultModal() {
        var m = document.getElementById('resultModal');
        if (m) m.remove();
        // Clean the URL so the modal doesn't re-appear on manual refresh
        var url = window.location.pathname + '?page=<?php echo $page; ?>&entries=<?php echo $entries; ?><?php echo !empty($search) ? "&search=" . urlencode($search) : ""; ?>';
        window.history.replaceState({}, document.title, url);
    }
</script>

<?php include '../includes/footer.php'; ?>