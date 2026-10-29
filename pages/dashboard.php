<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . hsUrl('pages/login.php'));
    exit;
}

$pageTitle = 'My Dashboard';
$userId = (int) $_SESSION['user_id'];
$errorMessage = '';

try {
    $stmt = $pdo->prepare(
        "SELECT u.full_name, u.account_status, hp.age_group
         FROM users u
         LEFT JOIN health_profiles hp ON hp.user_id = u.user_id
         WHERE u.user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        $user['account_status'] !== 'active' ||
        !in_array($user['age_group'], ['child', 'adult', 'senior'], true)
    ) {
        $_SESSION = [];
        session_destroy();

        header('Location: ' . hsUrl('pages/login.php'));
        exit;
    }

    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['age_group'] = $user['age_group'];

} catch (PDOException $e) {
    error_log('HealthSphere dashboard error: ' . $e->getMessage());
    $errorMessage = 'Unable to load your dashboard. Please try again.';
}

$fullName = $user['full_name'] ?? 'there';
$ageGroup = $user['age_group'] ?? 'adult';
$dashboardPersona = [
    'child' => [
        'eyebrow' => 'YOUR BRIGHT HEALTH SPACE',
        'welcome' => 'Welcome back,',
        'intro' => 'A friendly place to keep healthy routines and ask a trusted grown-up for support.',
        'profileLabel' => 'MY CARE PROFILE',
        'calendarLabel' => 'My care calendar',
        'calendarIntro' => 'See your reminders and plans with your caregiver.',
        'dashboardLabel' => 'My health helpers',
        'dashboardIntro' => 'Choose a friendly tool and ask your caregiver to help when needed.'
    ],
    'adult' => [
        'eyebrow' => 'YOUR HEALTHSPACE',
        'welcome' => 'Welcome back,',
        'intro' => 'Your personal space for wellbeing information, care routines, and health resources.',
        'profileLabel' => 'YOUR PERSONAL INFORMATION',
        'calendarLabel' => 'Your health routine',
        'calendarIntro' => 'Keep reminders and appointments organised.',
        'dashboardLabel' => 'Explore your dashboard',
        'dashboardIntro' => 'Select a feature to continue.'
    ],
    'senior' => [
        'eyebrow' => 'AGEWELL HOME',
        'welcome' => 'Welcome back,',
        'intro' => 'A clear, comfortable space for your care routine, appointments, and support.',
        'profileLabel' => 'YOUR CARE INFORMATION',
        'calendarLabel' => 'Your care calendar',
        'calendarIntro' => 'View upcoming reminders and appointments in one place.',
        'dashboardLabel' => 'Your care tools',
        'dashboardIntro' => 'Choose a large, clear shortcut to continue.'
    ]
][$ageGroup];
$monthParameter = (string) ($_GET['month'] ?? date('Y-m'));
$calendarMonth = DateTimeImmutable::createFromFormat('!Y-m', $monthParameter);
if (!$calendarMonth || $calendarMonth->format('Y-m') !== $monthParameter) {
    $calendarMonth = new DateTimeImmutable('first day of this month');
}
$calendarMonth = $calendarMonth->modify('first day of this month');
$calendarStart = $calendarMonth->format('Y-m-01 00:00:00');
$calendarEnd = $calendarMonth->modify('first day of next month')->format('Y-m-d 00:00:00');
$calendarReminders = [];
$calendarLabRequests = [];
$monthlyReminderCounts = array_fill(1, 12, 0);

if ($errorMessage === '') {
    try {
        $calendarQuery = $pdo->prepare(
            "SELECT reminder_id, reminder_title, reminder_type, reminder_datetime, repeat_type, status
             FROM reminders
             WHERE user_id = :user_id
               AND reminder_datetime >= :month_start AND reminder_datetime < :month_end
               AND status = 'active'
             ORDER BY reminder_datetime"
        );
        $calendarQuery->execute([
            'user_id' => $userId,
            'month_start' => $calendarStart,
            'month_end' => $calendarEnd
        ]);
        $calendarReminders = $calendarQuery->fetchAll();

        $labCalendarQuery = $pdo->prepare(
            "SELECT lab_request_id, test_name, lab_name, preferred_date
             FROM lab_requests
             WHERE user_id = :user_id
               AND preferred_date >= :month_start
               AND preferred_date < :month_end
               AND status IN ('requested', 'contacted', 'confirmed')
             ORDER BY preferred_date, lab_request_id"
        );
        $labCalendarQuery->execute([
            'user_id' => $userId,
            'month_start' => $calendarMonth->format('Y-m-d'),
            'month_end' => $calendarMonth->modify('first day of next month')->format('Y-m-d')
        ]);
        $calendarLabRequests = $labCalendarQuery->fetchAll();

        $monthlyQuery = $pdo->prepare(
            "SELECT MONTH(reminder_datetime) AS reminder_month, COUNT(*) AS reminder_count
             FROM reminders
             WHERE user_id = :user_id
               AND YEAR(reminder_datetime) = :year
               AND status = 'active'
             GROUP BY MONTH(reminder_datetime)"
        );
        $monthlyQuery->execute([
            'user_id' => $userId,
            'year' => $calendarMonth->format('Y')
        ]);
        foreach ($monthlyQuery->fetchAll() as $monthlyRow) {
            $monthlyReminderCounts[(int) $monthlyRow['reminder_month']] = (int) $monthlyRow['reminder_count'];
        }
    } catch (PDOException $exception) {
        error_log('HealthSphere dashboard calendar error: ' . $exception->getMessage());
        $errorMessage = 'Unable to load your reminders. Please check the database upgrade.';
    }
}

$remindersByDate = [];
foreach ($calendarReminders as $calendarReminder) {
    $dateKey = substr($calendarReminder['reminder_datetime'], 0, 10);
    $calendarReminder['event_type'] = 'reminder';
    $remindersByDate[$dateKey][] = $calendarReminder;
}
foreach ($calendarLabRequests as $labRequest) {
    $dateKey = $labRequest['preferred_date'];
    $remindersByDate[$dateKey][] = [
        'event_type' => 'lab_request',
        'reminder_title' => 'Lab request: ' . $labRequest['test_name'],
        'reminder_type' => 'lab request',
        'reminder_datetime' => $dateKey . ' 00:00:00',
        'lab_name' => $labRequest['lab_name']
    ];
}
$calendarEvents = array_merge(
    array_map(static function (array $reminder): array {
        $reminder['event_type'] = 'reminder';
        return $reminder;
    }, $calendarReminders),
    array_map(static function (array $labRequest): array {
        return [
            'event_type' => 'lab_request',
            'reminder_title' => 'Lab request: ' . $labRequest['test_name'],
            'reminder_type' => 'lab request',
            'reminder_datetime' => $labRequest['preferred_date'] . ' 00:00:00',
            'lab_name' => $labRequest['lab_name']
        ];
    }, $calendarLabRequests)
);
$calendarOffset = (int) $calendarMonth->format('N') - 1;
$calendarDays = (int) $calendarMonth->format('t');
$calendarCellCount = (int) ceil(($calendarOffset + $calendarDays) / 7) * 7;
$previousMonth = $calendarMonth->modify('-1 month')->format('Y-m');
$nextMonth = $calendarMonth->modify('+1 month')->format('Y-m');
$remindersJson = json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$pageCsrfToken = $_SESSION['page_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['page_csrf_token'] = $pageCsrfToken;

require_once __DIR__ . '/../includes/header.php';
?>

<section class="dashboard-section dashboard-age dashboard-age-<?= htmlspecialchars($ageGroup, ENT_QUOTES, 'UTF-8') ?>">

    <?php if ($errorMessage !== ''): ?>

        <p class="alert alert-error">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>

    <?php else: ?>
        <div class="dashboard-app-shell">
            <aside class="dashboard-sidebar" aria-label="Dashboard">
                <a class="dashboard-side-brand" href="<?= hsUrl('pages/dashboard.php') ?>">
                    <span class="dashboard-side-logo" aria-hidden="true">H</span>
                    <span>HealthSphere<small><?= htmlspecialchars($ageGroup === 'child' ? 'Care & grow' : ($ageGroup === 'senior' ? 'AgeWell' : 'My health space'), ENT_QUOTES, 'UTF-8') ?></small></span>
                </a>
                <p class="dashboard-side-label">WORKSPACE</p>
                <nav class="dashboard-side-nav" aria-label="Dashboard sections">
                    <a class="is-current" href="<?= hsUrl('pages/dashboard.php') ?>"><span aria-hidden="true">▦</span> Dashboard</a>
                    <a href="<?= hsUrl('pages/reminders.php') ?>"><span aria-hidden="true">◷</span> Reminders</a>
                    <a href="<?= hsUrl('pages/chat.php') ?>"><span aria-hidden="true">✦</span> Wellbeing chat</a>
                    <a href="<?= hsUrl('pages/consultation.php') ?>"><span aria-hidden="true">◉</span> Voice &amp; video</a>
                    <a href="<?= hsUrl('pages/lab-tests.php') ?>"><span aria-hidden="true">⌕</span> Lab test requests</a>
                    <a href="<?= hsUrl('pages/medicine-orders.php') ?>"><span aria-hidden="true">＋</span> Pharmacy support</a>
                    <a href="<?= hsUrl('pages/nearby-healthcare.php') ?>"><span aria-hidden="true">⌖</span> Nearby care</a>
                </nav>
                <p class="dashboard-side-label dashboard-side-secondary">ACCOUNT</p>
                <nav class="dashboard-side-nav" aria-label="Account options">
                    <a href="<?= hsUrl('pages/profile.php') ?>"><span aria-hidden="true">◎</span> My profile</a>
                    <a href="<?= hsUrl('pages/medical-history.php') ?>"><span aria-hidden="true">▤</span> Medical history</a>
                    <a href="<?= hsUrl('pages/emergency-contacts.php') ?>"><span aria-hidden="true">♡</span> Emergency contact</a>
                    <a href="<?= hsUrl('pages/logout.php') ?>"><span aria-hidden="true">↪</span> Log out</a>
                </nav>
                <a class="dashboard-side-profile" href="<?= hsUrl('pages/profile.php') ?>">
                    <span class="dashboard-side-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($fullName, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                    <span><strong><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></strong><small><?= $ageGroup === 'child' ? 'Child & caregiver' : ($ageGroup === 'senior' ? 'AgeWell member' : 'Adult member') ?></small></span>
                </a>
            </aside>

            <div class="dashboard-workspace">
                <div class="dashboard-topbar">
                    <div class="dashboard-topbar-context">
                        <strong><?= htmlspecialchars($ageGroup === 'child' ? 'My care space' : ($ageGroup === 'senior' ? 'Your AgeWell space' : 'Your health space'), ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>Today is <?= htmlspecialchars(date('l, d M Y'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="dashboard-topbar-actions">
                        <span class="dashboard-date-chip" aria-label="Current date">▦ <?= htmlspecialchars(date('d M Y'), ENT_QUOTES, 'UTF-8') ?></span>
                        <a class="dashboard-top-profile" href="<?= hsUrl('pages/profile.php') ?>">My profile</a>
                    </div>
                </div>

        <div class="dashboard-welcome-banner" id="overview">
            <div>
                <p><?= htmlspecialchars($dashboardPersona['eyebrow'], ENT_QUOTES, 'UTF-8') ?></p>
                <h1><?= htmlspecialchars($dashboardPersona['welcome'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>!</h1>
                <span><?= htmlspecialchars($dashboardPersona['intro'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <span class="dashboard-welcome-art" aria-hidden="true"><?= $ageGroup === 'child' ? '🌼' : ($ageGroup === 'senior' ? '☀' : '✦') ?></span>
        </div>

        <div class="dashboard-overview-grid">
            <div class="dashboard-primary-column">
                <div class="dashboard-summary-row">
                    <a class="dashboard-profile-panel" href="<?= hsUrl('pages/profile.php') ?>">
                        <span class="dashboard-summary-icon" aria-hidden="true">◎</span>
                        <span>
                            <small><?= htmlspecialchars($dashboardPersona['profileLabel'], ENT_QUOTES, 'UTF-8') ?></small>
                            <strong>My Health Profile</strong>
                            <span>View your saved personal information</span>
                        </span>
                        <span class="dashboard-summary-arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="dashboard-profile-panel dashboard-summary-tasks" href="<?= hsUrl('pages/reminders.php') ?>">
                        <span class="dashboard-summary-icon" aria-hidden="true">✓</span>
                        <span>
                            <small>CARE ROUTINE</small>
                            <strong><?= count($calendarReminders) ?> reminders this month</strong>
                            <span>Open your reminder list</span>
                        </span>
                        <span class="dashboard-summary-arrow" aria-hidden="true">→</span>
                    </a>
                </div>

                <section class="dashboard-analytics-card" aria-labelledby="analyticsTitle">
                    <div class="dashboard-widget-heading">
                        <div>
                            <h2 id="analyticsTitle">Reminder overview</h2>
                            <p>Active reminders by month in <?= htmlspecialchars($calendarMonth->format('Y'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="dashboard-analytics-total"><?= array_sum($monthlyReminderCounts) ?> total</span>
                    </div>
                    <div class="reminder-chart" role="img" aria-label="Active reminder counts for each month">
                        <?php foreach ($monthlyReminderCounts as $monthNumber => $reminderCount): ?>
                            <?php $barHeight = $reminderCount > 0 ? max(12, min(100, (int) round($reminderCount * 100 / max(1, max($monthlyReminderCounts))))) : 8; ?>
                            <div class="reminder-chart-column" title="<?= htmlspecialchars(date('F', mktime(0, 0, 0, $monthNumber, 1)), ENT_QUOTES, 'UTF-8') ?>: <?= $reminderCount ?> active reminders">
                                <span class="reminder-chart-value"><?= $reminderCount > 0 ? $reminderCount : '' ?></span>
                                <span class="reminder-chart-bar<?= $monthNumber === (int) $calendarMonth->format('n') ? ' is-current' : '' ?>" style="--bar-height: <?= $barHeight ?>%"></span>
                                <small><?= htmlspecialchars(date('M', mktime(0, 0, 0, $monthNumber, 1)), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dashboard-chart-footer"><span>Personal reminders only</span><a href="<?= hsUrl('pages/reminders.php') ?>">Manage reminders &rarr;</a></div>
                </section>

            </div>

            <aside class="dashboard-side-column">
                <section class="dashboard-calendar" aria-labelledby="calendarTitle">
                    <div class="calendar-heading">
                        <div>
                            <div class="dashboard-widget-heading">
                                <h2 id="calendarTitle"><?= htmlspecialchars($calendarMonth->format('F Y'), ENT_QUOTES, 'UTF-8') ?></h2>
                                <div class="calendar-controls">
                                    <a class="btn btn-outline" href="<?= hsUrl('pages/dashboard.php') ?>?month=<?= $previousMonth ?>" aria-label="Previous month">‹</a>
                                    <a class="btn btn-outline" href="<?= hsUrl('pages/dashboard.php') ?>?month=<?= $nextMonth ?>" aria-label="Next month">›</a>
                                </div>
                            </div>
                            <p><?= htmlspecialchars($dashboardPersona['calendarIntro'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <a class="btn btn-primary" href="<?= hsUrl('pages/reminders.php') ?>">+ Add reminder</a>
                    </div>
                    <div class="calendar-grid calendar-weekdays" aria-hidden="true">
                        <?php foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $weekday): ?><span><?= $weekday ?></span><?php endforeach; ?>
                    </div>
                    <div class="calendar-grid calendar-days" id="calendarDays">
                        <?php for ($cell = 0; $cell < $calendarCellCount; $cell++): ?>
                            <?php
                            $day = $cell - $calendarOffset + 1;
                            $isCurrentMonth = $day >= 1 && $day <= $calendarDays;
                            $dateValue = $isCurrentMonth ? $calendarMonth->setDate(
                                (int) $calendarMonth->format('Y'),
                                (int) $calendarMonth->format('n'),
                                $day
                            )->format('Y-m-d') : '';
                            $dayReminders = $dateValue !== '' ? ($remindersByDate[$dateValue] ?? []) : [];
                            ?>
                            <?php if ($isCurrentMonth): ?>
                                <button class="calendar-day <?= $dateValue === date('Y-m-d') ? 'is-today' : '' ?>" type="button" data-date="<?= $dateValue ?>">
                                    <span><?= $day ?></span>
                                    <?php if ($dayReminders): ?><span class="calendar-event-count"><?= count($dayReminders) ?></span><?php endif; ?>
                                </button>
                            <?php else: ?>
                                <span class="calendar-day is-empty" aria-hidden="true"></span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="calendar-selected">
                        <h3 id="selectedDateTitle">Select a date</h3>
                        <div id="selectedDateEvents" aria-live="polite"></div>
                    </div>
                    <div class="calendar-notice" id="reminderNotice" role="status" aria-live="polite"></div>
                    <button type="button" class="btn btn-outline" id="enableReminderNotifications">Enable reminder notifications</button>
                    <p class="field-help">Keep the dashboard open and allow browser notifications for alerts.</p>
                    <input type="hidden" id="reminderCsrf" value="<?= htmlspecialchars($pageCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <script type="application/json" id="calendarData"><?= $remindersJson ?: '[]' ?></script>
                </section>
                <div class="dashboard-side-help">
                    <span aria-hidden="true">♡</span>
                    <strong>Need help now?</strong>
                    <p>For emergencies in India, call 112.</p>
                    <a href="tel:112">Call 112</a>
                </div>
            </aside>
        </div>
            </div>
        </div>

    <?php endif; ?>

</section>

<?php if ($errorMessage === ''): ?>
<script>
(() => {
    const reminders = JSON.parse(document.getElementById('calendarData').textContent);
    const eventContainer = document.getElementById('selectedDateEvents');
    const selectedTitle = document.getElementById('selectedDateTitle');
    const csrfToken = document.getElementById('reminderCsrf').value;
    const notice = document.getElementById('reminderNotice');
    const endpoint = <?= json_encode(hsUrl('pages/reminder-alerts.php')) ?>;

    function renderDate(date) {
        selectedTitle.textContent = new Date(`${date}T12:00:00`).toLocaleDateString(undefined, {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
        eventContainer.replaceChildren();
        const events = reminders.filter(item => item.reminder_datetime.slice(0, 10) === date);
        if (!events.length) {
            eventContainer.textContent = 'No reminders or lab requests on this date.';
            return;
        }
        events.forEach(item => {
            const card = document.createElement('div');
            card.className = `calendar-event${item.event_type === 'lab_request' ? ' calendar-event-lab' : ''}`;
            const title = document.createElement('strong');
            title.textContent = item.reminder_title;
            const when = document.createElement('span');
            when.textContent = item.event_type === 'lab_request'
                ? `Preferred date only · not confirmed${item.lab_name ? ` · ${item.lab_name}` : ''}`
                : `${new Date(item.reminder_datetime.replace(' ', 'T')).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})} · ${item.reminder_type}`;
            card.append(title, when);
            eventContainer.append(card);
        });
    }

    document.querySelectorAll('.calendar-day[data-date]').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.calendar-day.is-selected').forEach(day => day.classList.remove('is-selected'));
            button.classList.add('is-selected');
            renderDate(button.dataset.date);
        });
    });
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    const todayButton = document.querySelector(`.calendar-day[data-date="${today}"]`);
    const firstButton = document.querySelector('.calendar-day[data-date]');
    if (todayButton) todayButton.click();
    else if (firstButton) firstButton.click();

    async function postReminder(action, reminderId = '') {
        const body = new URLSearchParams({action, csrf_token: csrfToken, reminder_id: reminderId});
        const response = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Reminder action failed.');
        return result;
    }

    document.getElementById('enableReminderNotifications').addEventListener('click', async () => {
        if (!('Notification' in window)) {
            notice.textContent = 'This browser does not support desktop notifications. Keep the dashboard open to see in-page reminder alerts.';
            return;
        }
        const permission = await Notification.requestPermission();
        notice.textContent = permission === 'granted'
            ? 'Notifications enabled. Keep this dashboard open for reminders.'
            : 'Notifications are blocked. Allow them in browser settings to receive alerts.';
    });

    async function checkDueReminders() {
        try {
            const result = await postReminder('due');
            result.reminders.forEach(reminder => {
                const isLabAppointment = reminder.reminder_title.startsWith('Lab appointment reminder:');
                const title = isLabAppointment ? 'Lab appointment reminder' : `Reminder: ${reminder.reminder_title}`;
                const message = isLabAppointment
                    ? `${reminder.reminder_title.replace('Lab appointment reminder:', '').trim()} is scheduled for now. This is your reminder only; the lab has not confirmed an appointment.`
                    : `It is time for your ${reminder.reminder_type} reminder.`;
                notice.textContent = `${title}. ${message}`;
                const done = document.createElement('button');
                done.type = 'button';
                done.className = 'btn btn-primary';
                done.textContent = 'Mark as done';
                done.addEventListener('click', async () => {
                    try {
                        await postReminder('complete', String(reminder.reminder_id));
                        done.disabled = true;
                        done.textContent = 'Done';
                    } catch (error) {
                        notice.textContent = error.message;
                    }
                });
                eventContainer.prepend(done);
                if ('Notification' in window && Notification.permission === 'granted') {
                    const notification = new Notification(title, {body: message, tag: `healthsphere-${reminder.reminder_id}`});
                    notification.onclick = () => {
                        window.focus();
                        notification.close();
                    };
                }
            });
        } catch (error) {
            notice.textContent = error.message;
        }
    }
    checkDueReminders();
    window.setInterval(checkDueReminders, 5000);
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
