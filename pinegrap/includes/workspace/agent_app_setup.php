<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an application made an agent from Application Access: the
 * Agent tab of the application's drawer and the "Agent in a channel"
 * starting point of New application. The agent's row is checked and written
 * by the Agents card's own functions (ws_agents_settings_check(),
 * ws_agents_settings_write()) and each channel row by the single add of
 * "Agents in this channel" (ws_channel_agent_set_one()), so the same rules
 * and rights hold: the agent is added by an administrator, a channel row by
 * whoever may bring the agent into that channel.
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
 * What keeps a person from making an application an agent, '' when nothing
 * does. Pure.
 *
 * @param bool       $ready the Workspace is on and its agents' tables are there
 * @param int        $role  the person's role (0 administrator)
 * @param array|null $app   kind, has_secret, locked (the owner outranks the person)
 * @return string '' | workspace_off | admin_only | not_found | kind | no_secret | locked
 */
function ws_agent_app_setup_refusal($ready, $role, $app)
{
    if (!$ready) {
        return 'workspace_off';
    }

    if ((int) $role !== 0) {
        return 'admin_only';
    }

    if (!is_array($app)) {
        return 'not_found';
    }

    if ((string) ($app['kind'] ?? '') !== 'server') {
        return 'kind';
    }

    if (empty($app['has_secret'])) {
        return 'no_secret';
    }

    if (!empty($app['locked'])) {
        return 'locked';
    }

    return '';
}

/**
 * The sentence of a ws_agent_app_setup_refusal() code.
 *
 * @param string $code
 * @return string
 */
function ws_agent_app_setup_refusal_text($code)
{
    switch ((string) $code) {
        case 'workspace_off':
            return lang('An agent answers in Workspace channels: switch the Workspace on first.');
        case 'admin_only':
            return lang('Only an administrator can make an application an agent.');
        case 'not_found':
            return lang('That application could not be found.');
        case 'kind':
            return lang('Only an integration with a key and a secret can be an agent: a connection made by signing in acts as the person.');
        case 'no_secret':
            return lang('This application was made without a secret, so an agent cannot sign in with it.');
        case 'locked':
            return lang('This application belongs to an account above yours, so only that account or one above it can change it.');
    }

    return lang('Invalid request.');
}

/**
 * The tab's and the sheet's posted fields, each a scalar or a list of
 * channel ids. Pure.
 *
 * @param array $source agent_handle, agent_name, agent_role, agent_door_kind,
 *                      agent_door_url, agent_channels[] and agent_channels_sent
 *                      (the list was drawn, so an empty one unticks every row)
 * @return array handle, name, role, door_kind, door_url, channels (int[]), channels_sent (bool)
 */
function ws_agent_app_setup_posted($source)
{
    $source = is_array($source) ? $source : array();
    $text = function ($name) use ($source) {
        $value = $source[$name] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    };
    $channels = array();

    foreach ((is_array($source['agent_channels'] ?? null) ? $source['agent_channels'] : array()) as $id) {
        if (is_scalar($id) && ((int) $id > 0)) {
            $channels[(int) $id] = (int) $id;
        }
    }

    sort($channels);

    return array(
        'handle'        => $text('agent_handle'),
        'name'          => $text('agent_name'),
        'role'          => $text('agent_role'),
        'door_kind'     => ($text('agent_door_kind') === 'push') ? 'push' : 'poll',
        'door_url'      => $text('agent_door_url'),
        'channels'      => array_values($channels),
        'channels_sent' => ($text('agent_channels_sent') !== ''),
    );
}

/**
 * The Agents card's form fields for the agent of an application: what the
 * tab posts over what the agent has, so its duty, schedule, status, context
 * window size and sealed door secret stay as they are. A new agent is an active poll agent
 * with no schedule. Pure.
 *
 * @param int        $app_id
 * @param array|null $current ws_agent_shape() of the application's agent, null for none
 * @param array      $posted  ws_agent_app_setup_posted()
 * @return array ws_agents_settings_input()
 */
function ws_agent_app_setup_fields($app_id, $current, $posted)
{
    $current = is_array($current) ? $current : null;
    $door_kind = (string) $posted['door_kind'];

    return array(
        'kind'              => 'app',
        'handle'            => (string) $posted['handle'],
        'name'              => (string) $posted['name'],
        'role'              => (string) $posted['role'],
        'duty'              => $current ? (string) $current['duty'] : '',
        'app_id'            => (string) (int) $app_id,
        'provider'          => '',
        'model'             => '',
        'status'            => $current ? (string) $current['status'] : 'active',
        'door_kind'         => $door_kind,
        'door_url'          => ($door_kind === 'push') ? (string) $posted['door_url'] : '',
        'base'              => '',
        'wake_every'        => $current ? (string) (int) $current['wake_every'] : '0',
        'wake_note'         => $current ? (string) $current['wake_note'] : '',
        'key'               => '',
        'key_clear'         => '',
        'door_secret'       => '',
        'door_secret_clear' => '',
        'token_limit'       => '',
        // The context window's size is the Agents card's; this tab keeps it.
        'context_size'      => ($current && ((int) ($current['context_size'] ?? 0) > 0)) ? (string) (int) $current['context_size'] : '',
    );
}

/**
 * Whether checked values change nothing of the agent there is, so a second
 * save writes no row and no Site Log line. Pure.
 *
 * @param array      $values  ws_agent_save_check()'s values
 * @param array|null $current ws_agent_shape()
 * @return bool
 */
function ws_agent_app_setup_same($values, $current)
{
    if (!is_array($current)) {
        return false;
    }

    foreach (array('kind', 'handle', 'name', 'role', 'duty', 'app_id', 'status', 'door_kind', 'door_url', 'wake_every', 'wake_note') as $field) {
        if ((string) ($values[$field] ?? '') !== (string) ($current[$field] ?? '')) {
            return false;
        }
    }

    return true;
}

/**
 * The channel rows a save adds and takes out: only in channels the person
 * may manage (a row elsewhere is left as it is), never one already there or
 * already gone. Pure.
 *
 * @param int[] $ticked     the channels ticked
 * @param int[] $have       the channels the agent is in
 * @param int[] $manageable the channels the list offered
 * @return array add (int[]), remove (int[])
 */
function ws_agent_app_setup_channel_diff($ticked, $have, $manageable)
{
    $ints = function ($list) {
        $out = array_values(array_unique(array_map('intval', (array) $list)));
        sort($out);

        return $out;
    };
    $ticked = $ints($ticked);
    $have = $ints($have);
    $manageable = $ints($manageable);

    return array(
        'add'    => array_values(array_diff(array_intersect($ticked, $manageable), $have)),
        'remove' => array_values(array_diff(array_intersect($have, $manageable), $ticked)),
    );
}

/**
 * The application's agent: the first app agent working through it in id
 * order, as its key resolves to that one (app-agent-own-application), or
 * null.
 *
 * @param int $app_id
 * @return array|null ws_agent_shape()
 */
function ws_agent_app_setup_agent($app_id)
{
    if (!ws_agents_ready() || ((int) $app_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_agents WHERE kind = 'app' AND app_id = '" . (int) $app_id . "' ORDER BY id LIMIT 1");

    return $row ? ws_agent_shape($row) : null;
}

/**
 * The channels the person may manage, which the tab lists: open,
 * neither a discussion nor a mailbox, by name.
 *
 * @param array $viewer ws_viewer()
 * @return array[] id, name, kind
 */
function ws_agent_app_setup_channels($viewer)
{
    if (empty($viewer['member'])) {
        return array();
    }

    $out = array();

    foreach (ws_channels_for($viewer) as $brief) {
        $channel = ws_channel((int) $brief['id']);

        if ($channel && ws_can_manage_channel($viewer, $channel)) {
            $out[] = array('id' => (int) $channel['id'], 'name' => (string) $channel['name'], 'kind' => (string) $channel['kind']);
        }
    }

    return $out;
}

/**
 * The channels an agent has a row in.
 *
 * @param int $agent_id
 * @return int[]
 */
function ws_agent_app_setup_agent_channels($agent_id)
{
    if (!ws_agents_ready() || ((int) $agent_id <= 0)) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT channel_id FROM ws_channel_agents WHERE agent_id = '" . (int) $agent_id . "' AND allowed = 1 ORDER BY channel_id"));
}

/**
 * Checks the agent part before anything is written. A refusal names its
 * part: rights (who may), agent (a field of the Agents card's form, named
 * in field). The channels are checked one by one as they are written.
 *
 * @param array $viewer ws_viewer() with role
 * @param array $app    id, kind, has_secret, locked
 * @param array $posted ws_agent_app_setup_posted()
 * @return array ok, part, error, field; when ok also checked (ws_agents_settings_check()),
 *               current, same (nothing to write for the agent), posted
 */
function ws_agent_app_setup_check($viewer, $app, $posted)
{
    $refusal = ws_agent_app_setup_refusal(ws_enabled() && ws_agents_ready(), (int) ($viewer['role'] ?? 3), $app);

    if ($refusal !== '') {
        return array('ok' => false, 'part' => 'rights', 'error' => ws_agent_app_setup_refusal_text($refusal), 'field' => '');
    }

    $current = ws_agent_app_setup_agent((int) $app['id']);
    $checked = ws_agents_settings_check($viewer, $current ? (int) $current['id'] : 0, ws_agent_app_setup_fields((int) $app['id'], $current, $posted));

    if (!$checked['ok']) {
        return array('ok' => false, 'part' => 'agent', 'error' => $checked['error'], 'field' => (string) $checked['field']);
    }

    return array(
        'ok'      => true,
        'part'    => '',
        'error'   => '',
        'field'   => '',
        'checked' => $checked,
        'current' => $current,
        'same'    => ws_agent_app_setup_same($checked['values'], $current),
        'posted'  => $posted,
    );
}

/**
 * Writes what ws_agent_app_setup_check() passed: the agent (only when
 * something changed), then each channel row the ticks add or take out. A
 * channel that refuses is named and the rest go on; what was written stays.
 *
 * @param array $viewer
 * @param array $ok ws_agent_app_setup_check(), ok
 * @return array agent_id, agent_written (bool), added (int[]), removed (int[]),
 *               refused (array[]: channel_id, name, error)
 */
function ws_agent_app_setup_write($viewer, $ok)
{
    $agent_id = $ok['current'] ? (int) $ok['current']['id'] : 0;
    $written = false;

    if (!$ok['same']) {
        $agent_id = (int) ws_agents_settings_write($viewer, $ok['checked']);
        $written = true;
    }

    $out = array('agent_id' => $agent_id, 'agent_written' => $written, 'added' => array(), 'removed' => array(), 'refused' => array());
    $agent = ws_agent($agent_id);

    if (!$agent || !$ok['posted']['channels_sent']) {
        return $out;
    }

    $offered = array();

    foreach (ws_agent_app_setup_channels($viewer) as $channel) {
        $offered[$channel['id']] = $channel['name'];
    }

    $diff = ws_agent_app_setup_channel_diff($ok['posted']['channels'], ws_agent_app_setup_agent_channels($agent_id), array_keys($offered));

    foreach ($diff['add'] as $channel_id) {
        $channel = ws_channel($channel_id);
        $problem = $channel ? ws_channel_agent_add_problem($viewer, $channel) : 'forbidden';
        $result = ($problem === '') ? ws_channel_agent_set_one($viewer, $channel, $agent, array())
            : array('error' => ($problem === 'forbidden') ? lang('You cannot post in that channel.') : ws_channel_agent_error_text($problem));

        if ($result['error'] === '') {
            $out['added'][] = $channel_id;
        } else {
            $out['refused'][] = array('channel_id' => $channel_id, 'name' => $offered[$channel_id], 'error' => $result['error']);
        }
    }

    foreach ($diff['remove'] as $channel_id) {
        if (ws_channel_agent_remove($channel_id, $agent_id)) {
            $out['removed'][] = $channel_id;
        }
    }

    return $out;
}

/**
 * The sentences a save leaves on the screen: what was done and, for a part
 * that was refused, which part and where to finish it, escaped for the
 * liveform's notices. Pure but for lang() and h().
 *
 * @param array $result ws_agent_app_setup_write()
 * @param string $handle the agent's handle
 * @return array notices (string[]), errors (string[])
 */
function ws_agent_app_setup_messages($result, $handle)
{
    $notices = array();
    $errors = array();

    if ($result['agent_written']) {
        $notices[] = lang(array('string' => 'The agent @{var:1} was saved.', 'vars' => h($handle)));
    }

    if ($result['added'] || $result['removed']) {
        $notices[] = lang(array('string' => 'Channels: {var:1} added, {var:2} taken out.', 'vars' => array(count($result['added']), count($result['removed']))));
    }

    foreach ($result['refused'] as $refused) {
        $errors[] = lang(array('string' => 'Channel #{var:1} was not added: {var:2} Finish it in that channel\'s settings, under Agents in this channel.', 'vars' => array(h($refused['name']), h($refused['error']))));
    }

    if (!$notices && !$errors) {
        $notices[] = lang('Nothing changed.');
    }

    return array('notices' => $notices, 'errors' => $errors);
}
