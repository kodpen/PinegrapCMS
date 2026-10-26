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
 * channel and purpose (pg_consent_log_current()), and a screen that asks again
 * adds a row only for an answer that differs from it (pg_consent_log_answers()).
 * Writing a row never stops the action it records: a row that cannot be
 * written is left out.
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
        'form',         // a custom form: a double opt-in group or a field bound to the e-mail opt-in
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
 * @param array $person  any of email, person_token, user_id, contact_id, and
 *                       previous_email or previous_token: an address the
 *                       person had before this request, whose rows are
 *                       theirs as well
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

    $previous = (isset($person['previous_token']) && pg_privacy_is_person_token($person['previous_token']))
        ? $person['previous_token']
        : pg_privacy_person_token(isset($person['previous_email']) ? $person['previous_email'] : '');

    if (($previous !== '') && ($previous !== $token)) {
        $conditions[] = "person_token = '" . $previous . "'";
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

/**
 * The status a person's answer is recorded as, or '' when it repeats the last
 * recorded one. Pure. Nothing recorded yet, or only a pending request (a
 * confirmation mail not answered), counts as no answer: any answer is new.
 *
 * @param array|null $last    the newest row for that channel and purpose (an
 *                            entry of pg_consent_log_current()), or null
 * @param bool       $answer  true = yes
 * @return string  'granted', 'refused' or ''
 */
function pg_consent_log_change($last, $answer)
{
    $status = $answer ? 'granted' : 'refused';

    if (is_array($last) && isset($last['status']) && ((string) $last['status'] === $status)) {
        return '';
    }

    return $status;
}

/**
 * The rows a set of answers needs against the person's current answers: one
 * per answer that differs from the last recorded one. Pure.
 *
 * @param array $current  pg_consent_log_current() of the person
 * @param array $answers  "channel:purpose" => bool
 * @return array[]  list of array('channel' => .., 'purpose' => .., 'status' => ..)
 *                  in the order of $answers; a key that does not split into
 *                  a valid channel and purpose (pg_consent_log_purpose_valid())
 *                  is skipped
 */
function pg_consent_log_changes(array $current, array $answers)
{
    $changes = array();

    foreach ($answers as $key => $answer) {
        $key = (string) $key;
        $parts = explode(':', $key, 2);

        // The purpose keeps its own colon: "email:group:12" is email, group:12.
        if ((count($parts) !== 2) || !pg_consent_log_purpose_valid($parts[0], $parts[1])) {
            continue;
        }

        $status = pg_consent_log_change(isset($current[$key]) ? $current[$key] : null, $answer);

        if ($status !== '') {
            $changes[] = array('channel' => $parts[0], 'purpose' => $parts[1], 'status' => $status);
        }
    }

    return $changes;
}

/**
 * Record the answers a person gave on one screen. The person's rows are read
 * once and only the answers that differ from the last recorded ones are
 * written, so a form sent again, a second one-click unsubscribe or a save that
 * changed only the address adds nothing. The baseline is the log, not the
 * opt-in flags of the contact. Nothing is written before the upgrade, or for
 * a person with no identifier (the last answer could not be read).
 *
 * ip and user_agent are this request's (pg_consent_log_record()): the person
 * answers in their own request.
 *
 * @param array  $person   any of email (the address after the save),
 *                         person_token, user_id, contact_id, and
 *                         previous_email: the address before the save, whose
 *                         rows are read with the person's but never written
 * @param array  $answers  "channel:purpose" => bool, true = yes
 * @param string $source   a pg_consent_log_sources() value
 * @return int  rows written
 */
function pg_consent_log_answers(array $person, array $answers, $source)
{
    // Only the identifiers: the event's own fields come from the answer and the request.
    $person = array_intersect_key($person, array_flip(array('email', 'person_token', 'user_id', 'contact_id', 'previous_email')));

    // A row written keeps the person as they are after the save.
    $stored = $person;
    unset($stored['previous_email']);

    if (!$answers || (pg_consent_log_person_where($stored) === '') || !pg_consent_log_ready()) {
        return 0;
    }

    $changes = pg_consent_log_changes(pg_consent_log_current(pg_consent_log_rows($person)), $answers);
    $written = 0;

    foreach ($changes as $change) {
        if (pg_consent_log_record($change + array('source' => (string) $source) + $stored) > 0) {
            $written++;
        }
    }

    return $written;
}

/**
 * The e-mail marketing answer a checkout records when it writes the box's
 * value to the contact, or array() when that value is not the buyer's own
 * answer. Pure. The system screens and the shipped layouts post
 * opt_in_displayed 'true' when they draw the box and 'false' when the
 * contact was opted in already; a designed express order posts 'true' only
 * while its tree draws the box, and nothing without it. Without 'true' the
 * contact still gets a value, but not one the buyer chose. A custom billing
 * information or express order layout edited to leave the box out still
 * posts 'true', because the server prints the marker from the contact, not
 * from the layout: its post looks like an unticked box, so its 0 is
 * recorded as a refusal. While staff are ghosting, the box is answered by
 * the staff member, not by the customer.
 *
 * @param mixed $opt_in_displayed  the posted opt_in_displayed
 * @param mixed $ghost             $_SESSION['software']['ghost']
 * @param mixed $opt_in            the opt_in written to the contact, 0 or 1
 * @return array  "channel:purpose" => bool, for pg_consent_log_answers()
 */
function pg_consent_checkout_answers($opt_in_displayed, $ghost, $opt_in)
{
    if (($opt_in_displayed !== 'true') || $ghost) {
        return array();
    }

    return array('email:marketing' => ((int) $opt_in === 1));
}

/**
 * The person a checkout answer is recorded for. Pure. The billing address
 * only when validate_email_address() accepts it: an express order writes the
 * contact before its billing errors stop the order, and an address the
 * checkout refuses must not become the row's person token. The account and
 * the contact still name the person then.
 *
 * @param mixed $email       the billing e-mail address written to the contact
 * @param mixed $user_id     the buyer's account, empty for a guest
 * @param mixed $contact_id  the contact just updated
 * @return array  for pg_consent_log_answers()
 */
function pg_consent_checkout_person($email, $user_id, $contact_id)
{
    $person = array();

    if (is_string($email) && validate_email_address($email)) {
        $person['email'] = $email;
    }

    return $person + array('user_id' => (int) $user_id, 'contact_id' => (int) $contact_id);
}

/**
 * Record the e-mail marketing answer a checkout has just written to the
 * contact (billing_information.php, express_order.php, submit_order()). The
 * handlers call it right after the UPDATE contacts that carries opt_in, in
 * the branch that runs only when the contact is updated, so the log holds
 * what the contact holds: a submit refused or never paid afterwards keeps its
 * row, and a signed-in buyer whose card is not updated adds none. A repeated
 * answer writes nothing (pg_consent_log_answers()).
 *
 * @param mixed $email             the billing e-mail address written to the contact
 * @param mixed $user_id           the buyer's account, empty for a guest
 * @param mixed $contact_id        the contact just updated
 * @param mixed $opt_in_displayed  the posted opt_in_displayed
 * @param mixed $ghost             $_SESSION['software']['ghost']
 * @param mixed $opt_in            the opt_in written to the contact, 0 or 1
 * @return int  rows written
 */
function pg_consent_log_checkout($email, $user_id, $contact_id, $opt_in_displayed, $ghost, $opt_in)
{
    $answers = pg_consent_checkout_answers($opt_in_displayed, $ghost, $opt_in);

    if (!$answers) {
        return 0;
    }

    return pg_consent_log_answers(pg_consent_checkout_person($email, $user_id, $contact_id), $answers, 'checkout');
}


/**
 * What erasing a person does to each table that holds their data, in the
 * order it is done: the records that stay first, the person's own account
 * and contact last, so an erasure stopped half-way still finds the person
 * when it is run again.
 *
 * find:      how the person's rows are found, column => the list of the
 *            erasure's scope it is matched against (pg_privacy_erase_scope());
 *            the conditions are joined with OR.
 * action:    delete removes the rows; anonymise keeps them (an order, an ERP
 *            invoice or waybill kept for the legal retention period, a
 *            comment kept on its page, a consent kept as proof) and replaces
 *            the columns in fields.
 * fields:    column => new value. '{name}' is "Deleted customer" and
 *            '{token}' the person token of the address the column holds
 *            (pg_privacy_erase_token()); any other value is written as it
 *            is. An empty comment name is shown as "Anonymous".
 *
 * A column not listed is kept: amounts, dates, statuses, products, payment
 * and carrier references, the country (tax reports read it).
 *
 * @return array  table => find, action, fields
 */
function pg_privacy_erase_tables()
{
    $user = array('user_id' => 'user_ids');

    return array(
        'orders' => array('find' => array('id' => 'order_ids'), 'action' => 'anonymise', 'fields' => array(
            'billing_salutation'       => '',
            'billing_first_name'       => '{name}',
            'billing_last_name'        => '',
            'billing_company'          => '',
            'billing_address_1'        => '',
            'billing_address_2'        => '',
            'billing_city'             => '',
            'billing_state'            => '',
            'billing_zip_code'         => '',
            'billing_phone_number'     => '',
            'billing_fax_number'       => '',
            'billing_email_address'    => '{token}',
            'cardholder'               => '',
            'card_number'              => '',
            'expiration_month'         => '',
            'expiration_year'          => '',
            'card_verification_number' => '',
            'custom_field_1'           => '',
            'custom_field_2'           => '',
            'http_referer'             => '',
            'ip_address'               => 0,
            'user_id'                  => 0,
            'contact_id'               => 0,
        )),
        'ship_tos' => array('find' => array('order_id' => 'order_ids'), 'action' => 'anonymise', 'fields' => array(
            'ship_to_name' => '',
            'salutation'   => '',
            'first_name'   => '{name}',
            'last_name'    => '',
            'company'      => '',
            'address_1'    => '',
            'address_2'    => '',
            'city'         => '',
            'state'        => '',
            'zip_code'     => '',
            'phone_number' => '',
        )),
        'order_documents' => array('find' => array('order_id' => 'order_ids'), 'action' => 'anonymise', 'fields' => array(
            'ip'         => '',
            'user_agent' => '',
        )),
        'order_returns' => array('find' => array('order_id' => 'order_ids'), 'action' => 'anonymise', 'fields' => array(
            'customer_note' => '',
            'requested_by'  => 0,
        )),
        // A card the person bought loses the sender's name and message, a
        // card sent to them the address (_pg_privacy_erase_apply_gift_cards()).
        'gift_cards' => array('find' => array('order_id' => 'order_ids', 'recipient_email_address' => 'emails'), 'action' => 'anonymise', 'fields' => array(
            'from_name'               => '',
            'message'                 => '',
            'recipient_email_address' => '',
        )),
        'erp_accounts' => array('find' => array('id' => 'account_ids'), 'action' => 'anonymise', 'fields' => array(
            'title'            => '{name}',
            'tax_number'       => '',
            'tax_office'       => '',
            'email'            => '{token}',
            'invoice_email'    => '',
            'phone'            => '',
            'address'          => '',
            'district'         => '',
            'city'             => '',
            'state'            => '',
            'postcode'         => '',
            'einvoice_alias'   => '',
            'einvoice_aliases' => '',
            'notes'            => '',
            'contact_id'       => 0,
        )),
        'erp_invoices' => array('find' => array('account_id' => 'account_ids'), 'action' => 'anonymise', 'fields' => array(
            'account_title'      => '{name}',
            'account_tax_number' => '',
            'account_tax_office' => '',
            'account_address'    => '',
            'account_district'   => '',
            'account_city'       => '',
            'account_state'      => '',
            'account_postcode'   => '',
            'account_email'      => '{token}',
        )),
        'erp_quotes' => array('find' => array('account_id' => 'account_ids'), 'action' => 'anonymise', 'fields' => array(
            'form_data' => '',
        )),
        'erp_waybills' => array('find' => array('account_id' => 'account_ids'), 'action' => 'anonymise', 'fields' => array(
            'ship_to_title'    => '{name}',
            'ship_to_address'  => '',
            'ship_to_district' => '',
            'ship_to_city'     => '',
            'ship_to_state'    => '',
        )),
        'comments' => array('find' => array('created_user_id' => 'user_ids'), 'action' => 'anonymise', 'fields' => array(
            'name'            => '',
            'created_user_id' => 0,
        )),
        'consent_log' => array('find' => array('person_token' => 'tokens', 'user_id' => 'user_ids', 'contact_id' => 'contact_ids'), 'action' => 'anonymise', 'fields' => array(
            'user_id'    => 0,
            'contact_id' => 0,
            'ip'         => '',
            'user_agent' => '',
        )),
        'ws_approval_guests' => array('find' => array('email' => 'emails'), 'action' => 'anonymise', 'fields' => array(
            'name'          => '',
            'email'         => '{token}',
            'decided_ip'    => '',
            'decided_agent' => '',
        )),
        // The trail's lines about the person: the field list's columns are
        // replaced inside before_json and after_json
        // (_pg_privacy_erase_apply_audit_log()); who did what and when stays.
        'audit_log' => array('find' => array('id' => 'audit_ids'), 'action' => 'anonymise', 'fields' => array(
            'label' => '',
        )),
        'files'                        => array('find' => array('id' => 'file_ids'), 'action' => 'delete', 'fields' => array()),
        'form_data'                    => array('find' => array('form_id' => 'form_ids', 'order_id' => 'order_ids'), 'action' => 'delete', 'fields' => array()),
        'form_signatures'              => array('find' => array('form_id' => 'form_ids', 'user_id' => 'user_ids'), 'action' => 'delete', 'fields' => array()),
        'forms'                        => array('find' => array('id' => 'form_ids'), 'action' => 'delete', 'fields' => array()),
        'watchers'                     => array('find' => array('user_id' => 'user_ids', 'email_address' => 'emails'), 'action' => 'delete', 'fields' => array()),
        'wishlist_items'               => array('find' => $user, 'action' => 'delete', 'fields' => array()),
        'stock_notify'                 => array('find' => array('user_id' => 'user_ids', 'email_address' => 'emails'), 'action' => 'delete', 'fields' => array()),
        'email_recipients'             => array('find' => array('contact_id' => 'contact_ids', 'email_address' => 'emails'), 'action' => 'delete', 'fields' => array()),
        'mail_outbox'                  => array('find' => array('recipient' => 'emails'), 'action' => 'delete', 'fields' => array()),
        'sms_outbox'                   => array('find' => array('recipient' => 'phones'), 'action' => 'delete', 'fields' => array()),
        'chat_messages'                => array('find' => array('conversation_id' => 'conversation_ids'), 'action' => 'delete', 'fields' => array()),
        'chat_conversations'           => array('find' => array('id' => 'conversation_ids'), 'action' => 'delete', 'fields' => array()),
        'address_book'                 => array('find' => array('user' => 'user_ids'), 'action' => 'delete', 'fields' => array()),
        'push_subscriptions'           => array('find' => $user, 'action' => 'delete', 'fields' => array()),
        'user_passkeys'                => array('find' => $user, 'action' => 'delete', 'fields' => array()),
        'user_identities'              => array('find' => $user, 'action' => 'delete', 'fields' => array()),
        'user_mfa_recovery'            => array('find' => $user, 'action' => 'delete', 'fields' => array()),
        'user_mfa'                     => array('find' => $user, 'action' => 'delete', 'fields' => array()),
        'auth_tokens'                  => array('find' => $user, 'action' => 'delete', 'fields' => array()),
        'opt_in'                       => array('find' => array('contact_id' => 'contact_ids'), 'action' => 'delete', 'fields' => array()),
        'contacts_contact_groups_xref' => array('find' => array('contact_id' => 'contact_ids'), 'action' => 'delete', 'fields' => array()),
        'contacts'                     => array('find' => array('id' => 'contact_ids'), 'action' => 'delete', 'fields' => array()),
        'user'                         => array('find' => $user, 'action' => 'delete', 'fields' => array()),
    );
}

/**
 * The name each table of pg_privacy_erase_tables() is shown under.
 *
 * @return array  table => label
 */
function pg_privacy_erase_labels()
{
    return array(
        'orders'                       => lang('Orders'),
        'ship_tos'                     => lang('Shipping addresses'),
        'order_documents'              => lang('Accepted documents'),
        'order_returns'                => lang('Returns'),
        'gift_cards'                   => lang('Gift cards'),
        'erp_accounts'                 => lang('Current accounts'),
        'erp_invoices'                 => lang('Invoices'),
        'erp_quotes'                   => lang('Quotes'),
        'erp_waybills'                 => lang('Waybills'),
        'comments'                     => lang('Comments and reviews'),
        'consent_log'                  => lang('Consents'),
        'ws_approval_guests'           => lang('Workspace approvals'),
        'audit_log'                    => lang('Audit trail'),
        'files'                        => lang('Uploaded files'),
        'form_data'                    => lang('Form answers'),
        'form_signatures'              => lang('Signatures'),
        'forms'                        => lang('Form submissions'),
        'watchers'                     => lang('Watched items'),
        'wishlist_items'               => lang('Favourites'),
        'stock_notify'                 => lang('Back-in-stock requests'),
        'email_recipients'             => lang('E-mail campaigns'),
        'mail_outbox'                  => lang('E-mail messages'),
        'sms_outbox'                   => lang('Text messages'),
        'chat_messages'                => lang('Chat messages'),
        'chat_conversations'           => lang('Site chat'),
        'address_book'                 => lang('Address book'),
        'push_subscriptions'           => lang('Device notifications'),
        'user_passkeys'                => lang('Passkeys'),
        'user_identities'              => lang('Connected sign-in accounts'),
        'user_mfa_recovery'            => lang('Recovery codes'),
        'user_mfa'                     => lang('Two-step verification'),
        'auth_tokens'                  => lang('Sign-in sessions'),
        'opt_in'                       => lang('Opt-in answers'),
        'contacts_contact_groups_xref' => lang('Contact groups'),
        'contacts'                     => lang('Contact'),
        'user'                         => lang('User account'),
    );
}

/**
 * The condition that finds a person's rows of one table: each find column
 * matched against its list of the scope, joined with OR. Pure.
 *
 * @param array $find   column => scope key (pg_privacy_erase_tables())
 * @param array $scope  see pg_privacy_erase_scope()
 * @return string  '' when the scope has nothing to match, so no statement is
 *                 run: a condition left out would match every row
 */
function pg_privacy_erase_where(array $find, array $scope)
{
    $conditions = array();

    foreach ($find as $column => $key) {
        $conditions[] = _pg_privacy_export_in($column, isset($scope[$key]) ? (array) $scope[$key] : array());
    }

    return _pg_privacy_export_any($conditions);
}

/**
 * What an e-mail column of a kept row becomes: the person token of the
 * address it holds. A token already there stays, so an erasure run twice
 * does not lose it; anything that is not an address becomes ''. Pure.
 *
 * @param mixed       $value
 * @param string|null $key  see pg_privacy_person_token()
 * @return string
 */
function pg_privacy_erase_token($value, $key = null)
{
    if (pg_privacy_is_person_token($value)) {
        return $value;
    }

    return pg_privacy_person_token($value, $key);
}

/**
 * The SET list of an anonymising UPDATE. Pure.
 *
 * @param array  $fields  column => value (pg_privacy_erase_tables())
 * @param string $name    what '{name}' becomes
 * @param string $token   what '{token}' becomes
 * @return string
 */
function pg_privacy_erase_set(array $fields, $name, $token)
{
    $set = array();

    foreach ($fields as $column => $value) {
        if ($value === '{name}') {
            $value = (string) $name;
        } elseif ($value === '{token}') {
            $value = (string) $token;
        }

        $set[] = '`' . $column . '` = ' . (is_int($value) ? (string) $value : "'" . e((string) $value) . "'");
    }

    return implode(', ', $set);
}

/**
 * Everything an erasure works on: the person as pg_privacy_export_find()
 * puts them together, their person tokens and mobile numbers, and the ids
 * of the records tied to them. ERP accounts are the person's only when
 * marked as a person (is_person): a company account that names their
 * contact or address is the company's and stays.
 *
 * @param array $found  see pg_privacy_export_find()
 * @return array  user_ids, contact_ids, emails, tokens, phones, order_ids,
 *                form_ids, account_ids, conversation_ids, file_ids,
 *                return_ids, audit_ids
 */
function pg_privacy_erase_scope(array $found)
{
    $person = $found['person'];

    $scope = array(
        'user_ids'         => $person['user_ids'],
        'contact_ids'      => $person['contact_ids'],
        'emails'           => $person['emails'],
        'tokens'           => array_values(array_unique(array_filter(array_map('pg_privacy_person_token', $person['emails']), 'strlen'))),
        'phones'           => array(),
        'order_ids'        => array(),
        'form_ids'         => array(),
        'account_ids'      => array(),
        'conversation_ids' => array(),
        'file_ids'         => array(),
        'return_ids'       => array(),
        'audit_ids'        => array(),
    );

    $where = pg_privacy_erase_where(array('user_id' => 'user_ids', 'contact_id' => 'contact_ids', 'billing_email_address' => 'emails'), $scope);

    if ($where !== '') {
        $scope['order_ids'] = _pg_privacy_export_ids(db_values("SELECT id FROM orders WHERE " . $where));
    }

    // Form submissions: the person's own and those naming their address in a
    // field, as the export finds them.
    $named = $scope['emails'] ? db_values("SELECT DISTINCT form_id FROM form_data WHERE (form_id > 0) AND " . _pg_privacy_export_in('data', $scope['emails'])) : array();
    $where = _pg_privacy_export_any(array(pg_privacy_erase_where(array('user_id' => 'user_ids', 'contact_id' => 'contact_ids'), $scope), _pg_privacy_export_in('id', _pg_privacy_export_ids($named))));

    if ($where !== '') {
        $scope['form_ids'] = _pg_privacy_export_ids(db_values("SELECT id FROM forms WHERE " . $where));
    }

    if (pg_schema_has('erp_accounts', 'is_person')) {
        $linked = _pg_privacy_export_column(array_values($found['contacts']), 'erp_account_id');

        if ($scope['order_ids'] && pg_schema_has('orders', 'erp_account_id')) {
            $linked = array_merge($linked, db_values("SELECT DISTINCT erp_account_id FROM orders WHERE " . _pg_privacy_export_in('id', $scope['order_ids'])));
        }

        $where = _pg_privacy_export_any(array(_pg_privacy_export_in('id', _pg_privacy_export_ids($linked)), pg_privacy_erase_where(array('contact_id' => 'contact_ids', 'email' => 'emails'), $scope)));

        if ($where !== '') {
            $scope['account_ids'] = _pg_privacy_export_ids(db_values("SELECT id FROM erp_accounts WHERE (is_person = 1) AND " . $where));
        }
    }

    if (pg_schema_has('chat_conversations', 'party_email')) {
        $where = _pg_privacy_export_any(array(
            _pg_privacy_export_in('party_email', $scope['emails']),
            _pg_privacy_export_in('initiator_user_id', $scope['user_ids']),
        ));

        if ($where !== '') {
            $scope['conversation_ids'] = _pg_privacy_export_ids(db_values("SELECT id FROM chat_conversations WHERE (channel = 'site') AND " . $where));
        }
    }

    // Uploads: files sent with the person's forms and product forms, their
    // signature images and the attachments of their chats.
    $files = array();
    $where = pg_privacy_erase_where(array('form_id' => 'form_ids', 'order_id' => 'order_ids'), $scope);

    if ($where !== '') {
        $files = db_values("SELECT file_id FROM form_data WHERE (file_id > 0) AND " . $where);
    }

    $where = pg_privacy_erase_where(array('form_id' => 'form_ids', 'user_id' => 'user_ids'), $scope);

    if (($where !== '') && pg_schema_has('form_signatures', 'file_id')) {
        $files = array_merge($files, db_values("SELECT file_id FROM form_signatures WHERE (file_id > 0) AND " . $where));
    }

    if ($scope['conversation_ids'] && pg_schema_has('chat_messages', 'attachment_file_id')) {
        $files = array_merge($files, db_values("SELECT attachment_file_id FROM chat_messages WHERE (attachment_file_id > 0) AND " . _pg_privacy_export_in('conversation_id', $scope['conversation_ids'])));
    }

    $scope['file_ids'] = _pg_privacy_export_ids($files);

    // Mobile numbers, for the text messages sent to them.
    $rows = array_values($found['contacts']);
    $where = pg_privacy_erase_where(array('user' => 'user_ids'), $scope);

    if ($where !== '') {
        $rows = array_merge($rows, db_items("SELECT phone_number FROM address_book WHERE " . $where));
    }

    if ($scope['order_ids']) {
        $rows = array_merge(
            $rows,
            db_items("SELECT billing_phone_number FROM orders WHERE " . _pg_privacy_export_in('id', $scope['order_ids'])),
            db_items("SELECT phone_number FROM ship_tos WHERE " . _pg_privacy_export_in('order_id', $scope['order_ids']))
        );
    }

    $scope['phones'] = pg_privacy_export_phones($rows);

    if ($scope['order_ids'] && pg_schema_has('order_returns', 'order_id')) {
        $scope['return_ids'] = _pg_privacy_export_ids(db_values("SELECT id FROM order_returns WHERE " . _pg_privacy_export_in('order_id', $scope['order_ids'])));
    }

    // The trail's lines about the person's account, orders and returns, and
    // the export lines labelled with their address.
    if (pg_audit_ready()) {
        $conditions = array();

        foreach (array('user' => 'user_ids', 'order' => 'order_ids', 'order_return' => 'return_ids', 'person' => 'emails') as $type => $key) {
            if ($scope[$key]) {
                $conditions[] = "((entity_type = '" . $type . "') AND " . _pg_privacy_export_in(($type === 'person') ? 'label' : 'entity_id', $scope[$key]) . ")";
            }
        }

        if ($conditions) {
            $scope['audit_ids'] = _pg_privacy_export_ids(db_values("SELECT id FROM audit_log WHERE " . _pg_privacy_export_any($conditions)));
        }
    }

    return $scope;
}

/**
 * What is replaced in the audit trail's lines about the person, by record
 * type: the field list's columns inside before_json and after_json. A user
 * line has the person's account (deleted, so it has no field list of its
 * own); a person line (an export) holds only ids, the token and counts. The
 * label is cleared on user and person lines, where it is the user name or
 * the address; an order keeps its number.
 *
 * @return array  entity_type => column => value, as pg_privacy_erase_tables()
 */
function pg_privacy_erase_audit_fields()
{
    $tables = pg_privacy_erase_tables();

    return array(
        'user'         => array(
            'user_username'      => '{name}',
            'user_email'         => '{token}',
            'user_password_hint' => '',
            'user_google_id'     => '',
        ),
        'order'        => $tables['orders']['fields'],
        'order_return' => $tables['order_returns']['fields'],
        'person'       => array(),
    );
}

/**
 * What changes in one audit_log row about the person. Pure.
 *
 * Only the columns that are there and differ are replaced; a value left as
 * the trail compacted it (a long text) is replaced whole. A token already in
 * place stays, so an erasure run twice changes nothing.
 *
 * @param array       $row   entity_type, label, before_json, after_json
 * @param string      $name  what '{name}' becomes
 * @param string|null $key   see pg_privacy_person_token()
 * @return array  column => new value, empty when nothing changes
 */
function pg_privacy_erase_audit_row(array $row, $name, $key = null)
{
    $type = isset($row['entity_type']) ? (string) $row['entity_type'] : '';
    $all = pg_privacy_erase_audit_fields();

    if (!isset($all[$type])) {
        return array();
    }

    $changes = array();

    foreach (array('before_json', 'after_json') as $side) {
        $values = isset($row[$side]) && is_string($row[$side]) ? json_decode($row[$side], true) : null;

        if (!is_array($values)) {
            continue;
        }

        $changed = false;

        foreach ($all[$type] as $column => $value) {
            if (!array_key_exists($column, $values)) {
                continue;
            }

            if ($value === '{name}') {
                $value = (string) $name;
            } elseif ($value === '{token}') {
                $value = pg_privacy_erase_token($values[$column], $key);
            }

            if (is_array($values[$column]) || ((string) $values[$column] !== (string) $value)) {
                $values[$column] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $changes[$side] = json_encode($values, pg_audit_json_flags());
        }
    }

    if (in_array($type, array('user', 'person'), true) && isset($row['label']) && ((string) $row['label'] !== '')) {
        $changes['label'] = '';
    }

    return $changes;
}

/**
 * Count what erasing a person does to each table, and do it when $apply.
 * A table or a find column the site does not have yet is passed over, and
 * only the replaced columns it has are written.
 *
 * @param array $scope  see pg_privacy_erase_scope()
 * @param bool  $apply  false for the dry run
 * @return array  table => action, rows (the rows found), for the tables
 *                with rows only
 */
function pg_privacy_erase_run(array $scope, $apply)
{
    $name = lang('Deleted customer');
    $result = array();

    foreach (pg_privacy_erase_tables() as $table => $spec) {
        if (!pg_schema_has($table)) {
            continue;
        }

        $spec['find'] = array_filter($spec['find'], function ($column) use ($table) {
            return pg_schema_has($table, $column);
        }, ARRAY_FILTER_USE_KEY);

        $spec['fields'] = array_filter($spec['fields'], function ($column) use ($table) {
            return pg_schema_has($table, $column);
        }, ARRAY_FILTER_USE_KEY);

        $where = pg_privacy_erase_where($spec['find'], $scope);

        if (($where === '') || (($spec['action'] === 'anonymise') && !$spec['fields'])) {
            continue;
        }

        $rows = (int) db_value("SELECT COUNT(*) FROM `" . $table . "` WHERE " . $where);

        if ($rows === 0) {
            continue;
        }

        $result[$table] = array('action' => $spec['action'], 'rows' => $rows);

        if ($apply) {
            $hook = '_pg_privacy_erase_apply_' . $table;

            if (function_exists($hook)) {
                $hook($where, $spec['fields'], $scope, $name);
            } else {
                _pg_privacy_erase_write($table, $where, $spec['fields'], $name);
            }
        }
    }

    return $result;
}

/**
 * Erase a person, or count what erasing them would do. Who may erase whom is
 * the export's rule (pg_privacy_export_allowed()); nobody erases their own
 * account. An erasure writes one audit line with the person token and the
 * counts, and no line per row: a row's before side would keep what was
 * just erased.
 *
 * @param array $input     any of email, user_id, contact_id
 * @param array $operator  role, id
 * @param bool  $apply     false for the dry run
 * @return array  status (empty | refused | self | not_found | ok), found,
 *                tables (see pg_privacy_erase_run())
 */
function pg_privacy_erase(array $input, array $operator, $apply = false)
{
    $found = pg_privacy_export_find($input);
    $result = array('status' => 'ok', 'found' => $found, 'tables' => array());

    if (pg_privacy_export_is_empty($found['person'])) {
        $result['status'] = 'empty';
    } elseif (!pg_privacy_export_allowed($operator['role'] ?? 3, $operator['id'] ?? 0, $found['users'])) {
        $result['status'] = 'refused';
    } elseif (in_array((int) ($operator['id'] ?? 0), $found['person']['user_ids'], true)) {
        $result['status'] = 'self';
    }

    if ($result['status'] !== 'ok') {
        return $result;
    }

    $scope = pg_privacy_erase_scope($found);
    $result['tables'] = pg_privacy_erase_run($scope, false);

    if (!$result['tables']) {
        $result['status'] = 'not_found';
    } elseif ($apply) {
        $result['tables'] = pg_privacy_erase_run($scope, true);
        pg_audit('erase', 'person', 0, array(), pg_privacy_erase_audit_entry($scope, $result['tables']), '');
    }

    return $result;
}

/**
 * The after side of an erasure's audit line: the person token (under a name
 * the trail does not mask), the ids, and the rows of each table. No address
 * and no name. Pure.
 *
 * @param array $scope
 * @param array $tables  see pg_privacy_erase_run()
 * @return array
 */
function pg_privacy_erase_audit_entry(array $scope, array $tables)
{
    $entry = array(
        'person'      => $scope['tokens'] ? (string) reset($scope['tokens']) : '',
        'user_ids'    => implode(',', array_map('intval', $scope['user_ids'])),
        'contact_ids' => implode(',', array_map('intval', $scope['contact_ids'])),
        'deleted'     => array(),
        'anonymised'  => array(),
    );

    foreach ($tables as $table => $done) {
        $entry[($done['action'] === 'delete') ? 'deleted' : 'anonymised'][$table] = (int) $done['rows'];
    }

    return $entry;
}

/**
 * Delete the rows found, or replace their columns. A '{token}' column is
 * written one address at a time, each with its own token.
 *
 * @param string $table
 * @param string $where   from pg_privacy_erase_where(); '' writes nothing
 * @param array  $fields  column => value, none for a delete
 * @param string $name    what '{name}' becomes
 */
function _pg_privacy_erase_write($table, $where, array $fields, $name)
{
    if ($where === '') {
        return;
    }

    if (!$fields) {
        db("DELETE FROM `" . $table . "` WHERE " . $where);

        return;
    }

    $column = array_search('{token}', $fields, true);

    if ($column === false) {
        db("UPDATE `" . $table . "` SET " . pg_privacy_erase_set($fields, $name, '') . " WHERE " . $where);

        return;
    }

    foreach (db_values("SELECT DISTINCT `" . $column . "` FROM `" . $table . "` WHERE " . $where) as $value) {
        $same = ($value === null) ? " IS NULL" : " = '" . e($value) . "'";

        db("UPDATE `" . $table . "` SET " . pg_privacy_erase_set($fields, $name, pg_privacy_erase_token($value)) . " WHERE " . $where . " AND (`" . $column . "`" . $same . ")");
    }
}

/**
 * Uploaded files go from the disk as well as from the files table, as the
 * submitted forms and chat screens delete them.
 */
function _pg_privacy_erase_apply_files($where, array $fields, array $scope, $name)
{
    foreach (db_items("SELECT name FROM files WHERE " . $where) as $file) {
        if (defined('FILE_DIRECTORY_PATH') && ((string) $file['name'] !== '')) {
            @unlink(FILE_DIRECTORY_PATH . '/' . basename((string) $file['name']));
        }
    }

    _pg_privacy_erase_write('files', $where, array(), $name);
}

/**
 * A gift card the person bought keeps its recipient, who may still be
 * waiting for it; a card sent to the person keeps the buyer's name and
 * message, which are the buyer's.
 */
function _pg_privacy_erase_apply_gift_cards($where, array $fields, array $scope, $name)
{
    $parts = array(
        array(array('order_id' => 'order_ids'), array('from_name', 'message')),
        array(array('recipient_email_address' => 'emails'), array('recipient_email_address')),
    );

    foreach ($parts as $part) {
        $part_where = pg_privacy_erase_where($part[0], $scope);
        $part_fields = array_intersect_key($fields, array_flip($part[1]));

        if (($part_where !== '') && $part_fields) {
            _pg_privacy_erase_write('gift_cards', $part_where, $part_fields, $name);
        }
    }
}

/**
 * A consent kept as proof is tied to the person by their token alone: a row
 * written with only a user or contact id gets the token of their first
 * address before the ids go.
 */
function _pg_privacy_erase_apply_consent_log($where, array $fields, array $scope, $name)
{
    if ($scope['tokens']) {
        db("UPDATE consent_log SET person_token = '" . e(reset($scope['tokens'])) . "' WHERE (person_token = '') AND " . $where);
    }

    _pg_privacy_erase_write('consent_log', $where, $fields, $name);
}

/**
 * An account goes as delete_users.php deletes one, without the audit line
 * per row: its API devices are revoked, and its rights and memberships go
 * with it.
 */
function _pg_privacy_erase_apply_user($where, array $fields, array $scope, $name)
{
    foreach ($scope['user_ids'] as $user_id) {
        pg_auth_token_revoke_user($user_id);
    }

    _pg_privacy_erase_write('user', $where, array(), $name);

    $links = array(
        'aclfolder'                    => 'aclfolder_user',
        'users_contact_groups_xref'    => 'user_id',
        'users_calendars_xref'         => 'user_id',
        'users_ad_regions_xref'        => 'user_id',
        'users_common_regions_xref'    => 'user_id',
        'users_menus_xref'             => 'user_id',
        'users_messages_xref'          => 'user_id',
        'users_shared_components_xref' => 'user_id',
    );

    foreach ($links as $table => $column) {
        if (pg_schema_has($table, $column)) {
            _pg_privacy_erase_write($table, _pg_privacy_export_in($column, $scope['user_ids']), array(), $name);
        }
    }
}

/**
 * The trail's lines keep who did what and when; what they recorded about the
 * person is replaced, line by line (pg_privacy_erase_audit_row()).
 */
function _pg_privacy_erase_apply_audit_log($where, array $fields, array $scope, $name)
{
    foreach (db_items("SELECT id, entity_type, label, before_json, after_json FROM audit_log WHERE " . $where) as $row) {
        $set = array();

        foreach (pg_privacy_erase_audit_row($row, $name) as $column => $value) {
            $set[] = '`' . $column . '` = ' . (($value === false) ? 'NULL' : "'" . e($value) . "'");
        }

        if ($set) {
            db("UPDATE audit_log SET " . implode(', ', $set) . " WHERE id = '" . (int) $row['id'] . "'");
        }
    }
}
