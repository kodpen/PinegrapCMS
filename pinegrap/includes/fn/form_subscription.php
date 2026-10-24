<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Double opt-in for custom forms that add the sender to a contact group.
 *
 * With custom_form_pages.double_opt_in on (2026.11.0, 11.0.27) a submission
 * keeps the contact but not the form's group: the contact's own address gets a
 * signed link, and only a press of the button on confirm_subscription.php adds
 * the contact to the group. The link carries contact, group and issue time and
 * an HMAC over them and the contact's address under ENCRYPTION_KEY, so nothing
 * is stored for it; a changed address or key makes every open link invalid.
 * consent_log keeps a pending row for each confirmation mail and a granted row
 * for each confirmation, both with purpose "group:<id>".
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
 * How long a confirmation link is accepted, in seconds: seven days.
 *
 * @return int
 */
function pg_fs_ttl()
{
    return 604800;
}

/**
 * The signature of a confirmation link. Its own prefix keeps it apart from any
 * other HMAC made with the same key (the person token, the set password link).
 *
 * @param mixed  $contact_id
 * @param mixed  $group_id
 * @param mixed  $email      the contact's address, normalised here
 * @param mixed  $issued_at  Unix time the link was made
 * @param string $key
 * @return string  64 hex characters, '' when any part is missing
 */
function pg_fs_token($contact_id, $group_id, $email, $issued_at, $key)
{
    $contact_id = (int) $contact_id;
    $group_id = (int) $group_id;
    $issued_at = (int) $issued_at;
    $key = (string) $key;
    $email = pg_privacy_normalize_email($email);

    if (($contact_id <= 0) || ($group_id <= 0) || ($issued_at <= 0) || ($email === '') || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'subscribe-confirm:' . $contact_id . ':' . $group_id . ':' . $issued_at . ':' . $email, $key);
}

/**
 * The query string of a confirmation link, without the leading "?".
 *
 * @param int    $contact_id
 * @param int    $group_id
 * @param int    $issued_at
 * @param string $signature
 * @param string $separator  '&amp;' when the link is written into HTML
 * @return string
 */
function pg_fs_query($contact_id, $group_id, $issued_at, $signature, $separator = '&')
{
    return 'c=' . (int) $contact_id
        . $separator . 'g=' . (int) $group_id
        . $separator . 't=' . (int) $issued_at
        . $separator . 's=' . rawurlencode((string) $signature);
}

/**
 * Reads a confirmation link's values from the request ($_GET or $_POST).
 * Contact, group and time are plain positive whole numbers written as such
 * (no sign, no leading zero, no spaces) that fit this PHP's integer; the
 * signature is 64 lower-case hex characters. Anything else is no link.
 *
 * @param mixed $source
 * @return array|null  c, g, t (int) and s (string)
 */
function pg_fs_parse($source)
{
    if (!is_array($source)) {
        return null;
    }

    $parsed = array();

    foreach (array('c', 'g', 't') as $name) {
        $value = isset($source[$name]) ? $source[$name] : null;

        if (!is_string($value) || (preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1) || ((string) (int) $value !== $value)) {
            return null;
        }

        $parsed[$name] = (int) $value;
    }

    $signature = isset($source['s']) ? $source['s'] : null;

    if (!is_string($signature) || (preg_match('/^[0-9a-f]{64}$/', $signature) !== 1)) {
        return null;
    }

    $parsed['s'] = $signature;

    return $parsed;
}

/**
 * What a confirmation link can do now.
 *
 * invalid: not parsed, no expected signature (no key, the contact or its
 *          address is gone), a signature that does not match, or issued more
 *          than five minutes ahead of this clock;
 * member:  the contact is subscribed already (a second click), see
 *          pg_fs_subscribed();
 * expired: older than pg_fs_ttl();
 * confirm: the button may add the contact to the group.
 *
 * @param array|null $parsed    pg_fs_parse()
 * @param mixed      $expected  pg_fs_token() for the parsed values and the
 *                              contact's current address
 * @param int        $now
 * @param bool       $is_member
 * @return string
 */
function pg_fs_link_state($parsed, $expected, $now, $is_member)
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

    if ($is_member) {
        return 'member';
    }

    if ((int) $now > (int) $parsed['t'] + pg_fs_ttl()) {
        return 'expired';
    }

    return 'confirm';
}

/**
 * Whether a contact counts as subscribed to a group: in it, and not opted out
 * of it. Leaving a subscription group on the e-mail preferences screen keeps
 * the group row and writes opt_in = 0, so such a contact may confirm again;
 * no opt_in row is how every other membership looks.
 *
 * @param bool  $in_group      a contacts_contact_groups_xref row exists
 * @param mixed $group_opt_in  opt_in.opt_in, null when there is no row
 * @return bool
 */
function pg_fs_subscribed($in_group, $group_opt_in)
{
    return $in_group && (($group_opt_in === null) || ((int) $group_opt_in === 1));
}

/**
 * Whether a submission leaves the form's group out until the confirmation:
 * the form asks for it, names a group, and the contact is not subscribed to
 * it already (pg_fs_subscribed()).
 *
 * @param mixed $double_opt_in
 * @param mixed $group_id
 * @param bool  $is_member
 * @return bool
 */
function pg_fs_hold_group($double_opt_in, $group_id, $is_member)
{
    return ((int) $double_opt_in === 1) && ((int) $group_id > 0) && !$is_member;
}

/**
 * Whether another confirmation mail may go to an address for a group: one a
 * day, so a form submitted again and again with somebody else's address does
 * not fill their mailbox.
 *
 * @param mixed $last_pending_at  the newest pending row's time, 0 or null for none
 * @param int   $now
 * @return bool
 */
function pg_fs_resend_allowed($last_pending_at, $now)
{
    $last_pending_at = (int) $last_pending_at;

    return ($last_pending_at <= 0) || (((int) $now - $last_pending_at) >= 86400);
}

/**
 * The confirmation link of a submitted form, kept for this request so the
 * submitter's own e-mail can carry it. Read with one argument, set with two.
 *
 * The link is signed for the contact's own address. The submitter mail goes
 * to $submitter_email_address in custom_form.php, which can be another
 * address: the signed-in user's when the form has no contact e-mail field, or
 * an e-mail field not bound to the contact. A caller puts this link into that
 * mail only when that address, normalised, equals pg_fs_contact_email() for
 * the contact; otherwise the link would reach someone other than the owner of
 * the address and the confirmation would mean nothing.
 *
 * @param int         $form_id
 * @param string|null $url
 * @return string  '' when the submission holds no group back
 */
function pg_fs_pending_url($form_id, $url = null)
{
    static $urls = array();

    $form_id = (int) $form_id;

    if ($url !== null) {
        $urls[$form_id] = (string) $url;
    }

    return isset($urls[$form_id]) ? $urls[$form_id] : '';
}

/**
 * Whether the 11.0.27 column is there. Every read and write of the switch
 * waits for it: files land before the upgrade runs.
 *
 * @return bool
 */
function pg_fs_ready()
{
    return pg_schema_has('custom_form_pages', 'double_opt_in');
}

/**
 * The site's signing key.
 *
 * @return string
 */
function pg_fs_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}

/**
 * A contact's own address, as it is now.
 *
 * @param int $contact_id
 * @return string  '' for no contact or no address
 */
function pg_fs_contact_email($contact_id)
{
    $contact_id = (int) $contact_id;

    if ($contact_id <= 0) {
        return '';
    }

    return trim((string) db_value("SELECT email_address FROM contacts WHERE id = '" . $contact_id . "'"));
}

/**
 * Whether a contact is subscribed to a contact group (pg_fs_subscribed()).
 *
 * @param int $contact_id
 * @param int $group_id
 * @return bool
 */
function pg_fs_is_member($contact_id, $group_id)
{
    $contact_id = (int) $contact_id;
    $group_id = (int) $group_id;

    $in_group = (int) db_value(
        "SELECT COUNT(*)
        FROM contacts_contact_groups_xref
        WHERE
            (contact_id = '" . $contact_id . "')
            AND (contact_group_id = '" . $group_id . "')") > 0;

    if (!$in_group) {
        return false;
    }

    $opt_in = db_value(
        "SELECT MIN(opt_in)
        FROM opt_in
        WHERE
            (contact_id = '" . $contact_id . "')
            AND (contact_group_id = '" . $group_id . "')");

    return pg_fs_subscribed(true, (($opt_in === '') || ($opt_in === null)) ? null : $opt_in);
}

/**
 * Whether a contact has turned off every e-mail from the site
 * (contacts.opt_in = 0, "unsubscribe from all" on the e-mail preferences
 * screen). Campaigns skip such a contact in every group.
 *
 * @param int $contact_id
 * @return bool
 */
function pg_fs_global_opt_out($contact_id)
{
    return (string) db_value("SELECT opt_in FROM contacts WHERE id = '" . (int) $contact_id . "'") === '0';
}

/**
 * A contact group's row: its name, and whether people manage it themselves on
 * the e-mail preferences screen (only then is its name shown to them).
 *
 * @param int $group_id
 * @return array|null  id, name, email_subscription
 */
function pg_fs_group($group_id)
{
    $group_id = (int) $group_id;

    if ($group_id <= 0) {
        return null;
    }

    $row = db_item("SELECT id, name, email_subscription FROM contact_groups WHERE id = '" . $group_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The confirmation link for a contact and a group, made now.
 *
 * @param int    $contact_id
 * @param int    $group_id
 * @param string $email  the contact's address
 * @return string  '' without a key or an address
 */
function pg_fs_confirm_url($contact_id, $group_id, $email)
{
    $issued_at = time();
    $token = pg_fs_token($contact_id, $group_id, $email, $issued_at, pg_fs_key());

    if ($token === '') {
        return '';
    }

    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/confirm_subscription.php?' . pg_fs_query($contact_id, $group_id, $issued_at, $token);
}

/**
 * When the newest confirmation mail went to an address for a group.
 *
 * @param string $email
 * @param int    $group_id
 * @return int  0 for none
 */
function pg_fs_last_pending($email, $group_id)
{
    $token = pg_privacy_person_token($email);

    if (($token === '') || !pg_consent_log_ready()) {
        return 0;
    }

    return (int) db_value(
        "SELECT MAX(created_at)
        FROM consent_log
        WHERE
            (person_token = '" . e($token) . "')
            AND (channel = 'email')
            AND (purpose = 'group:" . (int) $group_id . "')
            AND (status = 'pending')");
}

/**
 * Mails the confirmation link. Plain text, queued, and kept out of the failure
 * log like any other mail that carries a working link.
 *
 * @param string     $email
 * @param string     $url
 * @param array|null $group  pg_fs_group()
 * @return bool
 */
function pg_fs_send_confirmation($email, $url, $group)
{
    $site = (defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== '')) ? ORGANIZATION_NAME : HOSTNAME_SETTING;

    if (is_array($group) && ((int) $group['email_subscription'] === 1) && ((string) $group['name'] !== '')) {
        $intro = lang(array(
            'string' => 'Someone, probably you, asked to add {var:1} to the "{var:2}" mailing list of {var:3}.',
            'vars'   => array($email, (string) $group['name'], $site)));
    } else {
        $intro = lang(array(
            'string' => 'Someone, probably you, asked to add {var:1} to a mailing list of {var:2}.',
            'vars'   => array($email, $site)));
    }

    $body =
        $intro . "\n\n"
        . lang('To confirm, open the link below and press the button on the page:') . "\n"
        . $url . "\n\n"
        . lang('If you did not ask for this, you can ignore this e-mail: nothing changes without your confirmation, and the link stops working after seven days.') . "\n";

    return (bool) email(array(
        'to'                 => $email,
        'from_name'          => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang('Confirm your subscription'),
        'format'             => 'plain_text',
        'body'               => $body,
        'secret'             => true,
        'queue'              => true,
    ));
}

/**
 * A submission whose form's group was held back: makes the confirmation link,
 * keeps it for the submitter's mail and, at most once a day per address and
 * group, records the request as pending and mails it.
 *
 * The link goes to the contact's own address, never to an address typed into
 * another field. A contact without one cannot confirm; the group stays out
 * and the reason is logged. The pending row is written before the mail and is
 * what the daily limit reads, so a row that cannot be written sends no mail.
 *
 * @param int $form_id
 * @param int $contact_id
 * @param int $group_id
 * @return string  the link, '' when none could be made
 */
function pg_fs_hold_submission($form_id, $contact_id, $group_id)
{
    $email = pg_fs_contact_email($contact_id);

    if (pg_privacy_normalize_email($email) === '') {
        log_activity(lang(array(
            'string' => 'A form submission was not added to contact group {var:1}: the form asks for confirmation by e-mail and the contact has no e-mail address.',
            'vars'   => array((string) (int) $group_id))));
        return '';
    }

    $url = pg_fs_confirm_url($contact_id, $group_id, $email);

    if ($url === '') {
        log_activity(lang(array(
            'string' => 'A form submission was not added to contact group {var:1}: the form asks for confirmation by e-mail and the site has no encryption key to sign the link with.',
            'vars'   => array((string) (int) $group_id))));
        return '';
    }

    pg_fs_pending_url($form_id, $url);

    if (!pg_fs_resend_allowed(pg_fs_last_pending($email, $group_id), time())) {
        return $url;
    }

    $pending_id = pg_consent_log_record(array(
        'channel'    => 'email',
        'purpose'    => 'group:' . (int) $group_id,
        'status'     => 'pending',
        'source'     => 'form',
        'email'      => $email,
        'contact_id' => (int) $contact_id,
    ));

    if ($pending_id <= 0) {
        log_activity(lang(array(
            'string' => 'No confirmation e-mail was sent for contact group {var:1}: the request could not be recorded in the consent log, which limits these e-mails to one a day.',
            'vars'   => array((string) (int) $group_id))));
        return $url;
    }

    pg_fs_send_confirmation($email, $url, pg_fs_group($group_id));

    return $url;
}

/**
 * The confirmation: adds the contact to the group, opts it in to the group,
 * and records the consent with this request's address and browser.
 *
 * A contact that has turned off every e-mail from the site (contacts.opt_in
 * = 0) keeps that: the confirmation is for one group, and switching the
 * general opt-in back on would also restart every other group's mail. Such a
 * contact receives the group's mail after turning e-mail back on in the
 * e-mail preferences (a subscription group mails only contacts opted in to
 * both, add_email_campaign.php).
 *
 * Each write is one statement that checks and writes together, so two
 * presses at once leave one group row and one opt_in row. A failed write
 * returns false instead of ending the request.
 *
 * @param int    $contact_id
 * @param int    $group_id
 * @param string $email  the address the link was signed for
 * @return bool  false when a write failed
 */
function pg_fs_confirm($contact_id, $group_id, $email)
{
    $contact_id = (int) $contact_id;
    $group_id = (int) $group_id;

    if (($contact_id <= 0) || ($group_id <= 0)) {
        return false;
    }

    $where = "(contact_id = '" . $contact_id . "') AND (contact_group_id = '" . $group_id . "')";

    $writes = array(
        "INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id)
        SELECT '" . $contact_id . "', '" . $group_id . "' FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM contacts_contact_groups_xref WHERE " . $where . ")",
        "UPDATE opt_in SET opt_in = '1' WHERE " . $where,
        "INSERT INTO opt_in (contact_id, contact_group_id, opt_in)
        SELECT '" . $contact_id . "', '" . $group_id . "', '1' FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM opt_in WHERE " . $where . ")",
        "UPDATE contacts SET timestamp = UNIX_TIMESTAMP() WHERE id = '" . $contact_id . "'",
    );

    foreach ($writes as $query) {
        // Not db(): a failed query there ends the request with an error page.
        if (@mysqli_query(db::$con, $query) === false) {
            error_log('Pinegrap: subscription confirmation failed: ' . mysqli_error(db::$con));
            return false;
        }
    }

    pg_consent_log_record(array(
        'channel'    => 'email',
        'purpose'    => 'group:' . $group_id,
        'status'     => 'granted',
        'source'     => 'form',
        'email'      => $email,
        'contact_id' => $contact_id,
    ));

    log_activity(lang(array(
        'string' => '{var:1} confirmed the subscription to contact group {var:2}.',
        'vars'   => array((string) $email, (string) $group_id))), 'UNKNOWN');

    return true;
}
