<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * How a person sends a request about their personal data themselves
 * (privacy_request.php): signed in, from My Account, at any time; as a
 * visitor only while the public form is switched on in the Privacy centre
 * (off by default: the page answers 404). A visitor's request is kept
 * unproven until the signed link mailed to the address typed is confirmed;
 * its 30 days start then. The link is stateless, as the double opt-in's
 * (includes/fn/form_subscription.php): request, issue time and an HMAC over
 * them and the request's address under the site's key, 48 hours. Sending is
 * counted per address in the firewall's rate store, which works with the
 * firewall off. The rows are those of includes/fn/privacy_requests.php.
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
 * The form's bounds. Pure.
 *
 * @return array  ttl (the proof link, seconds), per_email and per_ip (sends
 *                an hour), window (seconds), message (characters)
 */
function pg_privacy_request_form_limits()
{
    return array('ttl' => 172800, 'per_email' => 5, 'per_ip' => 20, 'window' => 3600, 'message' => 10000);
}

/**
 * Whether a stored switch value opens the public form: only '1'. Pure.
 *
 * @param mixed $stored
 * @return bool
 */
function pg_privacy_request_form_on_value($stored)
{
    return is_string($stored) && (trim($stored) === '1');
}

/**
 * Whether the public form is switched on (config_text privacy_request_form).
 *
 * @return bool  false until switched on
 */
function pg_privacy_request_form_on()
{
    return pg_privacy_request_form_on_value(pg_config_text('privacy_request_form'));
}

/**
 * Who privacy_request.php serves. Pure.
 *
 * @param bool $form_on   pg_privacy_request_form_on()
 * @param int  $user_id   the signed-in person, 0 for a visitor
 * @param bool $ready     the request table is there
 * @return string  account (signed in, always), form (a visitor, switch on),
 *                 off (answer 404)
 */
function pg_privacy_request_form_access($form_on, $user_id, $ready = true)
{
    if (!$ready) {
        return 'off';
    }

    if ((int) $user_id > 0) {
        return 'account';
    }

    return $form_on ? 'form' : 'off';
}

/**
 * Switch the public form from the Privacy centre: the role that may save
 * Site Settings (pg_privacy_rules_may_change()). One audit line.
 *
 * @param bool  $on
 * @param array $operator  role, id
 * @return string  refused | failed | ok
 */
function pg_privacy_request_form_switch($on, array $operator)
{
    if (!pg_privacy_rules_may_change($operator)) {
        return 'refused';
    }

    $before = pg_privacy_request_form_on();

    // Off is the absence of the row, as on a site that never switched it.
    if (!pg_config_text_save('privacy_request_form', $on ? '1' : '')) {
        return 'failed';
    }

    if ($before !== (bool) $on) {
        pg_audit('update', 'privacy_request', 0, array('public_form' => $before ? 1 : 0), array('public_form' => $on ? 1 : 0), '');
    }

    return 'ok';
}

/**
 * A request a person sends themselves. Pure.
 *
 * Signed in (source account) the address is the account's and the request is
 * open at once, its 30 days from now; a visitor's (source form) is unproven,
 * with its 7-day sweep moment in due_at (core/privacy-requests.md).
 *
 * @param array  $input   kind, email (a visitor), name, message
 * @param string $source  account | form
 * @param array  $person  user_id, email, name (signed in)
 * @param int    $now
 * @return array  errors (field => true), row
 */
function pg_privacy_request_form_row(array $input, $source, array $person, $now)
{
    $limits = pg_privacy_request_form_limits();
    $text = function ($value, $length) {
        $value = is_string($value) ? trim($value) : '';

        return mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value), 0, $length, 'UTF-8');
    };

    $account = ($source === 'account');
    $kind = $text($input['kind'] ?? '', 16);
    $email = pg_privacy_normalize_email($account ? (string) ($person['email'] ?? '') : $text($input['email'] ?? '', 200));
    $name = $account ? $text($person['name'] ?? '', 100) : $text($input['name'] ?? '', 100);
    $errors = array();

    if (!in_array($kind, pg_privacy_request_kinds(), true)) {
        $errors['kind'] = true;
    }

    if (!is_string($email) || ($email === '') || (strlen($email) > 100) || (strpos($email, '@') === false)) {
        $errors['email'] = true;
    }

    if ($account && ((int) ($person['user_id'] ?? 0) <= 0)) {
        $errors['account'] = true;
    }

    $row = array(
        'kind'        => $kind,
        'status'      => $account ? 'open' : 'unproven',
        'source'      => $account ? 'account' : 'form',
        'email'       => is_string($email) ? $email : '',
        'name'        => $name,
        'message'     => $text($input['message'] ?? '', $limits['message']),
        'user_id'     => $account ? (int) $person['user_id'] : 0,
        'received_at' => $account ? (int) $now : 0,
        'due_at'      => $account ? pg_privacy_request_due_at((int) $now) : pg_privacy_request_sweep_at((int) $now),
    );

    return array('errors' => $errors, 'row' => $row);
}

/**
 * The proof link's signature, under its own prefix so it never matches
 * another HMAC made with the same key. Pure.
 *
 * @param int    $id
 * @param string $email
 * @param int    $issued_at
 * @param string $key
 * @return string  '' when a part is missing
 */
function pg_privacy_request_proof_token($id, $email, $issued_at, $key)
{
    $email = pg_privacy_normalize_email($email);

    if (((int) $id <= 0) || ((int) $issued_at <= 0) || !is_string($email) || ($email === '') || ((string) $key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'privacy-request-proof:' . (int) $id . ':' . (int) $issued_at . ':' . $email, (string) $key);
}

/**
 * The proof link's values from a query string or a form. Pure.
 *
 * @param mixed $source
 * @return array|null  r (request id), t (issued at), s (signature)
 */
function pg_privacy_request_proof_parse($source)
{
    if (!is_array($source)) {
        return null;
    }

    $parsed = array();

    foreach (array('r', 't') as $name) {
        $value = $source[$name] ?? null;

        if (!is_string($value) || (preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1) || ((string) (int) $value !== $value)) {
            return null;
        }

        $parsed[$name] = (int) $value;
    }

    $signature = $source['s'] ?? null;

    if (!is_string($signature) || (preg_match('/^[0-9a-f]{64}$/', $signature) !== 1)) {
        return null;
    }

    $parsed['s'] = $signature;

    return $parsed;
}

/**
 * What the proof page does with a link. Pure.
 *
 * @param array|null $parsed    pg_privacy_request_proof_parse()
 * @param string     $expected  the signature the request's row gives
 * @param int        $now
 * @param bool       $proven    the request is proven already
 * @return string  invalid | proven | expired | confirm
 */
function pg_privacy_request_proof_state($parsed, $expected, $now, $proven)
{
    if (
        !is_array($parsed)
        || !isset($parsed['s'], $parsed['t'])
        || !is_string($expected)
        || ($expected === '')
        || !hash_equals($expected, (string) $parsed['s'])
        || ((int) $parsed['t'] > (int) $now + 300)
    ) {
        return 'invalid';
    }

    if ($proven) {
        return 'proven';
    }

    $limits = pg_privacy_request_form_limits();

    return ((int) $now > (int) $parsed['t'] + $limits['ttl']) ? 'expired' : 'confirm';
}

/**
 * The proof link.
 *
 * @param int    $id
 * @param int    $issued_at
 * @param string $signature
 * @return string
 */
function pg_privacy_request_proof_url($id, $issued_at, $signature)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/privacy_request.php?r=' . (int) $id . '&t=' . (int) $issued_at . '&s=' . rawurlencode((string) $signature);
}

/**
 * The rate buckets a send is counted in: the address asked about and the
 * sender's network address. Pure.
 *
 * @param string $email
 * @param string $ip  waf_ip_subject(), '' when not counted
 * @return array  subject => limit, all under scope privacy_request
 */
function pg_privacy_request_rate_subjects($email, $ip)
{
    $limits = pg_privacy_request_form_limits();
    $subjects = array();
    $email = pg_privacy_normalize_email($email);

    if (is_string($email) && ($email !== '')) {
        $subjects['email:' . $email] = $limits['per_email'];
    }

    if ((string) $ip !== '') {
        $subjects['ip:' . $ip] = $limits['per_ip'];
    }

    return $subjects;
}

/**
 * Whether this send is past the hourly limit. Every send is counted, so
 * the sixth in an hour for one address is refused. Fails open where the
 * rate store is not loaded.
 *
 * @param string $email
 * @return bool
 */
function pg_privacy_request_throttled($email)
{
    if (!function_exists('waf_rate_exceeded')) {
        return false;
    }

    $ip = function_exists('waf_client_ip') ? (string) waf_client_ip() : '';
    $ip = (($ip !== '') && function_exists('waf_ip_subject')) ? (string) waf_ip_subject($ip) : '';
    $limits = pg_privacy_request_form_limits();
    $exceeded = false;

    foreach (pg_privacy_request_rate_subjects($email, $ip) as $subject => $limit) {
        $exceeded = waf_rate_exceeded($subject, 'privacy_request', $limit, $limits['window']) || $exceeded;
    }

    if ($exceeded) {
        if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
            waf_log_event('rate', 'rate-privacy-request', 'rate', 5, 'privacy_request.php', $limits['per_email'] . '/60min');
        }

        if (function_exists('waf_text_log') && ($ip !== '')) {
            waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-privacy-request'));
        }
    }

    return $exceeded;
}

/**
 * The signed-in person who asks: the account's address and name.
 *
 * @param int $user_id
 * @return array  user_id, email, name; user_id 0 when not found
 */
function pg_privacy_request_account_person($user_id)
{
    $row = ((int) $user_id > 0) ? db_item(
        "SELECT user.user_id, user.user_email, contacts.first_name, contacts.last_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE user.user_id = '" . (int) $user_id . "'") : null;

    if (!is_array($row)) {
        return array('user_id' => 0, 'email' => '', 'name' => '');
    }

    return array(
        'user_id' => (int) $row['user_id'],
        'email'   => (string) $row['user_email'],
        'name'    => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
    );
}

/**
 * Store a request a person sent and, for a visitor, mail the proof link.
 *
 * @param array  $input   see pg_privacy_request_form_row()
 * @param string $source  account | form
 * @param array  $person  pg_privacy_request_account_person() when signed in
 * @return array  status (invalid | failed | ok), errors, id
 */
function pg_privacy_request_submit(array $input, $source, array $person)
{
    $now = time();
    $out = pg_privacy_request_form_row($input, $source, $person, $now);

    if ($out['errors']) {
        return array('status' => 'invalid', 'errors' => $out['errors'], 'id' => 0);
    }

    $row = $out['row'];
    $found = pg_privacy_export_find(array('email' => $row['email']));
    $row['contact_id'] = (int) (reset($found['person']['contact_ids']) ?: 0);

    if ($row['user_id'] <= 0) {
        $row['user_id'] = (int) (reset($found['person']['user_ids']) ?: 0);
    }

    $columns = array_keys($row);

    if (!pg_privacy_requests_ready() || (db_q("INSERT INTO privacy_requests (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")", array_values($row)) === false)) {
        return array('status' => 'failed', 'errors' => array(), 'id' => 0);
    }

    $id = (int) mysqli_insert_id(db::$con);
    pg_audit('create', 'privacy_request', $id, array(), pg_privacy_request_audit_view($row), '');

    if ($row['source'] === 'form') {
        $signature = pg_privacy_request_proof_token($id, $row['email'], $now, pg_fs_key());

        // Without a key no link can be made: the row is swept after 7 days.
        if ($signature !== '') {
            pg_privacy_request_send_proof($row['email'], pg_privacy_request_proof_url($id, $now, $signature));
        }
    }

    return array('status' => 'ok', 'errors' => array(), 'id' => $id);
}

/**
 * Mail the proof link. Plain text, queued, kept out of the failure log like
 * every mail that carries a working link.
 *
 * @param string $email
 * @param string $url
 * @return bool
 */
function pg_privacy_request_send_proof($email, $url)
{
    $site = (defined('ORGANIZATION_NAME') && (trim((string) ORGANIZATION_NAME) !== '')) ? (string) ORGANIZATION_NAME : (string) HOSTNAME_SETTING;

    $body =
        lang(array('string' => 'Someone, probably you, sent {var:1} a request about the personal data kept for {var:2}.', 'vars' => array($site, $email))) . "\n\n"
        . lang('To send it, open the link below and press the button on the page:') . "\n"
        . $url . "\n\n"
        . lang('If you did not send it, you can ignore this e-mail: nothing is done without your confirmation, and the link stops working after 48 hours.') . "\n";

    return (bool) email(array(
        'to'                 => $email,
        'from_name'          => $site,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang('Confirm your request about your personal data'),
        'format'             => 'plain_text',
        'body'               => $body,
        'secret'             => true,
        'queue'              => true,
    ));
}

/**
 * A request a proof link names, proven or not.
 *
 * @param int $id
 * @return array|null
 */
function pg_privacy_request_proof_row($id)
{
    if (((int) $id <= 0) || !pg_privacy_requests_ready()) {
        return null;
    }

    $row = db_item("SELECT id, kind, status, source, email, received_at, due_at FROM privacy_requests WHERE id = '" . (int) $id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The proof: the request becomes open and its 30 days start now. Only an
 * unproven row changes, so two presses of the button prove it once.
 *
 * @param array $request  pg_privacy_request_proof_row()
 * @return bool
 */
function pg_privacy_request_prove(array $request)
{
    $now = time();
    $changes = array('status' => 'open', 'received_at' => $now, 'due_at' => pg_privacy_request_due_at($now));

    if (db_q("UPDATE privacy_requests SET status = 'open', received_at = ?, due_at = ? WHERE (id = ?) AND (status = 'unproven') AND (received_at = 0)", array($now, $changes['due_at'], (int) $request['id'])) === false) {
        return false;
    }

    if (mysqli_affected_rows(db::$con) !== 1) {
        return false;
    }

    pg_audit('update', 'privacy_request', (int) $request['id'], pg_privacy_request_audit_view($request), pg_privacy_request_audit_view(array_merge($request, $changes)), '');

    return true;
}

/**
 * My Account's privacy part: one line and the link to the request page.
 *
 * @return array  shown, url
 */
function pg_privacy_request_account_view()
{
    return array(
        'shown' => pg_privacy_requests_ready(),
        'url'   => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/privacy_request.php',
    );
}

/**
 * The Privacy centre's card that switches the public form (Requests view).
 *
 * @param bool $on
 * @param bool $may_change  pg_privacy_rules_may_change()
 * @return string
 */
function pg_privacy_request_form_card($on, $may_change)
{
    $url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/privacy_request.php';
    $meta = $on
        ? h(lang('Visitors without an account can send a request at')) . ' <a href="' . h($url) . '" target="_blank" rel="noopener">' . h($url) . '</a>'
        : h(lang('Off: only signed-in people send requests, from My Account.'));

    return
        '<form method="post" action="privacy_centre.php" class="pg-card-inner mb-5" id="privacy_request_form_card">'
        . get_token_field()
        . '<input type="hidden" name="view" value="requests"><input type="hidden" name="action" value="public_form">'
        . '<div class="pg-card-inner-row"><span class="pg-icon-tile pg-icon-tile-lg ' . ($on ? 'pg-tone-teal' : 'pg-tone-gray') . '" aria-hidden="true"><span class="bi bi-globe"></span></span>'
        . '<div class="flex-grow-1"><strong class="pg-list-row-title">' . h(lang('Public request form')) . '</strong><div class="pg-text-meta">' . $meta . '</div></div>'
        . '<input type="checkbox" class="pg-switch" name="public_form" value="1"' . ($on ? ' checked' : '') . ($may_change ? '' : ' disabled') . ' aria-label="' . h(lang('Public request form')) . '" data-privacy-submit>'
        . '</div></form>';
}
