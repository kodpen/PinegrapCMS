<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What a booking tells its customer and the rest of the site (2026.11.0,
 * 11.0.161): the booking.created, booking.confirmed, booking.rescheduled and
 * booking.cancelled events for webhooks and Flows, the e-mails of the four
 * campaign profile actions booking_confirmed, booking_reminder,
 * booking_rescheduled and booking_cancelled with an .ics attachment, the text
 * messages of the same four, and the reminders before a booking starts.
 *
 * Every path that changes a booking calls pg_booking_changed() once after its
 * write: pg_booking_confirm() does for a new booking, the agenda, the manage
 * page and the API for a move or a cancellation. A mail is a campaign made
 * from the site's enabled profiles of the action (no profile, no mail); a
 * text is queued only while its SMS event is switched on.
 *
 * The reminders run in the general job: a reminder is due at its moment
 * (the start less the policy's reminder hours) for a confirmed booking that
 * stood as it is at that moment. The run keeps the time it last looked in
 * config_text booking_reminders_at and sends the moments that fell since, so
 * each reminder goes once without a column of its own; a run after a long
 * stop sends only the nearest reminder of a booking, never an old one.
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
 * The campaign profile actions of the bookings, in the order the form lists
 * them.
 *
 * @return string[]
 */
function pg_booking_mail_actions()
{
    return array('booking_confirmed', 'booking_reminder', 'booking_rescheduled', 'booking_cancelled');
}

/**
 * The mail action and SMS event of a booking event, '' when it sends nothing
 * to the customer (a new request only announces itself).
 *
 * @param string $event created, confirmed, rescheduled, cancelled, reminder
 * @return string
 */
function pg_booking_message_action($event)
{
    $actions = array(
        'confirmed'   => 'booking_confirmed',
        'reminder'    => 'booking_reminder',
        'rescheduled' => 'booking_rescheduled',
        'cancelled'   => 'booking_cancelled',
    );

    return isset($actions[$event]) ? $actions[$event] : '';
}

/**
 * The mail-merge fields a booking profile fills in, as the profile form lists
 * them.
 *
 * @return array field name => what it holds
 */
function pg_booking_mail_field_meanings()
{
    return array(
        'booking_service'    => lang('the booked service'),
        'booking_staff'      => lang('the staff member who takes the booking'),
        'booking_date'       => lang('the day of the booking'),
        'booking_time'       => lang('the start and end time of the booking'),
        'booking_zone'       => lang('the time zone the times are given in'),
        'booking_start'      => lang('the start, as a date and time that takes a %%format%%'),
        'booking_place'      => lang('the address of the business'),
        'booking_name'       => lang('the customer\'s name'),
        'booking_reference'  => lang('the booking\'s number'),
        'booking_manage_url' => lang('a link where the customer moves or cancels the booking'),
    );
}

/**
 * The day, the time range and the zone of a booking in the site's zone and
 * formats (pure).
 *
 * @param int    $start
 * @param int    $end
 * @param string $zone       a time zone name; the PHP zone when not valid
 * @param string $date_code  date() code of the day, e.g. 'j/n/Y'
 * @param string $time_code  date() code of a time, e.g. 'H:i'
 * @return array date, time, zone, start ('Y-m-d H:i:s' wall clock)
 */
function pg_booking_message_times($start, $end, $zone, $date_code, $time_code)
{
    try {
        $tz = new DateTimeZone((string) $zone);
    } catch (\Exception $exception) {
        $tz = new DateTimeZone(date_default_timezone_get());
    }

    $from = new DateTime('@' . (int) $start);
    $from->setTimezone($tz);
    $until = new DateTime('@' . (int) $end);
    $until->setTimezone($tz);

    $time = $from->format($time_code);

    if ((int) $end > (int) $start) {
        $time .= ' – ' . $until->format($time_code);
    }

    return array(
        'date'  => $from->format($date_code),
        'time'  => $time,
        'zone'  => $tz->getName(),
        'start' => $from->format('Y-m-d H:i:s'),
    );
}

/**
 * The business's address on one line, from the site's organization settings
 * (pure: the values come in).
 *
 * @param array $parts address_1, address_2, city, state, zip_code, country
 * @return string
 */
function pg_booking_place_text($parts)
{
    $line = array();

    foreach (array('address_1', 'address_2') as $key) {
        if (isset($parts[$key]) && (trim((string) $parts[$key]) !== '')) {
            $line[] = trim((string) $parts[$key]);
        }
    }

    $town = trim(trim((string) ($parts['zip_code'] ?? '')) . ' ' . trim((string) ($parts['city'] ?? '')));

    foreach (array($town, trim((string) ($parts['state'] ?? '')), trim((string) ($parts['country'] ?? ''))) as $piece) {
        if ($piece !== '') {
            $line[] = $piece;
        }
    }

    return implode(', ', $line);
}

/**
 * The site's address on one line.
 *
 * @return string
 */
function pg_booking_place()
{
    $parts = array();

    foreach (array('address_1', 'address_2', 'city', 'state', 'zip_code', 'country') as $key) {
        $constant = 'ORGANIZATION_' . strtoupper($key);
        $parts[$key] = defined($constant) ? (string) constant($constant) : '';
    }

    return pg_booking_place_text($parts);
}

/**
 * The manage link of a booking, drawn again from its row: the same link
 * every mail of the booking carries until it is moved. '' without a key.
 *
 * @param int $id
 * @param int $end_at
 * @return string
 */
function pg_booking_manage_url($id, $end_at)
{
    $token = pg_booking_manage_token($id, $end_at, pg_booking_key());

    if ($token === '') {
        return '';
    }

    $base = defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY : '';

    return $base . '/booking_manage.php?b=' . (int) $id . '&t=' . $token;
}

/**
 * A booking's row with the names its messages need: the service's name and
 * the staff member's (the contact's name, else the username). Null when the
 * tables are not there or the booking is gone.
 *
 * @param int $id
 * @return array|null
 */
function pg_booking_message_row($id)
{
    if (((int) $id <= 0) || !pg_booking_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT
            bookings.*,
            booking_services.name AS service_name,
            user.user_username AS staff_username,
            contacts.first_name AS staff_first_name,
            contacts.last_name AS staff_last_name
        FROM bookings
        LEFT JOIN booking_services ON booking_services.id = bookings.service_id
        LEFT JOIN booking_staff ON booking_staff.id = bookings.staff_id
        LEFT JOIN user ON user.user_id = booking_staff.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE bookings.id = '" . (int) $id . "'");

    if (!$row) {
        return null;
    }

    $row['staff_name'] = pg_user_display_name($row['staff_first_name'], $row['staff_last_name'], (string) $row['staff_username']);

    return $row;
}

/**
 * What a booking's messages say about it (pure): the values of the mail and
 * SMS fields.
 *
 * @param array $row     pg_booking_message_row()
 * @param array $context zone, date_code, time_code, place, manage_url
 * @return array field name (without the booking_ prefix) => text
 */
function pg_booking_message_values($row, $context)
{
    $times = pg_booking_message_times((int) $row['start_at'], (int) $row['end_at'], (string) ($context['zone'] ?? ''), (string) ($context['date_code'] ?? 'Y-m-d'), (string) ($context['time_code'] ?? 'H:i'));

    return array(
        'service'    => (string) ($row['service_name'] ?? ''),
        'staff'      => (string) ($row['staff_name'] ?? ''),
        'date'       => $times['date'],
        'time'       => $times['time'],
        'zone'       => $times['zone'],
        'start'      => $times['start'],
        'place'      => (string) ($context['place'] ?? ''),
        'name'       => (string) ($row['name'] ?? ''),
        'reference'  => (string) (int) ($row['id'] ?? 0),
        'manage_url' => (string) ($context['manage_url'] ?? ''),
    );
}

/**
 * The mail-merge fields of a booking (pure), every one of
 * pg_booking_mail_field_meanings() in the shape replace_variables() takes.
 *
 * @param array $values pg_booking_message_values()
 * @return array
 */
function pg_booking_mail_fields($values)
{
    $fields = array();

    foreach (array_keys(pg_booking_mail_field_meanings()) as $name) {
        $key = substr($name, strlen('booking_'));
        $fields[] = array(
            'name' => $name,
            'data' => (string) ($values[$key] ?? ''),
            'type' => ($key === 'start') ? 'date and time' : '',
        );
    }

    return $fields;
}

/**
 * The context of a booking's messages on this site: its zone, formats,
 * address and manage link.
 *
 * @param array $row
 * @return array
 */
function pg_booking_message_context($row)
{
    $date_code = (function_exists('get_date_format_code') && defined('DATE_FORMAT')) ? get_date_format_code() . '/Y' : 'Y-m-d';

    return array(
        'zone'       => date_default_timezone_get(),
        'date_code'  => $date_code,
        'time_code'  => pg_time_format_code(),
        'place'      => pg_booking_place(),
        'manage_url' => ((string) $row['status'] !== 'cancelled') ? pg_booking_manage_url((int) $row['id'], (int) $row['end_at']) : '',
    );
}

/**
 * One text line of an .ics file escaped as RFC 5545 asks: backslash,
 * semicolon, comma and line breaks (pure).
 *
 * @param string $text
 * @return string
 */
function pg_booking_ics_escape($text)
{
    $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);

    return str_replace(array('\\', ';', ',', "\n"), array('\\\\', '\\;', '\\,', '\\n'), $text);
}

/**
 * A content line folded at 75 octets, never inside a UTF-8 character; the
 * next part starts with a space (pure).
 *
 * @param string $line
 * @return string lines joined with CRLF, without the last CRLF
 */
function pg_booking_ics_fold($line)
{
    $line = (string) $line;
    $parts = array();
    $limit = 75;

    while (strlen($line) > $limit) {
        $cut = $limit;

        // Step back to the first byte of a character.
        while (($cut > 0) && ((ord($line[$cut]) & 0xC0) === 0x80)) {
            $cut--;
        }

        $parts[] = substr($line, 0, $cut);
        $line = substr($line, $cut);
        $limit = 74;
    }

    $parts[] = $line;

    return implode("\r\n ", $parts);
}

/**
 * A booking as an .ics calendar file (pure). One VEVENT under METHOD:PUBLISH
 * with a UID that stays the booking's, so a calendar that took the first
 * file updates the same entry from a later one: SEQUENCE grows with every
 * change, a moved booking carries its new times and a cancelled one STATUS
 * CANCELLED. Times in UTC.
 *
 * @param array $booking id, start_at, end_at, status, created_at, updated_at,
 *                       service_name, staff_name
 * @param array $context host (the UID's domain), store, place, manage_url
 * @param int   $now     DTSTAMP
 * @return string CRLF line endings
 */
function pg_booking_ics($booking, $context, $now)
{
    $utc = function ($time) {
        return gmdate('Ymd\THis\Z', (int) $time);
    };

    $status = (string) ($booking['status'] ?? '');
    $states = array('cancelled' => 'CANCELLED', 'requested' => 'TENTATIVE', 'held' => 'TENTATIVE');
    $host = preg_replace('/[^a-z0-9.-]/', '', strtolower((string) ($context['host'] ?? '')));
    $service = trim((string) ($booking['service_name'] ?? ''));
    $store = trim((string) ($context['store'] ?? ''));
    $staff = trim((string) ($booking['staff_name'] ?? ''));
    $manage = (string) ($context['manage_url'] ?? '');

    $summary = (($service !== '') && ($store !== '')) ? $service . ' - ' . $store : (($service !== '') ? $service : $store);

    $description = array();

    if ($staff !== '') {
        $description[] = $staff;
    }

    if ($manage !== '') {
        $description[] = $manage;
    }

    $lines = array(
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Pinegrap//Bookings//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:booking-' . (int) $booking['id'] . '@' . (($host !== '') ? $host : 'localhost'),
        'DTSTAMP:' . $utc($now),
        'DTSTART:' . $utc($booking['start_at']),
        'DTEND:' . $utc($booking['end_at']),
        'SEQUENCE:' . max(0, (int) ($booking['updated_at'] ?? 0) - (int) ($booking['created_at'] ?? 0)),
        'STATUS:' . (isset($states[$status]) ? $states[$status] : 'CONFIRMED'),
        'SUMMARY:' . pg_booking_ics_escape($summary),
    );

    if (trim((string) ($context['place'] ?? '')) !== '') {
        $lines[] = 'LOCATION:' . pg_booking_ics_escape(trim((string) $context['place']));
    }

    if ($description) {
        $lines[] = 'DESCRIPTION:' . pg_booking_ics_escape(implode("\n", $description));
    }

    if ($manage !== '') {
        $lines[] = 'URL:' . $manage;
    }

    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('pg_booking_ics_fold', $lines)) . "\r\n";
}

/**
 * The .ics attachment of a booking campaign's mail, read at the send so it
 * carries the booking as it stands. Null when the campaign is not a
 * booking's or the booking is gone.
 *
 * @param array $campaign a row with action and action_item_id
 * @return array|null name, content, type
 */
function pg_booking_ics_attachment($campaign)
{
    $action = (string) ($campaign['action'] ?? '');
    $id = (int) ($campaign['action_item_id'] ?? 0);

    if (($id <= 0) || !in_array($action, pg_booking_mail_actions(), true)) {
        return null;
    }

    $row = pg_booking_message_row($id);

    if (!$row) {
        return null;
    }

    $context = pg_booking_message_context($row) + array(
        'host'  => defined('HOSTNAME_SETTING') ? (string) HOSTNAME_SETTING : '',
        'store' => defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '',
    );

    return array(
        'name'    => 'booking-' . $id . '.ics',
        'content' => pg_booking_ics($row, $context, time()),
        'type'    => 'text/calendar',
    );
}

/**
 * What a booking.* event carries (pure): ids, times in ISO 8601 UTC, the
 * status and the source, never the manage token.
 *
 * @param array $row
 * @return array
 */
function pg_booking_event_payload($row)
{
    $iso = function ($time) {
        return gmdate('Y-m-d\TH:i:s\Z', (int) $time);
    };

    return array(
        'id'         => (int) $row['id'],
        'service_id' => (int) $row['service_id'],
        'staff_id'   => (int) $row['staff_id'],
        'user_id'    => (int) $row['user_id'],
        'name'       => (string) $row['name'],
        'email'      => (string) $row['email'],
        'start_at'   => $iso($row['start_at']),
        'end_at'     => $iso($row['end_at']),
        'status'     => (string) $row['status'],
        'source'     => (string) $row['source'],
    );
}

/**
 * The SMS reference of a booking's message: once per event and start, so a
 * moved booking is told again and a repeated call is not.
 *
 * @param array  $row
 * @param string $event
 * @return string
 */
function pg_booking_sms_reference($row, $event)
{
    return 'booking:' . (int) $row['id'] . ':' . $event . ':' . (int) $row['start_at'];
}

/**
 * Mails and texts the customer of a booking for one event. Quiet: no profile
 * of the action, no address or number, or SMS switched off sends nothing and
 * stops nothing.
 *
 * @param array  $row   pg_booking_message_row()
 * @param string $event confirmed, reminder, rescheduled, cancelled
 * @return void
 */
function pg_booking_message_send($row, $event)
{
    $action = pg_booking_message_action($event);

    if ($action === '') {
        return;
    }

    $values = pg_booking_message_values($row, pg_booking_message_context($row));

    if ((string) $row['email'] !== '') {
        $contact_id = (int) db_value("SELECT id FROM contacts WHERE email_address = '" . e((string) $row['email']) . "' ORDER BY id LIMIT 1");

        create_auto_email_campaigns(array(
            'action'           => $action,
            'campaign_item_id' => (int) $row['id'],
            'contact_id'       => $contact_id,
            'email_address'    => (string) $row['email'],
            'fields'           => pg_booking_mail_fields($values),
            'data'             => array('booking' => $row),
        ));
    }

    if (((string) $row['phone'] !== '') && function_exists('pg_sms_message_queue')) {
        pg_sms_message_queue($action, (string) $row['phone'], array(
            'name'       => $values['name'],
            'service'    => $values['service'],
            'date'       => $values['date'],
            'time'       => $values['time'],
            'manage_url' => $values['manage_url'],
        ), pg_booking_sms_reference($row, $event));
    }
}

/**
 * Tells the site and the customer that a booking changed, after the change
 * is written: the booking.<event> event, then the mail and the text of the
 * event. A throw is logged and stops nothing: the booking stands.
 *
 * @param int    $id
 * @param string $event created, confirmed, rescheduled, cancelled
 * @return bool whether the booking was found
 */
function pg_booking_changed($id, $event)
{
    if (!in_array($event, array('created', 'confirmed', 'rescheduled', 'cancelled'), true)) {
        return false;
    }

    try {
        $row = pg_booking_message_row($id);

        if (!$row) {
            return false;
        }

        pg_announce('booking.' . $event, pg_booking_event_payload($row));
        pg_booking_message_send($row, $event);
    } catch (\Throwable $exception) {
        log_activity(pg_log_lang(array(
            'string' => 'The messages of booking {var:1} could not be prepared: {var:2}',
            'vars'   => array((int) $id, $exception->getMessage()),
        )), 'UNKNOWN');

        return false;
    }

    return true;
}

/**
 * The event a status change announces (pure): a booking that becomes
 * confirmed or cancelled; '' for any other move (done, no-show, an
 * unchanged status). A hold confirmed goes through pg_booking_confirm(),
 * which announces it itself.
 *
 * @param string $from
 * @param string $to
 * @return string confirmed, cancelled or ''
 */
function pg_booking_status_event($from, $to)
{
    if (($from === $to) || ($from === 'held')) {
        return '';
    }

    return in_array($to, array('confirmed', 'cancelled'), true) ? $to : '';
}

/**
 * A new booking (not a hold): booking.created, and booking.confirmed with its
 * mail when it was confirmed at once.
 *
 * @param int    $id
 * @param string $status requested or confirmed
 * @return void
 */
function pg_booking_created($id, $status)
{
    if (pg_booking_changed($id, 'created') && ($status === 'confirmed')) {
        pg_booking_changed($id, 'confirmed');
    }
}

/**
 * The reminder a booking is due now, if any (pure). A reminder's moment is
 * the start less its hours; it is due when the moment fell after the last
 * run and by now, the booking stood as it is at that moment (not changed
 * after it), and no nearer reminder's moment has passed: after a long stop
 * only the nearest one goes.
 *
 * @param int   $start_at
 * @param int   $updated_at
 * @param int[] $hours      reminder hours
 * @param int   $last       the last run's time
 * @param int   $now
 * @return int the hours of the due reminder, 0 for none
 */
function pg_booking_reminder_due($start_at, $updated_at, $hours, $last, $now)
{
    $start_at = (int) $start_at;
    $now = (int) $now;

    if ($start_at <= $now) {
        return 0;
    }

    $hours = array_values(array_unique(array_filter(array_map('intval', (array) $hours), function ($hour) {
        return $hour > 0;
    })));

    // Nearest first: the smallest hours whose moment has passed wins.
    sort($hours);

    foreach ($hours as $hour) {
        $moment = $start_at - ($hour * 3600);

        if ($moment > $now) {
            continue;
        }

        return (($moment > (int) $last) && ((int) $updated_at <= $moment)) ? $hour : 0;
    }

    return 0;
}

/**
 * The policy's reminder hours: the settings' list, 24 and 2 while none is
 * stored.
 *
 * @return int[]
 */
function pg_booking_reminder_hours()
{
    if (function_exists('pg_booking_policy')) {
        $policy = pg_booking_policy();

        if (isset($policy['reminder_hours']) && is_array($policy['reminder_hours'])) {
            return array_values(array_map('intval', $policy['reminder_hours']));
        }
    }

    return array(24, 2);
}

/**
 * The general job's reminder pass: the confirmed bookings whose reminder
 * moment fell since the last pass get the booking_reminder mail and text.
 * The pass time is written before anything is sent, so a pass that dies
 * loses its reminders rather than sending them twice, and only while it still
 * holds the time this pass read, so two overlapping runs never both send.
 *
 * @param int $now
 * @return int how many reminders went out
 */
function pg_booking_reminders_run($now)
{
    $now = (int) $now;

    if (!pg_booking_ready() || !function_exists('pg_config_text_save')) {
        return 0;
    }

    $hours = pg_booking_reminder_hours();

    if (!$hours) {
        return 0;
    }

    $stored = pg_config_text('booking_reminders_at');
    // A first pass looks back one tick; a pass after a long stop no further
    // than a day.
    $last = ($stored !== '') ? max((int) $stored, $now - 86400) : $now - 900;

    // Claimed against the value read: of two general job runs that read the
    // same pass time, only the one whose write changed the row sends.
    if (!pg_config_text_claim('booking_reminders_at', $stored, (string) $now)) {
        return 0;
    }

    $rows = db_items(
        "SELECT id, start_at, updated_at FROM bookings
        WHERE
            status = 'confirmed'
            AND start_at > '" . $now . "'
            AND start_at <= '" . ($now + max($hours) * 3600) . "'
            AND start_at > '" . ($last + min($hours) * 3600) . "'
        ORDER BY start_at");

    $sent = 0;

    foreach ($rows as $candidate) {
        if (pg_booking_reminder_due($candidate['start_at'], $candidate['updated_at'], $hours, $last, $now) === 0) {
            continue;
        }

        try {
            $row = pg_booking_message_row((int) $candidate['id']);

            if ($row) {
                pg_booking_message_send($row, 'reminder');
                $sent++;
            }
        } catch (\Throwable $exception) {
            log_activity(pg_log_lang(array(
                'string' => 'The messages of booking {var:1} could not be prepared: {var:2}',
                'vars'   => array((int) $candidate['id'], $exception->getMessage()),
            )), 'UNKNOWN');
        }
    }

    return $sent;
}
