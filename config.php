<?php
// =====================================================
// SkillSync – config.php  |  Database Configuration
// =====================================================

define('DB_HOST',     getenv('DB_HOST') ?: 'localhost');
define('DB_USER',     getenv('DB_USER') ?: 'root');        // Change to your MySQL username
define('DB_PASS',     getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');            // Change to your MySQL password
define('DB_NAME',     getenv('DB_NAME') ?: 'skillsync_db');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = ($host === 'localhost' || $host === '127.0.0.1') ? '/skillsync' : '';
define('BASE_URL', $protocol . "://" . $host . $path . '/');
define('UPLOAD_PATH', __DIR__ . '/uploads/resumes/');
define('SITE_NAME',   'SkillSync');

// Connect
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("<h3 style='color:red;font-family:sans-serif'>Database Connection Failed: " . $conn->connect_error . "<br>Please import database.sql and update config.php</h3>");
}
$conn->set_charset('utf8mb4');

// Session start (if not already started)
if (session_status() === PHP_SESSION_NONE) session_start();

// Helper: redirect
function redirect($url) { header("Location: $url"); exit; }

// Helper: sanitize
function clean($conn, $val) { return htmlspecialchars(strip_tags($conn->real_escape_string(trim($val)))); }

// Helper: is logged in
function isLoggedIn() { return isset($_SESSION['user_id']); }

// Helper: require login
function requireLogin() {
    if (!isLoggedIn()) redirect('login.php');
}

// Helper: require admin
function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) redirect('admin_login.php');
}

// Helper: compute overall readiness score
function computeOverallScore($conn, $userId) {
    // Skill score: count user skills vs target role
    $row = $conn->query("SELECT target_role FROM users WHERE id=$userId")->fetch_assoc();
    $role = $row['target_role'] ?? '';

    $skillCount = $conn->query("SELECT COUNT(*) as c FROM user_skills WHERE user_id=$userId")->fetch_assoc()['c'];
    $skillScore = min(100, $skillCount * 5);   // 5 pts per skill, max 100

    // Aptitude score: last attempt percentage
    $apt = $conn->query("SELECT percentage FROM aptitude_attempts WHERE user_id=$userId ORDER BY attempted_at DESC LIMIT 1")->fetch_assoc();
    $aptScore = $apt ? round($apt['percentage']) : 0;

    // Resume score
    $res = $conn->query("SELECT strength_score FROM resumes WHERE user_id=$userId ORDER BY uploaded_at DESC LIMIT 1")->fetch_assoc();
    $resumeScore = $res ? $res['strength_score'] : 0;

    // Roadmap score
    $total   = $conn->query("SELECT COUNT(*) as c FROM career_roadmap_progress WHERE user_id=$userId")->fetch_assoc()['c'];
    $done    = $conn->query("SELECT COUNT(*) as c FROM career_roadmap_progress WHERE user_id=$userId AND is_complete=1")->fetch_assoc()['c'];
    $roadmapScore = $total > 0 ? round(($done / $total) * 100) : 0;

    // Weighted overall
    $overall = round($skillScore * 0.35 + $aptScore * 0.30 + $resumeScore * 0.20 + $roadmapScore * 0.15);

    // Upsert readiness_scores
    $conn->query("INSERT INTO readiness_scores (user_id, overall_score, skill_score, aptitude_score, resume_score, roadmap_score)
                  VALUES ($userId, $overall, $skillScore, $aptScore, $resumeScore, $roadmapScore)
                  ON DUPLICATE KEY UPDATE
                  overall_score=$overall, skill_score=$skillScore, aptitude_score=$aptScore,
                  resume_score=$resumeScore, roadmap_score=$roadmapScore");
    return compact('overall','skillScore','aptScore','resumeScore','roadmapScore');
}
?>