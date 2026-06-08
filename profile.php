<?php
require_once 'config.php';
requireLogin();

$userId = $_SESSION['user_id'];
$user   = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name       = clean($conn, $_POST['full_name'] ?? '');
        $email      = clean($conn, $_POST['email'] ?? '');
        $roll       = clean($conn, $_POST['roll_no'] ?? '');
        $branch     = clean($conn, $_POST['branch'] ?? 'CSE');
        $sem        = (int)($_POST['semester'] ?? 3);
        $targetRole = clean($conn, $_POST['target_role'] ?? '');

        if (!$name || !$email) {
            $error = 'Full Name and Email are required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            // Check if email is taken by another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param('si', $email, $userId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = 'This email address is already in use by another account.';
            } else {
                // Handle Profile Pic Upload
                $picName = $user['profile_pic'];
                if (isset($_FILES['profile_pic_file']) && $_FILES['profile_pic_file']['error'] === UPLOAD_ERR_OK) {
                    $file    = $_FILES['profile_pic_file'];
                    $allowed = ['jpg','jpeg','png','gif'];
                    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        if ($file['size'] <= 1 * 1024 * 1024) { // Max 1MB
                            $uploadDir = __DIR__ . '/uploads/profiles/';
                            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                            
                            $picName  = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                            $savePath = $uploadDir . $picName;
                            
                            if (move_uploaded_file($file['tmp_name'], $savePath)) {
                                // Delete old picture if not default
                                if ($user['profile_pic'] !== 'default.png' && file_exists($uploadDir . $user['profile_pic'])) {
                                    @unlink($uploadDir . $user['profile_pic']);
                                }
                            } else {
                                $error = 'Failed to save uploaded image.';
                            }
                        } else {
                            $error = 'Profile picture must be under 1 MB.';
                        }
                    } else {
                        $error = 'Invalid image format. Allowed: JPG, JPEG, PNG, GIF.';
                    }
                }

                if (empty($error)) {
                    // Update user record
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, roll_no = ?, branch = ?, semester = ?, target_role = ?, profile_pic = ? WHERE id = ?");
                    $stmt->bind_param('ssssissi', $name, $email, $roll, $branch, $sem, $targetRole, $picName, $userId);
                    
                    if ($stmt->execute()) {
                        $_SESSION['user_name'] = $name;
                        $success = 'Profile details updated successfully!';
                        // Refresh user data
                        $user = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();
                        // Refresh readiness score in case semester/role/skills changed
                        computeOverallScore($conn, $userId);
                    } else {
                        $error = 'Failed to update profile. Please try again.';
                    }
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $hash, $userId);
            if ($stmt->execute()) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}

$avatarPath = 'uploads/profiles/' . $user['profile_pic'];
if ($user['profile_pic'] === 'default.png' || !file_exists(__DIR__ . '/' . $avatarPath)) {
    $avatarUrl = null;
} else {
    $avatarUrl = $avatarPath;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile – SkillSync</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .profile-card-header {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 24px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--border);
    }
    .profile-pic-large {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: var(--primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      font-weight: 700;
      border: 3px solid #fff;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .profile-pic-large img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .profile-meta-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--text);
    }
    .profile-meta-sub {
      font-size: 13px;
      color: var(--muted);
      margin-top: 2px;
    }
  </style>
</head>
<body>
<div class="page-wrap">
  <aside class="sidebar">
    <div class="sidebar-logo">Skill<span>Sync</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="icon">🏠</span><span>Dashboard</span></a>
      <a href="skill_analysis.php"><span class="icon">🧠</span><span>Skill Analysis</span></a>
      <a href="aptitude_test.php"><span class="icon">📝</span><span>Aptitude Test</span></a>
      <a href="resume_upload.php"><span class="icon">📄</span><span>Resume Check</span></a>
      <a href="career_roadmap.php"><span class="icon">🗺️</span><span>Career Roadmap</span></a>
      <a href="job_matching.php"><span class="icon">🎯</span><span>Job Matching</span></a>
      <a href="profile.php" class="active"><span class="icon">👤</span><span>Profile</span></a>
      <a href="logout.php"><span class="icon">🚪</span><span>Logout</span></a>
    </nav>
    <div class="sidebar-footer">Semester <?= $user['semester'] ?> · <?= $user['branch'] ?></div>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">👤 My Profile Settings</div>
      <div class="topbar-user">
        <span><?= htmlspecialchars($user['full_name']) ?></span>
        <?php if ($avatarUrl): ?>
          <div class="avatar" style="overflow:hidden"><img src="<?= $avatarUrl ?>" style="width:100%;height:100%;object-fit:cover"></div>
        <?php else: ?>
          <div class="avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="content-area" style="max-width: 1000px">
      <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        
        <!-- Left: Profile Details form -->
        <div class="card">
          <div class="profile-card-header">
            <div class="profile-pic-large">
              <?php if ($avatarUrl): ?>
                <img src="<?= $avatarUrl ?>" alt="Avatar">
              <?php else: ?>
                <?= strtoupper(substr($user['full_name'],0,1)) ?>
              <?php endif; ?>
            </div>
            <div>
              <div class="profile-meta-title"><?= htmlspecialchars($user['full_name']) ?></div>
              <div class="profile-meta-sub">Member since <?= date('F Y', strtotime($user['created_at'])) ?></div>
              <div class="profile-meta-sub">Role: <?= htmlspecialchars($user['target_role'] ?: 'Not Specified') ?></div>
            </div>
          </div>

          <form method="POST" action="profile.php" enctype="multipart/form-data">
            <input type="hidden" name="update_profile" value="1">
            
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" 
                       value="<?= htmlspecialchars($user['full_name']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" 
                       value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Roll Number</label>
                <input type="text" name="roll_no" class="form-control" 
                       value="<?= htmlspecialchars($user['roll_no'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Branch</label>
                <select name="branch" class="form-control">
                  <option value="CSE" <?= $user['branch'] === 'CSE' ? 'selected' : '' ?>>CSE</option>
                  <option value="IT" <?= $user['branch'] === 'IT' ? 'selected' : '' ?>>IT</option>
                  <option value="ECE" <?= $user['branch'] === 'ECE' ? 'selected' : '' ?>>ECE</option>
                  <option value="ME" <?= $user['branch'] === 'ME' ? 'selected' : '' ?>>ME</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Semester</label>
                <select name="semester" class="form-control">
                  <?php for($i=1; $i<=8; $i++): ?>
                    <option value="<?= $i ?>" <?= $user['semester'] == $i ? 'selected' : '' ?>><?= $i ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Target Career Role</label>
                <select name="target_role" class="form-control">
                  <option value="" <?= empty($user['target_role']) ? 'selected' : '' ?>>-- Select Target Role --</option>
                  <option value="Web Developer" <?= $user['target_role'] === 'Web Developer' ? 'selected' : '' ?>>Web Developer</option>
                  <option value="Software Engineer" <?= $user['target_role'] === 'Software Engineer' ? 'selected' : '' ?>>Software Engineer</option>
                  <option value="Data Analyst" <?= $user['target_role'] === 'Data Analyst' ? 'selected' : '' ?>>Data Analyst</option>
                  <option value="Full Stack Developer" <?= $user['target_role'] === 'Full Stack Developer' ? 'selected' : '' ?>>Full Stack Developer</option>
                  <option value="Cloud Engineer" <?= $user['target_role'] === 'Cloud Engineer' ? 'selected' : '' ?>>Cloud Engineer</option>
                  <option value="UI/UX Designer" <?= $user['target_role'] === 'UI/UX Designer' ? 'selected' : '' ?>>UI/UX Designer</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Update Profile Picture</label>
              <input type="file" name="profile_pic_file" class="form-control" accept="image/*">
              <div class="form-hint">Format: JPG, JPEG, PNG, GIF (Max 1MB). Leave empty to keep current picture.</div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 10px">💾 Save Changes</button>
          </form>
        </div>

        <!-- Right: Change Password -->
        <div>
          <div class="card">
            <div class="card-title">🔐 Change Password</div>
            <form method="POST" action="profile.php">
              <input type="hidden" name="change_password" value="1">
              
              <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
              </div>
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required>
              </div>
              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
              </div>

              <button type="submit" class="btn btn-outline btn-block" style="margin-top: 8px">🔄 Update Password</button>
            </form>
          </div>

          <div class="card">
            <div class="card-title">📈 Account Summary</div>
            <div style="font-size:13px; color:#64748b; line-height: 1.8">
              <div>📍 Registered Email:</div>
              <div style="font-weight:600; color:var(--text); margin-bottom: 8px"><?= htmlspecialchars($user['email']) ?></div>
              
              <div>🎓 Academic Info:</div>
              <div style="font-weight:600; color:var(--text)">
                Branch: <?= htmlspecialchars($user['branch']) ?><br>
                Semester: <?= $user['semester'] ?>
              </div>
            </div>
          </div>
        </div>

      </div><!-- Grid -->
    </div><!-- Content Area -->
  </div><!-- Main Content -->
</div>
<script src="script.js"></script>
</body>
</html>
