
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/app.php';

$pageTitle = "HealthSphere";
$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin = $isLoggedIn && ($_SESSION['role'] ?? 'user') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="HealthSphere offers age-adaptive health support, wellness reminders, and helpful healthcare resources.">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | Your Health Journey</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <a class="brand" href="index.php" aria-label="HealthSphere home">
        <span class="brand-symbol">✚</span>
        <span>
            HealthSphere
            <small>Care at every age</small>
        </span>
    </a>

    <nav class="nav-links" aria-label="Main navigation">
        <a href="#our-approach">Our Approach</a>
        <a href="#care-options">Care Options</a>
        <?php if ($isLoggedIn): ?>
            <a href="<?= hsUrl('pages/dashboard.php') ?>">Dashboard</a>
            <details class="profile-menu">
                <summary>My profile <span aria-hidden="true">⌄</span></summary>
                <div class="profile-menu-items">
                    <a href="<?= hsUrl('pages/profile.php') ?>">Profile</a>
                    <a href="<?= hsUrl('pages/change-password.php') ?>">Change password</a>
                    <a href="<?= hsUrl('pages/medical-history.php') ?>">Medical history</a>
                    <?php if ($isAdmin): ?><a href="<?= hsUrl('pages/admin.php') ?>">Admin</a><?php endif; ?>
                    <a href="<?= hsUrl('pages/logout.php') ?>">Logout</a>
                </div>
            </details>
        <?php else: ?>
            <a href="<?= hsUrl('pages/login.php') ?>">Login</a>
            <a class="btn btn-primary" href="<?= hsUrl('pages/register.php') ?>">Create account</a>
        <?php endif; ?>
    </nav>
</header>

<main>
    <section class="hero" id="our-approach">
        <div class="hero-content">
            <span class="eyebrow">✦ AGE-ADAPTIVE HEALTH SUPPORT</span>

            <h1>Your health journey,<br>
                <span>supported at every age.</span>
            </h1>

            <p class="hero-description">
                From childhood milestones to everyday wellness and
                graceful ageing, HealthSphere brings helpful health
                resources, personalised reminders, and supportive
                guidance together in one welcoming space.
            </p>

            <div class="hero-actions">
                <a class="btn btn-rose" href="#care-options">
                    Discover your care path <span aria-hidden="true">→</span>
                </a>
                <a class="btn btn-outline" href="#our-promise">
                    Our promise
                </a>
            </div>

            <p class="hero-note">
                Thoughtful support for children, adults, and older adults.
            </p>
        </div>
    </section>

    <section class="section" id="care-options">
        <div class="section-heading">
            <span class="section-label">One platform, three care paths</span>
            <h2>Care that grows with you.</h2>
            <p>
                Different life stages bring different needs.
                Explore a care path designed around the stage of life
                you want to support.
            </p>
        </div>

        <div class="feature-grid">
            <article class="feature-card">
                <div class="feature-icon" aria-hidden="true">🌱</div>
                <h3>Child wellbeing</h3>
                <p>
                    A child-focused support path for wellness routines,
                    growth-related reminders, and useful information
                    for caregivers.
                </p>
                <a href="pages/register.php">Create a care profile &rarr;</a>
            </article>

            <article class="feature-card">
                <div class="feature-icon" aria-hidden="true">🌸</div>
                <h3>Adult wellbeing</h3>
                <p>
                    Support everyday wellbeing with health routines,
                    appointment reminders, and easy-to-access
                    healthcare resources.
                </p>
                <a href="pages/register.php">Create a care profile &rarr;</a>
            </article>

            <article class="feature-card">
                <div class="feature-icon" aria-hidden="true">🌿</div>
                <h3>AgeWell support</h3>
                <p>
                    Make daily care easier to organise with medication
                    reminders, emergency-contact information, and
                    accessible wellness resources.
                </p>
                <a href="pages/register.php">Create a care profile &rarr;</a>
            </article>
        </div>

        <div class="disclaimer" id="our-promise">
            <strong>Our promise:</strong>
            HealthSphere is designed to support health awareness and
            care organisation. It does not replace a qualified
            healthcare professional, provide a medical diagnosis,
            or prescribe treatment. In an emergency, contact your
            local emergency services.
        </div>
    </section>
</main>

<footer class="site-footer">
    <p><strong>HealthSphere</strong> — Your health journey, supported at every age.</p>
    <p>Built with care for the Zinnovatio 4.0 Hackathon · 2026</p>
</footer>

</body>
</html>
