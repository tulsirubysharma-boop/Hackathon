
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . hsUrl('pages/login.php'));
    exit;
}

$pageTitle = 'My Health Profile';
$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT
            u.full_name,
            u.email,
            u.phone,
            hp.date_of_birth,
            hp.age_group,
            hp.gender,
            hp.blood_group,
            hp.allergies,
            hp.existing_conditions,
            hp.accessibility_notes,
            hp.guardian_name,
            hp.guardian_phone
         FROM users u
         LEFT JOIN health_profiles hp ON hp.user_id = u.user_id
         WHERE u.user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute(['user_id' => $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        session_destroy();
        header('Location: ' . hsUrl('pages/login.php'));
        exit;
    }
} catch (PDOException $e) {
    error_log('HealthSphere profile error: ' . $e->getMessage());
    $errorMessage = 'We could not load your profile right now. Please try again.';
}

function profileValue($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return 'Not provided';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ageGroupLabel($group): string
{
    return match ($group) {
        'child' => 'Child wellbeing',
        'adult' => 'Adult wellbeing',
        'senior' => 'AgeWell support',
        default => 'Not assigned'
    };
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="dashboard-section">
    <div class="section-heading">
        <p class="eyebrow">YOUR HEALTH, YOUR INFORMATION</p>
        <h1>My Health Profile</h1>
        <p>Review the health information you provided during registration.</p>
    </div>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php else: ?>

        <div class="profile-card">
            <div class="profile-card-heading">
                <div>
                    <p class="eyebrow">PERSONAL INFORMATION</p>
                    <h2><?= profileValue($profile['full_name']) ?></h2>
                </div>

                <span class="profile-badge">
                    <?= profileValue(ageGroupLabel($profile['age_group'] ?? null)) ?>
                </span>
            </div>

            <div class="profile-grid">
                <div class="profile-field">
                    <span>Full Name</span>
                    <strong><?= profileValue($profile['full_name']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Email Address</span>
                    <strong><?= profileValue($profile['email']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Phone Number</span>
                    <strong><?= profileValue($profile['phone']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Date of Birth</span>
                    <strong><?= profileValue($profile['date_of_birth']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Age Group</span>
                    <strong><?= profileValue(ageGroupLabel($profile['age_group'] ?? null)) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Gender</span>
                    <strong><?= profileValue($profile['gender']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Blood Group</span>
                    <strong><?= profileValue($profile['blood_group']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Allergies</span>
                    <strong><?= profileValue($profile['allergies']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Existing Conditions</span>
                    <strong><?= profileValue($profile['existing_conditions']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Accessibility Notes</span>
                    <strong><?= profileValue($profile['accessibility_notes']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Guardian Name</span>
                    <strong><?= profileValue($profile['guardian_name']) ?></strong>
                </div>

                <div class="profile-field">
                    <span>Guardian Phone</span>
                    <strong><?= profileValue($profile['guardian_phone']) ?></strong>
                </div>
            </div>

            <p class="profile-note">
                This page displays your saved information. It does not provide
                a medical diagnosis or replace advice from a qualified healthcare professional.
            </p>
        </div>

    <?php endif; ?>

    <div class="dashboard-actions">
        <a class="btn btn-outline" href="<?= hsUrl('pages/change-password.php') ?>">Change password</a>
        <a class="btn btn-outline" href="<?= hsUrl('pages/medical-history.php') ?>">Medical history</a>
        <a class="btn btn-primary" href="<?= hsUrl('pages/dashboard.php') ?>">
            Back to Dashboard
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
