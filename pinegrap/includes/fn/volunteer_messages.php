<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The messages of volunteer programmes that go out by themselves, run by
 * the general job (job.php): the call to volunteers who fit a short shift
 * within the programme's call days, the reminder the day before to everyone
 * signed up, and the notices 30 and 7 days before a document's end to the
 * volunteer and the programme's coordinators. A call and a reminder go by
 * e-mail, SMS or WhatsApp as the volunteer chose among the programme's
 * channels; they are service messages (SMS kind 'volunteer', the WhatsApp
 * events volunteer_call and volunteer_reminder), no marketing consent is
 * asked. Calls and reminders go only between 09:00 and 20:00 site time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');

// The sending hours of calls and reminders, site time: from 09:00, before
// 20:00. A volunteer's phone never rings at night.
if (!defined('PG_VOLUNTEER_SEND_FROM')) {
    define('PG_VOLUNTEER_SEND_FROM', 9);
}

if (!defined('PG_VOLUNTEER_SEND_TO')) {
    define('PG_VOLUNTEER_SEND_TO', 20);
}

// A queued SMS or WhatsApp call or reminder held up longer than this (the
// queue stopped) is dropped: two hours, as the other service messages.
if (!defined('PG_VOLUNTEER_MESSAGE_EXPIRES')) {
    define('PG_VOLUNTEER_MESSAGE_EXPIRES', 7200);
}

// The most shifts one pass looks at, and the most documents one notice pass
// tells about; the rest wait for the next run.
if (!defined('PG_VOLUNTEER_PASS_ROWS')) {
    define('PG_VOLUNTEER_PASS_ROWS', 200);
}

/**
 * Whether a site wall-clock moment is inside the sending hours. Pure.
 *
 * @param string $now 'YYYY-MM-DD HH:MM[:SS]'
 * @return bool
 */
function pg_volunteer_send_hour($now)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2} (\d{2}):\d{2}/', (string) $now, $match)) {
        return false;
    }

    $hour = (int) $match[1];

    return ($hour >= PG_VOLUNTEER_SEND_FROM) && ($hour < PG_VOLUNTEER_SEND_TO);
}

/**
 * The channels to try for one volunteer, in order: their own choice when the
 * programme offers it and it can reach them, then the programme's other
 * channels that can. A phone is needed for SMS and WhatsApp, an e-mail
 * address for e-mail. Pure.
 *
 * @param string $chosen  the volunteer's call_channel
 * @param array  $allowed the programme's call_channels
 * @param bool   $phone   whether a mobile number is known
 * @param bool   $email   whether an e-mail address is known
 * @return array channels, empty when nothing reaches the volunteer
 */
function pg_volunteer_message_channels($chosen, array $allowed, $phone, $email)
{
    $reachable = array();

    foreach (PG_VOLUNTEER_CHANNELS as $channel) {
        if (in_array($channel, $allowed, true) && (($channel === 'email') ? $email : $phone)) {
            $reachable[] = $channel;
        }
    }

    if (in_array((string) $chosen, $reachable, true)) {
        $reachable = array_values(array_unique(array_merge(array((string) $chosen), $reachable)));
    }

    return $reachable;
}

/**
 * The channels a reminder may go by: the programme's call channels, or
 * e-mail when it set none, since a reminder is no call. Pure.
 *
 * @param array $allowed the programme's call_channels
 * @return array
 */
function pg_volunteer_reminder_allowed(array $allowed)
{
    return $allowed ? $allowed : array('email');
}

/**
 * Whether a shift's day-before reminder is due: not sent yet, the shift not
 * started and starting tomorrow or sooner. A shift added for the same day
 * still gets it. Pure.
 *
 * @param array  $shift start, reminded_at
 * @param string $now   'YYYY-MM-DD HH:MM:SS'
 * @return bool
 */
function pg_volunteer_reminder_due(array $shift, $now)
{
    $start = (string) ($shift['start'] ?? '');

    if (((int) ($shift['reminded_at'] ?? 0) > 0) || (pg_volunteer_minutes($start) === null) || (strcmp($start, (string) $now) <= 0)) {
        return false;
    }

    return strcmp(substr($start, 0, 10), pg_volunteer_add_days(substr((string) $now, 0, 10), 1)) <= 0;
}

/**
 * The bit of volunteer_documents.notice_sent a document notice sets: 1 for
 * the 30-day notice, 2 for the 7-day one. Pure.
 *
 * @param int $notice 30 or 7
 * @return int 0 for anything else
 */
function pg_volunteer_notice_bit($notice)
{
    $bits = array(30 => 1, 7 => 2);

    return $bits[(int) $notice] ?? 0;
}

/**
 * The notices a notice_sent value says were sent. Pure.
 *
 * @param int $bits
 * @return array 30 and/or 7
 */
function pg_volunteer_notices_sent($bits)
{
    $sent = array();

    foreach (array(30, 7) as $notice) {
        if (((int) $bits & pg_volunteer_notice_bit($notice)) !== 0) {
            $sent[] = $notice;
        }
    }

    return $sent;
}

/**
 * The raw token of a volunteer's link still in use, rebuilt from the moment
 * it was issued; '' when no link was sent or it was withdrawn. Pure.
 *
 * @param array  $volunteer id, link_hash, link_issued_at
 * @param string $key the site's ENCRYPTION_KEY
 * @return string
 */
function pg_volunteer_live_token(array $volunteer, $key)
{
    if ((int) ($volunteer['link_issued_at'] ?? 0) <= 0) {
        return '';
    }

    $token = pg_volunteer_link_token((int) ($volunteer['id'] ?? 0), (int) $volunteer['link_issued_at'], $key);

    return (($token !== '') && pg_volunteer_link_token_valid($token, $volunteer['link_hash'] ?? null)) ? $token : '';
}

/**
 * A call's or reminder's short text for SMS: the subject, the shift and the
 * volunteer's page when they have a link. Pure.
 *
 * @param array  $message pg_volunteer_message()
 * @param string $when    pg_volunteer_shift_when()
 * @param string $link    '' for none
 * @return string
 */
function pg_volunteer_sms_text(array $message, $when, $link)
{
    return trim($message['subject'] . ': ' . $when . (($link !== '') ? ' ' . $link : ''));
}

/**
 * Sends one call or reminder to a volunteer by the first channel that takes
 * it (pg_volunteer_message_channels()).
 *
 * @param array  $programme
 * @param array  $shift   pg_volunteer_shift_decode()
 * @param array  $person  id, first_name, email_address, mobile_phone,
 *                        call_channel, link_hash, link_issued_at
 * @param string $kind    call or reminder
 * @param array  $allowed the channels the programme lets this kind use
 * @return string the channel it went by, '' when none took it
 */
function pg_volunteer_send(array $programme, array $shift, array $person, $kind, array $allowed)
{
    $email = (string) ($person['email_address'] ?? '');
    $phone = pg_sms_number((string) ($person['mobile_phone'] ?? ''));
    $channels = pg_volunteer_message_channels((string) ($person['call_channel'] ?? ''), $allowed, $phone !== '', validate_email_address($email));
    $message = pg_volunteer_message($programme['texts'], $kind, array('name' => (string) ($person['first_name'] ?? ''), 'programme' => $programme['name']));
    $when = pg_volunteer_shift_when($shift);
    $token = pg_volunteer_live_token($person, defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : '');
    $link = ($token !== '') ? pg_volunteer_link_url((int) $person['id'], $token) : '';
    $reference = 'volunteer:' . (int) $shift['id'] . ':' . (int) $person['id'] . ':' . $kind;

    foreach ($channels as $channel) {
        if ($channel === 'email') {
            _pg_volunteer_mail($email, array('subject' => $message['subject'], 'body' => $message['body'] . "\n\n" . $when . (($link !== '') ? "\n\n" . $link : '')));

            return 'email';
        }

        if (($channel === 'whatsapp') && pg_whatsapp_message_queue('volunteer_' . $kind, pg_sms_dial($phone), array(
            'name'      => (string) ($person['first_name'] ?? ''),
            'programme' => $programme['name'],
            'shift'     => $when,
            'page'      => $link,
        ), $reference)) {
            return 'whatsapp';
        }

        if (($channel === 'sms') && !empty(pg_sms_enqueue(pg_sms_dial($phone), pg_volunteer_sms_text($message, $when, $link), 'volunteer', array('reference' => $reference, 'expires_at' => time() + PG_VOLUNTEER_MESSAGE_EXPIRES))['ok'])) {
            return 'sms';
        }
    }

    return '';
}

/**
 * Calls the volunteers a short shift asks (pg_volunteer_shift_call_ids()) by
 * their channels. The ids are first added to the shift's 'called' by one
 * write conditional on the list read, so two runs never ask anyone twice.
 * Outside the sending hours nobody is asked.
 *
 * @param array  $programme
 * @param array  $shift pg_volunteer_shift_get()
 * @param string $now   'YYYY-MM-DD HH:MM:SS'
 * @return int volunteers asked
 */
function pg_volunteer_shift_call_send(array $programme, array $shift, $now)
{
    if (!$programme['call_channels'] || !pg_volunteer_send_hour($now)) {
        return 0;
    }

    $people = pg_volunteer_call_people($programme, $shift);
    $ids = pg_volunteer_shift_call_ids($programme, $shift, $people, $now);

    if (!$ids) {
        return 0;
    }

    $before = db_value("SELECT called FROM volunteer_shifts WHERE id = '" . (int) $shift['id'] . "'");
    $after = json_encode(array_values(array_unique(array_merge($shift['called'], $ids))));

    if (!db_q("UPDATE volunteer_shifts SET called = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND called <=> ?", array($after, (int) $shift['id'], $before)) || (mysqli_affected_rows(db::$con) < 1)) {
        return 0;
    }

    foreach ($ids as $id) {
        pg_volunteer_send($programme, $shift, $people[$id], 'call', $programme['call_channels']);
    }

    // The first call of a shift tells that it is short of volunteers; a
    // later call for the same shift does not again.
    if (!json_decode((string) $before, true)) {
        pg_volunteer_announce_unfilled($programme, $shift, count($ids));
    }

    return count($ids);
}

/**
 * The general job's pass of unfilled shifts: each open programme's coming
 * shifts within its call days get their call (pg_volunteer_shift_call_send()).
 * A shift full, started or with everyone who fits asked sends nothing.
 *
 * @param string $now 'YYYY-MM-DD HH:MM:SS'
 * @return int volunteers asked
 */
function pg_volunteer_calls_run($now)
{
    if (!pg_volunteer_send_hour($now)) {
        return 0;
    }

    $asked = 0;
    $programmes = array();

    foreach ((array) db_items("SELECT volunteer_shifts.id, volunteer_shifts.programme_id FROM volunteer_shifts
        INNER JOIN volunteer_programmes ON volunteer_programmes.id = volunteer_shifts.programme_id
        WHERE volunteer_programmes.state = 'open' AND volunteer_programmes.call_channels <> '' AND volunteer_programmes.call_days > 0
            AND volunteer_shifts.start_time > '" . escape($now) . "' AND volunteer_shifts.start_time < '" . escape(pg_volunteer_add_days(substr($now, 0, 10), 16)) . "'
        ORDER BY volunteer_shifts.start_time, volunteer_shifts.id LIMIT " . (int) PG_VOLUNTEER_PASS_ROWS) as $row) {
        $programme_id = (int) $row['programme_id'];

        if (!array_key_exists($programme_id, $programmes)) {
            $programmes[$programme_id] = pg_volunteer_programme_get($programme_id);
        }

        $shift = $programmes[$programme_id] ? pg_volunteer_shift_get($programme_id, (int) $row['id']) : null;

        if (($shift !== null) && pg_volunteer_call_due($shift, $now, $programmes[$programme_id]['call_days'])) {
            $asked += pg_volunteer_shift_call_send($programmes[$programme_id], $shift, $now);
        }
    }

    return $asked;
}

/**
 * The general job's day-before reminders: each shift due
 * (pg_volunteer_reminder_due()) is claimed by setting reminded_at, then
 * everyone signed up on it is told, whether their calls are on or off. A
 * paused programme takes no new sign-ups, but its shifts still run, so its
 * volunteers are reminded too.
 *
 * @param string $now 'YYYY-MM-DD HH:MM:SS'
 * @return int volunteers reminded
 */
function pg_volunteer_reminders_run($now)
{
    if (!pg_volunteer_send_hour($now)) {
        return 0;
    }

    $told = 0;
    $programmes = array();

    foreach ((array) db_items("SELECT volunteer_shifts.* FROM volunteer_shifts
        INNER JOIN volunteer_programmes ON volunteer_programmes.id = volunteer_shifts.programme_id
        WHERE volunteer_shifts.reminded_at = 0
            AND volunteer_shifts.start_time > '" . escape($now) . "' AND volunteer_shifts.start_time < '" . escape(pg_volunteer_add_days(substr($now, 0, 10), 2)) . "'
        ORDER BY volunteer_shifts.start_time, volunteer_shifts.id LIMIT " . (int) PG_VOLUNTEER_PASS_ROWS) as $row) {
        $shift = pg_volunteer_shift_decode($row);
        $shift['reminded_at'] = (int) $row['reminded_at'];

        if (!pg_volunteer_reminder_due($shift, $now)) {
            continue;
        }

        if (!db_q("UPDATE volunteer_shifts SET reminded_at = UNIX_TIMESTAMP() WHERE id = ? AND reminded_at = 0", array($shift['id'])) || (mysqli_affected_rows(db::$con) < 1)) {
            continue;
        }

        if (!array_key_exists($shift['programme_id'], $programmes)) {
            $programmes[$shift['programme_id']] = pg_volunteer_programme_get($shift['programme_id']);
        }

        $programme = $programmes[$shift['programme_id']];

        if ($programme === null) {
            continue;
        }

        foreach ((array) db_items("SELECT volunteers.id, volunteers.call_channel, volunteers.link_hash, volunteers.link_issued_at, contacts.first_name, contacts.email_address, contacts.mobile_phone FROM volunteer_signups
            INNER JOIN volunteers ON volunteers.id = volunteer_signups.volunteer_id
            LEFT JOIN contacts ON contacts.id = volunteers.contact_id
            WHERE volunteer_signups.shift_id = '" . (int) $shift['id'] . "' AND volunteer_signups.state = 'signed' AND volunteers.state <> 'left'") as $person) {
            $told += (pg_volunteer_send($programme, $shift, $person, 'reminder', pg_volunteer_reminder_allowed($programme['call_channels'])) !== '') ? 1 : 0;
        }
    }

    return $told;
}

/**
 * The text of a document notice. Pure.
 *
 * @param string $kind        the document's kind
 * @param string $valid_until the date as words
 * @param string $whose       the volunteer's name, '' when written to them
 * @return array subject, body
 */
function pg_volunteer_document_notice_text($kind, $valid_until, $whose)
{
    $vars = array('{kind}' => (string) $kind, '{date}' => (string) $valid_until, '{name}' => (string) $whose);

    if ($whose === '') {
        return array(
            'subject' => strtr(lang('Your {kind} ends on {date}'), $vars),
            'body'    => strtr(lang('Your {kind} is valid until {date}. Please bring a new one to the coordinator before then; after it, shifts that need it cannot be taken.'), $vars),
        );
    }

    return array(
        'subject' => strtr(lang('{name}: {kind} ends on {date}'), $vars),
        'body'    => strtr(lang('The {kind} of volunteer {name} is valid until {date}. Shifts that need it cannot be taken after it.'), $vars),
    );
}

/**
 * The general job's document notices: a document of a volunteer who has not
 * left, ending within 30 days, gets its 30-day and its 7-day notice once
 * each (pg_volunteer_document_notice()) by e-mail, to the volunteer and the
 * programme's coordinators. A document whose kind has a newer one valid
 * longer (the volunteer brought the new one) is told about no more. The bit is claimed first: a dying pass loses a
 * notice, never doubles it.
 *
 * @param string $today 'YYYY-MM-DD'
 * @return int documents told about
 */
function pg_volunteer_document_notices_run($today)
{
    $count = 0;
    $coordinators = array();

    foreach ((array) db_items("SELECT volunteer_documents.id, volunteer_documents.kind, volunteer_documents.valid_until, volunteer_documents.notice_sent,
            volunteers.programme_id, contacts.first_name, contacts.last_name, contacts.email_address, volunteer_programmes.name AS programme, volunteer_programmes.coordinators
        FROM volunteer_documents
        INNER JOIN volunteers ON volunteers.id = volunteer_documents.volunteer_id
        INNER JOIN volunteer_programmes ON volunteer_programmes.id = volunteers.programme_id
        LEFT JOIN contacts ON contacts.id = volunteers.contact_id
        WHERE volunteers.state <> 'left' AND volunteer_documents.notice_sent < 3
            AND volunteer_documents.valid_until >= '" . escape($today) . "' AND volunteer_documents.valid_until <= '" . escape(pg_volunteer_add_days($today, 30)) . "'
            AND NOT EXISTS (SELECT 1 FROM volunteer_documents AS newer WHERE newer.volunteer_id = volunteer_documents.volunteer_id
                AND newer.kind = volunteer_documents.kind AND newer.valid_until > volunteer_documents.valid_until)
        ORDER BY volunteer_documents.valid_until, volunteer_documents.id LIMIT " . (int) PG_VOLUNTEER_PASS_ROWS) as $row) {
        $notice = pg_volunteer_document_notice((string) $row['valid_until'], $today, pg_volunteer_notices_sent((int) $row['notice_sent']));
        $bit = pg_volunteer_notice_bit($notice);

        // The 7-day notice stands for the 30-day one it came after or
        // replaced, so neither goes again.
        $set = ($notice === 7) ? 3 : $bit;

        if (($bit === 0) || !db_q("UPDATE volunteer_documents SET notice_sent = notice_sent | ? WHERE id = ? AND (notice_sent & ?) = 0", array($set, (int) $row['id'], $bit)) || (mysqli_affected_rows(db::$con) < 1)) {
            continue;
        }

        $date = get_absolute_time(array('timestamp' => strtotime((string) $row['valid_until']), 'type' => 'date', 'format' => 'plain_text'));
        $name = trim($row['first_name'] . ' ' . $row['last_name']);
        $name = ($name !== '') ? $name : (string) $row['email_address'];

        if (validate_email_address((string) $row['email_address'])) {
            $text = pg_volunteer_document_notice_text($row['kind'], $date, '');
            _pg_volunteer_mail((string) $row['email_address'], array('subject' => $row['programme'] . ': ' . $text['subject'], 'body' => strtr(lang('Hello {name},'), array('{name}' => (string) $row['first_name'])) . "\n\n" . $text['body']));
        }

        $ids = array_filter(array_map('intval', (array) json_decode((string) $row['coordinators'], true)));

        foreach ($ids as $user_id) {
            if (!array_key_exists($user_id, $coordinators)) {
                $coordinators[$user_id] = (string) db_value("SELECT user_email FROM user WHERE user_id = '" . (int) $user_id . "'");
            }

            if (validate_email_address($coordinators[$user_id])) {
                $text = pg_volunteer_document_notice_text($row['kind'], $date, $name);
                _pg_volunteer_mail($coordinators[$user_id], array('subject' => $row['programme'] . ': ' . $text['subject'], 'body' => $text['body']));
            }
        }

        $count++;
    }

    return $count;
}

/**
 * The general job's volunteer passes, in a few indexed reads when there is
 * nothing to do. Nothing on a site without the volunteer tables.
 *
 * @param string $now 'YYYY-MM-DD HH:MM:SS', site time
 * @return array asked, reminded, documents
 */
function pg_volunteer_job_run($now)
{
    if (!pg_volunteer_ready()) {
        return array('asked' => 0, 'reminded' => 0, 'documents' => 0);
    }

    return array(
        'asked'     => pg_volunteer_calls_run($now),
        'reminded'  => pg_volunteer_reminders_run($now),
        'documents' => pg_volunteer_document_notices_run(substr($now, 0, 10)),
    );
}

/**
 * Saves a volunteer's own "Shift calls" choice from their page: on or off,
 * and the channel among the programme's.
 *
 * @param int    $volunteer_id
 * @param bool   $on
 * @param string $channel
 * @return string 'calls_saved' or 'refused'
 */
function pg_volunteer_calls_save($volunteer_id, $on, $channel)
{
    $volunteer = pg_volunteer_self_load($volunteer_id);

    if ($volunteer === null) {
        return 'refused';
    }

    $allowed = $volunteer['programme']['call_channels'];
    $channel = in_array((string) $channel, $allowed, true) ? (string) $channel : (string) $volunteer['call_channel'];

    return db_q("UPDATE volunteers SET calls = ?, call_channel = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array($on ? 1 : 0, $channel, (int) $volunteer['id'])) ? 'calls_saved' : 'refused';
}
