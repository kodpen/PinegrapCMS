<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The users a panel user picker finds as the operator types: the source of a
 * server-search combobox (data-pg-source="user_search_action.php").
 *
 *   GET ?q=...                 users whose username, e-mail address or
 *                              name holds every word typed
 *   GET ?q=...&role=staff      the roles below 3 only
 *   GET ?q=...&value=username  the username as the stored value instead of
 *                              the user id (a field that posts a username)
 *
 * Answers {results: [{id, text}]}: the value the field stores and "First
 * Last (username)", at most 30, only among the accounts the viewer sees on
 * the users list. It reads only, so it takes no form token, and it answers
 * JSON on a refusal too: the combobox says the error text in its menu.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_picker.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

// The rule of the users list (view_users.php asks for 'manager'), checked
// before validate_user(), which would answer an HTML login page.
if (!defined('USER_LOGGED_IN') || (USER_LOGGED_IN !== true)) {
    http_response_code(403);
    echo json_encode(array('error' => lang('Your session has expired. Reload the page and sign in again.'), 'results' => array()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

if (!pg_user_picker_may_search(USER_ROLE)) {
    http_response_code(403);
    echo json_encode(array('error' => lang('Access denied'), 'results' => array()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';

$results = pg_user_picker_search($q, USER_ROLE, array(
    'staff' => (is_string($_GET['role'] ?? null) && ($_GET['role'] === 'staff')),
    'value' => (is_string($_GET['value'] ?? null) && ($_GET['value'] === 'username')) ? 'username' : 'id',
    'limit' => 30));

echo json_encode(array('results' => $results), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
exit();
