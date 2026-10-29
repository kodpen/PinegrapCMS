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

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM form_fields WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// get page_id and product_id for field, in order to validate user's access
$query =
    "SELECT
        form_type,
        page_id,
        product_id,
        " . (pg_pb_form_template_ready() ? 'product_group_id' : '0 AS product_group_id') . ",
        name as field_name,
        rss_field
    FROM form_fields
    WHERE form_fields.id = ?";
$result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');

// if a form field could not be found, then output error
if (mysqli_num_rows($result) == 0) {
    output_error(lang('The form field could not be found.'));
}

$row = mysqli_fetch_assoc($result);

$field_form_type = $row['form_type'];
$page_id = $row['page_id'];
$product_id = $row['product_id'];
$product_group_id = isset($row['product_group_id']) ? (int) $row['product_group_id'] : 0;
$field_name = $row['field_name'];

// Set the old rss field so later we can determine if field has been set as the title field,
// so address names need to be updated for submitted forms for pretty URL feature.
$old_rss_field = $row['rss_field'];

// A field of a form drawn in the visual designer: shown, never changed here.
$designer_form = false;

// if there is a page_id, this is a page form
if ($page_id != 0) {
    validate_area_access($user, 'user');
    
    // get page info
    $query =
        "SELECT
            page_type,
            page_folder,
            page_name
        FROM page
        WHERE page_id = ?";
    $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $page_type = $row['page_type'];
    $folder_id = $row['page_folder'];
    $page_name = $row['page_name'];

    $form_type = pg_field_form_page_form_type($page_type, $field_form_type);

    // A form drawn in the visual designer keeps its page's type ('standard').
    // Its fields are custom form fields, derived from the canvas on every
    // design save.
    if (($form_type === '') && ($field_form_type == 'custom') && pg_page_has_designer_form($page_id)) {
        $designer_form = true;
        $form_type = 'custom';
    }

    $form_type_name = pg_field_form_type_name($form_type);
    $form_type_identifier_id = 'page_id';
    $form_type_filter = pg_field_form_filter($form_type_identifier_id, $page_id, $page_type, $form_type);
    
    // validate user's access
    if (check_edit_access($folder_id) == false) {
        log_activity(lang(array('string'=>'access denied to edit field on {var:1} because user does not have access to modify folder that {var:1} is in','vars'=>$form_type_name )), $_SESSION['sessionusername']);
        output_error(lang('Access denied.'));
    }

    list($form_name, $quiz) = pg_field_form_page_form_name($page_type, $form_type, $page_id, $page_name);
    
    $output_form_designer_subnav_subheading = ucwords($form_type_name) . ': ' . h($form_name);
    $pg_breadcrumb_parent = array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php');
    $output_form_designer_content_heading = lang(array('string'=>'Edit {var:1} Field','vars'=>array(ucwords($form_type_name)) ));
    $output_form_designer_content_subheading = lang(array('string'=>'View or update this {var:1} field.','vars'=>$form_type_name ) );

    // A designer form's field is only viewed here (see below).
    if ($designer_form) {
        $output_form_designer_content_heading = lang('Form Field');
    }

// Else if this row is a variant set's form template (2026.4). Checked before
// the product branch because a template's product_id is 0 and would otherwise
// fall out of the chain entirely.
} elseif (($field_form_type == 'product_group') && ($product_group_id != 0)) {

    validate_ecommerce_access($user);

    $form_type = 'product_group';
    $form_type_name = 'product form';
    // Not a page form: no page type for the position list and layout checks below.
    $page_type = '';
    $form_type_identifier_id = 'product_group_id';
    $form_type_filter = pg_field_form_filter($form_type_identifier_id, $product_group_id, $page_type, $form_type);

    $product_group = db_items(
        "SELECT name, short_description, form_name
        FROM product_groups
        WHERE id = '" . (int) $product_group_id . "'
        LIMIT 1");

    $product_group = $product_group ? $product_group[0] : array('name' => '', 'short_description' => '', 'form_name' => '');

    $product_name = $product_group['name'];
    $form_name = pg_field_form_product_form_name($product_group['form_name'], $product_group['short_description'], $product_name);

    $output_form_designer_subnav_subheading = '
    <p class="p-0 m-0">' . lang('Form Name') . ': ' . h($form_name) . '</p>';

    $pg_breadcrumb_parent_items = array(
        array('label' => lang('Variant Sets'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php?mode=variant_sets'),
        array('label' => $product_name, 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_product_group.php?id=' . (int) $product_group_id),
    );

    $output_form_designer_content_heading = lang('Edit Product Form Field');
    $output_form_designer_content_subheading = lang('View or update this product form field.');

// else if there is a product_id, this is a product form
} elseif ($product_id != 0) {

    validate_ecommerce_access($user);
    
    $form_type = 'product';
    $form_type_name = 'product form';
    // Not a page form: no page type for the position list and layout checks below.
    $page_type = '';
    $form_type_identifier_id = 'product_id';
    $form_type_filter = pg_field_form_filter($form_type_identifier_id, $product_id, $page_type, $form_type);
    
    // get product name, short description and form name to determine what we will use for the form name
    $query = "SELECT 
                 name,
                 short_description,
                 form_name
             FROM products
             WHERE id = ?";
    $result = db_q($query, array((string) $product_id)) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $form_name = pg_field_form_product_form_name($row['form_name'], $row['short_description'], $row['name']);
    
    $output_form_designer_subnav_subheading = ' ' . lang('Product ID / SKU') . ': ' . h($row['name']) . ' | ' . lang('Form Name') . ': ' . h($form_name);
    $pg_breadcrumb_parent = array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php');
    $output_form_designer_content_heading = lang('Edit Product Form Field');
    $output_form_designer_content_subheading = lang('View or update this product form field.');
}

include_once('liveform.class.php');
$liveform = new liveform('edit_field');

// The breadcrumb ends with this screen; the product group branch has two parents.
$pg_breadcrumb_items = isset($pg_breadcrumb_parent_items) ? $pg_breadcrumb_parent_items : (isset($pg_breadcrumb_parent) ? array($pg_breadcrumb_parent) : array());
$pg_breadcrumb_items[] = array('label' => $output_form_designer_content_heading);

// The canvas is the record of a designer form: every column of the field is
// written from it on each design save (pg_cf_sync_page_form()), so a change
// saved here would quietly come undone. The field is shown read-only with the
// way to the editor, and a POST is refused.
if ($designer_form) {
    if ($_POST) {
        $liveform->add_error(lang('The fields of this form are edited in the visual editor.'));
        go(PATH . SOFTWARE_DIRECTORY . '/edit_field.php?id=' . $id);
    }

    $field = db_item("SELECT * FROM form_fields WHERE id = '" . $id . "'");
    $field_options = db_items(
        "SELECT label, value
        FROM form_field_options
        WHERE form_field_id = '" . $id . "'
        ORDER BY sort_order");

    // The information text is rich text and is shown as markup; a label is
    // plain text and is escaped.
    if ($field['type'] == 'information') {
        $output_label_or_information = pg_sanitize_rich_text($field['information'], 'staff');
    } else {
        $output_label_or_information = h($field['label']);
    }

    $output_field_options = '';

    foreach ((array) $field_options as $option) {
        $output_field_options .= '<li>' . h($option['label']);

        if ($option['value'] != $option['label']) {
            $output_field_options .= ' <span class="fg-secondary">(' . h($option['value']) . ')</span>';
        }

        $output_field_options .= '</li>';
    }

    $output_field_rows =
        '<dt class="sm:col-3">' . lang('Name') . '</dt>
        <dd class="sm:col-9">' . h($field['name']) . '</dd>
        <dt class="sm:col-3">' . lang('Variable') . '</dt>
        <dd class="sm:col-9"><code class="user-select-all">^^' . h($field['name']) . '^^</code></dd>
        <dt class="sm:col-3">' . lang('Label / Information') . '</dt>
        <dd class="sm:col-9">' . $output_label_or_information . '</dd>
        <dt class="sm:col-3">' . lang('Field Type') . '</dt>
        <dd class="sm:col-9">' . get_field_type_name($field['type']) . '</dd>
        <dt class="sm:col-3">' . lang('Required') . '</dt>
        <dd class="sm:col-9">' . ($field['required'] == 1 ? lang('Yes') : lang('No')) . '</dd>';

    if ($output_field_options != '') {
        $output_field_rows .=
            '<dt class="sm:col-3">' . lang('Options') . '</dt>
            <dd class="sm:col-9"><ul class="mb-0 ps-5">' . $output_field_options . '</ul></dd>';
    }

    if ($field['default_value'] != '') {
        $output_field_rows .=
            '<dt class="sm:col-3">' . lang('Default Value') . '</dt>
            <dd class="sm:col-9">' . h($field['default_value']) . '</dd>';
    }

    echo
    pg_page_shell([
        'title' => $output_form_designer_content_heading,
        'extra classes' => 'design',
        'icon' => 'design',
        'heading' => $output_form_designer_content_heading,
        'heading_description' => lang('The fields of this form are edited in the visual editor.'),
        'cancel' => array('enable' => 'true', 'url' => 'view_fields.php?page_id=' . (int) $page_id),
        'breadcrumb' => $pg_breadcrumb_items,
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        ' . $liveform->output_errors() . '
        <div class="col-12">
            <p class="p-0 m-0">' . $output_form_designer_subnav_subheading . '</p>
            <div class="alert theme-info d-flex flex-wrap align-items-center gap-3 mt-5 mb-0" role="note">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span class="flex-grow-1">' . lang('This form was drawn in the visual editor. Its fields are added, changed and removed there; a change made on this screen would be overwritten by the next design save. A field\'s variable (^^name^^) puts its value into the form\'s e-mails.') . '</span>
                <a class="btn btn-sm btn-outline theme-secondary" href="' . h(pg_page_edit_url_by_id($page_id)) . '"><i class="bi bi-brush me-1" aria-hidden="true"></i>' . lang('Open in Visual Editor') . '</a>
            </div>
            <div class="card my-7">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Field') . '</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        ' . $output_field_rows . '
                    </dl>
                </div>
            </div>
        </div>
    </div>
</main>
' . output_footer();

    $liveform->remove_form();
    exit;
}

if (!$_POST) {
    // get field data
    $query = "SELECT *
             FROM form_fields
             WHERE form_fields.id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $field = mysqli_fetch_assoc($result);

    // Empty numbers show as their placeholders (Auto, Unlimited).
    foreach (array('size', 'maxlength', 'rows', 'cols') as $column) {
        if (!$field[$column]) {
            $field[$column] = '';
        }
    }

    // Read before the upgrade adds the column: included.
    $field['translate_values'] = isset($field['translate_values']) ? (int) $field['translate_values'] : 1;

    $field['options'] = pg_field_form_options_text($field['id'], $field['type']);

    // If an upload folder has not been set for a custom form's field yet,
    // then the custom form's folder is the default.
    if (($form_type == 'custom') && !$field['upload_folder_id']) {
        $field['upload_folder_id'] = $folder_id;
    }

    // get field id for field directly above this field, because this will be the position value
    $query = "SELECT id
             FROM form_fields
             WHERE ($form_type_filter) AND (sort_order < " . (int) $field['sort_order'] . ")
             ORDER BY sort_order DESC
             LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $position = $row ? $row['id'] : 'top';

    // A custom form's submitted data goes with the field.
    $delete_data_warning = ($form_type == 'custom') ? lang(' and ALL SUBMITTED FORM DATA for this field') : '';
    
    echo
    pg_page_shell([
        'title'=> $output_form_designer_content_heading,
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>$output_form_designer_content_heading,
        'heading_description' => $output_form_designer_content_subheading,
        'cancel'=>array('enable'=>'true','url'=>'view_fields.php?' . $form_type_identifier_id . '=' . (int) ${$form_type_identifier_id}),
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

                        <p class="p-0 m-0">' . $output_form_designer_subnav_subheading . '</p>
                    </div>
                </div>
                <form name="form" action="edit_field.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    <input type="hidden" id="' . h($form_type_identifier_id) . '" name="' . h($form_type_identifier_id) . '" value="' . h(${$form_type_identifier_id}) . '" />
                    ' . pg_field_form_cards($liveform, $field, array(
                        'form_type' => $form_type,
                        'quiz' => $quiz ?? '',
                        'position' => $position,
                        'form_id' => ${$form_type_identifier_id},
                        'page_type' => $page_type,
                        'upload_folder_row' => true,
                    )) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('field') . $delete_data_warning))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
    
    // if field was selected for deletion
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        
        // delete field
        $query = "DELETE FROM form_fields WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        // delete field options
        $query = "DELETE FROM form_field_options WHERE form_field_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        // Delete target options for this field.
        db("DELETE FROM target_options WHERE trigger_form_field_id = '" . escape($_POST['id'] ?? '') . "'");

        db("DELETE FROM product_submit_form_fields WHERE form_field_id = '" . escape($_POST['id'] ?? '') . "'");

        // If this is a custom form, then delete submitted form data for this,
        // field also, because for custom forms, we don't have a way to show
        // submitted form data without the custom form field.
        if ($form_type == 'custom') {

            // Get uploaded files for this field, in order to delete them.
            $files = db_items(
                "SELECT
                    files.id,
                    files.name
                FROM form_data
                LEFT JOIN files ON form_data.file_id = files.id
                WHERE
                    (form_data.form_field_id = '" . e($_POST['id'] ?? '') . "')
                    AND (form_data.file_id != 0)
                    AND (files.id IS NOT NULL)");

            // Loop through files in order to delete record in DB and on file system.
            foreach ($files as $file) {
                db("DELETE FROM files WHERE id = '" . $file['id'] . "'");
                @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);
                log_activity(lang(array('string'=>'file ({var:1}) from a submitted form was deleted because a custom form field was deleted','vars'=>$file['name'])));
            }

            // Delete all submitted form data for this field.
            db("DELETE FROM form_data WHERE form_field_id = '" . e($_POST['id'] ?? '') . "'");

        }
        
        pg_field_form_touch_owner($form_type, $user['id'], $product_id, $page_id);

        $liveform_view_fields = new liveform('view_fields');

        // A page with a modified custom layout may need its layout edited
        // after a field is removed.
        pg_field_form_layout_warning($liveform_view_fields, $page_type, $page_id);
        
        log_activity(lang(array('string'=>'{var:1} field ({var:2}) on {var:1} ({var:3}) was deleted','vars'=>array($form_type_name,$_POST['name'],$form_name) )), $_SESSION['sessionusername']);
        
        $liveform_view_fields->add_notice(lang('The field has been deleted.'));

        $url_form_type = pg_field_form_express_query($page_type, $form_type);

        // forward user to view fields screen
        // The template is written to every product in the set on every change, rather
        // than behind an "apply" button. See pg_pb_apply_form_template().
        if ($form_type == 'product_group') {
            pg_pb_apply_form_template((int) $_POST['product_group_id']);
        }
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_fields.php?' . $form_type_identifier_id . '=' . $_POST[$form_type_identifier_id] . $url_form_type . '&send_to=' . urlencode($_POST['send_to']));
        
    // else field was not selected for deletion
    } else {
        $name = trim($_POST['name']);
    
        pg_field_form_validate_name($liveform, $name);
        
        // if there are errors in the liveform then send the user back to the edit field screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_field.php?id=' . $_POST['id'] . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        }

        $sql_upload_folder_id = "";

        // Only a custom form's text box or text area can be left out of
        // translation; other forms keep the column as it is.
        $sql_translate_values = "";
        if (($form_type == 'custom') && pg_schema_has('form_fields', 'translate_values')) {
            $sql_translate_values = "translate_values = '" . pg_cf_translate_flag(!empty($_POST['translate_values']), $_POST['type'] ?? '') . "',";
        }
        
        // If this is a file upload field, then check access to selected folder,
        // and prepare to add folder info to SQL. A signature is written into that
        // folder as well, so it answers to the same check.
        if (($_POST['type'] == 'file upload') || ($_POST['type'] == 'signature')) {
            // Get old upload folder, because we will always allow
            // the old upload folder to be set again, regardless of user's access to it.
            $old_upload_folder_id = db_value(
                "SELECT upload_folder_id
                FROM form_fields
                WHERE id = '" . e($_POST['id'] ?? '') . "'");

            // If the upload folder that the user just selected is different
            // from the old upload folder, and the user does not have edit access
            // to the folder, then output error.
            if (
                ($_POST['upload_folder_id'] != $old_upload_folder_id)
                && (check_edit_access($_POST['upload_folder_id']) == false)
            ) {
                log_activity(lang('access denied to set upload folder for custom form field because user does not have edit rights to folder'), $_SESSION['sessionusername']);
                output_error(lang('Access denied.'));
            }

            $sql_upload_folder_id = "upload_folder_id = '" . e($_POST['upload_folder_id'] ?? '') . "',";
        }
        
        $required = pg_field_form_required_value($_POST['type'], $_POST['required'] ?? null);
        
        // update field
        $query = "UPDATE form_fields
                 SET
                    name = '" . escape($name) . "',
                    rss_field = '" . escape($_POST['rss_field'] ?? '') . "',
                    label = '" . escape($_POST['label'] ?? '') . "',
                    type = '" . escape($_POST['type'] ?? '') . "',
                    required = '" . escape($required) . "',
                    information = '" . escape(prepare_rich_text_editor_content_for_input($_POST['information'])) . "',
                    default_value = '" . escape($_POST['default_value'] ?? '') . "',
                    use_folder_name_for_default_value = '" . escape($_POST['use_folder_name_for_default_value'] ?? '') . "',
                    size = '" . escape($_POST['size'] ?? '') . "',
                    maxlength = '" . escape($_POST['maxlength'] ?? '') . "',
                    wysiwyg = '" . escape($_POST['wysiwyg'] ?? '') . "',
                    `rows` = '" . escape($_POST['rows'] ?? '') . "',  # Backticks for reserved word.
                    cols = '" . escape($_POST['cols'] ?? '') . "',
                    multiple = '" . escape($_POST['multiple'] ?? '') . "',
                    spacing_above = '" . escape($_POST['spacing_above'] ?? '') . "',
                    spacing_below = '" . escape($_POST['spacing_below'] ?? '') . "',
                    contact_field = '" . escape($_POST['contact_field'] ?? '') . "',
                    office_use_only = '" . escape($_POST['office_use_only'] ?? '') . "',
                    $sql_upload_folder_id
                    $sql_translate_values
                    quiz_question = '" . escape($_POST['quiz_question'] ?? '') . "',
                    quiz_answer = '" . escape(prepare_form_data_for_input($_POST['quiz_answer'], $_POST['type'])) . "',
                    user = '" . $user['id'] . "',
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // if field has options, then replace them
        if (($_POST['type'] == 'pick list') || ($_POST['type'] == 'radio button') || ($_POST['type'] == 'check box')) {
            // delete existing options
            $query = "DELETE FROM form_field_options WHERE form_field_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

            // Delete target options for this field.
            db_q("DELETE FROM target_options WHERE trigger_form_field_id = ?", array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        }

        list($invalid_email_address, $invalid_trigger) = pg_field_form_save_options(
            $_POST['id'] ?? '',
            $_POST['type'],
            $_POST['options'] ?? '',
            $form_type_filter,
            array('page_id' => $page_id, 'product_id' => $product_id),
            $form_type_identifier_id,
            $_POST[$form_type_identifier_id]);

        pg_field_form_update_sort_order($_POST['id'] ?? '', $_POST['position'], $form_type_filter);

        // If this is a custom form, and RSS field was not set to title before this update,
        // and now it is set to title, and pretty URLs are enabled for custom form,
        // then update address names for submitted forms for pretty URL feature.
        if (
            ($form_type == 'custom')
            && ($old_rss_field != 'title')
            && ($_POST['rss_field'] == 'title')
            && (check_if_pretty_urls_are_enabled($page_id) == true)
        ) {
            update_multiple_submitted_form_address_names($page_id);
        }
        
        pg_field_form_touch_owner($form_type, $user['id'], $product_id, $page_id);

        $liveform_view_fields = new liveform('view_fields');

        // A page with a modified custom layout may need its layout edited
        // after a field changes.
        pg_field_form_layout_warning($liveform_view_fields, $page_type, $page_id);
        
        log_activity(lang(array('string'=>'{var:1} field ({var:2}) on {var:1} ({var:3}) was modified','vars'=>array($form_type_name,$_POST['name'],$form_name) )));
        
        $liveform_view_fields->add_notice(pg_field_form_saved_notice(lang('The field has been saved.'), $invalid_email_address, $invalid_trigger));

        $url_form_type = pg_field_form_express_query($page_type, $form_type);
        
        // forward user to view fields screen
        // The template is written to every product in the set on every change, rather
        // than behind an "apply" button. See pg_pb_apply_form_template().
        if ($form_type == 'product_group') {
            pg_pb_apply_form_template((int) $_POST['product_group_id']);
        }
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_fields.php?' . $form_type_identifier_id . '=' . $_POST[$form_type_identifier_id] . $url_form_type . '&send_to=' . urlencode($_POST['send_to']));
    }
}
