<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - channel templates in the external API: the list an application
 * offers its user, and applying one to a channel. Applied the way the panel
 * applies it (ws_template_apply()), with the reach of the application's
 * owner: a task the owner may not give to somebody is made with nobody on it,
 * and the answer says how many. The pinned message is written as the
 * application's; the notes are the owner's own and shared in their name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

function ws_api_template_present($template)
{
    $present = ws_template_present($template);

    return array(
        'id'            => $present['id'],
        'builtin'       => $present['builtin'],
        'name'          => $present['name'],
        'description'   => $present['description'],
        'icon'          => ($present['icon'] !== '') ? $present['icon'] : null,
        'color'         => ($present['hex'] !== '') ? $present['hex'] : null,
        'kind'          => $present['kind'],
        'department_id' => ($present['department_id'] > 0) ? $present['department_id'] : null,
        'tasks'         => $present['tasks'],
        'notes'         => $present['notes'],
        'summary'       => $present['summary'],
        'pinned'        => $present['pinned'],
        'welcome'       => $present['welcome'],
        'uses'          => $present['uses'],
    );
}

// What ws_api_template_present() returns.
function ws_api_template_schema()
{
    return array(
        'id'            => 'string',
        'builtin'       => 'boolean',
        'name'          => 'string',
        'description'   => 'string',
        'icon'          => 'string?',
        'color'         => 'string?',
        'kind'          => 'string',
        'department_id' => 'integer?',
        'tasks'         => 'integer',
        'notes'         => 'integer',
        'summary'       => 'boolean',
        'pinned'        => 'boolean',
        'welcome'       => 'boolean',
        'uses'          => 'integer',
    );
}

function ws_api_template_applied_present($channel, $template, $result)
{
    return array(
        'channel_id'    => (int) $channel['id'],
        'template_id'   => (string) $template['id'],
        'tasks'         => (int) $result['tasks'],
        'notes'         => (int) $result['notes'],
        'task_ids'      => array_values(array_map('intval', $result['task_ids'])),
        'unassigned'    => (int) $result['unassigned'],
        'summary_kept'  => (bool) $result['summary_kept'],
        'pinned_kept'   => (bool) $result['pinned_kept'],
        'message'       => (string) $result['message'],
    );
}

// What ws_api_template_applied_present() returns.
function ws_api_template_applied_schema()
{
    return array(
        'channel_id'   => 'integer',
        'template_id'  => 'string',
        'tasks'        => 'integer',
        'notes'        => 'integer',
        'task_ids'     => array('integer'),
        'unassigned'   => 'integer',
        'summary_kept' => 'boolean',
        'pinned_kept'  => 'boolean',
        'message'      => 'string',
    );
}

function ws_api_templates_list($params)
{
    $viewer = ws_api_viewer();
    $out = array();

    foreach (ws_templates_list($viewer) as $template) {
        $out[] = ws_api_template_present($template);
    }

    api_ok_list($out, count($out));
}

function ws_api_channel_apply_template($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!in_array((string) $channel['kind'], array('public', 'private'), true) || !ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $template = ws_template((string) ($params['template_id'] ?? ''));

    if (!$template || $template['archived']) {
        api_fail_validation(lang('That template could not be found.'), 'template_id');
    }

    api_dry_run_stop('applied', 'workspace_template', array(
        'channel_id'  => (int) $channel['id'],
        'template_id' => (string) $template['id'],
        'tasks'       => count($template['body']['tasks']),
        'notes'       => count($template['body']['notes']),
    ));

    $result = ws_template_apply($viewer, $channel, $template, array('app_id' => ws_api_app_id()));

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    api_ok(ws_api_template_applied_present($channel, $template, $result));
}
