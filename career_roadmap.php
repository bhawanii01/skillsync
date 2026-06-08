<?php
require_once 'config.php';
requireLogin();
$userId = $_SESSION['user_id'];
$user   = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();
$targetRole = $user['target_role'] ?? 'Software Engineer';

// Handle phase completion toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_phase'])) {
    $phaseIdx   = (int)$_POST['phase_index'];
    $role       = clean($conn, $_POST['role']);
    $isComplete = (int)$_POST['is_complete'];
    $now        = $isComplete ? date('Y-m-d H:i:s') : null;
    $nowSql     = $isComplete ? "'$now'" : "NULL";

    $conn->query("INSERT INTO career_roadmap_progress (user_id, role, phase_index, is_complete, completed_at)
                  VALUES ($userId, '$role', $phaseIdx, $isComplete, $nowSql)
                  ON DUPLICATE KEY UPDATE is_complete=$isComplete, completed_at=$nowSql");
    computeOverallScore($conn, $userId);
    header("Location: career_roadmap.php");
    exit;
}

// Handle role switch
if (isset($_GET['role'])) {
    $newRole = clean($conn, $_GET['role']);
    $conn->query("UPDATE users SET target_role='$newRole' WHERE id=$userId");
    $targetRole = $newRole;
    header("Location: career_roadmap.php");
    exit;
}

// Full roadmap data
$ROADMAPS = [
  'Web Developer' => [
    ['phase'=>'Foundation',          'duration'=>'Month 1–2', 'skills'=>['HTML5','CSS3','Responsive Design','Flexbox/Grid'],
     'resources'=>['W3Schools','freeCodeCamp','MDN Docs'], 'outcome'=>'Build 2 static responsive websites'],
    ['phase'=>'JavaScript Core',     'duration'=>'Month 3',   'skills'=>['ES6+','DOM Manipulation','Fetch API','Local Storage'],
     'resources'=>['javascript.info','Eloquent JavaScript'], 'outcome'=>'Build an interactive To-Do app'],
    ['phase'=>'Frontend Framework',  'duration'=>'Month 4–5', 'skills'=>['React.js','Components','State & Props','Hooks'],
     'resources'=>['React Docs','Scrimba React'], 'outcome'=>'Build a weather or movie app with React'],
    ['phase'=>'Backend Basics',      'duration'=>'Month 6',   'skills'=>['PHP','Node.js','Express.js','REST API Design'],
     'resources'=>['PHP.net','The Odin Project'], 'outcome'=>'Create a REST API for a blog system'],
    ['phase'=>'Database Integration','duration'=>'Month 7',   'skills'=>['MySQL','SQL Joins','PDO/MySQLi','MongoDB Basics'],
     'resources'=>['MySQL Tutorial','SQLZoo'], 'outcome'=>'Build a full CRUD application'],
    ['phase'=>'Deployment & Portfolio','duration'=>'Month 8', 'skills'=>['Git/GitHub','Netlify','Vercel','Portfolio Site'],
     'resources'=>['GitHub Docs','Netlify Docs'], 'outcome'=>'Deploy 3 projects, create portfolio'],
  ],
  'Software Engineer' => [
    ['phase'=>'C++ & OOP Fundamentals','duration'=>'Month 1–2','skills'=>['C++ Syntax','OOP Concepts','STL','Pointers & Memory'],
     'resources'=>['cppreference.com','LearnCPP.com'], 'outcome'=>'Solve 30 beginner problems on HackerRank'],
    ['phase'=>'Data Structures',      'duration'=>'Month 3–4', 'skills'=>['Arrays','Linked Lists','Stacks','Queues','Trees','Hashing'],
     'resources'=>['GeeksforGeeks','Visualgo.net'], 'outcome'=>'Implement all DS from scratch'],
    ['phase'=>'Algorithms',           'duration'=>'Month 5',   'skills'=>['Sorting','Searching','Recursion','Dynamic Programming'],
     'resources'=>['CP-Algorithms','CLRS Book'], 'outcome'=>'Solve 50 problems on LeetCode'],
    ['phase'=>'CS Fundamentals',      'duration'=>'Month 6',   'skills'=>['DBMS','OS Concepts','Computer Networks','SQL'],
     'resources'=>['Gate Smashers YouTube','Neso Academy'], 'outcome'=>'Complete DBMS & OS short courses'],
    ['phase'=>'Interview Prep',       'duration'=>'Month 7–8', 'skills'=>['System Design Basics','LLD','Mock Interviews','Problem Patterns'],
     'resources'=>['Striver DSA Sheet','InterviewBit'], 'outcome'=>'Attempt 5+ mock interviews'],
  ],
  'Data Analyst' => [
    ['phase'=>'Statistics & Excel',   'duration'=>'Month 1–2', 'skills'=>['Descriptive Stats','Excel Charts','Pivot Tables','Normal Distribution'],
     'resources'=>['Khan Academy','Excel Easy'], 'outcome'=>'Analyse a real dataset in Excel'],
    ['phase'=>'Python for Data',      'duration'=>'Month 3–4', 'skills'=>['NumPy','Pandas','Matplotlib','Seaborn'],
     'resources'=>['Kaggle Learn','DataCamp'], 'outcome'=>'Complete a Kaggle beginner notebook'],
    ['phase'=>'SQL & Databases',      'duration'=>'Month 5',   'skills'=>['SQL Queries','Joins','Subqueries','Aggregations'],
     'resources'=>['SQLZoo','Mode Analytics SQL'], 'outcome'=>'Complete 50 SQL challenges'],
    ['phase'=>'Data Visualization',   'duration'=>'Month 6',   'skills'=>['Power BI','Tableau Basics','Dashboard Design'],
     'resources'=>['Power BI Docs','Tableau Public'], 'outcome'=>'Build an interactive sales dashboard'],
    ['phase'=>'Projects & Portfolio', 'duration'=>'Month 7–8', 'skills'=>['EDA Projects','Storytelling with Data','GitHub Portfolio'],
     'resources'=>['Kaggle Datasets','Towards Data Science'], 'outcome'=>'Publish 2 Kaggle notebooks publicly'],
  ],
  'Full Stack Developer' => [
    ['phase'=>'HTML/CSS/JS Core',     'duration'=>'Month 1–2', 'skills'=>['HTML5','CSS3','JavaScript ES6','Git'],
     'resources'=>['The Odin Project','freeCodeCamp'], 'outcome'=>'Build 3 front-end projects'],
    ['phase'=>'React Frontend',       'duration'=>'Month 3',   'skills'=>['React.js','Hooks','React Router','Axios'],
     'resources'=>['React Docs','Scrimba'], 'outcome'=>'Build a SPA with React'],
    ['phase'=>'Node.js & Express',    'duration'=>'Month 4–5', 'skills'=>['Node.js','Express','REST APIs','Middleware','JWT'],
     'resources'=>['Node Docs','JWT.io'], 'outcome'=>'Build a secure REST API with auth'],
    ['phase'=>'Databases',            'duration'=>'Month 6',   'skills'=>['MySQL','MongoDB','Mongoose','Sequelize'],
     'resources'=>['MongoDB University','MySQL Tutorial'], 'outcome'=>'Integrate DB into your API'],
    ['phase'=>'Full Stack Projects',  'duration'=>'Month 7–8', 'skills'=>['MERN/LAMP Stack','Deployment','Docker Basics'],
     'resources'=>['DigitalOcean Tutorials'], 'outcome'=>'Deploy a full-stack app publicly'],
  ],
  'UI/UX Designer' => [
    ['phase'=>'Design Fundamentals',  'duration'=>'Month 1–2', 'skills'=>['Color Theory','Typography','Layout Principles','Accessibility'],
     'resources'=>['Google UX Design Certificate','Interaction Design Foundation'], 'outcome'=>'Design a mood board & style guide'],
    ['phase'=>'Figma Proficiency',    'duration'=>'Month 3',   'skills'=>['Figma Components','Auto Layout','Prototyping','Design Systems'],
     'resources'=>['Figma Academy','DesignCode.io'], 'outcome'=>'Redesign any popular app UI in Figma'],
    ['phase'=>'User Research',        'duration'=>'Month 4',   'skills'=>['User Personas','Journey Mapping','Usability Testing','A/B Testing'],
     'resources'=>['Nielsen Norman Group','UX Collective'], 'outcome'=>'Conduct a usability test & report'],
    ['phase'=>'Portfolio & HTML/CSS', 'duration'=>'Month 5–6', 'skills'=>['HTML','CSS Animations','Responsive Design','Portfolio'],
     'resources'=>['Awwwards','Dribbble'], 'outcome'=>'Build a design portfolio website'],
  ],
  'Cloud Engineer' => [
    ['phase'=>'Linux & Networking',   'duration'=>'Month 1–2', 'skills'=>['Linux CLI','TCP/IP','DNS','SSH','Bash Scripting'],
     'resources'=>['Linux Journey','Professor Messer'], 'outcome'=>'Set up and manage a Linux server'],
    ['phase'=>'AWS Fundamentals',     'duration'=>'Month 3–4', 'skills'=>['EC2','S3','IAM','VPC','Route53','RDS'],
     'resources'=>['AWS Free Tier','A Cloud Guru'], 'outcome'=>'Deploy a web app on AWS EC2'],
    ['phase'=>'Docker & Containers',  'duration'=>'Month 5',   'skills'=>['Docker','Dockerfile','Docker Compose','Container Networking'],
     'resources'=>['Docker Docs','Play with Docker'], 'outcome'=>'Containerise a Node.js app'],
    ['phase'=>'DevOps & CI/CD',       'duration'=>'Month 6–7', 'skills'=>['GitHub Actions','Jenkins','Terraform Basics','Monitoring'],
     'resources'=>['GitHub Actions Docs','HashiCorp Learn'], 'outcome'=>'Build a full CI/CD pipeline'],
    ['phase'=>'Certification Prep',   'duration'=>'Month 8',   'skills'=>['AWS SAA Exam Prep','Cloud Architecture','Best Practices'],
     'resources'=>['Stephane Maarek Udemy','AWS Practice Exams'], 'outcome'=>'Attempt AWS Certified Cloud Practitioner'],
  ],
];

$roadmap = $ROADMAPS[$targetRole] ?? $ROADMAPS['Software Engineer'];

// Load progress from DB
$progressResult = $conn->query("SELECT phase_index, is_complete FROM career_roadmap_progress
                                 WHERE user_id=$userId AND role='$targetRole'");
$progressMap = [];
while ($r = $progressResult->fetch_assoc()) $progressMap[$r['phase_index']] = $r['is_complete'];

$totalPhases   = count($roadmap);
$donePhases    = array_sum($progressMap);
$roadmapScore  = $totalPhases > 0 ? round(($donePhases / $totalPhases) * 100) : 0;
$currentPhase  = $donePhases < $totalPhases ? $donePhases : $totalPhases - 1;

$allRoles = array_keys($ROADMAPS);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Career Roadmap – SkillSync</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .phase-card {
      background: #fff; border: 1.5px solid #e2e8f0;
      border-radius: 14px; padding: 22px 24px;
      margin-bottom: 16px; transition: .2s;
      position: relative;
    }
    .phase-card.done    { border-color: #10b981; background: #f0fdf4; }
    .phase-card.active  { border-color: #4f46e5; box-shadow: 0 4px 20px rgba(79,70,229,.12); }
    .phase-card.locked  { opacity: .65; }
    .phase-header       { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .phase-num          { width: 32px; height: 32px; border-radius: 50%; background: #e0e7ff;
                          color: #4f46e5; font-weight: 700; font-size: 14px;
                          display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .phase-num.done-num { background: #10b981; color: #fff; }
    .phase-num.active-num{background: #4f46e5; color: #fff; }
    .phase-title        { font-size: 16px; font-weight: 600; margin-left: 12px; }
    .phase-duration     { font-size: 12px; color: #94a3b8; background: #f1f5f9; padding: 3px 10px; border-radius: 99px; }
    .skill-pill         { display: inline-block; background: #eef2ff; color: #4338ca;
                          padding: 3px 10px; border-radius: 99px; font-size: 12px; margin: 3px; }
    .resource-pill      { display: inline-block; background: #f1f5f9; color: #475569;
                          padding: 3px 10px; border-radius: 99px; font-size: 12px; margin: 3px; }
    .connector          { width: 2px; height: 16px; background: #e2e8f0; margin: 0 auto 0 23px; }
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
      <a href="career_roadmap.php" class="active"><span class="icon">🗺️</span><span>Career Roadmap</span></a>
      <a href="job_matching.php"><span class="icon">🎯</span><span>Job Matching</span></a>
      <a href="profile.php"><span class="icon">👤</span><span>Profile</span></a>
      <a href="logout.php"><span class="icon">🚪</span><span>Logout</span></a>
    </nav>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">🗺️ Personalized Career Roadmap</div>
      <div class="topbar-user">
        <span><?= htmlspecialchars($user['full_name']) ?></span>
        <div class="avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      </div>
    </div>

    <div class="content-area">

      <!-- Role Switcher -->
      <div class="card" style="padding:16px 24px">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <span style="font-size:14px;font-weight:600;color:#64748b">Switch Role:</span>
          <?php foreach ($allRoles as $r): ?>
            <a href="career_roadmap.php?role=<?= urlencode($r) ?>"
               class="btn btn-sm <?= $r===$targetRole?'btn-primary':'btn-outline' ?>">
              <?= $r ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">

        <!-- Roadmap Steps -->
        <div>
          <div class="card" style="margin-bottom:0">
            <div class="card-title">
              🗺️ <?= htmlspecialchars($targetRole) ?> Roadmap
              <span class="badge badge-blue" style="margin-left:8px"><?= $totalPhases ?> Phases</span>
            </div>
            <div class="card-subtitle">Mark phases complete as you finish them to track your progress.</div>
          </div>

          <?php foreach ($roadmap as $idx => $phase):
            $isDone   = isset($progressMap[$idx]) && $progressMap[$idx];
            $isActive = !$isDone && $idx === $currentPhase;
            $isLocked = !$isDone && $idx > $currentPhase;
            $stateClass = $isDone ? 'done' : ($isActive ? 'active' : 'locked');
            $numClass   = $isDone ? 'done-num' : ($isActive ? 'active-num' : '');
          ?>
            <?php if ($idx > 0): ?><div class="connector"></div><?php endif; ?>
            <div class="phase-card <?= $stateClass ?>">
              <div class="phase-header">
                <div style="display:flex;align-items:center;gap:0">
                  <div class="phase-num <?= $numClass ?>">
                    <?= $isDone ? '✓' : ($idx + 1) ?>
                  </div>
                  <div class="phase-title"><?= htmlspecialchars($phase['phase']) ?></div>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                  <span class="phase-duration"><?= $phase['duration'] ?></span>
                  <?php if ($isDone): ?>
                    <span class="badge badge-green">✅ Completed</span>
                  <?php elseif ($isActive): ?>
                    <span class="badge badge-blue">▶ In Progress</span>
                  <?php else: ?>
                    <span class="badge" style="background:#f1f5f9;color:#94a3b8">🔒 Locked</span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Skills to learn -->
              <div style="margin-bottom:10px">
                <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px">SKILLS</div>
                <?php foreach ($phase['skills'] as $sk): ?>
                  <span class="skill-pill"><?= htmlspecialchars($sk) ?></span>
                <?php endforeach; ?>
              </div>

              <!-- Resources -->
              <div style="margin-bottom:12px">
                <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px">RESOURCES</div>
                <?php foreach ($phase['resources'] as $rs): ?>
                  <span class="resource-pill">📚 <?= htmlspecialchars($rs) ?></span>
                <?php endforeach; ?>
              </div>

              <!-- Outcome -->
              <div style="background:#f8fafc;border-radius:8px;padding:10px 14px;font-size:13px;color:#475569;margin-bottom:14px">
                🎯 <strong>Milestone:</strong> <?= htmlspecialchars($phase['outcome']) ?>
              </div>

              <!-- Toggle button -->
              <form method="POST" action="career_roadmap.php" style="display:inline">
                <input type="hidden" name="toggle_phase" value="1">
                <input type="hidden" name="role" value="<?= htmlspecialchars($targetRole) ?>">
                <input type="hidden" name="phase_index" value="<?= $idx ?>">
                <input type="hidden" name="is_complete" value="<?= $isDone ? 0 : 1 ?>">
                <button type="submit" class="btn btn-sm <?= $isDone ? 'btn-outline' : 'btn-success' ?>">
                  <?= $isDone ? '↩ Mark Incomplete' : '✅ Mark as Complete' ?>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Right Panel -->
        <div>
          <!-- Overall Progress -->
          <div class="card">
            <div class="card-title">📊 Roadmap Progress</div>
            <div class="score-ring-wrap" style="margin-bottom:16px">
              <div class="score-ring" data-score="<?= $roadmapScore ?>" style="--pct:<?= $roadmapScore ?>">
                <div class="score-ring-val"><?= $roadmapScore ?></div>
              </div>
              <div class="score-label">Completion</div>
            </div>
            <div style="font-size:14px;text-align:center;color:#64748b">
              <?= $donePhases ?> of <?= $totalPhases ?> phases completed
            </div>
            <div class="progress-bar-wrap" style="margin-top:14px">
              <div class="progress-bar-track">
                <div class="progress-bar-fill" data-pct="<?= $roadmapScore ?>"></div>
              </div>
            </div>
          </div>

          <!-- Estimated Completion -->
          <div class="card">
            <div class="card-title">📅 Timeline</div>
            <?php
              $remaining = $totalPhases - $donePhases;
              $weeks = $remaining * 4; // ~4 weeks per phase
            ?>
            <div style="font-size:13px;color:#64748b;line-height:1.8">
              <div>✅ Completed: <strong><?= $donePhases ?> phases</strong></div>
              <div>⏳ Remaining: <strong><?= $remaining ?> phases</strong></div>
              <div>📆 Est. time: <strong>~<?= $weeks ?> weeks</strong></div>
              <div>🎯 Target: <strong><?= htmlspecialchars($targetRole) ?></strong></div>
            </div>
          </div>

          <!-- Quick tips -->
          <div class="card">
            <div class="card-title">💡 Study Tips</div>
            <div style="font-size:13px;color:#64748b;line-height:2">
              📌 Dedicate 2–3 hours daily<br>
              🔨 Build a project per phase<br>
              📝 Keep notes in Notion/OneNote<br>
              🤝 Join Discord study groups<br>
              💻 Push all code to GitHub<br>
              🔄 Review previous phase weekly
            </div>
          </div>
        </div>

      </div><!-- grid -->
    </div><!-- content-area -->
  </div>
</div>
<script src="script.js"></script>
</body>
</html>