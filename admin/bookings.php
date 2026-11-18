<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();
$pdo = getDBConnection();

// Status change
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $newStatus = $_GET['status'];
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (in_array($newStatus, $allowed)) {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        setFlash('success', 'Booking status updated to ' . ucfirst($newStatus));
    }
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

// Fetch all bookings
$bookings = $pdo->query("SELECT * FROM bookings ORDER BY id DESC")->fetchAll();

$pageTitle = 'Session Bookings & Inquiries';
$adminPage = 'bookings';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="margin-bottom: 2rem;">
  <h2 style="font-size:1.6rem; color:#fff; margin-bottom:0.3rem;">Consultation Inquiries & Bookings</h2>
  <p style="font-size:0.85rem; color:var(--dash-muted);">Manage requests submitted through your website booking form</p>
</div>

<div class="dash-panel">
  <?php if (empty($bookings)): ?>
    <div style="padding:3rem; text-align:center; color:var(--dash-muted);">
      <i class="fa-regular fa-calendar-xmark" style="font-size:2.5rem; margin-bottom:1rem; display:block;"></i>
      No session bookings received yet.
    </div>
  <?php else: ?>
    <div class="custom-table-wrap">
      <table class="custom-table">
        <thead>
          <tr>
            <th>Client</th>
            <th>Service / Amount</th>
            <th>Message / Birth Info</th>
            <th>Payment</th>
            <th>Booking Status</th>
            <th>Received Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td>
                <strong style="color:#fff;"><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></strong><br>
                <small><a href="mailto:<?= htmlspecialchars($b['email']) ?>" style="color:var(--dash-gold);"><?= htmlspecialchars($b['email']) ?></a></small>
              </td>
              <td>
                <span style="color:var(--dash-gold-bright); font-weight:600;"><?= htmlspecialchars($b['service']) ?></span><br>
                <small style="color:#fff; font-weight:600;">$<?= number_format($b['amount'] ?? 0, 2) ?></small>
              </td>
              <td style="max-width:280px;">
                <p style="font-size:0.82rem; color:var(--dash-muted); margin:0;"><?= nl2br(htmlspecialchars($b['message'])) ?></p>
              </td>
              <td>
                <?php if (($b['payment_status'] ?? '') === 'paid'): ?>
                  <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Paid (Stripe)</span>
                <?php else: ?>
                  <span class="badge badge-pending"><i class="fa-solid fa-clock"></i> Unpaid</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($b['status'] === 'confirmed'): ?>
                  <span class="badge badge-active">Confirmed</span>
                <?php elseif ($b['status'] === 'completed'): ?>
                  <span class="badge badge-success">Completed</span>
                <?php elseif ($b['status'] === 'cancelled'): ?>
                  <span class="badge badge-danger">Cancelled</span>
                <?php else: ?>
                  <span class="badge badge-pending">Pending</span>
                <?php endif; ?>
              </td>
              <td><?= date('d M Y, h:i A', strtotime($b['created_at'])) ?></td>
              <td>
                <div style="display:flex; gap:0.4rem;">
                  <a href="<?= BASE_URL ?>/admin/bookings.php?status=confirmed&id=<?= $b['id'] ?>" class="btn-action btn-approve" title="Confirm Booking">
                    <i class="fa-solid fa-check"></i>
                  </a>
                  <a href="<?= BASE_URL ?>/admin/bookings.php?status=completed&id=<?= $b['id'] ?>" class="btn-action topbar-btn" title="Mark Completed">
                    <i class="fa-solid fa-flag-checkered"></i>
                  </a>
                  <a href="<?= BASE_URL ?>/admin/bookings.php?status=cancelled&id=<?= $b['id'] ?>" class="btn-action btn-revoke" title="Cancel Booking">
                    <i class="fa-solid fa-xmark"></i>
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

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
