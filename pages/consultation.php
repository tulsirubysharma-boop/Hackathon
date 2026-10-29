<?php
require_once __DIR__ . '/../includes/user-page.php';

if (empty($_SESSION['chat_csrf_token'])) {
    $_SESSION['chat_csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Video wellbeing consultation';
$csrfToken = $_SESSION['chat_csrf_token'];
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-section consultation-page">
    <div class="section-heading">
        <p class="eyebrow">VOICE + VIDEO DEMO</p>
        <h1>Video wellbeing consultation</h1>
        <p>Record your concern and convert it into editable words. Cloud transcription sends the audio to OpenAI; HealthSphere does not save the recording. Only the text you submit is sent to the local HealthSphere wellbeing demo.</p>
    </div>
    <div class="alert alert-error" role="note">
        <strong>Prototype, not medical care:</strong>
        this is not a video call with a clinician or connected AI. It cannot
        diagnose, assess symptoms from video, or recommend medicines. It can
        offer general information, not possible medical outcomes. In an
        emergency in India, call <a href="tel:112">112</a>.
    </div>
    <div class="consultation-grid">
        <article class="feature-card consultation-video-card">
            <h2>Camera preview</h2>
            <video id="cameraPreview" autoplay muted playsinline></video>
            <p id="cameraStatus" role="status">Camera is off. Start preview only if you choose.</p>
            <div class="hero-actions">
                <button class="btn btn-primary" type="button" id="startCamera">Start camera preview</button>
                <button class="btn btn-outline" type="button" id="stopCamera" disabled>Turn camera off</button>
            </div>
        </article>
        <article class="feature-card consultation-chat-card">
            <h2>Speak or type your concern</h2>
            <div class="form-field">
                <label for="consultationLanguage">Language for voice and demo replies</label>
                <select id="consultationLanguage">
                    <option value="en-IN">English</option><option value="hi-IN">हिन्दी</option>
                    <option value="bn-IN">বাংলা</option><option value="ta-IN">தமிழ்</option>
                    <option value="te-IN">తెలుగు</option><option value="mr-IN">मराठी</option>
                    <option value="gu-IN">ગુજરાતી</option><option value="kn-IN">ಕನ್ನಡ</option>
                    <option value="ml-IN">മലയാളം</option><option value="pa-IN">ਪੰਜਾਬੀ</option>
                    <option value="ur-IN">اردو</option>
                </select>
            </div>
            <div class="consultation-transcript" id="consultationTranscript" aria-live="polite">
                Your conversation will appear here.
            </div>
            <form id="consultationForm">
                <div class="form-field">
                    <label for="symptomInput">What would you like general information about?</label>
                    <textarea id="symptomInput" rows="4" maxlength="4000" required placeholder="Describe what you are experiencing. Avoid names, address, or other identifying details."></textarea>
                </div>
                <div class="hero-actions">
                    <button class="btn btn-outline" type="button" id="consultationMic">Start voice recording</button>
                    <button class="btn btn-primary" type="submit" id="consultationSend">Get general information</button>
                </div>
            </form>
            <label class="consultation-speech-toggle"><input type="checkbox" id="cloudTranscriptionConsent"> Send this recording to OpenAI to convert it into words. HealthSphere will not save the audio.</label>
            <div class="consultation-recording" id="consultationRecording" hidden>
                <label for="consultationAudio">Your local voice recording</label>
                <audio id="consultationAudio" controls></audio>
                <a id="downloadConsultationAudio" class="btn btn-outline" download>Download recording</a>
            </div>
            <label class="consultation-speech-toggle"><input type="checkbox" id="consultationSpeak" checked> Speak the response aloud</label>
            <p id="consultationStatus" role="status" aria-live="polite">Press Start voice recording and speak. After you stop, select the OpenAI option above to transcribe the saved recording into the text box.</p>
        </article>
    </div>
</section>
<script>
(() => {
    const video = document.getElementById('cameraPreview');
    const cameraStatus = document.getElementById('cameraStatus');
    const startCamera = document.getElementById('startCamera');
    const stopCamera = document.getElementById('stopCamera');
    const language = document.getElementById('consultationLanguage');
    const input = document.getElementById('symptomInput');
    const form = document.getElementById('consultationForm');
    const transcript = document.getElementById('consultationTranscript');
    const status = document.getElementById('consultationStatus');
    const mic = document.getElementById('consultationMic');
    const send = document.getElementById('consultationSend');
    const recordingPanel = document.getElementById('consultationRecording');
    const audioPreview = document.getElementById('consultationAudio');
    const downloadRecording = document.getElementById('downloadConsultationAudio');
    const transcriptionConsent = document.getElementById('cloudTranscriptionConsent');
    const csrf = <?= json_encode($csrfToken) ?>;
    let stream = null;
    let voiceStream = null;
    let mediaRecorder = null;
    let audioChunks = [];
    let recordingUrl = '';
    let recognition = null;
    let listening = false;
    let recording = false;
    let finalTranscript = '';
    let recordingInitialText = '';
    let recognitionUnavailable = false;
    let recognitionRestartTimer = null;
    let processing = false;
    let currentMessage = '';
    let latestReply = '';

    startCamera.addEventListener('click', async () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraStatus.textContent = 'Camera access requires a supported browser and a secure connection (HTTPS or localhost).';
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {facingMode: 'user'},
                audio: false
            });
            video.srcObject = stream;
            await video.play();
            cameraStatus.textContent = 'Local preview is active. No video is recorded or analyzed.';
            startCamera.disabled = true;
            stopCamera.disabled = false;
        } catch (error) {
            if (error.name === 'NotFoundError') {
                cameraStatus.textContent = 'No camera was found. Connect a camera or continue by typing.';
            } else if (error.name === 'NotReadableError') {
                cameraStatus.textContent = 'The camera is busy in another app. Close that app and try again.';
            } else if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
                cameraStatus.textContent = 'Camera permission was blocked. Allow camera access for localhost in browser settings, then try again.';
            } else {
                cameraStatus.textContent = `Camera could not start (${error.name || 'unknown error'}). Check browser permissions and try again.`;
            }
        }
    });

    function turnOffCamera() {
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        cameraStatus.textContent = 'Camera is off.';
        startCamera.disabled = false;
        stopCamera.disabled = true;
    }
    stopCamera.addEventListener('click', turnOffCamera);
    window.addEventListener('pagehide', () => {
        turnOffCamera();
        if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
        if (voiceStream) voiceStream.getTracks().forEach(track => track.stop());
        if (recordingUrl) URL.revokeObjectURL(recordingUrl);
    });

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = language.value;
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.onstart = () => {
            listening = true;
            if (recording) {
                status.textContent = 'Recording locally and transcribing in the selected language. Speak clearly; press Stop recording when finished.';
            }
        };
        recognition.onresult = event => {
            let interimTranscript = '';
            for (let index = event.resultIndex; index < event.results.length; index++) {
                const transcriptPart = event.results[index][0].transcript;
                if (event.results[index].isFinal) {
                    finalTranscript += `${transcriptPart} `;
                } else {
                    interimTranscript += transcriptPart;
                }
            }
            input.value = `${finalTranscript}${interimTranscript}`.trim();
        };
        recognition.onerror = event => {
            const errors = {
                'not-allowed': 'Speech-to-text permission was blocked.',
                'no-speech': 'No speech was transcribed. Your local audio recording can continue.',
                'audio-capture': 'Speech-to-text could not access the microphone.',
                'network': 'Browser speech recognition needs a network connection.'
            };
            const reason = errors[event.error] || 'Speech-to-text is unavailable.';
            if (event.error !== 'no-speech' && event.error !== 'aborted') {
                recognitionUnavailable = true;
            }
            status.textContent = recording
                ? `${reason} Your voice is still being recorded locally. Press Stop recording to save and play it back.`
                : `${reason} You can type your concern instead.`;
        };
        recognition.onend = () => {
            listening = false;
            if (recording) {
                mic.textContent = 'Stop recording';
                if (!recognitionUnavailable) {
                    window.clearTimeout(recognitionRestartTimer);
                    recognitionRestartTimer = window.setTimeout(() => {
                        if (!recording || listening || recognitionUnavailable) return;
                        try {
                            recognition.start();
                        } catch (error) {
                            status.textContent = input.value.trim()
                                ? 'Some speech was transcribed. Recording continues; finish speaking, then review the words above.'
                                : 'Recording continues, but speech-to-text could not restart. You can finish recording and type any missing words.';
                        }
                    }, 300);
                }
                return;
            }
            mic.textContent = 'Start voice recording';
            if (input.value.trim()) {
                status.textContent = 'Transcript captured. Review it, then choose Get general information for a response.';
            }
        };
        language.addEventListener('change', () => {
            recognition.lang = language.value;
            if (listening) {
                recognition.stop();
                if (!recording) {
                    window.setTimeout(() => {
                        if (!listening && !recognitionUnavailable) {
                            try { recognition.start(); }
                            catch (error) { status.textContent = 'Language changed. Tap voice input to continue.'; }
                        }
                    }, 250);
                }
            }
            if (processing && currentMessage) {
                status.textContent = `The current reply will use ${language.options[language.selectedIndex].text}.`;
            } else if (latestReply && document.getElementById('consultationSpeak').checked) {
                speak(latestReply);
            }
        });
    }

    function stopVoiceCapture() {
        recording = false;
        window.clearTimeout(recognitionRestartTimer);
        if (listening && recognition) {
            try {
                recognition.stop();
            } catch (error) {
                status.textContent = 'Speech recognition could not be stopped cleanly. The recording will still be saved.';
            }
        }
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        } else {
            if (voiceStream) voiceStream.getTracks().forEach(track => track.stop());
            voiceStream = null;
            mic.textContent = 'Start voice recording';
            if (input.value.trim()) {
                status.textContent = 'Transcript captured. Choose Get general information for a response.';
            }
        }
    }

    async function startVoiceCapture() {
        recordingInitialText = input.value.trim();
        finalTranscript = input.value.trim() ? `${input.value.trim()} ` : '';
        recognitionUnavailable = false;
        window.clearTimeout(recognitionRestartTimer);
        recordingPanel.hidden = true;
        audioPreview.removeAttribute('src');
        downloadRecording.removeAttribute('href');
        if (recordingUrl) URL.revokeObjectURL(recordingUrl);
        recordingUrl = '';
        audioChunks = [];

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            status.textContent = 'Microphone recording requires a supported browser and a secure connection (HTTPS or localhost).';
            return;
        }

        try {
            voiceStream = await navigator.mediaDevices.getUserMedia({audio: true});
            if (typeof MediaRecorder === 'function') {
                const recorderOptions = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                    ? {mimeType: 'audio/webm;codecs=opus'}
                    : {};
                mediaRecorder = new MediaRecorder(voiceStream, recorderOptions);
                mediaRecorder.addEventListener('dataavailable', event => {
                    if (event.data.size > 0) audioChunks.push(event.data);
                });
                mediaRecorder.addEventListener('error', () => {
                    status.textContent = 'The local audio recording failed. Try again or type your concern.';
                });
                mediaRecorder.addEventListener('stop', async () => {
                    const audioType = mediaRecorder.mimeType || 'audio/webm';
                    const audioBlob = new Blob(audioChunks, {type: audioType});
                    if (audioBlob.size > 0) {
                        recordingUrl = URL.createObjectURL(audioBlob);
                        audioPreview.src = recordingUrl;
                        downloadRecording.href = recordingUrl;
                        const extension = audioType.includes('ogg') ? 'ogg' : (audioType.includes('mp4') ? 'm4a' : 'webm');
                        downloadRecording.download = `healthsphere-voice-${Date.now()}.${extension}`;
                        recordingPanel.hidden = false;
                    }
                    if (voiceStream) voiceStream.getTracks().forEach(track => track.stop());
                    voiceStream = null;
                    mediaRecorder = null;
                    mic.textContent = 'Start voice recording';
                    if (audioBlob.size > 0 && transcriptionConsent.checked) {
                        await transcribeRecording(audioBlob);
                    } else {
                        status.textContent = input.value.trim()
                            ? 'Recording saved locally. Review the words above and choose Get general information for a response.'
                            : 'Recording saved locally. To turn it into words, select the OpenAI transcription option and record again.';
                    }
                }, {once: true});
                mediaRecorder.start();
                recording = true;
                mic.textContent = 'Stop recording';
            }

            if (recognition) {
                recognition.lang = language.value;
                try {
                    recognition.start();
                } catch (error) {
                    status.textContent = recording
                        ? 'Audio is recording locally. Automatic transcription could not start; stop the recording when finished.'
                        : 'Speech recognition could not start. You can type your concern.';
                }
            } else if (recording) {
                status.textContent = 'Recording locally. Automatic speech-to-text is not supported in this browser; type your transcript to get a response.';
            } else {
                status.textContent = 'Neither local audio recording nor speech recognition is supported in this browser.';
            }
        } catch (error) {
            if (voiceStream) voiceStream.getTracks().forEach(track => track.stop());
            voiceStream = null;
            const errors = {
                'NotAllowedError': 'Microphone permission was blocked. Allow microphone access for localhost in browser settings.',
                'NotFoundError': 'No microphone was found. Connect a microphone or type your concern.',
                'NotReadableError': 'The microphone is busy in another app. Close that app and try again.'
            };
            status.textContent = errors[error.name] || `Microphone recording could not start (${error.name || 'unknown error'}).`;
            mic.textContent = 'Start voice recording';
        }
    }

    mic.addEventListener('click', () => {
        if (recording || listening) stopVoiceCapture();
        else startVoiceCapture();
    });

    async function transcribeRecording(audioBlob) {
        const audioType = audioBlob.type || 'audio/webm';
        const extension = audioType.includes('ogg') ? 'ogg' : (audioType.includes('mp4') ? 'm4a' : 'webm');
        const data = new FormData();
        data.set('csrf_token', <?= json_encode($pageCsrfToken) ?>);
        data.set('language', language.value);
        data.set('audio', audioBlob, `healthsphere-recording.${extension}`);
        mic.disabled = true;
        status.textContent = 'Sending the recording to OpenAI for transcription…';
        try {
            const response = await fetch(<?= json_encode(hsUrl('pages/transcribe-audio.php')) ?>, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            });
            const result = await response.json();
            if (!response.ok || !result.success || !result.text) {
                throw new Error(result.message || 'The recording could not be transcribed.');
            }
            input.value = [recordingInitialText, result.text.trim()].filter(Boolean).join(' ');
            input.dispatchEvent(new Event('input', {bubbles: true}));
            status.textContent = 'Transcript ready. Review or edit the words, then choose Get general information.';
        } catch (error) {
            status.textContent = error.message || 'Transcription failed. Your recording is still available for playback or download.';
        } finally {
            mic.disabled = false;
        }
    }

    function speak(message) {
        if (!('speechSynthesis' in window)) return;
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = language.value;
        window.speechSynthesis.speak(utterance);
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (processing) return;
        currentMessage = input.value.trim();
        if (!currentMessage) return;
        processing = true;
        send.disabled = true;
        status.textContent = 'Preparing general information…';
        const requestedLocale = language.value;
        const data = new FormData();
        data.set('action', 'send');
        data.set('message', currentMessage);
        data.set('language', requestedLocale);
        data.set('csrf_token', csrf);
        try {
            const response = await fetch(<?= json_encode(hsUrl('pages/chat.php')) ?>, {
                method: 'POST', credentials: 'same-origin', body: data
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'The response could not be prepared.');
            let reply = result.messages[1].message_text;
            if (language.value !== requestedLocale) {
                const translation = new FormData();
                translation.set('action', 'translate');
                translation.set('message', currentMessage);
                translation.set('language', language.value);
                translation.set('assistant_id', String(result.messages[1].message_id));
                translation.set('csrf_token', csrf);
                const translatedResponse = await fetch(<?= json_encode(hsUrl('pages/chat.php')) ?>, {
                    method: 'POST', credentials: 'same-origin', body: translation
                });
                const translated = await translatedResponse.json();
                if (!translatedResponse.ok || !translated.success) throw new Error(translated.message || 'The reply language could not be changed.');
                reply = translated.message_text;
            }
            latestReply = reply;
            transcript.textContent = `You: ${currentMessage}\n\nHealthSphere demo: ${reply}`;
            status.textContent = 'General information only—not a diagnosis. Discuss symptoms with a qualified clinician.';
            if (document.getElementById('consultationSpeak').checked) speak(reply);
        } catch (error) {
            status.textContent = error.message || 'The chat is unavailable. Try again.';
        } finally {
            processing = false;
            currentMessage = '';
            send.disabled = false;
        }
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
