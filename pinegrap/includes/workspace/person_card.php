<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what a channel shows about a person on their name or picture:
 * panel role, job title, departments with their leads, working day, away
 * days (leaves on the plan and the mail away range), local time, and the
 * last seen only for a manager of the channel. Read for a team member who
 * reads the channel; a guest is never sent any of it.
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
 * How many days ahead the card looks for away days.
 */
define('WS_PERSON_CARD_AWAY_DAYS', 90);

/**
 * How many away ranges the card lists.
 */
define('WS_PERSON_CARD_AWAY_MAX', 5);

/**
 * The panel actions this file answers.
 *
 * @return string[]
 */
function ws_person_card_actions()
{
    return array('ws_person_card');
}

/**
 * A working day: its minutes, in the planning screens' words, and its days
 * of the week as ISO numbers (Monday 1). Pure but for lang().
 *
 * @param int $day_minutes
 * @param int $workdays    Monday = bit 0
 * @return array minutes, hours, days
 */
function ws_person_card_workday($day_minutes, $workdays)
{
    $days = array();

    for ($bit = 0; $bit < 7; $bit++) {
        if (((int) $workdays & (1 << $bit)) !== 0) {
            $days[] = $bit + 1;
        }
    }

    return array(
        'minutes' => max(0, (int) $day_minutes),
        'hours'   => ws_minutes_label((int) $day_minutes),
        'days'    => $days,
    );
}

/**
 * A person's away days from today on: the leaves on the plan that apply to
 * them (their own, their department's, the company's) and their mail away
 * range, which carries no text (the reply is theirs). A mail range the plan
 * already shows with the same days is not listed twice. Pure.
 *
 * @param array[]    $events      ws_events_between() rows
 * @param int        $user_id
 * @param int[]      $departments the person's departments
 * @param string     $today       Y-m-d
 * @param array|null $mail_away   ws_mail_away()
 * @param int        $max
 * @return array[] start, end (Y-m-d), now, source (plan | mail), title
 */
function ws_person_card_away($events, $user_id, $departments, $today, $mail_away, $max = WS_PERSON_CARD_AWAY_MAX)
{
    $out = array();
    $seen = array();
    $today = (string) $today;

    foreach ((array) $events as $event) {
        if (((string) ($event['kind'] ?? '') !== 'leave') || !ws_event_applies($event, (int) $user_id, array_map('intval', (array) $departments))) {
            continue;
        }

        $start = date('Y-m-d', (int) $event['starts_at']);
        $end = date('Y-m-d', (int) $event['ends_at']);

        if (($end < $today) || isset($seen[$start . '|' . $end])) {
            continue;
        }

        $seen[$start . '|' . $end] = true;
        $out[] = array('start' => $start, 'end' => $end, 'now' => ($start <= $today), 'source' => 'plan', 'title' => (string) ($event['title'] ?? ''));
    }

    if (is_array($mail_away) && ((string) $mail_away['end'] >= $today) && !isset($seen[$mail_away['start'] . '|' . $mail_away['end']])) {
        $out[] = array('start' => (string) $mail_away['start'], 'end' => (string) $mail_away['end'], 'now' => ((string) $mail_away['start'] <= $today), 'source' => 'mail', 'title' => '');
    }

    usort($out, function ($a, $b) {
        return strcmp($a['start'], $b['start']) ?: strcmp($a['end'], $b['end']);
    });

    return array_slice($out, 0, max(0, (int) $max));
}

/**
 * A person's departments, primary first: name, colour, whether they lead
 * it, and its other leads by name. An archived or unknown one is left out.
 * Pure.
 *
 * @param int     $user_id
 * @param int[]   $order       ws_user_department_ids(), primary first
 * @param array[] $departments ws_departments(true)
 * @param array   $names       user id => name of the leads
 * @return array[] id, name, color, lead, leads (id, name)
 */
function ws_person_card_departments($user_id, $order, $departments, $names)
{
    $out = array();

    foreach ((array) $order as $department_id) {
        $department = $departments[(int) $department_id] ?? null;

        if (!is_array($department)) {
            continue;
        }

        $leads = array();

        foreach ((array) ($department['leads'] ?? array()) as $lead_id) {
            if (((int) $lead_id !== (int) $user_id) && isset($names[(int) $lead_id])) {
                $leads[] = array('id' => (int) $lead_id, 'name' => (string) $names[(int) $lead_id]);
            }
        }

        $out[] = array(
            'id'    => (int) $department['id'],
            'name'  => (string) $department['name'],
            'color' => (string) $department['color'],
            'lead'  => in_array((int) $user_id, array_map('intval', (array) ($department['leads'] ?? array())), true),
            'leads' => $leads,
        );
    }

    return $out;
}

/**
 * A person's local time: their own zone (user.timezone), else the site's;
 * an unknown name counts as none. `other` when it is not the site's. Pure.
 *
 * @param string $timezone the person's zone, '' for none
 * @param string $site     the site's zone
 * @param int    $now
 * @return array zone, time (H:i), other
 */
function ws_person_card_clock($timezone, $site, $now)
{
    $known = DateTimeZone::listIdentifiers();
    $site = in_array((string) $site, $known, true) ? (string) $site : 'UTC';
    $zone = in_array((string) $timezone, $known, true) ? (string) $timezone : $site;
    $date = new DateTime('@' . (int) $now);
    $date->setTimezone(new DateTimeZone($zone));

    return array('zone' => $zone, 'time' => $date->format('H:i'), 'other' => ($zone !== $site));
}

/**
 * The card of a team member, read in a channel the reader reads. Null for
 * anyone who is not on the team (a former member, a guest, an application).
 *
 * @param array $viewer
 * @param array $channel
 * @param int   $user_id
 * @param int   $now
 * @return array|null
 */
function ws_person_card($viewer, $channel, $user_id, $now)
{
    $user_id = (int) $user_id;

    if (($user_id <= 0) || !ws_is_team_member($user_id)) {
        return null;
    }

    $people = ws_people(array($user_id));
    $person = $people[$user_id] ?? null;

    if (!is_array($person) || !empty($person['gone'])) {
        return null;
    }

    $departments = ws_departments(true);
    $order = ws_user_department_ids($user_id);
    $lead_ids = array();

    foreach ($order as $department_id) {
        foreach ((array) ($departments[$department_id]['leads'] ?? array()) as $lead_id) {
            $lead_ids[(int) $lead_id] = (int) $lead_id;
        }
    }

    $names = array();

    foreach (ws_people(array_values($lead_ids)) as $id => $lead) {
        if (empty($lead['gone'])) {
            $names[(int) $id] = (string) $lead['name'];
        }
    }

    $capacity = ws_capacity($user_id);
    $today = date('Y-m-d', (int) $now);
    $events = ws_events_between($today, date('Y-m-d', (int) $now + WS_PERSON_CARD_AWAY_DAYS * 86400));
    $mail_away = function_exists('ws_mail_away') ? ws_mail_away($user_id) : null;
    $zone = (string) db_value("SELECT timezone FROM user WHERE user_id = '" . $user_id . "'");
    // An old zone name is read as its current one, as sign-in reads it.
    $zone = (($zone !== '') && function_exists('pg_timezone_canonical')) ? (string) pg_timezone_canonical($zone) : $zone;
    $seen = ws_channel_member_seen(ws_can_manage_channel($viewer, $channel), array($user_id), $now);

    return $person + array(
        'role_label'  => pg_user_role_name((int) $person['role']),
        'departments' => ws_person_card_departments($user_id, $order, $departments, $names),
        'workday'     => ws_person_card_workday($capacity['day_minutes'], $capacity['workdays']),
        'away'        => ws_person_card_away($events, $user_id, $order, $today, $mail_away),
        'clock'       => ws_person_card_clock($zone, (string) date_default_timezone_get(), (int) $now),
        // Only for a manager of the channel (owner 2026-11-12): '' else.
        'seen'        => (string) ($seen[$user_id] ?? ''),
    );
}

/**
 * Answers ws_person_card: any team member who reads the channel
 * (ws_action_channel()). A guest has no panel session and is refused with
 * everyone who is not on the team.
 *
 * @param string $action
 * @param array  $request channel_id, user_id
 * @return array
 */
function ws_person_card_action($action, $request)
{
    if (!ws_enabled()) {
        return ws_action_error(lang('The workspace is not switched on.'));
    }

    $viewer = ws_viewer(validate_user());

    if (!$viewer['member']) {
        return ws_action_error(lang('Access denied.'));
    }

    session_write_close();

    $request = is_array($request) ? $request : array();
    $channel = ws_action_channel($viewer, $request);

    if (!is_array($channel)) {
        return ws_action_error($channel);
    }

    $card = ($action === 'ws_person_card') ? ws_person_card($viewer, $channel, (int) ($request['user_id'] ?? 0), time()) : null;

    return is_array($card) ? ws_action_ok(array('card' => $card)) : ws_action_error(lang('That person could not be found.'));
}
