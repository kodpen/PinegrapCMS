<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Taking a walk-in queue number and following it: the public page
 * (queue.php) and staff on a place's screen add a number; the person's page
 * opens by the number's signed link and reads where it stands; "Leave the
 * queue" ends it. A number gets at most three notices, each once: when it
 * is taken, when few are ahead and when it is called, by WhatsApp or SMS
 * to the phone given for the number, or by e-mail when only an e-mail is
 * given. They are service messages (kind queue): no marketing consent is
 * asked and they carry no promotion. The reading and the texts are pure;
 * the rest needs pg_waiting_ready().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** People one number may stand for when the place asks. */
const PG_WAITING_PARTY_MAX = 50;

/** Days of handled numbers the estimate reads. */
const PG_WAITING_HANDLED_DAYS = 14;

/** Seconds a notice may wait in its queue before it is no longer worth sending. */
const PG_WAITING_NOTICE_EXPIRES = 7200;

/**
 * The join form read back: the service (the only one when the place has
 * one), and the optional name, phone, e-mail and party size. Pure.
 *
 * @param array $post
 * @param array $place services, settings as decoded
 * @return array values => array(service, name, phone, email, party_size), error ('' when it may be taken)
 */
function pg_waiting_join_input($post, $place)
{
    $post = is_array($post) ? $post : array();
    $keys = array_column((array) $place['services'], 'key');
    $service = is_scalar($post['service'] ?? null) ? (string) $post['service'] : '';

    if ((count($keys) === 1) && ($service === '')) {
        $service = $keys[0];
    }

    $phone_typed = pg_waiting_text($post['phone'] ?? '', 40);
    $phone = ($phone_typed === '') ? '' : pg_sms_number($phone_typed);
    $email = pg_waiting_text($post['email'] ?? '', 190);
    $party = empty($place['settings']['party_size']) ? 0 : pg_waiting_int($post['party_size'] ?? '', 1, PG_WAITING_PARTY_MAX, 1);
    $error = '';

    if (!in_array($service, $keys, true)) {
        $error = lang('Choose a service.');
    } elseif (($phone_typed !== '') && ($phone === '')) {
        $error = lang('This phone number cannot be read.');
    } elseif (($email !== '') && (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        $error = lang('This e-mail address cannot be read.');
    }

    return array(
        'values' => array('service' => $service, 'name' => pg_waiting_text($post['name'] ?? '', 100), 'phone' => $phone, 'email' => $email, 'party_size' => $party),
        'error'  => $error,
    );
}

/**
 * Whether a number may be taken by a way now: the place is open and the way
 * is switched on (qr, site, kiosk); staff add one whenever it is open.
 * Pure.
 *
 * @param array  $place status, settings
 * @param string $way   qr, site, kiosk or staff
 * @return bool
 */
function pg_waiting_way_open($place, $way)
{
    if (($place['status'] ?? '') !== 'open') {
        return false;
    }

    if ($way === 'staff') {
        return true;
    }

    return !empty($place['settings']['ways'][$way]);
}

/**
 * A notice's text: the place's own when it wrote one, else the standard
 * one, with {number}, {desk}, {link}, {place} and {ahead} filled. Pure.
 *
 * @param string $kind   taken, ahead or called
 * @param string $custom the place's text, '' for the standard one
 * @param array  $values number, desk, link, place, ahead
 * @return string
 */
function pg_waiting_notice_text($kind, $custom, $values)
{
    $standard = array(
        'taken'  => lang('Your number at {place} is {number}. See your turn here: {link}'),
        'ahead'  => lang('{place}: {ahead} ahead of {number}. Please come close. {link}'),
        'called' => lang('{number}: it is your turn. Please go to {desk}.'),
    );
    $text = (trim((string) $custom) !== '') ? (string) $custom : ($standard[$kind] ?? '');
    $fill = array();

    foreach (array('number', 'desk', 'link', 'place', 'ahead') as $name) {
        $fill['{' . $name . '}'] = (string) ($values[$name] ?? '');
    }

    return trim(strtr($text, $fill));
}

/**
 * The bit a notice sets on its number, so each goes once and a number gets
 * three at most. Pure.
 *
 * @param string $kind
 * @return int 0 for an unknown kind
 */
function pg_waiting_notice_bit($kind)
{
    $bits = array('taken' => 1, 'ahead' => 2, 'called' => 4);

    return $bits[$kind] ?? 0;
}

/**
 * Where a number's notices go: 'phone' (WhatsApp or SMS) when a phone is
 * given and either is on, 'email' when only an e-mail is given and e-mail
 * is on, '' for none. Pure.
 *
 * @param array  $notices the place's notices settings
 * @param string $phone
 * @param string $email
 * @return string
 */
function pg_waiting_notice_channel($notices, $phone, $email)
{
    if ((string) $phone !== '') {
        return (!empty($notices['sms']) || !empty($notices['whatsapp'])) ? 'phone' : '';
    }

    return (((string) $email !== '') && !empty($notices['email'])) ? 'email' : '';
}

/**
 * Whether the "almost your turn" notice is due: the number waits with no
 * more ahead than the place's setting and it was not sent. Pure.
 *
 * @param int|null $ahead   null when the number is not waiting
 * @param int      $setting tell when this many are ahead
 * @param int      $bits    the number's notices
 * @return bool
 */
function pg_waiting_ahead_due($ahead, $setting, $bits)
{
    return ($ahead !== null) && ((int) $ahead <= (int) $setting) && (((int) $bits & pg_waiting_notice_bit('ahead')) === 0);
}

/**
 * What the person's page says about a number: a headline, a line under it,
 * and whether it is over (the page then stops reading). Pure.
 *
 * @param string   $state
 * @param int|null $ahead
 * @param int|null $minutes the estimate as shown, null for none
 * @param string   $desk    the desk's name once called
 * @return array state, final, headline, line
 */
function pg_waiting_status_view($state, $ahead, $minutes, $desk)
{
    $view = array('state' => (string) $state, 'final' => true, 'headline' => '', 'line' => '');

    if ($state === 'waiting') {
        $view['final'] = false;
        $view['headline'] = ((int) $ahead > 0) ? lang(array('string' => '{var:1} ahead of you', 'vars' => array((int) $ahead))) : lang('You are next');
        $view['line'] = ((int) $minutes > 0) ? lang(array('string' => 'About {var:1} minutes', 'vars' => array((int) $minutes))) : '';
    } elseif ($state === 'called') {
        $view['final'] = false;
        $view['headline'] = lang('It is your turn');
        $view['line'] = ($desk !== '') ? lang(array('string' => 'Please go to {var:1}.', 'vars' => array($desk))) : lang('Please go to the desk.');
    } elseif ($state === 'served') {
        $view['headline'] = lang('Thank you');
        $view['line'] = lang('You have been served.');
    } elseif ($state === 'left') {
        $view['headline'] = lang('You left the queue');
        $view['line'] = lang('Take a new number if you still need to be seen.');
    } else {
        $view['headline'] = lang('You were called');
        $view['line'] = lang('You were not here when called. Take a new number if you still need to be seen.');
    }

    return $view;
}

/**
 * A number's page link. Pure.
 *
 * @param string $page  queue.php's address
 * @param int    $id
 * @param string $token pg_waiting_ticket_token()
 * @return string
 */
function pg_waiting_ticket_link($page, $id, $token)
{
    return $page . '?n=' . (int) $id . '&t=' . $token;
}

/**
 * queue.php's full address, for a notice's link.
 *
 * @return string
 */
function pg_waiting_page_url()
{
    return (defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY : '') . '/queue.php';
}

/**
 * Why a number is refused, in the person's words.
 *
 * @param string $code  pg_waiting_join_refusal() or 'way'
 * @param array  $place
 * @return string
 */
function pg_waiting_refusal_text($code, $place)
{
    if ($code === 'cap') {
        return lang('No more numbers are given today. Please come again another day.');
    }

    if ($code === 'closing') {
        return lang('The wait would pass the closing time, so no more numbers are given today.');
    }

    $text = (string) ($place['settings']['closed_text'] ?? '');

    return ($text !== '') ? $text : lang('Numbers are not given now. Please try again within the opening hours.');
}

/**
 * The numbers waiting at a place today, as the order rules take them.
 *
 * @param int $place_id
 * @param int $day
 * @return array list of array(id, service, joined_at, priority, booking_at, notices)
 */
function pg_waiting_place_waiting($place_id, $day)
{
    return (array) db_items("SELECT id, service, joined_at, priority, booking_at, notices FROM waiting_tickets WHERE place_id = '" . (int) $place_id . "' AND state = 'waiting' AND day = '" . (int) $day . "'");
}

/**
 * The estimate in seconds before a number with so many ahead, from the
 * service's handled numbers of the last 14 days and the open desks serving
 * it; null when there is none.
 *
 * @param array  $place
 * @param string $service
 * @param int    $ahead
 * @param int    $now
 * @return int|null
 */
function pg_waiting_estimate($place, $service, $ahead, $now)
{
    $durations = array();

    foreach (db_items("SELECT called_at, done_at FROM waiting_tickets WHERE place_id = '" . (int) $place['id'] . "' AND service = '" . e($service) . "' AND state = 'served' AND called_at > 0 AND done_at > called_at AND joined_at >= '" . ((int) $now - PG_WAITING_HANDLED_DAYS * 86400) . "'") as $row) {
        $durations[] = (int) $row['done_at'] - (int) $row['called_at'];
    }

    $minutes = 0;

    foreach ($place['services'] as $item) {
        if ($item['key'] === $service) {
            $minutes = (int) $item['minutes'];
        }
    }

    $desks = array_map(function ($desk) {
        return array('state' => $desk['state'], 'services' => json_decode((string) $desk['services'], true));
    }, (array) db_items("SELECT state, services FROM waiting_desks WHERE place_id = '" . (int) $place['id'] . "'"));

    return pg_waiting_estimate_seconds($ahead, pg_waiting_handling_seconds($durations, $minutes), pg_waiting_open_desks($desks, $service));
}

/**
 * A new number at a place, when the way is open and the hours, the daily
 * cap and the closing time allow it. The day's count is the service's last
 * plus one; two numbers taken at once meet on the unique key and the later
 * one takes the next count. Its "taken" notice is sent.
 *
 * @param array    $place  pg_waiting_place_get()
 * @param array    $values pg_waiting_join_input() values
 * @param string   $way    qr, site, kiosk or staff
 * @param int|null $now
 * @return array ok, error (pg_waiting_join_refusal() code or 'way', 'write'), id, token, number
 */
function pg_waiting_take($place, $values, $way, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $result = array('ok' => false, 'error' => 'way', 'id' => 0, 'token' => '', 'number' => '');

    if (!pg_waiting_way_open($place, $way)) {
        return $result;
    }

    $day = pg_waiting_today($now);
    $letter = '';

    foreach ($place['services'] as $service) {
        if ($service['key'] === $values['service']) {
            $letter = $service['letter'];
        }
    }

    $waiting = pg_waiting_place_waiting($place['id'], $day);
    $ahead = count(array_filter($waiting, function ($row) use ($values) {
        return $row['service'] === $values['service'];
    }));
    $taken = (int) db_value("SELECT COUNT(*) FROM waiting_tickets WHERE place_id = '" . (int) $place['id'] . "' AND day = '" . $day . "'");
    $closes = pg_waiting_closes_at($place['hours'], $place['exceptions'], $now, new DateTimeZone(date_default_timezone_get()));
    $result['error'] = pg_waiting_join_refusal($taken, $place['settings']['cap'], pg_waiting_estimate($place, $values['service'], $ahead + 1, $now), $now, $closes);

    if ($result['error'] !== '') {
        return $result;
    }

    // Few ahead already: the "taken" notice says so, no second one.
    $notices = pg_waiting_ahead_due($ahead, $place['settings']['notices']['ahead'], 0) ? pg_waiting_notice_bit('ahead') : 0;

    $written = false;

    for ($try = 0; $try < 5; $try++) {
        $count = pg_waiting_next_count(db_value("SELECT MAX(number) FROM waiting_tickets WHERE place_id = '" . (int) $place['id'] . "' AND day = '" . $day . "' AND service = '" . e($values['service']) . "'"));
        $written = db_q("INSERT INTO waiting_tickets (place_id, service, letter, day, number, state, source, party_size, notices, joined_at, name, phone, email) VALUES (?, ?, ?, ?, ?, 'waiting', ?, ?, ?, ?, ?, ?, ?)", array(
            (int) $place['id'], $values['service'], $letter, $day, $count, $way, (int) $values['party_size'], $notices, $now, $values['name'], $values['phone'], $values['email'],
        ));

        if ($written !== false) {
            break;
        }
    }

    if ($written === false) {
        $result['error'] = 'write';
        return $result;
    }

    $id = (int) mysqli_insert_id(db::$con);
    $token = pg_waiting_ticket_token($id, $now, pg_booking_key());
    db_q("UPDATE waiting_tickets SET token_hash = ? WHERE id = ?", array(hash('sha256', $token), $id));

    $result = array('ok' => true, 'error' => '', 'id' => $id, 'token' => $token, 'number' => pg_waiting_number($letter, $count));
    $ticket = db_item("SELECT * FROM waiting_tickets WHERE id = '" . $id . "'");

    if ($ticket) {
        pg_waiting_notify($ticket, $place, 'taken', array('ahead' => $ahead));
    }

    return $result;
}

/**
 * A number by its link: the id and a token matching its stored hash; null
 * otherwise, and once its personal fields were cleared.
 *
 * @param mixed $id
 * @param mixed $token
 * @return array|null the row
 */
function pg_waiting_ticket_find($id, $token)
{
    if (!is_scalar($id) || !is_scalar($token) || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM waiting_tickets WHERE id = '" . (int) $id . "'");

    return ($row && pg_waiting_ticket_token_valid((string) $token, (string) $row['token_hash'])) ? $row : null;
}

/**
 * Where a number stands now, as the person's page shows it: its number,
 * the people ahead and the estimate while it waits, the desk once called.
 *
 * @param array    $ticket the row
 * @param array    $place
 * @param int|null $now
 * @return array number, place, plus pg_waiting_status_view()
 */
function pg_waiting_ticket_status($ticket, $place, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $state = (string) $ticket['state'];
    $ahead = null;
    $minutes = null;
    $desk = '';

    // A number left waiting on an earlier day is no longer called.
    if (($state === 'waiting') && ((int) $ticket['day'] !== pg_waiting_today($now))) {
        $state = 'left';
    }

    if ($state === 'waiting') {
        $ahead = pg_waiting_ahead(pg_waiting_place_waiting($ticket['place_id'], $ticket['day']), $ticket['id'], (int) $place['settings']['grace_minutes'] * 60);
        $minutes = pg_waiting_estimate_minutes(pg_waiting_estimate($place, $ticket['service'], (int) $ahead, $now));
    } elseif ($state === 'called') {
        $desk = (string) db_value("SELECT name FROM waiting_desks WHERE id = '" . (int) $ticket['desk_id'] . "'");
    }

    return array(
        'number' => pg_waiting_number($ticket['letter'], $ticket['number']),
        'place'  => (string) $place['name'],
    ) + pg_waiting_status_view($state, $ahead, $minutes, $desk);
}

/**
 * "Leave the queue": a waiting number leaves; those behind it in its
 * service may now be few enough ahead to be told.
 *
 * @param array    $ticket
 * @param array    $place
 * @param int|null $now
 * @return bool whether it left now
 */
function pg_waiting_leave($ticket, $place, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    if ((db_q("UPDATE waiting_tickets SET state = 'left', done_at = ? WHERE id = ? AND state = 'waiting'", array($now, (int) $ticket['id'])) === false)
        || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    pg_waiting_notify_ahead($place, $ticket['service'], $now);

    return true;
}

/**
 * Tells the numbers of a service that have come within the place's
 * "tell when N are ahead" and were not told yet. Called after the line
 * moves: a number left, and from the desk when it calls.
 *
 * @param array    $place
 * @param string   $service
 * @param int|null $now
 * @return int the numbers told
 */
function pg_waiting_notify_ahead($place, $service, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $waiting = pg_waiting_place_waiting($place['id'], pg_waiting_today($now));
    $grace = (int) $place['settings']['grace_minutes'] * 60;
    $told = 0;

    foreach ($waiting as $row) {
        if ($row['service'] !== $service) {
            continue;
        }

        $ahead = pg_waiting_ahead($waiting, $row['id'], $grace);

        if (pg_waiting_ahead_due($ahead, $place['settings']['notices']['ahead'], $row['notices'])) {
            $ticket = db_item("SELECT * FROM waiting_tickets WHERE id = '" . (int) $row['id'] . "'");
            $told += ($ticket && pg_waiting_notify($ticket, $place, 'ahead', array('ahead' => $ahead))) ? 1 : 0;
        }
    }

    return $told;
}

/**
 * Sends one notice of a number, once: its bit is claimed first with one
 * conditional UPDATE, so two requests never send it twice. A phone gets
 * WhatsApp when the place and the event are set for it (a utility
 * template), else SMS; an e-mail only when no phone is given. Quiet:
 * nothing set up sends nothing.
 *
 * @param array  $ticket the row
 * @param array  $place
 * @param string $kind   taken, ahead or called
 * @param array  $extra  ahead, desk
 * @return bool whether a message was queued
 */
function pg_waiting_notify($ticket, $place, $kind, $extra = array())
{
    $notices = $place['settings']['notices'];
    $channel = pg_waiting_notice_channel($notices, $ticket['phone'], $ticket['email']);
    $bit = pg_waiting_notice_bit($kind);

    if (($channel === '') || ($bit === 0)) {
        return false;
    }

    if ((db_q("UPDATE waiting_tickets SET notices = notices | ? WHERE id = ? AND (notices & ?) = 0", array($bit, (int) $ticket['id'], $bit)) === false)
        || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    $values = array(
        'place'  => (string) $place['name'],
        'number' => pg_waiting_number($ticket['letter'], $ticket['number']),
        'desk'   => (string) ($extra['desk'] ?? ''),
        'ahead'  => (string) (int) ($extra['ahead'] ?? 0),
        'link'   => pg_waiting_ticket_link(pg_waiting_page_url(), $ticket['id'], pg_waiting_ticket_token($ticket['id'], $ticket['joined_at'], pg_booking_key())),
    );
    $text = pg_waiting_notice_text($kind, $notices['texts'][$kind] ?? '', $values);
    $reference = 'queue:' . (int) $ticket['id'] . ':' . $kind;

    if ($channel === 'email') {
        return (bool) email(array(
            'to'                 => (string) $ticket['email'],
            'from_name'          => defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : '',
            'from_email_address' => defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : '',
            'subject'            => $values['place'] . ': ' . $values['number'],
            'body'               => $text,
            'queue'              => true,
        ));
    }

    $to = pg_sms_dial($ticket['phone']);

    if (!empty($notices['whatsapp']) && pg_whatsapp_message_queue('queue_' . $kind, $to, $values, $reference)) {
        return true;
    }

    if (empty($notices['sms'])) {
        return false;
    }

    return !empty(pg_sms_enqueue($to, $text, 'queue', array('reference' => $reference, 'expires_at' => time() + PG_WAITING_NOTICE_EXPIRES))['ok']);
}

/**
 * Whether this address took too many numbers lately: 10 in 10 minutes. A
 * refusal is logged as the firewall's other limits are.
 *
 * @return int seconds to wait, 0 when it may take one
 */
function pg_waiting_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return 0;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || (function_exists('waf_ip_is_counted') && !waf_ip_is_counted($ip)) || !waf_rate_exceeded(waf_ip_subject($ip), 'queue_take', 10, 600)) {
        return 0;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-queue-take', 'rate', 5, 'queue.php', '10/10min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-queue-take'));
    }

    return 600;
}
