<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - leaving a flow: rules kept on the flow's graph ("leave"), each
 * one event that names a person or a record. A run about a record (its
 * subject_type and subject_id) ends when such an event arrives for the same
 * contact or record: its open boxes are skipped and its status becomes
 * "left", neither completed nor failed. A run keeps the rules of the version
 * it follows; a run about no record never leaves.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most leave rules a flow holds. */
define('WS_FLOW_MAX_LEAVE', 10);

/** The most open runs one event ends, per thing it names. */
define('WS_FLOW_LEAVE_RUNS', 200);

/* ---------------------------------------------------------------------------
   The rules (pure)
   --------------------------------------------------------------------------- */

/**
 * The leave rules as the graph keeps them: each {event}, an event's name
 * once, in the order written; anything else dropped. Pure.
 *
 * @param mixed $leave
 * @return array[] event
 */
function ws_flow_leave_normalize($leave)
{
    $out = array();
    $seen = array();

    foreach (is_array($leave) ? $leave : array() as $rule) {
        $event = is_array($rule) ? trim((string) ($rule['event'] ?? '')) : '';

        if (!preg_match('/^[a-z][a-z0-9_.]{0,47}$/', $event) || isset($seen[$event])) {
            continue;
        }

        $seen[$event] = true;
        $out[] = array('event' => $event);
    }

    return $out;
}

/**
 * The events a run may leave on, from the catalogue: those about a person
 * or a record. Left out: an event about nothing or a channel, one kept only
 * while a start names it (it would never arrive), and the workspace's own,
 * kept only while something waits for them. Pure.
 *
 * @param array    $catalog   ws_events_catalog()
 * @param string[] $on_demand pg_event_workspace_on_demand()
 * @param string[] $own       ws_events_own()
 * @return string[]
 */
function ws_flow_leave_offered($catalog, $on_demand, $own)
{
    $out = array();

    foreach ((array) $catalog as $event => $info) {
        $record = (string) ($info['record'] ?? '');

        if (($record === '') || ($record === 'channel') || in_array((string) $event, (array) $on_demand, true) || in_array((string) $event, (array) $own, true)) {
            continue;
        }

        $out[] = (string) $event;
    }

    return $out;
}

/**
 * What an event names, as a run's subject is kept: the contact of its
 * person first (the record's own when it is a contact, the payload's
 * contact_id, else the one $contact_of finds for the record), then the
 * record. Pure but for $contact_of.
 *
 * @param string   $event
 * @param array    $payload
 * @param callable $contact_of (type, id) => contact id, 0 for none
 * @return array[] [type, id] pairs, none for an event about nothing
 */
function ws_flow_leave_subjects($event, $payload, $contact_of)
{
    $payload = (array) $payload;
    list($type, $id) = ws_events_record_of((string) $event, $payload);
    $id = (int) $id;

    if (($type === '') || ($id <= 0)) {
        return array();
    }

    if ($type === 'contact') {
        return array(array('contact', $id));
    }

    $contact = (int) ($payload['contact_id'] ?? 0);

    if ($contact <= 0) {
        $contact = (int) call_user_func($contact_of, (string) $type, $id);
    }

    return ($contact > 0) ? array(array('contact', $contact), array((string) $type, $id)) : array(array((string) $type, $id));
}

/**
 * The subject a run an event started is about: the first thing the event
 * names, ['', 0] for none. Pure but for $contact_of.
 *
 * @param string   $event
 * @param array    $payload
 * @param callable $contact_of
 * @return array type, id
 */
function ws_flow_leave_run_subject($event, $payload, $contact_of)
{
    if ((string) $event === '') {
        return array('', 0);
    }

    $subjects = ws_flow_leave_subjects($event, $payload, $contact_of);

    return empty($subjects) ? array('', 0) : $subjects[0];
}

/**
 * Which rule of a run an event matches: the first naming it, -1 for none.
 * An event a flow's box caused (it carries a chain depth) matches none, so
 * a run never ends itself through what it does. Pure.
 *
 * @param mixed  $leave   the rules of the run's version
 * @param string $event
 * @param array  $payload
 * @return int
 */
function ws_flow_leave_rule($leave, $event, $payload)
{
    if ((int) ($payload['_depth'] ?? 0) > 0) {
        return -1;
    }

    foreach (ws_flow_leave_normalize($leave) as $index => $rule) {
        if ($rule['event'] === (string) $event) {
            return $index;
        }
    }

    return -1;
}

/**
 * What an open box of a run that left says: which rule ended it; a box that
 * opened a task, that the task stays; one that asked for an approval, that
 * the request stays open.
 *
 * @param string $effect the box's effect
 * @param string $label  what happened, the event's name in the catalogue
 * @return string
 */
function ws_flow_leave_result($effect, $label)
{
    if (ws_flow_effect_task_id($effect) > 0) {
        return lang(array('string' => 'The run left the flow: {var:1}. Its task stays as it is.', 'vars' => $label));
    }

    if (ws_flow_effect_approval_id($effect) > 0) {
        return lang(array('string' => 'The run left the flow: {var:1}. Its approval request stays open.', 'vars' => $label));
    }

    return lang(array('string' => 'The run left the flow: {var:1}.', 'vars' => $label));
}

/* ---------------------------------------------------------------------------
   The site
   --------------------------------------------------------------------------- */

/**
 * The events offered for leaving, by name.
 *
 * @return string[]
 */
function ws_flow_leave_events()
{
    static $out = null;

    if ($out === null) {
        $out = ws_flow_leave_offered(ws_events_catalog(),
            function_exists('pg_event_workspace_on_demand') ? pg_event_workspace_on_demand() : array(),
            function_exists('ws_events_own') ? ws_events_own() : array());
    }

    return $out;
}

/**
 * The events the canvas offers the person for leaving: those they may wait
 * for, with their names.
 *
 * @param array $viewer
 * @return array[] [event, label]
 */
function ws_flow_leave_js($viewer)
{
    $catalog = ws_events_catalog();
    $out = array();

    foreach (ws_flow_leave_events() as $event) {
        if (!empty($viewer[$catalog[$event]['right']])) {
            $out[] = array($event, (string) $catalog[$event]['label']);
        }
    }

    return $out;
}

/**
 * The rules of a draft that name an event not offered, as problems.
 *
 * @param array $graph normalized
 * @return array[] ws_flow_problem()
 */
function ws_flow_leave_problems($graph)
{
    $problems = array();

    foreach ((array) ($graph['leave'] ?? array()) as $rule) {
        if (!in_array($rule['event'], ws_flow_leave_events(), true)) {
            $problems[] = ws_flow_problem('bad_config', null, null, lang(array('string' => 'The flow leaves on an event the site does not offer ({var:1}).', 'vars' => $rule['event'])));
        }
    }

    return $problems;
}

/**
 * The contact of a record with a person: an order's or a form's.
 *
 * @param string $type
 * @param int    $id
 * @return int 0 for none
 */
function ws_flow_leave_contact_of($type, $id)
{
    if (in_array((string) $type, array('order', 'form'), true) && ((int) $id > 0)) {
        return (int) db_value("SELECT contact_id FROM " . (((string) $type === 'order') ? 'orders' : 'forms') . " WHERE id = '" . (int) $id . "'");
    }

    return 0;
}

/**
 * Ends the open runs an event takes out of their flows (ws_events_process()):
 * the runs about what it names, found by idx_subject, whose version has a
 * leave rule for it.
 *
 * @param string $event
 * @param array  $payload
 * @return int how many runs left
 */
function ws_flow_leave($event, $payload)
{
    if (!ws_flows_ready() || !in_array((string) $event, ws_flow_leave_events(), true) || ((int) ($payload['_depth'] ?? 0) > 0)) {
        return 0;
    }

    $left = 0;
    $catalog = ws_events_catalog();
    $label = (string) ($catalog[$event]['label'] ?? $event);

    foreach (ws_flow_leave_subjects($event, (array) $payload, 'ws_flow_leave_contact_of') as $subject) {
        $runs = (array) db_items("SELECT * FROM ws_flow_runs USE INDEX (idx_subject)
            WHERE subject_type = '" . e($subject[0]) . "' AND subject_id = '" . (int) $subject[1] . "' AND status IN ('running', 'waiting')
            ORDER BY id LIMIT " . WS_FLOW_LEAVE_RUNS);

        foreach ($runs as $run) {
            $graph = ws_flow_run_graph($run);

            if ((ws_flow_leave_rule($graph['leave'] ?? array(), $event, (array) $payload) >= 0) && ws_flow_run_leave($run, $label)) {
                $left++;
            }
        }
    }

    return $left;
}

/**
 * Ends one run as left: its status, then its open boxes skipped with the
 * rule that ended it; the tasks it opened stay. The owner is not told, as
 * for a run cancelled.
 *
 * @param array  $run
 * @param string $label what happened
 * @return bool whether this request ended it
 */
function ws_flow_run_leave($run, $label)
{
    $now = time();

    db("UPDATE ws_flow_runs SET status = 'left', finished_at = '" . $now . "', updated_at = '" . $now . "'
        WHERE id = '" . (int) $run['id'] . "' AND status IN ('running', 'waiting')");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return false;
    }

    ws_flow_anchor_run_ended($run, 'left');

    $kinds = array();

    foreach (ws_flow_run_steps($run['id']) as $step) {
        if (ws_flow_step_open($step['status'])) {
            $kinds[ws_flow_leave_result((string) $step['effect'], $label)][] = (int) $step['id'];
        }
    }

    foreach ($kinds as $result => $ids) {
        db("UPDATE ws_flow_run_steps SET status = 'skipped', finished_at = '" . $now . "', result = '" . e(ws_flow_result_pack($result)) . "'
            WHERE id IN ('" . implode("', '", $ids) . "') AND status IN ('ready', 'running', 'waiting')");
    }

    db("UPDATE ws_flow_run_steps SET status = 'skipped', finished_at = '" . $now . "',
            result = '" . e(ws_flow_result_pack(ws_flow_leave_result('', $label))) . "'
        WHERE run_id = '" . (int) $run['id'] . "' AND status IN ('ready', 'running', 'waiting')");

    return true;
}

/**
 * How many runs of a flow left it in the last days.
 *
 * @param int $flow_id
 * @param int $days
 * @return int
 */
function ws_flow_left_count($flow_id, $days = 30)
{
    if (!ws_flows_ready() || ((int) $flow_id <= 0)) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM ws_flow_runs USE INDEX (idx_status)
        WHERE status = 'left' AND updated_at >= '" . (time() - ((int) $days * 86400)) . "' AND flow_id = '" . (int) $flow_id . "'");
}
