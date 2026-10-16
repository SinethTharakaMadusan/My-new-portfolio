<?php require __DIR__ . '/_header.php';

const GC_MAX_IMG    = 8 * 1024 * 1024; // 8 MB per image
const GC_UPLOAD_DIR = 'uploads/graphic_carousel';

/** Validates & saves an uploaded image; returns its relative path. Throws on error. */
function gc_save_image(array $f): string
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)
        throw new RuntimeException('Image is too large (max 8 MB).');
    if ($err !== UPLOAD_ERR_OK)
        throw new RuntimeException('Image upload failed. Please try again.');
    if (($f['size'] ?? 0) > GC_MAX_IMG)
        throw new RuntimeException('Image must be under 8 MB.');
    if (!is_uploaded_file($f['tmp_name'] ?? ''))
        throw new RuntimeException('Invalid upload.');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true))
        throw new RuntimeException('Only real JPG, PNG or WebP images are allowed.');
    $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $ext  = $exts[$info[2]];
    $dir  = __DIR__ . '/../' . GC_UPLOAD_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true))
        throw new RuntimeException('Could not create the upload folder.');
    $name = 'gc_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name))
        throw new RuntimeException('Could not save the uploaded image.');
    return GC_UPLOAD_DIR . '/' . $name;
}

/** Deletes an image from uploads/ only when nothing else still references it. */
function gc_unlink(PDO $pdo, string $rel, int $exceptId = 0): void
{
    if ($rel === '') return;
    $base = realpath(__DIR__ . '/../uploads');
    $abs  = realpath(__DIR__ . '/../' . $rel);
    if (!$base || !$abs || !is_file($abs) ||
        strncmp($abs, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) return;
    try {
        $s = $pdo->prepare('SELECT COUNT(*) FROM graphic_carousel WHERE image=? AND id<>?');
        $s->execute([$rel, $exceptId]);
        if ((int)$s->fetchColumn() > 0) return; // still referenced
    } catch (Throwable $x) { return; }
    @unlink($abs);
}

$run = function (string $sql, array $args = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    if (!$s || !$s->execute($args))
        throw new RuntimeException('Database error. Has database/graphic_carousel.sql been imported?');
    return $s;
};

/* ── POST handler ──────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id  = (int)($_POST['id'] ?? 0);
    $do  = $_POST['do'] ?? 'save';
    $back = 'graphic_carousel.php';
    $newImg = '';
    try {
        if ($do === 'delete') {
            $old = $run('SELECT image FROM graphic_carousel WHERE id=?', [$id])->fetchColumn();
            $run('DELETE FROM graphic_carousel WHERE id=?', [$id]);
            if ($old) gc_unlink($pdo, (string)$old, $id);
            flash('Design deleted.');

        } elseif ($do === 'toggle') {
            $run('UPDATE graphic_carousel SET is_visible = 1 - is_visible WHERE id=?', [$id]);
            flash('Visibility updated.');

        } elseif ($do === 'move') {
            $ids = array_map('intval',
                $run('SELECT id FROM graphic_carousel ORDER BY sort_order ASC, id DESC')
                ->fetchAll(PDO::FETCH_COLUMN));
            $i = array_search($id, $ids, true);
            $j = ($i === false) ? -1 : $i + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
            if ($i !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                $pdo->beginTransaction();
                try {
                    foreach ($ids as $k => $vid)
                        $run('UPDATE graphic_carousel SET sort_order=? WHERE id=?', [($k + 1) * 10, $vid]);
                    $pdo->commit();
                } catch (Throwable $x) { $pdo->rollBack(); throw $x; }
            }

        } else { // save (add / edit)
            $back = 'graphic_carousel.php?action=' . ($id ? 'edit&id=' . $id : 'new');
            $title    = trim($_POST['title']    ?? '');
            $category = trim($_POST['category'] ?? '');
            $link_url = trim($_POST['link_url'] ?? '');
            $visible  = isset($_POST['is_visible']) ? 1 : 0;
            $sort     = trim($_POST['sort_order'] ?? '');

            $_SESSION['gc_old'] = compact('id', 'title', 'category', 'link_url', 'visible', 'sort');

            if ($title === '' || mb_strlen($title) > 150)
                throw new RuntimeException('Title is required (max 150 characters).');
            if (mb_strlen($category) > 80)
                throw new RuntimeException('Category is too long (max 80 characters).');
            if ($link_url !== '' && !filter_var($link_url, FILTER_VALIDATE_URL))
                throw new RuntimeException('Link URL must be a valid http(s) address.');
            if ($sort !== '' && !preg_match('/^\d{1,5}$/', $sort))
                throw new RuntimeException('Display order must be a whole number 0–99999.');

            $old = null;
            if ($id) {
                $old = $run('SELECT * FROM graphic_carousel WHERE id=?', [$id])->fetch();
                if (!$old) throw new RuntimeException('Record not found.');
            }

            $hasFile = ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if (!$old && !$hasFile) throw new RuntimeException('Please choose a design image.');

            $image = $old['image'] ?? '';
            if ($hasFile) {
                $newImg = gc_save_image($_FILES['image']);
                $image  = $newImg;
            } elseif (isset($_POST['remove_image']) && $old) {
                gc_unlink($pdo, (string)$old['image'], $id);
                $image = '';
            }

            $order = $sort !== '' ? (int)$sort
                : ($old ? (int)$old['sort_order']
                    : (int)$run('SELECT COALESCE(MAX(sort_order),0)+10 FROM graphic_carousel')->fetchColumn());

            if ($old) {
                $run('UPDATE graphic_carousel SET title=?,category=?,image=?,link_url=?,sort_order=?,is_visible=? WHERE id=?',
                    [$title, $category, $image, $link_url, $order, $visible, $id]);
                if ($hasFile && $old['image'] !== '' && $old['image'] !== $newImg)
                    gc_unlink($pdo, (string)$old['image'], $id);
                flash('Design updated.');
            } else {
                $run('INSERT INTO graphic_carousel (title,category,image,link_url,sort_order,is_visible) VALUES (?,?,?,?,?,?)',
                    [$title, $category, $image, $link_url, $order, $visible]);
                flash('Design added.');
            }
            unset($_SESSION['gc_old']);
        }
    } catch (Throwable $x) {
        if ($newImg) { $a = realpath(__DIR__ . '/../' . $newImg); if ($a && is_file($a)) @unlink($a); }
        flash($x instanceof RuntimeException ? $x->getMessage() : 'Something went wrong.', 'err');
    }
    header('Location: ' . $back); exit;
}

/* ── GET: form (new / edit) ─────────────────────────────────────────────── */
$action = $_GET['action'] ?? '';
if ($action === 'new' || $action === 'edit'):
    $d = ['id' => 0, 'title' => '', 'category' => '', 'image' => '', 'link_url' => '', 'sort_order' => '', 'is_visible' => 1];
    if ($action === 'edit') {
        try {
            $s = $pdo->prepare('SELECT * FROM graphic_carousel WHERE id=?');
            $s->execute([(int)($_GET['id'] ?? 0)]);
            $d = $s->fetch() ?: $d;
        } catch (Throwable $x) {}
    }
    if (isset($_SESSION['gc_old'])) {
        $o = $_SESSION['gc_old'];
        $d = array_merge($d, [
            'title'      => $o['title']    ?? $d['title'],
            'category'   => $o['category'] ?? $d['category'],
            'link_url'   => $o['link_url'] ?? $d['link_url'],
            'sort_order' => $o['sort']     ?? $d['sort_order'],
            'is_visible' => $o['visible']  ?? $d['is_visible'],
        ]);
        unset($_SESSION['gc_old']);
    }
    $prevSrc = $d['image'] ? '../' . $d['image'] : '';
?>
<h1><?= $d['id'] ? 'Edit' : 'New' ?> Graphic Design</h1>
<form method="post" enctype="multipart/form-data" class="card" id="gcForm">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">

    <label for="gc_title">Title *</label>
    <input id="gc_title" name="title" required maxlength="150" value="<?= e($d['title']) ?>">

    <label for="gc_cat">Category (optional, e.g. "YouTube Thumbnail")</label>
    <input id="gc_cat" name="category" maxlength="80" value="<?= e($d['category']) ?>">

    <label for="gc_img">Design image <?= $d['id'] ? '(leave empty to keep current)' : '*' ?> &mdash; JPG, PNG or WebP, max 8 MB</label>
    <input id="gc_img" type="file" name="image" accept="image/jpeg,image/png,image/webp" <?= $d['id'] ? '' : 'required' ?>>
    <?php if ($prevSrc): ?>
    <img id="gcPrev" class="thumb" alt="Preview" src="<?= e($prevSrc) ?>" style="max-width:320px;width:100%;height:auto;margin-top:8px">
    <label class="chk"><input type="checkbox" name="remove_image"> Remove current image</label>
    <?php else: ?>
    <img id="gcPrev" class="thumb" alt="Preview" src="" style="max-width:320px;width:100%;height:auto;margin-top:8px;display:none">
    <?php endif; ?>

    <label for="gc_link">Link URL (click destination – leave empty for no link)</label>
    <input id="gc_link" name="link_url" type="url" maxlength="255" placeholder="https://..." value="<?= e($d['link_url']) ?>">

    <label for="gc_order">Display order (lower = first; leave empty to append at end)</label>
    <input id="gc_order" name="sort_order" type="number" min="0" max="99999" value="<?= e((string)$d['sort_order']) ?>">

    <label class="chk">
        <input type="checkbox" name="is_visible" <?= $d['is_visible'] ? 'checked' : '' ?>>
        Published (visible on public site)
    </label>

    <button class="btn" id="gcSave">Save</button>
    <a href="graphic_carousel.php">Cancel</a>
    <span id="gcBusy" class="muted" style="display:none"> Uploading, please wait&hellip;</span>
</form>
<script>
(function () {
    var f = document.getElementById('gc_img'),
        img = document.getElementById('gcPrev'),
        form = document.getElementById('gcForm'),
        btn = document.getElementById('gcSave'),
        busy = document.getElementById('gcBusy'),
        obj = null;
    f.addEventListener('change', function () {
        if (obj) { URL.revokeObjectURL(obj); obj = null; }
        if (f.files && f.files[0]) {
            if (f.files[0].size > 8 * 1024 * 1024) { alert('Image is too large (max 8 MB).'); f.value = ''; return; }
            obj = URL.createObjectURL(f.files[0]);
            img.src = obj; img.style.display = 'block';
        }
    });
    form.addEventListener('submit', function () { btn.disabled = true; busy.style.display = 'inline'; });
    window.addEventListener('pageshow', function () { btn.disabled = false; busy.style.display = 'none'; });
})();
</script>

<?php else:
    /* ── GET: list view ─────────────────────────────────────────────────── */
    $rows = [];
    try {
        $rows = $pdo->query('SELECT * FROM graphic_carousel ORDER BY sort_order ASC, id DESC')->fetchAll();
    } catch (Throwable $x) {
        echo '<div class="alert err">Could not read the graphic_carousel table. Import database/graphic_carousel.sql first.</div>';
    }
?>
<h1>Graphic Design Carousel <a class="btn sm" href="?action=new">+ Add Design</a></h1>

<p class="muted">These designs appear as a right-to-left scrolling carousel on the portfolio home page, below the YouTube Videos section.</p>

<div class="card tbl"><table>
<tr><th></th><th>Title</th><th>Category</th><th>Order</th><th>Link</th><th>Status</th><th></th></tr>
<?php if (!$rows): ?>
<tr><td colspan="7" class="muted">No designs yet. Click "+ Add Design" to upload your first one.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr>
    <td><img class="mini" src="../<?= e($r['image']) ?>" alt="" style="width:70px;height:50px;object-fit:cover;border-radius:6px;background:#111"></td>
    <td><strong><?= e($r['title']) ?></strong></td>
    <td class="muted"><?= e($r['category']) ?></td>
    <td>
        <?= (int)$r['sort_order'] ?>
        <form method="post" class="inline"><?= csrf_field() ?>
            <input type="hidden" name="do" value="move">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="link" name="dir" value="up" title="Move up">&uarr;</button>
            <button class="link" name="dir" value="down" title="Move down">&darr;</button>
        </form>
    </td>
    <td>
        <?php if ($r['link_url']): ?>
        <a href="<?= e($r['link_url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px">
            <?= e(parse_url($r['link_url'], PHP_URL_HOST) ?: $r['link_url']) ?>
        </a>
        <?php else: ?>
        <span class="muted">—</span>
        <?php endif; ?>
    </td>
    <td>
        <form method="post" class="inline"><?= csrf_field() ?>
            <input type="hidden" name="do" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="link"><span class="pill <?= $r['is_visible'] ? 'on' : '' ?>"><?= $r['is_visible'] ? 'Published' : 'Draft' ?></span></button>
        </form>
    </td>
    <td>
        <a href="?action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Delete this design permanently?')"><?= csrf_field() ?>
            <input type="hidden" name="do" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="link danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table></div>
<?php endif; require __DIR__ . '/_footer.php';
