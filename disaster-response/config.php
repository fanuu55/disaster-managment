<?php
// Configuration, DB connection and shared helpers
session_start();

$DB_HOST = 'localhost';
$DB_NAME = 'disaster_response';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function require_login(array $roles = []) {
    if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
    if ($roles && !in_array($_SESSION['user']['role'], $roles)) {
        http_response_code(403); die('Access denied.');
    }
}

function badge($status) {
    $map = ['pending'=>'warning','assigned'=>'info','accepted'=>'info','in_progress'=>'primary',
            'completed'=>'success','high'=>'danger','medium'=>'warning','low'=>'secondary'];
    return '<span class="badge bg-' . ($map[$status] ?? 'secondary') . '">' . e(str_replace('_',' ',$status)) . '</span>';
}

function page_header($title) {
    $u = $_SESSION['user'] ?? null; ?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Disaster Response</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="style.css" rel="stylesheet">
</head><body>
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
 <div class="container">
  <a class="navbar-brand fw-bold" href="index.php">🚨 Disaster Response</a>
  <div class="navbar-nav ms-auto flex-row gap-3">
   <?php if ($u): ?>
     <a class="nav-link" href="dashboard.php">Dashboard</a>
     <?php if ($u['role']==='citizen'): ?>
       <a class="nav-link" href="report.php">Report</a><a class="nav-link" href="my_requests.php">My Requests</a>
     <?php elseif ($u['role']==='admin'): ?>
       <a class="nav-link" href="admin_requests.php">Requests</a>
     <?php elseif ($u['role']==='volunteer'): ?><a class="nav-link" href="volunteer.php">My Tasks</a>
     <?php elseif ($u['role']==='team'): ?><a class="nav-link" href="team.php">Assignments</a>
     <?php endif; ?>
     <span class="nav-link text-white-50"><?= e($u['name']) ?> (<?= e($u['role']) ?>)</span>
     <a class="nav-link" href="logout.php">Logout</a>
   <?php else: ?>
     <a class="nav-link" href="login.php">Login</a><a class="nav-link" href="register.php">Register</a>
   <?php endif; ?>
  </div>
 </div>
</nav>
<main class="container my-4">
<?php }

function page_footer() { ?>
</main>
<footer class="text-center text-muted py-3 small">Disaster Response Coordination System &middot; DBMS Mini Project</footer>
</body></html>
<?php }
