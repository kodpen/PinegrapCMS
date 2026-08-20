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

$liveform = new liveform('view_themes');

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['design']['view_themes']['sort'] = $_REQUEST['sort'];

    // clear order
    $_SESSION['software']['design']['view_themes']['order'] = '';
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    // store sort in session
    $_SESSION['software']['design']['view_themes']['order'] = $_REQUEST['order'];
}


// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['design']['view_themes']['sort']) == false) {
    $_SESSION['software']['design']['view_themes']['sort'] = '';
}

switch (($_SESSION['software']['design']['view_themes']['sort'] ?? '')) {
    case lang('File Name'):
        $sort_column = 'name';
        break;

    case lang('Description'):
        $sort_column = 'description';
        break;

    case lang('Folder'):
        $sort_column = 'folder_name';
        break;

    case lang('Activated for Desktop'):
        $sort_column = 'activated_desktop_theme';
        break;

    case lang('Activated for Mobile'):
        $sort_column = 'activated_mobile_theme';
        break;

    case lang('Last Modified'):
        $sort_column = 'timestamp';
        break;

    default:
        $sort_column = 'timestamp';
        $_SESSION['software']['design']['view_themes']['sort'] = lang('Last Modified');
        break;
}

if (!empty($_SESSION['software']['design']['view_themes']['order'])) {
    $asc_desc = ($_SESSION['software']['design']['view_themes']['order'] ?? '');
} elseif ($sort_column == 'timestamp') {
    $asc_desc = 'desc';
    $_SESSION['software']['design']['view_themes']['order'] = 'desc';
} else {
    $asc_desc = 'asc';
    $_SESSION['software']['design']['view_themes']['order'] = 'asc';
}

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

$search_query = mb_strtolower(($_SESSION['software']['design']['view_themes']['query'] ?? ''));

// create where clause for sql
$sql_search = "(LOWER(CONCAT_WS(',', files.name, folder.folder_name, user.user_username)) LIKE '%" . escape($search_query) . "%')";

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
    ORDER BY $sort_column $asc_desc";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_assoc($result)) {
    $files[] = $row;
}

// if there is at least one result to display
if ($files) {
 
    foreach ($files as $file) {
        $output_activated_desktop_theme_check_mark = '';

        // if this theme is the activated desktop theme, then output check mark
        if ($file['activated_desktop_theme'] == 1) {
            $output_activated_desktop_theme_check_mark = '<span class="material-icons">task_alt</span>';
        }

        $output_activated_mobile_theme_check_mark = '';

        // if this theme is the activated mobile theme, then output check mark
        if ($file['activated_mobile_theme'] == 1) {
            $output_activated_mobile_theme_check_mark = '<span class="material-icons">task_alt</span>';
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
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="align-middle chart_label">' . h($file['name']) . '</td>
                <td class="align-middle">' . nl2br(h($file['description'])) . '</td>
                <td class="align-middle text-start ' . $folder_access_control_type . '" title="' . h($file['folder_name']) . '"><span class="d-block overflow-hidden text-truncate" style="width: 100px;max-width:100%;"><span class="material-icons d-inline">folder</span><span class="badge fw-light text-reset d-inline">' . h($file['folder_name']) . '</span></span><span class="badge fw-light text-reset d-inline">(' . $output_access_control . ')</span></td>
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
        'heading'=>lang('All Themes')
    ]) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All CSS stylesheet files used to add consistency to design and content.') . '" title="' . lang('All Themes') . '">' . lang('All Themes') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_theme_file.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('File Name'), ($_SESSION['software']['design']['view_themes']['sort'] ?? ''), ($_SESSION['software']['design']['view_themes']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Description'), ($_SESSION['software']['design']['view_themes']['sort'] ?? ''), ($_SESSION['software']['design']['view_themes']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Folder'), ($_SESSION['software']['design']['view_themes']['sort'] ?? ''), ($_SESSION['software']['design']['view_themes']['order'] ?? '')) . '</th>
                                <th class="text-center">' . get_column_heading(lang('Activated for Desktop'), ($_SESSION['software']['design']['view_themes']['sort'] ?? ''), ($_SESSION['software']['design']['view_themes']['order'] ?? '')) . '</th>
                                <th class="text-center">' . get_column_heading(lang('Activated for Mobile'), ($_SESSION['software']['design']['view_themes']['sort'] ?? ''), ($_SESSION['software']['design']['view_themes']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['design']['view_themes']['sort'] ?? ''), ($_SESSION['software']['design']['view_themes']['order'] ?? '')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form('view_themes');