<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - messages: what is said in a channel, what was decided and what
 * was noted for later.
 *
 * A message can be marked as a decision or a note; those gather on the
 * channel's Decisions tab, which is the channel's memory once the scrolling
 * has buried the conversation that produced them. A message a task was made
 * from carries the task as a live card.
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
 * The longest message, in characters.
 */
define('WS_MESSAGE_MAX', 4000);

/**
 * One message row.
 *
 * @param int $message_id
 * @return array|null
 */
function ws_message($message_id)
{
    $row = db_item("SELECT * FROM ws_messages WHERE id = '" . (int) $message_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The newest messages of a channel, or the page before a given one, oldest
 * first.
 *
 * @param int $channel_id
 * @param int $before_id 0 for the newest
 * @param int $limit
 * @return array[]
 */
function ws_messages_page($channel_id, $before_id = 0, $limit = 50)
{
    // The current conversation only: what came before a clear is an earlier
    // version (includes/workspace/eras.php).
    $floor = ws_channel_floor($channel_id);

    $rows = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel_id . "'"
        . (((int) $before_id > 0) ? " AND id < '" . (int) $before_id . "'" : '')
        . (($floor > 0) ? " AND id > '" . $floor . "'" : '') . "
        ORDER BY id DESC
        LIMIT " . max(1, min(200, (int) $limit)));

    return array_reverse($rows);
}

/**
 * The messages of a channel around one message, for a link that points at it.
 *
 * @param int $channel_id
 * @param int $message_id
 * @return array[]
 */
function ws_messages_around($channel_id, $message_id)
{
    $floor = ws_channel_floor($channel_id);

    $before = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel_id . "' AND id <= '" . (int) $message_id . "' AND id > '" . $floor . "'
        ORDER BY id DESC LIMIT 30");

    $after = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel_id . "' AND id > '" . (int) $message_id . "'
        ORDER BY id ASC LIMIT 30");

    return array_merge(array_reverse($before), $after);
}

/**
 * The messages written after a given one.
 *
 * @param int $channel_id
 * @param int $since_id
 * @return array[]
 */
function ws_messages_since($channel_id, $since_id)
{
    return (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel_id . "' AND id > '" . max((int) $since_id, ws_channel_floor($channel_id)) . "'
        ORDER BY id ASC
        LIMIT 200");
}

/**
 * Messages as the screen draws them, for this reader. Tags, senders and task
 * cards are read once for the whole set.
 *
 * @param array   $viewer
 * @param array[] $rows
 * @return array[]
 */
function ws_message_payloads($viewer, $rows)
{
    // The messages the person deleted for themselves stay out of their view.
    $hidden = ws_message_hidden_ids($viewer['id'], array_map(function ($row) { return (int) $row['id']; }, (array) $rows));

    if (!empty($hidden)) {
        $rows = array_values(array_filter((array) $rows, function ($row) use ($hidden) {
            return !isset($hidden[(int) $row['id']]);
        }));
    }

    $tokens = array();
    $senders = array();
    $task_ids = array();

    foreach ($rows as $row) {
        foreach (ws_tokens($row['body']) as $token) {
            $tokens[] = $token;
        }

        if ($row['sender_kind'] === 'user') {
            $senders[] = (int) $row['sender_id'];
        }

        if ((int) $row['task_id'] > 0) {
            $task_ids[] = (int) $row['task_id'];
        }

        if ((int) $row['marked_by'] > 0) {
            $senders[] = (int) $row['marked_by'];
        }
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $people = ws_people($senders);
    $tasks = array();

    if (!empty($task_ids)) {
        foreach ((array) db_items("SELECT * FROM ws_tasks WHERE id IN (" . implode(',', array_unique($task_ids)) . ")") as $task) {
            $tasks[(int) $task['id']] = $task;
        }
    }

    $assignees = ws_task_assignees_map(array_keys($tasks));

    // Reactions, ticks and polls: one query each for the whole page.
    $ids = array();
    $can_post = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];

        if (!isset($can_post[(int) $row['channel_id']])) {
            $can_post[(int) $row['channel_id']] = ws_can_post_channel($viewer, ws_channel($row['channel_id']));
        }
    }

    $extra = array(
        'reactions'  => ws_reactions_map($ids, $viewer),
        'checks'     => ws_checks_map($ids),
        'polls'      => ws_polls_map($ids, $viewer),
        'list_tasks' => ws_checklist_tasks_map($viewer, $ids),
        'can_post'   => $can_post,
        // Requests to Claude, and the tasks and record changes its answers
        // propose.
        'claude'     => function_exists('ws_claude_requests_map') ? ws_claude_requests_map($ids) : array(),
        'drafts'     => function_exists('ws_claude_drafts_map') ? ws_claude_drafts_map($viewer, $ids) : array(),
        'changes'    => function_exists('ws_changes_map') ? ws_changes_map($viewer, $ids) : array(),
        // Page changes the answers propose (includes/designer_ai.php).
        'design'     => (function_exists('ws_design_ai') && ws_design_ai()) ? pg_design_ai_proposals_map($viewer, $ids) : array(),
        // How many earlier versions an edited file has (file_edits.php).
        'versions'   => function_exists('ws_file_versions_map') ? ws_file_versions_map(array_map(function ($row) {
            return ((int) $row['file_id'] > 0) ? (int) $row['id'] : 0;
        }, $rows)) : array(),
        'outside'    => array(),
        // Notes shared in the channel, as they are now (notes.php).
        'note_cards' => function_exists('ws_note_cards_map') ? ws_note_cards_map($viewer, $ids) : array(),
        // Scheduled actions written in the channel (scheduled.php).
        'scheduled'  => function_exists('ws_scheduled_cards_map') ? ws_scheduled_cards_map($viewer, $ids) : array(),
        // The other messages an answer answers, and the copies a forward
        // carries (forward.php).
        'quotes'     => function_exists('ws_message_quotes_map') ? ws_message_quotes_map($viewer, $ids) : array(),
        'forwards'   => function_exists('ws_message_forwards_map') ? ws_message_forwards_map($viewer, $ids) : array(),
        // The discussion about a message, and the copies of what was decided
        // in one (threads.php).
        'threads'    => ws_threads_map($viewer, $ids),
        'copies'     => ws_thread_copies_map($ids),
    );

    // The people the reader mentioned in their own messages who are not in
    // the channel: the screen offers to invite them.
    foreach ($rows as $row) {
        if (($row['sender_kind'] === 'user') && ((int) $row['sender_id'] === (int) $viewer['id']) && ((int) $row['deleted_at'] === 0)
            && (strpos((string) $row['body'], '<@user:') !== false)) {
            $outside = ws_message_mentions_outside($viewer, $row);

            if (!empty($outside)) {
                $extra['outside'][(int) $row['id']] = array_values(ws_people($outside));
            }
        }
    }

    $out = array();

    foreach ($rows as $row) {
        $out[] = ws_message_payload($viewer, $row, $refs, $people, $tasks, $assignees, $extra);
    }

    return $out;
}

/**
 * One message as the screen draws it.
 *
 * @return array
 */
function ws_message_payload($viewer, $row, $refs, $people, $tasks, $assignees, $extra = array())
{
    $deleted = ((int) $row['deleted_at'] > 0);
    $id = (int) $row['id'];
    // A message of an earlier version of the conversation is read as it was
    // (includes/workspace/eras.php): nothing in it changes any more.
    $past = ws_message_in_past($row);
    $can_post = !empty($extra['can_post'][(int) $row['channel_id']]) && !$past;
    $mine = ($row['sender_kind'] === 'user') && ((int) $row['sender_id'] === (int) $viewer['id']);
    $sender = ($row['sender_kind'] === 'user') ? ($people[(int) $row['sender_id']] ?? null) : null;

    if ($row['sender_kind'] === 'app') {
        $sender = ws_app_sender((int) $row['sender_id']);
    }

    // The guest of a room (guests.php).
    if (($row['sender_kind'] === 'guest') && function_exists('ws_guest_sender')) {
        $sender = ws_guest_sender((int) $row['sender_id']);
    }

    $labels = array();

    foreach (ws_tokens($row['body']) as $token) {
        $key = $token['type'] . ':' . $token['id'];

        if (isset($refs[$key])) {
            $labels['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
        }
    }

    $payload = array(
        'id'          => (int) $row['id'],
        'channel_id'  => (int) $row['channel_id'],
        'parent_id'   => (int) $row['parent_id'],
        'kind'        => (string) $row['kind'],
        'sender_kind' => (string) $row['sender_kind'],
        'sender'      => $sender,
        'mine'        => $mine,
        // What a guest wrote is drawn with the few marks a guest may use
        // (guests.php), here as on their own page: a guest adds no table.
        'html'        => $deleted ? '' : ((($row['sender_kind'] === 'guest') && function_exists('ws_guest_render'))
            ? ws_guest_render($row['body'])
            : ws_render_body($row['body'], $refs, $extra['checks'][$id] ?? array(), $can_post && ws_interact_ready())),
        'raw'         => ($mine && !$deleted) ? (string) $row['body'] : '',
        'labels'      => ($mine && !$deleted) ? $labels : array(),
        'deleted'     => $deleted,
        'edited'      => ((int) $row['edited_at'] > 0),
        'edited_time' => ((int) $row['edited_at'] > 0) ? ws_time_label($row['edited_at']) : '',
        'time'        => ws_time_label($row['created_at']),
        'timestamp'   => (int) $row['created_at'],
        'file'        => null,
        'task_id'     => (int) $row['task_id'],
        'task_html'   => '',
        'marked'      => in_array($row['kind'], array('decision', 'note'), true)
            ? lang(array('string' => 'Marked by {var:1}', 'vars' => ($people[(int) $row['marked_by']]['name'] ?? '')))
            : '',
        // A locked decision is the trace of a record change: nobody edits it,
        // and only staff may delete it or take its mark off.
        'locked'      => !empty($row['locked']),
        // A scheduled action's card is changed in its own form.
        'past'        => $past,
        // A copy of a decision of a discussion follows the original there.
        'can_edit'    => !$past && $mine && !$deleted && empty($row['locked']) && in_array($row['kind'], array('message', 'note', 'decision'), true) && !isset($extra['scheduled'][$id]) && !isset($extra['copies'][$id]),
        'can_delete'  => !$past && !$deleted && ((($row['sender_kind'] === 'user') && ($mine || ($viewer['role'] < 3)) && (empty($row['locked']) || ($viewer['role'] < 3)))
            || (($row['sender_kind'] === 'guest') && ($viewer['role'] < 3))),
        // Deleted for the person alone, whoever wrote it.
        'can_hide'    => !$deleted && ($row['kind'] !== 'system') && ws_message_hides_ready(),
        'can_mark'    => !$past && !$deleted && ($row['sender_kind'] === 'user') && in_array($row['kind'], array('message', 'note', 'decision'), true) && (empty($row['locked']) || ($viewer['role'] < 3)),
        'can_react'   => !$deleted && $can_post && ($row['kind'] !== 'system') && ws_interact_ready(),
        'reactions'   => $deleted ? array() : ($extra['reactions'][$id] ?? array()),
        'poll'        => $deleted ? null : ($extra['polls'][$id] ?? null),
        // The tasks this message's checklist became, with their progress.
        'list_tasks'  => $deleted ? array() : ($extra['list_tasks'][$id] ?? array()),
        'has_list'    => !$deleted && ws_task_work_ready() && (strpos((string) $row['body'], '[') !== false) && !empty(ws_checklist_items($row['body'])),
        'claude'      => (!$deleted && isset($extra['claude'][$id])) ? ws_claude_request_state($viewer, $extra['claude'][$id]) : null,
        'drafts'      => $deleted ? array() : ($extra['drafts'][$id] ?? array()),
        'changes'     => $deleted ? array() : ($extra['changes'][$id] ?? array()),
        'design'      => $deleted ? array() : ($extra['design'][$id] ?? array()),
        // Mentioned, but not in the channel (see ws_message_mention_invite()).
        'invite'      => $deleted ? array() : ($extra['outside'][$id] ?? array()),
        'note_card'   => $deleted ? null : ($extra['note_cards'][$id] ?? null),
        'scheduled'   => $deleted ? null : ($extra['scheduled'][$id] ?? null),
        'quotes'      => $deleted ? array() : ($extra['quotes'][$id] ?? array()),
        'forwards'    => $deleted ? array() : ($extra['forwards'][$id] ?? array()),
        'thread'      => $deleted ? null : ($extra['threads'][$id] ?? null),
        'from_thread' => $deleted ? null : ($extra['copies'][$id] ?? null),
    );

    if (!$deleted && ((int) $row['file_id'] > 0)) {
        $extension = strtolower((string) pathinfo((string) $row['file_name'], PATHINFO_EXTENSION));
        $stored = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $row['file_id'] . "'");

        if ($stored !== '') {
            $kind = ws_file_kind($row['file_name']);

            $payload['file'] = array(
                'name'     => (string) $row['file_name'],
                'url'      => PATH . encode_url_path($stored),
                'image'    => ($kind === 'image'),
                'kind'     => $kind,
                'ext'      => $extension,
                // A text file opens in the text window; an edited file lists
                // the versions it replaced.
                'text'     => function_exists('ws_file_is_text') && ws_file_is_text($row['file_name']),
                'versions' => (int) ($extra['versions'][$id] ?? 0),
            );
        }
    }

    if (($row['kind'] === 'task') && isset($tasks[(int) $row['task_id']])) {
        $payload['task_html'] = ws_task_card_html($viewer, $tasks[(int) $row['task_id']], $assignees[(int) $row['task_id']] ?? array());
    }

    return $payload;
}

/**
 * How an application that wrote into a channel is shown: its own name, marked
 * as an integration, so nobody mistakes it for a colleague.
 *
 * @param int $app_id
 * @return array
 */
function ws_app_sender($app_id)
{
    static $cache = array();

    // The application Claude works through writes as Claude.
    if (!isset($cache[$app_id]) && function_exists('ws_claude_config') && ((int) $app_id > 0) && ((int) $app_id === (int) ws_claude_config()['app_id'])) {
        $cache[$app_id] = array(
            'id'       => 0,
            'app_id'   => (int) $app_id,
            'name'     => 'Claude',
            'username' => '',
            'role'     => 3,
            'title'    => lang('AI assistant'),
            'avatar'   => PATH . SOFTWARE_DIRECTORY . '/assets/images/ws-assistant.svg',
            'presence' => 'offline',
            'app'      => true,
            'claude'   => true,
        );
    }

    // The application Pinegrap AI writes through writes as Pinegrap AI
    // (ai.php).
    if (!isset($cache[$app_id]) && function_exists('ws_ai_is_app') && ws_ai_is_app($app_id)) {
        $cache[$app_id] = array(
            'id'       => 0,
            'app_id'   => (int) $app_id,
            'name'     => 'Pinegrap AI',
            'username' => '',
            'role'     => 3,
            'title'    => lang('AI assistant'),
            'avatar'   => PATH . SOFTWARE_DIRECTORY . '/assets/images/ws-ai.svg',
            'presence' => 'offline',
            'app'      => true,
            'ai'       => true,
        );
    }

    if (!isset($cache[$app_id])) {
        $name = (string) db_value("SELECT name FROM api_apps WHERE id = '" . (int) $app_id . "'");

        $cache[$app_id] = array(
            'id'       => 0,
            'app_id'   => (int) $app_id,
            'name'     => lang(array('string' => '{var:1} (application)', 'vars' => (($name !== '') ? $name : lang('Application')))),
            'username' => '',
            'role'     => 3,
            'title'    => lang('Integration'),
            'avatar'   => function_exists('pg_initials_avatar_url')
                ? pg_initials_avatar_url(pg_initials(($name !== '') ? $name : 'App', ''), 'contact', 'app' . (int) $app_id)
                : PATH . SOFTWARE_DIRECTORY . '/assets/images/person1.png',
            'presence' => 'offline',
            'app'      => true,
        );
    }

    return $cache[$app_id];
}

/**
 * A line the system writes into a channel: who created it, who joined, which
 * task was finished.
 *
 * @param int    $channel_id
 * @param string $body
 * @param int    $task_id
 * @return int the message id
 */
function ws_message_system($channel_id, $body, $task_id = 0)
{
    $now = time();

    db("INSERT INTO ws_messages (channel_id, sender_kind, sender_id, kind, body, task_id, created_at)
        VALUES ('" . (int) $channel_id . "', 'system', 0, 'system', '" . e($body) . "', '" . (int) $task_id . "', '" . $now . "')");

    $message_id = (int) mysqli_insert_id(db::$con);

    if ($message_id > 0) {
        db("UPDATE ws_channels SET last_message_id = '" . $message_id . "', last_message_at = '" . $now . "'
            WHERE id = '" . (int) $channel_id . "'");
    }

    return $message_id;
}

/**
 * Is this person writing too fast? Twenty messages a minute is more than
 * anyone types; beyond it is a stuck key or a script.
 *
 * @param int $user_id
 * @return bool
 */
function ws_rate_limited($user_id)
{
    return (int) db_value("SELECT COUNT(*) FROM ws_messages
        WHERE sender_kind = 'user' AND sender_id = '" . (int) $user_id . "' AND created_at > '" . (time() - 60) . "'") >= 20;
}

/**
 * Writes a message into a channel and tells the people it mentions.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $body
 * @param array  $options kind (message | note | decision | task), task_id, file_id,
 *                        file_name, parent_id, app_id (written by an API application
 *                        acting for $viewer)
 * @return array ok, error, message_id
 */
function ws_message_send($viewer, $channel, $body, $options = array())
{
    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'), 'message_id' => 0);
    }

    $body = ws_tokens_normalise(trim((string) $body));
    $file_id = (int) ($options['file_id'] ?? 0);

    // A forward may carry the messages alone, without words of its own.
    if (($body === '') && ($file_id <= 0) && ((int) ($options['task_id'] ?? 0) <= 0) && ((int) ($options['forwards'] ?? 0) <= 0)) {
        return array('ok' => false, 'error' => lang('The message is empty.'), 'message_id' => 0);
    }

    if (mb_strlen($body) > WS_MESSAGE_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => WS_MESSAGE_MAX)), 'message_id' => 0);
    }

    $app_id = (int) ($options['app_id'] ?? 0);

    if (($app_id === 0) && ws_rate_limited($viewer['id'])) {
        return array('ok' => false, 'error' => lang('You are sending messages too quickly. Please wait a moment.'), 'message_id' => 0);
    }

    $kind = (string) ($options['kind'] ?? 'message');

    if (!in_array($kind, array('message', 'note', 'decision', 'task'), true)) {
        $kind = 'message';
    }

    // Writing in a public channel is joining it - for a person, not for the
    // owner of an application that wrote in their name.
    if (($app_id === 0) && ($channel['kind'] === 'public') && !ws_channel_membership($channel['id'], $viewer['id'])) {
        ws_channel_join($viewer, $channel);
    }

    $parent_id = (int) ($options['parent_id'] ?? 0);

    if (($parent_id > 0) && ((int) db_value("SELECT channel_id FROM ws_messages WHERE id = '" . $parent_id . "'") !== (int) $channel['id'])) {
        $parent_id = 0;
    }

    $now = time();
    $marked = in_array($kind, array('note', 'decision'), true);

    db("INSERT INTO ws_messages (channel_id, parent_id, sender_kind, sender_id, kind, body, task_id, file_id, file_name,
            marked_by, marked_at, created_at)
        VALUES (
            '" . (int) $channel['id'] . "',
            '" . $parent_id . "',
            '" . (($app_id > 0) ? 'app' : 'user') . "',
            '" . (($app_id > 0) ? $app_id : (int) $viewer['id']) . "',
            '" . $kind . "',
            '" . e($body) . "',
            '" . (int) ($options['task_id'] ?? 0) . "',
            '" . $file_id . "',
            '" . e(mb_substr((string) ($options['file_name'] ?? ''), 0, 255)) . "',
            '" . ($marked ? (int) $viewer['id'] : 0) . "',
            '" . ($marked ? $now : 0) . "',
            '" . $now . "')");

    $message_id = (int) mysqli_insert_id(db::$con);

    if ($message_id <= 0) {
        return array('ok' => false, 'error' => lang('The message could not be saved.'), 'message_id' => 0);
    }

    // An answer to several: the first is the parent, the others are kept in
    // their order - messages of the same channel only.
    if (($parent_id > 0) && !empty($options['quote_ids']) && function_exists('ws_message_quotes_store')) {
        $quoted = array_map('intval', (array) db_values("SELECT id FROM ws_messages
            WHERE channel_id = '" . (int) $channel['id'] . "' AND deleted_at = 0
            AND id IN (" . implode(',', array_merge(array(0), array_slice(array_map('intval', (array) $options['quote_ids']), 0, WS_FORWARD_MAX))) . ")"));
        $ordered = array_values(array_filter(array_map('intval', (array) $options['quote_ids']), function ($id) use ($quoted, $parent_id) {
            return ($id !== $parent_id) && in_array($id, $quoted, true);
        }));

        ws_message_quotes_store($message_id, $ordered);
    }

    db("UPDATE ws_channels SET last_message_id = '" . $message_id . "', last_message_at = '" . $now . "'
        WHERE id = '" . (int) $channel['id'] . "'");

    if ($app_id === 0) {
        ws_channel_mark_read($channel['id'], $viewer['id'], $message_id);
    }

    $tokens = ws_tokens($body);
    ws_refs_store('message', $message_id, $channel['id'], $tokens);

    // Its titled blocks, to be found and pulled elsewhere (blocks.php).
    if (function_exists('ws_blocks_index') && (strpos($body, ':::') !== false)) {
        ws_blocks_index('message', $message_id, $body, ($app_id > 0) ? 0 : (int) $viewer['id'], (int) $channel['id']);
    }
    ws_message_notify_mentions($viewer, $channel, $message_id, $tokens, $app_id);

    // A decision, a note or a task card of a discussion goes into its
    // channel too, and the bar under the message it talks over counts the
    // new one (threads.php).
    if ($channel['kind'] === 'thread') {
        if (in_array($kind, array('decision', 'note', 'task'), true)) {
            ws_thread_copy_sync($message_id);
        }

        $thread = ws_thread($channel['id']);

        if ($thread) {
            ws_message_touch((int) $thread['message_id']);
        }
    }

    // Announced for public channels only: a webhook receiver is not a member
    // of anything, and a private channel's words must not leave the site
    // through a subscription its members never agreed to.
    if ($channel['kind'] === 'public') {
        pg_announce('workspace.message.created', array(
            'id'          => $message_id,
            'channel_id'  => (int) $channel['id'],
            'channel'     => (string) $channel['name'],
            'sender_id'   => ($app_id > 0) ? 0 : (int) $viewer['id'],
            'app_id'      => $app_id,
            'kind'        => $kind,
            'text'        => ws_plain_text($viewer, $body),
            'task_id'     => (int) ($options['task_id'] ?? 0),
        ));
    }

    return array('ok' => true, 'error' => '', 'message_id' => $message_id);
}

/**
 * May this person bring others into the channel? Anyone who may write in a
 * public channel; only its members in a private one.
 *
 * @param array $viewer
 * @param array $channel
 * @return bool
 */
function ws_channel_can_invite($viewer, $channel)
{
    return ws_can_post_channel($viewer, $channel)
        && (($channel['kind'] === 'public') || (bool) ws_channel_membership($channel['id'], $viewer['id']));
}

/**
 * The colleagues a message mentions by name who are not in its channel, for
 * somebody who could bring them in. In a private channel the mention has not
 * reached them at all.
 *
 * @param array $viewer
 * @param array $message
 * @return int[]
 */
function ws_message_mentions_outside($viewer, $message)
{
    $channel = ws_channel($message['channel_id']);

    if (!$channel || !empty($channel['archived_at']) || !ws_channel_can_invite($viewer, $channel)) {
        return array();
    }

    $out = array();

    foreach (ws_tokens($message['body']) as $token) {
        $user_id = (int) $token['id'];

        if (($token['type'] !== 'user') || ($user_id === (int) $viewer['id']) || isset($out[$user_id])) {
            continue;
        }

        if (ws_is_team_member($user_id) && !ws_channel_membership($channel['id'], $user_id)) {
            $out[$user_id] = $user_id;
        }
    }

    return array_values($out);
}

/**
 * Brings the people a message mentions into its channel, and hands them the
 * mention they could not have had while they were outside it.
 *
 * @param array      $viewer
 * @param array      $message
 * @param int[]|null $user_ids some of them only; null for all
 * @return array ok, error, added (ids)
 */
function ws_message_mention_invite($viewer, $message, $user_ids = null)
{
    $channel = ws_channel($message['channel_id']);

    if (!$channel || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('That message could not be found.'), 'added' => array());
    }

    if (!ws_channel_can_invite($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('Access denied.'), 'added' => array());
    }

    $outside = ws_message_mentions_outside($viewer, $message);

    if ($user_ids !== null) {
        $outside = array_values(array_intersect($outside, array_map('intval', (array) $user_ids)));
    }

    if (empty($outside)) {
        return array('ok' => true, 'error' => '', 'added' => array());
    }

    $added = ws_channel_add_members($viewer, $channel, $outside);
    $channel = ws_channel($channel['id']);

    foreach ($added as $user_id) {
        ws_notify($user_id, 'mention', array(
            'channel_id' => $channel['id'],
            'message_id' => $message['id'],
            'actor_id'   => ($message['sender_kind'] === 'user') ? (int) $message['sender_id'] : 0,
        ));
    }

    ws_message_touch($message['id']);

    return array('ok' => true, 'error' => '', 'added' => $added);
}

/**
 * Tells the people a message mentions - by name or through their
 * department - provided they can read the channel it is in and have not
 * silenced it completely.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $message_id
 * @param array $tokens
 */
function ws_message_notify_mentions($viewer, $channel, $message_id, $tokens, $app_id = 0)
{
    $people = array();

    foreach ($tokens as $token) {
        if ($token['type'] === 'user') {
            $people[(int) $token['id']] = true;
        } elseif ($token['type'] === 'dept') {
            foreach (ws_department_member_ids($token['id']) as $user_id) {
                $people[(int) $user_id] = true;
            }
        }
    }

    // Nobody is told about their own words. An application's message is not
    // its owner's: Claude answering the administrator who owns its key still
    // tells them.
    if ((int) $app_id === 0) {
        unset($people[(int) $viewer['id']]);
    }

    foreach (array_keys($people) as $user_id) {
        if (!ws_is_team_member($user_id)) {
            continue;
        }

        $rights = ws_rights_for_id($user_id);

        if (!ws_can_read_channel($rights, $channel)) {
            continue;
        }

        $membership = ws_channel_membership($channel['id'], $user_id);

        if ($membership && ($membership['notify'] === 'none')) {
            continue;
        }

        ws_notify($user_id, 'mention', array(
            'channel_id' => $channel['id'],
            'message_id' => $message_id,
            'actor_id'   => ((int) $app_id > 0) ? 0 : $viewer['id'],
        ));
    }
}

/**
 * The messages of a channel that changed after they were written - an edit,
 * a reaction, a tick, a vote - since a moment, up to the newest one the
 * reader already has.
 *
 * @param int $channel_id
 * @param int $since   timestamp
 * @param int $upto_id the newest message the reader holds
 * @return array[]
 */
function ws_messages_touched($channel_id, $since, $upto_id)
{
    if (!ws_interact_ready() || ((int) $since <= 0) || ((int) $upto_id <= 0)) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel_id . "' AND touched_at >= '" . (int) $since . "' AND id <= '" . (int) $upto_id . "'
        ORDER BY id
        LIMIT 50");
}

/**
 * Changes the text of one's own message.
 *
 * @param array  $viewer
 * @param array  $message
 * @param string $body
 * @return array ok, error
 */
function ws_message_edit($viewer, $message, $body)
{
    if (($message['sender_kind'] !== 'user') || ((int) $message['sender_id'] !== (int) $viewer['id'])
        || ((int) $message['deleted_at'] > 0) || !in_array($message['kind'], array('message', 'note', 'decision'), true)) {
        return array('ok' => false, 'error' => lang('You can only change your own messages.'));
    }

    if (!empty($message['locked'])) {
        return array('ok' => false, 'error' => lang('This decision records a change to a record and cannot be edited.'));
    }

    $channel = ws_channel($message['channel_id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $body = ws_tokens_normalise(trim((string) $body));

    if (($body === '') && ((int) $message['file_id'] <= 0)) {
        return array('ok' => false, 'error' => lang('The message is empty.'));
    }

    if (mb_strlen($body) > WS_MESSAGE_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => WS_MESSAGE_MAX)));
    }

    $before = array();

    foreach (ws_tokens($message['body']) as $token) {
        $before[$token['type'] . ':' . $token['id']] = true;
    }

    db("UPDATE ws_messages SET body = '" . e($body) . "', edited_at = '" . time() . "' WHERE id = '" . (int) $message['id'] . "'");

    if (function_exists('ws_blocks_index')) {
        ws_blocks_index('message', (int) $message['id'], $body, (int) $message['sender_id'], (int) $message['channel_id']);
    }

    ws_checks_remap($message['id'], $message['body'], $body);
    ws_message_touch($message['id']);
    ws_checklist_tasks_refresh($message['id']);

    $tokens = ws_tokens($body);
    ws_refs_store('message', $message['id'], $message['channel_id'], $tokens);

    // Only the people the edit newly mentions are told.
    $new = array();

    foreach ($tokens as $token) {
        if (!isset($before[$token['type'] . ':' . $token['id']])) {
            $new[] = $token;
        }
    }

    ws_message_notify_mentions($viewer, $channel, $message['id'], $new);

    if ($channel['kind'] === 'thread') {
        ws_thread_copy_sync($message['id']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Deletes a message: the author their own, staff anyone's. The row stays as
 * "message deleted" so the conversation around it still reads; its text,
 * file link and tags go.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error
 */
function ws_message_delete($viewer, $message)
{
    $mine = ($message['sender_kind'] === 'user') && ((int) $message['sender_id'] === (int) $viewer['id']);

    // A guest's message is taken out by staff.
    if (!in_array($message['sender_kind'], array('user', 'guest'), true) || (!$mine && ($viewer['role'] >= 3))) {
        return array('ok' => false, 'error' => lang('You can only delete your own messages.'));
    }

    if (!empty($message['locked']) && ($viewer['role'] >= 3)) {
        return array('ok' => false, 'error' => lang('This decision records a change to a record. People with the User role cannot delete it.'));
    }

    if (!ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        return array('ok' => false, 'error' => lang('Access denied.'));
    }

    // A checklist a task carries moves into the task before the text goes.
    ws_checklist_tasks_detach($message);

    db("UPDATE ws_messages SET body = '', file_id = 0, file_name = '', deleted_at = '" . time() . "' WHERE id = '" . (int) $message['id'] . "'");
    db("DELETE FROM ws_refs WHERE source_type = 'message' AND source_id = '" . (int) $message['id'] . "'");

    if (function_exists('ws_blocks_index')) {
        ws_blocks_index('message', (int) $message['id'], '');
    }
    db("DELETE FROM ws_inbox WHERE message_id = '" . (int) $message['id'] . "' AND kind = 'mention'");
    ws_message_touch($message['id']);

    // A decision of a discussion takes its copy in the channel with it.
    ws_thread_copy_sync($message['id']);

    if (!$mine) {
        log_activity(lang(array('string' => 'a workspace message by {var:1} was deleted', 'vars' => (($message['sender_kind'] === 'guest') && function_exists('ws_guest_sender'))
            ? ws_guest_sender((int) $message['sender_id'])['name']
            : ws_person_name($message['sender_id']))), (string) ($_SESSION['sessionusername'] ?? ''));
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Can a message be deleted for one person only (2026.4.5, 5.80)?
 *
 * @return bool
 */
function ws_message_hides_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_message_hides'") > 0);
    }

    return $ready;
}

/**
 * Of these messages, the ones the person deleted for themselves.
 *
 * @param int   $user_id
 * @param int[] $message_ids
 * @return array message id => true
 */
function ws_message_hidden_ids($user_id, $message_ids)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));

    if (empty($message_ids) || !ws_message_hides_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_values("SELECT message_id FROM ws_message_hides
        WHERE user_id = '" . (int) $user_id . "' AND message_id IN (" . implode(',', $message_ids) . ")") as $id) {
        $out[(int) $id] = true;
    }

    return $out;
}

/**
 * Deletes a message for the person alone: the others keep it as it is.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error
 */
function ws_message_hide($viewer, $message)
{
    if (!ws_message_hides_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if (($message['kind'] === 'system') || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        return array('ok' => false, 'error' => lang('That message could not be found.'));
    }

    db("INSERT IGNORE INTO ws_message_hides (message_id, user_id, hidden_at)
        VALUES ('" . (int) $message['id'] . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    // A mention in it is no longer waiting for them either.
    db("UPDATE ws_inbox SET read_at = '" . time() . "' WHERE user_id = '" . (int) $viewer['id'] . "' AND message_id = '" . (int) $message['id'] . "' AND read_at = 0");

    return array('ok' => true, 'error' => '');
}

/**
 * Who of the channel's members has read as far as a message, and who has not
 * yet. Read is what the channel keeps for each member: the newest message
 * that was on their screen (ws_channel_members.last_read_id). The one who
 * wrote it is left out; in a public channel only its members are counted,
 * a reader passing through leaves no mark.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error, seen, unseen (people)
 */
function ws_message_seen_by($viewer, $message)
{
    $channel = ws_channel($message['channel_id']);

    if (!$channel || !ws_can_read_channel($viewer, $channel) || ((int) $message['deleted_at'] > 0)) {
        return array('ok' => false, 'error' => lang('That message could not be found.'), 'seen' => array(), 'unseen' => array());
    }

    $sender = ($message['sender_kind'] === 'user') ? (int) $message['sender_id'] : 0;
    $rows = (array) db_items("SELECT user_id, last_read_id FROM ws_channel_members
        WHERE channel_id = '" . (int) $channel['id'] . "' ORDER BY joined_at");
    $people = ws_people(array_map(function ($row) { return (int) $row['user_id']; }, $rows));
    $seen = array();
    $unseen = array();

    foreach ($rows as $row) {
        $user_id = (int) $row['user_id'];

        if (($user_id === $sender) || !isset($people[$user_id])) {
            continue;
        }

        if ((int) $row['last_read_id'] >= (int) $message['id']) {
            $seen[] = $people[$user_id];
        } else {
            $unseen[] = $people[$user_id];
        }
    }

    return array('ok' => true, 'error' => '', 'seen' => $seen, 'unseen' => $unseen);
}

/**
 * Marks a message as a decision or a note, or takes the mark off.
 *
 * @param array  $viewer
 * @param array  $message
 * @param string $kind decision | note | message
 * @return array ok, error
 */
function ws_message_mark($viewer, $message, $kind)
{
    if (!in_array($kind, array('decision', 'note', 'message'), true)
        || ($message['sender_kind'] !== 'user') || ((int) $message['deleted_at'] > 0)
        || !in_array($message['kind'], array('message', 'note', 'decision'), true)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if (!empty($message['locked']) && ($viewer['role'] >= 3)) {
        return array('ok' => false, 'error' => lang('This decision records a change to a record. People with the User role cannot take its mark off.'));
    }

    if (!ws_can_post_channel($viewer, ws_channel($message['channel_id']))) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $marked = ($kind !== 'message');

    db("UPDATE ws_messages SET
            kind = '" . e($kind) . "',
            marked_by = '" . ($marked ? (int) $viewer['id'] : 0) . "',
            marked_at = '" . ($marked ? time() : 0) . "'
        WHERE id = '" . (int) $message['id'] . "'");

    ws_message_touch($message['id']);

    // Marked in a discussion: the channel gets the decision (threads.php).
    ws_thread_copy_sync($message['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * The decisions and notes of a channel, newest first.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @return array[]
 */
function ws_channel_decisions($viewer, $channel_id)
{
    $rows = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel_id . "' AND kind IN ('decision', 'note') AND deleted_at = 0
        ORDER BY marked_at DESC, id DESC
        LIMIT 300");

    return ws_message_payloads($viewer, $rows);
}

/**
 * The files posted in a channel, newest first.
 *
 * @param array $viewer
 * @param int   $channel_id
 * @return array[]
 */
function ws_channel_files($viewer, $channel_id)
{
    $rows = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel_id . "' AND file_id > 0 AND deleted_at = 0
        ORDER BY id DESC
        LIMIT 300");

    return ws_message_payloads($viewer, $rows);
}

/**
 * Searches the messages of the channels this person can read.
 *
 * @param array  $viewer
 * @param string $query
 * @param int    $channel_id 0 for every channel
 * @return array[]
 */
function ws_message_search($viewer, $query, $channel_id = 0)
{
    $query = trim((string) $query);

    if (mb_strlen($query) < 2) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_messages
        WHERE deleted_at = 0 AND body LIKE '%" . e(escape_like($query)) . "%'"
        . (((int) $channel_id > 0) ? " AND channel_id = '" . (int) $channel_id . "'" : '') . "
        ORDER BY id DESC
        LIMIT 200");

    $channels = array();
    $visible = array();

    foreach ($rows as $row) {
        $id = (int) $row['channel_id'];

        if (!isset($channels[$id])) {
            $channels[$id] = ws_channel($id);
        }

        if (ws_can_read_channel($viewer, $channels[$id])) {
            $visible[] = $row;
        }

        if (count($visible) >= 50) {
            break;
        }
    }

    $out = ws_message_payloads($viewer, $visible);

    foreach ($out as $index => $message) {
        $out[$index]['channel'] = $channels[$message['channel_id']]['name'] ?? '';
    }

    return $out;
}
