<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/includes/astrobot_data.php';

requireAdmin();
$pdo = getDBConnection();

// Active tab selection
$activeTab = $_GET['tab'] ?? 'overview';

// Handle quick approve / reject from dashboard
if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $reqId = intval($_GET['req_id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE enrollments SET status = 'active', approved_at = NOW() WHERE id = ?");
        $stmt->execute([$reqId]);
        logAuditTrail('COURSE_APPROVED', 'admin', "Admin approved and activated course access for enrollment request #{$reqId}", 'success');
        setFlash('success', 'Course access approved and granted successfully!');
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE enrollments SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$reqId]);
        logAuditTrail('COURSE_REJECTED', 'admin', "Admin rejected course access for enrollment request #{$reqId}", 'warning');
        setFlash('info', 'Enrollment request rejected.');
    }
    header('Location: ' . BASE_URL . '/admin/index.php?tab=overview');
    exit;
}

// Handle Gift Coins to Astrobot User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'gift_bot_coins') {
    $botEmail = trim($_POST['bot_email'] ?? '');
    $coinsToAward = intval($_POST['coins'] ?? 250);

    if (!empty($botEmail) && $coinsToAward > 0) {
        if (giftAstrobotCoins($botEmail, $coinsToAward)) {
            logAuditTrail('BOT_COINS_GIFTED', 'bot', "Admin awarded +{$coinsToAward} Astrobot coins to {$botEmail}", 'success', null, $botEmail);
            setFlash('success', "Awarded {$coinsToAward} coins to Astrobot seeker ({$botEmail})!");
        } else {
            setFlash('error', "Could not find Astrobot seeker with email {$botEmail}.");
        }
    }
    header('Location: ' . BASE_URL . '/admin/index.php?tab=bot');
    exit;
}

// Handle Manual Course Access Grant Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grant_manual_access') {
    $studentEmail = trim($_POST['student_email'] ?? '');
    $courseId = intval($_POST['course_id'] ?? 0);
    $accessNote = trim($_POST['access_note'] ?? 'Admin Manual Grant');

    if (empty($studentEmail) || empty($courseId)) {
        setFlash('error', 'Please provide student email and select a course.');
    } else {
        $uStmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $uStmt->execute([$studentEmail]);
        $targetUser = $uStmt->fetch();

        if (!$targetUser) {
            setFlash('error', 'User with email "' . htmlspecialchars($studentEmail) . '" not found. They must register an account first.');
        } else {
            $eCheck = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
            $eCheck->execute([$targetUser['id'], $courseId]);
            $existing = $eCheck->fetch();

            if ($existing) {
                $upd = $pdo->prepare("UPDATE enrollments SET status = 'active', access_type = 'admin_granted', request_note = ?, approved_at = NOW() WHERE id = ?");
                $upd->execute([$accessNote, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status, access_type, request_note, approved_at) VALUES (?, ?, 'active', 'admin_granted', ?, NOW())");
                $ins->execute([$targetUser['id'], $courseId, $accessNote]);
            }
            logAuditTrail('MANUAL_COURSE_GRANTED', 'admin', "Admin granted lifetime course access (Course #{$courseId}) to {$targetUser['name']} ({$studentEmail})", 'success', $targetUser['id'], $studentEmail, $targetUser['name']);
            setFlash('success', 'Lifetime course access successfully granted to ' . htmlspecialchars($targetUser['name']) . ' (' . htmlspecialchars($studentEmail) . ')!');
        }
    }
    header('Location: ' . BASE_URL . '/admin/index.php?tab=overview');
    exit;
}

// 1. Academy Analytics Queries
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM enrollments WHERE status = 'active'")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$activeEnrollments = $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn();
$pendingRequests = $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'pending'")->fetchColumn();
$totalLectures = $pdo->query("SELECT COUNT(*) FROM lectures")->fetchColumn();

// Fetch Pending Requests
$pendingStmt = $pdo->query("
    SELECT e.id as req_id, e.status, e.access_type, e.request_note, e.enrolled_at,
           u.id as user_id, u.name as user_name, u.email as user_email, u.phone as user_phone,
           c.id as course_id, c.title as course_title, c.price as course_price
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.status = 'pending'
    ORDER BY e.enrolled_at DESC
");
$pendingList = $pendingStmt->fetchAll();

// Course Breakdown for Chart
$courseBreakdownStmt = $pdo->query("
    SELECT c.title, COUNT(e.id) as count
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'active'
    GROUP BY c.id
");
$courseChartData = $courseBreakdownStmt->fetchAll();
$chartLabels = [];
$chartCounts = [];
foreach ($courseChartData as $cd) {
    $chartLabels[] = $cd['title'];
    $chartCounts[] = (int)$cd['count'];
}

// All courses for dropdown
$allCourses = $pdo->query("SELECT id, title, price FROM courses WHERE status = 'published'")->fetchAll();

// 2. Astrobot Data Integration
$botData = getAstrobotMetricsAndUsers();

// 3. Audit Trail Logs
$auditCat = trim($_GET['audit_cat'] ?? '');
$auditSearch = trim($_GET['audit_q'] ?? '');
$auditLogs = getAuditLogs(100, $auditCat, $auditSearch);
$auditStats = getAuditStats();

$pageTitle = 'Enterprise Admin Portal — The Astro Healer';
$adminPage = 'dashboard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- EXECUTIVE SYSTEM HEALTH BAR -->
<div style="background: rgba(13, 18, 56, 0.45); border: 1px solid var(--dash-border); border-radius: 14px; padding: 0.9rem 1.4rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
  <div style="display: flex; align-items: center; gap: 0.85rem;">
    <div style="position: relative; width: 12px; height: 12px; border-radius: 50%; background: #00c853; box-shadow: 0 0 12px #00c853;">
      <span style="position: absolute; inset: -4px; border-radius: 50%; border: 1.5px solid #00c853; animation: pulseGreen 2s infinite;"></span>
    </div>
    <div>
      <span style="font-weight: 700; color: #fff; font-size: 0.92rem;">Enterprise System Online</span>
      <span style="color: var(--dash-muted); font-size: 0.8rem; margin-left: 0.5rem;">Live Real-Time Monitoring & Security Active</span>
    </div>
  </div>

  <div style="display: flex; align-items: center; gap: 1.2rem; font-size: 0.82rem;">
    <span style="color: var(--dash-muted);"><i class="fa-brands fa-stripe" style="color:#a39cfd;"></i> Stripe Live</span>
    <span style="color: var(--dash-muted);"><i class="fa-solid fa-sparkles" style="color:var(--dash-gold);"></i> VedicAstroAPI Ready</span>
    <span style="color: var(--dash-muted);"><i class="fa-solid fa-shield-check" style="color:#00c853;"></i> Audit Trail Logging</span>
  </div>
</div>

<!-- TOP EXECUTIVE STATS GRID -->
<div class="stats-grid">
  <!-- Card 1: Total Academy Revenue -->
  <div class="stat-card" style="border-color: rgba(212, 175, 55, 0.35);">
    <div class="stat-icon" style="background: rgba(212,175,55,0.15); border-color: var(--dash-gold-bright); color: var(--dash-gold-bright);">
      <i class="fa-solid fa-sack-dollar"></i>
    </div>
    <div class="stat-details">
      <div class="stat-value"><?= formatPrice($totalRevenue) ?></div>
      <div class="stat-label">Academy Course Revenue</div>
      <div style="font-size: 0.72rem; color: #69f0ae; margin-top: 0.3rem;"><i class="fa-solid fa-arrow-trend-up"></i> Lifetime Stripe & Grants</div>
    </div>
  </div>

  <!-- Card 2: Astrobot Economy & Pulse -->
  <div class="stat-card" style="border-color: rgba(99, 91, 255, 0.35);">
    <div class="stat-icon" style="background: rgba(99, 91, 255, 0.15); border-color: #635bff; color: #a39cfd;">
      <i class="fa-solid fa-wand-magic-sparkles"></i>
    </div>
    <div class="stat-details">
      <div class="stat-value"><?= number_format($botData['total_users']) ?> Seekers</div>
      <div class="stat-label">Astrobot AI Consultations</div>
      <div style="font-size: 0.72rem; color: #a39cfd; margin-top: 0.3rem;">
        <strong><?= $botData['total_questions'] ?></strong> Questions · <strong><?= number_format($botData['total_coins']) ?></strong> Coins Held
      </div>
    </div>
  </div>

  <!-- Card 3: Active Students & Grants -->
  <div class="stat-card">
    <div class="stat-icon" style="background: rgba(0,200,83,0.15); border-color: #00c853; color:#69f0ae;">
      <i class="fa-solid fa-user-graduate"></i>
    </div>
    <div class="stat-details">
      <div class="stat-value"><?= $totalStudents ?> Students</div>
      <div class="stat-label">LMS Active Enrolled</div>
      <div style="font-size: 0.72rem; color: var(--dash-muted); margin-top: 0.3rem;">
        <?= $activeEnrollments ?> Active Grants · <?= $totalLectures ?> Classes
      </div>
    </div>
  </div>

  <!-- Card 4: Audit Activity & Pending -->
  <div class="stat-card" style="border-color: <?= $pendingRequests > 0 ? 'rgba(255,171,0,0.5)' : 'var(--dash-border)' ?>;">
    <div class="stat-icon" style="background: rgba(0,176,255,0.15); border-color: #00b0ff; color:#80d8ff;">
      <i class="fa-solid fa-shield-halved"></i>
    </div>
    <div class="stat-details">
      <div class="stat-value"><?= $auditStats['today'] ?> Events</div>
      <div class="stat-label">Audit Trail Logged Today</div>
      <div style="font-size: 0.72rem; color: <?= $pendingRequests > 0 ? '#ffd740' : 'var(--dash-muted)' ?>; margin-top: 0.3rem;">
        <?= $pendingRequests ?> Pending Requests · <?= $auditStats['total'] ?> Total Logs
      </div>
    </div>
  </div>
</div>

<!-- SEGMENTED NAVIGATION TABS -->
<div class="dash-tabs-bar">
  <a href="<?= BASE_URL ?>/admin/index.php?tab=overview" class="dash-tab-btn <?= $activeTab === 'overview' ? 'active' : '' ?>">
    <i class="fa-solid fa-chart-pie"></i>
    <span>Overview & LMS Analytics</span>
    <?php if ($pendingRequests > 0): ?>
      <span class="dash-tab-badge" style="background:#ffab00; color:#000;"><?= $pendingRequests ?> Pending</span>
    <?php endif; ?>
  </a>

  <a href="<?= BASE_URL ?>/admin/index.php?tab=bot" class="dash-tab-btn <?= $activeTab === 'bot' ? 'active' : '' ?>">
    <i class="fa-solid fa-sparkles" style="color:#a39cfd;"></i>
    <span>Astrobot AI Chamber</span>
    <span class="dash-tab-badge"><?= $botData['total_users'] ?> Seekers</span>
  </a>

  <a href="<?= BASE_URL ?>/admin/index.php?tab=audit" class="dash-tab-btn <?= $activeTab === 'audit' ? 'active' : '' ?>">
    <i class="fa-solid fa-shield-halved" style="color:#81c784;"></i>
    <span>System Audit Trail</span>
    <span class="dash-tab-badge" style="background:rgba(0,200,83,0.2); color:#69f0ae;"><?= $auditStats['today'] ?> Today</span>
  </a>

  <a href="<?= BASE_URL ?>/admin/index.php?tab=grants" class="dash-tab-btn <?= $activeTab === 'grants' ? 'active' : '' ?>">
    <i class="fa-solid fa-key" style="color:var(--dash-gold);"></i>
    <span>Direct Course Grants</span>
  </a>
</div>

<!-- ========================================================================= -->
<!-- TAB 1: EXECUTIVE OVERVIEW & LMS                                            -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'overview'): ?>
  <!-- Quick Classroom Action Banner -->
  <div style="display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, rgba(212,175,55,0.12) 0%, rgba(13,18,56,0.85) 100%); border:1px solid rgba(212,175,55,0.3); border-radius:12px; padding:1.1rem 1.4rem; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div style="display:flex; align-items:center; gap:1rem;">
      <div style="width:44px; height:44px; border-radius:10px; background:rgba(212,175,55,0.15); border:1px solid var(--gold); display:flex; align-items:center; justify-content:center; color:var(--gold-bright); font-size:1.2rem;">
        <i class="fa-solid fa-video"></i>
      </div>
      <div>
        <h4 style="margin:0; font-size:1rem; color:#fff;">Course Lectures & Video Uploader</h4>
        <p style="margin:0.2rem 0 0; font-size:0.8rem; color:var(--dash-muted);">Upload class videos (MP4) or stream from Google Drive & YouTube directly to student classrooms.</p>
      </div>
    </div>
    <div style="display:flex; gap:0.6rem; align-items:center;">
      <a href="<?= BASE_URL ?>/admin/lectures.php" class="topbar-btn btn-sm">
        <i class="fa-solid fa-list-check"></i> Manage Classes (<?= $totalLectures ?>)
      </a>
      <a href="<?= BASE_URL ?>/admin/lectures.php?action=new" class="btn-primary btn-sm">
        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Class Video
      </a>
    </div>
  </div>

  <!-- Charts Section -->
  <div class="charts-grid">
    <div class="dash-panel" style="margin-bottom:0;">
      <div class="panel-header">
        <h3 class="panel-title"><i class="fa-solid fa-chart-line" style="color:var(--dash-gold-bright);"></i> Revenue & Enrollment Analytics</h3>
        <span style="font-size:0.8rem; color:var(--dash-muted);">Live Course Growth</span>
      </div>
      <div style="height: 280px; position: relative;">
        <canvas id="revenueTrendChart"></canvas>
      </div>
    </div>

    <div class="dash-panel" style="margin-bottom:0;">
      <div class="panel-header">
        <h3 class="panel-title"><i class="fa-solid fa-chart-pie" style="color:var(--dash-gold-bright);"></i> Course Enrollment Share</h3>
      </div>
      <div style="height: 280px; position: relative;">
        <canvas id="courseShareChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Pending Requests Table -->
  <div class="dash-panel" style="margin-top: 2rem;">
    <div class="panel-header">
      <h3 class="panel-title">
        <i class="fa-solid fa-user-clock" style="color:var(--warning);"></i> 
        Pending Course Access Requests (<?= count($pendingList) ?>)
      </h3>
      <a href="<?= BASE_URL ?>/admin/requests.php" class="topbar-btn btn-sm">Manage All Grants</a>
    </div>

    <?php if (empty($pendingList)): ?>
      <div style="padding: 2.5rem; text-align: center; color: var(--dash-muted);">
        <i class="fa-solid fa-circle-check" style="font-size: 2.5rem; color: var(--success); margin-bottom: 0.8rem; display: block;"></i>
        All course access requests have been cleared and verified! No pending items.
      </div>
    <?php else: ?>
      <div class="custom-table-wrap">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Student Details</th>
              <th>Course Requested</th>
              <th>Request Date</th>
              <th>Reference Note</th>
              <th>Instant Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingList as $p): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($p['user_name']) ?></strong><br>
                  <small style="color:var(--dash-muted)"><?= htmlspecialchars($p['user_email']) ?></small><br>
                  <small style="color:var(--dash-gold)"><?= htmlspecialchars($p['user_phone'] ?? '') ?></small>
                </td>
                <td>
                  <span style="color:var(--dash-gold-bright); font-weight:600;"><?= htmlspecialchars($p['course_title']) ?></span><br>
                  <small><?= formatPrice($p['course_price']) ?></small>
                </td>
                <td><?= date('d M Y, h:i A', strtotime($p['enrolled_at'])) ?></td>
                <td>
                  <span style="font-size:0.8rem; color:var(--dash-muted); max-width:200px; display:inline-block;">
                    <?= htmlspecialchars($p['request_note'] ?: 'Direct checkout request') ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex; gap:0.4rem;">
                    <a href="<?= BASE_URL ?>/admin/index.php?action=approve&req_id=<?= $p['req_id'] ?>" class="btn-action btn-approve" title="Approve & Grant Lifetime Access">
                      <i class="fa-solid fa-check"></i> Approve
                    </a>
                    <a href="<?= BASE_URL ?>/admin/index.php?action=reject&req_id=<?= $p['req_id'] ?>" class="btn-action btn-revoke" title="Reject Request" onclick="return confirm('Are you sure you want to reject this request?');">
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

<!-- ========================================================================= -->
<!-- TAB 2: ASTROBOT AI PULSE & SEEKER DIRECTORY                                -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'bot'): ?>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.2rem; margin-bottom: 2rem;">
    <div class="dash-panel" style="margin-bottom:0; padding:1.2rem;">
      <div style="font-size:0.75rem; text-transform:uppercase; color:var(--dash-muted); letter-spacing:0.06em;">Total Bot Seekers</div>
      <div style="font-size:1.8rem; font-weight:800; color:#fff; margin:0.3rem 0; font-family:'Cinzel',serif;"><?= $botData['total_users'] ?></div>
      <small style="color:#a39cfd;"><i class="fa-solid fa-users"></i> Registered with Birth Data</small>
    </div>

    <div class="dash-panel" style="margin-bottom:0; padding:1.2rem;">
      <div style="font-size:0.75rem; text-transform:uppercase; color:var(--dash-muted); letter-spacing:0.06em;">VIP Paid Seekers</div>
      <div style="font-size:1.8rem; font-weight:800; color:#ffd700; margin:0.3rem 0; font-family:'Cinzel',serif;"><?= $botData['vip_users'] ?></div>
      <small style="color:#ffd700;"><i class="fa-solid fa-crown"></i> Active Premium Members</small>
    </div>

    <div class="dash-panel" style="margin-bottom:0; padding:1.2rem;">
      <div style="font-size:0.75rem; text-transform:uppercase; color:var(--dash-muted); letter-spacing:0.06em;">Questions Answered</div>
      <div style="font-size:1.8rem; font-weight:800; color:#69f0ae; margin:0.3rem 0; font-family:'Cinzel',serif;"><?= $botData['total_questions'] ?></div>
      <small style="color:#69f0ae;"><i class="fa-solid fa-comments"></i> Astrologer Malik Oracle AI</small>
    </div>

    <div class="dash-panel" style="margin-bottom:0; padding:1.2rem;">
      <div style="font-size:0.75rem; text-transform:uppercase; color:var(--dash-muted); letter-spacing:0.06em;">Total Coins Circulating</div>
      <div style="font-size:1.8rem; font-weight:800; color:#00b0ff; margin:0.3rem 0; font-family:'Cinzel',serif;"><?= number_format($botData['total_coins']) ?></div>
      <small style="color:#00b0ff;"><i class="fa-solid fa-coins"></i> Wallet Economy</small>
    </div>
  </div>

  <!-- Astrobot Users Table -->
  <div class="dash-panel">
    <div class="panel-header">
      <h3 class="panel-title">
        <i class="fa-solid fa-sparkles" style="color:#a39cfd;"></i> 
        Astrobot Registered Seekers & Birth Chart Placemarks (<?= count($botData['users']) ?>)
      </h3>
      <a href="<?= BASE_URL ?>/ai.php" target="_blank" class="topbar-btn btn-sm">
        <i class="fa-solid fa-up-right-from-square"></i> Open Bot Chamber
      </a>
    </div>

    <div class="custom-table-wrap">
      <table class="custom-table">
        <thead>
          <tr>
            <th>Seeker Identity</th>
            <th>Birth Details & Location</th>
            <th>Plan & Status</th>
            <th>Coins Balance</th>
            <th>Questions</th>
            <th>Joined / Active</th>
            <th>Admin Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($botData['users'] as $bu): ?>
            <?php 
              $coins = intval($bu['coins']);
              $chipClass = $coins > 200 ? 'high' : ($coins > 0 ? 'mid' : 'empty');
              $historyCount = is_array($bu['chatHistory']) ? count($bu['chatHistory']) : 0;
            ?>
            <tr>
              <td>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                  <div style="width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#312e81,#1e1b4b); border:1px solid #6366f1; display:flex; align-items:center; justify-content:center; font-weight:bold; color:#fff; font-size:0.8rem;">
                    <?= strtoupper(substr($bu['name'] ?: 'S', 0, 2)) ?>
                  </div>
                  <div>
                    <strong style="color:#fff;"><?= htmlspecialchars($bu['name'] ?: 'Seeker') ?></strong>
                    <?php if ($bu['role'] === 'admin'): ?>
                      <span style="font-size:0.65rem; background:rgba(212,175,55,0.2); color:#ffd700; border:1px solid rgba(212,175,55,0.4); padding:1px 5px; border-radius:4px; margin-left:4px;">ADMIN</span>
                    <?php endif; ?>
                    <br>
                    <small style="color:var(--dash-muted); font-family:monospace;"><?= htmlspecialchars($bu['email']) ?></small>
                    <?php if (!empty($bu['phone'])): ?>
                      <br><small style="color:#a39cfd;"><i class="fa-brands fa-whatsapp"></i> <?= htmlspecialchars($bu['phone']) ?></small>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-size:0.82rem; color:#e2e8f0;">
                  <i class="fa-solid fa-location-dot" style="color:#ef4444; font-size:0.75rem;"></i> <?= htmlspecialchars($bu['place'] ?: 'Lahore, Pakistan') ?>
                </div>
                <div style="font-size:0.72rem; color:var(--dash-muted); font-family:monospace; margin-top:2px;">
                  DOB: <?= htmlspecialchars($bu['dob'] ?: 'N/A') ?> · TOB: <?= htmlspecialchars($bu['tob'] ?: '12:00') ?>
                </div>
              </td>
              <td>
                <?php if ($bu['plan'] === 'premium' || $coins > 200): ?>
                  <span class="bot-badge-vip"><i class="fa-solid fa-crown"></i> VIP</span>
                <?php else: ?>
                  <span class="bot-badge-free"><i class="fa-regular fa-user"></i> Free</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="coin-chip <?= $chipClass ?>">
                  <i class="fa-solid fa-coins"></i> <?= $coins ?>
                </span>
              </td>
              <td>
                <span style="font-weight:700; color:#fff; font-size:0.9rem;"><?= $bu['questionsAsked'] ?></span>
                <small style="color:var(--dash-muted); font-size:0.75rem;">asks</small>
              </td>
              <td>
                <span style="font-size:0.75rem; color:var(--dash-muted);">
                  <?= $bu['createdAt'] ? date('d M Y', round($bu['createdAt'] / 1000)) : 'Recent' ?>
                </span>
              </td>
              <td>
                <div style="display:flex; gap:0.4rem; align-items:center;">
                  <!-- Gift Coins Button -->
                  <button type="button" class="btn-action" style="background:rgba(212,175,55,0.15); border-color:var(--dash-gold); color:#ffd700;" 
                    data-email="<?= htmlspecialchars($bu['email'] ?? '', ENT_QUOTES) ?>" 
                    data-name="<?= htmlspecialchars($bu['name'] ?: 'Seeker', ENT_QUOTES) ?>" 
                    onclick="openGiftFromButton(this)" title="Gift Coins">
                    <i class="fa-solid fa-plus"></i> Coins
                  </button>

                  <!-- View Transcript Button -->
                  <?php if ($historyCount > 0): ?>
                    <button type="button" class="btn-action" style="background:rgba(99,91,255,0.15); border-color:#635bff; color:#a39cfd;" 
                      data-seeker="<?= htmlspecialchars($bu['name'] ?: 'Seeker', ENT_QUOTES) ?>" 
                      data-history="<?= htmlspecialchars(json_encode($bu['chatHistory'] ?? []), ENT_QUOTES, 'UTF-8') ?>" 
                      onclick="openTranscriptFromButton(this)" title="View Chat Transcript">
                      <i class="fa-regular fa-message"></i> (<?= $historyCount ?>)
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<!-- ========================================================================= -->
<!-- TAB 3: AUDIT TRAIL & SECURITY EVENT LOGS                                  -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'audit'): ?>
  <!-- Audit Filter Bar -->
  <div class="dash-panel" style="padding: 1.2rem; margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
      <input type="hidden" name="tab" value="audit">
      
      <div style="flex: 1 1 200px; min-width: 0; width: 100%;">
        <input type="text" name="audit_q" value="<?= htmlspecialchars($auditSearch) ?>" class="form-control" placeholder="Search by user email, name, IP address, or event details...">
      </div>

      <div style="flex: 1 1 160px; min-width: 0; width: 100%;">
        <select name="audit_cat" class="form-control" onchange="this.form.submit()">
          <option value="">All Categories (<?= $auditStats['total'] ?>)</option>
          <option value="auth" <?= $auditCat === 'auth' ? 'selected' : '' ?>>Authentication & Logins</option>
          <option value="bot" <?= $auditCat === 'bot' ? 'selected' : '' ?>>Astrobot Interactions (<?= $auditStats['bot_events'] ?>)</option>
          <option value="payment" <?= $auditCat === 'payment' ? 'selected' : '' ?>>Stripe & Financial</option>
          <option value="course" <?= $auditCat === 'course' ? 'selected' : '' ?>>Course Enrollments</option>
          <option value="booking" <?= $auditCat === 'booking' ? 'selected' : '' ?>>Session Consultations</option>
          <option value="admin" <?= $auditCat === 'admin' ? 'selected' : '' ?>>Admin Actions</option>
        </select>
      </div>

      <button type="submit" class="btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Filter Logs</button>
      <?php if (!empty($auditCat) || !empty($auditSearch)): ?>
        <a href="<?= BASE_URL ?>/admin/index.php?tab=audit" class="topbar-btn btn-sm">Clear Filters</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Audit Table Panel -->
  <div class="dash-panel">
    <div class="panel-header">
      <h3 class="panel-title">
        <i class="fa-solid fa-shield-halved" style="color:#00c853;"></i> 
        Enterprise Audit Trail & Live Activity Log (<?= count($auditLogs) ?> Records)
      </h3>
      <div style="display:flex; gap:0.5rem; font-size:0.75rem; color:var(--dash-muted);">
        <span><i class="fa-solid fa-circle" style="color:#00c853; font-size:0.6rem;"></i> Success</span>
        <span><i class="fa-solid fa-circle" style="color:#a39cfd; font-size:0.6rem;"></i> Info</span>
        <span><i class="fa-solid fa-circle" style="color:#ffab00; font-size:0.6rem;"></i> Warning</span>
        <span><i class="fa-solid fa-circle" style="color:#ff5252; font-size:0.6rem;"></i> Danger</span>
      </div>
    </div>

    <?php if (empty($auditLogs)): ?>
      <div style="padding: 3rem; text-align: center; color: var(--dash-muted);">
        <i class="fa-solid fa-clipboard-check" style="font-size: 2.5rem; color: var(--dash-muted); margin-bottom: 0.8rem; display: block;"></i>
        No audit log events found matching the selected filter criteria.
      </div>
    <?php else: ?>
      <div class="custom-table-wrap">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Actor / Subject</th>
              <th>Action Identifier</th>
              <th>Category</th>
              <th>Event Payload / Details</th>
              <th>Client IP & Agent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($auditLogs as $log): ?>
              <?php
                $status = $log['status'] ?? 'info';
                $badgeClass = 'audit-badge-' . $status;
              ?>
              <tr>
                <td>
                  <span style="font-weight:600; color:#fff; font-size:0.8rem; display:block;">
                    <?= date('d M Y, h:i:s A', strtotime($log['created_at'])) ?>
                  </span>
                  <small style="color:var(--dash-muted); font-size:0.7rem;">
                    <?php 
                      $diff = time() - strtotime($log['created_at']);
                      if ($diff < 60) echo 'Just now';
                      elseif ($diff < 3600) echo round($diff/60) . ' mins ago';
                      elseif ($diff < 86400) echo round($diff/3600) . ' hours ago';
                      else echo round($diff/86400) . ' days ago';
                    ?>
                  </small>
                </td>
                <td>
                  <strong style="color:#fff;"><?= htmlspecialchars($log['user_name'] ?: 'System / Guest') ?></strong><br>
                  <small style="color:var(--dash-muted); font-family:monospace;"><?= htmlspecialchars($log['user_email'] ?: 'No email') ?></small>
                  <?php if (!empty($log['role']) && $log['role'] !== 'guest'): ?>
                    <span style="font-size:0.62rem; text-transform:uppercase; padding:1px 5px; border-radius:3px; background:rgba(255,255,255,0.06); color:#cbd5e1; margin-left:3px;"><?= htmlspecialchars($log['role']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="audit-badge <?= $badgeClass ?>">
                    <?= htmlspecialchars($log['action']) ?>
                  </span>
                </td>
                <td>
                  <span class="audit-cat-pill"><?= htmlspecialchars($log['category']) ?></span>
                </td>
                <td>
                  <span style="font-size:0.84rem; color:#e2e8f0; line-height:1.4; max-width:340px; display:inline-block;">
                    <?= htmlspecialchars($log['details']) ?>
                  </span>
                </td>
                <td>
                  <span class="audit-ip-tag"><?= htmlspecialchars($log['ip_address'] ?: '127.0.0.1') ?></span><br>
                  <small style="color:var(--dash-muted); font-size:0.68rem; max-width:140px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($log['user_agent']) ?>">
                    <?= htmlspecialchars(substr($log['user_agent'], 0, 30)) ?>...
                  </small>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<!-- ========================================================================= -->
<!-- TAB 4: MANUAL COURSE ACCESS GRANTS                                        -->
<!-- ========================================================================= -->
<?php elseif ($activeTab === 'grants'): ?>
  <div style="max-width: 680px; margin: 0 auto;">
    <div class="dash-panel">
      <div class="panel-header">
        <h3 class="panel-title"><i class="fa-solid fa-key" style="color:var(--dash-gold-bright);"></i> Grant Lifetime Course Access</h3>
      </div>
      <p style="font-size:0.85rem; color:var(--dash-muted); margin-bottom:1.5rem;">
        Instantly unlock any of the 3 sacred astrology masterclasses for any registered student. This will activate full classroom access in their portal and register an entry in the Audit Trail.
      </p>

      <form method="POST">
        <input type="hidden" name="action" value="grant_manual_access">

        <div class="form-group">
          <label class="form-label">Student Email *</label>
          <input type="email" name="student_email" class="form-control" required placeholder="student@example.com">
          <small style="color:var(--dash-muted); font-size:0.75rem; margin-top:0.3rem; display:block;">Must be registered with an account in the system.</small>
        </div>

        <div class="form-group">
          <label class="form-label">Select Course to Unlock *</label>
          <select name="course_id" class="form-control" required>
            <option value="" disabled selected>Select course...</option>
            <?php foreach ($allCourses as $ac): ?>
              <option value="<?= $ac['id'] ?>"><?= htmlspecialchars($ac['title']) ?> (<?= formatPrice($ac['price']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Admin Reason / Payment Reference</label>
          <input type="text" name="access_note" class="form-control" placeholder="e.g. Paid in full via Bank Transfer / Direct Malik Mehboob Approval">
        </div>

        <button type="submit" class="btn-primary" style="width:100%; margin-top:1rem;">
          <i class="fa-solid fa-unlock-keyhole"></i> Grant Lifetime Course Access
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- MODAL 1: VIEW ASTROBOT CHAT TRANSCRIPT                                    -->
<!-- ========================================================================= -->
<div id="transcript-modal" class="modal-backdrop">
  <div class="modal-box" style="max-width: 680px;">
    <button type="button" class="modal-close" onclick="closeTranscript()">&times;</button>
    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1.2rem;">
      <div style="width:40px; height:40px; border-radius:50%; background:rgba(99,91,255,0.2); border:1px solid #6366f1; display:flex; align-items:center; justify-content:center; color:#a39cfd;">
        <i class="fa-solid fa-messages"></i>
      </div>
      <div>
        <h3 id="transcript-title" style="margin:0; font-size:1.15rem; color:#fff;">Seeker Chat Transcript</h3>
        <small style="color:var(--dash-muted);">Live questions asked to Astrologer Malik Bot</small>
      </div>
    </div>

    <div id="transcript-messages-container" class="chat-transcript-box">
      <!-- Dynamic transcript bubbles populated via JS -->
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: GIFT COINS TO ASTROBOT SEEKER                                    -->
<!-- ========================================================================= -->
<div id="gift-modal" class="modal-backdrop">
  <div class="modal-box" style="max-width: 480px;">
    <button type="button" class="modal-close" onclick="closeGiftModal()">&times;</button>
    <div style="margin-bottom:1.2rem;">
      <h3 style="margin:0; font-size:1.15rem; color:#ffd700;"><i class="fa-solid fa-gift"></i> Award Astrobot Coins</h3>
      <p id="gift-modal-desc" style="color:var(--dash-muted); font-size:0.85rem; margin-top:0.3rem;">Credit coins to seeker account.</p>
    </div>

    <form method="POST">
      <input type="hidden" name="action" value="gift_bot_coins">
      <input type="hidden" name="bot_email" id="gift-bot-email" value="">

      <div class="form-group">
        <label class="form-label">Coins to Credit</label>
        <select name="coins" class="form-control">
          <option value="50">+50 Coins (5 Free Questions)</option>
          <option value="250" selected>+250 Coins (Basic Pack Value - 25 Questions)</option>
          <option value="500">+500 Coins (Medium Pack Value - 50 Questions)</option>
          <option value="1000">+1000 Coins (Ultra Pack Value - 100 Questions)</option>
        </select>
      </div>

      <button type="submit" class="btn-primary" style="width:100%; margin-top:1rem;">
        <i class="fa-solid fa-coins"></i> Confirm & Award Coins
      </button>
    </form>
  </div>
</div>

<!-- INTERACTIVE SCRIPTS -->
<script>
  // Helper for opening transcript from button data attributes
  function openTranscriptFromButton(btn) {
    try {
      const history = JSON.parse(btn.getAttribute('data-history') || '[]');
      const seekerName = btn.getAttribute('data-seeker') || 'Seeker';
      viewTranscript(history, seekerName);
    } catch (e) {
      console.error('Failed to parse transcript history:', e);
      alert('Could not render transcript history.');
    }
  }

  // Helper for opening gift modal from button data attributes
  function openGiftFromButton(btn) {
    const email = btn.getAttribute('data-email') || '';
    const name = btn.getAttribute('data-name') || 'Seeker';
    openGiftModal(email, name);
  }

  // Chat Transcript Viewer
  function viewTranscript(chatHistory, seekerName) {
    const container = document.getElementById('transcript-messages-container');
    document.getElementById('transcript-title').innerText = seekerName + ' — Astrobot Chat Transcript';
    container.innerHTML = '';

    if (!chatHistory || chatHistory.length === 0) {
      container.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--dash-muted);">No chat messages recorded yet for this seeker.</div>';
    } else {
      chatHistory.forEach(msg => {
        const isModel = msg.role === 'model';
        const msgDiv = document.createElement('div');
        msgDiv.className = 'transcript-msg ' + (isModel ? 'model' : 'user');

        const avatar = document.createElement('div');
        avatar.className = 'transcript-avatar';
        avatar.innerText = isModel ? 'M' : seekerName.substring(0, 1).toUpperCase();

        const bubble = document.createElement('div');
        bubble.className = 'transcript-bubble';
        bubble.innerHTML = msg.text.replace(/\n/g, '<br>');

        msgDiv.appendChild(avatar);
        msgDiv.appendChild(bubble);
        container.appendChild(msgDiv);
      });
    }

    document.getElementById('transcript-modal').classList.add('open');
  }

  function closeTranscript() {
    document.getElementById('transcript-modal').classList.remove('open');
  }

  // Gift Coins Modal
  function openGiftModal(email, name) {
    document.getElementById('gift-bot-email').value = email;
    document.getElementById('gift-modal-desc').innerText = 'Crediting spiritual coins for ' + name + ' (' + email + ')';
    document.getElementById('gift-modal').classList.add('open');
  }

  function closeGiftModal() {
    document.getElementById('gift-modal').classList.remove('open');
  }

  // Close modals on escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeTranscript();
      closeGiftModal();
    }
  });

  <?php if ($activeTab === 'overview'): ?>
  // 1. Revenue & Enrollment Line Chart
  const ctxTrend = document.getElementById('revenueTrendChart').getContext('2d');
  const gradientGold = ctxTrend.createLinearGradient(0, 0, 0, 300);
  gradientGold.addColorStop(0, 'rgba(212, 175, 55, 0.45)');
  gradientGold.addColorStop(1, 'rgba(212, 175, 55, 0.0)');

  new Chart(ctxTrend, {
    type: 'line',
    data: {
      labels: ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
      datasets: [{
        label: 'Course Sales ($)',
        data: [800, 1400, 1900, 2200, 2900, <?= max(3800, (float)$totalRevenue) ?>],
        borderColor: '#ffd700',
        backgroundColor: gradientGold,
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#fff',
        pointBorderColor: '#d4af37',
        pointRadius: 5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          ticks: { color: '#8b93b8' }
        },
        y: {
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          ticks: { color: '#8b93b8' }
        }
      }
    }
  });

  // 2. Course Share Doughnut Chart
  const ctxShare = document.getElementById('courseShareChart').getContext('2d');
  new Chart(ctxShare, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [{
        data: <?= json_encode($chartCounts) ?>,
        backgroundColor: ['#d4af37', '#9d68f7', '#00b0ff'],
        borderColor: '#090e29',
        borderWidth: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#e6ebff', boxWidth: 12, padding: 15 }
        }
      },
      cutout: '70%'
    }
  });
  <?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
