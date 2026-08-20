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
validate_area_access($user, 'designer');

$liveform = new liveform('view_styles');

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['design']['view_styles']['sort'] = $_REQUEST['sort'];

    // clear order
    $_SESSION['software']['design']['view_styles']['order'] = '';
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    // store sort in session
    $_SESSION['software']['design']['view_styles']['order'] = $_REQUEST['order'];
}

$number_of_results = 0;
$output_clear_button = '';

// if the sort is not set yet (first visit to this screen), then default it to empty so the
// switch below falls through to its default case
if (isset($_SESSION['software']['design']['view_styles']['sort']) == false) {
    $_SESSION['software']['design']['view_styles']['sort'] = '';
}

switch (($_SESSION['software']['design']['view_styles']['sort'] ?? ''))
{
    case lang('Name'):
        $sort_column = 'style_name';
        break;
    case lang('Theme'):
        $sort_column = 'theme_name';
        break;
    case lang('Collection'):
        $sort_column = 'collection';
        break;

    case lang('Layout Type'):
        $sort_column = 'layout_type';
        break;

    case lang('Last Modified'):
        $sort_column = 'style_timestamp';
        break;
    default:
        $sort_column = 'style_timestamp';
        $_SESSION['software']['design']['view_styles']['sort'] = lang('Last Modified');
}

if (!empty($_SESSION['software']['design']['view_styles']['order'])) {
    $asc_desc = ($_SESSION['software']['design']['view_styles']['order'] ?? '');
} elseif ($sort_column == 'style_timestamp') {
    $asc_desc = 'desc';
    $_SESSION['software']['design']['view_styles']['order'] = 'desc';
} else {
    $asc_desc = 'asc';
    $_SESSION['software']['design']['view_styles']['order'] = 'asc';
}

// get total number of styles
$query = "SELECT COUNT(style_id) FROM style";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_styles = $row[0];

// get the number of folders and pages that use each style
$style_usage = get_style_usage();


$query =
    "SELECT
        style.style_id as id,
        style.style_name as name,
        style.style_type as type,
        files.name AS theme_name,
        style.collection,
        style.layout_type,
        style.style_timestamp as last_modified_timestamp,
        user.user_username as last_modified_username
    FROM style
    LEFT JOIN files ON style.theme_id = files.id
    LEFT JOIN user ON style.style_user = user.user_id
    ORDER BY $sort_column $asc_desc";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$styles = array();

// Loop through the results
while ($row = mysqli_fetch_assoc($result)) {
    $styles[] = $row;
}

// loop through styles in order to prepare to output them
foreach ($styles as $style) {
    $output_link_url = 'edit_' . $style['type'] . '_style.php?id=' . $style['id'];
    
    // if the last modified username was found, then prepare to output it
    if ($style['last_modified_username']) {
        $last_modified_username = $style['last_modified_username'];
        
    // else the last modified username was not found, so prepare placeholder
    } else {
        $last_modified_username = '[' . lang('Unknown') . ']';
    }
    

    
    $output_rows .= '
        <tr>
            <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="styles[]" value="' . $style['id'] . '" class="checkbox" /></td>
			<td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle chart_label"  nowrap>' . h($style['name']) . '</td>
            <td class="align-middle text-center">' . number_format(isset($style_usage[$style['id']]) ? $style_usage[$style['id']]['number_of_folders'] : 0) . '</td>
            <td class="align-middle text-center">' . number_format(isset($style_usage[$style['id']]) ? $style_usage[$style['id']]['number_of_pages'] : 0) . '</td>
            <td class="align-middle ">' . h($style['theme_name']) . '</td>
            <td class="align-middle text-center">' . strtoupper($style['collection']) . '</td>
            <td class="align-middle ">' . ucwords($style['layout_type']) . '</td>
            <td class="align-middle " nowrap>' . get_relative_time(array('timestamp' => $style['last_modified_timestamp'])) . '  ' . h($last_modified_username) . '</td>
        </tr>';
}

print
pg_page_shell([
        'title'=> lang('All Page Styles'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('All Page Styles')
    ]) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All HTML templates that define the design and content layout for any page.') . '" title="' . lang('All Page Styles') . '">' . lang('All Page Styles') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_style.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <form name="form" action="delete_styles.php" method="post" class="disable_shortcut"> 
                        ' . get_token_field() . '
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                        </div>
                                    </th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th nowrap>' . get_column_heading(lang('Name'), ($_SESSION['software']['design']['view_styles']['sort'] ?? ''), ($_SESSION['software']['design']['view_styles']['order'] ?? '')) . '</th>
                                    <th class="text-center">' . lang('Folders Using Style') . '</th>
                                    <th class="text-center">' . lang('Pages Using Style') . '</th>
                                    <th>' . get_column_heading(lang('Theme'), ($_SESSION['software']['design']['view_styles']['sort'] ?? ''), ($_SESSION['software']['design']['view_styles']['order'] ?? '')) . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Collection'), ($_SESSION['software']['design']['view_styles']['sort'] ?? ''), ($_SESSION['software']['design']['view_styles']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Layout Type'), ($_SESSION['software']['design']['view_styles']['sort'] ?? ''), ($_SESSION['software']['design']['view_styles']['order'] ?? '')) . '</th>
                                    <th nowrap>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['design']['view_styles']['sort'] ?? ''), ($_SESSION['software']['design']['view_styles']['order'] ?? '')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                            <div class="container">
                                <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                    <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('page styles')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
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

$liveform->remove_form('view_styles');

// get the number of folders and pages that use each style
// we pass data in arrays instead of performing a query at each branch of the folder tree for performance reasons
function get_style_usage($parent_folder_id = 0, $style_id = 0, $mobile_style_id = 0, $folders = array(), $pages = array())
{
    $style_usage = array();
    
    // if this is the first time this function has run, then get all folders and pages
    if ($parent_folder_id == 0) {
        // get all folders
        $query =
            "SELECT
                folder_id as id,
                folder_parent as parent_folder_id,
                folder_style as style_id,
                mobile_style_id
            FROM folder
            ORDER BY folder_level, folder_order";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            // if this folder is the parent folder, then set styles
            if ($row['id'] == $parent_folder_id) {
                $style_id = $row['style_id'];
                $mobile_style_id = $row['mobile_style_id'];
            }
            
            $folders[] = $row;
        }
        
        // get all pages
        $query =
            "SELECT
                page_folder as folder_id,
                page_style as style_id,
                mobile_style_id
            FROM page";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $pages[] = $row;
        }
    }
    
    // increase the number of folders that use the desktop style
    if (!isset($style_usage[$style_id])) {
        $style_usage[$style_id] = array('number_of_folders' => 0, 'number_of_pages' => 0);
    }
    ++$style_usage[$style_id]['number_of_folders'];

    // if the mobile style is not also the desktop style, then increase the number of folders that use the mobile style
    // if they are using custom styles, then they could technically select the same style for both desktop and mobile,
    // if that happens, we don't want to count the style twice for the same folder
    if ($mobile_style_id != $style_id) {
        if (!isset($style_usage[$mobile_style_id])) {
            $style_usage[$mobile_style_id] = array('number_of_folders' => 0, 'number_of_pages' => 0);
        }
        ++$style_usage[$mobile_style_id]['number_of_folders'];
    }
    
    $child_folders = array();
    
    // loop through folders array in order to get all folders that are in parent folder
    foreach ($folders as $folder) {
        // if the parent folder id for this folder is equal to the parent folder id, then this is a child folder, so add to array
        if ($folder['parent_folder_id'] == $parent_folder_id) {
            $child_folders[] = $folder;
        }
    }
    
    // loop through child folders
    foreach ($child_folders as $folder) {
        // if the child folder does not have a desktop style set, then set desktop style to inherited style
        if ($folder['style_id'] == 0) {
            $folder['style_id'] = $style_id;
        }

        // if the child folder does not have a mobile style set, then set mobile style to inherited style
        if ($folder['mobile_style_id'] == 0) {
            $folder['mobile_style_id'] = $mobile_style_id;
        }
        
        $child_folder_style_usage = get_style_usage($folder['id'], $folder['style_id'], $folder['mobile_style_id'], $folders, $pages);
        
        // loop through child folder style usage, so that we can add values to array
        foreach ($child_folder_style_usage as $key => $style_id_usage) {
            if (!isset($style_usage[$key])) {
                $style_usage[$key] = array('number_of_folders' => 0, 'number_of_pages' => 0);
            }
            $style_usage[$key]['number_of_folders'] = $style_usage[$key]['number_of_folders'] + $style_id_usage['number_of_folders'];
            $style_usage[$key]['number_of_pages'] = $style_usage[$key]['number_of_pages'] + $style_id_usage['number_of_pages'];
        }
    }
    
    // loop through all pages in order to figure out which pages are in this folder
    foreach ($pages as $page) {
        // if this page is in the parent folder, then get style data for page
        if ($page['folder_id'] == $parent_folder_id) {
            // if this page has a desktop style set for it, then increment data for that desktop style
            if ($page['style_id'] != 0) {
                if (!isset($style_usage[$page['style_id']])) {
                    $style_usage[$page['style_id']] = array('number_of_folders' => 0, 'number_of_pages' => 0);
                }
                ++$style_usage[$page['style_id']]['number_of_pages'];

            // else this page does not have a desktop style set for it, so increment data for inherited desktop style
            } else {
                if (!isset($style_usage[$style_id])) {
                    $style_usage[$style_id] = array('number_of_folders' => 0, 'number_of_pages' => 0);
                }
                ++$style_usage[$style_id]['number_of_pages'];
            }

            // if the mobile style is not also the desktop style, then increase the number of pages that use the mobile style
            if ($page['mobile_style_id'] != $page['style_id']) {
                // if this page has a mobile style set for it, then increment data for that mobile style
                if ($page['mobile_style_id'] != 0) {
                    if (!isset($style_usage[$page['mobile_style_id']])) {
                        $style_usage[$page['mobile_style_id']] = array('number_of_folders' => 0, 'number_of_pages' => 0);
                    }
                    ++$style_usage[$page['mobile_style_id']]['number_of_pages'];

                // else this page does not have a mobile style set for it, so increment data for inherited mobile style
                } else {
                    if (!isset($style_usage[$mobile_style_id])) {
                        $style_usage[$mobile_style_id] = array('number_of_folders' => 0, 'number_of_pages' => 0);
                    }
                    ++$style_usage[$mobile_style_id]['number_of_pages'];
                }
            }
        }
    }
    
    return $style_usage;
}