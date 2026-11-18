<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireStudent();
$pdo = getDBConnection();
$currentUser = getCurrentUser();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($name)) {
        $error = 'Name cannot be empty.';
    } else {
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $hash, $currentUser['id']]);
                $_SESSION['user_name'] = $name;
                $success = true;
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $currentUser['id']]);
            $_SESSION['user_name'] = $name;
            $success = true;
        }

        if ($success) {
            setFlash('success', 'Profile details updated successfully!');
            header('Location: ' . BASE_URL . '/student/profile.php');
            exit;
        }
    }
}

$pageTitle = 'Student Profile';
$studentPage = 'profile';
require_once __DIR__ . '/includes/student_header.php';
?>

<div style="max-width: 650px; margin: 0 auto;">
  <div style="margin-bottom: 2rem;">
    <h2 style="font-size:1.6rem; color:#fff; margin-bottom:0.3rem;">Student Profile & Credentials</h2>
    <p style="font-size:0.85rem; color:var(--dash-muted);">Update your personal information and account security</p>
  </div>

  <?php if ($error): ?>
    <div class="alert-box alert-danger">
      <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <div class="dash-panel">
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email Address (Account Identifier)</label>
        <input type="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" readonly style="opacity: 0.7; cursor: not-allowed;">
        <small style="color:var(--dash-muted); font-size:0.75rem; margin-top:0.3rem; display:block;">Email address cannot be changed once registered.</small>
      </div>

      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($currentUser['name']) ?>">
      </div>

      <div class="form-group">
        <label class="form-label">WhatsApp / Contact Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="+92 300 1234567">
      </div>

      <div style="padding-top: 1.5rem; margin-top: 1.5rem; border-top: 1px solid rgba(212,175,55,0.15);">
        <h4 style="font-size: 1.1rem; color: #fff; margin-bottom: 0.4rem;">Change Password</h4>
        <p style="font-size: 0.8rem; color: var(--dash-muted); margin-bottom: 1.2rem;">Leave blank if you do not wish to change your password.</p>

        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters">
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
          </div>
        </div>
      </div>

      <button type="submit" class="btn-primary" style="margin-top: 1rem;">
        <i class="fa-solid fa-floppy-disk"></i> Update Profile
      </button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/student_footer.php'; ?>
