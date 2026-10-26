<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - other services' MCP servers in the external API, for an
 * administrator's application: the ready-made connectors (mcp_catalogue.php),
 * the site's servers with where each stands, an address checked, a server
 * added, its tools read again and turned on or off. The checks and writes are
 * the settings screen's own (mcp.php); the sign-in to a service stays there,
 * since a person signs in in a browser.
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

/**
 * One ready-made connector as the API gives it.
 *
 * @param string     $key
 * @param array      $entry  ws_mcp_catalogue_entry()
 * @param array|null $server the site's server that is this connector
 * @return array
 */
function ws_api_mcp_connector_present($key, $entry, $server)
{
    return array(
        'key'          => (string) $key,
        'name'         => (string) $entry['name'],
        'description'  => (string) $entry['description'],
        'url'          => (string) $entry['url'],
        'auth'         => (string) $entry['auth'],
        'token_url'    => ($entry['token_url'] !== '') ? (string) $entry['token_url'] : null,
        'icon'         => (string) $entry['icon'],
        'alias'        => (string) $entry['alias'],
        'tools'        => (string) $entry['tools'],
        'added'        => is_array($server),
        'server_id'    => is_array($server) ? (int) $server['id'] : null,
        'server_alias' => is_array($server) ? (string) $server['alias'] : null,
    );
}

// What ws_api_mcp_connector_present() returns.
function ws_api_mcp_connector_schema()
{
    return array(
        'key'          => 'string',
        'name'         => 'string',
        'description'  => 'string',
        'url'          => 'string',
        'auth'         => 'string',
        'token_url'    => 'string?',
        'icon'         => 'string',
        'alias'        => 'string',
        'tools'        => 'string',
        'added'        => 'boolean',
        'server_id'    => 'integer?',
        'server_alias' => 'string?',
    );
}

function ws_api_mcp_catalogue($params)
{
    $viewer = ws_api_viewer();

    if ((int) $viewer['role'] !== 0) {
        api_fail(403, 'forbidden', lang('Only an administrator can add MCP servers.'));
    }

    $added = ws_mcp_ready() ? ws_mcp_catalogue_added(ws_mcp_servers()) : array();
    $out = array();

    foreach (ws_mcp_catalogue() as $key => $entry) {
        $out[] = ws_api_mcp_connector_present($key, $entry, $added[$key] ?? null);
    }

    api_ok_list($out, count($out));
}

/**
 * The application's owner, when an administrator and not an assistant's key;
 * 403 for anybody else, 503 before the database is upgraded. An assistant's
 * key is made by an administrator, so the role alone would let a request
 * written in a channel add a server or turn its tools on without approval.
 *
 * @return array the viewer
 */
function ws_api_mcp_admin()
{
    $viewer = ws_api_viewer();

    if (ws_api_is_assistant()) {
        api_fail(403, 'forbidden', lang('An assistant does not manage the MCP servers of the site; an administrator does.'));
    }

    if ((int) $viewer['role'] !== 0) {
        api_fail(403, 'forbidden', lang('Only an administrator can add MCP servers.'));
    }

    if (!ws_mcp_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    return $viewer;
}

/**
 * A server of the site by the id in the path, or 404.
 *
 * @param mixed $id
 * @return array
 */
function ws_api_mcp_server_or_404($id)
{
    $server = ws_mcp_server((int) $id);

    if (!$server) {
        api_fail(404, 'not_found', lang('That MCP server could not be found.'));
    }

    return $server;
}

/**
 * One tool of a server as the API gives it: what the server says it does
 * and the administrator's rule for it.
 *
 * @param array $server
 * @param array $tool
 * @param array $rules ws_mcp_tool_rules()
 * @param array $on    ws_mcp_tools_on()
 * @return array
 */
function ws_api_mcp_tool_present($server, $tool, $rules, $on)
{
    $name = (string) $tool['name'];
    $can = ws_mcp_tool_can_run($tool);
    $hint = ($can === 'read') ? 'reads_only' : (($can === 'declare') ? 'not_said' : ((($tool['hints']['destructiveHint'] ?? null) === true) ? 'destroys' : 'writes'));

    return array(
        'name'        => $name,
        'title'       => (string) ($tool['title'] ?? ''),
        'description' => (string) ($tool['description'] ?? ''),
        'hint'        => $hint,
        'rule'        => (string) ($rules[$name] ?? 'off'),
        'on'          => isset($on[$name]),
        'asks_first'  => !empty($on[$name]['approve']),
    );
}

// What ws_api_mcp_tool_present() returns.
function ws_api_mcp_tool_schema()
{
    return array(
        'name'        => 'string',
        'title'       => 'string',
        'description' => 'string',
        'hint'        => 'string',
        'rule'        => 'string',
        'on'          => 'boolean',
        'asks_first'  => 'boolean',
    );
}

/**
 * One server of the site as the API gives it: where it stands (the card's
 * badge), why, the checklist, and its tools with their rules. No credential.
 *
 * @param array      $server
 * @param array|null $read   ws_mcp_refresh_tools(), when this call read the tools
 * @return array
 */
function ws_api_mcp_server_present($server, $read = null)
{
    $found = ws_mcp_server_state($server);
    $rules = ws_mcp_tool_rules($server);
    $on = ws_mcp_tools_on($server);
    $tools = array();

    foreach (ws_mcp_tools($server) as $tool) {
        $tools[] = ws_api_mcp_tool_present($server, $tool, $rules, $on);
    }

    return array(
        'id'            => (int) $server['id'],
        'alias'         => (string) $server['alias'],
        'name'          => (string) $server['name'],
        'url'           => (string) $server['url'],
        'auth'          => (string) $server['auth_kind'],
        'status'        => (string) $server['status'],
        'state'         => $found['state'],
        'reason'        => $found['reason'],
        'checklist'     => $found['checklist'],
        'server_info'   => (string) $server['server_info'],
        'last_error'    => ((string) $server['last_error'] !== '') ? (string) $server['last_error'] : null,
        'tools_read_at' => api_time($server['tools_at']),
        'tools_on'      => count($on),
        'tools'         => $tools,
        'read'          => is_array($read) ? array('ok' => (bool) $read['ok'], 'error' => ($read['error'] !== '') ? (string) $read['error'] : null, 'count' => (int) $read['count'], 'turned_on' => (int) $read['turned_on']) : null,
    );
}

// What ws_api_mcp_server_present() returns.
function ws_api_mcp_server_schema()
{
    return array(
        'id'            => 'integer',
        'alias'         => 'string',
        'name'          => 'string',
        'url'           => 'string',
        'auth'          => 'string',
        'status'        => 'string',
        'state'         => 'string',
        'reason'        => 'string',
        'checklist'     => array(array('key' => 'string', 'done' => 'boolean', 'text' => 'string')),
        'server_info'   => 'string',
        'last_error'    => 'string?',
        'tools_read_at' => 'string?',
        'tools_on'      => 'integer',
        'tools'         => 'WorkspaceMcpTool[]',
        'read'          => 'object?',
    );
}

/**
 * What an address answered, as the API gives it.
 *
 * @param string $url
 * @param array  $found ws_mcp_probe_address()
 * @return array
 */
function ws_api_mcp_probe_present($url, $found)
{
    $probe = $found['probe'];
    $kind = (string) $probe['kind'];

    return array(
        'url'       => (string) $url,
        'auth'      => ($kind !== '') ? $kind : null,
        'tools'     => (int) ($probe['tools'] ?? 0),
        'reads'     => (int) ($probe['reads'] ?? 0),
        'name'      => ($kind !== '') ? (string) $probe['name'] : null,
        'alias'     => ($kind !== '') ? ws_mcp_alias_free($probe['alias']) : null,
        'state'     => (string) $found['state'],
        'text'      => (string) $found['text'],
        'server_id' => is_array($found['server']) ? (int) $found['server']['id'] : null,
    );
}

// What ws_api_mcp_probe_present() returns.
function ws_api_mcp_probe_schema()
{
    return array(
        'url'       => 'string',
        'auth'      => 'string?',
        'tools'     => 'integer',
        'reads'     => 'integer',
        'name'      => 'string?',
        'alias'     => 'string?',
        'state'     => 'string',
        'text'      => 'string',
        'server_id' => 'integer?',
    );
}

function ws_api_mcp_servers_list($params)
{
    ws_api_mcp_admin();

    $out = array();

    foreach (ws_mcp_servers(true) as $server) {
        $out[] = ws_api_mcp_server_present($server);
    }

    api_ok_list($out, count($out));
}

function ws_api_mcp_servers_probe($params)
{
    ws_api_mcp_admin();

    $url = (string) $params['url'];
    $problem = ws_mcp_probe_check($url);

    if ($problem !== '') {
        api_fail_validation($problem, 'url');
    }

    // A rehearsal asks nobody: the address is only checked.
    $existing = ws_mcp_server_at($url);
    api_dry_run_stop('probed', 'workspace_mcp_server', array('url' => $url, 'server_id' => $existing ? (int) $existing['id'] : null));

    api_ok(ws_api_mcp_probe_present($url, ws_mcp_probe_address($url)));
}

function ws_api_mcp_servers_create($params)
{
    $viewer = ws_api_mcp_admin();
    $url = (string) $params['url'];
    $alias = (string) ($params['alias'] ?? '');

    // An alias left out is taken from the host, made free.
    if ($alias === '') {
        $alias = ws_mcp_alias_free(ws_mcp_alias_from_host((string) parse_url($url, PHP_URL_HOST)));
    }

    $post = array(
        'mcp_alias'         => $alias,
        'mcp_name'          => (string) ($params['name'] ?? ''),
        'mcp_url'           => $url,
        'mcp_auth'          => (string) ($params['auth'] ?? 'none'),
        'mcp_token'         => (string) ($params['token'] ?? ''),
        'mcp_header_name'   => (string) ($params['header_name'] ?? ''),
        'mcp_header_value'  => (string) ($params['header_value'] ?? ''),
        'mcp_user'          => (string) ($params['user'] ?? ''),
        'mcp_password'      => (string) ($params['password'] ?? ''),
        'mcp_client_id'     => (string) ($params['client_id'] ?? ''),
        'mcp_client_secret' => (string) ($params['client_secret'] ?? ''),
    );

    $existing = ws_mcp_server_at($url);

    if ($existing) {
        api_fail(409, 'conflict', lang(array('string' => 'This address is already added as @{var:1}.', 'vars' => $existing['alias'])));
    }

    $check = ws_mcp_save_check(null, $post);

    if ($check['problem'] !== '') {
        api_fail($check['full'] ? 409 : 422, $check['full'] ? 'conflict' : 'validation_failed', $check['problem']);
    }

    // A rehearsal asks nobody: neither the server for its tools nor the
    // service for a sign-in.
    api_dry_run_stop('created', 'workspace_mcp_server', array('alias' => $check['alias'], 'url' => $check['url'], 'auth' => $check['kind']));

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_MCP_SCREEN_TIMEOUT * (PG_MCP_CLIENT_MAX_PAGES + 3));
    }

    $result = ws_api_as_owner(function () use ($viewer, $check) {
        $written = ws_mcp_save($viewer, null, $check);
        $server = ws_mcp_server($written['id']);
        $read = null;

        if (!$server) {
            return null;
        }

        // A server people sign in to gets its application now; the sign-in
        // itself is a person's, in the Workspace settings.
        if ($check['kind'] === 'oauth') {
            ws_mcp_oauth_setup($server, $check['client_id'], ($check['client_secret'] !== '') ? $check['client_secret'] : null, (int) $viewer['id']);
        } else {
            $read = ws_mcp_refresh_tools($server, (int) $viewer['id']);
        }

        return array('server' => ws_mcp_server($written['id']), 'read' => $read);
    });

    if (!$result || !$result['server']) {
        api_fail(500, 'server_error', lang('The MCP server could not be saved.'));
    }

    api_ok(ws_api_mcp_server_present($result['server'], $result['read']), 201);
}

function ws_api_mcp_servers_refresh($params)
{
    $viewer = ws_api_mcp_admin();
    $server = ws_api_mcp_server_or_404($params['id']);
    $problem = ws_mcp_refresh_check($server, (int) $viewer['id']);

    if ($problem !== '') {
        api_fail(409, 'conflict', $problem);
    }

    // A rehearsal asks nobody.
    api_dry_run_stop('refreshed', 'workspace_mcp_server', array('id' => (int) $server['id'], 'first_read' => ws_mcp_first_read($server)));

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_MCP_SCREEN_TIMEOUT * (PG_MCP_CLIENT_MAX_PAGES + 3));
    }

    $read = ws_api_as_owner(function () use ($viewer, $server) {
        return ws_mcp_refresh_tools($server, (int) $viewer['id']);
    });

    api_ok(ws_api_mcp_server_present(ws_mcp_server((int) $server['id']), $read));
}

function ws_api_mcp_servers_tools($params)
{
    ws_api_mcp_admin();

    $server = ws_api_mcp_server_or_404($params['id']);
    $wanted = array();

    // off, on, approve: on is what the tool may have (reads only, declared
    // to, or asking first where it writes); approve asks first.
    foreach ((array) ($params['tools'] ?? array()) as $i => $row) {
        $name = is_array($row) && is_scalar($row['name'] ?? null) ? (string) $row['name'] : '';
        $rule = is_array($row) && is_scalar($row['rule'] ?? null) ? (string) $row['rule'] : '';

        if (($name === '') || !in_array($rule, array('off', 'on', 'approve'), true)) {
            api_fail_validation(lang('Each tool is an object of {name, rule}; rule is off, on or approve.'), 'tools');
        }

        $wanted[$name] = ($rule === 'off') ? '' : (($rule === 'on') ? '1' : 'approve');
    }

    $check = ws_mcp_tools_check($server, $wanted, !empty($params['declare_all']), ws_mcp_tool_rules($server));

    if (!empty($check['unknown'])) {
        api_fail_validation(lang(array('string' => '@{var:1} lists no tool named {var:2}.', 'vars' => array($server['alias'], implode(', ', $check['unknown'])))), 'tools');
    }

    if (!empty($check['refused'])) {
        api_fail_validation(lang(array('string' => 'These tools write, so they can be turned on only on a server people sign in to, each call approved: {var:1}', 'vars' => implode(', ', $check['refused']))), 'tools');
    }

    api_dry_run_stop('updated', 'workspace_mcp_server', array('id' => (int) $server['id'], 'rules' => (object) $check['rules']));

    ws_api_as_owner(function () use ($server, $check) {
        ws_mcp_tools_save($server, $check['rules']);
    });

    api_ok(ws_api_mcp_server_present(ws_mcp_server((int) $server['id'])));
}
