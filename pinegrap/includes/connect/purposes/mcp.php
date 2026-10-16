<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Signing the site in to another service's MCP server (Workspace settings ›
 * MCP servers; includes/workspace/mcp.php).
 *
 * The providers are built at run time, one per server (mcp_<server id>), from
 * what the server's discovery found; the scopes are the ones the server asks
 * for, so the purpose names none of its own. The site's account (connected by
 * an administrator) serves the tools that read; each person's own account
 * serves them first for that person, and is the only one a tool that writes
 * runs with, a call at a time, once that person approved it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

return array(
    'label'           => lang('MCP servers'),
    'description'     => lang('Lets the Workspace use the tools of the service\'s MCP server under its alias.'),
    'owner'           => 'any',
    'scopes'          => array(),
    'provider_prefix' => 'mcp_',
);
