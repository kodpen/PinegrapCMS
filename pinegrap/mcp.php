<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The MCP server (includes/mcp/). An AI application - Claude, ChatGPT, an
// editor's assistant - is given this address, signs a person in through the
// site's OAuth server (oauth.php) and then works with the site through tools
// that are the external API's own endpoints:
//
//   POST mcp.php   {"jsonrpc":"2.0","id":1,"method":"tools/list"}
//
// One address and no path after it, so no rewrite rule is needed.

define('PG_MCP_ENTRY', true);

// The external API's code runs every tool, with its credentials, scopes, rate
// limit and request log.
define('PG_API_ENTRY', true);

// The resource OAuth tokens must have been issued for (RFC 8707): a token for
// integration.php is refused here, and one for here is refused there.
define('PG_OAUTH_RESOURCE', 'mcp');

$GLOBALS['pg_api_started_at'] = microtime(true);

// No session, for the reason integration.php gives: credentials travel with
// every request, and a panel cookie in the same browser must never decide what
// a call may do.
define('PG_NO_SESSION', true);

$_SESSION = array();

// Whatever the shared code prints on the way through would break the JSON.
ob_start();

include('init.php');

require_once(dirname(__FILE__) . '/includes/api/bootstrap.php');
require_once(dirname(__FILE__) . '/includes/mcp/protocol.php');
require_once(dirname(__FILE__) . '/includes/mcp/tools.php');

pg_mcp_run();
