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
$user = validate_user();
$output_images = '';
$output_warning = '';


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



// The sort control used to be a link that silently cycled through the three modes, so the user
// had to click and see what happened. It is a real menu now, with the active mode checked.
$sort_options = array(
    'newest'       => array('label' => lang('Newest'),       'icon' => 'bi-sort-down'),
    'oldest'       => array('label' => lang('Oldest'),       'icon' => 'bi-sort-up'),
    'alphabetical' => array('label' => lang('Alphabetical'), 'icon' => 'bi-sort-alpha-down'));

// If the stored sort is missing, or holds a value we do not recognise, fall back to newest.
$current_sort = ($_SESSION['software']['editor_select_image']['sort'] ?? '');

if (isset($sort_options[$current_sort]) == false) {
    $current_sort = 'newest';
    $_SESSION['software']['editor_select_image']['sort'] = 'newest';
}

$output_sort_items = '';

foreach ($sort_options as $sort_key => $sort_option) {

    $output_sort_items .=
        '<li><a class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center' . (($sort_key == $current_sort) ? ' active' : '') . '" href="editor_select_image.php?sort=' . h($sort_key) . '&' . $url_parameters . '"><i class="bi ' . $sort_option['icon'] . ' m-2"></i>' . $sort_option['label'] . '</a></li>';
}

$output_sort_link =
    '<div class="dropdown">
                        <button class="esi-seg-btn no-popover" data-bs-toggle="dropdown" type="button" id="esi_sort" title="' . lang('Sort') . '" aria-label="' . lang('Sort') . '"><span class="bi ' . $sort_options[$current_sort]['icon'] . '"></span></button>
                        <ul aria-labelledby="esi_sort" class="dropdown-menu shadow p-1 bg-body backdrop border-dropdown-menu" style="--bs-dropdown-min-width: 11rem;">' . $output_sort_items . '</ul>
                    </div>';




// if the user clicked on the clear button, then clear the search
if (isset($_GET['clear']) == true) {
    $_SESSION['software']['editor_select_image']['query'] = '';
}

// if the user asked for a clean slate, then drop every filter at once instead of making
// them reset the folder, the access type and the search box one by one
if (isset($_GET['reset']) == true) {
    $_SESSION['software']['editor_select_image']['query'] = '';
    $_SESSION['software']['editor_select_image']['folder_id'] = 'all';
    $_SESSION['software']['editor_select_image']['access_control_type'] = 'all';
}

$output_clear_button = '';

// if there is a search query, then prepare to output clear button
if ((isset($_SESSION['software']['editor_select_image']['query']) == true) && (($_SESSION['software']['editor_select_image']['query'] ?? '') != '')) {
    $output_clear_button = '<button type="button" title="' . lang(array('string'=>'Clear') ) . '" class="esi-clear-btn no-popover bi bi-x-circle-fill" onclick="document.location.href = \'' . h(escape_javascript($_SERVER['PHP_SELF'])) . '?clear=true&' . $url_parameters . '\'"></button>';
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

// Work out which filters are actually narrowing the list, so the screen can both explain
// itself and offer a way back out of a filter combination that returns nothing.
$has_query_filter  = ((($_SESSION['software']['editor_select_image']['query'] ?? '') != ''));
$has_folder_filter = ((($_SESSION['software']['editor_select_image']['folder_id'] ?? 'all') != 'all'));
$has_access_filter = ((($_SESSION['software']['editor_select_image']['access_control_type'] ?? 'all') != 'all'));
$has_any_filter    = (($has_query_filter == true) || ($has_folder_filter == true) || ($has_access_filter == true));

$output_filter_chips = '';

if ($has_any_filter == true) {

    if ($has_folder_filter == true) {

        $selected_folder_name = db_value(
            "SELECT folder_name
            FROM folder
            WHERE folder_id = '" . escape(($_SESSION['software']['editor_select_image']['folder_id'] ?? '')) . "'
            LIMIT 1");

        $output_filter_chips .=
            '<a class="esi-chip" href="editor_select_image.php?folder_id=all&' . $url_parameters . '" title="' . lang('Clear') . '"><span class="bi bi-folder2 me-1"></span>' . h((string) $selected_folder_name) . '<span class="bi bi-x ms-1"></span></a>';
    }

    if ($has_access_filter == true) {

        $output_filter_chips .=
            '<a class="esi-chip" href="editor_select_image.php?access_control_type=all&' . $url_parameters . '" title="' . lang('Clear') . '"><span class="bi bi-shield-lock me-1"></span>' . h((string) get_access_control_type_name(($_SESSION['software']['editor_select_image']['access_control_type'] ?? ''))) . '<span class="bi bi-x ms-1"></span></a>';
    }

    if ($has_query_filter == true) {

        $output_filter_chips .=
            '<a class="esi-chip" href="editor_select_image.php?clear=true&' . $url_parameters . '" title="' . lang('Clear') . '"><span class="bi bi-search me-1"></span>' . h(($_SESSION['software']['editor_select_image']['query'] ?? '')) . '<span class="bi bi-x ms-1"></span></a>';
    }

    $output_filter_chips =
        '<div class="d-flex flex-wrap align-items-center gap-2 mt-3">
            <span class="text-body-secondary small">' . lang('Filters') . ':</span>
            ' . $output_filter_chips . '
            <a class="small link-secondary text-decoration-none ms-1" href="editor_select_image.php?reset=true&' . $url_parameters . '">' . lang('Clear Filters') . '</a>
        </div>';
}

$number_of_results   = count($images);
$number_of_screens   = 0;
$output_screen_links = '';

// If there is at least one result to display.
if ($number_of_results > 0) {
    // define the maximum number of results to display on one screen
    $max = 100;

    // get number of screens
    $number_of_screens = ceil($number_of_results / $max);

    // A screen number kept from an earlier, wider result set used to render an empty grid
    // as soon as a filter shortened the list.  Clamp it to what actually exists.
    if ($screen > $number_of_screens) {
        $screen = $number_of_screens;
    }

    // if there are more than one screen
    if ($number_of_screens > 1) {

        // Only a window of pages around the current one is listed.  The old build printed
        // every single page number, which wrapped onto several rows on a large library.
        $window_start = $screen - 2;
        $window_end   = $screen + 2;

        if ($window_start < 1) {
            $window_end   = $window_end + (1 - $window_start);
            $window_start = 1;
        }

        if ($window_end > $number_of_screens) {
            $window_start = $window_start - ($window_end - $number_of_screens);
            $window_end   = $number_of_screens;
        }

        if ($window_start < 1) {
            $window_start = 1;
        }

        $output_screen_links .= '
            <nav class="navigation" aria-label="data pagination">
                <ul class="pagination pagination-sm flex-wrap justify-content-center mb-0">';

        // build Previous button if necessary
        $previous = $screen - 1;

        // if previous screen is greater than zero, output previous link
        if ($previous > 0) {
            $output_screen_links .= '<li class="page-item"><a class="page-link" href="editor_select_image.php?screen=' . $previous . '&' . $url_parameters . '" aria-label="Previous"><span class="bi bi-chevron-left"></span></a></li>';
        } else {
            $output_screen_links .= '<li class="page-item disabled"><span class="page-link"><span class="bi bi-chevron-left"></span></span></li>';
        }

        // if the window does not start at the beginning, output the first screen and a gap
        if ($window_start > 1) {
            $output_screen_links .= '<li class="page-item"><a class="page-link" href="editor_select_image.php?screen=1&' . $url_parameters . '">1</a></li>';

            if ($window_start > 2) {
                $output_screen_links .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
        }

        // build HTML output for links to screens
        for ($i = $window_start; $i <= $window_end; $i++) {
            // if this number is the current screen, then mark it and do not link it
            if ($i == $screen) {
                $output_screen_links .= '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
            // else this number is not the current screen, so link to it
            } else {
                $output_screen_links .= '<li class="page-item"><a class="page-link" href="editor_select_image.php?screen=' . $i . '&' . $url_parameters . '">' . $i . '</a></li>';
            }
        }

        // if the window does not reach the end, output a gap and the last screen
        if ($window_end < $number_of_screens) {

            if ($window_end < ($number_of_screens - 1)) {
                $output_screen_links .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }

            $output_screen_links .= '<li class="page-item"><a class="page-link" href="editor_select_image.php?screen=' . $number_of_screens . '&' . $url_parameters . '">' . $number_of_screens . '</a></li>';
        }

        // build Next button if necessary
        $next = $screen + 1;

        // if next screen is less than or equal to the total number of screens, output next link
        if ($next <= $number_of_screens) {
            $output_screen_links .= '<li class="page-item"><a class="page-link" href="editor_select_image.php?screen=' . $next . '&' . $url_parameters . '" aria-label="Next"><span class="bi bi-chevron-right"></span></a></li>';
        } else {
            $output_screen_links .= '<li class="page-item disabled"><span class="page-link"><span class="bi bi-chevron-right"></span></span></li>';
        }

        $output_screen_links .= '</ul></nav>';
    }

    // determine where result set should start
    $start = $screen * $max - $max;

    // determine where result set should end
    $end = $start + $max - 1;

    // get the value of the last index of the array
    $last_index = $number_of_results - 1;

    // if the end if past the last index of the array, set the end to the last index of the array
    if ($end > $last_index) {
        $end = $last_index;
    }

    for ($key = $start; $key <= $end; $key++) {
        // If we did not get the access control type already up above for this image, then get it now.
        if (isset($images[$key]['access_control_type']) == false) {
            $images[$key]['access_control_type'] = get_access_control_type($images[$key]['folder_id']);
        }

        $image_path = FILE_DIRECTORY_PATH . '/' . $images[$key]['name'];

        // A row outlives its file whenever the file was removed outside of the software.
        // Those tiles used to render as a broken thumbnail that still inserted a dead path
        // into the page when clicked, so they are shown but not selectable now.
        $image_is_missing = (is_file($image_path) == false);

        // getimagesize() returns false for a missing file or one it cannot read, such as SVG.
        $image_size = ($image_is_missing == true) ? false : @getimagesize($image_path);

        $image_width  = isset($image_size[0]) ? $image_size[0] : 0;
        $image_height = isset($image_size[1]) ? $image_size[1] : 0;

        // SVG and unreadable files report no dimensions at all, so do not print "0 x 0".
        $output_dimensions = ($image_width > 0) ? h($image_width) . ' x ' . h($image_height) : '&mdash;';

        $output_last_modified_username = '';

        if ($images[$key]['last_modified_username'] != '') {
            $output_last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($images[$key]['last_modified_username']) ) ) );
        }

        $output_details =
            lang('Size') . ': ' . h(convert_bytes_to_string($images[$key]['size'])) . '<br/>' .
            lang('Dimensions') . ': ' . $output_dimensions . '<br/>' .
            lang('Folder') . ': ' . h($images[$key]['folder_name']) . '<br/>' .
            lang('Access') . ': ' . h((string) get_access_control_type_name($images[$key]['access_control_type'])) . '<br/>' .
            lang('Last Modified') . ': ' . get_relative_time(array('timestamp' => $images[$key]['last_modified_timestamp'], 'format' => 'plain_text')) . ' ' . $output_last_modified_username;

        $output_meta = h(convert_bytes_to_string($images[$key]['size']));

        if ($image_width > 0) {
            $output_meta .= ' &middot; ' . h($image_width) . ' x ' . h($image_height);
        }

        // The file is gone, so show it greyed out with a reason rather than offering it.
        if ($image_is_missing == true) {

            $output_images .=
                '<div class="col">
                    <div class="esi-tile esi-tile-missing" title="' . lang('File not found') . '" data-bs-content="' . $output_details . '">
                        <span class="esi-thumb d-flex align-items-center justify-content-center">
                            <span class="bi bi-exclamation-triangle text-warning fs-3"></span>
                        </span>
                        <span class="esi-caption text-truncate">' . h($images[$key]['name']) . '</span>
                        <span class="esi-meta text-truncate">' . lang('File not found') . '</span>
                    </div>
                </div>';

            continue;
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
            '<div class="col">
                <button type="button" class="esi-tile image' . h($images[$key]['access_control_type']) . '" onclick="' . $output_onclick . '" title="' . lang('Image Details') . '" data-bs-content="' . $output_details . '">
                    <span class="esi-thumb">
                        <img src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . OUTPUT_PATH . h($images[$key]['name']) . '" class="lazy" alt="' . h($images[$key]['name']) . '">
                        <span class="esi-overlay"><span class="bi bi-check-lg me-1"></span>' . lang('Select') . '</span>
                    </span>
                    <span class="esi-caption text-truncate">' . h($images[$key]['name']) . '</span>
                    <span class="esi-meta text-truncate">' . $output_meta . '</span>
                </button>
            </div>';
    }

// Otherwise there are no results, so output an empty state that says what to do next.
} else {

    $output_warning =
        '<div class="esi-empty text-center">
            <span class="bi bi-images d-block"></span>
            <p class="h5 mb-2">' . lang('Sorry, we could not find any images.') . '</p>
            <p class="text-body-secondary mb-4">' . (($has_any_filter == true) ? lang('Try a different folder or search term.') : lang('There are no images to show yet.')) . '</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                ' . (($has_any_filter == true) ? '<a class="btn btn-outline-secondary no-popover" href="editor_select_image.php?reset=true&' . $url_parameters . '"><span class="bi bi-x-circle me-2"></span>' . lang('Clear Filters') . '</a>' : '') . '
            </div>
        </div>';
}
$show_unsplash = defined('UNSPLASH_ACCESS_KEY') && UNSPLASH_ACCESS_KEY !== '';

echo
output_header_secure(array('title'=>lang('Browse Images'),'icon'=>'file')) . '
<style>
/* ------------------------------------------------------------------ *
 *  Image picker
 *  Uploading now lives in the File Manager, so this screen is only a
 *  browser: a filter bar, a grid of tiles and a pager.
 * ------------------------------------------------------------------ */
/* Sort, folder, access and search read as one control rather than four loose
   widgets scattered along the bar, so they are drawn as segments of a single
   pill with hairline dividers between them. */
.esi-filters {
    display: flex;
    align-items: center;
    gap: .1rem;
    padding: .15rem .35rem;
    border: 1px solid var(--bs-border-color);
    border-radius: 2rem;
    background-color: var(--bs-tertiary-bg);
}

.esi-filters:focus-within { border-color: var(--pg-logo-color-1); }

.esi-filters .form-select,
.esi-filters .form-control {
    height: auto;
    padding: .1rem .25rem;
    border: 0;
    border-radius: 0;
    /* The same colour as the pill, rather than transparent. Chrome paints the native
       option list from the background-color of the select itself, and a transparent one
       leaves that list white in the middle of a dark panel. Matching the pill keeps
       the closed control looking seamless and the open list looking like the site. */
    background-color: var(--bs-tertiary-bg);
    box-shadow: none;
    color: inherit;
    font-size: .8125rem;
    line-height: 1.5;
}

.esi-filters .form-select {
    padding-right: 1.25rem;
    background-position: right .05rem center;
    background-size: 12px 9px;
}

/* Said out loud as well, because the rows of the list inherit "transparent" from the
   select on their own and land on the white default of the browser. */
.esi-filters .form-select option {
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.esi-filters .form-control::placeholder { color: var(--bs-secondary-color); }

.esi-divider {
    width: 1px;
    align-self: stretch;
    margin: .2rem .25rem;
    background-color: var(--bs-border-color);
}

.esi-seg-icon {
    padding-left: .2rem;
    font-size: .75rem;
    opacity: .55;
}

.esi-seg-btn,
.esi-clear-btn {
    padding: .1rem .3rem;
    border: 0;
    border-radius: 1rem;
    background-color: transparent;
    color: inherit;
    font-size: .8125rem;
    line-height: 1.5;
}

.esi-seg-btn:hover,
.esi-clear-btn:hover { background-color: var(--bs-secondary-bg); }
.esi-seg-btn::after   { display: none; }
.esi-clear-btn        { color: var(--bs-secondary-color); }

.esi-field  { width: 7.5rem; }
.esi-search { width: 10rem; }

@media (max-width: 1199.98px) {
    .esi-field  { width: 5.75rem; }
    .esi-search { width: 7.5rem; }
}

.esi-chip {
    display: inline-flex;
    align-items: center;
    max-width: 16rem;
    padding: .15rem .55rem;
    border: 1px solid var(--bs-border-color);
    border-radius: 2rem;
    background-color: var(--bs-tertiary-bg);
    color: var(--bs-body-color);
    font-size: .75rem;
    line-height: 1.6;
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.esi-chip:hover {
    border-color: var(--bs-danger-border-subtle);
    color: var(--bs-danger-text-emphasis);
}

.esi-tile {
    display: block;
    width: 100%;
    padding: 0 0 .35rem;
    border: 1px solid transparent;
    border-radius: var(--bs-border-radius-lg);
    background-color: transparent;
    color: inherit;
    text-align: left;
    transition: border-color .15s ease-in-out, background-color .15s ease-in-out, transform .15s ease-in-out;
}

.esi-tile:hover,
.esi-tile:focus-visible {
    outline: 0;
    transform: translateY(-2px);
    border-color: var(--pg-logo-color-1);
    background-color: var(--bs-tertiary-bg);
}

.esi-thumb {
    position: relative;
    display: block;
    overflow: hidden;
    aspect-ratio: 4 / 3;
    border-radius: calc(var(--bs-border-radius-lg) - 1px);
    background-color: var(--bs-tertiary-bg);
    background-image: radial-gradient(transparent, rgba(0, 0, 0, .14));
}

.esi-thumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.esi-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(0, 0, 0, .45);
    color: #fff;
    font-size: .8125rem;
    font-weight: 600;
    opacity: 0;
    transition: opacity .15s ease-in-out;
}

.esi-tile:hover .esi-overlay,
.esi-tile:focus-visible .esi-overlay { opacity: 1; }

.esi-caption {
    display: block;
    padding: .45rem .5rem 0;
    font-size: .8125rem;
}

.esi-meta {
    display: block;
    padding: 0 .5rem;
    font-size: .6875rem;
    opacity: .65;
}

.esi-tile-missing { cursor: not-allowed; opacity: .5; }
.esi-tile-missing:hover { transform: none; border-color: transparent; background-color: transparent; }

.esi-empty { padding: 4.5rem 1rem; }
.esi-empty .bi-images { font-size: 3rem; opacity: .3; margin-bottom: 1rem; }

/* Bootstrap stops at six columns per row. The picker is opened as a window of its
   own and is often dragged out to the full width of a large screen, where six tiles
   leave most of the glass empty. */
@media (min-width: 1600px) { .images > .col { flex: 0 0 auto; width: 12.5%; } }
@media (min-width: 2200px) { .images > .col { flex: 0 0 auto; width: 10%; } }
</style>
<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
    <div class="container-fluid" id="esi_toolbar">
        <span
            class="navbar-text d-flex align-items-center gap-2 py-0"
            data-bs-content="' . lang('Select the image that you want to embed in your content. Hover over an image to see more info.') . '"
            title="' . lang('Browse Images') . '">
            <span class="bi bi-images"></span>
            <span class="d-none d-md-inline">' . lang('Browse Images') . '</span>
            <span class="badge rounded-pill text-bg-secondary fw-normal">' . $number_of_results . '</span>
        </span>
        <ul class="navbar-nav ms-auto" id="files_nav_controls">
            <li class="nav-item d-flex align-items-center">
                <form id="search" action="editor_select_image.php" method="get" class="mb-0">

                    <input type="hidden" name="CKEditorFuncNum" value="' . h(($_GET['CKEditorFuncNum'] ?? '')) . '" />
                    <input type="hidden" name="file_input_name" value="' . h(($_GET['file_input_name'] ?? '')) . '" />
                    <input type="hidden" name="SingleImage" value="' . h(($_GET['SingleImage'] ?? '')) . '" />

                    <div class="esi-filters">

                        ' . $output_sort_link . '

                        <span class="esi-divider"></span>
                        <span class="esi-seg-icon bi bi-folder2"></span>
                        <select title="' . lang('Folder') . '" id="folder_id" name="folder_id" class="form-select esi-field" onchange="submit_form(\'search\')"><option value="all">' . lang('All') . '</option>' . select_folder(($_SESSION['software']['editor_select_image']['folder_id'] ?? '')) . '</select>

                        <span class="esi-divider"></span>
                        <span class="esi-seg-icon bi bi-shield-lock"></span>
                        <select title="' . lang('Access') . '" id="access_control_type" name="access_control_type" class="form-select esi-field ' . h(($_SESSION['software']['editor_select_image']['access_control_type'] ?? '')) . '" onchange="submit_form(\'search\')"><option value="all" class="all">' . lang('All') . '</option>' . select_access_control_type(($_SESSION['software']['editor_select_image']['access_control_type'] ?? ''), false) . '</select>

                        <span class="esi-divider"></span>
                        <span class="esi-seg-icon bi bi-search"></span>
                        <input type="text" class="form-control esi-search" name="query" placeholder="' . lang(array('string'=>'Search') ) . '" value="' . h(($_SESSION['software']['editor_select_image']['query'] ?? '')) . '" aria-label="' . lang(array('string'=>'Search') ) . '">
                        ' . $output_clear_button . '

                        <button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>
                    </div>
                </form>
            </li>
        </ul>

        <ul class="navbar-nav">
            <li class="vh nav-item pe-1 me-1 border-end my-auto"></li>
            <li class="nav-item">
                <button title="' . lang('Refresh') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.location.reload()" aria-label="' . lang('Refresh') . '">
                    <span class="bi bi-arrow-clockwise"></span>
                </button>
            </li>
            <li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
                <button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                <ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
                </ul>
            </li>
            <li class="nav-item">
                <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="' . lang('Close') . '">
                    <span class="bi bi-x-lg"></span>
                </button>
            </li>
        </ul>
    </div>
</nav>


<main class="container-fluid pb-4">

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

        ' . $output_filter_chips . '
        ' . $output_warning . '

        <div class="images row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-3 mt-1">
            ' . $output_images . '
        </div>

        <div class="mt-4">
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
        <div id="unsplash_results" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-3"></div>
        <div id="unsplash_load_more" class="text-center mt-3" style="display:none">
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="esiUnsplashSearch(true)">
                ' . lang('Load More') . '
            </button>
        </div>
    </div>
    ' : '') . '

</main>
<script>
/* The picker opens as a bare popup with no browser chrome, so the keyboard is the only
   quick way around it: Escape gives it back, "/" jumps straight to the search box. */
document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        window.close();
        return;
    }

    if (event.key === "/") {
        var active = document.activeElement;
        var tag    = active ? active.tagName : "";

        if ((tag === "INPUT") || (tag === "TEXTAREA") || (tag === "SELECT")) {
            return;
        }

        var field = document.querySelector("#search input[name=query]");

        if (field) {
            event.preventDefault();
            field.focus();
            field.select();
        }
    }
});
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