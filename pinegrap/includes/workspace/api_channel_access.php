<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - who reads a channel and the versions of its conversation in
 * the external API: make a channel private or public, join a public
 * channel, clear the conversation and rename a version kept. Each is the
 * person's own act in the panel, so each is answered only for the person
 * signed in on a device (ws_api_person_only()) and none is an MCP tool. The
 * checks and writes are the panel's own (channels.php, eras.php).
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
 * The channel access and version routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_channel_access_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $person = ' Only the person, signed in on a device, does this: an application key or an OAuth connection is refused with 403, and it is no MCP tool.';
    $staff = ' Staff who are in the channel only, as in the panel: anybody else, an archived channel and a direct message, discussion or mailbox room are refused with 403.';

    return array(
        array(
            'id'          => 'workspace.channels.kind',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/kind',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_kind',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceChannelKindChange',
            'summary'     => 'Make a channel private or public',
            'description' => 'Makes a public channel private, so only its members read it from then on, or opens a private channel to the whole team, as Make private and Make public in the channel menu do. Its members stay; the channel gets a line saying so. gaining names the team members who start reading it, losing those who stop (staff may still open a private channel for inspection). Ask the dry run (X-Dry-Run: true) first to show who would come in or leave. A channel that already has that kind changes nothing: changed is false.' . $staff . $person,
            'params'      => array(
                $id,
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('public', 'private'), 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channels.join',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/join',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_join',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceChannel',
            'summary'     => 'Join a public channel',
            'description' => 'Makes the person a member of a public channel, as Join on the channel does: they hear about what is written in it and get its folder\'s files. Joining a channel they are in already changes nothing. A private channel is joined only when somebody in it adds the person (POST /workspace/channels/{id}/members): it is refused with 403.' . $person,
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.channels.clear',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/clear',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_clear',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceChannelEraWrite',
            'summary'     => 'Clear the conversation of a channel',
            'description' => 'Starts the conversation of a channel again, as Clear the conversation in its settings does. Nothing is deleted: what was written so far is kept as an earlier version (GET /workspace/channels/{id}/eras) that everybody who reads the channel can read, and the channel gets a line saying so. Requests to agents still waiting there are closed, and nobody keeps an unread count for it. Nothing written since the conversation began is refused with 422 validation_failed; the database not updated yet 503 service_unavailable.' . $staff . $person,
            'params'      => array(
                $id,
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 160, 'description' => 'A name for the version kept, optional; without one it is called by its number.'),
            ),
        ),

        array(
            'id'          => 'workspace.channels.eras.rename',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/eras/{number}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_era_rename',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceChannelEraWrite',
            'summary'     => 'Rename a version of a channel\'s conversation',
            'description' => 'Gives an earlier version of the conversation a name, or takes it off with an empty title, as Rename in the channel\'s list of versions does. number is the version\'s number (GET /workspace/channels/{id}/eras); one the channel does not have answers 404.' . $staff . $person,
            'params'      => array(
                $id,
                array('name' => 'number', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 160, 'required' => true, 'description' => 'The new name; empty takes it off.'),
            ),
        ),
    );
}

/**
 * Who starts and who stops reading a channel when its kind changes: making
 * it public opens it to the team members outside it, making it private
 * closes it to them. Its members read it either way. Pure.
 *
 * @param string $kind     the channel's kind now
 * @param string $to       public or private
 * @param int[]  $team     the team's ids (ws_team_ids())
 * @param int[]  $members  the channel's members
 * @return array gaining, losing (user ids)
 */
function ws_api_channel_kind_forecast($kind, $to, $team, $members)
{
    $members = array_map('intval', (array) $members);
    $outside = array();

    foreach ((array) $team as $user_id) {
        $user_id = (int) $user_id;

        if (($user_id > 0) && !in_array($user_id, $members, true) && !in_array($user_id, $outside, true)) {
            $outside[] = $user_id;
        }
    }

    sort($outside);

    if ((string) $kind === (string) $to) {
        return array('gaining' => array(), 'losing' => array());
    }

    return array(
        'gaining' => (($kind === 'private') && ($to === 'public')) ? $outside : array(),
        'losing'  => (($kind === 'public') && ($to === 'private')) ? $outside : array(),
    );
}

/**
 * People by id as {user_id, name}, in the order given.
 *
 * @param int[] $ids
 * @return array[]
 */
function ws_api_channel_access_people($ids)
{
    $ids = array_values(array_map('intval', (array) $ids));
    $people = empty($ids) ? array() : ws_people($ids);
    $out = array();

    foreach ($ids as $user_id) {
        $out[] = array('user_id' => $user_id, 'name' => (string) ($people[$user_id]['name'] ?? ''));
    }

    return $out;
}

function ws_api_channel_kind($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can make a channel public or private; an application cannot do it for them.'));

    $to = (string) $params['kind'];

    if (!ws_can_change_channel_kind($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('Only staff who are in the channel can make it public or private.'));
    }

    $members = array();

    foreach ((array) db_items("SELECT user_id FROM ws_channel_members WHERE channel_id = '" . (int) $channel['id'] . "'") as $row) {
        $members[] = (int) $row['user_id'];
    }

    $forecast = ws_api_channel_kind_forecast((string) $channel['kind'], $to, ws_team_ids(), $members);
    $answer = array(
        'channel_id' => (int) $channel['id'],
        'kind'       => $to,
        'changed'    => ((string) $channel['kind'] !== $to),
        'gaining'    => ws_api_channel_access_people($forecast['gaining']),
        'losing'     => ws_api_channel_access_people($forecast['losing']),
    );

    api_dry_run_stop($answer['changed'] ? 'changed' : 'unchanged', 'workspace_channel', $answer);

    if ($answer['changed']) {
        // The panel's writers log in the name of the signed-in person.
        $result = ws_api_as_owner(function () use ($viewer, $channel, $to) {
            return ($to === 'public') ? ws_channel_make_public($viewer, $channel) : ws_channel_make_private($viewer, $channel);
        });

        if (!$result['ok']) {
            api_fail(403, 'forbidden', $result['error']);
        }
    }

    api_ok($answer);
}

// What ws_api_channel_kind() answers.
function ws_api_channel_kind_change_schema()
{
    return array(
        'channel_id' => 'integer',
        'kind'       => 'string',
        'changed'    => 'boolean',
        'gaining'    => 'WorkspacePerson[]',
        'losing'     => 'WorkspacePerson[]',
    );
}

function ws_api_channel_join($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can join a channel; an application cannot join it for them.'));

    if ((string) $channel['kind'] !== 'public') {
        api_fail(403, 'forbidden', lang('Only a public channel can be joined; a private one is joined when somebody in it adds you.'));
    }

    api_dry_run_stop(ws_channel_membership($channel['id'], $viewer['id']) ? 'unchanged' : 'joined', 'workspace_channel', array('id' => (int) $channel['id']));

    $result = ws_channel_join($viewer, $channel);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(ws_api_channel_present(ws_api_channel_brief($viewer, ws_channel($channel['id']))));
}

/**
 * A version of the conversation as a clear or a rename answers it. Pure but
 * for lang().
 *
 * @param int   $channel_id
 * @param array $era a ws_channel_eras row
 * @return array
 */
function ws_api_channel_era_write_present($channel_id, $era)
{
    $title = (string) ($era['title'] ?? '');

    return array(
        'channel_id' => (int) $channel_id,
        'id'         => (int) $era['id'],
        'number'     => (int) $era['number'],
        'title'      => $title,
        'label'      => ($title !== '') ? $title : lang(array('string' => 'Version {var:1}', 'vars' => (int) $era['number'])),
        'messages'   => (int) ($era['message_count'] ?? 0),
    );
}

// What ws_api_channel_era_write_present() returns.
function ws_api_channel_era_write_schema()
{
    return array(
        'channel_id' => 'integer',
        'id'         => 'integer',
        'number'     => 'integer',
        'title'      => 'string',
        'label'      => 'string',
        'messages'   => 'integer',
    );
}

// The versions need their table (2026.4.5); a site not updated yet waits.
function ws_api_channel_eras_ready()
{
    if (!ws_eras_ready()) {
        api_fail(503, 'service_unavailable', lang('The workspace is not installed yet: the database has to be updated first.'));
    }
}

function ws_api_channel_clear($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can clear a channel\'s conversation; an application cannot do it for them.'));
    ws_api_channel_eras_ready();

    if (!ws_can_clear_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('Only staff who are in the channel can clear its conversation.'));
    }

    $span = ws_channel_clear_span($channel['id']);

    if ($span === null) {
        api_fail_validation(ws_channel_clear_nothing());
    }

    $title = trim((string) ($params['title'] ?? ''));
    $number = (int) db_value("SELECT COALESCE(MAX(number), 0) FROM ws_channel_eras WHERE channel_id = '" . (int) $channel['id'] . "'") + 1;

    api_dry_run_stop('cleared', 'workspace_channel_era', ws_api_channel_era_write_present($channel['id'], array(
        'id' => 0, 'number' => $number, 'title' => $title, 'message_count' => (int) $span['written'],
    )));

    $result = ws_api_as_owner(function () use ($viewer, $channel, $title) {
        return ws_channel_clear($viewer, $channel, $title);
    });

    if (!$result['ok']) {
        api_fail_validation($result['error']);
    }

    api_ok(ws_api_channel_era_write_present($channel['id'], ws_channel_era($channel['id'], (int) $result['era']['id'])));
}

function ws_api_channel_era_rename($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    ws_api_person_only(lang('Only the person, signed in on a device, can rename a version of the conversation; an application cannot do it for them.'));
    ws_api_channel_eras_ready();

    $era = null;

    foreach (ws_channel_eras($channel['id']) as $row) {
        if ((int) $row['number'] === (int) $params['number']) {
            $era = $row;
        }
    }

    if ($era === null) {
        api_fail_not_found(lang('Version of the conversation'));
    }

    if (!ws_can_clear_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('Only staff who are in the channel can clear its conversation.'));
    }

    $title = mb_substr(trim((string) ($params['title'] ?? '')), 0, 160);

    api_dry_run_stop('renamed', 'workspace_channel_era', ws_api_channel_era_write_present($channel['id'], array('title' => $title) + $era));

    $result = ws_channel_era_rename($viewer, $channel, $era, $title);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(ws_api_channel_era_write_present($channel['id'], ws_channel_era($channel['id'], (int) $era['id'])));
}
