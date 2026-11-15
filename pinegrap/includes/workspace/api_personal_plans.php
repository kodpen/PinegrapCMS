<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's own plans (personal_plans.php) in the external API
 * and MCP: the person lists, reads, adds, changes and deletes their own
 * plans through their own team devices session or an OAuth connection they
 * approved, under the scope workspace:personal_plans.
 * An application key - an integration, a channel agent, Claude's routine or
 * Pinegrap AI - never reaches a plan, whatever its scopes: a plan is read
 * only when its owner asks. A plan somebody else shared with the caller is
 * not listed either; it stays on My calendar.
 *
 * Every read goes through personal_plans.php and its gate; this file names
 * no plan table. Nothing here writes a Site Log line, a webhook or a flow
 * event: the title and the note are the person's own words.
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

/** The widest span GET /workspace/personal-plans lists, in days. */
const WS_API_PERSONAL_PLANS_SPAN_MAX = 366;

/**
 * The plan routes, merged into ws_api_routes(). Reading, adding and changing
 * are tools; deleting cannot be undone, so it is not.
 *
 * @return array[]
 */
function ws_personal_plan_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $fields = array(
        array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'What the plan is called on My calendar.'),
        array('name' => 'note', 'in' => 'body', 'type' => 'string', 'max_length' => WS_PERSONAL_PLAN_NOTE_MAX, 'description' => 'A note of the person\'s own, plain text.'),
        array('name' => 'start', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'The first day, YYYY-MM-DD, in the site\'s time zone.'),
        array('name' => 'end', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'The last day, YYYY-MM-DD; the first day when empty.'),
        array('name' => 'all_day', 'in' => 'body', 'type' => 'bool', 'description' => 'true: the plan takes the whole days, start_time and end_time are not read.'),
        array('name' => 'start_time', 'in' => 'body', 'type' => 'string', 'max_length' => 5, 'description' => 'HH:MM on the first day; needed unless all_day.'),
        array('name' => 'end_time', 'in' => 'body', 'type' => 'string', 'max_length' => 5, 'description' => 'HH:MM on the last day; one hour after the start when empty.'),
        array('name' => 'remind', 'in' => 'body', 'type' => 'string', 'max_length' => 5, 'description' => 'Minutes before the start to remind the person in their inbox (0, 15, 60, 1440 on My calendar; at most 40320), counted from 09:00 of the first day for an all-day plan; empty for no reminder.'),
        array('name' => 'visibility', 'in' => 'body', 'type' => 'enum', 'values' => array('me', 'people', 'team'), 'description' => 'Who else sees it on their My calendar: me (nobody, the default), people (the team members in people) or team (the whole team). They read it and change nothing.'),
        array('name' => 'people', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 100, 'description' => 'User ids of the team members who may see it, with visibility people; anyone off the team is dropped.'),
        array('name' => 'repeat', 'in' => 'body', 'type' => 'string', 'max_length' => 8, 'description' => 'How the plan repeats: daily, weekly, monthly or yearly; empty for a plan that does not repeat. A plan with roadmap steps does not repeat. A plan longer than the step between its times is refused.'),
        array('name' => 'repeat_every', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => WS_PERSONAL_PLAN_REPEAT_INTERVAL_MAX, 'description' => 'Every how many days, weeks, months or years; 1 when left out.'),
        array('name' => 'repeat_end', 'in' => 'body', 'type' => 'enum', 'values' => array('never', 'until', 'count'), 'description' => 'When the repeat ends: never, until (repeat_until) or count (repeat_count).'),
        array('name' => 'repeat_until', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'With repeat_end until: the last day a time may start on, YYYY-MM-DD.'),
        array('name' => 'repeat_count', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => WS_PERSONAL_PLAN_REPEAT_COUNT_MAX, 'description' => 'With repeat_end count: how many times, counted from the first.'),
    );
    // One time of a repeating plan, or it and the ones after it, as "This one"
    // and "This and the following" on My calendar. In the query
    // of DELETE, in the body of the POST routes.
    $times = array(
        array('name' => 'mode', 'in' => 'query', 'type' => 'enum', 'values' => array('one', 'following'), 'description' => 'For a repeating plan: one changes or deletes only the time starting on day (it leaves the series and becomes a plan of its own when changed); following does so for that time and the ones after it (the series ends before it). Left out: the whole series.'),
        array('name' => 'day', 'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'With mode: the day the time starts on, YYYY-MM-DD, as times[].day in the list gives it; on a change, the fields left out are the time\'s own.'),
    );
    $times_body = array_map(function ($param) {
        return array_merge($param, array('in' => 'body'));
    }, $times);
    $errors = ' A refused value answers 422 validation_failed with the reason (a title, the day and, unless all_day, the time are needed; the end comes after the start; people needs somebody on the team). A person keeps at most ' . WS_PERSONAL_PLAN_MAX . ' plans.';
    $who = ' Only the person\'s own team devices session or an OAuth connection they approved reaches their plans; an application key gets 403 forbidden whatever its scopes, so no integration, channel agent or assistant key reads them.';

    $create_fields = $fields;
    $create_fields[0]['required'] = true;
    $create_fields[2]['required'] = true;

    return array(
        array(
            'id'          => 'workspace.personal_plans.list',
            'method'      => 'GET',
            'path'        => '/workspace/personal-plans',
            'scope'       => 'workspace:personal_plans',
            'handler'     => 'ws_api_personal_plans_list',
            'returns'     => array('list' => 'WorkspacePersonalPlan'),
            'summary'     => 'The person\'s own plans',
            'description' => 'The plans of the signed-in person on My calendar that touch the days from..to, by start, all in one page (at most 1000). A repeating plan is listed once, with its rule; times holds every plan\'s times inside from..to (a plan that does not repeat has its one time). A plan another person shared with them is not listed. Closed to everybody else, administrators included.' . $who,
            'params'      => array(
                array('name' => 'from', 'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'The first day, YYYY-MM-DD; today when left out.'),
                array('name' => 'to', 'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'The last day, YYYY-MM-DD; 30 days after from when left out; at most ' . WS_API_PERSONAL_PLANS_SPAN_MAX . ' days after from.'),
            ),
        ),

        array(
            'id'          => 'workspace.personal_plans.get',
            'method'      => 'GET',
            'path'        => '/workspace/personal-plans/{id}',
            'scope'       => 'workspace:personal_plans',
            'handler'     => 'ws_api_personal_plans_get',
            'returns'     => 'WorkspacePersonalPlan',
            'summary'     => 'One of the person\'s plans',
            'description' => 'A plan of the signed-in person; anybody else\'s plan is 404, a shared one too.' . $who,
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.personal_plans.create',
            'method'      => 'POST',
            'path'        => '/workspace/personal-plans',
            'scope'       => 'workspace:personal_plans',
            'handler'     => 'ws_api_personal_plans_create',
            'dry_run'     => true,
            'status'      => 201,
            'returns'     => 'WorkspacePersonalPlan',
            'summary'     => 'Add a plan',
            'description' => 'Adds a plan to the signed-in person\'s My calendar, as "New plan" there does. It takes no capacity and is not a plan item of the team.' . $errors . $who,
            'params'      => $create_fields,
        ),

        array(
            'id'           => 'workspace.personal_plans.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/personal-plans/{id}',
            'scope'        => 'workspace:personal_plans',
            'handler'      => 'ws_api_personal_plans_update',
            'dry_run'      => true,
            'returns'      => 'WorkspacePersonalPlan',
            'summary'      => 'Change a plan',
            'description'  => 'Changes only the fields sent on a plan of the signed-in person; anybody else\'s plan is 404, a shared one too. A changed reminder moment reminds again. people replaces the list. On a repeating plan the change is for the whole series, or with mode and day for one time or for it and the following ones. PATCH is accepted beside POST.' . $errors . $who,
            'params'       => array_merge(array($id), $fields, $times_body),
        ),

        array(
            'id'          => 'workspace.personal_plans.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/personal-plans/{id}',
            'scope'       => 'workspace:personal_plans',
            'handler'     => 'ws_api_personal_plans_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary'     => 'Delete a plan',
            'description' => 'Deletes a plan of the signed-in person for good, with its reminder lines; there is no bin. A repeating plan goes as a whole series with the times changed out of it, or with mode and day one time or it and the following ones. Anybody else\'s plan is 404. It cannot be undone, so it is not offered to assistants as a tool. POST /workspace/personal-plans/{id}/delete does the same for servers that refuse DELETE.' . $who,
            'params'      => array_merge(array($id), $times),
        ),

        array(
            'id'          => 'workspace.personal_plans.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/personal-plans/{id}/delete',
            'scope'       => 'workspace:personal_plans',
            'handler'     => 'ws_api_personal_plans_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary'     => 'Delete a plan (POST)',
            'description' => 'The same as DELETE /workspace/personal-plans/{id}, for servers and clients that refuse DELETE.',
            'params'      => array_merge(array($id), $times_body),
        ),
    );
}

// The fields of a plan as ws_personal_plan_for_reader() gives them to its owner.
function ws_api_personal_plan_schema()
{
    return array(
        'id'         => 'integer',
        'title'      => 'string',
        'note'       => 'string',
        'start'      => 'string',
        'end'        => 'string',
        'start_time' => 'string',
        'end_time'   => 'string',
        'all_day'    => 'boolean',
        'remind'     => 'string',
        'visibility' => 'string',
        'owner_id'   => 'integer',
        'mine'       => 'boolean',
        'people'     => 'array',
        'owner_name' => 'string',
        'time_day'     => 'string',
        'repeat'       => 'string',
        'repeat_every' => 'integer',
        'repeat_end'   => 'string',
        'repeat_until' => 'string',
        'repeat_count' => 'string',
        // GET /workspace/personal-plans only: the times inside from..to.
        'times'        => 'array',
    );
}

/** The fields a write may send. */
function ws_api_personal_plan_fields()
{
    return array('title', 'note', 'start', 'end', 'all_day', 'start_time', 'end_time', 'remind', 'visibility', 'people',
        'repeat', 'repeat_every', 'repeat_end', 'repeat_until', 'repeat_count');
}

/**
 * What ws_personal_plan_save() is given: the plan as it is (its owner's
 * view, none for a new one) with the fields sent laid over it. Pure.
 *
 * @param array|null $current ws_personal_plan_for_reader() for the owner
 * @param array      $params  the validated request
 * @return array
 */
function ws_api_personal_plan_merge($current, $params)
{
    $data = array();

    if (is_array($current)) {
        foreach (ws_api_personal_plan_fields() as $field) {
            if (array_key_exists($field, $current)) {
                $data[$field] = $current[$field];
            }
        }
    }

    foreach (ws_api_personal_plan_fields() as $field) {
        if (array_key_exists($field, $params) && ($params[$field] !== null)) {
            $data[$field] = $params[$field];
        }
    }

    $data['all_day'] = !empty($data['all_day']) && ($data['all_day'] !== '0') ? '1' : '';
    $data['people'] = array_values(array_map('intval', (array) ($data['people'] ?? array())));

    return $data;
}

/**
 * The span of GET /workspace/personal-plans: array(from_ts, to_ts) with the
 * end exclusive, or array('error' => text). Pure.
 *
 * @param string|null $from YYYY-MM-DD
 * @param string|null $to   YYYY-MM-DD
 * @param int         $now
 * @return array
 */
function ws_api_personal_plans_span($from, $to, $now)
{
    $day = function ($value) {
        return (is_string($value) && preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) ? $value : null;
    };
    $from = (($from === null) || ($from === '')) ? date('Y-m-d', $now) : $day($from);

    if ($from === null) {
        return array('error' => lang('Give the days as YYYY-MM-DD.'), 'field' => 'from');
    }

    $from_ts = strtotime($from . ' 00:00:00');
    $to = (($to === null) || ($to === '')) ? date('Y-m-d', strtotime($from . ' 00:00:00 +30 days')) : $day($to);

    if ($to === null) {
        return array('error' => lang('Give the days as YYYY-MM-DD.'), 'field' => 'to');
    }

    $to_ts = strtotime($to . ' 00:00:00 +1 day');

    if ($to_ts <= $from_ts) {
        return array('error' => lang('The last day cannot come before the first.'), 'field' => 'to');
    }

    if ($to_ts > strtotime($from . ' 00:00:00 +' . WS_API_PERSONAL_PLANS_SPAN_MAX . ' days')) {
        return array('error' => lang('List at most a year at a time.'), 'field' => 'to');
    }

    return array($from_ts, $to_ts);
}

/**
 * The caller every plan route asks for: the person themselves, through
 * their own team devices session or an OAuth connection they approved. An
 * application key is refused before anything is read.
 *
 * @return array the viewer
 */
function ws_api_personal_plans_caller()
{
    ws_api_person_only(lang('Only the person, through their own team devices session or an OAuth connection they approved, can reach their plans; an application key cannot.'));

    $viewer = ws_api_viewer();

    if (!ws_personal_plans_ready()) {
        api_fail(503, 'service_unavailable', lang('Plans need the database update first.'));
    }

    return $viewer;
}

/**
 * One of the caller's own plans, as its owner reads it, or 404 (also for a
 * plan another person shared with them).
 *
 * @param array $viewer
 * @param mixed $id
 * @return array
 */
function ws_api_personal_plan_or_404($viewer, $id)
{
    $plan = ws_personal_plan((int) $viewer['id'], (int) $id);

    if (!$plan || !ws_personal_plan_can_edit((int) $viewer['id'], $plan)) {
        api_fail_not_found(lang('Plan'));
    }

    return ws_personal_plan_for_reader((int) $viewer['id'], $plan);
}

/**
 * Checks a write as the drawer's save does, and refuses what it turns down.
 *
 * @param array $data ws_api_personal_plan_merge()
 */
function ws_api_personal_plan_check($data)
{
    $clean = ws_personal_plan_clean($data);

    if (isset($clean['error'])) {
        api_fail_validation($clean['error'], null);
    }

    // The rule, as the save reads it (a write without one keeps the plan's).
    $rule = (ws_personal_plan_repeat_ready() && array_key_exists('repeat', $data)) ? ws_personal_plan_repeat_clean($clean, $data) : array();

    if (isset($rule['error'])) {
        api_fail_validation($rule['error'], null);
    }
}

/**
 * Saves and answers, or refuses what the save turned down.
 *
 * @param array $viewer
 * @param array $data
 * @param int   $plan_id
 * @param int   $status
 */
function ws_api_personal_plan_write($viewer, $data, $plan_id, $status)
{
    $result = ws_personal_plan_save($viewer, $data, $plan_id);

    if (empty($result['ok'])) {
        $error = (string) ($result['error'] ?? lang('Access denied'));

        if ($error === lang('This plan cannot be found.')) {
            api_fail_not_found(lang('Plan'));
        }

        api_fail_validation($error, null);
    }

    api_ok($result['plan'], $status);
}

function ws_api_personal_plans_list($params)
{
    $viewer = ws_api_personal_plans_caller();
    $span = ws_api_personal_plans_span($params['from'] ?? null, $params['to'] ?? null, time());

    if (isset($span['error'])) {
        api_fail_validation($span['error'], $span['field']);
    }

    $out = array();

    // A repeating plan once, with its rule and its times in the span.
    $plans = array_values(array_filter(ws_personal_plans_between((int) $viewer['id'], $span[0], $span[1], false), function ($plan) use ($viewer) {
        return ws_personal_plan_can_edit((int) $viewer['id'], $plan);
    }));
    $skips = ws_personal_plan_skips(array_column($plans, 'id'));

    foreach ($plans as $plan) {
        $out[] = ws_personal_plan_for_reader((int) $viewer['id'], $plan)
            + array('times' => ws_api_personal_plan_times($plan, $span[0], $span[1], $skips[(int) $plan['id']] ?? array()));
    }

    api_ok_list($out, 1000, null, count($out));
}

function ws_api_personal_plans_get($params)
{
    $viewer = ws_api_personal_plans_caller();

    api_ok(ws_api_personal_plan_or_404($viewer, $params['id']));
}

function ws_api_personal_plans_create($params)
{
    $viewer = ws_api_personal_plans_caller();
    $data = ws_api_personal_plan_merge(null, $params);

    ws_api_personal_plan_check($data);
    api_dry_run_stop('created', 'workspace_personal_plan', array('title' => (string) $data['title']));
    ws_api_personal_plan_write($viewer, $data, 0, 201);
}

function ws_api_personal_plans_update($params)
{
    $viewer = ws_api_personal_plans_caller();
    $plan = ws_api_personal_plan_or_404($viewer, $params['id']);
    $time = ws_api_personal_plan_time_asked($viewer, $plan, $params);
    // One time, or it and the following: the fields left out are the time's.
    $data = ws_api_personal_plan_merge($time ? $time['plan'] : $plan, $params);

    if ($time) {
        $data['mode'] = $time['mode'];
        $data['time_day'] = $time['day'];
    }

    ws_api_personal_plan_check($data);
    api_dry_run_stop('updated', 'workspace_personal_plan', array('id' => (int) $plan['id']));
    ws_api_personal_plan_write($viewer, $data, (int) $plan['id'], 200);
}

function ws_api_personal_plans_delete($params)
{
    $viewer = ws_api_personal_plans_caller();
    $plan = ws_api_personal_plan_or_404($viewer, $params['id']);

    ws_api_personal_plan_time_asked($viewer, $plan, $params);
    api_dry_run_stop('deleted', 'workspace_personal_plan', array('id' => (int) $plan['id']));

    $time = ws_api_personal_plan_time_asked($viewer, $plan, $params);
    $result = $time ? ws_personal_plan_delete($viewer, (int) $plan['id'], $time['mode'], $time['day']) : ws_personal_plan_delete($viewer, (int) $plan['id']);

    if (empty($result['ok'])) {
        api_fail_not_found(lang('Plan'));
    }

    api_ok(array('id' => (int) $plan['id'], 'deleted' => true));
}

/**
 * The time a change or a delete names (mode and day), or null for the whole
 * plan; 422 for a mode without a day, a day with no time of the plan, or a
 * mode on a plan that does not repeat.
 *
 * @param array $viewer
 * @param array $plan   the caller's plan as its owner reads it
 * @param array $params
 * @return array|null mode, day, plan (the time as its owner reads it)
 */
function ws_api_personal_plan_time_asked($viewer, $plan, $params)
{
    $mode = is_string($params['mode'] ?? null) ? $params['mode'] : '';

    if ($mode === '') {
        return null;
    }

    $row = ws_personal_plan((int) $viewer['id'], (int) $plan['id']);
    $at = ($row && ws_personal_plan_repeats($row)) ? ws_personal_plan_at_time($row, is_string($params['day'] ?? null) ? $params['day'] : '') : null;

    if (!$at) {
        api_fail_validation(lang('This time of the plan cannot be found.'), ($row && ws_personal_plan_repeats($row)) ? 'day' : 'mode');
    }

    return array('mode' => $mode, 'day' => (string) $at['time_day'], 'plan' => ws_personal_plan_for_reader((int) $viewer['id'], $at));
}

/**
 * A plan's times inside a span as the list answers them: day, the first and
 * last day, and the hours unless it lasts all day. Pure.
 *
 * @param array    $plan  a plan row
 * @param int      $from_ts
 * @param int      $to_ts
 * @param string[] $skips
 * @return array[]
 */
function ws_api_personal_plan_times($plan, $from_ts, $to_ts, $skips = array())
{
    $all_day = ((int) $plan['all_day'] === 1);
    $out = array();

    foreach (ws_personal_plan_times($plan, $from_ts, $to_ts, $skips) as $time) {
        $out[] = array(
            'day'        => $time['day'],
            'start'      => date('Y-m-d', $time['starts_at']),
            'end'        => date('Y-m-d', max($time['starts_at'], $all_day ? $time['ends_at'] - 1 : $time['ends_at'])),
            'start_time' => $all_day ? '' : date('H:i', $time['starts_at']),
            'end_time'   => $all_day ? '' : date('H:i', $time['ends_at']),
        );
    }

    return $out;
}
