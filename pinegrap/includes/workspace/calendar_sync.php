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
 *               remote (id => provider's id), synced_at, error, left
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
    );
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
        return pg_connect_api((int) $connection_id, $method, WS_GRAPH_ME_API . $path, $options, array(WS_GRAPH_CAL_SCOPE));
    }

    return pg_connect_api((int) $connection_id, $method, WS_GCAL_API . $path, $options);
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
 * @return array ok, error, written, deleted, left
 */
function ws_calsync_sync($user_id)
{
    $answer = array('ok' => false, 'error' => '', 'written' => 0, 'deleted' => 0, 'left' => 0);
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
    $items = array_merge(
        ws_calendar_feed_task_items($viewer, false, $since, $until),
        ws_calendar_feed_copy_items($viewer, $today)
    );

    $timezone = date_default_timezone_get();
    $bodies = array();

    foreach (array_slice($items, 0, WS_CALENDAR_FEED_LIMIT) as $item) {
        $bodies[ws_calsync_event_id(HOSTNAME_SETTING, (string) $item['uid'])] = ($provider === 'microsoft')
            ? ws_calsync_graph_body($item)
            : ws_calsync_google_body($item, $timezone);
    }

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

    $state['synced_at'] = time();
    $state['error'] = $error;
    $state['left'] = ($error === '') ? (int) $plan['left'] : 0;
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
