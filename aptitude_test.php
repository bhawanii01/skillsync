<?php
require_once 'config.php';
requireLogin();
$userId = $_SESSION['user_id'];
$user   = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_submit'])) {
    $answers   = $_POST['answers'] ?? [];
    $qids      = $_POST['qids'] ?? [];
    $timeTaken = (int)($_POST['time_taken'] ?? 0);
    $correct   = $wrong = $skipped = 0;

    foreach ($qids as $qid) {
        $qid = (int)$qid;
        $row = $conn->query("SELECT correct_ans, marks FROM aptitude_questions WHERE id=$qid")->fetch_assoc();
        if (!$row) continue;
        if (!isset($answers[$qid]) || $answers[$qid] === '') {
            $skipped++;
        } elseif ($answers[$qid] === $row['correct_ans']) {
            $correct += $row['marks'];
        } else {
            $wrong++;
        }
    }
    $total      = count($qids);
    $scoreVal   = $correct;
    $percentage = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

    // Get attempt number
    $prevAttempts = $conn->query("SELECT COUNT(*) as c FROM aptitude_attempts WHERE user_id=$userId")->fetch_assoc()['c'];
    $attemptNo = $prevAttempts + 1;

    $stmt = $conn->prepare("INSERT INTO aptitude_attempts (user_id, attempt_no, total_q, correct, wrong, skipped, score, percentage, time_taken) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('iiiiiiddi', $userId, $attemptNo, $total, $correct, $wrong, $skipped, $scoreVal, $percentage, $timeTaken);
    $stmt->execute();

    computeOverallScore($conn, $userId);

    // Redirect to results page
    header("Location: aptitude_test.php?result=1&correct=$correct&wrong=$wrong&skipped=$skipped&total=$total&pct=$percentage&time=$timeTaken");
    exit;
}

// Show results if redirected after submit
$showResult = isset($_GET['result']);

// Fetch 20 random questions
$questions = [];
if (!$showResult) {
    $res = $conn->query("SELECT * FROM aptitude_questions ORDER BY RAND() LIMIT 20");
    while ($r = $res->fetch_assoc()) $questions[] = $r;
}

// All past attempts for history
$attemptsRes = $conn->query("SELECT * FROM aptitude_attempts WHERE user_id=$userId ORDER BY attempted_at DESC LIMIT 5");
$attempts = [];
while ($r = $attemptsRes->fetch_assoc()) $attempts[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aptitude Test – SkillSync</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page-wrap">
  <aside class="sidebar">
    <div class="sidebar-logo">Skill<span>Sync</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="icon">🏠</span><span>Dashboard</span></a>
      <a href="skill_analysis.php"><span class="icon">🧠</span><span>Skill Analysis</span></a>
      <a href="aptitude_test.php" class="active"><span class="icon">📝</span><span>Aptitude Test</span></a>
      <a href="resume_upload.php"><span class="icon">📄</span><span>Resume Check</span></a>
      <a href="career_roadmap.php"><span class="icon">🗺️</span><span>Career Roadmap</span></a>
      <a href="job_matching.php"><span class="icon">🎯</span><span>Job Matching</span></a>
      <a href="profile.php"><span class="icon">👤</span><span>Profile</span></a>
      <a href="logout.php"><span class="icon">🚪</span><span>Logout</span></a>
    </nav>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">📝 Aptitude Assessment</div>
      <div class="topbar-user">
        <span><?= htmlspecialchars($user['full_name']) ?></span>
        <div class="avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      </div>
    </div>

    <div class="content-area">

      <?php if ($showResult): ?>
        <!-- Results Card -->
        <?php
          $correct = (int)$_GET['correct']; $wrong = (int)$_GET['wrong'];
          $skipped = (int)$_GET['skipped']; $total = (int)$_GET['total'];
          $pct = (float)$_GET['pct']; $mins = floor((int)$_GET['time'] / 60);
          $grade = $pct >= 80 ? ['Excellent','badge-green'] : ($pct >= 60 ? ['Good','badge-blue'] : ($pct >= 40 ? ['Average','badge-yellow'] : ['Needs Improvement','badge-red']));
        ?>
        <div class="card" style="max-width:600px;margin:0 auto;text-align:center">
          <div style="font-size:48px;margin-bottom:12px"><?= $pct >= 60 ? '🎉' : '📚' ?></div>
          <h2 style="font-size:22px;font-weight:700;margin-bottom:6px">Test Completed!</h2>
          <div style="font-size:48px;font-weight:800;color:<?= $pct>=60?'#10b981':'#f59e0b' ?>;margin:16px 0">
            <?= round($pct) ?>%
          </div>
          <span class="badge <?= $grade[1] ?>" style="font-size:14px;padding:6px 16px"><?= $grade[0] ?></span>

          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:24px 0;text-align:left">
            <div style="background:#f0fdf4;border-radius:10px;padding:14px;text-align:center">
              <div style="font-size:26px;font-weight:700;color:#10b981"><?= $correct ?></div>
              <div style="font-size:12px;color:#64748b">Correct</div>
            </div>
            <div style="background:#fef2f2;border-radius:10px;padding:14px;text-align:center">
              <div style="font-size:26px;font-weight:700;color:#ef4444"><?= $wrong ?></div>
              <div style="font-size:12px;color:#64748b">Wrong</div>
            </div>
            <div style="background:#f8fafc;border-radius:10px;padding:14px;text-align:center">
              <div style="font-size:26px;font-weight:700;color:#94a3b8"><?= $skipped ?></div>
              <div style="font-size:12px;color:#64748b">Skipped</div>
            </div>
          </div>
          <div style="font-size:14px;color:#64748b;margin-bottom:20px">
            ⏱️ Time taken: <?= $mins ?> min <?= (int)$_GET['time'] % 60 ?> sec
          </div>
          <div style="display:flex;gap:12px;justify-content:center">
            <a href="aptitude_test.php" class="btn btn-primary">🔄 Retake Test</a>
            <a href="dashboard.php" class="btn btn-outline">🏠 Dashboard</a>
          </div>
        </div>

      <?php else: ?>
        <!-- Instructions + Test Form -->
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
          <div>
            <div class="card">
              <div class="card-title">📋 Test Instructions</div>
              <ul style="font-size:14px;color:#64748b;line-height:1.9;padding-left:18px">
                <li>This test contains <strong>20 questions</strong> across 4 sections</li>
                <li>Total time: <strong>30 minutes</strong> (auto-submits on timeout)</li>
                <li>Each correct answer: <strong>+1 mark</strong>. No negative marking</li>
                <li>Sections: Quantitative, Logical Reasoning, Verbal, Technical</li>
                <li>You can attempt the test multiple times to improve your score</li>
              </ul>
              <button onclick="startTest()" class="btn btn-primary" style="margin-top:16px">
                🚀 Start Test Now
              </button>
            </div>

            <!-- Quiz Form (hidden initially) -->
            <div id="quiz-section" style="display:none">
              <div class="card" style="position:sticky;top:72px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                  <div class="quiz-progress" id="q-progress">Question 1 of 20</div>
                  <div class="quiz-timer" id="quiz-timer">30:00</div>
                </div>
                <div class="progress-bar-track" style="margin-bottom:20px">
                  <div class="progress-bar-fill" id="q-progress-bar" style="width:5%"></div>
                </div>
              </div>

              <form method="POST" action="aptitude_test.php" id="quiz-form">
                <input type="hidden" name="quiz_submit" value="1">
                <input type="hidden" name="time_taken" id="time_taken_input" value="0">
                <?php foreach ($questions as $i => $q): ?>
                  <input type="hidden" name="qids[]" value="<?= $q['id'] ?>">
                  <div class="card quiz-card" id="qcard-<?= $i ?>" style="<?= $i>0?'display:none':'' ?>">
                    <div style="display:flex;justify-content:space-between;margin-bottom:12px">
                      <span class="badge badge-blue"><?= $q['category'] ?></span>
                      <span class="badge badge-yellow"><?= $q['difficulty'] ?></span>
                    </div>
                    <div class="quiz-question"><?= $i+1 ?>. <?= htmlspecialchars($q['question_text']) ?></div>
                    <div class="quiz-options">
                      <?php foreach (['A','B','C','D'] as $opt): ?>
                        <label>
                          <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt ?>">
                          <strong><?= $opt ?>.</strong> <?= htmlspecialchars($q['option_' . strtolower($opt)]) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:16px">
                      <?php if ($i > 0): ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="navigateQ(<?=$i-1?>)">← Previous</button>
                      <?php else: ?><div></div><?php endif; ?>
                      <?php if ($i < count($questions)-1): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="navigateQ(<?=$i+1?>)">Next →</button>
                      <?php else: ?>
                        <button type="submit" class="btn btn-success btn-sm">✅ Submit Test</button>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </form>
            </div>
          </div>

          <!-- Past Attempts -->
          <div>
            <div class="card">
              <div class="card-title">📈 Past Attempts</div>
              <?php if ($attempts): ?>
                <?php foreach ($attempts as $a): ?>
                  <div style="border-bottom:1px solid #e2e8f0;padding:10px 0">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                      <span style="font-size:13px;color:#64748b">Attempt #<?= $a['attempt_no'] ?></span>
                      <span class="badge <?= $a['percentage']>=60?'badge-green':'badge-yellow' ?>">
                        <?= round($a['percentage']) ?>%
                      </span>
                    </div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:2px">
                      ✅ <?= $a['correct'] ?> / <?= $a['total_q'] ?> &nbsp;·&nbsp;
                      <?= date('d M Y', strtotime($a['attempted_at'])) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p style="font-size:13px;color:#94a3b8">No attempts yet. Take your first test!</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="script.js"></script>
<script>
let currentQ = 0, totalQ = <?= count($questions) ?>, startTime;

function startTest() {
  document.querySelector('.card').style.display = 'none';
  document.getElementById('quiz-section').style.display = 'block';
  startTime = Date.now();
  startQuizTimer('quiz-timer', 1800);
}

function navigateQ(idx) {
  document.getElementById('qcard-' + currentQ).style.display = 'none';
  document.getElementById('qcard-' + idx).style.display = 'block';
  currentQ = idx;
  document.getElementById('q-progress').textContent = 'Question ' + (idx+1) + ' of ' + totalQ;
  document.getElementById('q-progress-bar').style.width = ((idx+1)/totalQ*100) + '%';
}

document.getElementById('quiz-form')?.addEventListener('submit', function() {
  const elapsed = Math.round((Date.now() - startTime) / 1000);
  document.getElementById('time_taken_input').value = elapsed;
});
</script>
</body>
</html>