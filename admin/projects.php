<?php require __DIR__ . '/_header.php';
$dir = __DIR__ . '/../uploads';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['do'] ?? '') === 'delete') {
            $pdo->prepare('DELETE FROM projects WHERE id=?')->execute([$id]); flash('Project deleted.');
        } else {
            $t = trim($_POST['title'] ?? ''); $d = trim($_POST['description'] ?? '');
            if ($t === '' || $d === '' || mb_strlen($t) > 150) throw new RuntimeException('Title and description are required.');
            $img = save_image($_FILES['thumbnail'] ?? ['error' => UPLOAD_ERR_NO_FILE], $dir);
            $v = [$t, $d, mb_substr(trim($_POST['technologies'] ?? ''), 0, 255), mb_substr(trim($_POST['category'] ?? ''), 0, 60),
                  safe_url($_POST['github_url'] ?? ''), safe_url($_POST['demo_url'] ?? ''), isset($_POST['is_featured']) ? 1 : 0, isset($_POST['is_visible']) ? 1 : 0];
            if ($id) {
                $pdo->prepare('UPDATE projects SET title=?,description=?,technologies=?,category=?,github_url=?,demo_url=?,is_featured=?,is_visible=? WHERE id=?')->execute([...$v, $id]);
                if ($img) $pdo->prepare('UPDATE projects SET thumbnail=? WHERE id=?')->execute([$img, $id]);
                flash('Project updated.');
            } else {
                $pdo->prepare('INSERT INTO projects (title,description,technologies,category,github_url,demo_url,is_featured,is_visible,thumbnail) VALUES (?,?,?,?,?,?,?,?,?)')->execute([...$v, $img ?? '']);
                flash('Project added.');
            }
        }
    } catch (Throwable $x) { flash($x instanceof RuntimeException ? $x->getMessage() : 'Something went wrong.', 'err'); }
    header('Location: projects.php'); exit;
}
$action = $_GET['action'] ?? ''; $p = ['id'=>0,'title'=>'','description'=>'','technologies'=>'','category'=>'','github_url'=>'','demo_url'=>'','thumbnail'=>'','is_featured'=>0,'is_visible'=>1];
if ($action === 'edit') { $s = $pdo->prepare('SELECT * FROM projects WHERE id=?'); $s->execute([(int)($_GET['id'] ?? 0)]); $p = $s->fetch() ?: $p; }
if ($action === 'new' || $action === 'edit'): ?>
<h1><?= $p['id'] ? 'Edit' : 'New' ?> project</h1>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
<label for="t">Title *</label><input id="t" name="title" required maxlength="150" value="<?= e($p['title']) ?>">
<label for="d">Description *</label><textarea id="d" name="description" rows="4" required><?= e($p['description']) ?></textarea>
<label for="te">Technologies (comma separated)</label><input id="te" name="technologies" value="<?= e($p['technologies']) ?>">
<label for="c">Category</label><input id="c" name="category" value="<?= e($p['category']) ?>">
<label for="g">GitHub URL</label><input id="g" type="url" name="github_url" value="<?= e($p['github_url']) ?>">
<label for="l">Live demo URL</label><input id="l" type="url" name="demo_url" value="<?= e($p['demo_url']) ?>">
<label for="i">Thumbnail (JPG/PNG/WebP, max 3 MB)</label><input id="i" type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp">
<?php if ($p['thumbnail']): ?><img class="thumb" src="../<?= e($p['thumbnail']) ?>" alt="Current thumbnail"><?php endif; ?>
<label class="chk"><input type="checkbox" name="is_featured" <?= $p['is_featured'] ? 'checked' : '' ?>> Featured</label>
<label class="chk"><input type="checkbox" name="is_visible" <?= $p['is_visible'] ? 'checked' : '' ?>> Visible on public site</label>
<button class="btn">Save</button> <a href="projects.php">Cancel</a></form>
<?php else:
$q = trim($_GET['q'] ?? ''); $s = $pdo->prepare('SELECT * FROM projects WHERE title LIKE ? OR category LIKE ? ORDER BY id DESC');
$like = '%' . addcslashes($q, '%_') . '%'; $s->execute([$like, $like]); $rows = $s->fetchAll(); ?>
<h1>Projects <a class="btn sm" href="?action=new">+ Add</a></h1>
<form class="search"><input name="q" placeholder="Search title or category" value="<?= e($q) ?>" aria-label="Search"></form>
<div class="card tbl"><table><tr><th></th><th>Title</th><th>Category</th><th>Status</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">No projects found.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td><?php if ($r['thumbnail']): ?><img class="mini" src="../<?= e($r['thumbnail']) ?>" alt=""><?php endif; ?></td>
<td><?= e($r['title']) ?><?= $r['is_featured'] ? ' ★' : '' ?></td><td><?= e($r['category']) ?></td>
<td><span class="pill <?= $r['is_visible'] ? 'on' : '' ?>"><?= $r['is_visible'] ? 'Visible' : 'Hidden' ?></span></td>
<td><a href="?action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
<form method="post" class="inline" onsubmit="return confirm('Delete this project permanently?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link danger">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<?php endif; require __DIR__ . '/_footer.php';
