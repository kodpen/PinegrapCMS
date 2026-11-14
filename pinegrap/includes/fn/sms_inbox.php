<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A customer's SMS to the site's number: received from the provider, a stop
 * word written to the consent log as an SMS marketing refusal, then the
 * message brought into the channel the SMS settings name, as a
 * conversation by number in the mail tables (includes/workspace/
 * mail_phone.php). Twilio posts each message to sms_webhook.php, signed with
 * the account's auth token; the providers that are asked by poll come
 * later. Nothing is kept from a number when no channel takes SMS: an
 * incoming SMS is stored only as a conversation's item.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Posts to sms_webhook.php one address may make in a minute. */
define('PG_SMS_WEBHOOK_PER_MINUTE', 120);

/** The largest post sms_webhook.php reads. */
define('PG_SMS_WEBHOOK_MAX_BODY', 65536);

/**
 * How each provider brings a customer's SMS: webhook (it posts to
 * sms_webhook.php), or none yet.
 *
 * @return array provider => webhook|''
 */
function pg_sms_incoming_ways()
{
    return array('twilio' => 'webhook', 'netgsm' => '', 'iletimerkezi' => '');
}

/**
 * Whether SMS from customers can be received through this provider.
 *
 * @param string $provider
 * @return bool
 */
function pg_sms_incoming_supported($provider)
{
    return (pg_sms_incoming_ways()[(string) $provider] ?? '') !== '';
}

/**
 * The words that, written as the whole message, stop marketing messages to
 * the number: any case, Turkish letters folded, spaces and a closing dot
 * ignored. Owner (2026-11-13, #9286).
 *
 * @return string[]
 */
function pg_sms_stop_words()
{
    return array('STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'IPTAL', 'RET', 'DUR');
}

/**
 * Whether a message is a stop word alone. Pure.
 *
 * @param string $text
 * @return bool
 */
function pg_sms_is_stop_word($text)
{
    $text = trim((string) preg_replace('/[\s.!]+/u', ' ', (string) $text));
    $text = strtr($text, array('İ' => 'I', 'ı' => 'i', 'Ş' => 'S', 'ş' => 's', 'Ğ' => 'G', 'ğ' => 'g', 'Ü' => 'U', 'ü' => 'u', 'Ö' => 'O', 'ö' => 'o', 'Ç' => 'C', 'ç' => 'c'));
    $text = strtoupper(str_replace(' ', '', $text));

    return in_array($text, pg_sms_stop_words(), true);
}

/**
 * The address the provider posts customers' SMS to, to be pasted into its
 * settings.
 *
 * @param string $provider
 * @return string
 */
function pg_sms_webhook_url($provider)
{
    // init.php defines the site's address; without it (a unit test run) the
    // address is relative.
    $base = defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY : '';

    return $base . '/sms_webhook.php?provider=' . rawurlencode((string) $provider);
}

/**
 * Twilio's signature of a post: HMAC-SHA1 with the auth token over the
 * address it was sent to followed by each field's name and value, the names
 * sorted, in Base64 (X-Twilio-Signature). Pure.
 *
 * @param string $url    the address as configured at Twilio
 * @param array  $fields the posted fields
 * @param string $token  the account's auth token
 * @return string
 */
function pg_sms_twilio_signature($url, $fields, $token)
{
    $fields = is_array($fields) ? $fields : array();
    ksort($fields, SORT_STRING);
    $data = (string) $url;

    foreach ($fields as $name => $value) {
        $data .= (string) $name . (is_scalar($value) ? (string) $value : '');
    }

    return base64_encode(hash_hmac('sha1', $data, (string) $token, true));
}

/**
 * Whether a post carries Twilio's signature for this account. Pure.
 *
 * @param string $url
 * @param array  $fields
 * @param string $header X-Twilio-Signature
 * @param string $token
 * @return bool
 */
function pg_sms_twilio_signed($url, $fields, $header, $token)
{
    return ((string) $token !== '') && ((string) $header !== '') && hash_equals(pg_sms_twilio_signature($url, $fields, $token), (string) $header);
}

/**
 * The message of a Twilio post, in the shape ws_mail_phone_ingest() reads,
 * or null when it is not one. Pure but for pg_sms_number().
 *
 * @param array $fields MessageSid, From, Body, NumMedia
 * @return array|null wamid (the provider's id), from (E.164 digits), text,
 *                    kind (text or image), name, at (0: now)
 */
function pg_sms_twilio_message($fields)
{
    $read = function ($name, $length) use ($fields) {
        return (is_array($fields) && isset($fields[$name]) && is_scalar($fields[$name])) ? mb_substr(trim((string) $fields[$name]), 0, $length, 'UTF-8') : '';
    };

    $id = $read('MessageSid', 64);
    $from = pg_sms_number($read('From', 32));

    if (!preg_match('/^[A-Za-z0-9]{10,64}$/D', $id) || ($from === '')) {
        return null;
    }

    return array(
        'wamid' => $id,
        'from'  => $from,
        'text'  => $read('Body', 1600),
        'kind'  => ((int) $read('NumMedia', 3) > 0) ? 'image' : 'text',
        'name'  => '',
        'at'    => 0,
    );
}

/**
 * The contacts a mobile number belongs to, by the number as people typed it.
 *
 * @param string $number E.164 digits
 * @return int[]
 */
function pg_sms_number_contacts($number)
{
    $variants = pg_sms_number_variants((string) $number);

    if (!$variants) {
        return array();
    }

    $rows = (array) db_items("SELECT id, mobile_phone FROM contacts
        WHERE (mobile_phone <> '') AND (" . pg_sms_number_sql('mobile_phone') . " IN ('" . implode("', '", array_map('e', $variants)) . "'))
        ORDER BY id");
    $ids = array();

    foreach ($rows as $row) {
        if (pg_sms_number((string) $row['mobile_phone']) === (string) $number) {
            $ids[] = (int) $row['id'];
        }
    }

    return $ids;
}

/**
 * Writes the SMS marketing refusal of a stop word for each contact of the
 * number; the İYS push sends it on as any refusal. A number no contact has
 * records nothing: the consent log needs a person.
 *
 * @param string $number E.164 digits
 * @param int    $now
 * @return int rows written
 */
function pg_sms_stop_refusal($number, $now)
{
    if (!function_exists('pg_consent_log_record')) {
        return 0;
    }

    $written = 0;

    foreach (pg_sms_number_contacts($number) as $contact_id) {
        $written += (pg_consent_log_record(array(
            'channel'    => 'sms',
            'purpose'    => 'marketing',
            'status'     => 'refused',
            'source'     => 'message',
            'contact_id' => $contact_id,
            'created_at' => (int) $now,
            'ip'         => '',
            'user_agent' => '',
        )) > 0) ? 1 : 0;
    }

    return $written;
}

/**
 * Takes one customer's SMS: a stop word is written to the consent log, then
 * the message joins or opens the conversation of its number in the SMS
 * box's channel (with the Workspace on). A demonstration site receives
 * nothing.
 *
 * @param array $message pg_sms_twilio_message()
 * @param int   $now
 * @return array stopped (refusal rows written), status (ws_mail_phone_ingest()
 *               status, or off)
 */
function pg_sms_incoming_receive($message, $now)
{
    $out = array('stopped' => 0, 'status' => 'off');

    if (!is_array($message) || (function_exists('pg_demo') && pg_demo())) {
        return $out;
    }

    if (pg_sms_is_stop_word($message['text'])) {
        $out['stopped'] = pg_sms_stop_refusal($message['from'], $now);
    }

    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED) {
        return $out;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    if (!ws_enabled() || !ws_mail_phone_ready()) {
        return $out;
    }

    $contacts = pg_sms_number_contacts($message['from']);
    $ingested = ws_mail_phone_ingest(ws_mail_phone_box('sms'), $message + array('contact_id' => $contacts ? $contacts[0] : 0), $now);
    $out['status'] = (string) $ingested['status'];

    return $out;
}

/**
 * Answers a call to sms_webhook.php and ends the request: an SMS Twilio
 * posts, signed with the account's auth token, answered with an empty TwiML
 * response so Twilio sends no reply of its own. A wrong signature counts as
 * an offence, as a wrong WhatsApp signature does.
 */
function pg_sms_webhook_receive()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
    $provider = (isset($_GET['provider']) && is_string($_GET['provider'])) ? $_GET['provider'] : '';

    if ($method !== 'POST') {
        pg_whatsapp_webhook_answer(405, 'method_not_allowed', 'text/plain');
    }

    if (pg_sms_webhook_throttled()) {
        pg_whatsapp_webhook_answer(429, 'rate_limited', 'text/plain');
    }

    if (($provider !== 'twilio') || !pg_sms_incoming_supported($provider)) {
        pg_whatsapp_webhook_answer(404, 'unknown_provider', 'text/plain');
    }

    $app = pg_sms_app($provider);

    if (!$app['enabled'] || ($app['password'] === '')) {
        pg_whatsapp_webhook_answer(403, 'off', 'text/plain');
    }

    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    if ($length > PG_SMS_WEBHOOK_MAX_BODY) {
        pg_whatsapp_webhook_answer(413, 'too_large', 'text/plain');
    }

    $signature = isset($_SERVER['HTTP_X_TWILIO_SIGNATURE']) ? (string) $_SERVER['HTTP_X_TWILIO_SIGNATURE'] : '';

    if (!pg_sms_twilio_signed(pg_sms_webhook_url($provider), $_POST, $signature, $app['password'])) {
        pg_whatsapp_webhook_offence();
        pg_whatsapp_webhook_answer(401, 'wrong_signature', 'text/plain');
    }

    pg_sms_incoming_receive(pg_sms_twilio_message($_POST), time());

    pg_whatsapp_webhook_answer(200, '<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 'text/xml');
}

/**
 * Whether this address has posted more than PG_SMS_WEBHOOK_PER_MINUTE times
 * in the last minute.
 *
 * @return bool
 */
function pg_sms_webhook_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject') || !function_exists('waf_ip_is_counted')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    return ($ip !== '') && waf_ip_is_counted($ip) && waf_rate_exceeded(waf_ip_subject($ip), 'sms_webhook', PG_SMS_WEBHOOK_PER_MINUTE, 60);
}

/**
 * The SMS settings' "Customers' messages" part: the address to give the
 * provider and the Channel field, '' for a provider that cannot receive or
 * with the Workspace off.
 *
 * @param string $provider the chosen provider
 * @param int    $user_id
 * @return string HTML
 */
function pg_sms_channel_field($provider, $user_id)
{
    if (!pg_sms_incoming_supported($provider) || !defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED) {
        return '';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    $field = ws_mail_phone_card_field((int) $user_id, 'sms');

    if ($field === '') {
        return '';
    }

    return '
                                                        <div class="pg-f-md">
                                                            <label for="sms_webhook_url" class="form-label">' . lang('Address for customers\' messages') . '</label>
                                                            <input type="text" id="sms_webhook_url" class="form-control" value="' . h(pg_sms_webhook_url($provider)) . '" readonly>
                                                            <div class="form-text">' . lang('In Twilio, paste it as the number\'s "A message comes in" webhook (HTTP POST).') . '</div>
                                                        </div>' . $field;
}

/**
 * Reads the Channel field before anything of the pane is written.
 *
 * @param array  $post
 * @param int    $user_id
 * @param object $liveform
 * @return array|null channel_id, or null when there is nothing to save
 */
function pg_sms_channel_check($post, $user_id, $liveform)
{
    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED || !array_key_exists('sms_channel', (array) $post)) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    return ws_mail_phone_card_check($post, (int) $user_id, $liveform, 'sms');
}

/**
 * Writes the SMS box after the provider's settings were saved, so its name
 * and number are the provider's own.
 *
 * @param array|null $decision pg_sms_channel_check()
 * @param int        $user_id
 */
function pg_sms_channel_save($decision, $user_id)
{
    if (!is_array($decision) || !function_exists('ws_mail_phone_box_write')) {
        return;
    }

    $provider = pg_sms_provider();

    if (!pg_sms_incoming_supported($provider)) {
        return;
    }

    $app = pg_sms_app($provider);

    ws_mail_phone_box_write('sms', ws_mail_phone_sms_box_values($provider, $app['sender'], (int) $decision['channel_id']), (int) $user_id);
}
