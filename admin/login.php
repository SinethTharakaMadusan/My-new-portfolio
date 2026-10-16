<?php
require __DIR__ . '/../includes/bootstrap.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $c = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND created_at > NOW() - INTERVAL 15 MINUTE'); $c->execute([$ip]);
    if ($c->fetchColumn() >= 5) { $err = 'Too many attempts. Try again in 15 minutes.'; }
    else {
        $s = $pdo->prepare('SELECT * FROM admins WHERE username=?'); $s->execute([trim($_POST['username'] ?? '')]);
        $a = $s->fetch();
        if ($a && password_verify($_POST['password'] ?? '', $a['password_hash'])) {
            session_regenerate_id(true); $_SESSION['admin_id'] = $a['id']; $_SESSION['admin_name'] = $a['username'];
            $pdo->prepare('DELETE FROM login_attempts WHERE ip=?')->execute([$ip]);
            header('Location: index.php'); exit;
        }
        $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        $err = 'Invalid username or password.';
    }
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title><link rel="stylesheet" href="assets/admin.css"></head>
<body class="login"><form method="post" class="card login-card"><h1>Admin <span>Login</span></h1>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
<?= csrf_field() ?><label for="u">Username</label><input id="u" name="username" required autofocus autocomplete="username">
<label for="p">Password</label><input id="p" type="password" name="password" required autocomplete="current-password">
<button class="btn">Sign in</button></form></body></html>
