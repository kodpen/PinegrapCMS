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
$user = validate_user();
validate_area_access($user, 'user');

include_once('liveform.class.php');
$liveform = new liveform('edit_short_link');

// Get short link data that we will use later.
$query =
    "SELECT 
        short_links.id,
        short_links.name,
        short_links.destination_type,
        short_links.page_id,
        short_links.product_group_id,
        short_links.product_id,
        short_links.url,
        short_links.file_id,
        short_links.tracking_code,
        short_links.created_user_id,
        page.page_folder AS folder_id,
        page.page_type
    FROM short_links
    LEFT JOIN page ON short_links.page_id = page.page_id
    WHERE short_links.id = '" . escape($_REQUEST['id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$short_link = mysqli_fetch_assoc($result);
$short_link_file_id = $short_link['file_id'];


// If this user has a user role then determine if user has access to short link.
// A user has access to a short link if he/she has edit rights to the short link's page
// or for url type: created the short link.
if (USER_ROLE == 3) {
    // Determine if the user has access to the short link differently based on the destination type.
    switch ($short_link['destination_type']) {
        default:
            // If the user does not have edit access to the page's folder, then output error.
            if (check_edit_access($short_link['folder_id']) == false) {
                log_activity(lang('access denied to edit short link because user does not have edit rights to page'), $_SESSION['sessionusername']);
                output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }

            break;

        case 'url':
            // If this user is not the user that created the short link, then output error.
            if (USER_ID != $short_link['created_user_id']) {
                log_activity(lang('access denied to edit short link because user did not create short link'), $_SESSION['sessionusername']);
                output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }

            break;
    }
}

// If the form was not just submitted then output form.
if (!$_POST) {
    // If the form has not been submitted at all yet, pre-populate fields with data.
    if ($liveform->field_in_session('id') == FALSE) {
        $liveform->assign_field_value('name', $short_link['name']);
        $liveform->assign_field_value('destination_type', $short_link['destination_type']);

        // Set the page field differently based on the destination type.
        switch ($liveform->get_field_value('destination_type')) {
            case 'page':
                $liveform->assign_field_value('page_id', $short_link['page_id']);
                break;
            
            case 'product_group':
                // If the page is a catalog page, then set page for that field.
                if ($short_link['page_type'] == 'catalog') {
                    $liveform->assign_field_value('catalog_page_id', $short_link['page_id']);

                // Otherwise the page is a catalog detail page, so set page for that field.
                } else {
                    $liveform->assign_field_value('catalog_detail_page_id', $short_link['page_id']);
                }

                break;

            case 'product':
                $liveform->assign_field_value('catalog_detail_page_id', $short_link['page_id']);
                break;

            case 'url':
                $liveform->assign_field_value('url', $short_link['url']);
                break;

            case 'file':
                $liveform->assign_field_value('file', $short_link['file_id']);
                break;
        }

        $liveform->assign_field_value('product_group_id', $short_link['product_group_id']);
        $liveform->assign_field_value('product_id', $short_link['product_id']);
        $liveform->assign_field_value('tracking_code', $short_link['tracking_code']);
    }


    $destination_type_options = array(
        '-' . lang(array('string'=>'Select {var:1}','vars'=>lang('destination type'))) . '-' => '',
        lang('Page') => 'page',
        lang('Product Group') => 'product_group',
        lang('Product') => 'product',
        lang('URL') => 'url',
        lang('File') => 'file');

    // Hide certain fields until we know which should be shown.
    $output_page_id_row_style = ' style="display: none"';
    $output_catalog_page_id_row_style = ' style="display: none"';
    $output_or_row_style = ' style="display: none"';
    $output_catalog_detail_page_id_row_style = ' style="display: none"';
    $output_product_group_id_row_style = ' style="display: none"';
    $output_product_id_row_style = ' style="display: none"';
    $output_url_row_style = ' style="display: none"';
    $output_tracking_code_row_style = ' style="display: none"';
    $output_file_row_style = ' style="display: none"';

    switch ($liveform->get_field_value('destination_type')) {
        case 'page':
            $output_page_id_row_style = '';
            $output_tracking_code_row_style = '';
            break;
        
        case 'product_group':
            $output_catalog_page_id_row_style = '';
            $output_or_row_style = '';
            $output_catalog_detail_page_id_row_style = '';
            $output_product_group_id_row_style = '';
            $output_tracking_code_row_style = '';
            break;

        case 'product':
            $output_catalog_detail_page_id_row_style = '';
            $output_product_id_row_style = '';
            $output_tracking_code_row_style = '';
            break;

        case 'url':
            $output_url_row_style = '';
            break;

        case 'file':
            $output_file_row_style = '';
            break;
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Short Link'),
            'extra classes'=>'page',
            'icon'=>'page', 
            'heading'=>lang('Edit Short Link'),
            'cancel'=>array('enable'=>'true','url'=>'view_short_links.php')
        ,
            'breadcrumb' => array(array('label' => lang('My Short Links'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_short_links.php'), array('label' => lang('Edit Short Link'))),
        )
    )  . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Update this shortcut alias for a Page, Product Group, Product, or URL.') . '" title="' . lang('Edit Short Link') . '">[' . h($short_link['name']) . ']</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 mb-2 "  href="' . OUTPUT_PATH . h($short_link['name']) . '" target="_blank"><span class="material-icons me-1">link</span>' . lang('Visit') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_short_link.php" method="post">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>$_GET['id'])) . '
                    <div class="row">
                        <div class="col-12 col-md-4 col-lg-3 col-xl-2">
                            <div class="card my-4 position-sticky" style="top:56px;">
                                <label for="type" class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Destination Type') . '
                                </label>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            ' . $liveform->output_field(array('type'=>'select', 'id'=>'destination_type', 'class'=>'form-select collapse-if-selected', 'name'=>'destination_type', 'options'=>$destination_type_options, 'onchange'=>'change_short_link_destination_type(this.options[this.selectedIndex].value)', 'data-bs-target'=>'#options_row', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            <script>
                                                $(document).ready(function() {
                                                    change_short_link_destination_type($("select#destination_type option:selected").val());
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
                                                    <label for="name" class="form-label">' . lang('Name') . '</label>
                                                    <div class="input-group ">
                                                        <label for="name" class="input-group-text material-icons" title="' . lang('This option determines the url address of the short link.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Short Link Name') . '}">public</label>
                                                        ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100', 'placeholder'=>lang('Short Link Name'), 'required'=>'required')) . '
                                                        <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 collapse" id="options_row">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Function Options') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-4 my-2" id="page_id_row"' . $output_page_id_row_style . '>
                                                    <label for="page_id" class="form-label">' . lang('Page') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'page_id', 'id'=>'page_id', 'class'=>'form-select', 'options'=>get_page_options())) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-5 my-2" id="catalog_page_id_row"' . $output_catalog_page_id_row_style . '>
                                                    <label for="catalog_page_id" class="form-label">' . lang('Catalog Page') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'catalog_page_id', 'id'=>'catalog_page_id', 'class'=>'form-select', 'options'=>get_page_options('', 'catalog'), 'onclick'=>'if (this.selectedIndex != 0) {document.getElementById(\'catalog_detail_page_id\').selectedIndex = 0}' )) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 col-lg-2 my-2 text-lg-center" id="or_row"' . $output_or_row_style . '>
                                                -' . lang('or') . '-
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-5 my-2" id="catalog_detail_page_id_row"' . $output_catalog_detail_page_id_row_style . '>
                                                    <label for="catalog_detail_page_id" class="form-label">' . lang('Catalog Detail Page') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'catalog_detail_page_id', 'id'=>'catalog_detail_page_id', 'class'=>'form-select', 'options'=>get_page_options('', 'catalog detail'), 'onclick'=>'if (this.selectedIndex != 0) {document.getElementById(\'catalog_page_id\').selectedIndex = 0}' )) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-5 my-2" id="product_group_id_row"' . $output_product_group_id_row_style . '>
                                                    <label for="product_group_id" class="form-label">' . lang('Product Group') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_group_id', 'id'=>'product_group_id', 'class'=>'form-select', 'options'=>get_product_group_options($product_group_id = 0, $parent_product_group_id = 0, $excluded_product_group_id = 0, $level = 0, $product_groups = array(), $include_select_product_groups = TRUE, $format = 'array', $include_blank_option = TRUE))) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-7 my-2" id="product_id_row"' . $output_product_id_row_style . '>
                                                    <label for="product_id" class="form-label">' . lang('Product') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_id', 'id'=>'product_id', 'class'=>'form-select', 'options'=>get_product_options())) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 col-md-8 col-lg-6 my-2" id="url_row"' . $output_url_row_style . '>
                                                    <label for="url" class="form-label">' . lang('Destination URL') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text', 'name'=>'url', 'id'=>'url', 'class'=>'form-control', 'maxlength'=>'255', 'placeholder' => lang('Enter URL that visitor should be redirected to'))) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg my-2" id="tracking_code_row"' . $output_tracking_code_row_style . '>
                                                    <label for="tracking_code" class="form-label">' . lang('Tracking Code') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text', 'name'=>'tracking_code', 'id'=>'tracking_code', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4 my-2" id="file_row"' . $output_file_row_style . '>
                                                     <label for="file" class="form-label">' . lang('File') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'file', 'id'=>'file', 'class'=>'form-select', 'options'=>get_file_options($design=true))) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
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
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('short link')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();

    $liveform->remove_form();

// Otherwise the form was just submitted, so process it.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();

    $handle = @fopen(HTACCESS_FILE_PATH, 'a');

    // If the rewrite file does not exist or can not be written to, then add error and send user back to previous screen.
    if ($handle == FALSE) {
        $liveform->mark_error('', lang(array('string'=>'{var:1} is not writeable so your request could not be completed.','vars'=>HTACCESS_FILE_NAME)) );
        header('Location: ' . URL_SCHEME . HOSTNAME . $_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        exit();

    // Otherwise the rewrite file is writeable, so close handle.
    } else {
        @fclose($handle);
    }

    // If the user selected to delete this short link, then delete it.
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        $query = "DELETE FROM short_links WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity( lang(array('string'=>'short link ({var:1}) was deleted','vars'=>$short_link['name'])), $_SESSION['sessionusername']);
        
        $liveform->remove_form();

        $liveform_view_short_links = new liveform('view_short_links');
        $liveform_view_short_links->add_notice(lang('The short link has been deleted.'));
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_short_links.php');

    // Otherwise the user selected to save the short link, so save it.
    } else {
        $liveform->validate_required_field('name', lang('Name is required.'));
        $name = $liveform->get_field_value('name');

        // Replace spaces with underscores for the name.
        $name = str_replace(' ', '_', $name);

        // Update name in liveform.
        $liveform->assign_field_value('name', $name);

        // If there is not already an error for the name field and it contains invalid characters, then add error.
        if (
            ($liveform->check_field_error('name') == FALSE)
            && (preg_match('/[^A-Za-z0-9._\-\/]/', $name) == 1)
        ) {
            $liveform->mark_error('name', lang('The name may only contain letters, numbers, periods, underscores, hyphens, and forward slashes.'));
        }

        // If there is not already an error for the name field,
        // and the name is already in use, then add error.
        if (
            ($liveform->check_field_error('name') == false)
            && (check_name_availability(array('name' => $name, 'ignore_item_id' => $liveform->get_field_value('id'), 'ignore_item_type' => 'short_link')) == false)
        ) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }

        $liveform->validate_required_field('destination_type', lang('Destination Type is required.'));

        // If there is not already an error for the destination type and the destination type is invalid, then add error.
        if (
            ($liveform->check_field_error('destination_type') == FALSE)
            &&
            (
                ($liveform->get_field_value('destination_type') != 'page')
                && ($liveform->get_field_value('destination_type') != 'product_group')
                && ($liveform->get_field_value('destination_type') != 'product')
                && ($liveform->get_field_value('destination_type') != 'url')
                && ($liveform->get_field_value('destination_type') != 'file')
            )
        ) {
            $liveform->mark_error('destination_type', lang('The destination type is not valid.'));
        }

        // If there is not already an error for the destination type, then validate other fields.
        if ($liveform->check_field_error('destination_type') == FALSE) {
            // Validate other fields differently based on the destination type.
            switch ($liveform->get_field_value('destination_type')) {
                case 'page':
                    $liveform->validate_required_field('page_id', lang('Page is required.'));
                    break;

                case 'product_group':
                    // If neither a catalog page or a catalog detail page was selected, then add error.
                    if (
                        ($liveform->get_field_value('catalog_page_id') == '')
                        && ($liveform->get_field_value('catalog_detail_page_id') == '')
                    ) {
                        $liveform->mark_error('catalog_page_id', lang('Catalog Page or Catalog Detail Page is required.'));
                        $liveform->mark_error('catalog_detail_page_id', '');
                    }

                    // If both a catalog page and a catalog detail page were selected, then add error.
                    if (
                        ($liveform->get_field_value('catalog_page_id') != '')
                        && ($liveform->get_field_value('catalog_detail_page_id') != '')
                    ) {
                        $liveform->mark_error('catalog_page_id', lang('Please select either a Catalog Page or a Catalog Detail Page, not both.'));
                        $liveform->mark_error('catalog_detail_page_id', '');
                    }

                    $liveform->validate_required_field('product_group_id', lang('Product Group is required.'));

                    // If there is not already an error for the product group, then check if product group exists.
                    if ($liveform->check_field_error('product_group_id') == FALSE) {
                        $query = "SELECT COUNT(*) FROM product_groups WHERE id = '" . escape($liveform->get_field_value('product_group_id')) . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        $row = mysqli_fetch_row($result);

                        // If the selected product group does not exist, then add error.
                        if ($row[0] == 0) {
                            $liveform->mark_error('product_group_id', lang('The product group does not exist.'));
                        }
                    }
                
                    break;
                
                case 'product':
                    $liveform->validate_required_field('catalog_detail_page_id', lang('Catalog Detail Page is required.'));
                    $liveform->validate_required_field('product_id', 'Product is required.');

                    // If there is not already an error for the product, then check if product exists.
                    if ($liveform->check_field_error('product_id') == FALSE) {
                        $query = "SELECT COUNT(*) FROM products WHERE id = '" . escape($liveform->get_field_value('product_id')) . "'";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        $row = mysqli_fetch_row($result);

                        // If the selected product does not exist, then add error.
                        if ($row[0] == 0) {
                            $liveform->mark_error('product_id', lang('The product does not exist.'));
                        }
                    }

                    break;

                case 'url':
                    $liveform->validate_required_field('url', lang('Destination URL is required.'));

                    // If there is not already an error for the url field
                    // and the url contains white-space, then output error.
                    // We have to do this to prevent outputting invalid code to the rewrite file
                    // which would cause all URL's at the site to stop working.
                    if (
                        ($liveform->check_field_error('url') == false)
                        && (preg_match('/\s/', $liveform->get_field_value('url')) == 1)
                    ) {
                        $liveform->mark_error('url', lang('Sorry, you may not enter a space in the URL.'));
                    }

                    break;

                case 'file':
                    $liveform->validate_required_field('file', lang('Destination URL is required.'));
                    break;
            }
        }

        $page_id = 0;

        // If this is a certain destination type then check selected page in different ways.
        if (
            ($liveform->get_field_value('destination_type') == 'page')
            || ($liveform->get_field_value('destination_type') == 'product_group')
            || ($liveform->get_field_value('destination_type') == 'product')
        ) {
            // If there is not already an error, then prepare values that we will need below.
            if ($liveform->check_form_errors() == FALSE) {
                switch ($liveform->get_field_value('destination_type')) {
                    case 'page':
                        $page_field_name = 'page_id';
                        break;

                    case 'product_group':
                        // If a catalog page was selected, then remember that.
                        if ($liveform->get_field_value('catalog_page_id') != '') {
                            $page_field_name = 'catalog_page_id';

                        // Otherwise a catalog detail page was selected, so remember that.
                        } else {
                            $page_field_name = 'catalog_detail_page_id';
                        }
                    
                        break;
                    
                    case 'product':
                        $page_field_name = 'catalog_detail_page_id';
                        break;
                }

                $page_id = $liveform->get_field_value($page_field_name);
            }

            // If there is not already an error, then check if page exists.
            if ($liveform->check_form_errors() == FALSE) {
                $query = "SELECT COUNT(*) FROM page WHERE page_id = '" . escape($page_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_row($result);

                // If the selected page does not exist, then add error.
                if ($row[0] == 0) {
                    $liveform->mark_error($page_field_name, lang('The page does not exist.'));
                }
            }

            // If there is not already an error and the user has a user role,
            // then check if user has edit rights to page.
            if (
                ($liveform->check_form_errors() == FALSE)
                && (USER_ROLE == 3)
            ) {
                // Get the page's folder in order to check if the user has edit rights to the page.
                $query = "SELECT page_folder AS folder_id FROM page WHERE page_id = '" . escape($page_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $folder_id = $row['folder_id'];

                // If the user does not have edit rights to the page's folder, then log activity and add error.
                if (check_edit_access($folder_id) == false) {
                    
                    log_activity(lang('access denied to edit short link for page because user does not have edit rights to page'), $_SESSION['sessionusername']);
                    $liveform->mark_error($page_field_name, lang('Sorry, you do not have access to that page.'));
                }
            }
        }
        
        // If there is an error, forward user back to previous screen.
        if ($liveform->check_form_errors() == TRUE) {
            header('Location: ' . URL_SCHEME . HOSTNAME . $_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
            exit();
        }

        $product_group_id = '';
        $product_id = '';
        $url = '';
        $file_id = '';
        // Prepare the SQL differently based on the destination type.
        switch ($liveform->get_field_value('destination_type')) {
            case 'product_group':
                $product_group_id = $liveform->get_field_value('product_group_id');
                break;
            
            case 'product':
                $product_id = $liveform->get_field_value('product_id');
                break;

            case 'url':
                $url = $liveform->get_field_value('url');
                break;

            case 'file':
                $file_id = $liveform->get_field_value('file');
            break;
        }

        // Update short link.
        $query =
            "UPDATE short_links
            SET
                name = '" . escape($name) . "',
                destination_type = '" . escape($liveform->get_field_value('destination_type')) . "',
                page_id = '" . escape($page_id) . "',
                product_group_id = '" . escape($product_group_id) . "',
                product_id = '" . escape($product_id) . "',
                url = '" . escape($url) . "',
                tracking_code = '" . escape($liveform->get_field_value('tracking_code')) . "',
                file_id = '" . escape($file_id) . "',
                last_modified_user_id = '" . USER_ID . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        

        log_activity(lang(array('string'=>'short link ({var:1}) was modified','vars'=>$name)), $_SESSION['sessionusername']);


        $liveform->remove_form();
        $liveform_view_short_links = new liveform('view_short_links');
        $liveform_view_short_links->add_notice(lang('The short link has been saved.'));

        // Forward user to view short links screen.
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_short_links.php');
    }
    
    
    exit();
}
?>