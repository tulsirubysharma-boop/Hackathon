# HealthSphere hackathon MVP

HealthSphere is a PHP 8 / MySQL demo for age-aware wellbeing profiles, general health chat, personal reminders, emergency contacts, map searches, lab-request tracking, and optional affiliate destinations.

## Run locally with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/` or MySQL Workbench and select the existing `healthsphere_db` database.
3. If this is a fresh database, import `healthsphere_database.sql`. The current local XAMPP database has already been updated with reminder alerts and activity/history tables. On a separate existing install, run `healthsphere_upgrade.sql` once; its reminder-column migration is not intended to be run twice.
4. PHP first checks the private local file `C:\xampp\healthsphere-private\healthsphere-db.php` for connection settings. You can instead override individual settings with environment variables below. Keep passwords outside the web root and never commit them:
   - `HEALTHSPHERE_BASE_PATH` (defaults to `/Hackathon`; set to an empty value if the app is served from the domain root)
   - `HEALTHSPHERE_DB_HOST` (defaults to `127.0.0.1`)
   - `HEALTHSPHERE_DB_PORT` (defaults to `3306`)
   - `HEALTHSPHERE_DB_NAME` (defaults to `healthsphere_db`)
   - `HEALTHSPHERE_DB_USER` (defaults to `root`)
   - `HEALTHSPHERE_DB_PASSWORD` (defaults to an empty password)
5. Visit `http://localhost/Hackathon/`, create an account, and sign in. Accounts for users under 18 require a parent/guardian's details.

Never put database passwords or API secrets in a public repository. The app deliberately reads credentials from the server environment and does not contain a default password.

For Nginx hosting, configure the site to pass PHP requests to PHP-FPM and point the document root at the app's public files. HealthSphere is a standalone PHP application, not a WordPress plugin or theme. Set `HEALTHSPHERE_BASE_PATH` to the URL path where it is mounted.

## Included demo workflows

- **Age-aware accounts:** age groups are calculated from date of birth (child 0–11, adult 12–49, senior 50+). Login asks you to choose the matching care space; the saved date-of-birth group is checked before access. Child, adult, and senior accounts receive distinct dashboard styling, with larger text and controls for seniors and a friendly child-and-caregiver theme.
- **Chat and voice:** chat history is saved per account. Browser speech recognition/playback offers Indian locale options; availability depends on the browser and device. The interface remains English; scripted chat replies are localized for the supported Indian locales. A language change while a reply is processing updates that reply before it is displayed or spoken. The assistant is a local scripted prototype, not a connected AI model, and does not diagnose, assess images/video, or prescribe medicines.
- **Reminders:** create, complete, and delete personal reminders; repeating reminders advance when completed; download an `.ics` event to add an item to a calendar. Dashboard reminders are checked every five seconds and can notify while the dashboard is open. Browser timers/notifications are not reliable when the browser is closed, the device sleeps, or notification permission is denied.
- **Emergency contacts:** save one contact. The emergency-email button attempts to email the saved address via the configured mail transport. SMS opens the device's SMS app and still requires the user to tap Send; automatic SMS delivery needs an SMS provider. HealthSphere does not continuously listen for distress or guarantee message delivery. For emergencies in India, call **112**.
- **Nearby care:** location is requested only after the user presses the location button. A Google Maps view and nearby hospital/pharmacy searches are shown after permission.
- **Lab tests:** save and cancel a request with a preferred date and time. Saving also creates a personal appointment reminder; allow browser notifications and keep the dashboard open at the selected time to receive a laptop alert. The request does not book a laboratory, collect a sample, or confirm an appointment/home visit.
- **Medicine requests:** symptom selection gives general safety guidance and never selects a drug. The medicine dropdown contains names from the account's existing medicine reminders; alternatively enter a medicine already recommended by a clinician or pharmacist. Saving records a local request note and can show a browser notification; it sends no email and does not place an order or purchase.
- **Email notifications:** saved reminder notifications are sent to the email on the signed-in account using PHP `mail()`. Emergency-contact email is addressed to the saved contact. Lab requests and medicine request notes do not send email. A successful `mail()` call means it was accepted by the local mail transport, not that it reached an inbox. The local XAMPP Gmail relay must be configured before email works:
  1. In the Google account that will send HealthSphere mail, enable 2-Step Verification and create an **App Password** (do not use your regular Gmail password).
  2. In `C:\xampp\sendmail\sendmail.ini`, set `smtp_server=smtp.gmail.com`, `smtp_port=587`, `smtp_ssl=tls`, `auth_username=` and `force_sender=` to the sending Gmail address, and `auth_password=` to the Google App Password (enter the 16 characters without spaces).
  3. In `C:\xampp\php\php.ini`, set `sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"` and restart Apache from the XAMPP Control Panel.
  4. Never paste the App Password into chat or put it in this repository. Keep it only in the local XAMPP config file.
- **Video wellbeing demo:** camera preview and audio recording stay local. While recording, supported browser speech recognition writes recognized words into the editable text box; it continues across pauses. Transcription depends on the browser speech service and an internet connection. Only submitted text reaches the local scripted chat. There is no live clinician/video call, connected AI, or video analysis. Some familiar symptoms receive cautious general information; this is not diagnosis or treatment advice.
- **History and account menu:** account options are under “My profile”; chat history is grouped by date and each date opens that day's saved conversation.

## Production and integration requirements

This is a hackathon prototype, not a medical device or production telehealth service. Real clinician video consultations, a medically reviewed AI service, SMS/email alert delivery, pharmacy ordering, and lab bookings require separately selected providers, contracts/credentials, consent flows, security review, and jurisdiction-specific compliance. Do not send identifiable health data to an AI API without the required approvals and safeguards. Never use AI output to prescribe medication, especially for children.
