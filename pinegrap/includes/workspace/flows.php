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
 * Staff in the team build flows (ws_can_schedule()) and a flow is its owner's
 * alone, as a note is: being an administrator gives no view of somebody
 * else's flow. A live flow with a home channel may be run by the people who
 * read that channel. Site members never reach a flow. Publishing is the
 * person's own decision, since a published flow later acts in their name.
 *
 * Its runs are carried out by includes/workspace/flow_runs.php. The canvas
 * and the boxes beyond start, task and end come later on the same tables
 * (ws_flow_runs, ws_flow_run_steps).
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
    return array(
        'manual' => array('label' => lang('Started by hand'), 'trigger' => true, 'outcomes' => array('next')),
        'task'   => array('label' => lang('Task'), 'trigger' => false, 'outcomes' => array('done', 'cancelled')),
        'end'    => array('label' => lang('End'), 'trigger' => false, 'outcomes' => array()),
    );
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

    return array();
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
 * What the person may do with a flow:
 *   edit  its owner, staff in the team (ws_can_schedule()); running it too
 *   run   a live flow whose home channel (public or private) they read
 *   ''    nothing: somebody else's flow without a home channel they read,
 *         however high their role, and every deleted flow
 *
 * @param array      $viewer
 * @param array|null $flow
 * @return string edit | run | ''
 */
function ws_flow_access($viewer, $flow)
{
    if (!is_array($flow) || empty($viewer['member']) || ((string) $flow['status'] === 'binned')) {
        return '';
    }

    if (((int) $flow['owner_id'] === (int) $viewer['id']) && ws_can_schedule($viewer)) {
        return 'edit';
    }

    if (((string) $flow['status'] === 'live') && ((int) $flow['channel_id'] > 0)) {
        $channel = ws_channel($flow['channel_id']);

        if ($channel && in_array((string) $channel['kind'], array('public', 'private'), true) && ws_can_read_channel($viewer, $channel)) {
            return 'run';
        }
    }

    return '';
}

/**
 * A flow the person may reach at least as far as asked.
 *
 * @param array  $viewer
 * @param int    $id
 * @param string $need run | edit
 * @return array|null the row, with access
 */
function ws_flow_for($viewer, $id, $need = 'run')
{
    $flow = ws_flow($id);
    $access = ws_flow_access($viewer, $flow);
    $rank = array('' => 0, 'run' => 1, 'edit' => 2);

    if (($access === '') || ($rank[$access] < $rank[$need])) {
        return null;
    }

    $flow['access'] = $access;

    return $flow;
}

/**
 * The flows the person reaches, the latest changed first: their own, and the
 * live flows of others whose home channel they read. Drafts are not read.
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

    $rows = (array) db_items("SELECT id, owner_id, channel_id, name, status, draft_rev, version_id, updated_by, created_at, updated_at, binned_at
        FROM ws_flows
        WHERE status <> 'binned' AND (owner_id = '" . (int) $viewer['id'] . "' OR (status = 'live' AND channel_id > 0))
        ORDER BY updated_at DESC, id DESC
        LIMIT 1000");

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
 * Whether the person may publish the flow's draft: theirs to change, no
 * problem left in it, and every task box with people on it giving work only
 * to people they may give it to (ws_task_create_check()). A box with a rule
 * is resolved when the run opens its task.
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

    if (ws_flow_access($viewer, $flow) !== 'edit') {
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
 * Whether the person may pause the flow: theirs, and live.
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
 * Whether the person may delete the flow: theirs to change. Its open runs do
 * not hold it back: they end with it.
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

    if (ws_flow_access($viewer, $flow) !== 'edit') {
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
