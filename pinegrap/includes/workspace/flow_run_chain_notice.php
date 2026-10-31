<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - one inbox line for a chain of flow runs. A run another run's
 * box started (ws_flow_chain_start()) belongs to the chain of the run that
 * started it all, its root: a manual, scheduled or event-started run. Each
 * run of the chain that ends writes no line of its own; it updates the
 * root's unread flow_finished line, which counts the runs it started and
 * says how the chain stands. A failure anywhere turns the line into the
 * failure line, which then opens the failed run. A line already read is
 * left alone: the next run that ends writes a new one, as a note changed
 * again does (ws_notes_tell_updated()).
 *
 * The line is "sent again" when updated: its created_at moves, so an app
 * reading GET /workspace/inbox?changed_since= gets it once more. A device is
 * woken for a new line and for the turn to failure, never for a count.
 *
 * Only runs of flows with the root's owner fold into the line: a chained
 * flow somebody else owns tells its owner on a line per run, as before.
 *
 * The start box's tell choice (every run, only failures, never) decides
 * whether a run's end is told (ws_flow_run_tell_wanted()): the root's choice
 * for a run that goes well, a flow set to never stays out of the line.
 *
 * The root's id is written into a chained run's context as it is started
 * (chain_root); a run started before that walks its start keys
 * (flow:<run>-<box>-<pass>) up to the root. No schema: the line is the
 * inbox row and its notice (ws_scheduled_notices), whose action_id is the
 * run the line opens and whose created_at is the moment the line began.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How many of the owner's unread flow lines are looked through for a chain's line. */
define('WS_FLOW_CHAIN_NOTICE_LOOK', 50);

/** How many start keys a root is looked for through: more than a chain of runs may hold. */
define('WS_FLOW_CHAIN_ROOT_WALK', 16);

/* ---------------------------------------------------------------------------
   The decisions (pure)
   --------------------------------------------------------------------------- */

/**
 * The run whose box started this one, read from its start key
 * (ws_flow_chain_key()); 0 for a run started any other way. Pure.
 *
 * @param string $start_key
 * @return int
 */
function ws_flow_chain_parent_run($start_key)
{
    return preg_match('~^flow:(\d+)-~', (string) $start_key, $match) ? (int) $match[1] : 0;
}

/**
 * The root of a run's chain: the run itself when no run's box started it,
 * else the root its context keeps, else the run its start keys lead up to.
 * A run swept on the way ends the walk at the furthest run still known.
 * Pure but for $lookup.
 *
 * @param array    $run    ws_flow_runs row: id, start_key, context
 * @param callable $lookup function (int $id): ws_flow_runs row or null
 * @return int
 */
function ws_flow_chain_root($run, $lookup)
{
    $id = (int) $run['id'];

    for ($i = 0; $i < WS_FLOW_CHAIN_ROOT_WALK; $i++) {
        $context = json_decode((string) ($run['context'] ?? ''), true);

        if (is_array($context) && ((int) ($context['chain_root'] ?? 0) > 0)) {
            return (int) $context['chain_root'];
        }

        $parent_id = ws_flow_chain_parent_run($run['start_key'] ?? '');
        $parent = ($parent_id > 0) ? $lookup($parent_id) : null;

        if (!is_array($parent)) {
            return ($parent_id > 0) ? $parent_id : $id;
        }

        $run = $parent;
        $id = (int) $parent['id'];
    }

    return $id;
}

/**
 * Whether a run's end folds into its root's line: a run of the chain other
 * than the root, of a flow whose owner owns the root's flow too. Pure.
 *
 * @param int $run_id
 * @param int $root_id
 * @param int $owner_id      the owner of the run's flow
 * @param int $root_owner_id the owner of the root's flow, 0 when it is gone
 * @return bool
 */
function ws_flow_chain_folds($run_id, $root_id, $owner_id, $root_owner_id)
{
    return ((int) $root_id > 0) && ((int) $root_id !== (int) $run_id)
        && ((int) $root_owner_id > 0) && ((int) $owner_id === (int) $root_owner_id);
}

/**
 * Whether a run's end is told at all, by the tell choices
 * (ws_flow_tell_of()) of its own flow and of its chain's root. A flow set to
 * never is never told and stays out of a chain's line, even when it failed.
 * Any other failure is told: alone, or as the chain's line turned to
 * failure. A run that went well is told by the choice of the line it lands
 * on: its own alone, the root's when it folds into the root's line; only
 * every run tells it. Pure.
 *
 * @param string $own    the run's own flow's choice
 * @param string $root   the root's flow's choice (the own one for a run alone)
 * @param string $state  done | failed
 * @param bool   $folded whether the run folds into its root's line
 * @return bool
 */
function ws_flow_run_tell_wanted($own, $root, $state, $folded)
{
    if ((string) $own === 'never') {
        return false;
    }

    if ((string) $state === 'failed') {
        return true;
    }

    return ((string) ($folded ? $root : $own) === 'every');
}

/**
 * What the end of a run of a chain does to the chain's line: writes it
 * (no unread line), or updates it. A device is woken for a new line and
 * for the turn to failure. The line opens the root, or the failed run once
 * one failed; the first failure stays what it opens. Pure.
 *
 * @param array|null $line   the unread line: failed (it opens a failed run); null for none
 * @param string     $state  done | failed, the run's
 * @return array write (new | update), push (bool), point (root | this | keep)
 */
function ws_flow_chain_notice_plan($line, $state)
{
    $failed = ((string) $state === 'failed');

    if (!is_array($line)) {
        return array('write' => 'new', 'push' => true, 'point' => $failed ? 'this' : 'root');
    }

    if ($failed && empty($line['failed'])) {
        return array('write' => 'update', 'push' => true, 'point' => 'this');
    }

    return array('write' => 'update', 'push' => false, 'point' => 'keep');
}

/**
 * The sentence of a chain's line. With no run of the chain counted it is
 * the root's own sentence, as a run's line always said. Pure.
 *
 * @param string     $root_name the root's flow
 * @param int        $root_id
 * @param int        $count     the runs the root's chain started that ended on this line
 * @param array|null $failure   run, flow, box, text of the failed run; null for none
 * @return string
 */
function ws_flow_chain_notice_text($root_name, $root_id, $count, $failure)
{
    $count = (int) $count;

    if ($count <= 0) {
        return is_array($failure)
            ? lang(array('string' => 'Flow “{var:1}”, run {var:2}, failed at the box {var:3}: {var:4}', 'vars' => array($root_name, (int) $failure['run'], $failure['box'], $failure['text'])))
            : lang(array('string' => 'Flow “{var:1}”, run {var:2}, is done.', 'vars' => array($root_name, (int) $root_id)));
    }

    if (!is_array($failure)) {
        return ($count === 1)
            ? lang(array('string' => 'Flow “{var:1}” and one flow it started: done.', 'vars' => $root_name))
            : lang(array('string' => 'Flow “{var:1}” and {var:2} flows it started: done.', 'vars' => array($root_name, $count)));
    }

    $vars = array($root_name, (int) $failure['run'], $failure['flow'], $failure['box'], $failure['text']);

    return ($count === 1)
        ? lang(array('string' => 'Flow “{var:1}” and one flow it started: run {var:2} of “{var:3}” failed at the box {var:4}: {var:5}', 'vars' => $vars))
        : lang(array('string' => 'Flow “{var:1}” and {var:2} flows it started: run {var:3} of “{var:4}” failed at the box {var:5}: {var:6}',
            'vars' => array_merge(array($root_name, $count), array_slice($vars, 1))));
}

/* ---------------------------------------------------------------------------
   Reading and writing the line
   --------------------------------------------------------------------------- */

/**
 * The root of a run's chain, its start keys read from the table.
 *
 * @param array $run ws_flow_runs row
 * @return int
 */
function ws_flow_run_chain_root_id($run)
{
    return ws_flow_chain_root($run, function ($id) {
        return ws_flow_run($id);
    });
}

/**
 * Where a failed run stopped: the run, its flow, the first box that failed
 * and that box's sentence.
 *
 * @param array      $run
 * @param array|null $flow its flow
 * @return array run, flow, box, text
 */
function ws_flow_run_failure($run, $flow)
{
    $failed = db_item("SELECT node_id, result FROM ws_flow_run_steps
        WHERE run_id = '" . (int) $run['id'] . "' AND status = 'failed' ORDER BY finished_at, id LIMIT 1");
    $node = is_array($failed) ? ws_flow_graph_node(ws_flow_run_graph($run), $failed['node_id']) : null;

    return array(
        'run'  => (int) $run['id'],
        'flow' => $flow ? (string) $flow['name'] : (string) (ws_flow_run_context($run)['flow_name'] ?? ''),
        'box'  => $node ? ws_flow_box_name($node) : (is_array($failed) ? (string) $failed['node_id'] : '?'),
        'text' => is_array($failed) ? ws_flow_result_text($failed['result']) : '',
    );
}

/**
 * The owner's unread line of a chain: the latest unread flow line whose run
 * is the root or a run of its chain.
 *
 * @param int $owner_id
 * @param int $root_id
 * @return array|null id, action_id, since, failed
 */
function ws_flow_chain_notice_line($owner_id, $root_id)
{
    $rows = db_items("SELECT i.id, n.action_id, n.created_at AS since, r.status, r.start_key, r.context
        FROM ws_inbox i
        JOIN ws_scheduled_notices n ON n.inbox_id = i.id
        JOIN ws_flow_runs r ON r.id = n.action_id
        WHERE i.user_id = '" . (int) $owner_id . "' AND i.kind = 'flow_finished' AND i.read_at = 0 AND n.action_id > 0
        ORDER BY i.id DESC LIMIT " . WS_FLOW_CHAIN_NOTICE_LOOK);

    foreach ((array) $rows as $row) {
        $run = array('id' => (int) $row['action_id'], 'start_key' => $row['start_key'], 'context' => $row['context']);

        if (((int) $row['action_id'] === (int) $root_id) || (ws_flow_run_chain_root_id($run) === (int) $root_id)) {
            return array('id' => (int) $row['id'], 'action_id' => (int) $row['action_id'], 'since' => (int) $row['since'], 'failed' => ((string) $row['status'] === 'failed'));
        }
    }

    return null;
}

/**
 * How many runs of the root's chain, of flows with this owner, ended on the
 * line since it began; the run ending now always counts, a run of a flow
 * set to never tell does not (its graph read per version, few in a chain).
 *
 * @param int   $owner_id
 * @param array $root_run
 * @param int   $run_id the run ending now
 * @param int   $since  the line's beginning
 * @return int
 */
function ws_flow_chain_notice_count($owner_id, $root_run, $run_id, $since)
{
    $root_id = (int) $root_run['id'];

    // The context is JSON written once, chain_root its last key
    // (ws_flow_chain_context()).
    $rows = db_items("SELECT r.id, r.version_id FROM ws_flow_runs r
        JOIN ws_flows f ON f.id = r.flow_id
        WHERE f.owner_id = '" . (int) $owner_id . "' AND r.depth > 0 AND r.status IN ('done', 'failed')
        AND r.started_at >= '" . (int) ($root_run['started_at'] ?? 0) . "'
        AND (r.id = '" . (int) $run_id . "' OR (r.finished_at >= '" . (int) $since . "'
            AND (r.context LIKE '%\"chain_root\":" . $root_id . "}' OR r.context LIKE '%\"chain_root\":" . $root_id . ",%')))");
    $count = 0;

    foreach ((array) $rows as $row) {
        if (((int) $row['id'] === (int) $run_id) || (ws_flow_tell_of(ws_flow_run_graph($row)) !== 'never')) {
            $count++;
        }
    }

    return $count;
}

/**
 * Tells the owner that a run of a chain ended, on the chain's one line.
 * Answers null for a run that does not fold into its root's line (a flow of
 * another owner): it gets a line of its own.
 *
 * @param array  $run
 * @param string $state    done | failed
 * @param array  $flow     the run's flow
 * @param int    $owner_id its owner
 * @return int|null the inbox row, 0 when nobody was told, null when not folded
 */
function ws_flow_run_tell_chain($run, $state, $flow, $owner_id)
{
    if (!ws_scheduled_chains_ready()) {
        return null;
    }

    $run_id = (int) $run['id'];
    $root_id = ws_flow_run_chain_root_id($run);
    $root_run = ($root_id === $run_id) ? $run : ws_flow_run($root_id);
    $root_flow = ($root_id === $run_id) ? $flow : ($root_run ? ws_flow($root_run['flow_id']) : null);

    if (($root_id !== $run_id) && !ws_flow_chain_folds($run_id, $root_id, $owner_id, $root_flow ? (int) $root_flow['owner_id'] : 0)) {
        return null;
    }

    // Each choice is read from the version the run used: the owner
    // publishes it, so a member's draft never changes what is told.
    $own_tell = ws_flow_tell_of(ws_flow_run_graph($run));
    $root_tell = ($root_id === $run_id) ? $own_tell : ws_flow_tell_of(ws_flow_run_graph($root_run));

    if (!ws_flow_run_tell_wanted($own_tell, $root_tell, $state, $root_id !== $run_id)) {
        return 0;
    }

    $root_name = (string) $root_flow['name'];

    $compose = function ($line) use ($run, $state, $flow, $owner_id, $root_run, $root_id, $root_name, $run_id) {
        $plan = ws_flow_chain_notice_plan($line ? array('failed' => $line['failed']) : null, $state);
        $point = ($plan['point'] === 'root') ? $root_id : (($plan['point'] === 'this') ? $run_id : (int) $line['action_id']);
        $since = $line ? (int) $line['since'] : time();

        if ($point === $run_id) {
            $failure = ($state === 'failed') ? ws_flow_run_failure($run, $flow) : null;
        } else {
            $failed_run = ($line && $line['failed']) ? ws_flow_run($point) : null;
            $failure = $failed_run ? ws_flow_run_failure($failed_run, ws_flow($failed_run['flow_id'])) : null;
        }

        $count = ws_flow_chain_notice_count($owner_id, $root_run, $run_id, $since);

        return $plan + array('action_id' => $point, 'since' => $since, 'text' => mb_substr(ws_flow_chain_notice_text($root_name, $root_id, $count, $failure), 0, 1000));
    };

    $line = ws_flow_chain_notice_line($owner_id, $root_id);

    if ($line) {
        $out = $compose($line);

        // Updated only while unread: a line read meanwhile is left as the
        // person read it, and a new one is written below.
        $sent = ws_inbox_send_again($owner_id, $line['id'], $out['push'], function () use ($line, $out) {
            db("UPDATE ws_scheduled_notices SET action_id = '" . (int) $out['action_id'] . "', text = '" . e($out['text']) . "'
                WHERE inbox_id = '" . (int) $line['id'] . "'");
        });

        if ($sent) {
            return (int) $line['id'];
        }
    }

    $out = $compose(null);
    $inbox_id = ws_notify($owner_id, 'flow_finished', array('channel_id' => 0, 'message_id' => 0, 'actor_id' => 0, 'separate' => true));

    if ($inbox_id > 0) {
        db("REPLACE INTO ws_scheduled_notices (inbox_id, action_id, text, created_at)
            VALUES ('" . (int) $inbox_id . "', '" . (int) $out['action_id'] . "', '" . e($out['text']) . "', '" . (int) $out['since'] . "')");
    }

    return $inbox_id;
}
