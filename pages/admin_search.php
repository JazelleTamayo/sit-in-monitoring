<?php
session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Search Student - CCS Sit-in System";
$basePath  = "../";

// Handle search
$search_results = [];
$search_query   = '';
$searched       = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_query'])) {
    $search_query = trim($_POST['search_query']);
    $searched     = true;

    if (!empty($search_query)) {
        $stmt = $pdo->prepare("
            SELECT * FROM users
            WHERE id_number     LIKE ?
               OR first_name    LIKE ?
               OR last_name     LIKE ?
               OR email         LIKE ?
               OR course        LIKE ?
            ORDER BY last_name, first_name
        ");
        $like = "%{$search_query}%";
        $stmt->execute([$like, $like, $like, $like, $like]);
        $search_results = $stmt->fetchAll();
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
:root {
    --primary:           #2563eb;
    --primary-light:     #3b82f6;
    --accent:            #0ea5e9;
    --text-primary:      #f1f5f9;
    --text-secondary:    #cbd5e1;
    --text-muted:        #94a3b8;
    --text-label:        #7dd3fc;
    --border-light:      rgba(255, 255, 255, 0.10);
    --border-hover:      rgba(14, 165, 233, 0.40);
    --shadow-md:         0 8px 32px rgba(0,0,0,0.50);
    --radius-md:         16px;
    --radius-sm:         10px;
    --transition:        all 0.25s ease;
    --card-bg:           rgba(10, 18, 40, 0.82);
    --card-bg-hover:     rgba(14, 24, 52, 0.90);
    --card-border:       rgba(255, 255, 255, 0.10);
    --card-border-hover: rgba(14, 165, 233, 0.45);
}

.search-page-container {
    min-height: 100vh;
    padding: 1.5rem 32px 48px 32px;   /* ← changed to match dashboard */
    position: relative;
}

.search-page-container::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 5%   0%,  rgba(37,99,235,0.35)   0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25)  0%, transparent 45%),
        radial-gradient(ellipse at 75%  15%, rgba(124,58,237,0.18)  0%, transparent 38%),
        radial-gradient(ellipse at 25%  85%, rgba(16,185,129,0.12)  0%, transparent 38%);
    pointer-events: none;
    z-index: -1;
}

.search-main {
    max-width: 1300px;               /* ← changed to match dashboard */
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

/* Page header */
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
    font-weight: 500;
    backdrop-filter: blur(12px);
}
.date-badge i { color: var(--accent); }

/* Search Card */
.search-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-md);
    backdrop-filter: blur(24px);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.search-card-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 1rem 1.25rem;
    background: rgba(37,99,235,0.20);
    border-bottom: 1px solid var(--border-light);
}

.search-card-header i { color: var(--accent); }
.search-card-header h3 {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.search-card-body {
    padding: 1.5rem;
}

/* Search Input Row */
.search-input-row {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.search-input-wrapper {
    flex: 1;
    position: relative;
}

.search-input-wrapper i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.9rem;
    pointer-events: none;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 0.95rem;
    font-family: inherit;
    transition: var(--transition);
    box-sizing: border-box;
}

.search-input::placeholder { color: var(--text-muted); }

.search-input:focus {
    outline: none;
    border-color: var(--accent);
    background: rgba(255,255,255,0.08);
    box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}

.btn-search {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.75rem;
    background: linear-gradient(135deg, var(--primary), #1d4ed8);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
    white-space: nowrap;
}

.btn-search:hover {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37,99,235,0.4);
}

.search-hint {
    margin-top: 0.6rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

/* Results Card */
.results-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-md);
    backdrop-filter: blur(24px);
    overflow: hidden;
}

.results-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: rgba(37,99,235,0.20);
    border-bottom: 1px solid var(--border-light);
}

.results-card-header h3 {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.results-card-header h3 i { color: var(--accent); }

.results-count {
    background: rgba(14,165,233,0.15);
    border: 1px solid rgba(14,165,233,0.3);
    color: var(--text-label);
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Table */
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

.results-table tbody tr:hover {
    background: rgba(255,255,255,0.04);
}

.results-table td {
    padding: 1rem 1.25rem;
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

.badge-course {
    display: inline-block;
    padding: 0.2rem 0.7rem;
    background: rgba(37,99,235,0.15);
    border: 1px solid rgba(37,99,235,0.3);
    color: #93c5fd;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-year {
    display: inline-block;
    padding: 0.2rem 0.7rem;
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.25);
    color: #6ee7b7;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Empty / No results */
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
    font-size: 1.1rem;
}

.empty-state p {
    color: var(--text-muted);
    font-size: 0.875rem;
}
</style>

<div class="search-page-container">
    <div class="search-main">

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-search" style="color:#0ea5e9;margin-right:8px;"></i> Search Student</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- Search Card -->
        <div class="search-card">
            <div class="search-card-header">
                <i class="fas fa-search"></i>
                <h3>Find a Student</h3>
            </div>
            <div class="search-card-body">
                <form method="POST" action="">
                    <div class="search-input-row">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text"
                                   name="search_query"
                                   class="search-input"
                                   placeholder="Search by name, ID number, email, or course..."
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   autofocus>
                        </div>
                        <button type="submit" class="btn-search">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <p class="search-hint">
                        <i class="fas fa-info-circle"></i>
                        You can search by ID number, first name, last name, email, or course.
                    </p>
                </form>
            </div>
        </div>

        <!-- Results -->
        <?php if ($searched): ?>
        <div class="results-card">
            <div class="results-card-header">
                <h3>
                    <i class="fas fa-users"></i>
                    Search Results
                    <?php if (!empty($search_query)): ?>
                        for &ldquo;<?php echo htmlspecialchars($search_query); ?>&rdquo;
                    <?php endif; ?>
                </h3>
                <span class="results-count"><?php echo count($search_results); ?> found</span>
            </div>

            <?php if (!empty($search_results)): ?>
            <div class="results-table-wrapper">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Email</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results as $student): ?>
                        <tr>
                            <td class="td-id"><?php echo htmlspecialchars($student['id_number']); ?></td>
                            <td class="td-name">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'][0] . '. ' : '') . $student['last_name']); ?>
                            </td>
                            <td><span class="badge-course"><?php echo htmlspecialchars($student['course']); ?></span></td>
                            <td><span class="badge-year">Year <?php echo htmlspecialchars($student['year_level']); ?></span></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['address'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <h3>No students found</h3>
                <p>No results matched &ldquo;<?php echo htmlspecialchars($search_query); ?>&rdquo;. Try a different keyword.</p>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>