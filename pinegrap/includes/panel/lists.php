<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the rows of the big list screens, one page at a time.
 *
 *   submitted_form_list_rows   a page of the submitted forms list
 *                              (view_submitted_forms.php)
 *   product_list_rows          a page of the products list (view_products.php)
 *   site_log_list_rows         a page of the Site Log (view_log.php)
 *   audit_log_list_rows        a page of the audit trail (view_audit_log.php)
 *   audit_log_changes          the changed fields of one audit trail line
 *   waf_log_list_rows          a page of the firewall log's events
 *                              (view_waf_log.php)
 *   list_table_rows            a batch of rows of a list drawn by the list
 *                              table driver (includes/fn/list_table.php):
 *                              the pages, users and contacts lists
 *                              (view_pages.php, view_users.php,
 *                              view_contacts.php), the commission lists
 *                              (view_commissions.php,
 *                              view_recurring_commission_profiles.php) and
 *                              the mail queue (mail_queue.php, both tabs)
 *   booking_list_rows          a page of the bookings agenda's list view
 *                              (view_bookings.php?view=list)
 *
 * Each action answers DataTables' page request (includes/fn/list_paging.php)
 * behind the gate of the screen it pages.
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

function pg_panel_submitted_form_list_rows($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The general gate has checked the session (any_role). The screen's own
    // gate is validate_forms_access(), answered here in JSON.
    $user = validate_user();

    if (((int) $user['role'] > 2) && ($user['manage_forms'] != true)) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');

    // The filters are the ones the screen kept in the session.
    pg_submitted_form_list_defaults(pg_submitted_form_list_custom_forms());

    $page = pg_submitted_form_list_page(
        $user,
        pg_submitted_form_list_filter($user),
        pg_list_search_terms($request['search'] ?? ''),
        pg_list_page_window($request['start'] ?? 0, $request['length'] ?? 100));

    if ($page === false) {
        http_response_code(500);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    return pg_list_rows_answer($request['draw'] ?? 0, $page['total'], $page['filtered'], $page['rows']);
}

function pg_panel_product_list_rows($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The general gate has checked the session (any_role). The screen's own
    // gate is validate_ecommerce_access(), answered here in JSON.
    $user = validate_user();

    if (((int) $user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    validate_token();

    // The list's cells are the product screen's (pg_pb_list_cells()).
    require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_list.php');

    // The sort is the one the screen kept in the session.
    $page = pg_product_list_page(
        pg_product_list_site_view(pg_product_list_filter_name($request['filter'] ?? '')),
        pg_list_search_terms($request['search'] ?? ''),
        pg_list_page_window($request['start'] ?? 0, $request['length'] ?? 100));

    if ($page === false) {
        http_response_code(500);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    return pg_list_rows_answer($request['draw'] ?? 0, $page['total'], $page['filtered'], $page['rows']);
}

function pg_panel_site_log_list_rows($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The general gate has checked the session (any_role). The screen's own
    // gate is validate_area_access($user, 'manager'), answered here in JSON.
    $user = validate_user();

    if ((int) $user['role'] > 2) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_log_list.php');

    // The range the screen shows; the sort is the one it kept in the session.
    $range = pg_site_log_list_range($request['start_time'] ?? null, $request['stop_time'] ?? null);

    if ($range === null) {
        http_response_code(400);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    $page = pg_site_log_list_page(
        $range,
        pg_list_search_terms($request['search'] ?? ''),
        pg_list_page_window($request['start'] ?? 0, $request['length'] ?? 100));

    if ($page === false) {
        http_response_code(500);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    return pg_list_rows_answer($request['draw'] ?? 0, $page['total'], $page['filtered'], $page['rows']);
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

function pg_panel_audit_log_list_rows($request, $action)
{
    pg_panel_audit_log_gate();
    validate_token();

    // The filters the screen shows (its address), checked again; the sort
    // is the one it kept in the session.
    $filters = (isset($request['filters']) && is_array($request['filters'])) ? $request['filters'] : array();

    $page = pg_audit_list_page(
        pg_audit_view_filters($filters),
        pg_list_search_terms($request['search'] ?? ''),
        pg_list_page_window($request['start'] ?? 0, $request['length'] ?? 100));

    if ($page === false) {
        http_response_code(500);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    return pg_list_rows_answer($request['draw'] ?? 0, $page['total'], $page['filtered'], $page['rows']);
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

function pg_panel_waf_log_list_rows($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The general gate has checked the session (any_role). The screen's own
    // gate is validate_area_access($user, 'manager'), answered here in JSON.
    $user = validate_user();

    if ((int) $user['role'] > 2) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/waf_log_list.php');

    // The filters the screen shows (its address), checked again; the sort
    // is the one it kept in the session.
    $filters = (isset($request['filters']) && is_array($request['filters'])) ? $request['filters'] : array();

    $page = pg_schema_has('waf_log') ? pg_waf_log_list_page(
        pg_waf_log_list_filters($filters),
        pg_list_search_terms($request['search'] ?? ''),
        pg_list_page_window($request['start'] ?? 0, $request['length'] ?? 100),
        time()) : false;

    if ($page === false) {
        http_response_code(500);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    return pg_list_rows_answer($request['draw'] ?? 0, $page['total'], $page['filtered'], $page['rows']);
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

function pg_panel_booking_list_rows($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The general gate has checked the session (any_role). The screen lets
    // roles 0-2 see every staff member and a role-3 staff member their own
    // bookings (pg_booking_agenda_user_scope()), answered here in JSON.
    $user = validate_user();

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

    if (!pg_booking_ready()) {
        http_response_code(409);
        respond(array('status' => 'error', 'message' => lang('The database has not been upgraded yet. Run the software update first.')));
    }

    $scope = pg_booking_agenda_user_scope($user);

    if ($scope === false) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    validate_token();

    $staff = pg_booking_agenda_staff();

    $page = pg_booking_agenda_list_page(
        pg_booking_agenda_list_filters($request['filters'] ?? array(), $scope, $staff),
        pg_list_search_terms($request['search'] ?? ''),
        pg_list_page_window($request['start'] ?? 0, $request['length'] ?? 100),
        $staff);

    if ($page === false) {
        http_response_code(500);
        respond(array('status' => 'error', 'message' => lang('Query failed.')));
    }

    return pg_list_rows_answer($request['draw'] ?? 0, $page['total'], $page['filtered'], $page['rows']);
}
