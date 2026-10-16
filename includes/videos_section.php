<?php
// Public "Latest Videos" section: the 7 newest published videos in a slow right-to-left scrolling strip.
// Included from index.php; relies on the existing $pdo and e() from bootstrap.php.
if (!isset($pdo)) { http_response_code(403); exit; }

const VIDEOS_LIMIT = 7;         // how many of the newest published videos to show
const VIDEOS_SECS_PER_CARD = 6; // scroll speed: seconds per card (higher = slower)

$videoRows = [];
try {
    // newest 7 published videos (by id), then shown in your display-order
    $r = $pdo->query('SELECT * FROM (SELECT id, title, description, youtube_url, youtube_id, thumbnail, sort_order
                      FROM videos WHERE is_visible=1 ORDER BY id DESC LIMIT ' . (int)VIDEOS_LIMIT . ') latest
                      ORDER BY sort_order ASC, id DESC');
    $videoRows = $r ? $r->fetchAll() : [];
} catch (Throwable $x) {
    $videoRows = []; // e.g. table not created yet: never break the rest of the page
}

$cards = [];
foreach ($videoRows as $video) {
    $ytId = (string)$video['youtube_id'];
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $ytId)) continue;
    $custom = (string)($video['thumbnail'] ?? '');
    // open the exact saved link, but only if it is still a canonical YouTube URL for this same video ID
    $link = (string)($video['youtube_url'] ?? '');
    if (!preg_match('#^https://www\.youtube\.com/(watch\?v=|shorts/)' . preg_quote($ytId, '#') . '$#', $link))
        $link = 'https://www.youtube.com/watch?v=' . $ytId;
    $cards[] = [
        'title' => (string)$video['title'],
        'desc' => trim((string)($video['description'] ?? '')),
        'link' => $link,
        'thumb' => $custom !== '' ? $custom : 'https://i.ytimg.com/vi/' . $ytId . '/hqdefault.jpg',
        'fallback' => $custom !== '' ? '' : 'https://i.ytimg.com/vi/' . $ytId . '/mqdefault.jpg',
    ];
}

// 4+ videos: scroll. Fewer: show them centered and still, because a loop of 1-3 cards looks broken.
$animate = count($cards) >= 4;
$half = $cards;
while ($animate && count($half) < 8) $half = array_merge($half, $cards); // each half must be wider than the screen
$duration = max(count($half), 1) * VIDEOS_SECS_PER_CARD;

$renderCard = function (array $c, bool $dup) {
    $onerror = $c['fallback'] !== '' ? ' onerror="this.onerror=null;this.src=\'' . e($c['fallback']) . '\'"' : '';
    ?>
                <a class="video-card" href="<?= e($c['link']) ?>" target="_blank" rel="noopener noreferrer"
                   <?= $dup ? 'aria-hidden="true" tabindex="-1"' : 'aria-label="Watch ' . e($c['title']) . ' on YouTube"' ?>>
                    <div class="video-thumb">
                        <img src="<?= e($c['thumb']) ?>" alt="<?= $dup ? '' : e($c['title']) ?>" loading="lazy"<?= $onerror ?>>
                        <span class="video-play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                    </div>
                    <h3><?= e($c['title']) ?></h3>
                    <?php if ($c['desc'] !== ''): ?><p><?= e($c['desc']) ?></p><?php endif; ?>
                    <span class="video-watch"><i class="fa-brands fa-youtube"></i> Watch on YouTube</span>
                </a>
    <?php
};
?>
    <section class="projects videos-section" id="videos">
        <div class="project-block">
            <h2>Latest <span>Videos</span></h2>
        </div>
        <?php if (!$cards): ?>
        <p class="video-empty">No videos published yet. Check back soon.</p>
        <?php else: ?>
        <div class="video-marquee">
            <div class="video-track<?= $animate ? '' : ' static' ?>"<?= $animate ? ' style="--video-duration:' . (int)$duration . 's"' : '' ?>>
                <?php foreach ($half as $c) $renderCard($c, false); ?>
                <?php if ($animate) foreach ($half as $c) $renderCard($c, true); ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
