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
 * (ws_calendar_feed_task_items() and the coming copies of repeating tasks),
 * titles, dates and a link only. Google's scope calendar.app.created reaches
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
 * A stored state in its known shape, array() when writing is off.
 *
 * @param array $state as unsealed
 * @return array provider, connection_id, calendar_id, written (id => hash),
 *               remote (id => provider's id), synced_at, error, left,
 *               read_token (Google syncToken or Graph deltaLink), read_started
 */
function ws_calsync_state_clean($state)
{
    $provider = (string) ($state['provider'] ?? 'google');

    if (empty($state['calendar_id']) || !isset(ws_calsync_provider_names()[$provider])) {
        return array();
    }

    $remote = array();

    foreach ((is_array($state['remote'] ?? null) ? $state['remote'] : array()) as $id => $value) {
        if (is_string($value) && ($value !== '')) {
            $remote[(string) $id] = $value;
        }
    }

    return array(
        'provider'      => $provider,
        'connection_id' => (int) ($state['connection_id'] ?? 0),
        'calendar_id'   => (string) $state['calendar_id'],
        'written'       => is_array($state['written'] ?? null) ? $state['written'] : array(),
        'remote'        => $remote,
        'synced_at'     => (int) ($state['synced_at'] ?? 0),
        'error'         => (string) ($state['error'] ?? ''),
        'left'          => (int) ($state['left'] ?? 0),
        'read_token'    => is_string($state['read_token'] ?? null) ? $state['read_token'] : '',
        'read_started'  => (int) ($state['read_started'] ?? 0),
    );
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
 * @param array  $tasks    derived id => task id, the tasks among the bodies
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
 * What the person's calendar should carry, as event bodies, and which of
 * them are tasks.
 *
 * @param array  $viewer
 * @param string $provider
 * @param string $since    Y-m-d
 * @param string $until    Y-m-d
 * @param string $timezone
 * @return array bodies (derived id => body), tasks (derived id => task id)
 */
function ws_calsync_bodies($viewer, $provider, $since, $until, $timezone)
{
    $items = array_merge(
        ws_calendar_feed_task_items($viewer, false, $since, $until),
        ws_calendar_feed_copy_items($viewer, date('Y-m-d'))
    );

    $bodies = array();
    $tasks = array();

    foreach (array_slice($items, 0, WS_CALENDAR_FEED_LIMIT) as $item) {
        $id = ws_calsync_event_id(HOSTNAME_SETTING, (string) $item['uid']);
        $bodies[$id] = ($provider === 'microsoft')
            ? ws_calsync_graph_body($item)
            : ws_calsync_google_body($item, $timezone);

        if (preg_match('/^ws-task-([0-9]+)$/', (string) $item['uid'], $match)) {
            $tasks[$id] = (int) $match[1];
        }
    }

    return array('bodies' => $bodies, 'tasks' => $tasks);
}


/* ---------------------------------------------------------------------------
   Writing
   --------------------------------------------------------------------------- */

/**
 * Turns writing on: makes the site's calendar in the person's account and
 * writes the tasks into it.
 *
 * @param int    $user_id
 * @param string $provider
 * @return array ok, error
 */
function ws_calsync_start($user_id, $provider)
{
    $account = in_array($provider, ws_calsync_offered(), true) ? ws_calsync_connection($user_id, $provider) : null;

    if (!$account || ($account['status'] !== 'active')) {
        return array('ok' => false, 'error' => lang('Connect your account first.'));
    }

    if (ws_calsync_state($user_id)) {
        return ws_calsync_sync($user_id);
    }

    $name = lang(array('string' => 'Workspace tasks ({var:1})', 'vars' => HOSTNAME_SETTING));

    $made = ($provider === 'microsoft')
        ? ws_calsync_call($provider, $account['id'], 'POST', '/calendars', array('name' => $name))
        : ws_calsync_call($provider, $account['id'], 'POST', '/calendars', array(
            'summary'     => $name,
            'description' => lang('Your tasks from the workspace. Changes are made on the site.'),
            'timeZone'    => date_default_timezone_get(),
        ));

    if (!$made['ok'] || empty($made['data']['id']) || !is_string($made['data']['id'])) {
        return array('ok' => false, 'error' => ws_calsync_error($made));
    }

    $saved = ws_calsync_state_save($user_id, array(
        'provider'      => $provider,
        'connection_id' => (int) $account['id'],
        'calendar_id'   => $made['data']['id'],
        'written'       => array(),
        'remote'        => array(),
        'synced_at'     => 0,
        'error'         => '',
        'left'          => 0,
    ));

    if (!$saved) {
        return array('ok' => false, 'error' => lang('This site has no encryption key, so it cannot keep the connection.'));
    }

    return ws_calsync_sync($user_id);
}

/**
 * Deletes one written event.
 *
 * @param array  $state
 * @param int    $connection_id
 * @param string $id the derived id
 * @return array ok, error
 */
function ws_calsync_delete_event(&$state, $connection_id, $id)
{
    if ($state['provider'] === 'microsoft') {
        // Never made (a create that failed): nothing to delete.
        if (!isset($state['remote'][$id])) {
            return array('ok' => true, 'error' => '');
        }

        $done = ws_calsync_call('microsoft', $connection_id, 'DELETE', '/events/' . rawurlencode($state['remote'][$id]));
    } else {
        $done = ws_calsync_call('google', $connection_id, 'DELETE', '/calendars/' . rawurlencode($state['calendar_id']) . '/events/' . rawurlencode((string) $id));
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
 * @param array  $state
 * @param int    $connection_id
 * @param string $id   the derived id
 * @param array  $body
 * @return array ok, error
 */
function ws_calsync_put_event(&$state, $connection_id, $id, $body)
{
    $provider = $state['provider'];
    $done = array('ok' => false, 'status' => 404);

    if ($provider === 'microsoft') {
        $events = '/calendars/' . rawurlencode($state['calendar_id']) . '/events';

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
        $events = '/calendars/' . rawurlencode($state['calendar_id']) . '/events';
        $done = ws_calsync_call($provider, $connection_id, 'PUT', $events . '/' . rawurlencode((string) $id), $body);

        // Not there yet: made with the same id.
        if ((int) $done['status'] === 404) {
            $done = ws_calsync_call($provider, $connection_id, 'POST', $events, array('id' => (string) $id) + $body);
        }
    }

    if ($done['ok']) {
        return array('ok' => true, 'error' => '');
    }

    // A create answered 404: the person deleted the site's calendar, and
    // writing stops until they turn it on again, which makes a new one.
    return array('ok' => false, 'error' => ((int) $done['status'] === 404)
        ? lang('The Workspace tasks calendar was deleted from your account. Turn writing on again to make a new one.')
        : ws_calsync_error($done));
}

/**
 * Writes the person's tasks to their calendar: new and changed ones, and
 * deletes those no longer carried, up to WS_CALSYNC_WRITES_MAX calls.
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
    $wanted = ws_calsync_bodies($viewer, $provider, $since, $until, $timezone);

    // What the person moved in the calendar comes in before anything is
    // written over it. A refused or skipped move is forgotten as written, so
    // the event is written back as the task stands.
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

    $answer['moved'] = $moved;

    if ($moved > 0) {
        $wanted = ws_calsync_bodies($viewer, $provider, $since, $until, $timezone);
    }

    $bodies = $wanted['bodies'];

    $plan = ws_calsync_plan($bodies, $state['written'], WS_CALSYNC_WRITES_MAX);
    $error = '';

    foreach ($plan['delete'] as $id) {
        $done = ws_calsync_delete_event($state, $account['id'], (string) $id);

        if (!$done['ok']) {
            $error = $done['error'];
            break;
        }

        unset($state['written'][$id]);
        $answer['deleted']++;
    }

    if ($error === '') {
        foreach ($plan['put'] as $id => $body) {
            $done = ws_calsync_put_event($state, $account['id'], (string) $id, $body);

            if (!$done['ok']) {
                $error = $done['error'];
                break;
            }

            $state['written'][$id] = ws_calsync_body_hash($body);
            $answer['written']++;
        }
    }

    $state['left'] = ($error === '') ? (int) $plan['left'] : 0;

    // A read that failed is said on the card once the writes went well.
    if (($error === '') && !$read['ok']) {
        $error = $read['error'];
    }

    $state['synced_at'] = time();
    $state['error'] = $error;
    ws_calsync_state_save($user_id, $state);

    $answer['ok'] = ($error === '');
    $answer['error'] = $error;
    $answer['left'] = $state['left'];

    return $answer;
}

/**
 * Turns writing off. The site's calendar is deleted from the person's
 * account when it can be (best effort); the state goes either way.
 *
 * @param int $user_id
 */
function ws_calsync_stop($user_id)
{
    $state = ws_calsync_state($user_id);
    $account = $state ? ws_calsync_connection($user_id, $state['provider']) : null;

    if ($account && ($account['status'] === 'active')) {
        ws_calsync_call($state['provider'], $account['id'], 'DELETE', '/calendars/' . rawurlencode($state['calendar_id']));
    }

    pg_connect_service_delete(ws_calsync_state_key($user_id));
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
        $first = !ws_calsync_state($user_id);
        $done = ($action === 'calsync_start')
            ? ws_calsync_start($user_id, (string) ($_POST['calsync_provider'] ?? ''))
            : ws_calsync_sync($user_id);

        if ($first && ws_calsync_state($user_id)) {
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

    if ($state) {
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

        foreach ($offered as $provider) {
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
