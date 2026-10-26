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
 * The kinds of box a flow knows: whether the box starts the flow, and the
 * outcomes an arrow may leave it on.
 *
 * @return array kind => label, trigger, outcomes
 */
function ws_flow_kinds()
{
    $kinds = array(
        'manual' => array('label' => lang('Started by hand'), 'trigger' => true, 'outcomes' => array('next'), 'step' => false),
        'task'   => array('label' => lang('Task'), 'trigger' => false, 'outcomes' => array('done', 'cancelled'), 'step' => false),
        'end'    => array('label' => lang('End'), 'trigger' => false, 'outcomes' => array(), 'step' => false),
    );

    // The steps of a scheduled action, each a box of its own (steps.php).
    foreach ((function_exists('ws_step_kinds') ? ws_step_kinds() : array()) as $kind => $info) {
        $kinds[$kind] = array('label' => $info['label'], 'trigger' => false, 'outcomes' => ws_step_outcomes(), 'step' => true);
    }

    return $kinds;
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
 *         the run), due_in ('' or 0-3650 days), priority
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

            return array(
                'title'       => ws_flow_line($config['title'] ?? '', 200),
                'description' => ws_flow_lines($config['description'] ?? '', 8000),
                'assignees'   => array_slice(array_values($assignees), 0, 20),
                'assign'      => ws_flow_line($config['assign'] ?? '', 40),
                'due_in'      => (is_bool($due_in) || !is_numeric($due_in)) ? '' : max(0, min(3650, (int) $due_in)),
                'priority'    => in_array($priority, array('low', 'normal', 'high', 'urgent'), true) ? $priority : 'normal',
            );
    }

    return function_exists('ws_step_config_normalize') ? ws_step_config_normalize($kind, $config) : array();
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
function ws_flow_problem($code, $node, $edge, $text)
{
    return array('code' => $code, 'node' => $node, 'edge' => $edge, 'text' => $text);
}

/**
 * What stops a graph from being published, in the order a reader fixes it:
 * too_many, bad_id, duplicate_id, unknown_kind, bad_config, no_trigger,
 * two_triggers, bad_edge, arrow_into_trigger, bad_outcome, duplicate_edge,
 * cycle, unreachable. Notes are never checked. A kind of record a start box
 * names is checked by ws_flow_problems(), which knows the site's records.
 *
 * @param array|string $graph
 * @return array[] code, node, edge, text (none for a graph that may be published)
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
        }

        if ($kinds[$node['kind']]['step']) {
            foreach (ws_step_config_problems($node['kind'], $node['config'], $name) as $text) {
                $problems[] = ws_flow_problem('bad_config', $id, null, $text);
            }
        }
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

        if (in_array($to, $triggers, true)) {
            $problems[] = ws_flow_problem('arrow_into_trigger', $to, $index, lang(array('string' => 'No arrow may lead into the box that starts the flow ({var:1}).', 'vars' => ws_flow_box_name($nodes[$to]))));
        }

        $kind = $nodes[$from]['kind'];

        if (isset($kinds[$kind]) && !in_array($edge['on'], $kinds[$kind]['outcomes'], true)) {
            $problems[] = ws_flow_problem('bad_outcome', $from, $index, lang(array('string' => 'An arrow leaves the box {var:1} on {var:2}, which is not one of its outcomes.', 'vars' => array(ws_flow_box_name($nodes[$from]), ($edge['on'] !== '') ? $edge['on'] : '-'))));
        }

        $key = $from . "\n" . $to . "\n" . $edge['on'];

        if (isset($seen[$key])) {
            $problems[] = ws_flow_problem('duplicate_edge', $from, $index, lang(array('string' => 'Two arrows join {var:1} to {var:2} on the same outcome.', 'vars' => array(ws_flow_box_name($nodes[$from]), ws_flow_box_name($nodes[$to])))));
            continue;
        }

        $seen[$key] = true;

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
            if (!isset($reached[$id]) && isset($kinds[$node['kind']])) {
                $problems[] = ws_flow_problem('unreachable', (string) $id, null, lang(array('string' => 'No arrow from the start leads to the box {var:1}.', 'vars' => ws_flow_box_name($node))));
            }
        }
    }

    return $problems;
}

/* ---------------------------------------------------------------------------
   The store
   --------------------------------------------------------------------------- */

/**
 * What stops a graph from being published, the site's own records included:
 * ws_flow_graph_check(), and a start box about a kind of record the
 * Workspace does not know (ws_scheduled_record_types()).
 *
 * @param array|string $graph
 * @return array[] code, node, edge, text
 */
function ws_flow_problems($graph)
{
    $graph = ws_flow_graph_normalize($graph);
    $problems = ws_flow_graph_check($graph);
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
        'owner'   => $owner,
        'staff'   => $staff,
        'status'  => (string) $flow['status'],
        'home'    => $home,
        'person'  => $reach['person'],
        'channel' => $reach['channel'],
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
 * with them or in a channel they read. Drafts are not read.
 *
 * @param array  $viewer
 * @param string $scope all | mine | shared
 * @return array[] rows without draft, each with access
 */
function ws_flows_list($viewer, $scope = 'all')
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
        WHERE status <> 'binned' AND (owner_id = '" . (int) $viewer['id'] . "' OR (status = 'live' AND channel_id > 0)" . $shared . ")
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

    return $out;
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
 * @return array ok, error, status, field, flow_id, draft_rev, problems
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

        return array('ok' => true, 'error' => '', 'status' => 201, 'field' => '', 'flow_id' => $flow_id, 'draft_rev' => 1, 'problems' => $check['problems']);
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

    return array('ok' => true, 'error' => '', 'status' => 200, 'field' => '', 'flow_id' => (int) $flow['id'], 'draft_rev' => (int) $check['draft_rev'] + 1, 'problems' => $check['problems']);
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
 * @return array ok, error, status, field, graph, json (the graph as it will be kept), problems
 */
function ws_flow_publish_check($viewer, $flow, $data = array())
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (!ws_flow_share_manager($viewer, $flow)) {
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
            $step = ws_step_check($viewer, $node['kind'], $node['config']);

            if (!$step['ok']) {
                return ws_flow_refusal(lang(array('string' => 'The box {var:1}: {var:2}', 'vars' => array(ws_flow_box_name($node), $step['error']))));
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
    $written = @mysqli_query(db::$con, "INSERT INTO ws_flow_versions (flow_id, n, graph, published_by, published_at)
        VALUES ('" . $flow_id . "', '" . $n . "', '" . e($check['json']) . "', '" . (int) $viewer['id'] . "', '" . $now . "')");

    if (!$written) {
        return (((int) mysqli_errno(db::$con) === 1062)
            ? ws_flow_refusal(lang('This flow was published meanwhile.'), 409)
            : ws_flow_refusal(lang('The flow could not be published.'))) + array('version_id' => 0, 'n' => 0);
    }

    $version_id = (int) mysqli_insert_id(db::$con);

    db("UPDATE ws_flows SET status = 'live', version_id = '" . $version_id . "', updated_by = '" . (int) $viewer['id'] . "', updated_at = '" . $now . "'
        WHERE id = '" . $flow_id . "' AND status <> 'binned'");

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
 * the whole address of a webhook or web check box (ws_flow_url_masked()).
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

            $node['config'] = (object) $node['config'];

            return $node;
        }, $graph['nodes']),
        'edges' => $graph['edges'],
        'notes' => $graph['notes'],
    );
}

/**
 * Are this person's scheduled actions listed in Flows (a section of the
 * flows screen) rather than on the scheduled screen? Staff's are, once the
 * flow tables are there; their scheduled messages stay on the scheduled
 * screen, and so does everything without the flow tables.
 *
 * @param array $viewer
 * @return bool
 */
function ws_flows_scheduled($viewer)
{
    return function_exists('ws_can_schedule') && ws_can_schedule($viewer) && ws_flows_nav_visible($viewer);
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
 * @param array $viewer
 * @param array $flow ws_flows row with access
 * @param int   $n    the number of its live version, 0 for none
 * @return array
 */
function ws_flow_brief($viewer, $flow, $n)
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

    return ws_flow_brief($viewer, $flow, is_array($version) ? (int) $version['n'] : 0) + array(
        'draft'    => ($draft !== null) ? ws_flow_graph_present($draft, $edit) : null,
        'graph'    => ($live !== null) ? ws_flow_graph_present($live, $edit) : null,
        'problems' => ($edit && ($draft !== null)) ? ws_flow_problems($draft) : array(),
        // The draft holds something its live version does not.
        'changed'  => $edit && ($draft !== null) && (($live === null) || ($draft !== $live)),
        'manage'   => ws_flow_share_manager($viewer, $flow),
        'owner_id' => (int) $flow['owner_id'],
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
        'fl_zoom'             => lang('Zoom controls'),
        'fl_zoom_in'          => lang('Zoom in'),
        'fl_zoom_out'         => lang('Zoom out'),
        'fl_fit'              => lang('Fit to the screen'),
        'fl_outline'          => lang('Outline view'),
        'fl_outline_help'     => lang('The boxes in the order of their arrows, each with its settings. Everything the canvas does can be done here too.'),
        'fl_new'              => lang('New flow'),
        'fl_list_mine'        => lang('My flows'),
        'fl_list_shared'      => lang('Shared with me and my channels'),
        'fl_none'             => lang('No flows yet.'),
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
        'fl_problems'         => ws_js_template('Problems: {var:1}', 1),
        'fl_no_problems'      => lang('No problems'),
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
        'fl_post_pick'        => lang('Choose a channel'),
        'fl_post_channel'     => lang('Channel to write in'),
        'fl_post_body'        => lang('Message'),
        'fl_email_meta'       => ws_js_template('To {var:1}', 1),
        'fl_email_no_to'      => lang('No address yet'),
        'fl_email_to'         => lang('To'),
        'fl_email_to_help'    => lang('E-mail addresses, separated by commas.'),
        'fl_email_subject'    => lang('Subject'),
        'fl_email_body'       => lang('Text of the e-mail'),
        'fl_values_help'      => lang('Written in your name when the box runs. {{node.ID.text}} puts in what an earlier box of the run did, {{node.ID.message_id}} the message it posted; {{date}} and the other words of scheduled actions work too.'),
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
        'fl_remove_arrow'     => lang('Remove the arrow'),
        'fl_remove_arrow_to'  => ws_js_template('Remove the arrow to {var:1}', 1),
        'fl_arrow'            => lang('Arrow'),
        'fl_arrow_text'       => ws_js_template('{var:2} waits for {var:1}.', 2),
        'fl_on'               => lang('Followed when the task is'),
        'fl_on_done'          => lang('Done'),
        'fl_on_cancelled'     => lang('Cancelled'),
        'fl_on_failed'        => lang('Failed'),
        'fl_on_box'           => lang('Followed when the box ends'),
        'fl_one_start'        => lang('A flow has one box that starts it.'),
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
        'fl_key_delete'       => lang('Delete: remove the selected box or arrow'),
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
        'fl_run_pick'         => lang('Select a box to see how it went in this run.'),
        'fl_run_boxes'        => lang('The boxes of this run'),
        'fl_run_pair'         => ws_js_template('{var:1}: {var:2}', 2),
        'fl_run_running'      => lang('Running'),
        'fl_run_waiting'      => lang('Waiting'),
        'fl_run_done'         => lang('Done'),
        'fl_run_failed'       => lang('Failed'),
        'fl_run_cancelled_state' => lang('Cancelled'),
        'fl_step_ready'       => lang('Ready'),
        'fl_step_running'     => lang('Running'),
        'fl_step_waiting'     => lang('Waiting'),
        'fl_step_done'        => lang('Done'),
        'fl_step_failed'      => lang('Failed'),
        'fl_step_skipped'     => lang('Skipped'),
        'fl_step_none'        => lang('Not reached yet'),
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

    return $config + array(
        'ready'    => ws_flows_ready(),
        'build'    => ws_flows_ready() && ws_can_schedule($viewer),
        'subjects' => $subjects,
        'limits'   => array('nodes' => WS_FLOW_MAX_NODES, 'notes' => WS_FLOW_MAX_NOTES, 'coord' => WS_FLOW_COORD_MAX),
    );
}
