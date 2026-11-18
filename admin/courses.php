<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();
$pdo = getDBConnection();

// Handle Edit / Add Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_course') {
    $editId = intval($_POST['edit_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $duration = trim($_POST['duration'] ?? '6 Weeks');
    $level = trim($_POST['level'] ?? 'All Levels');
    $badge = trim($_POST['badge'] ?? 'Featured');

    if (empty($title) || empty($slug)) {
        setFlash('error', 'Course title and slug are required.');
    } else {
        // Thumbnail upload handling
        $imagePath = null;
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['course_image']['tmp_name'];
            $name = $_FILES['course_image']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $newFileName = 'course_' . time() . '.' . $ext;
                $target = __DIR__ . '/../uploads/thumbnails/' . $newFileName;
                if (move_uploaded_file($tmp, $target)) {
                    $imagePath = 'uploads/thumbnails/' . $newFileName;
                }
            }
        }

        if ($editId > 0) {
            if ($imagePath) {
                $upd = $pdo->prepare("UPDATE courses SET title=?, slug=?, subtitle=?, description=?, price=?, duration=?, level=?, badge=?, image=? WHERE id=?");
                $upd->execute([$title, $slug, $subtitle, $description, $price, $duration, $level, $badge, $imagePath, $editId]);
            } else {
                $upd = $pdo->prepare("UPDATE courses SET title=?, slug=?, subtitle=?, description=?, price=?, duration=?, level=?, badge=? WHERE id=?");
                $upd->execute([$title, $slug, $subtitle, $description, $price, $duration, $level, $badge, $editId]);
            }
            setFlash('success', 'Course updated successfully!');
        } else {
            $imagePath = $imagePath ?: 'assets/images/courses/numerology.jpg';
            $ins = $pdo->prepare("INSERT INTO courses (title, slug, subtitle, description, price, duration, level, badge, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$title, $slug, $subtitle, $description, $price, $duration, $level, $badge, $imagePath]);
            setFlash('success', 'New course created successfully!');
        }
        header('Location: ' . BASE_URL . '/admin/courses.php');
        exit;
    }
}

// Fetch Courses with Lecture Counts and Enrollment Counts
$courses = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM lectures WHERE course_id = c.id) as lecture_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'active') as active_students
    FROM courses c
    ORDER BY c.id ASC
")->fetchAll();

$pageTitle = 'Courses Management';
$adminPage = 'courses';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; color:#fff; margin-bottom:0.3rem;">Academy Courses</h2>
    <p style="font-size:0.85rem; color:var(--dash-muted);">Manage your 3 core courses, pricing, duration, and certificates</p>
  </div>
  <button type="button" onclick="openCourseModal()" class="btn-primary">
    <i class="fa-solid fa-plus"></i> Add New Course
  </button>
</div>

<div class="dash-panel">
  <div class="custom-table-wrap">
    <table class="custom-table">
      <thead>
        <tr>
          <th>Thumbnail</th>
          <th>Course Title & Slug</th>
          <th>Price</th>
          <th>Classes</th>
          <th>Active Students</th>
          <th>Duration & Level</th>
          <th>Badge</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td style="width: 80px;">
              <img src="<?= BASE_URL ?>/<?= htmlspecialchars($c['image'] ?: 'assets/images/courses/numerology.jpg') ?>" alt="<?= htmlspecialchars($c['title']) ?>" style="width:70px; height:45px; object-fit:cover; border-radius:6px; border:1px solid var(--dash-border);">
            </td>
            <td>
              <strong style="color:#fff; font-size:0.95rem;"><?= htmlspecialchars($c['title']) ?></strong><br>
              <small style="color:var(--dash-gold);"><?= htmlspecialchars($c['slug']) ?></small>
            </td>
            <td>
              <strong style="color:var(--dash-gold-bright); font-size:1.05rem;"><?= formatPrice($c['price']) ?></strong>
            </td>
            <td>
              <a href="<?= BASE_URL ?>/admin/lectures.php?course_id=<?= $c['id'] ?>" style="color:var(--info);">
                <i class="fa-solid fa-video"></i> <?= $c['lecture_count'] ?> Lectures
              </a>
            </td>
            <td>
              <span class="badge badge-active"><?= $c['active_students'] ?> Students</span>
            </td>
            <td>
              <?= htmlspecialchars($c['duration']) ?><br>
              <small style="color:var(--dash-muted);"><?= htmlspecialchars($c['level']) ?></small>
            </td>
            <td>
              <span class="badge" style="background:rgba(212,175,55,0.1); border:1px solid var(--gold); color:var(--gold-bright);">
                <?= htmlspecialchars($c['badge']) ?>
              </span>
            </td>
            <td>
              <div style="display:flex; gap:0.4rem;">
                <button type="button" class="btn-action btn-approve" onclick='editCourse(<?= json_encode($c) ?>)' title="Edit Course">
                  <i class="fa-solid fa-pen"></i> Edit
                </button>
                <a href="<?= BASE_URL ?>/course.php?slug=<?= $c['slug'] ?>" target="_blank" class="btn-action btn-revoke" title="View Public Page">
                  <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Edit / Add Course -->
<div class="modal-backdrop" id="course-modal">
  <div class="modal-box" style="max-width: 680px;">
    <button class="modal-close" onclick="document.getElementById('course-modal').classList.remove('open')">&times;</button>
    <h3 style="font-family: var(--font-serif); font-size: 1.3rem; color: var(--dash-gold-bright); margin-bottom: 0.5rem;" id="course-modal-title">
      Edit Course
    </h3>
    <p style="font-size: 0.85rem; color: var(--dash-muted); margin-bottom: 1.5rem;">
      Update course information, pricing, duration, and promotional badges.
    </p>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_course">
      <input type="hidden" name="edit_id" id="course-edit-id" value="0">

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Course Title *</label>
          <input type="text" name="title" id="course-title" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">URL Slug *</label>
          <input type="text" name="slug" id="course-slug" class="form-control" required placeholder="e.g. bnn-astrology">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Subtitle / Hook</label>
        <input type="text" name="subtitle" id="course-subtitle" class="form-control">
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Price ($ USD) *</label>
          <input type="number" step="0.01" name="price" id="course-price" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Duration</label>
          <input type="text" name="duration" id="course-duration" class="form-control" placeholder="e.g. 8 Weeks">
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Target Level</label>
          <input type="text" name="level" id="course-level" class="form-control" placeholder="e.g. Beginner to Master">
        </div>
        <div class="form-group">
          <label class="form-label">Badge</label>
          <input type="text" name="badge" id="course-badge" class="form-control" placeholder="e.g. Best Seller">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Course Thumbnail Image</label>
        <input type="file" name="course_image" class="form-control" accept="image/*">
      </div>

      <div class="form-group">
        <label class="form-label">Full Course Description</label>
        <textarea name="description" id="course-description" rows="4" class="form-control"></textarea>
      </div>

      <button type="submit" class="btn-primary" style="width: 100%;">
        Save Course Changes
      </button>
    </form>
  </div>
</div>

<script>
  function openCourseModal() {
    document.getElementById('course-modal-title').innerText = 'Add New Course';
    document.getElementById('course-edit-id').value = '0';
    document.getElementById('course-title').value = '';
    document.getElementById('course-slug').value = '';
    document.getElementById('course-subtitle').value = '';
    document.getElementById('course-price').value = '199';
    document.getElementById('course-duration').value = '6 Weeks';
    document.getElementById('course-level').value = 'Beginner to Advanced';
    document.getElementById('course-badge').value = 'New Course';
    document.getElementById('course-description').value = '';
    document.getElementById('course-modal').classList.add('open');
  }

  function editCourse(c) {
    document.getElementById('course-modal-title').innerText = 'Edit Course';
    document.getElementById('course-edit-id').value = c.id;
    document.getElementById('course-title').value = c.title;
    document.getElementById('course-slug').value = c.slug;
    document.getElementById('course-subtitle').value = c.subtitle || '';
    document.getElementById('course-price').value = c.price;
    document.getElementById('course-duration').value = c.duration || '';
    document.getElementById('course-level').value = c.level || '';
    document.getElementById('course-badge').value = c.badge || '';
    document.getElementById('course-description').value = c.description || '';
    document.getElementById('course-modal').classList.add('open');
  }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
