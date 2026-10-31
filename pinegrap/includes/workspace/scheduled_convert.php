<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - every scheduled action becomes a flow (2026.11.0). Each staff
 * action is drawn as a flow (ws_flow_graph_from_scheduled()): its "when" the
 * start box, each condition a condition box, its step and follow-ups boxes
 * on their arrows. The flow is published as its first version, owned by the
 * action's creator, with no home channel, so nobody gains a right the
 * action did not give. The action's row stays, its id, card, history and
 * state kept, and becomes the flow's converted anchor
 * (ws_scheduled_actions.anchor): its rules keep the "when" only, its step
 * starts the flow (ws_flow_anchor_start()), and the run's end is written
 * back to it (ws_flow_anchor_run_ended()). Any staff member changes it on
 * the flow canvas, as they changed the action.
 *
 * The upgrade cannot load the Workspace code, so the rows are converted by
 * the scheduled actions' driver (ws_scheduled_run()) in the first requests
 * after it, a few at a time; an action saved from the form afterwards is
 * converted the same way. A row whose drawing the flow would refuse stays
 * as it is and keeps running on ws_scheduled_execute().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How many scheduled actions one request converts at most. */
define('WS_FLOW_CONVERT_BATCH', 10);

/**
 * The rule that keeps starting a converted action: its first "when" (a
 * time, an event, a join, or by hand and by a chain). Its conditions are
 * the flow's condition boxes now. Pure.
 *
 * @param array $rules the action's rules, decoded
 * @return array[] one rule
 */
function ws_scheduled_convert_rules($rules)
{
    foreach ((array) $rules as $rule) {
        if (is_array($rule) && in_array((string) ($rule['type'] ?? ''), array('at', 'event', 'join', 'trigger'), true)) {
            return array($rule);
        }
    }

    return array(array('type' => 'trigger'));
}

/**
 * Converts the scheduled actions still waiting, at most $limit of them: the
 * staff's actions (never a scheduled message), not cancelled, with no flow.
 * A row the flow refuses is passed over and stays as it is.
 *
 * @param int $limit
 * @return int how many were converted
 */
function ws_scheduled_convert_due($limit = WS_FLOW_CONVERT_BATCH)
{
    if (!function_exists('ws_scheduled_anchor_ready') || !ws_scheduled_anchor_ready() || !ws_scheduled_messages_ready()) {
        return 0;
    }

    $done = 0;
    $until = time() + 5;

    foreach ((array) db_items("SELECT id FROM ws_scheduled_actions
        WHERE kind = 'action' AND flow_id = 0 AND anchor = '' AND status IN ('active', 'paused', 'failed', 'done')
        ORDER BY id LIMIT 200") as $item) {
        if (($done >= (int) $limit) || (time() > $until)) {
            break;
        }

        if (ws_scheduled_convert((int) $item['id'])) {
            $done++;
        }
    }

    if ($done > 0) {
        ws_flow_converted(0, true);
    }

    return $done;
}

/**
 * The flow an action is converted into, drawn as its creator sees it, and
 * what the flow refuses in it (ws_flow_problems()): nothing when it can be
 * converted.
 *
 * @param array $row ws_scheduled()
 * @return array graph, left (what the flow keeps otherwise, in sentences), problems
 */
function ws_scheduled_convert_drawing($row)
{
    $owner = (int) $row['created_by'];
    $viewer = ws_change_viewer_for_id($owner);
    $viewer = is_array($viewer) ? $viewer : array('id' => $owner, 'member' => false, 'role' => 3);
    $made = ws_flow_graph_from_scheduled(ws_scheduled_form_data($viewer, $row), ws_scheduled_money_metrics(), (int) $row['id']);
    $graph = ws_flow_graph_normalize($made['graph']);

    return array('graph' => $graph, 'left' => $made['left'], 'problems' => is_array($graph) ? ws_flow_problems($graph) : array());
}

/**
 * Converts one scheduled action into a flow and makes the action its
 * converted anchor. Taken with the action's run lock, so a run and a
 * second request leave it alone meanwhile; the anchor is written last and
 * only on a row still without a flow.
 *
 * @param int $id
 * @return bool converted
 */
function ws_scheduled_convert($id)
{
    $now = time();

    db("UPDATE ws_scheduled_actions SET running_at = '" . $now . "'
        WHERE id = '" . (int) $id . "' AND kind = 'action' AND flow_id = 0 AND anchor = ''
        AND running_at < '" . ($now - WS_SCHEDULED_LOCK) . "'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return false;
    }

    $release = function () use ($id) {
        db("UPDATE ws_scheduled_actions SET running_at = 0 WHERE id = '" . (int) $id . "' AND flow_id = 0");

        return false;
    };

    $row = ws_scheduled($id);

    if (!$row) {
        return $release();
    }

    $owner = (int) $row['created_by'];
    $made = ws_scheduled_convert_drawing($row);
    $graph = $made['graph'];
    $json = is_array($graph) ? json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : false;

    if (!is_string($json) || !empty($made['problems'])) {
        return $release();
    }

    $name = mb_substr((string) $row['name'], 0, 160);

    db("INSERT INTO ws_flows (owner_id, channel_id, name, status, draft, draft_rev, version_id, updated_by, created_at, updated_at)
        VALUES ('" . $owner . "', 0, '" . e($name) . "', 'live', '" . e($json) . "', 1, 0, '" . $owner . "', '" . $now . "', '" . $now . "')");

    $flow_id = (int) mysqli_insert_id(db::$con);

    if ($flow_id <= 0) {
        return $release();
    }

    db("INSERT INTO ws_flow_versions (flow_id, n, graph, published_by, published_at)
        VALUES ('" . $flow_id . "', 1, '" . e($json) . "', '" . $owner . "', '" . $now . "')");

    db("UPDATE ws_flows SET version_id = '" . (int) mysqli_insert_id(db::$con) . "' WHERE id = '" . $flow_id . "'");

    $follow = ws_scheduled_chains_ready() ? ", follow = '[]', then_action = NULL" : ", then_action = NULL";

    db("UPDATE ws_scheduled_actions SET flow_id = '" . $flow_id . "', anchor = 'converted',
            rules = '" . e(json_encode(ws_scheduled_convert_rules($row['rules']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "',
            action = '" . e(json_encode(array('type' => 'flow', 'flow_id' => $flow_id))) . "'" . $follow . ", running_at = 0
        WHERE id = '" . (int) $row['id'] . "' AND flow_id = 0");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        db("DELETE FROM ws_flow_versions WHERE flow_id = '" . $flow_id . "'");
        db("DELETE FROM ws_flows WHERE id = '" . $flow_id . "'");

        return $release();
    }

    $said = lang(array('string' => 'scheduled action “{var:1}” now runs as a flow', 'vars' => $row['name']));

    log_activity($said . (empty($made['left']) ? '' : ': ' . implode(' ', $made['left'])), ws_person_name($owner));

    return true;
}

/**
 * The flow Add › Scheduled flow in a channel starts from (pure): a time
 * start tomorrow at 09:00, once, and a post box into that channel with no
 * text yet. What the member filled in the mini window replaces those: the
 * start's settings (as ws_scheduled_flow_fill() keeps them) and the text.
 * Laid out as the templates are; ws_flow_graph_normalize() cuts and casts.
 *
 * @param int    $channel_id the channel the composer is in
 * @param string $today      the site's date, Y-m-d
 * @param array  $fill       start (date, time, repeat, every, days, until), body
 * @return array the graph (ws_flow_graph_normalize())
 */
function ws_scheduled_flow_graph($channel_id, $today, $fill = array())
{
    $utc = new DateTimeZone('UTC');
    $day = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $today) === 1) ? date_create_immutable($today . ' 12:00:00', $utc) : false;
    $day = $day ? $day : date_create_immutable(date('Y-m-d') . ' 12:00:00', $utc);
    $start = array('date' => $day->modify('+1 day')->format('Y-m-d'), 'time' => '09:00', 'repeat' => 'none', 'every' => 1, 'days' => array(), 'until' => '');

    foreach ((array) ($fill['start'] ?? array()) as $key => $value) {
        if (array_key_exists($key, $start)) {
            $start[$key] = $value;
        }
    }

    return ws_flow_graph_normalize(array('v' => 1, 'nodes' => array(
        array('id' => 'start', 'kind' => 'schedule', 'label' => '', 'x' => 0, 'y' => 0, 'config' => $start),
        array('id' => 'post', 'kind' => 'post', 'label' => '', 'x' => 256, 'y' => 0,
            'config' => array('channel_id' => max(0, (int) $channel_id), 'body' => is_scalar($fill['body'] ?? null) ? (string) $fill['body'] : '')),
    ), 'edges' => array(
        array('from' => 'start', 'to' => 'post', 'on' => 'next'),
    ), 'notes' => array()));
}

/**
 * What the mini window of Add › Scheduled flow sends, kept to its fields
 * (pure): the flow's name, the time start's settings and the message. Only
 * what was sent is kept, so a missing field leaves the default.
 *
 * @param array $request
 * @return array name (only when sent), start, body
 */
function ws_scheduled_flow_fill($request)
{
    $fill = array('start' => array(), 'body' => '');
    $start = is_array($request['start'] ?? null) ? $request['start'] : array();

    foreach (array('date', 'time', 'repeat', 'every', 'until') as $key) {
        if (isset($start[$key]) && is_scalar($start[$key])) {
            $fill['start'][$key] = (string) $start[$key];
        }
    }

    if (isset($start['days'])) {
        $fill['start']['days'] = array_values(array_filter((array) $start['days'], 'is_scalar'));
    }

    if (isset($request['body']) && is_scalar($request['body'])) {
        $fill['body'] = (string) $request['body'];
    }

    if (isset($request['name']) && is_scalar($request['name']) && (trim((string) $request['name']) !== '')) {
        $fill['name'] = (string) $request['name'];
    }

    return $fill;
}

/**
 * The home of a flow opened from a channel: that channel when a flow may
 * live there (public or private) and the person reads it, else none.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @return int
 */
function ws_scheduled_flow_home($viewer, $channel_id)
{
    $channel = ((int) $channel_id > 0) ? ws_channel((int) $channel_id) : null;

    return ($channel && in_array((string) $channel['kind'], array('public', 'private'), true) && ws_can_read_channel($viewer, $channel)) ? (int) $channel['id'] : 0;
}

/**
 * Add › Scheduled flow in a channel: a draft flow of the person's own, its
 * home that channel where a flow may live there, holding what they filled
 * in the mini window, published there or opened on the canvas. A second
 * write from the same window (flow_id, draft_rev) changes the draft it made
 * before, while it is still a draft of theirs, so a refused publish leaves
 * one draft, not one per try. Only where they may post, with the right to
 * build flows; ws_flow_save() and ws_flow_publish() check it all again.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @param array $data ws_scheduled_flow_fill(), and flow_id, draft_rev, publish
 * @return array ok, error, field (of the save), flow_id, draft_rev, published, publish_error
 */
function ws_scheduled_flow_new($viewer, $channel_id, $data = array())
{
    $answer = function ($saved, $published = false, $publish_error = '') {
        return array('ok' => $saved['ok'], 'error' => $saved['error'], 'field' => (string) ($saved['field'] ?? ''), 'flow_id' => (int) ($saved['flow_id'] ?? 0),
            'draft_rev' => (int) ($saved['draft_rev'] ?? 0), 'published' => $published, 'publish_error' => $publish_error);
    };
    $channel = ws_channel((int) $channel_id);

    if (!$channel || !ws_can_post_channel($viewer, $channel)) {
        return $answer(array('ok' => false, 'error' => lang('That channel could not be found.')));
    }

    $flow = null;

    if ((int) ($data['flow_id'] ?? 0) > 0) {
        $flow = ws_flow_for($viewer, (int) $data['flow_id'], 'edit');

        if (!$flow || ((int) $flow['owner_id'] !== (int) $viewer['id']) || ((string) $flow['status'] !== 'draft')) {
            return $answer(array('ok' => false, 'error' => lang('That flow could not be found.')));
        }
    }

    $fields = array('graph' => ws_scheduled_flow_graph($channel['id'], date('Y-m-d'), $data));

    if ($flow === null) {
        $fields += array('name' => lang('Scheduled flow'), 'channel_id' => ws_scheduled_flow_home($viewer, $channel['id']));
    } else {
        $fields['draft_rev'] = (int) ($data['draft_rev'] ?? 0);
    }

    if (isset($data['name'])) {
        $fields['name'] = (string) $data['name'];
    }

    $saved = ws_flow_save($viewer, $fields, $flow);

    if (!$saved['ok'] || empty($data['publish'])) {
        return $answer($saved);
    }

    // Refused here, the draft stays and the window says why: the next try
    // changes this draft.
    $published = ws_flow_publish($viewer, ws_flow_for($viewer, $saved['flow_id'], 'edit'), array('draft_rev' => $saved['draft_rev']));

    return $answer($saved, $published['ok'], $published['ok'] ? '' : $published['error']);
}

/**
 * The two boxes of Add › Scheduled flow as the mini window draws them before
 * anything is written (ws_flow_card_graph()), each with its dry run as its
 * third line, tried with the person's own rights on the flow in memory
 * (ws_flow_card_try_flow()), with the start's settings the form opens on.
 * What the form holds so far (ws_scheduled_flow_fill()) is drawn and tried
 * in place of the defaults. Only where the person may post and build flows.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @param array $fill ws_scheduled_flow_fill(), none for the first drawing
 * @return array ok, error, graph, start
 */
function ws_scheduled_flow_sketch($viewer, $channel_id, $fill = array())
{
    $channel = ws_channel((int) $channel_id);

    if (!$channel || !ws_can_post_channel($viewer, $channel) || !ws_can_schedule($viewer)) {
        return array('ok' => false, 'error' => lang('That channel could not be found.'), 'graph' => null, 'start' => null);
    }

    $graph = ws_scheduled_flow_graph($channel['id'], date('Y-m-d'), $fill);
    $flow = ws_flow_card_try_flow($viewer, 'new', $graph, null, false, (string) ($fill['name'] ?? lang('Scheduled flow')));

    return array('ok' => true, 'error' => '', 'graph' => ws_flow_card_tries($viewer, $flow, ws_flow_card_graph($graph)), 'start' => $graph['nodes'][0]['config']);
}

/**
 * Duplicate on a scheduled action's card: a new draft flow of the person's
 * own, named as a copy, opened on the canvas. A converted action's copy is
 * its flow's draft; any other is drawn from the action as conversion draws
 * it, a retry of itself becoming the copy's own retry. Its home is the
 * action's channel where a flow may live there; a note keeps no new ones.
 *
 * @param array $viewer
 * @param array $row    ws_scheduled_action()
 * @return array ok, error, flow_id, left (what the flow keeps otherwise, in sentences)
 */
function ws_scheduled_flow_copy($viewer, $row)
{
    $flow = ((int) ($row['flow_id'] ?? 0) > 0) ? ws_flow((int) $row['flow_id']) : null;

    if ($flow) {
        $made = array('graph' => ws_flow_graph_of($flow), 'left' => array());
    } else {
        $made = ws_flow_graph_from_scheduled(ws_scheduled_form_data($viewer, $row), ws_scheduled_money_metrics(), (int) $row['id']);
    }

    $saved = ws_flow_save($viewer, array(
        'name'       => mb_substr(lang(array('string' => 'Copy of {var:1}', 'vars' => (string) $row['name'])), 0, 160),
        'channel_id' => ws_scheduled_flow_home($viewer, (int) $row['channel_id']),
        'graph'      => $made['graph'],
    ));

    return array('ok' => $saved['ok'], 'error' => $saved['error'], 'flow_id' => (int) $saved['flow_id'], 'left' => $saved['ok'] ? $made['left'] : array());
}

/**
 * Edit on a scheduled action's card: the flow it is changed on. A row not
 * converted yet is converted now, so a problem fixed since the upgrade (a
 * channel restored, a template corrected) no longer keeps it off the
 * canvas. A row the flow still refuses answers what is wrong in it, which a
 * Duplicate can fix on its own canvas; the row runs on as it is
 * (ws_scheduled_execute()) until it is cancelled.
 *
 * @param array $row ws_scheduled_action()
 * @return array flow_id (0 when refused), problems (sentences), message
 */
function ws_scheduled_flow_edit($row)
{
    $id = (int) $row['id'];

    if (((int) ($row['flow_id'] ?? 0) <= 0) && ((string) ($row['anchor'] ?? '') === '')) {
        ws_scheduled_convert($id);
        $row = ws_scheduled($id);
    }

    if ($row && ((int) $row['flow_id'] > 0)) {
        return array('flow_id' => (int) $row['flow_id'], 'problems' => array(), 'message' => '');
    }

    $problems = $row ? ws_scheduled_flow_edit_problems(ws_scheduled_convert_drawing($row)['problems']) : array();

    // Nothing refused: another request holds the row's run lock just now.
    if (empty($problems)) {
        return array('flow_id' => 0, 'problems' => array(), 'message' => lang('The action is running just now. Try again in a moment.'));
    }

    return array('flow_id' => 0, 'problems' => $problems, 'message' => lang('This action cannot become a flow yet, so it runs on as it is until it is cancelled. Duplicate makes a new flow from it, where these can be fixed:'));
}

/**
 * The sentences of the problems a flow refuses, each once (pure).
 *
 * @param array[] $problems ws_flow_problems()
 * @return string[]
 */
function ws_scheduled_flow_edit_problems($problems)
{
    $texts = array();

    foreach ((array) $problems as $problem) {
        $text = trim((string) ($problem['text'] ?? ''));

        if (($text !== '') && !in_array($text, $texts, true)) {
            $texts[] = $text;
        }
    }

    return $texts;
}
