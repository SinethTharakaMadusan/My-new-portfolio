<?php require __DIR__ . '/../includes/bootstrap.php'; require_admin(); $page = basename($_SERVER['SCRIPT_NAME'], '.php'); $f = flash();
$nav = ['index' => ['Dashboard', 'fa-gauge'], 'content' => ['Site content', 'fa-pen-to-square'], 'projects' => ['Projects', 'fa-folder-open'], 'skills' => ['Skills', 'fa-chart-pie'], 'experience' => ['Experience', 'fa-briefcase'], 'videos' => ['YouTube Videos', 'fa-video'], 'graphic_carousel' => ['Graphic Designs', 'fa-images'], 'designs' => ['Design Showcase', 'fa-palette'], 'messages' => ['Messages', 'fa-envelope']]; ?>

<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"><link rel="stylesheet" href="assets/admin.css"></head><body>
<aside class="side" id="side"><div class="brand">Portfolio <span>Admin</span></div>
<?php foreach ($nav as $k => [$l, $i]): ?><a href="<?= $k ?>.php" class="<?= $page === $k ? 'on' : '' ?>"><i class="fa-solid <?= $i ?>"></i> <?= $l ?></a><?php endforeach; ?>
<a href="../index.php" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> View site</a></aside>
<main><header class="top"><button class="burger" onclick="document.getElementById('side').classList.toggle('open')" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
<span><?= e($_SESSION['admin_name']) ?> &nbsp; <a href="logout.php">Logout</a></span></header><section class="content">
<?php if ($f): ?><div class="alert <?= $f[1] ?>"><?= e($f[0]) ?></div><?php endif; ?>
