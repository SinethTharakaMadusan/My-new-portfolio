<?php
// CLI only: php setup_admin.php <username> <password>   (delete this file afterwards)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/includes/bootstrap.php';
[$_, $u, $p] = $argv + [null, '', ''];
if ($u === '' || strlen($p) < 10) exit("Usage: php setup_admin.php <username> <password (min 10 chars)>\n");
$pdo->prepare('INSERT INTO admins (username,password_hash) VALUES (?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
echo "Admin created. Now delete setup_admin.php.\n";
