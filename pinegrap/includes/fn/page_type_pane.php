<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page type settings of a classic page that lives in a visual design.
 *
 * edit_page.php sends every visual-design page to the designer, so the
 * page's type fields (the "System Options" card) and its Layout Type radio
 * are reached from the designer's settings modal instead: the pane there
 * reads and saves them through edit_page.php?properties=1, which answers in
 * JSON. The rights are those of edit_page.php: edit access to the page's
 * folder and the right to set the page's type.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The page types whose fields the designer pane shows and saves. The other
 * types carry rich-text editors, show/hide scripts, per-field access checks
 * or a redirect to the form designer that the pane does not handle yet.
 *
 * @return string[]
 */
function pg_page_type_pane_types()
{
    return array(
        'email a friend',
        'folder view',
        'photo gallery',
        'search results',
        'update address book',
        'custom form confirmation',
        'catalog',
        'catalog detail',
        'order form',
        'shipping method',
        'order receipt',
        'affiliate sign up form',
        'calendar event view',
    );
}

/**
 * Whether a user may set a page to the given type: roles above basic user may
 * set any type, a basic user needs the type's own right. edit_page.php and
 * the designer pane ask the same question.
 *
 * @param array  $user       The signed-in user (validate_user())
 * @param string $page_type
 * @return bool
 */
function pg_page_type_user_may_set($user, $page_type)
{
    if ((int) $user['role'] < 3) {
        return true;
    }

    if ($page_type == 'standard') {
        return true;
    }

    // Calendar pages also need the calendars right.
    if (($page_type == 'calendar view') || ($page_type == 'calendar event view')) {
        if (empty($user['manage_calendars'])) {
            return false;
        }
    }

    $rights = array(
        'email a friend' => 'set_page_type_email_a_friend',
        'folder view' => 'set_page_type_folder_view',
        'photo gallery' => 'set_page_type_photo_gallery',
        'catalog' => 'set_page_type_catalog',
        'catalog detail' => 'set_page_type_catalog_detail',
        'express order' => 'set_page_type_express_order',
        'order form' => 'set_page_type_order_form',
        'shopping cart' => 'set_page_type_shopping_cart',
        'shipping address and arrival' => 'set_page_type_shipping_address_and_arrival',
        'shipping method' => 'set_page_type_shipping_method',
        'billing information' => 'set_page_type_billing_information',
        'order preview' => 'set_page_type_order_preview',
        'order receipt' => 'set_page_type_order_receipt',
        'custom form' => 'set_page_type_custom_form',
        'custom form confirmation' => 'set_page_type_custom_form_confirmation',
        'form list view' => 'set_page_type_form_list_view',
        'form item view' => 'set_page_type_form_item_view',
        'form view directory' => 'set_page_type_form_view_directory',
        'calendar view' => 'set_page_type_calendar_view',
        'calendar event view' => 'set_page_type_calendar_event_view',
    );

    return isset($rights[$page_type]) && !empty($user[$rights[$page_type]]);
}

/**
 * The layout type a save leaves on the page, as edit_page.php decides it: a
 * type without a layout keeps what it has; administrators and designers pick
 * either one, everybody else may only go back to the system layout.
 *
 * @param string $page_type
 * @param mixed  $posted   The posted layout_type
 * @param string $current  The stored layout_type
 * @param int    $role     The user's role
 * @return string
 */
function pg_page_type_pane_layout_type($page_type, $posted, $current, $role)
{
    if (!is_string($posted) || !in_array($posted, array('system', 'custom'), true)) {
        return $current;
    }

    if (!check_if_page_type_supports_layout($page_type)) {
        return $current;
    }

    if (((int) $role < 2) || ($posted == 'system')) {
        return $posted;
    }

    return $current;
}

/**
 * Answers edit_page.php?properties=1 in JSON and ends the request.
 *
 * GET returns the page's System Options fields; POST saves them and the
 * layout type, nothing else of the page. A refusal is JSON with a status,
 * never an HTML error page.
 *
 * @param array $user     The signed-in user (validate_user())
 * @param int   $page_id
 */
function pg_page_type_pane_request($user, $page_id)
{
    $reply = function ($status, $data) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo encode_json($data);
        exit();
    };

    $page_id = (int) $page_id;
    $row = ($page_id > 0)
        ? db_item("SELECT page_id, page_name, page_folder, page_type, layout_type FROM page WHERE page_id = '" . $page_id . "'")
        : null;

    if (!is_array($row)) {
        $reply(404, array('ok' => false, 'message' => lang('Record not found.')));
    }

    $is_post = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');

    // The form token before anything else of a save is looked at.
    if ($is_post) {
        $session_token = (string) ($_SESSION['software']['token'] ?? '');
        $post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';

        if (($session_token === '') || !hash_equals($session_token, $post_token)) {
            $reply(403, array('ok' => false, 'message' => lang('Your session has expired. Please sign in again.')));
        }
    }

    if (check_edit_access($row['page_folder']) == false) {
        log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
        $reply(403, array('ok' => false, 'message' => lang('Access denied.')));
    }

    $page_type = (string) $row['page_type'];

    if (!pg_page_type_user_may_set($user, $page_type)) {
        $reply(403, array('ok' => false, 'message' => lang('Access denied.')));
    }

    if (!in_array($page_type, pg_page_type_pane_types(), true)) {
        $reply(200, array(
            'ok' => false,
            'later' => true,
            'message' => lang('The settings of this page type cannot be changed in the visual editor yet.'),
        ));
    }

    if (!$is_post) {
        $properties = array();

        if (check_for_page_type_properties($page_type)) {
            $properties[$page_type] = get_page_type_properties($page_id, $page_type);
        }

        $fields = pg_page_form_type_fields($page_type, $row['layout_type'], $properties, $user, $page_id);

        $reply(200, array(
            'ok' => true,
            'page_id' => $page_id,
            'page_type' => $page_type,
            'page_type_name' => (string) get_page_type_name($page_type),
            'html' => $fields['properties'],
        ));
    }

    // The designer holds a page for one tab at a time; a save from another
    // one would write under the holder's feet.
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

    if (!pg_collab_may_edit_page((string) ($_POST['collab_key'] ?? ''), $page_id)) {
        $reply(409, array('ok' => false, 'message' => lang('These settings belong to the page somebody else is editing right now, so they are locked until they move on.')));
    }

    $audit_before = pg_audit_fetch('page', $page_id);

    $properties = pg_page_form_type_properties_from_post($page_type, $page_id);

    pg_page_form_save_type_links($page_type, $page_id, $user);

    if (check_for_page_type_properties($page_type)) {
        create_or_update_page_type_record($page_type, $properties);
    }

    $layout_type = pg_page_type_pane_layout_type($page_type, $_POST['layout_type'] ?? null, (string) $row['layout_type'], USER_ROLE);

    db("UPDATE page
        SET
            layout_type = '" . e($layout_type) . "',
            page_timestamp = UNIX_TIMESTAMP(),
            page_user = '" . (int) $user['id'] . "'
        WHERE page_id = '" . $page_id . "'");

    log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('page'), $row['page_name']))), $_SESSION['sessionusername']);

    pg_audit_record('update', 'page', $page_id, $audit_before);

    $reply(200, array('ok' => true, 'layout_type' => $layout_type));
}
