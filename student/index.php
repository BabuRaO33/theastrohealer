<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireStudent();
$pdo = getDBConnection();
$currentUser = getCurrentUser();

// 1. Fetch Enrolled Courses with Active Access
$activeStmt = $pdo->prepare("
    SELECT c.*, e.status as enrollment_status, e.enrolled_at, e.approved_at,
           (SELECT COUNT(*) FROM lectures WHERE course_id = c.id) as total_lectures,
           (SELECT COUNT(*) FROM lecture_progress lp JOIN lectures l ON lp.lecture_id = l.id WHERE l.course_id = c.id AND lp.user_id = ? AND lp.completed = 1) as completed_lectures
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ? AND e.status = 'active'
    ORDER BY e.approved_at DESC
");
$activeStmt->execute([$currentUser['id'], $currentUser['id']]);
$enrolledCourses = $activeStmt->fetchAll();

// 2. Fetch Pending Course Requests
$pendingStmt = $pdo->prepare("
    SELECT c.*, e.status as enrollment_status, e.request_note, e.enrolled_at
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ? AND e.status = 'pending'
");
$pendingStmt->execute([$currentUser['id']]);
$pendingCourses = $pendingStmt->fetchAll();

// 3. Fetch Other Courses (Not Enrolled / Not Pending)
$otherStmt = $pdo->prepare("
    SELECT * FROM courses 
    WHERE id NOT IN (SELECT course_id FROM enrollments WHERE user_id = ?)
    AND status = 'published'
");
$otherStmt->execute([$currentUser['id']]);
$availableCourses = $otherStmt->fetchAll();

$pageTitle = 'My Learning Portal';
$studentPage = 'dashboard';
require_once __DIR__ . '/includes/student_header.php';
?>

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, rgba(13,18,56,0.9) 0%, rgba(9,14,41,0.95) 100%); border: 1px solid var(--glass-border); border-radius: 16px; padding: clamp(1.4rem, 4vw, 2.5rem) clamp(1.2rem, 3vw, 2rem); margin-bottom: 2.5rem; position: relative; overflow: hidden;">
  <div style="position: absolute; right: -20px; bottom: -20px; opacity: 0.15; font-size: 10rem; color: var(--gold); pointer-events: none;">✦</div>
  <span class="section-label">Academy Portal</span>
  <h2 style="font-size: clamp(1.4rem, 3.5vw, 2rem); color: #fff; margin-bottom: 0.5rem; overflow-wrap: break-word;">
    Welcome to Your Celestial Study, <span style="color:var(--gold-bright);"><?= htmlspecialchars($currentUser['name']) ?></span>!
  </h2>
  <p style="font-family: var(--font-display); font-size: clamp(1rem, 2vw, 1.25rem); color: var(--gold-light); font-style: italic; max-width: 650px;">
    "As above, so below." Resume your video classes, review your lesson charts, and deepen your cosmic comprehension.
  </p>
</div>

<!-- Active Enrolled Courses -->
<div style="margin-bottom: 3rem;">
  <div class="panel-header">
    <h3 class="panel-title">
      <i class="fa-solid fa-graduation-cap" style="color:var(--dash-gold-bright);"></i> 
      My Enrolled Courses (<?= count($enrolledCourses) ?>)
    </h3>
  </div>

  <?php if (empty($enrolledCourses)): ?>
    <div class="dash-panel" style="text-align: center; padding: 3rem 2rem;">
      <i class="fa-solid fa-book-sparkles" style="font-size: 3rem; color: var(--dash-gold); margin-bottom: 1rem; display: block;"></i>
      <h3 style="font-size: 1.3rem; color: #fff; margin-bottom: 0.5rem;">You are not enrolled in any course yet</h3>
      <p style="font-size: 0.9rem; color: var(--dash-muted); margin-bottom: 1.5rem;">
        Choose from our 3 master courses below to begin your astrology or numerology training.
      </p>
      <a href="<?= BASE_URL ?>/student/courses.php" class="btn-primary">
        <i class="fa-solid fa-compass"></i> Explore Courses Catalog
      </a>
    </div>
  <?php else: ?>
    <div class="student-courses-grid">
      <?php foreach ($enrolledCourses as $ec): 
        $totalLec = max(1, (int)$ec['total_lectures']);
        $completedLec = (int)$ec['completed_lectures'];
        $percent = min(100, round(($completedLec / $totalLec) * 100));
      ?>
        <div class="dash-panel" style="display: flex; flex-direction: column; padding: 0; overflow: hidden; border-color: var(--dash-border);">
          <div style="position: relative; height: 180px; width: 100%;">
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($ec['image'] ?: 'assets/images/courses/numerology.jpg') ?>" alt="<?= htmlspecialchars($ec['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
            <span class="badge badge-active" style="position: absolute; top: 1rem; right: 1rem;">
              <i class="fa-solid fa-circle-check"></i> Enrolled & Active
            </span>
          </div>

          <div style="padding: 1.5rem; display: flex; flex-direction: column; flex-grow: 1;">
            <h4 style="font-size: 1.15rem; color: #fff; margin-bottom: 0.4rem; overflow-wrap: break-word;"><?= htmlspecialchars($ec['title']) ?></h4>
            <p style="font-size: 0.82rem; color: var(--dash-muted); margin-bottom: 1.2rem; flex-grow: 1;">
              <?= htmlspecialchars(substr($ec['description'], 0, 110)) ?>...
            </p>

            <!-- Progress Bar -->
            <div style="margin-bottom: 1.5rem;">
              <div style="display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 0.4rem;">
                <span style="color: var(--dash-gold-bright); font-weight: 600;">Course Progress</span>
                <span style="color: var(--dash-muted);"><?= $completedLec ?> of <?= $totalLec ?> Classes (<?= $percent ?>%)</span>
              </div>
              <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.08); border-radius: 10px; overflow: hidden;">
                <div style="width: <?= $percent ?>%; height: 100%; background: linear-gradient(90deg, #d4af37, #ffd700); border-radius: 10px; transition: width 0.4s ease;"></div>
              </div>
            </div>

            <a href="<?= BASE_URL ?>/student/classroom.php?course_id=<?= $ec['id'] ?>" class="btn-primary" style="width: 100%; text-align: center;">
              <i class="fa-solid fa-circle-play"></i> Open Course Classroom
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Pending Requests Alert (if any) -->
<?php if (!empty($pendingCourses)): ?>
  <div style="margin-bottom: 3rem;">
    <div class="panel-header">
      <h3 class="panel-title" style="color: var(--warning);">
        <i class="fa-solid fa-clock-rotate-left"></i> Course Requests Awaiting Admin Approval
      </h3>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1rem;">
      <?php foreach ($pendingCourses as $pc): ?>
        <div class="dash-panel pending-course-card" style="display: flex; align-items: center; justify-content: space-between; border-left: 4px solid var(--warning); padding: 1.2rem 1.8rem;">
          <div>
            <h4 style="font-size: 1.05rem; color: #fff; margin-bottom: 0.2rem; overflow-wrap: break-word;"><?= htmlspecialchars($pc['title']) ?></h4>
            <p style="font-size: 0.82rem; color: var(--dash-muted);">
              Requested on <?= date('d M Y, h:i A', strtotime($pc['enrolled_at'])) ?> · 
              <span style="color: var(--gold);">Admin Malik Mehboob is reviewing your request</span>
            </p>
          </div>
          <span class="badge badge-pending"><i class="fa-solid fa-hourglass-half"></i> Pending Approval</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Available Courses to Enroll -->
<?php if (!empty($availableCourses)): ?>
  <div>
    <div class="panel-header">
      <h3 class="panel-title">
        <i class="fa-solid fa-compass" style="color:var(--dash-gold-bright);"></i> 
        Other Courses You Can Unlock
      </h3>
    </div>

    <div class="student-courses-grid">
      <?php foreach ($availableCourses as $ac): ?>
        <div class="dash-panel" style="padding: 1.5rem; display: flex; flex-direction: column;">
          <h4 style="font-size: 1.1rem; color: #fff; margin-bottom: 0.4rem;"><?= htmlspecialchars($ac['title']) ?></h4>
          <div style="font-size: 1.3rem; font-weight: 700; color: var(--gold-bright); margin-bottom: 0.6rem;">
            <?= formatPrice($ac['price']) ?>
          </div>
          <p style="font-size: 0.82rem; color: var(--dash-muted); margin-bottom: 1.2rem; flex-grow: 1;">
            <?= htmlspecialchars(substr($ac['description'], 0, 110)) ?>...
          </p>
          <div style="display: flex; gap: 0.8rem;">
            <a href="<?= BASE_URL ?>/course.php?slug=<?= $ac['slug'] ?>" class="btn-outline btn-sm" style="flex: 1; text-align: center;">Curriculum</a>
            <a href="<?= BASE_URL ?>/checkout_stripe.php?course_id=<?= $ac['id'] ?>" class="btn-primary btn-sm" style="flex: 1; text-align: center;">Enroll Now</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/student_footer.php'; ?>
