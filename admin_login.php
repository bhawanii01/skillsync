<?php
require_once 'config.php';

// Redirect if already logged in as admin
if (isset($_SESSION['admin_id'])) {
    redirect('admin_dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password_hash FROM admin_users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['admin_id']   = $row['id'];
                $_SESSION['admin_name'] = $row['full_name'];
                redirect('admin_dashboard.php');
            } else {
                $error = 'Incorrect password. Please try again.';
            }
        } else {
            $error = 'No administrator account found with that username.';
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
  <title>Admin Login – SkillSync</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-wrap" style="background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);">
  <div class="auth-card">
    <div class="auth-logo" style="color: #64748b">Skill<span>Sync</span> <span class="badge badge-yellow" style="font-size:10px; vertical-align:middle; margin-left:5px">ADMIN</span></div>
    <div class="auth-tagline">Portal Management & Control Panel</div>

    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="admin_login.php">
      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input type="text" id="username" name="username" class="form-control"
               placeholder="admin" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Enter admin password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="background: #334155; margin-top:8px">
        🔑 Authenticate Admin
      </button>
    </form>

    <p style="text-align:center;margin-top:20px;font-size:14px;color:#64748b">
      <a href="login.php">← Back to Student Login</a>
    </p>
  </div>
</div>
<script src="script.js"></script>
</body>
</html>
