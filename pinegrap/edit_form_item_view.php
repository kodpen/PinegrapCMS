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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/form_item_view_form.php');
$user = validate_user();
validate_area_access($user, 'user');

// The page is one value; one sent as an array (?page_id[]=x) counts as not
// given: it ended in an error page.
pg_request_single_values(array('page_id'));

// Get various properties for page that we will use in various places below.
$page = db_item(
    "SELECT
        page_id AS id,
        page_name AS name,
        page_folder AS folder_id,
        page_style AS style_id,
        mobile_style_id AS mobile_style_id,
        page_type,
        page_timestamp AS timestamp,
        page_user AS user_id
    FROM page
    WHERE page_id = '" . e($_REQUEST['page_id'] ?? '') . "'");

// Only a form item view page has a form item view to edit: any other page
// has no form_item_view_pages row, and a save would write one for it.
if (!$page || $page['page_type'] !== 'form item view') {
    output_error(lang('Sorry, the page could not be found.'), 404);
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
            (form_item_view_pages.page_id = ?)
            AND (form_item_view_pages.collection = 'a')";
$result = db_q($query, array((string) $_REQUEST['page_id'])) or output_error('Query failed.');
// A form item view saved before its first edit has no row yet.
$row = mysqli_fetch_assoc($result) ?: array();

$custom_form_folder_id = $row['page_folder'] ?? '';
$custom_form_name = $row['form_name'] ?? '';

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
        $collection_field_marker = ' <span class="fg-success">' . lang('Collection Field') . '</span>';
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
            (page_id = ?)
            AND (collection = 'a')";
    $result = db_q($query, array((string) $page['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result) ?: array();

    $custom_form_page_id = $row['custom_form_page_id'] ?? '';
    $layout = $row['layout'] ?? '';

    // If the style is set to collection b, then get page type properties for
    // that collection.
    if ($collection == 'b') {

        $properties = get_page_type_properties($page['id'], 'form item view', 'b');

        // We only currently support collection for the layout field,
        // so that is why we only override that property.
        $layout = $properties['layout'];

    }

    // The form's fields, the standard fields and a sample line per format.
    // Every entry is a button that types its token in at the layout's cursor.
    $variables_help = pg_cf_variables_help($custom_form_page_id);

    // The Summary's last change: the page's timestamp and the user who made it.
    $change_user = (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $page['user_id'] . "'");
    $changed = ((int) $page['timestamp'] > 0)
        ? get_relative_time(array('timestamp' => (int) $page['timestamp'])) . (($change_user !== '') ? ' ' . h($change_user) : '')
        : '';

    // These two are only ever produced by the form list view screen this block
    // was copied from; there is no filter UI here, so they stay empty. Declared
    // so the concatenation below does not read undefined variables.
    $output_custom_fields_for_javascript = '';
    $output_filters_for_javascript = '';

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
        'heading_description' => lang('Update this page\'s display of a single submitted form, linked to by a reference code.'),
        'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('edit_page.php?id=' . (int)$page['id']))
    ,
            'breadcrumb' => array(array('label' => lang('All My Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'), array('label' => lang('Edit Form Item View'))),
        ]) . '<main id="content" class="container-fluid">' );

    print $output_header . '
            <div class="row">
            <div class="col-12">
                ' . $form->get_messages() . '
                <dialog class="dialog dialog-xl" id="hints" aria-labelledby="hints_title">
                            <div class="dialog-header">
                                <h5 class="dialog-title" id="hints_title">' . lang('Hints') . '</h5>
                                <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                            </div>
                            <div class="dialog-body ">
                                <div class="row">
                                    <div class="col-12 my-5">
                                        <p>' . lang('Click a variable to insert it at the cursor in the layout.') . '</p>
                                        <p>' . lang('Use the following URL format to link to files and embed images') . ':</p>
                                        <p class="mt-3 mb-7 p-5 bg-black rounded-4 fg-white">{path}^^example^^</p>
                                        <p>' . lang('Use the following format to output different content depending on whether there is a value or not') . ':</p>
                                        <p class="mt-3 mb-7 p-5 bg-black rounded-4 fg-white">[[' . lang('There is a value') . ': ^^example^^ || ' . lang('There is not a value') . ']]</p>
                                        <p>' . lang('Use the following format to output different content depending on whether Comparison Operators.') . ' ("==","===","!=","<>","!==","<",">","<=",">=","<=>"):</p>
                                        <p class="mt-3 mb-7 p-5 bg-black rounded-4 fg-white">{if ^^example^^ == \'VALUE\'} ' . lang('Equal') . ' {else} ' . lang('Not Equal') . ' {endif}<br/>{if ^^example^^ == \'VALUE\'} ' . lang('Equal') . ' {endif}</p>
                                        <p>' . lang(array('string'=>'Use the following format to customize the date format for date and date & time fields. The format can either be a {var:1} or "relative" for a relative time (e.g. "2 minutes ago", "2 minutes from now").','vars'=>lang('<a href="http://php.net/manual/en/function.date.php" target="_blank">PHP date format</a>'))) . '</p>
                                        <p class="mt-3 mb-7 p-5 bg-black rounded-4 fg-white">^^submitted_date_and_time^^%%l, F j, Y \a\t g:i A%%<br />^^submitted_date_and_time^^%%relative%%</p>
                                        <p>' . lang('Use the following format to print one text for a ticked check box and another for an empty one; either text may be left empty.') . '</p>
                                        <p class="mt-3 mb-7 p-5 bg-black rounded-4 fg-white">' . h('^^example^^%%' . lang('Yes') . '|' . lang('No') . '%%') . '</p>
                                        <p>' . lang('Use the following URL format to link directly to the newest comment') . ':</p>
                                        <p class="mt-3 mb-7 p-5 bg-black rounded-4 fg-white">#c-^^newest_comment_id^^</p>
                                    </div>
                                </div>
                            </div>
                </dialog>
                <form action="edit_form_item_view.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <input type="hidden" name="page_id" value="' . h($_GET['page_id']) . '" />
                    ' . pg_form_item_view_form_layout(
                        pg_form_item_view_form_card($layout, $collection_field_marker, pg_code_editor_attach(array('id' => 'layout', 'code_type' => 'mixed')))
                        . pg_form_item_view_form_status(
                            pg_form_item_view_form_summary(array(
                                'page_name' => $page['name'],
                                'form_name' => $custom_form_name,
                                'collection' => $collection,
                                'changed' => $changed,
                            )),
                            pg_form_item_view_form_variables($variables_help)),
                        '
                                        <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>') . '
                </form>
            </div>
        </div>
    ' . pg_form_item_view_form_insert_script('pg_fiv_variables', array('layout')) . '
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
                page_user = ?
             WHERE page_id = ?";
    $result = db_q($query, array((string) $user['id'], (string) ($_POST['page_id'] ?? ''))) or output_error('Query failed.');

    log_activity(lang(array('string'=>'page ({var:1}) was modified','vars'=>$page['name'])), $_SESSION['sessionusername']);

    if ($_POST['send_to']) {
        // send user to send to
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
    } else {
        // send user to send to
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_page.php?id=' . $_POST['page_id']);
    }

}