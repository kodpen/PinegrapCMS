<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what people do to a message after it is written: leave an
 * emoji on it, tick the items of a checklist in it, vote in the poll it asks.
 *
 * None of it sends a notification. A reaction is a nod, not a message; a
 * poll's result is written into the channel as a decision when it closes,
 * and that is the part people are told about by reading the channel.
 *
 * Every change stamps the message's touched_at, which is what an open
 * conversation asks for to draw the messages that changed.
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
 * Are the tables there? The 2026.4.4 step 4.85 adds them; until it has run
 * the conversation works as before, without these three.
 *
 * @return bool
 */
function ws_interact_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_reactions', 'ws_checks', 'ws_polls', 'ws_poll_options', 'ws_poll_votes')") === 5)
            && ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_messages' AND COLUMN_NAME = 'touched_at'") > 0);
    }

    return $ready;
}

/**
 * Something about a message changed: an open conversation draws it again.
 *
 * @param int $message_id
 */
function ws_message_touch($message_id)
{
    if (ws_interact_ready()) {
        db("UPDATE ws_messages SET touched_at = '" . time() . "' WHERE id = '" . (int) $message_id . "'");
    }
}

/* ---------------------------------------------------------------------------
   Reactions
   --------------------------------------------------------------------------- */

/**
 * The emoji one click away, above every message.
 *
 * @return string[]
 */
function ws_reaction_quick()
{
    return array('👍', '❤️', '😂', '🎉', '👀', '✅', '🙏', '🔥');
}

/**
 * Is this an emoji and nothing else? Letters, spaces and markup are refused;
 * the picker only ever sends one emoji, so a longer string is not one.
 *
 * @param string $emoji
 * @return bool
 */
function ws_reaction_valid($emoji)
{
    $emoji = (string) $emoji;

    if (($emoji === '') || (strlen($emoji) > 32) || (mb_strlen($emoji) > 10)) {
        return false;
    }

    return (bool) preg_match('/^[^\x00-\x22\x24-\x29\x2B-\x2F\x3A-\x7F\s]+$/u', $emoji)
        && (bool) preg_match('/[\x{20E3}\x{2190}-\x{2BFF}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F000}-\x{1FAFF}]/u', $emoji);
}

/**
 * Leaves an emoji on a message, or takes it back.
 *
 * @param array    $viewer
 * @param array    $message
 * @param string   $emoji
 * @param int      $app_id an application reacting for its owner (0 = the person)
 * @param bool|null $on    true to leave, false to take back, null to toggle
 * @return array ok, error
 */
function ws_reaction_set($viewer, $message, $emoji, $app_id = 0, $on = null)
{
    if (!ws_interact_ready()) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    $emoji = trim((string) $emoji);

    if (!ws_reaction_valid($emoji)) {
        return array('ok' => false, 'error' => lang('That is not an emoji.'));
    }

    if (((int) $message['deleted_at'] > 0) || ($message['kind'] === 'system')) {
        return array('ok' => false, 'error' => lang('That message could not be found.'));
    }

    $channel = ws_channel($message['channel_id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $kind = ($app_id > 0) ? 'app' : 'user';
    $sender = ($app_id > 0) ? (int) $app_id : (int) $viewer['id'];
    $where = "message_id = '" . (int) $message['id'] . "' AND sender_kind = '" . $kind . "' AND sender_id = '" . $sender . "' AND emoji = '" . e($emoji) . "'";
    $has = ((int) db_value("SELECT COUNT(*) FROM ws_reactions WHERE " . $where) > 0);

    if ($on === null) {
        $on = !$has;
    }

    if ($on && !$has) {
        // A message is not a place for a hundred different emoji.
        if ((int) db_value("SELECT COUNT(DISTINCT emoji) FROM ws_reactions WHERE message_id = '" . (int) $message['id'] . "'") >= 20) {
            return array('ok' => false, 'error' => lang('This message already carries as many different reactions as it can.'));
        }

        db("INSERT IGNORE INTO ws_reactions (message_id, sender_kind, sender_id, emoji, created_at)
            VALUES ('" . (int) $message['id'] . "', '" . $kind . "', '" . $sender . "', '" . e($emoji) . "', '" . time() . "')");
    } elseif (!$on && $has) {
        db("DELETE FROM ws_reactions WHERE " . $where);
    }

    ws_message_touch($message['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * The reactions on a set of messages, grouped by emoji in the order they were
 * first left.
 *
 * @param int[] $message_ids
 * @param array $viewer
 * @return array message id => [ [emoji, count, mine, who[]] ]
 */
function ws_reactions_map($message_ids, $viewer)
{
    $message_ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (empty($message_ids) || !ws_interact_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT message_id, sender_kind, sender_id, emoji FROM ws_reactions
        WHERE message_id IN (" . implode(',', $message_ids) . ")
        ORDER BY id");

    $user_ids = array();

    foreach ($rows as $row) {
        if ($row['sender_kind'] === 'user') {
            $user_ids[] = (int) $row['sender_id'];
        }
    }

    $people = ws_people($user_ids);
    $out = array();

    foreach ($rows as $row) {
        $message_id = (int) $row['message_id'];
        $emoji = (string) $row['emoji'];

        if (!isset($out[$message_id][$emoji])) {
            $out[$message_id][$emoji] = array('emoji' => $emoji, 'count' => 0, 'mine' => false, 'who' => array());
        }

        $out[$message_id][$emoji]['count']++;

        if ($row['sender_kind'] === 'user') {
            $out[$message_id][$emoji]['who'][] = $people[(int) $row['sender_id']]['name'] ?? ws_person_name($row['sender_id']);

            if ((int) $row['sender_id'] === (int) $viewer['id']) {
                $out[$message_id][$emoji]['mine'] = true;
            }
        } else {
            $out[$message_id][$emoji]['who'][] = ws_app_sender((int) $row['sender_id'])['name'];
        }
    }

    foreach ($out as $message_id => $groups) {
        $out[$message_id] = array_values($groups);
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   Checklists
   --------------------------------------------------------------------------- */

/**
 * The checklist lines of a text: "- [ ] item" and "- [x] item", in order.
 *
 * @param string $body
 * @return array[] index, checked (as written), text
 */
function ws_checklist_items($body)
{
    $items = array();
    $code = false;

    foreach (preg_split('/\r\n|\r|\n/', (string) $body) as $line) {
        // Lines inside a block of code are code, whatever they look like.
        if ($code) {
            $code = !ws_code_fence_close($line);
            continue;
        }

        if (ws_code_fence_open($line) !== null) {
            $code = true;
            continue;
        }

        if (preg_match('/^\s*[-*]\s\[( |x|X)\]\s+(.+)$/u', $line, $match)) {
            $items[] = array('index' => count($items), 'checked' => ($match[1] !== ' '), 'text' => trim($match[2]));
        }
    }

    return $items;
}

/**
 * Who ticked what since the message was written.
 *
 * @param int[] $message_ids
 * @return array message id => item => [checked, user_id, name, at]
 */
function ws_checks_map($message_ids)
{
    $message_ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (empty($message_ids) || !ws_interact_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_checks WHERE message_id IN (" . implode(',', $message_ids) . ")");
    $user_ids = array();

    foreach ($rows as $row) {
        $user_ids[] = (int) $row['user_id'];
    }

    $people = ws_people($user_ids);
    $out = array();

    foreach ($rows as $row) {
        $out[(int) $row['message_id']][(int) $row['item']] = array(
            'checked' => ((int) $row['checked'] === 1),
            'user_id' => (int) $row['user_id'],
            'name'    => $people[(int) $row['user_id']]['name'] ?? ws_person_name($row['user_id']),
            'at'      => ws_time_label($row['updated_at']),
        );
    }

    return $out;
}

/**
 * Ticks one item of a message's checklist, or clears it. Anyone who may write
 * in the channel may.
 *
 * @param array $viewer
 * @param array $message
 * @param int   $item
 * @param bool  $checked
 * @return array ok, error
 */
function ws_check_set($viewer, $message, $item, $checked)
{
    if (!ws_interact_ready() || ((int) $message['deleted_at'] > 0)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if (!ws_can_post_channel($viewer, ws_channel($message['channel_id']))) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $items = ws_checklist_items($message['body']);
    $item = (int) $item;

    if (!isset($items[$item])) {
        return array('ok' => false, 'error' => lang('That item is no longer in the list.'));
    }

    db("INSERT INTO ws_checks (message_id, item, checked, user_id, updated_at)
        VALUES ('" . (int) $message['id'] . "', '" . $item . "', '" . ($checked ? 1 : 0) . "', '" . (int) $viewer['id'] . "', '" . time() . "')
        ON DUPLICATE KEY UPDATE checked = VALUES(checked), user_id = VALUES(user_id), updated_at = VALUES(updated_at)");

    ws_message_touch($message['id']);
    ws_checklist_tasks_refresh($message['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * After an edit, the ticks follow their items: an item that is still in the
 * list, word for word, keeps its tick wherever it moved; the rest go.
 *
 * @param int    $message_id
 * @param string $old_body
 * @param string $new_body
 */
function ws_checks_remap($message_id, $old_body, $new_body)
{
    if (!ws_interact_ready()) {
        return;
    }

    ws_checks_remap_in('ws_checks', 'message_id', $message_id, $old_body, $new_body);
}

/**
 * The same for any list of ticks kept by item number: a message's
 * (ws_checks) or a task's own (ws_task_checks).
 *
 * @param string $table  ws_checks | ws_task_checks
 * @param string $column message_id | task_id
 * @param int    $owner_id
 * @param string $old_body
 * @param string $new_body
 */
function ws_checks_remap_in($table, $column, $owner_id, $old_body, $new_body)
{
    if (!in_array($table . '.' . $column, array('ws_checks.message_id', 'ws_task_checks.task_id'), true)) {
        return;
    }

    $owner_id = (int) $owner_id;
    $rows = (array) db_items("SELECT * FROM " . $table . " WHERE " . $column . " = '" . $owner_id . "'");

    if (empty($rows)) {
        return;
    }

    $old = ws_checklist_items($old_body);
    $new = ws_checklist_items($new_body);
    $places = array();

    foreach ($new as $entry) {
        $places[$entry['text']][] = $entry['index'];
    }

    db("DELETE FROM " . $table . " WHERE " . $column . " = '" . $owner_id . "'");

    foreach ($rows as $row) {
        $text = $old[(int) $row['item']]['text'] ?? null;

        if (($text === null) || empty($places[$text])) {
            continue;
        }

        $item = array_shift($places[$text]);

        db("INSERT IGNORE INTO " . $table . " (" . $column . ", item, checked, user_id, updated_at)
            VALUES ('" . $owner_id . "', '" . (int) $item . "', '" . (int) $row['checked'] . "', '" . (int) $row['user_id'] . "', '" . (int) $row['updated_at'] . "')");
    }
}

/* ---------------------------------------------------------------------------
   Polls
   --------------------------------------------------------------------------- */

/**
 * @param int $poll_id
 * @return array|null
 */
function ws_poll($poll_id)
{
    if (!ws_interact_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_polls WHERE id = '" . (int) $poll_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * Asks the channel a question with options to choose from. The question is
 * the message; the options, the settings and the votes are the poll's.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $data question, options (texts), multiple, anonymous, closes_at (timestamp, 0 = open until closed)
 * @param int   $app_id
 * @return array ok, error, field, message_id, poll_id
 */
function ws_poll_create($viewer, $channel, $data, $app_id = 0)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'message_id' => 0, 'poll_id' => 0);
    };

    if (!ws_interact_ready()) {
        return $fail(lang('Invalid request.'));
    }

    $question = trim((string) ($data['question'] ?? ''));

    if ($question === '') {
        return $fail(lang('A poll needs a question.'), 'question');
    }

    $options = array();

    foreach ((array) ($data['options'] ?? array()) as $option) {
        $option = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $option)), 0, 255);

        if (($option !== '') && !in_array($option, $options, true)) {
            $options[] = $option;
        }
    }

    if (count($options) < 2) {
        return $fail(lang('A poll needs at least two different options.'), 'options');
    }

    if (count($options) > 10) {
        return $fail(lang('A poll can have at most ten options.'), 'options');
    }

    $closes_at = (int) ($data['closes_at'] ?? 0);

    if (($closes_at > 0) && ($closes_at <= time() + 60)) {
        return $fail(lang('The poll has to close later than now.'), 'closes_at');
    }

    $sent = ws_message_send($viewer, $channel, $question, array('app_id' => $app_id));

    if (!$sent['ok']) {
        return $fail($sent['error'], 'question');
    }

    db("INSERT INTO ws_polls (message_id, channel_id, multiple, anonymous, closes_at, created_by, created_at)
        VALUES ('" . (int) $sent['message_id'] . "', '" . (int) $channel['id'] . "', '" . (empty($data['multiple']) ? 0 : 1) . "',
            '" . (empty($data['anonymous']) ? 0 : 1) . "', '" . max(0, $closes_at) . "', '" . (int) $viewer['id'] . "', '" . time() . "')");

    $poll_id = (int) mysqli_insert_id(db::$con);

    foreach ($options as $sort => $label) {
        db("INSERT INTO ws_poll_options (poll_id, sort, label) VALUES ('" . $poll_id . "', '" . (int) $sort . "', '" . e($label) . "')");
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'message_id' => (int) $sent['message_id'], 'poll_id' => $poll_id);
}

/**
 * Changes an open poll: its question, its options, how it is answered and
 * when it closes. Only the person who asked. Once somebody has voted, the poll
 * stays what they voted on: an option with votes keeps its text and stays,
 * and whether it takes several answers or shows who voted no longer changes
 * (a secret vote must not turn public, and a second answer must not be taken
 * away). New options can still be added and the question worded better.
 *
 * @param array $viewer
 * @param array $poll
 * @param array $data question, options (list of {id, label}; id 0 for a new one), multiple, anonymous, closes_at (timestamp, 0 = open until closed)
 * @return array ok, error, field
 */
function ws_poll_edit($viewer, $poll, $data)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field);
    };

    if (!ws_interact_ready()) {
        return $fail(lang('Invalid request.'));
    }

    $message = ws_message($poll['message_id']);

    if (!is_array($message) || ((int) $message['deleted_at'] > 0)) {
        return $fail(lang('That poll could not be found.'));
    }

    if (((int) $poll['created_by'] !== (int) $viewer['id']) || ($message['sender_kind'] !== 'user') || ((int) $message['sender_id'] !== (int) $viewer['id'])) {
        return $fail(lang('Only the person who asked can change the poll.'));
    }

    if (((int) $poll['closed_at'] > 0) || (((int) $poll['closes_at'] > 0) && ((int) $poll['closes_at'] <= time()))) {
        return $fail(lang('The poll is closed.'));
    }

    if (!ws_can_post_channel($viewer, ws_channel($poll['channel_id']))) {
        return $fail(lang('You cannot post in that channel.'));
    }

    $question = ws_tokens_normalise(trim((string) ($data['question'] ?? '')));

    if ($question === '') {
        return $fail(lang('A poll needs a question.'), 'question');
    }

    if (mb_strlen($question) > WS_MESSAGE_MAX) {
        return $fail(lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => WS_MESSAGE_MAX)), 'question');
    }

    $existing = array();

    foreach ((array) db_items("SELECT id, label FROM ws_poll_options WHERE poll_id = '" . (int) $poll['id'] . "'") as $row) {
        $existing[(int) $row['id']] = (string) $row['label'];
    }

    $votes = array();

    foreach ((array) db_items("SELECT option_id, COUNT(*) AS votes FROM ws_poll_votes WHERE poll_id = '" . (int) $poll['id'] . "' GROUP BY option_id") as $row) {
        $votes[(int) $row['option_id']] = (int) $row['votes'];
    }

    $voted = (array_sum($votes) > 0);
    $options = array();
    $labels = array();

    foreach ((array) ($data['options'] ?? array()) as $option) {
        $option_id = is_array($option) ? (int) ($option['id'] ?? 0) : 0;
        $label = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) (is_array($option) ? ($option['label'] ?? '') : $option))), 0, 255);

        if (($option_id > 0) && !isset($existing[$option_id])) {
            return $fail(lang('Invalid request.'), 'options');
        }

        if (($label === '') || in_array($label, $labels, true)) {
            continue;
        }

        $labels[] = $label;
        $options[] = array('id' => $option_id, 'label' => $label);
    }

    if (count($options) < 2) {
        return $fail(lang('A poll needs at least two different options.'), 'options');
    }

    if (count($options) > 10) {
        return $fail(lang('A poll can have at most ten options.'), 'options');
    }

    // What people voted for stays, word for word.
    foreach ($votes as $option_id => $count) {
        $kept = false;

        foreach ($options as $option) {
            if (($option['id'] === $option_id) && ($option['label'] === ($existing[$option_id] ?? null))) {
                $kept = true;
            }
        }

        if (($count > 0) && !$kept) {
            return $fail(lang('An option somebody voted for cannot be changed or removed.'), 'options');
        }
    }

    $multiple = !empty($data['multiple']);
    $anonymous = !empty($data['anonymous']);

    if ($voted && (($multiple !== ((int) $poll['multiple'] === 1)) || ($anonymous !== ((int) $poll['anonymous'] === 1)))) {
        return $fail(lang('Somebody has voted, so whether the poll takes several answers and whether it is anonymous no longer change.'));
    }

    $closes_at = max(0, (int) ($data['closes_at'] ?? 0));

    if (($closes_at > 0) && ($closes_at !== (int) $poll['closes_at']) && ($closes_at <= time() + 60)) {
        return $fail(lang('The poll has to close later than now.'), 'closes_at');
    }

    // The question is the message: its edit keeps tags, mentions and
    // checklists right and tells whoever it newly mentions.
    if ($question !== trim((string) $message['body'])) {
        $edited = ws_message_edit($viewer, $message, $question);

        if (!$edited['ok']) {
            return $fail($edited['error'], 'question');
        }
    } else {
        db("UPDATE ws_messages SET edited_at = '" . time() . "' WHERE id = '" . (int) $message['id'] . "'");
        ws_message_touch($message['id']);
    }

    $keep = array();

    foreach ($options as $sort => $option) {
        if ($option['id'] > 0) {
            db("UPDATE ws_poll_options SET sort = '" . (int) $sort . "', label = '" . e($option['label']) . "'
                WHERE id = '" . (int) $option['id'] . "' AND poll_id = '" . (int) $poll['id'] . "'");
            $keep[] = (int) $option['id'];
        } else {
            db("INSERT INTO ws_poll_options (poll_id, sort, label) VALUES ('" . (int) $poll['id'] . "', '" . (int) $sort . "', '" . e($option['label']) . "')");
            $keep[] = (int) mysqli_insert_id(db::$con);
        }
    }

    // Left out: only options nobody voted for can be, as checked above.
    db("DELETE FROM ws_poll_options WHERE poll_id = '" . (int) $poll['id'] . "' AND id NOT IN (" . implode(',', $keep) . ")");

    db("UPDATE ws_polls SET multiple = '" . ($multiple ? 1 : 0) . "', anonymous = '" . ($anonymous ? 1 : 0) . "', closes_at = '" . $closes_at . "'
        WHERE id = '" . (int) $poll['id'] . "'");

    return array('ok' => true, 'error' => '', 'field' => '');
}

/**
 * The polls asked by a set of messages, as this reader sees them.
 *
 * @param int[] $message_ids
 * @param array $viewer
 * @return array message id => poll
 */
function ws_polls_map($message_ids, $viewer)
{
    $message_ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (empty($message_ids) || !ws_interact_ready()) {
        return array();
    }

    $polls = (array) db_items("SELECT * FROM ws_polls WHERE message_id IN (" . implode(',', $message_ids) . ")");

    if (empty($polls)) {
        return array();
    }

    $poll_ids = array();

    foreach ($polls as $poll) {
        $poll_ids[] = (int) $poll['id'];
    }

    $options = array();

    foreach ((array) db_items("SELECT * FROM ws_poll_options WHERE poll_id IN (" . implode(',', $poll_ids) . ") ORDER BY poll_id, sort, id") as $row) {
        $options[(int) $row['poll_id']][] = $row;
    }

    $votes = array();
    $user_ids = array();

    foreach ((array) db_items("SELECT poll_id, option_id, user_id FROM ws_poll_votes WHERE poll_id IN (" . implode(',', $poll_ids) . ") ORDER BY created_at") as $row) {
        $votes[(int) $row['poll_id']][] = $row;
        $user_ids[] = (int) $row['user_id'];
    }

    $people = ws_people($user_ids);
    $out = array();

    foreach ($polls as $poll) {
        $poll_id = (int) $poll['id'];
        $anonymous = ((int) $poll['anonymous'] === 1);
        $closed = ((int) $poll['closed_at'] > 0);
        $counts = array();
        $voters = array();
        $mine = array();
        $people_voted = array();

        foreach ($votes[$poll_id] ?? array() as $vote) {
            $option_id = (int) $vote['option_id'];
            $counts[$option_id] = ($counts[$option_id] ?? 0) + 1;
            $people_voted[(int) $vote['user_id']] = true;

            if (!$anonymous) {
                $voters[$option_id][] = array('id' => (int) $vote['user_id'], 'name' => $people[(int) $vote['user_id']]['name'] ?? ws_person_name($vote['user_id']),
                    'avatar' => $people[(int) $vote['user_id']]['avatar'] ?? '');
            }

            if ((int) $vote['user_id'] === (int) $viewer['id']) {
                $mine[] = $option_id;
            }
        }

        $top = empty($counts) ? 0 : max($counts);
        $list = array();

        foreach ($options[$poll_id] ?? array() as $option) {
            $option_id = (int) $option['id'];
            $count = $counts[$option_id] ?? 0;

            $list[] = array(
                'id'      => $option_id,
                'label'   => (string) $option['label'],
                'count'   => $count,
                'mine'    => in_array($option_id, $mine, true),
                'leading' => ($top > 0) && ($count === $top),
                'voters'  => $voters[$option_id] ?? array(),
            );
        }

        $channel = ws_channel($poll['channel_id']);

        $out[(int) $poll['message_id']] = array(
            'id'         => $poll_id,
            'multiple'   => ((int) $poll['multiple'] === 1),
            'anonymous'  => $anonymous,
            'closed'     => $closed,
            'closes_at'  => (int) $poll['closes_at'],
            'closes'     => ((int) $poll['closes_at'] > 0) ? date('d.m.Y H:i', (int) $poll['closes_at']) : '',
            'closed_on'  => $closed ? ws_time_label($poll['closed_at']) : '',
            'voters'     => count($people_voted),
            'options'    => $list,
            'voted'      => !empty($mine),
            'can_vote'   => !$closed && ws_can_post_channel($viewer, $channel),
            'can_close'  => !$closed && ws_can_close_poll($viewer, $poll, $channel),
            // The asker changes it while it is open; the form is filled from these.
            'can_edit'   => !$closed && ((int) $poll['created_by'] === (int) $viewer['id']) && ws_can_post_channel($viewer, $channel),
            'closes_date' => ((int) $poll['closes_at'] > 0) ? date('Y-m-d', (int) $poll['closes_at']) : '',
            'closes_time' => ((int) $poll['closes_at'] > 0) ? date('H:i', (int) $poll['closes_at']) : '',
            'result_id'  => (int) $poll['result_message_id'],
        );
    }

    return $out;
}

/**
 * The asker closes their own poll; so does whoever runs the channel.
 *
 * @param array $viewer
 * @param array $poll
 * @param array $channel
 * @return bool
 */
function ws_can_close_poll($viewer, $poll, $channel)
{
    if ((int) $poll['created_by'] === (int) $viewer['id']) {
        return true;
    }

    return ws_can_manage_channel($viewer, $channel);
}

/**
 * Casts this person's vote, replacing the one they cast before. An empty
 * choice takes the vote back.
 *
 * @param array $viewer
 * @param array $poll
 * @param int[] $option_ids
 * @return array ok, error
 */
function ws_poll_vote($viewer, $poll, $option_ids)
{
    if ((int) $poll['closed_at'] > 0) {
        return array('ok' => false, 'error' => lang('The poll is closed.'));
    }

    if (((int) $poll['closes_at'] > 0) && ((int) $poll['closes_at'] <= time())) {
        ws_poll_close(null, $poll);

        return array('ok' => false, 'error' => lang('The poll is closed.'));
    }

    if (!ws_can_post_channel($viewer, ws_channel($poll['channel_id']))) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $valid = array_map('intval', (array) db_values("SELECT id FROM ws_poll_options WHERE poll_id = '" . (int) $poll['id'] . "'"));
    $chosen = array_values(array_unique(array_intersect(array_map('intval', (array) $option_ids), $valid)));

    if ((count($chosen) > 1) && ((int) $poll['multiple'] !== 1)) {
        $chosen = array($chosen[0]);
    }

    db("DELETE FROM ws_poll_votes WHERE poll_id = '" . (int) $poll['id'] . "' AND user_id = '" . (int) $viewer['id'] . "'");

    foreach ($chosen as $option_id) {
        db("INSERT IGNORE INTO ws_poll_votes (poll_id, option_id, user_id, created_at)
            VALUES ('" . (int) $poll['id'] . "', '" . (int) $option_id . "', '" . (int) $viewer['id'] . "', '" . time() . "')");
    }

    ws_message_touch($poll['message_id']);

    return array('ok' => true, 'error' => '');
}

/**
 * Closes a poll and writes its result into the channel. A clear winner is
 * written as a decision, so it lands on the Decisions tab; a tie or a poll
 * nobody voted in is said as a line of the system's, and the decision stays
 * with the people.
 *
 * @param array|null $viewer whoever closed it; null when its time ran out
 * @param array      $poll
 * @return array ok, error, result_message_id
 */
function ws_poll_close($viewer, $poll)
{
    if ((int) $poll['closed_at'] > 0) {
        return array('ok' => true, 'error' => '', 'result_message_id' => (int) $poll['result_message_id']);
    }

    $channel = ws_channel($poll['channel_id']);

    if (($viewer !== null) && !ws_can_close_poll($viewer, $poll, $channel)) {
        return array('ok' => false, 'error' => lang('Only the person who asked, or whoever runs the channel, can close the poll.'), 'result_message_id' => 0);
    }

    $now = time();

    // Closed first, so two requests arriving together do not both write a result.
    db("UPDATE ws_polls SET closed_at = '" . $now . "', closed_by = '" . (($viewer !== null) ? (int) $viewer['id'] : 0) . "'
        WHERE id = '" . (int) $poll['id'] . "' AND closed_at = 0");

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => true, 'error' => '', 'result_message_id' => 0);
    }

    $counts = array();
    $labels = array();

    foreach ((array) db_items("SELECT id, label FROM ws_poll_options WHERE poll_id = '" . (int) $poll['id'] . "' ORDER BY sort, id") as $option) {
        $labels[(int) $option['id']] = (string) $option['label'];
        $counts[(int) $option['id']] = 0;
    }

    foreach ((array) db_items("SELECT option_id, COUNT(*) AS votes FROM ws_poll_votes WHERE poll_id = '" . (int) $poll['id'] . "' GROUP BY option_id") as $row) {
        $counts[(int) $row['option_id']] = (int) $row['votes'];
    }

    $voters = (int) db_value("SELECT COUNT(DISTINCT user_id) FROM ws_poll_votes WHERE poll_id = '" . (int) $poll['id'] . "'");
    $top = empty($counts) ? 0 : max($counts);
    $winners = array();

    foreach ($counts as $option_id => $count) {
        if (($top > 0) && ($count === $top)) {
            $winners[] = $labels[$option_id];
        }
    }

    $asker = ws_viewer((array) pg_load_user_row((int) $poll['created_by']));
    $question = ws_plain_text($asker, (string) db_value("SELECT body FROM ws_messages WHERE id = '" . (int) $poll['message_id'] . "'"));
    $result_id = 0;

    if (count($winners) === 1) {
        // Written in the asker's name: the decision is the channel's answer to
        // their question, and it is theirs to change if it turns out wrong.
        $body = lang(array('string' => 'Poll result: **{var:1}** ({var:2} of {var:3} voters) - {var:4}', 'vars' => array($winners[0], $top, $voters, $question)));

        if ($asker['member'] && ws_can_post_channel($asker, $channel)) {
            $sent = ws_message_send($asker, $channel, $body, array('kind' => 'decision', 'parent_id' => (int) $poll['message_id']));
            $result_id = $sent['ok'] ? (int) $sent['message_id'] : 0;
        }

        if ($result_id === 0) {
            $result_id = ws_message_system($channel['id'], $body);
        }
    } elseif (count($winners) > 1) {
        $result_id = ws_message_system($channel['id'], lang(array('string' => 'The poll ended in a tie between {var:1} ({var:2} votes each) - {var:3}', 'vars' => array(implode(', ', $winners), $top, $question))));
    } else {
        $result_id = ws_message_system($channel['id'], lang(array('string' => 'The poll closed without a vote - {var:1}', 'vars' => $question)));
    }

    db("UPDATE ws_polls SET result_message_id = '" . (int) $result_id . "' WHERE id = '" . (int) $poll['id'] . "'");
    ws_message_touch($poll['message_id']);

    // Announced for public channels only, the way their messages are.
    if ($channel && ((string) $channel['kind'] === 'public') && function_exists('pg_announce')) {
        $options = array();

        foreach ($labels as $option_id => $label) {
            $options[] = array('id' => (int) $option_id, 'label' => $label, 'votes' => (int) $counts[$option_id]);
        }

        pg_announce('workspace.poll.closed', array(
            'id'                => (int) $poll['id'],
            'message_id'        => (int) $poll['message_id'],
            'channel_id'        => (int) $channel['id'],
            'channel'           => (string) $channel['name'],
            'question'          => $question,
            'options'           => $options,
            'voters'            => $voters,
            'winner'            => (count($winners) === 1) ? $winners[0] : null,
            'tie'               => (count($winners) > 1),
            'closed_by'         => ($viewer !== null) ? (int) $viewer['id'] : 0,
            'result_message_id' => (int) $result_id,
        ));
    }

    return array('ok' => true, 'error' => '', 'result_message_id' => (int) $result_id);
}

/**
 * Closes the polls of a channel whose time ran out. Asked whenever the
 * channel is opened or read again, so no scheduled job is needed.
 *
 * @param int $channel_id
 */
function ws_polls_autoclose($channel_id)
{
    if (!ws_interact_ready()) {
        return;
    }

    foreach ((array) db_items("SELECT * FROM ws_polls
        WHERE channel_id = '" . (int) $channel_id . "' AND closed_at = 0 AND closes_at > 0 AND closes_at <= '" . time() . "'
        LIMIT 10") as $poll) {
        ws_poll_close(null, $poll);
    }
}
