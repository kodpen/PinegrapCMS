<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Automation flows in the external API on a site whose Workspace is off.
 *
 * Flows run at every site once their tables are there (pg_flows_enabled()),
 * so their routes, objects and run events must not go dark with the
 * Workspace's switch: a key holding workspace:read or workspace:write keeps
 * reaching /workspace/flows… whichever way the switch is set. With the
 * Workspace on, includes/workspace/api.php contributes everything, flows
 * included; with it off, includes/api/modules.php loads this file instead,
 * which contributes only the flow part of the same declarations. The rows
 * are picked from the Workspace's own lists, never copied, so ids, paths,
 * scopes and handlers stay one declaration, and the MCP server, which reads
 * api_schema(), offers the same tools.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

/**
 * The flow routes among route rows (pure): the ones whose id starts with
 * workspace.flows. or workspace.flow_ (flows, versions, templates, kinds,
 * shares, runs).
 *
 * @param array $routes
 * @return array
 */
function ws_flows_alone_pick_routes($routes)
{
    $out = array();

    foreach ($routes as $route) {
        $id = isset($route['id']) ? (string) $route['id'] : '';

        if ((strpos($id, 'workspace.flows.') === 0) || (strpos($id, 'workspace.flow_') === 0)) {
            $out[] = $route;
        }
    }

    return $out;
}

/**
 * The run events among webhook events (pure): workspace.flow.run.*.
 *
 * @param array $events name => line
 * @return array
 */
function ws_flows_alone_pick_events($events)
{
    $out = array();

    foreach ($events as $name => $line) {
        if (strpos((string) $name, 'workspace.flow.run.') === 0) {
            $out[$name] = $line;
        }
    }

    return $out;
}

/**
 * The flow objects among OpenAPI objects (pure): WorkspaceFlow*.
 *
 * @param array $objects name => declaring function
 * @return array
 */
function ws_flows_alone_pick_objects($objects)
{
    $out = array();

    foreach ($objects as $name => $declare) {
        if (strpos((string) $name, 'WorkspaceFlow') === 0) {
            $out[$name] = $declare;
        }
    }

    return $out;
}

/**
 * The route rows.
 *
 * @return array
 */
function ws_flows_alone_api_routes()
{
    return ws_flows_alone_pick_routes(ws_api_routes());
}

/**
 * The events an application may subscribe to.
 *
 * @return array
 */
function ws_flows_alone_webhook_events()
{
    return ws_flows_alone_pick_events(ws_webhook_events());
}

/**
 * The objects the routes return.
 *
 * @return array
 */
function ws_flows_alone_openapi_objects()
{
    return ws_flows_alone_pick_objects(ws_openapi_objects());
}

/**
 * The permission row on the Application Access screen: the Workspace's own
 * scopes, which the flow routes ask, under the name of what they reach.
 *
 * @return array
 */
function ws_flows_alone_scope_groups()
{
    return array(
        'workspace' => array(
            'label'       => lang('Automation flows'),
            'description' => lang('Flows, their runs and shares'),
            'icon'        => 'bi-diagram-3',
            'read'        => 'workspace:read',
            'write'       => 'workspace:write',
        ),
    );
}

/**
 * Which of the scopes an application owner may delegate: the Workspace's
 * own ceiling, kept to the two scopes the flow routes ask.
 *
 * @param array $owner
 * @return array
 */
function ws_flows_alone_owner_scopes($owner)
{
    return array_values(array_intersect(ws_owner_scopes($owner), array('workspace:read', 'workspace:write')));
}
