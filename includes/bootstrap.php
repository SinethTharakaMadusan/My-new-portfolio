<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Invalid request token.'); }
}
function require_admin(): void {
    if (empty($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
}
function flash(?string $msg = null, string $type = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function safe_url(string $u): string {
    $u = trim($u);
    return ($u !== '' && filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u)) ? $u : '';
}
function save_image(array $f, string $dir): ?string {
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024) throw new RuntimeException('Upload failed or larger than 3 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Only JPG, PNG or WebP images are allowed.');
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not save the file.');
    return 'uploads/' . $name;
}

function site_settings(PDO $pdo): array {
    $d = [
        'site_title' => 'Portfolio', 'hero_banner' => 'image/main banner.png',
        'hero_strings' => "Sineth Tharaka\na Web Developer\na Creative Designer\na UX/UI Designer\na Problem Solver",
        'about_title' => 'Behind the Code', 'about_heading' => 'Failure is the condiment that gives Success',
        'about_text' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Non ut asperiores distinctio recusandae facere, ipsum natus molestias nesciunt odio excepturi.',
        'about_image' => 'image/about.jpeg', 'cv_file' => '',
        'skills_title' => 'Experience And Skill',
        'skills_desc' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Minima nam minus eos consectetur sunt voluptas non, alias voluptatem, illum perspiciatis ipsum ut provident dolorem, tenetur reiciendis totam odio eius nostrum beatae quam? Sequi eligendi ipsam cumque distinctio tempora quibusdam ratione.',
        'github' => '', 'linkedin' => '', 'whatsapp' => '',
    ];
    return array_merge($d, $pdo->query('SELECT k, v FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR));
}
function accent(string $s): string { // last word goes green, like your original headings
    $s = trim($s); $p = strrpos($s, ' ');
    return $p === false ? '<span>' . e($s) . '</span>' : e(substr($s, 0, $p)) . ' <span>' . e(substr($s, $p + 1)) . '</span>';
}
function save_cv(array $f, string $dir): ?string {
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) throw new RuntimeException('CV upload failed or larger than 5 MB.');
    if ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf') throw new RuntimeException('The CV must be a PDF file.');
    $name = 'cv-' . bin2hex(random_bytes(8)) . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not save the CV.');
    return 'uploads/' . $name;
}
