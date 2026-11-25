<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - a collection item's fields corrected in place from the
 * Page Design Studio's Preview (includes/fn/record_edit.php).
 *   record_edit_read   The item's fields edited in place, each with its
 *                      control and stored value, and whether a change
 *                      would wait for approval.
 *   record_edit_save   Writes the changed fields through the item's own
 *                      checks, history and address rename; a field changed
 *                      meanwhile comes back as a conflict, unwritten.
 *
 * The general gate checks the session (any_role); who may write the item is
 * the item edit screen's own rule, checked here.
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

/**
 * The HTTP status of a read or save answer that is not ok: 404 for a
 * missing item, 403 for a refused person, 409 for a conflict or a change
 * that waits for approval, 422 for values refused; 200 otherwise. Pure.
 *
 * @param string $status
 * @return int
 */
function pg_panel_record_edit_http($status)
{
    switch ((string) $status) {
        case 'missing':
            return 404;
        case 'denied':
            return 403;
        case 'conflict':
        case 'approval':
            return 409;
        case 'invalid':
        case 'refused':
            return 422;
    }

    return 200;
}

/**
 * @param array  $request  item_id
 * @param string $action
 * @return array
 */
function pg_panel_record_edit_read($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = validate_user();

    validate_token();

    $answer = pg_rec_edit_read($user, is_scalar($request['item_id'] ?? null) ? (int) $request['item_id'] : 0);

    http_response_code(pg_panel_record_edit_http($answer['status']));

    return $answer;
}

/**
 * @param array  $request  item_id, changes (list of field_id, value, base)
 * @param string $action
 * @return array
 */
function pg_panel_record_edit_save($request, $action)
{
    global $user;

    $user = validate_user();

    validate_token();

    $answer = pg_rec_edit_save($user, is_scalar($request['item_id'] ?? null) ? (int) $request['item_id'] : 0, $request['changes'] ?? null);

    http_response_code(pg_panel_record_edit_http($answer['status']));

    return $answer;
}
