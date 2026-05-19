<?php
session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Feedback Reports - CCS Sit-in System";
$basePath  = "../";

// Handle delete feedback
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: admin_feedback_reports.php?msg=deleted");
    exit();
}

// Pagination
$entriesRaw = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$entries    = in_array($entriesRaw, [10, 25, 50, 100]) ? $entriesRaw : 10;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $entries;
$search     = trim($_GET['search'] ?? '');

// Build query - JOIN with users table to get name and id_number
$where = "";
$params = [];

if (!empty($search)) {
    $where = "WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.id_number LIKE ? OR f.message LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
}

// Count total
$countSql = "
    SELECT COUNT(*) 
    FROM feedback f 
    JOIN users u ON f.user_id = u.id 
    $where
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $entries));
$page = min($page, $totalPages);
$offset = ($page - 1) * $entries;

// Fetch feedback with user data
$sql = "
    SELECT f.*, 
           u.id_number, 
           u.first_name, 
           u.last_name, 
           u.middle_name,
           u.course, 
           u.year_level,
           CONCAT(u.first_name, ' ', u.last_name) as full_name
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    $where
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$paramIndex = 1;
foreach ($params as $val) {
    $stmt->bindValue($paramIndex++, $val);
}
$stmt->bindValue($paramIndex++, $entries, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->execute();
$feedbacks = $stmt->fetchAll();

// Statistics
$total_feedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$avg_rating = $pdo->query("SELECT AVG(rating) FROM feedback")->fetchColumn();
$total_5star = $pdo->query("SELECT COUNT(*) FROM feedback WHERE rating = 5")->fetchColumn();
$total_4star = $pdo->query("SELECT COUNT(*) FROM feedback WHERE rating = 4")->fetchColumn();
$total_3star = $pdo->query("SELECT COUNT(*) FROM feedback WHERE rating = 3")->fetchColumn();
$total_2star = $pdo->query("SELECT COUNT(*) FROM feedback WHERE rating = 2")->fetchColumn();
$total_1star = $pdo->query("SELECT COUNT(*) FROM feedback WHERE rating = 1")->fetchColumn();

$showingFrom = $totalRows === 0 ? 0 : $offset + 1;
$showingTo   = min($offset + $entries, $totalRows);
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
.feedback-container {
    min-height: 100vh;
    padding: 1.5rem 24px 48px;
    position: relative;
}

.feedback-container::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 5%   0%,  rgba(37,99,235,0.35)  0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%);
    pointer-events: none;
    z-index: -1;
}

.feedback-main {
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
    border-bottom: 1px solid rgba(255,255,255,0.10);
}

.page-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #f1f5f9;
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

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: rgba(10,18,40,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 16px;
    backdrop-filter: blur(24px);
    padding: 1.25rem;
    text-align: center;
    transition: all 0.25s ease;
}

.stat-card:hover {
    background: rgba(14,24,52,0.90);
    border-color: rgba(14,165,233,0.45);
    transform: translateY(-3px);
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #f1f5f9;
}

.stat-card .stat-label {
    color: #64748b;
    font-size: 0.8rem;
    margin-top: 5px;
}

.stat-card .stars {
    margin-top: 8px;
    color: #facc15;
}

/* Rating Bars */
.rating-bars {
    background: rgba(10,18,40,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 16px;
    backdrop-filter: blur(24px);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.rating-bars h3 {
    color: #f1f5f9;
    font-size: 1rem;
    margin-bottom: 1rem;
}

.rating-bar-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.rating-label {
    width: 70px;
    color: #facc15;
    font-size: 0.85rem;
    font-weight: 600;
}

.rating-bar-bg {
    flex: 1;
    height: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    overflow: hidden;
}

.rating-bar-fill {
    height: 100%;
    border-radius: 4px;
}

.rating-count {
    width: 40px;
    color: #64748b;
    font-size: 0.75rem;
    text-align: right;
}

/* Controls Bar */
.controls-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
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
    background: #1e293b;
    border: 1px solid rgba(255,255,255,0.08);
    color: #f1f5f9;
    padding: 7px 12px;
    border-radius: 10px;
    font-size: 0.875rem;
    cursor: pointer;
}

.entries-select option {
    background: #1e293b;
    color: #f1f5f9;
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
}

.search-input::placeholder { color: #64748b; }
.search-input:focus {
    outline: none;
    border-color: #0ea5e9;
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
    transition: all 0.2s;
}

.btn-search:hover {
    opacity: 0.88;
    transform: translateY(-1px);
}

/* Table */
.table-wrapper {
    overflow-x: auto;
}

.feedback-table {
    width: 100%;
    border-collapse: collapse;
}

.feedback-table thead tr {
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.feedback-table th {
    padding: 12px 16px;
    text-align: left;
    color: #64748b;
    font-weight: 600;
    font-size: 0.7rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}

.feedback-table td {
    padding: 12px 16px;
    color: #cbd5e1;
    font-size: 0.85rem;
    vertical-align: top;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}

.feedback-table tr:hover td {
    background: rgba(255,255,255,0.03);
}

.rating-stars {
    display: inline-flex;
    gap: 2px;
}
.rating-stars i {
    font-size: 0.75rem;
    color: #facc15;
}
/* Force outline stars for far class */
.rating-stars i.far {
    font-weight: 400;
    color: #475569;
}

.feedback-message {
    max-width: 350px;
    white-space: normal;
    word-wrap: break-word;
}

.btn-delete {
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.25);
    color: #fca5a5;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-delete:hover {
    background: rgba(239,68,68,0.25);
    color: #fff;
}

/* Table Footer */
.table-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    flex-wrap: wrap;
    gap: 12px;
}

.showing-text {
    color: #64748b;
    font-size: 0.875rem;
}

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
    border-radius: 6px;
    color: #64748b;
    font-size: 0.8rem;
    text-decoration: none;
    transition: all 0.18s;
}

.page-btn:hover:not(.disabled):not(.active) {
    background: rgba(255,255,255,0.08);
    color: #cbd5e1;
}

.page-btn.active {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: #fff;
    border-color: transparent;
}

.page-btn.disabled {
    opacity: 0.35;
    cursor: not-allowed;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 60px;
}

.empty-state i {
    font-size: 3rem;
    color: #475569;
    margin-bottom: 1rem;
    display: block;
}

.empty-state h3 {
    color: #f1f5f9;
    margin-bottom: 5px;
}

.empty-state p {
    color: #64748b;
}

.alert {
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: rgba(16,185,129,0.1);
    color: #6ee7b7;
    border: 1px solid rgba(16,185,129,0.2);
}

@media (max-width: 1024px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .feedback-container {
        padding: 80px 16px 40px;
    }
    .stats-row {
        grid-template-columns: 1fr;
    }
    .controls-bar {
        flex-direction: column;
    }
    .search-form {
        width: 100%;
    }
    .search-input {
        flex: 1;
    }
}
</style>

<div class="feedback-container">
    <div class="feedback-main">

        <div class="page-header">
            <h1><i class="fas fa-comment-dots" style="color:#0ea5e9;margin-right:8px;"></i>Feedback Reports</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Feedback deleted successfully.
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_feedback; ?></div>
                <div class="stat-label">Total Feedback</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($avg_rating, 1); ?></div>
                <div class="stat-label">Average Rating</div>
                <div class="stars">
                    <?php 
                    $avg = round($avg_rating);
                    for ($i = 1; $i <= 5; $i++): 
                    ?>
                        <i class="fas fa-star <?php echo $i <= $avg ? '' : 'far'; ?>" style="color: #facc15;"></i>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_5star; ?></div>
                <div class="stat-label">5 Star Ratings</div>
                <div class="stars"><i class="fas fa-star" style="color: #facc15;"></i> Excellent</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_1star; ?></div>
                <div class="stat-label">1 Star Ratings</div>
                <div class="stars"><i class="fas fa-star" style="color: #facc15;"></i> Needs Improvement</div>
            </div>
        </div>

        <!-- Rating Distribution Bars -->
        <div class="rating-bars">
            <h3>Rating Distribution</h3>
            <?php
            $ratings = [
                5 => ['label' => '5 Stars', 'count' => $total_5star, 'color' => '#facc15'],
                4 => ['label' => '4 Stars', 'count' => $total_4star, 'color' => '#fbbf24'],
                3 => ['label' => '3 Stars', 'count' => $total_3star, 'color' => '#f59e0b'],
                2 => ['label' => '2 Stars', 'count' => $total_2star, 'color' => '#ea580c'],
                1 => ['label' => '1 Star', 'count' => $total_1star, 'color' => '#dc2626']
            ];
            $maxCount = max($total_5star, $total_4star, $total_3star, $total_2star, $total_1star);
            $maxCount = $maxCount > 0 ? $maxCount : 1;
            foreach ($ratings as $rating => $data):
                $percentage = ($data['count'] / $maxCount) * 100;
            ?>
                <div class="rating-bar-item">
                    <div class="rating-label"><?php echo $data['label']; ?></div>
                    <div class="rating-bar-bg">
                        <div class="rating-bar-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $data['color']; ?>;"></div>
                    </div>
                    <div class="rating-count"><?php echo $data['count']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Feedback Table -->
        <div class="dash-card" style="background: rgba(10,18,40,0.82); border: 1px solid rgba(255,255,255,0.10); border-radius: 16px; backdrop-filter: blur(24px); overflow: hidden;">
            
            <!-- Controls -->
            <div class="controls-bar">
                <div class="entries-control">
                    <span>Show</span>
                    <select class="entries-select" onchange="changeEntries(this.value)">
                        <?php foreach ([10,25,50,100] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $entries == $opt ? 'selected' : ''; ?>>
                            <?php echo $opt; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span>entries</span>
                </div>

                <form method="GET" action="" class="search-form">
                    <input type="hidden" name="entries" value="<?php echo $entries; ?>">
                    <input type="hidden" name="page" value="1">
                    <input type="text" name="search" class="search-input" placeholder="Search by student or message..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <!-- Table -->
            <div class="table-wrapper">
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>ID Number</th>
                            <th>Course / Year</th>
                            <th>Rating</th>
                            <th>Feedback</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($feedbacks)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-comment-slash"></i>
                                        <h3>No feedback yet</h3>
                                        <p>Students haven't submitted any feedback.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($feedbacks as $fb): ?>
                            <tr>
                                <td><?php echo $fb['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']); ?></strong>
                                 </td>
                                <td><?php echo htmlspecialchars($fb['id_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($fb['course'] ?? 'N/A'); ?><br>
                                    <small style="color:#64748b;">Year <?php echo htmlspecialchars($fb['year_level'] ?? ''); ?></small>
                                 </td>
                                <td>
                                    <?php 
                                    // Ensure rating is integer (in case DB returns string)
                                    $rating = (int)($fb['rating'] ?? 0);
                                    // Clamp between 0 and 5
                                    $rating = max(0, min(5, $rating));
                                    ?>
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $rating): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <span style="font-size:0.65rem; color:#64748b;">(<?php echo $rating; ?> stars)</span>
                                 </td>
                                <td class="feedback-message">
                                    <?php echo nl2br(htmlspecialchars(substr($fb['message'], 0, 200))); ?>
                                    <?php if (strlen($fb['message']) > 200): ?>...<?php endif; ?>
                                 </td>
                                <td>
                                    <small><?php echo date('M j, Y g:i A', strtotime($fb['created_at'])); ?></small>
                                 </td>
                                <td>
                                    <a href="?delete=<?php echo $fb['id']; ?>" class="btn-delete" onclick="return confirm('Delete this feedback?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="table-footer">
                <div class="showing-text">
                    Showing <strong><?php echo $showingFrom; ?></strong> to <strong><?php echo $showingTo; ?></strong> of <strong><?php echo $totalRows; ?></strong> entries
                    <?php if ($search): ?>
                        <span style="color:#475569;"> &mdash; filtered by "<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <a href="?page=1&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo max(1, $page - 1); ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-left"></i> Prev
                    </a>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <a href="?page=<?php echo $p; ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>" class="page-btn <?php echo $p == $page ? 'active' : ''; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                    <a href="?page=<?php echo min($totalPages, $page + 1); ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>" class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        Next <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $totalPages; ?>&entries=<?php echo $entries; ?>&search=<?php echo urlencode($search); ?>" class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function changeEntries(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('entries', value);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}
</script>

<?php include '../includes/footer.php'; ?>