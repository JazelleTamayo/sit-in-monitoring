<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Reservations - CCS Sit-in System";
$basePath  = "../";

// ── Pagination & search (includes pc_number) ───────────────────────────────
$entriesRaw = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$entries    = in_array($entriesRaw, [10, 25, 50, 100]) ? $entriesRaw : 10;
$page       = max(1, (int)($_GET['page'] ?? 1));
$search     = trim($_GET['search'] ?? '');
$filter     = $_GET['filter'] ?? 'all';

$params = [];
$where  = [];

if ($filter !== 'all') {
    $where[]  = "r.status = ?";
    $params[] = $filter;
}
if ($search !== '') {
    $where[]  = "(r.name LIKE ? OR r.id_number LIKE ? OR r.purpose LIKE ? OR r.laboratory LIKE ? OR r.pc_number LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations r
    INNER JOIN users u ON r.user_id = u.id
    $whereSql
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $entries));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $entries;

$dataStmt = $pdo->prepare("
    SELECT r.*, u.course, u.year_level, u.sessions AS remaining_sessions
    FROM reservations r
    INNER JOIN users u ON r.user_id = u.id
    $whereSql
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
");
$paramIndex = 1;
foreach ($params as $val) {
    $dataStmt->bindValue($paramIndex++, $val);
}
$dataStmt->bindValue($paramIndex++, $entries, PDO::PARAM_INT);
$dataStmt->bindValue($paramIndex++, $offset,  PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll();

$showingFrom = $totalRows === 0 ? 0 : $offset + 1;
$showingTo   = min($offset + $entries, $totalRows);

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(r.status = 'pending')  AS pending,
        SUM(r.status = 'approved') AS approved,
        SUM(r.status = 'rejected') AS rejected
    FROM reservations r
    INNER JOIN users u ON r.user_id = u.id
")->fetch();
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
    --radius-lg: 28px;
    --radius-md: 16px;
    --radius-sm: 10px;
    --transition: all 0.25s ease;
    --card-bg: rgba(10,18,40,0.82);
    --card-border: rgba(255,255,255,0.10);
    --card-border-hover: rgba(14,165,233,0.45);
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
}

.res-page {
    min-height: 100vh;
    padding: 1.5rem 32px 48px 32px;
    position: relative;
}
.res-page::before {
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
.res-main {
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
.alert-success { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }
.alert-error   { background: rgba(239,68,68,0.12);  border: 1px solid rgba(239,68,68,0.25);  color: #fca5a5; }
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-md);
    backdrop-filter: blur(24px);
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
}
.stat-card:hover {
    border-color: var(--card-border-hover);
    transform: translateY(-2px);
}
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.stat-icon.blue   { background: rgba(37,99,235,0.18);  color: #93c5fd; }
.stat-icon.yellow { background: rgba(245,158,11,0.18); color: #fcd34d; }
.stat-icon.green  { background: rgba(16,185,129,0.18); color: #6ee7b7; }
.stat-icon.red    { background: rgba(239,68,68,0.18);  color: #fca5a5; }
.stat-info { min-width: 0; }
.stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
}
.stat-label {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-top: 3px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
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
    background: rgba(37,99,235,0.20);
    border-bottom: 1px solid var(--border-light);
    flex-wrap: wrap;
    gap: 10px;
}
.dash-card-header-left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.dash-card-header i { color: var(--accent); }
.dash-card-header h3 { color: var(--text-primary); font-size: 1rem; font-weight: 600; margin: 0; }
.filter-tabs {
    display: flex;
    gap: 6px;
}
.filter-tab {
    padding: 5px 14px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid transparent;
    transition: var(--transition);
    color: var(--text-muted);
    background: rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.07);
}
.filter-tab:hover { color: var(--text-primary); background: rgba(255,255,255,0.08); }
.filter-tab.active {
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: #fff;
    border-color: transparent;
}
.controls-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    flex-wrap: wrap;
    gap: 12px;
}
.entries-control {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: 0.875rem;
}
.entries-select {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    color: var(--text-primary);
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
}
.search-form { display: flex; gap: 8px; }
.search-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    color: var(--text-primary);
    padding: 8px 14px;
    border-radius: 8px;
    width: 240px;
    font-size: 0.875rem;
}
.search-input:focus { outline: none; border-color: var(--accent); }
.btn-search {
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: #fff;
    border: none;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}
.table-wrapper { overflow-x: auto; }
.res-table { width: 100%; border-collapse: collapse; }
.res-table thead tr {
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.res-table th {
    padding: 13px 16px;
    text-align: left;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}
.res-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.18s;
}
.res-table tbody tr:last-child { border-bottom: none; }
.res-table tbody tr:hover { background: rgba(255,255,255,0.04); }
.res-table td {
    padding: 12px 16px;
    color: var(--text-secondary);
    font-size: 0.875rem;
    vertical-align: middle;
    white-space: nowrap;
}
.td-id   { color: var(--text-label) !important; font-weight: 700; font-size: 0.82rem; }
.td-name { color: var(--text-primary) !important; font-weight: 600; }
.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
}
.badge-pending  { background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.30); color: #fcd34d; }
.badge-approved { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }
.badge-rejected { background: rgba(239,68,68,0.12);  border: 1px solid rgba(239,68,68,0.25);  color: #fca5a5; }
.action-group {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.btn-approve, .btn-reassign, .btn-reject, .btn-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
    font-family: inherit;
    border: none;
    white-space: nowrap;
    width: 85px;  /* Fixed width for all buttons */
    text-align: center;
}
.btn-approve {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.25);
    color: #6ee7b7;
}
.btn-approve:hover { background: rgba(16,185,129,0.25); color: #fff; }
.btn-reassign {
    background: rgba(14,165,233,0.12);
    border: 1px solid rgba(14,165,233,0.25);
    color: #7dd3fc;
}
.btn-reassign:hover { background: rgba(14,165,233,0.25); color: #fff; }
.btn-reject {
    background: rgba(245,158,11,0.12);
    border: 1px solid rgba(245,158,11,0.25);
    color: #fcd34d;
}
.btn-reject:hover { background: rgba(245,158,11,0.25); color: #fff; }
.btn-delete {
    background: rgba(239,68,68,0.10);
    border: 1px solid rgba(239,68,68,0.20);
    color: #fca5a5;
}
.btn-delete:hover { background: rgba(239,68,68,0.25); color: #fff; }
.empty-state { padding: 60px 20px; text-align: center; }
.empty-state i { font-size: 3rem; color: #475569; display: block; margin-bottom: 16px; }
.empty-state h3 { color: var(--text-primary); margin-bottom: 6px; }
.empty-state p  { color: #64748b; font-size: 0.875rem; }
.table-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
    flex-wrap: wrap;
    gap: 12px;
}
.showing-text { color: #64748b; font-size: 0.875rem; }
.pagination { display: flex; gap: 4px; align-items: center; }
.page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 6px 12px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    color: #64748b;
    font-size: 0.82rem;
    text-decoration: none;
}
.page-btn.active {
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: #fff;
    cursor: default;
}
.page-btn.disabled { opacity: 0.35; pointer-events: none; }

/* Modal styles (unchanged) */
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
    max-width: 500px;
    width: 92%;
    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
    transform: translateY(20px);
    transition: transform 0.3s ease;
}
.modal-overlay.open .modal-box {
    transform: translateY(0);
}
.modal-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
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
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.modal-header h2 i { color: var(--accent); }
.modal-close {
    width: 32px; height: 32px;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    color: var(--text-muted);
    font-size: 1rem;
    cursor: pointer;
}
.modal-close:hover {
    background: rgba(239,68,68,0.15);
    border-color: rgba(239,68,68,0.3);
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
}
.btn-cancel {
    padding: 0.6rem 1.5rem;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
}
.btn-cancel:hover {
    background: rgba(255,255,255,0.10);
    color: var(--text-primary);
}
.btn-confirm {
    padding: 0.6rem 1.5rem;
    background: linear-gradient(135deg, var(--primary), #1d4ed8);
    border: none;
    border-radius: var(--radius-sm);
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
}
.btn-confirm:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37,99,235,0.4);
}
.form-group { margin-bottom: 15px; text-align: left; }
.form-group label { display: block; color: var(--text-muted); font-size: 0.8rem; margin-bottom: 5px; }
.form-control { width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: #fff; }
textarea.form-control { resize: vertical; }
select.form-control option { background: #0d1829; }

@media (max-width: 768px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .search-input { width: 160px; }
}
@media (max-width: 480px) {
    .stat-grid { grid-template-columns: 1fr 1fr; }
    .btn-approve, .btn-reassign, .btn-reject, .btn-delete { width: 70px; font-size: 0.7rem; }
}
</style>

<div class="res-page">
    <div class="res-main">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check" style="color:#0ea5e9;margin-right:8px;"></i>Reservation Management</h1>
            <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('F j, Y'); ?></div>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="stat-grid">
            <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div><div class="stat-info"><div class="stat-value"><?php echo $stats['total']; ?></div><div class="stat-label">Total</div></div></div>
            <div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-hourglass-half"></i></div><div class="stat-info"><div class="stat-value"><?php echo $stats['pending']; ?></div><div class="stat-label">Pending</div></div></div>
            <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-value"><?php echo $stats['approved']; ?></div><div class="stat-label">Approved</div></div></div>
            <div class="stat-card"><div class="stat-icon red"><i class="fas fa-times-circle"></i></div><div class="stat-info"><div class="stat-value"><?php echo $stats['rejected']; ?></div><div class="stat-label">Rejected</div></div></div>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-header-left"><i class="fas fa-list"></i><h3>Reservation Requests</h3></div>
                <div class="filter-tabs">
                    <?php $tabs = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'];
                    foreach ($tabs as $val=>$label): $active = ($filter===$val); ?>
                    <a href="?filter=<?php echo $val; ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>&page=1" class="filter-tab <?php echo $active?'active':''; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="controls-bar">
                <div class="entries-control"><span>Show</span><select class="entries-select" onchange="changeEntries(this.value)"><?php foreach([10,25,50,100] as $opt): ?><option value="<?php echo $opt; ?>" <?php echo $entries==$opt?'selected':''; ?>><?php echo $opt; ?></option><?php endforeach; ?></select><span>entries</span></div>
                <form method="GET" class="search-form">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="entries" value="<?php echo $entries; ?>">
                    <input type="hidden" name="page" value="1">
                    <input type="text" name="search" class="search-input" placeholder="Search name, ID, lab, PC..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
            <div class="table-wrapper">
                <table class="res-table">
                    <thead>
                        <tr><th>#</th><th>ID Number</th><th>Student Name</th><th>Course/Year</th><th>Purpose</th><th>Lab</th><th>PC</th><th>Date</th><th>Time In</th><th>Sessions</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="12"><div class="empty-state"><i class="fas fa-calendar-times"></i><h3>No reservations found</h3></div></td></tr>
                        <?php else: foreach ($rows as $i => $row): ?>
                        <tr>
                            <td style="color:#475569;"><?php echo $offset + $i + 1; ?></td>
                            <td class="td-id"><?php echo htmlspecialchars($row['id_number']); ?></td>
                            <td class="td-name"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['course'] ?? '—'); ?> <?php if($row['year_level']) echo '• '.htmlspecialchars($row['year_level']); ?></td>
                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($row['laboratory']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['pc_number'] ?? '—'); ?></strong></td>
                            <td><?php echo $row['reservation_date'] ? date('M j, Y', strtotime($row['reservation_date'])) : '—'; ?></td>
                            <td><?php echo $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '—'; ?></td>
                            <td><?php $sess = (int)($row['remaining_sessions']??0); echo '<span style="color:'.($sess>10?'#6ee7b7':($sess>5?'#fcd34d':'#fca5a5')).'">'.$sess.'</span>'; ?></td>
                            <td><span class="badge <?php echo match($row['status']) { 'approved'=>'badge-approved', 'rejected'=>'badge-rejected', default=>'badge-pending' }; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <td>
                                <div class="action-group">
                                    <?php if ($row['status'] === 'pending'): ?>
                                    <button class="btn-approve" onclick="directApprove(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn-reassign" onclick="openReassignModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['laboratory']); ?>', '<?php echo $row['reservation_date']; ?>')">
                                        <i class="fas fa-exchange-alt"></i> Reassign
                                    </button>
                                    <button class="btn-reject" onclick="openRejectModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-delete" onclick="openDeleteModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <div class="showing-text">Showing <?php echo $showingFrom; ?> to <?php echo $showingTo; ?> of <?php echo $totalRows; ?> entries</div>
                <?php if ($totalPages > 1): $baseUrl = '?filter='.urlencode($filter).'&entries='.$entries.'&search='.urlencode($search).'&page='; ?>
                <div class="pagination">
                    <a href="<?php echo $baseUrl.'1'; ?>" class="page-btn <?php echo $page<=1?'disabled':''; ?>"><i class="fas fa-angle-double-left"></i></a>
                    <a href="<?php echo $baseUrl.max(1,$page-1); ?>" class="page-btn <?php echo $page<=1?'disabled':''; ?>"><i class="fas fa-angle-left"></i> Prev</a>
                    <?php $start=max(1,$page-2); $end=min($totalPages,$start+4); $start=max(1,$end-4); for($p=$start;$p<=$end;$p++): ?>
                    <a href="<?php echo $baseUrl.$p; ?>" class="page-btn <?php echo $p==$page?'active':''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <a href="<?php echo $baseUrl.min($totalPages,$page+1); ?>" class="page-btn <?php echo $page>=$totalPages?'disabled':''; ?>">Next <i class="fas fa-angle-right"></i></a>
                    <a href="<?php echo $baseUrl.$totalPages; ?>" class="page-btn <?php echo $page>=$totalPages?'disabled':''; ?>"><i class="fas fa-angle-double-right"></i></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="reassignModal" class="modal-overlay">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header"><h2><i class="fas fa-exchange-alt"></i> Reassign & Approve</h2><button class="modal-close" onclick="closeModal('reassignModal')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <p id="reassignMessage"></p>
            <div class="form-group"><label>Select new PC:</label><select id="newPCSelect" class="form-control"></select></div>
            <div class="form-group"><label>Reason (optional):</label><input type="text" id="reassignReason" class="form-control" placeholder="e.g., requested PC was broken"></div>
        </div>
        <div class="modal-footer"><button class="btn-cancel" onclick="closeModal('reassignModal')">Cancel</button><button class="btn-confirm" id="reassignConfirmBtn">Reassign & Approve</button></div>
    </div>
</div>

<div id="rejectModal" class="modal-overlay">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header"><h2><i class="fas fa-times-circle"></i> Reject Reservation</h2><button class="modal-close" onclick="closeModal('rejectModal')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <p id="rejectMessage"></p>
            <div class="form-group"><label>Rejection reason <span style="color:#f87171;">*</span> :</label><textarea id="rejectReason" class="form-control" rows="3" required></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn-cancel" onclick="closeModal('rejectModal')">Cancel</button><button class="btn-confirm" id="rejectConfirmBtn">Reject</button></div>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header"><h2><i class="fas fa-trash"></i> Delete Reservation</h2><button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body"><p id="deleteMessage"></p></div>
        <div class="modal-footer"><button class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button><button class="btn-confirm" id="deleteConfirmBtn">Delete</button></div>
    </div>
</div>

<script>
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }

function directApprove(id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../process/reservation_action.php';
    form.innerHTML = `<input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
}

function openReassignModal(id, name, lab, date) {
    fetch(`../process/get_pcs.php?lab=${encodeURIComponent(lab)}&date=${encodeURIComponent(date)}`)
        .then(res => res.json())
        .then(pcs => {
            const available = pcs.filter(p => p.status === 'available').map(p => p.pc_number);
            const select = document.getElementById('newPCSelect');
            select.innerHTML = available.length ? available.map(p => `<option value="${p}">${p}</option>`).join('') : '<option disabled>No available PCs</option>';
            document.getElementById('reassignMessage').innerHTML = `Reassign reservation for <strong>${name}</strong> (Lab ${lab}) to an available PC.`;
            document.getElementById('reassignModal').dataset.id = id;
            openModal('reassignModal');
        })
        .catch(() => alert('Error loading available PCs.'));
}

document.getElementById('reassignConfirmBtn').onclick = () => {
    const id = document.getElementById('reassignModal').dataset.id;
    const newPC = document.getElementById('newPCSelect').value;
    if (!newPC) { alert('Please select a PC'); return; }
    const reason = document.getElementById('reassignReason').value;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../process/reservation_action.php';
    form.innerHTML = `<input type="hidden" name="action" value="reassign_approve"><input type="hidden" name="id" value="${id}"><input type="hidden" name="new_pc" value="${newPC}"><input type="hidden" name="reason" value="${reason}">`;
    document.body.appendChild(form);
    form.submit();
};

function openRejectModal(id, name) {
    document.getElementById('rejectMessage').innerHTML = `Reject reservation for <strong>${name}</strong>? Provide a reason below.`;
    document.getElementById('rejectModal').dataset.id = id;
    openModal('rejectModal');
}
document.getElementById('rejectConfirmBtn').onclick = () => {
    const id = document.getElementById('rejectModal').dataset.id;
    const reason = document.getElementById('rejectReason').value;
    if (!reason.trim()) { alert('Please provide a rejection reason'); return; }
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../process/reservation_action.php';
    form.innerHTML = `<input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="${id}"><input type="hidden" name="reject_reason" value="${reason}">`;
    document.body.appendChild(form);
    form.submit();
};

function openDeleteModal(id, name) {
    document.getElementById('deleteMessage').innerHTML = `Delete reservation for <strong>${name}</strong>? This action cannot be undone.`;
    document.getElementById('deleteModal').dataset.id = id;
    openModal('deleteModal');
}
document.getElementById('deleteConfirmBtn').onclick = () => {
    const id = document.getElementById('deleteModal').dataset.id;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../process/reservation_action.php';
    form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
};

function changeEntries(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('entries', value);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}
</script>

<?php include '../includes/footer.php'; ?>