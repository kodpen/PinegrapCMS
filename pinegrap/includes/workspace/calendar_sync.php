<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's tasks written to their own Google Calendar through
 * a connected account of theirs (purpose calendar_sync,
 * includes/connect/purposes/calendar_sync.php).
 *
 * The site makes one calendar of its own in the person's account (scope
 * calendar.app.created: it reaches only calendars the site made, never the
 * person's other calendars) and keeps there the same tasks the calendar
 * subscription carries (ws_calendar_feed_task_items() and the coming copies
 * of repeating tasks), titles, dates and a link only.
 *
 * Every event's id is derived from the site and the task (ws_gcal_event_id()),
 * so no table keeps which event belongs to which task: a write is a PUT to
 * that id, and a task no longer carried is a DELETE of it. What was written
 * last time (id => hash of the body) is kept with the calendar's id in a
 * sealed service entry per person (pg_connect_service_put(), key
 * ws_gcal:<user id>), so an unchanged task costs no call.
 *
 * The workspace's scheduled job writes for one person a run, the one written
 * longest ago, at most WS_GCAL_WRITES_MAX calls; the person's Write now button
 * does the same at once.
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

/** The most writes and deletions one run makes; the rest waits for the next. */
define('WS_GCAL_WRITES_MAX', 60);

/** How long the job leaves a person's calendar before writing it again, in seconds. */
define('WS_GCAL_JOB_EVERY', 3600);

/** The connected account's purpose. */
define('WS_GCAL_PURPOSE', 'calendar_sync');


/* ---------------------------------------------------------------------------
   Events (pure)
   --------------------------------------------------------------------------- */

/**
 * The Google event id of one item: lower-case hex is within the base32hex
 * letters Google accepts (a-v, 0-9, 5 to 1024 long), and the site's host
 * keeps two sites writing to one account apart.
 *
 * @param string $host
 * @param string $uid  the item's uid (ws-task-<id>, ws-copy-...)
 * @return string
 */
function ws_gcal_event_id($host, $uid)
{
    return 'pg' . sha1(strtolower((string) $host) . '|' . (string) $uid);
}

/**
 * The event resource of one calendar feed item (ws_calendar_feed_items()).
 *
 * @param array  $item
 * @param string $timezone the site's zone
 * @return array
 */
function ws_gcal_event_body($item, $timezone)
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
 * @param array $body
 * @return string
 */
function ws_gcal_body_hash($body)
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
function ws_gcal_plan($bodies, $written, $limit)
{
    $delete = array();
    $put = array();

    foreach (array_keys($written) as $id) {
        if (!isset($bodies[$id])) {
            $delete[] = $id;
        }
    }

    foreach ($bodies as $id => $body) {
        if (!isset($written[$id]) || ((string) $written[$id] !== ws_gcal_body_hash($body))) {
            $put[$id] = $body;
        }
    }

    $limit = max(0, (int) $limit);
    $total = count($delete) + count($put);
    $delete = array_slice($delete, 0, $limit);
    $put = array_slice($put, 0, $limit - count($delete), true);

    return array('put' => $put, 'delete' => $delete, 'left' => $total - count($delete) - count($put));
}


/* ---------------------------------------------------------------------------
   The person's account and state
   --------------------------------------------------------------------------- */

/**
 * Whether the site can offer it: the Google application is set up, tokens can
 * be sealed and the calendar subscription's tables are there.
 *
 * @return bool
 */
function ws_gcal_offered()
{
    return function_exists('pg_connect_schema_ready') && pg_connect_schema_ready()
        && pg_connect_can_seal() && pg_connect_app_ready(pg_connect_app('google'))
        && ws_calendar_feed_ready();
}

/**
 * The person's Google connection for calendar writing, or null.
 *
 * @param int $user_id
 * @return array|null pg_connect_account_public()
 */
function ws_gcal_connection($user_id)
{
    if (!function_exists('pg_connect_accounts') || ((int) $user_id <= 0)) {
        return null;
    }

    $accounts = pg_connect_accounts(array('provider' => 'google', 'user_id' => (int) $user_id, 'purpose' => WS_GCAL_PURPOSE));

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
function ws_gcal_state_key($user_id)
{
    return 'ws_gcal:' . (int) $user_id;
}

/**
 * What was written to the person's calendar, array() when writing is off.
 *
 * @param int $user_id
 * @return array connection_id, calendar_id, written (id => hash), synced_at,
 *               error, left
 */
function ws_gcal_state($user_id)
{
    $state = function_exists('pg_connect_service_get') ? pg_connect_service_get(ws_gcal_state_key($user_id)) : array();

    if (empty($state['calendar_id'])) {
        return array();
    }

    return array(
        'connection_id' => (int) ($state['connection_id'] ?? 0),
        'calendar_id'   => (string) $state['calendar_id'],
        'written'       => is_array($state['written'] ?? null) ? $state['written'] : array(),
        'synced_at'     => (int) ($state['synced_at'] ?? 0),
        'error'         => (string) ($state['error'] ?? ''),
        'left'          => (int) ($state['left'] ?? 0),
    );
}

/**
 * @param int   $user_id
 * @param array $state
 * @return bool
 */
function ws_gcal_state_save($user_id, $state)
{
    return pg_connect_service_put(ws_gcal_state_key($user_id), 'Google Calendar (Workspace tasks)', $state);
}

/**
 * The error text of a failed Google call.
 *
 * @param array $result pg_connect_api()
 * @return string
 */
function ws_gcal_error($result)
{
    if (isset($result['data']['error']['message']) && is_string($result['data']['error']['message'])) {
        return pg_connect_clip('HTTP ' . (int) $result['status'] . ': ' . $result['data']['error']['message']);
    }

    return pg_connect_clip(((string) $result['error'] !== '') ? (string) $result['error'] : 'HTTP ' . (int) $result['status']);
}

/**
 * One call to the Calendar API.
 *
 * @param int        $connection_id
 * @param string     $method
 * @param string     $path after the API's base
 * @param array|null $body
 * @return array pg_connect_api()
 */
function ws_gcal_call($connection_id, $method, $path, $body = null)
{
    $options = array('headers' => array('Accept: application/json'), 'timeout' => 30);

    if ($body !== null) {
        $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $options['headers'][] = 'Content-Type: application/json';
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
 * @param int $user_id
 * @return array ok, error
 */
function ws_gcal_start($user_id)
{
    $account = ws_gcal_offered() ? ws_gcal_connection($user_id) : null;

    if (!$account || ($account['status'] !== 'active')) {
        return array('ok' => false, 'error' => lang('Connect your Google account first.'));
    }

    if (ws_gcal_state($user_id)) {
        return ws_gcal_sync($user_id);
    }

    $made = ws_gcal_call($account['id'], 'POST', '/calendars', array(
        'summary'     => lang(array('string' => 'Workspace tasks ({var:1})', 'vars' => HOSTNAME_SETTING)),
        'description' => lang('Your tasks from the workspace. Changes are made on the site.'),
        'timeZone'    => date_default_timezone_get(),
    ));

    if (!$made['ok'] || empty($made['data']['id'])) {
        return array('ok' => false, 'error' => ws_gcal_error($made));
    }

    $saved = ws_gcal_state_save($user_id, array(
        'connection_id' => (int) $account['id'],
        'calendar_id'   => (string) $made['data']['id'],
        'written'       => array(),
        'synced_at'     => 0,
        'error'         => '',
        'left'          => 0,
    ));

    if (!$saved) {
        return array('ok' => false, 'error' => lang('This site has no encryption key, so it cannot keep the connection.'));
    }

    return ws_gcal_sync($user_id);
}

/**
 * Writes the person's tasks to their calendar: new and changed ones, and
 * deletes those no longer carried, up to WS_GCAL_WRITES_MAX calls.
 *
 * @param int $user_id
 * @return array ok, error, written, deleted, left
 */
function ws_gcal_sync($user_id)
{
    $answer = array('ok' => false, 'error' => '', 'written' => 0, 'deleted' => 0, 'left' => 0);
    $state = ws_gcal_state($user_id);

    if (!$state) {
        $answer['error'] = lang('Writing to Google Calendar is off.');

        return $answer;
    }

    $user = pg_load_user_row((int) $user_id);
    $viewer = is_array($user) ? ws_viewer($user) : array('member' => false);

    // An account that left the team stops being written; the calendar stays
    // in their Google account, theirs to delete.
    if (!$viewer['member'] || !ws_enabled()) {
        pg_connect_service_delete(ws_gcal_state_key($user_id));
        $answer['error'] = lang('This account is no longer in the workspace team.');

        return $answer;
    }

    $account = ws_gcal_connection($user_id);

    if (!$account || ($account['status'] !== 'active') || !ws_gcal_offered()) {
        $state['error'] = lang('The Google account has to be connected again.');
        $state['synced_at'] = time();
        ws_gcal_state_save($user_id, $state);
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
        $bodies[ws_gcal_event_id(HOSTNAME_SETTING, (string) $item['uid'])] = ws_gcal_event_body($item, $timezone);
    }

    $plan = ws_gcal_plan($bodies, $state['written'], WS_GCAL_WRITES_MAX);
    $events = '/calendars/' . rawurlencode($state['calendar_id']) . '/events';
    $error = '';

    foreach ($plan['delete'] as $id) {
        $done = ws_gcal_call($account['id'], 'DELETE', $events . '/' . rawurlencode((string) $id));

        // Already gone (404) or already deleted (410) is what was wanted.
        if ($done['ok'] || in_array((int) $done['status'], array(404, 410), true)) {
            unset($state['written'][$id]);
            $answer['deleted']++;
        } else {
            $error = ws_gcal_error($done);
            break;
        }
    }

    if ($error === '') {
        foreach ($plan['put'] as $id => $body) {
            $done = ws_gcal_call($account['id'], 'PUT', $events . '/' . rawurlencode((string) $id), $body);

            // Not there yet: made with the same id.
            if ((int) $done['status'] === 404) {
                $done = ws_gcal_call($account['id'], 'POST', $events, array('id' => (string) $id) + $body);
            }

            if (!$done['ok']) {
                // The person deleted the site's calendar: writing stops
                // until they turn it on again, which makes a new one.
                $error = ((int) $done['status'] === 404)
                    ? lang('The Workspace tasks calendar was deleted in Google Calendar. Turn writing on again to make a new one.')
                    : ws_gcal_error($done);
                break;
            }

            $state['written'][$id] = ws_gcal_body_hash($body);
            $answer['written']++;
        }
    }

    $state['synced_at'] = time();
    $state['error'] = $error;
    $state['left'] = ($error === '') ? (int) $plan['left'] : 0;
    ws_gcal_state_save($user_id, $state);

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
function ws_gcal_stop($user_id)
{
    $state = ws_gcal_state($user_id);
    $account = ws_gcal_connection($user_id);

    if ($state && $account && ($account['status'] === 'active')) {
        ws_gcal_call($account['id'], 'DELETE', '/calendars/' . rawurlencode($state['calendar_id']));
    }

    pg_connect_service_delete(ws_gcal_state_key($user_id));
}

/**
 * The scheduled job's share: the person written longest ago, once an hour at
 * most each (sooner while a run left calls for later).
 */
function ws_gcal_tick()
{
    if (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready()) {
        return;
    }

    $key = db_value(
        "SELECT external_id FROM connect_accounts
        WHERE provider = 'service' AND user_id = '0' AND external_id LIKE 'ws\\_gcal:%'
        ORDER BY last_used_timestamp ASC
        LIMIT 1");

    if (($key === null) || !preg_match('/^ws_gcal:([0-9]+)$/', (string) $key, $match)) {
        return;
    }

    $user_id = (int) $match[1];
    $state = ws_gcal_state($user_id);

    if (!$state) {
        return;
    }

    if (((int) $state['left'] === 0) && ($state['synced_at'] > time() - WS_GCAL_JOB_EVERY)) {
        return;
    }

    ws_gcal_sync($user_id);
}


/* ---------------------------------------------------------------------------
   The card on Calendar subscription (workspace_calendar_feed.php)
   --------------------------------------------------------------------------- */

/**
 * The card's posts (feed_action gcal_*).
 *
 * @param int      $user_id
 * @param string   $action
 * @param liveform $liveform
 * @return bool whether the action was the card's
 */
function ws_gcal_post($user_id, $action, $liveform)
{
    if (strpos($action, 'gcal_') !== 0) {
        return false;
    }

    $username = (string) ($_SESSION['sessionusername'] ?? '');

    if (($action === 'gcal_start') || ($action === 'gcal_sync')) {
        $first = !ws_gcal_state($user_id);
        $done = ($action === 'gcal_start') ? ws_gcal_start($user_id) : ws_gcal_sync($user_id);

        if ($first && ws_gcal_state($user_id)) {
            log_activity(lang('turned on writing their workspace tasks to Google Calendar'), $username);
        }

        if (!$done['ok']) {
            $liveform->add_error(h(lang(array('string' => 'Google Calendar could not be written: {var:1}', 'vars' => $done['error']))));
        } else {
            $liveform->add_notice(lang('Your tasks were written to Google Calendar.'));
        }
    } elseif ($action === 'gcal_stop') {
        ws_gcal_stop($user_id);
        log_activity(lang('turned off writing their workspace tasks to Google Calendar'), $username);
        $liveform->add_notice(lang('Writing to Google Calendar is off and its Workspace tasks calendar was removed.'));
    }

    return true;
}

/**
 * The card, empty when the site does not offer it.
 *
 * @param int $user_id
 * @return string
 */
function ws_gcal_card($user_id)
{
    if (!ws_gcal_offered()) {
        return '';
    }

    $account = ws_gcal_connection($user_id);
    $state = ws_gcal_state($user_id);

    $form = function ($action, $classes, $icon, $label, $confirm = '') {
        // A form with a question is asked by the screen's script (ws-gcal-confirm).
        return '<form method="post" action="workspace_calendar_feed.php#ws-google-calendar" class="d-inline disable_shortcut'
            . (($confirm !== '') ? ' ws-gcal-confirm" data-confirm="' . h($confirm) . '"' : '"') . '>'
            . get_token_field() . '<input type="hidden" name="feed_action" value="' . h($action) . '">'
            . '<button type="submit" class="btn btn-sm ' . $classes . '"><i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . h($label) . '</button></form>';
    };

    $flash = isset($_GET['gcal_connect']) ? pg_connect_flash_take() : null;
    $body = $flash
        ? '<p class="small ' . (($flash['kind'] === 'error') ? 'fg-danger' : 'fg-success') . '" role="status">' . h($flash['text']) . '</p>'
        : '';

    if (!$account || ($account['status'] !== 'active')) {
        $body .= '
                <p class="small fg-secondary">' . h($account
                    ? lang('Your Google account has to be connected again before your tasks can be written.')
                    : lang('Connect your Google account and your tasks are written to a calendar of their own there, kept up to date every hour. The site reaches only that calendar.')) . '</p>
                <form method="post" action="connect.php" class="disable_shortcut">
                    ' . pg_connect_start_fields('google', WS_GCAL_PURPOSE, 'workspace_calendar_feed.php?gcal_connect=1#ws-google-calendar', 'person', $account ? (int) $account['id'] : 0) . '
                    <button type="submit" class="btn btn-sm btn-outline theme-primary"><i class="bi bi-google me-1" aria-hidden="true"></i>' . h(lang('Connect your Google account')) . '</button>
                </form>';
    } elseif (!$state) {
        $body .= '
                <p class="small fg-secondary">' . h(lang(array('string' => 'Connected as {var:1}. Turn writing on to make a Workspace tasks calendar in this account and keep your tasks in it.', 'vars' => $account['label']))) . '</p>
                ' . $form('gcal_start', 'btn-solid theme-primary', 'bi-calendar-plus', lang('Write my tasks to Google Calendar'));
    } else {
        if ($state['error'] !== '') {
            $status = '<span class="fg-danger">' . h($state['error']) . '</span>';
        } elseif ($state['synced_at'] > 0) {
            $status = h(lang(array('string' => '{var:1} tasks · written {var:2}', 'vars' => array(count($state['written']), date('d.m.Y H:i', $state['synced_at'])))));
        } else {
            $status = h(lang('Not written yet.'));
        }

        $body .= '
                <p class="small mb-1">' . h(lang(array('string' => 'Connected as {var:1}.', 'vars' => $account['label']))) . '</p>
                <p class="small fg-secondary">' . $status . '</p>
                <div class="d-flex flex-wrap gap-3">
                    ' . $form('gcal_sync', 'btn-outline theme-secondary', 'bi-arrow-repeat', lang('Write now')) . '
                    ' . $form('gcal_stop', 'btn-outline theme-danger', 'bi-x-circle', lang('Stop writing'), lang('Your tasks will no longer be written and the Workspace tasks calendar will be removed from your Google account. Stop?')) . '
                </div>';
    }

    return '
        <div class="card mb-5" id="ws-google-calendar">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-google me-3" aria-hidden="true"></i>' . h(lang('Your tasks in Google Calendar')) . '</h2>
                ' . $body . '
            </div>
        </div>';
}
