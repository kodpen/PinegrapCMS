<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - flows: a process drawn as boxes and arrows. A box that starts
 * the flow, the tasks it opens one after another, an end; an arrow leaves a
 * box on one of its outcomes (a task is done, or cancelled) and leads to the
 * box that comes after it. Several arrows into one box make it wait for all
 * of them (a join).
 *
 * A flow is kept as a draft that its owner edits (ws_flows.draft, graph JSON)
 * and the versions published from it (ws_flow_versions), which are never
 * changed afterwards: a run keeps the version it started on. Every save of
 * the draft raises draft_rev, and a save made against an older draft_rev is
 * refused, so two screens of the same person never write over each other.
 *
 * The graph is read and written in PHP only (no JSON in SQL):
 *   {"v": 1,
 *    "nodes": [{"id", "kind", "label", "x", "y", "config"}],
 *    "edges": [{"from", "to", "on"}],
 *    "notes": [{"id", "text", "x", "y", "w", "h"}]}
 * ws_flow_graph_normalize() keeps it in that shape and ws_flow_graph_check()
 * lists what stops it from being published; both are pure. A draft may hold
 * problems, a published version never does.
 *
 * Staff in the team build flows (ws_can_schedule()) and a flow is its owner's,
 * as a note is: being an administrator gives no view of somebody else's
 * flow. A live flow with a home channel may be run by the people who read
 * that channel, and its owner may share it with colleagues and channels
 * (includes/workspace/flow_shares.php). Site members never reach a flow.
 * Publishing is the person's own decision, since a published flow later acts
 * in its owner's name.
 *
 * The canvas that draws a flow is workspace_flows.php with
 * assets/js/workspace_flow.js. Its runs are carried out by
 * includes/workspace/flow_runs.php. The boxes beyond start, task and end come
 * later on the same tables (ws_flow_runs, ws_flow_run_steps).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most boxes a flow holds. */
define('WS_FLOW_MAX_NODES', 100);

/** The most notes a flow's canvas holds. */
define('WS_FLOW_MAX_NOTES', 200);

/** The largest graph, in bytes of JSON. */
define('WS_FLOW_GRAPH_BYTES', 262144);

/** The most flows one person keeps, deleted ones left out. */
define('WS_FLOW_MAX_PER_OWNER', 200);

/** How far a box or a note may stand from the middle of the canvas. */
define('WS_FLOW_COORD_MAX', 100000);

/**
 * Are the flow tables there (2026.11.0, 11.0.80)? The step creates
 * ws_flow_run_steps last and adds ws_scheduled_actions.flow_id after it.
 *
 * @return bool
 */
function ws_flows_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has')
            && pg_schema_has('ws_flow_run_steps')
            && pg_schema_has('ws_scheduled_actions', 'flow_id');
    }

    return $ready;
}

/* ---------------------------------------------------------------------------
   The graph (pure)
   --------------------------------------------------------------------------- */

/**
 * The kinds of box a flow knows: whether the box starts the flow, the
 * outcomes an arrow may leave it on, and the values it gives the boxes after
 * it ({{node.<id>.<key>}}; key, type, label): a task box its task, a step
 * box those of ws_step_kinds().
 *
 * A value box (pure) has no arrows: it only gives values, read when a box
 * reading them runs (flow_values.php).
 *
 * @return array kind => label, trigger, outcomes, step, outputs; pure on a value box
 */
function ws_flow_kinds()
{
    $kinds = array(
        'manual' => array('label' => lang('Started by hand'), 'trigger' => true, 'outcomes' => array('next'), 'step' => false, 'outputs' => array()),
        'task'   => array('label' => lang('Task'), 'trigger' => false, 'outcomes' => array('done', 'cancelled', 'timeout'), 'step' => false,
            'outputs' => array(array('key' => 'task_id', 'type' => 'number', 'label' => lang('Task')))),
        'end'    => array('label' => lang('End'), 'trigger' => false, 'outcomes' => array(), 'step' => false, 'outputs' => array()),
    );

    // The boxes that start a flow on their own, through its anchor
    // (flow_anchors.php).
    foreach ((function_exists('ws_flow_anchor_kinds') ? ws_flow_anchor_kinds() : array()) as $kind => $info) {
        $kinds[$kind] = array('label' => $info['label'], 'trigger' => true, 'outcomes' => array('next'), 'step' => false, 'outputs' => array());
    }

    // One condition of a scheduled action (flow_conditions.php).
    if (function_exists('ws_flow_condition_outcomes')) {
        $kinds['condition'] = array('label' => lang('Condition'), 'trigger' => false, 'outcomes' => ws_flow_condition_outcomes(), 'step' => false, 'outputs' => array());
    }

    // A wait before the boxes after it (flow_runs.php).
    if (function_exists('ws_flow_delay_normalize')) {
        $kinds['delay'] = array('label' => lang('Wait'), 'trigger' => false, 'outcomes' => array('done'), 'step' => false,
            'outputs' => array(array('key' => 'waited_until', 'type' => 'text', 'label' => lang('Waited until'))));
    }

    // Another flow started, without waiting for it (flow_runs.php).
    if (function_exists('ws_flow_chain_normalize')) {
        $kinds['start_flow'] = array('label' => lang('Start a flow'), 'trigger' => false, 'outcomes' => array('done', 'failed'), 'step' => false,
            'outputs' => array(array('key' => 'run_id', 'type' => 'number', 'label' => lang('Run'))));
    }

    // Values written by hand, given to the boxes that read them (flow_values.php).
    if (function_exists('ws_flow_constants_normalize')) {
        $kinds['constants'] = array('label' => lang('Constants'), 'trigger' => false, 'outcomes' => array(), 'step' => false, 'outputs' => array(), 'pure' => true);
        $kinds['value'] = array('label' => lang('Derived value'), 'trigger' => false, 'outcomes' => array(), 'step' => false,
            'outputs' => array(array('key' => 'value', 'type' => 'text', 'label' => lang('Value'))), 'pure' => true);
    }

    // The steps of a scheduled action, each a box of its own (steps.php).
    foreach ((function_exists('ws_step_kinds') ? ws_step_kinds() : array()) as $kind => $info) {
        $kinds[$kind] = array('label' => $info['label'], 'trigger' => false, 'outcomes' => $info['outcomes'] ?? ws_step_outcomes(), 'step' => true, 'outputs' => $info['outputs']);
    }

    return $kinds;
}

/**
 * The outcomes an arrow may leave a box of this kind on: its own, and
 * always (any of them) for a box that is not a start and has any. Pure.
 *
 * @param array $kind ws_flow_kinds() entry
 * @return string[]
 */
function ws_flow_arrow_outcomes($kind)
{
    $outcomes = (array) ($kind['outcomes'] ?? array());

    return (empty($kind['trigger']) && !empty($outcomes)) ? array_merge($outcomes, array('always')) : $outcomes;
}

/**
 * Is this a box id a flow may use? It names the box in a run's steps
 * (ws_flow_run_steps.node_id) and in the values later boxes read.
 *
 * @param mixed $id
 * @return bool
 */
function ws_flow_node_id_valid($id)
{
    return is_string($id) && (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $id) === 1);
}

/**
 * A graph with nothing on it.
 *
 * @return array
 */
function ws_flow_graph_empty()
{
    return array('v' => 1, 'nodes' => array(), 'edges' => array(), 'notes' => array());
}

/**
 * A place on the canvas: a whole number within WS_FLOW_COORD_MAX of the
 * middle, 0 for anything that is not a number.
 *
 * @param mixed $value
 * @param int   $min
 * @return int
 */
function ws_flow_coord($value, $min = -WS_FLOW_COORD_MAX)
{
    if (is_bool($value) || !is_numeric($value)) {
        return max($min, 0);
    }

    return (int) max($min, min(WS_FLOW_COORD_MAX, round((float) $value)));
}

/**
 * A value as valid UTF-8 text: '' for anything but a string or a number, and
 * a byte that is not part of a character turned into ?, so that the graph
 * can always be written as JSON.
 *
 * @param mixed $value
 * @return string
 */
function ws_flow_text($value)
{
    if (!is_scalar($value) || is_bool($value)) {
        return '';
    }

    $text = (string) $value;

    return mb_check_encoding($text, 'UTF-8') ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8');
}

/**
 * A name in the graph (an id, a kind, an outcome): trimmed and cut to its
 * length in characters.
 *
 * @param mixed $value
 * @param int   $length
 * @return string
 */
function ws_flow_token($value, $length)
{
    return mb_substr(trim(ws_flow_text($value)), 0, $length);
}

/**
 * A short text on one line: control characters made spaces, runs of spaces
 * made one, cut to its length.
 *
 * @param mixed $value
 * @param int   $length
 * @return string
 */
function ws_flow_line($value, $length)
{
    $text = trim((string) preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', ws_flow_text($value)));

    return mb_substr($text, 0, $length);
}

/**
 * A text of several lines: line ends made \n, control characters other than
 * tabs and line ends taken out, cut to its length.
 *
 * @param mixed $value
 * @param int   $length
 * @return string
 */
function ws_flow_lines($value, $length)
{
    $text = str_replace("\r\n", "\n", ws_flow_text($value));

    return mb_substr(trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text)), 0, $length);
}

/**
 * The settings of one box, as its kind keeps them; unknown keys are dropped.
 *
 * manual: subject ('' or a kind of record a run is about)
 * task:   title, description, assignees (up to 20 user ids) or assign (a
 *         rule of ws_template_assign_valid(), 'creator' being whoever starts
 *         the run), due_in ('' or 0-3650 days), priority, timeout ('' for
 *         none, else 1 to WS_FLOW_TIMEOUT_MAX hours: the box leaves on
 *         timeout when its task is still open by then)
 * condition: ws_flow_condition_normalize()
 * delay: ws_flow_delay_normalize()
 * start_flow: ws_flow_chain_normalize()
 * schedule, event, join: ws_flow_anchor_normalize()
 * a step: ws_step_config_normalize()
 *
 * @param string $kind
 * @param mixed  $config
 * @return array
 */
function ws_flow_config_normalize($kind, $config)
{
    $config = is_array($config) ? $config : array();

    switch ($kind) {
        case 'manual':
            $subject = strtolower(ws_flow_text($config['subject'] ?? ''));

            return array('subject' => substr((string) preg_replace('/[^a-z_]/', '', $subject), 0, 32));

        case 'task':
            $assignees = array();

            foreach ((is_array($config['assignees'] ?? null) ? $config['assignees'] : array()) as $user_id) {
                if (is_numeric($user_id) && ((int) $user_id > 0)) {
                    $assignees[(int) $user_id] = (int) $user_id;
                }
            }

            $due_in = $config['due_in'] ?? '';
            $priority = ws_flow_text($config['priority'] ?? '');
            $timeout = $config['timeout'] ?? '';

            return array(
                'title'       => ws_flow_line($config['title'] ?? '', 200),
                'description' => ws_flow_lines($config['description'] ?? '', 8000),
                'assignees'   => array_slice(array_values($assignees), 0, 20),
                'assign'      => ws_flow_line($config['assign'] ?? '', 40),
                'due_in'      => (is_bool($due_in) || !is_numeric($due_in)) ? '' : max(0, min(3650, (int) $due_in)),
                'priority'    => in_array($priority, array('low', 'normal', 'high', 'urgent'), true) ? $priority : 'normal',
                'timeout'     => (is_bool($timeout) || !is_numeric($timeout)) ? '' : max(1, min(defined('WS_FLOW_TIMEOUT_MAX') ? WS_FLOW_TIMEOUT_MAX : 720, (int) $timeout)),
            );

        case 'condition':
            return function_exists('ws_flow_condition_normalize') ? ws_flow_condition_normalize($config) : array();

        case 'delay':
            return function_exists('ws_flow_delay_normalize') ? ws_flow_delay_normalize($config) : array();

        case 'start_flow':
            return function_exists('ws_flow_chain_normalize') ? ws_flow_chain_normalize($config) : array();

        case 'constants':
            return function_exists('ws_flow_constants_normalize') ? ws_flow_constants_normalize($config) : array();

        case 'value':
            return function_exists('ws_flow_derived_normalize') ? ws_flow_derived_normalize($config) : array();

        case 'schedule':
        case 'event':
        case 'join':
        case 'row':
            return function_exists('ws_flow_anchor_normalize') ? ws_flow_anchor_normalize($kind, $config) : array();
    }

    if (!function_exists('ws_step_config_normalize')) {
        return array();
    }

    // A step box may try again on its own after a failure (flow_runs.php).
    $step = ws_step_config_normalize($kind, $config);

    return (function_exists('ws_step_kinds') && isset(ws_step_kinds()[$kind])) ? $step + ws_flow_retry_normalize($config) : $step;
}

/**
 * A graph in its kept shape, from an array or its JSON: types cast, unknown
 * keys dropped, labels cut to 160 characters and note texts to 2,000, places
 * held within WS_FLOW_COORD_MAX. An arrow sent without an outcome leaves its
 * box on the box's first one (a start box on next, a task when it is done).
 * Nothing is refused here; ws_flow_graph_check() says what is wrong.
 *
 * @param array|string $graph
 * @return array v, nodes, edges, notes
 */
function ws_flow_graph_normalize($graph)
{
    if (is_string($graph)) {
        $graph = json_decode($graph, true);
    }

    if (!is_array($graph)) {
        return ws_flow_graph_empty();
    }

    $kinds = ws_flow_kinds();
    $out = ws_flow_graph_empty();
    $kind_of = array();

    foreach ((is_array($graph['nodes'] ?? null) ? $graph['nodes'] : array()) as $node) {
        if (!is_array($node)) {
            continue;
        }

        $id = ws_flow_token($node['id'] ?? '', 64);
        $kind = ws_flow_token($node['kind'] ?? '', 32);

        $out['nodes'][] = array(
            'id'     => $id,
            'kind'   => $kind,
            'label'  => ws_flow_line($node['label'] ?? '', 160),
            'x'      => ws_flow_coord($node['x'] ?? 0),
            'y'      => ws_flow_coord($node['y'] ?? 0),
            'config' => ws_flow_config_normalize($kind, $node['config'] ?? array()),
        );

        if (!isset($kind_of[$id])) {
            $kind_of[$id] = $kind;
        }
    }

    foreach ((is_array($graph['edges'] ?? null) ? $graph['edges'] : array()) as $edge) {
        if (!is_array($edge)) {
            continue;
        }

        $from = ws_flow_token($edge['from'] ?? '', 64);
        $on = ws_flow_token($edge['on'] ?? '', 32);

        if (($on === '') && isset($kind_of[$from], $kinds[$kind_of[$from]]) && !empty($kinds[$kind_of[$from]]['outcomes'])) {
            $on = $kinds[$kind_of[$from]]['outcomes'][0];
        }

        $out['edges'][] = array(
            'from' => $from,
            'to'   => ws_flow_token($edge['to'] ?? '', 64),
            'on'   => $on,
        );
    }

    foreach ((is_array($graph['notes'] ?? null) ? $graph['notes'] : array()) as $note) {
        if (!is_array($note)) {
            continue;
        }

        $out['notes'][] = array(
            'id'   => ws_flow_token($note['id'] ?? '', 64),
            'text' => ws_flow_lines($note['text'] ?? '', 2000),
            'x'    => ws_flow_coord($note['x'] ?? 0),
            'y'    => ws_flow_coord($note['y'] ?? 0),
            'w'    => ws_flow_coord($note['w'] ?? 0, 0),
            'h'    => ws_flow_coord($note['h'] ?? 0, 0),
        );
    }

    return $out;
}

/**
 * How a box is called in a problem: its label, or its id when it has none.
 *
 * @param array $node
 * @return string
 */
function ws_flow_box_name($node)
{
    return ((string) $node['label'] !== '') ? (string) $node['label'] : (string) $node['id'];
}

/**
 * One problem of a graph.
 *
 * @param string      $code
 * @param string|null $node the box it is about
 * @param int|null    $edge the place of the arrow it is about in edges
 * @param string      $text
 * @return array code, node, edge, text
 */
function ws_flow_problem($code, $node, $edge, $text, $warning = false)
{
    $problem = array('code' => $code, 'node' => $node, 'edge' => $edge, 'text' => $text);

    return $warning ? $problem + array('warning' => true) : $problem;
}

/**
 * The texts of a box a run fills {{node.<id>.<field>}} into: a step box's
 * texts (ws_step_kinds()), a task box's title and description, the value a
 * condition box compares. Pure.
 *
 * @param array $node ws_flow_graph_normalize() box
 * @return string[]
 */
function ws_flow_node_texts($node)
{
    $kind = (string) $node['kind'];

    // A condition on a value reads that one value.
    if ($kind === 'condition') {
        $rule = (array) ($node['config']['rule'] ?? array());

        return ((($rule['type'] ?? '') === 'value') && is_string($rule['ref'] ?? null) && ($rule['ref'] !== '')) ? array($rule['ref']) : array();
    }

    // A derived value box reads its two inputs.
    if ($kind === 'value') {
        return array_values(array_filter(array($node['config']['a'] ?? null, $node['config']['b'] ?? null), 'is_string'));
    }

    $keys = ($kind === 'task') ? array('title', 'description') : ((function_exists('ws_step_kinds') ? (ws_step_kinds()[$kind]['texts'] ?? array()) : array()));
    $out = array();

    foreach ($keys as $key) {
        if (isset($node['config'][$key]) && is_string($node['config'][$key])) {
            $out[] = $node['config'][$key];
        }
    }

    return $out;
}

/**
 * The fields {{node.<id>.<field>}} reads of a box: text, outcome and status
 * of every box, and the values it declares (a step box's as it is set up:
 * ws_step_outputs()). Pure.
 *
 * @param array $node  ws_flow_graph_normalize() box
 * @param array $kinds ws_flow_kinds()
 * @return string[]
 */
function ws_flow_node_fields($node, $kinds)
{
    $kind = (string) $node['kind'];

    // A constants box gives its rows only: it never runs, so it has no
    // text, outcome or status.
    if ($kind === 'constants') {
        return function_exists('ws_flow_constants_outputs') ? array_column(ws_flow_constants_outputs($node['config']), 'key') : array();
    }

    if ($kind === 'value') {
        return array('value');
    }

    $outputs = (!empty($kinds[$kind]['step']) && function_exists('ws_step_outputs')) ? ws_step_outputs($kind, $node['config']) : ($kinds[$kind]['outputs'] ?? array());

    return array_merge(array('text', 'outcome', 'status'), array_column($outputs, 'key'));
}

/**
 * The fields {{trigger.<field>}} reads of a flow: those its start box
 * declares (ws_flow_trigger_outputs()), none without one. Pure.
 *
 * @param array $nodes ws_flow_graph_normalize() boxes
 * @param array $kinds ws_flow_kinds()
 * @return string[]
 */
function ws_flow_trigger_fields($nodes, $kinds)
{
    foreach ($nodes as $node) {
        if (!empty($kinds[(string) $node['kind']]['trigger'])) {
            return function_exists('ws_flow_trigger_outputs') ? array_column(ws_flow_trigger_outputs((string) $node['kind'], (array) $node['config']), 'key') : array();
        }
    }

    return array();
}

/**
 * The boxes that have surely run, done, whenever a box runs, by box: its own
 * and those every arrow into it brings (a box runs after one arrow in was
 * taken, the box it leaves done), and the boxes before it an arrow that is
 * always taken leads into from such a box. The walk (ws_flow_walk()) waits
 * for every box before a box to settle and skips a box behind a failure, so
 * a box before it with such an arrow in has run, done. A box on a loop is
 * left out. Pure.
 *
 * @param array $next   node id => ids an arrow leads into
 * @param array $always node id => id => true: an arrow taken whenever its box is done
 * @return array node id => node id => true
 */
function ws_flow_sure_before($next, $always)
{
    $into = array_fill_keys(array_keys($next), array());

    foreach ($next as $from => $tos) {
        foreach ($tos as $to) {
            $into[$to][] = $from;
        }
    }

    // The boxes in an order of their arrows (Kahn).
    $left = array_map('count', $into);
    $queue = array_keys(array_filter($left, function ($count) {
        return $count === 0;
    }));
    $order = array();

    while (!empty($queue)) {
        $id = array_shift($queue);
        $order[] = $id;

        foreach ($next[$id] as $to) {
            if (--$left[$to] === 0) {
                $queue[] = $to;
            }
        }
    }

    $sure = array();

    foreach ($order as $id) {
        $before = ws_flow_boxes_before($into, $id);
        $set = null;

        foreach ($into[$id] as $from) {
            $set = ($set === null) ? $sure[$from] : array_intersect_key($set, $sure[$from]);
        }

        $set = (array) $set + array($id => true);

        do {
            $grew = false;

            foreach ($before as $other => $unused) {
                if (isset($set[$other])) {
                    continue;
                }

                foreach ($into[$other] as $from) {
                    if (isset($set[$from]) && !empty($always[$from][$other])) {
                        $set += $sure[$other];
                        $grew = true;
                        break;
                    }
                }
            }
        } while ($grew);

        $sure[$id] = $set;
    }

    return $sure;
}

/**
 * The boxes some arrows lead from to a box: those before it. Pure.
 *
 * @param array  $into node id => ids an arrow comes from
 * @param string $id
 * @return array node id => true
 */
function ws_flow_boxes_before($into, $id)
{
    $before = array();
    $queue = array($id);

    while (!empty($queue)) {
        foreach ($into[array_shift($queue)] ?? array() as $from) {
            if (!isset($before[$from])) {
                $before[$from] = true;
                $queue[] = $from;
            }
        }
    }

    return $before;
}

/**
 * Whether a flow starts when somebody joins a channel: a join start box is
 * in its graph. Its post boxes may then write in the channel joined, as a
 * scheduled action on a join may. Pure.
 *
 * @param array $graph
 * @return bool
 */
function ws_flow_starts_on_join($graph)
{
    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if ((string) ($node['kind'] ?? '') === 'join') {
            return true;
        }
    }

    return false;
}

/**
 * What stops a graph from being published, in the order a reader fixes it:
 * too_many, bad_id, duplicate_id, unknown_kind, bad_config, no_trigger,
 * two_triggers, bad_edge, arrow_into_trigger, bad_outcome, duplicate_edge,
 * cycle, unreachable, bad_reference (a {{node.<id>.<field>}} of a box that
 * is not there, not before the reader, or a field the box does not give).
 * A reference to a box that may not have run when the reader runs (a branch
 * of a condition, a step's done) is a warning, skippable_reference with
 * warning true, which does not stop publishing: the value is then empty.
 * Notes are never checked. A kind of record a start box names is checked by
 * ws_flow_problems(), which knows the site's records.
 *
 * @param array|string $graph
 * @return array[] code, node, edge, text, warning on a warning (none for a graph that may be published)
 */
function ws_flow_graph_check($graph)
{
    $graph = ws_flow_graph_normalize($graph);
    $kinds = ws_flow_kinds();
    $problems = array();

    if (count($graph['nodes']) > WS_FLOW_MAX_NODES) {
        $problems[] = ws_flow_problem('too_many', null, null, lang(array('string' => 'A flow can hold at most {var:1} boxes.', 'vars' => WS_FLOW_MAX_NODES)));
    }

    if (count($graph['notes']) > WS_FLOW_MAX_NOTES) {
        $problems[] = ws_flow_problem('too_many', null, null, lang(array('string' => 'A flow can hold at most {var:1} notes.', 'vars' => WS_FLOW_MAX_NOTES)));
    }

    // The boxes, by id; a second box with an id already taken is not one an
    // arrow can reach.
    $nodes = array();
    $triggers = array();
    $joined = ws_flow_starts_on_join($graph);

    foreach ($graph['nodes'] as $node) {
        $id = $node['id'];
        $name = ws_flow_box_name($node);

        if (!ws_flow_node_id_valid($id)) {
            $problems[] = ws_flow_problem('bad_id', ($id !== '') ? $id : null, null, lang(array('string' => 'The box {var:1} needs an id of small letters, digits and _ that starts with a letter, at most 32 characters.', 'vars' => ($name !== '') ? $name : '?')));
        }

        if (isset($nodes[$id])) {
            $problems[] = ws_flow_problem('duplicate_id', $id, null, lang(array('string' => 'Two boxes have the id {var:1}.', 'vars' => $id)));
            continue;
        }

        $nodes[$id] = $node;

        if (!isset($kinds[$node['kind']])) {
            $problems[] = ws_flow_problem('unknown_kind', $id, null, lang(array('string' => 'The box {var:1} is of a kind the flow does not know ({var:2}).', 'vars' => array($name, $node['kind']))));
            continue;
        }

        if ($kinds[$node['kind']]['trigger']) {
            $triggers[] = $id;
        }

        if ($node['kind'] === 'task') {
            $config = $node['config'];

            if ($config['title'] === '') {
                $problems[] = ws_flow_problem('bad_config', $id, null, lang(array('string' => 'The task box {var:1} needs a title.', 'vars' => $name)));
            }

            if (($config['assign'] !== '') && !empty($config['assignees'])) {
                $problems[] = ws_flow_problem('bad_config', $id, null, lang(array('string' => 'The task box {var:1} names both people and a rule for whom it goes to; keep one of the two.', 'vars' => $name)));
            } elseif (($config['assign'] !== '') && !ws_template_assign_valid($config['assign'])) {
                $problems[] = ws_flow_problem('bad_config', $id, null, lang(array('string' => 'The task box {var:1} has a rule for whom it goes to that the flow does not know.', 'vars' => $name)));
            }

            if (function_exists('ws_flow_timeout_problems')) {
                foreach (ws_flow_timeout_problems($config, $name, ws_flow_outcome_routed($graph, $id, 'timeout', false)) as $text) {
                    $problems[] = ws_flow_problem('bad_config', $id, null, $text);
                }
            }
        }

        if ($kinds[$node['kind']]['step']) {
            foreach (array_merge(ws_step_config_problems($node['kind'], $node['config'], $name, $joined), ws_flow_retry_problems($node['config'], $name)) as $text) {
                $problems[] = ws_flow_problem('bad_config', $id, null, $text);
            }
        }

        if (in_array($node['kind'], array('schedule', 'event', 'join', 'row'), true)) {
            foreach (ws_flow_anchor_problems($node['kind'], $node['config'], $name) as $text) {
                $problems[] = ws_flow_problem('bad_config', $id, null, $text);
            }
        }

        if ($node['kind'] === 'condition') {
            foreach (ws_flow_condition_problems($node['config'], $name) as $text) {
                $problems[] = ws_flow_problem('bad_config', $id, null, $text);
            }
        }

        if ($node['kind'] === 'delay') {
            foreach (ws_flow_delay_problems($node['config'], $name) as $text) {
                $problems[] = ws_flow_problem('bad_config', $id, null, $text);
            }
        }

        if ($node['kind'] === 'constants') {
            foreach (ws_flow_constants_problems($node['config'], $name) as $text) {
                $problems[] = ws_flow_problem('bad_config', $id, null, $text);
            }
        }

        if ($node['kind'] === 'value') {
            foreach (ws_flow_derived_problems($node['config'], $name) as $text) {
                $problems[] = ws_flow_problem('bad_config', $id, null, $text);
            }
        }

        if (($node['kind'] === 'start_flow') && ((int) ($node['config']['flow_id'] ?? 0) <= 0)) {
            $problems[] = ws_flow_problem('bad_config', $id, null, lang(array('string' => 'The box {var:1} needs the flow to start.', 'vars' => $name)));
        }
    }

    foreach ((function_exists('ws_flow_derived_loops') ? ws_flow_derived_loops($nodes) : array()) as $id) {
        $problems[] = ws_flow_problem('bad_config', $id, null, lang(array('string' => 'The box {var:1} reads its own value, through other value boxes: it cannot be worked out.', 'vars' => ws_flow_box_name($nodes[$id]))));
    }

    if (empty($triggers)) {
        $problems[] = ws_flow_problem('no_trigger', null, null, lang('The flow has no box that starts it.'));
    }

    foreach (array_slice($triggers, 1) as $id) {
        $problems[] = ws_flow_problem('two_triggers', $id, null, lang(array('string' => 'A flow is started by one box; {var:1} is a second one.', 'vars' => ws_flow_box_name($nodes[$id]))));
    }

    // The arrows. One that joins two boxes that are there counts for the
    // order of the boxes even when its outcome is wrong, so a loop is still
    // found; one that is there twice counts once.
    $next = array_fill_keys(array_keys($nodes), array());
    $into = array_fill_keys(array_keys($nodes), 0);
    $seen = array();
    $always = array();

    foreach ($graph['edges'] as $index => $edge) {
        $from = $edge['from'];
        $to = $edge['to'];

        if (!isset($nodes[$from]) || !isset($nodes[$to])) {
            $problems[] = ws_flow_problem('bad_edge', isset($nodes[$from]) ? $from : null, $index, lang('An arrow joins a box that is not there.'));
            continue;
        }

        if ($from === $to) {
            $problems[] = ws_flow_problem('bad_edge', $from, $index, lang(array('string' => 'An arrow joins the box {var:1} to itself.', 'vars' => ws_flow_box_name($nodes[$from]))));
            continue;
        }

        // A value box gives values through threads only: no arrow leads into
        // it (one leaving it has no outcome to leave on).
        if (!empty($kinds[$nodes[$to]['kind']]['pure'])) {
            $problems[] = ws_flow_problem('bad_edge', $to, $index, lang(array('string' => 'No arrow may lead into the box {var:1}: it gives its values to the boxes that read them.', 'vars' => ws_flow_box_name($nodes[$to]))));
        }

        if (in_array($to, $triggers, true)) {
            $problems[] = ws_flow_problem('arrow_into_trigger', $to, $index, lang(array('string' => 'No arrow may lead into the box that starts the flow ({var:1}).', 'vars' => ws_flow_box_name($nodes[$to]))));
        }

        $kind = $nodes[$from]['kind'];

        if (isset($kinds[$kind]) && !in_array($edge['on'], ws_flow_arrow_outcomes($kinds[$kind]), true)) {
            $problems[] = ws_flow_problem('bad_outcome', $from, $index, lang(array('string' => 'An arrow leaves the box {var:1} on {var:2}, which is not one of its outcomes.', 'vars' => array(ws_flow_box_name($nodes[$from]), ($edge['on'] !== '') ? $edge['on'] : '-'))));
        }

        $key = $from . "\n" . $to . "\n" . $edge['on'];

        if (isset($seen[$key])) {
            $problems[] = ws_flow_problem('duplicate_edge', $from, $index, lang(array('string' => 'Two arrows join {var:1} to {var:2} on the same outcome.', 'vars' => array(ws_flow_box_name($nodes[$from]), ws_flow_box_name($nodes[$to])))));
            continue;
        }

        $seen[$key] = true;

        // Taken whenever its box is done: on always, or on the one outcome
        // the box has.
        $outcomes = (array) ($kinds[$kind]['outcomes'] ?? array());

        if (($edge['on'] === 'always') || ((count($outcomes) === 1) && ($edge['on'] === $outcomes[0]))) {
            $always[$from][$to] = true;
        }

        if (!in_array($to, $next[$from], true)) {
            $next[$from][] = $to;
            $into[$to]++;
        }
    }

    // Loops (Kahn): the boxes no order can place. Of those, the ones that
    // only follow a loop are taken away again from the other end, so the
    // boxes named are the ones on it.
    $left = $into;
    $queue = array_keys(array_filter($left, function ($count) {
        return $count === 0;
    }));

    while (!empty($queue)) {
        $id = array_shift($queue);
        unset($left[$id]);

        foreach ($next[$id] as $to) {
            if (isset($left[$to]) && (--$left[$to] === 0)) {
                $queue[] = $to;
            }
        }
    }

    do {
        $pruned = false;

        foreach (array_keys($left) as $id) {
            if (empty(array_intersect($next[$id], array_keys($left)))) {
                unset($left[$id]);
                $pruned = true;
            }
        }
    } while ($pruned);

    foreach (array_keys($left) as $id) {
        $problems[] = ws_flow_problem('cycle', (string) $id, null, lang(array('string' => 'The box {var:1} is in a loop: the arrows lead back to it.', 'vars' => ws_flow_box_name($nodes[$id]))));
    }

    // Boxes no arrow from the start leads to, while there is one start.
    if (count($triggers) === 1) {
        $reached = array($triggers[0] => true);
        $queue = array($triggers[0]);

        while (!empty($queue)) {
            foreach ($next[array_shift($queue)] as $to) {
                if (!isset($reached[$to])) {
                    $reached[$to] = true;
                    $queue[] = $to;
                }
            }
        }

        foreach ($nodes as $id => $node) {
            if (!isset($reached[$id]) && isset($kinds[$node['kind']]) && empty($kinds[$node['kind']]['pure'])) {
                $problems[] = ws_flow_problem('unreachable', (string) $id, null, lang(array('string' => 'No arrow from the start leads to the box {var:1}.', 'vars' => ws_flow_box_name($node))));
            }
        }
    }

    return array_merge($problems, ws_flow_reference_problems($nodes, $kinds, $next, $always));
}

/**
 * The {{node.<id>.<field>}} of each box's texts: bad_reference for a box the
 * flow does not have, one not before the reader, a field that box does not
 * give; a warning for a box that may not have run when the reader runs
 * (ws_flow_sure_before()). Once per reference and box. Pure.
 *
 * @param array $nodes  node id => box
 * @param array $kinds  ws_flow_kinds()
 * @param array $next   node id => ids an arrow leads into
 * @param array $always node id => id => true (ws_flow_sure_before())
 * @return array[] ws_flow_problem()
 */
function ws_flow_reference_problems($nodes, $kinds, $next, $always)
{
    $problems = array();
    $into = array_fill_keys(array_keys($nodes), array());
    $sure = null;

    foreach ($next as $from => $tos) {
        foreach ($tos as $to) {
            $into[$to][] = $from;
        }
    }

    foreach ($nodes as $id => $node) {
        if (!isset($kinds[$node['kind']])) {
            continue;
        }

        $refs = array();
        $trigger_refs = array();

        foreach (ws_flow_node_texts($node) as $text) {
            if (preg_match_all('/\{\{node\.([a-z][a-z0-9_]{0,31})\.([a-z_][a-z0-9_]{0,31})\}\}/', $text, $found, PREG_SET_ORDER)) {
                foreach ($found as $match) {
                    $refs[$match[0]] = array($match[1], $match[2]);
                }
            }

            if (preg_match_all('/\{\{trigger\.([a-z_][a-z0-9_]{0,31})\}\}/', $text, $found, PREG_SET_ORDER)) {
                foreach ($found as $match) {
                    $trigger_refs[$match[0]] = $match[1];
                }
            }
        }

        if (empty($refs) && empty($trigger_refs)) {
            continue;
        }

        $name = ws_flow_box_name($node);

        // What started the run gives the values its start box declares; a
        // flow without one has its own problem (no_trigger).
        if (!empty($trigger_refs)) {
            $trigger_fields = ws_flow_trigger_fields($nodes, $kinds);

            foreach ($trigger_refs as $ref => $field) {
                if (!in_array($field, $trigger_fields, true)) {
                    $problems[] = ws_flow_problem('bad_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads {var:2}, but what starts the flow gives no value {var:3}.', 'vars' => array($name, $ref, $field))));
                }
            }
        }
        // A value box has no place among the arrows: what it reads is
        // checked at the boxes that read it.
        $pure_reader = !empty($kinds[$node['kind']]['pure']);
        $before = $pure_reader ? array() : ws_flow_boxes_before($into, (string) $id);

        foreach ($refs as $ref => $parts) {
            list($box, $field) = $parts;
            $other = $nodes[$box] ?? null;

            if (($other === null) || !isset($kinds[$other['kind']])) {
                $problems[] = ws_flow_problem('bad_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads {var:2}, but the flow has no box {var:3}.', 'vars' => array($name, $ref, $box))));
            } elseif (!$pure_reader && !isset($before[$box]) && empty($kinds[$other['kind']]['pure'])) {
                $problems[] = ws_flow_problem('bad_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads {var:2}, but the box {var:3} does not come before it.', 'vars' => array($name, $ref, ws_flow_box_name($other)))));
            } elseif (!in_array($field, ws_flow_node_fields($other, $kinds), true)) {
                $problems[] = ws_flow_problem('bad_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads {var:2}, but the box {var:3} gives no value {var:4}.', 'vars' => array($name, $ref, ws_flow_box_name($other), $field))));
            } elseif (!$pure_reader && empty($kinds[$other['kind']]['pure'])) {
                $sure = $sure ?? ws_flow_sure_before($next, $always);

                if (isset($sure[$id]) && !isset($sure[$id][$box])) {
                    $problems[] = ws_flow_problem('skippable_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads {var:2}, but the box {var:3} may not run before it; the value is then empty.', 'vars' => array($name, $ref, ws_flow_box_name($other)))), true);
                }
            }
        }

        // A derived value is worked out when its reader runs, from the boxes
        // it reads: those must come before the reader as if it read them.
        $sources = ($pure_reader || !function_exists('ws_flow_derived_sources')) ? array() : ws_flow_derived_sources($nodes, array_unique(array_column($refs, 0)));

        foreach ($sources as $box => $via) {
            $other = $nodes[$box];

            if (!isset($kinds[$other['kind']]) || !empty($kinds[$other['kind']]['pure']) || !empty($kinds[$other['kind']]['trigger'])) {
                continue;
            }

            if (!isset($before[$box])) {
                $problems[] = ws_flow_problem('bad_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads the value box {var:2}, which reads the box {var:3}; that box does not come before {var:1}.', 'vars' => array($name, ws_flow_box_name($nodes[$via]), ws_flow_box_name($other)))));
                continue;
            }

            $sure = $sure ?? ws_flow_sure_before($next, $always);

            if (isset($sure[$id]) && !isset($sure[$id][$box])) {
                $problems[] = ws_flow_problem('skippable_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads the value box {var:2}, which reads the box {var:3}; that box may not run before {var:1}, and its values are then empty.', 'vars' => array($name, ws_flow_box_name($nodes[$via]), ws_flow_box_name($other)))), true);
            }
        }
    }

    return $problems;
}

/**
 * What a graph may be published with but a reader should know: references
 * to a box that may not run before the box reading it. Pure.
 *
 * @param array|string $graph
 * @return array[] code, node, edge, text, warning
 */
function ws_flow_warnings($graph)
{
    return array_values(array_filter(ws_flow_graph_check($graph), function ($problem) {
        return !empty($problem['warning']);
    }));
}

/* ---------------------------------------------------------------------------
   The store
   --------------------------------------------------------------------------- */

/**
 * What stops a graph from being published, the site's own records included:
 * ws_flow_graph_check() without its warnings (ws_flow_warnings()), and a
 * start box about a kind of record the
 * Workspace does not know (ws_scheduled_record_types()).
 *
 * @param array|string $graph
 * @return array[] code, node, edge, text
 */
function ws_flow_problems($graph)
{
    $graph = ws_flow_graph_normalize($graph);
    $problems = array_values(array_filter(ws_flow_graph_check($graph), function ($problem) {
        return empty($problem['warning']);
    }));
    $types = function_exists('ws_scheduled_record_types') ? ws_scheduled_record_types() : array();

    foreach ($graph['nodes'] as $node) {
        if (($node['kind'] === 'manual') && ($node['config']['subject'] !== '') && !isset($types[$node['config']['subject']])) {
            $problems[] = ws_flow_problem('bad_config', ($node['id'] !== '') ? $node['id'] : null, null, lang(array('string' => 'The start box {var:1} is about a kind of record the Workspace does not know ({var:2}).', 'vars' => array(ws_flow_box_name($node), $node['config']['subject']))));
        }
    }

    return $problems;
}

/**
 * One flow row.
 *
 * @param int $id
 * @return array|null
 */
function ws_flow($id)
{
    if (!ws_flows_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_flows WHERE id = '" . (int) $id . "'");

    return is_array($row) ? $row : null;
}

/**
 * One published version of a flow.
 *
 * @param int $version_id
 * @return array|null
 */
function ws_flow_version($version_id)
{
    if (!ws_flows_ready() || ((int) $version_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_flow_versions WHERE id = '" . (int) $version_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * What the person may do with a flow (ws_flow_access_level() decides from
 * what is gathered here):
 *   edit  its owner, staff in the team (ws_can_schedule()); a colleague on
 *         the staff it is shared with at edit, who changes the draft but
 *         does not publish it (ws_flow_publish_check()); running it too
 *   run   a live flow whose home channel (public or private) they read; a
 *         person it is shared with at run, or at edit while not on the staff
 *   view  a person it is shared with at view; a channel share in a channel
 *         they read
 *   ''    nothing: somebody else's flow that reaches them in none of these
 *         ways, however high their role, and every deleted flow
 *
 * @param array      $viewer
 * @param array|null $flow
 * @return string edit | run | view | ''
 */
function ws_flow_access($viewer, $flow)
{
    if (!is_array($flow) || empty($viewer['member']) || ((string) $flow['status'] === 'binned')) {
        return '';
    }

    $owner = ((int) $flow['owner_id'] === (int) $viewer['id']);
    $staff = ws_can_schedule($viewer);

    if ($owner && $staff) {
        return 'edit';
    }

    $home = false;

    // A public or private home channel the person reads, asked once a
    // request per channel: a list asks it for every flow or run.
    if (((string) $flow['status'] === 'live') && ((int) $flow['channel_id'] > 0)) {
        $home = ws_flow_channel_readable($viewer, (int) $flow['channel_id']);
    }

    // Read only for a flow with an id: ws_flows_nav_visible() asks about a
    // home channel alone.
    $reach = ((int) ($flow['id'] ?? 0) > 0) ? ws_flow_share_reach($viewer, $flow) : array('person' => '', 'channel' => false);

    return ws_flow_access_level(array(
        'owner'     => $owner,
        'staff'     => $staff,
        'status'    => (string) $flow['status'],
        'home'      => $home,
        'person'    => $reach['person'],
        'channel'   => $reach['channel'],
        'converted' => $staff && ws_flow_converted((int) ($flow['id'] ?? 0)),
    ));
}

/**
 * A flow the person may reach at least as far as asked.
 *
 * @param array  $viewer
 * @param int    $id
 * @param string $need view | run | edit
 * @return array|null the row, with access
 */
function ws_flow_for($viewer, $id, $need = 'view')
{
    $flow = ws_flow($id);
    $access = ws_flow_access($viewer, $flow);

    if (($access === '') || (ws_flow_access_rank($access) < ws_flow_access_rank($need))) {
        return null;
    }

    $flow['access'] = $access;

    return $flow;
}

/**
 * The flows the person reaches, the latest changed first: their own, the
 * live flows of others whose home channel they read, and the flows shared
 * with them or in a channel they read. Drafts are not read, except to find
 * the start of a flow never published when the list is narrowed by it.
 *
 * @param array  $viewer
 * @param string $scope   all | mine | shared
 * @param array  $filters ws_flows_list_where() keys, and start (ws_flow_start_kind())
 * @return array[] rows without draft, each with access
 */
function ws_flows_list($viewer, $scope = 'all', $filters = array())
{
    if (!ws_flows_ready() || empty($viewer['member'])) {
        return array();
    }

    // A flow shared in any channel is read, and ws_flow_access() keeps the
    // ones whose channel the person reads.
    $shared = ws_flow_shares_ready()
        ? " OR id IN (SELECT flow_id FROM ws_flow_shares
            WHERE (target_type = 'user' AND target_id = '" . (int) $viewer['id'] . "') OR target_type = 'channel')"
        : '';

    $rows = (array) db_items("SELECT id, owner_id, channel_id, name, status, draft_rev, version_id, updated_by, created_at, updated_at, binned_at
        FROM ws_flows
        WHERE status <> 'binned' AND (owner_id = '" . (int) $viewer['id'] . "' OR (status = 'live' AND channel_id > 0)" . $shared . ")" . ws_flows_list_where($filters) . "
        ORDER BY updated_at DESC, id DESC
        LIMIT 1000");

    ws_flow_share_rows_prime(array_map(function ($row) {
        return (int) $row['id'];
    }, $rows));

    $out = array();

    foreach ($rows as $row) {
        $mine = ((int) $row['owner_id'] === (int) $viewer['id']);

        if ((($scope === 'mine') && !$mine) || (($scope === 'shared') && $mine)) {
            continue;
        }

        $access = ws_flow_access($viewer, $row);

        if ($access !== '') {
            $row['access'] = $access;
            $out[] = $row;
        }
    }

    $start = (string) ($filters['start'] ?? '');

    if (($start !== '') && !empty($out)) {
        $kinds = ws_flows_start_kinds($out);
        $out = array_values(array_filter($out, function ($row) use ($kinds, $start) {
            return ($kinds[(int) $row['id']] ?? '') === $start;
        }));
    }

    return $out;
}

/**
 * The conditions a narrowed flow list adds to its query, each led by AND:
 * status (draft, live or paused), channel_id (the home channel) and
 * updated_since (unix time, changed at or after it). A value outside them
 * adds nothing; the API refuses it before.
 *
 * @param array $filters
 * @return string
 */
function ws_flows_list_where($filters)
{
    $sql = '';
    $status = (string) ($filters['status'] ?? '');

    // Only the three literals reach the query.
    if (in_array($status, array('draft', 'live', 'paused'), true)) {
        $sql .= " AND status = '" . $status . "'";
    }

    if ((int) ($filters['channel_id'] ?? 0) > 0) {
        $sql .= " AND channel_id = '" . (int) $filters['channel_id'] . "'";
    }

    if ((int) ($filters['updated_since'] ?? 0) > 0) {
        $sql .= " AND updated_at >= '" . (int) $filters['updated_since'] . "'";
    }

    return $sql;
}

/**
 * How a flow starts, by the kind of its start box: at a time, on an event
 * (a row of a workflow table written is one too), when somebody joins a
 * channel, or by hand.
 *
 * @return array box kind => start
 */
function ws_flow_start_names()
{
    return array('schedule' => 'time', 'event' => 'event', 'row' => 'event', 'join' => 'join', 'manual' => 'manual');
}

/**
 * How a graph starts (ws_flow_start_names()), '' when it has no start box.
 *
 * @param array $graph
 * @return string
 */
function ws_flow_start_kind($graph)
{
    $trigger = ws_flow_trigger_node($graph);

    return $trigger ? (string) (ws_flow_start_names()[$trigger['kind']] ?? '') : '';
}

/**
 * The graph a flow starts from: its live version once published, the draft
 * of a flow never published.
 *
 * @param array      $flow    ws_flows row with draft
 * @param array|null $version its live ws_flow_versions row
 * @return array
 */
function ws_flow_start_graph($flow, $version)
{
    return is_array($version) ? ws_flow_graph_of($version) : ws_flow_graph_of($flow);
}

/**
 * The graph each flow listed starts from (ws_flow_start_graph()): its live
 * version for a published flow, the draft for one never published, in two
 * reads.
 *
 * @param array[] $rows ws_flows rows without draft
 * @return array flow id => graph
 */
function ws_flows_start_graphs($rows)
{
    $versions = array();
    $drafts = array();

    foreach ($rows as $row) {
        if ((int) $row['version_id'] > 0) {
            $versions[(int) $row['version_id']] = (int) $row['id'];
        } else {
            $drafts[(int) $row['id']] = (int) $row['id'];
        }
    }

    $out = array();

    if (!empty($versions)) {
        foreach ((array) db_items("SELECT id, graph FROM ws_flow_versions WHERE id IN (" . implode(',', array_keys($versions)) . ")") as $version) {
            $out[$versions[(int) $version['id']]] = ws_flow_graph_of(array('graph' => $version['graph']));
        }
    }

    if (!empty($drafts)) {
        foreach ((array) db_items("SELECT id, draft FROM ws_flows WHERE id IN (" . implode(',', $drafts) . ")") as $flow) {
            $out[(int) $flow['id']] = ws_flow_graph_of(array('draft' => $flow['draft']));
        }
    }

    return $out;
}

/**
 * How each flow listed starts.
 *
 * @param array[] $rows ws_flows rows without draft
 * @return array flow id => ws_flow_start_kind()
 */
function ws_flows_start_kinds($rows)
{
    return array_map('ws_flow_start_kind', ws_flows_start_graphs($rows));
}

/**
 * How each flow listed starts, as its kind and as the flow list's sentence.
 *
 * @param array   $viewer
 * @param array[] $rows ws_flows rows without draft
 * @return array flow id => kind (ws_flow_start_kind()), text (ws_flow_start_sentence())
 */
function ws_flows_starts($viewer, $rows)
{
    $out = array();

    foreach (ws_flows_start_graphs($rows) as $id => $graph) {
        $out[$id] = array('kind' => ws_flow_start_kind($graph), 'text' => ws_flow_start_sentence($viewer, $graph));
    }

    return $out;
}

/**
 * The names of the home channels of the flows listed that the person reads,
 * in one read; a channel they do not read is not named.
 *
 * @param array   $viewer
 * @param array[] $rows ws_flows rows
 * @return array channel id => name
 */
function ws_flows_channel_names($viewer, $rows)
{
    $ids = array();

    foreach ($rows as $row) {
        $id = (int) ($row['channel_id'] ?? 0);

        if (($id > 0) && !isset($ids[$id]) && ws_flow_channel_readable($viewer, $id)) {
            $ids[$id] = $id;
        }
    }

    $out = array();

    if (!empty($ids)) {
        foreach ((array) db_items("SELECT id, name FROM ws_channels WHERE id IN (" . implode(',', $ids) . ")") as $channel) {
            $out[(int) $channel['id']] = (string) $channel['name'];
        }
    }

    return $out;
}

/**
 * How a graph starts, in words: "By hand", the rule of a box that starts it
 * on its own as a scheduled action says it ("Every week on Monday at
 * 09:00, from 02.11.2026", "When a form is submitted: Contact"), or null
 * when it has no start box.
 *
 * @param array         $viewer
 * @param array         $graph
 * @param callable|null $readable channel id => bool; ws_flow_channel_readable() when null
 * @return string|null
 */
function ws_flow_start_text($viewer, $graph, $readable = null)
{
    $start = ws_flow_start_rule($graph, ($readable !== null) ? $readable : function ($channel_id) use ($viewer) {
        return ws_flow_channel_readable($viewer, $channel_id);
    }, function ($config) use ($viewer) {
        return ws_flow_row_start_text($viewer, $config);
    });

    if ($start === null) {
        return null;
    }

    $text = (($start['rule'] !== null) && function_exists('ws_scheduled_rule_text')) ? ws_scheduled_rule_text($viewer, $start['rule']) : '';

    return ($text !== '') ? $text : $start['text'];
}

/**
 * How a graph starts in the flow list's short words: a time box by its time
 * alone ("Every day 09:00"), any other start as ws_flow_start_text() says
 * it; '' when it has no start box.
 *
 * @param array         $viewer
 * @param array         $graph
 * @param callable|null $readable channel id => bool; ws_flow_channel_readable() when null
 * @return string
 */
function ws_flow_start_sentence($viewer, $graph, $readable = null)
{
    $readable = ($readable !== null) ? $readable : function ($channel_id) use ($viewer) {
        return ws_flow_channel_readable($viewer, $channel_id);
    };
    $start = ws_flow_start_rule($graph, $readable, function ($config) use ($viewer) {
        return ws_flow_row_start_text($viewer, $config);
    });

    if ($start === null) {
        return '';
    }

    $short = ($start['rule'] !== null) ? ws_flow_start_short($start['rule']) : '';

    return ($short !== '') ? $short : (string) ws_flow_start_text($viewer, $graph, $readable);
}

/**
 * A time rule in short words, without the day it runs from: "Every day
 * 09:00", "Every Monday, Thursday 09:00"; a rule that runs once keeps its
 * day. '' for a rule that is not a time. Pure.
 *
 * @param array $rule ws_flow_anchor_rule()
 * @return string
 */
function ws_flow_start_short($rule)
{
    if ((($rule['type'] ?? '') !== 'at') || !isset($rule['date'], $rule['time'])) {
        return '';
    }

    $at = strtotime($rule['date'] . ' ' . $rule['time'] . ':00');

    if ($at === false) {
        return '';
    }

    $time = (string) $rule['time'];
    $every = max(1, (int) ($rule['every'] ?? 1));

    switch ((string) ($rule['repeat'] ?? 'none')) {
        case 'none':
            return lang(array('string' => 'On {var:1} at {var:2}', 'vars' => array(date('d.m.Y', $at), $time)));

        case 'minutes':
            return lang(array('string' => 'Every {var:1} minutes', 'vars' => $every));

        case 'hourly':
            return ($every > 1) ? lang(array('string' => 'Every {var:1} hours', 'vars' => $every)) : lang('Every hour');

        case 'daily':
            return ($every > 1)
                ? lang(array('string' => 'Every {var:1} days {var:2}', 'vars' => array($every, $time)))
                : lang(array('string' => 'Every day {var:1}', 'vars' => $time));

        case 'workdays':
            return lang(array('string' => 'Every working day {var:1}', 'vars' => $time));

        case 'weekly':
            $days = ws_scheduled_days_text(!empty($rule['days']) ? $rule['days'] : array((int) date('N', $at)));

            return ($every > 1)
                ? lang(array('string' => 'Every {var:1} weeks on {var:2} {var:3}', 'vars' => array($every, $days, $time)))
                : lang(array('string' => 'Every {var:1} {var:2}', 'vars' => array($days, $time)));

        case 'monthly':
            return ($every > 1)
                ? lang(array('string' => 'Every {var:1} months on day {var:2} {var:3}', 'vars' => array($every, (int) date('j', $at), $time)))
                : lang(array('string' => 'Every month on day {var:1} {var:2}', 'vars' => array((int) date('j', $at), $time)));

        case 'yearly':
            return lang(array('string' => 'Every year on {var:1} {var:2}', 'vars' => array(date('d.m', $at), $time)));
    }

    return '';
}

/**
 * What says how a graph starts: text, the words to use, and rule, the rule
 * of a scheduled action to say in words instead when there is one (a box
 * that starts the flow on its own). A channel the person does not read is
 * not named: a join box then says only that somebody joins a channel, an
 * event box leaves the channel out of its rule. A time box not filled in
 * yet has no rule to read. A row box says its words itself ($table_text),
 * with no rule. Null when the graph has no start box.
 *
 * @param array         $graph
 * @param callable      $readable   channel id => bool
 * @param callable|null $table_text a row box's settings => its words; its label when null
 * @return array|null text, rule (array or null)
 */
function ws_flow_start_rule($graph, $readable, $table_text = null)
{
    $trigger = ws_flow_trigger_node($graph);

    if (!$trigger) {
        return null;
    }

    $kind = (string) $trigger['kind'];
    $anchors = function_exists('ws_flow_anchor_kinds') ? ws_flow_anchor_kinds() : array();

    if (!isset($anchors[$kind])) {
        return array('text' => lang('By hand'), 'rule' => null);
    }

    $label = (string) $anchors[$kind]['label'];
    $config = ws_flow_anchor_normalize($kind, $trigger['config'] ?? array());

    // A row box names its table and column only to whoever reads the table.
    if ($kind === 'row') {
        return array('text' => ($table_text !== null) ? (string) call_user_func($table_text, $config) : $label, 'rule' => null);
    }

    if (((int) ($config['channel_id'] ?? 0) > 0) && !call_user_func($readable, (int) $config['channel_id'])) {
        if ($kind === 'join') {
            return array('text' => $label, 'rule' => null);
        }

        $config['channel_id'] = 0;
    }

    if (($kind === 'schedule') && (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $config['date']) || !preg_match('~^\d{2}:\d{2}$~', $config['time'])
        || (strtotime($config['date'] . ' ' . $config['time'] . ':00') === false))) {
        return array('text' => $label, 'rule' => null);
    }

    return array('text' => $label, 'rule' => ws_flow_anchor_rule($kind, $config));
}

/**
 * The graph of a draft or a version as kept.
 *
 * @param array $row ws_flows (draft) or ws_flow_versions (graph)
 * @return array
 */
function ws_flow_graph_of($row)
{
    $json = array_key_exists('draft', $row) ? $row['draft'] : ($row['graph'] ?? '');

    return ws_flow_graph_normalize((string) $json);
}

/**
 * A refused flow write, as the check functions answer it.
 *
 * @param string $error
 * @param int    $status 422, or 403, 404, 409 (changed meanwhile), 503
 * @param string $field
 * @return array
 */
function ws_flow_refusal($error, $status = 422, $field = '')
{
    return array('ok' => false, 'error' => $error, 'status' => $status, 'field' => $field);
}

/**
 * Whether the person may write this flow: the checks of ws_flow_save(), made
 * on their own so that the API's dry run meets the same refusals. A draft
 * may hold problems; they are answered, not refused.
 *
 * @param array      $viewer
 * @param array      $data   name, channel_id, graph (array or JSON), draft_rev (the one it was read at); only those sent are changed
 * @param array|null $flow   null for a new flow
 * @return array ok, error, status, field, sets (columns as they would be stored), graph (the draft as it would be), problems, draft_rev
 */
function ws_flow_save_check($viewer, $data, $flow = null)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (!ws_can_schedule($viewer)) {
        return ws_flow_refusal(lang('Only staff in the team can build flows.'), 403);
    }

    $sets = array();
    $draft_rev = 0;

    if ($flow === null) {
        if ((int) db_value("SELECT COUNT(*) FROM ws_flows WHERE owner_id = '" . (int) $viewer['id'] . "' AND status <> 'binned'") >= WS_FLOW_MAX_PER_OWNER) {
            return ws_flow_refusal(lang(array('string' => 'You can keep at most {var:1} flows.', 'vars' => WS_FLOW_MAX_PER_OWNER)));
        }
    } else {
        // Its owner, or a colleague on the staff it is shared with at edit.
        if (ws_flow_access($viewer, $flow) !== 'edit') {
            return ws_flow_refusal(lang('Only the owner of a flow can change it.'), 403);
        }

        $draft_rev = (int) $flow['draft_rev'];

        if (isset($data['draft_rev']) && ((int) $data['draft_rev'] !== $draft_rev)) {
            return ws_flow_refusal(lang('This flow was changed meanwhile, on another screen or device. Load it again.'), 409, 'draft_rev');
        }
    }

    if (($flow === null) || array_key_exists('name', $data)) {
        $name = ws_flow_line($data['name'] ?? '', 160);

        if ($name === '') {
            return ws_flow_refusal(lang('A flow needs a name.'), 422, 'name');
        }

        $sets['name'] = $name;
    }

    if (array_key_exists('channel_id', $data) && (($flow === null) || ((int) $data['channel_id'] !== (int) $flow['channel_id']))) {
        // The home decides who may run the flow, as a share does: a person
        // the flow is shared with at edit changes the graph, not the home.
        if (($flow !== null) && ((int) $flow['owner_id'] !== (int) $viewer['id'])) {
            return ws_flow_refusal(lang('Only the owner of a flow can move it to another home channel.'), 403, 'channel_id');
        }

        $channel_id = max(0, (int) $data['channel_id']);

        if ($channel_id > 0) {
            $channel = ws_channel($channel_id);

            if (!$channel || !in_array((string) $channel['kind'], array('public', 'private'), true) || !ws_can_read_channel($viewer, $channel)) {
                return ws_flow_refusal(lang('A flow\'s home is a public or private channel you read.'), 422, 'channel_id');
            }
        }

        $sets['channel_id'] = $channel_id;
    }

    if (($flow === null) || array_key_exists('graph', $data)) {
        $graph = ws_flow_graph_normalize($data['graph'] ?? array());
        $json = json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            return ws_flow_refusal(lang('The flow could not be saved.'), 422, 'graph');
        }

        if (strlen($json) > WS_FLOW_GRAPH_BYTES) {
            return ws_flow_refusal(lang(array('string' => 'The flow is too large to keep: at most {var:1} KB.', 'vars' => (int) (WS_FLOW_GRAPH_BYTES / 1024))), 422, 'graph');
        }

        $sets['draft'] = $json;
    } else {
        $graph = ws_flow_graph_of($flow);
    }

    return array(
        'ok'        => true,
        'error'     => '',
        'status'    => 200,
        'field'     => '',
        'sets'      => $sets,
        'graph'     => $graph,
        'problems'  => ws_flow_problems($graph),
        'warnings'  => ws_flow_warnings($graph),
        'draft_rev' => $draft_rev,
    );
}

/**
 * Writes a flow: a new draft of the person's own, or a change to one of
 * theirs. A change raises draft_rev, and is refused (409) when the draft was
 * saved meanwhile from another screen.
 *
 * @param array      $viewer
 * @param array      $data   see ws_flow_save_check()
 * @param array|null $flow   null for a new flow
 * @return array ok, error, status, field, flow_id, draft_rev, problems, warnings (on a write)
 */
function ws_flow_save($viewer, $data, $flow = null)
{
    $check = ws_flow_save_check($viewer, $data, $flow);

    if (!$check['ok']) {
        return $check + array('flow_id' => ($flow !== null) ? (int) $flow['id'] : 0, 'draft_rev' => 0, 'problems' => array());
    }

    $sets = $check['sets'];
    $now = time();

    if ($flow === null) {
        db("INSERT INTO ws_flows (owner_id, channel_id, name, status, draft, draft_rev, version_id, updated_by, created_at, updated_at)
            VALUES ('" . (int) $viewer['id'] . "', '" . (int) ($sets['channel_id'] ?? 0) . "', '" . e($sets['name']) . "', 'draft',
                '" . e($sets['draft']) . "', 1, 0, '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

        $flow_id = (int) mysqli_insert_id(db::$con);

        if ($flow_id <= 0) {
            return ws_flow_refusal(lang('The flow could not be saved.')) + array('flow_id' => 0, 'draft_rev' => 0, 'problems' => array());
        }

        log_activity(lang(array('string' => 'workspace flow ({var:1}) was created', 'vars' => $sets['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

        return array('ok' => true, 'error' => '', 'status' => 201, 'field' => '', 'flow_id' => $flow_id, 'draft_rev' => 1, 'problems' => $check['problems'], 'warnings' => $check['warnings']);
    }

    $parts = array();

    foreach ($sets as $column => $value) {
        $parts[] = $column . " = '" . e((string) $value) . "'";
    }

    $parts[] = "draft_rev = draft_rev + 1";
    $parts[] = "updated_by = '" . (int) $viewer['id'] . "'";
    $parts[] = "updated_at = '" . $now . "'";

    // The draft_rev it was checked at: a save from another screen in between
    // has raised it, and this one then changes no row.
    db("UPDATE ws_flows SET " . implode(', ', $parts) . "
        WHERE id = '" . (int) $flow['id'] . "' AND draft_rev = '" . (int) $check['draft_rev'] . "' AND status <> 'binned'");

    if ((int) mysqli_affected_rows(db::$con) < 1) {
        return ws_flow_refusal(lang('This flow was changed meanwhile, on another screen or device. Load it again.'), 409, 'draft_rev')
            + array('flow_id' => (int) $flow['id'], 'draft_rev' => 0, 'problems' => array());
    }

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'flow_id' => (int) $flow['id'], 'draft_rev' => (int) $check['draft_rev'] + 1, 'problems' => $check['problems'], 'warnings' => $check['warnings']);
}

/**
 * Whether the person may publish the flow's draft: its owner, while it is
 * theirs to change (ws_flow_share_manager()), no problem left in it, and
 * every task box with people on it giving work only to people they may give
 * it to (ws_task_create_check()). A box with a rule is resolved when the run
 * opens its task. A colleague the flow is shared with at edit saves the
 * draft but does not publish it: a published flow opens its tasks in the
 * owner's name, with the owner's rights (an assign rule resolved through
 * them, the home channel they post in), so what it runs is the owner's
 * decision, as publishing is the person's own in the API.
 *
 * @param array $viewer
 * @param array $flow
 * @param array $data draft_rev (the one the draft was read at), optional
 * @return array ok, error, status, field, graph, json (the graph as it will be kept), problems, warnings
 */
function ws_flow_publish_check($viewer, $flow, $data = array())
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    // A converted scheduled action's flow is published by any staff member,
    // as they saved the action; it keeps running as its owner.
    $converted = ws_flow_converted((int) $flow['id']) && (ws_flow_access($viewer, $flow) === 'edit');

    if (!ws_flow_share_manager($viewer, $flow) && !$converted) {
        return ws_flow_refusal(lang('Only the owner of a flow can publish it.'), 403);
    }

    if (isset($data['draft_rev']) && ((int) $data['draft_rev'] !== (int) $flow['draft_rev'])) {
        return ws_flow_refusal(lang('This flow was changed meanwhile, on another screen or device. Load it again.'), 409, 'draft_rev');
    }

    $graph = ws_flow_graph_of($flow);
    $problems = ws_flow_problems($graph);

    if (!empty($problems)) {
        return ws_flow_refusal(lang(array('string' => 'Fix the flow before it is published: {var:1}', 'vars' => $problems[0]['text']))) + array('problems' => $problems);
    }

    // A step is checked as the scheduled action's step is, with the owner's
    // rights: a channel they may write in, addresses that are good. It is
    // checked again each time the box runs.
    foreach ($graph['nodes'] as $node) {
        if (!empty(ws_flow_kinds()[$node['kind']]['step'])) {
            $step = ws_step_check($viewer, $node['kind'], $node['config'], ws_flow_starts_on_join($graph));

            if (!$step['ok']) {
                return ws_flow_refusal(lang(array('string' => 'The box {var:1}: {var:2}', 'vars' => array(ws_flow_box_name($node), $step['error']))));
            }
        }

        // A box that starts the flow on its own: a time still to come, an
        // event whose records the owner sees, a channel they read.
        if (isset(ws_flow_anchor_kinds()[$node['kind']])) {
            $anchor = ws_flow_anchor_check($viewer, $node['kind'], $node['config']);

            if (!$anchor['ok']) {
                return ws_flow_refusal(lang(array('string' => 'The box {var:1}: {var:2}', 'vars' => array(ws_flow_box_name($node), $anchor['error']))));
            }
        }

        // A condition: a count or a record the owner may read.
        if ($node['kind'] === 'condition') {
            $condition = ws_flow_condition_check($viewer, $node['config']);

            if (!$condition['ok']) {
                return ws_flow_refusal(lang(array('string' => 'The box {var:1}: {var:2}', 'vars' => array(ws_flow_box_name($node), $condition['error']))));
            }
        }

        // Another flow: one the owner runs, live and started by hand.
        if ($node['kind'] === 'start_flow') {
            $chain = ws_flow_chain_check($viewer, (int) $flow['id'], $node['config']);

            if (!$chain['ok']) {
                return ws_flow_refusal(lang(array('string' => 'The box {var:1}: {var:2}', 'vars' => array(ws_flow_box_name($node), $chain['error']))));
            }
        }
    }

    foreach ($graph['nodes'] as $node) {
        if (($node['kind'] !== 'task') || empty($node['config']['assignees'])) {
            continue;
        }

        $task = ws_task_create_check($viewer, array('title' => $node['config']['title'], 'assignees' => $node['config']['assignees']));

        if (!$task['ok']) {
            return ws_flow_refusal(lang(array('string' => 'The task box {var:1}: {var:2}', 'vars' => array(ws_flow_box_name($node), $task['error']))));
        }
    }

    $json = json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($json)) {
        return ws_flow_refusal(lang('The flow could not be published.'));
    }

    return array(
        'ok'       => true,
        'error'    => '',
        'status'   => 200,
        'field'    => '',
        'graph'    => $graph,
        'json'     => $json,
        'problems' => array(),
    );
}

/**
 * Publishes the flow's draft as its next version and makes the flow live. A
 * second publish of the same moment (a double click) meets the unique
 * (flow_id, n) and is refused with 409.
 *
 * @param array $viewer
 * @param array $flow
 * @param array $data see ws_flow_publish_check()
 * @return array ok, error, status, field, version_id, n
 */
function ws_flow_publish($viewer, $flow, $data = array())
{
    $check = ws_flow_publish_check($viewer, $flow, $data);

    if (!$check['ok']) {
        return $check + array('version_id' => 0, 'n' => 0);
    }

    $flow_id = (int) $flow['id'];
    $n = (int) db_value("SELECT MAX(n) FROM ws_flow_versions WHERE flow_id = '" . $flow_id . "'") + 1;
    $now = time();

    // Not db(): a failed statement there ends the request with an HTML page,
    // and the duplicate key is an answer here.
    $written = db_q("INSERT INTO ws_flow_versions (flow_id, n, graph, published_by, published_at) VALUES (?, ?, ?, ?, ?)",
        array($flow_id, $n, (string) $check['json'], (int) $viewer['id'], $now));

    if (!$written) {
        return (((int) mysqli_errno(db::$con) === 1062)
            ? ws_flow_refusal(lang('This flow was published meanwhile.'), 409)
            : ws_flow_refusal(lang('The flow could not be published.'))) + array('version_id' => 0, 'n' => 0);
    }

    $version_id = (int) mysqli_insert_id(db::$con);

    db("UPDATE ws_flows SET status = 'live', version_id = '" . $version_id . "', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . $now . "'
        WHERE id = '" . $flow_id . "' AND status <> 'binned'");

    // The hidden scheduled action that starts it on its own, written from
    // the version now live (checked a moment ago by the publish check).
    ws_flow_anchor_sync($viewer, $flow, ws_flow_graph_normalize($check['json']));

    log_activity(lang(array('string' => 'workspace flow ({var:1}) was published as version {var:2}', 'vars' => array($flow['name'], $n))), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'version_id' => $version_id, 'n' => $n);
}

/**
 * Whether the person may pause the flow: theirs to change, and live. A
 * colleague shared at edit may pause it too: a pause only stops new runs,
 * and the owner makes it live again by publishing.
 *
 * @param array $viewer
 * @param array $flow
 * @return array ok, error, status, field
 */
function ws_flow_pause_check($viewer, $flow)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (ws_flow_access($viewer, $flow) !== 'edit') {
        return ws_flow_refusal(lang('Only the owner of a flow can pause it.'), 403);
    }

    if ((string) $flow['status'] !== 'live') {
        return ws_flow_refusal(lang('Only a live flow can be paused.'));
    }

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '');
}

/**
 * Pauses a live flow: nothing starts it until it is published again. Its
 * versions stay as they are.
 *
 * @param array $viewer
 * @param array $flow
 * @return array ok, error, status, field
 */
function ws_flow_pause($viewer, $flow)
{
    $check = ws_flow_pause_check($viewer, $flow);

    if (!$check['ok']) {
        return $check;
    }

    db("UPDATE ws_flows SET status = 'paused', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . time() . "'
        WHERE id = '" . (int) $flow['id'] . "' AND status = 'live'");

    if ((int) mysqli_affected_rows(db::$con) < 1) {
        return ws_flow_refusal(lang('Only a live flow can be paused.'), 409);
    }

    ws_flow_anchor_set_status($flow['id'], 'paused');

    log_activity(lang(array('string' => 'workspace flow ({var:1}) was paused', 'vars' => $flow['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return $check;
}

/**
 * Whether the person may delete the flow: its owner, while it is theirs to
 * change; a person it is shared with at edit may not. Its open runs do not
 * hold it back: they end with it.
 *
 * @param array $viewer
 * @param array $flow
 * @return array ok, error, status, field
 */
function ws_flow_bin_check($viewer, $flow)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (!ws_flow_share_manager($viewer, $flow)) {
        return ws_flow_refusal(lang('Only the owner of a flow can delete it.'), 403);
    }

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '');
}

/**
 * Deletes a flow: it is marked binned and nobody reaches it any more, its
 * runs included, so its open runs are cancelled (the tasks they opened stay
 * as they are). The row and its versions are kept for the runs that point
 * at them.
 *
 * @param array $viewer
 * @param array $flow
 * @return array ok, error, status, field
 */
function ws_flow_bin($viewer, $flow)
{
    $check = ws_flow_bin_check($viewer, $flow);

    if (!$check['ok']) {
        return $check;
    }

    $now = time();

    db("UPDATE ws_flows SET status = 'binned', binned_at = '" . $now . "', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . $now . "'
        WHERE id = '" . (int) $flow['id'] . "' AND status <> 'binned'");

    ws_flow_runs_cancel_for_flow($flow);
    ws_flow_anchor_set_status($flow['id'], 'cancelled');

    log_activity(lang(array('string' => 'workspace flow ({var:1}) was deleted', 'vars' => $flow['name'])), (string) ($_SESSION['sessionusername'] ?? ''));

    return $check;
}

/* ---------------------------------------------------------------------------
   The screen (workspace_flows.php, assets/js/workspace_flow.js)
   --------------------------------------------------------------------------- */

/**
 * The address a webhook or web check box calls, as somebody who may not
 * change the flow reads it: only its host. A chat or automation hook
 * (Slack, Discord, n8n, Zapier) carries its secret in the path, and anybody
 * holding the address can post as the integration; a scheduled action shows
 * no more of it either (ws_scheduled_host()). Pure.
 *
 * @param string $url
 * @return string https://host/… , '' for none
 */
function ws_flow_url_masked($url)
{
    $host = parse_url((string) $url, PHP_URL_HOST);

    return (is_string($host) && ($host !== '')) ? 'https://' . strtolower($host) . '/…' : '';
}

/**
 * A graph as the screen and the API hand it out: a box's settings always an
 * object, even an end's empty ones. Only whoever may change the flow reads
 * the whole address of a webhook or web check box (ws_flow_url_masked()),
 * and which record a change box changes and to what: the record may be one
 * the reader may not open, and its values a customer's data. They read the
 * kind of record, the action and the names of the fields. A condition box's
 * record and the value it compares with are kept back the same way
 * (ws_flow_condition_masked()).
 *
 * @param array $graph ws_flow_graph_normalize()
 * @param bool  $edit  the reader may change the flow
 * @return array
 */
function ws_flow_graph_present($graph, $edit = false)
{
    return array(
        'v'     => 1,
        'nodes' => array_map(function ($node) use ($edit) {
            if (!$edit && in_array((string) $node['kind'], array('webhook', 'web_check'), true) && isset($node['config']['url'])) {
                $node['config']['url'] = ws_flow_url_masked($node['config']['url']);
            }

            if (!$edit && ((string) $node['kind'] === 'change')) {
                $node['config']['id'] = 0;
                $node['config']['fields'] = array_fill_keys(array_keys((array) ($node['config']['fields'] ?? array())), '');
            }

            if (!$edit && ((string) $node['kind'] === 'condition')) {
                $node['config'] = ws_flow_condition_masked($node['config']);
            }

            $node['config'] = (object) $node['config'];

            return $node;
        }, $graph['nodes']),
        'edges' => $graph['edges'],
        'notes' => $graph['notes'],
    );
}

/**
 * Are this person's scheduled actions listed in Flows (the groups at the
 * end of the flow list) rather than on the scheduled screen? Staff's are,
 * once the flow tables are there; their scheduled messages stay on the
 * scheduled screen, and so does everything without the flow tables.
 *
 * @param array $viewer
 * @return bool
 */
function ws_flows_scheduled($viewer)
{
    return function_exists('ws_can_schedule') && ws_can_schedule($viewer) && ws_flows_nav_visible($viewer);
}

/**
 * The two groups of scheduled actions the flow list ends with: the rows
 * converted into a flow (any state: their history stays readable) and the
 * rows the conversion refused, still run by ws_scheduled_execute() (no
 * flow, no anchor, not cancelled). A cancelled row never converted is in
 * neither (its channel or note card stays), nor is a flow's hidden anchor
 * or a scheduled message. A row from before the kind and anchor columns
 * reads as an action with no anchor. Pure.
 *
 * @param array[] $rows ws_scheduled_actions rows (kind, flow_id, anchor, status)
 * @return array converted (rows), refused (rows), each in the order given
 */
function ws_flows_scheduled_groups($rows)
{
    $groups = array('converted' => array(), 'refused' => array());

    foreach ((array) $rows as $row) {
        if ((string) ($row['kind'] ?? 'action') !== 'action') {
            continue;
        }

        $anchor = (string) ($row['anchor'] ?? '');

        if (((int) ($row['flow_id'] ?? 0) > 0) && ($anchor === 'converted')) {
            $groups['converted'][] = $row;
        } elseif (((int) ($row['flow_id'] ?? 0) === 0) && ($anchor === '') && ((string) ($row['status'] ?? '') !== 'cancelled')) {
            $groups['refused'][] = $row;
        }
    }

    return $groups;
}

/**
 * The scheduled actions at the end of a staff member's flow list: the
 * converted ones as their cards present them (when, step, state, the flow
 * they run) and how many the conversion refused; that group draws its own
 * cards (ws_scheduled_list() with refused). Nothing for anybody else.
 *
 * @param array $viewer
 * @return array|null converted (ws_scheduled_present() items), refused (count)
 */
function ws_flows_scheduled_rows($viewer)
{
    if (!ws_flows_scheduled($viewer) || !function_exists('ws_scheduled_present')) {
        return null;
    }

    $rows = (array) db_items("SELECT * FROM ws_scheduled_actions WHERE 1 = 1" . ws_scheduled_actions_only() . ws_scheduled_anchors_out() . "
        ORDER BY (status = 'active') DESC, IF(status = 'active' AND next_run_at > 0, next_run_at, 4294967295), id DESC LIMIT 300");
    $groups = ws_flows_scheduled_groups($rows);

    return array(
        'converted' => array_map(function ($row) use ($viewer) {
            return ws_scheduled_present($viewer, ws_scheduled_decode($row));
        }, $groups['converted']),
        'refused'   => count($groups['refused']),
    );
}

/**
 * Does the Workspace show Flows to this person? Staff, who build them,
 * anybody who may run the live flow of a channel they read, and anybody a
 * flow is shared with, themselves or in a channel they read.
 *
 * @param array $viewer
 * @return bool
 */
function ws_flows_nav_visible($viewer)
{
    static $seen = array();

    $key = (int) ($viewer['id'] ?? 0);

    if (isset($seen[$key])) {
        return $seen[$key];
    }

    $visible = false;

    if (ws_flows_ready() && !empty($viewer['member'])) {
        $visible = ws_can_schedule($viewer);

        // A live flow whose home is a public channel, or a private one the
        // person is in: what ws_flow_access() answers "run" for, in one read
        // (this runs on every Workspace screen, through the rail).
        if (!$visible) {
            $visible = (bool) db_value("SELECT 1 FROM ws_flows f
                JOIN ws_channels c ON c.id = f.channel_id
                LEFT JOIN ws_channel_members m ON m.channel_id = c.id AND m.user_id = '" . (int) $viewer['id'] . "'
                WHERE f.status = 'live' AND f.channel_id > 0
                    AND (c.kind = 'public' OR (c.kind = 'private' AND m.user_id IS NOT NULL))
                LIMIT 1");
        }

        // A flow shared with the person, or in a public channel or a private
        // one they are in (ws_flow_share_reach() asks the same per flow).
        if (!$visible && ws_flow_shares_ready()) {
            $visible = (bool) db_value("SELECT 1 FROM ws_flow_shares s
                JOIN ws_flows f ON f.id = s.flow_id AND f.status <> 'binned'
                LEFT JOIN ws_channels c ON s.target_type = 'channel' AND c.id = s.target_id
                LEFT JOIN ws_channel_members m ON m.channel_id = c.id AND m.user_id = '" . (int) $viewer['id'] . "'
                WHERE (s.target_type = 'user' AND s.target_id = '" . (int) $viewer['id'] . "')
                    OR (s.target_type = 'channel' AND (c.kind = 'public' OR (c.kind = 'private' AND m.user_id IS NOT NULL)))
                LIMIT 1");
        }

        // Staff also read a private channel opened for inspection
        // (ws_audit_open()), which the session holds: asked channel by
        // channel, the way ws_flow_access() asks it.
        if (!$visible && ((int) ($viewer['role'] ?? 3) < 3)) {
            foreach ((array) db_values("SELECT DISTINCT channel_id FROM ws_flows WHERE status = 'live' AND channel_id > 0 LIMIT 500") as $channel_id) {
                if (ws_flow_access($viewer, array('status' => 'live', 'owner_id' => 0, 'channel_id' => (int) $channel_id)) === 'run') {
                    $visible = true;
                    break;
                }
            }
        }
    }

    $seen[$key] = $visible;

    return $visible;
}

/**
 * The version numbers of the flows listed, by version id, in one read.
 *
 * @param array[] $rows ws_flows rows
 * @return array version_id => n
 */
function ws_flow_version_numbers($rows)
{
    $ids = array();

    foreach ($rows as $row) {
        if ((int) $row['version_id'] > 0) {
            $ids[(int) $row['version_id']] = (int) $row['version_id'];
        }
    }

    $out = array();

    if (!empty($ids)) {
        foreach ((array) db_items("SELECT id, n FROM ws_flow_versions WHERE id IN (" . implode(',', $ids) . ")") as $version) {
            $out[(int) $version['id']] = (int) $version['n'];
        }
    }

    return $out;
}

/**
 * A flow in the screen's list.
 *
 * @param array  $viewer
 * @param array  $flow  ws_flows row with access
 * @param int    $n     the number of its live version, 0 for none
 * @param string $start how it starts (ws_flow_start_kind()), '' for no start box
 * @return array
 */
function ws_flow_brief($viewer, $flow, $n, $start = '')
{
    return array(
        'id'         => (int) $flow['id'],
        'name'       => (string) $flow['name'],
        'status'     => (string) $flow['status'],
        'access'     => (string) $flow['access'],
        'mine'       => ((int) $flow['owner_id'] === (int) $viewer['id']),
        'channel_id' => (int) $flow['channel_id'],
        'draft_rev'  => (int) $flow['draft_rev'],
        'version'    => (int) $n,
        'start'      => (string) $start,
    );
}

/**
 * Whether the person reads a flow's draft: whoever may change it, and
 * whoever else reaches a flow never published (shared with them before it
 * was), read-only. Once a version is published, the others read that.
 *
 * @param string $access ws_flow_access()
 * @param int    $version_id the flow's live version, 0 for none
 * @return bool
 */
function ws_flow_draft_readable($access, $version_id)
{
    return ($access === 'edit') || (($access !== '') && ((int) $version_id <= 0));
}

/**
 * A flow opened on the screen: whoever may change it gets the draft and what
 * stops it from being published, whoever else reaches it the live graph (the
 * draft of a flow never published, read-only). manage says whether the
 * person manages its shares (its owner).
 *
 * @param array $viewer
 * @param array $flow ws_flow_for()
 * @return array
 */
function ws_flow_detail($viewer, $flow)
{
    $version = ws_flow_version($flow['version_id']);
    $live = is_array($version) ? ws_flow_graph_of($version) : null;
    $edit = ((string) $flow['access'] === 'edit');
    $draft = ws_flow_draft_readable((string) $flow['access'], is_array($version) ? (int) $version['id'] : 0) ? ws_flow_graph_of($flow) : null;

    $start = ws_flow_start_kind(ws_flow_start_graph($flow, $version));

    return ws_flow_brief($viewer, $flow, is_array($version) ? (int) $version['n'] : 0, $start) + array(
        'draft'    => ($draft !== null) ? ws_flow_graph_present($draft, $edit) : null,
        'graph'    => ($live !== null) ? ws_flow_graph_present($live, $edit) : null,
        'problems' => ($edit && ($draft !== null)) ? ws_flow_problems($draft) : array(),
        'warnings' => ($edit && ($draft !== null)) ? ws_flow_warnings($draft) : array(),
        // The draft holds something its live version does not.
        'changed'  => $edit && ($draft !== null) && (($live === null) || ($draft !== $live)),
        'manage'   => ws_flow_share_manager($viewer, $flow),
        'owner_id' => (int) $flow['owner_id'],
        // The boxes a patch of Pinegrap AI opened here added or changed
        // (flow_draft.php), marked for whoever may change it.
        'ai_marks' => ($edit && ($draft !== null)) ? ws_flow_ai_marks($viewer, $flow, $version, $draft) : null,
    );
}

/**
 * The texts of the flows screen and its canvas (assets/js/workspace_flow.js),
 * and the name the channel screen's sidebar gives it.
 *
 * @return array key => text
 */
function ws_flow_js_strings()
{
    return array(
        'fl_nav'              => lang('Flows'),
        'fl_sa_moved'         => lang('Your scheduled actions are in Flows.'),
        'fl_toolbar'          => lang('Flow tools'),
        'fl_settings'         => lang('Settings of what is selected'),
        'fl_canvas'           => lang('Canvas'),
        'fl_canvas_help'      => lang('The flow canvas. Tab moves from box to box; C connects the selected box to another, Delete removes it, the arrow keys move it. The outline view lists everything without the canvas.'),
        'fl_palette'          => lang('Add a box'),
        'fl_palette_search'   => lang('Search boxes'),
        'fl_palette_none'     => lang('No box matches.'),
        'fl_palette_this_flow' => ws_js_template('This flow: {var:1}', 1),
        'fl_group_start'      => lang('Start'),
        'fl_group_values'     => lang('Values'),
        'fl_group_logic'      => lang('Logic'),
        'fl_group_people'     => lang('People and tasks'),
        'fl_group_message'    => lang('Message'),
        'fl_group_system'     => lang('System'),
        'fl_zoom'             => lang('Zoom controls'),
        'fl_zoom_in'          => lang('Zoom in'),
        'fl_zoom_out'         => lang('Zoom out'),
        'fl_fit'              => lang('Fit to the screen'),
        'fl_outline'          => lang('Outline view'),
        'fl_outline_help'     => lang('The boxes in the order of their arrows, each with its settings. Everything the canvas does can be done here too.'),
        'fl_threads'          => lang('Data threads'),
        'fl_thread_line'      => ws_js_template('{var:1} reads {var:2}', 2),
        'fl_kind_constants'   => lang('Constants'),
        'fl_constants_none'   => lang('No value yet'),
        'fl_constants_rows'   => lang('Values it gives'),
        'fl_constants_name'   => lang('Name'),
        'fl_constants_type'   => lang('Type'),
        'fl_constants_value'  => lang('Value'),
        'fl_constants_text'   => lang('Text'),
        'fl_constants_number' => lang('Number'),
        'fl_constants_add'    => lang('Add a value'),
        'fl_constants_remove' => ws_js_template('Remove {var:1}', 1),
        'fl_constants_help'   => lang('This box has no arrows: each value reaches the boxes that read it by a data thread, the same in every run.'),
        'fl_kind_value'       => lang('Derived value'),
        'fl_derived_rule'     => lang('Rule'),
        'fl_derived_a'        => lang('Value it works from'),
        'fl_derived_b'        => lang('With'),
        'fl_derived_days'     => lang('Days to add'),
        'fl_derived_op'       => lang('Comparison'),
        'fl_derived_places'   => lang('Decimal places'),
        'fl_derived_format'   => lang('Date shape'),
        'fl_derived_value'    => lang('Value'),
        'fl_derived_help'     => lang('This box has no arrows: its value is worked out from the values it reads each time a box reads it. A comparison gives 1 when it holds, else 0; a value the rule cannot read gives an empty value.'),
        'fl_thread_dot'       => lang('Drag to a field, or double-click and choose the field'),
        'fl_thread_fork'      => lang('Fork'),
        'fl_thread_pass'      => lang('Pass to a field…'),
        'fl_thread_title'     => ws_js_template('Pass a value of {var:1} to a field', 1),
        'fl_thread_field'     => lang('Field it goes into'),
        'fl_thread_add'       => lang('Pass the value'),
        'fl_thread_none'      => lang('No field after this box can take this value.'),
        'fl_thread_pick'      => lang('Choose the field the value goes into; Esc stops.'),
        'fl_thread_bound'     => ws_js_template('{var:1} goes into {var:2}', 2),
        'fl_thread_full'      => lang('This field has no room for another value.'),
        'fl_new'              => lang('New flow'),
        'fl_list_search'      => lang('Search flows'),
        'fl_conv_title'       => ws_js_template('Converted from scheduled actions · {var:1}', 1),
        'fl_conv_mark'        => lang('Converted'),
        'fl_open_flow'        => lang('Open flow'),
        'fl_refused_title'    => ws_js_template('Not converted · {var:1}', 1),
        'fl_refused_help'     => lang('These scheduled actions could not become flows and still run as before. Edit tries the conversion again.'),
        'fl_list_filter'      => lang('Filter flows'),
        'fl_list_back'        => lang('Back to the flow list'),
        'fl_list_none_match'  => lang('No flow matches.'),
        'fl_list_never'       => lang('Not run yet'),
        'fl_list_no_start'    => lang('No start box yet'),
        'fl_col_flow'         => lang('Flow'),
        'fl_col_start'        => lang('How it starts'),
        'fl_col_state'        => lang('State'),
        'fl_col_channel'      => lang('Home channel'),
        'fl_chip_all'         => lang('All'),
        'fl_chip_time'        => lang('Time'),
        'fl_chip_manual'      => lang('By hand'),
        'fl_chip_event'       => lang('Event'),
        'fl_chip_join'        => lang('Join'),
        'fl_chip_draft'       => lang('Draft'),
        'fl_none_build'       => lang('No flows yet. "New flow" starts one: the box that starts it, the tasks that follow one another, the end.'),
        'fl_none_run'         => lang('No flows yet. A published flow of a channel you read, or a flow shared with you, appears here.'),
        'fl_not_ready'        => lang('The database has to be updated first.'),
        'fl_status_draft'     => lang('Draft'),
        'fl_status_live'      => ws_js_template('Live · v{var:1}', 1),
        'fl_status_paused'    => ws_js_template('Paused · v{var:1}', 1),
        'fl_unpublished'      => lang('Changes not published yet'),
        'fl_name'             => lang('Name of the flow'),
        'fl_untitled'         => lang('Untitled flow'),
        'fl_saved'            => lang('Draft saved'),
        'fl_saving'           => lang('Saving…'),
        'fl_unsaved'          => lang('Changes not saved'),
        'fl_retry'            => lang('Try again'),
        'fl_undo'             => lang('Undo'),
        'fl_redo'             => lang('Redo'),
        'fl_undone'           => lang('Change undone'),
        'fl_redone'           => lang('Change redone'),
        'fl_problems'         => ws_js_template('Problems: {var:1}', 1),
        'fl_no_problems'      => lang('No problems'),
        'fl_ai_title'         => lang('Changes of Pinegrap AI'),
        'fl_ai_marked'        => lang("Pinegrap AI's changes, marked until the flow is published:"),
        'fl_ai_added'         => lang('New'),
        'fl_ai_changed'       => lang('Changed'),
        'fl_ai_added_long'    => lang('Added by Pinegrap AI'),
        'fl_ai_changed_long'  => lang('Changed by Pinegrap AI'),
        'fl_ai_hide'          => lang('Hide marks'),
        'fl_ai_hidden'        => lang('The marks are hidden.'),
        'fl_warnings'         => ws_js_template('Warnings: {var:1}', 1),
        'fl_publish'          => lang('Publish'),
        'fl_publish_blocked'  => lang('Fix the problems before the flow is published.'),
        'fl_publish_ask'      => ws_js_template('Publish this draft as version {var:1}? Whoever may run the flow runs this version from now on; runs already started keep theirs.', 1),
        'fl_publish_do'       => ws_js_template('Publish version {var:1}', 1),
        'fl_published'        => ws_js_template('Published as version {var:1}.', 1),
        'fl_publish_owner'    => lang('Only the owner publishes this flow; your changes are kept in its draft.'),
        'fl_pause'            => lang('Pause'),
        'fl_paused'           => lang('The flow is paused. Publishing it again makes it live.'),
        'fl_more'             => lang('More actions'),
        'fl_bin'              => lang('Delete the flow'),
        'fl_bin_ask'          => ws_js_template('Delete the flow {var:1}? Nobody can open or run it any more.', 1),
        'fl_binned'           => lang('The flow was deleted.'),
        'fl_read_only'        => lang('You can see this flow; only its owner changes it.'),
        'fl_conflict'         => lang('This flow was changed meanwhile, on another screen or device. Load it again.'),
        'fl_reload'           => lang('Load it again'),
        'fl_leave_ask'        => lang('The changes to this flow could not be saved. Leave them and go on?'),
        'fl_leave'            => lang('Discard the changes'),
        'fl_empty_canvas'     => lang('Start with "Start manually" in the palette, add tasks and an end, then drag from the dot on the right of a box to the box that comes after it.'),
        'fl_kind_manual'      => lang('Started by hand'),
        'fl_kind_task'        => lang('Task'),
        'fl_kind_end'         => lang('End'),
        'fl_kind_post'        => lang('Post a message'),
        'fl_kind_email'       => lang('Send an e-mail'),
        'fl_add_post'         => lang('Post a message'),
        'fl_add_email'        => lang('Send an e-mail'),
        'fl_kind_notify'      => lang('Let people know'),
        'fl_kind_task_open'   => lang('Open a task without waiting'),
        'fl_kind_webhook'     => lang('Call a webhook'),
        'fl_kind_web_check'   => lang('Check a web address'),
        'fl_kind_task_digest' => lang('E-mail people their tasks'),
        'fl_kind_report'      => lang('Send a summary of counts'),
        'fl_kind_checkin'     => lang('Ask for a check-in'),
        'fl_kind_start_action' => lang('Start a scheduled action'),
        'fl_kind_start_flow'  => lang('Start a flow'),
        'fl_chain_pick'       => lang('Choose a flow'),
        'fl_chain_flow'       => lang('Flow to start'),
        'fl_chain_help'       => lang('A live flow started by hand. The boxes after this one go on without waiting for it; {{source}} names this flow in its texts.'),
        'fl_chain_none'       => lang('No flow chosen'),
        'fl_digest_mode'      => lang('Which tasks'),
        'fl_digest_mine'      => lang('Each person their own open tasks'),
        'fl_digest_channel'   => lang('The open tasks of one channel'),
        'fl_digest_channel_pick' => lang('Channel whose tasks are sent'),
        'fl_digest_who'       => lang('Who gets the e-mail'),
        'fl_digest_who_help'  => lang('Nobody ticked: everybody with such tasks, or the members of the channel.'),
        'fl_digest_only'      => lang('Which of them'),
        'fl_digest_intro'     => lang('Opening lines'),
        'fl_digest_missed'    => lang('Also what each person has not read yet'),
        'fl_digest_ai'        => lang('Pinegrap AI puts what they missed into a few sentences'),
        'fl_report_title'     => lang('Title of the summary'),
        'fl_report_counts'    => lang('Counts listed'),
        'fl_report_where_help' => lang('A channel, e-mail addresses separated by commas, or both.'),
        'fl_report_nowhere'   => lang('Nowhere to send yet'),
        'fl_checkin_question' => lang('Question'),
        'fl_checkin_help'     => lang('Asked of the channel\'s people; empty for the usual weekly question.'),
        'fl_start_pick'       => lang('Choose a scheduled action'),
        'fl_start_none'       => lang('No action chosen'),
        'fl_start_target'     => lang('Scheduled action to start'),
        'fl_start_delay'      => lang('Minutes later'),
        'fl_start_delay_help' => lang('0 starts it at once; at most a week (10,080 minutes).'),
        'fl_kind_delay'       => lang('Wait'),
        'fl_delay_amount'     => lang('How long'),
        'fl_delay_unit'       => lang('Counted in'),
        'fl_delay_unit_minutes' => lang('Minutes'),
        'fl_delay_unit_hours' => lang('Hours'),
        'fl_delay_unit_days'  => lang('Days'),
        'fl_delay_help'       => lang('The boxes after it start when the time is up: at least a minute, at most a week.'),
        'fl_delay_none'       => lang('No time set'),
        'fl_self_retry'       => lang('Tries again after a failure'),
        'fl_self_retry_help'  => lang('0 for never; at most 7 more tries, 8 in all. The arrow on failed is taken once they are used.'),
        'fl_self_retry_after' => lang('Minutes before each try'),
        'fl_self_retry_after_help' => lang('At least a minute, at most a week (10,080 minutes). A try may post or send again.'),
        'fl_delay_minutes'    => ws_js_template('{var:1} minutes', 1),
        'fl_delay_hours'      => ws_js_template('{var:1} hours', 1),
        'fl_delay_days'       => ws_js_template('{var:1} days', 1),
        'fl_delay_minute'     => lang('1 minute'),
        'fl_delay_hour'       => lang('1 hour'),
        'fl_delay_day'        => lang('1 day'),
        'fl_kind_approval'    => lang('Ask for approval'),
        'fl_on_approved'      => lang('Approved'),
        'fl_on_rejected'      => lang('Rejected'),
        'fl_on_expired'       => lang('Expired'),
        'fl_on_withdrawn'     => lang('Closed without a decision'),
        'fl_on_timeout'       => lang('Not done in time'),
        'fl_timeout'          => lang('Timeout (hours)'),
        'fl_timeout_help'     => lang('Empty to wait as long as it takes; at most 720 hours (30 days). Still open by then, the box leaves on Not done in time and the task stays open.'),
        'fl_timeout_meta'     => ws_js_template('{var:1} · timeout {var:2} h', 2),
        'fl_approval_channel' => lang('Channel to ask in'),
        'fl_approval_no_channel' => lang('No channel to ask in'),
        'fl_approval_who'     => lang('People asked to approve'),
        'fl_approval_who_help' => lang('People of the channel who may write in it.'),
        'fl_approval_rule'    => lang('Settled by'),
        'fl_approval_any'     => lang('Any one of them'),
        'fl_approval_all'     => lang('All of them'),
        'fl_approval_closes'  => lang('Hours to decide'),
        'fl_approval_closes_help' => lang('Empty for no deadline; at most 720 hours (30 days). A request with no decision by then expires.'),
        'fl_kind_change'      => lang('Change a record'),
        'fl_kind_lead'        => lang('Bring a customer into a channel'),
        'fl_kind_mcp'         => lang('Run a tool of an MCP server'),
        'fl_mcp_none'         => lang('No server chosen'),
        'fl_mcp_server'       => lang('MCP server'),
        'fl_mcp_server_pick'  => lang('Choose a server'),
        'fl_mcp_no_server'    => lang('No server is turned on with a tool that reads and an account you may call it with.'),
        'fl_mcp_server_out'   => lang('This server is turned off, has no tool that reads, or needs your own account first.'),
        'fl_mcp_tool'         => lang('Tool'),
        'fl_mcp_tool_pick'    => lang('Choose a tool'),
        'fl_mcp_tool_help'    => lang('Only the tools that read and run without an approval.'),
        'fl_mcp_tool_asks'    => ws_js_template('The tool {var:1} of @{var:2} asks before each call: a flow runs only the tools that read.', 2),
        'fl_mcp_tool_off'     => ws_js_template('The tool {var:1} is not turned on at @{var:2}.', 2),
        'fl_mcp_arguments'    => lang('Arguments'),
        'fl_mcp_arguments_help' => lang('A JSON object, empty for none. Put a value inside quotes: "{{node.box.field}}".'),
        'fl_mcp_arguments_bad' => lang('The arguments are not a JSON object.'),
        'fl_lead_meta'        => lang('The customer of the submitted form'),
        'fl_lead_meta_private' => lang('The customer of the submitted form, privately'),
        'fl_lead_private'     => lang('A channel it opens is private'),
        'fl_lead_help'        => lang('Starts after a form is submitted. A channel the customer already has is used; otherwise one is opened with the New customer template, at most 20 an hour.'),
        'fl_lead_people'      => lang('People put in a channel it opens'),
        'fl_kind_condition'   => lang('Condition'),
        'fl_kind_schedule'    => lang('At a time'),
        'fl_kind_event'       => lang('When something happens'),
        'fl_kind_join'        => lang('When somebody joins a channel'),
        'fl_when_none'        => lang('Not set yet'),
        'fl_when_date'        => lang('First day'),
        'fl_when_help'        => lang('Publishing the flow sets it to start on its own then, as its owner.'),
        'fl_when_time'        => lang('Time'),
        'fl_when_repeat'      => lang('Repeat'),
        'fl_when_every'       => lang('Every'),
        'fl_when_until'       => lang('Last day'),
        'fl_when_until_help'  => lang('Empty for no last day.'),
        'fl_event'            => lang('What happens'),
        'fl_event_pick'       => lang('Choose what happens'),
        'fl_event_help'       => lang('Each time it happens a run starts, as the flow\'s owner, while the flow is live.'),
        'fl_event_form'       => lang('Form'),
        'fl_event_any_form'   => lang('Any form'),
        'fl_event_status'     => lang('Status it moves to'),
        'fl_event_any_status' => lang('Any status'),
        'fl_event_channel'    => lang('Channel'),
        'fl_event_any_channel' => lang('Any channel'),
        'fl_join_channel'     => lang('Channel joined'),
        'fl_join_any'         => lang('Any public channel'),
        'fl_kind_row'         => lang('When a table row changes'),
        'fl_row_table'        => lang('Table'),
        'fl_row_table_pick'   => lang('Choose a table'),
        'fl_row_table_help'   => lang('Each row written starts a run, as the flow\'s owner, while the flow is live and they may read the table. Rows a file import adds start nothing.'),
        'fl_row_what'         => lang('What happens'),
        'fl_row_created'      => lang('A row is added'),
        'fl_row_updated'      => lang('A row is changed'),
        'fl_row_deleted'      => lang('A row is deleted'),
        'fl_row_column'       => lang('Column'),
        'fl_row_any_column'   => lang('Any column'),
        'fl_row_column_help'  => lang('A change starts the flow only when it writes this column.'),
        'fl_row_none'         => lang('No table chosen'),
        'fl_day_mon'         => lang('Monday'),
        'fl_day_tue'         => lang('Tuesday'),
        'fl_day_wed'         => lang('Wednesday'),
        'fl_day_thu'         => lang('Thursday'),
        'fl_day_fri'         => lang('Friday'),
        'fl_day_sat'         => lang('Saturday'),
        'fl_day_sun'         => lang('Sunday'),
        'fl_cond_pick'        => lang('Choose a condition'),
        'fl_cond_none'        => lang('No condition chosen'),
        'fl_cond_kind'        => lang('Condition'),
        'fl_cond_help'        => lang('An arrow on Yes is taken when it holds, one on No when it does not. Without an arrow on No the flow stops there, and the run keeps the reason.'),
        'fl_cond_workday'     => lang('A working day'),
        'fl_cond_weekdays'    => lang('Days of the week'),
        'fl_cond_hours'       => lang('Hours of the day'),
        'fl_cond_from'        => lang('Starts at'),
        'fl_cond_to'          => lang('Ends at'),
        'fl_cond_metric'      => lang('A count'),
        'fl_cond_metric_pick' => lang('Choose a count'),
        'fl_cond_any_channel' => lang('Every channel'),
        'fl_cond_threshold'   => lang('At most'),
        'fl_cond_record'      => lang('A field of a record'),
        'fl_cond_value_rule'  => lang('A value of an earlier box'),
        'fl_cond_value_ref'   => lang('Value'),
        'fl_cond_value_ref_pick' => lang('Choose a value'),
        'fl_cond_field'       => lang('Field'),
        'fl_cond_field_pick'  => lang('Choose a field'),
        'fl_cond_op'          => lang('Comparison'),
        'fl_cond_value'       => lang('Value'),
        'fl_cond_value_pick'  => lang('Choose a value'),
        'fl_cond_within_help' => lang('A number of days.'),
        'fl_cond_id_help'     => lang('The number of the record, as its page or the record drawer shows it. It is checked with your rights as you publish and again each time the box runs.'),
        'fl_cond_hidden'      => lang('Which record, and the value it is compared with, are shown only to whoever may change the flow.'),
        'fl_change_pick'      => lang('Choose a kind of record'),
        'fl_change_none'      => lang('No record chosen'),
        'fl_change_kind'      => lang('Kind of record'),
        'fl_change_action'    => lang('What is done'),
        'fl_change_update'    => lang('Change its fields'),
        'fl_change_delete'    => lang('Delete it'),
        'fl_change_id'        => lang('Record number'),
        'fl_change_id_help'   => lang('The number of the record, as its page or the record drawer shows it. It is checked with your rights as you publish and again each time the box runs.'),
        'fl_change_hidden'    => lang('Which record, and the new values, are shown only to whoever may change the flow.'),
        'fl_change_fields'    => lang('Fields to change'),
        'fl_change_fields_help' => lang('Add each field the box writes, then its new value.'),
        'fl_change_add'       => lang('Add a field'),
        'fl_change_remove'    => lang('Remove'),
        'fl_change_yes'       => lang('Yes'),
        'fl_change_no'        => lang('No'),
        'fl_change_answers'   => lang('A set of answers'),
        'fl_notify_nobody'    => lang('Nobody chosen yet'),
        'fl_notify_who'       => lang('Who is told'),
        'fl_notify_text'      => lang('What they are told'),
        'fl_task_open_meta'   => lang('Opened, the flow goes on at once'),
        'fl_task_channel'     => lang('Channel of the task'),
        'fl_task_no_channel'  => lang('No channel'),
        'fl_task_open_due_help' => lang('Days from when the box runs; empty for no due date. The flow does not wait for the task: a Task box does.'),
        'fl_url'              => lang('Address'),
        'fl_url_help'         => lang('An https address outside this network.'),
        'fl_url_none'         => lang('No address yet'),
        'fl_hook_format'      => lang('What is sent'),
        'fl_hook_generic'     => lang('JSON with the details of the run'),
        'fl_hook_text'        => lang('Chat message (Slack, Teams, Google Chat)'),
        'fl_hook_discord'     => lang('Discord message'),
        'fl_hook_help'        => lang('Each call carries an Idempotency-Key header, the same for one box of one run.'),
        'fl_check_mode'       => lang('What is looked at'),
        'fl_check_status'     => lang('Whether it answers'),
        'fl_check_ssl'        => lang('Days left on its certificate'),
        'fl_check_days'       => lang('Fails when fewer days are left than'),
        'fl_check_contains'   => lang('The page holds this text'),
        'fl_check_max_ms'     => lang('Answers within (ms)'),
        'fl_check_max_ms_help' => lang('0 for no limit.'),
        'fl_check_redirect'   => lang('A redirect counts as an answer'),
        'fl_post_meta'        => ws_js_template('In #{var:1}', 1),
        'fl_post_no_channel'  => lang('No channel chosen'),
        'fl_post_joined'      => lang('The channel they joined'),
        'fl_post_meta_joined' => lang('In the channel they joined'),
        'fl_post_pick'        => lang('Choose a channel'),
        'fl_post_channel'     => lang('Channel to write in'),
        'fl_post_body'        => lang('Message'),
        'fl_email_meta'       => ws_js_template('To {var:1}', 1),
        'fl_email_no_to'      => lang('No address yet'),
        'fl_email_to'         => lang('To'),
        'fl_email_to_help'    => lang('E-mail addresses, separated by commas.'),
        'fl_email_subject'    => lang('Subject'),
        'fl_email_body'       => lang('Text of the e-mail'),
        'fl_values_hint'      => lang('Add value puts in what started the run or what an earlier box gave; {{date}} and the other words of scheduled actions work too.'),
        'fl_values_add'       => lang('Add value'),
        'fl_values_text'      => lang('What it did'),
        'fl_values_outcome'   => lang('Outcome'),
        'fl_values_status'    => lang('Status'),
        'fl_values_skipped'   => lang('Empty if skipped'),
        'fl_values_missing'   => lang('No box before this one gives this value.'),
        'fl_values_none'      => lang('No box before this one gives a value yet: connect it after a box or a start.'),
        'fl_add_manual'       => lang('Start manually'),
        'fl_add_task'         => lang('Task'),
        'fl_add_end'          => lang('End'),
        'fl_box'              => lang('Flow box'),
        'fl_port_out'         => lang('Drag to the box that comes after it'),
        'fl_untitled_box'     => lang('No title yet'),
        'fl_manual_meta'      => lang('Starts when somebody runs it'),
        'fl_starts_on'        => ws_js_template('Started on: {var:1}', 1),
        'fl_end_meta'         => lang('The flow ends here'),
        'fl_due_meta'         => ws_js_template('{var:1} · due in {var:2} d', 2),
        'fl_people_more'      => ws_js_template('{var:1} and {var:2} more', 2),
        'fl_assign'           => lang('Given to'),
        'fl_assign_help'      => lang('The task opens for them once the boxes before it are done.'),
        'fl_assign_creator'   => lang('Whoever starts the run'),
        'fl_assign_people'    => lang('The people I pick'),
        'fl_people_filter'    => lang('Search people'),
        'fl_due_in'           => lang('Due (days after it becomes ready)'),
        'fl_due_help'         => lang('Left empty, the task has no due date.'),
        'fl_priority'         => lang('Task priority'),
        'fl_description'      => lang('Description'),
        'fl_description_help' => lang('Written into the task when it opens.'),
        'fl_title'            => lang('Title'),
        'fl_subject'          => lang('What it starts on'),
        'fl_subject_help'     => lang('A run can be about one record of this kind, such as an order or a customer.'),
        'fl_subject_none'     => lang('Nothing in particular'),
        'fl_after'            => lang('Comes after'),
        'fl_after_none'       => lang('Nothing; no arrow leads here yet.'),
        'fl_then'             => lang('Then comes'),
        'fl_then_none'        => lang('No arrow leaves this box yet.'),
        'fl_connect_pick'     => lang('Connect to…'),
        'fl_connect_add'      => lang('Add the arrow'),
        'fl_connect_title'    => ws_js_template('Connect {var:1} to the box that comes after it', 1),
        'fl_connect_none'     => lang('There is no box an arrow from here may lead to.'),
        'fl_connected'        => ws_js_template('Arrow added: {var:1} → {var:2}', 2),
        'fl_added'            => ws_js_template('Added: {var:1}', 1),
        'fl_removed'          => ws_js_template('Removed: {var:1}', 1),
        'fl_remove_box'       => lang('Remove the box'),
        // A box tried without running it (ws_flow_box_try()).
        'fl_try'              => lang('Try this box'),
        'fl_try_help'         => lang('See what this box would do now: nothing is sent, written or called.'),
        'fl_try_run'          => lang('Values of the boxes before it'),
        'fl_try_no_run'       => lang('No run: left empty'),
        'fl_try_run_item'     => ws_js_template('Run #{var:1} · {var:2}', 2),
        'fl_try_fails'        => ws_js_template('It would fail: {var:1}', 1),
        'fl_try_empty'        => ws_js_template('Left empty: {var:1}', 1),
        'fl_try_next'         => ws_js_template('Next times: {var:1}', 1),
        'fl_try_outcome'      => ws_js_template('Leaves on: {var:1}', 1),
        'fl_remove_arrow'     => lang('Remove the arrow'),
        'fl_remove_arrow_to'  => ws_js_template('Remove the arrow to {var:1}', 1),
        'fl_arrow'            => lang('Arrow'),
        'fl_arrow_text'       => ws_js_template('{var:2} waits for {var:1}.', 2),
        'fl_on'               => lang('Followed when the task is'),
        'fl_on_done'          => lang('Done'),
        'fl_on_cancelled'     => lang('Cancelled'),
        'fl_on_failed'        => lang('Failed'),
        'fl_on_always'        => lang('However it ended'),
        'fl_on_yes'           => lang('Yes'),
        'fl_on_no'            => lang('No'),
        'fl_on_box'           => lang('Followed when the box ends'),
        'fl_one_start'        => lang('A flow has one box that starts it.'),
        'fl_tpl_title'        => lang('Templates'),
        'fl_tpl_start'        => lang('Start a new flow'),
        'fl_tpl_empty'        => lang('Empty canvas'),
        'fl_tpl_empty_help'   => lang('Start with nothing and add the boxes yourself.'),
        'fl_tpl_from_template' => lang('Start from a template'),
        'fl_tpl_back'         => lang('Back to the flow'),
        'fl_tpl_use'          => lang('Use'),
        'fl_tpl_boxes'        => ws_js_template('{var:1} boxes', 1),
        'fl_tpl_threads'      => ws_js_template('{var:1} data threads', 1),
        'fl_tpl_setup'        => lang('Set it up'),
        'fl_tpl_setup_help'   => ws_js_template('{var:1} is on the canvas. Only these are left to choose; the rest can be changed on the canvas.', 1),
        'fl_tpl_open'         => lang('Open on canvas'),
        'fl_tpl_publish'      => lang('Publish now'),
        'fl_tpl_published'    => lang('The flow is published.'),
        'fl_tpl_close'        => lang('Close'),
        'fl_tpl_set_channel'  => lang('Channel'),
        'fl_tpl_set_person'   => lang('Who'),
        'fl_tpl_set_time'     => lang('Time'),
        'fl_tpl_set_url'      => lang('Web address'),
        'fl_tpl_set_address'  => lang('E-mail addresses'),
        'fl_tpl_save'         => lang('Save as template'),
        'fl_tpl_save_help'    => lang('The boxes and arrows of the draft are kept, without a webhook\'s address. Everyone who builds flows on this site finds it among the templates.'),
        'fl_tpl_save_do'      => lang('Save template'),
        'fl_tpl_saved'        => lang('The template is saved. The gallery shows it under Saved on this site.'),
        'fl_tpl_name'         => lang('Title'),
        'fl_tpl_line'         => lang('One line about it'),
        'fl_tpl_group'        => lang('Group'),
        'fl_tpl_cancel'       => lang('Cancel'),
        'fl_tpl_edit'         => lang('Edit template'),
        'fl_tpl_change_do'    => lang('Save changes'),
        'fl_tpl_changed'      => lang('The template is changed.'),
        'fl_tpl_delete'       => lang('Delete template'),
        'fl_tpl_delete_ask'   => ws_js_template('Delete the template {var:1}? Flows made from it keep their boxes.', 1),
        'fl_tpl_deleted'      => lang('The template is deleted.'),
        'fl_tpl_site_mark'    => lang('Saved on this site'),
        'fl_too_many_boxes'   => ws_js_template('A flow can hold at most {var:1} boxes.', 1),
        'fl_flow_settings'    => lang('The flow'),
        'fl_flow_help'        => lang('Select a box to change it. Nothing runs until the flow is published.'),
        'fl_home'             => lang('Home channel'),
        'fl_home_none'        => lang('No channel (only you)'),
        'fl_home_help'        => lang('Once the flow is published, the people who read this channel may run it.'),
        'fl_keys'             => lang('Keyboard'),
        'fl_key_tab'          => lang('Tab: from box to box, in the order of the arrows'),
        'fl_key_arrows'       => lang('Arrow keys: move the selected box (with Shift, further)'),
        'fl_key_connect'      => lang('C: connect the selected box to the one that comes after it'),
        'fl_key_thread'       => lang('V: pass a value of the selected box to a field of a box after it'),
        'fl_key_delete'       => lang('Delete: remove the selected box or arrow'),
        'fl_key_undo'         => lang('Ctrl+Z: undo the last change; Ctrl+Y or Ctrl+Shift+Z: redo it'),
        'fl_key_enter'        => lang('Enter: go to the settings of the selected box'),
        'fl_key_zoom'         => lang('+ and −: zoom; 0: fit everything on the screen'),
        'fl_key_pan'          => lang('Space and drag, or drag the empty canvas: move the view; Ctrl and the wheel: zoom'),
        'fl_key_escape'       => lang('Esc: clear the selection'),
        // The runs of a flow, shown on the canvas (flow_runs.php).
        'fl_run'              => lang('Run'),
        'fl_run_help'         => lang('Run the live version of this flow'),
        'fl_run_ask'          => ws_js_template('Run “{var:1}” now? Version {var:2} starts, and its first tasks open at once in the name of the owner of the flow.', 2),
        'fl_run_do'           => lang('Start the run'),
        'fl_run_started'      => lang('The run has started.'),
        'fl_run_again'        => lang('This run had started already.'),
        'fl_runs'             => lang('Flow runs'),
        'fl_runs_title'       => lang('Runs of this flow'),
        'fl_runs_none'        => lang('This flow has not run yet.'),
        'fl_runs_more'        => lang('Show older runs'),
        'fl_runs_failed'      => lang('The runs could not be loaded.'),
        'fl_run_name'         => ws_js_template('Run {var:1}', 1),
        'fl_run_version'      => ws_js_template('Version {var:1}', 1),
        'fl_run_by'           => ws_js_template('Started by {var:1}', 1),
        'fl_run_at'           => ws_js_template('Started at {var:1}', 1),
        'fl_run_ended'        => ws_js_template('Ended at {var:1}', 1),
        'fl_run_open'         => lang('Still going'),
        'fl_run_shown'        => ws_js_template('Run {var:1} of version {var:2} is shown as it went. Nothing can be changed while it is open.', 2),
        'fl_run_close'        => lang('Close the run'),
        'fl_run_lost'         => lang('The run is no longer followed; it is shown as it was last read.'),
        'fl_run_unreached'    => lang('The run could not be read just now; it will be tried again shortly.'),
        'fl_run_all'          => lang('All runs'),
        'fl_run_cancel'       => lang('Cancel the run'),
        'fl_run_cancel_ask'   => lang('Cancel this run? Its open boxes are skipped; the tasks it opened stay as they are.'),
        'fl_run_cancelled'    => lang('The run was cancelled.'),
        'fl_run_task'         => lang('Open the task'),
        'fl_run_task_of'      => ws_js_template('Open the task of {var:1}', 1),
        'fl_run_result'       => lang('Result'),
        'fl_run_values'       => lang('Values received'),
        'fl_run_filled'       => lang('As filled in this run'),
        'fl_run_template_show' => lang('Show the template'),
        'fl_run_template_hide' => lang('Hide the template'),
        'fl_run_value_empty'  => lang('Empty'),
        'fl_run_value_derived' => lang('worked out as the box read it'),
        'fl_run_value_pending' => lang('Not given yet'),
        'fl_run_value_skipped' => lang('Empty: the box was skipped'),
        'fl_run_value_unreached' => lang('Empty: the box was not reached'),
        'fl_run_value_trigger' => lang('Not shown here: it comes with what started the run'),
        'fl_run_pick'         => lang('Select a box to see how it went in this run.'),
        'fl_run_boxes'        => lang('The boxes of this run'),
        'fl_run_pair'         => ws_js_template('{var:1}: {var:2}', 2),
        'fl_run_running'      => lang('Running'),
        'fl_run_waiting'      => lang('Waiting'),
        'fl_run_done'         => lang('Done'),
        'fl_run_failed'       => lang('Failed'),
        'fl_run_cancelled_state' => lang('Cancelled'),
        // The run's flag (runFlag() in workspace_flow.js).
        'fl_flag_done'        => lang('Completed'),
        'fl_flag_last'        => lang('Last run'),
        'fl_flag_stop'        => lang('Stop'),
        'fl_flag_open'        => lang('Open the run'),
        'fl_flag_more'        => lang('Show the whole result'),
        'fl_flag_less'        => lang('Hide the whole result'),
        'fl_flag_sec'         => ws_js_template('{var:1} s', 1),
        'fl_flag_min'         => ws_js_template('{var:1} min', 1),
        'fl_flag_hour'        => ws_js_template('{var:1} h {var:2} min', 2),
        'fl_flag_day'         => ws_js_template('{var:1} d {var:2} h', 2),
        // Add › Start a flow in a channel's composer and the run's card there.
        'fl_here_start'       => lang('Start a flow'),
        'fl_here_help'        => ws_js_template('The flows you may start in {var:1}. A run opens its tasks here, and its card here follows it.', 1),
        'fl_here_none'        => lang('No flow can be started here. A flow is listed once it is published, started by hand, and its owner may write in this channel.'),
        'fl_run_card'         => ws_js_template('Run of {var:1}', 1),
        'fl_step_ready'       => lang('Ready'),
        'fl_step_running'     => lang('Running'),
        'fl_step_waiting'     => lang('Waiting'),
        'fl_step_done'        => lang('Done'),
        'fl_step_failed'      => lang('Failed'),
        'fl_step_skipped'     => lang('Skipped'),
        'fl_step_none'        => lang('Not reached yet'),
        'fl_step_retry'       => lang('Try again'),
        'fl_step_skip'        => lang('Skip'),
        'fl_step_retry_ask'   => ws_js_template('Try the box {var:1} again? It runs once more in the name of the owner of the flow: a message or e-mail it may have sent already can go out again, and a task box opens a new task.', 1),
        'fl_step_skip_ask'    => ws_js_template('Skip the box {var:1}? The run goes on as if its branch had not been taken.', 1),
        'fl_step_retried'     => lang('The box will run again.'),
        'fl_step_skipped_by_hand' => lang('The box was skipped.'),
        // A flow shared with the team (flow_shares.php).
        'fl_level_view'       => lang('Can view'),
        'fl_level_run'        => lang('Can run'),
        'fl_level_edit'       => lang('Can edit'),
        'fl_share'            => lang('Share'),
        'fl_shared_with'      => lang('Who it is shared with'),
        'fl_share_title'      => ws_js_template('Share “{var:1}”', 1),
        'fl_share_help'       => lang('A colleague may look at the flow, run it once it is live, or change its draft with you, which you publish; changing it needs staff in the team, and for anybody else it acts as running. The people who read a channel may look at it. A run always acts with your rights.'),
        'fl_share_read_help'  => lang('Only the owner of the flow shares it.'),
        'fl_share_list'       => lang('Shared with'),
        'fl_share_none'       => lang('Not shared with anybody yet.'),
        'fl_share_owner'      => lang('Owner'),
        'fl_share_people'     => lang('People'),
        'fl_share_channel'    => lang('Channel'),
        'fl_share_level'      => lang('Level'),
        'fl_share_as_run'     => lang('Acts as running: not staff in the team'),
        'fl_share_hidden'     => lang('A channel you do not read'),
        'fl_share_gone'       => lang('No longer in the team'),
        'fl_share_pick'       => lang('Pick at least one colleague.'),
        'fl_share_remove'     => ws_js_template('Stop sharing with {var:1}', 1),
        'fl_share_added'      => ws_js_template('Shared with {var:1}.', 1),
        'fl_share_changed'    => ws_js_template('{var:1}: {var:2}', 2),
        'fl_share_removed'    => ws_js_template('No longer shared with {var:1}.', 1),
        'fl_share_close'      => lang('Close'),
        'fl_home_owner_only'  => lang('Only the owner of a flow can move it to another home channel.'),
        'fl_home_none_other'  => lang('No channel'),
    );
}

/**
 * What the Workspace script is told about flows: whether its menus show
 * them, and on the flows screen whether the person builds them, the kinds of
 * record a start box may be about and the limits of a graph.
 *
 * A record's drawer (mode record) draws no channel sidebar: it is told
 * nothing and nothing is read for it.
 *
 * @param array  $viewer
 * @param string $mode the screen's mode, as ws_screen_config() has it
 * @return array
 */
function ws_flow_js_config($viewer, $mode)
{
    if ($mode === 'record') {
        return array('nav' => false);
    }

    $config = array('nav' => ws_flows_nav_visible($viewer), 'scheduled' => ws_flows_scheduled($viewer));

    if ($mode !== 'flows') {
        return $config;
    }

    $subjects = array();

    foreach ((function_exists('ws_scheduled_record_types') ? ws_scheduled_record_types() : array()) as $type => $info) {
        $subjects[] = array('key' => (string) $type, 'label' => (string) $info['label']);
    }

    // What the step boxes offer to choose from, to whoever builds flows: the
    // counts the person may read, the task e-mail's filters, and the
    // scheduled actions a box may start.
    $build = ws_flows_ready() && ws_can_schedule($viewer);
    $steps = array('metrics' => array(), 'digest_only' => array(), 'targets' => array(), 'changes' => (object) array(),
        'records' => (object) array(), 'operators' => (object) array(), 'counts' => array(), 'repeats' => (object) array(), 'events' => null, 'tables' => array(), 'mcp' => array());

    // The boxes that may try again on their own after a failure, and how
    // many times at most (flow_runs.php).
    $steps['retry'] = array('kinds' => function_exists('ws_step_kinds') ? array_keys(ws_step_kinds()) : array(), 'max' => WS_FLOW_RETRY_MAX, 'after_max' => WS_FLOW_DELAY_MAX);

    if ($build && function_exists('ws_scheduled_metrics')) {
        foreach (ws_scheduled_metrics() as $key => $metric) {
            if (($metric['right'] === '') || !empty($viewer[$metric['right']])) {
                $steps['metrics'][] = array('key' => (string) $key, 'label' => (string) $metric['label'], 'scope' => (string) $metric['scope']);
            }
        }

        $steps['digest_only'] = ws_scheduled_digest_filters();
        $steps['targets'] = ws_scheduled_targets($viewer);

        // The kinds of record the person may change, with their actions and
        // fields, as the scheduled form offers them.
        $scheduled = function_exists('ws_scheduled_js_config') ? ws_scheduled_js_config($viewer) : null;
        $steps['changes'] = (object) (is_array($scheduled) ? $scheduled['changes'] : array());

        // What a condition box looks at: the kinds of record with their
        // fields, and how a value or a count is compared.
        $steps['records'] = (object) (is_array($scheduled) ? $scheduled['records'] : array());
        $steps['operators'] = (object) (is_array($scheduled) ? $scheduled['operators'] : array());
        $steps['counts'] = is_array($scheduled) ? $scheduled['counts'] : array();

        // What the boxes that start a flow on their own offer: the ways a
        // time repeats, and the events the person may wait for.
        $steps['repeats'] = (object) (is_array($scheduled) ? $scheduled['repeats'] : array());
        $steps['events'] = is_array($scheduled) ? $scheduled['events'] : null;

        // The workflow tables a row box may start on, with their columns.
        $steps['tables'] = function_exists('ws_flow_row_tables') ? ws_flow_row_tables($viewer) : array();

        // The MCP servers an MCP box may call with the person's account and
        // the tools that read there; none, and the palette has no MCP box.
        if (function_exists('ws_mcp_active_servers') && function_exists('ws_step_mcp_form_servers')) {
            $steps['mcp'] = ws_step_mcp_form_servers(ws_mcp_active_servers(), function ($server) use ($viewer) {
                return ($server['auth_kind'] !== 'oauth') || (bool) ws_mcp_oauth_account($server, (int) $viewer['id']);
            });
        }
    }

    // The values each kind of box gives the boxes after it (a report's
    // counts follow from the counts it is set to send: ws_step_outputs()).
    $outputs = array();

    foreach (ws_flow_kinds() as $kind => $info) {
        $outputs[$kind] = $info['outputs'];
    }

    // The values what started a run gives ({{trigger.<field>}}): each start
    // kind's own, and an event box's by the event it waits for.
    $trigger_outputs = array('kinds' => array(), 'events' => function_exists('ws_flow_event_outputs') ? ws_flow_event_outputs() : array());

    foreach (ws_flow_kinds() as $kind => $info) {
        if (!empty($info['trigger'])) {
            $trigger_outputs['kinds'][$kind] = function_exists('ws_flow_trigger_outputs') ? ws_flow_trigger_outputs($kind) : array();
        }
    }

    // The ready-made flows the gallery offers to whoever builds flows
    // (flow_templates.php), filled for the person: their texts go to them.
    $templates = array();

    if ($build && is_array($scheduled ?? null) && function_exists('ws_flow_templates_for')) {
        $templates = ws_flow_templates_for($viewer, $scheduled);
    }

    return $config + array(
        'ready'    => ws_flows_ready(),
        'build'    => $build,
        'templates' => $templates,
        'template_categories' => function_exists('ws_flow_template_categories') ? ws_flow_template_categories() : array(),
        // "Save as template" in the editor: builders, once the table of
        // saved templates is there (flow_templates_saved.php).
        'template_save' => $build && function_exists('ws_flow_templates_saved_ready') && ws_flow_templates_saved_ready(),
        'steps'    => $steps,
        'outputs'  => $outputs,
        'trigger_outputs' => $trigger_outputs,
        'subjects' => $subjects,
        'derived'  => function_exists('ws_flow_derived_rules') ? ws_flow_derived_lists() : array(),
        'limits'   => array('nodes' => WS_FLOW_MAX_NODES, 'notes' => WS_FLOW_MAX_NOTES, 'coord' => WS_FLOW_COORD_MAX, 'constants' => defined('WS_FLOW_CONSTANTS_MAX') ? WS_FLOW_CONSTANTS_MAX : 20),
    );
}
