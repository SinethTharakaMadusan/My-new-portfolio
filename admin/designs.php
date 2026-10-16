<?php require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/designs_lib.php';
$dir = __DIR__ . '/../uploads/designs';

/** Validates and stores an uploaded design image (extension comes from the real file type). Returns relative path. */
function design_store_upload(array $f, string $dir): string
{
    design_check_image($f);
    $type = @getimagesize($f['tmp_name'])[2] ?? 0;
    $ext  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$type] ?? null;
    if (!$ext) throw new RuntimeException('Only real JPG, PNG or WebP images are allowed.');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Could not create the uploads/designs folder.');
    $name = 'design_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not save the uploaded image.');
    return 'uploads/designs/' . $name;
}

$run = function (string $sql, array $args = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    if (!$s || !$s->execute($args)) throw new RuntimeException('Database error. Has database/designs.sql been imported?');
    return $s;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $do = $_POST['do'] ?? 'save'; $back = 'designs.php'; $newFiles = [];
    try {
        if ($do === 'delete') {
            $old = $run('SELECT image,thumb FROM designs WHERE id=?', [$id])->fetch();
            $run('DELETE FROM designs WHERE id=?', [$id]);
            if ($old) design_delete_files($pdo, [$old['image'], $old['thumb']], $id);
            flash('Design deleted.');
        } elseif ($do === 'toggle') {
            $run('UPDATE designs SET is_visible = 1 - is_visible WHERE id=?', [$id]);
            flash('Status updated.');
        } elseif ($do === 'save_url') {
            $u = design_clean_url($_POST['see_more_url'] ?? '');
            if ($u === null) throw new RuntimeException('Please enter a valid http:// or https:// URL (or leave it empty to hide the button).');
            $run('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [DESIGN_SEE_MORE_KEY, $u]);
            flash($u === '' ? 'See More URL cleared. The button is now hidden on the site.' : 'See More URL saved.');
        } else {
            $back = 'designs.php?action=' . ($id ? 'edit&id=' . $id : 'new');
            $_SESSION['design_old'] = ['id' => $id, 'title' => trim($_POST['title'] ?? ''), 'category' => $_POST['category'] ?? '',
                'description' => trim($_POST['description'] ?? ''), 'project_url' => trim($_POST['project_url'] ?? ''), 'is_visible' => isset($_POST['is_visible']) ? 1 : 0];
            $o = $_SESSION['design_old'];
            if ($o['title'] === '' || mb_strlen($o['title']) > 150) throw new RuntimeException('Title is required (max 150 characters).');
            if (!in_array($o['category'], design_categories(), true)) throw new RuntimeException('Please choose a category.');
            if (mb_strlen($o['description']) > 500) throw new RuntimeException('Description is too long (max 500 characters).');
            $url = design_clean_url($o['project_url']);
            if ($url === null) throw new RuntimeException('Project URL must be a valid http:// or https:// link.');
            $old = null;
            if ($id) { $old = $run('SELECT * FROM designs WHERE id=?', [$id])->fetch(); if (!$old) throw new RuntimeException('Design not found.'); }
            $hasFile = ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if (!$old && !$hasFile) throw new RuntimeException('Please choose a design image.');
            $image = $old['image'] ?? ''; $thumb = $old['thumb'] ?? ''; $w = (int)($old['width'] ?? 0); $h = (int)($old['height'] ?? 0);
            if ($hasFile) {
                $image = design_store_upload($_FILES['image'], $dir); $newFiles[] = $image;
                $thumb = design_make_thumb($image); if ($thumb) $newFiles[] = $thumb;
                [$w, $h] = design_dimensions($image);
            }
            if ($old) {
                $run('UPDATE designs SET title=?,category=?,description=?,image=?,thumb=?,width=?,height=?,project_url=?,is_visible=? WHERE id=?',
                    [$o['title'], $o['category'], $o['description'], $image, $thumb, $w, $h, $url, $o['is_visible'], $id]);
                if ($hasFile) design_delete_files($pdo, [$old['image'], $old['thumb']], $id);
                flash('Design updated.');
            } else {
                $run('INSERT INTO designs (title,category,description,image,thumb,width,height,project_url,is_visible) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$o['title'], $o['category'], $o['description'], $image, $thumb, $w, $h, $url, $o['is_visible']]);
                flash('Design added.');
            }
            unset($_SESSION['design_old']);
        }
    } catch (Throwable $x) {
        foreach ($newFiles as $rel) { $a = design_abs($rel); if ($a) @unlink($a); } // no orphan uploads
        flash($x instanceof RuntimeException ? $x->getMessage() : 'Something went wrong.', 'err');
    }
    header('Location: ' . $back); exit;
}

$action = $_GET['action'] ?? '';
if ($action === 'new' || $action === 'edit'):
    $d = ['id' => 0, 'title' => '', 'category' => 'Class Poster', 'description' => '', 'image' => '', 'project_url' => '', 'is_visible' => 1];
    if ($action === 'edit') { try { $s = $pdo->prepare('SELECT * FROM designs WHERE id=?'); $s->execute([(int)($_GET['id'] ?? 0)]); $d = $s->fetch() ?: $d; } catch (Throwable $x) {} }
    if (isset($_SESSION['design_old'])) { $d = array_merge($d, $_SESSION['design_old']); unset($_SESSION['design_old']); }
?>
<h1><?= $d['id'] ? 'Edit' : 'New' ?> design</h1>
<form method="post" enctype="multipart/form-data" class="card" id="designForm"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
<label for="dt">Design title *</label><input id="dt" name="title" required maxlength="150" value="<?= e($d['title']) ?>">
<label for="dc">Category *</label><select id="dc" name="category" style="width:100%;padding:11px;background:#1a1a1a;color:#fff;border-radius:8px"><?php foreach (design_categories() as $c): ?><option <?= $d['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
<label for="dd">Short description (optional, max 500)</label><textarea id="dd" name="description" rows="3" maxlength="500"><?= e($d['description']) ?></textarea>
<label for="di">Design image <?= $d['id'] ? '(leave empty to keep the current one)' : '*' ?> &mdash; JPG, PNG or WebP, max 8 MB</label>
<input id="di" type="file" name="image" accept="image/jpeg,image/png,image/webp" <?= $d['id'] ? '' : 'required' ?>>
<img id="dPrev" class="thumb" alt="Image preview" src="<?= $d['image'] ? '../' . e($d['image']) : '' ?>" style="max-width:320px;width:100%;height:auto;<?= $d['image'] ? '' : 'display:none' ?>">
<label for="du">Project URL (optional)</label><input id="du" name="project_url" type="url" maxlength="255" placeholder="https://..." value="<?= e($d['project_url']) ?>">
<label class="chk"><input type="checkbox" name="is_visible" <?= $d['is_visible'] ? 'checked' : '' ?>> Published (visible on public site)</label>
<button class="btn" id="dSave">Save</button> <a href="designs.php">Cancel</a><span id="dBusy" class="muted" style="display:none"> Uploading, please wait&hellip;</span></form>
<script>
(function () {
    var f = document.getElementById('di'), img = document.getElementById('dPrev'), form = document.getElementById('designForm'),
        btn = document.getElementById('dSave'), busy = document.getElementById('dBusy'), obj = null;
    f.addEventListener('change', function () {
        if (obj) { URL.revokeObjectURL(obj); obj = null; }
        if (f.files && f.files[0]) {
            if (f.files[0].size > 8 * 1024 * 1024) { alert('Image is too large (max 8 MB).'); f.value = ''; return; }
            obj = URL.createObjectURL(f.files[0]); img.src = obj; img.style.display = 'block';
        }
    });
    form.addEventListener('submit', function () { btn.disabled = true; busy.style.display = 'inline'; });
    window.addEventListener('pageshow', function () { btn.disabled = false; busy.style.display = 'none'; });
})();
</script>
<?php else:
    $rows = []; $err = false;
    try { $rows = $pdo->query('SELECT * FROM designs ORDER BY created_at DESC, id DESC')->fetchAll(); }
    catch (Throwable $x) { $err = true; echo '<div class="alert err">Could not read the designs table. Import database/designs.sql first.</div>'; }
    $seeMore = design_see_more_url($pdo);
?>
<h1>Graphic Design Management <a class="btn sm" href="?action=new">+ Add New Design</a></h1>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="do" value="save_url"><h2>&ldquo;See More Designs&rdquo; button</h2>
<label for="sm">Destination URL (leave empty to hide the button on the site)</label>
<input id="sm" name="see_more_url" type="url" maxlength="255" placeholder="https://..." value="<?= e($seeMore) ?>">
<p class="muted">Currently saved: <?= $seeMore !== '' ? '<a href="' . e($seeMore) . '" target="_blank" rel="noopener noreferrer">' . e($seeMore) . '</a>' : 'nothing (button hidden)' ?></p>
<button class="btn">Save URL</button></form>
<?php if (!$err && !$rows): ?><div class="card muted">No designs yet. Click &ldquo;Add New Design&rdquo; to upload your first one.</div><?php endif; ?>
<?php if ($rows): ?><p class="muted">The homepage shows the latest <?= DESIGN_HOME_LIMIT ?> published designs.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px">
<?php foreach ($rows as $r): ?><div class="card" style="margin:0">
<img src="../<?= e($r['thumb'] ?: $r['image']) ?>" alt="<?= e($r['title']) ?>" loading="lazy" style="width:100%;height:200px;object-fit:cover;border-radius:8px;background:#111">
<p style="margin-top:10px"><b><?= e($r['title']) ?></b></p>
<p class="muted"><?= e($r['category']) ?> &middot; <?= e(substr((string)$r['created_at'], 0, 10)) ?></p>
<p><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link"><span class="pill <?= $r['is_visible'] ? 'on' : '' ?>"><?= $r['is_visible'] ? 'Published' : 'Draft' ?></span></button></form></p>
<p><a href="?action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
<form method="post" class="inline" onsubmit="return confirm('Delete this design permanently?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link danger">Delete</button></form></p>
</div><?php endforeach; ?></div><?php endif; ?>
<?php endif; require __DIR__ . '/_footer.php';
