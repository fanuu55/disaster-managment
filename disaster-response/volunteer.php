<?php
require 'config.php';
require_login(['volunteer']);
$st = $pdo->prepare("SELECT * FROM volunteers WHERE user_id=?");
$st->execute([$_SESSION['user']['id']]);
$vol = $st->fetch();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'availability' && in_array($_POST['availability'], ['available','busy','offline'])) {
        $pdo->prepare("UPDATE volunteers SET availability=? WHERE volunteer_id=?")->execute([$_POST['availability'], $vol['volunteer_id']]);
        $vol['availability'] = $_POST['availability']; $msg = 'Availability updated.';
    } elseif ($_POST['action'] === 'accept') {
        $pdo->prepare("UPDATE assignment SET status='accepted' WHERE assignment_id=? AND volunteer_id=? AND status='assigned'")
            ->execute([$_POST['id'], $vol['volunteer_id']]);
        $msg = 'Task accepted.';
    } elseif ($_POST['action'] === 'progress' || $_POST['action'] === 'complete') {
        $new = $_POST['action'] === 'complete' ? 'completed' : 'in_progress';
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE assignment SET status=?, completed_at=IF(?='completed',NOW(),NULL)
                           WHERE assignment_id=? AND volunteer_id=? AND status IN ('accepted','in_progress')")
                ->execute([$new, $new, $_POST['id'], $vol['volunteer_id']]);
            $pdo->prepare("UPDATE emergency_request SET status=? WHERE request_id=(SELECT request_id FROM assignment WHERE assignment_id=?)")
                ->execute([$new, $_POST['id']]);
            if ($new === 'completed')
                $pdo->prepare("UPDATE volunteers SET availability='available' WHERE volunteer_id=?")->execute([$vol['volunteer_id']]);
            $pdo->commit(); $msg = 'Task updated.';
        } catch (PDOException $ex) { $pdo->rollBack(); $msg = 'Update failed.'; }
    }
}

$tasks = $pdo->prepare("SELECT a.*, r.request_type, r.location, r.description, r.priority
    FROM assignment a JOIN emergency_request r USING(request_id)
    WHERE a.volunteer_id=? ORDER BY a.assigned_at DESC");
$tasks->execute([$vol['volunteer_id']]); $tasks = $tasks->fetchAll();

page_header('Volunteer'); ?>
<h3>Volunteer Panel</h3>
<?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>
<form method="post" class="card p-3 mb-3 d-flex flex-row gap-2 align-items-center">
 <input type="hidden" name="action" value="availability"><b>My availability:</b>
 <select name="availability" class="form-select w-auto">
  <?php foreach (['available','busy','offline'] as $a) echo '<option '.($vol['availability']===$a?'selected':'').'>'.$a.'</option>'; ?></select>
 <button class="btn btn-outline-secondary">Update</button>
</form>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<tr><th>Task</th><th>Type</th><th>Location</th><th>Details</th><th>Priority</th><th>Status</th><th>Action</th></tr>
<?php foreach ($tasks as $t): ?>
<tr><td>#<?= $t['request_id'] ?></td><td><?= e($t['request_type']) ?></td><td><?= e($t['location']) ?></td>
<td class="small"><?= e($t['description']) ?></td><td><?= badge($t['priority']) ?></td><td><?= badge($t['status']) ?></td>
<td><form method="post" class="d-flex gap-1"><input type="hidden" name="id" value="<?= $t['assignment_id'] ?>">
<?php if ($t['status']==='assigned'): ?><button name="action" value="accept" class="btn btn-sm btn-primary">Accept</button>
<?php elseif ($t['status']==='accepted'): ?><button name="action" value="progress" class="btn btn-sm btn-warning">Start</button>
<?php elseif ($t['status']==='in_progress'): ?><button name="action" value="complete" class="btn btn-sm btn-success">Complete</button>
<?php else: ?>Done<?php endif; ?></form></td></tr>
<?php endforeach; if (!$tasks) echo '<tr><td colspan="7" class="text-center text-muted">No tasks assigned.</td></tr>'; ?>
</table></div></div>
<?php page_footer();
