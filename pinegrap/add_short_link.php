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
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('add_short_link');

// if the form has not been submitted
if (!$_POST) {
    // Opened from the 404 log (pg_not_found_log_redirect_url()): the missing
    // address as the name, answering 301. Only a form not filled in yet takes
    // it, and the save checks the name as it checks any other.
    if (($liveform->get_field_value('name') == '') && isset($_GET['name']) && is_string($_GET['name'])) {
        $liveform->assign_field_value('name', pg_short_link_name_from_address($_GET['name']));

        if (pg_short_link_redirects_ready() && isset($_GET['redirect_code']) && is_string($_GET['redirect_code'])
            && in_array((int) $_GET['redirect_code'], pg_short_link_answer_codes(), true)) {
            $liveform->assign_field_value('redirect_code', (string) (int) $_GET['redirect_code']);
        }
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

    // How it opens (2026.4.5, 5.112): for good, once (a generated address, no
    // name to type) or until a time chosen here.
    $output_link_mode = '';

    if (pg_short_link_modes_ready()) {
        $link_mode_options = array(
            lang('Permanent: an address you name, for good') => 'permanent',
            lang('One-time: a generated address, for the first visit only') => 'once',
            lang('Timed: an address you name, until a time you choose') => 'timed');

        $duration_options = array();

        foreach (pg_short_link_durations() as $duration_seconds => $duration_label) {
            $duration_options[$duration_label] = $duration_seconds;
        }

        if ($liveform->get_field_value('link_mode') == '') {
            $liveform->assign_field_value('link_mode', 'permanent');
            $liveform->assign_field_value('duration', 604800);
        }

        $output_link_mode = '
                                                <div class="col-12 md:col-8 lg:col-6 my-3" id="link_mode_row">
                                                    <label for="link_mode" class="form-label">' . lang('How it opens') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'link_mode', 'id'=>'link_mode', 'class'=>'form-control', 'options'=>$link_mode_options, 'onchange'=>'change_short_link_mode(this.options[this.selectedIndex].value)')) . '
                                                </div>
                                                <div class="col-12 md:col-4 lg:col-3 my-3" id="duration_row" style="display: none">
                                                    <label for="duration" class="form-label">' . lang('Valid for') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'duration', 'id'=>'duration', 'class'=>'form-control', 'options'=>$duration_options)) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <script>
                                                    // A one-time link has no name: its address is made for it.
                                                    function change_short_link_mode(mode) {
                                                        var name = document.getElementById(\'name\');
                                                        document.getElementById(\'name_row\').style.display = (mode === \'once\') ? \'none\' : \'\';
                                                        document.getElementById(\'duration_row\').style.display = (mode === \'timed\') ? \'\' : \'none\';
                                                        if (mode === \'once\') { name.removeAttribute(\'required\'); } else { name.setAttribute(\'required\', \'required\'); }
                                                    }
                                                    $(document).ready(function() {
                                                        change_short_link_mode($("select#link_mode option:selected").val());
                                                    });
                                                </script>';
    }

    // How it answers (2026.11.0, 11.0.12): at its own address, as short links
    // always did, or by sending the visitor to the destination's address.
    $output_redirect_code = '';

    if (pg_short_link_redirects_ready()) {
        if ($liveform->get_field_value('redirect_code') == '') {
            $liveform->assign_field_value('redirect_code', '0');
        }

        $output_redirect_code = '
                                                <div class="col-12 md:col-8 lg:col-6 my-3" id="redirect_code_row">
                                                    <label for="redirect_code" class="form-label">' . lang('How it answers') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'redirect_code', 'id'=>'redirect_code', 'class'=>'form-control', 'options'=>pg_short_link_answer_options())) . '
                                                    <div class="form-text">' . lang('A redirect sends visitors to the destination\'s own address and keeps their query string: 301 for the old address of a page that moved, 302 for a while.') . '</div>
                                                </div>';
    }

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
            'title'=> lang('Create Short Link'),
            'extra classes'=>'page',
            'icon'=>'page',
            'heading'=>lang('Create Short Link'),
            'heading_description' => lang('Create a shortcut alias for a Page, Product Group, Product, or URL.'),
            'cancel'=>array('enable'=>'true','url'=>'view_short_links.php'),
            'breadcrumb' => array(
                array('label' => lang('My Short Links'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_short_links.php'),
                array('label' => lang('Create Short Link')),
            ),
        )
    )  . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_short_link.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12 md:col-4 lg:col-3 xl:col-2">
                            <div class="card my-7 position-sticky" style="top:56px;">
                                <label for="type" class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Destination Type') . '
                                </label>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            ' . $liveform->output_field(array('type'=>'select', 'id'=>'destination_type', 'class'=>'form-control collapse-if-selected', 'name'=>'destination_type', 'options'=>$destination_type_options, 'onchange'=>'change_short_link_destination_type(this.options[this.selectedIndex].value)', 'data-bs-target'=>'#options_row', 'required'=>'required')) . '
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
                        <div class="col-12 md:col-8 lg:col-9 xl:col-10">
                            <div class="row">
                                <div class="col-12">
                                    <div class="card my-7 position-sticky" style="top:56px;">
                                        <label for="name" class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Main Informations') . '
                                        </label>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 md:col-8 lg:col-6 my-3" id="name_row">
                                                    <label for="name" class="form-label">' . lang('Name') . '</label>
                                                    <div class="input-group ">
                                                        <label for="name" class="input-group-text material-icons" title="' . lang('This option determines the url address of the short link.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Short Link Name') . '}">public</label>
                                                        ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>pg_short_link_url_max(), 'placeholder'=>lang('Short Link Name'), 'required'=>'required')) . '
                                                        <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                    </div>
                                                    <div class="form-text">' . pg_short_link_name_rule_text() . '</div>
                                                    <div class="form-text">' . lang('An old address can be pasted as it is: only its path is kept. A name with a * is tried only where no page answers, and it always redirects.') . '</div>
                                                </div>
                                                ' . $output_link_mode . '
                                                ' . $output_redirect_code . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 collapse" id="options_row">
                                    <div class="card my-7">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                            ' . lang('Function Options') . '
                                        </div>

                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 md:col-6 lg:col-4 my-3" id="page_id_row"' . $output_page_id_row_style . '>
                                                    <label for="page_id" class="form-label">' . lang('Page') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'page_id', 'id'=>'page_id', 'class'=>'form-control', 'options'=>get_page_options('', '', 'edit', FALSE))) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 md:col-6 lg:col-5 my-3" id="catalog_page_id_row"' . $output_catalog_page_id_row_style . '>
                                                    <label for="catalog_page_id" class="form-label">' . lang('Catalog Page') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'catalog_page_id', 'id'=>'catalog_page_id', 'class'=>'form-control', 'options'=>get_page_options('', 'catalog', 'edit', FALSE), 'onclick'=>'if (this.selectedIndex != 0) {document.getElementById(\'catalog_detail_page_id\').selectedIndex = 0}' )) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 lg:col-2 my-3 lg:text-center" id="or_row"' . $output_or_row_style . '>
                                                -' . lang('or') . '-
                                                </div>
                                                <div class="col-12 md:col-6 lg:col-5 my-3" id="catalog_detail_page_id_row"' . $output_catalog_detail_page_id_row_style . '>
                                                    <label for="catalog_detail_page_id" class="form-label">' . lang('Catalog Detail Page') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'catalog_detail_page_id', 'id'=>'catalog_detail_page_id', 'class'=>'form-control', 'options'=>get_page_options('', 'catalog detail', 'edit', FALSE), 'onclick'=>'if (this.selectedIndex != 0) {document.getElementById(\'catalog_page_id\').selectedIndex = 0}' )) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 md:col-6 lg:col-5 my-3" id="product_group_id_row"' . $output_product_group_id_row_style . '>
                                                    <label for="product_group_id" class="form-label">' . lang('Product Group') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_group_id', 'id'=>'product_group_id', 'class'=>'form-control', 'options'=>get_product_group_options($product_group_id = 0, $parent_product_group_id = 0, $excluded_product_group_id = 0, $level = 0, $product_groups = array(), $include_select_product_groups = TRUE, $format = 'array', $include_blank_option = TRUE, TRUE, FALSE))) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 md:col-6 lg:col-7 my-3" id="product_id_row"' . $output_product_id_row_style . '>
                                                    <label for="product_id" class="form-label">' . lang('Product') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'product_id', 'id'=>'product_id', 'class'=>'form-control', 'options'=>get_product_options())) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
                                                <div class="col-12 md:col-8 lg:col-6 my-3" id="url_row"' . $output_url_row_style . '>
                                                    <label for="url" class="form-label">' . lang('Destination URL') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text', 'name'=>'url', 'id'=>'url', 'class'=>'form-control', 'maxlength'=>pg_short_link_url_max(), 'placeholder' => lang('Enter URL that visitor should be redirected to'))) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                    <div class="form-text">' . lang('With a * in the name, $1 to $9 put here what each * matched, after the host name.') . '</div>
                                                </div>
                                                <div class="col-12 md:col-6 lg:col my-3" id="tracking_code_row"' . $output_tracking_code_row_style . '>
                                                    <label for="tracking_code" class="form-label">' . lang('Tracking Code') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'text', 'name'=>'tracking_code', 'id'=>'tracking_code', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                                </div>
                                                <div class="col-12 md:col-6 lg:col-4 my-3" id="file_row"' . $output_file_row_style . '>
                                                     <label for="file" class="form-label">' . lang('File') . '</label>
                                                    ' . $liveform->output_field(array('type'=>'select', 'name'=>'file', 'id'=>'file', 'class'=>'form-control', 'options'=>get_file_options($design=true))) . '
                                                    <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                </div>
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
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();

    // A field posted as a list (product_id[]=x) counts as not given: the
    // escaping and the checks below stopped the request on it with a 500.
    pg_liveform_single_values($liveform, array('name', 'destination_type', 'page_id', 'catalog_page_id', 'catalog_detail_page_id', 'product_group_id', 'product_id', 'url', 'file', 'tracking_code', 'link_mode', 'duration', 'redirect_code'));

    $handle = @fopen(HTACCESS_FILE_PATH, 'a');

    // If the rewrite file does not exist or can not be written to, then add error.
    if ($handle == FALSE) {
        $liveform->mark_error('', lang(array('string'=>'{var:1} is not writeable so a short link cannot be added.','vars'=>HTACCESS_FILE_NAME)) );

    // Otherwise the rewrite file is writeable, so close handle.
    } else {
        @fclose($handle);
    }
    
    // How it opens (2026.4.5, 5.112): for good, once (a generated address, no
    // name) or until a time chosen here.
    $link_mode = pg_short_link_modes_ready() ? (string) $liveform->get_field_value('link_mode') : 'permanent';

    if (!in_array($link_mode, array('permanent', 'once', 'timed'), true)) {
        $link_mode = 'permanent';
    }

    $link_expires = 0;

    if ($link_mode === 'timed') {
        $link_duration = (int) $liveform->get_field_value('duration');

        if (!isset(pg_short_link_durations()[$link_duration])) {
            $liveform->mark_error('duration', lang('Choose how long the link is valid.'));
        } else {
            $link_expires = time() + $link_duration;
        }
    }

    $name = '';

    if ($link_mode !== 'once') {
        $liveform->validate_required_field('name', lang('Name is required.'));

        // An old address pasted whole keeps only its path; spaces become
        // underscores (pg_short_link_name_from_address()).
        $name = pg_short_link_name_from_address($liveform->get_field_value('name'));

        // Update name in liveform.
        $liveform->assign_field_value('name', $name);

        // If there is not already an error for the name field and the name breaks the rule, then add error.
        if ($liveform->check_field_error('name') == FALSE) {
            $name_problem = pg_short_link_name_problem($name);

            if ($name_problem !== '') {
                $liveform->mark_error('name', h($name_problem));
            }
        }

        // If there is not already an error for the name field,
        // and the name is already in use, then add error.
        if (
            ($liveform->check_field_error('name') == false)
            && (check_name_availability(array('name' => $name)) == false)
        ) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
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

                // A form drawn before the group was deleted can still post it.
                if (
                    ($liveform->check_field_error('product_group_id') == FALSE)
                    && pg_catalog_group_binned($liveform->get_field_value('product_group_id'))
                ) {
                    $liveform->mark_error('product_group_id', lang('The product group is in the Recycle Bin. Restore it in the Catalog Manager or choose another product group.'));
                }
            
                break;
            
            case 'product':
                $liveform->validate_required_field('catalog_detail_page_id', lang('Catalog Detail Page is required.'));
                $liveform->validate_required_field('product_id', lang('Product is required.'));

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

                // Same for the product.
                if (
                    ($liveform->check_field_error('product_id') == FALSE)
                    && pg_catalog_product_binned($liveform->get_field_value('product_id'))
                ) {
                    $liveform->mark_error('product_id', lang('The product is in the Recycle Bin. Restore it in the Catalog Manager or choose another product.'));
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

                // The column holds this many characters; a longer address
                // would be cut short in silence.
                if (
                    ($liveform->check_field_error('url') == false)
                    && (mb_strlen($liveform->get_field_value('url'), 'UTF-8') > pg_short_link_url_max())
                ) {
                    $liveform->mark_error('url', lang(array('string' => 'The address can be at most {var:1} characters long.', 'vars' => array(pg_short_link_url_max()))));
                }

                break;

            case 'file':
                $liveform->validate_required_field('file', lang('Destination URL is required.'));

                // A form drawn before the file was deleted can still post it.
                if (
                    ($liveform->check_field_error('file') == FALSE)
                    && pg_recycle_file_binned($liveform->get_field_value('file'))
                ) {
                    $liveform->mark_error('file', lang('The file is in the Recycle Bin. Restore it in the File Manager or choose another file.'));
                }

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

        // Same for the page as for the file above.
        if (
            ($liveform->check_form_errors() == FALSE)
            && pg_recycle_page_binned($page_id)
        ) {
            $liveform->mark_error($page_field_name, lang('The page is in the Recycle Bin. Restore it in the File Manager or choose another page.'));
        }
    }
    
    // How it answers, checked with the name and the destination it goes
    // with: an address may not lead back to the link or round a loop.
    $short_link_answer = array('columns' => array(), 'problem' => null);

    if ($liveform->check_form_errors() == FALSE) {
        $short_link_answer = pg_short_link_redirect_columns(
            array(
                'id' => 0,
                'name' => $name,
                'destination_type' => $liveform->get_field_value('destination_type'),
                'url' => ($liveform->get_field_value('destination_type') == 'url') ? $liveform->get_field_value('url') : ''),
            pg_short_link_redirects_ready() ? (int) $liveform->get_field_value('redirect_code') : null,
            0);

        if ($short_link_answer['problem'] !== null) {
            $liveform->mark_error($short_link_answer['problem']['field'], h($short_link_answer['problem']['message']));
        }
    }

    // If there is an error, forward user back to previous screen.
    if ($liveform->check_form_errors() == TRUE) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_short_link.php');
        exit();
    }

    $sql_field = "";
    $sql_value = "";

    // Prepare the SQL differently based on the destination type.
    switch ($liveform->get_field_value('destination_type')) {
        case 'product_group':
            $sql_field = "product_group_id,";
            $sql_value = "'" . escape($liveform->get_field_value('product_group_id')) . "',";
            break;
        
        case 'product':
            $sql_field = "product_id,";
            $sql_value = "'" . escape($liveform->get_field_value('product_id')) . "',";
            break;

        case 'url':
            $sql_field = "url,";
            $sql_value = "'" . escape($liveform->get_field_value('url')) . "',";
            break;

        case 'file':
            $sql_field = "file_id,";
            $sql_value = "'" . escape($liveform->get_field_value('file')) . "',";
            break;
    }
    
    // Once or until a time; a one-time link gets a token for its address,
    // kept only as its hash.
    $link_token = '';

    if (pg_short_link_modes_ready()) {
        $sql_field .= "link_mode, expires_at,";
        $sql_value .= "'" . escape($link_mode) . "', '" . (int) $link_expires . "',";

        if ($link_mode === 'once') {
            $link_token = pg_short_link_new_token();
            $sql_field .= "token_hash, token_hint,";
            $sql_value .= "'" . hash('sha256', $link_token) . "', '" . escape(substr($link_token, 0, 6)) . "',";
        }
    }

    foreach ($short_link_answer['columns'] as $answer_column => $answer_value) {
        $sql_field .= $answer_column . ",";
        $sql_value .= "'" . (int) $answer_value . "',";
    }

    // create short link
    $query =
        "INSERT INTO short_links (
            name,
            destination_type,
            page_id,
            " . $sql_field . "
            tracking_code,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($name) . "',
            '" . escape($liveform->get_field_value('destination_type')) . "',
            '" . escape($page_id) . "',
            " . $sql_value . "
            '" . escape($liveform->get_field_value('tracking_code')) . "',
            '" . USER_ID . "',
            UNIX_TIMESTAMP(),
            '" . USER_ID . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'short link ({var:1}) was created','vars'=>($link_token !== '') ? substr($link_token, 0, 6) . '…' : $name)), $_SESSION['sessionusername']);
    $liveform->remove_form();

    $liveform_view_short_links = new liveform('view_short_links');

    // The whole address of a one-time link, this once: only its hash is kept.
    if ($link_token !== '') {
        $liveform_view_short_links->add_notice(lang('The one-time link has been created. Its address is shown only now; copy it and send it:')
            . ' <code class="user-select-all">' . h(URL_SCHEME . HOSTNAME_SETTING . PATH . $link_token) . '</code>');
    } else {
        $liveform_view_short_links->add_notice(lang('The short link has been created.'));
    }

    // forward user to view short links screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_short_links.php');
    
    
}
?>