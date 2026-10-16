<?php
// Public "Graphic Designs" scrolling carousel section.
// Included from index.php right after the YouTube Videos section.
// Relies on the existing $pdo and e() from bootstrap.php.
if (!isset($pdo)) { http_response_code(403); exit; }

const GC_LIMIT       = 20;  // max cards to pull from DB
const GC_SECS_PER_CARD = 5; // scroll speed (higher = slower)

$gcRows = [];
try {
    $r = $pdo->query('SELECT id, title, category, image, link_url FROM graphic_carousel
                      WHERE is_visible=1 ORDER BY sort_order ASC, id DESC LIMIT ' . (int)GC_LIMIT);
    $gcRows = $r ? $r->fetchAll() : [];
} catch (Throwable $x) {
    $gcRows = []; // table not yet created: silently skip
}

if (!$gcRows) return; // nothing to show - skip the whole section

// 4+ cards: animate. Fewer: show centred and still (a loop of 1-3 looks broken).
$gcAnimate = count($gcRows) >= 4;
$gcHalf    = $gcRows;
while ($gcAnimate && count($gcHalf) < 8)
    $gcHalf = array_merge($gcHalf, $gcRows);
$gcDuration = max(count($gcHalf), 1) * GC_SECS_PER_CARD;
?>
    <section class="projects gc-section" id="graphic-designs">
        <div class="project-block gc-head-block">
            <h2>Graphic <span>Designs</span></h2>
        </div>

        <div class="gc-marquee">
            <div class="gc-track<?= $gcAnimate ? '' : ' static' ?>"<?= $gcAnimate ? ' style="--gc-duration:' . (int)$gcDuration . 's"' : '' ?>>
                <?php
                $renderGcCard = function (array $c, bool $dup) {
                    $href   = trim($c['link_url'] ?? '');
                    $hasUrl = ($href !== '' && filter_var($href, FILTER_VALIDATE_URL));
                    $tag    = $hasUrl ? 'a' : 'div';
                    $attrs  = $hasUrl
                        ? 'href="' . e($href) . '" target="_blank" rel="noopener noreferrer"'
                        : '';
                    if ($dup) $attrs .= ' aria-hidden="true" tabindex="-1"';
                ?>
                <<?= $tag ?> class="gc-card"<?= $attrs ? ' ' . $attrs : '' ?>>
                    <div class="gc-img-wrap">
                        <img src="<?= e($c['image']) ?>" alt="<?= $dup ? '' : e($c['title']) ?>" loading="lazy">
                        <div class="gc-overlay">
                            <?php if ($c['category'] !== ''): ?>
                            <span class="gc-cat"><?= e($c['category']) ?></span>
                            <?php endif; ?>
                            <span class="gc-title"><?= e($c['title']) ?></span>
                            <?php if ($hasUrl): ?>
                            <span class="gc-cta"><i class="fa-solid fa-arrow-up-right-from-square"></i> View</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </<?= $tag ?>>
                <?php }; ?>

                <?php foreach ($gcHalf as $c) $renderGcCard($c, false); ?>
                <?php if ($gcAnimate) foreach ($gcHalf as $c) $renderGcCard($c, true); ?>
            </div>
        </div>
    </section>
