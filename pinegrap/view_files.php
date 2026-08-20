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

// only ever appended to further below, so it has to start out empty
$output_rows = '';

$user = validate_user();
validate_area_access($user, 'user');

$liveform = new liveform('view_files');

// If there is a filter set.
if (isset($_GET['filter']) == true) {
    // Send the filter to the search form.
    $filter = $_GET['filter'];
} else {
    $filter = 'default';
}

$filter_for_links = '&filter=' . $filter;
$output_filter_for_links = h($filter_for_links);

// build filters array
$filters_in_array = 
    array(
        'all_my_files'=> lang('All My Files'),
        'all_my_archived_files'=>lang('All My Archived Files'),
        'my_documents'=>lang('My Documents'),
        'my_photos'=>lang('My Photos'),
        'my_media'=>lang('My Media'),
        'my_attachments'=>lang('My Attachments'),
        'my_public_files'=>lang('My Public Access Files'),
        'my_guest_files'=>lang('My Guest Access Files'),
        'my_registration_files'=>lang('My Registration Access Files'),
        'my_member_files'=>lang('My Member Access Files'),
        'my_private_files'=>lang('My Private Access Files')
    );

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['files']['sort'] = $_REQUEST['sort'];

    // clear order
    $_SESSION['software']['files']['order'] = '';
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    // store sort in session
    $_SESSION['software']['files']['order'] = $_REQUEST['order'];
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['files']['sort']) == false) {
    $_SESSION['software']['files']['sort'] = '';
}

switch (($_SESSION['software']['files']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'name';
        break;
    case lang('Folder'):
        $sort_column = 'folder_name';
        break;
    case lang('Type'):
        $sort_column = 'type';
        break;
    case lang('Size'):
        $sort_column = 'size';
        break;

    case lang('Optimized'):
        $sort_column = 'optimized';
        break;

    case lang('Description'):
        $sort_column = 'description';
        break;
    case lang('Last Modified'):
        $sort_column = 'timestamp';
        break;
    default:
        $sort_column = 'timestamp';
        $_SESSION['software']['files']['sort'] = lang('Last Modified');
        break;
}

if (!empty($_SESSION['software']['files']['order'])) {
    $asc_desc = ($_SESSION['software']['files']['order'] ?? '');
} elseif ($sort_column == 'timestamp') {
    $asc_desc = 'desc';
    $_SESSION['software']['files']['order'] = 'desc';
} else {
    $asc_desc = 'asc';
    $_SESSION['software']['files']['order'] = 'asc';
}

$folders_that_user_has_access_to = array();

// if user is a basic user, then get folders that user has access to
if ($user['role'] == 3) {
    $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
}

// Default images on page to be off.
$images_on_page = false;

// if the filter is not all my archived files, then add the sql where statement to prevent getting archived files
if ($filter != 'all_my_archived_files') {
    $where = "(folder.folder_archived = '0')";

// else this is the all my archived files filter, so only get archived files
} else {
    $where = "(folder.folder_archived = '1')";
}
$folder_access_control_filter = '';
$images_on_page = false;
// Switch between the filters.
switch($filter) {
    case 'all_my_archived_files':
        // Change the heading and subheading.
        $heading = lang('All My Archived Files');
        $subheading = lang('All archived files I can edit & replace.');
        break;
    
    case 'my_documents':
        // Set the query filter.
        $where .= " AND ((files.type != 'jpg') AND (files.type != 'webp') AND (files.type != 'bmp') AND (files.type != 'png') AND (files.type != 'gif') AND (files.type != 'tiff') AND (files.type != 'tif') AND (files.type != 'aiff') AND (files.type != 'au') AND (files.type != 'avi') AND (files.type != 'flv') AND (files.type != 'mpg') AND (files.type != 'mpeg') AND (files.type != 'mov') AND (files.type != 'mid') AND (files.type != 'mp3') AND (files.type != 'rm') AND (files.type != 'ram') AND (files.type != 'snd') AND (files.type != 'swf') AND (files.type != 'wav') AND (files.type != 'wma') AND (files.type != 'wmv'))";

        // Change the heading and subheading.
        $heading = lang('My Documents');
        $subheading = lang('These files can be securely stored and downloaded from any page.');
        break;

    case 'my_photos':
        // Set the query filter.
        $where .= " AND ((files.type = 'bmp') OR (files.type = 'gif') OR (files.type = 'jpg') OR (files.type = 'webp') OR (files.type = 'jpeg') OR (files.type = 'png') OR (files.type = 'tif') OR (files.type = 'tiff'))";

        // Change the heading and subheading.
        $heading = lang('My Photos');
        $subheading = lang('These image files can be embedded into any page or viewed in a slideshow using a photo gallery page.');
        
        // Turn images on
        $images_on_page = true;
        break;

    case 'my_media':
        // Set the query filter.
        $where .= " AND ((files.type = 'aiff') OR (files.type = 'au') OR (files.type = 'avi') OR (files.type = 'flv') OR (files.type = 'webm') OR (files.type = 'mpg') OR (files.type = 'mpeg') OR (files.type = 'mov') OR (files.type = 'mid') OR (files.type = 'mp3') OR (files.type = 'rm') OR (files.type = 'ram') OR (files.type = 'snd') OR (files.type = 'swf') OR (files.type = 'wav') OR (files.type = 'wma') OR (files.type = 'wmv'))";

        // Change the heading and subheading.
        $heading = lang('My Media');
        $subheading = lang('These audio & video files can be embedded into any page or downloaded from any page.');
        break;
        
    case 'my_attachments':
        // Set the query filter.
        $where .= " AND (files.attachment = '1')";

        // Change the heading and subheading.
        $heading = lang('My Attachments');
        $subheading = lang('These are Files that were uploaded with a Submitted Form or a Comment.');
        break;

    case 'my_public_files':

        // Set the folder access control filter
        $folder_access_control_filter = 'public';

        // Change the heading and subheading.
        $heading = lang('My Public Access Files');
        $subheading = lang('These files can be downloaded by any website visitor.');
        
        // Turn images on
        $images_on_page = true;
        break;

    case 'my_guest_files':

        // Set the folder access control filter
        $folder_access_control_filter = 'guest';

        // Change the heading and subheading.
        $heading = lang('My Guest Access Files');
        $subheading = lang('These files can be downloaded by any website visitor after they are offered the option to login or register.');
        // Turn images on
        $images_on_page = true;
        break;

    case 'my_registration_files':

        // Set the folder access control filter
        $folder_access_control_filter = 'registration';

        // Change the heading and subheading.
        $heading = lang('My Registration Access Files');
        $subheading = lang('These files can be downloaded by any website visitor, but only after they login or register.');
        // Turn images on
        $images_on_page = true;
        break;

    case 'my_member_files':

        // Set the folder access control filter
        $folder_access_control_filter = 'membership';

        // Change the heading and subheading.
        $heading = lang('My Member Access Files');
        $subheading = lang('These files can be downloaded by any website user, but only after they have either registered with a valid member id, purchased a membership product, completed a membership trial custom form, or logged in as an unexpired member.');
        // Turn images on
        $images_on_page = true;
        break;

    case 'my_private_files':

        // Set the folder access control filter
        $folder_access_control_filter = 'private';

        // Change the heading and subheading.
        $heading = lang('My Private Access Files');
        $subheading = lang('These files can only be downloaded by website users who have been granted view access to the parent folder, or who have purchased a product that grants private access to the parent folder.');
        // Turn images on
        $images_on_page = true;
        break;

    default:

        // Change the heading and subheading.
        $heading = lang('All My Files');
        $subheading = lang('All files I can edit & replace.');
        break;

}

$all_files = 0;
$my_files = 0;

// Get file's id and folder number from files.
$query =
    "SELECT
       files.id,
       files.folder
    FROM files
    LEFT JOIN folder ON files.folder = folder.folder_id
    WHERE " . $where;
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// Loop through the results
while ($row = mysqli_fetch_assoc($result)) {
    // If the folder access control filter is set.
    if ($folder_access_control_filter) {
        // Compare the filter to the folder's access control type and set the row if they match.
        if (get_access_control_type($row['folder']) == $folder_access_control_filter) {

            // Add one to all files.
            $all_files++;

            // if user has access to file then add one to my files.
            if (check_folder_access_in_array($row['folder'], $folders_that_user_has_access_to) == true) {
                $my_files++;
            }
       }

    // else the access control filter is not set
    } else {
        // Add one to all files.
        $all_files++;

        // if user has access to file then add one to my files.
        if (check_folder_access_in_array($row['folder'], $folders_that_user_has_access_to) == true) {
            $my_files++;
        }
    }
}

// get all files
// image_width / image_height / optimization_percent are cached in the row
// (filled lazily by upgrade 2026.1.25 + write-back below) so we can render
// thumbnails and the optimize % badge without per-row filesystem stats or
// full image-decode passes.
$query =
    "SELECT
        files.id,
        files.name,
        files.folder,
        folder.folder_name,
        folder.folder_access_control_type,
        files.description,
        files.type,
        files.size,
        files.optimized,
        files.image_width,
        files.image_height,
        files.optimization_percent,
        user.user_username,
        files.timestamp,
        files.design
    FROM files
    LEFT JOIN folder ON files.folder = folder.folder_id
    LEFT JOIN user ON files.user = user.user_id
    WHERE " . $where . "
    ORDER BY $sort_column $asc_desc";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_assoc($result)) {

    // if user has access to file then continue
    if (check_folder_access_in_array($row['folder'], $folders_that_user_has_access_to) == true) {

        // If the folder access control filter is set.
        if ($folder_access_control_filter) {

            // Compare the filter to the folder's access control type and set the row if they match.
            if (get_access_control_type($row['folder']) == $folder_access_control_filter) {
                $files[] = $row;
            }
        } else {
            $files[] = $row;
        }
    }
}
$output_rows = false;

// Optimize yüzdesi hesaplama eşiği.
// Optimize edilmemiş görsel sayısı bu değeri geçerse hesaplama yapılmaz,
// buton görünür ancak yüzde gösterilmez (çökme önlemi).
define('OPTIMIZE_PERCENT_THRESHOLD', 30);

$unoptimized_image_types = ['jpg','jpeg','png','gif','bmp','tiff','tif','webp'];
$unoptimized_count = 0;
if ($files) {
    foreach ($files as $f) {
        if (!$f['optimized'] && in_array(mb_strtolower($f['type']), $unoptimized_image_types)) {
            $unoptimized_count++;
        }
    }
}
$show_optimize_percent = ($unoptimized_count <= OPTIMIZE_PERCENT_THRESHOLD);

// if there is at least one result to display
if ($files) {
    foreach ($files as $file) {
        $optimized = '';

        if ($file['optimized']) {
            $optimized = '<span class="material-icons">task_alt</span>';
        }




        $output_check_box = '<input class="form-check-input disabled" disabled type="checkbox" name="fake_checkbox[]" value="1" class="checkbox" />';
        $output_unselectable_class = 'class="unselectable"';
        $output_edit_buttons = '';

        // if this is not a design file or if the user has access to design files, then output link
        if (
            ($file['design'] == 0)
            || ($user['role'] <= 1)
        ) {
            $output_check_box = '<input class="form-check-input " type="checkbox" name="files[]" value="' . $file['id'] . '" class="checkbox" />';
            $output_unselectable_class = '';

            // If this file has not been optimized yet, and it is an image type that we support, then
            // show optimize button and edit image link in actions
           
            $file_type ='';
            $output_edit_image_button = '';
            $output_optimize_button = '';
            $file_type = mb_strtolower($file['type']);
            if (    ($file_type == 'jpg')
                    or ($file_type == 'jpeg')
                    or ($file_type == 'png')
                    or ($file_type == 'gif')
                    or ($file_type == 'bmp')
                    or ($file_type == 'tiff')
                    or ($file_type == 'webp')
                ){
                    if (!$optimized &&(extension_loaded('imagick') || extension_loaded('gd'))) {

                        // Use the cached optimization_percent column. It is filled lazily
                        // (only when the row has no value yet AND we are under the
                        // OPTIMIZE_PERCENT_THRESHOLD), so most renders skip the expensive
                        // full image decode/recompress in calculate_optimizable_percent().
                        $optimize_percent_html = '';
                        if ($show_optimize_percent) {
                            if ($file['optimization_percent'] === null || $file['optimization_percent'] === '') {
                                $percent_string = calculate_optimizable_percent(FILE_DIRECTORY_PATH . '/' . $file['name']);
                                $percent_int = (int) rtrim($percent_string, '%');
                                db("UPDATE files SET optimization_percent = '" . (int) $percent_int . "' WHERE id = '" . e($file['id']) . "'");
                                $optimize_percent_html = '<span class="ps-1 pe-2 fs-smaller">' . h($percent_string) . '</span>';
                            } else {
                                $optimize_percent_html = '<span class="ps-1 pe-2 fs-smaller">' . (int) $file['optimization_percent'] . '%</span>';
                            }
                        }
                        $output_optimize_button = '<button type="button" class="m-1 btn-data-control btn btn-outline-success border-2 " data-loading-content=" " title="' . lang('Optimize this image') . '" onclick="window.location.href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/optimize.php?id=' . h($file['id']) . get_token_query_string_field() . '\'"><i class="bi bi-fast-forward-circle"></i>' . $optimize_percent_html . '</button>';
                    }
                    $output_edit_image_button = '<button type="button" class="m-1 btn-data-control btn btn-outline-secondary border-2 " data-loading-content=" " title="' . lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor')) )) . '" onclick="window.location.href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/image_editor_edit.php?file_name=' . h($file['name']) . '&send_to=' . h(escape_javascript(REQUEST_URL)) . '\'"><i class="bi bi-brush"></i></button>';
            }

            $output_edit_buttons = '
            <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit File') . '" onclick="window.location.href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_file.php?id=' . $file['id'] . '&send_to=' . h(escape_javascript(REQUEST_URL)) . '\'"><i class="bi bi-pencil"></i></button>' . $output_optimize_button . $output_edit_image_button;

        }

        $output_design_class = '';

        // if this is a design file, then output design class
        if ($file['design'] == 1) {
            $output_design_class = ' design';
        }

        // Convert file size to a user friendly output.
        $file['size'] = convert_bytes_to_string($file['size']);

       

        // If the file is an image.
        if ((mb_strtolower($file['type']) == 'bmp') || (mb_strtolower($file['type']) == 'gif') || (mb_strtolower($file['type']) == 'jpg')|| (mb_strtolower($file['type']) == 'webp') || (mb_strtolower($file['type']) == 'jpeg') || (mb_strtolower($file['type']) == 'png') || (mb_strtolower($file['type']) == 'tif') || (mb_strtolower($file['type']) == 'tiff')) {

            // Use cached image dimensions. Falls back to getimagesize() once per
            // image when the row doesn't have a cached value yet, then writes the
            // result back so subsequent renders skip the disk stat / header decode.
            // This is especially important on OneDrive / network-mounted dev folders.
            if (
                ($file['image_width'] !== null && $file['image_width'] !== '')
                && ($file['image_height'] !== null && $file['image_height'] !== '')
            ) {
                $image_width = (int) $file['image_width'];
                $image_height = (int) $file['image_height'];
            } else {
                $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $file['name']);
                $image_width = isset($image_size[0]) ? (int) $image_size[0] : 0;
                $image_height = isset($image_size[1]) ? (int) $image_size[1] : 0;
                if ($image_width > 0 && $image_height > 0) {
                    db(
                        "UPDATE files SET image_width = '" . (int) $image_width
                        . "', image_height = '" . (int) $image_height
                        . "' WHERE id = '" . e($file['id']) . "'"
                    );
                }
            }

            // Output the image dimension to the table.
            $output_image_dimensions = lang('width') . ': ' . $image_width . ' px ' . lang('height') . ': ' . $image_height . ' px';

            // Set the maximum dimension size for the image.
            $max_dimension = 75;

            // Call function to resize image.
            $thumbnail_dimensions = get_thumbnail_dimensions($image_width, $image_height, $max_dimension);

            //default show image
		    $show_image = true;
		    if($show_image == true){
                $output_thumbnail ='<img style="width: 50px;height:50px;" title="' . $output_image_dimensions . '" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' .  PATH . $file['name'] . '" />';
		    }
        } else {
            $output_thumbnail ='';
            $output_image_dimensions = '';
        }
        $output_thumbnail_cell = '';
        $output_thumbnail_heading_cell = '';
        // If there are going to be images on the page then ouput the table rows and headings for them
        if ($images_on_page == true) {
            $output_thumbnail_cell = '<td class="align-middle text-start ' . $output_design_class . '" >' . $output_thumbnail . '</td>';
            $output_thumbnail_heading_cell = '<th>' . lang('Image') . '</th>';
        }
 
        $output_rows .=
        '<tr ' . $output_unselectable_class . '>
            <td class="select-all align-middle text-start">' . $output_check_box . '</td>
			<td class="align-middle text-start action-buttons">
                ' . $output_edit_buttons . '
                <a class="m-1 btn-data-control btn btn-outline-warning border-2 " title="' . lang('Download') . '" data-loading-content=" " download="' . h($file['type']) . '" href="' . OUTPUT_PATH. h($file['name']) . '" ><i class="bi bi-file-earmark-arrow-down"></i></a>
            </td>
            ' . $output_thumbnail_cell . '
            <td class="align-middle chart_label' . $output_design_class . '" title="' . h($file['name']) . '">' . h($file['name']) . '</td>
            <td class="align-middle ' . $output_design_class . '">' . h($file['type']) . '</td>
            <td class="align-middle ' . $output_design_class . '">' . h($file['size']) . '</td>
            <td class="align-middle text-center ' . $output_design_class . '" style="text-align: center">' . $optimized . '</td>
            <td class="align-middle text-start ' . $file['folder_access_control_type'] . '" title="' . h($file['folder_name']) . '"><span class="d-block overflow-hidden text-truncate" style="width: 100px;max-width:100%;"><span class="material-icons d-inline">folder</span><span class="badge fw-light text-reset d-inline">' . h($file['folder_name']) . '</span></span></td>
            <td class="align-middle ' . $output_design_class . '" title="' . nl2br(h($file['description'])) . '"><span class="text-truncate overflow-hidden d-block" style="width:200px;max-width:100%;">' . nl2br(h($file['description'])) . '</span></td>
            <td class="align-middle ' . $output_design_class . '">' . get_relative_time(array('timestamp' => $file['timestamp'])) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($file['user_username']) ) ) ) . '</td>
        </tr>';
    }
}

echo
    pg_page_shell(
        array(
            'title'=> lang('Files'),
            'extra classes'=>'file',
            'icon'=>'file', 
            'heading'=>lang('Files'),

        )
    ) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . $subheading . '" title="' . $heading . '">' . $heading . '</h2>
                        <p>' . lang('Disk Usage') . ': ' . h(convert_bytes_to_string(db("SELECT SUM(size) FROM files"), 2)) . '</p>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_file.php?send_to=' . h(urlencode(REQUEST_URL)) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="material-icons me-2">file_upload</span>' . lang(array('string'=>'Upload File') ) . '</a>
                            <a class="btn btn-sm btn-primary m-1 " href="create_file.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        </nav>
                    </div>
                    <div class="col-12 col-sm-12 col-md-6 col-xl-3 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <form id="search_form" action="view_files.php" method="get" class="search_form col-auto">
                                <input type="hidden" name="filter" value="' . h($filter) . '">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="filter_select" name="filter" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . get_filter_options($filters_in_array, $filter) . '</select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <form name="form"  action="edit_files.php" method="post"> 
                            ' . get_token_field() . '
                            <input type="hidden" name="action" />
                            <input type="hidden" name="move_to_folder" />
                            <input type="hidden" name="edit_design" />
                            <input type="hidden" name="optimize">
                            <input type="hidden" name="from" value="view_files" />
                            <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                            <table class="chart table-hover table " style="width:100%;display:none">
                                <thead>
                                    <tr>
                                        <th class="noVis">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                            </div>
                                        </th>
                                        <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                        ' . $output_thumbnail_heading_cell . '
                                        <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['files']['sort'] ?? ''), ($_SESSION['software']['files']['order'] ?? ''), $output_filter_for_links) . '</th>
                                        <th>' . get_column_heading(lang('Type'), ($_SESSION['software']['files']['sort'] ?? ''), ($_SESSION['software']['files']['order'] ?? ''), $output_filter_for_links) . '</th>
                                        <th>' . get_column_heading(lang('Size'), ($_SESSION['software']['files']['sort'] ?? ''), ($_SESSION['software']['files']['order'] ?? ''), $output_filter_for_links) . '</th>
                                        <th>' . get_column_heading(lang('Optimized'), ($_SESSION['software']['files']['sort'] ?? ''), ($_SESSION['software']['files']['order'] ?? ''), $output_filter_for_links) . '</th>
                                        <th>' . get_column_heading(lang('Folder'), ($_SESSION['software']['files']['sort'] ?? ''), ($_SESSION['software']['files']['order'] ?? ''), $output_filter_for_links) . '</th>
                                        <th>' . get_column_heading(lang('Description'), ($_SESSION['software']['files']['sort'] ?? ''), ($_SESSION['software']['files']['order'] ?? ''), $output_filter_for_links) . '</th>
                                        <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['files']['sort'] ?? ''), ($_SESSION['software']['files']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_rows . '</tbody>
                            </table>
                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        <button type="button" value="Modify Selected" class=" btn mb-1 mt-1 btn-primary disabled" onclick="window.open(\'edit_files.php\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\'); edit_chart_content(\'edit\',\'product\')"><span class="material-icons me-2">edit</span>' . lang(array('string'=>'Modify Selected') ) . '</button>
                                        <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('files')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>' .
    output_footer();

$liveform->remove_form('view_files');