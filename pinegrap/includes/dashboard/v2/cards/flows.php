<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Flows card: three tiles (live flows with the paused ones, runs today
 * with the failed ones, approvals a flow waits on from the person), the run
 * going on now as up to five boxes along its path, and the last runs with
 * Approve on one waiting for the person and Try again on a failed box of a
 * flow the person owns. The buttons call the Workspace's own panel actions
 * (ws_approval_decide, ws_flow_step_mend), which check everything again.
 *
 * Only flows and runs the person reaches are read: ws_flows_list() and
 * ws_flow_run_access(), as the Flows screen reads them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The boxes of the "Now" line and the last runs listed.
 */
if (!defined('PG_DASHBOARD_V2_FLOW_NODES')) {
    define('PG_DASHBOARD_V2_FLOW_NODES', 5);
}

if (!defined('PG_DASHBOARD_V2_FLOW_RUNS')) {
    define('PG_DASHBOARD_V2_FLOW_RUNS', 3);
}

/**
 * A box's state on the card from its step row's status; todo when the run
 * has not reached it. Pure.
 *
 * @param string|null $status ws_flow_run_steps.status, null for no step
 * @return string done, waiting, failed or todo
 */
function pg_dashboard_v2_flow_node_state($status)
{
    if ($status === null) {
        return 'todo';
    }

    switch ((string) $status) {
        case 'done':
        case 'skipped':
            return 'done';
        case 'failed':
            return 'failed';
    }

    return 'waiting';
}

/**
 * The path of a run as a line of boxes: from the start box along the arrows
 * the run took, else the first arrow drawn, then a window of at most $limit
 * boxes around the first box not done. Pure.
 *
 * @param array  $graph    nodes (id, label, kind), edges (from, to)
 * @param string $start_id the start box
 * @param array  $steps    node id => step status
 * @param int    $limit
 * @return array list of id, name, state
 */
function pg_dashboard_v2_flow_path($graph, $start_id, $steps, $limit = PG_DASHBOARD_V2_FLOW_NODES)
{
    $nodes = array();
    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        $nodes[(string) $node['id']] = $node;
    }

    $path = array();
    $seen = array();
    $current = (string) $start_id;

    while (($current !== '') && isset($nodes[$current]) && !isset($seen[$current]) && (count($path) < 40)) {
        $seen[$current] = true;
        $path[] = $current;

        $next = '';
        foreach ((array) ($graph['edges'] ?? array()) as $edge) {
            if ((string) $edge['from'] !== $current) {
                continue;
            }
            $to = (string) $edge['to'];
            if ($next === '') {
                $next = $to;
            }
            // An arrow the run took wins over the first one drawn.
            if (isset($steps[$to])) {
                $next = $to;
                break;
            }
        }
        $current = $next;
    }

    $out = array();
    foreach ($path as $id) {
        $label = (string) ($nodes[$id]['label'] ?? '');
        $out[] = array(
            'id'    => $id,
            'name'  => ($label !== '') ? $label : $id,
            'state' => pg_dashboard_v2_flow_node_state(array_key_exists($id, $steps) ? $steps[$id] : null),
        );
    }

    $limit = max(1, (int) $limit);
    if (count($out) <= $limit) {
        return $out;
    }

    // The first box not done sits second in the window, so the box before
    // it shows where the run came from.
    $at = 0;
    foreach ($out as $i => $node) {
        if ($node['state'] !== 'done') {
            $at = $i;
            break;
        }
        $at = $i;
    }

    $from = max(0, min($at - 1, count($out) - $limit));

    return array_slice($out, $from, $limit);
}

/**
 * The words of a node's state, so the colour never says it alone.
 *
 * @return array state => text
 */
function pg_dashboard_v2_flow_state_texts()
{
    return array(
        'done'    => lang('done'),
        'waiting' => lang('waiting'),
        'failed'  => lang('failed'),
        'todo'    => lang('to do'),
    );
}

/**
 * A last run as a row: its flow, a toned note and the one button it may
 * offer. Pure.
 *
 * @param array    $run        id, flow_id, status, finished_at, started_at
 * @param string   $flow_name
 * @param int      $approval   the approval it waits on from the person, 0 for none
 * @param array    $failed     node_id and name of its failed box, empty for none
 * @param bool     $owner      the person owns the flow (only the owner tries again)
 * @param string   $when       the run's time in words
 * @return array id, text, note, tone, when, url, action (null or kind, label, approval_id / run_id, node_id)
 */
function pg_dashboard_v2_flow_run_row($run, $flow_name, $approval, $failed, $owner, $when)
{
    $status = (string) $run['status'];
    $url = 'workspace_flows.php?' . http_build_query(array('flow' => (int) $run['flow_id'], 'run' => (int) $run['id']));
    $action = null;

    if ($status === 'failed') {
        $note = !empty($failed['name']) ? lang(array('string' => 'Failed at {var:1}', 'vars' => $failed['name'])) : lang('Failed');
        $tone = 'bad';
        if ($owner && !empty($failed['node_id'])) {
            $action = array('kind' => 'retry', 'label' => lang('Try again'), 'run_id' => (int) $run['id'], 'node_id' => (string) $failed['node_id']);
        }
    } elseif (in_array($status, array('running', 'waiting'), true)) {
        if ((int) $approval > 0) {
            $note = lang('Waiting for your approval');
            $tone = 'warn';
            $action = array('kind' => 'approve', 'label' => lang('Approve'), 'approval_id' => (int) $approval);
        } else {
            $note = ($status === 'waiting') ? lang('Waiting') : lang('Running');
            $tone = '';
        }
    } elseif ($status === 'cancelled') {
        $note = lang('Cancelled');
        $tone = '';
    } else {
        $note = lang('Done');
        $tone = 'good';
    }

    return array(
        'id'     => (int) $run['id'],
        'text'   => (string) $flow_name,
        'note'   => $note,
        'tone'   => $tone,
        'when'   => (string) $when,
        'url'    => $url,
        'action' => $action,
    );
}

/**
 * The approvals asked of the person that open runs of these flows wait on:
 * run id => approval id.
 *
 * @param int   $user_id
 * @param int[] $run_ids
 * @return array
 */
function pg_dashboard_v2_flow_approvals($user_id, $run_ids)
{
    $out = array();
    $run_ids = array_values(array_filter(array_map('intval', $run_ids)));

    if (empty($run_ids)) {
        return $out;
    }

    $rows = (array) db_items(
        "SELECT s.run_id, s.effect
         FROM ws_flow_run_steps s
         WHERE s.run_id IN (" . implode(',', $run_ids) . ") AND s.status = 'waiting' AND s.wait_event = '" . e(WS_FLOW_APPROVAL_WAIT) . "'
         ORDER BY s.id");

    foreach ($rows as $row) {
        $approval_id = ws_flow_effect_approval_id((string) $row['effect']);

        if (($approval_id <= 0) || isset($out[(int) $row['run_id']])) {
            continue;
        }

        $pending = (int) db_value(
            "SELECT COUNT(*) FROM ws_approval_people p
             INNER JOIN ws_approvals a ON a.id = p.approval_id AND a.closed_at = 0
             WHERE p.approval_id = '" . $approval_id . "' AND p.user_id = '" . (int) $user_id . "' AND p.decision = 'pending'");

        if ($pending > 0) {
            $out[(int) $row['run_id']] = $approval_id;
        }
    }

    return $out;
}

function pg_dashboard_v2_card_flows($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $rights = pg_dashboard_workspace_rights($user);

    if ($rights === null) {
        return array('status' => 'denied');
    }

    if (!function_exists('ws_flows_ready') || !ws_flows_ready()) {
        return array('status' => 'success', 'data' => array('empty' => lang('Flows are not set up on this site yet.')));
    }

    $now = time();
    $midnight = strtotime(date('Y-m-d', $now));
    $flows = array();

    foreach (ws_flows_list($rights) as $flow) {
        $flows[(int) $flow['id']] = $flow;
    }

    if (empty($flows)) {
        return array('status' => 'success', 'data' => array('empty' => lang('No flows yet.')));
    }

    $live = 0;
    $paused = 0;
    foreach ($flows as $flow) {
        $live += ((string) $flow['status'] === 'live') ? 1 : 0;
        $paused += ((string) $flow['status'] === 'paused') ? 1 : 0;
    }

    $ids = implode(',', array_keys($flows));
    $today = db_item("SELECT COUNT(*) AS runs, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
        FROM ws_flow_runs WHERE flow_id IN (" . $ids . ") AND started_at >= " . (int) $midnight);

    // The newest runs the person reaches, open ones read first for "Now".
    $runs = array();
    foreach ((array) db_items("SELECT * FROM ws_flow_runs WHERE flow_id IN (" . $ids . ") ORDER BY id DESC LIMIT 40") as $run) {
        if (ws_flow_run_access($rights, $flows[(int) $run['flow_id']], $run) !== '') {
            $runs[] = $run;
        }
    }

    $open_ids = array();
    foreach ($runs as $run) {
        if (ws_flow_run_open($run['status'])) {
            $open_ids[] = (int) $run['id'];
        }
    }
    $approvals = pg_dashboard_v2_flow_approvals((int) $rights['id'], $open_ids);

    // Now: the newest open run, its path from the start box.
    $current = null;
    foreach ($runs as $run) {
        if (ws_flow_run_open($run['status'])) {
            $graph = ws_flow_run_graph($run);
            $start = ws_flow_trigger_node($graph);
            $steps = array();
            foreach (ws_flow_run_steps($run['id']) as $step) {
                $steps[(string) $step['node_id']] = (string) $step['status'];
            }
            $current = array(
                'flow'  => (string) $flows[(int) $run['flow_id']]['name'],
                'url'   => 'workspace_flows.php?' . http_build_query(array('flow' => (int) $run['flow_id'], 'run' => (int) $run['id'])),
                'nodes' => $start ? pg_dashboard_v2_flow_path($graph, (string) $start['id'], $steps) : array(),
            );
            break;
        }
    }

    $rows = array();
    foreach (array_slice($runs, 0, PG_DASHBOARD_V2_FLOW_RUNS) as $run) {
        $flow = $flows[(int) $run['flow_id']];
        $failed = array();

        if ((string) $run['status'] === 'failed') {
            $step = db_item("SELECT node_id FROM ws_flow_run_steps WHERE run_id = '" . (int) $run['id'] . "' AND status = 'failed' ORDER BY id DESC LIMIT 1");
            if (is_array($step)) {
                $node = ws_flow_graph_node(ws_flow_run_graph($run), $step['node_id']);
                $failed = array('node_id' => (string) $step['node_id'], 'name' => $node ? ws_flow_box_name($node) : (string) $step['node_id']);
            }
        }

        $time = ((int) $run['finished_at'] > 0) ? (int) $run['finished_at'] : (int) $run['started_at'];

        $rows[] = pg_dashboard_v2_flow_run_row($run, (string) $flow['name'], $approvals[(int) $run['id']] ?? 0, $failed,
            ws_flow_share_manager($rights, $flow),
            ($time > 0) ? get_relative_time(array('timestamp' => $time, 'format' => 'plain_text')) : '');
    }

    $waiting = count($approvals);
    $runs_today = (int) ($today['runs'] ?? 0);
    $failed_today = (int) ($today['failed'] ?? 0);

    return array('status' => 'success', 'data' => array(
        'tiles' => array(
            array('label' => lang('Live'), 'text' => pg_format_number($live, 0), 'note' => lang(array('string' => '{var:1} paused', 'vars' => pg_format_number($paused, 0))), 'tone' => ''),
            array('label' => lang('Today'), 'text' => pg_format_number($runs_today, 0),
                'note' => ($failed_today > 0) ? lang(array('string' => '{var:1} failed', 'vars' => pg_format_number($failed_today, 0))) : lang('runs'),
                'tone' => ($failed_today > 0) ? 'bad' : ''),
            array('label' => lang('Approval'), 'text' => pg_format_number($waiting, 0), 'note' => lang('waiting on you'), 'tone' => ($waiting > 0) ? 'warn' : ''),
        ),
        'now'    => $current,
        'states' => pg_dashboard_v2_flow_state_texts(),
        'rows'   => $rows,
        'labels' => array(
            'now'    => lang('Now'),
            'last'   => lang('Last runs'),
            'failed' => lang('That could not be done. Open the run to see why.'),
            'done'   => lang('Done'),
        ),
        'link'   => lang('Go to flows'),
        'url'    => 'workspace_flows.php',
    ));
}
