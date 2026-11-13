<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Send an SMS box of a flow (step kind sms): a text message
 * to the mobile number of the person of the run's record (recipient
 * record_person: an order's customer, a booking's guest, a contact, the
 * contact of a submitted form or of a received e-mail) or to a typed
 * number (never a value of the run, as no recipient is). A purpose says why it writes:
 * info is a transactional message of its own kind (flow); marketing goes
 * only to a number whose person agreed to marketing SMS and İYS has not
 * refused, the rule the SMS campaigns keep (pg_sms_marketing_permission()),
 * and carries the sender identity and type label the layer appends. The
 * message is queued through pg_sms_enqueue(), never sent here; a person it
 * may not write to leaves the box done with nothing sent and the reason in
 * the run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest text the box keeps, before the marketing footer. */
define('WS_STEP_SMS_TEXT_MAX', 900);

/** Hours a queued marketing message may wait for the queue, as a campaign's. */
define('WS_STEP_SMS_MARKETING_HOURS', 6);

/**
 * Whom the box writes to: the number typed in to, or the person of the
 * run's record.
 *
 * @return string[]
 */
function ws_step_sms_recipients()
{
    return array('typed', 'record_person');
}

/**
 * Why the box writes: info (an answer, a reminder; no consent needed) or
 * marketing (consent needed).
 *
 * @return string[]
 */
function ws_step_sms_purposes()
{
    return array('info', 'marketing');
}

/**
 * The box's settings: to (a typed number), recipient,
 * purpose, text. Unknown words become the first. Pure.
 *
 * @param mixed $config
 * @return array to, recipient, purpose, text
 */
function ws_step_sms_normalize($config)
{
    $config = is_array($config) ? $config : array();
    $recipient = (string) ($config['recipient'] ?? '');
    $purpose = (string) ($config['purpose'] ?? '');

    return array(
        'to'        => ws_flow_line($config['to'] ?? '', 200),
        'recipient' => in_array($recipient, ws_step_sms_recipients(), true) ? $recipient : 'typed',
        'purpose'   => in_array($purpose, ws_step_sms_purposes(), true) ? $purpose : 'info',
        'text'      => ws_flow_lines($config['text'] ?? '', WS_STEP_SMS_TEXT_MAX),
    );
}

/**
 * What keeps the box from being published. A typed number is read as the
 * queue reads it. Pure but for lang().
 *
 * @param array  $config ws_step_sms_normalize()
 * @param string $name   the box's name in the sentences
 * @return string[]
 */
function ws_step_sms_problems($config, $name)
{
    $problems = array();

    if ($config['recipient'] === 'typed') {
        $to = trim((string) $config['to']);

        if ($to === '') {
            $problems[] = lang(array('string' => 'The box {var:1} needs a mobile number to write to.', 'vars' => $name));
        } elseif (pg_sms_number($to) === '') {
            $problems[] = lang(array('string' => 'The box {var:1} has a number an SMS cannot be sent to.', 'vars' => $name));
        }
    }

    if (trim((string) $config['text']) === '') {
        $problems[] = lang(array('string' => 'The box {var:1} needs the text of the message.', 'vars' => $name));
    }

    return $problems;
}

/**
 * The kind of the queued message (pg_sms_kinds()): marketing, or the flows'
 * own transactional kind. Pure.
 *
 * @param string $purpose
 * @return string
 */
function ws_step_sms_kind($purpose)
{
    return ((string) $purpose === 'marketing') ? 'marketing' : 'flow';
}

/**
 * Checks the box as its owner publishes it and as it runs: its settings,
 * that the site can send SMS, and for marketing the sender identity the
 * layer appends.
 *
 * @param array $viewer
 * @param array $config
 * @return array ok, error, paused, action
 */
function ws_step_sms_check($viewer, $config)
{
    $config = ws_step_sms_normalize($config);
    $problems = ws_step_sms_problems($config, lang('Send an SMS'));
    $refuse = function ($error) {
        return array('ok' => false, 'error' => (string) $error, 'paused' => false, 'action' => null);
    };

    if (!empty($problems)) {
        return $refuse($problems[0]);
    }

    if (!pg_sms_ready()) {
        return $refuse(lang('SMS is not set up on this site.'));
    }

    if (($config['purpose'] === 'marketing') && !pg_sms_marketing_identity_filled(pg_sms_marketing_settings())) {
        return $refuse(pg_sms_marketing_identity_missing());
    }

    return array('ok' => true, 'error' => '', 'paused' => false, 'action' => array('type' => 'sms') + $config);
}

/**
 * The phone number kept on the row of a run's record, as written there: an
 * order's billing phone (none for an order a marketplace sold, whose buyer
 * the marketplace tells, pg_sms_order_texted()), a booking's phone, a
 * contact's mobile phone. Pure.
 *
 * @param string     $kind order, booking, contact
 * @param array|null $row
 * @return string
 */
function ws_step_sms_phone_of($kind, $row)
{
    if (!is_array($row)) {
        return '';
    }

    switch ((string) $kind) {
        case 'order':
            return (((string) ($row['type'] ?? '') === 'marketplace')) ? '' : trim((string) ($row['billing_phone_number'] ?? ''));

        case 'booking':
            return trim((string) ($row['phone'] ?? ''));

        case 'contact':
            return trim((string) ($row['mobile_phone'] ?? ''));
    }

    return '';
}

/**
 * The mobile number of the person of a run's record the flow's owner may
 * reach (ws_flow_mail_person_source() names the record): a submitted form
 * and a received e-mail give their contact's.
 *
 * @param array $owner the flow's owner, on the staff
 * @param array $run   ws_flow_runs row
 * @return array number (E.164 digits), error ('' when found)
 */
function ws_step_sms_person($owner, $run)
{
    list($kind, $id) = ws_flow_mail_person_source((string) ($run['subject_type'] ?? ''), (int) ($run['subject_id'] ?? 0), ws_flow_run_context($run));
    $none = array('number' => '', 'error' => lang('The run is about no order, form, booking, contact or e-mail with a person to write to.'));
    $out = array('number' => '', 'error' => lang('The flow\'s owner may not read the record the run is about, so no SMS was sent.'));

    if ($kind === '') {
        return $none;
    }

    if ($kind === 'mail') {
        $row = db_item("SELECT contact_id, channel_id FROM ws_mail_conversations WHERE id = '" . (int) $id . "'");
        $channel = is_array($row) ? db_item("SELECT * FROM ws_channels WHERE id = '" . (int) $row['channel_id'] . "'") : null;

        if (!is_array($row) || !is_array($channel) || !ws_can_read_channel(ws_rights_for_id((int) $owner['id']), $channel)) {
            return $out;
        }

        $kind = 'contact';
        $id = (int) $row['contact_id'];
    } else {
        if (!ws_step_record_get_read($owner, $kind, $id)) {
            return $out;
        }

        if ($kind === 'form') {
            $kind = 'contact';
            $id = (int) db_value("SELECT contact_id FROM forms WHERE id = '" . (int) $id . "'");
        }
    }

    switch ($kind) {
        case 'order':
            $row = db_item("SELECT billing_phone_number, type FROM orders WHERE id = '" . (int) $id . "'");
            break;

        case 'booking':
            $row = db_item("SELECT phone FROM bookings WHERE id = '" . (int) $id . "'");
            break;

        default:
            $row = ((int) $id > 0) ? db_item("SELECT mobile_phone FROM contacts WHERE id = '" . (int) $id . "'") : null;
    }

    $number = pg_sms_number(ws_step_sms_phone_of($kind, is_array($row) ? $row : null));

    if ($number === '') {
        return array('number' => '', 'error' => lang('The person of the record the run is about has no mobile number an SMS can be sent to.'));
    }

    return array('number' => $number, 'error' => '');
}

/**
 * Why a marketing SMS may not go to a number, '' when it may: the consent
 * rule the SMS campaigns keep (pg_sms_marketing_permission()).
 *
 * @param string $number E.164 digits
 * @return string
 */
function ws_step_sms_marketing_refusal($number)
{
    $refusal = pg_sms_marketing_permission((string) $number);

    return ($refusal === '') ? '' : lang(array('string' => '{var:1} No SMS was sent.', 'vars' => $refusal));
}

/**
 * The queue's reference of the box's message: one per run, box and pass,
 * so a box that runs again after a stop does not queue it twice. Only the
 * characters a reference takes, at most 64 of them. Pure.
 *
 * @param int    $run_id
 * @param string $node_id
 * @param int    $pass
 * @return string
 */
function ws_step_sms_reference($run_id, $node_id, $pass)
{
    $node = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $node_id);

    return substr('flow:' . (int) $run_id . ':' . (int) $pass . ':' . $node, 0, 64);
}

/**
 * The options the box queues its message with: its reference, and for
 * marketing the hours it may wait, as a campaign's message, so a promotion
 * is not sent the next morning after a stalled queue. Pure.
 *
 * @param string $purpose
 * @param string $reference ws_step_sms_reference()
 * @param int    $now
 * @return array
 */
function ws_step_sms_queue_options($purpose, $reference, $now)
{
    $options = array('reference' => (string) $reference);

    if ((string) $purpose === 'marketing') {
        $options['expires_at'] = (int) $now + WS_STEP_SMS_MARKETING_HOURS * 3600;
    }

    return $options;
}

/**
 * Queues the box's message: the number read, the text with its
 * placeholders filled, marketing only with consent.
 *
 * @param array  $action    ws_step_sms_check(), its texts filled
 * @param array  $context   what the placeholders read (ws_scheduled_fill())
 * @param string $reference ws_step_sms_reference()
 * @return array ok, text, data (sent)
 */
function ws_step_sms_do($action, $context, $reference)
{
    $number = pg_sms_number((string) $action['to']);

    if ($number === '') {
        return array('ok' => false, 'text' => lang('This is not a mobile number an SMS can be sent to.'), 'data' => array('sent' => 0));
    }

    if ($action['purpose'] === 'marketing') {
        $refusal = ws_step_sms_marketing_refusal($number);

        if ($refusal !== '') {
            return array('ok' => true, 'text' => $refusal, 'data' => array('sent' => 0));
        }
    }

    $text = function_exists('ws_scheduled_fill') ? ws_scheduled_fill((string) $action['text'], $context) : (string) $action['text'];
    $queued = pg_sms_enqueue(pg_sms_dial($number), $text, ws_step_sms_kind($action['purpose']), ws_step_sms_queue_options($action['purpose'], $reference, time()));

    if (!$queued['ok']) {
        return array('ok' => false, 'text' => lang(array('string' => 'The SMS was not queued: {var:1}', 'vars' => (string) $queued['error'])), 'data' => array('sent' => 0));
    }

    return array('ok' => true, 'text' => lang(array('string' => 'SMS queued to {var:1}.', 'vars' => ws_step_sms_number_shown($number))), 'data' => array('sent' => 1));
}

/**
 * A number as a run's sentence names it: its last four digits only, so the
 * run's history does not keep whole numbers. Pure.
 *
 * @param string $number E.164 digits
 * @return string
 */
function ws_step_sms_number_shown($number)
{
    $number = (string) $number;

    return (strlen($number) > 4) ? '…' . substr($number, -4) : $number;
}

/**
 * What the box does, in words, for a try. Nothing is queued in a try.
 *
 * @param array $action ws_step_sms_check()
 * @return string
 */
function ws_step_sms_text($action)
{
    $marketing = ((string) ($action['purpose'] ?? '') === 'marketing');

    if ((string) ($action['recipient'] ?? '') === 'record_person') {
        return $marketing
            ? lang('Queues a marketing SMS to the person of the record the run is about, if they agreed to it.')
            : lang('Queues an SMS to the person of the record the run is about.');
    }

    $number = pg_sms_number((string) ($action['to'] ?? ''));
    $to = ($number !== '') ? ws_step_sms_number_shown($number) : (string) ($action['to'] ?? '');

    return $marketing
        ? lang(array('string' => 'Queues a marketing SMS to {var:1}, if its person agreed to it.', 'vars' => $to))
        : lang(array('string' => 'Queues an SMS to {var:1}.', 'vars' => $to));
}
