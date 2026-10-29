<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's own calendar on their Work Calendar: the secret
 * iCal address Google, Outlook or Apple give for a calendar ("secret address
 * in iCal format"), read by the job that reads the public holiday calendars
 * (ws_holiday_feeds, includes/workspace/workdays.php) and drawn as a
 * read-only layer for that person alone.
 *
 * A person's address is a ws_holiday_feeds row with their user_id; the rows
 * of user_id 0 are the site's public holiday calendars and are read as
 * before. The secret address opens the person's whole calendar, so it is
 * never kept or shown in the clear: url holds one blob sealed with the site's
 * key, the address together with the on/off choice and the events last read.
 * The events never go into ws_events: every other reader of the calendar
 * (the plan items of the team, the days off, the briefing, the calendar
 * subscription, the API) reads that table, and none of them may see a
 * person's private calendar. A site without an encryption key cannot keep
 * such an address and refuses it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most addresses one person keeps. */
define('WS_PERSONAL_ICAL_MAX', 5);

/** How far back and ahead of today a person's events are kept, in days. */
define('WS_PERSONAL_ICAL_PAST_DAYS', 62);
define('WS_PERSONAL_ICAL_AHEAD_DAYS', 366);

/** The most bytes the kept events take before sealing: the sealed blob stays within url (TEXT). */
define('WS_PERSONAL_ICAL_PAYLOAD_MAX', 40000);

/** The most occurrences one repeating event gives. */
define('WS_PERSONAL_ICAL_REPEAT_MAX', 500);

/**
 * Whether the upgrade that gives a calendar address its owner has run
 * (2026.11.0, 11.0.117).
 *
 * @return bool
 */
function ws_personal_ical_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_workdays_ready() && waf_table_has_column('ws_holiday_feeds', 'user_id');
    }

    return $ready;
}

/**
 * The filter that keeps a reader to the rows of one owner: a person's own
 * addresses, or with 0 the site's public holiday calendars.
 *
 * @param int $user_id
 * @return string
 */
function ws_holiday_feed_owner_where($user_id)
{
    return "user_id = '" . max(0, (int) $user_id) . "'";
}

/**
 * Checks an address a person pasted: https, or webcal (https under another
 * name), a host name and no host inside the server's own network. The name
 * is resolved and checked again when it is called (api_http_request()).
 *
 * @param string $url
 * @return array ok, url (as it is called), error
 */
function ws_personal_ical_address($url)
{
    $refuse = function ($error) {
        return array('ok' => false, 'url' => '', 'error' => $error);
    };

    $url = ws_holiday_feed_url($url);
    $parts = ($url !== '') ? @parse_url($url) : false;

    if (!is_array($parts) || (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') || empty($parts['host'])
        || preg_match('/\s/', $url) || (strlen($url) > 2000)) {
        return $refuse(lang('Write the secret address of your calendar in iCal format (https://… or webcal://…).'));
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        return $refuse(lang('Write the address without a user name or password.'));
    }

    $host = strtolower(trim((string) $parts['host'], '[]'));

    if (($host === 'localhost') || preg_match('/\.(localhost|local|internal|lan|home|intranet)$/', $host)
        || ((filter_var($host, FILTER_VALIDATE_IP) !== false) && !api_http_address_is_allowed($host))) {
        return $refuse(lang('That address is inside this network and is not called.'));
    }

    return array('ok' => true, 'url' => $url, 'error' => '');
}

/**
 * Seals data with a key, as encrypt_string_with_iv() does with the site's
 * key: "<ciphertext>:<iv>", both base64.
 *
 * @param array  $data
 * @param string $secret
 * @return string '' when there is no key
 */
function ws_personal_ical_seal_with($data, $secret)
{
    $json = json_encode($data);

    if (((string) $secret === '') || ($json === false) || !function_exists('openssl_encrypt')) {
        return '';
    }

    $key = substr(hash('sha256', (string) $secret, true), 0, 32);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($json, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    return ($cipher === false) ? '' : base64_encode($cipher) . ':' . base64_encode($iv);
}

/**
 * @param string $sealed
 * @param string $secret
 * @return array the data, or an empty array when it cannot be read
 */
function ws_personal_ical_unseal_with($sealed, $secret)
{
    $parts = explode(':', (string) $sealed, 2);

    if (((string) $secret === '') || (count($parts) !== 2) || !function_exists('openssl_decrypt')) {
        return array();
    }

    $cipher = base64_decode($parts[0], true);
    $iv = base64_decode($parts[1], true);

    if (($cipher === false) || ($iv === false) || (strlen($iv) !== 16)) {
        return array();
    }

    $json = openssl_decrypt($cipher, 'aes-256-cbc', substr(hash('sha256', (string) $secret, true), 0, 32), OPENSSL_RAW_DATA, $iv);
    $data = ($json !== false) ? json_decode($json, true) : null;

    return is_array($data) ? $data : array();
}

/**
 * The site's key, '' when it has none.
 *
 * @return string
 */
function ws_personal_ical_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}

/**
 * A person's row with its sealed part read: url, on, events (empty when the
 * site's key changed, and the address has to be added again).
 *
 * @param array $row
 * @return array
 */
function ws_personal_ical_open($row)
{
    $data = ws_personal_ical_unseal_with($row['url'] ?? '', ws_personal_ical_key());

    return $row + array(
        'address' => (string) ($data['url'] ?? ''),
        'on'      => !empty($data['on']),
        'layer'   => isset($data['events']) && is_array($data['events']) ? $data['events'] : array(),
    );
}

/**
 * Writes a person's row back with its sealed part.
 *
 * @param int    $feed_id
 * @param string $address
 * @param bool   $on
 * @param array  $events
 * @param string $more    further SET assignments, already escaped
 * @return void
 */
function ws_personal_ical_store($feed_id, $address, $on, $events, $more = '')
{
    $sealed = ws_personal_ical_seal_with(array('url' => $address, 'on' => (bool) $on, 'events' => array_values($events)), ws_personal_ical_key());

    db("UPDATE ws_holiday_feeds SET url = '" . e($sealed) . "', updated_at = '" . time() . "'" . (($more !== '') ? ', ' . $more : '') . "
        WHERE id = '" . (int) $feed_id . "' AND user_id > 0");
}

/**
 * A person's own addresses, oldest first, their sealed part read.
 *
 * @param int $user_id
 * @return array[]
 */
function ws_personal_ical_feeds($user_id)
{
    if (!ws_personal_ical_ready() || ((int) $user_id <= 0)) {
        return array();
    }

    return array_map('ws_personal_ical_open', (array) db_items("SELECT * FROM ws_holiday_feeds WHERE " . ws_holiday_feed_owner_where($user_id) . " ORDER BY id"));
}

/**
 * One of a person's addresses, or null when it is not theirs.
 *
 * @param int $user_id
 * @param int $feed_id
 * @return array|null
 */
function ws_personal_ical_feed($user_id, $feed_id)
{
    if (!ws_personal_ical_ready() || ((int) $user_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_holiday_feeds WHERE id = '" . (int) $feed_id . "' AND " . ws_holiday_feed_owner_where($user_id));

    return is_array($row) ? ws_personal_ical_open($row) : null;
}

/**
 * Adds a person's address and reads it.
 *
 * @param int    $user_id
 * @param string $url
 * @param string $name
 * @return array ok, error, feed_id, events
 */
function ws_personal_ical_add($user_id, $url, $name)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'feed_id' => 0, 'events' => 0);
    };

    if (!ws_personal_ical_ready() || ((int) $user_id <= 0)) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if ((ws_personal_ical_key() === '') || !function_exists('openssl_encrypt')) {
        return $fail(lang('This site has no encryption key, so a secret calendar address cannot be kept.'));
    }

    $address = ws_personal_ical_address($url);

    if (!$address['ok']) {
        return $fail($address['error']);
    }

    if (count(ws_personal_ical_feeds($user_id)) >= WS_PERSONAL_ICAL_MAX) {
        return $fail(lang(array('string' => 'You can add up to {var:1} calendars.', 'vars' => WS_PERSONAL_ICAL_MAX)));
    }

    $name = mb_substr(trim((string) $name), 0, 120);

    if ($name === '') {
        $name = (string) parse_url($address['url'], PHP_URL_HOST);
    }

    db("INSERT INTO ws_holiday_feeds (user_id, name, url, department_id, public_only, created_by, created_at, updated_at)
        VALUES ('" . (int) $user_id . "', '" . e($name) . "', '', 0, 0, '" . (int) $user_id . "', '" . time() . "', '" . time() . "')");

    $feed_id = (int) mysqli_insert_id(db::$con);
    ws_personal_ical_store($feed_id, $address['url'], true, array());
    $read = ws_holiday_feed_sync($feed_id);

    return array('ok' => $read['ok'], 'error' => $read['error'], 'feed_id' => $feed_id, 'events' => $read['events']);
}

/**
 * Reads a person's address again (called by ws_holiday_feed_sync(), the
 * screen and the scheduled run). A calendar switched off is not called: its
 * events are dropped and it is marked as read, so the run moves on.
 *
 * @param array $row the ws_holiday_feeds row
 * @return array ok, error, events
 */
function ws_personal_ical_sync($row)
{
    $feed = ws_personal_ical_open($row);
    $now = time();

    $fail = function ($message) use ($feed) {
        db("UPDATE ws_holiday_feeds SET sync_error = '" . e(mb_substr($message, 0, 500)) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $feed['id'] . "'");

        return array('ok' => false, 'error' => $message, 'events' => 0);
    };

    // An account that left the team has its calendar removed, not read.
    if (!ws_is_team_member($feed['user_id'])) {
        db("DELETE FROM ws_holiday_feeds WHERE id = '" . (int) $feed['id'] . "' AND user_id > 0");

        return array('ok' => false, 'error' => lang('That calendar could not be found.'), 'events' => 0);
    }

    if ($feed['address'] === '') {
        return $fail(lang('The address can no longer be read on this site: remove the calendar and add it again.'));
    }

    if (!$feed['on']) {
        ws_personal_ical_store($feed['id'], $feed['address'], false, array(), "events = 0, synced_at = '" . $now . "', sync_error = ''");

        return array('ok' => true, 'error' => '', 'events' => 0);
    }

    if (!function_exists('api_http_request') && is_file(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    if (!function_exists('api_http_request')) {
        return $fail(lang('This server cannot call other addresses.'));
    }

    $answer = api_http_request('GET', $feed['address'], array(
        'headers'  => array('Accept: text/calendar'),
        'timeout'  => 20,
        'max_body' => 4 * 1024 * 1024,
        'agent'    => 'calendar',
    ));

    if (!$answer['ok']) {
        // The error names the status or the network, never the address.
        return $fail(($answer['status'] > 0) ? lang(array('string' => 'The address answered {var:1}.', 'vars' => (int) $answer['status'])) : lang('The calendar could not be reached.'));
    }

    $body = (string) $answer['body'];

    if ((substr($body, 0, 2) === "\x1f\x8b") && function_exists('gzdecode')) {
        $decoded = @gzdecode($body);
        $body = is_string($decoded) ? $decoded : $body;
    }

    if (strpos($body, 'BEGIN:VCALENDAR') === false) {
        return $fail(lang('That address did not answer with a calendar.'));
    }

    $events = ws_personal_ical_trim(ws_personal_ical_events($body, strtotime('today -' . WS_PERSONAL_ICAL_PAST_DAYS . ' days'), strtotime('today +' . WS_PERSONAL_ICAL_AHEAD_DAYS . ' days')));

    ws_personal_ical_store($feed['id'], $feed['address'], true, $events, "events = '" . count($events) . "', synced_at = '" . $now . "', sync_error = ''");

    return array('ok' => true, 'error' => '', 'events' => count($events));
}

/**
 * Switches a person's calendar on or off: off hides it and stops reading it,
 * on reads it again at once.
 *
 * @param int  $user_id
 * @param int  $feed_id
 * @param bool $on
 * @return array|null the read, or null when the calendar is not theirs
 */
function ws_personal_ical_switch($user_id, $feed_id, $on)
{
    $feed = ws_personal_ical_feed($user_id, $feed_id);

    if (($feed === null) || ($feed['address'] === '')) {
        return null;
    }

    ws_personal_ical_store($feed['id'], $feed['address'], (bool) $on, array(), "events = 0, synced_at = 0, sync_error = ''");

    return ws_holiday_feed_sync($feed['id']);
}

/**
 * Removes a person's calendar with what was read from it.
 *
 * @param int $user_id
 * @param int $feed_id
 * @return bool
 */
function ws_personal_ical_remove($user_id, $feed_id)
{
    if (ws_personal_ical_feed($user_id, $feed_id) === null) {
        return false;
    }

    db("DELETE FROM ws_holiday_feeds WHERE id = '" . (int) $feed_id . "' AND " . ws_holiday_feed_owner_where($user_id));

    return true;
}

/**
 * The events a person's calendars switched on hold, with the name of the
 * calendar each came from.
 *
 * @param int $user_id
 * @return array[] title, starts_at, ends_at, all_day, layer
 */
function ws_personal_ical_layer($user_id)
{
    $events = array();

    foreach (ws_personal_ical_feeds($user_id) as $feed) {
        if (!$feed['on']) {
            continue;
        }

        foreach ($feed['layer'] as $event) {
            $events[] = $event + array('layer' => (string) $feed['name']);
        }
    }

    return $events;
}

/**
 * Lays a person's events on the days of the Work Calendar, read-only: no
 * plan item stands behind them (id 0), and they name their calendar.
 *
 * @param array  $days  date => day, each with an events list
 * @param array  $events
 * @param string $layer the calendar's name, when the events do not carry one
 * @return array the days
 */
function ws_personal_ical_place($days, $events, $layer = '')
{
    foreach ($events as $event) {
        foreach (array_keys($days) as $date) {
            $day_start = strtotime($date . ' 00:00:00');
            $day_end = strtotime($date . ' 00:00:00 +1 day');

            if (((int) $event['starts_at'] >= $day_end) || ((int) $event['ends_at'] < $day_start)) {
                continue;
            }

            $days[$date]['events'][] = array(
                'id'          => 0,
                'kind'        => 'other',
                'title'       => (string) $event['title'],
                'time'        => !empty($event['all_day']) ? '' : date('H:i', max((int) $event['starts_at'], $day_start)),
                'scope'       => 'people',
                'people'      => array(),
                'meeting_url' => '',
                'layer'       => (string) ($event['layer'] ?? $layer),
                'readonly'    => true,
            );
        }
    }

    return $days;
}

/**
 * Keeps as many events as fit the sealed column, the nearest first.
 *
 * @param array[] $events sorted by start
 * @return array[]
 */
function ws_personal_ical_trim($events)
{
    $kept = array();
    $size = 2;

    foreach ($events as $event) {
        $size += strlen((string) json_encode($event)) + 1;

        if ($size > WS_PERSONAL_ICAL_PAYLOAD_MAX) {
            break;
        }

        $kept[] = $event;
    }

    return $kept;
}

/**
 * A DTSTART, DTEND, EXDATE or RECURRENCE-ID value as a moment: a date is
 * midnight in the site's zone, a time ending in Z is UTC, one with a TZID is
 * in that zone (the site's when PHP does not know the name, as with
 * Outlook's Windows names), a floating time is in the site's zone.
 *
 * @param array $property value, params
 * @return DateTime|null
 */
function ws_personal_ical_moment($property)
{
    $value = trim((string) ($property['value'] ?? ''));

    if (!preg_match('/^(\d{8})(?:T(\d{6})(Z?))?$/', $value, $match)) {
        return null;
    }

    $zone = null;

    if (!empty($match[3])) {
        $zone = new DateTimeZone('UTC');
    } elseif (preg_match('/(?:^|;)TZID="?([^";]+)"?/i', (string) ($property['params'] ?? ''), $tzid)) {
        try {
            $zone = new DateTimeZone($tzid[1]);
        } catch (Exception $e) {
            $zone = null;
        }
    }

    $moment = DateTime::createFromFormat('!YmdHis', $match[1] . (isset($match[2]) && $match[2] !== '' ? $match[2] : '000000'), $zone ?: new DateTimeZone(date_default_timezone_get()));

    return ($moment instanceof DateTime) ? $moment : null;
}

/**
 * The events of a person's calendar between two moments, timed and all-day,
 * a repeating event given as its occurrences: FREQ DAILY, WEEKLY (with
 * BYDAY), MONTHLY and YEARLY with INTERVAL, COUNT and UNTIL; EXDATE leaves an
 * occurrence out and an event with a RECURRENCE-ID takes its place. A rule
 * this reader does not follow (BYDAY on a month, BYSETPOS…) gives its first
 * occurrence only. Cancelled events are left out.
 *
 * @param string $text
 * @param int    $from
 * @param int    $to
 * @return array[] title, starts_at, ends_at, all_day; sorted by start
 */
function ws_personal_ical_events($text, $from, $to)
{
    $vevents = ws_ical_vevents($text);
    $moved = array();

    foreach ($vevents as $event) {
        if (isset($event['RECURRENCE-ID'])) {
            $original = ws_personal_ical_moment($event['RECURRENCE-ID']);

            if ($original) {
                $moved[trim((string) ($event['UID']['value'] ?? '')) . '|' . $original->getTimestamp()] = true;
            }
        }
    }

    $out = array();
    $weekdays = array('MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7);

    foreach ($vevents as $event) {
        $start = ws_personal_ical_moment($event['DTSTART'] ?? array());

        if (!$start || (strtoupper(trim((string) ($event['STATUS']['value'] ?? ''))) === 'CANCELLED')) {
            continue;
        }

        $all_day = (strpos((string) $event['DTSTART']['value'], 'T') === false);
        $end = isset($event['DTEND']) ? ws_personal_ical_moment($event['DTEND']) : null;
        $length = ($end && ($end > $start)) ? ($end->getTimestamp() - $start->getTimestamp()) : ($all_day ? 86400 : 0);
        $title = mb_substr(ws_ical_text($event['SUMMARY']['value'] ?? ''), 0, 120);
        $title = ($title !== '') ? $title : lang('Busy');
        $uid = trim((string) ($event['UID']['value'] ?? ''));

        $skip = array();

        foreach ((array) ($event['EXDATE*'] ?? array()) as $property) {
            foreach (explode(',', (string) $property['value']) as $value) {
                $moment = ws_personal_ical_moment(array('value' => $value, 'params' => $property['params']));

                if ($moment) {
                    $skip[$moment->getTimestamp()] = true;
                }
            }
        }

        // The occurrences: the event itself, then those its rule gives.
        $rule = array();

        foreach (explode(';', strtoupper((string) ($event['RRULE']['value'] ?? ''))) as $pair) {
            if (strpos($pair, '=') !== false) {
                list($key, $value) = explode('=', $pair, 2);
                $rule[$key] = $value;
            }
        }

        $frequency = isset($event['RECURRENCE-ID']) ? '' : ($rule['FREQ'] ?? '');
        $steps = array('DAILY' => 'day', 'WEEKLY' => 'week', 'MONTHLY' => 'month', 'YEARLY' => 'year');

        if (!isset($steps[$frequency]) || isset($rule['BYSETPOS']) || isset($rule['BYMONTHDAY']) || isset($rule['BYYEARDAY'])
            || (isset($rule['BYDAY']) && ($frequency !== 'WEEKLY'))) {
            $frequency = '';
        }

        $interval = max(1, (int) ($rule['INTERVAL'] ?? 1));
        $count = isset($rule['COUNT']) ? max(1, (int) $rule['COUNT']) : 0;
        $until = isset($rule['UNTIL']) ? ws_personal_ical_moment(array('value' => $rule['UNTIL'])) : null;
        $days = array();

        if (($frequency === 'WEEKLY') && isset($rule['BYDAY'])) {
            foreach (explode(',', $rule['BYDAY']) as $day) {
                if (isset($weekdays[$day])) {
                    $days[] = $weekdays[$day];
                }
            }

            sort($days);
        }

        $given = 0;

        for ($period = 0; $period < WS_PERSONAL_ICAL_REPEAT_MAX; $period++) {
            $base = clone $start;

            if ($period > 0) {
                if ($frequency === '') {
                    break;
                }

                $base->modify('+' . ($period * $interval) . ' ' . $steps[$frequency]);
            }

            // A week with BYDAY gives each of its days, from its Monday.
            $candidates = array($base);

            if (!empty($days)) {
                $candidates = array();
                $monday = clone $base;
                $monday->modify('-' . ((int) $monday->format('N') - 1) . ' days');

                foreach ($days as $day) {
                    $candidate = clone $monday;
                    $candidate->modify('+' . ($day - 1) . ' days');

                    if ($candidate >= $start) {
                        $candidates[] = $candidate;
                    }
                }
            }

            foreach ($candidates as $moment) {
                $at = $moment->getTimestamp();

                if (($until && ($moment > $until)) || ($count && ($given >= $count)) || ($at > $to)) {
                    break 2;
                }

                // A month without the day (the 31st, 29 February) has no occurrence.
                if ((($frequency === 'MONTHLY') || ($frequency === 'YEARLY')) && ($moment->format('d') !== $start->format('d'))) {
                    continue;
                }

                $given++;

                if (isset($skip[$at]) || (($frequency !== '') && isset($moved[$uid . '|' . $at]))) {
                    continue;
                }

                if (($at + $length) < $from) {
                    continue;
                }

                $out[] = array(
                    'title'     => $title,
                    'starts_at' => $at,
                    'ends_at'   => $all_day ? ($at + $length - 1) : ($at + $length),
                    'all_day'   => $all_day,
                );
            }
        }
    }

    usort($out, function ($a, $b) {
        return ($a['starts_at'] - $b['starts_at']) ?: strcmp($a['title'], $b['title']);
    });

    return $out;
}

// ── The card on the calendar subscription screen ──────────────────────

/**
 * Handles the card's forms; every action reaches only the person's own
 * calendars.
 *
 * @param int      $user_id
 * @param string   $action own_add | own_sync | own_switch | own_remove
 * @param liveform $liveform
 * @return bool whether the action was the card's
 */
function ws_personal_ical_post($user_id, $action, $liveform)
{
    if (strpos($action, 'own_') !== 0) {
        return false;
    }

    $feed_id = (int) ($_POST['own_feed_id'] ?? 0);
    $username = (string) ($_SESSION['sessionusername'] ?? '');

    switch ($action) {
        case 'own_add':
            $added = ws_personal_ical_add($user_id, (string) ($_POST['own_url'] ?? ''), (string) ($_POST['own_name'] ?? ''));

            if ($added['feed_id'] <= 0) {
                $liveform->add_error(h($added['error']));
            } else {
                log_activity(lang('added their own calendar to their Work Calendar'), $username);

                if ($added['ok']) {
                    $liveform->add_notice(lang(array('string' => 'Your calendar was read: {var:1} events.', 'vars' => $added['events'])));
                } else {
                    $liveform->add_error(lang(array('string' => 'The calendar was added, but it could not be read: {var:1}', 'vars' => h($added['error']))));
                }
            }

            return true;

        case 'own_sync':
        case 'own_switch':
            $read = ($action === 'own_sync')
                ? ((ws_personal_ical_feed($user_id, $feed_id) !== null) ? ws_holiday_feed_sync($feed_id) : null)
                : ws_personal_ical_switch($user_id, $feed_id, !empty($_POST['own_on']));

            if ($read === null) {
                $liveform->add_error(lang('That calendar could not be found.'));
            } elseif (!$read['ok']) {
                $liveform->add_error(lang(array('string' => 'The calendar could not be read: {var:1}', 'vars' => h($read['error']))));
            } elseif (($action === 'own_switch') && empty($_POST['own_on'])) {
                $liveform->add_notice(lang('The calendar is hidden and no longer read.'));
            } else {
                $liveform->add_notice(lang(array('string' => 'Your calendar was read: {var:1} events.', 'vars' => $read['events'])));
            }

            return true;

        case 'own_remove':
            if (ws_personal_ical_remove($user_id, $feed_id)) {
                log_activity(lang('removed their own calendar from their Work Calendar'), $username);
                $liveform->add_notice(lang('The calendar was removed with its events.'));
            }

            return true;
    }

    return false;
}

/**
 * The card: the person's own calendars, each with its state, and the form
 * that adds one. The address is never shown again, only its host.
 *
 * @param int $user_id
 * @return string
 */
function ws_personal_ical_card($user_id)
{
    if (!ws_personal_ical_ready()) {
        return '';
    }

    $form = function ($action, $feed_id, $inner) {
        return '<form method="post" action="workspace_calendar_feed.php" class="d-inline disable_shortcut">' . get_token_field()
            . '<input type="hidden" name="feed_action" value="' . h($action) . '"><input type="hidden" name="own_feed_id" value="' . (int) $feed_id . '">' . $inner . '</form>';
    };

    $feeds = ws_personal_ical_feeds($user_id);
    $rows = '';

    foreach ($feeds as $feed) {
        $host = ($feed['address'] !== '') ? (string) parse_url($feed['address'], PHP_URL_HOST) : '';

        if ((string) $feed['sync_error'] !== '') {
            $state = '<span class="fg-danger">' . h($feed['sync_error']) . '</span>';
        } elseif (!$feed['on']) {
            $state = h(lang('Hidden'));
        } else {
            $state = h(lang(array('string' => '{var:1} events', 'vars' => (int) $feed['events'])))
                . ((int) $feed['synced_at'] > 0 ? ' · ' . h(lang(array('string' => 'read {var:1}', 'vars' => date('d.m.Y H:i', (int) $feed['synced_at'])))) : '');
        }

        $rows .= '
            <div class="ws-feed-row">
                <i class="bi bi-calendar2-heart" aria-hidden="true"></i>
                <div class="ws-grow">
                    <div class="fw-semibold">' . h($feed['name']) . '</div>
                    <div class="small fg-secondary text-break">' . h($host) . ' · ' . $state . '</div>
                </div>
                ' . $form('own_switch', $feed['id'], '<input type="hidden" name="own_on" value="' . ($feed['on'] ? 0 : 1) . '"><button type="submit" class="btn btn-sm btn-text theme-secondary"><i class="bi ' . ($feed['on'] ? 'bi-eye-slash' : 'bi-eye') . ' me-1" aria-hidden="true"></i>' . h($feed['on'] ? lang('Hide') : lang('Show')) . '</button>') . '
                ' . ($feed['on'] ? $form('own_sync', $feed['id'], '<button type="submit" class="btn btn-sm btn-text theme-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Read now')) . '</button>') : '') . '
                ' . $form('own_remove', $feed['id'], '<button type="submit" class="btn btn-sm btn-text theme-secondary" title="' . h(lang('Remove')) . '" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>') . '
            </div>';
    }

    $add = (count($feeds) < WS_PERSONAL_ICAL_MAX) ? '
                <form method="post" action="workspace_calendar_feed.php" class="disable_shortcut mt-3">
                    ' . get_token_field() . '
                    <input type="hidden" name="feed_action" value="own_add">
                    <div class="row g-3 align-items-end">
                        <div class="lg:col-7">
                            <label class="form-label small" for="ws_own_url">' . h(lang('Secret address in iCal format')) . '</label>
                            <input class="form-control form-control-sm" type="password" name="own_url" id="ws_own_url" required autocomplete="off" spellcheck="false" placeholder="https://…/basic.ics">
                        </div>
                        <div class="col-7 lg:col-3">
                            <label class="form-label small" for="ws_own_name">' . h(lang('Name')) . '</label>
                            <input class="form-control form-control-sm" type="text" name="own_name" id="ws_own_name" maxlength="120">
                        </div>
                        <div class="col-5 lg:col-2 text-end">
                            <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5">' . h(lang('Add')) . '</button>
                        </div>
                    </div>
                </form>' : '';

    return '
        <div class="card mb-5" id="ws-own-calendars">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-calendar2-heart me-3" aria-hidden="true"></i>' . h(lang('Your own calendar on the Work Calendar')) . '</h2>
                <p class="small fg-secondary">' . h(lang('Paste the secret iCal address of your Google, Outlook or Apple calendar: its events appear on your Work Calendar, read-only and only for you. They are read again every day and never count as days off. The address is kept encrypted and is not shown again.')) . '</p>
                ' . $rows . '
                ' . $add . '
            </div>
        </div>';
}
