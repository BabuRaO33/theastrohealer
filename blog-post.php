<?php
/**
 * Single Blog Post Viewer — The Astro Healer
 * Rich SEO Article Presentation with Schema.org & FAQ Markup
 */

require_once __DIR__ . '/includes/blog_data.php';

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    header('Location: ' . BASE_URL . '/blog.php');
    exit;
}

$blog = getBlogBySlug($slug);

if (!$blog) {
    // 404 Fallback
    http_response_code(404);
    $pageTitle = 'Cosmic Insight Not Found — The Astro Healer';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="blog-hero" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
      <div class="container" style="text-align: center;">
        <div style="font-size: 3.5rem; color: var(--gold); margin-bottom: 1rem;"><i class="fa-solid fa-moon"></i></div>
        <h1 style="font-family: var(--font-heading); color: #fff; margin-bottom: 1rem;">Celestial Article Not Found</h1>
        <p style="color: var(--text-secondary); max-width: 550px; margin: 0 auto 2rem; line-height: 1.7;">
          The planetary transit or insight you are searching for might have shifted its cosmic coordinates or been archived.
        </p>
        <a href="<?= BASE_URL ?>/blog.php" class="btn-primary">
          <i class="fa-solid fa-arrow-left"></i> Return to Daily Cosmic Blogs
        </a>
      </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Prepare dynamic SEO tags
$rawTitle = $blog['meta_title'] ?? $blog['title'];
$pageTitle = (stripos($rawTitle, 'The Astro Healer') === false) ? ($rawTitle . ' | The Astro Healer') : $rawTitle;
$pageDescription = $blog['meta_description'] ?? $blog['excerpt'];
$pageKeywords = $blog['keywords'] ?? 'Vedic astrology, Numerology, Vastu Shastra, Malik Mehboob';
$canonicalUrl = BASE_URL . '/blog-post.php?slug=' . urlencode($blog['slug']);
$ogImage = BASE_URL . '/' . ltrim($blog['image'] ?? 'assets/images/courses/numerology.jpg', '/');
$ogType = 'article';

// Generate Schema.org JSON-LD (BlogPosting, Breadcrumbs, and FAQPage)
$schemaGraph = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'BlogPosting',
            '@id' => $canonicalUrl . '#article',
            'isPartOf' => [
                '@type' => 'WebPage',
                '@id' => $canonicalUrl
            ],
            'headline' => $blog['title'],
            'description' => $blog['excerpt'],
            'image' => [
                '@type' => 'ImageObject',
                'url' => $ogImage,
                'width' => 1200,
                'height' => 630
            ],
            'datePublished' => $blog['published_date'] . 'T08:00:00+05:00',
            'dateModified' => $blog['published_date'] . 'T08:00:00+05:00',
            'mainEntityOfPage' => $canonicalUrl,
            'author' => [
                '@type' => 'Person',
                'name' => $blog['author'] ?? 'Malik Mehboob',
                'jobTitle' => 'Master Vedic Astrologer & Numerologist',
                'url' => BASE_URL . '/about.php'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'The Astro Healer',
                'url' => BASE_URL . '/',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => BASE_URL . '/assets/images/logo.png'
                ]
            ],
            'articleSection' => $blog['category'],
            'keywords' => $blog['keywords'] ?? ''
        ],
        [
            '@type' => 'BreadcrumbList',
            '@id' => $canonicalUrl . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => BASE_URL . '/'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Daily Cosmic Blogs',
                    'item' => BASE_URL . '/blog.php'
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $blog['category'],
                    'item' => BASE_URL . '/blog.php?category=' . urlencode($blog['category'])
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 4,
                    'name' => $blog['title'],
                    'item' => $canonicalUrl
                ]
            ]
        ]
    ]
];

// Add FAQPage Schema if FAQs exist
if (!empty($blog['faqs']) && is_array($blog['faqs'])) {
    $faqEntities = [];
    foreach ($blog['faqs'] as $faq) {
        $q = $faq['question'] ?? $faq['q'] ?? '';
        $a = $faq['answer'] ?? $faq['a'] ?? '';
        if (!empty($q) && !empty($a)) {
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a
                ]
            ];
        }
    }
    if (!empty($faqEntities)) {
        $schemaGraph['@graph'][] = [
            '@type' => 'FAQPage',
            '@id' => $canonicalUrl . '#faq',
            'mainEntity' => $faqEntities
        ];
    }
}

$customJsonLd = json_encode($schemaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Fetch All Blogs for Prev/Next navigation & Related posts
$allBlogsList = getAllBlogs();
$currIndex = -1;
foreach ($allBlogsList as $idx => $b) {
    if ($b['slug'] === $blog['slug']) {
        $currIndex = $idx;
        break;
    }
}
$prevBlog = ($currIndex > 0 && isset($allBlogsList[$currIndex - 1])) ? $allBlogsList[$currIndex - 1] : null;
$nextBlog = ($currIndex >= 0 && isset($allBlogsList[$currIndex + 1])) ? $allBlogsList[$currIndex + 1] : null;

// Related posts (same category or closest dates)
$relatedBlogs = array_filter($allBlogsList, function($b) use ($blog) {
    return $b['slug'] !== $blog['slug'] && ($b['category'] === $blog['category'] || empty($b['category']));
});
$relatedBlogs = array_slice(array_values($relatedBlogs), 0, 3);
if (count($relatedBlogs) < 3) {
    // Fill with any other posts
    foreach ($allBlogsList as $b) {
        if ($b['slug'] !== $blog['slug'] && !in_array($b, $relatedBlogs, true)) {
            $relatedBlogs[] = $b;
            if (count($relatedBlogs) >= 3) break;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ARTICLE HERO BANNER -->
<section class="blog-hero" style="padding-bottom: 2rem;">
  <div class="container">
    
    <!-- BREADCRUMBS -->
    <nav style="display: flex; gap: 0.5rem; align-items: center; justify-content: center; font-size: 0.85rem; color: var(--muted); margin-bottom: 1.5rem; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>/index.php" style="color:var(--muted); text-decoration:none;"><i class="fa-solid fa-house"></i> Home</a>
      <span>/</span>
      <a href="<?= BASE_URL ?>/blog.php" style="color:var(--muted); text-decoration:none;">Daily Cosmic Blogs</a>
      <span>/</span>
      <a href="<?= BASE_URL ?>/blog.php?category=<?= urlencode($blog['category']) ?>" style="color:var(--gold-light); text-decoration:none;">
        <?= htmlspecialchars($blog['category']) ?>
      </a>
    </nav>

    <!-- CATEGORY BADGE -->
    <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(245, 158, 11, 0.15); border: 1px solid var(--gold); color: var(--gold-bright); padding: 0.4rem 1.1rem; border-radius: 50px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 1.2rem;">
      <i class="fa-solid fa-sparkles"></i> <?= htmlspecialchars($blog['category']) ?> Wisdom
    </div>

    <!-- ARTICLE TITLE -->
    <h1 style="font-family: var(--font-heading); font-size: clamp(2rem, 3.8vw, 3.2rem); line-height: 1.3; max-width: 960px; margin: 0 auto 1.5rem; color: #ffffff;">
      <?= htmlspecialchars($blog['title']) ?>
    </h1>

    <div class="gold-divider"><span>✦</span></div>

    <!-- AUTHOR & PUBLISH METADATA -->
    <div style="display: flex; align-items: center; justify-content: center; gap: 1.5rem; flex-wrap: wrap; font-size: 0.9rem; color: var(--muted); margin-top: 1.2rem;">
      <div style="display: flex; align-items: center; gap: 0.55rem;">
        <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--navy); border: 1.5px solid var(--gold); display: flex; align-items: center; justify-content: center; color: var(--gold-bright); font-weight: 700; font-size: 0.85rem;">
          M
        </div>
        <span style="color: #fff; font-weight: 600;">
          <?= htmlspecialchars($blog['author'] ?? 'Malik Mehboob') ?>
          <i class="fa-solid fa-circle-check" style="color: var(--gold); font-size: 0.8rem; margin-left: 2px;" title="Verified Astrologer"></i>
        </span>
      </div>
      <span>&bull;</span>
      <span><i class="fa-regular fa-calendar-days" style="color:var(--gold);"></i> <?= date('F j, Y', strtotime($blog['published_date'])) ?></span>
      <span>&bull;</span>
      <span><i class="fa-regular fa-clock" style="color:var(--gold);"></i> <?= htmlspecialchars($blog['read_time'] ?? '5 min read') ?></span>
      <?php if (!empty($blog['views'])): ?>
        <span>&bull;</span>
        <span><i class="fa-regular fa-eye" style="color:var(--gold);"></i> <?= number_format($blog['views']) ?> reads</span>
      <?php endif; ?>
    </div>

  </div>
</section>

<!-- MAIN ARTICLE SECTION -->
<section style="padding: 2.5rem 0 6rem; background: var(--deep); position: relative; z-index: 1;">
  <div class="container">
    <div class="blog-layout">

      <!-- LEFT COLUMN: ARTICLE BODY -->
      <article class="blog-article-wrap">

        <!-- SOCIAL SHARING STRIP -->
        <div class="blog-share-strip">
          <span style="font-size: 0.85rem; color: var(--muted); font-weight: 600; margin-right: 0.5rem;">
            <i class="fa-solid fa-share-nodes"></i> Share Wisdom:
          </span>

          <?php 
          $shareUrl = urlencode($canonicalUrl);
          $shareText = urlencode($blog['title'] . ' | The Astro Healer');
          ?>

          <a href="https://api.whatsapp.com/send?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-wa">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp
          </a>

          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-fb">
            <i class="fa-brands fa-facebook-f"></i> Facebook
          </a>

          <a href="https://twitter.com/intent/tweet?text=<?= $shareText ?>&url=<?= $shareUrl ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-tw">
            <i class="fa-brands fa-x-twitter"></i> Tweet
          </a>

          <button type="button" class="share-btn share-copy" onclick="copyArticleLink(this)" data-url="<?= htmlspecialchars($canonicalUrl) ?>">
            <i class="fa-regular fa-copy"></i> <span>Copy Link</span>
          </button>
        </div>

        <!-- ARTICLE EXCERPT LEAD -->
        <div style="font-size: 1.15rem; font-style: italic; color: var(--gold-light); line-height: 1.8; margin-bottom: 2.2rem; padding: 1.2rem 1.6rem; background: rgba(255,255,255,0.02); border-left: 3px solid var(--gold); border-radius: 4px;">
          &ldquo;<?= htmlspecialchars($blog['excerpt']) ?>&rdquo;
        </div>

        <!-- FULL ARTICLE CONTENT -->
        <div class="blog-article-content">
          <?= $blog['content'] ?>
        </div>

        <!-- INTERACTIVE FREQUENTLY ASKED QUESTIONS (FAQS) -->
        <?php if (!empty($blog['faqs']) && is_array($blog['faqs'])): ?>
          <section class="blog-faq-section" id="faq">
            <h3 style="font-family: var(--font-heading); color: #fff; font-size: 1.45rem; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.6rem;">
              <i class="fa-solid fa-circle-question" style="color: var(--gold-bright);"></i> Frequently Asked Questions
            </h3>

            <div class="blog-faq-list">
              <?php foreach ($blog['faqs'] as $fIdx => $faq): ?>
                <?php 
                  $faqQ = $faq['question'] ?? $faq['q'] ?? '';
                  $faqA = $faq['answer'] ?? $faq['a'] ?? '';
                  if (empty($faqQ) || empty($faqA)) continue;
                ?>
                <div class="blog-faq-item <?= $fIdx === 0 ? 'active' : '' ?>">
                  <div class="blog-faq-header" onclick="toggleFaq(this)">
                    <span><?= htmlspecialchars($faqQ) ?></span>
                    <i class="fa-solid fa-chevron-down"></i>
                  </div>
                  <div class="blog-faq-body">
                    <p><?= htmlspecialchars($faqA) ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- AUTHOR AUTHORITY BIO BOX -->
        <div style="background: linear-gradient(135deg, rgba(255,255,255,0.03) 0%, rgba(245, 158, 11, 0.05) 100%); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 16px; padding: 1.8rem; margin: 2.5rem 0; display: flex; gap: 1.4rem; align-items: center; flex-wrap: wrap;">
          <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--navy); border: 2px solid var(--gold); display: flex; align-items: center; justify-content: center; color: var(--gold-bright); font-size: 1.8rem; font-weight: 700; flex-shrink: 0;">
            M
          </div>
          <div style="flex: 1; min-width: 250px;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
              <h4 style="margin: 0; color: #fff; font-size: 1.15rem; font-family: var(--font-heading);"><?= htmlspecialchars($blog['author'] ?? 'Malik Mehboob') ?></h4>
              <span style="background: rgba(245, 158, 11, 0.15); color: var(--gold-bright); font-size: 0.72rem; padding: 0.2rem 0.6rem; border-radius: 50px; font-weight: 600;">Master Astrologer</span>
            </div>
            <p style="font-size: 0.86rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 0.75rem;">
              Founder of <em>The Astro Healer</em> and certified Vedic Astrologer &amp; Pythagorean Numerologist. Malik Mehboob specializes in Bhrigu Nandi Nadi (BNN) precision forecasting, Astro-Vastu alignment, and ethical remedial solutions for seekers across the globe.
            </p>
            <div style="display: flex; gap: 0.8rem; font-size: 0.82rem;">
              <a href="<?= BASE_URL ?>/about.php" style="color: var(--gold-light); text-decoration: none; font-weight: 600;">
                <i class="fa-solid fa-user-astronaut"></i> Learn More About Malik &rarr;
              </a>
            </div>
          </div>
        </div>

        <!-- HIGH-CONVERTING BOTTOM CONSULTATION CTA BANNER -->
        <div style="background: radial-gradient(circle at center, rgba(139, 92, 246, 0.2), rgba(11, 16, 48, 0.95) 80%); border: 1.5px solid var(--gold); border-radius: 18px; padding: 2.2rem; text-align: center; margin-top: 2rem; box-shadow: 0 15px 40px rgba(0,0,0,0.6);">
          <span style="color: var(--gold-bright); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 0.5rem;">
            ✦ Personalized Planetary Guidance ✦
          </span>
          <h3 style="font-family: var(--font-heading); font-size: clamp(1.4rem, 2.5vw, 1.9rem); color: #fff; margin-bottom: 0.8rem;">
            Want To Know What This Means For Your Personal Zaicha?
          </h3>
          <p style="color: var(--text-secondary); font-size: 0.96rem; max-width: 650px; margin: 0 auto 1.6rem; line-height: 1.65;">
            General transits set the collective tone, but your natal chart holds the exact timeline of your breakthroughs. Book a private 1-on-1 session with Malik Mehboob or consult our 24/7 AI Oracle Chamber.
          </p>
          <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>/booking.php" class="btn-primary">
              <i class="fa-regular fa-calendar-check"></i> Book 1-on-1 Zaicha Reading
            </a>
            <a href="<?= BASE_URL ?>/ai.php" class="btn-outline" style="border-color: rgba(139, 92, 246, 0.5); color: #c4b5fd;">
              <i class="fa-solid fa-wand-magic-sparkles"></i> Talk to Malik AI Oracle
            </a>
          </div>
        </div>

        <!-- PREV / NEXT NAVIGATION -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.08);">
          <?php if ($prevBlog): ?>
            <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($prevBlog['slug']) ?>" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1.2rem; text-decoration: none; color: inherit; display: flex; flex-direction: column; transition: var(--transition);">
              <small style="color: var(--muted); font-size: 0.74rem; text-transform: uppercase; margin-bottom: 0.35rem;">
                <i class="fa-solid fa-arrow-left"></i> Previous Forecast
              </small>
              <strong style="color: #fff; font-size: 0.92rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                <?= htmlspecialchars($prevBlog['title']) ?>
              </strong>
            </a>
          <?php else: ?>
            <div></div>
          <?php endif; ?>

          <?php if ($nextBlog): ?>
            <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($nextBlog['slug']) ?>" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1.2rem; text-decoration: none; color: inherit; display: flex; flex-direction: column; text-align: right; transition: var(--transition);">
              <small style="color: var(--muted); font-size: 0.74rem; text-transform: uppercase; margin-bottom: 0.35rem;">
                Next Forecast <i class="fa-solid fa-arrow-right"></i>
              </small>
              <strong style="color: #fff; font-size: 0.92rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                <?= htmlspecialchars($nextBlog['title']) ?>
              </strong>
            </a>
          <?php else: ?>
            <div></div>
          <?php endif; ?>
        </div>

      </article>

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
            Ask questions about this forecast, planetary yogas, or your birth numbers directly to our AI assistant.
          </p>
          <a href="<?= BASE_URL ?>/ai.php" class="btn-primary btn-sm" style="width: 100%; text-align: center; background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%);">
            <i class="fa-solid fa-sparkles"></i> Consult AI Chamber
          </a>
        </div>

        <!-- 2. Master Consultation Booking -->
        <div class="blog-widget">
          <h4 class="blog-widget-title"><i class="fa-solid fa-calendar-check"></i> Private Zaicha Reading</h4>
          <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 1.2rem;">
            Detailed birth chart reading with Malik Mehboob covering career, marriage, finances, and gemstone recommendations.
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
            Enroll in structured certificate courses with lifetime video lecture access and curriculum materials.
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

    <!-- RELATED ARTICLES SECTION -->
    <?php if (!empty($relatedBlogs)): ?>
      <div style="margin-top: 4rem; padding-top: 3rem; border-top: 1px solid rgba(255,255,255,0.08);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
          <div>
            <span class="section-label" style="text-align: left; margin: 0 0 0.4rem;"><i class="fa-solid fa-sparkles"></i> Continue Exploring</span>
            <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.8rem; margin: 0;">Related Cosmic Wisdom</h2>
          </div>
          <a href="<?= BASE_URL ?>/blog.php" class="btn-outline btn-sm">
            View All Blogs <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>

        <div class="blogs-grid">
          <?php foreach ($relatedBlogs as $rb): ?>
            <article class="blog-card">
              <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($rb['slug']) ?>" class="blog-card-thumb-wrap" tabindex="-1" aria-hidden="true">
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($rb['image'] ?? 'assets/images/courses/numerology.jpg') ?>" alt="<?= htmlspecialchars($rb['title']) ?>" class="blog-card-thumb" loading="lazy">
                <span class="blog-card-badge">
                  <i class="fa-solid fa-sparkles"></i> <?= htmlspecialchars($rb['category']) ?>
                </span>
              </a>

              <div class="blog-card-body">
                <div class="blog-card-meta">
                  <span><i class="fa-regular fa-calendar-days"></i> <?= date('M j, Y', strtotime($rb['published_date'])) ?></span>
                  <span>&bull;</span>
                  <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($rb['read_time']) ?></span>
                </div>

                <h3 class="blog-card-title">
                  <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($rb['slug']) ?>" style="color:inherit; text-decoration:none;">
                    <?= htmlspecialchars($rb['title']) ?>
                  </a>
                </h3>

                <p class="blog-card-excerpt">
                  <?= htmlspecialchars($rb['excerpt']) ?>
                </p>

                <div class="blog-card-footer">
                  <span class="blog-card-author">
                    <i class="fa-solid fa-user-check" style="color:var(--gold);"></i> <?= htmlspecialchars($rb['author'] ?? 'Malik Mehboob') ?>
                  </span>
                  <a href="<?= BASE_URL ?>/blog-post.php?slug=<?= htmlspecialchars($rb['slug']) ?>" class="blog-read-link">
                    Read <i class="fa-solid fa-chevron-right"></i>
                  </a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- SCRIPT FOR ACCORDION & COPY LINK -->
<script>
function toggleFaq(header) {
  const item = header.closest('.blog-faq-item');
  if (item) {
    item.classList.toggle('active');
  }
}

function copyArticleLink(btn) {
  const url = btn.getAttribute('data-url') || window.location.href;
  navigator.clipboard.writeText(url).then(() => {
    const span = btn.querySelector('span');
    const orig = span ? span.textContent : '';
    if (span) span.textContent = 'Copied!';
    btn.style.background = '#22c55e';
    setTimeout(() => {
      if (span) span.textContent = orig;
      btn.style.background = '';
    }, 2200);
  }).catch(() => {
    prompt('Copy link:', url);
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
