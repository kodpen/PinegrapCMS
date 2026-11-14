<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's tasks written to their own Google Calendar or
 * Outlook calendar through a connected account of theirs (purpose
 * calendar_sync, includes/connect/purposes/calendar_sync.php).
 *
 * The site makes one calendar of its own in the person's account and keeps
 * there the same tasks the calendar subscription carries
 * (ws_calendar_feed_task_items() and the coming copies of repeating tasks)
 * and their confirmed bookings when they are a booking staff member
 * (pg_booking_calendar_items()), titles, dates and a link only. A booking
 * moved in the calendar is never taken: its time is the booking row's. Google's scope calendar.app.created reaches
 * only calendars the site made; Microsoft has no narrower scope than
 * Calendars.ReadWrite, so the site keeps to the calendar it made by its id.
 *
 * Every item has an id derived from the site and the task
 * (ws_calsync_event_id()). Google takes it as the event's own id, so a write
 * is a PUT to it and no list of ids is needed. Graph gives events ids of its
 * own: they are kept as derived id => Graph id ('remote'), and the derived
 * id goes as the create call's transactionId, so a repeated create makes no
 * second event. What was written (derived id => hash of the body), the
 * remote ids and the calendar's id are kept in a sealed service entry per
 * person (pg_connect_service_put(), key ws_calsync:<user id>), so no table
 * is needed and an unchanged task costs no call.
 *
 * The workspace's scheduled job writes for one person a run, the one written
 * longest ago, at most WS_CALSYNC_WRITES_MAX calls; the person's Write now
 * button does the same at once.
 *
 * Before writing, a run reads back what changed in that calendar since the
 * last read (Google events.list with a syncToken, Graph calendarView/delta;
 * the token is kept in the same sealed entry). A task's event the person
 * moved writes the task's due date and time through the task drawer's own
 * check (ws_task_update_check(), ws_task_update()); a move that check refuses
 * is written back as it was and the person gets one bell line. Copies of
 * repeating tasks and done tasks are not moved; a deleted or renamed event
 * changes nothing and the next write of it restores it.
 *
 * The person's own plans (My calendar) can be written too, by a switch of
 * their own there, into a second calendar the site makes for plans: only
 * plans the person owns, whatever whom they are opened to, never a plan
 * someone else shared with them. Title, days and times and a link to the
 * plan only. Plans go one way: that calendar is never read back, and the
 * next write puts a plan back as it stands on the site. Plans never join the
 * calendar subscription's items (ws_calendar_feed_task_items()), which
 * build the outgoing iCal address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Google Calendar API v3. */
define('WS_GCAL_API', 'https://www.googleapis.com/calendar/v3');

/** Microsoft Graph, the signed-in person. */
define('WS_GRAPH_ME_API', 'https://graph.microsoft.com/v1.0/me');

/** The Graph scope a calendar call needs (Microsoft tokens are per resource). */
define('WS_GRAPH_CAL_SCOPE', 'https://graph.microsoft.com/Calendars.ReadWrite');

/** The most writes and deletions one run makes; the rest waits for the next. */
define('WS_CALSYNC_WRITES_MAX', 60);

/** How long the job leaves a person's calendar before writing it again, in seconds. */
define('WS_CALSYNC_JOB_EVERY', 3600);

/** The connected account's purpose. */
define('WS_CALSYNC_PURPOSE', 'calendar_sync');

/** The most pages of changes one run reads back. */
define('WS_CALSYNC_READ_PAGES', 10);

/** How long one read-back token is followed before a full read starts again, in seconds. */
define('WS_CALSYNC_READ_ROUND', 2592000);


/* ---------------------------------------------------------------------------
   Events (pure)
   --------------------------------------------------------------------------- */

/**
 * The calendars a person can write to, provider => name and icon.
 *
 * @return array
 */
function ws_calsync_provider_names()
{
    return array(
        'google'    => array('name' => 'Google Calendar', 'account' => 'Google', 'icon' => 'bi-google'),
        'microsoft' => array('name' => 'Outlook', 'account' => 'Microsoft', 'icon' => 'bi-microsoft'),
    );
}

/**
 * The derived id of one item: lower-case hex is within the base32hex letters
 * Google accepts as an event id (a-v, 0-9, 5 to 1024 long), and the site's
 * host keeps two sites writing to one account apart.
 *
 * @param string $host
 * @param string $uid  the item's uid (ws-task-<id>, ws-copy-...)
 * @return string
 */
function ws_calsync_event_id($host, $uid)
{
    return 'pg' . sha1(strtolower((string) $host) . '|' . (string) $uid);
}

/**
 * The Google event resource of one calendar feed item
 * (ws_calendar_feed_items()).
 *
 * @param array  $item
 * @param string $timezone the site's zone
 * @return array
 */
function ws_calsync_google_body($item, $timezone)
{
    if (!empty($item['all_day'])) {
        $first = (string) $item['start'];
        $last = (string) ($item['end'] ?? $first);
        $last = ($last < $first) ? $first : $last;

        // The end of a whole-day event is the day after its last.
        $start = array('date' => $first);
        $end = array('date' => date('Y-m-d', strtotime($last . ' 12:00:00 +1 day')));
    } else {
        $start = array('dateTime' => date('c', (int) $item['start']), 'timeZone' => (string) $timezone);
        $end = $start;

        // With no end the task is a deadline: an event of no length.
        if (!empty($item['end']) && ((int) $item['end'] > (int) $item['start'])) {
            $end = array('dateTime' => date('c', (int) $item['end']), 'timeZone' => (string) $timezone);
        }
    }

    $body = array(
        'summary'      => (string) $item['summary'],
        'description'  => (string) ($item['description'] ?? ''),
        'start'        => $start,
        'end'          => $end,
        // A task written earlier and deleted in Google is cancelled there,
        // not gone: the PUT brings it back.
        'status'       => (strtoupper((string) ($item['status'] ?? '')) === 'TENTATIVE') ? 'tentative' : 'confirmed',
        'transparency' => !empty($item['transparent']) ? 'transparent' : 'opaque',
        // The workspace sends its own reminders.
        'reminders'    => array('useDefault' => false),
        'extendedProperties' => array('private' => array('pinegrap_uid' => (string) $item['uid'])),
    );

    if ((string) ($item['url'] ?? '') !== '') {
        $body['source'] = array('title' => (string) $item['summary'], 'url' => (string) $item['url']);
    }

    return $body;
}

/**
 * The Graph event resource of one calendar feed item. Times go in UTC: Graph
 * reads Windows zone names and not every IANA one, and UTC is placed right
 * by every reader; a whole-day event runs from midnight to midnight.
 *
 * @param array $item
 * @return array
 */
function ws_calsync_graph_body($item)
{
    $all_day = !empty($item['all_day']);

    if ($all_day) {
        $first = (string) $item['start'];
        $last = (string) ($item['end'] ?? $first);
        $last = ($last < $first) ? $first : $last;

        $start = array('dateTime' => $first . 'T00:00:00', 'timeZone' => 'UTC');
        $end = array('dateTime' => date('Y-m-d', strtotime($last . ' 12:00:00 +1 day')) . 'T00:00:00', 'timeZone' => 'UTC');
    } else {
        $start = array('dateTime' => gmdate('Y-m-d\TH:i:s', (int) $item['start']), 'timeZone' => 'UTC');
        $end = $start;

        if (!empty($item['end']) && ((int) $item['end'] > (int) $item['start'])) {
            $end = array('dateTime' => gmdate('Y-m-d\TH:i:s', (int) $item['end']), 'timeZone' => 'UTC');
        }
    }

    $tentative = (strtoupper((string) ($item['status'] ?? '')) === 'TENTATIVE');

    return array(
        'subject'      => (string) $item['summary'],
        // Plain text: a task's words are never read as HTML there.
        'body'         => array('contentType' => 'text', 'content' => (string) ($item['description'] ?? '')),
        'start'        => $start,
        'end'          => $end,
        'isAllDay'     => $all_day,
        'showAs'       => $tentative ? 'tentative' : (!empty($item['transparent']) ? 'free' : 'busy'),
        // The workspace sends its own reminders.
        'isReminderOn' => false,
    );
}

/**
 * @param array $body
 * @return string
 */
function ws_calsync_body_hash($body)
{
    return sha1((string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * The calls one run makes: deletions of the ids written before and no longer
 * carried first, then the events new or changed since the last write, no
 * more than $limit together.
 *
 * @param array $bodies  event id => body, what the calendar should carry
 * @param array $written event id => body hash, what was written before
 * @param int   $limit
 * @return array put (id => body), delete (ids), left (calls for a later run)
 */
function ws_calsync_plan($bodies, $written, $limit)
{
    $delete = array();
    $put = array();

    foreach (array_keys($written) as $id) {
        if (!isset($bodies[$id])) {
            $delete[] = $id;
        }
    }

    foreach ($bodies as $id => $body) {
        if (!isset($written[$id]) || ((string) $written[$id] !== ws_calsync_body_hash($body))) {
            $put[$id] = $body;
        }
    }

    $limit = max(0, (int) $limit);
    $total = count($delete) + count($put);
    $delete = array_slice($delete, 0, $limit);
    $put = array_slice($put, 0, $limit - count($delete), true);

    return array('put' => $put, 'delete' => $delete, 'left' => $total - count($delete) - count($put));
}

/**
 * A stored state in its known shape, array() when writing is off for both
 * tasks and plans. The tasks calendar_id is '' while only plans are written;
 * a plans calendar is kept after the switch went off until its events are
 * deleted.
 *
 * @param array $state as unsealed
 * @return array provider, connection_id, calendar_id, written (id => hash),
 *               remote (id => provider's id), synced_at, error, left,
 *               read_token (Google syncToken or Graph deltaLink), read_started,
 *               plans (the switch), plans_calendar_id, plans_written (id => hash)
 */
function ws_calsync_state_clean($state)
{
    $state = is_array($state) ? $state : array();
    $provider = (string) ($state['provider'] ?? 'google');
    $calendar = is_string($state['calendar_id'] ?? null) ? $state['calendar_id'] : '';
    $plans = !empty($state['plans']);
    $plans_calendar = is_string($state['plans_calendar_id'] ?? null) ? $state['plans_calendar_id'] : '';

    if ((($calendar === '') && !$plans && ($plans_calendar === '')) || !isset(ws_calsync_provider_names()[$provider])) {
        return array();
    }

    $remote = array();

    foreach ((is_array($state['remote'] ?? null) ? $state['remote'] : array()) as $id => $value) {
        if (is_string($value) && ($value !== '')) {
            $remote[(string) $id] = $value;
        }
    }

    return array(
        'provider'          => $provider,
        'connection_id'     => (int) ($state['connection_id'] ?? 0),
        'calendar_id'       => $calendar,
        'written'           => is_array($state['written'] ?? null) ? $state['written'] : array(),
        'remote'            => $remote,
        'synced_at'         => (int) ($state['synced_at'] ?? 0),
        'error'             => (string) ($state['error'] ?? ''),
        'left'              => (int) ($state['left'] ?? 0),
        'read_token'        => is_string($state['read_token'] ?? null) ? $state['read_token'] : '',
        'read_started'      => (int) ($state['read_started'] ?? 0),
        'plans'             => $plans,
        'plans_calendar_id' => $plans_calendar,
        'plans_written'     => is_array($state['plans_written'] ?? null) ? $state['plans_written'] : array(),
    );
}

/**
 * Whether the person's tasks are written (the tasks calendar is there).
 *
 * @param array $state ws_calsync_state_clean()
 * @return bool
 */
function ws_calsync_tasks_on($state)
{
    return !empty($state) && ((string) $state['calendar_id'] !== '');
}

/**
 * The day and time an event of the site's Google calendar starts on, in the
 * site's zone; a Google body (ws_calsync_google_body()) reads the same way.
 *
 * @param array  $start    the event's start (date, or dateTime and timeZone)
 * @param string $timezone the site's zone
 * @return array|null date (Y-m-d), time (H:i, '' for a whole day)
 */
function ws_calsync_google_when($start, $timezone)
{
    if (!is_array($start)) {
        return null;
    }

    if (isset($start['date']) && is_string($start['date']) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $start['date'])) {
        return array('date' => $start['date'], 'time' => '');
    }

    if (!isset($start['dateTime']) || !is_string($start['dateTime'])) {
        return null;
    }

    try {
        $moment = new DateTime($start['dateTime']);
        $moment->setTimezone(new DateTimeZone((string) $timezone));
    } catch (Exception $e) {
        return null;
    }

    return array('date' => $moment->format('Y-m-d'), 'time' => $moment->format('H:i'));
}

/**
 * The day and time a Graph event starts on, in the site's zone; a Graph body
 * (ws_calsync_graph_body()) reads the same way. Graph answers in UTC unless
 * asked otherwise; a whole-day event is read by its date alone.
 *
 * @param array  $start    the event's start (dateTime, timeZone)
 * @param bool   $all_day
 * @param string $timezone the site's zone
 * @return array|null date (Y-m-d), time (H:i, '' for a whole day)
 */
function ws_calsync_graph_when($start, $all_day, $timezone)
{
    if (!is_array($start) || !isset($start['dateTime']) || !is_string($start['dateTime'])
        || !preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})T([0-9]{2}:[0-9]{2}:[0-9]{2})/', $start['dateTime'], $match)) {
        return null;
    }

    if ($all_day) {
        return array('date' => $match[1], 'time' => '');
    }

    try {
        $moment = new DateTime($match[1] . ' ' . $match[2], new DateTimeZone((string) ($start['timeZone'] ?? 'UTC') ?: 'UTC'));
        $moment->setTimezone(new DateTimeZone((string) $timezone));
    } catch (Exception $e) {
        return null;
    }

    return array('date' => $moment->format('Y-m-d'), 'time' => $moment->format('H:i'));
}

/**
 * When a body written by the site starts.
 *
 * @param string $provider
 * @param array  $body
 * @param string $timezone
 * @return array|null ws_calsync_google_when()
 */
function ws_calsync_body_when($provider, $body, $timezone)
{
    return ($provider === 'microsoft')
        ? ws_calsync_graph_when($body['start'] ?? null, !empty($body['isAllDay']), $timezone)
        : ws_calsync_google_when($body['start'] ?? null, $timezone);
}

/**
 * The events the person moved, out of the changes read back: an event of a
 * task (not a coming copy of a repeating one) whose calendar still held what
 * the site wrote last and that starts on another day or time now. A task
 * changed on the site since that write is left to the write that follows:
 * the site's date wins. A deleted or renamed event is no move.
 *
 * @param array  $changes  derived id => when (ws_calsync_google_when()), null for a deleted event
 * @param array  $bodies   derived id => body, what the calendar should carry
 * @param array  $written  derived id => body hash, what was written before
 * @param array  $tasks    derived id => task id, the tasks among the bodies (or
 *                         booking id, the bookings, whose moves are refused)
 * @param string $provider
 * @param string $timezone
 * @return array derived id => task_id, when
 */
function ws_calsync_moves($changes, $bodies, $written, $tasks, $provider, $timezone)
{
    $moves = array();

    foreach ($changes as $id => $when) {
        if (!is_array($when) || !isset($tasks[$id], $bodies[$id], $written[$id])
            || ((string) $written[$id] !== ws_calsync_body_hash($bodies[$id]))) {
            continue;
        }

        $was = ws_calsync_body_when($provider, $bodies[$id], $timezone);

        // The site's own write read back, or a new title only.
        if (!$was || ($was === $when)) {
            continue;
        }

        $moves[$id] = array('task_id' => (int) $tasks[$id], 'when' => $when);
    }

    return $moves;
}

/**
 * The task fields a move in the calendar writes, as the board's drag onto
 * another day moves them: the due date, a start date by as many days (a task
 * with only a start date gets the day as its due date), and the due time
 * when the task has that field (a whole-day event clears it).
 *
 * @param array $task
 * @param array $when date, time (ws_calsync_google_when())
 * @return array empty when nothing changes
 */
function ws_calsync_move_fields($task, $when)
{
    $date = (string) $when['date'];
    $due = (string) ($task['due_date'] ?? '');
    $start = (string) ($task['start_date'] ?? '');
    $due = ($due > '0000-00-00') ? $due : '';
    $start = ($start > '0000-00-00') ? $start : '';
    $fields = array();

    if ($date !== $due) {
        $fields['due_date'] = $date;

        if (($start !== '') && ($due !== '')) {
            $shift = (int) round((strtotime($date . ' 12:00:00') - strtotime($due . ' 12:00:00')) / 86400);
            $fields['start_date'] = date('Y-m-d', strtotime($start . ' 12:00:00 ' . (($shift >= 0) ? '+' : '') . $shift . ' days'));
        } elseif ($start !== '') {
            $fields['start_date'] = null;
        }
    }

    if (array_key_exists('due_time', $task) && ((string) $when['time'] !== substr((string) $task['due_time'], 0, 5))) {
        $fields['due_time'] = (string) $when['time'];
    }

    return $fields;
}


/* ---------------------------------------------------------------------------
   The person's account and state
   --------------------------------------------------------------------------- */

/**
 * The providers the site can offer: the application is set up, tokens can be
 * sealed and the calendar subscription's tables are there.
 *
 * @return string[]
 */
function ws_calsync_offered()
{
    if (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready()
        || !pg_connect_can_seal() || !ws_calendar_feed_ready()) {
        return array();
    }

    $offered = array();

    foreach (array_keys(ws_calsync_provider_names()) as $provider) {
        if (pg_connect_app_ready(pg_connect_app($provider))) {
            $offered[] = $provider;
        }
    }

    return $offered;
}

/**
 * The person's connection with a provider for calendar writing, or null.
 *
 * @param int    $user_id
 * @param string $provider
 * @return array|null pg_connect_account_public()
 */
function ws_calsync_connection($user_id, $provider)
{
    if (!function_exists('pg_connect_accounts') || ((int) $user_id <= 0)) {
        return null;
    }

    $accounts = pg_connect_accounts(array('provider' => (string) $provider, 'user_id' => (int) $user_id, 'purpose' => WS_CALSYNC_PURPOSE));

    foreach ($accounts as $account) {
        if ($account['status'] === 'active') {
            return $account;
        }
    }

    return $accounts ? $accounts[0] : null;
}

/**
 * @param int $user_id
 * @return string
 */
function ws_calsync_state_key($user_id)
{
    return 'ws_calsync:' . (int) $user_id;
}

/**
 * @param int $user_id
 * @return array ws_calsync_state_clean()
 */
function ws_calsync_state($user_id)
{
    return ws_calsync_state_clean(function_exists('pg_connect_service_get') ? pg_connect_service_get(ws_calsync_state_key($user_id)) : array());
}

/**
 * @param int   $user_id
 * @param array $state
 * @return bool
 */
function ws_calsync_state_save($user_id, $state)
{
    return pg_connect_service_put(ws_calsync_state_key($user_id), 'Calendar sync (Workspace tasks)', $state);
}

/**
 * The error text of a failed call (Google and Graph both answer
 * error.message).
 *
 * @param array $result pg_connect_api()
 * @return string
 */
function ws_calsync_error($result)
{
    if (isset($result['data']['error']['message']) && is_string($result['data']['error']['message'])) {
        return pg_connect_clip('HTTP ' . (int) $result['status'] . ': ' . $result['data']['error']['message']);
    }

    return pg_connect_clip(((string) $result['error'] !== '') ? (string) $result['error'] : 'HTTP ' . (int) $result['status']);
}

/**
 * One call to the provider's calendar API.
 *
 * @param string     $provider
 * @param int        $connection_id
 * @param string     $method
 * @param string     $path after the API's base
 * @param array|null $body
 * @return array pg_connect_api()
 */
function ws_calsync_call($provider, $connection_id, $method, $path, $body = null)
{
    $options = array('headers' => array('Accept: application/json'), 'timeout' => 30);

    if ($body !== null) {
        $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $options['headers'][] = 'Content-Type: application/json';
    }

    if ($provider === 'microsoft') {
        // A page or delta link Graph handed back is followed as it is.
        $url = (strpos($path, 'https://graph.microsoft.com/') === 0) ? $path : WS_GRAPH_ME_API . $path;

        return pg_connect_api((int) $connection_id, $method, $url, $options, array(WS_GRAPH_CAL_SCOPE));
    }

    return pg_connect_api((int) $connection_id, $method, WS_GCAL_API . $path, $options);
}


/* ---------------------------------------------------------------------------
   Reading changes back
   --------------------------------------------------------------------------- */

/**
 * What changed in the site's calendar since the last read: Google's
 * events.list with the syncToken, Graph's calendarView/delta with its delta
 * link. Without a token (the first read, or one the provider let expire) the
 * whole calendar is read and the token it ends with is kept; a token older
 * than WS_CALSYNC_READ_ROUND starts a full read again, which also moves
 * Graph's window on with the days.
 *
 * @param array  $state    changed: read_token, read_started, remote ids of events gone
 * @param int    $connection_id
 * @param string $since    Y-m-d, Graph's window
 * @param string $until    Y-m-d
 * @param string $timezone the site's zone
 * @return array ok, error, changes (derived id => when, null for a deleted event)
 */
function ws_calsync_read(&$state, $connection_id, $since, $until, $timezone)
{
    if ($state['read_started'] < time() - WS_CALSYNC_READ_ROUND) {
        $state['read_token'] = '';
    }

    for ($round = 0; $round < 2; $round++) {
        $read = ($state['provider'] === 'microsoft')
            ? ws_calsync_read_graph($state, $connection_id, $since, $until, $timezone)
            : ws_calsync_read_google($state, $connection_id, $timezone);

        // The token expired (410 on both): read everything once more.
        if (!$read['ok'] && ($read['status'] === 410) && ($state['read_token'] !== '')) {
            $state['read_token'] = '';
            continue;
        }

        break;
    }

    if ($read['ok'] && ($read['token'] !== '')) {
        if ($state['read_token'] === '') {
            $state['read_started'] = time();
        }

        $state['read_token'] = $read['token'];
    }

    return array('ok' => $read['ok'], 'error' => $read['error'], 'changes' => $read['changes']);
}

/**
 * @param array  $state
 * @param int    $connection_id
 * @param string $timezone
 * @return array ok, status, error, changes, token
 */
function ws_calsync_read_google($state, $connection_id, $timezone)
{
    $answer = array('ok' => false, 'status' => 0, 'error' => '', 'changes' => array(), 'token' => '');
    $path = '/calendars/' . rawurlencode($state['calendar_id']) . '/events';
    $query = array('maxResults' => 250);

    if ($state['read_token'] !== '') {
        $query['syncToken'] = $state['read_token'];
    }

    for ($page = 0; $page < WS_CALSYNC_READ_PAGES; $page++) {
        $done = ws_calsync_call('google', $connection_id, 'GET', $path . '?' . http_build_query($query));

        if (!$done['ok'] || !is_array($done['data'])) {
            return array('status' => (int) $done['status'], 'error' => ws_calsync_error($done)) + $answer;
        }

        foreach ((is_array($done['data']['items'] ?? null) ? $done['data']['items'] : array()) as $event) {
            if (!is_array($event) || !isset($event['id']) || !is_string($event['id'])) {
                continue;
            }

            $answer['changes'][$event['id']] = ((string) ($event['status'] ?? '') === 'cancelled')
                ? null
                : ws_calsync_google_when($event['start'] ?? null, $timezone);
        }

        if (!empty($done['data']['nextPageToken']) && is_string($done['data']['nextPageToken'])) {
            $query['pageToken'] = $done['data']['nextPageToken'];
            continue;
        }

        $answer['token'] = (string) ($done['data']['nextSyncToken'] ?? '');
        break;
    }

    $answer['ok'] = true;

    return $answer;
}

/**
 * @param array  $state
 * @param int    $connection_id
 * @param string $since
 * @param string $until
 * @param string $timezone
 * @return array ok, status, error, changes, token
 */
function ws_calsync_read_graph($state, $connection_id, $since, $until, $timezone)
{
    $answer = array('ok' => false, 'status' => 0, 'error' => '', 'changes' => array(), 'token' => '');
    $derived = array_flip($state['remote']);
    $link = ($state['read_token'] !== '')
        ? $state['read_token']
        : '/calendars/' . rawurlencode($state['calendar_id']) . '/calendarView/delta?' . http_build_query(array(
            'startDateTime' => $since . 'T00:00:00Z',
            'endDateTime'   => $until . 'T23:59:59Z',
        ));

    for ($page = 0; $page < WS_CALSYNC_READ_PAGES; $page++) {
        $done = ws_calsync_call('microsoft', $connection_id, 'GET', $link);

        if (!$done['ok'] || !is_array($done['data'])) {
            return array('status' => (int) $done['status'], 'error' => ws_calsync_error($done)) + $answer;
        }

        foreach ((is_array($done['data']['value'] ?? null) ? $done['data']['value'] : array()) as $event) {
            // Only the events the site made, by the ids Graph gave them.
            if (!is_array($event) || !isset($event['id']) || !is_string($event['id']) || !isset($derived[$event['id']])) {
                continue;
            }

            $answer['changes'][$derived[$event['id']]] = isset($event['@removed'])
                ? null
                : ws_calsync_graph_when($event['start'] ?? null, !empty($event['isAllDay']), $timezone);
        }

        $next = (string) ($done['data']['@odata.nextLink'] ?? '');

        if ($next !== '') {
            $link = $next;
            continue;
        }

        $answer['token'] = (string) ($done['data']['@odata.deltaLink'] ?? '');
        break;
    }

    // A link from elsewhere is never followed.
    if (strpos($answer['token'], 'https://graph.microsoft.com/') !== 0) {
        $answer['token'] = '';
    }

    $answer['ok'] = true;

    return $answer;
}

/**
 * Writes one move made in the calendar to its task, through the task
 * drawer's own check, as the person who owns the calendar.
 *
 * @param array $viewer ws_viewer()
 * @param array $user   the person's user row
 * @param int   $task_id
 * @param array $when   date, time
 * @return string kept | refused | skipped (a done task, a copy of a repeating one, nothing to change)
 */
function ws_calsync_apply_move($viewer, $user, $task_id, $when)
{
    $task = ws_task((int) $task_id);

    if (!$task || ((string) $task['status'] === 'done') || ((int) ($task['recurrence_id'] ?? 0) > 0)) {
        return 'skipped';
    }

    $fields = ws_calsync_move_fields($task, $when);

    if (!$fields) {
        return 'skipped';
    }

    $check = ws_task_update_check($viewer, $task, $fields);
    $result = $check['ok'] ? ws_task_update($viewer, $task, $fields) : $check;

    if (!$result['ok']) {
        ws_notify($viewer['id'], 'calendar_refused', array('task_id' => (int) $task['id'], 'actor_id' => 0));

        return 'refused';
    }

    $after = ws_task($task['id']);

    log_activity(lang(array(
        'string' => 'moved workspace task {var:1} to {var:2} from their calendar',
        'vars'   => array(ws_task_number($task['id']), ws_task_due_label($after)),
    )), (string) ($user['username'] ?? ''));

    // The task's card in its channel is drawn again where it is open.
    if (((int) ($task['source_message_id'] ?? 0) > 0) && function_exists('ws_message_touch')) {
        ws_message_touch($task['source_message_id']);
    }

    return 'kept';
}

/**
 * Calendar feed items of a staff member's confirmed bookings
 * (pg_booking_calendar_items()): timed and busy, uid ws-booking-<id>. A
 * booking cancelled, done or marked no-show is no longer among them, so the
 * sync deletes its event.
 *
 * @param array[] $bookings pg_booking_calendar_item_rows()'s rows
 * @param string  $base     the panel's address, ending in a slash
 * @return array[]
 */
function ws_calsync_booking_feed_items($bookings, $base)
{
    $items = array();

    foreach ($bookings as $booking) {
        $url = $base . (string) $booking['url'];
        $lines = array(lang(array('string' => 'Booking #{var:1}', 'vars' => (int) $booking['id'])));

        if ((string) $booking['service'] !== '') {
            $lines[] = lang(array('string' => 'Service: {var:1}', 'vars' => (string) $booking['service']));
        }

        if ((string) $booking['customer'] !== '') {
            $lines[] = lang(array('string' => 'Customer: {var:1}', 'vars' => (string) $booking['customer']));
        }

        $lines[] = '';
        $lines[] = $url;

        $items[] = array(
            'uid'         => 'ws-booking-' . (int) $booking['id'],
            'summary'     => (string) $booking['title'],
            'description' => implode("\n", $lines),
            'url'         => $url,
            'all_day'     => false,
            'start'       => (int) $booking['starts_at'],
            'end'         => (int) $booking['ends_at'],
            'rrule'       => '',
            'categories'  => array(lang('Booking')),
            'status'      => 'CONFIRMED',
            'transparent' => false,
            'stamp'       => (int) $booking['starts_at'],
        );
    }

    return $items;
}

/**
 * What the person's calendar should carry, as event bodies, and which of
 * them are tasks and which bookings.
 *
 * @param array  $viewer
 * @param string $provider
 * @param string $since    Y-m-d
 * @param string $until    Y-m-d
 * @param string $timezone
 * @return array bodies (derived id => body), tasks (derived id => task id),
 *               bookings (derived id => pg_booking_calendar_items() row)
 */
function ws_calsync_bodies($viewer, $provider, $since, $until, $timezone)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

    $booked = array();

    foreach (pg_booking_calendar_items((int) $viewer['id'], strtotime($since . ' 00:00:00'), strtotime($until . ' 23:59:59')) as $booking) {
        $booked['ws-booking-' . (int) $booking['id']] = $booking;
    }

    $items = array_merge(
        ws_calsync_booking_feed_items(array_values($booked), ws_calendar_feed_panel_url('')),
        ws_calendar_feed_task_items($viewer, false, $since, $until),
        ws_calendar_feed_copy_items($viewer, date('Y-m-d'))
    );

    $bodies = array();
    $tasks = array();
    $bookings = array();

    foreach (array_slice($items, 0, WS_CALENDAR_FEED_LIMIT) as $item) {
        $uid = (string) $item['uid'];
        $id = ws_calsync_event_id(HOSTNAME_SETTING, $uid);
        $bodies[$id] = ($provider === 'microsoft')
            ? ws_calsync_graph_body($item)
            : ws_calsync_google_body($item, $timezone);

        if (preg_match('/^ws-task-([0-9]+)$/', $uid, $match)) {
            $tasks[$id] = (int) $match[1];
        } elseif (isset($booked[$uid])) {
            $bookings[$id] = $booked[$uid];
        }
    }

    return array('bodies' => $bodies, 'tasks' => $tasks, 'bookings' => $bookings);
}


/**
 * The times one plan is written as, inside the writer's window: today one,
 * the plan's own days, under the uid ws-plan-<id>. A plan that repeats is
 * written one event a time, each with a suffix of its day (-Ymd) in its key.
 *
 * @param array $plan     a plan row (ws_personal_plan())
 * @param int   $since_ts the window's first moment
 * @param int   $until_ts the moment after the window
 * @return array[] key (the uid's suffix), starts_at, ends_at (exclusive)
 */
function ws_calsync_plan_times($plan, $since_ts, $until_ts)
{
    $starts = (int) ($plan['starts_at'] ?? 0);
    $ends = max($starts, (int) ($plan['ends_at'] ?? 0));

    if (($starts <= 0) || ($starts >= (int) $until_ts) || (max($ends, $starts + 1) <= (int) $since_ts)) {
        return array();
    }

    return array(array('key' => '', 'starts_at' => $starts, 'ends_at' => $ends));
}

/**
 * The calendar items of the person's own plans: those whose owner is the
 * person, read through the plans' gate, whoever else they are opened to. A
 * plan someone else shared with the person is never one. Title, days and
 * times and a link to the plan on My calendar; never the note, the
 * customer or the roadmap's steps.
 *
 * @param array[] $plans    plan rows the person may see (ws_personal_plans_between())
 * @param int     $user_id  the person
 * @param int[]   $team     the team's ids (ws_team_ids())
 * @param string  $base     the panel's address, ending in a slash
 * @param int     $since_ts
 * @param int     $until_ts
 * @return array[] items as ws_calendar_feed_items() gives them
 */
function ws_calsync_plan_items($plans, $user_id, $team, $base, $since_ts, $until_ts)
{
    $items = array();
    $user_id = (int) $user_id;

    foreach ($plans as $plan) {
        if (!is_array($plan) || ((int) ($plan['user_id'] ?? 0) !== $user_id)
            || !ws_personal_plan_can_see($user_id, $plan, $team, array())) {
            continue;
        }

        $id = (int) $plan['id'];
        $url = $base . 'workspace_my_calendar.php?plan=' . $id;
        $all_day = !empty($plan['all_day']);

        foreach (ws_calsync_plan_times($plan, $since_ts, $until_ts) as $time) {
            $items[] = array(
                'uid'         => 'ws-plan-' . $id . (string) $time['key'],
                'summary'     => (string) ($plan['title'] ?? ''),
                'description' => $url,
                'url'         => $url,
                'all_day'     => $all_day,
                // A whole-day plan ends at midnight after its last day.
                'start'       => $all_day ? date('Y-m-d', $time['starts_at']) : (int) $time['starts_at'],
                'end'         => $all_day ? date('Y-m-d', max($time['starts_at'], $time['ends_at'] - 1)) : (int) $time['ends_at'],
                'rrule'       => '',
                'categories'  => array(),
                'status'      => 'CONFIRMED',
                'transparent' => true,
                'stamp'       => (int) $time['starts_at'],
            );
        }
    }

    return $items;
}

/**
 * What the person's plans calendar should carry: derived id => body, empty
 * while the switch is off, so the events written before are deleted.
 *
 * @param array  $state    ws_calsync_state_clean()
 * @param array  $viewer
 * @param string $since    Y-m-d
 * @param string $until    Y-m-d
 * @param string $timezone
 * @return array
 */
function ws_calsync_plan_bodies($state, $viewer, $since, $until, $timezone)
{
    if (empty($state['plans']) || !function_exists('ws_personal_plans_between')) {
        return array();
    }

    $since_ts = strtotime($since . ' 00:00:00');
    $until_ts = strtotime($until . ' 00:00:00 +1 day');
    $items = ws_calsync_plan_items(
        ws_personal_plans_between((int) $viewer['id'], $since_ts, $until_ts),
        (int) $viewer['id'],
        ws_team_ids(),
        ws_calendar_feed_panel_url(''),
        $since_ts,
        $until_ts
    );

    $bodies = array();

    foreach (array_slice($items, 0, WS_CALENDAR_FEED_LIMIT) as $item) {
        $bodies[ws_calsync_event_id(HOSTNAME_SETTING, (string) $item['uid'])] = ($state['provider'] === 'microsoft')
            ? ws_calsync_graph_body($item)
            : ws_calsync_google_body($item, $timezone);
    }

    return $bodies;
}


/* ---------------------------------------------------------------------------
   Writing
   --------------------------------------------------------------------------- */

/**
 * Makes a calendar of the site's in the person's account.
 *
 * @param string $provider
 * @param int    $connection_id
 * @param string $name
 * @param string $description
 * @return array ok, error, id
 */
function ws_calsync_make_calendar($provider, $connection_id, $name, $description)
{
    $made = ($provider === 'microsoft')
        ? ws_calsync_call($provider, $connection_id, 'POST', '/calendars', array('name' => $name))
        : ws_calsync_call($provider, $connection_id, 'POST', '/calendars', array(
            'summary'     => $name,
            'description' => $description,
            'timeZone'    => date_default_timezone_get(),
        ));

    if (!$made['ok'] || empty($made['data']['id']) || !is_string($made['data']['id'])) {
        return array('ok' => false, 'error' => ws_calsync_error($made), 'id' => '');
    }

    return array('ok' => true, 'error' => '', 'id' => $made['data']['id']);
}

/**
 * Turns writing on: makes the site's calendar in the person's account and
 * writes the tasks into it. While only plans are written, the account those
 * go to is the one tasks use too.
 *
 * @param int    $user_id
 * @param string $provider
 * @return array ok, error
 */
function ws_calsync_start($user_id, $provider)
{
    $state = ws_calsync_state($user_id);

    if (ws_calsync_tasks_on($state)) {
        return ws_calsync_sync($user_id);
    }

    $provider = $state ? $state['provider'] : (string) $provider;
    $account = in_array($provider, ws_calsync_offered(), true) ? ws_calsync_connection($user_id, $provider) : null;

    if (!$account || ($account['status'] !== 'active')) {
        return array('ok' => false, 'error' => lang('Connect your account first.'));
    }

    $made = ws_calsync_make_calendar(
        $provider,
        $account['id'],
        lang(array('string' => 'Workspace tasks ({var:1})', 'vars' => HOSTNAME_SETTING)),
        lang('Your tasks from the workspace. Changes are made on the site.')
    );

    if (!$made['ok']) {
        return array('ok' => false, 'error' => $made['error']);
    }

    $saved = ws_calsync_state_save($user_id, array(
        'provider'      => $provider,
        'connection_id' => (int) $account['id'],
        'calendar_id'   => $made['id'],
        'written'       => array(),
        'synced_at'     => 0,
        'error'         => '',
        'left'          => 0,
    ) + ($state ? $state : array('remote' => array())));

    if (!$saved) {
        return array('ok' => false, 'error' => lang('This site has no encryption key, so it cannot keep the connection.'));
    }

    return ws_calsync_sync($user_id);
}

/**
 * The account plans are written with: the one tasks are written with, or
 * else the first offered provider the person has connected.
 *
 * @param int   $user_id
 * @param array $state ws_calsync_state_clean()
 * @return array|null pg_connect_account_public() with provider
 */
function ws_calsync_plans_account($user_id, $state)
{
    $providers = $state ? array($state['provider']) : ws_calsync_offered();

    foreach ($providers as $provider) {
        $account = in_array($provider, ws_calsync_offered(), true) ? ws_calsync_connection($user_id, $provider) : null;

        if ($account && ($account['status'] === 'active')) {
            return $account + array('provider' => $provider);
        }
    }

    return null;
}

/**
 * The plans switch of My calendar. On needs a connected account and writes
 * at once; off is kept and the next run deletes the plan events, then the
 * empty plans calendar.
 *
 * @param int  $user_id
 * @param bool $on
 * @return array ok, error
 */
function ws_calsync_plans_switch($user_id, $on)
{
    $state = ws_calsync_state($user_id);

    if (!$on) {
        if (!$state || !$state['plans']) {
            return array('ok' => true, 'error' => '');
        }

        $state['plans'] = false;
        // Picked by the next run of the job, not an hour later.
        $state['left'] = max(1, (int) $state['left']);
        ws_calsync_state_save($user_id, $state);

        return array('ok' => true, 'error' => '');
    }

    $account = ws_calsync_plans_account($user_id, $state);

    if (!$account) {
        return array('ok' => false, 'error' => lang('Connect your account first.'));
    }

    $state = ws_calsync_state_clean(array('plans' => true, 'provider' => $account['provider'], 'connection_id' => (int) $account['id']) + ($state ? $state : array()));
    $state['plans'] = true;

    if (!ws_calsync_state_save($user_id, $state)) {
        return array('ok' => false, 'error' => lang('This site has no encryption key, so it cannot keep the connection.'));
    }

    return ws_calsync_sync($user_id);
}

/**
 * Deletes one written event.
 *
 * @param array       $state
 * @param int         $connection_id
 * @param string      $id       the derived id
 * @param string|null $calendar the calendar's id, the tasks one when null
 * @return array ok, error
 */
function ws_calsync_delete_event(&$state, $connection_id, $id, $calendar = null)
{
    $calendar = ($calendar === null) ? $state['calendar_id'] : (string) $calendar;

    if ($state['provider'] === 'microsoft') {
        // Never made (a create that failed): nothing to delete.
        if (!isset($state['remote'][$id])) {
            return array('ok' => true, 'error' => '');
        }

        $done = ws_calsync_call('microsoft', $connection_id, 'DELETE', '/events/' . rawurlencode($state['remote'][$id]));
    } else {
        $done = ws_calsync_call('google', $connection_id, 'DELETE', '/calendars/' . rawurlencode($calendar) . '/events/' . rawurlencode((string) $id));
    }

    // Already gone (404) or already deleted (410) is what was wanted.
    if ($done['ok'] || in_array((int) $done['status'], array(404, 410), true)) {
        unset($state['remote'][$id]);

        return array('ok' => true, 'error' => '');
    }

    return array('ok' => false, 'error' => ws_calsync_error($done));
}

/**
 * Writes one event, made when it is not there yet.
 *
 * @param array       $state
 * @param int         $connection_id
 * @param string      $id       the derived id
 * @param array       $body
 * @param string|null $calendar the calendar's id, the tasks one when null
 * @return array ok, error, gone (the calendar was deleted in the account)
 */
function ws_calsync_put_event(&$state, $connection_id, $id, $body, $calendar = null)
{
    $provider = $state['provider'];
    $calendar = ($calendar === null) ? $state['calendar_id'] : (string) $calendar;
    $done = array('ok' => false, 'status' => 404);

    if ($provider === 'microsoft') {
        $events = '/calendars/' . rawurlencode($calendar) . '/events';

        if (isset($state['remote'][$id])) {
            $done = ws_calsync_call($provider, $connection_id, 'PATCH', '/events/' . rawurlencode($state['remote'][$id]), $body);
        }

        // Not there (never made, or deleted in Outlook): made again. The
        // transactionId keeps a repeated create from making a second event.
        if ((int) $done['status'] === 404) {
            $done = ws_calsync_call($provider, $connection_id, 'POST', $events, $body + array('transactionId' => (string) $id));

            if ($done['ok'] && !empty($done['data']['id']) && is_string($done['data']['id'])) {
                $state['remote'][$id] = $done['data']['id'];
            }
        }
    } else {
        $events = '/calendars/' . rawurlencode($calendar) . '/events';
        $done = ws_calsync_call($provider, $connection_id, 'PUT', $events . '/' . rawurlencode((string) $id), $body);

        // Not there yet: made with the same id.
        if ((int) $done['status'] === 404) {
            $done = ws_calsync_call($provider, $connection_id, 'POST', $events, array('id' => (string) $id) + $body);
        }
    }

    if ($done['ok']) {
        return array('ok' => true, 'error' => '', 'gone' => false);
    }

    // A create answered 404: the person deleted the site's calendar, and
    // writing stops until they turn it on again, which makes a new one.
    return array('ok' => false, 'gone' => ((int) $done['status'] === 404), 'error' => ((int) $done['status'] === 404)
        ? lang('The Workspace tasks calendar was deleted from your account. Turn writing on again to make a new one.')
        : ws_calsync_error($done));
}

/**
 * Makes one run's calls (ws_calsync_plan()) on one calendar and keeps what
 * was written under $key ('written' for tasks, 'plans_written' for plans).
 *
 * @param array  $state
 * @param int    $connection_id
 * @param array  $plan     ws_calsync_plan()
 * @param string $calendar
 * @param string $key
 * @return array error, gone, written, deleted
 */
function ws_calsync_write(&$state, $connection_id, $plan, $calendar, $key)
{
    $done = array('error' => '', 'gone' => false, 'written' => 0, 'deleted' => 0);

    foreach ($plan['delete'] as $id) {
        $call = ws_calsync_delete_event($state, $connection_id, (string) $id, $calendar);

        if (!$call['ok']) {
            $done['error'] = $call['error'];

            return $done;
        }

        unset($state[$key][$id]);
        $done['deleted']++;
    }

    foreach ($plan['put'] as $id => $body) {
        $call = ws_calsync_put_event($state, $connection_id, (string) $id, $body, $calendar);

        if (!$call['ok']) {
            $done['error'] = $call['error'];
            $done['gone'] = !empty($call['gone']);

            return $done;
        }

        $state[$key][$id] = ws_calsync_body_hash($body);
        $done['written']++;
    }

    return $done;
}

/**
 * The plans' share of a run: the plans calendar made when the switch is on
 * and it is not there yet, the person's own plans written into it, and once
 * the switch is off its events deleted and then the empty calendar. Never
 * read back: an event moved or deleted there is written again as the plan
 * stands at its next change.
 *
 * @param array  $state
 * @param array  $viewer
 * @param int    $connection_id
 * @param int    $limit    calls this share may make
 * @param string $since    Y-m-d
 * @param string $until    Y-m-d
 * @param string $timezone
 * @return array error, written, deleted, left
 */
function ws_calsync_sync_plans(&$state, $viewer, $connection_id, $limit, $since, $until, $timezone)
{
    $answer = array('error' => '', 'written' => 0, 'deleted' => 0, 'left' => 0);

    if (!$state['plans'] && ($state['plans_calendar_id'] === '')) {
        return $answer;
    }

    $bodies = ws_calsync_plan_bodies($state, $viewer, $since, $until, $timezone);

    if ($state['plans_calendar_id'] === '') {
        $made = ws_calsync_make_calendar(
            $state['provider'],
            $connection_id,
            lang(array('string' => 'Workspace plans ({var:1})', 'vars' => HOSTNAME_SETTING)),
            lang('Your own plans from My calendar. Changes are made on the site.')
        );

        if (!$made['ok']) {
            $answer['error'] = $made['error'];

            return $answer;
        }

        $state['plans_calendar_id'] = $made['id'];
        $state['plans_written'] = array();
    }

    $plan = ws_calsync_plan($bodies, $state['plans_written'], $limit);
    $done = ws_calsync_write($state, $connection_id, $plan, $state['plans_calendar_id'], 'plans_written');

    $answer['written'] = $done['written'];
    $answer['deleted'] = $done['deleted'];
    $answer['error'] = $done['gone']
        ? lang('The Workspace plans calendar was deleted from your account. Switch writing your plans off and on again to make a new one.')
        : $done['error'];
    $answer['left'] = ($answer['error'] === '') ? (int) $plan['left'] : 0;

    // Switched off and emptied: the plans calendar goes too (best effort).
    if (!$state['plans'] && ($answer['error'] === '') && ($answer['left'] === 0) && !$state['plans_written']) {
        ws_calsync_call($state['provider'], $connection_id, 'DELETE', '/calendars/' . rawurlencode($state['plans_calendar_id']));
        $state['plans_calendar_id'] = '';
    }

    return $answer;
}

/**
 * Writes the person's tasks to their calendar: new and changed ones, and
 * deletes those no longer carried; then their own plans when they switched
 * that on; up to WS_CALSYNC_WRITES_MAX calls together.
 *
 * @param int $user_id
 * @return array ok, error, written, deleted, left, moved (tasks moved from the calendar)
 */
function ws_calsync_sync($user_id)
{
    $answer = array('ok' => false, 'error' => '', 'written' => 0, 'deleted' => 0, 'left' => 0, 'moved' => 0);
    $state = ws_calsync_state($user_id);

    if (!$state) {
        $answer['error'] = lang('Writing to your calendar is off.');

        return $answer;
    }

    $user = pg_load_user_row((int) $user_id);
    $viewer = is_array($user) ? ws_viewer($user) : array('member' => false);

    // An account that left the team stops being written; the calendar stays
    // in their account, theirs to delete.
    if (!$viewer['member'] || !ws_enabled()) {
        pg_connect_service_delete(ws_calsync_state_key($user_id));
        $answer['error'] = lang('This account is no longer in the workspace team.');

        return $answer;
    }

    $provider = $state['provider'];
    $account = ws_calsync_connection($user_id, $provider);

    if (!$account || ($account['status'] !== 'active') || !in_array($provider, ws_calsync_offered(), true)) {
        $state['error'] = lang('The account has to be connected again.');
        $state['synced_at'] = time();
        ws_calsync_state_save($user_id, $state);
        $answer['error'] = $state['error'];

        return $answer;
    }

    $state['connection_id'] = (int) $account['id'];

    $today = date('Y-m-d');
    $since = date('Y-m-d', strtotime($today . ' 12:00:00 -' . WS_CALENDAR_FEED_PAST_DAYS . ' days'));
    $until = date('Y-m-d', strtotime($today . ' 12:00:00 +' . WS_CALENDAR_FEED_AHEAD_DAYS . ' days'));
    $timezone = date_default_timezone_get();
    $error = '';
    $read = array('ok' => true, 'error' => '');
    $plan = array('put' => array(), 'delete' => array(), 'left' => 0);

    if (ws_calsync_tasks_on($state)) {
        $wanted = ws_calsync_bodies($viewer, $provider, $since, $until, $timezone);

        // What the person moved in the calendar comes in before anything is
        // written over it. A refused or skipped move is forgotten as written,
        // so the event is written back as the task stands.
        $read = ws_calsync_read($state, $account['id'], $since, $until, $timezone);
        $moved = 0;

        if ($read['ok']) {
            foreach (ws_calsync_moves($read['changes'], $wanted['bodies'], $state['written'], $wanted['tasks'], $provider, $timezone) as $id => $move) {
                if (ws_calsync_apply_move($viewer, $user, $move['task_id'], $move['when']) === 'kept') {
                    $moved++;
                } else {
                    unset($state['written'][$id]);
                }
            }
        }

        // A booking owns its time: a move made in the calendar is refused and
        // the event written back, with one bell line naming the booking.
        if ($read['ok']) {
            $booked = array_map(function ($booking) {
                return (int) $booking['id'];
            }, $wanted['bookings']);

            foreach (ws_calsync_moves($read['changes'], $wanted['bodies'], $state['written'], $booked, $provider, $timezone) as $id => $move) {
                unset($state['written'][$id]);
                pg_booking_calendar_refused($user_id, $wanted['bookings'][$id]);
            }
        }

        $answer['moved'] = $moved;

        if ($moved > 0) {
            $wanted = ws_calsync_bodies($viewer, $provider, $since, $until, $timezone);
        }

        $plan = ws_calsync_plan($wanted['bodies'], $state['written'], WS_CALSYNC_WRITES_MAX);
        $done = ws_calsync_write($state, $account['id'], $plan, $state['calendar_id'], 'written');
        $error = $done['error'];
        $answer['written'] = $done['written'];
        $answer['deleted'] = $done['deleted'];
    }

    $left = ($error === '') ? (int) $plan['left'] : 0;

    // The plans take the calls the tasks left over.
    if ($error === '') {
        $plans = ws_calsync_sync_plans($state, $viewer, $account['id'], WS_CALSYNC_WRITES_MAX - count($plan['put']) - count($plan['delete']), $since, $until, $timezone);
        $error = $plans['error'];
        $answer['written'] += $plans['written'];
        $answer['deleted'] += $plans['deleted'];
        $left += $plans['left'];
    }

    $state['left'] = ($error === '') ? $left : 0;

    // A read that failed is said on the card once the writes went well.
    if (($error === '') && !$read['ok']) {
        $error = $read['error'];
    }

    $state['synced_at'] = time();
    $state['error'] = $error;

    // Neither tasks nor plans are written any more: nothing to keep.
    if (!ws_calsync_state_clean($state)) {
        pg_connect_service_delete(ws_calsync_state_key($user_id));
    } else {
        ws_calsync_state_save($user_id, $state);
    }

    $answer['ok'] = ($error === '');
    $answer['error'] = $error;
    $answer['left'] = $state['left'];

    return $answer;
}

/**
 * Turns writing tasks off. The tasks calendar is deleted from the person's
 * account when it can be (best effort); the state goes with it, but for
 * the plans part when that is still written.
 *
 * @param int $user_id
 */
function ws_calsync_stop($user_id)
{
    $state = ws_calsync_state($user_id);
    $account = $state ? ws_calsync_connection($user_id, $state['provider']) : null;

    if ($account && ($account['status'] === 'active') && ws_calsync_tasks_on($state)) {
        ws_calsync_call($state['provider'], $account['id'], 'DELETE', '/calendars/' . rawurlencode($state['calendar_id']));
    }

    $state = ws_calsync_stop_tasks($state);

    if (!$state) {
        pg_connect_service_delete(ws_calsync_state_key($user_id));
    } else {
        ws_calsync_state_save($user_id, $state);
    }
}

/**
 * A state with the tasks part taken away: array() when no plans are
 * written either.
 *
 * @param array $state ws_calsync_state_clean()
 * @return array
 */
function ws_calsync_stop_tasks($state)
{
    if (!$state) {
        return array();
    }

    $state['calendar_id'] = '';
    $state['written'] = array();
    $state['read_token'] = '';
    $state['read_started'] = 0;
    $state['error'] = '';
    $state['remote'] = array_intersect_key($state['remote'], $state['plans_written']);

    return ws_calsync_state_clean($state);
}

/**
 * The scheduled job's share: the person written longest ago, once an hour at
 * most each (sooner while a run left calls for later).
 */
function ws_calsync_tick()
{
    if (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready()) {
        return;
    }

    $key = db_value(
        "SELECT external_id FROM connect_accounts
        WHERE provider = 'service' AND user_id = '0' AND external_id LIKE 'ws\\_calsync:%'
        ORDER BY last_used_timestamp ASC
        LIMIT 1");

    if (($key === null) || !preg_match('/^ws_calsync:([0-9]+)$/', (string) $key, $match)) {
        return;
    }

    $user_id = (int) $match[1];
    $state = ws_calsync_state($user_id);

    if (!$state) {
        // An entry no state can be read from would hold the queue: gone.
        pg_connect_service_delete(ws_calsync_state_key($user_id));

        return;
    }

    if (((int) $state['left'] === 0) && ($state['synced_at'] > time() - WS_CALSYNC_JOB_EVERY)) {
        return;
    }

    ws_calsync_sync($user_id);
}


/* ---------------------------------------------------------------------------
   The card on Calendar subscription (workspace_calendar_feed.php)
   --------------------------------------------------------------------------- */

/**
 * The card's posts (feed_action calsync_*).
 *
 * @param int      $user_id
 * @param string   $action
 * @param liveform $liveform
 * @return bool whether the action was the card's
 */
function ws_calsync_post($user_id, $action, $liveform)
{
    if (strpos($action, 'calsync_') !== 0) {
        return false;
    }

    $username = (string) ($_SESSION['sessionusername'] ?? '');

    if (($action === 'calsync_start') || ($action === 'calsync_sync')) {
        $first = !ws_calsync_tasks_on(ws_calsync_state($user_id));
        $done = ($action === 'calsync_start')
            ? ws_calsync_start($user_id, (string) ($_POST['calsync_provider'] ?? ''))
            : ws_calsync_sync($user_id);

        if ($first && ws_calsync_tasks_on(ws_calsync_state($user_id))) {
            log_activity(lang('turned on writing their workspace tasks to their own calendar'), $username);
        }

        if (!$done['ok']) {
            $liveform->add_error(h(lang(array('string' => 'Your calendar could not be written: {var:1}', 'vars' => $done['error']))));
        } else {
            $liveform->add_notice(lang('Your tasks were written to your calendar.'));
        }
    } elseif ($action === 'calsync_stop') {
        ws_calsync_stop($user_id);
        log_activity(lang('turned off writing their workspace tasks to their own calendar'), $username);
        $liveform->add_notice(lang('Writing to your calendar is off and its Workspace tasks calendar was removed.'));
    }

    return true;
}

/**
 * The card, empty when the site offers no provider.
 *
 * @param int $user_id
 * @return string
 */
function ws_calsync_card($user_id)
{
    $offered = ws_calsync_offered();

    if (!$offered) {
        return '';
    }

    $names = ws_calsync_provider_names();
    $state = ws_calsync_state($user_id);

    $form = function ($action, $classes, $icon, $label, $confirm = '', $provider = '') {
        // A form with a question is asked by the screen's script (ws-calsync-confirm).
        return '<form method="post" action="workspace_calendar_feed.php#ws-calendar-sync" class="d-inline disable_shortcut'
            . (($confirm !== '') ? ' ws-calsync-confirm" data-confirm="' . h($confirm) . '"' : '"') . '>'
            . get_token_field() . '<input type="hidden" name="feed_action" value="' . h($action) . '">'
            . (($provider !== '') ? '<input type="hidden" name="calsync_provider" value="' . h($provider) . '">' : '')
            . '<button type="submit" class="btn btn-sm ' . $classes . '"><i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . h($label) . '</button></form>';
    };

    $flash = isset($_GET['calsync_connect']) ? pg_connect_flash_take() : null;
    $body = $flash
        ? '<p class="small ' . (($flash['kind'] === 'error') ? 'fg-danger' : 'fg-success') . '" role="status">' . h($flash['text']) . '</p>'
        : '';

    if (ws_calsync_tasks_on($state)) {
        $provider = $state['provider'];
        $account = ws_calsync_connection($user_id, $provider);

        if ($state['error'] !== '') {
            $status = '<span class="fg-danger">' . h($state['error']) . '</span>';
        } elseif ($state['synced_at'] > 0) {
            $status = h(lang(array('string' => '{var:1} tasks · written {var:2}', 'vars' => array(count($state['written']), date('d.m.Y H:i', $state['synced_at'])))));
        } else {
            $status = h(lang('Not written yet.'));
        }

        $body .= '
                <p class="small mb-1"><i class="bi ' . h($names[$provider]['icon']) . ' me-1" aria-hidden="true"></i>' . h($names[$provider]['name'])
                    . ($account ? ' · ' . h(lang(array('string' => 'Connected as {var:1}.', 'vars' => $account['label']))) : '') . '</p>
                <p class="small fg-secondary">' . $status . '</p>
                <div class="d-flex flex-wrap gap-3">';

        if (!$account || ($account['status'] !== 'active')) {
            $body .= '
                    <form method="post" action="connect.php" class="d-inline disable_shortcut">
                        ' . pg_connect_start_fields($provider, WS_CALSYNC_PURPOSE, 'workspace_calendar_feed.php?calsync_connect=1#ws-calendar-sync', 'person', $account ? (int) $account['id'] : 0) . '
                        <button type="submit" class="btn btn-sm btn-outline theme-primary"><i class="bi ' . h($names[$provider]['icon']) . ' me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Connect your {var:1} account', 'vars' => $names[$provider]['account']))) . '</button>
                    </form>';
        } else {
            $body .= '
                    ' . $form('calsync_sync', 'btn-outline theme-secondary', 'bi-arrow-repeat', lang('Write now'));
        }

        $body .= '
                    ' . $form('calsync_stop', 'btn-outline theme-danger', 'bi-x-circle', lang('Stop writing'), lang('Your tasks will no longer be written and the Workspace tasks calendar will be removed from your account. Stop?')) . '
                </div>';
    } else {
        $body .= '
                <p class="small fg-secondary">' . h(lang('Connect your account and your tasks are written to a calendar of their own there, kept up to date every hour. Changes are made here.')) . '</p>
                <div class="d-flex flex-wrap gap-3">';

        // While only plans are written, tasks go to the same account.
        foreach (($state ? array_intersect($offered, array($state['provider'])) : $offered) as $provider) {
            $account = ws_calsync_connection($user_id, $provider);

            if ($account && ($account['status'] === 'active')) {
                $body .= '
                    ' . $form('calsync_start', 'btn-solid theme-primary', $names[$provider]['icon'], lang(array('string' => 'Write my tasks to {var:1}', 'vars' => $names[$provider]['name'])), '', $provider);
            } else {
                $body .= '
                    <form method="post" action="connect.php" class="d-inline disable_shortcut">
                        ' . pg_connect_start_fields($provider, WS_CALSYNC_PURPOSE, 'workspace_calendar_feed.php?calsync_connect=1#ws-calendar-sync', 'person', $account ? (int) $account['id'] : 0) . '
                        <button type="submit" class="btn btn-sm btn-outline theme-primary"><i class="bi ' . h($names[$provider]['icon']) . ' me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Connect your {var:1} account', 'vars' => $names[$provider]['account']))) . '</button>
                    </form>';
            }
        }

        $body .= '
                </div>';
    }

    return '
        <div class="card mb-5" id="ws-calendar-sync">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-calendar-check me-3" aria-hidden="true"></i>' . h(lang('Your tasks in your own calendar')) . '</h2>
                ' . $body . '
            </div>
        </div>';
}


/* ---------------------------------------------------------------------------
   The plans switch on My calendar (workspace_my_calendar.php)
   --------------------------------------------------------------------------- */

/**
 * The switch's post (my_calendar_action calsync_plans).
 *
 * @param int $user_id
 * @return bool whether the post was the switch's
 */
function ws_calsync_plans_post($user_id)
{
    if ((string) ($_POST['my_calendar_action'] ?? '') !== 'calsync_plans') {
        return false;
    }

    $on = !empty($_POST['plans_on']);
    $was = ws_calsync_state($user_id);
    $was = $was && $was['plans'];

    if ($on !== $was) {
        ws_calsync_plans_switch($user_id, $on);
        $now = ws_calsync_state($user_id);

        // Switching on without a connected account changes nothing.
        if (($now && $now['plans']) !== $on) {
            return true;
        }

        log_activity($on
            ? lang('turned on writing their own plans to their own calendar')
            : lang('turned off writing their own plans to their own calendar'), (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return true;
}

/**
 * The switch "Write my plans to my calendar", empty when the site offers no
 * provider.
 *
 * @param int $user_id
 * @return string
 */
function ws_calsync_plans_card($user_id)
{
    if (!ws_calsync_offered()) {
        return '';
    }

    $names = ws_calsync_provider_names();
    $state = ws_calsync_state($user_id);
    $on = $state && $state['plans'];
    $account = ws_calsync_plans_account($user_id, $state);
    $calendar = $account ? $names[$account['provider']]['name'] : '';
    $failed = $on && (!$account || ($state['error'] !== ''));
    $link = ' <a href="workspace_calendar_feed.php#ws-calendar-sync">' . h(lang('Calendar subscription')) . '</a>';
    $meta = '<div class="pg-text-meta" id="ws-calsync-plans-text">';

    if ($failed) {
        // The last run for this account did not write: said in the warning
        // tone, never an old "written" time.
        $meta .= '<span class="pg-pill pg-tone-amber me-2">' . h(lang('Last write failed')) . '</span>'
            . h(($state['error'] !== '') ? $state['error'] : lang('The account has to be connected again.')) . $link;
    } elseif (!$on && $state && ($state['plans_calendar_id'] !== '')) {
        // Switched off, the plans calendar still in the account until the next run.
        $meta .= h(lang(array('string' => 'Your plans calendar in {var:1} is removed at the next run; your plans here stay.', 'vars' => $calendar)));
    } elseif ($on && ($state['synced_at'] > 0)) {
        $meta .= h(lang(array('string' => '{var:1} · {var:2} plans · written {var:3}', 'vars' => array(
            $calendar, count($state['plans_written']), date('d.m.Y H:i', $state['synced_at']),
        ))));
    } elseif (!$account) {
        $meta .= h(lang('Connect your Google or Microsoft account under Calendar subscription first.')) . $link;
    } else {
        $meta .= h(lang(array('string' => 'Your own plans go to a calendar of their own in {var:1}: title, days and times only. Plans shared with you are never written.', 'vars' => $calendar)));
    }

    $title = $account
        ? lang(array('string' => 'Write my plans to {var:1}', 'vars' => $calendar))
        : lang('Write my plans to my Google or Outlook calendar');
    $meta .= '</div>';

    return '
        <form method="post" action="workspace_my_calendar.php" id="ws-calsync-plans" class="pg-card-inner ws-calsync-plans disable_shortcut">
            ' . get_token_field() . '
            <input type="hidden" name="my_calendar_action" value="calsync_plans">
            <div class="pg-card-inner-row">
                <span class="pg-icon-tile pg-icon-tile-lg pg-tone-violet" aria-hidden="true"><span class="bi bi-calendar-check"></span></span>
                <div class="ws-calsync-plans-text"><strong class="pg-list-row-title">' . h($title) . '</strong>' . $meta . '</div>
                <input type="checkbox" class="pg-switch" name="plans_on" value="1" id="ws-calsync-plans-on" aria-label="' . h($title) . '" aria-describedby="ws-calsync-plans-text"'
                    . ($on ? ' checked' : '') . ((!$account && !$on) ? ' disabled' : '') . '>
            </div>
        </form>
        <script>
            document.getElementById("ws-calsync-plans-on").addEventListener("change", function () {
                this.form.submit();
            });
        </script>';
}
