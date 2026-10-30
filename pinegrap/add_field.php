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
include_once('product_builder.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php');

$user = validate_user();

$liveform = new liveform('add_field');

// The form's page, product group and product are single values; one sent as an
// array (?page_id[]=x) counts as not given: it ended in an error page.
pg_request_single_values(array('page_id', 'product_group_id', 'product_id'));

// The form's folder: the default upload folder of a custom form's file field.
$folder_id = '';
$quiz = '';

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
        WHERE page_id = ?";
    $result = db_q($query, array((string) $_REQUEST['page_id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    // A deleted or mistyped page id must stop here: the page type below is
    // used to build a table name, and a missing row would turn that into a
    // query against a table that does not exist.
    if (!$row) {
        output_error(lang('Sorry, the page could not be found.'), 404);
    }
    
    $page_type = $row['page_type'];
    $folder_id = $row['page_folder'];
    $page_name = $row['page_name'];
    
    // An express order page holds a shipping and a billing form; the query
    // string says which one.
    $form_type = pg_field_form_page_form_type($page_type, $_REQUEST['form_type'] ?? '');

    // No field is added to a designer form (the canvas is its record) or to a
    // page that has no form.
    if ($form_type === '') {
        pg_form_fields_refuse_page($_REQUEST['page_id']);
    }

    $form_type_name = pg_field_form_type_name($form_type);
    $form_type_identifier_id = 'page_id';
    $form_type_filter = pg_field_form_filter($form_type_identifier_id, $_REQUEST[$form_type_identifier_id], $page_type, $form_type);
    
    // validate user's access
    if (check_edit_access($folder_id) == false) {
        log_activity('access denied to add field to ' . $form_type . ' because user does not have access to modify folder that ' . $form_type . ' is in', $_SESSION['sessionusername']);
        output_error(lang('Access denied.'));
    }

    list($form_name, $quiz) = pg_field_form_page_form_name($page_type, $form_type, $_REQUEST['page_id'], $page_name);

    $pg_breadcrumb_parent = array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php');
    $output_form_designer_content_heading = lang(array('string'=>'Create {var:1} Field','vars'=>array(ucwords($form_type_name)) ));
    $output_form_designer_content_subheading = lang(array('string'=>'Create a new field on this {var:1}.','vars'=>$form_type_name ) );
    
// Else if there is a product_group_id, this is a variant set's form template
// (2026.4). Placed before the product branch so a request carrying both is
// unambiguous.
} elseif ((isset($_REQUEST['product_group_id'])) && ($_REQUEST['product_group_id'] != '')) {

    $form_type = 'product_group';
    $form_type_name = lang('product form');

    // Not a page form: no page type for the position list and layout checks below.
    $page_type = '';
    $form_type_identifier_id = 'product_group_id';
    $form_type_filter = pg_field_form_filter($form_type_identifier_id, $_REQUEST[$form_type_identifier_id], $page_type, $form_type);

    validate_ecommerce_access($user);

    $product_group = db_items(
        "SELECT name, short_description, form_name
        FROM product_groups
        WHERE id = '" . e($_REQUEST['product_group_id']) . "'
        LIMIT 1");

    $product_group = $product_group ? $product_group[0] : array('name' => '', 'short_description' => '', 'form_name' => '');

    $product_name = $product_group['name'];
    $form_name = pg_field_form_product_form_name($product_group['form_name'], $product_group['short_description'], $product_name);

    $pg_breadcrumb_parent_items = array(
        array('label' => lang('Variant Sets'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php?mode=variant_sets'),
        array('label' => $product_name, 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . h(escape_javascript($_REQUEST['product_group_id']))),
    );

    $output_form_designer_content_heading = lang('Create Product Form Field');
    $output_form_designer_content_subheading = lang('Create a new field on this product form.');

// else if there is a product_id supplied in the query string, this is a product form
} elseif ((isset($_REQUEST['product_id'])) && ($_REQUEST['product_id'] != '')) {

    $form_type = 'product';
    $form_type_name = lang('product form');

    // Not a page form: no page type for the position list and layout checks below.
    $page_type = '';
    $form_type_identifier_id = 'product_id';
    $form_type_filter = pg_field_form_filter($form_type_identifier_id, $_REQUEST[$form_type_identifier_id], $page_type, $form_type);

    validate_ecommerce_access($user);
    
    // get product name, short description and form name to determine what we will use for the form name
    $query =
        "SELECT 
            name,
            short_description,
            form_name
        FROM products
        WHERE id = ?";
    $result = db_q($query, array((string) $_REQUEST['product_id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $form_name = pg_field_form_product_form_name($row['form_name'], $row['short_description'], $row['name']);

    $pg_breadcrumb_parent = array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php');
    $output_form_designer_content_heading = lang('Create Product Form Field');
    $output_form_designer_content_subheading = lang('Create a new field on this product form.');

// Opened without a page, product group or product (stale link, hand-typed
// URL): there is no form to add the field to, so a "not found" screen instead
// of PHP notices from the form details below that were never set.
} else {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// The breadcrumb ends with this screen; the product group branch has two parents.
$pg_breadcrumb_items = isset($pg_breadcrumb_parent_items) ? $pg_breadcrumb_parent_items : (isset($pg_breadcrumb_parent) ? array($pg_breadcrumb_parent) : array());
$pg_breadcrumb_items[] = array('label' => $output_form_designer_content_heading);

// The back arrow goes where Save goes: the fields of this form, with the
// Express Order form type (shipping or billing) and send_to.
$pg_back_arrow_url = 'view_fields.php?' . $form_type_identifier_id . '=' . urlencode((string) $_REQUEST[$form_type_identifier_id]) . pg_field_form_express_query($page_type, $form_type) . '&send_to=' . urlencode(is_scalar($_GET['send_to'] ?? null) ? (string) $_GET['send_to'] : '');

// if the form has not been submitted yet, then prepare to output form
if (!$_POST) {
    // The new field goes below the form's last field by default.
    $query = "SELECT id
             FROM form_fields
             WHERE $form_type_filter
             ORDER BY sort_order DESC
             LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $position = $row ? $row['id'] : 'top';

    // A form sent back by a refused save shows what was typed.
    $field = pg_field_form_new_field($folder_id);
    $posted = pg_field_form_session_values($liveform, $field);

    if ($posted !== null) {
        $field = pg_field_form_posted_field($field, $posted);
        $position = $posted['position'] ?? $position;
    }
    
    echo
    pg_page_shell([
        'title'=> $output_form_designer_content_heading,
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>$output_form_designer_content_heading,
        'heading_description' => $output_form_designer_content_subheading,
        'cancel'=>array('enable'=>'true','url' => $pg_back_arrow_url),
        'breadcrumb' => $pg_breadcrumb_items,
    ]) . '
<main id="content" class="container-fluid">
            ' . get_wysiwyg_editor_code(array('information')) . '
        <div class="row">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <div class="col-12">
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        <p class="p-0 m-0">' . ucwords($form_type_name) . ': ' . h($form_name) . '</p>
                    </div>
                </div>
                <form name="form" action="add_field.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '">
                    <input type="hidden" id="' . $form_type_identifier_id . '" name="' . $form_type_identifier_id . '" value="' . h($_REQUEST[$form_type_identifier_id]) . '">
                    <input type="hidden" name="form_type" value="' . $form_type . '">
                    ' . pg_field_form_cards($liveform, $field, array(
                        'form_type' => $form_type,
                        'quiz' => $quiz,
                        'position' => $position,
                        'form_id' => $_REQUEST[$form_type_identifier_id],
                        'page_type' => $page_type,
                        'upload_folder_row' => ($form_type == 'custom'),
                    )) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
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

    $name = trim($_POST['name']);
    
    pg_field_form_validate_name($liveform, $name);
    
    $url_form_type = pg_field_form_express_query($page_type, $form_type);

    // if there are errors in the liveform then send the user back to the add field screen
    if ($liveform->check_form_errors()) {
        $liveform->add_fields_to_session();
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_field.php?' . $form_type_identifier_id . '=' . urlencode($_POST[$form_type_identifier_id]) . $url_form_type . '&send_to=' . urlencode($_POST['send_to']));
        exit();
    }

    $sql_upload_folder_id_column = "";
    $sql_upload_folder_id_value = "";
    $sql_translate_values_column = "";
    $sql_translate_values_value = "";
    
    // if this is a custom form
    if ($form_type == 'custom') {
        // Only a text box or text area can be left out of translation.
        if (pg_schema_has('form_fields', 'translate_values')) {
            $sql_translate_values_column = "translate_values,";
            $sql_translate_values_value = "'" . pg_cf_translate_flag(!empty($_POST['translate_values']), $_POST['type'] ?? '') . "',";
        }

        // If this is a file upload field, then check access to selected folder,
        // and prepare to add folder info to SQL.
        // A signature is written into that folder as well, so it answers to the
        // same check: the operator must be able to write there.
        if (($_POST['type'] == 'file upload') || ($_POST['type'] == 'signature')) {
            // validate user's access to upload folder id
            if (check_edit_access($_POST['upload_folder_id']) == false) {
                log_activity(lang('access denied to set upload folder for custom form field because user does not have edit rights to folder'), $_SESSION['sessionusername']);
                output_error(lang('Access denied.'));
            }

            $sql_upload_folder_id_column = "upload_folder_id,";
            $sql_upload_folder_id_value = "'" . e($_POST['upload_folder_id'] ?? '') . "',";
        }
    }
    
    $required = pg_field_form_required_value($_POST['type'], $_POST['required'] ?? null);
    
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
                $sql_translate_values_column
                quiz_question,
                quiz_answer,
                user,
                timestamp)
            VALUES (
                '" . e($form_type) . "',
                '" . escape($_POST[$form_type_identifier_id]) . "',
                '" . escape($name) . "',
                '" . escape($_POST['rss_field'] ?? '') . "',
                '" . escape($_POST['label'] ?? '') . "',
                '" . escape($_POST['type'] ?? '') . "',
                '" . escape($required) . "',
                '" . escape(prepare_rich_text_editor_content_for_input($_POST['information'])) . "',
                '" . escape($_POST['default_value'] ?? '') . "',
                '" . escape($_POST['use_folder_name_for_default_value'] ?? '') . "',
                '" . escape($_POST['size'] ?? '') . "',
                '" . escape($_POST['maxlength'] ?? '') . "',
                '" . escape($_POST['wysiwyg'] ?? '') . "',
                '" . escape($_POST['rows'] ?? '') . "',
                '" . escape($_POST['cols'] ?? '') . "',
                '" . escape($_POST['multiple'] ?? '') . "',
                '" . escape($_POST['spacing_above'] ?? '') . "',
                '" . escape($_POST['spacing_below'] ?? '') . "',
                '" . escape($_POST['contact_field'] ?? '') . "',
                '" . escape($_POST['office_use_only'] ?? '') . "',
                $sql_upload_folder_id_value
                $sql_translate_values_value
                '" . escape($_POST['quiz_question'] ?? '') . "',
                '" . escape(prepare_form_data_for_input($_POST['quiz_answer'] ?? '', $_POST['type'])) . "',
                '" . $user['id'] . "',
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $form_field_id = mysqli_insert_id(db::$con);

    list($invalid_email_address, $invalid_trigger) = pg_field_form_save_options(
        $form_field_id,
        $_POST['type'],
        $_POST['options'] ?? '',
        $form_type_filter,
        array($form_type_identifier_id => $_POST[$form_type_identifier_id]),
        $form_type_identifier_id,
        $_POST[$form_type_identifier_id]);

    pg_field_form_update_sort_order($form_field_id, $_POST['position'], $form_type_filter);
    
    pg_field_form_touch_owner($form_type, $user['id'], $_POST['product_id'] ?? '', $_POST['page_id'] ?? '');

    $liveform = new liveform('view_fields');

    // A page with a modified custom layout may need its layout edited for the
    // new field.
    pg_field_form_layout_warning($liveform, $page_type, $_POST['page_id'] ?? '');
    
    log_activity(lang(array('string'=>'{var:1} field ({var:2}) on {var:3} ({var:4}) was created','vars'=>array($form_type_name,$_POST['name'],$form_type_name,$form_name))), $_SESSION['sessionusername']);
    
    $liveform->add_notice(pg_field_form_saved_notice(lang('The field has been created.'), $invalid_email_address, $invalid_trigger));

    // The template is written to every product in the set on every change, rather
    // than behind an "apply" button. A template drawn but never applied leaves a
    // set whose products have no form, and nothing on screen says so.
    if ($form_type == 'product_group') {
        pg_pb_apply_form_template((int) $_POST['product_group_id']);
    }

    // forward user to view fields screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_fields.php?' . $form_type_identifier_id . '=' . $_POST[$form_type_identifier_id] . $url_form_type . '&send_to=' . urlencode($_POST['send_to']));
}
