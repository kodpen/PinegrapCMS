<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the conversation timeline: the current conversation of a
 * channel as one strip in the tabs row (on a narrow screen at the foot of
 * Channel info), each turn a block in the colour of who wrote it, the waits
 * between them as the strip's grey. Pointing at it shows a card with the
 * message's first words, the channel tool it carries (a decision, a poll,
 * an approval, a task...) and what came of it; a click opens the message.
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
 * @return array items (id, at, s: index into people, text, tools), people
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
    $tools = ws_conversation_timeline_tools($rows, $people);
    $agents = function_exists('ws_mcp_agent_answers_map') ? ws_mcp_agent_answers_map($ids) : array();
    $mail = function_exists('ws_mail_items_map') ? ws_mail_items_map(array_values(array_filter($rows, function ($row) { return $row['sender_kind'] === 'mail'; }))) : array();
    $keys = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $agent = (($row['sender_kind'] === 'user') && isset($agents[$id]) && function_exists('ws_mcp_agent_answer_view'))
            ? ws_mcp_agent_answer_view($agents[$id], (string) $row['body'])
            : null;
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
        $body = $agent ? (string) $agent['text'] : (string) $row['body'];
        $text = ws_plain_line($viewer, (mb_strlen($body) > 1500) ? mb_substr($body, 0, 1500) : $body, $refs);

        $out['items'][] = array(
            'id'    => $id,
            'at'    => (int) $row['created_at'],
            's'     => $keys[$sender['key']],
            'text'  => ws_conversation_timeline_cut($text),
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
 * The channel tools the messages carry and what came of each, one query a
 * tool for the whole set: decision and note marks, tasks, files, polls,
 * approvals, read receipts, status updates, check-ins and agent requests.
 *
 * @param array[] $rows   ws_messages
 * @param array   $people ws_people() of the senders and markers
 * @return array message id => list of k (the tool), text, result, state (ok, bad, open or '')
 */
function ws_conversation_timeline_tools($rows, $people)
{
    $out = array();
    $ids = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $ids[] = $id;

        if (in_array($row['kind'], array('decision', 'note'), true)) {
            $by = ws_mark_by_name($row, $people);
            $out[$id][] = ws_conversation_timeline_tool($row['kind'], '', ($by !== '') ? lang(array('string' => 'Marked by {var:1}', 'vars' => $by)) : '', ($row['kind'] === 'decision') ? 'ok' : '');
        }

        if ((int) $row['file_id'] > 0) {
            $kind = ws_file_kind($row['file_name']);
            $out[$id][] = ws_conversation_timeline_tool(($kind === 'audio') ? 'voice' : (($kind === 'image') ? 'picture' : 'file'), (string) $row['file_name']);
        }
    }

    $in = implode(',', $ids);

    // A task made of a message, and a task's card; one line a task.
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

    // Polls: the votes of each option.
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
            $result = ws_conversation_timeline_poll_result($options[(int) $poll['id']] ?? array(), (int) $poll['closed_at'] > 0, (int) $poll['withdrawn_at'] > 0);
            $out[(int) $poll['message_id']][] = ws_conversation_timeline_tool(!empty($poll['agent_question']) ? 'question' : 'poll', '', $result['result'], $result['state']);
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
            $out[(int) $asked['message_id']][] = ws_conversation_timeline_tool('ack', '', lang(array('string' => '{var:1} read', 'vars' => $count)), $count ? 'ok' : 'open');
        }
    }

    // Status updates and check-ins.
    if (function_exists('ws_status_healths') && pg_schema_has('ws_status_updates', 'health')) {
        $healths = ws_status_healths();

        foreach ((array) db_items("SELECT message_id, health FROM ws_status_updates WHERE message_id IN (" . $in . ")") as $status) {
            $out[(int) $status['message_id']][] = ws_conversation_timeline_tool('status', '', (string) ($healths[$status['health']] ?? ''), ($status['health'] === 'on_track') ? 'ok' : (($status['health'] === 'off_track') ? 'bad' : 'open'));
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
        $out[$message_id][] = ws_conversation_timeline_tool('agent', '', $result['result'], $result['state']);
    }

    return $out;
}

/**
 * One tool of a message as the card lists it. Pure.
 *
 * @param string $kind   decision, note, task, file, picture, voice, poll, question, approval, ack, status, checkin, agent
 * @param string $text   what it is about (a title, a file name, a question)
 * @param string $result what came of it
 * @param string $state  ok, bad, open or '' (the result's colour)
 * @return array
 */
function ws_conversation_timeline_tool($kind, $text = '', $result = '', $state = '')
{
    return array(
        'k'      => (string) $kind,
        'text'   => ws_conversation_timeline_cut((string) $text, 80),
        'result' => (string) $result,
        'state'  => in_array($state, array('ok', 'bad', 'open'), true) ? $state : '',
    );
}

/**
 * A task as the card lists it: its title and status. Pure but for lang().
 *
 * @param array $task ws_tasks: id, title, status
 * @return array
 */
function ws_conversation_timeline_task($task)
{
    $status = (string) $task['status'];
    $tool = ws_conversation_timeline_tool('task', (string) $task['title'], ws_task_status_label($status), ($status === 'done') ? 'ok' : (($status === 'cancelled') ? 'bad' : 'open'));
    $tool['id'] = (int) $task['id'];

    return $tool;
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
 * What came of a poll: taken back, the winner once closed, else the votes
 * and the leading option. A tie leads with none. Pure but for lang().
 *
 * @param array[] $options label, votes; in the poll's order
 * @param bool    $closed
 * @param bool    $withdrawn
 * @return array result, state
 */
function ws_conversation_timeline_poll_result($options, $closed, $withdrawn = false)
{
    if ($withdrawn) {
        return array('result' => lang('Taken back'), 'state' => 'bad');
    }

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

    $count = lang(array('string' => '{var:1} votes', 'vars' => $votes));

    if (!$votes || $tie || ($top === null)) {
        return array('result' => $closed ? lang('Closed') . ' · ' . $count : $count, 'state' => $closed ? '' : 'open');
    }

    $label = ws_conversation_timeline_cut((string) $top['label'], 40);

    return $closed
        ? array('result' => lang(array('string' => 'Closed: {var:1}', 'vars' => $label)) . ' · ' . $count, 'state' => 'ok')
        : array('result' => $count . ' · ' . lang(array('string' => 'leading: {var:1}', 'vars' => $label)), 'state' => 'open');
}

/**
 * What came of an approval request. Pure but for lang().
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

    $all = array_sum(array_map('intval', (array) $counts));

    return array(
        'result' => lang(array('string' => 'Waiting: {var:1} of {var:2} approved', 'vars' => array((int) ($counts['approved'] ?? 0), $all))),
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
        'ctl_label'        => lang('Conversation timeline'),
        'ctl_cut'          => ws_js_template('The newest {var:1} of {var:2} messages', 2),
        'ctl_empty'        => lang('No message yet.'),
        'ctl_failed'       => lang('The timeline could not be read.'),
        'ctl_open'         => lang('Click to open'),
        'ctl_after'        => ws_js_template('{var:1} after the one before', 1),
        'ctl_sec'          => ws_js_template('{var:1} s', 1),
        'ctl_min'          => ws_js_template('{var:1} min', 1),
        'ctl_hour'         => ws_js_template('{var:1} h {var:2} min', 2),
        'ctl_day'          => ws_js_template('{var:1} d {var:2} h', 2),
        'ctl_no_text'      => lang('(no text)'),
        'ctl_more_tools'   => ws_js_template('+{var:1} more', 1),
        'ctl_today'        => lang('Today'),
        'ctl_zoom_in'      => lang('Zoom in'),
        'ctl_zoom_out'     => lang('Zoom out'),
        'ctl_kind_agent'   => lang('AI agent'),
        'ctl_kind_app'     => lang('Application'),
        'ctl_kind_guest'   => lang('Guest'),
        'ctl_kind_mail'    => lang('E-mail'),
        'ctl_kind_system'  => lang('System'),
        'ctl_tool_decision' => lang('Decision'),
        'ctl_tool_note'    => lang('Note'),
        'ctl_tool_task'    => lang('Task'),
        'ctl_tool_file'    => lang('File'),
        'ctl_tool_picture' => lang('Picture'),
        'ctl_tool_voice'   => lang('Voice message'),
        'ctl_tool_poll'    => lang('Poll'),
        'ctl_tool_question' => lang('Agent question'),
        'ctl_tool_approval' => lang('Approval'),
        'ctl_tool_ack'     => lang('Read receipt'),
        'ctl_tool_status'  => lang('Status update'),
        'ctl_tool_checkin' => lang('Check-in'),
        'ctl_tool_agent'   => lang('Agent request'),
    );
}
