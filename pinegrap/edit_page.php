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
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

$liveform = new liveform('edit_page');

$user = validate_user();
validate_area_access($user, 'user');

// The folder check below decides who may edit or delete the page, so it has
// to run on the id the handler further down reads: $_POST for a submit, $_GET
// for the form. $_REQUEST can also be fed by a cookie of the same name, which
// would let the check pass on one page while the handler changes another.
$requested_page_id = $_POST ? ($_POST['id'] ?? '') : ($_GET['id'] ?? '');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($requested_page_id) ? (int) $requested_page_id : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM page WHERE page_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// get page's folder in order to validate folder access
$result = mysqli_query(db::$con, "SELECT page_id, page_folder FROM page WHERE page_id = '" . escape($requested_page_id) . "'") or output_error('Query failed.');
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
            " . (pg_page_seo_extras_ready() ? "canonical_url, noarchive, max_snippet," : "'' AS canonical_url, '0' AS noarchive, NULL AS max_snippet,") . "
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
            " . (pg_ai_fields_ready('page') ? "ai_fields," : "NULL AS ai_fields,") . "
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
    $page_ai_fields = $row['ai_fields'];

    // A visual-editor page (its own style is a visual design) has no
    // regions to edit here; an old bookmark or a hand-typed URL lands in the
    // visual editor instead.
    if (pg_page_is_visual_design(array('page_style' => $page_style, 'has_tree' => 0))) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . pg_page_edit_url(array('page_id' => $page_id, 'page_style' => $page_style)));
        exit();
    }
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
    $noindex = (int) ($row['noindex'] ?? 0);
    $nofollow = (int) ($row['nofollow'] ?? 0);
    $page_canonical_url = (string) ($row['canonical_url'] ?? '');
    $page_noarchive = (int) ($row['noarchive'] ?? 0);
    $page_max_snippet = (($row['max_snippet'] ?? null) === null) ? '' : (string) $row['max_snippet'];
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
    
    // The page type area below is only prepared when this user may set the
    // page's type; the form template prints these three either way.
    $output_button_bar = '';
    $output_page_type_selector = '';
    $output_page_type_properties = '';

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
        $page_type_properties = array();

        if (check_for_page_type_properties($page_type)) {
            $page_type_properties[$page_type] = get_page_type_properties($page_id, $page_type);
        }

        $stored_properties = $page_type_properties[$page_type] ?? array();

        $output_edit_custom_form = '';
        $output_layout_buttons = '';
        $output_edit_form_list_view = '';
        $output_edit_form_item_view = '';
        $output_edit_custom_shipping_form = '';
        $output_edit_custom_billing_form = '';

        // If this page has a custom layout, and user is an admin or designer,
        // then output layout buttons in the button bar.
        if (
            check_if_page_type_supports_layout($page_type)
            && ($layout_type == 'custom')
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

        switch ($page_type) {
            case 'custom form':
                $output_edit_custom_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Form') . '</a>';
                break;

            case 'form list view':
                $output_edit_form_list_view = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_form_list_view.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">list</span>' . lang('Edit Form List View') . '</a>';
                break;

            case 'form item view':
                $output_edit_form_item_view = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_form_item_view.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">description</span>' . lang('Edit Form Item View') . '</a>';
                break;

            case 'express order':
                if (($stored_properties['shipping_form'] ?? '')) {
                    $output_edit_custom_shipping_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;form_type=shipping&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Shipping Form') . '</a>';
                }

                if (($stored_properties['form'] ?? '') == '1') {
                    $output_edit_custom_billing_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;form_type=billing&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Billing Form') . '</a>';
                }

                break;

            case 'shipping address and arrival':
                if (($stored_properties['form'] ?? '') == '1') {
                    $output_edit_custom_shipping_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Shipping Form') . '</a>';
                }

                break;

            case 'billing information':
                if (($stored_properties['form'] ?? '') == '1') {
                    $output_edit_custom_billing_form = '<a class="btn btn-link link-secondary py-0 mb-2 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_fields.php?page_id=' . $page_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="material-icons me-1">edit_note</span>' . lang('Edit Custom Billing Form') . '</a>';
                }

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

        $page_type_fields = pg_page_form_type_fields($page_type, $layout_type, $page_type_properties, $user, $page_id);

        $output_page_type_selector = $page_type_fields['selector'];
        $output_page_type_properties = $page_type_fields['properties'];
        $output_wysiwyg_editor_code = $page_type_fields['wysiwyg'];
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
    
    // Search engine indexing. The block is only built where the columns behind
    // it exist: a switch that saves nothing is worse than no switch at all.
    //
    // nofollow arrives disabled unless the page is already closed to search
    // engines, which is the state the master switch would put it in anyway -
    // bindPageIndexingSwitches() keeps the two in step from then on.
    $output_noindex_rows = '';

    if (pg_page_noindex_ready() == TRUE) {

        $noindex_checked = ($noindex == 1) ? ' checked="checked"' : '';
        $nofollow_checked = ($nofollow == 1) ? ' checked="checked"' : '';
        $nofollow_disabled = ($noindex == 1) ? '' : ' disabled="disabled"';

        $output_noindex_rows =
            '<div class="col-12 my-3" id="noindex_row">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="noindex" name="noindex" value="1"' . $noindex_checked . '>
                    <label class="form-check-label" for="noindex">' . lang('Close to Search Engines (noindex)') . '</label>
                </div>
                <div class="form-text">' . lang('The page is served with a noindex robots tag, is blocked in robots.txt and is left out of the site map.') . '</div>
                <div class="form-check form-switch mt-3 ms-4">
                    <input class="form-check-input" type="checkbox" id="nofollow" name="nofollow" value="1"' . $nofollow_checked . $nofollow_disabled . '>
                    <label class="form-check-label" for="nofollow">' . lang('Do Not Follow Links on This Page (nofollow)') . '</label>
                </div>
                <div class="form-text ms-4">' . lang('Leave this off so that search engines keep discovering the items this page links to. Turn it on for a widget page whose links already appear on a page that is indexed.') . '</div>
            </div>';
    }

    // The page's own canonical address and the robots directives besides
    // noindex (includes/fn/seo.php), where the columns exist. A value the save
    // refused comes back as it was typed, with the reason above the form; the
    // hidden field tells the save that this form carried the three.
    $output_seo_extra_rows = '';

    if (pg_page_seo_extras_ready() == TRUE) {

        $canonical_invalid = '';
        $max_snippet_invalid = '';

        if ($liveform->check_field_error('canonical_url')) {
            $page_canonical_url = (string) $liveform->get_field_value('canonical_url');
            $canonical_invalid = ' is-invalid';
        }

        if ($liveform->check_field_error('max_snippet')) {
            $page_max_snippet = (string) $liveform->get_field_value('max_snippet');
            $max_snippet_invalid = ' is-invalid';
        }

        $noarchive_checked = ($page_noarchive == 1) ? ' checked="checked"' : '';

        $output_seo_extra_rows =
            '<div class="col-12 mt-1 mb-2" id="canonical_url_row">
                <input type="hidden" name="seo_extras" value="1">
                <label for="canonical_url" class="form-label">' . lang('Canonical Address') . '</label>
                <input type="text" value="' . h($page_canonical_url) . '" name="canonical_url" id="canonical_url" class="form-control' . $canonical_invalid . '" maxlength="2000" placeholder="https://" autocomplete="off" spellcheck="false">
                <div class="form-text">' . lang('Leave empty to use the address of this page. Fill it in when the page repeats another one, to name the address search engines should list instead: a full address starting with https://, or a path on this site such as /about-us. Products, posts and other items shown through this page keep their own address.') . '</div>
            </div>
            <div class="col-12 my-3" id="noarchive_row">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="noarchive" name="noarchive" value="1"' . $noarchive_checked . '>
                    <label class="form-check-label" for="noarchive">' . lang('Do Not Keep a Cached Copy (noarchive)') . '</label>
                </div>
                <div class="form-text">' . lang('Asks search engines not to keep or show a cached copy of the page. Like the snippet length, it is left out while the page is closed to search engines.') . '</div>
            </div>
            <div class="col-12 col-md-6 mt-1 mb-2" id="max_snippet_row">
                <label for="max_snippet" class="form-label">' . lang('Snippet Length (max-snippet)') . '</label>
                <input type="number" value="' . h($page_max_snippet) . '" name="max_snippet" id="max_snippet" class="form-control' . $max_snippet_invalid . '" min="-1" max="' . pg_page_max_snippet_limit() . '" step="1">
                <div class="form-text">' . lang('The most characters of text a search result may quote from the page. Leave empty to let the search engine decide; -1 is no limit, 0 is no text snippet.') . '</div>
            </div>';
    }

    // if sitemap is enabled, then check the checkbox
    $sitemap_checked = '';

    if ($sitemap == '1') {
        $sitemap_checked = ' checked="checked"';
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
        // With the recycle bin installed the page goes there; one already in
        // it is deleted for good.
        require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
        $delete_confirmation = (pg_recycle_ready() && !pg_recycle_is_inside($page_folder))
            ? lang('This page will be moved to the Recycle Bin.')
            : lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('page'))));
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-confirm-content="' . $delete_confirmation . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }

    // "Write with AI" beside the browser title and description
    // (includes/fn/ai_write.php): the model reads them and the page's name
    // from the form, the page's folder, headings and text from the site.
    $ai_page_context = array('page_title' => 'title', 'meta_description' => 'meta_description', 'page_name' => 'name');
    $ai_title_button = pg_ai_write_button($user, array('target' => 'seo_title', 'record' => 'page', 'record_id' => $page_id, 'field' => 'title', 'context' => $ai_page_context));
    $ai_description_button = pg_ai_write_button($user, array('target' => 'seo_description', 'record' => 'page', 'record_id' => $page_id, 'field' => 'meta_description', 'context' => $ai_page_context));

    // A text that came from an accepted draft carries a marker beside its
    // label while it is as the draft left it: editing it by hand, the
    // marker's button or saving the field empty takes the mark off
    // (pg_ai_fields_marker(), assets/js/pg_ai_fields.js); the hidden
    // ai_fields input carries the list to the save. A mark whose text was
    // changed elsewhere is not shown and goes with the next save
    // (pg_ai_fields_marked()). Nothing of it is drawn while the column is
    // missing.
    $ai_marks_ready = pg_ai_fields_ready('page');
    $ai_marks = $ai_marks_ready ? pg_ai_fields_marked('page', $page_ai_fields, array('page_title' => $page_title, 'page_meta_description' => $page_meta_description)) : array();
    $ai_title_marker = $ai_marks_ready ? pg_ai_fields_marker('page_title', 'title', in_array('page_title', $ai_marks, true)) : '';
    $ai_description_marker = $ai_marks_ready ? pg_ai_fields_marker('page_meta_description', 'meta_description', in_array('page_meta_description', $ai_marks, true)) : '';

    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Page'),
            'extra classes'=>'page',
            'icon'=>'page',
            'heading'=>lang('Edit Page'),
            'heading_description' => lang('View and update the page, move it to another folder, or change its built-in features.'),
            // Cancel and the breadcrumb go back where the operator came from,
            // when the screen that opened this one said where that was.
            'cancel'=>array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php')),
            'breadcrumb' => array(
                array('label' => lang('Pages'), 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php')),
                array('label' => $page_name),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
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
                        
                        <p>' . lang('Access') . ': ' . h(get_access_control_type_name(get_access_control_type($page_folder))) . $output_subnav_page_type . $output_subnav_short_link . $output_subnav_search . $output_subnav_search_keywords . $output_subnav_next_page . $output_subnav_skip_page . '</p>
                        ' . $output_button_bar . '
                    </div>
                </div>
                <form name="form" action="edit_page.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    ' . ($ai_marks_ready ? pg_ai_fields_input('page', $ai_marks) : '') . '
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
                                                        <input name="name" id="name" type="text" value="' . h($page_name) . '" placeholder="' . lang('Page Name') . '" maxlength="100" class="form-control" required="required" />
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
                                                    ' . pg_ai_write_label_row('<label for="title" class="form-label">' . lang('Web Browser Title') . '</label>', $ai_title_button, $ai_title_marker) . '
                                                    <input type="text" value="' . h($page_title) . '" name="title" id="title" class="form-control" maxlength="255"/>
                                                    <div id="seo_c_title"></div>
                                                </div>
                                                <div class="col-12 mt-1 mb-2">
                                                    ' . pg_ai_write_label_row('<label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>', $ai_description_button, $ai_description_marker) . '
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
                                                ' . $output_seo_extra_rows . '
                                                <div class="col-12">
                                                    ' . pg_seo_render_checklist($seo_row, 'page', $page_id) . '
                                                    ' . pg_seo_render_run_script() . '
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
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-success "><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_delete_button . '
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    <script>
    // SEO character counters — logic in assets/js/backend.src.js
    initSeoCounters([
        { sel: "#title",            counterId: "seo_c_title",            min: 50,  max: 60  },
        { sel: "#meta_description", counterId: "seo_c_meta_description", min: 150, max: 160 }
    ]);

    // Indexing switch dependencies — logic in assets/js/backend.src.js
    bindPageIndexingSwitches();
    </script>
    ' . pg_ai_write_assets() . '
    ' . ($ai_marks_ready ? pg_ai_fields_assets() : '') . '
</main>' .
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
            " . (pg_ai_fields_ready('page') ? "ai_fields," : "NULL AS ai_fields,") . "
            sitemap
        FROM page
        WHERE page_id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $page_id = $row['page_id'];

    // The whole row as it was, for the audit trail of the save or delete below.
    $audit_before = pg_audit_fetch('page', $page_id);
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
    $current_ai_fields = $row['ai_fields'];
    
    // if page was selected for delete, check if user has access and then delete page
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // if the user has a user role and the user does not have access to delete pages, then output error
        if (($user['role'] == '3') && ($user['delete_pages'] == FALSE)) {
            log_activity(lang('access denied because user does not have access to delete pages'), $_SESSION['sessionusername']);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // With the recycle bin installed the page goes there, the way the file
        // manager deletes it, and keeps everything it had until the bin is
        // emptied. A page already in the bin is deleted for good below.
        require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

        if (pg_recycle_ready()) {
            $recycled = pg_recycle_page($page_id, $user);

            if ($recycled['status'] == 'error') {
                output_error(h($recycled['message']) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }

            if ($recycled['status'] == 'binned') {
                log_activity(lang(array('string' => 'page ({var:1}) was moved to the Recycle Bin', 'vars' => $current_page_name)), $_SESSION['sessionusername']);

                $liveform_view_pages = new liveform('view_pages');
                $liveform_view_pages->add_notice(lang('The page was moved to the Recycle Bin.')
                    . ($recycled['home'] ? ' ' . lang('It was the home page: the site has no home page until another page is marked as home.') : ''));

                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
                exit();
            }
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

        pg_audit_record('delete', 'page', $page_id, $audit_before);
        
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

        // The canonical address and the snippet length are checked before
        // anything is written (pg_page_canonical_url_check(),
        // pg_page_max_snippet_check()). A refused value goes back to the form
        // as typed. The three are written only when the form carried them.
        $seo_extras_sent = pg_page_seo_extras_ready() && (($_POST['seo_extras'] ?? '') == 1);
        $canonical_check = array('ok' => true, 'value' => '', 'error' => '');
        $max_snippet_check = array('ok' => true, 'value' => null, 'error' => '');

        if ($seo_extras_sent) {
            $canonical_raw = (isset($_POST['canonical_url']) && is_string($_POST['canonical_url'])) ? $_POST['canonical_url'] : '';
            $max_snippet_raw = $_POST['max_snippet'] ?? '';

            $canonical_check = pg_page_canonical_url_check($canonical_raw);
            $max_snippet_check = pg_page_max_snippet_check($max_snippet_raw);

            if (!$canonical_check['ok']) {
                $liveform->mark_error('canonical_url', pg_page_canonical_url_message($canonical_check['error'], $current_page_name));
                $liveform->assign_field_value('canonical_url', trim($canonical_raw));
            }

            if (!$max_snippet_check['ok']) {
                $liveform->mark_error('max_snippet', pg_page_max_snippet_message($current_page_name));
                $liveform->assign_field_value('max_snippet', is_string($max_snippet_raw) ? trim($max_snippet_raw) : '');
            }

            if (!$canonical_check['ok'] || !$max_snippet_check['ok']) {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . (int) $page_id . '&send_to=' . urlencode((string) ($_POST['send_to'] ?? '')));
                exit();
            }
        }
        
        // The gate at the top of the file covers the folder the page is in
        // now; moving the page needs edit rights to the destination as well,
        // the same check add_page.php makes for a new page.
        if (
            ((int) $current_page_folder != (int) ($_POST['folder'] ?? 0))
            && (check_edit_access($_POST['folder'] ?? '') == false)
        ) {
            log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
            output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // Whether this user may set a page to the given type: roles above
        // basic user may set any type, a basic user needs the matching flag.
        // add_page.php applies the same list to a new page.
        $user_can_set_page_type = function ($page_type) use ($user) {
            return (
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
            );
        };

        // A type the software does not know would not fit the page_type
        // column, so the page keeps its current type instead. The value is
        // read from $_POST in every step below, so it is corrected there.
        if (get_page_type_name((string) ($_POST['type'] ?? '')) === null) {
            $_POST['type'] = $current_page_type;
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

            // An e-mail switched on with nothing to send, or a "Next Page"
            // confirmation without a page (pg_cf_legacy_settings_errors()).
            $custom_form_setting_errors = pg_cf_legacy_settings_errors(
                pg_page_form_type_properties_from_post('custom form', (int) $_POST['id']),
                function ($page_id) {
                    return ($page_id > 0) && (db_value("SELECT COUNT(*) FROM page WHERE page_id = '" . (int) $page_id . "'") > 0);
                });

            if ($custom_form_setting_errors) {
                foreach ($custom_form_setting_errors as $custom_form_setting_field => $custom_form_setting_error) {
                    $liveform->mark_error($custom_form_setting_field, $custom_form_setting_error);
                }
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . (int) $_POST['id'] . '&send_to=' . urlencode((string) ($_POST['send_to'] ?? '')));
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
        
        // If the user may set the current page type, and the type the page is
        // being changed to as well, then prepare to save page type. The second
        // test is what keeps a basic user from turning a standard page into a
        // type they were never given.
        if (
            $user_can_set_page_type($current_page_type)
            && (($_POST['type'] == $current_page_type) || $user_can_set_page_type($_POST['type']))
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
            
            // The current form flags are only looked up inside the matching
            // page type case below, but the form designer redirect test reads
            // them for every page type.
            $current_shipping_address_and_arrival_form = '';
            $current_billing_information_form = '';

            // if we can update the page type, then update page type and tag cloud table if needed
            if ($update_page_type == true) {
                // if the page type was changed and the original page type was a search results page type, then remove the keywords for the products and product groups if there are any to remove
                if (($current_page_type != $_POST['type']) && ($current_page_type == 'search results')) {
                    delete_tag_cloud_keywords_for_search_results_page($_POST['id']);
                }
                
                $properties = pg_page_form_type_properties_from_post($_POST['type'], $_POST['id']);

                switch ($_POST['type']) {
                    case 'custom form':
                        $new_contact_group_id = $properties['contact_group_id'];
                        
                        // if user has a user role, then verify that user has access to contact group that was selected
                        if ($user['role'] == 3) {
                            // get current contact group id
                            $current_contact_group_id = db_value(
                                "SELECT contact_group_id
                                FROM custom_form_pages
                                WHERE page_id = '" . escape($_POST['id'] ?? '') . "'");
                            
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

                        $properties['contact_group_id'] = $new_contact_group_id;

                        $new_private_folder_id = $properties['private_folder_id'];
                        
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

                        $properties['private_folder_id'] = $new_private_folder_id;

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
                            if (USER_MANAGE_ECOMMERCE || !($_POST['custom_form_offer'] ?? '')) {
                                $properties['offer'] = $_POST['custom_form_offer'] ?? 0;

                            // Otherwise the user does not have access to commerce, so save old value.
                            } else {
                                $properties['offer'] = ($old_properties['offer'] ?? '');
                            }

                            // If the user has access to commerce or the user just selected the blank offer,
                            // then save the offer id from the user.
                            if (USER_MANAGE_ECOMMERCE || !($_POST['custom_form_offer_id'] ?? '')) {
                                $properties['offer_id'] = $_POST['custom_form_offer_id'] ?? '';

                            // Otherwise the user does not have access to commerce, so save old value.
                            } else {
                                $properties['offer_id'] = ($old_properties['offer_id'] ?? '');
                            }

                            $properties['offer_days'] = $_POST['custom_form_offer_days'] ?? '';
                            $properties['offer_eligibility'] = $_POST['custom_form_offer_eligibility'] ?? '';
                        }
                        
                        break;
                    
                    case 'form list view':
                    case 'form item view':
                        $new_custom_form_page_id = $properties['custom_form_page_id'];
                        $page_type_table_name = str_replace(' ', '_', $_POST['type']) . '_pages';
                        
                        // if user has a user role, then verify that user has access to custom form that was selected
                        if ($user['role'] == 3) {
                            // get current custom form
                            $current_custom_form_page_id = db_value(
                                "SELECT custom_form_page_id
                                FROM $page_type_table_name
                                WHERE
                                    (page_id = '" . escape($_POST['id'] ?? '') . "')
                                    AND (collection = 'a')");
                            
                            // get folder of new custom_form
                            $new_custom_form_folder_id = db_value(
                                "SELECT page_folder
                                FROM page
                                WHERE page_id = '" . escape($new_custom_form_page_id) . "'");
                            
                            // if custom form is trying to be changed and user does not have access to custom form, don't allow custom form to be changed
                            if (($new_custom_form_page_id != $current_custom_form_page_id) && (check_edit_access($new_custom_form_folder_id) == false)) {
                                $new_custom_form_page_id = $current_custom_form_page_id;

                                if ($_POST['type'] == 'form list view') {
                                    $liveform->mark_error('', lang('The custom form for the form list view could not be changed, because you do not have access to the custom form you selected.'));
                                    log_activity(lang('access denied to change custom form for form list view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                                } else {
                                    $liveform->mark_error('', lang('The custom form for the form item view could not be changed, because you do not have access to the custom form you selected.'));
                                    log_activity(lang('access denied to change custom form for form item view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                                }
                            }
                        }
                        
                        $properties['custom_form_page_id'] = $new_custom_form_page_id;

                        // if there is not a record for this form list view yet, then set default values
                        if (
                            ($_POST['type'] == 'form list view')
                            && (db_value("SELECT COUNT(*) FROM form_list_view_pages WHERE page_id = '" . escape($_POST['id'] ?? '') . "'") == 0)
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
                            WHERE page_id = '" . e($_POST['id'] ?? '') . "'");

                        if ($current_default_view != $properties['default_view']) {
                            unset($_SESSION['software']['calendar_views'][$_POST['id']]['view']);
                        }
                        
                        break;
                    
                    case 'express order':
                        // Get current form values before we update it, so that we know later
                        // if we should forward the user to the form designer or not.
                        $old_properties = db_item(
                            "SELECT shipping_form, form FROM express_order_pages
                            WHERE page_id = '" . e($_POST['id'] ?? '') . "'");

                        break;

                    case 'shipping address and arrival':
                        // get current form value before we update it, so that we know later if we should forward the user to the form designer or not
                        $current_shipping_address_and_arrival_form = db_value("SELECT form FROM shipping_address_and_arrival_pages WHERE page_id = '" . escape($_POST['id'] ?? '') . "'");
                        
                        break;

                    case 'billing information':
                        // Get current form value before we update it, so that we know later
                        // if we should forward the user to the form designer or not.
                        $current_billing_information_form = db_value("SELECT form FROM billing_information_pages WHERE page_id = '" . escape($_POST['id'] ?? '') . "'");

                        break;
                }

                pg_page_form_save_type_links($_POST['type'], $_POST['id'], $user);
                
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
        
        // if sitemap was enabled and the selected page type is a valid page type for the sitemap,
        // then include this page in the sitemap
        if (
            (($_POST['sitemap'] ?? 0) == 1)
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
        
        $noindex = 0;
        $nofollow = 0;

        // nofollow qualifies the noindex directive and is never emitted on its
        // own, so it is only stored while the page is closed to search engines.
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

        $sql_noindex = "";

        if (pg_page_noindex_ready() == TRUE) {
            $sql_noindex =
                "noindex = '" . $noindex . "',
                nofollow = '" . $nofollow . "',";
        }

        $sql_seo_extras = "";

        if ($seo_extras_sent) {
            $sql_seo_extras =
                "canonical_url = '" . e($canonical_check['value']) . "',
                noarchive = '" . ((($_POST['noarchive'] ?? '') == 1) ? 1 : 0) . "',
                max_snippet = " . (($max_snippet_check['value'] === null) ? "NULL" : "'" . (int) $max_snippet_check['value'] . "'") . ",";
        }

        $sql_seo_analysis_current = "";

        // If the seo analysis is current and any field the SEO score reads
        // has changed, then prepare to clear the current status. Beyond the
        // title and description: the promoted keywords feed the site search
        // check, the name feeds the URL check, and the folder, search and
        // sitemap switches decide which checks apply to the page at all.
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
            )
        ) {
            $sql_seo_analysis_current = "seo_analysis_current = '0',";
        }

        // Which of the two texts is an accepted "Write with AI" draft as it
        // was used: the columns the screen posts, each marked on the text
        // saved now; without a list, the stored marks whose text this save
        // leaves as it was. An edited or emptied text loses its mark
        // (pg_ai_fields_after_save()).
        $sql_ai_fields = "";

        if (pg_ai_fields_ready('page')) {
            $ai_fields = pg_ai_fields_after_save('page', $_POST['ai_fields'] ?? null, $current_ai_fields, array(
                'page_title' => $_POST['title'] ?? '',
                'page_meta_description' => $_POST['meta_description'] ?? '',
            ));

            $sql_ai_fields = "ai_fields = " . (($ai_fields === null) ? "NULL" : "'" . e($ai_fields) . "'") . ",";
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
                $sql_noindex
                $sql_seo_extras
                $sql_ai_fields
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
        
        // Score the page now rather than leaving it for a list visit or the
        // nightly job. Every field the meta half reads was just written by the
        // UPDATE above, and the operator is one click away from the card that
        // shows the number - a card that would otherwise still be showing the
        // score of what they just replaced.
        //
        // The markup half is not run here: nothing on this screen changes it.
        // Content is saved by save_region_content.php, which runs it there.
        if (pg_seo_schema_ready()) {
            pg_seo_recalculate('page', array((int) $page_id));
        }

        log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('page'), $name))), $_SESSION['sessionusername']);

        pg_audit_record('update', 'page', $page_id, $audit_before);
        
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
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($send_to));
        }
    }
}