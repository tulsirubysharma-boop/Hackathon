<?php
require_once __DIR__ . '/../includes/user-page.php';
require_once __DIR__ . '/../includes/notifications.php';

$pageTitle = 'Health reminders';
$message = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hsRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $type = (string) ($_POST['type'] ?? 'medicine');
            $dateTimeInput = (string) ($_POST['reminder_datetime'] ?? '');
            $repeat = (string) ($_POST['repeat_type'] ?? 'none');
            $allowedTypes = ['medicine', 'appointment', 'water', 'exercise', 'general'];
            $allowedRepeats = ['none', 'daily', 'weekly', 'monthly'];
            $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateTimeInput);

            if (
                $title === '' || mb_strlen($title) > 150 ||
                mb_strlen($description) > 2000 ||
                !in_array($type, $allowedTypes, true) ||
                !in_array($repeat, $allowedRepeats, true) ||
                !$dateTime || $dateTime->format('Y-m-d\TH:i') !== $dateTimeInput
            ) {
                throw new InvalidArgumentException('Check the title, reminder date, and selected options.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO reminders
                    (user_id, reminder_title, description, reminder_type, reminder_datetime, repeat_type)
                 VALUES
                    (:user_id, :title, :description, :type, :reminder_datetime, :repeat_type)'
            );
            $insert->execute([
                'user_id' => $userId,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'type' => $type,
                'reminder_datetime' => $dateTime->format('Y-m-d H:i:s'),
                'repeat_type' => $repeat
            ]);
            hsLogMedicalEvent($pdo, $userId, 'reminder added', "Reminder added: {$title}.");
            if ($type === 'medicine') {
                $emailSent = hsNotifyUser(
                    $pdo,
                    $userId,
                    'HealthSphere medicine reminder created',
                    "A medicine reminder was added to your HealthSphere calendar.\n\n"
                    . "Reminder: {$title}\n"
                    . 'Time: ' . $dateTime->format('d M Y, h:i A') . "\n\n"
                    . "This is a reminder only, not medical advice. Follow your clinician's instructions."
                );
                $message = $emailSent
                    ? 'Medicine reminder saved; the email was handed to your configured mail service.'
                    : 'Medicine reminder saved. ' . hsMailFailureMessage();
            } else {
                $message = 'Reminder saved.';
            }
        } elseif ($action === 'complete' || $action === 'delete') {
            $reminderId = filter_var($_POST['reminder_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$reminderId) {
                throw new InvalidArgumentException('Select a valid reminder.');
            }

            if ($action === 'delete') {
                $statement = $pdo->prepare(
                    'DELETE FROM reminders WHERE reminder_id = :id AND user_id = :user_id'
                );
                $statement->execute(['id' => $reminderId, 'user_id' => $userId]);
                $changed = $statement->rowCount() > 0;
            } else {
                $changed = hsCompleteReminder($pdo, $userId, (int) $reminderId);
            }
            $message = $changed ? 'Reminder updated.' : 'No matching reminder was changed.';
            if ($changed) {
                hsLogMedicalEvent(
                    $pdo,
                    $userId,
                    $action === 'delete' ? 'reminder deleted' : 'reminder completed',
                    "Reminder {$action}."
                );
            }
        } else {
            throw new InvalidArgumentException('Choose a valid reminder action.');
        }
    } catch (InvalidArgumentException $exception) {
        $errorMessage = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('HealthSphere reminder error: ' . $exception->getMessage());
        $errorMessage = 'The reminder could not be saved. Check your database setup and try again.';
    }
}

if (isset($_GET['calendar'])) {
    $calendarId = filter_var($_GET['calendar'], FILTER_VALIDATE_INT);
    $calendarQuery = $pdo->prepare(
        'SELECT reminder_title, description, reminder_datetime, repeat_type
         FROM reminders
         WHERE reminder_id = :id AND user_id = :user_id'
    );
    $calendarQuery->execute(['id' => $calendarId, 'user_id' => $userId]);
    $calendarReminder = $calendarQuery->fetch();
    if (!$calendarReminder) {
        http_response_code(404);
        exit('Reminder not found.');
    }

    $start = new DateTimeImmutable($calendarReminder['reminder_datetime']);
    $end = $start->modify('+15 minutes');
    $escapeIcs = static fn (string $value): string => str_replace(
        ["\\", ";", ",", "\r\n", "\n"],
        ["\\\\", "\\;", "\\,", "\\n", "\\n"],
        $value
    );
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="healthsphere-reminder.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//HealthSphere//Reminder//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n";
    echo "BEGIN:VEVENT\r\nUID:reminder-{$calendarId}@healthsphere.local\r\n";
    echo 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n";
    echo 'DTSTART:' . $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . "\r\n";
    echo 'DTEND:' . $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . "\r\n";
    echo 'SUMMARY:' . $escapeIcs($calendarReminder['reminder_title']) . "\r\n";
    if (!empty($calendarReminder['description'])) {
        echo 'DESCRIPTION:' . $escapeIcs($calendarReminder['description']) . "\r\n";
    }
    if ($calendarReminder['repeat_type'] !== 'none') {
        echo 'RRULE:FREQ=' . strtoupper($calendarReminder['repeat_type']) . "\r\n";
    }
    echo "BEGIN:VALARM\r\nTRIGGER:PT0M\r\nACTION:DISPLAY\r\n";
    echo 'DESCRIPTION:' . $escapeIcs('HealthSphere reminder: ' . $calendarReminder['reminder_title']) . "\r\n";
    echo "END:VALARM\r\n";
    echo "END:VEVENT\r\nEND:VCALENDAR\r\n";
    exit;
}

$remindersQuery = $pdo->prepare(
    'SELECT reminder_id, reminder_title, description, reminder_type,
            reminder_datetime, repeat_type, status
     FROM reminders
     WHERE user_id = :user_id
     ORDER BY reminder_datetime ASC
     LIMIT 100'
);
$remindersQuery->execute(['user_id' => $userId]);
$reminders = $remindersQuery->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-section">
    <div class="section-heading">
        <p class="eyebrow">YOUR HEALTH ROUTINE</p>
        <h1>Health reminders</h1>
        <p>Organise medicines and appointments. Follow the directions from your clinician or pharmacist; reminders do not provide dosing advice.</p>
    </div>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= hsEscape($message) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><?= hsEscape($errorMessage) ?></div><?php endif; ?>

    <div class="profile-card">
        <h2>Add a reminder</h2>
        <form method="post" class="health-form">
            <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-field">
                    <label for="title">Reminder title *</label>
                    <input id="title" name="title" maxlength="150" required placeholder="Medicine or appointment">
                </div>
                <div class="form-field">
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <option value="medicine">Medicine</option><option value="appointment">Appointment</option>
                        <option value="water">Water</option><option value="exercise">Exercise</option><option value="general">General</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="reminder_datetime">Date and time *</label>
                    <input id="reminder_datetime" name="reminder_datetime" type="datetime-local" required>
                </div>
                <div class="form-field">
                    <label for="repeat_type">Repeat</label>
                    <select id="repeat_type" name="repeat_type">
                        <option value="none">Does not repeat</option><option value="daily">Daily</option>
                        <option value="weekly">Weekly</option><option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="form-field full-width">
                    <label for="description">Notes (optional)</label>
                    <textarea id="description" name="description" maxlength="2000" rows="2"></textarea>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Save reminder</button>
        </form>
    </div>

    <div class="feature-grid" style="margin-top:24px">
        <?php if (!$reminders): ?>
            <article class="feature-card"><h2>No reminders yet</h2><p>Your saved reminders will appear here.</p></article>
        <?php endif; ?>
        <?php foreach ($reminders as $reminder): ?>
            <article class="feature-card">
                <span class="eyebrow"><?= hsEscape(ucfirst($reminder['reminder_type'])) ?> · <?= hsEscape(ucfirst($reminder['status'])) ?></span>
                <h3><?= hsEscape($reminder['reminder_title']) ?></h3>
                <p><?= hsEscape(date('D, d M Y · h:i A', strtotime($reminder['reminder_datetime']))) ?></p>
                <?php if ($reminder['description']): ?><p><?= nl2br(hsEscape($reminder['description'])) ?></p><?php endif; ?>
                <p>Repeat: <?= hsEscape(ucfirst($reminder['repeat_type'])) ?></p>
                <?php if ($reminder['status'] === 'active'): ?>
                    <a class="btn btn-outline" href="<?= hsUrl('pages/reminders.php') ?>?calendar=<?= (int) $reminder['reminder_id'] ?>">Add to calendar</a>
                    <p class="field-help">Downloads a calendar event file (.ics). Open it to add the reminder to your device calendar.</p>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
                        <input type="hidden" name="reminder_id" value="<?= (int) $reminder['reminder_id'] ?>">
                        <button class="btn btn-primary" name="action" value="complete">Mark done</button>
                    </form>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this reminder?')">
                    <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
                    <input type="hidden" name="reminder_id" value="<?= (int) $reminder['reminder_id'] ?>">
                    <button class="btn btn-outline" name="action" value="delete">Delete</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
