<?php
require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isStudent() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    static $currentUser = null;
    if ($currentUser !== null) {
        return $currentUser;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status, avatar, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    return $currentUser;
}

function requireLogin($redirect = 'login.php') {
    if (!isLoggedIn()) {
        setFlash('error', 'Please login to access this area.');
        header('Location: ' . BASE_URL . '/' . ltrim($redirect, '/'));
        exit;
    }
}

function requireAdmin($redirect = 'login.php') {
    if (!isAdmin()) {
        setFlash('error', 'Administrator access required.');
        header('Location: ' . BASE_URL . '/' . ltrim($redirect, '/'));
        exit;
    }
}

function requireStudent($redirect = 'login.php') {
    if (!isLoggedIn()) {
        setFlash('error', 'Please login to continue.');
        header('Location: ' . BASE_URL . '/' . ltrim($redirect, '/'));
        exit;
    }
}

function hasCourseAccess($userId, $courseId) {
    // Admin has access to all courses
    if (isAdmin()) {
        return true;
    }
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT status FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'");
    $stmt->execute([$userId, $courseId]);
    return (bool)$stmt->fetch();
}

function getCourseEnrollmentStatus($userId, $courseId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$userId, $courseId]);
    return $stmt->fetch();
}

function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function displayFlash() {
    $flash = getFlash();
    if ($flash) {
        $typeClass = $flash['type'] === 'success' ? 'alert-success' : ($flash['type'] === 'error' ? 'alert-danger' : 'alert-info');
        echo '<div class="alert-box ' . $typeClass . '"><i class="fa-solid fa-circle-info"></i> ' . htmlspecialchars($flash['message']) . '</div>';
    }
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function formatPrice($amount) {
    return SITE_CURRENCY_SYMBOL . number_format((float)$amount, 2);
}
