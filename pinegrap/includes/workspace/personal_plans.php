<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's own plans on My calendar (ws_personal_plans): a
 * reminder or a piece of work of their own, closed to everybody else, an
 * administrator included. A plan is never a row of ws_events, whose readers
 * do not ask whose row it is (plan items, the planning board, capacity, days
 * off, busy times, the outgoing calendar feed).
 *
 * Every read of the plan tables goes through this file, and every plan read
 * goes through ws_personal_plan_can_see() (tests/workspace_personal_plans_test.php
 * holds the other files to that). Nothing here writes a Site Log line, an
 * audit line, a webhook or a flow event: the title and the note are the
 * person's own words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest note a plan keeps. */
const WS_PERSONAL_PLAN_NOTE_MAX = 2000;

/** How many plans a person may keep. */
const WS_PERSONAL_PLAN_MAX = 5000;

/**
 * Whether the database has the plan tables (2026.11.0, 11.0.200): the one
 * probe, as the step adds them and ws_inbox.plan_id at once.
 *
 * @return bool
 */
function ws_personal_plans_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_personal_plans') && pg_schema_has('ws_inbox', 'plan_id');
    }

    return $ready;
}

/**
 * The one gate of a plan: may this person see it? Only its owner, while both
 * are on the team: a plan of somebody who left is read by nobody, and a
 * guest has no calendar. Sharing (visibility, ws_personal_plan_people) opens
 * it to others in a later step, here and nowhere else.
 *
 * @param int        $user_id the reader
 * @param array|null $plan    ws_personal_plans row
 * @param int[]|null $team    the team's ids (ws_team_ids()), for a test
 * @return bool
 */
function ws_personal_plan_can_see($user_id, $plan, $team = null)
{
    $user_id = (int) $user_id;

    if (!is_array($plan) || ($user_id <= 0)) {
        return false;
    }

    $team = ($team === null) ? ws_team_ids() : array_map('intval', (array) $team);

    if (!in_array($user_id, $team, true) || !in_array((int) $plan['user_id'], $team, true)) {
        return false;
    }

    return (int) $plan['user_id'] === $user_id;
}

/**
 * May this person change or delete the plan? Its owner alone, while they
 * may see it.
 *
 * @param int        $user_id
 * @param array|null $plan
 * @param int[]|null $team
 * @return bool
 */
function ws_personal_plan_can_edit($user_id, $plan, $team = null)
{
    return ws_personal_plan_can_see($user_id, $plan, $team) && ((int) $plan['user_id'] === (int) $user_id);
}

/**
 * One plan, if the reader may see it.
 *
 * @param int $user_id the reader
 * @param int $plan_id
 * @return array|null
 */
function ws_personal_plan($user_id, $plan_id)
{
    if (!ws_personal_plans_ready() || ((int) $plan_id <= 0)) {
        return null;
    }

    $plan = db_item("SELECT * FROM ws_personal_plans WHERE id = '" . (int) $plan_id . "'");

    return ws_personal_plan_can_see($user_id, $plan) ? $plan : null;
}

/**
 * The plans the reader may see that touch a span of time (end exclusive, as
 * a plan item's).
 *
 * @param int $user_id
 * @param int $from_ts
 * @param int $to_ts
 * @return array[]
 */
function ws_personal_plans_between($user_id, $from_ts, $to_ts)
{
    if (!ws_personal_plans_ready() || ((int) $user_id <= 0)) {
        return array();
    }

    $plans = array();
    $team = ws_team_ids();

    foreach ((array) db_items("SELECT * FROM ws_personal_plans
        WHERE user_id = '" . (int) $user_id . "' AND starts_at < '" . (int) $to_ts . "' AND ends_at > '" . (int) $from_ts . "'
        ORDER BY starts_at, id
        LIMIT 1000") as $plan) {
        if (ws_personal_plan_can_see($user_id, $plan, $team)) {
            $plans[] = $plan;
        }
    }

    return $plans;
}

/**
 * Reads and checks a plan sent by the drawer. Pure.
 *
 * @param array $data title, note, start (Y-m-d), end (Y-m-d, '' = start),
 *                    all_day, start_time, end_time (H:i), remind (minutes
 *                    before the start, '' for none)
 * @return array array('error' => string) or the clean row: title, note,
 *               starts_at, ends_at, all_day, remind_at
 */
function ws_personal_plan_clean($data)
{
    $text = function ($value) {
        return is_scalar($value) ? trim((string) $value) : '';
    };

    $title = $text($data['title'] ?? '');

    if ($title === '') {
        return array('error' => lang('Give the plan a title.'));
    }

    $title = mb_substr($title, 0, 255);
    $note = mb_substr($text($data['note'] ?? ''), 0, WS_PERSONAL_PLAN_NOTE_MAX);
    $day = function ($value) {
        return (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) ? $value : '';
    };
    $start = $day($text($data['start'] ?? ''));

    if ($start === '') {
        return array('error' => lang('Choose the day of the plan.'));
    }

    $end = $day($text($data['end'] ?? ''));
    $end = ($end === '') ? $start : $end;

    if ($end < $start) {
        return array('error' => lang('The last day cannot come before the first.'));
    }

    $all_day = !empty($data['all_day']) && ($data['all_day'] !== '0');

    if ($all_day) {
        $starts_at = strtotime($start . ' 00:00:00');
        $ends_at = strtotime($end . ' 00:00:00 +1 day');
    } else {
        $time = function ($value) {
            return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value) ? $value : '';
        };
        $start_time = $time($text($data['start_time'] ?? ''));

        if ($start_time === '') {
            return array('error' => lang('Choose the time of the plan.'));
        }

        $end_time = $time($text($data['end_time'] ?? ''));
        $starts_at = strtotime($start . ' ' . $start_time . ':00');
        $ends_at = ($end_time === '') ? $starts_at + 3600 : strtotime($end . ' ' . $end_time . ':00');

        if ($ends_at <= $starts_at) {
            return array('error' => lang('The plan has to end after it starts.'));
        }
    }

    $remind = $text($data['remind'] ?? '');
    $remind_at = 0;

    if ($remind !== '') {
        if (!ctype_digit($remind) || ((int) $remind > 40320)) {
            return array('error' => lang('Choose when to be reminded.'));
        }

        // An all-day plan reminds at 09:00 of its first day, less the lead.
        $at = $all_day ? strtotime($start . ' 09:00:00') : $starts_at;
        $remind_at = max(1, $at - ((int) $remind * 60));
    }

    return array(
        'title'     => $title,
        'note'      => $note,
        'starts_at' => (int) $starts_at,
        'ends_at'   => (int) $ends_at,
        'all_day'   => $all_day ? 1 : 0,
        'remind_at' => (int) $remind_at,
        'remind'    => ($remind === '') ? '' : (int) $remind,
    );
}

/**
 * Saves a plan of the viewer: a new one, or their own.
 *
 * @param array $viewer
 * @param array $data    ws_personal_plan_clean()'s input
 * @param int   $plan_id 0 for a new plan
 * @return array ok + plan, or error
 */
function ws_personal_plan_save($viewer, $data, $plan_id = 0)
{
    $user_id = (int) $viewer['id'];

    if (!ws_personal_plans_ready() || empty($viewer['member'])) {
        return array('error' => lang('Access denied'));
    }

    $clean = ws_personal_plan_clean($data);

    if (isset($clean['error'])) {
        return $clean;
    }

    $now = time();
    $plan_id = (int) $plan_id;

    // A reminder already gone stays gone unless its moment moved.
    $values = "title = '" . e($clean['title']) . "', note = '" . e($clean['note']) . "',
        starts_at = '" . $clean['starts_at'] . "', ends_at = '" . $clean['ends_at'] . "', all_day = '" . $clean['all_day'] . "',
        remind_at = '" . $clean['remind_at'] . "', updated_at = '" . $now . "'";

    if ($plan_id > 0) {
        $plan = ws_personal_plan($user_id, $plan_id);

        if (!$plan || !ws_personal_plan_can_edit($user_id, $plan)) {
            return array('error' => lang('This plan cannot be found.'));
        }

        $rearm = ((int) $plan['remind_at'] !== $clean['remind_at']) ? ", reminded_at = 0" : '';
        db("UPDATE ws_personal_plans SET " . $values . $rearm . " WHERE id = '" . $plan_id . "' AND user_id = '" . $user_id . "'");
    } else {
        if ((int) db_value("SELECT COUNT(*) FROM ws_personal_plans WHERE user_id = '" . $user_id . "'") >= WS_PERSONAL_PLAN_MAX) {
            return array('error' => lang('You have too many plans. Delete some first.'));
        }

        db("INSERT INTO ws_personal_plans SET user_id = '" . $user_id . "', created_at = '" . $now . "', " . $values);
        $plan_id = (int) mysqli_insert_id(db::$con);
    }

    return array('ok' => true, 'plan' => ws_personal_plan_present(ws_personal_plan($user_id, $plan_id)));
}

/**
 * Deletes a plan of the viewer, with its steps, its people and the reminder
 * lines naming it. There is no bin.
 *
 * @param array $viewer
 * @param int   $plan_id
 * @return array ok or error
 */
function ws_personal_plan_delete($viewer, $plan_id)
{
    $plan = ws_personal_plan((int) $viewer['id'], $plan_id);

    if (!$plan || !ws_personal_plan_can_edit((int) $viewer['id'], $plan)) {
        return array('error' => lang('This plan cannot be found.'));
    }

    ws_personal_plans_remove(array((int) $plan['id']));

    return array('ok' => true);
}

/**
 * Removes plans and everything naming them. The inbox lines go through
 * ws_inbox_delete(), so an application syncing the inbox hears of them.
 *
 * @param int[] $plan_ids
 */
function ws_personal_plans_remove($plan_ids)
{
    $plan_ids = array_values(array_filter(array_map('intval', (array) $plan_ids)));

    if (empty($plan_ids) || !ws_personal_plans_ready()) {
        return;
    }

    $in = implode(',', $plan_ids);

    if (function_exists('ws_inbox_delete')) {
        ws_inbox_delete("plan_id IN (" . $in . ")");
    } else {
        db("DELETE FROM ws_inbox WHERE plan_id IN (" . $in . ")");
    }

    db("DELETE FROM ws_personal_plan_steps WHERE plan_id IN (" . $in . ")");
    db("DELETE FROM ws_personal_plan_people WHERE plan_id IN (" . $in . ")");
    db("DELETE FROM ws_personal_plans WHERE id IN (" . $in . ")");
}

/**
 * An account deleted (pg_user_delete()): its plans go, and it is taken off
 * the plans shared with it.
 *
 * @param int $user_id
 */
function ws_personal_plans_user_deleted($user_id)
{
    if (!ws_personal_plans_ready() || ((int) $user_id <= 0)) {
        return;
    }

    ws_personal_plans_remove((array) db_values("SELECT id FROM ws_personal_plans WHERE user_id = '" . (int) $user_id . "'"));
    db("DELETE FROM ws_personal_plan_people WHERE user_id = '" . (int) $user_id . "'");
}

/**
 * A plan as the drawer and the calendar read it. Pure.
 *
 * @param array|null $plan
 * @return array|null
 */
function ws_personal_plan_present($plan)
{
    if (!is_array($plan)) {
        return null;
    }

    $starts = (int) $plan['starts_at'];
    $ends = (int) $plan['ends_at'];
    $all_day = ((int) $plan['all_day'] === 1);
    // The last day: an all-day plan ends at midnight after it.
    $last = $all_day ? $ends - 1 : $ends;
    $at = $all_day ? strtotime(date('Y-m-d', $starts) . ' 09:00:00') : $starts;

    return array(
        'id'         => (int) $plan['id'],
        'title'      => (string) $plan['title'],
        'note'       => (string) $plan['note'],
        'start'      => date('Y-m-d', $starts),
        'end'        => date('Y-m-d', max($starts, $last)),
        'start_time' => $all_day ? '' : date('H:i', $starts),
        'end_time'   => $all_day ? '' : date('H:i', $ends),
        'all_day'    => $all_day,
        'remind'     => ((int) $plan['remind_at'] > 0) ? (string) max(0, (int) round(($at - (int) $plan['remind_at']) / 60)) : '',
    );
}

/**
 * Lays the reader's plans on the days of a calendar grid, as plan items are
 * laid (ws_calendar_plan_place()): kind personal, opened in the plan drawer.
 * Pure.
 *
 * @param array   $days  date => day with an events list
 * @param array[] $plans ws_personal_plans rows
 * @return array the days
 */
function ws_personal_plans_place($days, $plans)
{
    foreach ($plans as $plan) {
        foreach (array_keys($days) as $date) {
            $day_start = strtotime($date . ' 00:00:00');
            $day_end = strtotime($date . ' 00:00:00 +1 day');

            if (((int) $plan['starts_at'] >= $day_end) || ((int) $plan['ends_at'] <= $day_start)) {
                continue;
            }

            $days[$date]['events'][] = array(
                'id'          => 0,
                'plan_id'     => (int) $plan['id'],
                'kind'        => 'personal',
                'title'       => (string) $plan['title'],
                'time'        => ((int) $plan['all_day'] === 1) ? '' : date('H:i', max((int) $plan['starts_at'], $day_start)),
                'scope'       => 'people',
                'people'      => array(),
                'meeting_url' => '',
            );
        }
    }

    return $days;
}

/**
 * Whether a plan's reminder waits: the general job asks before it loads the
 * workspace.
 *
 * @return bool
 */
function ws_personal_plan_reminders_waiting()
{
    return ws_personal_plans_ready() && (bool) db_value("SELECT id FROM ws_personal_plans
        WHERE reminded_at = 0 AND remind_at > 0 AND remind_at <= '" . time() . "' LIMIT 1");
}

/**
 * Tells the owners of the plans whose reminder is due: a line in their own
 * inbox, which wakes their devices. Claimed as the task reminders are, so
 * two runs never tell twice; a plan whose owner left the team is passed by.
 *
 * @param int $limit
 * @return int the lines written
 */
function ws_personal_plan_reminders_run($limit = 50)
{
    if (!ws_personal_plans_ready() || !ws_enabled()) {
        return 0;
    }

    $now = time();
    $told = 0;
    $team = ws_team_ids();

    foreach ((array) db_values("SELECT id FROM ws_personal_plans
        WHERE reminded_at = 0 AND remind_at > 0 AND remind_at <= '" . $now . "'
        ORDER BY remind_at LIMIT " . max(1, (int) $limit)) as $plan_id) {
        db("UPDATE ws_personal_plans SET reminded_at = '" . $now . "' WHERE id = '" . (int) $plan_id . "' AND reminded_at = 0");

        if (mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        $plan = db_item("SELECT * FROM ws_personal_plans WHERE id = '" . (int) $plan_id . "'");

        // Over already, or its owner gone: nobody to tell.
        if (!ws_personal_plan_can_see(is_array($plan) ? (int) $plan['user_id'] : 0, $plan, $team) || ((int) $plan['ends_at'] <= $now)) {
            continue;
        }

        ws_notify((int) $plan['user_id'], 'plan_reminder', array('plan_id' => (int) $plan['id'], 'actor_id' => 0, 'batch' => true));
        $told++;
    }

    if ($told > 0) {
        ws_push_batch_done(true);
    }

    return $told;
}

/**
 * What a reminder line says (ws_inbox_describe()): read through the gate, so
 * a line nobody may read any more says nothing of the plan.
 *
 * @param array  $viewer
 * @param array  $row  ws_inbox row
 * @param string $base the panel's address
 * @return array title, body, url, icon
 */
function ws_personal_plan_inbox_describe($viewer, $row, $base)
{
    $plan = ws_personal_plan((int) $viewer['id'], (int) ($row['plan_id'] ?? 0));

    if (!$plan) {
        return array('title' => lang('Reminder'), 'body' => '', 'url' => $base . 'workspace_my_calendar.php', 'icon' => 'bi-alarm');
    }

    $when = ((int) $plan['all_day'] === 1)
        ? ws_day_label(date('Y-m-d', (int) $plan['starts_at']))
        : ws_day_label(date('Y-m-d', (int) $plan['starts_at'])) . ' ' . date('H:i', (int) $plan['starts_at']);

    return array(
        'title' => lang(array('string' => 'Reminder: {var:1}', 'vars' => mb_substr((string) $plan['title'], 0, 150))),
        'body'  => $when,
        'url'   => $base . 'workspace_my_calendar.php?view=list&date=' . date('Y-m-d', (int) $plan['starts_at']) . '&plan=' . (int) $plan['id'],
        'icon'  => 'bi-alarm',
    );
}

/**
 * The texts of the plan drawer (assets/js/workspace_my_calendar.js).
 *
 * @return array
 */
function ws_personal_plan_js_strings()
{
    return array(
        'new_plan'       => lang('New plan'),
        'edit_plan'      => lang('Plan'),
        'plan_title'     => lang('Title'),
        'plan_first_day' => lang('First day'),
        'plan_last_day'  => lang('Last day'),
        'plan_from'      => lang('Start time'),
        'plan_until'     => lang('End time'),
        'plan_note'      => lang('Note'),
        'plan_remind'    => lang('Reminder'),
        'plan_no_remind' => lang('No reminder'),
        'plan_private'   => lang('Only you see this plan.'),
        'plan_saved'     => lang('The plan is saved.'),
        'plan_deleted'   => lang('The plan is deleted.'),
        'plan_delete_q'  => lang('Delete this plan? It cannot be brought back.'),
        'save'           => lang('Save'),
        'delete'         => lang('Delete'),
        'my_plan'        => lang('My plan'),
        'remind_options' => array(
            '0'    => lang('At the start'),
            '15'   => lang('15 minutes before'),
            '60'   => lang('1 hour before'),
            '1440' => lang('1 day before'),
        ),
    );
}
