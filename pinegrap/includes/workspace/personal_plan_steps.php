<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the roadmap of a person's own plan (ws_personal_plan_steps):
 * dated steps with a title, a day and a done mark, drawn as a bar with the
 * plan's progress. A step may be tied to a task ("Make a task"); it then
 * follows the task: done while the task is done.
 *
 * Every read starts from a plan the gate let through (ws_personal_plan(),
 * ws_personal_plan_can_see()); only the owner changes the steps
 * (ws_personal_plan_can_edit()). The task tied to a step stays the team's:
 * its number and status are shown only to a reader who may see the task.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How many steps a roadmap keeps. */
const WS_PERSONAL_PLAN_STEP_MAX = 50;

/**
 * Reads and checks the steps sent by the drawer. Pure. A row with no title
 * is dropped; the order sent is the order kept.
 *
 * @param mixed $steps list of id, title, day (Y-m-d or ''), done
 * @return array array('error' => string) or array('steps' => list of id,
 *               title, day, done)
 */
function ws_personal_plan_steps_clean($steps)
{
    if (is_string($steps)) {
        $steps = json_decode($steps, true);
    }

    $clean = array();

    foreach ((array) $steps as $step) {
        if (!is_array($step)) {
            continue;
        }

        $title = is_scalar($step['title'] ?? null) ? trim((string) $step['title']) : '';

        if ($title === '') {
            continue;
        }

        $day = is_scalar($step['day'] ?? null) ? trim((string) $step['day']) : '';

        if (($day !== '') && !(preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $day, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]))) {
            return array('error' => lang('Choose a valid day for each step.'));
        }

        $id = $step['id'] ?? 0;

        $clean[] = array(
            'id'    => (is_scalar($id) && ctype_digit((string) $id)) ? (int) $id : 0,
            'title' => mb_substr($title, 0, 255),
            'day'   => $day,
            'done'  => !empty($step['done']) && ($step['done'] !== '0'),
        );
    }

    if (count($clean) > WS_PERSONAL_PLAN_STEP_MAX) {
        return array('error' => lang(array('string' => 'A roadmap keeps at most {var:1} steps.', 'vars' => WS_PERSONAL_PLAN_STEP_MAX)));
    }

    return array('steps' => $clean);
}

/**
 * The steps as the drawer reads them, with the state of the tasks they are
 * tied to. Pure.
 *
 * @param array[] $rows  ws_personal_plan_steps rows, in order
 * @param array   $tasks task id => array(status, number, visible): the tasks
 *                       that still exist; visible says the reader may see it
 * @return array[] id, title, day, done, task_id, task_status, task_number,
 *                 follows (the done mark is the task's)
 */
function ws_personal_plan_steps_state($rows, $tasks)
{
    $steps = array();

    foreach ($rows as $row) {
        $task_id = (int) $row['task_id'];
        $task = ($task_id > 0) ? ($tasks[$task_id] ?? null) : null;
        $visible = $task && !empty($task['visible']);
        $day = (string) $row['day'];

        $steps[] = array(
            'id'          => (int) $row['id'],
            'title'       => (string) $row['title'],
            'day'         => (($day === '') || ($day === '0000-00-00')) ? '' : $day,
            // A step tied to a task that still exists is done while the task
            // is; a deleted task leaves the step its own mark.
            'done'        => $task ? ((string) $task['status'] === 'done') : ((int) $row['done_at'] > 0),
            'follows'     => (bool) $task,
            'task_id'     => $visible ? $task_id : 0,
            'task_status' => $visible ? (string) $task['status'] : '',
            'task_number' => $visible ? (string) $task['number'] : '',
        );
    }

    return $steps;
}

/**
 * How far a roadmap has come. Pure.
 *
 * @param array[] $steps ws_personal_plan_steps_state()
 * @return array total, done, percent (whole)
 */
function ws_personal_plan_progress($steps)
{
    $total = count($steps);
    $done = count(array_filter($steps, function ($step) {
        return !empty($step['done']);
    }));

    return array(
        'total'   => $total,
        'done'    => $done,
        'percent' => ($total > 0) ? (int) round($done * 100 / $total) : 0,
    );
}

/**
 * The step rows of plans the gate already let through, by plan.
 *
 * @param int[] $plan_ids
 * @return array plan id => rows in order
 */
function ws_personal_plan_step_rows($plan_ids)
{
    $plan_ids = array_values(array_filter(array_map('intval', (array) $plan_ids)));
    $rows = array_fill_keys($plan_ids, array());

    if (empty($plan_ids) || !ws_personal_plans_ready()) {
        return $rows;
    }

    foreach ((array) db_items("SELECT * FROM ws_personal_plan_steps
        WHERE plan_id IN (" . implode(',', $plan_ids) . ")
        ORDER BY plan_id, position, id") as $row) {
        $rows[(int) $row['plan_id']][] = $row;
    }

    return $rows;
}

/**
 * The tasks the steps are tied to, for ws_personal_plan_steps_state().
 *
 * @param array   $viewer the reader (ws_viewer())
 * @param array[] $rows   step rows
 * @return array task id => status, number, visible
 */
function ws_personal_plan_step_tasks($viewer, $rows)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'task_id')))));
    $tasks = array();

    if (empty($ids)) {
        return $tasks;
    }

    $assignees = ws_task_assignees_map($ids);

    foreach ((array) db_items("SELECT * FROM ws_tasks WHERE id IN (" . implode(',', $ids) . ")") as $task) {
        $tasks[(int) $task['id']] = array(
            'status'  => (string) $task['status'],
            'number'  => ws_task_number((int) $task['id']),
            'visible' => ws_can_see_task($viewer, $task, $assignees[(int) $task['id']] ?? array()),
        );
    }

    return $tasks;
}

/**
 * A plan's roadmap as one reader gets it.
 *
 * @param array $viewer  ws_viewer()
 * @param int   $plan_id
 * @return array|null steps, progress, can_edit; null when the gate says no
 */
function ws_personal_plan_roadmap($viewer, $plan_id)
{
    $plan = ws_personal_plan((int) $viewer['id'], (int) $plan_id);

    if (!$plan) {
        return null;
    }

    $rows = ws_personal_plan_step_rows(array((int) $plan['id']))[(int) $plan['id']] ?? array();
    $steps = ws_personal_plan_steps_state($rows, ws_personal_plan_step_tasks($viewer, $rows));

    return array(
        'plan_id'  => (int) $plan['id'],
        'steps'    => $steps,
        'progress' => ws_personal_plan_progress($steps),
        'can_edit' => ws_personal_plan_can_edit((int) $viewer['id'], $plan),
    );
}

/**
 * Saves the steps of the viewer's own plan: the list sent is the roadmap.
 * A step tied to a task keeps the tie and follows the task.
 *
 * @param array $viewer
 * @param int   $plan_id
 * @param mixed $steps   ws_personal_plan_steps_clean()'s input
 * @return array ok + roadmap, or error
 */
function ws_personal_plan_steps_save($viewer, $plan_id, $steps)
{
    $user_id = (int) $viewer['id'];
    $plan = ws_personal_plan($user_id, (int) $plan_id);

    if (!$plan || !ws_personal_plan_can_edit($user_id, $plan)) {
        return array('error' => lang('This plan cannot be found.'));
    }

    $clean = ws_personal_plan_steps_clean($steps);

    if (isset($clean['error'])) {
        return $clean;
    }

    // A roadmap does not repeat (personal_plan_repeat.php).
    if (!empty($clean['steps']) && function_exists('ws_personal_plan_repeats') && ws_personal_plan_repeats($plan)) {
        return array('error' => lang('A repeating plan has no roadmap.'));
    }

    $plan_id = (int) $plan['id'];
    $now = time();
    $existing = array();

    foreach (ws_personal_plan_step_rows(array($plan_id))[$plan_id] ?? array() as $row) {
        $existing[(int) $row['id']] = $row;
    }

    $kept = array();

    foreach ($clean['steps'] as $position => $step) {
        $old = $existing[$step['id']] ?? null;
        $done_at = $step['done'] ? (($old && ((int) $old['done_at'] > 0)) ? (int) $old['done_at'] : $now) : 0;
        $values = "position = '" . (int) $position . "', title = '" . e($step['title']) . "',
            day = '" . (($step['day'] === '') ? '0000-00-00' : e($step['day'])) . "'";

        // A step that follows its task keeps the mark it had.
        if (!$old || ((int) $old['task_id'] === 0)) {
            $values .= ", done_at = '" . $done_at . "'";
        }

        if ($old) {
            db("UPDATE ws_personal_plan_steps SET " . $values . " WHERE id = '" . (int) $old['id'] . "' AND plan_id = '" . $plan_id . "'");
            $kept[] = (int) $old['id'];
        } else {
            db("INSERT INTO ws_personal_plan_steps SET plan_id = '" . $plan_id . "', task_id = 0, " . $values);
            $kept[] = (int) mysqli_insert_id(db::$con);
        }
    }

    $gone = array_diff(array_keys($existing), $kept);

    if (!empty($gone)) {
        db("DELETE FROM ws_personal_plan_steps WHERE plan_id = '" . $plan_id . "' AND id IN (" . implode(',', array_map('intval', $gone)) . ")");
    }

    db("UPDATE ws_personal_plans SET updated_at = '" . $now . "' WHERE id = '" . $plan_id . "' AND user_id = '" . $user_id . "'");

    return array('ok' => true, 'roadmap' => ws_personal_plan_roadmap($viewer, $plan_id));
}

/**
 * Ties a step of the viewer's own plan to a task they made from it and may
 * see; from then on the step follows the task.
 *
 * @param array $viewer
 * @param int   $plan_id
 * @param int   $step_id
 * @param int   $task_id
 * @return array ok + roadmap, or error
 */
function ws_personal_plan_step_task($viewer, $plan_id, $step_id, $task_id)
{
    $user_id = (int) $viewer['id'];
    $plan = ws_personal_plan($user_id, (int) $plan_id);

    if (!$plan || !ws_personal_plan_can_edit($user_id, $plan)) {
        return array('error' => lang('This plan cannot be found.'));
    }

    $task = ws_task((int) $task_id);

    if (!$task || !ws_can_see_task($viewer, $task)) {
        return array('error' => lang('That task could not be found.'));
    }

    if (!db_value("SELECT id FROM ws_personal_plan_steps WHERE id = '" . (int) $step_id . "' AND plan_id = '" . (int) $plan['id'] . "'")) {
        return array('error' => lang('This step cannot be found.'));
    }

    db("UPDATE ws_personal_plan_steps SET task_id = '" . (int) $task['id'] . "'
        WHERE id = '" . (int) $step_id . "' AND plan_id = '" . (int) $plan['id'] . "'");

    return array('ok' => true, 'roadmap' => ws_personal_plan_roadmap($viewer, (int) $plan['id']));
}

/**
 * Writes each plan's progress on its calendar entries (steps_done,
 * steps_total), for the plans of the days the gate let through.
 *
 * @param array $days   date => day with an events list
 * @param array $viewer ws_viewer()
 * @return array the days
 */
function ws_personal_plans_progress_place($days, $viewer)
{
    $ids = array();

    foreach ($days as $day) {
        foreach ((array) ($day['events'] ?? array()) as $event) {
            if (!empty($event['plan_id'])) {
                $ids[(int) $event['plan_id']] = (int) $event['plan_id'];
            }
        }
    }

    if (empty($ids) || !ws_personal_plans_ready()) {
        return $days;
    }

    // The days hold only what the gate let through; asked again all the same.
    $team = ws_team_ids();
    $seen = array();

    foreach ((array) db_items("SELECT * FROM ws_personal_plans WHERE id IN (" . implode(',', $ids) . ")") as $plan) {
        if (ws_personal_plan_can_see((int) $viewer['id'], $plan, $team)) {
            $seen[] = (int) $plan['id'];
        }
    }

    $rows = ws_personal_plan_step_rows($seen);
    $all = array();

    foreach ($rows as $plan_rows) {
        foreach ($plan_rows as $row) {
            $all[] = $row;
        }
    }

    $tasks = ws_personal_plan_step_tasks($viewer, $all);
    $progress = array();

    foreach ($rows as $plan_id => $plan_rows) {
        if (!empty($plan_rows)) {
            $progress[$plan_id] = ws_personal_plan_progress(ws_personal_plan_steps_state($plan_rows, $tasks));
        }
    }

    foreach ($days as $date => $day) {
        foreach ((array) ($day['events'] ?? array()) as $index => $event) {
            $plan_id = (int) ($event['plan_id'] ?? 0);

            if (isset($progress[$plan_id])) {
                $days[$date]['events'][$index]['steps_done'] = $progress[$plan_id]['done'];
                $days[$date]['events'][$index]['steps_total'] = $progress[$plan_id]['total'];
            }
        }
    }

    return $days;
}

/**
 * The texts of the roadmap (assets/js/workspace_my_calendar_roadmap.js).
 *
 * @return array
 */
function ws_personal_plan_steps_js_strings()
{
    return array(
        'roadmap'          => lang('Roadmap'),
        'roadmap_hint'     => lang('Break the plan into dated steps and tick them off as you go.'),
        'step_add'         => lang('Add a step'),
        'step_title'       => lang('Step'),
        'step_day'         => lang('Day'),
        'step_done'        => lang('Done'),
        'step_remove'      => lang('Remove the step'),
        'step_make_task'   => lang('Make a task'),
        'step_open_task'   => lang('Open the task'),
        'step_follows'     => lang("Follows the task's state"),
        'step_task_linked' => lang('The step now follows the task.'),
        'step_progress'    => ws_js_template('{var:1} of {var:2} steps done', 2),
        'step_no_day'      => lang('No day'),
    );
}
