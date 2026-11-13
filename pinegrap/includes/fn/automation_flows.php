<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Automation flows outside the Workspace switch.
 *
 * Flows are drawn and run by the Workspace module's files
 * (includes/workspace/flows.php, flow_runs.php), but they are on at every
 * site whose database holds their tables, whether or not the Workspace is
 * switched on: they have no switch of their own, and who may build or run one
 * is decided by its right alone. This is the gate the places outside the
 * module ask before they load it: the general job (job.php), the site's
 * events (pg_event_record()) and the scheduled job of the Workspace.
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
 * Do automation flows run on this site? Yes once their tables are there
 * (2026.11.0, step 11.0.80, which adds ws_scheduled_actions.flow_id last)
 * with the queue of the events a flow starts on. Read from the schema cache,
 * so a visitor's checkout that announces an event pays nothing for asking.
 *
 * The Workspace's own gate, ws_enabled(), stays for everything else.
 *
 * @return bool
 */
function pg_flows_enabled()
{
    return function_exists('pg_schema_has')
        && pg_schema_has('ws_flow_run_steps')
        && pg_schema_has('ws_scheduled_actions', 'flow_id')
        && pg_schema_has('ws_events_in');
}

/**
 * Is there anything for flows to do on a site whose Workspace is off: a live
 * flow, whose start may come due or hear an event, or a run still going? One
 * small read, so the general job loads the Workspace's files only where flows
 * are used.
 *
 * @return bool
 */
function pg_flows_in_use()
{
    if (!pg_flows_enabled()) {
        return false;
    }

    return (bool) db_value("(SELECT id FROM ws_flows WHERE status = 'live' LIMIT 1)
        UNION ALL (SELECT id FROM ws_flow_runs WHERE status IN ('running', 'waiting') LIMIT 1)
        LIMIT 1");
}

/**
 * Is a flow's run still going anywhere on the site: running, or waiting for
 * an answer, a time or an event (ws_flow_run_open())? One small read on the
 * runs' status index, so the main menu loads the Workspace's files to count
 * the flows running only while there are some.
 *
 * @return bool
 */
function pg_flows_runs_open()
{
    if (!pg_flows_enabled()) {
        return false;
    }

    return (bool) db_value("SELECT id FROM ws_flow_runs WHERE status IN ('running', 'waiting') LIMIT 1");
}

/**
 * May this person open the Flows screen: flows run on the site and they are
 * a member of the team (ws_rights(), which never asks the Workspace switch);
 * a role-3 user needs the Workspace right (USER_MANAGE_WORKSPACE). The main
 * menu's row and the dashboard's flow entries ask it. Loads only the
 * Workspace's access file.
 *
 * @param array $user validate_user()
 * @return bool
 */
function pg_flows_user_may($user)
{
    if (!is_array($user) || !pg_flows_enabled()) {
        return false;
    }

    if (((int) ($user['role'] ?? 3) >= 3) && !(defined('USER_MANAGE_WORKSPACE') && USER_MANAGE_WORKSPACE)) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/access.php');

    return ws_ready() && !empty(ws_rights($user)['member']);
}

/**
 * The main menu's Automation flows row for the person signed in: null when
 * it is not drawn, else the number of flows running now that they reach.
 *
 * The row is drawn for the people its screen admits (workspace_flows.php,
 * ws_screen_gate($user, true)): the Workspace's tables in the database,
 * flows on the site, and a member of the team (ws_rights(), which never asks
 * the Workspace switch). Flows run with the Workspace on or off, and so does
 * the row. Roles 0-2 are members unless their Workspace profile leaves them
 * out; a role-3 user needs the Workspace right (USER_MANAGE_WORKSPACE).
 *
 * Every panel page draws the menu, so a site whose Workspace is off loads
 * only the Workspace's access file for the gate, and its whole module only
 * while a run is open, to count the flows of those runs the person may read
 * (ws_flows_running_count()).
 *
 * @param array $user validate_user()
 * @return array|null running (int)
 */
function pg_flows_menu_row($user)
{
    if (!pg_flows_user_may($user)) {
        return null;
    }

    $running = 0;

    if (pg_flows_runs_open()) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

        $running = ws_flows_running_count(ws_viewer($user));
    }

    return array('running' => $running);
}
