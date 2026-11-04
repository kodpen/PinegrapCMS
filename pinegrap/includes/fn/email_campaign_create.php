<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * E-mail campaign create: picking the recipients from contact groups and
 * writing the campaign with its recipients and groups, one path that the
 * create screen (add_email_campaign.php) and the external API
 * (POST /campaigns) share, so a campaign made from either place reaches the
 * same people and leaves the same rows.
 *
 * Nothing here checks who is asking beyond the contact groups: the screen
 * gates on validate_email_access(), the API on campaigns:write. A group the
 * user may not reach (validate_contact_group_access()) is passed over, as the
 * screen has always done.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_recipients.php');

/**
 * The recipients of a new manual campaign: the addresses of the included
 * groups (opted-in contacts only for a commercial campaign), less every
 * address of the excluded groups, plus one entered address. Each address is
 * kept once, with the first contact found for it. For a user above the user
 * role an entered address that no contact or user has yet becomes a contact,
 * as on the create screen.
 *
 * @param array  $user
 * @param string $purpose  commercial or transactional
 * @param array  $groups   contact group id => 'included' or 'excluded'
 * @param string $entered  one more address, '' for none (checked by the caller)
 * @return array|false  recipients (addresses), contact_ids (address => id),
 *                      included and excluded (group ids); false when a query failed
 */
function pg_email_campaign_pick_recipients($user, $purpose, $groups, $entered)
{
    $recipients = array();
    $contact_ids = array();
    $included = array();
    $excluded = array();

    $result = mysqli_query(db::$con, "SELECT id FROM contact_groups");

    if ($result === false) {
        return false;
    }

    $contact_groups = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $contact_groups[] = $row['id'];
    }

    foreach ($contact_groups as $contact_group_id) {
        if ((($groups[$contact_group_id] ?? '') !== 'included') || !validate_contact_group_access($user, $contact_group_id)) {
            continue;
        }

        $included[] = $contact_group_id;

        $email_subscription = db_value("SELECT email_subscription FROM contact_groups WHERE id = '" . (int) $contact_group_id . "'");

        // A commercial campaign takes opted-in contacts only, a
        // transactional one every contact of the group with an address.
        $result = db_q(pg_email_campaign_recipient_query($purpose, $email_subscription == 1), array((string) $contact_group_id));

        if ($result === false) {
            return false;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $email_address = mb_strtolower($row['email_address']);
            $recipients[] = $email_address;

            // The first contact found for an address keeps it.
            if (!array_key_exists($email_address, $contact_ids)) {
                $contact_ids[$email_address] = $row['id'];
            }
        }
    }

    $recipients = array_unique($recipients);

    foreach ($contact_groups as $contact_group_id) {
        if ((($groups[$contact_group_id] ?? '') !== 'excluded') || !validate_contact_group_access($user, $contact_group_id)) {
            continue;
        }

        $excluded[] = $contact_group_id;

        $result = db_q(
            "SELECT contacts.email_address
            FROM contacts_contact_groups_xref
            LEFT JOIN contacts ON contacts_contact_groups_xref.contact_id = contacts.id
            WHERE
                (contacts_contact_groups_xref.contact_group_id = ?)
                AND (contacts.email_address != '')",
            array((string) $contact_group_id));

        if ($result === false) {
            return false;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $key = array_search(mb_strtolower($row['email_address']), $recipients);

            if ($key !== false) {
                unset($recipients[$key]);
            }
        }
    }

    if ($entered !== '') {
        $recipients[] = mb_strtolower($entered);

        // An address nobody has yet becomes a contact, for a user above the
        // user role.
        if ($user['role'] < 3) {
            $in_contacts = db_value("SELECT COUNT(*) FROM contacts WHERE email_address = '" . escape($entered) . "'");
            $in_users = db_value("SELECT COUNT(*) FROM user WHERE user_email = '" . escape($entered) . "'");

            if (((int) $in_contacts === 0) && ((int) $in_users === 0)) {
                $result = db_q(
                    "INSERT INTO contacts (email_address, user, timestamp) VALUES (?, ?, UNIX_TIMESTAMP())",
                    array((string) $entered, (string) $user['id']));

                if ($result !== false) {
                    // Read the id first: the audit line is itself an insert.
                    $new_contact_id = (int) mysqli_insert_id(db::$con);
                    pg_audit_record('create', 'contacts', $new_contact_id);
                }
            }
        }
    }

    return array(
        'recipients'  => array_values(array_unique($recipients)),
        'contact_ids' => $contact_ids,
        'included'    => $included,
        'excluded'    => $excluded,
    );
}

/**
 * Writes a new manual campaign, one recipient row per address and the groups
 * it was put together from.
 *
 * @param array $values  from_name, from_email_address, reply_email_address,
 *                       subject, format, body, page_id, start_time (the
 *                       column value), purpose, status (ready or paused)
 * @param array $picked  pg_email_campaign_pick_recipients()
 * @param int   $user_id
 * @return int  the campaign's id, 0 when it could not be written
 */
function pg_email_campaign_insert($values, $picked, $user_id)
{
    // The body is stored as written or drawn. email() sends through
    // PHPMailer, which switches to quoted-printable for a line over 998
    // characters; a hard wrap here cut words, URLs, multibyte characters and
    // inline scripts apart.
    $body = (string) $values['body'];
    $status = (($values['status'] ?? 'ready') === 'paused') ? 'paused' : 'ready';
    $user_id = (string) (int) $user_id;

    $result = db_q(
        "INSERT INTO email_campaigns (
            from_name,
            from_email_address,
            reply_email_address,
            subject,
            format,
            body,
            page_id,
            start_time,
            purpose,
            status,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())",
        array(
            (string) $values['from_name'], (string) $values['from_email_address'], (string) $values['reply_email_address'],
            (string) $values['subject'], (string) $values['format'], $body, (string) $values['page_id'],
            (string) $values['start_time'], (string) $values['purpose'], $status, $user_id, $user_id
        ));

    if ($result === false) {
        return 0;
    }

    $email_campaign_id = (int) mysqli_insert_id(db::$con);

    foreach ($picked['recipients'] as $email_address) {
        $contact_id = array_key_exists($email_address, $picked['contact_ids']) ? $picked['contact_ids'][$email_address] : 0;

        db_q(
            "INSERT INTO email_recipients (email_campaign_id, email_address, contact_id) VALUES (?, ?, ?)",
            array((string) $email_campaign_id, (string) $email_address, (string) $contact_id));
    }

    foreach (array('included', 'excluded') as $type) {
        foreach ($picked[$type] as $contact_group_id) {
            db_q(
                "INSERT INTO contact_groups_email_campaigns_xref (contact_group_id, email_campaign_id, type) VALUES (?, ?, ?)",
                array((string) $contact_group_id, (string) $email_campaign_id, $type));
        }
    }

    return $email_campaign_id;
}

/**
 * The HTML of a page drawn as the body of a campaign.
 *
 * The page is drawn inside the sender's request, but the e-mail is read by
 * its recipients: the render is an e-mail render (the account widgets draw
 * signed out, get_page_content() writes no session token), and any copy of
 * the sender's CSRF token that still reached the HTML, such as a link a
 * widget built with it, is removed (pg_email_campaign_without_token()).
 *
 * @param int|string $page_id
 * @return string
 */
function pg_email_campaign_page_body($page_id)
{
    require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

    $body = (string) get_page_content($page_id, '', '', 'preview', true);

    return pg_email_campaign_without_token($body, (string) ($_SESSION['software']['token'] ?? ''));
}

/**
 * The HTML without the given session token. A token is a long random hex
 * string, so it is cut wherever it appears (a link's token= parameter, a
 * hidden field, an inline script); a blank or short value cuts nothing.
 *
 * @param string $html
 * @param string $token
 * @return string
 */
function pg_email_campaign_without_token($html, $token)
{
    $token = (string) $token;

    if (strlen($token) < 16) {
        return (string) $html;
    }

    return str_replace($token, '', (string) $html);
}
