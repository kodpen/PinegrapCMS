<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Personal data: the person token and the consent log.
 *
 * The person token is a keyed hash of a person's e-mail address. It ties the
 * rows that must outlive the person's own records (a consent kept as proof, an
 * anonymised order) to the person without keeping the address itself.
 *
 * consent_log (2026.11.0, 11.0.16) keeps one row each time a person gives or
 * refuses a consent: the cookie notice's categories, marketing by e-mail, SMS
 * or phone call, and the documents accepted at checkout. Rows are appended,
 * never changed; the person's current answer is the newest row of each
 * channel and purpose (pg_consent_log_current()). Writing a row never stops
 * the action it records: a row that cannot be written is left out.
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
 * An e-mail address in the one form the person token is made from: trimmed
 * and lower-cased. Anything that is not one address comes back empty.
 *
 * @param mixed $email
 * @return string
 */
function pg_privacy_normalize_email($email)
{
    if (!is_string($email)) {
        return '';
    }

    $email = mb_strtolower(trim($email), 'UTF-8');

    if (($email === '') || (strlen($email) > 254) || preg_match('/[\s<>,;"]/u', $email)) {
        return '';
    }

    $at = strrpos($email, '@');

    if (($at === false) || ($at === 0) || ($at === strlen($email) - 1) || (strpos($email, '@') !== $at)) {
        return '';
    }

    return $email;
}

/**
 * The person token of an e-mail address: HMAC-SHA256 under the site's
 * ENCRYPTION_KEY, 64 hex characters. A plain hash would be read back by
 * hashing candidate addresses; the key keeps it a pseudonym. The prefix keeps
 * it apart from any other HMAC made with the same key.
 *
 * @param mixed       $email
 * @param string|null $key  the key, ENCRYPTION_KEY when null
 * @return string  '' for an address that is not one, or without a key
 */
function pg_privacy_person_token($email, $key = null)
{
    $email = pg_privacy_normalize_email($email);

    if ($key === null) {
        $key = defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
    }

    if (($email === '') || ((string) $key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'person:' . $email, (string) $key);
}

/**
 * Whether a value has the shape of a person token.
 *
 * @param mixed $token
 * @return bool
 */
function pg_privacy_is_person_token($token)
{
    return is_string($token) && (preg_match('/^[0-9a-f]{64}$/', $token) === 1);
}

/**
 * The channels a consent is given on, with the purposes each one takes.
 *
 * cookie:   the cookie notice's optional categories (pg_consent_category_letters()).
 * email, sms, call: commercial messages, the three İYS channels.
 * contract: the documents accepted at checkout, the order_documents types.
 *
 * @return array  channel => list of purposes
 */
function pg_consent_log_purposes()
{
    return array(
        'cookie'   => array_keys(pg_consent_category_letters()),
        'email'    => array('marketing'),
        'sms'      => array('marketing'),
        'call'     => array('marketing'),
        'contract' => array('preinfo', 'contract', 'kvkk'),
    );
}

/**
 * Whether a purpose may be recorded on a channel: one of the channel's
 * purposes, or on the e-mail channel one contact group, "group:<id>" (the
 * group a double opt-in form adds to once the person confirms). The column is
 * 32 characters; the longest group purpose is 16.
 *
 * @param string $channel
 * @param mixed  $purpose
 * @return bool
 */
function pg_consent_log_purpose_valid($channel, $purpose)
{
    $purposes = pg_consent_log_purposes();
    $channel = (string) $channel;

    if (!isset($purposes[$channel]) || !is_string($purpose)) {
        return false;
    }

    if (in_array($purpose, $purposes[$channel], true)) {
        return true;
    }

    if (($channel !== 'email') || (preg_match('/^group:([1-9][0-9]{0,9})$/', $purpose, $match) !== 1)) {
        return false;
    }

    // INT UNSIGNED, the contact_groups.id column.
    return (float) $match[1] <= 4294967295;
}

/**
 * The answers a row records. A withdrawal is a refused row after a granted
 * one; pending is a request sent and not answered yet (a double opt-in mail),
 * never a consent.
 *
 * @return string[]
 */
function pg_consent_log_statuses()
{
    return array('granted', 'refused', 'pending');
}

/**
 * Where a consent was given. A new writer adds its source here.
 *
 * @return string[]
 */
function pg_consent_log_sources()
{
    return array(
        'banner',       // the cookie notice
        'signup',       // registration and membership forms
        'checkout',     // express order, order preview
        'account',      // My Account preferences
        'unsubscribe',  // the link in an e-mail
        'panel',        // recorded by staff (a paper form, a phone call)
        'import',       // brought in with a contact list
        'api',          // an application through the external API
        'iys',          // pulled back from İYS
        'form',         // a custom form that adds to a contact group (double opt-in)
    );
}

/**
 * The row one consent event becomes, or the reason it is refused.
 *
 * The person is the token (given, or made from 'email'), the user and the
 * contact; at least one is needed, except for a cookie answer, which an
 * anonymous visitor gives. ip and user_agent are taken as given and cut to
 * their columns; an address that is not an IP is dropped.
 *
 * @param array    $event  channel, purpose, status, source, and any of email,
 *                         person_token, user_id, contact_id, ip, user_agent,
 *                         created_at
 * @param int|null $now    the time stamped when created_at is missing
 * @return array|string  the row (column => value), or an error code:
 *                       channel, purpose, status, source, person
 */
function pg_consent_log_row(array $event, $now = null)
{
    $purposes = pg_consent_log_purposes();
    $channel = isset($event['channel']) ? (string) $event['channel'] : '';

    if (!isset($purposes[$channel])) {
        return 'channel';
    }

    $purpose = isset($event['purpose']) ? (string) $event['purpose'] : '';

    if (!pg_consent_log_purpose_valid($channel, $purpose)) {
        return 'purpose';
    }

    $status = isset($event['status']) ? (string) $event['status'] : '';

    if (!in_array($status, pg_consent_log_statuses(), true)) {
        return 'status';
    }

    $source = isset($event['source']) ? (string) $event['source'] : '';

    if (!in_array($source, pg_consent_log_sources(), true)) {
        return 'source';
    }

    $token = (isset($event['person_token']) && pg_privacy_is_person_token($event['person_token']))
        ? $event['person_token']
        : pg_privacy_person_token(isset($event['email']) ? $event['email'] : '');
    $user_id = isset($event['user_id']) ? max(0, (int) $event['user_id']) : 0;
    $contact_id = isset($event['contact_id']) ? max(0, (int) $event['contact_id']) : 0;

    if (($channel !== 'cookie') && ($token === '') && ($user_id === 0) && ($contact_id === 0)) {
        return 'person';
    }

    $ip = isset($event['ip']) ? trim((string) $event['ip']) : '';

    if (($ip !== '') && (filter_var($ip, FILTER_VALIDATE_IP) === false)) {
        $ip = '';
    }

    $agent = isset($event['user_agent']) ? (string) $event['user_agent'] : '';
    $agent = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $agent);
    $agent = mb_substr(is_string($agent) ? $agent : '', 0, 255, 'UTF-8');

    $created_at = isset($event['created_at']) ? (int) $event['created_at'] : 0;

    if ($created_at <= 0) {
        $created_at = ($now === null) ? time() : (int) $now;
    }

    return array(
        'created_at'   => $created_at,
        'person_token' => $token,
        'user_id'      => $user_id,
        'contact_id'   => $contact_id,
        'channel'      => $channel,
        'purpose'      => $purpose,
        'status'       => $status,
        'source'       => $source,
        'ip'           => substr($ip, 0, 45),
        'user_agent'   => $agent,
    );
}

/**
 * Whether the 11.0.16 table is there.
 *
 * @return bool
 */
function pg_consent_log_ready()
{
    return pg_schema_has('consent_log', 'person_token');
}

/**
 * Record one consent event.
 *
 * ip and user_agent default to this request's visitor (the address as the
 * firewall resolves it, so a proxy or CDN is not recorded for everyone); pass
 * them, empty when unknown, for an event that did not come from this request
 * (an import, an İYS pull).
 *
 * @param array $event  see pg_consent_log_row()
 * @return int  the new row's id, 0 when nothing was written
 */
function pg_consent_log_record(array $event)
{
    if (!array_key_exists('ip', $event)) {
        $event['ip'] = function_exists('waf_client_ip') ? waf_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    }

    if (!array_key_exists('user_agent', $event)) {
        $event['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    }

    $row = pg_consent_log_row($event);

    if (!is_array($row) || !pg_consent_log_ready()) {
        return 0;
    }

    $values = array();

    foreach ($row as $value) {
        $values[] = "'" . (is_int($value) ? $value : e($value)) . "'";
    }

    if (!@mysqli_query(db::$con, "INSERT INTO consent_log (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(', ', $values) . ")")) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * The SQL condition that finds a person's rows: by token (given, or made from
 * 'email'), user or contact, any of them.
 *
 * @param array $person  any of email, person_token, user_id, contact_id
 * @return string  '' when the person has no identifier
 */
function pg_consent_log_person_where(array $person)
{
    $conditions = array();

    $token = (isset($person['person_token']) && pg_privacy_is_person_token($person['person_token']))
        ? $person['person_token']
        : pg_privacy_person_token(isset($person['email']) ? $person['email'] : '');

    if ($token !== '') {
        $conditions[] = "person_token = '" . $token . "'";
    }

    foreach (array('user_id', 'contact_id') as $column) {
        $id = isset($person[$column]) ? (int) $person[$column] : 0;

        if ($id > 0) {
            $conditions[] = $column . " = '" . $id . "'";
        }
    }

    return $conditions ? '(' . implode(' OR ', $conditions) . ')' : '';
}

/**
 * A person's consent rows, newest first.
 *
 * @param array $person  see pg_consent_log_person_where()
 * @param int   $limit
 * @return array[]
 */
function pg_consent_log_rows(array $person, $limit = 1000)
{
    $where = pg_consent_log_person_where($person);

    if (($where === '') || !pg_consent_log_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT id, created_at, person_token, user_id, contact_id, channel, purpose, status, source, ip, user_agent
        FROM consent_log
        WHERE " . $where . "
        ORDER BY created_at DESC, id DESC
        LIMIT " . max(1, min(10000, (int) $limit))
    );

    return is_array($rows) ? $rows : array();
}

/**
 * The current answer of each channel and purpose: the newest row of each
 * (by created_at, then id), in any order of input.
 *
 * @param array[] $rows  consent_log rows
 * @return array  "channel:purpose" => row, sorted by key
 */
function pg_consent_log_current(array $rows)
{
    $current = array();

    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['channel'], $row['purpose'])) {
            continue;
        }

        $key = $row['channel'] . ':' . $row['purpose'];

        if (!isset($current[$key])) {
            $current[$key] = $row;
            continue;
        }

        $held = $current[$key];
        $newer = ((int) $row['created_at'] > (int) $held['created_at'])
            || (((int) $row['created_at'] === (int) $held['created_at']) && ((int) ($row['id'] ?? 0) > (int) ($held['id'] ?? 0)));

        if ($newer) {
            $current[$key] = $row;
        }
    }

    ksort($current);

    return $current;
}
