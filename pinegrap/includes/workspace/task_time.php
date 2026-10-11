<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the time spent on a task. The estimate says how long a task
 * should take; these rows say how long it did: a timer started and stopped
 * in the task drawer, or minutes written in by hand, each against a person,
 * a day and an optional note.
 *
 * A running timer is a row with started_at set and no minutes yet. A person
 * has one running timer at most: starting another stops and keeps the first.
 * Stopping counts the minutes from started_at on the server, so a closed tab
 * or a sleeping laptop does not lose or invent time; anything shorter than a
 * minute is kept as a minute.
 *
 * The channel's Summary tab adds up the time on the channel's tasks, per
 * person, this month and unbilled. Where the channel's customer is an ERP
 * current account, someone with the ERP's invoice right turns the unbilled
 * billable rows into a draft invoice - one line per task, the hours as the
 * quantity - and the rows remember the draft (invoice_id), so the same hour
 * is not billed twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most minutes one row may hold, as for an estimate. */
if (!defined('WS_TASK_TIME_MAX')) {
    define('WS_TASK_TIME_MAX', 100000);
}

/**
 * Has the database the time rows (2026.4.8, 8.86)?
 *
 * @return bool
 */
function ws_task_time_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_task_time', 'invoice_id')
            && waf_table_has_column('ws_task_time', 'started_at');
    }

    return $ready;
}

// ─── Pure helpers ───────────────────────────────────────────────────────

/**
 * Minutes written by hand: "90" (minutes), "1:30", "1s 30d", "1sa 30dk",
 * "1h 30m", "2s", "45dk", "1,5s", with or without a leading ~. The unit words
 * are the estimate's (ws_parse_estimate()), except that here "d" is minutes:
 * time spent is not written in days.
 *
 * @param string $text
 * @return int 0 when it cannot be read
 */
function ws_task_time_parse($text)
{
    $text = trim(mb_strtolower((string) $text, 'UTF-8'));
    $text = ltrim($text, '~ ');

    if ($text === '') {
        return 0;
    }

    if (preg_match('/^[0-9]+$/', $text)) {
        return (int) $text;
    }

    if (preg_match('/^([0-9]+):([0-5][0-9])$/', $text, $match)) {
        return ((int) $match[1] * 60) + (int) $match[2];
    }

    $units = array(
        'dk' => 1, 'dakika' => 1, 'd' => 1, 'm' => 1, 'min' => 1,
        'sa' => 60, 'saat' => 60, 's' => 60, 'h' => 60, 'hr' => 60,
    );

    if (!preg_match_all('/([0-9]+(?:[.,][0-9]+)?)\s*([a-zçğıöşü]+)/u', $text, $parts, PREG_SET_ORDER)) {
        return 0;
    }

    // Every character has to belong to a part: "1s abc" is not 60 minutes.
    $rest = trim(preg_replace('/([0-9]+(?:[.,][0-9]+)?)\s*([a-zçğıöşü]+)/u', '', $text));

    if ($rest !== '') {
        return 0;
    }

    $minutes = 0.0;

    foreach ($parts as $part) {
        if (!isset($units[$part[2]])) {
            return 0;
        }

        $minutes += ((float) str_replace(',', '.', $part[1])) * $units[$part[2]];
    }

    return (int) round($minutes);
}

/**
 * The minutes a timer counted, a minute at the least.
 *
 * @param int $started_at
 * @param int $now
 * @return int
 */
function ws_task_time_elapsed($started_at, $now)
{
    return max(1, min(WS_TASK_TIME_MAX, (int) round(max(0, (int) $now - (int) $started_at) / 60)));
}

/**
 * Minutes as hours for an invoice line: two decimals, rounded.
 *
 * @param int $minutes
 * @return float
 */
function ws_task_time_hours($minutes)
{
    return round(max(0, (int) $minutes) / 60, 2);
}

/**
 * How the time spent stands against the estimate.
 *
 * @param int $logged
 * @param int $estimate
 * @return array|null percent (of the estimate, capped at 100 for the bar),
 *                    over (more than estimated); null without an estimate
 */
function ws_task_time_share($logged, $estimate)
{
    $logged = max(0, (int) $logged);
    $estimate = (int) $estimate;

    if ($estimate <= 0) {
        return null;
    }

    return array(
        'percent' => min(100, (int) floor(($logged * 100) / $estimate)),
        'over'    => ($logged > $estimate),
    );
}

/**
 * May this person write time on the task, for themselves or (a member of
 * staff only) for somebody else? Whoever is on the task or created it may
 * write their own; staff may write anybody's. Seeing the task is asked
 * before (ws_can_see_task()).
 *
 * @param array $viewer
 * @param array $task
 * @param int[] $assignees
 * @param int   $user_id whose time; 0 for the viewer's own
 * @return bool
 */
function ws_task_time_may_log($viewer, $task, $assignees, $user_id = 0)
{
    if (empty($viewer['member']) || !is_array($task)) {
        return false;
    }

    $me = (int) $viewer['id'];
    $user_id = ((int) $user_id > 0) ? (int) $user_id : $me;
    $staff = ((int) $viewer['role'] < 3);

    if ($user_id !== $me) {
        return $staff;
    }

    return $staff || ((int) $task['creator_id'] === $me) || in_array($me, array_map('intval', (array) $assignees), true);
}

// ─── Reading ────────────────────────────────────────────────────────────

/**
 * Staff, who write time for others, delete anybody's row and undo an
 * invoice tie.
 *
 * @param array $viewer
 * @return bool
 */
function ws_task_time_is_manager($viewer)
{
    return !empty($viewer['member']) && ((int) $viewer['role'] < 3);
}

/**
 * One time row.
 *
 * @param int $id
 * @return array|null
 */
function ws_task_time_entry($id)
{
    $row = db_item("SELECT * FROM ws_task_time WHERE id = '" . (int) $id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The timer a person has running, wherever it is.
 *
 * @param int $user_id
 * @return array|null
 */
function ws_task_time_running($user_id)
{
    $row = db_item("SELECT * FROM ws_task_time WHERE user_id = '" . (int) $user_id . "' AND minutes = 0 AND started_at > 0 ORDER BY id DESC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The minutes spent on a set of tasks, in one query.
 *
 * @param int[] $task_ids
 * @return array task id => minutes
 */
function ws_task_time_totals($task_ids)
{
    $ids = array();

    foreach ((array) $task_ids as $task_id) {
        if ((int) $task_id > 0) {
            $ids[(int) $task_id] = (int) $task_id;
        }
    }

    $out = array();

    if (empty($ids) || !ws_task_time_ready()) {
        return $out;
    }

    foreach ((array) db_items("SELECT task_id, SUM(minutes) AS minutes FROM ws_task_time
        WHERE task_id IN (" . implode(',', $ids) . ") GROUP BY task_id") as $row) {
        $out[(int) $row['task_id']] = (int) $row['minutes'];
    }

    return $out;
}

/**
 * Minutes as people read them on the planning screens ("1 h 30 min").
 *
 * @param int $minutes
 * @return string
 */
function ws_task_time_label($minutes)
{
    return ws_minutes_label($minutes);
}

/**
 * One row for the drawer and the API.
 *
 * @param array $viewer
 * @param array $row
 * @param array $people id => person (ws_people())
 * @return array
 */
function ws_task_time_present($viewer, $row, $people = array())
{
    $person = $people[(int) $row['user_id']] ?? (ws_people(array($row['user_id']))[(int) $row['user_id']] ?? null);
    $running = ((int) $row['minutes'] === 0) && ((int) $row['started_at'] > 0);
    $mine = ((int) $row['user_id'] === (int) $viewer['id']);

    return array(
        'id'         => (int) $row['id'],
        'task_id'    => (int) $row['task_id'],
        'user_id'    => (int) $row['user_id'],
        'person'     => $person,
        'minutes'    => (int) $row['minutes'],
        'label'      => $running ? lang('Running') : ws_task_time_label($row['minutes']),
        'worked_on'  => (string) $row['worked_on'],
        'day_label'  => ws_day_label((string) $row['worked_on']),
        'note'       => (string) $row['note'],
        'billable'   => ((int) $row['billable'] === 1),
        'invoice_id' => (int) $row['invoice_id'],
        'running'    => $running,
        'started_at' => (int) $row['started_at'],
        'can_delete' => ((int) $row['invoice_id'] === 0) && ($mine || ws_task_time_is_manager($viewer)),
    );
}

/**
 * The Time part of the task drawer.
 *
 * @param array $viewer
 * @param array $task
 * @param int[] $assignees
 * @return array|null null when the schema is not there
 */
function ws_task_time_detail($viewer, $task, $assignees)
{
    if (!ws_task_time_ready()) {
        return null;
    }

    $rows = (array) db_items("SELECT * FROM ws_task_time WHERE task_id = '" . (int) $task['id'] . "' ORDER BY worked_on DESC, id DESC LIMIT 200");
    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['user_id'];
    }

    $people = ws_people($ids);
    $entries = array();
    $total = 0;

    foreach ($rows as $row) {
        $entries[] = ws_task_time_present($viewer, $row, $people);
        $total += (int) $row['minutes'];
    }

    $running = ws_task_time_running($viewer['id']);
    $manager = ws_task_time_is_manager($viewer);

    return array(
        'entries'     => $entries,
        'total'       => $total,
        'total_label' => ws_task_time_label($total),
        'estimate'    => (int) $task['estimate_minutes'],
        'compare'     => ((int) $task['estimate_minutes'] > 0)
            ? lang(array('string' => '{var:1} spent of {var:2}', 'vars' => array(ws_task_time_label($total), ws_task_time_label($task['estimate_minutes']))))
            : ws_task_time_label($total),
        'share'       => ws_task_time_share($total, $task['estimate_minutes']),
        'can_log'     => ws_task_time_may_log($viewer, $task, $assignees),
        'for_others'  => $manager,
        // The timer of the reader: running here, or on another task.
        'timer'       => $running ? array(
            'id'         => (int) $running['id'],
            'task_id'    => (int) $running['task_id'],
            'here'       => ((int) $running['task_id'] === (int) $task['id']),
            'started_at' => (int) $running['started_at'],
            'number'     => ws_task_number($running['task_id']),
        ) : null,
        'now'         => time(),
        'today'       => date('Y-m-d'),
    );
}

// ─── Writing ────────────────────────────────────────────────────────────

/**
 * Stops a running timer and keeps what it counted.
 *
 * @param array $row
 * @return int the minutes kept
 */
function ws_task_time_close($row)
{
    $minutes = ws_task_time_elapsed($row['started_at'], time());

    db("UPDATE ws_task_time SET minutes = '" . $minutes . "'
        WHERE id = '" . (int) $row['id'] . "' AND minutes = 0");

    if (mysqli_affected_rows(db::$con) > 0) {
        $task = ws_task($row['task_id']);

        if ($task) {
            ws_task_time_announce($task, ws_task_time_entry($row['id']));
        }
    }

    return $minutes;
}

/**
 * Starts the person's timer on a task. One that runs elsewhere is stopped
 * and kept first.
 *
 * @param array $viewer
 * @param array $task
 * @return array ok, error, id, stopped (the task number of the timer stopped, or '')
 */
function ws_task_time_start($viewer, $task)
{
    if (!ws_task_time_ready() || !ws_task_time_may_log($viewer, $task, ws_task_assignee_ids($task['id']))) {
        return array('ok' => false, 'error' => lang('Only the people on a task, the one who created it and staff can write time on it.'), 'id' => 0, 'stopped' => '');
    }

    $running = ws_task_time_running($viewer['id']);
    $stopped = '';

    if ($running) {
        if ((int) $running['task_id'] === (int) $task['id']) {
            return array('ok' => true, 'error' => '', 'id' => (int) $running['id'], 'stopped' => '');
        }

        ws_task_time_close($running);
        $stopped = ws_task_number($running['task_id']);
    }

    $now = time();

    db("INSERT INTO ws_task_time (task_id, user_id, started_at, minutes, worked_on, note, billable, invoice_id, created_at)
        VALUES ('" . (int) $task['id'] . "', '" . (int) $viewer['id'] . "', '" . $now . "', 0, '" . e(date('Y-m-d', $now)) . "', '', 1, 0, '" . $now . "')");

    $id = (int) mysqli_insert_id(db::$con);

    if ($id <= 0) {
        return array('ok' => false, 'error' => lang('The time could not be saved.'), 'id' => 0, 'stopped' => $stopped);
    }

    return array('ok' => true, 'error' => '', 'id' => $id, 'stopped' => $stopped);
}

/**
 * Stops the person's running timer, wherever it runs.
 *
 * @param array $viewer
 * @return array ok, error, minutes, task_id
 */
function ws_task_time_stop($viewer)
{
    $running = ws_task_time_ready() ? ws_task_time_running($viewer['id']) : null;

    if (!$running) {
        return array('ok' => false, 'error' => lang('No timer of yours is running.'), 'minutes' => 0, 'task_id' => 0);
    }

    return array('ok' => true, 'error' => '', 'minutes' => ws_task_time_close($running), 'task_id' => (int) $running['task_id']);
}

/**
 * Writes time spent by hand.
 *
 * @param array $viewer
 * @param array $task
 * @param array $data minutes (int) or text ("1s 30d"), worked_on, note, billable, user_id
 * @return array ok, error, field, id
 */
function ws_task_time_add($viewer, $task, $data)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'id' => 0);
    };

    if (!ws_task_time_ready()) {
        return $fail(lang('The database has to be updated first.'));
    }

    $user_id = ((int) ($data['user_id'] ?? 0) > 0) ? (int) $data['user_id'] : (int) $viewer['id'];

    if (!ws_task_time_may_log($viewer, $task, ws_task_assignee_ids($task['id']), $user_id)) {
        return $fail(($user_id === (int) $viewer['id'])
            ? lang('Only the people on a task, the one who created it and staff can write time on it.')
            : lang('Only staff can write time for somebody else.'), 'user_id');
    }

    if (($user_id !== (int) $viewer['id']) && !ws_is_team_member($user_id)) {
        return $fail(lang('Time can only be written for members of the team.'), 'user_id');
    }

    // A number of minutes (the API), or what was typed ("1s 30d").
    $minutes = (isset($data['minutes']) && preg_match('/^[0-9]+$/', (string) $data['minutes']))
        ? (int) $data['minutes']
        : ws_task_time_parse((string) ($data['text'] ?? ($data['minutes'] ?? '')));

    if ($minutes < 1) {
        return $fail(lang('Write the time as minutes or as 1s 30d.'), 'minutes');
    }

    if ($minutes > WS_TASK_TIME_MAX) {
        return $fail(lang('That is more time than one entry can hold.'), 'minutes');
    }

    $day = ws_date_or_null($data['worked_on'] ?? '');

    if ($day === false) {
        return $fail(lang('Write the date as day.month.year.'), 'worked_on');
    }

    $day = $day ?: date('Y-m-d');

    if ($day > date('Y-m-d')) {
        return $fail(lang('Time cannot be written for a day that has not come yet.'), 'worked_on');
    }

    $note = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($data['note'] ?? ''))), 0, 255);
    $billable = array_key_exists('billable', $data) ? (!empty($data['billable']) ? 1 : 0) : 1;

    db("INSERT INTO ws_task_time (task_id, user_id, started_at, minutes, worked_on, note, billable, invoice_id, created_at)
        VALUES ('" . (int) $task['id'] . "', '" . $user_id . "', 0, '" . $minutes . "', '" . e($day) . "', '" . e($note) . "', '" . $billable . "', 0, '" . time() . "')");

    $id = (int) mysqli_insert_id(db::$con);

    if ($id <= 0) {
        return $fail(lang('The time could not be saved.'));
    }

    ws_task_time_announce($task, ws_task_time_entry($id));

    return array('ok' => true, 'error' => '', 'field' => '', 'id' => $id);
}

/**
 * Deletes a time row: one's own, or anybody's for staff. A row on an invoice
 * stays until the tie is undone.
 *
 * @param array $viewer
 * @param array $entry
 * @return array ok, error
 */
function ws_task_time_delete($viewer, $entry)
{
    if (((int) $entry['user_id'] !== (int) $viewer['id']) && !ws_task_time_is_manager($viewer)) {
        return array('ok' => false, 'error' => lang('Only the one who wrote the time, or staff, can delete it.'));
    }

    if ((int) $entry['invoice_id'] > 0) {
        return array('ok' => false, 'error' => lang('This time is on an invoice draft. Undo the tie on the channel\'s Summary tab first.'));
    }

    db("DELETE FROM ws_task_time WHERE id = '" . (int) $entry['id'] . "' AND invoice_id = 0");

    return array('ok' => true, 'error' => '');
}

/**
 * Tells the API's subscribers about time that was written.
 *
 * @param array $task
 * @param array $entry
 */
function ws_task_time_announce($task, $entry)
{
    if (!is_array($task) || !is_array($entry) || ((int) $entry['minutes'] <= 0)) {
        return;
    }

    pg_announce('workspace.task.time_logged', array(
        'id'         => (int) $entry['id'],
        'task_id'    => (int) $task['id'],
        'channel_id' => (int) $task['channel_id'],
        'user_id'    => (int) $entry['user_id'],
        'minutes'    => (int) $entry['minutes'],
        'worked_on'  => (string) $entry['worked_on'],
        'billable'   => ((int) $entry['billable'] === 1),
        'timer'      => ((int) $entry['started_at'] > 0),
    ));
}

// ─── The channel's card ─────────────────────────────────────────────────

/**
 * May this person turn the channel's time into an ERP invoice draft? The
 * ERP is on, the channel's customer is a current account, and the person
 * holds the ERP's invoice right - staff, or manage_erp without the
 * accountant's read-only right - and may write in the channel, where the
 * decision line goes.
 *
 * @param array $viewer
 * @param array $user    validate_user()
 * @param array $channel
 * @return bool
 */
function ws_task_time_can_invoice($viewer, $user, $channel)
{
    if (!defined('ERP_ENABLED') || !ERP_ENABLED || !ws_task_time_ready() || !is_array($channel)) {
        return false;
    }

    list($type, $id) = ws_channel_customer_of($channel);

    if (($type !== 'erp_account') || ($id <= 0)) {
        return false;
    }

    $staff = ((int) $viewer['role'] < 3);

    if (!$staff && (empty($user['manage_erp']) || !empty($user['manage_erp_readonly']))) {
        return false;
    }

    return ws_can_post_channel($viewer, $channel);
}

/**
 * What the channel's Summary tab shows about the time spent on its tasks.
 *
 * @param array $viewer
 * @param array $user    validate_user()
 * @param array $channel
 * @return array|null null when the schema is not there
 */
function ws_task_time_channel($viewer, $user, $channel)
{
    if (!ws_task_time_ready()) {
        return null;
    }

    $channel_id = (int) $channel['id'];
    $from = "FROM ws_task_time tt INNER JOIN ws_tasks t ON t.id = tt.task_id AND t.channel_id = '" . $channel_id . "' WHERE tt.minutes > 0";

    $sums = db_item("SELECT COALESCE(SUM(tt.minutes), 0) AS total,
            COALESCE(SUM(IF(tt.worked_on >= '" . e(date('Y-m-01')) . "', tt.minutes, 0)), 0) AS month,
            COALESCE(SUM(IF(tt.billable = 1 AND tt.invoice_id = 0, tt.minutes, 0)), 0) AS unbilled,
            COUNT(*) AS entries " . $from);

    $by_person = array();
    $rows = (array) db_items("SELECT tt.user_id, SUM(tt.minutes) AS minutes " . $from . " GROUP BY tt.user_id ORDER BY minutes DESC");
    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['user_id'];
    }

    $people = ws_people($ids);

    foreach ($rows as $row) {
        $by_person[] = array(
            'person'  => $people[(int) $row['user_id']] ?? null,
            'minutes' => (int) $row['minutes'],
            'label'   => ws_task_time_label($row['minutes']),
        );
    }

    // The drafts the rows went on, for the tie to be undone.
    $invoices = array();

    foreach ((array) db_items("SELECT tt.invoice_id, COUNT(*) AS entries, SUM(tt.minutes) AS minutes " . $from . " AND tt.invoice_id > 0 GROUP BY tt.invoice_id ORDER BY tt.invoice_id DESC LIMIT 20") as $row) {
        $invoice = db_item("SELECT id, status, full_number FROM erp_invoices WHERE id = '" . (int) $row['invoice_id'] . "'");

        if (!is_array($invoice)) {
            $state = lang('Deleted');
        } elseif ((string) $invoice['status'] === 'draft') {
            $state = lang('Draft');
        } else {
            $state = (string) $invoice['full_number'];
        }

        $invoices[] = array(
            'id'         => (int) $row['invoice_id'],
            'label'      => lang(array('string' => 'Invoice #{var:1} · {var:2}', 'vars' => array((int) $row['invoice_id'], $state))),
            'entries'    => (int) $row['entries'],
            'minutes'    => (int) $row['minutes'],
            'time_label' => ws_task_time_label($row['minutes']),
            'url'        => (is_array($invoice) && ((string) $invoice['status'] === 'draft') && !empty($viewer['erp']))
                ? OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . (int) $row['invoice_id'] : '',
            'can_unlink' => ws_task_time_is_manager($viewer) && ws_task_time_unlinkable($invoice),
        );
    }

    $total = (int) ($sums['total'] ?? 0);

    return array(
        'total'          => $total,
        'total_label'    => ws_task_time_label($total),
        'month'          => (int) ($sums['month'] ?? 0),
        'month_label'    => ws_task_time_label($sums['month'] ?? 0),
        'unbilled'       => (int) ($sums['unbilled'] ?? 0),
        'unbilled_label' => ws_task_time_label($sums['unbilled'] ?? 0),
        'entries'        => (int) ($sums['entries'] ?? 0),
        'people'         => $by_person,
        'invoices'       => $invoices,
        'can_invoice'    => ws_task_time_can_invoice($viewer, $user, $channel),
        'today'          => date('Y-m-d'),
        'month_start'    => date('Y-m-01'),
    );
}

/**
 * May the tie of the rows to this invoice be undone? While it is a draft,
 * or gone, or cancelled; an issued invoice keeps its hours, or they would be
 * billed again.
 *
 * @param array|null $invoice erp_invoices row
 * @return bool
 */
function ws_task_time_unlinkable($invoice)
{
    return !is_array($invoice) || in_array((string) $invoice['status'], array('draft', 'cancelled'), true);
}

/**
 * Turns the channel's unbilled billable time between two days into an ERP
 * invoice draft: one line per task (its number and title, the hours rounded
 * to two decimals, the hourly rate typed in), the VAT rate typed in. The rows
 * are locked while it is written and take the draft's id in the same
 * transaction; a refused draft leaves them as they were. The channel gets a
 * locked decision line.
 *
 * @param array $viewer
 * @param array $user    validate_user()
 * @param array $channel
 * @param array $data    from, to (Y-m-d), rate (typed money), tax_rate
 * @return array ok, error, field, invoice_id, entries, minutes
 */
function ws_task_time_invoice($viewer, $user, $channel, $data)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'invoice_id' => 0, 'entries' => 0, 'minutes' => 0);
    };

    if (!ws_task_time_can_invoice($viewer, $user, $channel)) {
        return $fail(lang('An invoice draft needs the ERP, a current account as the channel\'s customer and the right to write invoices.'));
    }

    if (!function_exists('erp_invoice_draft_save')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    }

    list(, $account_id) = ws_channel_customer_of($channel);

    $from = ws_date_or_null($data['from'] ?? '');
    $to = ws_date_or_null($data['to'] ?? '');

    if (($from === false) || ($to === false)) {
        return $fail(lang('Write the date as day.month.year.'), ($from === false) ? 'from' : 'to');
    }

    $from = $from ?: '0000-00-00';
    $to = $to ?: date('Y-m-d');

    if ($from > $to) {
        return $fail(lang('The first day cannot be after the last.'), 'from');
    }

    $rate = erp_kurus((string) ($data['rate'] ?? ''));

    if ($rate <= 0) {
        return $fail(lang('Write the hourly rate.'), 'rate');
    }

    $tax_rate = (float) str_replace(',', '.', (string) ($data['tax_rate'] ?? '0'));

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    // Locked until the draft's id is on them: a second request for the same
    // rows waits here, then finds them taken.
    $rows = (array) db_items("SELECT tt.id, tt.task_id, tt.minutes FROM ws_task_time tt
        INNER JOIN ws_tasks t ON t.id = tt.task_id AND t.channel_id = '" . (int) $channel['id'] . "'
        WHERE tt.minutes > 0 AND tt.billable = 1 AND tt.invoice_id = 0
        AND tt.worked_on >= '" . e($from) . "' AND tt.worked_on <= '" . e($to) . "'
        ORDER BY tt.task_id, tt.id
        FOR UPDATE");

    if (empty($rows)) {
        erp_tx_rollback();

        return $fail(lang('There is no unbilled billable time on the channel\'s tasks in those days.'), 'from');
    }

    $per_task = array();
    $entry_ids = array();
    $minutes = 0;

    foreach ($rows as $row) {
        $per_task[(int) $row['task_id']] = ($per_task[(int) $row['task_id']] ?? 0) + (int) $row['minutes'];
        $entry_ids[] = (int) $row['id'];
        $minutes += (int) $row['minutes'];
    }

    $lines = array();

    foreach ($per_task as $task_id => $task_minutes) {
        $task = ws_task($task_id);

        $lines[] = array(
            'description' => mb_substr(ws_task_number($task_id) . ' · ' . ($task ? $task['title'] : ''), 0, 255),
            'quantity'    => ws_task_time_hours($task_minutes),
            'unit_code'   => 'HUR',
            'unit_price'  => $rate,
            'tax_rate'    => $tax_rate,
        );
    }

    $saved = erp_invoice_draft_save(array(
        'direction'  => 'sales',
        'account_id' => (int) $account_id,
        'issue_date' => date('Y-m-d'),
        'due_date'   => '',
        'series'     => defined('ERP_DEFAULT_SERIES') ? ERP_DEFAULT_SERIES : 'PGF',
        'notes'      => lang(array('string' => 'Time spent on the tasks of #{var:1}', 'vars' => (string) $channel['name'])),
        'lines'      => $lines,
        'created_by' => (int) $viewer['id'],
    ), 0);

    if (empty($saved['success'])) {
        erp_tx_rollback();

        return $fail((string) $saved['error'], ($saved['field'] ?? '') === 'lines[0][tax_rate]' ? 'tax_rate' : '');
    }

    $invoice_id = (int) $saved['invoice_id'];

    // erp_query() answers a failure with false instead of ending the
    // request, so the draft above is rolled back with the rows.
    $tied = erp_query("UPDATE ws_task_time SET invoice_id = '" . $invoice_id . "' WHERE invoice_id = 0 AND id IN (" . implode(',', $entry_ids) . ")");

    if (($tied === false) || (mysqli_affected_rows(db::$con) !== count($entry_ids))) {
        erp_tx_rollback();

        return $fail(($tied === false) ? lang('The invoice draft could not be saved.') : lang('The time was being invoiced by somebody else at the same moment. Try again.'));
    }

    if (!erp_tx_commit()) {
        erp_tx_rollback();

        return $fail(lang('The invoice draft could not be saved.'));
    }

    log_activity(lang(array('string' => 'erp invoice draft ({var:1}) was saved', 'vars' => $invoice_id)), $_SESSION['sessionusername'] ?? '');

    $body = lang(array('string' => '{var:1} time entries, {var:2} hours → invoice draft {var:3}', 'vars' => array(
        count($entry_ids),
        function_exists('erp_quantity_text') ? erp_quantity_text(ws_task_time_hours($minutes)) : (string) ws_task_time_hours($minutes),
        '<#invoice:' . $invoice_id . '>',
    )));

    $sent = ws_message_send($viewer, $channel, $body, array('kind' => 'decision'));

    if ($sent['ok']) {
        db("UPDATE ws_messages SET locked = 1 WHERE id = '" . (int) $sent['message_id'] . "'");
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'invoice_id' => $invoice_id, 'entries' => count($entry_ids), 'minutes' => $minutes);
}

/**
 * Undoes the tie of a channel's time rows to an invoice (staff): after the
 * draft was deleted, or to make it again. An issued invoice keeps them.
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $invoice_id
 * @return array ok, error, entries
 */
function ws_task_time_unlink($viewer, $channel, $invoice_id)
{
    $invoice_id = (int) $invoice_id;

    if (!ws_task_time_ready() || !ws_task_time_is_manager($viewer) || ($invoice_id <= 0)) {
        return array('ok' => false, 'error' => lang('Only staff can undo the tie of time to an invoice.'), 'entries' => 0);
    }

    $invoice = db_item("SELECT id, status FROM erp_invoices WHERE id = '" . $invoice_id . "'");

    if (!ws_task_time_unlinkable(is_array($invoice) ? $invoice : null)) {
        return array('ok' => false, 'error' => lang('The invoice has been issued; its time stays on it.'), 'entries' => 0);
    }

    db("UPDATE ws_task_time tt INNER JOIN ws_tasks t ON t.id = tt.task_id AND t.channel_id = '" . (int) $channel['id'] . "'
        SET tt.invoice_id = 0 WHERE tt.invoice_id = '" . $invoice_id . "'");

    return array('ok' => true, 'error' => '', 'entries' => (int) mysqli_affected_rows(db::$con));
}

// ─── A person's own time, the board, the counts ─────────────────────────

/**
 * The reader's own time, week by week and task by task (My time on the
 * tasks screen).
 *
 * @param array $viewer
 * @param int   $weeks how many weeks back, this one included
 * @return array weeks (start, label, total, total_label, tasks), total, running
 */
function ws_task_time_mine($viewer, $weeks = 8)
{
    $weeks = max(1, min(52, (int) $weeks));
    $monday = date('Y-m-d', strtotime('monday this week'));
    $since = date('Y-m-d', strtotime($monday . ' 12:00:00 -' . ($weeks - 1) . ' weeks'));
    $out = array('weeks' => array(), 'total' => 0, 'total_label' => '', 'since' => $since);

    if (!ws_task_time_ready()) {
        return $out;
    }

    $rows = (array) db_items("SELECT task_id, worked_on, SUM(minutes) AS minutes FROM ws_task_time
        WHERE user_id = '" . (int) $viewer['id'] . "' AND minutes > 0 AND worked_on >= '" . e($since) . "'
        GROUP BY task_id, worked_on ORDER BY worked_on DESC");

    $tasks = array();
    $weeks_out = array();

    foreach ($rows as $row) {
        $start = date('Y-m-d', strtotime('monday this week', strtotime($row['worked_on'] . ' 12:00:00')));
        $task_id = (int) $row['task_id'];

        if (!array_key_exists($task_id, $tasks)) {
            $task = ws_task($task_id);
            $tasks[$task_id] = ($task && ws_can_see_task($viewer, $task)) ? $task : null;
        }

        if (!isset($weeks_out[$start])) {
            $weeks_out[$start] = array('start' => $start, 'label' => lang(array('string' => 'Week of {var:1}', 'vars' => ws_day_label($start))), 'total' => 0, 'tasks' => array());
        }

        $weeks_out[$start]['total'] += (int) $row['minutes'];

        if (!isset($weeks_out[$start]['tasks'][$task_id])) {
            $weeks_out[$start]['tasks'][$task_id] = array(
                'id'      => $task_id,
                'number'  => ws_task_number($task_id),
                'title'   => $tasks[$task_id] ? (string) $tasks[$task_id]['title'] : '',
                'visible' => (bool) $tasks[$task_id],
                'minutes' => 0,
            );
        }

        $weeks_out[$start]['tasks'][$task_id]['minutes'] += (int) $row['minutes'];
        $out['total'] += (int) $row['minutes'];
    }

    foreach ($weeks_out as $week) {
        $week['total_label'] = ws_task_time_label($week['total']);
        $list = array_values($week['tasks']);

        usort($list, function ($a, $b) {
            return $b['minutes'] - $a['minutes'];
        });

        foreach ($list as $index => $task) {
            $list[$index]['label'] = ws_task_time_label($task['minutes']);
        }

        $week['tasks'] = $list;
        $out['weeks'][] = $week;
    }

    $out['total_label'] = ws_task_time_label($out['total']);
    $running = ws_task_time_running($viewer['id']);
    $out['running'] = $running ? array('task_id' => (int) $running['task_id'], 'number' => ws_task_number($running['task_id']), 'started_at' => (int) $running['started_at']) : null;
    $out['now'] = time();

    return $out;
}

/**
 * The minutes each person wrote between two days, for the board's rows.
 *
 * @param int[]  $user_ids
 * @param string $from
 * @param string $to
 * @return array user id => minutes
 */
function ws_task_time_people($user_ids, $from, $to)
{
    $ids = array_filter(array_map('intval', (array) $user_ids));
    $out = array();

    if (empty($ids) || !ws_task_time_ready()) {
        return $out;
    }

    foreach ((array) db_items("SELECT user_id, SUM(minutes) AS minutes FROM ws_task_time
        WHERE user_id IN (" . implode(',', $ids) . ") AND worked_on >= '" . e($from) . "' AND worked_on <= '" . e($to) . "'
        GROUP BY user_id") as $row) {
        $out[(int) $row['user_id']] = (int) $row['minutes'];
    }

    return $out;
}

/**
 * The minutes written this week (from Monday), on a channel's tasks or on
 * all of them: the time_logged_week count of the scheduled actions.
 *
 * @param int $channel_id 0 for every task
 * @return int
 */
function ws_task_time_week_minutes($channel_id = 0)
{
    if (!ws_task_time_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COALESCE(SUM(tt.minutes), 0) FROM ws_task_time tt"
        . (((int) $channel_id > 0) ? " INNER JOIN ws_tasks t ON t.id = tt.task_id AND t.channel_id = '" . (int) $channel_id . "'" : '')
        . " WHERE tt.worked_on >= '" . e(date('Y-m-d', strtotime('monday this week'))) . "'");
}

/**
 * Every text assets/js/workspace_task_time.js shows.
 *
 * @return array
 */
function ws_task_time_js_strings()
{
    return array(
        'tt_title'           => lang('Time spent'),
        'tt_start'           => lang('Start the timer'),
        'tt_stop'            => lang('Stop the timer'),
        'tt_running_here'    => lang('The timer is running'),
        'tt_running_other'   => ws_js_template('Your timer is running on {var:1}. Starting it here stops that one.', 1),
        'tt_stopped_other'   => ws_js_template('The timer on {var:1} was stopped and kept.', 1),
        'tt_saved'           => lang('The time was saved.'),
        'tt_add'             => lang('Add time'),
        'tt_time'            => lang('Duration'),
        'tt_placeholder'     => lang('1s 30d, 90, 1:30'),
        'tt_day'             => lang('Date'),
        'tt_note'            => lang('Note'),
        'tt_billable'        => lang('Billable'),
        'tt_person'          => lang('Whose time'),
        'tt_none'            => lang('No time written yet.'),
        'tt_delete_confirm'  => lang('Delete this time entry?'),
        'tt_on_invoice'      => lang('On an invoice'),
        'tt_not_billable'    => lang('Not billable'),
        'tt_total'           => ws_js_template('Total {var:1}', 1),
        'tt_over'            => lang('More than estimated'),
        'tt_card'            => lang('Time spent'),
        'tt_month'           => lang('This month'),
        'tt_all'             => lang('In total'),
        'tt_unbilled'        => lang('Unbilled'),
        'tt_by_person'       => lang('By person'),
        'tt_invoices'        => lang('On invoices'),
        'tt_unlink'          => lang('Undo the tie'),
        'tt_unlink_confirm'  => lang('Undo the tie of this time to the invoice? The time can be invoiced again.'),
        'tt_unlinked'        => lang('The tie was undone.'),
        'tt_invoice'         => lang('Create an invoice draft'),
        'tt_invoice_help'    => lang('The unbilled billable time between the two days, one line per task, the hours as the quantity. The draft is not issued.'),
        'tt_from'            => lang('First day'),
        'tt_to'              => lang('Last day'),
        'tt_rate'            => lang('Hourly rate'),
        'tt_tax'             => lang('VAT rate (%)'),
        'tt_invoiced'        => ws_js_template('Invoice draft #{var:1} was created: {var:2} time entries.', 2),
        'tt_open_draft'      => lang('Open the draft'),
        'tt_my_time'         => lang('My time'),
        'tt_my_time_empty'   => lang('You have not written any time in these weeks.'),
        'tt_board'           => ws_js_template('Planned {var:1} · spent {var:2}', 2),
    );
}
