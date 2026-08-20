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
$liveform = new liveform('edit_file');
validate_area_access($user, 'user');

$from = '';
if(isset($_GET['from'])){
    $from = ($_GET['from'] ?? '');
}


$liveform_view_files = new liveform('view_files');
// get file's folder in order to validate folder access
$result = mysqli_query(db::$con, "SELECT design, folder FROM files WHERE id = '" . escape($_REQUEST['id']) . "'") or output_error('Query failed');
$row = mysqli_fetch_assoc($result);

// if the user does not have edit rights to this file's folder,
// or this file is a design file and the user is not a designer or administrator,
// then log activity and output error
if (
    (check_edit_access($row['folder']) == false)
    ||
    (
        ($row['design'] == 1)
        && ($user['role'] > 1)
    )
) {
    log_activity(lang('access denied because user does not have access to file'), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

if (!isset($_POST['name'])) {
    $query = 
        "SELECT 
            files.name,
            files.folder,
            files.description,
            files.type,
            files.size,
            files.design,
            files.optimized,
            folder.folder_archived
        FROM files 
        LEFT JOIN folder ON files.folder = folder.folder_id
        WHERE files.id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_array($result);
    
    $file_folder = $row['folder'];
    $file_name = $row['name'];
    $design = $row['design'];
    $optimized = $row['optimized'];
    $folder_archived = $row['folder_archived'];
    // Get file content
    $code = file_get_contents(FILE_DIRECTORY_PATH . '/' . $file_name);

    $output_file_name = '';
    
    // if this files folder is archived, then output a notice next to the file name
    if ($folder_archived == '1') {
        $output_file_name = h($file_name . ' [' . lang('ARCHIVED') . ']');
    
    // else output the file name as normal
    } else {
        $output_file_name = h($file_name);
    }

    

    $output_content_based_classes = 'col-md';
    
    $output_thumbnail = '';
    $output_image_info = '';
    // Get file path
    $file_path = FILE_DIRECTORY_PATH . '/' . $file_name;

    if (mb_strtolower($row['type']) == 'pdf') {
        // PDF preview
        $output_thumbnail = '
            <div class="col-12">
                <div class="card my-4">
                    <div class="ratio ratio-16x9">
                        <embed 
                            src="' . OUTPUT_PATH . $file_name . '" 
                            type="application/pdf" 
                            class="w-100 h-100" />
                    </div>
                </div>
            </div>';
            
    } elseif (in_array(mb_strtolower($row['type']), ['mp4', 'webm'])) {
        // Video preview
        $accent_color = get_dominant_area_color(FILE_DIRECTORY_PATH . '/' . $file_name);
        $output_thumbnail = '
            <div class="col-12">
                <div class="card my-4 position-relative overflow-hidden" style="background-color:' . h($accent_color) . ';">
                    <div class="ratio ratio-16x9">
                        <video 
                            src="' . OUTPUT_PATH . $file_name . '" 
                            class="w-100 h-100" 
                            controls 
                            preload="metadata" 
                            playsinline>
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            </div>';
            
 
    } elseif (in_array(mb_strtolower($row['type']), ['mp3','wav','ogg'])) {
        // Audio preview
        $output_thumbnail = '
            <div class="col-12">
                <div class="card my-4">
                    <audio controls class="w-100">
                        <source src="' . OUTPUT_PATH . $file_name . '" type="audio/' . mb_strtolower($row['type']) . '">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>';
            
    } elseif (mb_strtolower($row['type']) == 'ico') {
        // ICO preview
        $output_thumbnail = '
            <div class="col-auto">
                <div class="card my-4 w-auto d-inline-flex p-3">
                    <img 
                        src="' . OUTPUT_PATH . $file_name . '" 
                        class="img-fluid" 
                        alt="icon preview" 
                        style="max-width:128px; height:auto;" />
                   
                </div>
            </div>';
    }

    // If file type is an image.
    if (
            (mb_strtolower($row['type']) == 'bmp') ||
            (mb_strtolower($row['type']) == 'gif') ||
            (mb_strtolower($row['type']) == 'jpg') ||
            (mb_strtolower($row['type']) == 'webp') ||
            (mb_strtolower($row['type']) == 'svg') ||
            (mb_strtolower($row['type']) == 'jpeg') ||
            (mb_strtolower($row['type']) == 'png') ||
            (mb_strtolower($row['type']) == 'tif')
    ) {

        $output_content_based_classes = 'col-md';
        
        $accent_color = get_dominant_area_color(FILE_DIRECTORY_PATH . '/' . $file_name);
        // Set default values
        $image_width = '';
        $image_height = '';
        $data_src = '';
        // If SVG, parse XML manually
        if (mb_strtolower($row['type']) == 'svg') {
            $svg_content = @file_get_contents($file_path);
            if ($svg_content !== false) {
                $svg = @simplexml_load_string($svg_content);
                if ($svg !== false) {
                    $attributes = $svg->attributes();
                    if (isset($attributes->width)) {
                        $image_width = preg_replace('/[^0-9.]/', '', (string)$attributes->width);
                    }
                    if (isset($attributes->height)) {
                        $image_height = preg_replace('/[^0-9.]/', '', (string)$attributes->height);
                    }
                }
            }
            // Output version check
            $data_src = OUTPUT_PATH . $file_name . '?v=' . @filemtime(FILE_DIRECTORY_PATH . '/' . $file_name);

        } else {
            // For raster formats, use getimagesize
            $image_size = @getimagesize($file_path);
            if ($image_size !== false) {
                $image_width = $image_size[0];
                $image_height = $image_size[1];
            }
            
            
            // Output version check
            $data_src = OUTPUT_PATH . $file_name . '?v=' . @filemtime(FILE_DIRECTORY_PATH . '/' . $file_name);
            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: 0");
        }
        // Set the maximum dimension size for the image.
        $max_dimension = 1920;
        // Call function to resize image if dimensions are valid
        if (is_numeric($image_width) && is_numeric($image_height)) {
            $thumbnail_dimensions = get_thumbnail_dimensions($image_width, $image_height, $max_dimension);
            $output_image_info = ' | ' . $image_width . 'px - ' . $image_height . 'px';
        }

       
        
        

        $output_thumbnail = '
        <div class="col-12">
            <div class="card my-4 position-relative overflow-hidden" style="background-color:' . h($accent_color) . ';">
                <a href="' . OUTPUT_PATH . $file_name . '" class="stretched-link" target="_blank"></a>
                <img style="width: auto; height: auto; max-width: 100%; max-height: 300px; position: relative; z-index: 1;" class="card-img object-fit-contain lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . $data_src . '" />
            </div>
        </div>';

    }

    // If this file has not been optimized yet, and it is an image type that we support, then
    // show optimize button in button bar and edit image link

    $optimize_button = '';
    $image_buttons = '';
    $output_convert_rows = '';
    $output_image_edit_link = '' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/image_editor_edit.php?file_name=' . h($file_name) . '&send_to=' . h(escape_javascript(REQUEST_URL)) . '';
    // Convert file size to a user friendly output.
    $output_file_size = convert_bytes_to_string ($row['size']);
    // get file extension
    $file_extension = mb_strtolower(mb_substr($file_name, mb_strrpos($file_name, '.') + 1));
    $file['type'] = mb_strtolower($row['type']);

    // Map extension to CodeMirror mode
    switch ($file_extension) {
        case 'css':
            $code_type = 'css';
            break;
        case 'js':
            $code_type = 'javascript';
            break;
        case 'json':
            $code_type = 'application/json';
            break;
        case 'svg':
        case 'xml':
            $code_type = 'xml';
            break;
        case 'txt':
        case 'key':
        case 'pub':
        default:
            $code_type = 'text/plain';
            break;
    }
    $output_file_content_row = '';
    $output_no_file_editor_classes = 'col-md-6'; //default


    if (in_array($file_extension, array('json', 'txt', 'key','pub', 'svg', 'xml','css','js') ) ){

        $edit_file_button_value = 'Edit ' . strtoupper($file_extension);
        $output_file_content_row = '
        <div class="col-12 col-md-8">
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('File Content') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 my-2">
                            <label class="form-label">' . lang( array('string'=>'{var:1} file','vars'=>strtoupper($file_extension)) )  . '</label>
                            <div id="edit_custom">
                                <textarea name="code" id="code" rows="25" cols="60" wrap="off">' . h($code) . '</textarea>
                                ' . get_codemirror_includes() . '
                                ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => $code_type)) . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        $edit_file_button_label = lang( array('string'=>'Edit {var:1}','vars'=>strtoupper($file_extension)) );
        $output_no_file_editor_classes = 'col-md-12';
    }


    // SVG → raster conversion UI
    if ($file['type'] === 'svg') {
        
        $output_content_based_classes = 'col-md-4';
        $svg_w = (is_numeric($image_width)  && $image_width  > 0) ? (int)$image_width  : 0;
        $svg_h = (is_numeric($image_height) && $image_height > 0) ? (int)$image_height : 0;
        $output_convert_rows = '
        <div class="col-12 ' . $output_no_file_editor_classes . '">
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Convert') . '
                </div>
                <div class="card-body">
                    <div class="col-12 my-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input collapse-switcher" type="checkbox" id="convert_svg_raster" name="convert_svg_raster" value="1" data-bs-target="#convert_svg_raster_row">
                            <label class="form-check-label" for="convert_svg_raster">' . lang('Convert this SVG to PNG, JPEG or WebP') . '</label>
                        </div>
                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="convert_svg_raster_row">
                            <div class="popover-arrow" style="position:absolute;left:0;transform:translate(59px,0)"></div>
                            <div class="popover-body">
                                <div class="row g-2 align-items-end">
                                    <div class="col-auto">
                                        <label class="form-label mb-1">' . lang('Format') . '</label>
                                        <select class="form-select form-select-sm" name="svg_convert_format" id="svg_convert_format">
                                            <option value="png">PNG</option>
                                            <option value="jpeg">JPEG</option>
                                            <option value="webp">WebP</option>
                                        </select>
                                    </div>
                                    ' . ($svg_w > 0 ? '<div class="col-auto align-self-center mt-3"><small class="text-muted">' . $svg_w . ' &times; ' . $svg_h . ' px</small></div>' : '') . '
                                    <div class="col-12 mt-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="svg_convert_create_copy" name="svg_convert_create_copy" value="1" checked>
                                            <label class="form-check-label" for="svg_convert_create_copy">' . lang('Create a copy instead of replacing the original') . '</label>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="svg_convert_data"   id="svg_convert_data">
                                <input type="hidden" name="svg_convert_width"  id="svg_convert_width"  value="' . $svg_w . '">
                                <input type="hidden" name="svg_convert_height" id="svg_convert_height" value="' . $svg_h . '">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    $output_rotate_image_button ='';
    if (    ($file['type'] == 'jpg')
            or ($file['type'] == 'jpeg')
            or ($file['type'] == 'png')
            or ($file['type'] == 'gif')
            or ($file['type'] == 'bmp')
            or ($file['type'] == 'tiff')
            or ($file['type'] == 'webp')
        ){
             if (!$optimized &&(extension_loaded('imagick') || extension_loaded('gd'))) {
                $optimize_button ='<a class="btn btn-link link-secondary py-0 mb-2" data-loading-content="' . lang('Processing') . '" href="optimize.php?id=' . h($_GET['id']) . get_token_query_string_field() . '"><i class="bi bi-rocket bi-me-2"></i>' . lang('Optimize this image') . '( ' . calculate_optimizable_percent(FILE_DIRECTORY_PATH . '/' . $file_name) . ' )</a>';
            }

         
            $image_buttons = $optimize_button . '<button type="submit" class="btn btn-link link-secondary py-0 mb-2" name="rotate_left" value="1"><i class="bi bi-arrow-counterclockwise bi-me-2"></i>' . lang('Rotate Left') . '</button> <button type="submit" class="btn btn-link link-secondary py-0 mb-2" name="rotate_right" value="1"><i class="bi bi-arrow-clockwise bi-me-2"></i>' . lang('Rotate right') . '</button><a class="btn btn-link link-secondary py-0 mb-2" data-loading-content="' . lang('Loading') . '" href="' . $output_image_edit_link . '"><i class="bi bi-flower2 bi-me-2"></i>' . lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor')) )) . '</a>';
            if($file['type'] !== 'webp'){
                $output_convert_rows = '
                <div class="col-12 ' . $output_no_file_editor_classes . '">
                    <div class="card my-4 ">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Convert') . '
                        </div>
                        <div class="card-body">
                            <div class="col-12 my-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input collapse-switcher" type="checkbox" id="convert_webp" name="convert_webp" value="1" data-bs-target="#convert_webp_row">
                                    <label class="form-check-label" for="convert_webp">' . lang('Convert this image to .webp format') . '</label>
                                </div>
                                <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="convert_webp_row">
                                    <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                    <div class="popover-body">
                                        <div class="row">
                                            <div class="col-12 my-2">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="convert_webp_create_copy" name="convert_webp_create_copy" value="1">
                                                    <label class="form-check-label" for="convert_webp_create_copy">' . lang('Create a .webp copy instead of replace orginal image') . '</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';

            }


    }

    $output_design_rows = '';
    // if the user is a designer or administrator, then output design rows
    if ($user['role'] <= 1) {
        $output_design_checked = '';

        // if this file is a design file, then check the design check box
        if ($design == 1) {
            $output_design_checked = ' checked="checked"';
        }

        $output_design_rows =
            '<div class="col-12 my-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="design" name="design" value="1" ' . $output_design_checked . '>
                    <label class="form-check-label" for="design" data-bs-content="' . lang('Check if File is a Design File that is Managed by Site Designers') . '" title="" data-bs-original-title="' . lang('Design File') . '">' . lang('Design File') . ' (?)</label>
                </div>
            </div>';
    }


    $output =
    pg_page_shell(
        array(
            'title'=> lang('Edit File'),
            'extra classes'=>'file',
            'icon'=>'file', 
            'heading'=>lang('Edit File'),
            'cancel'=>array(
                'enable'=>'true',
                'title'=>lang('Return to Files'),
                'url'=>'view_files.php'
            ),
            'breadcrumb' => array(
                array('label' => lang('Files'), 'url' => 'view_files.php'),
                array('label' => $file_name),
            ),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form action="edit_file.php" method="post" submitshortcut="submit_save">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '"><input type="hidden" name="from" value="' . h($from) . '">
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />

                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<div class="row mb-2">
                                <div class="col-12 col-md">
                                    <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Rename file, move it to another folder, or change its description.') . '" title="' . lang('Edit File') . '">[' . $output_file_name . ']</h2>
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
                            <div class="row mb-2">
                                <div class="col-12">
                                    ' . $output_thumbnail . '
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-12 ' . $output_content_based_classes . '">
                            <div class="row">
                                <div class="col-12  ' . $output_no_file_editor_classes . '">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Main Informations') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 my-2">
                                                    <label for="name" class="form-label">' . lang('File Name') . '</label>
                                                    <input value="' . h($file_name) . '" type="text" name="name" id="name" class="form-control  add-header-content-updater"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div> 
                                </div>
                                <div class="col-12   ' . $output_no_file_editor_classes . '">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('File Access Control') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="col-12 my-2">
                                                <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                                <select class="form-select" id="folder" name="folder">' . select_folder($row['folder']) . '</select>
                                            </div>
                                            ' . $output_design_rows . '
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12  ' . $output_no_file_editor_classes . '">
                                    <div class="card my-4">
                                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                            ' . lang('Description') . '
                                        </div>
                                        <div class="card-body">
                                            <div class="col-12 my-2">
                                                <label for="description" class="form-label">' . lang('File Description / Photo Gallery Caption') . '</label>
                                                <textarea class="form-control" id="description" name="description" style="min-height:85px;">' . h($row['description']) . '</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                ' . $output_convert_rows . '
                            </div>
                        </div>
                        ' . $output_file_content_row . '
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save_and_return" name="submit_save_and_return" value="Save & Return" class="btn my-1  btn-primary " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">arrow_backsave</span><span class="btn-text" >' . lang(array('string'=>'Save & Return') ) . '</span></button>
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-success"><span class="material-icons me-2">save</span><span class="btn-text">' . lang('Save') . '</span></button>
                                <button type="submit" id="submit_duplicate" name="submit_duplicate" value="Duplicate" class="btn my-1 btn-secondary"><span class="material-icons me-2">control_point_duplicate</span><span class="btn-text">' . lang('Duplicate') . '</span></button>
                                <button type="submit" name="delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('file')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>
    ' . ($file['type'] === 'svg' ? '
    <script>
    (function () {
        var cb = document.getElementById("convert_svg_raster");
        if (!cb) return;
        var form = cb.closest("form");
        if (!form) return;

        form.addEventListener("submit", function (e) {
            if (!cb.checked) return;
            if (document.getElementById("svg_convert_data").value) return; // prevent double-submit

            e.preventDefault();
            e.stopImmediatePropagation();

            var format = document.getElementById("svg_convert_format").value;
            var svgW   = parseInt(document.getElementById("svg_convert_width").value, 10);
            var svgH   = parseInt(document.getElementById("svg_convert_height").value, 10);
            var svgUrl = ' . json_encode($data_src) . ';

            var img = new Image();
            img.crossOrigin = "anonymous";
            img.onload = function () {
                var w = svgW > 0 ? svgW : (img.naturalWidth  || 1024);
                var h = svgH > 0 ? svgH : (img.naturalHeight || w);
                var canvas = document.createElement("canvas");
                canvas.width  = w;
                canvas.height = h;
                var ctx = canvas.getContext("2d");
                if (format === "jpeg") { ctx.fillStyle = "#ffffff"; ctx.fillRect(0, 0, w, h); }
                ctx.drawImage(img, 0, 0, w, h);
                var mime = format === "jpeg" ? "image/jpeg" : (format === "webp" ? "image/webp" : "image/png");
                document.getElementById("svg_convert_data").value = canvas.toDataURL(mime, 0.92);
                form.submit();
            };
            img.onerror = function () {
                alert(' . json_encode(lang('SVG could not be rendered for conversion. Make sure the SVG file is valid.')) . ');
            };
            img.src = svgUrl;
        });
    }());
    </script>' : '') . output_footer();

print $output;
$liveform->remove_form('edit_file');
} else {
    validate_token_field();

    $file_id = escape($_POST['id'] ?? '');
    $result = mysqli_query(db::$con, "SELECT name FROM files WHERE id = '" . $file_id . "'") or output_error('Query failed');
    $row = mysqli_fetch_array($result);

    $name = prepare_file_name($_POST['name']);
    $file_path = FILE_DIRECTORY_PATH . '/' . $name;
    $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $editable_formats = array('json', 'txt', 'svg', 'xml', 'css', 'js');

    // DELETE
    if (!empty($_POST['delete'])) {
        mysqli_query(db::$con, "DELETE FROM files WHERE id = '" . $file_id . "'") or output_error('Query failed');
        mysqli_query(db::$con, "DELETE FROM system_theme_css_rules WHERE file_id = '" . $file_id . "'") or output_error('Query failed');
        db("DELETE FROM preview_styles WHERE theme_id = '" . $file_id . "'");
        @unlink(FILE_DIRECTORY_PATH . '/' . $row['name']);

        log_activity(lang(array('string'=>'file ({var:1}) was deleted','vars'=>$name)), $_SESSION['sessionusername']);

        include_once('liveform.class.php');
        $liveform = new liveform('view_files');
        $liveform->add_notice(lang('The file was deleted successfully.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_files.php');
        exit();
    }

    // INVALID NAME
    if ($name === '.htaccess') {
        output_error(lang('File name is invalid') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // DESIGN FIELD
    $sql_design = '';
    if ($user['role'] <= 1) {
        $sql_design = "design = '" . escape($_POST['design'] ?? '') . "',";
    }

    $sql_size = '';
    $image = '';


    
    // ROTATE IMAGE (left or right)
    if (!empty($_POST['rotate_left']) || !empty($_POST['rotate_right'])) {
        $rotate_left = !empty($_POST['rotate_left']);
        $rotated = false;

        // Imagick preferred — positive angle = clockwise
        if (class_exists('Imagick')) {
            try {
                $img = new Imagick($file_path);
                $img->rotateImage(new ImagickPixel('none'), $rotate_left ? -90 : 90);
                $img->writeImage($file_path);
                $img->destroy();
                $rotated = true;
            } catch (Exception $e) {
                $rotated = false;
            }
        }

        // GD fallback — positive angle = counter-clockwise (opposite of Imagick)
        if (!$rotated) {
            $angle_gd = $rotate_left ? 90 : -90;
            switch ($file_extension) {
                case 'jpg': case 'jpeg':
                    $img = imagecreatefromjpeg($file_path);
                    if ($img !== false) {
                        $rot = imagerotate($img, $angle_gd, 0);
                        imagejpeg($rot, $file_path, 90);
                        imagedestroy($img); imagedestroy($rot);
                        $rotated = true;
                    }
                    break;
                case 'png':
                    $img = imagecreatefrompng($file_path);
                    if ($img !== false) {
                        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
                        $rot = imagerotate($img, $angle_gd, $transparent);
                        imagesavealpha($rot, true);
                        imagepng($rot, $file_path);
                        imagedestroy($img); imagedestroy($rot);
                        $rotated = true;
                    }
                    break;
                case 'gif':
                    $img = imagecreatefromgif($file_path);
                    if ($img !== false) {
                        $rot = imagerotate($img, $angle_gd, 0);
                        imagegif($rot, $file_path);
                        imagedestroy($img); imagedestroy($rot);
                        $rotated = true;
                    }
                    break;
                case 'webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $img = imagecreatefromwebp($file_path);
                        if ($img !== false) {
                            $rot = imagerotate($img, $angle_gd, 0);
                            imagewebp($rot, $file_path, 90);
                            imagedestroy($img); imagedestroy($rot);
                            $rotated = true;
                        }
                    }
                    break;
                case 'bmp':
                    if (function_exists('imagecreatefrombmp')) {
                        $img = imagecreatefrombmp($file_path);
                        if ($img !== false) {
                            $rot = imagerotate($img, $angle_gd, 0);
                            imagejpeg($rot, $file_path, 90);
                            imagedestroy($img); imagedestroy($rot);
                            $rotated = true;
                        }
                    }
                    break;
            }
        }
    
        // Update DB with new size and timestamp
        $query = "UPDATE files SET
            size = '" . escape(filesize($file_path)) . "',
            timestamp = UNIX_TIMESTAMP(),
            user = '" . $user['id'] . "'
            WHERE id = '" . $file_id . "'";
        mysqli_query(db::$con, $query) or output_error('Query failed');
    
        // Log activity
        log_activity(lang(array('string'=>'file ({var:1}) was rotated','vars'=>$name)), $_SESSION['sessionusername']);
    
        // Notify user
        include_once('liveform.class.php');
        $liveform = new liveform('edit_file');
        $liveform->add_notice(lang('The file was rotated successfully.'));
    
        // Build redirect URL
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_file.php?id=' . urlencode($_POST['id']));
        exit();
    }

    // WEBP CONVERSION
    if (!empty($_POST['convert_webp'])) {
        switch ($file_extension) {
            case 'jpg': case 'jpeg': $image = imagecreatefromjpeg($file_path); break;
            case 'png': $image = imagecreatefrompng($file_path); break;
            case 'gif': $image = imagecreatefromgif($file_path); break;
            case 'bmp': $image = imagecreatefrombmp($file_path); break;
        }
    }

    // WEBP overwrite
    if (!empty($_POST['convert_webp']) && empty($_POST['convert_webp_create_copy']) && $image) {
        $name = prepare_file_name(pathinfo($name, PATHINFO_FILENAME) . '.webp');
        $file_path = FILE_DIRECTORY_PATH . '/' . $name;
        if (!check_name_availability(array('name' => $name, 'ignore_item_id' => $_POST['id'], 'ignore_item_type' => 'file'))) {
            output_error(lang(array('string'=>'{var:1} already exists. Please choose a different file name.','vars'=>array(h($name)))) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        imagewebp($image, $file_path);
        $file_extension = 'webp';
        $sql_size = "size = '" . escape(filesize($file_path)) . "',";
        imagedestroy($image);
    }

    // WEBP copy
    if (!empty($_POST['convert_webp']) && !empty($_POST['convert_webp_create_copy']) && $image) {
        $webpname = prepare_file_name(pathinfo($name, PATHINFO_FILENAME) . '.webp');
        $webpname = get_unique_name(array('name' => $webpname, 'type' => 'file'));
        $webp_path = FILE_DIRECTORY_PATH . '/' . $webpname;
        $sql_design_1 = ($user['role'] <= 1) ? "design," : '';
        $sql_design_2 = ($user['role'] <= 1) ? "'" . escape($_POST['design'] ?? '') . "'," : '';

        imagewebp($image, $webp_path);
        $size = filesize($webp_path);

        db("INSERT INTO files (
            name, folder, description, type, size, " . $sql_design_1 . " user, timestamp
        ) VALUES (
            '" . escape($webpname) . "',
            '" . escape($_POST['folder'] ?? '') . "',
            '" . escape($_POST['description'] ?? '') . "',
            'webp',
            '" . escape($size) . "',
            " . $sql_design_2 . "
            '" . USER_ID . "',
            UNIX_TIMESTAMP()
        )");

        log_activity(lang(array('string'=>'file ({var:1}) was created','vars'=>$webpname)), $_SESSION['sessionusername']);
        imagedestroy($image);
    }

    // SVG → RASTER CONVERSION
    if (!empty($_POST['convert_svg_raster']) && !empty($_POST['svg_convert_data'])) {
        $data_url = $_POST['svg_convert_data'];

        // Validate data URL format — only accept PNG, JPEG or WebP
        if (!preg_match('/^data:image\/(png|jpeg|webp);base64,([A-Za-z0-9+\/=]+)$/', $data_url, $m)) {
            output_error(lang('Invalid image data. Please try again.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        $target_ext  = ($m[1] === 'jpeg') ? 'jpg' : $m[1]; // png → png, webp → webp
        $image_bytes = base64_decode($m[2], true);

        if ($image_bytes === false || strlen($image_bytes) < 8) {
            output_error(lang('Failed to decode image data.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        $base_name = pathinfo($name, PATHINFO_FILENAME);
        $new_name  = prepare_file_name($base_name . '.' . $target_ext);

        $sql_design_1 = ($user['role'] <= 1) ? 'design,' : '';
        $sql_design_2 = ($user['role'] <= 1) ? "'" . escape(isset($_POST['design']) ? $_POST['design'] : '0') . "'," : '';

        if (!empty($_POST['svg_convert_create_copy'])) {
            // Save as a new file (copy)
            $new_name  = get_unique_name(array('name' => $new_name, 'type' => 'file'));
            $new_path  = FILE_DIRECTORY_PATH . '/' . $new_name;
            file_put_contents($new_path, $image_bytes);
            $new_size  = filesize($new_path);

            db("INSERT INTO files (name, folder, description, type, size, " . $sql_design_1 . " user, timestamp)
                VALUES (
                    '" . escape($new_name) . "',
                    '" . escape($_POST['folder'] ?? '') . "',
                    '" . escape($_POST['description'] ?? '') . "',
                    '" . escape($target_ext) . "',
                    '" . escape($new_size) . "',
                    " . $sql_design_2 . "
                    '" . USER_ID . "',
                    UNIX_TIMESTAMP()
                )");
            $new_id = mysqli_insert_id(db::$con);
            log_activity(lang(array('string' => 'file ({var:1}) was created', 'vars' => $new_name)), $_SESSION['sessionusername']);

            $liveform_redirect = new liveform('edit_file');
            $liveform_redirect->add_notice(lang('SVG was converted and saved as a new file.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_file.php?id=' . urlencode($new_id));
            exit();

        } else {
            // Overwrite: replace SVG with raster
            if (!check_name_availability(array('name' => $new_name, 'ignore_item_id' => $_POST['id'], 'ignore_item_type' => 'file'))) {
                output_error(lang(array('string' => '{var:1} already exists. Please choose a different file name.', 'vars' => array(h($new_name)))) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
            $new_path = FILE_DIRECTORY_PATH . '/' . $new_name;
            file_put_contents($new_path, $image_bytes);
            @unlink(FILE_DIRECTORY_PATH . '/' . $row['name']); // remove old SVG
            $new_size = filesize($new_path);

            db("UPDATE files SET
                name      = '" . escape($new_name) . "',
                type      = '" . escape($target_ext) . "',
                size      = '" . escape($new_size) . "',
                timestamp = UNIX_TIMESTAMP()
                WHERE id  = '" . $file_id . "'");

            log_activity(lang(array('string' => 'file ({var:1}) was converted to {var:2}', 'vars' => array($name, strtoupper($target_ext)))), $_SESSION['sessionusername']);

            $liveform_redirect = new liveform('edit_file');
            $liveform_redirect->add_notice(lang('SVG was converted successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_file.php?id=' . urlencode($_POST['id']));
            exit();
        }
    }

    // SAVE / SAVE & RETURN
    if (!empty($_POST['submit_save']) || !empty($_POST['submit_save_and_return'])) {
        // NAME CONFLICT
        if (!check_name_availability(array('name' => $name, 'ignore_item_id' => $_POST['id'], 'ignore_item_type' => 'file'))) {
            output_error(lang(array('string'=>'{var:1} already exists. Please choose a different file name.','vars'=>array(h($name)))) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        if (in_array($file_extension, $editable_formats) && isset($_POST['code'])) {
            $handle = fopen($file_path, 'w');
            fwrite($handle, $_POST['code']);
            fclose($handle);
            $sql_size = "size = '" . escape(filesize($file_path)) . "',";
        }

        $query = "UPDATE files SET
            name = '" . escape($name) . "',
            folder = '" . escape($_POST['folder'] ?? '') . "',
            description = '" . escape($_POST['description'] ?? '') . "',
            type = '" . escape($file_extension) . "',
            " . $sql_design . "
            " . $sql_size . "
            timestamp = UNIX_TIMESTAMP(),
            user = '" . $user['id'] . "'
            WHERE id = '" . $file_id . "'";
        mysqli_query(db::$con, $query) or output_error('Query failed');
            log_activity(lang(array('string'=>'file ({var:1}) was modified','vars'=>$name)), $_SESSION['sessionusername']);
        include_once('liveform.class.php');

        if (!empty($_POST['submit_save']) && ($_POST['submit_save'] ?? '') === 'Save') {
            $liveform = new liveform('edit_file');
            $liveform->add_notice(lang('The file was edited successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_file.php?id=' . urlencode($_POST['id']) . '&send_to=' . urlencode($_POST['send_to']));
            exit();
        } elseif (!empty($_POST['submit_save_and_return']) && ($_POST['submit_save_and_return'] ?? '') === 'Save & Return') {
            $liveform = new liveform('view_files');
            $liveform->add_notice(lang('The file was edited successfully.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_files.php');
            exit();
        }
    }

    // DUPLICATE
    if (!empty($_POST['submit_duplicate']) && ($_POST['submit_duplicate'] ?? '') === 'Duplicate') {
        $original_name = $row['name']; // always use last saved file from DB
        $new_file_name = prepare_file_name($original_name);
        $new_file_name = get_unique_name(array('name' => $new_file_name, 'type' => 'file'));
        $new_file_path = FILE_DIRECTORY_PATH . '/' . $new_file_name;
        $file_extension = strtolower(pathinfo($new_file_name, PATHINFO_EXTENSION));

        // Always copy from filesystem, never from $_POST['code']
        copy(FILE_DIRECTORY_PATH . '/' . $original_name, $new_file_path);

        // Fetch folder and description from DB
        $query = "SELECT folder, description FROM files WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        $sql_design = ($user['role'] <= 1) ? "'1'," : "'0',";

        // Insert new file record
        $query = "INSERT INTO files (
            name, folder, description, type, size, design, user, timestamp
        ) VALUES (
            '" . escape($new_file_name) . "',
            '" . escape($row['folder']) . "',
            '" . escape($row['description']) . "',
            '" . escape($file_extension) . "',
            '" . escape(filesize($new_file_path)) . "',
            " . $sql_design . "
            '" . $user['id'] . "',
            UNIX_TIMESTAMP()
        )";
        mysqli_query(db::$con, $query) or output_error('Query failed.');
        $new_file_id = mysqli_insert_id(db::$con);

        log_activity(
            lang(array('string' => 'a new copy of the file ({var:1}) was created', 'vars' => $original_name)),
            $_SESSION['sessionusername']
        );

        include_once('liveform.class.php');
        $liveform = new liveform('edit_file');
        $liveform->add_notice(lang('A new copy of the file has been created.'));

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_file.php?id=' . $new_file_id);
        exit();
    }

}