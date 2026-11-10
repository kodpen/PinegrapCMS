<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agents of a channel in the external API and MCP
 * (includes/workspace/agents.php): list them, add an agent to a channel or
 * change what wakes it there, and take it out. Whoever may post in a channel
 * changes its agents, in a private channel only a member; a new agent in a
 * private channel and the tier above 1 are the channel manager's. A row
 * decides waking and the tier only: an application keeps reading with its
 * owner's reach.
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

/**
 * The channel-agent routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_agent_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $errors = ' A refused value answers 422 validation_failed naming the field (wake_rule_invalid, wake_prefix_invalid, tier_invalid); a tier changed, or an agent added to a private channel, by anybody but a manager of the channel 403 forbidden; the database not updated yet 503 service_unavailable.';

    return array(
        array(
            'id'          => 'workspace.channels.agents.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/agents',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_agents_list',
            'returns'     => array('list' => 'WorkspaceChannelAgent'),
            'summary'     => 'The agents in a channel',
            'description' => 'The agents added to a channel the owner of the application may read, in the order they were added, each with what wakes it there, its tier and whether the loop-guard text is on. An agent with no row in a channel works by its defaults there (Claude by the channel\'s claude_access).',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.channels.agents.set',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/agents',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_agents_set',
            'dry_run'     => true,
            'returns'     => 'WorkspaceChannelAgent',
            'summary'     => 'Add an agent to a channel or change its row',
            'description' => 'Adds an agent to a channel the owner of the application may post in - a private one only when the owner is in it - or changes what wakes it there. Fields left out keep their value (a new row: woken by a mention, tier 1, loop guard on). A row decides waking and the tier only, never what the agent may read.' . $errors,
            'params'      => array(
                $id,
                array('name' => 'agent_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'wake_rule', 'in' => 'body', 'type' => 'enum', 'values' => ws_agent_wake_rules(), 'description' => 'mention: its @handle; all: every message; prefix: a message starting with wake_prefix; replies: replies to its own messages.'),
                array('name' => 'wake_prefix', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'description' => 'For wake_rule prefix, such as [ci].'),
                array('name' => 'tier', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => 3, 'description' => '1 reading and messages, 2 reversible record changes, 3 irreversible ones. Only the owner of the channel changes it.'),
                array('name' => 'loop_guard', 'in' => 'body', 'type' => 'bool', 'description' => 'Whether the loop-guard text goes into the agent\'s brief here.'),
            ),
        ),

        array(
            'id'           => 'workspace.channels.agents.remove',
            'method'       => 'DELETE',
            'also_accepts' => array('POST'),
            'path'         => '/workspace/channels/{id}/agents/{agent_id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_channel_agents_remove',
            'dry_run'      => true,
            'returns'      => 'WorkspaceChannelAgentRemoval',
            'summary'      => 'Take an agent out of a channel',
            'description'  => 'Whoever may add an agent to the channel takes it out; an agent that was not in it is not an error: removed is then false. POST is accepted as well, because a default IIS install answers DELETE itself before PHP is reached.',
            'params'       => array(
                $id,
                array('name' => 'agent_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),
    );
}

function ws_api_channel_agent_schema()
{
    return array(
        'channel_id'  => 'integer',
        'agent_id'    => 'integer',
        'kind'        => 'string',
        'handle'      => 'string',
        'name'        => 'string',
        'status'      => 'string',
        'wake_rule'   => 'string',
        'wake_prefix' => 'string',
        'tier'        => 'integer',
        'loop_guard'  => 'boolean',
        'added_by'    => 'integer',
        'added_at'    => 'string?',
    );
}

function ws_api_channel_agent_removal_schema()
{
    return array(
        'channel_id' => 'integer',
        'agent_id'   => 'integer',
        'removed'    => 'boolean',
    );
}

/**
 * A channel-agent row as the API gives it.
 *
 * @param int   $channel_id
 * @param array $row ws_channel_agent_shape()
 * @return array
 */
function ws_api_channel_agent_present($channel_id, $row)
{
    return array(
        'channel_id'  => (int) $channel_id,
        'agent_id'    => (int) $row['agent']['id'],
        'kind'        => (string) $row['agent']['kind'],
        'handle'      => (string) $row['agent']['handle'],
        'name'        => (string) $row['agent']['name'],
        'status'      => (string) $row['agent']['status'],
        'wake_rule'   => (string) $row['wake_rule'],
        'wake_prefix' => (string) $row['wake_prefix'],
        'tier'        => (int) $row['tier'],
        'loop_guard'  => (bool) $row['loop_guard'],
        'added_by'    => (int) $row['added_by'],
        'added_at'    => api_time($row['added_at']),
    );
}

function ws_api_agents_ready_or_503()
{
    if (!ws_agents_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * The channel and the agent of a write, or the refusal.
 *
 * @param array $viewer
 * @param array $params
 * @return array channel, agent
 */
function ws_api_channel_agent_target($viewer, $params)
{
    ws_api_agents_ready_or_503();

    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $problem = ws_channel_agent_add_problem($viewer, $channel);

    if ($problem !== '') {
        api_fail(403, 'forbidden', ($problem === 'forbidden') ? lang('You cannot post in that channel.') : ws_channel_agent_error_text($problem));
    }

    $agent = ws_agent($params['agent_id']);

    if (!$agent) {
        api_fail_not_found(lang('Agent'));
    }

    return array('channel' => $channel, 'agent' => $agent);
}

function ws_api_channel_agents_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $out = array();

    foreach (ws_channel_agents($channel['id']) as $row) {
        $out[] = ws_api_channel_agent_present($channel['id'], $row);
    }

    api_ok_list($out, count($out));
}

function ws_api_channel_agents_set($params)
{
    $viewer = ws_api_viewer();
    $target = ws_api_channel_agent_target($viewer, $params);
    $channel = $target['channel'];
    $agent = $target['agent'];
    $current = ws_channel_agent_row($channel['id'], $agent['id']);
    $problem = ws_channel_agent_enter_problem($viewer, $channel, $current);

    if ($problem !== '') {
        api_fail(403, 'forbidden', ws_channel_agent_error_text($problem));
    }

    $check = ws_channel_agent_check($params, $current, ws_can_manage_channel($viewer, $channel));

    if (!$check['ok']) {
        if ($check['error'] === 'tier_forbidden') {
            api_fail(403, 'forbidden', ws_channel_agent_error_text($check['error']));
        }

        api_fail(422, 'validation_failed', ws_channel_agent_error_text($check['error']), $check['field']);
    }

    api_dry_run_stop($current ? 'updated' : 'added', 'workspace_channel_agent', array('channel_id' => (int) $channel['id'], 'agent_id' => (int) $agent['id']) + $check['values']);

    ws_channel_agent_save($viewer, $channel['id'], $agent['id'], $check['values']);

    foreach (ws_channel_agents($channel['id']) as $row) {
        if ((int) $row['agent']['id'] === (int) $agent['id']) {
            api_ok(ws_api_channel_agent_present($channel['id'], $row));
        }
    }

    api_fail_not_found(lang('Agent'));
}

function ws_api_channel_agents_remove($params)
{
    $viewer = ws_api_viewer();
    $target = ws_api_channel_agent_target($viewer, $params);
    $channel_id = (int) $target['channel']['id'];
    $agent_id = (int) $target['agent']['id'];

    api_dry_run_stop('removed', 'workspace_channel_agent', array('channel_id' => $channel_id, 'agent_id' => $agent_id, 'member' => (bool) ws_channel_agent_row($channel_id, $agent_id)));

    api_ok(array('channel_id' => $channel_id, 'agent_id' => $agent_id, 'removed' => ws_channel_agent_remove($channel_id, $agent_id)));
}
