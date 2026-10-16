<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(json_encode(['ok' => false])); }
if (!empty($_POST['website'])) exit(json_encode(['ok' => true])); // honeypot
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$n = trim($_POST['name'] ?? ''); $em = trim($_POST['email'] ?? ''); $m = trim($_POST['message'] ?? '');
$sub = trim($_POST['subject'] ?? ''); $mob = trim($_POST['mobile'] ?? '');
if ($n === '' || !filter_var($em, FILTER_VALIDATE_EMAIL) || $m === '' || mb_strlen($m) > 5000 || mb_strlen($n) > 100) {
    http_response_code(422); exit(json_encode(['ok' => false, 'error' => 'Please fill in name, a valid email and a message.']));
}
$c = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE ip=? AND created_at > NOW() - INTERVAL 10 MINUTE'); $c->execute([$ip]);
if ($c->fetchColumn() >= 3) { http_response_code(429); exit(json_encode(['ok' => false, 'error' => 'Too many messages, try again later.'])); }
$pdo->prepare('INSERT INTO messages (name,email,mobile,subject,message,ip) VALUES (?,?,?,?,?,?)')
    ->execute([$n, $em, mb_substr($mob, 0, 30), mb_substr($sub, 0, 150), $m, $ip]);
echo json_encode(['ok' => true]);
