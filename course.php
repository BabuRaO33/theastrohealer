<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/stripe.php';

$pdo = getDBConnection();
$slug = trim($_GET['slug'] ?? '');

// Fetch Course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE slug = ?");
$stmt->execute([$slug]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: ' . BASE_URL . '/index.php#courses');
    exit;
}

// Fetch all classes / lectures for this course
$lecStmt = $pdo->prepare("SELECT * FROM lectures WHERE course_id = ? ORDER BY class_number ASC");
$lecStmt->execute([$course['id']]);
$lectures = $lecStmt->fetchAll();

$currentUser = getCurrentUser();
$enrollment = $currentUser ? getCourseEnrollmentStatus($currentUser['id'], $course['id']) : null;
$hasAccess = $currentUser ? hasCourseAccess($currentUser['id'], $course['id']) : false;

$requestSuccess = false;
$requestError = '';

// Handle manual access request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_access') {
    if (!$currentUser) {
        setFlash('info', 'Please sign up or login to request course access.');
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode('course.php?slug=' . $course['slug']));
        exit;
    }

    $requestNote = trim($_POST['request_note'] ?? '');
    try {
        if ($enrollment) {
            $upd = $pdo->prepare("UPDATE enrollments SET status = 'pending', access_type = 'manual_request', request_note = ? WHERE id = ?");
            $upd->execute([$requestNote, $enrollment['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status, access_type, request_note) VALUES (?, ?, 'pending', 'manual_request', ?)");
            $ins->execute([$currentUser['id'], $course['id'], $requestNote]);
        }
        $requestSuccess = true;
        // Refresh enrollment
        $enrollment = getCourseEnrollmentStatus($currentUser['id'], $course['id']);
    } catch (Exception $e) {
        $requestError = 'Error submitting request: ' . $e->getMessage();
    }
}

$pageTitle = htmlspecialchars($course['title']) . ' — The Astro Healer Academy';
$pageDescription = htmlspecialchars($course['subtitle'] ?: substr(strip_tags($course['description']), 0, 160));
$pageKeywords = htmlspecialchars($course['title']) . ', learn astrology, BNN astrology course, numerology course, Malik Mehboob';
$canonicalUrl = BASE_URL . '/course.php?slug=' . urlencode($course['slug']);
$ogImage = BASE_URL . '/' . ltrim($course['image'] ?: 'assets/images/courses/numerology.jpg', '/');
$ogType = 'website';

$courseSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Course',
    'name' => $course['title'],
    'description' => $course['subtitle'] ?: substr(strip_tags($course['description']), 0, 160),
    'provider' => [
        '@type' => 'Organization',
        'name' => 'The Astro Healer Academy',
        'sameAs' => BASE_URL . '/'
    ],
    'offers' => [
        '@type' => 'Offer',
        'category' => 'Paid',
        'priceCurrency' => 'USD',
        'price' => (float)$course['price'],
        'availability' => 'https://schema.org/InStock',
        'url' => $canonicalUrl
    ],
    'hasCourseInstance' => [
        '@type' => 'CourseInstance',
        'courseMode' => 'Online',
        'instructor' => [
            '@type' => 'Person',
            'name' => 'Malik Mehboob',
            'jobTitle' => 'Master Vedic Astrologer'
        ]
    ]
];
$customJsonLd = json_encode($courseSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
require_once __DIR__ . '/includes/header.php';
?>

<div style="padding: 7rem 0 5rem; background: var(--deep2); position: relative; z-index: 1;">
  <div class="container">
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--dash-muted); margin-bottom: 2rem;">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <span>/</span>
      <a href="<?= BASE_URL ?>/index.php#courses">Courses</a>
      <span>/</span>
      <span style="color: var(--gold-light);"><?= htmlspecialchars($course['title']) ?></span>
    </div>

    <?php displayFlash(); ?>

    <div class="course-layout-grid">
      <!-- Left Column: Details & Curriculum -->
      <div>
        <span class="section-label"><?= htmlspecialchars($course['badge']) ?></span>
        <h1 style="font-size: clamp(2rem, 3.5vw, 2.8rem); line-height: 1.2; margin-bottom: 0.8rem;"><?= htmlspecialchars($course['title']) ?></h1>
        <p style="font-family: var(--font-display); font-size: 1.35rem; color: var(--gold-light); font-style: italic; margin-bottom: 1.8rem;">
          <?= htmlspecialchars($course['subtitle']) ?>
        </p>

        <!-- Course Cover Image -->
        <div style="border-radius: 12px; overflow: hidden; border: 1px solid var(--glass-border); margin-bottom: 2.5rem; box-shadow: 0 15px 40px rgba(0,0,0,0.6);">
          <img src="<?= BASE_URL ?>/<?= htmlspecialchars($course['image'] ?: 'assets/images/courses/numerology.jpg') ?>" alt="<?= htmlspecialchars($course['title']) ?>" style="width: 100%; height: auto; display: block;">
        </div>

        <!-- Description -->
        <div class="dash-panel" style="margin-bottom: 2.5rem;">
          <h3 style="font-size: 1.4rem; color: #fff; margin-bottom: 1rem;"><i class="fa-solid fa-book-open" style="color:var(--gold);"></i> Course Overview</h3>
          <p style="font-size: 1rem; color: var(--muted); line-height: 1.8; margin-bottom: 1.5rem;">
            <?= nl2br(htmlspecialchars($course['description'])) ?>
          </p>
          <?php if (!empty($course['curriculum_summary'])): ?>
            <div style="background: rgba(212,175,55,0.06); border-left: 3px solid var(--gold); padding: 1rem 1.2rem; border-radius: 4px;">
              <strong style="color: var(--gold-bright); font-size: 0.9rem;">Curriculum Highlights:</strong>
              <p style="font-size: 0.9rem; color: var(--muted); margin-top: 0.4rem;"><?= htmlspecialchars($course['curriculum_summary']) ?></p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Class-wise Lecture Breakdown -->
        <div class="dash-panel">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.4rem; color: #fff;"><i class="fa-solid fa-list-check" style="color:var(--gold);"></i> Class-by-Class Curriculum</h3>
            <span style="font-size: 0.85rem; color: var(--dash-muted);"><?= count($lectures) ?> Lectures · <?= htmlspecialchars($course['duration']) ?></span>
          </div>

          <div style="display: flex; flex-direction: column; gap: 0.8rem;">
            <?php foreach ($lectures as $lec): ?>
              <div class="lecture-item">
                <div style="display: flex; align-items: center; gap: 1rem;">
                  <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(212,175,55,0.1); border: 1px solid var(--gold); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; color: var(--gold-bright); font-weight: 700;">
                    <?= $lec['class_number'] ?>
                  </div>
                  <div>
                    <h4 style="font-size: 1rem; color: #fff; margin-bottom: 0.2rem;"><?= htmlspecialchars($lec['title']) ?></h4>
                    <p style="font-size: 0.82rem; color: var(--dash-muted);"><?= htmlspecialchars($lec['description']) ?></p>
                  </div>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                  <span style="font-size: 0.8rem; color: var(--dash-muted);"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($lec['duration']) ?></span>
                  <?php if ($lec['is_free_preview']): ?>
                    <?php 
                      $previewSrc = $lec['video_type'] === 'file' 
                        ? (BASE_URL . '/' . ltrim($lec['video_source'], '/')) 
                        : $lec['video_source'];
                    ?>
                    <button type="button" class="badge badge-success" style="cursor: pointer; border: 1px solid rgba(0,230,118,0.5); background: rgba(0,230,118,0.15); color: #69f0ae; display: inline-flex; align-items: center; gap: 5px; padding: 0.35rem 0.75rem;" 
                      onclick="openFreePreview('<?= htmlspecialchars($lec['video_type']) ?>', '<?= htmlspecialchars(addslashes($previewSrc)) ?>', '<?= htmlspecialchars(addslashes($lec['title'])) ?>')">
                      <i class="fa-solid fa-play" style="font-size:0.7rem;"></i> Free Preview
                    </button>
                  <?php else: ?>
                    <i class="fa-solid fa-lock" style="color: var(--dash-muted); font-size: 0.9rem;" title="Locked for enrolled students"></i>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Right Column: Enrollment & Checkout Box -->
      <div style="position: sticky; top: 90px;">
        <div class="dash-panel" style="border: 1px solid var(--gold); box-shadow: 0 15px 40px rgba(0,0,0,0.8);">
          <div style="text-align: center; padding-bottom: 1.5rem; border-bottom: 1px solid rgba(212,175,55,0.15);">
            <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.15em; color: var(--dash-muted); margin-bottom: 0.5rem;">Course Tuition</div>
            <div style="font-family: var(--font-serif); font-size: 2.5rem; font-weight: 700; color: var(--gold-bright); line-height: 1;">
              <?= formatPrice($course['price']) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-top: 0.4rem;">One-time payment · Complete lifetime access</div>
          </div>

          <div style="padding: 1.5rem 0; display: flex; flex-direction: column; gap: 0.8rem; font-size: 0.88rem; color: var(--dash-text); border-bottom: 1px solid rgba(212,175,55,0.15);">
            <div><i class="fa-solid fa-video" style="color:var(--gold); width:20px;"></i> <?= count($lectures) ?> On-Demand Video Classes</div>
            <div><i class="fa-regular fa-clock" style="color:var(--gold); width:20px;"></i> <?= htmlspecialchars($course['duration']) ?> Structured Study</div>
            <div><i class="fa-solid fa-file-pdf" style="color:var(--gold); width:20px;"></i> Downloadable Study PDF Handouts</div>
            <div><i class="fa-solid fa-award" style="color:var(--gold); width:20px;"></i> Certificate of Course Completion</div>
            <div><i class="fa-solid fa-infinity" style="color:var(--gold); width:20px;"></i> Lifetime Student Portal Access</div>
            <div><i class="fa-brands fa-whatsapp" style="color:var(--gold); width:20px;"></i> Private Student Q&A Group Access</div>
          </div>

          <div style="padding-top: 1.5rem;">
            <?php if ($hasAccess): ?>
              <div class="alert-box alert-success" style="margin-bottom: 1rem;">
                <i class="fa-solid fa-circle-check"></i> You have active access to this course!
              </div>
              <a href="<?= BASE_URL ?>/student/classroom.php?course_id=<?= $course['id'] ?>" class="btn-primary" style="width: 100%; text-align: center;">
                <i class="fa-solid fa-circle-play"></i> Open Learning Player
              </a>
            <?php elseif ($enrollment && $enrollment['status'] === 'pending'): ?>
              <div class="alert-box alert-info" style="margin-bottom: 1rem;">
                <i class="fa-solid fa-hourglass-half"></i> Access request pending admin approval.
              </div>
              <p style="font-size: 0.82rem; color: var(--dash-muted); margin-bottom: 1rem; text-align: center;">
                Our administrator (Malik Mehboob) will review and activate your access shortly.
              </p>
            <?php else: ?>
              <!-- Stripe Checkout Button -->
              <a href="<?= BASE_URL ?>/checkout_stripe.php?course_id=<?= $course['id'] ?>" class="btn-primary" style="width: 100%; text-align: center; margin-bottom: 1rem;">
                <i class="fa-brands fa-stripe" style="font-size: 1.2rem;"></i> Pay & Instant Enroll
              </a>

              <div style="text-align: center; font-size: 0.78rem; color: var(--dash-muted); margin: 0.8rem 0;">— OR —</div>

              <!-- Manual Request Form Trigger -->
              <?php if ($requestSuccess): ?>
                <div class="alert-box alert-success" style="font-size: 0.82rem;">
                  <i class="fa-solid fa-circle-check"></i> Request submitted! Admin will grant access soon.
                </div>
              <?php else: ?>
                <button type="button" onclick="document.getElementById('manual-request-modal').classList.add('open')" class="btn-outline" style="width: 100%; text-align: center;">
                  <i class="fa-regular fa-paper-plane"></i> Request Offline / Bank Access
                </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Manual Access Request Modal -->
<div class="modal-backdrop" id="manual-request-modal">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('manual-request-modal').classList.remove('open')">&times;</button>
    <h3 style="font-family: var(--font-serif); font-size: 1.3rem; color: var(--gold-bright); margin-bottom: 0.5rem;">
      Request Enrollment Access
    </h3>
    <p style="font-size: 0.85rem; color: var(--dash-muted); margin-bottom: 1.5rem;">
      If you transferred tuition via JazzCash, EasyPaisa, Bank Transfer, or wish to request course access directly, send a note to Admin below.
    </p>

    <?php if (!$currentUser): ?>
      <p style="font-size: 0.9rem; color: #fff; margin-bottom: 1rem;">Please sign in or create an account first so admin can attach the course to your profile.</p>
      <div style="display: flex; gap: 1rem;">
        <a href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode('course.php?slug=' . $course['slug']) ?>" class="btn-outline btn-sm">Login</a>
        <a href="<?= BASE_URL ?>/signup.php?redirect=<?= urlencode('course.php?slug=' . $course['slug']) ?>" class="btn-primary btn-sm">Sign Up</a>
      </div>
    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="action" value="request_access">
        <div class="form-group">
          <label class="form-label">Student Name</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['name']) ?>" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Student Email</label>
          <input type="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Reference / Notes (Optional)</label>
          <textarea name="request_note" rows="3" class="form-control" placeholder="Mention Transaction ID, bank receipt number, or message for Malik Mehboob..."></textarea>
        </div>
        <button type="submit" class="btn-primary" style="width: 100%;">
          Submit Request to Admin
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Free Preview Video Modal -->
<div class="modal-backdrop" id="free-preview-modal">
  <div class="modal-box" style="max-width: 780px; padding: 1.5rem;">
    <button class="modal-close" onclick="closeFreePreview()">&times;</button>
    <h3 style="font-family: var(--font-serif); font-size: 1.25rem; color: var(--gold-bright); margin-bottom: 0.8rem;" id="free-preview-title">
      Free Class Preview
    </h3>
    <div style="width: 100%; aspect-ratio: 16/9; background: #000; border-radius: 8px; overflow: hidden; position: relative;" id="free-preview-container"></div>
  </div>
</div>

<script>
function openFreePreview(type, source, title) {
  const modal = document.getElementById('free-preview-modal');
  const container = document.getElementById('free-preview-container');
  const titleEl = document.getElementById('free-preview-title');
  titleEl.innerText = title || 'Free Class Preview';
  container.innerHTML = '';

  let raw = (source || '').trim();
  const iframeMatch = raw.match(/src=["']([^"']+)["']/i);
  if (iframeMatch) raw = iframeMatch[1];

  const isDirectVideo = (type === 'file') || /\.(mp4|webm|mov|mkv|m4v)(\?.*)?$/i.test(raw);

  if (isDirectVideo) {
    container.innerHTML = `
      <video controls autoplay playsinline controlslist="nodownload" style="width:100%; height:100%; background:#000; object-fit:contain;">
        <source src="${raw}" type="video/mp4">
        Your browser does not support HTML5 video playback.
      </video>
    `;
  } else {
    let embedUrl = raw;
    let driveId = null;

    const driveMatch = raw.match(/(?:drive|docs)\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)([a-zA-Z0-9_-]+)/i);
    if (driveMatch) {
      driveId = driveMatch[1];
      embedUrl = 'https://drive.google.com/file/d/' + driveId + '/preview';
    } else if (raw.includes('youtube.com/watch?v=') || raw.includes('youtu.be/') || raw.includes('youtube.com/embed/') || raw.includes('youtube.com/shorts/')) {
      const ytMatch = raw.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i);
      if (ytMatch) embedUrl = 'https://www.youtube.com/embed/' + ytMatch[1] + '?autoplay=1&rel=0';
    } else if (raw.includes('vimeo.com/')) {
      const vimMatch = raw.match(/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)([0-9]+)/i);
      if (vimMatch) embedUrl = 'https://player.vimeo.com/video/' + vimMatch[1] + '?autoplay=1';
    }

    let driveOverlay = '';
    if (driveId) {
      driveOverlay = `
        <div style="position:absolute; bottom:12px; right:12px; z-index:10; background:rgba(0,0,0,0.8); backdrop-filter:blur(6px); padding:4px 10px; border-radius:6px; border:1px solid rgba(212,175,55,0.4); font-size:0.75rem;">
          <a href="https://drive.google.com/file/d/${driveId}/view" target="_blank" rel="noopener noreferrer" style="color:#ffd700; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
            <i class="fa-brands fa-google-drive" style="color:#4285F4;"></i>
            <span>Watch in Google Drive <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.65rem;"></i></span>
          </a>
        </div>
      `;
    }

    container.innerHTML = `
      <div style="position:relative; width:100%; height:100%;">
        <iframe src="${embedUrl}" allow="autoplay; fullscreen; accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:100%; height:100%; border:none;"></iframe>
        ${driveOverlay}
      </div>
    `;
  }

  modal.classList.add('open');
}

function closeFreePreview() {
  const modal = document.getElementById('free-preview-modal');
  const container = document.getElementById('free-preview-container');
  container.innerHTML = '';
  modal.classList.remove('open');
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeFreePreview();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
