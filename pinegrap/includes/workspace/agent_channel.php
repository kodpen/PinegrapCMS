<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agent channel switch (ws_channels.agent_channel). A channel
 * manager turns a channel into an agent channel with /agent-channel on|off,
 * the Agent channel switch of Channel settings or the New channel form;
 * its decisions then stay apart from the team's: the decisions screen, the
 * dashboard and GET /workspace/decisions leave them out unless asked for
 * (agent_channels include|only) or the channel is named. An agent channel's
 * Decisions tab and its agents' brief read the decisions of every agent
 * channel the reader may read, so a new agent channel starts with what the
 * old ones decided. Who may read a decision does not change.
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
 * Whether ws_channels has the switch: files land before the upgrade runs.
 *
 * @return bool
 */
function ws_agent_channel_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_channels', 'agent_channel');
    }

    return $ready;
}

/**
 * Whether a channel row is an agent channel.
 *
 * @param array|null $channel a ws_channels row
 * @return bool
 */
function ws_agent_channel_is($channel)
{
    return is_array($channel) && !empty($channel['agent_channel']);
}

/**
 * The agent channel filter a request asked for, cleaned: '' leaves agent
 * channels out, include takes them with the others, only takes them alone.
 *
 * @param mixed $value
 * @return string '' | include | only
 */
function ws_agent_channel_mode($value)
{
    $value = strtolower(trim((string) $value));

    return in_array($value, array('include', 'only'), true) ? $value : '';
}

/**
 * The channels a decisions listing looks at under the agent channel filter.
 * A channel named by the request is kept whatever it is, so the channel's
 * own decisions are always reachable by its id.
 *
 * @param array[] $channels keyed by id, each with agent_channel (0 or 1)
 * @param string  $mode     ws_agent_channel_mode()
 * @param int     $named    the channel the request names, 0 for none
 * @return array[] the kept channels, keys kept
 */
function ws_agent_channel_filter($channels, $mode, $named = 0)
{
    $mode = ws_agent_channel_mode($mode);
    $out = array();

    foreach ((array) $channels as $id => $channel) {
        $agent = ws_agent_channel_is($channel);

        if (((int) $named > 0) && ((int) $id === (int) $named)) {
            $out[$id] = $channel;
        } elseif ($mode === 'include') {
            $out[$id] = $channel;
        } elseif (($mode === 'only') ? $agent : !$agent) {
            $out[$id] = $channel;
        }
    }

    return $out;
}

/**
 * The agent channels a person may read, archived ones included (a deleted
 * channel's decisions are gone with it), keyed by id with their names.
 *
 * @param array $viewer
 * @return array[]
 */
function ws_agent_channel_readable($viewer)
{
    if (!ws_agent_channel_ready()) {
        return array();
    }

    return ws_agent_channel_filter(ws_timeline_channels($viewer), 'only');
}

/**
 * Which decisions an agent channel's Decisions tab asked for. Pure.
 *
 * @param mixed $value
 * @return string channel (this channel's) | agents (every agent channel's)
 */
function ws_agent_channel_scope($value)
{
    return (strtolower(trim((string) $value)) === 'agents') ? 'agents' : 'channel';
}

/**
 * The channels whose decisions a channel's list reads: every readable agent
 * channel when an agent channel asks for them all, otherwise the channel
 * alone. The channel itself is always kept. Pure.
 *
 * @param array   $channel  a ws_channels row
 * @param array[] $readable ws_agent_channel_readable(), keyed by id
 * @param string  $scope    ws_agent_channel_scope()
 * @return int[]
 */
function ws_agent_channel_decision_ids($channel, $readable, $scope)
{
    $id = (int) ($channel['id'] ?? 0);

    if (!ws_agent_channel_is($channel) || (ws_agent_channel_scope($scope) !== 'agents')) {
        return array($id);
    }

    $ids = array_map('intval', array_keys((array) $readable));

    if (!in_array($id, $ids, true)) {
        array_unshift($ids, $id);
    }

    return array_values(array_unique($ids));
}

/**
 * Names the channel of each decision read from another agent channel, so the
 * Decisions tab says where it was made. Pure.
 *
 * @param array[] $messages ws_message_payload() rows
 * @param int     $channel_id the channel the tab belongs to
 * @param array[] $readable   ws_agent_channel_readable(), keyed by id
 * @return array[] each with channel_name ('' for the channel's own)
 */
function ws_agent_channel_decision_names($messages, $channel_id, $readable)
{
    foreach ((array) $messages as $index => $message) {
        $id = (int) ($message['channel_id'] ?? 0);
        $messages[$index]['channel_name'] = (($id !== (int) $channel_id) && isset($readable[$id])) ? (string) ($readable[$id]['name'] ?? '') : '';
    }

    return (array) $messages;
}

/**
 * Switches a channel to an agent channel or back: a channel manager only,
 * never a discussion; a system line names who switched it. The command,
 * Channel settings and the New channel form all come here.
 *
 * @param array $viewer
 * @param array $channel
 * @param bool  $want
 * @return array ok, error, changed, message_id
 */
function ws_agent_channel_set($viewer, $channel, $want)
{
    $out = array('ok' => false, 'error' => '', 'changed' => false, 'message_id' => 0);

    if (!ws_agent_channel_ready()) {
        $out['error'] = lang('The database has to be updated first.');
        return $out;
    }

    if ((string) ($channel['kind'] ?? '') === 'thread') {
        $out['error'] = lang('A discussion follows its channel; switch the channel itself.');
        return $out;
    }

    if (!ws_can_manage_channel($viewer, $channel)) {
        $out['error'] = lang('Only a manager of this channel may switch it.');
        return $out;
    }

    $out['ok'] = true;
    $want = (bool) $want;

    if ($want === ws_agent_channel_is($channel)) {
        return $out;
    }

    db("UPDATE ws_channels SET agent_channel = '" . ($want ? 1 : 0) . "' WHERE id = '" . (int) $channel['id'] . "'");

    $out['changed'] = true;
    $out['message_id'] = ws_message_system((int) $channel['id'], lang(array(
        'string' => $want ? '{var:1} made this an agent channel: its decisions stay apart from the other channels\' decisions.' : '{var:1} turned the agent channel off: its decisions are listed with the other channels\' decisions again.',
        'vars'   => '<@user:' . (int) $viewer['id'] . '>',
    )));

    return $out;
}

/**
 * What the text after /agent-channel asks for.
 *
 * @param string $text
 * @return string|null show (bare), on, off; null for anything else
 */
function ws_agent_channel_parse($text)
{
    $word = ws_command_fold(trim((string) $text));

    if ($word === '') {
        return 'show';
    }

    if (in_array($word, array('on', 'ac', 'acik'), true)) {
        return 'on';
    }

    if (in_array($word, array('off', 'kapat', 'kapali'), true)) {
        return 'off';
    }

    return null;
}

/**
 * The sentence that says a channel's state.
 *
 * @param bool $on
 * @return string
 */
function ws_agent_channel_sentence($on)
{
    return $on
        ? lang('This is an agent channel: its decisions stay apart from the other channels\' decisions.')
        : lang('This is not an agent channel: its decisions are listed with the other channels\' decisions.');
}

/**
 * /agent-channel on|off: a channel manager switches it, with a system line
 * naming who did; bare, it says the state to the person alone.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $text
 * @return array a ws_command_run() answer
 */
function ws_command_agent_channel($viewer, $channel, $text)
{
    if (!ws_agent_channel_ready()) {
        return array('type' => 'error', 'error' => lang('The database has to be updated first.'));
    }

    $action = ws_agent_channel_parse($text);

    if ($action === null) {
        return array('type' => 'error', 'error' => lang('Write on or off after /agent-channel, or nothing to see its state.'));
    }

    if ($action === 'show') {
        if ((string) ($channel['kind'] ?? '') === 'thread') {
            return array('type' => 'error', 'error' => lang('A discussion follows its channel; switch the channel itself.'));
        }

        return array('type' => 'ephemeral', 'html' => h(ws_agent_channel_sentence(ws_agent_channel_is($channel))));
    }

    $result = ws_agent_channel_set($viewer, $channel, $action === 'on');

    if (!$result['ok']) {
        return array('type' => 'error', 'error' => $result['error']);
    }

    if (!$result['changed']) {
        return array('type' => 'ephemeral', 'html' => h(ws_agent_channel_sentence(ws_agent_channel_is($channel))));
    }

    return array('type' => 'done', 'message_id' => $result['message_id']);
}
