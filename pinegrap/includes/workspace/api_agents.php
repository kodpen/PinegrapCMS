<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agents of a channel in the external API and MCP
 * (includes/workspace/agents.php): list them with the registered agents the
 * channel can still take, read one agent's card, add an agent to a channel
 * or change what wakes it there, take it out, and a person's setting of an
 * application agent's scheduled wakes from the channel (/wake @agent). Whoever may post in a channel
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
            'returns'     => array('list' => 'WorkspaceChannelAgent', 'addable' => 'WorkspaceAddableAgent[]'),
            'summary'     => 'The agents in a channel',
            'description' => 'The agents added to a channel the owner of the application may read, in the order they were added, each with what wakes it there, its tier and whether the loop-guard text is on, and what the channel\'s agent card shows: state (paused, working, late for not seen, waiting or idle), waiting (requests queued for it), seen_at (its last call with its key), and its last report of model and usage windows (reported_at; usage_high when a window of a report younger than a day is at 90% or more). The report is the agent\'s own: the site cannot check it. Claude is no channel agent and is not listed: it answers where the channel\'s claude_access allows. Beside data, addable lists the registered agents the channel does not have yet, as the Add an agent list of Channel settings shows them (id, handle, name, status: active, paused or off), never a door or a key: an id there is the agent_id POST /workspace/channels/{id}/agents takes. The database not updated yet leaves addable empty.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.channels.agents.card',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/agents/{agent_id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_agent_card',
            'returns'     => 'WorkspaceAgentCard',
            'summary'     => 'The agent card of one agent of a channel',
            'description' => 'The card the channel opens on an agent\'s name or picture, for a channel the owner of the application may read: its role, state (paused, working, late for not seen, waiting or idle) and the requests waiting for it, last seen, its last report of model and usage windows (the agent\'s own report: the site cannot check it; stale when a day old), today\'s answered and failed requests, its last message in this channel, the channels it is in that the owner may read, its tier and what wakes it here. The words fields (role, seen, wake, reported, state_words, a window\'s resets, last_message.when) are in the site\'s language. Refusals: a channel the owner may not read, or an agent not in the channel, 404 not_found; the database not updated yet 503 service_unavailable.',
            'params'      => array(
                $id,
                array('name' => 'agent_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
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
            'description' => 'Adds an agent to a channel the owner of the application may post in - to a private one only when the owner manages it - or changes what wakes an agent the channel has, in a private one as a member of it. Fields left out keep their value (a new row: woken by a mention, tier 1, loop guard on). A row decides waking and the tier only, never what the agent may read. Claude is no channel agent: its agent_id answers 422 validation_failed (claude_not_channel_agent), a rehearsal too.' . $errors,
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
            'id'          => 'workspace.channels.agents.wake',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/agents/{agent_id}/wake',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_agent_wake',
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => ws_api_channel_agent_wake_schema(),
            'summary'     => 'Set an agent\'s scheduled wakes from a channel',
            'description' => 'The same as /wake @agent typed in the channel: every (minutes, 1-1440) wakes the agent every so often from now; at (a moment to come) once; off clears its schedule. Give exactly one of them; note is what each wake request carries. The change leaves the same system line in the channel, naming the person, and message_id is that line. Only a person signed in on a device sets it: an application key sets no agent\'s wakes, and an agent keeps its own POST /workspace/agent-wake. No MCP tool. Refusals: not exactly one of every, at and off, or at a time gone, 422 validation_failed; a channel the person may not read, an agent that is none, or no application agent (a model agent has no scheduled wakes) 404 not_found; an application key, a person who may not write in the channel, an agent not in it, or one paused 403 forbidden; the database not updated yet 503 service_unavailable.',
            'params'      => array(
                $id,
                array('name' => 'agent_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'every', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => WS_AGENT_WAKE_EVERY_MAX),
                array('name' => 'at', 'in' => 'body', 'type' => 'datetime'),
                array('name' => 'off', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'note', 'in' => 'body', 'type' => 'string', 'max_length' => WS_AGENT_WAKE_NOTE_MAX),
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
            'description'  => 'Whoever may post in the channel takes an agent out of it, in a private channel as a member of it; an agent that was not in it is not an error: removed is then false. A Claude row made before Claude was left out of the channels is taken out here too. A client that cannot send DELETE, or talks to an IIS server, uses POST /workspace/channels/{id}/agents/{agent_id}/delete; a plain POST to this address does the same.',
            'params'       => array(
                $id,
                array('name' => 'agent_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'           => 'workspace.channels.agents.remove_post',
            'method'       => 'POST',
            'path'         => '/workspace/channels/{id}/agents/{agent_id}/delete',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_channel_agents_remove',
            'dry_run'      => true,
            'returns'      => 'WorkspaceChannelAgentRemoval',
            'summary'      => 'Take an agent out of a channel, addressed as a POST',
            'description'  => 'The same as DELETE /workspace/channels/{id}/agents/{agent_id}, for clients and servers that cannot send DELETE.',
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
        'role'        => 'string',
        'status'      => 'string',
        'wake_rule'   => 'string',
        'wake_prefix' => 'string',
        'tier'        => 'integer',
        'loop_guard'  => 'boolean',
        'added_by'    => 'integer',
        'added_at'    => 'datetime?',
        'state'       => 'string',
        'waiting'     => 'integer',
        'seen_at'     => 'datetime?',
        'model'       => 'string',
        'usage'       => array(array('name' => 'string', 'percent' => 'integer', 'resets_at' => 'datetime?')),
        'reported_at' => 'datetime?',
        'usage_high'  => 'boolean',
    );
}

function ws_api_addable_agent_schema()
{
    return array(
        'id'     => 'integer',
        'handle' => 'string',
        'name'   => 'string',
        'status' => 'string',
    );
}

function ws_api_agent_card_schema()
{
    return array(
        'channel_id'   => 'integer',
        'id'           => 'integer',
        'app_id'       => 'integer',
        'handle'       => 'string',
        'name'         => 'string',
        'kind'         => 'string',
        'avatar'       => 'string',
        'role'         => 'string',
        'role_key'     => 'string',
        'state'        => 'string',
        'state_words'  => 'string',
        'waiting'      => 'integer',
        'seen'         => 'string',
        'seen_state'   => 'string',
        'model'        => 'string',
        'windows'      => array(array('name' => 'string', 'label' => 'string', 'percent' => 'integer', 'resets_at' => 'datetime?', 'resets' => 'string', 'high' => 'boolean')),
        'reported'     => 'string',
        'stale'        => 'boolean',
        'usage_high'   => 'boolean',
        'today'        => array('answered' => 'integer', 'failed' => 'integer'),
        'last_message' => 'WorkspaceAgentCardMessage?',
        'channels'     => array(array('id' => 'integer', 'name' => 'string')),
        'tier'         => 'integer',
        'wake'         => 'string',
    );
}

function ws_api_agent_card_message_schema()
{
    return array(
        'id'   => 'integer',
        'when' => 'string',
    );
}

/**
 * The Add an agent list of Channel settings (ws_channel_agents_addable()) as
 * the API gives it: id, handle, name and status only. Pure.
 *
 * @param array[] $addable ws_channel_agents_addable()
 * @return array[]
 */
function ws_api_addable_agents_present($addable)
{
    $out = array();

    foreach ((array) $addable as $agent) {
        $out[] = array(
            'id'     => (int) $agent['agent_id'],
            'handle' => (string) $agent['handle'],
            'name'   => (string) $agent['name'],
            'status' => (string) $agent['status'],
        );
    }

    return $out;
}

/**
 * The agent card (ws_agent_card()) as the API gives it: the panel's fields
 * with the channel's id beside them and a window's reset time as an API
 * time. Pure but for api_time().
 *
 * @param int   $channel_id
 * @param array $card ws_agent_card()
 * @return array
 */
function ws_api_agent_card_present($channel_id, $card)
{
    $out = array('channel_id' => (int) $channel_id) + (array) $card;
    $windows = array();

    foreach ((array) ($card['windows'] ?? array()) as $window) {
        $window['resets_at'] = ((int) ($window['resets_at'] ?? 0) > 0) ? api_time($window['resets_at']) : null;
        $windows[] = $window;
    }

    $out['windows'] = $windows;

    return array_intersect_key($out, ws_api_agent_card_schema());
}

// What the channel wake route returns: the agent's schedule as the
// agent-wake routes give it, beside the channel, the agent and the line.
function ws_api_channel_agent_wake_schema()
{
    return array('channel_id' => 'integer', 'agent_id' => 'integer', 'message_id' => 'integer?') + ws_api_agent_wake_schema();
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
 * A channel-agent row as the API gives it, with what the channel's agent
 * card shows (agent_card.php): state, requests waiting, last seen, and the
 * agent's last report of its model and usage windows.
 *
 * @param int   $channel_id
 * @param array $row    ws_channel_agent_shape()
 * @param array $counts ws_agent_request_counts() of the channel's agents
 * @param int   $now
 * @return array
 */
function ws_api_channel_agent_present($channel_id, $row, $counts = array(), $now = 0)
{
    $now = ($now > 0) ? (int) $now : time();
    $agent = (array) $row['agent'];
    $member = ws_channel_member_agent($row, $now);
    $brief = ws_agent_card_brief($agent, $member['seen_state'], $counts[ws_agent_request_value($agent)] ?? array(), $now);
    $report_at = ((string) $agent['kind'] === 'app') ? (int) ($agent['report_at'] ?? 0) : 0;
    $usage = array();

    foreach (($report_at > 0) ? ws_agent_report_windows($agent['report_usage'] ?? '') : array() as $window) {
        $usage[] = array('name' => (string) $window['name'], 'percent' => (int) $window['percent'], 'resets_at' => api_time($window['resets_at']));
    }

    return array(
        'channel_id'  => (int) $channel_id,
        'agent_id'    => (int) $row['agent']['id'],
        'kind'        => (string) $row['agent']['kind'],
        'handle'      => (string) $row['agent']['handle'],
        'name'        => (string) $row['agent']['name'],
        'role'        => ws_agent_role_key($row['agent']['role'] ?? ''),
        'status'      => (string) $row['agent']['status'],
        'wake_rule'   => (string) $row['wake_rule'],
        'wake_prefix' => (string) $row['wake_prefix'],
        'tier'        => (int) $row['tier'],
        'loop_guard'  => (bool) $row['loop_guard'],
        'added_by'    => (int) $row['added_by'],
        'added_at'    => api_time($row['added_at']),
        'state'       => $brief['state'],
        'waiting'     => $brief['waiting'],
        'seen_at'     => ((int) ($agent['seen_at'] ?? 0) > 0) ? api_time($agent['seen_at']) : null,
        'model'       => ($report_at > 0) ? (string) ($agent['report_model'] ?? '') : '',
        'usage'       => $usage,
        'reported_at' => ($report_at > 0) ? api_time($report_at) : null,
        'usage_high'  => $brief['usage_high'],
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
        api_fail_not_found(lang('The agent'));
    }

    return array('channel' => $channel, 'agent' => $agent);
}

function ws_api_channel_agents_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $out = array();
    $rows = ws_channel_agents($channel['id']);
    $now = time();
    $counts = ws_agent_request_counts(array_map(function ($row) {
        return $row['agent'];
    }, $rows), $now);

    $in = array();

    foreach ($rows as $row) {
        $out[] = ws_api_channel_agent_present($channel['id'], $row, $counts, $now);
        $in[] = (int) $row['agent']['id'];
    }

    $addable = ws_agents_ready() ? ws_api_addable_agents_present(ws_channel_agents_addable(ws_agents_list(), $in)) : array();

    api_ok_list($out, count($out), null, null, array('addable' => $addable));
}

function ws_api_channel_agent_card($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_agents_ready_or_503();

    $card = ws_agent_card($viewer, $channel, (int) $params['agent_id'], time());

    if (!is_array($card)) {
        api_fail_not_found(lang('The agent'));
    }

    api_ok(ws_api_agent_card_present($channel['id'], $card));
}

function ws_api_channel_agents_set($params)
{
    $viewer = ws_api_viewer();
    $target = ws_api_channel_agent_target($viewer, $params);
    $channel = $target['channel'];
    $agent = $target['agent'];
    $kind_problem = ws_channel_agent_kind_problem($agent['kind']);

    if ($kind_problem !== '') {
        api_fail(422, 'validation_failed', ws_channel_agent_error_text($kind_problem), 'agent_id');
    }

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

    api_fail_not_found(lang('The agent'));
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

/**
 * A person sets an application agent's scheduled wakes from a channel, as
 * /wake @agent does (ws_command_wake()): the same check, writer and system
 * line. A device session only: an application key sets no agent's wakes.
 */
function ws_api_channel_agent_wake($params)
{
    ws_api_person_only(lang('Only a person signed in on a device can set an agent\'s wakes; an application key cannot.'));

    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_agents_ready_or_503();

    $agent = ws_agent((int) $params['agent_id']);

    if (!$agent || ((string) $agent['kind'] !== 'app')) {
        api_fail_not_found(lang('The agent'));
    }

    $problem = ws_agent_wake_problem($viewer, $channel, $agent);

    if ($problem !== '') {
        api_fail(403, 'forbidden', $problem);
    }

    $now = time();
    $values = ws_agent_wake_values(ws_api_agent_wake_parsed($params, $now), $now);
    $out = array('channel_id' => (int) $channel['id'], 'agent_id' => (int) $agent['id'], 'message_id' => null) + ws_api_agent_wake_present(array('handle' => $agent['handle']) + $values);

    api_dry_run_stop('set', 'workspace_agent_wake', $out);

    ws_agent_wake_save((int) $agent['id'], $values);
    $out['message_id'] = ws_agent_wake_system_line($viewer, $channel, $agent, $values);

    api_ok($out);
}
