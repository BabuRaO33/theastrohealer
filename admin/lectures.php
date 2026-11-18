<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

requireAdmin();
$pdo = getDBConnection();

$isAjax = isset($_GET['ajax']) 
    || isset($_POST['is_ajax']) 
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Detect if upload exceeded post_max_size (which causes PHP to discard $_POST and $_FILES)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $postMax = ini_get('post_max_size');
    $errMsg = "The uploaded file exceeded the server maximum POST size ({$postMax}). Please choose a video under {$postMax} or use an external video link (e.g. Google Drive, YouTube).";
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }
    setFlash('error', $errMsg);
    header('Location: ' . BASE_URL . '/admin/lectures.php');
    exit;
}

// Fetch Courses
$courses = $pdo->query("SELECT id, title, slug FROM courses ORDER BY id ASC")->fetchAll();

// Handle Delete Lecture
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Check if there was an uploaded file to unlink
    $getStmt = $pdo->prepare("SELECT title, course_id, video_type, video_source, resources_pdf FROM lectures WHERE id = ?");
    $getStmt->execute([$id]);
    $lec = $getStmt->fetch();

    if ($lec) {
        if ($lec['video_type'] === 'file' && !empty($lec['video_source']) && file_exists(__DIR__ . '/../' . $lec['video_source'])) {
            @unlink(__DIR__ . '/../' . $lec['video_source']);
        }
        if (!empty($lec['resources_pdf']) && file_exists(__DIR__ . '/../' . $lec['resources_pdf'])) {
            @unlink(__DIR__ . '/../' . $lec['resources_pdf']);
        }
        $del = $pdo->prepare("DELETE FROM lectures WHERE id = ?");
        $del->execute([$id]);

        $pdo->query("UPDATE courses SET total_classes = (SELECT COUNT(*) FROM lectures WHERE course_id = {$lec['course_id']}) WHERE id = {$lec['course_id']}");
        logAuditTrail('LECTURE_DELETED', 'course', "Deleted class lecture #{$id}: {$lec['title']}", 'warning');

        setFlash('success', 'Class lecture deleted successfully.');
    }
    header('Location: ' . BASE_URL . '/admin/lectures.php');
    exit;
}

// Handle Add / Edit Lecture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_lecture') {
    $courseId = intval($_POST['course_id'] ?? 0);
    $classNumber = intval($_POST['class_number'] ?? 1);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '45 mins');
    $videoType = $_POST['video_type'] ?? 'url'; // 'file' or 'url'
    $videoUrl = trim($_POST['video_url'] ?? '');
    $isFreePreview = isset($_POST['is_free_preview']) ? 1 : 0;
    $editId = intval($_POST['edit_id'] ?? 0);

    // Fetch existing if editing
    $existing = null;
    if ($editId > 0) {
        $eStmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ?");
        $eStmt->execute([$editId]);
        $existing = $eStmt->fetch();
    }

    if (empty($title) || empty($courseId)) {
        $errMsg = 'Please fill in course and class title.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errMsg]);
            exit;
        }
        setFlash('error', $errMsg);
        header('Location: ' . BASE_URL . '/admin/lectures.php?course_id=' . $courseId);
        exit;
    }

    $videoSource = $videoUrl;
    if ($videoType === 'url') {
        $videoSource = normalizeVideoInputForStorage('url', $videoUrl);
    }

    // Handle Direct Video File Upload
    if ($videoType === 'file') {
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['video_file']['tmp_name'];
            $fileName = $_FILES['video_file']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v'];

            if (!in_array($fileExt, $allowedExts)) {
                $errMsg = 'Invalid video format (.' . htmlspecialchars($fileExt) . '). Supported formats: MP4, WebM, MOV, MKV.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $errMsg]);
                    exit;
                }
                setFlash('error', $errMsg);
                header('Location: ' . BASE_URL . '/admin/lectures.php?course_id=' . $courseId);
                exit;
            }

            $uploadDir = __DIR__ . '/../uploads/lectures';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $newFileName = 'class_' . $courseId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $targetPath = $uploadDir . '/' . $newFileName;
            if (move_uploaded_file($fileTmp, $targetPath)) {
                $videoSource = 'uploads/lectures/' . $newFileName;
                // Delete previous file if replacing
                if ($existing && $existing['video_type'] === 'file' && !empty($existing['video_source']) && file_exists(__DIR__ . '/../' . $existing['video_source'])) {
                    @unlink(__DIR__ . '/../' . $existing['video_source']);
                }
            } else {
                $errMsg = 'Failed to move uploaded video to uploads/lectures directory. Please check server folder permissions.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $errMsg]);
                    exit;
                }
                setFlash('error', $errMsg);
                header('Location: ' . BASE_URL . '/admin/lectures.php?course_id=' . $courseId);
                exit;
            }
        } elseif ($editId > 0 && !empty($existing['video_source']) && $existing['video_type'] === 'file') {
            // Retain existing video when editing without selecting new file
            $videoSource = $existing['video_source'];
        } else {
            // No file uploaded or error occurred
            $uploadError = $_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errMsg = 'Please select a valid MP4 video file to upload.';
            if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                $errMsg = 'The video file exceeds the server maximum upload limit (' . ini_get('upload_max_filesize') . ').';
            } elseif ($uploadError === UPLOAD_ERR_PARTIAL) {
                $errMsg = 'The video file was only partially uploaded. Please try again.';
            }
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errMsg]);
                exit;
            }
            setFlash('error', $errMsg);
            header('Location: ' . BASE_URL . '/admin/lectures.php?course_id=' . $courseId);
            exit;
        }
    } else {
        // URL delivery
        if (empty($videoSource)) {
            $errMsg = 'Please provide a valid video URL or embed link.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errMsg]);
                exit;
            }
            setFlash('error', $errMsg);
            header('Location: ' . BASE_URL . '/admin/lectures.php?course_id=' . $courseId);
            exit;
        }
    }

    // Handle PDF Resource Upload
    $resourcePdfPath = $existing['resources_pdf'] ?? null;
    if (isset($_FILES['resource_pdf']) && $_FILES['resource_pdf']['error'] === UPLOAD_ERR_OK) {
        $pdfTmp = $_FILES['resource_pdf']['tmp_name'];
        $pdfName = $_FILES['resource_pdf']['name'];
        $pdfExt = strtolower(pathinfo($pdfName, PATHINFO_EXTENSION));

        if ($pdfExt === 'pdf') {
            $pdfDir = __DIR__ . '/../uploads/resources';
            if (!is_dir($pdfDir)) {
                @mkdir($pdfDir, 0777, true);
            }
            $newPdfName = 'resource_' . $courseId . '_' . time() . '.pdf';
            $pdfTargetPath = $pdfDir . '/' . $newPdfName;
            if (move_uploaded_file($pdfTmp, $pdfTargetPath)) {
                $resourcePdfPath = 'uploads/resources/' . $newPdfName;
            }
        }
    }

    if ($editId > 0) {
        // Update
        $upd = $pdo->prepare("UPDATE lectures SET course_id=?, class_number=?, title=?, description=?, video_type=?, video_source=?, duration=?, resources_pdf=?, is_free_preview=? WHERE id=?");
        $upd->execute([$courseId, $classNumber, $title, $description, $videoType, $videoSource, $duration, $resourcePdfPath, $isFreePreview, $editId]);
        logAuditTrail('LECTURE_UPDATED', 'course', "Updated class lecture #{$editId}: {$title}", 'info');
        setFlash('success', 'Class updated successfully!');
    } else {
        // Insert
        $ins = $pdo->prepare("INSERT INTO lectures (course_id, class_number, title, description, video_type, video_source, duration, resources_pdf, is_free_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$courseId, $classNumber, $title, $description, $videoType, $videoSource, $duration, $resourcePdfPath, $isFreePreview]);
        $newId = $pdo->lastInsertId();
        
        // Update course total classes count
        $pdo->query("UPDATE courses SET total_classes = (SELECT COUNT(*) FROM lectures WHERE course_id = $courseId) WHERE id = $courseId");
        logAuditTrail('LECTURE_UPLOADED', 'course', "Uploaded new class video: {$title} (Class #{$classNumber}) for course ID {$courseId}", 'success');
        setFlash('success', 'New class lecture uploaded and added to course successfully!');
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => BASE_URL . '/admin/lectures.php?course_id=' . $courseId]);
        exit;
    }

    header('Location: ' . BASE_URL . '/admin/lectures.php?course_id=' . $courseId);
    exit;
}

// Course Filter
$selectedCourseId = intval($_GET['course_id'] ?? 0);
$where = $selectedCourseId > 0 ? "WHERE l.course_id = $selectedCourseId" : "";

$lectures = $pdo->query("
    SELECT l.*, c.title as course_title
    FROM lectures l
    JOIN courses c ON l.course_id = c.id
    $where
    ORDER BY l.course_id ASC, l.class_number ASC
")->fetchAll();

$pageTitle = 'Class-wise Lectures & Video Uploader';
$adminPage = 'lectures';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem; flex-wrap:wrap; gap:1rem;">
  <div>
    <h2 style="font-size:1.6rem; color:#fff; margin-bottom:0.3rem;">Class Lectures Management</h2>
    <p style="font-size:0.85rem; color:var(--dash-muted);">Upload class videos, set durations, attach PDF handouts, and organize course curriculum</p>
  </div>
  <button type="button" onclick="openLectureModal()" class="btn-primary">
    <i class="fa-solid fa-cloud-arrow-up"></i> Upload New Class Video
  </button>
</div>

<!-- Course Filter Tabs -->
<div style="display:flex; gap:0.6rem; margin-bottom:1.5rem; flex-wrap:wrap;">
  <a href="<?= BASE_URL ?>/admin/lectures.php" class="topbar-btn <?= $selectedCourseId === 0 ? 'btn-primary' : '' ?>">All Courses (<?= count($lectures) ?>)</a>
  <?php foreach ($courses as $c): ?>
    <a href="<?= BASE_URL ?>/admin/lectures.php?course_id=<?= $c['id'] ?>" class="topbar-btn <?= $selectedCourseId === $c['id'] ? 'btn-primary' : '' ?>">
      <?= htmlspecialchars($c['title']) ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Lectures Table -->
<div class="dash-panel">
  <?php if (empty($lectures)): ?>
    <div style="padding:3rem; text-align:center; color:var(--dash-muted);">
      <i class="fa-solid fa-video-slash" style="font-size:2.5rem; margin-bottom:1rem; display:block;"></i>
      No lectures uploaded for this course yet. Click "Upload New Class Video" above to add classes.
    </div>
  <?php else: ?>
    <div class="custom-table-wrap">
      <table class="custom-table">
        <thead>
          <tr>
            <th>Course</th>
            <th>Class #</th>
            <th>Class Title & Topics</th>
            <th>Video Source</th>
            <th>Duration</th>
            <th>Resources</th>
            <th>Preview</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lectures as $l): ?>
            <tr>
              <td><span style="color:var(--dash-gold); font-size:0.85rem;"><?= htmlspecialchars($l['course_title']) ?></span></td>
              <td>
                <span class="badge" style="background:rgba(212,175,55,0.1); border:1px solid var(--gold); color:var(--gold-bright);">
                  Class <?= $l['class_number'] ?>
                </span>
              </td>
              <td>
                <strong style="color:#fff;"><?= htmlspecialchars($l['title']) ?></strong>
                <p style="font-size:0.8rem; color:var(--dash-muted); margin-top:0.2rem;"><?= htmlspecialchars($l['description']) ?></p>
              </td>
              <td>
                <?php if ($l['video_type'] === 'file'): ?>
                  <span class="badge badge-active"><i class="fa-solid fa-file-video"></i> Direct File</span>
                <?php else: ?>
                  <span class="badge badge-pending"><i class="fa-solid fa-link"></i> URL/Embed</span>
                <?php endif; ?>
                <br><small style="color:var(--dash-muted); font-size:0.75rem;"><?= htmlspecialchars(substr($l['video_source'], 0, 35)) ?>...</small>
              </td>
              <td><i class="fa-regular fa-clock" style="color:var(--dash-gold);"></i> <?= htmlspecialchars($l['duration']) ?></td>
              <td>
                <?php if (!empty($l['resources_pdf'])): ?>
                  <a href="<?= BASE_URL ?>/<?= htmlspecialchars($l['resources_pdf']) ?>" target="_blank" style="color:var(--dash-gold-bright); font-size:0.8rem;">
                    <i class="fa-solid fa-file-pdf"></i> Download PDF
                  </a>
                <?php else: ?>
                  <span style="color:var(--dash-muted); font-size:0.8rem;">None</span>
                <?php endif; ?>
              </td>
              <td>
                <?= $l['is_free_preview'] ? '<span class="badge badge-success">Free Preview</span>' : '<span style="color:var(--dash-muted); font-size:0.8rem;">Locked</span>' ?>
              </td>
              <td>
                <div style="display:flex; gap:0.4rem; align-items:center;">
                  <!-- Watch / Preview Video Button -->
                  <?php 
                    $resolvedPreviewSource = $l['video_type'] === 'file' 
                      ? (BASE_URL . '/' . ltrim($l['video_source'], '/')) 
                      : $l['video_source'];
                  ?>
                  <button type="button" class="btn-action" style="background:rgba(0,176,255,0.15); border-color:#00b0ff; color:#80d8ff;" 
                    onclick="previewLectureVideo('<?= htmlspecialchars($l['video_type']) ?>', '<?= htmlspecialchars(addslashes($resolvedPreviewSource)) ?>', '<?= htmlspecialchars(addslashes($l['title'])) ?>')" 
                    title="Watch / Preview Video">
                    <i class="fa-solid fa-play"></i>
                  </button>

                  <button type="button" class="btn-action btn-approve" onclick='editLecture(<?= json_encode($l) ?>)' title="Edit Class">
                    <i class="fa-solid fa-pen"></i>
                  </button>
                  <a href="<?= BASE_URL ?>/admin/lectures.php?action=delete&id=<?= $l['id'] ?>" class="btn-action btn-revoke" onclick="return confirm('Delete this class lecture?');" title="Delete Class">
                    <i class="fa-solid fa-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Upload / Edit Class Modal -->
<div class="modal-backdrop" id="lecture-modal">
  <div class="modal-box" style="max-width: 680px;">
    <button class="modal-close" onclick="closeLectureModal()">&times;</button>
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 0.5rem; flex-wrap:wrap; gap:0.5rem;">
      <h3 style="font-family: var(--font-serif); font-size: 1.3rem; color: var(--dash-gold-bright); margin:0;" id="modal-heading">
        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Class Lecture Video
      </h3>
      <span class="badge" style="background:rgba(0,200,83,0.12); color:#69f0ae; border:1px solid rgba(0,200,83,0.3); font-size:0.75rem;">
        <i class="fa-solid fa-shield-check"></i> Max Server Upload: <?= ini_get('upload_max_filesize') ?>
      </span>
    </div>
    <p style="font-size: 0.85rem; color: var(--dash-muted); margin-bottom: 1.5rem;">
      Upload video MP4 file directly to server or embed video link (YouTube, Vimeo, CDN).
    </p>

    <!-- Error Alert Box -->
    <div id="modal-error-box" class="alert-box alert-danger" style="display:none; margin-bottom:1.2rem;"></div>

    <form id="lecture-upload-form" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_lecture">
      <input type="hidden" name="edit_id" id="form-edit-id" value="0">

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Course *</label>
          <select name="course_id" id="form-course-id" class="form-control" required>
            <?php foreach ($courses as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $selectedCourseId === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Class Number *</label>
          <input type="number" name="class_number" id="form-class-number" class="form-control" min="1" max="100" value="1" required>
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label">Class Title *</label>
          <input type="text" name="title" id="form-title" class="form-control" placeholder="e.g. Class 1: Introduction to Planets" required>
        </div>
        <div class="form-group">
          <label class="form-label">Duration *</label>
          <input type="text" name="duration" id="form-duration" class="form-control" placeholder="e.g. 50 mins" value="45 mins" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Video Delivery Method *</label>
        <div style="display:flex; gap:1.5rem; margin-top:0.3rem;">
          <label style="color:var(--dash-text); font-size:0.88rem; cursor:pointer;">
            <input type="radio" name="video_type" value="file" checked onchange="toggleVideoInput('file')"> Direct Video Upload (MP4 / WebM)
          </label>
          <label style="color:var(--dash-text); font-size:0.88rem; cursor:pointer;">
            <input type="radio" name="video_type" value="url" onchange="toggleVideoInput('url')"> External Video URL / Embed (YouTube, Vimeo, CDN)
          </label>
        </div>
      </div>

      <!-- File Upload Input (Default) -->
      <div class="form-group" id="video-file-group">
        <label class="form-label">Upload Video File (.mp4, .webm, .mov) *</label>
        <input type="file" name="video_file" id="form-video-file" class="form-control" accept="video/mp4,video/webm,video/quicktime" onchange="handleVideoFileSelect(this)">
        <div id="video-file-details" style="display:none; margin-top:0.4rem; font-size:0.82rem; color:#69f0ae;">
          <i class="fa-solid fa-circle-check"></i> <span id="video-file-info"></span>
        </div>
        <div id="existing-file-note" style="display:none; font-size:0.78rem; color:var(--dash-gold-bright); margin-top:0.3rem;">
          <i class="fa-solid fa-info-circle"></i> A video is already attached. Leave empty if you do not want to replace it.
        </div>
      </div>

      <!-- URL Input -->
      <div class="form-group" id="video-url-group" style="display:none;">
        <label class="form-label">Video Link / Embed URL *</label>
        <input type="text" name="video_url" id="form-video-url" class="form-control" placeholder="Google Drive, YouTube, Vimeo, or direct MP4 link">
        <div style="margin-top:0.6rem; background:rgba(66, 133, 244, 0.08); border:1px solid rgba(66, 133, 244, 0.3); border-radius:8px; padding:0.7rem 0.9rem; font-size:0.78rem; color:#90caf9; line-height:1.5;">
          <div style="display:flex; align-items:center; gap:0.4rem; color:#fff; font-weight:600; margin-bottom:0.3rem;">
            <i class="fa-brands fa-google-drive" style="color:#4285F4; font-size:1rem;"></i> Google Drive Links Supported:
          </div>
          <div>Paste any Google Drive link (sharing URL, preview URL, or iframe embed code). System automatically converts it to stream mode.</div>
          <div style="color:#ffd700; margin-top:0.25rem;">
            <i class="fa-solid fa-circle-info"></i> <strong>Important:</strong> Google Drive file ki access ko <em>"Anyone with the link" (Viewer)</em> par set karein taake video bina kisi error ke chal sakay.
          </div>
        </div>
      </div>

      <!-- PDF Resource -->
      <div class="form-group">
        <label class="form-label">Class Handout / Study Notes (.pdf)</label>
        <input type="file" name="resource_pdf" class="form-control" accept="application/pdf">
      </div>

      <div class="form-group">
        <label class="form-label">Class Description & Key Learnings</label>
        <textarea name="description" id="form-description" rows="3" class="form-control" placeholder="Brief outline of concepts covered in this class..."></textarea>
      </div>

      <div class="form-group" style="display:flex; align-items:center; gap:0.6rem;">
        <input type="checkbox" name="is_free_preview" id="form-free-preview" value="1" style="accent-color:var(--gold);">
        <label for="form-free-preview" style="color:var(--dash-gold-bright); font-size:0.88rem; cursor:pointer;">
          Allow Free Sample Preview for non-enrolled students
        </label>
      </div>

      <!-- Live Upload Progress Bar -->
      <div id="upload-progress-wrapper" style="display:none; margin:1.2rem 0; background:rgba(13,18,56,0.85); border:1px solid rgba(212,175,55,0.4); border-radius:12px; padding:1.2rem;">
        <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.6rem;">
          <span style="color:var(--gold-bright); font-weight:700;"><i class="fa-solid fa-cloud-arrow-up fa-bounce"></i> Uploading Video File to Server...</span>
          <span id="upload-percent-text" style="color:#fff; font-family:monospace; font-weight:bold;">0%</span>
        </div>
        <div style="width:100%; height:12px; background:rgba(0,0,0,0.6); border-radius:10px; overflow:hidden; border:1px solid rgba(255,255,255,0.1);">
          <div id="upload-progress-bar" style="width:0%; height:100%; background:linear-gradient(90deg, #d4af37 0%, #00e676 100%); transition:width 0.15s ease;"></div>
        </div>
        <div id="upload-status-sub" style="font-size:0.75rem; color:var(--dash-muted); margin-top:0.5rem; text-align:center;">
          Uploading high-definition video... Please keep this page open.
        </div>
      </div>

      <button type="submit" id="submit-btn" class="btn-primary" style="width:100%; margin-top:0.8rem;">
        <i class="fa-solid fa-cloud-arrow-up"></i> Save & Upload Lecture
      </button>
    </form>
  </div>
</div>

<!-- Video Preview Modal for Admin -->
<div class="modal-backdrop" id="video-preview-modal">
  <div class="modal-box" style="max-width: 820px; padding: 1.5rem;">
    <button type="button" class="modal-close" onclick="closeVideoPreview()">&times;</button>
    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:1rem;">
      <i class="fa-solid fa-circle-play" style="color:var(--dash-gold-bright); font-size:1.4rem;"></i>
      <div>
        <h3 style="margin:0; font-size:1.15rem; color:#fff;" id="preview-modal-title">Class Video Player</h3>
        <small style="color:var(--dash-muted);" id="preview-modal-source">Direct Video Stream</small>
      </div>
    </div>
    <div id="preview-player-container" style="position: relative; width: 100%; aspect-ratio: 16/9; background: #000; border-radius: 8px; overflow: hidden; border:1px solid var(--dash-border);">
      <!-- Injected Video or Iframe -->
    </div>
  </div>
</div>

<script>
  function openLectureModal() {
    document.getElementById('modal-heading').innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Upload Class Lecture Video';
    document.getElementById('form-edit-id').value = '0';
    document.getElementById('form-title').value = '';
    document.getElementById('form-description').value = '';
    document.getElementById('form-video-url').value = '';
    document.getElementById('form-video-file').value = '';
    document.getElementById('form-free-preview').checked = false;
    document.getElementById('video-file-details').style.display = 'none';
    document.getElementById('existing-file-note').style.display = 'none';
    document.getElementById('modal-error-box').style.display = 'none';
    document.getElementById('upload-progress-wrapper').style.display = 'none';
    document.getElementById('submit-btn').disabled = false;
    document.getElementById('submit-btn').innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Save & Upload Lecture';

    document.querySelector('input[name="video_type"][value="file"]').checked = true;
    toggleVideoInput('file');
    document.getElementById('lecture-modal').classList.add('open');
  }

  function closeLectureModal() {
    document.getElementById('lecture-modal').classList.remove('open');
  }

  function editLecture(data) {
    document.getElementById('modal-heading').innerHTML = '<i class="fa-solid fa-pen"></i> Edit Class Lecture';
    document.getElementById('form-edit-id').value = data.id;
    document.getElementById('form-course-id').value = data.course_id;
    document.getElementById('form-class-number').value = data.class_number;
    document.getElementById('form-title').value = data.title;
    document.getElementById('form-duration').value = data.duration;
    document.getElementById('form-description').value = data.description || '';
    document.getElementById('form-video-url').value = data.video_source || '';
    document.getElementById('form-free-preview').checked = data.is_free_preview == 1;
    document.getElementById('modal-error-box').style.display = 'none';
    document.getElementById('upload-progress-wrapper').style.display = 'none';
    document.getElementById('submit-btn').disabled = false;
    document.getElementById('submit-btn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Class Lecture';

    if (data.video_type === 'file') {
      document.querySelector('input[name="video_type"][value="file"]').checked = true;
      toggleVideoInput('file');
      document.getElementById('existing-file-note').style.display = 'block';
    } else {
      document.querySelector('input[name="video_type"][value="url"]').checked = true;
      toggleVideoInput('url');
      document.getElementById('existing-file-note').style.display = 'none';
    }

    document.getElementById('lecture-modal').classList.add('open');
  }

  function toggleVideoInput(type) {
    if (type === 'file') {
      document.getElementById('video-file-group').style.display = 'block';
      document.getElementById('video-url-group').style.display = 'none';
    } else {
      document.getElementById('video-file-group').style.display = 'none';
      document.getElementById('video-url-group').style.display = 'block';
    }
  }

  const SERVER_MAX_UPLOAD_BYTES = <?= getServerMaxUploadBytes() ?>;
  const SERVER_MAX_UPLOAD_MB = <?= round(getServerMaxUploadBytes() / (1024 * 1024), 1) ?>;

  function handleVideoFileSelect(input) {
    const file = input.files[0];
    const detailsBox = document.getElementById('video-file-details');
    const infoSpan = document.getElementById('video-file-info');

    if (file) {
      const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
      if (file.size > SERVER_MAX_UPLOAD_BYTES) {
        infoSpan.innerHTML = `<span style="color:#ff5252;"><i class="fa-solid fa-triangle-exclamation"></i> Warning: File is <strong>${sizeMB} MB</strong>, which exceeds the server maximum upload limit (${SERVER_MAX_UPLOAD_MB} MB). Direct upload may fail. Recommended: Use a Google Drive link.</span>`;
        detailsBox.style.display = 'block';
      } else {
        infoSpan.innerHTML = `<strong>${file.name}</strong> (${sizeMB} MB) ready to upload`;
        infoSpan.style.color = '#69f0ae';
        detailsBox.style.display = 'block';
      }
    } else {
      detailsBox.style.display = 'none';
    }
  }

  // Handle Form Submission with Real-time Progress Bar
  const uploadForm = document.getElementById('lecture-upload-form');
  if (uploadForm) {
    uploadForm.addEventListener('submit', function(e) {
      const videoType = document.querySelector('input[name="video_type"]:checked').value;
      const fileInput = document.getElementById('form-video-file');
      const editId = parseInt(document.getElementById('form-edit-id').value, 10);
      const errorBox = document.getElementById('modal-error-box');
      errorBox.style.display = 'none';

      if (videoType === 'file') {
        if (editId === 0 && (!fileInput.files || fileInput.files.length === 0)) {
          e.preventDefault();
          errorBox.innerText = 'Please select a video file (.mp4, .webm) to upload, or switch to "External Video URL".';
          errorBox.style.display = 'block';
          fileInput.focus();
          return;
        }
      } else {
        const urlInput = document.getElementById('form-video-url');
        if (!urlInput.value.trim()) {
          e.preventDefault();
          errorBox.innerText = 'Please paste a video link (Google Drive, YouTube, or direct MP4 link).';
          errorBox.style.display = 'block';
          urlInput.focus();
          return;
        }
      }

      // Only show progress bar if uploading a physical file
      if (videoType === 'file' && fileInput.files.length > 0) {
        e.preventDefault();
        const formData = new FormData(uploadForm);
        formData.append('is_ajax', '1');

        const progressWrapper = document.getElementById('upload-progress-wrapper');
        const progressBar = document.getElementById('upload-progress-bar');
        const percentText = document.getElementById('upload-percent-text');
        const statusSub = document.getElementById('upload-status-sub');
        const submitBtn = document.getElementById('submit-btn');

        progressWrapper.style.display = 'block';
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading Video...';
        errorBox.style.display = 'none';

        const xhr = new XMLHttpRequest();
        const postUrl = window.location.pathname + (window.location.search ? window.location.search + '&' : '?') + 'ajax=1';
        xhr.open('POST', postUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = function(event) {
          if (event.lengthComputable) {
            const percent = Math.round((event.loaded / event.total) * 100);
            progressBar.style.width = percent + '%';
            percentText.innerText = percent + '%';
            const loadedMB = (event.loaded / (1024 * 1024)).toFixed(1);
            const totalMB = (event.total / (1024 * 1024)).toFixed(1);
            statusSub.innerText = 'Uploaded ' + loadedMB + ' MB of ' + totalMB + ' MB (' + percent + '%)...';
          }
        };

        xhr.onload = function() {
          let res = null;
          try {
            res = JSON.parse(xhr.responseText);
          } catch (err) {
            // response was not JSON
          }

          if (xhr.status >= 200 && xhr.status < 300 && res && res.success) {
            progressBar.style.width = '100%';
            percentText.innerText = '100%';
            statusSub.innerText = 'Upload complete! Finalizing class...';
            window.location.href = res.redirect || window.location.href;
          } else {
            const err = (res && res.error) ? res.error : ('Server error during upload (HTTP ' + xhr.status + '). Please check video file size.');
            errorBox.innerText = err;
            errorBox.style.display = 'block';
            progressWrapper.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Try Again';
          }
        };

        xhr.onerror = function() {
          errorBox.innerText = 'Network error during upload. Please check your connection and try again.';
          errorBox.style.display = 'block';
          progressWrapper.style.display = 'none';
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Try Again';
        };

        xhr.send(formData);
      }
    });
  }

  // Video Preview Player Modal
  function previewLectureVideo(type, source, title) {
    const modal = document.getElementById('video-preview-modal');
    const container = document.getElementById('preview-player-container');
    const titleEl = document.getElementById('preview-modal-title');
    const sourceEl = document.getElementById('preview-modal-source');

    titleEl.innerText = title;
    container.innerHTML = '';

    let raw = (source || '').trim();
    // If raw iframe tag was passed, extract src
    const iframeMatch = raw.match(/src=["']([^"']+)["']/i);
    if (iframeMatch) {
      raw = iframeMatch[1];
    }

    const isDirectVideo = (type === 'file') || /\.(mp4|webm|mov|mkv|m4v)(\?.*)?$/i.test(raw);

    if (isDirectVideo) {
      sourceEl.innerText = 'Local / Direct Video File: ' + raw;
      container.innerHTML = `
        <video controls autoplay playsinline controlslist="nodownload" style="width:100%; height:100%; background:#000; object-fit:contain;">
          <source src="${raw}" type="video/mp4">
          Your browser does not support the video tag.
        </video>
      `;
    } else {
      let embedUrl = raw;
      let driveId = null;

      // Google Drive pattern
      const driveMatch = raw.match(/(?:drive|docs)\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)([a-zA-Z0-9_-]+)/i);
      if (driveMatch) {
        driveId = driveMatch[1];
        embedUrl = 'https://drive.google.com/file/d/' + driveId + '/preview';
        sourceEl.innerHTML = `Google Drive Stream: <a href="https://drive.google.com/file/d/${driveId}/view" target="_blank" rel="noopener noreferrer" style="color:var(--dash-gold-bright); text-decoration:underline; font-weight:600;"><i class="fa-brands fa-google-drive"></i> Open in Google Drive <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i></a>`;
      } else if (raw.includes('youtube.com/watch?v=') || raw.includes('youtu.be/') || raw.includes('youtube.com/embed/') || raw.includes('youtube.com/shorts/')) {
        const ytMatch = raw.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i);
        if (ytMatch) {
          embedUrl = 'https://www.youtube.com/embed/' + ytMatch[1] + '?autoplay=1&rel=0';
        }
        sourceEl.innerText = 'YouTube Stream: ' + embedUrl;
      } else if (raw.includes('vimeo.com/')) {
        const vimMatch = raw.match(/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)([0-9]+)/i);
        if (vimMatch) {
          embedUrl = 'https://player.vimeo.com/video/' + vimMatch[1] + '?autoplay=1';
        }
        sourceEl.innerText = 'Vimeo Stream: ' + embedUrl;
      } else {
        sourceEl.innerText = 'External Stream: ' + embedUrl;
      }

      let driveHelper = '';
      if (driveId) {
        driveHelper = `
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
          ${driveHelper}
        </div>
      `;
    }

    modal.classList.add('open');
  }

  function closeVideoPreview() {
    const modal = document.getElementById('video-preview-modal');
    const container = document.getElementById('preview-player-container');
    container.innerHTML = ''; // Stop video playback
    modal.classList.remove('open');
  }

  // Close modals on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeLectureModal();
      closeVideoPreview();
    }
  });

  // Open modal if URL has ?action=new
  if (window.location.search.includes('action=new')) {
    openLectureModal();
  }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
