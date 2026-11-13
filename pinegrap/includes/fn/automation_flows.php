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
