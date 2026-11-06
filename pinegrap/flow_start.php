<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The web address that starts a flow whose start box is "When a web address
 * is called":
 *
 *   POST flow_start.php?flow=<id>&key=<secret>   a JSON object or form fields
 *
 * Called by systems outside the site, with the flow's secret in the address
 * (or the X-Flow-Key header) as the credential, outside the API's keys. The
 * work is in includes/workspace/flow_web_start.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: the key is the credential, and a panel cookie in the same
// browser must never decide what the address does. $_SESSION stays an array
// so the shared code that looks into it finds nothing rather than warning.
define('PG_NO_SESSION', true);

$_SESSION = array();

// Whatever the shared code prints on the way through would sit in front of
// the answer; it is dropped before the answer is sent.
ob_start();

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

ws_flow_web_receive();
