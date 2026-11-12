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

/**
 * Hours as typed on Workspace Settings ("7,5", "8") in minutes. Empty or
 * nothing at all means the default (0); a value under the floor is raised to
 * it.
 *
 * @param mixed $value
 * @param int   $min floor in minutes
 * @param int   $max ceiling in minutes
 * @return int|null minutes, 0 for the default; null for text that is no
 *                  number of hours, a negative one or one over the ceiling
 */
function ws_settings_minutes($value, $min, $max)
{
    $text = str_replace(',', '.', trim((string) $value));

    if ($text === '') {
        return 0;
    }

    if (!is_numeric($text) || ((float) $text < 0)) {
        return null;
    }

    $minutes = (int) round(((float) $text) * 60);

    if ($minutes === 0) {
        return 0;
    }

    return ($minutes > $max) ? null : max($min, $minutes);
}

/**
 * The ticked weekdays (1 = Monday) as the bit mask the board reads.
 *
 * @param mixed $days
 * @return int
 */
function ws_settings_mask($days)
{
    $mask = 0;

    foreach ((array) $days as $day) {
        $day = (int) $day;

        if (($day >= 1) && ($day <= 7)) {
            $mask |= (1 << ($day - 1));
        }
    }

    return $mask;
}

/**
 * Seven small toggles for the working days.
 *
 * @param string $name  the field name, [] is added
 * @param int    $mask
 * @param string $id_prefix
 * @param string $label  the group's accessible name, "Working days" when empty
 * @param bool   $disabled  shown but not changeable
 * @return string
 */
function ws_settings_days_html($name, $mask, $id_prefix, $label = '', $disabled = false)
{
    $output = '<div class="btn-group btn-group-sm ws-days" role="group" aria-label="' . h(($label !== '') ? $label : lang('Working days')) . '">';

    foreach (ws_weekday_short_names() as $day => $label) {
        $id = $id_prefix . '_' . $day;

        $output .= '<input type="checkbox" class="btn-check" name="' . h($name) . '[]" value="' . $day . '" id="' . h($id) . '" autocomplete="off"'
            . (($mask & (1 << ($day - 1))) ? ' checked' : '') . ($disabled ? ' disabled' : '') . '>'
            . '<label class="btn btn-outline theme-secondary" for="' . h($id) . '">' . h($label) . '</label>';
    }

    return $output . '</div>';
}

/**
 * Minutes as the hours a field shows: "8", "7.5" (with the panel's decimal
 * separator, which ws_settings_minutes() reads back).
 *
 * @param int $minutes
 * @return string
 */
function ws_settings_hours($minutes)
{
    $hours = round(((int) $minutes) / 60, 2);
    $text = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

    return str_replace('.', pg_number_separators()['decimal'], $text);
}

/**
 * Saves the Workspace defaults: the hours of a working day, the working week
 * and the hours a task with no estimate counts as. Asked by the Defaults card
 * of the Site Settings dialog (Team and working hours) and by a form still
 * open on Workspace Settings. Unreadable or out-of-range hours are refused,
 * not turned into something else, and nothing is written then.
 *
 * @param mixed $day_hours
 * @param mixed $workdays  the ticked weekdays, 1 = Monday
 * @param mixed $default_task_hours
 * @return array the refused fields, day_hours and default_task_hours, each
 *               with its sentence; empty when the defaults were written
 */
function ws_settings_defaults_save($day_hours, $workdays, $default_task_hours)
{
    $day = ws_settings_minutes($day_hours, 30, 1440);
    $mask = ws_settings_mask($workdays);
    $default = ws_settings_minutes($default_task_hours, 5, 2400);

    $refused = array();

    if ($day === null) {
        $refused['day_hours'] = lang(array('string' => '{var:1}: enter the hours as a number from 0 to {var:2}.', 'vars' => array(h(lang('Hours in a working day')), 24)));
    }

    if ($default === null) {
        $refused['default_task_hours'] = lang(array('string' => '{var:1}: enter the hours as a number from 0 to {var:2}.', 'vars' => array(h(lang('A task with no estimate counts as (hours)')), 40)));
    }

    if ($refused) {
        return $refused;
    }

    db("UPDATE config SET
        ws_day_minutes = '" . (($day > 0) ? $day : 480) . "',
        ws_workdays = '" . (($mask > 0) ? $mask : 31) . "',
        ws_default_task_minutes = '" . (($default > 0) ? $default : 60) . "'");

    return array();
}

// ── Days off kept by hand ───────────────────────────────────────────────

/**
 * A day off's name and days, checked: the last day is the first when left
 * empty. Needs no database, so the Site Settings dialog asks it before it
 * writes anything (ws_holidays_settings_refused()).
 *
 * @param array $data name, from, to
 * @return array name, from, to, error ('' when accepted), field (the one refused)
 */
function ws_holiday_input($data)
{
    $text = function ($key) use ($data) {
        return is_scalar($data[$key] ?? '') ? (string) ($data[$key] ?? '') : '';
    };
    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', $text('name'))), 0, 255);
    $from = ws_date_or_null($text('from'));
    $to = ($text('to') !== '') ? ws_date_or_null($text('to')) : $from;
    $out = array('name' => $name, 'from' => $from, 'to' => $to, 'error' => '', 'field' => '');
    $missing = ($name === '') ? 'name' : (!$from ? 'from' : (!$to ? 'to' : ''));

    if ($missing !== '') {
        return array_merge($out, array('error' => lang('A holiday needs a name and a first day.'), 'field' => $missing));
    }

    if ($to < $from) {
        return array_merge($out, array('error' => lang('The last day cannot be before the first day.'), 'field' => 'to'));
    }

    return $out;
}

/**
 * Adds a day off, or a run of them.
 *
 * @param array $viewer
 * @param array $data name, from, to (empty for one day), department_id (0 for the company), yearly
 * @return array ok, error, event_id
 */
function ws_holiday_add($viewer, $data)
{
    $checked = ws_holiday_input($data);

    if ($checked['error'] !== '') {
        return array('ok' => false, 'error' => $checked['error'], 'event_id' => 0);
    }

    $name = $checked['name'];
    $from = $checked['from'];
    $to = $checked['to'];

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
        'end'           => $to,
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
 * A calendar address as it is stored, or '' when it is not one: https (or
 * webcal) and at most 2,000 characters.
 *
 * @param mixed $url
 * @return string
 */
function ws_holiday_feed_address($url)
{
    $url = ws_holiday_feed_url(is_scalar($url) ? $url : '');

    return (preg_match('#^https://[^\s]+$#i', $url) && (strlen($url) <= 2000)) ? $url : '';
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
    $url = ws_holiday_feed_address($data['url'] ?? '');

    if ($url === '') {
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
    // A person's own calendar addresses (includes/workspace/personal_ical.php)
    // are theirs alone.
    $where = ws_personal_ical_ready() ? ' WHERE ' . ws_holiday_feed_owner_where(0) : '';

    return (array) db_items("SELECT * FROM ws_holiday_feeds" . $where . " ORDER BY id");
}

/**
 * Whether a calendar address is one of the site's, not a person's own.
 *
 * @param int $feed_id
 * @return bool
 */
function ws_holiday_feed_site_wide($feed_id)
{
    $where = ws_personal_ical_ready() ? ' AND ' . ws_holiday_feed_owner_where(0) : '';

    return (bool) db_value("SELECT id FROM ws_holiday_feeds WHERE id = '" . (int) $feed_id . "'" . $where);
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

    // A person's own calendar is read into their layer, never into ws_events.
    if ((int) ($feed['user_id'] ?? 0) > 0) {
        return ws_personal_ical_sync($feed);
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
 * The VEVENT blocks of an iCalendar text, each as property name => value,
 * params (the later one when a name repeats); every EXDATE is also listed
 * under 'EXDATE*'. Folded lines are unfolded first. A block nested in an
 * event (VALARM, or any other BEGIN:) is skipped whole: a reminder's
 * SUMMARY, DESCRIPTION and UID are not the event's. A line is split at the
 * first ':' and ';' outside double quotes, since a quoted parameter value
 * may hold both (RFC 5545 3.2).
 *
 * @param string $text
 * @return array[]
 */
function ws_ical_vevents($text)
{
    // Long lines are folded: a line that starts with a space or a tab goes on
    // the one before it.
    $text = preg_replace("/\r\n[ \t]|\n[ \t]|\r[ \t]/", '', str_replace("\r\n", "\n", (string) $text));
    $events = array();
    $current = null;
    $depth = 0;

    foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
        $upper = strtoupper(rtrim($line));

        if (!is_array($current)) {
            if ($upper === 'BEGIN:VEVENT') {
                $current = array();
                $depth = 0;
            }

            continue;
        }

        if (strpos($upper, 'BEGIN:') === 0) {
            $depth++;
            continue;
        }

        if (strpos($upper, 'END:') === 0) {
            if ($depth > 0) {
                $depth--;
                continue;
            }

            if ($upper === 'END:VEVENT') {
                $events[] = $current;
                $current = null;
            }

            continue;
        }

        if ($depth > 0) {
            continue;
        }

        // The name and the parameters end at the first ':' outside quotes;
        // the name ends at the first ';' before it.
        $colon = -1;
        $semicolon = -1;
        $quoted = false;

        for ($i = 0, $length = strlen($line); $i < $length; $i++) {
            $char = $line[$i];

            if ($char === '"') {
                $quoted = !$quoted;
            } elseif (!$quoted && ($char === ';') && ($semicolon < 0)) {
                $semicolon = $i;
            } elseif (!$quoted && ($char === ':')) {
                $colon = $i;
                break;
            }
        }

        if ($colon < 0) {
            continue;
        }

        $head = substr($line, 0, $colon);
        $value = (string) substr($line, $colon + 1);
        $name = strtoupper(($semicolon >= 0) ? substr($head, 0, $semicolon) : $head);
        $params = ($semicolon >= 0) ? (string) substr($head, $semicolon + 1) : '';
        $current[$name] = array('value' => $value, 'params' => $params);

        if ($name === 'EXDATE') {
            $current['EXDATE*'][] = $current[$name];
        }
    }

    return $events;
}

/**
 * An iCalendar DURATION (RFC 5545 3.3.6: PnW, or PnDTnHnMnS and its parts) in
 * seconds; 0 for a negative, empty or unreadable one.
 *
 * @param string $value
 * @return int
 */
function ws_ical_duration($value)
{
    $value = strtoupper(trim((string) $value));

    if (!preg_match('/^\+?P(?:(\d+)W|(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?)$/', $value, $match)) {
        return 0;
    }

    return (int) ($match[1] ?? 0) * 604800 + (int) ($match[2] ?? 0) * 86400
        + (int) ($match[3] ?? 0) * 3600 + (int) ($match[4] ?? 0) * 60 + (int) ($match[5] ?? 0);
}

/**
 * A TEXT value of an iCalendar property read back: its escapes undone, a
 * line break as a space.
 *
 * @param string $value
 * @return string
 */
function ws_ical_text($value)
{
    return trim(strtr((string) $value, array('\\n' => ' ', '\\N' => ' ', '\\,' => ',', '\\;' => ';', '\\\\' => '\\')));
}

/**
 * The all-day events of an iCalendar text: uid, name, start, end (Y-m-d, the
 * last day itself), yearly. Only what a holiday calendar needs is read: a
 * DTEND on a date is the day after the last one (RFC 5545), a yearly RRULE
 * marks the day as coming back every year, timed and cancelled events are
 * left out, and a moved occurrence (RECURRENCE-ID) gets the uid
 * '<UID>|<RECURRENCE-ID>' so it never replaces its repeating day.
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
    $day = function ($value) {
        return preg_match('/^([0-9]{4})([0-9]{2})([0-9]{2})/', (string) $value, $match) && checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            ? $match[1] . '-' . $match[2] . '-' . $match[3]
            : null;
    };

    $events = ws_ical_vevents($text);
    $out = array();

    foreach ($events as $event) {
        $start = $day($event['DTSTART']['value'] ?? '');

        // A timed event is a meeting, not a day off; a cancelled one is no day.
        if (($start === null) || (strpos(($event['DTSTART']['value'] ?? ''), 'T') !== false)
            || (strtoupper(trim((string) ($event['STATUS']['value'] ?? ''))) === 'CANCELLED')) {
            continue;
        }

        $description = ws_ical_text($event['DESCRIPTION']['value'] ?? '');
        $name = ws_ical_text($event['SUMMARY']['value'] ?? '');

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

        $uid = trim((string) ($event['UID']['value'] ?? ''));

        if ($uid === '') {
            $uid = md5($start . '|' . $name);
        }

        // A moved occurrence of a repeating day carries the day's own UID
        // with a RECURRENCE-ID. The sync keys its rows by uid, so without a
        // uid of its own the moved year replaced the yearly row (or was
        // replaced by it).
        $occurrence = trim((string) ($event['RECURRENCE-ID']['value'] ?? ''));

        if ($occurrence !== '') {
            $suffix = '|' . preg_replace('/[^0-9TZ]/', '', strtoupper($occurrence));
            $uid = mb_substr($uid, 0, 191 - strlen($suffix)) . $suffix;
        } else {
            $uid = mb_substr($uid, 0, 191);
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
//
// Drawn on two screens until the Workspace Settings screen goes: its own
// card there, with a form per action, and the Working calendar card of the
// Site Settings dialog (includes/settings/workspace_team.php), whose actions
// are named submit buttons of the settings form. Both read the request
// through ws_holidays_settings_input() and save through
// ws_holidays_settings_post(), and both draw the parts below.

/**
 * Handles the card's actions.
 *
 * @param array    $viewer
 * @param string   $action holiday_add | holiday_remove | feed_add | feed_sync | feed_remove | feed_day
 * @param liveform $liveform
 * @param array    $input  ws_holidays_settings_input()
 * @param bool     $after_save true when the Site Settings pane was saved
 *                         first: a refusal is then a warning, as the rest of
 *                         the pane is stored by now
 * @return void
 */
function ws_holidays_settings_post($viewer, $action, $liveform, $input, $after_save = false)
{
    $refuse = $after_save ? 'add_warning' : 'add_error';

    if (!ws_workdays_ready()) {
        $liveform->$refuse(lang('The workspace is not installed yet: the database has to be updated first.'));
        return;
    }

    $username = (string) ($_SESSION['sessionusername'] ?? '');

    switch ($action) {
        case 'holiday_add':
            $added = ws_holiday_add($viewer, array(
                'name'          => $input['name'] ?? '',
                'from'          => $input['from'] ?? '',
                'to'            => $input['to'] ?? '',
                'department_id' => $input['department_id'] ?? 0,
                'yearly'        => !empty($input['yearly']),
            ));

            if (!$added['ok']) {
                $liveform->$refuse($added['error']);
                return;
            }

            log_activity(lang(array('string' => 'workspace holiday ({var:1}) was added', 'vars' => trim((string) ($input['name'] ?? '')))), $username);
            $liveform->add_notice(lang(array('string' => 'The holiday was added: {var:1}.', 'vars' => h(trim((string) ($input['name'] ?? ''))))));
            return;

        case 'holiday_remove':
            $event = db_item("SELECT * FROM ws_events WHERE id = '" . (int) ($input['event_id'] ?? 0) . "' AND kind = 'holiday' AND feed_id = 0");

            if (is_array($event) && ws_event_delete($viewer, $event['id'])['ok']) {
                log_activity(lang(array('string' => 'workspace holiday ({var:1}) was removed', 'vars' => $event['title'])), $username);
                $liveform->add_notice(lang('The holiday was removed.'));
            }

            return;

        case 'feed_add':
            $added = ws_holiday_feed_add($viewer, array(
                'url'           => $input['url'] ?? '',
                'name'          => $input['name'] ?? '',
                'department_id' => $input['department_id'] ?? 0,
                'public_only'   => !empty($input['public_only']),
            ));

            if ($added['feed_id'] <= 0) {
                $liveform->$refuse($added['error']);
                return;
            }

            log_activity(lang('a public holiday calendar was added to the workspace'), $username);

            if ($added['ok']) {
                $liveform->add_notice(lang(array('string' => 'The calendar was read: {var:1} days off.', 'vars' => $added['events'])));
            } else {
                $liveform->add_warning(lang(array('string' => 'The calendar was added, but it could not be read: {var:1}', 'vars' => h($added['error']))));
            }

            return;

        case 'feed_sync':
            if (!ws_holiday_feed_site_wide((int) ($input['feed_id'] ?? 0))) {
                return;
            }

            $read = ws_holiday_feed_sync((int) ($input['feed_id'] ?? 0));

            if ($read['ok']) {
                $liveform->add_notice(lang(array('string' => 'The calendar was read: {var:1} days off.', 'vars' => $read['events'])));
            } else {
                $liveform->$refuse(lang(array('string' => 'The calendar could not be read: {var:1}', 'vars' => h($read['error']))));
            }

            return;

        case 'feed_remove':
            if (!ws_holiday_feed_site_wide((int) ($input['feed_id'] ?? 0))) {
                return;
            }

            ws_holiday_feed_remove((int) ($input['feed_id'] ?? 0));
            log_activity(lang('a public holiday calendar was removed from the workspace'), $username);
            $liveform->add_notice(lang('The calendar was removed with its days.'));
            return;

        // One day of a calendar left out, or counted again.
        case 'feed_day':
            $skip = !empty($input['skip']);
            $event = ws_holiday_feed_day_skip((int) ($input['event_id'] ?? 0), $skip);

            if ($event === null) {
                $liveform->$refuse(lang('That day could not be found.'));
                return;
            }

            $when = date('d.m.Y', (int) $event['starts_at']) . ' ' . $event['title'];

            if ($skip) {
                log_activity(lang(array('string' => 'workspace holiday ({var:1}) is no longer counted', 'vars' => $when)), $username);
                $liveform->add_notice(lang(array('string' => 'The day is left out of the working calendar: {var:1}.', 'vars' => h($when))));
            } else {
                log_activity(lang(array('string' => 'workspace holiday ({var:1}) is counted again', 'vars' => $when)), $username);
                $liveform->add_notice(lang(array('string' => 'The day counts as a day off again: {var:1}.', 'vars' => h($when))));
            }

            return;
    }
}

/**
 * The fields an action of the card reads, from a request in the field names
 * of the Workspace Settings screen. A field posted as a list reads as empty.
 *
 * @param string $action holiday_add | holiday_remove | feed_add | feed_sync | feed_remove | feed_day
 * @param array  $source
 * @return array
 */
function ws_holidays_settings_input($action, $source)
{
    $fields = array(
        'holiday_add'    => array('name', 'from', 'to', 'department_id', 'yearly'),
        'holiday_remove' => array('event_id'),
        'feed_add'       => array('url', 'name', 'department_id', 'public_only'),
        'feed_sync'      => array('feed_id'),
        'feed_remove'    => array('feed_id'),
        'feed_day'       => array('feed_id', 'event_id', 'skip'),
    );
    $source = is_array($source) ? $source : array();
    $input = array();

    foreach ($fields[(string) $action] ?? array() as $field) {
        $value = $source[$field] ?? '';
        $input[$field] = is_scalar($value) ? (string) $value : '';
    }

    return $input;
}

/**
 * The name and value of the settings form's submit button for a row action
 * (holiday_remove, feed_sync, feed_remove, feed_day): the record's id is the
 * value, and a day of a calendar has one name to leave it out and one to
 * count it again. ws_holidays_settings_request() reads them back.
 *
 * @param string $action
 * @param array  $values the action's fields (ws_holidays_settings_input())
 * @return array name, value
 */
function ws_holidays_settings_button($action, $values)
{
    switch ((string) $action) {
        case 'holiday_remove':
            return array('ws_holiday_remove', (int) ($values['event_id'] ?? 0));

        case 'feed_sync':
        case 'feed_remove':
            return array('ws_' . $action, (int) ($values['feed_id'] ?? 0));

        case 'feed_day':
            return array(!empty($values['skip']) ? 'ws_feed_skip' : 'ws_feed_count', (int) ($values['event_id'] ?? 0));
    }

    return array('ws_' . $action, 1);
}

/**
 * The name of an add form's field in the Site Settings dialog: a holiday's
 * under ws_hol, a calendar address's under ws_feed, so the pane's names stay
 * apart from every other card's.
 *
 * @param string $action holiday_add | feed_add
 * @param string $field  the field's name on the Workspace Settings screen
 * @return string
 */
function ws_holidays_settings_field($action, $field)
{
    return (($action === 'feed_add') ? 'ws_feed' : 'ws_hol') . '[' . $field . ']';
}

/**
 * Which action of the card a Site Settings save asks for, read from the
 * pressed button: array(action, its fields as ws_holidays_settings_input()
 * gives them), or null for a plain save.
 *
 * @param array $post
 * @return array|null
 */
function ws_holidays_settings_request($post)
{
    $post = is_array($post) ? $post : array();
    $pressed = function ($name) use ($post) {
        return (isset($post[$name]) && is_scalar($post[$name])) ? (int) $post[$name] : null;
    };

    if ($pressed('ws_holiday_add') !== null) {
        return array('holiday_add', ws_holidays_settings_input('holiday_add', $post['ws_hol'] ?? array()));
    }

    if ($pressed('ws_feed_add') !== null) {
        return array('feed_add', ws_holidays_settings_input('feed_add', $post['ws_feed'] ?? array()));
    }

    if (($id = $pressed('ws_holiday_remove')) !== null) {
        return array('holiday_remove', ws_holidays_settings_input('holiday_remove', array('event_id' => $id)));
    }

    foreach (array('feed_sync', 'feed_remove') as $action) {
        if (($id = $pressed('ws_' . $action)) !== null) {
            return array($action, ws_holidays_settings_input($action, array('feed_id' => $id)));
        }
    }

    foreach (array('ws_feed_skip' => 1, 'ws_feed_count' => 0) as $name => $skip) {
        if (($id = $pressed($name)) !== null) {
            return array('feed_day', ws_holidays_settings_input('feed_day', array('event_id' => $id, 'skip' => $skip)));
        }
    }

    return null;
}

/**
 * The checks of an add form that need no database: field => sentence, the
 * field in the Workspace Settings screen's names. ws_holiday_add() and
 * ws_holiday_feed_add() ask the same, so a request that passes here is
 * refused there only by the database.
 *
 * @param string $action
 * @param array  $input ws_holidays_settings_input()
 * @return array
 */
function ws_holidays_settings_refused($action, $input)
{
    if ($action === 'holiday_add') {
        $checked = ws_holiday_input($input);

        return ($checked['error'] !== '') ? array($checked['field'] => $checked['error']) : array();
    }

    if ($action === 'feed_add') {
        return (ws_holiday_feed_address($input['url'] ?? '') === '') ? array('url' => lang('Write the address of an iCal calendar (https://…).')) : array();
    }

    return array();
}

/**
 * Who a day off or a calendar address is for, as markup.
 *
 * @param array $departments ws_departments()
 * @param int   $department_id 0 for the whole company
 * @return string
 */
function ws_holidays_whom($departments, $department_id)
{
    return ((int) $department_id > 0)
        ? h($departments[(int) $department_id]['name'] ?? lang('Department'))
        : h(lang('The whole company'));
}

/**
 * A row action's button. The caller's $button callback draws it: in a form
 * of its own on the Workspace Settings screen, as a named submit button of
 * the settings form in the dialog.
 *
 * @param callable $button function ($action, $values, $class, $inner, $label)
 * @return string
 */
function ws_holidays_button($button, $action, $values, $class, $inner, $label = '')
{
    return (string) call_user_func($button, $action, $values, $class, $inner, $label);
}

/**
 * The card's opening sentence.
 *
 * @return string markup
 */
function ws_holidays_settings_intro_html()
{
    return '<p class="small fg-secondary">' . h(lang('A repeating task\'s copy that falls on a weekend or a holiday moves to the next working day, and the task window warns about a date picked by hand. The working week is set under Defaults; the days off are kept here. A holiday for one department counts for that department\'s tasks.')) . '</p>';
}

/**
 * The days off kept by hand, as rows of the Holidays table, each with its
 * remove button.
 *
 * @param array    $departments
 * @param callable $button see ws_holidays_button()
 * @return string
 */
function ws_holidays_rows_html($departments, $button)
{
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
                <td class="text-nowrap">' . h($when) . ((int) $event['yearly'] === 1 ? ' <span class="badge badge-subtle theme-secondary">' . h(lang('Every year')) . '</span>' : '') . '</td>
                <td>' . ws_holidays_whom($departments, ((string) $event['scope'] === 'department') ? $event['department_id'] : 0) . '</td>
                <td class="text-end">' . ws_holidays_button($button, 'holiday_remove', array('event_id' => (int) $event['id']), 'btn btn-sm btn-text theme-secondary', '<i class="bi bi-trash" aria-hidden="true"></i>', lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Remove'), $event['title'])))) . '</td>
            </tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="4" class="fg-secondary">' . h(lang('No holidays kept by hand yet.')) . '</td></tr>';
    }

    return '
                        <div class="table-responsive">
                            <table class="table table-sm align-middle small mb-3">
                                <thead><tr><th>' . h(lang('Holiday')) . '</th><th>' . h(lang('Date')) . '</th><th>' . h(lang('Who it is for')) . '</th><th></th></tr></thead>
                                <tbody>' . $rows . '</tbody>
                            </table>
                        </div>';
}

/**
 * The next days off, from every source, so the effect can be seen.
 *
 * @param array $departments
 * @return string
 */
function ws_holidays_next_html($departments)
{
    $next = '';

    foreach (ws_holidays_next(8) as $item) {
        $next .= '<li class="d-flex gap-3"><span class="fg-secondary text-nowrap">' . h(ws_day_label($item[0])) . '.' . h(date('Y', strtotime($item[0]))) . '</span>'
            . '<span class="ws-grow">' . h($item[1]) . '</span>'
            . ((int) $item[2] > 0 ? '<span class="badge badge-subtle theme-secondary">' . ws_holidays_whom($departments, $item[2]) . '</span>' : '') . '</li>';
    }

    return '<h3 class="pg-group-label">' . h(lang('Next days off')) . '</h3>'
        . ($next !== '' ? '<ul class="list-unstyled small mb-0 ws-next-off">' . $next . '</ul>' : '<div class="small fg-secondary">' . h(lang('No days off in the coming months.')) . '</div>');
}

/**
 * A calendar address's days, each of which can be left out: a day the
 * country keeps that this company works on.
 *
 * @param array    $feed
 * @param callable $button see ws_holidays_button()
 * @return array html (list items), count, left_out
 */
function ws_holiday_feed_days_html($feed, $button)
{
    $days = '';
    $count = 0;
    $left_out = 0;

    foreach (ws_holiday_feed_days($feed['id']) as $day) {
        $off = ((int) $day['skipped'] === 1);
        $first = date('d.m.Y', (int) $day['starts_at']);
        $last = date('d.m.Y', (int) $day['ends_at']);
        $when = ((int) $day['yearly'] === 1)
            ? date('d.m', (int) $day['starts_at']) . (($first !== $last) ? '–' . date('d.m', (int) $day['ends_at']) : '') . ' <span class="badge badge-subtle theme-secondary">' . h(lang('Every year')) . '</span>'
            : h($first . (($first !== $last) ? '–' . $last : ''));

        $count++;
        $left_out += $off ? 1 : 0;
        $days .= '
                <li class="ws-feed-day' . ($off ? ' ws-feed-day-off' : '') . '">
                    <span class="fg-secondary text-nowrap">' . $when . '</span>
                    <span class="ws-grow">' . h($day['title']) . ($off ? ' <span class="badge theme-secondary">' . h(lang('Not counted')) . '</span>' : '') . '</span>
                    ' . (ws_holiday_skip_ready() ? ws_holidays_button($button, 'feed_day', array('feed_id' => (int) $feed['id'], 'event_id' => (int) $day['id'], 'skip' => $off ? 0 : 1), 'btn btn-sm btn-link p-0', h($off ? lang('Count again') : lang('Do not count'))) : '') . '
                </li>';
    }

    return array('html' => $days, 'count' => $count, 'left_out' => $left_out);
}

/**
 * A calendar address's row: its name, whom it is for, its state, Read now
 * and Remove, and what the caller puts after the name (the dialog's Days
 * button).
 *
 * @param array    $feed
 * @param array    $departments
 * @param int      $left_out days of it not counted
 * @param callable $button see ws_holidays_button()
 * @param string   $extra markup before Read now
 * @return string
 */
function ws_holiday_feed_row_html($feed, $departments, $left_out, $button, $extra = '')
{
    $state = ((string) $feed['sync_error'] !== '')
        ? '<span class="fg-danger">' . h($feed['sync_error']) . '</span>'
        : h(lang(array('string' => '{var:1} days off', 'vars' => (int) $feed['events'])))
            . ($left_out > 0 ? ' · ' . h(lang(array('string' => '{var:1} not counted', 'vars' => $left_out))) : '')
            . ((int) $feed['synced_at'] > 0 ? ' · ' . h(lang(array('string' => 'read {var:1}', 'vars' => date('d.m.Y H:i', (int) $feed['synced_at'])))) : '');

    return '
            <div class="ws-feed-row">
                <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                <div class="ws-grow">
                    <div class="fw-semibold">' . h($feed['name']) . ' <span class="badge badge-subtle theme-secondary">' . ws_holidays_whom($departments, $feed['department_id']) . '</span></div>
                    <div class="small fg-secondary text-break">' . h(parse_url((string) $feed['url'], PHP_URL_HOST)) . ' · ' . $state . '</div>
                </div>
                ' . $extra . '
                ' . ws_holidays_button($button, 'feed_sync', array('feed_id' => (int) $feed['id']), 'btn btn-sm btn-text theme-secondary', '<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Read now'))) . '
                ' . ws_holidays_button($button, 'feed_remove', array('feed_id' => (int) $feed['id']), 'btn btn-sm btn-text theme-secondary', '<i class="bi bi-trash" aria-hidden="true"></i>', lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Remove'), $feed['name'])))) . '
            </div>';
}

/**
 * The heading and the help of the calendar addresses.
 *
 * @return string
 */
function ws_holiday_feeds_intro_html()
{
    $example = 'https://calendar.google.com/calendar/ical/en.turkish%23holiday%40group.v.calendar.google.com/public/basic.ics';

    return '<h3 class="pg-group-label">' . h(lang('Public holidays from a calendar address')) . '</h3>
                <p class="small fg-secondary mb-3">' . h(lang('Paste the iCal address of a public holiday calendar: its days are read now and again every day. Google Calendar publishes one for every country, for example:')) . '
                    <code class="d-block text-break my-1">' . h($example) . '</code>'
                    . h(lang('Write the country in place of "turkish" (usa, uk, german, french…).')) . '</p>';
}

/**
 * The department choices of the add forms.
 *
 * @param array $departments
 * @return string
 */
function ws_holidays_department_options($departments)
{
    $options = '<option value="0">' . h(lang('The whole company')) . '</option>';

    foreach ($departments as $department) {
        $options .= '<option value="' . (int) $department['id'] . '">' . h($department['name']) . '</option>';
    }

    return $options;
}

/**
 * The fields of an add form. The Workspace Settings screen names them as
 * posted there and marks the needed ones required; the dialog names them
 * through ws_holidays_settings_field(), joins them to the settings form and
 * checks them on the server, since a required field in a drawer would block
 * the pane's Save.
 *
 * @param string      $action  holiday_add | feed_add
 * @param array       $departments
 * @param callable    $name    function ($field) the field's name
 * @param string      $prefix  the ids' prefix
 * @param string|null $form    the settings form's id, null on the old screen
 * @return string
 */
function ws_holidays_add_fields_html($action, $departments, $name, $prefix, $form = null)
{
    $join = ($form !== null) ? ' form="' . h($form) . '"' : '';
    $required = ($form === null) ? ' required' : '';
    $options = ws_holidays_department_options($departments);
    $field = function ($field) use ($name) {
        return h((string) call_user_func($name, $field));
    };

    if ($action === 'feed_add') {
        return '
                    <div class="row g-3' . (($form === null) ? ' align-items-end mt-1' : '') . '">
                        <div class="' . (($form === null) ? 'lg:col-6' : 'col-12') . '">
                            <label class="form-label small" for="' . $prefix . 'url">' . h(lang('Calendar address (iCal)')) . '</label>
                            <input class="form-control form-control-sm" type="url" name="' . $field('url') . '" id="' . $prefix . 'url"' . $join . $required . ' placeholder="https://…/basic.ics">
                        </div>
                        <div class="col-6' . (($form === null) ? ' lg:col-3' : '') . '">
                            <label class="form-label small" for="' . $prefix . 'name">' . h(lang('Name')) . '</label>
                            <input class="form-control form-control-sm" type="text" name="' . $field('name') . '" id="' . $prefix . 'name"' . $join . ' maxlength="120">
                        </div>
                        <div class="col-6' . (($form === null) ? ' lg:col-3' : '') . '">
                            <label class="form-label small" for="' . $prefix . 'for">' . h(lang('Who it is for')) . '</label>
                            <select class="form-control form-control-sm" name="' . $field('department_id') . '" id="' . $prefix . 'for"' . $join . '>' . $options . '</select>
                        </div>
                        <div class="' . (($form === null) ? 'sm:col-8' : 'col-12') . '">
                            <div class="form-field mb-0">
                                <input class="check" type="checkbox" name="' . $field('public_only') . '" value="1" id="' . $prefix . 'public"' . $join . ' checked>
                                <label class="small" for="' . $prefix . 'public">' . h(lang('Only the public holidays (observances and half days are left out)')) . '</label>
                            </div>
                        </div>';
    }

    return '
                            <div class="row g-3' . (($form === null) ? ' align-items-end' : '') . '">
                                <div class="sm:col-12">
                                    <label class="form-label small" for="' . $prefix . 'name">' . h(($form === null) ? lang('Add a holiday') : lang('Name')) . '</label>
                                    <input class="form-control form-control-sm" type="text" name="' . $field('name') . '" id="' . $prefix . 'name"' . $join . ' maxlength="255"' . $required . (($form === null) ? ' placeholder="' . h(lang('Name')) . '"' : '') . '>
                                </div>
                                <div class="col-6' . (($form === null) ? ' sm:col-4' : '') . '">
                                    <label class="form-label small" for="' . $prefix . 'from">' . h(lang('First day')) . '</label>
                                    <input class="form-control form-control-sm" type="date" data-pg-date name="' . $field('from') . '" id="' . $prefix . 'from"' . $join . $required . '>
                                </div>
                                <div class="col-6' . (($form === null) ? ' sm:col-4' : '') . '">
                                    <label class="form-label small" for="' . $prefix . 'to">' . h(lang('Last day')) . '</label>
                                    <input class="form-control form-control-sm" type="date" data-pg-date name="' . $field('to') . '" id="' . $prefix . 'to"' . $join . ' aria-describedby="' . $prefix . 'to_hint">
                                    <div class="form-text" id="' . $prefix . 'to_hint">' . h(lang('Leave it empty for one day.')) . '</div>
                                </div>
                                <div class="' . (($form === null) ? 'sm:col-4' : 'col-12') . '">
                                    <label class="form-label small" for="' . $prefix . 'for">' . h(lang('Who it is for')) . '</label>
                                    <select class="form-control form-control-sm" name="' . $field('department_id') . '" id="' . $prefix . 'for"' . $join . '>' . $options . '</select>
                                </div>
                                <div class="' . (($form === null) ? 'sm:col-8' : 'col-12') . '">
                                    <div class="form-field mb-0">
                                        <input class="check" type="checkbox" name="' . $field('yearly') . '" value="1" id="' . $prefix . 'yearly"' . $join . '>
                                        <label class="small" for="' . $prefix . 'yearly">' . h(lang('Every year on the same day')) . '</label>
                                    </div>
                                </div>';
}

/**
 * The working calendar card of the Workspace Settings screen.
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

    $form = function ($action, $inner, $class = 'd-inline') use ($self_url) {
        return '<form method="post" action="' . h($self_url) . '" class="' . $class . '">' . get_token_field()
            . '<input type="hidden" name="ws_action" value="' . h($action) . '">' . $inner . '</form>';
    };

    // A row action posts its own form with the action's fields hidden.
    $button = function ($action, $values, $class, $inner, $label) use ($form) {
        $hidden = '';

        foreach ($values as $field => $value) {
            $hidden .= '<input type="hidden" name="' . h($field) . '" value="' . h($value) . '">';
        }

        return $form($action, $hidden . '<button type="submit" class="' . h($class) . '"' . (($label !== '') ? ' title="' . h(lang('Remove')) . '" aria-label="' . h($label) . '"' : '') . '>' . $inner . '</button>');
    };

    $named = function ($field) {
        return $field;
    };

    // The calendar addresses, each with the list of its days.
    $feeds = '';

    foreach (ws_holiday_feeds() as $feed) {
        $days = ws_holiday_feed_days_html($feed, $button);
        $feeds .= ws_holiday_feed_row_html($feed, $departments, $days['left_out'], $button);

        if ($days['count'] > 0) {
            $feeds .= '
            <details class="ws-feed-days" id="ws-feed-' . (int) $feed['id'] . '"' . ((!empty($_GET['feed']) && ((int) $_GET['feed'] === (int) $feed['id'])) ? ' open' : '') . '>
                <summary class="small">' . h(lang(array('string' => 'Days from this calendar ({var:1})', 'vars' => $days['count']))) . '</summary>
                <p class="small fg-secondary my-1">' . h(lang('A day that is not a day off at this company can be left out; reading the calendar again keeps the choice.')) . '</p>
                <ul class="list-unstyled small mb-0">' . $days['html'] . '</ul>
            </details>';
        }
    }

    return '
        <div class="card mt-7" id="ws-calendar">
            <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-calendar-x me-1" aria-hidden="true"></i>' . h(lang('Working calendar')) . '</h2></div>
            <div class="card-body">
                ' . ws_holidays_settings_intro_html() . '

                <div class="row g-7">
                    <div class="lg:col-7">
                        <h3 class="h6">' . h(lang('Holidays')) . '</h3>
                        ' . ws_holidays_rows_html($departments, $button) . '
                        ' . $form('holiday_add', ws_holidays_add_fields_html('holiday_add', $departments, $named, 'ws_hol_') . '
                                <div class="sm:col-4 sm:text-end">
                                    <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add')) . '</button>
                                </div>
                            </div>', 'ws-holiday-form') . '
                    </div>

                    <div class="lg:col-5">
                        ' . ws_holidays_next_html($departments) . '
                    </div>
                </div>

                <hr>

                ' . ws_holiday_feeds_intro_html() . '
                ' . $feeds . '
                ' . $form('feed_add', ws_holidays_add_fields_html('feed_add', $departments, $named, 'ws_feed_') . '
                        <div class="sm:col-4 sm:text-end">
                            <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>' . h(lang('Add and read')) . '</button>
                        </div>
                    </div>', '') . '
            </div>
        </div>' . get_date_picker_format() . pg_fields_script();
}
