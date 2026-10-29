
<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . hsUrl('pages/dashboard.php'));
    exit;
}

$pageTitle = 'Create Account';
$errors = [];
$success = false;

$fullName = '';
$email = '';
$phone = '';
$dateOfBirth = '';
$gender = '';
$bloodGroup = '';
$allergies = '';
$existingConditions = '';
$accessibilityNotes = '';
$guardianName = '';
$guardianPhone = '';

function calculateAge(string $dateOfBirth): int
{
    $birthDate = new DateTimeImmutable($dateOfBirth);
    $today = new DateTimeImmutable('today');
    return $birthDate->diff($today)->y;
}

function determineAgeGroup(int $age): string
{
    if ($age <= 11) {
        return 'child';
    }

    if ($age <= 49) {
        return 'adult';
    }

    return 'senior';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $bloodGroup = trim($_POST['blood_group'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $existingConditions = trim($_POST['existing_conditions'] ?? '');
    $accessibilityNotes = trim($_POST['accessibility_notes'] ?? '');
    $guardianName = trim($_POST['guardian_name'] ?? '');
    $guardianPhone = trim($_POST['guardian_phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($_SESSION['registration_csrf'])) {
        $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
    }

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) ||
        !hash_equals($_SESSION['registration_csrf'], $submittedToken)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    if ($fullName === '' || mb_strlen($fullName) > 120) {
        $errors[] = 'Enter your full name (up to 120 characters).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors[] = 'Enter a valid email address (up to 190 characters).';
    }

    if ($phone !== '' && mb_strlen($phone) > 25) {
        $errors[] = 'Phone number must be 25 characters or fewer.';
    }

    $validBirthDate = false;
    $age = null;

    if ($dateOfBirth === '') {
        $errors[] = 'Date of birth is required.';
    } else {
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            $birth === false ||
            ($dateErrors !== false &&
                ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
            $birth->format('Y-m-d') !== $dateOfBirth
        ) {
            $errors[] = 'Enter a valid date of birth.';
        } elseif ($birth > new DateTimeImmutable('today')) {
            $errors[] = 'Date of birth cannot be in the future.';
        } else {
            $validBirthDate = true;
            $age = calculateAge($dateOfBirth);
        }
    }

    if ($gender !== '' && mb_strlen($gender) > 40) {
        $errors[] = 'Gender must be 40 characters or fewer.';
    }

    $allowedBloodGroups = [
        '', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
    ];

    if (!in_array($bloodGroup, $allowedBloodGroups, true)) {
        $errors[] = 'Select a valid blood group.';
    }

    if (mb_strlen($guardianName) > 120) {
        $errors[] = 'Guardian name must be 120 characters or fewer.';
    }

    if (mb_strlen($guardianPhone) > 25) {
        $errors[] = 'Guardian phone must be 25 characters or fewer.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if (strlen($password) > 4096) {
        $errors[] = 'Password is too long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if ($validBirthDate && $age !== null && $age < 18 &&
        ($guardianName === '' || $guardianPhone === '')) {
        $errors[] = 'For users under 18, provide a guardian name and phone number.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $checkEmail = $pdo->prepare(
                'SELECT user_id FROM users WHERE email = :email LIMIT 1'
            );
            $checkEmail->execute(['email' => $email]);

            if ($checkEmail->fetch()) {
                $pdo->rollBack();
                $errors[] = 'An account with this email already exists.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $ageGroup = determineAgeGroup($age);

                $insertUser = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, phone)
                     VALUES (:full_name, :email, :password_hash, :phone)'
                );

                $insertUser->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'phone' => $phone !== '' ? $phone : null
                ]);

                $userId = (int) $pdo->lastInsertId();

                $insertProfile = $pdo->prepare(
                    'INSERT INTO health_profiles
                    (user_id, date_of_birth, age_group, gender, blood_group,
                     allergies, existing_conditions, accessibility_notes,
                     guardian_name, guardian_phone)
                    VALUES
                    (:user_id, :date_of_birth, :age_group, :gender, :blood_group,
                     :allergies, :existing_conditions, :accessibility_notes,
                     :guardian_name, :guardian_phone)'
                );

                $insertProfile->execute([
                    'user_id' => $userId,
                    'date_of_birth' => $dateOfBirth,
                    'age_group' => $ageGroup,
                    'gender' => $gender !== '' ? $gender : null,
                    'blood_group' => $bloodGroup !== '' ? $bloodGroup : null,
                    'allergies' => $allergies !== '' ? $allergies : null,
                    'existing_conditions' => $existingConditions !== '' ? $existingConditions : null,
                    'accessibility_notes' => $accessibilityNotes !== '' ? $accessibilityNotes : null,
                    'guardian_name' => $guardianName !== '' ? $guardianName : null,
                    'guardian_phone' => $guardianPhone !== '' ? $guardianPhone : null
                ]);

                $pdo->commit();

                unset($_SESSION['registration_csrf']);
                $success = true;

                $fullName = $email = $phone = $dateOfBirth = '';
                $gender = $bloodGroup = $allergies = '';
                $existingConditions = $accessibilityNotes = '';
                $guardianName = $guardianPhone = '';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                $errors[] = 'An account with this email already exists.';
            } else {
                error_log('HealthSphere registration error: ' . $e->getMessage());
                $errors[] = 'Registration could not be completed. Please try again.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('HealthSphere registration error: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

if (empty($_SESSION['registration_csrf'])) {
    $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../includes/header.php';

function escapeValue(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<section class="auth-section">
    <div class="auth-intro">
        <span class="eyebrow">YOUR WELLNESS JOURNEY STARTS HERE</span>
        <h1>Create your <span>HealthSphere</span> account.</h1>
        <p>Personalised health support designed around your stage of life, preferences and everyday needs.</p>

        <div class="age-group-list">
            <div class="age-group-card">
                <span class="age-icon">✦</span>
                <div>
                    <strong>Child wellbeing</strong>
                    <p>Children aged 0–11</p>
                </div>
            </div>
            <div class="age-group-card">
                <span class="age-icon">✧</span>
                <div>
                    <strong>Adult wellbeing</strong>
                    <p>Adults aged 12–49</p>
                </div>
            </div>
            <div class="age-group-card">
                <span class="age-icon">❋</span>
                <div>
                    <strong>AgeWell support</strong>
                    <p>Adults aged 50 and above</p>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-card">
        <div class="auth-card-heading">
            <span class="eyebrow">JOIN HEALTHSPHERE</span>
            <h2>Get started</h2>
            <p>Fill in your details to create an account.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" role="status">
                <strong>Registration successful!</strong>
                Your account and health profile have been created.
                <a href="<?= hsUrl('pages/login.php') ?>">Continue to login</a>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <strong>Please check the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeValue($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= hsUrl('pages/register.php') ?>" class="health-form">
            <input type="hidden" name="csrf_token"
                   value="<?= escapeValue($_SESSION['registration_csrf']) ?>">

            <div class="form-grid">
                <div class="form-field full-width">
                    <label for="full_name">Full name *</label>
                    <input id="full_name" name="full_name" type="text"
                           maxlength="120" autocomplete="name" required
                           value="<?= escapeValue($fullName) ?>"
                           placeholder="Enter your full name">
                </div>

                <div class="form-field">
                    <label for="email">Email address *</label>
                    <input id="email" name="email" type="email"
                           maxlength="190" autocomplete="email" required
                           value="<?= escapeValue($email) ?>"
                           placeholder="you@example.com">
                </div>

                <div class="form-field">
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" type="tel"
                           maxlength="25" autocomplete="tel"
                           value="<?= escapeValue($phone) ?>"
                           placeholder="Your phone number">
                </div>

                <div class="form-field">
                    <label for="date_of_birth">Date of birth *</label>
                    <input id="date_of_birth" name="date_of_birth"
                           type="date" required
                           max="<?= date('Y-m-d') ?>"
                           value="<?= escapeValue($dateOfBirth) ?>">
                </div>

                <div class="form-field">
                    <label for="gender">Gender (optional)</label>
                    <select id="gender" name="gender">
                        <option value="">Prefer not to say</option>
                        <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Non-binary" <?= $gender === 'Non-binary' ? 'selected' : '' ?>>Non-binary</option>
                        <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="form-field">
                    <label for="blood_group">Blood group (optional)</label>
                    <select id="blood_group" name="blood_group">
                        <option value="">Not provided</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group): ?>
                            <option value="<?= $group ?>" <?= $bloodGroup === $group ? 'selected' : '' ?>>
                                <?= $group ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="password">Password *</label>
                    <input id="password" name="password" type="password"
                           minlength="8" maxlength="4096"
                           autocomplete="new-password" required
                           placeholder="At least 8 characters">
                </div>

                <div class="form-field">
                    <label for="confirm_password">Confirm password *</label>
                    <input id="confirm_password" name="confirm_password"
                           type="password" minlength="8" maxlength="4096"
                           autocomplete="new-password" required
                           placeholder="Enter password again">
                </div>

                <div class="form-field full-width">
                    <label for="allergies">Known allergies (optional)</label>
                    <textarea id="allergies" name="allergies" rows="2"
                              placeholder="List any known allergies, or leave blank"><?= escapeValue($allergies) ?></textarea>
                </div>

                <div class="form-field full-width">
                    <label for="existing_conditions">Existing health conditions (optional)</label>
                    <textarea id="existing_conditions" name="existing_conditions" rows="2"
                              placeholder="Share only what you are comfortable providing"><?= escapeValue($existingConditions) ?></textarea>
                </div>

                <div class="form-field full-width">
                    <label for="accessibility_notes">Accessibility or support needs (optional)</label>
                    <textarea id="accessibility_notes" name="accessibility_notes" rows="2"
                              placeholder="For example, larger text or mobility support"><?= escapeValue($accessibilityNotes) ?></textarea>
                </div>

                <div class="form-field full-width">
                    <h3 class="form-subheading">Guardian details</h3>
                    <p class="field-help">Required for users under 18. Otherwise, these fields are optional.</p>
                </div>

                <div class="form-field">
                    <label for="guardian_name">Guardian name</label>
                    <input id="guardian_name" name="guardian_name" type="text"
                           maxlength="120" value="<?= escapeValue($guardianName) ?>"
                           placeholder="Parent or guardian">
                </div>

                <div class="form-field">
                    <label for="guardian_phone">Guardian phone</label>
                    <input id="guardian_phone" name="guardian_phone" type="tel"
                           maxlength="25" value="<?= escapeValue($guardianPhone) ?>"
                           placeholder="Guardian's phone number">
                </div>
            </div>

            <p class="field-help">Please provide accurate information. HealthSphere is a support platform, not a substitute for professional medical care.</p>

            <button type="submit" class="btn btn-primary btn-full">
                Create my account <span aria-hidden="true">→</span>
            </button>
        </form>

        <p class="auth-switch">
            Already registered?
            <a href="<?= hsUrl('pages/login.php') ?>">Sign in here</a>
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
