<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();
$currentUser = getCurrentUser();
$adminPage = $adminPage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin Portal — The Astro Healer') ?></title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">

  <!-- Google Fonts & Font Awesome -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Chart.js CDN for Graphs -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <!-- Master & Dashboard Styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../../assets/css/dashboard.css') ?>">
</head>
<body class="dashboard-body">

  <!-- Admin Sidebar -->
  <aside class="dash-sidebar" id="admin-sidebar">
    <div class="sidebar-header">
      <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="The Astro Healer" class="sidebar-logo">
      <div>
        <div class="sidebar-brand-name">THE ASTRO HEALER</div>
        <div class="sidebar-badge">Admin Control Panel</div>
      </div>
    </div>

    <ul class="sidebar-nav">
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/index.php" class="sidebar-link <?= $adminPage === 'dashboard' ? 'active' : '' ?>">
          <i class="fa-solid fa-gauge-high"></i>
          <span>Dashboard & Charts</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/requests.php" class="sidebar-link <?= $adminPage === 'requests' ? 'active' : '' ?>">
          <i class="fa-solid fa-user-check"></i>
          <span>Course Access & Grants</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/lectures.php" class="sidebar-link <?= $adminPage === 'lectures' ? 'active' : '' ?>">
          <i class="fa-solid fa-video"></i>
          <span>Class Lectures Uploader</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/courses.php" class="sidebar-link <?= $adminPage === 'courses' ? 'active' : '' ?>">
          <i class="fa-solid fa-graduation-cap"></i>
          <span>Courses Management</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/students.php" class="sidebar-link <?= $adminPage === 'students' ? 'active' : '' ?>">
          <i class="fa-solid fa-users"></i>
          <span>Students & Users</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/index.php?tab=bot" class="sidebar-link <?= ($adminPage === 'bot' || (isset($_GET['tab']) && $_GET['tab'] === 'bot')) ? 'active' : '' ?>">
          <i class="fa-solid fa-sparkles" style="color: #a39cfd;"></i>
          <span>Astrobot AI Pulse</span>
          <span style="margin-left: auto; font-size: 0.65rem; background: rgba(99, 91, 255, 0.2); color: #c4c1ff; border: 1px solid rgba(99, 91, 255, 0.4); padding: 2px 7px; border-radius: 10px; font-weight: 700;">LIVE</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/index.php?tab=audit" class="sidebar-link <?= ($adminPage === 'audit' || (isset($_GET['tab']) && $_GET['tab'] === 'audit')) ? 'active' : '' ?>">
          <i class="fa-solid fa-shield-halved" style="color: #81c784;"></i>
          <span>Audit Trail & Logs</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/bookings.php" class="sidebar-link <?= $adminPage === 'bookings' ? 'active' : '' ?>">
          <i class="fa-regular fa-calendar-check"></i>
          <span>Session Bookings</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link <?= $adminPage === 'settings' ? 'active' : '' ?>">
          <i class="fa-solid fa-sliders"></i>
          <span>Stripe & Settings</span>
        </a>
      </li>
    </ul>


    <div class="sidebar-footer">
      <div class="user-snippet">
        <div class="user-avatar-small">M</div>
        <div class="user-info-text">
          <div class="user-name-snippet"><?= htmlspecialchars($currentUser['name']) ?></div>
          <div class="user-role-badge">Super Administrator</div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" title="Logout" style="color:var(--danger);"><i class="fa-solid fa-power-off"></i></a>
      </div>
    </div>
  </aside>

  <!-- Main Content Wrapper -->
  <div class="dash-main">
    <!-- Topbar -->
    <header class="dash-topbar">
      <div class="topbar-title">
        <button id="sidebar-toggle" class="dash-sidebar-toggle" aria-label="Toggle Navigation Menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span><?= htmlspecialchars($pageTitle ?? 'Admin Dashboard') ?></span>
      </div>

      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>/index.php" target="_blank" class="topbar-btn" title="View Live Website">
          <i class="fa-solid fa-globe"></i> <span>Website</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/lectures.php?action=new" class="btn-primary btn-sm" title="Upload New Class Video">
          <i class="fa-solid fa-cloud-arrow-up"></i> <span>Upload</span>
        </a>
      </div>
    </header>

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="dash-sidebar-backdrop" id="sidebar-backdrop"></div>

    <div class="dash-content">
      <?php displayFlash(); ?>
