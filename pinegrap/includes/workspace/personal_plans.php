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

// Repeating plans: every reader of this file (the job, account deletion)
// needs the times too.
require_once(__DIR__ . '/personal_plan_repeat.php');

/** The longest note a plan keeps. */
const WS_PERSONAL_PLAN_NOTE_MAX = 2000;

/** How many plans a person may keep. */
const WS_PERSONAL_PLAN_MAX = 5000;

/**
 * Whether the database has the plan tables (2026.11.0, 11.0.201): the one
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
 * The one gate of a plan: may this person see it? Its owner, and those the
 * owner opened it to (visibility): chosen team members (people) or the whole
 * team (team); "me" is the default. Both reader and owner must be on the team:
 * a plan of somebody who left is read by nobody, a guest has no calendar, and
 * an administrator is no exception.
 *
 * @param int        $user_id the reader
 * @param array|null $plan    ws_personal_plans row
 * @param int[]|null $team    the team's ids (ws_team_ids()), for a test
 * @param int[]|null $people  the chosen members (ws_personal_plan_people_ids()),
 *                            for a test; read when the plan names people
 * @return bool
 */
function ws_personal_plan_can_see($user_id, $plan, $team = null, $people = null)
{
    $user_id = (int) $user_id;

    if (!is_array($plan) || ($user_id <= 0)) {
        return false;
    }

    $team = ($team === null) ? ws_team_ids() : array_map('intval', (array) $team);

    if (!in_array($user_id, $team, true) || !in_array((int) $plan['user_id'], $team, true)) {
        return false;
    }

    if ((int) $plan['user_id'] === $user_id) {
        return true;
    }

    $visibility = (string) ($plan['visibility'] ?? 'me');

    if ($visibility === 'team') {
        return true;
    }

    if ($visibility === 'people') {
        $people = ($people === null) ? ws_personal_plan_people_ids((int) $plan['id']) : array_map('intval', (array) $people);

        return in_array($user_id, $people, true);
    }

    return false;
}

/**
 * The team members a plan is opened to, one by one.
 *
 * @param int $plan_id
 * @return int[]
 */
function ws_personal_plan_people_ids($plan_id)
{
    if (!ws_personal_plans_ready() || ((int) $plan_id <= 0)) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT user_id FROM ws_personal_plan_people WHERE plan_id = '" . (int) $plan_id . "' ORDER BY user_id"));
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
 * @param int  $to_ts
 * @param bool $times each time of a repeating plan as its own row (with
 *                    time_day), or the plan rows alone
 * @return array[]
 */
function ws_personal_plans_between($user_id, $from_ts, $to_ts, $times = true)
{
    if (!ws_personal_plans_ready() || ((int) $user_id <= 0)) {
        return array();
    }

    $plans = array();
    $team = ws_team_ids();
    // A repeating plan whose series still runs in the span: its times are
    // worked out below (personal_plan_repeat.php).
    $repeating = ws_personal_plan_repeat_ready()
        ? " OR (repeat_frequency <> '' AND (repeat_until = 0 OR repeat_until > '" . (int) $from_ts . "'))" : '';

    // Their own, those opened to the team, and those naming them; the gate
    // then decides each one.
    foreach ((array) db_items("SELECT * FROM ws_personal_plans
        WHERE (user_id = '" . (int) $user_id . "' OR visibility = 'team'
            OR (visibility = 'people' AND id IN (SELECT plan_id FROM ws_personal_plan_people WHERE user_id = '" . (int) $user_id . "')))
        AND starts_at < '" . (int) $to_ts . "' AND (ends_at > '" . (int) $from_ts . "'" . $repeating . ")
        ORDER BY starts_at, id
        LIMIT 1000") as $plan) {
        if (ws_personal_plan_can_see($user_id, $plan, $team)) {
            $plans[] = $plan;
        }
    }

    return $times ? ws_personal_plans_as_times($plans, (int) $from_ts, (int) $to_ts) : $plans;
}

/**
 * The reader's own plans made from mail conversations (Add to my calendar),
 * conversation id => plan row, through the gate. Read once a request: a
 * page of mail cards asks it for each card.
 *
 * @param int  $user_id
 * @param bool $fresh   read again (after a save)
 * @return array[]
 */
function ws_personal_plan_mail_plans($user_id, $fresh = false)
{
    static $plans = array();

    if (!ws_personal_plans_ready() || ((int) $user_id <= 0)) {
        return array();
    }

    if ($fresh || !isset($plans[(int) $user_id])) {
        $plans[(int) $user_id] = array();

        foreach ((array) db_items("SELECT * FROM ws_personal_plans
            WHERE user_id = '" . (int) $user_id . "' AND mail_conversation_id > 0 ORDER BY id LIMIT 5000") as $plan) {
            if (ws_personal_plan_can_see($user_id, $plan)) {
                $plans[(int) $user_id][(int) $plan['mail_conversation_id']] = $plan;
            }
        }
    }

    return $plans[(int) $user_id];
}

/**
 * The reader's own plan made from a mail conversation: one a person.
 *
 * @param int $user_id
 * @param int $conversation_id
 * @return array|null
 */
function ws_personal_plan_of_mail($user_id, $conversation_id)
{
    $plans = ws_personal_plan_mail_plans($user_id);

    return $plans[(int) $conversation_id] ?? null;
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

    // Who else may see it: nobody (the default), chosen team members or the
    // whole team. The members are checked against the team when saved.
    $visibility = $text($data['visibility'] ?? 'me');
    $visibility = in_array($visibility, array('me', 'people', 'team'), true) ? $visibility : 'me';
    $people = array();

    if ($visibility === 'people') {
        foreach ((array) ($data['people'] ?? array()) as $id) {
            if (is_scalar($id) && ctype_digit((string) $id) && ((int) $id > 0)) {
                $people[(int) $id] = (int) $id;
            }
        }

        if (empty($people)) {
            return array('error' => lang('Choose who may see the plan.'));
        }
    }

    return array(
        'visibility' => $visibility,
        'people'    => array_values($people),
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
    $plan = ($plan_id > 0) ? ws_personal_plan($user_id, $plan_id) : null;

    if (($plan_id > 0) && (!$plan || !ws_personal_plan_can_edit($user_id, $plan))) {
        return array('error' => lang('This plan cannot be found.'));
    }

    // One time of a series changed alone, or it and the ones after it.
    if ($plan && ws_personal_plan_repeat_ready() && ws_personal_plan_repeats($plan)
        && in_array((string) ($data['mode'] ?? ''), array('one', 'following'), true)) {
        $split = ws_personal_plan_save_time($viewer, $plan, $data);

        if ($split !== null) {
            return $split;
        }
    }

    // The rule: a write that does not send it keeps the plan's own.
    $rule = array();

    if (ws_personal_plan_repeat_ready()) {
        $sent = array_key_exists('repeat', $data) ? $data : ($plan ? ws_personal_plan_repeat_present($plan) : array());
        $rule = ws_personal_plan_repeat_clean($clean, $sent);

        if (isset($rule['error'])) {
            return $rule;
        }

        // A roadmap does not repeat.
        if (($rule['repeat_frequency'] !== '') && $plan && function_exists('ws_personal_plan_step_rows')
            && !empty(ws_personal_plan_step_rows(array($plan_id))[$plan_id])) {
            return array('error' => lang('A roadmap does not repeat. Remove its steps first.'));
        }

        $rule['remind_minutes'] = ($clean['remind'] === '') ? 0 : (int) $clean['remind'];

        // A series reminds at its next time; with none left, it stays told.
        if (($rule['repeat_frequency'] !== '') && ($clean['remind_at'] > 0)) {
            $row = array_merge($clean, $rule, array('id' => $plan_id));
            $next = ws_personal_plan_next_remind($row, $now, $plan_id ? (ws_personal_plan_skips(array($plan_id))[$plan_id] ?? array()) : array());
            $clean['remind_at'] = ($next > 0) ? $next : $clean['remind_at'];
            $rule['reminded_at'] = ($next > 0) ? 0 : $now;
        }
    }

    // Only team members other than the owner can be chosen: a guest never.
    $people = array_values(array_diff(array_intersect($clean['people'], ws_team_ids()), array($user_id)));

    if (($clean['visibility'] === 'people') && empty($people)) {
        return array('error' => lang('Choose who may see the plan.'));
    }

    // A reminder already gone stays gone unless its moment moved.
    $values = "title = '" . e($clean['title']) . "', note = '" . e($clean['note']) . "',
        starts_at = '" . $clean['starts_at'] . "', ends_at = '" . $clean['ends_at'] . "', all_day = '" . $clean['all_day'] . "',
        remind_at = '" . $clean['remind_at'] . "', visibility = '" . e($clean['visibility']) . "', updated_at = '" . $now . "'";

    foreach (array('repeat_frequency', 'repeat_interval', 'repeat_until', 'repeat_count', 'remind_minutes') as $column) {
        $values .= isset($rule[$column]) ? ", " . $column . " = '" . e((string) $rule[$column]) . "'" : '';
    }

    // The time a "This one" change was made from (ws_personal_plan_save_time()).
    $values .= ((int) ($data['repeat_of'] ?? 0) > 0) && ws_personal_plan_repeat_ready() ? ", repeat_of = '" . (int) $data['repeat_of'] . "'" : '';

    if ($plan_id > 0) {
        $rearm = isset($rule['reminded_at']) ? ", reminded_at = '" . (int) $rule['reminded_at'] . "'"
            : (((int) $plan['remind_at'] !== $clean['remind_at']) ? ", reminded_at = 0" : '');
        db("UPDATE ws_personal_plans SET " . $values . $rearm . " WHERE id = '" . $plan_id . "' AND user_id = '" . $user_id . "'");
    } else {
        if ((int) db_value("SELECT COUNT(*) FROM ws_personal_plans WHERE user_id = '" . $user_id . "'") >= WS_PERSONAL_PLAN_MAX) {
            return array('error' => lang('You have too many plans. Delete some first.'));
        }

        // Add to my calendar: a new plan made from a mail the person may
        // read is tied to its conversation (personal_plan_mail.php).
        $mail = ((int) ($data['mail_item'] ?? 0) > 0) ? ws_personal_plan_mail_item($viewer, (int) $data['mail_item']) : null;

        if (((int) ($data['mail_item'] ?? 0) > 0) && !$mail) {
            return array('error' => lang('This e-mail cannot be found.'));
        }

        // One plan a person for a conversation: a second press opens the first.
        if ($mail && ws_personal_plan_of_mail($user_id, (int) $mail['item']['conversation_id'])) {
            return array('error' => lang('This e-mail is already in your calendar.'));
        }

        $values .= $mail ? ", mail_conversation_id = '" . (int) $mail['item']['conversation_id'] . "'" : '';
        $values .= isset($rule['reminded_at']) ? ", reminded_at = '" . (int) $rule['reminded_at'] . "'" : '';
        db("INSERT INTO ws_personal_plans SET user_id = '" . $user_id . "', created_at = '" . $now . "', " . $values);
        $plan_id = (int) mysqli_insert_id(db::$con);
        ws_personal_plan_mail_plans($user_id, true);
    }

    db("DELETE FROM ws_personal_plan_people WHERE plan_id = '" . $plan_id . "'");

    if ($clean['visibility'] === 'people') {
        db("INSERT IGNORE INTO ws_personal_plan_people (plan_id, user_id) VALUES " . implode(', ', array_map(function ($id) use ($plan_id) {
            return "('" . $plan_id . "', '" . (int) $id . "')";
        }, $people)));
    }

    return array('ok' => true, 'plan' => ws_personal_plan_for_reader($user_id, ws_personal_plan($user_id, $plan_id), $viewer));
}

/**
 * Deletes a plan of the viewer, with its steps, its people and the reminder
 * lines naming it. There is no bin.
 *
 * @param array $viewer
 * @param int   $plan_id
 * @return array ok or error
 */
function ws_personal_plan_delete($viewer, $plan_id, $mode = '', $day = '')
{
    $plan = ws_personal_plan((int) $viewer['id'], $plan_id);

    if (!$plan || !ws_personal_plan_can_edit((int) $viewer['id'], $plan)) {
        return array('error' => lang('This plan cannot be found.'));
    }

    if (ws_personal_plan_repeat_ready() && ws_personal_plan_repeats($plan) && in_array($mode, array('one', 'following'), true)) {
        $done = ws_personal_plan_delete_time($plan, $mode, (string) $day);

        if ($done !== null) {
            return $done;
        }
    }

    // A series goes with the times changed out of it.
    $ids = array((int) $plan['id']);

    if (ws_personal_plan_repeat_ready() && ws_personal_plan_repeats($plan)) {
        $ids = array_merge($ids, array_map('intval', (array) db_values("SELECT id FROM ws_personal_plans
            WHERE repeat_of = '" . (int) $plan['id'] . "' AND user_id = '" . (int) $plan['user_id'] . "'")));
    }

    ws_personal_plans_remove($ids);

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

    if (ws_personal_plan_repeat_ready()) {
        db("DELETE FROM ws_personal_plan_skips WHERE plan_id IN (" . $in . ")");
    }
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

    $remind = ((int) $plan['remind_at'] > 0) ? (string) max(0, (int) round(($at - (int) $plan['remind_at']) / 60)) : '';

    // A series keeps its lead: its reminder moment moves on with each time.
    if (((int) $plan['remind_at'] > 0) && function_exists('ws_personal_plan_repeats') && ws_personal_plan_repeats($plan)) {
        $remind = (string) (int) ($plan['remind_minutes'] ?? 0);
    }

    return array_merge(array(
        'id'         => (int) $plan['id'],
        'title'      => (string) $plan['title'],
        'note'       => (string) $plan['note'],
        'start'      => date('Y-m-d', $starts),
        'end'        => date('Y-m-d', max($starts, $last)),
        'start_time' => $all_day ? '' : date('H:i', $starts),
        'end_time'   => $all_day ? '' : date('H:i', $ends),
        'all_day'    => $all_day,
        'remind'     => $remind,
        'visibility' => (string) ($plan['visibility'] ?? 'me'),
        'owner_id'   => (int) ($plan['user_id'] ?? 0),
        // The time the drawer was opened on, for a repeating plan.
        'time_day'   => (string) ($plan['time_day'] ?? ''),
    ), function_exists('ws_personal_plan_repeat_present') ? ws_personal_plan_repeat_present($plan) : array());
}

/**
 * A plan as one reader gets it: the owner gets everything and may change it;
 * anybody it is opened to reads it under the owner's name, without the
 * owner's reminder or the list of the others it is opened to.
 *
 * @param int        $user_id the reader
 * @param array      $plan    a row the gate let through
 * @param array|null $viewer  the reader, for the mail line of a plan made
 *                            from a mail (drawn only for whoever may read it)
 * @return array
 */
function ws_personal_plan_for_reader($user_id, $plan, $viewer = null)
{
    $out = ws_personal_plan_present($plan);
    $mine = ((int) $plan['user_id'] === (int) $user_id);
    $owner = $mine ? null : ws_people(array((int) $plan['user_id']));

    $out['mine'] = $mine;
    $out['people'] = ($mine && ($out['visibility'] === 'people')) ? ws_personal_plan_people_ids((int) $plan['id']) : array();
    $out['owner_name'] = $mine ? '' : (string) ($owner[(int) $plan['user_id']]['name'] ?? '');
    // The mail a plan was made from (Add to my calendar): its subject and
    // conversation, for a reader who may read that conversation.
    $out['mail'] = (is_array($viewer) && ((int) ($plan['mail_conversation_id'] ?? 0) > 0) && function_exists('ws_personal_plan_mail_line'))
        ? ws_personal_plan_mail_line($viewer, (int) $plan['mail_conversation_id']) : null;

    if (!$mine) {
        $out['remind'] = '';
        $out['visibility'] = '';
    }

    return $out;
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
function ws_personal_plans_place($days, $plans, $reader = 0, $names = array())
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
                'plan_day'    => (string) ($plan['time_day'] ?? ''),
                // A time of a repeating plan: its rule, marked before it is opened.
                'repeat'      => (string) ($plan['repeat_frequency'] ?? ''),
                'kind'        => 'personal',
                'title'       => (string) $plan['title'],
                'time'        => ((int) $plan['all_day'] === 1) ? '' : date('H:i', max((int) $plan['starts_at'], $day_start)),
                'scope'       => 'people',
                'people'      => array(),
                'meeting_url' => '',
                // A plan opened to the reader by somebody else: whose it is.
                'owner'       => ((int) ($plan['user_id'] ?? 0) !== (int) $reader) ? (string) ($names[(int) ($plan['user_id'] ?? 0)] ?? '') : '',
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
        $ends_at = is_array($plan) ? (int) $plan['ends_at'] : 0;

        // A series: the time this reminder is for, then the next one armed.
        if (ws_personal_plan_repeat_ready() && ws_personal_plan_repeats($plan)) {
            $skips = ws_personal_plan_skips(array((int) $plan_id))[(int) $plan_id] ?? array();
            $time = ws_personal_plan_time_on($plan, ws_personal_plan_remind_day($plan, (int) $plan['remind_at']), $skips);
            $ends_at = $time ? (int) $time['ends_at'] : 0;
            $next = ws_personal_plan_next_remind($plan, max($now, (int) $plan['remind_at']), $skips);

            if ($next > 0) {
                db("UPDATE ws_personal_plans SET remind_at = '" . $next . "', reminded_at = 0 WHERE id = '" . (int) $plan_id . "' AND reminded_at = '" . $now . "'");
            }
        }

        // Over already, or its owner gone: nobody to tell.
        if (!ws_personal_plan_can_see(is_array($plan) ? (int) $plan['user_id'] : 0, $plan, $team) || ($ends_at <= $now)) {
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
 * The time of a series a reminder written at a moment is for: the one with
 * the latest reminder moment not after it. The first time still running is
 * not it when the lead reaches the next time. Null before the first
 * reminder. Pure.
 *
 * @param array    $plan       with remind_minutes
 * @param int      $written_at
 * @param string[] $skips
 * @return array|null ws_personal_plan_time()
 */
function ws_personal_plan_reminded_time($plan, $written_at, $skips = array())
{
    $lead = max(0, (int) ($plan['remind_minutes'] ?? 0)) * 60;
    $found = null;

    // A time reminding by the moment starts no later than the lead (and a
    // day for the 09:00 of an all-day time) after it.
    foreach (ws_personal_plan_times($plan, $written_at - $lead - 86400, $written_at + $lead + 86400, $skips) as $time) {
        if (ws_personal_plan_time_remind_at($plan, $time) <= $written_at) {
            $found = $time;
        }
    }

    return $found;
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

    // A series: the time whose reminder moment the line was written for.
    $day = '';

    if (ws_personal_plan_repeat_ready() && ws_personal_plan_repeats($plan)) {
        $time = ws_personal_plan_reminded_time($plan, (int) ($row['created_at'] ?? 0), ws_personal_plan_skips(array((int) $plan['id']))[(int) $plan['id']] ?? array());

        if ($time) {
            $plan = array_merge($plan, array('starts_at' => $time['starts_at'], 'ends_at' => $time['ends_at']));
            $day = '&day=' . $time['day'];
        }
    }

    $when = ((int) $plan['all_day'] === 1)
        ? ws_day_label(date('Y-m-d', (int) $plan['starts_at']))
        : ws_day_label(date('Y-m-d', (int) $plan['starts_at'])) . ' ' . date('H:i', (int) $plan['starts_at']);

    return array(
        'title' => lang(array('string' => 'Reminder: {var:1}', 'vars' => mb_substr((string) $plan['title'], 0, 150))),
        'body'  => $when,
        'url'   => $base . 'workspace_my_calendar.php?view=list&date=' . date('Y-m-d', (int) $plan['starts_at']) . '&plan=' . (int) $plan['id'] . $day,
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
        'plan_of'        => ws_js_template('Plan of {var:1}', 1),
        'plan_shared_by' => ws_js_template('{var:1} shared this plan with you. Only they can change it.', 1),
        'plan_who'       => lang('Who can see'),
        'plan_private_people' => lang('You and the people you choose see this plan. Only you can change it.'),
        'plan_private_team'   => lang('The whole team sees this plan. Only you can change it.'),
        'plan_only_me'   => lang('Only me'),
        'plan_chosen'    => lang('Chosen team members'),
        'plan_people_hint'  => lang('Choose the team members who may see this plan.'),
        'plan_people_error' => lang('Choose who may see the plan.'),
        'plan_team'      => lang('The whole team'),
        'plan_mail'      => lang('Open the e-mail'),
        // Repeating plans (personal_plan_repeat.php).
        'repeat'           => lang('Repeat'),
        'repeat_none'      => lang('Does not repeat'),
        'repeat_options'   => array(
            'daily'   => lang('Every day'),
            'weekly'  => lang('Every week'),
            'monthly' => lang('Every month'),
            'yearly'  => lang('Every year'),
        ),
        'repeat_every'     => lang('Repeat every'),
        'repeat_unit_daily'   => lang('days'),
        'repeat_unit_weekly'  => lang('weeks'),
        'repeat_unit_monthly' => lang('months'),
        'repeat_unit_yearly'  => lang('years'),
        'repeat_ends'      => lang('Repeat ends'),
        'repeat_end_never' => lang('Never'),
        'repeat_end_until' => lang('On a day'),
        'repeat_end_count' => lang('After a number of times'),
        'repeat_until'     => lang('Last day of the repeat'),
        'repeat_count'     => lang('Number of times'),
        'repeat_this_one'  => lang('This one'),
        'repeat_following' => lang('This and the following'),
        'repeat_change_q'  => lang('Which times of the repeating plan does the change apply to?'),
        'repeat_delete_q'  => lang('Which times of the repeating plan do you want to delete? They cannot be brought back.'),
        'remind_options' => array(
            '0'    => lang('At the start'),
            '15'   => lang('15 minutes before'),
            '60'   => lang('1 hour before'),
            '1440' => lang('1 day before'),
        ),
    );
}
