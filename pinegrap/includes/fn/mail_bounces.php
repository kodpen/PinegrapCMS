<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The bounce record and the send gate. One mail_bounces row per address
 * holds its current state: a hard bounce, a spam complaint or a manual block
 * suppresses the address at once, soft bounces when PG_MAIL_BOUNCE_SOFT_LIMIT
 * of them arrive within PG_MAIL_BOUNCE_SOFT_WINDOW. The mail queue asks the
 * gate before every send and campaigns skip a suppressed address. A change of
 * address or an explicit resubscribe clears the block. The address stays as
 * it is in contacts and the orders; nothing here deletes a contact.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Soft bounces (a full mailbox, a server that refused for now) suppress the
// address when this many arrive within the window. Older ones are forgotten
// by first_at, so a mailbox that was full last season is mailed again.
const PG_MAIL_BOUNCE_SOFT_LIMIT = 3;
const PG_MAIL_BOUNCE_SOFT_WINDOW = 2592000;

/**
 * @return string[] the kinds a bounce record holds, in mail_bounces.kind
 */
function pg_mail_bounce_kinds()
{
    return array('hard', 'soft', 'complaint', 'manual');
}

/**
 * The key of an address in mail_bounces: trimmed and lower-cased, or '' when
 * it is not one address or does not fit the column (VARCHAR(191)).
 *
 * @param mixed $address
 * @return string
 */
function pg_mail_bounce_address($address)
{
    if (!is_string($address)) {
        return '';
    }

    $address = strtolower(trim($address));

    if (($address === '') || (strlen($address) > 191)) {
        return '';
    }

    if (preg_match('/^[^@\s,;<>"]+@[^@\s,;<>"]+$/', $address) !== 1) {
        return '';
    }

    return $address;
}

/**
 * What one more bounce makes of an address's record.
 *
 * A soft bounce counts from first_at: one older than the window starts the
 * count again. A soft bounce never lowers a stronger record (hard, complaint,
 * manual) that still suppresses the address; it only moves last_at. A
 * cleared record (soft_count and first_at set to 0) starts afresh.
 *
 * @param array|null $row   the stored row, or null when there is none
 * @param string     $kind  hard, soft, complaint, manual
 * @param int        $now
 * @return array|null  kind, soft_count, first_at, last_at, suppressed; null
 *                     for an unknown kind
 */
function pg_mail_bounce_next($row, $kind, $now)
{
    $kind = (string) $kind;
    $now = (int) $now;

    if (!in_array($kind, pg_mail_bounce_kinds(), true)) {
        return null;
    }

    $row = is_array($row) ? $row : array();

    $current_kind = isset($row['kind']) ? (string) $row['kind'] : '';
    $soft_count = isset($row['soft_count']) ? (int) $row['soft_count'] : 0;
    $first_at = isset($row['first_at']) ? (int) $row['first_at'] : 0;
    $suppressed = !empty($row['suppressed']) ? 1 : 0;

    if ($kind === 'soft') {

        if ($suppressed && ($current_kind !== '') && ($current_kind !== 'soft')) {
            return array(
                'kind'       => $current_kind,
                'soft_count' => $soft_count,
                'first_at'   => $first_at,
                'last_at'    => $now,
                'suppressed' => 1,
            );
        }

        if (($first_at <= 0) || ($soft_count <= 0) || (($now - $first_at) > PG_MAIL_BOUNCE_SOFT_WINDOW)) {
            $soft_count = 0;
            $first_at = $now;
        }

        $soft_count++;

        return array(
            'kind'       => 'soft',
            'soft_count' => $soft_count,
            'first_at'   => $first_at,
            'last_at'    => $now,
            'suppressed' => ($suppressed || ($soft_count >= PG_MAIL_BOUNCE_SOFT_LIMIT)) ? 1 : 0,
        );
    }

    return array(
        'kind'       => $kind,
        'soft_count' => $soft_count,
        'first_at'   => ($first_at > 0) ? $first_at : $now,
        'last_at'    => $now,
        'suppressed' => 1,
    );
}

/**
 * The columns a clear writes: the block lifted and the soft count forgotten.
 * kind and last_at stay, as the history of the address.
 *
 * @param int $now
 * @return array
 */
function pg_mail_bounce_cleared($now)
{
    return array(
        'suppressed' => 0,
        'soft_count' => 0,
        'first_at'   => 0,
        'cleared_at' => (int) $now,
    );
}

/**
 * Whether a stored record stops mail to its address.
 *
 * @param mixed $row
 * @return bool
 */
function pg_mail_bounce_blocks($row)
{
    return is_array($row) && !empty($row['suppressed']);
}

/**
 * The keys to look up for a message's "to": each keyable address once.
 *
 * @param mixed $to  one address or a list, as email() takes it
 * @return string[]
 */
function pg_mail_bounce_lookup_addresses($to)
{
    $keys = array();

    foreach ((array) $to as $address) {
        $key = pg_mail_bounce_address($address);

        if ($key !== '') {
            $keys[$key] = true;
        }
    }

    return array_keys($keys);
}

/**
 * A message's recipients split by the gate. What cannot be keyed is kept:
 * email() refuses it with its own reason.
 *
 * @param mixed $to       one address or a list, as email() takes it
 * @param array $blocked  key => true for the suppressed addresses
 * @return array keep, blocked (the addresses as written)
 */
function pg_mail_bounce_split($to, array $blocked)
{
    $split = array('keep' => array(), 'blocked' => array());

    foreach ((array) $to as $address) {
        $key = pg_mail_bounce_address($address);

        if (($key !== '') && isset($blocked[$key])) {
            $split['blocked'][] = $address;
        } else {
            $split['keep'][] = $address;
        }
    }

    return $split;
}

/**
 * Whether the table is there: before the 2026.11.0 upgrade nothing is
 * suppressed.
 *
 * @return bool
 */
function pg_mail_bounces_ready()
{
    return pg_schema_has('mail_bounces', 'suppressed');
}

/**
 * The stored record of an address, or null.
 *
 * @param string $address
 * @return array|null
 */
function pg_mail_bounce_row($address)
{
    $key = pg_mail_bounce_address($address);

    if (($key === '') || !pg_mail_bounces_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT id, address, kind, source, soft_count, first_at, last_at, message_id, campaign_id, suppressed, cleared_at
        FROM mail_bounces
        WHERE address = '" . e($key) . "'");

    return is_array($row) ? $row : null;
}

/**
 * The gate: whether mail to this address is stopped (a hard bounce, a
 * complaint, a manual block, or the soft limit within the window). One
 * indexed read.
 *
 * @param string $address
 * @return bool
 */
function pg_mail_address_blocked($address)
{
    return pg_mail_bounce_blocks(pg_mail_bounce_row($address));
}

/**
 * The suppressed addresses among these keys, key => true. One query.
 *
 * @param string[] $keys  from pg_mail_bounce_lookup_addresses()
 * @return array
 */
function pg_mail_bounce_blocked_among(array $keys)
{
    if (!$keys || !pg_mail_bounces_ready()) {
        return array();
    }

    $quoted = array();

    foreach ($keys as $key) {
        $quoted[] = "'" . e((string) $key) . "'";
    }

    $blocked = array();

    foreach (db_items("SELECT address FROM mail_bounces WHERE (suppressed = 1) AND (address IN (" . implode(',', $quoted) . "))") as $row) {
        $blocked[(string) $row['address']] = true;
    }

    return $blocked;
}

/**
 * Record a bounce, a complaint or a manual block for an address.
 *
 * A complaint also withdraws the e-mail marketing consent: every contact with
 * the address gets opt_in 0 and a refused consent-log row, source complaint,
 * so lists and exports agree with the suppression.
 *
 * @param string $address
 * @param string $kind    hard, soft, complaint, manual
 * @param string $source  where it came from: dsn, a transport key, manual
 * @param array  $ref     optional message_id, campaign_id
 * @return bool  whether the address is suppressed after this record; false
 *               too when nothing could be recorded
 */
function pg_mail_bounce_record($address, $kind, $source, $ref = array())
{
    $key = pg_mail_bounce_address($address);

    if (($key === '') || !pg_mail_bounces_ready()) {
        return false;
    }

    $now = time();
    $row = pg_mail_bounce_row($key);
    $next = pg_mail_bounce_next($row, $kind, $now);

    if ($next === null) {
        return false;
    }

    $ref = (array) $ref;

    $columns = $next + array(
        'source'      => substr((string) $source, 0, 40),
        'message_id'  => substr(isset($ref['message_id']) ? (string) $ref['message_id'] : '', 0, 255),
        'campaign_id' => isset($ref['campaign_id']) ? max(0, (int) $ref['campaign_id']) : 0,
    );

    $set = array();

    foreach ($columns as $column => $value) {
        $set[] = $column . " = '" . e((string) $value) . "'";
    }

    if ($row) {
        db("UPDATE mail_bounces SET " . implode(', ', $set) . " WHERE id = '" . (int) $row['id'] . "'");
    } else {
        // Two reports of one address at once: the second insert meets the
        // unique key and updates the row the first one made.
        db("INSERT INTO mail_bounces SET address = '" . e($key) . "', " . implode(', ', $set) . "
            ON DUPLICATE KEY UPDATE " . implode(', ', $set));
    }

    if ((string) $kind === 'complaint') {
        pg_mail_bounce_withdraw_consent($key);
    }

    if (($next['suppressed'] === 1) && !pg_mail_bounce_blocks($row)) {
        log_activity(pg_log_lang(array(
            'string' => 'E-mail to {var:1} is stopped: {var:2}',
            'vars'   => array($key, $next['kind']),
        )), 'UNKNOWN');
    }

    if (function_exists('pg_announce') && in_array((string) $kind, array('hard', 'soft', 'complaint'), true)) {
        pg_announce(((string) $kind === 'complaint') ? 'mail.complained' : 'mail.bounced', array(
            'address'     => $key,
            'kind'        => (string) $kind,
            'source'      => $columns['source'],
            'suppressed'  => (bool) $next['suppressed'],
            'message_id'  => $columns['message_id'],
            'campaign_id' => $columns['campaign_id'],
        ));
    }

    return (bool) $next['suppressed'];
}

/**
 * A complaint is an unsubscribe: opt_in 0 on every contact with the address
 * and one refused e-mail marketing row in the consent log for each contact
 * that was opted in.
 *
 * @param string $key  a key from pg_mail_bounce_address()
 */
function pg_mail_bounce_withdraw_consent($key)
{
    $contacts = db_items(
        "SELECT id
        FROM contacts
        WHERE
            (email_address = '" . e($key) . "')
            AND (opt_in = '1')");

    if (!$contacts) {
        return;
    }

    db(
        "UPDATE contacts
        SET opt_in = '0'
        WHERE
            (email_address = '" . e($key) . "')
            AND (opt_in = '1')");

    if (!function_exists('pg_consent_log_ready') || !pg_consent_log_ready()) {
        return;
    }

    foreach ($contacts as $contact) {
        pg_consent_log_record(array(
            'channel'    => 'email',
            'purpose'    => 'marketing',
            'status'     => 'refused',
            'source'     => 'complaint',
            'email'      => $key,
            'contact_id' => (int) $contact['id'],
            'ip'         => '',
            'user_agent' => '',
        ));
    }
}

/**
 * Lift the block on an address: its owner changed it or resubscribed.
 * Nothing happens when there is no record or it does not block.
 *
 * @param string $address
 * @return bool  whether a block was lifted
 */
function pg_mail_bounce_clear($address)
{
    $row = pg_mail_bounce_row($address);

    if (!pg_mail_bounce_blocks($row)) {
        return false;
    }

    $set = array();

    foreach (pg_mail_bounce_cleared(time()) as $column => $value) {
        $set[] = $column . " = '" . e((string) $value) . "'";
    }

    db("UPDATE mail_bounces SET " . implode(', ', $set) . " WHERE id = '" . (int) $row['id'] . "'");

    log_activity(pg_log_lang(array(
        'string' => 'E-mail to {var:1} is allowed again.',
        'vars'   => array((string) $row['address']),
    )), 'UNKNOWN');

    return true;
}

/**
 * The reference a return path carries: what the message was, and its id.
 * Pure. q = a mail_outbox row, c = a campaign; anything else is m, no id.
 *
 * @param string $type  queue, campaign, mail
 * @param int    $id
 * @return string  q<base36>, c<base36> or m
 */
function pg_mail_bounce_ref($type, $id)
{
    $id = (int) $id;

    if (($id > 0) && ($type === 'queue')) {
        return 'q' . base_convert((string) $id, 10, 36);
    }

    if (($id > 0) && ($type === 'campaign')) {
        return 'c' . base_convert((string) $id, 10, 36);
    }

    return 'm';
}

/**
 * The parts of a reference. Pure.
 *
 * @param string $ref
 * @return array|null type (queue, campaign, mail), id
 */
function pg_mail_bounce_ref_parts($ref)
{
    if (preg_match('/^(?:([qc])([0-9a-z]{1,12})|m)$/', (string) $ref, $match) !== 1) {
        return null;
    }

    if (!isset($match[1]) || ($match[1] === '')) {
        return array('type' => 'mail', 'id' => 0);
    }

    return array('type' => ($match[1] === 'q') ? 'queue' : 'campaign', 'id' => (int) base_convert($match[2], 36, 10));
}

/**
 * The signed token of one message to one address: the reference, a dot and
 * the first 16 hex characters of HMAC-SHA256(key, reference and address).
 * No table: the bounce that brings it back is checked against the address
 * it reports. '' without a key or a keyable address. Pure.
 *
 * @param string $ref      pg_mail_bounce_ref()
 * @param string $address  the one recipient
 * @param string $key      the site's signing key
 * @return string
 */
function pg_mail_bounce_token($ref, $address, $key)
{
    $address = pg_mail_bounce_address($address);
    $key = (string) $key;

    if (($address === '') || ($key === '') || (pg_mail_bounce_ref_parts($ref) === null)) {
        return '';
    }

    return $ref . '.' . substr(hash_hmac('sha256', $ref . "\n" . $address, $key), 0, 16);
}

/**
 * Whether a token was signed for this address. Pure.
 *
 * @param string $token
 * @param string $address
 * @param string $key
 * @return bool
 */
function pg_mail_bounce_token_valid($token, $address, $key)
{
    $token = strtolower((string) $token);
    $dot = strpos($token, '.');

    if ($dot === false) {
        return false;
    }

    $expected = pg_mail_bounce_token(substr($token, 0, $dot), $address, $key);

    return ($expected !== '') && hash_equals($expected, $token);
}

/**
 * The tokens written in a text after a "+" of an address. Pure.
 *
 * @param string $text
 * @return string[]  each once, lower-cased
 */
function pg_mail_bounce_tokens_in($text)
{
    preg_match_all('/\+((?:[qc][0-9a-z]{1,12}|m)\.[0-9a-f]{16})@/i', (string) $text, $matches);

    return array_values(array_unique(array_map('strtolower', $matches[1])));
}

/**
 * The return path of a message: the bounce mailbox's address with the token
 * after a "+" (sub-addressing, so the server delivers it to that mailbox).
 * '' when either is missing or the local part would pass 64 characters.
 * Pure.
 *
 * @param string $mailbox  the address of the mailbox that reads bounces
 * @param string $token
 * @return string
 */
function pg_mail_bounce_return_path($mailbox, $token)
{
    $mailbox = strtolower(trim((string) $mailbox));
    $at = strrpos($mailbox, '@');

    if (($token === '') || ($at === false) || ($at === 0)) {
        return '';
    }

    $local = substr($mailbox, 0, $at) . '+' . $token;

    return (strlen($local) > 64) ? '' : $local . substr($mailbox, $at);
}

/**
 * The envelope sender with VERP: the return path replaces the sender only
 * when both are on the same domain, so the SMTP account that may send as
 * the sender may send as the return path too, and SPF checks the same
 * domain. Pure.
 *
 * @param string $sender       pg_mail_envelope_sender()'s answer
 * @param string $return_path  pg_mail_bounce_return_path()
 * @return string
 */
function pg_mail_bounce_envelope($sender, $return_path)
{
    $sender = (string) $sender;
    $return_path = (string) $return_path;

    if (($return_path === '') || (strpos($sender, '@') === false)) {
        return $sender;
    }

    $sender_domain = strtolower(substr(strrchr($sender, '@'), 1));
    $return_domain = strtolower(substr(strrchr($return_path, '@'), 1));

    return ($sender_domain === $return_domain) ? $return_path : $sender;
}

/**
 * Which address a bounce report is about: the failed recipient it names,
 * else the first candidate, when one of its tokens was signed for it. A
 * report with no token, or none that matches, is not the site's to record:
 * anyone can send a mailbox a report. Pure.
 *
 * @param array  $dsn  pg_mail_parse_dsn()
 * @param string $key
 * @return array|null address, token, ref (pg_mail_bounce_ref_parts())
 */
function pg_mail_bounce_dsn_match($dsn, $key)
{
    if (!is_array($dsn) || empty($dsn['tokens'])) {
        return null;
    }

    $candidates = (array) ($dsn['candidates'] ?? array());

    // A report that names its failed recipient is about that address only.
    // A bcc copy leaves with the return path signed for the message's "to",
    // and its report carries the returned headers (To: that recipient)
    // among the candidates: matching any candidate would record the bcc
    // copy's bounce or complaint against the "to", who got the message.
    $recipient = pg_mail_bounce_address((string) ($dsn['recipient'] ?? ''));

    if ($recipient !== '') {
        $candidates = array($recipient);
    }

    foreach ($candidates as $address) {
        foreach ((array) $dsn['tokens'] as $token) {
            if (pg_mail_bounce_token_valid($token, $address, $key)) {
                return array(
                    'address' => pg_mail_bounce_address($address),
                    'token'   => (string) $token,
                    'ref'     => pg_mail_bounce_ref_parts(substr((string) $token, 0, strpos((string) $token, '.'))),
                );
            }
        }
    }

    return null;
}

/**
 * The site's signing key for return-path tokens.
 *
 * @return string
 */
function pg_mail_bounce_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}

/**
 * The address of the Workspace mailbox that reads bounces: the first box
 * that is on and has "reads bounces" ticked (settings.reads_bounces), or ''.
 * Read once per request.
 *
 * @return string
 */
function pg_mail_bounce_mailbox()
{
    static $address = null;

    if ($address !== null) {
        return $address;
    }

    $address = '';

    if (!pg_schema_has('ws_mail_boxes', 'settings') || !pg_mail_bounces_ready()) {
        return $address;
    }

    foreach ((array) db_items("SELECT address, settings FROM ws_mail_boxes WHERE enabled = 1 ORDER BY id") as $box) {
        $settings = json_decode((string) $box['settings'], true);

        if (is_array($settings) && !empty($settings['reads_bounces']) && filter_var((string) $box['address'], FILTER_VALIDATE_EMAIL)) {
            $address = strtolower((string) $box['address']);
            break;
        }
    }

    return $address;
}

/**
 * The return path for one message, or '' to keep the envelope as it is:
 * VERP needs a bounce mailbox, a key and exactly one recipient.
 *
 * @param string $ref  pg_mail_bounce_ref()
 * @param mixed  $to   email()'s to
 * @return string
 */
function pg_mail_bounce_return_path_for($ref, $to)
{
    $to = (array) $to;

    if (count($to) !== 1) {
        return '';
    }

    $mailbox = pg_mail_bounce_mailbox();

    if ($mailbox === '') {
        return '';
    }

    return pg_mail_bounce_return_path($mailbox, pg_mail_bounce_token($ref, reset($to), pg_mail_bounce_key()));
}

/**
 * Record what a bounce report says, when one of its tokens signs the
 * address it is about.
 *
 * @param array|null $dsn  pg_mail_parse_dsn()
 * @return bool  whether a bounce was recorded
 */
function pg_mail_bounce_record_dsn($dsn)
{
    $match = pg_mail_bounce_dsn_match($dsn, pg_mail_bounce_key());

    if ($match === null) {
        return false;
    }

    pg_mail_bounce_record($match['address'], $dsn['kind'], 'dsn', array(
        'message_id'  => (string) ($dsn['original_id'] ?? ''),
        'campaign_id' => ($match['ref']['type'] === 'campaign') ? $match['ref']['id'] : 0,
    ));

    return true;
}
