<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Incoming e-mail in the external API on a site whose Workspace is off.
 *
 * Mail is read and answered whatever the Workspace's switch (ws_mail_on()),
 * so its routes, objects and event must not go dark with it: a key holding
 * workspace:read or workspace:write keeps reaching /workspace/mail… (a
 * person's connection sends under workspace:mail_send)
 * whichever way the switch is set. With the Workspace on,
 * includes/workspace/api.php contributes everything, mail included; with it
 * off, includes/api/modules.php loads this file, which contributes only the
 * mail part of the same declarations, picked from the Workspace's own lists
 * so ids, paths, scopes and handlers stay one declaration. The MCP server
 * reads api_schema(), so it offers the same tools.
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
 * The mail routes among route rows (pure): the ones whose id starts with
 * workspace.mail_ (conversations, messages) or workspace.mail. (a route a
 * later step adds under that name).
 *
 * @param array $routes
 * @return array
 */
function ws_mail_alone_pick_routes($routes)
{
    $out = array();

    foreach ($routes as $route) {
        $id = isset($route['id']) ? (string) $route['id'] : '';

        if ((strpos($id, 'workspace.mail_') === 0) || (strpos($id, 'workspace.mail.') === 0)) {
            $out[] = $route;
        }
    }

    return $out;
}

/**
 * The mail events among webhook events (pure): workspace.mail.*.
 *
 * @param array $events name => line
 * @return array
 */
function ws_mail_alone_pick_events($events)
{
    $out = array();

    foreach ($events as $name => $line) {
        if (strpos((string) $name, 'workspace.mail.') === 0) {
            $out[$name] = $line;
        }
    }

    return $out;
}

/**
 * The mail objects among OpenAPI objects (pure): WorkspaceMail*.
 *
 * @param array $objects name => declaring function
 * @return array
 */
function ws_mail_alone_pick_objects($objects)
{
    $out = array();

    foreach ($objects as $name => $declare) {
        if (strpos((string) $name, 'WorkspaceMail') === 0) {
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
function ws_mail_alone_api_routes()
{
    return ws_mail_alone_pick_routes(ws_api_routes());
}

/**
 * The events an application may subscribe to.
 *
 * @return array
 */
function ws_mail_alone_webhook_events()
{
    return ws_mail_alone_pick_events(ws_webhook_events());
}

/**
 * The objects the routes return.
 *
 * @return array
 */
function ws_mail_alone_openapi_objects()
{
    return ws_mail_alone_pick_objects(ws_openapi_objects());
}

/**
 * The permission row on the Application Access screen: the Workspace's own
 * scopes, which the mail routes ask. Where flows are merged too, their
 * module's row comes first and names both (ws_flows_alone_scope_groups()).
 *
 * @return array
 */
function ws_mail_alone_scope_groups()
{
    return array(
        'workspace' => array(
            'label'       => lang('Incoming e-mail'),
            'description' => lang('E-mail conversations, answers and new e-mails'),
            'icon'        => 'bi-envelope',
            'read'        => 'workspace:read',
            'write'       => 'workspace:write',
        ),
        'workspace_mail' => ws_scope_groups()['workspace_mail'],
    );
}

/**
 * Which of the scopes an application owner may delegate: the Workspace's
 * own ceiling, kept to the scopes the mail routes ask (sending in the
 * person's name is workspace:mail_send).
 *
 * @param array $owner
 * @return array
 */
function ws_mail_alone_owner_scopes($owner)
{
    return array_values(array_intersect(ws_owner_scopes($owner), array('workspace:read', 'workspace:write', 'workspace:mail_send')));
}
