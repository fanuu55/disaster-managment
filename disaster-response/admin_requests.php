<?php
require 'config.php';
require_login(['admin']);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    $team = $_POST['team_id'] ?: null;
    $vol  = $_POST['volunteer_id'] ?: null;
    if (!$team && !$vol) { $msg = 'Select a rescue team or a volunteer.'; }
    else {
        try {
            $stmt = $pdo->prepare("CALL assign_request(?,?,?)");   // stored procedure with transaction
            $stmt->execute([$_POST['request_id'], $team, $vol]);
            $stmt->closeCursor();
            $msg = 'Request assigned.';
        } catch (PDOException $ex) { $msg = 'Error: ' . $ex->getMessage(); }
    }
}

// Search and filter
$where = []; $args = [];
if (!empty($_GET['status'])) { $where[] = 'status = ?'; $args[] = $_GET['status']; }
if (!empty($_GET['type']))   { $where[] = 'request_type = ?'; $args[] = $_GET['type']; }
if (!empty($_GET['q']))      { $where[] = '(location LIKE ? OR citizen LIKE ?)'; $args[] = '%'.$_GET['q'].'%'; $args[] = '%'.$_GET['q'].'%'; }
$sql = "SELECT * FROM v_request_summary" . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY FIELD(priority,'high','medium','low'), created_at DESC";
$st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();

$teams = $pdo->query("SELECT team_id, team_name, status FROM rescue_team")->fetchAll();
$vols  = $pdo->query("SELECT v.volunteer_id, u.name, v.availability FROM volunteers v JOIN users u USING(user_id) WHERE v.availability='available'")->fetchAll();

page_header('Requests'); ?>
<h3>Emergency Requests</h3>
<?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>
<form class="row g-2 mb-3" method="get">
 <div class="col-md-3"><input name="q" value="<?= e($_GET['q'] ?? '') ?>" class="form-control" placeholder="Search location / citizen"></div>
 <div class="col-md-2"><select name="status" class="form-select"><option value="">All status</option>
  <?php foreach (['pending','assigned','in_progress','completed'] as $s) echo '<option '.(($_GET['status'] ?? '')===$s?'selected':'').'>'.$s.'</option>'; ?></select></div>
 <div class="col-md-2"><select name="type" class="form-select"><option value="">All types</option>
  <?php foreach (['rescue','food','water','medicine','other'] as $s) echo '<option '.(($_GET['type'] ?? '')===$s?'selected':'').'>'.$s.'</option>'; ?></select></div>
 <div class="col-md-2"><button class="btn btn-secondary w-100">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<tr><th>#</th><th>Citizen</th><th>Type</th><th>Location</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Assign</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= $r['request_id'] ?></td><td><?= e($r['citizen']) ?></td><td><?= e($r['request_type']) ?></td><td><?= e($r['location']) ?></td>
<td><?= badge($r['priority']) ?></td><td><?= badge($r['status']) ?></td><td><?= e($r['team_name'] ?: $r['volunteer'] ?: '—') ?></td>
<td><?php if ($r['status'] === 'pending'): ?>
 <form method="post" class="d-flex gap-1"><input type="hidden" name="action" value="assign"><input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
  <select name="team_id" class="form-select form-select-sm"><option value="">Team…</option>
   <?php foreach ($teams as $t) echo '<option value="'.$t['team_id'].'">'.e($t['team_name']).' ('.$t['status'].')</option>'; ?></select>
  <select name="volunteer_id" class="form-select form-select-sm"><option value="">Volunteer…</option>
   <?php foreach ($vols as $v) echo '<option value="'.$v['volunteer_id'].'">'.e($v['name']).'</option>'; ?></select>
  <button class="btn btn-sm btn-danger">Assign</button></form>
<?php else: echo '—'; endif; ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="text-center text-muted">No requests found.</td></tr>'; ?>
</table></div></div>
<?php page_footer();
