<?php
// ─── includes/get_announcements.php ───────────────────────────────────
// This file fetches announcements and notifications from database

// Get PDO connection
function getDB() {
    // Check if we already have a global PDO connection
    global $pdo;
    
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }
    
    // If not, include database config which creates $pdo
    require_once __DIR__ . '/../config/database.php';
    
    // Return the global $pdo
    global $pdo;
    return $pdo;
}

// ============ ANNOUNCEMENT FUNCTIONS ============

function getLatestAnnouncements($limit = 5) {
    $pdo = getDB();
    
    $query = "SELECT * FROM announcements 
              WHERE is_active = 1 
              ORDER BY created_at DESC 
              LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSingleAnnouncement($id) {
    $pdo = getDB();
    
    $query = "SELECT * FROM announcements WHERE id = :id AND is_active = 1";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUnreadAnnouncementCount($user_id) {
    $pdo = getDB();
    
    // Create announcement_reads table if not exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `announcement_reads` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `announcement_id` int(11) NOT NULL,
            `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_read` (`user_id`, `announcement_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        // Table might already exist - continue
    }
    
    $query = "SELECT COUNT(*) as unread 
              FROM announcements a 
              WHERE a.is_active = 1 
              AND NOT EXISTS (
                  SELECT 1 FROM announcement_reads ar 
                  WHERE ar.announcement_id = a.id 
                  AND ar.user_id = :user_id
              )";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['unread'] ?? 0;
}

function markAnnouncementAsRead($user_id, $announcement_id) {
    $pdo = getDB();
    
    $query = "INSERT IGNORE INTO announcement_reads (user_id, announcement_id) 
              VALUES (:user_id, :announcement_id)";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':announcement_id', $announcement_id);
    return $stmt->execute();
}

// ============ NOTIFICATION FUNCTIONS ============

function addNotification($user_id, $type, $title, $message, $link = null) {
    $pdo = getDB();
    
    $query = "INSERT INTO notifications (user_id, type, title, message, link) 
              VALUES (:user_id, :type, :title, :message, :link)";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':type', $type);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':link', $link);
    return $stmt->execute();
}

function getUserNotifications($user_id, $limit = 20) {
    $pdo = getDB();
    
    $query = "SELECT * FROM notifications 
              WHERE user_id = :user_id 
              ORDER BY created_at DESC 
              LIMIT :limit";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUnreadNotificationCount($user_id) {
    $pdo = getDB();
    
    $query = "SELECT COUNT(*) as unread FROM notifications 
              WHERE user_id = :user_id AND is_read = 0";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['unread'] ?? 0;
}

function markNotificationAsRead($notification_id, $user_id) {
    $pdo = getDB();
    
    $query = "UPDATE notifications SET is_read = 1 
              WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $notification_id);
    $stmt->bindParam(':user_id', $user_id);
    return $stmt->execute();
}

function markAllNotificationsAsRead($user_id) {
    $pdo = getDB();
    
    $query = "UPDATE notifications SET is_read = 1 
              WHERE user_id = :user_id AND is_read = 0";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    return $stmt->execute();
}

function deleteNotification($id, $user_id) {
    $pdo = getDB();
    
    $query = "DELETE FROM notifications WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':user_id', $user_id);
    return $stmt->execute();
}

// ============ HELPER FUNCTIONS ============

function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}
?>