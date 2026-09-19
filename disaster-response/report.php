<?php
require 'config.php';
require_login(['citizen']);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loc = trim($_POST['location']);
    if ($loc === '') { $msg = 'Location is required.'; }
    else {
        $pdo->prepare("INSERT INTO emergency_request(user_id,disaster_id,request_type,description,location,priority) VALUES(?,?,?,?,?,?)")
            ->execute([$_SESSION['user']['id'], $_POST['disaster_id'] ?: null, $_POST['request_type'],
                       trim($_POST['description']), $loc, $_POST['priority']]);
        header('Location: my_requests.php?ok=1'); exit;
    }
}
$disasters = $pdo->query("SELECT disaster_id,name FROM disaster WHERE status<>'closed'")->fetchAll();
page_header('Report Emergency'); ?>
<div class="row justify-content-center"><div class="col-md-7"><div class="card p-4">
<h4 class="mb-3">Report an emergency / request help</h4>
<?php if ($msg): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>
<form method="post">
 <label class="form-label">Request type</label>
 <select name="request_type" class="form-select mb-2">
  <option value="rescue">Rescue</option><option value="food">Food</option><option value="water">Water</option>
  <option value="medicine">Medicine</option><option value="other">Other</option></select>
 <label class="form-label">Related disaster</label>
 <select name="disaster_id" class="form-select mb-2"><option value="">-- Not sure --</option>
  <?php foreach ($disasters as $d) echo '<option value="'.$d['disaster_id'].'">'.e($d['name']).'</option>'; ?></select>
 <label class="form-label">Your location / address</label>
 <input name="location" class="form-control mb-2" required>
 <label class="form-label">Priority</label>
 <select name="priority" class="form-select mb-2"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High (life-threatening)</option></select>
 <label class="form-label">Details</label>
 <textarea name="description" rows="3" class="form-control mb-3" placeholder="People affected, injuries, landmarks..."></textarea>
 <button class="btn btn-danger w-100">Submit request</button>
</form></div></div></div>
<?php page_footer();
