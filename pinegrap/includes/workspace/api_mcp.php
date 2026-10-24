<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - other services' MCP servers in the external API: the
 * ready-made connectors (mcp_catalogue.php), for an administrator's
 * application, with the ones the site already has marked. Reading only:
 * adding a server, its credential and its sign-in stay on the settings
 * screen.
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
