<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/user-page.php';

header('Content-Type: application/json; charset=utf-8');

function hsTranscriptionResponse(int $statusCode, array $response): void
{
    http_response_code($statusCode);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    hsTranscriptionResponse(405, ['success' => false, 'message' => 'Use POST to transcribe a recording.']);
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (
    !is_string($submittedToken) ||
    empty($_SESSION['page_csrf_token']) ||
    !hash_equals((string) $_SESSION['page_csrf_token'], $submittedToken)
) {
    hsTranscriptionResponse(403, ['success' => false, 'message' => 'Refresh the page and try again.']);
}

$apiKey = getenv('HEALTHSPHERE_OPENAI_API_KEY');
if (!is_string($apiKey) || trim($apiKey) === '') {
    $privateConfigPath = 'C:\\xampp\\healthsphere-private\\healthsphere-ai.php';
    if (is_file($privateConfigPath)) {
        $privateConfig = require $privateConfigPath;
        if (is_array($privateConfig)) {
            $apiKey = $privateConfig['openai_api_key'] ?? '';
        }
    }
}
if (!is_string($apiKey) || trim($apiKey) === '') {
    hsTranscriptionResponse(503, [
        'success' => false,
        'message' => 'Cloud transcription is not configured. Add your OpenAI API key to the private server config, then try again.'
    ]);
}

if (!extension_loaded('curl') || !class_exists('CURLFile') || !class_exists('finfo')) {
    error_log('HealthSphere audio transcription requires PHP cURL and fileinfo extensions.');
    hsTranscriptionResponse(500, ['success' => false, 'message' => 'Audio transcription is unavailable on this server.']);
}

$audio = $_FILES['audio'] ?? null;
if (!is_array($audio) || !isset($audio['error'], $audio['tmp_name'], $audio['size'])) {
    hsTranscriptionResponse(400, ['success' => false, 'message' => 'Select a voice recording to transcribe.']);
}
if ((int) $audio['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $audio['tmp_name'])) {
    hsTranscriptionResponse(400, ['success' => false, 'message' => 'The recording upload failed. Try recording again.']);
}

$maximumAudioBytes = 20 * 1024 * 1024;
if ((int) $audio['size'] < 1 || (int) $audio['size'] > $maximumAudioBytes) {
    hsTranscriptionResponse(413, ['success' => false, 'message' => 'Keep recordings under 20 MB, then try again.']);
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file((string) $audio['tmp_name']);
$fileExtensions = [
    'audio/webm' => 'webm',
    'video/webm' => 'webm',
    'audio/ogg' => 'ogg',
    'application/ogg' => 'ogg',
    'audio/mp4' => 'm4a',
    'video/mp4' => 'm4a',
    'audio/mpeg' => 'mp3',
    'audio/mp3' => 'mp3',
    'audio/wav' => 'wav',
    'audio/x-wav' => 'wav',
    'audio/vnd.wave' => 'wav',
    'audio/x-m4a' => 'm4a'
];
if (!is_string($mimeType) || !isset($fileExtensions[$mimeType])) {
    hsTranscriptionResponse(415, ['success' => false, 'message' => 'This recording format is not supported. Record again using the browser audio controls.']);
}

$locales = [
    'en-IN' => 'en',
    'hi-IN' => 'hi',
    'bn-IN' => 'bn',
    'ta-IN' => 'ta',
    'te-IN' => 'te',
    'mr-IN' => 'mr',
    'gu-IN' => 'gu',
    'kn-IN' => 'kn',
    'ml-IN' => 'ml',
    'pa-IN' => 'pa',
    'ur-IN' => 'ur'
];
$requestedLocale = $_POST['language'] ?? '';
if (!is_string($requestedLocale) || !isset($locales[$requestedLocale])) {
    hsTranscriptionResponse(422, ['success' => false, 'message' => 'Choose a supported transcription language.']);
}

$upload = new CURLFile(
    (string) $audio['tmp_name'],
    $mimeType,
    'healthsphere-recording.' . $fileExtensions[$mimeType]
);
$curl = curl_init('https://api.openai.com/v1/audio/transcriptions');
if ($curl === false) {
    error_log('HealthSphere could not initialize the audio transcription request.');
    hsTranscriptionResponse(500, ['success' => false, 'message' => 'Audio transcription could not start.']);
}

set_time_limit(90);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'file' => $upload,
        'model' => 'gpt-4o-mini-transcribe',
        'language' => $locales[$requestedLocale],
        'response_format' => 'json'
    ],
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . trim($apiKey),
        'Accept: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 75,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$providerResponse = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if (!is_string($providerResponse)) {
    error_log('HealthSphere audio transcription transport failed: ' . $curlError);
    hsTranscriptionResponse(502, [
        'success' => false,
        'message' => 'Could not reach the transcription service. Your local recording is still available; try again or type your concern.'
    ]);
}

$decodedResponse = json_decode($providerResponse, true);
if ($httpStatus < 200 || $httpStatus >= 300) {
    error_log('HealthSphere audio transcription provider returned HTTP ' . $httpStatus . '.');
    $message = $httpStatus === 401 || $httpStatus === 403
        ? 'The OpenAI API key was rejected. Check the private server configuration.'
        : 'The transcription service could not process this recording. Your local recording is still available; try again or type your concern.';
    hsTranscriptionResponse(502, ['success' => false, 'message' => $message]);
}

$transcribedText = is_array($decodedResponse) ? ($decodedResponse['text'] ?? null) : null;
if (!is_string($transcribedText) || trim($transcribedText) === '') {
    hsTranscriptionResponse(422, [
        'success' => false,
        'message' => 'No words were recognized. Try speaking closer to the microphone or type your concern.'
    ]);
}

hsTranscriptionResponse(200, ['success' => true, 'text' => trim($transcribedText)]);
