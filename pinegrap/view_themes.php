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

$liveform = new liveform('view_themes');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'              => lang('File Name'),
    'description'       => lang('Description'),
    'folder'            => lang('Folder'),
    'activated_desktop' => lang('Activated for Desktop'),
    'activated_mobile'  => lang('Activated for Mobile'),
    'last_modified'     => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_themes', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'              => 'name',
    'description'       => 'description',
    'folder'            => 'folder_name',
    'activated_desktop' => 'activated_desktop_theme',
    'activated_mobile'  => 'activated_mobile_theme',
    'last_modified'     => 'timestamp',
));

// count all stylesheet files
$query =
    "SELECT
        COUNT(files.id)
    FROM files
    WHERE
        (type = 'css')
        AND (design = '1')
        AND (theme = '1')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_theme_files = $row[0];

$where = '';

// if the search query is not set yet, then default it to empty
if (isset($_SESSION['software']['design']['view_themes']['query']) == false) {
    $_SESSION['software']['design']['view_themes']['query'] = '';
}

$search_query = pg_search_lower($_SESSION['software']['design']['view_themes']['query'] ?? '');

// create where clause for sql
$sql_search = "(LOWER(CONCAT_WS(',', files.name, folder.folder_name, user.user_username)) LIKE '" . escape(pg_list_like_pattern($search_query)) . "')";

if (!empty($_SESSION['software']['design']['view_themes']['query'])) {
    // Get only the results the user wanted in the search.
    $where .= " AND ($sql_search) ";
}

// get all stylesheet files
$query =
    "SELECT
        files.id,
        files.name,
        files.description,
        files.folder,
        files.activated_desktop_theme,
        files.activated_mobile_theme,
        files.timestamp,
        user.user_username,
        folder.folder_name
    FROM files
    LEFT JOIN user ON user.user_id = files.user
    LEFT JOIN folder ON folder.folder_id = files.folder
    WHERE
        (type = 'css')
        AND (design = '1')
        AND (theme = '1')
        $where
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$files = array();
while ($row = mysqli_fetch_assoc($result)) {
    $files[] = $row;
}

// if there is at least one result to display
if ($files) {
 
    foreach ($files as $file) {
        $output_activated_desktop_theme_check_mark = '';

        // if this theme is the activated desktop theme, then output check mark
        if ($file['activated_desktop_theme'] == 1) {
            $output_activated_desktop_theme_check_mark = '<span class="material-icons" role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>';
        }

        $output_activated_mobile_theme_check_mark = '';

        // if this theme is the activated mobile theme, then output check mark
        if ($file['activated_mobile_theme'] == 1) {
            $output_activated_mobile_theme_check_mark = '<span class="material-icons" role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>';
        }
        
        // If the theme was upload by a user then display their name.
        if (isset($file['user_username']) == TRUE) {
            $output_created_by_user = lang(array('string'=>'by {var:1}','vars'=>h($file['user_username'])));
        } else {
            
            $output_created_by_user = lang(array('string'=>'by {var:1}','vars'=>'[' . lang('Unknown') . ']'));
        }


        // Get folder access control type
        $folder_access_control_type = get_access_control_type($file['folder']);
        // Get folder access control name
        $output_access_control = get_access_control_type_name($folder_access_control_type);

        $output_link_url = h(escape_javascript(PATH . SOFTWARE_DIRECTORY)) . '/edit_theme_file.php?id=' . $file['id'];

        $output_rows .=
            '<tr>
                <td class="align-middle text-start">
                    <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="align-middle chart_label">' . h($file['name']) . '</td>
                <td class="align-middle">' . nl2br(h($file['description'])) . '</td>
                <td class="align-middle text-start text-wrap ' . $folder_access_control_type . '">' . pg_folder_list_name($file['folder_name']) . '<span class="badge fw-light fg-reset d-inline">(' . $output_access_control . ')</span></td>
                <td class="align-middle text-center">' . $output_activated_desktop_theme_check_mark . '</td>
                <td class="align-middle text-center">' . $output_activated_mobile_theme_check_mark . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $file['timestamp'])) . ' ' . $output_created_by_user . '</td>
            </tr>';
    }
}

print
pg_page_shell([
        'title'=> lang('All Themes'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('All Themes'),
        'heading_description' => lang('All CSS stylesheet files used to add consistency to design and content.'),
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
                        <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_theme_file.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_themes.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('File Name'), $sort_levels, '', '', 'name') . '</th>
                                <th>' . get_column_heading(lang('Description'), $sort_levels, '', '', 'description') . '</th>
                                <th>' . get_column_heading(lang('Folder'), $sort_levels, '', '', 'folder') . '</th>
                                <th class="text-center">' . get_column_heading(lang('Activated for Desktop'), $sort_levels, '', '', 'activated_desktop') . '</th>
                                <th class="text-center">' . get_column_heading(lang('Activated for Mobile'), $sort_levels, '', '', 'activated_mobile') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form('view_themes');