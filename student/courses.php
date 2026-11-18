<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireStudent();
$pdo = getDBConnection();
$currentUser = getCurrentUser();

// Fetch all courses
$courses = $pdo->query("SELECT * FROM courses WHERE status = 'published' ORDER BY price DESC")->fetchAll();

// Fetch student enrollments
$eStmt = $pdo->prepare("SELECT course_id, status FROM enrollments WHERE user_id = ?");
$eStmt->execute([$currentUser['id']]);
$userEnrollments = [];
while ($row = $eStmt->fetch()) {
    $userEnrollments[$row['course_id']] = $row['status'];
}

$pageTitle = 'Explore Academy Courses';
$studentPage = 'courses';
require_once __DIR__ . '/includes/student_header.php';
?>

<div style="margin-bottom: 2rem;">
  <h2 style="font-size:1.6rem; color:#fff; margin-bottom:0.3rem;">Academy Course Catalog</h2>
  <p style="font-size:0.85rem; color:var(--dash-muted);">Browse all 3 specialized Vedic astrology and numerology programs</p>
</div>

<div class="student-courses-grid">
  <?php foreach ($courses as $c): 
    $status = $userEnrollments[$c['id']] ?? null;
  ?>
    <div class="dash-panel" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
      <div style="position: relative; height: 200px; width: 100%;">
        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($c['image'] ?: 'assets/images/courses/numerology.jpg') ?>" alt="<?= htmlspecialchars($c['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
        <span class="badge" style="position: absolute; top: 1rem; right: 1rem; background: rgba(5,7,22,0.85); border: 1px solid var(--gold); color: var(--gold-light);">
          <?= htmlspecialchars($c['badge']) ?>
        </span>
      </div>

      <div class="dash-card-body" style="padding: clamp(1.2rem, 2.5vw, 1.8rem); display: flex; flex-direction: column; flex-grow: 1;">
        <div style="display: flex; gap: 1rem; font-size: 0.78rem; color: var(--dash-muted); margin-bottom: 0.8rem; flex-wrap: wrap;">
          <span><i class="fa-regular fa-clock" style="color:var(--gold);"></i> <?= htmlspecialchars($c['duration']) ?></span>
          <span><i class="fa-solid fa-layer-group" style="color:var(--gold);"></i> <?= htmlspecialchars($c['level']) ?></span>
        </div>

        <h3 style="font-size: 1.25rem; color: #fff; margin-bottom: 0.4rem; overflow-wrap: break-word;"><?= htmlspecialchars($c['title']) ?></h3>
        <p style="font-size: 0.85rem; color: var(--dash-muted); margin-bottom: 1.5rem; flex-grow: 1;">
          <?= htmlspecialchars(substr($c['description'], 0, 130)) ?>...
        </p>

        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1.2rem; border-top: 1px solid rgba(212,175,55,0.15); flex-wrap: wrap; gap: 0.8rem;">
          <div style="font-family: var(--font-serif); font-size: 1.5rem; color: var(--gold-bright); font-weight: 700;">
            <?= formatPrice($c['price']) ?>
          </div>

          <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <?php if ($status === 'active'): ?>
              <a href="<?= BASE_URL ?>/student/classroom.php?course_id=<?= $c['id'] ?>" class="btn-primary btn-sm">
                <i class="fa-solid fa-circle-play"></i> Open Class
              </a>
            <?php elseif ($status === 'pending'): ?>
              <span class="badge badge-pending"><i class="fa-solid fa-clock"></i> Pending Approval</span>
            <?php else: ?>
              <a href="<?= BASE_URL ?>/course.php?slug=<?= $c['slug'] ?>" class="btn-outline btn-sm">Details</a>
              <a href="<?= BASE_URL ?>/checkout_stripe.php?course_id=<?= $c['id'] ?>" class="btn-primary btn-sm">Enroll</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/student_footer.php'; ?>
