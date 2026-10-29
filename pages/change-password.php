<?php
require_once __DIR__ . '/../includes/user-page.php';
require_once __DIR__ . '/../includes/notifications.php';

$pageTitle = 'Change password';
$message = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hsRequireCsrf();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($newPassword) < 8 || strlen($newPassword) > 4096) {
        $errorMessage = 'Your new password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'The new passwords do not match.';
    } else {
        try {
            $query = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :user_id');
            $query->execute(['user_id' => $userId]);
            $passwordHash = $query->fetchColumn();

            if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
                $errorMessage = 'Your current password is incorrect.';
            } else {
                $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id');
                $update->execute([
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'user_id' => $userId
                ]);
                hsLogMedicalEvent($pdo, $userId, 'security', 'Account password changed.');
                $message = 'Password changed successfully.';
            }
        } catch (Throwable $exception) {
            error_log('HealthSphere password update error: ' . $exception->getMessage());
            $errorMessage = 'Your password could not be changed. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-section">
    <div class="section-heading">
        <p class="eyebrow">ACCOUNT SECURITY</p>
        <h1>Change password</h1>
    </div>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= hsEscape($message) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><?= hsEscape($errorMessage) ?></div><?php endif; ?>
    <div class="profile-card">
        <form method="post" class="health-form">
            <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
            <div class="form-grid">
                <div class="form-field full-width">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                </div>
                <div class="form-field">
                    <label for="new_password">New password</label>
                    <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="form-field">
                    <label for="confirm_password">Confirm new password</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
