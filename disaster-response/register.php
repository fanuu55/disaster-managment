<?php
require 'config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']); $email = trim($_POST['email']); $phone = trim($_POST['phone']);
    $pass = $_POST['password']; $role = $_POST['role'];
    if (!in_array($role, ['citizen','volunteer','team'])) $role = 'citizen';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
        $err = 'Enter a name, valid email and a password of at least 6 characters.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO users(name,email,phone,password,role) VALUES(?,?,?,?,?)")
                ->execute([$name,$email,$phone,password_hash($pass, PASSWORD_DEFAULT),$role]);
            $uid = $pdo->lastInsertId();
            if ($role === 'volunteer')
                $pdo->prepare("INSERT INTO volunteers(user_id,skills,location) VALUES(?,?,?)")
                    ->execute([$uid, trim($_POST['skills'] ?? ''), trim($_POST['location'] ?? '')]);
            if ($role === 'team')
                $pdo->prepare("INSERT INTO rescue_team(user_id,team_name,members) VALUES(?,?,?)")
                    ->execute([$uid, trim($_POST['team_name'] ?? '') ?: $name.' Team', (int)($_POST['members'] ?? 1)]);
            $pdo->commit();
            header('Location: login.php?registered=1'); exit;
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $err = ($ex->errorInfo[1] ?? 0) == 1062 ? 'Email already registered.' : 'Registration failed.';
        }
    }
}
page_header('Register'); ?>
<div class="row justify-content-center"><div class="col-md-6"><div class="card p-4">
<h4 class="mb-3">Create an account</h4>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<form method="post">
 <div class="mb-2"><input name="name" class="form-control" placeholder="Full name" required></div>
 <div class="mb-2"><input name="email" type="email" class="form-control" placeholder="Email" required></div>
 <div class="mb-2"><input name="phone" class="form-control" placeholder="Phone"></div>
 <div class="mb-2"><input name="password" type="password" class="form-control" placeholder="Password (min 6)" required></div>
 <div class="mb-2"><select name="role" id="role" class="form-select" onchange="toggle()">
   <option value="citizen">Citizen</option><option value="volunteer">Volunteer</option><option value="team">Relief Team</option></select></div>
 <div id="vol" style="display:none">
   <input name="skills" class="form-control mb-2" placeholder="Skills (e.g. first aid, boat rescue)">
   <input name="location" class="form-control mb-2" placeholder="Your location"></div>
 <div id="team" style="display:none">
   <input name="team_name" class="form-control mb-2" placeholder="Team name">
   <input name="members" type="number" min="1" class="form-control mb-2" placeholder="Number of members"></div>
 <button class="btn btn-danger w-100">Register</button>
</form></div></div></div>
<script>function toggle(){var r=document.getElementById('role').value;
document.getElementById('vol').style.display=r==='volunteer'?'block':'none';
document.getElementById('team').style.display=r==='team'?'block':'none';}</script>
<?php page_footer();
