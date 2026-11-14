<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A WhatsApp template's offer code. A template may carry an offer's code in
 * one of its body variables and in a copy-code button: either the offer's
 * own code, the same for everyone (shared), or a single-use key code made
 * for each recipient when the message is queued, valid for the number of
 * days set on the template.
 *
 * The setting is kept in whatsapp_templates.offer as JSON: mode ('' none,
 * shared, key), code (the offer's code), days (a key's validity) and
 * variable (the body variable that carries the code, '' for none).
 *
 * The senders (order moments, a form's message, the flow box) never choose
 * what fills the code: pg_whatsapp_message_components() leaves
 * PG_WHATSAPP_OFFER_PLACEHOLDER in its place, and pg_whatsapp_enqueue()
 * replaces it with the shared code or a new key, whose id goes into the
 * queue row's key_code_id; a row that ends failed disables its key. An
 * offer code goes only in a marketing template, so the marketing consent
 * gate of the queue covers every message that carries one.
 *
 * Loaded by includes/fn/whatsapp.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// What a sender's components hold where the offer code goes until the
// queue fills it; never sent to Meta.
define('PG_WHATSAPP_OFFER_PLACEHOLDER', '{{pg_offer_code}}');

// The letters of a key made for one recipient: no 0/O and 1/I, which are
// read wrong on a phone. Ten of them stay under the copy-code limit of 20.
define('PG_WHATSAPP_OFFER_KEY_LETTERS', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
define('PG_WHATSAPP_OFFER_KEY_LENGTH', 10);

// The longest validity a key may be given, in days.
define('PG_WHATSAPP_OFFER_DAYS_MAX', 365);


/* ---------------------------------------------------------------------------
   The setting (pure)
   --------------------------------------------------------------------------- */

/**
 * The offer setting of a template, read from the column or an array.
 *
 * @param mixed $raw JSON or array
 * @return array mode ('', shared, key), code, days, variable
 */
function pg_whatsapp_offer_read($raw)
{
    $offer = array('mode' => '', 'code' => '', 'days' => 0, 'variable' => '');
    $read = is_array($raw) ? $raw : json_decode((string) $raw, true);

    if (!is_array($read) || !isset($read['mode']) || !in_array($read['mode'], array('shared', 'key'), true)) {
        return $offer;
    }

    $offer['mode'] = $read['mode'];
    $offer['code'] = (isset($read['code']) && is_scalar($read['code'])) ? trim((string) $read['code']) : '';
    $offer['days'] = ($offer['mode'] === 'key') && isset($read['days']) && is_scalar($read['days']) && ctype_digit(trim((string) $read['days'])) ? (int) $read['days'] : 0;
    $offer['variable'] = (isset($read['variable']) && is_scalar($read['variable']) && preg_match('/^[a-z0-9_]{1,64}$/D', (string) $read['variable'])) ? (string) $read['variable'] : '';

    return $offer;
}

/**
 * The column's value of a setting.
 *
 * @param array $offer pg_whatsapp_offer_read()
 * @return string JSON, '' for no offer
 */
function pg_whatsapp_offer_json($offer)
{
    $offer = pg_whatsapp_offer_read($offer);

    return ($offer['mode'] === '') ? '' : json_encode($offer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * The setting a form posted: offer_mode, offer_code, offer_days and
 * offer_variable. A validity that is not a number is kept as -1, so the
 * check names it.
 *
 * @param array $post
 * @return array pg_whatsapp_offer_read() shape
 */
function pg_whatsapp_offer_posted($post)
{
    $text = function ($name) use ($post) {
        return (isset($post[$name]) && is_scalar($post[$name])) ? trim((string) $post[$name]) : '';
    };

    $mode = $text('offer_mode');

    if (!in_array($mode, array('shared', 'key'), true)) {
        return array('mode' => '', 'code' => '', 'days' => 0, 'variable' => '');
    }

    $days = $text('offer_days');

    return array(
        'mode'     => $mode,
        'code'     => $text('offer_code'),
        'days'     => ($mode === 'key') ? (ctype_digit($days) ? (int) $days : -1) : 0,
        'variable' => preg_match('/^[a-z0-9_]{1,64}$/D', $text('offer_variable')) ? $text('offer_variable') : '',
    );
}

/**
 * What the designer calls the variable that carries the offer code: the
 * chip a design puts where the code goes.
 *
 * @return string
 */
function pg_whatsapp_offer_label()
{
    return lang('Offer code');
}

/**
 * Whether an offer setting fits a template's components (pure but for
 * lang()): an offer chosen, a validity for a key, a variable the message
 * holds, somewhere for the code to go, and an offer for a copy-code button.
 *
 * @param array $offer      pg_whatsapp_offer_read()
 * @param array $components the template's components as Meta reads them
 * @return array field (offer_mode, offer_code, offer_days, offer_variable) => message
 */
function pg_whatsapp_offer_check($offer, $components)
{
    $variables = array();
    $copy = false;

    foreach ((is_array($components) ? $components : array()) as $component) {
        $type = (is_array($component) && isset($component['type'])) ? strtoupper((string) $component['type']) : '';

        if ($type === 'BODY') {
            preg_match_all('/\{\{\s*([a-z0-9_]{1,64})\s*\}\}/', isset($component['text']) ? (string) $component['text'] : '', $matches);
            $variables = $matches[1];
        } elseif (($type === 'BUTTONS') && isset($component['buttons']) && is_array($component['buttons'])) {
            foreach ($component['buttons'] as $button) {
                $copy = $copy || (is_array($button) && isset($button['type']) && (strtoupper((string) $button['type']) === 'COPY_CODE'));
            }
        }
    }

    if ($offer['mode'] === '') {
        return $copy ? array('offer_mode' => lang('The copy-code button needs an offer code: choose a shared code or a key for each person.')) : array();
    }

    $errors = array();

    if ($offer['code'] === '') {
        $errors['offer_code'] = lang('Choose the offer whose code the message carries.');
    }

    if (($offer['mode'] === 'key') && (($offer['days'] < 1) || ($offer['days'] > PG_WHATSAPP_OFFER_DAYS_MAX))) {
        $errors['offer_days'] = lang(array('string' => 'Give the key a validity of 1 to {var:1} days.', 'vars' => array(PG_WHATSAPP_OFFER_DAYS_MAX)));
    }

    if (($offer['variable'] !== '') && !in_array($offer['variable'], $variables, true)) {
        $errors['offer_variable'] = lang('The message no longer holds the variable chosen for the offer code.');
    } elseif (($offer['variable'] === '') && !$copy) {
        $errors['offer_variable'] = lang(array('string' => 'Put the variable {var:1} in the message, or add a copy-code button, so the code reaches the person.', 'vars' => array(pg_whatsapp_offer_label())));
    }

    return $errors;
}


/* ---------------------------------------------------------------------------
   A send's code (pure)
   --------------------------------------------------------------------------- */

/**
 * A new key for one recipient: ten letters and digits that read the same on
 * any phone. Not checked against the stored codes here.
 *
 * @return string
 */
function pg_whatsapp_offer_key_code()
{
    $letters = PG_WHATSAPP_OFFER_KEY_LETTERS;
    $code = '';

    for ($i = 0; $i < PG_WHATSAPP_OFFER_KEY_LENGTH; $i++) {
        $code .= $letters[random_int(0, strlen($letters) - 1)];
    }

    return $code;
}

/**
 * The last day a key is valid: the validity in days from the day it is
 * queued; no end for none.
 *
 * @param int $days
 * @param int $now
 * @return string Y-m-d, 0000-00-00 for no end
 */
function pg_whatsapp_offer_expiration($days, $now)
{
    return ((int) $days > 0) ? date('Y-m-d', strtotime('+' . (int) $days . ' day', (int) $now)) : '0000-00-00';
}

/**
 * Whether a send's components still wait for an offer code.
 *
 * @param array $components
 * @return bool
 */
function pg_whatsapp_offer_needed($components)
{
    return strpos((string) json_encode($components, JSON_UNESCAPED_SLASHES), PG_WHATSAPP_OFFER_PLACEHOLDER) !== false;
}

/**
 * A send's components with the offer code in its places.
 *
 * @param array  $components
 * @param string $code
 * @return array
 */
function pg_whatsapp_offer_fill($components, $code)
{
    foreach ($components as $key => $value) {
        if (is_array($value)) {
            $components[$key] = pg_whatsapp_offer_fill($value, $code);
        } elseif ($value === PG_WHATSAPP_OFFER_PLACEHOLDER) {
            $components[$key] = (string) $code;
        }
    }

    return $components;
}


/* ---------------------------------------------------------------------------
   The offers, the keys and the stored setting (database)
   --------------------------------------------------------------------------- */

/**
 * The offers a template may carry, for the designer's list.
 *
 * @return array rows: code, description, status
 */
function pg_whatsapp_offer_offers()
{
    return (array) db_items("SELECT code, description, status FROM offers ORDER BY code LIMIT 500");
}

/**
 * Whether an offer with this code exists and is switched on.
 *
 * @param string $code
 * @return bool
 */
function pg_whatsapp_offer_enabled($code)
{
    $result = db_q("SELECT id FROM offers WHERE (code = ?) AND (status = 'enabled') LIMIT 1", array((string) $code));

    return ($result !== false) && (mysqli_num_rows($result) > 0);
}

/**
 * The code one send carries, made when the message is queued: the offer's
 * own code, or a new single-use key code of the offer.
 *
 * @param array  $offer    pg_whatsapp_offer_read()
 * @param string $template the template's name, for the key's notes
 * @param string $number   E.164 digits, for the key's notes
 * @return array ok, code, key_code_id (0 for a shared code), error
 */
function pg_whatsapp_offer_code_for_send($offer, $template, $number)
{
    $fail = function ($error) {
        return array('ok' => false, 'code' => '', 'key_code_id' => 0, 'error' => $error);
    };

    if (($offer['mode'] === '') || ($offer['code'] === '')) {
        return $fail(lang('The template has an offer code, but no offer is set for it.'));
    }

    if (!pg_whatsapp_offer_enabled($offer['code'])) {
        return $fail(lang(array('string' => 'The offer {var:1} of the template is missing or switched off.', 'vars' => array($offer['code']))));
    }

    if ($offer['mode'] === 'shared') {
        return array('ok' => true, 'code' => $offer['code'], 'key_code_id' => 0, 'error' => '');
    }

    // A key no other key code or offer code holds.
    for ($try = 0; $try < 10; $try++) {
        $code = pg_whatsapp_offer_key_code();
        $taken = db_q("SELECT code FROM key_codes WHERE code = ? UNION SELECT code FROM offers WHERE code = ? LIMIT 1", array($code, $code));

        if (($taken !== false) && (mysqli_num_rows($taken) === 0)) {
            break;
        }

        $code = '';
    }

    if ($code === '') {
        return $fail(lang('No free key code could be made for the offer.'));
    }

    $made = db_q(
        "INSERT INTO key_codes SET code = ?, offer_code = ?, enabled = '1', expiration_date = ?, single_use = '1',
            report = 'offer_code', notes = ?, user = '0', timestamp = UNIX_TIMESTAMP()",
        array($code, $offer['code'], pg_whatsapp_offer_expiration($offer['days'], time()),
            lang(array('string' => 'WhatsApp template {var:1} to +{var:2}', 'vars' => array($template, $number)))));

    if ($made === false) {
        return $fail(lang('The key code of the offer could not be made.'));
    }

    return array('ok' => true, 'code' => $code, 'key_code_id' => (int) mysqli_insert_id(db::$con), 'error' => '');
}

/**
 * Switches off a key whose message did not go: it reached nobody.
 *
 * @param int $id key_codes.id
 * @return void
 */
function pg_whatsapp_offer_key_disable($id)
{
    if ((int) $id > 0) {
        db_q("UPDATE key_codes SET enabled = '0' WHERE id = '" . (int) $id . "'");
    }
}

/**
 * Keeps the offer setting of one template. It is the site's own setting,
 * never sent to Meta, so it can be changed on any template.
 *
 * @param int   $id
 * @param array $offer pg_whatsapp_offer_read()
 * @return bool
 */
function pg_whatsapp_offer_save($id, $offer)
{
    return db_q("UPDATE whatsapp_templates SET offer = ?, updated_at = UNIX_TIMESTAMP() WHERE id = '" . (int) $id . "'",
        array(pg_whatsapp_offer_json($offer))) !== false;
}
