<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The external API. Everything an outside system reads or writes comes through
// here:
//
//   GET  integration.php/products?updated_since=2026-09-01
//   POST integration.php/products/12/inventory   {"op":"adjust","quantity":-1}
//
// The resource is in the path, carried by PATH_INFO, so no rewrite rule is
// needed on any server the software is installed on. The verb says whether a
// call reads or writes. There is no version segment: nothing was ever built
// against the endpoint this replaces, so there is no older shape to tell this
// one apart from. The version is reported by the meta endpoint.
//
// Not to be confused with api.php, which is the control panel's own AJAX
// endpoint and is not part of this surface.

define('PG_API_ENTRY', true);

// Timing for the log line, taken before anything else so it measures the whole
// request rather than the part after the framework loaded.
$GLOBALS['pg_api_started_at'] = microtime(true);

// No session, on purpose.
//
// This endpoint authenticates with an application's own key and secret. If it
// also started a session, an operator signed in to the panel in the same browser
// would carry their own rights into a call that presented no credentials at all,
// and every anonymous request would leave a session file behind. $_SESSION is
// still an array so the shared code that looks into it finds nothing rather than
// warning about a missing superglobal.
define('PG_NO_SESSION', true);

$_SESSION = array();

// Anything the shared code prints on the way through - a notice, a warning from
// a host with display_errors on - would otherwise sit in front of the JSON and
// make the whole response unparseable. Held here and discarded when the answer
// is sent.
ob_start();

include('init.php');

require_once(dirname(__FILE__) . '/includes/api/bootstrap.php');

api_run();
