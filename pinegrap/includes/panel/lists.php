<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the rows of the big list screens, one page at a time.
 *
 *   audit_log_changes          the changed fields of one audit trail line
 *   list_table_rows            a batch of rows of a list drawn by the list
 *                              table driver (includes/fn/list_table.php):
 *                              the pages, users, contacts, submitted
 *                              forms and products lists, the Site Log, the
 *                              audit trail, the firewall log, the commission
 *                              lists and the mail queue
 *                              (pg_list_table_definitions())
 *   list_table_sort            keeps the sort of a list the driver prints
 *                              whole (browser mode), set by a heading click
 *
 * Each action answers behind the gate of the screen it pages.
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

// The audit trail's gate: validate_area_access($user, 'manager') and the
// trail's table, answered in JSON. Each action checks the form token itself.
function pg_panel_audit_log_gate()
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = validate_user();

    if ((int) $user['role'] > 2) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    if (!pg_audit_ready()) {
        http_response_code(409);
        respond(array('status' => 'error', 'message' => lang('The database has not been upgraded yet. Run the software update first.')));
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/audit_list.php');
}

function pg_panel_audit_log_changes($request, $action)
{
    pg_panel_audit_log_gate();
    validate_token();

    $id = $request['id'] ?? '';
    $html = (is_scalar($id) && ctype_digit((string) $id)) ? pg_audit_list_line_changes((int) $id) : null;

    if ($html === false) {
        http_response_code(500);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    if ($html === null) {
        http_response_code(404);
        respond(array('status' => 'error', 'message' => lang('Not found')));
    }

    return array('status' => 'success', 'html' => $html);
}

function pg_panel_list_table_rows($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The general gate has checked the session (any_role). Each list's
    // source function is its screen's gate: it answers null for a user who
    // may not open the list.
    $user = validate_user();

    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $definition = pg_list_table_definition($request['list'] ?? '');

    if ($definition === null) {
        http_response_code(400);
        respond(array('status' => 'error', 'message' => lang('Invalid action.')));
    }

    require_once(PG_FUNCTIONS_DIR . '/' . $definition['file']);

    $params = (isset($request['params']) && is_array($request['params'])) ? $request['params'] : array();
    $source = call_user_func($definition['source'], $user, $params);

    if (!is_array($source)) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $source = pg_list_table_apply_sort($source, $request['sort'] ?? '');
    pg_list_table_keep_sort($source);

    $after = (isset($request['after']) && is_string($request['after']) && ($request['after'] !== '')) ? $request['after'] : null;
    $page = pg_list_table_page($source, pg_list_search_terms($request['search'] ?? ''), $after);

    if ($page === false) {
        http_response_code(500);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    return pg_list_table_answer($page, $source['columns']);
}

function pg_panel_list_table_sort($request, $action)
{
    // The general gate has checked the session (any_role). Nothing is read
    // here: the sort is only kept in the session, and the list's screen
    // checks it against its own columns when it reads it (pg_list_state()).
    validate_user();
    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    if (!pg_list_table_printed_sort($request['list'] ?? '', $request['sort'] ?? '')) {
        http_response_code(400);
        respond(array('status' => 'error', 'message' => lang('Invalid action.')));
    }

    return array('status' => 'success');
}
