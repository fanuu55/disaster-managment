<?php
require 'config.php';
require_login();
$u = $_SESSION['user'];
$msg = '';

// ---------- Admin actions: manage disasters, shelters, supplies, donations ----------
if ($u['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action'] ?? '') {
            case 'disaster':
                $pdo->prepare("INSERT INTO disaster(name,type,location,severity,start_date) VALUES(?,?,?,?,?)")
                    ->execute([$_POST['name'],$_POST['type'],$_POST['location'],$_POST['severity'],$_POST['start_date']]);
                $msg = 'Disaster added.'; break;
            case 'disaster_status':
                $pdo->prepare("UPDATE disaster SET status=? WHERE disaster_id=?")->execute([$_POST['status'],$_POST['id']]);
                $msg = 'Disaster updated.'; break;
            case 'shelter':
                $pdo->prepare("INSERT INTO shelter(disaster_id,name,location,capacity,occupied) VALUES(?,?,?,?,?)")
                    ->execute([$_POST['disaster_id'],$_POST['name'],$_POST['location'],(int)$_POST['capacity'],(int)$_POST['occupied']]);
                $msg = 'Shelter added.'; break;
            case 'occupancy':
                $pdo->prepare("UPDATE shelter SET occupied=? WHERE shelter_id=?")->execute([(int)$_POST['occupied'],$_POST['id']]);
                $msg = 'Occupancy updated.'; break;
            case 'supply':
                $pdo->prepare("INSERT INTO relief_supplies(shelter_id,item_name,quantity,unit) VALUES(?,?,?,?)")
                    ->execute([$_POST['shelter_id'],$_POST['item_name'],(int)$_POST['quantity'],$_POST['unit']]);
                $msg = 'Supply added.'; break;
            case 'donation':  // trigger trg_donation_stock increases the inventory
                $pdo->prepare("INSERT INTO donation(donor_name,supply_id,quantity) VALUES(?,?,?)")
                    ->execute([$_POST['donor_name'],$_POST['supply_id'],(int)$_POST['quantity']]);
                $msg = 'Donation recorded and stock updated.'; break;
            case 'delete_supply':
                $pdo->prepare("DELETE FROM relief_supplies WHERE supply_id=?")->execute([$_POST['id']]);
                $msg = 'Supply removed.'; break;
        }
    } catch (PDOException $ex) { $msg = 'Error: ' . $ex->errorInfo[2]; }
}

page_header('Dashboard');
if ($msg) echo '<div class="alert alert-info">' . e($msg) . '</div>';

/* ================= CITIZEN ================= */
if ($u['role'] === 'citizen'):
  $c = $pdo->prepare("SELECT status, COUNT(*) n FROM emergency_request WHERE user_id=? GROUP BY status");
  $c->execute([$u['id']]); $counts = $c->fetchAll(PDO::FETCH_KEY_PAIR); ?>
  <h3>Welcome, <?= e($u['name']) ?></h3>
  <div class="row g-3 my-2">
   <?php foreach (['pending','assigned','in_progress','completed'] as $s): ?>
    <div class="col-6 col-md-3"><div class="card stat-card text-center p-3"><h2><?= (int)($counts[$s] ?? 0) ?></h2>
    <div class="text-muted"><?= e(str_replace('_',' ',$s)) ?></div></div></div>
   <?php endforeach; ?>
  </div>
  <a href="report.php" class="btn btn-danger">Report an emergency</a>
  <a href="my_requests.php" class="btn btn-outline-secondary">View my requests</a>

<?php /* ================= VOLUNTEER / TEAM ================= */
elseif ($u['role'] === 'volunteer'): ?>
  <h3>Welcome, <?= e($u['name']) ?></h3><a href="volunteer.php" class="btn btn-danger">Open my tasks</a>
<?php elseif ($u['role'] === 'team'): ?>
  <h3>Welcome, <?= e($u['name']) ?></h3><a href="team.php" class="btn btn-danger">Open team assignments</a>

<?php /* ================= ADMIN ================= */
else:
  $tot = $pdo->query("SELECT COUNT(*) total, SUM(status='pending') pending, SUM(status='completed') done FROM emergency_request")->fetch();
  $byType = $pdo->query("SELECT request_type, COUNT(*) n FROM emergency_request GROUP BY request_type")->fetchAll();
  $disasters = $pdo->query("SELECT * FROM disaster ORDER BY start_date DESC")->fetchAll();
  $shelters  = $pdo->query("SELECT s.*, d.name dname FROM shelter s JOIN disaster d USING(disaster_id)")->fetchAll();
  $supplies  = $pdo->query("SELECT r.*, s.name sname FROM relief_supplies r JOIN shelter s USING(shelter_id) ORDER BY r.item_name")->fetchAll();
  $donations = $pdo->query("SELECT d.*, r.item_name FROM donation d JOIN relief_supplies r USING(supply_id) ORDER BY donation_id DESC LIMIT 10")->fetchAll();
  $volCount  = $pdo->query("SELECT availability, COUNT(*) FROM volunteers GROUP BY availability")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
  <h3>Admin Dashboard</h3>
  <div class="row g-3 my-2">
    <div class="col-6 col-md-3"><div class="card stat-card text-center p-3"><h2><?= (int)$tot['total'] ?></h2><div class="text-muted">Total requests</div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card text-center p-3"><h2><?= (int)$tot['pending'] ?></h2><div class="text-muted">Pending</div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card text-center p-3"><h2><?= (int)$tot['done'] ?></h2><div class="text-muted">Completed</div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card text-center p-3"><h2><?= (int)($volCount['available'] ?? 0) ?></h2><div class="text-muted">Volunteers available</div></div></div>
  </div>
  <p><a href="admin_requests.php" class="btn btn-danger">Manage requests &amp; assignments</a>
     <span class="ms-3 text-muted">By type:
     <?php foreach ($byType as $b) echo e($b['request_type']) . ': <b>' . (int)$b['n'] . '</b> &nbsp;'; ?></span></p>

  <ul class="nav nav-tabs mt-4" role="tablist">
   <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t1">Disasters</button></li>
   <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t2">Shelters</button></li>
   <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t3">Supplies</button></li>
   <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t4">Donations</button></li>
  </ul>
  <div class="tab-content card p-3 border-top-0 rounded-top-0">

  <div class="tab-pane fade show active" id="t1">
   <form method="post" class="row g-2 mb-3"><input type="hidden" name="action" value="disaster">
    <div class="col-md-3"><input name="name" class="form-control" placeholder="Name" required></div>
    <div class="col-md-2"><input name="type" class="form-control" placeholder="Type" required></div>
    <div class="col-md-2"><input name="location" class="form-control" placeholder="Location" required></div>
    <div class="col-md-2"><select name="severity" class="form-select"><option>low</option><option selected>medium</option><option>high</option><option>critical</option></select></div>
    <div class="col-md-2"><input name="start_date" type="date" value="<?= date('Y-m-d') ?>" class="form-control" required></div>
    <div class="col-md-1"><button class="btn btn-danger w-100">Add</button></div></form>
   <table class="table table-sm"><tr><th>Name</th><th>Type</th><th>Location</th><th>Severity</th><th>Status</th><th></th></tr>
   <?php foreach ($disasters as $d): ?><tr><td><?= e($d['name']) ?></td><td><?= e($d['type']) ?></td><td><?= e($d['location']) ?></td>
    <td><?= e($d['severity']) ?></td><td><?= e($d['status']) ?></td>
    <td><form method="post" class="d-flex gap-1"><input type="hidden" name="action" value="disaster_status"><input type="hidden" name="id" value="<?= $d['disaster_id'] ?>">
     <select name="status" class="form-select form-select-sm"><?php foreach (['active','contained','closed'] as $s) echo "<option ".($s==$d['status']?'selected':'').">$s</option>"; ?></select>
     <button class="btn btn-sm btn-outline-secondary">Set</button></form></td></tr><?php endforeach; ?></table>
  </div>

  <div class="tab-pane fade" id="t2">
   <form method="post" class="row g-2 mb-3"><input type="hidden" name="action" value="shelter">
    <div class="col-md-3"><select name="disaster_id" class="form-select"><?php foreach ($disasters as $d) echo '<option value="'.$d['disaster_id'].'">'.e($d['name']).'</option>'; ?></select></div>
    <div class="col-md-3"><input name="name" class="form-control" placeholder="Shelter name" required></div>
    <div class="col-md-2"><input name="location" class="form-control" placeholder="Location" required></div>
    <div class="col-md-1"><input name="capacity" type="number" min="0" class="form-control" placeholder="Cap." required></div>
    <div class="col-md-2"><input name="occupied" type="number" min="0" value="0" class="form-control"></div>
    <div class="col-md-1"><button class="btn btn-danger w-100">Add</button></div></form>
   <table class="table table-sm"><tr><th>Shelter</th><th>Disaster</th><th>Location</th><th>Capacity</th><th>Occupied</th><th>Update occupancy</th></tr>
   <?php foreach ($shelters as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= e($s['dname']) ?></td><td><?= e($s['location']) ?></td>
    <td><?= $s['capacity'] ?></td><td><?= $s['occupied'] ?></td>
    <td><form method="post" class="d-flex gap-1"><input type="hidden" name="action" value="occupancy"><input type="hidden" name="id" value="<?= $s['shelter_id'] ?>">
     <input name="occupied" type="number" min="0" value="<?= $s['occupied'] ?>" class="form-control form-control-sm" style="width:90px">
     <button class="btn btn-sm btn-outline-secondary">Save</button></form></td></tr><?php endforeach; ?></table>
  </div>

  <div class="tab-pane fade" id="t3">
   <form method="post" class="row g-2 mb-3"><input type="hidden" name="action" value="supply">
    <div class="col-md-3"><select name="shelter_id" class="form-select"><?php foreach ($shelters as $s) echo '<option value="'.$s['shelter_id'].'">'.e($s['name']).'</option>'; ?></select></div>
    <div class="col-md-3"><input name="item_name" class="form-control" placeholder="Item" required></div>
    <div class="col-md-2"><input name="quantity" type="number" min="0" class="form-control" placeholder="Qty" required></div>
    <div class="col-md-2"><input name="unit" class="form-control" placeholder="Unit" value="units"></div>
    <div class="col-md-2"><button class="btn btn-danger w-100">Add</button></div></form>
   <table class="table table-sm"><tr><th>Item</th><th>Shelter</th><th>Quantity</th><th></th></tr>
   <?php foreach ($supplies as $r): ?><tr><td><?= e($r['item_name']) ?></td><td><?= e($r['sname']) ?></td>
    <td><?= $r['quantity'] . ' ' . e($r['unit']) ?></td>
    <td><form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_supply"><input type="hidden" name="id" value="<?= $r['supply_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr><?php endforeach; ?></table>
  </div>

  <div class="tab-pane fade" id="t4">
   <form method="post" class="row g-2 mb-3"><input type="hidden" name="action" value="donation">
    <div class="col-md-4"><input name="donor_name" class="form-control" placeholder="Donor name" required></div>
    <div class="col-md-4"><select name="supply_id" class="form-select"><?php foreach ($supplies as $r) echo '<option value="'.$r['supply_id'].'">'.e($r['item_name'].' @ '.$r['sname']).'</option>'; ?></select></div>
    <div class="col-md-2"><input name="quantity" type="number" min="1" class="form-control" placeholder="Qty" required></div>
    <div class="col-md-2"><button class="btn btn-danger w-100">Record</button></div></form>
   <table class="table table-sm"><tr><th>Donor</th><th>Item</th><th>Qty</th><th>Date</th></tr>
   <?php foreach ($donations as $d): ?><tr><td><?= e($d['donor_name']) ?></td><td><?= e($d['item_name']) ?></td><td><?= $d['quantity'] ?></td><td><?= e($d['donated_on']) ?></td></tr><?php endforeach; ?></table>
  </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif;
page_footer();
