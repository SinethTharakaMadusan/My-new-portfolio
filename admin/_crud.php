<?php // Generic list + add/edit/delete page. Table/column names come only from the page config.
require __DIR__ . '/_header.php';
$t = $cfg['table']; $F = $cfg['fields']; $first = array_key_first($F);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $id = (int)($_POST['id'] ?? 0);
    if (($_POST['do'] ?? '') === 'delete') { $pdo->prepare("DELETE FROM $t WHERE id=?")->execute([$id]); flash('Deleted.'); }
    else {
        $v = [];
        foreach ($F as $c => [$l, $ty, $o]) {
            $x = trim($_POST[$c] ?? '');
            $v[$c] = $ty === 'number' ? max(0, min(100, (int)$x)) : ($ty === 'select' ? (in_array($x, $o, true) ? $x : $o[0]) : mb_substr($x, 0, $ty === 'textarea' ? 2000 : 150));
        }
        $v['is_visible'] = isset($_POST['is_visible']) ? 1 : 0;
        if ($v[$first] === '') flash($F[$first][0] . ' is required.', 'err');
        else {
            $cols = array_keys($v);
            if ($id) $pdo->prepare("UPDATE $t SET " . implode(',', array_map(fn($c) => "$c=?", $cols)) . ' WHERE id=?')->execute([...array_values($v), $id]);
            else $pdo->prepare("INSERT INTO $t (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($v));
            flash('Saved.');
        }
    }
    header('Location: ' . basename($_SERVER['SCRIPT_NAME'])); exit;
}
$e = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare("SELECT * FROM $t WHERE id=?"); $s->execute([(int)$_GET['edit']]); $e = $s->fetch() ?: null; }
$rows = $pdo->query("SELECT * FROM $t ORDER BY id")->fetchAll(); ?>
<h1><?= e($cfg['title']) ?></h1>
<form method="post" class="card"><h2><?= $e ? 'Edit' : 'Add new' ?></h2><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
<?php foreach ($F as $c => [$l, $ty, $o]): $val = $e[$c] ?? ($ty === 'number' ? 50 : ''); ?>
<label for="<?= $c ?>"><?= e($l) ?></label>
<?php if ($ty === 'textarea'): ?><textarea id="<?= $c ?>" name="<?= $c ?>" rows="3"><?= e($val) ?></textarea>
<?php elseif ($ty === 'select'): ?><select id="<?= $c ?>" name="<?= $c ?>" style="width:100%;padding:11px;background:#1a1a1a;color:#fff;border-radius:8px"><?php foreach ($o as $op): ?><option <?= $val === $op ? 'selected' : '' ?>><?= e($op) ?></option><?php endforeach; ?></select>
<?php else: ?><input id="<?= $c ?>" name="<?= $c ?>" <?= $ty === 'number' ? 'type="number" min="0" max="100"' : '' ?> value="<?= e((string)$val) ?>"><?php endif; endforeach; ?>
<label class="chk"><input type="checkbox" name="is_visible" <?= ($e['is_visible'] ?? 1) ? 'checked' : '' ?>> Visible on public site</label>
<button class="btn">Save</button> <?php if ($e): ?><a href="<?= e(basename($_SERVER['SCRIPT_NAME'])) ?>">Cancel</a><?php endif; ?></form>
<div class="card tbl"><table><tr><?php foreach ($cfg['cols'] as $c): ?><th><?= e($F[$c][0]) ?></th><?php endforeach; ?><th>Status</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="9" class="muted">Nothing here yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><?php foreach ($cfg['cols'] as $c): ?><td><?= e((string)$r[$c]) ?></td><?php endforeach; ?>
<td><span class="pill <?= $r['is_visible'] ? 'on' : '' ?>"><?= $r['is_visible'] ? 'Visible' : 'Hidden' ?></span></td>
<td><a href="?edit=<?= (int)$r['id'] ?>">Edit</a><form method="post" class="inline" onsubmit="return confirm('Delete permanently?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link danger">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<?php require __DIR__ . '/_footer.php';
