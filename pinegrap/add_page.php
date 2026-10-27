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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

$liveform_add_page = new liveform('add_page');

$user = validate_user();
validate_area_access($user, 'user');

// if the user has a user role and has create pages turned off, then output error
if (($user['role'] == '3') && ($user['create_pages'] == FALSE)) {
    log_activity(lang("access denied because user does not have access to create pages"), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

if (!$_POST) {
    // if user role is Administrator, Designer, or Manager, then allow user to select style and mobile style for page
    if ($user['role'] < 3) {
        $output_style = '<select class="form-control" id="style" name="style">' . select_style() . '</select>';
        $output_mobile_style = '<select class="form-control" id="mobile_style_id" name="mobile_style_id">' . get_mobile_style_options() . '</select>';

    // else user has a user role
    } else {
        $output_style = lang('Default') . ' (' . lang('inherit') . ')';
        $output_mobile_style = lang('Default') . ' (' . lang('inherit') . ')';
    }

    // NOTE: $page_folder is never assigned anywhere in this script, so the folder picklist has
    // no preselected folder.  Empty keeps that behaviour and stops the warning.
    $page_folder = '';

    // Search engine indexing. The block is only built where the columns behind
    // it exist: a switch that saves nothing is worse than no switch at all.
    // A new page starts open to search engines, so both switches start off and
    // nofollow starts unavailable, the way the master switch leaves it.
    $output_noindex_rows = '';

    if (pg_page_noindex_ready() == TRUE) {
        $output_noindex_rows =
            '<div class="col-12 my-5" id="noindex_row">
                <div class="form-field">
                    <div class="switch"><input class="" type="checkbox" id="noindex" name="noindex" value="1"></div>
                    <label class="" for="noindex">' . lang('Close to Search Engines (noindex)') . '</label>
                </div>
                <div class="form-text">' . lang('The page is served with a noindex robots tag, is blocked in robots.txt and is left out of the site map.') . '</div>
                <div class="form-field mt-5 ms-7">
                    <div class="switch"><input class="" type="checkbox" id="nofollow" name="nofollow" value="1" disabled="disabled"></div>
                    <label class="" for="nofollow">' . lang('Do Not Follow Links on This Page (nofollow)') . '</label>
                </div>
                <div class="form-text ms-7">' . lang('Leave this off so that search engines keep discovering the items this page links to. Turn it on for a widget page whose links already appear on a page that is indexed.') . '</div>
            </div>';
    }

    // A new page opens on the standard type; the other types' rows are
    // printed hidden with the defaults of a new page and shown by the type
    // picker.
    $page_type_fields = pg_page_form_type_fields('standard', 'system', pg_page_form_new_page_properties(), $user);
    $output_wysiwyg_editor_code = $page_type_fields['wysiwyg'];

    echo
    pg_page_shell(
        array(
            'title'=> lang('Create Page'),
            'extra classes'=>'page',
            'icon'=>'page',
            'heading'=>lang('Create Page'),
            'heading_description' => lang('Create a new page, place it in a folder, and add any built-in features.'),
            'cancel'=>array('enable'=>'true','url'=>'view_pages.php'),
            'breadcrumb' => array(
                array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
                array('label' => lang('Create Page')),
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
                ' . $liveform_add_page->output_errors() . '
                ' . $liveform_add_page->output_notices() . '
                
                <form name="form" action="add_page.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        ' . $page_type_fields['selector'] . '
                        <div class="col-12 md:col-8 lg:col-9 xl:col-10">
                            <div class="row">
                                <div class="col-12">
                                    <div class="card my-7 position-sticky" style="top:56px;">
                                        <label for="name" class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Main Informations') . '
                                        </label>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 md:col-8 lg:col-6 my-3">
                                                    <div class="input-group ">
                                                        <label for="name" class="input-group-text material-icons" title="' . lang('This option determines the url address of the page.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Page Name') . '}">public</label>
                                                        ' . $liveform_add_page->output_field(array('type'=>'text','id'=>'name','name'=>'name','placeholder'=>lang('Page Name'),'maxlength'=>'100', 'class'=>'form-control ', 'required'=>'required')) . '
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="card my-7">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                            ' . lang('Content Options') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 md:col-6 my-3">
                                                    <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                                    <select class="form-control" id="folder" name="folder">' . select_folder($page_folder) . '</select>
                                                    <div class="form-text">' . lang('Page Access Control, Design &amp; Common Content') . '</div>
                                                </div>
                                            </div>
                                            <div class="row mt-7">
                                                <div class="col-12 md:col-6 lg:col-auto my-3">
                                                    <label for="style" class="form-label">' . lang('Desktop Page Style') . '</label>
                                                    ' . $output_style . '
                                                </div>
                                                <div class="col-12 md:col-6 lg:col-auto my-3">
                                                    <label for="mobile_style_id" class="form-label">' . lang('Mobile Page Style') . '</label>
                                                    ' . $output_mobile_style . '
                                                </div>
                                                <div class="col-12 form-text">' . lang('Override Folder\'s Default Page Styles') . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                ' . $page_type_fields['properties'] . '
                                <div class="col-12 my-5">
                                    <div class="card my-7">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                            ' . lang('Search Engine Optimization') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 my-3" id="sitemap_row">
                                                    <div class="form-field">
                                                        <div class="switch"><input class="" type="checkbox" id="sitemap" name="sitemap" value="1" checked="checked"></div>
                                                        <label class="" for="sitemap">' . lang('Include in Site Map?') . '</label>
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
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    <script>
    // Indexing switch dependencies — logic in assets/js/backend.src.js
    bindPageIndexingSwitches();
    </script>
</main>' .
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
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_page.php');
        exit();
    }
    
    // if page is a custom form, check to see if there is another page with this same form name
    if ($_POST['type'] == 'custom form') {
        $query = "SELECT id FROM custom_form_pages WHERE form_name = ?";
        $result = db_q($query, array((string) ($_POST['custom_form_form_name'] ?? ''))) or output_error('Query failed.');

        // if there is another page with this form name, output error
        if (mysqli_num_rows($result) > 0) {
            $liveform_add_page->mark_error('custom_form_form_name', lang('The form name that you entered is already in use. Please enter a different form name.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_page.php');
            exit();
        }

        // An e-mail switched on with nothing to send, or a "Next Page"
        // confirmation without a page (pg_cf_legacy_settings_errors()).
        $custom_form_setting_errors = pg_cf_legacy_settings_errors(
            pg_page_form_type_properties_from_post('custom form', 0),
            function ($page_id) {
                return ($page_id > 0)
                    && (db_value("SELECT COUNT(*) FROM page WHERE page_id = '" . (int) $page_id . "'") > 0)
                    && !(function_exists('pg_recycle_page_binned') && pg_recycle_page_binned((int) $page_id));
            });

        if ($custom_form_setting_errors) {
            foreach ($custom_form_setting_errors as $custom_form_setting_field => $custom_form_setting_error) {
                $liveform_add_page->mark_error($custom_form_setting_field, $custom_form_setting_error);
            }
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_page.php');
            exit();
        }
    }

    $sql_style_fields = "";
    $sql_style_values = "";
    $style_values = array();
    
    // if user role is Administrator, Designer, or Manager, then allow user to set style and mobile style for folder
    if ($user['role'] < 3) {
        $sql_style_fields =
            "page_style,
            mobile_style_id,";

        $sql_style_values =
            "?,
            ?,";

        $style_values = array((string) ($_POST['style'] ?? ''), (string) ($_POST['mobile_style_id'] ?? ''));
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
    // and starts outside the site search. Both literals have to match their
    // column types - page_home is ENUM('no','yes') (a numeric string would be
    // read as an enum index) and page_search is an INT column - because a
    // server running with STRICT_TRANS_TABLES, the default since MySQL 5.7 and
    // MariaDB 10.2, rejects a mistyped value and page creation fails on the
    // query.
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
            ?,
            ?,
            ?,
            ?,
            'no',
            '0',
            '',
            UNIX_TIMESTAMP(),
            ?,
            $sql_style_values
            '',
            '',
            ?,
            $sql_noindex_values
            '" . $sitemap . "')";
    $result = db_q($query, array_merge(
        array(
            (string) $name,
            (string) ($_POST['folder'] ?? ''),
            (string) $type,
            (string) $layout_type,
            (string) $user['id'],
        ),
        $style_values,
        array((string) lang('We\'re sorry. New comments are no longer being accepted.'))
    )) or output_error('Query failed.');
    $page_id = mysqli_insert_id(db::$con);

    // A page that did not exist before this request. Announced here and
    // not in the duplicate or import paths: those create pages in bulk and
    // an integration learns more from one listing than from fifty events.
    pg_announce('page.created', array('id' => (int) $page_id, 'name' => $name));
    
    // set page type properties, if necessary
    $properties = pg_page_form_type_properties_from_post($type, $page_id);

    switch ($type) {
        case 'custom form':
            // if user has a user role,
            // and a contact group was selected
            // and user does not have access to contact group,
            // then don't allow contact group to be changed
            if (($user['role'] == 3) && ($properties['contact_group_id'] != 0) && (validate_contact_group_access($user, $properties['contact_group_id']) == false)) {
                $properties['contact_group_id'] = 0;
                log_activity(lang('access denied to set contact group for custom form because user did not have access to contact group'), $_SESSION['sessionusername']);
            }

            // If the user selected a private folder that he/she does not have edit access to,
            // then don't allow folder to be set and log activity.
            if ($properties['private_folder_id'] && (check_edit_access($properties['private_folder_id']) == false)) {
                $properties['private_folder_id'] = 0;
                log_activity(lang('access denied to set private folder for custom form because user did not have edit access to folder'), $_SESSION['sessionusername']);
            }

            // If commerce is enabled and the user has access to commerce, then save offer properties.
            if ((ECOMMERCE) && (USER_MANAGE_ECOMMERCE)) {
                $properties['offer'] = ($_POST['custom_form_offer'] ?? 0);
                $properties['offer_id'] = $_POST['custom_form_offer_id'] ?? '';
                $properties['offer_days'] = $_POST['custom_form_offer_days'] ?? '';
                $properties['offer_eligibility'] = $_POST['custom_form_offer_eligibility'] ?? '';
            }

            break;

        case 'form list view':
        case 'form item view':
            // if user has a user role, then verify that user has access to custom form that was selected
            if ($user['role'] == 3) {
                // get folder of custom form
                $custom_form_folder_id = db_value(
                    "SELECT page_folder
                    FROM page
                    WHERE page_id = '" . escape($properties['custom_form_page_id']) . "'");

                // if the user does not have access to custom form, don't allow custom form to be changed
                if (check_edit_access($custom_form_folder_id) == false) {
                    $properties['custom_form_page_id'] = 0;
                    if ($type == 'form list view') {
                        log_activity(lang('access denied to set custom form for form list view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                    } else {
                        log_activity(lang('access denied to set custom form for form item view because user did not have access to modify folder that custom form was in'), $_SESSION['sessionusername']);
                    }
                }
            }

            // A new form list view starts with search on, its results shown
            // and 25 to a page.
            if ($type == 'form list view') {
                $properties['maximum_number_of_results_per_page'] = 25;
                $properties['search'] = 1;
                $properties['search_label'] = lang('Search');
                $properties['show_results_by_default'] = 1;
            }

            break;
    }

    pg_page_form_save_type_links($type, $page_id, $user);

    // if page type has a table for properties, create record in page type table
    if (check_for_page_type_properties($type) == true) {
        create_or_update_page_type_record($type, $properties);
    }

    // get style so that we can create regions
    // if default was selected for style
    if (($_POST['style'] ?? 0) == 0) {
        $style = get_style($_POST['folder']);
    // else default was not selected
    } else {
        $style = $_POST['style'];
    }

    // get style code
    $result = db_q("SELECT style_code FROM style WHERE style_id = ?", array((string) $style)) or output_error('Query failed');
    $row = mysqli_fetch_assoc($result);
    $style_code = $row['style_code'];

    // create regions
    $pregion_count = 1;
    preg_match_all('/<pregion>.*?<\/pregion>/i', $style_code, $regions);
    foreach ($regions[0] as $region)
    {
        $region_name = time() .'_' . $pregion_count;
        $query = "INSERT INTO pregion (pregion_name, pregion_content, pregion_page, pregion_order, pregion_user, pregion_timestamp) VALUES (?, '', ?, ?, ?, UNIX_TIMESTAMP())";
        $result = db_q($query, array((string) $region_name, (string) $page_id, (string) $pregion_count, (string) $user['id'])) or output_error('Query failed');
        $pregion_count++;
    }

    log_activity(lang(array('string'=>'page ({var:1}) was created','vars'=>$name)), $_SESSION['sessionusername']);

    pg_audit_record('create', 'page', (int) $page_id);

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