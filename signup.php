<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/student/index.php');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

// Fetch published courses for optional enrollment selection
$courseStmt = $pdo->query("SELECT id, title, price FROM courses WHERE status = 'published' ORDER BY price DESC");
$availableCourses = $courseStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $selectedCourseId = intval($_POST['course_interest'] ?? 0);
    $signupNote = trim($_POST['signup_note'] ?? '');
    $redirect = trim($_POST['redirect'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already registered
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $error = 'This email is already registered. Please login instead.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $insertStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'student', 'active')");
            
            if ($insertStmt->execute([$name, $email, $phone, $hashedPassword])) {
                $newUserId = $pdo->lastInsertId();

                // If user selected a course during signup, create pending enrollment request for admin
                if ($selectedCourseId > 0) {
                    $note = !empty($signupNote) ? $signupNote : 'Signed up requesting this course.';
                    $reqStmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status, access_type, request_note) VALUES (?, ?, 'pending', 'manual_request', ?)");
                    $reqStmt->execute([$newUserId, $selectedCourseId, $note]);
                }

                // Log the new user in
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'student';

                require_once __DIR__ . '/includes/audit.php';
                logAuditTrail('STUDENT_SIGNUP', 'auth', "New student account created: {$name} ({$email})", 'success', $newUserId, $email, $name);

                setFlash('success', 'Welcome to The Astro Healer, ' . htmlspecialchars($name) . '! Your account is active.');


                if (!empty($redirect)) {
                    header('Location: ' . BASE_URL . '/' . ltrim($redirect, '/'));
                    exit;
                }

                header('Location: ' . BASE_URL . '/student/index.php');
                exit;
            } else {
                $error = 'Registration failed due to a system error. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign Up — The Astro Healer Academy';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: calc(100vh - 120px); display: flex; align-items: center; justify-content: center; padding: 7rem 1.5rem 4rem; position: relative; z-index: 1;">
  <div style="width: 100%; max-width: 520px; background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 2.8rem 2.2rem; backdrop-filter: blur(16px); box-shadow: 0 20px 50px rgba(0,0,0,0.8); position: relative;">
    
    <div style="text-align: center; margin-bottom: 2rem;">
      <a href="<?= BASE_URL ?>/index.php">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="The Astro Healer" style="height: 70px; width: 70px; border-radius: 50%; border: 2px solid var(--gold); box-shadow: 0 0 20px var(--gold-glow); margin-bottom: 1rem;">
      </a>
      <h2 style="font-size: 1.6rem; color: #fff; margin-bottom: 0.3rem;">Create Student Account</h2>
      <p style="font-size: 0.82rem; color: var(--dash-muted); letter-spacing: 0.1em; text-transform: uppercase;">Join Our Global Celestial Academy</p>
    </div>

    <?php displayFlash(); ?>
    <?php if ($error): ?>
      <div class="alert-box alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" name="name" class="form-control" required placeholder="e.g. Ayesha Khan" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" required placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone / WhatsApp</label>
          <input type="text" name="phone" class="form-control" placeholder="+92 300 1234567" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Select Course to Request Access (Optional)</label>
        <select name="course_interest" class="form-control">
          <option value="0">Browse First / Select Later</option>
          <?php foreach ($availableCourses as $ac): ?>
            <option value="<?= $ac['id'] ?>" <?= (isset($_POST['course_interest']) && $_POST['course_interest'] == $ac['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($ac['title']) ?> (<?= formatPrice($ac['price']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <small style="font-size: 0.75rem; color: var(--dash-muted); margin-top: 0.3rem; display: block;">
          Choosing a course sends an immediate enrollment request to Admin Malik Mehboob.
        </small>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" required placeholder="Min. 6 characters">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password *</label>
          <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat password">
        </div>
      </div>

      <button type="submit" class="btn-primary" style="width: 100%; margin-bottom: 1.5rem; margin-top: 0.5rem;">
        <i class="fa-solid fa-user-plus"></i> Create Account & Enter Academy
      </button>
    </form>

    <div style="text-align: center; font-size: 0.85rem; color: var(--dash-muted); padding-top: 1rem; border-top: 1px solid rgba(212,175,55,0.15);">
      Already have an account? 
      <a href="<?= BASE_URL ?>/login.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" style="color: var(--gold-bright); font-weight: 600;">Sign In</a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
