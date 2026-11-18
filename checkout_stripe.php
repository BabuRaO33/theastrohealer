<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/stripe.php';

$pdo = getDBConnection();
$courseId = intval($_GET['course_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    setFlash('error', 'Course not found.');
    header('Location: ' . BASE_URL . '/index.php#courses');
    exit;
}

if (!isLoggedIn()) {
    setFlash('info', 'Please sign in or create an account to enroll in this course.');
    header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode('course.php?slug=' . $course['slug']));
    exit;
}

$currentUser = getCurrentUser();

// Check if already active
if (hasCourseAccess($currentUser['id'], $course['id'])) {
    setFlash('info', 'You already have active access to this course!');
    header('Location: ' . BASE_URL . '/student/classroom.php?course_id=' . $course['id']);
    exit;
}

$successUrl = BASE_URL . '/stripe_success.php?course_id=' . $course['id'];
$cancelUrl = BASE_URL . '/course.php?slug=' . $course['slug'];

$checkout = createStripeCheckoutSession($course, $currentUser, $successUrl, $cancelUrl);

if ($checkout['success'] && !empty($checkout['url'])) {
    header('Location: ' . $checkout['url']);
    exit;
} else {
    setFlash('error', 'Unable to initiate Stripe checkout. Please try again or request manual access.');
    header('Location: ' . BASE_URL . '/course.php?slug=' . $course['slug']);
    exit;
}
