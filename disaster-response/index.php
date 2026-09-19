<?php
require 'config.php';
$stats = [
  'Active Disasters' => $pdo->query("SELECT COUNT(*) FROM disaster WHERE status='active'")->fetchColumn(),
  'Open Requests'    => $pdo->query("SELECT COUNT(*) FROM emergency_request WHERE status<>'completed'")->fetchColumn(),
  'Free Shelter Beds'=> $pdo->query("SELECT COALESCE(SUM(capacity-occupied),0) FROM shelter")->fetchColumn(),
  'Volunteers Available' => $pdo->query("SELECT COUNT(*) FROM volunteers WHERE availability='available'")->fetchColumn(),
];
$shelters = $pdo->query("SELECT * FROM v_shelter_availability ORDER BY free_beds DESC")->fetchAll();
page_header('Home'); ?>
<div class="hero p-5 mb-4 text-center">
  <h1 class="display-5 fw-bold">Disaster Response Coordination</h1>
  <p class="lead">Report emergencies, coordinate rescue teams, track shelters and relief supplies.</p>
  <?php if (empty($_SESSION['user'])): ?>
    <a href="register.php" class="btn btn-light btn-lg me-2">Register</a>
    <a href="login.php" class="btn btn-outline-light btn-lg">Login</a>
  <?php else: ?><a href="dashboard.php" class="btn btn-light btn-lg">Go to Dashboard</a><?php endif; ?>
</div>
<div class="row g-3 mb-4">
<?php foreach ($stats as $label => $val): ?>
  <div class="col-6 col-md-3"><div class="card stat-card text-center p-3">
    <h2><?= e($val) ?></h2><div class="text-muted"><?= e($label) ?></div></div></div>
<?php endforeach; ?>
</div>
<div class="card"><div class="card-header fw-bold">Shelter Availability</div>
<div class="table-responsive"><table class="table mb-0">
<tr><th>Shelter</th><th>Disaster</th><th>Location</th><th>Capacity</th><th>Free beds</th></tr>
<?php foreach ($shelters as $s): ?>
<tr><td><?= e($s['name']) ?></td><td><?= e($s['disaster']) ?></td><td><?= e($s['location']) ?></td>
<td><?= e($s['capacity']) ?></td><td><?= e($s['free_beds']) ?></td></tr>
<?php endforeach; ?></table></div></div>
<?php page_footer();
