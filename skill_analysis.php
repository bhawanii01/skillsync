<?php
require_once 'config.php';
requireLogin();
$userId = $_SESSION['user_id'];
$user   = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();

// Handle skill update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_skills'])) {
    $selectedRaw = $_POST['selected_skills'] ?? '[]';
    $targetRole  = clean($conn, $_POST['target_role'] ?? '');

    // Update target role
    $stmt = $conn->prepare("UPDATE users SET target_role=? WHERE id=?");
    $stmt->bind_param('si', $targetRole, $userId);
    $stmt->execute();

    // Decode skills
    $selected = json_decode(stripslashes($selectedRaw), true) ?: [];

    // Clear old skills, re-insert selected
    $conn->query("DELETE FROM user_skills WHERE user_id=$userId");
    foreach ($selected as $skillName) {
        $skillName = $conn->real_escape_string(trim($skillName));
        $row = $conn->query("SELECT id FROM skills WHERE skill_name='$skillName'")->fetch_assoc();
        if ($row) {
            $sid = $row['id'];
            $conn->query("INSERT IGNORE INTO user_skills (user_id, skill_id) VALUES ($userId, $sid)");
        }
    }
    computeOverallScore($conn, $userId);
    header("Location: skill_analysis.php?saved=1");
    exit;
}

// Load all skills grouped by category
$allSkillsResult = $conn->query("SELECT * FROM skills ORDER BY category, skill_name");
$allSkills = [];
while ($r = $allSkillsResult->fetch_assoc()) {
    $allSkills[$r['category']][] = $r;
}

// Load user's current skills
$mySkillsResult = $conn->query("SELECT skill_name FROM user_skills us JOIN skills s ON s.id=us.skill_id WHERE us.user_id=$userId");
$mySkills = [];
while ($r = $mySkillsResult->fetch_assoc()) $mySkills[] = $r['skill_name'];

$targetRole = $user['target_role'] ?? 'Software Engineer';
$saved = isset($_GET['saved']);

// Skill Matrix for gap analysis
$SKILL_MATRIX = [
  'Web Developer'       => ['HTML','CSS','JavaScript','React.js','PHP','MySQL','Git','REST API'],
  'Software Engineer'   => ['Data Structures','Algorithms','C++','Java','OOP','DBMS','Computer Networks','Git'],
  'Data Analyst'        => ['Python','SQL','Excel','Statistics','Power BI','Pandas','Communication'],
  'Full Stack Developer'=> ['React.js','Node.js','MySQL','MongoDB','Git','REST API'],
  'Cloud Engineer'      => ['AWS','Linux','Docker','Computer Networks','Git','OOP'],
  'UI/UX Designer'      => ['Figma','CSS','HTML','Communication','Problem Solving'],
];
$roleSkills    = $SKILL_MATRIX[$targetRole] ?? [];
$hasSkills     = array_values(array_intersect($mySkills, $roleSkills));
$missingSkills = array_values(array_diff($roleSkills, $mySkills));
$readinessScore = count($roleSkills) > 0 ? round(count($hasSkills) / count($roleSkills) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Skill Gap Analysis – SkillSync</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .skill-btn {
      display: inline-block; padding: 7px 14px; border-radius: 99px;
      border: 1.5px solid #e2e8f0; font-size: 13px; cursor: pointer;
      margin: 4px; transition: .2s; background: #fff; user-select: none;
    }
    .skill-btn:hover { border-color: #4f46e5; }
    .skill-btn.selected { background: #4f46e5; color: #fff; border-color: #4f46e5; }
    .cat-title { font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase;
                 letter-spacing: .5px; margin: 16px 0 8px; }
  </style>
</head>
<body>
<div class="page-wrap">
  <aside class="sidebar">
    <div class="sidebar-logo">Skill<span>Sync</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="icon">🏠</span><span>Dashboard</span></a>
      <a href="skill_analysis.php" class="active"><span class="icon">🧠</span><span>Skill Analysis</span></a>
      <a href="aptitude_test.php"><span class="icon">📝</span><span>Aptitude Test</span></a>
      <a href="resume_upload.php"><span class="icon">📄</span><span>Resume Check</span></a>
      <a href="career_roadmap.php"><span class="icon">🗺️</span><span>Career Roadmap</span></a>
      <a href="job_matching.php"><span class="icon">🎯</span><span>Job Matching</span></a>
      <a href="profile.php"><span class="icon">👤</span><span>Profile</span></a>
      <a href="logout.php"><span class="icon">🚪</span><span>Logout</span></a>
    </nav>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">🧠 Skill Gap Analysis Engine</div>
      <div class="topbar-user">
        <span><?= htmlspecialchars($user['full_name']) ?></span>
        <div class="avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      </div>
    </div>

    <div class="content-area">
      <?php if ($saved): ?>
        <div class="alert alert-success">✅ Skills updated successfully! Your readiness score has been recalculated.</div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">

        <!-- Skill Selector -->
        <div class="card">
          <div class="card-title">Select Your Current Skills</div>
          <div class="card-subtitle">Click skills you know. Select your target role to see the gap.</div>

          <form method="POST" action="skill_analysis.php" id="skill-form">
            <div class="form-group">
              <label class="form-label">Target Career Role</label>
              <select id="target_role" name="target_role" class="form-control" onchange="updateLiveScore()">
                <?php foreach (array_keys($SKILL_MATRIX) as $r): ?>
                  <option value="<?=$r?>" <?= $targetRole===$r?'selected':'' ?>><?=$r?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="margin-bottom:12px;padding:10px 16px;background:#eef2ff;border-radius:8px;font-size:14px">
              Live Skill Score for selected role:
              <strong id="live_score" style="font-size:20px;margin-left:8px;color:#4f46e5"><?= $readinessScore ?></strong>/100
            </div>

            <?php foreach ($allSkills as $cat => $skills): ?>
              <div class="cat-title"><?= $cat ?></div>
              <div>
                <?php foreach ($skills as $sk): ?>
                  <div class="skill-btn <?= in_array($sk['skill_name'], $mySkills) ? 'selected' : '' ?>"
                       data-skill="<?= htmlspecialchars($sk['skill_name']) ?>"
                       onclick="toggleSkill(this, '<?= addslashes($sk['skill_name']) ?>')">
                    <?= htmlspecialchars($sk['skill_name']) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>

            <input type="hidden" id="selected_skills_input" name="selected_skills"
                   value="<?= htmlspecialchars(json_encode($mySkills)) ?>">
            <div style="margin-top:20px">
              <button type="submit" class="btn btn-primary">💾 Save Skills & Recalculate Score</button>
            </div>
          </form>
        </div>

        <!-- Gap Analysis Panel -->
        <div>
          <div class="card">
            <div class="card-title">📊 Gap Analysis</div>
            <div style="font-size:13px;color:#64748b;margin-bottom:14px">Role: <strong><?= htmlspecialchars($targetRole) ?></strong></div>

            <div class="score-ring-wrap" style="margin-bottom:20px">
              <div class="score-ring" data-score="<?= $readinessScore ?>" style="--pct:<?= $readinessScore ?>">
                <div class="score-ring-val"><?= $readinessScore ?></div>
              </div>
              <div class="score-label">Role Fit Score</div>
            </div>

            <div style="margin-bottom:12px">
              <div style="font-size:13px;font-weight:600;color:#065f46;margin-bottom:6px">✅ Skills You Have</div>
              <?php if ($hasSkills): ?>
                <?php foreach ($hasSkills as $s): ?>
                  <span class="skill-tag has"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="font-size:13px;color:#94a3b8">None yet — add your skills!</span>
              <?php endif; ?>
            </div>

            <div>
              <div style="font-size:13px;font-weight:600;color:#991b1b;margin-bottom:6px">✗ Skills to Learn</div>
              <?php if ($missingSkills): ?>
                <?php foreach ($missingSkills as $s): ?>
                  <span class="skill-tag missing"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="alert alert-success" style="margin-top:8px">🎉 All role skills covered!</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="card">
            <div class="card-title">💡 Learning Priority</div>
            <?php if ($missingSkills): ?>
              <p style="font-size:13px;color:#64748b;margin-bottom:10px">Focus on these first:</p>
              <?php foreach (array_slice($missingSkills,0,4) as $i => $s): ?>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                  <div style="width:22px;height:22px;border-radius:50%;background:#4f46e5;color:#fff;
                              font-size:12px;display:flex;align-items:center;justify-content:center;font-weight:600">
                    <?= $i+1 ?>
                  </div>
                  <div>
                    <div style="font-size:14px;font-weight:500"><?= htmlspecialchars($s) ?></div>
                    <div style="font-size:12px;color:#94a3b8">High Priority</div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="alert alert-success">All skills covered for this role!</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="script.js"></script>
<script>
// Pre-mark selected skills on page load
document.querySelectorAll('.skill-btn.selected').forEach(btn => {
  btn.style.background='#4f46e5'; btn.style.color='#fff'; btn.style.borderColor='#4f46e5';
});
</script>
</body>
</html>