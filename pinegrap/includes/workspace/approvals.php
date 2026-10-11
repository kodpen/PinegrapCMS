<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - approval requests: a message that asks chosen people of the
 * channel to approve something, with a card under it that shows who has
 * approved, who has refused and who is still to answer.
 *
 * The text of the request is the message (its tags work as anywhere else;
 * the first record tagged in it is the record the card points at); the
 * title, the rule, the deadline and the people are the card's. The rule is
 * "any" - the first approval settles it - or "all" - everybody has to
 * approve. A refusal settles it at once, and a deadline that passes with the
 * rule unmet lets it expire. Only the people asked may answer, each once;
 * the server checks that, whatever the screen offers. The person who asked
 * may take the request back, and staff (roles 0-2) may close it.
 *
 * A settled request is closed first, with one conditional update, so two
 * answers arriving together do not both write a result. The result is
 * written into the channel as a locked decision, in the name of the person
 * who asked, answering the request: it lands on the Decisions tab and the
 * Decision Timeline, and a request of a discussion has its decision copied
 * into the channel the way every decision of a discussion is. A request
 * taken back or closed is said as a line of the system's.
 *
 * The people asked are told in their inbox, and everybody concerned when the
 * request is settled. A day before a deadline the ones still to answer are
 * reminded once; the reminder and the expiry run with the scheduled actions
 * (ws_scheduled_run()), and a channel that is opened lets its overdue
 * requests expire as it does its polls.
 *
 * A guest of a room reads the card; they are never asked.
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
 * The most people one request asks.
 */
define('WS_APPROVAL_MAX_PEOPLE', 10);

/**
 * How long before its deadline a request reminds the people still to
 * answer, in seconds.
 */
define('WS_APPROVAL_REMIND_BEFORE', 86400);

/**
 * Are the tables there (2026.4.8, 8.87)?
 *
 * @return bool
 */
function ws_approvals_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ws_approvals', 'ws_approval_people')") === 2);
    }

    return $ready;
}

/**
 * @param int $approval_id
 * @return array|null
 */
function ws_approval($approval_id)
{
    if (!ws_approvals_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_approvals WHERE id = '" . (int) $approval_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The request a message carries, if any.
 *
 * @param int $message_id
 * @return array|null
 */
function ws_approval_of_message($message_id)
{
    if (!ws_approvals_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_approvals WHERE message_id = '" . (int) $message_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The rules, as the form and the card name them.
 *
 * @return array rule => label
 */
function ws_approval_rules()
{
    return array(
        'any' => lang('Any one of them'),
        'all' => lang('All of them'),
    );
}

/**
 * Where a request stands, from its rule and the answers given so far. A
 * refusal settles it whatever the rule; "any" is met by one approval, "all"
 * by everybody's; a deadline that passed with the rule unmet lets it expire.
 * Pure: no database, no clock of its own.
 *
 * @param string   $rule      any | all
 * @param string[] $decisions one per person asked: pending | approved | rejected
 * @param int      $now
 * @param int      $closes_at 0 = no deadline
 * @return string open | approved | rejected | expired
 */
function ws_approval_outcome($rule, $decisions, $now, $closes_at)
{
    $decisions = array_values((array) $decisions);
    $approved = 0;

    foreach ($decisions as $decision) {
        if ($decision === 'rejected') {
            return 'rejected';
        }

        if ($decision === 'approved') {
            $approved++;
        }
    }

    if (($approved > 0) && (($rule !== 'all') || ($approved === count($decisions)))) {
        return 'approved';
    }

    if (((int) $closes_at > 0) && ((int) $now >= (int) $closes_at)) {
        return 'expired';
    }

    return 'open';
}

/**
 * The people of a channel who may be asked: members of the team who are in
 * the channel and may write in it. A guest of a room is not a member of the
 * channel, so never among them.
 *
 * @param array $channel
 * @param int[] $user_ids
 * @return int[] the ones that may, in the order given
 */
function ws_approval_eligible($channel, $user_ids)
{
    $out = array();

    foreach ((array) $user_ids as $user_id) {
        $user_id = (int) $user_id;

        if (($user_id <= 0) || in_array($user_id, $out, true) || !ws_channel_membership($channel['id'], $user_id)) {
            continue;
        }

        if (ws_can_post_channel(ws_rights_for_id($user_id), $channel)) {
            $out[] = $user_id;
        }
    }

    return $out;
}

/**
 * The answers given to one request, per person, in the order they were asked.
 *
 * @param int $approval_id
 * @return array[] user_id, decision, note, decided_at
 */
function ws_approval_people($approval_id)
{
    return (array) db_items("SELECT user_id, decision, note, decided_at FROM ws_approval_people
        WHERE approval_id = '" . (int) $approval_id . "' ORDER BY decided_at = 0, decided_at, user_id");
}

/**
 * Asks people of the channel to approve something. The text is the message;
 * the title, the rule, the deadline and the people are the card's.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $data   title, text, approvers (user ids), rule (any | all), closes_at (timestamp, 0 = none)
 * @param int   $app_id an application asking for its owner (0 = the person)
 * @param bool  $strict false drops the people who may not be asked rather than refusing (a scheduled action)
 * @return array ok, error, field, message_id, approval_id
 */
function ws_approval_create($viewer, $channel, $data, $app_id = 0, $strict = true)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'message_id' => 0, 'approval_id' => 0);
    };

    $checked = ws_approval_input($viewer, $channel, $data, $strict);

    if (!$checked['ok']) {
        return $fail($checked['error'], $checked['field']);
    }

    $title = $checked['title'];
    $text = $checked['text'];
    $body = '**' . $title . '**' . (($text !== '') ? "\n\n" . $text : '');

    if (mb_strlen($body) > WS_MESSAGE_MAX) {
        return $fail(lang(array('string' => 'A message can be at most {var:1} characters long.', 'vars' => WS_MESSAGE_MAX)), 'text');
    }

    $sent = ws_message_send($viewer, $channel, $body, array('app_id' => (int) $app_id));

    if (!$sent['ok']) {
        return $fail($sent['error'], 'text');
    }

    // The record the request is about: the first record tagged in its text.
    $record_type = '';
    $record_id = 0;

    foreach (ws_tokens($text) as $token) {
        if (($token['sigil'] === '#') && in_array($token['type'], ws_record_type_keys(), true)) {
            $record_type = $token['type'];
            $record_id = (int) $token['id'];
            break;
        }
    }

    $now = time();

    // A deadline less than a day away was just told to the people asked:
    // the reminder is marked sent so it never goes (the expiry still runs).
    $reminded_at = (((int) $checked['closes_at'] > 0) && ((int) $checked['closes_at'] - $now < WS_APPROVAL_REMIND_BEFORE)) ? $now : 0;

    db("INSERT INTO ws_approvals (message_id, channel_id, title, rule, record_type, record_id, closes_at, reminded_at, created_by, created_at)
        VALUES ('" . (int) $sent['message_id'] . "', '" . (int) $channel['id'] . "', '" . e($title) . "', '" . e($checked['rule']) . "',
            '" . e($record_type) . "', '" . $record_id . "', '" . (int) $checked['closes_at'] . "', '" . $reminded_at . "', '" . (int) $viewer['id'] . "', '" . $now . "')");

    $approval_id = (int) mysqli_insert_id(db::$con);

    if ($approval_id <= 0) {
        return $fail(lang('The message could not be saved.'));
    }

    foreach ($checked['approvers'] as $user_id) {
        db("INSERT IGNORE INTO ws_approval_people (approval_id, user_id) VALUES ('" . $approval_id . "', '" . (int) $user_id . "')");
    }

    ws_message_touch($sent['message_id']);

    foreach ($checked['approvers'] as $user_id) {
        if (($app_id > 0) || ((int) $user_id !== (int) $viewer['id'])) {
            ws_notify($user_id, 'approval_requested', array('channel_id' => (int) $channel['id'], 'message_id' => (int) $sent['message_id'], 'actor_id' => (int) $viewer['id']));
        }
    }

    // Announced for public channels only, the way their messages are.
    if (((string) $channel['kind'] === 'public') && function_exists('pg_announce')) {
        pg_announce('workspace.approval.requested', array(
            'id'          => $approval_id,
            'message_id'  => (int) $sent['message_id'],
            'channel_id'  => (int) $channel['id'],
            'channel'     => (string) $channel['name'],
            'title'       => $title,
            'rule'        => $checked['rule'],
            'approvers'   => $checked['approvers'],
            'closes_at'   => (int) $checked['closes_at'],
            'record_type' => $record_type,
            'record_id'   => $record_id,
            'created_by'  => (int) $viewer['id'],
            'app_id'      => (int) $app_id,
        ));
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'message_id' => (int) $sent['message_id'], 'approval_id' => $approval_id);
}

/**
 * Checks a request as the form, the API or a scheduled action sends it,
 * before anything is written: a dry run meets the same refusals.
 *
 * @param array $viewer
 * @param array $channel
 * @param array $data
 * @param bool  $strict
 * @return array ok, error, field, title, text, approvers, rule, closes_at
 */
function ws_approval_input($viewer, $channel, $data, $strict = true)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field);
    };

    if (!ws_approvals_ready()) {
        return $fail(lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    if (!ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    // The title is said in the decision as it is: no tags, no new lines.
    $title = mb_substr(trim(preg_replace('/\s+/u', ' ', str_replace(array('<', '>', '*'), '', (string) ($data['title'] ?? '')))), 0, 255);

    if ($title === '') {
        return $fail(lang('An approval request needs a title.'), 'title');
    }

    $text = ws_tokens_normalise(trim(str_replace("\r\n", "\n", (string) ($data['text'] ?? ''))));
    $given = array_values(array_unique(array_filter(array_map('intval', (array) ($data['approvers'] ?? array())))));

    if (empty($given)) {
        return $fail(lang('Choose who is to approve it.'), 'approvers');
    }

    $approvers = ws_approval_eligible($channel, $given);

    if ($strict && (count($approvers) !== count($given))) {
        return $fail(lang('Only people in the channel who may write in it can be asked to approve.'), 'approvers');
    }

    if (empty($approvers)) {
        return $fail(lang('Choose who is to approve it.'), 'approvers');
    }

    if (count($approvers) > WS_APPROVAL_MAX_PEOPLE) {
        return $fail(lang(array('string' => 'At most {var:1} people can be asked to approve.', 'vars' => WS_APPROVAL_MAX_PEOPLE)), 'approvers');
    }

    $rule = ((string) ($data['rule'] ?? 'any') === 'all') ? 'all' : 'any';
    $closes_at = max(0, (int) ($data['closes_at'] ?? 0));

    if (($closes_at > 0) && ($closes_at <= time() + 60)) {
        return $fail(lang('The deadline has to be later than now.'), 'closes_at');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'title' => $title, 'text' => $text, 'approvers' => $approvers, 'rule' => $rule, 'closes_at' => $closes_at);
}

/**
 * The requests a set of messages carries, as this reader sees them.
 *
 * @param int[] $message_ids
 * @param array $viewer
 * @return array message id => card
 */
function ws_approvals_map($message_ids, $viewer)
{
    $message_ids = array_values(array_filter(array_map('intval', (array) $message_ids)));

    if (empty($message_ids) || !ws_approvals_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT * FROM ws_approvals WHERE message_id IN (" . implode(',', $message_ids) . ")");

    if (empty($rows)) {
        return array();
    }

    $ids = array();
    $user_ids = array();
    $tokens = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
        $user_ids[] = (int) $row['created_by'];

        if ($row['record_type'] !== '') {
            $tokens[] = array('sigil' => '#', 'type' => (string) $row['record_type'], 'id' => (int) $row['record_id']);
        }
    }

    $answers = array();

    foreach ((array) db_items("SELECT * FROM ws_approval_people WHERE approval_id IN (" . implode(',', $ids) . ")
        ORDER BY decided_at = 0, decided_at, user_id") as $row) {
        $answers[(int) $row['approval_id']][] = $row;
        $user_ids[] = (int) $row['user_id'];
    }

    $people = ws_people($user_ids);
    $refs = empty($tokens) ? array() : ws_refs_resolve($viewer, $tokens);
    $guest = ((int) ($viewer['guest_id'] ?? 0) > 0);
    $rules = ws_approval_rules();
    $out = array();

    foreach ($rows as $row) {
        $approval_id = (int) $row['id'];
        $open = ((int) $row['closed_at'] === 0);
        $channel = ws_channel($row['channel_id']);
        $can_post = !$guest && $channel && ws_can_post_channel($viewer, $channel)
            && !ws_message_in_past(array('id' => (int) $row['message_id'], 'channel_id' => (int) $row['channel_id']));
        $list = array();
        $counts = array('pending' => 0, 'approved' => 0, 'rejected' => 0);
        $mine = '';

        foreach ($answers[$approval_id] ?? array() as $answer) {
            $user_id = (int) $answer['user_id'];
            $person = $people[$user_id] ?? array();
            $decision = (string) $answer['decision'];
            $counts[$decision] = ($counts[$decision] ?? 0) + 1;

            if (!$guest && ($user_id === (int) $viewer['id'])) {
                $mine = $decision;
            }

            $list[] = array(
                'id'       => $user_id,
                'name'     => (string) ($person['name'] ?? ws_person_name($user_id)),
                'avatar'   => (string) ($person['avatar'] ?? ''),
                'avatar_kind' => (string) ($person['avatar_kind'] ?? ''),
                'decision' => $decision,
                'note'     => (string) $answer['note'],
                'time'     => ((int) $answer['decided_at'] > 0) ? ws_time_label($answer['decided_at']) : '',
            );
        }

        $record = null;
        $key = $row['record_type'] . ':' . (int) $row['record_id'];

        if (($row['record_type'] !== '') && isset($refs[$key]) && $refs[$key]['known']) {
            $record = array(
                'type'  => (string) $row['record_type'],
                'id'    => (int) $row['record_id'],
                'label' => (string) $refs[$key]['label'],
                'icon'  => (string) $refs[$key]['icon'],
                'meta'  => (string) $refs[$key]['meta'],
            );
        }

        $asker = $people[(int) $row['created_by']] ?? null;

        $out[(int) $row['message_id']] = array(
            'id'          => $approval_id,
            'title'       => (string) $row['title'],
            'rule'        => (string) $row['rule'],
            'rule_label'  => (string) ($rules[$row['rule']] ?? ''),
            'outcome'     => (string) $row['outcome'],
            'open'        => $open,
            'closes'      => ((int) $row['closes_at'] > 0) ? date('d.m.Y H:i', (int) $row['closes_at']) : '',
            'closed_on'   => $open ? '' : ws_time_label($row['closed_at']),
            'closed_by'   => ((int) $row['closed_by'] > 0) ? ws_person_name($row['closed_by']) : '',
            'asker'       => $asker ? array('id' => (int) $asker['id'], 'name' => (string) $asker['name'], 'avatar' => (string) $asker['avatar']) : null,
            'record'      => $record,
            'people'      => $list,
            'counts'      => $counts,
            'mine'        => $mine,
            'can_decide'  => $open && $can_post && ($mine === 'pending'),
            'can_withdraw' => $open && !$guest && ((int) $row['created_by'] === (int) $viewer['id']) && ($channel !== null) && ws_can_read_channel($viewer, $channel),
            'can_close'   => $open && !$guest && ((int) $row['created_by'] !== (int) $viewer['id']) && ((int) ($viewer['role'] ?? 3) < 3)
                && ($channel !== null) && ws_can_read_channel($viewer, $channel),
            'result_id'   => (int) $row['result_message_id'],
        );
    }

    return $out;
}

/**
 * One person's answer: approve or refuse, with a note. Only the people
 * asked, each once, while the request is open and they may write in its
 * channel.
 *
 * @param array  $viewer
 * @param array  $approval
 * @param string $decision approve | reject
 * @param string $note
 * @return array ok, error
 */
function ws_approval_decide($viewer, $approval, $decision, $note = '')
{
    if (!in_array($decision, array('approve', 'reject'), true)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    if ((int) $approval['closed_at'] > 0) {
        return array('ok' => false, 'error' => lang('The approval request is closed.'));
    }

    // Its time ran out before the answer came: it expires now.
    if (((int) $approval['closes_at'] > 0) && ((int) $approval['closes_at'] <= time())) {
        ws_approval_evaluate($approval);

        return array('ok' => false, 'error' => lang('The approval request is closed.'));
    }

    $answer = db_item("SELECT decision FROM ws_approval_people
        WHERE approval_id = '" . (int) $approval['id'] . "' AND user_id = '" . (int) $viewer['id'] . "'");

    if (!is_array($answer)) {
        return array('ok' => false, 'error' => lang('You were not asked to approve this.'));
    }

    if ((string) $answer['decision'] !== 'pending') {
        return array('ok' => false, 'error' => lang('You have already answered.'));
    }

    $message = ws_message($approval['message_id']);

    if (!$message || ((int) $message['deleted_at'] > 0)) {
        return array('ok' => false, 'error' => lang('That approval request could not be found.'));
    }

    if (!ws_can_post_channel($viewer, ws_channel($approval['channel_id']))) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'));
    }

    $note = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $note)), 0, 255);

    // Pending first, so an answer sent twice counts once.
    db("UPDATE ws_approval_people SET decision = '" . (($decision === 'approve') ? 'approved' : 'rejected') . "',
            note = '" . e($note) . "', decided_at = '" . time() . "'
        WHERE approval_id = '" . (int) $approval['id'] . "' AND user_id = '" . (int) $viewer['id'] . "' AND decision = 'pending'");

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => false, 'error' => lang('You have already answered.'));
    }

    ws_message_touch($approval['message_id']);
    ws_approval_evaluate($approval, (int) $viewer['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * Settles a request whose rule is met or whose deadline passed; leaves an
 * open one as it is.
 *
 * @param array $approval
 * @param int   $actor_id whoever's answer settled it (0 = the clock)
 * @return string the outcome now
 */
function ws_approval_evaluate($approval, $actor_id = 0)
{
    $decisions = array();

    foreach (ws_approval_people($approval['id']) as $row) {
        $decisions[] = (string) $row['decision'];
    }

    $outcome = ws_approval_outcome($approval['rule'], $decisions, time(), (int) $approval['closes_at']);

    if ($outcome !== 'open') {
        ws_approval_settle($approval, $outcome, $actor_id);
    }

    return $outcome;
}

/**
 * The person who asked takes the request back; staff close it. Either way
 * nothing was decided, and the channel is told so in a line of the system's.
 *
 * @param array $viewer
 * @param array $approval
 * @return array ok, error
 */
function ws_approval_close($viewer, $approval)
{
    if ((int) $approval['closed_at'] > 0) {
        return array('ok' => false, 'error' => lang('The approval request is closed.'));
    }

    $channel = ws_channel($approval['channel_id']);

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('That approval request could not be found.'));
    }

    if (((int) $approval['created_by'] !== (int) $viewer['id']) && ((int) $viewer['role'] >= 3)) {
        return array('ok' => false, 'error' => lang('Only the person who asked, or staff, can close the approval request.'));
    }

    ws_approval_settle($approval, 'withdrawn', (int) $viewer['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * Closes a request with its outcome and writes the result into the channel:
 * a locked decision for an approval, a refusal or an expiry; a line of the
 * system's for a request taken back or closed. The people concerned are
 * told.
 *
 * @param array  $approval
 * @param string $outcome  approved | rejected | expired | withdrawn
 * @param int    $actor_id
 * @return int the result message, 0 when another request settled it first
 */
function ws_approval_settle($approval, $outcome, $actor_id = 0)
{
    $now = time();

    // Closed first, so two answers arriving together do not both write a result.
    db("UPDATE ws_approvals SET closed_at = '" . $now . "', closed_by = '" . (int) $actor_id . "', outcome = '" . e($outcome) . "'
        WHERE id = '" . (int) $approval['id'] . "' AND closed_at = 0");

    if (mysqli_affected_rows(db::$con) < 1) {
        return 0;
    }

    $channel = ws_channel($approval['channel_id']);
    $answers = ws_approval_people($approval['id']);
    $people = ws_people(array_map(function ($row) { return (int) $row['user_id']; }, $answers));
    $name = function ($user_id) use ($people) {
        return (string) ($people[(int) $user_id]['name'] ?? ws_person_name($user_id));
    };
    $title = (string) $approval['title'];
    $by = array('approved' => array(), 'rejected' => array(), 'pending' => array());

    foreach ($answers as $row) {
        $by[(string) $row['decision']][] = $row;
    }

    $body = '';

    if ($outcome === 'approved') {
        $body = lang(array('string' => 'Approved: **{var:1}** — {var:2}', 'vars' => array($title, implode(', ', array_map(function ($row) use ($name) {
            return $name($row['user_id']);
        }, $by['approved'])))));
    } elseif ($outcome === 'rejected') {
        $body = lang(array('string' => 'Rejected: **{var:1}** — {var:2}', 'vars' => array($title, implode('; ', array_map(function ($row) use ($name) {
            return $name($row['user_id']) . ((string) $row['note'] !== '' ? ': ' . $row['note'] : '');
        }, $by['rejected'])))));
    } elseif ($outcome === 'expired') {
        $body = lang(array('string' => 'Expired without a decision: **{var:1}** — still waiting for {var:2}', 'vars' => array($title, implode(', ', array_map(function ($row) use ($name) {
            return $name($row['user_id']);
        }, $by['pending'])))));
    }

    $result_id = 0;

    if ($channel && ($body !== '')) {
        // Written in the asker's name: the decision answers their request.
        $asker = ws_viewer((array) pg_load_user_row((int) $approval['created_by']));

        if ($asker['member'] && ws_can_post_channel($asker, $channel)) {
            $sent = ws_message_send($asker, $channel, $body, array('kind' => 'decision', 'parent_id' => (int) $approval['message_id']));
            $result_id = $sent['ok'] ? (int) $sent['message_id'] : 0;

            // Nobody edits it; staff alone may delete it or take its mark off.
            if (($result_id > 0) && function_exists('ws_changes_ready') && ws_changes_ready()) {
                db("UPDATE ws_messages SET locked = 1 WHERE id = '" . $result_id . "'");

                // Settled in a discussion: the channel's copy is locked as well.
                if (function_exists('ws_thread_copy_sync')) {
                    ws_thread_copy_sync($result_id);
                }
            }
        }

        if ($result_id === 0) {
            $result_id = ws_message_system($channel['id'], $body);
        }
    } elseif ($channel) {
        $actor = '<@user:' . (int) $actor_id . '>';

        $result_id = ((int) $actor_id === (int) $approval['created_by'])
            ? ws_message_system($channel['id'], lang(array('string' => '{var:1} took back the approval request: {var:2}', 'vars' => array($actor, $title))))
            : ws_message_system($channel['id'], lang(array('string' => '{var:1} closed the approval request without a decision: {var:2}', 'vars' => array($actor, $title))));
    }

    db("UPDATE ws_approvals SET result_message_id = '" . (int) $result_id . "' WHERE id = '" . (int) $approval['id'] . "'");
    ws_message_touch($approval['message_id']);

    // Everybody concerned hears how it ended, but not from themselves.
    $told = array((int) $approval['created_by']);

    foreach ($answers as $row) {
        $told[] = (int) $row['user_id'];
    }

    foreach (array_unique($told) as $user_id) {
        if ($user_id !== (int) $actor_id) {
            ws_notify($user_id, 'approval_decided', array('channel_id' => (int) $approval['channel_id'], 'message_id' => (int) $approval['message_id'], 'actor_id' => (int) $actor_id));
        }
    }

    // Announced for public channels only, the way their messages are.
    if ($channel && ((string) $channel['kind'] === 'public') && function_exists('pg_announce')) {
        $list = array();

        foreach ($answers as $row) {
            $list[] = array('user_id' => (int) $row['user_id'], 'decision' => (string) $row['decision'], 'note' => (string) $row['note']);
        }

        pg_announce('workspace.approval.decided', array(
            'id'                => (int) $approval['id'],
            'message_id'        => (int) $approval['message_id'],
            'channel_id'        => (int) $channel['id'],
            'channel'           => (string) $channel['name'],
            'title'             => $title,
            'rule'              => (string) $approval['rule'],
            'outcome'           => $outcome,
            'people'            => $list,
            'closed_by'         => (int) $actor_id,
            'result_message_id' => (int) $result_id,
        ));
    }

    return (int) $result_id;
}

/**
 * Lets the requests of a channel whose deadline passed expire. Asked when
 * the channel is opened or read again, beside its polls.
 *
 * @param int $channel_id
 */
function ws_approvals_autoclose($channel_id)
{
    if (!ws_approvals_ready()) {
        return;
    }

    foreach ((array) db_items("SELECT * FROM ws_approvals
        WHERE channel_id = '" . (int) $channel_id . "' AND closed_at = 0 AND closes_at > 0 AND closes_at <= '" . time() . "'
        LIMIT 10") as $approval) {
        ws_approval_evaluate($approval);
    }
}

/**
 * Is a deadline due - passed, or a day away with the reminder not yet sent?
 * One indexed read (idx_due), for ws_scheduled_due().
 *
 * @return bool
 */
function ws_approvals_due()
{
    if (!ws_approvals_ready()) {
        return false;
    }

    $now = time();

    return (bool) db_value("SELECT id FROM ws_approvals
        WHERE closed_at = 0 AND closes_at > 0 AND closes_at <= '" . ($now + WS_APPROVAL_REMIND_BEFORE) . "'
        AND (reminded_at = 0 OR closes_at <= '" . $now . "')
        LIMIT 1");
}

/**
 * Lets the overdue requests expire and reminds, once, the people still to
 * answer a request whose deadline is a day away. Run with the scheduled
 * actions; a few at a time.
 *
 * @param int $limit
 * @return array expired, reminded
 */
function ws_approvals_run($limit = 20)
{
    $result = array('expired' => 0, 'reminded' => 0);

    if (!ws_approvals_ready()) {
        return $result;
    }

    $now = time();

    foreach ((array) db_items("SELECT * FROM ws_approvals
        WHERE closed_at = 0 AND closes_at > 0 AND closes_at <= '" . $now . "'
        ORDER BY closes_at LIMIT " . max(1, (int) $limit)) as $approval) {
        if (ws_approval_evaluate($approval) !== 'open') {
            $result['expired']++;
        }
    }

    foreach ((array) db_items("SELECT * FROM ws_approvals
        WHERE closed_at = 0 AND closes_at > '" . $now . "' AND closes_at <= '" . ($now + WS_APPROVAL_REMIND_BEFORE) . "' AND reminded_at = 0
        ORDER BY closes_at LIMIT " . max(1, (int) $limit)) as $approval) {
        // Claimed first: two runs that overlap remind once.
        db("UPDATE ws_approvals SET reminded_at = '" . $now . "' WHERE id = '" . (int) $approval['id'] . "' AND reminded_at = 0");

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        $message = ws_message($approval['message_id']);

        if (!$message || ((int) $message['deleted_at'] > 0)) {
            continue;
        }

        foreach ((array) db_values("SELECT user_id FROM ws_approval_people
            WHERE approval_id = '" . (int) $approval['id'] . "' AND decision = 'pending'") as $user_id) {
            ws_notify((int) $user_id, 'approval_reminder', array('channel_id' => (int) $approval['channel_id'], 'message_id' => (int) $approval['message_id'], 'actor_id' => (int) $approval['created_by']));
            $result['reminded']++;
        }
    }

    return $result;
}

/**
 * The requests waiting for this person's answer, newest first, from the
 * channels they may still write in: for the overview.
 *
 * @param array $viewer
 * @param int   $limit
 * @return array[]
 */
function ws_approvals_waiting($viewer, $limit = 5)
{
    if (!ws_approvals_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT a.* FROM ws_approval_people p
        INNER JOIN ws_approvals a ON a.id = p.approval_id AND a.closed_at = 0
        INNER JOIN ws_messages m ON m.id = a.message_id AND m.deleted_at = 0
        WHERE p.user_id = '" . (int) $viewer['id'] . "' AND p.decision = 'pending'
        ORDER BY a.id DESC LIMIT 50");

    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php';
    $out = array();

    foreach ($rows as $row) {
        $channel = ws_channel($row['channel_id']);

        if (!$channel || !ws_can_post_channel($viewer, $channel)) {
            continue;
        }

        $out[] = array(
            'id'         => (int) $row['id'],
            'message_id' => (int) $row['message_id'],
            'title'      => (string) $row['title'],
            'asker'      => ws_person_name($row['created_by']),
            'closes'     => ((int) $row['closes_at'] > 0) ? date('d.m.Y H:i', (int) $row['closes_at']) : '',
            'time'       => ws_time_label($row['created_at']),
            'channel'    => array('id' => (int) $channel['id'], 'name' => (string) $channel['name'], 'private' => ($channel['kind'] !== 'public')),
            'url'        => $base . '?channel=' . (int) $channel['id'] . '&message=' . (int) $row['message_id'],
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * An inbox row about a request, as a sentence (notify.php).
 *
 * @param array  $viewer
 * @param array  $row
 * @param string $actor
 * @param string $base
 * @return array title, body, url, icon
 */
function ws_approval_inbox_describe($viewer, $row, $actor, $base)
{
    $approval = ws_approval_of_message((int) $row['message_id']);
    $title = $approval ? (string) $approval['title'] : '';
    $url = $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'];

    if ($row['kind'] === 'approval_requested') {
        return array(
            'title' => lang(array('string' => '{var:1} asks you to approve', 'vars' => $actor)),
            'body'  => $title,
            'url'   => $url,
            'icon'  => 'bi-patch-question',
        );
    }

    if ($row['kind'] === 'approval_reminder') {
        return array(
            'title' => lang(array('string' => 'Your approval is awaited until {var:1}', 'vars' => ($approval && ((int) $approval['closes_at'] > 0)) ? date('d.m.Y H:i', (int) $approval['closes_at']) : '')),
            'body'  => $title,
            'url'   => $url,
            'icon'  => 'bi-hourglass-split',
        );
    }

    $sentences = array(
        'approved'  => 'Approved: {var:1}',
        'rejected'  => 'Rejected: {var:1}',
        'expired'   => 'Expired without a decision: {var:1}',
        'withdrawn' => 'Closed without a decision: {var:1}',
    );
    $outcome = $approval ? (string) $approval['outcome'] : '';

    return array(
        'title' => isset($sentences[$outcome]) ? lang(array('string' => $sentences[$outcome], 'vars' => $title)) : lang('Approval request'),
        'body'  => ((int) $row['actor_id'] > 0) ? $actor : '',
        'url'   => $url,
        'icon'  => ($outcome === 'approved') ? 'bi-patch-check' : 'bi-patch-exclamation',
    );
}

/**
 * The texts of approval requests on the channel screen and the overview.
 *
 * @return array key => text
 */
function ws_approvals_js_strings()
{
    return array(
        'apv_new'            => lang('Ask for approval'),
        'apv_form_title'     => lang('Ask for approval'),
        'apv_title'          => lang('Title'),
        'apv_title_help'     => lang('What is to be approved, in a line.'),
        'apv_text'           => lang('Details'),
        'apv_text_help'      => lang('Optional. @ and # tags work here; the first record tagged is shown on the card.'),
        'apv_people'         => lang('Who approves'),
        'apv_people_help'    => lang('People of this channel, one to ten.'),
        'apv_rule'           => lang('Approved when'),
        'apv_rule_any'       => lang('Any one of them approves'),
        'apv_rule_all'       => lang('All of them approve'),
        'apv_closes'         => lang('Deadline'),
        'apv_closes_time'    => lang('Time'),
        'apv_closes_help'    => lang('Left empty, it waits until it is answered. A day before, the people still to answer are reminded.'),
        'apv_send'           => lang('Ask'),
        'apv_result_help'    => lang('A refusal settles it at once. The result is written into the channel as a locked decision and lands on the Decision Timeline.'),
        'apv_card'           => lang('Approval request'),
        'apv_asked_by'       => ws_js_template('asked by {var:1}', 1),
        'apv_closes_on'      => ws_js_template('until {var:1}', 1),
        'apv_closed_on'      => ws_js_template('Closed {var:1}', 1),
        'apv_pending'        => lang('Waiting'),
        'apv_approved'       => lang('Approved'),
        'apv_rejected'       => lang('Rejected'),
        'apv_has_approved'   => lang('Has approved'),
        'apv_has_rejected'   => lang('Has rejected'),
        'apv_expired'        => lang('Expired'),
        'apv_withdrawn'      => lang('Closed without a decision'),
        'apv_approve'        => lang('Approve'),
        'apv_reject'         => lang('Reject'),
        'apv_reject_note'    => lang('Why? (optional)'),
        'apv_reject_confirm' => lang('Reject this request? It is settled at once.'),
        'apv_withdraw'       => lang('Take back'),
        'apv_withdraw_confirm' => lang('Take back the approval request? Nothing is decided and the channel is told.'),
        'apv_close'          => lang('Close'),
        'apv_close_confirm'  => lang('Close the approval request without a decision? The channel is told.'),
        'apv_result'         => lang('See the decision'),
        'apv_count'          => ws_js_template('{var:1} of {var:2} approved', 2),
        'apv_home'           => lang('Approvals waiting for you'),
        'apv_home_until'     => ws_js_template('until {var:1}', 1),
        'apv_sa_title'       => lang('Title of the request'),
        'apv_sa_text'        => lang('Details'),
        'apv_sa_people'      => lang('Who approves'),
        'apv_sa_people_help' => lang('People of the channel; when it runs, anybody no longer in it is left out.'),
        'apv_sa_rule'        => lang('Approved when'),
        'sa_do_approval'     => lang('Ask for approval in a channel'),
    );
}
