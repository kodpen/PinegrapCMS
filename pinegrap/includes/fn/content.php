<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Frontend content: menus, submitted form content, catalog search and item URLs, image ids, layout rendering.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
// Outputs dynamic menu system using values from the database.
// We don't currently support multiple menu items being set as the current menu item
// because it causes problems for the accordion menu.  The logic for the accordion menu
// assumes there is only one current menu item.  If multiple are set,
// then menu items do not collapse properly.  We should eventually spend some time and resolve this
// in order to add support for multiple current menu items.
function get_menu_content($menu_id, $parent_id = 0, $current_menu_item_id = 0, $edit_mode = false, $edit_context = array(), $menu = null)
{
    // Fetch menu row only on the first (top-level) call; pass it down to recursive calls to avoid re-querying.
    if ($menu === null) {
        $menu = db_item("SELECT name, effect, class, active_item_class FROM menus WHERE id = '" . $menu_id . "'");
    }
    $output = '';
    // if parent_id is 0 then this is the first level of the menu
    if ($parent_id == 0) {
        $class = '';
        // If there is a custom class for the menu, then add a space on the beginning for
        // separation from the software_menu class.
        if ($menu['class']) {
            $class = ' ' . $menu['class'];
        }
        $output .= '<ul id="software_menu_' . $menu['name'] . '" class="software_menu' . h($class) . '">';
        // else, this is the second level of the menu
    } else {
        $output .= '<ul>';
    }
    // get menu items
    $query = "SELECT

           menu_items.id,

           menu_items.name,

           page.page_name AS link_page_name,

           page.page_folder AS link_page_folder_id,

           menu_items.link_url,

           menu_items.link_target,

           menu_items.security,

           menu_items.class

        FROM menu_items

        LEFT JOIN page ON page.page_id = menu_items.link_page_id

        WHERE

           (menu_items.parent_id = '" . escape($parent_id) . "')

           AND (menu_items.menu_id = '" . $menu_id . "')

        ORDER BY menu_items.sort_order";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $menu_items = array();
    // loop through all menu items in order to build array
    while ($row = mysqli_fetch_assoc($result)) {
        $menu_items[] = $row;
    }
    $first_menu_item = true;
    $output_edit_from = ($edit_mode && isset($edit_context['from'])) ? urlencode($edit_context['from']) : '';
    $output_edit_send_to = ($edit_mode && isset($edit_context['send_to'])) ? urlencode($edit_context['send_to']) : '';
    // loop through all menu items in order to get content
    foreach ($menu_items as $menu_item) {
        // If security is disabled,
        // or if this menu item is not connected to a page
        // or this visitor has access to view this menu item's page,
        // then show the menu item and its child items, if any exist.
        if (($menu_item['security'] == 0) || ($menu_item['link_page_name'] == '') || (check_view_access($menu_item['link_page_folder_id']) == true)) {
            // assume that sub menus do not exist, until we find out otherwise
            $sub_menu_exists = false;
            // find out if sub menu exists
            $query = "SELECT COUNT(*) FROM menu_items WHERE parent_id = '" . $menu_item['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $row = mysqli_fetch_row($result);
            // if sub menu exists, remember that
            if ($row[0] > 0) {
                $sub_menu_exists = true;
            }
            $output_link_href = '';
            $output_link_target = '';
            // if this is not an accordion menu or a sub-menu does not exist and there is a link, then prepare link
            if ((($menu['effect'] != 'Accordion') || ($sub_menu_exists == false)) && (($menu_item['link_page_name'] != '') || ($menu_item['link_url'] != ''))) {
                // if there is a link page name, then prepare link to page
                if ($menu_item['link_page_name']) {
                    $output_link_href = OUTPUT_PATH . h($menu_item['link_page_name']);
                    // else there is not a link page name, so prepare link to URL
                } else {
                    $output_link_href = h($menu_item['link_url']);
                }
                // if link should open in a new window, then prepare target
                if ($menu_item['link_target'] == 'New Window') {
                    $output_link_target = ' target="_blank"';
                }
                // else there should not be a link, so disable link
            } else {
                $output_link_href = 'javascript:void(0)';
            }
            // Create variable that we will use to build all of the classes that should exist for this menu item.
            $output_menu_item_class = '';
            // If this is a top-level menu item, then add class for that.
            if ($parent_id == 0) {
                $output_menu_item_class .= 'top_level';
            }
            // If this menu item is for the page that the visitor is currently on, then output current class.
            if ($menu_item['id'] == $current_menu_item_id) {
                // If there are other classes for this menu item, then add space for separation.
                if ($output_menu_item_class != '') {
                    $output_menu_item_class .= ' ';
                }
                $output_menu_item_class .= 'current';
                if ($menu['active_item_class']) {
                    $output_menu_item_class .= ' ' . $menu['active_item_class'];
                }
            }
            // If this is the first menu item at this level, then output first class.
            if ($first_menu_item == true) {
                // If there are other classes for this menu item, then add space for separation.
                if ($output_menu_item_class != '') {
                    $output_menu_item_class .= ' ';
                }
                $output_menu_item_class .= 'first';
                // Update variable so that we will know that the next menu item is not first.
                $first_menu_item = false;
            }
            // If this menu item has a menu under it, then output parent class.
            if ($sub_menu_exists == true) {
                // If there are other classes for this menu item, then add space for separation.
                if ($output_menu_item_class != '') {
                    $output_menu_item_class .= ' ';
                }
                $output_menu_item_class .= 'parent';
            }
            // If there is a custom class, append it.
            if ($menu_item['class'] != '') {
                if ($output_menu_item_class != '') {
                    $output_menu_item_class .= ' ';
                }
                $output_menu_item_class .= $menu_item['class'];
            }
            // If there is at least one class for the menu item, then prepare attribute.
            $output_li_class_attr = '';
            if ($output_menu_item_class != '') {
                $output_li_class_attr = ' class="' . h($output_menu_item_class) . '"';
            }
            // add opening li tag and optional edit button (in edit mode) and anchor to output
            $output .= '<li id="software_menu_item_' . $menu_item['id'] . '"' . $output_li_class_attr . '>';
            if ($edit_mode) {
                $output .= '<a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_menu_item.php?id=' . $menu_item['id'] . '&from=' . $output_edit_from . '&send_to=' . h($output_edit_send_to) . '" class="software_pinegrap_inline_edit_button design" title="' . lang('Edit Menu Item') . ': ' . h($menu_item['name']) . '"><i class="bi bi-pencil"></i></a>';
            }
            $output .= '<a href="' . $output_link_href . '"' . $output_link_target . $output_li_class_attr . '>' . h($menu_item['name']) . '</a>';
            // if sub menu exists, then add sub menu content to output
            if ($sub_menu_exists == true) {
                $output .= get_menu_content($menu_id, $menu_item['id'], $current_menu_item_id, $edit_mode, $edit_context, $menu);
            }
            // add closing li tag to output
            $output .= '</li>';
        }
    }
    // add closing ul tag
    $output .= '</ul>';
    return $output;
}
// get options for parent menu item liveform pick list
function get_menu_item_options($menu_id, $exception_menu_item_id = 0, $parent_id = 0, $level = 1, $selected_id = 0)
{
    $options = '';
    // if this is the first level, then add -None- option
    if ($level == 1) {
        $options .= '<option value="0">-' . lang('None') . '-</option>';
    }
    // get menu items
    $query = "SELECT

            id,

            name

        FROM menu_items

        WHERE

            (menu_id = '" . escape($menu_id) . "')

            AND (id != '" . escape($exception_menu_item_id) . "')

            AND (parent_id = '" . escape($parent_id) . "')

        ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $menu_items = array();
    // loop through menu items in order to build array
    while ($row = mysqli_fetch_assoc($result)) {
        $menu_items[] = $row;
    }
    // loop through menu items in order to get options
    foreach ($menu_items as $menu_item) {
        $indent = '';
        // create indent
        for ($i = 2; $i <= $level; $i++) {
            $indent .= '&nbsp;&nbsp;&nbsp;&nbsp;';
        }
        if ($selected_id == $menu_item['id']) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $options .= '<option value="' . $menu_item['id'] . '"' . $selected . '>' . $indent . h($menu_item['name']) . '</option>';
        // determine if there is a sub menu to display
        $query = "SELECT COUNT(*)

            FROM menu_items

            WHERE

                (parent_id = '" . $menu_item['id'] . "')

                AND (id != '" . escape($exception_menu_item_id) . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_row($result);
        // if there is a sub menu, then get options for sub menu
        if ($row[0] > 0) {
            // get options for sub menu
            $sub_options = get_menu_item_options($menu_id, $exception_menu_item_id, $menu_item['id'], $level + 1, $selected_id);
            // add sub menu options to options
            $options .= $sub_options;
        }
    }
    return $options;
}
// get page options for liveform pick list
// $access: "edit" or "view".  This defines whether only pages should be shown where
// user has edit access or if pages that user has view access should be shown.
function get_page_options($page_id = '', $page_type = '', $access = 'edit')
{
    global $user;
    // If access is set to "edit", then get folders that user has edit access to.
    // We will use this further below in loop.
    if ($access == 'edit') {
        $folders_that_user_has_access_to = array();
        // if user is a basic user, then get folders that user has access to
        if ($user['role'] == 3) {
            $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
        }
    }
    $page_options = array();
    // Setup first option
    $page_options['-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('Page')))) . '-'] = '';
    // if a page type was given, prepare to only return pages with that page type
    if ($page_type) {
        $where = "WHERE page.page_type = '" . escape($page_type) . "'";
    } else {
        $where = '';
    }
    // if there is not already a where statement, then output the starting where part
    if ($where == '') {
        $where .= 'WHERE ';
        // else add and so that we can add the where condition
    } else {
        $where .= ' AND ';
    }
    $where .= '(folder.folder_archived = "0")';
    // get pages
    $query = "SELECT

            page.page_id,

            page.page_name,

            page.page_folder

        FROM page

        LEFT JOIN folder ON page.page_folder = folder.folder_id

        $where

        ORDER BY page.page_name";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        // If access is set to "edit" and the user has edit access to this page,
        // or if access is set to "view" and the user has view access to this page,
        // then include it
        if ((($access == 'edit') && (check_folder_access_in_array($row['page_folder'], $folders_that_user_has_access_to) == true)) || (($access == 'view') && (check_view_access($row['page_folder']) == true))) {
            $page_options[h($row['page_name'])] = $row['page_id'];
        }
    }
    return $page_options;
}
// get form info (i.e. field content, wysiwyg fields, file upload exists)
function get_form_info($page_id, $product_id, $order_item_id, $quantity_number, $label_column_width, $office_use_only, $liveform, $interface, $editable = false, $device_type = 'desktop', $folder_id_for_default_value = 0, $reference_code_field_id = 0, $express_order_form_type = '', $prefix = '')
{
    $form_info = array();
    // if page id is not equal to 0, then this is a page form
    if ($page_id != 0) {
        // get page type
        $query = "SELECT page_type FROM page WHERE page_id = '" . escape($page_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $page_type = $row['page_type'];
        $form_type = '';
        // get the form type by looking at the page type
        switch ($page_type) {
            case 'custom form':
                $form_type = 'custom';
                break;
            case 'express order':
                if ($express_order_form_type == 'shipping') {
                    $form_type = 'shipping';
                } else {
                    $form_type = 'billing';
                }
                break;
            case 'billing information':
                $form_type = 'billing';
                break;
            case 'shipping address and arrival':
                $form_type = 'shipping';
                break;
        }
        // else page id is 0, so this is a product form
    } else {
        $form_type = 'product';
    }
    $connect_to_contact = '';
    // if there is a connect to contact passed in the URL string then prepare and save it
    if (isset($_GET['connect_to_contact']) == true) {
        $connect_to_contact = trim(mb_strtolower($_GET['connect_to_contact']));
    }
    $contact = array();
    // If this form is a custom form, and if connect to contact is on,
    // and if user is logged in, then get contact info, because fields might need to be prefilled.
    if (($form_type == 'custom') && ($connect_to_contact != 'false') && (USER_LOGGED_IN == true)) {
        $query = "SELECT contacts.*

            FROM user

            LEFT JOIN contacts ON user.user_contact = contacts.id

            WHERE user.user_username = '" . escape($_SESSION['sessionusername']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $contact = mysqli_fetch_assoc($result);
    }
    $office_use_only_target_options = array();
    // If office use only fields will not appear on form, then get triggers for them,
    // so we can limit the options that appear in target pick lists.
    if ($office_use_only == false) {
        // if this is a product form, then prepare SQL
        if ($form_type == 'product') {
            // form_type is pinned as well as product_id: since 2026.4 the table
            // also holds product_group_id rows (a variant set's form template)
            // whose product_id is 0. They must never reach a rendered form.
            $sql_where = "(product_id = '" . e($product_id) . "') AND (form_fields.form_type = 'product')";
            // else this is a form for a page, so prepare SQL in a different way
        } else {
            $sql_where = "(page_id = '" . e($page_id) . "')";
            if ($page_type == 'express order') {
                $sql_where .= " AND (form_type = '" . e($form_type) . "')";
            }
        }
        $office_use_only_fields = db_items("SELECT

                id,

                contact_field,

                default_value,

                use_folder_name_for_default_value

            FROM form_fields

            WHERE

                $sql_where

                AND (type = 'pick list')

                AND (office_use_only = '1')");
        // Loop through the office use only fields in order to check if we need to apply triggers.
        foreach ($office_use_only_fields as $office_use_only_field) {
            // Get default value for office use only field so we can find if there is a trigger for this value.
            $default_value = '';
            // If field is set to use folder name for default value, then use it.
            if ($office_use_only_field['use_folder_name_for_default_value'] == 1) {
                $default_value = db_value("SELECT folder_name FROM folder WHERE folder_id = '" . escape($folder_id_for_default_value) . "'");
                // Otherwise use default value from field.
            } else {
                $default_value = $office_use_only_field['default_value'];
            }
            // Check if there is an option for this default value that has a trigger.
            $option = db_item("SELECT

                    id,

                    target_form_field_id

                FROM form_field_options

                WHERE

                    (form_field_id = '" . $office_use_only_field['id'] . "')

                    AND (value = '$default_value')

                    AND (target_form_field_id != '0')");
            // If an option with a trigger for the default value was found, then add target options to array.
            if ($option != '') {
                $target_options = db_items("SELECT value FROM target_options WHERE trigger_option_id = '" . $option['id'] . "'");
                $office_use_only_target_options[$option['target_form_field_id']] = array();
                // Loop through target options in order to add them to array.
                foreach ($target_options as $target_option) {
                    $office_use_only_target_options[$option['target_form_field_id']][] = mb_strtolower($target_option['value']);
                }
            }
        }
    }
    $sql_custom_form_fields = '';
    $sql_where = '';
    $sql_where_office_use_only = '';
    // if this is a product form, then prepare SQL
    if ($form_type == 'product') {
        // See the note above: template rows carry product_id 0 and are excluded
        // by pinning form_type.
        $sql_where = "product_id = '" . e($product_id) . "' AND form_type = 'product'";
        // else this is a form for a page, so prepare SQL in a different way
    } else {
        $sql_where = "(page_id = '" . e($page_id) . "')";
        if ($page_type == 'express order') {
            $sql_where .= " AND (form_type = '" . e($form_type) . "')";
        }
        // if this is a custom form, then prepare other SQL
        if ($form_type == 'custom') {
            $sql_custom_form_fields = ",

                contact_field,

                office_use_only";
            // if office use only fields should not be displayed, prepare to only select non office use only fields
            if ($office_use_only == false) {
                $sql_where_office_use_only = " AND (office_use_only = 0)";
            }
        }
    }
    // get all fields for this form
    $query = "SELECT

            id,

            name,

            label,

            type,

            required,

            information,

            default_value,

            use_folder_name_for_default_value,

            size,

            maxlength,

            wysiwyg,

            `rows`, # Backticks for reserved word.

            cols,

            multiple,

            spacing_above,

            spacing_below

            $sql_custom_form_fields

        FROM form_fields

        WHERE

            ($sql_where)

            $sql_where_office_use_only

        ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $fields = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $fields[] = $row;
    }
    $label_column_width_added = false;
    $form_info['wysiwyg_fields'] = array();
    foreach ($fields as $field) {
        $output_label_column_width = '';
        // if the label column width is not blank and it has not been added and this field type is not information, then add it to this field
        if (($label_column_width != '') && ($label_column_width_added == false) && ($field['type'] != 'information')) {
            $output_label_column_width = 'width: ' . $label_column_width . '%';
            $label_column_width_added = true;
        }
        // Assume that field will be shown until we find out otherwise.
        $show = true;
        // If a query string value was passed that says to not show field then remember that.
        if ($_GET['show_' . $field['id']] == 'false') {
            $show = false;
        }
        $output_hidden_style = '';
        // If the field should not be shown, then hide the row.
        // The field will still be outputted and the default value will
        // be outputted with the submitted form.
        if ($show == false) {
            $output_hidden_style = ' style="display: none"';
        }
        // create unique and valid css class for field
        $output_row_class = 'ff_' . get_class_name($field['name']);
        // if form is a custom form and field is for office use only, then prepare to apply office use only style to row; append field label as class
        if (($form_type == 'custom') && ($field['office_use_only'] == 1)) {
            $row_class = ' class="' . $output_row_class . ' software_office_use_only"';
        } else {
            $row_class = ' class="' . $output_row_class . '"';
        }
        if ($field['size'] == 0) {
            $field['size'] = '';
        }
        if ($field['maxlength'] == 0) {
            $field['maxlength'] = '';
        }
        if ($field['rows'] == 0) {
            $field['rows'] = '';
        }
        if ($field['cols'] == 0) {
            $field['cols'] = '';
        }
        if ($field['label'] && $field['required']) {
            $field['label'] .= '*';
        }
        $default_value = '';
        $value_from_query_string = trim($_GET['value_' . $field['id']]);
        // If a default value was passed in the query string, then use that.
        if ($value_from_query_string != '') {
            $default_value = $value_from_query_string;
            // Otherwise if edit mode is off and the form is a custom form and a contact was found for user
            // and this field is connected to a contact field, then set the default value for the field
            // to the contact field's value
        } else if (($editable == false) && ($form_type == 'custom') && ($contact) && ($field['contact_field'] != '')) {
            $default_value = $contact[$field['contact_field']];
            // Otherwise, if field is set to use folder name for default value, then use it.
        } else if ($field['use_folder_name_for_default_value'] == 1) {
            $default_value = db_value("SELECT folder_name FROM folder WHERE folder_id = '" . escape($folder_id_for_default_value) . "'");
            // Otherwise use default value from field.
        } else {
            $default_value = $field['default_value'];
        }
        // if field has options, get options
        if (($field['type'] == 'pick list') || ($field['type'] == 'radio button') || ($field['type'] == 'check box')) {
            $query = "SELECT

                    id,

                    label,

                    value,

                    default_selected

                FROM form_field_options

                WHERE form_field_id = '" . $field['id'] . "'

                ORDER BY sort_order";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $options = array();
            while ($row = mysqli_fetch_assoc($result)) {
                // If there are not target options for this field,
                // or this option is a target option, then include this option.
                // Target options in this case are the only options that a different office use only field require to appear in pick list.
                if ((isset($office_use_only_target_options[$field['id']]) == false) || (in_array(mb_strtolower($row['value']), $office_use_only_target_options[$field['id']]) == true)) {
                    $options[] = $row;
                }
            }
        }
        $output_spacing_row_class = 'class="spacing_row' . ($field['office_use_only'] ? ' software_office_use_only" ' : '" ');
        // if field should have spacing above, add spacing
        if ($field['spacing_above']) {
            $form_info['content'] .= '<tr ' . $output_spacing_row_class . $output_hidden_style . '>

                    <td colspan="2">&nbsp;</td>

                </tr>';
        }
        // if field needs it, then get html field name
        switch ($field['type']) {
            case 'text box':
            case 'email address':
            case 'text area':
            case 'pick list':
            case 'radio button':
            case 'check box':
            case 'file upload':
            case 'date':
            case 'date and time':
            case 'time':
                // get html field name differently based on the form type
                switch ($form_type) {
                    case 'custom':
                        $html_field_name = $field['id'];
                        break;
                    case 'billing':
                    case 'shipping':
                        $html_field_name = $prefix . 'field_' . $field['id'];
                        break;
                    case 'product':
                        $html_field_name = 'order_item_' . $order_item_id . '_quantity_number_' . $quantity_number . '_form_field_' . $field['id'];
                        break;
                }
                break;
        }
        // if field is a radio button or checkbox, then get html option id prefix
        switch ($field['type']) {
            case 'radio button':
            case 'check box':
                // get html option id prefix differently based on the form type
                switch ($form_type) {
                    case 'custom':
                    case 'billing':
                    case 'shipping':
                        $html_option_id_prefix = $prefix . 'software_option_';
                        break;
                    case 'product':
                        $html_option_id_prefix = 'order_item_' . $order_item_id . '_quantity_number_' . $quantity_number . '_software_option_';
                        break;
                }
                break;
        }
        $required = '';
        // If this field is required, and it is not a check box, or it is a check box and there is
        // just one check box option, then add required attribute.  We don't add the required
        // attribute, when there are multiple check box options, because it would require that all
        // of them be checked.
        if ($field['required'] and (($field['type'] != 'check box') or (count($options) == 1))) {
            $required = 'true';
        }
        // prepare to output field
        switch ($field['type']) {
            case 'text box':
            case 'email address':
                if ($field['type'] == 'email address') {
                    $type = 'email';
                } else {
                    $type = 'text';
                }
                $output_title_row = '';
                // If this field is a product submit form reference code field,
                // and the field does not have an error and the
                // and the field has a value in it, then check if we can find a
                // submitted form for the reference code, and then get title for that form.
                // This will help the user understand which submitted form the reference code is related to.
                if (($field['id'] == $reference_code_field_id) && ($liveform->check_field_error($html_field_name) == false) && ($liveform->get_field_value($html_field_name) != '') && ($submitted_form = db_item("SELECT id, page_id FROM forms WHERE reference_code = '" . e($liveform->get_field_value($html_field_name)) . "'"))) {
                    $title_label = db_value("SELECT label

                        FROM form_fields

                        WHERE

                            page_id = '" . $submitted_form['page_id'] . "'

                            AND (rss_field = 'title')

                        ORDER BY sort_order");
                    $title = get_submitted_form_title($submitted_form['id']);
                    if ($title != '') {
                        $output_title_row = '<tr' . $row_class . $output_hidden_style . '>

                                <td style="' . $output_label_column_width . '">' . $title_label . '</td>

                                <td>' . h($title) . '</td>

                            </tr>';
                    }
                }
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td>' . $liveform->output_field(array(
                                'type' => $type,
                                'name' => $html_field_name,
                                'value' => $default_value,
                                'size' => $field['size'],
                                'maxlength' => $field['maxlength'],
                                'class' => 'software_input_text',
                                'required' => $required
                            )) . '</td>

                    </tr>' . $output_title_row;
                break;
            case 'text area':
                // if field is wysiwyg, then prepare special values and output textarea so it takes up both columns
                if ($field['wysiwyg'] == 1) {
                    // add field to wysiwyg fields array, so that we can prepare JavaScript later
                    $form_info['wysiwyg_fields'][] = $html_field_name;
                    // if rows was not set, then set default rows so that WYSIWYG editor appears correctly
                    if (!$field['rows']) {
                        $field['rows'] = 15;
                    }
                    $style = '';
                    // if cols was not set, then set default width so that WYSIWYG editor appears correctly
                    if (!$field['cols']) {
                        $style = 'width: 95%';
                    }
                    $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                            <td colspan="2">

                                <div style="margin-bottom: .5em">' . $field['label'] . '</div>

                                <div>' . $liveform->output_field(array(
                                    'type' => 'textarea',
                                    'name' => $html_field_name,
                                    'id' => $html_field_name,
                                    'value' => $default_value,
                                    'maxlength' => $field['maxlength'],
                                    'rows' => $field['rows'],
                                    'cols' => $field['cols'],
                                    'class' => 'software_textarea',
                                    'style' => $style,
                                    'required' => $required
                                )) . '</div>

                            </td>

                        </tr>';
                    // else the field is not wysiwyg, so output two columns like normal
                } else {
                    $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                            <td style="vertical-align: top; ' . $output_label_column_width . '">' . $field['label'] . '</td>

                            <td style="vertical-align: top">' . $liveform->output_field(array(
                                    'type' => 'textarea',
                                    'name' => $html_field_name,
                                    'id' => $html_field_name,
                                    'value' => $default_value,
                                    'maxlength' => $field['maxlength'],
                                    'rows' => $field['rows'],
                                    'cols' => $field['cols'],
                                    'class' => 'software_textarea',
                                    'required' => $required
                                )) . '</td>

                        </tr>';
                }
                break;
            case 'pick list':
                $multiple = '';
                // if the pick list supports multiple selection, then alter html field name and setup pick list to allow multiple selection
                if ($field['multiple'] == 1) {
                    $html_field_name .= '[]';
                    $multiple = 'multiple';
                }
                $pick_list_options = array();
                foreach ($options as $option) {
                    $pick_list_options[$option['label']] = array(
                        'value' => $option['value'],
                        'default_selected' => $option['default_selected']
                    );
                }
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="vertical-align: top; ' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td style="vertical-align: top">' . $liveform->output_field(array(
                                'type' => 'select',
                                'name' => $html_field_name,
                                'value' => $default_value,
                                'options' => $pick_list_options,
                                'size' => $field['size'],
                                'multiple' => $multiple,
                                'class' => 'software_select',
                                'required' => $required
                            )) . '</td>

                    </tr>';
                break;
            case 'radio button':
                $output_options = '';
                foreach ($options as $option) {
                    // if this radio button should be selected by default, prepare to select by default
                    if ($option['value'] == $default_value) {
                        $checked = 'checked';
                    } else {
                        $checked = '';
                    }
                    $output_options .= $liveform->output_field(array(
                        'type' => 'radio',
                        'name' => $html_field_name,
                        'id' => $html_option_id_prefix . $option['id'],
                        'value' => $option['value'],
                        'checked' => $checked,
                        'class' => 'software_input_radio',
                        'required' => $required
                    )) . '<label for="' . $html_option_id_prefix . $option['id'] . '"> ' . h($option['label']) . '</label><br />';
                }
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="vertical-align: top; ' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td style="vertical-align: top">' . $output_options . '</td>

                    </tr>';
                break;
            case 'check box':
                $output_options = '';
                // if there is more than one option for this check box group, then prepare check boxes to support multiple check boxes
                if (count($options) > 1) {
                    $html_field_name .= '[]';
                }
                foreach ($options as $option) {
                    // if this checkbox should be selected by default, prepare to select by default
                    if (($option['default_selected'] == 1) || ($option['value'] == $default_value)) {
                        $checked = 'checked';
                    } else {
                        $checked = '';
                    }
                    $output_options .= $liveform->output_field(array(
                        'type' => 'checkbox',
                        'name' => $html_field_name,
                        'id' => $html_option_id_prefix . $option['id'],
                        'value' => $option['value'],
                        'checked' => $checked,
                        'class' => 'software_input_checkbox',
                        'required' => $required
                    )) . '<label for="' . $html_option_id_prefix . $option['id'] . '"> ' . h($option['label']) . '</label><br />';
                }
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="vertical-align: top; ' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td style="vertical-align: top">' . $output_options . '</td>

                    </tr>';
                break;
            case 'file upload':
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td style="vertical-align: top">' . $liveform->output_field(array(
                                'type' => 'file',
                                'name' => $html_field_name,
                                'size' => $field['size'],
                                'class' => 'software_input_file',
                                'required' => $required
                            )) . '</td>

                    </tr>';
                $form_info['file_upload_exists'] = true;
                break;
            case 'date':
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td style="vertical-align: top">

                            ' . $liveform->output_field(array(
                                'type' => 'text',
                                'id' => $html_field_name,
                                'name' => $html_field_name,
                                'value' => $default_value,
                                'size' => $field['size'],
                                'maxlength' => '10',
                                'class' => 'software_input_text',
                                'required' => $required
                            )) . '

                            ' . get_date_picker_format() . '

                            <script>

                                software_$("#' . $html_field_name . '").datepicker(datetimepicker_options);

                            </script>

                        </td>

                    </tr>';
                break;
            case 'date and time':
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td style="vertical-align: top">

                            ' . $liveform->output_field(array(
                                'type' => 'text',
                                'id' => $html_field_name,
                                'name' => $html_field_name,
                                'value' => $default_value,
                                'size' => $field['size'],
                                'maxlength' => '22',
                                'class' => 'software_input_text',
                                'required' => $required
                            )) . '

                            <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
                            ' . get_date_time_picker_format() . '
                            <script>
                                software_$("#' . $html_field_name . '").datetimepicker(datetimepicker_options);
                            </script>

                        </td>

                    </tr>';
                break;
            case 'information':
                // if this is the back-end (e.g. preview form feature for product from) then prepare content for output
                if ($interface == 'backend') {
                    $field['information'] = prepare_rich_text_editor_content_for_output($field['information']);
                }
                if ($editable == true) {
                    // add the edit button to images in content
                    $field['information'] = add_edit_button_for_images('form_field', $field['id'], $field['information']);
                }
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td colspan="2" style="vertical-align: top; ' . $output_label_column_width . '">' . $field['information'] . '</td>

                    </tr>';
                break;
            case 'time':
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td style="vertical-align: top">' . $liveform->output_field(array(
                                'type' => 'text',
                                'name' => $html_field_name,
                                'value' => $default_value,
                                'size' => $field['size'],
                                'maxlength' => '11',
                                'class' => 'software_input_text',
                                'required' => $required
                            )) . ' (Format: h:mm AM/PM)</td>

                    </tr>';
                break;
            default:
                $form_info['content'] .= '<tr' . $row_class . $output_hidden_style . '>

                        <td style="' . $output_label_column_width . '">' . $field['label'] . '</td>

                        <td style="vertical-align: top"></td>

                    </tr>';
                break;
        }
        // if field should have spacing below, add spacing
        if ($field['spacing_below']) {
            $form_info['content'] .= '<tr ' . $output_spacing_row_class . $output_hidden_style . '>

                    <td colspan="2">&nbsp;</td>

                </tr>';
        }
    }
    return $form_info;
}
// get the table rows of content for a submitted product form and use data from form fields (e.g. label)
function get_submitted_product_form_content_with_form_fields($order_item_id, $quantity_number)
{
    // get product info
    $query = "SELECT

            products.id,

            products.form_label_column_width

        FROM order_items

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE order_items.id = '$order_item_id'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $product_id = $row['id'];
    $label_column_width = $row['form_label_column_width'];
    // The join above is a LEFT JOIN, so a product that has since been deleted
    // comes back as NULL. Falling through with an empty $product_id turns the
    // next query into "product_id = ''", which MySQL reads as 0 and which
    // matches every row that belongs to no product — page forms, and since
    // 2026.4 variant set form templates as well. An order line whose product is
    // gone has no product form to print.
    if ($product_id === NULL || $product_id === '' || (int) $product_id <= 0) {
        return '';
    }
    // get all fields for this form
    $query = "SELECT

            id,

            label,

            type,

            information,

            wysiwyg,

            spacing_above,

            spacing_below

        FROM form_fields

        WHERE (product_id = '$product_id') AND (form_type = 'product')

        ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $form_fields = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $form_fields[] = $row;
    }
    $content = '';
    $label_column_width_added = false;
    // loop through form fields
    foreach ($form_fields as $form_field) {
        $output_label_column_width = '';
        // if the label column width is not blank and it has not been added and this field type is not information, then add it to this field
        if (($label_column_width != '') && ($label_column_width_added == false) && ($form_field['type'] != 'information')) {
            $output_label_column_width = '; width: ' . $label_column_width . '%';
            $label_column_width_added = true;
        }
        // if field should have spacing above, add spacing
        if ($form_field['spacing_above']) {
            $content .= '<tr>

                    <td colspan="2">&nbsp;</td>

                </tr>';
        }
        // if field has an information type, then don't get data for field
        if ($form_field['type'] == 'information') {
            $content .= '<tr>

                    <td colspan="2" style="vertical-align: top' . $output_label_column_width . '">' . $form_field['information'] . '</td>

                </tr>';
            // else field does not have an information type, so get data for field
        } else {
            $data = '';
            $query = "SELECT data

                FROM form_data

                WHERE

                    (order_item_id = '$order_item_id')

                    AND (quantity_number = '$quantity_number')

                    AND (form_field_id = '" . $form_field['id'] . "')

                ORDER BY id";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // if there is one value, then set data to value
            if (mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_assoc($result);
                $data = $row['data'];
                // else if there is more than one value, then set data for multiple values
            } elseif (mysqli_num_rows($result) > 1) {
                while ($row = mysqli_fetch_assoc($result)) {
                    // if there is already data, then add a comma and a space
                    if ($data != '') {
                        $data .= ', ';
                    }
                    $data .= $row['data'];
                }
            }
            // if this form field is wysiwyg, then do not prepare for html; the markup is filtered instead
            if ($form_field['wysiwyg'] == 1) {
                $data = prepare_form_data_for_output(pg_sanitize_rich_text($data), $form_field['type'], $prepare_for_html = false);
                // else this form field is not wysiwyg, so prepare for html
            } else {
                $data = prepare_form_data_for_output($data, $form_field['type'], $prepare_for_html = true);
            }
            $content .= '<tr>

                    <td style="vertical-align: top' . $output_label_column_width . '">' . $form_field['label'] . '</td>

                    <td style="vertical-align: top">' . $data . '</td>

                </tr>';
        }
        // if field should have spacing below, add spacing
        if ($form_field['spacing_below']) {
            $content .= '<tr>

                    <td colspan="2">&nbsp;</td>

                </tr>';
        }
    }
    return $content;
}
// get the table rows of content for a submitted product form but do not use data from form fields (e.g. label) because they might not exist
function get_submitted_product_form_content_without_form_fields($order_item_id, $quantity_number, $interface)
{
    // get form data items for order item
    $query = "SELECT

            form_field_id,

            data,

            name,

            type,

            count(*) as number_of_values

        FROM form_data

        WHERE

            (order_item_id = '$order_item_id')

            AND (quantity_number = '$quantity_number')

        GROUP BY form_field_id

        ORDER BY id";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $form_data_items = array();
    // loop through form data items in order to add them to array
    while ($row = mysqli_fetch_assoc($result)) {
        $form_data_items[] = $row;
    }
    $output_form_data_rows = '';
    // loop through form data items in order to prepare rows of data
    foreach ($form_data_items as $form_data_item) {
        $data = '';
        // if there is more than one value, then get all values so data can be set to all values
        if ($form_data_item['number_of_values'] > 1) {
            $query = "SELECT data

                FROM form_data

                WHERE

                    (order_item_id = '$order_item_id')

                    AND (quantity_number = '$quantity_number')

                    AND (form_field_id = '" . $form_data_item['form_field_id'] . "')

                ORDER BY id";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            while ($row = mysqli_fetch_assoc($result)) {
                // if data is not empty, then add comma and space
                if ($data != '') {
                    $data .= ', ';
                }
                $data .= $row['data'];
            }
            // else there is just one value, so set data
        } else {
            $data = $form_data_item['data'];
        }
        // if form data item type is html, then don't prepare output for html, because data is already html
        if ($form_data_item['type'] == 'html') {
            $output_data = prepare_form_data_for_output($data, $form_data_item['type'], $prepare_for_html = false);
            // else form data item type is not html, so prepare output for html
        } else {
            $output_data = prepare_form_data_for_output($data, $form_data_item['type'], $prepare_for_html = true);
        }
        $output_form_data_rows .= '<tr>

                <td style="vertical-align: top">' . h($form_data_item['name']) . ':</td>

                <td style="vertical-align: top">' . $output_data . '</td>

            </tr>';
    }
    return $output_form_data_rows;
}
// Create function that is used to show data for a submitted custom form
// for various different screens (e.g. order preview, order receipt)
// for custom billing forms that appear on express order and billing information pages
// and for custom shipping forms that appear on shipping address & arrival pages.
// This function gets all info for the form, including labels.
function get_submitted_form_content_with_form_fields($properties)
{
    $type = $properties['type'];
    $order_id = $properties['order_id'];

    // Only passed for shipping forms.
    $ship_to_id = isset($properties['ship_to_id']) ? $properties['ship_to_id'] : '';
    // Prepare where part of queries differently based on the custom form type.
    switch ($type) {
        case 'custom_billing_form':
            $sql_where = "(order_id = '" . escape($order_id) . "')

                AND (order_id != '0')

                AND (ship_to_id = '0')

                AND (order_item_id = '0')";
            break;
        case 'custom_shipping_form':
            $sql_where = "(ship_to_id = '" . escape($ship_to_id) . "')";
            break;
    }
    // Get a field id for any submitted form data,
    // in order determine if there is a submitted form
    // and which page it came from.
    $field_id = db_value("SELECT form_field_id

        FROM form_data

        WHERE $sql_where

        LIMIT 1");
    // If the visitor has not submitted form data, then return empty string.
    if ($field_id == '') {
        return '';
    }
    // Get page info for the field of data.
    $page = db_item("SELECT

            page.page_id AS id,

            page.page_type AS type

        FROM form_fields

        LEFT JOIN page ON form_fields.page_id = page.page_id

        WHERE form_fields.id = '$field_id'");
    // If a page was not found for some reason or the page type does not support a custom form,
    // then return empty string.
    if (($page['id'] == '') || (($page['type'] != 'express order') && ($page['type'] != 'billing information') && ($page['type'] != 'shipping address and arrival'))) {
        return '';
    }
    $page_type_table_name = str_replace(' ', '_', $page['type']) . '_pages';
    $page_type = $page['type'];
    // Get more page type info for the page and verify that form is enabled for the page.
    // The properties for a custom shipping form on express order are unique, because
    // shipping and billing forms can appear on express order, so get values in unique way
    if ($page_type == 'express order' and $type == 'custom_shipping_form') {
        $page = db_item("SELECT page_id AS id

            FROM $page_type_table_name

            WHERE

                (page_id = '" . $page['id'] . "')

                AND (shipping_form = '1')");
    } else {
        $page = db_item("SELECT

                page_id AS id,

                form_name,

                form_label_column_width

            FROM $page_type_table_name

            WHERE

                (page_id = '" . $page['id'] . "')

                AND (form = '1')");
    }
    // If page type properties could not be found, or the page does not have a form
    // enabled anymore, then return empty string.
    if ($page['id'] == '') {
        return '';
    }
    $form_name = $page['form_name'];
    $label_column_width = $page['form_label_column_width'];
    if ($page_type == 'express order') {
        if ($type == 'custom_shipping_form') {
            $sql_field_where = "(page_id = '" . e($page['id']) . "') AND (form_type = 'shipping')";
        } else {
            $sql_field_where = "(page_id = '" . e($page['id']) . "') AND (form_type = 'billing')";
        }
    } else {
        $sql_field_where = "page_id = '" . e($page['id']) . "'";
    }
    // Get all fields for this form.
    $fields = db_items("SELECT

            id,

            label,

            type,

            information,

            wysiwyg,

            spacing_above,

            spacing_below

        FROM form_fields

        WHERE $sql_field_where

        ORDER BY sort_order ASC");
    $output_rows = '';
    $label_column_width_added = false;
    // Loop through form fields.
    foreach ($fields as $field) {
        $output_label_column_width = '';
        // If the label column width is not blank and it has not been added and this field type is not information,
        // then add it to this field.
        if (($label_column_width != '') && ($label_column_width_added == false) && ($field['type'] != 'information')) {
            $output_label_column_width = '; width: ' . $label_column_width . '%';
            $label_column_width_added = true;
        }
        // If field should have spacing above, add spacing.
        if ($field['spacing_above']) {
            $output_rows .= '<tr>

                    <td colspan="2">&nbsp;</td>

                </tr>';
        }
        // If field has an information type, then don't get data for field.
        if ($field['type'] == 'information') {
            $output_rows .= '<tr>

                    <td colspan="2" style="vertical-align: top' . $output_label_column_width . '">' . $field['information'] . '</td>

                </tr>';
            // Otherwise field does not have an information type, so get data for field.
        } else {
            $data = '';
            $query = "SELECT data

                FROM form_data

                WHERE

                    $sql_where

                    AND (form_field_id = '" . $field['id'] . "')

                ORDER BY id ASC";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // If there is one value, then set data to value.
            if (mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_assoc($result);
                $data = $row['data'];
                // Otherwise there is more than one value, so set data for multiple values.
            } elseif (mysqli_num_rows($result) > 1) {
                while ($row = mysqli_fetch_assoc($result)) {
                    // if there is already data, then add a comma and a space
                    if ($data != '') {
                        $data .= ', ';
                    }
                    $data .= $row['data'];
                }
            }
            // If this form field is wysiwyg, then do not prepare for html; the markup is filtered instead.
            if ($field['wysiwyg'] == 1) {
                $data = prepare_form_data_for_output(pg_sanitize_rich_text($data), $field['type'], $prepare_for_html = false);
                // Otherwise this form field is not wysiwyg, so prepare for html.
            } else {
                $data = prepare_form_data_for_output($data, $field['type'], $prepare_for_html = true);
            }
            $output_rows .= '<tr>

                    <td style="vertical-align: top' . $output_label_column_width . '">' . $field['label'] . '</td>

                    <td style="vertical-align: top">' . $data . '</td>

                </tr>';
        }
        // If field should have spacing below, add spacing.
        if ($field['spacing_below']) {
            $output_rows .= '<tr>

                    <td colspan="2">&nbsp;</td>

                </tr>';
        }
    }
    // If there is a form name, then output fieldset and legend with table of rows.
    if ($form_name != '') {
        return '<div style="margin-top: .5em; margin-bottom: .5em">

                <fieldset class="software_fieldset">

                    <legend class="software_legend">' . h($form_name) . '</legend>

                    <table>

                        ' . $output_rows . '

                    </table>

                </fieldset>

            </div>';
        // Otherwise there is not a form name, so just output table without a fieldset.
    } else {
        return '<div style="margin-top: .5em; margin-bottom: .5em">

                <table>

                    ' . $output_rows . '

                </table>

            </div>';
    }
}
// This is used to get custom shipping/billing/product form field data that customer
// has entered so it can be displayed in custom layouts for review on
// express order, order preview, and order receipt.
function get_form_review_info($properties)
{
    // Which of these the caller passes depends on the form type, so fill in the rest.
    $properties = $properties + array(
        'order_id'        => '',
        'ship_to_id'      => '',
        'order_item_id'   => '',
        'quantity_number' => '',
        'product_id'      => '');

    $type = $properties['type'];
    $order_id = $properties['order_id'];
    $ship_to_id = $properties['ship_to_id'];
    $order_item_id = $properties['order_item_id'];
    $quantity_number = $properties['quantity_number'];
    $product_id = $properties['product_id'];
    // Prepare where part of queries differently based on the custom form type.
    switch ($type) {
        case 'custom_billing_form':
            $sql_where = "(order_id = '" . e($order_id) . "')

                AND (order_id != '0')

                AND (ship_to_id = '0')

                AND (order_item_id = '0')";
            break;
        case 'custom_shipping_form':
            $sql_where = "(ship_to_id = '" . e($ship_to_id) . "')";
            break;
        case 'product_form':
            $sql_where = "(order_item_id = '" . e($order_item_id) . "')

                AND (quantity_number = '" . e($quantity_number) . "')";
            break;
    }
    // Get a field id for any submitted form data,
    // in order determine if there is a submitted form
    // and which page it came from.
    $field_id = db_value("SELECT form_field_id

        FROM form_data

        WHERE $sql_where

        LIMIT 1");
    // If the visitor has not submitted form data, then return false.
    if ($field_id == '') {
        return false;
    }
    $title = '';
    // Get info about the source of the form differently based on the type.
    switch ($type) {
        case 'custom_billing_form':
        case 'custom_shipping_form':
            // Get page info for the field of data.
            $page = db_item("SELECT

                    page.page_id AS id,

                    page.page_type AS type

                FROM form_fields

                LEFT JOIN page ON form_fields.page_id = page.page_id

                WHERE form_fields.id = '$field_id'");
            // If a page was not found for some reason or the page type does not support a custom form,
            // then return false.
            if (($page['id'] == '') || (($page['type'] != 'express order') && ($page['type'] != 'billing information') && ($page['type'] != 'shipping address and arrival'))) {
                return false;
            }
            $page_type_table_name = str_replace(' ', '_', $page['type']) . '_pages';
            $page_type = $page['type'];
            // Get more page type info for the page and verify that form is enabled for the page.
            // The properties for a custom shipping form on express order are unique, because
            // shipping and billing forms can appear on express order, so get values in unique way
            if ($page_type == 'express order' and $type == 'custom_shipping_form') {
                $page = db_item("SELECT page_id AS id

                    FROM $page_type_table_name

                    WHERE

                        (page_id = '" . $page['id'] . "')

                        AND (shipping_form = '1')");
            } else {
                $page = db_item("SELECT

                        page_id AS id,

                        form_name

                    FROM $page_type_table_name

                    WHERE

                        (page_id = '" . $page['id'] . "')

                        AND (form = '1')");
            }
            // If page type properties could not be found, or the page does not have a form
            // enabled anymore, then return false.
            if ($page['id'] == '') {
                return false;
            }
            $title = $page['form_name'];
            if ($page_type == 'express order') {
                if ($type == 'custom_shipping_form') {
                    $sql_field_where = "(page_id = '" . e($page['id']) . "') AND (form_type = 'shipping')";
                } else {
                    $sql_field_where = "(page_id = '" . e($page['id']) . "') AND (form_type = 'billing')";
                }
            } else {
                $sql_field_where = "page_id = '" . e($page['id']) . "'";
            }
            break;
        case 'product_form':
            $sql_field_where = "product_id = '" . e($product_id) . "' AND form_type = 'product'";
            break;
    }
    // Get all fields for this form.
    $fields = db_items("SELECT

            id,

            label,

            type,

            information,

            wysiwyg,

            spacing_above,

            spacing_below

        FROM form_fields

        WHERE $sql_field_where

        ORDER BY sort_order ASC");
    // Create a variable that we will use to remember if there
    // is at least one field that has data, so a designer
    // can decide if he/she wants to output form if it has no data.
    $data = false;
    // Loop through form fields.
    foreach ($fields as $key => $field) {
        // If this is not an information field, then get data that customer entered.
        if ($field['type'] != 'information') {
            $field['data'] = '';
            $query = "SELECT data

                FROM form_data

                WHERE

                    $sql_where

                    AND (form_field_id = '" . $field['id'] . "')

                ORDER BY id ASC";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // If there is one value, then set data to value.
            if (mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_assoc($result);
                $field['data'] = $row['data'];
                // Otherwise there is more than one value, so set data for multiple values.
            } elseif (mysqli_num_rows($result) > 1) {
                while ($row = mysqli_fetch_assoc($result)) {
                    // if there is already data, then add a comma and a space
                    if ($field['data'] != '') {
                        $field['data'] .= ', ';
                    }
                    $field['data'] .= $row['data'];
                }
            }
            if ($field['data'] != '') {
                $data = true;
            }
            // If this form field is wysiwyg, then do not prepare for html; the markup is filtered instead.
            if ($field['wysiwyg'] == 1) {
                $field['data_info'] = prepare_form_data_for_output(pg_sanitize_rich_text($field['data']), $field['type'], $prepare_for_html = false);
                // Otherwise this form field is not wysiwyg, so prepare for html.
            } else {
                $field['data_info'] = prepare_form_data_for_output($field['data'], $field['type'], $prepare_for_html = true);
            }
        }
        $fields[$key] = $field;
    }
    $info = array();
    $info['title'] = $title;
    $info['fields'] = $fields;
    $info['data'] = $data;
    return $info;
}
// Create function that is used to show data for a submitted custom billing or shipping form
// for the front-end and back-end view order screens.  The original form might have changed
// since the order was submitted, so we don't show labels in this function.
// We just output name and value for each field.
function get_submitted_form_content_without_form_fields($properties)
{
    $type = $properties['type'];
    $order_id = $properties['order_id'];

    // Only passed for shipping forms.
    $ship_to_id = isset($properties['ship_to_id']) ? $properties['ship_to_id'] : '';

    $style = $properties['style'];
    // Prepare where part of queries differently based on the custom form type.
    switch ($type) {
        case 'custom_billing_form':
            $sql_where = "(order_id = '" . escape($order_id) . "')

                AND (order_id != '0')

                AND (ship_to_id = '0')

                AND (order_item_id = '0')";
            break;
        case 'custom_shipping_form':
            $sql_where = "(ship_to_id = '" . escape($ship_to_id) . "')";
            break;
    }
    // Get form data items for order item.
    $form_data_items = db_items("SELECT

            form_field_id,

            data,

            name,

            type,

            count(*) as number_of_values

        FROM form_data

        WHERE $sql_where

        GROUP BY form_field_id

        ORDER BY id");
    // If there is no form data, then return empty string.
    if (count($form_data_items) == 0) {
        return '';
    }
    $output_rows = '';
    // Loop through form data items in order to prepare rows of data.
    foreach ($form_data_items as $form_data_item) {
        $data = '';
        // If there is more than one value, then get all values so data can be set to all values.
        if ($form_data_item['number_of_values'] > 1) {
            $query = "SELECT data

                FROM form_data

                WHERE

                    $sql_where

                    AND (form_field_id = '" . $form_data_item['form_field_id'] . "')

                ORDER BY id";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            while ($row = mysqli_fetch_assoc($result)) {
                // If data is not empty, then add comma and space.
                if ($data != '') {
                    $data .= ', ';
                }
                $data .= $row['data'];
            }
            // Otherwise there is just one value, so set data.
        } else {
            $data = $form_data_item['data'];
        }
        // If form data item type is html, then don't prepare output for html, because data is already html.
        if ($form_data_item['type'] == 'html') {
            $output_data = prepare_form_data_for_output($data, $form_data_item['type'], $prepare_for_html = false);
            // Otherwise form data item type is not html, so prepare output for html.
        } else {
            $output_data = prepare_form_data_for_output($data, $form_data_item['type'], $prepare_for_html = true);
        }
        $output_rows .= '<tr>

                <td style="vertical-align: top">' . h($form_data_item['name']) . ':</td>

                <td style="vertical-align: top">' . $output_data . '</td>

            </tr>';
    }
    if ($style) {
        $output_style = $style;
    } else {
        $output_style = 'margin-top: .5em; margin-bottom: .5em';
    }
    return '<div style="' . $output_style . '">

            <table>

                ' . $output_rows . '

            </table>

        </div>';
}
function send_user_to_login_home()
{
    // get user information
    $user = validate_user();
    // get home page name
    $home_page_name = get_page_name($user['home']);
    // if there is a send to, then forward user to the send to
    if ((isset($_REQUEST['send_to']) == true) && ($_REQUEST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
        exit();
        // else if user has a home page, then forward user to that page
    } elseif ($home_page_name != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path($home_page_name));
        exit();
        // else if user's role is administrator, designer, or manager
        // or user has edit rights
        // or user has access to control panel
        // then forward user to control panel welcome screen
    } elseif (($user['role'] < 3) || (no_acl_check($user['id']) == true) || ($user['manage_calendars'] == true) || ($user['manage_forms'] == true) || ($user['manage_visitors'] == true) || ($user['manage_contacts'] == true) || ($user['manage_emails'] == true) || ($user['manage_ecommerce'] == true) || $user['manage_ecommerce_reports'] || !empty($user['manage_erp']) || (count(get_items_user_can_edit('ad_regions', $user['id'])) > 0)) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/welcome.php');
        exit();
        // else send user to my account page
    } else {
        go(get_page_type_url('my account'));
    }
}
// get the charset from HTML content
function get_charset($content)
{
    // look for the charset in a meta tag
    preg_match("/<meta[^>]*http-equiv[^>]*charset=(.*)[\"|']/i", $content, $matches);
    // if the charset was found, then return the charset
    if (isset($matches[1]) == true) {
        return mb_strtolower(trim($matches[1]));
    } else {
        return '';
    }
}
function get_month_name_from_number($month_number)
{
    switch ($month_number) {
        case '01':
            return lang('January');
            break;
        case '02':
            return lang('February');
            break;
        case '03':
            return lang('March');
            break;
        case '04':
            return lang('April');
            break;
        case '05':
            return lang('May');
            break;
        case '06':
            return lang('June');
            break;
        case '07':
            return lang('July');
            break;
        case '08':
            return lang('August');
            break;
        case '09':
            return lang('September');
            break;
        case '10':
            return lang('October');
            break;
        case '11':
            return lang('November');
            break;
        case '12':
            return lang('December');
            break;
    }
}
function get_thumbnail_dimensions($image_width, $image_height, $max_dimension)
{
    // Get the aspect ratio of the image.
    $width_percentage = 0;
    if ($image_width != 0) {
        $width_percentage = $max_dimension / $image_width;
    }
    $height_percentage = 0;
    if ($image_height != 0) {
        $height_percentage = $max_dimension / $image_height;
    }
    // If the image width is wider than the maximum width allowed then continue the code.
    if ($image_width >= $max_dimension) {
        // If the width percentage ratio is smaller than or equal to the height percentage ratio then continue the code.
        if ($width_percentage <= $height_percentage) {
            // NOTE: the following will scale the image and keep the original aspect ratio.
            // Set the thumbnail width to the value of the width percentage multiplied by the current image width.
            $thumbnail_width = $width_percentage * $image_width;
            // Set the thumbnail height to the value of the width percentage multiplied by the current image height.
            $thumbnail_height = $width_percentage * $image_height;
            // If the height percentage ratio is greater than the width then continue the code.
        } else {
            // Set the thumbnail width to the value of the height percentage multiplied by the current image width.
            $thumbnail_width = $height_percentage * $image_width;
            // Set the thumbnail height to the value of the height percentage multiplied by the current image height.
            $thumbnail_height = $height_percentage * $image_height;
        }
        // If the image width is less than the maximum width allowed but the height is greater than the allowed amount then continue the code.
    } else if ($image_height >= $max_dimension) {
        // If the width percentage ratio is smaller than or equal to the height percentage ratio then continue the code.
        if ($width_percentage <= $height_percentage) {
            // NOTE: the following will scale the image and keep the original aspect ratio.
            // Set the thumbnail width to the value of the width percentage multiplied by the current image width.
            $thumbnail_width = $width_percentage * $image_width;
            // Set the thumbnail height to the value of the width percentage multiplied by the current image height.
            $thumbnail_height = $width_percentage * $image_height;
        } else {
            // Set the thumbnail width to the value of the height percentage multiplied by the current image width.
            $thumbnail_width = $height_percentage * $image_width;
            // Set the thumbnail height to the value of the height percentage multiplied by the current image height.
            $thumbnail_height = $height_percentage * $image_height;
        }
        // If the image is smaller than or equal to the allowed image size then keep original size.
    } else {
        $thumbnail_width = $image_width;
        $thumbnail_height = $image_height;
    }
    // Output thumbnail size.
    return array(
        'width' => $thumbnail_width,
        'height' => $thumbnail_height
    );
}
function convert_bytes_to_string($bytes, $round = 0)
{
    $sizes = array(
        'B',
        'KB',
        'MB',
        'GB',
        'TB',
        'PB',
        'EB',
        'ZB',
        'YB'
    );
    $total = count($sizes);
    for ($i = 0; $bytes > 1024 && $i < $total; $i++)
        $bytes /= 1024;
    return number_format(round($bytes, $round), $round, '.', ',') . ' ' . $sizes[$i];
}
function generate_url_id()
{
    // if the URL array in the session has not been initialized yet, then initialize it
    if (isset($_SESSION['software']['urls']) == false) {
        $_SESSION['software']['urls'] = array();
    }
    // get current request uri
    $request_uri = get_request_uri();
    // check to see if the current URL has already been stored
    $url_id = array_search($request_uri, $_SESSION['software']['urls']);
    // if a URL id was found, then return it
    if ($url_id !== false) {
        return $url_id;
        // else a URL id was not found, so store URL in session and return URL id
    } else {
        $_SESSION['software']['urls'][] = $request_uri;
        return count($_SESSION['software']['urls']) - 1;
    }
}
// get product groups and products that should appear in search results for the site search and for the catalog search
// the logic below might seem more complex than necessary, which is because we are trying to minimize the number of database queries,
// in order to get good performance for sites with many product groups and products
function get_catalog_search_results($search_query, $product_group_id)
{
    // If the product group is disabled, then return an empty array of results.
    if (!db_value("SELECT enabled FROM product_groups WHERE id = '" . e($product_group_id) . "'")) {
        return array();
    }
    // get all product groups so we can determine which product groups can appear in search results
    $query = "SELECT

            id,

            parent_id,

            display_type

        FROM product_groups

        WHERE enabled = '1'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $product_groups = array();
    // add each product group to an array
    while ($row = mysqli_fetch_assoc($result)) {
        $product_groups[] = $row;
    }
    // get all relationships between products and product groups so we can determine which products can appear in search results
    $query = "SELECT

            product as product_id,

            product_group as product_group_id

        FROM products_groups_xref";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $products_product_groups_xrefs = array();
    // add each relationship to an array
    while ($row = mysqli_fetch_assoc($result)) {
        $products_product_groups_xrefs[] = $row;
    }
    $items_that_can_appear_in_search_results = get_catalog_items_that_can_appear_in_search_results($product_group_id, $product_groups, $products_product_groups_xrefs);
    // initialize array for storing product groups and products that have been added to the items array, so that we don't add duplicate items
    $added_items = array();
    $added_items['product_groups'] = array();
    $added_items['products'] = array();
    // initialize array for storing items
    $items = array();
    // get search results for keywords matches
    $search_results = get_catalog_search_results_for_field('keywords', 'contains', $search_query, $items_that_can_appear_in_search_results, $added_items);
    $items = array_merge($items, $search_results['items']);
    $added_items = array_merge($added_items, $search_results['added_items']);
    // get search results for exact name matches
    $search_results = get_catalog_search_results_for_field('name', 'is equal to', $search_query, $items_that_can_appear_in_search_results, $added_items);
    $items = array_merge($items, $search_results['items']);
    $added_items = array_merge($added_items, $search_results['added_items']);
    // get search results for partial name matches
    $search_results = get_catalog_search_results_for_field('name', 'contains', $search_query, $items_that_can_appear_in_search_results, $added_items);
    $items = array_merge($items, $search_results['items']);
    $added_items = array_merge($added_items, $search_results['added_items']);
    // get search results for short description matches
    $search_results = get_catalog_search_results_for_field('short_description', 'contains', $search_query, $items_that_can_appear_in_search_results, $added_items);
    $items = array_merge($items, $search_results['items']);
    $added_items = array_merge($added_items, $search_results['added_items']);
    // get search results for full description matches
    $search_results = get_catalog_search_results_for_field('full_description', 'contains', $search_query, $items_that_can_appear_in_search_results, $added_items);
    $items = array_merge($items, $search_results['items']);
    $added_items = array_merge($added_items, $search_results['added_items']);
    // get search results for details matches
    $search_results = get_catalog_search_results_for_field('details', 'contains', $search_query, $items_that_can_appear_in_search_results, $added_items);
    $items = array_merge($items, $search_results['items']);
    $added_items = array_merge($added_items, $search_results['added_items']);
    // get search results for meta keywords matches
    $search_results = get_catalog_search_results_for_field('meta_keywords', 'contains', $search_query, $items_that_can_appear_in_search_results, $added_items);
    $items = array_merge($items, $search_results['items']);
    $added_items = array_merge($added_items, $search_results['added_items']);
    return $items;
}
// we will use the function below to get all product groups and products that can appear in search results,
// because they have a certain type and are in the product group tree scope
function get_catalog_items_that_can_appear_in_search_results($parent_product_group_id, $product_groups, $products_product_groups_xrefs)
{
    $items_that_can_appear_in_search_results = array();
    $items_that_can_appear_in_search_results['product_groups'] = array();
    $items_that_can_appear_in_search_results['products'] = array();
    $child_product_groups = array();
    // loop through product groups array in order to get all product groups that are in parent product group
    foreach ($product_groups as $product_group) {
        // if the parent product group id for this product group is equal to the parent product group id, then this is a child product group, so add to array
        if ($product_group['parent_id'] == $parent_product_group_id) {
            $child_product_groups[] = $product_group;
        }
    }
    // loop through child product groups
    foreach ($child_product_groups as $child_product_group) {
        // if this child product group is a select product group, then add it to array
        if ($child_product_group['display_type'] == 'select') {
            $items_that_can_appear_in_search_results['product_groups'][] = $child_product_group['id'];
            // else this child product group is a browse product group, so get items under this product group, via recursion
        } else {
            // get child items
            $child_items_that_can_appear_in_search_results = get_catalog_items_that_can_appear_in_search_results($child_product_group['id'], $product_groups, $products_product_groups_xrefs);
            // add child product groups to array
            $items_that_can_appear_in_search_results['product_groups'] = array_merge($items_that_can_appear_in_search_results['product_groups'], $child_items_that_can_appear_in_search_results['product_groups']);
            // add child products to array
            $items_that_can_appear_in_search_results['products'] = array_merge($items_that_can_appear_in_search_results['products'], $child_items_that_can_appear_in_search_results['products']);
        }
    }
    // loop through products_product_groups_xrefs array in order to get all products that are in the parent product group
    foreach ($products_product_groups_xrefs as $products_product_groups_xref) {
        // if this product is in the parent product group, then add it to the array
        if ($products_product_groups_xref['product_group_id'] == $parent_product_group_id) {
            $items_that_can_appear_in_search_results['products'][] = $products_product_groups_xref['product_id'];
        }
    }
    // remove duplicate entries
    $items_that_can_appear_in_search_results['product_groups'] = array_unique($items_that_can_appear_in_search_results['product_groups']);
    $items_that_can_appear_in_search_results['products'] = array_unique($items_that_can_appear_in_search_results['products']);
    return $items_that_can_appear_in_search_results;
}
// initialize a function for getting all items for search results for a certain field, operator, and search query
// we have created a function for this so that we don't have to duplicate this large amount of code for every field that needs to be searched
function get_catalog_search_results_for_field($field, $operator, $search_query, $items_that_can_appear_in_search_results, $added_items)
{
    // initialize array that will store items
    $items = array();
    // initialize array that will store data for sorting
    $item_names = array();
    // prepare sql comparison
    $sql_comparison = "";
    switch ($operator) {
        case 'contains':
            $sql_comparison = "LIKE '%" . escape(escape_like($search_query)) . "%'";
            break;
        case 'is equal to':
            $sql_comparison = "= '" . escape($search_query) . "'";
            break;
    }
    // get all select product groups where the field matches the comparison
    $query = "SELECT

            id,

            name,

            short_description,

            image_name

        FROM product_groups

        WHERE

            (enabled = '1')

            AND (display_type = 'select')

            AND ($field $sql_comparison)";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // loop through product groups in order to add them to array
    while ($row = mysqli_fetch_assoc($result)) {
        // if this product group can appear in search results and it has not been added already, then add it
        if ((in_array($row['id'], $items_that_can_appear_in_search_results['product_groups']) == true) && (in_array($row['id'], $added_items['product_groups']) == false)) {
            $items[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'short_description' => $row['short_description'],
                'image_name' => $row['image_name'],
                'display_type' => 'select',
                'type' => 'product group'
            );
            $item_names[] = mb_strtolower($row['name']);
            // remember that this product group has been added
            $added_items['product_groups'][] = $row['id'];
        }
    }
    // get all products that are in a product group where the product's field matches the comparison
    $query = "SELECT

            products.id as product_id,

            products.name as product_name,

            products.short_description as product_short_description,

            products.image_name as product_image_name,

            products.price as product_price,

            products.selection_type as product_selection_type,
            
            products.inventory as product_inventory,

            products.inventory_quantity as product_inventory_quantity,

            product_groups.id as product_group_id,

            product_groups.name as product_group_name,

            product_groups.short_description as product_group_short_description,

            product_groups.image_name as product_group_image_name,

            product_groups.display_type as product_group_display_type

        FROM products_groups_xref

        LEFT JOIN products ON products_groups_xref.product = products.id

        LEFT JOIN product_groups ON products_groups_xref.product_group = product_groups.id

        WHERE

            (products.enabled = '1')

            AND (product_groups.enabled = '1')

            AND (products.$field $sql_comparison)";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // loop through products in order to add the product or its product group to the array
    while ($row = mysqli_fetch_assoc($result)) {
        // if this product's product group is a browse product group, then add continue to determine if product should be added to array
        if ($row['product_group_display_type'] == 'browse') {
            // if this product can appear in search results and it has not been added already, then add it
            if ((in_array($row['product_id'], $items_that_can_appear_in_search_results['products']) == true) && (in_array($row['product_id'], $added_items['products']) == false)) {
                $items[] = array(
                    'id' => $row['product_id'],
                    'name' => $row['product_name'],
                    'short_description' => $row['product_short_description'],
                    'image_name' => $row['product_image_name'],
                    'price' => $row['product_price'],
                    'inventory' => $row['product_inventory'],
                    'inventory_quantity' => $row['product_inventory_quantity'],
                    'selection_type' => $row['product_selection_type'],
                    'type' => 'product'
                );
                $item_names[] = mb_strtolower($row['product_name']);
                // remember that this product has been added
                $added_items['products'][] = $row['product_id'];
            }
            // else this product's product group is a select product group, so continue to determine if we should add product group to array
            // because we don't want to forward the customer directly to the product
        } else {
            // if this product group can appear in search results and it has not been added already, then add it
            if ((in_array($row['product_group_id'], $items_that_can_appear_in_search_results['product_groups']) == true) && (in_array($row['product_group_id'], $added_items['product_groups']) == false)) {
                $items[] = array(
                    'id' => $row['product_group_id'],
                    'name' => $row['product_group_name'],
                    'short_description' => $row['product_group_short_description'],
                    'image_name' => $row['product_group_image_name'],
                    'display_type' => 'select',
                    'type' => 'product group'
                );
                $item_names[] = mb_strtolower($row['product_group_name']);
                // remember that this product group has been added
                $added_items['product_groups'][] = $row['product_group_id'];
            }
        }
    }
    // sort the items by name
    array_multisort($item_names, $items);
    return array(
        'items' => $items,
        'added_items' => $added_items
    );
}
function require_cookies()
{
    // if cookies are required and the visitor has cookies disabled, then output error to the visitor
    if ((isset($_POST['require_cookies']) == true) && ($_POST['require_cookies'] == 'true') && (isset($_COOKIE[session_name()]) == false)) {
        output_error(lang('Please enable cookies in your web browser and then <a href="javascript:history.go(-1)">go back</a> and try again.'));
    }
}
function get_query_string_for_page_url($page_type, $item_id, $item_type)
{
    $query_string = '';
    // if there is an item id, then check certain page types to see if a query string needs to be added
    if ($item_id != '0') {
        // switch between the different item types and add any needed URL parameters
        switch ($page_type) {
            case 'catalog':
            case 'catalog detail':
                $address_name = '';
                // get the address name
                $address_name = get_catalog_item_address_name_from_id($item_id, $item_type);
                // if the address name is not blank, then set the query string to it
                if ($address_name != '') {
                    $query_string = '/' . $address_name;
                    // else set the query string to blank
                } else {
                    $query_string = '';
                }
                break;
            case 'calendar event view':
                $query_string = '?id=' . $item_id;
                break;
            case 'form item view':
                // get the reference code for this item
                $query = "SELECT reference_code FROM forms WHERE id = '" . escape($item_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                $row = mysqli_fetch_assoc($result);
                $query_string = '?r=' . $row['reference_code'];
                break;
        }
    }
    return $query_string;
}
function get_duplicate_catalog_item_address_name_number($address_name, $number = 1)
{
    // assume that the address name does not already exist until we find out otherwise
    $address_name_exists = false;
    // check products table to see if there is already a product with an address name that matches the one we want to use
    $query = "SELECT id FROM products where address_name = '" . escape($address_name) . "[" . $number . "]'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there is a result, then there is already a product with that address name
    if (mysqli_num_rows($result) > 0) {
        $address_name_exists = true;
    }
    // check product groups table to see if there is already a product group with an address name that matches the one we want to use
    $query = "SELECT id FROM product_groups where address_name = '" . escape($address_name) . "[" . $number . "]'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there is a result, then there is already a product group with that address name
    if (mysqli_num_rows($result) > 0) {
        $address_name_exists = true;
    }
    // if the address name already exists, then use recursion to try to get a different address name
    if ($address_name_exists == true) {
        return get_duplicate_catalog_item_address_name_number($address_name, $number + 1);
    } else {
        return $number;
    }
}
// Latin letters carrying a mark, written as the letter without it.
//
// Not a general transliteration: it covers the alphabets this software is
// actually used in, which is what a URL segment needs. Turkish first, because
// its dotless i and dotted I are the pair that most often breaks an address.
function pg_transliterate_to_ascii($text)
{
    static $accented = array(
        'ı','İ','ş','Ş','ğ','Ğ','ü','Ü','ö','Ö','ç','Ç',
        'á','à','â','ä','ã','å','ā','Á','À','Â','Ä','Ã','Å','Ā',
        'é','è','ê','ë','ē','É','È','Ê','Ë','Ē',
        'í','ì','î','ï','ī','Í','Ì','Î','Ï','Ī',
        'ó','ò','ô','õ','ø','ō','Ó','Ò','Ô','Õ','Ø','Ō',
        'ú','ù','û','ũ','ū','Ú','Ù','Û','Ũ','Ū',
        'ñ','Ñ','ý','ÿ','Ý','ć','č','Ć','Č','ď','đ','Ď','Đ',
        'ł','Ł','ń','ň','Ń','Ň','ř','Ř','ś','š','Ś','Š',
        'ť','Ť','ź','ż','ž','Ź','Ż','Ž','æ','Æ','œ','Œ','ß');

    static $plain = array(
        'i','I','s','S','g','G','u','U','o','O','c','C',
        'a','a','a','a','a','a','a','A','A','A','A','A','A','A',
        'e','e','e','e','e','E','E','E','E','E',
        'i','i','i','i','i','I','I','I','I','I',
        'o','o','o','o','o','o','O','O','O','O','O','O',
        'u','u','u','u','u','U','U','U','U','U',
        'n','N','y','y','Y','c','c','C','C','d','d','D','D',
        'l','L','n','n','N','N','r','R','s','s','S','S',
        't','T','z','z','z','Z','Z','Z','ae','AE','oe','OE','ss');

    return str_replace($accented, $plain, (string) $text);
}

function prepare_catalog_item_address_name($address_name, $item_id = '')
{
    // if item id is blank then set to zero.
    if ($item_id == '') {
        $item_id = '0';
    }
    // Prepare the address name for the database.
    //
    // This value becomes a path segment: /katalog/<address name>. Anything
    // outside plain ASCII cannot survive that reliably - IIS decodes the
    // request path and re-encodes it into the server's ANSI codepage, so a
    // product called "kahve-fincani" with Turkish letters in it ends up at an
    // address that does not match what is stored, and the product cannot be
    // reached by its own URL at all. The same trap is documented for page
    // names in seo.php, where it is only reported; here it is prevented.
    //
    // Turkish letters are transliterated rather than dropped, so "Çay Bardağı"
    // becomes "Cay_Bardagi" and still reads as itself.
    //
    // Square brackets stay: get_duplicate_catalog_item_address_name_number()
    // appends "[1]" to make a duplicate unique, and this function runs again
    // over an address that already carries one. Hyphen and underscore stay
    // because they are the ordinary word separators in an address.
    $address_name = trim($address_name);
    $address_name = pg_transliterate_to_ascii($address_name);

    // Everything that is not allowed becomes an underscore rather than
    // disappearing, so two different names cannot collapse into one.
    $address_name = preg_replace('/[^A-Za-z0-9\[\]_-]/', '_', $address_name);

    // A run of separators reads as one, and neither end should start or finish
    // on one.
    $address_name = preg_replace('/_{2,}/', '_', $address_name);
    $address_name = trim($address_name, '_');

    // An address written entirely in a script this cannot transliterate -
    // Arabic, Greek, Chinese - reduces to nothing. An empty path segment is
    // not an address, so fall back to a placeholder and let the duplicate
    // numbering below make each one unique.
    if ($address_name === '') {
        $address_name = 'item';
    }
    // assume that the address name does not already exist until we find out otherwise
    $address_name_exists = false;
    // check products table to see if there is already a product with an address name that matches the one we want to use
    $query = "SELECT id FROM products where address_name = '" . escape($address_name) . "' AND id != '" . escape($item_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there is a result, then there is already a product with that address name
    if (mysqli_num_rows($result) > 0) {
        $address_name_exists = true;
    }
    // check product groups table to see if there is already a product group with an address name that matches the one we want to use
    $query = "SELECT id FROM product_groups where address_name = '" . escape($address_name) . "' AND id != '" . escape($item_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there is a result, then there is already a product group with that address name
    if (mysqli_num_rows($result) > 0) {
        $address_name_exists = true;
    }
    // if the address name already exists then create a unique address name
    if ($address_name_exists == true) {
        $address_name .= '[' . get_duplicate_catalog_item_address_name_number($address_name) . ']';
    }
    return $address_name;
}
function get_catalog_item_from_url($forget = false)
{
    // Memoised per request. The render path calls this between two and five
    // times for a single catalog detail page (get_page_content.php lines 2172,
    // 3074, 3409, 3431, 5043), and every call re-ran the same two lookups.
    // Visitor tracking calls it once more at the end of the request, so
    // caching also keeps the new tracking free rather than adding a query.
    //
    // "Per request" holds for a request, which renders one page. A process that
    // renders many - the search indexer, the SEO structure pass - has to be
    // able to forget, or every catalog detail page after the first resolves to
    // the first one's product.
    static $cached = null;

    if ($forget) {
        $cached = null;
        return null;
    }

    if ($cached !== null) {
        return $cached;
    }

    // get the address name
    $address_name = mb_substr(mb_substr($_GET['page'], mb_strpos($_GET['page'], '/')), 1);
    $item_id = '';
    $item_image_name = '';
    $item_type = '';
    $item_name = '';
    $item_enabled = '';
    $item_short_description = '';
    $item_full_description = '';
    $item_address_name = '';
    $item_price = '';
    $item_brand = '';
    $item_meta_description = '';
    $item_mpn = '';
    $item_gtin = '';
    $item_inventory = '';
    $item_inventory_quantity = '';
    $item_backorder = '';

    // get the product group if there is one
    $query = "SELECT
            id,
            image_name,
            name,
            enabled,
            short_description,
            full_description,
            address_name
        FROM product_groups

        WHERE address_name = '" . e($address_name) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if a product group was found, then set its information
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $item_id = $row['id'];
        $item_image_name = $row['image_name'];
        $item_type = 'product group';
        $item_name = $row['name'];
        $item_enabled = $row['enabled'];
        $item_short_description = $row['short_description'];
        $item_full_description = $row['full_description'];
        $item_address_name = $row['address_name'];
        // else a product group was not found, so check for a product
    } else {
        $query = "SELECT
                id,
                name,
                image_name,
                enabled,
                short_description,
                full_description,
                address_name,
                products.price as price,
                products.brand as brand,
                products.meta_description as meta_description,
                products.mpn as mpn,
                products.gtin as gtin,
                inventory,
                inventory_quantity,
                backorder
            FROM products

            WHERE address_name = '" . escape($address_name) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // if a product was found, then set its information
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $item_id = $row['id'];
            $item_image_name = $row['image_name'];
            $item_type = 'product';
            $item_name = $row['name'];
            $item_enabled = $row['enabled'];
            $item_short_description = $row['short_description'];
            $item_full_description = $row['full_description'];
            $item_address_name = $row['address_name'];
            $item_price = $row['price'];
            $item_brand = $row['brand'];
            $item_meta_description = $row['meta_description'];
            $item_mpn = $row['mpn'];
            $item_gtin = $row['gtin'];
            $item_inventory = $row['inventory'];
            $item_inventory_quantity = $row['inventory_quantity'];
            $item_backorder = $row['backorder'];
        }
    }
    // Tell visitor tracking which catalog item this request actually showed.
    // Without this the hourly report only ever sees the host page name, which
    // is the same string ('urun-detay') for every product on the site.
    if ($item_id !== '') {
        pg_track_content(($item_type == 'product group') ? 'product_group' : 'product', $item_id);
    }

    $cached = array(
        'id' => $item_id,
        'image_name' => $item_image_name,
        'type' => $item_type,
        'name' => $item_name,
        'enabled' => $item_enabled,
        'short_description' => $item_short_description,
        'full_description' => $item_full_description,
        'address_name' => $item_address_name,
        'price' => $item_price,
        'brand' => $item_brand,
        'meta_description' => $item_meta_description,
        'mpn' => $item_mpn,
        'gtin' => $item_gtin,
        'inventory' => $item_inventory,
        'inventory_quantity' => $item_inventory_quantity,
        'backorder' => $item_backorder
    );

    return $cached;
}
function get_catalog_item_address_name_from_id($item_id, $item_type)
{
    // if the item type is product group then set sql table accordingly
    if (($item_type == 'product group') || ($item_type == 'product_group')) {
        $sql_table = 'product_groups';
        // else the item type is a product so set the sql tabel accordingly
    } else {
        $sql_table = 'products';
    }
    // get the item id based off of the item type
    $query = "SELECT address_name FROM $sql_table WHERE id = '" . escape($item_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $address_name = $row['address_name'];
    // return the address name
    return $address_name;
}
function get_checkboxes_for_items_user_can_edit($item_type, $selected_ids = array())
{
    $output = '';
    $sql_table = '';
    $sql_columns = '';
    $sql_where = '';
    // prepare tables and columns for sql
    switch ($item_type) {
        case 'ad_regions':
            $sql_table = 'ad_regions';
            $sql_columns = '

                id,

                name';
            break;
        case 'common_regions':
            $sql_table = 'cregion';
            $sql_columns = '

                cregion_id as id,

                cregion_name as name';
            $sql_where = '

                WHERE cregion_designer_type = "no"';
            break;
        case 'menus':
            $sql_table = 'menus';
            $sql_columns = '

                id,

                name';
            break;
    }
    $items = array();
    // get items from database
    $query = "SELECT

            $sql_columns

        FROM $sql_table

        $sql_where

        ORDER BY name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    // format item type for checkboxes
    $item_type = mb_substr($item_type, 0, -1);
    // loop through each item and build checkbox options
    foreach ($items as $item) {
        $checked = '';
        // if the item id is in the selected items array, then check this checkbox
        if (in_array($item['id'], $selected_ids) == true) {
            $checked = ' checked="checked"';
        }
        $output .= '<div class="form-check"><input type="checkbox" name="' . $item_type . '_' . $item['id'] . '" id="' . $item_type . '_' . $item['id'] . '" value="1" class="form-check-input multiselect-checkbox"' . $checked . ' /><label class="form-check-label" for="' . $item_type . '_' . $item['id'] . '">' . h($item['name']) . '</label></div>';
    }
    return $output;
}
function get_items_user_can_edit($item_type, $user_id)
{
    $sql_table = '';
    $sql_columns = '';
    // prepare tables and columns for sql
    switch ($item_type) {
        case 'ad_regions':
            $sql_table = 'users_ad_regions_xref';
            $sql_columns = 'ad_region_id';
            break;
        case 'common_regions':
            $sql_table = 'users_common_regions_xref';
            $sql_columns = 'common_region_id';
            break;
        case 'contact_groups':
            $sql_table = 'users_contact_groups_xref';
            $sql_columns = 'contact_group_id';
            break;
        case 'menus':
            $sql_table = 'users_menus_xref';
            $sql_columns = 'menu_id';
            break;
    }
    $items_user_can_edit = array();
    // get the items that this user can edit
    $query = "SELECT $sql_columns as id

        FROM $sql_table

        WHERE user_id = '" . escape($user_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $items_user_can_edit[] = $row['id'];
    }
    return $items_user_can_edit;
}
function add_edit_button_for_images($object_type = '', $object_id = 0, $content = '', $column_to_update = '')
{
    // If this is for a form list view or form item view, then do not add buttons for GIF's,
    // because for GIF's we have to save a new copy as a PNG because of a Image Editor limitation,
    // and we do not currently support saving a new copy for images in form list views and form item views.
    if (($object_type == 'form_list_view') || ($object_type == 'form_item_view')) {
        $allowed_file_extensions = 'jpg|jpeg|png';
        // Otherwise this is not for a form list view or form item view, so include GIF as an allowed format.
    } else {
        $allowed_file_extensions = 'gif|jpg|jpeg|png';
    }
    // if curl is installed, and if there are image tags in the content, then add edit container to the images
    if ((function_exists('curl_init') == true) && (preg_match_all('/(<img.*?src="(.*?(' . $allowed_file_extensions . '))".*?>)/i', $content, $matches, PREG_SET_ORDER) != 0)) {
        // loop through all images in order to add an edit button to each image
        foreach ($matches as $match) {
            $image_source = trim($match[2]);
            // Remove {path} from the image source so that we can find the image name.
            $image_source = str_replace('{path}', '', $image_source);
            // If there is a slash in the image source,
            // then get image name by looking at content after last slash.
            if (mb_strpos($image_source, '/') !== false) {
                $position_of_last_slash = mb_strrpos($image_source, '/');
                $image_name = mb_substr($image_source, $position_of_last_slash + 1);
                // Otherwise, the image name is the whole source.
            } else {
                $image_name = $image_source;
            }
            $image_name = trim($image_name);
            $image_name = rawurldecode($image_name);
            // get the image content
            $image_content = $match[0];
            // if there is an ID for the image, then set image id to it so that it can be passed into javascript function
            if (preg_match('/id="(.*?)"/i', $image_content, $id) != 0) {
                $image_id = unhtmlspecialchars($id[1]);
                // else, add an ID to the image
            } else {
                // get a unique image id
                $image_id = 'software_image_' . get_unique_image_id($image_name);
                // update the image tag with the id
                $image_content = preg_replace('/<img(.*?)>/i', '<img$1 id="' . h($image_id) . '">', $image_content, 1);
            }
            // if there is a mouseover event for the image, then add our js function call to it
            if (preg_match('/onmouseover=".*?"/i', $image_content) != 0) {
                $image_content = preg_replace('/onmouseover="(.*?)"/i', 'onmouseover="software_show_or_hide_image_edit_button(\'' . h(escape_javascript($image_id)) . '\', event); $1"', $image_content, 1);
                // else, add onmouseover event to image tag
            } else {
                $image_content = preg_replace('/<img(.*?)>/i', '<img$1 onmouseover="software_show_or_hide_image_edit_button(\'' . h(escape_javascript($image_id)) . '\', event);">', $image_content, 1);
            }
            // if there is a onmouseout event for the image, then add our js function call to it
            if (preg_match('/onmouseout=".*?"/i', $image_content) != 0) {
                $image_content = preg_replace('/onmouseout="(.*?)"/i', 'onmouseout="software_show_or_hide_image_edit_button(\'' . h(escape_javascript($image_id)) . '\', event); $1"', $image_content, 1);
                // else, add onmouseover event to image tag
            } else {
                $image_content = preg_replace('/<img(.*?)>/i', '<img$1 onmouseout="software_show_or_hide_image_edit_button(\'' . h(escape_javascript($image_id)) . '\', event);">', $image_content, 1);
            }
            // remove any left over spaces
            $image_content = str_replace('  ', ' ', $image_content);
            // if there is a column specified to update, then prepare it for the link
            if ($column_to_update != '') {
                $column_to_update = '&amp;column_to_update=' . $column_to_update;
            }
            // add link to the image editor
            $image_content .= '<a id="software_edit_button_for_' . h($image_id) . '" href="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/image_editor_edit.php?file_name=' . h(urlencode($image_name)) . '&amp;object_type=' . $object_type . '&amp;object_id=' . $object_id . $column_to_update . '&amp;send_to=' . h(urlencode(get_request_uri())) . '" style="background: #7a7a7a63;cursor:pointer;border: 1px dashed #fff;height:auto;position: absolute; left: 0px; top: 0px; display: none; padding: .5em; margin: 0; text-decoration: none; z-index: 9;border-bottom-right-radius: 5px !important;" title="Edit Image (' . h($image_name) . ') with Software Image Editor" onmouseover="software_show_or_hide_image_edit_button(\'' . h(escape_javascript($image_id)) . '\', event);" onmouseout="software_show_or_hide_image_edit_button(\'' . h(escape_javascript($image_id)) . '\', event);"><img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/icon_image_editor.png" width="50" height="auto" alt="' . lang('Software Image Editor') . '"></a>';
            // output the image we created above with a link around it
            $content = preg_replace('/' . escape_regex($match[0]) . '/i', $image_content, $content, 1);
        }
    }
    return $content;
}
// global array used to create unique image id
$image_ids_in_array = array();
// the following function is used in order to generate id's for images, so that the image edit button can be added
function get_unique_image_id($image_name, $number = 0)
{
    global $image_ids_in_array;
    $image_id = '';
    // if the number is greater than zero, then add an underscore with the number to the image name
    if ($number > 0) {
        $image_id = $image_name . '_' . $number;
        // else just use the image name as the id
    } else {
        $image_id = $image_name;
    }
    // if the current image name is already in the array, then call this function again until a unique id is found
    if (in_array($image_id, $image_ids_in_array) === true) {
        return get_unique_image_id($image_name, $number + 1);
    }
    // add the image id to the array
    $image_ids_in_array[] = $image_id;
    return $image_id;
}
function update_image_in_content($content, $original_image_name, $new_image_name)
{
    // if there are image tags in the content that are in the files directory, then find the image that needs to be updated and update it's attributes
    if (preg_match_all('/<img.*?src="(.*?)".*?>/i', $content, $matches) != 0) {
        // split image tags and names into their own arrays
        $image_tags = $matches[0];
        $image_sources = $matches[1];
        // Loop through the image sources and see if they match the image we are needing to update.
        foreach ($image_sources as $key => $image_source) {
            // Remove {path} from the image source so that we can find the image name.
            $image_source = str_replace('{path}', '', $image_source);
            // If there is a slash in the image source,
            // then get image name by looking at content after last slash.
            if (mb_strpos($image_source, '/') !== false) {
                $position_of_last_slash = mb_strrpos($image_source, '/');
                $image_name = mb_substr($image_source, $position_of_last_slash + 1);
                // Otherwise, the image name is the whole source.
            } else {
                $image_name = $image_source;
            }
            $image_name = trim($image_name);
            // If this is the image that we need to update, then update it.
            if (($image_name == $original_image_name) || ($image_name == encode_url_path($original_image_name))) {
                // set the image tag content in a variable
                $image_content = $image_tags[$key];
                // If the orignal file name is different than the current file name,
                // then update the src, title and alt attributes in the image tag.
                if ($original_image_name != $new_image_name) {
                    $image_content = preg_replace('/src=".*?"/i', 'src="{path}' . h($new_image_name) . '"', $image_content);
                    $image_content = preg_replace('/alt=".*?"/i', 'alt="' . h($new_image_name) . '"', $image_content);
                    $image_content = preg_replace('/title=".*?"/i', 'title="' . h($new_image_name) . '"', $image_content);
                }
                // Get the dimensions of the new image
                $image_size = getimagesize(FILE_DIRECTORY_PATH . '/' . $new_image_name);
                $image_width = $image_size[0];
                $image_height = $image_size[1];
                // Update the width and height attributes in the image tag.
                // The standard image plugin for CKEditor uses style="width: 100px; height: 100px;"
                // instead of width="100" height="100", so we have added support for that
                // format also.  We have left the code for the old width="100" height="100"
                // for images that were entered with old editor or if someone manually enters
                // that type of format.
                $image_content = preg_replace('/width=".*?"/i', 'width="' . $image_width . '"', $image_content);
                $image_content = preg_replace('/width:.*?px/i', 'width: ' . $image_width . 'px', $image_content);
                $image_content = preg_replace('/height=".*?"/i', 'height="' . $image_height . '"', $image_content);
                $image_content = preg_replace('/height:.*?px/i', 'height: ' . $image_height . 'px', $image_content);
                // return the updated image tag
                $content = preg_replace('/' . escape_regex($image_tags[$key]) . '/i', $image_content, $content);
            }
        }
    }
    // return the content
    return $content;
}
// this function is responsible for checking content for variables, and replacing any variables with the content from the submitted form
function get_variable_submitted_form_data_for_content($current_page_id, $submitted_form_id, $content, $prepare_for_html = true)
{
    // if the content contains variables then replace them with data
    if (mb_strpos($content, '^^') !== false) {
        // get standard fields
        $standard_fields = get_standard_fields_for_view();

        // built up in the loop below, so it has to start out empty
        $sql_field_selects = '';

        // loop through all standard fields in order to prepare filters
        foreach ($standard_fields as $standard_field) {
            if ($sql_field_selects) {
                $separator = ",\n";
            } else {
                $separator = '';
            }
            $sql_field_selects .= $separator . $standard_field['sql_name'] . " as " . $standard_field['value'];
        }
        // get submitted form
        $query = "SELECT

                forms.id,

                forms.user_id as submitter_id,

                forms.form_editor_user_id,

                forms.page_id as custom_form_page_id,

                submitter.user_badge AS submitter_badge,

                submitter.user_badge_label AS submitter_badge_label,

                last_modifier.user_badge AS last_modifier_badge,

                last_modifier.user_badge_label AS last_modifier_badge_label,

                newest_comment_submitter.user_badge AS newest_comment_submitter_badge,

                newest_comment_submitter.user_badge_label AS newest_comment_submitter_badge_label,

                $sql_field_selects

            FROM forms

            LEFT JOIN user as submitter ON forms.user_id = submitter.user_id

            LEFT JOIN user as last_modifier ON forms.last_modified_user_id = last_modifier.user_id

            LEFT JOIN submitted_form_info ON ((forms.id = submitted_form_info.submitted_form_id) AND (submitted_form_info.page_id = '" . e($current_page_id) . "'))

            LEFT JOIN comments AS newest_comment ON submitted_form_info.newest_comment_id = newest_comment.id

            LEFT JOIN user AS newest_comment_submitter ON newest_comment.created_user_id = newest_comment_submitter.user_id

            WHERE forms.id = '" . e($submitted_form_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $submitted_form = mysqli_fetch_assoc($result);
        // get form data for all custom fields
        $query = "SELECT

                form_data.form_field_id,

                form_data.data,

                count(*) as number_of_values,

                form_fields.name,

                form_fields.type,

                form_fields.office_use_only as office_use_only,

                files.name as file_name

            FROM form_data

            LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id

            LEFT JOIN files on form_data.file_id = files.id

            WHERE form_data.form_id = '" . escape($submitted_form['id']) . "'

            GROUP BY form_data.form_field_id";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // initialize array to remember which fields have data, for use with conditionals later
        $custom_fields_with_data = array();
        $fields = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $fields[] = $row;
        }
        // loop through all field data
        foreach ($fields as $field) {
            // if there is more than one value, get all values
            if ($field['number_of_values'] > 1) {
                $query = "SELECT data

                    FROM form_data

                    WHERE (form_id = '" . escape($submitted_form['id']) . "') AND (form_field_id = '" . $field['form_field_id'] . "')

                    ORDER BY id";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                $field['data'] = array();
                while ($row = mysqli_fetch_assoc($result)) {
                    $field['data'][] = $row['data'];
                }
            }
            $data = '';
            // if there are multiple data parts, prepare data string with commas for separation
            if (is_array($field['data']) == true) {
                foreach ($field['data'] as $data_part) {
                    if ($data != '') {
                        $data .= ', ';
                    }
                    $data .= $data_part;
                }
                // else there are not multiple data parts
            } else {
                // if there is a file name, use file name for data
                if ($field['file_name']) {
                    $data = $field['file_name'];
                    // else there is not a file name, so just use data
                } else {
                    $data = $field['data'];
                }
            }
            $submitted_form['field_' . $field['form_field_id']] = $data;
            // if there is data for this field, remember that for conditionals later
            if ($data != '') {
                $custom_fields_with_data[$field['name']] = true;
            }
        }
        // get custom fields
        $query = "SELECT

                id,

                name,

                type,

                wysiwyg

            FROM form_fields

            WHERE page_id = '" . escape($submitted_form['custom_form_page_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $custom_fields = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $custom_fields[] = $row;
        }
        // get all conditionals
        preg_match_all('/\[\[(.*?\^\^(.*?)\^\^.*?)(\|\|(.*?))?\]\]/si', $content, $conditionals, PREG_SET_ORDER);
        // loop through all conditionals
        foreach ($conditionals as $conditional) {
            $whole_string = $conditional[0];
            $positive_string = $conditional[1];
            $negative_string = $conditional[4];
            $field_name = $conditional[2];
            // if field name is reference code and there is another field, use the other field,
            // because we don't want to use reference code for conditional checking
            if (($field_name == 'reference_code') && (preg_match('/\[\[(.*?\^\^reference_code\^\^.*?\^\^(.*?)\^\^.*?)(\|\|(.*?))?\]\]/si', $whole_string, $conditional))) {
                $field_name = $conditional[2];
            }
            // assume that the field name is not valid, until we find out otherwise
            // we don't want to replace the conditional if the field name is not valid, so that a ^^name^^ conditional will be left alone if used with an e-mail campaign
            $field_name_valid = false;
            // loop through the standard fields in order to determine if the field name is a valid standard field name
            foreach ($standard_fields as $standard_field) {
                // if this standard field value matches the field name, then the field name is valid, so remember that and break out of the loop
                if ($standard_field['value'] == $field_name) {
                    $field_name_valid = true;
                    break;
                }
            }
            // if we don't know if the field name is valid yet, then loop through the custom fields in order to check if the field name is a valid custom field
            if ($field_name_valid == false) {
                foreach ($custom_fields as $custom_field) {
                    // if this custom field name matches the field name, then the field name is valid, so remember that and break out of the loop
                    if ($custom_field['name'] == $field_name) {
                        $field_name_valid = true;
                        break;
                    }
                }
            }
            // if the field name is valid, then replace conditional
            if ($field_name_valid == true) {
                // if there is data to output, use first part of conditional
                // $field_name comes from the layout and refers to a form field that the site owner
                // defined, so it can be any name at all.  Neither array is guaranteed to have a
                // matching key.
                if ((($submitted_form[$field_name] ?? '') != '') || (($custom_fields_with_data[$field_name] ?? false) == true)) {
                    $content = str_replace($whole_string, $positive_string, $content);
                    // else there is no data to output, so use second part of conditional
                } else {
                    $content = str_replace($whole_string, $negative_string, $content);
                }
            }
        }
        // get all variables so they can be replaced with data
        preg_match_all('/\^\^(.*?)\^\^(%%(.*?)%%)?/i', $content, $variables, PREG_SET_ORDER);
        // loop through the variables in order to replace them with data
        foreach ($variables as $variable) {
            $whole_string = $variable[0];
            $field_name = $variable[1];
            $date_format = '';
            // if a date format was passed along with the variable, then store that
            if (isset($variable[3]) == true) {
                $date_format = $variable[3];
                // if prepare for HTML is enabled, then that means
                // we have to use unhtmlspecialchars() because the date format was created
                // in the rich-text editor, so there might be HTML entities (e.g. &lt;)
                // and the date() function would convert those characters into date elements
                if ($prepare_for_html == true) {
                    $date_format = unhtmlspecialchars($date_format);
                }
            }
            // assume that the field name is not valid, until we find out otherwise
            $field_name_valid = false;
            $field_group = '';
            $field_type = '';
            $field_id = '';
            $field_wysiwyg = '';
            // loop through the standard fields in order to determine
            // if the field name is valid and to get field info
            foreach ($standard_fields as $standard_field) {
                // if this standard field value matches the field name, then the field name is valid,
                // so remember that, store field info, and break out of the loop
                if ($standard_field['value'] == $field_name) {
                    $field_name_valid = true;
                    $field_group = 'standard';
                    $field_type = $standard_field['type'];
                    break;
                }
            }
            // if we don't know if the field name is valid yet, then loop through the custom fields
            // in order to check if the field name is a valid custom field and to get field info
            if ($field_name_valid == false) {
                foreach ($custom_fields as $custom_field) {
                    // if this custom field name matches the field name, then the field name is valid,
                    // so remember that, store field info, and break out of loop
                    if ($custom_field['name'] == $field_name) {
                        $field_name_valid = true;
                        $field_group = 'custom';
                        $field_id = $custom_field['id'];
                        $field_type = $custom_field['type'];
                        $field_wysiwyg = $custom_field['wysiwyg'];
                        break;
                    }
                }
            }
            // if the field name is valid, then continue to replace variable with data
            if ($field_name_valid == true) {
                $data = '';
                // set prepare for html value to global value, until we find it should be set to something else
                $prepare_this_field_for_html = $prepare_for_html;
                // get values differently based on the field group
                switch ($field_group) {
                    case 'standard':
                        $data = $submitted_form[$field_name] ?? '';
                        break;
                    case 'custom':
                        $data = $submitted_form['field_' . $field_id];
                        // if this field is a WYSIWYG field, then do not prepare for HTML
                        if ($field_wysiwyg == 1) {
                            $prepare_this_field_for_html = false;
                        }
                        break;
                }
                $data = prepare_form_data_for_output($data, $field_type, $prepare_this_field_for_html, $date_format);
                // if this is a standard field, then do some extra things for standard fields
                if ($field_group == 'standard') {
                    // If this is the number of views or number of comments field then do some things for the numeric value.
                    if (($field_name == 'number_of_views') || ($field_name == 'number_of_comments')) {
                        // If the value is blank, then set it to 0.
                        if ($data == '') {
                            $data = 0;
                            // Otherwise the value is not blank, so format the number,
                            // so that it has commas in the thousands place.
                        } else {
                            $data = number_format($data);
                        }
                    }
                    // if this is the newest comment field and the message is greater than 100 characters, then shorten message
                    if (($field_name == 'newest_comment') && (mb_strlen($data) > 100)) {
                        $data = mb_substr($data, 0, 100) . '...';
                    }
                    // if this is the newest comment name field and the value is blank and there is a newest comment, then set name to "Anonymous"
                    if (($field_name == 'newest_comment_name') && ($data == '') && ($submitted_form['newest_comment_id'] != '')) {
                        $data = 'Anonymous';
                    }
                    // If this is the submitter field and badge is enabled for the submitter,
                    // or if this is the last modifier field and badge is enabled for the last modifier,
                    // or if this is the newest comment name field and badge is enabled for the newest comment submitter,
                    // then add badge
                    if ((($field_name == 'submitter') && ($submitted_form['submitter_badge'] == 1) && (($submitted_form['submitter_badge_label'] != '') || (BADGE_LABEL != ''))) || (($field_name == 'last_modifier') && ($submitted_form['last_modifier_badge'] == 1) && (($submitted_form['last_modifier_badge_label'] != '') || (BADGE_LABEL != ''))) || (($field_name == 'newest_comment_name') && ($submitted_form['newest_comment_submitter_badge'] == 1) && (($submitted_form['newest_comment_submitter_badge_label'] != '') || (BADGE_LABEL != '')))) {
                        $badge_label = '';
                        // Get the user's badge label differently based on the field.
                        switch ($field_name) {
                            case 'submitter':
                                $badge_label = $submitted_form['submitter_badge_label'];
                                break;
                            case 'last_modifier':
                                $badge_label = $submitted_form['last_modifier_badge_label'];
                                break;
                            case 'newest_comment_name':
                                $badge_label = $submitted_form['newest_comment_submitter_badge_label'];
                                break;
                        }
                        // If the user's badge label is blank, then use default label.
                        if ($badge_label == '') {
                            $badge_label = BADGE_LABEL;
                        }
                        // If this field is being prepared for HTML, then add HTML version of badge.
                        if ($prepare_this_field_for_html == true) {
                            $data .= ' <span class="software_badge ' . h(get_class_name($badge_label)) . '">' . h($badge_label) . '</span>';
                            // Otherwise this field is being prepared for plain text, so add plain text version of badge.
                        } else {
                            $data .= ' [' . $badge_label . ']';
                        }
                    }
                    // If this is the comment attachments field and the data is not blank,
                    // then output the comment attachments as links.
                    if (($field_name == 'comment_attachments') && ($data != '')) {
                        $comment_attachments = explode('||', $data);
                        $output_comment_attachments = '';
                        foreach ($comment_attachments as $comment_attachment) {
                            if ($output_comment_attachments != '') {
                                $output_comment_attachments .= ', ';
                            }
                            // If this field is being prepared for HTML, then output links for comment attachments.
                            if ($prepare_this_field_for_html == true) {
                                $output_comment_attachments .= '<a href="' . OUTPUT_PATH . h(encode_url_path($comment_attachment)) . '" target="_blank">' . h($comment_attachment) . '</a>';
                                // Otherwise this field is being prepared for plain text, so just output attachment name.
                            } else {
                                $output_comment_attachments .= $comment_attachment;
                            }
                        }
                        $data = $output_comment_attachments;
                    }
                }
                // replace the variable with the data
                // we can't use str_replace() for this because we need to limit the number of replacements to 1
                // in order to prevent bugs where it will replace variables further below that might have date formats
                $content = preg_replace('/' . preg_quote($whole_string, '/') . '/', addcslashes($data, '\\$'), $content, 1);
            }
        }
    }
    // return the content
    return $content;
}
// this function searches the content for links, then disables them and returns the new content
function disable_links_in_content($content)
{
    // if there are links in the content, then loop through them all and disable their links
    if (preg_match_all('/<a.*?href=["|\'\'](.*?)["|\'\'].*?>.*?<\/a>/', $content, $matches) > 0) {
        foreach ($matches[1] as $match) {
            // if the link is not an ad region link, then disable the link
            if (preg_match('/#software_ad_.*?/i', $match) == 0) {
                $content = preg_replace('/<a(.*?)href=["|\'\']' . escape_regex($match) . '["|\'\'](.*?)>(.*?)<\/a>/si', '<a$1href="javascript:void(0)"$2>$3</a>', $content, 1);
            }
        }
    }
    return $content;
}
// Create a function that allows us to get a valid class name for CSS
// from some general content.  For example, if a style name is "home page!",
// then this function will convert the name into "home_page".
function get_class_name($content)
{
    $class_name = $content;
    // convert spaces to underscores so that CSS does not treat it as separate classes
    $class_name = str_replace(' ', '_', $class_name);
    // remove any characters that are not valid for a class name
    $class_name = preg_replace('/[^A-Za-z0-9-_]/', '', $class_name);
    return $class_name;
}

function render_layout($properties)
{
    extract($properties);
    // If a page id was supplied, then get page info.
    if ($page_id) {
        $page = db_item("SELECT
                page_type AS type,
                layout_type,
                layout_modified
            FROM page
            WHERE page_id = '" . e($page_id) . "'");
        // Otherwise a page id was not supplied,
        // because we are loading a system screen, like forgot password,
        // where a page for that system screen does not exist.
    } else {
        $page['layout_type'] = 'system';
        $page['type'] = $page_type;
    }
    // If the style that is being used for the current page,
    // has an override layout type, then use it.
    if (pg_render_layout_type()) {
        $page['layout_type'] = pg_render_layout_type();
    }
    ob_start();
    if ($page['layout_type'] == 'system') {
        $template_name = str_replace(' ', '_', $page['type']) . '_system.php';
        include(PG_FUNCTIONS_DIR . '/includes/templates/' . $template_name);
    } else {
        require_once(PG_FUNCTIONS_DIR . '/generate_layout_content.php');
        if ($page['layout_modified']) {
            include(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php');
        } else {
            $template_name = str_replace(' ', '_', $page['type']) . '.php';
            include(PG_FUNCTIONS_DIR . '/includes/templates/' . $template_name);
        }
    }
    return ob_get_clean();
}

function render($properties)
{
    extract($properties);
    ob_start();
    include(PG_FUNCTIONS_DIR . '/includes/templates/' . $template);
    return ob_get_clean();
}

// Gets a URL for a page type that should just have one page (e.g. change password).
function get_page_type_url($page_type)
{
    $page_name = db_value("SELECT page_name

        FROM page

        WHERE page_type = '" . e($page_type) . "'

        LIMIT 1");
    if ($page_name != '') {
        return PATH . encode_url_path($page_name);
    } else {
        return false;
    }
}
function get_layout_type($page_id)
{
    // If the style that is being used for the current page,
    // has an override layout type, then use it.
    if (pg_render_layout_type()) {
        return pg_render_layout_type();
        // Otherwise use the page's layout type.
    } else {
        return db_value("SELECT layout_type FROM page WHERE page_id = '" . e($page_id) . "'");
    }
}
