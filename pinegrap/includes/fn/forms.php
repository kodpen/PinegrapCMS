<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Control panel form helpers: select_*() builders, option lists, field preparation, reference codes, and the frontend screen loaders.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
// Gets the activated style for a particular folder and device type.
// This is used in order to figure out the style for a page, when a page does not
// have a style set for it.  This function uses recursion to continue to look up
// into the tree into parent folders if a child folder does not have a style set.
function get_style($folder_id, $device_type = 'desktop')
{
    switch ($device_type) {
        case 'desktop':
            $query = "SELECT

                    folder_parent,

                    folder_style,

                    folder_level

                FROM folder

                WHERE folder_id = '" . escape($folder_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
            $row = mysqli_fetch_assoc($result);
            $folder_parent = $row['folder_parent'] ?? '';
            $folder_style = $row['folder_style'] ?? '';
            $folder_level = $row['folder_level'] ?? '';
            // if there is a style for this folder, then return it
            if ($folder_style) {
                return $folder_style;
                // else if this is the root folder, then there is no where else to look, so return 0
            } else if ($folder_level == 0) {
                return 0;
                // else use recursion to check for a style in the parent folder
            } else {
                return get_style($folder_parent, $device_type);
            }
            break;
        case 'mobile':
            $query = "SELECT

                    folder_parent,

                    mobile_style_id,

                    folder_level

                FROM folder

                WHERE folder_id = '" . escape($folder_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
            $row = mysqli_fetch_assoc($result);
            $folder_parent = $row['folder_parent'];
            $mobile_style_id = $row['mobile_style_id'];
            $folder_level = $row['folder_level'];
            // if there is a mobile style for this folder, then return it
            if ($mobile_style_id != 0) {
                return $mobile_style_id;
                // else if this is the root folder, then there is no where else to look, so return 0
            } else if ($folder_level == 0) {
                return 0;
                // else use recursion to check for a style in the parent folder
            } else {
                return get_style($folder_parent, $device_type);
            }
            break;
    }
}
// Get the style that should be shown for a particular page.  If the visitor is
// a designer and is previewing styles, then this function will return the style
// that is currently being previewed.  Otherwise, if the visitor is a common
// website visitor, then it will return the activated style for the page.
function get_preview_style($properties)
{
    $page_id = $properties['page_id'];
    $folder_id = $properties['folder_id'];
    $page_style_id = $properties['page_style_id'];
    $page_mobile_style_id = $properties['page_mobile_style_id'];
    $device_type = $properties['device_type'];
    $style_id = '';
    // If theme/style preview is enabled, and this page is not being loaded in the
    // theme designer, or if the theme that is being edited in the theme designer
    // is the same as the theme that is being previewed, then check if we should use
    // a preview style instead of the activated style.
    if ((isset($_SESSION['software']['preview_theme_id']) == true) && (($_GET['edit_theme'] != 'true') || ($_GET['theme_id'] == $_SESSION['software']['preview_theme_id']))) {
        // If the selected theme is the activated theme, then get style in a certain way.
        if (($_SESSION['software']['preview_theme_id']) && ($_SESSION['software']['preview_theme_id'] == db_value("SELECT id FROM files WHERE activated_" . $device_type . "_theme = '1'"))) {
            // If a style has been selected, then use that style.
            if (isset($_SESSION['software']['preview_style']['theme_' . $_SESSION['software']['preview_theme_id'] . '_page_' . $page_id . '_' . $device_type]) == true) {
                // If the selected style is blank, then that means,
                // the default option has been selected, so get style from folders.
                if (!$_SESSION['software']['preview_style']['theme_' . $_SESSION['software']['preview_theme_id'] . '_page_' . $page_id . '_' . $device_type]) {
                    $style_id = get_style($folder_id, $device_type);
                    // If the device type is set to mobile and a default mobile style id
                    // could not be found, then get desktop style id because we fallback
                    // to a desktop style when a mobile style cannot be found for a page
                    if (($device_type == 'mobile') && ($style_id == 0)) {
                        $style_id = get_style($folder_id, 'desktop');
                    }
                    // Otherwise the selected style is not blank, so use that style.
                } else {
                    $style_id = $_SESSION['software']['preview_style']['theme_' . $_SESSION['software']['preview_theme_id'] . '_page_' . $page_id . '_' . $device_type];
                }
            }
            // Otherwise the selected theme is not the activated theme,
            // so get style in a different way.
        } else {
            // If a style has not been selected yet, then check for preview style in database.
            if (isset($_SESSION['software']['preview_style']['theme_' . $_SESSION['software']['preview_theme_id'] . '_page_' . $page_id . '_' . $device_type]) == false) {
                $style_id = db_value("SELECT style_id

                    FROM preview_styles

                    WHERE

                        (page_id = '" . e($page_id) . "')

                        AND (theme_id = '" . e($_SESSION['software']['preview_theme_id']) . "')

                        AND (device_type = '" . e($device_type) . "')");
                // Otherwise, use style that has been selected.
            } else {
                $style_id = $_SESSION['software']['preview_style']['theme_' . $_SESSION['software']['preview_theme_id'] . '_page_' . $page_id . '_' . $device_type];
            }
        }
    }
    // If no style is being previewed then get activated style.
    if (!$style_id) {
        $activated_style = get_activated_style(array(
            'page_id' => $page_id,
            'folder_id' => $folder_id,
            'page_style_id' => $page_style_id,
            'page_mobile_style_id' => $page_mobile_style_id,
            'device_type' => $device_type
        ));
        $style_id = $activated_style['id'];
        // If there was no mobile style, then the device type might have been
        // switched to desktop, so update the device type.
        $device_type = $activated_style['device_type'];
    }
    return array(
        'id' => $style_id,
        'device_type' => $device_type
    );
}
// Gets the activated style for a particular page and device type.
// If the page does not have style properties set on it,
// then it will check its parent folders for the style.
// This function ignores any style that might be being previewed.
// It just gets the current production style for a page.
function get_activated_style($properties)
{
    $page_id = $properties['page_id'];
    $folder_id = $properties['folder_id'];
    $page_style_id = $properties['page_style_id'];
    $page_mobile_style_id = $properties['page_mobile_style_id'];
    $device_type = $properties['device_type'];
    $style_id = '';
    // get the style id differently based on the device type
    switch ($device_type) {
        case 'desktop':
        default:
            $style_id = $page_style_id;
            // if the page does not have a desktop style, then get desktop style from parent folders
            if ($style_id == 0) {
                $style_id = get_style($folder_id, 'desktop');
            }
            break;
        case 'mobile':
            $style_id = $page_mobile_style_id;
            // if the page does not have a mobile style, then get mobile style from parent folders
            if ($style_id == 0) {
                $style_id = get_style($folder_id, 'mobile');
                // If a mobile style could not be found, then get desktop style and switch device type to desktop,
                // so that we don't use mobile theme.
                if ($style_id == 0) {
                    $style_id = $page_style_id;
                    // if the page does not have a desktop style, then get desktop style from parent folders
                    if ($style_id == 0) {
                        $style_id = get_style($folder_id, 'desktop');
                    }
                    $device_type = 'desktop';
                }
            }
            break;
    }
    return array(
        'id' => $style_id,
        'device_type' => $device_type
    );
}
/**
 *
 * Generate a random string using a variety of character sets.
 * Compatible with PHP 7.0 - 8.5. Uses the best available CSPRNG:
 *   - random_int (PHP 7+)
 *   - openssl_random_pseudo_bytes (fallback for PHP 5.6)
 *   - mt_rand (last resort; not cryptographically secure)
 *
 * Accepts $properties array with keys:
 *   - type: one of ('letters_and_numbers','lowercase_letters_and_numbers',
 *                    'uppercase_letters_and_numbers','lowercase_letters',
 *                    'uppercase_letters','numbers') or custom set via 'characters'
 *   - length: integer length of string (default 10)
 *   - characters: optional string or array of characters to use (overrides 'type')
 *
 * Returns generated string.
 */
function get_random_string($properties = array())
{
    // normalize input
    $type = isset($properties['type']) ? (string) $properties['type'] : 'letters_and_numbers';
    $length = isset($properties['length']) ? (int) $properties['length'] : 10;
    if ($length <= 0) {
        $length = 10;
    }

    // allow characters to be passed as array or string
    $characters = null;
    if (isset($properties['characters'])) {
        if (is_array($properties['characters'])) {
            $characters = implode('', $properties['characters']);
        } else {
            $characters = (string) $properties['characters'];
        }
    }

    // default character sets (only used if $characters not provided)
    if ($characters === null || $characters === '') {
        switch ($type) {
            case 'lowercase_letters_and_numbers':
                $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
                break;
            case 'uppercase_letters_and_numbers':
                $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                break;
            case 'lowercase_letters':
                $characters = 'abcdefghijklmnopqrstuvwxyz';
                break;
            case 'uppercase_letters':
                $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'numbers':
                $characters = '0123456789';
                break;
            case 'letters_and_numbers':
            default:
                $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                break;
        }
    }

    // Use 8bit to work with bytes/indexing reliably
    $chars_len = mb_strlen($characters, '8bit');
    if ($chars_len === 0) {
        return '';
    }
    $max = $chars_len - 1;

    // Helper to produce a secure random int in [min, max] where possible.
    $secureRandomInt = function ($min, $max) {
        // Try built-in random_int (PHP 7+)
        if (function_exists('random_int')) {
            try {
                return random_int($min, $max);
            } catch (Exception $e) {
                // fall through to other methods on failure
            }
        }

        // Fallback to OpenSSL where available (works on PHP 5.6+ typically)
        if (function_exists('openssl_random_pseudo_bytes')) {
            $range = ($max - $min) + 1;
            if ($range <= 1) {
                return $min;
            }
            // 31-bit positive integer max
            $max_rand = 0x7fffffff;
            // Use rejection sampling to avoid modulo bias
            $limit = (int) (floor($max_rand / $range) * $range);
            do {
                $bytes = openssl_random_pseudo_bytes(4);
                if ($bytes === false) {
                    break;
                }
                $unpacked = unpack('N', $bytes);
                $val = $unpacked[1] & 0x7fffffff; // force positive 31-bit
            } while ($val >= $limit);
            if (isset($val)) {
                return $min + ($val % $range);
            }
        }

        // Last resort: mt_rand (not cryptographically secure)
        return mt_rand($min, $max);
    };

    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $idx = $secureRandomInt(0, $max);
        // use mb_substr with 8bit encoding to reliably index bytes
        $result .= mb_substr($characters, $idx, 1, '8bit');
    }

    return $result;
}

// outputs the form's <select> page list for selecting a page (value is page id)
function select_page($page_id = '', $page_type = '')
{
    global $user;
    $folders_that_user_has_access_to = array();
    // if user is a basic user, then get folders that user has access to
    if ($user['role'] == 3) {
        $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
    }
    // if a page type was given, prepare to only return pages with that page type
    if ($page_type) {
        // If multiple page types were passed in an array, then prepare filter for all of them.
        if (is_array($page_type)) {
            $where = "WHERE (";
            foreach ($page_type as $key => $type) {
                if ($key != 0) {
                    $where .= "OR ";
                }
                $where .= "(page.page_type = '" . e($type) . "') ";
            }
            $where .= ")";
            // Otherwise only one page type was passed, so prepare single filter.
        } else {
            $where = "WHERE page.page_type = '" . e($page_type) . "'";
        }
    } else {
        $where = '';
    }
    // if there is not already a where statement, then output the starting where part
    if ($where == '') {
        $where .= 'WHERE ';
        // else there is already a where statement, so add an and
    } else {
        $where .= ' AND ';
    }
    $where .= '(folder.folder_archived = "0")';
    // get pages
    $query = "SELECT

            page.page_id as id,

            page.page_name as name,

            page.page_folder as folder_id

        FROM page

        LEFT JOIN folder ON page.page_folder = folder.folder_id

        $where

        ORDER BY page.page_name";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $pages = array();
    // loop through all pages so they can be added to array
    while ($row = mysqli_fetch_assoc($result)) {
        $pages[] = $row;
    }
    $output = '';
    // loop through all pages so their options can be outputted
    foreach ($pages as $page) {
        // if user has access to folder that page is in, then output option
        if (check_folder_access_in_array($page['folder_id'], $folders_that_user_has_access_to) == true) {
            // if this page should be selected, then select it
            if ($page['id'] == $page_id) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }
            // output option
            $output .= '<option value="' . $page['id'] . '"' . $selected . '>' . h($page['name']) . '</option>';
        }
    }
    return $output;
}
// outputs the form's <select> page list for selecting a custom form
function select_custom_form($page_id = '', $user = '')
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    // get pages
    $query = "SELECT

                page.page_id,

                page.page_name,

                page.page_folder,

                custom_form_pages.form_name

             FROM page

             LEFT JOIN custom_form_pages ON page.page_id = custom_form_pages.page_id

             WHERE " . pg_form_page_sql('page') . "

             ORDER BY custom_form_pages.form_name";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['page_id'] == $page_id) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        // if access does not need to be validated
        // or custom form is the current selected custom form
        // or user has access to this custom form,
        // prepare to output custom form option
        if (!$user || $selected || (check_edit_access($row['page_folder']) == true)) {
            if ($row['form_name']) {
                $name = $row['form_name'];
            } else {
                $name = $row['page_name'];
            }
            $output .= '<option value="' . $row['page_id'] . '"' . $selected . '>' . h($name) . '</option>';
        }
    }
    return $output;
}
// outputs the form's <select> list for selecting a page type
function select_page_type($page_type, $user)
{
    $output = '';

    // Only the case matching the current page type sets its own variable below, but every
    // one of them is read when the option list is built, so they all have to start empty.
    $affiliate_sign_up_confirmation_status = '';
    $affiliate_sign_up_form_status = '';
    $affiliate_welcome_status = '';
    $billing_information_status = '';
    $calendar_event_view_status = '';
    $calendar_view_status = '';
    $catalog_detail_status = '';
    $catalog_status = '';
    $change_password_status = '';
    $custom_form_confirmation_status = '';
    $custom_form_status = '';
    $email_a_friend_status = '';
    $email_preferences_status = '';
    $error_status = '';
    $express_order_status = '';
    $folder_view_status = '';
    $forgot_password_status = '';
    $form_item_view_status = '';
    $form_list_view_status = '';
    $form_view_directory_status = '';
    $login_status = '';
    $logout_status = '';
    $membership_confirmation_status = '';
    $membership_entrance_status = '';
    $my_account_profile_status = '';
    $my_account_status = '';
    $order_form_status = '';
    $order_preview_status = '';
    $order_receipt_status = '';
    $photo_gallery_status = '';
    $registration_confirmation_status = '';
    $registration_entrance_status = '';
    $search_results_status = '';
    $set_password_status = '';
    $shipping_address_and_arrival_status = '';
    $shipping_method_status = '';
    $shopping_cart_status = '';
    $update_address_book_status = '';
    $view_order_status = '';

    switch ($page_type) {
        case 'change password':
            $change_password_status = ' selected="selected"';
            break;
        case 'set password':
            $set_password_status = ' selected="selected"';
            break;
        case 'email a friend':
            $email_a_friend_status = ' selected="selected"';
            break;
        case 'error':
            $error_status = ' selected="selected"';
            break;
        case 'folder view':
            $folder_view_status = ' selected="selected"';
            break;
        case 'forgot password':
            $forgot_password_status = ' selected="selected"';
            break;
        case 'login':
            $login_status = ' selected="selected"';
            break;
        case 'logout':
            $logout_status = ' selected="selected"';
            break;
        case 'my account':
            $my_account_status = ' selected="selected"';
            break;
        case 'my account profile':
            $my_account_profile_status = ' selected="selected"';
            break;
        case 'email preferences':
            $email_preferences_status = ' selected="selected"';
            break;
        case 'view order':
            $view_order_status = ' selected="selected"';
            break;
        case 'update address book':
            $update_address_book_status = ' selected="selected"';
            break;
        case 'search results':
            $search_results_status = ' selected="selected"';
            break;
        case 'registration entrance':
            $registration_entrance_status = ' selected="selected"';
            break;
        case 'registration confirmation':
            $registration_confirmation_status = ' selected="selected"';
            break;
        case 'membership entrance':
            $membership_entrance_status = ' selected="selected"';
            break;
        case 'membership confirmation':
            $membership_confirmation_status = ' selected="selected"';
            break;
        case 'custom form':
            $custom_form_status = ' selected="selected"';
            break;
        case 'custom form confirmation':
            $custom_form_confirmation_status = ' selected="selected"';
            break;
        case 'form list view':
            $form_list_view_status = ' selected="selected"';
            break;
        case 'form item view':
            $form_item_view_status = ' selected="selected"';
            break;
        case 'form view directory':
            $form_view_directory_status = ' selected="selected"';
            break;
        case 'calendar view':
            $calendar_view_status = ' selected="selected"';
            break;
        case 'calendar event view':
            $calendar_event_view_status = ' selected="selected"';
            break;
        case 'catalog':
            $catalog_status = ' selected="selected"';
            break;
        case 'catalog detail':
            $catalog_detail_status = ' selected="selected"';
            break;
        case 'express order':
            $express_order_status = ' selected="selected"';
            break;
        case 'order form':
            $order_form_status = ' selected="selected"';
            break;
        case 'shopping cart':
            $shopping_cart_status = ' selected="selected"';
            break;
        case 'shipping address and arrival':
            $shipping_address_and_arrival_status = ' selected="selected"';
            break;
        case 'shipping method':
            $shipping_method_status = ' selected="selected"';
            break;
        case 'billing information':
            $billing_information_status = ' selected="selected"';
            break;
        case 'order preview':
            $order_preview_status = ' selected="selected"';
            break;
        case 'order receipt':
            $order_receipt_status = ' selected="selected"';
            break;
        case 'affiliate sign up form':
            $affiliate_sign_up_form_status = ' selected="selected"';
            break;
        case 'affiliate sign up confirmation':
            $affiliate_sign_up_confirmation_status = ' selected="selected"';
            break;
        case 'affiliate welcome':
            $affiliate_welcome_status = ' selected="selected"';
            break;
        case 'photo gallery':
            $photo_gallery_status = ' selected="selected"';
            break;
    }
    // output the standard page type option
    $output .= '<option value="standard">' . lang('Standard') . '</option>';
    $output_miscellaneous_options = '';
    // if user is above a basic user role, then prepare system page types
    if ($user['role'] < 3) {
        $output_miscellaneous_options .= '<option value="change password"' . $change_password_status . '>' . lang('Change Password') . '</option>' . '<option value="set password"' . $set_password_status . '>' . lang('Set Password') . '</option>';
    }
    // if user is at least a manager or if they are able to create this type of page, then output the page type option
    if (($user['role'] < 3) || ($user['set_page_type_email_a_friend'] == true)) {
        $output_miscellaneous_options .= '<option value="email a friend"' . $email_a_friend_status . '>' . lang('E-mail a Friend') . '</option>';
    }
    // if user is above a basic user role, then output error page type option.
    if ($user['role'] < 3) {
        $output_miscellaneous_options .= '<option value="error"' . $error_status . '>' . lang('Error') . '</option>';
    }
    // If user is at least a manager or if they are able to create this type of page, then output the page type option.
    if (($user['role'] < 3) || ($user['set_page_type_folder_view'] == true)) {
        $output_miscellaneous_options .= '<option value="folder view"' . $folder_view_status . '>' . lang('Folder View') . '</option>';
    }
    // If user is above a basic user role, then output more page type options.
    if ($user['role'] < 3) {
        $output_miscellaneous_options .= '<option value="forgot password"' . $forgot_password_status . '>' . lang('Forgot Password') . '</option>

            <option value="login"' . $login_status . '>' . lang('Login') . '</option>

            <option value="logout"' . $logout_status . '>' . lang('Logout') . '</option>';
    }
    // if user is at least a manager or if they are able to create this type of page, then output the page type option
    if (($user['role'] < 3) || ($user['set_page_type_photo_gallery'] == true)) {
        $output_miscellaneous_options .= '<option value="photo gallery"' . $photo_gallery_status . '>' . lang('Photo Gallery') . '</option>';
    }
    // if user is above a basic user role, then prepare system page types
    if ($user['role'] < 3) {
        $output_miscellaneous_options .= '<option value="search results"' . $search_results_status . '>' . lang('Search Results') . '</option>';
    }
    // if there are miscellaneous page types to output then output them
    if ($output_miscellaneous_options != '') {
        $output .= '<optgroup label="' . lang('Miscellaneous') . '">

                ' . $output_miscellaneous_options . '

            </optgroup>';
    }
    // if user is above a basic user role, then prepare system page types
    if ($user['role'] < 3) {
        $output .= '<optgroup label="' . lang('Registration') . '">

                <option value="registration entrance"' . $registration_entrance_status . '>' . lang('Registration Entrance') . '</option>

                <option value="registration confirmation"' . $registration_confirmation_status . '>' . lang('Registration Confirmation') . '</option>

            </optgroup>

            <optgroup label="' . lang('Membership') . '">

                <option value="membership entrance"' . $membership_entrance_status . '>' . lang('Membership Entrance') . '</option>

                <option value="membership confirmation"' . $membership_confirmation_status . '>' . lang('Membership Confirmation') . '</option>

            </optgroup>

            <optgroup label="' . lang('My Account') . '">

                <option value="my account"' . $my_account_status . '>' . lang('My Account') . '</option>

                <option value="my account profile"' . $my_account_profile_status . '>' . lang('My Account Profile') . '</option>

                <option value="email preferences"' . $email_preferences_status . '>' . lang('E-mail Preferences') . '</option>';
        // if e-commerce module is active, then prepare e-commerce page types
        if (ECOMMERCE == true) {
            $output .= '<option value="view order"' . $view_order_status . '>' . lang('View Order') . '</option>

                <option value="update address book"' . $update_address_book_status . '>' . lang('Update Address Book') . '</option>';
        }
        $output .= '</optgroup>';
    }
    // if forms module is active, then use forms page types
    if (FORMS == true) {
        $output_form_options = '';
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_custom_form'] == true)) {
            $output_form_options .= '<option value="custom form"' . $custom_form_status . '>' . lang('Custom Form') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_custom_form_confirmation'] == true)) {
            $output_form_options .= '<option value="custom form confirmation"' . $custom_form_confirmation_status . '>' . lang('Custom Form Confirmation') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_form_list_view'] == true)) {
            $output_form_options .= '<option value="form list view"' . $form_list_view_status . '>' . lang('Form List View') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_form_item_view'] == true)) {
            $output_form_options .= '<option value="form item view"' . $form_item_view_status . '>' . lang('Form Item View') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_form_view_directory'] == true)) {
            $output_form_options .= '<option value="form view directory"' . $form_view_directory_status . '>' . lang('Form View Directory') . '</option>';
        }
        // if there are form options, then output the group
        if ($output_form_options != '') {
            $output .= '<optgroup label="' . lang('Forms') . '">

                    ' . $output_form_options . '

                </optgroup>';
        }
    }
    // if calendars module is active and user has access to calendars, then use calendars page types
    if ((CALENDARS == true) && (($user['role'] < 3) || ($user['manage_calendars'] == true))) {
        $output_calendar_options = '';
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_calendar_view'] == true)) {
            $output_calendar_options .= '<option value="calendar view"' . $calendar_view_status . '>' . lang('Calendar View') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_calendar_event_view'] == true)) {
            $output_calendar_options .= '<option value="calendar event view"' . $calendar_event_view_status . '>' . lang('Calendar Event View') . '</option>';
        }
        // if there are calendar options to output, then output them
        if ($output_calendar_options != '') {
            $output .= '<optgroup label="' . lang('Calendars') . '">

                    ' . $output_calendar_options . '

                </optgroup>';
        }
    }
    // if e-commerce module is active, then use e-commerce page types
    if (ECOMMERCE == true) {
        $output_commerce_options = '';
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_catalog'] == true)) {
            $output_commerce_options .= '<option value="catalog"' . $catalog_status . '>' . lang('Catalog') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_catalog_detail'] == true)) {
            $output_commerce_options .= '<option value="catalog detail"' . $catalog_detail_status . '>' . lang('Catalog Detail') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_express_order'] == true)) {
            $output_commerce_options .= '<option value="express order"' . $express_order_status . '>' . lang('Express Order') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_order_form'] == true)) {
            $output_commerce_options .= '<option value="order form"' . $order_form_status . '>' . lang('Order Form') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_shopping_cart'] == true)) {
            $output_commerce_options .= '<option value="shopping cart"' . $shopping_cart_status . '>' . lang('Shopping Cart') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_shipping_address_and_arrival'] == true)) {
            $output_commerce_options .= '<option value="shipping address and arrival"' . $shipping_address_and_arrival_status . '>' . lang('Shipping Address & Arrival') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_shipping_method'] == true)) {
            $output_commerce_options .= '<option value="shipping method"' . $shipping_method_status . '>' . lang('Shipping Method') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_billing_information'] == true)) {
            $output_commerce_options .= '<option value="billing information"' . $billing_information_status . '>' . lang('Billing Information') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_order_preview'] == true)) {
            $output_commerce_options .= '<option value="order preview"' . $order_preview_status . '>' . lang('Order Preview') . '</option>';
        }
        // if user is at least a manager or if they are able to create this type of page, then output the page type option
        if (($user['role'] < 3) || ($user['set_page_type_order_receipt'] == true)) {
            $output_commerce_options .= '<option value="order receipt"' . $order_receipt_status . '>' . lang('Order Receipt') . '</option>';
        }
        // if there are ecommerce options to output, then output them
        if ($output_commerce_options != '') {
            $output .= '<optgroup label="' . lang('E-Commerce') . '">

                    ' . $output_commerce_options . '

                </optgroup>';
        }
    }
    // if affiliate program is active and user is above a basic user role, then use affiliate page types
    if ((AFFILIATE_PROGRAM == true) && ($user['role'] < 3)) {
        $output .= '<optgroup label="' . lang('Affiliate Program') . '">

                <option value="affiliate sign up form"' . $affiliate_sign_up_form_status . '>' . lang('Affiliate Sign Up Form') . '</option>

                <option value="affiliate sign up confirmation"' . $affiliate_sign_up_confirmation_status . '>' . lang('Affiliate Sign Up Confirmation') . '</option>

                <option value="affiliate welcome"' . $affiliate_welcome_status . '>' . lang('Affiliate Welcome') . '</option>

            </optgroup>';
    }
    return $output;
}
function get_product_group_options($product_group_id = 0, $parent_product_group_id = 0, $excluded_product_group_id = 0, $level = 0, $product_groups = array(), $include_select_product_groups = true, $format = 'text', $include_blank_option = false, $include_disabled = true)
{
    global $user;
    // If the format is text, then prepare output variable for that.
    if ($format == 'text') {
        $output = '';
        // Otherwise the format is array, so prepare output variable for that.
    } else {
        $output = array();
    }
    // If this is the first time this function has run, then get all product groups and put them in an array,
    // and determine if a blank option should be added.
    if (count($product_groups) == 0) {
        $sql_where = "";
        // If disabled product groups should not be included, then add SQL where
        // filter.
        if (!$include_disabled) {
            $sql_where = "WHERE enabled = '1'";
        }
        // get all product groups
        $query = "SELECT

                id,

                name,

                enabled,

                parent_id,

                display_type

            FROM product_groups

            $sql_where

            ORDER BY sort_order, name";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // add each product group to an array
        while ($row = mysqli_fetch_assoc($result)) {
            $product_groups[] = $row;
        }
        // If a blank option should be included, then include it.
        if ($include_blank_option == true) {
            // If the format is text, then get prepare blank option in a certain way.
            if ($format == 'text') {
                $output .= '<option value=""></option>';
                // Otherwise the format is array, so prepare blank option a different way.
            } else {
                $output[] = array(
                    'label' => '',
                    'value' => ''
                );
            }
        }
    }
    $child_product_groups = array();
    // loop through product groups array in order to get all product groups that are in parent product group
    foreach ($product_groups as $product_group) {
        // if the parent product group id for this product group is equal to the parent product group id,
        // and this product group is a browse product group or select product groups should be included
        // then this is a child product group and it should be added, so add to array
        if (($product_group['parent_id'] == $parent_product_group_id) && (($product_group['display_type'] == 'browse') || ($include_select_product_groups == true))) {
            $child_product_groups[] = $product_group;
        }
    }
    // loop through child product groups
    foreach ($child_product_groups as $child_product_group) {
        // if product group id is not equal to excluded product group id, then continue to prepare option and get child product groups
        if ($child_product_group['id'] != $excluded_product_group_id) {
            // prepare indentation
            $indentation = '';
            for ($i = 1; $i <= $level; $i++) {
                $indentation .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            }
            $next_level = $level + 1;
            $disabled = '';
            // If this product group is disabled, then output "[DISABLED]" on the
            // end of the label.
            if (!$child_product_group['enabled']) {
                $disabled = ' [' . lang('DISABLED') . ']';
            }
            // If the format is text, then prepare output for that format.
            if ($format == 'text') {
                // if this product group is the selected product group, then this option should be selected
                if ($child_product_group['id'] == $product_group_id) {
                    $selected = ' selected';
                } else {
                    $selected = '';
                }
                $output .= '<option value="' . $child_product_group['id'] . '"' . $selected . '>' . $indentation . h($child_product_group['name']) . $disabled . '</option>';
                // Otherwise the format is array, so prepare output for that format.
            } else {
                $output[] = array(
                    'label' => $indentation . h($child_product_group['name']) . $disabled,
                    'value' => $child_product_group['id']
                );
            }
            // if this product group is a browse product group, then get child product groups
            if ($child_product_group['display_type'] == 'browse') {
                // If the format is text, then get child product groups in a certain way.
                if ($format == 'text') {
                    // get options for child product groups
                    $output .= get_product_group_options($product_group_id, $child_product_group['id'], $excluded_product_group_id, $next_level, $product_groups, $include_select_product_groups, $format, $include_blank_option, $include_disabled);
                    // Otherwise the format is array, so get child product groups in a different way.
                } else {
                    $output = array_merge($output, get_product_group_options($product_group_id, $child_product_group['id'], $excluded_product_group_id, $next_level, $product_groups, $include_select_product_groups, $format, $include_blank_option, $include_disabled));
                }
            }
        }
    }
    return $output;
}
// get an array of ad regions for pick lists
function get_ad_region_options()
{
    global $user;
    // Setup first option
    $ad_region_options[''] = '';
    $sql_join = '';
    $where = '';
    // if user is a user role, then prepare sql join and where
    if ($user['role'] == 3) {
        $sql_join = 'LEFT JOIN users_ad_regions_xref ON ad_regions.id = users_ad_regions_xref.ad_region_id';
        $where = "WHERE users_ad_regions_xref.user_id = '" . escape($user['id']) . "'";
    }
    // get ad regions
    $query = "SELECT
            id,
            name
        FROM ad_regions
        $sql_join
        $where
        ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // loop through each ad region and add it to array
    while ($row = mysqli_fetch_assoc($result)) {
        $ad_region_options[h($row['name'])] = $row['id'];
    }
    return $ad_region_options;
}
// get an array of states for pick lists
function get_state_options()
{
    // check if foreign states exist
    // foreign states are states that belong to a country that is not the default country
    $query = "SELECT COUNT(*)

        FROM states

        LEFT JOIN countries ON states.country_id = countries.id

        WHERE countries.default_selected = '0'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_row($result);
    $foreign_states = false;
    // if foreign states exist, then remember that
    if ($row[0] > 0) {
        $foreign_states = true;
    }
    // get states
    $query = "SELECT
            states.id,
            states.name,
            countries.name as country_name
        FROM states
        LEFT JOIN countries ON states.country_id = countries.id
        ORDER BY
            countries.name ASC,
            states.name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $states = array();
    // loop through each state and add it to array
    while ($row = mysqli_fetch_assoc($result)) {
        $states[] = $row;
    }
    $state_options = array();
    $state_options[''] = '';
    // loop through each state in order to prepare options
    foreach ($states as $state) {
        // if foreign states exist, then output state label with country name prefix in order to prevent confusion
        if ($foreign_states == true) {
            $output_state_label = h($state['country_name']) . ': ' . h($state['name']);
            // else foreign states do not exist, so just use state name for label
        } else {
            $output_state_label = h($state['name']);
        }
        $state_options[$output_state_label] = $state['id'];
    }
    return $state_options;
}


function get_file_options($design = FALSE)
{
    $folders_that_user_has_access_to = array();
    global $user;

    // if user is a basic user, then get folders that user has access to
    if ($user['role'] == 3) {
        $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
    }
    $sql_design = '';
    // if design files should not be included, then include filter
    if ($design == FALSE) {
        $sql_design = "AND (design = 0)";
    }

    // get image list
    $query =
        "SELECT
            id,
            name,
            folder
        FROM files
            WHERE name != ''
            $sql_design
            AND (attachment = 0)
        ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $file_options = array();
    // Setup first option
    $file_options['-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('File')))) . '-'] = '';




    // loop through each image and create an <option> tag
    while ($row = mysqli_fetch_assoc($result)) {
        // if the user has access to this folder
        if (check_folder_access_in_array($row['folder'], $folders_that_user_has_access_to) == true) {
            $file_options[h($row['name'])] = $row['id'];
        }
    }
    return $file_options;
}


function select_image_options($image_name = '', $design = false)
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    $folders_that_user_has_access_to = array();
    global $user;
    // if user is a basic user, then get folders that user has access to
    if ($user['role'] == 3) {
        $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
    }
    $sql_design = '';
    // if design files should not be included, then include filter
    if ($design == false) {
        $sql_design = "AND (design = 0)";
    }
    // get image list
    $query = "SELECT
                id,
                name,
                size,
                folder,
                timestamp,
                type
            FROM files
            WHERE
                (
                    (type = 'gif')
                    || (type = 'jpg')
                    || (type = 'jpeg')
                    || (type = 'png')
                    || (type = 'bmp')
                    || (type = 'tif')
                    || (type = 'tiff')
                    || (type = 'webp')
                )
                $sql_design
                AND (attachment = 0)
            ORDER BY timestamp DESC,
            name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        // if the user has access to this folder
        if (($row['name'] == $image_name) || (check_folder_access_in_array($row['folder'], $folders_that_user_has_access_to) == true)) {
            $selected_class = " ";
            $control_button = "";
            if (is_array($image_name)) {
                // loop through each image and create an <option> tag
                $selected_class = ' selected';
                $control_button = '<button type="button" onclick="add_this_image(\'' . $row['name'] . '\',\'' . $row['id'] . '\');return false;" data-bs-dismiss="modal"  class="m-1 btn-data-control btn btn-outline-primary border-2 "  title="' . lang('Select') . '" ><i class="material-icons">done</i></button>';
            } else {
                $control_button = '<button type="button" onclick="add_this_image(\'' . $row['name'] . '\',\'' . $row['id'] . '\');return false;" data-bs-dismiss="modal"  class="m-1 btn-data-control btn btn-outline-primary border-2 "  title="' . lang('Select') . '" ><i class="material-icons">done</i></button>';
            }
            $output .= '
            <tr file-id="' . $row['id'] . '" class="image_selector_item ' . $selected_class . '">
                <td class="align-middle text-start action-buttons">
                    ' . $control_button . '
                </td>
                <td class="align-middle"><img class="card-img-top delayed" src=" ' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . h($row['name']) . '" ></td>
                <td class="image_name align-middle">' . h($row['name']) . '</td>    
                <td class="align-middle">' . h(convert_bytes_to_string($row['size'])) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $row['timestamp'])) . ' </td>
            </tr>';
        }
    }

    return $output;
}


// outputs the form's <select> list for selecting a recurring payment period
function select_payment_period($payment_period = '')
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    $payment_periods[] = '';
    $payment_periods[] = 'Monthly';
    $payment_periods[] = 'Weekly';
    $payment_periods[] = 'Every Two Weeks';
    $payment_periods[] = 'Twice every Month';
    $payment_periods[] = 'Every Four Weeks';
    $payment_periods[] = 'Quarterly';
    $payment_periods[] = 'Twice every Year';
    $payment_periods[] = 'Yearly';
    foreach ($payment_periods as $value) {
        if ($value == $payment_period) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $label = '';
        switch ($value) {
            case 'Monthly':
                $label = lang('Monthly');
                break;
            case 'Weekly':
                $label = lang('Weekly');
                break;
            case 'Every Two Weeks':
                $label = lang('Every Two Weeks');
                break;
            case 'Twice every Month':
                $label = lang('Twice every Month');
                break;
            case 'Every Four Weeks':
                $label = lang('Every Four Weeks');
                break;
            case 'Quarterly':
                $label = lang('Quarterly');
                break;
            case 'Twice every Year':
                $label = lang('Twice every Year');
                break;
            case 'Yearly':
                $label = lang('Yearly');
                break;
        }
        $output .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
    }
    return $output;
}
function get_payment_period_options()
{
    $payment_period_options = array();
    $payment_period_options[''] = '';
    $payment_period_options[lang('Monthly')] = 'Monthly';
    $payment_period_options[lang('Weekly')] = 'Weekly';
    $payment_period_options[lang('Every Two Weeks')] = 'Every Two Weeks';
    $payment_period_options[lang('Twice every Month')] = 'Twice every Month';
    $payment_period_options[lang('Every Four Weeks')] = 'Every Four Weeks';
    $payment_period_options[lang('Quarterly')] = 'Quarterly';
    $payment_period_options[lang('Twice every Year')] = 'Twice every Year';
    $payment_period_options[lang('Yearly')] = 'Yearly';
    return $payment_period_options;
}
// outputs the form's <select> list for selecting a selection type for a product
function select_selection_type($selected_selection_type = '')
{
    $selection_types = array();
    $selection_types[] = array(
        'value' => 'checkbox',
        'name' => lang('Checkbox')
    );
    $selection_types[] = array(
        'value' => 'quantity',
        'name' => lang('Quantity')
    );
    $selection_types[] = array(
        'value' => 'donation',
        'name' => lang('Donation')
    );
    $selection_types[] = array(
        'value' => 'autoselect',
        'name' => lang('Auto-Select')
    );
    $output = '';
    foreach ($selection_types as $selection_type) {
        // if this selection type is the selected selection type then select this option
        if ($selection_type['value'] == $selected_selection_type) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $selection_type['value'] . '"' . $selected . '>' . $selection_type['name'] . '</option>';
    }
    return $output;
}
function get_registration_entrance_screen()
{
    // find if there is a registration entrance page
    $query = "SELECT page_id FROM page WHERE page_type = 'registration entrance'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is a registration entrance page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a registration entrance page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_registration_entrance.php');
        $output = output_header_secure() . get_registration_entrance() . output_footer_secure();
    }
    return $output;
}
function get_membership_entrance_screen()
{
    // find if there is a membership entrance page
    $query = "SELECT page_id FROM page WHERE page_type = 'membership entrance'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is a membership entrance page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a membership entrance page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_membership_entrance.php');
        $output = output_header_secure() . get_membership_entrance() . output_footer_secure();
    }
    return $output;
}
function get_registration_confirmation_screen()
{
    // find if there is a registration confirmation page
    $query = "SELECT page_id FROM page WHERE page_type = 'registration confirmation'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is a registration confirmation page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a registration confirmation page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_registration_confirmation_screen_content.php');
        $output = output_header_secure() . get_registration_confirmation_screen_content() . output_footer_secure();
    }
    return $output;
}
function get_membership_confirmation_screen()
{
    // find if there is a membership confirmation page
    $query = "SELECT page_id FROM page WHERE page_type = 'membership confirmation'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is a membership confirmation page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a membership confirmation page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_membership_confirmation_screen_content.php');
        $output = output_header_secure() . get_membership_confirmation_screen_content() . output_footer_secure();
    }
    return $output;
}
function get_my_account_profile_screen()
{
    // find if there is a my account profile page
    $query = "SELECT page_id FROM page WHERE page_type = 'my account profile'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is a my account profile page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a my account profile page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_my_account_profile.php');
        $output = output_header_secure() . get_my_account_profile() . output_footer_secure();
    }
    return $output;
}
function get_email_preferences_screen()
{
    // find if there is an e-mail preferences page
    $query = "SELECT page_id FROM page WHERE page_type = 'email preferences'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is an e-mail preferences page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not an e-mail preferences page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_email_preferences.php');
        $output = output_header_secure() . get_email_preferences() . output_footer_secure();
    }
    return $output;
}
function get_view_order_screen()
{
    // find if there is a view order page
    $query = "SELECT page_id FROM page WHERE page_type = 'view order'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there is a view order page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a view order page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_view_order_screen_content.php');
        $output = output_header_secure() . get_view_order_screen_content(array()) . output_footer_secure();
    }
    return $output;
}
function get_update_address_book_screen()
{
    // find if there is an update address book page
    $query = "SELECT page_id FROM page WHERE page_type = 'update address book'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there is an update address book page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not an update address book page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_update_address_book.php');
        $output = output_header_secure() . get_update_address_book() . output_footer_secure();
    }
    return $output;
}
function get_error_screen($content)
{
    // if the connection to the database was successful
    if (defined('DB_CONNECTED') and DB_CONNECTED == true) {
        // find if there is an error page
        $query = "SELECT page_id FROM page WHERE page_type = 'error'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
        // if there is an error page
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
            // get page content
            $output = get_page_content($row['page_id'], $content, $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
            return $output;
        }
    }
    $output = output_header_secure() . $content . output_footer_secure();
    return $output;
}
function get_forgot_password_screen()
{
    // find if there is a forgot password page
    $query = "SELECT page_id FROM page WHERE page_type = 'forgot password'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is a forgot password page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $content, $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a forgot password page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_forgot_password.php');
        $output = output_header_secure() . get_forgot_password() . output_footer_secure();
    }
    return $output;
}
function get_login_screen()
{
    // find if there is a login page
    $query = "SELECT page_id FROM page WHERE page_type = 'login'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is a login page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a login page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_login.php');
        $output = output_header_secure() . get_login() . output_footer_secure();
    }
    return $output;
}
function get_logout_screen()
{
    // find if there is a logout page
    $query = "SELECT page_id FROM page WHERE page_type = 'logout'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is a logout page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not a login page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_logout_screen_content.php');
        $output = output_header_secure() . get_logout_screen_content() . output_footer_secure();
    }
    return $output;
}
function get_affiliate_sign_up_form_screen()
{
    // find if there is an affiliate sign up form page
    $query = "SELECT page_id FROM page WHERE page_type = 'affiliate sign up form'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is an affiliate sign up form page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not an affiliate sign up form page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_affiliate_sign_up_form_screen_content.php');
        $output = output_header_secure() . get_affiliate_sign_up_form_screen_content($properties) . output_footer_secure();
    }
    return $output;
}
function get_affiliate_sign_up_confirmation_screen()
{
    // check if there is a affiliate sign up confirmation page
    $query = "SELECT page_id FROM page WHERE page_type = 'affiliate sign up confirmation'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    // if there is an affiliate sign up confirmation page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, $dynamic_properties = array(), $toolbar = false, ($_SESSION['software']['device_type'] ?? ''));
        // else there is not an affiliate sign up confirmation page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_affiliate_sign_up_confirmation_screen_content.php');
        $output = output_header_secure() . get_affiliate_sign_up_confirmation_screen_content() . output_footer_secure();
    }
    return $output;
}
function get_affiliate_welcome_screen()
{
    // find if there is an affiliate welcome page
    $query = "SELECT page_id FROM page WHERE page_type = 'affiliate welcome'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there is an affiliate welcome page
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');
        // get page content
        $output = get_page_content($row['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true);
        // else there is not an affiliate welcome page, so use default screen
    } else {
        require_once(PG_FUNCTIONS_DIR . '/get_affiliate_welcome_screen_content.php');
        $output = output_header_secure() . get_affiliate_welcome_screen_content() . output_footer_secure();
    }
    return $output;
}
function log_activity($description, $user = '')
{
    // If a username was not passed, and a user is logged in,
    // then use that user's username.
    if (($user == '') && defined('USER_USERNAME')) {
        $user = USER_USERNAME;
    }
    // No remote address when the software runs from the command line, which is
    // how the scheduled jobs reach this function.
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    $query = "INSERT INTO log (log_id, log_description, log_ip, log_user, log_timestamp) " . "VALUES ('', '" . escape($description) . "', '" . escape($ip) . "', '" . escape($user) . "', UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // get a random number between 1 and 100 in order to determine if we should delete old log entries
    // there is a 1 in 100 chance that we will delete old log entries each time a log entry is added
    $random_number = rand(1, 100);
    // if the random number is 1, then delete old log entries
    // all log entries before 6 months ago are deleted
    if ($random_number == 1) {
        $query = "DELETE FROM log WHERE log_timestamp < (UNIX_TIMESTAMP() - 15552000)";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    }
}
function get_number_of_contacts($contact_group, $require_email = false)
{
    // if contact group is [None]
    if ($contact_group == '[' . lang('None') . ']') {
        $where = "WHERE (contacts_contact_groups_xref.contact_group_id IS NULL)";
        // else contact group is not [None]
    } else {
        $where = "WHERE (contacts_contact_groups_xref.contact_group_id = '" . escape($contact_group) . "')";
    }
    if ($require_email == true) {
        $where .= " AND (opt_in != 0)

            AND (email_address != '')";
    }
    // get number of contacts in group
    $query = "SELECT count(contacts.id)

        FROM contacts

        LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id

        $where";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_row($result);
    return $row[0];
}
function validate_date($date)
{
    // if format of date is valid
    if (preg_match('/(\d{1,2})[-,\/](\d{1,2})[-,\/](\d{4})/', $date, $date_parts) == 1) {
        $year = $date_parts[3];
        if (DATE_FORMAT == 'month_day') {
            $month = $date_parts[1];
            $day = $date_parts[2];
        } else {
            $month = $date_parts[2];
            $day = $date_parts[1];
        }
        // if date is valid (i.e. does the day exist for the month)
        if (checkdate($month, $day, $year) == true) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
function validate_date_and_time($date_and_time)
{
    $date_and_time_parts = explode(' ', $date_and_time, 2);
    $date = $date_and_time_parts[0];
    $time = $date_and_time_parts[1];
    if ((validate_date($date) == true) && (validate_time($time) == true)) {
        return true;
    } else {
        return false;
    }
}
function validate_email_address($email_address)
{
    if (preg_match("/^([-!#\$%&'*+.\/0-9=?A-Z^_`a-z{|}~])+@([-!#\$%&'*+\/0-9=?A-Z^_`a-z{|}~]+\\.)+[a-zA-Z]{2,6}\$/i", $email_address) == 1) {
        return true;
    } else {
        return false;
    }
}
function validate_time($time)
{
    // if format of time is not valid
    if (preg_match('/(\d{1,2})(:\d{1,2}) (AM|PM)/i', $time, $time_parts) == 0) {
        return false;
    }
    $hour = $time_parts[1];
    $minute = str_replace(':', '', $time_parts[2]);
    $second = $time_parts[4];
    // if format of time is not valid
    if (($hour == 0) || ($hour > 12) || ($minute > 59) || ($second > 59)) {
        return false;
    } else {
        return true;
    }
}
function prepare_page_for_email($html)
{
    // find if there is a base tag in the HTML
    $base_in_html = preg_match('/<\s*base\s+[^>]*href\s*=\s*["\'](?:http:\/\/|https:\/\/|ftp:\/\/).*?["\']/is', $html);
    // if there is not a base tag in the HTML, add base tag and convert relative links to absolute links
    if (!$base_in_html) {
        $base = '<head>' . "\n" . '<base href="' . URL_SCHEME . HOSTNAME_SETTING . '/" />';
        $html = preg_replace('/<head>/i', $base, $html);
        // change relative URLs to absolute URLs for links
        $html = preg_replace('/(<\s*a\s+[^>]*href\s*=\s*["\'])(?!ftp:\/\/|https:\/\/|mailto:|http:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $html);
        // change relative URLs to absolute URLs for images
        $html = preg_replace('/(<\s*img\s+[^>]*src\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $html);
        // change relative URLs to absolute URLs for CSS background images
        $html = preg_replace('/(background-image\s*:\s*url\s*\(\s*(?:"|\'|))(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?(?:"|\'|).*?\))/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $html);
        // change relative URLs to absolute URLs for HTML background images
        $html = preg_replace('/(background\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'])/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $html);
    }
    // wrap long lines (RFC 821)
    $html = wordwrap($html, 900, "\n", 1);
    return $html;
}
// outputs the form's <select> product list for selecting a product
function select_product($selected_value = '', $value_type = 'id')
{
    $query = "SELECT
            id,
            name,
            enabled,
            short_description,
            price
        FROM products
        ORDER BY name";

    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $output = '';

    while ($row = mysqli_fetch_assoc($result)) {
        // value_type can be 'id' or 'name'
        $value = ($value_type === 'name') ? $row['name'] : $row['id'];

        // Selected check.
        if ($value == $selected_value) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }

        $output_disabled_label = '';
        // Append a label when the product is disabled.
        if ($row['enabled'] == 0) {
            $output_disabled_label = ' [' . lang('DISABLED') . ']';
        }

        $output .= '<option value="' . h($value) . '"' . $selected . '>'
            . h($row['name']) . ' - ' . h($row['short_description'])
            . ' (' . prepare_amount($row['price'] / 100) . ')'
            . $output_disabled_label
            . '</option>';
    }

    return $output;
}



// get an array of products for pick lists
function get_product_options($include_blank_option = true)
{
    $product_options = array();
    if ($include_blank_option) {
        $product_options['-' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('product')))) . '-'] = '';
    }
    // get all products
    $query = "SELECT
            id,
            name,
            enabled,
            short_description,
            price
        FROM products
        ORDER BY name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // loop through each product in order to add it to array
    while ($row = mysqli_fetch_assoc($result)) {
        $output_disabled_label = '';
        // If this product is disabled, then output "disabled" next to the name and short description.
        if ($row['enabled'] == 0) {
            $output_disabled_label = ' [' . lang('DISABLED') . ']';
        }
        $label = h($row['name']) . ' - ' . h($row['short_description']) . ' (' . prepare_amount($row['price'] / 100) . ')' . $output_disabled_label;
        $product_options[$label] = $row['id'];
    }
    return $product_options;
}
function get_recipient_options()
{
    $recipient_options = array();
    $recipient_options[''] = '';
    // The array is label => value; both sides are the translated name, so the
    // list does not show the English word and does not offer the buyer twice
    // once the name is in the session as well.
    $recipient_options[lang('myself')] = lang('myself');

    // if it has not been done already, add recipients to session
    initialize_recipients();
    // if there is at least one recipient stored in session
    if (isset($_SESSION['ecommerce']['recipients']) == true) {
        // loop through all recipients to build recipient options
        foreach ($_SESSION['ecommerce']['recipients'] as $recipient) {
            $value = h($recipient);
            $recipient_options[$value] = $value;
        }
    }
    $recipient_options[lang('- add name below -')] = lang('- add name below -');

    return $recipient_options;
}
function select_country($country_id = '')
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    $query = "SELECT id, name, default_selected " . "FROM countries " . "ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        // if (this country is the selected country OR (this is the default selected country AND there is no selected country)), select this country by default
        if (($row['id'] == $country_id) || (($row['default_selected'] == 1) && ($country_id == ''))) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $row['id'] . '"' . $selected . '>' . h($row['name']) . '</option>';
    }
    return $output;
}
// outputs the options for a drop-down selection field for selecting a referral source
function select_referral_source($referral_source_code = '')
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    $query = "SELECT name, code FROM referral_sources ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        if (($referral_source_code) && ($row['code'] == $referral_source_code)) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $row['code'] . '"' . $selected . '>' . h($row['name']) . '</option>';
    }
    return $output;
}
// output options for drop-down selection for selecting an offer rule
function select_offer_rule($offer_rule_id = '')
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    // get offer rule
    $query = "SELECT id, name FROM offer_rules ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['id'] == $offer_rule_id) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $row['id'] . '"' . $selected . '>' . $row['name'] . '</option>';
    }
    return $output;
}
// output options for drop-down selection for selecting an offer action type
function select_offer_action_type($offer_action_type = '')
{
    // Only the case matching the current type sets its own variable below, but all of them are
    // read when the option list is built, so they all have to start out empty.
    $order_discount_status = '';
    $product_discount_status = '';
    $add_product_status = '';
    $shipping_discount_status = '';

    switch ($offer_action_type) {
        case 'discount order':
            $order_discount_status = ' selected="selected"';
            break;
        case 'discount product':
            $product_discount_status = ' selected="selected"';
            break;
        case 'add product':
            $add_product_status = ' selected="selected"';
            break;
        case 'discount shipping':
            $shipping_discount_status = ' selected="selected"';
            break;
    }
    $output_discount_shipping_option = '';
    // if shipping is on, then prepare to output discount shipping option
    if (ECOMMERCE_SHIPPING == true) {
        $output_discount_shipping_option = '<option value="discount shipping"' . $shipping_discount_status . '>' . lang('Discount Shipping') . '</option>';
    }
    return '<option value="discount order"' . $order_discount_status . '>' . lang('Discount Order') . '</option>

        <option value="discount product"' . $product_discount_status . '>' . lang('Discount Product') . '</option>

        <option value="add product"' . $add_product_status . '>' . lang('Add Product') . '</option>

        ' . $output_discount_shipping_option;
}
// output options for drop-down selection for selecting a field type
function select_field_type($field_type = '', $form_type = 'custom')
{
    $signature_status = '';

    switch ($field_type) {
        case 'text box':
            $text_box_status = ' selected="selected"';
            break;
        case 'text area':
            $text_area_status = ' selected="selected"';
            break;
        case 'pick list':
            $pick_list_status = ' selected="selected"';
            break;
        case 'radio button':
            $radio_button_status = ' selected="selected"';
            break;
        case 'check box':
            $check_box_status = ' selected="selected"';
            break;
        case 'file upload':
            $file_upload_status = ' selected="selected"';
            break;
        case 'date':
            $date_status = ' selected="selected"';
            break;
        case 'date and time':
            $date_and_time_status = ' selected="selected"';
            break;
        case 'email address':
            $email_address_status = ' selected="selected"';
            break;
        case 'information':
            $information_status = ' selected="selected"';
            break;
        case 'time':
            $time_status = ' selected="selected"';
            break;
        case 'signature':
            $signature_status = ' selected="selected"';
            break;
    }
    $output_file_upload_field_option = '';
    // if this is a custom form, then output file upload field type option
    if ($form_type == 'custom') {
        $output_file_upload_field_option = '<option value="file upload"' . $file_upload_status . '>' . lang('File Upload') . '</option>';
    }
    // Custom forms only, and only where the database can store the value. A
    // screen that offers a type the column does not have would save a text box
    // and say nothing, and the person would find that out after someone signed.
    $output_signature_field_option = '';

    if (($form_type == 'custom') && function_exists('pg_signature_ready') && pg_signature_ready()) {
        $output_signature_field_option = '<option value="signature"' . $signature_status . '>' . lang('Signature') . '</option>';
    }
    $output = '
        <optgroup label="' . lang('Standard') . '">
            <option value="text box"' . $text_box_status . '>' . lang('Text Box') . '</option>
            <option value="text area"' . $text_area_status . '>' . lang('Text Area') . '</option>
            <option value="pick list"' . $pick_list_status . '>' . lang('Pick List') . '</option>
            <option value="radio button"' . $radio_button_status . '>' . lang('Radio Button') . '</option>
            <option value="check box"' . $check_box_status . '>' . lang('Check Box') . '</option>
            ' . $output_file_upload_field_option . '
        </optgroup>
        <optgroup label="' . lang('Special') . '">
            <option value="date"' . $date_status . '>' . lang('Date') . '</option>
            <option value="date and time"' . $date_and_time_status . '>' . lang('Date & Time') . '</option>
            <option value="email address"' . $email_address_status . '>' . lang('E-mail Address') . '</option>
            <option value="information"' . $information_status . '>' . lang('Information') . '</option>
            <option value="time"' . $time_status . '>' . lang('Time') . '</option>
            ' . $output_signature_field_option . '
        </optgroup>';
    return $output;
}
// output options for drop-down selection for selecting a field's position
function select_field_position($position, $field_id, $page_or_product_id, $page_type, $form_type)
{
    if ($form_type == 'product') {
        $form_fields_identifier_column = 'product_id';
    } elseif ($form_type == 'product_group') {
        // 2026.4: a variant set's form template.
        $form_fields_identifier_column = 'product_group_id';
    } else {
        $form_fields_identifier_column = 'page_id';
    }
    // Prepare sql filter in order to get correct fields
    $form_type_filter = "form_fields." . $form_fields_identifier_column . " = '" . e($page_or_product_id) . "'";
    // If the page type is express order then we need to add an extra filter for the form type
    if ($page_type == 'express order') {
        $form_type_filter .= " AND form_fields.form_type = '" . e($form_type) . "'";
    }
    // Pinning form_type is mandatory for a template, not optional as it is
    // elsewhere: the copies generated from a template carry the same
    // product_group_id, so filtering on that column alone returns the template
    // AND every product's copy of it.
    if ($form_type == 'product_group') {
        $form_type_filter .= " AND form_fields.form_type = 'product_group'";
    }
    if ($position == 'top') {
        $top_selected = ' selected="selected"';
    } else {
        $top_selected = '';
    }
    $output = '<option value="top"' . $top_selected . '>' . lang('Top') . '</option>';
    // get all fields
    $query = "SELECT
                id,
                name
             FROM form_fields
             WHERE $form_type_filter
             ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['id'] != $field_id) {
            if ($row['id'] == $position) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }
            $output .= '<option value="' . $row['id'] . '"' . $selected . '>' . lang(array('string' => 'Below {var:1}', 'vars' => h($row['name']))) . '</option>';
        }
    }
    return $output;
}
// output options for drop-down selection for selecting a contact field
function select_contact_field($selected_contact_field = '')
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    $contact_fields = array();
    $contact_fields[] = array(
        'value' => 'salutation',
        'name' => lang('Salutation')
    );
    $contact_fields[] = array(
        'value' => 'first_name',
        'name' => lang('First Name')
    );
    $contact_fields[] = array(
        'value' => 'last_name',
        'name' => lang('Last Name')
    );
    $contact_fields[] = array(
        'value' => 'suffix',
        'name' => lang('Suffix')
    );
    $contact_fields[] = array(
        'value' => 'nickname',
        'name' => lang('Nickname')
    );
    $contact_fields[] = array(
        'value' => 'image',
        'name' => lang('Image')
    );
    $contact_fields[] = array(
        'value' => 'company',
        'name' => lang('Company')
    );
    $contact_fields[] = array(
        'value' => 'title',
        'name' => lang('Title')
    );
    $contact_fields[] = array(
        'value' => 'department',
        'name' => lang('Department')
    );
    $contact_fields[] = array(
        'value' => 'office_location',
        'name' => lang('Office Location')
    );
    $contact_fields[] = array(
        'value' => 'business_address_1',
        'name' => lang('Business Address') . ' 1'
    );
    $contact_fields[] = array(
        'value' => 'business_address_2',
        'name' => lang('Business Address') . ' 2'
    );
    $contact_fields[] = array(
        'value' => 'business_city',
        'name' => lang('Business City')
    );
    $contact_fields[] = array(
        'value' => 'business_state',
        'name' => lang('Business State')
    );
    $contact_fields[] = array(
        'value' => 'business_zip_code',
        'name' => lang('Business Zip Code')
    );
    $contact_fields[] = array(
        'value' => 'business_country',
        'name' => lang('Business Country')
    );
    $contact_fields[] = array(
        'value' => 'business_phone',
        'name' => lang('Business Phone')
    );
    $contact_fields[] = array(
        'value' => 'business_fax',
        'name' => lang('Business Fax')
    );
    $contact_fields[] = array(
        'value' => 'home_address_1',
        'name' => lang('Home Address') . ' 1'
    );
    $contact_fields[] = array(
        'value' => 'home_address_2',
        'name' => lang('Home Address') . ' 2'
    );
    $contact_fields[] = array(
        'value' => 'home_city',
        'name' => lang('Home City')
    );
    $contact_fields[] = array(
        'value' => 'home_state',
        'name' => lang('Home State')
    );
    $contact_fields[] = array(
        'value' => 'home_zip_code',
        'name' => lang('Home Zip Code')
    );
    $contact_fields[] = array(
        'value' => 'home_country',
        'name' => lang('Home Country')
    );
    $contact_fields[] = array(
        'value' => 'home_phone',
        'name' => lang('Home Phone')
    );
    $contact_fields[] = array(
        'value' => 'home_fax',
        'name' => lang('Home Fax')
    );
    $contact_fields[] = array(
        'value' => 'mobile_phone',
        'name' => lang('Mobile Phone')
    );
    $contact_fields[] = array(
        'value' => 'email_address',
        'name' => lang('E-mail Address')
    );
    $contact_fields[] = array(
        'value' => 'website',
        'name' => lang('Website')
    );
    $contact_fields[] = array(
        'value' => 'lead_source',
        'name' => lang('Lead Source')
    );
    $contact_fields[] = array(
        'value' => 'opt_in',
        'name' => lang('Opt-In')
    );
    $contact_fields[] = array(
        'value' => 'description',
        'name' => lang('Description')
    );
    $contact_fields[] = array(
        'value' => 'affiliate_name',
        'name' => lang('Affiliate Name')
    );
    foreach ($contact_fields as $contact_field) {
        // if this contact field is the selected contact field, select this option
        if ($contact_field['value'] == $selected_contact_field) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $contact_field['value'] . '"' . $selected . '>' . $contact_field['name'] . '</option>';
    }
    return $output;
}
// Gets the number of users who have access to the control panel to edit something.
// The $exception_user_id is used by the edit user screen to ignore the current
// user that is being edited when figuring out the number of edit users.
function get_number_of_edit_users($exception_user_id = 0)
{
    // Get all editors that we can figure out by just looking at the user table.
    $sql_calendars = "";
    if (CALENDARS) {
        $sql_calendars = "OR (user_manage_calendars = 'yes')";
    }
    $sql_ecommerce = "";
    if (ECOMMERCE) {
        $sql_offline_payment = "";
        if (ECOMMERCE_OFFLINE_PAYMENT) {
            $sql_offline_payment = "OR (user_set_offline_payment = '1')";
        }
        $sql_ecommerce = "OR (user_manage_ecommerce = 'yes')
            OR (manage_ecommerce_reports = '1')
            $sql_offline_payment";
    }
    $general_editors = db_values("SELECT user_id FROM user
        WHERE
            (user_role < 3)
            $sql_calendars
            OR (user_manage_visitors = 'yes')
            OR (user_manage_contacts = 'yes')
            OR (user_manage_emails = 'yes')
            $sql_ecommerce");
    // Get other types of editors that we need to check other tables for.
    $folder_editors = db_values("SELECT DISTINCT(aclfolder_user) FROM aclfolder

        LEFT JOIN user ON aclfolder.aclfolder_user = user.user_id

        WHERE (aclfolder.aclfolder_rights = '2') AND (user.user_role = '3')");
    $common_region_editors = db_values("SELECT DISTINCT(users_common_regions_xref.user_id)
        FROM users_common_regions_xref
        LEFT JOIN user ON users_common_regions_xref.user_id = user.user_id
        WHERE user.user_role = '3'");
    $menu_editors = db_values("SELECT DISTINCT(users_menus_xref.user_id)
        FROM users_menus_xref
        LEFT JOIN user ON users_menus_xref.user_id = user.user_id
        WHERE user.user_role = '3'");
    $ad_region_editors = array();
    if (ADS) {
        $ad_region_editors = db_values("SELECT DISTINCT(users_ad_regions_xref.user_id)
            FROM users_ad_regions_xref
            LEFT JOIN user ON users_ad_regions_xref.user_id = user.user_id
            WHERE user.user_role = '3'");
    }
    // Combine all the different types of editors into one array so we can remove duplicates and count.
    $editors = array_merge($general_editors, $folder_editors, $common_region_editors, $menu_editors, $ad_region_editors);
    // Remove duplicate users from the array, so we can get an accurate unique count.
    $editors = array_unique($editors);
    // If there is an exception user (i.e. user being edited), then remove it from the array,
    // because we don't want to count it.
    if ($exception_user_id) {
        if (($key = array_search($exception_user_id, $editors)) !== false) {
            unset($editors[$key]);
        }
    }
    // Return the total number of editors.
    return count($editors);
}
function logout()
{
    $username = isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '';
    $logged_in_as_different_user = isset($_SESSION['software']['logged_in_as_different_user'])
        ? $_SESSION['software']['logged_in_as_different_user'] : false;

    // Revoke this browser's remember-me token in the database before the session
    // goes, so a copied cookie cannot outlive the logout.
    if (isset($_COOKIE['software']['auth'])) {
        $auth_parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
        if ($auth_parts[0] !== '') {
            pg_auth_token_revoke($auth_parts[0]);
        }
    }

    session_unset();
    session_destroy();
    // If the user was not logged in as a different user, then also clear the
    // remember me cookie. We don't clear it when logged in as a different user,
    // so the user can automatically log back in under their own account now.
    if ($logged_in_as_different_user == false) {
        setcookie('software[auth]', '', time() - 1000, '/');
    }
    // remove the device type cookie, so if this visitor is a control panel user that was just previewing
    // mobile then the mobile site will not appear by default in their desktop web browser the next time
    // the visitor visits the site
    setcookie('software[device_type]', '', time() - 1000, '/');
    unset($_COOKIE['software']['device_type']);
    // if user was logged in
    if ($username) {
        log_activity(lang('user logged out'), $username);
        // else there was no user to logout
    } else {
        output_error(lang('You are not logged in.'));
    }
}
function select_month($month)
{
    $output = '';
    $months = array();
    $months[] = array(
        'name' => lang('January'),
        'value' => '01'
    );
    $months[] = array(
        'name' => lang('February'),
        'value' => '02'
    );
    $months[] = array(
        'name' => lang('March'),
        'value' => '03'
    );
    $months[] = array(
        'name' => lang('April'),
        'value' => '04'
    );
    $months[] = array(
        'name' => lang('May'),
        'value' => '05'
    );
    $months[] = array(
        'name' => lang('June'),
        'value' => '06'
    );
    $months[] = array(
        'name' => lang('July'),
        'value' => '07'
    );
    $months[] = array(
        'name' => lang('August'),
        'value' => '08'
    );
    $months[] = array(
        'name' => lang('September'),
        'value' => '09'
    );
    $months[] = array(
        'name' => lang('October'),
        'value' => '10'
    );
    $months[] = array(
        'name' => lang('November'),
        'value' => '11'
    );
    $months[] = array(
        'name' => lang('December'),
        'value' => '12'
    );
    foreach ($months as $key => $value) {
        // if this month is the selected month, select this option
        if ($months[$key]['value'] == $month) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $months[$key]['value'] . '"' . $selected . '>' . $months[$key]['name'] . '</option>';
    }
    return $output;
}
function select_day($day)
{
    $output = '';
    $days = array();
    $days[] = array(
        'name' => '1',
        'value' => '01'
    );
    $days[] = array(
        'name' => '2',
        'value' => '02'
    );
    $days[] = array(
        'name' => '3',
        'value' => '03'
    );
    $days[] = array(
        'name' => '4',
        'value' => '04'
    );
    $days[] = array(
        'name' => '5',
        'value' => '05'
    );
    $days[] = array(
        'name' => '6',
        'value' => '06'
    );
    $days[] = array(
        'name' => '7',
        'value' => '07'
    );
    $days[] = array(
        'name' => '8',
        'value' => '08'
    );
    $days[] = array(
        'name' => '9',
        'value' => '09'
    );
    $days[] = array(
        'name' => '10',
        'value' => '10'
    );
    $days[] = array(
        'name' => '11',
        'value' => '11'
    );
    $days[] = array(
        'name' => '12',
        'value' => '12'
    );
    $days[] = array(
        'name' => '13',
        'value' => '13'
    );
    $days[] = array(
        'name' => '14',
        'value' => '14'
    );
    $days[] = array(
        'name' => '15',
        'value' => '15'
    );
    $days[] = array(
        'name' => '16',
        'value' => '16'
    );
    $days[] = array(
        'name' => '17',
        'value' => '17'
    );
    $days[] = array(
        'name' => '18',
        'value' => '18'
    );
    $days[] = array(
        'name' => '19',
        'value' => '19'
    );
    $days[] = array(
        'name' => '20',
        'value' => '20'
    );
    $days[] = array(
        'name' => '21',
        'value' => '21'
    );
    $days[] = array(
        'name' => '22',
        'value' => '22'
    );
    $days[] = array(
        'name' => '23',
        'value' => '23'
    );
    $days[] = array(
        'name' => '24',
        'value' => '24'
    );
    $days[] = array(
        'name' => '25',
        'value' => '25'
    );
    $days[] = array(
        'name' => '26',
        'value' => '26'
    );
    $days[] = array(
        'name' => '27',
        'value' => '27'
    );
    $days[] = array(
        'name' => '28',
        'value' => '28'
    );
    $days[] = array(
        'name' => '29',
        'value' => '29'
    );
    $days[] = array(
        'name' => '30',
        'value' => '30'
    );
    $days[] = array(
        'name' => '31',
        'value' => '31'
    );
    foreach ($days as $key => $value) {
        // if this day is the selected day, select this option
        if ($days[$key]['value'] == $day) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $days[$key]['value'] . '"' . $selected . '>' . $days[$key]['name'] . '</option>';
    }
    return $output;
}
function select_year($years, $year)
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    foreach ($years as $value) {
        // if this year is the selected year, select this option
        if ($value == $year) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $value . '"' . $selected . '>' . $value . '</option>';
    }
    return $output;
}
// output options for drop-down selection for selecting a user's role
function select_user_role($selected_user_role, $parent_user_role)
{
    $user_roles = array();
    // if parent user is an administrator then prepare certain roles for picklist
    if ($parent_user_role == 0) {
        $user_roles[] = array(
            'value' => '0',
            'name' => lang('Administrator')
        );
        $user_roles[] = array(
            'value' => '1',
            'name' => lang('Designer')
        );
    }
    $user_roles[] = array(
        'value' => '2',
        'name' => lang('Manager')
    );
    $user_roles[] = array(
        'value' => '3',
        'name' => lang('User')
    );
    $output = '';
    foreach ($user_roles as $user_role) {
        // if this user role is the selected user role, select this option
        if ($user_role['value'] == $selected_user_role) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $user_role['value'] . '"' . $selected . '>' . $user_role['name'] . '</option>';
    }
    return $output;
}
// output options for drop-down selection for selecting a contact group
function select_contact_group($contact_group_id = '', $user = '')
{
    $output = '';
    // get contact groups
    $query = "SELECT

           id,

           name

        FROM contact_groups

        ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $name = $row['name'];
        if ($id == $contact_group_id) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        // if access does not need to be validated
        // or contact group is the current selected contact group
        // or user has access to this contact group,
        // prepare to output contact group option
        if (!$user || $selected || (validate_contact_group_access($user, $id) == true)) {
            $output .= '<option value="' . $id . '"' . $selected . '>' . h($name) . '</option>';
        }
    }
    return $output;
}
// Prepare options for a pick list of offers.
function select_offer($offer_id = '')
{
    // Get all offers.
    $offers = db_items("SELECT

            id,

            code,

            description

        FROM offers

        ORDER BY code ASC");
    $output = '';
    // Loop through offers in order to prepare options.
    foreach ($offers as $offer) {
        // If the user has access to commerce or this is the selected offer, then output it.
        if (USER_MANAGE_ECOMMERCE || ($offer['id'] == $offer_id)) {
            $selected = '';
            // If this offer is the selected offer, then select it by default.
            if ($offer['id'] == $offer_id) {
                $selected = ' selected="selected"';
            }
            // Start the label with the offer code.
            $output_label = h($offer['code']);
            // If there is a description, then add it to the label.
            if ($offer['description'] != '') {
                $output_label .= ' - ';
                // If the description is greater than 50 characters, then truncate it to 50 characters.
                if (mb_strlen($offer['description']) > 50) {
                    $offer['description'] = mb_substr($offer['description'], 0, 50) . '...';
                }
                $output_label .= $offer['description'];
            }
            $output .= '<option value="' . $offer['id'] . '"' . $selected . '>' . $output_label . '</option>';
        }
    }
    return $output;
}
function get_page_type_properties($page_id, $page_type, $collection = '')
{
    $page_type_table_name = str_replace(' ', '_', $page_type) . '_pages';
    $sql_collection = '';
    // If this is a form list or item view, then deal with collection.
    if ($page_type == 'form list view' or $page_type == 'form item view') {
        // If the collection was not passed, then assume A collection.
        if (!$collection) {
            $collection = 'a';
        }
        $sql_collection = "AND (collection = '$collection')";
    }
    return db_item("SELECT *

        FROM $page_type_table_name

        WHERE

            (page_id = '" . e($page_id) . "')

            $sql_collection");
}
function check_for_page_type_properties($page_type)
{
    if (($page_type == 'email a friend') || ($page_type == 'folder view') || ($page_type == 'photo gallery') || ($page_type == 'search results') || ($page_type == 'custom form') || ($page_type == 'custom form confirmation') || ($page_type == 'form list view') || ($page_type == 'form item view') || ($page_type == 'form view directory') || ($page_type == 'calendar view') || ($page_type == 'calendar event view') || ($page_type == 'catalog') || ($page_type == 'catalog detail') || ($page_type == 'express order') || ($page_type == 'order form') || ($page_type == 'shopping cart') || ($page_type == 'shipping address and arrival') || ($page_type == 'shipping method') || ($page_type == 'billing information') || ($page_type == 'order preview') || ($page_type == 'order receipt') || ($page_type == 'affiliate sign up form') || ($page_type == 'update address book')) {
        return true;
    } else {
        return false;
    }
}
function create_or_update_page_type_record($page_type, $properties)
{
    $page_type_table_name = str_replace(' ', '_', $page_type) . '_pages';
    $sql_collection = "";
    // If this is a form list or item view, then deal with the collection.
    if ($page_type == 'form list view' or $page_type == 'form item view') {
        // If the collection is not set, then set to default collection A.
        if (!$properties['collection']) {
            $properties['collection'] = 'a';
        }
        $sql_collection = "AND (collection = '" . e($properties['collection']) . "')";
    }
    // check to see if there is already a page type record
    $query = "SELECT COUNT(*)

        FROM $page_type_table_name

        WHERE

            (page_id = '" . e($properties['page_id']) . "')

            $sql_collection";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_row($result);
    $number_of_records = $row[0];
    // if a record does not exist, create record
    if ($number_of_records == 0) {
        $count = 1;
        $columns = '';
        $values = '';
        foreach ($properties as $field => $value) {
            $columns .= $field;
            $values .= "'" . e($value) . "'";
            if ($count < count($properties)) {
                $columns .= ', ';
                $values .= ', ';
            }
            $count++;
        }
        $query = "INSERT INTO $page_type_table_name ($columns) VALUES ($values)";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // else a record exists, so update record
    } else {
        $count = 1;
        $sql_update = '';
        foreach ($properties as $field => $value) {
            $sql_update .= "$field = '" . e($value) . "'";
            if ($count < count($properties)) {
                $sql_update .= ', ';
            }
            $count++;
        }
        $query = "UPDATE $page_type_table_name

            SET $sql_update

            WHERE

                (page_id = '" . e($properties['page_id']) . "')

                $sql_collection";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    }
}
function get_page_type_name($page_type)
{
    switch ($page_type) {
        case 'standard':
            return lang('Standard');
            break;
        case 'change password':
            return lang('Change Password');
            break;
        case 'set password':
            return lang('Set Password');
            break;
        case 'email a friend':
            return lang('E-mail a Friend');
            break;
        case 'error':
            return lang('Error');
            break;
        case 'folder view':
            return lang('Folder View');
            break;
        case 'forgot password':
            return lang('Forgot Password');
            break;
        case 'login':
            return lang('Login');
            break;
        case 'logout':
            return lang('Logout');
            break;
        case 'photo gallery':
            return lang('Photo Gallery');
            break;
        case 'membership confirmation':
            return lang('Membership Confirmation');
            break;
        case 'membership entrance':
            return lang('Membership Entrance');
            break;
        case 'my account':
            return lang('My Account');
            break;
        case 'my account profile':
            return lang('My Account Profile');
            break;
        case 'email preferences':
            return lang('E-mail Preferences');
            break;
        case 'view order':
            return lang('View Order');
            break;
        case 'update address book':
            return lang('Update Address Book');
            break;
        case 'custom form':
            return lang('Custom Form');
            break;
        case 'custom form confirmation':
            return lang('Custom Form Confirmation');
            break;
        case 'form list view':
            return lang('Form List View');
            break;
        case 'form item view':
            return lang('Form Item View');
            break;
        case 'form view directory':
            return lang('Form View Directory');
            break;
        case 'calendar view':
            return lang('Calendar View');
            break;
        case 'calendar event view':
            return lang('Calendar Event View');
            break;
        case 'catalog':
            return lang('Catalog');
            break;
        case 'catalog detail':
            return lang('Catalog Detail');
            break;
        case 'express order':
            return lang('Express Order');
            break;
        case 'order form':
            return lang('Order Form');
            break;
        case 'shopping cart':
            return lang('Shopping Cart');
            break;
        case 'shipping address and arrival':
            return lang('Shipping Address & Arrival');
            break;
        case 'shipping method':
            return lang('Shipping Method');
            break;
        case 'billing information':
            return lang('Billing Information');
            break;
        case 'order preview':
            return lang('Order Preview');
            break;
        case 'order receipt':
            return lang('Order Receipt');
            break;
        case 'registration confirmation':
            return lang('Registration Confirmation');
            break;
        case 'registration entrance':
            return lang('Registration Entrance');
            break;
        case 'search results':
            return lang('Search Results');
            break;
        case 'affiliate sign up form':
            return lang('Affiliate Sign Up Form');
            break;
        case 'affiliate sign up confirmation':
            return lang('Affiliate Sign Up Confirmation');
            break;
        case 'affiliate welcome':
            return lang('Affiliate Welcome');
            break;
    }
}
function get_currency_name_from_code($currency_code)
{
    // if the currency code is not blank, then get name from code
    if ($currency_code != '') {
        $query = "SELECT name FROM currencies WHERE code = '" . escape($currency_code) . "'";
        $result = mysqli_query(db::$con, $query) or output_error("Query failed.");
        $row = mysqli_fetch_assoc($result);
        return $row['name'];
        // else the currency code is blank, so return blank
    } else {
        return '';
    }
}
function get_login_region_content($login_region_id)
{
    // if the user is not logged in then prepare header, possibly body with form, and footer
    if (isset($_SESSION['sessionusername']) == false) {
        // get information from the database
        $query = "SELECT 

                not_logged_in_header,

                login_form,

                not_logged_in_footer

            FROM login_regions

            WHERE id = '$login_region_id'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $login_region_header = $row['not_logged_in_header'];
        $login_form = $row['login_form'];
        $login_region_footer = $row['not_logged_in_footer'];
        // assume that the login region body should be blank, until we find out otherwise
        $login_region_body = '';
        // if the login form is enabled, then output it
        if ($login_form == 1) {
            $liveform = new liveform('login');
            // if software is in secure mode, then make sure that form is submitted to a secure URL
            if (URL_SCHEME == 'https://') {
                $action_url = 'https://' . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/index.php';
                // else software is not in secure mode, so just use relative URL
            } else {
                $action_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/index.php';
            }
            $output_remember_me = '';
            // if the remember me feature is enabled, then deal with it
            if (REMEMBER_ME == true) {
                // if the form has not been submitted yet and the visitor checked remember me during the last login,
                // then check the remember me check box by default
                if (($liveform->field_in_session('email') == false) && ($_COOKIE['software']['remember_me'] == 'true')) {
                    $liveform->assign_field_value('remember_me', '1');
                }
                $output_remember_me = '<div>' . $liveform->output_field(array(
                    'type' => 'checkbox',
                    'name' => 'remember_me',
                    'id' => 'remember_me',
                    'value' => '1',
                    'class' => 'software_input_checkbox'
                )) . '<label for="remember_me">' . lang('Remember Me') . '</label></div>';

            }
            $output_forgot_password_link = '';
            // if the forgot password link is enabled in the settings, output link
            if (FORGOT_PASSWORD_LINK == true) {
                // nofollow for the same reason as the add-comment sign-in link
                // in get_page_content.php: send_to carries the current page, so
                // every page that shows a sign-in form renders a different
                // forgot-password URL, and crawlers walk all of them. That is
                // the likeliest source of the crawler traffic seen on this
                // endpoint - and it sends them at the one page that answers by
                // sending an email.
                $output_forgot_password_link = '<div><a class="forgot_button" rel="nofollow" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/forgot_password.php?send_to=' . h(urlencode(get_request_uri())) . '">' . lang('Forgot password?') . '</a></div>' . "\n";
            }
            $login_region_body = $liveform->output_errors() . '

                <form name="login" action="' . $action_url . '" method="post" style="margin: 0px;">

                    ' . get_token_field() . '

                    <input type="hidden" name="login_region" id="login_region" value="true" />

                    <input type="hidden" name="send_to" id="send_to" value="' . h(get_request_uri()) . '" />

                    <input type="hidden" name="require_cookies" value="true" />

                    <div class="username_label"><label for="email">' . lang('Email') . ':</label></div>

                    <div>' . $liveform->field(array(
                            'type' => 'email',
                            'id' => 'email',
                            'name' => 'email',
                            'class' => 'software_input_text',
                            'required' => 'true',
                            'autocomplete' => 'email',
                            'spellcheck' => 'false'
                        )) . '

                    </div>

                    <div class="password_label"><label for="password">' . lang('Password') . ':</label></div>

                    <div>' . $liveform->field(array(
                            'type' => 'password',
                            'id' => 'password',
                            'name' => 'password',
                            'class' => 'software_input_password',
                            'required' => 'true',
                            'autocomplete' => 'current-password',
                            'spellcheck' => 'false'
                        )) . '

                    </div>

                    ' . $output_remember_me . '
                    <div style="margin-top: .5em;">
                        <button type="submit" name="submit_login" value="Login" class="software_input_submit_primary login_button">' . lang('Login') . '</button>
                    </div>
                    ' . $output_forgot_password_link . '

                </form>';
            $liveform->remove();
        }
        // else the user is logged in, so prepare header, body with username, and footer
    } else {
        // get header and footer information from the datbase
        $query = "SELECT 

                logged_in_header,

                logged_in_footer

            FROM login_regions

            WHERE id = '$login_region_id'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $login_region_header = $row['logged_in_header'];
        $login_region_footer = $row['logged_in_footer'];
        $output_badge = '';
        // Get badge info for user.
        $query = "SELECT user_badge AS badge, user_badge_label AS badge_label FROM user WHERE user_username = '" . escape($_SESSION['sessionusername']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $badge = $row['badge'];
        $badge_label = $row['badge_label'];
        // If badge is enabled for user and there is a badge label, then output badge.
        if (($badge == 1) && (($badge_label != '') || (BADGE_LABEL != ''))) {
            // If the user's badge label is blank, then use default label.
            if ($badge_label == '') {
                $badge_label = BADGE_LABEL;
            }
            $output_badge = ' <span class="software_badge ' . h(get_class_name($badge_label)) . '">' . h($badge_label) . '</span>';
        }
        $login_region_body = h($_SESSION['sessionusername']) . $output_badge;
    }
    $login_region_content = '<div class="software_login_region">

            ' . $login_region_header . '

            ' . $login_region_body . '

            ' . $login_region_footer . '

        </div>';
    return $login_region_content;
}
function get_salutation_options()
{
    return array(
        '' => '',
        'Dr.' => 'Dr.',
        'Miss' => 'Miss',
        'Mr.' => 'Mr.',
        'Mrs.' => 'Mrs.',
        'Ms.' => 'Ms.',
        'Prof.' => 'Prof.',
        'Rev.' => 'Rev.'
    );
}
function get_suffix_options()
{
    return array(
        '' => '',
        'I' => 'I',
        'II' => 'II',
        'III' => 'III',
        'IV' => 'IV',
        'Jr.' => 'Jr.',
        'Sr.' => 'Sr.'
    );
}
function get_tax_rate_for_address($country_code, $state_code)
{
    // declare tax zones for country array that we will use to store all valid tax zones for country
    $tax_zones_for_country = array();
    // get all tax zones that contain country
    $query = "SELECT tax_zone_id

             FROM tax_zones_countries_xref

             LEFT JOIN countries ON countries.id = tax_zones_countries_xref.country_id

             WHERE countries.code = '" . escape($country_code) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $tax_zones_for_country[] = $row['tax_zone_id'];
    }
    // declare tax zones for state array that we will use to store all valid tax zones for state
    $tax_zones_for_state = array();
    // get country id
    $query = "SELECT id FROM countries WHERE code = '" . escape($country_code) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);

    // $row is null when the country code is not in the database.
    $country_id = isset($row['id']) ? $row['id'] : '';
    // find out if state is in database and if it belongs to country
    $query = "SELECT id

        FROM states

        WHERE

            (code = '" . escape($state_code) . "')

            AND (country_id = '$country_id')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if state was found in database and it belongs to country, find what tax zones contain state
    if (mysqli_num_rows($result) > 0) {
        $query = "SELECT tax_zone_id

                 FROM tax_zones_states_xref

                 LEFT JOIN states ON states.id = tax_zones_states_xref.state_id

                 WHERE states.code = '" . escape($state_code) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        while ($row = mysqli_fetch_assoc($result)) {
            $tax_zones_for_state[] = $row['tax_zone_id'];
        }
        // loop through all tax zones for country
        foreach ($tax_zones_for_country as $tax_zone_id) {
            // if tax zone id in tax zone for country is also a valid tax zone for state, then it is a valid tax zone
            if (in_array($tax_zone_id, $tax_zones_for_state) == true) {
                $valid_tax_zone_id = $tax_zone_id;
                break;
            }
        }
        // else state was not found in database, so valid tax zone is valid tax zone for country
    } else {
        $valid_tax_zone_id = isset($tax_zones_for_country[0]) ? $tax_zones_for_country[0] : '';
    }
    // only set when a valid tax zone is found just below
    $tax_rate = '';

    // if a valid tax zone was found, get tax rate
    if ($valid_tax_zone_id) {
        $query = "SELECT tax_rate FROM tax_zones WHERE id = '$valid_tax_zone_id'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $tax_rate = $row['tax_rate'];
    }
    // Being in a zone is the answer, not the size of its rate. A zone set to
    // 0.000 used to report "no tax zone here", which was the same outcome while
    // the zone was the only place a rate could live. It is not the same now: a
    // shop that keeps its rates on the articles sets the zone to zero, and
    // treating that as "outside every zone" would stop tax being charged on
    // every product that carries a rate of its own.
    if ($valid_tax_zone_id) {
        return $tax_rate;
        // else the address is in no zone at all
    } else {
        return false;
    }
}
function generate_order_reference_code()
{
    $characters = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
    // built up in the loop below, so it has to start out empty
    $reference_code = '';

    for ($i = 1; $i <= 10; $i++) {
        $index = mt_rand(0, 35);
        $reference_code .= $characters[$index];
    }
    // check to see if reference code is already in use
    $query = "SELECT id FROM orders WHERE reference_code = '$reference_code'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if reference code is already in use, use recursion to generate a new reference code
    if (mysqli_num_rows($result) > 0) {
        return generate_order_reference_code();
        // else reference code is not already in use, so return reference code
    } else {
        return $reference_code;
    }
}
function generate_affiliate_code()
{
    $characters = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
    for ($i = 1; $i <= 5; $i++) {
        $index = mt_rand(0, 35);
        $affiliate_code .= $characters[$index];
    }
    // check to see if affiliate code is already in use
    $query = "SELECT id FROM contacts WHERE affiliate_code = '$affiliate_code'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if affiliate code is already in use, use recursion to generate a new affiliate code
    if (mysqli_num_rows($result) > 0) {
        return generate_affiliate_code();
        // else affiliate code is not already in use, so return affiliate code
    } else {
        return $affiliate_code;
    }
}

// create function to get current URL
function get_request_uri()
{
    // the order of the conditionals below is important, because newer versions of IIS supply a REQUEST_URI value,
    // but the value does not contain the original pretty URL and might also have other problems

    // if HTTP_X_REWRITE_URL is set (i.e. ISAPI_Rewrite is being used on IIS)
    if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
        return $_SERVER['HTTP_X_REWRITE_URL'];

        // else if REQUEST_URI is set (i.e. non IIS web server)
    } elseif (isset($_SERVER['REQUEST_URI'])) {
        return $_SERVER['REQUEST_URI'];

        // else no REQUEST_URI or HTTP_X_REWRITE_URL can be found (i.e. IIS web server without ISAPI_Rewrite)
    } else {
        // if QUERY_STRING is set then return PHP_SELF and QUERY_STRING
        if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
            return $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];

            // else QUERY_STRING is not set, so return just PHP_SELF
        } else {
            return $_SERVER['PHP_SELF'];
        }
    }
}


function prepare_form_data_for_input($data, $type)
{
    if ($data) {
        switch ($type) {
            case 'date':
                $date_parts = preg_split('/[-,\/]/', $data);

                // A value that is not in a date format does not split into three parts.
                $date_parts = $date_parts + array('', '', '');

                $year = $date_parts[2];
                if (DATE_FORMAT == 'month_day') {
                    $month = $date_parts[0];
                    $day = $date_parts[1];
                } else {
                    $month = $date_parts[1];
                    $day = $date_parts[0];
                }
                $year = str_pad($year, 4, '0', STR_PAD_LEFT);
                $month = str_pad($month, 2, '0', STR_PAD_LEFT);
                $day = str_pad($day, 2, '0', STR_PAD_LEFT);
                return $year . '-' . $month . '-' . $day;
                break;
            case 'date and time':
                $date_and_time_parts = explode(' ', $data, 2);
                $date = $date_and_time_parts[0];
                $time = $date_and_time_parts[1];
                $date_parts = preg_split('/[-,\/]/', $date);
                $year = $date_parts[2];
                if (DATE_FORMAT == 'month_day') {
                    $month = $date_parts[0];
                    $day = $date_parts[1];
                } else {
                    $month = $date_parts[1];
                    $day = $date_parts[0];
                }
                $year = str_pad($year, 4, '0', STR_PAD_LEFT);
                $month = str_pad($month, 2, '0', STR_PAD_LEFT);
                $day = str_pad($day, 2, '0', STR_PAD_LEFT);
                preg_match('/(\d+):(\d+) (AM|PM)/i', $time, $time_parts);
                $hour = $time_parts[1];
                $minute = $time_parts[2];
                $am_pm = mb_strtoupper($time_parts[3]);
                // convert hour to military format
                if (($am_pm == 'PM') && ($hour != 12)) {
                    $hour = $hour + 12;
                }
                // convert 12 AM to 0 AM
                if (($am_pm == 'AM') && ($hour == 12)) {
                    $hour = 0;
                }
                $hour = str_pad($hour, 2, '0', STR_PAD_LEFT);
                $minute = str_pad($minute, 2, '0', STR_PAD_LEFT);
                return $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute;
                break;
            case 'time':
                preg_match('/(\d+):(\d+) (AM|PM)/i', $data, $time_parts);
                $hour = $time_parts[1];
                $minute = $time_parts[2];
                $am_pm = mb_strtoupper($time_parts[3]);
                // convert hour to military format
                if (($am_pm == 'PM') && ($hour != 12)) {
                    $hour = $hour + 12;
                }
                // convert 12 AM to 0 AM
                if (($am_pm == 'AM') && ($hour == 12)) {
                    $hour = 0;
                }
                $hour = str_pad($hour, 2, '0', STR_PAD_LEFT);
                $minute = str_pad($minute, 2, '0', STR_PAD_LEFT);
                return $hour . ':' . $minute;
                break;
            default:
                return $data;
                break;
        }
    } else {
        return $data;
    }
}
function prepare_form_data_for_output($data, $type, $prepare_for_html = true, $date_format = '')
{
    if ($data) {
        switch ($type) {
            case 'date':
                // if a date format was passed, then output date with that format
                if ($date_format != '') {
                    // if the format is relative, then get relative time
                    if ($date_format == 'relative') {
                        // If this is being prepared for HTML, then add tooltip with absolute date and time,
                        // so a user can hover over the relative time and see the absolute date and time
                        if ($prepare_for_html == true) {
                            $output = get_relative_time(array(
                                'timestamp' => strtotime($data),
                                'format' => 'html',
                                'type' => 'date'
                            ));
                            // because we have HTML in this output now,
                            // set the prepare for HTML value to false, so down below we don't escape HTML
                            $prepare_for_html = false;
                            // Otherwise this is being prepared for plain text, so do not add any HTML.
                        } else {
                            $output = get_relative_time(array(
                                'timestamp' => strtotime($data),
                                'format' => 'plain_text',
                                'type' => 'date'
                            ));
                        }
                        // else the format is not relative, so use format
                    } else {
                        $output = date($date_format, strtotime($data));
                    }
                    // else a date format was not passed, so output date with standard format
                } else {
                    $date_parts = explode('-', $data);
                    $month = $date_parts[1];
                    // If the first character of the month is 0, then remove the 0.
                    if (mb_substr($month, 0, 1) == '0') {
                        $month = mb_substr($month, 1);
                    }
                    $day = $date_parts[2];
                    // If the first character of the day is 0, then remove the 0.
                    if (mb_substr($day, 0, 1) == '0') {
                        $day = mb_substr($day, 1);
                    }
                    $year = $date_parts[0];
                    if (DATE_FORMAT == 'month_day') {
                        $output = $month . '/' . $day . '/' . $year;
                    } else {
                        $output = $day . '/' . $month . '/' . $year;
                    }
                }
                break;
            case 'date and time':
                // if a date format was passed, then output date with that format
                if ($date_format != '') {
                    // if the format is relative, then get relative time
                    if ($date_format == 'relative') {
                        // If this is being prepared for HTML, then add tooltip with absolute date and time,
                        // so a user can hover over the relative time and see the absolute date and time
                        if ($prepare_for_html == true) {
                            $output = get_relative_time(array(
                                'timestamp' => strtotime($data),
                                'format' => 'html'
                            ));
                            // because we have HTML in this output now,
                            // set the prepare for HTML value to false, so down below we don't escape HTML
                            $prepare_for_html = false;
                            // Otherwise this is being prepared for plain text, so do not add any HTML.
                        } else {
                            $output = get_relative_time(array(
                                'timestamp' => strtotime($data),
                                'format' => 'plain_text'
                            ));
                        }
                        // else the format is not relative, so use format
                    } else {
                        // If the visitor is logged in,
                        // and the user has a specific timezone set,
                        // and the date format contains a timezone,
                        // then get date & time in user's timezone.
                        if ((USER_LOGGED_IN) && (USER_TIMEZONE != '') && ((preg_match("/[^\\\]T/", $date_format) == 1) || (preg_match("/[^\\\]e/", $date_format) == 1))) {
                            $date = new DateTime('@' . strtotime($data));
                            $date->setTimezone(new DateTimeZone(USER_TIMEZONE));
                            $output = $date->format($date_format);
                            // Otherwise get date & time in site's timezone.
                        } else {
                            $output = date($date_format, strtotime($data));
                        }
                    }
                    // else a date format was not passed, so output date with standard format
                } else {
                    $date_and_time_parts = explode(' ', $data, 2);
                    $date = $date_and_time_parts[0];
                    $time = $date_and_time_parts[1];
                    $date_parts = explode('-', $date);
                    $month = $date_parts[1];
                    // If the first character of the month is 0, then remove the 0.
                    if (mb_substr($month, 0, 1) == '0') {
                        $month = mb_substr($month, 1);
                    }
                    $day = $date_parts[2];
                    // If the first character of the day is 0, then remove the 0.
                    if (mb_substr($day, 0, 1) == '0') {
                        $day = mb_substr($day, 1);
                    }
                    $year = $date_parts[0];
                    $time_parts = explode(':', $time);
                    $hour = $time_parts[0];
                    $minute = $time_parts[1];
                    // if it is AM
                    if ($hour <= 11) {
                        $am_pm = 'AM';
                        // else it is PM
                    } else {
                        $am_pm = 'PM';
                    }
                    // if hour is equal to 0 then convert to 12
                    if ($hour == 0) {
                        $hour = 12;
                        // else if hour is greater or equal to 13, subtract 12
                    } elseif ($hour >= 13) {
                        $hour = $hour - 12;
                    }
                    // If the first character of the hour is 0, then remove the 0.
                    if (mb_substr($hour, 0, 1) == '0') {
                        $hour = mb_substr($hour, 1);
                    }
                    $minute = str_pad($minute, 2, '0', STR_PAD_LEFT);
                    if (DATE_FORMAT == 'month_day') {
                        $output = $month . '/' . $day . '/' . $year . ' ' . $hour . ':' . $minute . ' ' . $am_pm;
                    } else {
                        $output = $day . '/' . $month . '/' . $year . ' ' . $hour . ':' . $minute . ' ' . $am_pm;
                    }
                }
                break;
            case 'time':
                $time_parts = explode(':', $data);
                $hour = $time_parts[0];
                $minute = $time_parts[1];
                // if it is AM
                if ($hour <= 11) {
                    $am_pm = 'AM';
                    // else it is PM
                } else {
                    $am_pm = 'PM';
                }
                // if hour is equal to 0 then convert to 12
                if ($hour == 0) {
                    $hour = 12;
                    // else if hour is greater or equal to 13, subtract 12
                } elseif ($hour >= 13) {
                    $hour = $hour - 12;
                }
                // If the first character of the hour is 0, then remove the 0.
                if (mb_substr($hour, 0, 1) == '0') {
                    $hour = mb_substr($hour, 1);
                }
                $minute = str_pad($minute, 2, '0', STR_PAD_LEFT);
                $output = $hour . ':' . $minute . ' ' . $am_pm;
                break;
            // A signature's stored value is the file it was drawn into, and a
            // file name proves nothing to whoever is reading. This is the one
            // field whose whole purpose is to be seen, so the token prints the
            // signature itself - as an image, never a control. What already
            // happened is shown, not offered again.
            case 'signature':
                if ($prepare_for_html == true) {
                    $output = '<img src="' . h(OUTPUT_PATH . encode_url_path($data)) . '" alt="' . h(lang('Signature')) . '" style="max-width:320px;height:auto">';
                    $prepare_for_html = false;

                // Plain text - an e-mail body, an export - gets an address the
                // reader can open instead of markup they would have to read.
                } else {
                    $output = OUTPUT_PATH . $data;
                }

                break;
            // Form data of type html is the markup a WYSIWYG text area was
            // filled in with, and it is printed unescaped. The author may be an
            // anonymous visitor, so the markup is filtered on the way out too;
            // this covers rows stored before the filter existed.
            case 'html':
                $output = $data;
                if ($prepare_for_html == false) {
                    $output = pg_sanitize_rich_text($output);
                }
                break;
            default:
                $output = $data;
                break;
        }
        if ($prepare_for_html == true) {
            $output = h($output);
            $output = nl2br($output);
        }
    } else {
        $output = $data;
    }
    return $output;
}
function get_standard_fields_for_view()
{
    $standard_fields = array(
        array(
            'name' => lang('Reference Code'),
            'value' => 'reference_code',
            'sql_name' => 'forms.reference_code',
            'type' => ''
        ),
        array(
            'name' => lang('Complete'),
            'value' => 'complete',
            'sql_name' => 'CASE WHEN (forms.complete) THEN "Complete" ELSE "" END',
            'type' => ''
        ),
        array(
            'name' => lang('Address Name'),
            'value' => 'address_name',
            'sql_name' => 'forms.address_name',
            'type' => ''
        ),
        array(
            'name' => lang('Tracking Code'),
            'value' => 'tracking_code',
            'sql_name' => 'forms.tracking_code',
            'type' => ''
        ),
        array(
            'name' => lang('Affiliate Code'),
            'value' => 'affiliate_code',
            'sql_name' => 'forms.affiliate_code',
            'type' => ''
        ),
        array(
            'name' => lang('Referring URL'),
            'value' => 'referring_url',
            'sql_name' => 'forms.http_referer',
            'type' => ''
        ),
        array(
            'name' => lang('Submitter'),
            'value' => 'submitter',
            'sql_name' => 'submitter.user_username',
            'type' => 'username'
        ),
        array(
            'name' => lang('Submitted Date & Time'),
            'value' => 'submitted_date_and_time',
            'sql_name' => 'FROM_UNIXTIME(forms.submitted_timestamp)',
            'type' => 'date and time'
        ),
        array(
            'name' => lang('Last Modifier'),
            'value' => 'last_modifier',
            'sql_name' => 'last_modifier.user_username',
            'type' => 'username'
        ),
        array(
            'name' => lang('Last Modified Date & Time'),
            'value' => 'last_modified_date_and_time',
            'sql_name' => 'FROM_UNIXTIME(forms.last_modified_timestamp)',
            'type' => 'date and time'
        ),
        array(
            'name' => lang('Number of Views'),
            'value' => 'number_of_views',
            'sql_name' => 'submitted_form_info.number_of_views',
            'type' => ''
        ),
        array(
            'name' => lang('Number of Comments'),
            'value' => 'number_of_comments',
            'sql_name' => 'submitted_form_info.number_of_comments',
            'type' => ''
        ),
        array(
            'name' => lang('Newest Comment Name'),
            'value' => 'newest_comment_name',
            'sql_name' => 'newest_comment.name',
            'type' => ''
        ),
        array(
            'name' => lang('Newest Comment'),
            'value' => 'newest_comment',
            'sql_name' => 'newest_comment.message',
            'type' => ''
        ),
        array(
            'name' => lang('Newest Comment Date & Time'),
            'value' => 'newest_comment_date_and_time',
            'sql_name' => 'FROM_UNIXTIME(newest_comment.created_timestamp)',
            'type' => 'date and time'
        ),
        array(
            'name' => lang('Newest Comment ID'),
            'value' => 'newest_comment_id',
            'sql_name' => 'newest_comment.id',
            'type' => ''
        ),
        array(
            'name' => lang('Newest Activity Date & Time'),
            'value' => 'newest_activity_date_and_time',
            'sql_name' => 'FROM_UNIXTIME(GREATEST(forms.submitted_timestamp, IFNULL(newest_comment.created_timestamp, \'\')))',
            'type' => 'date and time'
        ),
        array(
            'name' => lang('Comment Attachments'),
            'value' => 'comment_attachments',
            'sql_name' => "(SELECT GROUP_CONCAT(files.name ORDER BY comments.id ASC SEPARATOR '||')

                    FROM comments

                    LEFT JOIN files ON files.id = comments.file_id

                    WHERE

                        (comments.page_id = submitted_form_info.page_id)

                        AND (comments.item_id = forms.id)

                        AND (comments.item_type = 'submitted_form')

                        AND (comments.published = 1)

                        AND (comments.file_id != 0))",
            'type' => ''
        )
    );
    return $standard_fields;
}
// This function is used by form list view to create sql for filters.
function prepare_sql_operation($operator, $operand_1, $operand_2)
{
    $quote = '';
    // If the operator is a size operator and the second operand is not numeric,
    // then prepare to add single quotes around the second operand.  We have to eliminate
    // quotes for numeric values so that MySQL will compare the operands correctly.
    if ((($operator == 'is less than') || ($operator == 'is less than or equal to') || ($operator == 'is greater than') || ($operator == 'is greater than or equal to')) && (is_numeric($operand_2) == false)) {
        $quote = "'";
    }
    switch ($operator) {
        case 'contains':
        default:
            return "IFNULL(" . $operand_1 . ", '') LIKE '%" . e(escape_like($operand_2)) . "%'";
            break;
        case 'does not contain':
            return "IFNULL(" . $operand_1 . ", '') NOT LIKE '%" . e(escape_like($operand_2)) . "%'";
            break;
        case 'is equal to':
            return "IFNULL(" . $operand_1 . ", '') = '" . e($operand_2) . "'";
            break;
        case 'is not equal to':
            return "IFNULL(" . $operand_1 . ", '') != '" . e($operand_2) . "'";
            break;
        case 'is less than':
            return "IFNULL(" . $operand_1 . ", '') < " . $quote . e($operand_2) . $quote;
            break;
        case 'is less than or equal to':
            return "IFNULL(" . $operand_1 . ", '') <= " . $quote . e($operand_2) . $quote;
            break;
        case 'is greater than':
            return "IFNULL(" . $operand_1 . ", '') > " . $quote . e($operand_2) . $quote;
            break;
        case 'is greater than or equal to':
            return "IFNULL(" . $operand_1 . ", '') >= " . $quote . e($operand_2) . $quote;
            break;
    }
}
// this function is used by form list view to convert a dynamic value to a static value
function get_dynamic_value($dynamic_value, $dynamic_value_attribute = '')
{
    switch ($dynamic_value) {
        case 'current date':
            return date('Y-m-d');
            break;
        case 'current date and time':
            return date('Y-m-d H:i:s');
            break;
        case 'current time':
            return date('H:i:s');
            break;
        case 'days ago':
            return date('Y-m-d H:i:s', time() - (86400 * $dynamic_value_attribute));
            break;
        case 'viewer':
            return $_SESSION['sessionusername'];
            break;
        case 'viewers email address':
            $viewers_email_address = '';
            // if there is a user logged in then get the user's e-mail address
            if (isset($_SESSION['sessionusername']) == true) {
                $query = "SELECT user_email FROM user WHERE user_username = '" . escape($_SESSION['sessionusername']) . "'";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                $row = mysqli_fetch_assoc($result);
                $viewers_email_address = $row['user_email'];
            }
            return $viewers_email_address;
            break;
    }
}
function generate_email_recipient_reference_code()
{
    $characters = array(
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'Q',
        'R',
        'S',
        'T',
        'U',
        'V',
        'W',
        'X',
        'Y',
        'Z'
    );
    // built up in the loop below, so it has to start out empty
    $reference_code = '';

    for ($i = 1; $i <= 10; $i++) {
        $index = mt_rand(0, 35);
        $reference_code .= $characters[$index];
    }
    // check to see if reference code is already in use
    $query = "SELECT id FROM email_recipients WHERE reference_code = '$reference_code'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if reference code is already in use, use recursion to generate a new reference code
    if (mysqli_num_rows($result) > 0) {
        return generate_form_reference_code();
        // else reference code is not already in use, so return reference code
    } else {
        return $reference_code;
    }
}
function generate_form_reference_code()
{
    $characters = array(
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'Q',
        'R',
        'S',
        'T',
        'U',
        'V',
        'W',
        'X',
        'Y',
        'Z'
    );
    // built up in the loop below, so it has to start out empty
    $reference_code = '';

    for ($i = 1; $i <= 10; $i++) {
        $index = mt_rand(0, 35);
        $reference_code .= $characters[$index];
    }
    // check to see if reference code is already in use
    $query = "SELECT id FROM forms WHERE reference_code = '$reference_code'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if reference code is already in use, use recursion to generate a new reference code
    if (mysqli_num_rows($result) > 0) {
        return generate_form_reference_code();
        // else reference code is not already in use, so return reference code
    } else {
        return $reference_code;
    }
}
function generate_commission_reference_code()
{
    $characters = array(
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'Q',
        'R',
        'S',
        'T',
        'U',
        'V',
        'W',
        'X',
        'Y',
        'Z'
    );
    // built up in the loop below, so it has to start out empty
    $reference_code = '';

    for ($i = 1; $i <= 10; $i++) {
        $index = mt_rand(0, 35);
        $reference_code .= $characters[$index];
    }
    // check to see if reference code is already in use
    $query = "SELECT id FROM commissions WHERE reference_code = '$reference_code'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if reference code is already in use, use recursion to generate a new reference code
    if (mysqli_num_rows($result) > 0) {
        return generate_commission_reference_code();
        // else reference code is not already in use, so return reference code
    } else {
        return $reference_code;
    }
}
function generate_encryption_key()
{
    $characters = array(
        '0',
        '1',
        '2',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'Q',
        'R',
        'S',
        'T',
        'U',
        'V',
        'W',
        'X',
        'Y',
        'Z'
    );
    $key = '';
    for ($i = 1; $i <= 32; $i++) {
        $index = mt_rand(0, 35);
        $key .= $characters[$index];
    }
    return $key;
}
function get_tracking_code()
{
    // if there is a tracking code in the session, then return it
    if (isset($_SESSION['software']['tracking_code']) && $_SESSION['software']['tracking_code']) {
        return $_SESSION['software']['tracking_code'];
        // else if there is a tracking code in a cookie, then return it
    } elseif (isset($_COOKIE['software']['tracking_code']) && $_COOKIE['software']['tracking_code']) {
        return $_COOKIE['software']['tracking_code'];
        // else no tracking code can be found, so return empty string
    } else {
        return '';
    }
}
function get_affiliate_code()
{
    // if there is a tracking code in the session, then return it
    if (isset($_SESSION['software']['affiliate_code']) && $_SESSION['software']['affiliate_code']) {
        return $_SESSION['software']['affiliate_code'];
        // else if there is a tracking code in a cookie, then return it
    } elseif (isset($_COOKIE['software']['affiliate_code']) && $_COOKIE['software']['affiliate_code']) {
        return $_COOKIE['software']['affiliate_code'];
        // else no tracking code can be found, so return empty string
    } else {
        return '';
    }
}
function validate_order_item_for_arrival_date($order_item_id)
{
    // get information for order item
    $query = "SELECT

            ship_tos.id AS ship_to_id,

            ship_tos.arrival_date,

            ship_tos.zip_code,

            ship_tos.country,

            ship_tos.shipping_method_id

        FROM order_items

        LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id

        WHERE order_items.id = '" . e($order_item_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $ship_to_id = $row['ship_to_id'];
    $arrival_date = $row['arrival_date'];
    $zip_code = $row['zip_code'];
    $country = $row['country'];
    $shipping_method_id = $row['shipping_method_id'];
    // if the requested arrival date is not at once
    if ($arrival_date != '0000-00-00') {
        // determine if there is a shipping cut-off for the arrival date and shipping method
        $query = "SELECT

                shipping_cutoffs.date_and_time

            FROM shipping_cutoffs

            LEFT JOIN arrival_dates ON shipping_cutoffs.arrival_date_id = arrival_dates.id

            WHERE

                (arrival_dates.arrival_date = '" . escape($arrival_date) . "')

                AND (shipping_cutoffs.shipping_method_id = '$shipping_method_id')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // if there is a shipping cut-off, then the order item is valid for the arrival date, because we ignore the product preparation time when there is a shipping cut-off
        if (mysqli_num_rows($result) > 0) {
            return true;
            // else there is not a shipping cut-off, so determine if the order item is valid for the arrival date by looking at the transit time
        } else {
            require_once(PG_FUNCTIONS_DIR . '/shipping.php');
            $response = get_delivery_date(array(
                'ship_to_id' => $ship_to_id,
                'shipping_method' => array(
                    'id' => $shipping_method_id
                ),
                'zip_code' => $zip_code,
                'country' => $country
            ));
            $delivery_date = $response['delivery_date'];
            if (!$delivery_date or $delivery_date > $arrival_date) {
                return false;
            } else {
                return true;
            }
        }
        // else the requested arrival date is at once, so the product is valid for the requested arrival date
    } else {
        return true;
    }
}
function get_update_currency_form()
{
    // if multi-currency is disabled in the settings, then do not return form and return empty string
    if (ECOMMERCE_MULTICURRENCY == false) {
        return '';
    }
    // Get all currencies where the exchange rate is not 0, with base currency first.
    $currencies = db_items("SELECT

            id,

            name,

            code,

            exchange_rate

        FROM currencies

        WHERE exchange_rate != '0'

        ORDER BY

            base DESC,

            name ASC");
    // If there is less than 2 currencies, then return empty string.
    if (count($currencies) < 2) {
        return '';
    }
    $output_currency_options = '';
    // loop through all currencies
    foreach ($currencies as $currency) {
        $selected = '';
        // if this currency matches which currency the user chose, display it as selected in the picklist.
        if ($currency['id'] == VISITOR_CURRENCY_ID) {
            $selected = ' selected="selected"';
        }
        // prepare option for currency
        $output_currency_options .= '<option value="' . $currency['id'] . '"' . $selected . '>' . h($currency['name']) . ' (' . h($currency['code']) . ')</option>';
    }
    return '<form action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/update_currency.php" method="post" class="currency" style="text-align: right; margin-top: 15px; margin-bottom: 15px">

            ' . get_token_field() . '

            <input type="hidden" name="send_to" value="' . h(get_request_uri()) . '">

            <select name="currency_id" class="software_select">' . $output_currency_options . '</select> <button type="submit" name="submit" value="Update" class="software_input_submit_small_secondary">' . lang('Update') . '</button>

        </form>';
}
function get_currency_amount($amount, $exchange_rate)
{
    return $amount * $exchange_rate;
}
function get_currency_options($currency_code = '')
{
    // only ever appended to below, so it has to start out empty
    $output = '';

    // Get currency names and codes, with the base currency first.
    $query = "SELECT

            name,

            code

        FROM currencies

        ORDER BY

            base DESC,

            name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // loop through each currency and create an <option> tag
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['code'] == $currency_code) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . h($row['code']) . '"' . $selected . '>' . h($row['name']) . ' (' . h($row['code']) . ')</option>';
    }
    return $output;
}
function get_spell_checker_engine_info()
{
    $spell_checker_engine_info = array();

    // if pspell extension is loaded in PHP, then return its information
    if (function_exists("pspell_new")) {
        $spell_checker_engine_info['name'] = lang('Pspell Extension');
        return $spell_checker_engine_info;
    }

    // if the server is not running Windows, then try to find aspell
    if (mb_strtoupper(mb_substr(PHP_OS, 0, 3)) != 'WIN' && function_exists('shell_exec')) {

        // try to find aspell at /usr/bin/aspell
        $path = '/usr/bin/aspell';
        $data = shell_exec($path . ' --version');
        if (!empty($data)) {
            $spell_checker_engine_info['name'] = 'Aspell';
            $spell_checker_engine_info['path'] = $path;
            return $spell_checker_engine_info;
        }

        // try to find aspell at /usr/local/bin/aspell
        $path = '/usr/local/bin/aspell';
        $data = shell_exec($path . ' --version');
        if (!empty($data)) {
            $spell_checker_engine_info['name'] = 'Aspell';
            $spell_checker_engine_info['path'] = $path;
            return $spell_checker_engine_info;
        }
    }

    // fallback: Google
    $spell_checker_engine_info['name'] = 'Google';
    return $spell_checker_engine_info;
}
// get three digit country code from 2 character country code
function get_country_number($country_code)
{
    switch ($country_code) {
        case 'US':
            return '840';
        case 'AF':
            return '004';
        case 'AX':
            return '248';
        case 'AL':
            return '008';
        case 'DZ':
            return '012';
        case 'AS':
            return '016';
        case 'AD':
            return '020';
        case 'AO':
            return '024';
        case 'AI':
            return '660';
        case 'AQ':
            return '010';
        case 'AG':
            return '028';
        case 'AR':
            return '032';
        case 'AM':
            return '051';
        case 'AW':
            return '533';
        case 'AU':
            return '036';
        case 'AT':
            return '040';
        case 'AZ':
            return '031';
        case 'BS':
            return '044';
        case 'BH':
            return '048';
        case 'BD':
            return '050';
        case 'BB':
            return '052';
        case 'BY':
            return '112';
        case 'BE':
            return '056';
        case 'BZ':
            return '084';
        case 'BJ':
            return '204';
        case 'BM':
            return '060';
        case 'BT':
            return '064';
        case 'BO':
            return '068';
        case 'BA':
            return '070';
        case 'BW':
            return '072';
        case 'BV':
            return '074';
        case 'BR':
            return '076';
        case 'IO':
            return '086';
        case 'BN':
            return '096';
        case 'BG':
            return '100';
        case 'BF':
            return '854';
        case 'BI':
            return '108';
        case 'KH':
            return '116';
        case 'CM':
            return '120';
        case 'CA':
            return '124';
        case 'CV':
            return '132';
        case 'KY':
            return '136';
        case 'CF':
            return '140';
        case 'TD':
            return '148';
        case 'XX':
            return '830';
        case 'CL':
            return '152';
        case 'CN':
            return '156';
        case 'CX':
            return '162';
        case 'CC':
            return '166';
        case 'CO':
            return '170';
        case 'KM':
            return '174';
        case 'CD':
            return '180';
        case 'CG':
            return '178';
        case 'CK':
            return '184';
        case 'CR':
            return '188';
        case 'CI':
            return '384';
        case 'HR':
            return '191';
        case 'CU':
            return '192';
        case 'CY':
            return '196';
        case 'CZ':
            return '203';
        case 'DK':
            return '208';
        case 'DJ':
            return '262';
        case 'DM':
            return '212';
        case 'DO':
            return '214';
        case 'EC':
            return '218';
        case 'EG':
            return '818';
        case 'SV':
            return '222';
        case 'GQ':
            return '226';
        case 'ER':
            return '232';
        case 'EE':
            return '233';
        case 'ET':
            return '231';
        case 'FK':
            return '238';
        case 'FO':
            return '234';
        case 'FJ':
            return '242';
        case 'FI':
            return '246';
        case 'FR':
            return '250';
        case 'FX':
            return '249';
        case 'GF':
            return '254';
        case 'PF':
            return '258';
        case 'TF':
            return '260';
        case 'GA':
            return '266';
        case 'GM':
            return '270';
        case 'GE':
            return '268';
        case 'DE':
            return '276';
        case 'GH':
            return '288';
        case 'GI':
            return '292';
        case 'GR':
            return '300';
        case 'GL':
            return '304';
        case 'GD':
            return '308';
        case 'GP':
            return '312';
        case 'GU':
            return '316';
        case 'GT':
            return '320';
        case 'GN':
            return '324';
        case 'GW':
            return '624';
        case 'GY':
            return '328';
        case 'HT':
            return '332';
        case 'HM':
            return '334';
        case 'VA':
            return '336';
        case 'HN':
            return '340';
        case 'HK':
            return '344';
        case 'HU':
            return '348';
        case 'IS':
            return '352';
        case 'IN':
            return '356';
        case 'ID':
            return '360';
        case 'IR':
            return '364';
        case 'IQ':
            return '368';
        case 'IE':
            return '372';
        case 'IM':
            return '833';
        case 'IL':
            return '376';
        case 'IT':
            return '380';
        case 'JM':
            return '388';
        case 'JP':
            return '392';
        case 'JO':
            return '400';
        case 'KZ':
            return '398';
        case 'KE':
            return '404';
        case 'KI':
            return '296';
        case 'KP':
            return '408';
        case 'KR':
            return '410';
        case 'KW':
            return '414';
        case 'KG':
            return '417';
        case 'LA':
            return '418';
        case 'LV':
            return '428';
        case 'LB':
            return '422';
        case 'LS':
            return '426';
        case 'LR':
            return '430';
        case 'LY':
            return '434';
        case 'LI':
            return '438';
        case 'LT':
            return '440';
        case 'LU':
            return '442';
        case 'MO':
            return '446';
        case 'MK':
            return '807';
        case 'MG':
            return '450';
        case 'MW':
            return '454';
        case 'MY':
            return '458';
        case 'MV':
            return '462';
        case 'ML':
            return '466';
        case 'MT':
            return '470';
        case 'MH':
            return '584';
        case 'MQ':
            return '474';
        case 'MR':
            return '478';
        case 'MU':
            return '480';
        case 'YT':
            return '175';
        case 'MX':
            return '484';
        case 'FM':
            return '583';
        case 'MD':
            return '498';
        case 'MC':
            return '492';
        case 'MN':
            return '496';
        case 'MS':
            return '500';
        case 'MA':
            return '504';
        case 'MZ':
            return '508';
        case 'MM':
            return '104';
        case 'NA':
            return '516';
        case 'NR':
            return '520';
        case 'NP':
            return '524';
        case 'NL':
            return '528';
        case 'AN':
            return '530';
        case 'NC':
            return '540';
        case 'NZ':
            return '554';
        case 'NI':
            return '558';
        case 'NE':
            return '562';
        case 'NG':
            return '566';
        case 'NU':
            return '570';
        case 'NF':
            return '574';
        case 'MP':
            return '580';
        case 'NO':
            return '578';
        case 'OM':
            return '512';
        case 'PK':
            return '586';
        case 'PW':
            return '585';
        case 'PS':
            return '275';
        case 'PA':
            return '591';
        case 'PG':
            return '598';
        case 'PY':
            return '600';
        case 'PE':
            return '604';
        case 'PH':
            return '608';
        case 'PN':
            return '612';
        case 'PL':
            return '616';
        case 'PT':
            return '620';
        case 'PR':
            return '630';
        case 'QA':
            return '634';
        case 'RE':
            return '638';
        case 'RO':
            return '642';
        case 'RU':
            return '643';
        case 'RW':
            return '646';
        case 'SH':
            return '654';
        case 'KN':
            return '659';
        case 'LC':
            return '662';
        case 'PM':
            return '666';
        case 'VC':
            return '670';
        case 'WS':
            return '882';
        case 'SM':
            return '674';
        case 'ST':
            return '678';
        case 'SA':
            return '682';
        case 'SN':
            return '686';
        case 'CS':
            return '891';
        case 'SC':
            return '690';
        case 'SL':
            return '694';
        case 'SG':
            return '702';
        case 'SK':
            return '703';
        case 'SI':
            return '705';
        case 'SB':
            return '090';
        case 'SO':
            return '706';
        case 'ZA':
            return '710';
        case 'GS':
            return '239';
        case 'ES':
            return '724';
        case 'LK':
            return '144';
        case 'SD':
            return '736';
        case 'SR':
            return '740';
        case 'SJ':
            return '744';
        case 'SZ':
            return '748';
        case 'SE':
            return '752';
        case 'CH':
            return '756';
        case 'SY':
            return '760';
        case 'TW':
            return '158';
        case 'TJ':
            return '762';
        case 'TZ':
            return '834';
        case 'TH':
            return '764';
        case 'TL':
            return '626';
        case 'TG':
            return '768';
        case 'TK':
            return '772';
        case 'TO':
            return '776';
        case 'TT':
            return '780';
        case 'TN':
            return '788';
        case 'TR':
            return '792';
        case 'TM':
            return '795';
        case 'TC':
            return '796';
        case 'TV':
            return '798';
        case 'UG':
            return '800';
        case 'UA':
            return '804';
        case 'AE':
            return '784';
        case 'GB':
            return '826';
        case 'UM':
            return '581';
        case 'UY':
            return '858';
        case 'UZ':
            return '860';
        case 'VU':
            return '548';
        case 'VE':
            return '862';
        case 'VN':
            return '704';
        case 'VG':
            return '092';
        case 'VI':
            return '850';
        case 'WF':
            return '876';
        case 'EH':
            return '732';
        case 'YE':
            return '887';
        case 'ZM':
            return '894';
        case 'ZW':
            return '716';
        default:
            return '';
    }
}
// address type is either "po box" or "street address"
function get_address_type($address)
{
    // remove spaces from beginning and end of address
    $address = trim($address);
    // convert address to lowercase characters
    $address = mb_strtolower($address);
    // get first three characters of address
    $first_three_characters = mb_substr($address, 0, 3);
    // get first two characters of address
    $first_two_characters = mb_substr($address, 0, 2);
    // if the address is a po box, then prepare type
    if ((mb_strpos($first_three_characters, 'po ') !== false) || (mb_strpos($first_three_characters, 'p.o') !== false) || (mb_strpos($first_three_characters, 'p,o') !== false) || (mb_strpos($first_three_characters, 'pob') !== false) || (mb_strpos($first_three_characters, 'p o') !== false) || (mb_strpos($first_three_characters, 'p. ') !== false) || (mb_strpos($first_three_characters, 'p b') !== false) || (mb_strpos($first_three_characters, 'rr ') !== false) || (mb_strpos($first_three_characters, 'hc ') !== false) || (mb_strpos($first_three_characters, 'rou') !== false) || (mb_strpos($first_two_characters, 'hc') !== false) || (mb_strpos($first_two_characters, 'rr') !== false) || (mb_strpos($first_two_characters, 'rt') !== false)) {
        $address_type = 'po box';
        // else the address is a street address, so prepare type
    } else {
        $address_type = 'street address';
    }
    return $address_type;
}
function select_rss_field($rss_field = '')
{
    $rss_field_options = '';
    // put rss field types into an array
    $rss_field_options_in_array = array(
        '',
        'category',
        'title',
        'description',
        'media'
    );
    // loop through each rss field type and build options for select list
    foreach ($rss_field_options_in_array as $rss_field_option_in_array) {
        $selected = '';
        // if rss field type is equal to the rss field value from the database, then select the option
        if ($rss_field_option_in_array == $rss_field) {
            $selected = ' selected="selected"';
        }
        if ($rss_field_options != '') {
            $rss_field_option_in_array_for_title = ' (' . $rss_field_option_in_array . ')';
        } else {
            $rss_field_option_in_array_for_title = '-' . lang(array('string' => 'Select {var:1}', 'vars' => lang('Element'))) . '-';
        }

        $rss_field_options .= '<option value="' . h($rss_field_option_in_array) . '"' . $selected . '>' . h(ucwords(lang($rss_field_option_in_array))) . $rss_field_option_in_array_for_title . '</option>';
    }
    return $rss_field_options;
}
// outputs checkboxes with labels for page types
function get_page_type_checkboxes_and_labels($set_page_type_values = array())
{
    $output = '';

    // This is also called for a brand new user, where no values exist yet.  Every key read
    // below is filled in with an empty string, which is the same value the checks treat as
    // "not set yet", so the defaults stay exactly as they were.
    foreach (array(
        'set_page_type_billing_information',
        'set_page_type_calendar_event_view',
        'set_page_type_calendar_view',
        'set_page_type_catalog',
        'set_page_type_catalog_detail',
        'set_page_type_custom_form',
        'set_page_type_custom_form_confirmation',
        'set_page_type_email_a_friend',
        'set_page_type_express_order',
        'set_page_type_folder_view',
        'set_page_type_form_item_view',
        'set_page_type_form_list_view',
        'set_page_type_form_view_directory',
        'set_page_type_order_form',
        'set_page_type_order_preview',
        'set_page_type_order_receipt',
        'set_page_type_photo_gallery',
        'set_page_type_shipping_address_and_arrival',
        'set_page_type_shipping_method',
        'set_page_type_shopping_cart') as $set_page_type_key) {
        if (isset($set_page_type_values[$set_page_type_key]) == false) {
            $set_page_type_values[$set_page_type_key] = '';
        }
    }

    $set_page_type_email_a_friend_checked = '';
    $set_page_type_calendar_style = '';
    // if email a friend is set to blank or if it is turned on, then check it's checkbox
    if (($set_page_type_values['set_page_type_email_a_friend'] == '') || ($set_page_type_values['set_page_type_email_a_friend'] == '1')) {
        $set_page_type_email_a_friend_checked = ' checked="checked"';
    }
    $set_page_type_folder_view_checked = '';
    // If folder view is set to blank or if it is turned on, then check it's checkbox.
    if (($set_page_type_values['set_page_type_folder_view'] == '') || ($set_page_type_values['set_page_type_folder_view'] == '1')) {
        $set_page_type_folder_view_checked = ' checked="checked"';
    }
    $set_page_type_photo_gallery_checked = '';
    // if photo gallery is set to blank or if it is turned on, then check it's checkbox
    if (($set_page_type_values['set_page_type_photo_gallery'] == '') || ($set_page_type_values['set_page_type_photo_gallery'] == '1')) {
        $set_page_type_photo_gallery_checked = ' checked="checked"';
    }
    // output basic page types
    $output .= '<div class="form-check"><input type="checkbox" name="set_page_type_standart" id="set_page_type_standart" value="1" class="form-check-input disabled" checked disabled readonly/><label class="form-check-label" for="set_page_type_standart"> ' . lang('Standard') . '</label></div>

        <div class="form-check"><input type="checkbox" name="set_page_type_email_a_friend" id="set_page_type_email_a_friend" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_email_a_friend_checked . ' /><label class="form-check-label" for="set_page_type_email_a_friend"> ' . lang('Email a Friend') . '</label></div>

        <div class="form-check"><input type="checkbox" name="set_page_type_folder_view" id="set_page_type_folder_view" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_folder_view_checked . ' /><label class="form-check-label" for="set_page_type_folder_view"> ' . lang('Folder View') . '</label></div>

        <div class="form-check"><input type="checkbox" name="set_page_type_photo_gallery" id="set_page_type_photo_gallery" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_photo_gallery_checked . ' /><label class="form-check-label" for="set_page_type_photo_gallery"> ' . lang('Photo Gallery') . '</label></div>';
    // if forms module is active, then display forms page types
    if (FORMS == true) {
        $set_page_type_custom_form_checked = '';
        // if custom form is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_custom_form'] == '') || ($set_page_type_values['set_page_type_custom_form'] == '1')) {
            $set_page_type_custom_form_checked = ' checked="checked"';
        }
        $set_page_type_custom_form_confirmation_checked = '';
        // if custom form confirmation is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_custom_form_confirmation'] == '') || ($set_page_type_values['set_page_type_custom_form_confirmation'] == '1')) {
            $set_page_type_custom_form_confirmation_checked = ' checked="checked"';
        }
        $set_page_type_form_list_view_checked = '';
        // if form list view is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_form_list_view'] == '') || ($set_page_type_values['set_page_type_form_list_view'] == '1')) {
            $set_page_type_form_list_view_checked = ' checked="checked"';
        }
        $set_page_type_form_item_view_checked = '';
        // if form item view is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_form_item_view'] == '') || ($set_page_type_values['set_page_type_form_item_view'] == '1')) {
            $set_page_type_form_item_view_checked = ' checked="checked"';
        }
        $set_page_type_form_view_directory_checked = '';
        // if form view directory is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_form_view_directory'] == '') || ($set_page_type_values['set_page_type_form_view_directory'] == '1')) {
            $set_page_type_form_view_directory_checked = ' checked="checked"';
        }
        $output .= '<div class="form-check"><input type="checkbox" name="set_page_type_custom_form" id="set_page_type_custom_form" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_custom_form_checked . ' /><label class="form-check-label" for="set_page_type_custom_form"> ' . lang('Custom Form') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_custom_form_confirmation" id="set_page_type_custom_form_confirmation" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_custom_form_confirmation_checked . ' /><label class="form-check-label" for="set_page_type_custom_form_confirmation"> ' . lang('Custom Form Confirmation') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_form_list_view" id="set_page_type_form_list_view" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_form_list_view_checked . ' /><label class="form-check-label" for="set_page_type_form_list_view"> ' . lang('Form List View') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_form_item_view" id="set_page_type_form_item_view" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_form_item_view_checked . ' /><label class="form-check-label" for="set_page_type_form_item_view"> ' . lang('Form Item View') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_form_view_directory" id="set_page_type_form_view_directory" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_form_view_directory_checked . ' /><label class="form-check-label" for="set_page_type_form_view_directory"> ' . lang('Form View Directory') . '</label></div>';
    }
    // if calendars module is active and user has access to calendars, then display calendars page types
    if (CALENDARS == true) {
        $set_page_type_calendar_view_checked = '';
        // if calendar view is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_calendar_view'] == '') || ($set_page_type_values['set_page_type_calendar_view'] == '1')) {
            $set_page_type_calendar_view_checked = ' checked="checked"';
        }
        $set_page_type_calendar_event_view_checked = '';
        // if calendar event view is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_calendar_event_view'] == '') || ($set_page_type_values['set_page_type_calendar_event_view'] == '1')) {
            $set_page_type_calendar_event_view_checked = ' checked="checked"';
        }
        $output .= '<div class="form-check" style="' . $set_page_type_calendar_style . '"><input type="checkbox" name="set_page_type_calendar_view" id="set_page_type_calendar_view" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_calendar_view_checked . ' /><label class="form-check-label" for="set_page_type_calendar_view"> ' . lang('Calendar View') . '</label></div>

            <div class="form-check" style="' . $set_page_type_calendar_style . '"><input type="checkbox" name="set_page_type_calendar_event_view" id="set_page_type_calendar_event_view" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_calendar_event_view_checked . ' /><label class="form-check-label" for="set_page_type_calendar_event_view"> ' . lang('Calendar Event View') . '</label></div>';
    }
    // if e-commerce module is active, then display e-commerce page types
    if (ECOMMERCE == true) {
        $set_page_type_catalog_checked = '';
        // if catalog is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_catalog'] == '') || ($set_page_type_values['set_page_type_catalog'] == '1')) {
            $set_page_type_catalog_checked = ' checked="checked"';
        }
        $set_page_type_catalog_detail_checked = '';
        // if catalog detail is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_catalog_detail'] == '') || ($set_page_type_values['set_page_type_catalog_detail'] == '1')) {
            $set_page_type_catalog_detail_checked = ' checked="checked"';
        }
        $set_page_type_express_order_checked = '';
        // if express order is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_express_order'] == '') || ($set_page_type_values['set_page_type_express_order'] == '1')) {
            $set_page_type_express_order_checked = ' checked="checked"';
        }
        $set_page_type_order_form_checked = '';
        // if order form is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_order_form'] == '') || ($set_page_type_values['set_page_type_order_form'] == '1')) {
            $set_page_type_order_form_checked = ' checked="checked"';
        }
        $set_page_type_shopping_cart_checked = '';
        // if shopping cart is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_shopping_cart'] == '') || ($set_page_type_values['set_page_type_shopping_cart'] == '1')) {
            $set_page_type_shopping_cart_checked = ' checked="checked"';
        }
        $set_page_type_shipping_address_and_arrival_checked = '';
        // if shipping address and arrival is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_shipping_address_and_arrival'] == '') || ($set_page_type_values['set_page_type_shipping_address_and_arrival'] == '1')) {
            $set_page_type_shipping_address_and_arrival_checked = ' checked="checked"';
        }
        $set_page_type_shipping_method_checked = '';
        // if shipping method is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_shipping_method'] == '') || ($set_page_type_values['set_page_type_shipping_method'] == '1')) {
            $set_page_type_shipping_method_checked = ' checked="checked"';
        }
        $set_page_type_billing_information_checked = '';
        // if billing information is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_billing_information'] == '') || ($set_page_type_values['set_page_type_billing_information'] == '1')) {
            $set_page_type_billing_information_checked = ' checked="checked"';
        }
        $set_page_type_order_preview_checked = '';
        // if order preview is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_order_preview'] == '') || ($set_page_type_values['set_page_type_order_preview'] == '1')) {
            $set_page_type_order_preview_checked = ' checked="checked"';
        }
        $set_page_type_order_receipt_checked = '';
        // if order receipt is set to blank or if it is turned on, then check it's checkbox
        if (($set_page_type_values['set_page_type_order_receipt'] == '') || ($set_page_type_values['set_page_type_order_receipt'] == '1')) {
            $set_page_type_order_receipt_checked = ' checked="checked"';
        }
        $output .= '<div class="form-check"><input type="checkbox" name="set_page_type_catalog" id="set_page_type_catalog" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_catalog_checked . ' /><label class="form-check-label" for="set_page_type_catalog"> ' . lang('Catalog') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_catalog_detail" id="set_page_type_catalog_detail" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_catalog_detail_checked . ' /><label class="form-check-label" for="set_page_type_catalog_detail"> ' . lang('Catalog Detail') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_express_order" id="set_page_type_express_order" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_express_order_checked . ' /><label class="form-check-label" for="set_page_type_express_order"> ' . lang('Express Order') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_order_form" id="set_page_type_order_form" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_order_form_checked . ' /><label class="form-check-label" for="set_page_type_order_form"> ' . lang('Order Form') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_shopping_cart" id="set_page_type_shopping_cart" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_shopping_cart_checked . ' /><label class="form-check-label" for="set_page_type_shopping_cart"> ' . lang('Shopping Cart') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_shipping_address_and_arrival" id="set_page_type_shipping_address_and_arrival" value="1"' . $set_page_type_shipping_address_and_arrival_checked . ' class="form-check-input  multiselect-checkbox" /><label class="form-check-label" for="set_page_type_shipping_address_and_arrival"> ' . lang('Shipping Address and Arrival') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_shipping_method" id="set_page_type_shipping_method" value="1" class="form-check-input  multiselect-form-check-input  multiselect-checkbox"' . $set_page_type_shipping_method_checked . ' /><label class="form-check-label" for="set_page_type_shipping_method"> ' . lang('Shipping Method') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_billing_information" id="set_page_type_billing_information" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_billing_information_checked . ' /><label class="form-check-label" for="set_page_type_billing_information"> ' . lang('Billing Information') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_order_preview" id="set_page_type_order_preview" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_order_preview_checked . ' /><label class="form-check-label" for="set_page_type_order_preview"> ' . lang('Order Preview') . '</label></div>

            <div class="form-check"><input type="checkbox" name="set_page_type_order_receipt" id="set_page_type_order_receipt" value="1" class="form-check-input  multiselect-checkbox"' . $set_page_type_order_receipt_checked . ' /><label class="form-check-label" for="set_page_type_order_receipt"> ' . lang('Order Receipt') . '</label></div>';
    }
    return $output;
}
// this function is responsible for building options for the filters picklist
function get_filter_options($filters_in_array, $current_filter)
{
    $output = '';
    // loop through each of the filters in the array, and build the picklist options
    foreach ($filters_in_array as $filter_value => $filter_name) {
        $selected = '';
        // if the filter value is equal to the current filter, then select the option
        if ($filter_value == $current_filter) {
            $selected = ' selected="selected"';
        }
        // output the option
        $output .= '<option value="' . $filter_value . '"' . $selected . '>' . $filter_name . '</option>';
    }
    return $output;
}
// Create function that can be used to build the options for a pick list of themes.
function get_theme_options()
{
    $theme_options = array();
    $theme_options[''] = '';
    // Get all themes.
    $themes = db_items("SELECT

            id,

            name

        FROM files

        WHERE

            (type = 'css')

            AND (design = '1')

        ORDER BY name ASC");
    // Loop through the themes in order to prepare options.
    foreach ($themes as $theme) {
        $theme_options[h($theme['name'])] = $theme['id'];
    }
    return $theme_options;
}
