<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - why a flow box failed when a value it read came empty. As a
 * box fails, the runner keeps in its result (data.empty_values) which
 * values of its texts were filled empty without a "When empty, write"
 * text: the box each came from, the value's name and, when that box is a
 * row read that found nothing, that box's own sentence. The run card, the
 * bell line and the run view add the sentence "The value “Name” from the
 * box “Read a row” was empty." to the failed box's own; the canvas offers
 * the value's fallback and the box that gave it. A box that failed for
 * another reason keeps no list and shows nothing new.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How many empty values one failed box keeps and names.
define('WS_FLOW_REASON_VALUES', 3);

/**
 * The {{node.<id>.<field>}} and {{trigger.<field>}} of a box's texts that
 * the run filled empty: the run gives none, or an empty one, and the
 * reference has no "When empty, write" text. Each once, in the order they
 * first appear, at most WS_FLOW_REASON_VALUES. Pure.
 *
 * @param string[] $texts  the box's texts as written, not filled
 * @param array    $values ws_step_node_values(), the trigger's under WS_STEP_TRIGGER
 * @return array[] box (the id, '' for what started the run), key
 */
function ws_flow_empty_values($texts, $values)
{
    $out = array();
    $seen = array();

    foreach ((array) $texts as $text) {
        if (!is_string($text) || !preg_match_all(WS_STEP_VALUE_PATTERN, $text, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $value = $values[ws_step_value_box($match)][$match[2]] ?? '';
            $bare = (string) $match[1] . '.' . (string) $match[2];

            if ((is_scalar($value) && ((string) $value !== '')) || (ws_step_value_fallback($match) !== '') || isset($seen[$bare])) {
                continue;
            }

            $seen[$bare] = true;
            $out[] = array('box' => (string) $match[1], 'key' => (string) $match[2]);

            if (count($out) >= WS_FLOW_REASON_VALUES) {
                return $out;
            }
        }
    }

    return $out;
}

/**
 * The texts of a step box a run fills (ws_step_kinds() texts), as written.
 * Pure.
 *
 * @param string $kind
 * @param array  $action ws_step_check() action
 * @return string[]
 */
function ws_flow_step_texts($kind, $action)
{
    $out = array();

    foreach ((ws_step_kinds()[(string) $kind]['texts'] ?? array()) as $key) {
        if (isset($action[$key]) && is_string($action[$key])) {
            $out[] = $action[$key];
        }
    }

    return $out;
}

/**
 * The empty values named for the failure: the box that gives each (its id,
 * the start box's for what started the run, '' for a box the graph lacks)
 * and its name, the value's name as the value picker names it, and the
 * giving box's own sentence when it is a row read that found nothing.
 *
 * @param array[]  $empty  ws_flow_empty_values()
 * @param array    $nodes  boxes of the run's graph
 * @param array    $values ws_step_node_values() the box read
 * @param callable $label  (box, field) => the value's name, '' for none
 * @return array[] node_id, key, box, name, said
 */
function ws_flow_empty_entries($empty, $nodes, $values, $label)
{
    $out = array();
    $kinds = ws_flow_kinds();

    foreach ((array) $empty as $item) {
        $giver = ws_flow_value_giver((string) $item['box'], (array) $nodes, $kinds);
        $given = $values[ws_step_value_box(array(1 => (string) $item['box']))] ?? array();
        $name = $giver ? (string) $label($giver, (string) $item['key']) : '';
        $nothing = $giver && ((string) $giver['kind'] === 'row_get') && ((string) ($given['found'] ?? '') === '0');

        $out[] = array(
            'node_id' => $giver ? (string) $giver['id'] : '',
            'key'     => (string) $item['key'],
            'box'     => $giver ? ws_flow_box_name($giver) : (string) $item['box'],
            'name'    => ($name !== '') ? $name : (string) $item['key'],
            'said'    => $nothing ? (string) ($given['text'] ?? '') : '',
        );
    }

    return $out;
}

/**
 * The sentences a failure adds for its empty values: "The value “Name”
 * from the box “Box” was empty.", each followed by a row read's own
 * sentence for whoever may change the flow (it names the table, which
 * ws_step_result_shown() keeps from the others). Pure but for lang().
 *
 * @param array[] $entries ws_flow_empty_entries()
 * @param bool    $edit    the reader may change the flow
 * @return string '' for none
 */
function ws_flow_reason_text($entries, $edit)
{
    $out = array();

    foreach ((array) $entries as $entry) {
        if (!is_array($entry) || !isset($entry['name'], $entry['box'])) {
            continue;
        }

        $out[] = lang(array('string' => 'The value “{var:1}” from the box “{var:2}” was empty.', 'vars' => array((string) $entry['name'], (string) $entry['box'])));

        if ($edit && ((string) ($entry['said'] ?? '') !== '')) {
            $out[] = (string) $entry['said'];
        }
    }

    return implode(' ', $out);
}

/**
 * The empty values a box's stored result keeps (ws_flow_result_pack()
 * data.empty_values). Pure.
 *
 * @param string $stored ws_flow_run_steps.result
 * @return array[] ws_flow_empty_entries()
 */
function ws_flow_result_empty($stored)
{
    $result = is_string($stored) ? json_decode($stored, true) : null;
    $list = $result['data']['empty_values'] ?? null;

    return is_array($list) ? array_values(array_filter($list, 'is_array')) : array();
}

/**
 * A failed box's sentence with the sentences of its empty values after it;
 * a box that kept none, or did not fail, keeps its own. Pure but for
 * lang().
 *
 * @param string $text   the sentence shown (ws_step_result_shown())
 * @param string $status the box's state
 * @param string $stored ws_flow_run_steps.result
 * @param bool   $edit   the reader may change the flow
 * @return string
 */
function ws_flow_text_with_reason($text, $status, $stored, $edit)
{
    $reason = ((string) $status === 'failed') ? ws_flow_reason_text(ws_flow_result_empty($stored), $edit) : '';

    if ($reason === '') {
        return (string) $text;
    }

    return (trim((string) $text) === '') ? $reason : rtrim((string) $text) . ' ' . $reason;
}

/**
 * The empty values of a failed box as the canvas offers them: the box that
 * gave each, the value's key and both names. Pure.
 *
 * @param string $status the box's state
 * @param string $stored ws_flow_run_steps.result
 * @return array[] node_id, key, box, name
 */
function ws_flow_empty_present($status, $stored)
{
    if ((string) $status !== 'failed') {
        return array();
    }

    return array_map(function ($entry) {
        return array(
            'node_id' => (string) ($entry['node_id'] ?? ''),
            'key'     => (string) ($entry['key'] ?? ''),
            'box'     => (string) ($entry['box'] ?? ''),
            'name'    => (string) ($entry['name'] ?? ''),
        );
    }, ws_flow_result_empty($stored));
}
