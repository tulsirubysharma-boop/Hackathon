<?php
declare(strict_types=1);

function hsMailSenderAddress(): ?string
{
    $fromAddress = getenv('HEALTHSPHERE_MAIL_FROM');
    if (is_string($fromAddress) && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        return $fromAddress;
    }

    $sendmailConfigPath = 'C:\\xampp\\sendmail\\sendmail.ini';
    if (!is_file($sendmailConfigPath)) {
        return null;
    }

    $sendmailConfig = parse_ini_file($sendmailConfigPath, true, INI_SCANNER_RAW);
    $username = $sendmailConfig['sendmail']['auth_username'] ?? '';
    return is_string($username) && filter_var($username, FILTER_VALIDATE_EMAIL)
        ? $username
        : null;
}

function hsSendEmail(string $recipient, string $subject, string $body): bool
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('The email recipient address is invalid.');
    }

    $fromAddress = hsMailSenderAddress();
    if ($fromAddress === null) {
        error_log('HealthSphere email sender is not configured.');
        return false;
    }

    $sendmailConfigPath = 'C:\\xampp\\sendmail\\sendmail.ini';
    $sendmailConfig = is_file($sendmailConfigPath)
        ? parse_ini_file($sendmailConfigPath, true, INI_SCANNER_RAW)
        : false;
    $smtpConfig = is_array($sendmailConfig) ? ($sendmailConfig['sendmail'] ?? []) : [];
    if (
        ($smtpConfig['smtp_server'] ?? '') !== 'smtp.gmail.com' ||
        ($smtpConfig['smtp_port'] ?? '') !== '587' ||
        ($smtpConfig['smtp_ssl'] ?? '') !== 'tls' ||
        trim((string) ($smtpConfig['auth_password'] ?? '')) === ''
    ) {
        error_log('HealthSphere email was not sent because Gmail SMTP credentials are incomplete.');
        return false;
    }

    $headers = [
        'From: HealthSphere <' . $fromAddress . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: HealthSphere PHP'
    ];
    $sent = @mail($recipient, $subject, $body, implode("\r\n", $headers));
    if (!$sent) {
        error_log('HealthSphere email notification could not be handed to the configured mail transport.');
    }

    return $sent;
}

function hsMailFailureMessage(): string
{
    return 'Email was not sent. Add a Google App Password to auth_password in C:\\xampp\\sendmail\\sendmail.ini, then restart Apache in XAMPP. No email was delivered.';
}

function hsNotifyUser(PDO $pdo, int $userId, string $subject, string $body): bool
{
    $userQuery = $pdo->prepare('SELECT email FROM users WHERE user_id = :user_id');
    $userQuery->execute(['user_id' => $userId]);
    $email = $userQuery->fetchColumn();

    if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The account email address is invalid.');
    }

    $insert = $pdo->prepare(
        "INSERT INTO user_notifications
            (user_id, notification_type, subject, message, email_status)
         VALUES (:user_id, 'email', :subject, :message, 'pending')"
    );
    $insert->execute([
        'user_id' => $userId,
        'subject' => $subject,
        'message' => $body
    ]);
    $notificationId = (int) $pdo->lastInsertId();

    $sent = hsSendEmail($email, $subject, $body);

    $status = $sent ? 'sent' : 'failed';
    $update = $pdo->prepare(
        'UPDATE user_notifications SET email_status = :status WHERE notification_id = :id'
    );
    $update->execute(['status' => $status, 'id' => $notificationId]);

    return $sent;
}

function hsSendEmergencyContactEmail(
    PDO $pdo,
    int $userId,
    string $contactName,
    string $contactEmail
): bool {
    $sent = hsSendEmail(
        $contactEmail,
        'HealthSphere emergency check-in',
        "Hello {$contactName},\n\n"
        . ($_SESSION['full_name'] ?? 'Your trusted contact')
        . " has requested that you check in with them as soon as possible.\n"
        . "Please call or message them using the number they shared with you.\n\n"
        . "If there is immediate danger in India, call emergency services at 112."
    );
    hsLogMedicalEvent(
        $pdo,
        $userId,
        'emergency contact',
        $sent
            ? 'Emergency email handed to the configured mail service.'
            : 'Emergency email could not be sent because mail is not configured.'
    );
    return $sent;
}

function hsLogMedicalEvent(PDO $pdo, int $userId, string $eventType, string $summary): void
{
    $statement = $pdo->prepare(
        'INSERT INTO user_activity_history (user_id, event_type, summary)
         VALUES (:user_id, :event_type, :summary)'
    );
    $statement->execute([
        'user_id' => $userId,
        'event_type' => $eventType,
        'summary' => $summary
    ]);
}
