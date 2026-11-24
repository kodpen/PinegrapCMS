<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Satisfaction surveys on their way out. pg_announce() hands every event to
 * pg_feedback_heard(): a booking marked done, a ticket checked in, a course
 * completed, a form sent, an order delivered, a mail conversation closed or
 * a walk-in queue number served queues one feedback_responses row for each
 * switched-on survey that follows it, due after the survey's delay inside the sending hours (09:00
 * to 20:00 site time). The touchpoint's own code only announces; nothing
 * here sends.
 *
 * The general job's pass (pg_feedback_job_run()) queues the periodic NPS
 * page by page, marks a survey left unanswered for 30 days, and, inside the
 * sending hours, sends what is due and the one reminder after 3 days. Before
 * a send it looks again: a cancelled booking, a cancelled or refunded order,
 * a conversation opened again, a switched-off survey, a person asked in the
 * last 30 days, a staff address, "Don't ask me again", a bounced address
 * or, for a commercial survey, missing marketing consent or an İYS refusal
 * cancels the row. The message itself is pg_feedback_message_send() in
 * includes/fn/feedback_answer.php, loaded by pg_feedback_sender().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** feedback_responses.status */
define('PG_FEEDBACK_QUEUED', 0);
define('PG_FEEDBACK_SENT', 1);
define('PG_FEEDBACK_ANSWERED', 2);
define('PG_FEEDBACK_CANCELLED', 3);
define('PG_FEEDBACK_NO_ANSWER', 4);

/** The sending hours, site time: from 09:00, before 20:00. */
define('PG_FEEDBACK_HOUR_FROM', 9);
define('PG_FEEDBACK_HOUR_TO', 20);

/** The one reminder goes this many days after the survey. */
define('PG_FEEDBACK_REMIND_DAYS', 3);

/** A form survey waits this long for its field to take the chosen value. */
define('PG_FEEDBACK_FORM_WAIT_DAYS', 90);

/** Seconds between two looks at a form survey still waiting for its value. */
define('PG_FEEDBACK_WAIT_STEP', 21600);

/** Rows sent, and reminders, per pass. */
define('PG_FEEDBACK_PASS_ROWS', 200);

/** Contacts queued per periodic survey and pass. */
define('PG_FEEDBACK_PERIODIC_PAGE', 500);

/**
 * The record an announced event is about, for the survey touchpoint that
 * follows it; null for any other event. Pure.
 *
 * @param string $event
 * @param array  $data the event's payload
 * @return array|null touchpoint, type (feedback_responses.record_type), id
 */
function pg_feedback_event_record($event, $data)
{
    $map = array(
        'booking.completed'     => array('booking', 'booking', 'id'),
        'ticket.checked_in'     => array('ticket', 'ticket', 'id'),
        'course.completed'      => array('course', 'course', 'id'),
        'form.submitted'        => array('form', 'form', 'id'),
        'order.delivered'       => array('delivery', 'order', 'id'),
        'workspace.mail.closed' => array('mail', 'conversation', 'conversation_id'),
        'queue.served'          => array('queue', 'queue', 'id'),
    );
    $event = (string) $event;

    if (!isset($map[$event])) {
        return null;
    }

    $id = (int) ($data[$map[$event][2]] ?? 0);

    return ($id > 0) ? array('touchpoint' => $map[$event][0], 'type' => $map[$event][1], 'id' => $id) : null;
}

/**
 * Whether a survey's filter takes this record: its service, event, course,
 * form, mail box or queue place, 0 for any. Pure.
 *
 * @param string $touchpoint
 * @param array  $filter     the survey's filter
 * @param int    $subject_id the record's service, event, course, form page, box or place
 * @return bool
 */
function pg_feedback_filter_takes($touchpoint, $filter, $subject_id)
{
    $keys = array('booking' => 'service', 'ticket' => 'event', 'course' => 'course', 'form' => 'form', 'mail' => 'box', 'queue' => 'place');

    if (!isset($keys[$touchpoint])) {
        return true;
    }

    $wanted = (int) ($filter[$keys[$touchpoint]] ?? 0);

    return ($wanted === 0) || ($wanted === (int) $subject_id);
}

/**
 * The first moment at or after $at inside the sending hours of the zone:
 * the time itself between 09:00 and 20:00, else the next 09:00. Pure.
 *
 * @param int    $at
 * @param string $zone a time zone name (the site's)
 * @return int
 */
function pg_feedback_send_time($at, $zone)
{
    try {
        $time_zone = new DateTimeZone((string) $zone);
    } catch (\Exception $exception) {
        $time_zone = new DateTimeZone('UTC');
    }

    $moment = (new DateTime('@' . (int) $at))->setTimezone($time_zone);
    $hour = (int) $moment->format('G');

    if (($hour >= PG_FEEDBACK_HOUR_FROM) && ($hour < PG_FEEDBACK_HOUR_TO)) {
        return (int) $at;
    }

    if ($hour >= PG_FEEDBACK_HOUR_TO) {
        $moment->modify('+1 day');
    }

    $moment->setTime(PG_FEEDBACK_HOUR_FROM, 0, 0);

    return $moment->getTimestamp();
}

/**
 * A new row for one survey and record: the recipient by the survey's
 * channel (an address for e-mail, a number in pg_sms_number() form for SMS
 * and WhatsApp), its person hash, and due after the delay inside the
 * sending hours. Null when the record has nobody to ask by that channel.
 * Pure.
 *
 * @param array  $survey pg_feedback_survey_decode()
 * @param array  $record type, id
 * @param array  $facts  pg_feedback_record_facts()
 * @param int    $now
 * @param string $zone
 * @return array|null column => value
 */
function pg_feedback_queue_row(array $survey, array $record, array $facts, $now, $zone)
{
    $recipient = ($survey['channel'] === 'email') ? trim((string) ($facts['email'] ?? '')) : (string) ($facts['phone'] ?? '');

    if (($survey['channel'] === 'email') && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    if (($recipient === '') || (mb_strlen($recipient) > 190)) {
        return null;
    }

    return array(
        'survey_id'     => (int) $survey['id'],
        'contact_id'    => max(0, (int) ($facts['contact_id'] ?? 0)),
        'person_hash'   => pg_feedback_person_hash($recipient),
        'recipient'     => $recipient,
        'record_type'   => (string) $record['type'],
        'record_id'     => (int) $record['id'],
        'subject_id'    => max(0, (int) ($facts['subject_id'] ?? 0)),
        'staff_user_id' => max(0, (int) ($facts['staff_user_id'] ?? 0)),
        'channel'       => (string) $survey['channel'],
        'status'        => PG_FEEDBACK_QUEUED,
        'due_at'        => pg_feedback_send_time((int) $now + 60 * (int) $survey['delay_minutes'], $zone),
        'created_at'    => (int) $now,
    );
}

/**
 * What a due row's record says now: 'send', 'wait' (a form whose field has
 * not taken the chosen value yet, for at most 90 days) or 'cancel' (the
 * record is gone, the booking is no longer done, the ticket void, the order
 * cancelled or its review request went in the last 30 days, the
 * conversation opened again, the queue number's personal fields cleared).
 * Pure.
 *
 * @param string $type    feedback_responses.record_type
 * @param array  $facts   pg_feedback_record_facts(); array() when the record is gone
 * @param array  $filter  the survey's filter
 * @param int    $created_at the row's
 * @param int    $now
 * @return string
 */
function pg_feedback_record_verdict($type, array $facts, array $filter, $created_at, $now)
{
    if (!$facts) {
        return 'cancel';
    }

    $state = (string) ($facts['state'] ?? '');

    switch ((string) $type) {
        case 'booking':
            return ($state === 'done') ? 'send' : 'cancel';
        case 'ticket':
            return ($state === 'void') ? 'cancel' : 'send';
        case 'order':
            return (($state === 'cancelled') || !empty($facts['review_requested'])) ? 'cancel' : 'send';
        case 'conversation':
            return ($state === 'closed') ? 'send' : 'cancel';
        case 'queue':
            // A number whose personal fields the keep period cleared asks nobody.
            return (($state === 'served') && empty($facts['cleared_at'])) ? 'send' : 'cancel';
        case 'form':
            if ((int) ($filter['field'] ?? 0) <= 0) {
                return 'send';
            }

            if (trim((string) ($facts['value'] ?? '')) === trim((string) ($filter['value'] ?? ''))) {
                return 'send';
            }

            return ((int) $now - (int) $created_at < PG_FEEDBACK_FORM_WAIT_DAYS * 86400) ? 'wait' : 'cancel';
    }

    return 'send';
}

/**
 * Why a survey may not go to this recipient under its purpose, '' when it
 * may. Every survey: never to an e-mail address that bounced or complained
 * ('blocked'). A commercial one (the periodic NPS, or one switched to
 * commercial) also needs marketing consent: by e-mail a contact of the
 * address agreed ('consent') and İYS holds no refusal ('iys'); by SMS or
 * WhatsApp the number's marketing permission ('consent'). A service survey
 * asks for no consent and carries no promotion. Pure.
 *
 * @param int    $purpose 0 service message, 1 commercial
 * @param string $channel email, sms, whatsapp
 * @param array  $facts   blocked, opted_in, iys_refused, number_refusal (sentence, '' for none)
 * @return string '', blocked, consent or iys
 */
function pg_feedback_consent_refusal($purpose, $channel, array $facts)
{
    if (($channel === 'email') && !empty($facts['blocked'])) {
        return 'blocked';
    }

    if ((int) $purpose !== 1) {
        return '';
    }

    if ($channel === 'email') {
        if (empty($facts['opted_in'])) {
            return 'consent';
        }

        return empty($facts['iys_refused']) ? '' : 'iys';
    }

    return ((string) ($facts['number_refusal'] ?? '') === '') ? '' : 'consent';
}

/**
 * Whether a sent survey's one reminder is due: sent 3 days ago or more,
 * not answered, not reminded, its link still valid. Pure.
 *
 * @param array $row feedback_responses
 * @param int   $now
 * @return bool
 */
function pg_feedback_reminder_due(array $row, $now)
{
    $sent_at = (int) ($row['sent_at'] ?? 0);
    $age = (int) $now - $sent_at;

    return ((int) ($row['status'] ?? -1) === PG_FEEDBACK_SENT) && ($sent_at > 0) && ((int) ($row['reminded_at'] ?? 0) === 0)
        && ((int) ($row['answered_at'] ?? 0) === 0) && ($age >= PG_FEEDBACK_REMIND_DAYS * 86400) && ($age < PG_FEEDBACK_TOKEN_DAYS * 86400);
}

/**
 * Whether a periodic survey starts a new round: never asked, or its months
 * have passed since the last round started. Pure.
 *
 * @param int $last_at feedback_surveys.last_periodic_at
 * @param int $every   months
 * @param int $now
 * @return bool
 */
function pg_feedback_periodic_due($last_at, $every, $now)
{
    $last_at = (int) $last_at;

    if ($last_at <= 0) {
        return true;
    }

    $next = (new DateTime('@' . $last_at))->modify('+' . max(1, (int) $every) . ' months')->getTimestamp();

    return (int) $now >= $next;
}

/**
 * Whether the tables are there. Read once a request.
 *
 * @return bool
 */
function pg_feedback_sends_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('feedback_responses') && pg_schema_has('feedback_surveys');
    }

    return $ready;
}

/**
 * The switched-on surveys of a touchpoint, decoded; read once a request.
 *
 * @param string $touchpoint
 * @return array[]
 */
function pg_feedback_live_surveys($touchpoint)
{
    static $read = array();

    if (!isset($read[$touchpoint])) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_surveys.php');

        $read[$touchpoint] = array_map('pg_feedback_survey_decode', (array) db_items(
            "SELECT * FROM feedback_surveys
            WHERE (enabled = 1) AND (archived_at = 0) AND (touchpoint = '" . e((string) $touchpoint) . "')
            ORDER BY id"));
    }

    return $read[$touchpoint];
}

/**
 * Hears every announced event: queues the surveys that follow it, and
 * cancels the waiting surveys of a cancelled booking. Quiet: a failure is
 * logged and never reaches the code that announced.
 *
 * @param string $event
 * @param array  $data
 * @return void
 */
function pg_feedback_heard($event, $data)
{
    $record = pg_feedback_event_record($event, (array) $data);

    if (((string) $event !== 'booking.cancelled') && ($record === null)) {
        return;
    }

    if (!pg_feedback_sends_ready()) {
        return;
    }

    try {
        if ((string) $event === 'booking.cancelled') {
            pg_feedback_cancel_record('booking', (int) ($data['id'] ?? 0));

            return;
        }

        $surveys = pg_feedback_live_surveys($record['touchpoint']);

        if (!$surveys || (pg_feedback_key() === '')) {
            return;
        }

        $facts = pg_feedback_record_facts($record['type'], $record['id'], (array) $data);

        foreach ($surveys as $survey) {
            if ($facts && pg_feedback_filter_takes($record['touchpoint'], $survey['filter'], $facts['subject_id'])) {
                pg_feedback_queue($survey, $record, $facts, time());
            }
        }
    } catch (\Throwable $exception) {
        log_activity(pg_log_lang(array(
            'string' => 'A satisfaction survey could not be queued for {var:1}: {var:2}',
            'vars'   => array((string) $event, $exception->getMessage()),
        )), 'UNKNOWN');
    }
}

/**
 * Stores a queued row and, in the same call, the hash of its answer token
 * (issued at its due time, so the link holds 30 days from the send). A
 * person with a row of the same survey still waiting gets no second one.
 *
 * @param array $survey
 * @param array $record type, id
 * @param array $facts
 * @param int   $now
 * @return int the row's id, 0 when none was queued
 */
function pg_feedback_queue(array $survey, array $record, array $facts, $now)
{
    $row = pg_feedback_queue_row($survey, $record, $facts, $now, date_default_timezone_get());
    $key = pg_feedback_key();

    if (($row === null) || ($key === '')) {
        return 0;
    }

    $waiting = db_value(
        "SELECT id FROM feedback_responses
        WHERE (person_hash = '" . e($row['person_hash']) . "') AND (survey_id = '" . (int) $row['survey_id'] . "') AND (status = " . PG_FEEDBACK_QUEUED . ")
        LIMIT 1");

    if ($waiting) {
        return 0;
    }

    $values = array();

    foreach ($row as $value) {
        $values[] = "'" . e((string) $value) . "'";
    }

    if (!db("INSERT INTO feedback_responses (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(', ', $values) . ")")) {
        return 0;
    }

    $id = (int) mysqli_insert_id(db::$con);
    pg_feedback_set_due($id, $row['due_at'], $key);

    return $id;
}

/**
 * Moves a row's due time and writes the hash of the answer token issued at
 * it, so the stored hash always matches the link the send makes.
 *
 * @param int    $id
 * @param int    $due_at
 * @param string $key
 * @return void
 */
function pg_feedback_set_due($id, $due_at, $key)
{
    $token = pg_feedback_token_make('answer', (int) $id, (int) $due_at, $key);

    db("UPDATE feedback_responses
        SET due_at = '" . (int) $due_at . "', token_hash = " . (($token === '') ? 'NULL' : "'" . e(pg_feedback_token_hash($token)) . "'") . "
        WHERE id = '" . (int) $id . "'");
}

/**
 * Cancels the surveys still waiting for a record: a cancelled booking, a
 * cancelled or fully refunded order, a conversation opened again. Sent and
 * answered rows stay.
 *
 * @param string $type booking, order, conversation
 * @param int    $id
 * @return void
 */
function pg_feedback_cancel_record($type, $id)
{
    if (((int) $id <= 0) || !pg_feedback_sends_ready()) {
        return;
    }

    db("UPDATE feedback_responses SET status = " . PG_FEEDBACK_CANCELLED . "
        WHERE (record_type = '" . e((string) $type) . "') AND (record_id = '" . (int) $id . "') AND (status = " . PG_FEEDBACK_QUEUED . ")");
}

/**
 * What a record says for its surveys: email, phone (pg_sms_number() form),
 * contact_id, subject_id (service, event, course, form page or mail box),
 * staff_user_id (a booking's staff user), state (booking status, ticket
 * state, order status, conversation status), and for a form the value of
 * $field_id, for an order whether its review request went in the last 30
 * days. array() when the record is gone.
 *
 * @param string $type
 * @param int    $id
 * @param array  $data     the event's payload (a course's user)
 * @param int    $field_id a form's field
 * @return array
 */
function pg_feedback_record_facts($type, $id, array $data = array(), $field_id = 0)
{
    $id = (int) $id;
    $row = null;

    switch ((string) $type) {
        case 'booking':
            $row = db_item(
                "SELECT bookings.email, bookings.phone, bookings.service_id AS subject_id, bookings.status AS state, booking_staff.user_id AS staff_user_id
                FROM bookings
                LEFT JOIN booking_staff ON booking_staff.id = bookings.staff_id
                WHERE bookings.id = '" . $id . "'");
            break;

        case 'ticket':
            $row = db_item(
                "SELECT orders.billing_email_address AS email, orders.billing_phone_number AS phone, orders.contact_id,
                    event_tickets.calendar_event_id AS subject_id, event_tickets.state
                FROM event_tickets
                JOIN orders ON orders.id = event_tickets.order_id
                WHERE event_tickets.id = '" . $id . "'");
            break;

        case 'course':
            $row = db_item("SELECT user_email AS email, user_contact AS contact_id, '" . $id . "' AS subject_id FROM user WHERE user_id = '" . (int) ($data['user_id'] ?? 0) . "'");
            break;

        case 'form':
            $row = db_item(
                "SELECT contacts.email_address AS email, contacts.mobile_phone AS phone, forms.contact_id, forms.page_id AS subject_id, user.user_email
                FROM forms
                LEFT JOIN contacts ON contacts.id = forms.contact_id
                LEFT JOIN user ON user.user_id = forms.user_id
                WHERE forms.id = '" . $id . "'");

            if (is_array($row)) {
                $row['email'] = ((string) $row['email'] !== '') ? $row['email'] : $row['user_email'];
                $row['value'] = ($field_id > 0) ? (string) db_value("SELECT data FROM form_data WHERE form_id = '" . $id . "' AND form_field_id = '" . (int) $field_id . "' LIMIT 1") : '';
            }
            break;

        case 'order':
            $row = db_item("SELECT billing_email_address AS email, billing_phone_number AS phone, contact_id, 0 AS subject_id, status AS state FROM orders WHERE id = '" . $id . "'");

            if (is_array($row)) {
                $row['review_requested'] = (bool) db_value(
                    "SELECT id FROM email_campaigns
                    WHERE (action = 'order_review_request') AND (order_id = '" . $id . "') AND (status <> 'cancelled')
                        AND (created_timestamp >= '" . (time() - PG_FEEDBACK_REST_DAYS * 86400) . "')
                    LIMIT 1");
            }
            break;

        case 'conversation':
            $row = pg_schema_has('ws_mail_conversations') ? db_item(
                "SELECT ws_mail_conversations.customer_email AS email, contacts.mobile_phone AS phone, ws_mail_conversations.contact_id,
                    ws_mail_conversations.box_id AS subject_id, ws_mail_conversations.status AS state
                FROM ws_mail_conversations
                LEFT JOIN contacts ON contacts.id = ws_mail_conversations.contact_id
                WHERE ws_mail_conversations.id = '" . $id . "'") : null;
            break;

        case 'queue':
            $row = (function_exists('pg_waiting_ready') && pg_waiting_ready()) ? db_item(
                "SELECT email, phone, contact_id, place_id AS subject_id, staff_user_id, state, cleared_at
                FROM waiting_tickets
                WHERE id = '" . $id . "'") : null;
            break;

        case 'periodic':
            $row = db_item("SELECT email_address AS email, mobile_phone AS phone, id AS contact_id, 0 AS subject_id FROM contacts WHERE id = '" . (int) ($data['contact_id'] ?? 0) . "'");
            break;
    }

    if (!is_array($row)) {
        return array();
    }

    $row['phone'] = pg_sms_number((string) ($row['phone'] ?? ''));

    if (empty($row['contact_id']) && ((string) ($row['email'] ?? '') !== '')) {
        $row['contact_id'] = (int) db_value("SELECT id FROM contacts WHERE email_address = '" . e((string) $row['email']) . "' ORDER BY id LIMIT 1");
    }

    return $row;
}

/**
 * The general job's pass: the periodic rounds, the surveys left unanswered,
 * and, inside the sending hours, the due surveys and the reminders.
 *
 * @param int $now
 * @return void
 */
function pg_feedback_job_run($now)
{
    if (!pg_feedback_sends_ready()) {
        return;
    }

    pg_feedback_periodic_run($now);

    // A link no longer valid: the survey went unanswered.
    db("UPDATE feedback_responses SET status = " . PG_FEEDBACK_NO_ANSWER . "
        WHERE (status = " . PG_FEEDBACK_SENT . ") AND (sent_at < '" . ((int) $now - PG_FEEDBACK_TOKEN_DAYS * 86400) . "')");

    // A low score's follow-up still open after 48 hours
    // (includes/fn/feedback_followup.php).
    pg_feedback_escalation_run($now);

    $sender = pg_feedback_sender();

    if (($sender === null) || (pg_feedback_send_time($now, date_default_timezone_get()) !== (int) $now)) {
        return;
    }

    // A queued row whose link has run out can no longer be answered: a
    // WhatsApp survey's rows wait for its template and may wait that long.
    db("UPDATE feedback_responses SET status = " . PG_FEEDBACK_CANCELLED . "
        WHERE (status = " . PG_FEEDBACK_QUEUED . ") AND (token_hash IS NOT NULL) AND (due_at < '" . ((int) $now - PG_FEEDBACK_TOKEN_DAYS * 86400) . "')");

    // A WhatsApp survey whose template cannot be sent keeps its rows waiting.
    $due = (array) db_items(
        "SELECT * FROM feedback_responses
        WHERE (status = " . PG_FEEDBACK_QUEUED . ") AND (due_at <= '" . (int) $now . "') AND (token_hash IS NOT NULL) AND " . pg_feedback_due_channel_sql(pg_feedback_whatsapp_ready_surveys()) . "
        ORDER BY due_at
        LIMIT " . PG_FEEDBACK_PASS_ROWS);

    foreach ($due as $row) {
        pg_feedback_send_due($row, $now, $sender);
    }

    $sent = (array) db_items(
        "SELECT * FROM feedback_responses
        WHERE (status = " . PG_FEEDBACK_SENT . ") AND (reminded_at = 0) AND (sent_at <= '" . ((int) $now - PG_FEEDBACK_REMIND_DAYS * 86400) . "')
        ORDER BY sent_at
        LIMIT " . PG_FEEDBACK_PASS_ROWS);

    foreach ($sent as $row) {
        if (!pg_feedback_reminder_due($row, $now)) {
            continue;
        }

        $survey = pg_feedback_survey_of_row($row);

        // Claimed first, so two overlapping passes never both remind.
        db("UPDATE feedback_responses SET reminded_at = '" . (int) $now . "' WHERE (id = '" . (int) $row['id'] . "') AND (reminded_at = 0)");

        if ($survey && (mysqli_affected_rows(db::$con) === 1)) {
            call_user_func($sender, $row, $survey, true);
        }
    }
}

/**
 * The function that sends a survey's message (its row, its survey, whether
 * it is the reminder; true when it went), loaded from
 * includes/fn/feedback_answer.php; null while none is there.
 *
 * @return callable|null
 */
function pg_feedback_sender()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_answer.php');

    return function_exists('pg_feedback_message_send') ? 'pg_feedback_message_send' : null;
}

/**
 * A row's survey, null when it was deleted, archived or switched off.
 *
 * @param array $row
 * @return array|null
 */
function pg_feedback_survey_of_row(array $row)
{
    static $read = array();

    $id = (int) $row['survey_id'];

    if (!array_key_exists($id, $read)) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_surveys.php');

        $survey = db_item("SELECT * FROM feedback_surveys WHERE id = '" . $id . "'");
        $read[$id] = (is_array($survey) && ((int) $survey['enabled'] === 1) && ((int) $survey['archived_at'] === 0)) ? pg_feedback_survey_decode($survey) : null;
    }

    return $read[$id];
}

/**
 * One due row: cancelled, moved on or sent, after looking again at its
 * survey, its record, the person and their consent.
 *
 * @param array    $row
 * @param int      $now
 * @param callable $sender pg_feedback_sender()
 * @return string sent, cancelled, waiting or lost (another pass took it)
 */
function pg_feedback_send_due(array $row, $now, $sender)
{
    $cancel = function () use ($row) {
        db("UPDATE feedback_responses SET status = " . PG_FEEDBACK_CANCELLED . " WHERE (id = '" . (int) $row['id'] . "') AND (status = " . PG_FEEDBACK_QUEUED . ")");

        return 'cancelled';
    };
    $survey = pg_feedback_survey_of_row($row);

    if ($survey === null) {
        return $cancel();
    }

    $facts = pg_feedback_record_facts($row['record_type'], $row['record_id'], array('contact_id' => $row['contact_id']), (int) ($survey['filter']['field'] ?? 0));
    $verdict = pg_feedback_record_verdict($row['record_type'], $facts, $survey['filter'], $row['created_at'], $now);

    if ($verdict === 'cancel') {
        return $cancel();
    }

    if ($verdict === 'wait') {
        pg_feedback_set_due($row['id'], pg_feedback_send_time((int) $now + PG_FEEDBACK_WAIT_STEP, date_default_timezone_get()), pg_feedback_key());

        return 'waiting';
    }

    $history = db_item(
        "SELECT MAX(IF(status IN (" . PG_FEEDBACK_SENT . ", " . PG_FEEDBACK_ANSWERED . ", " . PG_FEEDBACK_NO_ANSWER . "), sent_at, 0)) AS last_asked_at, MAX(stopped) AS stopped
        FROM feedback_responses
        WHERE person_hash = '" . e((string) $row['person_hash']) . "'");
    $rules = array('staff' => pg_feedback_staff_address($row['recipient']) ? array($row['recipient']) : array(), 'test' => array());

    if (pg_feedback_fatigue($row['recipient'], (array) $history, $rules, $now) !== '') {
        return $cancel();
    }

    if (pg_feedback_consent_refusal($survey['purpose'], $row['channel'], pg_feedback_consent_facts($survey['purpose'], $row['channel'], $row['recipient'])) !== '') {
        return $cancel();
    }

    // Claimed first, so two overlapping passes never both send.
    db("UPDATE feedback_responses SET status = " . PG_FEEDBACK_SENT . ", sent_at = '" . (int) $now . "' WHERE (id = '" . (int) $row['id'] . "') AND (status = " . PG_FEEDBACK_QUEUED . ")");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return 'lost';
    }

    if (!call_user_func($sender, array_merge($row, array('status' => PG_FEEDBACK_SENT, 'sent_at' => (int) $now)), $survey, false)) {
        db("UPDATE feedback_responses SET status = " . PG_FEEDBACK_CANCELLED . " WHERE id = '" . (int) $row['id'] . "'");

        return 'cancelled';
    }

    return 'sent';
}

/**
 * Whether an address belongs to a staff account: a panel role, or a member
 * given a right to manage something (pg_privacy_rule_is_staff()).
 *
 * @param string $recipient
 * @return bool
 */
function pg_feedback_staff_address($recipient)
{
    if (strpos((string) $recipient, '@') === false) {
        return false;
    }

    $user = db_item("SELECT * FROM user WHERE user_email = '" . e((string) $recipient) . "' LIMIT 1");

    return is_array($user) && pg_privacy_rule_is_staff($user);
}

/**
 * The facts pg_feedback_consent_refusal() weighs, read for one recipient:
 * the bounce block of an address, and for a commercial survey the
 * address's marketing consent and İYS refusal or the number's permission.
 *
 * @param int    $purpose
 * @param string $channel
 * @param string $recipient
 * @return array
 */
function pg_feedback_consent_facts($purpose, $channel, $recipient)
{
    $facts = array();

    if ($channel === 'email') {
        $facts['blocked'] = pg_mail_address_blocked($recipient);
    }

    if ((int) $purpose !== 1) {
        return $facts;
    }

    if ($channel === 'email') {
        require_once(PG_FUNCTIONS_DIR . '/includes/iys/client.php');

        $facts['opted_in'] = (bool) db_value("SELECT id FROM contacts WHERE email_address = '" . e((string) $recipient) . "' AND opt_in = '1' LIMIT 1");
        $facts['iys_refused'] = pg_iys_email_refused($recipient);
    } else {
        $facts['number_refusal'] = pg_sms_marketing_permission((string) $recipient);
    }

    return $facts;
}

/**
 * The periodic NPS: a due survey starts a round (claimed on
 * last_periodic_at), then each pass queues one page of the contacts active
 * in its last months, in contact id order; the round's place is kept in
 * config_text feedback_periodic and claimed before each page. By e-mail
 * only contacts who agreed to marketing e-mail are read; the send checks
 * consent again.
 *
 * @param int $now
 * @return void
 */
function pg_feedback_periodic_run($now)
{
    $surveys = pg_feedback_live_surveys('periodic');
    $read = pg_config_text('feedback_periodic');
    $stored = json_decode($read, true);
    $rounds = array();

    // A round of a survey switched off or archived meanwhile is dropped.
    foreach ($surveys as $survey) {
        if (is_array($stored) && isset($stored[(string) $survey['id']]['since'], $stored[(string) $survey['id']]['after'])) {
            $rounds[(string) $survey['id']] = $stored[(string) $survey['id']];
        }
    }

    foreach ($surveys as $survey) {
        $id = (string) $survey['id'];

        if (!isset($rounds[$id])) {
            $last = (int) db_value("SELECT last_periodic_at FROM feedback_surveys WHERE id = '" . (int) $id . "'");

            if (!pg_feedback_periodic_due($last, $survey['filter']['every'], $now)) {
                continue;
            }

            db("UPDATE feedback_surveys SET last_periodic_at = '" . (int) $now . "' WHERE (id = '" . (int) $id . "') AND (last_periodic_at = '" . $last . "')");

            if (mysqli_affected_rows(db::$con) !== 1) {
                continue;
            }

            $rounds[$id] = array('since' => (int) $now - (int) $survey['filter']['active'] * 30 * 86400, 'after' => 0);
        }

        $contacts = pg_feedback_periodic_page($survey['channel'], (int) $rounds[$id]['since'], (int) $rounds[$id]['after']);
        $rounds[$id]['after'] = $contacts ? (int) end($contacts) : 0;

        if (count($contacts) < PG_FEEDBACK_PERIODIC_PAGE) {
            unset($rounds[$id]);
        }

        // The page is this pass's only once the new place is written.
        $next = (string) json_encode((object) $rounds);

        if (!pg_config_text_claim('feedback_periodic', $read, $next)) {
            return;
        }

        $read = $next;

        foreach ($contacts as $contact_id) {
            $facts = pg_feedback_record_facts('periodic', $contact_id, array('contact_id' => $contact_id));

            if ($facts) {
                pg_feedback_queue($survey, array('type' => 'periodic', 'id' => (int) $survey['id']), $facts, $now);
            }
        }
    }
}

/**
 * One page of the contacts active since a time (an order, a form, a
 * booking done or a course lesson), after a contact id.
 *
 * @param string $channel
 * @param int    $since
 * @param int    $after
 * @return int[] contact ids
 */
function pg_feedback_periodic_page($channel, $since, $after)
{
    $since = (int) $since;
    $active = array(
        "EXISTS (SELECT 1 FROM orders WHERE orders.contact_id = contacts.id AND orders.status <> 'incomplete' AND orders.order_date >= '" . $since . "')",
        "EXISTS (SELECT 1 FROM forms WHERE forms.contact_id = contacts.id AND forms.complete = 1 AND forms.submitted_timestamp >= '" . $since . "')",
    );

    if (function_exists('pg_booking_ready') && pg_booking_ready()) {
        $active[] = "EXISTS (SELECT 1 FROM bookings WHERE bookings.email = contacts.email_address AND contacts.email_address <> '' AND bookings.status = 'done' AND bookings.start_at >= '" . $since . "')";
    }

    if (function_exists('pg_course_ready') && pg_course_ready()) {
        $active[] = "EXISTS (SELECT 1 FROM course_progress JOIN user ON user.user_id = course_progress.user_id WHERE user.user_contact = contacts.id AND course_progress.created_at >= '" . $since . "')";
    }

    $reach = ($channel === 'email') ? "(contacts.email_address <> '') AND (contacts.opt_in = '1')" : "(contacts.mobile_phone <> '')";

    return array_map('intval', (array) db_values(
        "SELECT contacts.id FROM contacts
        WHERE (contacts.id > '" . (int) $after . "') AND " . $reach . " AND (" . implode(' OR ', $active) . ")
        ORDER BY contacts.id
        LIMIT " . PG_FEEDBACK_PERIODIC_PAGE));
}
