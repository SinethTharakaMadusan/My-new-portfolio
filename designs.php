<?php
// Graphic Design Management. _header.php loads bootstrap.php and enforces require_admin() (existing admin login).
require __DIR__ . '/_header.php';
require __DIR__ . '/../includes/designs_lib.php';

$dir  = __DIR__ . '/../uploads';
$cats = design_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do   = $_POST['do'] ?? 'save';
    $id   = (int)($_POST['id'] ?? 0);
    $back = 'designs.php' . ($do === 'save' && $id ? '?edit=' . $id : '');
    $newFiles = [];
    try {
        if ($do === 'settings') {
            $u = design_clean_url($_POST['see_more_url'] ?? '');
            if ($u === null) throw new RuntimeException('The See More link must be a valid http:// or https:// URL.');
            $pdo->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([DESIGN_SEE_MORE_KEY, $u]);
            flash($u === '' ? 'See More link cleared. The button is now hidden on the site.' : 'See More link saved.');
            $back = 'designs.php';
        } elseif ($do === 'delete') {
            $s = $pdo->prepare('SELECT image, thumb FROM designs WHERE id=?');
            $s->execute([$id]);
            $old = $s->fetch();
            if (!$old) throw new RuntimeException('Design not found.');
            $pdo->prepare('DELETE FROM designs WHERE id=?')->execute([$id]);
            design_delete_files($pdo, [$old['image'], $old['thumb']], $id); // skips files other records still use
            flash('Design deleted.');
            $back = 'designs.php';
        } else {
            $title = mb_substr(trim($_POST['title'] ?? ''), 0, 150);
            $cat   = trim($_POST['category'] ?? '');
            if (!in_array($cat, $cats, true)) $cat = 'Other';
            $desc  = mb_substr(trim($_POST['description'] ?? ''), 0, 500);
            $url   = design_clean_url($_POST['external_url'] ?? '');
            $pub   = (($_POST['status'] ?? 'published') === 'draft') ? 0 : 1;
            if ($title === '') throw new RuntimeException('Design title is required.');
            if ($url === null) throw new RuntimeException('Project URL must be a valid http:// or https:// link.');

            $old = null;
            if ($id) {
                $s = $pdo->prepare('SELECT image, thumb FROM designs WHERE id=?');
                $s->execute([$id]);
                $old = $s->fetch();
                if (!$old) throw new RuntimeException('Design not found.');
            }
            $file    = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
            $hasFile = ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if (!$id && !$hasFile) throw new RuntimeException('Please choose a design image.');

            $img = $thumb = null; $w = $h = 0;
            if ($hasFile) {
                design_check_image($file);                       // real content check, size limit
                $img = save_image($file, $dir);                  // existing upload helper (same as Site content page)
                if (!$img) throw new RuntimeException('Image upload failed. Please try again.');
                $newFiles[] = $img;
                [$w, $h] = design_dimensions($img);
                $thumb = design_make_thumb($img);
                if ($thumb !== '') $newFiles[] = $thumb;
            }

            if ($id) {
                if ($hasFile) {
                    $pdo->prepare('UPDATE designs SET title=?,category=?,description=?,external_url=?,is_published=?,image=?,thumb=?,img_width=?,img_height=? WHERE id=?')
                        ->execute([$title, $cat, $desc, $url, $pub, $img, $thumb, $w, $h, $id]);
                    design_delete_files($pdo, [$old['image'], $old['thumb']], $id);
                } else {
                    $pdo->prepare('UPDATE designs SET title=?,category=?,description=?,external_url=?,is_published=? WHERE id=?')
                        ->execute([$title, $cat, $desc, $url, $pub, $id]);
                }
                flash('Design updated.');
            } else {
                $pdo->prepare('INSERT INTO designs (title,category,description,external_url,is_published,image,thumb,img_width,img_height) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$title, $cat, $desc, $url, $pub, $img, $thumb, $w, $h]);
                flash($pub ? 'Design added and published.' : 'Design saved as a draft.');
            }
            $back = 'designs.php';
        }
    } catch (RuntimeException $x) {
        design_delete_files($pdo, $newFiles);
        flash($x->getMessage(), 'err');
    } catch (Throwable $x) {
        design_delete_files($pdo, $newFiles);   // don't leave orphan uploads behind
        error_log('designs.php: ' . $x->getMessage());
        flash('Database error. Please try again or check that database/designs.sql has been imported.', 'err');
    }
    header('Location: ' . $back);
    exit;
}

$dbError = '';
$rows = []; $edit = null; $seeMore = '';
try {
    $rows = $pdo->query('SELECT * FROM designs ORDER BY created_at DESC, id DESC')->fetchAll();
    if (isset($_GET['edit'])) {
        $s = $pdo->prepare('SELECT * FROM designs WHERE id=?');
        $s->execute([(int)$_GET['edit']]);
        $edit = $s->fetch() ?: null;
    }
    $s = $pdo->prepare('SELECT v FROM settings WHERE k=?');
    $s->execute([DESIGN_SEE_MORE_KEY]);
    $seeMore = (string)$s->fetchColumn();
} catch (Throwable $x) {
    error_log('designs.php: ' . $x->getMessage());
    $dbError = 'The designs table is not available. Import database/designs.sql (phpMyAdmin > SQL tab), then reload this page.';
}
$publishedCount = count(array_filter($rows, fn($r) => (int)$r['is_published'] === 1));
$val = fn($k, $d = '') => e((string)($edit[$k] ?? $d));
?>
<style>
.dz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px}
.dz-item{background:#141414;border:1px solid rgba(255,255,255,.08);border-radius:14px;overflow:hidden;display:flex;flex-direction:column}
.dz-item.dz-draft{opacity:.75}
.dz-item img{display:block;width:100%;height:170px;object-fit:cover;background:#0b0b0b}
.dz-body{padding:12px 14px;display:flex;flex-direction:column;gap:6px;flex:1}
.dz-body b{font-size:.95rem;overflow-wrap:anywhere}
.dz-actions{display:flex;gap:14px;align-items:center;margin-top:auto;padding-top:8px}
.dz-preview{display:block;max-width:240px;max-height:200px;margin:10px 0;border-radius:10px;border:1px solid rgba(255,255,255,.1)}
.dz-preview[hidden]{display:none}
.dz-bar{display:none;height:4px;margin-top:12px;border-radius:4px;background:rgba(255,255,255,.1);overflow:hidden}
.dz-bar i{display:block;height:100%;width:40%;background:#00e676;animation:dzslide 1s ease-in-out infinite}
form.dz-busy .dz-bar{display:block}
@keyframes dzslide{0%{margin-left:-40%}100%{margin-left:100%}}
.dz-form select{width:100%;padding:11px;background:#1a1a1a;color:#fff;border-radius:8px}
.dz-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
</style>
<h1>Graphic Design Management</h1>

<?php if ($dbError): ?>
<div class="alert err"><?= e($dbError) ?></div>
<?php else: ?>

<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="do" value="settings">
    <h2>"See More Designs" button link</h2>
    <label for="see_more_url">Destination URL (opens when visitors click the button)</label>
    <input id="see_more_url" name="see_more_url" type="url" maxlength="255" placeholder="https://..." value="<?= e($seeMore) ?>">
    <p class="muted"><?= $seeMore !== '' ? 'Currently saved: ' . e($seeMore) : 'Not set yet. The button stays hidden on the public site until you add a link.' ?></p>
    <button class="btn">Save link</button>
</form>

<form method="post" enctype="multipart/form-data" class="card dz-form" id="dzForm"><?= csrf_field() ?>
    <input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <h2><?= $edit ? 'Edit design' : 'Add new design' ?></h2>
    <label for="title">Design title *</label>
    <input id="title" name="title" maxlength="150" required value="<?= $val('title') ?>">
    <label for="category">Category</label>
    <select id="category" name="category"><?php foreach ($cats as $c): ?><option <?= ($edit['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
    <label for="description">Short description (optional, max 500 characters)</label>
    <textarea id="description" name="description" rows="3" maxlength="500"><?= $val('description') ?></textarea>
    <label for="image">Design image <?= $edit ? '(leave empty to keep the current one)' : '*' ?> &mdash; JPG, PNG or WebP, max 8 MB</label>
    <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" <?= $edit ? '' : 'required' ?>>
    <img id="dzPreview" class="dz-preview" alt="Image preview" <?= $edit ? 'src="../' . e($edit['image']) . '"' : 'hidden' ?>>
    <label for="external_url">Project URL (optional)</label>
    <input id="external_url" name="external_url" type="url" maxlength="255" placeholder="https://..." value="<?= $val('external_url') ?>">
    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="published" <?= (int)($edit['is_published'] ?? 1) ? 'selected' : '' ?>>Published (visible on the website)</option>
        <option value="draft" <?= !(int)($edit['is_published'] ?? 1) ? 'selected' : '' ?>>Draft (hidden)</option>
    </select>
    <div class="dz-row" style="margin-top:14px">
        <button class="btn" id="dzSubmit"><?= $edit ? 'Save changes' : '+ Add design' ?></button>
        <?php if ($edit): ?><a href="designs.php">Cancel</a><?php endif; ?>
    </div>
    <div class="dz-bar" aria-hidden="true"><i></i></div>
    <p id="dzMsg" class="muted" role="status"></p>
</form>

<div class="card">
    <h2>All designs (<?= count($rows) ?>)</h2>
    <p class="muted">The homepage shows the latest <?= (int)DESIGN_HOME_LIMIT ?> published designs (<?= $publishedCount ?> published right now), newest first.</p>
    <?php if (!$rows): ?>
        <p class="muted">No designs yet. Use the form above to add your first one.</p>
    <?php else: ?>
    <div class="dz-grid">
        <?php foreach ($rows as $r): $pub = (int)$r['is_published'] === 1; ?>
        <div class="dz-item <?= $pub ? '' : 'dz-draft' ?>">
            <img src="../<?= e($r['thumb'] !== '' ? $r['thumb'] : $r['image']) ?>" alt="<?= e($r['title']) ?>" loading="lazy">
            <div class="dz-body">
                <b><?= e($r['title']) ?></b>
                <span class="muted"><?= e($r['category']) ?> &middot; <?= e(substr((string)$r['created_at'], 0, 10)) ?></span>
                <span><span class="pill <?= $pub ? 'on' : '' ?>"><?= $pub ? 'Published' : 'Draft' ?></span></span>
                <div class="dz-actions">
                    <a href="?edit=<?= (int)$r['id'] ?>#dzForm">Edit</a>
                    <form method="post" class="inline" onsubmit="return confirm('Delete this design permanently? This cannot be undone.')">
                        <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="link danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var form = document.getElementById('dzForm'), file = document.getElementById('image'),
        prev = document.getElementById('dzPreview'), msg = document.getElementById('dzMsg'),
        btn = document.getElementById('dzSubmit'), MAX = <?= (int)DESIGN_MAX_BYTES ?>, OK = ['image/jpeg', 'image/png', 'image/webp'];
    file.addEventListener('change', function () {
        var f = file.files[0];
        msg.textContent = '';
        if (!f) return;
        if (OK.indexOf(f.type) === -1) { msg.textContent = 'Only JPG, PNG or WebP images are allowed.'; file.value = ''; return; }
        if (f.size > MAX) { msg.textContent = 'That image is larger than 8 MB.'; file.value = ''; return; }
        prev.src = URL.createObjectURL(f); prev.hidden = false;
    });
    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.textContent = file.files.length ? 'Uploading...' : 'Saving...';
        form.classList.add('dz-busy');
    });
    window.addEventListener('pageshow', function () { btn.disabled = false; form.classList.remove('dz-busy'); });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php';
