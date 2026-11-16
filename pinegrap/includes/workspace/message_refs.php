<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a message pointed at from another text: the tag <#message:N>.
 *
 * The tag is a reference, not an answer: it opens no discussion, wakes no
 * agent and tells nobody. Each reader sees it as their own rights allow: a
 * reader of the message's channel gets its sender, time, channel, kind and a
 * short text; anybody else only its number and "You cannot see this
 * message."; a message deleted, or deleted by the reader for themselves,
 * says it was deleted. The popup the screen opens on the tag reads the
 * message's own payload (ws_message_payloads()) behind the same right.
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
 * The line every agent's instructions carry: a question or an answer about a
 * picture, a file or an earlier message names it with its tag, so the person
 * reading sees which one it means (the tag opens it) instead of guessing at
 * "the picture above".
 *
 * @return string
 */
function ws_message_ref_agent_rule()
{
    return 'When you ask or answer about a picture, a file or an earlier message, point at it with its tag <#message:ID> (the message\'s id), never only with words such as "the picture above": the reader opens the message from the tag. It is a reference, not a reply: nobody is told by it.';
}

/**
 * The most characters of a referenced message's words its tag carries.
 */
const WS_MESSAGE_REF_TEXT = 140;

/**
 * The tag's words: the message's number. Pure.
 *
 * @param int $id
 * @return string
 */
function ws_message_ref_label($id)
{
    return lang(array('string' => 'Message #{var:1}', 'vars' => (int) $id));
}

/**
 * Words cut to a length on one line, with an ellipsis when cut. Pure.
 *
 * @param string $text
 * @param int    $length
 * @return string
 */
function ws_message_ref_cut($text, $length = WS_MESSAGE_REF_TEXT)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    if (mb_strlen($text) > $length) {
        $text = rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }

    return $text;
}

/**
 * What a message tag looks like to one reader, built from what was read for
 * them. Pure: the reader's right is decided before (ws_message_refs_resolve()).
 *
 * @param int    $id
 * @param string $state shown (the reader reads its channel), hidden (they do
 *                      not), gone (deleted, or never there)
 * @param array  $info  for shown: channel_id, channel (its name), sender,
 *                      timestamp, kind, file_kind, poll, text
 * @param string $base  the software directory's address, with its slash
 * @return array a ws_refs_resolve() entry; shown also carries message (what
 *               the popup heads with) and every state plain (the words of
 *               ws_plain_text())
 */
function ws_message_ref($id, $state, $info = array(), $base = '')
{
    $id = (int) $id;
    $label = ws_message_ref_label($id);
    $ref = array(
        'type'   => 'message',
        'id'     => $id,
        'label'  => $label,
        'url'    => '',
        'meta'   => '',
        'status' => '',
        'known'  => false,
        'icon'   => 'bi-chat-quote',
    );

    if ($state === 'gone') {
        $ref['meta'] = lang('This message was deleted.');
        $ref['icon'] = 'bi-x-circle';
        $ref['gone'] = true;
        $ref['plain'] = $label . ' (' . $ref['meta'] . ')';

        return $ref;
    }

    if ($state !== 'shown') {
        $ref['meta'] = lang('You cannot see this message.');
        $ref['plain'] = $label . ' (' . $ref['meta'] . ')';

        return $ref;
    }

    $channel = '#' . (string) ($info['channel'] ?? '');
    $sender = (string) ($info['sender'] ?? '');
    $timestamp = (int) ($info['timestamp'] ?? 0);
    $time = ($timestamp > 0) ? ws_time_label($timestamp) : '';
    $text = ws_message_ref_cut((string) ($info['text'] ?? ''));

    $ref['known'] = true;
    $ref['url'] = $base . 'workspace.php?channel=' . (int) ($info['channel_id'] ?? 0) . '&message=' . $id;
    $ref['meta'] = implode(' · ', array_filter(array($sender, $channel, $time), 'strlen'));
    $ref['message'] = array(
        'id'         => $id,
        'channel_id' => (int) ($info['channel_id'] ?? 0),
        'channel'    => (string) ($info['channel'] ?? ''),
        'sender'     => $sender,
        'time'       => $time,
        'timestamp'  => $timestamp,
        'kind'       => (string) ($info['kind'] ?? 'message'),
        // image, video, audio, pdf or file for a message carrying one, '' for none.
        'file_kind'  => (string) ($info['file_kind'] ?? ''),
        'poll'       => !empty($info['poll']),
        'text'       => $text,
    );

    $who = ($sender !== '') ? lang(array('string' => '{var:1} in {var:2}', 'vars' => array($sender, $channel))) : $channel;
    $ref['plain'] = $label . ' (' . $who . (($text !== '') ? ': “' . $text . '”' : '') . ')';

    return $ref;
}

/**
 * The message tags of a set, for this reader: one query for the messages,
 * one for the people who wrote them, one for the ones the reader deleted for
 * themselves and one for polls, however many tags there are.
 *
 * The words of a referenced message have their own tags resolved for the
 * same reader; a message tag inside them is read as its number alone, so two
 * messages pointing at each other end there.
 *
 * @param array $viewer
 * @param int[] $ids
 * @return array "message:id" => ws_message_ref()
 */
function ws_message_refs_resolve($viewer, $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    $out = array();

    if (empty($ids)) {
        return $out;
    }

    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $rows = array();

    foreach ((array) db_items("SELECT id, channel_id, sender_kind, sender_id, kind, body, file_id, file_name, deleted_at, created_at
        FROM ws_messages WHERE id IN (" . implode(',', $ids) . ")") as $row) {
        $rows[(int) $row['id']] = $row;
    }

    // Whether the reader reads each channel, and the name it is known by: a
    // discussion is named after the channel it is about.
    $channels = array();
    $shown = array();

    foreach ($rows as $id => $row) {
        $channel_id = (int) $row['channel_id'];

        if (!array_key_exists($channel_id, $channels)) {
            $channel = ws_channel($channel_id);
            $channels[$channel_id] = ($channel && ws_can_read_channel($viewer, $channel)) ? $channel : null;
        }

        if ($channels[$channel_id] && ((int) $row['deleted_at'] === 0)) {
            $shown[$id] = $row;
        }
    }

    $hidden = ws_message_hidden_ids((int) $viewer['id'], array_keys($shown));

    foreach (array_keys($hidden) as $id) {
        unset($shown[$id]);
    }

    $people = ws_people(array_map(function ($row) {
        return ($row['sender_kind'] === 'user') ? (int) $row['sender_id'] : 0;
    }, $shown));

    $polls = array();

    if (!empty($shown) && function_exists('ws_interact_ready') && ws_interact_ready()) {
        foreach ((array) db_values("SELECT message_id FROM ws_polls WHERE message_id IN (" . implode(',', array_keys($shown)) . ")") as $message_id) {
            $polls[(int) $message_id] = true;
        }
    }

    // The tags inside the referenced words, other message tags aside.
    $nested = array();
    $nested_messages = array();

    foreach ($shown as $row) {
        foreach (ws_tokens($row['body']) as $token) {
            if ($token['type'] === 'message') {
                $nested_messages[(int) $token['id']] = true;
            } else {
                $nested[] = $token;
            }
        }
    }

    $refs = empty($nested) ? array() : ws_refs_resolve($viewer, $nested);

    foreach (array_keys($nested_messages) as $id) {
        $refs['message:' . $id] = array('type' => 'message', 'id' => $id, 'label' => ws_message_ref_label($id), 'url' => '', 'meta' => '', 'status' => '', 'known' => false, 'icon' => 'bi-chat-quote');
    }

    foreach ($ids as $id) {
        $row = $rows[$id] ?? null;

        if (!$row || ((int) $row['deleted_at'] > 0) || isset($hidden[$id])) {
            // A message of a channel the reader cannot open stays unknown,
            // deleted or not: they learn nothing of it.
            $state = ($row && !$channels[(int) $row['channel_id']]) ? 'hidden' : 'gone';
            $out['message:' . $id] = ws_message_ref($id, $state);
            continue;
        }

        if (!isset($shown[$id])) {
            $out['message:' . $id] = ws_message_ref($id, 'hidden');
            continue;
        }

        $channel = $channels[(int) $row['channel_id']];
        $parent = ws_channel_is_thread($channel) ? ws_thread_parent_channel($channel) : null;
        $sender = ($row['sender_kind'] === 'mail') ? array('name' => lang('E-mail')) : ws_forward_sender($row['sender_kind'], $row['sender_id'], $people);
        $text = ws_plain_line($viewer, (string) $row['body'], $refs);
        $file_kind = ((int) $row['file_id'] > 0) ? ws_file_kind($row['file_name']) : '';

        $out['message:' . $id] = ws_message_ref($id, 'shown', array(
            'channel_id' => (int) $row['channel_id'],
            'channel'    => (string) ($parent ? $parent['name'] : $channel['name']),
            'sender'     => $sender ? (string) $sender['name'] : '',
            'timestamp'  => (int) $row['created_at'],
            'kind'       => (string) $row['kind'],
            'file_kind'  => $file_kind,
            'poll'       => isset($polls[$id]),
            'text'       => ($text !== '') ? $text : (string) $row['file_name'],
        ), $base);
    }

    return $out;
}

/**
 * The referenced message as the channel draws it, for the tag's popup: the
 * same right as the tag (ws_message_refs_resolve()), then the message's own
 * payload (ws_message_payloads()), which the reader's deletion for
 * themselves also leaves out.
 *
 * @param array $viewer
 * @param int   $id
 * @return array ref (ws_message_ref()), message (the payload, null unless
 *               the reader may see it)
 */
function ws_message_ref_popup($viewer, $id)
{
    $id = (int) $id;
    $ref = ws_message_refs_resolve($viewer, array($id))['message:' . $id] ?? ws_message_ref($id, 'gone');

    if (empty($ref['known'])) {
        return array('ref' => $ref, 'message' => null);
    }

    $row = ws_message($id);
    $payload = $row ? ws_message_payloads($viewer, array($row)) : array();

    if (empty($payload) || !empty($payload[0]['deleted'])) {
        return array('ref' => ws_message_ref($id, 'gone'), 'message' => null);
    }

    return array('ref' => $ref, 'message' => $payload[0]);
}

/**
 * The texts of the message tag's card in workspace.js.
 *
 * @return array key => text
 */
function ws_message_ref_js_strings()
{
    return array(
        'mr_title' => lang('Referenced message'),
        'mr_go'    => lang('Go to the message'),
    );
}
