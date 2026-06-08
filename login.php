<?php
require_once 'config.php';
if (isLoggedIn()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password_hash FROM users WHERE email = ? AND is_active = 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['user_name'] = $row['full_name'];
                redirect('dashboard.php');
            } else {
                $error = 'Incorrect password. Please try again.';
            }
        } else {
            $error = 'No account found with that email address.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – SkillSync</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">Skill<span>Sync</span></div>
    <div class="auth-tagline">AI-Assisted Placement & Career Readiness Portal</div>

    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="student@example.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Enter your password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">
        🔐 Login to SkillSync
      </button>
    </form>

    <div class="auth-divider">OR</div>
    <p style="text-align:center;font-size:14px;color:#64748b">
      Don't have an account? <a href="register.php">Register here →</a>
    </p>
    <p style="text-align:center;margin-top:12px;font-size:13px">
      <a href="admin_login.php" style="color:#94a3b8">Admin Login</a>
    </p>
  </div>
</div>
<script src="script.js"></script>
</body>
</html>