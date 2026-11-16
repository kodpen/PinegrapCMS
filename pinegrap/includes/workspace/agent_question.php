<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agent question. A poll an application agent or an
 * assistant asks is marked by the server (ws_polls.agent_question), never
 * by the request. It is asked of the people its question mentions, else of
 * the channel's owners: only they answer, the others read, a guest never.
 * It takes no anonymous vote and no closing time; the first answer settles
 * it and closes it without an outcome line. The answer wakes the asking
 * agent with one request whose brief carries the question and the answer,
 * and the channel's memory index reads it as a decision.
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
 * Does ws_polls.agent_question exist (step 11.0.214)?
 *
 * @return bool
 */
function ws_agent_question_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('ws_polls', 'agent_question');
}

/**
 * The flag a new poll is stored with: an application asking (an agent's key,
 * an assistant, an OAuth connection) makes it an agent question; a person
 * never does, whatever the request says. Pure.
 *
 * @param int $app_id the asking application, 0 for a person
 * @return int 1 | 0
 */
function ws_agent_question_flag($app_id)
{
    return ((int) $app_id > 0) ? 1 : 0;
}

/**
 * Is the poll an agent question? Pure.
 *
 * @param array $poll ws_polls row
 * @return bool
 */
function ws_agent_question_is($poll)
{
    return (int) ($poll['agent_question'] ?? 0) === 1;
}

/**
 * The settings an agent question is kept to: no anonymous vote and no
 * closing time, as it is settled by the first answer. A person's poll keeps
 * what it was given. Pure.
 *
 * @param array $data     multiple, anonymous, closes_at as sent
 * @param bool  $question it is an agent question
 * @return array the same keys
 */
function ws_agent_question_settings($data, $question)
{
    if ($question) {
        $data['anonymous'] = 0;
        $data['closes_at'] = 0;
    }

    return $data;
}

/**
 * Why this reader may not answer the agent question, or '' when they may: a
 * guest never answers, and only the people asked do. Pure.
 *
 * @param int   $viewer_id
 * @param bool  $guest     the reader is a guest of the room
 * @param int[] $asked     ws_agent_question_asked()
 * @return string
 */
function ws_agent_question_answer_refusal($viewer_id, $guest, $asked)
{
    if ($guest) {
        return lang('A guest cannot answer an agent question.');
    }

    if (((int) $viewer_id <= 0) || !in_array((int) $viewer_id, array_map('intval', (array) $asked), true)) {
        return lang('Only the people asked can answer this question.');
    }

    return '';
}

/**
 * Why a choice cannot settle the agent question, or '': the answer cannot be
 * taken back, so an empty one is refused. Pure.
 *
 * @param int[] $chosen
 * @return string
 */
function ws_agent_question_choice_refusal($chosen)
{
    return empty($chosen) ? lang('Choose an answer first.') : '';
}

/**
 * Why the poll cannot be closed by hand, or '': an open agent question
 * closes itself when it is answered, and its asker takes it back instead.
 * Pure.
 *
 * @param array $poll ws_polls row
 * @return string
 */
function ws_agent_question_close_refusal($poll)
{
    if (ws_agent_question_is($poll) && ((int) ($poll['closed_at'] ?? 0) === 0)) {
        return lang('An agent question closes itself when it is answered.');
    }

    return '';
}

/**
 * The answer of a settled agent question from its options and votes: the
 * labels chosen in the options' order, the words on the "Other" row, who
 * answered and when. Every vote is one person's, as the first answer closes
 * it. Pure.
 *
 * @param array[] $options ws_poll_options rows: id, label, other
 * @param array[] $votes   ws_poll_votes rows: option_id, user_id, other_text, created_at
 * @return array labels, other_text, by (user id, 0 none), at
 */
function ws_agent_question_answer($options, $votes)
{
    $chosen = array();
    $by = 0;
    $at = 0;
    $text = '';

    foreach ((array) $votes as $vote) {
        $chosen[(int) $vote['option_id']] = true;
        $by = $by ?: (int) $vote['user_id'];
        $at = max($at, (int) ($vote['created_at'] ?? 0));
        $text = (trim((string) ($vote['other_text'] ?? '')) !== '') ? trim((string) $vote['other_text']) : $text;
    }

    $labels = array();

    foreach ((array) $options as $option) {
        if (isset($chosen[(int) $option['id']]) && empty($option['other'])) {
            $labels[] = (string) $option['label'];
        }
    }

    return array('labels' => $labels, 'other_text' => $text, 'by' => $by, 'at' => $at);
}

/**
 * The answer in one line: the labels chosen, then the "Other" words. Pure.
 *
 * @param array $answer ws_agent_question_answer()
 * @return string
 */
function ws_agent_question_answer_text($answer)
{
    $parts = array_values((array) ($answer['labels'] ?? array()));

    if ((string) ($answer['other_text'] ?? '') !== '') {
        $parts[] = '"' . (string) $answer['other_text'] . '"';
    }

    return implode(', ', $parts);
}

/**
 * The decision line an answered agent question gives the memory index:
 * question — answer (who). Pure.
 *
 * @param string $question as plain text
 * @param string $answer   ws_agent_question_answer_text()
 * @param string $who      the name of who answered
 * @return string
 */
function ws_agent_question_decision_line($question, $answer, $who)
{
    $line = trim((string) $question) . ' — ' . trim((string) $answer);

    return (trim((string) $who) !== '') ? $line . ' (' . trim((string) $who) . ')' : $line;
}

/**
 * The decision lines and the answered agent questions in one list, newest
 * first, cut to the limit. Pure.
 *
 * @param array[] $decisions body, marked_at
 * @param array[] $answered  body, marked_at (the answer's time)
 * @param int     $limit
 * @return array[] body, marked_at
 */
function ws_agent_question_decisions_merge($decisions, $answered, $limit)
{
    $all = array_merge(array_values((array) $decisions), array_values((array) $answered));

    usort($all, function ($a, $b) {
        return (int) $b['marked_at'] - (int) $a['marked_at'];
    });

    return array_slice($all, 0, max(0, (int) $limit));
}

/**
 * The ai_state the request waking the asking agent carries. Pure.
 *
 * @param array $question poll_id, message_id, question, answer (text), by (name)
 * @return string JSON
 */
function ws_agent_question_request_state($question)
{
    return (string) json_encode(array('agent_question' => array(
        'poll_id'    => (int) ($question['poll_id'] ?? 0),
        'message_id' => (int) ($question['message_id'] ?? 0),
        'question'   => (string) ($question['question'] ?? ''),
        'answer'     => (string) ($question['answer'] ?? ''),
        'by'         => (string) ($question['by'] ?? ''),
    )), JSON_UNESCAPED_UNICODE);
}

/**
 * The agent question a request carries the answer of, or null. Pure.
 *
 * @param array $row ws_ai_requests: ai_state
 * @return array|null
 */
function ws_agent_question_request_of($row)
{
    $state = json_decode((string) ($row['ai_state'] ?? ''), true);

    return (is_array($state) && isset($state['agent_question']) && is_array($state['agent_question'])) ? $state['agent_question'] : null;
}

/**
 * What the asking agent is told of the answer, in English as the rest of
 * the brief. The question and the answer are people's words, so they go in
 * data blocks. Pure.
 *
 * @param array $question ws_agent_question_request_of()
 * @return string[]
 */
function ws_agent_question_brief_lines($question)
{
    $lines = array();

    $lines[] = 'The question you asked with a poll (poll ' . (int) ($question['poll_id'] ?? 0) . ', message ' . (int) ($question['message_id'] ?? 0) . ') was answered by ' . (string) ($question['by'] ?? '') . '.';
    $lines[] = 'The question:';
    $lines[] = pg_ai_data_block('question', (string) ($question['question'] ?? ''));
    $lines[] = 'The answer:';
    $lines[] = pg_ai_data_block('answer', (string) ($question['answer'] ?? ''));
    $lines[] = 'The question is settled and closed; carry on with that answer. Your answer goes under the poll in the channel.';

    return $lines;
}

/**
 * The people an agent question is asked of: those its question mentions,
 * else the owners of the channel (ws_question_asked_ids()).
 *
 * @param array $poll ws_polls row
 * @return int[]
 */
function ws_agent_question_asked($poll)
{
    $channel = ws_channel($poll['channel_id']);
    $body = (string) db_value("SELECT body FROM ws_messages WHERE id = '" . (int) $poll['message_id'] . "'");

    return $channel ? ws_question_asked_ids($channel, ws_tokens($body)) : array();
}

/**
 * May this reader answer the agent question now? '' when they may.
 *
 * @param array $viewer
 * @param array $poll
 * @return string
 */
function ws_agent_question_answer_check($viewer, $poll)
{
    $guest = (int) ($viewer['guest_id'] ?? 0) > 0;

    return ws_agent_question_answer_refusal((int) ($viewer['id'] ?? 0), $guest, $guest ? array() : ws_agent_question_asked($poll));
}

/**
 * Settles the agent question: closed by its first answer, before the votes
 * are written, so two answers arriving together do not both settle it.
 *
 * @param array $viewer
 * @param array $poll
 * @return bool this answer settled it
 */
function ws_agent_question_settle($viewer, $poll)
{
    db("UPDATE ws_polls SET closed_at = '" . time() . "', closed_by = '" . (int) $viewer['id'] . "'
        WHERE id = '" . (int) $poll['id'] . "' AND closed_at = 0" . (ws_poll_again_ready() ? ' AND withdrawn_at = 0' : ''));

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * The answer of a settled agent question, read from its rows.
 *
 * @param int $poll_id
 * @return array ws_agent_question_answer()
 */
function ws_agent_question_answer_of($poll_id)
{
    $parts = ws_poll_parts_ready();
    $options = (array) db_items("SELECT id, label" . ($parts ? ', other' : ', 0 AS other') . " FROM ws_poll_options WHERE poll_id = '" . (int) $poll_id . "' ORDER BY sort, id");
    $votes = (array) db_items("SELECT option_id, user_id" . ($parts ? ', other_text' : ", '' AS other_text") . ", created_at FROM ws_poll_votes WHERE poll_id = '" . (int) $poll_id . "' ORDER BY created_at");

    return ws_agent_question_answer($options, $votes);
}

/**
 * Does an assistant's per-person limit (WS_CLAUDE_PER_PERSON,
 * WS_AI_PER_PERSON) count this wake? Not for an answer to the assistant's own
 * question: that is not a new request of the person, and refusing it would
 * leave a closed question the assistant never hears about. Pure.
 *
 * @param bool $answer
 * @return bool
 */
function ws_assistant_per_person_limit_applies($answer)
{
    return !$answer;
}

/**
 * Wakes the asking agent with the answer: one request, as a mention would
 * make it, hung on the poll's message, its brief carrying the question and
 * the answer. A registry agent through its reaching row and push door;
 * Claude and Pinegrap AI through their own queues. An application with no
 * agent (an OAuth connection) reads the answer itself.
 *
 * @param array $viewer who answered
 * @param array $poll
 * @return string the request's agent value, '' when nobody was woken
 */
function ws_agent_question_wake($viewer, $poll)
{
    $app_id = ws_message_app_id($poll['message_id']);
    $channel = ws_channel($poll['channel_id']);

    if (($app_id <= 0) || !$channel || !function_exists('ws_ai_schema_ready') || !ws_ai_schema_ready()) {
        return '';
    }

    $body = ws_tokens_normalise((string) db_value("SELECT body FROM ws_messages WHERE id = '" . (int) $poll['message_id'] . "'"));
    $state = ws_agent_question_request_state(array(
        'poll_id'    => (int) $poll['id'],
        'message_id' => (int) $poll['message_id'],
        'question'   => ws_plain_text($viewer, $body),
        'answer'     => ws_agent_question_answer_text(ws_agent_question_answer_of((int) $poll['id'])),
        'by'         => ws_person_name($viewer['id']),
    ));

    // Claude and Pinegrap AI have queues of their own; the request is given
    // the answer once made (as approval_agents.php does).
    $own = array(
        'claude' => function_exists('ws_claude_config') ? (int) (ws_claude_config()['app_id'] ?? 0) : 0,
        'ai'     => function_exists('ws_approval_agent_ai_row') ? (int) (ws_approval_agent_ai_row()['app_id'] ?? 0) : 0,
    );

    foreach ($own as $value => $own_app) {
        if (($own_app <= 0) || ($own_app !== $app_id)) {
            continue;
        }

        // An answer is not a new request of the person who gives it, so
        // their per-person limit is not counted; the question is closed
        // already, so a wake that still cannot be made is said in the channel.
        $queued = ($value === 'ai')
            ? ws_ai_after_send($viewer, $channel, (int) $poll['message_id'], $body, true, true)
            : ws_claude_after_send($viewer, $channel, (int) $poll['message_id'], $body, true, true);

        if (!$queued) {
            ws_message_system((int) $channel['id'], lang(array(
                'string' => '{var:1} was not told the answer to its question: it is not set up or not allowed in this channel.',
                'vars'   => ($value === 'ai') ? 'Pinegrap AI' : 'Claude',
            )));

            return '';
        }

        db("UPDATE ws_ai_requests SET ai_state = '" . e($state) . "'
            WHERE message_id = '" . (int) $poll['message_id'] . "' AND status = 'queued'" . (($value === 'ai') ? " AND agent = 'ai'" : ws_claude_only()) . " ORDER BY id DESC LIMIT 1");

        return $value;
    }

    if (!function_exists('ws_agent_reach_rows')) {
        return '';
    }

    foreach (ws_agent_reach_rows($channel) as $row) {
        $value = ws_agent_request_value($row);

        if (($value === '') || ((int) $row['app_id'] !== $app_id)) {
            continue;
        }

        $made = ws_agent_after_send($viewer, $channel, (int) $poll['message_id'], $body, $value, false, array('state' => $state, 'answer' => true));

        return in_array($value, $made, true) ? $value : '';
    }

    return '';
}

/**
 * The answered agent questions of a channel as decision lines for the
 * memory index, newest first.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $limit
 * @return array lines (body, marked_at), total
 */
function ws_agent_question_decisions($viewer, $channel, $limit)
{
    if (!ws_interact_ready() || !ws_agent_question_ready()) {
        return array('lines' => array(), 'total' => 0);
    }

    $where = "p.channel_id = '" . (int) $channel['id'] . "' AND p.agent_question = 1 AND p.closed_at > 0"
        . (ws_poll_again_ready() ? ' AND p.withdrawn_at = 0' : '') . " AND m.deleted_at = 0";
    $lines = array();

    foreach ((array) db_items("SELECT p.id, p.closed_at, p.closed_by, m.body FROM ws_polls p
        INNER JOIN ws_messages m ON m.id = p.message_id
        WHERE " . $where . "
        ORDER BY p.closed_at DESC, p.id DESC
        LIMIT " . max(1, (int) $limit)) as $row) {
        $answer = ws_agent_question_answer_of((int) $row['id']);
        $who = ws_person_name($answer['by'] ?: (int) $row['closed_by']);
        $lines[] = array(
            'body'      => ws_agent_question_decision_line(ws_plain_text($viewer, (string) $row['body']), ws_agent_question_answer_text($answer), $who),
            'marked_at' => (int) $row['closed_at'],
        );
    }

    $total = (count($lines) < (int) $limit) ? count($lines) : (int) db_value("SELECT COUNT(*) FROM ws_polls p INNER JOIN ws_messages m ON m.id = p.message_id WHERE " . $where);

    return array('lines' => $lines, 'total' => $total);
}

/**
 * The messages of a channel's answered agent questions (not taken back),
 * newest first, for the Decisions tab. Each row's marked_at is the moment
 * it was answered, so it sorts among the decisions
 * (ws_agent_question_decisions_merge()).
 *
 * @param int $channel_id
 * @param int $limit
 * @return array[] ws_messages rows
 */
function ws_agent_question_decision_messages($channel_id, $limit)
{
    if (!ws_interact_ready() || !ws_agent_question_ready()) {
        return array();
    }

    $rows = array();

    foreach ((array) db_items("SELECT m.*, p.closed_at AS answered_at FROM ws_polls p
        INNER JOIN ws_messages m ON m.id = p.message_id
        WHERE p.channel_id = '" . (int) $channel_id . "' AND p.agent_question = 1 AND p.closed_at > 0"
        . (ws_poll_again_ready() ? ' AND p.withdrawn_at = 0' : '') . " AND m.deleted_at = 0
        ORDER BY p.closed_at DESC, p.id DESC
        LIMIT " . max(1, (int) $limit)) as $row) {
        $row['marked_at'] = (int) $row['answered_at'];
        unset($row['answered_at']);
        $rows[] = $row;
    }

    return $rows;
}

/**
 * The fields an agent question adds to its ws_polls_map() entry: who is
 * asked (names), whether this reader may answer, who answered and when, and
 * the answer as a line. A person's poll gets agent_question false and empty
 * fields.
 *
 * @param array $poll   ws_polls row
 * @param array $viewer
 * @return array agent_question, asked, can_answer, answered_by, answered_at, answer
 */
function ws_agent_question_map_fields($poll, $viewer)
{
    $out = array('agent_question' => false, 'asked' => array(), 'can_answer' => false, 'answered_by' => null, 'answered_at' => 0, 'answer' => '');

    if (!ws_agent_question_is($poll)) {
        return $out;
    }

    $asked = ws_agent_question_asked($poll);
    $people = ws_people($asked);
    $out['agent_question'] = true;

    foreach ($asked as $user_id) {
        $out['asked'][] = array('id' => (int) $user_id, 'name' => $people[$user_id]['name'] ?? ws_person_name($user_id));
    }

    $guest = (int) ($viewer['guest_id'] ?? 0) > 0;
    $out['can_answer'] = ((int) $poll['closed_at'] === 0) && !ws_poll_withdrawn($poll)
        && (ws_agent_question_answer_refusal((int) $viewer['id'], $guest, $asked) === '');

    if ((int) $poll['closed_at'] > 0) {
        $answer = ws_agent_question_answer_of((int) $poll['id']);
        $by = $answer['by'] ?: (int) $poll['closed_by'];
        $out['answered_by'] = ($by > 0) ? array('id' => $by, 'name' => ws_person_name($by)) : null;
        $out['answered_at'] = (int) $poll['closed_at'];
        $out['answer'] = ws_agent_question_answer_text($answer);
    }

    return $out;
}

/**
 * Is this reader one of the people the agent question asks, and is it still
 * open (not answered, not taken back)? Pure.
 *
 * @param array $poll      ws_polls row
 * @param int[] $asked     ws_question_asked_ids() of it
 * @param int   $viewer_id
 * @return bool
 */
function ws_agent_question_waits_for($poll, $asked, $viewer_id)
{
    return ws_agent_question_is($poll) && ((int) ($poll['closed_at'] ?? 0) === 0) && !ws_poll_withdrawn($poll)
        && ((int) $viewer_id > 0) && in_array((int) $viewer_id, array_map('intval', (array) $asked), true);
}

/**
 * The rows a reader keeps from the open questions, read page by page, newest
 * first: each page is read below the last id of the one before, until
 * $limit rows are kept or a page comes back short. A person's question past
 * the site's newest page is still found. Pure: $fetch($before_id, $page)
 * gives a page of rows (id descending; $before_id 0 for the first),
 * $keep($row) says whether the reader keeps it.
 *
 * @param callable $fetch
 * @param callable $keep
 * @param int      $limit
 * @param int      $page
 * @return array[] the kept rows, at most $limit
 */
function ws_agent_questions_collect($fetch, $keep, $limit, $page = 100)
{
    $limit = max(1, (int) $limit);
    $page = max(1, (int) $page);
    $kept = array();
    $before_id = 0;

    do {
        $rows = (array) $fetch($before_id, $page);

        foreach ($rows as $row) {
            $before_id = (int) $row['id'];

            if (!$keep($row)) {
                continue;
            }

            $kept[] = $row;

            if (count($kept) >= $limit) {
                return $kept;
            }
        }
    } while (count($rows) >= $page);

    return $kept;
}

/**
 * The agent questions waiting for this person's answer, newest first, from
 * the channels they read: for Today's "Waiting for you" card and the
 * dashboard's Assistant card. Each: poll id, message id, the question cut
 * to 160 characters, the asking agent as a sender, the channel, the time
 * and the address of the message.
 *
 * @param array $viewer
 * @param int   $limit
 * @return array[] id, message_id, question, asker, channel (id, name, private), time, created_at, url
 */
function ws_agent_questions_for($viewer, $limit = 5)
{
    $me = (int) ($viewer['id'] ?? 0);

    if (($me <= 0) || ((int) ($viewer['guest_id'] ?? 0) > 0) || !ws_interact_ready() || !ws_agent_question_ready()) {
        return array();
    }

    // The open questions of the whole site are read a page at a time below
    // the last id, until enough wait for this reader: a page of the site's
    // newest alone could hold none of theirs.
    $withdrawn = ws_poll_again_ready() ? ' AND p.withdrawn_at = 0' : '';
    $fetch = function ($before_id, $page) use ($withdrawn) {
        return (array) db_items("SELECT p.*, m.body, m.sender_kind, m.sender_id, m.created_at AS asked_at FROM ws_polls p
            INNER JOIN ws_messages m ON m.id = p.message_id
            WHERE p.agent_question = 1 AND p.closed_at = 0" . $withdrawn . " AND m.deleted_at = 0"
            . (((int) $before_id > 0) ? " AND p.id < '" . (int) $before_id . "'" : '') . "
            ORDER BY p.id DESC
            LIMIT " . (int) $page);
    };
    $channels = array();
    $keep = function ($row) use ($viewer, $me, &$channels) {
        $channel_id = (int) $row['channel_id'];

        if (!array_key_exists($channel_id, $channels)) {
            $channel = ws_channel($channel_id);
            $channels[$channel_id] = ($channel && ws_can_read_channel($viewer, $channel)) ? $channel : null;
        }

        return ($channels[$channel_id] !== null) && ws_agent_question_waits_for($row, ws_question_asked_ids($channels[$channel_id], ws_tokens($row['body'])), $me);
    };
    $kept = ws_agent_questions_collect($fetch, $keep, $limit, 100);

    if (empty($kept)) {
        return array();
    }

    // An agent question is an application's message; a person's poll made
    // before the step never is one.
    $people = ws_people(array_filter(array_map(function ($row) {
        return ((string) $row['sender_kind'] === 'user') ? (int) $row['sender_id'] : 0;
    }, $kept)));
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php';
    $out = array();

    foreach ($kept as $row) {
        $channel = $channels[(int) $row['channel_id']];
        $text = ws_plain_text($viewer, (string) $row['body']);

        if (mb_strlen($text) > 160) {
            $text = rtrim(mb_substr($text, 0, 159)) . '…';
        }

        $out[] = array(
            'id'         => (int) $row['id'],
            'message_id' => (int) $row['message_id'],
            'question'   => $text,
            'asker'      => ws_message_row_sender($row, $people),
            'channel'    => array('id' => (int) $channel['id'], 'name' => (string) $channel['name'], 'private' => ((string) $channel['kind'] === 'private')),
            'time'       => ws_time_label($row['asked_at']),
            'created_at' => (int) $row['asked_at'],
            'url'        => $base . '?channel=' . (int) $channel['id'] . '&message=' . (int) $row['message_id'],
        );
    }

    return $out;
}
