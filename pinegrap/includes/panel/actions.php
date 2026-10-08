<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the table behind api.php's switch ($action).
 *
 * An action listed in pg_panel_actions() lives in its own group file under
 * includes/panel/ as one function:
 *
 *     function pg_panel_<action>($request, $action)
 *
 * api.php calls pg_panel_dispatch() right before its switch; a listed action
 * is answered here and never reaches the switch, any other action falls
 * through to it.
 *
 * Unlike a dashboard widget, which only produces data and leaves the answer to
 * api.php, a panel action writes its own answer: the bodies respond() and exit
 * the way they did as switch cases. A handler may also return an array, which
 * the dispatcher respond()s, or nothing, which ends the request with an empty
 * 200 - what software_update_check and an unknown file_explorer type have
 * always answered.
 *
 * The access checks stay in the handlers, in the order they always ran, and
 * the general gate in api.php still runs first. That order decides how a
 * request without a session is refused - "Invalid login." from the general
 * gate, "Invalid token." where validate_token() comes first, a redirect where
 * validate_user() does - so one uniform check here would change answers
 * clients already rely on. The 'exempt' column records which actions the
 * general gate lets through to their own checks; 'token' and 'write' record
 * whether the handler checks the form token and whether it changes anything.
 * None of the three is enforced by the dispatcher.
 *
 * What the handlers inherit from api.php:
 *   - respond() and validate_token() stay defined in api.php (the barcode
 *     inventory endpoints define their own copies, so they cannot move to a
 *     file both would load);
 *   - $token stays in api.php's global scope, where validate_token() reads it
 *     with `global $token`;
 *   - the working directory is still api.php's directory, which relative paths
 *     such as 'data/backups/' rely on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The gate every handler file checks.
define('PG_PANEL_ACTIONS', true);

/**
 * @return array action => array('file', 'handler', 'exempt', 'token', 'write')
 */
function pg_panel_actions()
{
    return array(
        'software_backup' => array('file' => 'software.php', 'handler' => 'pg_panel_software_backup', 'exempt' => true, 'token' => true, 'write' => true),
        'software_update_check' => array('file' => 'software.php', 'handler' => 'pg_panel_software_update_check', 'exempt' => true, 'token' => true, 'write' => true),
        'software_update' => array('file' => 'software.php', 'handler' => 'pg_panel_software_update', 'exempt' => true, 'token' => true, 'write' => true),
    );
}

/**
 * @param mixed $action
 * @return array|null the action's row, or null when it is not in the table
 */
function pg_panel_action($action)
{
    if (!is_string($action)) {
        return null;
    }

    $actions = pg_panel_actions();

    return isset($actions[$action]) ? $actions[$action] : null;
}

/**
 * Answers a listed action and ends the request; returns false for any other
 * action so api.php's switch can take it.
 *
 * @param mixed $action
 * @param array $request
 * @return bool
 */
function pg_panel_dispatch($action, $request)
{
    $entry = pg_panel_action($action);

    if ($entry === null) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/panel/' . $entry['file']);

    if (!function_exists($entry['handler'])) {
        respond(array('status' => 'error', 'message' => 'Invalid action.'));
    }

    $result = $entry['handler']($request, $action);

    if (is_array($result)) {
        respond($result);
    }

    exit;
}
