<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();
$pdo = getDBConnection();

// Handle Actions (approve, revoke, activate, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve' || $action === 'activate') {
        $stmt = $pdo->prepare("UPDATE enrollments SET status = 'active', approved_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Course access activated successfully!');
    } elseif ($action === 'revoke') {
        $stmt = $pdo->prepare("UPDATE enrollments SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('info', 'Course access revoked.');
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Enrollment record removed.');
    }
    header('Location: ' . BASE_URL . '/admin/requests.php');
    exit;
}

// Handle Direct Grant Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grant_access') {
    $email = trim($_POST['email'] ?? '');
    $courseId = intval($_POST['course_id'] ?? 0);
    $note = trim($_POST['note'] ?? 'Admin Manual Grant');

    if (empty($email) || empty($courseId)) {
        setFlash('error', 'Please provide student email and select course.');
    } else {
        $uStmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $uStmt->execute([$email]);
        $user = $uStmt->fetch();

        if (!$user) {
            setFlash('error', 'User with email "' . htmlspecialchars($email) . '" does not exist. Student must register an account first.');
        } else {
            $check = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
            $check->execute([$user['id'], $courseId]);
            $existing = $check->fetch();

            if ($existing) {
                $upd = $pdo->prepare("UPDATE enrollments SET status = 'active', access_type = 'admin_granted', request_note = ?, approved_at = NOW() WHERE id = ?");
                $upd->execute([$note, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status, access_type, request_note, approved_at) VALUES (?, ?, 'active', 'admin_granted', ?, NOW())");
                $ins->execute([$user['id'], $courseId, $note]);
            }
            setFlash('success', 'Course access granted to ' . htmlspecialchars($user['name']) . ' (' . htmlspecialchars($email) . ')!');
        }
    }
    header('Location: ' . BASE_URL . '/admin/requests.php');
    exit;
}

// Filter
$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT e.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
           c.title as course_title, c.price as course_price
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
";

if ($statusFilter !== 'all') {
    $sql .= " WHERE e.status = " . $pdo->quote($statusFilter);
}
$sql .= " ORDER BY e.enrolled_at DESC";

$enrollments = $pdo->query($sql)->fetchAll();
$allCourses = $pdo->query("SELECT id, title, price FROM courses WHERE status = 'published'")->fetchAll();

$pageTitle = 'Course Access & Enrollment Grants';
$adminPage = 'requests';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; color:#fff; margin-bottom:0.3rem;">Course Access Grants</h2>
    <p style="font-size:0.85rem; color:var(--dash-muted);">Manage student course enrollments, approvals, and access permissions</p>
  </div>
  <button type="button" onclick="document.getElementById('grant-modal').classList.add('open')" class="btn-primary">
    <i class="fa-solid fa-key"></i> Grant New Access
  </button>
</div>

<!-- Filters -->
<div style="display:flex; gap:0.6rem; margin-bottom:1.5rem; flex-wrap:wrap;">
  <a href="<?= BASE_URL ?>/admin/requests.php?status=all" class="topbar-btn <?= $statusFilter === 'all' ? 'btn-primary' : '' ?>">All Enrollments</a>
  <a href="<?= BASE_URL ?>/admin/requests.php?status=pending" class="topbar-btn <?= $statusFilter === 'pending' ? 'btn-primary' : '' ?>">Pending Requests</a>
  <a href="<?= BASE_URL ?>/admin/requests.php?status=active" class="topbar-btn <?= $statusFilter === 'active' ? 'btn-primary' : '' ?>">Active Grants</a>
  <a href="<?= BASE_URL ?>/admin/requests.php?status=revoked" class="topbar-btn <?= $statusFilter === 'revoked' ? 'btn-primary' : '' ?>">Revoked</a>
</div>

<div class="dash-panel">
  <?php if (empty($enrollments)): ?>
    <div style="padding:3rem; text-align:center; color:var(--dash-muted);">
      <i class="fa-solid fa-folder-open" style="font-size:2.5rem; margin-bottom:1rem; display:block;"></i>
      No enrollment records found for the selected filter.
    </div>
  <?php else: ?>
    <div class="custom-table-wrap">
      <table class="custom-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Course</th>
            <th>Status</th>
            <th>Access Type</th>
            <th>Amount Paid</th>
            <th>Requested Date</th>
            <th>Approved Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($enrollments as $e): ?>
            <tr>
              <td>
                <strong style="color:#fff;"><?= htmlspecialchars($e['user_name']) ?></strong><br>
                <small style="color:var(--dash-muted);"><?= htmlspecialchars($e['user_email']) ?></small><br>
                <small style="color:var(--dash-gold);"><?= htmlspecialchars($e['user_phone'] ?? '') ?></small>
              </td>
              <td>
                <span style="color:var(--dash-gold-bright); font-weight:600;"><?= htmlspecialchars($e['course_title']) ?></span><br>
                <small><?= formatPrice($e['course_price']) ?></small>
              </td>
              <td>
                <?php if ($e['status'] === 'active'): ?>
                  <span class="badge badge-active"><i class="fa-solid fa-circle-check"></i> Active</span>
                <?php elseif ($e['status'] === 'pending'): ?>
                  <span class="badge badge-pending"><i class="fa-solid fa-clock"></i> Pending</span>
                <?php else: ?>
                  <span class="badge badge-danger"><i class="fa-solid fa-ban"></i> <?= htmlspecialchars($e['status']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--dash-muted);">
                  <?= str_replace('_', ' ', $e['access_type']) ?>
                </span>
                <?php if (!empty($e['request_note'])): ?>
                  <br><small style="color:var(--dash-gold);" title="<?= htmlspecialchars($e['request_note']) ?>">Note: <?= htmlspecialchars(substr($e['request_note'], 0, 30)) ?>...</small>
                <?php endif; ?>
              </td>
              <td><?= formatPrice($e['amount_paid']) ?></td>
              <td><?= date('d M Y, h:i A', strtotime($e['enrolled_at'])) ?></td>
              <td><?= $e['approved_at'] ? date('d M Y, h:i A', strtotime($e['approved_at'])) : '<span style="color:var(--dash-muted);">—</span>' ?></td>
              <td>
                <div style="display:flex; gap:0.4rem;">
                  <?php if ($e['status'] !== 'active'): ?>
                    <a href="<?= BASE_URL ?>/admin/requests.php?action=activate&id=<?= $e['id'] ?>" class="btn-action btn-approve" title="Grant Active Access">
                      <i class="fa-solid fa-check"></i> Grant
                    </a>
                  <?php else: ?>
                    <a href="<?= BASE_URL ?>/admin/requests.php?action=revoke&id=<?= $e['id'] ?>" class="btn-action btn-revoke" title="Revoke Access" onclick="return confirm('Revoke student access to this course?');">
                      <i class="fa-solid fa-ban"></i> Revoke
                    </a>
                  <?php endif; ?>
                  <a href="<?= BASE_URL ?>/admin/requests.php?action=delete&id=<?= $e['id'] ?>" class="btn-action btn-revoke" title="Delete Record" onclick="return confirm('Permanently delete this enrollment?');">
                    <i class="fa-solid fa-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Modal: Grant Course Access -->
<div class="modal-backdrop" id="grant-modal">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('grant-modal').classList.remove('open')">&times;</button>
    <h3 style="font-family: var(--font-serif); font-size: 1.3rem; color: var(--dash-gold-bright); margin-bottom: 0.5rem;">
      <i class="fa-solid fa-unlock-keyhole"></i> Grant Course Access
    </h3>
    <p style="font-size: 0.85rem; color: var(--dash-muted); margin-bottom: 1.5rem;">
      Unlock any course for a registered user by email. Access is granted instantly in their student dashboard.
    </p>

    <form method="POST">
      <input type="hidden" name="action" value="grant_access">

      <div class="form-group">
        <label class="form-label">Student Email Address *</label>
        <input type="email" name="email" class="form-control" required placeholder="student@example.com">
      </div>

      <div class="form-group">
        <label class="form-label">Select Course to Unlock *</label>
        <select name="course_id" class="form-control" required>
          <option value="" disabled selected>Select course...</option>
          <?php foreach ($allCourses as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?> (<?= formatPrice($c['price']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Admin Reference / Note</label>
        <input type="text" name="note" class="form-control" placeholder="e.g. VIP Student / Offline Payment">
      </div>

      <button type="submit" class="btn-primary" style="width: 100%;">
        Confirm & Grant Access
      </button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
