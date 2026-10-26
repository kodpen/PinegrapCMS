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
 * Whether the designer pane has anything to show for a page type: fields of
 * its own (a <type>_pages table) or a Layout Type. A standard page has
 * neither, nor do the few system types such as logout or error.
 *
 * @param string $page_type
 * @return bool
 */
function pg_page_type_pane_has_fields($page_type)
{
    return ($page_type !== 'standard')
        && (check_for_page_type_properties($page_type) || check_if_page_type_supports_layout($page_type));
}

/**
 * The classic editors of a page type's own forms and views, as the button bar
 * of edit_page.php offers them: the custom form's fields, the form list and
 * item views, the custom shipping and billing forms once switched on. The
 * caller adds send_to.
 *
 * @param string $page_type
 * @param int    $page_id
 * @return array  Each with 'label' and 'url'
 */
function pg_page_type_pane_links($page_type, $page_id)
{
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';
    $page_id = (int) $page_id;
    $stored = check_for_page_type_properties($page_type) ? get_page_type_properties($page_id, $page_type) : array();
    $links = array();

    switch ($page_type) {
        case 'custom form':
            $links[] = array('label' => lang('Edit Custom Form'), 'url' => $base . 'view_fields.php?page_id=' . $page_id);
            break;

        case 'form list view':
            $links[] = array('label' => lang('Edit Form List View'), 'url' => $base . 'edit_form_list_view.php?page_id=' . $page_id);
            break;

        case 'form item view':
            $links[] = array('label' => lang('Edit Form Item View'), 'url' => $base . 'edit_form_item_view.php?page_id=' . $page_id);
            break;

        case 'express order':
            if (!empty($stored['shipping_form'])) {
                $links[] = array('label' => lang('Edit Custom Shipping Form'), 'url' => $base . 'view_fields.php?page_id=' . $page_id . '&form_type=shipping');
            }

            if (($stored['form'] ?? '') == '1') {
                $links[] = array('label' => lang('Edit Custom Billing Form'), 'url' => $base . 'view_fields.php?page_id=' . $page_id . '&form_type=billing');
            }

            break;

        case 'shipping address and arrival':
            if (($stored['form'] ?? '') == '1') {
                $links[] = array('label' => lang('Edit Custom Shipping Form'), 'url' => $base . 'view_fields.php?page_id=' . $page_id);
            }

            break;

        case 'billing information':
            if (($stored['form'] ?? '') == '1') {
                $links[] = array('label' => lang('Edit Custom Billing Form'), 'url' => $base . 'view_fields.php?page_id=' . $page_id);
            }

            break;
    }

    return $links;
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
 * The properties a save writes for a page type, read from $_POST and held to
 * the access rules of edit_page.php: a basic user keeps the stored contact
 * group, private folder or custom form when the posted one is out of their
 * reach, and the offer fields stay as stored without the commerce right.
 * edit_page.php and the designer pane both save through it.
 *
 * @param string $page_type
 * @param int    $page_id
 * @param array  $user  The signed-in user (validate_user())
 * @return array 'properties'  the row for the type's table,
 *               'errors'      messages for what was kept as stored,
 *               'before'      the custom form switches as stored before the
 *                             save (express order 'shipping_form', 'form';
 *                             'shipping_address_and_arrival_form';
 *                             'billing_information_form'), to tell whether
 *                             one was just switched on
 */
function pg_page_type_properties_for_save($page_type, $page_id, $user)
{
    $properties = pg_page_form_type_properties_from_post($page_type, $page_id);
    $errors = array();
    $old_properties = array();
    $current_shipping_address_and_arrival_form = '';
    $current_billing_information_form = '';

    switch ($page_type) {
        case 'custom form':
            $new_contact_group_id = $properties['contact_group_id'];
            
            // if user has a user role, then verify that user has access to contact group that was selected
            if ($user['role'] == 3) {
                // get current contact group id
                $current_contact_group_id = db_value(
                    "SELECT contact_group_id
                    FROM custom_form_pages
                    WHERE page_id = '" . escape($page_id) . "'");
                
                // if contact group is trying to be changed
                // and a contact group was selected
                // and user does not have access to contact group,
                // then don't allow contact group to be changed
                if (($new_contact_group_id != $current_contact_group_id) && ($new_contact_group_id) && (validate_contact_group_access($user, $new_contact_group_id) == false)) {
                    $new_contact_group_id = $current_contact_group_id;
                    $errors[] = lang('The contact group for the custom form could not be changed, because you do not have access to the contact group you selected.');
                    log_activity(lang('access denied to change contact group for custom form because user did not have access to contact group'), $_SESSION['sessionusername']);
                }
            }

            $properties['contact_group_id'] = $new_contact_group_id;

            $new_private_folder_id = $properties['private_folder_id'];
            
            // If user has a user role, then verify that user has access to the selected private folder.
            if (USER_ROLE == 3) {
                $old_private_folder_id = db_value(
                    "SELECT private_folder_id
                    FROM custom_form_pages
                    WHERE page_id = '" . escape($page_id) . "'");
                
                // If the user is trying to change the private folder to a folder
                // that he/she does not have edit access to,
                // then don't allow folder to be set and log activity.
                if (
                    ($new_private_folder_id != $old_private_folder_id)
                    && ($new_private_folder_id)
                    && (check_edit_access($new_private_folder_id) == false)
                ) {
                    $new_private_folder_id = $old_private_folder_id;
                    $errors[] = lang('The private folder for the custom form could not be changed, because you do not have edit access to the folder you selected.');
                    log_activity(lang('access denied to change private folder for custom form because user did not have edit access to folder'), $_SESSION['sessionusername']);
                }
            }

            $properties['private_folder_id'] = $new_private_folder_id;

            // If commerce is enabled then save offer properties.
            if (ECOMMERCE) {
                // If user does not have access to commerce, then get old values,
                // because we might need to save them.
                if (!USER_MANAGE_ECOMMERCE) {
                    $offer_before = db_item(
                        "SELECT
                            offer,
                            offer_id
                        FROM custom_form_pages
                        WHERE page_id = '" . e($page_id) . "'");
                }

                // If the user has access to commerce or the user just disabled grant offer,
                // then save grant offer status.
                if (USER_MANAGE_ECOMMERCE || !($_POST['custom_form_offer'] ?? '')) {
                    $properties['offer'] = $_POST['custom_form_offer'] ?? 0;

                // Otherwise the user does not have access to commerce, so save old value.
                } else {
                    $properties['offer'] = ($offer_before['offer'] ?? '');
                }

                // If the user has access to commerce or the user just selected the blank offer,
                // then save the offer id from the user.
                if (USER_MANAGE_ECOMMERCE || !($_POST['custom_form_offer_id'] ?? '')) {
                    $properties['offer_id'] = $_POST['custom_form_offer_id'] ?? '';

                // Otherwise the user does not have access to commerce, so save old value.
                } else {
                    $properties['offer_id'] = ($offer_before['offer_id'] ?? '');
                }

                $properties['offer_days'] = $_POST['custom_form_offer_days'] ?? '';
                $properties['offer_eligibility'] = $_POST['custom_form_offer_eligibility'] ?? '';
            }
            
            break;
        
        case 'form list view':
        case 'form item view':
            $new_custom_form_page_id = $properties['custom_form_page_id'];
            $page_type_table_name = str_replace(' ', '_', $page_type) . '_pages';
            
            // if user has a user role, then verify that user has access to custom form that was selected
            if ($user['role'] == 3) {
                // get current custom form
                $current_custom_form_page_id = db_value(
                    "SELECT custom_form_page_id
                    FROM $page_type_table_name
                    WHERE
                        (page_id = '" . escape($page_id) . "')
                        AND (collection = 'a')");
                
                // get folder of new custom_form
                $new_custom_form_folder_id = db_value(
                    "SELECT page_folder
                    FROM page
                    WHERE page_id = '" . escape($new_custom_form_page_id) . "'");
                
                // if custom form is trying to be changed and user does not have access to custom form, don't allow custom form to be changed
                if (($new_custom_form_page_id != $current_custom_form_page_id) && (check_edit_access($new_custom_form_folder_id) == false)) {
                    $new_custom_form_page_id = $current_custom_form_page_id;

                    if ($page_type == 'form list view') {
                        $errors[] = lang('The custom form for the form list view could not be changed, because you do not have access to the custom form you selected.');
                        log_activity(lang('access denied to change custom form for form list view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                    } else {
                        $errors[] = lang('The custom form for the form item view could not be changed, because you do not have access to the custom form you selected.');
                        log_activity(lang('access denied to change custom form for form item view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                    }
                }
            }
            
            $properties['custom_form_page_id'] = $new_custom_form_page_id;

            // if there is not a record for this form list view yet, then set default values
            if (
                ($page_type == 'form list view')
                && (db_value("SELECT COUNT(*) FROM form_list_view_pages WHERE page_id = '" . escape($page_id) . "'") == 0)
            ) {
                $properties['search'] = 1;
                $properties['show_results_by_default'] = 1;
                $properties['maximum_number_of_results_per_page'] = 25;
            }
            
            break;
            
        case 'calendar view':
            // If the default_view property was changed, then forget the view
            // visitors last picked, so that the new default shows.
            $current_default_view = db_value(
                "SELECT default_view
                FROM calendar_view_pages
                WHERE page_id = '" . e($page_id) . "'");

            if ($current_default_view != $properties['default_view']) {
                unset($_SESSION['software']['calendar_views'][$page_id]['view']);
            }
            
            break;
        
        case 'express order':
            // Get current form values before we update it, so that we know later
            // if we should forward the user to the form designer or not.
            $old_properties = db_item(
                "SELECT shipping_form, form FROM express_order_pages
                WHERE page_id = '" . e($page_id) . "'");

            break;

        case 'shipping address and arrival':
            // get current form value before we update it, so that we know later if we should forward the user to the form designer or not
            $current_shipping_address_and_arrival_form = db_value("SELECT form FROM shipping_address_and_arrival_pages WHERE page_id = '" . escape($page_id) . "'");
            
            break;

        case 'billing information':
            // Get current form value before we update it, so that we know later
            // if we should forward the user to the form designer or not.
            $current_billing_information_form = db_value("SELECT form FROM billing_information_pages WHERE page_id = '" . escape($page_id) . "'");

            break;
    }

    return array(
        'properties' => $properties,
        'errors' => $errors,
        'before' => array(
            'shipping_form' => (string) ($old_properties['shipping_form'] ?? ''),
            'form' => (string) ($old_properties['form'] ?? ''),
            'shipping_address_and_arrival_form' => (string) $current_shipping_address_and_arrival_form,
            'billing_information_form' => (string) $current_billing_information_form,
        ),
    );
}

/**
 * Why a custom form page's posted settings cannot be saved, by field: its
 * form name taken by another page, or a setting edit_page.php refuses
 * (pg_cf_legacy_settings_errors()). Empty when they can.
 *
 * @param int $page_id
 * @return array  Field name => message
 */
function pg_page_type_custom_form_errors($page_id)
{
    $page_id = (int) $page_id;
    $errors = array();
    $form_name = (string) ($_POST['custom_form_form_name'] ?? '');

    if (db_value("SELECT COUNT(*) FROM custom_form_pages WHERE (form_name = '" . e($form_name) . "') AND (page_id != '" . $page_id . "')") > 0) {
        $errors['custom_form_form_name'] = lang('The form name that you entered is already in use. Please enter a different form name.');
    }

    $setting_errors = pg_cf_legacy_settings_errors(
        pg_page_form_type_properties_from_post('custom form', $page_id),
        function ($next_page_id) {
            return ($next_page_id > 0)
                && (db_value("SELECT COUNT(*) FROM page WHERE page_id = '" . (int) $next_page_id . "'") > 0)
                && !(function_exists('pg_recycle_page_binned') && pg_recycle_page_binned((int) $next_page_id));
        });

    return $errors + $setting_errors;
}

/**
 * Answers edit_page.php?properties=1 in JSON and ends the request.
 *
 * GET returns the page's System Options fields and the links to its forms'
 * editors; POST saves the fields and the
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

    if (!pg_page_type_pane_has_fields($page_type)) {
        $reply(200, array('ok' => false, 'message' => lang('This page type has no settings of its own.')));
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
            'links' => pg_page_type_pane_links($page_type, $page_id),
        ));
    }

    // The designer holds a page for one tab at a time; a save from another
    // one would write under the holder's feet.
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

    if (!pg_collab_may_edit_page((string) ($_POST['collab_key'] ?? ''), $page_id)) {
        $reply(409, array('ok' => false, 'message' => lang('These settings belong to the page somebody else is editing right now, so they are locked until they move on.')));
    }

    // A custom form's name is its address among the forms, and a switched on
    // e-mail needs something to send: refused before anything is written, as
    // edit_page.php does.
    if ($page_type == 'custom form') {
        $refused = pg_page_type_custom_form_errors($page_id);

        if ($refused) {
            $reply(422, array('ok' => false, 'message' => implode(' ', $refused), 'fields' => $refused));
        }

        $pretty_urls_before = check_if_pretty_urls_are_enabled($page_id);
    }

    $audit_before = pg_audit_fetch('page', $page_id);

    $checked = pg_page_type_properties_for_save($page_type, $page_id, $user);
    $properties = $checked['properties'];

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

    // Submitted forms take addresses of their own once pretty URLs come on.
    if (($page_type == 'custom form') && !$pretty_urls_before && check_if_pretty_urls_are_enabled($page_id)) {
        update_multiple_submitted_form_address_names($page_id);
    }

    // A custom shipping or billing form just switched on has no fields yet;
    // the classic screen went on to the form designer, the pane says where.
    $before = $checked['before'];
    $form_switched_on = false;

    switch ($page_type) {
        case 'express order':
            $form_switched_on = (!empty($properties['shipping_form']) && !$before['shipping_form'])
                || ((($properties['form'] ?? '') == 1) && ($before['form'] != 1));
            break;

        case 'shipping address and arrival':
            $form_switched_on = (($properties['form'] ?? '') == 1) && ($before['shipping_address_and_arrival_form'] != 1);
            break;

        case 'billing information':
            $form_switched_on = (($properties['form'] ?? '') == 1) && ($before['billing_information_form'] != 1);
            break;
    }

    $reply(200, array(
        'ok' => true,
        'message' => $form_switched_on ? lang('Saved. Add the fields of the new form with the link below.') : lang('Saved.'),
        'layout_type' => $layout_type,
        'warnings' => $checked['errors'],
        'links' => pg_page_type_pane_links($page_type, $page_id),
    ));
}
