<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireStudent();
$pdo = getDBConnection();
$currentUser = getCurrentUser();

$courseId = intval($_GET['course_id'] ?? 0);

// Verify course exists
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    setFlash('error', 'Course not found.');
    header('Location: ' . BASE_URL . '/student/index.php');
    exit;
}

// Verify access permission
if (!hasCourseAccess($currentUser['id'], $course['id'])) {
    setFlash('error', 'You do not have active access to this course yet. Please enroll or await admin approval.');
    header('Location: ' . BASE_URL . '/course.php?slug=' . $course['slug']);
    exit;
}

// Fetch all classes / lectures for this course
$lecStmt = $pdo->prepare("SELECT * FROM lectures WHERE course_id = ? ORDER BY class_number ASC");
$lecStmt->execute([$course['id']]);
$lectures = $lecStmt->fetchAll();

if (empty($lectures)) {
    setFlash('info', 'No lectures have been uploaded for this course yet.');
    header('Location: ' . BASE_URL . '/student/index.php');
    exit;
}

// Fetch completed lectures for this user
$progStmt = $pdo->prepare("SELECT lecture_id FROM lecture_progress WHERE user_id = ? AND completed = 1");
$progStmt->execute([$currentUser['id']]);
$completedIds = $progStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle Mark as Completed / Incomplete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_complete') {
    $targetLecId = intval($_POST['lecture_id'] ?? 0);
    $isComplete = in_array($targetLecId, $completedIds);

    if ($isComplete) {
        $del = $pdo->prepare("DELETE FROM lecture_progress WHERE user_id = ? AND lecture_id = ?");
        $del->execute([$currentUser['id'], $targetLecId]);
        setFlash('info', 'Class marked as incomplete.');
    } else {
        $ins = $pdo->prepare("INSERT INTO lecture_progress (user_id, lecture_id, completed, completed_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()");
        $ins->execute([$currentUser['id'], $targetLecId]);
        setFlash('success', 'Class marked as completed!');
    }
    header('Location: ' . BASE_URL . '/student/classroom.php?course_id=' . $course['id'] . '&lecture_id=' . $targetLecId);
    exit;
}

// Determine active lecture
$activeLecId = intval($_GET['lecture_id'] ?? 0);
$activeLecture = null;
if ($activeLecId > 0) {
    foreach ($lectures as $l) {
        if ($l['id'] === $activeLecId) {
            $activeLecture = $l;
            break;
        }
    }
}
if (!$activeLecture) {
    $activeLecture = $lectures[0];
}

// Find Next & Previous Lectures
$prevLecture = null;
$nextLecture = null;
$currentIndex = 0;
foreach ($lectures as $idx => $l) {
    if ($l['id'] === $activeLecture['id']) {
        $currentIndex = $idx;
        if ($idx > 0) $prevLecture = $lectures[$idx - 1];
        if ($idx < count($lectures) - 1) $nextLecture = $lectures[$idx + 1];
        break;
    }
}

$isCurrentCompleted = in_array($activeLecture['id'], $completedIds);
$progressPercent = count($lectures) > 0 ? round((count($completedIds) / count($lectures)) * 100) : 0;

$pageTitle = htmlspecialchars($course['title']) . ' — Classroom';
$studentPage = 'classroom';
require_once __DIR__ . '/includes/student_header.php';
?>

<!-- Classroom Header Breadcrumbs -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <div style="display: flex; gap: 0.5rem; font-size: 0.8rem; color: var(--dash-muted); margin-bottom: 0.3rem;">
      <a href="<?= BASE_URL ?>/student/index.php">Dashboard</a>
      <span>/</span>
      <span style="color: var(--gold-light);"><?= htmlspecialchars($course['title']) ?></span>
    </div>
    <h2 style="font-size: 1.4rem; color: #fff;">
      <span style="color: var(--dash-gold-bright);">Class <?= $activeLecture['class_number'] ?>:</span> 
      <?= htmlspecialchars($activeLecture['title']) ?>
    </h2>
  </div>

  <div style="display: flex; align-items: center; gap: 1rem;">
    <span style="font-size: 0.82rem; color: var(--dash-muted);">Progress: <strong style="color:var(--gold-bright);"><?= count($completedIds) ?> / <?= count($lectures) ?></strong> (<?= $progressPercent ?>%)</span>
    <div style="width: 120px; height: 8px; background: rgba(255,255,255,0.08); border-radius: 10px; overflow: hidden;">
      <div style="width: <?= $progressPercent ?>%; height: 100%; background: linear-gradient(90deg, #d4af37, #ffd700);"></div>
    </div>
  </div>
</div>

<!-- Main Video Classroom Layout -->
<div class="classroom-layout">
  <!-- Left Side: Big Video Player & Lesson Notes -->
  <div class="video-main-box">
    <!-- Video Player Frame -->
    <div class="video-frame-container" style="position: relative;">
      <?= renderLectureVideoPlayerHtml($activeLecture['video_type'], $activeLecture['video_source'], $activeLecture['title']) ?>
    </div>

    <!-- Classroom Meta & Actions -->
    <div class="classroom-meta-box">
      <div class="classroom-title-row">
        <div>
          <h3 class="class-title-text"><?= htmlspecialchars($activeLecture['title']) ?></h3>
          <span style="font-size: 0.82rem; color: var(--dash-muted);">
            <i class="fa-regular fa-clock" style="color:var(--dash-gold);"></i> <?= htmlspecialchars($activeLecture['duration']) ?> · 
            <i class="fa-solid fa-user-tie" style="color:var(--dash-gold);"></i> Instructor: Malik Mehboob
          </span>
        </div>

        <!-- Mark as Completed Form -->
        <form method="POST">
          <input type="hidden" name="action" value="toggle_complete">
          <input type="hidden" name="lecture_id" value="<?= $activeLecture['id'] ?>">
          <button type="submit" class="btn-action <?= $isCurrentCompleted ? 'btn-approve' : 'btn-primary' ?>" style="padding: 0.6rem 1.2rem; font-size: 0.85rem;">
            <?php if ($isCurrentCompleted): ?>
              <i class="fa-solid fa-circle-check"></i> Completed
            <?php else: ?>
              <i class="fa-regular fa-circle-check"></i> Mark as Completed
            <?php endif; ?>
          </button>
        </form>
      </div>

      <!-- Navigation: Prev & Next Buttons -->
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-top: 1px solid rgba(212,175,55,0.15); border-bottom: 1px solid rgba(212,175,55,0.15); margin: 1.2rem 0; flex-wrap: wrap; gap: 0.8rem;">
        <?php if ($prevLecture): ?>
          <a href="<?= BASE_URL ?>/student/classroom.php?course_id=<?= $course['id'] ?>&lecture_id=<?= $prevLecture['id'] ?>" class="topbar-btn">
            <i class="fa-solid fa-arrow-left"></i> Previous Class
          </a>
        <?php else: ?>
          <div></div>
        <?php endif; ?>

        <?php if (!empty($activeLecture['resources_pdf'])): ?>
          <a href="<?= BASE_URL ?>/<?= htmlspecialchars($activeLecture['resources_pdf']) ?>" target="_blank" class="topbar-btn" style="color:var(--dash-gold-bright);">
            <i class="fa-solid fa-file-pdf"></i> Download Class PDF Notes
          </a>
        <?php endif; ?>

        <?php if ($nextLecture): ?>
          <a href="<?= BASE_URL ?>/student/classroom.php?course_id=<?= $course['id'] ?>&lecture_id=<?= $nextLecture['id'] ?>" class="btn-primary btn-sm">
            Next Class <i class="fa-solid fa-arrow-right"></i>
          </a>
        <?php else: ?>
          <span class="badge badge-success">Course Completed!</span>
        <?php endif; ?>
      </div>

      <!-- Lesson Notes & Description -->
      <div>
        <h4 style="font-size: 1rem; color: #fff; margin-bottom: 0.6rem;">
          <i class="fa-solid fa-book-open" style="color:var(--dash-gold);"></i> Class Topics & Lesson Summary
        </h4>
        <p style="font-size: 0.92rem; color: var(--dash-muted); line-height: 1.8;">
          <?= nl2br(htmlspecialchars($activeLecture['description'] ?: 'Study the cosmic alignments and instructions explained by Malik Mehboob in this video lesson. Practice charting with the provided diagrams.')) ?>
        </p>
      </div>
    </div>
  </div>

  <!-- Right Side: Class Playlist Panel -->
  <div class="classroom-playlist-panel">
    <div class="playlist-header">
      <div>
        <span>Course Syllabus</span>
        <div style="font-size: 0.72rem; color: var(--dash-muted); font-family: var(--font-body); margin-top: 0.2rem;">
          <?= count($lectures) ?> Lectures · Lifetime Access
        </div>
      </div>
      <span class="badge badge-active"><?= $progressPercent ?>%</span>
    </div>

    <ul class="playlist-items">
      <?php foreach ($lectures as $l): 
        $isActive = ($l['id'] === $activeLecture['id']);
        $isDone = in_array($l['id'], $completedIds);
      ?>
        <li class="playlist-item <?= $isActive ? 'active' : '' ?>">
          <a href="<?= BASE_URL ?>/student/classroom.php?course_id=<?= $course['id'] ?>&lecture_id=<?= $l['id'] ?>" class="playlist-link">
            <span class="class-num-badge">
              <?php if ($isDone): ?>
                <i class="fa-solid fa-check" style="color:var(--success);"></i>
              <?php else: ?>
                <?= $l['class_number'] ?>
              <?php endif; ?>
            </span>
            <div class="class-info-col">
              <div class="class-item-title"><?= htmlspecialchars($l['title']) ?></div>
              <div class="class-item-duration">
                <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($l['duration']) ?>
                <?php if ($l['is_free_preview']): ?>
                  · <span style="color:var(--info);">Preview</span>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($isActive): ?>
              <i class="fa-solid fa-volume-high" style="color:var(--dash-gold-bright); font-size:0.85rem; margin-top:0.3rem;"></i>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<?php require_once __DIR__ . '/includes/student_footer.php'; ?>
