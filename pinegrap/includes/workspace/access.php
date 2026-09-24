<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - who may see and do what.
 *
 * Every path into the module asks these functions and nothing else: the
 * screens, the AJAX actions, the external API, the device notifications, the
 * record drawers on other screens and the file gate. A rule changed here
 * changes everywhere at once, which is the point - a message hidden from a
 * person's screen must also stay out of their phone and out of an order
 * screen's "where was this discussed" list.
 *
 * The user table is not the staff table: every member of a membership site is
 * a row there with role 3. A basic user is part of the team only while the
 * account holds manage_workspace; staff (roles 0-2) are part of it unless
 * their workspace profile excludes them.
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
 * Is the schema in place?
 *
 * Files land before the schema does, so every entry point asks first and the
 * module stays silent rather than failing until the upgrade has run. The last
 * tables of the step and the permission columns answer for the rest.
 *
 * @param bool $recheck
 * @return bool
 */
function ws_ready($recheck = false)
{
    static $ready = null;

    if (($ready !== null) && !$recheck) {
        return $ready;
    }

    $ready = false;

    if (!class_exists('db') || !isset(db::$con) || !db::$con) {
        return $ready;
    }

    $count = (int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('ws_channels', 'ws_messages', 'ws_tasks', 'ws_inbox', 'ws_event_people')");

    $ready = ($count === 5) && function_exists('pg_user_has_ws_columns') && pg_user_has_ws_columns();

    return $ready;
}

/**
 * Switched on and ready.
 *
 * @return bool
 */
function ws_enabled()
{
    return defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED && ws_ready();
}

/**
 * The rights of one account in the workspace.
 *
 * $user is the array validate_user() or pg_load_user_row() returns; any right
 * it does not carry is read from the table. The answer is cached per account
 * for the request.
 *
 * @param array $user
 * @return array id, role, member, assign, board, settings
 */
function ws_rights($user)
{
    static $cache = array();

    $id = (int) ($user['id'] ?? 0);

    if (isset($cache[$id])) {
        return $cache[$id];
    }

    $role = (int) ($user['role'] ?? 3);

    $rights = array(
        'id'       => $id,
        'role'     => $role,
        'member'   => false,
        'assign'   => false,
        'board'    => false,
        'settings' => false,
    );

    if (($id <= 0) || !ws_ready()) {
        return $cache[$id] = $rights;
    }

    if (!array_key_exists('manage_workspace', $user)) {
        $row = db_item("SELECT manage_workspace, manage_workspace_assign, manage_workspace_board, manage_workspace_settings
            FROM user WHERE user_id = '" . $id . "'");

        if (is_array($row)) {
            $user = array_merge($user, $row);
        }
    }

    // A profile can take a staff account out of the team - an agency designer
    // who only ever touches the page styles has no business in its channels.
    $excluded = ((int) db_value("SELECT excluded FROM ws_profiles WHERE user_id = '" . $id . "'") === 1);

    if ($role < 3) {
        $member = !$excluded;
        $rights['member'] = $member;
        $rights['assign'] = $member;
        $rights['board'] = $member;
        $rights['settings'] = $member;

        return $cache[$id] = $rights;
    }

    if (!empty($user['manage_workspace']) && !$excluded) {
        $rights['member'] = true;
        $rights['assign'] = !empty($user['manage_workspace_assign']);
        $rights['board'] = !empty($user['manage_workspace_board']);
        $rights['settings'] = !empty($user['manage_workspace_settings']);
    }

    return $cache[$id] = $rights;
}

/**
 * The rights of an account known only by its id.
 *
 * @param int $user_id
 * @return array
 */
function ws_rights_for_id($user_id)
{
    $user_id = (int) $user_id;

    $row = db_item("SELECT user_id AS id, user_role AS role FROM user WHERE user_id = '" . $user_id . "'");

    if (!is_array($row)) {
        return ws_rights(array('id' => 0));
    }

    return ws_rights($row);
}

/**
 * The departments an account leads.
 *
 * @param int $user_id
 * @return int[]
 */
function ws_lead_departments($user_id)
{
    static $cache = array();

    $user_id = (int) $user_id;

    if (!isset($cache[$user_id])) {
        $cache[$user_id] = array_map('intval', (array) db_values("SELECT department_id FROM ws_department_members
            WHERE user_id = '" . $user_id . "' AND is_lead = 1"));
    }

    return $cache[$user_id];
}

/**
 * The people in the departments an account leads, the account itself left out.
 *
 * @param int $user_id
 * @return int[]
 */
function ws_led_people($user_id)
{
    $departments = ws_lead_departments($user_id);

    if (empty($departments)) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT DISTINCT user_id FROM ws_department_members
        WHERE department_id IN (" . implode(',', $departments) . ")
        AND user_id <> '" . (int) $user_id . "'"));
}

/**
 * One channel row.
 *
 * @param int $channel_id
 * @return array|null
 */
function ws_channel($channel_id)
{
    $row = db_item("SELECT * FROM ws_channels WHERE id = '" . (int) $channel_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The membership row of one account in one channel.
 *
 * @param int $channel_id
 * @param int $user_id
 * @return array|null
 */
function ws_channel_membership($channel_id, $user_id)
{
    static $cache = array();

    // The generation moves whenever the request changes a membership, so a
    // row written a moment ago is never answered from before it existed.
    $key = (int) ($GLOBALS['ws_membership_generation'] ?? 0) . ':' . (int) $channel_id . ':' . (int) $user_id;

    if (!array_key_exists($key, $cache)) {
        $row = db_item("SELECT * FROM ws_channel_members
            WHERE channel_id = '" . (int) $channel_id . "' AND user_id = '" . (int) $user_id . "'");

        $cache[$key] = is_array($row) ? $row : null;
    }

    return $cache[$key];
}

/**
 * Forgets the cached membership rows, after the request changed one.
 */
function ws_channel_membership_forget()
{
    $GLOBALS['ws_membership_generation'] = ($GLOBALS['ws_membership_generation'] ?? 0) + 1;
}

/**
 * Has this session opened a private channel for inspection?
 *
 * An administrator does not read private channels in passing. Opening one is
 * a deliberate act that leaves a line in the channel itself and in the
 * activity log (ws_channel_audit_open()); the session then remembers it for
 * as long as it lasts.
 *
 * @param int $channel_id
 * @return bool
 */
function ws_audit_open($channel_id)
{
    return !empty($_SESSION['software']['ws_audit'][(int) $channel_id]);
}

/**
 * May this person read the channel?
 *
 * @param array $rights
 * @param array $channel
 * @return bool
 */
function ws_can_read_channel($rights, $channel)
{
    if (!$rights['member'] || !is_array($channel)) {
        return false;
    }

    if ($channel['kind'] === 'public') {
        return true;
    }

    if (ws_channel_membership($channel['id'], $rights['id'])) {
        return true;
    }

    return ($rights['role'] < 3) && ws_audit_open($channel['id']);
}

/**
 * May this person write in the channel? Reading a private channel for
 * inspection does not make the reader a participant.
 *
 * @param array $rights
 * @param array $channel
 * @return bool
 */
function ws_can_post_channel($rights, $channel)
{
    if (!ws_can_read_channel($rights, $channel) || ((int) $channel['archived_at'] > 0)) {
        return false;
    }

    if ($channel['kind'] === 'public') {
        return true;
    }

    return (bool) ws_channel_membership($channel['id'], $rights['id']);
}

/**
 * May this person rename, archive, convert and manage the members of the
 * channel? Its owner, and staff - of a private channel only while they are
 * in it.
 *
 * @param array $rights
 * @param array $channel
 * @return bool
 */
function ws_can_manage_channel($rights, $channel)
{
    if (!$rights['member'] || !is_array($channel)) {
        return false;
    }

    if ((int) $channel['owner_user_id'] === (int) $rights['id']) {
        return true;
    }

    if ($rights['role'] >= 3) {
        return false;
    }

    return ($channel['kind'] === 'public') || (bool) ws_channel_membership($channel['id'], $rights['id']);
}

/**
 * The people a task is assigned to.
 *
 * @param int $task_id
 * @return int[]
 */
function ws_task_assignee_ids($task_id)
{
    return array_map('intval', (array) db_values("SELECT user_id FROM ws_task_assignees
        WHERE task_id = '" . (int) $task_id . "' ORDER BY assigned_at, user_id"));
}

/**
 * May this person see the task?
 *
 * A task posted in a channel is as visible as the channel; one with no
 * channel belongs to the people on it, the department's leads and whoever
 * holds the board.
 *
 * @param array $rights
 * @param array $task
 * @param int[]|null $assignees  known already, to spare a query in a list
 * @return bool
 */
function ws_can_see_task($rights, $task, $assignees = null)
{
    if (!$rights['member'] || !is_array($task)) {
        return false;
    }

    if ((int) $task['creator_id'] === (int) $rights['id']) {
        return true;
    }

    if ($assignees === null) {
        $assignees = ws_task_assignee_ids($task['id']);
    }

    if (in_array((int) $rights['id'], $assignees, true)) {
        return true;
    }

    if ((int) $task['channel_id'] > 0) {
        return ws_can_read_channel($rights, ws_channel($task['channel_id']));
    }

    if ($rights['board']) {
        return true;
    }

    return ((int) $task['department_id'] > 0) && in_array((int) $task['department_id'], ws_lead_departments($rights['id']), true);
}

/**
 * May this person change the task - its status, dates, text and people?
 *
 * @param array $rights
 * @param array $task
 * @param int[]|null $assignees
 * @return bool
 */
function ws_can_edit_task($rights, $task, $assignees = null)
{
    if (!ws_can_see_task($rights, $task, $assignees)) {
        return false;
    }

    if ($assignees === null) {
        $assignees = ws_task_assignee_ids($task['id']);
    }

    if (((int) $task['creator_id'] === (int) $rights['id']) || in_array((int) $rights['id'], $assignees, true)) {
        return true;
    }

    if ($rights['assign'] || ($rights['role'] < 3)) {
        return true;
    }

    return ((int) $task['department_id'] > 0) && in_array((int) $task['department_id'], ws_lead_departments($rights['id']), true);
}

/**
 * May this person hand work to these people?
 *
 * Everyone may take work on themselves. Handing it to somebody else needs the
 * assign right - or leading a department every one of the others belongs to.
 *
 * @param array $rights
 * @param int[] $user_ids
 * @return bool
 */
function ws_can_assign_to($rights, $user_ids)
{
    if (!$rights['member']) {
        return false;
    }

    $others = array();

    foreach ((array) $user_ids as $user_id) {
        if ((int) $user_id !== (int) $rights['id']) {
            $others[] = (int) $user_id;
        }
    }

    if (empty($others) || $rights['assign']) {
        return true;
    }

    $led = ws_led_people($rights['id']);

    foreach ($others as $user_id) {
        if (!in_array($user_id, $led, true)) {
            return false;
        }
    }

    return true;
}

/**
 * Whose board may this person see? True for everyone, or the ids of the
 * people whose rows they may read: their own and those of the departments
 * they lead.
 *
 * @param array $rights
 * @return true|int[]
 */
function ws_board_scope($rights)
{
    if ($rights['board']) {
        return true;
    }

    return array_values(array_unique(array_merge(array((int) $rights['id']), ws_led_people($rights['id']))));
}
