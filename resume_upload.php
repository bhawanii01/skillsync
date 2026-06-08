<?php
require_once 'config.php';
requireLogin();
$userId = $_SESSION['user_id'];
$user   = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();

$error = ''; $success = '';

// Handle resume upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume_file'])) {
    $file    = $_FILES['resume_file'];
    $allowed = ['pdf','doc','docx','txt'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
    } elseif (!in_array($ext, $allowed)) {
        $error = 'Only PDF, DOC, DOCX, and TXT files are allowed.';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $error = 'File size must be under 2 MB.';
    } else {
        // Save file
        $uploadDir = __DIR__ . '/uploads/resumes/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $newName  = 'resume_' . $userId . '_' . time() . '.' . $ext;
        $savePath = $uploadDir . $newName;

        if (move_uploaded_file($file['tmp_name'], $savePath)) {
            // Extract text for analysis
            $resumeText = '';
            if ($ext === 'txt') {
                $resumeText = file_get_contents($savePath);
            } elseif ($ext === 'pdf') {
                // Basic text extraction via strings command (no extra lib needed)
                $resumeText = shell_exec("strings " . escapeshellarg($savePath) . " 2>/dev/null") ?? '';
            } else {
                // For doc/docx, store filename and do keyword-based check
                $resumeText = $file['name'] . ' ' . ($_POST['resume_text_manual'] ?? '');
            }

            // If user pasted resume text manually, use that
            if (!empty($_POST['resume_text_manual'])) {
                $resumeText = $_POST['resume_text_manual'];
            }

            // ---- Rule-Based Resume Strength Checker ----
            $rules = [
                ['pattern' => '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',    'weight' => 10, 'label' => 'Email address present'],
                ['pattern' => '/\b\d{10}\b|\+91[-\s]?\d{10}/',                'weight' => 8,  'label' => 'Phone number present'],
                ['pattern' => '/linkedin\.com\/in\//i',                        'weight' => 8,  'label' => 'LinkedIn profile linked'],
                ['pattern' => '/github\.com\//i',                              'weight' => 7,  'label' => 'GitHub profile linked'],
                ['pattern' => '/objective|summary|profile/i',                  'weight' => 8,  'label' => 'Objective/Summary section present'],
                ['pattern' => '/b\.?tech|btech|bachelor|b\.?e\.|degree/i',     'weight' => 10, 'label' => 'Education details found'],
                ['pattern' => '/skills|technologies|technical/i',              'weight' => 10, 'label' => 'Skills section present'],
                ['pattern' => '/project|built|developed|implemented/i',        'weight' => 12, 'label' => 'Project experience found'],
                ['pattern' => '/internship|intern|training|experience/i',      'weight' => 10, 'label' => 'Internship/Experience mentioned'],
                ['pattern' => '/achievement|award|honor|certificate/i',        'weight' => 7,  'label' => 'Achievements/Certifications listed'],
                ['pattern' => '/python|java|c\+\+|javascript|sql|react|php/i', 'weight' => 10, 'label' => 'Technical keywords detected'],
            ];

            $earnedScore = 0; $maxScore = 0;
            $passed = []; $failed = [];
            foreach ($rules as $rule) {
                $maxScore += $rule['weight'];
                if (preg_match($rule['pattern'], $resumeText)) {
                    $earnedScore += $rule['weight'];
                    $passed[] = $rule['label'];
                } else {
                    $failed[] = $rule['label'];
                }
            }
            $strengthScore = $maxScore > 0 ? round(($earnedScore / $maxScore) * 100) : 0;

            // Store in DB
            $passedJson  = $conn->real_escape_string(json_encode($passed));
            $failedJson  = $conn->real_escape_string(json_encode($failed));
            $escapedText = $conn->real_escape_string(substr($resumeText, 0, 5000));
            $fileSize    = $file['size'];
            $fileName    = $conn->real_escape_string($file['name']);
            $filePath    = $conn->real_escape_string('uploads/resumes/' . $newName);

            $conn->query("INSERT INTO resumes (user_id, file_name, file_path, file_size, strength_score, passed_checks, failed_checks, resume_text)
                          VALUES ($userId, '$fileName', '$filePath', $fileSize, $strengthScore, '$passedJson', '$failedJson', '$escapedText')");
            computeOverallScore($conn, $userId);
            $success = 'Resume uploaded and analysed successfully!';
        } else {
            $error = 'Could not save the file. Check server upload directory permissions.';
        }
    }
}

// Fetch latest resume analysis
$resume = $conn->query("SELECT * FROM resumes WHERE user_id=$userId ORDER BY uploaded_at DESC LIMIT 1")->fetch_assoc();
$passed = $resume ? json_decode($resume['passed_checks'], true) : [];
$failed = $resume ? json_decode($resume['failed_checks'], true) : [];
$score  = $resume ? $resume['strength_score'] : 0;
$scoreColor = $score >= 70 ? '#10b981' : ($score >= 45 ? '#f59e0b' : '#ef4444');
$scoreLabel = $score >= 70 ? 'Strong Resume 💪' : ($score >= 45 ? 'Average Resume 📝' : 'Weak Resume — Needs Work ⚠️');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resume Strength Checker – SkillSync</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page-wrap">
  <aside class="sidebar">
    <div class="sidebar-logo">Skill<span>Sync</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="icon">🏠</span><span>Dashboard</span></a>
      <a href="skill_analysis.php"><span class="icon">🧠</span><span>Skill Analysis</span></a>
      <a href="aptitude_test.php"><span class="icon">📝</span><span>Aptitude Test</span></a>
      <a href="resume_upload.php" class="active"><span class="icon">📄</span><span>Resume Check</span></a>
      <a href="career_roadmap.php"><span class="icon">🗺️</span><span>Career Roadmap</span></a>
      <a href="job_matching.php"><span class="icon">🎯</span><span>Job Matching</span></a>
      <a href="profile.php"><span class="icon">👤</span><span>Profile</span></a>
      <a href="logout.php"><span class="icon">🚪</span><span>Logout</span></a>
    </nav>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">📄 Resume Strength Checker</div>
      <div class="topbar-user">
        <span><?= htmlspecialchars($user['full_name']) ?></span>
        <div class="avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      </div>
    </div>

    <div class="content-area">
      <?php if ($error):   ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

        <!-- Upload Form -->
        <div class="card">
          <div class="card-title">📤 Upload Your Resume</div>
          <div class="card-subtitle">Accepted formats: PDF, DOC, DOCX, TXT (max 2 MB)</div>

          <form method="POST" action="resume_upload.php" enctype="multipart/form-data">
            <!-- Drag-drop zone -->
            <div class="upload-zone" id="upload_zone" onclick="document.getElementById('resume_file').click()">
              <div class="upload-icon">📎</div>
              <div class="upload-text">Click or drag & drop your resume here</div>
              <div style="font-size:12px;color:#94a3b8;margin-top:6px">PDF, DOC, DOCX, TXT · Max 2 MB</div>
            </div>
            <input type="file" id="resume_file" name="resume_file" accept=".pdf,.doc,.docx,.txt"
                   style="display:none" onchange="showFileName(this.files[0],'upload_zone')">

            <!-- Manual text paste (for better analysis accuracy) -->
            <div class="form-group" style="margin-top:18px">
              <label class="form-label">📋 Or Paste Resume Text (for better analysis)</label>
              <textarea name="resume_text_manual" class="form-control" rows="6"
                        placeholder="Paste your resume content here for more accurate scoring..."></textarea>
              <div class="form-hint">Pasted text takes priority over file parsing for the strength score.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">
              🔍 Analyse Resume Strength
            </button>
          </form>
        </div>

        <!-- Results Panel -->
        <div>
          <?php if ($resume): ?>
          <div class="card" style="text-align:center">
            <div class="card-title" style="justify-content:center">📊 Resume Strength Score</div>

            <!-- Score Ring -->
            <div class="score-ring-wrap" style="margin:16px 0">
              <div class="score-ring" data-score="<?= $score ?>" style="--pct:<?= $score ?>">
                <div class="score-ring-val" style="color:<?= $scoreColor ?>"><?= $score ?></div>
              </div>
            </div>
            <div style="font-size:16px;font-weight:600;color:<?= $scoreColor ?>;margin-bottom:8px">
              <?= $scoreLabel ?>
            </div>
            <div style="font-size:13px;color:#64748b;margin-bottom:20px">
              File: <?= htmlspecialchars($resume['file_name']) ?> ·
              <?= round($resume['file_size']/1024,1) ?> KB ·
              <?= date('d M Y', strtotime($resume['uploaded_at'])) ?>
            </div>

            <!-- Progress bar -->
            <div class="progress-bar-wrap">
              <div class="progress-bar-track" style="height:12px">
                <div class="progress-bar-fill" data-pct="<?= $score ?>"
                     style="background:<?= $scoreColor ?>"></div>
              </div>
            </div>
          </div>

          <!-- Passed Checks -->
          <div class="card">
            <div class="card-title" style="color:#065f46">✅ What's Good (<?= count($passed) ?> checks passed)</div>
            <?php if ($passed): ?>
              <?php foreach ($passed as $p): ?>
                <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #f0fdf4;font-size:14px">
                  <span style="color:#10b981;font-size:16px">✓</span>
                  <?= htmlspecialchars($p) ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p style="color:#94a3b8;font-size:13px">No checks passed. Please upload your resume or paste resume text.</p>
            <?php endif; ?>
          </div>

          <!-- Failed Checks -->
          <?php if ($failed): ?>
          <div class="card">
            <div class="card-title" style="color:#991b1b">⚠️ What to Improve (<?= count($failed) ?> checks failed)</div>
            <?php foreach ($failed as $f): ?>
              <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #fef2f2;font-size:14px">
                <span style="color:#ef4444;font-size:16px">✗</span>
                <?= htmlspecialchars($f) ?> — <em style="color:#94a3b8">missing</em>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php else: ?>
          <div class="card" style="text-align:center;padding:48px 24px">
            <div style="font-size:48px;margin-bottom:16px">📄</div>
            <div style="font-size:16px;font-weight:600;margin-bottom:8px">No Resume Uploaded Yet</div>
            <p style="font-size:14px;color:#64748b">
              Upload your resume to get an instant strength score with detailed feedback.
            </p>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- grid -->

      <!-- Tips Card -->
      <div class="card">
        <div class="card-title">💡 Resume Improvement Tips</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
          <?php
          $tips = [
            ['🔗','Add LinkedIn & GitHub','Recruiters always check your online presence. Include both profile URLs.'],
            ['🎯','Strong Objective','Write a 2-line targeted objective mentioning your role and key skills.'],
            ['💻','List Projects','Add 2–3 projects with tech stack, your role, and a brief outcome description.'],
            ['📜','Add Certifications','Even free certifications (Coursera, Google) add credibility to your profile.'],
            ['🔑','Use Keywords','Mirror the job description keywords. ATS systems filter resumes by keywords.'],
            ['📐','Keep It 1 Page','For freshers, a crisp 1-page resume is more impactful than a padded 3-pager.'],
          ];
          foreach ($tips as $t): ?>
          <div style="background:#f8fafc;border-radius:10px;padding:16px;border:1px solid #e2e8f0">
            <div style="font-size:22px;margin-bottom:8px"><?= $t[0] ?></div>
            <div style="font-weight:600;font-size:14px;margin-bottom:4px"><?= $t[1] ?></div>
            <div style="font-size:13px;color:#64748b"><?= $t[2] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- content-area -->
  </div>
</div>
<script src="script.js"></script>
<script>
function showFileName(file, zoneId) {
  if (!file) return;
  const zone = document.getElementById(zoneId);
  zone.querySelector('.upload-text').textContent = '✅ ' + file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
  zone.style.borderColor = '#10b981';
  zone.style.background  = '#f0fdf4';
}
</script>
</body>
</html>