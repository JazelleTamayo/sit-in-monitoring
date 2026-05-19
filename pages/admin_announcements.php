<?php
session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Manage Announcements - CCS Sit-in System";
$basePath = "../";

// Handle delete request
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_announcements.php?msg=deleted");
    exit();
}

// Handle toggle active status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_announcements.php?msg=toggled");
    exit();
}

// Fetch all announcements
$stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
.announcements-container {
    min-height: 100vh;
    padding: 1.5rem 32px 48px 32px;
    position: relative;
}

.announcements-container::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 5%   0%,  rgba(37,99,235,0.35)  0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%);
    pointer-events: none;
    z-index: -1;
}

.announcements-main {
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

.btn-add {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    padding: 12px 24px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
    margin-bottom: 25px;
}
.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37,99,235,0.4);
}

.announcement-card {
    background: rgba(10,18,40,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 16px;
    backdrop-filter: blur(24px);
    margin-bottom: 20px;
    overflow: hidden;
    transition: all 0.25s ease;
}

.announcement-card:hover {
    border-color: rgba(14,165,233,0.45);
    transform: translateY(-2px);
}

.announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    background: rgba(37,99,235,0.15);
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.announcement-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0;
}

.announcement-meta {
    display: flex;
    gap: 15px;
    align-items: center;
    color: #64748b;
    font-size: 0.8rem;
    flex-wrap: wrap;
}

.announcement-meta i {
    margin-right: 4px;
    color: #60a5fa;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-active {
    background: rgba(16,185,129,0.15);
    color: #6ee7b7;
    border: 1px solid rgba(16,185,129,0.25);
}

.status-inactive {
    background: rgba(239,68,68,0.15);
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.25);
}

.announcement-body {
    padding: 20px 24px;
    color: #cbd5e1;
    line-height: 1.6;
}

.announcement-actions {
    display: flex;
    gap: 10px;
    padding: 12px 24px 20px;
    border-top: 1px solid rgba(255,255,255,0.05);
}

.btn-edit, .btn-delete, .btn-toggle {
    padding: 8px 20px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
}

.btn-edit {
    background: rgba(96,165,250,0.15);
    color: #60a5fa;
    border: 1px solid rgba(96,165,250,0.25);
}

.btn-edit:hover {
    background: rgba(96,165,250,0.25);
    transform: translateY(-1px);
}

.btn-delete {
    background: rgba(239,68,68,0.15);
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.25);
}

.btn-delete:hover {
    background: rgba(239,68,68,0.25);
    transform: translateY(-1px);
}

.btn-toggle {
    background: rgba(96,165,250,0.15);
    color: #60a5fa;
    border: 1px solid rgba(96,165,250,0.25);
}

.btn-toggle:hover {
    background: rgba(96,165,250,0.25);
    transform: translateY(-1px);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: rgba(10,18,40,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 16px;
}

.empty-state i {
    font-size: 3rem;
    color: #475569;
    margin-bottom: 16px;
}

.empty-state h3 {
    color: #f1f5f9;
    margin-bottom: 8px;
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

@media (max-width: 768px) {
    .announcements-container {
        padding: 80px 16px 40px;
    }
    .announcement-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    .page-header {
        flex-direction: column;
        gap: 15px;
    }
    .announcement-meta {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="announcements-container">
    <div class="announcements-main">
        
        <div class="page-header">
            <h1><i class="fas fa-bullhorn" style="color:#0ea5e9;margin-right:8px;"></i>Manage Announcements</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                    if ($_GET['msg'] == 'added') echo "Announcement added successfully!";
                    if ($_GET['msg'] == 'updated') echo "Announcement updated successfully!";
                    if ($_GET['msg'] == 'deleted') echo "Announcement deleted successfully!";
                    if ($_GET['msg'] == 'toggled') echo "Announcement status updated!";
                ?>
            </div>
        <?php endif; ?>

        <a href="admin_announcements_add.php" class="btn-add">
            <i class="fas fa-plus-circle"></i> Create New Announcement
        </a>

        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <h3>No Announcements Yet</h3>
                <p>Click the button above to create your first announcement</p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $ann): ?>
                <div class="announcement-card">
                    <div class="announcement-header">
                        <h3 class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></h3>
                        <div class="announcement-meta">
                            <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($ann['author']); ?></span>
                            <span><i class="far fa-calendar-alt"></i> <?php echo date('M j, Y g:i A', strtotime($ann['created_at'])); ?></span>
                            <span class="status-badge <?php echo $ann['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <i class="fas <?php echo $ann['is_active'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                <?php echo $ann['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="announcement-body">
                        <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                    </div>
                    <div class="announcement-actions">
                        <a href="admin_announcements_edit.php?id=<?php echo $ann['id']; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="?toggle=<?php echo $ann['id']; ?>" class="btn-toggle" onclick="return confirm('Toggle announcement status?')">
                            <i class="fas <?php echo $ann['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                            <?php echo $ann['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </a>
                        <a href="?delete=<?php echo $ann['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this announcement?')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>