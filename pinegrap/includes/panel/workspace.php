<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the workspace.
 *
 *   pg_panel_ws  ws_* - every workspace action
 *                (includes/workspace/actions.php)
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_ws($request, $action)
{
    // Workspace
    // Session + CSRF token here; who may read, post, assign and plan is
    // decided in includes/workspace/actions.php.
    if (!USER_LOGGED_IN) {
        respond(panel_login_refusal());
    }

    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

    respond(ws_handle_action($action, $request));
}
