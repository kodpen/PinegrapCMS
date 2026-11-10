<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agent channel switch (ws_channels.agent_channel). A channel
 * manager turns a channel into an agent channel with /agent-channel on|off;
 * its decisions then stay apart from the team's: the decisions screen, the
 * dashboard and GET /workspace/decisions leave them out unless asked for
 * (agent_channels include|only) or the channel is named. The channel's own
 * Decisions tab and who may read a decision do not change.
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

    if ((string) ($channel['kind'] ?? '') === 'thread') {
        return array('type' => 'error', 'error' => lang('A discussion follows its channel; switch the channel itself.'));
    }

    $on = ws_agent_channel_is($channel);

    if ($action === 'show') {
        return array('type' => 'ephemeral', 'html' => h(ws_agent_channel_sentence($on)));
    }

    if (!ws_can_manage_channel($viewer, $channel)) {
        return array('type' => 'error', 'error' => lang('Only a manager of this channel may switch it.'));
    }

    $want = ($action === 'on');

    if ($want === $on) {
        return array('type' => 'ephemeral', 'html' => h(ws_agent_channel_sentence($on)));
    }

    db("UPDATE ws_channels SET agent_channel = '" . ($want ? 1 : 0) . "' WHERE id = '" . (int) $channel['id'] . "'");

    $id = ws_message_system((int) $channel['id'], lang(array(
        'string' => $want ? '{var:1} made this an agent channel: its decisions stay apart from the other channels\' decisions.' : '{var:1} turned the agent channel off: its decisions are listed with the other channels\' decisions again.',
        'vars'   => '<@user:' . (int) $viewer['id'] . '>',
    )));

    return array('type' => 'done', 'message_id' => $id);
}
