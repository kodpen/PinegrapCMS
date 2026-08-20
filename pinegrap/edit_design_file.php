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
include_once('liveform.class.php');
$liveform_view_design_files = new liveform('view_design_files');
$liveform = new liveform('edit_design_file');
validate_area_access($user, 'designer');
$from = '';
if(isset($_GET['from'])){
    $from = ($_GET['from'] ?? '');
}
if (!$_POST['name']) {
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
        WHERE files.id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
        $output_thumbnail = '<div class="col-12 col-md-auto"><a href="' . OUTPUT_PATH . $file_name . '" target="_blank"><img style="width: 100px;height:100px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . OUTPUT_PATH . $file_name . '" /></a></div>';
        $output_image_info = ' | ' . $image_width . 'px - ' .  $image_height . 'px';

    }

     // If this file has not been optimized yet, and it is an image type that we support, then
    // show optimize button in button bar and edit image link

    $optimize_button = '';
    $image_buttons = '';
    $output_image_edit_link = '' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/image_editor_edit.php?file_name=' . $output_file_name . '&send_to=' . h(escape_javascript(REQUEST_URL)) . '';

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
                    '<a class="btn btn-link link-secondary py-0 mb-2" data-loading-content="' . lang('Processing') . '" href="optimize.php?id=' . h($_GET['id']) . get_token_query_string_field() . '"><i class="bi bi-rocket bi-me-2"></i>' . lang('Optimize this image') . '</a>';
            }
            $image_buttons = $optimize_button . ' <a class="btn btn-link link-secondary py-0 mb-2" data-loading-content="' . lang('Loading') . '" href="' . $output_image_edit_link . '"><i class="bi bi-flower2 bi-me-2"></i>' . lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor')) )) . '</a>';
    }

    // Convert file size to a user friendly output.
    $output_file_size = convert_bytes_to_string($file_size);
    
    // get file extension
    $file_extension = mb_strtolower(mb_substr($file_name, mb_strrpos($file_name, '.') + 1));
    
    // if the file is either a CSS or JS file, then output the edit file button
    if (($file_extension == 'css') || ($file_extension == 'js')) {
        $edit_file_button_label = '';
        $edit_file_button_location = '';
    
        // if this is a CSS file, then output the CSS or Theme label and location
        if ($file_extension == 'css') {
            // check to see if this is a system theme
            $query = "SELECT COUNT(id) FROM system_theme_css_rules WHERE file_id = '" . escape($_GET['id']) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_row($result);
        
            // if this is a system theme then output the edit theme button
            if ($row[0] > 0) {
                $edit_file_button_value = 'Edit Theme';
                $edit_file_button_label = lang('Edit Theme');
                $edit_file_button_location = 'theme_designer.php?id=' . urlencode($_GET['id']) . '&send_to=' . urlencode(get_request_uri());
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
    
        $output_edit_file_button = '<button type="button" value="' . $edit_file_button_value . '" class="btn my-1 btn-primary" data-loading-content="' . lang('Loading') . '" OnClick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/' . $edit_file_button_location)) . '\'"><span class="material-icons me-2">edit</span><span class="btn-text">' . $edit_file_button_label . '</span></button>';
    
    }
    
    // additional formats handled by editor_edit_file.php
    elseif (in_array($file_extension, array('json', 'txt', 'svg', 'xml'))) {
        $edit_file_button_value = 'Edit ' . strtoupper($file_extension);
        $edit_file_button_label = lang( array('string'=>'Edit {var:1}','vars'=>strtoupper($file_extension)) );
        $edit_file_button_location = 'editor_edit_file.php?id=' . urlencode($_GET['id']) . '&send_to=' . urlencode(get_request_uri()) . '&from=edit_design_file';
    
        $output_edit_file_button = '<button type="button" value="' . $edit_file_button_value . '" class="btn my-1 btn-primary" data-loading-content="' . lang('Loading') . '" OnClick="window.location=\'' . h(escape_javascript(PATH . SOFTWARE_DIRECTORY . '/' . $edit_file_button_location)) . '\'"><span class="material-icons me-2">edit</span><span class="btn-text">' . $edit_file_button_label . '</span></button>';
    }

    $output_theme_rows = '';

    // If this is a CSS file, then output theme property.
    if (mb_strtolower($file_type) == 'css') {
        $output_theme_checked = '';

        if ($theme == 1) {
            $output_theme_checked = ' checked="checked"';
        }

        $output_theme_disabled = '';

        if ($system_theme == true) {
            $output_theme_disabled = ' disabled="disabled"';
        }

        $output_theme_rows =
            '<div class="col-12 my-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="theme" name="theme" value="1"' . $output_theme_checked . $output_theme_disabled . '>
                    <label class="form-check-label" for="theme" data-bs-content="' . lang('Check if Design File should be included on All Themes screen and in Theme preview') . '" title="" data-bs-original-title="' . lang('Theme') . '">' . lang('Theme') . ' (?)</label>
                </div>
            </div>';
    }
    
    echo
    pg_page_shell(
        array(
            'title'=> h($file_name) . ' | ' . lang('Edit Design File'),
            'extra classes'=>'designer',
            'icon'=>'design', 
            'heading'=> lang('Edit Design File'),
            'cancel'=>array(
                'enable'=>'true',
                'title'=>lang('Return to Design Files'),
                'url'=>OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php'
            )
        ,
            'breadcrumb' => array(array('label' => lang('All Design Files'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_design_files.php'), array('label' => lang('Edit Design File'))),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<div class="row mb-2">
                            ' . $output_thumbnail . '
                            <div class="col-12 col-md">
                                <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Rename, move, delete, or download this design file. (A rename/delete will require any links to this file to be updated. This file will not be viewable to site visitors if placed in a folder that is not public.)') . '" title="' . lang('Edit Design File') . '">[' . $output_file_name . ']</h2>
                                <p class="p-0 m-0">' . lang('File Size') . ': '. $output_file_size . $output_image_info . '</p>
                                <p class="p-0 m-0 ' . h(get_access_control_type($file_folder)) . '">' . lang('Access') . ': ' . h(get_access_control_type_name(get_access_control_type($file_folder))) . '</p>
                            </div>
                        </div>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                ' . $image_buttons . '
                                <a class="btn btn-link link-secondary py-0 mb-2"  href="' . OUTPUT_PATH . $file_name . '" target="_blank"><span class="material-icons me-1">open_in_browser</span>' . lang('Open in New Tab') . '</a>
                                <a class="btn btn-link link-secondary py-0 mb-2" data-loading-content="' . lang('Downloading') . '" download="image" href="' . OUTPUT_PATH . $file_name . '" ><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang('Download') . '</a>
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
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-4 my-2">
                                            <label for="name" class="form-label">' . lang('File Name') . '</label>
                                            <input value="' . $file_name . '" type="text" name="name" id="name" class="form-control  add-header-content-updater" maxlength="100"/>
                                        </div>
                                    </div>
                                </div>
                            </div> 
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('File Access Control') . '
                                </div>
                                <div class="card-body">
                                    <div class="col-12 my-2">
                                        <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                        <select class="form-select" id="folder" name="folder">' . select_folder($file_folder) . '</select>
                                    </div>
                                    <div class="col-12 my-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="design" name="design" value="1" checked="checked">
                                            <label class="form-check-label" for="design" data-bs-content="' . lang('Check if File is a Design File that is Managed by Site Designers') . '" title="" data-bs-original-title="' . lang('Design File') . '">' . lang('Design File') . ' (?)</label>
                                        </div>
                                    </div>
                                    ' . $output_theme_rows . '
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4 ">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Description') . '
                                </div>
                                <div class="card-body">
                                    <div class="col-12 my-2">
                                        <label for="description" class="form-label">' . lang('File Description / Photo Gallery Caption') . '</label>
                                        <textarea class="form-control" id="description" name="description" style="min-height:85px;">' . h($file_description) . '</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_edit_file_button . '
                                <button type="submit" name="delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('design file')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' . output_footer();
    
    $liveform->remove_form('edit_design_file');

print $output;

} else {
    validate_token_field();
    
    $result=mysqli_query(db::$con, "SELECT name FROM files WHERE id = '" . escape($_POST['id'] ?? '') . "'") or output_error('Query failed');
    $row=mysqli_fetch_array($result);

    $name = prepare_file_name($_POST['name']);
    
    // if file was selected for delete
    if ($_POST['delete'])
    {
        // delete file row
        $query = "DELETE FROM files WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result=mysqli_query(db::$con, $query) or output_error('Query failed');
        
        // check to see if this is a system theme
        $query = "SELECT COUNT(id) FROM system_theme_css_rules WHERE file_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        
        // if this is a system theme then delete it's css theme properties from the database
        if ($row[0] > 0) {
            // delete file's system css properties
            $query = "DELETE FROM system_theme_css_rules WHERE file_id = '" . escape($_POST['id'] ?? '') . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }

        db("DELETE FROM preview_styles WHERE theme_id = '" . escape($_POST['id'] ?? '') . "'");

        // Delete file on file system.
        @unlink(FILE_DIRECTORY_PATH . '/' . $name);
        
        log_activity(lang(array('string'=>'design file ({var:1}) was deleted','vars'=>$name)), $_SESSION['sessionusername']);
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
        
        // rename file's name
        rename(FILE_DIRECTORY_PATH . '/' . $row['name'], FILE_DIRECTORY_PATH . '/' . $name);


        log_activity(lang(array('string'=>'design file ({var:1}) was modified','vars'=>$name)), $_SESSION['sessionusername']);

        $notice = lang('The design file was edited successfully.');

    }
    
    $liveform_view_design_files->add_notice($notice);
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_design_files.php');
}
?>
