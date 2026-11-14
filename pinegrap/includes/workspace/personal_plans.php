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

    // Their own, those opened to the team, and those naming them; the gate
    // then decides each one.
    foreach ((array) db_items("SELECT * FROM ws_personal_plans
        WHERE (user_id = '" . (int) $user_id . "' OR visibility = 'team'
            OR (visibility = 'people' AND id IN (SELECT plan_id FROM ws_personal_plan_people WHERE user_id = '" . (int) $user_id . "')))
        AND starts_at < '" . (int) $to_ts . "' AND ends_at > '" . (int) $from_ts . "'
        ORDER BY starts_at, id
        LIMIT 1000") as $plan) {
        if (ws_personal_plan_can_see($user_id, $plan, $team)) {
            $plans[] = $plan;
        }
    }

    return $plans;
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

    // Only team members other than the owner can be chosen: a guest never.
    $people = array_values(array_diff(array_intersect($clean['people'], ws_team_ids()), array($user_id)));

    if (($clean['visibility'] === 'people') && empty($people)) {
        return array('error' => lang('Choose who may see the plan.'));
    }

    // A reminder already gone stays gone unless its moment moved.
    $values = "title = '" . e($clean['title']) . "', note = '" . e($clean['note']) . "',
        starts_at = '" . $clean['starts_at'] . "', ends_at = '" . $clean['ends_at'] . "', all_day = '" . $clean['all_day'] . "',
        remind_at = '" . $clean['remind_at'] . "', visibility = '" . e($clean['visibility']) . "', updated_at = '" . $now . "'";

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
        'visibility' => (string) ($plan['visibility'] ?? 'me'),
        'owner_id'   => (int) ($plan['user_id'] ?? 0),
    );
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
        'remind_options' => array(
            '0'    => lang('At the start'),
            '15'   => lang('15 minutes before'),
            '60'   => lang('1 hour before'),
            '1440' => lang('1 day before'),
        ),
    );
}
