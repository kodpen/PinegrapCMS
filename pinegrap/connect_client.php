<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's client ID metadata document (draft-ietf-oauth-client-id-
 * metadata-document), for connected accounts (includes/fn/connect.php).
 *
 * An authorization server that reads these - an MCP server's, among others -
 * takes this file's address as the site's client_id and reads from it the
 * site's name and the one redirect address (connect.php), so the site is not
 * registered there first. The site has no secret there: PKCE proves it.
 *
 * Public and read-only: what it says is on every consent screen anyway.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Read by a machine: no session is opened for it.
define('PG_NO_SESSION', true);

$_SESSION = array();

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/client.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

echo json_encode(
	pg_mcp_client_client_metadata(pg_connect_redirect_uri(), URL_SCHEME . HOSTNAME_SETTING . PATH, pg_connect_client_metadata_url()),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
