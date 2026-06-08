<?php
require_once 'config.php';
requireAdmin();

$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];

$error = ''; $success = '';

// Handle Announcement Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $title   = clean($conn, $_POST['title'] ?? '');
    $content = clean($conn, $_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $error = 'Both title and content are required for announcements.';
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, admin_id) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $title, $content, $adminId);
        if ($stmt->execute()) {
            $success = 'Announcement published successfully!';
        } else {
            $error = 'Failed to publish announcement.';
        }
        $stmt->close();
    }
}

// Handle Announcement Delete
if (isset($_GET['delete_announcement'])) {
    $annId = (int)$_GET['delete_announcement'];
    if ($conn->query("DELETE FROM announcements WHERE id = $annId")) {
        $success = 'Announcement deleted successfully.';
    } else {
        $error = 'Failed to delete announcement.';
    }
}

// Handle Student Delete
if (isset($_GET['delete_student'])) {
    $studentId = (int)$_GET['delete_student'];
    // Delete student (cascade will take care of user_skills, resumes, aptitude_attempts, career_roadmap_progress, readiness_scores)
    if ($conn->query("DELETE FROM users WHERE id = $studentId")) {
        $success = 'Student account deleted successfully.';
    } else {
        $error = 'Failed to delete student account.';
    }
}

// Fetch stats
$studentCount = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$skillsCount   = $conn->query("SELECT COUNT(*) as c FROM user_skills")->fetch_assoc()['c'];
$aptAttempts   = $conn->query("SELECT COUNT(*) as c FROM aptitude_attempts")->fetch_assoc()['c'];
$resumesCount  = $conn->query("SELECT COUNT(*) as c FROM resumes")->fetch_assoc()['c'];

// Fetch announcements
$annRes = $conn->query("SELECT a.id, a.title, a.content, a.created_at, adm.full_name as author FROM announcements a 
                        JOIN admin_users adm ON adm.id = a.admin_id 
                        ORDER BY a.created_at DESC");
$announcements = [];
while ($r = $annRes->fetch_assoc()) $announcements[] = $r;

// Fetch students list
$studentListRes = $conn->query("SELECT u.id, u.full_name, u.email, u.branch, u.semester, u.target_role, u.created_at, 
                                rs.overall_score 
                                FROM users u 
                                LEFT JOIN readiness_scores rs ON rs.user_id = u.id 
                                ORDER BY rs.overall_score DESC, u.created_at DESC");
$students = [];
while ($r = $studentListRes->fetch_assoc()) $students[] = $r;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard – SkillSync</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .admin-topbar {
      background: #1e293b;
      color: #fff;
    }
    .admin-topbar .topbar-user {
      color: #94a3b8;
    }
    .admin-sidebar {
      background: #0f172a;
    }
    .admin-sidebar-nav a:hover,
    .admin-sidebar-nav a.active {
      background: rgba(255,255,255,0.05);
      border-left-color: var(--warning);
    }
  </style>
</head>
<body>
<div class="page-wrap">
  <!-- Sidebar -->
  <aside class="sidebar admin-sidebar">
    <div class="sidebar-logo">Skill<span>Sync</span> <span class="badge badge-yellow" style="font-size: 9px; vertical-align: middle">ADMIN</span></div>
    <nav class="sidebar-nav admin-sidebar-nav">
      <a href="admin_dashboard.php" class="active"><span class="icon">📊</span><span>Dashboard</span></a>
      <a href="logout.php"><span class="icon">🚪</span><span>Logout</span></a>
    </nav>
    <div class="sidebar-footer">Admin Panel v1.0</div>
  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <div class="topbar admin-topbar">
      <div class="topbar-title">🛡️ Administrator Management Console</div>
      <div class="topbar-user">
        <span>Logged in as: <strong><?= htmlspecialchars($adminName) ?></strong></span>
        <div class="avatar" style="background: var(--warning); color: #000; font-weight: 700">A</div>
      </div>
    </div>

    <div class="content-area">
      <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

      <!-- Stat Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">👥</div>
          <div>
            <div class="stat-val"><?= $studentCount ?></div>
            <div class="stat-label">Total Students</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">🧠</div>
          <div>
            <div class="stat-val"><?= $skillsCount ?></div>
            <div class="stat-label">Skills Mapped</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon yellow">📝</div>
          <div>
            <div class="stat-val"><?= $aptAttempts ?></div>
            <div class="stat-label">Quiz Attempts</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan">📄</div>
          <div>
            <div class="stat-val"><?= $resumesCount ?></div>
            <div class="stat-label">Resumes Screened</div>
          </div>
        </div>
      </div>

      <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 24px">
        
        <!-- Students Directory -->
        <div>
          <div class="card">
            <div class="card-title">👥 Student Readiness Directory</div>
            <div class="card-subtitle">List of students ordered by overall career readiness scores.</div>
            
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Student Name</th>
                    <th>Branch / Sem</th>
                    <th>Target Role</th>
                    <th>Readiness Score</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($students): ?>
                    <?php foreach ($students as $student): 
                        $score = $student['overall_score'] ?? 0;
                        $color = $score >= 70 ? 'badge-green' : ($score >= 40 ? 'badge-yellow' : 'badge-red');
                    ?>
                      <tr>
                        <td>
                          <div style="font-weight: 600"><?= htmlspecialchars($student['full_name']) ?></div>
                          <div style="font-size: 12px; color: #64748b"><?= htmlspecialchars($student['email']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($student['branch']) ?> · Sem <?= $student['semester'] ?></td>
                        <td><?= htmlspecialchars($student['target_role'] ?: 'Not selected') ?></td>
                        <td>
                          <span class="badge <?= $color ?>" style="font-size:13px; font-weight:700"><?= $score ?>%</span>
                        </td>
                        <td>
                          <a href="admin_dashboard.php?delete_student=<?= $student['id'] ?>" 
                             class="btn btn-danger btn-sm" 
                             onclick="return confirm('Are you sure you want to delete this student account and all related data?')">
                            🗑️ Delete
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" style="text-align: center; color: #94a3b8; padding: 24px">No students registered yet.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Announcement and Management Panel -->
        <div>
          <!-- Create Announcement -->
          <div class="card">
            <div class="card-title">📢 Broadcast Announcement</div>
            <form method="POST" action="admin_dashboard.php">
              <input type="hidden" name="post_announcement" value="1">
              <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" placeholder="E.g., TCS Recruitment Drive 2026" required>
              </div>
              <div class="form-group">
                <label class="form-label">Content</label>
                <textarea name="content" class="form-control" rows="4" placeholder="Enter recruitment info, deadlines, or test announcements..." required></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-block">📢 Publish to Students</button>
            </form>
          </div>

          <!-- Existing Announcements -->
          <div class="card">
            <div class="card-title">📌 Active Announcements (<?= count($announcements) ?>)</div>
            <?php if ($announcements): ?>
              <?php foreach ($announcements as $ann): ?>
                <div style="border-bottom: 1px solid #e2e8f0; padding: 12px 0; position: relative">
                  <div style="font-weight: 600; font-size: 14px"><?= htmlspecialchars($ann['title']) ?></div>
                  <div style="font-size: 12px; color: #64748b; margin-top: 4px"><?= htmlspecialchars($ann['content']) ?></div>
                  <div style="font-size: 11px; color: #94a3b8; margin-top: 6px">
                    Posted by <?= htmlspecialchars($ann['author']) ?> on <?= date('d M, H:i', strtotime($ann['created_at'])) ?>
                  </div>
                  <div style="margin-top: 8px">
                    <a href="admin_dashboard.php?delete_announcement=<?= $ann['id'] ?>" 
                       style="font-size: 11px; color: #ef4444; font-weight: 600"
                       onclick="return confirm('Delete this announcement?')">
                      Delete Announcement ✗
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p style="font-size: 13px; color: #94a3b8; text-align: center; padding: 12px 0">No announcements published.</p>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- grid -->
    </div><!-- content-area -->
  </div><!-- main-content -->
</div>
<script src="script.js"></script>
</body>
</html>
