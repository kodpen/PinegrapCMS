<?php
/**
 * Pinegrap - Enterprise Website Platform
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
include_once('liveform.class.php');
$liveform_view_design_files = new liveform('view_design_files');
$liveform = new liveform('edit_design_file');
validate_area_access($user, 'designer');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page. The screen renames and deletes the file, so the record must
// also be a design file stored under a plain name in the files folder: a
// File Manager file opened by a hand-typed URL is not found either.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$stored_name = ($id > 0) ? (string) db_value("SELECT name FROM files WHERE (id = '" . $id . "') AND (design = '1')") : '';
if (
    ($stored_name === '')
    || ($stored_name !== basename($stored_name))
    || (strpos($stored_name, '..') !== false)
) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

$from = '';
if(isset($_GET['from'])){
    $from = ($_GET['from'] ?? '');
}
if (empty($_POST['name'])) {
    $query = 
        "SELECT 
            files.name, 
            files.folder, 
            files.description, 
            files.type, 
            files.size,
            files.optimized,
            files.theme,
            folder.folder_archived
        FROM files 
        LEFT JOIN folder ON files.folder = folder.folder_id
        WHERE files.id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_array($result);
    
    $file_name = $row['name'];
    $file_folder = $row['folder'];
    $file_description = $row['description'];
    $file_type = $row['type'];
    $file_size = $row['size'];
    $optimized = $row['optimized'];
    $theme = $row['theme'];
    $folder_archived = $row['folder_archived'];
    
    $output_file_name = '';
    
    // if this files folder is archived, then output a notice next to the file name
    if ($folder_archived == '1') {
        $output_file_name = h($file_name . ' [' . lang('ARCHIVED') . ']');
    
    // else output the file name as normal
    } else {
        $output_file_name = h($file_name);
    }
    
    // Only set for images; other design files show neither.
    $output_thumbnail = '';
    $output_image_info = '';

    if ((mb_strtolower($file_type) == 'bmp') || (mb_strtolower($file_type) == 'gif') || (mb_strtolower($file_type) == 'jpg') || (mb_strtolower($file_type) == 'jpeg') || (mb_strtolower($file_type) == 'png') || (mb_strtolower($file_type) == 'tif') || (mb_strtolower($file_type) == 'tiff')) {
        // Get the dimensions of the image.
        $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $file_name);
        $image_width = $image_size[0];
        $image_height = $image_size[1];
        
        // Set the maximum dimension size for the image.
        $max_dimension = 75;
        
        // Call function to resize image.
        $thumbnail_dimensions = get_thumbnail_dimensions($image_width, $image_height, $max_dimension);
        
        // Output thumnail.
        $output_thumbnail = '<div class="col-12 md:col-auto"><a href="' . OUTPUT_PATH . h($file_name) . '" target="_blank" tabindex="-1" aria-hidden="true"><img style="width: 100px;height:100px;" class="img-fluid img-thumbnail lazy" alt="" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . OUTPUT_PATH . h($file_name) . '" /></a></div>';
        $output_image_info = ' | ' . $image_width . 'px - ' .  $image_height . 'px';

    }

     // If this file has not been optimized yet, and it is an image type that we support, then
    // show optimize button in button bar and edit image link

    $optimize_button = '';
    $image_buttons = '';
    $output_image_edit_link = '' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/image_editor_edit.php?file_name=' . h(urlencode($file_name)) . '&send_to=' . h(escape_javascript(REQUEST_URL)) . '';

    $file['type'] = mb_strtolower($row['type']);
    if (    ($file['type'] == 'jpg')
            or ($file['type'] == 'jpeg')
            or ($file['type'] == 'png')
            or ($file['type'] == 'gif')
            or ($file['type'] == 'bmp')
            or ($file['type'] == 'tiff')
            or ($file['type'] == 'webp')
        ){
             if (!$optimized &&(extension_loaded('imagick') || extension_loaded('gd'))) {
                $optimize_button =
                    '<a class="btn btn-link pg-link-secondary py-0 mb-3" href="optimize.php?id=' . h($_GET['id']) . get_token_query_string_field() . '"><i class="bi bi-rocket bi-me-2"></i>' . lang('Optimize this image') . '</a>';
            }
            $image_buttons = $optimize_button . ' <a class="btn btn-link pg-link-secondary py-0 mb-3" href="' . $output_image_edit_link . '"><i class="bi bi-flower2 bi-me-2"></i>' . lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor')) )) . '</a>';
    }

    // Convert file size to a user friendly output.
    $output_file_size = convert_bytes_to_string($file_size);
    
    // get file extension
    $file_extension = mb_strtolower(mb_substr($file_name, mb_strrpos($file_name, '.') + 1));
    
    // Images and other formats without an editor get no edit button.
    $output_edit_file_button = '';

    // if the file is either a CSS or JS file, then output the edit file button
    if (($file_extension == 'css') || ($file_extension == 'js')) {
        $edit_file_button_label = '';
        $edit_file_button_location = '';
    
        // if this is a CSS file, then output the CSS or Theme label and location
        if ($file_extension == 'css') {
            // check to see if this is a system theme
            $query = "SELECT COUNT(id) FROM system_theme_css_rules WHERE file_id = ?";
            $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
            $row = mysqli_fetch_row($result);
        
            // if this is a system theme then output the edit theme button
            if ($row[0] > 0) {
                $edit_file_button_value = 'Edit Theme';
                $edit_file_button_label = lang('Edit Theme');
                $edit_file_button_location = 'edit_theme_css.php?id=' . urlencode($_GET['id']) . '&send_to=' . urlencode(get_request_uri()) . '&from=edit_design_file';
                $system_theme = true;
            } else {
                // else output the edit css button
                $edit_file_button_value = 'Edit CSS';
                $edit_file_button_label = lang('Edit CSS');
                $edit_file_button_location = 'edit_theme_css.php?id=' . urlencode($_GET['id']) . '&send_to=' . urlencode(get_request_uri()) . '&from=edit_design_file';
                $system_theme = false;
            }
        
        } else {
            // else this is a JS file so output the JS label and location
            $edit_file_button_value = 'Edit JavaScript';
            $edit_file_button_label = lang('Edit JavaScript');
            $edit_file_button_location = 'edit_javascript.php?id=' . urlencode($_GET['id']) . '&send_to=' . urlencode(get_request_uri());
        }
    
        $output_edit_file_button = '<button type="button" value="' . $edit_file_button_value . '" class="btn my-1 btn-solid theme-primary" OnClick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/' . $edit_file_button_location)) . '\'"><span class="material-icons me-3">edit</span><span class="pg-btn-label">' . $edit_file_button_label . '</span></button>';
    
    }
    
    // additional formats handled by editor_edit_file.php
    elseif (in_array($file_extension, array('json', 'txt', 'svg', 'xml'))) {
        $edit_file_button_value = 'Edit ' . strtoupper($file_extension);
        $edit_file_button_label = lang( array('string'=>'Edit {var:1}','vars'=>strtoupper($file_extension)) );
        $edit_file_button_location = 'editor_edit_file.php?id=' . urlencode($_GET['id']) . '&send_to=' . urlencode(get_request_uri()) . '&from=edit_design_file';
    
        $output_edit_file_button = '<button type="button" value="' . $edit_file_button_value . '" class="btn my-1 btn-solid theme-primary" OnClick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/' . $edit_file_button_location)) . '\'"><span class="material-icons me-3">edit</span><span class="pg-btn-label">' . $edit_file_button_label . '</span></button>';
    }

    // A CSS file shows the Theme switch; a system theme cannot change it.
    $theme_row = null;

    if (mb_strtolower($file_type) == 'css') {
        $theme_row = array('checked' => ($theme == 1), 'disabled' => ($system_theme == true));
    }
    
    echo
    pg_page_shell(
        array(
            'title'=> h($file_name) . ' | ' . lang('Edit Design File'),
            'extra classes'=>'designer',
            'icon'=>'design', 
            'heading'=> lang('Edit Design File'),
            'heading_description' => lang('Rename, move, delete, or download this design file. (A rename/delete will require any links to this file to be updated. This file will not be viewable to site visitors if placed in a folder that is not public.)'),
            'cancel'=>array(
                'enable'=>'true',
                'title'=>lang('Return to Design Files'),
                'url'=>OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php'
            )
        ,
            'breadcrumb' => array(array('label' => lang('All Design Files'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php'), array('label' => lang('Edit Design File'))),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">
<div class="row mb-3">
                            ' . $output_thumbnail . '
                            <div class="col-12 md:col">
                                
                                <p class="p-0 m-0">' . lang('File Size') . ': '. $output_file_size . $output_image_info . '</p>
                                <p class="p-0 m-0 ' . h(get_access_control_type($file_folder)) . '">' . lang('Access') . ': ' . h(get_access_control_type_name(get_access_control_type($file_folder))) . '</p>
                            </div>
                        </div>
                        <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                ' . $image_buttons . '
                                <a class="btn btn-link pg-link-secondary py-0 mb-3"  href="' . OUTPUT_PATH . h($file_name) . '" target="_blank"><span class="material-icons me-1">open_in_browser</span>' . lang('Open in New Tab') . '</a>
                                <a class="btn btn-link pg-link-secondary py-0 mb-3" download="' . h($file_name) . '" href="' . OUTPUT_PATH . h($file_name) . '" ><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang('Download') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form action="edit_design_file.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '"><input type="hidden" name="from" value="' . h($from) . '">
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 sm:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('File Name') . '</label>
                                            <input value="' . h($file_name) . '" type="text" name="name" id="name" class="form-control " maxlength="100"/>
                                        </div>
                                    </div>
                                </div>
                            </div> 
                        </div>
                        <div class="col-12 md:col-6">' . pg_design_file_form_access_card(select_folder($file_folder), array('design' => true, 'theme' => $theme_row)) . '
                        </div>
                        <div class="col-12 md:col-6">' . pg_design_file_form_description_card($file_description) . '
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_edit_file_button . '
                                <button type="submit" name="delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('design file')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' . output_footer();
    
    $liveform->remove_form('edit_design_file');

} else {
    validate_token_field();

    // Delete and rename act on the record and the stored name the gate
    // above checked.
    $_POST['id'] = $id;

    $name = prepare_file_name($_POST['name'] ?? '');
    
    // if file was selected for delete
    if (!empty($_POST['delete']))
    {
        // delete file row
        $query = "DELETE FROM files WHERE id = ?";
        $result=db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed');
        
        // check to see if this is a system theme
        $query = "SELECT COUNT(id) FROM system_theme_css_rules WHERE file_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        
        // if this is a system theme then delete it's css theme properties from the database
        if ($row[0] > 0) {
            // delete file's system css properties
            $query = "DELETE FROM system_theme_css_rules WHERE file_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        }

        db("DELETE FROM preview_styles WHERE theme_id = '" . escape($_POST['id'] ?? '') . "'");

        // Delete the file under its stored name; the posted name may have
        // been edited in the same submit and does not exist on disk.
        @unlink(FILE_DIRECTORY_PATH . '/' . $stored_name);
        
        log_activity(lang(array('string'=>'design file ({var:1}) was deleted','vars'=>$stored_name)), $_SESSION['sessionusername']);
        $notice = lang('The design file was deleted successfully.');
    }
    else
    {
        // if file name is invalid, output error
        if ($name == '.htaccess') {
            output_error(lang('File name is invalid') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        if (check_name_availability(array('name' => $name, 'ignore_item_id' => $_POST['id'], 'ignore_item_type' => 'file')) == false) {
            output_error( lang(array('string'=>'{var:1} already exists. Please choose a different file name.','vars'=>array(h($name)) )) . '  <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // get file extension
        $array_file_extension = explode('.', $name);
        $size_of_array = count($array_file_extension);
        $file_extension = $array_file_extension[$size_of_array - 1];

        $sql_theme = "";

        // If this is a CSS file and this is not a system theme,
        // then update theme property.
        if (
            (mb_strtolower($file_extension) == 'css')
            && (db_value("SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . escape($_POST['id'] ?? '') . "'") == 0)
        ) {
            $sql_theme = "theme = '" . escape($_POST['theme'] ?? '') . "',";
        }
        
        // update file
        $query =
            "UPDATE files 
            SET 
                name = '" . escape($name) . "',
                folder = '" . escape($_POST['folder'] ?? '') . "', 
                description = '" . escape($_POST['description'] ?? '') . "',
                type = '" . escape($file_extension) . "',
                design = '" . escape($_POST['design'] ?? '') . "',
                $sql_theme
                timestamp = UNIX_TIMESTAMP(), 
                user = '" . $user['id'] . "' 
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        
        $result=mysqli_query(db::$con, $query) or output_error('Query failed');
        
        // Rename the file when its name changed and it is on the disk (a
        // file recorded here but missing from the disk has nothing to rename).
        if (($name !== $stored_name) && is_file(FILE_DIRECTORY_PATH . '/' . $stored_name)) {
            rename(FILE_DIRECTORY_PATH . '/' . $stored_name, FILE_DIRECTORY_PATH . '/' . $name);
        }


        log_activity(lang(array('string'=>'design file ({var:1}) was modified','vars'=>$name)), $_SESSION['sessionusername']);

        $notice = lang('The design file was edited successfully.');

    }
    
    $liveform_view_design_files->add_notice($notice);
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_design_files.php');
}
?>
