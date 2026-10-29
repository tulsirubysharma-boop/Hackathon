<?php
require_once __DIR__ . '/../includes/user-page.php';
require_once __DIR__ . '/../includes/notifications.php';

$pageTitle = 'Lab test requests';
$message = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hsRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'request') {
            $testName = trim((string) ($_POST['test_name'] ?? ''));
            $labName = trim((string) ($_POST['lab_name'] ?? ''));
            $preferredDate = trim((string) ($_POST['preferred_date'] ?? ''));
            $preferredTime = trim((string) ($_POST['preferred_time'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $date = $preferredDate !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $preferredDate) : null;
            $time = $preferredTime !== '' ? DateTimeImmutable::createFromFormat('!H:i', $preferredTime) : null;
            $appointmentReminderAt = $date && $time
                ? $date->setTime((int) $time->format('H'), (int) $time->format('i'))
                : null;

            if (
                $testName === '' || mb_strlen($testName) > 180 ||
                mb_strlen($labName) > 180 || mb_strlen($location) > 255 ||
                mb_strlen($notes) > 2000 ||
                !$date || $date->format('Y-m-d') !== $preferredDate ||
                !$time || $time->format('H:i') !== $preferredTime ||
                !$appointmentReminderAt || $appointmentReminderAt <= new DateTimeImmutable('now')
            ) {
                throw new InvalidArgumentException('Choose a test and a future preferred date and time for your local reminder.');
            }

            $pdo->beginTransaction();
            $insert = $pdo->prepare(
                "INSERT INTO lab_requests (user_id, lab_name, test_name, preferred_date, location, notes)
                 VALUES (:user_id, :lab_name, :test_name, :preferred_date, :location, :notes)"
            );
            $insert->execute([
                'user_id' => $userId,
                'lab_name' => $labName !== '' ? $labName : null,
                'test_name' => $testName,
                'preferred_date' => $preferredDate !== '' ? $preferredDate : null,
                'location' => $location !== '' ? $location : null,
                'notes' => $notes !== '' ? $notes : null
            ]);
            $labRequestId = (int) $pdo->lastInsertId();
            $reminderInsert = $pdo->prepare(
                "INSERT INTO reminders
                    (user_id, reminder_title, description, reminder_type, reminder_datetime, repeat_type)
                 VALUES
                    (:user_id, :title, :description, 'appointment', :reminder_datetime, 'none')"
            );
            $reminderInsert->execute([
                'user_id' => $userId,
                'title' => 'Lab appointment reminder: ' . $testName,
                'description' => 'Lab request #' . $labRequestId . '. This is a personal reminder only; no provider booking is confirmed.',
                'reminder_datetime' => $appointmentReminderAt->format('Y-m-d H:i:s')
            ]);
            $eventSummary = sprintf(
                'Lab test request recorded: %s · preferred date and time %s · area %s. A personal reminder was scheduled; no provider booking was made.',
                $testName,
                $appointmentReminderAt->format('d M Y, h:i A'),
                $location !== '' ? $location : 'not specified'
            );
            hsLogMedicalEvent($pdo, $userId, 'lab test', $eventSummary);
            $pdo->commit();
            $message = 'Lab request saved and a laptop reminder was scheduled for '
                . $appointmentReminderAt->format('d M Y, h:i A')
                . '. Open the dashboard and allow notifications; keep it open for the alert. No lab booking or home visit is confirmed.';
        } elseif ($action === 'cancel') {
            $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$requestId) {
                throw new InvalidArgumentException('Select a valid request.');
            }
            $cancel = $pdo->prepare(
                "UPDATE lab_requests SET status = 'cancelled'
                 WHERE lab_request_id = :id AND user_id = :user_id AND status = 'requested'"
            );
            $cancel->execute(['id' => $requestId, 'user_id' => $userId]);
            $message = $cancel->rowCount() ? 'Request cancelled.' : 'No pending request was changed.';
            if ($cancel->rowCount()) {
                $cancelReminder = $pdo->prepare(
                    "UPDATE reminders
                     SET status = 'cancelled'
                     WHERE user_id = :user_id
                       AND reminder_type = 'appointment'
                       AND description = :description
                       AND status = 'active'"
                );
                $cancelReminder->execute([
                    'user_id' => $userId,
                    'description' => 'Lab request #' . $requestId . '. This is a personal reminder only; no provider booking is confirmed.'
                ]);
                hsLogMedicalEvent($pdo, $userId, 'lab test', 'Lab test request cancelled.');
            }
        } else {
            throw new InvalidArgumentException('Choose a valid request action.');
        }
    } catch (InvalidArgumentException $exception) {
        $errorMessage = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('HealthSphere lab request error: ' . $exception->getMessage());
        $errorMessage = 'The request could not be saved. Check your database connection and try again.';
    }
}

$requestQuery = $pdo->prepare(
    "SELECT lr.lab_request_id, lr.lab_name, lr.test_name, lr.preferred_date,
            lr.location, lr.notes, lr.status, lr.created_at,
            (SELECT r.reminder_datetime
             FROM reminders r
             WHERE r.user_id = lr.user_id
               AND r.description = CONCAT(
                   'Lab request #', lr.lab_request_id,
                   '. This is a personal reminder only; no provider booking is confirmed.'
               )
             ORDER BY r.reminder_id DESC
             LIMIT 1) AS reminder_datetime
     FROM lab_requests lr
     WHERE lr.user_id = :user_id
     ORDER BY lr.created_at DESC
     LIMIT 50"
);
$requestQuery->execute(['user_id' => $userId]);
$requests = $requestQuery->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-section">
    <div class="section-heading">
        <p class="eyebrow">CARE THAT COMES CLOSER</p>
        <h1>Lab test requests</h1>
        <p>Save a personal lab-test request and choose when you want a laptop reminder. This form does not contact a lab, confirm an appointment, arrange a home collection, or send an email.</p>
    </div>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= hsEscape($message) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><?= hsEscape($errorMessage) ?></div><?php endif; ?>

    <div class="profile-card">
        <h2>Request a test</h2>
        <form method="post" class="health-form" id="labRequestForm">
            <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
            <input type="hidden" name="action" value="request">
            <div class="form-grid">
                <div class="form-field"><label for="test_name">Test name *</label><input id="test_name" name="test_name" maxlength="180" required placeholder="For example, complete blood count"></div>
                <div class="form-field"><label for="lab_name">Preferred lab (optional)</label><input id="lab_name" name="lab_name" maxlength="180"></div>
                <div class="form-field"><label for="preferred_date">Preferred date for your reminder *</label><input id="preferred_date" name="preferred_date" type="date" min="<?= date('Y-m-d') ?>" required></div>
                <div class="form-field"><label for="preferred_time">Preferred time for your reminder *</label><input id="preferred_time" name="preferred_time" type="time" required></div>
                <div class="form-field"><label for="location">Area / PIN code</label><input id="location" name="location" maxlength="255" autocomplete="postal-code"></div>
                <div class="form-field full-width"><label for="notes">Notes (optional)</label><textarea id="notes" name="notes" maxlength="2000" rows="2"></textarea></div>
            </div>
            <button class="btn btn-primary" type="submit">Save request and reminder</button>
            <p class="field-help" id="labNotificationStatus" role="status" aria-live="polite">A laptop alert requires browser notification permission and the dashboard open at the selected time.</p>
        </form>
    </div>

    <div class="feature-grid" style="margin-top:24px">
        <?php if (!$requests): ?><article class="feature-card"><h2>No requests yet</h2><p>Your lab test requests will appear here.</p></article><?php endif; ?>
        <?php foreach ($requests as $request): ?>
            <article class="feature-card">
                <span class="eyebrow">Status: <?= hsEscape(ucfirst($request['status'])) ?></span>
                <h3><?= hsEscape($request['test_name']) ?></h3>
                <p><?= hsEscape($request['lab_name'] ?: 'No lab selected') ?></p>
                <p>Preferred date: <?= hsEscape($request['preferred_date'] ?: 'Not specified') ?></p>
                <p>Personal reminder: <?= hsEscape($request['reminder_datetime'] ? date('D, d M Y · h:i A', strtotime($request['reminder_datetime'])) : 'Not scheduled') ?></p>
                <p>Location: <?= hsEscape($request['location'] ?: 'Not specified') ?></p>
                <?php if ($request['notes']): ?><p><?= nl2br(hsEscape($request['notes'])) ?></p><?php endif; ?>
                <?php if ($request['status'] === 'requested'): ?>
                    <form method="post" onsubmit="return confirm('Cancel this request?')">
                        <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
                        <input type="hidden" name="request_id" value="<?= (int) $request['lab_request_id'] ?>">
                        <button class="btn btn-outline" name="action" value="cancel">Cancel request</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<script>
(() => {
    const form = document.getElementById('labRequestForm');
    const status = document.getElementById('labNotificationStatus');
    let permissionResolved = false;

    form.addEventListener('submit', async event => {
        if (permissionResolved) return;
        permissionResolved = true;
        if (!('Notification' in window) || Notification.permission !== 'default') return;

        event.preventDefault();
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                status.textContent = 'Browser notifications are blocked. The request will still be saved; allow notifications in browser settings for the laptop alert.';
            }
        } catch (error) {
            status.textContent = 'Notification permission could not be requested. The request will still be saved.';
        }
        form.requestSubmit(event.submitter);
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
