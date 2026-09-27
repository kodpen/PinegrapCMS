<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a task's e-mail reminder.
 *
 * A task can carry a time on its due date and a reminder: "so many minutes,
 * hours or days before", by e-mail, to the people on the task (to the person
 * who made it when nobody is on it). A task due on a day with no time is
 * reckoned from WS_TASK_REMIND_DAY_TIME that day.
 *
 * When the mail goes is worked out once, as the task is saved
 * (ws_tasks.remind_at), so finding the ones that are due is one indexed read:
 * reminded_at = 0 and remind_at up to now. That read is done by the general
 * job every five minutes (job.php, which loads the workspace only when one is
 * due), by the hourly repeating-tasks job and by any open workspace screen,
 * whose poll is told there is something to run. Every reminder is claimed by
 * a guarded update before its mail goes, so two of them at once cannot send
 * it twice.
 *
 * A reminder whose moment was missed goes as soon as it is seen, as long as
 * the task is not yet due; after that, and for a task that is finished, it is
 * dropped. Changing the date, the time or the reminder arms it again.
 *
 * A repeating task asks, in its drawer, whether every copy is reminded
 * (ws_task_recurrences.remind_each). Each copy takes the newest one's time
 * either way, and its reminder only when the series says so.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The time of day a task due on a day, with no time of its own, is reckoned from. */
define('WS_TASK_REMIND_DAY_TIME', '09:00');

/** The longest a reminder can be set before the due time: 60 days, in minutes. */
define('WS_TASK_REMIND_MAX', 86400);

/** How many reminders one run sends at most. */
define('WS_TASK_REMIND_BATCH', 20);

/**
 * Whether the database has the reminder columns (2026.4.5, 5.111). Reads
 * nothing of the workspace, so the general job can ask before loading it.
 *
 * @return bool
 */
function ws_task_reminders_ready()
{
    static $ready = null;

    if ($ready === null) {
        if (function_exists('waf_table_has_column')) {
            $ready = waf_table_has_column('ws_tasks', 'remind_at')
                && waf_table_has_column('ws_task_recurrences', 'remind_each');
        } else {
            $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND ((TABLE_NAME = 'ws_tasks' AND COLUMN_NAME = 'remind_at')
                    OR (TABLE_NAME = 'ws_task_recurrences' AND COLUMN_NAME = 'remind_each'))") === 2);
        }
    }

    return $ready;
}

/**
 * Whether a reminder is waiting to go. For the general job and the screens'
 * poll: one indexed read, nothing of the workspace needed.
 *
 * @return bool
 */
function ws_task_reminders_waiting()
{
    if (!ws_task_reminders_ready()) {
        return false;
    }

    return (bool) db_value("SELECT id FROM ws_tasks
        WHERE reminded_at = 0 AND remind_at > 0 AND remind_at <= '" . time() . "'
        LIMIT 1");
}

/**
 * The same, under the name the workspace screens' poll asks by.
 *
 * @return bool
 */
function ws_task_reminders_due()
{
    return ws_task_reminders_waiting();
}

/**
 * A time the caller typed, as HH:MM:00, or null. "9:5" is not a time;
 * "9:05", "09:05" and "09:05:00" are.
 *
 * @param mixed $value
 * @return string|null|false false when it is not a time
 */
function ws_task_time_or_null($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (preg_match('/^([0-9]{1,2}):([0-9]{2})(?::([0-9]{2}))?$/', $value, $match)
        && ((int) $match[1] <= 23) && ((int) $match[2] <= 59)) {
        return sprintf('%02d:%02d:00', $match[1], $match[2]);
    }

    return false;
}

/**
 * How long before the due time the reminder goes, as the caller sent it:
 * minutes, or empty for no reminder.
 *
 * @param mixed $value
 * @return int|null|false false when it is not a number the reminder can take
 */
function ws_task_remind_minutes_or_null($value)
{
    if (($value === null) || (trim((string) $value) === '')) {
        return null;
    }

    $value = trim((string) $value);

    if (!preg_match('/^[0-9]+$/', $value) || ((int) $value > WS_TASK_REMIND_MAX)) {
        return false;
    }

    return (int) $value;
}

/**
 * The fields of a task being written that belong to the reminder, checked:
 * what is not in $data is not touched. Called by ws_task_validate().
 *
 * @param array      $data
 * @param array|null $current the task as it is, for an update
 * @param string|null $due    the due date the task will have
 * @return array ok, error, field, clean
 */
function ws_task_reminder_validate($data, $current, $due)
{
    $clean = array();

    if (!ws_task_reminders_ready()) {
        return array('ok' => true, 'error' => '', 'field' => '', 'clean' => $clean);
    }

    if (array_key_exists('due_time', $data)) {
        $time = ws_task_time_or_null($data['due_time']);

        if ($time === false) {
            return array('ok' => false, 'error' => lang('Write the time as hour:minute.'), 'field' => 'due_time', 'clean' => array());
        }

        $clean['due_time'] = $time;
    }

    if (array_key_exists('remind_minutes', $data)) {
        $minutes = ws_task_remind_minutes_or_null($data['remind_minutes']);

        if ($minutes === false) {
            return array('ok' => false, 'error' => lang('A reminder can be set up to 60 days before the task is due.'), 'field' => 'remind_minutes', 'clean' => array());
        }

        if (($minutes !== null) && ($due === null)) {
            return array('ok' => false, 'error' => lang('A reminder needs a due date.'), 'field' => 'remind_minutes', 'clean' => array());
        }

        $clean['remind_minutes'] = $minutes;
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'clean' => $clean);
}

/**
 * The SET parts of an UPDATE for the reminder fields that were checked.
 *
 * @param array $clean
 * @return string[]
 */
function ws_task_reminder_set($clean)
{
    $set = array();

    if (array_key_exists('due_time', $clean)) {
        $set[] = 'due_time = ' . (($clean['due_time'] === null) ? 'NULL' : "'" . e($clean['due_time']) . "'");
    }

    if (array_key_exists('remind_minutes', $clean)) {
        $set[] = 'remind_minutes = ' . (($clean['remind_minutes'] === null) ? 'NULL' : "'" . (int) $clean['remind_minutes'] . "'");
    }

    return $set;
}

/**
 * When a task is due, as a moment: its due date at its time, or at
 * WS_TASK_REMIND_DAY_TIME when it has none. 0 without a due date.
 *
 * @param array $task
 * @return int
 */
function ws_task_due_at($task)
{
    $date = (string) ($task['due_date'] ?? '');

    if (($date === '') || ($date === '0000-00-00')) {
        return 0;
    }

    $time = (string) ($task['due_time'] ?? '');
    $time = ($time !== '') ? substr($time, 0, 5) : WS_TASK_REMIND_DAY_TIME;
    $at = strtotime($date . ' ' . $time . ':00');

    return ($at === false) ? 0 : (int) $at;
}

/**
 * When the task's reminder is to go, 0 for none.
 *
 * @param array $task
 * @return int
 */
function ws_task_remind_target($task)
{
    if (!array_key_exists('remind_minutes', $task) || ($task['remind_minutes'] === null) || ($task['remind_minutes'] === '')) {
        return 0;
    }

    $due = ws_task_due_at($task);

    if ($due <= 0) {
        return 0;
    }

    return max(1, $due - ((int) $task['remind_minutes'] * 60));
}

/**
 * Works out again when the task's reminder goes, after anything it depends
 * on may have changed. The same moment leaves it as it is (a reminder that
 * went does not go again); another one arms it; a task already due has
 * nothing left to remind of.
 *
 * @param int $task_id
 * @return void
 */
function ws_task_reminder_sync($task_id)
{
    if (!ws_task_reminders_ready()) {
        return;
    }

    $task = db_item("SELECT id, due_date, due_time, remind_minutes, remind_at FROM ws_tasks WHERE id = '" . (int) $task_id . "'");

    if (!is_array($task)) {
        return;
    }

    $target = ws_task_remind_target($task);

    if (($target > 0) && (ws_task_due_at($task) <= time())) {
        $target = 0;
    }

    if ($target !== (int) $task['remind_at']) {
        db("UPDATE ws_tasks SET remind_at = '" . $target . "', reminded_at = 0 WHERE id = '" . (int) $task['id'] . "'");
    }
}

/**
 * "30 minutes before", "2 hours before", "1 day before", "At the due time".
 *
 * @param int $minutes
 * @return string
 */
function ws_task_reminder_label($minutes)
{
    $minutes = (int) $minutes;

    if ($minutes <= 0) {
        return lang('At the due time');
    }

    if (($minutes % 1440) === 0) {
        return lang(array('string' => '{var:1} day(s) before', 'vars' => (string) ($minutes / 1440)));
    }

    if (($minutes % 60) === 0) {
        return lang(array('string' => '{var:1} hour(s) before', 'vars' => (string) ($minutes / 60)));
    }

    return lang(array('string' => '{var:1} minute(s) before', 'vars' => (string) $minutes));
}

/**
 * What the task drawer is told about the reminder.
 *
 * @param array $task
 * @return array due_time (HH:MM | null), remind_minutes (int | null),
 *               remind_label, remind_at (unix, 0 for none), remind_at_label,
 *               reminded (the mail went), remind_each (a repeating task's
 *               copies are reminded too)
 */
function ws_task_reminder_detail($task)
{
    if (!ws_task_reminders_ready()) {
        return array();
    }

    $time = (string) ($task['due_time'] ?? '');
    $minutes = (($task['remind_minutes'] ?? null) === null) ? null : (int) $task['remind_minutes'];
    $at = (int) ($task['remind_at'] ?? 0);
    $sent = (int) ($task['reminded_at'] ?? 0);
    $each = false;

    if ((int) ($task['recurrence_id'] ?? 0) > 0) {
        $each = (bool) db_value("SELECT remind_each FROM ws_task_recurrences WHERE id = '" . (int) $task['recurrence_id'] . "'");
    }

    return array(
        'due_time'        => ($time !== '') ? substr($time, 0, 5) : null,
        'remind_minutes'  => $minutes,
        'remind_label'    => ($minutes !== null) ? ws_task_reminder_label($minutes) : '',
        'remind_at'       => $at,
        'remind_at_label' => ($at > 0) ? date('d.m.Y H:i', $at) : '',
        'reminded'        => ($sent > 0) && ($at > 0),
        'reminded_label'  => ($sent > 0) ? date('d.m.Y H:i', $sent) : '',
        'remind_each'     => $each,
    );
}

/**
 * Says whether every copy of a repeating task is reminded. Called once the
 * task and its repeat are saved.
 *
 * @param int  $task_id
 * @param bool $each
 * @return void
 */
function ws_task_reminder_series_set($task_id, $each)
{
    if (!ws_task_reminders_ready()) {
        return;
    }

    $series_id = (int) db_value("SELECT recurrence_id FROM ws_tasks WHERE id = '" . (int) $task_id . "'");

    if ($series_id > 0) {
        db("UPDATE ws_task_recurrences SET remind_each = '" . ($each ? 1 : 0) . "' WHERE id = '" . $series_id . "'");
    }
}

/**
 * A new copy of a repeating task takes the newest copy's time, and its
 * reminder when the series reminds every copy. Called by
 * ws_recurrence_spawn().
 *
 * @param array $series
 * @param array $template the copy the new one was made from
 * @param int   $task_id  the new copy
 * @return void
 */
function ws_task_reminder_copy($series, $template, $task_id)
{
    if (!ws_task_reminders_ready()) {
        return;
    }

    $time = (string) ($template['due_time'] ?? '');
    $minutes = (($template['remind_minutes'] ?? null) === null) ? null : (int) $template['remind_minutes'];

    if (empty($series['remind_each'])) {
        $minutes = null;
    }

    db("UPDATE ws_tasks SET
            due_time = " . (($time !== '') ? "'" . e($time) . "'" : 'NULL') . ",
            remind_minutes = " . (($minutes !== null) ? "'" . $minutes . "'" : 'NULL') . "
        WHERE id = '" . (int) $task_id . "'");

    ws_task_reminder_sync($task_id);
}

/**
 * Who a task's reminder goes to: the people on it who are still on the team
 * and have an address, or the person who made it when nobody is on it.
 *
 * @param array $task
 * @return array[] user_id, email
 */
function ws_task_reminder_people($task)
{
    $ids = array();

    foreach (ws_task_assignee_ids($task['id']) as $user_id) {
        if (ws_is_team_member($user_id)) {
            $ids[(int) $user_id] = (int) $user_id;
        }
    }

    if (empty($ids) && ws_is_team_member((int) $task['creator_id'])) {
        $ids[(int) $task['creator_id']] = (int) $task['creator_id'];
    }

    if (empty($ids)) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT user_id, user_email FROM user WHERE user_id IN (" . implode(',', $ids) . ")") as $row) {
        $email = trim((string) $row['user_email']);

        if (($email !== '') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out[] = array('user_id' => (int) $row['user_id'], 'email' => $email);
        }
    }

    return $out;
}

/**
 * The mail of one reminder: subject and plain text.
 *
 * @param array $task
 * @return array subject, body
 */
function ws_task_reminder_mail($task)
{
    $title = (string) $task['title'];
    $due = ws_task_due_at($task);
    $when = date('d.m.Y', $due) . (((string) ($task['due_time'] ?? '') !== '') ? ' ' . date('H:i', $due) : '');

    $lines = array(
        $title,
        ws_task_number($task['id']) . ' · ' . lang(array('string' => 'Due: {var:1}', 'vars' => $when)),
    );

    if ((int) $task['channel_id'] > 0) {
        $channel = ws_channel($task['channel_id']);

        if ($channel) {
            $lines[] = lang(array('string' => 'Channel: {var:1}', 'vars' => '#' . $channel['name']));
        }
    }

    if (in_array((string) $task['priority'], array('high', 'urgent'), true)) {
        $lines[] = lang(array('string' => 'Priority: {var:1}', 'vars' => ws_task_priorities()[$task['priority']]));
    }

    $lines[] = '';
    $lines[] = lang('Open the task:') . ' ' . URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace_tasks.php?task=' . (int) $task['id'];
    $lines[] = '';
    $lines[] = '--';
    $lines[] = lang('You are receiving this because a reminder was set on a task you are on in the workspace.');

    return array(
        'subject' => lang(array('string' => 'Reminder: {var:1}', 'vars' => mb_substr($title, 0, 150))),
        'body'    => implode("\n", $lines),
    );
}

/**
 * Sends the reminders whose time has come.
 *
 * @param int $limit
 * @return array sent, skipped, failed
 */
function ws_task_reminders_run($limit = WS_TASK_REMIND_BATCH)
{
    $result = array('sent' => 0, 'skipped' => 0, 'failed' => 0);

    if (!ws_task_reminders_ready() || !ws_enabled()) {
        return $result;
    }

    $now = time();

    foreach ((array) db_values("SELECT id FROM ws_tasks
        WHERE reminded_at = 0 AND remind_at > 0 AND remind_at <= '" . $now . "'
        ORDER BY remind_at LIMIT " . max(1, (int) $limit)) as $task_id) {

        // Claimed first: a second run that read the same row finds it taken.
        db("UPDATE ws_tasks SET reminded_at = '" . $now . "' WHERE id = '" . (int) $task_id . "' AND reminded_at = 0");

        if (mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        $task = ws_task($task_id);

        // Finished, or due already: there is nothing left to remind of.
        if (!$task || !ws_task_is_open((string) $task['status']) || (ws_task_due_at($task) <= $now)) {
            $result['skipped']++;
            continue;
        }

        $people = ws_task_reminder_people($task);

        if (empty($people)) {
            $result['skipped']++;
            continue;
        }

        $mail = ws_task_reminder_mail($task);
        $sent = 0;

        foreach ($people as $person) {
            $ok = email(array(
                'to'                 => $person['email'],
                'from_name'          => defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : '',
                'from_email_address' => defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : '',
                'subject'            => $mail['subject'],
                'format'             => 'plain_text',
                'body'               => $mail['body'],
            ));

            if ($ok !== false) {
                $sent++;
            }
        }

        if ($sent > 0) {
            $result['sent']++;
        } else {
            $result['failed']++;
            log_activity(lang(array('string' => 'The e-mail reminder of task {var:1} could not be sent.', 'vars' => ws_task_number($task['id']))));
        }
    }

    return $result;
}

/**
 * What the task drawer's script is handed.
 *
 * @return array
 */
function ws_task_reminders_js_config()
{
    return array(
        'ready'    => ws_task_reminders_ready(),
        'day_time' => WS_TASK_REMIND_DAY_TIME,
        'max'      => WS_TASK_REMIND_MAX,
    );
}

/**
 * The texts of the reminder box in the task drawer.
 *
 * @return array key => text
 */
function ws_task_reminders_js_strings()
{
    return array(
        'remind_head'         => lang('Time and e-mail reminder'),
        'remind_time'         => lang('Due time'),
        'remind_before'       => lang('Remind by e-mail'),
        'remind_unit_minutes' => lang('minutes before'),
        'remind_unit_hours'   => lang('hours before'),
        'remind_unit_days'    => lang('days before'),
        'remind_each'         => lang('Remind on every repeat'),
        'remind_none'         => lang('Leave the number empty for no reminder.'),
        'remind_needs_due'    => lang('A reminder needs a due date.'),
        'remind_goes_at'      => ws_js_template('The e-mail goes to the people on the task on {var:1}.', 1),
        'remind_goes_now'     => lang('The e-mail goes to the people on the task within a few minutes.'),
        'remind_past'         => lang('The task is already due: no e-mail will go.'),
        'remind_day_time'     => ws_js_template('No time is set, so {var:1} on the due date is taken.', 1),
        'remind_sent'         => ws_js_template('The reminder was e-mailed on {var:1}.', 1),
    );
}
