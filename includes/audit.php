<?php
/**
 * Audit Trail & Security Logging System
 * Tracks User Activity, Security Events, Financial Transactions, and Astrobot Sessions
 * The Astro Healer
 */

require_once __DIR__ . '/db.php';

/**
 * Initializes the audit_trail table if it does not already exist
 */
function initAuditTrailTable() {
    static $initialized = false;
    if ($initialized) return;

    try {
        $pdo = getDBConnection();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `audit_trail` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT DEFAULT NULL,
                `user_email` VARCHAR(150) DEFAULT NULL,
                `user_name` VARCHAR(100) DEFAULT NULL,
                `role` VARCHAR(50) DEFAULT 'guest',
                `action` VARCHAR(100) NOT NULL,
                `category` ENUM('auth', 'course', 'payment', 'booking', 'bot', 'admin', 'system') DEFAULT 'system',
                `details` TEXT DEFAULT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(255) DEFAULT NULL,
                `status` ENUM('success', 'info', 'warning', 'danger') DEFAULT 'info',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_category` (`category`),
                INDEX `idx_action` (`action`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $initialized = true;
    } catch (\Throwable $e) {
        error_log('Audit table init error: ' . $e->getMessage());
    }
}

/**
 * Logs an activity event to the audit trail
 * 
 * @param string $action Event identifier (e.g. 'USER_LOGIN', 'BOT_QUESTION_ASKED', 'COURSE_PAID')
 * @param string $category 'auth', 'course', 'payment', 'booking', 'bot', 'admin', 'system'
 * @param string $details Human readable description of what occurred
 * @param string $status 'success', 'info', 'warning', 'danger'
 * @param int|null $userId Optional user ID
 * @param string|null $userEmail Optional user email
 * @param string|null $userName Optional user name
 */
function logAuditTrail($action, $category = 'system', $details = '', $status = 'info', $userId = null, $userEmail = null, $userName = null) {
    initAuditTrailTable();

    try {
        $pdo = getDBConnection();

        // Auto-detect current session user if not passed
        if (session_status() === PHP_SESSION_ACTIVE) {
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }
            if ($userEmail === null && isset($_SESSION['user_email'])) {
                $userEmail = $_SESSION['user_email'];
            }
            if ($userName === null && isset($_SESSION['user_name'])) {
                $userName = $_SESSION['user_name'];
            }
            $role = $_SESSION['user_role'] ?? 'guest';
        } else {
            $role = 'guest';
        }

        // Detect IP
        $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent', 0, 250);

        $stmt = $pdo->prepare("
            INSERT INTO `audit_trail` 
            (`user_id`, `user_email`, `user_name`, `role`, `action`, `category`, `details`, `ip_address`, `user_agent`, `status`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $userEmail,
            $userName,
            $role,
            $action,
            $category,
            $details,
            $ip,
            $userAgent,
            $status
        ]);
    } catch (\Throwable $e) {
        error_log('Audit log failure: ' . $e->getMessage());
    }
}

/**
 * Fetches audit logs with filtering options
 */
function getAuditLogs($limit = 60, $category = '', $search = '') {
    initAuditTrailTable();
    try {
        $pdo = getDBConnection();
        $sql = "SELECT * FROM `audit_trail` WHERE 1=1";
        $params = [];

        if (!empty($category)) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        if (!empty($search)) {
            $sql .= " AND (action LIKE ? OR details LIKE ? OR user_email LIKE ? OR user_name LIKE ? OR ip_address LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY created_at DESC LIMIT " . intval($limit);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        error_log('Fetch audit logs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get quick count of audit trail events today
 */
function getAuditStats() {
    initAuditTrailTable();
    try {
        $pdo = getDBConnection();
        $totalLogs = $pdo->query("SELECT COUNT(*) FROM `audit_trail`")->fetchColumn();
        $todayLogs = $pdo->query("SELECT COUNT(*) FROM `audit_trail` WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $securityAlerts = $pdo->query("SELECT COUNT(*) FROM `audit_trail` WHERE status IN ('warning', 'danger')")->fetchColumn();
        $botInteractions = $pdo->query("SELECT COUNT(*) FROM `audit_trail` WHERE category = 'bot'")->fetchColumn();

        return [
            'total' => (int)$totalLogs,
            'today' => (int)$todayLogs,
            'alerts' => (int)$securityAlerts,
            'bot_events' => (int)$botInteractions
        ];
    } catch (\Throwable $e) {
        return ['total' => 0, 'today' => 0, 'alerts' => 0, 'bot_events' => 0];
    }
}
