<?php
// ============================================================
// TEMPLATE ONLY - do not put real values in this file.
//
// Copy this content into a NEW file named sms_credentials.php,
// placed ONE LEVEL ABOVE public_html - the same account-root
// location as db_credentials.php. Keeps API keys completely
// outside the directory Git deployment manages.
//
// Leave SMS_PROVIDER as 'none' (or leave this file absent
// entirely) to run with SMS/WhatsApp reminders silently
// disabled - only the existing email reminders will send.
// ============================================================

// One of: 'none', 'whatsapp', 'sms_gateway'
define('SMS_PROVIDER', 'none');

// --- WhatsApp Cloud API (Meta Business) ---
// From your WhatsApp Business Platform app in Meta Business Manager.
define('WHATSAPP_PHONE_NUMBER_ID', '');
define('WHATSAPP_ACCESS_TOKEN', '');

// --- Generic SMS gateway (e.g. MSG91, Twilio) ---
// A simple POST endpoint: SMS_GATEWAY_URL receives {to, message} and an
// Authorization: Bearer SMS_GATEWAY_API_KEY header. Adjust
// includes/sms.php if your provider's request shape differs.
define('SMS_GATEWAY_URL', '');
define('SMS_GATEWAY_API_KEY', '');
