<?php
require_once 'config.php';
if (isLoggedIn()) redirect('dashboard.php');

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = clean($conn, $_POST['full_name'] ?? '');
    $email    = clean($conn, $_POST['email'] ?? '');
    $roll     = clean($conn, $_POST['roll_no'] ?? '');
    $branch   = clean($conn, $_POST['branch'] ?? 'CSE');
    $sem      = (int)($_POST['semester'] ?? 3);
    $role     = clean($conn, $_POST['target_role'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check duplicate email
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $chk->bind_param('s', $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins = $conn->prepare("INSERT INTO users (full_name, email, password_hash, roll_no, branch, semester, target_role) VALUES (?,?,?,?,?,?,?)");
            $ins->bind_param('sssssss', $name, $email, $hash, $roll, $branch, $sem, $role);
            if ($ins->execute()) {
                $success = 'Account created successfully! You can now <a href="login.php">log in</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – SkillSync</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card" style="max-width:520px">
    <div class="auth-logo">Skill<span>Sync</span></div>
    <div class="auth-tagline">Create your career readiness profile</div>

    <?php if ($error):  ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
    <?php if ($success):?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="register.php" onsubmit="return validateRegistrationForm()">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input id="reg_name" type="text" name="full_name" class="form-control" placeholder="Arjun Sharma" required>
        </div>
        <div class="form-group">
          <label class="form-label">Roll Number</label>
          <input type="text" name="roll_no" class="form-control" placeholder="2215001">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address *</label>
        <input id="reg_email" type="email" name="email" class="form-control" placeholder="arjun@example.com" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Branch</label>
          <select name="branch" class="form-control">
            <option value="CSE">CSE</option>
            <option value="IT">IT</option>
            <option value="ECE">ECE</option>
            <option value="ME">ME</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Semester</label>
          <select name="semester" class="form-control">
            <?php for($i=1;$i<=8;$i++): ?>
            <option value="<?=$i?>" <?=$i==3?'selected':''?>><?=$i?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Target Career Role</label>
        <select name="target_role" class="form-control">
          <option value="">-- Select Target Role --</option>
          <option>Web Developer</option>
          <option>Software Engineer</option>
          <option>Data Analyst</option>
          <option>Full Stack Developer</option>
          <option>Cloud Engineer</option>
          <option>UI/UX Designer</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input id="reg_password" type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password *</label>
          <input id="reg_confirm" type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">✅ Create My Account</button>
    </form>
    <?php endif; ?>

    <div class="auth-divider">OR</div>
    <p style="text-align:center;font-size:14px;color:#64748b">
      Already have an account? <a href="login.php">Login here →</a>
    </p>
  </div>
</div>
<script src="script.js"></script>
</body>
</html>