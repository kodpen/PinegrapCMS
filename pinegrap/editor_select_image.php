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
$output_images = '';
$output_warning = '';
$folder_id = 0;


$url_parameters = '';
if(($_GET['CKEditorFuncNum'] ?? '')){
    $url_parameters = 'CKEditorFuncNum=' . h(urlencode(($_GET['CKEditorFuncNum'] ?? '')));
}

if(($_GET['SingleImage'] ?? '')){
    if($url_parameters != ''){
        $url_parameters .= '&';
    }
    $url_parameters .= 'SingleImage=' . h(urlencode(($_GET['SingleImage'] ?? '')));
}

if(($_GET['file_input_name'] ?? '')){
    if($url_parameters != ''){
        $url_parameters .= '&';
    }
    $url_parameters .= 'file_input_name=' . h(urlencode(($_GET['file_input_name'] ?? '')));
}


$max_file_uploads = ini_get('max_file_uploads');

// NOTE: $max_number_of_files is never assigned anywhere in this script, so the dropzone limit
// below is never emitted.  Starting it at zero keeps that behaviour and stops the warning.
$max_number_of_files = 0;

$output_maxfiles = '';
if ($max_number_of_files > 0) {
    //for dropzone
    $output_maxfiles = 'maxFiles: ' . $max_number_of_files . ',';
}

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['editor_select_image'][$key] = trim($value);
    }
}

// If a screen was passed and it is a positive integer, then use it.
// These checks are necessary in order to avoid SQL errors below for a bogus screen value.
if (
    isset($_REQUEST['screen'])
    and $_REQUEST['screen']
    and is_numeric($_REQUEST['screen'])
    and $_REQUEST['screen'] > 0
    and $_REQUEST['screen'] == round($_REQUEST['screen'])
) {
    $screen = (int) $_REQUEST['screen'];

// Otherwise, use the default, which is the first screen.
} else {
    $screen = 1;
}

// If sort is not set, set to "newest".
if (isset($_SESSION['software']['editor_select_image']['sort']) == false) {
    $_SESSION['software']['editor_select_image']['sort'] = 'newest';
}

// If folder is not set yet, set to "all".
if (isset($_SESSION['software']['editor_select_image']['folder_id']) == false) {
    $_SESSION['software']['editor_select_image']['folder_id'] = 'all';
}

// If access control type is not set yet, set to "all".
if (isset($_SESSION['software']['editor_select_image']['access_control_type']) == false) {
    $_SESSION['software']['editor_select_image']['access_control_type'] = 'all';
}



$sort_link_href = '#!';
$sort_link_icon = '';
$sort_link_title = '';

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['editor_select_image']['sort']) == false) {
    $_SESSION['software']['editor_select_image']['sort'] = '';
}

switch (($_SESSION['software']['editor_select_image']['sort'] ?? '')) {
    
    case 'newest':
        $sort_link_href = 'editor_select_image.php?sort=oldest&' . $url_parameters;
        
        $sort_link_icon = 'bi-sort-down';
        $sort_link_title = lang('Newest');
        break;

    case 'oldest':
        $sort_link_href = 'editor_select_image.php?sort=alphabetical&' . $url_parameters;
        $sort_link_icon = 'bi-sort-up';
        $sort_link_title = lang('Oldest');
        break;

    case 'alphabetical':
        $sort_link_href = 'editor_select_image.php?sort=newest&' . $url_parameters;
        
        $sort_link_icon = 'bi-sort-alpha-down';
        $sort_link_title = lang('Alphabetical');
        break;
}
$output_sort_link = '<a href="' . $sort_link_href . '" title="' . $sort_link_title . '" class="nav-link nav-link-sm no-popover"><span class="has-notification-icon bi ' . $sort_link_icon . ' fs-6"></span></a>';




// if the user clicked on the clear button, then clear the search
if (isset($_GET['clear']) == true) {
    $_SESSION['software']['editor_select_image']['query'] = '';
}

$output_clear_button = '';

// if there is a search query, then prepare to output clear button
if ((isset($_SESSION['software']['editor_select_image']['query']) == true) && (($_SESSION['software']['editor_select_image']['query'] ?? '') != '')) {
    $output_clear_button = ' <button type="button" value="Clear" title="' . lang(array('string'=>'Clear') ) . '" class="btn btn-link link-danger position-absolute top-0 end-0 py-0 px-0 me-1 bi bi-trash" onclick="document.location.href = \'' . h(escape_javascript($_SERVER['PHP_SELF'])) . '?clear=true&' . $url_parameters . '\'"></button>';
}

$folders_that_user_has_access_to = array();

if (USER_ROLE == 3) {
    $folders_that_user_has_access_to = get_folders_that_user_has_access_to(USER_ID);
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['editor_select_image']['sort']) == false) {
    $_SESSION['software']['editor_select_image']['sort'] = '';
}

switch (($_SESSION['software']['editor_select_image']['sort'] ?? '')) {
    default:
    case 'newest':
        $order_by =
            "files.timestamp DESC,
            files.name ASC";
        break;

    case 'oldest':
        $order_by =
            "files.timestamp ASC,
            files.name ASC";
        break;

    case 'alphabetical':
        $order_by = "files.name ASC";
        break;
}

$where = "";

// If there is a search query and it is not blank, then prepare filter.
if ((isset($_SESSION['software']['editor_select_image']['query']) == true) && (($_SESSION['software']['editor_select_image']['query'] ?? '') != '')) {
    $where .= "AND (LOWER(CONCAT_WS(',', files.name, folder.folder_name, user.user_username)) LIKE '%" . escape(escape_like(mb_strtolower(($_SESSION['software']['editor_select_image']['query'] ?? '')))) . "%')";
}

// Get all images.
$all_images = db_items(
    "SELECT
        files.id as file_id,
        files.name,
        files.size,
        files.folder AS folder_id,
        folder.folder_name,
        files.timestamp AS last_modified_timestamp,
        user.user_username AS last_modified_username
    FROM files
    LEFT JOIN folder ON files.folder = folder.folder_id
    LEFT JOIN user ON files.user = user.user_id
    WHERE
        (
            (files.type = 'gif')
            || (files.type = 'jpg')
            || (files.type = 'jpeg')
            || (files.type = 'png')
            || (files.type = 'tif')
            || (files.type = 'tiff')
            || (files.type = 'svg')
            || (files.type = 'webp')
        )
        AND (files.design = '0')
        AND (files.attachment = '0')
        AND (folder.folder_archived = '0')
        $where
    ORDER BY $order_by");

// If a folder was selected, then store that folder and all child folders
// in an array so that we can later determine if images are in the selected folder scope.
if (($_SESSION['software']['editor_select_image']['folder_id'] ?? '') != 'all') {
    $folders = array();

    // Start the folders off with the selected folder.
    $folders[] = ($_SESSION['software']['editor_select_image']['folder_id'] ?? '');

    // Get all folders in order to add child folders to array.
    $all_folders = db_items(
        "SELECT
            folder_id AS id,
            folder_parent AS parent_folder_id
        FROM folder");

    // Get child folders under the selected folder.
    $child_folders = get_child_folders(($_SESSION['software']['editor_select_image']['folder_id'] ?? ''), $all_folders);

    // Add child folders to array.
    $folders = array_merge($folders, $child_folders);
}

// Create an array that we will use to store images that user has access to.
$images = array();

// Loop through all images in order to determine which to include in results.
foreach ($all_images as $image) {
    // If this user has edit access to this image's folder,
    // and this image is within the scope of the selected folder,
    // then continue to determine if this image should be included in results.
    if (
        (
            (USER_ROLE < 3)
            || (check_folder_access_in_array($image['folder_id'], $folders_that_user_has_access_to) == true)
        )
        &&
        (
            (($_SESSION['software']['editor_select_image']['folder_id'] ?? '') == 'all')
            || (in_array($image['folder_id'], $folders) == true)
        )
    ) {
        // If an access control type has been selected, then get access control type for image,
        // in order to determine if image should be included in results.
        if (($_SESSION['software']['editor_select_image']['access_control_type'] ?? '') != 'all') {
            $image['access_control_type'] = get_access_control_type($image['folder_id']);

            // If the access control type for this image is the same as the selected access
            // control type, then include image in results.
            if ($image['access_control_type'] == ($_SESSION['software']['editor_select_image']['access_control_type'] ?? '')) {
                $images[] = $image;
            }

        // Otherwise an access control type has not been selected,
        // so include image in results.
        } else {
            $images[] = $image;
        }
    }
}

// If there is at least one result to display.
if ($images) {
    // define the maximum number of results to display on one screen
    $max = 100;

    $number_of_results = count($images);

    // get number of screens
    $number_of_screens = ceil($number_of_results / $max);

    
    // only ever appended to below, so it has to start out empty
    $output_screen_links = '';

    // if there are more than one screen
    if ($number_of_screens > 1) {

        $output_screen_links .= '
            <nav class="mt-3 navigation " aria-label="data pagination"> 
                <ul class="pagination pagination-sm flex-wrap justify-content-center">';
        // build Previous button if necessary
        $previous = $screen - 1;
        // if previous screen is greater than zero, output previous link
        if ($previous > 0) {
            $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="editor_select_image.php?screen=' . $previous . '&' . $url_parameters . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
        }else{
            $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
        }

        // build HTML output for links to screens
        for ($i = 1; $i <= $number_of_screens; $i++) {
            // if this number is the current screen, then select option
            if ($i == $screen) {
                $active = 'active"';
            // else this number is not the current screen, so do not select option
            } else {
                $active = '';
            }
            $output_screen_links .= '<li class="page-item mt-1 mb-1 ' . $active . '"><a class="page-link " href="editor_select_image.php?screen=' . $i . '&' . $url_parameters . '">' . $i . '</a></li>';
        }
        // build Next button if necessary
        $next = $screen + 1;
        // if next screen is less than or equal to the total number of screens, output next link
        if ($next <= $number_of_screens) {
            $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="editor_select_image.php?screen=' . $next . '&' . $url_parameters . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
        }else{
            $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
        }
        $output_screen_links .= '</ul></nav>';
    }

    // determine where result set should start
    $start = $screen * $max - $max;

    // determine where result set should end
    $end = $start + $max - 1;

    // get the value of the last index of the array
    $last_index = count($images) - 1;

    // if the end if past the last index of the array, set the end to the last index of the array
    if ($end > $last_index) {
        $end = $last_index;
    }

    for ($key = $start; $key <= $end; $key++) {
        // If we did not get the access control type already up above for this image, then get it now.
        if (isset($images[$key]['access_control_type']) == false) {
            $images[$key]['access_control_type'] = get_access_control_type($images[$key]['folder_id']);
        }

        // getimagesize() returns false for a missing file or one that is not an image.
        $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $images[$key]['name']);
        $image_width = isset($image_size[0]) ? $image_size[0] : 0;
        $image_height = isset($image_size[1]) ? $image_size[1] : 0;
        $output_image_dimantion_based_css = 'h-100';
        if($image_width > $image_height){
            $output_image_dimantion_based_css = 'w-100';
        }

        $thumbnail_dimensions = get_thumbnail_dimensions($image_width, $image_height, 100);

        $output_last_modified_username = '';
        
        if ($images[$key]['last_modified_username'] != '') {
            $output_last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($images[$key]['last_modified_username']) ) ) );
        }
        
        if(($_GET['CKEditorFuncNum'] ?? '')){
            $output_onclick = 'window.opener.CKEDITOR.tools.callFunction(\'' . h(escape_javascript(($_GET['CKEditorFuncNum'] ?? ''))) . '\', \'' . OUTPUT_PATH . h(escape_javascript(encode_url_path($images[$key]['name']))) . '\'); window.close();';
        }else{
            $output_properties = '';
            if(($_GET['SingleImage'] ?? '')){
                $output_properties .= 'SingleImage:true,';
            }
            if(($_GET['file_input_name'] ?? '')){
                $output_properties .= 'file_input_name:\''  . ($_GET['file_input_name'] ?? '') . '\',';
            }
            $output_onclick = 'window.opener.software_image_picker({' . $output_properties . 'return:true,file_id:' . $images[$key]['file_id'] . ',image_name: \'' . h(escape_javascript(encode_url_path($images[$key]['name']))) . '\'}); window.close();';
        }

        $output_images .=
            '<div class="col mb-5">
                <div class="card border-0 shadow-none hoverable cursor-pointer image' . h($images[$key]['access_control_type']) . '" onclick="' . $output_onclick . '" title="' . lang('Image Details') . '" data-bs-content="' . lang('Size') . ': ' . h(convert_bytes_to_string($images[$key]['size'])) . '<br/>' . lang('Dimensions') . ': ' . h($image_width) . ' x ' . h($image_height) . '<br/>' . lang('Folder') . ': ' . h($images[$key]['folder_name']) . '<br/>' . lang('Access') . ': ' . h(get_access_control_type_name($images[$key]['access_control_type'])) . '<br/>' . lang('Last Modified') . ': ' . get_relative_time(array('timestamp' => $images[$key]['last_modified_timestamp'], 'format' => 'plain_text')) . ' ' . $output_last_modified_username . '">
                    <div class="overflow-hidden position-relative rounded ratio ratio-2x1 w-100" style="--bs-aspect-ratio: 80%;background: radial-gradient(transparent, #00000024);">
                        <img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . OUTPUT_PATH . h($images[$key]['name']) . '" class="lazy object-fit-contain w-100 h-100 ' . $output_image_dimantion_based_css . '" alt="' . h($images[$key]['name']) . '">
                    </div>
                    <div class="card-footer border-0 bg-transparent text-truncate">
                        ' . h($images[$key]['name']) . '
                    </div>
                </div>
            </div>';
    }

// Otherwise there are no results, so output message.
} else {
    $output_warning = '<p class="alert alert-danger">' . lang('Sorry, we could not find any images.') . '</p>';
}
$show_unsplash = defined('UNSPLASH_ACCESS_KEY') && UNSPLASH_ACCESS_KEY !== '';

echo
output_header_secure(array('title'=>lang('Browse Images'),'icon'=>'file')) . '
<link rel="stylesheet" type="text/css" href="assets/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.css?v=' . @filemtime(dirname(__FILE__) . '/assets/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.css') . '" />
<script src="assets/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.js?v=' . @filemtime(dirname(__FILE__) . '/assets/dropzone/dropzone.' . ENVIRONMENT_SUFFIX . '.js') . '"></script>
<script>

    Dropzone.options.dropzone = {
        acceptedFiles: "image/*",
        autoProcessQueue: true,
        uploadMultiple: true,
        addRemoveLinks: true,
        dictMaxFilesExceeded : "' . lang('You can not upload any more files.') . '",
        dictDefaultMessage : "' . lang('Drop files here to upload') . '",
        dictFallbackMessage : "' . lang('Your browser does not support drag and drop file uploads.') . '",
        dictFallbackText : "' . lang('Please use the fallback form below to upload your files like in the olden days.') . '",
        dictInvalidFileType : "' . lang('You can not upload files of this type.') . '",
        dictCancelUploadConfirmation : "' . lang('Are you sure you want to cancel this upload?') . '",
        dictMaxFilesExceeded : "' . lang('You can not upload any more files.') . '",
        dictCancelUpload : "' . lang('Cancel upload') . '",
        dictRemoveFile : "<span class=\'material-icons btn btn-sm text-danger p-1 rounded position-absolute top-0 end-0\' style=\'z-index:20;\'>delete</span>",
        // Had to set high value for parallelUploads in order for
        // large number of uploads to work (overrides default which is 2).
        parallelUploads: 9999,
        // Had to set high value for maxFilesize in order to override
        // the default of 256 MB (value is in MB).
        maxFilesize: 9999,
        previewsContainer: ".dropzone_previews",
        clickable: ".dropzone_previews",
        ' . $output_maxfiles . '
        init: function() {
            var myDropzone = this;
           
            // First change the button to actually tell Dropzone to process the queue.
            this.element.querySelector("button[type=submit]").addEventListener("click", function(e) {
              // Make sure that the form isnt actually being sent.
              e.preventDefault();
              e.stopPropagation();
              myDropzone.processQueue();
            });
            this.on("sendingmultiple", function() {
                $("#submit").prop("disabled", true);
                $("#submit").addClass("disabled");
                $("#submit").html("<span class=\'spinner-border spinner-border-sm\'></span> ' . lang('Uploading') . '...");
            });
            this.on("successmultiple", function(files, response) {
                window.location = window.location.href;
            });
            
            this.on("errormultiple", function(files, response) {
                console.log("' . lang('Sorry, the files could not be uploaded.  Please refresh and try again.') . '");
            });
        }
    }
</script>
<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
    <div class="container-fluid">
        <span 
            class="navbar-text" 
            data-bs-content="' . lang('Select the image that you want to embed in your content. Hover over an image to see more info.') . '" 
            title="' . lang('Browse Images') . '">
            ' . lang('Browse Images') . '
        </span> 
        <ul class="navbar-nav ms-auto scrollable-navbar-nav" id="files_nav_controls">
            <li class="vh nav-item pe-1 me-1 border-end my-auto"></li>
            <li class="nav-item dropdown no-arrow">
                ' . $output_sort_link . '
            </li>
            <li class="nav-item ms-auto">
                <form id="search" action="editor_select_image.php" method="get" class="search_form flex-nowrap row g-1 mb-0">
                    
                    <input type="hidden" name="CKEditorFuncNum" value="' . h(($_GET['CKEditorFuncNum'] ?? '')) . '" />
                    <input type="hidden" name="file_input_name" value="' . h(($_GET['file_input_name'] ?? '')) . '" />
                    <input type="hidden" name="SingleImage" value="' . h(($_GET['SingleImage'] ?? '')) . '" />
                    <div class="position-relative" style="width:100px">
                        <select  title="' . lang('Folder') . '" id="folder_id" name="folder_id" class="form-select border-0 py-0" title="' . lang('Folder') . '" onchange="submit_form(\'search\')"><option value="all">[' . lang('All') . ']</option>' . select_folder(($_SESSION['software']['editor_select_image']['folder_id'] ?? '')) . '</select>
                    </div>
                    <div class="position-relative" style="width:100px">
                        <select title="' . lang('Access') . '" id="access_control_type" name="access_control_type" class="form-select border-0 py-0 ' . h(($_SESSION['software']['editor_select_image']['access_control_type'] ?? '')) . '" title="' . lang('Access') . '" onchange="submit_form(\'search\')"><option value="all" class="all">[' . lang('All') . ']</option>' . select_access_control_type(($_SESSION['software']['editor_select_image']['access_control_type'] ?? ''), false) . '</select>
                    </div>

                    <div class="position-relative" style="width:100px">
                        <button type="submit" value="Search" title="' . lang(array('string'=>'Search') ) . '" class="btn btn-link link-success bi bi-search position-absolute top-0 start-0 py-0 px-0 ms-1"></button> 
                        <input type="text" class="form-control border-0 py-0 px-4" name="query" placeholder="' . lang(array('string'=>'Search') ) . '" value="' . h(($_SESSION['software']['editor_select_image']['query'] ?? '')) . '" aria-label="search">
                        ' . $output_clear_button . '
                    </div>
                </form>
            </li>
            
        </ul>

        <ul class="navbar-nav">
            <li class="vh nav-item pe-1 me-1 border-end my-auto"></li>
            <li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
                <button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                <ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
                </ul>
            </li>
            <li class="nav-item">
                <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="Close">
                    <span class="bi bi-x-lg"></span>
                </button>
            </li>
        </ul>
    </div>
</nav>


<main class="container-xxl">

    ' . ($show_unsplash ? '
    <!-- Tab nav -->
    <ul class="nav nav-tabs mt-3 mb-0" id="esi_tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="esi_tab_files_btn" type="button" role="tab"
                onclick="esiSwitchTab(\'files\', this)">
                <i class="bi bi-folder2-open me-1"></i>' . lang('My Files') . '
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="esi_tab_unsplash_btn" type="button" role="tab"
                onclick="esiSwitchTab(\'unsplash\', this)">
                <i class="bi bi-image me-1"></i>Unsplash
            </button>
        </li>
    </ul>
    ' : '') . '

    <!-- My Files tab panel -->
    <div id="esi_tab_files">
    ' . $output_warning . '
    <form enctype="multipart/form-data" action="add_file.php" method="post" id="dropzone" class="dropzone" style="border: none;background: unset;padding: unset;">
    ' . get_token_field() . '
    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
    <input type="hidden" name="uploadreturn" value="true" />
    <input type="hidden" name="CKEditorFuncNum" value="' . h(($_GET['CKEditorFuncNum'] ?? '')) . '" />
    <input type="hidden" name="file_input_name" value="' . h(($_GET['file_input_name'] ?? '')) . '" />
    <input type="hidden" name="SingleImage" value="' . h(($_GET['SingleImage'] ?? '')) . '" />

    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Upload File') . '
                </div>
                <div class="card-body">
                    <div class="dropzone_previews ">
                        <div class="dz-message needsclick" data-dz-message>
                            <div class="dz-message ">' . lang('Drop files here or click to browse') . '</div>
                        </div>
                        <div class="fallback">
                            <div class="alert alert-primary">' . lang('It appears that you are using an older browser that does not support dragging & dropping files to upload them. Please use the field below to select one or more files.') . '</div>
                            <input type="hidden" name="fallback" value="true" />
                            <div class="mb-3">
                                <label for="fallback_input" class="form-label">' . lang('Select File(s)') . '</label>
                                <input class="form-control form-control-sm" id="fallback_input" name="file[]" type="file" multiple="multiple">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-2 mt-2">
                        <div class="col-12 col-md">
                            <label for="folder" class="form-label">' . lang('Folder') . '</label>
                            <select class="form-select " id="folder" name="folder">' . select_folder($folder_id, 0) . '</select>
                        </div>
                        <div class="col-12 col-md">
                        <label for="description" class="form-label">' . lang('File Description') . '</label>
                        <textarea class="form-control" id="description" name="description" style="height:0px;"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>         
    <nav class="buttons d-none navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
        <div class="container">
            <div class=" btn-group flex-wrap justify-content-center">
                <button type="submit" id="submit" name="submit" value="Upload" class="btn my-1  btn-success " ><span class="material-icons me-2">file_upload</span><span class="btn-text">' . lang(array('string'=>'Upload') ) . '</span></button>
            </div>
        </div>
    </nav>
</form>
    <div class="images row row-cols-2 row-cols-sm-4 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4">
        ' . $output_images . '
    </div>
    <div class="text-center">
        ' . $output_screen_links . '
    </div>

    </div><!-- /#esi_tab_files -->

    ' . ($show_unsplash ? '
    <!-- Unsplash tab panel -->
    <div id="esi_tab_unsplash" style="display:none">
        <div class="my-3">
            <div class="input-group">
                <input type="text" id="unsplash_query" class="form-control"
                    placeholder="' . lang('Search') . ' Unsplash..."
                    onkeydown="if(event.key===\'Enter\'){esiUnsplashSearch();}" />
                <button class="btn btn-primary" type="button" onclick="esiUnsplashSearch()">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
        <div id="unsplash_status" class="text-muted small mb-2"></div>
        <div id="unsplash_results" class="row row-cols-2 row-cols-sm-4 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4"></div>
        <div id="unsplash_load_more" class="text-center mt-3" style="display:none">
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="esiUnsplashSearch(true)">
                ' . lang('Load More') . '
            </button>
        </div>
    </div>
    ' : '') . '

</main>
<script>
' . ($show_unsplash ? '
// ── Unsplash integration ──────────────────────────────────────────────────────
var ESI_UNSPLASH_KEY  = ' . json_encode(UNSPLASH_ACCESS_KEY) . ';
var ESI_CK_FUNC_NUM   = ' . json_encode(h($_GET['CKEditorFuncNum'] ?? '')) . ';
var ESI_SINGLE_IMAGE  = ' . (($_GET['SingleImage'] ?? '') ? 'true' : 'false') . ';
var ESI_FILE_INPUT    = ' . json_encode(h($_GET['file_input_name'] ?? '')) . ';
var _unsplashPage     = 1;
var _unsplashQuery    = \'\';
var _unsplashPopularLoaded = false;

function esiSwitchTab(tab, btn) {
    document.querySelectorAll(\'#esi_tabs .nav-link\').forEach(function(b){ b.classList.remove(\'active\'); });
    btn.classList.add(\'active\');
    document.getElementById(\'esi_tab_files\').style.display    = (tab === \'files\')    ? \'\' : \'none\';
    document.getElementById(\'esi_tab_unsplash\').style.display = (tab === \'unsplash\') ? \'\' : \'none\';
    var nav = document.getElementById(\'files_nav_controls\');
    if (nav) nav.style.display = (tab === \'files\') ? \'\' : \'none\';
    // Load popular photos on first visit to the tab
    if (tab === \'unsplash\' && !_unsplashPopularLoaded) {
        esiUnsplashPopular();
    }
}

function esiUnsplashPopular(loadMore) {
    _unsplashPopularLoaded = true;
    _unsplashQuery = \'\';
    if (!loadMore) {
        _unsplashPage = 1;
        document.getElementById(\'unsplash_results\').innerHTML = \'\';
    } else {
        _unsplashPage++;
    }
    var status = document.getElementById(\'unsplash_status\');
    status.textContent = \'' . lang('Loading') . '...\';
    // /photos endpoint returns popular editorial photos (no search query needed)
    fetch(\'https://api.unsplash.com/photos?order_by=popular&per_page=20&page=\'
            + _unsplashPage + \'&client_id=\' + ESI_UNSPLASH_KEY)
        .then(function(r){
            // X-Total header holds total count for /photos endpoint
            var total = r.headers.get(\'X-Total\') || \'\';
            return r.json().then(function(data){ return { data: data, total: total }; });
        })
        .then(function(res){
            if (!res.data || !res.data.length) {
                status.textContent = \'' . lang('Sorry, we could not find any images.') . '\';
                document.getElementById(\'unsplash_load_more\').style.display = \'none\';
                return;
            }
            status.textContent = res.total ? res.total + \' ' . lang('result(s)') . '\' : \'\';
            esiRenderUnsplash(res.data);
            // Show "Load More" as long as a full page was returned
            document.getElementById(\'unsplash_load_more\').style.display =
                (res.data.length === 20) ? \'\' : \'none\';
            // Wire "Load More" to popular loader instead of search
            document.getElementById(\'unsplash_load_more\').querySelector(\'button\')
                .onclick = function(){ esiUnsplashPopular(true); };
        })
        .catch(function(){ status.textContent = \'' . lang('Sorry, we could not find any images.') . '\'; });
}

function esiUnsplashSearch(loadMore) {
    var query = document.getElementById(\'unsplash_query\').value.trim();
    if (!query) { esiUnsplashPopular(); return; }
    if (!loadMore || query !== _unsplashQuery) {
        _unsplashPage = 1;
        _unsplashQuery = query;
        document.getElementById(\'unsplash_results\').innerHTML = \'\';
    } else {
        _unsplashPage++;
    }
    var status = document.getElementById(\'unsplash_status\');
    status.textContent = \'' . lang('Loading') . '...\';
    fetch(\'https://api.unsplash.com/search/photos?query=\' + encodeURIComponent(_unsplashQuery)
            + \'&per_page=20&page=\' + _unsplashPage + \'&client_id=\' + ESI_UNSPLASH_KEY)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.results || !data.results.length) {
                status.textContent = \'' . lang('Sorry, we could not find any images.') . '\';
                document.getElementById(\'unsplash_load_more\').style.display = \'none\';
                return;
            }
            status.textContent = data.total + \' ' . lang('result(s)') . '\';
            esiRenderUnsplash(data.results);
            document.getElementById(\'unsplash_load_more\').style.display =
                (_unsplashPage < data.total_pages) ? \'\' : \'none\';
            // Wire "Load More" back to search loader
            document.getElementById(\'unsplash_load_more\').querySelector(\'button\')
                .onclick = function(){ esiUnsplashSearch(true); };
        })
        .catch(function(){ status.textContent = \'' . lang('Sorry, we could not find any images.') . '\'; });
}

function esiRenderUnsplash(photos) {
    var grid   = document.getElementById(\'unsplash_results\');
    var utm    = \'?utm_source=pinegrap&utm_medium=referral\';
    photos.forEach(function(photo) {
        var photographerUrl = (photo.user.links && photo.user.links.html)
            ? photo.user.links.html + utm : \'#\';
        var photoPageUrl    = (photo.links && photo.links.html)
            ? photo.links.html + utm : \'https://unsplash.com\' + utm;
        var col = document.createElement(\'div\');
        col.className = \'col\';
        col.innerHTML =
            \'<div class="card border-0 shadow-none hoverable">\' +
                \'<div class="overflow-hidden position-relative rounded ratio cursor-pointer" style="--bs-aspect-ratio:80%"\' +
                    \' onclick="esiSelectUnsplash(\\\'\' + photo.urls.regular + \'\\\', \\\'\' + photo.links.download_location + \'\\\')">\' +
                    \'<img src="\' + photo.urls.small + \'" class="object-fit-cover w-100 h-100" alt="\' + (photo.alt_description || \'\') + \'" loading="lazy">\' +
                \'</div>\' +
                /* Attribution — required by Unsplash API guidelines */
                \'<div class="card-footer border-0 bg-transparent" style="font-size:10px;line-height:1.3">\' +
                    \'<a href="\' + photographerUrl + \'" target="_blank" rel="noopener" onclick="event.stopPropagation()" class="text-reset text-decoration-none fw-semibold">\' +
                        photo.user.name +
                    \'</a>\' +
                    \' <span class="text-muted">on</span> \' +
                    \'<a href="\' + photoPageUrl + \'" target="_blank" rel="noopener" onclick="event.stopPropagation()" class="text-muted text-decoration-none">Unsplash</a>\' +
                \'</div>\' +
            \'</div>\';
        grid.appendChild(col);
    });
}

function esiSelectUnsplash(url, downloadLocation) {
    if (ESI_CK_FUNC_NUM && window.opener && window.opener.CKEDITOR) {
        window.opener.CKEDITOR.tools.callFunction(ESI_CK_FUNC_NUM, url);
    } else if (window.opener && window.opener.software_image_picker) {
        window.opener.software_image_picker({
            return:          true,
            SingleImage:     ESI_SINGLE_IMAGE,
            file_input_name: ESI_FILE_INPUT,
            image_name:      url,
            file_id:         0
        });
    }
    // Trigger Unsplash download endpoint as required by API guidelines
    fetch(downloadLocation + \'&client_id=\' + ESI_UNSPLASH_KEY).catch(function(){});
    window.close();
}
' : '') . '
</script>
' . output_footer_secure();