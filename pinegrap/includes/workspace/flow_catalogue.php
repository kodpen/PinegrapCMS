<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what a flow is built from, for whoever builds one without the
 * canvas (an application, an assistant over MCP): every kind of box with the
 * outcomes its arrows may leave on, the values it gives the boxes after it
 * and its settings, read from the normalizers the draft is kept through, so
 * the catalogue cannot drift from what a save keeps; and a draft looked at
 * as publishing would look at it, without publishing it.
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
 * The settings whose type a box's empty settings do not show: a number left
 * empty for none (kept as ''), a set of named values, and the lists of
 * objects rather than of ids.
 *
 * @return array "kind.key" => type, blank, of
 */
function ws_flow_setting_overrides()
{
    $blank_integer = array('type' => 'integer', 'blank' => true);

    return array(
        'task.due_in'       => $blank_integer,
        'task.timeout'      => $blank_integer,
        'task_open.due_in'  => $blank_integer,
        'delay.amount'      => $blank_integer,
        'approval.closes_in' => $blank_integer,
        'mcp.closes_in'     => $blank_integer,
        'change.fields'     => array('type' => 'object'),
        'condition.rule'    => array('type' => 'object'),
        'report.metrics'    => array('of' => 'object'),
    );
}

/**
 * The settings that take one of a few words, each kept by its normalizer
 * and any other word replaced by the first (tests/workspace_flow_catalogue_test.php
 * holds the two together).
 *
 * @return array "kind.key" => string[]
 */
function ws_flow_setting_values()
{
    $priorities = array('normal', 'low', 'high', 'urgent');

    return array(
        'task.priority'      => $priorities,
        'task_open.priority' => $priorities,
        'delay.unit'         => array_keys(ws_flow_delay_units()),
        'webhook.format'     => array('generic', 'text', 'discord'),
        'web_check.mode'     => array('status', 'ssl'),
        'task_digest.mode'   => array('mine', 'channel'),
        'task_digest.only'   => array('open', 'week', 'overdue', 'stale'),
        'change.action'      => array('update', 'delete'),
        'approval.rule'      => array('any', 'all'),
        'row.what'           => array_keys(ws_flow_row_whats()),
    );
}

/**
 * The settings of one kind of box, in the order its normalizer keeps them:
 * the key, its type (text, integer, boolean, list or object), blank (an
 * integer left empty for none), of (what a list holds: integer or object)
 * and values (the words it takes, or null). Pure.
 *
 * @param string $kind
 * @param array  $empty ws_flow_config_normalize($kind, array())
 * @return array[]
 */
function ws_flow_kind_settings($kind, $empty)
{
    $overrides = ws_flow_setting_overrides();
    $words = ws_flow_setting_values();
    $out = array();

    foreach ($empty as $key => $value) {
        if (is_bool($value)) {
            $type = 'boolean';
        } elseif (is_int($value)) {
            $type = 'integer';
        } elseif (is_array($value)) {
            $type = (empty($value) || array_keys($value) === range(0, count($value) - 1)) ? 'list' : 'object';
        } else {
            $type = 'text';
        }

        $setting = (array) ($overrides[$kind . '.' . $key] ?? array()) + array('type' => $type, 'blank' => false);

        $out[] = array(
            'key'    => (string) $key,
            'type'   => $setting['type'],
            'blank'  => (bool) $setting['blank'],
            'of'     => ($setting['type'] === 'list') ? (string) ($setting['of'] ?? 'integer') : null,
            'values' => $words[$kind . '.' . $key] ?? null,
        );
    }

    return $out;
}

/**
 * Every kind of box a flow knows, as ws_flow_kinds() has it, with its
 * settings: kind, label, starts (it starts the flow), step (a scheduled
 * action's step), outcomes, outputs (key, type, label; a web check's carry
 * the mode they belong to, a report adds one per count it sends), texts (the
 * settings that take {{node.ID.FIELD}}) and settings.
 *
 * @return array[]
 */
function ws_flow_kind_catalogue()
{
    $steps = function_exists('ws_step_kinds') ? ws_step_kinds() : array();
    $out = array();

    foreach (ws_flow_kinds() as $kind => $info) {
        $outputs = array();

        foreach ((array) $info['outputs'] as $output) {
            $outputs[] = array(
                'key'   => (string) $output['key'],
                'type'  => (string) $output['type'],
                'label' => (string) $output['label'],
                'mode'  => isset($output['mode']) ? (string) $output['mode'] : null,
            );
        }

        $out[] = array(
            'kind'     => (string) $kind,
            'label'    => (string) $info['label'],
            'starts'   => !empty($info['trigger']),
            'step'     => !empty($info['step']),
            'outcomes' => array_values(array_map('strval', (array) $info['outcomes'])),
            'outputs'  => $outputs,
            'texts'    => array_values(array_map('strval', (array) ($steps[$kind]['texts'] ?? array()))),
            'settings' => ws_flow_kind_settings($kind, ws_flow_config_normalize($kind, array())),
        );
    }

    return $out;
}

/**
 * A flow's draft looked at as publishing it would look at it, nothing
 * written: its problems for whoever may change it, and for its owner also
 * the checks made with their rights now (ws_flow_publish_check()), whose
 * first refusal is answered. A colleague the flow is shared with at edit
 * does not publish it, so only the problems are theirs.
 *
 * @param array $viewer
 * @param array $flow ws_flow_for() with access
 * @return array ok, error, status, field; problems, publishable, refusal ('' for none), checked_rights
 */
function ws_flow_validate($viewer, $flow)
{
    if (!ws_flows_ready()) {
        return ws_flow_refusal(lang('The database has to be updated first.'), 503);
    }

    if (ws_flow_access($viewer, $flow) !== 'edit') {
        return ws_flow_refusal(lang('Only the owner of a flow can change it.'), 403);
    }

    $problems = ws_flow_problems(ws_flow_graph_of($flow));
    $owner = ws_flow_share_manager($viewer, $flow);
    $refusal = '';

    if ($owner && empty($problems)) {
        $check = ws_flow_publish_check($viewer, $flow);
        $refusal = $check['ok'] ? '' : (string) $check['error'];
    }

    return array(
        'ok'             => true,
        'error'          => '',
        'status'         => 200,
        'field'          => '',
        'problems'       => $problems,
        'publishable'    => $owner && empty($problems) && ($refusal === ''),
        'refusal'        => $refusal,
        'checked_rights' => $owner,
    );
}
