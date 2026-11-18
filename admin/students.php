<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();
$pdo = getDBConnection();

// Handle Status Toggle (activate/suspend)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'suspend') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role != 'admin'");
        $stmt->execute([$id]);
        setFlash('info', 'User account suspended.');
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'User account activated.');
    }
    header('Location: ' . BASE_URL . '/admin/students.php');
    exit;
}

// Handle Add / Reset User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? 'student123';
        $role = $_POST['role'] ?? 'student';

        if (empty($name) || empty($email)) {
            setFlash('error', 'Name and email are required.');
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                setFlash('error', 'A user with this email already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $ins->execute([$name, $email, $phone, $hash, $role]);
                setFlash('success', 'User created successfully with password: ' . htmlspecialchars($password));
            }
        }
    } elseif ($_POST['action'] === 'reset_password') {
        $userId = intval($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';

        if (!empty($newPass) && $userId > 0) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
            setFlash('success', 'Password reset successfully for student.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/students.php');
    exit;
}

// Fetch all users with enrolled courses
$users = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM enrollments WHERE user_id = u.id AND status = 'active') as active_courses,
           (SELECT GROUP_CONCAT(c.title SEPARATOR ', ') 
            FROM enrollments e 
            JOIN courses c ON e.course_id = c.id 
            WHERE e.user_id = u.id AND e.status = 'active') as enrolled_titles
    FROM users u
    ORDER BY u.id DESC
")->fetchAll();

$pageTitle = 'Students & Registered Users';
$adminPage = 'students';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; color:#fff; margin-bottom:0.3rem;">Students & User Accounts</h2>
    <p style="font-size:0.85rem; color:var(--dash-muted);">Manage academy students, enrollment statuses, and login credentials</p>
  </div>
  <button type="button" onclick="document.getElementById('add-user-modal').classList.add('open')" class="btn-primary">
    <i class="fa-solid fa-user-plus"></i> Add New User
  </button>
</div>

<div class="dash-panel">
  <div class="custom-table-wrap">
    <table class="custom-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>Status</th>
          <th>Enrolled Courses</th>
          <th>Joined Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="display:flex; align-items:center; gap:0.8rem;">
                <div class="user-avatar-small"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                <div>
                  <strong style="color:#fff;"><?= htmlspecialchars($u['name']) ?></strong><br>
                  <small style="color:var(--dash-muted);"><?= htmlspecialchars($u['email']) ?></small><br>
                  <small style="color:var(--dash-gold);"><?= htmlspecialchars($u['phone'] ?? 'No phone') ?></small>
                </div>
              </div>
            </td>
            <td>
              <span class="badge" style="background:rgba(212,175,55,0.1); border:1px solid var(--gold); color:var(--gold-bright);">
                <?= strtoupper($u['role']) ?>
              </span>
            </td>
            <td>
              <?php if ($u['status'] === 'active'): ?>
                <span class="badge badge-active"><i class="fa-solid fa-circle-check"></i> Active</span>
              <?php else: ?>
                <span class="badge badge-danger"><i class="fa-solid fa-ban"></i> Suspended</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['active_courses'] > 0): ?>
                <span class="badge badge-active"><?= $u['active_courses'] ?> Active</span>
                <br><small style="color:var(--dash-muted); font-size:0.75rem;"><?= htmlspecialchars(substr($u['enrolled_titles'], 0, 40)) ?>...</small>
              <?php else: ?>
                <span style="color:var(--dash-muted); font-size:0.8rem;">None</span>
              <?php endif; ?>
            </td>
            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div style="display:flex; gap:0.4rem;">
                <a href="<?= BASE_URL ?>/admin/requests.php" class="btn-action btn-approve" title="Grant Course to this User">
                  <i class="fa-solid fa-key"></i> Grant
                </a>
                <button type="button" class="btn-action topbar-btn" onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')" title="Reset Password">
                  <i class="fa-solid fa-lock"></i>
                </button>
                <?php if ($u['role'] !== 'admin'): ?>
                  <?php if ($u['status'] === 'active'): ?>
                    <a href="<?= BASE_URL ?>/admin/students.php?action=suspend&id=<?= $u['id'] ?>" class="btn-action btn-revoke" onclick="return confirm('Suspend this user account?');" title="Suspend Account">
                      <i class="fa-solid fa-user-slash"></i>
                    </a>
                  <?php else: ?>
                    <a href="<?= BASE_URL ?>/admin/students.php?action=activate&id=<?= $u['id'] ?>" class="btn-action btn-approve" title="Activate Account">
                      <i class="fa-solid fa-user-check"></i>
                    </a>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add New User -->
<div class="modal-backdrop" id="add-user-modal">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('add-user-modal').classList.remove('open')">&times;</button>
    <h3 style="font-family: var(--font-serif); font-size: 1.3rem; color: var(--dash-gold-bright); margin-bottom: 0.5rem;">
      <i class="fa-solid fa-user-plus"></i> Add New User / Student
    </h3>
    <p style="font-size: 0.85rem; color: var(--dash-muted); margin-bottom: 1.5rem;">
      Create an account for a student or administrator directly.
    </p>

    <form method="POST">
      <input type="hidden" name="action" value="add_user">

      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input type="text" name="name" class="form-control" required placeholder="e.g. Tariq Khan">
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" required placeholder="user@example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="+92 300 1234567">
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Initial Password *</label>
          <input type="text" name="password" class="form-control" value="student123" required>
        </div>
        <div class="form-group">
          <label class="form-label">Account Role</label>
          <select name="role" class="form-control">
            <option value="student" selected>Student</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
      </div>

      <button type="submit" class="btn-primary" style="width: 100%;">
        Create Account
      </button>
    </form>
  </div>
</div>

<!-- Modal: Reset Password -->
<div class="modal-backdrop" id="reset-password-modal">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('reset-password-modal').classList.remove('open')">&times;</button>
    <h3 style="font-family: var(--font-serif); font-size: 1.3rem; color: var(--dash-gold-bright); margin-bottom: 0.5rem;">
      <i class="fa-solid fa-lock"></i> Reset Student Password
    </h3>
    <p style="font-size: 0.85rem; color: var(--dash-muted); margin-bottom: 1.5rem;" id="reset-user-text">
      Enter a new password for student.
    </p>

    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset-user-id" value="0">

      <div class="form-group">
        <label class="form-label">New Password *</label>
        <input type="text" name="new_password" class="form-control" required placeholder="Enter new password">
      </div>

      <button type="submit" class="btn-primary" style="width: 100%;">
        Update Password
      </button>
    </form>
  </div>
</div>

<script>
  function openResetModal(userId, userName) {
    document.getElementById('reset-user-id').value = userId;
    document.getElementById('reset-user-text').innerText = 'Setting new password for: ' + userName;
    document.getElementById('reset-password-modal').classList.add('open');
  }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
