<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One-time codes sent by text message: the cash-on-delivery confirmation at
 * checkout and a member's sign-in code. A code is kept in the session as a
 * hash, bound to what it confirms (a purpose and a subject such as an order)
 * and to the number it was sent to, so a code texted to one number never
 * confirms another. It works for ten minutes, once, and the fifth wrong
 * guess ends it, so six digits cannot be walked through.
 *
 * The message goes through the SMS queue (pg_sms_enqueue()) with the code's
 * own end as its expires_at: a code the queue could not send in time is
 * dropped instead of arriving after it stopped working.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The purposes a code is sent for.
 *
 * @return array
 */
function pg_sms_code_purposes()
{
    return array('cod', 'signin');
}

/**
 * How long a texted code works, in seconds.
 *
 * @return int
 */
function pg_sms_code_lifetime()
{
    return 600;
}

/**
 * A new six-digit code.
 *
 * @return string
 */
function pg_sms_code_new()
{
    return sprintf('%06d', random_int(0, 999999));
}

/**
 * The hash a code is kept as.
 *
 * @param string $purpose
 * @param string $subject
 * @param string $number  E.164 digits
 * @param string $code
 * @return string
 */
function pg_sms_code_hash($purpose, $subject, $number, $code)
{
    return hash('sha256', 'sms-code:' . $purpose . ':' . $subject . ':' . $number . ':' . $code);
}

/**
 * Keeps a texted code: its hash, never the code, so a session store that is
 * read cannot hand it out. One code per purpose; a newer one replaces the
 * older.
 *
 * @param mixed  $store   the session's value for the purpose, by reference
 * @param string $purpose pg_sms_code_purposes()
 * @param string $subject what it confirms ('order:12', 'user:5')
 * @param string $number  E.164 digits it was sent to (pg_sms_number())
 * @param string $code
 * @param int    $now
 * @return bool false for an unknown purpose or an empty subject or number
 */
function pg_sms_code_keep(&$store, $purpose, $subject, $number, $code, $now)
{
    $purpose = (string) $purpose;
    $subject = (string) $subject;
    $number = (string) $number;

    if (!in_array($purpose, pg_sms_code_purposes(), true) || ($subject === '') || ($number === '')) {
        return false;
    }

    $store = array(
        'purpose' => $purpose,
        'subject' => $subject,
        'number'  => $number,
        'hash'    => pg_sms_code_hash($purpose, $subject, $number, (string) $code),
        'time'    => (int) $now,
        'misses'  => 0,
    );

    return true;
}

/**
 * Checks a typed code. A right one is used up; a wrong one counts, and the
 * fifth wrong guess ends the code. A code kept for another purpose, subject
 * or number is left as it is.
 *
 * @param mixed  $store   by reference
 * @param string $purpose
 * @param string $subject
 * @param string $number  E.164 digits the code must have gone to
 * @param string $code    white space is ignored
 * @param int    $now
 * @return string '' when accepted, 'code_wrong' when wrong, 'code_expired'
 *                when there is none to check (never sent, too old, used, too
 *                many wrong guesses, sent for something else or to another
 *                number)
 */
function pg_sms_code_check(&$store, $purpose, $subject, $number, $code, $now)
{
    if (!is_array($store) || !isset($store['purpose'], $store['subject'], $store['number'], $store['hash'], $store['time'], $store['misses'])
        || ((string) $store['purpose'] !== (string) $purpose)
        || ((string) $store['subject'] !== (string) $subject)
        || ((string) $store['number'] !== (string) $number)) {
        return 'code_expired';
    }

    if ((((int) $now - (int) $store['time']) > pg_sms_code_lifetime())
        || ((int) $store['time'] > (int) $now) || ((int) $store['misses'] >= 5)) {
        $store = null;

        return 'code_expired';
    }

    $code = (string) preg_replace('/\s+/', '', (string) $code);

    if (($code !== '') && hash_equals((string) $store['hash'], pg_sms_code_hash((string) $purpose, (string) $subject, (string) $number, $code))) {
        $store = null;

        return '';
    }

    $store['misses'] = (int) $store['misses'] + 1;

    if ($store['misses'] >= 5) {
        $store = null;

        return 'code_expired';
    }

    return 'code_wrong';
}

/**
 * The queue options of a code's message: a reference of its own per send
 * (the queue's reference is unique, and a code sent again is a new message)
 * and an expiry at the code's end.
 *
 * @param string $purpose
 * @param string $subject
 * @param int    $now
 * @param string $nonce   a few characters telling two sends in one second
 *                        apart
 * @return array reference, expires_at
 */
function pg_sms_code_queue_options($purpose, $subject, $now, $nonce)
{
    $reference = 'code:' . $purpose . ':' . $subject . ':' . (int) $now . ':' . $nonce;
    $reference = substr((string) preg_replace('/[^A-Za-z0-9:._\/-]/', '', $reference), 0, 64);

    return array(
        'reference'  => $reference,
        'expires_at' => (int) $now + pg_sms_code_lifetime(),
    );
}

/**
 * Whether a request asking for a code carries the session's form token
 * (validate_token_field()'s check, answered by the caller in its own shape):
 * without it a page on another site could have the shop text any number.
 *
 * @param mixed $session_token $_SESSION['software']['token']
 * @param mixed $posted_token
 * @return bool
 */
function pg_sms_code_token_ok($session_token, $posted_token)
{
    return is_string($session_token) && ($session_token !== '') && is_string($posted_token)
        && hash_equals($session_token, $posted_token);
}

/**
 * The SMS Messages event a purpose's code is sent with
 * (pg_sms_message_events()).
 *
 * @param string $purpose
 * @return string '' for an unknown purpose
 */
function pg_sms_code_event($purpose)
{
    $events = array('cod' => 'cod_code');

    return isset($events[$purpose]) ? $events[$purpose] : '';
}

/**
 * Whether codes of a purpose are sent: its event is switched on and SMS is
 * set up.
 *
 * @param string $purpose
 * @return bool
 */
function pg_sms_code_on($purpose)
{
    $event = pg_sms_code_event($purpose);

    if (($event === '') || !pg_sms_ready()) {
        return false;
    }

    $settings = pg_sms_message_settings();

    return !empty($settings[$event]['on']);
}

/**
 * Whether a checkout order is confirmed with a texted code (pure): an
 * offline payment, not put on the member's account, while the event is on.
 * Marketplace and ShipWorks orders never pass through the checkout.
 *
 * @param string $payment_method the order's method as checked
 *                               ('On Account' already read as offline)
 * @param bool   $on_account     the order goes on the member's account
 * @param bool   $on             pg_sms_code_on('cod')
 * @return bool
 */
function pg_sms_cod_applies($payment_method, $on_account, $on)
{
    return $on && !$on_account && ((string) $payment_method === 'Offline Payment');
}

/**
 * A number shown without most of its digits: the last two, so a customer
 * can tell which phone the code went to.
 *
 * @param string $number E.164 digits
 * @return string
 */
function pg_sms_code_masked($number)
{
    $number = (string) $number;

    return (strlen($number) < 4) ? '' : '•••• ' . substr($number, -2);
}

/**
 * How many codes may be sent, counted with the firewall's rate buckets:
 * per subject (an order, an account), per number and per address.
 *
 * @return array scope => [limit, window seconds]
 */
function pg_sms_code_limits()
{
    return array(
        'subject' => array(3, 600),
        'number'  => array(5, 3600),
        'address' => array(10, 3600),
    );
}

/**
 * Whether one more code may be sent. Every bucket is counted, so a refused
 * send counts too.
 *
 * @param string $purpose
 * @param string $subject
 * @param string $number
 * @return bool true when a limit is reached
 */
function pg_sms_code_throttled($purpose, $subject, $number)
{
    if (!function_exists('waf_rate_exceeded') || !isset(db::$con) || !db::$con) {
        return false;
    }

    $limits = pg_sms_code_limits();
    $ip = function_exists('waf_client_ip') ? (string) waf_client_ip() : '';
    $exceeded = waf_rate_exceeded($purpose . ':' . $subject, 'sms-code', $limits['subject'][0], $limits['subject'][1]);
    $exceeded = waf_rate_exceeded($number, 'sms-code-number', $limits['number'][0], $limits['number'][1]) || $exceeded;

    if (($ip !== '') && function_exists('waf_ip_subject')) {
        $exceeded = waf_rate_exceeded(waf_ip_subject($ip), 'sms-code-ip', $limits['address'][0], $limits['address'][1]) || $exceeded;
    }

    return $exceeded;
}

/**
 * Texts a new code and keeps it in the session in place of an older one.
 *
 * @param string $purpose
 * @param string $subject
 * @param string $phone the number as typed
 * @return array error ('' sent, 'off', 'number', 'limit', 'send'), masked
 */
function pg_sms_code_send($purpose, $subject, $phone)
{
    $number = pg_sms_number($phone);

    if (!pg_sms_code_on($purpose) || ((string) $subject === '')) {
        return array('error' => 'off', 'masked' => '');
    }

    if ($number === '') {
        return array('error' => 'number', 'masked' => '');
    }

    if (pg_sms_code_throttled($purpose, $subject, $number)) {
        return array('error' => 'limit', 'masked' => '');
    }

    $now = time();
    $code = pg_sms_code_new();
    $event = pg_sms_code_event($purpose);
    $definitions = pg_sms_message_events();

    $queued = pg_sms_enqueue($number, pg_sms_message_text($event, array('code' => $code)), $definitions[$event]['kind'],
        pg_sms_code_queue_options($purpose, $subject, $now, bin2hex(random_bytes(3))));

    if (!$queued['ok']) {
        return array('error' => 'send', 'masked' => '');
    }

    if (!isset($_SESSION['software']['sms_code']) || !is_array($_SESSION['software']['sms_code'])) {
        $_SESSION['software']['sms_code'] = array();
    }

    pg_sms_code_keep($_SESSION['software']['sms_code'][$purpose], $purpose, $subject, $number, $code, $now);

    return array('error' => '', 'masked' => pg_sms_code_masked($number));
}

/**
 * Checks a typed code against the one this session was sent.
 *
 * @param string $purpose
 * @param string $subject
 * @param string $phone the number the code must have gone to, as stored
 * @param string $code
 * @return string '' accepted, 'number', 'code_missing', 'code_wrong',
 *                'code_expired'
 */
function pg_sms_code_verify($purpose, $subject, $phone, $code)
{
    $number = pg_sms_number($phone);

    if ($number === '') {
        return 'number';
    }

    if (trim((string) $code) === '') {
        return 'code_missing';
    }

    if (!isset($_SESSION['software']['sms_code'][$purpose])) {
        return 'code_expired';
    }

    return pg_sms_code_check($_SESSION['software']['sms_code'][$purpose], $purpose, $subject, $number, (string) $code, time());
}

/**
 * The sentence for a send or check answer.
 *
 * @param string $error pg_sms_code_send(), pg_sms_code_verify()
 * @param string $masked the number the code went to, for a sent code
 * @return string
 */
function pg_sms_code_sentence($error, $masked = '')
{
    switch ($error) {
        case '':
            return lang(array('string' => 'A code was sent to your phone number ending in {var:1}. It works for 10 minutes.', 'vars' => array(substr((string) $masked, -2))));
        case 'off':
            return lang('A confirmation code is not needed.');
        case 'number':
            return lang('Enter a mobile phone number in your billing information: the confirmation code is sent to it as a text message.');
        case 'limit':
            return lang('Too many codes were asked for. Please wait a few minutes and try again.');
        case 'send':
            return lang('The code could not be sent. Please try again.');
        case 'code_missing':
            return lang('Enter the confirmation code we texted to you. Use "Send the code" to get one.');
        case 'code_wrong':
            return lang('The confirmation code is not correct.');
        default:
            return lang('The confirmation code has expired. Send a new code and try again.');
    }
}

/**
 * The cash-on-delivery code's field on a checkout form (order preview,
 * express order; classic, custom and designer layouts alike, which all print
 * the page's system part). It shows while offline payment is chosen; its
 * button asks sms_code.php for a code to the billing phone number typed on
 * the form, or the one the order has.
 *
 * @return string '' while the code is not asked for
 */
function pg_sms_cod_field_html()
{
    if (!pg_sms_code_on('cod')) {
        return '';
    }

    $url = (defined('PATH') ? PATH : '/') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : '') . '/sms_code.php';
    $texts = array(
        'sending' => lang('Sending…'),
        'failed'  => lang('The code could not be sent. Please try again.'),
    );

    return '<div class="pg-sms-cod mt-3 mb-3" data-pg-sms-cod data-url="' . h($url) . '" hidden>'
        . '<label for="pg_sms_cod_code" class="form-label">' . h(lang('Confirmation code')) . '</label>'
        . '<div class="form-text mb-2">' . h(lang('A cash-on-delivery order is confirmed with a code we text to your billing phone number.')) . '</div>'
        . '<div class="input-group">'
        . '<input type="text" name="sms_code" id="pg_sms_cod_code" class="form-control software_input_text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" value="">'
        . '<button type="button" class="btn btn-outline-secondary software_input_submit_secondary" data-pg-sms-cod-send>' . h(lang('Send the code')) . '</button>'
        . '</div>'
        . '<div class="form-text" data-pg-sms-cod-status role="status" aria-live="polite"></div>'
        . '</div>'
        . '<script>(function(){'
        . 'var box=document.currentScript&&document.currentScript.previousElementSibling;'
        . 'if(!box||!box.hasAttribute("data-pg-sms-cod"))return;'
        . 'var form=box.closest("form");'
        . 'if(form&&form.querySelector("[data-pg-sms-cod-ready]")){box.remove();return;}'
        . 'box.setAttribute("data-pg-sms-cod-ready","1");'
        . 'var texts=' . json_encode($texts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';'
        . 'if(!form)return;'
        . 'var input=box.querySelector("[name=sms_code]"),status=box.querySelector("[data-pg-sms-cod-status]"),button=box.querySelector("[data-pg-sms-cod-send]");'
        . 'function method(){var p=form.querySelector("input[name=payment_method]:checked")||form.querySelector("input[type=hidden][name=payment_method]");return p?p.value:"";}'
        . 'function sync(){var on=(method()==="Offline Payment");box.hidden=!on;input.required=on;}'
        . 'form.addEventListener("change",function(e){if(e.target&&e.target.name==="payment_method")sync();});'
        . 'window.addEventListener("pageshow",sync);sync();'
        . 'button.addEventListener("click",function(){'
        .   'var data=new FormData(),token=form.querySelector("[name=token]"),phone=form.querySelector("[name=billing_phone_number]");'
        .   'data.append("purpose","cod");data.append("token",token?token.value:"");'
        .   'if(phone&&phone.value)data.append("phone",phone.value);'
        .   'button.disabled=true;status.textContent=texts.sending;'
        .   'fetch(box.getAttribute("data-url"),{method:"POST",body:data,credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest"}})'
        .   '.then(function(r){return r.json();})'
        .   '.then(function(a){status.textContent=(a&&a.message)?a.message:texts.failed;if(a&&a.ok)input.focus();})'
        .   '.catch(function(){status.textContent=texts.failed;})'
        .   '.then(function(){setTimeout(function(){button.disabled=false;},3000);});'
        . '});'
        . '})();</script>';
}
