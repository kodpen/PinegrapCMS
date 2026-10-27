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
 * @param array   $options hidden_as_deleted: the rows are changes of messages
 *                         the screen already shows, so one the person hid
 *                         comes as deleted rather than not at all
 * @return array[]
 */
function ws_message_payloads($viewer, $rows, $options = array())
{
    // The messages the person deleted for themselves stay out of their view.
    // Among the changes to messages a screen already shows, one they hid
    // since, on another screen or device, comes as deleted: the screen takes
    // it away as it does a message deleted for everyone.
    $rows = (array) $rows;
    $hidden = ws_message_hidden_ids($viewer['id'], array_map(function ($row) { return (int) $row['id']; }, $rows));

    if (!empty($hidden) && !empty($options['hidden_as_deleted'])) {
        foreach ($rows as $i => $row) {
            if (isset($hidden[(int) $row['id']]) && ((int) $row['deleted_at'] === 0)) {
                $rows[$i]['deleted_at'] = time();
                $rows[$i]['body'] = '';
            }
        }
    } elseif (!empty($hidden)) {
        $rows = array_values(array_filter($rows, function ($row) use ($hidden) {
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

    // The tasks made of checklist items, their tags resolved with the rest.
    $item_tasks = ws_checklist_item_tasks_map($rows);

    foreach ($item_tasks as $pairs) {
        foreach ($pairs as $task_id) {
            $tokens[] = array('sigil' => '#', 'type' => 'task', 'id' => (int) $task_id);
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
        // Approval requests and read receipts (approvals.php, acks.php).
        'approvals'  => function_exists('ws_approvals_map') ? ws_approvals_map($ids, $viewer) : array(),
        'acks'       => function_exists('ws_acks_map') ? ws_acks_map($rows, $viewer) : array(),
        // Status updates and check-ins (status.php).
        'status'     => function_exists('ws_status_cards_map') ? ws_status_cards_map($viewer, $ids) : array(),
        'list_tasks' => ws_checklist_tasks_map($viewer, $ids),
        'item_tasks' => $item_tasks,
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
        // The answers MCP agents gave, kept in their askers' names
        // (mcp_agent.php).
        'agents'     => function_exists('ws_mcp_agent_answers_map') ? ws_mcp_agent_answers_map($ids) : array(),
        // The proposals of applications whose words these are
        // (proposals.php).
        'proposals'  => function_exists('ws_proposals_by_reply') ? ws_proposals_by_reply($ids) : array(),
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

        // A proposal an OAuth connection made is shown under the client's
        // own name (proposals.php): every client writes through one
        // application.
        if (isset($extra['proposals'][$id]) && function_exists('ws_proposal_sender')) {
            $sender = ws_proposal_sender($sender, $extra['proposals'][$id]);
        }
    }

    // The guest of a room (guests.php).
    if (($row['sender_kind'] === 'guest') && function_exists('ws_guest_sender')) {
        $sender = ws_guest_sender((int) $row['sender_id']);
    }

    // An MCP agent's answer is kept in the name of the person who asked
    // (mcp_agent.php) and drawn under the agent's own: to them too it is an
    // answer that came, not a message of theirs.
    $agent = (($row['sender_kind'] === 'user') && isset($extra['agents'][$id]) && function_exists('ws_mcp_agent_answer_view'))
        ? ws_mcp_agent_answer_view($extra['agents'][$id], (string) $row['body'])
        : null;

    if ($agent) {
        $sender = $agent['sender'];
        $mine = false;
    }

    $labels = array();

    foreach (ws_tokens($row['body']) as $token) {
        $key = $token['type'] . ':' . $token['id'];

        if (isset($refs[$key])) {
            $labels['<' . $token['sigil'] . $key . '>'] = $refs[$key]['label'];
        }
    }

    // Its checklist items show the tasks made of them, and offer to make
    // one where the reader may (task_work.php).
    $item_tasks = $agent ? null : array(
        'tasks' => $extra['item_tasks'][$id] ?? array(),
        'make'  => ws_checklist_item_task_offered($row, $can_post),
    );

    // A message that is one record tag and nothing else is drawn as its card
    // (render.php), with what this reader may see of the record.
    $tag_card = (!$deleted && !$agent && in_array($row['kind'], array('message', 'decision', 'note'), true)
        && in_array($row['sender_kind'], array('user', 'app'), true))
        ? ws_tag_card_html((string) $row['body'], $refs)
        : '';

    $payload = array(
        'id'          => (int) $row['id'],
        'channel_id'  => (int) $row['channel_id'],
        'parent_id'   => (int) $row['parent_id'],
        'kind'        => (string) $row['kind'],
        'sender_kind' => $agent ? 'app' : (string) $row['sender_kind'],
        'sender'      => $sender,
        'mine'        => $mine,
        // What a guest wrote is drawn with the few marks a guest may use
        // (guests.php), here as on their own page: a guest adds no table.
        'html'        => $deleted ? '' : ((($row['sender_kind'] === 'guest') && function_exists('ws_guest_render'))
            ? ws_guest_render($row['body'])
            : (($tag_card !== '') ? $tag_card : ws_render_body($agent ? $agent['text'] : $row['body'], $refs, $extra['checks'][$id] ?? array(), $can_post && ws_interact_ready(), null, $item_tasks))),
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
        // An approval request stays what the people were asked to approve.
        // A note card's text is only the card's caption; the note itself is
        // edited on the notes screen.
        'can_edit'    => !$past && $mine && !$deleted && empty($row['locked']) && in_array($row['kind'], array('message', 'note', 'decision'), true) && !isset($extra['scheduled'][$id]) && !isset($extra['copies'][$id]) && !isset($extra['approvals'][$id]) && !isset($extra['note_cards'][$id]),
        'can_delete'  => !$past && !$deleted && ((($row['sender_kind'] === 'user') && ($mine || ($viewer['role'] < 3)) && (empty($row['locked']) || ($viewer['role'] < 3)))
            || (($row['sender_kind'] === 'guest') && ($viewer['role'] < 3))),
        // Deleted for the person alone, whoever wrote it.
        'can_hide'    => !$deleted && ($row['kind'] !== 'system') && ws_message_hides_ready(),
        'can_mark'    => !$past && !$deleted && ($row['sender_kind'] === 'user') && in_array($row['kind'], array('message', 'note', 'decision'), true) && (empty($row['locked']) || ($viewer['role'] < 3)),
        'can_react'   => !$deleted && $can_post && ($row['kind'] !== 'system') && ws_interact_ready(),
        'reactions'   => $deleted ? array() : ($extra['reactions'][$id] ?? array()),
        'poll'        => $deleted ? null : ($extra['polls'][$id] ?? null),
        'approval'    => $deleted ? null : ($extra['approvals'][$id] ?? null),
        'status_card' => $deleted ? null : ($extra['status'][$id] ?? null),
        // A read receipt asked for, and whether the reader may ask for one.
        'ack'         => $deleted ? null : ($extra['acks'][$id] ?? null),
        // The same test as ws_ack_can_request(), from what is known already.
        'ack_can_request' => $can_post && !$deleted && function_exists('ws_acks_ready') && ws_acks_ready()
            && in_array($row['kind'], array('message', 'note', 'decision'), true) && in_array($row['sender_kind'], array('user', 'app'), true)
            && ($mine || ($viewer['role'] < 3)),
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
                // A Word or Excel file opens in the preview window, read on
                // the server (ws_file_office).
                'office'   => function_exists('pg_office_kind') && (pg_office_kind($row['file_name']) !== ''),
                'message_id' => (int) $id,
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
 * Whether this message may be written: the checks of ws_message_send(), made
 * on their own so that the API's dry run meets the same refusals.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param string $body
 * @param array  $options as for ws_message_send()
 * @return array ok, error, body (the text as it would be stored)
 */
function ws_message_send_check($viewer, $channel, $body, $options = array())
{
    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $body = ws_tokens_normalise(trim((string) $body));
    $file_id = (int) ($options['file_id'] ?? 0);

    // A forward may carry the messages alone, without words of its own.
    if (($body === '') && ($file_id <= 0) && ((int) ($options['task_id'] ?? 0) <= 0) && ((int) ($options['forwards'] ?? 0) <= 0)) {
        return array('ok' => false, 'error' => lang('The message is empty.'));
    }

    if (mb_strlen($body) > WS_MESSAGE_MAX) {
        return array('ok' => false, 'error' => lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => WS_MESSAGE_MAX)));
    }

    if (((int) ($options['app_id'] ?? 0) === 0) && ws_rate_limited($viewer['id'])) {
        return array('ok' => false, 'error' => lang('You are sending messages too quickly. Please wait a moment.'));
    }

    return array('ok' => true, 'error' => '', 'body' => $body);
}

/**
 * Why a message cannot be answered in a channel, from its row: it is not one
 * of the channel's own (a discussion's own messages for a discussion), it was
 * deleted, or it is in an earlier version of the conversation (eras.php).
 * Pure: the row and the floor are read by ws_message_parent_check().
 *
 * @param array|null $parent     ws_messages row, null when there is none
 * @param int        $channel_id the channel or discussion the answer goes to
 * @param int        $floor      ws_channel_floor() of that channel
 * @return string '' when it may be answered, else the refusal
 */
function ws_message_parent_refusal($parent, $channel_id, $floor)
{
    if (!is_array($parent) || ((int) $parent['channel_id'] !== (int) $channel_id) || ((int) $parent['deleted_at'] > 0)) {
        return lang('That message could not be found.');
    }

    if ((int) $parent['id'] <= (int) $floor) {
        return lang('This message is in an earlier version of the conversation, which is kept as it was.');
    }

    return '';
}

/**
 * Whether a file posted into a channel may answer a message there
 * (parent_id): the external API's upload refuses with it before its dry run
 * stops, and the panel's upload (ws_attach) posts the file without the parent
 * rather than lose a recording. ws_message_send() itself only keeps a parent
 * of another channel out.
 *
 * @param array $channel
 * @param int   $parent_id 0 for none
 * @return string '' when it may (or when none is given), else the refusal
 */
function ws_message_parent_check($channel, $parent_id)
{
    if ((int) $parent_id <= 0) {
        return '';
    }

    return ws_message_parent_refusal(ws_message((int) $parent_id), (int) $channel['id'], ws_channel_floor($channel['id']));
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
    $checked = ws_message_send_check($viewer, $channel, $body, $options);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'message_id' => 0);
    }

    $body = $checked['body'];
    $file_id = (int) ($options['file_id'] ?? 0);
    $app_id = (int) ($options['app_id'] ?? 0);
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

    // An application it mentions hears it through its own subscriptions,
    // in any channel it may read.
    ws_message_tell_apps($channel, $message_id, $tokens);

    return array('ok' => true, 'error' => '', 'message_id' => $message_id);
}

/**
 * After a person's message was written: the assistants it asks get the
 * request queued - Claude (claude.php), an MCP server that answers its alias
 * on its own (mcp_agent.php), otherwise Pinegrap AI (ai.php), which also
 * answers an alias with that server's tools. Each one checks whether it may
 * be asked in the channel and leaves a line saying why not when it may not.
 * Nothing is started here; the caller carries the queue on.
 *
 * @param array  $viewer
 * @param array  $channel
 * @param int    $message_id
 * @param string $body as written
 * @return array claude, agent, ai: whether a request was queued for each
 */
function ws_message_ask_assistants($viewer, $channel, $message_id, $body)
{
    $body = ws_tokens_normalise(trim((string) $body));
    $claude = function_exists('ws_claude_after_send') && ws_claude_after_send($viewer, $channel, $message_id, $body);
    $agent = function_exists('ws_mcp_agent_after_send') ? ws_mcp_agent_after_send($viewer, $channel, $message_id, $body) : null;

    // A message written to an agent's alias is the agent's alone.
    $ai = ($agent === null) && function_exists('ws_ai_after_send') && ws_ai_after_send($viewer, $channel, $message_id, $body);

    // Asked in a discussion: its channel's message says so.
    if (($claude || $ai) && ws_channel_is_thread($channel)) {
        ws_thread_assistant_asked($channel, $claude ? 'claude' : 'ai');
    }

    return array('claude' => $claude, 'agent' => ($agent === true), 'ai' => $ai);
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
 * The applications a message's tags mention (<@app:N>), each once, in the
 * order they first appear. A tag written with # mentions nobody.
 *
 * @param array $tokens ws_tokens() rows
 * @return int[]
 */
function ws_mentioned_app_ids($tokens)
{
    $ids = array();

    foreach ((array) $tokens as $token) {
        $id = (int) ($token['id'] ?? 0);

        if ((($token['sigil'] ?? '') === '@') && (($token['type'] ?? '') === 'app') && ($id > 0) && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

/**
 * The applications a message tells that they were mentioned: the ones its
 * tags mention, less the application that wrote it - its own words never
 * call it, so an answer that repeats its tag does not start it again - and
 * less the assistants that answer their mentions inside the site (Claude
 * and Pinegrap AI, ws_message_ask_assistants()). One application mentioning
 * another is heard; the event says which application wrote it.
 *
 * @param array $tokens        ws_tokens() rows
 * @param int   $writer_app_id the application that wrote the message, 0 for a person
 * @param int[] $assistant_ids the applications Claude and Pinegrap AI write through, 0 for one not set up
 * @return int[]
 */
function ws_app_mention_audience($tokens, $writer_app_id, $assistant_ids)
{
    $leave_out = array_map('intval', (array) $assistant_ids);
    $leave_out[] = (int) $writer_app_id;

    return array_values(array_filter(ws_mentioned_app_ids($tokens), function ($id) use ($leave_out) {
        return !in_array($id, $leave_out, true);
    }));
}

/**
 * Does an application hear its mentions at all: it is active, its key has
 * not run out (the API refuses an expired key, api_authenticate()) and it
 * may read the workspace (workspace:read, or workspace:write, which includes
 * it), so a channel's words never reach an integration that could not read
 * them through the API. Its owner must also read the channel
 * (ws_app_reads_channel()).
 *
 * @param array    $app api_apps row: status, expires_at (0 for never), scopes (the list as stored)
 * @param int|null $now the moment the key's end is compared with; now when null
 * @return bool
 */
function ws_app_hears_mentions($app, $now = null)
{
    if ((string) ($app['status'] ?? '') !== 'active') {
        return false;
    }

    $expires = (int) ($app['expires_at'] ?? 0);

    if (($expires > 0) && ($expires < (($now === null) ? time() : (int) $now))) {
        return false;
    }

    $scopes = json_decode((string) ($app['scopes'] ?? ''), true);

    return is_array($scopes) && (in_array('workspace:read', $scopes, true) || in_array('workspace:write', $scopes, true));
}

/**
 * May an application read the channel the way the API lets it
 * (ws_api_channel_or_404()): with its owner's reach - a public channel, and
 * a private channel, a guest's room or a discussion the owner is in. Never
 * through inspection: a private channel an administrator opened for
 * inspection is open in that person's session alone and the API keeps no
 * session, so the owner is asked as somebody who cannot inspect (the role
 * decides nothing else in ws_can_read_channel()).
 *
 * @param array|null $reader  ws_viewer() of the application's owner, null when there is none
 * @param array      $channel
 * @return bool
 */
function ws_app_reads_channel($reader, $channel)
{
    return is_array($reader) && ws_can_read_channel(array('role' => 3) + $reader, $channel);
}

/**
 * Who an assistant's words go into a channel through - Claude's
 * (api_claude.php) and Pinegrap AI's (ai.php): the owner of its application
 * where that person may write, otherwise the person who asked, while they
 * still may. Being asked is the invitation: somebody who asks an assistant in
 * a discussion, or in a private channel that lets it be asked, brings it in
 * for that request, so the owner of its application need not be in the room.
 * The words stay the application's own message; the reader only answers
 * whether they may be written there. Never through inspection: the work is
 * carried on by whichever screen asks for it, and a private channel an
 * administrator opened for inspection in that session must not let anybody
 * in, so each is asked the way an application's reader is
 * (ws_app_reads_channel()). Whether the assistant may be asked there at all
 * is the caller's question (ws_claude_channel_allowed(),
 * ws_ai_channel_allowed()).
 *
 * @param array|null $owner   ws_viewer() of the application's owner, null when there is none
 * @param array|null $asker   ws_viewer() of the person who asked, null when they are gone
 * @param array      $channel the channel or discussion the request was asked in
 * @return array|null the reader to write through, null when neither may write there
 */
function ws_assistant_writer($owner, $asker, $channel)
{
    foreach (array($owner, $asker) as $reader) {
        if (is_array($reader) && is_array($channel) && ws_can_post_channel(array('role' => 3) + $reader, $channel)) {
            return $reader;
        }
    }

    return null;
}

/**
 * What workspace.app.mentioned carries: the message the way
 * workspace.message.created has it, where it was written - for a discussion
 * also the channel and the message it is about - and the application it
 * mentions. channel_id is where an answer goes
 * (POST /workspace/channels/{channel_id}/messages).
 *
 * @param array      $message ws_messages row
 * @param array      $channel the channel or discussion it was written in
 * @param array|null $thread  ws_threads row of a discussion, null in a channel
 * @param int        $app_id  the application mentioned
 * @param string     $text    the words as the application's owner reads them
 * @param bool       $edited  an edit added the mention
 * @return array
 */
function ws_app_mention_payload($message, $channel, $thread, $app_id, $text, $edited = false)
{
    $by_app = ((string) $message['sender_kind'] === 'app');

    return array(
        'id'               => (int) $message['id'],
        'channel_id'       => (int) $channel['id'],
        'channel'          => (string) $channel['name'],
        'channel_kind'     => (string) $channel['kind'],
        'thread'           => is_array($thread) ? array('channel_id' => (int) $thread['parent_channel_id'], 'message_id' => (int) $thread['message_id']) : null,
        'parent_id'        => (int) $message['parent_id'],
        'sender_id'        => $by_app ? 0 : (int) $message['sender_id'],
        'app_id'           => $by_app ? (int) $message['sender_id'] : 0,
        'mentioned_app_id' => (int) $app_id,
        'kind'             => (string) $message['kind'],
        'text'             => (string) $text,
        'body'             => (string) $message['body'],
        'task_id'          => (int) $message['task_id'],
        'edited'           => (bool) $edited,
    );
}

/**
 * Tells the applications a message mentions (<@app:N>) through their own
 * webhook subscriptions (workspace.app.mentioned), so a service outside the
 * site can answer in the channel with POST /workspace/channels/{id}/messages
 * and the site holds none of its code.
 *
 * Each application once per message: for the tags of a new message, or the
 * ones an edit adds. Only where it may read the channel
 * (ws_app_hears_mentions(), ws_app_reads_channel()); anywhere else nothing
 * is sent - not the words, not that the channel exists or who wrote in it -
 * and the application could not answer there either. The words go as its
 * owner reads them, so a record tag says no more than the owner may see.
 * Which applications are left out: ws_app_mention_audience(). The queue
 * sends the event to the mentioned application's subscriptions alone
 * (ws_webhook_event_app_id()).
 *
 * @param array $channel    the channel or discussion the message is in
 * @param int   $message_id
 * @param array $tokens     ws_tokens() of the tags to tell about
 * @param bool  $edited     an edit added them
 * @return int[] the applications an event was announced for
 */
function ws_message_tell_apps($channel, $message_id, $tokens, $edited = false)
{
    // Nearly every message mentions no application: settled without a query.
    if (empty(ws_mentioned_app_ids($tokens)) || !function_exists('pg_announce')) {
        return array();
    }

    $message = ws_message($message_id);

    if (!$message || ((int) $message['deleted_at'] > 0)) {
        return array();
    }

    $assistants = array(
        function_exists('ws_claude_config') ? (int) ws_claude_config()['app_id'] : 0,
        function_exists('ws_ai_config') ? (int) ws_ai_config()['app_id'] : 0,
    );

    $ids = ws_app_mention_audience($tokens, ((string) $message['sender_kind'] === 'app') ? (int) $message['sender_id'] : 0, $assistants);

    if (empty($ids)) {
        return array();
    }

    $apps = db_items("SELECT id, status, expires_at, owner_user_id, scopes FROM api_apps WHERE id IN (" . implode(',', $ids) . ")", 'id');
    $thread = ws_channel_is_thread($channel) ? ws_thread($channel['id']) : null;
    $told = array();

    foreach ($ids as $app_id) {
        if (!isset($apps[$app_id]) || !ws_app_hears_mentions($apps[$app_id])) {
            continue;
        }

        $owner = pg_load_user_row((int) $apps[$app_id]['owner_user_id']);
        $reader = is_array($owner) ? ws_viewer($owner) : null;

        if (!ws_app_reads_channel($reader, $channel)) {
            continue;
        }

        pg_announce('workspace.app.mentioned', ws_app_mention_payload($message, $channel, $thread, $app_id, ws_plain_text($reader, $message['body']), $edited));

        $told[] = $app_id;
    }

    return $told;
}

/**
 * The one application a workspace event is for, as the webhook queue asks
 * it (api_webhook_event_app_id()): the application a mention names
 * (workspace.app.mentioned), -1 for a mention that names none - sent to
 * nobody rather than to every subscriber - and 0 for every other event,
 * which any subscription listening to it hears.
 *
 * @param string $event
 * @param array  $payload
 * @return int
 */
function ws_webhook_event_app_id($event, $payload)
{
    if ((string) $event !== 'workspace.app.mentioned') {
        return 0;
    }

    $payload = (array) $payload;
    $named = $payload['mentioned_app_id'] ?? 0;
    $app_id = (is_int($named) || (is_string($named) && ctype_digit($named))) ? (int) $named : 0;

    return ($app_id > 0) ? $app_id : -1;
}

/**
 * The messages of a channel that changed after they were written - an edit,
 * a reaction, a tick, a vote - since a moment, up to the newest one the
 * reader already has. With a reader, also the ones that reader deleted for
 * themselves since then: the message stays as it is for everybody else, so
 * hiding it does not stamp touched_at (the others' screens would draw it
 * again for nothing), and only the reader's own row in ws_message_hides says
 * when it went. Their other screens and devices learn it here.
 *
 * @param int $channel_id
 * @param int $since    timestamp
 * @param int $upto_id  the newest message the reader holds
 * @param int $after_id only messages after this one (the API pages with it)
 * @param int $limit
 * @param int $user_id  the reader, 0 for none
 * @return array[]
 */
function ws_messages_touched($channel_id, $since, $upto_id, $after_id = 0, $limit = 50, $user_id = 0)
{
    if (!ws_interact_ready() || ((int) $since <= 0) || ((int) $upto_id <= 0)) {
        return array();
    }

    $limit = max(1, min(200, (int) $limit));
    $hidden = ws_message_hidden_since($user_id, $channel_id, $since, $upto_id, $after_id, $limit);

    return (array) db_items("SELECT * FROM ws_messages
        WHERE " . ws_messages_touched_where($channel_id, $since, $upto_id, $after_id, $hidden) . "
        ORDER BY id
        LIMIT " . $limit);
}

/**
 * The condition ws_messages_touched() reads with: the channel's messages
 * stamped at or after the moment, and the ones given besides (those the
 * reader hid since then), up to the newest one held and after the one given.
 * With none given it is the touched_at range alone, which idx_touched
 * answers.
 *
 * @param int   $channel_id
 * @param int   $since
 * @param int   $upto_id
 * @param int   $after_id
 * @param int[] $also_ids
 * @return string
 */
function ws_messages_touched_where($channel_id, $since, $upto_id, $after_id = 0, $also_ids = array())
{
    $also_ids = array_values(array_unique(array_filter(array_map('intval', (array) $also_ids), function ($id) {
        return $id > 0;
    })));

    $changed = "touched_at >= '" . (int) $since . "'";

    if (!empty($also_ids)) {
        $changed = "(" . $changed . " OR id IN (" . implode(',', $also_ids) . "))";
    }

    return "channel_id = '" . (int) $channel_id . "' AND " . $changed . " AND id <= '" . (int) $upto_id . "'"
        . (((int) $after_id > 0) ? " AND id > '" . (int) $after_id . "'" : '');
}

/**
 * What a screen open on a channel asks for on each look, the panel's
 * (ws_sync) and an app's (GET /workspace/sync) alike: the polls and approval
 * requests whose time ran out are closed first, so they are read closed;
 * then the messages after the newest one the reader holds, and the ones they
 * hold that changed since their previous look.
 *
 * @param int   $channel_id
 * @param int   $held_id the newest message the reader holds, 0 for none
 * @param int   $since   the moment of the previous look, 0 for none
 * @param array $options new: false reads no new messages (an earlier version
 *                       of the conversation is on the screen); first_page:
 *                       with nothing held, the newest this many rather than
 *                       the conversation from its start; after_id, limit,
 *                       user_id (the reader, whose own hidden messages count
 *                       as changed): as ws_messages_touched() takes them
 * @return array new, changed (ws_messages rows, oldest first)
 */
function ws_channel_news($channel_id, $held_id, $since, $options = array())
{
    $channel_id = (int) $channel_id;
    $held_id = (int) $held_id;

    ws_polls_autoclose($channel_id);
    ws_approvals_autoclose($channel_id);

    $new = array();

    if (!isset($options['new']) || $options['new']) {
        $new = (($held_id <= 0) && !empty($options['first_page']))
            ? ws_messages_page($channel_id, 0, (int) $options['first_page'])
            : ws_messages_since($channel_id, $held_id);
    }

    return array(
        'new'     => $new,
        'changed' => ws_messages_touched($channel_id, $since, $held_id, (int) ($options['after_id'] ?? 0), (int) ($options['limit'] ?? 50), (int) ($options['user_id'] ?? 0)),
    );
}

/**
 * Whether a message may be changed to this text: the checks of
 * ws_message_edit(), made on their own so that the API's dry run meets the
 * same refusals.
 *
 * @param array  $viewer
 * @param array  $message
 * @param string $body
 * @return array ok, error, body (the new text as it would be stored), channel
 */
function ws_message_edit_check($viewer, $message, $body)
{
    if (($message['sender_kind'] !== 'user') || ((int) $message['sender_id'] !== (int) $viewer['id'])
        || ((int) $message['deleted_at'] > 0) || !in_array($message['kind'], array('message', 'note', 'decision'), true)) {
        return array('ok' => false, 'error' => lang('You can only change your own messages.'));
    }

    if (!empty($message['locked'])) {
        return array('ok' => false, 'error' => lang('This decision records a change to a record and cannot be edited.'));
    }

    if (function_exists('ws_approval_of_message') && ws_approval_of_message($message['id'])) {
        return array('ok' => false, 'error' => lang('An approval request cannot be edited; take it back and ask again.'));
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

    return array('ok' => true, 'error' => '', 'body' => $body, 'channel' => $channel);
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
    $checked = ws_message_edit_check($viewer, $message, $body);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error']);
    }

    $body = $checked['body'];
    $channel = $checked['channel'];
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

    // So are the applications it mentions for the first time.
    ws_message_tell_apps($channel, $message['id'], $new, true);

    if ($channel['kind'] === 'thread') {
        ws_thread_copy_sync($message['id']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Whether the person may delete a message for everyone: the checks of
 * ws_message_delete(), made on their own for the API's dry run.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error, mine
 */
function ws_message_delete_check($viewer, $message)
{
    $mine = ($message['sender_kind'] === 'user') && ((int) $message['sender_id'] === (int) $viewer['id']);

    // A guest's message is taken out by staff.
    if (!in_array($message['sender_kind'], array('user', 'guest'), true) || (!$mine && ($viewer['role'] >= 3))) {
        return array('ok' => false, 'error' => lang('You can only delete your own messages.'), 'mine' => $mine);
    }

    if (!empty($message['locked']) && ($viewer['role'] >= 3)) {
        return array('ok' => false, 'error' => lang('This decision records a change to a record. People with the User role cannot delete it.'), 'mine' => $mine);
    }

    if (!ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        return array('ok' => false, 'error' => lang('Access denied.'), 'mine' => $mine);
    }

    return array('ok' => true, 'error' => '', 'mine' => $mine);
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
    $checked = ws_message_delete_check($viewer, $message);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error']);
    }

    $mine = $checked['mine'];

    // A checklist a task carries moves into the task before the text goes.
    ws_checklist_tasks_detach($message);

    db("UPDATE ws_messages SET body = '', file_id = 0, file_name = '', deleted_at = '" . time() . "' WHERE id = '" . (int) $message['id'] . "'");
    db("DELETE FROM ws_refs WHERE source_type = 'message' AND source_id = '" . (int) $message['id'] . "'");

    if (function_exists('ws_blocks_index')) {
        ws_blocks_index('message', (int) $message['id'], '');
    }
    ws_inbox_delete("message_id = '" . (int) $message['id'] . "' AND kind = 'mention'");
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
 * The messages of a channel the person deleted for themselves at or after a
 * moment, up to the newest one they hold and after the one given, lowest
 * first: what ws_messages_touched() adds to the changes for them. A message
 * past the first $limit of these could not make the first $limit of the
 * changes either, so no more are read.
 *
 * @param int $user_id
 * @param int $channel_id
 * @param int $since    timestamp
 * @param int $upto_id
 * @param int $after_id
 * @param int $limit
 * @return int[]
 */
function ws_message_hidden_since($user_id, $channel_id, $since, $upto_id, $after_id = 0, $limit = 200)
{
    if (((int) $user_id <= 0) || ((int) $since <= 0) || ((int) $upto_id <= 0) || !ws_message_hides_ready()) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT h.message_id FROM ws_message_hides h
        INNER JOIN ws_messages m ON m.id = h.message_id
        WHERE h.user_id = '" . (int) $user_id . "' AND h.hidden_at >= '" . (int) $since . "'
            AND m.channel_id = '" . (int) $channel_id . "' AND m.id <= '" . (int) $upto_id . "'"
            . (((int) $after_id > 0) ? " AND m.id > '" . (int) $after_id . "'" : '') . "
        ORDER BY h.message_id
        LIMIT " . max(1, min(200, (int) $limit))));
}

/**
 * Whether the person may delete a message for themselves: the checks of
 * ws_message_hide(), made on their own for the API's dry run.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error
 */
function ws_message_hide_check($viewer, $message)
{
    if (!ws_message_hides_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if (($message['kind'] === 'system') || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        return array('ok' => false, 'error' => lang('That message could not be found.'));
    }

    return array('ok' => true, 'error' => '');
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
    $checked = ws_message_hide_check($viewer, $message);

    if (!$checked['ok']) {
        return $checked;
    }

    // touched_at is left alone: nothing changed for anybody else. hidden_at
    // is what the person's other screens and devices look for
    // (ws_messages_touched()).
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
