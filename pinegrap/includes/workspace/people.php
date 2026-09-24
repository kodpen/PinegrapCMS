<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the team: who is in it, how they are shown, the departments
 * they belong to and how much of a day each of them has.
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
 * The SQL condition that picks the team out of the user table: staff, and the
 * basic users holding the gate, minus anyone whose profile excludes them.
 * Expects the user table as "u" and the profile LEFT JOINed as "p".
 *
 * @return string
 */
function ws_team_condition()
{
    return "((u.user_role < 3) OR (u.manage_workspace = 1)) AND (p.excluded IS NULL OR p.excluded = 0)";
}

/**
 * The name a person is shown by: the contact's name, the username otherwise.
 *
 * @param array $row first_name, last_name, username
 * @return string
 */
function ws_display_name($row)
{
    $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));

    return ($name !== '') ? $name : (string) ($row['username'] ?? '');
}

/**
 * The picture a person is shown with, in the order the dashboard uses:
 * the contact's uploaded file, the contact's image address, the default.
 *
 * @param array $row image, image_file_id, image_file_name
 * @return string
 */
function ws_avatar_url($row)
{
    if (((int) ($row['image_file_id'] ?? 0) > 0) && ((string) ($row['image_file_name'] ?? '') !== '')) {
        return PATH . $row['image_file_name'];
    }

    if ((string) ($row['image'] ?? '') !== '') {
        return (string) $row['image'];
    }

    return PATH . SOFTWARE_DIRECTORY . '/assets/images/person1.png';
}

/**
 * Online, away or offline, from the panel's heartbeat (the same thresholds
 * the chat uses).
 *
 * @param int $timestamp
 * @return string
 */
function ws_presence($timestamp)
{
    $timestamp = (int) $timestamp;

    if ($timestamp <= 0) {
        return 'offline';
    }

    $ago = time() - $timestamp;

    if ($ago < 120) {
        return 'online';
    }

    return ($ago < 1200) ? 'away' : 'offline';
}

/**
 * The columns every people query selects.
 *
 * @return string
 */
function ws_people_select()
{
    return "SELECT
            u.user_id AS id,
            u.user_role AS role,
            u.user_username AS username,
            u.user_online_timestamp AS online_timestamp,
            c.first_name,
            c.last_name,
            c.image,
            c.file_id AS image_file_id,
            f.name AS image_file_name,
            p.title AS title,
            p.day_minutes AS day_minutes,
            p.workdays AS workdays
        FROM user u
        LEFT JOIN contacts c ON c.id = u.user_contact
        LEFT JOIN files f ON f.id = c.file_id
        LEFT JOIN ws_profiles p ON p.user_id = u.user_id";
}

/**
 * The short form of a person the screens and the API hand around.
 *
 * @param array $row a ws_people_select() row
 * @return array
 */
function ws_person_brief($row)
{
    return array(
        'id'       => (int) $row['id'],
        'name'     => ws_display_name($row),
        'username' => (string) $row['username'],
        'role'     => (int) $row['role'],
        'title'    => (string) ($row['title'] ?? ''),
        'avatar'   => ws_avatar_url($row),
        'presence' => ws_presence($row['online_timestamp'] ?? 0),
    );
}

/**
 * Everyone in the team, by name.
 *
 * @return array[] briefs, keyed by nothing
 */
function ws_team_members()
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $rows = (array) db_items(ws_people_select() . "
        WHERE " . ws_team_condition() . "
        ORDER BY c.first_name, c.last_name, u.user_username
        LIMIT 1000");

    $cache = array();

    foreach ($rows as $row) {
        $cache[] = ws_person_brief($row);
    }

    return $cache;
}

/**
 * The team's ids.
 *
 * @return int[]
 */
function ws_team_ids()
{
    $ids = array();

    foreach (ws_team_members() as $person) {
        $ids[] = (int) $person['id'];
    }

    return $ids;
}

/**
 * Is this account part of the team?
 *
 * @param int $user_id
 * @return bool
 */
function ws_is_team_member($user_id)
{
    return in_array((int) $user_id, ws_team_ids(), true);
}

/**
 * Briefs for a set of accounts, keyed by id. Accounts that left the team are
 * still named - their old messages keep a sender.
 *
 * @param int[] $user_ids
 * @return array
 */
function ws_people($user_ids)
{
    static $cache = array();

    $wanted = array();

    foreach ((array) $user_ids as $user_id) {
        $user_id = (int) $user_id;

        if (($user_id > 0) && !isset($cache[$user_id])) {
            $wanted[$user_id] = $user_id;
        }
    }

    if (!empty($wanted)) {
        foreach ((array) db_items(ws_people_select() . " WHERE u.user_id IN (" . implode(',', $wanted) . ")") as $row) {
            $cache[(int) $row['id']] = ws_person_brief($row);
        }

        foreach ($wanted as $user_id) {
            if (!isset($cache[$user_id])) {
                $cache[$user_id] = array(
                    'id'       => $user_id,
                    'name'     => lang('Former member'),
                    'username' => '',
                    'role'     => 3,
                    'title'    => '',
                    'avatar'   => PATH . SOFTWARE_DIRECTORY . '/assets/images/person1.png',
                    'presence' => 'offline',
                );
            }
        }
    }

    $out = array();

    foreach ((array) $user_ids as $user_id) {
        $user_id = (int) $user_id;

        if (isset($cache[$user_id])) {
            $out[$user_id] = $cache[$user_id];
        }
    }

    return $out;
}

/**
 * One person's name.
 *
 * @param int $user_id
 * @return string
 */
function ws_person_name($user_id)
{
    $people = ws_people(array($user_id));

    return isset($people[(int) $user_id]) ? $people[(int) $user_id]['name'] : '';
}

/**
 * The departments, with their members when asked.
 *
 * @param bool $with_members
 * @param bool $include_archived
 * @return array[]
 */
function ws_departments($with_members = false, $include_archived = false)
{
    $rows = (array) db_items("SELECT * FROM ws_departments"
        . ($include_archived ? '' : " WHERE archived = 0")
        . " ORDER BY sort, name");

    $out = array();

    foreach ($rows as $row) {
        $department = array(
            'id'         => (int) $row['id'],
            'name'       => (string) $row['name'],
            'color'      => (string) $row['color'],
            'channel_id' => (int) $row['channel_id'],
            'archived'   => ((int) $row['archived'] === 1),
        );

        if ($with_members) {
            $department['members'] = array();
            $department['leads'] = array();
        }

        $out[(int) $row['id']] = $department;
    }

    if ($with_members && !empty($out)) {
        foreach ((array) db_items("SELECT department_id, user_id, is_lead, is_primary FROM ws_department_members
            WHERE department_id IN (" . implode(',', array_keys($out)) . ")") as $member) {

            $department_id = (int) $member['department_id'];

            $out[$department_id]['members'][] = (int) $member['user_id'];

            if ((int) $member['is_lead'] === 1) {
                $out[$department_id]['leads'][] = (int) $member['user_id'];
            }
        }
    }

    return $out;
}

/**
 * One department.
 *
 * @param int $department_id
 * @return array|null
 */
function ws_department($department_id)
{
    $row = db_item("SELECT * FROM ws_departments WHERE id = '" . (int) $department_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The people in a department.
 *
 * @param int $department_id
 * @return int[]
 */
function ws_department_member_ids($department_id)
{
    return array_map('intval', (array) db_values("SELECT user_id FROM ws_department_members
        WHERE department_id = '" . (int) $department_id . "'"));
}

/**
 * The departments one person is in, primary first.
 *
 * @param int $user_id
 * @return int[]
 */
function ws_user_department_ids($user_id)
{
    return array_map('intval', (array) db_values("SELECT department_id FROM ws_department_members
        WHERE user_id = '" . (int) $user_id . "'
        ORDER BY is_primary DESC, department_id"));
}

/**
 * How much of a day a person has, and which days they work.
 *
 * @param int $user_id
 * @return array day_minutes, workdays (Monday = bit 0)
 */
function ws_capacity($user_id)
{
    static $cache = array();

    $user_id = (int) $user_id;

    if (!isset($cache[$user_id])) {
        $row = db_item("SELECT day_minutes, workdays FROM ws_profiles WHERE user_id = '" . $user_id . "'");

        $day = (is_array($row) && ((int) $row['day_minutes'] > 0)) ? (int) $row['day_minutes'] : (int) WS_DAY_MINUTES;
        $week = (is_array($row) && ((int) $row['workdays'] > 0)) ? (int) $row['workdays'] : (int) WS_WORKDAYS;

        $cache[$user_id] = array(
            'day_minutes' => max(0, min(1440, $day)),
            'workdays'    => ($week & 127) ?: 31,
        );
    }

    return $cache[$user_id];
}

/**
 * A number of minutes as the planning screens say it: "1 h 30 min".
 *
 * @param int $minutes
 * @return string
 */
function ws_minutes_label($minutes)
{
    $minutes = max(0, (int) $minutes);
    $hours = (int) floor($minutes / 60);
    $rest = $minutes % 60;

    if ($hours === 0) {
        return lang(array('string' => '{var:1} min', 'vars' => $rest));
    }

    if ($rest === 0) {
        return lang(array('string' => '{var:1} h', 'vars' => $hours));
    }

    return lang(array('string' => '{var:1} h {var:2} min', 'vars' => array($hours, $rest)));
}
