<?php
require_once __DIR__ . '/../includes/user-page.php';
require_once __DIR__ . '/../includes/notifications.php';

$pageTitle = 'Emergency contacts';
$message = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hsRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add') {
            $name = trim((string) ($_POST['contact_name'] ?? ''));
            $relationship = trim((string) ($_POST['relationship'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));

            if (
                $name === '' || mb_strlen($name) > 120 ||
                $relationship !== '' && mb_strlen($relationship) > 60 ||
                $phone === '' || mb_strlen($phone) > 25 ||
                $email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)
            ) {
                throw new InvalidArgumentException('Enter a name and valid phone number. Email is optional.');
            }

            $pdo->beginTransaction();
            $lockUser = $pdo->prepare('SELECT user_id FROM users WHERE user_id = :user_id FOR UPDATE');
            $lockUser->execute(['user_id' => $userId]);
            $countQuery = $pdo->prepare(
                'SELECT COUNT(*) FROM emergency_contacts WHERE user_id = :user_id'
            );
            $countQuery->execute(['user_id' => $userId]);
            if ((int) $countQuery->fetchColumn() >= 1) {
                $pdo->rollBack();
                throw new InvalidArgumentException('Only one emergency contact can be saved.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO emergency_contacts (user_id, contact_name, relationship, phone, email)
                 VALUES (:user_id, :name, :relationship, :phone, :email)'
            );
            $insert->execute([
                'user_id' => $userId,
                'name' => $name,
                'relationship' => $relationship !== '' ? $relationship : null,
                'phone' => $phone,
                'email' => $email !== '' ? $email : null
            ]);
            $pdo->commit();
            hsLogMedicalEvent($pdo, $userId, 'emergency contact', "Emergency contact added: {$name}.");
            $message = 'Emergency contact saved.';
        } elseif ($action === 'update') {
            $contactId = filter_var($_POST['contact_id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['contact_name'] ?? ''));
            $relationship = trim((string) ($_POST['relationship'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            if (
                !$contactId ||
                $name === '' || mb_strlen($name) > 120 ||
                ($relationship !== '' && mb_strlen($relationship) > 60) ||
                $phone === '' || mb_strlen($phone) > 25 ||
                ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190))
            ) {
                throw new InvalidArgumentException('Enter a name and valid phone number. Email is optional.');
            }
            $update = $pdo->prepare(
                'UPDATE emergency_contacts
                 SET contact_name = :name, relationship = :relationship, phone = :phone, email = :email
                 WHERE contact_id = :id AND user_id = :user_id'
            );
            $update->execute([
                'name' => $name,
                'relationship' => $relationship !== '' ? $relationship : null,
                'phone' => $phone,
                'email' => $email !== '' ? $email : null,
                'id' => $contactId,
                'user_id' => $userId
            ]);
            if (!$update->rowCount()) {
                $exists = $pdo->prepare(
                    'SELECT contact_id FROM emergency_contacts
                     WHERE contact_id = :id AND user_id = :user_id'
                );
                $exists->execute(['id' => $contactId, 'user_id' => $userId]);
                if (!$exists->fetch()) {
                    throw new InvalidArgumentException('No matching contact was found.');
                }
            }
            hsLogMedicalEvent($pdo, $userId, 'emergency contact', 'Emergency contact details updated.');
            $message = 'Emergency contact details updated.';
        } elseif ($action === 'email_contact') {
            $contactId = filter_var($_POST['contact_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$contactId) {
                throw new InvalidArgumentException('Select a valid emergency contact.');
            }
            $contactQuery = $pdo->prepare(
                'SELECT contact_name, email FROM emergency_contacts
                 WHERE contact_id = :id AND user_id = :user_id'
            );
            $contactQuery->execute(['id' => $contactId, 'user_id' => $userId]);
            $contact = $contactQuery->fetch();
            if (!$contact || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Add a valid email address for this contact first.');
            }
            $emailSent = hsSendEmergencyContactEmail(
                $pdo,
                $userId,
                (string) $contact['contact_name'],
                (string) $contact['email']
            );
            $message = $emailSent
                ? 'Emergency email was handed to your configured mail service. Delivery is not guaranteed.'
                : hsMailFailureMessage();
        } elseif ($action === 'delete') {
            $contactId = filter_var($_POST['contact_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$contactId) {
                throw new InvalidArgumentException('Select a valid contact.');
            }
            $delete = $pdo->prepare(
                'DELETE FROM emergency_contacts WHERE contact_id = :id AND user_id = :user_id'
            );
            $delete->execute(['id' => $contactId, 'user_id' => $userId]);
            $message = $delete->rowCount() ? 'Emergency contact removed.' : 'No matching contact was found.';
            if ($delete->rowCount()) {
                hsLogMedicalEvent($pdo, $userId, 'emergency contact', 'Emergency contact removed.');
            }
        } else {
            throw new InvalidArgumentException('Choose a valid contact action.');
        }
    } catch (InvalidArgumentException $exception) {
        $errorMessage = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('HealthSphere emergency contact error: ' . $exception->getMessage());
        $errorMessage = 'Contacts could not be updated. Check that the database upgrade has been applied.';
    }
}

$contactsQuery = $pdo->prepare(
    'SELECT contact_id, contact_name, relationship, phone, email
     FROM emergency_contacts WHERE user_id = :user_id ORDER BY contact_id LIMIT 1'
);
$contactsQuery->execute(['user_id' => $userId]);
$contacts = $contactsQuery->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-section">
    <div class="section-heading">
        <p class="eyebrow">PEOPLE YOU TRUST</p>
        <h1>Emergency contacts</h1>
        <p>Save one trusted contact. Email attempts send through your configured mail service; SMS opens your messaging app and still needs you to tap Send.</p>
    </div>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= hsEscape($message) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><?= hsEscape($errorMessage) ?></div><?php endif; ?>

    <div class="profile-card emergency-contact-form-card">
            <h2><?= $contacts ? 'Update your emergency contact' : 'Add your emergency contact' ?></h2>
            <p><?= $contacts ? 'Keep one trusted contact on file. Saving updates the contact already attached to your account.' : 'Add one trusted person. You can update these details later.' ?></p>
            <form method="post" class="health-form">
                <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
                <input type="hidden" name="action" value="<?= $contacts ? 'update' : 'add' ?>">
                <?php if ($contacts): ?>
                    <input type="hidden" name="contact_id" value="<?= (int) $contacts[0]['contact_id'] ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div class="form-field"><label for="contact_name">Name *</label><input id="contact_name" name="contact_name" maxlength="120" value="<?= hsEscape($contacts[0]['contact_name'] ?? '') ?>" required></div>
                    <div class="form-field"><label for="relationship">Relationship</label><input id="relationship" name="relationship" maxlength="60" value="<?= hsEscape($contacts[0]['relationship'] ?? '') ?>" placeholder="Family, friend..."></div>
                    <div class="form-field"><label for="phone">Mobile number *</label><input id="phone" name="phone" type="tel" maxlength="25" value="<?= hsEscape($contacts[0]['phone'] ?? '') ?>" required></div>
                    <div class="form-field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" value="<?= hsEscape($contacts[0]['email'] ?? '') ?>"></div>
                </div>
                <button class="btn btn-primary" type="submit"><?= $contacts ? 'Save contact changes' : 'Add contact' ?></button>
            </form>
    </div>

    <div class="contact-card-list">
        <?php if (!$contacts): ?><article class="feature-card"><h2>No contacts saved</h2><p>Add trusted people who can help in an emergency.</p></article><?php endif; ?>
        <?php foreach ($contacts as $contact): ?>
            <?php
            $smsBody = rawurlencode('I may need help. Please contact me as soon as possible.');
            $safePhone = preg_replace('/[^0-9+]/', '', $contact['phone']);
            ?>
            <article class="feature-card">
                <span class="feature-icon" aria-hidden="true">♡</span>
                <h3><?= hsEscape($contact['contact_name']) ?></h3>
                <p><?= hsEscape($contact['relationship'] ?? 'Trusted contact') ?></p>
                <p><?= hsEscape($contact['phone']) ?></p>
                <?php if ($contact['email']): ?><p><?= hsEscape($contact['email']) ?></p><?php endif; ?>
                <div class="contact-actions">
                    <a class="btn btn-primary emergency-sms-action"
                       href="sms:<?= rawurlencode($safePhone) ?>?body=<?= $smsBody ?>"
                       data-contact-name="<?= hsEscape($contact['contact_name']) ?>">Contact in emergency (SMS)</a>
                    <?php if ($contact['email']): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
                            <input type="hidden" name="contact_id" value="<?= (int) $contact['contact_id'] ?>">
                            <button class="btn btn-outline" name="action" value="email_contact">Send emergency email</button>
                        </form>
                    <?php endif; ?>
                </div>
                <p class="emergency-contact-notice" role="status" aria-live="polite"></p>
                <form method="post" onsubmit="return confirm('Remove this contact?')">
                    <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
                    <input type="hidden" name="contact_id" value="<?= (int) $contact['contact_id'] ?>">
                    <button class="btn btn-outline" name="action" value="delete">Remove</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="disclaimer" style="margin-top:24px">
        <strong>Need urgent help?</strong> Call India's emergency number <a href="tel:112">112</a>. HealthSphere does not monitor audio or automatically contact anyone.
    </div>
</section>
<script>
(() => {
    const status = document.querySelector('.emergency-contact-notice');
    document.querySelectorAll('.emergency-sms-action').forEach(link => {
        link.addEventListener('click', async event => {
            event.preventDefault();
            const name = link.dataset.contactName || 'your emergency contact';
            const message = `Message ready to send to ${name}. Tap Send in your messaging app to contact them.`;
            status.textContent = message;

            if ('Notification' in window) {
                try {
                    const permission = Notification.permission === 'default'
                        ? await Notification.requestPermission()
                        : Notification.permission;
                    if (permission === 'granted') {
                        new Notification('Emergency contact message ready', {
                            body: message,
                            tag: 'healthsphere-emergency-contact'
                        });
                    } else {
                        status.textContent += ' Allow browser notifications to see this as a desktop alert.';
                    }
                } catch (error) {
                    status.textContent += ' The desktop alert could not be shown; the SMS composer will still open.';
                }
            } else {
                status.textContent += ' This browser does not support desktop notifications.';
            }

            window.location.href = link.href;
        });
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
