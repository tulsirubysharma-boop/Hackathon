
<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Login';
$errors = [];
$email = '';
$ageGroup = (string) ($_POST['age_group'] ?? '');
$allowedAgeGroups = ['child', 'adult', 'senior'];
$alreadyLoggedIn = !empty($_SESSION['user_id']);

if ($alreadyLoggedIn) {
    $sessionAccountQuery = $pdo->prepare(
        'SELECT u.full_name, u.account_status, hp.age_group
         FROM users u
         INNER JOIN health_profiles hp ON hp.user_id = u.user_id
         WHERE u.user_id = :user_id
         LIMIT 1'
    );
    $sessionAccountQuery->execute(['user_id' => (int) $_SESSION['user_id']]);
    $sessionAccount = $sessionAccountQuery->fetch(PDO::FETCH_ASSOC);
    if (
        !$sessionAccount ||
        $sessionAccount['account_status'] !== 'active' ||
        !in_array($sessionAccount['age_group'], $allowedAgeGroups, true)
    ) {
        $_SESSION = [];
        session_regenerate_id(true);
        $alreadyLoggedIn = false;
    } else {
        $_SESSION['full_name'] = $sessionAccount['full_name'];
        $_SESSION['age_group'] = $sessionAccount['age_group'];
    }
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyLoggedIn) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['login_csrf'], $token)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!in_array($ageGroup, $allowedAgeGroups, true)) {
        $errors[] = 'Choose the care space that matches the account date of birth.';
    }

    if (!is_string($password) || $password === '') {
        $errors[] = 'Enter your password.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'SELECT user_id, full_name, email, password_hash, role,
                        account_status
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );

            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$user ||
                !password_verify($password, $user['password_hash'])
            ) {
                $errors[] = 'Invalid email or password.';
            } elseif ($user['account_status'] !== 'active') {
                $errors[] = 'This account is disabled. Please contact the administrator.';
            } else {
                $profileStmt = $pdo->prepare(
                    'SELECT age_group
                     FROM health_profiles
                     WHERE user_id = :user_id
                     LIMIT 1'
                );

                $profileStmt->execute(['user_id' => $user['user_id']]);
                $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

                if (!$profile) {
                    $errors[] = 'Your health profile could not be found. Please contact support.';
                } elseif ($profile['age_group'] !== $ageGroup) {
                    $groupNames = [
                        'child' => 'Child & caregiver',
                        'adult' => 'Adult',
                        'senior' => 'AgeWell (50+)'
                    ];
                    $actualGroup = $groupNames[$profile['age_group']] ?? 'another';
                    $errors[] = "This account belongs to the {$actualGroup} care space. Select the matching option and try again.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['age_group'] = $profile['age_group'];

                    unset($_SESSION['login_csrf']);

                    header('Location: ' . hsUrl('pages/dashboard.php'));
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('HealthSphere login error: ' . $e->getMessage());
            $errors[] = 'Login is temporarily unavailable. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-section login-section<?= in_array($ageGroup, $allowedAgeGroups, true) ? ' age-choice-' . htmlspecialchars($ageGroup, ENT_QUOTES, 'UTF-8') : '' ?>">
    <div class="auth-intro">
        <span class="eyebrow">WELCOME BACK</span>

        <h1>Your wellbeing, <span>all in one place.</span></h1>

        <p>
            Sign in to access your personalised HealthSphere experience,
            health reminders and everyday wellness support.
        </p>

        <div class="age-group-list">
            <div class="age-group-card">
                <span class="age-icon" aria-hidden="true">🌈</span>
                <div><strong>Child &amp; caregiver</strong><p>A friendly, supported space for ages 0–11</p></div>
            </div>
            <div class="age-group-card">
                <span class="age-icon" aria-hidden="true">✦</span>
                <div><strong>Adult wellbeing</strong><p>A focused space for ages 12–49</p></div>
            </div>
            <div class="age-group-card">
                <span class="age-icon" aria-hidden="true">🌿</span>
                <div><strong>AgeWell</strong><p>Clear, comfortable support for ages 50+</p></div>
            </div>
        </div>
    </div>

    <div class="auth-card login-card">
        <?php if ($alreadyLoggedIn): ?>
            <div class="auth-card-heading">
                <span class="eyebrow">ACCOUNT ALREADY OPEN</span>
                <h2>You’re signed in</h2>
                <p><?= htmlspecialchars((string) ($_SESSION['full_name'] ?? 'Your account'), ENT_QUOTES, 'UTF-8') ?> is currently signed in. Sign out before opening another login.</p>
            </div>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= hsUrl('pages/dashboard.php') ?>">Continue to dashboard</a>
                <a class="btn btn-outline" href="<?= hsUrl('pages/logout.php') ?>">Sign out to login</a>
            </div>
        <?php else: ?>
        <div class="auth-card-heading">
            <span class="eyebrow">HEALTHSPHERE ACCOUNT</span>
            <h2>Sign in</h2>
            <p>Choose the care space matching the date of birth used when the account was created.</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <strong>Unable to sign in</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="<?= hsUrl('pages/login.php') ?>"
              class="health-form">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">

            <fieldset class="age-login-options">
                <legend>Choose your care space</legend>
                <label class="age-login-choice">
                    <input type="radio" name="age_group" value="child" required <?= $ageGroup === 'child' ? 'checked' : '' ?>>
                    <span class="age-login-card age-login-card-child"><span aria-hidden="true">🌈</span><strong>Child &amp; caregiver</strong><small>Ages 0–11</small></span>
                </label>
                <label class="age-login-choice">
                    <input type="radio" name="age_group" value="adult" <?= $ageGroup === 'adult' ? 'checked' : '' ?>>
                    <span class="age-login-card age-login-card-adult"><span aria-hidden="true">✦</span><strong>Adult</strong><small>Ages 12–49</small></span>
                </label>
                <label class="age-login-choice">
                    <input type="radio" name="age_group" value="senior" <?= $ageGroup === 'senior' ? 'checked' : '' ?>>
                    <span class="age-login-card age-login-card-senior"><span aria-hidden="true">🌿</span><strong>AgeWell</strong><small>Ages 50+</small></span>
                </label>
            </fieldset>

            <div class="form-grid">
                <div class="form-field full-width">
                    <label for="email">Email address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="190"
                        autocomplete="username"
                        placeholder="you@example.com"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="form-field full-width">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                Sign in <span aria-hidden="true">→</span>
            </button>
        </form>

        <p class="auth-switch">
            New to HealthSphere?
            <a href="<?= hsUrl('pages/register.php') ?>">Create an account</a>
        </p>
        <?php endif; ?>
    </div>
</section>
<?php if (!$alreadyLoggedIn): ?>
<script>
document.querySelectorAll('input[name="age_group"]').forEach(option => {
    option.addEventListener('change', () => {
        const section = document.querySelector('.login-section');
        section.classList.remove('age-choice-child', 'age-choice-adult', 'age-choice-senior');
        section.classList.add(`age-choice-${option.value}`);
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
