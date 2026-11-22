<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the counts of a flow's boxes: per box id, the runs waiting
 * there now and the times it passed and failed in the last days. One
 * grouped read over the flow's runs (idx_flow) and their boxes (uq_node)
 * gives every box at once, so the canvas can show them on its boxes.
 *
 * Rights as the runs list (ws_flow_runs_list()): whoever may edit the flow
 * counts every run; anybody else only the runs ws_flow_run_access() lets
 * them read, looked at one by one up to WS_FLOW_COUNTS_SCAN runs, the
 * newest first; past that the counts say they are partial.
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
 * The most runs the counts of a person who may not edit the flow look at
 * one by one for their rights; the older ones are left out.
 */
define('WS_FLOW_COUNTS_SCAN', 2000);

/**
 * The states a box's count is of, which the runs list narrows to: runs
 * waiting at the box now, its passes done and its passes failed. Pure.
 *
 * @return string[]
 */
function ws_flow_counts_states()
{
    return array('waiting', 'passed', 'failed');
}

/**
 * The days counted: 7 when none are sent, else whole days from 1 up to the
 * days ended runs are kept (WS_FLOW_RUNS_KEEP). Pure.
 *
 * @param mixed $days
 * @return int
 */
function ws_flow_counts_days($days)
{
    $keep = max(1, (int) (WS_FLOW_RUNS_KEEP / 86400));

    if (!is_numeric($days) || ((int) $days === 0)) {
        return min(7, $keep);
    }

    return max(1, min($keep, (int) $days));
}

/**
 * The grouped read of the counts: the flow's runs still going or ended
 * since the moment, by idx_flow, their boxes joined (uq_node leads with
 * run_id), grouped by box. waiting counts runs, so a box a loop passes
 * twice counts its run once; passed and failed count the passes finished
 * since the moment. Pure.
 *
 * @param int        $flow_id
 * @param int        $since   the moment counted from
 * @param int[]|null $run_ids the only runs counted; null for all
 * @return string the query, '' when there is nothing to read
 */
function ws_flow_counts_sql($flow_id, $since, $run_ids = null)
{
    $flow_id = (int) $flow_id;
    $since = max(0, (int) $since);

    if ($flow_id <= 0) {
        return '';
    }

    $narrow = '';

    if ($run_ids !== null) {
        $ids = array();

        foreach ((array) $run_ids as $id) {
            if ((int) $id > 0) {
                $ids[(int) $id] = "'" . (int) $id . "'";
            }
        }

        if (empty($ids)) {
            return '';
        }

        $narrow = " AND r.id IN (" . implode(',', $ids) . ")";
    }

    return "SELECT s.node_id,
            COUNT(DISTINCT CASE WHEN s.status = 'waiting' AND r.status IN ('running', 'waiting') THEN r.id END) AS waiting,
            SUM(CASE WHEN s.status = 'done' AND s.finished_at >= '" . $since . "' THEN 1 ELSE 0 END) AS passed,
            SUM(CASE WHEN s.status = 'failed' AND s.finished_at >= '" . $since . "' THEN 1 ELSE 0 END) AS failed
        FROM ws_flow_runs AS r USE INDEX (idx_flow)
        JOIN ws_flow_run_steps AS s ON s.run_id = r.id
        WHERE r.flow_id = '" . $flow_id . "'
            AND (r.status IN ('running', 'waiting') OR r.finished_at >= '" . $since . "')" . $narrow . "
        GROUP BY s.node_id";
}

/**
 * The grouped rows as one line per box, in whole numbers; a box with
 * nothing to count is left out. Pure.
 *
 * @param array[]|null $rows node_id, waiting, passed, failed
 * @return array[] node, waiting, passed, failed
 */
function ws_flow_counts_fold($rows)
{
    $out = array();

    foreach ((array) $rows as $row) {
        $node = (string) ($row['node_id'] ?? '');
        $line = array(
            'node'    => $node,
            'waiting' => max(0, (int) ($row['waiting'] ?? 0)),
            'passed'  => max(0, (int) ($row['passed'] ?? 0)),
            'failed'  => max(0, (int) ($row['failed'] ?? 0)),
        );

        if (($node !== '') && ($line['waiting'] + $line['passed'] + $line['failed'] > 0)) {
            $out[] = $line;
        }
    }

    return $out;
}

/**
 * The condition that narrows ws_flow_runs_list() to the runs of one count:
 * waiting at the box now (the run still going), or with a pass of it done
 * (passed) or failed since the moment. Pure.
 *
 * @param string $node  the box id
 * @param string $state waiting | passed | failed
 * @param int    $since the moment for passed and failed; 0 for any time
 * @return string the condition, '' when the box or state cannot be one
 */
function ws_flow_runs_node_where($node, $state, $since)
{
    $node = (string) $node;
    $status = array('waiting' => 'waiting', 'passed' => 'done', 'failed' => 'failed')[(string) $state] ?? '';

    // A box id is a token of at most the column's 32 characters.
    if (($status === '') || !preg_match('~^[A-Za-z0-9_.:-]{1,32}$~', $node)) {
        return '';
    }

    $where = "EXISTS (SELECT 1 FROM ws_flow_run_steps AS s WHERE s.run_id = ws_flow_runs.id AND s.node_id = '" . e($node) . "' AND s.status = '" . $status . "'";

    if (($status !== 'waiting') && ((int) $since > 0)) {
        $where .= " AND s.finished_at >= '" . (int) $since . "'";
    }

    $where .= ")";

    if ($status === 'waiting') {
        $where .= " AND status IN ('running', 'waiting')";
    }

    return $where;
}

/**
 * The counts of a flow's boxes for the person, over the last days.
 *
 * @param array         $viewer
 * @param array         $flow   ws_flow_for() row
 * @param mixed         $days   ws_flow_counts_days()
 * @param callable|null $keep   a further test a run must pass to be counted
 * @return array ok, error, days, since, boxes (node, waiting, passed, failed), partial
 */
function ws_flow_counts($viewer, $flow, $days, $keep = null)
{
    $days = ws_flow_counts_days($days);
    $since = time() - ($days * 86400);
    $answer = array('ok' => true, 'error' => '', 'days' => $days, 'since' => $since, 'boxes' => array(), 'partial' => false);

    if (!is_array($flow) || ((string) $flow['status'] === 'binned') || (ws_flow_access($viewer, $flow) === '')) {
        return array('ok' => false, 'error' => lang('That flow could not be found.')) + $answer;
    }

    $run_ids = null;

    // Who may not edit the flow counts only the runs they read: looked at
    // one by one, the newest first, as the runs list looks at them.
    if ((ws_flow_access($viewer, $flow) !== 'edit') || ($keep !== null)) {
        $run_ids = array();
        $runs = (array) db_items("SELECT id, flow_id, status, started_by, context FROM ws_flow_runs USE INDEX (idx_flow)
            WHERE flow_id = '" . (int) $flow['id'] . "' AND (status IN ('running', 'waiting') OR finished_at >= '" . $since . "')
            ORDER BY id DESC LIMIT " . (WS_FLOW_COUNTS_SCAN + 1));

        if (count($runs) > WS_FLOW_COUNTS_SCAN) {
            $answer['partial'] = true;
            $runs = array_slice($runs, 0, WS_FLOW_COUNTS_SCAN);
        }

        foreach ($runs as $run) {
            $access = ws_flow_run_access($viewer, $flow, $run);

            if (($access !== '') && (($keep === null) || $keep($run + array('access' => $access)))) {
                $run_ids[] = (int) $run['id'];
            }
        }
    }

    $sql = ws_flow_counts_sql($flow['id'], $since, $run_ids);

    if ($sql !== '') {
        $answer['boxes'] = ws_flow_counts_fold(db_items($sql));
    }

    return $answer;
}
