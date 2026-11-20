<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's call in the external API and MCP, read only: the
 * live call, its kind and the people in it. Nothing here starts, joins or
 * ends a call: a call is always a person's own act in a browser
 * (calls.php).
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
 * The call route, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_call_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.channels.call.get',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/call',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_call_get',
            'returns'     => 'WorkspaceChannelCall',
            'summary'     => 'A channel\'s live voice or video call',
            'description' => 'Whether a voice or video call is on in the channel now: call is null when none is, else its id, kind (voice or video), who started it, when, and the people in it (kind person or guest; user_id null for a guest; name as the channel knows them; joined_at). Read only: no route starts, joins or ends a call, which a person does in the browser. Refusals: the owner not a member of the workspace 403 forbidden; a channel the owner may not read 404 not_found.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),
    );
}

function ws_api_channel_call_get($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $call = ws_calls_ready() ? ws_call_live((int) $channel['id']) : null;

    if (!$call) {
        api_ok(ws_api_call_present(null));
    }

    $members = ws_call_members_live((int) $call['id']);

    api_ok(ws_api_call_present(ws_call_present($call, $members, ws_call_member_names($members))));
}

/**
 * The call as the API gives it. Pure.
 *
 * @param array|null $call ws_call_present(), null for none
 * @return array
 */
function ws_api_call_present($call)
{
    if (!is_array($call)) {
        return array('call' => null);
    }

    $members = array();

    foreach ((array) $call['members'] as $member) {
        $guest = (string) $member['kind'] === 'guest';

        $members[] = array(
            'kind'      => $guest ? 'guest' : 'person',
            'user_id'   => $guest ? null : (int) $member['user_id'],
            'name'      => (string) $member['name'],
            'joined_at' => api_time((int) $member['joined_at']),
        );
    }

    return array('call' => array(
        'id'         => (int) $call['id'],
        'kind'       => (string) $call['kind'],
        'started_by' => (int) $call['started_by'],
        'started_at' => api_time((int) $call['started_at']),
        'members'    => $members,
    ));
}

function ws_api_channel_call_schema()
{
    return array(
        'call' => 'WorkspaceCall?',
    );
}

function ws_api_call_schema()
{
    return array(
        'id'         => 'integer',
        'kind'       => 'string',
        'started_by' => 'integer',
        'started_at' => 'datetime',
        'members'    => 'WorkspaceCallMember[]',
    );
}

function ws_api_call_member_schema()
{
    return array(
        'kind'      => 'string',
        'user_id'   => 'integer?',
        'name'      => 'string',
        'joined_at' => 'datetime',
    );
}
