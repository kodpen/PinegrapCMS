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
include_once('liveform.class.php');
$user = validate_user();
validate_area_access($user, 'designer');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/theme_file_form.php');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page. The screen renames, deletes and exports the file, so the
// record must also be a design file stored under a plain .css name in the
// files folder: an image, a font or any other file is not found either.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$theme_file_name = ($id > 0) ? (string) db_value("SELECT name FROM files WHERE (id = '" . $id . "') AND (design = '1')") : '';
if (
    pg_design_file_name_unsafe($theme_file_name)
    || ($theme_file_name !== basename($theme_file_name))
    || (mb_strtolower(pathinfo($theme_file_name, PATHINFO_EXTENSION)) !== 'css')
) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

$liveform = new liveform('edit_theme_file');

if (!$_POST) {
    // get the file data
    $query =
        "SELECT
            name,
            folder,
            description,
            activated_desktop_theme,
            activated_mobile_theme
        FROM files
        WHERE id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_array($result);

    $file_name = $row['name'];
    $file_folder = $row['folder'];
    $description = $row['description'];
    $activated_desktop_theme = $row['activated_desktop_theme'];
    $activated_mobile_theme = $row['activated_mobile_theme'];
    
    // The screen is carried through to the hidden field below; it is never set anywhere else.
    $from = isset($_GET['from']) ? ($_GET['from'] ?? '') : '';

    // if user requested to export the theme, then export the theme
    if (($_GET['export'] ?? '') == TRUE) {

        // remove the file extension from the filename
        $file_name_for_csv = mb_substr($file_name, 0, -4);
        
        // force download dialog
        header("Content-type: text/csv");
        header('Content-disposition: attachment; filename="' . preg_replace('/["\\\\\r\n]/', '', $file_name_for_csv) . '.csv"');

        print 'area,row,col,module,region_type,region_name,property,value' . "\n";
        
        // get the properties from the database, then loop through them to add them to the csv file
        $query = 
            "SELECT
                area,
                `row`, # Backticks for reserved word.
                col,
                module,
                property,
                value,
                region_type,
                region_name
            FROM system_theme_css_rules 
            WHERE file_id = ?";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
        while($row = mysqli_fetch_array($result)) {
            // for each value in the row
            foreach ($row as $key => $value) {
               // replace quotation mark with two quotation marks
               $value = str_replace('"', '""', $value);
               // add quotation marks around value
               $value = '"' . $value . '"';
               // set new value
               $row[$key] = $value;
            }
            
            // print the row to the csv file
            print $row['area'] . ',' . $row['row'] . ',' . $row['col'] . ',' . $row['module'] . ',' . $row['region_type'] . ',' . $row['region_name'] . ',' . $row['property'] . ',' . $row['value'] . "\n";
        }
        
        // log the activity
        log_activity(lang(array('string' => 'theme file ({var:1}) was exported', 'vars' => array($file_name))), $_SESSION['sessionusername']);
    
    // else output the page
    } else {
        // if the form has not been submitted yet, pre-populate fields with data
        if ($liveform->field_in_session('id') == FALSE) {
            $liveform->assign_field_value('activated_desktop_theme', $activated_desktop_theme);
            $liveform->assign_field_value('activated_mobile_theme', $activated_mobile_theme);
        }

        // check to see if this is a system theme
        $query = "SELECT COUNT(id) FROM system_theme_css_rules WHERE file_id = ?";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        
        // if this is a system theme then output the edit theme button
        $system_theme = ($row[0] > 0);

        if ($system_theme) {
            $output_edit_theme_button = '<button type="button" class="btn btn-sm btn-text theme-secondary" onclick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/edit_theme_css.php?id=' . urlencode($_GET['id']) . '&send_to=' . urlencode(get_request_uri()) . '&from=edit_theme_file')) . '\'"><i class="bi bi-palette me-2" aria-hidden="true"></i>' . lang('Edit Theme') . '</button>';
            $output_export_button = '<button type="button" class="btn btn-sm btn-text theme-secondary" onclick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/edit_theme_file.php?id=' . urlencode($_GET['id']) . '&export=true')) . '\'"><i class="bi bi-box-arrow-up me-2" aria-hidden="true"></i>' . lang('Export') . '</button>';
            $output_download_file_button = '';
        } else {
            $output_edit_theme_button = '<button type="button" class="btn btn-sm btn-text theme-secondary" onclick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/edit_theme_css.php?id=' . urlencode($_GET['id']) . '&send_to=' . urlencode(get_request_uri()) . '&from=edit_theme_file')) . '\'"><i class="bi bi-filetype-css me-2" aria-hidden="true"></i>' . lang('Edit CSS') . '</button>';
            $output_export_button = '';
            $output_download_file_button = '<button type="button" class="btn btn-sm btn-text theme-secondary" onclick="window.open(\'' . h(escape_javascript(PATH . encode_url_path($file_name))) . '\')"><i class="bi bi-file-earmark-arrow-down me-2" aria-hidden="true"></i>' . lang('Download File') . '</button>';
        }

        // A theme recorded here but missing from the disk says so before it is
        // saved, with the note the file editors show for the same case.
        $output_file_missing = '';

        if (!is_file(FILE_DIRECTORY_PATH . '/' . $file_name)) {
            $output_file_missing = '<div class="alert theme-warning">' . lang('The file is recorded here but is not on the disk.') . '</div>';
        }

        // Summary rows of the status column: the kind of theme, its size on
        // the disk and who may open it (the folder's access control).
        $summary_rows = array(lang('Type') => h($system_theme ? lang('System') : lang('Custom')));

        if ($output_file_missing === '') {
            $summary_rows[lang('File Size')] = h(convert_bytes_to_string(filesize(FILE_DIRECTORY_PATH . '/' . $file_name)));
        }

        $summary_rows[lang('Access')] = '<span class="' . h(get_access_control_type($file_folder)) . '">' . h(get_access_control_type_name(get_access_control_type($file_folder))) . '</span>';

        print
            pg_page_shell([
                'title'=> lang('Edit Theme'),
                'extra classes'=>'design pg-measure-edit',
                'icon'=>'design',
                'heading'=>lang('Edit Theme'),
                'cancel'=>array('enable'=>'true','url'=>'view_themes.php'),
                'breadcrumb' => array(
                    array('label' => lang('Themes'), 'url' => 'view_themes.php'),
                    array('label' => $file_name),
                ),
            ]) . '
<main id="content" class="container-fluid">
                            <div class="row">
                    <div class="col-12">
                        ' . $liveform->output_errors() . '
                        ' . $liveform->output_notices() . '
                ' . pg_screen_toolbar(array(
                    'secondary' => array($output_edit_theme_button, $output_export_button, $output_download_file_button),
                    'grow' => '<p class="fg-secondary small mb-0">' . lang('Page Style Head Tag') . ': ' . h('<stylesheet></stylesheet>') . '</p>',
                )) . '
                <form action="edit_theme_file.php" method="post">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>(isset($_GET['id']) ? $_GET['id'] : ''))) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'from', 'value'=>$from)) . '
                    ' . pg_design_file_form_layout(
                        '
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'value'=>$file_name, 'maxlength'=>'100', 'class'=>'form-control', 'required'=>'required')) . '
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="description" class="form-label">' . lang('Description') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'description', 'id'=>'description', 'value'=>$description, 'rows'=>'3', 'class'=>'form-control')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>',
                        pg_design_file_form_summary_card($output_file_missing, $summary_rows) . '
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Activate Theme to Make it Live') . '
                                </div>
                                <div class="card-body">
                                    <div class="form-field my-3">
                                        <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'activated_desktop_theme', 'id'=>'activated_desktop_theme', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                        <label for="activated_desktop_theme">' . lang('Activate for Desktop') . '</label>
                                    </div>
                                    <div class="form-field my-3">
                                        <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'activated_mobile_theme', 'id'=>'activated_mobile_theme', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                        <label for="activated_mobile_theme">' . lang('Activate for Mobile') . '</label>
                                    </div>
                                </div>
                            </div>' . pg_design_file_form_access_card(select_folder($file_folder)),
                        '
                                        <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                        <button type="button" class="btn my-1 btn-outline theme-secondary" onclick="window.location=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_themes.php\'">' . lang('Cancel') . '</button>
                                        <button type="submit" name="delete" value="Delete" formnovalidate class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('theme')))) . '"><span class="material-icons me-3" aria-hidden="true">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>'
                    ) . '
                </form>
                    </div>
                </div>
            
</main>' . output_footer();
        
        $liveform->remove_form('edit_theme_file');
    }

} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // Create new liveform
    $liveform_view_themes = new liveform('view_themes');
    
    // The stored name: the file the delete removes from the disk.
    $original_name = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $liveform->get_field_value('id') . "'");

    // The row every write below acts on, for its Audit Trail line.
    $audit_id = (int) $liveform->get_field_value('id');
    
    // if file was selected for delete
    if (!empty($_POST['delete'])) {
        // delete file row
        $audit_before = pg_audit_fetch('files', $audit_id);
        $query = "DELETE FROM files WHERE id = ?";
        $result=db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed');
        pg_audit_record('delete', 'files', $audit_id, $audit_before);
        
        // delete file's system css properties
        $query = "DELETE FROM system_theme_css_rules WHERE file_id = ?";
        $result=db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');

        db("DELETE FROM preview_styles WHERE theme_id = '" . escape($liveform->get_field_value('id')) . "'");
        
        // Delete the file under its stored name; the posted name may have
        // been edited in the same submit and does not exist on disk.
        @unlink(FILE_DIRECTORY_PATH . '/' . $original_name);
        
        // log activity, add a notice to the liveform, and then send the user back to the view themes screen
        log_activity(lang(array('string' => 'theme file ({var:1}) was deleted', 'vars' => array($original_name))), $_SESSION['sessionusername']);
        $liveform_view_themes->add_notice(lang('The theme file was deleted successfully.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_themes.php');
    
    // else then the file was selected to be saved, so save the file
    } else {
        // The name rule, the rename on the disk, the activation and the
        // record update are shared with the All Themes list's quick edit.
        pg_theme_file_form_validate($liveform);

        if ($liveform->check_form_errors() == false) {
            $saved = pg_theme_file_form_save($liveform, (int) $user['id']);
        }

        // if there is an error, forward user back to edit theme file screen
        if (($liveform->check_form_errors() == true) || !$saved['ok']) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_theme_file.php?id=' . $liveform->get_field_value('id'));
            exit();
        }

        $name = $saved['name'];
        $theme_file_missing = $saved['missing'];

        $notice = lang('The theme file was edited successfully.');

        // The name is the theme's address; say so when it is not the one typed.
        $saved_as = pg_file_name_saved_as_notice(trim((string) $liveform->get_field_value('name')), $name);

        if ($saved_as !== '') {
            $notice .= ' ' . $saved_as;
        }

        $liveform_view_themes->add_notice($notice);

        if ($theme_file_missing) {
            $liveform_view_themes->add_warning(lang('The file is recorded here but is not on the disk.'));
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_themes.php');
    }
    
    // clear the liveform
    $liveform->remove_form();
}