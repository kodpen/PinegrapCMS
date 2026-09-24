<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the working calendar: the days the company works, the days it
 * is closed, and the next working day after a date.
 *
 * The working week is the site's default (Workspace Settings > Defaults, a bit
 * mask with Monday as bit 0). A day off is an all-day plan item of kind
 * holiday, for the whole company or for one department: added on the
 * settings screen, planned on the board, or read from an iCal address that
 * publishes a country's public holidays (ws_holiday_feeds). A holiday marked
 * yearly comes back on the same day every year; ws_events_between() brings
 * it into every year it is asked for.
 *
 * What it moves: a repeating task's copy that falls on a day off goes to the
 * next working day (includes/workspace/recurrence.php), and the task drawer
 * warns about a date picked by hand and offers the same move. A person's own
 * working days and leave move nothing: the planning board warns about those.
 * A task that belongs to a department follows that department's days off as
 * well as the company's.
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
 * The most days of a public holiday calendar that are kept, and how far back
 * and ahead of today they are read.
 */
define('WS_HOLIDAY_FEED_LIMIT', 1000);
define('WS_HOLIDAY_FEED_PAST_DAYS', 400);
define('WS_HOLIDAY_FEED_AHEAD_DAYS', 1100);

/**
 * Whether the upgrade that holds the calendar has run.
 *
 * @return bool
 */
function ws_workdays_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_events', 'yearly')
            && waf_table_has_column('ws_holiday_feeds', 'url')
            && waf_table_has_column('ws_tasks', 'recurrence_date');
    }

    return $ready;
}

/**
 * Whether a day read from a calendar address can be left out one by one
 * (ws_events.skipped, added to the same upgrade step later).
 *
 * @return bool
 */
function ws_holiday_skip_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('ws_events', 'skipped');
    }

    return $ready;
}

/**
 * The company's working week as a bit mask, Monday as bit 0.
 *
 * @return int
 */
function ws_workweek()
{
    return ((int) WS_WORKDAYS & 127) ?: 31;
}

/**
 * The days off of one year: date => [name, department_id (0 for the whole
 * company)], each day of a holiday that runs several.
 *
 * @param int $year
 * @return array
 */
function ws_holiday_year($year)
{
    static $cache = array();

    $year = (int) $year;

    if (isset($cache[$year])) {
        return $cache[$year];
    }

    $from = sprintf('%04d-01-01', $year);
    $to = sprintf('%04d-12-31', $year);
    $days = array();

    foreach (ws_events_between($from, $to) as $event) {
        if (((string) $event['kind'] !== 'holiday') || ((int) $event['all_day'] !== 1) || ((string) $event['scope'] === 'people')) {
            continue;
        }

        $department_id = ((string) $event['scope'] === 'department') ? (int) $event['department_id'] : 0;
        $first = max($from, date('Y-m-d', (int) $event['starts_at']));
        $last = min($to, date('Y-m-d', (int) $event['ends_at']));

        foreach (ws_days($first, $last) as $date) {
            $days[$date][] = array('name' => (string) $event['title'], 'department_id' => $department_id);
        }
    }

    return $cache[$year] = $days;
}

/**
 * Why a day is not worked, or '' when it is.
 *
 * @param string $date          Y-m-d
 * @param int    $department_id the task's department, 0 for none
 * @return string
 */
function ws_day_off($date, $department_id = 0)
{
    $time = strtotime($date . ' 12:00:00');

    if ($time === false) {
        return '';
    }

    foreach (ws_holiday_year((int) date('Y', $time))[$date] ?? array() as $holiday) {
        if (($holiday['department_id'] === 0) || ($holiday['department_id'] === (int) $department_id)) {
            return $holiday['name'];
        }
    }

    if (!(ws_workweek() & (1 << ((int) date('N', $time) - 1)))) {
        return ws_recurrence_weekday_names()[(int) date('N', $time)];
    }

    return '';
}

/**
 * The first working day on or after a date. A calendar with no working day
 * in two months (a week with none ticked cannot be saved, but a long closure
 * can) keeps the date as it was.
 *
 * @param string $date          Y-m-d
 * @param int    $department_id
 * @return string Y-m-d
 */
function ws_next_workday($date, $department_id = 0)
{
    $cursor = strtotime($date . ' 12:00:00');

    if ($cursor === false) {
        return $date;
    }

    for ($i = 0; $i < 62; $i++) {
        $day = date('Y-m-d', $cursor);

        if (ws_day_off($day, $department_id) === '') {
            return $day;
        }

        $cursor = strtotime('+1 day', $cursor);
    }

    return $date;
}

/**
 * What the task drawer needs to tell a day off from a working day without
 * asking: the week, and the days off from last year to two years ahead.
 *
 * @return array week (mask), holidays ([date, name, department_id], ...)
 */
function ws_workdays_js_config()
{
    $holidays = array();
    $year = (int) date('Y');

    for ($y = $year - 1; $y <= $year + 2; $y++) {
        foreach (ws_holiday_year($y) as $date => $entries) {
            foreach ($entries as $entry) {
                $holidays[] = array($date, $entry['name'], $entry['department_id']);
            }
        }
    }

    return array('week' => ws_workweek(), 'holidays' => $holidays);
}

/**
 * The texts the task drawer uses to talk about days off.
 *
 * @return array key => text
 */
function ws_workdays_js_strings()
{
    return array(
        'day_off_week'      => ws_js_template('{var:1} {var:2} is not a working day.', 2),
        'day_off_holiday'   => ws_js_template('{var:1} is a day off: {var:2}.', 2),
        'move_to_workday'   => ws_js_template('Move to {var:1}', 1),
        'repeat_moved'      => ws_js_template('This copy was due on {var:1}, a day off, and moved to the next working day.', 1),
        'repeat_moved_note' => lang('Copies marked with an arrow fall on a day off and move to the next working day.'),
        'moved_from'        => ws_js_template('moved from {var:1}', 1),
    );
}

// ── Days off kept by hand ───────────────────────────────────────────────

/**
 * Adds a day off, or a run of them.
 *
 * @param array $viewer
 * @param array $data name, from, to (empty for one day), department_id (0 for the company), yearly
 * @return array ok, error, event_id
 */
function ws_holiday_add($viewer, $data)
{
    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($data['name'] ?? ''))), 0, 255);
    $from = ws_date_or_null($data['from'] ?? '');
    $to = ((string) ($data['to'] ?? '') !== '') ? ws_date_or_null($data['to']) : $from;

    if (($name === '') || !$from || !$to) {
        return array('ok' => false, 'error' => lang('A holiday needs a name and a first day.'), 'event_id' => 0);
    }

    $department_id = (int) ($data['department_id'] ?? 0);

    if (($department_id > 0) && !ws_department($department_id)) {
        $department_id = 0;
    }

    $saved = ws_event_save($viewer, array(
        'kind'          => 'holiday',
        'scope'         => ($department_id > 0) ? 'department' : 'company',
        'department_id' => $department_id,
        'title'         => $name,
        'start'         => $from,
        'end'           => ($to < $from) ? $from : $to,
        'all_day'       => 1,
    ));

    if (!$saved['ok']) {
        return array('ok' => false, 'error' => $saved['error'], 'event_id' => 0);
    }

    if (!empty($data['yearly'])) {
        db("UPDATE ws_events SET yearly = 1 WHERE id = '" . (int) $saved['event_id'] . "'");
    }

    return array('ok' => true, 'error' => '', 'event_id' => (int) $saved['event_id']);
}

/**
 * The days off kept by hand, the yearly ones and those still to come (or
 * ended in the last month), soonest first.
 *
 * @return array[]
 */
function ws_holidays_kept()
{
    return (array) db_items("SELECT * FROM ws_events
        WHERE kind = 'holiday' AND scope IN ('company', 'department') AND feed_id = 0
        AND (yearly = 1 OR ends_at >= '" . (strtotime('today') - (30 * 86400)) . "')
        ORDER BY yearly DESC, starts_at
        LIMIT 300");
}

/**
 * The next days off from today, from every source, for the settings screen:
 * [date, name, department_id].
 *
 * @param int $count
 * @return array[]
 */
function ws_holidays_next($count = 8)
{
    $out = array();
    $today = date('Y-m-d');
    $year = (int) date('Y');

    for ($y = $year; ($y <= $year + 1) && (count($out) < $count); $y++) {
        $days = ws_holiday_year($y);
        ksort($days);

        foreach ($days as $date => $entries) {
            if ($date < $today) {
                continue;
            }

            // The same day off kept by hand and read from a calendar is
            // listed once.
            foreach ($entries as $entry) {
                $out[$date . '|' . $entry['department_id'] . '|' . mb_strtolower($entry['name'])] = array($date, $entry['name'], $entry['department_id']);
            }

            if (count($out) >= $count) {
                break;
            }
        }
    }

    return array_slice(array_values($out), 0, $count);
}

// ── Public holidays read from an iCal address ──────────────────────────

/**
 * An address as it is called: webcal:// is https:// under another name.
 *
 * @param string $url
 * @return string
 */
function ws_holiday_feed_url($url)
{
    $url = trim((string) $url);

    return preg_match('#^webcals?://#i', $url) ? ('https://' . preg_replace('#^webcals?://#i', '', $url)) : $url;
}

/**
 * Adds a calendar address and reads it.
 *
 * @param array $viewer
 * @param array $data url, name, department_id, public_only
 * @return array ok, error, feed_id, events
 */
function ws_holiday_feed_add($viewer, $data)
{
    $url = ws_holiday_feed_url($data['url'] ?? '');

    if (!preg_match('#^https://[^\s]+$#i', $url) || (strlen($url) > 2000)) {
        return array('ok' => false, 'error' => lang('Write the address of an iCal calendar (https://…).'), 'feed_id' => 0, 'events' => 0);
    }

    $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 120);

    if ($name === '') {
        $name = (string) parse_url($url, PHP_URL_HOST);
    }

    $department_id = (int) ($data['department_id'] ?? 0);

    if (($department_id > 0) && !ws_department($department_id)) {
        $department_id = 0;
    }

    db("INSERT INTO ws_holiday_feeds (name, url, department_id, public_only, created_by, created_at, updated_at)
        VALUES ('" . e($name) . "', '" . e($url) . "', '" . $department_id . "', '" . (empty($data['public_only']) ? 0 : 1) . "',
            '" . (int) $viewer['id'] . "', '" . time() . "', '" . time() . "')");

    $feed_id = (int) mysqli_insert_id(db::$con);
    $read = ws_holiday_feed_sync($feed_id);

    return array('ok' => $read['ok'], 'error' => $read['error'], 'feed_id' => $feed_id, 'events' => $read['events']);
}

/**
 * @return array[] every calendar address, oldest first
 */
function ws_holiday_feeds()
{
    return (array) db_items("SELECT * FROM ws_holiday_feeds ORDER BY id");
}

/**
 * Reads a calendar address again: its days are added, changed in place (by
 * the calendar's own UID) or removed when the calendar no longer has them.
 * Only the days from a year ago to three years ahead are kept.
 *
 * @param int $feed_id
 * @return array ok, error, events
 */
function ws_holiday_feed_sync($feed_id)
{
    $feed = db_item("SELECT * FROM ws_holiday_feeds WHERE id = '" . (int) $feed_id . "'");

    if (!is_array($feed)) {
        return array('ok' => false, 'error' => lang('That calendar could not be found.'), 'events' => 0);
    }

    $fail = function ($message) use ($feed) {
        db("UPDATE ws_holiday_feeds SET sync_error = '" . e(mb_substr($message, 0, 500)) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $feed['id'] . "'");

        return array('ok' => false, 'error' => $message, 'events' => 0);
    };

    // The same outgoing client the webhooks use: the address is resolved and
    // checked before it is called, so a calendar address cannot be pointed at
    // the server's own network, and redirects are not followed.
    if (!function_exists('api_http_request') && is_file(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    if (!function_exists('api_http_request')) {
        return $fail(lang('This server cannot call other addresses.'));
    }

    $answer = api_http_request('GET', (string) $feed['url'], array(
        'headers'  => array('Accept: text/calendar'),
        'timeout'  => 20,
        'max_body' => 4 * 1024 * 1024,
        'agent'    => 'holidays',
    ));

    if (!$answer['ok']) {
        return $fail($answer['error'] !== '' ? $answer['error'] : lang(array('string' => 'The address answered {var:1}.', 'vars' => (int) $answer['status'])));
    }

    // Some servers send the file compressed whatever was asked for (Apple's
    // public holiday calendars do).
    $body = (string) $answer['body'];

    if ((substr($body, 0, 2) === "\x1f\x8b") && function_exists('gzdecode')) {
        $decoded = @gzdecode($body);

        if (is_string($decoded)) {
            $body = $decoded;
        }
    }

    if (strpos($body, 'BEGIN:VCALENDAR') === false) {
        return $fail(lang('That address did not answer with a calendar.'));
    }

    $from = date('Y-m-d', strtotime('-' . WS_HOLIDAY_FEED_PAST_DAYS . ' days'));
    $to = date('Y-m-d', strtotime('+' . WS_HOLIDAY_FEED_AHEAD_DAYS . ' days'));
    $events = array();

    foreach (ws_ical_events($body, (int) $feed['public_only'] === 1) as $event) {
        if ($event['yearly'] || (($event['end'] >= $from) && ($event['start'] <= $to))) {
            $events[$event['uid']] = $event;
        }

        if (count($events) >= WS_HOLIDAY_FEED_LIMIT) {
            break;
        }
    }

    $scope = ((int) $feed['department_id'] > 0) ? 'department' : 'company';
    $existing = array();

    foreach ((array) db_items("SELECT id, feed_uid FROM ws_events WHERE feed_id = '" . (int) $feed['id'] . "'") as $row) {
        $existing[(string) $row['feed_uid']] = (int) $row['id'];
    }

    foreach ($events as $uid => $event) {
        $starts_at = strtotime($event['start'] . ' 00:00:00');
        $ends_at = strtotime($event['end'] . ' 23:59:59');
        $fields = "kind = 'holiday', scope = '" . $scope . "', department_id = '" . (($scope === 'department') ? (int) $feed['department_id'] : 0) . "',
            title = '" . e(mb_substr($event['name'], 0, 255)) . "', starts_at = '" . (int) $starts_at . "', ends_at = '" . (int) $ends_at . "',
            all_day = 1, yearly = '" . ($event['yearly'] ? 1 : 0) . "'";

        if (isset($existing[$uid])) {
            db("UPDATE ws_events SET " . $fields . " WHERE id = '" . $existing[$uid] . "'");
            unset($existing[$uid]);
        } else {
            db("INSERT INTO ws_events SET " . $fields . ", note = '', feed_id = '" . (int) $feed['id'] . "', feed_uid = '" . e($uid) . "',
                created_by = '" . (int) $feed['created_by'] . "', created_at = '" . time() . "'");
        }
    }

    // What the calendar no longer holds goes with it.
    if (!empty($existing)) {
        db("DELETE FROM ws_events WHERE feed_id = '" . (int) $feed['id'] . "' AND id IN (" . implode(',', array_values($existing)) . ")");
    }

    db("UPDATE ws_holiday_feeds SET events = '" . count($events) . "', synced_at = '" . time() . "', sync_error = '', updated_at = '" . time() . "'
        WHERE id = '" . (int) $feed['id'] . "'");

    return array('ok' => true, 'error' => '', 'events' => count($events));
}

/**
 * The days a calendar address brought that are still to come, and its yearly
 * ones, for the settings screen: the ones left out as well.
 *
 * @param int $feed_id
 * @return array[] id, title, starts_at, ends_at, yearly, skipped
 */
function ws_holiday_feed_days($feed_id)
{
    $skipped = ws_holiday_skip_ready() ? 'skipped' : '0 AS skipped';

    return (array) db_items("SELECT id, title, starts_at, ends_at, yearly, " . $skipped . " FROM ws_events
        WHERE feed_id = '" . (int) $feed_id . "' AND feed_id > 0 AND kind = 'holiday'
        AND (yearly = 1 OR ends_at >= '" . strtotime('today') . "')
        ORDER BY yearly DESC, starts_at
        LIMIT 150");
}

/**
 * Leaves one day of a calendar address out of the working calendar, or
 * counts it again. The day stays with its address, so reading the calendar
 * again neither brings it back as a day off nor forgets the choice; a day
 * the calendar drops goes with the choice.
 *
 * @param int  $event_id
 * @param bool $skipped
 * @return array|null the day, or null when it is not one read from a calendar
 */
function ws_holiday_feed_day_skip($event_id, $skipped)
{
    if (!ws_holiday_skip_ready()) {
        return null;
    }

    $event = db_item("SELECT * FROM ws_events WHERE id = '" . (int) $event_id . "' AND kind = 'holiday' AND feed_id > 0");

    if (!is_array($event)) {
        return null;
    }

    db("UPDATE ws_events SET skipped = '" . ($skipped ? 1 : 0) . "' WHERE id = '" . (int) $event['id'] . "'");

    return $event;
}

/**
 * Removes a calendar address and the days read from it.
 *
 * @param int $feed_id
 * @return void
 */
function ws_holiday_feed_remove($feed_id)
{
    db("DELETE FROM ws_events WHERE feed_id = '" . (int) $feed_id . "' AND feed_id > 0");
    db("DELETE FROM ws_holiday_feeds WHERE id = '" . (int) $feed_id . "'");
}

/**
 * The scheduled run: reads the calendar addresses not read for a day, one a
 * run, and one that failed not before an hour has passed.
 *
 * @return void
 */
function ws_holiday_feeds_tick()
{
    if (!ws_workdays_ready()) {
        return;
    }

    $feed_id = (int) db_value("SELECT id FROM ws_holiday_feeds
        WHERE synced_at < '" . (time() - 86400) . "'
        AND (sync_error = '' OR updated_at < '" . (time() - 3600) . "')
        ORDER BY synced_at
        LIMIT 1");

    if ($feed_id > 0) {
        ws_holiday_feed_sync($feed_id);
    }
}

/**
 * The all-day events of an iCalendar text: uid, name, start, end (Y-m-d, the
 * last day itself), yearly. Only what a holiday calendar needs is read: a
 * DTEND on a date is the day after the last one (RFC 5545), a yearly RRULE
 * marks the day as coming back every year, and timed events are left out.
 *
 * Google's public holiday calendars list observances (Mother's Day and the
 * like) beside the days off. An observance's DESCRIPTION says, in the
 * calendar's language, how to hide observances in Google Calendar, and the
 * English ones start with "Observance"; a day off's never names Google. Half
 * days off (the eve of a feast: "half day", "yarım gün", "afternoon",
 * "öğleden sonra", "arife") are working mornings. With $public_only both are
 * left out. Calendars that mark neither (Apple's list Mother's Day beside the
 * feasts) come in whole; a day can then be left out one by one.
 *
 * @param string $text
 * @param bool   $public_only
 * @return array[]
 */
function ws_ical_events($text, $public_only = true)
{
    // Long lines are folded: a line that starts with a space or a tab goes on
    // the one before it.
    $text = preg_replace("/\r\n[ \t]|\n[ \t]|\r[ \t]/", '', str_replace("\r\n", "\n", (string) $text));
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $events = array();
    $current = null;

    $unescape = function ($value) {
        return trim(strtr((string) $value, array('\\n' => ' ', '\\N' => ' ', '\\,' => ',', '\\;' => ';', '\\\\' => '\\')));
    };

    $day = function ($value) {
        return preg_match('/^([0-9]{4})([0-9]{2})([0-9]{2})/', (string) $value, $match) && checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            ? $match[1] . '-' . $match[2] . '-' . $match[3]
            : null;
    };

    foreach ($lines as $line) {
        if ($line === 'BEGIN:VEVENT') {
            $current = array();
            continue;
        }

        if ($line === 'END:VEVENT') {
            if (is_array($current)) {
                $events[] = $current;
            }

            $current = null;
            continue;
        }

        if (!is_array($current) || (strpos($line, ':') === false)) {
            continue;
        }

        list($head, $value) = explode(':', $line, 2);
        $parts = explode(';', $head);
        $name = strtoupper($parts[0]);
        $current[$name] = array('value' => $value, 'params' => strtoupper(implode(';', array_slice($parts, 1))));
    }

    $out = array();

    foreach ($events as $event) {
        $start = $day($event['DTSTART']['value'] ?? '');

        // A timed event is a meeting, not a day off.
        if (($start === null) || (strpos(($event['DTSTART']['value'] ?? ''), 'T') !== false)) {
            continue;
        }

        $description = $unescape($event['DESCRIPTION']['value'] ?? '');
        $name = $unescape($event['SUMMARY']['value'] ?? '');

        if ($public_only && (preg_match('/^\s*observance/i', $description) || (strpos($description, 'Google') !== false))) {
            continue;
        }

        if ($public_only && preg_match('/half[\s-]?day|yarım gün|afternoon|öğleden sonra|arife/iu', $name . ' ' . $description)) {
            continue;
        }

        $end = $start;
        $next = $day($event['DTEND']['value'] ?? '');

        if (($next !== null) && ($next > $start)) {
            $end = date('Y-m-d', strtotime($next . ' 12:00:00 -1 day'));
        }

        $uid = mb_substr(trim((string) ($event['UID']['value'] ?? '')), 0, 191);

        if ($uid === '') {
            $uid = md5($start . '|' . $name);
        }

        $out[] = array(
            'uid'    => $uid,
            'name'   => ($name !== '') ? $name : lang('Holiday'),
            'start'  => $start,
            'end'    => $end,
            'yearly' => (bool) preg_match('/FREQ=YEARLY/i', (string) ($event['RRULE']['value'] ?? '')),
        );
    }

    return $out;
}

// ── The settings card ──────────────────────────────────────────────────

/**
 * Handles the card's forms.
 *
 * @param array    $viewer
 * @param string   $action holiday_add | holiday_remove | feed_add | feed_sync | feed_remove | feed_day
 * @param liveform $liveform
 * @return void
 */
function ws_holidays_settings_post($viewer, $action, $liveform)
{
    if (!ws_workdays_ready()) {
        $liveform->add_error(lang('The workspace is not installed yet: the database has to be updated first.'));
        return;
    }

    $username = (string) ($_SESSION['sessionusername'] ?? '');

    switch ($action) {
        case 'holiday_add':
            $added = ws_holiday_add($viewer, array(
                'name'          => $_POST['name'] ?? '',
                'from'          => $_POST['from'] ?? '',
                'to'            => $_POST['to'] ?? '',
                'department_id' => $_POST['department_id'] ?? 0,
                'yearly'        => !empty($_POST['yearly']),
            ));

            if (!$added['ok']) {
                $liveform->add_error($added['error']);
                return;
            }

            log_activity(lang(array('string' => 'workspace holiday ({var:1}) was added', 'vars' => trim((string) ($_POST['name'] ?? '')))), $username);
            $liveform->add_notice(lang(array('string' => 'The holiday was added: {var:1}.', 'vars' => trim((string) ($_POST['name'] ?? '')))));
            return;

        case 'holiday_remove':
            $event = db_item("SELECT * FROM ws_events WHERE id = '" . (int) ($_POST['event_id'] ?? 0) . "' AND kind = 'holiday' AND feed_id = 0");

            if (is_array($event) && ws_event_delete($viewer, $event['id'])['ok']) {
                log_activity(lang(array('string' => 'workspace holiday ({var:1}) was removed', 'vars' => $event['title'])), $username);
                $liveform->add_notice(lang('The holiday was removed.'));
            }

            return;

        case 'feed_add':
            $added = ws_holiday_feed_add($viewer, array(
                'url'           => $_POST['url'] ?? '',
                'name'          => $_POST['name'] ?? '',
                'department_id' => $_POST['department_id'] ?? 0,
                'public_only'   => !empty($_POST['public_only']),
            ));

            if ($added['feed_id'] <= 0) {
                $liveform->add_error($added['error']);
                return;
            }

            log_activity(lang('a public holiday calendar was added to the workspace'), $username);

            if ($added['ok']) {
                $liveform->add_notice(lang(array('string' => 'The calendar was read: {var:1} days off.', 'vars' => $added['events'])));
            } else {
                $liveform->add_warning(lang(array('string' => 'The calendar was added, but it could not be read: {var:1}', 'vars' => $added['error'])));
            }

            return;

        case 'feed_sync':
            $read = ws_holiday_feed_sync((int) ($_POST['feed_id'] ?? 0));

            if ($read['ok']) {
                $liveform->add_notice(lang(array('string' => 'The calendar was read: {var:1} days off.', 'vars' => $read['events'])));
            } else {
                $liveform->add_error(lang(array('string' => 'The calendar could not be read: {var:1}', 'vars' => $read['error'])));
            }

            return;

        case 'feed_remove':
            ws_holiday_feed_remove((int) ($_POST['feed_id'] ?? 0));
            log_activity(lang('a public holiday calendar was removed from the workspace'), $username);
            $liveform->add_notice(lang('The calendar was removed with its days.'));
            return;

        // One day of a calendar left out, or counted again.
        case 'feed_day':
            $skip = !empty($_POST['skip']);
            $event = ws_holiday_feed_day_skip((int) ($_POST['event_id'] ?? 0), $skip);

            if ($event === null) {
                $liveform->add_error(lang('That day could not be found.'));
                return;
            }

            $when = date('d.m.Y', (int) $event['starts_at']) . ' ' . $event['title'];

            if ($skip) {
                log_activity(lang(array('string' => 'workspace holiday ({var:1}) is no longer counted', 'vars' => $when)), $username);
                $liveform->add_notice(lang(array('string' => 'The day is left out of the working calendar: {var:1}.', 'vars' => $when)));
            } else {
                log_activity(lang(array('string' => 'workspace holiday ({var:1}) is counted again', 'vars' => $when)), $username);
                $liveform->add_notice(lang(array('string' => 'The day counts as a day off again: {var:1}.', 'vars' => $when)));
            }

            return;
    }
}

/**
 * The working calendar card of the settings screen.
 *
 * @param string $self_url
 * @return string
 */
function ws_holidays_settings_card($self_url)
{
    if (!ws_workdays_ready()) {
        return '';
    }

    $departments = ws_departments();
    $whom = function ($department_id) use ($departments) {
        return ((int) $department_id > 0)
            ? h($departments[(int) $department_id]['name'] ?? lang('Department'))
            : h(lang('The whole company'));
    };

    $options = '<option value="0">' . h(lang('The whole company')) . '</option>';

    foreach ($departments as $department) {
        $options .= '<option value="' . (int) $department['id'] . '">' . h($department['name']) . '</option>';
    }

    $form = function ($action, $inner, $class = 'd-inline') use ($self_url) {
        return '<form method="post" action="' . h($self_url) . '" class="' . $class . '">' . get_token_field()
            . '<input type="hidden" name="ws_action" value="' . h($action) . '">' . $inner . '</form>';
    };

    // The next days off, from every source, so the effect can be seen.
    $next = '';

    foreach (ws_holidays_next(8) as $item) {
        $next .= '<li class="d-flex gap-2"><span class="text-body-secondary text-nowrap">' . h(ws_day_label($item[0])) . '.' . h(date('Y', strtotime($item[0]))) . '</span>'
            . '<span class="ws-grow">' . h($item[1]) . '</span>'
            . ((int) $item[2] > 0 ? '<span class="badge text-bg-light">' . $whom($item[2]) . '</span>' : '') . '</li>';
    }

    // The days off kept by hand.
    $rows = '';

    foreach (ws_holidays_kept() as $event) {
        $first = date('d.m.Y', (int) $event['starts_at']);
        $last = date('d.m.Y', (int) $event['ends_at']);
        $when = ((int) $event['yearly'] === 1)
            ? date('d.m', (int) $event['starts_at']) . (($first !== $last) ? '–' . date('d.m', (int) $event['ends_at']) : '')
            : $first . (($first !== $last) ? '–' . $last : '');

        $rows .= '
            <tr>
                <td>' . h($event['title']) . '</td>
                <td class="text-nowrap">' . h($when) . ((int) $event['yearly'] === 1 ? ' <span class="badge text-bg-light">' . h(lang('Every year')) . '</span>' : '') . '</td>
                <td>' . $whom(((string) $event['scope'] === 'department') ? $event['department_id'] : 0) . '</td>
                <td class="text-end">' . $form('holiday_remove', '<input type="hidden" name="event_id" value="' . (int) $event['id'] . '"><button type="submit" class="btn btn-sm btn-ghost" title="' . h(lang('Remove')) . '" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>') . '</td>
            </tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="4" class="text-body-secondary small">' . h(lang('No holidays kept by hand yet.')) . '</td></tr>';
    }

    // The calendar addresses.
    $feeds = '';

    foreach (ws_holiday_feeds() as $feed) {
        // Its days, each of which can be left out: a day the country keeps
        // that this company works on.
        $days = '';
        $left_out = 0;

        foreach (ws_holiday_feed_days($feed['id']) as $day) {
            $off = ((int) $day['skipped'] === 1);
            $first = date('d.m.Y', (int) $day['starts_at']);
            $last = date('d.m.Y', (int) $day['ends_at']);
            $when = ((int) $day['yearly'] === 1)
                ? date('d.m', (int) $day['starts_at']) . (($first !== $last) ? '–' . date('d.m', (int) $day['ends_at']) : '') . ' <span class="badge text-bg-light">' . h(lang('Every year')) . '</span>'
                : h($first . (($first !== $last) ? '–' . $last : ''));

            $left_out += $off ? 1 : 0;
            $days .= '
                <li class="ws-feed-day' . ($off ? ' ws-feed-day-off' : '') . '">
                    <span class="text-body-secondary text-nowrap">' . $when . '</span>
                    <span class="ws-grow">' . h($day['title']) . ($off ? ' <span class="badge text-bg-secondary">' . h(lang('Not counted')) . '</span>' : '') . '</span>
                    ' . (ws_holiday_skip_ready() ? $form('feed_day', '<input type="hidden" name="feed_id" value="' . (int) $feed['id'] . '"><input type="hidden" name="event_id" value="' . (int) $day['id'] . '"><input type="hidden" name="skip" value="' . ($off ? 0 : 1) . '"><button type="submit" class="btn btn-sm btn-link p-0">' . h($off ? lang('Count again') : lang('Do not count')) . '</button>') : '') . '
                </li>';
        }

        $state = ((string) $feed['sync_error'] !== '')
            ? '<span class="text-danger">' . h($feed['sync_error']) . '</span>'
            : h(lang(array('string' => '{var:1} days off', 'vars' => (int) $feed['events'])))
                . ($left_out > 0 ? ' · ' . h(lang(array('string' => '{var:1} not counted', 'vars' => $left_out))) : '')
                . ((int) $feed['synced_at'] > 0 ? ' · ' . h(lang(array('string' => 'read {var:1}', 'vars' => date('d.m.Y H:i', (int) $feed['synced_at'])))) : '');

        $feeds .= '
            <div class="ws-feed-row">
                <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                <div class="ws-grow">
                    <div class="fw-semibold">' . h($feed['name']) . ' <span class="badge text-bg-light">' . $whom($feed['department_id']) . '</span></div>
                    <div class="small text-body-secondary text-break">' . h(parse_url((string) $feed['url'], PHP_URL_HOST)) . ' · ' . $state . '</div>
                </div>
                ' . $form('feed_sync', '<input type="hidden" name="feed_id" value="' . (int) $feed['id'] . '"><button type="submit" class="btn btn-sm btn-ghost"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Read now')) . '</button>') . '
                ' . $form('feed_remove', '<input type="hidden" name="feed_id" value="' . (int) $feed['id'] . '"><button type="submit" class="btn btn-sm btn-ghost" title="' . h(lang('Remove')) . '" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>') . '
            </div>';

        if ($days !== '') {
            $feeds .= '
            <details class="ws-feed-days" id="ws-feed-' . (int) $feed['id'] . '"' . ((!empty($_GET['feed']) && ((int) $_GET['feed'] === (int) $feed['id'])) ? ' open' : '') . '>
                <summary class="small">' . h(lang(array('string' => 'Days from this calendar ({var:1})', 'vars' => substr_count($days, '<li')))) . '</summary>
                <p class="small text-body-secondary my-1">' . h(lang('A day that is not a day off at this company can be left out; reading the calendar again keeps the choice.')) . '</p>
                <ul class="list-unstyled small mb-0">' . $days . '</ul>
            </details>';
        }
    }

    $example = 'https://calendar.google.com/calendar/ical/en.turkish%23holiday%40group.v.calendar.google.com/public/basic.ics';

    return '
        <div class="card mt-4" id="ws-calendar">
            <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-calendar-x me-1" aria-hidden="true"></i>' . h(lang('Working calendar')) . '</h2></div>
            <div class="card-body">
                <p class="small text-body-secondary">' . h(lang('A repeating task\'s copy that falls on a weekend or a holiday moves to the next working day, and the task window warns about a date picked by hand. The working week is set under Defaults; the days off are kept here. A holiday for one department counts for that department\'s tasks.')) . '</p>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <h3 class="h6">' . h(lang('Holidays')) . '</h3>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-2">
                                <thead><tr><th>' . h(lang('Holiday')) . '</th><th>' . h(lang('Date')) . '</th><th>' . h(lang('Who it is for')) . '</th><th></th></tr></thead>
                                <tbody>' . $rows . '</tbody>
                            </table>
                        </div>
                        ' . $form('holiday_add', '
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-12">
                                    <label class="form-label small" for="ws_hol_name">' . h(lang('Add a holiday')) . '</label>
                                    <input class="form-control form-control-sm" type="text" name="name" id="ws_hol_name" maxlength="255" required placeholder="' . h(lang('Name')) . '">
                                </div>
                                <div class="col-6 col-sm-4">
                                    <label class="form-label small" for="ws_hol_from">' . h(lang('First day')) . '</label>
                                    <input class="form-control form-control-sm" type="date" name="from" id="ws_hol_from" required>
                                </div>
                                <div class="col-6 col-sm-4">
                                    <label class="form-label small" for="ws_hol_to">' . h(lang('Last day')) . '</label>
                                    <input class="form-control form-control-sm" type="date" name="to" id="ws_hol_to" title="' . h(lang('Leave it empty for one day.')) . '">
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label small" for="ws_hol_for">' . h(lang('Who it is for')) . '</label>
                                    <select class="form-select form-select-sm" name="department_id" id="ws_hol_for">' . $options . '</select>
                                </div>
                                <div class="col-sm-8">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="yearly" value="1" id="ws_hol_yearly">
                                        <label class="form-check-label small" for="ws_hol_yearly">' . h(lang('Every year on the same day')) . '</label>
                                    </div>
                                </div>
                                <div class="col-sm-4 text-sm-end">
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add')) . '</button>
                                </div>
                            </div>', 'ws-holiday-form') . '
                    </div>

                    <div class="col-lg-5">
                        <h3 class="h6">' . h(lang('Next days off')) . '</h3>
                        ' . ($next !== '' ? '<ul class="list-unstyled small mb-0 ws-next-off">' . $next . '</ul>' : '<div class="small text-body-secondary">' . h(lang('No days off in the coming months.')) . '</div>') . '
                    </div>
                </div>

                <hr>

                <h3 class="h6">' . h(lang('Public holidays from a calendar address')) . '</h3>
                <p class="small text-body-secondary mb-2">' . h(lang('Paste the iCal address of a public holiday calendar: its days are read now and again every day. Google Calendar publishes one for every country, for example:')) . '
                    <code class="d-block text-break my-1">' . h($example) . '</code>'
                    . h(lang('Write the country in place of "turkish" (usa, uk, german, french…).')) . '</p>
                ' . $feeds . '
                ' . $form('feed_add', '
                    <div class="row g-2 align-items-end mt-1">
                        <div class="col-lg-6">
                            <label class="form-label small" for="ws_feed_url">' . h(lang('Calendar address (iCal)')) . '</label>
                            <input class="form-control form-control-sm" type="url" name="url" id="ws_feed_url" required placeholder="https://…/basic.ics">
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="form-label small" for="ws_feed_name">' . h(lang('Name')) . '</label>
                            <input class="form-control form-control-sm" type="text" name="name" id="ws_feed_name" maxlength="120">
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="form-label small" for="ws_feed_for">' . h(lang('Who it is for')) . '</label>
                            <select class="form-select form-select-sm" name="department_id" id="ws_feed_for">' . $options . '</select>
                        </div>
                        <div class="col-sm-8">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="public_only" value="1" id="ws_feed_public" checked>
                                <label class="form-check-label small" for="ws_feed_public">' . h(lang('Only the public holidays (observances and half days are left out)')) . '</label>
                            </div>
                        </div>
                        <div class="col-sm-4 text-sm-end">
                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>' . h(lang('Add and read')) . '</button>
                        </div>
                    </div>', '') . '
            </div>
        </div>';
}
