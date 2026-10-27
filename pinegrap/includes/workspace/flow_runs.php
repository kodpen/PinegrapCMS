<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the runs of a flow: a published flow started by hand, its boxes
 * carried out in the order of the arrows. A task box opens a real task and
 * waits until somebody finishes it; a step box (steps.php) posts or sends
 * as a scheduled action's step does; an end box ends its branch; a run can
 * be cancelled.
 *
 * A run (ws_flow_runs) keeps the version it started on, so publishing the
 * flow again changes nothing for the runs already going. Each box the run
 * reaches is one row of ws_flow_run_steps, unique by (run, box, pass), so two
 * requests that finish boxes at the same moment can only ever write the box
 * after them once (INSERT IGNORE). A box moves ready -> running -> waiting ->
 * done, failed or skipped, every move a conditional UPDATE whose affected
 * rows are counted: a request that loses the race does nothing.
 *
 * The walk (ws_flow_walk(), pure) decides which boxes come next: a box whose
 * arrows in have all settled becomes ready when one of them was taken (its
 * box ended on that arrow's outcome) and no box before it failed; otherwise
 * it is skipped, and the boxes only reachable through it are skipped in
 * turn. So a join after an outcome that was not taken fires once, on the
 * branch that was. A run with no open box ends: failed when a box failed,
 * done otherwise (an end box reached, or every branch ran out of arrows).
 * Its owner is told once, by the request whose UPDATE ended it.
 *
 * A run acts with the rights its flow's owner holds when each box runs, not
 * those they held when they published it: an owner who has left the staff
 * leaves runs whose boxes fail with a sentence that says so. Whoever starts
 * the run is "whoever starts the run" for a task box's rule.
 *
 * Nothing here runs by itself. ws_scheduled_run() calls ws_flow_advance() on
 * every screen tick and job run, ws_scheduled_due() reports boxes that are
 * due, and the queue of the workspace's events (watch.php) keeps the event a
 * waiting box needs and wakes the box with it (ws_flow_wake()). An event that
 * was lost is made up for: a waiting box looks at its task again every
 * WS_FLOW_RECHECK seconds. A delay box waits the same way, with its next
 * look at the moment its time is up; an approval box waits for its request
 * as a task box waits for its task.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most runs of one flow that may be open at once. */
define('WS_FLOW_MAX_OPEN_RUNS', 500);

/** The most runs one flow may be started in an hour. */
define('WS_FLOW_STARTS_HOURLY', 60);

/** The most boxes one run carries out, a box tried again counted twice. */
define('WS_FLOW_MAX_BOX_RUNS', 200);

/** The largest result one box keeps, in bytes of JSON. */
define('WS_FLOW_RESULT_BYTES', 16384);

/** Seconds a finished run is kept before it is swept (30 days). */
define('WS_FLOW_RUNS_KEEP', 2592000);

/** Seconds a box may stay running before it counts as stopped half way. */
define('WS_FLOW_LOCK', 600);

/** Seconds between two looks of a waiting box at what it waits for. */
define('WS_FLOW_RECHECK', 3600);

/** Seconds after which a screen's tick starts no further box. */
define('WS_FLOW_BUDGET_TICK', 10);

/** Seconds after which a job starts no further box. */
define('WS_FLOW_BUDGET_JOB', 25);

/** The most boxes of one run a single call carries out. */
define('WS_FLOW_BOXES_PER_RUN', 3);

/** The most boxes a single call carries out, all runs together. */
define('WS_FLOW_ADVANCE_MAX', 30);

/** The longest token a client sends to start a run once. */
define('WS_FLOW_TOKEN_MAX', 56);

/** The event a task box waits for (ws_event(), tasks.php). */
define('WS_FLOW_TASK_WAIT', 'workspace.task.status_changed');

/** The event an approval box waits for (ws_event(), approvals.php). */
define('WS_FLOW_APPROVAL_WAIT', 'workspace.approval.decided');

/** The longest a delay box waits, in minutes: a scheduled action's start delay (a week). */
define('WS_FLOW_DELAY_MAX', 10080);

/** The longest a task box waits before it leaves on timeout, in hours: as long as an approval waits for its decision (30 days). */
define('WS_FLOW_TIMEOUT_MAX', 720);

/* ---------------------------------------------------------------------------
   States and the walk (pure)
   --------------------------------------------------------------------------- */

/**
 * The states of a run.
 *
 * @return array status => label
 */
function ws_flow_run_statuses()
{
    return array(
        'running'   => lang('Running'),
        'waiting'   => lang('Waiting'),
        'done'      => lang('Done'),
        'failed'    => lang('Failed'),
        'cancelled' => lang('Cancelled'),
    );
}

/**
 * The states of a box in a run.
 *
 * @return array status => label
 */
function ws_flow_step_statuses()
{
    return array(
        'ready'   => lang('Ready'),
        'running' => lang('Running'),
        'waiting' => lang('Waiting'),
        'done'    => lang('Done'),
        'failed'  => lang('Failed'),
        'skipped' => lang('Skipped'),
    );
}

/**
 * Is the run still going?
 *
 * @param string $status
 * @return bool
 */
function ws_flow_run_open($status)
{
    return in_array((string) $status, array('running', 'waiting'), true);
}

/**
 * Has the box still something to do?
 *
 * @param string $status
 * @return bool
 */
function ws_flow_step_open($status)
{
    return in_array((string) $status, array('ready', 'running', 'waiting'), true);
}

/**
 * Has the box ended, one way or another? A settled box never changes again.
 *
 * @param string $status
 * @return bool
 */
function ws_flow_step_settled($status)
{
    return in_array((string) $status, array('done', 'failed', 'skipped'), true);
}

/**
 * A box of a graph by its id.
 *
 * @param array  $graph ws_flow_graph_normalize()
 * @param string $id
 * @return array|null
 */
function ws_flow_graph_node($graph, $id)
{
    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if ((string) $node['id'] === (string) $id) {
            return $node;
        }
    }

    return null;
}

/**
 * The box that starts a graph.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @return array|null
 */
function ws_flow_trigger_node($graph)
{
    $kinds = ws_flow_kinds();

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if (!empty($kinds[$node['kind']]['trigger'])) {
            return $node;
        }
    }

    return null;
}

/**
 * Does an arrow leave the box on this outcome?
 *
 * @param array  $graph
 * @param string $node_id
 * @param string $outcome
 * @return bool
 */
function ws_flow_outcome_routed($graph, $node_id, $outcome)
{
    foreach ((array) ($graph['edges'] ?? array()) as $edge) {
        if (((string) $edge['from'] === (string) $node_id) && ((string) $edge['on'] === (string) $outcome)) {
            return true;
        }
    }

    return false;
}

/**
 * The boxes that come next, given the boxes of the run so far. A box without
 * a step whose arrows in have all settled becomes ready when one of them was
 * taken and no box before it failed, and is skipped otherwise; a skipped box
 * settles the arrows out of it, so the walk goes on until nothing changes.
 * A start box is never made ready here: the run writes it done as it starts.
 *
 * A failure is carried through the boxes skipped behind it: a skipped box
 * with an arrow in from a failed box, or from a box skipped that way, counts
 * as failed for the boxes after it, so a join any number of boxes behind a
 * failure is skipped even when its other arrows were taken. The steps keep
 * no reason for a skip, so this is worked out again from their statuses on
 * every walk; a branch merely not taken carries no failure.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @param array $steps node id => status, outcome
 * @return array ready (node ids), skipped (node ids), in the order of the graph
 */
function ws_flow_walk($graph, $steps)
{
    $kinds = ws_flow_kinds();
    $nodes = array();
    $into = array();

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if (!isset($nodes[$node['id']])) {
            $nodes[$node['id']] = $node;
        }
    }

    foreach ((array) ($graph['edges'] ?? array()) as $edge) {
        $into[(string) $edge['to']][] = $edge;
    }

    $state = (array) $steps;
    $out = array('ready' => array(), 'skipped' => array());

    // The boxes behind which everything is skipped: the failed ones, and the
    // skipped ones an arrow from such a box leads into.
    $blocked = array();

    do {
        $grew = false;

        foreach ($state as $id => $step) {
            if (isset($blocked[$id])) {
                continue;
            }

            $status = (string) $step['status'];
            $behind = ($status === 'failed');

            if (!$behind && ($status === 'skipped')) {
                foreach ($into[$id] ?? array() as $edge) {
                    if (isset($blocked[(string) $edge['from']])) {
                        $behind = true;
                        break;
                    }
                }
            }

            if ($behind) {
                $blocked[$id] = true;
                $grew = true;
            }
        }
    } while ($grew);

    do {
        $changed = false;

        foreach ($nodes as $id => $node) {
            if (isset($state[$id]) || !empty($kinds[$node['kind']]['trigger']) || empty($into[$id])) {
                continue;
            }

            $pending = false;
            $taken = false;
            $failed = false;

            foreach ($into[$id] as $edge) {
                $from = $state[(string) $edge['from']] ?? null;

                if (($from === null) || !ws_flow_step_settled($from['status'])) {
                    $pending = true;
                    break;
                }

                if (isset($blocked[(string) $edge['from']])) {
                    $failed = true;
                } elseif (((string) $from['status'] === 'done') && ((string) $from['outcome'] === (string) $edge['on'])) {
                    $taken = true;
                }
            }

            if ($pending) {
                continue;
            }

            if ($taken && !$failed) {
                $out['ready'][] = (string) $id;
                $state[$id] = array('status' => 'ready', 'outcome' => '');
            } else {
                $out['skipped'][] = (string) $id;
                $state[$id] = array('status' => 'skipped', 'outcome' => '');
                $changed = true;

                if ($failed) {
                    $blocked[$id] = true;
                }
            }
        }
    } while ($changed);

    return $out;
}

/**
 * How a run stands: running while a box is ready or at work, or a box is
 * still to be written; waiting while its boxes only wait; done when nothing
 * is open and no box failed; failed when nothing is open and a box failed.
 *
 * @param array $graph
 * @param array $steps node id => status, outcome
 * @return string running | waiting | done | failed
 */
function ws_flow_run_state($graph, $steps)
{
    $waiting = false;
    $failed = false;

    foreach ((array) $steps as $step) {
        $status = (string) $step['status'];

        if (($status === 'ready') || ($status === 'running')) {
            return 'running';
        }

        $waiting = $waiting || ($status === 'waiting');
        $failed = $failed || ($status === 'failed');
    }

    $walk = ws_flow_walk($graph, $steps);

    if (!empty($walk['ready']) || !empty($walk['skipped'])) {
        return 'running';
    }

    if ($waiting) {
        return 'waiting';
    }

    return $failed ? 'failed' : 'done';
}

/**
 * How a task's state settles the box that opened it. Done is done. A task
 * cancelled, or gone, leaves the box on cancelled where an arrow leaves it
 * on that outcome, and fails the box where none does. A task still open
 * keeps the box waiting (status '').
 *
 * @param string|null $task_status the task's status, null when it is gone
 * @param bool        $cancel_routed an arrow leaves the box on cancelled
 * @return array status (done | failed | ''), outcome, reason (done | cancelled | gone | '')
 */
function ws_flow_task_settlement($task_status, $cancel_routed)
{
    if ($task_status === 'done') {
        return array('status' => 'done', 'outcome' => 'done', 'reason' => 'done');
    }

    if (($task_status === null) || ($task_status === 'cancelled')) {
        return array(
            'status'  => $cancel_routed ? 'done' : 'failed',
            'outcome' => 'cancelled',
            'reason'  => ($task_status === null) ? 'gone' : 'cancelled',
        );
    }

    return array('status' => '', 'outcome' => '', 'reason' => '');
}

/**
 * The hours a task box waits for its task before it leaves on timeout
 * (its timeout setting), 0 for no timeout.
 *
 * @param array $config the box's settings
 * @return int
 */
function ws_flow_timeout_hours($config)
{
    $hours = $config['timeout'] ?? '';

    return (is_int($hours) && ($hours > 0)) ? min(WS_FLOW_TIMEOUT_MAX, $hours) : 0;
}

/**
 * The moment a task box runs out: so many hours after it began (its claim),
 * 0 for a box without a timeout or without a known start.
 *
 * @param array $config the box's settings
 * @param int   $since  when the box began
 * @return int
 */
function ws_flow_timeout_at($config, $since)
{
    $hours = ws_flow_timeout_hours($config);

    return (($hours > 0) && ((int) $since > 0)) ? (int) $since + $hours * 3600 : 0;
}

/**
 * The next look of a waiting box: in WS_FLOW_RECHECK seconds, or at its
 * deadline when that comes sooner (at once when it has passed).
 *
 * @param int $now
 * @param int $timeout_at ws_flow_timeout_at(), 0 for none
 * @return int
 */
function ws_flow_timeout_next_look($now, $timeout_at)
{
    $next = (int) $now + WS_FLOW_RECHECK;

    return ((int) $timeout_at > 0) ? max((int) $now, min($next, (int) $timeout_at)) : $next;
}

/**
 * Has a box's deadline come?
 *
 * @param int $timeout_at ws_flow_timeout_at(), 0 for none
 * @param int $now
 * @return bool
 */
function ws_flow_timed_out($timeout_at, $now)
{
    return ((int) $timeout_at > 0) && ((int) $now >= (int) $timeout_at);
}

/**
 * How a task box whose deadline came settles: done on timeout where an arrow
 * leaves it on timeout, failed where none does (the run fails), as a
 * cancelled task fails a box without an arrow on cancelled.
 *
 * @param bool $routed an arrow leaves the box on timeout
 * @return array status, outcome
 */
function ws_flow_timeout_settlement($routed)
{
    return array('status' => $routed ? 'done' : 'failed', 'outcome' => 'timeout');
}

/**
 * The publish problems of a task box's timeout: an arrow on timeout from a
 * box that never runs out would never be followed.
 *
 * @param array  $config the box's settings
 * @param string $name   the box's name
 * @param bool   $routed an arrow leaves the box on timeout
 * @return string[]
 */
function ws_flow_timeout_problems($config, $name, $routed)
{
    if ($routed && (ws_flow_timeout_hours($config) === 0)) {
        return array(lang(array('string' => 'An arrow leaves the task box {var:1} on timeout, but the box has no timeout.', 'vars' => $name)));
    }

    return array();
}

/**
 * Which waiting boxes an event of the workspace is for: the key they wait on
 * (wait_key), '' for an event no box waits for.
 *
 * @param string $event
 * @param array  $payload
 * @return string
 */
function ws_flow_wake_key($event, $payload)
{
    $id = (int) ($payload['id'] ?? 0);

    if ($id <= 0) {
        return '';
    }

    if ((string) $event === WS_FLOW_APPROVAL_WAIT) {
        return ws_flow_approval_key($id);
    }

    return ((string) $event === WS_FLOW_TASK_WAIT) ? 'task:' . $id : '';
}

/**
 * The task a box's effect names (task:<id>), 0 for none.
 *
 * @param string $effect
 * @return int
 */
function ws_flow_effect_task_id($effect)
{
    return preg_match('/^task:([1-9][0-9]{0,9})$/', (string) $effect, $match) ? (int) $match[1] : 0;
}

/**
 * What a box with this effect waits for: the event and the key, null for an
 * effect nothing waits for.
 *
 * @param string $effect
 * @return array|null event, key
 */
function ws_flow_effect_wait($effect)
{
    $approval_id = ws_flow_effect_approval_id($effect);

    if ($approval_id > 0) {
        return array('event' => WS_FLOW_APPROVAL_WAIT, 'key' => ws_flow_approval_key($approval_id));
    }

    $task_id = ws_flow_effect_task_id($effect);

    return ($task_id > 0) ? array('event' => WS_FLOW_TASK_WAIT, 'key' => 'task:' . $task_id) : null;
}

/**
 * Did a step box begin its work (steps.php, WS_STEP_BEGAN)?
 *
 * @param string $effect
 * @return bool
 */
function ws_flow_effect_began($effect)
{
    return strpos((string) $effect, 'began:') === 0;
}

/**
 * What becomes of a box that stopped half way (running for longer than
 * WS_FLOW_LOCK). One whose effect is written did its work: it waits for it
 * again. A step box that began (ws_flow_effect_began()) fails, since it may
 * have posted or sent already. One without an effect is tried once more,
 * then fails; its owner may then try it again or skip it by hand
 * (ws_flow_step_mend()).
 *
 * @param array $step ws_flow_run_steps row
 * @return string resume | retry | fail
 */
function ws_flow_stale_action($step)
{
    if (ws_flow_effect_wait((string) ($step['effect'] ?? '')) !== null) {
        return 'resume';
    }

    // A step that began may have posted or sent already: never twice.
    if (ws_flow_effect_began((string) ($step['effect'] ?? ''))) {
        return 'fail';
    }

    return ((int) ($step['attempt'] ?? 0) < 2) ? 'retry' : 'fail';
}

/* ---------------------------------------------------------------------------
   The delay box (pure)
   --------------------------------------------------------------------------- */

/**
 * The units a delay box counts its time in, as minutes.
 *
 * @return int[] unit => minutes
 */
function ws_flow_delay_units()
{
    return array('minutes' => 1, 'hours' => 60, 'days' => 1440);
}

/**
 * A delay box's settings as it keeps them: amount ('' when none is set, else
 * a whole number of at least 0, held below a size the problems still name)
 * and unit (ws_flow_delay_units()).
 *
 * @param array $config
 * @return array amount, unit
 */
function ws_flow_delay_normalize($config)
{
    $amount = $config['amount'] ?? '';
    $unit = is_string($config['unit'] ?? null) ? $config['unit'] : '';

    return array(
        'amount' => (is_bool($amount) || !is_numeric($amount)) ? '' : (int) max(0, min(99999, (float) $amount)),
        'unit'   => isset(ws_flow_delay_units()[$unit]) ? $unit : 'minutes',
    );
}

/**
 * How many minutes a delay box waits, 0 when no time is set.
 *
 * @param array $config ws_flow_delay_normalize()
 * @return int
 */
function ws_flow_delay_minutes($config)
{
    $config = ws_flow_delay_normalize($config);

    return ($config['amount'] === '') ? 0 : $config['amount'] * ws_flow_delay_units()[$config['unit']];
}

/**
 * Why a delay box may not be published: no time to wait, or more than a
 * scheduled action's start may be put off (WS_FLOW_DELAY_MAX minutes).
 *
 * @param array  $config
 * @param string $name the box as the problems name it
 * @return string[]
 */
function ws_flow_delay_problems($config, $name)
{
    $minutes = ws_flow_delay_minutes($config);

    if ($minutes < 1) {
        return array(lang(array('string' => 'The delay box {var:1} needs a time to wait.', 'vars' => $name)));
    }

    if ($minutes > WS_FLOW_DELAY_MAX) {
        return array(lang(array('string' => 'The delay box {var:1} waits at most a week (10,080 minutes).', 'vars' => $name)));
    }

    return array();
}

/**
 * The time a delay box waits, in words: "2 hours".
 *
 * @param array $config
 * @return string
 */
function ws_flow_delay_text($config)
{
    $config = ws_flow_delay_normalize($config);
    $amount = (int) $config['amount'];

    switch ($config['unit']) {
        case 'days':
            return ($amount === 1) ? lang('1 day') : lang(array('string' => '{var:1} days', 'vars' => $amount));

        case 'hours':
            return ($amount === 1) ? lang('1 hour') : lang(array('string' => '{var:1} hours', 'vars' => $amount));
    }

    return ($amount === 1) ? lang('1 minute') : lang(array('string' => '{var:1} minutes', 'vars' => $amount));
}

/**
 * The moment a delay box that starts waiting at $from waits until.
 *
 * @param array $config
 * @param int   $from unix time
 * @return int
 */
function ws_flow_delay_until($config, $from)
{
    return (int) $from + ws_flow_delay_minutes($config) * 60;
}

/**
 * The key a waiting delay box keeps (wait_key and effect): delay:<until>.
 *
 * @param int $until
 * @return string
 */
function ws_flow_delay_key($until)
{
    return 'delay:' . (int) $until;
}

/**
 * The moment a delay box's key waits until, 0 for a key of another kind.
 *
 * @param string $key
 * @return int
 */
function ws_flow_delay_key_until($key)
{
    return preg_match('/^delay:([1-9][0-9]{0,11})$/', (string) $key, $match) ? (int) $match[1] : 0;
}

/**
 * How a look at a waiting delay box settles it: done once its moment has
 * come, with the moment as its output (waited_until); before it, it goes on
 * waiting (status '').
 *
 * @param string $key ws_flow_delay_key()
 * @param int    $now
 * @return array status (done | ''), outcome, data
 */
function ws_flow_delay_settlement($key, $now)
{
    $until = ws_flow_delay_key_until($key);

    if (($until <= 0) || ((int) $now < $until)) {
        return array('status' => '', 'outcome' => '', 'data' => array());
    }

    return array('status' => 'done', 'outcome' => 'done', 'data' => array('waited_until' => ws_flow_time($until)));
}

/* ---------------------------------------------------------------------------
   The approval box (pure)
   --------------------------------------------------------------------------- */

/**
 * The key an approval box waits on (effect and wait_key): approval:<id>.
 *
 * @param int $approval_id
 * @return string
 */
function ws_flow_approval_key($approval_id)
{
    return 'approval:' . (int) $approval_id;
}

/**
 * The approval request a box's effect names (approval:<id>), 0 for none.
 *
 * @param string $effect
 * @return int
 */
function ws_flow_effect_approval_id($effect)
{
    return preg_match('/^approval:([1-9][0-9]{0,9})$/', (string) $effect, $match) ? (int) $match[1] : 0;
}

/**
 * How the way a request ended settles the approval box that asked it.
 * Approved and rejected are decisions: the box is done on them, and a
 * decision no arrow leaves on ends the branch. Expired and withdrawn
 * decided nothing: the box leaves on them where an arrow does, and fails
 * where none does, as a cancelled task fails a task box. A request that is
 * gone counts as withdrawn; one still open keeps the box waiting
 * (status '').
 *
 * @param string|null $outcome what the request ended with ('' while open,
 *                             null when it is gone)
 * @param bool        $routed  an arrow leaves the box on that outcome
 * @return array status (done | failed | ''), outcome
 */
function ws_flow_approval_settlement($outcome, $routed)
{
    if ($outcome === '') {
        return array('status' => '', 'outcome' => '');
    }

    if (in_array($outcome, array('approved', 'rejected'), true)) {
        return array('status' => 'done', 'outcome' => $outcome);
    }

    $outcome = ($outcome === 'expired') ? 'expired' : 'withdrawn';

    return array('status' => $routed ? 'done' : 'failed', 'outcome' => $outcome);
}

/**
 * Why a flow takes no further start, '' when it does.
 *
 * @param int $open_runs     its runs open now
 * @param int $recent_starts its runs started in the last hour
 * @return string '' | open_runs | hourly
 */
function ws_flow_start_refusal($open_runs, $recent_starts)
{
    if ((int) $open_runs >= WS_FLOW_MAX_OPEN_RUNS) {
        return 'open_runs';
    }

    if ((int) $recent_starts >= WS_FLOW_STARTS_HOURLY) {
        return 'hourly';
    }

    return '';
}

/**
 * Is this a token a client may start a run with? It becomes the run's
 * start_key (ws_flow_start_key()), unique per flow, so the same token sent
 * twice starts one run.
 *
 * @param mixed $token
 * @return bool
 */
function ws_flow_start_token_valid($token)
{
    return is_string($token) && (preg_match('/^[A-Za-z0-9._:-]{1,' . WS_FLOW_TOKEN_MAX . '}$/', $token) === 1);
}

/**
 * The start key a client's token becomes: manual: and a hash of the token in
 * small hex letters. start_key compares without regard to letter case
 * (utf8mb4_unicode_ci), so the token itself would make "aB3x" and "Ab3X" one
 * run; its hash keeps every distinct token apart, within the VARCHAR(64).
 *
 * @param string $token ws_flow_start_token_valid()
 * @return string
 */
function ws_flow_start_key($token)
{
    return 'manual:' . sha1((string) $token);
}

/**
 * What a box keeps of how it went, as JSON: its sentence (text) and the
 * values the boxes after it may read later (data). Within
 * WS_FLOW_RESULT_BYTES: values too large are left out first, then the
 * sentence is cut, never in the middle of a character.
 *
 * @param string $text
 * @param array  $data
 * @return string
 */
function ws_flow_result_pack($text, $data = array())
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;
    $text = ws_flow_text($text);
    $out = array('text' => $text);

    if (!empty($data)) {
        $out['data'] = $data;
    }

    $json = json_encode($out, $flags);

    if (is_string($json) && (strlen($json) <= WS_FLOW_RESULT_BYTES)) {
        return $json;
    }

    $budget = WS_FLOW_RESULT_BYTES;

    do {
        $json = (string) json_encode(array('text' => mb_strcut($text, 0, $budget, 'UTF-8')), $flags);
        $budget = (int) floor($budget * 0.8);
    } while ((strlen($json) > WS_FLOW_RESULT_BYTES) && ($budget > 0));

    return $json;
}

/**
 * A moment of a run or a box as its answers carry it: ISO 8601 in UTC, null
 * for none (0). The same text api_time() writes; the presenters of a run
 * (api_flows.php) answer the canvas's panel actions too, where the external
 * API's response helpers are not loaded.
 *
 * @param int|string $unix
 * @return string|null
 */
function ws_flow_time($unix)
{
    $unix = (int) $unix;

    return ($unix > 0) ? gmdate('Y-m-d\TH:i:s\Z', $unix) : null;
}

/**
 * The sentence of a box's result; a result kept as plain text is that text.
 *
 * @param mixed $stored
 * @return string
 */
function ws_flow_result_text($stored)
{
    if (!is_string($stored) || ($stored === '')) {
        return '';
    }

    $decoded = json_decode($stored, true);

    return (is_array($decoded) && array_key_exists('text', $decoded)) ? ws_flow_text($decoded['text']) : $stored;
}

/**
 * The day a task box's task is due: so many days after the day the box
 * became ready, null for a box that gives no due date.
 *
 * @param int        $ready_at
 * @param int|string $due_in ws_flow_config_normalize(): '' or 0-3650
 * @return string|null Y-m-d
 */
function ws_flow_due_date($ready_at, $due_in)
{
    if (is_bool($due_in) || !is_numeric($due_in)) {
        return null;
    }

    return ws_template_relative_date(date('Y-m-d', (int) $ready_at), max(0, min(3650, (int) $due_in)));
}

/**
 * The description of the task a box opens: the box's own, and a last line
 * that names the flow and the run, which a long description never pushes out
 * of the 8,000 characters a task keeps. The line is how the task leads back
 * to its run, and how a box tried again finds the task it opened.
 *
 * @param string $description
 * @param string $marker
 * @return string
 */
function ws_flow_task_description($description, $marker)
{
    $marker = trim((string) $marker);
    $room = max(0, 8000 - mb_strlen($marker) - 2);
    $description = trim(mb_substr(trim((string) $description), 0, $room));

    return ($description === '') ? $marker : $description . "\n\n" . $marker;
}

/**
 * The name a box goes by in the line of its task: its label, or its id; a
 * name another box of the graph also goes by gets the box's id beside it, so
 * the line of one box never matches the task of another.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @param array $node
 * @return string
 */
function ws_flow_box_unique_name($graph, $node)
{
    $name = ws_flow_box_name($node);

    foreach ((array) ($graph['nodes'] ?? array()) as $other) {
        if (((string) $other['id'] !== (string) $node['id']) && (ws_flow_box_name($other) === $name)) {
            return $name . ' (' . (string) $node['id'] . ')';
        }
    }

    return $name;
}

/**
 * The last line of a task a box opens (ws_flow_task_description()): the flow
 * by the name its run started with, the run and the box. It leads from the
 * task back to its run, and a box tried again finds its own task by it.
 *
 * @param string $flow_name the name the run keeps in its context
 * @param int    $run_id
 * @param string $box_name  ws_flow_box_unique_name()
 * @return string
 */
function ws_flow_task_marker($flow_name, $run_id, $box_name)
{
    return lang(array('string' => 'Opened by the flow “{var:1}”, run {var:2}, at the box {var:3}.', 'vars' => array((string) $flow_name, (int) $run_id, (string) $box_name)));
}

/**
 * The context a run keeps from its start, written once: how it was started,
 * from which box, the flow's home channel and name as they were then. A draft
 * save may change the flow's home at once, so the channel a run opens its
 * tasks in, and whose readers read it, is the one it started with.
 *
 * @param array $flow    ws_flows row
 * @param array $trigger the start box
 * @return array trigger, node, channel_id, flow_name
 */
function ws_flow_run_context_new($flow, $trigger)
{
    return array(
        'trigger'    => 'manual',
        'node'       => (string) $trigger['id'],
        'channel_id' => max(0, (int) ($flow['channel_id'] ?? 0)),
        'flow_name'  => (string) ($flow['name'] ?? ''),
    );
}

/**
 * The context a run keeps (ws_flow_run_context_new()), an empty one for a
 * context that cannot be read.
 *
 * @param array $run ws_flow_runs row
 * @return array
 */
function ws_flow_run_context($run)
{
    $context = json_decode((string) ($run['context'] ?? ''), true);

    return is_array($context) ? $context : array();
}

/**
 * The channel a run opens its tasks in and is read through: its flow's home
 * as the run started, 0 for none (a personal flow, or a context without it).
 *
 * @param array $run ws_flow_runs row
 * @return int
 */
function ws_flow_run_channel_id($run)
{
    return max(0, (int) (ws_flow_run_context($run)['channel_id'] ?? 0));
}

/**
 * What a person may do with a run, from what they may do with its flow and
 * the channel the run was started in (ws_flow_run_channel_id()):
 *   edit  the flow is theirs to change: they read and cancel its runs
 *   read  they read the run's channel, and may run the flow or started this
 *         run (a run of a flow paused since); or they started it and may
 *         still run the flow while $own_run says the channel does not
 *         count (a person share lets them run it, who may not read its
 *         home, or the run has no channel: a flow with no home)
 *   ''    nothing: a run started in a channel they do not read stays out of
 *         their sight, even when the flow's home is now one they read and
 *         they started it; a view share reads the flow, not its runs
 *
 * @param string $flow_access      ws_flow_access()
 * @param bool   $is_starter       they started the run
 * @param bool   $channel_readable they read the channel the run was started in
 * @param bool   $own_run          the run has no channel, or a person share gives them run (ws_flow_run_own_reach())
 * @return string edit | read | ''
 */
function ws_flow_run_access_from($flow_access, $is_starter, $channel_readable, $own_run = false)
{
    if ($flow_access === 'edit') {
        return 'edit';
    }

    if ($is_starter && $own_run && ($flow_access === 'run')) {
        return 'read';
    }

    return ($channel_readable && (($flow_access === 'run') || $is_starter)) ? 'read' : '';
}

/**
 * May the person cancel the run? The flow's editors, and whoever started it.
 *
 * @param string $access     ws_flow_run_access()
 * @param bool   $is_starter
 * @return bool
 */
function ws_flow_run_cancel_allowed($access, $is_starter)
{
    return ($access === 'edit') || (($access === 'read') && $is_starter);
}

/**
 * Why a box of a run may not be tried again or skipped by hand, '' when it
 * may: only a failed box (one that stopped half way included), of a run that
 * is still going or ended failed. Pure.
 *
 * @param string $run_status  the run's status
 * @param string $step_status the box's status
 * @return string '' | not_failed | ended
 */
function ws_flow_step_mend_refusal($run_status, $step_status)
{
    if ((string) $step_status !== 'failed') {
        return 'not_failed';
    }

    return in_array((string) $run_status, array('running', 'waiting', 'failed'), true) ? '' : 'ended';
}

/**
 * The boxes skipped behind a box: those an arrow from it leads into that are
 * skipped, and on through the skipped boxes after them, in the order they
 * are reached. When the box is tried again or skipped by hand these are
 * taken back and walked again (ws_flow_walk() works out each one anew from
 * the boxes before it, so one also skipped for another reason is skipped
 * again). Pure.
 *
 * @param array  $graph   ws_flow_graph_normalize()
 * @param array  $steps   node id => status, outcome
 * @param string $node_id
 * @return string[]
 */
function ws_flow_skipped_behind($graph, $steps, $node_id)
{
    $out = array();
    $seen = array((string) $node_id => true);
    $queue = array((string) $node_id);

    while (!empty($queue)) {
        $from = array_shift($queue);

        foreach ((array) ($graph['edges'] ?? array()) as $edge) {
            $to = (string) $edge['to'];

            if (((string) $edge['from'] !== $from) || isset($seen[$to])) {
                continue;
            }

            if ((string) ($steps[$to]['status'] ?? '') === 'skipped') {
                $seen[$to] = true;
                $out[] = $to;
                $queue[] = $to;
            }
        }
    }

    return $out;
}

/**
 * The boxes of a run as the walk reads them; a later pass of a box stands
 * for it.
 *
 * @param array[] $rows ws_flow_run_steps rows, oldest first
 * @return array node id => status, outcome
 */
function ws_flow_step_states($rows)
{
    $out = array();

    foreach ((array) $rows as $row) {
        $out[(string) $row['node_id']] = array('status' => (string) $row['status'], 'outcome' => (string) $row['outcome']);
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   Runs
   --------------------------------------------------------------------------- */

/**
 * One run.
 *
 * @param int $id
 * @return array|null
 */
function ws_flow_run($id)
{
    if (!ws_flows_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_flow_runs WHERE id = '" . (int) $id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The boxes a run has reached, oldest first.
 *
 * @param int $run_id
 * @return array[]
 */
function ws_flow_run_steps($run_id)
{
    return (array) db_items("SELECT * FROM ws_flow_run_steps WHERE run_id = '" . (int) $run_id . "' ORDER BY id");
}

/**
 * The graph a run follows: the version it started on, which never changes.
 *
 * @param array $run
 * @return array
 */
function ws_flow_run_graph($run)
{
    static $graphs = array();

    $version_id = (int) $run['version_id'];

    if (!isset($graphs[$version_id])) {
        $version = ws_flow_version($version_id);
        $graphs[$version_id] = is_array($version) ? ws_flow_graph_of($version) : ws_flow_graph_empty();
    }

    return $graphs[$version_id];
}

/**
 * Does the person read the channel the run was started in (a public or
 * private one)? Read once a request per person and channel
 * (ws_flow_channel_readable()): a listing asks it for every run, and most of
 * a flow's runs share one channel.
 *
 * @param array $viewer
 * @param array $run
 * @return bool
 */
function ws_flow_run_channel_readable($viewer, $run)
{
    return ws_flow_channel_readable($viewer, ws_flow_run_channel_id($run));
}

/**
 * What the person may do with a run (ws_flow_run_access_from()). Nobody
 * reaches the runs of a deleted flow.
 *
 * @param array      $viewer
 * @param array|null $flow
 * @param array|null $run
 * @return string edit | read | ''
 */
function ws_flow_run_access($viewer, $flow, $run)
{
    if (!is_array($flow) || !is_array($run) || empty($viewer['member']) || ((string) $flow['status'] === 'binned') || ((int) $run['flow_id'] !== (int) $flow['id'])) {
        return '';
    }

    $flow_access = ws_flow_access($viewer, $flow);
    $starter = ((int) $run['started_by'] === (int) $viewer['id']);
    $own_run = $starter && ($flow_access === 'run') && ws_flow_run_own_reach($viewer, $flow, $run);

    // Decided without the run's channel: an editor, the starter whose own
    // run the channel does not decide, and anybody else who may not run it.
    if (($flow_access === 'edit') || $own_run || (!$starter && ($flow_access !== 'run'))) {
        return ws_flow_run_access_from($flow_access, $starter, false, $own_run);
    }

    return ws_flow_run_access_from($flow_access, $starter, ws_flow_run_channel_readable($viewer, $run));
}

/**
 * Does the channel leave a starter's own run alone? When the run has none
 * (its flow had no home), or when a share made to the person lets them run
 * the flow: they may not read its home, and their run is theirs to follow.
 * Running it through the home alone does not: a run started in a channel
 * they were taken out of since stays out of their sight.
 *
 * @param array $viewer
 * @param array $flow
 * @param array $run
 * @return bool
 */
function ws_flow_run_own_reach($viewer, $flow, $run)
{
    if (ws_flow_run_channel_id($run) <= 0) {
        return true;
    }

    $person = (string) ws_flow_share_reach($viewer, $flow)['person'];

    return ($person !== '') && (ws_flow_access_rank(ws_flow_share_gives('user', $person, ws_can_schedule($viewer))) >= ws_flow_access_rank('run'));
}

/**
 * A run the person reaches, with access and its flow.
 *
 * @param array $viewer
 * @param int   $run_id
 * @return array|null the row, with access and flow
 */
function ws_flow_run_for($viewer, $run_id)
{
    $run = ws_flow_run($run_id);
    $flow = $run ? ws_flow($run['flow_id']) : null;
    $access = ws_flow_run_access($viewer, $flow, $run);

    if ($access === '') {
        return null;
    }

    $run['access'] = $access;
    $run['flow'] = $flow;

    return $run;
}

/**
 * The most runs one page of a listing reads past the ones it shows: runs
 * started in a channel the person does not read are left out, and the page
 * then ends early with a cursor to read on from.
 */
define('WS_FLOW_LIST_SCAN', 500);

/**
 * The runs of a flow the person reaches, newest first, a page at a time,
 * each with its access (ws_flow_run_access()): its owner reads them all,
 * anybody else those started in a channel they read. A page may be shorter
 * than the limit and still have a next: rows left out are passed over at
 * most WS_FLOW_LIST_SCAN at a time.
 *
 * @param array         $viewer
 * @param array         $flow
 * @param array         $filters status (open, or a run status), before_id, limit
 * @param callable|null $keep    a further test a run must pass to be listed
 * @return array rows (each with access), next (the id to read on from, null on the last page)
 */
function ws_flow_runs_list($viewer, $flow, $filters = array(), $keep = null)
{
    $where = array("flow_id = '" . (int) $flow['id'] . "'");
    $status = (string) ($filters['status'] ?? '');

    if ($status === 'open') {
        $where[] = "status IN ('running', 'waiting')";
    } elseif (isset(ws_flow_run_statuses()[$status])) {
        $where[] = "status = '" . e($status) . "'";
    }

    $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
    $before = (int) ($filters['before_id'] ?? 0);
    $rows = array();
    $scanned = 0;

    while (true) {
        $batch = (array) db_items("SELECT * FROM ws_flow_runs WHERE " . implode(' AND ', $where)
            . (($before > 0) ? " AND id < '" . $before . "'" : '') . " ORDER BY id DESC LIMIT " . ($limit + 1));

        foreach ($batch as $run) {
            if (count($rows) === $limit) {
                return array('rows' => $rows, 'next' => (int) $rows[$limit - 1]['id']);
            }

            $before = (int) $run['id'];
            $scanned++;
            $access = ws_flow_run_access($viewer, $flow, $run);

            if (($access !== '') && (($keep === null) || $keep($run + array('access' => $access)))) {
                $run['access'] = $access;
                $rows[] = $run;
            }
        }

        if (count($batch) <= $limit) {
            return array('rows' => $rows, 'next' => null);
        }

        if ($scanned >= WS_FLOW_LIST_SCAN) {
            return array('rows' => $rows, 'next' => $before);
        }
    }
}

/**
 * The run a start token named for a flow, as the person reaches it
 * (ws_flow_run_for()): null for no token, a token of the wrong shape, one
 * that started no run of the flow, or a run they may not read.
 *
 * @param array $viewer
 * @param int   $flow_id
 * @param mixed $token   as the client sent it
 * @return array|null the run, with access and flow
 */
function ws_flow_run_for_token($viewer, $flow_id, $token)
{
    if (((int) $flow_id <= 0) || !ws_flow_start_token_valid($token)) {
        return null;
    }

    $run_id = (int) db_value("SELECT id FROM ws_flow_runs WHERE flow_id = '" . (int) $flow_id . "' AND start_key = '" . e(ws_flow_start_key($token)) . "'");

    return ($run_id > 0) ? ws_flow_run_for($viewer, $run_id) : null;
}

/**
 * Whether the person may start a run of the flow now: the checks of
 * ws_flow_run_start(), made on their own so that the API's dry run meets the
 * same refusals. The flow is live with a version that is started by hand,
 * the person may run it, its owner is still on the staff (the run acts with
 * their rights), and it is neither too busy nor started too often. A token
 * already used for this flow is no refusal: it names the run it started, and
 * is looked up before the other refusals, so a start retried after the flow
 * was paused or its owner left still answers the run it started. A person
 * who may no longer run the flow (it was paused, or their share was lowered
 * to view) gets that run back while they may read it
 * (ws_flow_run_for_token()); anybody else gets the 404 of a flow they cannot
 * see, or the 403 of a flow shared with them at view, never a refusal of the
 * token's shape.
 *
 * @param array      $viewer
 * @param array|null $flow
 * @param array      $data   token (optional, ws_flow_start_token_valid())
 * @return array ok, error, status, field, version, graph, trigger, start_key ('' when one is to be made), existing (the run the token started, 0 for none)
 */
function ws_flow_run_start_check($viewer, $flow, $data = array())
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    $token = $data['token'] ?? '';

    // The run a token started, answered as its version stands.
    $found = function ($run, $start_key) {
        $version = ws_flow_version($run['version_id']);
        $graph = is_array($version) ? ws_flow_graph_of($version) : ws_flow_graph_empty();

        return array(
            'ok'        => true,
            'error'     => '',
            'status'    => 200,
            'field'     => '',
            'start_key' => $start_key,
            'version'   => is_array($version) ? $version : array('id' => 0, 'n' => 0),
            'graph'     => $graph,
            'trigger'   => ws_flow_trigger_node($graph),
            'existing'  => (int) $run['id'],
        );
    };

    $access = ws_flow_access($viewer, $flow);

    // Somebody who may not run the flow, or no longer may (it was paused, or
    // their share was lowered to view), gets the run their token started
    // while they may read it. Otherwise a flow they do not reach is a 404,
    // and a flow shared at view is looked at, not run.
    if (($access === '') || ($access === 'view')) {
        $retried = is_array($flow) ? ws_flow_run_for_token($viewer, (int) $flow['id'], $token) : null;

        if ($retried) {
            return $found($retried, ws_flow_start_key($token));
        }

        return ($access === 'view')
            ? ws_flow_refusal(lang('You can look at this flow but not run it.'), 403)
            : ws_flow_refusal(lang('That flow could not be found.'), 404);
    }

    if (($token !== '') && ($token !== null) && !ws_flow_start_token_valid($token)) {
        return ws_flow_refusal(lang(array('string' => 'The token of a run is 1-{var:1} letters, digits and . _ : - characters.', 'vars' => WS_FLOW_TOKEN_MAX)), 422, 'token');
    }

    $start_key = ws_flow_start_token_valid($token) ? ws_flow_start_key($token) : '';
    $existing = ($start_key !== '') ? ws_flow_run(db_value("SELECT id FROM ws_flow_runs WHERE flow_id = '" . (int) $flow['id'] . "' AND start_key = '" . e($start_key) . "'")) : null;
    $ok = array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'start_key' => $start_key);

    if ($existing) {
        return $found($existing, $start_key);
    }

    $version = (((string) $flow['status'] === 'live') && ((int) $flow['version_id'] > 0)) ? ws_flow_version($flow['version_id']) : null;

    if (!is_array($version)) {
        return ws_flow_refusal(lang('Only a published flow that is live can be run.'));
    }

    $graph = ws_flow_graph_of($version);
    $trigger = ws_flow_trigger_node($graph);

    if (!$trigger || ((string) $trigger['kind'] !== 'manual')) {
        return ws_flow_refusal(lang('This flow is not started by hand.'));
    }

    $owner = ws_change_viewer_for_id($flow['owner_id']);

    if (!is_array($owner) || !ws_can_schedule($owner)) {
        return ws_flow_refusal(lang('The owner of this flow is no longer on the staff, so it cannot run.'));
    }

    $open = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . (int) $flow['id'] . "' AND status IN ('running', 'waiting')");
    $recent = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . (int) $flow['id'] . "' AND started_at > '" . (time() - 3600) . "'");

    switch (ws_flow_start_refusal($open, $recent)) {
        case 'open_runs':
            return ws_flow_refusal(lang(array('string' => 'This flow has {var:1} runs open already; it starts again once some of them end.', 'vars' => WS_FLOW_MAX_OPEN_RUNS)));

        case 'hourly':
            return ws_flow_refusal(lang(array('string' => 'This flow was started {var:1} times in the last hour; try again later.', 'vars' => WS_FLOW_STARTS_HOURLY)));
    }

    return $ok + array('version' => $version, 'graph' => $graph, 'trigger' => $trigger, 'existing' => 0);
}

/**
 * Starts a run of the flow's live version, started by the person and acting
 * as the flow's owner: the run, its start box done, the boxes after it made
 * ready, and the first of them carried out at once so that the first task is
 * there when the answer comes back. A second start with the same token (a
 * double click) meets the unique start_key and answers the run the first one
 * started. The run row is written only while the flow is still live, read in
 * the same statement, so a flow deleted meanwhile gets no run its deletion
 * missed (ws_flow_runs_cancel_for_flow()).
 *
 * @param array $viewer
 * @param array $flow
 * @param array $data see ws_flow_run_start_check()
 * @return array ok, error, status (201, or 200 for the run a token started), field, run_id, started
 */
function ws_flow_run_start($viewer, $flow, $data = array())
{
    $check = ws_flow_run_start_check($viewer, $flow, $data);

    if (!$check['ok']) {
        return $check + array('run_id' => 0, 'started' => false);
    }

    $found = function ($run_id) {
        return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'run_id' => (int) $run_id, 'started' => false);
    };

    if ($check['existing'] > 0) {
        return $found($check['existing']);
    }

    $flow_id = (int) $flow['id'];
    $start_key = ($check['start_key'] !== '') ? $check['start_key'] : 'manual:' . bin2hex(random_bytes(16));
    $context = json_encode(ws_flow_run_context_new($flow, $check['trigger']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $now = time();

    // Not db(): a failed statement there ends the request with an HTML page,
    // and the duplicate start key is an answer here.
    $written = @mysqli_query(db::$con, "INSERT INTO ws_flow_runs (flow_id, version_id, status, started_by, actor_id, start_key,
            subject_type, subject_id, context, depth, boxes_run, started_at, updated_at, finished_at)
        SELECT '" . $flow_id . "', '" . (int) $check['version']['id'] . "', 'running', '" . (int) $viewer['id'] . "', '" . (int) $flow['owner_id'] . "',
            '" . e($start_key) . "', '', 0, '" . e((string) $context) . "', 0, 0, '" . $now . "', '" . $now . "', 0
        FROM ws_flows WHERE id = '" . $flow_id . "' AND status = 'live'");

    if (!$written) {
        $existing = ((int) mysqli_errno(db::$con) === 1062)
            ? (int) db_value("SELECT id FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND start_key = '" . e($start_key) . "'")
            : 0;

        return ($existing > 0) ? $found($existing) : ws_flow_refusal(lang('The run could not be started.')) + array('run_id' => 0, 'started' => false);
    }

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return ws_flow_refusal(lang('Only a published flow that is live can be run.')) + array('run_id' => 0, 'started' => false);
    }

    $run_id = (int) mysqli_insert_id(db::$con);

    // A request that dies before this row leaves a run without boxes, which
    // ws_flow_runs_heal() gives its start box.
    db("INSERT IGNORE INTO ws_flow_run_steps (run_id, node_id, pass, status, outcome, attempt, ready_at, claimed_at, finished_at, result)
        VALUES ('" . $run_id . "', '" . e((string) $check['trigger']['id']) . "', 0, 'done', 'next', 1, '" . $now . "', '" . $now . "', '" . $now . "',
            '" . e(ws_flow_result_pack(lang(array('string' => 'Started by {var:1}.', 'vars' => ws_person_name($viewer['id']))))) . "')");

    log_activity(lang(array('string' => 'workspace flow ({var:1}) was started, run {var:2}', 'vars' => array($flow['name'], $run_id))), (string) ($_SESSION['sessionusername'] ?? ''));

    ws_flow_run_refresh($run_id);
    ws_flow_advance(WS_FLOW_BUDGET_TICK, $run_id);

    return array('ok' => true, 'error' => '', 'status' => 201, 'field' => '', 'run_id' => $run_id, 'started' => true);
}

/**
 * Writes the boxes that come next after a box of the run settled, and ends
 * the run when nothing is left open: done or failed, its owner told once by
 * the request whose UPDATE ended it. Otherwise the run reads running or
 * waiting, as its boxes stand.
 *
 * @param int $run_id
 * @return string the run's state, '' for a run that is not open
 */
function ws_flow_run_refresh($run_id)
{
    $run = ws_flow_run($run_id);

    if (!$run || !ws_flow_run_open($run['status'])) {
        return '';
    }

    $graph = ws_flow_run_graph($run);

    // Taken before the boxes are read: a box another request settles after
    // this read ends at or after the run's updated_at written below, which
    // is how ws_flow_runs_heal() knows a waiting state written from boxes
    // read too early.
    $now = time();
    $steps = ws_flow_step_states(ws_flow_run_steps($run['id']));
    $walk = ws_flow_walk($graph, $steps);

    // Two requests that settle the boxes before a join at the same moment
    // may both find it ready: the unique (run, box, pass) keeps one row.
    foreach ($walk['ready'] as $node_id) {
        db("INSERT IGNORE INTO ws_flow_run_steps (run_id, node_id, pass, status, ready_at)
            VALUES ('" . (int) $run['id'] . "', '" . e($node_id) . "', 0, 'ready', '" . $now . "')");
    }

    foreach ($walk['skipped'] as $node_id) {
        db("INSERT IGNORE INTO ws_flow_run_steps (run_id, node_id, pass, status, finished_at, result)
            VALUES ('" . (int) $run['id'] . "', '" . e($node_id) . "', 0, 'skipped', '" . $now . "', '" . e(ws_flow_result_pack(lang('Not reached in this run.'))) . "')");
    }

    if (!empty($walk['ready']) || !empty($walk['skipped'])) {
        $steps = ws_flow_step_states(ws_flow_run_steps($run['id']));
    }

    $state = ws_flow_run_state($graph, $steps);

    if (ws_flow_run_open($state)) {
        if ($state !== (string) $run['status']) {
            db("UPDATE ws_flow_runs SET status = '" . e($state) . "', updated_at = '" . $now . "'
                WHERE id = '" . (int) $run['id'] . "' AND status IN ('running', 'waiting')");
        }

        return $state;
    }

    db("UPDATE ws_flow_runs SET status = '" . e($state) . "', finished_at = '" . $now . "', updated_at = '" . $now . "'
        WHERE id = '" . (int) $run['id'] . "' AND status IN ('running', 'waiting')");

    if ((int) mysqli_affected_rows(db::$con) === 1) {
        ws_flow_run_tell_owner($run, $state);
    }

    return $state;
}

/**
 * Tells the flow's owner that a run ended, in their inbox. The sentence sits
 * beside the inbox row as a scheduled action's notice does
 * (ws_scheduled_notices), so the line names the flow and the run. Every run
 * that ends gets a line of its own ('separate'): the inbox keys a line by its
 * channel, message and task, all 0 here, so a line still unread would
 * otherwise take the sentence of any later run of any of the owner's flows,
 * and a failure would be written over by an unrelated success.
 *
 * @param array  $run
 * @param string $state done | failed
 * @return int the inbox row, 0 when nobody was told
 */
function ws_flow_run_tell_owner($run, $state)
{
    $flow = ws_flow($run['flow_id']);
    $owner_id = $flow ? (int) $flow['owner_id'] : 0;

    if (($owner_id <= 0) || !ws_is_team_member($owner_id)) {
        return 0;
    }

    if ($state === 'failed') {
        $failed = db_item("SELECT node_id, result FROM ws_flow_run_steps
            WHERE run_id = '" . (int) $run['id'] . "' AND status = 'failed' ORDER BY finished_at, id LIMIT 1");
        $node = is_array($failed) ? ws_flow_graph_node(ws_flow_run_graph($run), $failed['node_id']) : null;

        $text = lang(array('string' => 'Flow “{var:1}”, run {var:2}, failed at the box {var:3}: {var:4}', 'vars' => array(
            $flow['name'],
            (int) $run['id'],
            $node ? ws_flow_box_name($node) : (is_array($failed) ? (string) $failed['node_id'] : '?'),
            is_array($failed) ? ws_flow_result_text($failed['result']) : '',
        )));
    } else {
        $text = lang(array('string' => 'Flow “{var:1}”, run {var:2}, is done.', 'vars' => array($flow['name'], (int) $run['id'])));
    }

    $inbox_id = ws_notify($owner_id, 'flow_finished', array('channel_id' => 0, 'message_id' => 0, 'actor_id' => 0, 'separate' => true));

    if (($inbox_id > 0) && ws_scheduled_chains_ready()) {
        db("REPLACE INTO ws_scheduled_notices (inbox_id, action_id, text, created_at)
            VALUES ('" . (int) $inbox_id . "', 0, '" . e(mb_substr($text, 0, 1000)) . "', '" . time() . "')");
    }

    return $inbox_id;
}

/* ---------------------------------------------------------------------------
   Carrying out the boxes
   --------------------------------------------------------------------------- */

/**
 * Takes a ready box for this request: a conditional UPDATE, so of two
 * requests that meet only the one whose UPDATE changed the row runs it.
 *
 * @param array $step ws_flow_run_steps row
 * @return bool
 */
function ws_flow_step_claim($step)
{
    db("UPDATE ws_flow_run_steps SET status = 'running', claimed_at = '" . time() . "', attempt = attempt + 1
        WHERE id = '" . (int) $step['id'] . "' AND status = 'ready'");

    return (int) mysqli_affected_rows(db::$con) === 1;
}

/**
 * Marks an open run as running now. A box is settled only after this, so a
 * request that dies between settling a box and writing what comes after it
 * leaves a run that reads running and is touched by nobody: a running run is
 * always touched within WS_FLOW_LOCK (its boxes are claimed, or stop half
 * way and are taken care of), so ws_flow_runs_heal() knows such a run by its
 * age and walks it again. A walk of another request that read the boxes
 * before this box settled may still write waiting over this mark; the run
 * then has a box that ended at or after its last write, which the heal looks
 * for too. An ended run stays as it is.
 *
 * @param int $run_id
 */
function ws_flow_run_touch($run_id)
{
    db("UPDATE ws_flow_runs SET status = 'running', updated_at = '" . time() . "'
        WHERE id = '" . (int) $run_id . "' AND status IN ('running', 'waiting')");
}

/**
 * Ends a box the way it went and writes what comes after it. Only from the
 * state it was read in: a box another request settled, or a run cancelled
 * meanwhile, is left as it is. The run is walked again either way, so a
 * request that lost the box puts back the state its mark of the run changed.
 *
 * @param array  $step    ws_flow_run_steps row
 * @param string $from    the state it was in (running, waiting)
 * @param string $status  done | failed | skipped
 * @param string $outcome the arrow it leaves on, '' for none
 * @param string $text    how it went, in a sentence
 * @param array  $data    values the boxes after it may read
 * @return bool whether this request settled it
 */
function ws_flow_step_settle($step, $from, $status, $outcome, $text, $data = array())
{
    ws_flow_run_touch((int) $step['run_id']);

    db("UPDATE ws_flow_run_steps SET status = '" . e($status) . "', outcome = '" . e($outcome) . "',
            result = '" . e(ws_flow_result_pack($text, $data)) . "', finished_at = '" . time() . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = '" . e($from) . "'");

    $settled = ((int) mysqli_affected_rows(db::$con) === 1);

    ws_flow_run_refresh((int) $step['run_id']);

    return $settled;
}

/**
 * Runs a request left half walked, which nothing else would look at again:
 * - running, but nobody touched them for WS_FLOW_LOCK seconds
 *   (ws_flow_run_touch()): a request died after it settled a box and before
 *   it wrote the boxes after it, or after it wrote the run and before its
 *   start box;
 * - waiting, untouched as long, with a box that ended at or after the run's
 *   last write: a walk that read the boxes just before that box ended wrote
 *   waiting over the mark the box's request had made, and that request died
 *   before it wrote what comes after the box. A run that only waits for its
 *   tasks matches only when its last box ended in the second of that write;
 *   it is walked once, to no effect, and then marked looked at.
 * Each is walked again (ws_flow_run_refresh()), a run without a single box
 * getting its start box first, and marked looked at: a run that still reads
 * running then (its boxes are ready, and the claims take them) is left for
 * another WS_FLOW_LOCK. ws_flow_due() sees the running ones only, since
 * every open screen asks it and waiting runs are many; a waiting one is
 * found on the runner's next pass (workspace_recurring_job.php runs it on
 * every tick, and any box or action that is due starts one too).
 *
 * @param int $run_id only this run, 0 for all
 * @return int how many runs were walked again
 */
function ws_flow_runs_heal($run_id = 0)
{
    $now = time();
    $healed = 0;
    $only = ((int) $run_id > 0) ? " AND id = '" . (int) $run_id . "'" : '';

    $runs = array_merge(
        (array) db_items("SELECT * FROM ws_flow_runs
            WHERE status = 'running' AND updated_at < '" . ($now - WS_FLOW_LOCK) . "'" . $only . "
            ORDER BY updated_at LIMIT 10"),
        (array) db_items("SELECT * FROM ws_flow_runs
            WHERE status = 'waiting' AND updated_at < '" . ($now - WS_FLOW_LOCK) . "'" . $only . "
            AND EXISTS (SELECT 1 FROM ws_flow_run_steps
                WHERE ws_flow_run_steps.run_id = ws_flow_runs.id AND ws_flow_run_steps.status IN ('done', 'failed', 'skipped')
                AND ws_flow_run_steps.finished_at >= ws_flow_runs.updated_at)
            ORDER BY updated_at LIMIT 10")
    );

    foreach ($runs as $run) {
        if (!db_value("SELECT id FROM ws_flow_run_steps WHERE run_id = '" . (int) $run['id'] . "' LIMIT 1")) {
            $trigger = ws_flow_trigger_node(ws_flow_run_graph($run));

            if (!$trigger) {
                db("UPDATE ws_flow_runs SET status = 'failed', finished_at = '" . $now . "', updated_at = '" . $now . "'
                    WHERE id = '" . (int) $run['id'] . "' AND status = 'running'");

                continue;
            }

            db("INSERT IGNORE INTO ws_flow_run_steps (run_id, node_id, pass, status, outcome, attempt, ready_at, claimed_at, finished_at, result)
                VALUES ('" . (int) $run['id'] . "', '" . e((string) $trigger['id']) . "', 0, 'done', 'next', 1, '" . $now . "', '" . $now . "', '" . $now . "',
                    '" . e(ws_flow_result_pack(lang(array('string' => 'Started by {var:1}.', 'vars' => ws_person_name($run['started_by']))))) . "')");
        }

        ws_flow_run_refresh((int) $run['id']);

        // Only when nobody wrote the run meanwhile: the walk's own write, or
        // another request's, carries its own moment.
        db("UPDATE ws_flow_runs SET updated_at = '" . $now . "'
            WHERE id = '" . (int) $run['id'] . "' AND status IN ('running', 'waiting') AND updated_at = '" . (int) $run['updated_at'] . "'");

        $healed++;
    }

    return $healed;
}

/**
 * Carries the runs on: boxes that stopped half way are taken care of, runs
 * a request left half walked are walked again, waiting boxes whose next look
 * has come look at their task, then the ready boxes are claimed and carried
 * out, oldest first, at most WS_FLOW_BOXES_PER_RUN of one run and
 * WS_FLOW_ADVANCE_MAX in all, and none started once the time budget is
 * spent (ws_flow_advance_boxes()). A box that ends makes the next ones
 * ready, which a later round of the same call takes.
 *
 * @param int $budget seconds: WS_FLOW_BUDGET_TICK on a screen, WS_FLOW_BUDGET_JOB in a job
 * @param int $run_id only this run (the one just started), 0 for all
 * @return array ran (boxes carried out), woke (waiting boxes settled)
 */
function ws_flow_advance($budget = WS_FLOW_BUDGET_TICK, $run_id = 0)
{
    $result = array('ran' => 0, 'woke' => 0);

    if (!ws_flows_ready()) {
        return $result;
    }

    $deadline = microtime(true) + max(1, (int) $budget);
    $only = ((int) $run_id > 0) ? " AND run_id = '" . (int) $run_id . "'" : '';

    ws_flow_steps_stale($only);
    ws_flow_runs_heal((int) $run_id);
    $result['woke'] = ws_flow_steps_recheck($only, $deadline);

    $result['ran'] = ws_flow_advance_boxes(
        function ($full) use ($only) {
            return (array) db_items("SELECT * FROM ws_flow_run_steps
                WHERE status = 'ready' AND ready_at <= '" . time() . "'" . $only
                . (!empty($full) ? " AND run_id NOT IN (" . implode(',', array_map('intval', $full)) . ")" : '') . "
                ORDER BY ready_at, id LIMIT 20");
        },
        function ($row) {
            return ws_flow_step_claim($row);
        },
        function ($row) {
            // The row as the claim left it: a box on its second try knows it
            // is one (ws_flow_step_task() looks for the first try's task).
            $row['status'] = 'running';
            $row['attempt'] = (int) $row['attempt'] + 1;

            ws_flow_step_run($row);
        },
        $deadline
    );

    return $result;
}

/**
 * The ready boxes one call of ws_flow_advance() carries out, oldest first: a
 * batch read at a time ($select, told the runs that had their share so that
 * it leaves them out), each box taken ($claim) and carried out ($carry) in
 * turn. At most WS_FLOW_BOXES_PER_RUN boxes of one run, also when one batch
 * holds more of it, and WS_FLOW_ADVANCE_MAX in all; none started once the
 * deadline has passed. A box another request took counts for nothing; a
 * batch that starts no box ends the call. A box that ends makes the next
 * ones ready, which a later batch takes. Pure but for its callables.
 *
 * @param callable $select   function (int[] $full): array[], ready rows of runs not in $full
 * @param callable $claim    function (array $row): bool, whether this request took the box
 * @param callable $carry    function (array $row), carries out a box taken
 * @param float    $deadline microtime(true) after which no box is started
 * @return int how many boxes were carried out
 */
function ws_flow_advance_boxes($select, $claim, $carry, $deadline)
{
    $ran = 0;
    $per_run = array();
    $full = array();

    for ($round = 0; $round < WS_FLOW_ADVANCE_MAX; $round++) {
        $started = 0;

        foreach ((array) $select($full) as $row) {
            if (($ran >= WS_FLOW_ADVANCE_MAX) || (microtime(true) >= $deadline)) {
                return $ran;
            }

            $id = (int) $row['run_id'];

            // A run that had its share is passed over before its box is
            // taken: a box taken and then left would wait in running until
            // the stale pass.
            if (isset($full[$id]) || !$claim($row)) {
                continue;
            }

            $per_run[$id] = ($per_run[$id] ?? 0) + 1;

            if ($per_run[$id] >= WS_FLOW_BOXES_PER_RUN) {
                $full[$id] = $id;
            }

            $ran++;
            $started++;

            $carry($row);
        }

        if ($started === 0) {
            break;
        }
    }

    return $ran;
}

/**
 * Boxes running for longer than WS_FLOW_LOCK: the request that took them
 * died. One whose effect is written waits for it again, looking at it at
 * once; one without is made ready again for its second try, or fails after
 * it (ws_flow_stale_action()).
 *
 * @param string $only " AND run_id = ..." or ''
 */
function ws_flow_steps_stale($only = '')
{
    $now = time();

    foreach ((array) db_items("SELECT * FROM ws_flow_run_steps
        WHERE status = 'running' AND claimed_at < '" . ($now - WS_FLOW_LOCK) . "'" . $only . "
        ORDER BY claimed_at LIMIT 20") as $step) {

        $same = " WHERE id = '" . (int) $step['id'] . "' AND status = 'running' AND claimed_at = '" . (int) $step['claimed_at'] . "'";

        switch (ws_flow_stale_action($step)) {
            case 'resume':
                $wait = ws_flow_effect_wait($step['effect']);

                db("UPDATE ws_flow_run_steps SET status = 'waiting', wait_event = '" . e($wait['event']) . "',
                    wait_key = '" . e($wait['key']) . "', wake_at = '" . $now . "'" . $same);

                if ((int) mysqli_affected_rows(db::$con) === 1) {
                    ws_events_awaited(true);
                    ws_flow_run_refresh((int) $step['run_id']);
                }

                break;

            case 'retry':
                db("UPDATE ws_flow_run_steps SET status = 'ready'" . $same);
                break;

            default:
                ws_flow_step_settle($step, 'running', 'failed', '', ws_flow_effect_began((string) $step['effect'])
                    ? lang('The box stopped half way and may have done its work, so it was not tried again.')
                    : lang('The box stopped half way twice and was not tried again.'));
        }
    }
}

/**
 * Waiting boxes whose next look has come (wake_at): the event that should
 * have woken them may have been lost, so each looks at its task itself. Each
 * is taken first by moving its next look an hour on, so two requests do not
 * both look.
 *
 * @param string $only     " AND run_id = ..." or ''
 * @param float  $deadline no look after it, 0 for none
 * @return int how many boxes settled
 */
function ws_flow_steps_recheck($only = '', $deadline = 0.0)
{
    $now = time();
    $woke = 0;

    foreach ((array) db_items("SELECT * FROM ws_flow_run_steps
        WHERE status = 'waiting' AND wake_at > 0 AND wake_at <= '" . $now . "'" . $only . "
        ORDER BY wake_at LIMIT 20") as $step) {

        if (($deadline > 0) && (microtime(true) >= $deadline)) {
            break;
        }

        db("UPDATE ws_flow_run_steps SET wake_at = '" . ($now + WS_FLOW_RECHECK) . "'
            WHERE id = '" . (int) $step['id'] . "' AND status = 'waiting' AND wake_at = '" . (int) $step['wake_at'] . "'");

        if (((int) mysqli_affected_rows(db::$con) === 1) && ws_flow_step_recheck($step)) {
            $woke++;
        }
    }

    return $woke;
}

/**
 * Carries out one box this request claimed, by its kind. A box of a run
 * cancelled meanwhile is let go; a run of a flow deleted meanwhile, which
 * nobody could see or cancel any more, is cancelled with its open boxes
 * before it opens anything; every box counts against the run's limit.
 *
 * @param array $step ws_flow_run_steps row, now running
 */
function ws_flow_step_run($step)
{
    $run = ws_flow_run($step['run_id']);

    if (!$run || !ws_flow_run_open($run['status'])) {
        ws_flow_step_settle($step, 'running', 'skipped', '', lang('Not reached in this run.'));
        return;
    }

    $flow = ws_flow($run['flow_id']);
    $node = ws_flow_graph_node(ws_flow_run_graph($run), $step['node_id']);

    if ($flow && ((string) $flow['status'] === 'binned')) {
        ws_flow_runs_cancel_ids(array((int) $run['id']), lang('Cancelled: the flow was deleted.'));
        return;
    }

    if (!$flow || !$node) {
        ws_flow_step_settle($step, 'running', 'failed', '', lang('This box is not in the version of the flow the run follows.'));
        return;
    }

    db("UPDATE ws_flow_runs SET boxes_run = boxes_run + 1, updated_at = '" . time() . "'
        WHERE id = '" . (int) $run['id'] . "' AND boxes_run < '" . WS_FLOW_MAX_BOX_RUNS . "'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        ws_flow_step_settle($step, 'running', 'failed', '', lang(array('string' => 'The run reached its limit of {var:1} boxes.', 'vars' => WS_FLOW_MAX_BOX_RUNS)));
        return;
    }

    switch ($node['kind']) {
        case 'end':
            ws_flow_step_settle($step, 'running', 'done', '', lang('The flow reached its end.'));
            return;

        // A wait acts on nothing, so it needs nobody's rights.
        case 'delay':
            ws_flow_delay_run($step, $node);
            return;

        // The owner's rights as they are now: somebody who has left the staff
        // opens no task in the team's name.
        case 'task':
            $owner = ws_change_viewer_for_id($run['actor_id']);

            if (!is_array($owner) || !ws_can_schedule($owner)) {
                ws_flow_step_settle($step, 'running', 'failed', '', lang('The owner of the flow is no longer on the staff, so the task was not opened.'));
                return;
            }

            ws_flow_step_task($run, $flow, $step, $node, $owner);
            return;

        // Another flow, started with the owner's rights now.
        case 'start_flow':
            $owner = ws_change_viewer_for_id($run['actor_id']);

            if (!is_array($owner) || !ws_can_schedule($owner)) {
                ws_flow_step_settle($step, 'running', 'failed', '', lang('The owner of the flow is no longer on the staff, so the box did not run.'));
                return;
            }

            ws_flow_chain_start($run, $step, $node, $owner);
            return;

        // A condition, looked at with the owner's rights now.
        case 'condition':
            $owner = ws_change_viewer_for_id($run['actor_id']);

            if (!is_array($owner) || !ws_can_schedule($owner)) {
                ws_flow_step_settle($step, 'running', 'failed', '', lang('The owner of the flow is no longer on the staff, so the box did not run.'));
                return;
            }

            ws_flow_condition_run($run, $step, $node, $owner);
            return;
    }

    // A step (steps.php), with the owner's rights as they are now, like a
    // task box.
    if (!empty(ws_flow_kinds()[$node['kind']]['step'])) {
        $owner = ws_change_viewer_for_id($run['actor_id']);

        if (!is_array($owner) || !ws_can_schedule($owner)) {
            ws_flow_step_settle($step, 'running', 'failed', '', lang('The owner of the flow is no longer on the staff, so the box did not run.'));
            return;
        }

        ws_step_run($run, $step, $node, ws_flow_run_graph($run), $owner);
        return;
    }

    // A start box is written done as the run starts and never comes here.
    ws_flow_step_settle($step, 'running', 'failed', '', lang('This kind of box cannot run yet.'));
}

/**
 * The people a task box gives its task to, with the owner's rights. People
 * named on the box are given it as they are (ws_task_create() refuses them
 * when the owner may no longer give them work). A rule is resolved now as a
 * channel template resolves it (ws_template_assignees()), "whoever starts
 * the run" being the run's starter; somebody it means who cannot be found,
 * or to whom the owner may not give work, leaves the task with nobody on it.
 *
 * @param array $owner
 * @param array $config        the box's settings
 * @param array $run
 * @param int   $department_id the home channel's department, for its lead
 * @return array user_ids, department_id (a department's pool), unassigned
 */
function ws_flow_step_task_people($owner, $config, $run, $department_id)
{
    $out = array('user_ids' => array(), 'department_id' => 0, 'unassigned' => false);

    if (!empty($config['assignees'])) {
        $out['user_ids'] = array_values(array_map('intval', (array) $config['assignees']));

        return $out;
    }

    $rule = (string) $config['assign'];

    if (($rule === '') || ($rule === 'none')) {
        return $out;
    }

    if ($rule === 'creator') {
        $starter = (int) $run['started_by'];
        $people = ws_is_team_member($starter) ? array($starter) : array();
        $wanted = true;
    } else {
        $assign = ws_template_assignees($owner, $rule, (int) $department_id);
        $people = $assign['user_ids'];
        $wanted = $assign['wanted'];
        $out['department_id'] = (int) $assign['department_id'];
    }

    if ($wanted && (empty($people) || !ws_can_assign_to($owner, $people))) {
        $out['unassigned'] = true;

        return $out;
    }

    $out['user_ids'] = $people;

    return $out;
}

/**
 * The task a box's first try opened before its request died, found by its
 * owner, title and the last line of its description (ws_flow_task_marker(),
 * which names the run and the box), opened since the box became ready. A
 * task another box of the run already holds is never taken, and of two the
 * newest is. The line is matched as it is: a backslash, % or _ in a name is
 * no pattern.
 *
 * @param array  $owner
 * @param int    $run_id
 * @param string $title
 * @param string $marker
 * @param int    $since  when the box became ready
 * @return int the task, 0 for none
 */
function ws_flow_step_task_find($owner, $run_id, $title, $marker, $since)
{
    return (int) db_value("SELECT id FROM ws_tasks
        WHERE created_at >= '" . (int) $since . "'
        AND creator_id = '" . (int) $owner['id'] . "' AND title = '" . e($title) . "'
        AND description LIKE '%" . e(addcslashes($marker, '\\%_')) . "'
        AND NOT EXISTS (SELECT 1 FROM ws_flow_run_steps
            WHERE ws_flow_run_steps.run_id = '" . (int) $run_id . "' AND ws_flow_run_steps.effect = CONCAT('task:', ws_tasks.id))
        ORDER BY id DESC LIMIT 1");
}

/**
 * A task box: opens its task with the owner's rights, in the channel the run
 * was started in (its flow's home then, ws_flow_run_channel_id()) when there
 * is one, given to the box's people, due so many days from when the box
 * became ready; then waits for it. The task's id is written in the same
 * UPDATE that makes the box wait, right after the task is opened: from then
 * on a stale box waits again instead of opening a second task. A box tried
 * again after its request died first looks for the task its first try
 * opened, and looks at it at once when it finds it: the task may have been
 * finished meanwhile, and the event that said so woke no box. A run
 * cancelled while the box was on its way opens no task; one cancelled while
 * the task was being opened keeps its id on the box. A box with a timeout
 * says in its result when it runs out; its next look is no later than that.
 *
 * @param array $run
 * @param array $flow
 * @param array $step  ws_flow_run_steps row, running
 * @param array $node  the box
 * @param array $owner the flow's owner, as the workspace sees their rights
 */
function ws_flow_step_task($run, $flow, $step, $node, $owner)
{
    $config = $node['config'];
    $title = (string) $config['title'];
    $context = ws_flow_run_context($run);
    $marker = ws_flow_task_marker((string) ($context['flow_name'] ?? $flow['name']), (int) $run['id'], ws_flow_box_unique_name(ws_flow_run_graph($run), $node));
    $channel_id = ws_flow_run_channel_id($run);
    $channel = ($channel_id > 0) ? ws_channel($channel_id) : null;
    $task_id = ((int) $step['attempt'] > 1) ? ws_flow_step_task_find($owner, (int) $run['id'], $title, $marker, (int) $step['ready_at']) : 0;
    $adopted = ($task_id > 0);
    $unassigned = false;

    if (!$adopted) {
        $people = ws_flow_step_task_people($owner, $config, $run, $channel ? (int) $channel['department_id'] : 0);

        // The run as it is now, read again right before anything is opened:
        // a run cancelled since ws_flow_step_run() looked at it opens no
        // task. The cancel skipped this box already; this leaves it so.
        $current = ws_flow_run((int) $run['id']);

        if (!$current || !ws_flow_run_open($current['status'])) {
            ws_flow_step_settle($step, 'running', 'skipped', '', lang('Not reached in this run.'));
            return;
        }

        $made = ws_task_create($owner, array(
            'title'         => $title,
            'description'   => ws_flow_task_description($config['description'], $marker),
            'priority'      => (string) $config['priority'],
            'due_date'      => (string) ws_flow_due_date($step['ready_at'], $config['due_in']),
            'department_id' => $people['department_id'],
            'channel_id'    => $channel ? (int) $channel['id'] : 0,
            'assignees'     => $people['user_ids'],
        ));

        if (!$made['ok']) {
            ws_flow_step_settle($step, 'running', 'failed', '', lang(array('string' => 'The task could not be opened: {var:1}', 'vars' => $made['error'])));
            return;
        }

        $task_id = (int) $made['task_id'];
        $unassigned = $people['unassigned'];
    }

    // Counted from the claim this request made a moment ago, which the
    // look counts from too (claimed_at).
    $now = time();
    $timeout_at = ws_flow_timeout_at($config, $now);
    $text = lang(array('string' => 'task {var:1} “{var:2}” opened', 'vars' => array(ws_task_number($task_id), $title)))
        . ($unassigned ? ' · ' . lang('with nobody on it: the people it was meant for could not be given it') : '')
        . (($timeout_at > 0) ? ' · ' . lang(array('string' => 'leaves on timeout at {var:1}', 'vars' => ws_scheduled_moment($timeout_at))) : '');

    db("UPDATE ws_flow_run_steps SET effect = 'task:" . $task_id . "', status = 'waiting',
            wait_event = '" . e(WS_FLOW_TASK_WAIT) . "', wait_key = 'task:" . $task_id . "', wake_at = '" . ($adopted ? $now : ws_flow_timeout_next_look($now, $timeout_at)) . "',
            result = '" . e(ws_flow_result_pack($text, array('task_id' => $task_id))) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running'");

    if ((int) mysqli_affected_rows(db::$con) === 1) {
        // The list of awaited events this request read may have been read
        // before this box waited: dropped, so a status change of the task
        // later in the same request is kept for it.
        ws_events_awaited(true);
        ws_flow_run_refresh((int) $run['id']);

        return;
    }

    // The box ended while its task was being opened: the run was cancelled
    // at that very moment, or the box was given up as stopped half way. It
    // still names the task it opened, which stays as it is, so the run shows
    // it. A box made ready again for its retry is left alone: the retry
    // finds this task itself (ws_flow_step_task_find()), and a box holding
    // it would hide it from that search.
    db("UPDATE ws_flow_run_steps SET effect = 'task:" . $task_id . "'
        WHERE id = '" . (int) $step['id'] . "' AND effect = '' AND status IN ('skipped', 'failed')");
}

/**
 * A waiting box looks at its task: done, cancelled or gone settles it
 * (ws_flow_task_settlement()); still open past the box's timeout
 * (ws_flow_timeout_at(), counted from its claim), it leaves on timeout
 * (ws_flow_timeout_settlement()) and the task stays open; still open before
 * it, it goes on waiting. The task's state now is what counts, not what an
 * event said about it, and a task that ended counts before the deadline. A
 * box of a run that ended is let go.
 *
 * @param array $step ws_flow_run_steps row, waiting
 * @return bool whether this request settled it
 */
function ws_flow_step_recheck($step)
{
    if (ws_flow_delay_key_until($step['wait_key']) > 0) {
        return ws_flow_delay_recheck($step);
    }

    if (ws_flow_effect_approval_id($step['wait_key']) > 0) {
        return ws_flow_approval_recheck($step);
    }

    $task_id = ws_flow_effect_task_id($step['wait_key']);
    $run = ($task_id > 0) ? ws_flow_run($step['run_id']) : null;

    if (!$run) {
        return false;
    }

    // A run that ended while the box waited (cancelled half way by a
    // request that died): the box is let go rather than looked at forever.
    if (!ws_flow_run_open($run['status'])) {
        return ws_flow_step_settle($step, 'waiting', 'skipped', '', lang('The run ended before this box did; its task stays as it is.'));
    }

    $task = ws_task($task_id);
    $graph = ws_flow_run_graph($run);
    $routed = ws_flow_outcome_routed($graph, $step['node_id'], 'cancelled');
    $settled = ws_flow_task_settlement($task ? (string) $task['status'] : null, $routed);
    $number = ws_task_number($task_id);

    // The task still open: the box runs out at its deadline, or goes on
    // waiting with its next look no later than it. Both ends go through
    // ws_flow_step_settle(), whose write holds only while the box waits, so
    // a task finished at that moment and the deadline never both settle it.
    if ($settled['status'] === '') {
        $node = ws_flow_graph_node($graph, $step['node_id']);
        $timeout_at = $node ? ws_flow_timeout_at($node['config'] ?? array(), (int) $step['claimed_at']) : 0;
        $now = time();

        if (!ws_flow_timed_out($timeout_at, $now)) {
            if ($timeout_at > 0) {
                db("UPDATE ws_flow_run_steps SET wake_at = '" . ws_flow_timeout_next_look($now, $timeout_at) . "'
                    WHERE id = '" . (int) $step['id'] . "' AND status = 'waiting' AND wake_at > '" . (int) $timeout_at . "'");
            }

            return false;
        }

        $timeout = ws_flow_timeout_settlement(ws_flow_outcome_routed($graph, $step['node_id'], 'timeout'));
        $hours = ws_flow_timeout_hours($node['config']);
        $text = ($timeout['status'] === 'done')
            ? lang(array('string' => 'Task {var:1} was not finished within {var:2} hours; it stays open.', 'vars' => array($number, $hours)))
            : lang(array('string' => 'Task {var:1} was not finished within {var:2} hours, and no arrow leaves the box on timeout; it stays open.', 'vars' => array($number, $hours)));

        return ws_flow_step_settle($step, 'waiting', $timeout['status'], $timeout['outcome'], $text, array('task_id' => $task_id));
    }

    switch ($settled['reason']) {
        case 'done':
            $text = lang(array('string' => 'Task {var:1} is done.', 'vars' => $number));
            break;

        case 'gone':
            $text = $routed
                ? lang(array('string' => 'Task {var:1} is gone.', 'vars' => $number))
                : lang(array('string' => 'Task {var:1} is gone, and no arrow leaves the box on cancelled.', 'vars' => $number));
            break;

        default:
            $text = $routed
                ? lang(array('string' => 'Task {var:1} was cancelled.', 'vars' => $number))
                : lang(array('string' => 'Task {var:1} was cancelled, and no arrow leaves the box on cancelled.', 'vars' => $number));
    }

    return ws_flow_step_settle($step, 'waiting', $settled['status'], $settled['outcome'], $text, array('task_id' => $task_id));
}

/**
 * A delay box claimed by this request starts to wait: in one conditional
 * write it keeps the moment it waits until (effect and wait_key, see
 * ws_flow_delay_key()), waits on no event, and has its next look then
 * (wake_at), which ws_flow_due() and ws_flow_steps_recheck() read. Its
 * result says until when, which the run view shows while it waits. A box
 * whose time is out of range (a graph published before the check) fails.
 *
 * @param array $step ws_flow_run_steps row, now running
 * @param array $node its box
 */
function ws_flow_delay_run($step, $node)
{
    $minutes = ws_flow_delay_minutes($node['config'] ?? array());

    if (($minutes < 1) || ($minutes > WS_FLOW_DELAY_MAX)) {
        ws_flow_step_settle($step, 'running', 'failed', '', lang('The delay box has no time to wait within a week.'));
        return;
    }

    $until = ws_flow_delay_until($node['config'], time());
    $key = ws_flow_delay_key($until);
    $text = lang(array('string' => 'Waits until {var:1}.', 'vars' => ws_scheduled_moment($until)));

    db("UPDATE ws_flow_run_steps SET effect = '" . e($key) . "', status = 'waiting',
            wait_event = '', wait_key = '" . e($key) . "', wake_at = '" . $until . "',
            result = '" . e(ws_flow_result_pack($text, array('waited_until' => ws_flow_time($until)))) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running'");

    if ((int) mysqli_affected_rows(db::$con) === 1) {
        ws_flow_run_refresh((int) $step['run_id']);
    }
}

/**
 * A waiting delay box looks at its time (ws_flow_delay_settlement()): come,
 * it settles done; not yet (a look before it, as a clock moved), its next
 * look goes back to the moment. A run that ended meanwhile lets it go.
 *
 * @param array $step ws_flow_run_steps row, waiting
 * @return bool whether the box settled
 */
function ws_flow_delay_recheck($step)
{
    $run = ws_flow_run($step['run_id']);

    if (!$run) {
        return false;
    }

    if (!ws_flow_run_open($run['status'])) {
        return ws_flow_step_settle($step, 'waiting', 'skipped', '', lang('Not reached in this run.'));
    }

    $settled = ws_flow_delay_settlement((string) $step['wait_key'], time());
    $until = ws_flow_delay_key_until($step['wait_key']);

    if ($settled['status'] === '') {
        db("UPDATE ws_flow_run_steps SET wake_at = '" . $until . "'
            WHERE id = '" . (int) $step['id'] . "' AND status = 'waiting'");

        return false;
    }

    return ws_flow_step_settle($step, 'waiting', 'done', 'done', lang(array('string' => 'Waited until {var:1}.', 'vars' => ws_scheduled_moment($until))), $settled['data']);
}

/**
 * An approval box whose request is out (steps.php, ws_step_run()) waits for
 * it: in one conditional write it keeps the request (effect and wait_key,
 * ws_flow_approval_key()), waits for its decision (WS_FLOW_APPROVAL_WAIT)
 * and has its next look in WS_FLOW_RECHECK seconds, in case the event is
 * lost. A step that gave no request fails. A box that ended while its
 * request was being asked (the run cancelled at that moment) still names
 * the request, which stays as it is.
 *
 * @param array  $step        ws_flow_run_steps row, running
 * @param int    $approval_id the request asked
 * @param string $text        the step's sentence
 * @param array  $data        ws_step_result_data()
 */
function ws_flow_approval_wait($step, $approval_id, $text, $data)
{
    if ((int) $approval_id <= 0) {
        ws_flow_step_settle($step, 'running', 'failed', '', lang('The approval request could not be asked.'), $data);
        return;
    }

    $key = ws_flow_approval_key($approval_id);

    db("UPDATE ws_flow_run_steps SET effect = '" . e($key) . "', status = 'waiting',
            wait_event = '" . e(WS_FLOW_APPROVAL_WAIT) . "', wait_key = '" . e($key) . "', wake_at = '" . (time() + WS_FLOW_RECHECK) . "',
            result = '" . e(ws_flow_result_pack($text, $data)) . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running'");

    if ((int) mysqli_affected_rows(db::$con) === 1) {
        // The awaited events this request read may predate the wait: read
        // again, so a decision later in the same request is kept for it.
        ws_events_awaited(true);
        ws_flow_run_refresh((int) $step['run_id']);

        return;
    }

    db("UPDATE ws_flow_run_steps SET effect = '" . e($key) . "'
        WHERE id = '" . (int) $step['id'] . "' AND effect = '" . e(WS_STEP_BEGAN . 'approval') . "' AND status IN ('skipped', 'failed')");
}

/**
 * A waiting approval box looks at its request: ended, it settles
 * (ws_flow_approval_settlement()) with the request and its message as its
 * values; still open, it goes on waiting. The request as it stands now is
 * what counts, not what an event said. A box of a run that ended is let go;
 * its request stays as it is.
 *
 * @param array $step ws_flow_run_steps row, waiting
 * @return bool whether this request settled it
 */
function ws_flow_approval_recheck($step)
{
    $approval_id = ws_flow_effect_approval_id($step['wait_key']);
    $run = ws_flow_run($step['run_id']);

    if (!$run) {
        return false;
    }

    if (!ws_flow_run_open($run['status'])) {
        return ws_flow_step_settle($step, 'waiting', 'skipped', '', lang('The run ended before this box did; its approval request stays as it is.'));
    }

    $approval = function_exists('ws_approval') ? ws_approval($approval_id) : null;
    $outcome = $approval ? (((int) $approval['closed_at'] > 0) ? (string) $approval['outcome'] : '') : null;

    // Closed with no outcome written: nobody decided.
    if (($outcome === '') && $approval && ((int) $approval['closed_at'] > 0)) {
        $outcome = 'withdrawn';
    }

    $routed = ($outcome !== '') && ws_flow_outcome_routed(ws_flow_run_graph($run), $step['node_id'], ($outcome === 'expired') ? 'expired' : 'withdrawn');
    $settled = ws_flow_approval_settlement($outcome, $routed);

    if ($settled['status'] === '') {
        return false;
    }

    $title = $approval ? (string) $approval['title'] : '';

    switch ($settled['outcome']) {
        case 'approved':
            $text = lang(array('string' => 'The approval request “{var:1}” was approved.', 'vars' => $title));
            break;

        case 'rejected':
            $text = lang(array('string' => 'The approval request “{var:1}” was rejected.', 'vars' => $title));
            break;

        case 'expired':
            $text = lang(array('string' => 'The approval request “{var:1}” expired without a decision.', 'vars' => $title));
            break;

        default:
            $text = $approval
                ? lang(array('string' => 'The approval request “{var:1}” was closed without a decision.', 'vars' => $title))
                : lang('The approval request is gone.');
    }

    $data = array('approval_id' => $approval_id, 'message_id' => $approval ? (((int) $approval['message_id'] > 0) ? (int) $approval['message_id'] : null) : null);

    return ws_flow_step_settle($step, 'waiting', $settled['status'], $settled['outcome'], $text, $data);
}

/**
 * Wakes the boxes waiting for an event of the workspace (ws_events_process()):
 * each looks at what it waits for and, when that is over, settles and makes
 * the next boxes ready for the ws_flow_advance() that follows.
 *
 * @param string $event
 * @param array  $payload
 * @return int how many boxes settled
 */
function ws_flow_wake($event, $payload)
{
    if (!ws_flows_ready()) {
        return 0;
    }

    $key = ws_flow_wake_key($event, (array) $payload);

    if ($key === '') {
        return 0;
    }

    $woke = 0;

    foreach ((array) db_items("SELECT * FROM ws_flow_run_steps
        WHERE status = 'waiting' AND wait_event = '" . e((string) $event) . "' AND wait_key = '" . e($key) . "'
        ORDER BY id LIMIT 50") as $step) {
        if (ws_flow_step_recheck($step)) {
            $woke++;
        }
    }

    return $woke;
}

/**
 * The events waiting boxes need, for ws_events_awaited(): ws_event() keeps
 * no event nobody waits for.
 *
 * @return string[]
 */
function ws_flow_waits_awaited()
{
    if (!ws_flows_ready()) {
        return array();
    }

    return array_map('strval', (array) db_values("SELECT DISTINCT wait_event FROM ws_flow_run_steps WHERE status = 'waiting' AND wait_event <> ''"));
}

/**
 * Is a box due: ready, waiting past its next look, or stopped half way? Or a
 * run left half walked (ws_flow_runs_heal())? One read over four indexed
 * lookups, for ws_scheduled_due(), which every open screen asks.
 *
 * @return bool
 */
function ws_flow_due()
{
    if (!ws_flows_ready()) {
        return false;
    }

    $now = time();

    return (bool) db_value("(SELECT id FROM ws_flow_run_steps WHERE status = 'ready' AND ready_at <= '" . $now . "' LIMIT 1)
        UNION ALL (SELECT id FROM ws_flow_run_steps WHERE status = 'waiting' AND wake_at > 0 AND wake_at <= '" . $now . "' LIMIT 1)
        UNION ALL (SELECT id FROM ws_flow_run_steps WHERE status = 'running' AND claimed_at < '" . ($now - WS_FLOW_LOCK) . "' LIMIT 1)
        UNION ALL (SELECT id FROM ws_flow_runs WHERE status = 'running' AND updated_at < '" . ($now - WS_FLOW_LOCK) . "' LIMIT 1)
        LIMIT 1");
}

/* ---------------------------------------------------------------------------
   Cancelling and sweeping
   --------------------------------------------------------------------------- */

/**
 * Whether the person may cancel the run: the checks of ws_flow_run_cancel(),
 * made on their own so that the API's dry run meets the same refusals. The
 * flow's editors and the run's starter may; the run is still going.
 *
 * @param array $viewer
 * @param array $run
 * @return array ok, error, status, field, open (boxes still open), task_ids (the tasks its waiting boxes wait for, which stay as they are)
 */
function ws_flow_run_cancel_check($viewer, $run)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    $access = ws_flow_run_access($viewer, ws_flow($run['flow_id']), $run);

    if ($access === '') {
        return ws_flow_refusal(lang('That run could not be found.'), 404);
    }

    if (!ws_flow_run_cancel_allowed($access, (int) $run['started_by'] === (int) $viewer['id'])) {
        return ws_flow_refusal(lang('Only the owner of the flow or the person who started the run can cancel it.'), 403);
    }

    if (!ws_flow_run_open($run['status'])) {
        return ws_flow_refusal(lang('This run has ended already.'));
    }

    $open = 0;
    $task_ids = array();

    foreach (ws_flow_run_steps($run['id']) as $step) {
        if (ws_flow_step_open($step['status'])) {
            $open++;

            if (ws_flow_effect_task_id($step['effect']) > 0) {
                $task_ids[] = ws_flow_effect_task_id($step['effect']);
            }
        }
    }

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'open' => $open, 'task_ids' => $task_ids);
}

/**
 * Cancels a run: the run ends cancelled and its open boxes are skipped. The
 * tasks it opened stay as they are: a person decides what becomes of them.
 * A box another request is carrying out at this moment finds the run over
 * and leaves it so; a task box looks again right before it opens its task,
 * and a task opened at that very moment is still written on its box
 * (ws_flow_step_task()), though this answer could not name it.
 *
 * @param array $viewer
 * @param array $run
 * @return array ok, error, status, field, open, task_ids
 */
function ws_flow_run_cancel($viewer, $run)
{
    $check = ws_flow_run_cancel_check($viewer, $run);

    if (!$check['ok']) {
        return $check;
    }

    $now = time();

    db("UPDATE ws_flow_runs SET status = 'cancelled', finished_at = '" . $now . "', updated_at = '" . $now . "'
        WHERE id = '" . (int) $run['id'] . "' AND status IN ('running', 'waiting')");

    if ((int) mysqli_affected_rows(db::$con) < 1) {
        return ws_flow_refusal(lang('This run has ended already.'), 409);
    }

    // A box waiting for its task says that the task stays.
    $name = ws_person_name($viewer['id']);

    db("UPDATE ws_flow_run_steps SET status = 'skipped', finished_at = '" . $now . "',
            result = '" . e(ws_flow_result_pack(lang(array('string' => 'Cancelled with the run by {var:1}; its task stays as it is.', 'vars' => $name)))) . "'
        WHERE run_id = '" . (int) $run['id'] . "' AND status IN ('ready', 'running', 'waiting') AND effect <> ''");

    db("UPDATE ws_flow_run_steps SET status = 'skipped', finished_at = '" . $now . "',
            result = '" . e(ws_flow_result_pack(lang(array('string' => 'Cancelled with the run by {var:1}.', 'vars' => $name)))) . "'
        WHERE run_id = '" . (int) $run['id'] . "' AND status IN ('ready', 'running', 'waiting')");

    $flow = ws_flow($run['flow_id']);

    log_activity(lang(array('string' => 'workspace flow ({var:1}) run {var:2} was cancelled', 'vars' => array($flow ? $flow['name'] : '', (int) $run['id']))), (string) ($_SESSION['sessionusername'] ?? ''));

    return $check;
}

/**
 * Whether the person may try a failed box of the run again or skip it: the
 * checks of ws_flow_step_mend(), made on their own so that the API's dry run
 * meets the same refusals. Only the flow's owner may: a box tried again runs
 * in their name and with their rights, as publishing does
 * (ws_flow_share_manager()).
 *
 * @param array  $viewer
 * @param array  $run
 * @param string $node_id the box
 * @return array ok, error, status, field, step (the box's row), behind (the boxes skipped behind it)
 */
function ws_flow_step_mend_check($viewer, $run, $node_id)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    $flow = ws_flow($run['flow_id']);

    if (ws_flow_run_access($viewer, $flow, $run) === '') {
        return ws_flow_refusal(lang('That run could not be found.'), 404);
    }

    if (!ws_flow_share_manager($viewer, $flow)) {
        return ws_flow_refusal(lang('Only the owner of the flow can try a box again or skip it.'), 403);
    }

    $rows = ws_flow_run_steps($run['id']);
    $step = null;

    foreach ($rows as $row) {
        if ((string) $row['node_id'] === (string) $node_id) {
            $step = $row;
        }
    }

    if (!$step) {
        return ws_flow_refusal(lang('That box has not been reached in this run.'), 404, 'node_id');
    }

    switch (ws_flow_step_mend_refusal($run['status'], $step['status'])) {
        case 'not_failed':
            return ws_flow_refusal(lang('Only a box that failed can be tried again or skipped.'), 422, 'node_id');

        case 'ended':
            return ws_flow_refusal(lang('This run has ended already.'));
    }

    return array(
        'ok'     => true,
        'error'  => '',
        'status' => 200,
        'field'  => '',
        'step'   => $step,
        'behind' => ws_flow_skipped_behind(ws_flow_run_graph($run), ws_flow_step_states($rows), $step['node_id']),
    );
}

/**
 * Tries a failed box of the run again ($how retry: it is ready again as a
 * new attempt, forgets what it began and the task it waited for, and the
 * boxes after it follow how it goes) or skips it ($how skip: the run goes on
 * as for a branch not taken). The box changes only from failed, so of two
 * clicks only one does it. The boxes the walk skipped behind it are taken
 * back and the run, reopened when it had failed, is walked again. A task
 * box tried again opens a new task: one its earlier tries opened stays as it
 * is. The box keeps the sentence it failed with in its result until it
 * settles again, and the activity log gets a line.
 *
 * @param array  $viewer
 * @param array  $run
 * @param string $node_id
 * @param string $how     retry | skip
 * @return array ok, error, status, field, step, behind
 */
function ws_flow_step_mend($viewer, $run, $node_id, $how)
{
    $check = ws_flow_step_mend_check($viewer, $run, $node_id);

    if (!$check['ok']) {
        return $check;
    }

    $now = time();
    $step = $check['step'];
    $name = ws_person_name($viewer['id']);
    $was = ws_flow_result_text($step['result']);

    if ($how === 'retry') {
        $text = lang(array('string' => 'Tried again by {var:1} after: {var:2}', 'vars' => array($name, $was)));

        db("UPDATE ws_flow_run_steps SET status = 'ready', outcome = '', effect = '', wait_event = '', wait_key = '', wake_at = 0,
                ready_at = '" . $now . "', claimed_at = 0, finished_at = 0, result = '" . e(ws_flow_result_pack($text)) . "'
            WHERE id = '" . (int) $step['id'] . "' AND status = 'failed'");
    } else {
        $text = lang(array('string' => 'Skipped by {var:1} after: {var:2}', 'vars' => array($name, $was)));

        db("UPDATE ws_flow_run_steps SET status = 'skipped', outcome = '', finished_at = '" . $now . "',
                result = '" . e(ws_flow_result_pack($text)) . "'
            WHERE id = '" . (int) $step['id'] . "' AND status = 'failed'");
    }

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return ws_flow_refusal(lang('The box was tried again or skipped at this moment already.'), 409);
    }

    // Only rows the walk wrote (never run): ws_flow_run_refresh() writes
    // them again as the box now stands.
    if (!empty($check['behind'])) {
        db("DELETE FROM ws_flow_run_steps WHERE run_id = '" . (int) $run['id'] . "'
            AND node_id IN ('" . implode("', '", array_map('e', $check['behind'])) . "')
            AND status = 'skipped' AND attempt = 0");
    }

    db("UPDATE ws_flow_runs SET status = 'running', finished_at = 0, updated_at = '" . $now . "'
        WHERE id = '" . (int) $run['id'] . "' AND status IN ('running', 'waiting', 'failed')");

    ws_flow_run_refresh((int) $run['id']);

    $flow = ws_flow($run['flow_id']);
    $node = ws_flow_graph_node(ws_flow_run_graph($run), $step['node_id']);
    $box = $node ? ws_flow_box_name($node) : (string) $step['node_id'];

    $vars = array($flow ? $flow['name'] : '', (int) $run['id'], $box);

    log_activity(($how === 'retry')
        ? lang(array('string' => 'workspace flow ({var:1}) run {var:2}: the box {var:3} was tried again', 'vars' => $vars))
        : lang(array('string' => 'workspace flow ({var:1}) run {var:2}: the box {var:3} was skipped', 'vars' => $vars)), (string) ($_SESSION['sessionusername'] ?? ''));

    if ($how === 'retry') {
        ws_flow_advance(WS_FLOW_BUDGET_TICK, (int) $run['id']);
    }

    return $check;
}

/**
 * Cancels runs that end without anybody cancelling them by hand (their flow
 * was deleted): each run ends cancelled and its open boxes are skipped with
 * the sentence given; the tasks they opened stay.
 *
 * @param int[]  $ids
 * @param string $text why, for the open boxes
 */
function ws_flow_runs_cancel_ids($ids, $text)
{
    $list = implode(',', array_map('intval', (array) $ids));

    if ($list === '') {
        return;
    }

    $now = time();

    db("UPDATE ws_flow_runs SET status = 'cancelled', finished_at = '" . $now . "', updated_at = '" . $now . "'
        WHERE id IN (" . $list . ") AND status IN ('running', 'waiting')");

    db("UPDATE ws_flow_run_steps SET status = 'skipped', finished_at = '" . $now . "', result = '" . e(ws_flow_result_pack($text)) . "'
        WHERE run_id IN (" . $list . ") AND status IN ('ready', 'running', 'waiting')");
}

/**
 * Ends the open runs of a flow being deleted: nobody could see or cancel them
 * any more. Their open boxes are skipped; the tasks they opened stay. Every
 * open run, a batch at a time: starts that met at the same moment may have
 * left more than WS_FLOW_MAX_OPEN_RUNS open. A run a start writes after this
 * meets the deleted flow at its first box (ws_flow_step_run()).
 *
 * @param array $flow
 * @return int how many runs ended
 */
function ws_flow_runs_cancel_for_flow($flow)
{
    if (!ws_flows_ready()) {
        return 0;
    }

    $ended = 0;
    $rounds = 0;

    do {
        $ids = array_map('intval', (array) db_values("SELECT id FROM ws_flow_runs
            WHERE flow_id = '" . (int) $flow['id'] . "' AND status IN ('running', 'waiting') ORDER BY id LIMIT 500"));

        ws_flow_runs_cancel_ids($ids, lang('Cancelled: the flow was deleted.'));
        $ended += count($ids);
    } while ((count($ids) === 500) && (++$rounds < 100));

    return $ended;
}

/**
 * Sweeps the runs that ended more than WS_FLOW_RUNS_KEEP ago, with their
 * boxes, a batch at a time (the Workspace's scheduled job).
 *
 * @param int $limit
 * @return int how many runs went
 */
function ws_flow_runs_sweep($limit = 200)
{
    if (!ws_flows_ready()) {
        return 0;
    }

    $ids = array_map('intval', (array) db_values("SELECT id FROM ws_flow_runs
        WHERE status IN ('done', 'failed', 'cancelled') AND updated_at < '" . (time() - WS_FLOW_RUNS_KEEP) . "'
        ORDER BY updated_at LIMIT " . max(1, (int) $limit)));

    if (empty($ids)) {
        return 0;
    }

    db("DELETE FROM ws_flow_run_steps WHERE run_id IN (" . implode(',', $ids) . ")");
    db("DELETE FROM ws_flow_runs WHERE id IN (" . implode(',', $ids) . ")");

    return count($ids);
}

/**
 * The settings of a box that starts another flow: the flow it starts.
 *
 * @param mixed $config
 * @return array flow_id (0 for none chosen)
 */
function ws_flow_chain_normalize($config)
{
    $flow_id = is_array($config) ? ($config['flow_id'] ?? 0) : 0;

    return array('flow_id' => (is_bool($flow_id) || !is_numeric($flow_id)) ? 0 : max(0, (int) $flow_id));
}

/**
 * Why a box may not start a flow now, '' when it may: a flow does not start
 * itself (flows have no loops), the flow must be live and started by hand
 * (a flow that starts on its own is not chained), and a chain stops at
 * WS_SCHEDULED_CHAIN_DEPTH runs that follow one another, as scheduled
 * actions do. Pure.
 *
 * @param int        $source_flow_id the flow whose box starts it
 * @param array|null $flow           the flow to start
 * @param string     $trigger_kind   the kind of its live version's start box
 * @param int        $depth          the depth of the run whose box starts it
 * @return string '' | self | gone | not_live | not_by_hand | depth
 */
function ws_flow_chain_refusal($source_flow_id, $flow, $trigger_kind, $depth)
{
    if (!is_array($flow)) {
        return 'gone';
    }

    if ((int) $flow['id'] === (int) $source_flow_id) {
        return 'self';
    }

    if (((string) $flow['status'] !== 'live') || ((int) $flow['version_id'] <= 0)) {
        return 'not_live';
    }

    if ((string) $trigger_kind !== 'manual') {
        return 'not_by_hand';
    }

    if ((int) $depth + 1 >= WS_SCHEDULED_CHAIN_DEPTH) {
        return 'depth';
    }

    return '';
}

/**
 * The refusal of ws_flow_chain_refusal() in words.
 *
 * @param string $refusal
 * @return string
 */
function ws_flow_chain_refusal_text($refusal)
{
    switch ($refusal) {
        case 'self':
            return lang('A flow cannot start itself.');

        case 'not_live':
            return lang('The flow to start is not live.');

        case 'not_by_hand':
            return lang('The flow to start is not started by hand.');

        case 'depth':
            return lang(array('string' => 'At most {var:1} runs can start one another in a chain.', 'vars' => WS_SCHEDULED_CHAIN_DEPTH));
    }

    return lang('The flow to start could not be found, or the owner of this flow may not run it.');
}

/**
 * The start key of the run a box starts: one per run, box and pass, so the
 * box carried out twice finds the run it started (start_key is unique per
 * flow, VARCHAR(64)).
 *
 * @param int    $run_id
 * @param string $node_id
 * @param int    $pass
 * @return string
 */
function ws_flow_chain_key($run_id, $node_id, $pass)
{
    return 'flow:' . (int) $run_id . '-' . (string) $node_id . '-' . (int) $pass;
}

/**
 * The context of a run another flow's box started: a manual run's
 * (ws_flow_run_context_new()) as trigger chain, what started the source run
 * carried on (the event, the newcomer: ws_step_trigger_context()), and the
 * source for {{source}} and {{source_result}}, as a scheduled action
 * started by another keeps them. Pure.
 *
 * @param array  $flow           the flow started
 * @param array  $trigger        its start box
 * @param array  $source_context ws_flow_run_context() of the run that started it
 * @param string $source         the name of the flow that started it
 * @param string $source_result  the sentence of the box that led to the start
 * @return array
 */
function ws_flow_chain_context($flow, $trigger, $source_context, $source, $source_result)
{
    $carried = ws_step_trigger_context($source_context);
    unset($carried['source'], $carried['source_result']);

    return array('trigger' => 'chain') + ws_flow_run_context_new($flow, $trigger) + $carried + array(
        'source'        => (string) $source,
        'source_result' => (string) $source_result,
    );
}

/**
 * The sentence of the box an arrow came from into this one, done in the
 * run: what {{source_result}} names in the run started. '' for none. Pure.
 *
 * @param array  $graph
 * @param string $node_id
 * @param array  $values ws_step_node_values()
 * @return string
 */
function ws_flow_chain_source_result($graph, $node_id, $values)
{
    foreach ((array) ($graph['edges'] ?? array()) as $edge) {
        $from = (string) $edge['from'];

        if (((string) $edge['to'] === (string) $node_id) && (($values[$from]['status'] ?? '') === 'done')) {
            return (string) $values[$from]['text'];
        }
    }

    return '';
}

/**
 * Checks a box that starts another flow with the owner's rights, as they
 * publish or try it: a flow they run or change, not this one, live and
 * started by hand. Depth and limits are the run's, asked as the box runs.
 *
 * @param array $viewer         the flow's owner
 * @param int   $source_flow_id the flow the box is in
 * @param array $config         ws_flow_chain_normalize()
 * @return array ok, error, flow (the flow to start, null when refused)
 */
function ws_flow_chain_check($viewer, $source_flow_id, $config)
{
    $flow = ws_flow((int) ws_flow_chain_normalize($config)['flow_id']);

    if (!in_array(ws_flow_access($viewer, $flow), array('run', 'edit'), true)) {
        return array('ok' => false, 'error' => ws_flow_chain_refusal_text('gone'), 'flow' => null);
    }

    $version = ((string) $flow['status'] === 'live') ? ws_flow_version($flow['version_id']) : null;
    $trigger = is_array($version) ? ws_flow_trigger_node(ws_flow_graph_of($version)) : null;
    $refusal = ws_flow_chain_refusal($source_flow_id, is_array($version) ? $flow : array('version_id' => 0) + $flow, $trigger ? (string) $trigger['kind'] : '', 0);

    if ($refusal !== '') {
        return array('ok' => false, 'error' => ws_flow_chain_refusal_text($refusal), 'flow' => null);
    }

    return array('ok' => true, 'error' => '', 'flow' => $flow);
}

/**
 * A box that starts another flow: as the flow's owner, who must be able to
 * run the flow started; never twice (the began mark, then the start key);
 * the run started one deeper in the chain, within the started flow's open
 * and hourly limits. The box does not wait for the run it started: it is
 * done once the run is written, and gives its id.
 *
 * @param array $run   ws_flow_runs row
 * @param array $step  ws_flow_run_steps row, running
 * @param array $node  the box
 * @param array $owner the flow's owner, on the staff
 */
function ws_flow_chain_start($run, $step, $node, $owner)
{
    db("UPDATE ws_flow_run_steps SET effect = '" . e(WS_STEP_BEGAN . 'start_flow') . "'
        WHERE id = '" . (int) $step['id'] . "' AND status = 'running'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return;
    }

    $graph = ws_flow_run_graph($run);
    $routed = ws_flow_outcome_routed($graph, $node['id'], 'failed');
    $fail = function ($text) use ($step, $routed) {
        $end = ws_step_settlement(false, $routed);
        ws_flow_step_settle($step, 'running', $end['status'], $end['outcome'], $text);
    };

    $flow = ws_flow((int) (ws_flow_chain_normalize($node['config'] ?? array())['flow_id']));

    // The owner's rights: a flow they run or change, never one they cannot
    // reach.
    if (!in_array(ws_flow_access($owner, $flow), array('run', 'edit'), true)) {
        $fail(ws_flow_chain_refusal_text('gone'));
        return;
    }

    $version = ((string) $flow['status'] === 'live') ? ws_flow_version($flow['version_id']) : null;
    $target = is_array($version) ? ws_flow_graph_of($version) : null;
    $trigger = $target ? ws_flow_trigger_node($target) : null;
    $refusal = ws_flow_chain_refusal((int) $run['flow_id'], is_array($version) ? $flow : array('version_id' => 0) + $flow, $trigger ? (string) $trigger['kind'] : '', (int) $run['depth']);

    if ($refusal !== '') {
        $fail(ws_flow_chain_refusal_text($refusal));
        return;
    }

    $flow_id = (int) $flow['id'];
    $start_key = ws_flow_chain_key($run['id'], $step['node_id'], $step['pass'] ?? 0);
    $started = function ($run_id) use ($step, $flow) {
        ws_flow_step_settle($step, 'running', 'done', 'done',
            lang(array('string' => 'flow “{var:1}” started, run {var:2}', 'vars' => array($flow['name'], (int) $run_id))), array('run_id' => (int) $run_id));
    };

    $existing = (int) db_value("SELECT id FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND start_key = '" . e($start_key) . "'");

    if ($existing > 0) {
        $started($existing);
        return;
    }

    $open = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND status IN ('running', 'waiting')");
    $recent = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND started_at > '" . (time() - 3600) . "'");

    switch (ws_flow_start_refusal($open, $recent)) {
        case 'open_runs':
            $fail(lang(array('string' => 'This flow has {var:1} runs open already; it starts again once some of them end.', 'vars' => WS_FLOW_MAX_OPEN_RUNS)));
            return;

        case 'hourly':
            $fail(lang(array('string' => 'This flow was started {var:1} times in the last hour; try again later.', 'vars' => WS_FLOW_STARTS_HOURLY)));
            return;
    }

    $source_context = ws_flow_run_context($run);
    $values = ws_step_node_values(db_items("SELECT node_id, status, outcome, result FROM ws_flow_run_steps
        WHERE run_id = '" . (int) $run['id'] . "' AND status IN ('done', 'failed', 'skipped')"));
    $context = json_encode(ws_flow_chain_context($flow, $trigger, $source_context, (string) ($source_context['flow_name'] ?? ''),
        ws_flow_chain_source_result($graph, $node['id'], $values)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $now = time();

    // Started by the owner of the flow whose box starts it, acting as the
    // owner of the flow started. Not db(): the duplicate start key of a box
    // carried out twice is an answer here.
    $written = @mysqli_query(db::$con, "INSERT INTO ws_flow_runs (flow_id, version_id, status, started_by, actor_id, start_key,
            subject_type, subject_id, context, depth, boxes_run, started_at, updated_at, finished_at)
        SELECT '" . $flow_id . "', '" . (int) $version['id'] . "', 'running', '" . (int) $run['actor_id'] . "', '" . (int) $flow['owner_id'] . "',
            '" . e($start_key) . "', '', 0, '" . e((string) $context) . "', '" . ((int) $run['depth'] + 1) . "', 0, '" . $now . "', '" . $now . "', 0
        FROM ws_flows WHERE id = '" . $flow_id . "' AND status = 'live'");

    if (!$written) {
        $existing = ((int) mysqli_errno(db::$con) === 1062)
            ? (int) db_value("SELECT id FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND start_key = '" . e($start_key) . "'")
            : 0;

        if ($existing > 0) {
            $started($existing);
        } else {
            $fail(lang('The run could not be started.'));
        }

        return;
    }

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        $fail(ws_flow_chain_refusal_text('not_live'));
        return;
    }

    $new_id = (int) mysqli_insert_id(db::$con);

    db("INSERT IGNORE INTO ws_flow_run_steps (run_id, node_id, pass, status, outcome, attempt, ready_at, claimed_at, finished_at, result)
        VALUES ('" . $new_id . "', '" . e((string) $trigger['id']) . "', 0, 'done', 'next', 1, '" . $now . "', '" . $now . "', '" . $now . "',
            '" . e(ws_flow_result_pack(lang(array('string' => 'Started by the flow “{var:1}”.', 'vars' => (string) ($source_context['flow_name'] ?? ''))))) . "')");

    log_activity(lang(array('string' => 'workspace flow ({var:1}) was started by the flow ({var:2}), run {var:3}', 'vars' => array($flow['name'], (string) ($source_context['flow_name'] ?? ''), $new_id))), (string) ($_SESSION['sessionusername'] ?? ''));

    ws_flow_run_refresh($new_id);
    $started($new_id);
}
