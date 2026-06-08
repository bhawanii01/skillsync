<?php
require_once 'config.php';
requireLogin();

$userId = $_SESSION['user_id'];
$user   = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();

// Compute / refresh readiness scores
$scores = computeOverallScore($conn, $userId);

// Fetch skill count per category
$skillsData = [];
$res = $conn->query("SELECT s.category, COUNT(*) as cnt FROM user_skills us
                     JOIN skills s ON s.id = us.skill_id
                     WHERE us.user_id=$userId GROUP BY s.category");
while ($r = $res->fetch_assoc()) $skillsData[$r['category']] = $r['cnt'];

// Last aptitude attempt
$lastApt = $conn->query("SELECT * FROM aptitude_attempts WHERE user_id=$userId ORDER BY attempted_at DESC LIMIT 1")->fetch_assoc();

// Latest resume
$resume = $conn->query("SELECT * FROM resumes WHERE user_id=$userId ORDER BY uploaded_at DESC LIMIT 1")->fetch_assoc();

// Announcements
$annRes = $conn->query("SELECT a.title, a.content, a.created_at FROM announcements a ORDER BY a.created_at DESC LIMIT 3");
$announcements = [];
while ($r = $annRes->fetch_assoc()) $announcements[] = $r;

// Skill gap: top 5 missing skills for target role
$SKILL_MATRIX = [
  'Web Developer'      => ['HTML','CSS','JavaScript','React.js','PHP','MySQL','Git','REST API'],
  'Software Engineer'  => ['Data Structures','Algorithms','C++','Java','OOP','DBMS','Computer Networks','Git'],
  'Data Analyst'       => ['Python','SQL','Excel','Statistics','Power BI','Pandas','Communication'],
  'Full Stack Developer'=> ['React.js','Node.js','MySQL','MongoDB','Git','REST API'],
  'Cloud Engineer'     => ['AWS','Linux','Docker','Computer Networks','Git','OOP'],
  'UI/UX Designer'     => ['Figma','CSS','HTML','Communication','Problem Solving'],
];
$mySkills = [];
$skR = $conn->query("SELECT s.skill_name FROM user_skills us JOIN skills s ON s.id=us.skill_id WHERE us.user_id=$userId");
while ($r = $skR->fetch_assoc()) $mySkills[] = $r['skill_name'];

$targetRole  = $user['target_role'] ?? 'Software Engineer';
$roleSkills  = $SKILL_MATRIX[$targetRole] ?? [];
$missingSkills = array_values(array_diff($roleSkills, $mySkills));
$scoreLabel  = $scores['overall'] >= 70 ? '🟢 Good' : ($scores['overall'] >= 40 ? '🟡 Average' : '🔴 Needs Work');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard – SkillSync</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page-wrap">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">Skill<span>Sync</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="active"><span class="icon">🏠</span><span>Dashboard</span></a>
      <a href="skill_analysis.php"><span class="icon">🧠</span><span>Skill Analysis</span></a>
      <a href="aptitude_test.php"><span class="icon">📝</span><span>Aptitude Test</span></a>
      <a href="resume_upload.php"><span class="icon">📄</span><span>Resume Check</span></a>
      <a href="career_roadmap.php"><span class="icon">🗺️</span><span>Career Roadmap</span></a>
      <a href="job_matching.php"><span class="icon">🎯</span><span>Job Matching</span></a>
      <a href="profile.php"><span class="icon">👤</span><span>Profile</span></a>
      <a href="logout.php"><span class="icon">🚪</span><span>Logout</span></a>
    </nav>
    <div class="sidebar-footer">Semester <?= $user['semester'] ?> · <?= $user['branch'] ?></div>
  </aside>

  <!-- Main -->
  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">Welcome back, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?> 👋</div>
      <div class="topbar-user">
        <span><?= htmlspecialchars($user['full_name']) ?></span>
        <div class="avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      </div>
    </div>

    <div class="content-area">

      <!-- Stat Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">🧠</div>
          <div>
            <div class="stat-val"><?= count($mySkills) ?></div>
            <div class="stat-label">Skills Added</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">📝</div>
          <div>
            <div class="stat-val"><?= $lastApt ? round($lastApt['percentage']) . '%' : 'N/A' ?></div>
            <div class="stat-label">Last Aptitude Score</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon yellow">📄</div>
          <div>
            <div class="stat-val"><?= $resume ? $resume['strength_score'] . '%' : 'N/A' ?></div>
            <div class="stat-label">Resume Strength</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon cyan">🎯</div>
          <div>
            <div class="stat-val"><?= count($missingSkills) ?></div>
            <div class="stat-label">Skill Gaps</div>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px">

        <!-- Readiness Score -->
        <div class="card" style="display:flex;flex-direction:column;align-items:center;justify-content:center">
          <div class="card-title">🏆 Career Readiness Score</div>
          <div class="score-ring" data-score="<?= $scores['overall'] ?>"
               style="--pct:<?= $scores['overall'] ?>">
            <div class="score-ring-val"><?= $scores['overall'] ?></div>
          </div>
          <div style="margin-top:12px;font-size:15px;font-weight:600"><?= $scoreLabel ?></div>
          <div style="font-size:13px;color:#64748b;margin-top:4px">Target: <?= htmlspecialchars($targetRole) ?></div>

          <div style="width:100%;margin-top:20px">
            <div class="progress-bar-wrap">
              <div class="progress-bar-label"><span>Skills</span><span><?= $scores['skillScore'] ?>%</span></div>
              <div class="progress-bar-track"><div class="progress-bar-fill" data-pct="<?= $scores['skillScore'] ?>"></div></div>
            </div>
            <div class="progress-bar-wrap">
              <div class="progress-bar-label"><span>Aptitude</span><span><?= $scores['aptScore'] ?>%</span></div>
              <div class="progress-bar-track"><div class="progress-bar-fill" data-pct="<?= $scores['aptScore'] ?>"></div></div>
            </div>
            <div class="progress-bar-wrap">
              <div class="progress-bar-label"><span>Resume</span><span><?= $scores['resumeScore'] ?>%</span></div>
              <div class="progress-bar-track"><div class="progress-bar-fill" data-pct="<?= $scores['resumeScore'] ?>"></div></div>
            </div>
            <div class="progress-bar-wrap">
              <div class="progress-bar-label"><span>Roadmap</span><span><?= $scores['roadmapScore'] ?>%</span></div>
              <div class="progress-bar-track"><div class="progress-bar-fill" data-pct="<?= $scores['roadmapScore'] ?>"></div></div>
            </div>
          </div>
        </div>

        <!-- Skill Gap + Quick Actions -->
        <div>
          <div class="card">
            <div class="card-title">⚡ Skill Gap for <?= htmlspecialchars($targetRole) ?></div>
            <?php if (empty($missingSkills)): ?>
              <div class="alert alert-success">🎉 You have all the required skills for this role!</div>
            <?php else: ?>
              <p style="font-size:13px;color:#64748b;margin-bottom:12px">
                Skills you still need to learn:
              </p>
              <?php foreach ($missingSkills as $sk): ?>
                <span class="skill-tag missing">✗ <?= htmlspecialchars($sk) ?></span>
              <?php endforeach; ?>
              <?php foreach ($mySkills as $sk): if (!in_array($sk, $roleSkills)) continue; ?>
                <span class="skill-tag has">✓ <?= htmlspecialchars($sk) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
            <div style="margin-top:14px">
              <a href="skill_analysis.php" class="btn btn-primary btn-sm">Update Skills →</a>
            </div>
          </div>

          <div class="card">
            <div class="card-title">🚀 Quick Actions</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <a href="aptitude_test.php" class="btn btn-outline">📝 Take Aptitude Test</a>
              <a href="resume_upload.php" class="btn btn-outline">📄 Check Resume</a>
              <a href="career_roadmap.php" class="btn btn-outline">🗺️ View Roadmap</a>
              <a href="job_matching.php"  class="btn btn-outline">🎯 Match Jobs</a>
            </div>
          </div>

          <?php if ($announcements): ?>
          <div class="card">
            <div class="card-title">📢 Announcements</div>
            <?php foreach ($announcements as $ann): ?>
              <div style="border-bottom:1px solid #e2e8f0;padding:10px 0;last-child{border:none}">
                <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($ann['title']) ?></div>
                <div style="font-size:13px;color:#64748b;margin-top:3px"><?= htmlspecialchars($ann['content']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div><!-- end grid -->

    </div><!-- content-area -->
  </div><!-- main-content -->
</div>
<script src="script.js"></script>
</body>
</html>