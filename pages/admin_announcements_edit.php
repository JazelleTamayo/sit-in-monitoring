<?php
session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Edit Announcement - CCS Sit-in System";
$basePath = "../";

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->execute([$id]);
$announcement = $stmt->fetch();

if (!$announcement) {
    header("Location: admin_announcements.php?error=notfound");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($title)) {
        $error = "Title is required";
    } elseif (empty($content)) {
        $error = "Content is required";
    } else {
        $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $content, $is_active, $id]);
        header("Location: admin_announcements.php?msg=updated");
        exit();
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
.edit-container {
    min-height: 100vh;
    padding: 1.5rem 24px 48px;
    position: relative;
}

.edit-container::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 5%   0%,  rgba(37,99,235,0.35)  0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%);
    pointer-events: none;
    z-index: -1;
}

.edit-main {
    max-width: 800px;
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

.form-card {
    background: rgba(10,18,40,0.82);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 20px;
    backdrop-filter: blur(24px);
    padding: 30px;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.form-group label i {
    margin-right: 5px;
    color: #60a5fa;
}

.form-control {
    width: 100%;
    padding: 13px 16px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    color: #f1f5f9;
    font-size: 0.92rem;
    font-family: inherit;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: rgba(96,165,250,0.45);
    background: rgba(255,255,255,0.08);
}

textarea.form-control {
    resize: vertical;
    min-height: 200px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.checkbox-group input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-group span {
    color: #cbd5e1;
    font-size: 0.9rem;
    cursor: pointer;
}

.btn-row {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 20px;
}

.btn-submit {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    border: none;
    padding: 14px 32px;
    border-radius: 999px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37,99,235,0.4);
}

.btn-cancel {
    background: rgba(255,255,255,0.07);
    color: #94a3b8;
    text-decoration: none;
    padding: 14px 32px;
    border-radius: 999px;
    font-size: 0.95rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(255,255,255,0.08);
    transition: all 0.25s ease;
}

.btn-cancel:hover {
    background: rgba(255,255,255,0.12);
    color: #f1f5f9;
}

.alert {
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-error {
    background: rgba(239,68,68,0.1);
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.2);
}

.help-text {
    color: #475569;
    font-size: 0.7rem;
    margin-top: 5px;
}
</style>

<div class="edit-container">
    <div class="edit-main">
        
        <div class="page-header">
            <h1><i class="fas fa-edit" style="color:#0ea5e9;margin-right:8px;"></i>Edit Announcement</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Announcement Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Announcement Content</label>
                    <textarea name="content" class="form-control" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" name="is_active" <?php echo $announcement['is_active'] ? 'checked' : ''; ?>>
                        <span><i class="fas fa-eye"></i> Active (visible to students)</span>
                    </label>
                    <div class="help-text">If unchecked, this announcement will be hidden from students</div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Announcement
                    </button>
                    <a href="admin_announcements.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>