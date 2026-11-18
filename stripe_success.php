<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$courseId = intval($_GET['course_id'] ?? 0);
$sessionId = trim($_GET['session_id'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    setFlash('info', 'Please sign in to view your enrolled course.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Update or Insert Enrollment
$check = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
$check->execute([$currentUser['id'], $course['id']]);
$enrollment = $check->fetch();

$isNewPayment = true;
if ($enrollment) {
    if ($enrollment['status'] === 'active' && $enrollment['stripe_payment_intent'] === $sessionId) {
        $isNewPayment = false;
    }
    $upd = $pdo->prepare("UPDATE enrollments SET status = 'active', access_type = 'paid_stripe', stripe_payment_intent = ?, amount_paid = ?, approved_at = NOW() WHERE id = ?");
    $upd->execute([$sessionId, $course['price'], $enrollment['id']]);
} else {
    $ins = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status, access_type, stripe_payment_intent, amount_paid, approved_at) VALUES (?, ?, 'active', 'paid_stripe', ?, ?, NOW())");
    $ins->execute([$currentUser['id'], $course['id'], $sessionId, $course['price']]);
}

// Send Admin Notification (Email to admin@theastrohealer.com & WhatsApp)
if ($isNewPayment) {
    require_once __DIR__ . '/includes/notifications.php';
    try {
        sendAdminPaymentAlert('course', [
            'name' => $currentUser['name'],
            'email' => $currentUser['email'],
            'item_title' => $course['title'],
            'amount' => $course['price'],
            'session_id' => $sessionId
        ]);

        require_once __DIR__ . '/includes/audit.php';
        logAuditTrail('COURSE_ENROLLED_STRIPE', 'payment', "Student enrolled in {$course['title']} (\${$course['price']}) via Stripe [Session: {$sessionId}]", 'success', $currentUser['id'], $currentUser['email'], $currentUser['name']);
    } catch (\Throwable $e) {

        error_log('Notification error: ' . $e->getMessage());
    }
}

setFlash('success', 'Payment successful! You now have lifetime access to ' . $course['title']);
header('Location: ' . BASE_URL . '/student/classroom.php?course_id=' . $course['id']);
exit;

