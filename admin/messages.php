<?php require __DIR__ . '/_header.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $id = (int)($_POST['id'] ?? 0);
    if (($_POST['do'] ?? '') === 'delete') { $pdo->prepare('DELETE FROM messages WHERE id=?')->execute([$id]); flash('Message deleted.'); }
    elseif (($_POST['do'] ?? '') === 'toggle') { $pdo->prepare('UPDATE messages SET is_read = 1 - is_read WHERE id=?')->execute([$id]); }
    header('Location: messages.php'); exit;
}
$rows = $pdo->query('SELECT * FROM messages ORDER BY id DESC LIMIT 200')->fetchAll(); ?>
<h1>Messages</h1>
<?php if (!$rows): ?><div class="card muted">No messages yet.</div><?php endif; ?>
<?php foreach ($rows as $r): ?><div class="card" <?= $r['is_read'] ? '' : 'style="border-color:#00e676"' ?>>
<p><b><?= e($r['name']) ?></b> &lt;<?= e($r['email']) ?>&gt; <?= $r['mobile'] ? '· ' . e($r['mobile']) : '' ?> <span class="muted"><?= e($r['created_at']) ?></span></p>
<p><b><?= e($r['subject'] ?: '(no subject)') ?></b></p><p style="white-space:pre-wrap;margin:8px 0"><?= e($r['message']) ?></p>
<form method="post" class="inline" style="margin:0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="do" value="toggle"><button class="link">Mark as <?= $r['is_read'] ? 'unread' : 'read' ?></button></form>
<form method="post" class="inline" onsubmit="return confirm('Delete this message permanently?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="do" value="delete"><button class="link danger">Delete</button></form></div><?php endforeach;
require __DIR__ . '/_footer.php';
