<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the e-mail box of a flow writing to the person of the run's
 * record (recipient record_person): an order's customer, a submitted form's
 * sender, a booking's guest, a received e-mail's sender, a contact. The
 * address is read from the record, never typed, with the flow owner's
 * rights as they stand when the box runs. A purpose says why it writes:
 * info needs no consent; marketing goes only to an address whose contact
 * agreed to marketing e-mail (contacts.opt_in, as campaigns read it), not
 * refused in İYS nor blocked by bounces, and carries the campaigns'
 * preferences and unsubscribe link. A person it may not write to leaves the
 * box done with nothing sent and the reason in the run.
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
 * The recipients an e-mail box writes to: the addresses typed in to, or the
 * person of the run's record.
 *
 * @return string[]
 */
function ws_flow_mail_recipients()
{
    return array('typed', 'record_person');
}

/**
 * Why an e-mail box writes to the record's person: info (an answer, a
 * reminder; no consent needed) or marketing (consent needed).
 *
 * @return string[]
 */
function ws_flow_mail_purposes()
{
    return array('info', 'marketing');
}

/**
 * Which record a run is about, as the person it writes to is read from: a
 * run started by hand or for a customer who stopped ordering keeps its
 * contact; one started by an event names the event's record
 * (ws_events_record_of()); a received e-mail its conversation. Pure.
 *
 * @param string $subject_type the run's subject_type
 * @param int    $subject_id   the run's subject_id
 * @param array  $context      ws_flow_run_context()
 * @return array kind (order, form, booking, contact, mail; '' for none), id
 */
function ws_flow_mail_person_source($subject_type, $subject_id, $context)
{
    if (((string) $subject_type === 'contact') && ((int) $subject_id > 0)) {
        return array('contact', (int) $subject_id);
    }

    $event = (string) ($context['event'] ?? '');
    $payload = is_array($context['event_payload'] ?? null) ? $context['event_payload'] : array();

    if ($event === 'workspace.mail.received') {
        $id = (int) ($payload['conversation_id'] ?? 0);

        return ($id > 0) ? array('mail', $id) : array('', 0);
    }

    if (($event !== '') && function_exists('ws_events_record_of')) {
        $record = ws_events_record_of($event, $payload);

        if (in_array($record[0], array('order', 'form', 'booking', 'contact'), true) && ((int) $record[1] > 0)) {
            return array($record[0], (int) $record[1]);
        }
    }

    return array('', 0);
}

/**
 * The person of a run's record the flow's owner may reach: their address
 * and contact, or null with why not.
 *
 * @param array $owner the flow's owner, on the staff
 * @param array $run   ws_flow_runs row
 * @return array email, contact_id, error ('' when found)
 */
function ws_flow_mail_person($owner, $run)
{
    $source = ws_flow_mail_person_source((string) ($run['subject_type'] ?? ''), (int) ($run['subject_id'] ?? 0), ws_flow_run_context($run));
    $none = array('email' => '', 'contact_id' => 0, 'error' => lang('The run is about no order, form, booking, contact or e-mail with a person to write to.'));
    $out = lang('The flow\'s owner may not read the record the run is about, so no e-mail was sent.');
    list($kind, $id) = $source;

    if ($kind === '') {
        return $none;
    }

    if ($kind === 'mail') {
        $row = db_item("SELECT c.customer_email, c.contact_id, c.channel_id FROM ws_mail_conversations c WHERE c.id = '" . (int) $id . "'");
        $channel = is_array($row) ? db_item("SELECT * FROM ws_channels WHERE id = '" . (int) $row['channel_id'] . "'") : null;

        if (!is_array($row) || !is_array($channel) || !ws_can_read_channel(ws_rights_for_id((int) $owner['id']), $channel)) {
            return array('error' => $out) + $none;
        }

        $email = (string) $row['customer_email'];
        $contact_id = (int) $row['contact_id'];
    } else {
        if (!ws_step_record_get_read($owner, $kind, $id)) {
            return array('error' => $out) + $none;
        }

        switch ($kind) {
            case 'order':
                $row = db_item("SELECT billing_email_address AS email, contact_id FROM orders WHERE id = '" . (int) $id . "'");
                break;

            case 'form':
                require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
                $row = array('email' => (string) get_submitter_email_address((int) $id), 'contact_id' => (int) db_value("SELECT contact_id FROM forms WHERE id = '" . (int) $id . "'"));
                break;

            case 'booking':
                $row = db_item("SELECT email, 0 AS contact_id FROM bookings WHERE id = '" . (int) $id . "'");
                break;

            default:
                $row = db_item("SELECT email_address AS email, id AS contact_id FROM contacts WHERE id = '" . (int) $id . "'");
        }

        $email = is_array($row) ? (string) $row['email'] : '';
        $contact_id = is_array($row) ? (int) $row['contact_id'] : 0;
    }

    $email = trim($email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array('error' => lang('The person of the record the run is about has no e-mail address.')) + $none;
    }

    return array('email' => $email, 'contact_id' => $contact_id, 'error' => '');
}

/**
 * Why marketing e-mail may not go to an address, '' when it may: as a
 * campaign's commercial mail, a contact with the address agreed to it
 * (contacts.opt_in), İYS has no refusal for it, and no bounce or complaint
 * blocks it.
 *
 * @param string $email
 * @return string
 */
function ws_flow_mail_marketing_refusal($email)
{
    // The İYS check lives in the İYS client, which the flow run path does not
    // load on its own; without it the refusal would be skipped silently.
    require_once(PG_FUNCTIONS_DIR . '/includes/iys/client.php');

    if (!(bool) db_value("SELECT id FROM contacts WHERE email_address = '" . e((string) $email) . "' AND opt_in = '1' LIMIT 1")) {
        return lang('The person has not agreed to marketing e-mail, so none was sent.');
    }

    if (pg_iys_email_refused($email)) {
        return lang('The person refused marketing e-mail in İYS, so none was sent.');
    }

    if (function_exists('pg_mail_address_blocked') && pg_mail_address_blocked($email)) {
        return lang('The address bounced or complained before, so no marketing e-mail was sent.');
    }

    return '';
}

/**
 * A marketing e-mail's body with the campaigns' preferences and unsubscribe
 * link at its end: a line of plain text, or a paragraph of HTML. Pure but
 * for the link, given.
 *
 * @param string $body
 * @param string $format plain_text | html
 * @param string $url    pg_email_preferences_url(), '' for none
 * @return string
 */
function ws_flow_mail_unsubscribe_footer($body, $format, $url)
{
    if ((string) $url === '') {
        return (string) $body;
    }

    $label = lang('Update email preferences or unsubscribe');

    if ($format === 'html') {
        $footer = '<p style="font-size:12px;color:#666">' . h($label) . ': <a href="' . h($url) . '">' . h($url) . '</a></p>';

        return (stripos((string) $body, '</body>') !== false) ? (string) preg_replace('~</body>~i', $footer . '</body>', (string) $body, 1) : $body . $footer;
    }

    return rtrim((string) $body) . "\n\n" . $label . ":\n" . $url;
}
