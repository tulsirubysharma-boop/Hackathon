
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

$pageTitle = $pageTitle ?? 'HealthSphere';
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = $isLoggedIn && ($_SESSION['role'] ?? 'user') === 'admin';
$ageTheme = $isLoggedIn && in_array($_SESSION['age_group'] ?? '', ['child', 'adult', 'senior'], true)
    ? 'age-theme-' . $_SESSION['age_group']
    : '';
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$backPath = $isLoggedIn && $currentPage !== 'dashboard.php'
    ? hsUrl('pages/dashboard.php')
    : hsUrl('index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | HealthSphere</title>

    <link rel="stylesheet" href="<?= htmlspecialchars(hsUrl('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body<?= $ageTheme !== '' ? ' class="' . htmlspecialchars($ageTheme, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

<header class="site-header">
    <a class="brand" href="<?= htmlspecialchars(hsUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>">
        <span class="brand-mark">H</span>
        <span>HealthSphere</span>
    </a>

    <nav class="site-nav" aria-label="Main navigation">
        <?php if ($isLoggedIn): ?>
            <details class="profile-menu">
                <summary>My profile <span aria-hidden="true">⌄</span></summary>
                <div class="profile-menu-items">
                    <a href="<?= htmlspecialchars(hsUrl('pages/profile.php'), ENT_QUOTES, 'UTF-8') ?>">Profile</a>
                    <a href="<?= htmlspecialchars(hsUrl('pages/change-password.php'), ENT_QUOTES, 'UTF-8') ?>">Change password</a>
                    <a href="<?= htmlspecialchars(hsUrl('pages/medical-history.php'), ENT_QUOTES, 'UTF-8') ?>">Medical history</a>
                    <a href="<?= htmlspecialchars(hsUrl('pages/login.php'), ENT_QUOTES, 'UTF-8') ?>">Login / switch account</a>
                    <?php if ($isAdmin): ?>
                        <a href="<?= htmlspecialchars(hsUrl('pages/admin.php'), ENT_QUOTES, 'UTF-8') ?>">Admin</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars(hsUrl('pages/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Logout</a>
                </div>
            </details>
        <?php else: ?>
            <a href="<?= htmlspecialchars(hsUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <a href="<?= htmlspecialchars(hsUrl('pages/register.php'), ENT_QUOTES, 'UTF-8') ?>">Register</a>
            <a href="<?= htmlspecialchars(hsUrl('pages/login.php'), ENT_QUOTES, 'UTF-8') ?>">Login</a>
        <?php endif; ?>
    </nav>
</header>

<?php if ($currentPage !== 'index.php'): ?>
<div class="page-back-wrap">
    <a class="page-back" href="<?= htmlspecialchars($backPath, ENT_QUOTES, 'UTF-8') ?>" id="pageBack">← Back</a>
</div>
<script>
<?php if ($currentPage !== 'dashboard.php'): ?>
document.getElementById('pageBack').addEventListener('click', event => {
    try {
        if (document.referrer && new URL(document.referrer).origin === window.location.origin) {
            event.preventDefault();
            window.history.back();
        }
    } catch (error) {
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

<main class="page-content">
