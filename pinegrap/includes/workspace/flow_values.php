<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the value boxes of a flow: boxes with no arrows that only give
 * values to the boxes reading them through data threads ({{node.<id>.<key>}}).
 * They have no side effect and no run row: what they give is read when a
 * box that reads it runs. The constants box holds rows written by hand
 * (a name, a type, a value).
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
 * The values the value boxes of a graph give, by box, as
 * ws_step_node_values() gives a settled box's: read when a box that reads
 * them runs, never a run row. Pure.
 *
 * @param array $graph ws_flow_graph_normalize()
 * @return array node id => key => value
 */
function ws_flow_pure_values($graph)
{
    $out = array();

    foreach ((array) ($graph['nodes'] ?? array()) as $node) {
        if (($node['kind'] ?? '') !== 'constants') {
            continue;
        }

        $values = array();

        foreach ((array) ($node['config']['rows'] ?? array()) as $row) {
            if (preg_match('/^[a-z_][a-z0-9_]{0,31}$/', (string) ($row['key'] ?? '')) && !isset($values[$row['key']])) {
                $values[$row['key']] = (string) $row['value'];
            }
        }

        $out[(string) $node['id']] = $values;
    }

    return $out;
}
