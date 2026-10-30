<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the For each loop of a flow. A For each box reads a list (a
 * JSON array kept by a box before it, such as a webhook's answer) and its
 * each arrow leads into the loop's body: the boxes between it and its End
 * for each box. The body runs once per item, one item after another, its
 * boxes' steps carrying pass = the item's place (1, 2, ...); boxes outside
 * a loop keep pass 0. The End box settles once every item has, and the flow
 * goes on from it once, with what the items gave (count, done, failed and
 * the list of one value per item).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most items one For each box goes through; a longer list fails it.
define('WS_FLOW_LOOP_ITEMS_MAX', 50);

// The most bytes the items a For each box keeps for its body may take.
define('WS_FLOW_LOOP_ITEMS_BYTES', 32768);

// The most bytes of the list an End box gives, as the webhook's answer.
define('WS_FLOW_LOOP_LIST_BYTES', 16384);

// The most tasks, approvals and e-mails the boxes inside loops open in a run.
define('WS_FLOW_LOOP_EFFECTS_MAX', 20);

// The most fields of an item a For each box gives by name.
define('WS_FLOW_LOOP_FIELDS_MAX', 10);

/**
 * The settings of a For each box (list: the one value holding the list, path:
 * where the list sits inside it, as data.items; fields: the keys of an item
 * it gives by name) or of an End for each box (for_each: the box whose loop
 * it ends; collect: the one value of the body it gathers per item). Pure.
 *
 * @param string $kind for_each | for_each_end
 * @param array  $config
 * @return array
 */
function ws_flow_loop_normalize($kind, $config)
{
    $config = is_array($config) ? $config : array();

    if ($kind === 'for_each_end') {
        return array(
            'for_each' => ws_flow_token($config['for_each'] ?? '', 32),
            'collect'  => ws_flow_line($config['collect'] ?? '', 200),
        );
    }

    $fields = array();

    foreach ((is_array($config['fields'] ?? null) ? $config['fields'] : array()) as $field) {
        $field = substr((string) preg_replace('/[^a-z0-9_]/', '', strtolower(ws_flow_text($field))), 0, 32);

        if (($field !== '') && !in_array($field, $fields, true)) {
            $fields[] = $field;
        }
    }

    return array(
        'list'   => ws_flow_line($config['list'] ?? '', 200),
        'path'   => substr((string) preg_replace('/[^A-Za-z0-9_.\-]/', '', ws_flow_text($config['path'] ?? '')), 0, 200),
        'fields' => array_slice($fields, 0, WS_FLOW_LOOP_FIELDS_MAX),
    );
}

/**
 * The values a For each box gives the boxes of its body: the number of
 * items, the place and the whole of the current one, and each field it
 * names. Pure.
 *
 * @param array $config ws_flow_loop_normalize()
 * @return array[] key, type, label
 */
function ws_flow_loop_outputs($config)
{
    $out = array(
        array('key' => 'count', 'type' => 'number', 'label' => lang('Item count')),
        array('key' => 'index', 'type' => 'number', 'label' => lang('Item number')),
        array('key' => 'item', 'type' => 'text', 'label' => lang('Item')),
    );

    foreach ((array) ($config['fields'] ?? array()) as $field) {
        $out[] = array('key' => (string) $field, 'type' => 'text', 'label' => (string) $field);
    }

    return $out;
}

/**
 * The values of a For each box that have a value only inside its loop. Pure.
 *
 * @param array $config ws_flow_loop_normalize()
 * @return string[]
 */
function ws_flow_loop_item_keys($config)
{
    return array_merge(array('index', 'item'), array_map('strval', (array) ($config['fields'] ?? array())));
}

/**
 * The kinds of box whose work inside a loop counts against
 * WS_FLOW_LOOP_EFFECTS_MAX: those that open a task, ask for an approval or
 * send an e-mail.
 *
 * @return string[]
 */
function ws_flow_loop_effect_kinds()
{
    return array('task', 'task_open', 'approval', 'email');
}

/**
 * The loops of a graph: each For each box with its End box and its body,
 * the boxes its each arrows lead into and the boxes after them up to the
 * End box (which is not in it, nor any box after it). An End box is the one naming the For each
 * box; the first such counts. Pure.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @return array for_each id => end ('' for none), each (ids its each arrows lead into), body (ids)
 */
function ws_flow_loops($graph)
{
    $kinds = array();
    $ends = array();

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        $kinds[(string) $node['id']] = (string) $node['kind'];

        if (((string) $node['kind'] === 'for_each_end') && !isset($ends[(string) ($node['config']['for_each'] ?? '')])) {
            $ends[(string) ($node['config']['for_each'] ?? '')] = (string) $node['id'];
        }
    }

    $next = array();

    foreach ((array) ($graph['edges'] ?? array()) as $edge) {
        $next[(string) $edge['from']][] = array((string) $edge['to'], (string) $edge['on']);
    }

    $out = array();

    foreach ($kinds as $id => $kind) {
        if ($kind !== 'for_each') {
            continue;
        }

        $end = $ends[$id] ?? '';
        $each = array();

        foreach ($next[$id] ?? array() as $arrow) {
            if (($arrow[1] === 'each') && !in_array($arrow[0], $each, true)) {
                $each[] = $arrow[0];
            }
        }

        // The boxes after the End box are outside the body, also where an
        // arrow from the body leads to them.
        $after = array();
        $queue = ($end !== '') ? array($end) : array();

        while (!empty($queue)) {
            foreach ($next[array_shift($queue)] ?? array() as $arrow) {
                if (!isset($after[$arrow[0]])) {
                    $after[$arrow[0]] = true;
                    $queue[] = $arrow[0];
                }
            }
        }

        $body = array();
        $queue = array();

        foreach ($each as $to) {
            if (($to !== $end) && ($to !== $id) && !isset($body[$to]) && !isset($after[$to])) {
                $body[$to] = true;
                $queue[] = $to;
            }
        }

        while (!empty($queue)) {
            foreach ($next[array_shift($queue)] ?? array() as $arrow) {
                $to = $arrow[0];

                if (($to !== $end) && ($to !== $id) && !isset($body[$to]) && !isset($after[$to])) {
                    $body[$to] = true;
                    $queue[] = $to;
                }
            }
        }

        $out[$id] = array('end' => $end, 'each' => $each, 'body' => array_keys($body));
    }

    return $out;
}

/**
 * The For each box whose body holds a box, '' for a box outside every loop.
 * Pure.
 *
 * @param array  $loops ws_flow_loops()
 * @param string $node_id
 * @return string
 */
function ws_flow_loop_of($loops, $node_id)
{
    foreach ($loops as $id => $loop) {
        if (in_array((string) $node_id, $loop['body'], true)) {
            return (string) $id;
        }
    }

    return '';
}

/**
 * What is wrong with the loops of a graph: bad_loop for an End box without
 * its For each box, a For each box without its End box or without an arrow
 * on each, a body that is empty or never reaches its End box, a loop inside
 * another (not supported), an arrow that enters a body other than from its
 * For each box or leaves it other than into its End box, an End box reached
 * from outside its body; bad_config for a list or a gathered value that is
 * not one value; bad_reference for a box outside a loop reading a box of its
 * body or an item; loop_size, a warning, for loops whose items can pass the
 * run's box limit (ws_flow_loop_size_warnings()). Pure.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @param array $nodes node id => box
 * @return array[] ws_flow_problem()
 */
function ws_flow_loop_problems($graph, $nodes)
{
    $problems = array();
    $loops = ws_flow_loops($graph);
    $name = function ($id) use ($nodes) {
        return isset($nodes[$id]) ? ws_flow_box_name($nodes[$id]) : (string) $id;
    };
    $single = function ($text) {
        return (preg_replace(WS_STEP_VALUE_PATTERN, '', trim((string) $text), -1, $count) === '') && ($count === 1);
    };

    foreach ($nodes as $id => $node) {
        if (((string) $node['kind'] === 'for_each_end') && !isset($loops[(string) $node['config']['for_each']])) {
            $problems[] = ws_flow_problem('bad_loop', (string) $id, null, lang(array('string' => 'The box {var:1} ends no loop: choose the loop it ends.', 'vars' => $name($id))));
        }

        if (((string) $node['kind'] === 'for_each_end') && isset($loops[(string) $node['config']['for_each']]) && ($loops[(string) $node['config']['for_each']]['end'] !== (string) $id)) {
            $problems[] = ws_flow_problem('bad_loop', (string) $id, null, lang(array('string' => 'The loop of {var:1} has a second End box, {var:2}.', 'vars' => array($name($node['config']['for_each']), $name($id)))));
        }

        if ((string) $node['kind'] === 'for_each') {
            if ((string) $node['config']['list'] === '') {
                $problems[] = ws_flow_problem('bad_config', (string) $id, null, lang(array('string' => 'The box {var:1} needs the list it goes through.', 'vars' => $name($id))));
            } elseif (!$single($node['config']['list'])) {
                $problems[] = ws_flow_problem('bad_config', (string) $id, null, lang(array('string' => 'The box {var:1} reads its list from one value only, such as a webhook\'s answer.', 'vars' => $name($id))));
            }

            foreach ((array) $node['config']['fields'] as $field) {
                if (in_array($field, array('count', 'index', 'item', 'text', 'outcome', 'status', 'items'), true)) {
                    $problems[] = ws_flow_problem('bad_config', (string) $id, null, lang(array('string' => 'The box {var:1} cannot give a field named {var:2}: the box has a value of that name already.', 'vars' => array($name($id), $field))));
                }
            }
        }
    }

    // Which loop's body each box is in; a box in two is a loop inside or
    // across another.
    $member = array();

    foreach ($loops as $id => $loop) {
        foreach ($loop['body'] as $box) {
            $member[$box][] = (string) $id;
        }
    }

    foreach ($loops as $id => $loop) {
        $end = $loop['end'];

        if ($end === '') {
            $problems[] = ws_flow_problem('bad_loop', (string) $id, null, lang(array('string' => 'The loop of {var:1} has no End box: add one after the boxes it repeats.', 'vars' => $name($id))));
        }

        if (empty($loop['each'])) {
            $problems[] = ws_flow_problem('bad_loop', (string) $id, null, lang(array('string' => 'The loop of {var:1} needs an arrow on each into the boxes it repeats.', 'vars' => $name($id))));
            continue;
        }

        if (empty($loop['body'])) {
            $problems[] = ws_flow_problem('bad_loop', (string) $id, null, lang(array('string' => 'The loop of {var:1} has no box in it.', 'vars' => $name($id))));
            continue;
        }

        foreach ($loop['body'] as $box) {
            $kind = (string) ($nodes[$box]['kind'] ?? '');

            if (in_array($kind, array('for_each', 'for_each_end'), true) || (count($member[$box]) > 1)) {
                $problems[] = ws_flow_problem('bad_loop', (string) $box, null, lang(array('string' => 'The box {var:1} is in the loop of {var:2} and starts, ends or is in another loop too; a loop inside or across another is not supported yet.', 'vars' => array($name($box), $name($id)))));
            }
        }

        if ($end === '') {
            continue;
        }

        $reaches = false;

        foreach ((array) $graph['edges'] as $index => $edge) {
            $from = (string) $edge['from'];
            $to = (string) $edge['to'];
            $from_in = in_array($from, $loop['body'], true);
            $to_in = in_array($to, $loop['body'], true);

            if ($from_in && ($to === $end)) {
                $reaches = true;
            } elseif ($from_in && !$to_in && ($to !== (string) $id)) {
                $problems[] = ws_flow_problem('bad_loop', $from, $index, lang(array('string' => 'An arrow leaves the loop of {var:1} from {var:2} to {var:3}; a loop is left through its End box only.', 'vars' => array($name($id), $name($from), $name($to)))));
            } elseif (!$from_in && $to_in && !(($from === (string) $id) && ($edge['on'] === 'each'))) {
                $problems[] = ws_flow_problem('bad_loop', $from, $index, lang(array('string' => 'An arrow from {var:1} leads into the loop of {var:2}; a loop is entered through its each arrow only.', 'vars' => array($name($from), $name($id)))));
            } elseif (!$from_in && ($to === $end)) {
                $problems[] = ws_flow_problem('bad_loop', $from, $index, lang(array('string' => 'Only the boxes inside the loop may lead into its End box {var:1}.', 'vars' => $name($end))));
            }
        }

        if (!$reaches) {
            $problems[] = ws_flow_problem('bad_loop', (string) $id, null, lang(array('string' => 'The loop of {var:1} never reaches its End box {var:2}.', 'vars' => array($name($id), $name($end)))));
        }

        $collect = (string) ($nodes[$end]['config']['collect'] ?? '');

        if ($collect !== '') {
            $box = (preg_match(WS_STEP_VALUE_PATTERN, $collect, $match) === 1) ? (string) $match[1] : '';

            if (!$single($collect) || !in_array($box, $loop['body'], true)) {
                $problems[] = ws_flow_problem('bad_config', $end, null, lang(array('string' => 'The End box {var:1} gathers one value of a box inside its loop, not {var:2}.', 'vars' => array($name($end), $collect))));
            }
        }
    }

    // A value of the body, or of the current item, has no meaning outside
    // its loop: the End box gives what the items gave.
    foreach ($nodes as $id => $node) {
        $own = ws_flow_loop_of($loops, $id);

        foreach (ws_flow_node_texts($node) as $text) {
            if (!preg_match_all(WS_STEP_VALUE_PATTERN, (string) $text, $found, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($found as $match) {
                $box = (string) $match[1];

                if (($box === '') || ((string) $node['kind'] === 'for_each_end')) {
                    continue;
                }

                $loop = ws_flow_loop_of($loops, $box);

                if (($loop !== '') && ($loop !== $own)) {
                    $problems[] = ws_flow_problem('bad_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads {var:2} from inside the loop of {var:3}; outside the loop, read what the items gave through its End box.', 'vars' => array($name($id), $match[0], $name($loop)))));
                } elseif (isset($loops[$box]) && ($own !== $box) && in_array((string) $match[2], ws_flow_loop_item_keys($nodes[$box]['config']), true)) {
                    $problems[] = ws_flow_problem('bad_reference', (string) $id, null, lang(array('string' => 'The box {var:1} reads {var:2}, which has a value only inside the loop of {var:3}.', 'vars' => array($name($id), $match[0], $name($box)))));
                }
            }
        }
    }

    return array_merge($problems, ws_flow_loop_size_warnings($nodes, $loops, $name));
}

/**
 * The most boxes a run of a graph can start: each box that runs once (not
 * the start, not a value box, which never runs), and each box of a loop's
 * body once per item, up to WS_FLOW_LOOP_ITEMS_MAX items. A box behind a
 * condition counts as if it ran. Pure.
 *
 * @param array $nodes node id => box
 * @param array $loops ws_flow_loops()
 * @return array total, loops (for_each id => boxes its items can start)
 */
function ws_flow_loop_box_runs($nodes, $loops)
{
    $kinds = ws_flow_kinds();
    $runs = function ($id) use ($nodes, $kinds) {
        $kind = $kinds[(string) ($nodes[$id]['kind'] ?? '')] ?? null;

        return ($kind && empty($kind['trigger']) && empty($kind['pure'])) ? 1 : 0;
    };
    $in_body = array();
    $out = array('total' => 0, 'loops' => array());

    foreach ($loops as $id => $loop) {
        $boxes = 0;

        foreach ($loop['body'] as $box) {
            if (!isset($in_body[$box])) {
                $in_body[$box] = true;
                $boxes += $runs($box);
            }
        }

        $out['loops'][(string) $id] = array('boxes' => $boxes, 'runs' => $boxes * WS_FLOW_LOOP_ITEMS_MAX);
        $out['total'] += $boxes * WS_FLOW_LOOP_ITEMS_MAX;
    }

    foreach (array_keys($nodes) as $id) {
        if (!isset($in_body[(string) $id])) {
            $out['total'] += $runs((string) $id);
        }
    }

    return $out;
}

/**
 * A warning on each loop of a graph whose runs, at the most items a loop
 * takes, can pass the run's limit of WS_FLOW_MAX_BOX_RUNS boxes. A warning,
 * not a refusal: a list is often far shorter than the cap, and the flow
 * works for it; the run otherwise learns of the limit only on its last
 * items, after the work before them is done. Pure.
 *
 * @param array    $nodes node id => box
 * @param array    $loops ws_flow_loops()
 * @param callable $name  a box's name by its id
 * @return array[] ws_flow_problem(), warning true
 */
function ws_flow_loop_size_warnings($nodes, $loops, $name)
{
    $limit = defined('WS_FLOW_MAX_BOX_RUNS') ? (int) WS_FLOW_MAX_BOX_RUNS : 200;
    $runs = ws_flow_loop_box_runs($nodes, $loops);
    $warnings = array();

    if ($runs['total'] <= $limit) {
        return $warnings;
    }

    foreach ($runs['loops'] as $id => $loop) {
        if ($loop['boxes'] > 0) {
            $warnings[] = ws_flow_problem('loop_size', (string) $id, null, lang(array(
                'string' => 'The loop of {var:1} can run {var:2} boxes ({var:3} items × {var:4} boxes in it); with the rest of the flow a run can pass its limit of {var:5} boxes, and a long list then fails on its last items.',
                'vars'   => array($name($id), $loop['runs'], WS_FLOW_LOOP_ITEMS_MAX, $loop['boxes'], $limit),
            )), true);
        }
    }

    return $warnings;
}

/**
 * The items of a list value: the JSON array the text holds, or the one at
 * path inside it (keys joined with dots, a digit taking an array's place).
 * A text that is not such a list, a longer list than WS_FLOW_LOOP_ITEMS_MAX
 * or items too large to keep fail the box with the reason; an empty list is
 * a list of no items. Pure.
 *
 * @param string|null $text
 * @param string      $path
 * @return array ok, items (the list), json (it as kept), error
 */
function ws_flow_loop_items($text, $path = '')
{
    $fail = function ($error) {
        return array('ok' => false, 'items' => array(), 'json' => '', 'error' => $error);
    };
    $value = json_decode((string) $text, true);

    foreach ((((string) $path !== '') ? explode('.', (string) $path) : array()) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $fail(lang(array('string' => 'The value has nothing at {var:1}.', 'vars' => $path)));
        }

        $value = $value[$key];
    }

    if (!is_array($value) || (!empty($value) && (array_keys($value) !== range(0, count($value) - 1)))) {
        return $fail(lang('The value the box reads is not a list.'));
    }

    if (count($value) > WS_FLOW_LOOP_ITEMS_MAX) {
        return $fail(lang(array('string' => 'The list holds {var:1} items; a loop goes through at most {var:2}.', 'vars' => array(count($value), WS_FLOW_LOOP_ITEMS_MAX))));
    }

    $json = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

    if (strlen($json) > WS_FLOW_LOOP_ITEMS_BYTES) {
        return $fail(lang(array('string' => 'The items of the list take more than {var:1} KB; they cannot be kept for the loop.', 'vars' => WS_FLOW_LOOP_ITEMS_BYTES / 1024)));
    }

    return array('ok' => true, 'items' => $value, 'json' => $json, 'error' => '');
}

/**
 * A value of an item as the boxes fill it in: a text or a number as it is,
 * true and false as 1 and 0, null as '', a list or an object as its JSON.
 * Pure.
 *
 * @param mixed $value
 * @return string
 */
function ws_flow_loop_value_text($value)
{
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    return ($value === null) ? '' : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * The values of a For each box inside its body at one pass: its place, the
 * item, and each field it names (a key the item does not have is ''). Pure.
 *
 * @param array  $config ws_flow_loop_normalize()
 * @param string $items  the items as the box kept them (JSON)
 * @param int    $pass   the item's place, from 1
 * @return array key => text
 */
function ws_flow_loop_item_values($config, $items, $pass)
{
    $list = json_decode((string) $items, true);
    $item = (is_array($list) && array_key_exists((int) $pass - 1, $list)) ? $list[(int) $pass - 1] : null;
    $out = array('index' => (string) (int) $pass, 'item' => ws_flow_loop_value_text($item));

    foreach ((array) ($config['fields'] ?? array()) as $field) {
        $out[(string) $field] = ws_flow_loop_value_text(is_array($item) ? ($item[$field] ?? null) : null);
    }

    return $out;
}

/**
 * The number of items a For each box went through, from its step's result.
 * Pure.
 *
 * @param string $result the step's result column
 * @return int
 */
function ws_flow_loop_count($result)
{
    $data = json_decode((string) $result, true)['data'] ?? null;

    return is_array($data) ? max(0, min(WS_FLOW_LOOP_ITEMS_MAX, (int) ($data['count'] ?? 0))) : 0;
}

/**
 * The boxes that come next in a run of a graph with loops, by box and pass.
 * Boxes outside the loops walk at pass 0 as ws_flow_walk() walks them, an
 * End box coming right after its For each box. The body of a For each box
 * done on each walks once per item, at the item's pass, as a graph of its
 * own whose start is the For each box: item 2 only once every box of item
 * 1 has settled, and so on. Until every item has settled the For each box
 * reads as still at work, so its End box waits; then the End box is ready.
 * A graph without loops walks exactly as ws_flow_walk(). Pure.
 *
 * @param array   $graph ws_flow_graph_normalize()
 * @param array[] $rows  node_id, pass, status, outcome, result; oldest first
 * @return array ready, skipped: lists of array(node id, pass)
 */
function ws_flow_loop_walk($graph, $rows)
{
    $loops = ws_flow_loops($graph);
    $out = array('ready' => array(), 'skipped' => array());
    $passes = array();

    foreach ((array) $rows as $row) {
        $passes[(int) ($row['pass'] ?? 0)][(string) $row['node_id']] = $row;
    }

    $outer_rows = array();
    $body = array();

    foreach ($loops as $loop) {
        $body += array_fill_keys($loop['body'], true);
    }

    foreach ($passes[0] ?? array() as $id => $row) {
        if (!isset($body[$id])) {
            $outer_rows[$id] = array('status' => (string) $row['status'], 'outcome' => (string) $row['outcome']);
        }
    }

    foreach ($loops as $id => $loop) {
        $start = $outer_rows[$id] ?? null;

        if (($start === null) || ($start['status'] !== 'done') || ($start['outcome'] !== 'each') || empty($loop['body'])) {
            continue;
        }

        $frame = ws_flow_loop_frame($graph, $id, $loop['body']);
        $count = ws_flow_loop_count($passes[0][$id]['result'] ?? '');
        $settled = true;

        for ($pass = 1; $pass <= $count; $pass++) {
            $states = array((string) $id => $start);

            foreach ($loop['body'] as $box) {
                if (isset($passes[$pass][$box])) {
                    $states[$box] = array('status' => (string) $passes[$pass][$box]['status'], 'outcome' => (string) $passes[$pass][$box]['outcome']);
                }
            }

            $walk = ws_flow_walk($frame, $states);

            foreach ($walk['ready'] as $box) {
                $out['ready'][] = array($box, $pass);
                $states[$box] = array('status' => 'ready', 'outcome' => '');
            }

            foreach ($walk['skipped'] as $box) {
                $out['skipped'][] = array($box, $pass);
                $states[$box] = array('status' => 'skipped', 'outcome' => '');
            }

            foreach ($loop['body'] as $box) {
                if (!isset($states[$box]) || !ws_flow_step_settled($states[$box]['status'])) {
                    $settled = false;
                    break 2;
                }
            }
        }

        // Its End box waits while an item is still on its way.
        if (!$settled) {
            $outer_rows[$id] = array('status' => 'running', 'outcome' => '');
        }
    }

    $walk = ws_flow_walk(ws_flow_loop_outer($graph, $loops, $body), $outer_rows);

    foreach ($walk['ready'] as $box) {
        $out['ready'][] = array($box, 0);
    }

    foreach ($walk['skipped'] as $box) {
        $out['skipped'][] = array($box, 0);
    }

    return $out;
}

/**
 * The graph one item of a loop walks: the For each box as its start and the
 * body, with the arrows between them. Pure.
 *
 * @param array    $graph
 * @param string   $id   the For each box
 * @param string[] $body
 * @return array
 */
function ws_flow_loop_frame($graph, $id, $body)
{
    $in = array_fill_keys($body, true);
    $frame = array('nodes' => array(), 'edges' => array());

    foreach ((array) $graph['nodes'] as $node) {
        if (isset($in[(string) $node['id']]) || ((string) $node['id'] === (string) $id)) {
            $frame['nodes'][] = $node;
        }
    }

    foreach ((array) $graph['edges'] as $edge) {
        if (isset($in[(string) $edge['to']]) && (isset($in[(string) $edge['from']]) || ((string) $edge['from'] === (string) $id))) {
            $frame['edges'][] = $edge;
        }
    }

    return $frame;
}

/**
 * The graph the boxes outside the loops walk: the bodies taken out, each End
 * box coming after its For each box on each. Pure.
 *
 * @param array $graph
 * @param array $loops ws_flow_loops()
 * @param array $body  node id => true for every box of a body
 * @return array
 */
function ws_flow_loop_outer($graph, $loops, $body)
{
    if (empty($loops)) {
        return $graph;
    }

    $outer = array('nodes' => array(), 'edges' => array());

    foreach ((array) $graph['nodes'] as $node) {
        if (!isset($body[(string) $node['id']])) {
            $outer['nodes'][] = $node;
        }
    }

    foreach ((array) $graph['edges'] as $edge) {
        if (!isset($body[(string) $edge['from']]) && !isset($body[(string) $edge['to']])) {
            $outer['edges'][] = $edge;
        }
    }

    foreach ($loops as $id => $loop) {
        if ($loop['end'] !== '') {
            $outer['edges'][] = array('from' => (string) $id, 'to' => $loop['end'], 'on' => 'each');
        }
    }

    return $outer;
}

/**
 * How a run of a graph with loops stands, as ws_flow_run_state() says it: a
 * box of a body that failed fails its item, not the run (its End box counts
 * it), so only a failed box outside the loops fails the run. Pure.
 *
 * @param array   $graph
 * @param array[] $rows node_id, pass, status, outcome, result
 * @return string running | waiting | done | failed
 */
function ws_flow_loop_run_state($graph, $rows)
{
    $waiting = false;
    $failed = false;

    foreach ((array) $rows as $row) {
        $status = (string) $row['status'];

        if (($status === 'ready') || ($status === 'running')) {
            return 'running';
        }

        $waiting = $waiting || ($status === 'waiting');
        $failed = $failed || (($status === 'failed') && ((int) ($row['pass'] ?? 0) === 0));
    }

    $walk = ws_flow_loop_walk($graph, $rows);

    if (!empty($walk['ready']) || !empty($walk['skipped'])) {
        return 'running';
    }

    if ($waiting) {
        return 'waiting';
    }

    return $failed ? 'failed' : 'done';
}

/**
 * What an End box gives: the items gone through, those whose boxes all
 * ended well (done) and those with a box that failed (failed), and list,
 * the JSON array of the gathered value of each item (null where the box
 * gave none). The list keeps whole items up to WS_FLOW_LOOP_LIST_BYTES; kept
 * says how many fit. Pure.
 *
 * @param array    $loop    ws_flow_loops() entry
 * @param int      $count   the items gone through
 * @param array[]  $rows    the run's steps: node_id, pass, status, outcome, result
 * @param string   $collect the gathered value ('' for none): {{node.<id>.<field>}}
 * @return array count, done, failed, list, kept
 */
function ws_flow_loop_end_outputs($loop, $count, $rows, $collect)
{
    $body = array_fill_keys($loop['body'], true);
    $failed = array();
    $values = array();
    $box = '';
    $field = '';

    if (preg_match(WS_STEP_VALUE_PATTERN, (string) $collect, $match) === 1) {
        $box = (string) $match[1];
        $field = (string) $match[2];
    }

    foreach ((array) $rows as $row) {
        $pass = (int) ($row['pass'] ?? 0);

        if (($pass < 1) || ($pass > $count) || !isset($body[(string) $row['node_id']])) {
            continue;
        }

        if ((string) $row['status'] === 'failed') {
            $failed[$pass] = true;
        }

        if (((string) $row['node_id'] === $box) && ($field !== '')) {
            $result = json_decode((string) ($row['result'] ?? ''), true);
            $data = is_array($result['data'] ?? null) ? $result['data'] : array();

            if (in_array($field, array('text', 'outcome', 'status'), true)) {
                $values[$pass] = ($field === 'text') ? ws_flow_result_text($row['result'] ?? '') : (string) $row[$field];
            } elseif (isset($data[$field]) && is_scalar($data[$field])) {
                $values[$pass] = $data[$field];
            }
        }
    }

    $list = array();
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;
    $json = '[]';

    if ($box !== '') {
        for ($pass = 1; $pass <= $count; $pass++) {
            $list[] = $values[$pass] ?? null;
            $next = (string) json_encode($list, $flags);

            if (strlen($next) > WS_FLOW_LOOP_LIST_BYTES) {
                array_pop($list);
                break;
            }

            $json = $next;
        }
    }

    return array(
        'count'  => (int) $count,
        'done'   => (int) $count - count($failed),
        'failed' => count($failed),
        'list'   => $json,
        'kept'   => count($list),
    );
}

/**
 * Why a box inside a loop may not open a task, ask for an approval or send
 * an e-mail: the run's boxes inside loops did so WS_FLOW_LOOP_EFFECTS_MAX
 * times already; '' when it may. Pure.
 *
 * @param int $opened how many such boxes inside loops began their work
 * @return string
 */
function ws_flow_loop_effects_refusal($opened)
{
    if ((int) $opened < WS_FLOW_LOOP_EFFECTS_MAX) {
        return '';
    }

    return lang(array('string' => 'The boxes inside loops opened {var:1} tasks, approvals or e-mails in this run, its limit; this one was not opened.', 'vars' => WS_FLOW_LOOP_EFFECTS_MAX));
}

/* ---------------------------------------------------------------------------
   Carrying out the two boxes
   --------------------------------------------------------------------------- */

/**
 * A For each box: reads its list, with the owner's rights now, from the
 * values the run has so far, and keeps its items for its body. A value that
 * is no list, or a list over WS_FLOW_LOOP_ITEMS_MAX items, fails the box
 * with the reason (on failed when an arrow leaves on it); nothing is cut.
 *
 * @param array $run
 * @param array $step  ws_flow_run_steps row, running
 * @param array $node  the box
 * @param array $owner the flow's owner, on the staff
 */
function ws_flow_loop_start_run($run, $step, $node, $owner)
{
    $graph = ws_flow_run_graph($run);
    $values = ws_step_run_values($run, $graph, $owner, ws_flow_run_context($run), $step);
    $text = null;

    if (preg_match(WS_STEP_VALUE_PATTERN, (string) $node['config']['list'], $match) === 1) {
        $text = $values[ws_step_value_box($match)][$match[2]] ?? null;
    }

    $list = ws_flow_loop_items($text, (string) $node['config']['path']);

    if (!$list['ok']) {
        $routed = ws_flow_outcome_routed($graph, $node['id'], 'failed', false);
        ws_flow_step_settle($step, 'running', $routed ? 'done' : 'failed', 'failed', $list['error']);
        return;
    }

    $count = count($list['items']);

    ws_flow_step_settle($step, 'running', 'done', 'each', lang(array('string' => 'The list holds {var:1} items.', 'vars' => $count)),
        array('count' => $count, 'items' => $list['json']));
}

/**
 * An End box: counts what the items of its loop gave and gathers its value
 * of each into the list.
 *
 * @param array $run
 * @param array $step ws_flow_run_steps row, running
 * @param array $node the box
 */
function ws_flow_loop_end_run($run, $step, $node)
{
    $graph = ws_flow_run_graph($run);
    $loops = ws_flow_loops($graph);
    $start = (string) $node['config']['for_each'];
    $rows = ws_flow_run_steps($run['id']);
    $count = 0;

    foreach ($rows as $row) {
        if (((string) $row['node_id'] === $start) && ((int) $row['pass'] === 0)) {
            $count = ws_flow_loop_count($row['result']);
        }
    }

    $out = ws_flow_loop_end_outputs($loops[$start] ?? array('body' => array()), $count, $rows, (string) $node['config']['collect']);
    $text = lang(array('string' => 'Went through {var:1} items: {var:2} done, {var:3} failed.', 'vars' => array($out['count'], $out['done'], $out['failed'])));

    if (((string) $node['config']['collect'] !== '') && ($out['kept'] < $out['count'])) {
        $text .= ' ' . lang(array('string' => 'The list keeps the first {var:1} values: the rest pass {var:2} KB.', 'vars' => array($out['kept'], WS_FLOW_LOOP_LIST_BYTES / 1024)));
    }

    unset($out['kept']);

    ws_flow_step_settle($step, 'running', 'done', 'done', $text, $out);
}

/**
 * Whether a box inside a loop may still open a task, ask for an approval
 * or send an e-mail in this run: the boxes inside loops that began such
 * work (their effect written), this one left out, under
 * WS_FLOW_LOOP_EFFECTS_MAX. '' when it may, else why not.
 *
 * @param array $run
 * @param array $step ws_flow_run_steps row
 * @param array $node the box
 * @return string
 */
function ws_flow_loop_effects_check($run, $step, $node)
{
    if (((int) ($step['pass'] ?? 0) === 0) || !in_array((string) $node['kind'], ws_flow_loop_effect_kinds(), true)) {
        return '';
    }

    $ids = array();

    foreach ((array) ws_flow_run_graph($run)['nodes'] as $other) {
        if (in_array((string) $other['kind'], ws_flow_loop_effect_kinds(), true)) {
            $ids[] = "'" . e((string) $other['id']) . "'";
        }
    }

    return ws_flow_loop_effects_refusal((int) db_value("SELECT COUNT(*) FROM ws_flow_run_steps
        WHERE run_id = '" . (int) $run['id'] . "' AND pass > 0 AND id <> '" . (int) $step['id'] . "' AND effect <> ''
        AND node_id IN (" . implode(',', $ids) . ")"));
}
