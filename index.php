<?php
require __DIR__ . '/includes/bootstrap.php';
$S = site_settings($pdo);
$typed = array_values(array_filter(array_map('trim', explode("\n", $S['hero_strings']))));
$projects = $pdo->query('SELECT * FROM projects WHERE is_visible=1 ORDER BY is_featured DESC, id DESC')->fetchAll();
$skills = $pdo->query('SELECT * FROM skills WHERE is_visible=1 ORDER BY id')->fetchAll();
$experiences = $pdo->query('SELECT * FROM experiences WHERE is_visible=1 ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($S['site_title'] ?? 'Portfolio') ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>

<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <!-- <img src="image/logo1.png" class="nav-logo" alt=""> -->
                <a href="#home">Sineth Tharaka</a>
            </div>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#home" class="nav-link">Home</a>
                </li>
                <li class="nav-item">
                    <a href="#about" class="nav-link">About</a>
                </li>
                <li class="nav-item">
                    <a href="#projects" class="nav-link">Projects</a>
                </li>
                <li class="nav-item">
                    <a href="#skills" class="nav-link">Skills</a>
                </li>
                <li class="nav-item">
                    <a href="#contact" class="nav-link">Contact</a>
                </li>
            </ul>

            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>
    <!-- Main banner -->

    <section class="main" id="home">
        <img src="<?= e($S['hero_banner']) ?>" alt="main banner" class="main_banner">

        <!-- Auto Typing placed over the banner at the bottom side -->

        <div class="auto-typing">
            <h1>Hi, I'm <span class="typed-text"></span></h1>
        </div>
    </section>



    <section class="about-section" id="about">
        <div class="about-block" >
            <h2><?= accent($S['about_title']) ?></h2>
        </div>

        <div class="about-flex-container">
            <div class="about_image">
                <img src="<?= e($S['about_image']) ?>" alt="">
            </div>

            <div class="about-content">
                <h2><?= accent($S['about_heading']) ?></h2>
                <p><?= nl2br(e($S['about_text'])) ?></p>
                <a href="<?= $S['cv_file'] ? e($S['cv_file']) : '#' ?>" class="btn" <?= $S['cv_file'] ? 'download' : '' ?>>Download CV</a>
                <div class="icon">
                    <a href="<?= e($S['github'] ?? '#') ?>"><i class="fa-brands fa-github"></i></a>
                    <a href="<?= e($S['linkedin'] ?? '#') ?>"><i
                            class="fa-brands fa-square-linkedin"></i></a>
                    <a href="<?= e($S['whatsapp'] ?? '#') ?>"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>
        </div>
    </section>

    <section class="skill-section" id="skills">
        <div class="header-section">
            <div class="title-block">
                <h2><?= accent($S['skills_title']) ?></h2>
            </div>
            <div class="description-block"> </div>
        </div>
        <div class="experience-grid">
            <?php foreach ($experiences as $x): ?>
            <div class="experience-card">
                <p class="meta"><?= e($x['period']) ?> (<?= e($x['title']) ?>)</p>
                <h3 class="company"><?= e($x['organization']) ?></h3>
                <p class="description"><?= e($x['description']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="skills-grid">
            <?php foreach ($skills as $k): ?>
            <div class="skill-item">
                <div class="progress-circle" style="--p:<?= (int)$k['level'] ?>;">
                    <span class="percentage"><?= (int)$k['level'] ?>%</span>
                </div>
                <p class="skill-name"><?= e(strtoupper($k['name'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

    </section>

    <section class="projects" id="projects">
        <div class="project-block">
            <h2>Latest Working <span>Project</span></h2>

        </div>
        <div class="project-container">
            <?php foreach ($projects as $p): ?>
            <div class="project-card">
                <img src="<?= e($p['thumbnail']) ?>" alt="<?= e($p['title']) ?>">
                <h3><?= e($p['title']) ?></h3>
                <p><?= e($p['description']) ?></p>
                <?php if ($p['github_url'] || $p['demo_url']): ?>
                <p>
                    <?php if ($p['github_url']): ?><a href="<?= e($p['github_url']) ?>" target="_blank" rel="noopener" style="color:#00e676">GitHub</a><?php endif; ?>
                    <?php if ($p['github_url'] && $p['demo_url']): ?> · <?php endif; ?>
                    <?php if ($p['demo_url']): ?><a href="<?= e($p['demo_url']) ?>" target="_blank" rel="noopener" style="color:#00e676">Live demo</a><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </section>

    <?php require __DIR__ . '/includes/videos_section.php'; ?>

    <?php require __DIR__ . '/includes/graphic_carousel_section.php'; ?>

    <?php require __DIR__ . '/includes/designs_section.php'; ?>


    <section class="my-contact" id="contact">
        <div class="contact-block">
            <h2>Get In <span>Touch</span></h2>
        </div>
        <div class="contact-container">
            <div class="contact-card">
                <form action="contact.php" method="post" class="contact-form" id="contactForm">
                    <div class="input-box">
                        <input type="text" name="name" placeholder="Full Name" required maxlength="100">
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                    <div class="input-box">
                        <input type="text" name="mobile" placeholder="Mobile Number" maxlength="30">
                        <input type="text" name="subject" placeholder="Email Subject" maxlength="150">
                    </div>
                    <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
                    <textarea name="message" cols="30" rows="10" placeholder="Your Message" required maxlength="5000"></textarea>
                    <input type="submit" value="Send Message" class="btn">
                    <p id="formStatus" role="status" style="margin-top:15px"></p>
                </form>
            </div>

        </div>
    </section>



    <script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>

    <script>window.TYPED_STRINGS = <?= json_encode($typed, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="main.js"></script>
</body>



</html>
