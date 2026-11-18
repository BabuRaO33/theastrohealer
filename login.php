<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();

// If already logged in, redirect to respective dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    } else {
        header('Location: ' . BASE_URL . '/student/index.php');
        exit;
    }
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = trim($_POST['redirect'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'suspended') {
                    $error = 'Your account has been deactivated. Please contact support.';
                    require_once __DIR__ . '/includes/audit.php';
                    logAuditTrail('LOGIN_BLOCKED', 'auth', "Suspended user attempted login: {$email}", 'danger', $user['id'], $email, $user['name']);
                } else {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    // Audit Log
                    require_once __DIR__ . '/includes/audit.php';
                    logAuditTrail('USER_LOGIN', 'auth', "User logged into academy portal ({$user['role']})", 'success', $user['id'], $user['email'], $user['name']);

                    setFlash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

                    if (!empty($redirect)) {
                        header('Location: ' . BASE_URL . '/' . ltrim($redirect, '/'));
                        exit;
                    }

                    if ($user['role'] === 'admin') {
                        header('Location: ' . BASE_URL . '/admin/index.php');
                    } else {
                        header('Location: ' . BASE_URL . '/student/index.php');
                    }
                    exit;
                }
            } else {
                $error = 'Invalid email address or password.';
                require_once __DIR__ . '/includes/audit.php';
                logAuditTrail('LOGIN_FAILED', 'auth', "Failed login attempt for: {$email}", 'warning', null, $email);
            }

        } catch (\PDOException $e) {
            $error = 'Database connection error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$pageTitle = 'Login — The Astro Healer Portal';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: calc(100vh - 120px); display: flex; align-items: center; justify-content: center; padding: 7rem 1.5rem 4rem; position: relative; z-index: 1;">
  <div style="width: 100%; max-width: 440px; background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 2.8rem 2.2rem; backdrop-filter: blur(16px); box-shadow: 0 20px 50px rgba(0,0,0,0.8); position: relative;">
    
    <div style="text-align: center; margin-bottom: 2rem;">
      <a href="<?= BASE_URL ?>/index.php">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="The Astro Healer" style="height: 70px; width: 70px; border-radius: 50%; border: 2px solid var(--gold); box-shadow: 0 0 20px var(--gold-glow); margin-bottom: 1rem;">
      </a>
      <h2 style="font-size: 1.6rem; color: #fff; margin-bottom: 0.3rem;">Sign In</h2>
      <p style="font-size: 0.82rem; color: var(--dash-muted); letter-spacing: 0.1em; text-transform: uppercase;">Access Academy & Consultations</p>
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
        <label class="form-label">Email Address</label>
        <div style="position: relative;">
          <input type="email" name="email" class="form-control" required placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div style="position: relative;">
          <input type="password" name="password" class="form-control" required placeholder="••••••••">
        </div>
      </div>

      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; font-size: 0.82rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--dash-muted);">
          <input type="checkbox" name="remember" checked style="accent-color: var(--gold);"> Remember me
        </label>
        <a href="<?= BASE_URL ?>/index.php#booking" style="color: var(--gold);">Need Help?</a>
      </div>

      <button type="submit" class="btn-primary" style="width: 100%; margin-bottom: 1.5rem;">
        <i class="fa-solid fa-arrow-right-to-bracket"></i> Login to Portal
      </button>
    </form>

    <div style="text-align: center; font-size: 0.85rem; color: var(--dash-muted); padding-top: 1rem; border-top: 1px solid rgba(212,175,55,0.15);">
      Don't have an account yet? 
      <a href="<?= BASE_URL ?>/signup.php<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" style="color: var(--gold-bright); font-weight: 600;">Sign Up Here</a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
