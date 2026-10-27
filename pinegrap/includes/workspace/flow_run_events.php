<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what a flow's run tells the webhooks: that it ended done
 * (workspace.flow.run.finished) or failed (workspace.flow.run.failed), with
 * the box its result comes from, and that one of its boxes started to wait
 * (workspace.flow.run.waiting) for a task, an approval, a moment or its
 * next try.
 *
 * Each is announced by the request whose conditional write made the move
 * (flow_runs.php): the UPDATE that ended the run, or the one that put a
 * running box to waiting, counted by its affected rows. A request that lost
 * the race, a look at a box that keeps waiting, a box that stopped half way
 * and waits again for what it began, and a run that was cancelled announce
 * nothing, so every move is told once. A run reopened by trying a failed box
 * again ends again, and that end is a move of its own.
 *
 * The run goes out as the API's run answer gives it without its boxes
 * (ws_api_flow_run_present()), less what is the reader's own (access,
 * can_cancel): one body is queued for every subscriber. Words go out only
 * for a run of a public channel - the flow's and box's names, the box's
 * sentence and its values - and even then as a reader who may not change
 * the flow reads them (ws_step_result_shown()); a run of a private channel,
 * a guest room or of no channel carries its ids and states alone, as a
 * private channel's task note does. The events are webhooks only: the
 * workspace does not record them (pg_event_workspace_events()), so no flow
 * starts or wakes on another's run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest sentence or value an event carries, in characters: as the API's run answer cuts them. */
define('WS_FLOW_EVENT_TEXT_CHARS', 1000);

/* ---------------------------------------------------------------------------
   The bodies (pure)
   --------------------------------------------------------------------------- */

/**
 * The event a run that ended in this state announces.
 *
 * @param string $state ws_flow_run_state()
 * @return string the event, '' for a state that announces no end
 */
function ws_flow_run_end_event($state)
{
    $events = array(
        'done'   => 'workspace.flow.run.finished',
        'failed' => 'workspace.flow.run.failed',
    );

    return $events[(string) $state] ?? '';
}

/**
 * Do the words of a run go out with its events? Only for a run of a public
 * channel: a private channel's or a guest room's are read by its members
 * alone, and a run of no channel by the flow's own people.
 *
 * @param array|null $channel ws_channels row of the run's channel
 * @return bool
 */
function ws_flow_run_event_words($channel)
{
    return is_array($channel) && ((string) ($channel['kind'] ?? '') === 'public');
}

/**
 * The run as an event carries it: the fields of ws_api_flow_run_present()
 * without its boxes and graph, and without access and can_cancel, which
 * depend on who reads.
 *
 * @param array $run ws_flow_runs row
 * @param int   $n   the number of the version it follows
 * @return array
 */
function ws_flow_run_event_run($run, $n)
{
    $channel_id = ws_flow_run_channel_id($run);

    return array(
        'id'          => (int) $run['id'],
        'flow_id'     => (int) $run['flow_id'],
        'version_id'  => (int) $run['version_id'],
        'version'     => (int) $n,
        'status'      => (string) $run['status'],
        'started_by'  => (int) $run['started_by'],
        'actor_id'    => (int) $run['actor_id'],
        'channel_id'  => ($channel_id > 0) ? $channel_id : null,
        'boxes_run'   => (int) $run['boxes_run'],
        'started_at'  => ws_flow_time($run['started_at']),
        'updated_at'  => ws_flow_time($run['updated_at']),
        'finished_at' => ws_flow_time($run['finished_at']),
    );
}

/**
 * The values a box kept, as a reader who may not change the flow reads them
 * (ws_api_flow_step_outputs() with $edit false): none of a change box or a
 * start-a-flow box, and of the others ints, strings cut to
 * WS_FLOW_EVENT_TEXT_CHARS and nulls.
 *
 * @param string $kind   the box's kind
 * @param mixed  $stored the box's result as kept
 * @return array
 */
function ws_flow_run_event_outputs($kind, $stored)
{
    if (in_array((string) $kind, array('change', 'start_flow'), true)) {
        return array();
    }

    $result = is_string($stored) ? json_decode($stored, true) : null;
    $out = array();

    foreach ((is_array($result['data'] ?? null) ? $result['data'] : array()) as $key => $value) {
        if (is_string($key) && (($value === null) || is_int($value) || is_string($value))) {
            $out[$key] = is_string($value) ? mb_substr($value, 0, WS_FLOW_EVENT_TEXT_CHARS) : $value;
        }
    }

    return $out;
}

/**
 * One box of the run as an event carries it, in the field names of the
 * API's box (WorkspaceFlowRunStep): its name, sentence and values only with
 * the run's words.
 *
 * @param array $step  ws_flow_run_steps row
 * @param array $graph the version the run follows
 * @param bool  $words ws_flow_run_event_words()
 * @return array
 */
function ws_flow_run_event_box($step, $graph, $words)
{
    $node = ws_flow_graph_node($graph, $step['node_id']);
    $kind = $node ? (string) $node['kind'] : '';
    $task_id = ws_flow_effect_task_id((string) $step['effect']);

    return array(
        'node_id'     => (string) $step['node_id'],
        'kind'        => $kind,
        'name'        => $words ? ($node ? ws_flow_box_name($node) : (string) $step['node_id']) : null,
        'status'      => (string) $step['status'],
        'outcome'     => ((string) $step['outcome'] !== '') ? (string) $step['outcome'] : null,
        'task_id'     => ($task_id > 0) ? $task_id : null,
        'attempt'     => (int) $step['attempt'],
        'finished_at' => ws_flow_time($step['finished_at']),
        'outputs'     => $words ? (object) ws_flow_run_event_outputs($kind, $step['result']) : null,
        'result'      => $words ? mb_substr(ws_step_result_shown($kind, (string) $step['outcome'], ws_flow_result_text($step['result']), false), 0, WS_FLOW_EVENT_TEXT_CHARS) : null,
    );
}

/**
 * The box a run's result comes from. A failed run: the box that failed
 * first, the one its owner's notice names. A done run: the box that
 * finished last on done, an end box passed over (its sentence only says the
 * flow reached it), and the start box only when no other box ran. Ties go
 * to the box written later.
 *
 * @param string  $state done | failed
 * @param array[] $rows  ws_flow_run_steps rows
 * @param array   $graph the version the run follows
 * @return array|null the row, null when no box qualifies
 */
function ws_flow_run_result_step($state, $rows, $graph)
{
    $best = null;
    $start = null;
    $kinds = ws_flow_kinds();

    foreach ((array) $rows as $row) {
        if ((string) $state === 'failed') {
            if (((string) $row['status'] === 'failed') && (($best === null) || ws_flow_run_step_later($best, $row))) {
                $best = $row;
            }

            continue;
        }

        if ((string) $row['status'] !== 'done') {
            continue;
        }

        $node = ws_flow_graph_node($graph, $row['node_id']);
        $kind = $node ? (string) $node['kind'] : '';

        if ($kind === 'end') {
            continue;
        }

        if (!empty($kinds[$kind]['trigger'])) {
            $start = (($start === null) || ws_flow_run_step_later($row, $start)) ? $row : $start;
            continue;
        }

        $best = (($best === null) || ws_flow_run_step_later($row, $best)) ? $row : $best;
    }

    return ($best !== null) ? $best : $start;
}

/**
 * Did box $a finish after box $b? By finished_at, then by the order the
 * rows were written.
 *
 * @param array $a ws_flow_run_steps row
 * @param array $b ws_flow_run_steps row
 * @return bool
 */
function ws_flow_run_step_later($a, $b)
{
    if ((int) $a['finished_at'] !== (int) $b['finished_at']) {
        return (int) $a['finished_at'] > (int) $b['finished_at'];
    }

    return (int) $a['id'] > (int) $b['id'];
}

/**
 * What a waiting box waits for, read from the key it waits on: a task, an
 * approval request, a moment (a delay box) or its next try (a step box that
 * failed and has tries left).
 *
 * @param array $step ws_flow_run_steps row, waiting
 * @return array for (task | approval | delay | retry | ''), task_id, approval_id, until
 */
function ws_flow_run_waiting_for($step)
{
    $key = (string) ($step['wait_key'] ?? '');
    $out = array('for' => '', 'task_id' => null, 'approval_id' => null, 'until' => null);

    if (ws_flow_effect_task_id($key) > 0) {
        $out['for'] = 'task';
        $out['task_id'] = ws_flow_effect_task_id($key);
    } elseif (ws_flow_effect_approval_id($key) > 0) {
        $out['for'] = 'approval';
        $out['approval_id'] = ws_flow_effect_approval_id($key);
    } elseif (ws_flow_delay_key_until($key) > 0) {
        $out['for'] = 'delay';
        $out['until'] = ws_flow_time(ws_flow_delay_key_until($key));
    } elseif (ws_flow_retry_key_until($key) > 0) {
        $out['for'] = 'retry';
        $out['until'] = ws_flow_time(ws_flow_retry_key_until($key));
    }

    return $out;
}

/**
 * The body of a run's event: the run's id, its channel (what a subscription
 * narrowed to a group of channels is matched by,
 * ws_webhook_event_channel_id()), its flow and the run, then what the event
 * is about ($about: result for an end, waiting for a wait).
 *
 * @param array $run_fields ws_flow_run_event_run()
 * @param array $context    ws_flow_run_context()
 * @param bool  $words      ws_flow_run_event_words()
 * @param array $about      key => value
 * @return array
 */
function ws_flow_run_event_body($run_fields, $context, $words, $about)
{
    $name = (string) ($context['flow_name'] ?? '');

    return array(
        'id'         => (int) $run_fields['id'],
        'channel_id' => $run_fields['channel_id'],
        'flow_id'    => (int) $run_fields['flow_id'],
        'flow_name'  => ($words && ($name !== '')) ? $name : null,
        'run'        => $run_fields,
    ) + $about;
}

/* ---------------------------------------------------------------------------
   Announcing
   --------------------------------------------------------------------------- */

/**
 * The run as its event needs it, read again: the row as the write that
 * moved it left it, its version's number and graph, and whether its words
 * go out.
 *
 * @param int $run_id
 * @return array|null run, fields, graph, words, context; null for a run that is gone
 */
function ws_flow_run_event_read($run_id)
{
    $run = ws_flow_run((int) $run_id);

    if (!$run) {
        return null;
    }

    $version = ws_flow_version($run['version_id']);
    $channel_id = ws_flow_run_channel_id($run);

    return array(
        'run'     => $run,
        'fields'  => ws_flow_run_event_run($run, is_array($version) ? (int) $version['n'] : 0),
        'graph'   => ws_flow_run_graph($run),
        'words'   => ws_flow_run_event_words(($channel_id > 0) ? ws_channel($channel_id) : null),
        'context' => ws_flow_run_context($run),
    );
}

/**
 * Announces that a run ended done or failed. Called only by the request
 * whose UPDATE ended it, beside its owner's notice.
 *
 * @param int    $run_id
 * @param string $state done | failed
 * @return bool whether it was announced
 */
function ws_flow_run_announce_end($run_id, $state)
{
    $event = ws_flow_run_end_event($state);

    if (($event === '') || !function_exists('pg_announce')) {
        return false;
    }

    $read = ws_flow_run_event_read($run_id);

    if (!$read || ((string) $read['run']['status'] !== (string) $state)) {
        return false;
    }

    $step = ws_flow_run_result_step($state, ws_flow_run_steps($run_id), $read['graph']);
    $about = array('result' => $step ? ws_flow_run_event_box($step, $read['graph'], $read['words']) : null);

    // Each name written out, so a reader of the source finds where it is
    // announced (ws_flow_run_end_event() says which state is which).
    if ($event === 'workspace.flow.run.failed') {
        pg_announce('workspace.flow.run.failed', ws_flow_run_event_body($read['fields'], $read['context'], $read['words'], $about));
    } else {
        pg_announce('workspace.flow.run.finished', ws_flow_run_event_body($read['fields'], $read['context'], $read['words'], $about));
    }

    return true;
}

/**
 * Announces that a box of a run started to wait. Called only by the request
 * whose UPDATE put the box from running to waiting, after the run was
 * walked. A box that no longer waits on what it started on (woken or let go
 * by another request at that moment), or a run that ended meanwhile, is not
 * announced: the wait is over before anybody could act on it. The run's
 * status says whether the rest of it waits too or still runs.
 *
 * @param int    $step_id  ws_flow_run_steps id
 * @param string $wait_key the key the box started to wait on
 * @return bool whether it was announced
 */
function ws_flow_run_announce_waiting($step_id, $wait_key)
{
    if (!function_exists('pg_announce')) {
        return false;
    }

    $step = db_item("SELECT * FROM ws_flow_run_steps WHERE id = '" . (int) $step_id . "'");

    if (!is_array($step) || ((string) $step['status'] !== 'waiting') || ((string) $step['wait_key'] !== (string) $wait_key)) {
        return false;
    }

    $read = ws_flow_run_event_read((int) $step['run_id']);

    if (!$read || !ws_flow_run_open($read['run']['status'])) {
        return false;
    }

    // A waiting box has neither an outcome nor an end yet, and the task it
    // waits for is named by what it waits for.
    $box = ws_flow_run_event_box($step, $read['graph'], $read['words']);
    unset($box['finished_at'], $box['outcome'], $box['task_id']);

    pg_announce('workspace.flow.run.waiting', ws_flow_run_event_body($read['fields'], $read['context'], $read['words'], array(
        'waiting' => $box + ws_flow_run_waiting_for($step),
    )));

    return true;
}
