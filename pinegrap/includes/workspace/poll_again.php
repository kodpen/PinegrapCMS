<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the asker of a poll takes it back or asks it again. A poll
 * taken back stays in the channel, greyed, and takes no vote and writes no
 * result (ws_polls.withdrawn_at). Asking again posts a new poll naming the
 * old one (ws_polls.again_of); an old poll still open is taken back by it,
 * an answered or closed one stays as it is.
 *
 * The asker is the person who asked, or the application whose message asks
 * it: an agent changes, takes back and asks again its own polls.
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
 * Do the take-back and ask-again columns exist?
 *
 * @return bool
 */
function ws_poll_again_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('ws_polls', 'withdrawn_at') && pg_schema_has('ws_polls', 'again_of');
}

/**
 * Is this caller the poll's asker? A person: the one who asked, with the
 * question their own message. An application: the one whose message the
 * question is. Pure.
 *
 * @param array $poll      ws_polls row
 * @param array $message   the poll's ws_messages row
 * @param int   $viewer_id
 * @param int   $as_app    the calling application, 0 for a person
 * @return bool
 */
function ws_poll_is_asker($poll, $message, $viewer_id, $as_app = 0)
{
    if ((int) $as_app > 0) {
        return ((string) $message['sender_kind'] === 'app') && ((int) $message['sender_id'] === (int) $as_app);
    }

    return ((int) $poll['created_by'] === (int) $viewer_id) && ((string) $message['sender_kind'] === 'user') && ((int) $message['sender_id'] === (int) $viewer_id);
}

/**
 * Did this person ask the poll with their own message? False on an
 * application's poll, whose owner is its created_by but not its asker, so
 * the box offers no change or take-back the change would refuse. Pure.
 *
 * @param array $poll           ws_polls row
 * @param int   $viewer_id
 * @param int   $message_app_id the application whose message the poll is, 0 for a person's
 * @return bool
 */
function ws_poll_person_asked($poll, $viewer_id, $message_app_id)
{
    return ((int) $poll['created_by'] === (int) $viewer_id) && ((int) $message_app_id === 0);
}

/**
 * Was the poll taken back? Pure.
 *
 * @param array $poll ws_polls row
 * @return bool
 */
function ws_poll_withdrawn($poll)
{
    return (int) ($poll['withdrawn_at'] ?? 0) > 0;
}

/**
 * The checks a take-back and an ask-again share: the columns, the poll's
 * message, an earlier version of the conversation, the asker, the channel.
 *
 * @param array $viewer
 * @param array $poll
 * @param int   $as_app
 * @return array ok, error, field, message
 */
function ws_poll_asker_check($viewer, $poll, $as_app = 0)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'message' => null);
    };

    if (!ws_interact_ready() || !ws_poll_again_ready()) {
        return $fail(lang('The database has to be updated first.'));
    }

    $message = ws_message($poll['message_id']);

    if (!is_array($message) || ((int) $message['deleted_at'] > 0)) {
        return $fail(lang('That poll could not be found.'));
    }

    if (ws_poll_in_past($poll)) {
        return $fail(lang('This message is in an earlier version of the conversation, which is kept as it was.'), 'message_id');
    }

    if (!ws_poll_is_asker($poll, $message, (int) $viewer['id'], $as_app)) {
        return $fail(lang('Only the person who asked can change the poll.'));
    }

    if (!ws_can_post_channel($viewer, ws_channel($poll['channel_id']))) {
        return $fail(lang('You cannot post in that channel.'));
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'message' => $message);
}

/**
 * May the asker take the poll back now? Only an open poll: a closed one has
 * its result, one taken back is already greyed.
 *
 * @param array $viewer
 * @param array $poll
 * @param int   $as_app
 * @return array ok, error, field
 */
function ws_poll_withdraw_check($viewer, $poll, $as_app = 0)
{
    $checked = ws_poll_asker_check($viewer, $poll, $as_app);

    if (!$checked['ok']) {
        return $checked;
    }

    if (ws_poll_withdrawn($poll)) {
        return array('ok' => false, 'error' => lang('The poll was taken back.'), 'field' => 'withdrawn_at');
    }

    if ((int) $poll['closed_at'] > 0) {
        return array('ok' => false, 'error' => lang('The poll is closed.'), 'field' => 'closes_at');
    }

    return array('ok' => true, 'error' => '', 'field' => '');
}

/**
 * Marks a poll taken back. Votes already cast stay stored; none is counted
 * into a result and none more is taken.
 *
 * @param array $poll
 * @param int   $by the person taking it back (an application's owner)
 * @return bool whether this call took it back
 */
function ws_poll_withdraw_write($poll, $by)
{
    db("UPDATE ws_polls SET withdrawn_at = '" . time() . "', withdrawn_by = '" . (int) $by . "'
        WHERE id = '" . (int) $poll['id'] . "' AND withdrawn_at = 0 AND closed_at = 0");

    $done = (mysqli_affected_rows(db::$con) > 0);

    if ($done) {
        ws_message_touch($poll['message_id']);
    }

    return $done;
}

/**
 * The asker takes the poll back.
 *
 * @param array $viewer
 * @param array $poll
 * @param int   $as_app
 * @return array ok, error, field
 */
function ws_poll_withdraw($viewer, $poll, $as_app = 0)
{
    $checked = ws_poll_withdraw_check($viewer, $poll, $as_app);

    if (!$checked['ok']) {
        return $checked;
    }

    ws_poll_withdraw_write($poll, (int) $viewer['id']);

    return array('ok' => true, 'error' => '', 'field' => '');
}

/**
 * The new poll an ask-again posts: what was sent over the old poll's
 * question, its picture, options (their parts, without ids), multiple and
 * anonymous. The closing moment is not carried over, as the old one's may
 * have passed. A picture sent replaces the old one; picture null sent
 * leaves it out. Pure.
 *
 * @param array $old  question, picture, options (parts), multiple, anonymous
 * @param array $sent what the caller sent; a key left out keeps the old value
 * @return array question, picture, options, multiple, anonymous, closes_at
 */
function ws_poll_again_data($old, $sent)
{
    $options = array();

    foreach ((array) ($old['options'] ?? array()) as $part) {
        unset($part['id']);
        $options[] = $part;
    }

    return array(
        'question'  => array_key_exists('question', $sent) ? (string) $sent['question'] : (string) ($old['question'] ?? ''),
        'picture'   => array_key_exists('picture', $sent) ? $sent['picture'] : ($old['picture'] ?? null),
        'options'   => array_key_exists('options', $sent) ? (array) $sent['options'] : $options,
        'multiple'  => array_key_exists('multiple', $sent) ? !empty($sent['multiple']) : !empty($old['multiple']),
        'anonymous' => array_key_exists('anonymous', $sent) ? !empty($sent['anonymous']) : !empty($old['anonymous']),
        'closes_at' => (int) ($sent['closes_at'] ?? 0),
    );
}

/**
 * May the asker ask the poll again with this data? The checks of
 * ws_poll_ask_again(), made on their own so that a dry run meets them.
 *
 * @param array $viewer
 * @param array $poll
 * @param array $sent question, options, multiple, anonymous, closes_at; left out keeps the old
 * @param int   $as_app
 * @return array ok, error, field, data, channel
 */
function ws_poll_again_check($viewer, $poll, $sent, $as_app = 0)
{
    $checked = ws_poll_asker_check($viewer, $poll, $as_app);

    if (!$checked['ok']) {
        return $checked + array('data' => null, 'channel' => null);
    }

    $data = ws_poll_again_data(array(
        'question'  => (string) $checked['message']['body'],
        'picture'   => ws_poll_picture_of_message($checked['message']),
        'options'   => array_values(ws_poll_parts_stored((int) $poll['id'])),
        'multiple'  => ((int) $poll['multiple'] === 1),
        'anonymous' => ((int) $poll['anonymous'] === 1),
    ), (array) $sent);

    $input = ws_poll_input($data);

    if (!$input['ok']) {
        return array('ok' => false, 'error' => $input['error'], 'field' => $input['field'], 'data' => null, 'channel' => null);
    }

    $stored = ws_poll_parts_store_check((int) $poll['channel_id'], $input['parts']);

    if (!$stored['ok']) {
        return array('ok' => false, 'error' => $stored['error'], 'field' => $stored['field'], 'data' => null, 'channel' => null);
    }

    $channel = ws_channel($poll['channel_id']);
    $data['picture'] = ws_poll_picture_normal($data['picture']);
    $pictured = ws_poll_picture_check($viewer, $channel, $data['picture']);

    if (!$pictured['ok']) {
        return array('ok' => false, 'error' => $pictured['error'], 'field' => 'picture', 'data' => null, 'channel' => null);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'data' => $data, 'channel' => $channel);
}

/**
 * Asks a poll again: a new poll in the same channel naming the old one
 * (again_of). The old one is taken back when it is still open; an answered
 * or closed one stays.
 *
 * @param array $viewer
 * @param array $poll
 * @param array $sent as ws_poll_again_check() takes it
 * @param int   $as_app the asking application, 0 for a person
 * @return array ok, error, field, message_id, poll_id
 */
function ws_poll_ask_again($viewer, $poll, $sent, $as_app = 0)
{
    $checked = ws_poll_again_check($viewer, $poll, $sent, $as_app);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'field' => $checked['field'], 'message_id' => 0, 'poll_id' => 0);
    }

    $made = ws_poll_create($viewer, $checked['channel'], $checked['data'], $as_app);

    if (!$made['ok']) {
        return $made;
    }

    db("UPDATE ws_polls SET again_of = '" . (int) $poll['id'] . "' WHERE id = '" . (int) $made['poll_id'] . "'");

    if (!ws_poll_withdrawn($poll) && ((int) $poll['closed_at'] === 0)) {
        ws_poll_withdraw_write($poll, (int) $viewer['id']);
    } else {
        ws_message_touch($poll['message_id']);
    }

    return $made;
}

/**
 * The polls asked again and asking again among a set: for each poll id the
 * message of the poll it asks again and of the newest poll that asks it
 * again, for the "Asked again" links.
 *
 * @param array[] $polls ws_polls rows
 * @return array poll id => {again_of, again_of_message, asked_again, asked_again_message}
 */
function ws_poll_again_links($polls)
{
    $out = array();

    if (empty($polls) || !ws_poll_again_ready()) {
        return $out;
    }

    $ids = array();
    $olds = array();

    foreach ($polls as $poll) {
        $ids[] = (int) $poll['id'];
        $out[(int) $poll['id']] = array('again_of' => (int) ($poll['again_of'] ?? 0), 'again_of_message' => 0, 'asked_again' => 0, 'asked_again_message' => 0);

        if ((int) ($poll['again_of'] ?? 0) > 0) {
            $olds[] = (int) $poll['again_of'];
        }
    }

    if (!empty($olds)) {
        foreach ((array) db_items("SELECT id, message_id FROM ws_polls WHERE id IN (" . implode(',', array_unique($olds)) . ")") as $row) {
            foreach ($out as $poll_id => $link) {
                if ($link['again_of'] === (int) $row['id']) {
                    $out[$poll_id]['again_of_message'] = (int) $row['message_id'];
                }
            }
        }
    }

    foreach ((array) db_items("SELECT id, again_of, message_id FROM ws_polls WHERE again_of IN (" . implode(',', $ids) . ") ORDER BY id") as $row) {
        $out[(int) $row['again_of']]['asked_again'] = (int) $row['id'];
        $out[(int) $row['again_of']]['asked_again_message'] = (int) $row['message_id'];
    }

    return $out;
}
