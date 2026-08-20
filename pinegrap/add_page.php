<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */


include('init.php');

$liveform_add_page = new liveform('add_page');

$user = validate_user();
validate_area_access($user, 'user');

// if the user has a user role and has create pages turned off, then output error
if (($user['role'] == '3') && ($user['create_pages'] == FALSE)) {
    log_activity(lang("access denied because user does not have access to create pages"), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

if (!$_POST) {
    $output_wysiwyg_editor_code = '';

    // if user role is Administrator, Designer, or Manager, then allow user to select style and mobile style for page
    if ($user['role'] < 3) {
        $output_style = '<select class="form-select" id="style" name="style">' . select_style() . '</select>';
        $output_mobile_style = '<select class="form-select" id="mobile_style_id" name="mobile_style_id">' . get_mobile_style_options() . '</select>';

    // else user has a user role
    } else {
        $output_style = lang('Default') . ' (' . lang('inherit') . ')';
        $output_mobile_style = lang('Default') . ' (' . lang('inherit') . ')';
    }

    // Only set for non designers below, but always output in the form further down.
    $layout_type_custom_label_class = '';
    $layout_type_custom_label_title = '';
    $layout_type_custom_option_disabled = '';

    // NOTE: $page_folder is never assigned anywhere in this script, so the folder picklist has
    // no preselected folder.  Empty keeps that behaviour and stops the warning.
    $page_folder = '';

    // If the user is not an admin or designer, then disable custom layout type option.
    if (USER_ROLE > 1) {
        $layout_type_custom_label_class = ' text-muted';
        $layout_type_custom_label_title = lang('Administrators & Designers are allowed to enable a custom layout type.');
        $layout_type_custom_option_disabled = ' disabled="disabled"';
    }
    
    // We do not know why we are setting variables below to hide rows.
    // All page type rows should be hidden by default for creating a page
    // because the page type is always "standard" when creating a page.
    // Remove these variables and put "display: none" inline when we have time.
    $email_a_friend_submit_button_label_row_style = 'display: none';
    $email_a_friend_next_page_id_row_style = 'display: none';
    $folder_view_pages_row_style = 'display: none';
    $folder_view_files_row_style = 'display: none';
    $photo_gallery_number_of_columns_row_style = 'display: none';
    $photo_gallery_thumbnail_max_size_row_style = 'display: none';
    $search_results_search_catalog_items_row_style = 'display: none';
    $update_address_book_address_type_row_style = 'display: none';
    $custom_form_form_name_row_style = 'display: none';
    $custom_form_enabled_row_style = 'display: none';
    $custom_form_quiz_row_style = 'display: none';
    $custom_form_label_column_width_row_style = 'display: none';
    $custom_form_watcher_page_id_row_style = 'display: none';
    $custom_form_submit_button_label_row_style = 'display: none';
    $custom_form_hook_code_row_style = 'display: none';
    $custom_form_submitter_email_row_style = 'display: none';
    $custom_form_administrator_email_row_style = 'display: none';
    $custom_form_contact_group_id_row_style = 'display: none';
    $custom_form_membership_row_style = 'display: none';
    $custom_form_confirmation_continue_button_label_row_style = 'display: none';
    $custom_form_confirmation_next_page_id_row_style = 'display: none';
    $form_list_view_custom_form_page_id_row_style = 'display: none';
    $form_list_view_form_item_view_page_id_row_style = 'display: none';
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
    $express_order_form_row_style = 'display: none';
    $express_order_form_name_row_style = 'display: none';
    $express_order_form_label_column_width_row_style = 'display: none';
    $express_order_card_verification_number_page_id_row_style = 'display: none';
    $express_order_offline_payment_always_allowed_row_style = 'display: none';
    $express_order_offline_payment_label_row_style = 'display: none';
    $express_order_terms_page_id_row_style = 'display: none';
    $express_order_update_button_label_row_style = 'display: none';
    $express_order_purchase_now_button_label_row_style = 'display: none';
    $express_order_pre_save_hook_code_row_style = 'display: none';
    $express_order_post_save_hook_code_row_style = 'display: none';
    $express_order_order_receipt_email_row_style = 'display: none';
    $express_order_next_page_id_row_style = 'display: none';
    $order_form_product_group_id_row_style = 'display: none';
    $order_form_product_layout_row_1_style = 'display: none';
    $order_form_product_layout_row_2_style = 'display: none';
    $order_form_add_button_row_style = 'display: none';
    $order_form_skip_button_row_style = 'display: none';
    $order_form_skip_button_next_page_id_row_style = 'display: none';
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
    $shipping_method_next_page_id_row_style = 'display: none';
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
    $order_preview_pre_save_hook_code_row_style = 'display: none';
    $order_preview_post_save_hook_code_row_style = 'display: none';
    $order_preview_order_receipt_email_row_style = 'display: none';
    $order_preview_next_page_id_row_style = 'display: none';
    $order_receipt_product_description_type_row_style = 'display: none';
    $affiliate_sign_up_form_terms_page_id_row_style = 'display: none';
    $affiliate_sign_up_form_submit_button_label_row_style = 'display: none';
    $affiliate_sign_up_form_next_page_id_row_style = 'display: none';
    
    $output_search_results_page_type_properties = '';

    // If advanced site search is enabled then output row for folder pick list
    // for search results properties.
    if (SEARCH_TYPE == 'advanced') {
        $output_search_results_page_type_properties .=
            '<div class="col-12 col-md-12 col-lg-6 my-2" id="search_results_search_folder_id_row" style="display: none">
                <label for="search_results_search_folder_id" class="form-label">' . lang('Search Folder') . '</label>
                <select name="search_results_search_folder_id" id="search_results_search_folder_id" class="form-select"  >' . select_folder() . '</select>
            </div>';
    }

    // Search engine indexing. The block is only built where the columns behind
    // it exist: a switch that saves nothing is worse than no switch at all.
    // A new page starts open to search engines, so both switches start off and
    // nofollow starts unavailable, the way the master switch leaves it.
    $output_noindex_rows = '';

    if (pg_page_noindex_ready() == TRUE) {
        $output_noindex_rows =
            '<div class="col-12 my-3" id="noindex_row">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="noindex" name="noindex" value="1">
                    <label class="form-check-label" for="noindex">' . lang('Close to Search Engines (noindex)') . '</label>
                </div>
                <div class="form-text">' . lang('The page is served with a noindex robots tag, is blocked in robots.txt and is left out of the site map.') . '</div>
                <div class="form-check form-switch mt-3 ms-4">
                    <input class="form-check-input" type="checkbox" id="nofollow" name="nofollow" value="1" disabled="disabled">
                    <label class="form-check-label" for="nofollow">' . lang('Do Not Follow Links on This Page (nofollow)') . '</label>
                </div>
                <div class="form-text ms-4">' . lang('Leave this off so that search engines keep discovering the items this page links to. Turn it on for a widget page whose links already appear on a page that is indexed.') . '</div>
            </div>';
    }

    $output_ecommerce_page_type_properties = '';
    
    if (ECOMMERCE == true) {
        // if the user is an advanced user then prepare to output search results page type properties
        if ($user['role'] < 3) {
            $output_search_results_page_type_properties .=
                '<div class="col-12 my-2" id="search_results_search_catalog_items_row" style="' . $search_results_search_catalog_items_row_style . '">
                    <div class="form-check form-switch">
                        <input value="1" id="search_results_search_catalog_items" name="search_results_search_catalog_items" class="form-check-input collapse-switcher" type="checkbox" role="switch" checked="checked" data-bs-target="#search_catalog_items_options_row" />
                        <label class="form-check-label" for="search_results_search_catalog_items">' . lang('Search Products') . '</label>
                    </div>
                    <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="search_catalog_items_options_row">
                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                        <div class="popover-body">
                            <div class="row">
                                <div class="col-12 col-md-12 col-lg-6 my-1">
                                    <label for="search_results_product_group_id" class="form-label">' . lang('In Product Group') . '</label>
                                    <select name="search_results_product_group_id" id="search_results_product_group_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Product Group')) )) . '-</option>' . get_product_group_options() . '</select>
                                    <div class="form-text">' . lang('leave unselected for all product groups') . '</div>
                                </div>
                                <div class="col-12 col-md-12 col-lg-6 my-1">
                                    <label for="search_results_catalog_detail_page_id" class="form-label">' . lang('Catalog Detail Page') . '</label>
                                    <select name="search_results_catalog_detail_page_id" id="search_results_catalog_detail_page_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page(0, 'catalog detail') . '</select>
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
        
        // if allow offline orders is on, then output offline payment rows.
        if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
            $output_express_order_offline_payment_rows = 
                '<div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_offline_payment_label_row" style="' . $express_order_offline_payment_label_row_style . '">
                    <label class="form-label" for="express_order_offline_payment_label">'. lang('Offline Payment Label') . '</label>
                    <input type="text" id="express_order_offline_payment_label" name="express_order_offline_payment_label" class="form-control" maxlength="255" >
                </div>';
            $output_express_order_offline_payment_checkbox_rows = 
            '<div class="col-12 my-2" id="express_order_offline_payment_always_allowed_row" style="' . $express_order_offline_payment_always_allowed_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="express_order_offline_payment_always_allowed" id="express_order_offline_payment_always_allowed" value="1" />
                    <label class="form-check-label" for="express_order_offline_payment_always_allowed">' . lang('Always Allow Offline Payments') . '</label>
                </div>
            </div>';

            $output_order_preview_offline_payment_rows = 
                '<div class="col-12 col-sm-6 col-lg-4 my-2" id="order_preview_offline_payment_label_row" style="' . $order_preview_offline_payment_label_row_style . '">
                    <label class="form-label" for="order_preview_offline_payment_label">'. lang('Offline Payment Label') . '</label>
                    <input type="text" id="order_preview_offline_payment_label" name="order_preview_offline_payment_label" class="form-control" maxlength="255" >
                </div>';
            $output_order_preview_offline_payment_checkbox_rows = 
                '<div class="col-12 my-2" id="order_preview_offline_payment_always_allowed_row" style="' . $order_preview_offline_payment_always_allowed_row_style . '">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="order_preview_offline_payment_always_allowed" id="order_preview_offline_payment_always_allowed" value="1" />
                        <label class="form-check-label" for="order_preview_offline_payment_always_allowed">' . lang('Always Allow Offline Payments') . '</label>
                    </div>
                </div>';
        }
        
        $output_ecommerce_page_type_properties =
            '<div class="col-12 col-md-6 my-2" id="catalog_product_group_id_row" style="' . $catalog_product_group_id_row_style . '">
                <label for="catalog_product_group_id" class="form-label">' . lang('Product Group') . '</label>
                <select name="catalog_product_group_id" id="catalog_product_group_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options($product_group_id = 0, $parent_product_group_id = 0, $excluded_product_group_id = 0, $level = 0, $product_groups = array(), $include_select_product_groups = FALSE) . '</select>
                <div class="form-text text-end">' . lang('leave unselected for all product groups') . '</div>
            </div>
            <div class="col-12 my-2" id="catalog_menu_row" style="' . $catalog_menu_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="catalog_menu" name="catalog_menu" value="1" checked="checked">
                    <label class="form-check-label" for="catalog_menu">' . lang('Enable Menu') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="catalog_search_row" style="' . $catalog_search_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="catalog_search" name="catalog_search" value="1" checked="checked">
                    <label class="form-check-label" for="catalog_search">' . lang('Enable Search') . '</label>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_number_of_featured_items_row" style="' . $catalog_number_of_featured_items_row_style . '">
                <label for="catalog_number_of_featured_items" class="form-label">' . lang('Number of Featured Items') . '</label>
                <input value="0" type="text" name="catalog_number_of_featured_items" id="catalog_number_of_featured_items" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_number_of_new_items_row" style="' . $catalog_number_of_new_items_row_style . '">
                <label for="catalog_number_of_new_items" class="form-label">' . lang('Number of New Items') . '</label>
                <input value="0" type="text" name="catalog_number_of_new_items" id="catalog_number_of_new_items" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_number_of_columns_row" style="' . $catalog_number_of_columns_row_style . '">
                <label for="catalog_number_of_columns" class="form-label">' . lang('Number of Columns') . '</label>
                <input value="4" type="text" name="catalog_number_of_columns" id="catalog_number_of_columns" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
            </div>
            <div class="col-12">
                <div class="row">
                    <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_image_width_row" style="' . $catalog_image_width_row_style . '">
                        <label for="catalog_image_width" class="form-label">' . lang('Image Width') . '</label>
                        <div class="input-group my-2">
                            <input value="50" type="text" name="catalog_image_width" id="catalog_image_width" maxlength="4" class="form-control text-end" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                            <label class="input-group-text" for="catalog_image_width">' . lang('pixels') . '</label>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_image_height_row" style="' . $catalog_image_height_row_style . '">
                        <label for="catalog_image_height" class="form-label">' . lang('Image Height') . '</label>
                        <div class="input-group my-2">
                            <input value="50" type="text" name="catalog_image_height" id="catalog_image_height" maxlength="4" class="form-control text-end" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                            <label class="input-group-text" for="catalog_image_height">' . lang('pixels') . '</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_back_button_label_row" style="' . $catalog_back_button_label_row_style . '">
                <label for="catalog_back_button_label" class="form-label">' . lang('Back Button Label') . '</label>
                <input type="text" name="catalog_back_button_label" id="catalog_back_button_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_catalog_detail_page_id_row" style="' . $catalog_catalog_detail_page_id_row_style . '">
                <label for="catalog_catalog_detail_page_id" class="form-label">' . lang('Catalog Detail Page') . '</label>
                <select class="form-select" id="catalog_catalog_detail_page_id" name="catalog_catalog_detail_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>lang('Page') )) . '-</option>' . select_page(0, 'catalog detail') . '</select>
            </div>
            <div class="col-12 my-2" id="catalog_detail_allow_customer_to_add_product_to_order_row" style="' . $catalog_detail_allow_customer_to_add_product_to_order_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="catalog_detail_allow_customer_to_add_product_to_order" name="catalog_detail_allow_customer_to_add_product_to_order" class="form-check-input collapse-switcher" checked="checked" type="checkbox" role="switch" data-bs-target="#catalog_detail_allow_customer_to_add_product_to_order_row_popover" />
                    <label class="form-check-label" for="catalog_detail_allow_customer_to_add_product_to_order">' . lang('Allow customer to add product to order') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="catalog_detail_allow_customer_to_add_product_to_order_row_popover">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-12 my-1">
                                <label for="catalog_detail_add_button_label" class="form-label">' . lang('Add Button Label') . '</label>
                                <input type="text" name="catalog_detail_add_button_label" id="catalog_detail_add_button_label" maxlength="50" class="form-control" />
                            </div>
                            <div class="col-12 col-md-12 my-1">
                                <label for="catalog_detail_next_page_id" class="form-label">' . lang('Next Page') . '</label>
                                <select class="form-select" id="catalog_detail_next_page_id" name="catalog_detail_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>lang('Page') )) . '-</option>' . select_page() . '</select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_detail_back_button_label_row" style="' . $catalog_detail_back_button_label_row_style . '">
                <label for="catalog_detail_back_button_label" class="form-label">' . lang('Back Button Label') . '</label>
                <input type="text" name="catalog_detail_back_button_label" id="catalog_detail_back_button_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_shopping_cart_label_row" style="' . $express_order_shopping_cart_label_row_style . '">
                <label for="express_order_shopping_cart_label" class="form-label">' . lang('Shopping Cart Label') . '</label>
                <input type="text" name="express_order_shopping_cart_label" id="express_order_shopping_cart_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_quick_add_label_row" style="' . $express_order_quick_add_label_row_style . '">
                <label for="express_order_quick_add_label" class="form-label">' . lang('Quick Add Label') . '</label>
                <input type="text" name="express_order_quick_add_label" id="express_order_quick_add_label" maxlength="255" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_quick_add_product_group_id_row" style="' . $express_order_quick_add_product_group_id_row_style . '">
                <label for="express_order_quick_add_product_group_id" class="form-label">' . lang('Quick Add Product Group') . '</label>
                <select class="form-select" name="express_order_quick_add_product_group_id" id="express_order_quick_add_product_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options() . '</select>
            </div>
            <div class="col-12 my-2" id="express_order_product_description_type_row" style="' . $express_order_product_description_type_row_style . '">
                <label class="form-label" for="">'. lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input value="full_description" class="form-check-input" type="radio" id="express_order_product_description_type_full_description" name="express_order_product_description_type"  checked="checked">
                    <label class="form-check-label" for="express_order_product_description_type_full_description">'. lang('Full Description') . '</label>
                </div>
                <div class="form-check">
                    <input value="short_description" class="form-check-input" type="radio" id="express_order_product_description_type_short_description" name="express_order_product_description_type">
                    <label class="form-check-label" for="express_order_product_description_type_short_description">'. lang('Short Description') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="express_order_shipping_form_row" style="' . $express_order_shipping_form_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="express_order_shipping_form" name="express_order_shipping_form" class="form-check-input" type="checkbox" role="switch" onclick="toggle_express_order_custom_shipping_form();show_or_hide_express_order_custom_billing_form();"/>
                    <label class="form-check-label" for="express_order_shipping_form">' . lang('Enable Custom Shipping Form') . '</label>
                </div>
                <script>
                    var original_express_order_shipping_form = "0";
                </script>

                <div id="express_order_shipping_form_notice" style="display:none;">
                    <div class="alert alert-primary">
                       <p class="mb-0">' . lang('when ready, click \'Save & Continue\' at the bottom of this screen to create the Custom Shipping Form.') . '</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_special_offer_code_label_row" style="' . $express_order_special_offer_code_label_row_style . '">
                <label for="express_order_special_offer_code_label" class="form-label">' . lang('Special Offer Code Label') . '</label>
                <input type="text" name="express_order_special_offer_code_label" id="express_order_special_offer_code_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 col-lg-8 my-2" id="express_order_special_offer_code_message_row" style="' . $express_order_special_offer_code_message_row_style . '">
                <label for="express_order_special_offer_code_message" class="form-label">' . lang('Special Offer Code Message') . '</label>
                <input type="text" name="express_order_special_offer_code_message" id="express_order_special_offer_code_message" maxlength="255" class="form-control" />
            </div>
            <div class="col-12 col-sm-6 my-2" id="express_order_custom_field_1_label_row" style="' . $express_order_custom_field_1_label_row_style . '">
                <div class="border-1 border p-2 my-2 rounded">
                    <label for="express_order_custom_field_1_label" class="form-label">' . lang('Custom Field #1 Label') . '</label>
                    <input type="text" name="express_order_custom_field_1_label" id="express_order_custom_field_1_label" maxlength="50" class="form-control" />
                    <div class="form-check form-switch ms-1 mt-2">
                        <input class="form-check-input" type="checkbox" name="express_order_custom_field_1_required" id="express_order_custom_field_1_required" value="1" />
                        <label class="form-check-label" for="express_order_custom_field_1_required">' . lang('Required') . '</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 my-2" id="express_order_custom_field_2_label_row" style="' . $express_order_custom_field_2_label_row_style . '">
                <div class="border-1 border p-2 my-2 rounded">
                    <label for="express_order_custom_field_2_label" class="form-label">' . lang('Custom Field #2 Label') . '</label>
                    <input type="text" name="express_order_custom_field_2_label" id="express_order_custom_field_2_label" maxlength="255" class="form-control" />
                    <div class="form-check form-switch ms-1 mt-2">
                        <input class="form-check-input" type="checkbox" name="express_order_custom_field_2_required" id="express_order_custom_field_2_required" value="1" />
                        <label class="form-check-label" for="express_order_custom_field_2_required">' . lang('Required') . '</label>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="express_order_po_number_row" style="' . $express_order_po_number_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="express_order_po_number" id="express_order_po_number" value="1" />
                    <label class="form-check-label" for="express_order_po_number">' . lang('Enable PO Number') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="express_order_form_row" style="' . $express_order_form_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="express_order_form" name="express_order_form" class="form-check-input collapse-switcher" type="checkbox" onclick="show_or_hide_express_order_custom_billing_form()" data-bs-target="#show_or_hide_express_order_custom_billing_form_row"/>
                    <label class="form-check-label" for="express_order_form">' . lang('Enable Custom Billing Form') . '</label>
                </div>
                <script>var original_express_order_form = "0";</script>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="show_or_hide_express_order_custom_billing_form_row">
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
                                <input type="text" name="express_order_form_name" id="express_order_form_name" maxlength="100" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1" id="express_order_form_label_column_width_row" style="' . $express_order_form_label_column_width_row_style . '">
                                <label for="express_order_form_label_column_width" class="form-label">' . lang('Label Column Width') . '</label>
                                <div class="input-group">
                                    <input type="text" name="express_order_form_label_column_width" id="express_order_form_label_column_width" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
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
                    <input class="form-check-input" type="checkbox" name="express_order_auto_registration" id="express_order_auto_registration" value="1" />
                    <label class="form-check-label" for="express_order_auto_registration">' . lang('Enable Auto-Registration') . '</label>
                </div>
            </div>
            ' . $output_express_order_offline_payment_checkbox_rows . '
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_card_verification_number_page_id_row" style="' . $express_order_card_verification_number_page_id_row_style . '">
                <label for="express_order_card_verification_number_page_id" class="form-label">' . lang('Card Verification Number Page') . '</label>
                <select class="form-select" name="express_order_card_verification_number_page_id" id="express_order_card_verification_number_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page() . '</select>
            </div>
            ' . $output_express_order_offline_payment_rows . '
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_terms_page_id_row" style="' . $express_order_terms_page_id_row_style . '">
                <label for="express_order_terms_page_id" class="form-label">' . lang('Terms Page') . '</label>
                <select class="form-select" name="express_order_terms_page_id" id="express_order_terms_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page() . '</select>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_update_button_label_row" style="' . $express_order_update_button_label_row_style . '">
                <label class="form-label" for="express_order_update_button_label">'. lang('Update Button Label') . '</label>
                <input value="' . lang('Update Cart') . '" type="text" id="express_order_update_button_label" name="express_order_update_button_label" class="form-control" maxlength="50" >
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_purchase_now_button_label_row" style="' . $express_order_purchase_now_button_label_row_style . '">
                <label class="form-label" for="express_order_purchase_now_button_label">'. lang('Purchase Now Button Label') . '</label>
                <input type="text" id="express_order_purchase_now_button_label" name="express_order_purchase_now_button_label" class="form-control" maxlength="50" >
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook rows for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_ecommerce_page_type_properties .=
                '<div class="col-12">
                    <div class="row">
                        <div class="col-12 col-lg-6 my-2" id="express_order_pre_save_hook_code_row" style="' . $express_order_pre_save_hook_code_row_style . '">
                            <label class="form-label" for="express_order_pre_save_hook_code">' . lang('Pre-Save Hook Code') . '</label>
                            <textarea id="express_order_pre_save_hook_code" name="express_order_pre_save_hook_code" class="form-control"></textarea>
                        </div>
                        <div class="col-12 col-lg-6 my-2" id="express_order_post_save_hook_code_row" style="' . $express_order_post_save_hook_code_row_style . '">
                            <label class="form-label" for="express_order_post_save_hook_code">' . lang('Post-Save Hook Code') . '</label>
                            <textarea id="express_order_post_save_hook_code" name="express_order_post_save_hook_code" class="form-control"></textarea>
                        </div>
                    </div>
                </div>';
        }

        $output_ecommerce_page_type_properties .=
            '<div class="col-12 col-md-6 col-lg-4 my-2" id="express_order_next_page_id_row" style="' . $express_order_next_page_id_row_style . '">
                <label class="form-label" for="express_order_next_page_id">' . lang('Next Page') . '</label>
                <select class="form-select" id="express_order_next_page_id" name="express_order_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('order receipt page')) )) . '-</option>' . select_page(0, 'order receipt') . '</select>
            </div>
            <div class="col-12 my-2" id="express_order_order_receipt_email_row" style="' . $express_order_order_receipt_email_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="express_order_order_receipt_email" name="express_order_order_receipt_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" checked="checked" data-bs-target="#show_or_hide_express_order_order_receipt_email" />
                    <label class="form-check-label" for="express_order_order_receipt_email">' . lang('E-mail Order Receipt') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_express_order_order_receipt_email">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-8 my-1">
                                <label class="form-label" for="express_order_order_receipt_email_subject">' . lang('Subject') . '</label>
                                <input value="' . lang('Order Receipt') . ' #" type="text" id="express_order_order_receipt_email_subject" name="express_order_order_receipt_email_subject" class="form-control" maxlength="255">
                            </div> 
                            <div class="col-12 my-1">
                                <div class="col-12">
                                    <label class="form-label">' . lang('Format') . '</label>
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="express_order_order_receipt_email_format_plain_text" name="express_order_order_receipt_email_format"  checked="checked" value="plain_text" data-bs-target="#express_order_order_receipt_email_format_plain_text_row" />
                                    <label class="form-check-label" for="express_order_order_receipt_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="express_order_order_receipt_email_format_html" name="express_order_order_receipt_email_format" value="html"  data-bs-target="#express_order_order_receipt_email_format_html_row"/>
                                    <label class="form-check-label" for="express_order_order_receipt_email_format_html">' . lang('HTML') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="express_order_order_receipt_email_format_plain_text_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                              <label for="express_order_order_receipt_email_header" class="form-label">' . lang('Header') . '</label>
                                              <textarea class="form-control" id="express_order_order_receipt_email_header" name="express_order_order_receipt_email_header" rows="3">' . lang('Order Receipt') . '</textarea>
                                            </div>
                                            <div class="col-12 my-1">
                                              <label for="express_order_order_receipt_email_footer" class="form-label">' . lang('Footer') . '</label>
                                              <textarea class="form-control" id="express_order_order_receipt_email_footer" name="express_order_order_receipt_email_footer" rows="3"></textarea>
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
                                                <select class="form-select" id="express_order_order_receipt_email_page_id" name="express_order_order_receipt_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('order receipt page')) )) . '-</option>' . select_page(0, 'order receipt') . '</select>
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
                <select class="form-select" id="order_form_product_group_id" name="order_form_product_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options() . '</select>
            </div>
            <div class="col-12 my-3" id="order_form_product_layout_row_1" style="' . $order_form_product_layout_row_1_style . '">
                <label class="form-label">' . lang('Format') . '</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="order_form_product_layout_list" name="order_form_product_layout"  checked="checked" value="list" />
                    <label class="form-check-label" for="order_form_product_layout_list">' . lang('List (full description)') . '</label> 
                </div>
                <div class="form-check" id="order_form_product_layout_row_2" style="' . $order_form_product_layout_row_2_style . '">
                    <input class="form-check-input" type="radio" id="order_form_product_layout_drop_down_selection" name="order_form_product_layout" value="drop-down selection" />
                    <label class="form-check-label" for="order_form_product_layout_drop_down_selection">' . lang('Drop-Down Selection (short description)') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="order_form_add_button_row" style="' . $order_form_add_button_row_style . '">
                <div class="row p-1 border border-1 rounded bg-light">
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="order_form_add_button_label">' . lang('Add Button Label') . '</label>
                        <input value="' . lang('Continue') . '" type="text" class="form-control" id="order_form_add_button_label" name="order_form_add_button_label" maxlength="50"/>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="order_form_add_button_next_page_id">' . lang('Next Page') . '</label>
                        <select class="form-select" id="order_form_add_button_next_page_id" name="order_form_add_button_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
                    </div> 
                </div>
            </div>
            <div class="col-12 my-2" id="order_form_skip_button_row" style="' . $order_form_skip_button_row_style . '">
                <div class="row p-1 border border-1 rounded bg-light">
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="order_form_skip_button_label">' . lang('Skip Button Label') . '</label>
                        <input type="text" class="form-control" id="order_form_skip_button_label" name="order_form_skip_button_label" maxlength="50"/>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="order_form_skip_button_next_page_id">' . lang('Next Page') . '</label>
                        <select class="form-select" id="order_form_skip_button_next_page_id" name="order_form_skip_button_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
                    </div> 
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_shopping_cart_label_row" style="' . $shopping_cart_shopping_cart_label_row_style . '">
                <label class="form-label" for="shopping_cart_shopping_cart_label">' . lang('Shopping Cart Label') . '</label>
                <input type="text" class="form-control" id="shopping_cart_shopping_cart_label" name="shopping_cart_shopping_cart_label" maxlength="50"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_quick_add_label_row" style="' . $shopping_cart_quick_add_label_row_style . '">
                <label class="form-label" for="shopping_cart_quick_add_label">' . lang('Quick Add Label') . '</label>
                <input type="text" class="form-control" id="shopping_cart_quick_add_label" name="shopping_cart_quick_add_label" maxlength="255"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_quick_add_product_group_id_row" style="' . $shopping_cart_quick_add_product_group_id_row_style . '">
                <label class="form-label" for="shopping_cart_quick_add_product_group_id">' . lang('Quick Add Product Group') . '</label>
                <select class="form-select" id="shopping_cart_quick_add_product_group_id" name="shopping_cart_quick_add_product_group_id"><option value="">-' . lang('None') . '-</option>' . get_product_group_options() . '</select>
            </div>
            <div class="col-12 my-3" id="shopping_cart_product_description_type_row" style="' . $shopping_cart_product_description_type_row_style . '">
                <label class="form-label">' . lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="shopping_cart_product_description_type_full_description" name="shopping_cart_product_description_type"  checked="checked" value="full_description" />
                    <label class="form-check-label" for="shopping_cart_product_description_type_full_description">' . lang('Full Description') . '</label> 
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="shopping_cart_product_description_type_short_description" name="shopping_cart_product_description_type" value="short_description" />
                    <label class="form-check-label" for="shopping_cart_product_description_type_short_description">' . lang('Short Description') . '</label>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_special_offer_code_label_row" style="' . $shopping_cart_special_offer_code_label_row_style . '">
                <label class="form-label" for="shopping_cart_special_offer_code_label">' . lang('Special Offer Code Label') . '</label>
                <input type="text" class="form-control" id="shopping_cart_special_offer_code_label" name="shopping_cart_special_offer_code_label" maxlength="50"/>
            </div>
            <div class="col-12 col-md-6 col-lg-8 my-2" id="shopping_cart_special_offer_code_message_row" style="' . $shopping_cart_special_offer_code_message_row_style . '">
                <label class="form-label" for="shopping_cart_special_offer_code_message">' . lang('Special Offer Code Message') . '</label>
                <input type="text" class="form-control" id="shopping_cart_special_offer_code_message" name="shopping_cart_special_offer_code_message" maxlength="255"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_update_button_label_row" style="' . $shopping_cart_update_button_label_row_style . '">
                <label class="form-label" for="shopping_cart_update_button_label">' . lang('Update Button Label') . '</label>
                <input value="' . lang('Update Cart') . '" type="text" class="form-control" id="shopping_cart_update_button_label" name="shopping_cart_update_button_label" maxlength="50"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_checkout_button_label_row" style="' . $shopping_cart_checkout_button_label_row_style . '">
                <label class="form-label" for="shopping_cart_checkout_button_label">' . lang('Checkout Button Label') . '</label>
                <input value="' . lang('Checkout') . '" type="text" class="form-control" id="shopping_cart_checkout_button_label" name="shopping_cart_checkout_button_label" maxlength="50"/>
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook row for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_ecommerce_page_type_properties .=
                '<div class="col-12 my-2" id="shopping_cart_hook_code_row" style="' . $shopping_cart_hook_code_row_style . '">
                    <label class="form-label" for="shopping_cart_hook_code">' . lang('Hook Code') . '</label>
                    <textarea id="shopping_cart_hook_code" name="shopping_cart_hook_code" class="form-control"></textarea>
                </div>';
        }


        $output_ecommerce_page_type_properties .=
            '<div class="col-12 col-lg-6 col-xl-4 my-2" id="shopping_cart_next_page_id_with_shipping_row" style="' . $shopping_cart_next_page_id_with_shipping_row_style . '">
                <label class="form-label" for="shopping_cart_next_page_id_with_shipping">' . lang('Next Page') . ' (' . lang('with shipping') . ')</label>
                <select class="form-select" id="shopping_cart_next_page_id_with_shipping" name="shopping_cart_next_page_id_with_shipping"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Shipping Address & Arrival or Express Order Page')) )) . '-</option>' . select_page(0, array('shipping address and arrival', 'express order')) . '</select>
            </div>
            <div class="col-12 col-lg-6 col-xl-4 my-2" id="shopping_cart_next_page_id_without_shipping_row" style="' . $shopping_cart_next_page_id_without_shipping_row_style . '">
                <label class="form-label" for="shopping_cart_next_page_id_without_shipping">' . lang('Next Page') . ' (' . lang('without shipping') . ')</label>
                <select class="form-select" id="shopping_cart_next_page_id_without_shipping" name="shopping_cart_next_page_id_without_shipping"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Billing Information or Express Order Page')) )) . '-</option>' . select_page(0, array('billing information', 'express order')) . '</select>
            </div> 
            <div class="col-12 my-2" id="shipping_address_and_arrival_address_type_row" style="' . $shipping_address_and_arrival_address_type_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="shipping_address_and_arrival_address_type" name="shipping_address_and_arrival_address_type" class="form-check-input collapse-switcher" type="checkbox"  data-bs-target="#shipping_address_and_arrival_address_type_page_row"/>
                    <label class="form-check-label" for="shipping_address_and_arrival_address_type">' . lang('Enable Address Type') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="shipping_address_and_arrival_address_type_page_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <label class="form-label" for="shipping_address_and_arrival_address_type_page_id">' . lang('Address Type Page') . '</label>
                                <select class="form-select" id="shipping_address_and_arrival_address_type_page_id" name="shipping_address_and_arrival_address_type_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
                            </div> 
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="shipping_address_and_arrival_form_row" style="' . $shipping_address_and_arrival_form_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="shipping_address_and_arrival_form" name="shipping_address_and_arrival_form" class="form-check-input collapse-switcher" type="checkbox" onclick="show_or_hide_custom_shipping_form()" data-bs-target="#show_or_hide_custom_shipping_form_row"/>
                    <label class="form-check-label" for="shipping_address_and_arrival_form">' . lang('Enable Custom Shipping Form') . '</label>
                </div>
                <script type="text/javascript">var original_shipping_address_and_arrival_form = "0";</script>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="show_or_hide_custom_shipping_form_row">
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
                                <input type="text" name="shipping_address_and_arrival_form_name" id="shipping_address_and_arrival_form_name" maxlength="100" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1" id="shipping_address_and_arrival_form_label_column_width_row" style="' . $shipping_address_and_arrival_form_label_column_width_row_style . '">
                                <label for="shipping_address_and_arrival_form_label_column_width" class="form-label">' . lang('Label Column Width') . '</label>
                                <div class="input-group">
                                    <input type="text" name="shipping_address_and_arrival_form_label_column_width" id="shipping_address_and_arrival_form_label_column_width" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
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
                        <input type="text" class="form-control" id="shipping_address_and_arrival_submit_button_label" name="shipping_address_and_arrival_submit_button_label" maxlength="50"/>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="shipping_address_and_arrival_next_page_id">' . lang('Next Page') . '</label>
                        <select class="form-select" id="shipping_address_and_arrival_next_page_id" name="shipping_address_and_arrival_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Shipping Method Page')) )) . '-</option>' . select_page(0, 'shipping method') . '</select>
                    </div> 
                </div>
            </div>
            <div class="col-12 my-2" id="shipping_method_product_description_type_row" style="' . $shipping_method_product_description_type_row_style . '">
                <label class="form-label" for="">'. lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input value="full_description" class="form-check-input" type="radio" id="shipping_method_product_description_type_full_description" name="shipping_method_product_description_type"  checked="checked">
                    <label class="form-check-label" for="shipping_method_product_description_type_full_description">'. lang('Full Description') . '</label>
                </div>
                <div class="form-check">
                    <input value="short_description" class="form-check-input" type="radio" id="shipping_method_product_description_type_short_description" name="shipping_method_product_description_type">
                    <label class="form-check-label" for="shipping_method_product_description_type_short_description">'. lang('Short Description') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="shipping_method_submit_button_row" style="' . $shipping_method_submit_button_row_style . '">
                <div class="row p-1 border border-1 rounded bg-light">
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="shipping_method_submit_button_label">' . lang('Submit Button Label') . '</label>
                        <input type="text" class="form-control" id="shipping_method_submit_button_label" name="shipping_method_submit_button_label" maxlength="50"/>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 my-2">
                        <label class="form-label" for="shipping_method_next_page_id">' . lang('Next Page') . '</label>
                        <select class="form-select" id="shipping_method_next_page_id" name="shipping_method_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
                    </div> 
                </div>
            </div>
            <div class="col-12 col-sm-6 my-2" id="billing_information_custom_field_1_label_row" style="' . $billing_information_custom_field_1_label_row_style . '">
                <div class="border-1 border p-2 my-2 rounded">
                    <label for="billing_information_custom_field_1_label" class="form-label">' . lang('Custom Field #1 Label') . '</label>
                    <input type="text" name="billing_information_custom_field_1_label" id="billing_information_custom_field_1_label" maxlength="255" class="form-control" />
                    <div class="form-check form-switch ms-1 mt-2">
                        <input class="form-check-input" type="checkbox" name="billing_information_custom_field_1_required" id="billing_information_custom_field_1_required" value="1" />
                        <label class="form-check-label" for="billing_information_custom_field_1_required">' . lang('Required') . '</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 my-2" id="billing_information_custom_field_2_label_row" style="' . $billing_information_custom_field_2_label_row_style . '">
                <div class="border-1 border p-2 my-2 rounded">
                    <label for="billing_information_custom_field_2_label" class="form-label">' . lang('Custom Field #2 Label') . '</label>
                    <input type="text" name="billing_information_custom_field_2_label" id="billing_information_custom_field_2_label" maxlength="255" class="form-control" />
                    <div class="form-check form-switch ms-1 mt-2">
                        <input class="form-check-input" type="checkbox" name="billing_information_custom_field_2_required" id="billing_information_custom_field_2_required" value="1" />
                        <label class="form-check-label" for="billing_information_custom_field_2_required">' . lang('Required') . '</label>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="billing_information_po_number_row" style="' . $billing_information_po_number_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="billing_information_po_number" id="billing_information_po_number" value="1" />
                    <label class="form-check-label" for="billing_information_po_number">' . lang('Enable PO Number') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="billing_information_form_row" style="' . $billing_information_form_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="billing_information_form" name="billing_information_form" class="form-check-input collapse-switcher" type="checkbox" onclick="show_or_hide_billing_information_custom_billing_form()" data-bs-target="#show_or_hide_billing_information_custom_billing_form_row"/>
                    <label class="form-check-label" for="billing_information_form">' . lang('Enable Custom Billing Form') . '</label>
                </div>
                <script type="text/javascript">var original_billing_information_form = "0";</script>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="show_or_hide_billing_information_custom_billing_form_row">
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
                                <input type="text" name="billing_information_form_name" id="billing_information_form_name" maxlength="100" class="form-control" />
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1" id="billing_information_form_label_column_width_row" style="' . $billing_information_form_label_column_width_row_style . '">
                                <label for="billing_information_form_label_column_width" class="form-label">' . lang('Label Column Width') . '</label>
                                <div class="input-group">
                                    <input type="text" name="billing_information_form_label_column_width" id="billing_information_form_label_column_width" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
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
                <input type="text" class="form-control" id="billing_information_submit_button_label" name="billing_information_submit_button_label" maxlength="50"/>
            </div>
            <div class="col-12 col-lg-6 col-xl-4 my-2" id="billing_information_next_page_id_row" style="' . $billing_information_next_page_id_row_style . '">
                <label class="form-label" for="billing_information_next_page_id">' . lang('Next Page') . ' (' . lang('without shipping') . ')</label>
                <select class="form-select" id="billing_information_next_page_id" name="billing_information_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Order Preview or Express Order Page')) )) . '-</option>' . select_page(0, 'order preview') . select_page(0, 'express order') . '</select>
            </div> 
            <div class="col-12 my-2" id="order_preview_product_description_type_row" style="' . $order_preview_product_description_type_row_style . '">
                <label class="form-label" for="">'. lang('Product Description Type') . '</label>
                <div class="form-check">
                    <input value="full_description" class="form-check-input" type="radio" id="order_preview_product_description_type_full_description" name="order_preview_product_description_type"  checked="checked">
                    <label class="form-check-label" for="order_preview_product_description_type_full_description">'. lang('Full Description') . '</label>
                </div>
                <div class="form-check">
                    <input value="short_description" class="form-check-input" type="radio" id="order_preview_product_description_type_short_description" name="order_preview_product_description_type">
                    <label class="form-check-label" for="order_preview_product_description_type_short_description">'. lang('Short Description') . '</label>
                </div>
            </div>
            <div class="col-12 my-2" id="order_preview_auto_registration_row" style="display: none">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="order_preview_auto_registration" id="order_preview_auto_registration" value="1" />
                    <label class="form-check-label" for="order_preview_auto_registration">' . lang('Enable Auto-Registration') . '</label>
                </div>
            </div>
            ' . $output_order_preview_offline_payment_checkbox_rows . '
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="order_preview_card_verification_number_page_id_row" style="' . $order_preview_card_verification_number_page_id_row_style . '">
                <label for="order_preview_card_verification_number_page_id" class="form-label">' . lang('Card Verification Number Page') . '</label>
                <select class="form-select" name="order_preview_card_verification_number_page_id" id="order_preview_card_verification_number_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page() . '</select>
            </div>
            ' . $output_order_preview_offline_payment_rows . '
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="order_preview_terms_page_id_row" style="' . $order_preview_terms_page_id_row_style . '">
                <label for="order_preview_terms_page_id" class="form-label">' . lang('Terms Page') . '</label>
                <select class="form-select" name="order_preview_terms_page_id" id="order_preview_terms_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page() . '</select>
            </div>
            <div class="col-12 col-sm-6 col-lg-4 my-2" id="order_preview_submit_button_label_row" style="' . $order_preview_submit_button_label_row_style . '">
                <label class="form-label" for="order_preview_submit_button_label">'. lang('Update Button Label') . '</label>
                <input type="text" id="order_preview_submit_button_label" name="order_preview_submit_button_label" class="form-control" maxlength="50" >
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook rows for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_ecommerce_page_type_properties .=
                '<div class="col-12">
                    <div class="row">
                        <div class="col-12 col-lg-6 my-2" id="order_preview_pre_save_hook_code_row" style="' . $order_preview_pre_save_hook_code_row_style . '">
                            <label class="form-label" for="order_preview_pre_save_hook_code">' . lang('Pre-Save Hook Code') . '</label>
                            <textarea id="order_preview_pre_save_hook_code" name="order_preview_pre_save_hook_code" class="form-control"></textarea>
                        </div>
                        <div class="col-12 col-lg-6 my-2" id="order_preview_post_save_hook_code_row" style="' . $order_preview_post_save_hook_code_row_style . '">
                            <label class="form-label" for="order_preview_post_save_hook_code">' . lang('Post-Save Hook Code') . '</label>
                            <textarea id="order_preview_post_save_hook_code" name="order_preview_post_save_hook_code" class="form-control"></textarea>
                        </div> 
                    </div>
                </div>';
        }

        $output_ecommerce_page_type_properties .=
            '<div class="col-12 col-md-6 col-lg-4 my-2" id="order_preview_next_page_id_row" style="' . $order_preview_next_page_id_row_style . '">
                <label class="form-label" for="order_preview_next_page_id">' . lang('Next Page') . '</label>
                <select class="form-select" id="order_preview_next_page_id" name="order_preview_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('order receipt page')) )) . '-</option>' . select_page(0, 'order receipt') . '</select>
            </div>
            <div class="col-12 my-2" id="order_preview_order_receipt_email_row" style="' . $order_preview_order_receipt_email_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="order_preview_order_receipt_email" name="order_preview_order_receipt_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" checked="checked" data-bs-target="#show_or_hide_order_preview_order_receipt_email" />
                    <label class="form-check-label" for="order_preview_order_receipt_email">' . lang('E-mail Order Receipt') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_order_preview_order_receipt_email">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-8 my-1">
                                <label class="form-label" for="order_preview_order_receipt_email_subject">' . lang('Subject') . '</label>
                                <input value="' . lang('Order Receipt') . ' #" type="text" id="order_preview_order_receipt_email_subject" name="order_preview_order_receipt_email_subject" class="form-control" maxlength="255">
                            </div>
                            <div class="col-12 my-1">
                                <div class="col-12">
                                    <label class="form-label">' . lang('Format') . '</label>
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="order_preview_order_receipt_email_format_plain_text" name="order_preview_order_receipt_email_format"  checked="checked" value="plain_text" data-bs-target="#order_preview_order_receipt_email_format_plain_text_row" />
                                    <label for="order_preview_order_receipt_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="order_preview_order_receipt_email_format_html" name="order_preview_order_receipt_email_format" value="html"  data-bs-target="#order_preview_order_receipt_email_format_html_row"/>
                                    <label for="order_preview_order_receipt_email_format_html">' . lang('HTML') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="order_preview_order_receipt_email_format_plain_text_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                              <label for="order_preview_order_receipt_email_header" class="form-label">' . lang('Header') . '</label>
                                              <textarea class="form-control" id="order_preview_order_receipt_email_header" name="order_preview_order_receipt_email_header" rows="3">' . lang('Order Receipt') . '</textarea>
                                            </div>
                                            <div class="col-12 my-1">
                                              <label for="order_preview_order_receipt_email_footer" class="form-label">' . lang('Footer') . '</label>
                                              <textarea class="form-control" id="order_preview_order_receipt_email_footer" name="order_preview_order_receipt_email_footer" rows="3"></textarea>
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
                                                <select class="form-select" id="order_preview_order_receipt_email_page_id" name="order_preview_order_receipt_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('order receipt page')) )) . '-</option>' . select_page(0, 'order receipt') . '</select>
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
                    <input value="full_description" class="form-check-input" type="radio" id="order_receipt_product_description_type_full_description" name="order_receipt_product_description_type"  checked="checked">
                    <label class="form-check-label" for="order_receipt_product_description_type_full_description">'. lang('Full Description') . '</label>
                </div>
                <div class="form-check">
                    <input value="short_description" class="form-check-input" type="radio" id="order_receipt_product_description_type_short_description" name="order_receipt_product_description_type">
                    <label class="form-check-label" for="order_receipt_product_description_type_short_description">'. lang('Short Description') . '</label>
                </div>
            </div>';
    }

    if (FORMS == true) {
        $output_wysiwyg_editor_code = get_wysiwyg_editor_code(array('custom_form_confirmation_message', 'custom_form_return_message'), $activate_editors = false);

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
                $output_form_view_directory_subject_field_options = '';
                
                // loop through the fields for this form list view's custom form, in order to prepare to output subject field options
                foreach ($custom_forms[$form_list_view['custom_form_page_id']]['form_fields'] as $form_field) {
                    $output_form_view_directory_subject_field_options .= '<option value="' . $form_field['id'] . '">' . h($form_field['name']) . '</option>';
                }
                
                $output_form_view_directory_form_list_view_rows .=
                    '<div class="form-check">
                        <input type="checkbox" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '" name="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '" value="1" class="form-check-input collapse-switcher" data-bs-target="#form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_row"/>
                        <label class="form-check-label" for="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '">' . h($form_list_view['page_name']) . '</label>
                    </div>
                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_row">
                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                        <div class="popover-body">
                            <div class="row">
                                <div class="col-12 col-lg-6 my-1" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name_container" >
                                    <label for="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name" class="form-label">' . lang('Name') . '</label>
                                    <input name="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name" id="form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name" type="text" class="form-control" maxlength="100" />
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
                <input type="text" name="custom_form_form_name" id="custom_form_form_name" maxlength="100" class="form-control" />
            </div>
            <div class="col-12 my-2" id="custom_form_enabled_row" style="' . $custom_form_enabled_row_style . '">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="custom_form_enabled" name="custom_form_enabled" value="1" checked="checked" />
                    <label for="custom_form_enabled">' . lang('Enable Form') . '</label> 
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_quiz_row" style="' . $custom_form_quiz_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="custom_form_quiz" name="custom_form_quiz" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#custom_form_quiz_collapse" />
                    <label class="form-check-label" for="custom_form_quiz">' . lang('Enable Quiz') . '</label>
                </div>
                <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="custom_form_quiz_collapse">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-12 my-1">
                                <label for="update_address_book_address_type_page_id" class="form-label">' . lang('Quiz Pass Percentage') . '</label>
                                <div class="input-group">
                                    <input type="text" name="custom_form_quiz_pass_percentage" id="custom_form_quiz_pass_percentage" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
                                    <label class="input-group-text" for="custom_form_quiz_pass_percentage">%</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_save_row" style="display: none">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="custom_form_save" name="custom_form_save" value="1" />
                    <label for="custom_form_save">' . lang('Enable Save-for-Later') . '</label> 
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_auto_registration_row" style="display: none">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="custom_form_auto_registration" name="custom_form_auto_registration" value="1" />
                    <label for="custom_form_auto_registration">' . lang('Enable Auto-Registration') . '</label> 
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_label_column_width_row" style="' . $custom_form_label_column_width_row_style . '">
                <label for="custom_form_label_column_width" class="form-label">' . lang('Label Column Width') . '</label>
                <div class="input-group">
                    <input type="text" name="custom_form_label_column_width" id="custom_form_label_column_width" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
                    <label class="input-group-text" for="custom_form_label_column_width">%</label>
                </div>
                <div class="form-text text-end">' . lang('leave blank for auto') . '</div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_submit_button_label_row" style="' . $custom_form_submit_button_label_row_style . '">
                <label for="custom_form_submit_button_label" class="form-label">' . lang('Submit Button Label') . '</label>
                <input type="text" name="custom_form_submit_button_label" id="custom_form_submit_button_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_watcher_page_id_row" style="' . $custom_form_watcher_page_id_row_style . '">
                <label for="custom_form_watcher_page_id" class="form-label">' . lang('Enable Watcher Option') . '</label>
                <select class="form-select" name="custom_form_watcher_page_id" id="custom_form_watcher_page_id">
                <option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('form item view page')) )) . '-</option>' . select_page(0, 'form item view') . '</select>
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook row for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_forms_page_type_properties .=
                '<div class="col-12 my-2" id="custom_form_hook_code_row" style="' . $custom_form_hook_code_row_style . '">
                    <label class="form-label" for="custom_form_hook_code">' . lang('Hook Code') . '</label>
                    <textarea id="custom_form_hook_code" name="custom_form_hook_code" class="form-control"></textarea>
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
                <select class="form-select" id="custom_form_contact_group_id" name="custom_form_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group')) )) . '-</option>' . select_contact_group(0, $user) . '</select>
            </div>
            <div class="col-12 my-2" id="custom_form_submitter_email_row" style="' . $custom_form_submitter_email_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="custom_form_submitter_email" name="custom_form_submitter_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_submitter_email" />
                    <label class="form-check-label" for="custom_form_submitter_email">' . lang('E-mail Submitter') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_submitter_email">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-xl-4 my-1">
                                <label class="form-label" for="custom_form_submitter_email_from_email_address">' . lang('From E-mail Address') . '</label>
                                <input type="text" class="form-control text-end" id="custom_form_submitter_email_from_email_address" name="custom_form_submitter_email_from_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-8 my-1">
                                <label class="form-label" for="custom_form_submitter_email_subject">' . lang('Subject') . '</label>
                                <input type="text" id="custom_form_submitter_email_subject" name="custom_form_submitter_email_subject" class="form-control" maxlength="255">
                            </div>
                            <div class="col-12 my-1">
                                <div class="col-12">
                                    <label class="form-label">' . lang('Format') . '</label>
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_submitter_email_format_plain_text" name="custom_form_submitter_email_format"  checked="checked" value="plain_text" data-bs-target="#custom_form_submitter_email_format_plain_text_row" />
                                    <label for="custom_form_submitter_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_submitter_email_format_html" name="custom_form_submitter_email_format" value="html"  data-bs-target="#custom_form_submitter_email_format_html_row"/>
                                    <label for="custom_form_submitter_email_format_html">' . lang('HTML') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_submitter_email_format_plain_text_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                              <label for="custom_form_submitter_email_body" class="form-label">' . lang('Body') . '</label>
                                              <textarea class="form-control" id="custom_form_submitter_email_body" name="custom_form_submitter_email_body" rows="3"></textarea>
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
                                                <select class="form-select" id="custom_form_submitter_email_page_id" name="custom_form_submitter_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
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
                    <input value="1" id="custom_form_administrator_email" name="custom_form_administrator_email" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_administrator_email" />
                    <label class="form-check-label" for="custom_form_administrator_email">' . lang('E-mail Administrator') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_administrator_email">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-xl-4 my-1">
                                <label class="form-label" for="custom_form_administrator_email_to_email_address">' . lang('To E-mail Address') . '</label>
                                <input type="text" class="form-control text-end" id="custom_form_administrator_email_to_email_address" name="custom_form_administrator_email_to_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-4 my-1">
                                <label class="form-label" for="custom_form_administrator_email_bcc_email_address">' . lang('BCC E-mail Address') . '</label>
                                <input type="text" class="form-control text-end" id="custom_form_administrator_email_bcc_email_address" name="custom_form_administrator_email_bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                            </div>
                            <div class="col-12 col-sm-12 col-xl-4 my-1">
                                <label class="form-label" for="custom_form_administrator_email_subject">' . lang('Subject') . '</label>
                                <input type="text" id="custom_form_administrator_email_subject" name="custom_form_administrator_email_subject" class="form-control" maxlength="255">
                            </div>
                            <div class="col-12 my-1">
                                <div class="col-12">
                                    <label class="form-label">' . lang('Format') . '</label>
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_administrator_email_format_plain_text" name="custom_form_administrator_email_format"  checked="checked" value="plain_text" data-bs-target="#custom_form_administrator_email_format_plain_text_row" />
                                    <label for="custom_form_administrator_email_format_plain_text">' . lang('Plain Text') . '</label> 
                                </div>
                                <div class="form-check  form-check-inline">
                                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_administrator_email_format_html" name="custom_form_administrator_email_format" value="html"  data-bs-target="#custom_form_administrator_email_format_html_row"/>
                                    <label for="custom_form_administrator_email_format_html">' . lang('HTML') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_administrator_email_format_plain_text_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                              <label for="custom_form_administrator_email_body" class="form-label">' . lang('Body') . '</label>
                                              <textarea class="form-control" id="custom_form_administrator_email_body" name="custom_form_administrator_email_body" rows="3"></textarea>
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
                                                <select class="form-select" id="custom_form_administrator_email_page_id" name="custom_form_administrator_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
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
                    <input value="1" id="custom_form_membership" name="custom_form_membership" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_membership" />
                    <label class="form-check-label" for="custom_form_membership">' . lang('Grant Membership Trial') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_membership">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label for="custom_form_membership_days" class="form-label">' . lang('Trial Length') . '</label>
                                <div class="input-group">
                                    <input type="text" name="custom_form_membership_days" id="custom_form_membership_days" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                    <span class="input-group-text">' . lang('day(s)') . '</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label class="form-label" for="custom_form_membership_start_page_id">' . lang('Set Member\'s Start Page to') . '</label>
                                <select class="form-select" id="custom_form_membership_start_page_id" name="custom_form_membership_start_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' .  select_page() . '</select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-2" id="custom_form_private_row" style="display: none">
                <div class="form-check form-switch">
                    <input value="1" id="custom_form_private" name="custom_form_private" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#toggle_custom_form_private" />
                    <label class="form-check-label" for="custom_form_private">' . lang('Grant Private Access') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="toggle_custom_form_private">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label class="form-label" for="custom_form_private_folder_id">' . lang('Set "View" Access to Folder') . '</label>
                                <select class="form-select" id="custom_form_private_folder_id" name="custom_form_private_folder_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('private folder')) )) . '-</option>' . select_folder(0, 0, 0, 0, array(), array(), 'private') . '</select>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label for="custom_form_private_days" class="form-label">' . lang('Length') . '</label>
                                <div class="input-group">
                                    <input type="text" name="custom_form_private_days" id="custom_form_private_days" class="form-control" size="7" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                    <span class="input-group-text">' . lang('day(s)') . '</span>
                                </div>
                                <div class="text-end form-text">' . lang('leave blank for no expiration') . '</div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label class="form-label" for="custom_form_private_start_page_id">' . lang('Set User\'s Start Page to') . '</label>
                                <select class="form-select" id="custom_form_private_start_page_id" name="custom_form_private_start_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' .  select_page() . '</select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';

        // If commerce is enabled and the user has access to commerce, then output grant offer rows.
        if ((ECOMMERCE) && (USER_MANAGE_ECOMMERCE)) {
            $output_forms_page_type_properties .=
                '<div class="col-12 my-2" id="custom_form_offer_row" style="display: none">
                    <div class="form-check form-switch">
                        <input value="1" id="custom_form_offer" name="custom_form_offer" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#toggle_custom_form_offer" />
                        <label class="form-check-label" for="custom_form_offer">' . lang('Grant Offer') . '</label>
                    </div>
                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="toggle_custom_form_offer">
                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                        <div class="popover-body">
                            <div class="row">
                                <div class="col-12 col-md-6 col-lg-4 my-1">
                                    <label class="form-label" for="custom_form_offer_id">' . lang('Offer') . '</label>
                                    <select class="form-select" id="custom_form_offer_id" name="custom_form_offer_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('offer')) )) . '-</option>' . select_offer() . '</select>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-1">
                                    <label for="custom_form_offer_days" class="form-label">' . lang('Validity Length') . '</label>
                                    <div class="input-group">
                                        <input type="text" name="custom_form_offer_days" id="custom_form_offer_days" class="form-control" size="7" maxlength="9" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                        <span class="input-group-text">' . lang('day(s)') . '</span>
                                    </div>
                                    <div class="text-end form-text">' . lang('leave blank for no expiration') . '</div>
                                </div>
                                <div class="col-12 col-md-6 col-lg-4 my-1">
                                    <label class="form-label" for="custom_form_offer_eligibility">' . lang('Eligibility') . '</label>
                                    <select class="form-select" id="custom_form_offer_eligibility" name="custom_form_offer_eligibility"><option value="everyone">' . lang('Everyone') . '</option><option value="new_contacts">' . lang('New Contacts') . '</option><option value="existing_contacts">' . lang('Existing Contacts') . '</option></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
        }

        $output_forms_page_type_properties .=
            '<div class="col-12 my-1" id="custom_form_confirmation_type_row" style="display: none">
                <div class="col-12">
                    <label class="form-label">' . lang('Confirmation Type') . '</label>
                </div>
                <div class="form-check  form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_confirmation_type_message" name="custom_form_confirmation_type"  checked="checked" value="message" onclick="show_or_hide_custom_form_confirmation_type()" data-bs-target="#custom_form_confirmation_type_message_row" />
                    <label for="custom_form_confirmation_type_message">' . lang('Message') . '</label> 
                </div>
                <div class="form-check  form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_confirmation_type_page" name="custom_form_confirmation_type" value="page" onclick="show_or_hide_custom_form_confirmation_type()" data-bs-target="#custom_form_confirmation_type_page_row"/>
                    <label for="custom_form_confirmation_type_page">' . lang('Next Page') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_confirmation_type_message_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                              <label for="custom_form_confirmation_message" class="form-label">' . lang('Message') . '</label>
                              <textarea class="form-control" id="custom_form_confirmation_message" name="custom_form_confirmation_message" rows="3"></textarea>
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
                                <select class="form-select" id="custom_form_confirmation_page_id" name="custom_form_confirmation_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
                            </div>
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1" id="custom_form_confirmation_alternative_page" name="custom_form_confirmation_alternative_page" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_confirmation_alternative_page" />
                                    <label class="form-check-label" for="custom_form_confirmation_alternative_page">' . lang('Alternative Next Page') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_confirmation_alternative_page">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row p-1 border border-1 rounded bg-light">
                                            <div class="col-12 col-lg-6 my-1">
                                                <label class="form-label" for="custom_form_confirmation_alternative_page_contact_group_id">' . lang('If Contact Group') . '</label>
                                                <select class="form-select" id="custom_form_confirmation_alternative_page_contact_group_id" name="custom_form_confirmation_alternative_page_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('contact group')) )) . '-</option>' . select_contact_group(0, $user) . '</select>
                                            </div>
                                            <div class="col-12 col-lg-6 my-1">
                                                <label class="form-label" for="custom_form_confirmation_alternative_page_id">' . lang('Then Go to Page') . '</label>
                                                <select class="form-select" id="custom_form_confirmation_alternative_page_id" name="custom_form_confirmation_alternative_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-1" id="custom_form_return_type_row" style="display: none">
                <div class="col-12">
                    <label class="form-label">' . lang('If User has already submitted form in the past, then show') . '</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_return_type_custom_form" name="custom_form_return_type"  checked="checked" value="custom_form" onclick="show_or_hide_custom_form_return_type()"  />
                    <label for="custom_form_return_type_custom_form">' . lang('Custom Form') . '</label> 
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_return_type_message" name="custom_form_return_type" value="message" onclick="show_or_hide_custom_form_return_type()" data-bs-target="#custom_form_return_type_message_row"/>
                    <label for="custom_form_return_type_message">' . lang('Message') . '</label>
                </div>
                <div class="form-check  form-check-inline">
                    <input class="form-check-input collapse-switcher" type="radio" id="custom_form_return_type_page" name="custom_form_return_type" value="page" onclick="show_or_hide_custom_form_return_type()" data-bs-target="#custom_form_return_type_page_row"/>
                    <label for="custom_form_return_type_page">' . lang('Page') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="custom_form_return_type_message_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(140px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <label for="custom_form_return_message" class="form-label">' . lang('Message') . '</label>
                                <textarea class="form-control" id="custom_form_return_message" name="custom_form_return_message" rows="3"></textarea>
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
                                <select class="form-select" id="custom_form_return_page_id" name="custom_form_return_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
                            </div>
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1" id="custom_form_return_alternative_page" name="custom_form_return_alternative_page" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_custom_form_return_alternative_page" />
                                    <label class="form-check-label" for="custom_form_return_alternative_page">' . lang('Alternative Next Page') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_custom_form_return_alternative_page">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row p-1 border border-1 rounded bg-light">
                                            <div class="col-12 col-lg-6 my-1">
                                                <label class="form-label" for="custom_form_return_alternative_page_contact_group_id">' . lang('If Contact Group') . '</label>
                                                <select class="form-select" id="custom_form_return_alternative_page_contact_group_id" name="custom_form_return_alternative_page_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('contact group')) )) . '-</option>' . select_contact_group(0, $user) . '</select>
                                            </div>
                                            <div class="col-12 col-lg-6 my-1">
                                                <label class="form-label" for="custom_form_return_alternative_page_id">' . lang('Then Go to Page') . '</label>
                                                <select class="form-select" id="custom_form_return_alternative_page_id" name="custom_form_return_alternative_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 my-3" id="custom_form_pretty_urls_row" style="display: none">
                <div class="form-check form-switch">
                    <input value="1" id="custom_form_pretty_urls" name="custom_form_pretty_urls" class="form-check-input" type="checkbox" role="switch" />
                    <label class="form-check-label" for="custom_form_pretty_urls">' . lang('Enable Pretty URLs') . '</label>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_confirmation_continue_button_label_row" style="' . $custom_form_confirmation_continue_button_label_row_style . '">
                <label for="custom_form_confirmation_continue_button_label" class="form-label">' . lang('Continue Button Label') . '</label>
                <input type="text" name="custom_form_confirmation_continue_button_label" id="custom_form_confirmation_continue_button_label" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="custom_form_confirmation_next_page_id_row" style="' . $custom_form_confirmation_next_page_id_row_style . '">
                <label class="form-label" for="custom_form_confirmation_next_page_id">' . lang('Next Page') . '</label>
                <select class="form-select" id="custom_form_confirmation_next_page_id" name="custom_form_confirmation_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page() . '</select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_list_view_custom_form_page_id_row" style="' . $form_list_view_custom_form_page_id_row_style . '">
                <label class="form-label" for="form_list_view_custom_form_page_id">' . lang('Custom Form') . '</label>
                <select class="form-select" id="form_list_view_custom_form_page_id" name="form_list_view_custom_form_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('custom form')) )) . '-</option>' . select_custom_form(0, $user) . '</select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_list_view_form_item_view_page_id_row" style="' . $form_list_view_form_item_view_page_id_row_style . '">
                <label class="form-label" for="form_list_view_form_item_view_page_id">' . lang('Form Item View') . '</label>
                <select class="form-select" id="form_list_view_form_item_view_page_id" name="form_list_view_form_item_view_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('form item view page')) )) . '-</option>' . select_page(0, 'form item view') . '</select>
            </div>
            <div class="col-12 my-1" id="form_list_view_viewer_filter_row" style="display: none">
                <div class="form-check form-switch">
                    <input value="1" id="form_list_view_viewer_filter" name="form_list_view_viewer_filter" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#show_or_hide_form_list_view_viewer_filter" />
                    <label class="form-check-label" for="form_list_view_viewer_filter">' . lang('Enable Viewer Filter') . '</label>
                    ' . $output_viewer_filter_warning . '
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_form_list_view_viewer_filter">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1" checked="checked" id="form_list_view_viewer_filter_submitter" name="form_list_view_viewer_filter_submitter" class="form-check-input"  type="checkbox" role="switch"/>
                                    <label class="form-check-label" for="form_list_view_viewer_filter_submitter">' . lang('Include Forms from Submitter') . '</label>
                                </div>
                            </div>
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1" checked="checked" id="form_list_view_viewer_filter_watcher" name="form_list_view_viewer_filter_watcher" class="form-check-input"  type="checkbox" role="switch"/>
                                    <label class="form-check-label" for="form_list_view_viewer_filter_watcher">' . lang('Include Forms for Watchers') . '</label>
                                </div>
                            </div>
                            <div class="col-12 my-1">
                                <div class="form-check form-switch">
                                    <input value="1" checked="checked" id="form_list_view_viewer_filter_editor" name="form_list_view_viewer_filter_editor" class="form-check-input"  type="checkbox" role="switch"/>
                                    <label class="form-check-label" for="form_list_view_viewer_filter_editor">' . lang('Include Forms for Form Editors') . '</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_item_view_custom_form_page_id_row" style="' . $form_item_view_custom_form_page_id_row_style . '">
                <label class="form-label" for="form_item_view_custom_form_page_id">' . lang('Custom Form') . '</label>
                <select class="form-select" id="form_item_view_custom_form_page_id" name="form_item_view_custom_form_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('custom form')) )) . '-</option>' . select_custom_form(0, $user) . '</select>
            </div>
            <div class="col-12 my-3" id="form_item_view_submitter_security_row" style="' . $form_item_view_submitter_security_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="form_item_view_submitter_security" name="form_item_view_submitter_security" class="form-check-input"  type="checkbox" role="switch"/>
                    <label class="form-check-label" for="form_item_view_submitter_security">' . lang('Allow only submitter and watchers to view his/her submitted form(s)') . '</label>
                </div>
            </div>
            <div class="col-12 my-3" id="form_item_view_submitted_form_editable_by_registered_user_row" style="' . $form_item_view_submitted_form_editable_by_registered_user_row_style . '">
                <div class="form-check form-switch">
                    <input value="1" id="form_item_view_submitted_form_editable_by_registered_user" name="form_item_view_submitted_form_editable_by_registered_user" class="form-check-input collapse-switcher"  type="checkbox" role="switch" data-bs-target="#show_or_hide_form_item_view_editor"/>
                    <label class="form-check-label" for="form_item_view_submitted_form_editable_by_registered_user">' . lang('Allow any registered user to edit submitted form(s)') . '</label>
                </div>
            </div>
            <div class="col-12" id="form_item_view_submitted_form_editable_by_submitter_row" style="' . $form_item_view_submitted_form_editable_by_submitter_row_style . '">
                <div class="my-3 collapse show-reverse" id="show_or_hide_form_item_view_editor">
                    <div class="form-check form-switch">
                        <input value="1" id="form_item_view_submitted_form_editable_by_submitter" name="form_item_view_submitted_form_editable_by_submitter" class="form-check-input"  type="checkbox" role="switch"/>
                        <label class="form-check-label" for="form_item_view_submitted_form_editable_by_submitter">' . lang('Allow submitter to edit his/her submitted form(s)') . '</label>
                    </div>
                </div>
            </div>';

        // If hooks are enabled and the user is a designer or administrator then output hook row for PHP code.
        if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
            $output_forms_page_type_properties .=
                '<div class="col-12 my-2" id="form_item_view_hook_code_row" style="' . $form_item_view_hook_code_row_style . '">
                    <label class="form-label" for="form_item_view_hook_code">' . lang('Hook Code') . '</label>
                    <textarea id="form_item_view_hook_code" name="form_item_view_hook_code" class="form-control"></textarea>
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
                    <input value="1" id="form_view_directory_summary" name="form_view_directory_summary" class="form-check-input collapse-switcher" checked="checked" type="checkbox" role="switch" data-bs-target="#show_or_hide_form_view_directory_summary" />
                    <label class="form-check-label" for="form_view_directory_summary">' . lang('Display Summary') . '</label>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_form_view_directory_summary">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label for="form_view_directory_summary_days" class="form-label">' . lang('Date Range') . '</label>
                                <div class="input-group">
                                    <input value="30" type="text" name="form_view_directory_summary_days" id="form_view_directory_summary_days" class="form-control" size="7" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                    <span class="input-group-text">' . lang('day(s)') . '</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 my-1">
                                <label for="form_view_directory_summary_maximum_number_of_results" class="form-label">' . lang('Maximum Number of Results') . '</label>
                                <input value="5" type="text" name="form_view_directory_summary_maximum_number_of_results" id="form_view_directory_summary_maximum_number_of_results" class="form-control" size="2" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_view_directory_form_list_view_heading_row" style="' . $form_view_directory_form_list_view_heading_row_style . '">
                <label for="form_view_directory_form_list_view_heading" class="form-label">' . lang('Form List View Heading') . '</label>
                <input value="' . lang('Forum') . '" type="text" name="form_view_directory_form_list_view_heading" id="form_view_directory_form_list_view_heading" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_view_directory_subject_heading_row" style="' . $form_view_directory_subject_heading_row_style . '">
                <label for="form_view_directory_subject_heading" class="form-label">' . lang('Subject Heading') . '</label>
                <input value="' . lang('Subject') . '" type="text" name="form_view_directory_subject_heading" id="form_view_directory_subject_heading" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="form_view_directory_number_of_submitted_forms_heading_row" style="' . $form_view_directory_number_of_submitted_forms_heading_row_style . '">
                <label for="form_view_directory_number_of_submitted_forms_heading" class="form-label">' . lang('Number of Submitted Forms Heading') . '</label>
                <input value="' . lang('Forms') . '" type="text" name="form_view_directory_number_of_submitted_forms_heading" id="form_view_directory_number_of_submitted_forms_heading" maxlength="50" class="form-control" />
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
        $calendar_event_view_calendar_check_boxes = '';
        $calendar_event_view_calendars_check_boxes = '';
        
        // loop through all calendars in order to check if user has access to calendar and if calendar should be checked
        foreach ($calendars as $calendar) {
            // if user has access to calendar, then continue
            if (validate_calendar_access($calendar['id']) == true) {
                $calendar_view_calendar_check_boxes .= '<div class="form-check"><input type="checkbox" id="calendar_view_calendar_' . $calendar['id'] . '" name="calendar_view_calendar_' . $calendar['id'] . '" value="1" class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="calendar_view_calendar_' . $calendar['id'] . '">' . h($calendar['name']) . '</label></div>';
                $calendar_event_view_calendar_check_boxes .= '<div class="form-check"><input type="checkbox" id="calendar_event_view_calendar_' . $calendar['id'] . '" name="calendar_event_view_calendar_' . $calendar['id'] . '" value="1" class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="calendar_event_view_calendar_' . $calendar['id'] . '">' . h($calendar['name']) . '</label></div>';
            }
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
                <select class="form-select" name="calendar_view_default_view" id="calendar_view_default_view" onchange="show_or_hide_calendar_view_number_of_upcoming_events()"><option value="monthly">' . lang('Monthly') . '</option><option value="weekly">' . lang('Weekly') . '</option><option value="upcoming">' . lang('Upcoming') . '</option></select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="calendar_view_number_of_upcoming_events_row" style="' . $calendar_view_number_of_upcoming_events_row_style . '">
                <label for="calendar_view_number_of_upcoming_events" class="form-label">' . lang('Number of Events') . '</label>
                <input name="calendar_view_number_of_upcoming_events" id="calendar_view_number_of_upcoming_events" type="text" value="5" maxlength="2" class="form-control" />
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="calendar_view_calendar_event_view_page_id_row" style="' . $calendar_view_calendar_event_view_page_id_row_style . '">
                <label for="calendar_view_calendar_event_view_page_id" class="form-label">' . lang('Calendar Event View') . '</label>
                <select class="form-select" name="calendar_view_calendar_event_view_page_id" id="calendar_view_calendar_event_view_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('calendar event view page')) )) . '-</option>' . select_page(0, 'calendar event view') . '</select>
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
                <input name="calendar_event_view_back_button_label" id="calendar_event_view_back_button_label" type="text" maxlength="50" class="form-control" />
            </div>
            <div class="col-12 my-2" id="calendar_event_view_notes_row" style="' . $calendar_event_view_notes_row_style . '">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="calendar_event_view_notes" name="calendar_event_view_notes" value="1">
                    <label class="form-check-label" for="calendar_event_view_notes">' . lang('Show Notes') . '</label>
                </div>
            </div>';
    }
    
    if (AFFILIATE_PROGRAM == true) {
        $output_affiliate_page_type_properties =
            '<div class="col-12 col-md-6 col-lg-4 my-2" id="affiliate_sign_up_form_terms_page_id_row" style="' . $affiliate_sign_up_form_terms_page_id_row_style . '">
                <label for="affiliate_sign_up_form_terms_page_id" class="form-label">' . lang('Terms Page') . '</label>
                <select class="form-select" name="affiliate_sign_up_form_terms_page_id" id="affiliate_sign_up_form_terms_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page() . '</select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="affiliate_sign_up_form_submit_button_label_row" style="' . $affiliate_sign_up_form_submit_button_label_row_style . '">
                <label for="affiliate_sign_up_form_submit_button_label" class="form-label">' . lang('Submit Button Label') . '</label>
                <input type="text" name="affiliate_sign_up_form_submit_button_label" id="affiliate_sign_up_form_submit_button_label" placeholder="' . lang('Sign Up') . '"  class="form-control" maxlength="50"/>
            </div>
            <div class="col-12 col-md-6 col-lg-4 my-2" id="affiliate_sign_up_form_next_page_id_row" style="' . $affiliate_sign_up_form_next_page_id_row_style . '">
                <label class="form-label" for="affiliate_sign_up_form_next_page_id">' . lang('Next Page') . '</label>
                <select class="form-select" id="affiliate_sign_up_form_next_page_id" name="affiliate_sign_up_form_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('affiliate sign up confirmation page')) )) . '-</option>' . select_page(0, 'affiliate sign up confirmation') . '</select>
            </div>';
    }
    
    echo
    pg_page_shell(
        array(
            'title'=> lang('Create Page'),
            'extra classes'=>'page',
            'icon'=>'page',
            'heading'=>lang('Create Page'),
            'cancel'=>array('enable'=>'true','url'=>'view_pages.php'),
            'breadcrumb' => array(
                array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
                array('label' => lang('Create Page')),
            ),
        )
    ) . '
    <script>
        //language objects for js for this page
        translate["Save"] = "' . lang('Save') . '";
        translate["Save & Continue"] = "' . lang('Save & Continue') . '";
    </script>
            ' . $output_wysiwyg_editor_code . '
        <div class="row">
            <div class="col-12">
                ' . $liveform_add_page->output_errors() . '
                ' . $liveform_add_page->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new page, place it in a folder, and add any built-in features.') . '" title="' . lang('Create Page') . '">[' . lang('Page Name') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_page.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12 col-md-4 col-lg-3 col-xl-2">
                            <div class="card my-4 position-sticky" style="top:56px;">
                                <label for="type" class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Page Type') . '
                                </label>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <select id="page_type" name="type" class="form-select" onchange="change_page_type(this.options[this.selectedIndex].value)">' . select_page_type('standard', $user) . '</select>
                                            <script>
                                                var original_page_type = "standard";
                                                $(document).ready(function() {
                                                    change_page_type($("select#page_type option:selected").val());
                                                });
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-8 col-lg-9 col-xl-10">
                            <div class="row">
                                <div class="col-12">
                                    <div class="card my-4 position-sticky" style="top:56px;">
                                        <label for="name" class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Main Informations') . '
                                        </label>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 col-md-8 col-lg-6 my-2">
                                                    <div class="input-group ">
                                                        <label for="name" class="input-group-text material-icons" title="' . lang('This option determines the url address of the page.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Page Name') . '}">public</label>
                                                        ' . $liveform_add_page->output_field(array('type'=>'text','id'=>'name','name'=>'name','placeholder'=>lang('Page Name'),'maxlength'=>'100', 'class'=>'form-control add-header-content-updater ', 'required'=>'required')) . '
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Content Options') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 col-md-6 my-2">
                                                    <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                                    <select class="form-select" id="folder" name="folder">' . select_folder($page_folder) . '</select>
                                                    <div class="form-text">' . lang('Page Access Control, Design &amp; Common Content') . '</div>
                                                </div>
                                            </div>
                                            <div class="row mt-4">
                                                <div class="col-12 col-md-6 col-lg-auto my-2">
                                                    <label for="style" class="form-label">' . lang('Desktop Page Style') . '</label>
                                                    ' . $output_style . '
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-auto my-2">
                                                    <label for="mobile_style_id" class="form-label">' . lang('Mobile Page Style') . '</label>
                                                    ' . $output_mobile_style . '
                                                </div>
                                                <div class="col-12 form-text">' . lang('Override Folder\'s Default Page Styles') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 collapse" id="options_row">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('System Options') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 my-2" id="layout_type_row" style="display: none">
                                                    <label class="form-label" for="">'. lang('Layout Type') . '</label>
                                                    <div class="form-check">
                                                        <input value="system" class="form-check-input" type="radio" id="layout_type_system" name="layout_type" checked="checked">
                                                        <label class="form-check-label" for="layout_type_system">'. lang('System') . '</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input value="custom" class="form-check-input" type="radio" id="layout_type_custom" name="layout_type"' . $layout_type_custom_option_disabled . '>
                                                        <label title="' . $layout_type_custom_label_title . '" class="form-check-label' . $layout_type_custom_label_class . '" for="layout_type_custom">'. lang('Custom') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4 my-2" id="email_a_friend_submit_button_label_row" style="' . $email_a_friend_submit_button_label_row_style . '">
                                                    <label for="email_a_friend_submit_button_label" class="form-label">' . lang('Submit Button Label') . '</label>
                                                    <input type="text" name="email_a_friend_submit_button_label" id="email_a_friend_submit_button_label" placeholder="' . lang('Submit') . '"  class="form-control" maxlength="50"/>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4 my-2" id="email_a_friend_next_page_id_row" style="' . $email_a_friend_next_page_id_row_style . '">
                                                    <label for="email_a_friend_next_page_id" class="form-label">' . lang('Next Page') . '</label>
                                                    <select name="email_a_friend_next_page_id" id="email_a_friend_next_page_id" class="form-select"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page() . '</select>
                                                </div>
                                                <div class="col-12 my-2" id="folder_view_pages_row" style="' . $folder_view_pages_row_style . '">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="folder_view_pages" name="folder_view_pages" value="1" checked="checked">
                                                        <label class="form-check-label" for="folder_view_pages">' . lang('Include Pages') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col-12 my-2" id="folder_view_files_row" style="' . $folder_view_files_row_style . '">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="folder_view_files" name="folder_view_files" value="1" checked="checked">
                                                        <label class="form-check-label" for="folder_view_files">' . lang('Include Files') . '</label>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-3 my-2" id="photo_gallery_number_of_columns_row" style="' . $photo_gallery_number_of_columns_row_style . '">
                                                    <label for="photo_gallery_number_of_columns" class="form-label">' . lang('Number of Columns') . '</label>
                                                    <input type="text" name="photo_gallery_number_of_columns" id="photo_gallery_number_of_columns" class="form-control" value="4" maxlength="2" inputmode="numeric" data-inputmask-alias="decimal"/>
                                                </div>
                                                <div class="col-12 col-md-8 col-lg-6 col-xl-4 my-2" id="photo_gallery_thumbnail_max_size_row" style="' . $photo_gallery_thumbnail_max_size_row_style . '">
                                                    <label for="photo_gallery_thumbnail_max_size" class="form-label">' . lang('Thumbnail Max Size') . '</label>
                                                    <div class="input-group">
                                                        <input type="text" name="photo_gallery_thumbnail_max_size" id="photo_gallery_thumbnail_max_size" class="form-control" value="100" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="100"  style="text-align: right;" />
                                                        <label for="photo_gallery_thumbnail_max_size"  class="input-group-text">' . lang('pixels') . '</label>
                                                    </div>
                                                </div>
                                                ' . $output_search_results_page_type_properties . '
                                                <div class="col-12 my-2" id="update_address_book_address_type_row" style="' . $update_address_book_address_type_row_style . '">
                                                    <div class="form-check form-switch">
                                                        <input value="1" id="update_address_book_address_type" name="update_address_book_address_type" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#update_address_book_address_type_page_id_row" />
                                                        <label class="form-check-label" for="update_address_book_address_type">' . lang('Enable Address Type') . '</label>
                                                    </div>
                                                    <div class="collapse popover  fade bs-popover-bottom p-0 mb-2" id="update_address_book_address_type_page_id_row">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12 col-md-12 my-1">
                                                                    <label for="update_address_book_address_type_page_id" class="form-label">' . lang('Address Type Page') . '</label>
                                                                    <select name="update_address_book_address_type_page_id" id="update_address_book_address_type_page_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page() . '</select>
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
                                </div>
                                <div class="col-12 my-3">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Search Engine Optimization') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 my-2" id="sitemap_row">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="sitemap" name="sitemap" value="1" checked="checked">
                                                        <label class="form-check-label" for="sitemap">' . lang('Include in Site Map?') . '</label>
                                                    </div>
                                                </div>
                                                ' . $output_noindex_rows . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>
    <script>
    // Indexing switch dependencies — logic in assets/backend.src.js
    bindPageIndexingSwitches();
    </script>' .
        output_footer();
    
    $liveform_add_page->unmark_errors('add_page');
    $liveform_add_page->clear_notices('add_page');
    
} else {
    validate_token_field();
    
    // verify that user has access to create page in the requested folder
    if (check_edit_access($_POST['folder']) == false) {
        log_activity(lang('access denied because user does not have access to create page in the requested folder'), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $name = trim($_POST['name']);
    
    // If the page name field is blank.
    if ($name == '') {
        $liveform_add_page->mark_error('name', lang('The page must have a name. Please type in a name for the page.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_page.php');
        exit();
    }
    
    // if the page type is catalog or catalog detail then check the name for slashes
    if (($_POST['type'] == 'catalog') || ($_POST['type'] == 'catalog detail')) {
        // if there is a slash in the page name, then output an error
        if (mb_strpos($name, '/') !== FALSE) {
            $liveform_add_page->mark_error('name', lang('The page name for catalog and catalog detail pages cannot contain forward slashes. Please type in a new name for the page.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_page.php');
            exit();
        }
    }
    
    $name = str_replace(" ", "_", $name);
    $name = str_replace("&", "_", $name);

    if (check_name_availability(array('name' => $name)) == false) {
        $liveform_add_page->mark_error('name', lang('The page name that you entered is already in use. Please enter a different page name.'));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_page.php');
        exit();
    }
    
    // if page is a custom form, check to see if there is another page with this same form name
    if ($_POST['type'] == 'custom form') {
        $query = "SELECT id FROM custom_form_pages WHERE form_name = '" . escape($_POST['custom_form_form_name'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // if there is another page with this form name, output error
        if (mysqli_num_rows($result) > 0) {
            $liveform_add_page->mark_error('custom_form_form_name', lang('The form name that you entered is already in use. Please enter a different form name.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_page.php');
            exit();
        }
    }

    $sql_style_fields = "";
    $sql_style_values = "";
    
    // if user role is Administrator, Designer, or Manager, then allow user to set style and mobile style for folder
    if ($user['role'] < 3) {
        $sql_style_fields =
            "page_style,
            mobile_style_id,";

        $sql_style_values =
            "'" . escape($_POST['style'] ?? '') . "',
            '" . escape($_POST['mobile_style_id'] ?? '') . "',";
    }
    
    // if the user has access to the selected page type, then set page type to selected page type
    if (
        ($user['role'] < 3)
        || ($_POST['type'] == 'standard')
        || (($_POST['type'] == 'email a friend') && ($user['set_page_type_email_a_friend'] == TRUE))
        || (($_POST['type'] == 'folder view') && ($user['set_page_type_folder_view'] == TRUE))
        || (($_POST['type'] == 'photo gallery') && ($user['set_page_type_photo_gallery'] == TRUE))
        || (($_POST['type'] == 'catalog') && ($user['set_page_type_catalog'] == TRUE))
        || (($_POST['type'] == 'catalog detail') && ($user['set_page_type_catalog_detail'] == TRUE))
        || (($_POST['type'] == 'express order') && ($user['set_page_type_express_order'] == TRUE))
        || (($_POST['type'] == 'order form') && ($user['set_page_type_order_form'] == TRUE))
        || (($_POST['type'] == 'shopping cart') && ($user['set_page_type_shopping_cart'] == TRUE))
        || (($_POST['type'] == 'shipping address and arrival') && ($user['set_page_type_shipping_address_and_arrival'] == TRUE))
        || (($_POST['type'] == 'shipping method') && ($user['set_page_type_shipping_method'] == TRUE))
        || (($_POST['type'] == 'billing information') && ($user['set_page_type_billing_information'] == TRUE))
        || (($_POST['type'] == 'order preview') && ($user['set_page_type_order_preview'] == TRUE))
        || (($_POST['type'] == 'order receipt') && ($user['set_page_type_order_receipt'] == TRUE))
        || (($_POST['type'] == 'custom form') && ($user['set_page_type_custom_form'] == TRUE))
        || (($_POST['type'] == 'custom form confirmation') && ($user['set_page_type_custom_form_confirmation'] == TRUE))
        || (($_POST['type'] == 'form list view') && ($user['set_page_type_form_list_view'] == TRUE))
        || (($_POST['type'] == 'form item view') && ($user['set_page_type_form_item_view'] == TRUE))
        || (($_POST['type'] == 'form view directory') && ($user['set_page_type_form_view_directory'] == TRUE))
        || (($_POST['type'] == 'calendar view') && ($user['manage_calendars'] == TRUE) && ($user['set_page_type_calendar_view'] == TRUE))
        || (($_POST['type'] == 'calendar event view') && ($user['manage_calendars'] == TRUE) && ($user['set_page_type_calendar_event_view'] == TRUE))
    ) {
        $type = $_POST['type'];
        
    // else the user does not have access to the selected page type, so set type to standard
    } else {
        $type = 'standard';
    }

    // If the page type supports a layout, and if the user is an admin or designer,
    // then use layout type value that user selected.
    if (
        check_if_page_type_supports_layout($type)
        && (USER_ROLE < 2)
    ) {
        $layout_type = $_POST['layout_type'];

    // Otherwise force layout type to be system.
    } else {
        $layout_type = 'system';
    }
    
    // Resolved into a variable rather than read straight into the query,
    // because the indexing switch below can override it. The cast also keeps an
    // unchecked checkbox - absent from the POST entirely - out of the TINYINT
    // column as an empty string.
    $sitemap = ((($_POST['sitemap'] ?? '') == 1) ? 1 : 0);

    $noindex = 0;
    $nofollow = 0;

    // nofollow qualifies the noindex directive and is never emitted on its own,
    // so it is only stored while the page is closed to search engines.
    if (($_POST['noindex'] ?? '') == 1) {
        $noindex = 1;

        if (($_POST['nofollow'] ?? '') == 1) {
            $nofollow = 1;
        }
    }

    // A page that is closed to search engines has no business in the site map.
    if ($noindex == 1) {
        $sitemap = 0;
    }

    $sql_noindex_columns = "";
    $sql_noindex_values = "";

    if (pg_page_noindex_ready() == TRUE) {
        $sql_noindex_columns =
            "noindex,
            nofollow,";

        $sql_noindex_values =
            "'" . $noindex . "',
            '" . $nofollow . "',";
    }

    // insert row into page table
    //
    // page_home and page_search are literals: a new page is never the home page
    // and starts outside the site search. page_search is an INT column, so its
    // literal has to be a number - a server running with STRICT_TRANS_TABLES,
    // the default since MySQL 5.7 and MariaDB 10.2, rejects an empty string for
    // it and page creation fails on the query.
    $query =
        "INSERT INTO page (
            page_name,
            page_folder,
            page_type,
            layout_type,
            page_home,
            page_search,
            page_search_keywords,
            page_timestamp,
            page_user,
            $sql_style_fields
            page_title,
            page_meta_description,
            comments_disallow_new_comment_message,
            $sql_noindex_columns
            sitemap)
        VALUES (
            '" . escape($name) . "',
            '" . escape($_POST['folder'] ?? '') . "',
            '" . escape($type) . "',
            '" . e($layout_type) . "',
            '0',
            '0',
            '',
            UNIX_TIMESTAMP(),
            '$user[id]',
            $sql_style_values
            '',
            '',
            'We\'re sorry. New comments are no longer being accepted.',
            $sql_noindex_values
            '" . $sitemap . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $page_id = mysqli_insert_id(db::$con);
    
    // set page type properties, if necessary
    switch($type) {
        case 'email a friend':
            $properties = array(
                'page_id' => $page_id,
                'submit_button_label' => $_POST['email_a_friend_submit_button_label'],
                'next_page_id' => $_POST['email_a_friend_next_page_id']
            );
            
            break;

        case 'folder view':
            $properties = array(
                'page_id' => $page_id,
                'pages' => $_POST['folder_view_pages'],
                'files' => $_POST['folder_view_files']
            );
            
            break;
            
        case 'photo gallery':
            $properties = array(
                'page_id' => $page_id,
                'number_of_columns' => $_POST['photo_gallery_number_of_columns'],
                'thumbnail_max_size' => $_POST['photo_gallery_thumbnail_max_size']
            );
            
            break;
            
        case 'search results':
            $properties = array(
                'page_id' => $page_id,
                'search_folder_id' => $_POST['search_results_search_folder_id'],
                'search_catalog_items' => $_POST['search_results_search_catalog_items'],
                'product_group_id' => $_POST['search_results_product_group_id'],
                'catalog_detail_page_id' => $_POST['search_results_catalog_detail_page_id']
            );
            
            // update the tag cloud tables if needed
            update_tag_cloud_keywords_for_search_results_page_type($page_id, $_POST['search_results_search_catalog_items'], $_POST['search_results_product_group_id']);
            
            break;
            
        case 'update address book':
            $properties = array(
                'page_id' => $page_id,
                'address_type' => $_POST['update_address_book_address_type'],
                'address_type_page_id' => $_POST['update_address_book_address_type_page_id']
            );
            
            break;
            
        case 'custom form':

            $custom_form_contact_group_id = $_POST['custom_form_contact_group_id'];
            
            // if user has a user role,
            // and a contact group was selected
            // and user does not have access to contact group,
            // then don't allow contact group to be changed
            if (($user['role'] == 3) && ($custom_form_contact_group_id != 0) && (validate_contact_group_access($user, $custom_form_contact_group_id) == false)) {
                $custom_form_contact_group_id = 0;
                log_activity(lang('access denied to set contact group for custom form because user did not have access to contact group'), $_SESSION['sessionusername']);
            }

            $custom_form_private_folder_id = $_POST['custom_form_private_folder_id'];

            // If the user selected a private folder that he/she does not have edit access to,
            // then don't allow folder to be set and log activity.
            if ($custom_form_private_folder_id && (check_edit_access($custom_form_private_folder_id) == false)) {
                $custom_form_private_folder_id = 0;
                log_activity(lang('access denied to set private folder for custom form because user did not have edit access to folder'), $_SESSION['sessionusername']);
            }
            
            $properties = array(
                'page_id' => $page_id,
                'form_name' => $_POST['custom_form_form_name'],
                'enabled' => $_POST['custom_form_enabled'],
                'quiz' => $_POST['custom_form_quiz'],
                'quiz_pass_percentage' => $_POST['custom_form_quiz_pass_percentage'],
                'label_column_width' => $_POST['custom_form_label_column_width'],
                'watcher_page_id' => $_POST['custom_form_watcher_page_id'],
                'save' => $_POST['custom_form_save'],
                'submit_button_label' => $_POST['custom_form_submit_button_label'],
                'auto_registration' => $_POST['custom_form_auto_registration'],
                'submitter_email' => $_POST['custom_form_submitter_email'],
                'submitter_email_from_email_address' => $_POST['custom_form_submitter_email_from_email_address'],
                'submitter_email_subject' => $_POST['custom_form_submitter_email_subject'],
                'submitter_email_format' => $_POST['custom_form_submitter_email_format'],
                'submitter_email_body' => $_POST['custom_form_submitter_email_body'],
                'submitter_email_page_id' => $_POST['custom_form_submitter_email_page_id'],
                'administrator_email' => $_POST['custom_form_administrator_email'],
                'administrator_email_to_email_address' => $_POST['custom_form_administrator_email_to_email_address'],
                'administrator_email_bcc_email_address' => $_POST['custom_form_administrator_email_bcc_email_address'],
                'administrator_email_subject' => $_POST['custom_form_administrator_email_subject'],
                'administrator_email_format' => $_POST['custom_form_administrator_email_format'],
                'administrator_email_body' => $_POST['custom_form_administrator_email_body'],
                'administrator_email_page_id' => $_POST['custom_form_administrator_email_page_id'],
                'contact_group_id' => $custom_form_contact_group_id,
                'membership' => $_POST['custom_form_membership'],
                'membership_days' => $_POST['custom_form_membership_days'],
                'membership_start_page_id' => $_POST['custom_form_membership_start_page_id'],
                'private' => $_POST['custom_form_private'],
                'private_folder_id' => $custom_form_private_folder_id,
                'private_days' => $_POST['custom_form_private_days'],
                'private_start_page_id' => $_POST['custom_form_private_start_page_id'],
                'confirmation_type' => $_POST['custom_form_confirmation_type'],
                'confirmation_message' => prepare_rich_text_editor_content_for_input($_POST['custom_form_confirmation_message']),
                'confirmation_page_id' => $_POST['custom_form_confirmation_page_id'],
                'confirmation_alternative_page' => $_POST['custom_form_confirmation_alternative_page'],
                'confirmation_alternative_page_contact_group_id' => $_POST['custom_form_confirmation_alternative_page_contact_group_id'],
                'confirmation_alternative_page_id' => $_POST['custom_form_confirmation_alternative_page_id'],
                'return_type' => $_POST['custom_form_return_type'],
                'return_message' => prepare_rich_text_editor_content_for_input($_POST['custom_form_return_message']),
                'return_page_id' => $_POST['custom_form_return_page_id'],
                'return_alternative_page' => $_POST['custom_form_return_alternative_page'],
                'return_alternative_page_contact_group_id' => $_POST['custom_form_return_alternative_page_contact_group_id'],
                'return_alternative_page_id' => $_POST['custom_form_return_alternative_page_id'],
                'pretty_urls' => $_POST['custom_form_pretty_urls']
            );
            
            // If hooks are enabled and the user is a designer or administrator then prepare property for PHP hook code.
            if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
                $properties['hook_code'] = $_POST['custom_form_hook_code'];
            }

            // If commerce is enabled and the user has access to commerce, then save offer properties.
            if ((ECOMMERCE) && (USER_MANAGE_ECOMMERCE)) {
                $properties['offer'] = $_POST['custom_form_offer'];
                $properties['offer_id'] = $_POST['custom_form_offer_id'];
                $properties['offer_days'] = $_POST['custom_form_offer_days'];
                $properties['offer_eligibility'] = $_POST['custom_form_offer_eligibility'];
            }
            
            break;
        
        case 'custom form confirmation':
            $properties = array(
                'page_id' => $page_id,
                'continue_button_label' => $_POST['custom_form_confirmation_continue_button_label'],
                'next_page_id' => $_POST['custom_form_confirmation_next_page_id']
            );
            
            break;
        
        case 'form list view':
            $form_list_view_custom_form_page_id = $_POST['form_list_view_custom_form_page_id'];
            
            // if user has a user role, then verify that user has access to custom form that was selected
            if ($user['role'] == 3) {
                // get folder of custom form
                $query =
                    "SELECT page_folder
                    FROM page
                    WHERE page_id = '" . escape($form_list_view_custom_form_page_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                
                $form_list_view_custom_form_folder_id = $row['page_folder'];
                
                // if the user does not have access to custom form, don't allow custom form to be changed
                if (check_edit_access($form_list_view_custom_form_folder_id) == false) {
                    $form_list_view_custom_form_page_id = 0;
                    log_activity(lang('access denied to set custom form for form list view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                }
            }
            
            $properties = array(
                'page_id' => $page_id,
                'custom_form_page_id' => $form_list_view_custom_form_page_id,
                'maximum_number_of_results_per_page' => 25,
                'search' => 1,
                'search_label' => 'Search',
                'show_results_by_default' => 1,
                'form_item_view_page_id' => $_POST['form_list_view_form_item_view_page_id'],
                'viewer_filter' => $_POST['form_list_view_viewer_filter'],
                'viewer_filter_submitter' => $_POST['form_list_view_viewer_filter_submitter'],
                'viewer_filter_watcher' => $_POST['form_list_view_viewer_filter_watcher'],
                'viewer_filter_editor' => $_POST['form_list_view_viewer_filter_editor']
            );
            
            break;
        
        case 'form item view':
            $form_item_view_custom_form_page_id = $_POST['form_item_view_custom_form_page_id'];
            
            // if user has a user role, then verify that user has access to custom form that was selected
            if ($user['role'] == 3) {
                // get folder of custom form
                $query = "SELECT page_folder
                         FROM page
                         WHERE page_id = '" . escape($form_item_view_custom_form_page_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                
                $form_item_view_custom_form_folder_id = $row['page_folder'];
                
                // if the user does not have access to custom form, don't allow custom form to be changed
                if (check_edit_access($form_item_view_custom_form_folder_id) == false) {
                    $form_item_view_custom_form_page_id = 0;
                    log_activity(lang('access denied to set custom form for form item view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                }
            }
            
            $properties = array(
                'page_id' => $page_id,
                'custom_form_page_id' => $form_item_view_custom_form_page_id,
                'submitter_security' => $_POST['form_item_view_submitter_security'],
                'submitted_form_editable_by_registered_user' => $_POST['form_item_view_submitted_form_editable_by_registered_user'],
                'submitted_form_editable_by_submitter' => $_POST['form_item_view_submitted_form_editable_by_submitter']
            );

            // If hooks are enabled and the user is a designer or administrator then prepare property for PHP hook code.
            if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
                $properties['hook_code'] = $_POST['form_item_view_hook_code'];
            }
            
            break;
            
        case 'form view directory':
            $form_list_view_heading = $_POST['form_view_directory_form_list_view_heading'];
            $subject_heading = $_POST['form_view_directory_subject_heading'];
            $number_of_submitted_forms_heading = $_POST['form_view_directory_number_of_submitted_forms_heading'];
            
            // if the form list view heading is blank, then set it to the default value
            if ($form_list_view_heading == '') {
                $form_list_view_heading = 'Forum';
            }
            
            // if the subject heading is blank, then set it to the default value
            if ($subject_heading == '') {
                $subject_heading = 'Subject';
            }
            
            // if the number of submitted forms heading is blank, then set it to the default value
            if ($number_of_submitted_forms_heading == '') {
                $number_of_submitted_forms_heading = 'Forms';
            }
            
            $properties = array(
                'page_id' => $page_id,
                'summary' => $_POST['form_view_directory_summary'],
                'summary_days' => $_POST['form_view_directory_summary_days'],
                'summary_maximum_number_of_results' => $_POST['form_view_directory_summary_maximum_number_of_results'],
                'form_list_view_heading' => $form_list_view_heading,
                'subject_heading' => $subject_heading,
                'number_of_submitted_forms_heading' => $number_of_submitted_forms_heading
            );
            
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
            
            // loop through all of the form list views in order to add them to the database if necessary
            foreach ($form_list_views as $form_list_view) {
                // if the user has edit access to the form list view and the user selected it, then add it to the data
                if ((check_folder_access_in_array($form_list_view['folder_id'], $folders_that_user_has_access_to) == TRUE) && (($_POST['form_view_directory_form_list_view_' . $form_list_view['page_id']] ?? '') == 1)) {
                    $query =
                        "INSERT INTO form_view_directories_form_list_views_xref (
                            form_view_directory_page_id,
                            form_list_view_page_id,
                            form_list_view_name,
                            subject_form_field_id)
                        VALUES (
                            '" . escape($page_id) . "',
                            '" . $form_list_view['page_id'] . "',
                            '" . escape($_POST['form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name']) . "',
                            '" . escape($_POST['form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_subject_form_field_id']) . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            break;
            
        case 'calendar view':
            $properties = array(
                'page_id' => $page_id,
                'default_view' => $_POST['calendar_view_default_view'],
                'number_of_upcoming_events' => $_POST['calendar_view_number_of_upcoming_events'],
                'calendar_event_view_page_id' => $_POST['calendar_view_calendar_event_view_page_id']
            );
            
            // get calendars in order to connect calendar view to calendars
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
            
            // loop through calendars in order to connect calendar view to calendars
            foreach ($calendars as $calendar) {
                // if user has access to calendar and calendar was checked then add connection between calendar view and calendar
                if ((validate_calendar_access($calendar['id']) == true) && ($_POST['calendar_view_calendar_' . $calendar['id']])) {
                    $query =
                        "INSERT INTO calendar_views_calendars_xref (
                           page_id,
                           calendar_id)
                        VALUES (
                           '" . escape($page_id) . "',
                           '" . $calendar['id'] . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            break;
            
        case 'calendar event view':
            $properties = array(
                'page_id' => $page_id,
                'notes' => $_POST['calendar_event_view_notes'],
                'back_button_label' => $_POST['calendar_event_view_back_button_label']
            );
            
            // get calendars in order to connect calendar event view to calendars
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
            
            // loop through calendars in order to connect calendar event view to calendars
            foreach ($calendars as $calendar) {
                // if user has access to calendar and calendar was checked then add connection between calendar event view and calendar
                if ((validate_calendar_access($calendar['id']) == true) && ($_POST['calendar_event_view_calendar_' . $calendar['id']])) {
                    $query =
                        "INSERT INTO calendar_event_views_calendars_xref (
                           page_id,
                           calendar_id)
                        VALUES (
                           '" . escape($page_id) . "',
                           '" . $calendar['id'] . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            break;
            
        case 'catalog':
            // if number_of_columns is less than 1, then set number_of_columns to 1
            if ($_POST['catalog_number_of_columns'] < 1) {
                $catalog_number_of_columns = 1;
                
            // else number_of_columns is not less than 1, so set number_of_columns to what user entered
            } else {
                $catalog_number_of_columns = $_POST['catalog_number_of_columns'];
            }
            
            $properties = array(
                'page_id' => $page_id,
                'product_group_id' => $_POST['catalog_product_group_id'],
                'menu' => $_POST['catalog_menu'],
                'search' => $_POST['catalog_search'],
                'number_of_featured_items' => $_POST['catalog_number_of_featured_items'],
                'number_of_new_items' => $_POST['catalog_number_of_new_items'],
                'number_of_columns' => $catalog_number_of_columns,
                'image_width' => $_POST['catalog_image_width'],
                'image_height' => $_POST['catalog_image_height'],
                'back_button_label' => $_POST['catalog_back_button_label'],
                'catalog_detail_page_id' => $_POST['catalog_catalog_detail_page_id']
            );
            
            break;
            
        case 'catalog detail':
            $properties = array(
                'page_id' => $page_id,
                'allow_customer_to_add_product_to_order' => $_POST['catalog_detail_allow_customer_to_add_product_to_order'],
                'add_button_label' => $_POST['catalog_detail_add_button_label'],
                'next_page_id' => $_POST['catalog_detail_next_page_id'],
                'back_button_label' => $_POST['catalog_detail_back_button_label']
            );
            
            break;
        
        case 'express order':
            $properties = array(
                'page_id' => $page_id,
                'shopping_cart_label' => $_POST['express_order_shopping_cart_label'],
                'quick_add_label' => $_POST['express_order_quick_add_label'],
                'quick_add_product_group_id' => $_POST['express_order_quick_add_product_group_id'],
                'product_description_type' => $_POST['product_description_type'],
                'shipping_form' => $_POST['express_order_shipping_form'],
                'special_offer_code_label' => $_POST['express_order_special_offer_code_label'],
                'special_offer_code_message' => $_POST['express_order_special_offer_code_message'],
                'custom_field_1_label' => $_POST['express_order_custom_field_1_label'],
                'custom_field_1_required' => $_POST['express_order_custom_field_1_required'],
                'custom_field_2_label' => $_POST['express_order_custom_field_2_label'],
                'custom_field_2_required' => $_POST['express_order_custom_field_2_required'],
                'po_number' => $_POST['express_order_po_number'],
                'form' => $_POST['express_order_form'],
                'form_name' => $_POST['express_order_form_name'],
                'form_label_column_width' => $_POST['express_order_form_label_column_width'],
                'card_verification_number_page_id' => $_POST['express_order_card_verification_number_page_id'],
                'terms_page_id' => $_POST['express_order_terms_page_id'],
                'update_button_label' => $_POST['express_order_update_button_label'],
                'purchase_now_button_label' => $_POST['express_order_purchase_now_button_label'],
                'auto_registration' => $_POST['express_order_auto_registration'],
                'order_receipt_email' => $_POST['express_order_order_receipt_email'],
                'order_receipt_email_subject' => $_POST['express_order_order_receipt_email_subject'],
                'order_receipt_email_format' => $_POST['express_order_order_receipt_email_format'],
                'order_receipt_email_header' => $_POST['express_order_order_receipt_email_header'],
                'order_receipt_email_footer' => $_POST['express_order_order_receipt_email_footer'],
                'order_receipt_email_page_id' => $_POST['express_order_order_receipt_email_page_id'],
                'next_page_id' => $_POST['express_order_next_page_id']
            );
            
            // if online payments is on, then update the offline payment properties.
            if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
                $properties['offline_payment_always_allowed'] = $_POST['express_order_offline_payment_always_allowed'];
                $properties['offline_payment_label'] = $_POST['express_order_offline_payment_label'];
            }

            // If hooks are enabled and the user is a designer or administrator then prepare properties for PHP hook code.
            if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
                $properties['pre_save_hook_code'] = $_POST['express_order_pre_save_hook_code'];
                $properties['post_save_hook_code'] = $_POST['express_order_post_save_hook_code'];
            }
            
            break;
        
        case 'order form':
            $properties = array(
                'page_id' => $page_id,
                'product_group_id' => $_POST['order_form_product_group_id'],
                'product_layout' => $_POST['order_form_product_layout'],
                'add_button_label' => $_POST['order_form_add_button_label'],
                'add_button_next_page_id' => $_POST['order_form_add_button_next_page_id'],
                'skip_button_label' => $_POST['order_form_skip_button_label'],
                'skip_button_next_page_id' => $_POST['order_form_skip_button_next_page_id']
            );
            
            break;
        
        case 'shopping cart':
            $properties = array(
                'page_id' => $page_id,
                'shopping_cart_label' => $_POST['shopping_cart_shopping_cart_label'],
                'quick_add_label' => $_POST['shopping_cart_quick_add_label'],
                'quick_add_product_group_id' => $_POST['shopping_cart_quick_add_product_group_id'],
                'product_description_type' => $_POST['product_description_type'],
                'special_offer_code_label' => $_POST['shopping_cart_special_offer_code_label'],
                'special_offer_code_message' => $_POST['shopping_cart_special_offer_code_message'],
                'update_button_label' => $_POST['shopping_cart_update_button_label'],
                'checkout_button_label' => $_POST['shopping_cart_checkout_button_label'],
                'next_page_id_with_shipping' => $_POST['shopping_cart_next_page_id_with_shipping'],
                'next_page_id_without_shipping' => $_POST['shopping_cart_next_page_id_without_shipping']
            );

            // If hooks are enabled and the user is a designer or administrator then prepare property for PHP hook code.
            if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
                $properties['hook_code'] = $_POST['shopping_cart_hook_code'];
            }
            
            break;

        case 'shipping address and arrival':
            $properties = array(
                'page_id' => $page_id,
                'address_type' => $_POST['shipping_address_and_arrival_address_type'],
                'address_type_page_id' => $_POST['shipping_address_and_arrival_address_type_page_id'],
                'form' => $_POST['shipping_address_and_arrival_form'],
                'form_name' => $_POST['shipping_address_and_arrival_form_name'],
                'form_label_column_width' => $_POST['shipping_address_and_arrival_form_label_column_width'],
                'submit_button_label' => $_POST['shipping_address_and_arrival_submit_button_label'],
                'next_page_id' => $_POST['shipping_address_and_arrival_next_page_id']
            );
            
            break;

        case 'shipping method':
            $properties = array(
                'page_id' => $page_id,
                'product_description_type' => $_POST['product_description_type'],
                'submit_button_label' => $_POST['shipping_method_submit_button_label'],
                'next_page_id' => $_POST['shipping_method_next_page_id']
            );
            
            break;

        case 'billing information':
            $properties = array(
                'page_id' => $page_id,
                'custom_field_1_label' => $_POST['billing_information_custom_field_1_label'],
                'custom_field_1_required' => $_POST['billing_information_custom_field_1_required'],
                'custom_field_2_label' => $_POST['billing_information_custom_field_2_label'],
                'custom_field_2_required' => $_POST['billing_information_custom_field_2_required'],
                'po_number' => $_POST['billing_information_po_number'],
                'form' => $_POST['billing_information_form'],
                'form_name' => $_POST['billing_information_form_name'],
                'form_label_column_width' => $_POST['billing_information_form_label_column_width'],
                'submit_button_label' => $_POST['billing_information_submit_button_label'],
                'next_page_id' => $_POST['billing_information_next_page_id']
            );
            
            break;

        case 'order preview':
            $properties = array(
                'page_id' => $page_id,
                'product_description_type' => $_POST['product_description_type'],
                'card_verification_number_page_id' => $_POST['order_preview_card_verification_number_page_id'],
                'terms_page_id' => $_POST['order_preview_terms_page_id'],
                'submit_button_label' => $_POST['order_preview_submit_button_label'],
                'auto_registration' => $_POST['order_preview_auto_registration'],
                'order_receipt_email' => $_POST['order_preview_order_receipt_email'],
                'order_receipt_email_subject' => $_POST['order_preview_order_receipt_email_subject'],
                'order_receipt_email_format' => $_POST['order_preview_order_receipt_email_format'],
                'order_receipt_email_header' => $_POST['order_preview_order_receipt_email_header'],
                'order_receipt_email_footer' => $_POST['order_preview_order_receipt_email_footer'],
                'order_receipt_email_page_id' => $_POST['order_preview_order_receipt_email_page_id'],
                'next_page_id' => $_POST['order_preview_next_page_id']
            );
        
            // if online payments is on, then update the offline payment properties.
            if (ECOMMERCE_OFFLINE_PAYMENT == TRUE) {
                $properties['offline_payment_always_allowed'] = $_POST['order_preview_offline_payment_always_allowed'];
                $properties['offline_payment_label'] = $_POST['order_preview_offline_payment_label'];
            }

            // If hooks are enabled and the user is a designer or administrator then prepare properties for PHP hook code.
            if ((defined('PHP_REGIONS') and PHP_REGIONS === true) && (USER_ROLE < 2)) {
                $properties['pre_save_hook_code'] = $_POST['order_preview_pre_save_hook_code'];
                $properties['post_save_hook_code'] = $_POST['order_preview_post_save_hook_code'];
            }
            
            break;

        case 'order receipt':
            $properties = array(
                'page_id' => $page_id,
                'product_description_type' => $_POST['order_receipt_product_description_type']
            );
            
            break;
            
        case 'affiliate sign up form':
            $properties = array(
                'page_id' => $page_id,
                'terms_page_id' => $_POST['affiliate_sign_up_form_terms_page_id'],
                'submit_button_label' => $_POST['affiliate_sign_up_form_submit_button_label'],
                'next_page_id' => $_POST['affiliate_sign_up_form_next_page_id']
            );
            
            break;
    }
    
    // if page type has a table for properties, create record in page type table
    if (check_for_page_type_properties($type) == true) {
        create_or_update_page_type_record($type, $properties);
    }

    // get style so that we can create regions
    // if default was selected for style
    if ($_POST['style'] == 0) {
        $style = get_style($_POST['folder']);
    // else default was not selected
    } else {
        $style = $_POST['style'];
    }

    // get style code
    $result = mysqli_query(db::$con, "SELECT style_code FROM style WHERE style_id = '" . escape($style) . "'") or output_error('Query failed');
    $row = mysqli_fetch_assoc($result);
    $style_code = $row['style_code'];

    // create regions
    $pregion_count = 1;
    preg_match_all('/<pregion>.*?<\/pregion>/i', $style_code, $regions);
    foreach ($regions[0] as $region)
    {
        $region_name = time() .'_' . $pregion_count;
        $query = "INSERT INTO pregion (pregion_name, pregion_content, pregion_page, pregion_order, pregion_user, pregion_timestamp) VALUES ('$region_name', '', '$page_id', '$pregion_count', '$user[id]', UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        $pregion_count++;
    }

    log_activity(lang(array('string'=>'page ({var:1}) was created','vars'=>$name)), $_SESSION['sessionusername']);

    // If this page is a custom form, or a custom shipping or custom billing form was enabled,
    // then forward user to form designer.
    if (
        ($type == 'custom form')
        || (($type == 'shipping address and arrival') && ($_POST['shipping_address_and_arrival_form'] == 1))
        || (($type == 'billing information') && ($_POST['billing_information_form'] == 1))
        || (
            ($type == 'express order') and ($_POST['express_order_shipping_form'] or $_POST['express_order_form'])
        )
    ) {

        $form_type = '';

        // If this is an express order page, then determine if we should forward to shipping
        // or billing form.
        if ($type == 'express order') {

            $form_type = '&form_type=';

            if ($_POST['express_order_shipping_form']) {
                $form_type .= 'shipping';
            } else {
                $form_type .= 'billing';
            }
        }

        $query_string_from = '';
        
        // if the page is a shipping address and arrival page, then prepare from
        if ($type == 'shipping address and arrival') {
            $query_string_from = '?from=control_panel';
        }
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . $form_type . '&send_to=' . urlencode(PATH . $name . $query_string_from));
        
    // else we don't need to forward the user to the form designer so forward the user to the page
    } else {
        $query_string_from = '';
        
        // if page type is a certain page type, then prepare from
        switch ($type) {
            case 'view order':
            case 'custom form':
            case 'custom form confirmation':
            case 'calendar event view':
            case 'catalog detail':
            case 'shipping address and arrival':
            case 'shipping method':
            case 'logout':
                $query_string_from = '?from=control_panel';
                break; 
        }
        
        // forward user to view page
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path($name) . $query_string_from);
    }
}