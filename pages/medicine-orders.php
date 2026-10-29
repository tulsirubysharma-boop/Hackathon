<?php
require_once __DIR__ . '/../includes/user-page.php';
require_once __DIR__ . '/../includes/notifications.php';

$pageTitle = 'Medicine ordering';
$message = '';
$errorMessage = '';
$medicineSaved = false;
$savedMedicineQuery = $pdo->prepare(
    "SELECT DISTINCT reminder_title
     FROM reminders
     WHERE user_id = :user_id
       AND reminder_type = 'medicine'
       AND status != 'cancelled'
     ORDER BY reminder_title
     LIMIT 100"
);
$savedMedicineQuery->execute(['user_id' => $userId]);
$savedMedicines = array_map(
    static fn (array $row): string => (string) $row['reminder_title'],
    $savedMedicineQuery->fetchAll()
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hsRequireCsrf();
    if (($_SESSION['age_group'] ?? '') === 'child') {
        $errorMessage = 'A parent or guardian must handle medicine requests using an adult account.';
    } else {
        $concern = (string) ($_POST['concern'] ?? '');
        $concernLabels = [
            'headache' => 'Headache',
            'cough' => 'Cough',
            'cold' => 'Cold-like symptoms',
            'fever' => 'Fever',
            'stomach' => 'Stomach discomfort',
            'pain' => 'Muscle or joint discomfort'
        ];
        $medicineChoice = (string) ($_POST['medicine_choice'] ?? '');
        $itemDescription = trim((string) ($_POST['custom_medicine'] ?? ''));
        if ($medicineChoice === 'saved') {
            $itemDescription = trim((string) ($_POST['saved_medicine'] ?? ''));
            if (!in_array($itemDescription, $savedMedicines, true)) {
                $errorMessage = 'Select one of your saved medicine reminders.';
            }
        } elseif ($medicineChoice !== 'custom') {
            $errorMessage = 'Choose a saved medicine reminder or enter a clinician-recommended medicine.';
        }
        if (!isset($concernLabels[$concern])) {
            $errorMessage = 'Choose a health concern.';
        } elseif ($errorMessage === '' && ($itemDescription === '' || mb_strlen($itemDescription) > 180)) {
            $errorMessage = 'Enter a medicine already recommended to you.';
        }
        if ($errorMessage === '') {
            try {
                $insert = $pdo->prepare(
                    "INSERT INTO demo_medicine_orders (user_id, item_description)
                     VALUES (:user_id, :item_description)"
                );
                $insert->execute(['user_id' => $userId, 'item_description' => $itemDescription]);
                hsLogMedicalEvent(
                    $pdo,
                    $userId,
                    'medicine request summary',
                    "Medicine request saved for {$itemDescription} under concern {$concernLabels[$concern]}. No purchase was placed."
                );
                $medicineSaved = true;
                $message = 'Medicine request saved.';
            } catch (Throwable $exception) {
                error_log('HealthSphere medicine request error: ' . $exception->getMessage());
                $errorMessage = 'The medicine request summary could not be saved. Check the database setup and try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-section">
    <div class="section-heading">
        <p class="eyebrow">MEDICINES, SAFELY</p>
        <h1>Medicine ordering</h1>
        <p>Choose only a medicine already recommended by your clinician or pharmacist. This saves a local request note; it does not place or confirm a purchase.</p>
    </div>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= hsEscape($message) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><?= hsEscape($errorMessage) ?></div><?php endif; ?>
    <?php if (($_SESSION['age_group'] ?? '') === 'child'): ?>
        <div class="alert alert-error" role="note">A parent or guardian must handle medicine decisions and pharmacy orders. This page does not choose a medicine for a child.</div>
    <?php endif; ?>
    <div class="profile-card">
        <h2>Save a medicine request</h2>
        <p>A symptom cannot safely determine a medicine. The medicine list below comes only from your saved medicine reminders; or enter a medicine your clinician or pharmacist has already recommended.</p>
        <?php if (($_SESSION['age_group'] ?? '') !== 'child'): ?>
        <form method="post" class="health-form" id="medicineRequestForm">
            <input type="hidden" name="csrf_token" value="<?= hsEscape($pageCsrfToken) ?>">
            <div class="form-field medicine-concern-field">
                <label for="medicineConcern">What are you feeling?</label>
                <select id="medicineConcern" name="concern" required>
                    <option value="">Choose a concern</option>
                    <option value="headache">Headache</option>
                    <option value="cough">Cough</option>
                    <option value="cold">Cold-like symptoms</option>
                    <option value="fever">Fever</option>
                    <option value="stomach">Stomach discomfort</option>
                    <option value="pain">Muscle or joint discomfort</option>
                </select>
                <p class="field-help" id="medicineConcernGuidance" role="status">A pharmacist or clinician can help choose an appropriate treatment after checking your age, health history, allergies, and other medicines.</p>
            </div>
            <div class="form-field">
                <label for="medicineChoice">Medicine choice</label>
                <select id="medicineChoice" name="medicine_choice" required>
                    <option value="">Choose how to select a medicine</option>
                    <?php if ($savedMedicines): ?><option value="saved">Choose from my saved medicine reminders</option><?php endif; ?>
                    <option value="custom">Enter a medicine already recommended by a clinician</option>
                </select>
            </div>
            <div class="form-field" id="savedMedicineField" hidden>
                <label for="savedMedicine">Saved medicine reminder</label>
                <select id="savedMedicine" name="saved_medicine" <?= $savedMedicines ? '' : 'disabled' ?>>
                    <option value="">Choose a saved medicine</option>
                    <?php foreach ($savedMedicines as $savedMedicine): ?>
                        <option value="<?= hsEscape($savedMedicine) ?>"><?= hsEscape($savedMedicine) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$savedMedicines): ?><p class="field-help">No medicine reminders are saved yet. Choose the clinician-recommended entry option.</p><?php endif; ?>
            </div>
            <div class="form-field" id="customMedicineField" hidden>
                <label for="customMedicine">Clinician-recommended medicine</label>
                <input id="customMedicine" name="custom_medicine" maxlength="180" placeholder="Enter the name from your prescription or pharmacist" disabled>
            </div>
            <button class="btn btn-primary" type="submit">Save medicine request</button>
            <p class="field-help" id="medicineNotificationStatus" role="status" aria-live="polite">Allow browser notifications to receive a laptop confirmation after saving.</p>
        </form>
        <?php else: ?>
            <p class="field-help">A parent or guardian can use an adult account to contact a licensed pharmacy.</p>
        <?php endif; ?>
    </div>
    <div class="disclaimer" style="margin-top:24px">
        No medicine is ordered, purchased, dispensed, or delivered from this page.
    </div>
</section>
<?php if (($_SESSION['age_group'] ?? '') !== 'child'): ?>
<script>
    (() => {
        const form = document.getElementById('medicineRequestForm');
        const choice = document.getElementById('medicineChoice');
        const savedField = document.getElementById('savedMedicineField');
        const savedSelect = document.getElementById('savedMedicine');
        const customField = document.getElementById('customMedicineField');
        const customInput = document.getElementById('customMedicine');
        const notificationStatus = document.getElementById('medicineNotificationStatus');
        let permissionResolved = false;

        function updateMedicineChoice() {
            const useSaved = choice.value === 'saved';
            const useCustom = choice.value === 'custom';
            savedField.hidden = !useSaved;
            savedSelect.required = useSaved;
            savedSelect.disabled = !useSaved || savedSelect.options.length < 2;
            customField.hidden = !useCustom;
            customInput.required = useCustom;
            customInput.disabled = !useCustom;
        }
        choice.addEventListener('change', updateMedicineChoice);
        updateMedicineChoice();

        const concern = document.getElementById('medicineConcern');
        const guidance = document.getElementById('medicineConcernGuidance');
        const generalGuidance = 'A pharmacist or clinician can help choose an appropriate treatment after checking your age, health history, allergies, and other medicines.';
        const messages = {
            headache: 'Headaches have different causes. Seek urgent care for sudden severe pain, weakness, confusion, vision changes, or a head injury. A clinician or pharmacist should advise about medicines.',
            cough: 'Coughs can have different causes. Seek urgent care for breathing difficulty, chest pain, blue lips, or severe worsening. Ask a clinician or pharmacist before choosing medicine.',
            cold: 'Cold-like symptoms can have different causes. Rest and fluids may support general comfort; ask a clinician or pharmacist before choosing medicine.',
            fever: 'Fever needs extra care for children, older adults, or people with other health conditions. Seek urgent care for severe symptoms and ask a clinician or pharmacist about treatment.',
            stomach: 'Stomach discomfort can have many causes. Severe pain, blood, repeated vomiting, or fainting needs urgent care. Ask a clinician or pharmacist before taking medicine.',
            pain: 'Muscle or joint discomfort can have different causes. Severe swelling, weakness, injury, or persistent pain should be assessed by a clinician before choosing medicine.'
        };
        concern.addEventListener('change', () => {
            guidance.textContent = messages[concern.value] || generalGuidance;
        });

            form.addEventListener('submit', async event => {
                if (permissionResolved) return;
                permissionResolved = true;
                if (!('Notification' in window) || Notification.permission !== 'default') return;

                event.preventDefault();
                try {
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        notificationStatus.textContent = 'Browser notifications are blocked. The request will still be saved; allow notifications in browser settings for laptop alerts.';
                    }
                } catch (error) {
                    notificationStatus.textContent = 'The notification permission could not be requested. The request will still be saved.';
                }
                form.requestSubmit(event.submitter);
            });

            <?php if ($medicineSaved): ?>
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Medicine request saved', {
                    body: 'Saved locally. No medicine was ordered or purchased.',
                    tag: 'healthsphere-medicine-request'
                });
            }
            <?php endif; ?>
        })();
    </script>
    <?php endif; ?>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
