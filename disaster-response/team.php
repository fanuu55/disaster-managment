<?php
require 'config.php';
require_login(['team']);
$st = $pdo->prepare("SELECT * FROM rescue_team WHERE user_id=?");
$st->execute([$_SESSION['user']['id']]);
$team = $st->fetch();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['action'] === 'complete' ? 'completed' : 'in_progress';
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE assignment SET status=?, completed_at=IF(?='completed',NOW(),NULL)
                       WHERE assignment_id=? AND team_id=? AND status<>'completed'")
            ->execute([$new, $new, $_POST['id'], $team['team_id']]);
        $pdo->prepare("UPDATE emergency_request SET status=? WHERE request_id=(SELECT request_id FROM assignment WHERE assignment_id=?)")
            ->execute([$new, $_POST['id']]);
        if ($new === 'completed') {
            // free the team only when it has no other unfinished assignment
            $pdo->prepare("UPDATE rescue_team SET status='available' WHERE team_id=? AND NOT EXISTS
                (SELECT 1 FROM assignment WHERE team_id=? AND status<>'completed')")->execute([$team['team_id'], $team['team_id']]);
        }
        $pdo->commit(); $msg = 'Progress updated.';
    } catch (PDOException $ex) { $pdo->rollBack(); $msg = 'Update failed.'; }
}

$tasks = $pdo->prepare("SELECT a.*, r.request_type, r.location, r.description, r.priority
    FROM assignment a JOIN emergency_request r USING(request_id)
    WHERE a.team_id=? ORDER BY a.status='completed', a.assigned_at DESC");
$tasks->execute([$team['team_id']]); $tasks = $tasks->fetchAll();

page_header('Rescue Team'); ?>
<h3><?= e($team['team_name']) ?> <small class="text-muted fs-6">(<?= (int)$team['members'] ?> members &middot; <?= e($team['status']) ?>)</small></h3>
<?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<tr><th>Request</th><th>Type</th><th>Location</th><th>Details</th><th>Priority</th><th>Status</th><th>Update</th></tr>
<?php foreach ($tasks as $t): ?>
<tr><td>#<?= $t['request_id'] ?></td><td><?= e($t['request_type']) ?></td><td><?= e($t['location']) ?></td>
<td class="small"><?= e($t['description']) ?></td><td><?= badge($t['priority']) ?></td><td><?= badge($t['status']) ?></td>
<td><?php if ($t['status'] !== 'completed'): ?><form method="post" class="d-flex gap-1"><input type="hidden" name="id" value="<?= $t['assignment_id'] ?>">
 <?php if ($t['status'] !== 'in_progress'): ?><button name="action" value="progress" class="btn btn-sm btn-warning">Start rescue</button><?php endif; ?>
 <button name="action" value="complete" class="btn btn-sm btn-success">Mark completed</button></form>
<?php else: echo 'Done'; endif; ?></td></tr>
<?php endforeach; if (!$tasks) echo '<tr><td colspan="7" class="text-center text-muted">No assignments yet.</td></tr>'; ?>
</table></div></div>
<?php page_footer();
