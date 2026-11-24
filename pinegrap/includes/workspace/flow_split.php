<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Split box of a flow (kind split): two to five arms, each a
 * name and a share in whole percent adding up to 100, each arm an outcome
 * with its own arrow. A run takes one arm at random by the shares and keeps
 * it as the step's outcome, so the run view, a walk healed after a lost
 * request and the boxes after it read the same arm; its name is the box's
 * value `arm`. A trial takes the first arm and offers the others.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most arms a Split box has; each is an outcome, arm_1 to arm_5.
const WS_FLOW_SPLIT_ARMS_MAX = 5;

// The fewest arms a Split box may be published with.
const WS_FLOW_SPLIT_ARMS_MIN = 2;

// The longest name of an arm.
const WS_FLOW_SPLIT_NAME_MAX = 60;

/**
 * The letter an arm is called by at its place (0 the first): A to E. Pure.
 *
 * @param int $index
 * @return string
 */
function ws_flow_split_letter($index)
{
    return chr(65 + max(0, min(25, (int) $index)));
}

/**
 * A Split box's settings as kept: up to five arms, each a name of one line
 * (its letter when left empty) and a whole share from 0 to 100; anything
 * that is no arm is left out. Pure.
 *
 * @param array $config
 * @return array arms (name, share)
 */
function ws_flow_split_normalize($config)
{
    $arms = array();

    foreach ((is_array($config['arms'] ?? null) ? $config['arms'] : array()) as $arm) {
        if (!is_array($arm)) {
            continue;
        }

        if (count($arms) >= WS_FLOW_SPLIT_ARMS_MAX) {
            break;
        }

        $name = ws_flow_line(is_string($arm['name'] ?? null) ? $arm['name'] : '', WS_FLOW_SPLIT_NAME_MAX);
        $share = $arm['share'] ?? 0;

        $arms[] = array(
            'name'  => ($name !== '') ? $name : ws_flow_split_letter(count($arms)),
            'share' => (is_bool($share) || !is_numeric($share)) ? 0 : max(0, min(100, (int) $share)),
        );
    }

    return array('arms' => $arms);
}

/**
 * What a Split box still lacks, in sentences. Pure but for lang().
 *
 * @param array  $config ws_flow_split_normalize()
 * @param string $name   the box's name in the sentences
 * @return string[]
 */
function ws_flow_split_problems($config, $name)
{
    $arms = (array) ($config['arms'] ?? array());

    if (count($arms) < WS_FLOW_SPLIT_ARMS_MIN) {
        return array(lang(array('string' => 'The box {var:1} needs at least two arms.', 'vars' => $name)));
    }

    $problems = array();
    $shares = array_column($arms, 'share');

    if (min($shares) <= 0) {
        $problems[] = lang(array('string' => 'Every arm of the box {var:1} needs a share above 0.', 'vars' => $name));
    }

    if (array_sum($shares) !== 100) {
        $problems[] = lang(array('string' => 'The shares of the box {var:1} add up to {var:2}%, not 100%.', 'vars' => array($name, array_sum($shares))));
    }

    $names = array_map('mb_strtolower', array_column($arms, 'name'));

    if (count(array_unique($names)) !== count($names)) {
        $problems[] = lang(array('string' => 'Each arm of the box {var:1} needs a name of its own.', 'vars' => $name));
    }

    return $problems;
}

/**
 * Every outcome a Split box may leave on: arm_1 to arm_5. Pure.
 *
 * @return string[]
 */
function ws_flow_split_outcomes()
{
    $out = array();

    for ($index = 0; $index < WS_FLOW_SPLIT_ARMS_MAX; $index++) {
        $out[] = 'arm_' . ($index + 1);
    }

    return $out;
}

/**
 * The outcomes an arrow may leave this Split box on: one for each of its
 * arms by its place (renaming an arm keeps its arrow), and always. Pure.
 *
 * @param array $config ws_flow_split_normalize()
 * @return string[]
 */
function ws_flow_split_arrow_outcomes($config)
{
    $out = array();

    foreach (array_keys((array) ($config['arms'] ?? array())) as $index) {
        $out[] = 'arm_' . ($index + 1);
    }

    return array_merge($out, array('always'));
}

/**
 * The place of the arm an outcome names (0 the first), -1 for none. Pure.
 *
 * @param string $outcome
 * @return int
 */
function ws_flow_split_arm_index($outcome)
{
    return (preg_match('/^arm_([1-9])$/', (string) $outcome, $match) === 1) && ((int) $match[1] <= WS_FLOW_SPLIT_ARMS_MAX) ? (int) $match[1] - 1 : -1;
}

/**
 * The name of the arm an outcome names, '' for none. Pure.
 *
 * @param array  $config  ws_flow_split_normalize()
 * @param string $outcome
 * @return string
 */
function ws_flow_split_arm_name($config, $outcome)
{
    $index = ws_flow_split_arm_index($outcome);

    return ($index >= 0) ? (string) ($config['arms'][$index]['name'] ?? '') : '';
}

/**
 * The arm a roll falls in: the shares laid end to end from 0, the roll
 * (brought between 0 and the total less one) takes the arm whose share
 * covers it, so an arm of 0 is never taken. With no share at all, the first
 * arm; with no arm, -1. Pure.
 *
 * @param array[] $arms ws_flow_split_normalize() arms
 * @param int     $roll
 * @return int the arm's place
 */
function ws_flow_split_pick($arms, $roll)
{
    $arms = array_values((array) $arms);

    if (empty($arms)) {
        return -1;
    }

    $total = array_sum(array_map(function ($arm) {
        return max(0, (int) ($arm['share'] ?? 0));
    }, $arms));

    if ($total <= 0) {
        return 0;
    }

    $roll = max(0, min($total - 1, (int) $roll));
    $upto = 0;

    foreach ($arms as $index => $arm) {
        $upto += max(0, (int) ($arm['share'] ?? 0));

        if ($roll < $upto) {
            return $index;
        }
    }

    return count($arms) - 1;
}

/**
 * The arms and their shares in words: “A” 50%, “B” 50%. Pure but for lang().
 *
 * @param array $config ws_flow_split_normalize()
 * @return string
 */
function ws_flow_split_shares_text($config)
{
    $parts = array();

    foreach ((array) ($config['arms'] ?? array()) as $arm) {
        $parts[] = lang(array('string' => '“{var:1}” {var:2}%', 'vars' => array($arm['name'], (int) $arm['share'])));
    }

    return implode(', ', $parts);
}

/**
 * What a Split box does, told in a box's try. Pure but for lang().
 *
 * @param array $config ws_flow_split_normalize()
 * @return string
 */
function ws_flow_split_text($config)
{
    return lang(array('string' => 'Takes one arm at random by its share: {var:1}.', 'vars' => ws_flow_split_shares_text($config)));
}

/**
 * Runs a Split box: one arm picked at random by the shares, kept as the
 * step's outcome with its name as the value `arm`. Settled once from
 * running, so the arm never changes for the run. Arms that cannot be taken
 * (a draft never published as such) fail the box.
 *
 * @param array $step ws_flow_run_steps row
 * @param array $node the box
 * @return void
 */
function ws_flow_split_run($step, $node)
{
    $config = ws_flow_split_normalize((array) ($node['config'] ?? array()));
    $problems = ws_flow_split_problems($config, ws_flow_box_name($node));

    if (!empty($problems)) {
        ws_flow_step_settle($step, 'running', 'failed', '', (string) $problems[0]);
        return;
    }

    $index = ws_flow_split_pick($config['arms'], random_int(0, 99));
    $arm = $config['arms'][$index];

    ws_flow_step_settle($step, 'running', 'done', 'arm_' . ($index + 1),
        lang(array('string' => 'Took the arm “{var:1}” ({var:2}%).', 'vars' => array($arm['name'], (int) $arm['share']))),
        array('arm' => $arm['name']));
}
