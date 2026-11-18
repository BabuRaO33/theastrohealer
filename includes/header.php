<?php
require_once __DIR__ . '/auth.php';
$currentUser = getCurrentUser();
$currPage = basename($_SERVER['PHP_SELF'] ?? '');

// SEO Dynamic Defaults
$pageTitle = $pageTitle ?? 'The Astro Healer — Moon and Stars Solutions Provider | Vedic Astrology & Numerology';
$pageDescription = $pageDescription ?? 'Ancient wisdom meets modern insight. Expert Vedic astrology, Numerology, Bhrigu Nandi Nadi courses, daily cosmic blogs, and personalized consultations by Malik Mehboob.';
$pageKeywords = $pageKeywords ?? 'Vedic astrology, BNN astrology, Numerology, Vastu Shastra, horoscope reading, Zaicha analysis, Malik Mehboob, daily cosmic blog, Pakistan, USA, UK';

// Canonical URL Resolution
if (!isset($canonicalUrl)) {
    if ($currPage === 'blog-post.php' && !empty($_GET['slug'])) {
        $canonicalUrl = BASE_URL . '/blog-post.php?slug=' . urlencode($_GET['slug']);
    } elseif ($currPage === 'course.php' && !empty($_GET['slug'])) {
        $canonicalUrl = BASE_URL . '/course.php?slug=' . urlencode($_GET['slug']);
    } elseif (in_array($currPage, ['index.php', ''])) {
        $canonicalUrl = BASE_URL . '/';
    } else {
        $canonicalUrl = BASE_URL . '/' . ltrim($currPage, '/');
    }
}

$ogImage = $ogImage ?? (BASE_URL . '/assets/images/logo.png');
$ogType = $ogType ?? 'website';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
  <meta name="keywords" content="<?= htmlspecialchars($pageKeywords) ?>">
  <meta name="author" content="Malik Mehboob">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <meta name="revisit-after" content="1 days">
  <meta name="theme-color" content="#040614">
  <meta name="format-detection" content="telephone=no">
  <meta name="geo.region" content="PK">
  <meta name="geo.placename" content="Islamabad">
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="<?= htmlspecialchars($ogType) ?>">
  <meta property="og:site_name" content="The Astro Healer">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta property="og:locale" content="en_US">

  <!-- Twitter / X Cards -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@theastromalik">
  <meta name="twitter:creator" content="@theastromalik">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
  <meta name="twitter:image:alt" content="<?= htmlspecialchars($pageTitle) ?>">

  <?php if (!empty($customJsonLd)): ?>
    <!-- Page Specific Structured Data JSON-LD -->
    <script type="application/ld+json">
      <?= $customJsonLd ?>
    </script>
  <?php endif; ?>
  
  <!-- DNS Prefetch & Preconnect for Peak Performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com">
  <link rel="dns-prefetch" href="https://fonts.googleapis.com">
  <link rel="dns-prefetch" href="https://fonts.gstatic.com">
  <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <!-- Primary Stylesheet -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
  <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>

  <!-- Ambient Starfield Canvas -->
  <canvas id="starfield"></canvas>

  <!-- Modern Luxury Navigation Bar -->
  <nav class="main-nav" id="main-nav">
    <div class="nav-container">
      
      <!-- Brand Presentation -->
      <a href="<?= BASE_URL ?>/index.php" class="brand-wrap">
        <div class="brand-logo-frame">
          <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="The Astro Healer Logo" class="brand-logo-img">
          <span class="brand-glow-ring"></span>
        </div>
        <div class="brand-text">
          <span class="brand-title">THE ASTRO HEALER</span>
          <span class="brand-tagline">Moon &amp; Stars Solutions</span>
        </div>
      </a>

      <!-- Desktop Navigation Menu -->
      <ul class="nav-menu" id="nav-menu">
        <!-- Mobile Drawer Header -->
        <li class="mobile-drawer-header">
          <div class="mobile-brand">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="mobile-logo">
            <div>
              <div class="mobile-brand-name">THE ASTRO HEALER</div>
              <div class="mobile-brand-sub">Cosmic Solutions &amp; AI</div>
            </div>
          </div>
          <button type="button" class="mobile-close-btn" id="mobile-close-btn" aria-label="Close navigation">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </li>

        <li>
          <a href="<?= BASE_URL ?>/index.php" class="nav-link <?= in_array($currPage, ['index.php', '']) ? 'active' : '' ?>">
            <i class="fa-solid fa-house-chimney"></i> <span>Home</span>
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/courses.php" class="nav-link <?= in_array($currPage, ['courses.php', 'course.php', 'checkout_stripe.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-graduation-cap"></i> <span>Courses</span>
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/services.php" class="nav-link <?= $currPage === 'services.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-wand-magic-sparkles"></i> <span>Services</span>
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/blog.php" class="nav-link <?= in_array($currPage, ['blog.php', 'blog-post.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-newspaper"></i> <span>Daily Blogs</span>
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/zodiac.php" class="nav-link <?= $currPage === 'zodiac.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-compass"></i> <span>Zodiac Wheel</span>
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/about.php" class="nav-link <?= $currPage === 'about.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-user-astronaut"></i> <span>About Guide</span>
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>/contact.php" class="nav-link <?= $currPage === 'contact.php' ? 'active' : '' ?>">
            <i class="fa-regular fa-envelope"></i> <span>Contact</span>
          </a>
        </li>
        
        <!-- Special Astrobot Nav Item with Cosmic Shimmer -->
        <li>
          <a href="<?= BASE_URL ?>/ai.php" class="nav-link nav-link-ai <?= $currPage === 'ai.php' ? 'active' : '' ?>">
            <span class="ai-live-dot"></span>
            <i class="fa-solid fa-sparkles"></i> 
            <span>Talk with AI</span>
            <span class="nav-ai-pill">24/7 ORACLE</span>
          </a>
        </li>

        <!-- Mobile-Only Drawer Actions -->
        <li class="mobile-nav-actions">
          <a href="<?= BASE_URL ?>/booking.php" class="btn-primary" style="width:100%; text-align:center;">
            <i class="fa-regular fa-calendar-check"></i> Book Consultation
          </a>

          <?php if ($currentUser): ?>
            <div class="mobile-user-card">
              <div class="user-chip-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
              <div style="flex:1; overflow:hidden;">
                <strong style="color:#fff; display:block; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;"><?= htmlspecialchars($currentUser['name']) ?></strong>
                <small style="color:var(--gold-light); text-transform:uppercase; font-size:0.72rem;"><?= htmlspecialchars($currentUser['role']) ?></small>
              </div>
            </div>

            <?php if ($currentUser['role'] === 'admin'): ?>
              <a href="<?= BASE_URL ?>/admin/index.php" class="btn-outline" style="width:100%; text-align:center;">
                <i class="fa-solid fa-gauge-high"></i> Admin Portal
              </a>
              <a href="<?= BASE_URL ?>/admin/index.php?tab=bot" class="btn-outline" style="width:100%; text-align:center; border-color:rgba(99,91,255,0.4); color:#a39cfd;">
                <i class="fa-solid fa-sparkles"></i> Astrobot Intelligence
              </a>
            <?php else: ?>
              <a href="<?= BASE_URL ?>/student/index.php" class="btn-outline" style="width:100%; text-align:center;">
                <i class="fa-solid fa-graduation-cap"></i> Student Portal
              </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/logout.php" class="btn-outline" style="width:100%; text-align:center; border-color:rgba(239,68,68,0.4); color:#ff8a80;">
              <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
            </a>
          <?php else: ?>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; width:100%;">
              <a href="<?= BASE_URL ?>/login.php" class="btn-outline" style="text-align:center;">
                <i class="fa-regular fa-user"></i> Login
              </a>
              <a href="<?= BASE_URL ?>/signup.php" class="btn-primary" style="text-align:center;">
                <i class="fa-solid fa-user-plus"></i> Join
              </a>
            </div>
          <?php endif; ?>

          <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.08); text-align:center;">
            <a href="https://wa.me/14706505315" target="_blank" style="color:#25d366; font-size:0.85rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem;">
              <i class="fa-brands fa-whatsapp" style="font-size:1.1rem;"></i> WhatsApp: +1 (470) 650-5315
            </a>
          </div>
        </li>
      </ul>

      <!-- Desktop Action Cluster -->
      <div class="nav-actions">
        <!-- Quick Consultation CTA Button -->
        <a href="<?= BASE_URL ?>/booking.php" class="nav-btn-book desktop-only-action">
          <i class="fa-regular fa-calendar-check"></i>
          <span>Book Session</span>
        </a>

        <?php if ($currentUser): ?>
          <!-- Logged In User Dropdown Chip -->
          <div class="nav-user-dropdown" id="navUserDropdown">
            <button type="button" class="nav-user-chip" id="navUserBtn" aria-haspopup="true" aria-expanded="false">
              <span class="user-chip-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></span>
              <span class="user-chip-name"><?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?></span>
              <span class="user-chip-badge <?= $currentUser['role'] === 'admin' ? 'admin' : 'student' ?>">
                <?= ucfirst($currentUser['role']) ?>
              </span>
              <i class="fa-solid fa-chevron-down user-chip-arrow"></i>
            </button>

            <!-- Dropdown Menu -->
            <div class="nav-dropdown-menu" id="navDropdownMenu">
              <div class="dropdown-user-header">
                <strong><?= htmlspecialchars($currentUser['name']) ?></strong>
                <small><?= htmlspecialchars($currentUser['email']) ?></small>
              </div>
              <div class="dropdown-divider"></div>

              <?php if ($currentUser['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>/admin/index.php" class="dropdown-item">
                  <i class="fa-solid fa-gauge-high"></i> Admin Portal
                </a>
                <a href="<?= BASE_URL ?>/admin/index.php?tab=bot" class="dropdown-item">
                  <i class="fa-solid fa-sparkles" style="color:#a39cfd;"></i> Astrobot Seekers (11)
                </a>
                <a href="<?= BASE_URL ?>/admin/index.php?tab=audit" class="dropdown-item">
                  <i class="fa-solid fa-shield-halved" style="color:#00c853;"></i> System Audit Trail
                </a>
                <a href="<?= BASE_URL ?>/admin/settings.php" class="dropdown-item">
                  <i class="fa-solid fa-sliders"></i> Gateway Settings
                </a>
              <?php else: ?>
                <a href="<?= BASE_URL ?>/student/index.php" class="dropdown-item">
                  <i class="fa-solid fa-graduation-cap"></i> Student Portal
                </a>
                <a href="<?= BASE_URL ?>/student/courses.php" class="dropdown-item">
                  <i class="fa-solid fa-book-open"></i> My Courses
                </a>
              <?php endif; ?>

              <a href="<?= BASE_URL ?>/ai.php" class="dropdown-item">
                <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--gold);"></i> Talk with Malik AI
              </a>

              <div class="dropdown-divider"></div>
              <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item dropdown-logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
              </a>
            </div>
          </div>
        <?php else: ?>
          <!-- Guest Login Link -->
          <a href="<?= BASE_URL ?>/login.php" class="nav-login-link desktop-only-action">
            <i class="fa-regular fa-user"></i>
            <span>Sign In</span>
          </a>
        <?php endif; ?>

        <!-- Responsive Hamburger Button -->
        <button class="hamburger-btn" id="hamburger-btn" aria-label="Toggle navigation" aria-expanded="false">
          <span class="hamburger-line"></span>
          <span class="hamburger-line"></span>
          <span class="hamburger-line"></span>
        </button>
      </div>

    </div>
  </nav>

  <!-- Mobile Backdrop Overlay -->
  <div class="nav-backdrop" id="nav-backdrop"></div>
