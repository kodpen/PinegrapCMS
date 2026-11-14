<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a flow's e-mail box sending as an e-mail campaign sends: an
 * optional sender of its own (From name and address, Reply-to, BCC), its
 * ^^fields^^ (and [[...||...]] blocks) filled from the values the run was
 * started with and the recipient's contact, a page body with a base tag and
 * absolute addresses whose PHP is given the run's record as data and, for
 * marketing, the ?t= codes on links to the site, the campaigns' commercial
 * footer and a contact group's opt-out for a run that joining it started.
 * A box drawn before has none of these set and sends as it did.
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
 * An e-mail box's sender settings as they are kept: each a single line, an
 * address trimmed; '' for the site's own (ORGANIZATION_NAME, EMAIL_ADDRESS,
 * no Reply-to, no BCC). Pure.
 *
 * @param array $config the box's settings
 * @return array from_name, from_email, reply_to, bcc
 */
function ws_flow_mail_sender_normalize($config)
{
    $line = function ($key, $max) use ($config) {
        $value = $config[$key] ?? '';

        return is_scalar($value) ? mb_substr(trim((string) preg_replace('/[\r\n\t]+/', ' ', (string) $value)), 0, $max) : '';
    };

    return array(
        'from_name'  => $line('from_name', 100),
        'from_email' => $line('from_email', 100),
        'reply_to'   => $line('reply_to', 100),
        'bcc'        => $line('bcc', 100),
    );
}

/**
 * What is wrong with an e-mail box's sender, checked as a campaign's sender
 * is: each address given is one address validate_email_address() takes.
 *
 * @param array  $config ws_flow_mail_sender_normalize() values
 * @param string $name   the box's name, for the message
 * @return string[]
 */
function ws_flow_mail_sender_problems($config, $name)
{
    $labels = array('from_email' => lang('From address'), 'reply_to' => lang('Reply-to'), 'bcc' => lang('BCC'));
    $problems = array();

    foreach ($labels as $key => $label) {
        $address = (string) ($config[$key] ?? '');

        if (($address !== '') && !validate_email_address($address)) {
            $problems[] = lang(array('string' => 'The box {var:1}: {var:2} is not an e-mail address.', 'vars' => array($name, $label)));
        }
    }

    return $problems;
}

/**
 * The email() properties a box's sender sets, only those given: a From
 * address of its own, its name (with the site's address when only a name is
 * given), Reply-to and BCC. Pure.
 *
 * @param array $sender ws_flow_mail_sender_normalize() values
 * @return array from_name, from_email_address, reply_to, bcc
 */
function ws_flow_mail_sender_properties($sender)
{
    $properties = array();
    $map = array('from_name' => 'from_name', 'from_email' => 'from_email_address', 'reply_to' => 'reply_to', 'bcc' => 'bcc');

    foreach ($map as $key => $property) {
        if ((string) ($sender[$key] ?? '') !== '') {
            $properties[$property] = (string) $sender[$key];
        }
    }

    return $properties;
}

/**
 * The ^^fields^^ a box's mail fills: the values the run was started with
 * (the start box's outputs; a list or a missing value is left out), then the
 * recipient's ^^name^^, ^^first_name^^ and ^^last_name^^ from their
 * contact, each blank without one. A start value of the same name comes
 * first, as a profile's action fields are filled before the recipient's.
 * Pure.
 *
 * @param array      $start   the run's start values (WS_STEP_TRIGGER)
 * @param array|null $contact the recipient's contacts row, null for none
 * @return array replace_variables() fields
 */
function ws_flow_mail_fields($start, $contact)
{
    $fields = array();

    foreach ((array) $start as $name => $value) {
        if (is_string($name) && preg_match('/^[a-z0-9_]{1,64}$/i', $name) && (is_scalar($value))) {
            $fields[] = array('name' => $name, 'data' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'type' => '');
        }
    }

    return array_merge($fields, pg_mail_merge_contact_fields($contact));
}

/**
 * The run's record a page's PHP is given as data, under the name a campaign
 * profile's action gives it: order, form, booking, contact. Pure.
 *
 * @param string     $kind ws_flow_mail_person_source() kind
 * @param array|null $row  the record's row
 * @return array
 */
function ws_flow_mail_record_data($kind, $row)
{
    $names = array('order' => 'order', 'form' => 'form', 'booking' => 'booking', 'contact' => 'contact');

    return (isset($names[$kind]) && is_array($row)) ? array($names[$kind] => $row) : array();
}

/**
 * The run's record for a page's PHP, read with the flow owner's rights as
 * they stand now; nothing when the run is about none (a received e-mail
 * included: no campaign page was ever given one) or the owner may not read
 * it.
 *
 * @param array $owner
 * @param array $run
 * @return array ws_flow_mail_record_data()
 */
function ws_flow_mail_record($owner, $run)
{
    list($kind, $id) = ws_flow_mail_person_source((string) ($run['subject_type'] ?? ''), (int) ($run['subject_id'] ?? 0), ws_flow_run_context($run));
    $tables = array('order' => 'orders', 'form' => 'forms', 'booking' => 'bookings', 'contact' => 'contacts');

    if (!isset($tables[$kind])) {
        return array();
    }

    if (!ws_step_record_get_read($owner, $kind, $id)) {
        return array();
    }

    return ws_flow_mail_record_data($kind, db_item("SELECT * FROM " . $tables[$kind] . " WHERE id = '" . (int) $id . "'"));
}

/**
 * The contact a mail fills ^^name^^ and the rest from: the person's own,
 * else the first holding the address.
 *
 * @param int    $contact_id
 * @param string $email
 * @return array|null
 */
function ws_flow_mail_contact($contact_id, $email)
{
    $columns = "first_name, last_name, nickname, salutation, suffix";

    if ((int) $contact_id > 0) {
        $row = db_item("SELECT " . $columns . " FROM contacts WHERE id = '" . (int) $contact_id . "'");

        if (is_array($row)) {
            return $row;
        }
    }

    if ((string) $email === '') {
        return null;
    }

    $row = db_item("SELECT " . $columns . " FROM contacts WHERE email_address = '" . e((string) $email) . "' ORDER BY id LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The contact group whose opt-out a marketing mail of this run honours: the
 * group a contact joined when contact_group.joined started the run. Pure.
 *
 * @param array $context ws_flow_run_context()
 * @return array contact_id, group_id (0 and 0 for a run started otherwise)
 */
function ws_flow_mail_joined_group($context)
{
    $payload = is_array($context['event_payload'] ?? null) ? $context['event_payload'] : array();

    if ((string) ($context['event'] ?? '') !== 'contact_group.joined') {
        return array('contact_id' => 0, 'group_id' => 0);
    }

    return array('contact_id' => max(0, (int) ($payload['contact_id'] ?? 0)), 'group_id' => max(0, (int) ($payload['group_id'] ?? 0)));
}

/**
 * Why a marketing mail of a run that joining a contact group started may not
 * go, '' when it may: the contact opted out of that group's e-mail, as a
 * campaign profile on "joined a group" leaves them out
 * (pg_auto_campaign_group_opted_out()).
 *
 * @param array $run
 * @return string
 */
function ws_flow_mail_group_refusal($run)
{
    $joined = ws_flow_mail_joined_group(ws_flow_run_context($run));

    if (($joined['contact_id'] <= 0) || ($joined['group_id'] <= 0)) {
        return '';
    }

    $group = db_item("SELECT contact_groups.email_subscription, opt_in.opt_in
        FROM contact_groups
        LEFT JOIN opt_in ON (opt_in.contact_group_id = contact_groups.id) AND (opt_in.contact_id = '" . (int) $joined['contact_id'] . "')
        WHERE contact_groups.id = '" . (int) $joined['group_id'] . "'");

    if (is_array($group) && pg_auto_campaign_group_opted_out($group['email_subscription'], $group['opt_in'])) {
        return lang('The person opted out of the contact group\'s e-mail, so no marketing e-mail was sent.');
    }

    return '';
}

/**
 * What an e-mail box's action carries beside its texts when it runs: its
 * sender, the ^^fields^^ to fill, the record for a page's PHP. Each is given
 * to ws_scheduled_do() in the action.
 *
 * @param array  $action  the filled action
 * @param array  $config  the box's settings
 * @param array  $owner
 * @param array  $run
 * @param array  $values  ws_step_run_values()
 * @param array  $person  ws_flow_mail_person() for a record_person box, else
 *                        an empty array
 * @return array the action
 */
function ws_flow_mail_campaign_action($action, $config, $owner, $run, $values, $person)
{
    $email = (string) ($person['email'] ?? '');
    $contact = ($email !== '') ? ws_flow_mail_contact((int) ($person['contact_id'] ?? 0), $email) : null;

    $action['sender'] = ws_flow_mail_sender_normalize($config);
    $action['fields'] = ws_flow_mail_fields(is_array($values[WS_STEP_TRIGGER] ?? null) ? $values[WS_STEP_TRIGGER] : array(), $contact);
    $action['data'] = ((int) ($action['page_id'] ?? 0) > 0) ? ws_flow_mail_record($owner, $run) : array();

    return $action;
}

/**
 * A page body made ready to send as a campaign's: a base tag and absolute
 * addresses and, for marketing, ?t= codes on links to the site and the
 * commercial footer before </body> with the preferences link whose
 * <email_address_id> placeholder ws_scheduled_mail() fills per recipient.
 * Pure but for the site's constants passed in.
 *
 * @param string $body         get_page_content() of the page
 * @param bool   $marketing
 * @param string $site         scheme and host
 * @param string $code         pg_mail_page_tracking_code()
 * @param string $hostname
 * @param array  $organization pg_mail_organization()
 * @param string $preferences  the preferences screen's address with the
 *                             placeholder, HTML ready
 * @return string
 */
function ws_flow_mail_page_html($body, $marketing, $site, $code, $hostname, $organization, $preferences)
{
    $body = pg_mail_page_absolute_links(pg_mail_merge_address_hrefs((string) $body), $site);

    if (!$marketing) {
        return $body;
    }

    $body = pg_mail_page_tracking_codes($body, $code, $hostname);
    $footer = '<div class="software_email_footer" style="font-family: arial; font-size: 11px; color: #666666; text-align: center; background-color: #ffffff; padding: 5px; margin-top: 15px">'
        . pg_mail_commercial_content_html($organization, $preferences) . '</div>';

    return (stripos($body, '</body>') !== false) ? (string) preg_replace('~</body>~i', $footer . '</body>', $body, 1) : $body . $footer;
}

/**
 * The placeholder a page body's preferences link carries until the mail is
 * sent to one address.
 *
 * @return string
 */
function ws_flow_mail_preferences_placeholder()
{
    return '<email_address_id></email_address_id>';
}
