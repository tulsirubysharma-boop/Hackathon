<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . hsUrl('pages/login.php'));
    exit;
}

$userId = (int) $_SESSION['user_id'];
$accountQuery = $pdo->prepare(
    'SELECT u.account_status, hp.age_group
     FROM users u
     INNER JOIN health_profiles hp ON hp.user_id = u.user_id
     WHERE u.user_id = :user_id
     LIMIT 1'
);
$accountQuery->execute(['user_id' => $userId]);
$account = $accountQuery->fetch();
if (
    !$account ||
    $account['account_status'] !== 'active' ||
    !in_array($account['age_group'], ['child', 'adult', 'senior'], true)
) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . hsUrl('pages/login.php'));
    exit;
}
$_SESSION['age_group'] = $account['age_group'];

if (empty($_SESSION['page_csrf_token'])) {
    $_SESSION['page_csrf_token'] = bin2hex(random_bytes(32));
}

$pageCsrfToken = $_SESSION['page_csrf_token'];

function hsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function hsRequireCsrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['page_csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(403);
        exit('Your session expired. Refresh the page and try again.');
    }
}

function hsCompleteReminder(PDO $pdo, int $userId, int $reminderId): bool
{
    $query = $pdo->prepare(
        "SELECT reminder_datetime, repeat_type
         FROM reminders
         WHERE reminder_id = :id AND user_id = :user_id AND status = 'active'"
    );
    $query->execute(['id' => $reminderId, 'user_id' => $userId]);
    $reminder = $query->fetch();
    if (!$reminder) {
        return false;
    }

    if ($reminder['repeat_type'] === 'none') {
        $update = $pdo->prepare(
            "UPDATE reminders SET status = 'completed'
             WHERE reminder_id = :id AND user_id = :user_id AND status = 'active'"
        );
        $update->execute(['id' => $reminderId, 'user_id' => $userId]);
        return $update->rowCount() > 0;
    }

    $nextDate = new DateTimeImmutable($reminder['reminder_datetime']);
    $now = new DateTimeImmutable('now');
    $repeat = $reminder['repeat_type'];
    $iterations = 0;
    do {
        if ($repeat === 'daily') {
            $nextDate = $nextDate->modify('+1 day');
        } elseif ($repeat === 'weekly') {
            $nextDate = $nextDate->modify('+1 week');
        } else {
            $dayOfMonth = (int) $nextDate->format('j');
            $nextMonth = $nextDate->modify('first day of next month');
            $lastDay = (int) $nextMonth->format('t');
            $nextDate = $nextMonth->setDate(
                (int) $nextMonth->format('Y'),
                (int) $nextMonth->format('n'),
                min($dayOfMonth, $lastDay)
            )->setTime(
                (int) $nextDate->format('H'),
                (int) $nextDate->format('i'),
                (int) $nextDate->format('s')
            );
        }
        $iterations++;
    } while ($nextDate <= $now && $iterations < 1000);

    $update = $pdo->prepare(
        "UPDATE reminders
         SET reminder_datetime = :next_datetime, notification_sent_at = NULL
         WHERE reminder_id = :id AND user_id = :user_id AND status = 'active'"
    );
    $update->execute([
        'next_datetime' => $nextDate->format('Y-m-d H:i:s'),
        'id' => $reminderId,
        'user_id' => $userId
    ]);
    return $update->rowCount() > 0;
}
