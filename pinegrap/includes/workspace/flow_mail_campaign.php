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

/* ---------------------------------------------------------------------------
   To a contact group, as a campaign
   --------------------------------------------------------------------------- */

/**
 * What an e-mail box to a contact group lacks before its flow is published:
 * the group, a text of its own (a page is not sent to a group), and a
 * records box only with a group. Pure but for lang().
 *
 * @param array  $config ws_step_config_normalize('email')
 * @param string $name   the box's name
 * @return string[]
 */
function ws_flow_mail_group_problems($config, $name)
{
    $problems = array();
    $group = (($config['recipient'] ?? '') === 'group');

    if ($group && ((int) ($config['group_id'] ?? 0) <= 0)) {
        $problems[] = lang(array('string' => 'The box {var:1} needs the contact group it writes to.', 'vars' => $name));
    }

    if ($group && ((int) ($config['page_id'] ?? 0) > 0)) {
        $problems[] = lang(array('string' => 'The box {var:1} writes to a contact group with a text of its own, not a page.', 'vars' => $name));
    }

    if (!$group && ((string) ($config['records'] ?? '') !== '')) {
        $problems[] = lang(array('string' => 'The box {var:1} draws records only in an e-mail to a contact group.', 'vars' => $name));
    }

    return $problems;
}

/**
 * The records box an e-mail box draws must be a "Read the records of a
 * list" box of the same flow. Pure but for lang().
 *
 * @param array  $config ws_step_config_normalize('email')
 * @param string $name   the box's name
 * @param array  $graph
 * @return string[]
 */
function ws_flow_mail_records_box_problems($config, $name, $graph)
{
    $box = (string) ($config['records'] ?? '');

    if ($box === '') {
        return array();
    }

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if (((string) ($node['id'] ?? '') === $box) && ((string) ($node['kind'] ?? '') === 'list_records')) {
            return array();
        }
    }

    return array(lang(array('string' => 'The box {var:1} draws the records of a box that is not a “Read the records of a list” box of this flow.', 'vars' => $name)));
}

/**
 * Why the owner may not send an e-mail box to its contact group, '' when
 * they may: a team member above role 3 (a role-3 flow has no e-mail box),
 * and a group that is there.
 *
 * @param array $viewer the flow's owner
 * @param array $config the box's settings
 * @return string
 */
function ws_flow_mail_group_refusal_for($viewer, $config)
{
    if ((int) ($viewer['role'] ?? 3) >= 3) {
        return lang('Only a team member who sends campaigns can write to a contact group.');
    }

    $group = (int) ($config['group_id'] ?? 0);

    if (($group <= 0) || !db_value("SELECT id FROM contact_groups WHERE id = '" . $group . "'")) {
        return lang('Choose a contact group that is there.');
    }

    if ((int) ($config['page_id'] ?? 0) > 0) {
        return lang('A contact group is written to with a text of its own, not a page.');
    }

    return '';
}

/**
 * The records an e-mail draws, from the list a records box gave: title,
 * summary, picture and link, each a string; a picture or link only an
 * http(s) address. Pure.
 *
 * @param mixed $list the records box's records (JSON text or list)
 * @return array[] title, summary, picture, link
 */
function ws_flow_mail_digest_items($list)
{
    $list = is_string($list) ? json_decode($list, true) : $list;
    $address = function ($value) {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return (preg_match('~^https?://[^\s<>"\']+$~i', $value) === 1) ? $value : '';
    };
    $out = array();

    foreach (is_array($list) ? $list : array() as $item) {
        if (!is_array($item)) {
            continue;
        }

        $out[] = array(
            'title'   => is_scalar($item['title'] ?? null) ? trim((string) $item['title']) : '',
            'summary' => is_scalar($item['summary'] ?? null) ? trim((string) $item['summary']) : '',
            'picture' => $address($item['picture'] ?? ''),
            'link'    => $address($item['link'] ?? ''),
        );
    }

    return $out;
}

/**
 * The e-mail to a contact group as a whole HTML document in the campaigns'
 * mail layout: the box's text, then each record with its picture, its
 * linked title and its summary. Every text is escaped; the campaign footer
 * is added after (pg_email_campaign_prepare_html_body()). Pure.
 *
 * @param string  $subject
 * @param string  $text  the box's filled text
 * @param array[] $items ws_flow_mail_digest_items()
 * @return string
 */
function ws_flow_mail_digest_html($subject, $text, $items)
{
    $font = 'font-family: Arial, Helvetica, sans-serif;';
    $html = '';

    foreach (preg_split('/\n{2,}/', trim(str_replace("\r\n", "\n", (string) $text))) as $paragraph) {
        if (trim($paragraph) !== '') {
            $html .= '<p style="' . $font . ' font-size: 16px; line-height: 24px; color: #1f2933; margin: 0 0 16px 0">' . nl2br(h(trim($paragraph)), false) . '</p>';
        }
    }

    foreach ((array) $items as $item) {
        $title = h((string) $item['title']);
        $link = (string) $item['link'];
        $row = '';

        if ((string) $item['picture'] !== '') {
            $image = '<img src="' . h((string) $item['picture']) . '" alt="' . $title . '" width="552" style="display: block; width: 100%; max-width: 552px; height: auto; border: 0; border-radius: 6px; margin: 0 0 12px 0">';
            $row .= ($link !== '') ? '<a href="' . h($link) . '">' . $image . '</a>' : $image;
        }

        if ($title !== '') {
            $heading = ($link !== '') ? '<a href="' . h($link) . '" style="color: #0b5cad; text-decoration: none">' . $title . '</a>' : $title;
            $row .= '<h2 style="' . $font . ' font-size: 20px; line-height: 26px; color: #1f2933; margin: 0 0 8px 0">' . $heading . '</h2>';
        }

        if ((string) $item['summary'] !== '') {
            $row .= '<p style="' . $font . ' font-size: 15px; line-height: 22px; color: #3e4c59; margin: 0">' . h((string) $item['summary']) . '</p>';
        }

        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top: 1px solid #e4e7eb; margin: 16px 0 0 0"><tr><td style="padding: 20px 0 4px 0">' . $row . '</td></tr></table>';
    }

    return '<!DOCTYPE html>' . "\n"
        . '<html>' . "\n" . '<head>' . "\n"
        . '<meta charset="utf-8">' . "\n"
        . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
        . '<title>' . h((string) $subject) . '</title>' . "\n"
        . '</head>' . "\n"
        . '<body style="margin: 0; padding: 0; background-color: #f4f5f7">' . "\n"
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f5f7"><tr><td align="center" style="padding: 24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; background-color: #ffffff; border-radius: 8px"><tr><td style="padding: 24px">'
        . $html
        . '</td></tr></table>'
        . '</td></tr></table>' . "\n"
        . '</body>' . "\n" . '</html>';
}

/**
 * The records an e-mail box draws in a run: the list its records box gave,
 * read from the run's values. No records box: none and no box.
 *
 * @param array $config the box's settings
 * @param array $values ws_step_run_values()
 * @return array box (a records box is set), count (records it read), items
 */
function ws_flow_mail_group_records($config, $values)
{
    $box = (string) ($config['records'] ?? '');

    if ($box === '') {
        return array('box' => false, 'count' => 0, 'items' => array());
    }

    $items = ws_flow_mail_digest_items($values[$box]['records'] ?? '');

    return array('box' => true, 'count' => count($items), 'items' => $items);
}

/**
 * The campaign row an e-mail to a contact group is written as: the box's
 * sender or the site's, its subject, the body made ready as a campaign
 * stores it, sent now; commercial for marketing (opted-in contacts only),
 * transactional otherwise.
 *
 * @param array  $action the filled action (subject, sender)
 * @param array  $config the box's settings
 * @param string $html   ws_flow_mail_digest_html()
 * @return array pg_email_campaign_insert() values
 */
function ws_flow_mail_group_campaign($action, $config, $html)
{
    // The campaigns' create path is loaded only where campaigns are made.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_create.php');

    $sender = is_array($action['sender'] ?? null) ? $action['sender'] : ws_flow_mail_sender_normalize($config);

    return array(
        'from_name'           => ((string) ($sender['from_name'] ?? '') !== '') ? (string) $sender['from_name'] : (string) ORGANIZATION_NAME,
        'from_email_address'  => ((string) ($sender['from_email'] ?? '') !== '') ? (string) $sender['from_email'] : (string) EMAIL_ADDRESS,
        'reply_email_address' => (string) ($sender['reply_to'] ?? ''),
        'subject'             => (string) ($action['subject'] ?? ''),
        'format'              => 'html',
        'body'                => pg_email_campaign_prepare_html_body($html, 'flow'),
        'page_id'             => 0,
        'start_time'          => '1000-01-01 00:00:00',
        'purpose'             => ws_flow_mail_group_purpose($config),
        'status'              => 'ready',
    );
}

/**
 * The campaign purpose of an e-mail to a contact group: commercial for a
 * marketing box, so only opted-in contacts get it, transactional
 * otherwise. Pure.
 *
 * @param array $config
 * @return string
 */
function ws_flow_mail_group_purpose($config)
{
    return (($config['purpose'] ?? '') === 'marketing') ? 'commercial' : 'transactional';
}

/**
 * Sends an e-mail box to its contact group: one campaign through the
 * campaigns' own path (pg_email_campaign_pick_recipients(),
 * pg_email_campaign_insert()), so opt-in, bounced addresses, İYS and the
 * unsubscribe footer are the campaign's and it stands in the campaigns
 * list. A records box that read none sends nothing.
 *
 * @param array $owner  the flow's owner, checked
 * @param array $action the filled action
 * @param array $config the box's settings
 * @param array $values ws_step_run_values()
 * @return array ok, text, data (sent, campaign_id)
 */
function ws_flow_mail_group_do($owner, $action, $config, $values)
{
    // The campaigns' create path is loaded only where campaigns are made.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_create.php');

    $refusal = ws_flow_mail_group_refusal_for($owner, $config);

    if ($refusal !== '') {
        return array('ok' => false, 'text' => $refusal, 'data' => array());
    }

    $records = ws_flow_mail_group_records($config, $values);

    if ($records['box'] && ($records['count'] === 0)) {
        return array('ok' => true, 'text' => lang('No record to send: no e-mail was sent.'), 'data' => array('sent' => 0));
    }

    $picked = pg_email_campaign_pick_recipients($owner, ws_flow_mail_group_purpose($config), array((int) $config['group_id'] => 'included'), '');

    if ($picked === false) {
        return array('ok' => false, 'text' => lang('The contact group could not be read.'), 'data' => array());
    }

    if (empty($picked['recipients'])) {
        return array('ok' => true, 'text' => lang('Nobody in the contact group may get this e-mail: none was sent.'), 'data' => array('sent' => 0));
    }

    $html = ws_flow_mail_digest_html((string) $action['subject'], (string) ($action['body'] ?? ''), $records['items']);
    $campaign = ws_flow_mail_group_campaign($action, $config, $html);
    $id = pg_email_campaign_insert($campaign, $picked, (int) $owner['id']);

    if ($id <= 0) {
        return array('ok' => false, 'text' => lang('The campaign could not be written.'), 'data' => array());
    }

    return array(
        'ok'   => true,
        'text' => lang(array('string' => 'A campaign to {var:1} people was made: “{var:2}”.', 'vars' => array(count($picked['recipients']), (string) $campaign['subject']))),
        'data' => array('sent' => count($picked['recipients'])),
    );
}

/**
 * The records Try draws: those of the chosen run, else those its records
 * box would read now (ws_step_list_records_try()'s window), read only.
 *
 * @param array $owner
 * @param array $config the box's settings
 * @param array $values ws_step_run_values() of the chosen run, or empty
 * @param array $graph
 * @param array $flow
 * @return array ws_flow_mail_group_records()
 */
function ws_flow_mail_group_try_records($owner, $config, $values, $graph, $flow)
{
    $box = (string) ($config['records'] ?? '');

    if (($box === '') || isset($values[$box])) {
        return ws_flow_mail_group_records($config, $values);
    }

    $node = ws_flow_graph_node($graph, $box);
    $check = is_array($node) ? ws_step_list_records_check($owner, (array) ($node['config'] ?? array())) : array('ok' => false);

    if (empty($check['ok'])) {
        return array('box' => true, 'count' => 0, 'items' => array());
    }

    $window = !empty($check['action']['only_new']) ? ws_flow_records_window_try($flow) : null;
    $items = ws_flow_mail_digest_items(ws_flow_records_read(ws_flow_records_page((int) $check['action']['page_id']), $window)['items']);

    return array('box' => true, 'count' => count($items), 'items' => $items);
}

/**
 * Try on an e-mail box to a contact group: the e-mail mailed to the person
 * trying alone, as a campaign's test is (pg_campaign_test_send(), its
 * hourly limit), and how many of the group would get it. No campaign is
 * made and no window moves.
 *
 * @param array $viewer the person trying
 * @param array $owner  the flow's owner
 * @param array $action the filled action
 * @param array $config the box's settings
 * @param array $values ws_step_run_values() of the chosen run, or empty
 * @param array $graph
 * @param array $flow
 * @return array outcome, said, refusal
 */
function ws_flow_mail_group_try($viewer, $owner, $action, $config, $values, $graph, $flow)
{
    // The campaigns' create path is loaded only where campaigns are made.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_create.php');

    $refusal = ws_flow_mail_group_refusal_for($owner, $config);

    if ($refusal !== '') {
        return array('outcome' => 'failed', 'refusal' => $refusal);
    }

    $records = ws_flow_mail_group_try_records($owner, $config, $values, $graph, $flow);

    if ($records['box'] && ($records['count'] === 0)) {
        return array('outcome' => 'done', 'said' => lang('No record to send: the run would send no e-mail.'));
    }

    $picked = pg_email_campaign_pick_recipients($owner, ws_flow_mail_group_purpose($config), array((int) $config['group_id'] => 'included'), '');
    $count = is_array($picked) ? count($picked['recipients']) : 0;
    $said = lang(array('string' => 'The run would make a campaign to {var:1} people.', 'vars' => $count));
    $to = trim((string) db_value("SELECT user_email FROM user WHERE user_id = '" . (int) ($viewer['id'] ?? 0) . "'"));

    if (($to === '') || !validate_email_address($to)) {
        return array('outcome' => 'done', 'said' => $said . ' ' . lang('You have no e-mail address, so no test was mailed.'));
    }

    if (function_exists('waf_rate_exceeded') && waf_rate_exceeded('campaign_test|' . (int) $viewer['id'], 'campaign_test', pg_campaign_test_hourly_limit(), 3600)) {
        return array('outcome' => 'done', 'said' => $said . ' ' . lang(array('string' => 'No test was mailed: a user may send {var:1} tests an hour.', 'vars' => pg_campaign_test_hourly_limit())));
    }

    $campaign = ws_flow_mail_group_campaign($action, $config, ws_flow_mail_digest_html((string) $action['subject'], (string) ($action['body'] ?? ''), $records['items']));
    $sent = pg_campaign_test_send($campaign, array($to), (int) $viewer['id']);

    return array('outcome' => 'done', 'said' => $said . ' ' . (($sent['sent'] > 0)
        ? lang(array('string' => 'A test was mailed to you alone ({var:1}).', 'vars' => $to))
        : lang(array('string' => 'The test to {var:1} could not be sent.', 'vars' => $to))));
}
