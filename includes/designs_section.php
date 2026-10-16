<?php
// Public "Graphic Design Showcase" section. Included from index.php right after the YouTube Videos section.
require_once __DIR__ . '/designs_lib.php';
$designs = [];
try {
    $designs = $pdo->query('SELECT id,title,category,description,image,thumb,width,height,project_url FROM designs WHERE is_visible=1 ORDER BY created_at DESC, id DESC LIMIT ' . (int)DESIGN_HOME_LIMIT)->fetchAll();
} catch (Throwable $x) { $designs = []; } // table missing or DB error: simply render nothing
if (!$designs) return;
$seeMore = design_see_more_url($pdo);
?>
<section class="designs-section" id="designs">
    <div class="designs-head">
        <h2>Graphic Design <span>Showcase</span></h2>
        <p>Creative designs. Bold visuals. Ideas brought to life.</p>
    </div>

    <div class="designs-grid">
        <?php foreach ($designs as $i => $d):
            $w = (int)$d['width']; $h = (int)$d['height'];
            $ratio = ($w > 0 && $h > 0) ? max(0.5, min(2.0, $w / $h)) : 1; // keep extreme shapes from dominating the grid
            $src = $d['thumb'] !== '' ? $d['thumb'] : $d['image'];
        ?>
        <button type="button" class="design-card" aria-label="View design: <?= e($d['title']) ?>"
            data-full="<?= e($d['image']) ?>" data-title="<?= e($d['title']) ?>" data-category="<?= e($d['category']) ?>"
            data-desc="<?= e($d['description']) ?>" data-url="<?= e($d['project_url']) ?>">
            <span class="design-media" style="aspect-ratio:<?= round($ratio, 4) ?>">
                <img src="<?= e($src) ?>" alt="<?= e($d['title']) ?>" <?= $w > 0 && $h > 0 ? 'width="' . $w . '" height="' . $h . '"' : '' ?> loading="<?= $i < 2 ? 'eager' : 'lazy' ?>" decoding="async">
            </span>
            <span class="design-info">
                <span class="design-cat"><?= e($d['category']) ?></span>
                <span class="design-title"><?= e($d['title']) ?></span>
            </span>
        </button>
        <?php endforeach; ?>
    </div>

    <?php if ($seeMore !== ''): ?>
    <div class="designs-more"><a class="btn" href="<?= e($seeMore) ?>" target="_blank" rel="noopener noreferrer">See More Designs</a></div>
    <?php endif; ?>

    <div class="design-lightbox" id="designLightbox" role="dialog" aria-modal="true" aria-labelledby="dlTitle" hidden>
        <div class="dl-backdrop" data-close></div>
        <div class="dl-box">
            <button type="button" class="dl-close" data-close aria-label="Close preview">&times;</button>
            <div class="dl-imgwrap"><img id="dlImg" src="" alt=""></div>
            <div class="dl-meta">
                <span class="design-cat" id="dlCat"></span>
                <h3 id="dlTitle"></h3>
                <p id="dlDesc"></p>
                <a id="dlLink" class="btn" href="#" target="_blank" rel="noopener noreferrer" hidden>View project</a>
            </div>
        </div>
    </div>
</section>
<script>
(function () {
    var lb = document.getElementById('designLightbox');
    if (!lb) return;
    var img = document.getElementById('dlImg'), cat = document.getElementById('dlCat'), ttl = document.getElementById('dlTitle'),
        desc = document.getElementById('dlDesc'), link = document.getElementById('dlLink'), closeBtn = lb.querySelector('.dl-close'), last = null;
    function open(card) {
        last = card;
        img.src = card.dataset.full; img.alt = card.dataset.title;
        cat.textContent = card.dataset.category; ttl.textContent = card.dataset.title;
        desc.textContent = card.dataset.desc; desc.hidden = !card.dataset.desc;
        if (card.dataset.url) { link.href = card.dataset.url; link.hidden = false; } else { link.hidden = true; }
        lb.hidden = false; document.body.style.overflow = 'hidden';
        requestAnimationFrame(function () { lb.classList.add('open'); closeBtn.focus(); });
    }
    function close() {
        lb.classList.remove('open'); document.body.style.overflow = '';
        setTimeout(function () { lb.hidden = true; img.src = ''; }, 200);
        if (last) last.focus();
    }
    document.querySelectorAll('.design-card').forEach(function (c) { c.addEventListener('click', function () { open(c); }); });
    lb.addEventListener('click', function (e) { if (e.target.hasAttribute('data-close')) close(); });
    document.addEventListener('keydown', function (e) {
        if (lb.hidden) return;
        if (e.key === 'Escape') { close(); return; }
        if (e.key === 'Tab') { // keep focus inside the dialog
            var f = Array.prototype.filter.call(lb.querySelectorAll('button, a[href]'), function (el) { return !el.hidden; });
            if (!f.length) return;
            var first = f[0], lastEl = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); lastEl.focus(); }
            else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); first.focus(); }
        }
    });
})();
</script>
