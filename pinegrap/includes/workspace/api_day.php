<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - plan items, the search box and the owner's day in the external
 * API.
 *
 * A plan item (schedule.php) is written through ws_event_save(), the function
 * the calendar's drawer calls, so who may plan for whom is decided there.
 *
 * The search asks ws_palette_search() (palette.php), the search box of the
 * workspace screens, and narrows it to what the application holds scopes for:
 * the tasks with tasks:read, each kind of panel record with the scope that
 * reads that kind.
 *
 * The owner's day gathers what the overview (home.php) and the dashboard
 * greeting (briefing.php) already read for one person.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Plan items (schedule.php)
   --------------------------------------------------------------------------- */

/**
 * One plan item as the API shows it.
 *
 * @param array $viewer
 * @param array $event ws_events row with people (user ids)
 * @return array
 */
function ws_api_plan_item_present($viewer, $event)
{
    $all_day = ((int) $event['all_day'] === 1);

    return array(
        'id'            => (int) $event['id'],
        'kind'          => (string) $event['kind'],
        'title'         => (string) $event['title'],
        'note'          => (string) $event['note'],
        'scope'         => (string) $event['scope'],
        'department_id' => ($event['scope'] === 'department') ? (int) $event['department_id'] : null,
        'people'        => array_values(array_map('intval', (array) ($event['people'] ?? array()))),
        'all_day'       => $all_day,
        'start_date'    => date('Y-m-d', (int) $event['starts_at']),
        'end_date'      => date('Y-m-d', (int) $event['ends_at']),
        'start_time'    => $all_day ? null : date('H:i', (int) $event['starts_at']),
        'end_time'      => $all_day ? null : date('H:i', (int) $event['ends_at']),
        'starts_at'     => api_time($event['starts_at']),
        'ends_at'       => api_time($event['ends_at']),
        'yearly'        => !empty($event['yearly']),
        'from_calendar' => ((int) ($event['feed_id'] ?? 0) > 0),
        'meeting_url'   => (string) ($event['meeting_url'] ?? ''),
        'created_by'    => (int) $event['created_by'],
        'can_edit'      => ws_can_edit_event($viewer, $event),
        'created_at'    => api_time($event['created_at']),
    );
}

// What ws_api_plan_item_present() returns.
function ws_api_plan_item_schema()
{
    return array(
        'id'            => 'integer',
        'kind'          => 'string',
        'title'         => 'string',
        'note'          => 'string',
        'scope'         => 'string',
        'department_id' => 'integer?',
        'people'        => array('integer'),
        'all_day'       => 'boolean',
        'start_date'    => 'string',
        'end_date'      => 'string',
        'start_time'    => 'string?',
        'end_time'      => 'string?',
        'starts_at'     => 'string?',
        'ends_at'       => 'string?',
        'yearly'        => 'boolean',
        'from_calendar' => 'boolean',
        'meeting_url'   => 'string',
        'created_by'    => 'integer',
        'can_edit'      => 'boolean',
        'created_at'    => 'string?',
    );
}

/**
 * A plan item with its people, or a 404. Every member of the team sees every
 * item, as on the calendar.
 *
 * @param int $id
 * @return array
 */
function ws_api_plan_item_or_404($id)
{
    $event = db_item("SELECT * FROM ws_events WHERE id = '" . (int) $id . "'");

    if (!is_array($event)) {
        api_fail_not_found(lang('Plan item'));
    }

    $event['people'] = array_map('intval', (array) db_values("SELECT user_id FROM ws_event_people WHERE event_id = '" . (int) $event['id'] . "'"));

    return $event;
}

/**
 * A day of a request as Y-m-d, or a 422 naming the field.
 *
 * @param mixed  $value
 * @param string $field
 * @return string
 */
function ws_api_plan_day($value, $field)
{
    $day = ws_date_or_null($value);

    if (!$day) {
        api_fail_validation(lang(array('string' => '{var:1} must be a date, in ISO-8601 or YYYY-MM-DD form.', 'vars' => $field)), $field);
    }

    return $day;
}

/**
 * A time of a request as H:i, or a 422 naming the field.
 *
 * @param mixed  $value
 * @param string $field
 * @return string
 */
function ws_api_plan_time($value, $field)
{
    $value = trim((string) $value);

    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
        api_fail_validation(lang('Write the time as hour:minute.'), $field);
    }

    return $value;
}

function ws_api_plan_items_list($params)
{
    $viewer = ws_api_viewer();
    $from = (($params['from'] ?? '') !== '') ? ws_api_plan_day($params['from'], 'from') : date('Y-m-d');
    $to = date('Y-m-d', strtotime($from . ' +' . (max(1, (int) ($params['days'] ?? 14)) - 1) . ' days'));
    $user_id = (int) ($params['user_id'] ?? 0);
    $departments = ($user_id > 0) ? ws_user_department_ids($user_id) : array();
    $kind = (string) ($params['kind'] ?? '');
    $out = array();

    foreach (ws_events_between($from, $to) as $event) {
        if ((($kind !== '') && ($event['kind'] !== $kind)) || (($user_id > 0) && !ws_event_applies($event, $user_id, $departments))) {
            continue;
        }

        $out[] = ws_api_plan_item_present($viewer, $event);
    }

    api_ok_list($out, count($out));
}

function ws_api_plan_items_get($params)
{
    $viewer = ws_api_viewer();

    api_ok(ws_api_plan_item_present($viewer, ws_api_plan_item_or_404($params['id'])));
}

/**
 * The whole item ws_event_save() takes, from the item as it is (none for a
 * new one) with the fields sent over it.
 *
 * @param array      $params
 * @param array|null $current ws_api_plan_item_or_404()
 * @return array
 */
function ws_api_plan_item_input($params, $current = null)
{
    $data = array(
        'id' => 0, 'kind' => 'meeting', 'scope' => 'people', 'department_id' => 0, 'people' => array(),
        'title' => '', 'note' => '', 'start' => '', 'end' => '', 'start_time' => '', 'end_time' => '', 'all_day' => 0,
    );

    if ($current !== null) {
        $all_day = ((int) $current['all_day'] === 1);

        $data = array(
            'id'            => (int) $current['id'],
            'kind'          => (string) $current['kind'],
            'scope'         => (string) $current['scope'],
            'department_id' => (int) $current['department_id'],
            'people'        => $current['people'],
            'title'         => (string) $current['title'],
            'note'          => (string) $current['note'],
            'start'         => date('Y-m-d', (int) $current['starts_at']),
            'end'           => date('Y-m-d', (int) $current['ends_at']),
            'start_time'    => $all_day ? '' : date('H:i', (int) $current['starts_at']),
            'end_time'      => $all_day ? '' : date('H:i', (int) $current['ends_at']),
            'all_day'       => $all_day ? 1 : 0,
        );
    }

    foreach (array('kind', 'scope', 'title', 'note') as $field) {
        if (array_key_exists($field, $params)) {
            $data[$field] = (string) $params[$field];
        }
    }

    if (array_key_exists('start_date', $params)) {
        $start = ws_api_plan_day($params['start_date'], 'start_date');

        // Moved without a new end, an item keeps its length in days.
        if (($current !== null) && !array_key_exists('end_date', $params)) {
            $length = (int) round((strtotime($data['end'] . ' 12:00:00') - strtotime($data['start'] . ' 12:00:00')) / 86400);
            $data['end'] = date('Y-m-d', strtotime($start . ' 12:00:00 +' . max(0, $length) . ' days'));
        }

        $data['start'] = $start;
    }

    if (array_key_exists('end_date', $params)) {
        $data['end'] = ((string) $params['end_date'] === '') ? $data['start'] : ws_api_plan_day($params['end_date'], 'end_date');
    }

    if (array_key_exists('all_day', $params)) {
        $data['all_day'] = !empty($params['all_day']) ? 1 : 0;
    }

    foreach (array('start_time', 'end_time') as $field) {
        if (array_key_exists($field, $params)) {
            $data[$field] = ws_api_plan_time($params[$field], $field);
        }
    }

    if (array_key_exists('department_id', $params)) {
        $data['department_id'] = (int) $params['department_id'];
    }

    if (array_key_exists('people', $params)) {
        $data['people'] = ws_api_user_ids($params['people'], 'people');
    }

    // The video call: kept as it is unless sent.
    if (array_key_exists('meeting_url', $params)) {
        if (ws_meeting_url_clean($params['meeting_url']) === false) {
            api_fail_validation(lang('The meeting link is not a web address.'), 'meeting_url');
        }

        $data['meeting_url'] = (string) $params['meeting_url'];
    }

    if (($data['scope'] === 'department') && (((int) $data['department_id'] <= 0) || !ws_department((int) $data['department_id']))) {
        api_fail_validation(lang('That department could not be found.'), 'department_id');
    }

    return $data;
}

/**
 * A plan item write that was refused, as the API answers it: refused dates
 * are the request's; anything else is a right the owner does not hold.
 *
 * @param array $result ws_event_save() or ws_event_save_check()
 */
function ws_api_plan_item_refused($result)
{
    $fields = array('start' => 'start_date', 'end' => 'end_date');

    if (isset($fields[$result['field']])) {
        api_fail_validation($result['error'], $fields[$result['field']]);
    }

    api_fail(403, 'forbidden', $result['error']);
}

/**
 * What a rehearsed plan item write would write (ws_event_save_check()).
 *
 * @param array $checked
 * @return array
 */
function ws_api_plan_item_would($checked)
{
    $item = $checked['item'];

    return array(
        'id'        => ((int) $checked['event_id'] > 0) ? (int) $checked['event_id'] : null,
        'kind'      => (string) $item['kind'],
        'scope'     => (string) $item['scope'],
        'title'     => (string) $item['title'],
        'starts_at' => api_time($item['starts_at']),
        'ends_at'   => api_time($item['ends_at']),
        'all_day'   => (bool) $item['all_day'],
        'people'    => array_values(array_map('intval', $item['people'])),
    );
}

function ws_api_plan_items_create($params)
{
    $viewer = ws_api_viewer();
    $data = ws_api_plan_item_input($params);

    // The checks ws_event_save() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_event_save_check($viewer, $data);

    if (!$checked['ok']) {
        ws_api_plan_item_refused($checked);
    }

    api_dry_run_stop('created', 'workspace_plan_item', ws_api_plan_item_would($checked));

    $result = ws_event_save($viewer, $data);

    if (!$result['ok']) {
        ws_api_plan_item_refused($result);
    }

    api_ok(ws_api_plan_item_present($viewer, ws_api_plan_item_or_404($result['event_id'])), 201);
}

function ws_api_plan_items_update($params)
{
    $viewer = ws_api_viewer();
    $event = ws_api_plan_item_or_404($params['id']);

    if (!ws_can_edit_event($viewer, $event)) {
        api_fail(403, 'forbidden', lang('You cannot change this item.'));
    }

    $data = ws_api_plan_item_input($params, $event);
    $checked = ws_event_save_check($viewer, $data);

    if (!$checked['ok']) {
        ws_api_plan_item_refused($checked);
    }

    api_dry_run_stop('updated', 'workspace_plan_item', ws_api_plan_item_would($checked));

    $result = ws_event_save($viewer, $data);

    if (!$result['ok']) {
        ws_api_plan_item_refused($result);
    }

    api_ok(ws_api_plan_item_present($viewer, ws_api_plan_item_or_404($event['id'])));
}

function ws_api_plan_items_delete($params)
{
    $viewer = ws_api_viewer();
    $event = ws_api_plan_item_or_404($params['id']);

    if (!ws_can_edit_event($viewer, $event)) {
        api_fail(403, 'forbidden', lang('You cannot change this item.'));
    }

    api_dry_run_stop('deleted', 'workspace_plan_item', array('id' => (int) $event['id']));

    $result = ws_event_delete($viewer, $event['id']);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(array('id' => (int) $event['id'], 'deleted' => true));
}

/* ---------------------------------------------------------------------------
   The search box (palette.php)
   --------------------------------------------------------------------------- */

/**
 * The scope that reads each kind of panel record the search may find. A kind
 * no scope reads (user accounts) is not searched for an application.
 *
 * @return array kind => scope
 */
function ws_api_search_record_scopes()
{
    return array(
        'order'          => 'orders:read',
        'product'        => 'products:read',
        'product_group'  => 'products:read',
        'offer'          => 'offers:read',
        'contact'        => 'customers:read',
        'erp_account'    => 'erp:read',
        'invoice'        => 'erp:read',
        'waybill'        => 'erp:read',
        'edoc'           => 'erp:read',
        'receipt'        => 'erp_cash:read',
        'form'           => 'forms:read',
        'calendar_event' => 'calendars:read',
        'file'           => 'files:read',
        'page'           => 'pages:read',
    );
}

/**
 * One line of the search box as the API shows it. The screen's addresses
 * are made whole, so they open from outside the panel.
 *
 * @param array $item a line of ws_palette_search()
 * @return array
 */
function ws_api_search_hit_present($item)
{
    $url = (string) ($item['url'] ?? '');

    if (($url !== '') && ($url[0] === '/') && (substr($url, 0, 2) !== '//')) {
        $url = URL_SCHEME . HOSTNAME_SETTING . $url;
    }

    return array(
        'type'        => (string) $item['type'],
        'id'          => (int) $item['id'],
        'title'       => (string) $item['title'],
        'detail'      => (string) ($item['sub'] ?? ''),
        'channel_id'  => !empty($item['channel_id']) ? (int) $item['channel_id'] : null,
        'message_id'  => !empty($item['message_id']) ? (int) $item['message_id'] : null,
        'record_type' => isset($item['record_type']) ? (string) $item['record_type'] : null,
        'url'         => $url,
    );
}

// What ws_api_search_hit_present() returns.
function ws_api_search_hit_schema()
{
    return array(
        'type'        => 'string',
        'id'          => 'integer',
        'title'       => 'string',
        'detail'      => 'string',
        'channel_id'  => 'integer?',
        'message_id'  => 'integer?',
        'record_type' => 'string?',
        'url'         => 'string',
    );
}

function ws_api_search($params)
{
    $viewer = ws_api_viewer();
    $granted = api_current_scopes();
    $q = trim((string) $params['q']);
    $channel_id = (int) ($params['channel_id'] ?? 0);

    if ($channel_id > 0) {
        ws_api_channel_or_404($viewer, $channel_id);
    }

    $types = !empty($params['type']) ? array((string) $params['type']) : array('channels', 'messages', 'decisions', 'tasks', 'notes', 'files', 'records');

    if (!api_has_scope($granted, 'tasks:read')) {
        if ($types === array('tasks')) {
            api_fail_scope('tasks:read');
        }

        $types = array_values(array_diff($types, array('tasks')));
    }

    $kinds = array();

    foreach (ws_api_search_record_scopes() as $kind => $scope) {
        if (api_has_scope($granted, $scope)) {
            $kinds[] = $kind;
        }
    }

    // The writing box's commands are not something to look for from outside.
    if (mb_substr($q, 0, 1) === '/') {
        api_ok_list(array(), 0);

        return;
    }

    $found = ws_palette_search($viewer, $q, array(
        'channel_id'   => $channel_id,
        'types'        => $types,
        'limit'        => (int) ($params['limit'] ?? WS_PALETTE_LIMIT),
        'record_kinds' => $kinds,
    ));

    // The application Claude works through finds only where Claude may be
    // asked, as it reads everywhere else.
    $claude = ws_api_is_claude();
    $out = array();

    foreach ($found['groups'] as $group) {
        foreach ($group['items'] as $item) {
            if ($claude && !empty($item['channel_id']) && !ws_claude_channel_allowed(ws_channel($item['channel_id']))) {
                continue;
            }

            if ($claude && ($item['type'] === 'note')) {
                $note = ws_note_for($viewer, $item['id'], 'view');

                if (!$note || !ws_api_note_reachable($viewer, $note)) {
                    continue;
                }
            }

            $out[] = ws_api_search_hit_present($item);
        }
    }

    api_ok_list($out, count($out));
}

/* ---------------------------------------------------------------------------
   The owner's day (home.php, briefing.php)
   --------------------------------------------------------------------------- */

/**
 * The moments some messages were written, in one read.
 *
 * @param int[] $message_ids
 * @return array message id => unix time
 */
function ws_api_message_times($message_ids)
{
    $message_ids = array_filter(array_map('intval', (array) $message_ids));
    $out = array();

    if (!empty($message_ids)) {
        foreach ((array) db_items("SELECT id, created_at FROM ws_messages WHERE id IN (" . implode(',', $message_ids) . ")") as $row) {
            $out[(int) $row['id']] = (int) $row['created_at'];
        }
    }

    return $out;
}

/**
 * A line of what was written to the person (ws_home_talk()).
 *
 * @param array $talk
 * @param array $times ws_api_message_times()
 * @return array
 */
function ws_api_my_day_talk_present($talk, $times)
{
    return array(
        'message_id' => (int) $talk['id'],
        'channel_id' => (int) $talk['channel']['id'],
        'channel'    => (string) $talk['channel']['name'],
        'kind'       => (string) $talk['kind'],
        'unread'     => !empty($talk['unread']),
        'sender'     => is_array($talk['sender'] ?? null) ? (string) ($talk['sender']['name'] ?? '') : '',
        'text'       => (string) $talk['text'],
        'created_at' => isset($times[(int) $talk['id']]) ? api_time($times[(int) $talk['id']]) : null,
    );
}

/**
 * A message waiting for the person to say they read it (ws_acks_waiting()).
 *
 * @param array $ack
 * @param array $times ws_api_message_times()
 * @return array
 */
function ws_api_my_day_ack_present($ack, $times)
{
    return array(
        'message_id' => (int) $ack['message_id'],
        'channel_id' => (int) $ack['channel']['id'],
        'channel'    => (string) $ack['channel']['name'],
        'asked_by'   => (string) $ack['asker'],
        'text'       => (string) $ack['text'],
        'created_at' => isset($times[(int) $ack['message_id']]) ? api_time($times[(int) $ack['message_id']]) : null,
    );
}

/**
 * The day as the API shows it, from the parts ws_api_my_day_get() gathered.
 *
 * @param array $day
 * @return array
 */
function ws_api_my_day_present($day)
{
    return array(
        'date'             => (string) $day['date'],
        'left_out'         => array_values($day['left_out']),
        'tasks_open'       => (int) $day['tasks_open'],
        'tasks_overdue'    => (int) $day['tasks_overdue'],
        'tasks_due_today'  => (int) $day['tasks_due_today'],
        'tasks_new'        => (int) $day['tasks_new'],
        'due'              => array_values($day['due']),
        'timer'            => $day['timer'],
        'plan_items'       => array_values($day['plan_items']),
        'approvals'        => array_values($day['approvals']),
        'proposed_changes' => array_values($day['proposed_changes']),
        'read_receipts'    => array_values($day['read_receipts']),
        'written_to_me'    => array_values($day['written_to_me']),
        'inbox_unread'     => (int) $day['inbox_unread'],
    );
}

// What ws_api_my_day_present() returns.
function ws_api_my_day_schema()
{
    return array(
        'date'             => 'string',
        'left_out'         => array('string'),
        'tasks_open'       => 'integer',
        'tasks_overdue'    => 'integer',
        'tasks_due_today'  => 'integer',
        'tasks_new'        => 'integer',
        'due'              => 'WorkspaceTask[]',
        'timer'            => 'WorkspaceTaskTime?',
        'plan_items'       => 'WorkspacePlanItem[]',
        'approvals'        => 'WorkspaceApproval[]',
        'proposed_changes' => 'WorkspaceChange[]',
        'read_receipts'    => array(array(
            'message_id' => 'integer',
            'channel_id' => 'integer',
            'channel'    => 'string',
            'asked_by'   => 'string',
            'text'       => 'string',
            'created_at' => 'string?',
        )),
        'written_to_me'    => array(array(
            'message_id' => 'integer',
            'channel_id' => 'integer',
            'channel'    => 'string',
            'kind'       => 'string',
            'unread'     => 'boolean',
            'sender'     => 'string',
            'text'       => 'string',
            'created_at' => 'string?',
        )),
        'inbox_unread'     => 'integer',
    );
}

function ws_api_my_day_get($params)
{
    $viewer = ws_api_viewer();
    $me = (int) $viewer['id'];
    $today = date('Y-m-d');
    $tasks_read = api_has_scope(api_current_scopes(), 'tasks:read');

    $day = array(
        'date'             => $today,
        'left_out'         => $tasks_read ? array() : array('tasks:read'),
        'tasks_open'       => 0,
        'tasks_overdue'    => 0,
        'tasks_due_today'  => 0,
        'tasks_new'        => 0,
        'due'              => array(),
        'timer'            => null,
        'plan_items'       => array(),
        'approvals'        => array(),
        'proposed_changes' => array(),
        'read_receipts'    => array(),
        'written_to_me'    => array(),
        'inbox_unread'     => ws_inbox_unread_count($me),
    );

    if ($tasks_read) {
        $counts = db_item("SELECT COUNT(*) AS open_count,
                SUM(t.due_date > '0000-00-00' AND t.due_date < '" . e($today) . "') AS overdue_count,
                SUM(t.due_date = '" . e($today) . "') AS today_count
            FROM ws_tasks t
            INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'
            WHERE t.status IN ('todo', 'doing', 'waiting')");

        $day['tasks_open'] = (int) ($counts['open_count'] ?? 0);
        $day['tasks_overdue'] = (int) ($counts['overdue_count'] ?? 0);
        $day['tasks_due_today'] = (int) ($counts['today_count'] ?? 0);

        // Handed over and not looked at yet, as the dashboard greeting counts.
        $day['tasks_new'] = (int) db_value("SELECT COUNT(*) FROM ws_inbox
            WHERE user_id = '" . $me . "' AND kind = 'assigned' AND read_at = 0");

        // Due today or before, the earliest first.
        $ids = array();

        foreach (ws_tasks_list($viewer, array('scope' => 'mine', 'status' => 'open', 'to' => $today, 'limit' => 50)) as $brief) {
            if ($brief['due_date'] !== null) {
                $ids[] = (int) $brief['id'];
            }
        }

        $known = ws_api_tasks_known($ids);

        foreach ($ids as $id) {
            $day['due'][] = ws_api_task_present($viewer, ws_task($id), array(), $known);
        }

        $running = ws_task_time_ready() ? ws_task_time_running($me) : null;

        if ($running) {
            $day['timer'] = ws_api_task_time_present($running);
        }

        $departments = ws_user_department_ids($me);

        foreach (ws_events_between($today, $today) as $event) {
            if (ws_event_applies($event, $me, $departments)) {
                $day['plan_items'][] = ws_api_plan_item_present($viewer, $event);
            }
        }
    }

    // The application Claude works through reads only where Claude may be
    // asked.
    $claude = ws_api_is_claude();
    $allowed = function ($channel_id) use ($claude) {
        return !$claude || ws_claude_channel_allowed(ws_channel($channel_id));
    };

    foreach (ws_approvals_waiting($viewer, 20) as $waiting) {
        $row = $allowed($waiting['channel']['id']) ? ws_approval($waiting['id']) : null;

        if ($row) {
            $day['approvals'][] = ws_api_approval_present($viewer, $row);
        }
    }

    // What the assistants proposed at the owner's request and only the
    // owner may apply: in the channels they read and on the tasks they see.
    if (function_exists('ws_changes_ready') && ws_changes_ready()) {
        $day['proposed_changes'] = ws_api_changes_present($viewer, ws_api_changes_rows(array(
            ws_api_changes_reach_sql($viewer),
            "c.status = 'pending'",
            "c.requested_by = '" . $me . "'",
        ), 20));
    }

    $acks = array();
    $talk = array();

    foreach (ws_acks_waiting($viewer, 20) as $ack) {
        if ($allowed($ack['channel']['id'])) {
            $acks[] = $ack;
        }
    }

    foreach (ws_home_talk($viewer, 10) as $line) {
        if ($allowed($line['channel']['id'])) {
            $talk[] = $line;
        }
    }

    $times = ws_api_message_times(array_merge(
        array_map(function ($ack) { return (int) $ack['message_id']; }, $acks),
        array_map(function ($line) { return (int) $line['id']; }, $talk)
    ));

    foreach ($acks as $ack) {
        $day['read_receipts'][] = ws_api_my_day_ack_present($ack, $times);
    }

    foreach ($talk as $line) {
        $day['written_to_me'][] = ws_api_my_day_talk_present($line, $times);
    }

    api_ok(ws_api_my_day_present($day));
}
