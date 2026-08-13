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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('product_builder.php');

$user = validate_user();

$liveform = new liveform('add_field');

// if there is a page_id supplied in the query string, then this is a page form
if ((isset($_REQUEST['page_id'])) && ($_REQUEST['page_id'] != '')) {
    validate_area_access($user, 'user');
    
    // get page info
    $query =
        "SELECT
            page_type,
            page_folder,
            page_name
        FROM page
        WHERE page_id = '" . escape($_REQUEST['page_id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $page_type = $row['page_type'];
    $folder_id = $row['page_folder'];
    $page_name = $row['page_name'];
    
    $form_type = '';
    
    // get the form type by looking at the page type
    switch ($page_type) {
        case 'custom form':
            $form_type = 'custom';
            break;

        // Express order can have a shipping and/or billing form, so check query string for the type
        // of form that we are dealing with
        case 'express order':

            if ($_REQUEST['form_type'] == 'shipping') {
                $form_type = 'shipping';
            } else {
                $form_type = 'billing';
            }

            break;

        case 'shipping address and arrival':
            $form_type = 'shipping';
            break;

        case 'billing information':
            $form_type = 'billing';
            break;
    }

    // Get the form type name that we will output to user

    $form_type_name = '';

    switch ($form_type) {
        case 'custom':
            $form_type_name = lang('custom form');
            break;

        case 'shipping':
            $form_type_name = lang('custom shipping form');
            break;

        case 'billing':
            $form_type_name = lang('custom billing form');
            break;
    }
    
    $form_type_identifier_id = 'page_id';

    // Prepare sql filter in order to get correct fields

    $form_type_filter =
        "form_fields." . $form_type_identifier_id . " = '" . e($_REQUEST[$form_type_identifier_id]) . "'";

    // If the page type is express order then we need to add an extra filter for the form type
    if ($page_type == 'express order') {
        $form_type_filter .=
            " AND form_fields.form_type = '" . e($form_type) . "'";
    } 
    
    // validate user's access
    if (check_edit_access($folder_id) == false) {
        log_activity('access denied to add field to ' . $form_type . ' because user does not have access to modify folder that ' . $form_type . ' is in', $_SESSION['sessionusername']);
        output_error(lang('Access denied.'));
    }

    $form_name = '';
    $quiz = '';

    // If this is a page and form type that supports a form name, then get it
    if ($page_type != 'express order' or $form_type != 'shipping') {
    
        $sql_quiz = "";
        
        // if this is a custom form, then get quiz value
        if ($form_type == 'custom') {
            $sql_quiz = ", quiz";
        }
        
        // get form name and possibly quiz for page
        $query = "SELECT form_name" . $sql_quiz . " FROM " . str_replace(' ', '_', $page_type) . "_pages WHERE page_id = '" . escape($_REQUEST['page_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $form_name = $row['form_name'];
        $quiz = $row['quiz'];
    }
    
    // if form name is blank, use page name for form name
    if (!$form_name) {
        $form_name = $page_name;
    }
    $output_breadcrumb_first_level_item  = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php">' . lang('All My Pages') . '</a></li>';
    $pg_breadcrumb_parent = array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php');
    $output_form_designer_content_heading = lang(array('string'=>'Create {var:1} Field','vars'=>array(ucwords($form_type_name)) ));
    $output_form_designer_content_subheading = lang(array('string'=>'Create a new field on this {var:1}.','vars'=>$form_type_name ) );
    
// Else if there is a product_group_id, this is a variant set's form template
// (2026.4). Placed before the product branch so a request carrying both is
// unambiguous.
} elseif ((isset($_REQUEST['product_group_id'])) && ($_REQUEST['product_group_id'] != '')) {

    $form_type = 'product_group';
    $form_type_name = lang('product form');
    $form_type_identifier_id = 'product_group_id';

    // form_type is pinned as well as the id: copies generated for each product
    // carry the same product_group_id.
    $form_type_filter =
        "form_fields." . $form_type_identifier_id . " = '" . e($_REQUEST[$form_type_identifier_id]) . "'"
        . " AND form_fields.form_type = 'product_group'";

    validate_ecommerce_access($user);

    $product_group = db_items(
        "SELECT name, short_description, form_name
        FROM product_groups
        WHERE id = '" . e($_REQUEST['product_group_id']) . "'
        LIMIT 1");

    $product_group = $product_group ? $product_group[0] : array('name' => '', 'short_description' => '', 'form_name' => '');

    $product_name      = $product_group['name'];
    $short_description = $product_group['short_description'];
    $form_name         = $product_group['form_name'];

    if (($form_name == '') && ($short_description != '')) {
        $form_name = $short_description;
    } elseif (($form_name == '') && ($product_name != '')) {
        $form_name = $product_name;
    }

    $output_form_designer_subnav_heading = h($short_description != '' ? $short_description : $product_name);
    $output_form_designer_subnav_subheading = '
    <p class="p-0 m-0">' . lang('Form Name') . ': ' . h($form_name) . '</p>';

    $pg_breadcrumb_parent_items = array(
        array('label' => lang('Variant Sets'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products2.php'),
        array('label' => $product_name, 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product2.php?group_id=' . h(escape_javascript($_REQUEST['product_group_id']))),
    );

    $output_form_designer_content_heading = lang('Create Product Form Field');
    $output_form_designer_content_subheading = lang('Create a new field on this product form.');
    $output_cancel_onclick = 'window.location.href=\'view_fields.php?product_group_id=' . h(escape_javascript($_REQUEST['product_group_id'])) . '\'';

// else if there is a product_id supplied in the query string, this is a product form
} elseif ((isset($_REQUEST['product_id'])) && ($_REQUEST['product_id'] != '')) {

    $form_type = 'product';
    $form_type_name = lang('product form');
    $form_type_identifier_id = 'product_id';
    $form_type_filter =
        "form_fields." . $form_type_identifier_id . " = '" . e($_REQUEST[$form_type_identifier_id]) . "'";

    validate_ecommerce_access($user);
    
    // get product name, short description and form name to determine what we will use for the form name
    $query =
        "SELECT 
            name,
            short_description,
            form_name
        FROM products
        WHERE id = '" . escape($_REQUEST['product_id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $product_name = $row['name'];
    $short_description = $row['short_description'];
    $form_name = $row['form_name'];
    
    // if form name is blank and short description is not, use short description for form name
    if (($form_name == '') && ($short_description != '')) {
        $form_name = $short_description;
        
    // else, if form name is blank and product name is not, use product name for form name
    } else if (($form_name == '') && ($product_name != '')) {
        $form_name = $product_name;
    }
    

    $output_breadcrumb_first_level_item  = '<li class="breadcrumb-item"><a class="link-secondary " data-loading-content="' . lang('Loading') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php">' . lang('All Products') . '</a></li>';
    $pg_breadcrumb_parent = array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php');
    $output_form_designer_content_heading = lang('Create Product Form Field');
    $output_form_designer_content_subheading = lang('Create a new field on this product form.');
}

// if the form has not been submitted yet, then prepare to output form
if (!$_POST) {
    // intialize output variables
    $output_rss_field_row = '';
    $output_quiz_rows = '';
    $output_upload_folder_id_row = '';
    $output_contact_field_row = '';
    $output_office_use_only_row = '';
    
    // if this is for a custom form
    if ($form_type == 'custom') {
        $output_rss_field_row = 
            '<div class="col-12 col-md-6 col-lg-4 my-2 collapse" id="rss_field_row">
                <label for="rss_field" class="form-label">' . lang('RSS / Search Element') . '</label>
                <select id="rss_field" name="rss_field" class="form-select">' . select_rss_field() . '</select>
            </div>';
        
        // if quiz is enabled, then prepare to output quiz fields
        if ($quiz == 1) {
            $output_quiz_rows =
                '<div class="col-12 my-1 collapse" id="quiz_question_row">
                    <div class="form-check form-switch">
                        <input class="form-check-input collapse-switcher" type="checkbox" id="quiz_question" name="quiz_question" value="1" data-bs-target="#quiz_answer_row">
                        <label class="form-check-label" for="quiz_question">' . lang('Quiz Question') . '</label>
                    </div>
                </div>
                <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="quiz_answer_row">
                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                    <div class="popover-body">
                        <div class="row">
                            <div class="col-12 my-2">
                                <label for="quiz_answer" class="form-label">' . lang('Correct Answer') . '</label>
                                <input value="" type="text" name="quiz_answer" id="quiz_answer" class="form-control"  maxlength="255"/>
                            </div>
                        </div>
                    </div>
                </div>';
        }
        
        $output_upload_folder_id_row = 
            '<div class="col-12 my-2 collapse" id="upload_folder_id_row">
                <div class="row">
                    <div class="col-12 col-md-6 my-2">
                        <label for="upload_folder_id" class="form-label">' . lang('Folder to upload File') . '</label>
                        <select name="upload_folder_id" id="upload_folder_id" class="form-select">' . select_folder($folder_id) . '</select>
                    </div>
                </div>
            </div>';
        
        $output_contact_field_row =
            '<div class="col-12 col-md-12 col-lg-8 my-2 collapse" id="contact_field_row">
                <label for="contact_field" class="form-label">' . lang('Connect to Contact') . '</label>
                <select name="contact_field" id="contact_field" class="form-select"><option value="">-' . lang('None') . '-</option>' . select_contact_field() . '</select>
                <span class="form-text">' . lang('Prefill & Update the Submitter\'s Contact Field') . '</span>
            </div>';
        
        $output_office_use_only_row = 
            '<div class="col-12 my-1 collapse" id="office_use_only_row">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="office_use_only" name="office_use_only" value="1">
                    <label class="form-check-label" for="office_use_only">' . lang('Hide This Field from Submitter') . '</label>
                </div>
            </div>';
    }
    
    // get field with largest sort order in this form, so we can prefill position field with an appropriate value
    $query = "SELECT id
             FROM form_fields
             WHERE $form_type_filter
             ORDER BY sort_order DESC
             LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // if no fields were found then this field is the first field
    if (mysqli_num_rows($result) == 0) {
        $position = 'top';
    
    // else this field is not the first field, so store field id in position value
    } else {
        $row = mysqli_fetch_assoc($result);
        $position = $row['id'];
    }
    
    echo
    pg_page_shell([
        'title'=> $output_form_designer_content_heading,
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>$output_form_designer_content_heading,
        'cancel'=>array('enable'=>'true','url'=>'view_fields.php'),
        'breadcrumb' => array(
            $pg_breadcrumb_parent,
            array('label' => $output_form_designer_content_heading),
        ),
    ]) . '
            ' . get_wysiwyg_editor_code(array('information')) . '
        <div class="row">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . $output_form_designer_content_subheading . '" title="' . $output_form_designer_content_heading . '">[' . lang('Field Name') . ']</h2>
                        <p class="p-0 m-0">' . ucwords($form_type_name) . ': ' . h($form_name) . '</p>
                    </div>
                </div>
                <form name="form" action="add_field.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="send_to" value="' . h($_GET['send_to']) . '">
                    <input type="hidden" id="' . $form_type_identifier_id . '" name="' . $form_type_identifier_id . '" value="' . h($_REQUEST[$form_type_identifier_id]) . '">
                    <input type="hidden" name="form_type" value="' . $form_type . '">
                    <div class="row">
                        <div class="col-12 col-md-4 col-lg-3 col-xl-2">
                            <div class="card my-4 position-sticky" style="top:56px;">
                                <label for="type" class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Field Type') . '
                                </label>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <select id="type" name="type" class="form-select collapse-if-selected" data-bs-target="#options_field_row" onchange="change_field_type(this.options[this.selectedIndex].value)"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>lang('field'))) . '-</option>' .  select_field_type('', $form_type) . '</select>
                                            <script>
                                                $(document).ready(function() {
                                                    change_field_type($("select#type option:selected").val());
                                                });
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-8 col-lg-9 col-xl-10 collapse" id="options_field_row">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('Field Settings') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text','id'=>'name','name'=>'name','required'=>'required', 'placeholder'=>lang('Field Name'), 'class'=>'form-control add-header-content-updater ')) . '
                                        </div>
                                        ' . $output_rss_field_row . '
                                        <div class="col-12 col-md-12 col-lg-4 my-2 collapse" id="label_row">
                                            <label for="label" class="form-label">' . lang('Label') . '</label>
                                            <input type="text" name="label" placeholder="' . lang('Label to display on pages') . '" id="label" class="form-control" />
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2 collapse" id="size_row">
                                            <label for="size" class="form-label">' . lang('Size') . '</label>
                                            <input type="number" placeholder="' . lang('Auto') . '" name="size" id="size" class="form-control" maxlength="10" />
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2 collapse" id="maxlength_row">
                                            <label for="maxlength" class="form-label">' . lang('Maximum Characters') . '</label>
                                            <input type="number" placeholder="' . lang('Unlimited') . '" name="maxlength" id="maxlength" class="form-control" maxlength="10" />
                                        </div>
                                        <div class="col-12 col-md-6  col-lg-4 my-2 collapse" id="position_row">
                                            <label for="position" class="form-label">' . lang('Position') . '</label>
                                            <select name="position" id="position" class="form-select">' . select_field_position($position, '', $_REQUEST[$form_type_identifier_id], $page_type, $form_type) . '</select>
                                        </div>
                                        ' . $output_contact_field_row . '
                                        <div class="col-12 my-1 collapse" id="required_row">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="required" name="required" value="1" checked="checked">
                                                <label class="form-check-label" for="required">' . lang('Is This Filed Required?') . '</label>
                                            </div>
                                        </div>
                                        ' . $output_office_use_only_row . '
                                        <div class="col-12 my-1 collapse" id="wysiwyg_row">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="wysiwyg" name="wysiwyg" value="1">
                                                <label class="form-check-label" for="wysiwyg">' . lang('Enable Rich-text Editor') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1 collapse" id="multiple_row">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="multiple" name="multiple" value="1">
                                                <label class="form-check-label" for="multiple">' . lang('Allow Multiple Selection') . '</label>
                                            </div>
                                        </div>
                                        ' . $output_quiz_rows . '
                                        <div class="col-12 collapse" id="spacing_row">
                                            <div class="my-1">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="spacing_above" name="spacing_above" value="1">
                                                    <label class="form-check-label" for="spacing_above">' . lang('Add Spacing Above') . '</label>
                                                </div>
                                            </div>
                                            <div class="my-1">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="spacing_below" name="spacing_below" value="1">
                                                    <label class="form-check-label" for="spacing_below">' . lang('Add Spacing Below') . '</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 collapse" id="default_value_row">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input collapse-switcher" type="checkbox" id="use_folder_name_for_default_value" name="use_folder_name_for_default_value" value="1" data-bs-target="#default_value_input_row">
                                                <label class="form-check-label" for="use_folder_name_for_default_value">' . lang('Use Folder name for field default value') . '</label>
                                            </div>
                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-4 my-2  collapse show-reverse" id="default_value_input_row">
                                                    <label for="default_value" class="form-label">' . lang('Default Value') . '</label>
                                                    <input value="" type="text" name="default_value" id="default_value" class="form-control" maxlength="255"/>
                                                </div>
                                            </div>
                                        </div>
                                        ' . $output_upload_folder_id_row . '
                                        <div class="col-12 my-1 collapse" id="rows_row">
                                            <h6 class="text-muted">' . lang('Amount of Rows and Columns to Display') . '</h6>
                                            <div class="w-100 border-3 border-top border-start position-relative">
                                                <div style="background: #dee2e6;width: 3px;height: 14px;right: 0px;position: absolute;"></div>
                                                <div style="background: #dee2e6;width: 14px;height: 3px;bottom: 0px;position: absolute;"></div>
                                                <div class="m-3 position-relative"  style="height:150px;">
                                                    <div class="position-absolute end-0 top-0">
                                                        <label for="cols" class="form-label float-end">' . lang('Columns') . '</label>
                                                        <input type="text" placeholder="' . lang('Columns') . '" name="cols" id="cols" class="form-control text-end"  maxlength="10" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="8" data-inputmask-digitsoptional="true" data-inputmask-placeholder="0"/>
                                                    </div>
                                                    <div class="position-absolute start-0 bottom-0">
                                                        <input type="text" name="rows" placeholder="' . lang('Rows') . '" id="rows" class="form-control text-end" maxlength="10" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-digits="8" data-inputmask-digitsoptional="true" data-inputmask-placeholder="0"/>
                                                        <label for="rows" class="form-label mb-0 mt-2">' . lang('Rows') . '</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card my-4 collapse" id="information_row">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('Information') . '
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-12">
                                            <textarea id="information" name="information"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card my-4 collapse" id="choices_row">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('Value Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-12 col-lg-6">
                                            <textarea name="options" id="options" placeholder="' . lang('Please Enter Values Here') . '" class="form-control rounded bg-light" style="min-height: calc(20em + .75rem + 2px);height:100%;" wrap="off"></textarea>
                                            ' . get_codemirror_includes() . '
                                            ' . get_codemirror_javascript(array('id' => 'options', 'code_type' => 'plain')) . '
                                        </div>
                                        <div class="col-12 col-md-12 col-lg-6">
                                            <div class="row">
                                                <div class="col-12 mb-1 card-group">
                                                    <div class="card">
                                                        <div class="card-header">' . lang('Format') . ' 1</div>
                                                        <div class="card-body text-nowrap overflow-auto">
                                                            <p>' . lang('Choice') . ' 1<br />' . lang('Choice') . ' 2<br />' . lang('Choice') . ' 3</p>
                                                        </div>
                                                    </div>
                                                    <div class="card">
                                                        <div class="card-header">' . lang('Example') . ' 1</div>
                                                        <div class="card-body text-nowrap overflow-auto">
                                                            <p>' . lang('Apple') . '<br />' . lang('Banana') . '<br />' . lang('Pear') . '</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 my-1 card-group">
                                                    <div class="card">
                                                        <div class="card-header">' . lang('Format') . ' 2</div>
                                                        <div class="card-body text-nowrap overflow-auto">
                                                            <p>' . lang('Label') . ' 1|' . lang('Value') . ' 1<br />' . lang('Label') . ' 2|' . lang('Value') . ' 2<br />' . lang('Label') . ' 3|' . lang('Value') . ' 3<br /></p>
                                                        </div>
                                                    </div>
                                                    <div class="card">
                                                        <div class="card-header">' . lang('Example') . ' 2</div>
                                                        <div class="card-body text-nowrap overflow-auto">
                                                            <p>' . lang('Apple') . '|' . lang('apple') . '<br />' . lang('Banana') . '|' . lang('banana') . '<br />' . lang('Pear') . '|' . lang('pear') . '</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 mt-1 card-group">
                                                    <div class="card">
                                                        <div class="card-header">' . lang('Format') . ' 3</div>
                                                        <div class="card-body text-nowrap overflow-auto">
                                                            <p>' . lang('Label') . ' 1|' . lang('Value') . ' 1|' . lang('on') . '/' . lang('off') . '<br />' . lang('Label') . ' 2|' . lang('Value') . ' 2|' . lang('on') . '/' . lang('off') . '<br />' . lang('Label') . ' 3|' . lang('Value') . ' 3|' . lang('on') . '/' . lang('off') . '</p>
                                                        </div>
                                                    </div>
                                                    <div class="card">
                                                        <div class="card-header">' . lang('Example') . ' 3</div>
                                                        <div class="card-body text-nowrap overflow-auto">
                                                            <p>' . lang('Apple') . '|' . lang('apple') . '|' . lang('on') . '<br />' . lang('Banana') . '|' . lang('banana') . '|' . lang('off') . '<br />' . lang('Pear') . '|' . lang('pear') . '|' . lang('on') . '</p>
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
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    

    
    $liveform->unmark_errors();
    $liveform->clear_notices();
    
} else {
    validate_token_field();

    $name = trim($_POST['name']);
    
    // If the page name field is blank.
    if ($name == '') {
        $liveform->mark_error('name', lang('The field must have a name. Please type in a name for the field.'));
    }

    // If the name contains a special character, then output an error.
    // We do not allow most of these characters because they can create problems
    // when field variables are used on form list views and etc. (e.g. ^^example^^).
    if (
        (mb_strpos($name, '^') !== false)
        || (mb_strpos($name, '&') !== false)
        || (mb_strpos($name, '[') !== false)
        || (mb_strpos($name, ']') !== false)
        || (mb_strpos($name, '<') !== false)
        || (mb_strpos($name, '>') !== false)
        || (mb_strpos($name, '/') !== false)
    ) {
        $liveform->mark_error('name', lang('The field name cannot contain the following special characters') . ': ^ &amp; [ ] &lt; &gt; /');
    }
    
    // if there are errors in the liveform then send the user back to the add field screen
    if ($liveform->check_form_errors()) {

        $url_form_type = '';

        // If this is an express order page, then determine if we should forward to shipping
        // or billing form.
        if ($page_type == 'express order') {

            $url_form_type = '&form_type=';

            if ($form_type == 'shipping') {
                $url_form_type .= 'shipping';
            } else {
                $url_form_type .= 'billing';
            }
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_field.php?' . $form_type_identifier_id . '=' . urlencode($_POST[$form_type_identifier_id]) . $url_form_type . '&send_to=' . urlencode($_POST['send_to']));
        exit();
    }

    $sql_upload_folder_id_column = "";
    $sql_upload_folder_id_value = "";
    
    // if this is a custom form
    if ($form_type == 'custom') {
        // If this is a file upload field, then check access to selected folder,
        // and prepare to add folder info to SQL.
        if ($_POST['type'] == 'file upload') {
            // validate user's access to upload folder id
            if (check_edit_access($_POST['upload_folder_id']) == false) {
                log_activity(lang('access denied to set upload folder for custom form field because user does not have edit rights to folder'), $_SESSION['sessionusername']);
                output_error(lang('Access denied.'));
            }

            $sql_upload_folder_id_column = "upload_folder_id,";
            $sql_upload_folder_id_value = "'" . e($_POST['upload_folder_id']) . "',";
        }
    }
    
    // if field is an information field, then set field to be not required
    if ($_POST['type'] == 'information') {
        $required = 0;
    } else {
        $required = $_POST['required'];
    }
    
    // create form field
    $query = "INSERT INTO form_fields (
                form_type,
                " . $form_type_identifier_id . ",
                name,
                rss_field,
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
                spacing_below,
                contact_field,
                office_use_only,
                $sql_upload_folder_id_column
                quiz_question,
                quiz_answer,
                user,
                timestamp)
            VALUES (
                '" . e($form_type) . "',
                '" . escape($_POST[$form_type_identifier_id]) . "',
                '" . escape($name) . "',
                '" . escape($_POST['rss_field']) . "',
                '" . escape($_POST['label']) . "',
                '" . escape($_POST['type']) . "',
                '" . escape($required) . "',
                '" . escape(prepare_rich_text_editor_content_for_input($_POST['information'])) . "',
                '" . escape($_POST['default_value']) . "',
                '" . escape($_POST['use_folder_name_for_default_value']) . "',
                '" . escape($_POST['size']) . "',
                '" . escape($_POST['maxlength']) . "',
                '" . escape($_POST['wysiwyg']) . "',
                '" . escape($_POST['rows']) . "',
                '" . escape($_POST['cols']) . "',
                '" . escape($_POST['multiple']) . "',
                '" . escape($_POST['spacing_above']) . "',
                '" . escape($_POST['spacing_below']) . "',
                '" . escape($_POST['contact_field']) . "',
                '" . escape($_POST['office_use_only']) . "',
                $sql_upload_folder_id_value
                '" . escape($_POST['quiz_question']) . "',
                '" . escape(prepare_form_data_for_input($_POST['quiz_answer'], $_POST['type'])) . "',
                '" . $user['id'] . "',
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $form_field_id = mysqli_insert_id(db::$con);

    // assume that there are not any invalid e-mail addresses in options until we find out otherwise
    $invalid_email_address = FALSE;

    // Assume that there are not any invalid triggers until we find out otherwise.
    $invalid_trigger = false;

    // if field has options, deal with options
    if (($_POST['type'] == 'pick list') || ($_POST['type'] == 'radio button') || ($_POST['type'] == 'check box')) {
        $option_lines = array();
        $option_lines = explode("\n", $_POST['options']);
        
        $count = 1;
        
        foreach ($option_lines as $option_line) {
            $email_address_list = '';
            
            // if there is an e-mail address in this option line, then validate e-mail address
            if (preg_match('/\^\^(.*?)\^\^/', $option_line, $matches)) {
                // We support multiple conditional admin email addresses, separated by comma,
                // (e.g. ^^example1@example.com,example2@example.com^^), so deal with that.
                $email_addresses = explode(',', $matches[1]);

                foreach ($email_addresses as $email_address) {
                    $email_address = trim($email_address);

                    // If e-mail address is valid, then add it to the list that will be stored in db.
                    if (validate_email_address($email_address)) {
                        if ($email_address_list != '') {
                            $email_address_list .= ', ';
                        }

                        $email_address_list .= $email_address;
                    
                    // Otherwise the e-mail address is not valid,
                    // so remember that so we can tell the user.
                    } else {
                        $invalid_email_address = true;
                    }
                }
                
                // remove e-mail address from option line
                $option_line = str_replace($matches[0], '', $option_line);
            }
            
            $option_parts = array();
            $option_parts = explode('|', $option_line);
            $label = trim($option_parts[0]);

            // if a value was specifically entered for this option, use entered value for value
            if (isset($option_parts[1]) == true) {
                $value = trim($option_parts[1]);
                
            // else use label for value
            } else {
                $value = $label;
            }
            
            if (mb_strtolower(trim($option_parts[2])) == 'on') {
                $default_selected = 1;
            } else {
                $default_selected = 0;
            }

            $target_field_id = '';
            $target_options = array();

            // If this field is a pick list and a trigger is defined, then deal with it.
            if (
                ($_POST['type'] == 'pick list')
                && (trim($option_parts[3]) != '')
            ) {
                $trigger_parts = explode('=', $option_parts[3]);
                $target_field_name = trim($trigger_parts[0]);

                // If there is a field name, then get field id.
                if ($target_field_name != '') {
                    $target_field_id = db_value(
                        "SELECT id
                        FROM form_fields
                        WHERE
                            ($form_type_filter)
                            AND (name = '" . e($target_field_name) . "')
                            AND (type = 'pick list')");

                    // If a target field was found for the name, then get target options.
                    if ($target_field_id != '') {
                        // Create array of target options by separating by comma.
                        $raw_target_options = explode(',', $trigger_parts[1]);

                        // Loop through the target options in order to remove white space and add options to array.
                        foreach ($raw_target_options as $target_option) {
                            $target_options[] = trim($target_option);
                        }

                        // If there are no target options, then remove trigger.
                        if (count($target_options) == 0) {
                            $target_field_id = '';
                            $invalid_trigger = true;
                        }

                    } else {
                        $target_field_id = '';
                        $invalid_trigger = true;
                    }

                } else {
                    $invalid_trigger = true;
                }
            }

            $upload_folder_id = '';

            // If this field is a pick list or a radio button, and an upload folder is defined
            // for this option, and a folder exists for the id, and this user has edit access to the folder,
            // then prepare to store upload folder for option.
            if (
                (
                    ($_POST['type'] == 'pick list')
                    || ($_POST['type'] == 'radio button')
                )
                && (trim($option_parts[4]) != '')
                && (db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . escape(trim($option_parts[4])) . "'") > 0)
                && (check_edit_access(trim($option_parts[4])) == true)
            ) {
                $upload_folder_id = trim($option_parts[4]);
            }
            
            // create form field option
            $query = "INSERT INTO form_field_options (
                        " . $form_type_identifier_id . ",
                        form_field_id,
                        label,
                        value,
                        email_address,
                        default_selected,
                        sort_order,
                        target_form_field_id,
                        upload_folder_id)
                    VALUES (
                        '" . escape($_POST[$form_type_identifier_id]) . "',
                        '$form_field_id',
                        '" . escape($label) . "',
                        '" . escape($value) . "',
                        '" . escape($email_address_list) . "',
                        '$default_selected',
                        '$count',
                        '$target_field_id',
                        '" . escape($upload_folder_id) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            $option_id = mysqli_insert_id(db::$con);

            // Loop through target options in order to add database records for them.
            foreach ($target_options as $target_option) {
                db(
                    "INSERT INTO target_options (
                        $form_type_identifier_id,
                        trigger_form_field_id,
                        trigger_option_id,
                        value)
                    VALUES (
                        '" . e($_POST[$form_type_identifier_id]) . "',
                        '$form_field_id',
                        '$option_id',
                        '$target_option')");
            }
            
            $count++;
        }
    }

    /* begin: update sort orders for fields */

    $fields = array();
    
    if ($_POST['position'] == 'top') {
        $fields[] = $form_field_id;
    }
    
    // get all fields other than the field that is currently being edited
    $query = "SELECT id
             FROM form_fields
             WHERE ($form_type_filter) AND (id != '" . e($form_field_id)  . "')
             ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    while ($row = mysqli_fetch_assoc($result)) {
        // add this field to array
        $fields[] = $row['id'];
        
        // if this field is the position value, then we need to add the field that is being edited to the array
        if ($row['id'] == $_POST['position']) {
            $fields[] = $form_field_id;
        }
    }
    
    $count = 1;
    
    // loop through all fields in order to update sort order
    foreach ($fields as $key => $field_id) {
        // update sort order for field
        $query = "UPDATE form_fields
                 SET sort_order = '$count'
                 WHERE id = '" . escape($field_id)  . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $count++;
    }
    
    /* end: update sort orders for fields */
    
    // if this is a product form, then update last modified info for product
    if ($form_type == 'product form') {
        $query = "UPDATE products
                 SET
                    user = '" . $user['id'] . "',
                    timestamp = UNIX_TIMESTAMP()
                 WHERE id = '" . escape($_POST['product_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
    // else this is a form for a page, so update last modified info for page
    } else {
        $query = "UPDATE page
                 SET
                    page_timestamp = UNIX_TIMESTAMP(),
                    page_user = '" . $user['id'] . "'
                 WHERE page_id = '" . escape($_POST['page_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }

    $liveform = new liveform('view_fields');

    // If this page type supports a custom layout, then check if this page
    // has a modified custom layout in order to determine if we should show a warning.
    if (check_if_page_type_supports_layout($page_type)) {
        $page = db_item(
            "SELECT
                layout_type,
                layout_modified
            FROM page
            WHERE page_id = '" . e($_POST['page_id']) . "'");

        if (($page['layout_type'] == 'custom') && $page['layout_modified']) {
            $liveform->add_warning(lang('You might need to edit the custom layout now, because you have made changes to fields on the custom form.'));
        }
    }
    
    log_activity(lang(array('string'=>'{var:1} field ({var:2}) on {var:3} ({var:4}) was created','vars'=>array($form_type_name,$_POST['name'],$form_type_name,$form_name))), $_SESSION['sessionusername']);
    
    $notice = lang('The field has been created.');
    
    // if there was an invalid e-mail address, then add error to the liveform
    if ($invalid_email_address == TRUE) {
        $notice .= lang(' However, there were one or more e-mail addresses entered for choices that were invalid, so they have been removed.');
    }

    // If there was an invalid trigger, then add error.
    if ($invalid_trigger == true) {
        // If there was also an invalid email address message then output message with certain wording.
        if ($invalid_email_address == true) {
            $notice .= lang(' Also, there were one or more triggers entered for choices that were invalid, so they have been removed.');
        } else {
            $notice .= lang(' However, there were one or more triggers entered for choices that were invalid, so they have been removed.');
        }
    }
    
    $liveform->add_notice($notice);

    $url_form_type = '';

    // If this is an express order page, then determine if we should forward to shipping
    // or billing form.
    if ($page_type == 'express order') {

        $url_form_type = '&form_type=';

        if ($form_type == 'shipping') {
            $url_form_type .= 'shipping';
        } else {
            $url_form_type .= 'billing';
        }
    }
    

// The template is written to every product in the set on every change, rather
// than behind an "apply" button. A template drawn but never applied leaves a
// set whose products have no form, and nothing on screen says so.
if (isset($form_type) && ($form_type == 'product_group')) {
    pg_pb_apply_form_template((int) $_POST['product_group_id']);
}
    // forward user to view fields screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_fields.php?' . $form_type_identifier_id . '=' . $_POST[$form_type_identifier_id] . $url_form_type . '&send_to=' . urlencode($_POST['send_to']));
}