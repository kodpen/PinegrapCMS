<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the start box "when the flow is mentioned in a channel". A
 * person writes @ and the flow's name in the channel the box names, and the
 * flow starts as its owner, with the message, its author and the channel as
 * the run's values. Only a message a person writes in the panel's composer
 * counts: a flow's post box, an application, Claude, Pinegrap AI or an MCP
 * agent writes through other paths and never starts a flow this way. One
 * message starts at most one flow (the first mentioned), and one person
 * starts at most one flow this way a minute.
 * The box keeps its rule in the flow's anchor (flow_anchors.php) as a row
 * box does: type mention, the event workspace.flow.mentioned kept by
 * ws_event() and matched in watch.php, with the flow it names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The event a mention is kept as, and how long one person waits between two
// starts by a mention.
define('WS_FLOW_MENTION_EVENT', 'workspace.flow.mentioned');
define('WS_FLOW_MENTION_GAP', 60);

// The most of the message's text a run keeps.
define('WS_FLOW_MENTION_TEXT_MAX', 2000);

/**
 * The values a mention box gives {{trigger.<field>}}. Pure.
 *
 * @return array[] key, type, label
 */
function ws_flow_mention_outputs()
{
    $value = function ($key, $type, $label) {
        return array('key' => $key, 'type' => $type, 'label' => $label);
    };

    return array(
        $value('message_id', 'number', lang('Message')),
        $value('text', 'text', lang('What was written')),
        $value('author_id', 'number', lang('The person who wrote it')),
        $value('author_name', 'text', lang('The name of the person who wrote it')),
        $value('channel_id', 'number', lang('Channel')),
        $value('channel_name', 'text', lang('The name of the channel')),
    );
}

/**
 * The rule a mention box's anchor keeps: the event, so ws_events_awaited()
 * reads it as an event rule's, and the channel. Pure.
 *
 * @param array $config ws_flow_anchor_normalize('mention')
 * @return array
 */
function ws_flow_mention_rule($config)
{
    return array('type' => 'mention', 'event' => WS_FLOW_MENTION_EVENT, 'channel_id' => (int) ($config['channel_id'] ?? 0));
}

/**
 * Does a mention rule match a mention (ws_events_rule_matches() compared the
 * event)? A message of its channel; which flow it names is asked by
 * ws_events_start(), which knows the rule's flow. Pure.
 *
 * @param array $rule    type mention: event, channel_id
 * @param array $payload ws_flow_mention_payload()
 * @return bool
 */
function ws_flow_mention_rule_matches($rule, $payload)
{
    return ((int) ($rule['channel_id'] ?? 0) > 0) && ((int) (((array) $payload)['channel_id'] ?? 0) === (int) $rule['channel_id']);
}

/**
 * Is this mention for another flow than the one a waiting rule starts? Only
 * the flow named in the message starts. Pure.
 *
 * @param array $rule
 * @param array $payload
 * @param int   $flow_id the flow the rule starts, 0 for a plain scheduled action
 * @return bool
 */
function ws_flow_mention_other_flow($rule, $payload, $flow_id)
{
    return ((string) ($rule['type'] ?? '') === 'mention')
        && (((int) $flow_id <= 0) || ((int) (((array) $payload)['flow_id'] ?? 0) !== (int) $flow_id));
}

/**
 * Where a text mentions a name as @name: the @ not inside a word or an
 * address, the name in any case, nothing of a word right after it. Pure.
 *
 * @param string $text
 * @param string $name
 * @return int the offset in bytes of the @, -1 for none
 */
function ws_flow_mention_at($text, $name)
{
    $name = trim((string) $name);

    if ($name === '') {
        return -1;
    }

    $pattern = '/(?<![\p{L}\p{N}_.@])@' . preg_quote($name, '/') . '(?![\p{L}\p{N}_])/iu';

    return preg_match($pattern, (string) $text, $match, PREG_OFFSET_CAPTURE) ? (int) $match[0][1] : -1;
}

/**
 * The flow a message mentions first, of those waiting in its channel; at
 * the same place the longer name wins (@Order check report over @Order
 * check). Pure.
 *
 * @param string $text
 * @param array  $flows flow id => name
 * @return int the flow's id, 0 for none
 */
function ws_flow_mention_pick($text, $flows)
{
    $best = 0;
    $best_at = -1;
    $best_length = 0;

    foreach ((array) $flows as $flow_id => $name) {
        $at = ws_flow_mention_at($text, (string) $name);

        if ($at < 0) {
            continue;
        }

        $length = mb_strlen(trim((string) $name));

        if (($best_at < 0) || ($at < $best_at) || (($at === $best_at) && ($length > $best_length))) {
            $best = (int) $flow_id;
            $best_at = $at;
            $best_length = $length;
        }
    }

    return $best;
}

/**
 * What a mention keeps for the flow: the author first (ws_flow_mention_recent()
 * reads it at the start of the JSON), the flow it names, the message, the
 * channel. Pure.
 *
 * @param array  $author  id, name
 * @param array  $channel id, name
 * @param int    $message_id
 * @param int    $flow_id
 * @param string $text    the message as plain text
 * @return array
 */
function ws_flow_mention_payload($author, $channel, $message_id, $flow_id, $text)
{
    return array(
        'author_id'    => (int) ($author['id'] ?? 0),
        'flow_id'      => (int) $flow_id,
        'id'           => (int) $message_id,
        'message_id'   => (int) $message_id,
        'author_name'  => (string) ($author['name'] ?? ''),
        'channel_id'   => (int) ($channel['id'] ?? 0),
        'channel_name' => (string) ($channel['name'] ?? ''),
        'text'         => mb_substr((string) $text, 0, WS_FLOW_MENTION_TEXT_MAX),
    );
}

/**
 * Why a mention box may not start a flow in this channel for somebody, ''
 * when it may: a public or private channel, not archived, that they read.
 * Pure.
 *
 * @param array|null $channel ws_channel()
 * @param bool       $reads   ws_can_read_channel() of the person
 * @return string
 */
function ws_flow_mention_refusal($channel, $reads)
{
    if (!is_array($channel) || !in_array((string) ($channel['kind'] ?? ''), array('public', 'private'), true)
        || ((int) ($channel['archived_at'] ?? 0) > 0) || !$reads) {
        return lang('Choose a channel you may read.');
    }

    return '';
}

/**
 * Checks a mention box with somebody's rights, the owner as they publish
 * and as each mention starts the flow.
 *
 * @param array $viewer
 * @param array $config ws_flow_anchor_normalize('mention')
 * @return array ok, error, rule, next_run_at
 */
function ws_flow_mention_check($viewer, $config)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'rule' => null, 'next_run_at' => 0);
    };

    if (!function_exists('ws_events_ready') || !ws_events_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $channel = ((int) ($config['channel_id'] ?? 0) > 0) ? ws_channel((int) $config['channel_id']) : null;
    $error = ws_flow_mention_refusal($channel, is_array($channel) && ws_can_read_channel($viewer, $channel));

    if ($error !== '') {
        return $fail($error);
    }

    return array('ok' => true, 'error' => '', 'rule' => ws_flow_mention_rule($config), 'next_run_at' => 0);
}

/**
 * How a mention box's start reads: the channel, when the reader may see it.
 * Pure.
 *
 * @param string $channel the channel's name, '' when not shown
 * @return string
 */
function ws_flow_mention_said($channel)
{
    return ((string) $channel === '')
        ? lang('When the flow is mentioned in a channel')
        : lang(array('string' => 'When the flow is mentioned in #{var:1}', 'vars' => (string) $channel));
}

/**
 * The live flows a mention in this channel may start: those whose active
 * anchor keeps a mention rule of the channel, by id with their names.
 *
 * @param int $channel_id
 * @return array flow id => name
 */
function ws_flow_mention_waiting($channel_id)
{
    if (((int) $channel_id <= 0) || !function_exists('ws_flows_ready') || !ws_flows_ready()) {
        return array();
    }

    $flows = array();

    foreach ((array) db_items("SELECT a.flow_id, a.rules, f.name
        FROM ws_scheduled_actions a
        INNER JOIN ws_flows f ON f.id = a.flow_id AND f.status = 'live'
        WHERE a.status = 'active' AND a.flow_id > 0 AND a.rules LIKE '%\"mention\"%'") as $row) {
        foreach ((array) json_decode((string) $row['rules'], true) as $rule) {
            if (is_array($rule) && ((string) ($rule['type'] ?? '') === 'mention') && ws_flow_mention_rule_matches($rule, array('channel_id' => (int) $channel_id))) {
                $flows[(int) $row['flow_id']] = (string) $row['name'];
            }
        }
    }

    return $flows;
}

/**
 * Did this person start a flow by a mention in the last minute? Read from
 * the mentions kept (ws_events_in), whose JSON starts with the author.
 *
 * @param int $user_id
 * @return bool
 */
function ws_flow_mention_recent($user_id)
{
    return (bool) db_value("SELECT id FROM ws_events_in
        WHERE event = '" . e(WS_FLOW_MENTION_EVENT) . "' AND created_at > '" . (time() - WS_FLOW_MENTION_GAP) . "'
        AND payload LIKE '" . e('{"author_id":' . (int) $user_id . ',') . "%'
        LIMIT 1");
}

/**
 * After a person's message was written in the panel: the flow it mentions
 * by name in this channel, if one waits there, is kept for the run
 * (ws_event()), at most once a minute for the person. Called by the
 * composer's send only, so a flow, an application or an agent writing in a
 * channel starts nothing.
 *
 * @param array  $viewer the person who wrote it
 * @param array  $channel
 * @param int    $message_id
 * @param string $body as written
 * @return int the flow kept to start, 0 for none
 */
function ws_flow_mention_after_send($viewer, $channel, $message_id, $body)
{
    if (!function_exists('ws_events_awaited') || !in_array(WS_FLOW_MENTION_EVENT, ws_events_awaited(), true)
        || empty($viewer['member']) || ((int) $message_id <= 0) || (strpos((string) $body, '@') === false)) {
        return 0;
    }

    $text = ws_plain_text($viewer, (string) $body);
    $flow_id = ws_flow_mention_pick($text, ws_flow_mention_waiting((int) $channel['id']));

    if (($flow_id <= 0) || ws_flow_mention_recent((int) $viewer['id'])) {
        return 0;
    }

    $author = array('id' => (int) $viewer['id'], 'name' => ws_person_name((int) $viewer['id']));

    return ws_event('workspace.flow.mentioned', ws_flow_mention_payload($author, $channel, $message_id, $flow_id, $text)) ? $flow_id : 0;
}
