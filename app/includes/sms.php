<?php
// Real credentials live outside public_html, exactly like
// db_credentials.php. If that file doesn't exist yet (fresh install,
// no SMS provider configured), everything here safely no-ops -- the
// existing email reminders keep working regardless.
$smsCredentialsPath = __DIR__ . '/../../../sms_credentials.php';
if (is_file($smsCredentialsPath)) {
    require_once $smsCredentialsPath;
}
if (!defined('SMS_PROVIDER')) {
    define('SMS_PROVIDER', 'none');
}

// Sends $message to $phone via whichever provider is configured.
// Never throws -- a misconfigured or unreachable provider just means
// the SMS/WhatsApp reminder is skipped; email reminders are unaffected.
function sendSmsReminder($phone, $message) {
    if (empty($phone) || SMS_PROVIDER === 'none') {
        return;
    }

    try {
        if (SMS_PROVIDER === 'whatsapp') {
            sendViaWhatsApp($phone, $message);
        } elseif (SMS_PROVIDER === 'sms_gateway') {
            sendViaSmsGateway($phone, $message);
        }
    } catch (Exception $e) {
        error_log('sendSmsReminder failed: ' . $e->getMessage());
    }
}

function sendViaWhatsApp($phone, $message) {
    if (!defined('WHATSAPP_PHONE_NUMBER_ID') || !defined('WHATSAPP_ACCESS_TOKEN')
        || WHATSAPP_PHONE_NUMBER_ID === '' || WHATSAPP_ACCESS_TOKEN === '') {
        return;
    }

    $url = 'https://graph.facebook.com/v18.0/' . WHATSAPP_PHONE_NUMBER_ID . '/messages';
    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to' => $phone,
        'type' => 'text',
        'text' => ['body' => $message],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
}

function sendViaSmsGateway($phone, $message) {
    if (!defined('SMS_GATEWAY_URL') || !defined('SMS_GATEWAY_API_KEY')
        || SMS_GATEWAY_URL === '' || SMS_GATEWAY_API_KEY === '') {
        return;
    }

    $payload = json_encode(['to' => $phone, 'message' => $message]);

    $ch = curl_init(SMS_GATEWAY_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SMS_GATEWAY_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
}
