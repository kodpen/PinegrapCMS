<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - contact groups filled by rules (includes/fn/audiences.php).
 *   audience_refresh   Refresh now: recomputes one dynamic group for a few
 *                      seconds a request; the screen asks again until the
 *                      answer says done, as the search index window does.
 *   audience_count     The rule editor's live count: how many contacts the
 *                      rules being edited select now (nothing is written).
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

// Seconds of one Refresh now request.
define('PG_AUDIENCE_REFRESH_SECONDS', 5);

function pg_panel_audience_refresh($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The general gate has checked the session (any_role). The screen's own
    // gates are validate_contacts_access() and the group's access, answered
    // here in JSON.
    $user = validate_user();
    $group_id = (int) ($request['contact_group_id'] ?? 0);

    if ((validate_contacts_access($user, true) != true) || ($group_id <= 0) || (validate_contact_group_access($user, $group_id) != true)) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    validate_token();

    $run = pg_audience_recompute($group_id, PG_AUDIENCE_REFRESH_SECONDS);

    if ($run === null) {
        http_response_code(409);
        respond(array('status' => 'error', 'message' => lang('This contact group is not filled by rules, or it could not be refreshed.')));
    }

    $count = db_value("SELECT COUNT(*) FROM contacts_contact_groups_xref WHERE contact_group_id = '" . $group_id . "'");

    return array(
        'status' => 'success',
        'done' => $run['done'],
        'added' => $run['added'],
        'removed' => $run['removed'],
        'members' => (int) $count,
    );
}

function pg_panel_audience_count($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    // The general gate has checked the session (any_role). Rules read orders,
    // consents and visits, so the editor is a Manager's or above, as the
    // switch on the screen.
    $user = validate_user();

    if (($user['role'] >= 3) || (validate_contacts_access($user, true) != true)) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    validate_token();

    $rules = $request['rules'] ?? array();
    $count = pg_audience_count(is_array($rules) || is_string($rules) ? $rules : array());

    return array(
        'status' => 'success',
        'count' => $count,
        'text' => pg_audience_count_text($count),
    );
}
