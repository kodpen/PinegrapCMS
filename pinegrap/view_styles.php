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

// only ever appended to further below, so it has to start out empty
$output_rows = '';

$user = validate_user();
validate_area_access($user, 'designer');

$liveform = new liveform('view_styles');

$number_of_results = 0;
$output_clear_button = '';

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'theme'         => lang('Theme'),
    'collection'    => lang('Collection'),
    'layout_type'   => lang('Layout Type'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_styles', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'style_name',
    'theme'         => 'theme_name',
    'collection'    => 'collection',
    'layout_type'   => 'layout_type',
    'last_modified' => 'style_timestamp',
));

// Designs made with the visual editor have their own screen
// (view_system_styles.php); this list is the HTML-template styles.
$query = "SELECT COUNT(style_id) FROM style WHERE style_layout <> 'visual_designer'";
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
    WHERE style.style_layout <> 'visual_designer'
    ORDER BY $order_by";
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
    

    
    // A classic style edited as code opens in the visual designer, its code
    // as the shared component framing its pages; nothing changes on the site
    // until the design is saved (includes/fn/classic_style_design.php).
    $output_designer_button = '';
    if ($style['type'] === 'custom' && (int)$user['role'] <= 1) {
        $output_designer_button = '<a class="m-1 btn-data-control btn btn-outline theme-secondary border-2" title="' . h(lang('Open in the visual designer')) . '" href="edit_system_style.php?id=' . (int)$style['id'] . '&amp;classic=1"><i class="bi bi-magic"></i></a>';
    }

    $output_rows .= '
        <tr>
            <td class="select-all align-middle text-start"><input class="check" type="checkbox" name="styles[]" value="' . $style['id'] . '" aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => array($style['name'])))) . '" /></td>
			<td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                ' . $output_designer_button . '
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle chart_label"  nowrap>' . h($style['name']) . '</td>
            <td class="align-middle text-center">' . pg_format_number(isset($style_usage[$style['id']]) ? $style_usage[$style['id']]['number_of_folders'] : 0, 0) . '</td>
            <td class="align-middle text-center">' . pg_format_number(isset($style_usage[$style['id']]) ? $style_usage[$style['id']]['number_of_pages'] : 0, 0) . '</td>
            <td class="align-middle ">' . h($style['theme_name']) . '</td>
            <td class="align-middle text-center">' . strtoupper($style['collection']) . '</td>
            <td class="align-middle ">' . ucwords($style['layout_type']) . '</td>
            <td class="align-middle " nowrap>' . get_relative_time(array('timestamp' => $style['last_modified_timestamp'])) . '  ' . h($last_modified_username) . '</td>
        </tr>';
}

// Once the bin holds styles, the ones deleted here go there (delete_styles.php).
require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
$delete_confirmation = pg_recycle_style_ready()
    ? lang('The selected page styles will be moved to the Recycle Bin. Those still used by a folder or page are kept.')
    : lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('page styles'))));

print
pg_page_shell([
        'title'=> lang('All Page Styles'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('All Page Styles'),
        'heading_description' => lang('All HTML templates that define the design and content layout for any page.')
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
                        <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_custom_style.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <a class="btn btn-sm btn-outline theme-secondary m-1 " href="view_system_styles.php"><span class="bi bi-magic me-3"></span>' . lang('Visual Page Editor') . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_styles.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <form name="form" action="delete_styles.php" method="post" class="disable_shortcut"> 
                        ' . get_token_field() . '
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-field">
                                            <div class="switch"><input class="" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all"></div>
                                        </div>
                                    </th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th nowrap>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                    <th class="text-center">' . lang('Folders Using Style') . '</th>
                                    <th class="text-center">' . lang('Pages Using Style') . '</th>
                                    <th>' . get_column_heading(lang('Theme'), $sort_levels, '', '', 'theme') . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Collection'), $sort_levels, '', '', 'collection') . '</th>
                                    <th>' . get_column_heading(lang('Layout Type'), $sort_levels, '', '', 'layout_type') . '</th>
                                    <th nowrap>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                    <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-solid theme-danger disabled" data-confirm-content="' . $delete_confirmation . '"><span class="material-icons me-3">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
' .
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