<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the lines a call writes into its channel: who started it,
 * that it ended with its length and who took part, and in a conversation
 * of two a missed or declined call naming the one who did not answer.
 *
 * They are system lines (ws_message_system()): they move the channel's
 * last activity, but they are never sent as a person's message, so no
 * agent is woken, asked or answered by them and no unread count grows.
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
 * A call's length as the line shows it: 0:42, 12:05, 1:02:09. Pure.
 *
 * @param int $seconds
 * @return string
 */
function ws_call_line_length($seconds)
{
    $seconds = max(0, (int) $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $rest = str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);

    return ($hours > 0) ? $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ':' . $rest : $minutes . ':' . $rest;
}

/**
 * The parties who were ever in a call, once each, in the order they first
 * joined. Pure.
 *
 * @param array[] $members ws_call_members rows (left ones too)
 * @return string[] 'user:<id>' / 'guest:<id>'
 */
function ws_call_line_parties($members)
{
    $rows = array_values((array) $members);

    usort($rows, function ($a, $b) {
        return ((int) $a['joined_at'] - (int) $b['joined_at']) ?: ((int) $a['id'] - (int) $b['id']);
    });

    $parties = array();

    foreach ($rows as $row) {
        $key = ((int) $row['guest_id'] > 0) ? 'guest:' . (int) $row['guest_id'] : 'user:' . (int) $row['user_id'];

        if (!in_array($key, $parties, true)) {
            $parties[] = $key;
        }
    }

    return $parties;
}

/**
 * One party as a line names it: a person by the mention tag the channel
 * draws as their name, a guest by the name the room knows, without
 * markup. Pure.
 *
 * @param string   $key   'user:<id>' / 'guest:<id>'
 * @param string[] $names 'guest:<id>' => name
 * @return string
 */
function ws_call_line_party_word($key, $names)
{
    list($kind, $id) = array_pad(explode(':', (string) $key, 2), 2, '0');

    if ($kind === 'user') {
        return '<@user:' . (int) $id . '>';
    }

    $name = trim(str_replace(array('<', '>', '*', '_', '`', '[', ']', '#', '~'), '', (string) ($names[(string) $key] ?? '')));

    return ($name !== '') ? $name : lang('A guest');
}

/**
 * Which line an ended call writes. Pure.
 *
 * missed: in a conversation of two (not a guest room), nobody but its
 * starter was ever in it and the other person did not decline it;
 * declined: the other person declined it; ended: everything else, a group
 * call nobody joined too.
 *
 * @param string[] $parties      ws_call_line_parties()
 * @param array    $call         the ws_calls row
 * @param int      $people       people with a membership row in the channel
 * @param string   $channel_kind
 * @param int      $declined_by  the user who declined, 0 for none
 * @return string ended | missed | declined
 */
function ws_call_line_end_kind($parties, $call, $people, $channel_kind, $declined_by)
{
    $alone = ((array) $parties === array('user:' . (int) $call['started_by']));

    if (!$alone || ((int) $people !== 2) || ((string) $channel_kind === 'guest')) {
        return 'ended';
    }

    return ((int) $declined_by > 0) ? 'declined' : 'missed';
}

/**
 * The words of the line a call writes when it starts. Pure.
 *
 * @param string $kind    voice | video
 * @param string $starter the starter's word (ws_call_line_party_word())
 * @return string
 */
function ws_call_line_started_text($kind, $starter)
{
    $string = ((string) $kind === 'video') ? '{var:1} started a video call.' : '{var:1} started a voice call.';

    return lang(array('string' => $string, 'vars' => array((string) $starter)));
}

/**
 * The words of the line a call writes when it ends. Pure.
 *
 * @param string   $line   ws_call_line_end_kind()
 * @param string   $kind   voice | video
 * @param int      $length seconds from its start to its end
 * @param string[] $words  the parties' words, the starter first
 * @param string   $other  in a conversation of two, the word of the one
 *                         who did not answer
 * @return string
 */
function ws_call_line_ended_text($line, $kind, $length, $words, $other)
{
    $video = ((string) $kind === 'video');
    $words = array_values((array) $words);
    $starter = (string) ($words[0] ?? '');

    if ($line === 'missed') {
        return lang(array('string' => $video ? '{var:1} missed a video call from {var:2}.' : '{var:1} missed a voice call from {var:2}.', 'vars' => array((string) $other, $starter)));
    }

    if ($line === 'declined') {
        return lang(array('string' => $video ? '{var:1} declined a video call from {var:2}.' : '{var:1} declined a voice call from {var:2}.', 'vars' => array((string) $other, $starter)));
    }

    return lang(array('string' => $video ? 'Video call ended · {var:1} · {var:2}' : 'Voice call ended · {var:1} · {var:2}', 'vars' => array(ws_call_line_length($length), implode(', ', $words))));
}

/**
 * Writes the line of a call that has just started.
 *
 * @param array $call the ws_calls row
 * @return int the line's message id, 0 for none
 */
function ws_call_line_post_started($call)
{
    if (!is_array($call) || ((int) $call['channel_id'] <= 0)) {
        return 0;
    }

    return ws_message_system((int) $call['channel_id'], ws_call_line_started_text((string) $call['kind'], ws_call_line_party_word('user:' . (int) $call['started_by'], array())));
}

/**
 * Writes the line of a call that has just ended: its length and who took
 * part, or a missed or declined call in a conversation of two.
 *
 * @param array $call        the ws_calls row, as it was while live
 * @param int   $ended_at
 * @param int   $declined_by the user who declined it, 0 for none
 * @return int the line's message id, 0 for none
 */
function ws_call_line_post_ended($call, $ended_at, $declined_by = 0)
{
    $channel = is_array($call) ? ws_channel((int) $call['channel_id']) : null;

    if (!$channel) {
        return 0;
    }

    $members = (array) db_items("SELECT id, user_id, guest_id, joined_at FROM ws_call_members WHERE call_id = '" . (int) $call['id'] . "'");
    $parties = ws_call_line_parties($members);
    $starter = 'user:' . (int) $call['started_by'];

    // The starter is named first, whatever the order of the rows.
    $parties = array_values(array_unique(array_merge(array($starter), $parties)));
    $line = ws_call_line_end_kind($parties, $call, ws_call_channel_people($channel['id']), (string) $channel['kind'], $declined_by);
    $other = '';

    if ($line !== 'ended') {
        $other_id = (int) db_value("SELECT user_id FROM ws_channel_members
            WHERE channel_id = '" . (int) $channel['id'] . "' AND user_id <> '" . (int) $call['started_by'] . "' ORDER BY user_id LIMIT 1");
        $other = ws_call_line_party_word('user:' . $other_id, array());
    }

    $names = ws_call_member_names($members);
    $words = array();

    foreach ($parties as $key) {
        $words[] = ws_call_line_party_word($key, $names);
    }

    $length = (int) $ended_at - (int) $call['started_at'];

    return ws_message_system((int) $channel['id'], ws_call_line_ended_text($line, (string) $call['kind'], $length, $words, $other));
}
