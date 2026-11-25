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
 * @return array ok, error, days, since, boxes (node, waiting, passed, failed), left (the runs that left the flow in the days), partial
 */
function ws_flow_counts($viewer, $flow, $days, $keep = null)
{
    $days = ws_flow_counts_days($days);
    $since = time() - ($days * 86400);
    $answer = array('ok' => true, 'error' => '', 'days' => $days, 'since' => $since, 'boxes' => array(), 'left' => 0, 'partial' => false);

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

    // The runs that left the flow (flow_leave.php), the same runs read.
    if (($run_ids === null) || !empty($run_ids)) {
        $answer['left'] = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs USE INDEX (idx_flow)
            WHERE flow_id = '" . (int) $flow['id'] . "' AND status = 'left' AND finished_at >= '" . $since . "'"
            . (($run_ids === null) ? '' : " AND id IN (" . implode(',', array_map('intval', $run_ids)) . ")"));
    }

    return $answer;
}

/**
 * Per run, the box's last pass in the count's state, as a run of that
 * count is listed: since (when the pass began), wake_at (a Wait's or a
 * timeout's next look) and finished_at, written as the API writes times.
 * A loop passes a box more than once; the last such pass is the one told.
 * Pure.
 *
 * @param array[]|null $rows  run_id, pass, status, ready_at, wake_at, finished_at
 * @param string       $state waiting | passed | failed
 * @return array run id => status, since, wake_at, finished_at
 */
function ws_flow_counts_at_fold($rows, $state)
{
    $status = array('waiting' => 'waiting', 'passed' => 'done', 'failed' => 'failed')[(string) $state] ?? '';
    $passes = array();
    $out = array();

    foreach ((array) $rows as $row) {
        $run_id = (int) ($row['run_id'] ?? 0);

        if (($status === '') || ($run_id <= 0) || ((string) ($row['status'] ?? '') !== $status)
            || (isset($passes[$run_id]) && ($passes[$run_id] > (int) ($row['pass'] ?? 0)))) {
            continue;
        }

        $passes[$run_id] = (int) ($row['pass'] ?? 0);
        $out[$run_id] = array(
            'status'      => $status,
            'since'       => ws_flow_time($row['ready_at'] ?? 0),
            'wake_at'     => ws_flow_time($row['wake_at'] ?? 0),
            'finished_at' => ws_flow_time($row['finished_at'] ?? 0),
        );
    }

    return $out;
}

/**
 * The box's pass of each run listed behind one of its counts
 * (ws_flow_counts_at_fold()), in one read by uq_node.
 *
 * @param array[] $runs  ws_flow_runs rows listed
 * @param string  $node  the box id
 * @param string  $state waiting | passed | failed
 * @return array run id => status, since, wake_at, finished_at
 */
function ws_flow_runs_at_node($runs, $node, $state)
{
    $ids = array_values(array_filter(array_map(function ($run) { return (int) $run['id']; }, (array) $runs)));

    if (empty($ids) || (ws_flow_runs_node_where($node, $state, 0) === '')) {
        return array();
    }

    return ws_flow_counts_at_fold(db_items("SELECT run_id, pass, status, ready_at, wake_at, finished_at FROM ws_flow_run_steps
        WHERE run_id IN (" . implode(',', $ids) . ") AND node_id = '" . e((string) $node) . "'"), $state);
}

/**
 * A contact's name as it is shown: the name, else the company, else the
 * e-mail address. Pure but for pg_contact_display_name().
 *
 * @param array $row first_name, last_name, company, email_address
 * @return string
 */
function ws_flow_contact_name($row)
{
    $name = pg_contact_display_name((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''));

    foreach (array('company', 'email_address') as $column) {
        $name = ($name !== '') ? $name : trim((string) ($row[$column] ?? ''));
    }

    return $name;
}

/**
 * The name of the contact each run listed is about, only for the contacts
 * the person may open with the contacts right (ws_flow_run_contact_readable());
 * a run about nobody, or about somebody they do not read, has no line.
 *
 * @param array   $viewer
 * @param array[] $runs ws_flow_runs rows listed
 * @return array run id => name
 */
function ws_flow_runs_about($viewer, $runs)
{
    $about = array();

    foreach ((array) $runs as $run) {
        if (((string) ($run['subject_type'] ?? '') === 'contact') && ((int) ($run['subject_id'] ?? 0) > 0)) {
            $about[(int) $run['id']] = (int) $run['subject_id'];
        }
    }

    $open = $about ? ws_viewer_contacts_open($viewer, array_values($about)) : array();
    $ids = array_values(array_filter(array_unique(array_values($about)), function ($id) use ($viewer, $open) {
        return ws_flow_run_contact_readable($viewer, $open, $id);
    }));

    if (empty($ids)) {
        return array();
    }

    $names = array();

    foreach ((array) db_items("SELECT id, first_name, last_name, company, email_address FROM contacts WHERE id IN (" . implode(',', $ids) . ")") as $row) {
        $names[(int) $row['id']] = ws_flow_contact_name($row);
    }

    $out = array();

    foreach ($about as $run_id => $contact_id) {
        if (($names[$contact_id] ?? '') !== '') {
            $out[$run_id] = $names[$contact_id];
        }
    }

    return $out;
}
