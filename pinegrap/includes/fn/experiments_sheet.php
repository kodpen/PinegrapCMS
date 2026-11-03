<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Experiments sheet of a designed page (page_experiments.php): set up an
 * experiment with a variant draft, start, pause and end it, publish the
 * winner, and the holds that keep a variant draft from being published or
 * binned while its experiment is open.
 *
 * The model, the draw and the bucket cookie are in experiments.php. A
 * variant is a draft page made as a copy of the live page in the Drafts
 * folder, edited with the designer as any draft. Publishing the winner saves
 * the variant's tree into the live page through pg_designer_save_page(), so
 * the page lock, the content gate and a revision apply as to any save; the
 * experiment then ends and the draft is a plain draft again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The goal kinds an experiment counts (experiments.goal_kind).
 *
 * @return array
 */
function pg_experiment_goal_kinds()
{
    return array('form', 'order', 'click', 'page');
}

/**
 * The Experiments sheet's form cleaned: name (1-100 characters), goal kind,
 * goal target (a click goal's name of letters, digits, - and _; a page
 * goal's page id; empty for the others) and the variant's weight (1-99, the
 * live page takes the rest). Pure.
 *
 * @param array $in name, goal_kind, goal_target, weight_b
 * @return array errors (codes: name, goal, target, weight), values
 */
function pg_experiment_sheet_clean(array $in)
{
    $errors = array();

    $name = trim(isset($in['name']) && is_string($in['name']) ? $in['name'] : '');
    $name_length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

    if (($name === '') || ($name_length > 100)) {
        $errors[] = 'name';
    }

    $kind = isset($in['goal_kind']) && is_string($in['goal_kind']) ? $in['goal_kind'] : '';

    if (!in_array($kind, pg_experiment_goal_kinds(), true)) {
        $errors[] = 'goal';
        $kind = '';
    }

    $target = trim(isset($in['goal_target']) && is_scalar($in['goal_target']) ? (string) $in['goal_target'] : '');

    if ($kind === 'click') {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $target) !== 1) {
            $errors[] = 'target';
        }
    } elseif ($kind === 'page') {
        if (preg_match('/^[1-9][0-9]{0,9}$/', $target) !== 1) {
            $errors[] = 'target';
        }
    } else {
        $target = '';
    }

    $weight = isset($in['weight_b']) ? $in['weight_b'] : '';

    if (!is_numeric($weight) || ((int) $weight != $weight) || ((int) $weight < 1) || ((int) $weight > 99)) {
        $errors[] = 'weight';
        $weight = 50;
    }

    return array(
        'errors' => $errors,
        'values' => array(
            'name' => $name,
            'goal_kind' => $kind,
            'goal_target' => $target,
            'weight_b' => (int) $weight,
        ),
    );
}

/**
 * The two weights from the variant's share: the live page (a) takes the
 * rest of 100. The share is held inside 1-99. Pure.
 *
 * @param int $weight_b
 * @return array a, b
 */
function pg_experiment_sheet_weights($weight_b)
{
    $b = max(1, min(99, (int) $weight_b));

    return array('a' => 100 - $b, 'b' => $b);
}

/**
 * The buttons the sheet offers for an experiment in a status. The winner is
 * published from a running or paused one; a draft is deleted, never ended.
 * Pure.
 *
 * @param string $status
 * @return array
 */
function pg_experiment_sheet_actions($status)
{
    $actions = array(
        'draft' => array('start', 'delete'),
        'running' => array('pause', 'end', 'publish_winner'),
        'paused' => array('start', 'end', 'publish_winner'),
    );

    return isset($actions[$status]) ? $actions[$status] : array();
}

/**
 * The status a sheet action moves an experiment to, '' for an action that
 * is not a move. Pure.
 *
 * @param string $action
 * @return string
 */
function pg_experiment_sheet_action_status($action)
{
    $to = array('start' => 'running', 'pause' => 'paused', 'end' => 'ended');

    return isset($to[$action]) ? $to[$action] : '';
}

/**
 * Whether an experiment in a status holds its variant drafts: from set-up
 * until it ends, a variant can be neither published nor binned. Pure.
 *
 * @param string $status
 * @return bool
 */
function pg_experiment_variant_held($status)
{
    return in_array((string) $status, array('draft', 'running', 'paused'), true);
}

/**
 * The chip's theme for an experiment's status; '' draws no chip. Pure.
 *
 * @param string $status
 * @return string
 */
function pg_experiment_chip_theme($status)
{
    $themes = array('running' => 'success', 'paused' => 'warning', 'draft' => 'secondary');

    return isset($themes[$status]) ? $themes[$status] : '';
}
