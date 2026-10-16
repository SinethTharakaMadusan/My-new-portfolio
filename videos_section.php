<?php
// Public "Latest Videos" section. Included from index.php; relies on the existing $pdo and e() from bootstrap.php.
if (!isset($pdo)) { http_response_code(403); exit; }

$videos = [];
try {
    $r = $pdo->query('SELECT id, title, description, youtube_url, youtube_id, thumbnail FROM videos WHERE is_visible=1 ORDER BY sort_order ASC, id DESC');
    $videos = $r ? $r->fetchAll() : [];
} catch (Throwable $x) {
    $videos = []; // e.g. migration not applied yet: never break the rest of the page
}
?>
    <section class="projects videos-section" id="videos">
        <div class="project-block">
            <h2>Latest <span>Videos</span></h2>
        </div>
        <?php if (!$videos): ?>
        <p class="video-empty">No videos published yet. Check back soon.</p>
        <?php else: ?>
        <div class="project-container video-grid">
            <?php foreach ($videos as $video):
                $ytId = (string)$video['youtube_id'];
                if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $ytId)) continue;
                $custom = (string)($video['thumbnail'] ?? '');
                $thumb = $custom !== '' ? e($custom) : 'https://i.ytimg.com/vi/' . $ytId . '/hqdefault.jpg';
                $fallback = $custom !== '' ? '' : ' onerror="this.onerror=null;this.src=\'https://i.ytimg.com/vi/' . $ytId . '/mqdefault.jpg\'"';
                $desc = trim((string)($video['description'] ?? ''));
                // Open the exact saved link, but only if it is still a canonical YouTube URL for this same video ID.
                $link = (string)($video['youtube_url'] ?? '');
                if (!preg_match('#^https://www\.youtube\.com/(watch\?v=|shorts/)' . preg_quote($ytId, '#') . '$#', $link))
                    $link = 'https://www.youtube.com/watch?v=' . $ytId;
            ?>
            <a class="video-card" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer"
               aria-label="Watch <?= e($video['title']) ?> on YouTube">
                <div class="video-thumb">
                    <img src="<?= $thumb ?>" alt="<?= e($video['title']) ?>" loading="lazy"<?= $fallback ?>>
                    <span class="video-play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                </div>
                <h3><?= e($video['title']) ?></h3>
                <?php if ($desc !== ''): ?><p><?= e($desc) ?></p><?php endif; ?>
                <span class="video-watch"><i class="fa-brands fa-youtube"></i> Watch on YouTube</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
