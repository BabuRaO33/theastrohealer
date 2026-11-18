<?php
/**
 * Daily Cosmic Blogs — The Astro Healer
 * Vedic Astrology, Numerology & Vastu Shastra Insights by Malik Mehboob
 */

if (!empty($_GET['slug'])) {
    require_once __DIR__ . '/blog-post.php';
    exit;
}

require_once __DIR__ . '/includes/blog_data.php';

$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

$allBlogs = getAllBlogs($category, $search);

$pageTitle = 'Daily Cosmic Blogs: Vedic Astrology, Numerology & Vastu Insights | The Astro Healer';
$pageDescription = 'Read daily celestial forecasts, Vedic astrology transits, Numerology master numbers, and Vastu Shastra remedies curated by Master Astrologer Malik Mehboob.';
$pageKeywords = 'Vedic astrology blog, daily cosmic forecast, planetary transits November 2026, numerology life path, Vastu Shastra remedies, Malik Mehboob, Zaicha analysis';
$canonicalUrl = BASE_URL . '/blog.php';
$ogType = 'website';

// If category is filtered, adjust page title
if (!empty($category) && $category !== 'all') {
    $pageTitle = htmlspecialchars(ucfirst($category)) . ' Insights & Cosmic Wisdom | The Astro Healer Blogs';
    $pageDescription = 'In-depth ' . htmlspecialchars($category) . ' guides, daily celestial wisdom, and spiritual remedies curated by Master Astrologer Malik Mehboob.';
}

$blogListItems = [];
foreach (array_slice($allBlogs, 0, 10) as $bIdx => $b) {
    $blogListItems[] = [
        '@type' => 'ListItem',
        'position' => $bIdx + 1,
        'url' => BASE_URL . '/blog-post.php?slug=' . urlencode($b['slug']),
        'name' => $b['title']
    ];
}
$blogSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Daily Cosmic Blogs — The Astro Healer',
    'description' => 'Daily astrological transits, numerology readings, and Vastu Shastra space cures by Malik Mehboob.',
    'mainEntity' => [
        '@type' => 'ItemList',
        'name' => 'Recent Daily Cosmic Forecasts',
        'itemListElement' => $blogListItems
    ]
];
$customJsonLd = json_encode($blogSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

require_once __DIR__ . '/includes/header.php';

// If no category/search filter, pick first blog as Featured Today
$featuredBlog = null;
$gridBlogs = $allBlogs;

if (empty($category) && empty($search) && count($allBlogs) > 0) {
    $featuredBlog = $allBlogs[0];
    $gridBlogs = array_slice($allBlogs, 1);
}
?>

<!-- BREADCRUMB / HERO SECTION -->
<section class="blog-hero">
  <div class="container">
    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; font-size: 0.85rem; color: var(--muted); margin-bottom: 1.2rem;">
      <a href="<?= BASE_URL ?>/index.php" style="color:var(--muted); text-decoration:none;"><i class="fa-solid fa-house"></i> Home</a>
      <span>/</span>
      <span style="color: var(--gold-light);">Daily Cosmic Blogs</span>
      <?php if (!empty($category)): ?>
        <span>/</span>
        <span style="color: var(--gold-bright);"><?= htmlspecialchars(ucfirst($category)) ?></span>
      <?php endif; ?>
    </div>

    <span class="section-label"><i class="fa-solid fa-moon"></i> Daily Celestial Wisdom &amp; Guidance</span>
    <h1 style="font-size: clamp(2.2rem, 4.2vw, 3.5rem); margin-bottom: 0.8rem; font-family: var(--font-heading);">
      Daily Cosmic Insights &amp; Remedies
    </h1>
    <div class="gold-divider"><span>✦</span></div>
    <p style="max-width: 760px; margin: 0 auto; font-size: 1.05rem; color: var(--text-secondary); line-height: 1.7;">
      Authentic Vedic Astrology transits, Pythagorean &amp; Chaldean Numerology revelations, and Vastu Shastra space remedies by <strong>Malik Mehboob</strong> to illuminate your daily destiny.
    </p>
  </div>
</section>

<!-- MAIN BLOG CONTAINER -->
<section style="padding: 3rem 0 6rem; background: var(--deep); position: relative; z-index: 1;">
  <div class="container">

    <!-- CATEGORIES & SEARCH FILTER BAR -->
    <div class="blog-filter-bar">
      <div class="blog-cats">
        <a href="<?= BASE_URL ?>/blog.php" class="blog-cat-btn <?= empty($category) || $category === 'all' ? 'active' : '' ?>">
          <i class="fa-solid fa-sparkles"></i> All Wisdom (<?= count(getStaticDailyBlogs()) ?>)
        </a>
        <a href="<?= BASE_URL ?>/blog.php?category=Astrology" class="blog-cat-btn <?= strcasecmp($category, 'Astrology') === 0 ? 'active' : '' ?>">
          <i class="fa-solid fa-sun"></i> Astrology
        </a>
        <a href="<?= BASE_URL ?>/blog.php?category=Numerology" class="blog-cat-btn <?= strcasecmp($category, 'Numerology') === 0 ? 'active' : '' ?>">
          <i class="fa-solid fa-hashtag"></i> Numerology
        </a>
        <a href="<?= BASE_URL ?>/blog.php?category=Vastu" class="blog-cat-btn <?= strcasecmp($category, 'Vastu') === 0 ? 'active' : '' ?>">
          <i class="fa-solid fa-compass"></i> Vastu Shastra
        </a>
      </div>

      <form action="<?= BASE_URL ?>/blog.php" method="GET" class="blog-search-form">
        <?php if (!empty($category)): ?>
          <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <?php endif; ?>
        <input type="text" name="search" class="blog-search-input" placeholder="Search cosmic topics..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="blog-search-submit" aria-label="Search">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
      </form>
    </div>

    <!-- ACTIVE FILTER NOTICE -->
    <?php if (!empty($search) || !empty($category)): ?>
      <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 0.9rem 1.4rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.8rem;">
        <div style="font-size: 0.92rem; color: #fff;">
          Showing results for
          <?php if (!empty($category)): ?>
            category <strong style="color:var(--gold-bright);"><?= htmlspecialchars(ucfirst($category)) ?></strong>
          <?php endif; ?>
          <?php if (!empty($search)): ?>
            keyword <strong style="color:var(--gold-bright);">&ldquo;<?= htmlspecialchars($search) ?>&rdquo;</strong>
          <?php endif; ?>
          (<?= count($allBlogs) ?> found)
        </div>
        <a href="<?= BASE_URL ?>/blog.php" class="btn-outline btn-sm" style="font-size: 0.78rem; padding: 0.35rem 0.8rem;">
          <i class="fa-solid fa-xmark"></i> Clear Filters
        </a>
      </div>
    <?php endif; ?>

    <!-- FEATURED ARTICLE OF THE DAY (When on main view) -->
    <?php if ($featuredBlog): ?>
      <div class="blog-featured-card">
        <div class="blog-featured-img-wrap">
          <img src="<?= BASE_URL ?>/<?= htmlspecialchars($featuredBlog['image'] ?? 'assets/images/courses/basic-astrology.jpg') ?>" alt="<?= htmlspecialchars($featuredBlog['title']) ?>" class="blog-featured-img">
          <div class="blog-card-badge" style="top: 20px; left: 20px; background: rgba(245, 158, 11, 0.92); color: #040614;">
            <i class="fa-solid fa-star"></i> TODAY'S CELESTIAL FOCUS
          </div>
        </div>

        <div class="blog-featured-body">
          <div class="blog-card-meta">
            <span><i class="fa-regular fa-calendar-days"></i> <?= date('F j, Y', strtotime($featuredBlog['published_date'])) ?></span>
            <span>&bull;</span>
            <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($featuredBlog['read_time']) ?></span>
            <span>&bull;</span>
            <span style="color: var(--gold-light);"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($featuredBlog['category']) ?></span>
          </div>

          <h2 style="font-family: var(--font-heading); font-size: clamp(1.4rem, 2.2vw, 1.85rem); line-height: 1.35; margin-bottom: 0.9rem; color: #ffffff;">
            <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($featuredBlog['slug']) ?>" style="color: inherit; text-decoration: none;">
              <?= htmlspecialchars($featuredBlog['title']) ?>
            </a>
          </h2>

          <p style="color: var(--text-secondary); font-size: 0.96rem; line-height: 1.68; margin-bottom: 1.6rem;">
            <?= htmlspecialchars($featuredBlog['excerpt']) ?>
          </p>

          <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding-top: 1.2rem; border-top: 1px solid rgba(255,255,255,0.08);">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
              <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--navy); border: 1.5px solid var(--gold); display: flex; align-items: center; justify-content: center; color: var(--gold-bright); font-weight: 700; font-size: 0.9rem;">
                M
              </div>
              <div>
                <strong style="color: #fff; font-size: 0.88rem; display: block;"><?= htmlspecialchars($featuredBlog['author'] ?? 'Malik Mehboob') ?></strong>
                <small style="color: var(--muted); font-size: 0.74rem;">Master Vedic Astrologer</small>
              </div>
            </div>

            <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($featuredBlog['slug']) ?>" class="btn-primary btn-sm">
              Read Forecast <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- 2-COLUMN MAIN CONTENT & SIDEBAR -->
    <div class="blog-layout">

      <!-- LEFT COLUMN: BLOGS GRID -->
      <div>
        <?php if (empty($gridBlogs) && !$featuredBlog): ?>
          <div style="background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 4rem 2rem; text-align: center;">
            <div style="font-size: 3rem; color: var(--gold); margin-bottom: 1rem;">
              <i class="fa-solid fa-compass"></i>
            </div>
            <h3 style="color: #fff; font-family: var(--font-heading); font-size: 1.5rem; margin-bottom: 0.8rem;">No Cosmic Insights Found</h3>
            <p style="color: var(--text-secondary); max-width: 500px; margin: 0 auto 1.5rem;">
              We couldn't find any articles matching your search criteria. Try a different keyword or explore all articles.
            </p>
            <a href="<?= BASE_URL ?>/blog.php" class="btn-primary btn-sm">
              <i class="fa-solid fa-rotate-left"></i> View All Articles
            </a>
          </div>
        <?php else: ?>
          <div class="blogs-grid">
            <?php foreach ($gridBlogs as $b): ?>
              <article class="blog-card">
                <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($b['slug']) ?>" class="blog-card-thumb-wrap" tabindex="-1" aria-hidden="true">
                  <img src="<?= BASE_URL ?>/<?= htmlspecialchars($b['image'] ?? 'assets/images/courses/numerology.jpg') ?>" alt="<?= htmlspecialchars($b['title']) ?>" class="blog-card-thumb" loading="lazy">
                  <span class="blog-card-badge">
                    <i class="fa-solid fa-sparkles"></i> <?= htmlspecialchars($b['category']) ?>
                  </span>
                </a>

                <div class="blog-card-body">
                  <div class="blog-card-meta">
                    <span><i class="fa-regular fa-calendar-days"></i> <?= date('M j, Y', strtotime($b['published_date'])) ?></span>
                    <span>&bull;</span>
                    <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($b['read_time']) ?></span>
                  </div>

                  <h3 class="blog-card-title">
                    <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($b['slug']) ?>" style="color:inherit; text-decoration:none;">
                      <?= htmlspecialchars($b['title']) ?>
                    </a>
                  </h3>

                  <p class="blog-card-excerpt">
                    <?= htmlspecialchars($b['excerpt']) ?>
                  </p>

                  <div class="blog-card-footer">
                    <span class="blog-card-author">
                      <i class="fa-solid fa-user-check" style="color:var(--gold);"></i> <?= htmlspecialchars($b['author'] ?? 'Malik Mehboob') ?>
                    </span>
                    <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($b['slug']) ?>" class="blog-read-link">
                      Read <i class="fa-solid fa-chevron-right"></i>
                    </a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- RIGHT COLUMN: SIDEBAR WIDGETS -->
      <aside class="blog-sidebar">

        <!-- 1. Astrobot 24/7 AI Oracle Chamber -->
        <div class="blog-widget" style="background: radial-gradient(circle at top right, rgba(139, 92, 246, 0.22), var(--card-bg) 70%); border-color: rgba(139, 92, 246, 0.4);">
          <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1rem;">
            <div style="width: 36px; height: 36px; border-radius: 50%; background: #8b5cf6; display: flex; align-items: center; justify-content: center; color: #fff;">
              <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <div>
              <h4 style="margin: 0; color: #fff; font-size: 1.05rem; font-family: var(--font-heading);">Malik AI Oracle</h4>
              <small style="color: #c4b5fd; font-size: 0.75rem;"><span class="concierge-status-dot" style="background:#a855f7;"></span> Online 24/7</small>
            </div>
          </div>
          <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 1.2rem;">
            Have questions about today's planetary transit or your zodiac sign? Consult our Vedic AI chamber right now.
          </p>
          <a href="<?= BASE_URL ?>/ai.php" class="btn-primary btn-sm" style="width: 100%; text-align: center; background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%);">
            <i class="fa-solid fa-sparkles"></i> Consult AI Chamber
          </a>
        </div>

        <!-- 2. Master Consultation Booking -->
        <div class="blog-widget">
          <h4 class="blog-widget-title"><i class="fa-solid fa-calendar-check"></i> Private Zaicha Reading</h4>
          <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 1.2rem;">
            Decode your complete natal horoscope, planetary dasha periods, and career timeline directly with <strong>Malik Mehboob</strong>.
          </p>
          <ul style="list-style: none; padding: 0; margin: 0 0 1.2rem; font-size: 0.82rem; color: var(--muted);">
            <li style="margin-bottom: 0.4rem;"><i class="fa-solid fa-check" style="color: var(--gold);"></i> 60-Min In-Depth Private Session</li>
            <li style="margin-bottom: 0.4rem;"><i class="fa-solid fa-check" style="color: var(--gold);"></i> Precise Planetary Remedies</li>
            <li style="margin-bottom: 0.4rem;"><i class="fa-solid fa-check" style="color: var(--gold);"></i> 100% Confidential Counsel</li>
          </ul>
          <a href="<?= BASE_URL ?>/booking.php" class="btn-primary btn-sm" style="width: 100%; text-align: center;">
            <i class="fa-regular fa-calendar-check"></i> Book Consultation
          </a>
        </div>

        <!-- 3. Certified Academy Courses -->
        <div class="blog-widget">
          <h4 class="blog-widget-title"><i class="fa-solid fa-graduation-cap"></i> Master Sacred Sciences</h4>
          <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 1rem;">
            Learn predictive BNN astrology and professional numerology with certified lifetime courses.
          </p>
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <a href="<?= BASE_URL ?>/course.php?slug=bnn-astrology" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; background: rgba(255,255,255,0.04); border-radius: 8px; text-decoration: none; color: #fff; font-size: 0.85rem; transition: var(--transition);">
              <span><i class="fa-solid fa-star" style="color:var(--gold); font-size:0.75rem; margin-right:0.4rem;"></i> BNN Astrology</span>
              <strong style="color:var(--gold-bright);">$399</strong>
            </a>
            <a href="<?= BASE_URL ?>/course.php?slug=numerology" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; background: rgba(255,255,255,0.04); border-radius: 8px; text-decoration: none; color: #fff; font-size: 0.85rem; transition: var(--transition);">
              <span><i class="fa-solid fa-hashtag" style="color:var(--gold); font-size:0.75rem; margin-right:0.4rem;"></i> Numerology Mastery</span>
              <strong style="color:var(--gold-bright);">$299</strong>
            </a>
            <a href="<?= BASE_URL ?>/course.php?slug=basic-astrology" style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; background: rgba(255,255,255,0.04); border-radius: 8px; text-decoration: none; color: #fff; font-size: 0.85rem; transition: var(--transition);">
              <span><i class="fa-solid fa-book-open" style="color:var(--gold); font-size:0.75rem; margin-right:0.4rem;"></i> Vedic Astrology 101</span>
              <strong style="color:var(--gold-bright);">$199</strong>
            </a>
          </div>
        </div>

        <!-- 4. Cosmic Topics Cloud -->
        <div class="blog-widget">
          <h4 class="blog-widget-title"><i class="fa-solid fa-tags"></i> Popular Cosmic Topics</h4>
          <div class="blog-tag-cloud">
            <a href="<?= BASE_URL ?>/blog.php?search=Scorpio" class="blog-tag">#ScorpioSeason</a>
            <a href="<?= BASE_URL ?>/blog.php?search=Saturn" class="blog-tag">#SaturnDirect</a>
            <a href="<?= BASE_URL ?>/blog.php?search=Remedies" class="blog-tag">#VedicRemedies</a>
            <a href="<?= BASE_URL ?>/blog.php?search=Zaicha" class="blog-tag">#ZaichaReading</a>
            <a href="<?= BASE_URL ?>/blog.php?search=Master+Number" class="blog-tag">#MasterNumbers</a>
            <a href="<?= BASE_URL ?>/blog.php?search=Vastu" class="blog-tag">#VastuCures</a>
            <a href="<?= BASE_URL ?>/blog.php?search=Kundali" class="blog-tag">#KundaliMatching</a>
            <a href="<?= BASE_URL ?>/blog.php?search=Karmic" class="blog-tag">#KarmicDebt</a>
            <a href="<?= BASE_URL ?>/blog.php?search=BNN" class="blog-tag">#BNNAstrology</a>
            <a href="<?= BASE_URL ?>/blog.php?search=Wealth" class="blog-tag">#WealthYogas</a>
          </div>
        </div>

      </aside>

    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
