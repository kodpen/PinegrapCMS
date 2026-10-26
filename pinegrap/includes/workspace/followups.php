<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - follow-ups: a record brought back to somebody on a day.
 *
 * A follow-up is an ordinary task (tasks.php) tagged with the record it is
 * about, on a channel or for a person, due on the day and reminded by the
 * task reminder (reminders.php): at the due time unless the caller asks
 * otherwise, in the inbox and by e-mail, and told once more if it goes
 * overdue. It has no table of its own: an open task carrying the record's tag
 * is found again by that tag (ws_refs), and the customer history lists it
 * with the customer's other tasks.
 *
 * Every caller opens it through ws_followup_task_open(), as the person it is
 * opened by, so it meets every check of a task that person makes by hand:
 * "Remind me about this customer" on a customer channel's card
 * (ws_customer_followup_open()), and jobs that bring a record back on their
 * own - a sales quote about to expire, an overdue receivable - which act as
 * the person the record is for and ask for one open follow-up per record.
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
 * The task a follow-up becomes, as ws_task_create() takes it, checked as far
 * as it can be without the database: a record to tag, a day, a reminder that
 * can still go. Nothing here decides who may open it; ws_task_create() does.
 *
 * @param int   $viewer_id the person opening it
 * @param array $input     title, description, refs (tokens as text or
 *                         type/id rows; site records only, at least one),
 *                         channel_id, assignees (ids), priority, due_date
 *                         (Y-m-d or d.m.Y), due_time (H:MM), remind_minutes
 *                         (minutes before the due time; left out: at the due
 *                         time; null: no reminder)
 * @param int   $now
 * @return array ok, error, field, data (the fields for ws_task_create())
 */
function ws_followup_task_data($viewer_id, $input, $now)
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'data' => array());
    };

    $refs = array();

    foreach (ws_task_ref_tokens($input['refs'] ?? array()) as $token) {
        if (in_array($token['type'], ws_record_type_keys(), true)) {
            $key = '<#' . $token['type'] . ':' . (int) $token['id'] . '>';
            $refs[$key] = $key;
        }
    }

    if (empty($refs)) {
        return $fail(lang('A follow-up needs the record it is about.'), 'refs');
    }

    $due = ws_date_or_null($input['due_date'] ?? '');

    if ($due === false) {
        return $fail(lang('Write the date as day.month.year.'), 'due_date');
    }

    if ($due === null) {
        return $fail(lang('A follow-up needs a day.'), 'due_date');
    }

    $time = ws_task_time_or_null($input['due_time'] ?? '');

    if ($time === false) {
        return $fail(lang('Write the time as hour:minute.'), 'due_time');
    }

    $minutes = array_key_exists('remind_minutes', $input) ? ws_task_remind_minutes_or_null($input['remind_minutes']) : 0;

    if ($minutes === false) {
        return $fail(lang('A reminder can be set up to 60 days before the task is due.'), 'remind_minutes');
    }

    // A task already due is reminded of nothing (ws_task_reminder_sync());
    // a reminder whose moment alone has passed goes at once.
    if (($minutes !== null) && (ws_task_due_at(array('due_date' => $due, 'due_time' => $time)) <= (int) $now)) {
        return $fail(lang('The reminder would go in the past: pick a later day or time.'), 'due_date');
    }

    $channel_id = max(0, (int) ($input['channel_id'] ?? 0));
    $people = array();

    foreach ((array) ($input['assignees'] ?? array()) as $user_id) {
        if ((int) $user_id > 0) {
            $people[(int) $user_id] = (int) $user_id;
        }
    }

    // On a channel the people of the channel see it, and the reminder goes to
    // the person who opened it while nobody is on it; without one it is that
    // person's own.
    if (empty($people) && ($channel_id === 0)) {
        $people[(int) $viewer_id] = (int) $viewer_id;
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'data' => array(
        'title'          => (string) ($input['title'] ?? ''),
        'description'    => (string) ($input['description'] ?? ''),
        'refs'           => array_values($refs),
        'channel_id'     => $channel_id,
        'assignees'      => array_values($people),
        'status'         => 'todo',
        'priority'       => (string) ($input['priority'] ?? 'normal'),
        'due_date'       => $due,
        'due_time'       => $time,
        'remind_minutes' => $minutes,
    ));
}

/**
 * An open task already following a record up in the same place: in the
 * channel, or, with no channel, one with one of these people on it.
 *
 * @param string $ref        the record's tag, '<#type:id>'
 * @param int    $channel_id
 * @param int[]  $user_ids
 * @return int the newest such task's id, 0 for none
 */
function ws_followup_task_find($ref, $channel_id, $user_ids)
{
    $tokens = ws_tokens($ref);

    if (empty($tokens)) {
        return 0;
    }

    $where = "r.source_type = 'task' AND r.ref_type = '" . e($tokens[0]['type']) . "' AND r.ref_id = '" . (int) $tokens[0]['id'] . "'
        AND t.status IN ('todo', 'doing', 'waiting')";

    if ((int) $channel_id > 0) {
        $where .= " AND t.channel_id = '" . (int) $channel_id . "'";
    } else {
        $ids = array();

        foreach ((array) $user_ids as $user_id) {
            if ((int) $user_id > 0) {
                $ids[(int) $user_id] = (int) $user_id;
            }
        }

        if (empty($ids)) {
            return 0;
        }

        $where .= " AND t.channel_id = 0 AND t.id IN (SELECT task_id FROM ws_task_assignees WHERE user_id IN (" . implode(',', $ids) . "))";
    }

    return (int) db_value("SELECT t.id FROM ws_refs AS r INNER JOIN ws_tasks AS t ON t.id = r.source_id
        WHERE " . $where . " ORDER BY t.id DESC LIMIT 1");
}

/**
 * Opens a follow-up as this person: the task ws_followup_task_data() lays
 * out, made by ws_task_create() with every check of a task the person makes
 * by hand (they may post in its channel, hand it to those people). A channel
 * they can no longer write in is left out and the follow-up is theirs alone,
 * so a job acting for somebody who left the channel still reminds them.
 *
 * @param array $viewer ws_viewer() of the person it is opened as
 * @param array $input  see ws_followup_task_data()
 * @param bool  $once   hand back an open follow-up of the first record in the
 *                      same place (ws_followup_task_find()) rather than open a
 *                      second one: for jobs that run again
 * @return array ok, error, field, task_id, existing (an open one handed back)
 */
function ws_followup_task_open($viewer, $input, $once = false)
{
    $fail = function ($error, $field) {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'task_id' => 0, 'existing' => false);
    };

    if (empty($viewer['member'])) {
        return $fail(lang('Tasks can only be given to members of the team.'), 'assignees');
    }

    $check = ws_followup_task_data((int) $viewer['id'], $input, time());

    if (!$check['ok']) {
        return $fail($check['error'], $check['field']);
    }

    $data = $check['data'];

    if (($data['channel_id'] > 0) && !ws_can_post_channel($viewer, ws_channel($data['channel_id']))) {
        $data['channel_id'] = 0;

        if (empty($data['assignees'])) {
            $data['assignees'] = array((int) $viewer['id']);
        }
    }

    if ($once) {
        $found = ws_followup_task_find($data['refs'][0], $data['channel_id'], $data['assignees']);

        if ($found > 0) {
            return array('ok' => true, 'error' => '', 'field' => '', 'task_id' => $found, 'existing' => true);
        }
    }

    $made = ws_task_create($viewer, $data);

    if (!$made['ok']) {
        return $fail($made['error'], $made['field']);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'task_id' => (int) $made['task_id'], 'existing' => false);
}

/**
 * "Remind me about this customer" on a customer channel's card: a follow-up
 * for the person asking, on the channel, tagged with the record the channel
 * is about, due on the day they pick (at the time they pick, or the day's
 * reminder time) and reminded then. The title is theirs; left empty it names
 * the channel, which everybody who can see the task reads already - not the
 * customer, whom a reader without the right sees only as a kind and number.
 *
 * @param array $viewer
 * @param array $channel a ws_channels row the person may post in
 * @param array $request title, due_date, due_time
 * @return array ok, error, field, task (id, number, due_label) once opened
 */
function ws_customer_followup_open($viewer, $channel, $request)
{
    list($type, $id) = ws_channel_customer_of($channel);

    if (($type === '') || !ws_customer_exists($type, $id)) {
        return array('ok' => false, 'error' => lang('This channel is not about a customer.'), 'field' => '', 'task' => null);
    }

    $title = trim((string) ($request['title'] ?? ''));

    $made = ws_followup_task_open($viewer, array(
        'title'      => ($title !== '') ? $title : lang(array('string' => 'Follow up: {var:1}', 'vars' => array((string) $channel['name']))),
        'refs'       => array(array('type' => $type, 'id' => $id)),
        'channel_id' => (int) $channel['id'],
        'assignees'  => array((int) $viewer['id']),
        'due_date'   => (string) ($request['due_date'] ?? ''),
        'due_time'   => (string) ($request['due_time'] ?? ''),
    ));

    if (!$made['ok']) {
        return array('ok' => false, 'error' => $made['error'], 'field' => $made['field'], 'task' => null);
    }

    $task = ws_task($made['task_id']);

    return array('ok' => true, 'error' => '', 'field' => '', 'task' => array(
        'id'        => (int) $made['task_id'],
        'number'    => ws_task_number($made['task_id']),
        'due_label' => is_array($task) ? ws_task_due_label($task) : '',
    ));
}
