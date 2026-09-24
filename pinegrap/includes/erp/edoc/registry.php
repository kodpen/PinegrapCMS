<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the e-document layer: which provider sends the invoices, and one
 * door to it.
 *
 * The invoice, the return and the delivery note are Pinegrap's own records;
 * turning them into an e-Fatura, an e-Arşiv or an e-İrsaliye at the tax
 * authority is done through a provider (Paraşüt, Logo İşbaşı, a private
 * integrator). The store picks one on the E-Invoice card of the commerce
 * settings (includes/settings/commerce.php, saved by commerce.save.php) and nothing
 * else in the module knows which: every caller goes through erp_edoc_call(),
 * and the provider is a driver file under includes/erp/edoc/.
 *
 * A driver is a set of functions with one prefix, erp_edoc_<code>_, the way
 * the API's module seam works (includes/api/modules.php): what is not defined
 * is not supported, and erp_edoc_supports() says so before anyone tries.
 *
 *   erp_edoc_<code>_info()                    label, description, docs, capabilities
 *   erp_edoc_<code>_fields()                  the credentials the settings card asks for
 *   erp_edoc_<code>_ping($credentials)        can we reach the provider with these
 *   erp_edoc_<code>_check_taxpayer($vkn)      is this tax number an e-invoice user
 *   erp_edoc_<code>_send_invoice($invoice, $lines, $options)
 *   erp_edoc_<code>_poll($invoice, $job_id)
 *   erp_edoc_<code>_cancellable($invoice)     can it be taken back there, and how
 *   erp_edoc_<code>_cancel_invoice($invoice, $reason)
 *   erp_edoc_<code>_fetch_document($invoice, $format)
 *   erp_edoc_<code>_send_waybill($waybill, $lines, $options)
 *   erp_edoc_<code>_date_refusal($message)    was an answer GİB's date order, and the latest date
 *   erp_edoc_<code>_inbox($from, $to, $page)      one page of incoming e-invoices
 *   erp_edoc_<code>_inbox_document($item, $format) an incoming one's UBL or PDF
 *                                             (both: includes/erp/edoc/inbox.php)
 *   erp_edoc_<code>_accounts($page)           one page of the provider's customer and supplier cards
 *   erp_edoc_<code>_account($external_id)     one of those cards, whole
 *   erp_edoc_<code>_account_save($card, $external_id) create ('' id) or change one card
 *                                             (all three: includes/erp/edoc/account_sync.php)
 *
 * Every operation answers an array with 'success' and 'error'; what else it
 * carries is written at each contract function below. poll() may also name
 * the provider's customer card the invoice landed on ('party' =>
 * external_id, code, title); the account is then linked to that card when
 * it has none yet (erp_edoc_account_link_from_invoice()).
 *
 * Taking a document back is two questions, because the answer depends on
 * the provider and on the document. cancellable() is asked when a screen is
 * drawn, from the row alone (no call to the provider): ['ok' => bool,
 * 'manual' => bool, 'reason' => string, 'url' => string]. ok with manual
 * false means cancel_invoice() does it through the API; ok with manual true
 * means the operator does it on the provider's own screen (url) and
 * cancel_invoice() only checks that it has been done; not ok means the
 * document stays and is answered with a return. cancel_invoice() answers
 * ['success', 'error', 'message', 'outcome' => 'deleted'|'cancelled'].
 *
 * Capabilities (info()['capabilities']): 'einvoice', 'earchive',
 * 'ewaybill', 'inbox', 'accounts' - its customer and supplier cards can be
 * read and written - and 'return' - the provider takes a sales return
 * through its API. The screens ask erp_edoc_has_capability() before they
 * offer a step, so a driver that gains one needs no change anywhere else. Credentials live in
 * erp_edoc_providers (4.61), AES-encrypted the way the Paraşüt secret has
 * been since Faz -1; a driver that keeps its own (Paraşüt, in the commerce
 * settings card) says so by declaring no fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * The providers this build ships a driver for: code => file.
 *
 * Adding a provider is adding a file here and a line to this list. The code
 * is what erp_edoc_providers.provider and erp_invoices.edoc_provider store,
 * so it never changes once a document has been sent through it.
 *
 * @return array
 */
function erp_edoc_drivers()
{
    return array(
        'parasut' => PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php',
        'isbasi' => PG_FUNCTIONS_DIR . '/includes/erp/edoc/isbasi.php',
    );
}

/**
 * Loads one driver's functions, once.
 *
 * @param string $code
 * @return bool  false for a code no driver answers to
 */
function erp_edoc_load($code)
{
    $drivers = erp_edoc_drivers();
    $code = (string) $code;

    if (!isset($drivers[$code]) || !is_file($drivers[$code])) {
        return false;
    }

    require_once($drivers[$code]);

    return function_exists('erp_edoc_' . $code . '_info');
}

/**
 * Whether the 4.61 tables and columns are there.
 *
 * @return bool
 */
function erp_edoc_installed()
{
    static $installed = null;

    if ($installed === null) {
        $installed = function_exists('waf_table_has_column') && waf_table_has_column('erp_edoc_providers', 'provider');
    }

    return $installed;
}

/**
 * The stored row of one provider, or null.
 *
 * @param string $code
 * @return array|null
 */
function erp_edoc_provider_row($code)
{
    if (!erp_edoc_installed()) {
        return null;
    }

    $row = db_item("SELECT * FROM erp_edoc_providers WHERE provider = '" . escape((string) $code) . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The provider the store sends through, or '' when documents stay PDF.
 *
 * Read once per request. Before 4.61 the only switch was the ERP Paraşüt
 * flag, and it is honoured so an installation that had it on keeps it.
 *
 * @return string  A driver code, or ''
 */
function erp_edoc_active()
{
    static $active = null;

    if ($active !== null) {
        return $active;
    }

    $active = '';

    if (erp_edoc_installed()) {
        $code = (string) db_value("SELECT provider FROM erp_edoc_providers WHERE is_active = 1 ORDER BY id ASC LIMIT 1");

        if (($code !== '') && erp_edoc_load($code)) {
            $active = $code;
        }
    } elseif (defined('ERP_PARASUT_ENABLED') && ((int) ERP_PARASUT_ENABLED === 1) && erp_edoc_load('parasut')) {
        $active = 'parasut';
    }

    return $active;
}

/**
 * Whether the store works with e-documents at all: a provider is selected,
 * or one carried documents before. Screens that speak of e-Invoices, GİB
 * numbers and ETTNs ask this first - a store without either (most of the
 * world) is not shown what it cannot have.
 *
 * @return bool
 */
function erp_edoc_in_use()
{
    static $in_use = null;

    if ($in_use === null) {
        $in_use = erp_edoc_installed()
            && ((erp_edoc_active() !== '')
                || ((int) db_value("SELECT COUNT(*) FROM erp_invoices WHERE edoc_status <> 'none' LIMIT 1") > 0)
                || ((int) db_value("SELECT COUNT(*) FROM erp_waybills WHERE edoc_external_id <> '' LIMIT 1") > 0));
    }

    return $in_use;
}

/**
 * Whether Turkish tax features are offered: the store is in Turkey, or it
 * works through a Turkish e-document provider. VAT withholding (tevkifat),
 * the internet-sale box of an e-Arşiv invoice and the Paraşüt export
 * profiles are Turkish law and Turkish software; elsewhere they are noise.
 * A document that already carries one of them shows it regardless.
 *
 * @return bool
 */
function erp_turkish_features()
{
    return (erp_account_country('') === 'TR') || erp_edoc_in_use();
}

/**
 * What a driver says about itself, with defaults filled in.
 *
 * @param string $code
 * @return array|null  label, description, docs_url, capabilities (list of
 *                     'einvoice', 'earchive', 'ewaybill', 'inbox', 'accounts',
 *                     'return'), settings_note
 */
function erp_edoc_info($code)
{
    if (!erp_edoc_load($code)) {
        return null;
    }

    $info = (array) call_user_func('erp_edoc_' . $code . '_info');

    return $info + array(
        'label' => ucfirst($code),
        'description' => '',
        'docs_url' => '',
        'capabilities' => array(),
        'settings_note' => '',
    );
}

/**
 * The credential fields a driver asks the settings card for.
 *
 * @param string $code
 * @return array  Each: name, label, type ('text' | 'password' | 'select'),
 *                help, required; a select also carries options (value => label)
 */
function erp_edoc_fields($code)
{
    if (!erp_edoc_load($code) || !function_exists('erp_edoc_' . $code . '_fields')) {
        return array();
    }

    $fields = array();

    foreach ((array) call_user_func('erp_edoc_' . $code . '_fields') as $field) {
        $fields[] = ((array) $field) + array('type' => 'text', 'help' => '', 'required' => false);
    }

    return $fields;
}

/**
 * The stored credentials of a provider, decrypted.
 *
 * @param string $code
 * @return array  name => value; empty when nothing is stored
 */
function erp_edoc_credentials($code)
{
    $row = erp_edoc_provider_row($code);

    if (($row === null) || (trim((string) $row['credentials_enc']) === '') || (strpos((string) $row['credentials_enc'], ':') === false)) {
        return array();
    }

    list($cipher, $iv) = explode(':', (string) $row['credentials_enc'], 2);
    $json = decode_ssl_keys($cipher, $iv);
    $values = ($json === '') ? null : json_decode($json, true);

    return is_array($values) ? $values : array();
}

/**
 * Store a provider's credentials: what was typed over what was there. An
 * empty password field keeps the stored one (the form shows a placeholder,
 * not the secret), so a save that changes nothing changes nothing.
 *
 * @param string $code
 * @param array  $posted  name => value
 * @return bool
 */
function erp_edoc_credentials_save($code, $posted)
{
    if (!erp_edoc_installed() || !erp_edoc_load($code)) {
        return false;
    }

    $current = erp_edoc_credentials($code);

    foreach (erp_edoc_fields($code) as $field) {
        $name = (string) $field['name'];

        // A field the caller did not send at all is not a field the caller
        // cleared: a partial array (one box, one screen) leaves the rest as
        // it is.
        if (!array_key_exists($name, $posted)) {
            continue;
        }

        // Emptied means emptied. The box shows what is stored, so a blank
        // one is the operator saying blank - not the old "empty means keep",
        // which left no way to clear a key that should no longer be there.
        $current[$name] = trim((string) $posted[$name]);
    }

    $encoded = '';

    if (array_filter($current, 'strlen') !== array()) {
        list($cipher, $iv) = encrypt_string_with_iv(json_encode($current, JSON_UNESCAPED_UNICODE));
        $encoded = $cipher . ':' . $iv;
    }

    erp_edoc_provider_ensure($code);

    return (erp_query("UPDATE erp_edoc_providers
        SET credentials_enc = '" . escape($encoded) . "', updated_at = '" . time() . "'
        WHERE provider = '" . escape($code) . "'") !== false);
}

/**
 * Makes sure a provider has its row, so activation and credentials have
 * somewhere to land.
 *
 * @param string $code
 */
function erp_edoc_provider_ensure($code)
{
    if (!erp_edoc_installed() || (erp_edoc_provider_row($code) !== null)) {
        return;
    }

    erp_query("INSERT INTO erp_edoc_providers (provider, is_active, credentials_enc, settings, created_at, updated_at)
        VALUES ('" . escape((string) $code) . "', 0, '', '', '" . time() . "', '" . time() . "')");
}

/**
 * Makes one provider the store's, or none.
 *
 * @param string $code  A driver code, or '' for "documents stay PDF"
 * @return bool
 */
function erp_edoc_activate($code)
{
    if (!erp_edoc_installed()) {
        return false;
    }

    $code = (string) $code;

    if (($code !== '') && !erp_edoc_load($code)) {
        return false;
    }

    if (erp_query("UPDATE erp_edoc_providers SET is_active = 0, updated_at = '" . time() . "' WHERE is_active = 1") === false) {
        return false;
    }

    if ($code === '') {
        return true;
    }

    erp_edoc_provider_ensure($code);

    return (erp_query("UPDATE erp_edoc_providers SET is_active = 1, updated_at = '" . time() . "' WHERE provider = '" . escape($code) . "'") !== false);
}

/**
 * Whether the active driver (or a named one) implements an operation.
 *
 * @param string $operation  'send_invoice', 'send_waybill', 'check_taxpayer', ...
 * @param string $code       A driver code; the active one when omitted
 * @return bool
 */
function erp_edoc_supports($operation, $code = '')
{
    $code = ($code === '') ? erp_edoc_active() : (string) $code;

    if (($code === '') || !erp_edoc_load($code)) {
        return false;
    }

    return function_exists('erp_edoc_' . $code . '_' . $operation);
}

/**
 * Runs an operation on a driver. The one door.
 *
 * @param string $operation
 * @param array  $arguments
 * @param string $code  A driver code; the active one when omitted
 * @return array  ['success' => bool, 'error' => string, ...]
 */
function erp_edoc_call($operation, $arguments = array(), $code = '')
{
    $code = ($code === '') ? erp_edoc_active() : (string) $code;

    if ($code === '') {
        return array('success' => false, 'error' => lang('No e-document provider is selected on the E-Invoice card of the commerce settings.'));
    }

    if (!erp_edoc_supports($operation, $code)) {
        return array('success' => false, 'error' => lang(array(
            'string' => 'The {var:1} driver does not do this yet ({var:2}).',
            'vars' => array(erp_edoc_info($code)['label'], $operation),
        )));
    }

    $result = call_user_func_array('erp_edoc_' . $code . '_' . $operation, (array) $arguments);

    if (!is_array($result)) {
        return array('success' => false, 'error' => lang('The driver answered with nothing usable.'));
    }

    return $result + array('success' => false, 'error' => '');
}

/**
 * Tries the provider with the credentials on file and records the outcome
 * on its row, so the settings card can show when it last answered.
 *
 * @param string $code
 * @return array ['success' => bool, 'message' => string, 'details' => array]
 */
function erp_edoc_test($code)
{
    $code = (string) $code;

    if (!erp_edoc_load($code)) {
        return array('success' => false, 'message' => lang('Unknown e-document provider.'), 'details' => array());
    }

    $result = erp_edoc_supports('ping', $code)
        ? (array) call_user_func('erp_edoc_' . $code . '_ping', erp_edoc_credentials($code))
        : array('success' => false, 'message' => lang('This driver has no connection test.'));

    $result = $result + array('success' => false, 'message' => '', 'details' => array());

    if (erp_edoc_installed()) {
        erp_edoc_provider_ensure($code);
        erp_query("UPDATE erp_edoc_providers
            SET checked_at = '" . time() . "',
                check_status = '" . ($result['success'] ? 'ok' : 'failed') . "',
                check_message = '" . escape(mb_substr((string) $result['message'], 0, 255)) . "',
                updated_at = '" . time() . "'
            WHERE provider = '" . escape($code) . "'");
    }

    return $result;
}

/**
 * Test or live, when the driver knows the difference. A driver that does
 * not define erp_edoc_<code>_environment() is simply live.
 *
 * @param string $code  A driver code; the active one when omitted
 * @return array ['is_test' => bool, 'label' => string]
 */
function erp_edoc_environment($code = '')
{
    $code = ($code === '') ? erp_edoc_active() : (string) $code;
    $none = array('is_test' => false, 'label' => '');

    if (($code === '') || !erp_edoc_supports('environment', $code)) {
        return $none;
    }

    return ((array) call_user_func('erp_edoc_' . $code . '_environment')) + $none;
}

/**
 * The identity number an e-Archive invoice carries when the buyer is a
 * final consumer nobody asked for a TCKN from - eleven ones, which is what
 * the tax authority's own e-Arşiv guidance uses and what every provider
 * expects for a counter sale. A driver uses it only where its provider
 * demands an identity number; it is never written to the account card,
 * because the card's blank is the truth about what the store knows.
 *
 * @return string
 */
function erp_edoc_final_consumer_tckn()
{
    return '11111111111';
}

/**
 * Whether an identity number passes the tax authority's check digits: the
 * TCKN algorithm for eleven digits, the VKN algorithm for ten. Every GİB
 * integrator runs the same arithmetic and refuses the document when it
 * fails, so a mistyped digit is caught here, on the card, instead of as a
 * rejected send.
 *
 * The final-consumer number does not pass the TCKN arithmetic and is
 * accepted by GİB anyway; it is valid only where the caller says so.
 *
 * @param string $number
 * @param bool   $allow_final_consumer
 * @return bool
 */
function erp_edoc_tax_number_valid($number, $allow_final_consumer = false)
{
    $number = preg_replace('/\D/', '', (string) $number);

    if ($number === erp_edoc_final_consumer_tckn()) {
        return (bool) $allow_final_consumer;
    }

    if (strlen($number) === 11) {
        $d = array_map('intval', str_split($number));

        if ($d[0] === 0) {
            return false;
        }

        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        if (((($odd * 7) - $even) % 10 + 10) % 10 !== $d[9]) {
            return false;
        }

        return (array_sum(array_slice($d, 0, 10)) % 10) === $d[10];
    }

    if (strlen($number) === 10) {
        $d = array_map('intval', str_split($number));
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $tmp = ($d[$i] + (9 - $i)) % 10;
            $sum += ($tmp === 9) ? 9 : (($tmp * (2 ** (9 - $i))) % 9);
        }

        return ((10 - ($sum % 10)) % 10) === $d[9];
    }

    return false;
}

/**
 * A person's name as first name and surname, the way GİB carries an
 * individual: the last word is the surname, the rest the first name.
 * A one-word name cannot be split, and an individual without a surname is
 * refused by every integrator ("Ad ve Soyad gereklidir").
 *
 * @param string $name
 * @return array|null  ['first' => string, 'last' => string], or null
 */
function erp_edoc_person_name($name)
{
    $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

    if (count($parts) < 2) {
        return null;
    }

    $last = array_pop($parts);

    return array('first' => implode(' ', $parts), 'last' => $last);
}

/**
 * Whether a postcode has the shape its country uses. Only Turkey is known
 * here - five digits (PTT) - and a foreign code is taken as written.
 *
 * @param string $postcode
 * @param string $country_code
 * @return bool
 */
function erp_edoc_postcode_valid($postcode, $country_code = 'TR')
{
    $postcode = trim((string) $postcode);

    if (strtoupper(trim((string) $country_code)) !== 'TR') {
        return $postcode !== '';
    }

    return (bool) preg_match('/^\d{5}$/', $postcode);
}

/**
 * The capability words as the screen prints them.
 *
 * @return array  code => label
 */
function erp_edoc_capability_labels()
{
    return array(
        'einvoice' => lang('e-Invoice'),
        'earchive' => lang('e-Archive'),
        'ewaybill' => lang('e-Delivery note'),
        'inbox' => lang('Incoming e-invoices'),
        'accounts' => lang('Account sync'),
        'return' => lang('Return invoices'),
    );
}

/**
 * Whether a provider declares a capability (see the header).
 *
 * @param string $capability
 * @param string $code  A driver code; the active one when omitted
 * @return bool
 */
function erp_edoc_has_capability($capability, $code = '')
{
    $code = ($code === '') ? erp_edoc_active() : (string) $code;

    if (($code === '') || !erp_edoc_load($code)) {
        return false;
    }

    $info = erp_edoc_info($code);

    return in_array((string) $capability, (array) ($info['capabilities'] ?? array()), true);
}

/* ---------------------------------------------------------------------------
   What every driver needs and none should write twice: the provider's own
   settings blob, a session cache for its access token, one HTTP call, and
   the masked request log (erp_edoc_log, 4.62).
   --------------------------------------------------------------------------- */

/**
 * The provider's settings blob, decoded. Free-form JSON a driver keeps for
 * itself (code lists, the last base URL it was told, ...). Secrets do not
 * go here in the clear: erp_edoc_session_save() encrypts the token.
 *
 * @param string $code
 * @return array
 */
function erp_edoc_settings($code)
{
    $row = erp_edoc_provider_row($code);
    $settings = ($row === null) ? null : json_decode((string) $row['settings'], true);

    return is_array($settings) ? $settings : array();
}

/**
 * Writes keys into the settings blob; a null value removes the key.
 *
 * @param string $code
 * @param array  $values  key => value
 * @return bool
 */
function erp_edoc_settings_save($code, $values)
{
    if (!erp_edoc_installed() || !erp_edoc_load($code)) {
        return false;
    }

    $settings = erp_edoc_settings($code);

    foreach ((array) $values as $key => $value) {
        if ($value === null) {
            unset($settings[$key]);
        } else {
            $settings[$key] = $value;
        }
    }

    erp_edoc_provider_ensure($code);

    return (erp_query("UPDATE erp_edoc_providers
        SET settings = '" . escape(json_encode($settings, JSON_UNESCAPED_UNICODE)) . "', updated_at = '" . time() . "'
        WHERE provider = '" . escape($code) . "'") !== false);
}

/**
 * The cached session (access token and whatever the login handed back),
 * or an empty array when there is none or it has expired. Stored encrypted
 * inside the settings blob: a token is a credential for as long as it lives.
 *
 * @param string $code
 * @return array
 */
function erp_edoc_session($code)
{
    $settings = erp_edoc_settings($code);
    $encoded = (string) ($settings['session_enc'] ?? '');

    if (($encoded === '') || (strpos($encoded, ':') === false)) {
        return array();
    }

    list($cipher, $iv) = explode(':', $encoded, 2);
    $json = decode_ssl_keys($cipher, $iv);
    $session = ($json === '') ? null : json_decode($json, true);

    if (!is_array($session)) {
        return array();
    }

    if (isset($session['expires_at']) && ((int) $session['expires_at'] <= time())) {
        return array();
    }

    return $session;
}

/**
 * Keeps a session for the next request. $session['expires_at'] decides
 * how long; an empty array forgets it.
 *
 * @param string $code
 * @param array  $session
 * @return bool
 */
function erp_edoc_session_save($code, $session)
{
    if (empty($session)) {
        return erp_edoc_settings_save($code, array('session_enc' => null));
    }

    list($cipher, $iv) = encrypt_string_with_iv(json_encode($session, JSON_UNESCAPED_UNICODE));

    return erp_edoc_settings_save($code, array('session_enc' => $cipher . ':' . $iv));
}

/**
 * One HTTP call. JSON in, JSON out; the raw body is kept for the log and
 * for answers that are not JSON.
 *
 * @param string       $method   GET | POST | PUT | DELETE
 * @param string       $url
 * @param array        $headers  'Name: value' strings
 * @param array|string $body     An array is sent as JSON; a string as is; null sends nothing
 * @param int          $timeout  seconds
 * @return array ['http_code' => int, 'body' => array|null, 'raw' => string,
 *                'error' => string (transport error, '' otherwise), 'duration_ms' => int]
 */
function erp_edoc_http($method, $url, $headers = array(), $body = null, $timeout = 40)
{
    $started = microtime(true);
    $ch = curl_init($url);

    // Sent with no User-Agent a request looks anonymous to the far side's
    // firewall (see _parasut_request()).
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');

    if ($body !== null) {
        $payload = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : (string) $body;
        $headers[] = 'Content-Length: ' . strlen($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => (int) $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ));

    // The certificate is verified against the bundle the rest of the
    // software uses (CURL_CA_BUNDLE in data/config.php, the update channel's
    // setting); pg_curl_tls() applies it and the operator's last-resort
    // switch alike. No silent insecure fallback: what goes out here carries
    // tax numbers and the store's İşbaşı password.
    if (function_exists('pg_curl_tls')) {
        pg_curl_tls($ch);
    } elseif (defined('CURL_CA_BUNDLE') && (CURL_CA_BUNDLE !== '') && is_file(CURL_CA_BUNDLE)) {
        curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_BUNDLE);
    }

    $raw = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    if (($curl_error !== '') && function_exists('pg_curl_tls_hint')) {
        $curl_error .= pg_curl_tls_hint($curl_errno);
    }

    $raw = is_string($raw) ? $raw : '';
    $decoded = ($raw === '') ? null : json_decode($raw, true);

    return array(
        'http_code' => $http_code,
        'body' => is_array($decoded) ? $decoded : null,
        'raw' => $raw,
        'error' => (string) $curl_error,
        'duration_ms' => (int) round((microtime(true) - $started) * 1000),
    );
}

/**
 * Blanks what a log must not keep: secrets, tokens, tax and identity
 * numbers, e-mail addresses. Works on the JSON text, so nested keys are
 * covered without walking the structure.
 *
 * @param string $text
 * @return string
 */
function erp_edoc_mask($text)
{
    $text = (string) $text;

    // "key": "value" pairs whose key names a secret or a person.
    $text = preg_replace_callback(
        '/("(?:[^"]*?(?:password|passwd|secret|apikey|api_key|token|authorization|tcknvkn|taxorpersonalid|vkntckn|identifier|tax_number|taxnumber|tckn|vkn|iban|email|emailaddress|useremail|phone|address|firstname|lastname|fullname|name|title)[^"]*?)"\s*:\s*)("(?:[^"\\\\]|\\\\.)*"|-?\d[\d.]*)/i',
        function ($m) {
            return $m[1] . '"***"';
        },
        $text
    );

    // Header lines and bare long digit runs (a VKN is 10, a TCKN 11 digits).
    $text = preg_replace('/(Authorization:\s*Bearer\s+)\S+/i', '$1***', $text);
    $text = preg_replace('/((?:apikey|tenantid|userid|useremail|username|password):\s*)\S+/i', '$1***', $text);
    $text = preg_replace('/\b\d{10,11}\b/', '**********', $text);

    return $text;
}

/**
 * One line in erp_edoc_log: what was asked and what came back, both as
 * masked excerpts. Rows older than thirty days go with the writes, one
 * sweep in fifty.
 *
 * @param string $provider
 * @param string $doc_type   'invoice' | 'waybill' | 'account' | '' (session, ping, lookups)
 * @param int    $doc_id
 * @param string $method
 * @param string $url        Logged without its query string values
 * @param array  $result     What erp_edoc_http() returned
 * @param mixed  $request    The body that was sent (array or string), or null
 */
function erp_edoc_log($provider, $doc_type, $doc_id, $method, $url, $result, $request = null)
{
    if (!erp_edoc_installed() || !function_exists('waf_table_has_column') || !waf_table_has_column('erp_edoc_log', 'provider')) {
        return;
    }

    $path = (string) preg_replace('/=([^&]*)/', '=…', (string) parse_url($url, PHP_URL_PATH) . ((string) parse_url($url, PHP_URL_QUERY) !== '' ? '?' . parse_url($url, PHP_URL_QUERY) : ''));
    $request_text = ($request === null) ? '' : (is_array($request) ? json_encode($request, JSON_UNESCAPED_UNICODE) : (string) $request);
    $response_text = ((string) ($result['error'] ?? '') !== '') ? 'curl: ' . $result['error'] : (string) ($result['raw'] ?? '');

    erp_query("INSERT INTO erp_edoc_log (provider, doc_type, doc_id, method, path, http_code, duration_ms, request_excerpt, response_excerpt, created_at)
        VALUES ('" . escape((string) $provider) . "', '" . escape((string) $doc_type) . "', '" . (int) $doc_id . "',
            '" . escape(strtoupper((string) $method)) . "', '" . escape(mb_substr($path, 0, 255)) . "',
            '" . (int) ($result['http_code'] ?? 0) . "', '" . (int) ($result['duration_ms'] ?? 0) . "',
            '" . escape(mb_substr(erp_edoc_mask($request_text), 0, 500)) . "',
            '" . escape(mb_substr(erp_edoc_mask($response_text), 0, 500)) . "',
            '" . time() . "')");

    if (mt_rand(1, 50) === 1) {
        erp_query("DELETE FROM erp_edoc_log WHERE created_at < '" . (time() - (30 * 86400)) . "'");
    }
}

/**
 * The last log lines for a document, newest first - what the invoice
 * screen shows under "e-document".
 *
 * @param string $doc_type
 * @param int    $doc_id
 * @param int    $limit
 * @return array
 */
function erp_edoc_log_for($doc_type, $doc_id, $limit = 10)
{
    if (!function_exists('waf_table_has_column') || !waf_table_has_column('erp_edoc_log', 'provider')) {
        return array();
    }

    return db_items("SELECT * FROM erp_edoc_log
        WHERE doc_type = '" . escape((string) $doc_type) . "' AND doc_id = '" . (int) $doc_id . "'
        ORDER BY id DESC LIMIT " . max(1, (int) $limit));
}

/**
 * The counterparty of an invoice as a driver wants it: the copy taken when
 * the document was issued, or the live card when the copy is missing.
 * Same rule as erp_invoice_document_data().
 *
 * @param array $invoice  An erp_invoices row
 * @return array title, is_person, tax_number, tax_office, email, phone,
 *               address, district, city, country_code, postcode
 */
function erp_edoc_invoice_party($invoice)
{
    $account = ((int) ($invoice['account_id'] ?? 0) > 0)
        ? db_item("SELECT * FROM erp_accounts WHERE id = '" . (int) $invoice['account_id'] . "' LIMIT 1")
        : null;
    $account = is_array($account) ? $account : array();

    if (trim((string) ($invoice['account_title'] ?? '')) !== '') {
        return array(
            'title' => (string) $invoice['account_title'],
            'is_person' => ((int) ($account['is_person'] ?? 1) === 1),
            'tax_number' => (string) ($invoice['account_tax_number'] ?? ''),
            'tax_office' => (string) ($invoice['account_tax_office'] ?? ''),
            'email' => (string) ($invoice['account_email'] ?? ''),
            'phone' => (string) ($account['phone'] ?? ''),
            'address' => (string) ($invoice['account_address'] ?? ''),
            // Out of the copy since 4.64. A document issued before that has
            // them empty there, so the card answers for those - which is
            // what this function did for every invoice until now.
            'district' => (trim((string) ($invoice['account_district'] ?? '')) !== '')
                ? (string) $invoice['account_district'] : (string) ($account['district'] ?? ''),
            'city' => (string) ($invoice['account_city'] ?? ''),
            'country_code' => (string) ($invoice['account_country_code'] ?? 'TR'),
            'postcode' => (trim((string) ($invoice['account_postcode'] ?? '')) !== '')
                ? (string) $invoice['account_postcode'] : (string) ($account['postcode'] ?? ''),
        );
    }

    return array(
        'title' => (string) ($account['title'] ?? ''),
        'is_person' => ((int) ($account['is_person'] ?? 1) === 1),
        'tax_number' => (string) ($account['tax_number'] ?? ''),
        'tax_office' => (string) ($account['tax_office'] ?? ''),
        'email' => (string) ($account['email'] ?? ''),
        'phone' => (string) ($account['phone'] ?? ''),
        'address' => (string) ($account['address'] ?? ''),
        'district' => (string) ($account['district'] ?? ''),
        'city' => (string) ($account['city'] ?? ''),
        'country_code' => (string) ($account['country_code'] ?? 'TR'),
        'postcode' => (string) ($account['postcode'] ?? ''),
    );
}
