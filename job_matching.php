<?php
require_once 'config.php';
requireLogin();

$userId = $_SESSION['user_id'];
$user   = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();

// Load user's current skills
$mySkillsResult = $conn->query("SELECT skill_name FROM user_skills us JOIN skills s ON s.id=us.skill_id WHERE us.user_id=$userId");
$mySkills = [];
while ($r = $mySkillsResult->fetch_assoc()) {
    $mySkills[] = strtolower(trim($r['skill_name']));
}

// Fetch job roles
$jobRolesRes = $conn->query("SELECT * FROM job_roles ORDER BY role_name");
$jobsMatched = [];

while ($job = $jobRolesRes->fetch_assoc()) {
    $reqSkillsRaw = explode(',', $job['req_skills']);
    $reqSkills = array_map(function($item) {
        return trim($item);
    }, $reqSkillsRaw);

    $matched = [];
    $missing = [];

    foreach ($reqSkills as $skill) {
        if (in_array(strtolower($skill), $mySkills)) {
            $matched[] = $skill;
        } else {
            $missing[] = $skill;
        }
    }

    $totalReq = count($reqSkills);
    $matchCount = count($matched);
    $percentage = $totalReq > 0 ? round(($matchCount / $totalReq) * 100) : 0;

    $job['matched_skills'] = $matched;
    $job['missing_skills'] = $missing;
    $job['match_percentage'] = $percentage;

    $jobsMatched[] = $job;
}

// Sort by match percentage descending
usort($jobsMatched, function($a, $b) {
    return $b['match_percentage'] <=> $a['match_percentage'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Matching – SkillSync</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .job-card {
      background: var(--card);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      padding: 24px;
      margin-bottom: 20px;
      box-shadow: var(--shadow);
      display: grid;
      grid-template-columns: 3fr 1fr;
      gap: 20px;
      transition: transform var(--transition), box-shadow var(--transition);
    }
    .job-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }
    .job-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 12px;
    }
    .job-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--text);
    }
    .job-details {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 12px;
    }
    .job-detail-item {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .job-desc {
      font-size: 14px;
      color: #475569;
      line-height: 1.6;
      margin-bottom: 16px;
    }
    .match-score-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border-left: 1px solid var(--border);
      padding-left: 20px;
    }
    .match-pct {
      font-size: 32px;
      font-weight: 800;
      line-height: 1;
      margin-bottom: 4px;
    }
    .match-label {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    @media(max-width: 768px) {
      .job-card {
        grid-template-columns: 1fr;
      }
      .match-score-wrap {
        border-left: none;
        border-top: 1px solid var(--border);
        padding-left: 0;
        padding-top: 20px;
        flex-direction: row;
        justify-content: space-between;
      }
      .match-pct {
        font-size: 24px;
        margin-bottom: 0;
      }
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
      <a href="job_matching.php" class="active"><span class="icon">🎯</span><span>Job Matching</span></a>
      <a href="profile.php"><span class="icon">👤</span><span>Profile</span></a>
      <a href="logout.php"><span class="icon">🚪</span><span>Logout</span></a>
    </nav>
    <div class="sidebar-footer">Semester <?= $user['semester'] ?> · <?= $user['branch'] ?></div>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">🎯 Job Role Compatibility Matcher</div>
      <div class="topbar-user">
        <span><?= htmlspecialchars($user['full_name']) ?></span>
        <div class="avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      </div>
    </div>

    <div class="content-area">
      <div class="card" style="margin-bottom: 24px">
        <div class="card-title">🎯 compatibility Matching Engine</div>
        <div class="card-subtitle">
          Matches your current skills against our job role requirements. Add more skills to improve your scores.
        </div>
        <div style="font-size: 14px; color: #64748b">
          Your active skills: 
          <?php if ($mySkills): ?>
            <strong style="color: var(--primary)"><?= count($mySkills) ?> skills loaded</strong>.
          <?php else: ?>
            <strong style="color: var(--danger)">No skills added yet. <a href="skill_analysis.php">Go to Skill Analysis</a></strong> to select your skills first.
          <?php endif; ?>
        </div>
      </div>

      <!-- Job List -->
      <?php foreach ($jobsMatched as $job): 
          $pct = $job['match_percentage'];
          $color = $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
          $badgeClass = $pct >= 80 ? 'badge-green' : ($pct >= 50 ? 'badge-yellow' : 'badge-red');
      ?>
        <div class="job-card">
          <div>
            <div class="job-header">
              <span class="job-title"><?= htmlspecialchars($job['role_name']) ?></span>
              <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($job['company_type']) ?> Company</span>
            </div>
            
            <div class="job-details">
              <div class="job-detail-item">💼 Average Package: <strong><?= htmlspecialchars($job['avg_package']) ?></strong></div>
              <div class="job-detail-item">🎓 Min. CGPA: <strong><?= htmlspecialchars($job['min_cgpa']) ?></strong></div>
            </div>

            <div class="job-desc">
              <?= htmlspecialchars($job['description']) ?>
            </div>

            <!-- Matching Skills list -->
            <div style="margin-bottom: 12px">
              <div style="font-size: 12px; font-weight: 600; color: #065f46; margin-bottom: 6px">✓ MATCHED SKILLS (<?= count($job['matched_skills']) ?>)</div>
              <?php if ($job['matched_skills']): ?>
                <?php foreach ($job['matched_skills'] as $s): ?>
                  <span class="skill-tag has"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="font-size: 13px; color: #94a3b8; font-style: italic">None</span>
              <?php endif; ?>
            </div>

            <!-- Missing Skills list -->
            <div>
              <div style="font-size: 12px; font-weight: 600; color: #991b1b; margin-bottom: 6px">✗ MISSING SKILLS (<?= count($job['missing_skills']) ?>)</div>
              <?php if ($job['missing_skills']): ?>
                <?php foreach ($job['missing_skills'] as $s): ?>
                  <span class="skill-tag missing"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="badge badge-green">🎉 No missing skills!</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="match-score-wrap">
            <div class="match-pct" style="color: <?= $color ?>"><?= $pct ?>%</div>
            <div class="match-label" style="color: <?= $color ?>">Compatibility</div>
            <div class="progress-bar-wrap" style="width: 100%; margin-top: 12px">
              <div class="progress-bar-track" style="height: 6px">
                <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $color ?>"></div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script src="script.js"></script>
</body>
</html>
