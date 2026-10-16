<?php require __DIR__ . '/_header.php';
$dir = __DIR__ . '/../uploads'; $S = site_settings($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $v = [];
        foreach (['site_title' => 100, 'about_title' => 100, 'about_heading' => 150, 'about_text' => 3000, 'skills_title' => 100, 'skills_desc' => 3000] as $k => $max)
            $v[$k] = mb_substr(trim($_POST[$k] ?? ''), 0, $max);
        $lines = array_slice(array_values(array_filter(array_map(fn($l) => mb_substr(trim($l), 0, 60), explode("\n", $_POST['hero_strings'] ?? '')))), 0, 10);
        $v['hero_strings'] = implode("\n", $lines ?: ['Sineth Tharaka']);
        foreach (['github', 'linkedin', 'whatsapp'] as $k) $v[$k] = safe_url($_POST[$k] ?? '');
        foreach (['hero_banner', 'about_image'] as $k) if ($i = save_image($_FILES[$k] ?? ['error' => UPLOAD_ERR_NO_FILE], $dir)) $v[$k] = $i;
        if ($c = save_cv($_FILES['cv_file'] ?? ['error' => UPLOAD_ERR_NO_FILE], $dir)) $v['cv_file'] = $c;
        if (isset($_POST['remove_cv'])) $v['cv_file'] = '';
        $st = $pdo->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');
        foreach ($v as $k => $val) $st->execute([$k, $val]);
        flash('Site content saved.');
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    header('Location: content.php'); exit;
}
$t = fn($k, $l, $max = 150) => '<label for="' . $k . '">' . $l . '</label><input id="' . $k . '" name="' . $k . '" maxlength="' . $max . '" value="' . e($S[$k]) . '">';
$a = fn($k, $l, $r = 4) => '<label for="' . $k . '">' . $l . '</label><textarea id="' . $k . '" name="' . $k . '" rows="' . $r . '">' . e($S[$k]) . '</textarea>';
$f = fn($k, $l, $acc = 'image/jpeg,image/png,image/webp') => '<label for="' . $k . '">' . $l . '</label><input id="' . $k . '" type="file" name="' . $k . '" accept="' . $acc . '">';
?><h1>Site content</h1>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="card"><h2>Hero</h2><?= $f('hero_banner', 'Banner image') ?><img class="thumb" src="../<?= e($S['hero_banner']) ?>" alt="">
<?= $a('hero_strings', 'Typing texts after "Hi, I\'m" (one per line, max 10)', 6) ?></div>
<div class="card"><h2>Behind the Code (About)</h2><?= $t('about_title', 'Section title') ?><?= $t('about_heading', 'Heading (last word is shown in green)') ?>
<?= $a('about_text', 'Paragraph', 6) ?><?= $f('about_image', 'About image') ?><img class="thumb" src="../<?= e($S['about_image']) ?>" alt="">
<?= $f('cv_file', 'CV (PDF, max 5 MB)', 'application/pdf') ?>
<?php if ($S['cv_file']): ?><p><a href="../<?= e($S['cv_file']) ?>" target="_blank">Current CV</a></p><label class="chk"><input type="checkbox" name="remove_cv"> Remove current CV</label><?php endif; ?></div>
<div class="card"><h2>Experience And Skill section</h2><?= $t('skills_title', 'Section title') ?><?= $a('skills_desc', 'Description', 5) ?>
<p class="muted">Manage the cards and skill circles under Experience and Skills in the sidebar.</p></div>
<div class="card"><h2>Website &amp; social links</h2><?= $t('site_title', 'Website title (browser tab)', 100) ?><?= $t('github', 'GitHub URL', 255) ?><?= $t('linkedin', 'LinkedIn URL', 255) ?><?= $t('whatsapp', 'WhatsApp URL', 255) ?></div>
<button class="btn">Save all</button></form>
<?php require __DIR__ . '/_footer.php';
