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
require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');

$liveform = new liveform('edit_page');

$user = validate_user();
validate_area_access($user, 'user');

// get page's folder in order to validate folder access
$result = mysqli_query(db::$con, "SELECT page_id, page_folder FROM page WHERE page_id = '" . escape($_REQUEST['id']) . "'") or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

if (!$row['page_id']) {
    output_error(lang('Sorry, the page could not be found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

if (check_edit_access($row['page_folder']) == false) {
    log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

if (!$_POST) {
    $query =
        "SELECT
            page_id,
            page_name,
            page_folder,
            page_home,
            page_search,
            page_search_keywords,
            page_style,
            mobile_style_id,
            page_title,
            page_meta_description,
            sitemap,
            " . (pg_page_noindex_ready() ? "noindex, nofollow," : "'0' AS noindex, '0' AS nofollow,") . "
            page_type,
            layout_type,
            comments,
            comments_label,
            comments_message,
            comments_rating,
            comments_allow_new_comments,
            comments_disallow_new_comment_message,
            comments_automatic_publish,
            comments_allow_user_to_select_name,
            comments_require_login_to_comment,
            comments_allow_file_attachments,
            comments_show_submitted_date_and_time,
            comments_administrator_email_to_email_address,
            comments_administrator_email_subject,
            comments_administrator_email_conditional_administrators,
            comments_submitter_email_page_id,
            comments_submitter_email_subject,
            comments_watcher_email_page_id,
            comments_watcher_email_subject,
            comments_watchers_managed_by_submitter,
            seo_score,
            " . (pg_seo_schema_ready() ? "seo_flags, seo_checked_at," : "'0' AS seo_flags, '0' AS seo_checked_at,") . "
            seo_analysis,
            seo_analysis_current
        FROM page
        WHERE page_id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $page_id = $row['page_id'];
    $page_name = $row['page_name'];
    $page_folder = $row['page_folder'];
    $page_home = $row['page_home'];
    $page_search = $row['page_search'];
    $page_search_keywords = $row['page_search_keywords'];
    $page_style = $row['page_style'];
    $mobile_style_id = $row['mobile_style_id'];
    $page_title = $row['page_title'];
    $page_meta_description = $row['page_meta_description'];
    // seo_checked_at travels with the rest: pg_seo_row_scored() asks it
    // first, and a row without it falls back to seo_analysis_current, which
    // means "the number is up to date" rather than "there is a number". Every
    // save marks the record stale, so the panel would go blank after each one
    // and claim the score had never been calculated.
    $seo_row = array(
        'seo_score' => $row['seo_score'],
        'seo_flags' => $row['seo_flags'],
        'seo_checked_at' => $row['seo_checked_at'],
        'seo_analysis' => $row['seo_analysis'],
        'seo_analysis_current' => $row['seo_analysis_current'],
    );
    $sitemap = $row['sitemap'];
    $noindex = $row['noindex'];
    $nofollow = $row['nofollow'];
    $page_type = $row['page_type'];
    $layout_type = $row['layout_type'];
    $comments = $row['comments'];
    $comments_label = $row['comments_label'];
    $comments_message = $row['comments_message'];
    $comments_rating = $row['comments_rating'];
    $comments_allow_new_comments = $row['comments_allow_new_comments'];
    $comments_disallow_new_comment_message = $row['comments_disallow_new_comment_message'];
    $comments_automatic_publish = $row['comments_automatic_publish'];
    $comments_allow_user_to_select_name = $row['comments_allow_user_to_select_name'];
    $comments_require_login_to_comment = $row['comments_require_login_to_comment'];
    $comments_allow_file_attachments = $row['comments_allow_file_attachments'];
    $comments_show_submitted_date_and_time = $row['comments_show_submitted_date_and_time'];
    $comments_administrator_email_to_email_address = $row['comments_administrator_email_to_email_address'];
    $comments_administrator_email_subject = $row['comments_administrator_email_subject'];
    $comments_administrator_email_conditional_administrators = $row['comments_administrator_email_conditional_administrators'];
    $comments_submitter_email_page_id = $row['comments_submitter_email_page_id'];
    $comments_submitter_email_subject = $row['comments_submitter_email_subject'];
    $comments_watcher_email_page_id = $row['comments_watcher_email_page_id'];
    $comments_watcher_email_subject = $row['comments_watcher_email_subject'];
    $comments_watchers_managed_by_submitter = $row['comments_watchers_managed_by_submitter'];

    $output_wysiwyg_editor_code = '';
    
    $output_subnav_page_type  = '';
    
    // Get page type
    if ($page_type != '') {
        $output_subnav_page_type = ' | ' . lang('Page Type') . ': ' . h(get_page_type_name($page_type));
    }
    
    $output_subnav_short_link = '';

    // Get most recent short link for this page if one exists.
    $query =
        "SELECT name
        FROM short_links
        WHERE
            (destination_type = 'page')
            AND (page_id = '" . escape($_GET['id']) . "')
        ORDER BY last_modified_timestamp DESC
        LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $short_link = mysqli_fetch_assoc($result);

    // if there is a short link, then prepare to output description in sub-navigation area
    // ($short_link is null when the page has no short link)
    if (!empty($short_link['name'])) {
        $output_subnav_short_link = ' | ' . lang('Short Link') . ': ' . h($short_link['name']);
    }
    
    $output_subnav_home = '';

    // Only set for the page types that have a next / skip page, but always printed below.
    $output_subnav_next_page = '';
    $output_subnav_skip_page = '';
    
    // if this is a home page, then prepare to output description in sub-navigation area
    if ($page_home == 'yes') {
        $output_subnav_home = '<span class="badge material-icons text-primary h2">home</span>';
    }
    
    $output_subnav_search = '';
    $output_subnav_search_keywords = '';
    
    // if search is enabled, then prepare to output description in sub-navigation area
    if ($page_search == 1) {
        $output_subnav_search = ' | ' . lang('Searchable');
        
        // if there are search keywords, then prepare to output description in sub-navigation area
        if ($page_search_keywords != '') {
            $output_subnav_search_keywords = ' | ' . lang('Keyword') . ': ' . h($page_search_keywords);
        }
    }
    
    // Check to see if the page has page type properties
    $check_for_page_type_properties = check_for_page_type_properties($page_type);
    // If the page has page type properties.
    if ($check_for_page_type_properties == true) {
        // Get the page type properties.
        $page_type_properties = get_page_type_properties($page_id, $page_type);
        
        // if there is a next page,
        // and the page type is not catalog detail,
        // or ordering is enabled,
        // then prepare to output next page
        if (
            (isset($page_type_properties['next_page_id']) == true)
            &&
            (
                ($page_type != 'catalog detail')
                || (($page_type_properties['allow_customer_to_add_product_to_order'] ?? '') == 1)
            )
        ) {
            // Get the next page name and output the next page.
            $next_page_name = get_page_name(($page_type_properties['next_page_id'] ?? ''));
            // Next page is blank then output none.
            if ($next_page_name == '') {
                $output_subnav_next_page = ' | ' . lang('Next Page') . ': ' . lang('None');
            // else output name
            } else {
                $output_subnav_next_page = ' | ' . lang('Next Page') . ': <a href="' . OUTPUT_PATH . $next_page_name . '">' . $next_page_name . '</a>';
            }
        } else if((isset($page_type_properties['add_button_next_page_id']) && (($page_type_properties['add_button_next_page_id'] ?? '') != 0))) {
            // Get the next page name and output the next page. 
            $next_page_name = get_page_name(($page_type_properties['add_button_next_page_id'] ?? ''));
            // Next page is blank then output none.
            if ($next_page_name == '') {
                $output_subnav_next_page = ' | ' . lang('Next Page') . ': ' . lang('None');
            // else output name
            } else {
                $output_subnav_next_page = ' | ' . lang('Next Page') . ': <a href="' . OUTPUT_PATH . $next_page_name . '">' . $next_page_name . '</a>';
            }
        } else {
            // If no next page set the variable to blank.
            $output_subnav_next_page = '';
        }
        
        if((isset($page_type_properties['skip_button_next_page_id']) && (($page_type_properties['skip_button_next_page_id'] ?? '') != 0))) {
            // Get the next page name and output the next page.
            $skip_page_name = get_page_name(($page_type_properties['skip_button_next_page_id'] ?? ''));
            $output_subnav_skip_page = ' | ' . lang('Skip Page') . ': <a href="' . OUTPUT_PATH . $skip_page_name . '">' . $skip_page_name . '</a>';
        } else {
            // If no next page set the variable to blank.
            $output_subnav_skip_page = '';
        }
    }
    
    // if user is above a user role, then prepare to output style and mobile style pick list, because user has access to select style
    if ($user['role'] < 3) {
        $output_style = '<select class="form-select" id="style" name="style">' . select_style($page_style) . '</select>';
        $output_mobile_style = '<select class="form-select" id="mobile_style_id" name="mobile_style_id">' . get_mobile_style_options($mobile_style_id) . '</select>';
        
    // else user has a user role, so prepare to just output style and mobile style name
    } else {
        // if there is a style set for this page, then prepare to just output style name
        if ($page_style != 0) {
            // get style name
            $query = "SELECT style_name FROM style WHERE style_id='" . escape($page_style) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $style_name = $row['style_name'];
            
            $output_style = h($style_name);
            
        // else there is not a style set for this page, so output default
        } else {
            $output_style = lang('Default') . ' (' . lang('inherit') . ')';
        }

        // if there is a mobile style set for this page, then prepare to just output mobile style name
        if ($mobile_style_id != 0) {
            // get mobile style name
            $query = "SELECT style_name FROM style WHERE style_id='" . escape($mobile_style_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $mobile_style_name = $row['style_name'];
            
            $output_mobile_style = h($mobile_style_name);
            
        // else there is not a mobile style set for this page, so output default
        } else {
            $output_mobile_style = lang('Default') . ' (' . lang('inherit') . ')';
        }
    }
    
    // if user is above a user role or page type is accessible by this user, then prepare to output page type area
    if (
        ($user['role'] < 3)
        || ($page_type == 'standard')
        || (($page_type == 'email a friend') && ($user['set_page_type_email_a_friend'] == TRUE))
        || (($page_type == 'folder view') && ($user['set_page_type_folder_view'] == TRUE))
        || (($page_type == 'photo gallery') && ($user['set_page_type_photo_gallery'] == TRUE))
        || (($page_type == 'catalog') && ($user['set_page_type_catalog'] == TRUE))
        || (($page_type == 'catalog detail') && ($user['set_page_type_catalog_detail'] == TRUE))
        || (($page_type == 'express order') && ($user['set_page_type_express_order'] == TRUE))
        || (($page_type == 'order form') && ($user['set_page_type_order_form'] == TRUE))
        || (($page_type == 'shopping cart') && ($user['set_page_type_shopping_cart'] == TRUE))
        || (($page_type == 'shipping address and arrival') && ($user['set_page_type_shipping_address_and_arrival'] == TRUE))
        || (($page_type == 'shipping method') && ($user['set_page_type_shipping_method'] == TRUE))
        || (($page_type == 'billing information') && ($user['set_page_type_billing_information'] == TRUE))
        || (($page_type == 'order preview') && ($user['set_page_type_order_preview'] == TRUE))
        || (($page_type == 'order receipt') && ($user['set_page_type_order_receipt'] == TRUE))
        || (($page_type == 'custom form') && ($user['set_page_type_custom_form'] == TRUE))
        || (($page_type == 'custom form confirmation') && ($user['set_page_type_custom_form_confirmation'] == TRUE))
        || (($page_type == 'form list view') && ($user['set_page_type_form_list_view'] == TRUE))
        || (($page_type == 'form item view') && ($user['set_page_type_form_item_view'] == TRUE))
        || (($page_type == 'form view directory') && ($user['set_page_type_form_view_directory'] == TRUE))
        || (($page_type == 'calendar view') && ($user['manage_calendars'] == TRUE) && ($user['set_page_type_calendar_view'] == TRUE))
        || (($page_type == 'calendar event view') && ($user['manage_calendars'] == TRUE) && ($user['set_page_type_calendar_event_view'] == TRUE))
    ) {
        // hide all page type properties until we determine which need to be displayed

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
        
        $output_edit_custom_form = '';
        $output_layout_buttons = '';
        $output_edit_form_list_view = '';
        $output_edit_form_item_view = '';
        $output_edit_custom_shipping_form = '';
        $output_edit_custom_billing_form = '';

        if (check_if_page_type_supports_layout($page_type)) {
            // If this page has a custom layout, and user is an admin or designer,
            // then output layout buttons in the button bar.
            if (
                ($layout_type == 'custom')
                && (USER_ROLE < 2)
            ) {
                if (check_if_page_type_requires_from_control_panel($page_type)) {
                    $query_string_from = '?from=control_panel';
                } else {
                    $query_string_from = '';
                }

                $output_layout_buttons =
                    '<a class="btn btn-link link-secondary py-0 mb-2 " href="page_designer.php?url=' . h(urlencode(PATH . encode_url_path($page_name) . $query_string_from)) . '&amp;type=layout&amp;id=' . $page_id . '" target="_top"><span class="material-icons me-1">code</span>' . lang('Edit Layout') . '</a>
                    <a class="btn btn-link link-secondary py-0 mb-2 " href="generate_layout.php?page_id=' . $page_id . '"><span class="material-icons me-1">plagiarism</span>' . lang('Generate Layout') . '</a>';
            }

            // Show the layout type row.
            $layout_type_row_style = '';
        }

        $activate_editors = false;

        // Only the branch matching this page's type fills its own properties below, but the form
        // further down reads every one of them, so they all have to start out as empty arrays.
        $email_a_friend_properties = array();
        $folder_view_properties = array();
        $photo_gallery_properties = array();
        $search_results_properties = array();
        $update_address_book_properties = array();
        $custom_form_properties = array();
        $custom_form_confirmation_properties = array();
        $form_list_view_properties = array();
        $form_item_view_properties = array();
        $form_view_directory_properties = array();
        $calendar_view_properties = array();
        $calendar_event_view_properties = array();
        $catalog_properties = array();
        $catalog_detail_properties = array();
        $express_order_properties = array();
        $order_form_properties = array();
        $shopping_cart_properties = array();
        $shipping_address_and_arrival_properties = array();
        $shipping_method_properties = array();
        $billing_information_properties = array();
        $order_preview_properties = array();
        $order_receipt_properties = array();
        $affiliate_sign_up_form_properties = array();

        // Same story for these: only the branch for this page's type sets them, but the form
        // below prints them for every page type.
        $custom_form_return_type_custom_form_checked = '';
        $custom_form_return_type_message_checked = '';
        $custom_form_return_type_page_checked = '';
        $calendar_view_calendar_check_boxes = '';
        $calendar_event_view_calendar_check_boxes = '';
        $output_button_bar = '';

        switch($page_type) {
            case 'email a friend':
                $email_a_friend_properties = get_page_type_properties($page_id, $page_type);
                
                $email_a_friend_submit_button_label_row_style = '';
                $email_a_friend_next_page_id_row_style = '';
                
                break;

            case 'folder view':
                $folder_view_properties = get_page_type_properties($page_id, $page_type);
                
                $folder_view_pages_row_style = '';
                $folder_view_files_row_style = '';
                
                break;
                
            case 'photo gallery':
                $photo_gallery_properties = get_page_type_properties($page_id, $page_type);
                
                $photo_gallery_number_of_columns_row_style = '';
                $photo_gallery_thumbnail_max_size_row_style = '';
                
                break;
                
            case 'search results':
                $search_results_properties = get_page_type_properties($page_id, $page_type);
                $search_results_search_folder_id_row_style = '';
                $search_results_search_catalog_items_row_style = '';
                
                break;
                
            case 'update address book':
                $update_address_book_properties = get_page_type_properties($page_id, $page_type);
                
                $update_address_book_address_type_row_style = '';

                break;
            
            case 'custom form':
                $custom_form_properties = get_page_type_properties($page_id, $page_type);
                
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
                if (($custom_form_properties['confirmation_type'] ?? '') == 'message') {
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
                
                // output edit custom form button
                $output_edit_custom_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Form') . '</a>';
                
                break;

            case 'custom form confirmation':
                $custom_form_confirmation_properties = get_page_type_properties($page_id, $page_type);
                
                $custom_form_confirmation_continue_button_label_row_style = '';
                $custom_form_confirmation_next_page_id_row_style = '';
                
                break;
                
            case 'form list view':
                $form_list_view_properties = get_page_type_properties($page_id, $page_type);
                
                $form_list_view_custom_form_page_id_row_style = '';
                $form_list_view_form_item_view_page_id_row_style = '';
                $form_list_view_viewer_filter_row_style = '';

                // Output edit form list view
                $output_edit_form_list_view = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_form_list_view.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">list</span>' . lang('Edit Form List View') . '</a>';
                
                break;
                
            case 'form item view':
                $form_item_view_properties = get_page_type_properties($page_id, $page_type);
                
                $form_item_view_custom_form_page_id_row_style = '';
                $form_item_view_submitter_security_row_style = '';
                $form_item_view_submitted_form_editable_by_registered_user_row_style = '';
                $form_item_view_submitted_form_editable_by_submitter_row_style = '';
                $form_item_view_hook_code_row_style = '';
                
                // Output edit form list view
                $output_edit_form_item_view = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_form_item_view.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">description</span>' . lang('Edit Form Item View') . '</a>';
                
                break;
                
            case 'form view directory':
                $form_view_directory_properties = get_page_type_properties($page_id, $page_type);
                
                $form_view_directory_form_list_views_row_style = '';
                $form_view_directory_summary_row_style = '';
                $form_view_directory_form_list_view_heading_row_style = '';
                $form_view_directory_subject_heading_row_style = '';
                $form_view_directory_number_of_submitted_forms_heading_row_style = '';
                
                break;
                
            case 'calendar view':
                $calendar_view_properties = get_page_type_properties($page_id, $page_type);
                
                $calendar_view_calendars_row_style = '';
                $calendar_view_default_view_row_style = '';
                $calendar_view_calendar_event_view_page_id_row_style = '';
                
                // if the default view is set to upcoming, then display number_of_upcoming_events field
                if (($calendar_view_properties['default_view'] ?? '') == 'upcoming') {
                    $calendar_view_number_of_upcoming_events_row_style = '';
                }
                
                break;
                
            case 'calendar event view':
                $calendar_event_view_properties = get_page_type_properties($page_id, $page_type);
                
                $calendar_event_view_calendars_row_style = '';
                $calendar_event_view_notes_row_style = '';
                $calendar_event_view_back_button_label_row_style = '';
                
                break;
                
            case 'catalog':
                $catalog_properties = get_page_type_properties($page_id, $page_type);
                
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
                $catalog_detail_properties = get_page_type_properties($page_id, $page_type);

                $catalog_detail_allow_customer_to_add_product_to_order_row_style = '';
                


                $catalog_detail_back_button_label_row_style = '';
                
                break;

            case 'express order':
                $express_order_properties = get_page_type_properties($page_id, $page_type);
                
                $express_order_shopping_cart_label_row_style = '';
                $express_order_quick_add_label_row_style = '';
                $express_order_quick_add_product_group_id_row_style = '';
                $express_order_product_description_type_row_style = '';

                $express_order_shipping_form_row_style = '';
                
                // If the shipping form is enabled, then output edit custom shipping form button
                if (($express_order_properties['shipping_form'] ?? '')) {
                    $output_edit_custom_shipping_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;form_type=shipping&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Shipping Form') . '</a>';
                }

                $express_order_special_offer_code_label_row_style = '';
                $express_order_special_offer_code_message_row_style = '';
                $express_order_custom_field_1_label_row_style = '';
                $express_order_custom_field_2_label_row_style = '';
                $express_order_po_number_row_style = '';

                $express_order_form_row_style = '';
                
                // If the form is enabled, then output edit custom billing form button and display fields.
                if (($express_order_properties['form'] ?? '') == '1') {
                    $output_edit_custom_billing_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;form_type=billing&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Billing Form') . '</a>';
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
                $order_form_properties = get_page_type_properties($page_id, $page_type);
                
                $order_form_product_group_id_row_style = '';
                $order_form_product_layout_row_1_style = '';
                $order_form_product_layout_row_2_style = '';
                $order_form_add_button_row_style = '';
                $order_form_skip_button_row_style = '';
                
                break;

            case 'shopping cart':
                $shopping_cart_properties = get_page_type_properties($page_id, $page_type);
                
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
                $shipping_address_and_arrival_properties = get_page_type_properties($page_id, $page_type);
                
                $shipping_address_and_arrival_address_type_row_style = '';
                
                $shipping_address_and_arrival_form_row_style = '';
                
                // if the form is enabled, then output edit custom shipping form button and display fields
                if (($shipping_address_and_arrival_properties['form'] ?? '') == '1') {
                    $output_edit_custom_shipping_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Shipping Form') . '</a>';
                    $shipping_address_and_arrival_form_name_row_style = '';
                    $shipping_address_and_arrival_form_label_column_width_row_style = '';
                }
                
                $shipping_address_and_arrival_submit_button_row_style = '';
                
                break;

            case 'shipping method':
                $shipping_method_properties = get_page_type_properties($page_id, $page_type);
                
                $shipping_method_product_description_type_row_style = '';
                $shipping_method_submit_button_row_style = '';
                
                break;
                
            case 'billing information':
                $billing_information_properties = get_page_type_properties($page_id, $page_type);
                
                $billing_information_custom_field_1_label_row_style = '';
                $billing_information_custom_field_2_label_row_style = '';
                $billing_information_po_number_row_style = '';

                $billing_information_form_row_style = '';
                
                // If the form is enabled, then output edit custom billing form button and display fields.
                if (($billing_information_properties['form'] ?? '') == '1') {
                    $output_edit_custom_billing_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Billing Form') . '</a>';

                    $billing_information_form_name_row_style = '';
                    $billing_information_form_label_column_width_row_style = '';
                }

                $billing_information_submit_button_label_row_style = '';
                $billing_information_next_page_id_row_style = '';
                
                break;

            case 'order preview':
                $order_preview_properties = get_page_type_properties($page_id, $page_type);
                
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
                $order_receipt_properties = get_page_type_properties($page_id, $page_type);
                
                $order_receipt_product_description_type_row_style = '';
                
                break;
                
            case 'affiliate sign up form':
                $affiliate_sign_up_form_properties = get_page_type_properties($page_id, $page_type);
                
                $affiliate_sign_up_form_terms_page_id_row_style = '';
                $affiliate_sign_up_form_submit_button_label_row_style = '';
                $affiliate_sign_up_form_next_page_id_row_style = '';
                
                break;
        }
        
        // if there is a edit button to display output the button bar.
        if (
            ($output_edit_custom_form != '')
            || ($output_edit_form_list_view != '')
            || ($output_edit_form_item_view != '')
            || ($output_edit_custom_shipping_form != '')
            || ($output_edit_custom_billing_form != '')
            || ($output_layout_buttons != '')
        ) {
            $output_button_bar = '
            <nav id="button_bar" class="navigation " aria-label="Button Bar">
                <div class=" btn-group btn-group-sm flex-wrap">' .
                        $output_edit_custom_form .
                        $output_edit_form_list_view .
                        $output_edit_form_item_view .
                        $output_edit_custom_shipping_form .
                        $output_edit_custom_billing_form .
                        $output_layout_buttons . '
                    </div>
                </nav>';
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

        // If confirmation type is set to message, then select the message radio button.
        if (($custom_form_properties['confirmation_type'] ?? '') == 'message') {
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
            $form_view_directory_form_list_view_heading = 'Forum';
            
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
            $form_view_directory_number_of_submitted_forms_heading = 'Forms';
            
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
        
        // if submit button label is empty, then prepare default value
        if (!($affiliate_sign_up_form_properties['submit_button_label'] ?? '')) {
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
                                        <select name="search_results_product_group_id" id="search_results_product_group_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Product Group')) )) . '-</option>' . get_product_group_options(($search_results_properties['product_group_id'] ?? ''),  $parent_product_group_id = 0, $excluded_product_group_id = 0, $level = 0, $product_groups = array(), $include_select_product_groups = FALSE) . '</select>
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
                    <select name="catalog_product_group_id" id="catalog_product_group_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options(($catalog_properties['product_group_id'] ?? ''), $parent_product_group_id = 0, $excluded_product_group_id = 0, $level = 0, $product_groups = array(), $include_select_product_groups = FALSE) . '</select>
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
                    <input value="' . ($catalog_properties['number_of_featured_items'] ?? '') . '" type="text" name="catalog_number_of_featured_items" id="catalog_number_of_featured_items" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_number_of_new_items_row" style="' . $catalog_number_of_new_items_row_style . '">
                    <label for="catalog_number_of_new_items" class="form-label">' . lang('Number of New Items') . '</label>
                    <input value="' . ($catalog_properties['number_of_new_items'] ?? '') . '" type="text" name="catalog_number_of_new_items" id="catalog_number_of_new_items" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_number_of_columns_row" style="' . $catalog_number_of_columns_row_style . '">
                    <label for="catalog_number_of_columns" class="form-label">' . lang('Number of Columns') . '</label>
                    <input value="' . $catalog_number_of_columns . '" type="text" name="catalog_number_of_columns" id="catalog_number_of_columns" maxlength="2" class="form-control text-start" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                </div>
                <div class="col-12">
                    <div class="row">
                        <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_image_width_row" style="' . $catalog_image_width_row_style . '">
                            <label for="catalog_image_width" class="form-label">' . lang('Image Width') . '</label>
                            <div class="input-group my-2">
                                <input value="' . $catalog_image_width . '" type="text" name="catalog_image_width" id="catalog_image_width" maxlength="4" class="form-control text-end" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                                <label class="input-group-text" for="catalog_image_width">' . lang('pixels') . '</label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_image_height_row" style="' . $catalog_image_height_row_style . '">
                            <label for="catalog_image_height" class="form-label">' . lang('Image Height') . '</label>
                            <div class="input-group my-2">
                                <input value="' . $catalog_image_height . '" type="text" name="catalog_image_height" id="catalog_image_height" maxlength="4" class="form-control text-end" inputmode="numeric" data-inputmask-alias="decimal"  data-inputmask-placeholder="0" />
                                <label class="input-group-text" for="catalog_image_height">' . lang('pixels') . '</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2" id="catalog_back_button_label_row" style="' . $catalog_back_button_label_row_style . '">
                    <label for="catalog_back_button_label" class="form-label">' . lang('Back Button Label') . '</label>
                    <input value="' . ($catalog_properties['back_button_label'] ?? '') . '" type="text" name="catalog_back_button_label" id="catalog_back_button_label" maxlength="50" class="form-control" />
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
                                    <input value="' . ($catalog_detail_properties['add_button_label'] ?? '') . '" type="text" name="catalog_detail_add_button_label" id="catalog_detail_add_button_label" maxlength="50" class="form-control" />
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
                    <input value="' . ($catalog_detail_properties['back_button_label'] ?? '') . '" type="text" name="catalog_detail_back_button_label" id="catalog_detail_back_button_label" maxlength="50" class="form-control" />
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_shopping_cart_label_row" style="' . $express_order_shopping_cart_label_row_style . '">
                    <label for="express_order_shopping_cart_label" class="form-label">' . lang('Shopping Cart Label') . '</label>
                    <input value="' . ($express_order_properties['shopping_cart_label'] ?? '') . '" type="text" name="express_order_shopping_cart_label" id="express_order_shopping_cart_label" maxlength="50" class="form-control" />
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_quick_add_label_row" style="' . $express_order_quick_add_label_row_style . '">
                    <label for="express_order_quick_add_label" class="form-label">' . lang('Quick Add Label') . '</label>
                    <input value="' . ($express_order_properties['quick_add_label'] ?? '') . '" type="text" name="express_order_quick_add_label" id="express_order_quick_add_label" maxlength="255" class="form-control" />
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_quick_add_product_group_id_row" style="' . $express_order_quick_add_product_group_id_row_style . '">
                    <label for="express_order_quick_add_product_group_id" class="form-label">' . lang('Quick Add Product Group') . '</label>
                    <select class="form-select" name="express_order_quick_add_product_group_id" id="express_order_quick_add_product_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options(($express_order_properties['quick_add_product_group_id'] ?? '')) . '</select>
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
                    <input value="' . ($express_order_properties['special_offer_code_label'] ?? '') . '" type="text" name="express_order_special_offer_code_label" id="express_order_special_offer_code_label" maxlength="50" class="form-control" />
                </div>
                <div class="col-12 col-sm-6 col-lg-8 my-2" id="express_order_special_offer_code_message_row" style="' . $express_order_special_offer_code_message_row_style . '">
                    <label for="express_order_special_offer_code_message" class="form-label">' . lang('Special Offer Code Message') . '</label>
                    <input value="' . ($express_order_properties['special_offer_code_message'] ?? '') . '" type="text" name="express_order_special_offer_code_message" id="express_order_special_offer_code_message" maxlength="255" class="form-control" />
                </div>
                <div class="col-12 col-sm-6 my-2" id="express_order_custom_field_1_label_row" style="' . $express_order_custom_field_1_label_row_style . '">
                    <div class="border-1 border p-2 my-2 rounded">
                        <label for="express_order_custom_field_1_label" class="form-label">' . lang('Custom Field #1 Label') . '</label>
                        <input value="' . ($express_order_properties['custom_field_1_label'] ?? '') . '" type="text" name="express_order_custom_field_1_label" id="express_order_custom_field_1_label" maxlength="50" class="form-control" />
                        <div class="form-check form-switch ms-1 mt-2">
                            <input class="form-check-input" type="checkbox" name="express_order_custom_field_1_required" id="express_order_custom_field_1_required" value="1"' . $express_order_custom_field_1_required_checked . ' />
                            <label class="form-check-label" for="express_order_custom_field_1_required">' . lang('Required') . '</label>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 my-2" id="express_order_custom_field_2_label_row" style="' . $express_order_custom_field_2_label_row_style . '">
                    <div class="border-1 border p-2 my-2 rounded">
                        <label for="express_order_custom_field_2_label" class="form-label">' . lang('Custom Field #2 Label') . '</label>
                        <input value="' . ($express_order_properties['custom_field_2_label'] ?? '') . '" type="text" name="express_order_custom_field_2_label" id="express_order_custom_field_2_label" maxlength="255" class="form-control" />
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
                    <input value="' . ($express_order_properties['update_button_label'] ?? '') . '" type="text" id="express_order_update_button_label" name="express_order_update_button_label" class="form-control" maxlength="50" >
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2" id="express_order_purchase_now_button_label_row" style="' . $express_order_purchase_now_button_label_row_style . '">
                    <label class="form-label" for="express_order_purchase_now_button_label">'. lang('Purchase Now Button Label') . '</label>
                    <input value="' . ($express_order_properties['purchase_now_button_label'] ?? '') . '" type="text" id="express_order_purchase_now_button_label" name="express_order_purchase_now_button_label" class="form-control" maxlength="50" >
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
                    <select class="form-select" id="order_form_product_group_id" name="order_form_product_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('product group')) )) . '-</option>' . get_product_group_options(($order_form_properties['product_group_id'] ?? '')) . '</select>
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
                            <input value="' . ($order_form_properties['add_button_label'] ?? '') . '" type="text" class="form-control" id="order_form_add_button_label" name="order_form_add_button_label" maxlength="50"/>
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
                            <input value="' . ($order_form_properties['skip_button_label'] ?? '') . '" type="text" class="form-control" id="order_form_skip_button_label" name="order_form_skip_button_label" maxlength="50"/>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 my-2">
                            <label class="form-label" for="order_form_skip_button_next_page_id">' . lang('Next Page') . '</label>
                            <select class="form-select" id="order_form_skip_button_next_page_id" name="order_form_skip_button_next_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('page')) )) . '-</option>' . select_page(($order_form_properties['skip_button_next_page_id'] ?? '')) . '</select>
                        </div> 
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_shopping_cart_label_row" style="' . $shopping_cart_shopping_cart_label_row_style . '">
                    <label class="form-label" for="shopping_cart_shopping_cart_label">' . lang('Shopping Cart Label') . '</label>
                    <input value="' . ($shopping_cart_properties['shopping_cart_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_shopping_cart_label" name="shopping_cart_shopping_cart_label" maxlength="50"/>
                </div>
                <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_quick_add_label_row" style="' . $shopping_cart_quick_add_label_row_style . '">
                    <label class="form-label" for="shopping_cart_quick_add_label">' . lang('Quick Add Label') . '</label>
                    <input value="' . ($shopping_cart_properties['quick_add_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_quick_add_label" name="shopping_cart_quick_add_label" maxlength="255"/>
                </div>
                <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_quick_add_product_group_id_row" style="' . $shopping_cart_quick_add_product_group_id_row_style . '">
                    <label class="form-label" for="shopping_cart_quick_add_product_group_id">' . lang('Quick Add Product Group') . '</label>
                    <select class="form-select" id="shopping_cart_quick_add_product_group_id" name="shopping_cart_quick_add_product_group_id"><option value="">-' . lang('None') . '-</option>' . get_product_group_options(($shopping_cart_properties['quick_add_product_group_id'] ?? '')) . '</select>
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
                    <input value="' . ($shopping_cart_properties['special_offer_code_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_special_offer_code_label" name="shopping_cart_special_offer_code_label" maxlength="50"/>
                </div>
                <div class="col-12 col-md-6 col-lg-8 my-2" id="shopping_cart_special_offer_code_message_row" style="' . $shopping_cart_special_offer_code_message_row_style . '">
                    <label class="form-label" for="shopping_cart_special_offer_code_message">' . lang('Special Offer Code Message') . '</label>
                    <input value="' . ($shopping_cart_properties['special_offer_code_message'] ?? '') . '" type="text" class="form-control" id="shopping_cart_special_offer_code_message" name="shopping_cart_special_offer_code_message" maxlength="255"/>
                </div>
                <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_update_button_label_row" style="' . $shopping_cart_update_button_label_row_style . '">
                    <label class="form-label" for="shopping_cart_update_button_label">' . lang('Update Button Label') . '</label>
                    <input value="' . ($shopping_cart_properties['update_button_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_update_button_label" name="shopping_cart_update_button_label" maxlength="50"/>
                </div>
                <div class="col-12 col-md-6 col-lg-4 my-2" id="shopping_cart_checkout_button_label_row" style="' . $shopping_cart_checkout_button_label_row_style . '">
                    <label class="form-label" for="shopping_cart_checkout_button_label">' . lang('Checkout Button Label') . '</label>
                    <input value="' . ($shopping_cart_properties['checkout_button_label'] ?? '') . '" type="text" class="form-control" id="shopping_cart_checkout_button_label" name="shopping_cart_checkout_button_label" maxlength="50"/>
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
                            <input value="' . ($shipping_address_and_arrival_properties['submit_button_label'] ?? '') . '" type="text" class="form-control" id="shipping_address_and_arrival_submit_button_label" name="shipping_address_and_arrival_submit_button_label" maxlength="50"/>
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
                            <input value="' . ($shipping_method_properties['submit_button_label'] ?? '') . '" type="text" class="form-control" id="shipping_method_submit_button_label" name="shipping_method_submit_button_label" maxlength="50"/>
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
                        <input value="' . ($billing_information_properties['custom_field_1_label'] ?? '') . '" type="text" name="billing_information_custom_field_1_label" id="billing_information_custom_field_1_label" maxlength="255" class="form-control" />
                        <div class="form-check form-switch ms-1 mt-2">
                            <input class="form-check-input" type="checkbox" name="billing_information_custom_field_1_required" id="billing_information_custom_field_1_required" value="1"' . $billing_information_custom_field_1_required_checked . ' />
                            <label class="form-check-label" for="billing_information_custom_field_1_required">' . lang('Required') . '</label>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 my-2" id="billing_information_custom_field_2_label_row" style="' . $billing_information_custom_field_2_label_row_style . '">
                    <div class="border-1 border p-2 my-2 rounded">
                        <label for="billing_information_custom_field_2_label" class="form-label">' . lang('Custom Field #2 Label') . '</label>
                        <input value="' . ($billing_information_properties['custom_field_2_label'] ?? '') . '" type="text" name="billing_information_custom_field_2_label" id="billing_information_custom_field_2_label" maxlength="255" class="form-control" />
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
                    <input value="' . ($billing_information_properties['submit_button_label'] ?? '') . '" type="text" class="form-control" id="billing_information_submit_button_label" name="billing_information_submit_button_label" maxlength="50"/>
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
                    <input value="' . ($order_preview_properties['submit_button_label'] ?? '') . '" type="text" id="order_preview_submit_button_label" name="order_preview_submit_button_label" class="form-control" maxlength="50" >
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
                                        <input value="' . ($custom_form_properties['quiz_pass_percentage'] ?? '') . '" type="text" name="custom_form_quiz_pass_percentage" id="custom_form_quiz_pass_percentage" maxlength="3" class="form-control" maxlength="3" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;"/>
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
                    <input value="' . ($custom_form_properties['submit_button_label'] ?? '') . '" type="text" name="custom_form_submit_button_label" id="custom_form_submit_button_label" maxlength="50" class="form-control" />
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
                                    <input value="' . ($custom_form_properties['submitter_email_from_email_address'] ?? '') . '" type="text" class="form-control text-end" id="custom_form_submitter_email_from_email_address" name="custom_form_submitter_email_from_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
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
                                    <input value="' . ($custom_form_properties['administrator_email_to_email_address'] ?? '') . '" type="text" class="form-control text-end" id="custom_form_administrator_email_to_email_address" name="custom_form_administrator_email_to_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                                </div>
                                <div class="col-12 col-sm-6 col-xl-4 my-1">
                                    <label class="form-label" for="custom_form_administrator_email_bcc_email_address">' . lang('BCC E-mail Address') . '</label>
                                    <input value="' . ($custom_form_properties['administrator_email_bcc_email_address'] ?? '') . '" type="text" class="form-control text-end" id="custom_form_administrator_email_bcc_email_address" name="custom_form_administrator_email_bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
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

            // If commerce is enabled, then output grant offer rows.
            if (ECOMMERCE) {
                $output_forms_page_type_properties .=
                    '<div class="col-12 my-2" id="custom_form_offer_row" style="' . $custom_form_private_row_style . '">
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
                    <input value="' . ($custom_form_confirmation_properties['continue_button_label'] ?? '') . '" type="text" name="custom_form_confirmation_continue_button_label" id="custom_form_confirmation_continue_button_label" maxlength="50" class="form-control" />
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
                    <input value="' . ($calendar_event_view_properties['back_button_label'] ?? '') . '" name="calendar_event_view_back_button_label" id="calendar_event_view_back_button_label" type="text" maxlength="50" class="form-control" />
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
                    <input value="' . ($affiliate_sign_up_form_properties['submit_button_label'] ?? '') . '" type="text" name="affiliate_sign_up_form_submit_button_label" id="affiliate_sign_up_form_submit_button_label" placeholder="' . lang('Sign Up') . '"  class="form-control" maxlength="50"/>
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
                                <input value="' . ($email_a_friend_properties['submit_button_label'] ?? '') . '" type="text" name="email_a_friend_submit_button_label" id="email_a_friend_submit_button_label" placeholder="' . lang('Submit') . '"  class="form-control" maxlength="50"/>
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
    }
    
    // find search default
    $search_checked = '';

    if ($page_search == 1) {
        $search_checked = ' checked="checked"';
    }
    
    $output_home_page_rows = '';
    
    // if user is above a user role, then prepare to output home page rows, because user is allowed to set home page
    if ($user['role'] < 3) {
        $home_checked = '';
        
        // if this page is a home page, then prepare to check home page check box
        if ($page_home == 'yes') {
            $home_checked = ' checked="checked"';
        }
        
        $output_home_page_rows =
            '<div class="col-12 my-3">
                <div class="form-check form-switch">
                    <input value="yes"' . $home_checked . ' id="home" name="home" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="home">' . lang('Set As Homepage') . '</label>
                </div>
            </div>';
    }
    
    $sitemap_row_style = 'display: none';
    
    // if the selected page type is a valid page type for the sitemap, then show sitemap row
    if (
        ($page_type == 'standard')
        || ($page_type == 'folder view')
        || ($page_type == 'photo gallery')
        || ($page_type == 'custom form')
        || ($page_type == 'form list view')
        || ($page_type == 'form item view')
        || ($page_type == 'form view directory')
        || ($page_type == 'calendar view')
        || ($page_type == 'calendar event view')
        || ($page_type == 'catalog')
        || ($page_type == 'catalog detail')
        || ($page_type == 'express order')
        || ($page_type == 'order form')
        || ($page_type == 'shopping cart')
        || ($page_type == 'search results')
    ) {
        $sitemap_row_style = '';
    }
    
    // if sitemap is enabled, then check the checkbox
    $sitemap_checked = '';

    if ($sitemap == '1') {
        $sitemap_checked = ' checked="checked"';
    }

    // Search engine indexing. The block is only built where the columns behind
    // it exist: a switch that saves nothing is worse than no switch at all.
    $output_noindex_rows = '';

    if (pg_page_noindex_ready() == TRUE) {
        $noindex_checked = '';
        $nofollow_checked = '';

        // nofollow qualifies the noindex directive and is meaningless without
        // it, so it starts out unavailable and the master switch opens it.
        $nofollow_disabled = ' disabled="disabled"';

        if ($noindex == '1') {
            $noindex_checked = ' checked="checked"';
            $nofollow_disabled = '';

            if ($nofollow == '1') {
                $nofollow_checked = ' checked="checked"';
            }
        }

        $output_noindex_rows =
            '<div class="col-12 my-3" id="noindex_row">
                <div class="form-check form-switch">
                    <input value="1"' . $noindex_checked . ' id="noindex" name="noindex" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="noindex">' . lang('Close to Search Engines (noindex)') . '</label>
                </div>
                <div class="form-text">' . lang('The page is served with a noindex robots tag, is blocked in robots.txt and is left out of the site map.') . '</div>
                <div class="form-check form-switch mt-3 ms-4">
                    <input value="1"' . $nofollow_checked . $nofollow_disabled . ' id="nofollow" name="nofollow" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="nofollow">' . lang('Do Not Follow Links on This Page (nofollow)') . '</label>
                </div>
                <div class="form-text ms-4">' . lang('Leave this off so that search engines keep discovering the items this page links to. Turn it on for a widget page whose links already appear on a page that is indexed.') . '</div>
                <div class="form-text text-warning mt-2"><i class="bi bi-exclamation-triangle me-1"></i>' . lang('A page blocked in robots.txt is not crawled, so the noindex tag on it is never read. Use this before a page reaches the results; a page that is already listed can take a while to drop out.') . '</div>
            </div>';
    }
    
    $comments_checked = '';
    $comments_allow_new_comments_checked = '';
    $comments_automatic_publish_checked = '';
    $comments_allow_user_to_select_name_checked = '';
    $comments_require_login_to_comment_checked = '';
    $comments_allow_file_attachments_checked = '';
    $comments_show_submitted_date_and_time_checked = '';
    $comments_administrator_email_row_style = 'display: none';
    $comments_administrator_email_conditional_administrators_checked = '';
    $comments_administrator_email_conditional_administrators_row_style = 'display: none';
    $comments_submitter_email_row_style = 'display: none';
    $comments_watcher_email_row_style = 'display: none';
    $comments_watchers_managed_by_submitter_checked = '';
    $comments_watchers_managed_by_submitter_row_style = 'display: none';
    
    // if comments are on then prepare the fields to be outputted
    if ($comments == '1') {
        // check the comments checkbox
        $comments_checked = ' checked="checked"';
        
        $comments_administrator_email_row_style = '';
        
        // if the page type is form item view then display the submitter specific rows
        if ($page_type == 'form item view') {
            $comments_administrator_email_conditional_administrators_row_style = '';
            $comments_submitter_email_row_style = '';
        }

        $comments_watcher_email_row_style = '';

        // If the page type is form item view then display the watches managed by submitter field
        if ($page_type == 'form item view') {
            $comments_watchers_managed_by_submitter_row_style = '';
        }
    }
   
    $comments_rating_checked = '';

    if ($comments_rating == '1') {
        // check the comments rating checkbox
        $comments_rating_checked = ' checked="checked"';
    }
    



    if ($comments_label == '') {
        $comments_label = 'Comment';
    }

    // if allow new comments is on or if comments are disabled, then check the checkbox
    if (($comments_allow_new_comments == '1') || ($comments == '0')) {
        $comments_allow_new_comments_checked = ' checked="checked"';
    }
    
    // if comments automatic publish is on or if comments are disabled, then check the checkbox
    if (($comments_automatic_publish == '1') || ($comments == '0')) {
        $comments_automatic_publish_checked = ' checked="checked"';
    }
    
    // if comments allow user to select name is on or if comments are disabled, then check the checkbox
    if (($comments_allow_user_to_select_name == '1') || ($comments == '0')) {
        $comments_allow_user_to_select_name_checked = ' checked="checked"';
    }
    
    // if comments require login to comment is on and comments are enabled, then check the checkbox
    if (($comments_require_login_to_comment == '1') && ($comments == '1')) {
        $comments_require_login_to_comment_checked = ' checked="checked"';
    }
    
    // if comments allow file attachments is on and comments are enabled, then check the checkbox
    if (($comments_allow_file_attachments == '1') && ($comments == '1')) {
        $comments_allow_file_attachments_checked = ' checked="checked"';
    }
    
    // if show submitted date and time is on or if comments are disabled, then check the checkbox
    if (($comments_show_submitted_date_and_time == '1') || ($comments == '0')) {
        $comments_show_submitted_date_and_time_checked = ' checked="checked"';
    }
    
    // if conditional administrators is enabled, then check the checkbox
    if ($comments_administrator_email_conditional_administrators == '1') {
        $comments_administrator_email_conditional_administrators_checked = ' checked="checked"';
    }

    // If watchers managed by submitter is enabled, then check the checkbox.
    if ($comments_watchers_managed_by_submitter == '1') {
        $comments_watchers_managed_by_submitter_checked = ' checked="checked"';
    }
    
    $output_delete_button = '';

    // if the user is at least a manager or has access to delete pages, then output the delete button
    if (($user['role'] < '3') || ($user['delete_pages'] == TRUE)) {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('page')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }
    
    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Page'),
            'extra classes'=>'page',
            'icon'=>'page',
            'heading'=>lang('Edit Page'),
            'cancel'=>array('enable' => 'true', 'url' => 'view_pages.php'),
            'breadcrumb' => array(
                array('label' => lang('Pages'), 'url' => 'view_pages.php'),
                array('label' => $page_name),
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
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
' . $output_subnav_home . '
                        <h2 class="d-inline-block text-break header-content-for-add-page position-relative" data-bs-content="' . lang('View and update the page, move it to another folder, or change its built-in features.') . '" title="' . lang('Edit Page Properties') . '">[' . h($page_name) . ']</h2>
                        <p>' . lang('Access') . ': ' . h(get_access_control_type_name(get_access_control_type($page_folder))) . $output_subnav_page_type . $output_subnav_short_link . $output_subnav_search . $output_subnav_search_keywords . $output_subnav_next_page . $output_subnav_skip_page . '</p>
                        ' . $output_button_bar . '
                    </div>
                </div>
                <form name="form" action="edit_page.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    <div class="row">
                        ' . $output_page_type_selector . '
                        <div class="col-12 col-md" style="min-width:0">
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
                                                        <input name="name" id="name" type="text" value="' . h($page_name) . '" placeholder="' . lang('Page Name') . '" maxlength="100" class="form-control add-header-content-updater" required="required" />
                                                    </div>
                                                </div>
                                                ' . $output_home_page_rows . '
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
                                ' . $output_page_type_properties . '
                                <div class="col-12 col-xl-6 mb-5">
                                    <div class="card my-4 h-100">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Search Engine Optimization') . '<span class="float-end">' . pg_seo_render_badge($seo_row) . '</span>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 mt-1 mb-2">
                                                    <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                                    <input type="text" value="' . h($page_title) . '" name="title" id="title" class="form-control" maxlength="255"/>
                                                    <div id="seo_c_title"></div>
                                                </div>
                                                <div class="col-12 mt-1 mb-2">
                                                    <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                                    <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255" >' . h($page_meta_description) . '</textarea>
                                                    <div id="seo_c_meta_description"></div>
                                                </div>
                                                <div class="col-12 my-3" id="sitemap_row" style="' . $sitemap_row_style . '">
                                                    <div class="form-check form-switch">
                                                        <input value="1"' . $sitemap_checked . ' id="sitemap" name="sitemap" class="form-check-input" type="checkbox" />
                                                        <label class="form-check-label" for="sitemap">' . lang('Include in Site Map') . '</label>
                                                    </div>
                                                </div>
                                                ' . $output_noindex_rows . '
                                                <div class="col-12">
                                                    ' . pg_seo_render_checklist($seo_row, 'page', $page_id) . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-xl-6 mb-5">
                                    <div class="card my-4 h-100">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Site Search') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 my-3">
                                                    <div class="form-check form-switch">
                                                        <input value="1"' . $search_checked . ' id="search" name="search" class="form-check-input collapse-switcher" type="checkbox"  data-bs-target="#show_or_hide_search"/>
                                                        <label class="form-check-label" for="search">' . lang('Include in Site Search') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_search">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12 mt-3 mb-2">
                                                                    <label for="keywords" class="form-label">' . lang('Promote on Keyword') . '</label>
                                                                    <input value="' . h($page_search_keywords) . '" type="text" name="search_keywords" id="search_keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '"/>
                                                                    <script>
                                                                        if(document.body.contains(document.querySelector("input#search_keywords"))){
                                                                            tagin( document.querySelector("#search_keywords") );
                                                                        }
                                                                    </script>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Comments Feature') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 my-2">
                                                    <div class="form-check form-switch">
                                                        <input value="1"' . $comments_checked . ' id="comments" name="comments" class="form-check-input collapse-switcher"  type="checkbox" role="switch" onclick="show_or_hide_comments();" data-bs-target="#show_or_hide_comments" />
                                                        <label class="form-check-label" for="comments">' . lang('Enable Comments') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_comments">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                                    <label for="comments_label" class="form-label">' . lang('Comment Label') . '</label>
                                                                    <input value="' . h($comments_label) . '" type="text" name="comments_label" id="comments_label" class="form-control" maxlength="100"/>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-8 my-2">
                                                                    <label for="comments_message" class="form-label">' . lang('Add Comment Message') . '</label>
                                                                    <input value="' . h($comments_message) . '" type="text" name="comments_message" id="comments_message" class="form-control" maxlength="255"/>
                                                                </div>
                                                                <div class="col-12 col-md-12 offset-lg-4 col-lg-8 my-2">
                                                                    <label for="comments_disallow_new_comment_message" class="form-label">' . lang('Do Not Allow New Comments Message') . '</label>
                                                                    <input value="' . h($comments_disallow_new_comment_message) . '" type="text" name="comments_disallow_new_comment_message" id="comments_disallow_new_comment_message" class="form-control" maxlength="255"/>
                                                                </div>
                                                                <div class="col-12 my-1 mt-3">
                                                                    <div class="form-check form-switch">
                                                                        <input type="checkbox" id="comments_allow_new_comments" name="comments_allow_new_comments" class="form-check-input" role="switch" value="1"' . $comments_allow_new_comments_checked . ' />
                                                                        <label class="form-check-label" for="comments_allow_new_comments">' . lang('Allow New Comments') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 my-1">
                                                                    <div class="form-check form-switch">
                                                                        <input type="checkbox" id="comments_automatic_publish" name="comments_automatic_publish" class="form-check-input" role="switch" value="1"' . $comments_automatic_publish_checked . ' />
                                                                        <label class="form-check-label" for="comments_automatic_publish">' . lang('Automatically Publish Comments') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 my-1">
                                                                    <div class="form-check form-switch">
                                                                        <input type="checkbox" id="comments_rating" name="comments_rating" class="form-check-input" role="switch" value="1"' . $comments_rating_checked . ' />
                                                                        <label class="form-check-label" for="comments_rating">' . lang('Enable Rating') . '</label>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="col-12 my-1">
                                                                    <div class="form-check form-switch">
                                                                        <input type="checkbox" id="comments_allow_user_to_select_name" name="comments_allow_user_to_select_name" class="form-check-input" role="switch" value="1"' . $comments_allow_user_to_select_name_checked . ' />
                                                                        <label class="form-check-label" for="comments_allow_user_to_select_name">' . lang('Allow User to Select Name') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 my-1">
                                                                    <div class="form-check form-switch">
                                                                        <input type="checkbox" id="comments_require_login_to_comment" name="comments_require_login_to_comment" class="form-check-input" role="switch" value="1"' . $comments_require_login_to_comment_checked . ' />
                                                                        <label class="form-check-label" for="comments_require_login_to_comment">' . lang('Require Login to Comment') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 my-1">
                                                                    <div class="form-check form-switch">
                                                                        <input type="checkbox" id="comments_allow_file_attachments" name="comments_allow_file_attachments" class="form-check-input" role="switch" value="1"' . $comments_allow_file_attachments_checked . ' />
                                                                        <label class="form-check-label" for="comments_allow_file_attachments">' . lang('Allow File Attachments') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 my-1">
                                                                    <div class="form-check form-switch">
                                                                        <input type="checkbox" id="comments_show_submitted_date_and_time" name="comments_show_submitted_date_and_time" class="form-check-input" role="switch" value="1"' . $comments_show_submitted_date_and_time_checked . ' />
                                                                        <label class="form-check-label" for="comments_show_submitted_date_and_time">' . lang('Show Submitted Date & Time') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 my-1 mb-3" id="comments_watchers_managed_by_submitter_row" style="' . $comments_watchers_managed_by_submitter_row_style . '">
                                                                    <div class="form-check form-switch">
                                                                        <input type="checkbox" id="comments_watchers_managed_by_submitter" name="comments_watchers_managed_by_submitter" class="form-check-input" role="switch" value="1"' . $comments_watchers_managed_by_submitter_checked . ' />
                                                                        <label class="form-check-label" for="comments_watchers_managed_by_submitter">' . lang('Allow submitter to manage watchers') . '</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 my-2 mt-4" id="comments_administrator_email_row" style="' . $comments_administrator_email_row_style . '">
                                                                    <h5>' . lang('E-mail moderator when a comment is added') . '</h5>
                                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100 show" id="fake_popover_1000000000">
                                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                        <div class="popover-body ">
                                                                            <div class="row">
                                                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                                                    <label for="comments_administrator_email_to_email_address" class="form-label">' . lang('To E-mail Address') . '</label>
                                                                                    <input value="' . h($comments_administrator_email_to_email_address) . '" type="text" class="form-control text-end" id="comments_administrator_email_to_email_address" name="comments_administrator_email_to_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                                                                                </div>
                                                                                <div class="col-12 col-md-6 col-lg-8 my-2">
                                                                                    <label for="comments_administrator_email_subject" class="form-label">' . lang('Subject') . '</label>
                                                                                    <input value="' . h($comments_administrator_email_subject) . '" type="text" name="comments_administrator_email_subject" id="comments_administrator_email_subject" class="form-control" maxlength="255"/>
                                                                                </div> 
                                                                                <div class="col-12 my-3" id="comments_administrator_email_conditional_administrators_row" style="' . $comments_administrator_email_conditional_administrators_row_style . '">
                                                                                    <div class="form-check form-switch">
                                                                                        <input type="checkbox" id="comments_administrator_email_conditional_administrators" name="comments_administrator_email_conditional_administrators" class="form-check-input" role="switch" value="1"' . $comments_administrator_email_conditional_administrators_checked . ' />
                                                                                        <label class="form-check-label" for="comments_administrator_email_conditional_administrators">' . lang('Also send e-mail to custom form conditional administrators') . '</label>
                                                                                    </div>
                                                                                </div>

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 my-2 mt-4" id="comments_submitter_email_row" style="' . $comments_submitter_email_row_style . '">
                                                                    <h5>' . lang('E-mail custom form submitter when a comment is published') . '</h5>
                                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100 show" id="fake_popover_1000000001">
                                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                        <div class="popover-body ">
                                                                            <div class="row">
                                                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                                                    <label for="comments_submitter_email_page_id" class="form-label">' . lang('Page') . '</label>
                                                                                    <select name="comments_submitter_email_page_id" id="comments_submitter_email_page_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page($comments_submitter_email_page_id) . '</select>
                                                                                </div>
                                                                                <div class="col-12 col-md-6 col-lg-8 my-2">
                                                                                    <label for="comments_submitter_email_subject" class="form-label">' . lang('Subject') . '</label>
                                                                                    <input value="' . h($comments_submitter_email_subject) . '" type="text" name="comments_submitter_email_subject" id="comments_submitter_email_subject" class="form-control" maxlength="255"/>
                                                                                </div> 
                                                                            </div> 
                                                                        </div> 
                                                                    </div> 
                                                                </div> 
                                                                <div class="col-12 my-2 mt-4" id="comments_watcher_email_row" style="' . $comments_watcher_email_row_style . '">
                                                                    <h5>' . lang('E-mail watchers when a comment is published') . '</h5>
                                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100 show" id="fake_popover_1000000002">
                                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                        <div class="popover-body ">
                                                                            <div class="row">
                                                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                                                    <label for="comments_watcher_email_page_id" class="form-label">' . lang('Page') . '</label>
                                                                                    <select name="comments_watcher_email_page_id" id="comments_watcher_email_page_id" class="form-select"  ><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page($comments_watcher_email_page_id) . '</select>
                                                                                </div>
                                                                                <div class="col-12 col-md-6 col-lg-8 my-2">
                                                                                    <label for="comments_watcher_email_subject" class="form-label">' . lang('Subject') . '</label>
                                                                                    <input value="' . h($comments_watcher_email_subject) . '" type="text" name="comments_watcher_email_subject" id="comments_watcher_email_subject" class="form-control" maxlength="255"/>
                                                                                </div> 
                                                                            </div> 
                                                                        </div> 
                                                                    </div> 
                                                                </div> 
                                                            </div> 
                                                        </div> 
                                                    </div> 
                                                </div> 
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
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_delete_button . '
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>
    <script>
    // SEO character counters — logic in assets/backend.src.js
    initSeoCounters([
        { sel: "#title",            counterId: "seo_c_title",            min: 50,  max: 60  },
        { sel: "#meta_description", counterId: "seo_c_meta_description", min: 150, max: 160 }
    ]);

    // Indexing switch dependencies — logic in assets/backend.src.js
    bindPageIndexingSwitches();
    </script>' .
    output_footer();

    $liveform->remove_form('edit_page');

// else -> process
} else {
    validate_token_field();
    
    // get current page data
    $query =
        "SELECT
            page_id,
            page_name,
            page_type,
            layout_type,
            page_title,
            page_meta_description,
            seo_analysis_current,
            page_search,
            page_search_keywords,
            page_folder,
            sitemap,
            " . (pg_page_noindex_ready() ? "noindex, nofollow" : "'0' AS noindex, '0' AS nofollow") . "
        FROM page
        WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $page_id = $row['page_id'];
	$current_page_name = $row['page_name'];
    $current_page_type = $row['page_type'];
    $current_layout_type = $row['layout_type'];
    $current_page_title = $row['page_title'];
    $current_page_meta_description = $row['page_meta_description'];
    $current_seo_analysis_current = $row['seo_analysis_current'];
    $current_page_search = $row['page_search'];
    $current_search_keywords = $row['page_search_keywords'];
    $current_page_folder = $row['page_folder'];
    $current_sitemap = $row['sitemap'];
    $current_noindex = $row['noindex'];
    $current_nofollow = $row['nofollow'];
    
    // if page was selected for delete, check if user has access and then delete page
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // if the user has a user role and the user does not have access to delete pages, then output error
        if (($user['role'] == '3') && ($user['delete_pages'] == FALSE)) {
            log_activity(lang('access denied because user does not have access to delete pages'), $_SESSION['sessionusername']);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        // if this page is a custom form, we need to check if there are submitted forms for this page,
        // because software will not allow this page to be deleted if there are submitted forms for this page
        if ($current_page_type == 'custom form') {
            $query = "SELECT id FROM forms WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // if there are submitted forms for this page, do not delete page and notify user
            if (mysqli_num_rows($result) > 0) {
                $liveform->mark_error('', lang('This page could not be deleted because there are submitted forms for this page. All submitted forms for this page must be deleted before this page is allowed to be deleted. You may disable the custom form on this page by unchecking the Enable Form check box below. You may archive this page by moving it to a private folder.'));
                
                // forward user to edit page screen
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . $_POST['id'] . '&send_to=' . urlencode($_POST['send_to']));
                exit();
            }
        }
        
        // if this page was a search results page type, then remove the keywords from the tag cloud
        if ($current_page_type == 'search results') {
            delete_tag_cloud_keywords_for_search_results_page($_POST['id']);
        }
        
        // delete page
        $query = "DELETE FROM page WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete regions
        $query = "DELETE FROM pregion WHERE pregion_page = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete form fields for page
        $query = "DELETE FROM form_fields WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete form field options for page
        $query = "DELETE FROM form_field_options WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // Delete target options for page.
        db("DELETE FROM target_options WHERE page_id = '" . escape($_POST['id'] ?? '') . "'");
        
        // if this page is a form list view, delete records from related tables.
        if ($current_page_type == 'form list view') {
            $query = "DELETE FROM form_list_view_filters WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            $query = "DELETE FROM form_list_view_browse_fields WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $query = "DELETE FROM form_view_directories_form_list_views_xref WHERE form_list_view_page_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
        
        // if this page is a form view directory, delete form_view_directories_form_list_views_xref records
        if ($current_page_type == 'form view directory') {
            $query = "DELETE FROM form_view_directories_form_list_views_xref WHERE form_view_directory_page_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
        
        // delete views for this page that the form view directory feature uses
        pg_sfv_delete_views('page_id', $_POST['id']);
        
        // delete calendar_views_calendars_xref records
        $query = "DELETE FROM calendar_views_calendars_xref WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete calendar_event_views_calendars_xref records
        $query = "DELETE FROM calendar_event_views_calendars_xref WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if page type has a table for properties, delete page type record of properties
        if (check_for_page_type_properties($current_page_type) == true) {
            $page_type_table_name = str_replace(' ', '_', $current_page_type) . '_pages';
            
            $query = "DELETE FROM $page_type_table_name WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
        
        // get comment attachments for this page, so they can be deleted
        $query = 
            "SELECT
                comments.id as comment_id,
                files.id,
                files.name
            FROM comments
            LEFT JOIN files ON comments.file_id = files.id
            WHERE
                (comments.page_id = '" . escape($_POST['id'] ?? '') . "')
                AND (files.id IS NOT NULL)";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $attachments = array();
        
        // loop through the attachments in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $attachments[] = $row;
        }
        
        // loop through the attachments so they can be deleted
        foreach ($attachments as $attachment) {
            // check if the file attachment is used by another comment (multiple comments can share the same file attachment when pages are duplicated)
            $query = "SELECT id FROM comments WHERE (file_id = '" . $attachment['id'] . "') AND (id != '" . $attachment['comment_id'] . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if the file attachment is not used by another comment, then delete the file
            if (mysqli_num_rows($result) == 0) {
                // delete file from database
                $query = "DELETE FROM files WHERE id = '" . $attachment['id'] . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                // delete file on file system
                @unlink(FILE_DIRECTORY_PATH . '/' . $attachment['name']);
                
                // log that the file was deleted
                log_activity(lang(array('string'=>'file attachment ({var:1}) for a comment was deleted because the page ({var:2}) was deleted','vars'=>array($attachment['name'],$_POST['name']))), $_SESSION['sessionusername']);
            }
        }
        
        // delete comments for page
        $query = "DELETE FROM comments WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete submitted_form_info for page
        $query = "DELETE FROM submitted_form_info WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete allow new comments data for this page
        $query = "DELETE FROM allow_new_comments_for_items WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete stored SEO structure findings for this page.
        // Guarded on the table existing, because a page can be deleted on an
        // installation that has not run the upgrade that creates it.
        if (db_item("SHOW TABLES LIKE 'seo_issue'")) {
            db("DELETE FROM seo_issue WHERE (entity_type = 'page') AND (entity_id = '" . (int) ($_POST['id'] ?? 0) . "')");
        }

        // Both directions of the link graph: the rows this page produced and
        // the rows naming it as a destination. Leaving the latter would make
        // pages that linked here look connected to something that is gone.
        if (db_item("SHOW TABLES LIKE 'seo_link'")) {
            db("DELETE FROM seo_link WHERE (from_type = 'page') AND (from_id = '" . (int) ($_POST['id'] ?? 0) . "')");
            db("DELETE FROM seo_link WHERE (to_type = 'page') AND (to_id = '" . (int) ($_POST['id'] ?? 0) . "')");
        }

        // delete watchers for this page
        $query = "DELETE FROM watchers WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // remove this page's keywords from the tag cloud
        update_tag_cloud_keywords_for_page($_POST['id'], 0, '', $current_page_search, $current_search_keywords);

        // Check if this page has short links, in order to determine if we need to delete them.
        $query =
            "SELECT COUNT(*)
            FROM short_links
            WHERE
                (destination_type = 'page')
                AND (page_id = '" . escape($_POST['id'] ?? '') . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // If a short link exists, then delete them.
        if ($row[0] != 0) {
            $query =
                "DELETE FROM short_links
                WHERE
                    (destination_type = 'page')
                    AND (page_id = '" . escape($_POST['id'] ?? '') . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }

        db("DELETE FROM preview_styles WHERE page_id = '" . escape($_POST['id'] ?? '') . "'");

        // If a layout file exists, then delete it.
        if (file_exists(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php')) {
            unlink(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php');
        }
        
        log_activity(lang(array('string'=>'page ({var:1}) was deleted','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
        
        // add notice
        $liveform_view_pages = new liveform('view_pages');
        $liveform_view_pages->add_notice(lang('The page has been deleted.'));
        
        // forward user to view pages
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
        exit();
        
    // else page was edited, not deleted
    } else {
        $name = trim($_POST['name']);
        
        // If the page name field is blank.
        if ($name == '') {
            $liveform->mark_error('name', lang('The page must have a name. Please type in a name for the page.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . $_POST['id'] . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        }
        
        // if the page type is catalog or catalog detail then check the name for slashes
        if (($_POST['type'] == 'catalog') || ($_POST['type'] == 'catalog detail')) {
            // if there is a slash in the page name, then output an error
            if (mb_strpos($name, '/') !== FALSE) {
                $liveform->mark_error('name', lang('The page name for catalog and catalog detail pages cannot contain forward slashes. Please type in a new name for the page.'));
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . $_POST['id'] . '&send_to=' . urlencode($_POST['send_to']));
                exit();
            }
        }
        
        $name = str_replace(" ", "_", $name);
        $name = str_replace("&", "_", $name);
        

        if (check_name_availability(array('name' => $name, 'ignore_item_id' => $_POST['id'], 'ignore_item_type' => 'page')) == false) {
            $liveform->mark_error('name', lang('The page name that you entered is already in use. Please enter a different page name.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . $_POST['id'] . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        }
        
        // if page is a custom form, check to see if there is another page with this same form name
        if ($_POST['type'] == 'custom form') {
            $query = "SELECT id FROM custom_form_pages WHERE (form_name = '" . escape($_POST['custom_form_form_name'] ?? '') . "') AND (page_id != '" . escape($_POST['id'] ?? '') . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // if there is another page with this form name, output error
            if (mysqli_num_rows($result) > 0) {
                $liveform->mark_error('custom_form_form_name', lang('The form name that you entered is already in use. Please enter a different form name.'));
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . $_POST['id'] . '&send_to=' . urlencode($_POST['send_to']));
                exit();
            }
        }
        
        // if user is above a user role, then deal with home page checkbox
        if ($user['role'] < 3) {
            $sql_home_page = " page_home = '" . escape($_POST['home'] ?? '') . "',";
            
        // else this user does not have access to change the home page property
        } else {
            $sql_home_page = '';
        }

        // Assume that pretty URLs were disabled previously for this page, until we find out otherwise.
        // We use this in order to determine later if we need to update address names for submitted forms.
        $pretty_urls_old = false;

        // If this is a custom form, then get previous pretty URL status.
        if ($current_page_type == 'custom form') {
            $pretty_urls_old = check_if_pretty_urls_are_enabled($_POST['id']);
        }
        
        // if user is above a user role or current page type is accessible by this user, then prepare to save page type
        if (
            ($user['role'] < 3)
            || ($current_page_type == 'standard')
            || (($current_page_type == 'email a friend') && ($user['set_page_type_email_a_friend'] == TRUE))
            || (($current_page_type == 'folder view') && ($user['set_page_type_folder_view'] == TRUE))
            || (($current_page_type == 'photo gallery') && ($user['set_page_type_photo_gallery'] == TRUE))
            || (($current_page_type == 'catalog') && ($user['set_page_type_catalog'] == TRUE))
            || (($current_page_type == 'catalog detail') && ($user['set_page_type_catalog_detail'] == TRUE))
            || (($current_page_type == 'express order') && ($user['set_page_type_express_order'] == TRUE))
            || (($current_page_type == 'order form') && ($user['set_page_type_order_form'] == TRUE))
            || (($current_page_type == 'shopping cart') && ($user['set_page_type_shopping_cart'] == TRUE))
            || (($current_page_type == 'shipping address and arrival') && ($user['set_page_type_shipping_address_and_arrival'] == TRUE))
            || (($current_page_type == 'shipping method') && ($user['set_page_type_shipping_method'] == TRUE))
            || (($current_page_type == 'billing information') && ($user['set_page_type_billing_information'] == TRUE))
            || (($current_page_type == 'order preview') && ($user['set_page_type_order_preview'] == TRUE))
            || (($current_page_type == 'order receipt') && ($user['set_page_type_order_receipt'] == TRUE))
            || (($current_page_type == 'custom form') && ($user['set_page_type_custom_form'] == TRUE))
            || (($current_page_type == 'custom form confirmation') && ($user['set_page_type_custom_form_confirmation'] == TRUE))
            || (($current_page_type == 'form list view') && ($user['set_page_type_form_list_view'] == TRUE))
            || (($current_page_type == 'form item view') && ($user['set_page_type_form_item_view'] == TRUE))
            || (($current_page_type == 'form view directory') && ($user['set_page_type_form_view_directory'] == TRUE))
            || (($current_page_type == 'calendar view') && ($user['manage_calendars'] == TRUE) && ($user['set_page_type_calendar_view'] == TRUE))
            || (($current_page_type == 'calendar event view') && ($user['manage_calendars'] == TRUE) && ($user['set_page_type_calendar_event_view'] == TRUE))
        ) {
            // assume that we can update the page type until we find out otherwise
            $update_page_type = true;
            
            // if the submitted page type is different from the current page type and this page is a custom form,
            // we need to check if there are submitted forms for this page,
            // because software will not allow the page type for this page to be changed if there are submitted forms for this page
            if (($current_page_type != $_POST['type']) && ($current_page_type == 'custom form')) {
                $query = "SELECT id FROM forms WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                if (mysqli_num_rows($result) > 0) {
                    $update_page_type = false;
                    $liveform->mark_error('', lang('The page type for this page could not be changed because there are submitted forms for this page.  All submitted forms for this page must be deleted before the page type for this page is allowed to be changed.'));
                }
            }
            
            // if we can update the page type, then update page type and tag cloud table if needed
            if ($update_page_type == true) {
                // if the page type was changed and the original page type was a search results page type, then remove the keywords for the products and product groups if there are any to remove
                if (($current_page_type != $_POST['type']) && ($current_page_type == 'search results')) {
                    delete_tag_cloud_keywords_for_search_results_page($_POST['id']);
                }
                
                switch($_POST['type']) {
                    case 'email a friend':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'submit_button_label' => $_POST['email_a_friend_submit_button_label'],
                            'next_page_id' => $_POST['email_a_friend_next_page_id']
                        );
                        
                        break;

                    case 'folder view':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'pages' => $_POST['folder_view_pages'],
                            'files' => $_POST['folder_view_files']
                        );
                        
                        break;
                        
                    case 'photo gallery':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'number_of_columns' => $_POST['photo_gallery_number_of_columns'],
                            'thumbnail_max_size' => $_POST['photo_gallery_thumbnail_max_size']
                        );
                        
                        break;
                        
                    case 'search results':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'search_folder_id' => $_POST['search_results_search_folder_id'],
                            'search_catalog_items' => $_POST['search_results_search_catalog_items'],
                            'product_group_id' => $_POST['search_results_product_group_id'],
                            'catalog_detail_page_id' => $_POST['search_results_catalog_detail_page_id']
                        );
                        
                        // update the tag cloud tables
                        update_tag_cloud_keywords_for_search_results_page_type($_POST['id'], $_POST['search_results_search_catalog_items'], $_POST['search_results_product_group_id']);
                        
                        break;
                        
                    case 'update address book':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'address_type' => $_POST['update_address_book_address_type'],
                            'address_type_page_id' => $_POST['update_address_book_address_type_page_id']
                        );
                        
                        break;
                        
                    case 'custom form':
                        $new_contact_group_id = $_POST['custom_form_contact_group_id'];
                        
                        // if user has a user role, then verify that user has access to contact group that was selected
                        if ($user['role'] == 3) {
                            // get current contact group id
                            $query = "SELECT contact_group_id
                                     FROM custom_form_pages
                                     WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);
                            
                            $current_contact_group_id = $row['contact_group_id'];
                            
                            // if contact group is trying to be changed
                            // and a contact group was selected
                            // and user does not have access to contact group,
                            // then don't allow contact group to be changed
                            if (($new_contact_group_id != $current_contact_group_id) && ($new_contact_group_id) && (validate_contact_group_access($user, $new_contact_group_id) == false)) {
                                $new_contact_group_id = $current_contact_group_id;
                                $liveform->mark_error('', lang('The contact group for the custom form could not be changed, because you do not have access to the contact group you selected.'));
                                log_activity(lang('access denied to change contact group for custom form because user did not have access to contact group'), $_SESSION['sessionusername']);
                            }
                        }

                        $new_private_folder_id = $_POST['custom_form_private_folder_id'];
                        
                        // If user has a user role, then verify that user has access to the selected private folder.
                        if (USER_ROLE == 3) {
                            $old_private_folder_id = db_value(
                                "SELECT private_folder_id
                                FROM custom_form_pages
                                WHERE page_id = '" . escape($_POST['id'] ?? '') . "'");
                            
                            // If the user is trying to change the private folder to a folder
                            // that he/she does not have edit access to,
                            // then don't allow folder to be set and log activity.
                            if (
                                ($new_private_folder_id != $old_private_folder_id)
                                && ($new_private_folder_id)
                                && (check_edit_access($new_private_folder_id) == false)
                            ) {
                                $new_private_folder_id = $old_private_folder_id;
                                $liveform->mark_error('', lang('The private folder for the custom form could not be changed, because you do not have edit access to the folder you selected.'));
                                log_activity(lang('access denied to change private folder for custom form because user did not have edit access to folder'), $_SESSION['sessionusername']);
                            }
                        }
                        
                        $properties = array(
                            'page_id' => $_POST['id'],
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
                            'contact_group_id' => $new_contact_group_id,
                            'membership' => $_POST['custom_form_membership'],
                            'membership_days' => $_POST['custom_form_membership_days'],
                            'membership_start_page_id' => $_POST['custom_form_membership_start_page_id'],
                            'private' => $_POST['custom_form_private'],
                            'private_folder_id' => $new_private_folder_id,
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

                        // If commerce is enabled then save offer properties.
                        if (ECOMMERCE) {
                            // If user does not have access to commerce, then get old values,
                            // because we might need to save them.
                            if (!USER_MANAGE_ECOMMERCE) {
                                $old_properties = db_item(
                                    "SELECT
                                        offer,
                                        offer_id
                                    FROM custom_form_pages
                                    WHERE page_id = '" . e($_POST['id'] ?? '') . "'");
                            }

                            // If the user has access to commerce or the user just disabled grant offer,
                            // then save grant offer status.
                            if (USER_MANAGE_ECOMMERCE || !$_POST['custom_form_offer']) {
                                $properties['offer'] = $_POST['custom_form_offer'];

                            // Otherwise the user does not have access to commerce, so save old value.
                            } else {
                                $properties['offer'] = ($old_properties['offer'] ?? '');
                            }

                            // If the user has access to commerce or the user just selected the blank offer,
                            // then save the offer id from the user.
                            if (USER_MANAGE_ECOMMERCE || !$_POST['custom_form_offer_id']) {
                                $properties['offer_id'] = $_POST['custom_form_offer_id'];

                            // Otherwise the user does not have access to commerce, so save old value.
                            } else {
                                $properties['offer_id'] = ($old_properties['offer_id'] ?? '');
                            }

                            $properties['offer_days'] = $_POST['custom_form_offer_days'];
                            $properties['offer_eligibility'] = $_POST['custom_form_offer_eligibility'];
                        }
                        
                        break;
                    
                    case 'custom form confirmation':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'continue_button_label' => $_POST['custom_form_confirmation_continue_button_label'],
                            'next_page_id' => $_POST['custom_form_confirmation_next_page_id']
                        );
                        
                        break;
                    
                    case 'form list view':
                        $new_custom_form_page_id = $_POST['form_list_view_custom_form_page_id'];
                        
                        // if user has a user role, then verify that user has access to custom form that was selected
                        if ($user['role'] == 3) {
                            // get current custom form
                            $query = "SELECT custom_form_page_id
                                     FROM form_list_view_pages
                                     WHERE
                                        (page_id = '" . escape($_POST['id'] ?? '') . "')
                                        AND (collection = 'a')";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);
                            
                            $current_custom_form_page_id = $row['custom_form_page_id'];
                            
                            // get folder of new custom_form
                            $query = "SELECT page_folder
                                     FROM page
                                     WHERE page_id = '" . escape($new_custom_form_page_id) . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);
                            
                            $new_custom_form_folder_id = $row['page_folder'];
                            
                            // if custom form is trying to be changed and user does not have access to custom form, don't allow custom form to be changed
                            if (($new_custom_form_page_id != $current_custom_form_page_id) && (check_edit_access($new_custom_form_folder_id) == false)) {
                                $new_custom_form_page_id = $current_custom_form_page_id;
                                $liveform->mark_error('', lang('The custom form for the form list view could not be changed, because you do not have access to the custom form you selected.'));
                                log_activity(lang('access denied to change custom form for form list view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                            }
                        }
                        
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'custom_form_page_id' => $new_custom_form_page_id,
                            'form_item_view_page_id' => $_POST['form_list_view_form_item_view_page_id'],
                            'viewer_filter' => $_POST['form_list_view_viewer_filter'],
                            'viewer_filter_submitter' => $_POST['form_list_view_viewer_filter_submitter'],
                            'viewer_filter_watcher' => $_POST['form_list_view_viewer_filter_watcher'],
                            'viewer_filter_editor' => $_POST['form_list_view_viewer_filter_editor']
                        );
                        
                        // check if there is a record for this form list view
                        $query =
                            "SELECT COUNT(*)
                            FROM form_list_view_pages
                            WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        $row = mysqli_fetch_row($result);
                        
                        // if there is not a record for this form list view, then set default values
                        if ($row[0] == 0) {
                            $properties['search'] = 1;
                            $properties['show_results_by_default'] = 1;
                            $properties['maximum_number_of_results_per_page'] = 25;
                        }
                        
                        break;
                    
                    case 'form item view':
                        $new_custom_form_page_id = $_POST['form_item_view_custom_form_page_id'];
                        
                        // if user has a user role, then verify that user has access to custom form that was selected
                        if ($user['role'] == 3) {
                            // get current custom form
                            $query =
                                "SELECT custom_form_page_id
                                FROM form_item_view_pages
                                WHERE
                                    (page_id = '" . escape($_POST['id'] ?? '') . "')
                                    AND (collection = 'a')";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);
                            
                            $current_custom_form_page_id = $row['custom_form_page_id'];
                            
                            // get folder of new custom_form
                            $query = "SELECT page_folder
                                     FROM page
                                     WHERE page_id = '" . escape($new_custom_form_page_id) . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);
                            
                            $new_custom_form_folder_id = $row['page_folder'];
                            
                            // if custom form is trying to be changed and user does not have access to custom form, don't allow custom form to be changed
                            if (($new_custom_form_page_id != $current_custom_form_page_id) && (check_edit_access($new_custom_form_folder_id) == false)) {
                                $new_custom_form_page_id = $current_custom_form_page_id;
                                $liveform->mark_error('', lang('The custom form for the form item view could not be changed, because you do not have access to the custom form you selected.'));
                                log_activity(lang('access denied to change custom form for form item view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                            }
                        }
                        
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'custom_form_page_id' => $new_custom_form_page_id,
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
                            'page_id' => $_POST['id'],
                            'summary' => $_POST['form_view_directory_summary'],
                            'summary_days' => $_POST['form_view_directory_summary_days'],
                            'summary_maximum_number_of_results' => $_POST['form_view_directory_summary_maximum_number_of_results'],
                            'form_list_view_heading' => $form_list_view_heading,
                            'subject_heading' => $subject_heading,
                            'number_of_submitted_forms_heading' => $number_of_submitted_forms_heading
                        );
                        
                        // delete old connections between form view directory and form list views
                        $query = "DELETE FROM form_view_directories_form_list_views_xref WHERE form_view_directory_page_id = '" . escape($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        
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
                                        '" . escape($_POST['id'] ?? '') . "',
                                        '" . $form_list_view['page_id'] . "',
                                        '" . escape($_POST['form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_name']) . "',
                                        '" . escape($_POST['form_view_directory_form_list_view_' . $form_list_view['page_id'] . '_subject_form_field_id']) . "')";
                                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            }
                        }
                        
                        break;
                        
                    case 'calendar view':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'default_view' => $_POST['calendar_view_default_view'],
                            'number_of_upcoming_events' => $_POST['calendar_view_number_of_upcoming_events'],
                            'calendar_event_view_page_id' => $_POST['calendar_view_calendar_event_view_page_id']
                        );
                        
                        // delete old connections between calendar view and calendars
                        $query = "DELETE FROM calendar_views_calendars_xref WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        
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
                                       '" . escape($_POST['id'] ?? '') . "',
                                       '" . $calendar['id'] . "')";
                                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            }
                        }
                        
                        // Get the calendar view pages default view property to see if it has been updated.
                        $query =
                            "SELECT
                               default_view
                            FROM calendar_view_pages
                            WHERE id = '" . e($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        $row = mysqli_fetch_assoc($result);
                        
                        // If the default_view property was changed
                        if ($row['default_view'] != $_POST['calendar_view_default_view']) {
                            // Unset the session value.
                            unset($_SESSION['software']['calendar_views'][$_POST['id']]['view']);
                        }
                        
                        break;
                        
                    case 'calendar event view':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'notes' => $_POST['calendar_event_view_notes'],
                            'back_button_label' => $_POST['calendar_event_view_back_button_label']
                        );
                    
                        // delete old connections between calendar event view and calendars
                        $query = "DELETE FROM calendar_event_views_calendars_xref WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        
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
                                       '" . escape($_POST['id'] ?? '') . "',
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
                            'page_id' => $_POST['id'],
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
                            'page_id' => $_POST['id'],
                            'allow_customer_to_add_product_to_order' => $_POST['catalog_detail_allow_customer_to_add_product_to_order'],
                            'add_button_label' => $_POST['catalog_detail_add_button_label'],
                            'next_page_id' => $_POST['catalog_detail_next_page_id'],
                            'back_button_label' => $_POST['catalog_detail_back_button_label']
                        );
                        
                        break;
                    
                    case 'express order':

                        // Get current form values before we update it, so that we know later
                        // if we should forward the user to the form designer or not.

                        $old_properties = db_item(
                            "SELECT shipping_form, form FROM express_order_pages
                            WHERE page_id = '" . e($_POST['id'] ?? '') . "'");

                        $properties = array(
                            'page_id' => $_POST['id'],
                            'shopping_cart_label' => $_POST['express_order_shopping_cart_label'],
                            'quick_add_label' => $_POST['express_order_quick_add_label'],
                            'quick_add_product_group_id' => $_POST['express_order_quick_add_product_group_id'],
                            'product_description_type' => $_POST['express_order_product_description_type'],
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
                            'page_id' => $_POST['id'],
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
                            'page_id' => $_POST['id'],
                            'shopping_cart_label' => $_POST['shopping_cart_shopping_cart_label'],
                            'quick_add_label' => $_POST['shopping_cart_quick_add_label'],
                            'quick_add_product_group_id' => $_POST['shopping_cart_quick_add_product_group_id'],
                            'product_description_type' => $_POST['shopping_cart_product_description_type'],
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
                        // get current form value before we update it, so that we know later if we should forward the user to the form designer or not
                        $query = "SELECT form FROM shipping_address_and_arrival_pages WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        $row = mysqli_fetch_assoc($result);
                        $current_shipping_address_and_arrival_form = $row['form'];
                        
                        $properties = array(
                            'page_id' => $_POST['id'],
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
                            'page_id' => $_POST['id'],
                            'product_description_type' => $_POST['shipping_method_product_description_type'],
                            'submit_button_label' => $_POST['shipping_method_submit_button_label'],
                            'next_page_id' => $_POST['shipping_method_next_page_id']
                        );
                        
                        break;

                    case 'billing information':
                        // Get current form value before we update it, so that we know later
                        // if we should forward the user to the form designer or not.
                        $current_billing_information_form = db_value("SELECT form FROM billing_information_pages WHERE page_id = '" . escape($_POST['id'] ?? '') . "'");

                        $properties = array(
                            'page_id' => $_POST['id'],
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
                            'page_id' => $_POST['id'],
                            'product_description_type' => $_POST['order_preview_product_description_type'],
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
                            'page_id' => $_POST['id'],
                            'product_description_type' => $_POST['order_receipt_product_description_type']
                        );
                        
                        break;
                        
                    case 'affiliate sign up form':
                        $properties = array(
                            'page_id' => $_POST['id'],
                            'terms_page_id' => $_POST['affiliate_sign_up_form_terms_page_id'],
                            'submit_button_label' => $_POST['affiliate_sign_up_form_submit_button_label'],
                            'next_page_id' => $_POST['affiliate_sign_up_form_next_page_id']
                        );
                        
                        break;
                }
                
                // if page type was changed then check if there are database records that we need to delete
                if ($current_page_type != $_POST['type']) {
                    // if current page type has a table for properties, delete page type record of properties
                    if (check_for_page_type_properties($current_page_type) == TRUE) {
                        $page_type_table_name = str_replace(' ', '_', $current_page_type) . '_pages';
                        
                        $query = "DELETE FROM $page_type_table_name WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                    
                    // if the old page type was form list view, delete form_view_directories_form_list_views_xref records
                    if ($current_page_type == 'form list view') {
                        $query = "DELETE FROM form_view_directories_form_list_views_xref WHERE form_list_view_page_id = '" . escape($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }

                    // if the old page type was form view directory, then delete form_view_directories_form_list_views_xref records
                    if ($current_page_type == 'form view directory') {
                        $query = "DELETE FROM form_view_directories_form_list_views_xref WHERE form_view_directory_page_id = '" . escape($_POST['id'] ?? '') . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }
                }
                
                // if new page type has a table for properties, create record in page type table
                if (check_for_page_type_properties($_POST['type']) == true) {
                    create_or_update_page_type_record($_POST['type'], $properties);
                }
                
                $sql_page_type = " page_type = '" . escape($_POST['type'] ?? '') . "',";
                
            // else we cannot update the page type, so don't allow page type to be updated
            } else {
                $sql_page_type = '';
            }
            
        // else this user does not have access to change the page type property
        } else {
            $sql_page_type = '';
        }

        // If the user is allowed to change the page type property,
        // and the page type supports a layout,
        // and the user is an admin or a designer,
        // or the user just selected the system option,
        // then use the value that the user selected.
        if (
            $sql_page_type
            && check_if_page_type_supports_layout($_POST['type'])
            &&
            (
                (USER_ROLE < 2)
                || ($_POST['layout_type'] == 'system')
            )
        ) {
            $layout_type = $_POST['layout_type'];

        // Otherwise use old layout type value.
        } else {
            $layout_type = $current_layout_type;
        }
        
        // call the function that is responsible for updating the tag cloud table for pages
        update_tag_cloud_keywords_for_page($_POST['id'], $_POST['search'] ?? '', $_POST['search_keywords'] ?? '', $current_page_search, $current_search_keywords);
        
        $sql_style_fields = "";

        // if user role is Administrator, Designer, or Manager, then allow user to change desktop style and mobile style for page
        if ($user['role'] < 3) {
            $sql_style_fields =
                "page_style = '" . escape($_POST['style'] ?? '') . "',
                mobile_style_id = '" . escape($_POST['mobile_style_id'] ?? '') . "',";
        }
        
        $noindex = 0;
        $nofollow = 0;

        // The two indexing switches only mean something together. nofollow
        // qualifies the noindex directive and is never emitted on its own, so it
        // is stored as off whenever the page is open to search engines, and
        // nothing downstream has to decide which of the two to believe.
        if (($_POST['noindex'] ?? '') == 1) {
            $noindex = 1;

            if (($_POST['nofollow'] ?? '') == 1) {
                $nofollow = 1;
            }
        }

        // if sitemap was enabled and the selected page type is a valid page type for the sitemap,
        // then include this page in the sitemap
        if (
            (($_POST['sitemap'] ?? '') == 1)
            &&
            (
                ($_POST['type'] == 'standard')
                || ($_POST['type'] == 'folder view')
                || ($_POST['type'] == 'photo gallery')
                || ($_POST['type'] == 'custom form')
                || ($_POST['type'] == 'form list view')
                || ($_POST['type'] == 'form item view')
                || ($_POST['type'] == 'form view directory')
                || ($_POST['type'] == 'calendar view')
                || ($_POST['type'] == 'calendar event view')
                || ($_POST['type'] == 'catalog')
                || ($_POST['type'] == 'catalog detail')
                || ($_POST['type'] == 'express order')
                || ($_POST['type'] == 'order form')
                || ($_POST['type'] == 'shopping cart')
                || ($_POST['type'] == 'search results')
            )
        ) {
            $sitemap = 1;
            
        // else sitemap was disabled or the selected page type is not a valid page type for the sitemap,
        // so do not include this page in the sitemap
        } else {
            $sitemap = 0;
        }

        // A page that is closed to search engines has no business in the site
        // map. The switch is disabled on screen while noindex is on, so a
        // browser never sends it, but a POST does not have to come from that
        // screen and the two columns have to agree in the database.
        if ($noindex == 1) {
            $sitemap = 0;
        }

        $sql_noindex_fields = "";

        if (pg_page_noindex_ready() == TRUE) {
            $sql_noindex_fields =
                "noindex = '" . $noindex . "',
                nofollow = '" . $nofollow . "',";
        }

        $sql_seo_analysis_current = "";

        // If the seo analysis is current and any field the SEO score reads
        // has changed, then prepare to clear the current status. Beyond the
        // title and description: the promoted keywords feed the site search
        // check, the name feeds the URL check, and the folder, search and
        // sitemap switches decide which checks apply to the page at all, and
        // the indexing switches change the robots tag the structure pass reads
        // back out of the rendered markup.
        if (
            ($current_seo_analysis_current == 1)
            && (
                ($current_page_title != $_POST['title'])
                || ($current_page_meta_description != $_POST['meta_description'])
                || ($current_search_keywords != ($_POST['search_keywords'] ?? ''))
                || ($current_page_name != $name)
                || ((int) $current_page_folder != (int) ($_POST['folder'] ?? 0))
                || ((int) $current_page_search != (int) ($_POST['search'] ?? 0))
                || ((int) $current_sitemap != (int) $sitemap)
                || ((int) $current_noindex != (int) $noindex)
                || ((int) $current_nofollow != (int) $nofollow)
            )
        ) {
            $sql_seo_analysis_current = "seo_analysis_current = '0',";
        }
        
        // update page
        $query =
            "UPDATE page
            SET
                page_name = '" . escape($name) . "',
                page_folder = '" . escape($_POST['folder'] ?? '') . "',
                $sql_page_type
                layout_type = '" . e($layout_type) . "',
                $sql_home_page
                page_search = '" . escape($_POST['search'] ?? '') . "',
                page_search_keywords = '" . escape($_POST['search_keywords'] ?? '') . "',
                page_timestamp = UNIX_TIMESTAMP(),
                page_user = '" . $user['id'] . "',
                $sql_style_fields
                page_title = '" . escape($_POST['title'] ?? '') . "',
                page_meta_description = '" . escape($_POST['meta_description'] ?? '') . "',
                sitemap = '" . $sitemap . "',
                $sql_noindex_fields
                $sql_seo_analysis_current
                comments = '" . escape($_POST['comments'] ?? '') . "',
                comments_label = '" . e($_POST['comments_label'] ?? '') . "',
                comments_message = '" . e($_POST['comments_message'] ?? '') . "',
                comments_rating = '" . escape($_POST['comments_rating'] ?? '') . "',
                comments_allow_new_comments = '" . escape($_POST['comments_allow_new_comments'] ?? '') . "',
                comments_disallow_new_comment_message = '" . escape($_POST['comments_disallow_new_comment_message'] ?? '') . "',
                comments_automatic_publish = '" . escape($_POST['comments_automatic_publish'] ?? '') . "',
                comments_allow_user_to_select_name = '" . escape($_POST['comments_allow_user_to_select_name'] ?? '') . "',
                comments_require_login_to_comment = '" . escape($_POST['comments_require_login_to_comment'] ?? '') . "',
                comments_allow_file_attachments = '" . escape($_POST['comments_allow_file_attachments'] ?? '') . "',
                comments_show_submitted_date_and_time = '" . escape($_POST['comments_show_submitted_date_and_time'] ?? '') . "',
                comments_administrator_email_to_email_address = '" . escape($_POST['comments_administrator_email_to_email_address'] ?? '') . "',
                comments_administrator_email_subject = '" . escape($_POST['comments_administrator_email_subject'] ?? '') . "',
                comments_administrator_email_conditional_administrators = '" . escape($_POST['comments_administrator_email_conditional_administrators'] ?? '') . "',
                comments_submitter_email_page_id = '" . escape($_POST['comments_submitter_email_page_id'] ?? '') . "',
                comments_submitter_email_subject = '" . escape($_POST['comments_submitter_email_subject'] ?? '') . "',
                comments_watcher_email_page_id = '" . escape($_POST['comments_watcher_email_page_id'] ?? '') . "',
                comments_watcher_email_subject = '" . escape($_POST['comments_watcher_email_subject'] ?? '') . "',
                comments_watchers_managed_by_submitter = '" . escape($_POST['comments_watchers_managed_by_submitter'] ?? '') . "'
            WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // If this page is a custom form, and pretty URLs were disabled before this update,
        // and now they are enabled, then update address names for submitted forms for pretty URLs feature.
        if (
            ($_POST['type'] == 'custom form')
            && ($pretty_urls_old == false)
            && (check_if_pretty_urls_are_enabled($_POST['id']) == true)
        ) {
            update_multiple_submitted_form_address_names($_POST['id']);
        }
        
        log_activity("page ($name) was modified", $_SESSION['sessionusername']);
        
        $send_to = $_POST['send_to'];
        
        // If there is not a send to value
        if ((isset($send_to) == FALSE) || ($send_to == '')) {
                $send_to = PATH . $name;
        }
        
        // If the page name has changed, or if the page type has been changed
        // from a page type that does not require from=control_panel to one that does
        // then update the send_to, so that it will work
        if (
            ($name != $current_page_name)
            ||
            (
                (check_if_page_type_requires_from_control_panel($current_page_type) == false)
                && (check_if_page_type_requires_from_control_panel($_POST['type']) == true)
            )
        ) {
            $query_string_from = '';

            if (check_if_page_type_requires_from_control_panel($_POST['type']) == true) {
                $query_string_from = '?from=control_panel';
            }
            
            $send_to = PATH . encode_url_path($name) . $query_string_from;
        }
        
        // If a custom form was enabled then forward user to form designer.
        if (
            (($_POST['type'] == 'custom form') && ($current_page_type != 'custom form'))
            || (($_POST['type'] == 'shipping address and arrival') && ($_POST['shipping_address_and_arrival_form'] == 1) && ($current_shipping_address_and_arrival_form != 1))
            || (($_POST['type'] == 'billing information') && ($_POST['billing_information_form'] == 1) && ($current_billing_information_form != 1))
            || (
                ($_POST['type'] == 'express order')
                and (
                    ($_POST['express_order_shipping_form'] and !($old_properties['shipping_form'] ?? ''))
                    or ($_POST['express_order_form'] and !($old_properties['form'] ?? ''))
                )
            )
        ) {

            $form_type = '';

            // If this is an express order page, then determine if we should forward to shipping
            // or billing form.
            if ($_POST['type'] == 'express order') {

                $form_type = '&form_type=';

                if ($_POST['express_order_shipping_form'] and !($old_properties['shipping_form'] ?? '')) {
                    $form_type .= 'shipping';
                } else {
                    $form_type .= 'billing';
                }
            }

            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $_POST['id'] . $form_type . '&send_to=' . urlencode($send_to));
            
        // else we don't need to forward the user to the form designer so forward the user to the page
        } else {
            header('Location: ' . URL_SCHEME . HOSTNAME . $send_to);
        }
    }
}