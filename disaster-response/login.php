<?php
require 'config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $st->execute([trim($_POST['email'])]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'], $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>$u['user_id'],'name'=>$u['name'],'role'=>$u['role']];
        header('Location: dashboard.php'); exit;
    }
    $err = 'Invalid email or password.';
}
page_header('Login'); ?>
<div class="row justify-content-center"><div class="col-md-4"><div class="card p-4">
<h4 class="mb-3">Login</h4>
<?php if (isset($_GET['registered'])): ?><div class="alert alert-success">Registered! Please log in.</div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<form method="post">
 <input name="email" type="email" class="form-control mb-2" placeholder="Email" required>
 <input name="password" type="password" class="form-control mb-3" placeholder="Password" required>
 <button class="btn btn-danger w-100">Login</button>
</form></div></div></div>
<?php page_footer();
