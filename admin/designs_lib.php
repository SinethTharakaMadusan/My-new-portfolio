<?php
// Shared helpers for the Graphic Design Showcase (public section + admin page).
// Relies on bootstrap.php having been loaded already ($pdo, e()).

const DESIGN_HOME_LIMIT   = 7;
const DESIGN_MAX_BYTES    = 8 * 1024 * 1024; // 8 MB per image
const DESIGN_SEE_MORE_KEY = 'designs_see_more_url';

function design_categories(): array
{
    return ['Class Poster', 'YouTube Thumbnail', 'Social Media Design', 'Promotional Poster', 'Event Poster', 'Other'];
}

/** '' = empty (allowed), null = invalid, otherwise a clean http(s) URL. */
function design_clean_url(?string $u): ?string
{
    $u = trim((string)$u);
    if ($u === '') return '';
    if (strlen($u) > 255 || preg_match('/[\x00-\x20\x7f<>"\'\\\\]/', $u)) return null;
    if (!filter_var($u, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower((string)parse_url($u, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true) || !parse_url($u, PHP_URL_HOST)) return null;
    return $u;
}

/** Only accept paths of the form uploads/... with no traversal. */
function design_valid_rel(string $rel): bool
{
    return $rel !== '' && strpos($rel, '..') === false && preg_match('#^uploads/[A-Za-z0-9._/-]+$#', $rel) === 1;
}

/** Absolute filesystem path for a stored relative path, or null if unsafe / missing. */
function design_abs(string $rel): ?string
{
    if (!design_valid_rel($rel)) return null;
    $root = realpath(__DIR__ . '/../uploads');
    $abs  = realpath(__DIR__ . '/../' . $rel);
    if ($root === false || $abs === false || !is_file($abs)) return null;
    return strpos($abs, $root . DIRECTORY_SEPARATOR) === 0 ? $abs : null;
}

/** Validate an uploaded image by its real content. Throws RuntimeException with a friendly message. */
function design_check_image(array $f): void
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('Image is too large (max 8 MB).');
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed. Please try again.');
    if (($f['size'] ?? 0) > DESIGN_MAX_BYTES) throw new RuntimeException('Image is too large (max 8 MB).');
    if (!is_uploaded_file($f['tmp_name'] ?? '')) throw new RuntimeException('Invalid upload.');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Only real JPG, PNG or WebP images are allowed.');
    }
}

function design_exif_orientation(string $abs, int $type): int
{
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) return 1;
    $ex = @exif_read_data($abs);
    return (int)($ex['Orientation'] ?? 1);
}

/** [width, height] as the browser will display it (EXIF rotation applied). */
function design_dimensions(string $rel): array
{
    $abs  = design_abs($rel);
    $info = $abs ? @getimagesize($abs) : false;
    if (!$info) return [0, 0];
    [$w, $h, $type] = $info;
    return in_array(design_exif_orientation($abs, $type), [5, 6, 7, 8], true) ? [$h, $w] : [$w, $h];
}

/** Creates a smaller copy for the gallery grid. Returns its relative path, or '' (the original is used instead). */
function design_make_thumb(string $rel, int $maxW = 900): string
{
    $abs = design_abs($rel);
    if (!$abs || !extension_loaded('gd')) return '';
    $info = @getimagesize($abs);
    if (!$info) return '';
    [$w, $h, $type] = $info;
    if ($w * $h > 36000000) return '';
    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!isset($loaders[$type]) || !function_exists($loaders[$type])) return '';
    $src = @$loaders[$type]($abs);
    if (!$src) return '';
    $rot = [3 => 180, 6 => -90, 8 => 90][design_exif_orientation($abs, $type)] ?? 0;
    if ($rot) {
        $r = imagerotate($src, $rot, 0);
        if ($r) { imagedestroy($src); $src = $r; }
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= $maxW) { imagedestroy($src); return ''; }
    $nh  = max(1, (int)round($h * $maxW / $w));
    $dst = imagecreatetruecolor($maxW, $nh);
    if ($type !== IMAGETYPE_JPEG) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
    $ext  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$type];
    $name = 'thumb_' . pathinfo($abs, PATHINFO_FILENAME) . '.' . $ext;
    $out  = dirname($abs) . DIRECTORY_SEPARATOR . $name;
    if ($type === IMAGETYPE_JPEG)      $ok = @imagejpeg($dst, $out, 82);
    elseif ($type === IMAGETYPE_PNG)   $ok = @imagepng($dst, $out, 6);
    else                               $ok = function_exists('imagewebp') && @imagewebp($dst, $out, 82);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok ? dirname($rel) . '/' . $name : '';
}

/** True if any other record still points at this file. */
function design_file_in_use(PDO $pdo, string $rel, int $excludeId = 0): bool
{
    try {
        $s = $pdo->prepare('SELECT COUNT(*) FROM designs WHERE id<>? AND (image=? OR thumb=?)');
        $s->execute([$excludeId, $rel, $rel]);
        if ($s->fetchColumn() > 0) return true;
    } catch (Throwable $x) {
        return true; // cannot verify: keep the file
    }
    foreach ([['projects', 'thumbnail'], ['videos', 'thumbnail'], ['settings', 'v']] as [$t, $c]) {
        try {
            $s = $pdo->prepare("SELECT COUNT(*) FROM $t WHERE $c=?");
            $s->execute([$rel]);
            if ($s->fetchColumn() > 0) return true;
        } catch (Throwable $x) { /* table not present on this install */ }
    }
    return false;
}

/** Deletes files from uploads/ only when nothing else references them. */
function design_delete_files(PDO $pdo, array $rels, int $excludeId = 0): void
{
    foreach (array_unique(array_filter($rels)) as $rel) {
        if (design_file_in_use($pdo, $rel, $excludeId)) continue;
        $abs = design_abs($rel);
        if ($abs) @unlink($abs);
    }
}

/** Saved "See More Designs" URL, validated again at read time. '' = not configured. */
function design_see_more_url(PDO $pdo): string
{
    try {
        $s = $pdo->prepare('SELECT v FROM settings WHERE k=?');
        $s->execute([DESIGN_SEE_MORE_KEY]);
        return design_clean_url((string)$s->fetchColumn()) ?? '';
    } catch (Throwable $x) {
        return '';
    }
}
