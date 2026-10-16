<?php require __DIR__ . '/_header.php';
$n = fn($t, $w = '1') => (int)$pdo->query("SELECT COUNT(*) FROM $t WHERE $w")->fetchColumn();
$cards = ['Projects' => $n('projects'), 'Skills' => $n('skills'), 'Messages' => $n('messages'), 'Unread' => $n('messages', 'is_read=0')];
$recent = $pdo->query('SELECT name,subject,created_at FROM messages ORDER BY id DESC LIMIT 5')->fetchAll(); ?>
<h1>Dashboard</h1><div class="grid"><?php foreach ($cards as $l => $v): ?><div class="card stat"><small><?= $l ?></small><b><?= $v ?></b></div><?php endforeach; ?></div>
<div class="card"><h2>Recent messages</h2><?php if (!$recent): ?><p class="muted">No messages yet.</p><?php endif; ?>
<?php foreach ($recent as $r): ?><p><b><?= e($r['name']) ?></b> — <?= e($r['subject'] ?: '(no subject)') ?> <span class="muted"><?= e($r['created_at']) ?></span></p><?php endforeach; ?></div>
<p><a class="btn" href="projects.php?action=new">+ Add project</a></p>
<?php require __DIR__ . '/_footer.php';
