<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the conversation timeline: the current conversation of a
 * channel as one well at the end of the tabs row (on a narrow screen at the
 * foot of Channel info), the days as columns, each run of messages one bar
 * cut into its writers' colours. Pointing at it shows a card with the
 * message's first words, its decision or note mark, and a shortcut row for
 * everything else it carries (a note, a task, a file, a table, a poll...)
 * that opens that thing; a click opens the message.
 *
 * One read, ws_conversation_timeline(), behind the panel action
 * ws_conversation_timeline; the strip is drawn by
 * assets/js/workspace_conversation_timeline.js. Only the conversation since
 * the last clear (ws_channel_floor()): an earlier version is never in it.
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
 * The most messages the strip holds: the newest of the conversation.
 */
define('WS_CONVERSATION_TIMELINE_MAX', 600);

/**
 * The longest first words a card shows, in characters.
 */
define('WS_CONVERSATION_TIMELINE_TEXT', 100);

/**
 * The conversation's messages as the strip draws them, for this reader.
 *
 * @param array    $viewer
 * @param array    $channel
 * @param int|null $limit WS_CONVERSATION_TIMELINE_MAX when null
 * @return array items (id, at, s: index into people, text, mark: decision,
 *               note or '', by: who marked it, tools: the card's rows), people
 *               (name, kind, tone), total, cut, now
 */
function ws_conversation_timeline($viewer, $channel, $limit = null)
{
    $channel_id = (int) $channel['id'];
    $limit = max(1, min(WS_CONVERSATION_TIMELINE_MAX, ($limit === null) ? WS_CONVERSATION_TIMELINE_MAX : (int) $limit));
    $floor = ws_channel_floor($channel_id);
    $where = "channel_id = '" . $channel_id . "' AND deleted_at = 0" . (($floor > 0) ? " AND id > '" . $floor . "'" : '');

    $rows = array_reverse((array) db_items("SELECT * FROM ws_messages WHERE " . $where . " ORDER BY id DESC LIMIT " . $limit));
    $total = (count($rows) < $limit) ? count($rows) : (int) db_value("SELECT COUNT(*) FROM ws_messages WHERE " . $where);

    // A message the reader deleted for themselves stays out, as it does
    // from their conversation.
    $hidden = ws_message_hidden_ids($viewer['id'], array_map(function ($row) { return (int) $row['id']; }, $rows));

    if (!empty($hidden)) {
        $rows = array_values(array_filter($rows, function ($row) use ($hidden) {
            return !isset($hidden[(int) $row['id']]);
        }));
    }

    $out = array('items' => array(), 'people' => array(), 'total' => $total, 'cut' => ($total > count($rows)), 'now' => time());

    if (empty($rows)) {
        return $out;
    }

    $ids = array();
    $tokens = array();
    $people_ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];

        foreach (ws_tokens($row['body']) as $token) {
            $tokens[] = $token;
        }

        if ($row['sender_kind'] === 'user') {
            $people_ids[] = (int) $row['sender_id'];
        }

        if (((int) $row['marked_by'] > 0) && !ws_mark_by_app($row)) {
            $people_ids[] = (int) $row['marked_by'];
        }
    }

    $refs = ws_refs_resolve($viewer, $tokens);
    $people = ws_people($people_ids);
    $agents = function_exists('ws_mcp_agent_answers_map') ? ws_mcp_agent_answers_map($ids) : array();
    $mail = function_exists('ws_mail_items_map') ? ws_mail_items_map(array_values(array_filter($rows, function ($row) { return $row['sender_kind'] === 'mail'; }))) : array();
    $views = array();
    $bodies = array();

    // An MCP agent's answer reads as the agent's words.
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $views[$id] = (($row['sender_kind'] === 'user') && isset($agents[$id]) && function_exists('ws_mcp_agent_answer_view'))
            ? ws_mcp_agent_answer_view($agents[$id], (string) $row['body'])
            : null;
        $bodies[$id] = $views[$id] ? (string) $views[$id]['text'] : (string) $row['body'];
    }

    $tools = ws_conversation_timeline_tools($viewer, $rows, $bodies, $refs);
    $keys = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $agent = $views[$id];
        $sender = ws_conversation_timeline_sender($row, $people, $agent, $mail);

        if (!isset($keys[$sender['key']])) {
            $keys[$sender['key']] = count($out['people']);
            $out['people'][] = array(
                'name' => $sender['name'],
                'kind' => $sender['kind'],
                // The colour: the order of first words, grey for the system.
                'tone' => ($sender['kind'] === 'system') ? -1 : count(array_filter($out['people'], function ($person) { return $person['kind'] !== 'system'; })),
            );
        }

        // The first words as the conversation reads them: the cut is made
        // on a stretch long enough for any first line, so a long message
        // costs no more than a short one.
        $body = $bodies[$id];
        $text = ws_plain_line($viewer, (mb_strlen($body) > 1500) ? mb_substr($body, 0, 1500) : $body, $refs);

        // A message marked as a decision or a note is that thing: the card
        // says so in a plain line, not a row.
        $mark = in_array($row['kind'], array('decision', 'note'), true) ? (string) $row['kind'] : '';

        $out['items'][] = array(
            'id'    => $id,
            'at'    => (int) $row['created_at'],
            's'     => $keys[$sender['key']],
            'text'  => ws_conversation_timeline_cut($text),
            'mark'  => $mark,
            'by'    => ($mark !== '') ? ws_mark_by_name($row, $people) : '',
            'tools' => $tools[$id] ?? array(),
        );
    }

    return $out;
}

/**
 * Who a message is by, as the strip names and colours them: a key that
 * keeps one writer one colour, the name and the kind of writer.
 *
 * @param array      $row    ws_messages
 * @param array      $people ws_people()
 * @param array|null $agent  ws_mcp_agent_answer_view() for an MCP agent's answer
 * @param array      $mail   ws_mail_items_map()
 * @return array key, name, kind (person, agent, app, guest, mail, system)
 */
function ws_conversation_timeline_sender($row, $people, $agent = null, $mail = array())
{
    $id = (int) $row['sender_id'];

    if ($agent) {
        $name = (string) ($agent['sender']['name'] ?? '');

        return array('key' => 'x' . $name, 'name' => $name, 'kind' => 'agent');
    }

    if ($row['sender_kind'] === 'user') {
        return array('key' => 'u' . $id, 'name' => (string) ($people[$id]['name'] ?? lang('Former member')), 'kind' => 'person');
    }

    if ($row['sender_kind'] === 'app') {
        $app = ws_app_sender($id);

        return array('key' => 'a' . $id, 'name' => (string) $app['name'], 'kind' => (!empty($app['agent']) || !empty($app['claude']) || !empty($app['ai'])) ? 'agent' : 'app');
    }

    if (($row['sender_kind'] === 'guest') && function_exists('ws_guest_sender')) {
        return array('key' => 'g' . $id, 'name' => (string) (ws_guest_sender($id)['name'] ?? lang('Guest')), 'kind' => 'guest');
    }

    if (($row['sender_kind'] === 'mail') && function_exists('ws_mail_sender')) {
        $sender = ws_mail_sender($mail[$id] ?? null);
        $name = (string) ($sender['name'] ?? lang('E-mail'));

        return array('key' => 'm' . $name, 'name' => $name, 'kind' => 'mail');
    }

    return array('key' => 's', 'name' => lang('System'), 'kind' => 'system');
}

/**
 * What the messages carry, as the card's shortcut rows, one query a kind
 * for the whole set: notes shared in the channel, tasks, files (a canvas,
 * a picture, a voice message or any other file), the tables written in a
 * message and the tables a binding line names, polls, agent questions,
 * approvals, check-ins, status updates, read receipts and agent requests.
 * A row is only drawn for what the reader may open; each says where it
 * leads (go): the note, the task, the file's or picture's preview, the
 * table's screen, else the message.
 *
 * @param array   $viewer
 * @param array[] $rows   ws_messages
 * @param array   $bodies message id => the words as the conversation reads them
 * @param array   $refs   ws_refs_resolve() of the messages' tokens
 * @return array message id => list of ws_conversation_timeline_tool() rows
 */
function ws_conversation_timeline_tools($viewer, $rows, $bodies = array(), $refs = array())
{
    $out = array();
    $ids = array();
    $files = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $ids[] = $id;

        if ((int) $row['file_id'] > 0) {
            $files[$id] = $row;
        }
    }

    if (empty($ids)) {
        return $out;
    }

    $in = implode(',', $ids);

    // Notes shared in the channel, each only for a reader who may read it
    // and none behind a closed lock (locking takes a note's shares back; the
    // gate holds the title of an untitled one, its first line, all the same).
    if (function_exists('ws_notes_ready') && ws_notes_ready()) {
        $shared = (array) db_items("SELECT note_id, message_id FROM ws_note_shares WHERE message_id IN (" . $in . ") ORDER BY id");

        if (!empty($shared)) {
            $note_ids = array_values(array_unique(array_map(function ($share) { return (int) $share['note_id']; }, $shared)));
            $shares = ws_note_shares_map($note_ids);
            $notes = array();
            $seen = array();

            foreach ((array) db_items("SELECT * FROM ws_notes WHERE id IN (" . implode(',', $note_ids) . ")") as $note) {
                $notes[(int) $note['id']] = $note;
            }

            foreach ($shared as $share) {
                $note = $notes[(int) $share['note_id']] ?? null;
                $key = (int) $share['message_id'] . ':' . (int) $share['note_id'];

                if ($note && !isset($seen[$key]) && (ws_note_access($viewer, $note, $shares[(int) $note['id']] ?? array()) !== '')
                    && !(function_exists('ws_note_lock_closed') && ws_note_lock_closed($viewer, $note))) {
                    $seen[$key] = true;
                    $out[(int) $share['message_id']][] = ws_conversation_timeline_tool('note', ws_note_title($viewer, $note), '', '', array('go' => 'note', 'id' => (int) $note['id']));
                }
            }
        }
    }

    // A task made of a message, and a task's card; one row a task.
    $seen = array();

    foreach ((array) db_items("SELECT id, title, status, source_message_id FROM ws_tasks WHERE source_message_id IN (" . $in . ") ORDER BY id") as $task) {
        $seen[(int) $task['source_message_id'] . ':' . (int) $task['id']] = true;
        $out[(int) $task['source_message_id']][] = ws_conversation_timeline_task($task);
    }

    $cards = array();

    foreach ($rows as $row) {
        if (($row['kind'] === 'task') && ((int) $row['task_id'] > 0)) {
            $cards[(int) $row['task_id']] = (int) $row['id'];
        }
    }

    if (!empty($cards)) {
        foreach ((array) db_items("SELECT id, title, status FROM ws_tasks WHERE id IN (" . implode(',', array_keys($cards)) . ")") as $task) {
            $message_id = $cards[(int) $task['id']];

            if (!isset($seen[$message_id . ':' . (int) $task['id']])) {
                $out[$message_id][] = ws_conversation_timeline_task($task);
            }
        }
    }

    // Files: one read of the stored files, the canvases among them, the
    // live pages and the recordings' lengths.
    if (!empty($files)) {
        $file_ids = array_values(array_unique(array_map(function ($row) { return (int) $row['file_id']; }, $files)));
        $html_ids = array();
        $stored = array();
        $pages = array();
        $voices = array();

        foreach ($files as $row) {
            if (preg_match('/\.html?$/i', (string) $row['file_name'])) {
                $html_ids[] = (int) $row['file_id'];
            }
        }

        foreach ((array) db_items("SELECT id, name, size FROM files WHERE id IN (" . implode(',', $file_ids) . ")") as $file) {
            $stored[(int) $file['id']] = $file;
        }

        $canvases = function_exists('ws_canvas_message_states') ? ws_canvas_message_states($file_ids) : array();

        if (!empty($html_ids) && function_exists('pg_file_versions_ready') && pg_file_versions_ready()) {
            foreach ((array) db_values("SELECT DISTINCT file_id FROM file_versions WHERE format = 'html' AND file_id IN (" . implode(',', $html_ids) . ")") as $file_id) {
                $pages[(int) $file_id] = true;
            }
        }

        if (function_exists('ws_voice_schema_ready') && ws_voice_schema_ready()) {
            foreach ((array) db_items("SELECT file_id, duration_ms FROM ws_voice WHERE file_id IN (" . implode(',', $file_ids) . ")") as $voice) {
                $voices[(int) $voice['file_id']] = (int) $voice['duration_ms'];
            }
        }

        foreach ($files as $id => $row) {
            $file_id = (int) $row['file_id'];

            // A file no longer stored opens nothing: no row.
            if (isset($stored[$file_id])) {
                $out[$id][] = ws_conversation_timeline_file($row, $stored[$file_id], $canvases[$file_id] ?? null, isset($pages[$file_id]), $voices[$file_id] ?? 0);
            }
        }
    }

    // Tables written in a message: a row each, named by its header cells.
    foreach ($rows as $row) {
        $id = (int) $row['id'];

        foreach (ws_conversation_timeline_tables((string) ($bodies[$id] ?? $row['body'])) as $table) {
            $cells = array();

            foreach ($table['header'] as $cell) {
                $cell = ws_plain_line($viewer, $cell, $refs);

                if ($cell !== '') {
                    $cells[] = $cell;
                }
            }

            $out[$id][] = ws_conversation_timeline_tool('table', implode(' · ', $cells), lang(array('string' => '{var:1} rows', 'vars' => (int) $table['rows'])));
        }
    }

    // A binding line names a canvas's table: its screen, for a reader who
    // may read the table.
    if (function_exists('ws_canvas_binding_line_refs') && function_exists('ws_wtable_for')) {
        $bound = array();

        foreach (ws_canvas_binding_line_refs($ids) as $line_id => $line) {
            $table = ws_wtable_for($viewer, (int) $line['table'], 'view');

            if ($table) {
                $bound[(int) $line_id] = $table;
            }
        }

        if (!empty($bound)) {
            $counts = array();
            $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

            foreach ((array) db_items("SELECT table_id, COUNT(*) AS n FROM ws_wtable_rows
                WHERE table_id IN (" . implode(',', array_unique(array_map(function ($table) { return (int) $table['id']; }, $bound))) . ")
                GROUP BY table_id") as $count) {
                $counts[(int) $count['table_id']] = (int) $count['n'];
            }

            foreach ($bound as $line_id => $table) {
                $out[$line_id][] = ws_conversation_timeline_tool('table', (string) $table['name'], lang(array('string' => '{var:1} rows', 'vars' => $counts[(int) $table['id']] ?? 0)), '',
                    array('go' => 'table', 'url' => $base . 'workspace_wtables.php?id=' . (int) $table['id']));
            }
        }
    }

    // Polls and agent questions: the votes of each option.
    $polls = (array) db_items("SELECT id, message_id, closed_at, withdrawn_at, agent_question FROM ws_polls WHERE message_id IN (" . $in . ")");

    if (!empty($polls)) {
        $poll_ids = array_map(function ($poll) { return (int) $poll['id']; }, $polls);
        $options = array();

        foreach ((array) db_items("SELECT o.poll_id, o.label, COUNT(v.option_id) AS votes FROM ws_poll_options o
            LEFT JOIN ws_poll_votes v ON v.option_id = o.id
            WHERE o.poll_id IN (" . implode(',', $poll_ids) . ")
            GROUP BY o.id ORDER BY o.poll_id, o.sort, o.id") as $option) {
            $options[(int) $option['poll_id']][] = array('label' => (string) $option['label'], 'votes' => (int) $option['votes']);
        }

        foreach ($polls as $poll) {
            $question = !empty($poll['agent_question']);
            $result = $question
                ? ws_conversation_timeline_question_result($options[(int) $poll['id']] ?? array(), (int) $poll['closed_at'] > 0, (int) $poll['withdrawn_at'] > 0)
                : ws_conversation_timeline_poll_result($options[(int) $poll['id']] ?? array(), (int) $poll['closed_at'] > 0, (int) $poll['withdrawn_at'] > 0);
            $out[(int) $poll['message_id']][] = ws_conversation_timeline_tool($question ? 'question' : 'poll', $result['name'], $result['result'], $result['state']);
        }
    }

    // Approvals: their outcome, and while open how many said yes.
    $approvals = (array) db_items("SELECT id, message_id, title, outcome FROM ws_approvals WHERE message_id IN (" . $in . ")");

    if (!empty($approvals)) {
        $counts = array();

        foreach ((array) db_items("SELECT approval_id, decision, COUNT(*) AS n FROM ws_approval_people
            WHERE approval_id IN (" . implode(',', array_map(function ($approval) { return (int) $approval['id']; }, $approvals)) . ")
            GROUP BY approval_id, decision") as $count) {
            $counts[(int) $count['approval_id']][(string) $count['decision']] = (int) $count['n'];
        }

        foreach ($approvals as $approval) {
            $result = ws_conversation_timeline_approval_result((string) $approval['outcome'], $counts[(int) $approval['id']] ?? array());
            $out[(int) $approval['message_id']][] = ws_conversation_timeline_tool('approval', (string) $approval['title'], $result['result'], $result['state']);
        }
    }

    // Read receipts asked for, and how many have read.
    if (function_exists('ws_acks_ready') && ws_acks_ready()) {
        $read = array();

        foreach ((array) db_items("SELECT message_id, COUNT(*) AS n FROM ws_acks WHERE message_id IN (" . $in . ") GROUP BY message_id") as $ack) {
            $read[(int) $ack['message_id']] = (int) $ack['n'];
        }

        foreach ((array) db_items("SELECT message_id FROM ws_ack_requests WHERE message_id IN (" . $in . ")") as $asked) {
            $count = $read[(int) $asked['message_id']] ?? 0;
            $out[(int) $asked['message_id']][] = ws_conversation_timeline_tool('ack', lang(array('string' => '{var:1} read', 'vars' => $count)), '', $count ? 'ok' : 'open');
        }
    }

    // Status updates and check-ins.
    if (function_exists('ws_status_healths') && pg_schema_has('ws_status_updates', 'health')) {
        $healths = ws_status_healths();

        foreach ((array) db_items("SELECT message_id, health FROM ws_status_updates WHERE message_id IN (" . $in . ")") as $status) {
            $out[(int) $status['message_id']][] = ws_conversation_timeline_tool('status', (string) ($healths[$status['health']] ?? ''), '', ($status['health'] === 'on_track') ? 'ok' : (($status['health'] === 'off_track') ? 'bad' : 'open'));
        }
    }

    if (pg_schema_has('ws_checkins', 'question')) {
        foreach ((array) db_items("SELECT message_id, question FROM ws_checkins WHERE message_id IN (" . $in . ")") as $checkin) {
            $out[(int) $checkin['message_id']][] = ws_conversation_timeline_tool('checkin', (string) $checkin['question']);
        }
    }

    // The agents a message asked, and where their answers stand.
    $requests = array();

    foreach ((array) db_items("SELECT message_id, status FROM ws_ai_requests WHERE message_id IN (" . $in . ") ORDER BY id") as $request) {
        $requests[(int) $request['message_id']][] = (string) $request['status'];
    }

    foreach ($requests as $message_id => $statuses) {
        $result = ws_conversation_timeline_request_result($statuses);
        $out[$message_id][] = ws_conversation_timeline_tool('agent', $result['result'], '', $result['state']);
    }

    foreach ($out as $message_id => $list) {
        $out[$message_id] = ws_conversation_timeline_order($list);
    }

    return $out;
}

/**
 * A message's file as its row: a canvas (its drawing opens as its card
 * opens it, in the picture window; a live page at its message), a picture,
 * a voice message (at its message, where it plays) or any other file (its
 * preview). Pure but for lang() and the address.
 *
 * @param array $row         ws_messages: file_id, file_name
 * @param array $file        files: name, size
 * @param array|null $canvas ws_canvas_message_states() entry of a drawing
 * @param bool  $page        the file is a live page (an .html with versions)
 * @param int   $duration_ms a recording's length, 0 when unknown
 * @return array
 */
function ws_conversation_timeline_file($row, $file, $canvas = null, $page = false, $duration_ms = 0)
{
    $name = (string) $row['file_name'];
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $kind = ws_file_kind($name);
    $url = PATH . encode_url_path((string) $file['name']);

    if ($canvas && ($kind === 'image') && ($extension === 'png')) {
        return ws_conversation_timeline_tool('canvas', ws_canvas_name($row), '', '', array('go' => 'picture', 'url' => ws_canvas_picture_url($url, $canvas)));
    }

    if ($page && in_array($extension, array('html', 'htm'), true)) {
        return ws_conversation_timeline_tool('canvas', ws_canvas_name($row));
    }

    if ($kind === 'image') {
        return ws_conversation_timeline_tool('picture', $name, '', '', array('go' => 'picture', 'url' => $url));
    }

    // A recording made in the composer is named voice-<time>
    // (ws_voice_is_recording()).
    if ($kind === 'audio') {
        $recording = function_exists('ws_voice_is_recording') ? ws_voice_is_recording($name) : (strpos($name, 'voice-') === 0);

        return ws_conversation_timeline_tool('voice', $recording ? lang('Voice message') : $name, ($duration_ms > 0) ? ws_conversation_timeline_duration($duration_ms) : '');
    }

    return ws_conversation_timeline_tool('file', $name, function_exists('ws_note_media_size_label') ? ws_note_media_size_label((int) $file['size']) : '', '', array(
        'go'     => 'file',
        'url'    => $url,
        'fk'     => $kind,
        'office' => function_exists('pg_office_preview_kind') && (pg_office_preview_kind($name) !== ''),
    ));
}

/**
 * The tables written in a message as the conversation draws them: a header
 * row, the row of dashes under it, then the rows that follow (at most 200);
 * none inside a block of code. Pure.
 *
 * @param string $body
 * @return array[] header (the header's cells as written), rows (the rows under it)
 */
function ws_conversation_timeline_tables($body)
{
    $body = (string) $body;

    if (strpos($body, '|') === false) {
        return array();
    }

    $lines = preg_split('/\R/', $body);
    $fenced = ws_code_fence_lines($lines);
    $count = count($lines);
    $pattern = '/^\s*\|.*\|\s*$/';
    $out = array();

    for ($i = 0; $i < $count - 1; $i++) {
        if (isset($fenced[$i]) || isset($fenced[$i + 1]) || !preg_match($pattern, $lines[$i]) || (ws_table_alignments($lines[$i + 1]) === null)) {
            continue;
        }

        $rows = 0;
        $j = $i + 2;

        while (($j < $count) && !isset($fenced[$j]) && preg_match($pattern, $lines[$j]) && ($rows < 200)) {
            $rows++;
            $j++;
        }

        $out[] = array('header' => ws_table_cells($lines[$i]), 'rows' => $rows);
        $i = $j - 1;
    }

    return $out;
}

/**
 * A recording's length as the player shows it: 0:42, 12:05, 1:02:09. Pure.
 *
 * @param int $milliseconds
 * @return string
 */
function ws_conversation_timeline_duration($milliseconds)
{
    $seconds = (int) round(max(0, (int) $milliseconds) / 1000);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $rest = str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);

    return ($hours > 0) ? $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ':' . $rest : $minutes . ':' . $rest;
}

/**
 * A message's rows in the card's order: what it shares first (a note, a
 * task, its file, its tables), then what it asks (a poll, a question, an
 * approval) and what came of it; rows of one kind as they came. Pure.
 *
 * @param array[] $rows ws_conversation_timeline_tool() rows
 * @return array[]
 */
function ws_conversation_timeline_order($rows)
{
    $order = array_flip(array('note', 'task', 'canvas', 'picture', 'file', 'voice', 'table', 'poll', 'question', 'approval', 'checkin', 'status', 'ack', 'agent'));
    $keyed = array();

    foreach (array_values((array) $rows) as $index => $row) {
        $keyed[] = array($order[$row['k']] ?? count($order), $index, $row);
    }

    usort($keyed, function ($a, $b) {
        return ($a[0] === $b[0]) ? ($a[1] - $b[1]) : ($a[0] - $b[0]);
    });

    return array_map(function ($entry) { return $entry[2]; }, $keyed);
}

/**
 * One row of a message's card. Pure.
 *
 * @param string $kind   note, task, canvas, picture, file, voice, table, poll, question, approval, checkin, status, ack, agent
 * @param string $text   the thing's name (a title, a file name, an outcome)
 * @param string $result what came of it, at the row's right (a status, a size, votes)
 * @param string $state  ok, bad, open or '' (how it stands)
 * @param array  $go     where the row leads: go (note, task, file, picture,
 *                       table; message by default) and what that needs (id, url,
 *                       fk: the file's kind, office: read on the server)
 * @return array k, text, result, state, go and the rest of $go
 */
function ws_conversation_timeline_tool($kind, $text = '', $result = '', $state = '', $go = array())
{
    $go = (array) $go;
    $go['go'] = in_array($go['go'] ?? '', array('note', 'task', 'file', 'picture', 'table'), true) ? $go['go'] : 'message';

    return array(
        'k'      => (string) $kind,
        'text'   => ws_conversation_timeline_cut((string) $text, 80),
        'result' => (string) $result,
        'state'  => in_array($state, array('ok', 'bad', 'open'), true) ? $state : '',
    ) + $go;
}

/**
 * A task as its row: its title and status; it opens the task. Pure but for
 * lang().
 *
 * @param array $task ws_tasks: id, title, status
 * @return array
 */
function ws_conversation_timeline_task($task)
{
    $status = (string) $task['status'];

    return ws_conversation_timeline_tool('task', (string) $task['title'], ws_task_status_label($status), ($status === 'done') ? 'ok' : (($status === 'cancelled') ? 'bad' : 'open'),
        array('go' => 'task', 'id' => (int) $task['id']));
}

/**
 * Text cut to a length on a word, an ellipsis after it. Pure.
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function ws_conversation_timeline_cut($text, $max = WS_CONVERSATION_TIMELINE_TEXT)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    if (mb_strlen($text) <= $max) {
        return $text;
    }

    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');

    // On the last space, when it leaves most of the words.
    if (($space !== false) && ($space > ($max * 0.6))) {
        $cut = mb_substr($cut, 0, $space);
    }

    return rtrim($cut, " \t,.;:-") . '…';
}

/**
 * The votes of a poll's options: the count, the leading option and whether
 * two or more share the lead. Pure.
 *
 * @param array[] $options label, votes; in the poll's order
 * @return array votes, top (the leading option or null), tie
 */
function ws_conversation_timeline_votes($options)
{
    $votes = 0;
    $top = null;
    $tie = false;

    foreach ((array) $options as $option) {
        $votes += (int) $option['votes'];

        if (($top === null) || ((int) $option['votes'] > (int) $top['votes'])) {
            $top = $option;
            $tie = false;
        } elseif ((int) $option['votes'] === (int) $top['votes']) {
            $tie = true;
        }
    }

    return array('votes' => $votes, 'top' => ($votes > 0) ? $top : null, 'tie' => $tie);
}

/**
 * A poll as its row: what stands (the leading option, the winner once
 * closed, a tie, no votes yet, taken back) and the votes. Pure but for
 * lang().
 *
 * @param array[] $options label, votes; in the poll's order
 * @param bool    $closed
 * @param bool    $withdrawn
 * @return array name, result, state
 */
function ws_conversation_timeline_poll_result($options, $closed, $withdrawn = false)
{
    if ($withdrawn) {
        return array('name' => lang('Taken back'), 'result' => '', 'state' => 'bad');
    }

    $count = ws_conversation_timeline_votes($options);
    $votes = $count['votes'] ? lang(array('string' => '{var:1} votes', 'vars' => $count['votes'])) : '';
    $label = $count['top'] ? ws_conversation_timeline_cut((string) $count['top']['label'], 40) : '';

    if ($closed) {
        return ($label !== '' && !$count['tie'])
            ? array('name' => lang(array('string' => 'Closed: {var:1}', 'vars' => $label)), 'result' => $votes, 'state' => 'ok')
            : array('name' => lang('Closed'), 'result' => $votes, 'state' => '');
    }

    if ($label === '') {
        return array('name' => lang('No votes yet'), 'result' => '', 'state' => 'open');
    }

    return $count['tie']
        ? array('name' => lang('Tied'), 'result' => $votes, 'state' => 'open')
        : array('name' => lang(array('string' => '{var:1} leads', 'vars' => $label)), 'result' => $votes, 'state' => 'open');
}

/**
 * An agent's question as its row: the answer once given, else that it
 * waits for one. Pure but for lang().
 *
 * @param array[] $options label, votes
 * @param bool    $closed
 * @param bool    $withdrawn
 * @return array name, result, state
 */
function ws_conversation_timeline_question_result($options, $closed, $withdrawn = false)
{
    if ($withdrawn) {
        return array('name' => lang('Taken back'), 'result' => '', 'state' => 'bad');
    }

    $count = ws_conversation_timeline_votes($options);

    if ($count['top']) {
        return array('name' => ws_conversation_timeline_cut((string) $count['top']['label'], 40), 'result' => lang('Answered'), 'state' => 'ok');
    }

    return $closed
        ? array('name' => lang('Closed'), 'result' => '', 'state' => '')
        : array('name' => lang('Awaiting an answer'), 'result' => '', 'state' => 'open');
}

/**
 * What came of an approval request: its outcome, and while open who said
 * yes of everyone asked ("2 / 3"). Pure but for lang().
 *
 * @param string $outcome open, approved, rejected, withdrawn, expired
 * @param array  $counts  decision (pending, approved, rejected) => people
 * @return array result, state
 */
function ws_conversation_timeline_approval_result($outcome, $counts)
{
    $states = array(
        'approved'  => array(lang('Approved'), 'ok'),
        'rejected'  => array(lang('Rejected'), 'bad'),
        'withdrawn' => array(lang('Withdrawn'), 'bad'),
        'expired'   => array(lang('Expired'), 'bad'),
    );

    if (isset($states[$outcome])) {
        return array('result' => $states[$outcome][0], 'state' => $states[$outcome][1]);
    }

    return array(
        'result' => (int) ($counts['approved'] ?? 0) . ' / ' . array_sum(array_map('intval', (array) $counts)),
        'state'  => 'open',
    );
}

/**
 * Where the agents a message asked stand: the request still in work first,
 * then a failed one, then answered. Pure but for lang().
 *
 * @param string[] $statuses ws_ai_requests.status of the message's requests
 * @return array result, state
 */
function ws_conversation_timeline_request_result($statuses)
{
    $statuses = array_values((array) $statuses);
    $working = count(array_intersect($statuses, array('queued', 'sent', 'running')));
    $failed = count(array_intersect($statuses, array('failed')));
    $answered = count(array_intersect($statuses, array('answered')));

    if ($working) {
        return array('result' => lang('Working on it'), 'state' => 'open');
    }

    if ($failed) {
        return array('result' => lang('Failed'), 'state' => 'bad');
    }

    if ($answered) {
        return array('result' => (count($statuses) > 1) ? lang(array('string' => '{var:1} answered', 'vars' => $answered)) : lang('Answered'), 'state' => 'ok');
    }

    return array('result' => lang('Cancelled'), 'state' => '');
}

/**
 * The words of the strip (assets/js/workspace_conversation_timeline.js).
 *
 * @return array key => text
 */
function ws_conversation_timeline_js_strings()
{
    return array(
        'ctl_label'         => lang('Conversation timeline'),
        'ctl_heading'       => lang('The conversation'),
        'ctl_cut'           => ws_js_template('The newest {var:1} of {var:2} messages', 2),
        'ctl_empty'         => lang('No message yet.'),
        'ctl_failed'        => lang('The timeline could not be read.'),
        'ctl_open'          => lang('Open the message'),
        'ctl_after'         => ws_js_template('{var:1} after the message before', 1),
        'ctl_sec'           => ws_js_template('{var:1} s', 1),
        'ctl_min'           => ws_js_template('{var:1} min', 1),
        'ctl_hour'          => ws_js_template('{var:1} h {var:2} min', 2),
        'ctl_day'           => ws_js_template('{var:1} d {var:2} h', 2),
        'ctl_no_text'       => lang('(no text)'),
        'ctl_more'          => ws_js_template('{var:1} more, in the message', 1),
        'ctl_marked_by'     => ws_js_template('marked by {var:1}', 1),
        'ctl_today'         => lang('Today'),
        'ctl_yesterday'     => lang('Yesterday'),
        'ctl_today_at'      => ws_js_template('Today {var:1}', 1),
        'ctl_yesterday_at'  => ws_js_template('Yesterday {var:1}', 1),
        'ctl_zoom_in'       => lang('Zoom in (+, or Ctrl and the wheel)'),
        'ctl_zoom_out'      => lang('Zoom out (-, or Ctrl and the wheel)'),
        'ctl_kind_agent'    => lang('AI agent'),
        'ctl_kind_app'      => lang('Application'),
        'ctl_kind_guest'    => lang('Guest'),
        'ctl_kind_mail'     => lang('E-mail'),
        'ctl_kind_system'   => lang('System'),
        'ctl_tool_decision' => lang('Decision'),
        'ctl_tool_note'     => lang('Note'),
        'ctl_tool_task'     => lang('Task'),
        'ctl_tool_canvas'   => lang('Canvas'),
        'ctl_tool_picture'  => lang('Picture'),
        'ctl_tool_file'     => lang('File'),
        'ctl_tool_voice'    => lang('Voice'),
        'ctl_tool_table'    => lang('Table'),
        'ctl_tool_poll'     => lang('Poll'),
        'ctl_tool_question' => lang('Agent question'),
        'ctl_tool_approval' => lang('Approval'),
        'ctl_tool_ack'      => lang('Read receipt'),
        'ctl_tool_status'   => lang('Status update'),
        'ctl_tool_checkin'  => lang('Check-in'),
        'ctl_tool_agent'    => lang('Agent request'),
    );
}
