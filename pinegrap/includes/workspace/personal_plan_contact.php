<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a person's own plan made for a customer
 * (ws_personal_plans.contact_id): the customer it names, the roadmaps on the
 * customer's card and in its history, and "New roadmap" there, empty or a
 * copy of one of the person's own plans.
 *
 * A plan stays its owner's: the card and the history show it only to those
 * the gate lets see it (ws_personal_plan_can_see()), and nobody else learns
 * from the card that it exists. A contact is named on a plan only by its
 * owner and only when they may open it (ws_viewer_contacts_open()), as a
 * visit names one. The customer never sees a roadmap on the site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How many roadmaps the card lists, and how many plans "New roadmap" offers to copy. */
const WS_PERSONAL_PLAN_CARD_ROWS = 5;

/**
 * Names a customer on the viewer's own plan, or clears it (0). A new contact
 * must be one the viewer may open; the one the plan names already may stay.
 *
 * @param array $viewer
 * @param int   $plan_id
 * @param int   $contact_id 0 for none
 * @return array ok + contact, or error and field
 */
function ws_personal_plan_contact_save($viewer, $plan_id, $contact_id)
{
    $user_id = (int) $viewer['id'];
    $plan = ws_personal_plan($user_id, (int) $plan_id);

    if (!$plan || !ws_personal_plan_can_edit($user_id, $plan)) {
        return array('error' => lang('This plan cannot be found.'));
    }

    $pick = ws_event_contact_pick('visit', array('contact_id' => (int) $contact_id), $plan, ws_viewer_contacts_open($viewer, array((int) $contact_id)));

    if (!$pick['ok'] || (($pick['contact_id'] > 0) && !db_value("SELECT id FROM contacts WHERE id = '" . (int) $pick['contact_id'] . "'"))) {
        return array('error' => lang('That customer could not be found.'), 'field' => 'contact_id');
    }

    db("UPDATE ws_personal_plans SET contact_id = '" . (int) $pick['contact_id'] . "', updated_at = '" . time() . "'
        WHERE id = '" . (int) $plan['id'] . "' AND user_id = '" . $user_id . "'");
    $plan['contact_id'] = (int) $pick['contact_id'];

    return array('ok' => true, 'contact' => ws_event_contact_of($viewer, $plan));
}

/**
 * The customer a plan names, as the drawer shows it (ws_event_contact_of()):
 * hidden when the reader may not open the contact.
 *
 * @param array $viewer
 * @param int   $plan_id
 * @return array|null id, label, hidden; null when the plan names nobody or
 *                    the gate says no
 */
function ws_personal_plan_contact($viewer, $plan_id)
{
    $plan = ws_personal_plan((int) $viewer['id'], (int) $plan_id);

    return $plan ? ws_event_contact_of($viewer, $plan) : null;
}

/**
 * The plans naming a contact that the reader may see, latest start first.
 *
 * @param array $viewer
 * @param int   $contact_id
 * @param int   $before     only plans starting before this moment; 0 for any
 * @param int   $limit
 * @return array[] ws_personal_plans rows
 */
function ws_personal_plans_of_contact($viewer, $contact_id, $before, $limit)
{
    $user_id = (int) $viewer['id'];
    $contact_id = (int) $contact_id;

    if (($contact_id <= 0) || ($user_id <= 0) || empty($viewer['member']) || !ws_personal_plans_ready()) {
        return array();
    }

    $team = ws_team_ids();
    $plans = array();

    // The reader's own, those opened to the team and those naming the reader;
    // the gate then decides each one.
    foreach ((array) db_items("SELECT * FROM ws_personal_plans
        WHERE contact_id = '" . $contact_id . "'
            AND (user_id = '" . $user_id . "' OR visibility = 'team'
                OR (visibility = 'people' AND id IN (SELECT plan_id FROM ws_personal_plan_people WHERE user_id = '" . $user_id . "')))"
            . (($before > 0) ? " AND starts_at < '" . (int) $before . "'" : '') . "
        ORDER BY starts_at DESC, id DESC
        LIMIT " . max(1, (int) $limit)) as $plan) {
        if (ws_personal_plan_can_see($user_id, $plan, $team)) {
            $plans[] = $plan;
        }
    }

    return $plans;
}

/**
 * The plans as the card and the history list them: title, start, the
 * owner's name for somebody else's plan, the roadmap's progress.
 *
 * @param array   $viewer
 * @param array[] $plans rows the gate let through
 * @return array[] id, title, starts_at, all_day, owner (name, '' for the
 *                 reader's own), done, total
 */
function ws_personal_plans_listed($viewer, $plans)
{
    if (empty($plans)) {
        return array();
    }

    $owners = array_values(array_unique(array_map('intval', array_column($plans, 'user_id'))));
    $names = array();

    foreach (ws_people($owners) as $id => $brief) {
        $names[(int) $id] = (string) $brief['name'];
    }

    $rows = ws_personal_plan_step_rows(array_column($plans, 'id'));
    $all = array();

    foreach ($rows as $plan_rows) {
        foreach ($plan_rows as $row) {
            $all[] = $row;
        }
    }

    $tasks = ws_personal_plan_step_tasks($viewer, $all);
    $out = array();

    foreach ($plans as $plan) {
        $progress = ws_personal_plan_progress(ws_personal_plan_steps_state($rows[(int) $plan['id']] ?? array(), $tasks));
        $mine = ((int) $plan['user_id'] === (int) $viewer['id']);

        $out[] = array(
            'id'        => (int) $plan['id'],
            'title'     => (string) $plan['title'],
            'starts_at' => (int) $plan['starts_at'],
            'all_day'   => ((int) $plan['all_day'] === 1),
            'owner'     => $mine ? '' : (string) ($names[(int) $plan['user_id']] ?? ''),
            'done'      => $progress['done'],
            'total'     => $progress['total'],
        );
    }

    return $out;
}

/**
 * Where a plan opens: My calendar, on its day, with its drawer.
 *
 * @param string $base the panel's address, ending in /
 * @param int    $plan_id
 * @param int    $starts_at
 * @return string
 */
function ws_personal_plan_url($base, $plan_id, $starts_at)
{
    return $base . 'workspace_my_calendar.php?view=list&date=' . date('Y-m-d', (int) $starts_at) . '&plan=' . (int) $plan_id;
}

/**
 * The roadmaps section of the customer card. Pure.
 *
 * @param array[] $listed ws_personal_plans_listed()
 * @param string  $base
 * @return array[] id, title, date_text, progress_text, owner_text, url
 */
function ws_customer_card_roadmaps($listed, $base)
{
    $out = array();

    foreach ((array) $listed as $plan) {
        $out[] = array(
            'id'            => (int) $plan['id'],
            'title'         => (string) $plan['title'],
            'date_text'     => ws_customer_date_text((int) $plan['starts_at']),
            'progress_text' => ($plan['total'] > 0) ? $plan['done'] . '/' . $plan['total'] : '',
            'owner_text'    => ($plan['owner'] !== '') ? lang(array('string' => 'Plan of {var:1}', 'vars' => $plan['owner'])) : '',
            'url'           => ws_personal_plan_url($base, $plan['id'], $plan['starts_at']),
        );
    }

    return $out;
}

/**
 * What "New roadmap" on the card offers the reader: an empty plan for the
 * customer and a copy of one of their own latest plans. Only a team member
 * who may open the contact is offered it.
 *
 * @param array  $viewer
 * @param array  $identity ws_customer_identity()
 * @param string $base
 * @return array|null contact_id, new_url, copies (id, title), templates
 *                    (ws_roadmap_templates_list()); null when
 *                    nothing is offered
 */
function ws_customer_card_roadmap_offer($viewer, $identity, $base)
{
    $contact_id = (int) ($identity['contact_id'] ?? 0);

    if (($contact_id <= 0) || empty($viewer['member']) || !ws_personal_plans_ready()
        || !in_array($contact_id, ws_viewer_contacts_open($viewer, array($contact_id)), true)) {
        return null;
    }

    $copies = array();

    foreach ((array) db_items("SELECT * FROM ws_personal_plans WHERE user_id = '" . (int) $viewer['id'] . "'
        ORDER BY updated_at DESC, id DESC LIMIT " . WS_PERSONAL_PLAN_CARD_ROWS) as $plan) {
        if (ws_personal_plan_can_edit((int) $viewer['id'], $plan)) {
            $copies[] = array('id' => (int) $plan['id'], 'title' => (string) $plan['title']);
        }
    }

    return array(
        'contact_id' => $contact_id,
        'new_url'    => $base . 'workspace_my_calendar.php?view=list&new_plan=1&contact=' . $contact_id,
        'copies'     => $copies,
        'templates'  => ws_roadmap_templates_list($viewer),
    );
}

/**
 * A copy of a plan moved to start on a new day: its length, its time of day
 * and its steps' days keep their distance to the start; nothing is done,
 * no task is tied, no reminder and nobody else sees it. Pure.
 *
 * @param array   $plan  ws_personal_plans row
 * @param array[] $steps its ws_personal_plan_steps rows, in order
 * @param string  $start the copy's first day, Y-m-d
 * @return array plan (title, note, starts_at, ends_at, all_day), steps
 *               (title, day)
 */
function ws_personal_plan_copy_shift($plan, $steps, $start)
{
    $first = strtotime(date('Y-m-d', (int) $plan['starts_at']) . ' 00:00:00');
    $days = (int) round((strtotime($start . ' 00:00:00') - $first) / 86400);
    $move = function ($ts) use ($days) {
        // By calendar days, so a time of day stays across a clock change.
        return (int) strtotime(date('Y-m-d H:i:s', (int) $ts) . ' ' . (($days >= 0) ? '+' : '') . $days . ' days');
    };
    $out = array();

    foreach ($steps as $step) {
        $day = (string) $step['day'];

        $out[] = array(
            'title' => (string) $step['title'],
            'day'   => (($day === '') || ($day === '0000-00-00')) ? '' : date('Y-m-d', $move(strtotime($day . ' 12:00:00'))),
        );
    }

    return array(
        'plan'  => array(
            'title'     => (string) $plan['title'],
            'note'      => (string) $plan['note'],
            'starts_at' => $move($plan['starts_at']),
            'ends_at'   => $move($plan['ends_at']),
            'all_day'   => (int) $plan['all_day'],
        ),
        'steps' => $out,
    );
}

/**
 * "Copy one of my plans" under New roadmap: what the New plan drawer is
 * filled with, a copy of the viewer's own plan moved to start today. Nothing
 * is written: the plan exists only once the drawer is saved.
 *
 * @param array $viewer
 * @param int   $plan_id the viewer's own plan
 * @return array ok + draft (title, note, start, end, all_day, start_time,
 *               end_time, steps of title and day), or error
 */
function ws_personal_plan_copy_draft($viewer, $plan_id)
{
    $user_id = (int) $viewer['id'];
    $plan = ws_personal_plan($user_id, (int) $plan_id);

    if (!$plan || !ws_personal_plan_can_edit($user_id, $plan) || empty($viewer['member'])) {
        return array('error' => lang('This plan cannot be found.'));
    }

    $rows = ws_personal_plan_step_rows(array((int) $plan['id']))[(int) $plan['id']] ?? array();
    $copy = ws_personal_plan_copy_shift($plan, $rows, date('Y-m-d'));
    $shown = ws_personal_plan_present(array('id' => 0, 'remind_at' => 0) + $copy['plan']);

    return array('ok' => true, 'draft' => array(
        'title'      => $shown['title'],
        'note'       => $shown['note'],
        'start'      => $shown['start'],
        'end'        => $shown['end'],
        'all_day'    => $shown['all_day'] ? 1 : 0,
        'start_time' => $shown['start_time'],
        'end_time'   => $shown['end_time'],
        'steps'      => $copy['steps'],
    ));
}

/**
 * A plan as a contact's data export lists it: its title, days, owner and
 * roadmap steps. Pure.
 *
 * @param array   $plan  ws_personal_plans row
 * @param string  $owner the owner's name
 * @param array[] $steps its ws_personal_plan_steps rows, in order
 * @return array
 */
function ws_personal_plan_export_row($plan, $owner, $steps)
{
    $all_day = ((int) $plan['all_day'] === 1);
    // An all-day plan ends at the midnight after its last day.
    $last = $all_day ? (int) $plan['ends_at'] - 1 : (int) $plan['ends_at'];
    $format = $all_day ? 'Y-m-d' : 'Y-m-d H:i';
    $listed = array();

    foreach ((array) $steps as $step) {
        $day = (string) $step['day'];
        $listed[] = (string) $step['title'] . ((($day !== '') && ($day !== '0000-00-00')) ? ' (' . $day . ')' : '');
    }

    return array(
        'record' => 'plan',
        'id'     => (int) $plan['id'],
        'title'  => (string) $plan['title'],
        'starts' => date($format, (int) $plan['starts_at']),
        'ends'   => date($format, max((int) $plan['starts_at'], $last)),
        'owner'  => (string) $owner,
        'steps'  => implode('; ', $listed),
    );
}

/**
 * The plans naming the person's contacts that a data export lists: only
 * those shared with the whole team. A plan kept to its owner or to chosen
 * team members is never listed, whoever runs the export. Its note,
 * reminder and the people it names stay out.
 *
 * @param int[] $contact_ids
 * @param int   $limit       pg_privacy_export_row_limit()
 * @return array rows, truncated
 */
function ws_personal_plans_export($contact_ids, $limit)
{
    $contact_ids = array_values(array_filter(array_map('intval', (array) $contact_ids)));

    if (empty($contact_ids) || !ws_personal_plans_ready()) {
        return array('rows' => array(), 'truncated' => false);
    }

    $plans = (array) db_items("SELECT * FROM ws_personal_plans
        WHERE contact_id IN (" . implode(',', $contact_ids) . ") AND visibility = 'team'
        ORDER BY starts_at, id
        LIMIT " . ((int) $limit + 1));
    $truncated = count($plans) > (int) $limit;
    $plans = array_slice($plans, 0, (int) $limit);
    $names = array();
    $owners = array_values(array_unique(array_map('intval', array_column($plans, 'user_id'))));

    if (!empty($owners)) {
        foreach ((array) db_items("SELECT u.user_id, u.user_username, c.first_name, c.last_name FROM user u
            LEFT JOIN contacts c ON c.id = u.user_contact WHERE u.user_id IN (" . implode(',', $owners) . ")") as $user) {
            // First and last name of the user's contact, else the username.
            $name = trim(trim((string) $user['first_name']) . ' ' . trim((string) $user['last_name']));
            $names[(int) $user['user_id']] = ($name !== '') ? $name : (string) $user['user_username'];
        }
    }

    $steps = ws_personal_plan_step_rows(array_column($plans, 'id'));
    $rows = array();

    foreach ($plans as $plan) {
        // The rule again, row by row: nothing but a whole-team plan leaves.
        if ((string) $plan['visibility'] !== 'team') {
            continue;
        }

        $rows[] = ws_personal_plan_export_row($plan, $names[(int) $plan['user_id']] ?? '', $steps[(int) $plan['id']] ?? array());
    }

    return array('rows' => $rows, 'truncated' => $truncated);
}

/**
 * The texts of the customer field on the plan drawer and of "New roadmap".
 *
 * @return array
 */
function ws_personal_plan_contact_js_strings()
{
    return array(
        'plan_customer'      => lang('Customer (optional)'),
        'plan_customer_help' => lang('The plan shows on the customer\'s card and history, only to those who may see the plan.'),
    ) + ws_roadmap_template_js_strings();
}
