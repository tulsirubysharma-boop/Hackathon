<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/user-page.php';

if (empty($_SESSION['chat_csrf_token'])) {
    $_SESSION['chat_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['chat_csrf_token'];

/**
 * Starter response logic.
 * This is not a connected AI model and does not diagnose conditions
 * or prescribe medication.
 */
function getHealthSphereReply(string $message, string $locale = 'en-IN'): string
{
    $text = strtolower(trim($message));

    if ($text === '') {
        return 'Please type or speak a message so I can respond.';
    }

    $urgentTerms = [
        'chest pain',
        'सीने में दर्द',
        'cannot breathe',
        "can't breathe",
        'difficulty breathing',
        'सांस नहीं',
        'severe bleeding',
        'unconscious',
        'overdose',
        'suicide',
        'want to kill myself'
    ];

    $replyType = 'general';
    foreach ($urgentTerms as $term) {
        if (strpos($text, $term) !== false) {
            $replyType = 'urgent';
            break;
        }
    }

    if ($replyType === 'general' && preg_match(
        '/\b(nearby|nearest|near me|clinic|clinics|hospital|hospitals|pharmacy|pharmacies|medical store|medical stores)\b/u',
        $text
    )) {
        $replyType = 'nearby';
    }

    if ($replyType === 'general' && (
        strpos($text, 'medicine') !== false ||
        strpos($text, 'medication') !== false ||
        strpos($text, 'tablet') !== false ||
        strpos($text, 'antibiotic') !== false ||
        strpos($text, 'dosage') !== false ||
        strpos($text, 'dose') !== false ||
        strpos($text, 'दवा') !== false
    )) {
        $replyType = 'medicine';
    }

    if ($replyType === 'general' && (
        strpos($text, 'appointment') !== false ||
        strpos($text, 'doctor') !== false ||
        strpos($text, 'hospital') !== false
    )) {
        $replyType = 'appointment';
    }

    if ($replyType === 'general' && (
        strpos($text, 'stress') !== false ||
        strpos($text, 'anxious') !== false ||
        strpos($text, 'anxiety') !== false ||
        strpos($text, 'sad') !== false ||
        strpos($text, 'sleep') !== false
    )) {
        $replyType = 'wellbeing';
    }

    if ($replyType === 'general' && (
        strpos($text, 'water') !== false ||
        strpos($text, 'hydration') !== false ||
        strpos($text, 'exercise') !== false ||
        strpos($text, 'diet') !== false ||
        strpos($text, 'healthy') !== false
    )) {
        $replyType = 'routine';
    }

    if ($replyType === 'general' && (
        strpos($text, 'hello') !== false ||
        strpos($text, 'hi ') === 0 ||
        $text === 'hi' ||
        strpos($text, 'hey') === 0
    )) {
        $replyType = 'greeting';
    }

    if ($replyType === 'general' && (
        strpos($text, 'headache') !== false ||
        strpos($text, 'सिर दर्द') !== false
    )) {
        $replyType = 'headache';
    } elseif ($replyType === 'general' && (
        strpos($text, 'cough') !== false ||
        strpos($text, 'खांसी') !== false
    )) {
        $replyType = 'cough';
    } elseif ($replyType === 'general' && (
        strpos($text, 'muscle pain') !== false ||
        strpos($text, 'muscle ache') !== false ||
        strpos($text, 'मांसपेशियों में दर्द') !== false
    )) {
        $replyType = 'muscle';
    } elseif ($replyType === 'general' && (
        strpos($text, 'hair loss') !== false ||
        strpos($text, 'hair fall') !== false ||
        strpos($text, 'बाल झड़') !== false
    )) {
        $replyType = 'hair';
    }

    $replies = [
        'en' => [
            'urgent' => 'Your safety comes first. If you may be experiencing a medical emergency, call local emergency services now (112 in India). Ask someone nearby to help. Do not rely on this chat for emergency care.',
            'nearby' => 'To find nearby clinics, hospitals, doctors, or pharmacies, open Nearby Healthcare on your dashboard and choose “Use my current location.” Allow location access to see the Google Maps view and nearby search links.',
            'medicine' => 'I cannot recommend a medicine or dose. Ask a qualified doctor or pharmacist before starting, stopping, or changing medicine. Never take someone else’s prescription.',
            'appointment' => 'Write down what you noticed, when it began, medicines you take, allergies, and your questions. A qualified clinician can assess you and recommend next steps.',
            'wellbeing' => 'Thank you for sharing. Consider speaking with someone you trust and taking a gentle, comfortable pause. If this persists or affects daily life, contact a qualified health professional.',
            'routine' => 'Balanced meals, suitable movement, rest, and fluids can support general wellbeing. Individual needs vary; follow any advice from your clinician.',
            'greeting' => 'Hello! I can share general wellbeing information, help you find nearby care, or help prepare for an appointment. I cannot diagnose or prescribe.',
            'general' => 'I can share general information about everyday wellbeing, symptoms, sleep, stress, hydration, and preparing for a healthcare visit. Choose a topic shortcut above for a focused response. This is not a diagnosis.',
            'headache' => 'Headaches can have many causes. Common contributors include poor sleep, stress, dehydration, or eye strain, but this is not a diagnosis. Seek urgent care for a sudden severe headache, weakness, confusion, vision loss, or head injury.',
            'cough' => 'A cough may be associated with a common infection, allergies, or irritation, among other causes. This is not a diagnosis. Seek urgent care for difficulty breathing, chest pain, blue lips, or severe worsening.',
            'muscle' => 'Muscle aches can follow activity or strain, but infections and other conditions can also cause them. This is not a diagnosis. Seek care for severe pain, major swelling, weakness, or symptoms that persist.',
            'hair' => 'Hair loss can have many contributors, including stress, hormonal changes, nutrition, or medicines. A clinician can assess the pattern and history; this demo cannot identify a cause or recommend treatment.'
        ],
        'hi' => [
            'urgent' => 'आपकी सुरक्षा सबसे ज़रूरी है। आपातकाल में तुरंत स्थानीय आपातकालीन सेवा को कॉल करें (भारत में 112)। पास के किसी व्यक्ति से मदद लें। इस चैट पर आपातकालीन देखभाल के लिए निर्भर न रहें।',
            'nearby' => 'पास के क्लिनिक, अस्पताल, डॉक्टर या फ़ार्मेसी खोजने के लिए डैशबोर्ड में Nearby Healthcare खोलें और “Use my current location” चुनें। Google Maps के लिए लोकेशन अनुमति दें।',
            'medicine' => 'मैं दवा या खुराक की सलाह नहीं दे सकता। दवा शुरू करने, रोकने या बदलने से पहले डॉक्टर या फार्मासिस्ट से पूछें। किसी और की दवा न लें।',
            'appointment' => 'लक्षण कब शुरू हुए, आप कौन सी दवाएँ लेते हैं, एलर्जी और अपने सवाल लिखें। योग्य डॉक्टर आपकी जाँच करके आगे की सलाह दे सकते हैं।',
            'wellbeing' => 'अपनी बात साझा करने के लिए धन्यवाद। किसी भरोसेमंद व्यक्ति से बात करें। परेशानी बनी रहे तो स्वास्थ्य विशेषज्ञ से संपर्क करें।',
            'routine' => 'संतुलित भोजन, अपनी क्षमता के अनुसार गतिविधि, आराम और पर्याप्त पानी सामान्य स्वास्थ्य में मदद कर सकते हैं। अपनी चिकित्सकीय सलाह का पालन करें।',
            'greeting' => 'नमस्ते! मैं सामान्य स्वास्थ्य जानकारी और डॉक्टर से पूछने के सवाल तैयार करने में मदद कर सकता हूँ। मैं निदान या दवा नहीं बता सकता।',
            'general' => 'मैं सामान्य स्वास्थ्य जानकारी, रोज़मर्रा की सेहत या डॉक्टर से पूछने के सवाल तैयार करने में मदद कर सकता हूँ। अपना खास सवाल बताइए।',
            'headache' => 'सिरदर्द के कई कारण हो सकते हैं; नींद की कमी, तनाव, पानी की कमी या आँखों पर ज़ोर आम कारण हो सकते हैं। यह निदान नहीं है। अचानक बहुत तेज़ दर्द, कमजोरी, भ्रम, दृष्टि में बदलाव या सिर की चोट पर तुरंत चिकित्सा लें।',
            'cough' => 'खाँसी संक्रमण, एलर्जी या जलन सहित कई कारणों से हो सकती है। यह निदान नहीं है। साँस लेने में कठिनाई, सीने में दर्द या हालत तेज़ी से बिगड़ने पर तुरंत चिकित्सा लें।',
            'muscle' => 'मांसपेशियों में दर्द गतिविधि या खिंचाव के बाद हो सकता है, लेकिन अन्य कारण भी संभव हैं। यह निदान नहीं है। तेज़ दर्द, सूजन, कमजोरी या लगातार लक्षण होने पर डॉक्टर से बात करें।',
            'hair' => 'बाल झड़ने के कई कारण हो सकते हैं, जैसे तनाव, हार्मोन में बदलाव, पोषण या दवाएँ। कारण जानने के लिए डॉक्टर से सलाह लें; यह डेमो निदान या उपचार नहीं देता।'
        ],
        'bn' => [
            'urgent' => 'আপনার নিরাপত্তাই আগে। জরুরি অবস্থায় এখনই স্থানীয় জরুরি পরিষেবায় ফোন করুন (ভারতে 112)। কাছের কারও সাহায্য নিন। জরুরি চিকিৎসার জন্য এই চ্যাটের উপর নির্ভর করবেন না।',
            'nearby' => 'কাছাকাছি ক্লিনিক, হাসপাতাল, চিকিৎসক বা ফার্মেসি খুঁজতে ড্যাশবোর্ডের Nearby Healthcare খুলে “Use my current location” নির্বাচন করুন এবং Google Maps-এর জন্য অবস্থানের অনুমতি দিন।',
            'medicine' => 'আমি ওষুধ বা ডোজ বলতে পারি না। ওষুধ শুরু, বন্ধ বা পরিবর্তনের আগে চিকিৎসক বা ফার্মাসিস্টের পরামর্শ নিন। অন্যের প্রেসক্রিপশনের ওষুধ নেবেন না।',
            'appointment' => 'লক্ষণ কখন শুরু হয়েছে, ওষুধ, অ্যালার্জি এবং প্রশ্নগুলি লিখে রাখুন। যোগ্য চিকিৎসক আপনার অবস্থা মূল্যায়ন করে পরামর্শ দেবেন।',
            'wellbeing' => 'আপনার কথা জানানোর জন্য ধন্যবাদ। বিশ্বাসের কারও সঙ্গে কথা বলুন। সমস্যা চলতে থাকলে স্বাস্থ্য বিশেষজ্ঞের সঙ্গে যোগাযোগ করুন।',
            'routine' => 'সুষম খাবার, উপযুক্ত চলাফেরা, বিশ্রাম এবং পর্যাপ্ত তরল সাধারণ সুস্থতায় সহায়তা করতে পারে। চিকিৎসকের পরামর্শ মেনে চলুন।',
            'greeting' => 'নমস্কার! আমি সাধারণ সুস্থতার তথ্য ও চিকিৎসকের জন্য প্রশ্ন সাজাতে সাহায্য করতে পারি। আমি রোগ নির্ণয় বা ওষুধ দিতে পারি না।',
            'general' => 'এই লক্ষণের অনেক সম্ভাব্য কারণ থাকতে পারে। এই ডেমো পরীক্ষা বা রোগ নির্ণয় করে না। কখন শুরু হয়েছে লিখুন এবং লক্ষণ চলতে থাকলে চিকিৎসকের সঙ্গে কথা বলুন। এই চ্যাটের ভিত্তিতে ওষুধ নেবেন না।'
        ],
        'ta' => [
            'urgent' => 'உங்கள் பாதுகாப்பே முதன்மை. அவசரநிலையில் உடனே உள்ளூர் அவசர சேவையை அழைக்கவும் (இந்தியாவில் 112). அருகிலுள்ள ஒருவரிடம் உதவி கேளுங்கள். அவசர சிகிச்சைக்கு இந்த உரையாடலை நம்ப வேண்டாம்.',
            'medicine' => 'மருந்தையோ அளவையோ நான் பரிந்துரைக்க முடியாது. மருந்தைத் தொடங்க, நிறுத்த அல்லது மாற்ற மருத்துவர் அல்லது மருந்தாளரிடம் கேளுங்கள்.',
            'appointment' => 'அறிகுறிகள் எப்போது தொடங்கின, மருந்துகள், ஒவ்வாமைகள் மற்றும் உங்கள் கேள்விகளை எழுதுங்கள். தகுதியான மருத்துவர் மதிப்பீடு செய்து வழிகாட்டுவார்.',
            'wellbeing' => 'பகிர்ந்ததற்கு நன்றி. நம்பிக்கையான ஒருவரிடம் பேசுங்கள். இது தொடர்ந்தால் சுகாதார நிபுணரை அணுகுங்கள்.',
            'routine' => 'சமச்சீர் உணவு, ஏற்ற உடற்பயிற்சி, ஓய்வு மற்றும் தேவையான திரவங்கள் நலத்திற்கு உதவும். மருத்துவரின் ஆலோசனையைப் பின்பற்றுங்கள்.',
            'greeting' => 'வணக்கம்! பொதுவான நலத் தகவல்களையும் மருத்துவரிடம் கேட்க வேண்டிய கேள்விகளையும் தயார் செய்ய உதவுவேன். நோயறிதல் அல்லது மருந்து வழங்க முடியாது.',
            'general' => 'இந்த அறிகுறிகளுக்குப் பல காரணங்கள் இருக்கலாம். இந்த டெமோ பரிசோதிக்கவோ நோயறிதல் செய்யவோ முடியாது. அறிகுறிகள் தொடர்ந்தால் மருத்துவரிடம் பேசுங்கள்; இந்த உரையாடலை வைத்து மருந்து எடுக்க வேண்டாம்.'
        ],
        'te' => [
            'urgent' => 'మీ భద్రత ముఖ్యం. అత్యవసర పరిస్థితిలో వెంటనే స్థానిక అత్యవసర సేవలకు కాల్ చేయండి (భారతదేశంలో 112). దగ్గరలో ఉన్నవారి సహాయం తీసుకోండి. అత్యవసర చికిత్స కోసం ఈ చాట్‌పై ఆధారపడవద్దు.',
            'medicine' => 'నేను మందు లేదా మోతాదు సూచించలేను. మందు ప్రారంభించే, ఆపే లేదా మార్చే ముందు వైద్యుడిని లేదా ఫార్మసిస్ట్‌ను సంప్రదించండి.',
            'appointment' => 'లక్షణాలు ఎప్పుడు మొదలయ్యాయి, మందులు, అలర్జీలు, మీ ప్రశ్నలు రాసుకోండి. అర్హత కలిగిన వైద్యుడు పరిశీలించి సలహా ఇస్తారు.',
            'wellbeing' => 'మీరు పంచుకున్నందుకు ధన్యవాదాలు. నమ్మకమైన వ్యక్తితో మాట్లాడండి. సమస్య కొనసాగితే ఆరోగ్య నిపుణుడిని సంప్రదించండి.',
            'routine' => 'సమతుల్య ఆహారం, తగిన కదలిక, విశ్రాంతి, అవసరమైన ద్రవాలు సాధారణ ఆరోగ్యానికి సహాయపడతాయి. వైద్యుడి సలహాను అనుసరించండి.',
            'greeting' => 'నమస్కారం! సాధారణ ఆరోగ్య సమాచారం, వైద్యుడిని అడగాల్సిన ప్రశ్నలు సిద్ధం చేయడంలో సహాయం చేస్తాను. నిర్ధారణ లేదా మందులు సూచించలేను.',
            'general' => 'ఈ లక్షణాలకు అనేక కారణాలు ఉండవచ్చు. ఈ డెమో పరీక్షించదు లేదా నిర్ధారించదు. లక్షణాలు కొనసాగితే వైద్యుడిని సంప్రదించండి; ఈ చాట్ ఆధారంగా మందులు తీసుకోవద్దు.'
        ],
        'mr' => [
            'urgent' => 'तुमची सुरक्षितता सर्वात महत्त्वाची आहे. आपत्कालीन परिस्थितीत त्वरित स्थानिक सेवांना कॉल करा (भारतात 112). जवळच्या व्यक्तीची मदत घ्या. आपत्कालीन उपचारांसाठी या चॅटवर अवलंबून राहू नका.',
            'medicine' => 'मी औषध किंवा मात्रा सुचवू शकत नाही. औषध सुरू, बंद किंवा बदलण्यापूर्वी डॉक्टर किंवा फार्मासिस्टचा सल्ला घ्या.',
            'appointment' => 'लक्षणे कधी सुरू झाली, औषधे, अॅलर्जी आणि प्रश्न लिहून ठेवा. पात्र डॉक्टर तपासून पुढील सल्ला देतील.',
            'wellbeing' => 'तुम्ही सांगितल्याबद्दल धन्यवाद. विश्वासू व्यक्तीशी बोला. त्रास सुरू राहिल्यास आरोग्यतज्ज्ञांशी संपर्क साधा.',
            'routine' => 'संतुलित आहार, योग्य हालचाल, विश्रांती आणि आवश्यक द्रव सामान्य आरोग्यास मदत करतात. डॉक्टरांचा सल्ला पाळा.',
            'greeting' => 'नमस्कार! सामान्य आरोग्य माहिती आणि डॉक्टरांना विचारायचे प्रश्न तयार करण्यात मी मदत करू शकतो. निदान किंवा औषध सांगू शकत नाही.',
            'general' => 'या लक्षणांची अनेक संभाव्य कारणे असू शकतात. हा डेमो तपासणी किंवा निदान करत नाही. लक्षणे कायम राहिल्यास डॉक्टरांशी बोला; या चॅटवरून औषध घेऊ नका.'
        ],
        'gu' => [
            'urgent' => 'તમારી સુરક્ષા સૌથી મહત્વપૂર્ણ છે. કટોકટીમાં તરત સ્થાનિક કટોકટી સેવાઓને ફોન કરો (ભારતમાં 112). નજીકની વ્યક્તિની મદદ લો. કટોકટીની સારવાર માટે આ ચેટ પર આધાર રાખશો નહીં.',
            'medicine' => 'હું દવા અથવા માત્રાની ભલામણ કરી શકતો નથી. દવા શરૂ, બંધ કે બદલતાં પહેલાં ડૉક્ટર અથવા ફાર્માસિસ્ટને પૂછો.',
            'appointment' => 'લક્ષણો ક્યારે શરૂ થયા, દવાઓ, એલર્જી અને પ્રશ્નો લખી રાખો. લાયક ડૉક્ટર તપાસ કરીને સલાહ આપી શકે છે.',
            'wellbeing' => 'તમારી વાત શેર કરવા બદલ આભાર. વિશ્વાસુ વ્યક્તિ સાથે વાત કરો. તકલીફ ચાલુ રહે તો આરોગ્ય નિષ્ણાતનો સંપર્ક કરો.',
            'routine' => 'સંતુલિત ભોજન, યોગ્ય કસરત, આરામ અને પૂરતા પ્રવાહી સામાન્ય સુખાકારીમાં મદદ કરે છે. ડૉક્ટરની સલાહ અનુસરો.',
            'greeting' => 'નમસ્તે! હું સામાન્ય આરોગ્ય માહિતી અને ડૉક્ટરને પૂછવાના પ્રશ્નો તૈયાર કરવામાં મદદ કરી શકું છું. નિદાન કે દવા આપી શકતો નથી.',
            'general' => 'આ લક્ષણોના અનેક સંભવિત કારણો હોઈ શકે છે. આ ડેમો તપાસ કે નિદાન કરતું નથી. લક્ષણો ચાલુ રહે તો ડૉક્ટર સાથે વાત કરો; આ ચેટ પરથી દવા ન લો.'
        ],
        'kn' => [
            'urgent' => 'ನಿಮ್ಮ ಸುರಕ್ಷತೆ ಮುಖ್ಯ. ತುರ್ತು ಪರಿಸ್ಥಿತಿಯಲ್ಲಿ ಸ್ಥಳೀಯ ತುರ್ತು ಸೇವೆಗಳಿಗೆ ಕರೆ ಮಾಡಿ (ಭಾರತದಲ್ಲಿ 112). ಹತ್ತಿರದವರ ಸಹಾಯ ಪಡೆಯಿರಿ. ತುರ್ತು ಚಿಕಿತ್ಸೆಗೆ ಈ ಚಾಟ್ ಅವಲಂಬಿಸಬೇಡಿ.',
            'medicine' => 'ನಾನು ಔಷಧಿ ಅಥವಾ ಪ್ರಮಾಣವನ್ನು ಸೂಚಿಸಲಾರೆ. ಔಷಧಿ ಆರಂಭಿಸುವ, ನಿಲ್ಲಿಸುವ ಅಥವಾ ಬದಲಿಸುವ ಮೊದಲು ವೈದ್ಯರು ಅಥವಾ ಔಷಧಿಕಾರರನ್ನು ಕೇಳಿ.',
            'appointment' => 'ಲಕ್ಷಣಗಳು ಯಾವಾಗ ಆರಂಭವಾದವು, ಔಷಧಿಗಳು, ಅಲರ್ಜಿ ಮತ್ತು ಪ್ರಶ್ನೆಗಳನ್ನು ಬರೆಯಿರಿ. ಅರ್ಹ ವೈದ್ಯರು ಪರಿಶೀಲಿಸಿ ಸಲಹೆ ನೀಡುತ್ತಾರೆ.',
            'wellbeing' => 'ಹಂಚಿಕೊಂಡಿದ್ದಕ್ಕೆ ಧನ್ಯವಾದಗಳು. ನಂಬಿಕೆಯ ವ್ಯಕ್ತಿಯೊಂದಿಗೆ ಮಾತನಾಡಿ. ಸಮಸ್ಯೆ ಮುಂದುವರಿದರೆ ಆರೋಗ್ಯ ತಜ್ಞರನ್ನು ಸಂಪರ್ಕಿಸಿ.',
            'routine' => 'ಸಮತೋಲಿತ ಆಹಾರ, ಸೂಕ್ತ ಚಟುವಟಿಕೆ, ವಿಶ್ರಾಂತಿ ಮತ್ತು ಅಗತ್ಯ ದ್ರವಗಳು ಸಾಮಾನ್ಯ ಆರೋಗ್ಯಕ್ಕೆ ಸಹಾಯ ಮಾಡುತ್ತವೆ. ವೈದ್ಯರ ಸಲಹೆ ಅನುಸರಿಸಿ.',
            'greeting' => 'ನಮಸ್ಕಾರ! ಸಾಮಾನ್ಯ ಆರೋಗ್ಯ ಮಾಹಿತಿ ಮತ್ತು ವೈದ್ಯರನ್ನು ಕೇಳಬೇಕಾದ ಪ್ರಶ್ನೆಗಳನ್ನು ಸಿದ್ಧಪಡಿಸಲು ಸಹಾಯ ಮಾಡುತ್ತೇನೆ. ರೋಗನಿರ್ಣಯ ಅಥವಾ ಔಷಧಿ ನೀಡಲಾರೆ.',
            'general' => 'ಈ ಲಕ್ಷಣಗಳಿಗೆ ಹಲವು ಕಾರಣಗಳಿರಬಹುದು. ಈ ಡೆಮೊ ಪರೀಕ್ಷೆ ಅಥವಾ ರೋಗನಿರ್ಣಯ ಮಾಡುವುದಿಲ್ಲ. ಲಕ್ಷಣಗಳು ಮುಂದುವರಿದರೆ ವೈದ್ಯರನ್ನು ಸಂಪರ್ಕಿಸಿ; ಈ ಚಾಟ್ ಆಧರಿಸಿ ಔಷಧಿ ತೆಗೆದುಕೊಳ್ಳಬೇಡಿ.'
        ],
        'ml' => [
            'urgent' => 'നിങ്ങളുടെ സുരക്ഷയാണ് പ്രധാനം. അടിയന്തരാവസ്ഥയിൽ ഉടൻ പ്രാദേശിക അടിയന്തര സേവനങ്ങളെ വിളിക്കുക (ഇന്ത്യയിൽ 112). സമീപത്തുള്ള ഒരാളുടെ സഹായം തേടുക. അടിയന്തര ചികിത്സയ്ക്ക് ഈ ചാറ്റിനെ ആശ്രയിക്കരുത്.',
            'medicine' => 'മരുന്നോ അളവോ ഞാൻ നിർദേശിക്കാനാവില്ല. മരുന്ന് തുടങ്ങാനോ നിർത്താനോ മാറ്റാനോ മുമ്പ് ഡോക്ടറെയോ ഫാർമസിസ്റ്റിനെയോ ചോദിക്കുക.',
            'appointment' => 'ലക്ഷണങ്ങൾ തുടങ്ങിയ സമയം, മരുന്നുകൾ, അലർജികൾ, ചോദ്യങ്ങൾ എന്നിവ കുറിക്കുക. യോഗ്യനായ ഡോക്ടർ പരിശോധിച്ച് നിർദേശിക്കും.',
            'wellbeing' => 'പങ്കുവെച്ചതിന് നന്ദി. വിശ്വസിക്കുന്ന ഒരാളോട് സംസാരിക്കുക. ബുദ്ധിമുട്ട് തുടരുകയാണെങ്കിൽ ആരോഗ്യ വിദഗ്ധനെ സമീപിക്കുക.',
            'routine' => 'സമതുലിതമായ ഭക്ഷണം, അനുയോജ്യമായ വ്യായാമം, വിശ്രമം, ആവശ്യമായ ദ്രവങ്ങൾ എന്നിവ പൊതുവായ ആരോഗ്യത്തിന് സഹായിക്കും. ഡോക്ടറുടെ നിർദേശം പാലിക്കുക.',
            'greeting' => 'നമസ്കാരം! പൊതുവായ ആരോഗ്യവിവരങ്ങളും ഡോക്ടറോട് ചോദിക്കേണ്ട ചോദ്യങ്ങളും തയ്യാറാക്കാൻ സഹായിക്കാം. രോഗനിർണയമോ മരുന്നോ നൽകാനാവില്ല.',
            'general' => 'ഈ ലക്ഷണങ്ങൾക്ക് പല കാരണങ്ങളുണ്ടാകാം. ഈ ഡെമോ പരിശോധനയോ രോഗനിർണയമോ നടത്തുന്നില്ല. ലക്ഷണങ്ങൾ തുടരുകയാണെങ്കിൽ ഡോക്ടറെ കാണുക; ഈ ചാറ്റിന്റെ അടിസ്ഥാനത്തിൽ മരുന്ന് കഴിക്കരുത്.'
        ],
        'pa' => [
            'urgent' => 'ਤੁਹਾਡੀ ਸੁਰੱਖਿਆ ਸਭ ਤੋਂ ਪਹਿਲਾਂ ਹੈ। ਐਮਰਜੈਂਸੀ ਵਿੱਚ ਤੁਰੰਤ ਸਥਾਨਕ ਸੇਵਾਵਾਂ ਨੂੰ ਕਾਲ ਕਰੋ (ਭਾਰਤ ਵਿੱਚ 112)। ਨੇੜੇ ਦੇ ਵਿਅਕਤੀ ਤੋਂ ਮਦਦ ਲਵੋ। ਐਮਰਜੈਂਸੀ ਇਲਾਜ ਲਈ ਇਸ ਚੈਟ ਉੱਤੇ ਭਰੋਸਾ ਨਾ ਕਰੋ।',
            'medicine' => 'ਮੈਂ ਦਵਾਈ ਜਾਂ ਖੁਰਾਕ ਦੀ ਸਿਫ਼ਾਰਸ਼ ਨਹੀਂ ਕਰ ਸਕਦਾ। ਦਵਾਈ ਸ਼ੁਰੂ, ਬੰਦ ਜਾਂ ਬਦਲਣ ਤੋਂ ਪਹਿਲਾਂ ਡਾਕਟਰ ਜਾਂ ਫਾਰਮਾਸਿਸਟ ਨਾਲ ਗੱਲ ਕਰੋ।',
            'appointment' => 'ਲੱਛਣ ਕਦੋਂ ਸ਼ੁਰੂ ਹੋਏ, ਦਵਾਈਆਂ, ਐਲਰਜੀਆਂ ਅਤੇ ਸਵਾਲ ਲਿਖੋ। ਯੋਗ ਡਾਕਟਰ ਜਾਂਚ ਕਰਕੇ ਸਲਾਹ ਦੇ ਸਕਦੇ ਹਨ।',
            'wellbeing' => 'ਆਪਣੀ ਗੱਲ ਸਾਂਝੀ ਕਰਨ ਲਈ ਧੰਨਵਾਦ। ਭਰੋਸੇਯੋਗ ਵਿਅਕਤੀ ਨਾਲ ਗੱਲ ਕਰੋ। ਤਕਲੀਫ਼ ਜਾਰੀ ਰਹੇ ਤਾਂ ਸਿਹਤ ਮਾਹਿਰ ਨਾਲ ਸੰਪਰਕ ਕਰੋ।',
            'routine' => 'ਸੰਤੁਲਿਤ ਖਾਣਾ, ਢੁਕਵੀਂ ਕਸਰਤ, ਆਰਾਮ ਅਤੇ ਲੋੜੀਂਦੇ ਤਰਲ ਪਦਾਰਥ ਆਮ ਸਿਹਤ ਵਿੱਚ ਮਦਦ ਕਰਦੇ ਹਨ। ਡਾਕਟਰ ਦੀ ਸਲਾਹ ਮੰਨੋ।',
            'greeting' => 'ਸਤ ਸ੍ਰੀ ਅਕਾਲ! ਮੈਂ ਆਮ ਸਿਹਤ ਜਾਣਕਾਰੀ ਅਤੇ ਡਾਕਟਰ ਲਈ ਸਵਾਲ ਤਿਆਰ ਕਰਨ ਵਿੱਚ ਮਦਦ ਕਰ ਸਕਦਾ ਹਾਂ। ਮੈਂ ਨਿਦਾਨ ਜਾਂ ਦਵਾਈ ਨਹੀਂ ਦੇ ਸਕਦਾ।',
            'general' => 'ਇਨ੍ਹਾਂ ਲੱਛਣਾਂ ਦੇ ਕਈ ਸੰਭਾਵੀ ਕਾਰਨ ਹੋ ਸਕਦੇ ਹਨ। ਇਹ ਡੈਮੋ ਜਾਂਚ ਜਾਂ ਨਿਦਾਨ ਨਹੀਂ ਕਰਦਾ। ਲੱਛਣ ਜਾਰੀ ਰਹਿਣ ਤਾਂ ਡਾਕਟਰ ਨਾਲ ਗੱਲ ਕਰੋ; ਇਸ ਚੈਟ ਦੇ ਆਧਾਰ ਤੇ ਦਵਾਈ ਨਾ ਲਵੋ।'
        ],
        'ur' => [
            'urgent' => 'آپ کی حفاظت سب سے اہم ہے۔ ہنگامی حالت میں فوراً مقامی ہنگامی خدمات کو کال کریں (بھارت میں 112)۔ قریب موجود شخص سے مدد لیں۔ ہنگامی علاج کے لیے اس چیٹ پر انحصار نہ کریں۔',
            'medicine' => 'میں دوا یا خوراک تجویز نہیں کر سکتا۔ دوا شروع، بند یا تبدیل کرنے سے پہلے ڈاکٹر یا فارماسسٹ سے پوچھیں۔',
            'appointment' => 'علامات کب شروع ہوئیں، ادویات، الرجی اور سوالات لکھ لیں۔ اہل ڈاکٹر معائنہ کرکے رہنمائی دے سکتے ہیں۔',
            'wellbeing' => 'اپنی بات بتانے کا شکریہ۔ کسی قابل اعتماد شخص سے بات کریں۔ پریشانی جاری رہے تو صحت کے ماہر سے رابطہ کریں۔',
            'routine' => 'متوازن غذا، مناسب سرگرمی، آرام اور ضرورت کے مطابق پانی عمومی صحت میں مدد دیتے ہیں۔ اپنے ڈاکٹر کے مشورے پر عمل کریں۔',
            'greeting' => 'سلام! میں عمومی صحت کی معلومات اور ڈاکٹر سے پوچھنے کے سوالات تیار کرنے میں مدد کر سکتا ہوں۔ میں تشخیص یا دوا تجویز نہیں کر سکتا۔',
            'general' => 'ان علامات کی کئی ممکنہ وجوہات ہو سکتی ہیں۔ یہ ڈیمو معائنہ یا تشخیص نہیں کرتا۔ علامات جاری رہیں تو ڈاکٹر سے بات کریں؛ اس چیٹ کی بنیاد پر دوا نہ لیں۔'
        ]
    ];

    $language = strtolower(explode('-', $locale)[0]);
    return $replies[$language][$replyType] ?? $replies['en'][$replyType] ?? $replies[$language]['general'] ?? $replies['en']['general'];
}

/* Handle chat submissions before rendering the page. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['send', 'translate'], true)) {
    header('Content-Type: application/json; charset=utf-8');

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Your session token is invalid. Refresh the page and try again.'
        ]);
        exit;
    }

    $message = trim((string) ($_POST['message'] ?? ''));

    if ($message === '') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a message first.'
        ]);
        exit;
    }

    if (mb_strlen($message) > 4000) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Please keep each message under 4,000 characters.'
        ]);
        exit;
    }

    try {
        $allowedLocales = [
            'en-IN', 'hi-IN', 'bn-IN', 'ta-IN', 'te-IN', 'mr-IN',
            'gu-IN', 'kn-IN', 'ml-IN', 'pa-IN', 'ur-IN'
        ];
        $locale = (string) ($_POST['language'] ?? 'en-IN');
        if (!in_array($locale, $allowedLocales, true)) {
            $locale = 'en-IN';
        }
        $reply = getHealthSphereReply($message, $locale);

        if (($_POST['action'] ?? '') === 'translate') {
            $assistantId = filter_var($_POST['assistant_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$assistantId) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'The chat reply could not be updated.']);
                exit;
            }
            $updateReply = $pdo->prepare(
                "UPDATE chat_messages
                 SET message_text = :message_text
                 WHERE message_id = :message_id AND user_id = :user_id AND sender = 'assistant'"
            );
            $updateReply->execute([
                'message_text' => $reply,
                'message_id' => $assistantId,
                'user_id' => $userId
            ]);
            if (!$updateReply->rowCount()) {
                $checkReply = $pdo->prepare(
                    "SELECT message_id FROM chat_messages
                     WHERE message_id = :message_id AND user_id = :user_id AND sender = 'assistant'"
                );
                $checkReply->execute(['message_id' => $assistantId, 'user_id' => $userId]);
                if (!$checkReply->fetch()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'The chat reply was not found.']);
                    exit;
                }
            }
            echo json_encode(['success' => true, 'message_text' => $reply], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->beginTransaction();

        $userInsert = $pdo->prepare(
            "INSERT INTO chat_messages (user_id, sender, message_text)
             VALUES (:user_id, 'user', :message_text)"
        );

        $userInsert->execute([
            ':user_id' => $userId,
            ':message_text' => $message
        ]);

        $userMessageId = (int) $pdo->lastInsertId();

        $assistantInsert = $pdo->prepare(
            "INSERT INTO chat_messages (user_id, sender, message_text)
             VALUES (:user_id, 'assistant', :message_text)"
        );

        $assistantInsert->execute([
            ':user_id' => $userId,
            ':message_text' => $reply
        ]);

        $assistantMessageId = (int) $pdo->lastInsertId();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'messages' => [
                [
                    'message_id' => $userMessageId,
                    'sender' => 'user',
                    'message_text' => $message,
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'message_id' => $assistantMessageId,
                    'sender' => 'assistant',
                    'message_text' => $reply,
                    'created_at' => date('Y-m-d H:i:s'),
                    'locale' => $locale
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('HealthSphere chat error: ' . $exception->getMessage());

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Your message could not be saved. Please check your database connection and try again.'
        ]);
    }

    exit;
}

$selectedChatDate = trim((string) ($_GET['date'] ?? ''));
$isArchivedChat = false;
if ($selectedChatDate !== '') {
    $parsedChatDate = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedChatDate);
    if (!$parsedChatDate || $parsedChatDate->format('Y-m-d') !== $selectedChatDate) {
        http_response_code(400);
        exit('Choose a valid chat date.');
    }
    $isArchivedChat = true;
}

/* Load only this logged-in user's messages for the selected date or recent chat. */
try {
    if ($isArchivedChat) {
        $historyQuery = $pdo->prepare(
            'SELECT message_id, sender, message_text, created_at
             FROM chat_messages
             WHERE user_id = :user_id
               AND created_at >= :day_start
               AND created_at < :day_end
             ORDER BY message_id ASC
             LIMIT 500'
        );
        $historyQuery->execute([
            'user_id' => $userId,
            'day_start' => $parsedChatDate->format('Y-m-d 00:00:00'),
            'day_end' => $parsedChatDate->modify('+1 day')->format('Y-m-d 00:00:00')
        ]);
        $chatMessages = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $historyQuery = $pdo->prepare(
            'SELECT message_id, sender, message_text, created_at
             FROM chat_messages
             WHERE user_id = :user_id
             ORDER BY message_id DESC
             LIMIT 60'
        );
        $historyQuery->execute([':user_id' => $userId]);
        $chatMessages = array_reverse($historyQuery->fetchAll(PDO::FETCH_ASSOC));
    }

} catch (Throwable $exception) {
    error_log('HealthSphere chat history error: ' . $exception->getMessage());
    $chatMessages = [];
    $historyError = true;
}

$pageTitle = $isArchivedChat
    ? 'Chat history | ' . $parsedChatDate->format('d M Y')
    : 'HealthSphere | Health Chat';

require_once __DIR__ . '/../includes/header.php';

function chatEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<style>
.health-chat-page {
    max-width: 1050px;
    margin: 0 auto;
    padding: 28px 16px 44px;
    color: #35234f;
}

.chat-heading {
    text-align: center;
    margin-bottom: 24px;
}

.chat-heading h1 {
    margin: 0 0 10px;
    color: #35234f;
    font-size: clamp(28px, 4vw, 40px);
}

.chat-heading p {
    margin: 0 auto;
    max-width: 680px;
    line-height: 1.7;
    color: #6b5b78;
}

.chat-notice {
    margin: 18px 0;
    padding: 13px 16px;
    border: 1px solid #ead6df;
    border-radius: 14px;
    background: #fcf3f6;
    color: #513b70;
    font-size: 13px;
    line-height: 1.65;
}

.chat-shell {
    overflow: hidden;
    border: 1px solid #eadfe9;
    border-radius: 22px;
    background: #fffdfb;
    box-shadow: 0 14px 45px rgba(53, 35, 79, 0.09);
}

.chat-topbar {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 18px 22px;
    color: white;
    background: linear-gradient(120deg, #35234f, #654777);
}

.chat-avatar {
    display: grid;
    width: 45px;
    height: 45px;
    flex-shrink: 0;
    place-items: center;
    border: 1px solid rgba(255,255,255,.35);
    border-radius: 50%;
    background: rgba(255,255,255,.12);
    font-size: 23px;
}

.chat-topbar h2 {
    margin: 0 0 4px;
    font-size: 17px;
}

.chat-topbar p {
    margin: 0;
    color: #f3e9f6;
    font-size: 12px;
}

.chat-status {
    margin-left: auto;
    padding: 7px 10px;
    border: 1px solid rgba(255,255,255,.25);
    border-radius: 30px;
    color: #fff;
    font-size: 11px;
    white-space: nowrap;
}

.chat-topic-prompts {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding: 11px 18px;
    border-bottom: 1px solid #eadfe9;
    background: #fff;
}

.chat-topic-prompts > span {
    flex: 0 0 auto;
    color: #786d7e;
    font-size: 12px;
    font-weight: 700;
}

.chat-topic-prompts button {
    flex: 0 0 auto;
    padding: 7px 10px;
    border: 1px solid #eadce8;
    border-radius: 20px;
    background: #fcf8f1;
    color: #513b70;
    cursor: pointer;
    font-size: 12px;
}

.chat-topic-prompts button:hover {
    border-color: #b86e83;
    background: #f7e8ec;
}

.chat-messages {
    display: flex;
    min-height: 330px;
    max-height: 510px;
    flex-direction: column;
    gap: 16px;
    overflow-y: auto;
    padding: 24px;
    background:
        radial-gradient(circle at top right, #f9edf2 0, transparent 32%),
        #fffdfb;
}

.chat-row {
    display: flex;
    width: 100%;
}

.chat-row.user {
    justify-content: flex-end;
}

.chat-row.assistant {
    justify-content: flex-start;
}

.chat-bubble {
    max-width: min(80%, 620px);
    padding: 13px 16px;
    border-radius: 17px;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    line-height: 1.65;
    font-size: 14px;
}

.chat-row.user .chat-bubble {
    color: #fff;
    background: #513b70;
    border-bottom-right-radius: 5px;
}

.chat-row.assistant .chat-bubble {
    color: #40304e;
    background: #f4eaf3;
    border: 1px solid #e8d9e8;
    border-bottom-left-radius: 5px;
}

.chat-time {
    margin-top: 7px;
    color: #877a8d;
    font-size: 10px;
}

.chat-row.user .chat-time {
    text-align: right;
}

.chat-empty {
    margin: auto;
    max-width: 430px;
    padding: 22px;
    text-align: center;
    color: #6b5b78;
    line-height: 1.7;
}

.chat-empty .empty-icon {
    margin-bottom: 12px;
    font-size: 37px;
}

.chat-empty h3 {
    margin: 0 0 8px;
    color: #513b70;
}

.chat-compose {
    padding: 18px;
    border-top: 1px solid #eadfe9;
    background: #fff;
}

.chat-input-row {
    display: flex;
    align-items: flex-end;
    gap: 9px;
}

.chat-input {
    min-width: 0;
    min-height: 50px;
    max-height: 130px;
    flex: 1;
    resize: vertical;
    padding: 13px 15px;
    border: 1px solid #d9cbdc;
    border-radius: 14px;
    outline: none;
    color: #35234f;
    background: #fffdfb;
    font: inherit;
    font-size: 14px;
}

.chat-input:focus {
    border-color: #8b659a;
    box-shadow: 0 0 0 3px rgba(139, 101, 154, .12);
}

.chat-action {
    display: inline-flex;
    min-width: 48px;
    height: 48px;
    align-items: center;
    justify-content: center;
    padding: 0 15px;
    border: 0;
    border-radius: 13px;
    cursor: pointer;
    font: inherit;
    font-weight: 600;
    transition: transform .15s ease, opacity .15s ease;
}

.chat-action:hover {
    transform: translateY(-1px);
}

.chat-action:disabled {
    cursor: not-allowed;
    opacity: .55;
    transform: none;
}

.chat-send {
    color: #fff;
    background: #513b70;
}

.chat-mic {
    color: #513b70;
    background: #f4eaf3;
    border: 1px solid #e3d3e5;
}

.chat-mic.listening {
    color: #fff;
    background: #b86e83;
}

.chat-voice-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 12px;
}

.chat-voice-row label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #6b5b78;
    font-size: 12px;
}

.chat-small-button {
    padding: 7px 10px;
    border: 1px solid #e3d3e5;
    border-radius: 9px;
    color: #513b70;
    background: #fff;
    cursor: pointer;
    font-size: 12px;
}

.chat-feedback {
    min-height: 19px;
    margin-top: 8px;
    color: #75617f;
    font-size: 12px;
}

.chat-feedback.error {
    color: #a22e45;
}

.chat-typing {
    color: #75617f;
    font-size: 12px;
    font-style: italic;
}

@media (max-width: 600px) {
    .health-chat-page {
        padding: 18px 10px 30px;
    }

    .chat-messages {
        min-height: 300px;
        max-height: 55vh;
        padding: 16px 12px;
    }

    .chat-topbar {
        padding: 15px;
    }

    .chat-topic-prompts {
        padding: 10px;
    }

    .chat-status {
        display: none;
    }

    .chat-bubble {
        max-width: 90%;
        font-size: 13px;
    }

    .chat-compose {
        padding: 12px;
    }

    .chat-action {
        min-width: 43px;
        padding: 0 12px;
    }
}
</style>

<div class="health-chat-page">
    <header class="chat-heading">
        <h1><?= $isArchivedChat ? 'Chat from ' . chatEscape($parsedChatDate->format('d M Y')) : 'Your HealthSphere Companion' ?></h1>
        <p>
            A place to explore general wellbeing information, organise health
            questions, and build healthier routines.
        </p>
        <?php if ($isArchivedChat): ?>
            <p><a class="btn btn-outline" href="<?= hsUrl('pages/medical-history.php') ?>">Back to medical history</a></p>
        <?php endif; ?>
    </header>

    <div class="chat-notice">
        <strong>Health and safety:</strong>
        This starter chat is not a medical professional and cannot diagnose,
        prescribe medicines, or replace professional care. Avoid sharing
        passwords, payment details, or other highly sensitive information.
        For emergencies, contact your local emergency services. This is a scripted, multilingual demo, not a connected AI service. Voice
        capture and playback use browser-provided Indian locales.
    </div>

    <section class="chat-shell" aria-label="HealthSphere chat">
        <div class="chat-topbar">
            <div class="chat-avatar" aria-hidden="true">✦</div>
            <div>
                <h2>HealthSphere Assistant</h2>
                <p>General wellbeing information · no diagnosis or prescriptions</p>
            </div>
            <div class="chat-status">Text + Voice</div>
        </div>

        <?php if (!$isArchivedChat): ?>
        <div class="chat-topic-prompts" aria-label="Suggested conversation topics">
            <span>Try a topic</span>
            <button type="button" data-chat-prompt="How can I build a relaxing bedtime routine?">Sleep routine</button>
            <button type="button" data-chat-prompt="What are some gentle hydration habits?">Hydration</button>
            <button type="button" data-chat-prompt="How can I prepare for a doctor visit?">Doctor visit</button>
            <button type="button" data-chat-prompt="How can I manage everyday stress?">Everyday stress</button>
            <button type="button" data-chat-prompt="Where can I find nearby clinics or pharmacies?">Nearby care</button>
        </div>
        <?php endif; ?>

        <div
            class="chat-messages"
            id="chatMessages"
            aria-live="polite"
            aria-label="Conversation messages"
        >
            <?php if (!empty($historyError)): ?>
                <div class="chat-empty">
                    <h3>Chat history unavailable</h3>
                    <p>
                        We could not load your saved messages. Please refresh
                        the page or check the database connection.
                    </p>
                </div>
            <?php elseif (empty($chatMessages)): ?>
                <div class="chat-empty" id="emptyChat">
                    <div class="empty-icon" aria-hidden="true">💜</div>
                    <h3>Welcome to HealthSphere</h3>
                    <p>
                        <?= $isArchivedChat
                            ? 'No saved messages were found for this date.'
                            : 'Start by typing a message below or using the microphone. Your conversation will be saved to your account.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($chatMessages as $message): ?>
                    <div class="chat-row <?= $message['sender'] === 'user' ? 'user' : 'assistant' ?>">
                        <div class="chat-bubble">
                            <div><?= nl2br(chatEscape((string) $message['message_text'])) ?></div>
                            <div class="chat-time">
                                <?= chatEscape(date(
                                    'd M Y, h:i A',
                                    strtotime((string) $message['created_at'])
                                )) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!$isArchivedChat): ?>
        <div class="chat-compose">
            <form id="chatForm">
                <div class="chat-input-row">
                    <textarea
                        class="chat-input"
                        id="chatInput"
                        name="message"
                        maxlength="4000"
                        rows="1"
                        placeholder="Type your wellbeing question..."
                        aria-label="Type your message"
                        required
                    ></textarea>

                    <button
                        class="chat-action chat-mic"
                        id="micButton"
                        type="button"
                        title="Speak your message"
                        aria-label="Speak your message"
                    >🎙</button>

                    <button
                        class="chat-action chat-send"
                        id="sendButton"
                        type="submit"
                        aria-label="Send message"
                    >➤</button>
                </div>

                <div class="chat-voice-row">
                    <label>
                        <input type="checkbox" id="speakReplies" checked>
                        Read replies aloud
                    </label>
                    <label for="voiceLanguage">Voice language
                        <select id="voiceLanguage" aria-label="Voice input and playback language">
                            <option value="en-IN">English (India)</option>
                            <option value="hi-IN">हिन्दी</option>
                            <option value="bn-IN">বাংলা</option>
                            <option value="ta-IN">தமிழ்</option>
                            <option value="te-IN">తెలుగు</option>
                            <option value="mr-IN">मराठी</option>
                            <option value="gu-IN">ગુજરાતી</option>
                            <option value="kn-IN">ಕನ್ನಡ</option>
                            <option value="ml-IN">മലയാളം</option>
                            <option value="pa-IN">ਪੰਜਾਬੀ</option>
                            <option value="ur-IN">اردو</option>
                        </select>
                    </label>

                    <button
                        class="chat-small-button"
                        type="button"
                        id="stopVoiceButton"
                    >Stop voice</button>
                </div>

                <div
                    class="chat-feedback"
                    id="chatFeedback"
                    role="status"
                    aria-live="polite"
                ></div>

                <input type="hidden" name="csrf_token"
                    value="<?= chatEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="send">
            </form>
        </div>
        <?php else: ?>
        <p class="chat-archive-note">This saved conversation is read-only. Start a new chat from the dashboard.</p>
        <?php endif; ?>
    </section>
</div>

<script>
(() => {
    'use strict';

    const form = document.getElementById('chatForm');
    if (!form) return;
    const input = document.getElementById('chatInput');
    const messages = document.getElementById('chatMessages');
    const sendButton = document.getElementById('sendButton');
    const micButton = document.getElementById('micButton');
    const feedback = document.getElementById('chatFeedback');
    const speakReplies = document.getElementById('speakReplies');
    const stopVoiceButton = document.getElementById('stopVoiceButton');
    const voiceLanguage = document.getElementById('voiceLanguage');
    const csrfInput = form.querySelector('input[name="csrf_token"]');

    let isSending = false;
    let recognition = null;
    let isListening = false;
    let activeUtterance = null;
    let pendingMessage = '';
    let lastSpokenText = '';

    function setFeedback(message, isError = false) {
        feedback.textContent = message;
        feedback.classList.toggle('error', isError);
    }

    function speakReply(text) {
        if (!('speechSynthesis' in window)) return;
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = voiceLanguage.value;
        lastSpokenText = text;
        window.speechSynthesis.speak(utterance);
    }

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    function formatTimestamp(value) {
        if (!value) {
            return new Date().toLocaleString();
        }

        // Database timestamps are interpreted as local server time here.
        const parts = value.match(
            /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/
        );

        if (!parts) {
            return new Date().toLocaleString();
        }

        const date = new Date(
            Number(parts[1]),
            Number(parts[2]) - 1,
            Number(parts[3]),
            Number(parts[4]),
            Number(parts[5]),
            Number(parts[6])
        );

        return date.toLocaleString([], {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function addMessage(sender, messageText, createdAt) {
        const emptyState = document.getElementById('emptyChat');

        if (emptyState) {
            emptyState.remove();
        }

        const row = document.createElement('div');
        row.className = 'chat-row ' +
            (sender === 'user' ? 'user' : 'assistant');

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';

        const content = document.createElement('div');
        content.textContent = messageText;

        const time = document.createElement('div');
        time.className = 'chat-time';
        time.textContent = formatTimestamp(createdAt);

        bubble.appendChild(content);
        bubble.appendChild(time);
        row.appendChild(bubble);
        messages.appendChild(row);

        scrollToBottom();
    }

    function addTypingIndicator() {
        const row = document.createElement('div');
        row.id = 'chatTyping';
        row.className = 'chat-row assistant';

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble chat-typing';
        bubble.textContent = 'Preparing a response...';

        row.appendChild(bubble);
        messages.appendChild(row);
        scrollToBottom();
    }

    function removeTypingIndicator() {
        const indicator = document.getElementById('chatTyping');

        if (indicator) {
            indicator.remove();
        }
    }

    async function sendMessage(message) {
        if (isSending || !message.trim()) {
            return;
        }

        isSending = true;
        pendingMessage = message.trim();
        const languageAtSubmit = voiceLanguage.value;
        sendButton.disabled = true;
        input.disabled = true;
        micButton.disabled = true;
        setFeedback('Sending your message...');

        addTypingIndicator();

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('message', message.trim());
        formData.append('language', languageAtSubmit);
        formData.append('csrf_token', csrfInput.value);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            removeTypingIndicator();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Unable to send your message.'
                );
            }

            if (voiceLanguage.value !== languageAtSubmit) {
                const translateForm = new FormData();
                translateForm.append('action', 'translate');
                translateForm.append('message', pendingMessage);
                translateForm.append('language', voiceLanguage.value);
                translateForm.append('assistant_id', String(result.messages[1].message_id));
                translateForm.append('csrf_token', csrfInput.value);
                const translationResponse = await fetch(window.location.href, {
                    method: 'POST',
                    body: translateForm,
                    credentials: 'same-origin'
                });
                const translated = await translationResponse.json();
                if (!translationResponse.ok || !translated.success) {
                    throw new Error(translated.message || 'Could not change the reply language.');
                }
                result.messages[1].message_text = translated.message_text;
            }

            result.messages.forEach((item) => {
                addMessage(
                    item.sender,
                    item.message_text,
                    item.created_at
                );

                if (
                    item.sender === 'assistant' &&
                    speakReplies.checked &&
                    'speechSynthesis' in window
                ) {
                    window.speechSynthesis.cancel();

                    speakReply(item.message_text);
                }
            });

            setFeedback('Conversation saved to your account.');
        } catch (error) {
            removeTypingIndicator();
            setFeedback(
                error.message || 'Something went wrong. Please try again.',
                true
            );
        } finally {
            isSending = false;
            pendingMessage = '';
            sendButton.disabled = false;
            input.disabled = false;
            micButton.disabled = false;
            input.focus();
        }
    }

    document.querySelectorAll('[data-chat-prompt]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (isSending) return;
            input.value = '';
            await sendMessage(button.dataset.chatPrompt);
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = input.value.trim();

        if (!message) {
            setFeedback('Please type a message first.', true);
            return;
        }

        input.value = '';
        await sendMessage(message);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    // Browser voice input depends on browser and microphone permissions.
    const SpeechRecognition =
        window.SpeechRecognition || window.webkitSpeechRecognition;

    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = voiceLanguage.value;
        recognition.interimResults = true;
        recognition.continuous = false;
        voiceLanguage.addEventListener('change', () => {
            recognition.lang = voiceLanguage.value;
            if (isListening) {
                recognition.stop();
                window.setTimeout(() => {
                    if (!isListening) {
                        try {
                            recognition.start();
                        } catch (error) {
                            setFeedback('Language changed. Tap the microphone to continue.', true);
                        }
                    }
                }, 250);
            }
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                if (speakReplies.checked && !isSending && lastSpokenText) {
                    speakReply(lastSpokenText);
                }
            }
        });

        recognition.onstart = () => {
            isListening = true;
            micButton.classList.add('listening');
            micButton.textContent = '●';
            setFeedback('Listening... speak clearly.');
        };

        recognition.onresult = (event) => {
            let transcript = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }

            input.value = transcript;
        };

        recognition.onerror = (event) => {
            const reason = event.error === 'not-allowed'
                ? 'Microphone permission was denied. Allow microphone access in your browser.'
                : 'Voice input was unavailable. You can type your message instead.';

            setFeedback(reason, true);
        };

        recognition.onend = () => {
            isListening = false;
            micButton.classList.remove('listening');
            micButton.textContent = '🎙';

            if (input.value.trim()) {
                input.focus();
                setFeedback('Voice captured. Review your message, then send it.');
            }
        };

        micButton.addEventListener('click', () => {
            if (isListening) {
                recognition.stop();
                return;
            }

            try {
                recognition.start();
            } catch (error) {
                setFeedback(
                    'Voice input could not start. Please try again or type your message.',
                    true
                );
            }
        });
    } else {
        micButton.disabled = true;
        micButton.title = 'Voice input is not supported by this browser';
        setFeedback(
            'Voice input is not supported in this browser. Text chat is available.'
        );
    }

    stopVoiceButton.addEventListener('click', () => {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }

        if (recognition && isListening) {
            recognition.stop();
        }

        setFeedback('Voice playback stopped.');
    });

    scrollToBottom();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
