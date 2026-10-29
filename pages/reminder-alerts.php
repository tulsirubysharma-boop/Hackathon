<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/user-page.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST for reminder actions.']);
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !hash_equals($pageCsrfToken, $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Refresh the page and try again.']);
    exit;
}

$action = (string) ($_POST['action'] ?? '');
try {
    if ($action === 'due') {
        $pdo->beginTransaction();
        $dueQuery = $pdo->prepare(
            'SELECT reminder_id, reminder_title, reminder_type, reminder_datetime
             FROM reminders
             WHERE user_id = :user_id AND status = \'active\'
               AND reminder_datetime <= NOW() AND notification_sent_at IS NULL
             ORDER BY reminder_datetime ASC
             LIMIT 20
             FOR UPDATE'
        );
        $dueQuery->execute(['user_id' => $userId]);
        $dueReminders = $dueQuery->fetchAll();

        if ($dueReminders) {
            $ids = array_map('intval', array_column($dueReminders, 'reminder_id'));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $markSent = $pdo->prepare(
                "UPDATE reminders SET notification_sent_at = NOW()
                 WHERE user_id = ? AND reminder_id IN ($placeholders)"
            );
            $markSent->execute(array_merge([$userId], $ids));
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'reminders' => $dueReminders], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'complete') {
        $reminderId = filter_var($_POST['reminder_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$reminderId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid reminder.']);
            exit;
        }
        $changed = hsCompleteReminder($pdo, $userId, (int) $reminderId);
        if ($changed) {
            hsLogMedicalEvent($pdo, $userId, 'reminder completed', 'Reminder marked complete from a dashboard notification.');
        }
        echo json_encode(['success' => $changed]);
        exit;
    }

    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid reminder action.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('HealthSphere reminder notification error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Reminder alerts are temporarily unavailable.']);
}
