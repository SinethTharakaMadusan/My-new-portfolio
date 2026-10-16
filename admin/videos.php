<?php require __DIR__ . '/_header.php';
$dir = __DIR__ . '/../uploads';
const VIDEO_MAX_THUMB = 3 * 1024 * 1024; // 3 MB, same limit shown on the Projects page

/** Returns ['id' => 11-char id, 'url' => canonical url] for a supported YouTube URL, otherwise null. */
function yt_parse(string $url): ?array {
    $url = trim($url);
    if ($url === '' || strlen($url) > 255 || !preg_match('#^https?://#i', $url)) return null;
    $p = parse_url($url);
    if (!$p || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return null;
    $host = strtolower($p['host']);
    if (strncmp($host, 'www.', 4) === 0) $host = substr($host, 4);
    $path = $p['path'] ?? ''; $id = null; $short = false;
    if ($host === 'youtu.be') {
        $id = explode('/', trim($path, '/'))[0];
    } elseif ($host === 'youtube.com' || $host === 'm.youtube.com') {
        if (preg_match('#^/shorts/([^/]+)/?$#', $path, $m)) { $id = $m[1]; $short = true; }
        elseif (rtrim($path, '/') === '/watch') { parse_str($p['query'] ?? '', $q); $id = is_string($q['v'] ?? null) ? $q['v'] : null; }
    }
    if ($id === null || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) return null;
    return ['id' => $id, 'url' => 'https://www.youtube.com/' . ($short ? 'shorts/' . $id : 'watch?v=' . $id)];
}

/** Extra server-side checks on an uploaded thumbnail, then the existing save_image() does the saving. Returns path or null. */
function video_save_thumb($file, string $dir): ?string {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Thumbnail upload failed. Please try again.');
    if ($file['size'] > VIDEO_MAX_THUMB) throw new RuntimeException('Thumbnail is larger than 3 MB.');
    if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Invalid upload.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !@getimagesize($file['tmp_name']))
        throw new RuntimeException('Thumbnail must be a real JPG, PNG or WebP image.');
    $saved = save_image($file, $dir);
    return $saved ?: null;
}

/** Deletes an uploaded thumbnail only if it lives inside /uploads and no other record still uses it. */
function video_thumb_unlink(PDO $pdo, string $rel, int $exceptVideoId = 0): void {
    if ($rel === '') return;
    $base = realpath(__DIR__ . '/../uploads'); $file = realpath(__DIR__ . '/../' . $rel);
    if (!$base || !$file || !is_file($file) || strncmp($file, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) return;
    try {
        $s = $pdo->prepare('SELECT (SELECT COUNT(*) FROM videos WHERE thumbnail=? AND id<>?) + (SELECT COUNT(*) FROM projects WHERE thumbnail=?) + (SELECT COUNT(*) FROM settings WHERE v=?)');
        $s->execute([$rel, $exceptVideoId, $rel, $rel]);
        if ((int)$s->fetchColumn() > 0) return;
        @unlink($file);
    } catch (Throwable $x) { /* keep the file if unsure */ }
}

$run = function (string $sql, array $args = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    if (!$s || !$s->execute($args)) throw new RuntimeException('Database error. Has database/videos.sql been imported?');
    return $s;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $id = (int)($_POST['id'] ?? 0); $do = $_POST['do'] ?? 'save'; $back = 'videos.php'; $newImg = null;
    try {
        if ($do === 'delete') {
            $old = $run('SELECT thumbnail FROM videos WHERE id=?', [$id])->fetchColumn();
            $run('DELETE FROM videos WHERE id=?', [$id]);
            if ($old) video_thumb_unlink($pdo, (string)$old, $id);
            flash('Video deleted.');
        } elseif ($do === 'toggle') {
            $run('UPDATE videos SET is_visible = 1 - is_visible WHERE id=?', [$id]);
            flash('Status updated.');
        } elseif ($do === 'move') {
            $ids = array_map('intval', $run('SELECT id FROM videos ORDER BY sort_order ASC, id DESC')->fetchAll(PDO::FETCH_COLUMN));
            $i = array_search($id, $ids, true); $j = ($i === false) ? -1 : $i + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
            if ($i !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                $pdo->beginTransaction();
                try { foreach ($ids as $k => $vid) $run('UPDATE videos SET sort_order=? WHERE id=?', [($k + 1) * 10, $vid]); $pdo->commit(); }
                catch (Throwable $x) { $pdo->rollBack(); throw $x; }
            }
        } else {
            $back = 'videos.php?action=' . ($id ? 'edit&id=' . $id : 'new');
            $_SESSION['video_old'] = ['id' => $id, 'title' => trim($_POST['title'] ?? ''), 'description' => trim($_POST['description'] ?? ''),
                'youtube_url' => trim($_POST['youtube_url'] ?? ''), 'sort_order' => trim($_POST['sort_order'] ?? ''), 'is_visible' => isset($_POST['is_visible']) ? 1 : 0];
            $o = $_SESSION['video_old'];
            if ($o['title'] === '' || mb_strlen($o['title']) > 150) throw new RuntimeException('Title is required (max 150 characters).');
            if (mb_strlen($o['description']) > 500) throw new RuntimeException('Description is too long (max 500 characters).');
            $yt = yt_parse($o['youtube_url']);
            if (!$yt) throw new RuntimeException('Please enter a valid YouTube link (youtube.com/watch?v=..., youtu.be/... or youtube.com/shorts/...).');
            if ($o['sort_order'] !== '' && !preg_match('/^\d{1,5}$/', $o['sort_order'])) throw new RuntimeException('Display order must be a whole number between 0 and 99999.');
            $old = null;
            if ($id) { $old = $run('SELECT * FROM videos WHERE id=?', [$id])->fetch(); if (!$old) throw new RuntimeException('Video not found.'); }
            $newImg = video_save_thumb($_FILES['thumbnail'] ?? null, $dir);
            $thumb = $newImg ?? ($old ? ((isset($_POST['remove_thumb']) ? '' : $old['thumbnail'])) : '');
            if ($o['sort_order'] !== '') $order = (int)$o['sort_order'];
            elseif ($old) $order = (int)$old['sort_order'];
            else $order = (int)$run('SELECT COALESCE(MAX(sort_order),0) + 10 FROM videos')->fetchColumn();
            if ($id) {
                $run('UPDATE videos SET title=?,description=?,youtube_url=?,youtube_id=?,thumbnail=?,sort_order=?,is_visible=? WHERE id=?',
                    [$o['title'], $o['description'], $yt['url'], $yt['id'], $thumb, $order, $o['is_visible'], $id]);
                if ($old['thumbnail'] !== '' && $old['thumbnail'] !== $thumb) video_thumb_unlink($pdo, (string)$old['thumbnail'], $id);
                flash('Video updated.');
            } else {
                $run('INSERT INTO videos (title,description,youtube_url,youtube_id,thumbnail,sort_order,is_visible) VALUES (?,?,?,?,?,?,?)',
                    [$o['title'], $o['description'], $yt['url'], $yt['id'], $thumb, $order, $o['is_visible']]);
                flash('Video added.');
            }
            unset($_SESSION['video_old']);
        }
    } catch (Throwable $x) {
        if ($newImg) video_thumb_unlink($pdo, $newImg, 0); // don't leave an orphan upload behind
        flash($x instanceof RuntimeException ? $x->getMessage() : 'Something went wrong.', 'err');
    }
    header('Location: ' . $back); exit;
}

$action = $_GET['action'] ?? '';
$v = ['id' => 0, 'title' => '', 'description' => '', 'youtube_url' => '', 'youtube_id' => '', 'thumbnail' => '', 'sort_order' => '', 'is_visible' => 1];
if ($action === 'new' || $action === 'edit'):
    if ($action === 'edit') {
        try { $s = $pdo->prepare('SELECT * FROM videos WHERE id=?'); $s->execute([(int)($_GET['id'] ?? 0)]); $v = $s->fetch() ?: $v; } catch (Throwable $x) {}
    }
    if (isset($_SESSION['video_old'])) { $v = array_merge($v, $_SESSION['video_old']); unset($_SESSION['video_old']); }
    $yp = yt_parse((string)$v['youtube_url']);
    $prevSrc = $v['thumbnail'] ? '../' . $v['thumbnail'] : ($yp ? 'https://i.ytimg.com/vi/' . $yp['id'] . '/hqdefault.jpg' : '');
?>
<h1><?= $v['id'] ? 'Edit' : 'New' ?> video</h1>
<form method="post" enctype="multipart/form-data" class="card" id="videoForm"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
<label for="vt">Video title *</label><input id="vt" name="title" required maxlength="150" value="<?= e($v['title']) ?>">
<label for="vu">YouTube video URL *</label><input id="vu" name="youtube_url" required maxlength="255" placeholder="https://www.youtube.com/watch?v=..." value="<?= e($v['youtube_url']) ?>">
<label for="vd">Short description (optional, max 500)</label><textarea id="vd" name="description" rows="3" maxlength="500"><?= e($v['description']) ?></textarea>
<label for="vo">Display order (optional, lower numbers show first; leave empty to add at the end)</label><input id="vo" name="sort_order" type="number" min="0" max="99999" value="<?= e((string)$v['sort_order']) ?>">
<label for="vi">Custom thumbnail (optional, JPG/PNG/WebP, max 3 MB, otherwise the YouTube thumbnail is used)</label><input id="vi" type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp">
<img id="vPrev" class="thumb" alt="Thumbnail preview" src="<?= e($prevSrc) ?>" style="max-width:320px;width:100%;aspect-ratio:16/9;object-fit:cover;<?= $prevSrc ? '' : 'display:none' ?>">
<?php if ($v['thumbnail']): ?><label class="chk"><input type="checkbox" name="remove_thumb" id="vRemove"> Remove custom thumbnail (use the YouTube thumbnail)</label><?php endif; ?>
<label class="chk"><input type="checkbox" name="is_visible" <?= $v['is_visible'] ? 'checked' : '' ?>> Published (visible on public site)</label>
<button class="btn">Save</button> <a href="videos.php">Cancel</a></form>
<script>
(function () {
    var url = document.getElementById('vu'), file = document.getElementById('vi'), img = document.getElementById('vPrev'),
        hasCustom = <?= $v['thumbnail'] ? 'true' : 'false' ?>, objUrl = null;
    function ytId(s) {
        var m = s.trim().match(/^https?:\/\/(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:[^#]*&)?v=|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})(?:[&?\/#]|$)/i);
        return m ? m[1] : null;
    }
    url.addEventListener('input', function () {
        if (hasCustom || (file.files && file.files.length)) return;
        var id = ytId(url.value);
        if (id) { img.src = 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg'; img.style.display = 'block'; } else { img.style.display = 'none'; }
    });
    file.addEventListener('change', function () {
        if (objUrl) { URL.revokeObjectURL(objUrl); objUrl = null; }
        if (file.files && file.files[0]) { objUrl = URL.createObjectURL(file.files[0]); img.src = objUrl; img.style.display = 'block'; }
    });
    var form = document.getElementById('videoForm'), btn = form.querySelector('.btn');
    form.addEventListener('submit', function () { btn.disabled = true; });
    window.addEventListener('pageshow', function () { btn.disabled = false; });
})();
</script>
<?php else:
    $rows = [];
    try { $rows = $pdo->query('SELECT * FROM videos ORDER BY sort_order ASC, id DESC')->fetchAll(); }
    catch (Throwable $x) { echo '<div class="alert err">Could not read the videos table. Import database/videos.sql first.</div>'; }
?>
<h1>YouTube Videos <a class="btn sm" href="?action=new">+ Add</a></h1>
<div class="card tbl"><table><tr><th></th><th>Title</th><th>YouTube ID</th><th>Order</th><th>Status</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">No videos yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): $src = $r['thumbnail'] ? '../' . $r['thumbnail'] : 'https://i.ytimg.com/vi/' . $r['youtube_id'] . '/mqdefault.jpg'; ?>
<tr><td><img class="mini" src="<?= e($src) ?>" alt=""></td>
<td><?= e($r['title']) ?></td>
<td><a href="https://www.youtube.com/watch?v=<?= e($r['youtube_id']) ?>" target="_blank" rel="noopener noreferrer"><?= e($r['youtube_id']) ?></a></td>
<td><?= (int)$r['sort_order'] ?>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="move"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link" name="dir" value="up" aria-label="Move up" title="Move up">&uarr;</button><button class="link" name="dir" value="down" aria-label="Move down" title="Move down">&darr;</button></form></td>
<td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link"><span class="pill <?= $r['is_visible'] ? 'on' : '' ?>"><?= $r['is_visible'] ? 'Published' : 'Draft' ?></span></button></form></td>
<td><a href="?action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
<form method="post" class="inline" onsubmit="return confirm('Delete this video permanently?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="link danger">Delete</button></form></td></tr>
<?php endforeach; ?></table></div>
<?php endif; require __DIR__ . '/_footer.php';
