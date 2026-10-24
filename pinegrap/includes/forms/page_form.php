<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page form, shared by the create and the edit screens.
 *
 * Both screens offer the same page type picker and the same per-type options
 * (the "System Options" card). They used to be written out twice, once with
 * the defaults of a new page and once filled from the stored record, and every
 * field had to be kept in step by hand. The markup is now described once and
 * filled from a properties array: add_page.php passes the defaults of a new
 * page for every type, edit_page.php passes what is stored for the page's own
 * type.
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
 * The page type picker and the per-type option rows.
 *
 * Only the rows of $page_type are shown; the others are printed hidden and the
 * picker's change_page_type() script reveals them when another type is chosen.
 *
 * @param string $page_type   The type the form opens on
 * @param string $layout_type 'system' or 'custom'
 * @param array  $properties  Page type name => that type's properties
 *                            (a row of its <type>_pages table, or the
 *                            defaults of a new page). A missing type falls
 *                            back to the defaults of an empty record.
 * @param array  $user        The signed-in user (validate_user())
 * @param int    $page_id     The page being edited, 0 for a new page
 * @return array  'selector'   the page type card,
 *                'properties' the System Options card,
 *                'wysiwyg'    the rich-text editor code for its message fields
 */
function pg_page_form_type_fields($page_type, $layout_type, $properties, $user, $page_id = 0)
{

    $layout_type_row_style = 'display: none';

    $email_a_friend_submit_button_label_row_style = 'display: none';
    $email_a_friend_next_page_id_row_style = 'display: none';
    $folder_view_pages_row_style = 'display: none';
    $folder_view_files_row_style = 'display: none';
    $photo_gallery_number_of_columns_row_style = 'display: none';
    $photo_gallery_thumbnail_max_size_row_style = 'display: none';
    $search_results_search_folder_id_row_style = 'display: none';
    $search_results_search_catalog_items_row_style = 'display: none';
    $update_address_book_address_type_row_style = 'display: none';
    $custom_form_form_name_row_style = 'display: none';
    $custom_form_enabled_row_style = 'display: none';
    $custom_form_quiz_row_style = 'display: none';
    $custom_form_label_column_width_row_style = 'display: none';
    $custom_form_watcher_page_id_row_style = 'display: none';
    $custom_form_save_row_style = 'display: none';
    $custom_form_submit_button_label_row_style = 'display: none';
    $custom_form_auto_registration_row_style = 'display: none';
    $custom_form_hook_code_row_style = 'display: none';
    $custom_form_submitter_email_row_style = 'display: none';
    $custom_form_administrator_email_row_style = 'display: none';
    $custom_form_contact_group_id_row_style = 'display: none';
    $custom_form_membership_row_style = 'display: none';
    $custom_form_private_row_style = 'display: none';
    $custom_form_offer_row_style = 'display: none';
    $custom_form_confirmation_type_row_style = 'display: none';
    $custom_form_return_type_row_style = 'display: none';
    $custom_form_pretty_urls_row_style = 'display: none';
    $custom_form_confirmation_continue_button_label_row_style = 'display: none';
    $custom_form_confirmation_next_page_id_row_style = 'display: none';
    $form_list_view_custom_form_page_id_row_style = 'display: none';
    $form_list_view_form_item_view_page_id_row_style = 'display: none';
    $form_list_view_viewer_filter_row_style = 'display: none';
    $form_item_view_custom_form_page_id_row_style = 'display: none';
    $form_item_view_submitter_security_row_style = 'display: none';
    $form_item_view_submitted_form_editable_by_registered_user_row_style = 'display: none';
    $form_item_view_submitted_form_editable_by_submitter_row_style = 'display: none';
    $form_item_view_hook_code_row_style = 'display: none';
    $form_view_directory_form_list_views_row_style = 'display: none';
    $form_view_directory_summary_row_style = 'display: none';
    $form_view_directory_form_list_view_heading_row_style = 'display: none';
    $form_view_directory_subject_heading_row_style = 'display: none';
    $form_view_directory_number_of_submitted_forms_heading_row_style = 'display: none';
    $calendar_view_calendars_row_style = 'display: none';
    $calendar_view_default_view_row_style = 'display: none';
    $calendar_view_number_of_upcoming_events_row_style = 'display: none';
    $calendar_view_calendar_event_view_page_id_row_style = 'display: none';
    $calendar_event_view_calendars_row_style = 'display: none';
    $calendar_event_view_notes_row_style = 'display: none';
    $calendar_event_view_back_button_label_row_style = 'display: none';
    $catalog_product_group_id_row_style = 'display: none';
    $catalog_menu_row_style = 'display: none';
    $catalog_search_row_style = 'display: none';
    $catalog_number_of_featured_items_row_style = 'display: none';
    $catalog_number_of_new_items_row_style = 'display: none';
    $catalog_number_of_columns_row_style = 'display: none';
    $catalog_image_width_row_style = 'display: none';
    $catalog_image_height_row_style = 'display: none';
    $catalog_back_button_label_row_style = 'display: none';
    $catalog_catalog_detail_page_id_row_style = 'display: none';
    $catalog_detail_allow_customer_to_add_product_to_order_row_style = 'display: none';
    $catalog_detail_back_button_label_row_style = 'display: none';
    $express_order_shopping_cart_label_row_style = 'display: none';
    $express_order_quick_add_label_row_style = 'display: none';
    $express_order_quick_add_product_group_id_row_style = 'display: none';
    $express_order_product_description_type_row_style = 'display: none';
    $express_order_shipping_form_row_style = 'display: none';
    $express_order_special_offer_code_label_row_style = 'display: none';
    $express_order_special_offer_code_message_row_style = 'display: none';
    $express_order_custom_field_1_label_row_style = 'display: none';
    $express_order_custom_field_2_label_row_style = 'display: none';
    $express_order_po_number_row_style = 'display: none';
    $express_order_card_verification_number_page_id_row_style = 'display: none';
    $express_order_offline_payment_always_allowed_row_style = 'display: none';
    $express_order_offline_payment_label_row_style = 'display: none';
    $express_order_terms_page_id_row_style = 'display: none';
    $express_order_update_button_label_row_style = 'display: none';
    $express_order_purchase_now_button_label_row_style = 'display: none';
    $express_order_auto_registration_row_style = 'display: none';
    $express_order_form_row_style = 'display: none';
    $express_order_form_name_row_style = 'display: none';
    $express_order_form_label_column_width_row_style = 'display: none';
    $express_order_pre_save_hook_code_row_style = 'display: none';
    $express_order_post_save_hook_code_row_style = 'display: none';
    $express_order_order_receipt_email_row_style = 'display: none';
    $express_order_next_page_id_row_style = 'display: none';
    $order_form_product_group_id_row_style = 'display: none';
    $order_form_product_layout_row_1_style = 'display: none';
    $order_form_product_layout_row_2_style = 'display: none';
    $order_form_add_button_row_style = 'display: none';
    $order_form_skip_button_row_style = 'display: none';
    $shopping_cart_shopping_cart_label_row_style = 'display: none';
    $shopping_cart_quick_add_label_row_style = 'display: none';
    $shopping_cart_quick_add_product_group_id_row_style = 'display: none';
    $shopping_cart_product_description_type_row_style = 'display: none';
    $shopping_cart_special_offer_code_label_row_style = 'display: none';
    $shopping_cart_special_offer_code_message_row_style = 'display: none';
    $shopping_cart_update_button_label_row_style = 'display: none';
    $shopping_cart_checkout_button_label_row_style = 'display: none';
    $shopping_cart_hook_code_row_style = 'display: none';
    $shopping_cart_next_page_id_with_shipping_row_style = 'display: none';
    $shopping_cart_next_page_id_without_shipping_row_style = 'display: none';
    $shipping_address_and_arrival_address_type_row_style = 'display: none';
    $shipping_address_and_arrival_form_row_style = 'display: none';
    $shipping_address_and_arrival_form_name_row_style = 'display: none';
    $shipping_address_and_arrival_form_label_column_width_row_style = 'display: none';
    $shipping_address_and_arrival_submit_button_row_style = 'display: none';
    $shipping_method_product_description_type_row_style = 'display: none';
    $shipping_method_submit_button_row_style = 'display: none';
    $billing_information_custom_field_1_label_row_style = 'display: none';
    $billing_information_custom_field_2_label_row_style = 'display: none';
    $billing_information_po_number_row_style = 'display: none';
    $billing_information_form_row_style = 'display: none';
    $billing_information_form_name_row_style = 'display: none';
    $billing_information_form_label_column_width_row_style = 'display: none';
    $billing_information_submit_button_label_row_style = 'display: none';
    $billing_information_next_page_id_row_style = 'display: none';
    $order_preview_product_description_type_row_style = 'display: none';
    $order_preview_card_verification_number_page_id_row_style = 'display: none';
    $order_preview_offline_payment_always_allowed_row_style = 'display: none';
    $order_preview_offline_payment_label_row_style = 'display: none';
    $order_preview_terms_page_id_row_style = 'display: none';
    $order_preview_submit_button_label_row_style = 'display: none';
    $order_preview_auto_registration_row_style = 'display: none';
    $order_preview_pre_save_hook_code_row_style = 'display: none';
    $order_preview_post_save_hook_code_row_style = 'display: none';
    $order_preview_order_receipt_email_row_style = 'display: none';
    $order_preview_next_page_id_row_style = 'display: none';
    $order_receipt_product_description_type_row_style = 'display: none';
    $affiliate_sign_up_form_terms_page_id_row_style = 'display: none';
    $affiliate_sign_up_form_submit_button_label_row_style = 'display: none';
    $affiliate_sign_up_form_next_page_id_row_style = 'display: none';
    
    // Only the rows of the page's own type are shown below.
    if (check_if_page_type_supports_layout($page_type)) {
        $layout_type_row_style = '';
    }

    $activate_editors = false;

    // Each of these is only built when its feature is enabled, but all of
    // them are printed below.
    $output_wysiwyg_editor_code = '';
    $output_forms_page_type_properties = '';
    $output_calendars_page_type_properties = '';
    $output_affiliate_page_type_properties = '';

    // Every type's rows are printed whatever the page's type, so each type
    // starts from its own properties or, failing those, an empty record.
    $email_a_friend_properties = $properties['email a friend'] ?? array();
    $folder_view_properties = $properties['folder view'] ?? array();
    $photo_gallery_properties = $properties['photo gallery'] ?? array();
    $search_results_properties = $properties['search results'] ?? array();
    $update_address_book_properties = $properties['update address book'] ?? array();
    $custom_form_properties = $properties['custom form'] ?? array();
    $custom_form_confirmation_properties = $properties['custom form confirmation'] ?? array();
    $form_list_view_properties = $properties['form list view'] ?? array();
    $form_item_view_properties = $properties['form item view'] ?? array();
    $form_view_directory_properties = $properties['form view directory'] ?? array();
    $calendar_view_properties = $properties['calendar view'] ?? array();
    $calendar_event_view_properties = $properties['calendar event view'] ?? array();
    $catalog_properties = $properties['catalog'] ?? array();
    $catalog_detail_properties = $properties['catalog detail'] ?? array();
    $express_order_properties = $properties['express order'] ?? array();
    $order_form_properties = $properties['order form'] ?? array();
    $shopping_cart_properties = $properties['shopping cart'] ?? array();
    $shipping_address_and_arrival_properties = $properties['shipping address and arrival'] ?? array();
    $shipping_method_properties = $properties['shipping method'] ?? array();
    $billing_information_properties = $properties['billing information'] ?? array();
    $order_preview_properties = $properties['order preview'] ?? array();
    $order_receipt_properties = $properties['order receipt'] ?? array();
    $affiliate_sign_up_form_properties = $properties['affiliate sign up form'] ?? array();

    // Set only by the branch for a matching value below, but printed for
    // every page type.
    $custom_form_return_type_custom_form_checked = '';
    $custom_form_return_type_message_checked = '';
    $custom_form_return_type_page_checked = '';
    $calendar_view_calendar_check_boxes = '';
    $calendar_event_view_calendar_check_boxes = '';

    // Show the rows of the page's own type.
    switch($page_type) {
        case 'email a friend':
            $email_a_friend_submit_button_label_row_style = '';
            $email_a_friend_next_page_id_row_style = '';
            
            break;

        case 'folder view':
            $folder_view_pages_row_style = '';
            $folder_view_files_row_style = '';
            
            break;
            
        case 'photo gallery':
            $photo_gallery_number_of_columns_row_style = '';
            $photo_gallery_thumbnail_max_size_row_style = '';
            
            break;
            
        case 'search results':
            $search_results_search_folder_id_row_style = '';
            $search_results_search_catalog_items_row_style = '';
            
            break;
            
        case 'update address book':
            $update_address_book_address_type_row_style = '';

            break;
        
        case 'custom form':
            $custom_form_form_name_row_style = '';
            $custom_form_enabled_row_style = '';
            $custom_form_quiz_row_style = '';
            $custom_form_label_column_width_row_style = '';
            $custom_form_watcher_page_id_row_style = '';
            $custom_form_save_row_style = '';
            $custom_form_submit_button_label_row_style = '';
            $custom_form_auto_registration_row_style = '';
            $custom_form_hook_code_row_style = '';
            $custom_form_submitter_email_row_style = '';
            $custom_form_administrator_email_row_style = '';
            $custom_form_contact_group_id_row_style = '';
            $custom_form_membership_row_style = '';
            $custom_form_private_row_style = '';
            $custom_form_offer_row_style = '';
            $custom_form_confirmation_type_row_style = '';
            
            // If confirmation type is message, then show rows for it.
            if (($custom_form_properties['confirmation_type'] ?? '') != 'page') {
                // Enable rich-text editor.
                $activate_editors = true;
            }

            $custom_form_return_type_row_style = '';
            
            // If return type is message, then show rows for it.
            if (($custom_form_properties['return_type'] ?? '') == 'message') {

                // Enable rich-text editor.
                $activate_editors = true;
            }

            $custom_form_pretty_urls_row_style = '';
            
            break;

        case 'custom form confirmation':
            $custom_form_confirmation_continue_button_label_row_style = '';
            $custom_form_confirmation_next_page_id_row_style = '';
            
            break;
            
        case 'form list view':
            $form_list_view_custom_form_page_id_row_style = '';
            $form_list_view_form_item_view_page_id_row_style = '';
            $form_list_view_viewer_filter_row_style = '';
            
            break;
            
        case 'form item view':
            $form_item_view_custom_form_page_id_row_style = '';
            $form_item_view_submitter_security_row_style = '';
            $form_item_view_submitted_form_editable_by_registered_user_row_style = '';
            $form_item_view_submitted_form_editable_by_submitter_row_style = '';
            $form_item_view_hook_code_row_style = '';
            
            break;
            
        case 'form view directory':
            $form_view_directory_form_list_views_row_style = '';
            $form_view_directory_summary_row_style = '';
            $form_view_directory_form_list_view_heading_row_style = '';
            $form_view_directory_subject_heading_row_style = '';
            $form_view_directory_number_of_submitted_forms_heading_row_style = '';
            
            break;
            
        case 'calendar view':
            $calendar_view_calendars_row_style = '';
            $calendar_view_default_view_row_style = '';
            $calendar_view_calendar_event_view_page_id_row_style = '';
            
            // if the default view is set to upcoming, then display number_of_upcoming_events field
            if (($calendar_view_properties['default_view'] ?? '') == 'upcoming') {
                $calendar_view_number_of_upcoming_events_row_style = '';
            }
            
            break;
            
        case 'calendar event view':
            $calendar_event_view_calendars_row_style = '';
            $calendar_event_view_notes_row_style = '';
            $calendar_event_view_back_button_label_row_style = '';
            
            break;
            
        case 'catalog':
            $catalog_product_group_id_row_style = '';
            $catalog_menu_row_style = '';
            $catalog_search_row_style = '';
            $catalog_number_of_featured_items_row_style = '';
            $catalog_number_of_new_items_row_style = '';
            $catalog_number_of_columns_row_style = '';
            $catalog_image_width_row_style = '';
            $catalog_image_height_row_style = '';
            $catalog_back_button_label_row_style = '';
            $catalog_catalog_detail_page_id_row_style = '';
            
            break;
            
        case 'catalog detail':
            $catalog_detail_allow_customer_to_add_product_to_order_row_style = '';
            $catalog_detail_back_button_label_row_style = '';
            
            break;

        case 'express order':
            $express_order_shopping_cart_label_row_style = '';
            $express_order_quick_add_label_row_style = '';
            $express_order_quick_add_product_group_id_row_style = '';
            $express_order_product_description_type_row_style = '';

            $express_order_shipping_form_row_style = '';

            $express_order_special_offer_code_label_row_style = '';
            $express_order_special_offer_code_message_row_style = '';
            $express_order_custom_field_1_label_row_style = '';
            $express_order_custom_field_2_label_row_style = '';
            $express_order_po_number_row_style = '';

            $express_order_form_row_style = '';
            
            // If the form is enabled, then display its fields.
            if (($express_order_properties['form'] ?? '') == '1') {
                $express_order_form_name_row_style = '';
                $express_order_form_label_column_width_row_style = '';
            }

            $express_order_card_verification_number_page_id_row_style = '';
            $express_order_offline_payment_always_allowed_row_style = '';
            $express_order_offline_payment_label_row_style = '';
            $express_order_terms_page_id_row_style = '';
            $express_order_update_button_label_row_style = '';
            $express_order_purchase_now_button_label_row_style = '';
            $express_order_auto_registration_row_style = '';
            $express_order_pre_save_hook_code_row_style = '';
            $express_order_post_save_hook_code_row_style = '';
            $express_order_order_receipt_email_row_style = '';
            $express_order_next_page_id_row_style = '';
            
            break;

        case 'order form':
            $order_form_product_group_id_row_style = '';
            $order_form_product_layout_row_1_style = '';
            $order_form_product_layout_row_2_style = '';
            $order_form_add_button_row_style = '';
            $order_form_skip_button_row_style = '';
            
            break;

        case 'shopping cart':
            $shopping_cart_shopping_cart_label_row_style = '';
            $shopping_cart_quick_add_label_row_style = '';
            $shopping_cart_quick_add_product_group_id_row_style = '';
            $shopping_cart_product_description_type_row_style = '';
            $shopping_cart_special_offer_code_label_row_style = '';
            $shopping_cart_special_offer_code_message_row_style = '';
            $shopping_cart_update_button_label_row_style = '';
            $shopping_cart_checkout_button_label_row_style = '';
            $shopping_cart_hook_code_row_style = '';
            $shopping_cart_next_page_id_with_shipping_row_style = '';
            $shopping_cart_next_page_id_without_shipping_row_style = '';
            
            break;

        case 'shipping address and arrival':
            $shipping_address_and_arrival_address_type_row_style = '';
            
            $shipping_address_and_arrival_form_row_style = '';
            
            // if the form is enabled, then display its fields
            if (($shipping_address_and_arrival_properties['form'] ?? '') == '1') {
                $shipping_address_and_arrival_form_name_row_style = '';
                $shipping_address_and_arrival_form_label_column_width_row_style = '';
            }
            
            $shipping_address_and_arrival_submit_button_row_style = '';
            
            break;

        case 'shipping method':
            $shipping_method_product_description_type_row_style = '';
            $shipping_method_submit_button_row_style = '';
            
            break;
            
        case 'billing information':
            $billing_information_custom_field_1_label_row_style = '';
            $billing_information_custom_field_2_label_row_style = '';
            $billing_information_po_number_row_style = '';

            $billing_information_form_row_style = '';
            
            // If the form is enabled, then display its fields.
            if (($billing_information_properties['form'] ?? '') == '1') {
                $billing_information_form_name_row_style = '';
                $billing_information_form_label_column_width_row_style = '';
            }

            $billing_information_submit_button_label_row_style = '';
            $billing_information_next_page_id_row_style = '';
            
            break;

        case 'order preview':
            $order_preview_product_description_type_row_style = '';
            $order_preview_card_verification_number_page_id_row_style = '';
            $order_preview_offline_payment_always_allowed_row_style = '';
            $order_preview_offline_payment_label_row_style = '';
            $order_preview_terms_page_id_row_style = '';
            $order_preview_submit_button_label_row_style = '';
            $order_preview_auto_registration_row_style = '';
            $order_preview_pre_save_hook_code_row_style = '';
            $order_preview_post_save_hook_code_row_style = '';
            $order_preview_order_receipt_email_row_style = '';
            $order_preview_next_page_id_row_style = '';
            
            break;

        case 'order receipt':
            $order_receipt_product_description_type_row_style = '';
            
            break;
            
        case 'affiliate sign up form':
            $affiliate_sign_up_form_terms_page_id_row_style = '';
            $affiliate_sign_up_form_submit_button_label_row_style = '';
            $affiliate_sign_up_form_next_page_id_row_style = '';
            
            break;
    }
    
    // Only filled in for the system layout branch below, but always output in the form
    // further down, so they have to be set for the custom layout branch too.
    $layout_type_custom_label_class = '';
    $layout_type_custom_label_title = '';
    $layout_type_custom_option_disabled = '';

    if ($layout_type == 'system') {
        $layout_type_system_checked = ' checked="checked"';
        $layout_type_custom_checked = '';

        // If the user is not an admin or designer, then disable custom layout type option.
        if (USER_ROLE > 1) {
            $layout_type_custom_label_class = ' text-muted';
            $layout_type_custom_label_title = lang('Administrators & Designers are allowed to enable a custom layout type.');
            $layout_type_custom_option_disabled = ' disabled="disabled"';
        }

    } else {
        $layout_type_system_checked = '';
        $layout_type_custom_checked = ' checked="checked"';
    }

    $folder_view_pages_checked = '';

    // If pages are enabled for folder view or if there are not any properties, then check the checkbox
    if ((($folder_view_properties['pages'] ?? '') == '1') || (($folder_view_properties['pages'] ?? '') == '')) {
        $folder_view_pages_checked = ' checked="checked"';
    }

    $folder_view_files_checked = '';

    // If files are enabled for folder view or if there are not any properties, then check the checkbox
    if ((($folder_view_properties['files'] ?? '') == '1') || (($folder_view_properties['files'] ?? '') == '')) {
        $folder_view_files_checked = ' checked="checked"';
    }

    // Setup the photo galleries default number_of_columns value
    if (($photo_gallery_properties['number_of_columns'] ?? '') == '') {
        $photo_gallery_number_of_columns = '4';
    } else {
        $photo_gallery_number_of_columns = ($photo_gallery_properties['number_of_columns'] ?? '');
    }
    
    // Setup the photo galleries default thumbnail max size value
    if (($photo_gallery_properties['thumbnail_max_size'] ?? '') == '') {
        $photo_gallery_thumbnail_max_size = '100';
    } else {
        $photo_gallery_thumbnail_max_size = ($photo_gallery_properties['thumbnail_max_size'] ?? '');
    }
    
    // if the product search is enabled or if there are not any properties, then check the checkbox
    if ((($search_results_properties['search_catalog_items'] ?? '') == '1') || (($search_results_properties['search_catalog_items'] ?? '') == '')) {
        $search_results_search_catalog_items_checked = ' checked="checked"';
    
    // else leave the checkbox unchecked
    } else {
        $search_results_search_catalog_items_checked = '';
    }
    
    // if update address book address type is enabled prepare to check checkbox
    if (($update_address_book_properties['address_type'] ?? '') == 1) {
        $update_address_book_address_type_checked = ' checked="checked"';
    } else {
        $update_address_book_address_type_checked = '';
    }
    
    // if custom form is enabled prepare to check checkbox
    if ((($custom_form_properties['enabled'] ?? '') == 1) || (($custom_form_properties['enabled'] ?? '') == '')) {
        $custom_form_enabled_checked = ' checked="checked"';
    } else {
        $custom_form_enabled_checked = '';
    }
    
    // if custom form quiz is enabled prepare to check checkbox
    if (($custom_form_properties['quiz'] ?? '') == 1) {
        $custom_form_quiz_checked = ' checked="checked"';
    } else {
        $custom_form_quiz_checked = '';
    }

    // If save is enabled, then check check box.
    if (($custom_form_properties['save'] ?? '') == 1) {
        $custom_form_save_checked = ' checked="checked"';
    } else {
        $custom_form_save_checked = '';
    }

    // If auto-registration is enabled, then check check box.
    if (($custom_form_properties['auto_registration'] ?? '') == 1) {
        $custom_form_auto_registration_checked = ' checked="checked"';
    } else {
        $custom_form_auto_registration_checked = '';
    }

    // Double opt-in: drawn only once the 11.0.27 column is there, inside the
    // contact group row so it shows and hides with it.
    $custom_form_double_opt_in_switch = '';

    if (pg_fs_ready()) {
        $custom_form_double_opt_in_switch =
            '<div class="form-check form-switch mt-2">
                <input type="checkbox" class="form-check-input" role="switch" id="custom_form_double_opt_in" name="custom_form_double_opt_in" value="1"' . ((($custom_form_properties['double_opt_in'] ?? '') == 1) ? ' checked="checked"' : '') . ' />
                <label class="form-check-label" for="custom_form_double_opt_in">' . h(lang('Ask for confirmation by e-mail before adding to the group (double opt-in)')) . '</label>
            </div>
            <div class="form-text">' . h(lang('The sender gets a link at the address of their contact record and joins the group only after pressing the button behind it.')) . ' ' . h(lang('Say in the message after submission that a confirmation e-mail is on its way: the form does not say it by itself.')) . '</div>';
    }

    // if submitter e-mail is enabled prepare to check check box
    if (($custom_form_properties['submitter_email'] ?? '') == 1) {
        $custom_form_submitter_email_checked = ' checked="checked"';
    } else {
        $custom_form_submitter_email_checked = '';
    }

    // if submitter e-mail format is set to plain text, then check the plain text radio button
    if (($custom_form_properties['submitter_email_format'] ?? '') == 'plain_text') {
        $custom_form_submitter_email_format_plain_text_checked = ' checked="checked"';
        $custom_form_submitter_email_format_html_checked = '';

    // else submitter e-mail format is set to HTML, so check the html radio button
    } else {
        $custom_form_submitter_email_format_plain_text_checked = '';
        $custom_form_submitter_email_format_html_checked = ' checked="checked"';
    }

    // if administrator e-mail is enabled prepare to check check box
    if (($custom_form_properties['administrator_email'] ?? '') == 1) {
        $custom_form_administrator_email_checked = ' checked="checked"';
    } else {
        $custom_form_administrator_email_checked = '';
    }

    // if administrator e-mail format is set to plain text, then check the plain text radio button
    if (($custom_form_properties['administrator_email_format'] ?? '') == 'plain_text') {
        $custom_form_administrator_email_format_plain_text_checked = ' checked="checked"';
        $custom_form_administrator_email_format_html_checked = '';

    // else administrator e-mail format is set to HTML, so check the html radio button
    } else {
        $custom_form_administrator_email_format_plain_text_checked = '';
        $custom_form_administrator_email_format_html_checked = ' checked="checked"';
    }
    
    // if custom form membership is enabled prepare to check checkbox
    if (($custom_form_properties['membership'] ?? '') == 1) {
        $custom_form_membership_checked = ' checked="checked"';
    } else {
        $custom_form_membership_checked = '';
    }

    if (($custom_form_properties['private'] ?? '') == 1) {
        $custom_form_private_checked = ' checked="checked"';
    } else {
        $custom_form_private_checked = '';
    }

    // If private days is 0, then change value to an empty string,
    // so that a 0 does not appear in the field.
    if (($custom_form_properties['private_days'] ?? '') == 0) {
        $custom_form_properties['private_days'] = '';
    }

    if (($custom_form_properties['offer'] ?? '') == 1) {
        $custom_form_offer_checked = ' checked="checked"';
    } else {
        $custom_form_offer_checked = '';
    }

    // If offer days is 0, then change value to an empty string,
    // so that a 0 does not appear in the field.
    if (($custom_form_properties['offer_days'] ?? '') == 0) {
        $custom_form_properties['offer_days'] = '';
    }

    $custom_form_offer_eligibility_everyone = '';
    $custom_form_offer_eligibility_new_contacts = '';
    $custom_form_offer_eligibility_existing_contacts = '';

    switch (($custom_form_properties['offer_eligibility'] ?? '')) {
        case 'everyone':
            $custom_form_offer_eligibility_everyone = ' selected="selected"';
            break;

        case 'new_contacts':
            $custom_form_offer_eligibility_new_contacts = ' selected="selected"';
            break;

        case 'existing_contacts':
            $custom_form_offer_eligibility_existing_contacts = ' selected="selected"';
            break;
    }

    // If confirmation type is not set to page, then select the message radio
    // button: a submission without a next page ends on the message
    // (pg_cf_confirmation_shows_message()).
    if (($custom_form_properties['confirmation_type'] ?? '') != 'page') {
        $custom_form_confirmation_type_message_checked = ' checked="checked"';
        $custom_form_confirmation_type_page_checked = '';

    // Otherwise confirmation type is set to page, so select the page radio button.
    } else {
        $custom_form_confirmation_type_message_checked = '';
        $custom_form_confirmation_type_page_checked = ' checked="checked"';
    }

    // If confirmation alternative page is enabled then check checkbox.
    if (($custom_form_properties['confirmation_alternative_page'] ?? '') == 1) {
        $custom_form_confirmation_alternative_page_checked = ' checked="checked"';
    } else {
        $custom_form_confirmation_alternative_page_checked = '';
    }

    // Select the correct radio button for return type.
    switch (($custom_form_properties['return_type'] ?? '')) {
        case 'custom_form':
            $custom_form_return_type_custom_form_checked = ' checked="checked"';
            $custom_form_return_type_message_checked = '';
            $custom_form_return_type_page_checked = '';
            break;

        case 'message':
            $custom_form_return_type_custom_form_checked = '';
            $custom_form_return_type_message_checked = ' checked="checked"';
            $custom_form_return_type_page_checked = '';
            break;

        case 'page':
            $custom_form_return_type_custom_form_checked = '';
            $custom_form_return_type_message_checked = '';
            $custom_form_return_type_page_checked = ' checked="checked"';
            break;
    }

    // If return alternative page is enabled then check checkbox.
    if (($custom_form_properties['return_alternative_page'] ?? '') == 1) {
        $custom_form_return_alternative_page_checked = ' checked="checked"';
    } else {
        $custom_form_return_alternative_page_checked = '';
    }

    // If pretty urls is enabled then check checkbox.
    if (($custom_form_properties['pretty_urls'] ?? '') == 1) {
        $custom_form_pretty_urls_checked = ' checked="checked"';
    } else {
        $custom_form_pretty_urls_checked = '';
    }

    $form_list_view_viewer_filter_checked = '';

    if (($form_list_view_properties['viewer_filter'] ?? '') == 1) {
        $form_list_view_viewer_filter_checked = ' checked="checked"';
    }

    $form_list_view_viewer_filter_submitter_checked = '';

    if ((($form_list_view_properties['viewer_filter_submitter'] ?? '') == 1) || (($form_list_view_properties['viewer_filter'] ?? '') == 0)) {
        $form_list_view_viewer_filter_submitter_checked = ' checked="checked"';
    }

    $form_list_view_viewer_filter_watcher_checked = '';

    if ((($form_list_view_properties['viewer_filter_watcher'] ?? '') == 1) || (($form_list_view_properties['viewer_filter'] ?? '') == 0)) {
        $form_list_view_viewer_filter_watcher_checked = ' checked="checked"';
    }

    $form_list_view_viewer_filter_editor_checked = '';

    if ((($form_list_view_properties['viewer_filter_editor'] ?? '') == 1) || (($form_list_view_properties['viewer_filter'] ?? '') == 0)) {
        $form_list_view_viewer_filter_editor_checked = ' checked="checked"';
    }

    // if form item view submitter security is enabled then prepare to check checkbox
    if (($form_item_view_properties['submitter_security'] ?? '') == 1) {
        $form_item_view_submitter_security_checked = ' checked="checked"';
    } else {
        $form_item_view_submitter_security_checked = '';
    }
    
    // if form item view allows registered users to edit form submissions, prepare to check checkbox
    if (($form_item_view_properties['submitted_form_editable_by_registered_user'] ?? '') == 1) {
        $form_item_view_submitted_form_editable_by_registered_user_checked = ' checked="checked"';
        $form_item_view_submitted_form_editable_by_submitter_row_style = 'display: none';
    } else {
        $form_item_view_submitted_form_editable_by_registered_user_checked = '';
    }
    
    // if form item view allows users to edit their own submissions, prepare to check checkbox
    if (($form_item_view_properties['submitted_form_editable_by_submitter'] ?? '') == 1) {
        $form_item_view_submitted_form_editable_by_submitter_checked = ' checked="checked"';
    } else {
        $form_item_view_submitted_form_editable_by_submitter_checked = '';
    }
    
    // if display is enabled for form view directory, prepare to check checkbox
    if (($form_view_directory_properties['summary'] ?? '') == 1) {
        $form_view_directory_summary_checked = ' checked="checked"';
    } else {
        $form_view_directory_summary_checked = '';
    }
    
    // if form view directory's summary date range is blank, then set it to the default value
    if (($form_view_directory_properties['summary_days'] ?? '') == '') {
        $form_view_directory_summary_days = '30';
        
    // else the form view directory's summary date range is not blank, so set it to the saved value
    } else {
        $form_view_directory_summary_days = ($form_view_directory_properties['summary_days'] ?? '');
    }
    
    // if form view directory's summary maximum number of results is blank, then set it to the default value
    if (($form_view_directory_properties['summary_maximum_number_of_results'] ?? '') == '') {
        $form_view_directory_summary_maximum_number_of_results = '5';
        
    // else the form view directory's summary maximum number of results is not blank, so set it to the saved value
    } else {
        $form_view_directory_summary_maximum_number_of_results = ($form_view_directory_properties['summary_maximum_number_of_results'] ?? '');
    }
    
    // if form view directory's form list view heading is blank, then set it to the default value
    if (($form_view_directory_properties['form_list_view_heading'] ?? '') == '') {
        $form_view_directory_form_list_view_heading = lang('Forum');
        
    // else the form view directory's form list view heading is not blank, so set it to the saved value
    } else {
        $form_view_directory_form_list_view_heading = ($form_view_directory_properties['form_list_view_heading'] ?? '');
    }
    
    // if form view directory's subject heading is blank, then set it to the default value
    if (($form_view_directory_properties['subject_heading'] ?? '') == '') {
        $form_view_directory_subject_heading = 'Subject';
        
    // else the form view directory's subject heading is not blank, so set it to the saved value
    } else {
        $form_view_directory_subject_heading = ($form_view_directory_properties['subject_heading'] ?? '');
    }
    
    // if form view directory's number of submitted forms heading is blank, then set it to the default value
    if (($form_view_directory_properties['number_of_submitted_forms_heading'] ?? '') == '') {
        $form_view_directory_number_of_submitted_forms_heading = lang('Forms');
        
    // else the form view directory's number of submitted forms heading is not blank, so set it to the saved value
    } else {
        $form_view_directory_number_of_submitted_forms_heading = ($form_view_directory_properties['number_of_submitted_forms_heading'] ?? '');
    }
    
    // if calendar view's default view is set to weekly, then prepare to select option
    if (($calendar_view_properties['default_view'] ?? '') == 'weekly') {
        $calendar_view_default_view_monthly = '';
        $calendar_view_default_view_weekly = ' selected="selected"';
        $calendar_view_default_view_upcoming = '';

    // else if calendar view's default view is set to upcoming, then prepare to select option
    } elseif (($calendar_view_properties['default_view'] ?? '') == 'upcoming') {
        $calendar_view_default_view_monthly = '';
        $calendar_view_default_view_weekly = '';
        $calendar_view_default_view_upcoming = ' selected="selected"';

    // else calendar view's default view is empty or is monthly, so prepare to select option
    } else {
        $calendar_view_default_view_monthly = ' selected="selected"';
        $calendar_view_default_view_weekly = '';
        $calendar_view_default_view_upcoming = '';
    }
    
    // Setup the calendar views default number_of_upcoming_events value
    if (($calendar_view_properties['number_of_upcoming_events'] ?? '') == '') {
        $calendar_view_number_of_upcoming_events_value = '5';
    } else {
        $calendar_view_number_of_upcoming_events_value = ($calendar_view_properties['number_of_upcoming_events'] ?? '');
    }
    
    // If the catalog menu is on, or if the original page type was not a catalog page then check the menu checkbox
    if ((($catalog_properties['menu'] ?? '') == '1') || (($catalog_properties['menu'] ?? '') == '')) {
        $catalog_menu_checked = ' checked="checked"';
        
    // else do not check the checkbox
    } else {
        $catalog_menu_checked = '';
    }
    
    // If the catalog search is on, or if the original page type was not a catalog page then check the search checkbox
    if ((($catalog_properties['search'] ?? '') == '1') || (($catalog_properties['search'] ?? '') == '')) {
        $catalog_search_checked = ' checked="checked"';
        
    // else do not check the checkbox
    } else {
        $catalog_search_checked = '';
    }
    
    // set the default number_of_columns value for the catalog page type
    if (($catalog_properties['number_of_columns'] ?? '') == '') {
        $catalog_number_of_columns = '4';
    } else {
        $catalog_number_of_columns = ($catalog_properties['number_of_columns'] ?? '');
    }
    
    // set the default image width value for the catalog page type
    if (($catalog_properties['image_width'] ?? '') == '') {
        $catalog_image_width = '50';
    } else {
        $catalog_image_width = ($catalog_properties['image_width'] ?? '');
    }
    
    // set the default image height value for the catalog page type
    if (($catalog_properties['image_height'] ?? '') == '') {
        $catalog_image_height = '50';
    } else {
        $catalog_image_height = ($catalog_properties['image_height'] ?? '');
    }
    
    // If allow_customer_to_add_product_to_order is on, or if the original page type was not a catalog detail page then check the allow_customer_to_add_product_to_order checkbox
    if ((($catalog_detail_properties['allow_customer_to_add_product_to_order'] ?? '') == '1') || (($catalog_detail_properties['allow_customer_to_add_product_to_order'] ?? '') == '')) {
        $catalog_detail_allow_customer_to_add_product_to_order_checked = ' checked="checked"';
        
    // else do not check the checkbox
    } else {
        $catalog_detail_allow_customer_to_add_product_to_order_checked = '';
    }

    // if product description type is set to full description, then check that radio button
    if (($express_order_properties['product_description_type'] ?? '') == 'full_description') {
        $express_order_product_description_type_full_description_checked = ' checked="checked"';
        $express_order_product_description_type_short_description_checked = '';

    // else product description type is set to short description, so check that radio button
    } else {
        $express_order_product_description_type_full_description_checked = '';
        $express_order_product_description_type_short_description_checked = ' checked="checked"';
    }

    $express_order_shipping_form_checked = '';

    if (($express_order_properties['shipping_form'] ?? '')) {
        $express_order_shipping_form_checked = ' checked="checked"';
    }
    
    // if update button label is empty, then prepare default value
    if (!($express_order_properties['update_button_label'] ?? '')) {
        // if a shopping cart label is found, then use that with "Update" in front of the label
        if (($express_order_properties['shopping_cart_label'] ?? '')) {
            $express_order_properties['update_button_label'] = 'Update ' . h(($express_order_properties['shopping_cart_label'] ?? ''));
            
        // else a shopping cart label could not be found, so just use a default label
        } else {
            $express_order_properties['update_button_label'] = 'Update Cart';
        }
    }
    
    // if express order custom field 1 required is enabled prepare to check checkbox
    if (($express_order_properties['custom_field_1_required'] ?? '') == 1) {
        $express_order_custom_field_1_required_checked = ' checked="checked"';
    } else {
        $express_order_custom_field_1_required_checked = '';
    }
    
    // if express order custom field 2 required is enabled prepare to check checkbox
    if (($express_order_properties['custom_field_2_required'] ?? '') == 1) {
        $express_order_custom_field_2_required_checked = ' checked="checked"';
    } else {
        $express_order_custom_field_2_required_checked = '';
    }
    
    // if express order po number is enabled prepare to check checkbox
    if (($express_order_properties['po_number'] ?? '') == 1) {
        $express_order_po_number_checked = ' checked="checked"';
    } else {
        $express_order_po_number_checked = '';
    }

    $express_order_form_checked = '';

    if (($express_order_properties['form'] ?? '') == 1) {
        $express_order_form_checked = ' checked="checked"';
    }

    // If offline payment is always allowed then prepare to check check box.
    if (($express_order_properties['offline_payment_always_allowed'] ?? '') == 1) {
        $express_order_offline_payment_always_allowed_checked = ' checked="checked"';
    } else {
        $express_order_offline_payment_always_allowed_checked = '';
    }

    // If auto-registration is enabled, then check check box.
    if (($express_order_properties['auto_registration'] ?? '') == 1) {
        $express_order_auto_registration_checked = ' checked="checked"';
    } else {
        $express_order_auto_registration_checked = '';
    }
    
    // if order receipt e-mail is enabled prepare to check checkbox
    if (($express_order_properties['order_receipt_email'] ?? '') == 1) {
        $express_order_order_receipt_email_checked = ' checked="checked"';
    } else {
        $express_order_order_receipt_email_checked = '';
    }

    // if order receipt format is set to "plain text", then check the plain text radio button
    if (($express_order_properties['order_receipt_email_format'] ?? '') == 'plain_text') {
        $express_order_order_receipt_email_format_plain_text_checked = ' checked="checked"';
        $express_order_order_receipt_email_format_html_checked = '';

    // else order receipt format is set to "HTML", so check the html radio button
    } else {
        $express_order_order_receipt_email_format_plain_text_checked = '';
        $express_order_order_receipt_email_format_html_checked = ' checked="checked"';
    }

    // if product layout is set to drop-down selection, prepare checked value for radio buttons
    if (($order_form_properties['product_layout'] ?? '') == 'drop-down selection') {
        $order_form_product_layout_list = '';
        $order_form_product_layout_drop_down_selection = ' checked="checked"';

    // else product layout is empty or is list, so prepare checked value for radio buttons
    } else {
        $order_form_product_layout_list = ' checked="checked"';
        $order_form_product_layout_drop_down_selection = '';
    }
    
    // if submit button label is empty, then prepare default value
    if (!($order_form_properties['add_button_label'] ?? '')) {
        $order_form_properties['add_button_label'] = 'Continue';
    }

    // if product description type is set to full description, then check that radio button
    if (($shopping_cart_properties['product_description_type'] ?? '') == 'full_description') {
        $shopping_cart_product_description_type_full_description_checked = ' checked="checked"';
        $shopping_cart_product_description_type_short_description_checked = '';

    // else product description type is set to short description, so check that radio button
    } else {
        $shopping_cart_product_description_type_full_description_checked = '';
        $shopping_cart_product_description_type_short_description_checked = ' checked="checked"';
    }
    
    // if update button label is empty, then prepare default value
    if (!($shopping_cart_properties['update_button_label'] ?? '')) {
        // if a shopping cart label is found, then use that with "Update" in front of the label
        if (($shopping_cart_properties['shopping_cart_label'] ?? '')) {
            $shopping_cart_properties['update_button_label'] = 'Update ' . h(($shopping_cart_properties['shopping_cart_label'] ?? ''));
            
        // else a shopping cart label could not be found, so just use a default label
        } else {
            $shopping_cart_properties['update_button_label'] = 'Update Cart';
        }
    }
    
    // if checkout button label is empty, then prepare default value
    if (!($shopping_cart_properties['checkout_button_label'] ?? '')) {
        $shopping_cart_properties['checkout_button_label'] = 'Checkout';
    }
    
    // if shipping address and arrival address type is enabled prepare to check checkbox
    if (($shipping_address_and_arrival_properties['address_type'] ?? '') == 1) {
        $shipping_address_and_arrival_address_type_checked = ' checked="checked"';
    } else {
        $shipping_address_and_arrival_address_type_checked = '';
    }
    
    // if shipping address and arrival form is enabled prepare to check checkbox
    if (($shipping_address_and_arrival_properties['form'] ?? '') == 1) {
        $shipping_address_and_arrival_form_checked = ' checked="checked"';
    } else {
        $shipping_address_and_arrival_form_checked = '';
    }

    // if product description type is set to full description, then check that radio button
    if (($shipping_method_properties['product_description_type'] ?? '') == 'full_description') {
        $shipping_method_product_description_type_full_description_checked = ' checked="checked"';
        $shipping_method_product_description_type_short_description_checked = '';

    // else product description type is set to short description, so check that radio button
    } else {
        $shipping_method_product_description_type_full_description_checked = '';
        $shipping_method_product_description_type_short_description_checked = ' checked="checked"';
    }
    
    // if billing information custom field 1 required is enabled prepare to check checkbox
    if (($billing_information_properties['custom_field_1_required'] ?? '') == 1) {
        $billing_information_custom_field_1_required_checked = ' checked="checked"';
    } else {
        $billing_information_custom_field_1_required_checked = '';
    }
    
    // if billing information custom field 2 required is enabled prepare to check checkbox
    if (($billing_information_properties['custom_field_2_required'] ?? '') == 1) {
        $billing_information_custom_field_2_required_checked = ' checked="checked"';
    } else {
        $billing_information_custom_field_2_required_checked = '';
    }
    
    // if billing information po number is enabled prepare to check checkbox
    if (($billing_information_properties['po_number'] ?? '') == 1) {
        $billing_information_po_number_checked = ' checked="checked"';
    } else {
        $billing_information_po_number_checked = '';
    }

    $billing_information_form_checked = '';

    if (($billing_information_properties['form'] ?? '') == 1) {
        $billing_information_form_checked = ' checked="checked"';
    }

    // if product description type is set to full description, then check that radio button
    if (($order_preview_properties['product_description_type'] ?? '') == 'full_description') {
        $order_preview_product_description_type_full_description_checked = ' checked="checked"';
        $order_preview_product_description_type_short_description_checked = '';

    // else product description type is set to short description, so check that radio button
    } else {
        $order_preview_product_description_type_full_description_checked = '';
        $order_preview_product_description_type_short_description_checked = ' checked="checked"';
    }

    // If offline payment is always allowed then prepare to check check box.
    if (($order_preview_properties['offline_payment_always_allowed'] ?? '') == 1) {
        $order_preview_offline_payment_always_allowed_checked = ' checked="checked"';
    } else {
        $order_preview_offline_payment_always_allowed_checked = '';
    }

    // If auto-registration is enabled, then check check box.
    if (($order_preview_properties['auto_registration'] ?? '') == 1) {
        $order_preview_auto_registration_checked = ' checked="checked"';
    } else {
        $order_preview_auto_registration_checked = '';
    }

    // if order receipt e-mail is enabled prepare to check checkbox
    if (($order_preview_properties['order_receipt_email'] ?? '') == 1) {
        $order_preview_order_receipt_email_checked = ' checked="checked"';
    } else {
        $order_preview_order_receipt_email_checked = '';
    }

    // if order receipt e-mail format is set to "plain text", then check the plain text radio button
    if (($order_preview_properties['order_receipt_email_format'] ?? '') == 'plain_text') {
        $order_preview_order_receipt_email_format_plain_text_checked = ' checked="checked"';
        $order_preview_order_receipt_email_format_html_checked = '';

    // else order receipt format is set to "HTML", so check the html radio button
    } else {
        $order_preview_order_receipt_email_format_plain_text_checked = '';
        $order_preview_order_receipt_email_format_html_checked = ' checked="checked"';
    }

    // if product description type is set to full description, then check that radio button
    if (($order_receipt_properties['product_description_type'] ?? '') == 'full_description') {
        $order_receipt_product_description_type_full_description_checked = ' checked="checked"';
        $order_receipt_product_description_type_short_description_checked = '';

    // else product description type is set to short description, so check that radio button
    } else {
        $order_receipt_product_description_type_full_description_checked = '';
        $order_receipt_product_description_type_short_description_checked = ' checked="checked"';
    }
    
    // if submit button label is empty, then prepare default value. A new
    // page keeps it empty: the sign up screen then shows its own translated
    // label, where this one would be stored and shown in English.
    if (
        $page_id
        && !($affiliate_sign_up_form_properties['submit_button_label'] ?? '')
    ) {
        $affiliate_sign_up_form_properties['submit_button_label'] = 'Sign Up';
    }
    
    $output_search_results_page_type_properties = '';

    // If advanced site search is enabled then output row for folder pick list
    // for search results properties.
    if (SEARCH_TYPE == 'advanced') {
        $output_search_results_page_type_properties .=
            '<div class="col-12 col-md-12 col-lg-6 my-2" id="search_results_search_folder_id_row" style="' . $search_results_search_folder_id_row_style . '">
                <label for="search_results_search_folder_id" class="form-label">' . lang('Search Folder') . '</label>
                <select name="search_results_search_folder_id" id="search_results_search_folder_id" class="form-select"  >' . select_folder(($search_results_properties['search_folder_id'] ?? '')) . '</select>
            </div>';
    }

    $output_ecommerce_page_type_properties = '';
    
    if (ECOMMERCE == true) {
        // if the user is an advanced user then prepare to output search results page type properties
        if ($user['role'] < 3) {
            $output_search_results_page_type_properties .=
                '<div class="col-12 my-2" id="search_results_search_catalog_items_row" style="' . $search_results_search_catalog_items_row_style . '">
                    <div class="form-check form-switch">
                        <input value="1" id="search_results_search_catalog_items" name="search_results_search_catalog_items" class="form-check-input collapse-switcher" type="checkbox" role="switch"' . $search_results_search_catalog_items_checked . ' data-bs-target="#search_catalog_items_options_row" />
                        <label class="form-check-label" for="search_results_search_catalog_items">' . lang('Search Products') . '</label>
                    </div>
                    <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="search_catalog_items_options_row">
                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                        <div class="popover-body">
                            <div class="row">
                                <div class="col-12 col-md-12 col-lg-6 my-1">
                                    <label for="search_results_product_group_id" class="form-label">' . lang('In Product Group') . '</label>
                                    <select name="search_results_product_group_id" id="search_results_product_group_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Product Group')) )) . '-</option>' . get_product_group_options(($search_results_properties['product_group_id'] ?? ''),  $parent_product_group_id = 0, $excluded_product_group_id = 0, $level = 0, $product_groups = array(), $include_select_product_groups = FALSE, 'text', false, true, false) . '</select>
                                    <div class="form-text">' . lang('leave unselected for all product groups') . '</div>
                                </div>
                                <div class="col-12 col-md-12 col-lg-6 my-1">
                                    <label for="search_results_catalog_detail_page_id" class="form-label">' . lang('Catalog Detail Page') . '</label>
                                    <select name="search_results_catalog_detail_page_id" id="search_results_catalog_detail_page_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($search_results_properties['catalog_detail_page_id'] ?? ''), 'catalog detail') . '</select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        $output_express_order_offline_payment_rows = '';
        $output_express_order_offline_payment_checkbox_rows = '';
        $output_order_preview_offline_payment_rows = '';
        $output_order_preview_offline_payment_checkbox_rows = '';
        
        // if allow offline orders is on, then output offline payment label
        if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
            $output_express_order_offline_payment_rows = 
            '<div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_offline_payment_label_row" style="' . $express_order_offline_payment_label_row_style . '">
                <label class="form-label" for="express_order_offline_payment_label">'. lang('Offline Payment Label') . '</label>
                <input value="' . h(($express_order_properties['offline_payment_label'] ?? '')) . '" type="text" id="express_order_offline_payment_label" name="express_order_offline_payment_label" class="form-control" maxlength="255" >
            </div>';
            $output_express_order_offline_payment_checkbox_rows = 
            '<div class="col-12 my-2" id="express_order_offline_payment_always_allowed_row" style="' . $express_order_offline_payment_always_allowed_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="express_order_offline_payment_always_allowed" id="express_order_offline_payment_always_allowed" value="1"' . $express_order_offline_payment_always_allowed_checked . ' />
                    <label class="form-check-label" for="express_order_offline_payment_always_allowed">' . lang('Always Allow Offline Payments') . '</label>
                </div>
            </div>';
            
            $output_order_preview_offline_payment_rows = 
            '<div class="col-12 col-sm-6 col-lg-4 my-2" id="order_preview_offline_payment_label_row" style="' . $order_preview_offline_payment_label_row_style . '">
                <label class="form-label" for="order_preview_offline_payment_label">'. lang('Offline Payment Label') . '</label>
                <input value="' . h(($order_preview_properties['offline_payment_label'] ?? '')) . '" type="text" id="order_preview_offline_payment_label" name="order_preview_offline_payment_label" class="form-control" maxlength="255" >
            </div>';
            $output_order_preview_offline_payment_checkbox_rows = 
            '<div class="col-12 my-2" id="order_preview_offline_payment_always_allowed_row" style="' . $order_preview_offline_payment_always_allowed_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="order_preview_offline_payment_always_allowed" id="order_preview_offline_payment_always_allowed" value="1"' . $order_preview_offline_payment_always_allowed_checked . ' />
                    <label class="form-check-label" for="order_preview_offline_payment_always_allowed">' . lang('Always Allow Offline Payments') . '</label>
                </div>
            </div>';
        }
        
        $output_ecommerce_page_type_properties =
            '<div class="col-12 col-md-6 my-2" id="catalog_product_group_id_row" style="' . $catalog_product_group_id_row_style . '">
                <label for="catalog_product_group_id" class="form-label">' . lang('Product Group') . '</label>
                <select name="catalog_product_group_id" id="catalog_product_group_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options(($catalog_properties['product_group_id'] ?? ''), $parent_product_group_id = 0, $excluded_product_group_id = 0, $level = 0, $product_groups = array(), $include_select_product_groups = FALSE, 'text', false, true, false) . '</select>
                <div class="form-text text-end">' . lang('leave unselected for all product groups') . '</div>
            </div>
            <div class="col-12 my-2" id="catalog_menu_row" style="' . $catalog_menu_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="catalog_menu" name="catalog_menu" value="1"' . $catalog_menu_checked . '>
                    <label class="form-check-label" for="catalog_menu">' . lang('Enable Menu') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="catalog_search_row" style="' . $catalog_search_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="catalog_search" name="catalog_search" value="1"' . $catalog_search_checked . '>
                    <label class="form-check-label" for="catalog_search">' . lang('Enable Search') . '</label>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_number_of_featured_items_row" style="' . $catalog_number_of_featured_items_row_style . '">
                <label for="catalog_number_of_featured_items" class="form-label">' . lang('Number of Featured Items') . '</label>
                <input value="' . h($catalog_properties['number_of_featured_items'] ?? '') . '" type="text" name="catalog_number_of_featured_items" id="catalog_number_of_featured_items" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_number_of_new_items_row" style="' . $catalog_number_of_new_items_row_style . '">
                <label for="catalog_number_of_new_items" class="form-label">' . lang('Number of New Items') . '</label>
                <input value="' . h($catalog_properties['number_of_new_items'] ?? '') . '" type="text" name="catalog_number_of_new_items" id="catalog_number_of_new_items" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_number_of_columns_row" style="' . $catalog_number_of_columns_row_style . '">
                <label for="catalog_number_of_columns" class="form-label">' . lang('Number of Columns') . '</label>
                <input value="' . h($catalog_number_of_columns) . '" type="text" name="catalog_number_of_columns" id="catalog_number_of_columns" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
            </div>
            <div class="col-12">
                <div class="row">
                    <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_image_width_row" style="' . $catalog_image_width_row_style . '">
                        <label for="catalog_image_width" class="form-label">' . lang('Image Width') . '</label>
                        <div class="input-group my-2">
                            <input value="' . h($catalog_image_width) . '" type="text" name="catalog_image_width" id="catalog_image_width" maxlength="4" class="form-control text-end" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                            <label class="input-group-text" for="catalog_image_width">' . lang('pixels') . '</label>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_image_height_row" style="' . $catalog_image_height_row_style . '">
                        <label for="catalog_image_height" class="form-label">' . lang('Image Height') . '</label>
                        <div class="input-group my-2">
                            <input value="' . h($catalog_image_height) . '" type="text" name="catalog_image_height" id="catalog_image_height" maxlength="4" class="form-control text-end" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                            <label class="input-group-text" for="catalog_image_height">' . lang('pixels') . '</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_back_button_label_row" style="' . $catalog_back_button_label_row_style . '">
                <label for="catalog_back_button_label" class="form-label">' . lang('Back Button Label') . '</label>
                <input value="' . h($catalog_properties['back_button_label'] ?? '') . '" type="text" name="catalog_back_button_label" id="catalog_back_button_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_catalog_detail_page_id_row" style="' . $catalog_catalog_detail_page_id_row_style . '">
                <label for="catalog_catalog_detail_page_id" class="form-label">' . lang('Catalog Detail Page') . '</label>
                <select class="form-select" id="catalog_catalog_detail_page_id" name="catalog_catalog_detail_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>lang('Page') )) . '-</option>' . select_page(($catalog_properties['catalog_detail_page_id'] ?? ''), 'catalog detail') . '</select>
            </div>
            <div class="col-12 my-2" id="catalog_detail_allow_customer_to_add_product_to_order_row" style="' . $catalog_detail_allow_customer_to_add_product_to_order_row_style . '">
                <div class="form-check form-switch">
                    <input id="catalog_detail_allow_customer_to_add_product_to_order" name="catalog_detail_allow_customer_to_add_product_to_order" class="form-check-input collapse-switcher" value="1"' . $catalog_detail_allow_customer_to_add_product_to_order_checked . ' type="checkbox" role="switch" data-bs-target="#catalog_detail_allow_customer_to_add_product_to_order_row_popover" />
                    <label class="form-check-label" for="catalog_detail_allow_customer_to_add_product_to_order">' . lang('Allow customer to add product to order') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="catalog_detail_allow_customer_to_add_product_to_order_row_popover">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-12 my-1">
                                <label for="catalog_detail_add_button_label" class="form-label">' . lang('Add Button Label') . '</label>
                                <input value="' . h($catalog_detail_properties['add_button_label'] ?? '') . '" type="text" name="catalog_detail_add_button_label" id="catalog_detail_add_button_label" maxlength="50" class="form-control" />
                            </div>
                            <div class="col-12 col-md-12 my-1">
                                <label for="catalog_detail_next_page_id" class="form-label">' . lang('Next Page') . '</label>
                                <select class="form-select" id="catalog_detail_next_page_id" name="catalog_detail_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>lang('Page') )) . '-</option>' . select_page(($catalog_detail_properties['next_page_id'] ?? '')) . '</select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_detail_back_button_label_row" style="' . $catalog_detail_back_button_label_row_style . '">
                <label for="catalog_detail_back_button_label" class="form-label">' . lang('Back Button Label') . '</label>
                <input value="' . h($catalog_detail_properties['back_button_label'] ?? '') . '" type="text" name="catalog_detail_back_button_label" id="catalog_detail_back_button_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_shopping_cart_label_row" style="' . $express_order_shopping_cart_label_row_style . '">
                <label for="express_order_shopping_cart_label" class="form-label">' . lang('Shopping Cart Label') . '</label>
                <input value="' . h($express_order_properties['shopping_cart_label'] ?? '') . '" type="text" name="express_order_shopping_cart_label" id="express_order_shopping_cart_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_quick_add_label_row" style="' . $express_order_quick_add_label_row_style . '">
                <label for="express_order_quick_add_label" class="form-label">' . lang('Quick Add Label') . '</label>
                <input value="' . h($express_order_properties['quick_add_label'] ?? '') . '" type="text" name="express_order_quick_add_label" id="express_order_quick_add_label" maxlength="255" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_quick_add_product_group_id_row" style="' . $express_order_quick_add_product_group_id_row_style . '">
                <label for="express_order_quick_add_product_group_id" class="form-label">' . lang('Quick Add Product Group') . '</label>
                <select class="form-select" name="express_order_quick_add_product_group_id" id="express_order_quick_add_product_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options(($express_order_properties['quick_add_product_group_id'] ?? ''), 0, 0, 0, array(), true, 'text', false, true, false) . '</select>
            </div>
            <div class="col-12 my-2" id="express_order_product_description_type_row" style="' . $express_order_product_description_type_row_style . '">
                <label class="form-label" for="">'. lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input value="full_description" class="form-check-input" type="radio" id="express_order_product_description_type_full_description" name="express_order_product_description_type"' . $express_order_product_description_type_full_description_checked . '>
                    <label class="form-check-label" for="express_order_product_description_type_full_description">'. lang('Full Description') . '</label>
                </div>
                <div class="form-check">
                    <input value="short_description" class="form-check-input" type="radio" id="express_order_product_description_type_short_description" name="express_order_product_description_type"' . $express_order_product_description_type_short_description_checked . '>
                    <label class="form-check-label" for="express_order_product_description_type_short_description">'. lang('Short Description') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="express_order_shipping_form_row" style="' . $express_order_shipping_form_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="express_order_shipping_form" name="express_order_shipping_form" class="form-check-input" type="checkbox" role="switch" onclick="toggle_express_order_custom_shipping_form();show_or_hide_express_order_custom_billing_form();"' . $express_order_shipping_form_checked . '/>
                    <label class="form-check-label" for="express_order_shipping_form">' . lang('Enable Custom Shipping Form') . '</label>
                </div>
                <script>
                    var original_express_order_shipping_form = "' . ($express_order_properties['shipping_form'] ?? '') . '";
                </script>

                <div id="express_order_shipping_form_notice" style="display:none;">
                    <div class="alert alert-primary">
                       <p class="mb-0">' . lang('when ready, click \'Save & Continue\' at the bottom of this screen to create the Custom Shipping Form.') . '</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_special_offer_code_label_row" style="' . $express_order_special_offer_code_label_row_style . '">
                <label for="express_order_special_offer_code_label" class="form-label">' . lang('Special Offer Code Label') . '</label>
                <input value="' . h($express_order_properties['special_offer_code_label'] ?? '') . '" type="text" name="express_order_special_offer_code_label" id="express_order_special_offer_code_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-8 my-2" id="express_order_special_offer_code_message_row" style="' . $express_order_special_offer_code_message_row_style . '">
                <label for="express_order_special_offer_code_message" class="form-label">' . lang('Special Offer Code Message') . '</label>
                <input value="' . h($express_order_properties['special_offer_code_message'] ?? '') . '" type="text" name="express_order_special_offer_code_message" id="express_order_special_offer_code_message" maxlength="255" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 my-2" id="express_order_custom_field_1_label_row" style="' . $express_order_custom_field_1_label_row_style . '">
                <div class="border-1 border p-2 my-2 rounded">
                    <label for="express_order_custom_field_1_label" class="form-label">' . lang('Custom Field #1 Label') . '</label>
                    <input value="' . h($express_order_properties['custom_field_1_label'] ?? '') . '" type="text" name="express_order_custom_field_1_label" id="express_order_custom_field_1_label" maxlength="50" class="form-control" />
                    <div class="form-check form-switch ms-1 mt-2">
                        <input class="form-check-input" type="checkbox" name="express_order_custom_field_1_required" id="express_order_custom_field_1_required" value="1"' . $express_order_custom_field_1_required_checked . ' />
                        <label class="form-check-label" for="express_order_custom_field_1_required">' . lang('Required') . '</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 my-2" id="express_order_custom_field_2_label_row" style="' . $express_order_custom_field_2_label_row_style . '">
                <div class="border-1 border p-2 my-2 rounded">
                    <label for="express_order_custom_field_2_label" class="form-label">' . lang('Custom Field #2 Label') . '</label>
                    <input value="' . h($express_order_properties['custom_field_2_label'] ?? '') . '" type="text" name="express_order_custom_field_2_label" id="express_order_custom_field_2_label" maxlength="255" class="form-control" />
                    <div class="form-check form-switch ms-1 mt-2">
                        <input class="form-check-input" type="checkbox" name="express_order_custom_field_2_required" id="express_order_custom_field_2_required" value="1"' . $express_order_custom_field_2_required_checked . ' />
                        <label class="form-check-label" for="express_order_custom_field_2_required">' . lang('Required') . '</label>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="express_order_po_number_row" style="' . $express_order_po_number_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="express_order_po_number" id="express_order_po_number" value="1"' . $express_order_po_number_checked . ' />
                    <label class="form-check-label" for="express_order_po_number">' . lang('Enable PO Number') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="express_order_form_row" style="' . $express_order_form_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $express_order_form_checked . ' id="express_order_form" name="express_order_form" class="form-check-input collapse-switcher" type="checkbox" onclick="show_or_hide_express_order_custom_billing_form()" data-bs-target="#show_or_hide_express_order_custom_billing_form_row"/>
                    <label class="form-check-label" for="express_order_form">' . lang('Enable Custom Billing Form') . '</label>
                </div>
                <script>var original_express_order_form = "' . ($express_order_properties['form'] ?? '') . '";</script>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_express_order_custom_billing_form_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1" id="express_order_form_notice" style="display:none;">
                                <div class="alert alert-primary">
                                   <p class="mb-0">' . lang('when ready, click \'Save & Continue\' at the bottom of this screen to create the Custom Billing Form.') . '</p>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-8 my-1" id="express_order_form_name_row" style="' . $express_order_form_name_row_style . '">
                                <label for="express_order_form_name" class="form-label">' . lang('Form Title for Display') . '</label>
                                <input value="' . h(($express_order_properties['form_name'] ?? '')) . '" type="text" name="express_order_form_name" id="express_order_form_name" maxlength="100" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1" id="express_order_form_label_column_width_row" style="' . $express_order_form_label_column_width_row_style . '">
                                <label for="express_order_form_label_column_width" class="form-label">' . lang('Label Column Width') . '</label>
                                <div class="input-group">
                                    <input value="' . h(($express_order_properties['form_label_column_width'] ?? '')) . '" type="text" name="express_order_form_label_column_width" id="express_order_form_label_column_width" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
                                    <label class="input-group-text" for="form_label_column_width">%</label>
                                </div>
                                <div class="form-text text-end">'. lang('leave blank for auto') . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="express_order_auto_registration_row" style="display: none">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="express_order_auto_registration" id="express_order_auto_registration" value="1"' . $express_order_auto_registration_checked . ' />
                    <label class="form-check-label" for="express_order_auto_registration">' . lang('Enable Auto-Registration') . '</label>
                </div>
            </div>
            ' . $output_express_order_offline_payment_checkbox_rows . '
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_card_verification_number_page_id_row" style="' . $express_order_card_verification_number_page_id_row_style . '">
                <label for="express_order_card_verification_number_page_id" class="form-label">' . lang('Card Verification Number Page') . '</label>
                <select class="form-select" name="express_order_card_verification_number_page_id" id="express_order_card_verification_number_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($express_order_properties['card_verification_number_page_id'] ?? '')) . '</select>
            </div>
            ' . $output_express_order_offline_payment_rows . '
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_terms_page_id_row" style="' . $express_order_terms_page_id_row_style . '">
                <label for="express_order_terms_page_id" class="form-label">' . lang('Terms Page') . '</label>
                <select class="form-select" name="express_order_terms_page_id" id="express_order_terms_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($express_order_properties['terms_page_id'] ?? '')) . '</select>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_update_button_label_row" style="' . $express_order_update_button_label_row_style . '">
                <label class="form-label" for="express_order_update_button_label">'. lang('Update Button Label') . '</label>
                <input value="' . h($express_order_properties['update_button_label'] ?? '') . '" type="text" id="express_order_update_button_label" name="express_order_update_button_label" class="form-control" maxlength="50" >
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_purchase_now_button_label_row" style="' . $express_order_purchase_now_button_label_row_style . '">
                <label class="form-label" for="express_order_purchase_now_button_label">'. lang('Purchase Now Button Label') . '</label>
                <input value="' . h($express_order_properties['purchase_now_button_label'] ?? '') . '" type="text" id="express_order_purchase_now_button_label" name="express_order_purchase_now_button_label" class="form-control" maxlength="50" >
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook rows for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_ecommerce_page_type_properties .=
                '<div class="col-12">
                    <div class="row">
                        <div class="col-12 col-lg-6 my-2" id="express_order_pre_save_hook_code_row" style="' . $express_order_pre_save_hook_code_row_style . '">
                            <label class="form-label" for="express_order_pre_save_hook_code">' . lang('Pre-Save Hook Code') . '</label>
                            <textarea id="express_order_pre_save_hook_code" name="express_order_pre_save_hook_code" class="form-control">' . h(($express_order_properties['pre_save_hook_code'] ?? '')) . '</textarea>
                        </div>
                        <div class="col-12 col-lg-6 my-2" id="express_order_post_save_hook_code_row" style="' . $express_order_post_save_hook_code_row_style . '">
                            <label class="form-label" for="express_order_post_save_hook_code">' . lang('Post-Save Hook Code') . '</label>
                            <textarea id="express_order_post_save_hook_code" name="express_order_post_save_hook_code" class="form-control">' . h(($express_order_properties['post_save_hook_code'] ?? '')) . '</textarea>
                        </div>
                    </div>
                </div>';
        }

        $output_ecommerce_page_type_properties .=
            '<div class="col-12 col-md-6 col-lg-4 my-2" id="express_order_next_page_id_row" style="' . $express_order_next_page_id_row_style . '">
                <label class="form-label" for="express_order_next_page_id">' . lang('Next Page') . '</label>
                <select class="form-select" id="express_order_next_page_id" name="express_order_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('order receipt page')) )) . '-</option>' . select_page(($express_order_properties['next_page_id'] ?? ''), 'order receipt') . '</select>
            </div>
            <div class="col-12 my-2" id="express_order_order_receipt_email_row" style="' . $express_order_order_receipt_email_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $express_order_order_receipt_email_checked . ' id="express_order_order_receipt_email" name="express_order_order_receipt_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_express_order_order_receipt_email" />
                    <label class="form-check-label" for="express_order_order_receipt_email">' . lang('E-mail Order Receipt') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_express_order_order_receipt_email">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-8 my-1">
                                <label class="form-label" for="express_order_order_receipt_email_subject">' . lang('Subject') . '</label>
                                <input value="' . h(($express_order_properties['order_receipt_email_subject'] ?? '')) . '" type="text" id="express_order_order_receipt_email_subject" name="express_order_order_receipt_email_subject" class="form-control" maxlength="255">
                            </div> 
                            <div class="col-12 my-1">
                                <div class="col-12">
                                    <label class="form-label">' . lang('Format') . '</label>
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="express_order_order_receipt_email_format_plain_text" name="express_order_order_receipt_email_format"' . $express_order_order_receipt_email_format_plain_text_checked . ' value="plain_text" data-bs-target="#express_order_order_receipt_email_format_plain_text_row" />
                                    <label for="express_order_order_receipt_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="express_order_order_receipt_email_format_html" name="express_order_order_receipt_email_format"' . $express_order_order_receipt_email_format_html_checked . ' value="html" data-bs-target="#express_order_order_receipt_email_format_html_row"/>
                                    <label for="express_order_order_receipt_email_format_html">' . lang('HTML') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="express_order_order_receipt_email_format_plain_text_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                              <label for="express_order_order_receipt_email_header" class="form-label">' . lang('Header') . '</label>
                                              <textarea class="form-control" id="express_order_order_receipt_email_header" name="express_order_order_receipt_email_header" rows="3">' . h(($express_order_properties['order_receipt_email_header'] ?? '')) . '</textarea>
                                            </div>
                                            <div class="col-12 my-1">
                                              <label for="express_order_order_receipt_email_footer" class="form-label">' . lang('Footer') . '</label>
                                              <textarea class="form-control" id="express_order_order_receipt_email_footer" name="express_order_order_receipt_email_footer" rows="3">' . h(($express_order_properties['order_receipt_email_footer'] ?? '')) . '</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="express_order_order_receipt_email_format_html_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                                <label class="form-label" for="express_order_order_receipt_email_page_id">' . lang('Page') . '</label>
                                                <select class="form-select" id="express_order_order_receipt_email_page_id" name="express_order_order_receipt_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('order receipt page')) )) . '-</option>' . select_page(($express_order_properties['order_receipt_email_page_id'] ?? ''), 'order receipt') . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="order_form_product_group_id_row" style="' . $order_form_product_group_id_row_style . '">
                <label class="form-label" for="order_form_product_group_id">' . lang('Product Group') . '</label>
                <select class="form-select" id="order_form_product_group_id" name="order_form_product_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options(($order_form_properties['product_group_id'] ?? ''), 0, 0, 0, array(), true, 'text', false, true, false) . '</select>
            </div>
            <div class="col-12 my-3" id="order_form_product_layout_row_1" style="' . $order_form_product_layout_row_1_style . '">
                <label class="form-label">' . lang('Format') . '</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="order_form_product_layout_list" name="order_form_product_layout"' . $order_form_product_layout_list . ' value="list" />
                    <label for="order_form_product_layout_list">' . lang('List (full description)') . '</label> 
                </div>
                <div class="form-check" id="order_form_product_layout_row_2" style="' . $order_form_product_layout_row_2_style . '">
                    <input class="form-check-input" type="radio" id="order_form_product_layout_drop_down_selection" name="order_form_product_layout"' . $order_form_product_layout_drop_down_selection . ' value="drop-down selection" />
                    <label for="order_form_product_layout_drop_down_selection">' . lang('Drop-Down Selection (short description)') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="order_form_add_button_row" style="' . $order_form_add_button_row_style . '">
                <div class="row p-1 border border-1 rounded bg-light">
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="order_form_add_button_label">' . lang('Add Button Label') . '</label>
                        <input value="' . h($order_form_properties['add_button_label'] ?? '') . '" type="text" class="form-control" id="order_form_add_button_label" name="order_form_add_button_label" maxlength="50"/>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="order_form_add_button_next_page_id">' . lang('Next Page') . '</label>
                        <select class="form-select" id="order_form_add_button_next_page_id" name="order_form_add_button_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($order_form_properties['add_button_next_page_id'] ?? '')) . '</select>
                    </div> 
                </div>
            </div>
            <div class="col-12 my-2" id="order_form_skip_button_row" style="' . $order_form_skip_button_row_style . '">
                <div class="row p-1 border border-1 rounded bg-light">
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="order_form_skip_button_label">' . lang('Skip Button Label') . '</label>
                        <input value="' . h($order_form_properties['skip_button_label'] ?? '') . '" type="text" class="form-control" id="order_form_skip_button_label" name="order_form_skip_button_label" maxlength="50"/>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="order_form_skip_button_next_page_id">' . lang('Next Page') . '</label>
                        <select class="form-select" id="order_form_skip_button_next_page_id" name="order_form_skip_button_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($order_form_properties['skip_button_next_page_id'] ?? '')) . '</select>
                    </div> 
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_shopping_cart_label_row" style="' . $shopping_cart_shopping_cart_label_row_style . '">
                <label class="form-label" for="shopping_cart_shopping_cart_label">' . lang('Shopping Cart Label') . '</label>
                <input value="' . h($shopping_cart_properties['shopping_cart_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_shopping_cart_label" name="shopping_cart_shopping_cart_label" maxlength="50"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_quick_add_label_row" style="' . $shopping_cart_quick_add_label_row_style . '">
                <label class="form-label" for="shopping_cart_quick_add_label">' . lang('Quick Add Label') . '</label>
                <input value="' . h($shopping_cart_properties['quick_add_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_quick_add_label" name="shopping_cart_quick_add_label" maxlength="255"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_quick_add_product_group_id_row" style="' . $shopping_cart_quick_add_product_group_id_row_style . '">
                <label class="form-label" for="shopping_cart_quick_add_product_group_id">' . lang('Quick Add Product Group') . '</label>
                <select class="form-select" id="shopping_cart_quick_add_product_group_id" name="shopping_cart_quick_add_product_group_id"><option value="">-' . lang('None') . '-</option>' . get_product_group_options(($shopping_cart_properties['quick_add_product_group_id'] ?? ''), 0, 0, 0, array(), true, 'text', false, true, false) . '</select>
            </div> 
            <div class="col-12 my-3" id="shopping_cart_product_description_type_row" style="' . $shopping_cart_product_description_type_row_style . '">
                <label class="form-label">' . lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="shopping_cart_product_description_type_full_description" name="shopping_cart_product_description_type"' . $shopping_cart_product_description_type_full_description_checked . ' value="full_description" />
                    <label for="shopping_cart_product_description_type_full_description">' . lang('Full Description') . '</label> 
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="shopping_cart_product_description_type_short_description" name="shopping_cart_product_description_type"' . $shopping_cart_product_description_type_short_description_checked . ' value="short_description" />
                    <label for="shopping_cart_product_description_type_short_description">' . lang('Short Description') . '</label>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_special_offer_code_label_row" style="' . $shopping_cart_special_offer_code_label_row_style . '">
                <label class="form-label" for="shopping_cart_special_offer_code_label">' . lang('Special Offer Code Label') . '</label>
                <input value="' . h($shopping_cart_properties['special_offer_code_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_special_offer_code_label" name="shopping_cart_special_offer_code_label" maxlength="50"/>
            </div>
            <div class="col-12 col-md-6 col-lg-8 my-2" id="shopping_cart_special_offer_code_message_row" style="' . $shopping_cart_special_offer_code_message_row_style . '">
                <label class="form-label" for="shopping_cart_special_offer_code_message">' . lang('Special Offer Code Message') . '</label>
                <input value="' . h($shopping_cart_properties['special_offer_code_message'] ?? '') . '" type="text" class="form-control" id="shopping_cart_special_offer_code_message" name="shopping_cart_special_offer_code_message" maxlength="255"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_update_button_label_row" style="' . $shopping_cart_update_button_label_row_style . '">
                <label class="form-label" for="shopping_cart_update_button_label">' . lang('Update Button Label') . '</label>
                <input value="' . h($shopping_cart_properties['update_button_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_update_button_label" name="shopping_cart_update_button_label" maxlength="50"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_checkout_button_label_row" style="' . $shopping_cart_checkout_button_label_row_style . '">
                <label class="form-label" for="shopping_cart_checkout_button_label">' . lang('Checkout Button Label') . '</label>
                <input value="' . h($shopping_cart_properties['checkout_button_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_checkout_button_label" name="shopping_cart_checkout_button_label" maxlength="50"/>
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook row for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_ecommerce_page_type_properties .=
                '<div class="col-12 my-2" id="shopping_cart_hook_code_row" style="' . $shopping_cart_hook_code_row_style . '">
                    <label class="form-label" for="shopping_cart_hook_code">' . lang('Hook Code') . '</label>
                    <textarea id="shopping_cart_hook_code" name="shopping_cart_hook_code" class="form-control">' . h(($shopping_cart_properties['hook_code'] ?? '')) . '</textarea>
                </div>';
        }

        $output_ecommerce_page_type_properties .=
            '<div class="col-12 col-lg-6 col-xl-4 my-2" id="shopping_cart_next_page_id_with_shipping_row" style="' . $shopping_cart_next_page_id_with_shipping_row_style . '">
                <label class="form-label" for="shopping_cart_next_page_id_with_shipping">' . lang('Next Page') . ' (' . lang('with shipping') . ')</label>
                <select class="form-select" id="shopping_cart_next_page_id_with_shipping" name="shopping_cart_next_page_id_with_shipping"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Shipping Address & Arrival or Express Order Page')) )) . '-</option>' . select_page(($shopping_cart_properties['next_page_id_with_shipping'] ?? ''), array('shipping address and arrival', 'express order')) . '</select>
            </div>
            <div class="col-12 col-lg-6 col-xl-4 my-2" id="shopping_cart_next_page_id_without_shipping_row" style="' . $shopping_cart_next_page_id_without_shipping_row_style . '">
                <label class="form-label" for="shopping_cart_next_page_id_without_shipping">' . lang('Next Page') . ' (' . lang('without shipping') . ')</label>
                <select class="form-select" id="shopping_cart_next_page_id_without_shipping" name="shopping_cart_next_page_id_without_shipping"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Billing Information or Express Order Page')) )) . '-</option>' . select_page(($shopping_cart_properties['next_page_id_without_shipping'] ?? ''), array('billing information', 'express order')) . '</select>
            </div> 
            <div class="col-12 my-2" id="shipping_address_and_arrival_address_type_row" style="' . $shipping_address_and_arrival_address_type_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="shipping_address_and_arrival_address_type" name="shipping_address_and_arrival_address_type" class="form-check-input collapse-switcher" type="checkbox"' . $shipping_address_and_arrival_address_type_checked . '  data-bs-target="#shipping_address_and_arrival_address_type_page_row"/>
                    <label class="form-check-label" for="shipping_address_and_arrival_address_type">' . lang('Enable Address Type') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="shipping_address_and_arrival_address_type_page_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <label class="form-label" for="shipping_address_and_arrival_address_type_page_id">' . lang('Address Type Page') . '</label>
                                <select class="form-select" id="shipping_address_and_arrival_address_type_page_id" name="shipping_address_and_arrival_address_type_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($shipping_address_and_arrival_properties['address_type_page_id'] ?? '')) . '</select>
                            </div> 
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="shipping_address_and_arrival_form_row" style="' . $shipping_address_and_arrival_form_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $shipping_address_and_arrival_form_checked . ' id="shipping_address_and_arrival_form" name="shipping_address_and_arrival_form" class="form-check-input collapse-switcher" type="checkbox" onclick="show_or_hide_custom_shipping_form()" data-bs-target="#show_or_hide_custom_shipping_form_row"/>
                    <label class="form-check-label" for="shipping_address_and_arrival_form">' . lang('Enable Custom Shipping Form') . '</label>
                </div>
                <script type="text/javascript">var original_shipping_address_and_arrival_form = "' . ($shipping_address_and_arrival_properties['form'] ?? '') . '";</script>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_shipping_form_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1" id="shipping_address_and_arrival_form_notice" style="display:none;">
                                <div class="alert alert-primary">
                                   <p class="mb-0">' . lang('when ready, click \'Save & Continue\' at the bottom of this screen to create the Custom Shipping Form.') . '</p>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-8 my-1" id="shipping_address_and_arrival_form_name_row" style="' . $shipping_address_and_arrival_form_name_row_style . '">
                                <label for="shipping_address_and_arrival_form_name" class="form-label">' . lang('Form Title for Display') . '</label>
                                <input value="' . h(($shipping_address_and_arrival_properties['form_name'] ?? '')) . '" type="text" name="shipping_address_and_arrival_form_name" id="shipping_address_and_arrival_form_name" maxlength="100" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1" id="shipping_address_and_arrival_form_label_column_width_row" style="' . $shipping_address_and_arrival_form_label_column_width_row_style . '">
                                <label for="shipping_address_and_arrival_form_label_column_width" class="form-label">' . lang('Label Column Width') . '</label>
                                <div class="input-group">
                                    <input value="' . h(($shipping_address_and_arrival_properties['form_label_column_width'] ?? '')) . '" type="text" name="shipping_address_and_arrival_form_label_column_width" id="shipping_address_and_arrival_form_label_column_width" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
                                    <label class="input-group-text" for="shipping_address_and_arrival_form_label_column_width">%</label>
                                </div>
                                <div class="form-text text-end">'. lang('leave blank for auto') . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="shipping_address_and_arrival_submit_button_row" style="' . $shipping_address_and_arrival_submit_button_row_style . '">
                <div class="row p-1 border border-1 rounded bg-light">
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="shipping_address_and_arrival_submit_button_label">' . lang('Submit Button Label') . '</label>
                        <input value="' . h($shipping_address_and_arrival_properties['submit_button_label'] ?? '') . '" type="text" class="form-control" id="shipping_address_and_arrival_submit_button_label" name="shipping_address_and_arrival_submit_button_label" maxlength="50"/>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="shipping_address_and_arrival_next_page_id">' . lang('Next Page') . '</label>
                        <select class="form-select" id="shipping_address_and_arrival_next_page_id" name="shipping_address_and_arrival_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Shipping Method Page')) )) . '-</option>' . select_page(($shipping_address_and_arrival_properties['next_page_id'] ?? ''), 'shipping method') . '</select>
                    </div> 
                </div>
            </div>
            <div class="col-12 my-2" id="shipping_method_product_description_type_row" style="' . $shipping_method_product_description_type_row_style . '">
                <label class="form-label" for="">'. lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input value="full_description" class="form-check-input" type="radio" id="shipping_method_product_description_type_full_description" name="shipping_method_product_description_type"' . $shipping_method_product_description_type_full_description_checked . '>
                    <label class="form-check-label" for="shipping_method_product_description_type_full_description">'. lang('Full Description') . '</label>
                </div>
                <div class="form-check">
                    <input value="short_description" class="form-check-input" type="radio" id="shipping_method_product_description_type_short_description" name="shipping_method_product_description_type"' . $shipping_method_product_description_type_short_description_checked . '>
                    <label class="form-check-label" for="shipping_method_product_description_type_short_description">'. lang('Short Description') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="shipping_method_submit_button_row" style="' . $shipping_method_submit_button_row_style . '">
                <div class="row p-1 border border-1 rounded bg-light">
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="shipping_method_submit_button_label">' . lang('Submit Button Label') . '</label>
                        <input value="' . h($shipping_method_properties['submit_button_label'] ?? '') . '" type="text" class="form-control" id="shipping_method_submit_button_label" name="shipping_method_submit_button_label" maxlength="50"/>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="shipping_method_next_page_id">' . lang('Next Page') . '</label>
                        <select class="form-select" id="shipping_method_next_page_id" name="shipping_method_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($shipping_method_properties['next_page_id'] ?? '')) . '</select>
                    </div> 
                </div>
            </div>
            <div class="col-12 col-sm-6 my-2" id="billing_information_custom_field_1_label_row" style="' . $billing_information_custom_field_1_label_row_style . '">
                <div class="border-1 border p-2 my-2 rounded">
                    <label for="billing_information_custom_field_1_label" class="form-label">' . lang('Custom Field #1 Label') . '</label>
                    <input value="' . h($billing_information_properties['custom_field_1_label'] ?? '') . '" type="text" name="billing_information_custom_field_1_label" id="billing_information_custom_field_1_label" maxlength="255" class="form-control" />
                    <div class="form-check form-switch ms-1 mt-2">
                        <input class="form-check-input" type="checkbox" name="billing_information_custom_field_1_required" id="billing_information_custom_field_1_required" value="1"' . $billing_information_custom_field_1_required_checked . ' />
                        <label class="form-check-label" for="billing_information_custom_field_1_required">' . lang('Required') . '</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 my-2" id="billing_information_custom_field_2_label_row" style="' . $billing_information_custom_field_2_label_row_style . '">
                <div class="border-1 border p-2 my-2 rounded">
                    <label for="billing_information_custom_field_2_label" class="form-label">' . lang('Custom Field #2 Label') . '</label>
                    <input value="' . h($billing_information_properties['custom_field_2_label'] ?? '') . '" type="text" name="billing_information_custom_field_2_label" id="billing_information_custom_field_2_label" maxlength="255" class="form-control" />
                    <div class="form-check form-switch ms-1 mt-2">
                        <input class="form-check-input" type="checkbox" name="billing_information_custom_field_2_required" id="billing_information_custom_field_2_required" value="1"' . $billing_information_custom_field_2_required_checked . ' />
                        <label class="form-check-label" for="billing_information_custom_field_2_required">' . lang('Required') . '</label>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="billing_information_po_number_row" style="' . $billing_information_po_number_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="billing_information_po_number" id="billing_information_po_number" value="1"' . $billing_information_po_number_checked . ' />
                    <label class="form-check-label" for="billing_information_po_number">' . lang('Enable PO Number') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="billing_information_form_row" style="' . $billing_information_form_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $billing_information_form_checked . ' id="billing_information_form" name="billing_information_form" class="form-check-input collapse-switcher" type="checkbox" onclick="show_or_hide_billing_information_custom_billing_form()" data-bs-target="#show_or_hide_billing_information_custom_billing_form_row"/>
                    <label class="form-check-label" for="billing_information_form">' . lang('Enable Custom Billing Form') . '</label>
                </div>
                <script type="text/javascript">var original_billing_information_form = "' . ($billing_information_properties['form'] ?? '') . '";</script>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_billing_information_custom_billing_form_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1" id="billing_information_form_notice" style="display:none;">
                                <div class="alert alert-primary">
                                   <p class="mb-0">' . lang('when ready, click \'Save & Continue\' at the bottom of this screen to create the Custom Billing Form.') . '</p>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-8 my-1" id="billing_information_form_name_row" style="' . $billing_information_form_name_row_style . '">
                                <label for="billing_information_form_name" class="form-label">' . lang('Form Title for Display') . '</label>
                                <input value="' . h(($billing_information_properties['form_name'] ?? '')) . '" type="text" name="billing_information_form_name" id="billing_information_form_name" maxlength="100" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1" id="billing_information_form_label_column_width_row" style="' . $billing_information_form_label_column_width_row_style . '">
                                <label for="billing_information_form_label_column_width" class="form-label">' . lang('Label Column Width') . '</label>
                                <div class="input-group">
                                    <input value="' . h(($billing_information_properties['form_label_column_width'] ?? '')) . '" type="text" name="billing_information_form_label_column_width" id="billing_information_form_label_column_width" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
                                    <label class="input-group-text" for="billing_information_form_label_column_width">%</label>
                                </div>
                                <div class="form-text text-end">'. lang('leave blank for auto') . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="billing_information_submit_button_label_row" style="' . $billing_information_submit_button_label_row_style . '">
                <label class="form-label" for="billing_information_submit_button_label">' . lang('Submit Button Label') . '</label>
                <input value="' . h($billing_information_properties['submit_button_label'] ?? '') . '" type="text" class="form-control" id="billing_information_submit_button_label" name="billing_information_submit_button_label" maxlength="50"/>
            </div>
            <div class="col-12 col-lg-6 col-xl-4 my-2" id="billing_information_next_page_id_row" style="' . $billing_information_next_page_id_row_style . '">
                <label class="form-label" for="billing_information_next_page_id">' . lang('Next Page') . ' (' . lang('without shipping') . ')</label>
                <select class="form-select" id="billing_information_next_page_id" name="billing_information_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Order Preview or Express Order Page')) )) . '-</option>' . select_page(($billing_information_properties['next_page_id'] ?? ''), 'order preview') . select_page(($billing_information_properties['next_page_id'] ?? ''), 'express order') . '</select>
            </div> 
            <div class="col-12 my-2" id="order_preview_product_description_type_row" style="' . $order_preview_product_description_type_row_style . '">
                <label class="form-label" for="">'. lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input value="full_description" class="form-check-input" type="radio" id="order_preview_product_description_type_full_description" name="order_preview_product_description_type"' . $order_preview_product_description_type_full_description_checked . '>
                    <label class="form-check-label" for="order_preview_product_description_type_full_description">'. lang('Full Description') . '</label>
                </div>
                <div class="form-check">
                    <input value="short_description" class="form-check-input" type="radio" id="order_preview_product_description_type_short_description" name="order_preview_product_description_type"' . $order_preview_product_description_type_short_description_checked . '>
                    <label class="form-check-label" for="order_preview_product_description_type_short_description">'. lang('Short Description') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="order_preview_auto_registration_row" style="' . $order_preview_auto_registration_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="order_preview_auto_registration" id="order_preview_auto_registration" value="1"' . $order_preview_auto_registration_checked . ' />
                    <label class="form-check-label" for="order_preview_auto_registration">' . lang('Enable Auto-Registration') . '</label>
                </div>
            </div>
            ' . $output_order_preview_offline_payment_checkbox_rows . '
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="order_preview_card_verification_number_page_id_row" style="' . $order_preview_card_verification_number_page_id_row_style . '">
                <label for="order_preview_card_verification_number_page_id" class="form-label">' . lang('Card Verification Number Page') . '</label>
                <select class="form-select" name="order_preview_card_verification_number_page_id" id="order_preview_card_verification_number_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($order_preview_properties['card_verification_number_page_id'] ?? '')) . '</select>
            </div>
            ' . $output_order_preview_offline_payment_rows . '
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="order_preview_terms_page_id_row" style="' . $order_preview_terms_page_id_row_style . '">
                <label for="order_preview_terms_page_id" class="form-label">' . lang('Terms Page') . '</label>
                <select class="form-select" name="order_preview_terms_page_id" id="order_preview_terms_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($order_preview_properties['terms_page_id'] ?? '')) . '</select>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="order_preview_submit_button_label_row" style="' . $order_preview_submit_button_label_row_style . '">
                <label class="form-label" for="order_preview_submit_button_label">'. lang('Update Button Label') . '</label>
                <input value="' . h($order_preview_properties['submit_button_label'] ?? '') . '" type="text" id="order_preview_submit_button_label" name="order_preview_submit_button_label" class="form-control" maxlength="50" >
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook rows for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_ecommerce_page_type_properties .=
                '<div class="col-12">
                    <div class="row">
                        <div class="col-12 col-lg-6 my-2" id="order_preview_pre_save_hook_code_row" style="' . $order_preview_pre_save_hook_code_row_style . '">
                            <label class="form-label" for="order_preview_pre_save_hook_code">' . lang('Pre-Save Hook Code') . '</label>
                            <textarea id="order_preview_pre_save_hook_code" name="order_preview_pre_save_hook_code" class="form-control">' . h(($order_preview_properties['pre_save_hook_code'] ?? '')) . '</textarea>
                        </div>
                        <div class="col-12 col-lg-6 my-2" id="order_preview_post_save_hook_code_row" style="' . $order_preview_post_save_hook_code_row_style . '">
                            <label class="form-label" for="order_preview_post_save_hook_code">' . lang('Post-Save Hook Code') . '</label>
                            <textarea id="order_preview_post_save_hook_code" name="order_preview_post_save_hook_code" class="form-control">' . h(($order_preview_properties['post_save_hook_code'] ?? '')) . '</textarea>
                        </div> 
                    </div>
                </div>';
        }

        $output_ecommerce_page_type_properties .=
            '<div class="col-12 col-md-6 col-lg-4 my-2" id="order_preview_next_page_id_row" style="' . $order_preview_next_page_id_row_style . '">
                <label class="form-label" for="order_preview_next_page_id">' . lang('Next Page') . '</label>
                <select class="form-select" id="order_preview_next_page_id" name="order_preview_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('order receipt page')) )) . '-</option>' . select_page(($order_preview_properties['next_page_id'] ?? ''), 'order receipt') . '</select>
            </div>
            <div class="col-12 my-2" id="order_preview_order_receipt_email_row" style="' . $order_preview_order_receipt_email_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $order_preview_order_receipt_email_checked . ' id="order_preview_order_receipt_email" name="order_preview_order_receipt_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_order_preview_order_receipt_email" />
                    <label class="form-check-label" for="order_preview_order_receipt_email">' . lang('E-mail Order Receipt') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_order_preview_order_receipt_email">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-8 my-1">
                                <label class="form-label" for="order_preview_order_receipt_email_subject">' . lang('Subject') . '</label>
                                <input value="' . h(($order_preview_properties['order_receipt_email_subject'] ?? '')) . '" type="text" id="order_preview_order_receipt_email_subject" name="order_preview_order_receipt_email_subject" class="form-control" maxlength="255">
                            </div> 
                            <div class="col-12 my-1">
                                <div class="col-12">
                                    <label class="form-label">' . lang('Format') . '</label>
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="order_preview_order_receipt_email_format_plain_text" name="order_preview_order_receipt_email_format"' . $order_preview_order_receipt_email_format_plain_text_checked . ' value="plain_text" data-bs-target="#order_preview_order_receipt_email_format_plain_text_row" />
                                    <label for="order_preview_order_receipt_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="order_preview_order_receipt_email_format_html" name="order_preview_order_receipt_email_format"' . $order_preview_order_receipt_email_format_html_checked . ' value="html"  data-bs-target="#order_preview_order_receipt_email_format_html_row"/>
                                    <label for="order_preview_order_receipt_email_format_html">' . lang('HTML') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="order_preview_order_receipt_email_format_plain_text_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                              <label for="order_preview_order_receipt_email_header" class="form-label">' . lang('Header') . '</label>
                                              <textarea class="form-control" id="order_preview_order_receipt_email_header" name="order_preview_order_receipt_email_header" rows="3">' . h(($order_preview_properties['order_receipt_email_header'] ?? '')) . '</textarea>
                                            </div>
                                            <div class="col-12 my-1">
                                              <label for="order_preview_order_receipt_email_footer" class="form-label">' . lang('Footer') . '</label>
                                              <textarea class="form-control" id="order_preview_order_receipt_email_footer" name="order_preview_order_receipt_email_footer" rows="3">' . h(($order_preview_properties['order_receipt_email_footer'] ?? '')) . '</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="order_preview_order_receipt_email_format_html_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                                <label class="form-label" for="order_preview_order_receipt_email_page_id">' . lang('Page') . '</label>
                                                <select class="form-select" id="order_preview_order_receipt_email_page_id" name="order_preview_order_receipt_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('order receipt page')) )) . '-</option>' . select_page(($order_preview_properties['order_receipt_email_page_id'] ?? ''), 'order receipt') . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="order_receipt_product_description_type_row" style="' . $order_receipt_product_description_type_row_style . '">
                <label class="form-label" for="">'. lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input value="full_description" class="form-check-input" type="radio" id="order_receipt_product_description_type_full_description" name="order_receipt_product_description_type"' . $order_receipt_product_description_type_full_description_checked . '>
                    <label class="form-check-label" for="order_receipt_product_description_type_full_description">'. lang('Full Description') . '</label>
                </div>
                <div class="form-check">
                    <input value="short_description" class="form-check-input" type="radio" id="order_receipt_product_description_type_short_description" name="order_receipt_product_description_type"' . $order_receipt_product_description_type_short_description_checked . '>
                    <label class="form-check-label" for="order_receipt_product_description_type_short_description">'. lang('Short Description') . '</label>
                </div>
            </div>';
    }
    
    if (FORMS == true) {
        $output_wysiwyg_editor_code = get_wysiwyg_editor_code(array('custom_form_confirmation_message', 'custom_form_return_message'), $activate_editors);

        // prepare to get folders that user has access to, in order to determine which form list views should be available to be selected for the form view directory page type
        $folders_that_user_has_access_to = array();
        
        // if user is a basic user, then get folders that user has access to
        if ($user['role'] == 3) {
            $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
        }
        
        // get all unarchived form list views for form view directory page type
        $query =
            "SELECT
                page.page_id,
                page.page_name,
                page.page_folder as folder_id,
                form_list_view_pages.custom_form_page_id
            FROM page
            LEFT JOIN form_list_view_pages ON
                (page.page_id = form_list_view_pages.page_id)
                AND (form_list_view_pages.collection = 'a')
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            WHERE
                (page.page_type = 'form list view')
                AND (folder.folder_archived = '0')
            ORDER BY page.page_name ASC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $form_list_views = array();
        
        // loop through the form list views in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $form_list_views[] = $row;
        }
        
        // get selected form list views for this page
        $query =
            "SELECT
                form_list_view_page_id,
                form_list_view_name,
                subject_form_field_id
            FROM form_view_directories_form_list_views_xref
            WHERE form_view_directory_page_id = '" . escape($page_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // create selected form list views array
        $selected_form_list_views = array();
        
        // loop through the selected form list views in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $selected_form_list_views[$row['form_list_view_page_id']] = $row;
        }
        
        // get all custom form fields for form view directory page type
        $query =
            "SELECT
                id,
                name,
                page_id
            FROM form_fields
            ORDER BY page_id ASC, sort_order ASC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // create custom forms array that will hold the form fields
        $custom_forms = array();
        
        // loop through the form fields in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $custom_forms[$row['page_id']]['form_fields'][] = $row;
        }
        
        $output_form_view_directory_form_list_view_rows = '';
        
        // loop through all of the form list views in order to prepare rows for each one
        foreach ($form_list_views as $form_list_view) {
            // if user has edit access to the form list view and there are form fields for the form list view's custom form, then prepare to output a row for it
            if ((check_folder_access_in_array($form_list_view['folder_id'], $folders_that_user_has_access_to) == TRUE) && (isset($custom_forms[$form_list_view['custom_form_page_id']]['form_fields']) == TRUE)) {
                // assume that form list view should not be checked until we find out otherwise
                $form_list_view_checked = '';
                
                // assume that we will not show name and subject fields until we find out otherwise
                $name_container_style = 'display: none';
                $subject_form_field_id_container_style = 'display: none';
                
                // assume that the form list view name is blank until we find out otherwise
                $output_form_list_view_name = '';
                
                // if this form list view is selected, then prepare checkbox to be checked and prepare to show name and subject fields
                if (isset($selected_form_list_views[$form_list_view['page_id']]) == TRUE) {
                    $form_list_view_checked = ' checked="checked"';
                    $name_container_style = '';
                    $subject_form_field_id_container_style = '';
                    $output_form_list_view_name = h($selected_form_list_views[$form_list_view['page_id']]['form_list_view_name']);
                }
                
                $output_form_view_directory_subject_field_options = '';
                
                // loop through the fields for this form list view's custom form, in order to prepare to output subject field options
                foreach ($custom_forms[$form_list_view['custom_form_page_id']]['form_fields'] as $form_field) {
                    // assume that this field should not be selected, until we find out otherwise
                    $selected = '';
                    
                    // if this form list view is selected and this field is selected, then prepare to select it
                    if ((isset($selected_form_list_views[$form_list_view['page_id']]) == TRUE) && ($form_field['id'] == $selected_form_list_views[$form_list_view['page_id']]['subject_form_field_id'])) {
                        $selected = ' selected="selected"';
                    }
                    
                    $output_form_view_directory_subject_field_options .= '<option value="' . $form_field['id'] . '"' . $selected . '>' . h($form_field['name']) . '</option>';
                }
                
                $output_form_view_directory_form_list_view_rows .=
                    '<div class="form-check">
                        <input type="checkbox" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '" name="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '" value="1"' . $form_list_view_checked . ' class="form-check-input collapse-switcher" data-bs-target="#form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_row"/>
                        <label class="form-check-label" for="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '">' . h($form_list_view['page_name']) . '</label>
                    </div>
                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_row">
                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                        <div class="popover-body">
                            <div class="row">
                                <div class="col-12 col-lg-6 my-1" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name_container" >
                                    <label for="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name" class="form-label">' . lang('Name') . '</label>
                                    <input value="' . $output_form_list_view_name . '" name="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name" type="text" class="form-control" maxlength="100" />
                                </div>
                                <div class="col-12 col-lg-6 my-1" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_subject_form_field_id_container">
                                    <label for="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_subject_form_field_id" class="form-label">' . lang('Subject Field') . '</label>
                                    <select class="form-select" name="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_subject_form_field_id" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_subject_form_field_id"><option value=""></option>' . $output_form_view_directory_subject_field_options . '</select>
                                </div>
                            </div>
                        </div>
                    </div>';
            }
        }
        
        $output_forms_page_type_properties =
            '<div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_form_name_row" style="' . $custom_form_form_name_row_style . '">
                <label for="custom_form_form_name" class="form-label">' . lang('Form Name') . '</label>
                <input value="' . h(($custom_form_properties['form_name'] ?? '')) . '" type="text" name="custom_form_form_name" id="custom_form_form_name" maxlength="100" class="form-control" />
            </div>
            <div class="col-12 my-2" id="custom_form_enabled_row" style="' . $custom_form_enabled_row_style . '">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="custom_form_enabled" name="custom_form_enabled" value="1"' . $custom_form_enabled_checked . ' />
                    <label for="custom_form_enabled">' . lang('Enable Form') . '</label> 
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_quiz_row" style="' . $custom_form_quiz_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $custom_form_quiz_checked . ' id="custom_form_quiz" name="custom_form_quiz" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#custom_form_quiz_collapse" />
                    <label class="form-check-label" for="custom_form_quiz">' . lang('Enable Quiz') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="custom_form_quiz_collapse">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-12 my-1">
                                <label for="update_address_book_address_type_page_id" class="form-label">' . lang('Quiz Pass Percentage') . '</label>
                                <div class="input-group">
                                    <input value="' . h($custom_form_properties['quiz_pass_percentage'] ?? '') . '" type="text" name="custom_form_quiz_pass_percentage" id="custom_form_quiz_pass_percentage" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
                                    <label class="input-group-text" for="custom_form_quiz_pass_percentage">%</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_save_row" style="' . $custom_form_save_row_style . '">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="custom_form_save" name="custom_form_save" value="1"' . $custom_form_save_checked . ' />
                    <label for="custom_form_save">' . lang('Enable Save-for-Later') . '</label> 
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_auto_registration_row" style="' . $custom_form_auto_registration_row_style . '">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="custom_form_auto_registration" name="custom_form_auto_registration" value="1"' . $custom_form_auto_registration_checked . ' />
                    <label for="custom_form_auto_registration">' . lang('Enable Auto-Registration') . '</label> 
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_label_column_width_row" style="' . $custom_form_label_column_width_row_style . '">
                <label for="custom_form_label_column_width" class="form-label">' . lang('Label Column Width') . '</label>
                <div class="input-group">
                    <input value="' . h(($custom_form_properties['label_column_width'] ?? '')) . '" type="text" name="custom_form_label_column_width" id="custom_form_label_column_width" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
                    <label class="input-group-text" for="custom_form_label_column_width">%</label>
                </div>
                <div class="form-text text-end">' . lang('leave blank for auto') . '</div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_submit_button_label_row" style="' . $custom_form_submit_button_label_row_style . '">
                <label for="custom_form_submit_button_label" class="form-label">' . lang('Submit Button Label') . '</label>
                <input value="' . h($custom_form_properties['submit_button_label'] ?? '') . '" type="text" name="custom_form_submit_button_label" id="custom_form_submit_button_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_watcher_page_id_row" style="' . $custom_form_watcher_page_id_row_style . '">
                <label for="custom_form_watcher_page_id" class="form-label">' . lang('Enable Watcher Option') . '</label>
                <select class="form-select" name="custom_form_watcher_page_id" id="custom_form_watcher_page_id">
                <option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('form item view page')) )) . '-</option>' . select_page(($custom_form_properties['watcher_page_id'] ?? ''), 'form item view') . '</select>
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook row for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_forms_page_type_properties .=
                '<div class="col-12 my-2" id="custom_form_hook_code_row" style="' . $custom_form_hook_code_row_style . '">
                    <label class="form-label" for="custom_form_hook_code">' . lang('Hook Code') . '</label>
                    <textarea id="custom_form_hook_code" name="custom_form_hook_code" class="form-control">' . h(($custom_form_properties['hook_code'] ?? '')) . '</textarea>
                </div>';
        }

        // Get MySQL version so we know if viewer filter feature is supported.
        $query = "SELECT VERSION()";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $mysql_version = $row[0];

        $mysql_version_parts = explode('.', $mysql_version);
        $mysql_major_version = $mysql_version_parts[0];
        $mysql_minor_version = $mysql_version_parts[1];

        // Assume that MySQL version is old until we find out otherwise.
        $mysql_version_new = false;

        // If the MySQL version is at least 4.1 then remember that MySQL version is new.
        if (
            (
                ($mysql_major_version == 4)
                && ($mysql_minor_version >= 1)
            )
            || ($mysql_major_version >= 5)
        ) {
            $mysql_version_new = true;
        }

        $output_viewer_filter_warning = '';

        // If mysql version is old then output warning about viewer filter feature not being supported.
        if ($mysql_version_new == false) {
            $output_viewer_filter_warning = '<div class="form-text alert alert-danger">' . lang('Sorry, not supported with your MySQL version.') . '</div>';
        }

        $output_forms_page_type_properties .=
            '<div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_contact_group_id_row" style="' . $custom_form_contact_group_id_row_style . '">
                <label class="form-label" for="custom_form_contact_group_id">' . lang('Add to Contact Group') . '</label>
                <select class="form-select" id="custom_form_contact_group_id" name="custom_form_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group')) )) . '-</option>' . select_contact_group(($custom_form_properties['contact_group_id'] ?? ''), $user) . '</select>
                ' . $custom_form_double_opt_in_switch . '
            </div>
            <div class="col-12 my-2" id="custom_form_submitter_email_row" style="' . $custom_form_submitter_email_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $custom_form_submitter_email_checked . ' id="custom_form_submitter_email" name="custom_form_submitter_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_submitter_email" />
                    <label class="form-check-label" for="custom_form_submitter_email">' . lang('E-mail Submitter') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_submitter_email">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-xl-4 my-1">
                                <label class="form-label" for="custom_form_submitter_email_from_email_address">' . lang('From E-mail Address') . '</label>
                                <input value="' . h($custom_form_properties['submitter_email_from_email_address'] ?? '') . '" type="text" class="form-control text-end" id="custom_form_submitter_email_from_email_address" name="custom_form_submitter_email_from_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-8 my-1">
                                <label class="form-label" for="custom_form_submitter_email_subject">' . lang('Subject') . '</label>
                                <input value="' . h(($custom_form_properties['submitter_email_subject'] ?? '')) . '" type="text" id="custom_form_submitter_email_subject" name="custom_form_submitter_email_subject" class="form-control" maxlength="255">
                            </div>
                            <div class="col-12 my-1">
                                <div class="col-12">
                                    <label class="form-label">' . lang('Format') . '</label>
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_submitter_email_format_plain_text" name="custom_form_submitter_email_format"' . $custom_form_submitter_email_format_plain_text_checked . ' value="plain_text" data-bs-target="#custom_form_submitter_email_format_plain_text_row" />
                                    <label for="custom_form_submitter_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_submitter_email_format_html" name="custom_form_submitter_email_format"' . $custom_form_submitter_email_format_html_checked . ' value="html"  data-bs-target="#custom_form_submitter_email_format_html_row"/>
                                    <label for="custom_form_submitter_email_format_html">' . lang('HTML') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_submitter_email_format_plain_text_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                              <label for="custom_form_submitter_email_body" class="form-label">' . lang('Body') . '</label>
                                              <textarea class="form-control" id="custom_form_submitter_email_body" name="custom_form_submitter_email_body" rows="3">' . h(($custom_form_properties['submitter_email_body'] ?? '')) . '</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="custom_form_submitter_email_format_html_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                                <label class="form-label" for="custom_form_submitter_email_page_id">' . lang('Page') . '</label>
                                                <select class="form-select" id="custom_form_submitter_email_page_id" name="custom_form_submitter_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($custom_form_properties['submitter_email_page_id'] ?? '')) . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_administrator_email_row" style="' . $custom_form_administrator_email_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $custom_form_administrator_email_checked . ' id="custom_form_administrator_email" name="custom_form_administrator_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_administrator_email" />
                    <label class="form-check-label" for="custom_form_administrator_email">' . lang('E-mail Administrator') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_administrator_email">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-xl-4 my-1">
                                <label class="form-label" for="custom_form_administrator_email_to_email_address">' . lang('To E-mail Address') . '</label>
                                <input value="' . h($custom_form_properties['administrator_email_to_email_address'] ?? '') . '" type="text" class="form-control text-end" id="custom_form_administrator_email_to_email_address" name="custom_form_administrator_email_to_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-4 my-1">
                                <label class="form-label" for="custom_form_administrator_email_bcc_email_address">' . lang('BCC E-mail Address') . '</label>
                                <input value="' . h($custom_form_properties['administrator_email_bcc_email_address'] ?? '') . '" type="text" class="form-control text-end" id="custom_form_administrator_email_bcc_email_address" name="custom_form_administrator_email_bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                            </div>
                            <div class="col-12 col-sm-12 col-xl-4 my-1">
                                <label class="form-label" for="custom_form_administrator_email_subject">' . lang('Subject') . '</label>
                                <input value="' . h(($custom_form_properties['administrator_email_subject'] ?? '')) . '" type="text" id="custom_form_administrator_email_subject" name="custom_form_administrator_email_subject" class="form-control" maxlength="255">
                            </div>
                            <div class="col-12 my-1">
                                <div class="col-12">
                                    <label class="form-label">' . lang('Format') . '</label>
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_administrator_email_format_plain_text" name="custom_form_administrator_email_format"' . $custom_form_administrator_email_format_plain_text_checked . ' value="plain_text" data-bs-target="#custom_form_administrator_email_format_plain_text_row" />
                                    <label for="custom_form_administrator_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_administrator_email_format_html" name="custom_form_administrator_email_format"' . $custom_form_administrator_email_format_html_checked . ' value="html"  data-bs-target="#custom_form_administrator_email_format_html_row"/>
                                    <label for="custom_form_administrator_email_format_html">' . lang('HTML') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_administrator_email_format_plain_text_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                              <label for="custom_form_administrator_email_body" class="form-label">' . lang('Body') . '</label>
                                              <textarea class="form-control" id="custom_form_administrator_email_body" name="custom_form_administrator_email_body" rows="3">' . h(($custom_form_properties['administrator_email_body'] ?? '')) . '</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="custom_form_administrator_email_format_html_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                                <label class="form-label" for="custom_form_administrator_email_page_id">' . lang('Page') . '</label>
                                                <select class="form-select" id="custom_form_administrator_email_page_id" name="custom_form_administrator_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($custom_form_properties['administrator_email_page_id'] ?? '')) . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_membership_row" style="' . $custom_form_membership_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $custom_form_membership_checked . ' id="custom_form_membership" name="custom_form_membership" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_membership" />
                    <label class="form-check-label" for="custom_form_membership">' . lang('Grant Membership Trial') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_membership">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label for="custom_form_membership_days" class="form-label">' . lang('Trial Length') . '</label>
                                <div class="input-group">
                                    <input value="' . h(($custom_form_properties['membership_days'] ?? '')) . '" type="text" name="custom_form_membership_days" id="custom_form_membership_days" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                    <span class="input-group-text">' . lang('day(s)') . '</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label class="form-label" for="custom_form_membership_start_page_id">' . lang('Set Member\'s Start Page to') . '</label>
                                <select class="form-select" id="custom_form_membership_start_page_id" name="custom_form_membership_start_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($custom_form_properties['membership_start_page_id'] ?? '')) . '</select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_private_row" style="' . $custom_form_private_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $custom_form_private_checked . ' id="custom_form_private" name="custom_form_private" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#toggle_custom_form_private" />
                    <label class="form-check-label" for="custom_form_private">' . lang('Grant Private Access') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="toggle_custom_form_private">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label class="form-label" for="custom_form_private_folder_id">' . lang('Set "View" Access to Folder') . '</label>
                                <select class="form-select" id="custom_form_private_folder_id" name="custom_form_private_folder_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('private folder')) )) . '-</option>' . select_folder(($custom_form_properties['private_folder_id'] ?? ''), 0, 0, 0, array(), array(), 'private') . '</select>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label for="custom_form_private_days" class="form-label">' . lang('Length') . '</label>
                                <div class="input-group">
                                    <input value="' . h(($custom_form_properties['private_days'] ?? '')) . '" type="text" name="custom_form_private_days" id="custom_form_private_days" class="form-control" size="7" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                    <span class="input-group-text">' . lang('day(s)') . '</span>
                                </div>
                                <div class="text-end form-text">' . lang('leave blank for no expiration') . '</div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label class="form-label" for="custom_form_private_start_page_id">' . lang('Set User\'s Start Page to') . '</label>
                                <select class="form-select" id="custom_form_private_start_page_id" name="custom_form_private_start_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($custom_form_properties['private_start_page_id'] ?? '')) . '</select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';

        // If commerce is enabled and the user has access to commerce, then output grant offer rows.
        if ((ECOMMERCE) && (USER_MANAGE_ECOMMERCE)) {
            $output_forms_page_type_properties .=
                '<div class="col-12 my-2" id="custom_form_offer_row" style="' . $custom_form_offer_row_style . '">
                    <div class="form-check form-switch">
                        <input value="1"' . $custom_form_offer_checked . ' id="custom_form_offer" name="custom_form_offer" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#toggle_custom_form_offer" />
                        <label class="form-check-label" for="custom_form_offer">' . lang('Grant Offer') . '</label>
                    </div>
                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="toggle_custom_form_offer">
                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                        <div class="popover-body">
                            <div class="row">
                                <div class="col-12 col-md-6 col-lg-4 my-1">
                                    <label class="form-label" for="custom_form_offer_id">' . lang('Offer') . '</label>
                                    <select class="form-select" id="custom_form_offer_id" name="custom_form_offer_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('offer')) )) . '-</option>' . select_offer(($custom_form_properties['offer_id'] ?? '')) . '</select>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-1">
                                    <label for="custom_form_offer_days" class="form-label">' . lang('Validity Length') . '</label>
                                    <div class="input-group">
                                        <input value="' . h(($custom_form_properties['offer_days'] ?? '')) . '" type="text" name="custom_form_offer_days" id="custom_form_offer_days" class="form-control" size="7" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                        <span class="input-group-text">' . lang('day(s)') . '</span>
                                    </div>
                                    <div class="text-end form-text">' . lang('leave blank for no expiration') . '</div>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-1">
                                    <label class="form-label" for="custom_form_offer_eligibility">' . lang('Eligibility') . '</label>
                                    <select class="form-select" id="custom_form_offer_eligibility" name="custom_form_offer_eligibility"><option value="everyone"' . $custom_form_offer_eligibility_everyone . '>' . lang('Everyone') . '</option><option value="new_contacts"' . $custom_form_offer_eligibility_new_contacts . '>' . lang('New Contacts') . '</option><option value="existing_contacts"' . $custom_form_offer_eligibility_existing_contacts . '>' . lang('Existing Contacts') . '</option></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        $output_forms_page_type_properties .=
            '<div class="col-12 my-1" id="custom_form_confirmation_type_row" style="' . $custom_form_confirmation_type_row_style . '">
                <div class="col-12">
                    <label class="form-label">' . lang('Confirmation Type') . '</label>
                </div>
                <div class="form-check  form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_confirmation_type_message" name="custom_form_confirmation_type"' . $custom_form_confirmation_type_message_checked . ' value="message" onclick="show_or_hide_custom_form_confirmation_type()" data-bs-target="#custom_form_confirmation_type_message_row" />
                    <label for="custom_form_confirmation_type_message">' . lang('Message') . '</label> 
                </div>
                <div class="form-check  form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_confirmation_type_page" name="custom_form_confirmation_type"' . $custom_form_confirmation_type_page_checked . ' value="page" onclick="show_or_hide_custom_form_confirmation_type()" data-bs-target="#custom_form_confirmation_type_page_row"/>
                    <label for="custom_form_confirmation_type_page">' . lang('Next Page') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_confirmation_type_message_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                              <label for="custom_form_confirmation_message" class="form-label">' . lang('Message') . '</label>
                              <textarea class="form-control" id="custom_form_confirmation_message" name="custom_form_confirmation_message" rows="3">' . h(prepare_rich_text_editor_content_for_output(($custom_form_properties['confirmation_message'] ?? ''))) . '</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_confirmation_type_page_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label class="form-label" for="custom_form_confirmation_page_id">' . lang('Next Page') . '</label>
                                <select class="form-select" id="custom_form_confirmation_page_id" name="custom_form_confirmation_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($custom_form_properties['confirmation_page_id'] ?? '')) . '</select>
                            </div>
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1"' . $custom_form_confirmation_alternative_page_checked . ' id="custom_form_confirmation_alternative_page" name="custom_form_confirmation_alternative_page" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_confirmation_alternative_page" />
                                    <label class="form-check-label" for="custom_form_confirmation_alternative_page">' . lang('Alternative Next Page') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_confirmation_alternative_page">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row p-1 border border-1 rounded bg-light">
                                            <div class="col-12 col-lg-6 my-1">
                                                <label class="form-label" for="custom_form_confirmation_alternative_page_contact_group_id">' . lang('If Contact Group') . '</label>
                                                <select class="form-select" id="custom_form_confirmation_alternative_page_contact_group_id" name="custom_form_confirmation_alternative_page_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('contact group')) )) . '-</option>' . select_contact_group(($custom_form_properties['confirmation_alternative_page_contact_group_id'] ?? ''), $user) . '</select>
                                            </div>
                                            <div class="col-12 col-lg-6 my-1">
                                                <label class="form-label" for="custom_form_confirmation_alternative_page_id">' . lang('Then Go to Page') . '</label>
                                                <select class="form-select" id="custom_form_confirmation_alternative_page_id" name="custom_form_confirmation_alternative_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($custom_form_properties['confirmation_alternative_page_id'] ?? '')) . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-1" id="custom_form_return_type_row" style="' . $custom_form_return_type_row_style . '">
                <div class="col-12">
                    <label class="form-label">' . lang('If User has already submitted form in the past, then show') . '</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_return_type_custom_form" name="custom_form_return_type"' . $custom_form_return_type_custom_form_checked . ' value="custom_form" onclick="show_or_hide_custom_form_return_type()"  />
                    <label for="custom_form_return_type_custom_form">' . lang('Custom Form') . '</label> 
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_return_type_message" name="custom_form_return_type"' . $custom_form_return_type_message_checked . ' value="message" onclick="show_or_hide_custom_form_return_type()" data-bs-target="#custom_form_return_type_message_row"/>
                    <label for="custom_form_return_type_message">' . lang('Message') . '</label>
                </div>
                <div class="form-check  form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_return_type_page" name="custom_form_return_type"' . $custom_form_return_type_page_checked . ' value="page" onclick="show_or_hide_custom_form_return_type()" data-bs-target="#custom_form_return_type_page_row"/>
                    <label for="custom_form_return_type_page">' . lang('Page') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_return_type_message_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(140px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <label for="custom_form_return_message" class="form-label">' . lang('Message') . '</label>
                                <textarea class="form-control" id="custom_form_return_message" name="custom_form_return_message" rows="3">' . h(prepare_rich_text_editor_content_for_output(($custom_form_properties['return_message'] ?? ''))) . '</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_return_type_page_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(230px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label class="form-label" for="custom_form_return_page_id">' . lang('Page') . '</label>
                                <select class="form-select" id="custom_form_return_page_id" name="custom_form_return_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($custom_form_properties['return_page_id'] ?? '')) . '</select>
                            </div>
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1"' . $custom_form_return_alternative_page_checked . ' id="custom_form_return_alternative_page" name="custom_form_return_alternative_page" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_return_alternative_page" />
                                    <label class="form-check-label" for="custom_form_return_alternative_page">' . lang('Alternative Next Page') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_return_alternative_page">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row p-1 border border-1 rounded bg-light">
                                            <div class="col-12 col-lg-6 my-1">
                                                <label class="form-label" for="custom_form_return_alternative_page_contact_group_id">' . lang('If Contact Group') . '</label>
                                                <select class="form-select" id="custom_form_return_alternative_page_contact_group_id" name="custom_form_return_alternative_page_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('contact group')) )) . '-</option>' . select_contact_group(($custom_form_properties['return_alternative_page_contact_group_id'] ?? ''), $user) . '</select>
                                            </div>
                                            <div class="col-12 col-lg-6 my-1">
                                                <label class="form-label" for="custom_form_return_alternative_page_id">' . lang('Then Go to Page') . '</label>
                                                <select class="form-select" id="custom_form_return_alternative_page_id" name="custom_form_return_alternative_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($custom_form_properties['return_alternative_page_id'] ?? '')) . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-3" id="custom_form_pretty_urls_row" style="' . $custom_form_pretty_urls_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $custom_form_pretty_urls_checked . ' id="custom_form_pretty_urls" name="custom_form_pretty_urls" class="form-check-input" type="checkbox" role="switch" />
                    <label class="form-check-label" for="custom_form_pretty_urls">' . lang('Enable Pretty URLs') . '</label>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_confirmation_continue_button_label_row" style="' . $custom_form_confirmation_continue_button_label_row_style . '">
                <label for="custom_form_confirmation_continue_button_label" class="form-label">' . lang('Continue Button Label') . '</label>
                <input value="' . h($custom_form_confirmation_properties['continue_button_label'] ?? '') . '" type="text" name="custom_form_confirmation_continue_button_label" id="custom_form_confirmation_continue_button_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_confirmation_next_page_id_row" style="' . $custom_form_confirmation_next_page_id_row_style . '">
                <label class="form-label" for="custom_form_confirmation_next_page_id">' . lang('Next Page') . '</label>
                <select class="form-select" id="custom_form_confirmation_next_page_id" name="custom_form_confirmation_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($custom_form_confirmation_properties['next_page_id'] ?? '')) . '</select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_list_view_custom_form_page_id_row" style="' . $form_list_view_custom_form_page_id_row_style . '">
                <label class="form-label" for="form_list_view_custom_form_page_id">' . lang('Custom Form') . '</label>
                <select class="form-select" id="form_list_view_custom_form_page_id" name="form_list_view_custom_form_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('custom form')) )) . '-</option>' . select_custom_form(($form_list_view_properties['custom_form_page_id'] ?? ''), $user) . '</select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_list_view_form_item_view_page_id_row" style="' . $form_list_view_form_item_view_page_id_row_style . '">
                <label class="form-label" for="form_list_view_form_item_view_page_id">' . lang('Form Item View') . '</label>
                <select class="form-select" id="form_list_view_form_item_view_page_id" name="form_list_view_form_item_view_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('form item view page')) )) . '-</option>' . select_page(($form_list_view_properties['form_item_view_page_id'] ?? ''), 'form item view') . '</select>
            </div>
            <div class="col-12 my-1" id="form_list_view_viewer_filter_row" style="' . $form_list_view_viewer_filter_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $form_list_view_viewer_filter_checked . ' id="form_list_view_viewer_filter" name="form_list_view_viewer_filter" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_form_list_view_viewer_filter" />
                    <label class="form-check-label" for="form_list_view_viewer_filter">' . lang('Enable Viewer Filter') . '</label>
                    ' . $output_viewer_filter_warning . '
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_form_list_view_viewer_filter">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1"' . $form_list_view_viewer_filter_submitter_checked . ' id="form_list_view_viewer_filter_submitter" name="form_list_view_viewer_filter_submitter" class="form-check-input"  type="checkbox" role="switch"/>
                                    <label class="form-check-label" for="form_list_view_viewer_filter_submitter">' . lang('Include Forms from Submitter') . '</label>
                                </div>
                            </div>
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1"' . $form_list_view_viewer_filter_watcher_checked . ' id="form_list_view_viewer_filter_watcher" name="form_list_view_viewer_filter_watcher" class="form-check-input"  type="checkbox" role="switch"/>
                                    <label class="form-check-label" for="form_list_view_viewer_filter_watcher">' . lang('Include Forms for Watchers') . '</label>
                                </div>
                            </div>
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1"' . $form_list_view_viewer_filter_editor_checked . ' id="form_list_view_viewer_filter_editor" name="form_list_view_viewer_filter_editor" class="form-check-input"  type="checkbox" role="switch"/>
                                    <label class="form-check-label" for="form_list_view_viewer_filter_editor">' . lang('Include Forms for Form Editors') . '</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_item_view_custom_form_page_id_row" style="' . $form_item_view_custom_form_page_id_row_style . '">
                <label class="form-label" for="form_item_view_custom_form_page_id">' . lang('Custom Form') . '</label>
                <select class="form-select" id="form_item_view_custom_form_page_id" name="form_item_view_custom_form_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('custom form')) )) . '-</option>' . select_custom_form(($form_item_view_properties['custom_form_page_id'] ?? ''), $user) . '</select>
            </div>
            <div class="col-12 my-3" id="form_item_view_submitter_security_row" style="' . $form_item_view_submitter_security_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $form_item_view_submitter_security_checked . ' id="form_item_view_submitter_security" name="form_item_view_submitter_security" class="form-check-input"  type="checkbox" role="switch"/>
                    <label class="form-check-label" for="form_item_view_submitter_security">' . lang('Allow only submitter and watchers to view his/her submitted form(s)') . '</label>
                </div>
            </div>
            <div class="col-12 my-3" id="form_item_view_submitted_form_editable_by_registered_user_row" style="' . $form_item_view_submitted_form_editable_by_registered_user_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $form_item_view_submitted_form_editable_by_registered_user_checked . ' id="form_item_view_submitted_form_editable_by_registered_user" name="form_item_view_submitted_form_editable_by_registered_user" class="form-check-input collapse-switcher"  type="checkbox" role="switch" data-bs-target="#show_or_hide_form_item_view_editor"/>
                    <label class="form-check-label" for="form_item_view_submitted_form_editable_by_registered_user">' . lang('Allow any registered user to edit submitted form(s)') . '</label>
                </div>
            </div>
            <div class="col-12" id="form_item_view_submitted_form_editable_by_submitter_row" style="' . $form_item_view_submitted_form_editable_by_submitter_row_style . '">
                <div class="my-3 collapse show-reverse" id="show_or_hide_form_item_view_editor">
                    <div class="form-check form-switch">
                        <input value="1" ' . $form_item_view_submitted_form_editable_by_submitter_checked . ' id="form_item_view_submitted_form_editable_by_submitter" name="form_item_view_submitted_form_editable_by_submitter" class="form-check-input"  type="checkbox" role="switch"/>
                        <label class="form-check-label" for="form_item_view_submitted_form_editable_by_submitter">' . lang('Allow submitter to edit his/her submitted form(s)') . '</label>
                    </div>
                </div>
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook row for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_forms_page_type_properties .=
                '<div class="col-12 my-2" id="form_item_view_hook_code_row" style="' . $form_item_view_hook_code_row_style . '">
                    <label class="form-label" for="form_item_view_hook_code">' . lang('Hook Code') . '</label>
                    <textarea id="form_item_view_hook_code" name="form_item_view_hook_code" class="form-control">' . h(($form_item_view_properties['hook_code'] ?? '')) . '</textarea>
                </div>';
        }

        $output_forms_page_type_properties .=
            '<div class="col-12 my-2" id="form_view_directory_form_list_views_row" style="' . $form_view_directory_form_list_views_row_style . '">
                <h5>' . lang('Form List Views') . '</h5>
                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                    <div class="card-body overflow-auto" >
                        ' . $output_form_view_directory_form_list_view_rows . '
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="form_view_directory_summary_row" style="' . $form_view_directory_summary_row_style . '">
                <div class="form-check form-switch">
                    <input value="1"' . $form_view_directory_summary_checked . ' id="form_view_directory_summary" name="form_view_directory_summary" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_form_view_directory_summary" />
                    <label class="form-check-label" for="form_view_directory_summary">' . lang('Display Summary') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_form_view_directory_summary">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label for="form_view_directory_summary_days" class="form-label">' . lang('Date Range') . '</label>
                                <div class="input-group">
                                    <input value="' . $form_view_directory_summary_days . '" type="text" name="form_view_directory_summary_days" id="form_view_directory_summary_days" class="form-control" size="7" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                    <span class="input-group-text">' . lang('day(s)') . '</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label for="form_view_directory_summary_maximum_number_of_results" class="form-label">' . lang('Maximum Number of Results') . '</label>
                                <input value="' . $form_view_directory_summary_maximum_number_of_results . '" type="text" name="form_view_directory_summary_maximum_number_of_results" id="form_view_directory_summary_maximum_number_of_results" class="form-control" size="2" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_view_directory_form_list_view_heading_row" style="' . $form_view_directory_form_list_view_heading_row_style . '">
                <label for="form_view_directory_form_list_view_heading" class="form-label">' . lang('Form List View Heading') . '</label>
                <input value="' . h($form_view_directory_form_list_view_heading) . '" type="text" name="form_view_directory_form_list_view_heading" id="form_view_directory_form_list_view_heading" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_view_directory_subject_heading_row" style="' . $form_view_directory_subject_heading_row_style . '">
                <label for="form_view_directory_subject_heading" class="form-label">' . lang('Subject Heading') . '</label>
                <input value="' . h($form_view_directory_subject_heading) . '" type="text" name="form_view_directory_subject_heading" id="form_view_directory_subject_heading" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_view_directory_number_of_submitted_forms_heading_row" style="' . $form_view_directory_number_of_submitted_forms_heading_row_style . '">
                <label for="form_view_directory_number_of_submitted_forms_heading" class="form-label">' . lang('Number of Submitted Forms Heading') . '</label>
                <input value="' . h($form_view_directory_number_of_submitted_forms_heading) . '" type="text" name="form_view_directory_number_of_submitted_forms_heading" id="form_view_directory_number_of_submitted_forms_heading" maxlength="50" class="form-control" />
            </div>';
    }
    
    if (CALENDARS == true) {
        // get calendars so user can select calendars for calendar view and calendar event view
        $query =
            "SELECT
               id,
               name
            FROM calendars
            ORDER BY name";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $calendars = array();
        
        while ($row = mysqli_fetch_assoc($result)) {
            $calendars[] = $row;
        }
        
        $calendar_view_calendar_check_boxes = '';
        $calendar_event_view_calendars_check_boxes = '';
        
        // loop through all calendars in order to check if user has access to calendar and if calendar should be checked
        foreach ($calendars as $calendar) {
            // if user has access to calendar, then continue
            if (validate_calendar_access($calendar['id']) == true) {
                // check if calendar should be checked for calendar view
                $query =
                    "SELECT calendar_id
                    FROM calendar_views_calendars_xref
                    WHERE
                        (calendar_id = '" . escape($calendar['id']) . "')
                        AND (page_id = '" . escape($page_id) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                $checked = '';
                
                // if calendar should be checked, then prepare checkbox to be checked
                if (mysqli_num_rows($result) > 0) {
                    $checked = ' checked="checked"';
                }
                
                $calendar_view_calendar_check_boxes .= '<div class="form-check"><input type="checkbox" id="calendar_view_calendar_' . $calendar['id'] . '" name="calendar_view_calendar_' . $calendar['id'] . '" value="1"' . $checked . ' class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="calendar_view_calendar_' . $calendar['id'] . '">' . h($calendar['name']) . '</label></div>';
                
                // check if calendar should be checked for calendar event view
                $query =
                    "SELECT calendar_id
                    FROM calendar_event_views_calendars_xref
                    WHERE
                        (calendar_id = '" . escape($calendar['id']) . "')
                        AND (page_id = '" . escape($page_id) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                $checked = '';
                
                // if calendar should be checked, then prepare checkbox to be checked
                if (mysqli_num_rows($result) > 0) {
                    $checked = ' checked="checked"';
                }
                
                $calendar_event_view_calendar_check_boxes .= '<div class="form-check"><input type="checkbox" id="calendar_event_view_calendar_' . $calendar['id'] . '" name="calendar_event_view_calendar_' . $calendar['id'] . '" value="1"' . $checked . ' class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="calendar_event_view_calendar_' . $calendar['id'] . '">' . h($calendar['name']) . '</label></div>';
            }
        }
        
        // if notes is enabled for calendar event view prepare to check checkbox
        if (($calendar_event_view_properties['notes'] ?? '') == 1) {
            $calendar_event_view_notes_checked = ' checked="checked"';
        } else {
            $calendar_event_view_notes_checked = '';
        }
        
        $output_calendars_page_type_properties =
            '<div class="col-12 my-2" id="calendar_view_calendars_row" style="' . $calendar_view_calendars_row_style . '">
                <h5>' . lang('Calendars') . '</h5>
                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                    <div class="card-header border-0 bg-reset">
                        <div class="form-check form-switch">
                            <input id="multiselect-checkbox-checker-0" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                            <label for="multiselect-checkbox-checker-0" class="form-check-label">' . lang('Select All') . '</label>
                        </div>
                    </div>
                    <div class="card-body overflow-auto" style="max-height:300px">
                        ' . $calendar_view_calendar_check_boxes . '
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="calendar_view_default_view_row" style="' . $calendar_view_default_view_row_style . '">
                <label for="calendar_view_default_view" class="form-label">' . lang('View Type') . '</label>
                <select class="form-select" name="calendar_view_default_view" id="calendar_view_default_view" onchange="show_or_hide_calendar_view_number_of_upcoming_events()"><option value="monthly"' . $calendar_view_default_view_monthly . '>' . lang('Monthly') . '</option><option value="weekly"' . $calendar_view_default_view_weekly . '>' . lang('Weekly') . '</option><option value="upcoming"' . $calendar_view_default_view_upcoming . '>' . lang('Upcoming') . '</option></select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="calendar_view_number_of_upcoming_events_row" style="' . $calendar_view_number_of_upcoming_events_row_style . '">
                <label for="calendar_view_number_of_upcoming_events" class="form-label">' . lang('Number of Events') . '</label>
                <input value="' . h($calendar_view_number_of_upcoming_events_value) . '" name="calendar_view_number_of_upcoming_events" id="calendar_view_number_of_upcoming_events" type="text" maxlength="2" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="calendar_view_calendar_event_view_page_id_row" style="' . $calendar_view_calendar_event_view_page_id_row_style . '">
                <label for="calendar_view_calendar_event_view_page_id" class="form-label">' . lang('Calendar Event View') . '</label>
                <select class="form-select" name="calendar_view_calendar_event_view_page_id" id="calendar_view_calendar_event_view_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('calendar event view page')) )) . '-</option>' . select_page(($calendar_view_properties['calendar_event_view_page_id'] ?? ''), 'calendar event view') . '</select>
            </div>
            <div class="col-12 my-2" id="calendar_event_view_calendars_row" style="' . $calendar_event_view_calendars_row_style . '">
                <h5>' . lang('Calendars') . '</h5>
                <div class="card multiselect-checkbox-container rounded-0 mb-4">
                    <div class="card-header border-0 bg-reset">
                        <div class="form-check form-switch">
                            <input id="multiselect-checkbox-checker-1" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                            <label for="multiselect-checkbox-checker-1" class="form-check-label">' . lang('Select All') . '</label>
                        </div>
                    </div>
                    <div class="card-body overflow-auto" style="max-height:300px">
                        ' . $calendar_event_view_calendar_check_boxes . '
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="calendar_event_view_back_button_label_row" style="' . $calendar_event_view_back_button_label_row_style . '">
                <label for="calendar_event_view_back_button_label" class="form-label">' . lang('Back Button Label') . '</label>
                <input value="' . h($calendar_event_view_properties['back_button_label'] ?? '') . '" name="calendar_event_view_back_button_label" id="calendar_event_view_back_button_label" type="text" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 my-2" id="calendar_event_view_notes_row" style="' . $calendar_event_view_notes_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="calendar_event_view_notes" name="calendar_event_view_notes" value="1"' . $calendar_event_view_notes_checked . ' />
                    <label class="form-check-label" for="calendar_event_view_notes">' . lang('Show Notes') . '</label>
                </div>
            </div>';
    }
    
    if (AFFILIATE_PROGRAM == true) {
        $output_affiliate_page_type_properties =
            '<div class="col-12 col-md-6 col-lg-4 my-2" id="affiliate_sign_up_form_terms_page_id_row" style="' . $affiliate_sign_up_form_terms_page_id_row_style . '">
                <label for="affiliate_sign_up_form_terms_page_id" class="form-label">' . lang('Terms Page') . '</label>
                <select class="form-select" name="affiliate_sign_up_form_terms_page_id" id="affiliate_sign_up_form_terms_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($affiliate_sign_up_form_properties['terms_page_id'] ?? '')) . '</select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="affiliate_sign_up_form_submit_button_label_row" style="' . $affiliate_sign_up_form_submit_button_label_row_style . '">
                <label for="affiliate_sign_up_form_submit_button_label" class="form-label">' . lang('Submit Button Label') . '</label>
                <input value="' . h($affiliate_sign_up_form_properties['submit_button_label'] ?? '') . '" type="text" name="affiliate_sign_up_form_submit_button_label" id="affiliate_sign_up_form_submit_button_label" placeholder="' . lang('Sign Up') . '"  class="form-control" maxlength="50"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="affiliate_sign_up_form_next_page_id_row" style="' . $affiliate_sign_up_form_next_page_id_row_style . '">
                <label class="form-label" for="affiliate_sign_up_form_next_page_id">' . lang('Next Page') . '</label>
                <select class="form-select" id="affiliate_sign_up_form_next_page_id" name="affiliate_sign_up_form_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('affiliate sign up confirmation page')) )) . '-</option>' . select_page(($affiliate_sign_up_form_properties['next_page_id'] ?? ''), 'affiliate sign up confirmation') . '</select>
            </div>';
    }
    
    $output_page_type_selector = 
        '<div class="col-12 col-md-4 col-lg-3 col-xl-2">
            <div class="card my-4 position-sticky" style="top:56px;">
                <label for="type" class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Page Type') . '
                </label>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <select id="page_type" name="type" class="form-select" onchange="change_page_type(this.options[this.selectedIndex].value)">' . select_page_type($page_type, $user) . '</select>
                            <script>
                                var original_page_type = "' . $page_type . '";
                                $(document).ready(function() {
                                    change_page_type($("select#page_type option:selected").val());
                                });
                            </script>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

    $output_page_type_properties =
        '<a name="interactive_page_feature"></a>
        <div class="col-12 collapse" id="options_row">
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('System Options') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 my-2" id="layout_type_row" style="' . $layout_type_row_style . '">
                            <label class="form-label" for="">'. lang('Layout Type') . '</label>
                            <div class="form-check">
                                <input value="system" class="form-check-input" type="radio" id="layout_type_system" name="layout_type"' . $layout_type_system_checked . '>
                                <label class="form-check-label" for="layout_type_system">'. lang('System') . '</label>
                            </div>
                            <div class="form-check">
                                <input value="custom" class="form-check-input" type="radio" id="layout_type_custom" name="layout_type"' . $layout_type_custom_option_disabled . $layout_type_custom_checked . '>
                                <label title="' . $layout_type_custom_label_title . '" class="form-check-label' . $layout_type_custom_label_class . '" for="layout_type_custom">'. lang('Custom') . '</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 my-2" id="email_a_friend_submit_button_label_row" style="' . $email_a_friend_submit_button_label_row_style . '">
                            <label for="email_a_friend_submit_button_label" class="form-label">' . lang('Submit Button Label') . '</label>
                            <input value="' . h($email_a_friend_properties['submit_button_label'] ?? '') . '" type="text" name="email_a_friend_submit_button_label" id="email_a_friend_submit_button_label" placeholder="' . lang('Submit') . '"  class="form-control" maxlength="50"/>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 my-2" id="email_a_friend_next_page_id_row" style="' . $email_a_friend_next_page_id_row_style . '">
                            <label for="email_a_friend_next_page_id" class="form-label">' . lang('Next Page') . '</label>
                            <select name="email_a_friend_next_page_id" id="email_a_friend_next_page_id" class="form-select"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($email_a_friend_properties['next_page_id'] ?? '')) . '</select>
                        </div>
                        <div class="col-12 my-2" id="folder_view_pages_row" style="' . $folder_view_pages_row_style . '">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="folder_view_pages" name="folder_view_pages" value="1"' . $folder_view_pages_checked . '>
                                <label class="form-check-label" for="folder_view_pages">' . lang('Include Pages') . '</label>
                            </div>
                        </div>
                        <div class="col-12 my-2" id="folder_view_files_row" style="' . $folder_view_files_row_style . '">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="folder_view_files" name="folder_view_files" value="1"' . $folder_view_files_checked . '>
                                <label class="form-check-label" for="folder_view_files">' . lang('Include Files') . '</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 col-lg-3 my-2" id="photo_gallery_number_of_columns_row" style="' . $photo_gallery_number_of_columns_row_style . '">
                            <label for="photo_gallery_number_of_columns" class="form-label">' . lang('Number of Columns') . '</label>
                            <input value="' . $photo_gallery_number_of_columns . '" type="text" name="photo_gallery_number_of_columns" id="photo_gallery_number_of_columns" class="form-control" maxlength="2" inputmode="numeric" data-inputmask-alias="decimal"/>
                        </div>
                        <div class="col-12 col-md-8 col-lg-6 col-xl-4 my-2" id="photo_gallery_thumbnail_max_size_row" style="' . $photo_gallery_thumbnail_max_size_row_style . '">
                            <label for="photo_gallery_thumbnail_max_size" class="form-label">' . lang('Thumbnail Max Size') . '</label>
                            <div class="input-group">
                                <input value="' . $photo_gallery_thumbnail_max_size . '" type="text" name="photo_gallery_thumbnail_max_size" id="photo_gallery_thumbnail_max_size" class="form-control" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="100"  style="text-align: right;" />
                                <label for="photo_gallery_thumbnail_max_size"  class="input-group-text">' . lang('pixels') . '</label>
                            </div>
                        </div>
                        ' . $output_search_results_page_type_properties . '
                        <div class="col-12 my-2" id="update_address_book_address_type_row" style="' . $update_address_book_address_type_row_style . '">
                            <div class="form-check form-switch">
                                <input value="1"' . $update_address_book_address_type_checked . ' id="update_address_book_address_type" name="update_address_book_address_type" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#update_address_book_address_type_page_id_row" />
                                <label class="form-check-label" for="update_address_book_address_type">' . lang('Enable Address Type') . '</label>
                            </div>
                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="update_address_book_address_type_page_id_row">
                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                <div class="popover-body">
                                    <div class="row">
                                        <div class="col-12 col-md-12 my-1">
                                            <label for="update_address_book_address_type_page_id" class="form-label">' . lang('Address Type Page') . '</label>
                                            <select name="update_address_book_address_type_page_id" id="update_address_book_address_type_page_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(($update_address_book_properties['address_type_page_id'] ?? '')) . '</select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ' . $output_ecommerce_page_type_properties . '
                        ' . $output_forms_page_type_properties . '
                        ' . $output_calendars_page_type_properties . '
                        ' . $output_affiliate_page_type_properties . '
                    </div>
                </div>
            </div>
        </div>';

    return array(
        'selector' => $output_page_type_selector,
        'properties' => $output_page_type_properties,
        'wysiwyg' => $output_wysiwyg_editor_code,
    );
}

/**
 * The properties a new page starts from, for every type that has them.
 *
 * @return array  Page type name => properties, in the shape of a row of the
 *                type's <type>_pages table
 */
function pg_page_form_new_page_properties()
{
    $order_receipt_email = array(
        'order_receipt_email' => '1',
        'order_receipt_email_subject' => lang('Order Receipt') . ' #',
        'order_receipt_email_format' => 'plain_text',
        'order_receipt_email_header' => lang('Order Receipt'),
    );

    return array(
        'custom form' => array(
            'submitter_email_format' => 'plain_text',
            'administrator_email_format' => 'plain_text',
            'confirmation_type' => 'message',
            'return_type' => 'custom_form',
        ),
        // The three viewer filters start on, which the form reads from a
        // viewer filter that is switched off.
        'form list view' => array(
            'viewer_filter' => '0',
        ),
        'form view directory' => array(
            'summary' => '1',
            'subject_heading' => lang('Subject'),
        ),
        'catalog' => array(
            'number_of_featured_items' => '0',
            'number_of_new_items' => '0',
        ),
        'express order' => array(
            'product_description_type' => 'full_description',
            'update_button_label' => lang('Update Cart'),
        ) + $order_receipt_email,
        'order form' => array(
            'add_button_label' => lang('Continue'),
        ),
        'shopping cart' => array(
            'product_description_type' => 'full_description',
            'update_button_label' => lang('Update Cart'),
            'checkout_button_label' => lang('Checkout'),
        ),
        'shipping method' => array(
            'product_description_type' => 'full_description',
        ),
        'order preview' => array(
            'product_description_type' => 'full_description',
        ) + $order_receipt_email,
        'order receipt' => array(
            'product_description_type' => 'full_description',
        ),
    );
}

/**
 * The per-type options of a submitted page form, in the shape
 * create_or_update_page_type_record() takes.
 *
 * Only what the form says is read here. Fields a basic user may not be
 * allowed to set (a custom form's contact group and private folder, the
 * custom form behind a form list or item view) come back as submitted, and
 * the offer of a custom form is left out: the create and the edit screens
 * check those against different things (nothing, or the stored value) and
 * fill them in themselves.
 *
 * @param string $page_type
 * @param int    $page_id
 * @return array  Empty for a type that has no properties table
 */
function pg_page_form_type_properties_from_post($page_type, $page_id)
{
    // An unticked check box is not posted at all; it is stored as 0.
    $field = function ($name, $default = '') {
        return $_POST[$name] ?? $default;
    };

    $hooks = (defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2);

    // A product description type that was not posted falls back to the one
    // a new page starts with.
    $description_type = function ($prefix) use ($field) {
        return $field($prefix . '_product_description_type', 'full_description');
    };

    $properties = array();

    switch ($page_type) {
        case 'email a friend':
            $properties = array(
                'page_id' => $page_id,
                'submit_button_label' => $field('email_a_friend_submit_button_label'),
                'next_page_id' => $field('email_a_friend_next_page_id')
            );

            break;

        case 'folder view':
            $properties = array(
                'page_id' => $page_id,
                'pages' => $field('folder_view_pages', 0),
                'files' => $field('folder_view_files', 0)
            );

            break;

        case 'photo gallery':
            $properties = array(
                'page_id' => $page_id,
                'number_of_columns' => $field('photo_gallery_number_of_columns'),
                'thumbnail_max_size' => $field('photo_gallery_thumbnail_max_size')
            );

            break;

        case 'search results':
            $properties = array(
                'page_id' => $page_id,
                'search_folder_id' => $field('search_results_search_folder_id'),
                'search_catalog_items' => $field('search_results_search_catalog_items', 0),
                'product_group_id' => $field('search_results_product_group_id'),
                'catalog_detail_page_id' => $field('search_results_catalog_detail_page_id')
            );

            break;

        case 'update address book':
            $properties = array(
                'page_id' => $page_id,
                'address_type' => $field('update_address_book_address_type', 0),
                'address_type_page_id' => $field('update_address_book_address_type_page_id')
            );

            break;

        case 'custom form':
            $properties = array(
                'page_id' => $page_id,
                'form_name' => $field('custom_form_form_name'),
                'enabled' => $field('custom_form_enabled', 0),
                'quiz' => $field('custom_form_quiz', 0),
                'quiz_pass_percentage' => $field('custom_form_quiz_pass_percentage'),
                'label_column_width' => $field('custom_form_label_column_width'),
                'watcher_page_id' => $field('custom_form_watcher_page_id'),
                'save' => $field('custom_form_save', 0),
                'submit_button_label' => $field('custom_form_submit_button_label'),
                'auto_registration' => $field('custom_form_auto_registration', 0),
                'submitter_email' => $field('custom_form_submitter_email', 0),
                'submitter_email_from_email_address' => $field('custom_form_submitter_email_from_email_address'),
                'submitter_email_subject' => $field('custom_form_submitter_email_subject'),
                'submitter_email_format' => $field('custom_form_submitter_email_format'),
                'submitter_email_body' => $field('custom_form_submitter_email_body'),
                'submitter_email_page_id' => $field('custom_form_submitter_email_page_id'),
                'administrator_email' => $field('custom_form_administrator_email', 0),
                'administrator_email_to_email_address' => $field('custom_form_administrator_email_to_email_address'),
                'administrator_email_bcc_email_address' => $field('custom_form_administrator_email_bcc_email_address'),
                'administrator_email_subject' => $field('custom_form_administrator_email_subject'),
                'administrator_email_format' => $field('custom_form_administrator_email_format'),
                'administrator_email_body' => $field('custom_form_administrator_email_body'),
                'administrator_email_page_id' => $field('custom_form_administrator_email_page_id'),
                'contact_group_id' => $field('custom_form_contact_group_id'),
                'membership' => $field('custom_form_membership', 0),
                'membership_days' => $field('custom_form_membership_days'),
                'membership_start_page_id' => $field('custom_form_membership_start_page_id'),
                'private' => $field('custom_form_private', 0),
                'private_folder_id' => $field('custom_form_private_folder_id'),
                'private_days' => $field('custom_form_private_days'),
                'private_start_page_id' => $field('custom_form_private_start_page_id'),
                'confirmation_type' => $field('custom_form_confirmation_type'),
                'confirmation_message' => prepare_rich_text_editor_content_for_input($field('custom_form_confirmation_message')),
                'confirmation_page_id' => $field('custom_form_confirmation_page_id'),
                'confirmation_alternative_page' => $field('custom_form_confirmation_alternative_page', 0),
                'confirmation_alternative_page_contact_group_id' => $field('custom_form_confirmation_alternative_page_contact_group_id'),
                'confirmation_alternative_page_id' => $field('custom_form_confirmation_alternative_page_id'),
                'return_type' => $field('custom_form_return_type'),
                'return_message' => prepare_rich_text_editor_content_for_input($field('custom_form_return_message')),
                'return_page_id' => $field('custom_form_return_page_id'),
                'return_alternative_page' => $field('custom_form_return_alternative_page', 0),
                'return_alternative_page_contact_group_id' => $field('custom_form_return_alternative_page_contact_group_id'),
                'return_alternative_page_id' => $field('custom_form_return_alternative_page_id'),
                'pretty_urls' => $field('custom_form_pretty_urls', 0)
            );

            if ($hooks) {
                $properties['hook_code'] = $field('custom_form_hook_code');
            }

            // Written only once the 11.0.27 column is there: every key here
            // becomes a column of the save query.
            if (pg_fs_ready()) {
                $properties['double_opt_in'] = $field('custom_form_double_opt_in', 0) ? 1 : 0;
            }

            break;

        case 'custom form confirmation':
            $properties = array(
                'page_id' => $page_id,
                'continue_button_label' => $field('custom_form_confirmation_continue_button_label'),
                'next_page_id' => $field('custom_form_confirmation_next_page_id')
            );

            break;

        case 'form list view':
            $properties = array(
                'page_id' => $page_id,
                'custom_form_page_id' => $field('form_list_view_custom_form_page_id'),
                'form_item_view_page_id' => $field('form_list_view_form_item_view_page_id'),
                'viewer_filter' => $field('form_list_view_viewer_filter', 0),
                'viewer_filter_submitter' => $field('form_list_view_viewer_filter_submitter', 0),
                'viewer_filter_watcher' => $field('form_list_view_viewer_filter_watcher', 0),
                'viewer_filter_editor' => $field('form_list_view_viewer_filter_editor', 0)
            );

            break;

        case 'form item view':
            $properties = array(
                'page_id' => $page_id,
                'custom_form_page_id' => $field('form_item_view_custom_form_page_id'),
                'submitter_security' => $field('form_item_view_submitter_security', 0),
                'submitted_form_editable_by_registered_user' => $field('form_item_view_submitted_form_editable_by_registered_user', 0),
                'submitted_form_editable_by_submitter' => $field('form_item_view_submitted_form_editable_by_submitter', 0)
            );

            if ($hooks) {
                $properties['hook_code'] = $field('form_item_view_hook_code');
            }

            break;

        case 'form view directory':
            $form_list_view_heading = $field('form_view_directory_form_list_view_heading');
            $subject_heading = $field('form_view_directory_subject_heading');
            $number_of_submitted_forms_heading = $field('form_view_directory_number_of_submitted_forms_heading');

            // if the form list view heading is blank, then set it to the default value
            if ($form_list_view_heading == '') {
                $form_list_view_heading = lang('Forum');
            }

            // if the subject heading is blank, then set it to the default value
            if ($subject_heading == '') {
                $subject_heading = 'Subject';
            }

            // if the number of submitted forms heading is blank, then set it to the default value
            if ($number_of_submitted_forms_heading == '') {
                $number_of_submitted_forms_heading = lang('Forms');
            }

            $properties = array(
                'page_id' => $page_id,
                'summary' => $field('form_view_directory_summary', 0),
                'summary_days' => $field('form_view_directory_summary_days'),
                'summary_maximum_number_of_results' => $field('form_view_directory_summary_maximum_number_of_results'),
                'form_list_view_heading' => $form_list_view_heading,
                'subject_heading' => $subject_heading,
                'number_of_submitted_forms_heading' => $number_of_submitted_forms_heading
            );

            break;

        case 'calendar view':
            $properties = array(
                'page_id' => $page_id,
                'default_view' => $field('calendar_view_default_view'),
                'number_of_upcoming_events' => $field('calendar_view_number_of_upcoming_events'),
                'calendar_event_view_page_id' => $field('calendar_view_calendar_event_view_page_id')
            );

            break;

        case 'calendar event view':
            $properties = array(
                'page_id' => $page_id,
                'notes' => $field('calendar_event_view_notes', 0),
                'back_button_label' => $field('calendar_event_view_back_button_label')
            );

            break;

        case 'catalog':
            // if number_of_columns is less than 1, then set number_of_columns to 1
            if ($field('catalog_number_of_columns') < 1) {
                $catalog_number_of_columns = 1;

            // else number_of_columns is not less than 1, so set number_of_columns to what user entered
            } else {
                $catalog_number_of_columns = $field('catalog_number_of_columns');
            }

            $properties = array(
                'page_id' => $page_id,
                'product_group_id' => $field('catalog_product_group_id'),
                'menu' => $field('catalog_menu', 0),
                'search' => $field('catalog_search', 0),
                'number_of_featured_items' => $field('catalog_number_of_featured_items'),
                'number_of_new_items' => $field('catalog_number_of_new_items'),
                'number_of_columns' => $catalog_number_of_columns,
                'image_width' => $field('catalog_image_width'),
                'image_height' => $field('catalog_image_height'),
                'back_button_label' => $field('catalog_back_button_label'),
                'catalog_detail_page_id' => $field('catalog_catalog_detail_page_id')
            );

            break;

        case 'catalog detail':
            $properties = array(
                'page_id' => $page_id,
                'allow_customer_to_add_product_to_order' => $field('catalog_detail_allow_customer_to_add_product_to_order', 0),
                'add_button_label' => $field('catalog_detail_add_button_label'),
                'next_page_id' => $field('catalog_detail_next_page_id'),
                'back_button_label' => $field('catalog_detail_back_button_label')
            );

            break;

        case 'express order':
            $properties = array(
                'page_id' => $page_id,
                'shopping_cart_label' => $field('express_order_shopping_cart_label'),
                'quick_add_label' => $field('express_order_quick_add_label'),
                'quick_add_product_group_id' => $field('express_order_quick_add_product_group_id'),
                'product_description_type' => $description_type('express_order'),
                'shipping_form' => $field('express_order_shipping_form', 0),
                'special_offer_code_label' => $field('express_order_special_offer_code_label'),
                'special_offer_code_message' => $field('express_order_special_offer_code_message'),
                'custom_field_1_label' => $field('express_order_custom_field_1_label'),
                'custom_field_1_required' => $field('express_order_custom_field_1_required', 0),
                'custom_field_2_label' => $field('express_order_custom_field_2_label'),
                'custom_field_2_required' => $field('express_order_custom_field_2_required', 0),
                'po_number' => $field('express_order_po_number', 0),
                'form' => $field('express_order_form', 0),
                'form_name' => $field('express_order_form_name'),
                'form_label_column_width' => $field('express_order_form_label_column_width'),
                'card_verification_number_page_id' => $field('express_order_card_verification_number_page_id'),
                'terms_page_id' => $field('express_order_terms_page_id'),
                'update_button_label' => $field('express_order_update_button_label'),
                'purchase_now_button_label' => $field('express_order_purchase_now_button_label'),
                'auto_registration' => $field('express_order_auto_registration', 0),
                'order_receipt_email' => $field('express_order_order_receipt_email', 0),
                'order_receipt_email_subject' => $field('express_order_order_receipt_email_subject'),
                'order_receipt_email_format' => $field('express_order_order_receipt_email_format'),
                'order_receipt_email_header' => $field('express_order_order_receipt_email_header'),
                'order_receipt_email_footer' => $field('express_order_order_receipt_email_footer'),
                'order_receipt_email_page_id' => $field('express_order_order_receipt_email_page_id'),
                'next_page_id' => $field('express_order_next_page_id')
            );

            // if online payments is on, then update the offline payment properties.
            if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
                $properties['offline_payment_always_allowed'] = $field('express_order_offline_payment_always_allowed', 0);
                $properties['offline_payment_label'] = $field('express_order_offline_payment_label');
            }

            if ($hooks) {
                $properties['pre_save_hook_code'] = $field('express_order_pre_save_hook_code');
                $properties['post_save_hook_code'] = $field('express_order_post_save_hook_code');
            }

            break;

        case 'order form':
            $properties = array(
                'page_id' => $page_id,
                'product_group_id' => $field('order_form_product_group_id'),
                'product_layout' => $field('order_form_product_layout'),
                'add_button_label' => $field('order_form_add_button_label'),
                'add_button_next_page_id' => $field('order_form_add_button_next_page_id'),
                'skip_button_label' => $field('order_form_skip_button_label'),
                'skip_button_next_page_id' => $field('order_form_skip_button_next_page_id')
            );

            break;

        case 'shopping cart':
            $properties = array(
                'page_id' => $page_id,
                'shopping_cart_label' => $field('shopping_cart_shopping_cart_label'),
                'quick_add_label' => $field('shopping_cart_quick_add_label'),
                'quick_add_product_group_id' => $field('shopping_cart_quick_add_product_group_id'),
                'product_description_type' => $description_type('shopping_cart'),
                'special_offer_code_label' => $field('shopping_cart_special_offer_code_label'),
                'special_offer_code_message' => $field('shopping_cart_special_offer_code_message'),
                'update_button_label' => $field('shopping_cart_update_button_label'),
                'checkout_button_label' => $field('shopping_cart_checkout_button_label'),
                'next_page_id_with_shipping' => $field('shopping_cart_next_page_id_with_shipping'),
                'next_page_id_without_shipping' => $field('shopping_cart_next_page_id_without_shipping')
            );

            if ($hooks) {
                $properties['hook_code'] = $field('shopping_cart_hook_code');
            }

            break;

        case 'shipping address and arrival':
            $properties = array(
                'page_id' => $page_id,
                'address_type' => $field('shipping_address_and_arrival_address_type', 0),
                'address_type_page_id' => $field('shipping_address_and_arrival_address_type_page_id'),
                'form' => $field('shipping_address_and_arrival_form', 0),
                'form_name' => $field('shipping_address_and_arrival_form_name'),
                'form_label_column_width' => $field('shipping_address_and_arrival_form_label_column_width'),
                'submit_button_label' => $field('shipping_address_and_arrival_submit_button_label'),
                'next_page_id' => $field('shipping_address_and_arrival_next_page_id')
            );

            break;

        case 'shipping method':
            $properties = array(
                'page_id' => $page_id,
                'product_description_type' => $description_type('shipping_method'),
                'submit_button_label' => $field('shipping_method_submit_button_label'),
                'next_page_id' => $field('shipping_method_next_page_id')
            );

            break;

        case 'billing information':
            $properties = array(
                'page_id' => $page_id,
                'custom_field_1_label' => $field('billing_information_custom_field_1_label'),
                'custom_field_1_required' => $field('billing_information_custom_field_1_required', 0),
                'custom_field_2_label' => $field('billing_information_custom_field_2_label'),
                'custom_field_2_required' => $field('billing_information_custom_field_2_required', 0),
                'po_number' => $field('billing_information_po_number', 0),
                'form' => $field('billing_information_form', 0),
                'form_name' => $field('billing_information_form_name'),
                'form_label_column_width' => $field('billing_information_form_label_column_width'),
                'submit_button_label' => $field('billing_information_submit_button_label'),
                'next_page_id' => $field('billing_information_next_page_id')
            );

            break;

        case 'order preview':
            $properties = array(
                'page_id' => $page_id,
                'product_description_type' => $description_type('order_preview'),
                'card_verification_number_page_id' => $field('order_preview_card_verification_number_page_id'),
                'terms_page_id' => $field('order_preview_terms_page_id'),
                'submit_button_label' => $field('order_preview_submit_button_label'),
                'auto_registration' => $field('order_preview_auto_registration', 0),
                'order_receipt_email' => $field('order_preview_order_receipt_email', 0),
                'order_receipt_email_subject' => $field('order_preview_order_receipt_email_subject'),
                'order_receipt_email_format' => $field('order_preview_order_receipt_email_format'),
                'order_receipt_email_header' => $field('order_preview_order_receipt_email_header'),
                'order_receipt_email_footer' => $field('order_preview_order_receipt_email_footer'),
                'order_receipt_email_page_id' => $field('order_preview_order_receipt_email_page_id'),
                'next_page_id' => $field('order_preview_next_page_id')
            );

            // if online payments is on, then update the offline payment properties.
            if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
                $properties['offline_payment_always_allowed'] = $field('order_preview_offline_payment_always_allowed', 0);
                $properties['offline_payment_label'] = $field('order_preview_offline_payment_label');
            }

            if ($hooks) {
                $properties['pre_save_hook_code'] = $field('order_preview_pre_save_hook_code');
                $properties['post_save_hook_code'] = $field('order_preview_post_save_hook_code');
            }

            break;

        case 'order receipt':
            $properties = array(
                'page_id' => $page_id,
                'product_description_type' => $description_type('order_receipt')
            );

            break;

        case 'affiliate sign up form':
            $properties = array(
                'page_id' => $page_id,
                'terms_page_id' => $field('affiliate_sign_up_form_terms_page_id'),
                'submit_button_label' => $field('affiliate_sign_up_form_submit_button_label'),
                'next_page_id' => $field('affiliate_sign_up_form_next_page_id')
            );

            break;
    }

    return $properties;
}

/**
 * The records a page type keeps outside its own table: the site search tag
 * cloud of a search results page, and the form list views or calendars a
 * directory or calendar page shows. The old links are replaced with what was
 * submitted, keeping only those the user may reach.
 *
 * @param string $page_type
 * @param int    $page_id
 * @param array  $user  The signed-in user (validate_user())
 */
function pg_page_form_save_type_links($page_type, $page_id, $user)
{
    switch ($page_type) {
        case 'search results':
            // An unchecked box must arrive as 0: the helper tests it with == 0,
            // which an empty string no longer passes.
            update_tag_cloud_keywords_for_search_results_page_type($page_id, $_POST['search_results_search_catalog_items'] ?? 0, $_POST['search_results_product_group_id'] ?? '');

            break;

        case 'form view directory':
            db("DELETE FROM form_view_directories_form_list_views_xref WHERE form_view_directory_page_id = '" . escape($page_id) . "'");

            // prepare to get folders that user has access to, in order to determine which form list views should be available to be selected for the form view directory page type
            $folders_that_user_has_access_to = array();

            // if user is a basic user, then get folders that user has access to
            if ($user['role'] == 3) {
                $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
            }

            // get all unarchived form list views for form view directory page type
            $form_list_views = db_items(
                "SELECT
                    page.page_id,
                    page.page_name,
                    page.page_folder as folder_id,
                    form_list_view_pages.custom_form_page_id
                FROM page
                LEFT JOIN form_list_view_pages ON
                    (page.page_id = form_list_view_pages.page_id)
                    AND (form_list_view_pages.collection = 'a')
                LEFT JOIN folder ON page.page_folder = folder.folder_id
                WHERE
                    (page.page_type = 'form list view')
                    AND (folder.folder_archived = '0')
                ORDER BY page.page_name ASC");

            // loop through all of the form list views in order to add them to the database if necessary
            foreach ($form_list_views as $form_list_view) {
                $prefix = 'form_view_directory_form_list_view_' . $form_list_view['page_id'];

                // if the user has edit access to the form list view and the user selected it, then add it to the data
                if ((check_folder_access_in_array($form_list_view['folder_id'], $folders_that_user_has_access_to) == TRUE) && (($_POST[$prefix] ?? '') == 1)) {
                    db(
                        "INSERT INTO form_view_directories_form_list_views_xref (
                            form_view_directory_page_id,
                            form_list_view_page_id,
                            form_list_view_name,
                            subject_form_field_id)
                        VALUES (
                            '" . escape($page_id) . "',
                            '" . $form_list_view['page_id'] . "',
                            '" . escape($_POST[$prefix . '_name'] ?? '') . "',
                            '" . escape($_POST[$prefix . '_subject_form_field_id'] ?? '') . "')");
                }
            }

            break;

        case 'calendar view':
        case 'calendar event view':
            if ($page_type == 'calendar view') {
                $table = 'calendar_views_calendars_xref';
                $prefix = 'calendar_view_calendar_';
            } else {
                $table = 'calendar_event_views_calendars_xref';
                $prefix = 'calendar_event_view_calendar_';
            }

            db("DELETE FROM $table WHERE page_id = '" . escape($page_id) . "'");

            $calendars = db_items("SELECT id, name FROM calendars ORDER BY name");

            // if user has access to calendar and calendar was checked then connect the page to it
            foreach ($calendars as $calendar) {
                if ((validate_calendar_access($calendar['id']) == true) && ($_POST[$prefix . $calendar['id']] ?? '')) {
                    db(
                        "INSERT INTO $table (
                           page_id,
                           calendar_id)
                        VALUES (
                           '" . escape($page_id) . "',
                           '" . $calendar['id'] . "')");
                }
            }

            break;
    }
}
