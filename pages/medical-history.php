<?php
require_once __DIR__ . '/../includes/user-page.php';

$pageTitle = 'Medical history';
$history = [];
$chatDays = [];
$errorMessage = '';

try {
    $chatDateQuery = $pdo->prepare(
        'SELECT DATE(created_at) AS chat_date, COUNT(*) AS message_count
         FROM chat_messages
         WHERE user_id = :user_id
         GROUP BY DATE(created_at)
         ORDER BY chat_date DESC
         LIMIT 180'
    );
    $chatDateQuery->execute(['user_id' => $userId]);
    $chatDays = $chatDateQuery->fetchAll();

    $historyQuery = $pdo->prepare(
        "SELECT event_type, summary, occurred_at FROM (
            SELECT 'Profile' AS event_type,
                   CONCAT('Health profile created. Age group: ', age_group) AS summary,
                   created_at AS occurred_at
            FROM health_profiles WHERE user_id = :profile_user
            UNION ALL
            SELECT CONCAT('Reminder · ', reminder_type),
                   CONCAT(reminder_title, ' · ', status), reminder_datetime
            FROM reminders WHERE user_id = :reminder_user
            UNION ALL
            SELECT 'Lab test',
                   CONCAT(test_name, ' · ', status, IF(location IS NULL, '', CONCAT(' · ', location))),
                   created_at
            FROM lab_requests WHERE user_id = :lab_user
            UNION ALL
            SELECT CONCAT('Wellbeing · ', activity_type), activity_description, recorded_at
            FROM health_activity_logs WHERE user_id = :activity_user
            UNION ALL
            SELECT 'Appointment',
                   CONCAT(COALESCE(provider_name, 'Appointment'), ' · ', status), appointment_datetime
            FROM appointments WHERE user_id = :appointment_user
            UNION ALL
            SELECT event_type, summary, occurred_at
            FROM user_activity_history WHERE user_id = :audit_user
            UNION ALL
            SELECT 'Notification', CONCAT(subject, ' · email ', email_status), created_at
            FROM user_notifications WHERE user_id = :notification_user
            UNION ALL
            SELECT 'Medicine request · not placed', CONCAT(item_description, ' · no purchase placed'), created_at
            FROM demo_medicine_orders WHERE user_id = :order_user
         ) AS timeline
         ORDER BY occurred_at DESC
         LIMIT 500"
    );
    $historyQuery->execute([
        'profile_user' => $userId,
        'reminder_user' => $userId,
        'lab_user' => $userId,
        'activity_user' => $userId,
        'appointment_user' => $userId,
        'audit_user' => $userId,
        'notification_user' => $userId,
        'order_user' => $userId
    ]);
    $history = $historyQuery->fetchAll();
} catch (Throwable $exception) {
    error_log('HealthSphere medical history error: ' . $exception->getMessage());
    $errorMessage = 'Medical history is unavailable. Apply the latest database upgrade and try again.';
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-section">
    <div class="section-heading">
        <p class="eyebrow">YOUR HEALTH RECORD</p>
        <h1>Medical history</h1>
        <p>Your chats are grouped by date. Select a date to reopen that conversation.</p>
    </div>
    <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><?= hsEscape($errorMessage) ?></div><?php endif; ?>
    <?php if ($chatDays): ?>
        <section class="chat-history-days" aria-labelledby="chatHistoryTitle">
            <h2 id="chatHistoryTitle">Chat history</h2>
            <div class="history-list">
                <?php foreach ($chatDays as $chatDay): ?>
                    <a class="history-entry history-day-link" href="<?= hsUrl('pages/chat.php') ?>?date=<?= rawurlencode($chatDay['chat_date']) ?>">
                        <time><?= hsEscape(date('l, d M Y', strtotime($chatDay['chat_date']))) ?></time>
                        <strong><?= (int) $chatDay['message_count'] ?> saved chat messages</strong>
                        <span>Open conversation &rarr;</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if (!$history && !$chatDays && $errorMessage === ''): ?><div class="profile-card"><p>Your history will appear here as you use HealthSphere.</p></div><?php endif; ?>
    <?php if ($history): ?>
        <h2 class="history-timeline-title">Other health and account activity</h2>
        <div class="history-list">
            <?php foreach ($history as $item): ?>
                <article class="history-entry">
                    <time><?= hsEscape(date('d M Y · h:i A', strtotime($item['occurred_at']))) ?></time>
                    <h2><?= hsEscape($item['event_type']) ?></h2>
                    <p><?= nl2br(hsEscape($item['summary'])) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
