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

// Get various properties for page that we will use in various places below.
$page = db_item(
    "SELECT
        page_id AS id,
        page_name AS name,
        page_folder AS folder_id,
        page_style AS style_id,
        mobile_style_id AS mobile_style_id
    FROM page
    WHERE page_id = '" . e($_REQUEST['page_id']) . "'");

if (!$page) {
    output_error(lang('Sorry, the page could not be found.'));
}

// validate user's access
if (check_edit_access($page['folder_id']) == false) {
    log_activity(lang('access denied to edit form item view because user does not have access to modify folder that form item view is in'), $_SESSION['sessionusername']);
    output_error(lang('Access was denied, because you do not have access to modify the folder that the form item view is in.'));
}

// get custom form folder, in order to validate user's access
$query = "SELECT
         page.page_folder,
         custom_form_pages.form_name
         FROM form_item_view_pages
         LEFT JOIN page ON form_item_view_pages.custom_form_page_id = page.page_id
         LEFT JOIN custom_form_pages ON custom_form_pages.page_id = form_item_view_pages.custom_form_page_id
         WHERE
            (form_item_view_pages.page_id = '" . e($_REQUEST['page_id']) . "')
            AND (form_item_view_pages.collection = 'a')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

$custom_form_folder_id = $row['page_folder'];
$custom_form_name = $row['form_name'];

if ((isset($custom_form_name) == true) && ($custom_form_name != '')) {
    $output_custom_form_information = lang('Displays submitted forms from') . ': ' . $custom_form_name;
} else {
    $output_custom_form_information = lang('Displays submitted forms from') . ': ' . lang('None');
}

// validate user's access to custom form
if (check_edit_access($custom_form_folder_id) == false) {
    log_activity(lang('access denied to edit form item view because user does not have access to modify folder that custom form is in'), $_SESSION['sessionusername']);
    output_error(lang('Access was denied, because you do not have access to modify the folder that the custom form is in.'));
}

// Get the current style that is shown for this page for this user, so we can figure
// out the collection.  This might be the style that a designer is previewing
// or the activated style if the user is not previewing a style.
$preview_style = get_preview_style(array(
    'page_id' => $page['id'],
    'folder_id' => $page['folder_id'],
    'page_style_id' => $page['style_id'],
    'page_mobile_style_id' => $page['mobile_style_id'],
    'device_type' => $_SESSION['software']['device_type']));

// Get the collection for the style so we can show/save data for the
// right collection
$collection = db_value("SELECT collection FROM style WHERE style_id = '" . e($preview_style['id']) . "'");

// if form has not been submitted
if (!$_POST) {

    $form = new liveform('edit_form_item_view');

    // Get activated style in order to figure out if the user is editing fields
    // for a collection that is different from the activated collection.

    $activated_style = get_activated_style(array(
        'page_id' => $page['id'],
        'folder_id' => $page['folder_id'],
        'page_style_id' => $page['style_id'],
        'page_mobile_style_id' => $page['mobile_style_id'],
        'device_type' => $_SESSION['software']['device_type']));

    $activated_collection = db_value("SELECT collection FROM style WHERE style_id = '" . e($activated_style['id']) . "'");

    $collection_field_marker = '';

    // If the user is editing fields for a collection that is different from
    // the activated collection, then add warning, so user understands.
    if ($activated_collection != $collection) {

        $form->add_notice(lang('You are currently previewing a Page Style that has a different collection than the activated Page Style.  This means that updates to the collection field marked below, will not affect the production Page. Once the new Page Style is activated, then the updates will go live. You can find more info about collections under the Page Style help.'));

        // Show marker next to collection fields, so user understands which
        // fields are collection fields.
        $collection_field_marker = ' <span class="text-success">' . lang('Collection Field') . '</span>';
    }

    // Get collection A info for this form item view.  We get the collection A
    // info even if the style is set to collection B, because we only support
    // collection B for the layout field for now.
    $query =
        "SELECT
            custom_form_page_id,
            layout
        FROM form_item_view_pages
        WHERE
            (page_id = '" . e($page['id']) . "')
            AND (collection = 'a')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $custom_form_page_id = $row['custom_form_page_id'];
    $layout = $row['layout'];

    // If the style is set to collection b, then get page type properties for
    // that collection.
    if ($collection == 'b') {

        $properties = get_page_type_properties($page['id'], 'form item view', 'b');

        // We only currently support collection for the layout field,
        // so that is why we only override that property.
        $layout = $properties['layout'];

    }

    // get standard fields
    $standard_fields = get_standard_fields_for_view();

    $output_available_standard_fields = '';

    // loop through all standard fields
    foreach ($standard_fields as $standard_field) {
        $output_available_standard_fields .= '<li class="list-group-item py-1" >^^' . h($standard_field['value']) . '^^</li>';
    }

    // get custom fields
    $query = "SELECT
                id,
                name
             FROM form_fields
             WHERE
                (page_id = '$custom_form_page_id')
                AND (type != 'information')
                AND (name != '')
             ORDER BY sort_order";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $custom_fields = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $custom_fields[] = $row;
    }

    $output_available_custom_fields = '';

    // loop through all custom fields
    foreach ($custom_fields as $custom_field) {
        $output_available_custom_fields .= '<li class="list-group-item py-1">^^' . h($custom_field['name']) . '^^</li>';
    }

    $output_javascript =
        '<script>
            window.onload = initialize_filters;
            var last_filter_number = 0;
            var custom_fields = new Array();
            ' . $output_custom_fields_for_javascript . '
            var filters = new Array();
            ' . $output_filters_for_javascript . '
        </script>';

    // Put the javascript into the head of the document.
    $output_header = preg_replace('/(<\/head>)/i', $output_javascript .'$1', pg_page_shell([
        'title'=> lang('Edit Form Item View'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Edit Form Item View'),
        'cancel'=>array('enable'=>'true','url'=>'view_submitted_forms.php')
    ,
            'breadcrumb' => array(array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'), array('label' => lang('Edit Form Item View'))),
        ]) );

    print $output_header . '
            <div class="row">
            <div class="col-12">
                ' . $form->get_messages() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page position-relative" data-bs-content="' . lang('Update this page\'s display of a single submitted form, linked to by a reference code.') . '" title="' . lang('Edit Form Item View') . '">[' . h($page['name']) . ']</h2>
                        <p>' . $output_custom_form_information . '</p>
                    </div>
                </div>
                <div class="modal fade" id="hints" tabindex="-1" aria-labelledby="hints" aria-hidden="true">
                    <div class="modal-dialog modal-xl ">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">' . lang('Hints') . '</h5>
                                <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body ">
                                <div class="row">
                                    <div class="col-12 my-3">
                                        <p>' . lang('Copy tags from Fields and paste in the layout below.') . '</p>
                                        <p>' . lang('Use the following URL format to link to files and embed images') . ':</p>
                                        <p class="mt-2 mb-4 p-3 bg-dark rounded text-light">{path}^^example^^</p>
                                        <p>' . lang('Use the following format to output different content depending on whether there is a value or not') . ':</p>
                                        <p class="mt-2 mb-4 p-3 bg-dark rounded text-light">[[' . lang('There is a value') . ': ^^example^^ || ' . lang('There is not a value') . ']]</p>
                                        <p>' . lang('Use the following format to output different content depending on whether Comparison Operators.') . ' ("==","===","!=","<>","!==","<",">","<=",">=","<=>"):</p>
                                        <p class="mt-2 mb-4 p-3 bg-dark rounded text-light">{if ^^example^^ == \'VALUE\'} ' . lang('Equal') . ' {else} ' . lang('Not Equal') . ' {endif}<br/>{if ^^example^^ == \'VALUE\'} ' . lang('Equal') . ' {endif}</p>
                                        <p>' . lang(array('string'=>'Use the following format to customize the date format for date and date & time fields. The format can either be a {var:1} or "relative" for a relative time (e.g. "2 minutes ago", "2 minutes from now").','vars'=>lang('<a href="http://php.net/manual/en/function.date.php" target="_blank">PHP date format</a>'))) . '</p>
                                        <p class="mt-2 mb-4 p-3 bg-dark rounded text-light">^^submitted_date_and_time^^%%l, F j, Y \a\t g:i A%%<br />^^submitted_date_and_time^^%%relative%%</p>
                                        <p>' . lang('Use the following URL format to link directly to the newest comment') . ':</p>
                                        <p class="mt-2 mb-4 p-3 bg-dark rounded text-light">#c-^^newest_comment_id^^</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <form action="edit_form_item_view.php" method="post">
                    ' . get_codemirror_includes() . '
                    ' . get_token_field() . '
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" name="page_id" value="' . h($_GET['page_id']) . '" />
                    <div class="row">
                        <div class="col-12 col-md">
                            <div class="row">
                                <div class="col-12">
                                    <div class="card my-4">
                                        <label for="name" class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Field Options') . '
                                        </label>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 col-md-auto col-lg my-2 ">
                                                    <p class="mb-0 text-primary ">' . lang('System Fields') . '</p>
                                                    <ul class="list-group overflow-auto pe-1" style="min-height:100px;height:150px;max-height:500px;resize: vertical;">
                                                        ' . $output_available_standard_fields . '
                                                    </ul>
                                                </div>
                                                <div class="col-12 col-md my-2">
                                                    <p class="mb-0 forms-color">' . lang('Form Fields') . '</p>
                                                    <ul class="list-group overflow-auto pe-1" style="min-height:100px;height:150px;max-height:500px;resize: vertical;">
                                                        ' . $output_available_custom_fields . '
                                                    </ul>
                                                </div>
                                                <div class="col-12 text-end my-2">
                                                    <button type="button" class="btn btn-link link-secondary" data-bs-toggle="modal" data-bs-target="#hints"><span class="material-icons me-1">info</span>' . lang('Hints') . '</button>
                                                </div>
                                                <div class="col mt-3">
                                                    <div class="form-text text-start">' . lang(array('string'=>'Display layout of submitted form data fields within View{var:1}','vars'=>$collection_field_marker)) . '</div>
                                                    <textarea id="layout" name="layout">' . h($layout) . '</textarea>
                                                    ' . get_codemirror_javascript(array('id' => 'layout', 'code_type' => 'mixed')) . '
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
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();

    $form->remove();

// else form has been submitted
} else {

    validate_token_field();

    // Update the layout for the collection that is set in the style.
    create_or_update_page_type_record('form item view', array(
        'page_id' => $page['id'],
        'collection' => $collection,
        'layout' => $_POST['layout']));

    // update last modified for page
    $query = "UPDATE page
             SET
                page_timestamp = UNIX_TIMESTAMP(),
                page_user = '" . $user['id'] . "'
             WHERE page_id = '" . escape($_POST['page_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    log_activity(lang(array('string'=>'page ({var:1}) was modified','vars'=>$page['name'])), $_SESSION['sessionusername']);

    if ($_POST['send_to']) {
        // send user to send to
        header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['send_to']);
    } else {
        // send user to send to
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . $_POST['page_id']);
    }

}