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
validate_area_access($user, 'designer');

$liveform = new liveform('view_design_files');

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['design']['view_design_files']['sort'] = $_REQUEST['sort'];

    // clear order
    $_SESSION['software']['design']['view_design_files']['order'] = '';
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    // store sort in session
    $_SESSION['software']['design']['view_design_files']['order'] = $_REQUEST['order'];
}

$output_clear_button = '';


// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['design']['view_design_files']['sort']) == false) {
    $_SESSION['software']['design']['view_design_files']['sort'] = '';
}

switch (($_SESSION['software']['design']['view_design_files']['sort'] ?? '')) {
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
        $_SESSION['software']['design']['view_design_files']['sort'] = lang('Last Modified');
        break;
}

if (!empty($_SESSION['software']['design']['view_design_files']['order'])) {
    $asc_desc = ($_SESSION['software']['design']['view_design_files']['order'] ?? '');
} elseif ($sort_column == 'timestamp') {
    $asc_desc = 'desc';
    $_SESSION['software']['design']['view_design_files']['order'] = 'desc';
} else {
    $asc_desc = 'asc';
    $_SESSION['software']['design']['view_design_files']['order'] = 'asc';
}

// get all stylesheet files
$query =
    "SELECT
        COUNT(files.id)
    FROM files
    WHERE
        (design = '1')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_design_files = $row[0];


// get all files
$query =
    "SELECT
        files.id,
        files.name,
        files.folder,
        folder.folder_name,
        folder.folder_archived,
        files.description,
        files.type,
        files.size,
        files.optimized,
        user.user_username,
        files.timestamp,
        files.design
    FROM files
    LEFT JOIN folder ON files.folder = folder.folder_id
    LEFT JOIN user ON files.user = user.user_id
    WHERE (files.design = '1')
    ORDER BY $sort_column $asc_desc";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_assoc($result)) {
    $files[] = $row;
}
$output_rows = '';

// Optimize yüzdesi hesaplama eşiği (view_files.php ile aynı mantık).
$unoptimized_image_types = ['jpg','jpeg','png','gif','bmp','tiff','tif','webp'];
$unoptimized_count = 0;
if ($files) {
    foreach ($files as $f) {
        if (!$f['optimized'] && in_array(mb_strtolower($f['type']), $unoptimized_image_types)) {
            $unoptimized_count++;
        }
    }
}
$show_optimize_percent = ($unoptimized_count <= 30);

// if there is at least one result to display
if ($files) {

    foreach ($files as $file) {
        $optimized = '';

        if ($file['optimized']) {
            $optimized = '<span class="material-icons">task_alt</span>';
        }
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

                    $optimize_percent_html = $show_optimize_percent
                        ? '<span class="ps-1 pe-2 fs-smaller">' . calculate_optimizable_percent(FILE_DIRECTORY_PATH . '/' . $file['name']) . '</span>'
                        : '';
                    $output_optimize_button = '<button type="button" class="m-1 btn-data-control btn btn-outline-success border-2 " data-loading-content=" " title="' . lang('Optimize this image') . '" onclick="window.location.href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/optimize.php?id=' . h($file['id']) . get_token_query_string_field() . '\'"><i class="bi bi-fast-forward-circle"></i>' . $optimize_percent_html . '</button>';
                }
                $output_edit_image_button = '<button type="button" class="m-1 btn-data-control btn btn-outline-secondary border-2 " data-loading-content=" " title="' . lang(array('string'=>'Edit this image with {var:1}','vars'=>array(lang('Image Editor')) )) . '" onclick="window.location.href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/image_editor_edit.php?file_name=' . h($file['name']) . '&send_to=' . h(escape_javascript(REQUEST_URL)) . '\'"><i class="bi bi-brush"></i></button>';
        }


        $output_edit_buttons = '
        <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit File') . '" onclick="window.location.href=\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_design_file.php?id=' . $file['id'] . '&send_to=' . h(escape_javascript(REQUEST_URL)) . '\'"><i class="bi bi-pencil"></i></button>' . $output_optimize_button . $output_edit_image_button;

        $archived_row_style = '';
        
        // if the file is inside of a archived folder, then set the table row's class to archived
        if ($file['folder_archived'] == '1') {
            $archived_row_style = ' style="font-style: italic;"';
        }

        // Convert file size to a user friendly output.
        $file['size'] = convert_bytes_to_string($file['size']);

        
        // If the file is an image.
        if ((mb_strtolower($file['type']) == 'bmp') || (mb_strtolower($file['type']) == 'gif') || (mb_strtolower($file['type']) == 'jpg') || (mb_strtolower($file['type']) == 'jpeg') || (mb_strtolower($file['type']) == 'png') || (mb_strtolower($file['type']) == 'tif') || (mb_strtolower($file['type']) == 'tiff') || (mb_strtolower($file['type']) == 'webp')) {

            // Get the dimensions of the image.
            $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $file['name']);
            $image_width = $image_size[0];
            $image_height = $image_size[1];

            // Output the image dimensions to the table.
            $output_image_dimensions = lang('width') . ': ' . $image_width . ' px ' . lang('height') . ': ' . $image_height . ' px';

            // Set the maximum dimension size for the image.
            $max_dimension = 75;

            // Call function to resize image.
            $thumbnail_dimensions = get_thumbnail_dimensions($image_width, $image_height, $max_dimension);

            // Output thumnail.
            $output_thumbnail ='<img style="width: 50px;height:50px;" title="' . $output_image_dimensions . '" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' .  PATH . $file['name'] . '" />';

        } else {

            $output_thumbnail = '';
            $output_image_dimensions = '';
        }

    

        $last_modified_username = '';
            
        if ($file['user_username']) {
            $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($file['user_username']) ) ) );
        }

        $output_rows .=
            '<tr' . $archived_row_style . '>
                <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="files[]" value="' . $file['id'] . '" class="checkbox" /></td>
                <td class="align-middle text-start action-buttons">
                    ' . $output_edit_buttons . '
                    <a class="m-1 btn-data-control btn btn-outline-warning border-2 " title="' . lang('Download') . '" data-loading-content=" " download="' . h($file['type']) . '" href="' . OUTPUT_PATH. h($file['name']) . '" ><i class="bi bi-file-earmark-arrow-down"></i></a>
                </td>
                <td class="align-middle text-center">' . $output_thumbnail . '</td>
                <td class="align-middle chart_label" title="' . h($file['name']) . '">' . h($file['name']) . '</td>
                <td class="align-middle">' . h($file['type']) . '</td>
                <td class="align-middle">' . h($file['size']) . '</td>
                <td class="align-middle text-center">' . $optimized . '</td>
                <td class="align-middle text-start " title="' . h($file['folder_name']) . '"><span class="d-block overflow-hidden text-truncate" style="width: 100px;max-width:100%;"><span class="material-icons d-inline">folder</span><span class="badge fw-light text-reset d-inline">' . h($file['folder_name']) . '</span></span></td>
                <td class="align-middle" title="' . nl2br(h($file['description'])) . '"><span class="text-truncate overflow-hidden d-block" style="width:200px;max-width:100%;">' . nl2br(h($file['description'])) . '</span></td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $file['timestamp'])) . ' ' . $last_modified_username . '</td>
            </tr>';
    }
}

print
pg_page_shell(
    array(
        'title'=> lang('Design Files'),
        'extra classes'=>'designer',
        'icon'=>'design', 
        'heading'=> lang('Design Files'),
        'cancel'=> false
    )
)  . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All design files that can be referenced by any theme, page style, or region, and managed only by site designers.') . '" title="' . lang('All Design Files') . '">' . lang('All Design Files') . '</h2>
                    <p>' . lang('Disk Usage') . ': ' . h(convert_bytes_to_string(db("SELECT SUM(size) FROM files"), 2)) . '</p>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_design_file.php?send_to=' . h(urlencode(REQUEST_URL)) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="material-icons me-2">file_upload</span>' . lang(array('string'=>'Upload File') ) . '</a>
                    </nav>
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
                        <input type="hidden" name="from" value="view_design_files" />
                        <input type="hidden" name="send_to" value="' . h(get_request_uri()) . '" />
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                        </div>
                                    </th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' . lang('Thumbnail') . '</th>
                                    <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['design']['view_design_files']['sort'] ?? ''), ($_SESSION['software']['design']['view_design_files']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Type'), ($_SESSION['software']['design']['view_design_files']['sort'] ?? ''), ($_SESSION['software']['design']['view_design_files']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Size'), ($_SESSION['software']['design']['view_design_files']['sort'] ?? ''), ($_SESSION['software']['design']['view_design_files']['order'] ?? '')) . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Optimized'), ($_SESSION['software']['design']['view_design_files']['sort'] ?? ''), ($_SESSION['software']['design']['view_design_files']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Folder'), ($_SESSION['software']['design']['view_design_files']['sort'] ?? ''), ($_SESSION['software']['design']['view_design_files']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Description'), ($_SESSION['software']['design']['view_design_files']['sort'] ?? ''), ($_SESSION['software']['design']['view_design_files']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['design']['view_design_files']['sort'] ?? ''), ($_SESSION['software']['design']['view_design_files']['order'] ?? '')) . '</th>
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

$liveform->remove_form('view_design_files');