<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace Settings, the old address. Every card is in the Site Settings
 * dialog now, under the Workspace heading (includes/settings/workspace_*.php),
 * so this address only sends the browser to the first Workspace category the
 * reader may open. The browser carries an old anchor (#ws-mail) over the
 * redirect, and the dialog opens on the card it names
 * (pg_settings_legacy_anchors()). A form left open on the old screen posts
 * here: it is refused, so nothing is written past the dialog's checks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

if (!$viewer['settings']) {
    log_activity(lang('access denied to the workspace settings'), $_SESSION['sessionusername'] ?? '');
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// A service's sign-in started on the old screen comes back to the MCP card.
$connected = (int) ($_GET['mcp_connected'] ?? 0);
$target = pg_settings_workspace_link(($connected > 0) ? 'ws-mcp' : '', $user);

if ($target === '') {
    log_activity(lang('access denied to the workspace settings'), $_SESSION['sessionusername'] ?? '');
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$target = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $target;

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    header('Allow: GET, HEAD');
    output_error(h(lang('The Workspace settings are in the Site Settings now. Nothing was saved.')) . ' <a href="' . h($target) . '">' . h(lang('Workspace Settings')) . '</a>', 405);
}

if ($connected > 0) {
    $hash = strpos($target, '#');
    $target = ($hash === false)
        ? $target . '?mcp_connected=' . $connected
        : substr($target, 0, $hash) . '?mcp_connected=' . $connected . substr($target, $hash);
}

go($target);
