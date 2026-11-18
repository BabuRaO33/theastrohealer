<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireStudent();
$currentUser = getCurrentUser();
$studentPage = $studentPage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Student LMS Portal — The Astro Healer') ?></title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">

  <!-- Google Fonts & Font Awesome -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Master & Dashboard Stylesheet -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../../assets/css/dashboard.css') ?>">
</head>
<body class="dashboard-body">

  <!-- Student Sidebar -->
  <aside class="dash-sidebar" id="student-sidebar">
    <div class="sidebar-header">
      <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="The Astro Healer" class="sidebar-logo">
      <div>
        <div class="sidebar-brand-name">THE ASTRO HEALER</div>
        <div class="sidebar-badge">Student Academy</div>
      </div>
    </div>

    <ul class="sidebar-nav">
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/student/index.php" class="sidebar-link <?= $studentPage === 'dashboard' ? 'active' : '' ?>">
          <i class="fa-solid fa-graduation-cap"></i>
          <span>My Learning</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/student/courses.php" class="sidebar-link <?= $studentPage === 'courses' ? 'active' : '' ?>">
          <i class="fa-solid fa-compass"></i>
          <span>Browse All Courses</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/student/profile.php" class="sidebar-link <?= $studentPage === 'profile' ? 'active' : '' ?>">
          <i class="fa-regular fa-user"></i>
          <span>My Profile</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="https://wa.me/14706505315" target="_blank" class="sidebar-link">
          <i class="fa-brands fa-whatsapp" style="color:#25d366;"></i>
          <span>Student Support</span>
        </a>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-snippet">
        <div class="user-avatar-small"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
        <div class="user-info-text">
          <div class="user-name-snippet"><?= htmlspecialchars($currentUser['name']) ?></div>
          <div class="user-role-badge">Enrolled Student</div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" title="Logout" style="color:var(--danger);"><i class="fa-solid fa-power-off"></i></a>
      </div>
    </div>
  </aside>

  <!-- Main Content Area -->
  <div class="dash-main">
    <!-- Topbar -->
    <header class="dash-topbar">
      <div class="topbar-title">
        <button id="sidebar-toggle" class="dash-sidebar-toggle" aria-label="Toggle Navigation Menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span><?= htmlspecialchars($pageTitle ?? 'Student Dashboard') ?></span>
      </div>

      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>/index.php" class="topbar-btn">
          <i class="fa-solid fa-globe"></i> Main Website
        </a>
      </div>
    </header>

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="dash-sidebar-backdrop" id="sidebar-backdrop"></div>

    <div class="dash-content">
      <?php displayFlash(); ?>
