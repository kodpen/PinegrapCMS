<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the team's roadmap templates (ws_roadmap_templates,
 * ws_roadmap_template_steps): "Save as team template" on a person's own
 * roadmap keeps its title, its time of day and length, and its steps with
 * each day counted from the first step's day. Every team member sees and uses
 * a template; only its maker changes or deletes it. Once the maker's account
 * is gone the template stays for the team, nobody changes it and a manager
 * may delete it.
 *
 * A template keeps no note, customer or people, so it names nobody but its
 * maker. It is never a plan: no plan reader (the calendar, reminders, the
 * API, the gate ws_personal_plan_can_see()) sees it. Using one fills the New
 * plan drawer the way a copy of a plan does; nothing is written until Save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How many templates one person may make. */
const WS_ROADMAP_TEMPLATE_MAX = 200;

/** How many of the team's templates "New roadmap" lists, the latest first. */
const WS_ROADMAP_TEMPLATE_LIST_ROWS = 20;

/** The largest step distance a template keeps, in days (SMALLINT UNSIGNED). */
const WS_ROADMAP_TEMPLATE_OFFSET_MAX = 65535;

/**
 * Whether the database has the template tables (2026.11.0, 11.0.208).
 *
 * @return bool
 */
function ws_roadmap_templates_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_roadmap_templates') && pg_schema_has('ws_roadmap_template_steps')
            && ws_personal_plans_ready();
    }

    return $ready;
}

/**
 * The calendar day number of a Y-m-d day, or null. Pure.
 *
 * @param string $day
 * @return int|null
 */
function ws_roadmap_template_day_number($day)
{
    $day = (string) $day;

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }

    return (int) floor(gmmktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]) / 86400);
}

/**
 * What a template keeps of a plan and its roadmap: the title, whether it is
 * all day, its time of day and length, and the steps with a title, each day
 * counted from the earliest step's day. The note, the customer, the people
 * and a step's done mark and task are left out. Pure.
 *
 * @param array   $plan  a plan row
 * @param array[] $steps its step rows, in order
 * @return array template (title, all_day, start_minute, length_minutes),
 *               steps (title, day_offset, has_day)
 */
function ws_roadmap_template_from_plan($plan, $steps)
{
    $starts = (int) $plan['starts_at'];
    $ends = (int) $plan['ends_at'];
    $all_day = ((int) $plan['all_day'] === 1);

    if ($all_day) {
        // In calendar days, so a clock change inside the plan counts no hour.
        $first = ws_roadmap_template_day_number(date('Y-m-d', $starts));
        $last = ws_roadmap_template_day_number(date('Y-m-d', max($starts, $ends - 1)));
        $length = (max(0, $last - $first) + 1) * 1440;
        $minute = 0;
    } else {
        $length = (int) max(0, round(($ends - $starts) / 60));
        $minute = (int) date('G', $starts) * 60 + (int) date('i', $starts);
    }

    $kept = array();
    $base = null;

    foreach ($steps as $step) {
        $title = trim((string) $step['title']);

        if ($title === '') {
            continue;
        }

        $at = ws_roadmap_template_day_number($step['day'] ?? '');
        $kept[] = array('title' => mb_substr($title, 0, 255), 'at' => $at);

        if (($at !== null) && (($base === null) || ($at < $base))) {
            $base = $at;
        }
    }

    $out = array();

    foreach ($kept as $step) {
        $out[] = array(
            'title'      => $step['title'],
            'day_offset' => ($step['at'] === null) ? 0 : min(WS_ROADMAP_TEMPLATE_OFFSET_MAX, $step['at'] - $base),
            'has_day'    => ($step['at'] === null) ? 0 : 1,
        );
    }

    return array(
        'template' => array(
            'title'          => mb_substr(trim((string) $plan['title']), 0, 255),
            'all_day'        => $all_day ? 1 : 0,
            'start_minute'   => min(1439, max(0, $minute)),
            'length_minutes' => $length,
        ),
        'steps'    => $out,
    );
}

/**
 * A plan made from a template, starting on a day: the first step on that
 * day and every other step as many days after it as the template counts.
 * Nothing done, no note, no customer, no reminder. Pure.
 *
 * @param array   $template ws_roadmap_templates row
 * @param array[] $steps    its ws_roadmap_template_steps rows, in order
 * @param string  $start    the plan's first day, Y-m-d
 * @return array plan (title, note, starts_at, ends_at, all_day), steps
 *               (title, day)
 */
function ws_roadmap_template_shift($template, $steps, $start)
{
    $midnight = (int) strtotime($start . ' 00:00:00');
    $length = max(0, (int) $template['length_minutes']);

    if ((int) $template['all_day'] === 1) {
        $days = max(1, (int) ceil($length / 1440));
        $starts = $midnight;
        $ends = (int) strtotime($start . ' 00:00:00 +' . $days . ' days');
    } else {
        $minute = min(1439, max(0, (int) $template['start_minute']));
        $starts = (int) strtotime(sprintf('%s %02d:%02d:00', $start, intdiv($minute, 60), $minute % 60));
        $ends = $starts + $length * 60;
    }

    $out = array();

    foreach ($steps as $step) {
        $out[] = array(
            'title' => (string) $step['title'],
            'day'   => ((int) $step['has_day'] === 1) ? date('Y-m-d', (int) strtotime($start . ' 12:00:00 +' . (int) $step['day_offset'] . ' days')) : '',
        );
    }

    return array(
        'plan'  => array(
            'title'     => (string) $template['title'],
            'note'      => '',
            'starts_at' => $starts,
            'ends_at'   => $ends,
            'all_day'   => ((int) $template['all_day'] === 1) ? 1 : 0,
        ),
        'steps' => $out,
    );
}

/**
 * May this person change the template? Its maker alone, and only while a
 * team member. Pure.
 *
 * @param int   $user_id  the reader
 * @param array $template ws_roadmap_templates row
 * @param bool  $member   whether the reader is on the team
 * @return bool
 */
function ws_roadmap_template_can_change($user_id, $template, $member)
{
    return $member && ((int) $user_id > 0) && is_array($template) && ((int) $template['user_id'] === (int) $user_id);
}

/**
 * May this person delete the template? Its maker; once the maker's account
 * is gone, a manager (roles 0 to 2) as well. Pure.
 *
 * @param int   $user_id    the reader
 * @param int   $role       the reader's role
 * @param array $template   ws_roadmap_templates row
 * @param bool  $member     whether the reader is on the team
 * @param bool  $maker_gone whether the maker's account no longer exists
 * @return bool
 */
function ws_roadmap_template_can_delete($user_id, $role, $template, $member, $maker_gone)
{
    if (ws_roadmap_template_can_change($user_id, $template, $member)) {
        return true;
    }

    return $member && ((int) $user_id > 0) && is_array($template) && $maker_gone && ((int) $role <= 2);
}

/**
 * One template row, or null.
 *
 * @param int $template_id
 * @return array|null
 */
function ws_roadmap_template_row($template_id)
{
    if (!ws_roadmap_templates_ready() || ((int) $template_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_roadmap_templates WHERE id = '" . (int) $template_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * A template's steps, in order.
 *
 * @param int $template_id
 * @return array[]
 */
function ws_roadmap_template_steps($template_id)
{
    return (array) db_items("SELECT * FROM ws_roadmap_template_steps WHERE template_id = '" . (int) $template_id . "' ORDER BY position, id");
}

/**
 * The team's templates as a team member reads them, the latest first.
 *
 * @param array $viewer
 * @return array[] id, title, steps (count), maker (name), mine, can_delete
 */
function ws_roadmap_templates_list($viewer)
{
    if (empty($viewer['member']) || !ws_roadmap_templates_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT t.*, (SELECT COUNT(*) FROM ws_roadmap_template_steps s WHERE s.template_id = t.id) AS step_count
        FROM ws_roadmap_templates t ORDER BY t.updated_at DESC, t.id DESC LIMIT " . WS_ROADMAP_TEMPLATE_LIST_ROWS);
    $people = ws_people(array_map(function ($row) { return (int) $row['user_id']; }, $rows));
    $out = array();

    foreach ($rows as $row) {
        $maker = $people[(int) $row['user_id']] ?? null;
        $gone = !$maker || !empty($maker['gone']);

        $out[] = array(
            'id'         => (int) $row['id'],
            'title'      => (string) $row['title'],
            'steps'      => (int) $row['step_count'],
            'maker'      => $gone ? lang('Former member') : (string) $maker['name'],
            'mine'       => ws_roadmap_template_can_change((int) $viewer['id'], $row, true),
            'can_delete' => ws_roadmap_template_can_delete((int) $viewer['id'], (int) ($viewer['role'] ?? 3), $row, true, $gone),
        );
    }

    return $out;
}

/**
 * "Save as team template": keeps the viewer's own saved plan and its roadmap
 * as a new template, or in place of one of the viewer's own templates.
 *
 * @param array $viewer
 * @param int   $plan_id     the viewer's own plan
 * @param int   $template_id 0 for a new template
 * @return array ok + template_id, or error
 */
function ws_roadmap_template_save($viewer, $plan_id, $template_id = 0)
{
    $user_id = (int) $viewer['id'];

    if (empty($viewer['member']) || !ws_roadmap_templates_ready()) {
        return array('error' => lang('Templates are not available.'));
    }

    $plan = ws_personal_plan($user_id, (int) $plan_id);

    if (!$plan || !ws_personal_plan_can_edit($user_id, $plan)) {
        return array('error' => lang('This plan cannot be found.'));
    }

    $made = ws_roadmap_template_from_plan($plan, ws_personal_plan_step_rows(array((int) $plan['id']))[(int) $plan['id']] ?? array());

    if (empty($made['steps'])) {
        return array('error' => lang('Add a step to the roadmap first.'));
    }

    $template_id = (int) $template_id;
    $now = time();
    $t = $made['template'];
    $values = "title = '" . e($t['title']) . "', all_day = '" . (int) $t['all_day'] . "', start_minute = '" . (int) $t['start_minute'] . "',
        length_minutes = '" . (int) $t['length_minutes'] . "', updated_at = '" . $now . "'";

    if ($template_id > 0) {
        if (!ws_roadmap_template_can_change($user_id, ws_roadmap_template_row($template_id), true)) {
            return array('error' => lang('Only the template\'s maker can change it.'));
        }

        db("UPDATE ws_roadmap_templates SET " . $values . " WHERE id = '" . $template_id . "' AND user_id = '" . $user_id . "'");
        db("DELETE FROM ws_roadmap_template_steps WHERE template_id = '" . $template_id . "'");
    } else {
        if ((int) db_value("SELECT COUNT(*) FROM ws_roadmap_templates WHERE user_id = '" . $user_id . "'") >= WS_ROADMAP_TEMPLATE_MAX) {
            return array('error' => lang(array('string' => 'You can keep at most {var:1} templates.', 'vars' => WS_ROADMAP_TEMPLATE_MAX)));
        }

        db("INSERT INTO ws_roadmap_templates SET user_id = '" . $user_id . "', created_at = '" . $now . "', " . $values);
        $template_id = (int) mysqli_insert_id(db::$con);
    }

    if ($template_id <= 0) {
        return array('error' => lang('The template could not be saved.'));
    }

    $rows = array();

    foreach ($made['steps'] as $position => $step) {
        $rows[] = "('" . $template_id . "', '" . (int) $position . "', '" . e($step['title']) . "', '" . (int) $step['day_offset'] . "', '" . (int) $step['has_day'] . "')";
    }

    db("INSERT INTO ws_roadmap_template_steps (template_id, position, title, day_offset, has_day) VALUES " . implode(', ', $rows));

    return array('ok' => true, 'template_id' => $template_id);
}

/**
 * Deletes a template: its maker, or a manager once the maker's account is
 * gone.
 *
 * @param array $viewer
 * @param int   $template_id
 * @return array ok, or error
 */
function ws_roadmap_template_delete($viewer, $template_id)
{
    $template = ws_roadmap_template_row($template_id);

    if (!$template || empty($viewer['member'])) {
        return array('error' => lang('This template cannot be found.'));
    }

    $maker = ws_people(array((int) $template['user_id']))[(int) $template['user_id']] ?? null;
    $gone = !$maker || !empty($maker['gone']);

    if (!ws_roadmap_template_can_delete((int) $viewer['id'], (int) ($viewer['role'] ?? 3), $template, true, $gone)) {
        return array('error' => lang('Only the template\'s maker can delete it.'));
    }

    db("DELETE FROM ws_roadmap_template_steps WHERE template_id = '" . (int) $template['id'] . "'");
    db("DELETE FROM ws_roadmap_templates WHERE id = '" . (int) $template['id'] . "'");

    return array('ok' => true);
}

/**
 * A team template used under "New roadmap": what the New plan drawer is
 * filled with, the first step today. Nothing is written: the plan exists
 * only once the drawer is saved, and it is the person's own, Only me.
 *
 * @param array $viewer
 * @param int   $template_id
 * @return array ok + draft (title, note, start, end, all_day, start_time,
 *               end_time, steps of title and day), or error
 */
function ws_roadmap_template_draft($viewer, $template_id)
{
    $template = ws_roadmap_template_row($template_id);

    if (!$template || empty($viewer['member'])) {
        return array('error' => lang('This template cannot be found.'));
    }

    $made = ws_roadmap_template_shift($template, ws_roadmap_template_steps((int) $template['id']), date('Y-m-d'));
    $shown = ws_personal_plan_present(array('id' => 0, 'remind_at' => 0) + $made['plan']);

    return array('ok' => true, 'draft' => array(
        'title'      => $shown['title'],
        'note'       => '',
        'start'      => $shown['start'],
        'end'        => $shown['end'],
        'all_day'    => $shown['all_day'] ? 1 : 0,
        'start_time' => $shown['start_time'],
        'end_time'   => $shown['end_time'],
        'steps'      => $made['steps'],
    ));
}

/**
 * The texts of the templates on the roadmap and under "New roadmap".
 *
 * @return array
 */
function ws_roadmap_template_js_strings()
{
    return array(
        'template_save'        => lang('Save as team template'),
        'template_save_new'    => lang('As a new template'),
        'template_save_over'   => lang('In place of: {1}'),
        'template_saved'       => lang('Saved as a team template. The team can use it under New roadmap on a customer\'s card.'),
        'steps_move_following' => lang('Move the following steps too'),
    );
}
