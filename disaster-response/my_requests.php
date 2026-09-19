<?php
require 'config.php';
require_login(['citizen']);
$st = $pdo->prepare("SELECT * FROM v_request_summary WHERE request_id IN
      (SELECT request_id FROM emergency_request WHERE user_id=?) ORDER BY created_at DESC");
$st->execute([$_SESSION['user']['id']]);
$rows = $st->fetchAll();

// status history per request (Status_Log)
$log = $pdo->prepare("SELECT l.* FROM status_log l JOIN emergency_request r USING(request_id)
                      WHERE r.user_id=? ORDER BY l.changed_at");
$log->execute([$_SESSION['user']['id']]);
$history = [];
foreach ($log->fetchAll() as $l) $history[$l['request_id']][] = $l;

page_header('My Requests'); ?>
<h3>My Requests</h3>
<?php if (isset($_GET['ok'])): ?><div class="alert alert-success">Request submitted. Help is on the way.</div><?php endif; ?>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<tr><th>#</th><th>Type</th><th>Location</th><th>Priority</th><th>Status</th><th>Assigned to</th><th>Submitted</th><th>History</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= $r['request_id'] ?></td><td><?= e($r['request_type']) ?></td><td><?= e($r['location']) ?></td>
<td><?= badge($r['priority']) ?></td><td><?= badge($r['status']) ?></td>
<td><?= e($r['team_name'] ?: $r['volunteer'] ?: '—') ?></td><td><?= e($r['created_at']) ?></td>
<td class="small"><?php foreach ($history[$r['request_id']] ?? [] as $h) echo e($h['old_status'].' → '.$h['new_status']).' <span class="text-muted">('.e($h['changed_at']).')</span><br>'; ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="text-center text-muted">No requests yet.</td></tr>'; ?>
</table></div></div>
<?php page_footer();
