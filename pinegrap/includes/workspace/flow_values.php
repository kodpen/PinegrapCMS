<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the value boxes of a flow: boxes with no arrows that only give
 * values to the boxes reading them through data threads ({{node.<id>.<key>}}).
 * They have no side effect and no run row: what they give is read when a
 * box that reads it runs. The constants box holds rows written by hand
 * (a name, a type, a value); the derived value box works one value out of
 * one or two others by a rule of a short list (ws_flow_derived_rules()),
 * read as numbers, dates or text, never run as code.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The rows a constants box holds at most, and the length of a value.
define('WS_FLOW_CONSTANTS_MAX', 20);
define('WS_FLOW_CONSTANT_LENGTH', 500);

// The longest input of a derived value box, and the most days it adds.
define('WS_FLOW_DERIVED_LENGTH', 500);
define('WS_FLOW_DERIVED_DAYS', 3650);

// The longest value a derived value box gives, and the longest input once
// filled, in characters: an input may cite a value many times, so without a
// limit a chain of joins grows the value many-fold per box.
define('WS_FLOW_DERIVED_VALUE_LENGTH', 4000);

/**
 * The types a constant takes: those of the values the boxes give
 * (ws_step_output_types()).
 *
 * @return string[]
 */
function ws_flow_constant_types()
{
    return array('text', 'number');
}

/**
 * A constants box's rows in their kept shape: the name cut to a key of small
 * letters, digits and _ (as {{node.<id>.<key>}} reads it), the type one of
 * ws_flow_constant_types() (else text), the value one line. Rows past
 * WS_FLOW_CONSTANTS_MAX are dropped; nothing else is, so the check can say
 * what is wrong. Pure.
 *
 * @param array $config
 * @return array rows: key, type, value
 */
function ws_flow_constants_normalize($config)
{
    $rows = array();

    foreach ((is_array($config['rows'] ?? null) ? $config['rows'] : array()) as $row) {
        if (!is_array($row)) {
            continue;
        }

        $type = ws_flow_text($row['type'] ?? '');

        $rows[] = array(
            'key'   => substr((string) preg_replace('/[^a-z0-9_]/', '', strtolower(ws_flow_text($row['key'] ?? ''))), 0, 32),
            'type'  => in_array($type, ws_flow_constant_types(), true) ? $type : 'text',
            'value' => ws_flow_line(is_scalar($row['value'] ?? '') ? (string) ($row['value'] ?? '') : '', WS_FLOW_CONSTANT_LENGTH),
        );

        if (count($rows) >= WS_FLOW_CONSTANTS_MAX) {
            break;
        }
    }

    return array('rows' => $rows);
}

/**
 * What a constants box gives: one value per row with a name, as a box
 * declares its outputs (key, type, label). Pure.
 *
 * @param array $config ws_flow_constants_normalize()
 * @return array[] key, type, label
 */
function ws_flow_constants_outputs($config)
{
    $out = array();

    foreach ((array) ($config['rows'] ?? array()) as $row) {
        if (preg_match('/^[a-z_][a-z0-9_]{0,31}$/', (string) $row['key']) && !isset($out[$row['key']])) {
            $out[$row['key']] = array('key' => $row['key'], 'type' => $row['type'], 'label' => $row['key']);
        }
    }

    return array_values($out);
}

/**
 * What is wrong with a constants box's rows: none, a name that is not a key,
 * a name twice, a number that is not one. Pure.
 *
 * @param array  $config ws_flow_constants_normalize()
 * @param string $name   the box's name
 * @return string[]
 */
function ws_flow_constants_problems($config, $name)
{
    $rows = (array) ($config['rows'] ?? array());
    $seen = array();
    $out = array();

    if (empty($rows)) {
        $out[] = lang(array('string' => 'The box {var:1} holds no value yet.', 'vars' => $name));
    }

    foreach ($rows as $row) {
        $key = (string) $row['key'];

        if (!preg_match('/^[a-z_][a-z0-9_]{0,31}$/', $key)) {
            $out[] = lang(array('string' => 'A value of the box {var:1} needs a name of small letters, digits and _ that does not start with a digit.', 'vars' => $name));
        } elseif (isset($seen[$key])) {
            $out[] = lang(array('string' => 'The box {var:1} holds two values named {var:2}.', 'vars' => array($name, $key)));
        }

        $seen[$key] = true;

        if (($row['type'] === 'number') && !is_numeric($row['value'])) {
            $out[] = lang(array('string' => 'The value {var:2} of the box {var:1} is not a number.', 'vars' => array($name, ($key !== '') ? $key : '?')));
        }
    }

    return array_values(array_unique($out));
}

/**
 * The rules a derived value box works its value out by, with the type of
 * the value each gives: compare (1 when it holds, else 0), join (the two
 * inputs with a space between), add, subtract, round, date format, add
 * days. Nothing that reads the database: the box is pure.
 *
 * @return array rule => type
 */
function ws_flow_derived_rules()
{
    return array(
        'compare'     => 'number',
        'join'        => 'text',
        'add'         => 'number',
        'subtract'    => 'number',
        'round'       => 'number',
        'date_format' => 'text',
        'add_days'    => 'text',
    );
}

/**
 * The rules that read a second input (b).
 *
 * @return string[]
 */
function ws_flow_derived_two_inputs()
{
    return array('compare', 'join', 'add', 'subtract', 'add_days');
}

/**
 * The comparisons the compare rule takes, as a value condition's
 * (ws_scheduled_operators()).
 *
 * @return string[]
 */
function ws_flow_derived_operators()
{
    return array('eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains');
}

/**
 * The shapes the date format rule writes a date in, as date() reads them.
 *
 * @return string[]
 */
function ws_flow_derived_formats()
{
    return array('Y-m-d', 'd.m.Y', 'd/m/Y', 'm/d/Y', 'Y-m-d H:i', 'd.m.Y H:i');
}

/**
 * The derived value box's lists as the canvas reads them: each rule with its
 * label, type and whether it reads a second value; the comparisons; the
 * date shapes with an example.
 *
 * @return array rules, operators, formats
 */
function ws_flow_derived_lists()
{
    $labels = array(
        'compare'     => lang('Compare two values'),
        'join'        => lang('Join two texts'),
        'add'         => lang('Add two numbers'),
        'subtract'    => lang('Subtract'),
        'round'       => lang('Round a number'),
        'date_format' => lang('Write a date in another shape'),
        'add_days'    => lang('Add days to a date'),
    );
    $rules = array();

    foreach (ws_flow_derived_rules() as $key => $type) {
        $rules[] = array('key' => $key, 'label' => $labels[$key], 'type' => $type, 'two' => in_array($key, ws_flow_derived_two_inputs(), true));
    }

    $formats = array();

    foreach (ws_flow_derived_formats() as $format) {
        $formats[] = array('key' => $format, 'label' => gmdate($format, gmmktime(14, 30, 0, 12, 31, 2026)));
    }

    return array('rules' => $rules, 'operators' => ws_flow_derived_operators(), 'formats' => $formats);
}

/**
 * A derived value box's set-up in its kept shape: a rule of
 * ws_flow_derived_rules() (else join), its inputs a and b one line each
 * (they may hold {{node.*}} and {{trigger.*}}), the comparison, the places
 * to round to (0-6) and the date shape. Pure.
 *
 * @param array $config
 * @return array rule, a, b, op, places, format
 */
function ws_flow_derived_normalize($config)
{
    $rule = ws_flow_text($config['rule'] ?? '');
    $op = ws_flow_text($config['op'] ?? '');
    $format = ws_flow_text($config['format'] ?? '');
    $places = $config['places'] ?? 0;
    $input = function ($value) {
        return ws_flow_line(is_scalar($value) ? (string) $value : '', WS_FLOW_DERIVED_LENGTH);
    };

    return array(
        'rule'   => isset(ws_flow_derived_rules()[$rule]) ? $rule : 'join',
        'a'      => $input($config['a'] ?? ''),
        'b'      => $input($config['b'] ?? ''),
        'op'     => in_array($op, ws_flow_derived_operators(), true) ? $op : 'eq',
        'places' => (is_bool($places) || !is_numeric($places)) ? 0 : max(0, min(6, (int) $places)),
        'format' => in_array($format, ws_flow_derived_formats(), true) ? $format : 'Y-m-d',
    );
}

/**
 * What a derived value box gives: one value, value, of its rule's type.
 * Pure.
 *
 * @param array $config ws_flow_derived_normalize()
 * @return array[] key, type, label
 */
function ws_flow_derived_outputs($config)
{
    return array(array('key' => 'value', 'type' => ws_flow_derived_rules()[$config['rule'] ?? ''] ?? 'text', 'label' => 'value'));
}

/**
 * The value boxes a derived value box reads, through its inputs. Pure.
 *
 * @param array $config ws_flow_derived_normalize()
 * @return string[] node ids
 */
function ws_flow_derived_reads($config)
{
    $ids = array();

    foreach (array($config['a'] ?? '', $config['b'] ?? '') as $text) {
        if (preg_match_all(WS_STEP_VALUE_PATTERN, (string) $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if ((string) $match[1] !== '') {
                    $ids[$match[1]] = $match[1];
                }
            }
        }
    }

    return array_values($ids);
}

/**
 * What is wrong with a derived value box: an input its rule needs left
 * empty, a number of days that is not a whole number. Whether it reads
 * itself through other value boxes is the graph's (ws_flow_derived_loops()).
 * Pure.
 *
 * @param array  $config ws_flow_derived_normalize()
 * @param string $name   the box's name
 * @return string[]
 */
function ws_flow_derived_problems($config, $name)
{
    $out = array();

    if ($config['a'] === '') {
        $out[] = lang(array('string' => 'The box {var:1} needs the value it works from.', 'vars' => $name));
    }

    if (in_array($config['rule'], ws_flow_derived_two_inputs(), true) && ($config['b'] === '')) {
        $out[] = lang(array('string' => 'The box {var:1} needs its second value.', 'vars' => $name));
    }

    if (($config['rule'] === 'add_days') && (strpos($config['b'], '{{') === false) && ($config['b'] !== '') && !preg_match('/^-?\d{1,4}$/', $config['b'])) {
        $out[] = lang(array('string' => 'The box {var:1} adds days: its second value must be a whole number.', 'vars' => $name));
    }

    return $out;
}

/**
 * The derived value boxes of a graph that read their own value, through
 * themselves or other derived value boxes: such a value cannot be worked
 * out. Pure.
 *
 * @param array $nodes node id => box
 * @return string[] node ids
 */
function ws_flow_derived_loops($nodes)
{
    $reads = array();

    foreach ($nodes as $id => $node) {
        if (($node['kind'] ?? '') === 'value') {
            $reads[(string) $id] = ws_flow_derived_reads((array) $node['config']);
        }
    }

    $out = array();

    foreach (array_keys($reads) as $id) {
        $seen = array();
        $queue = $reads[$id];

        while (!empty($queue)) {
            $next = (string) array_shift($queue);

            if ($next === (string) $id) {
                $out[] = (string) $id;
                break;
            }

            if (isset($reads[$next]) && !isset($seen[$next])) {
                $seen[$next] = true;
                $queue = array_merge($queue, $reads[$next]);
            }
        }
    }

    return $out;
}

/**
 * The boxes the derived value boxes $ids read, at any depth through other
 * derived value boxes, each with the derived box read first that leads to
 * it: a box reading a derived value reads those too, so they must come
 * before it. The derived boxes themselves are not listed. Pure.
 *
 * @param array    $nodes node id => box
 * @param string[] $ids   the boxes a reader reads
 * @return array node id => the id of the derived box the reader reads
 */
function ws_flow_derived_sources($nodes, $ids)
{
    $out = array();
    $seen = array();
    $queue = array();

    foreach ($ids as $id) {
        if ((($nodes[$id]['kind'] ?? '') === 'value') && !isset($seen[$id])) {
            $seen[$id] = true;
            $queue[] = array((string) $id, (string) $id);
        }
    }

    while (!empty($queue)) {
        list($id, $via) = array_shift($queue);

        foreach (ws_flow_derived_reads((array) $nodes[$id]['config']) as $read) {
            if (!isset($nodes[$read])) {
                continue;
            }

            if ($nodes[$read]['kind'] === 'value') {
                if (!isset($seen[$read])) {
                    $seen[$read] = true;
                    $queue[] = array($read, $via);
                }
            } elseif (!isset($out[$read])) {
                $out[$read] = $via;
            }
        }
    }

    return $out;
}

/**
 * A number as a derived value writes it: a whole number without a point,
 * else up to six places without trailing zeros. Pure.
 *
 * @param float|int $number
 * @return string
 */
function ws_flow_derived_number($number)
{
    $text = rtrim(rtrim(sprintf('%.6F', (float) $number), '0'), '.');

    return (($text === '-0') || ($text === '')) ? '0' : $text;
}

/**
 * A date a derived value reads: Y-m-d, Y-m-d H:i, Y-m-d H:i:s, or a Unix
 * time (written in the site's time zone). The time of day only when the
 * text gives one. Pure.
 *
 * @param string $text
 * @return array|null y, m, d, h, i, time (whether it gave a time); null when it is no date
 */
function ws_flow_derived_date($text)
{
    $text = trim((string) $text);

    if (preg_match('/^\d{9,11}$/', $text)) {
        $text = date('Y-m-d H:i', (int) $text);
    }

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/', $text, $match)
        || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])
        || ((int) ($match[4] ?? 0) > 23) || ((int) ($match[5] ?? 0) > 59)) {
        return null;
    }

    return array(
        'y'    => (int) $match[1],
        'm'    => (int) $match[2],
        'd'    => (int) $match[3],
        'h'    => (int) ($match[4] ?? 0),
        'i'    => (int) ($match[5] ?? 0),
        'time' => isset($match[4]) && ($match[4] !== ''),
    );
}

/**
 * The value of a derived value box from its inputs as filled: empty when an
 * input is not what the rule reads (a word to add, a date that is none).
 * Pure.
 *
 * @param array  $config ws_flow_derived_normalize()
 * @param string $a      the first input, filled
 * @param string $b      the second input, filled
 * @return string
 */
function ws_flow_derived_value($config, $a, $b)
{
    $a = trim(mb_substr((string) $a, 0, WS_FLOW_DERIVED_VALUE_LENGTH));
    $b = trim(mb_substr((string) $b, 0, WS_FLOW_DERIVED_VALUE_LENGTH));
    $numbers = is_numeric($a) && is_numeric($b);

    switch ($config['rule']) {
        case 'compare':
            $op = (string) $config['op'];

            if (in_array($op, array('gt', 'gte', 'lt', 'lte'), true) && !$numbers) {
                return '0';
            }

            // Two numbers are equal by their value (5 is 5.0).
            if (in_array($op, array('eq', 'neq'), true) && $numbers) {
                return ((((float) $a) == ((float) $b)) === ($op === 'eq')) ? '1' : '0';
            }

            return ws_scheduled_compare($a, $op, $b, 'text') ? '1' : '0';

        case 'join':
            return mb_substr(trim($a . ' ' . $b), 0, WS_FLOW_DERIVED_VALUE_LENGTH);

        case 'add':
        case 'subtract':
            return $numbers ? ws_flow_derived_number(($config['rule'] === 'add') ? ((float) $a + (float) $b) : ((float) $a - (float) $b)) : '';

        case 'round':
            return is_numeric($a) ? ws_flow_derived_number(round((float) $a, (int) $config['places'])) : '';

        case 'date_format':
        case 'add_days':
            $date = ws_flow_derived_date($a);

            if (($date === null) || (($config['rule'] === 'add_days') && !preg_match('/^-?\d{1,4}$/', $b))) {
                return '';
            }

            $days = ($config['rule'] === 'add_days') ? max(-WS_FLOW_DERIVED_DAYS, min(WS_FLOW_DERIVED_DAYS, (int) $b)) : 0;
            $time = gmmktime($date['h'], $date['i'], 0, $date['m'], $date['d'] + $days, $date['y']);

            if ($config['rule'] === 'add_days') {
                return gmdate($date['time'] ? 'Y-m-d H:i' : 'Y-m-d', $time);
            }

            return gmdate((string) $config['format'], $time);
    }

    return '';
}

/**
 * A derived value box's input with the run's values in it, as they are:
 * the box that reads the value makes it safe where it lands
 * (ws_step_fill_value()). Cut to WS_FLOW_DERIVED_VALUE_LENGTH characters:
 * each value placed takes only the room left, so the text never grows past
 * the limit and the written input's own length. Pure.
 *
 * @param string $text
 * @param array  $values node id => key => value, the trigger's under WS_STEP_TRIGGER
 * @return string
 */
function ws_flow_derived_fill($text, $values)
{
    $room = WS_FLOW_DERIVED_VALUE_LENGTH;

    $text = (string) preg_replace_callback(WS_STEP_VALUE_PATTERN, function ($match) use ($values, &$room) {
        $value = mb_substr((string) ($values[ws_step_value_box($match)][$match[2]] ?? ''), 0, max(0, $room));
        $room -= mb_strlen($value);

        return $value;
    }, (string) $text);

    return mb_substr($text, 0, WS_FLOW_DERIVED_VALUE_LENGTH);
}

/**
 * The values the value boxes of a graph give, by box, as
 * ws_step_node_values() gives a settled box's: read when a box that reads
 * them runs, never a run row. A derived value box works its value out from
 * the run's values so far ($values: the settled boxes' and what started the
 * run), a value box's and another derived box's; one that reads itself
 * gives an empty value. Pure.
 *
 * @param array $graph  ws_flow_graph_normalize()
 * @param array $values ws_step_node_values() of the run, the trigger's under WS_STEP_TRIGGER
 * @return array node id => key => value (the value boxes only)
 */
function ws_flow_pure_values($graph, $values = array())
{
    $out = array();
    $derived = array();

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if (($node['kind'] ?? '') === 'value') {
            $derived[(string) $node['id']] = ws_flow_derived_normalize((array) ($node['config'] ?? array()));
        }

        if (($node['kind'] ?? '') !== 'constants') {
            continue;
        }

        $kept = array();

        foreach ((array) ($node['config']['rows'] ?? array()) as $row) {
            if (preg_match('/^[a-z_][a-z0-9_]{0,31}$/', (string) ($row['key'] ?? '')) && !isset($kept[$row['key']])) {
                $kept[$row['key']] = (string) $row['value'];
            }
        }

        $out[(string) $node['id']] = $kept;
    }

    // Each pass works out the derived boxes whose derived inputs are known;
    // those left when a pass adds none read themselves.
    while (!empty($derived)) {
        $added = false;

        foreach ($derived as $id => $config) {
            if (!empty(array_intersect(ws_flow_derived_reads($config), array_keys($derived)))) {
                continue;
            }

            $known = $out + (array) $values;
            $out[$id] = array('value' => ws_flow_derived_value($config, ws_flow_derived_fill($config['a'], $known), ws_flow_derived_fill($config['b'], $known)));
            unset($derived[$id]);
            $added = true;
        }

        if (!$added) {
            foreach (array_keys($derived) as $id) {
                $known = $out + (array) $values;
            $out[$id] = array('value' => '');
            }

            break;
        }
    }

    return $out;
}
