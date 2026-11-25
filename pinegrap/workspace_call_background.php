<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Hands a Workspace call's background-effect files (the tasks-vision
 * runtime and the segmentation model the Calls card fetched into
 * data/call_background/) to the call window's loader, after checking each
 * against its pinned digest (includes/workspace/calls_background.php).
 *
 * No session: the files are the same for every caller, and the module and
 * WASM loader are fetched by the browser without one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

define('PG_NO_SESSION', true);

$_SESSION = array();

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

ws_call_background_serve(isset($_GET['f']) ? $_GET['f'] : '');
