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
validate_area_access($user, 'user');

// prepare expanded folders array from cookie
$expanded_folders = isset($_COOKIE['software']['view_folders']['expanded_folders']) ? explode(',', $_COOKIE['software']['view_folders']['expanded_folders']) : [];

$folders_that_user_has_access_to = array();

// if user is a basic user, then get folders that user has access to
if ($user['role'] == 3) {
    $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
}

header("Content-type: text/xml");
echo '<?xml version="1.0" encoding="utf-8"?>';
echo '<root>';
echo get_folder_tree(isset($_GET['folder_id']) ? $_GET['folder_id'] : 0);
echo '</root>';

function get_folder_tree($parent_folder_id)
{
    global $user;
    global $expanded_folders;
    global $folders_that_user_has_access_to;
    
    // get styles
    $query = "SELECT style_id, style_name FROM style";
    $style_result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // get folders
    $query = "SELECT
                folder.folder_id,
                folder.folder_name,
                folder.folder_level,
                folder.folder_style,
                folder.folder_archived,
                style.style_id,
                style.style_name
             FROM folder
             LEFT JOIN style ON folder.folder_style = style.style_id
             WHERE folder.folder_parent = '" . escape($parent_folder_id) . "'
             ORDER BY folder.folder_order, folder.folder_name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $output = '';
    
    while ($row = mysqli_fetch_assoc($result)) {
        $folder_access = check_folder_access_in_array($row['folder_id'], $folders_that_user_has_access_to);

        if ($folder_access) {
            $access_control_type = get_access_control_type($row['folder_id']);
            $style = $row['folder_style'] != '0' ? $row['style_name'] : 'Inherited';
            $folder_archived = $row['folder_archived'] == '1' ? 'true' : 'false';
            
            $output .=
                '<folder>' .
                    '<id>' . $row['folder_id'] . '</id>' .
                    '<name>' . h($row['folder_name']) . '</name>' .
                    '<style>' . h($style) . '</style>' .
                    '<access_control_type>' . $access_control_type . '</access_control_type>' .
                    '<archived>' . $folder_archived . '</archived>' . "\n";
        }
        
        if (
            in_array($row['folder_id'], $expanded_folders) ||
            (isset($_GET['expand_all']) && $_GET['expand_all'] == 'true') ||
            $row['folder_id'] == 1 ||
            ($user['role'] == 3 && !$folder_access)
        ) {
            $output .= get_folder_tree($row['folder_id']);
        }

        if ($folder_access) {
            $output .= '</folder>';
        }
    }
    
    if (check_folder_access_in_array($parent_folder_id, $folders_that_user_has_access_to)) {
        // get pages
        $query = "SELECT
                    page.page_id,
                    page.page_name,
                    page.page_folder,
                    page.page_style,
                    page.page_home,
                    page.page_type,
                    style.style_id,
                    style.style_name,
                    folder.folder_archived
                 FROM page
                 LEFT JOIN style ON page.page_style = style.style_id
                 LEFT JOIN folder ON page.page_folder = folder.folder_id
                 WHERE page.page_folder = '" . escape($parent_folder_id) . "'
                 ORDER BY page.page_name";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        while ($row = mysqli_fetch_assoc($result)) {
            $style = $row['page_style'] != '0' ? $row['style_name'] : '&nbsp;';
            $home = $row['page_home'] == 'yes' ? 'true' : 'false';
            $folder_archived = $row['folder_archived'] == '1' ? 'true' : 'false';

            $output .=
                '<page>' .
                    '<id>' . $row['page_id'] . '</id>' .
                    '<name>' . h($row['page_name']) . '</name>' .
                    '<style>' . h($style) . '</style>' .
                    '<home>' . $home . '</home>' .
                    '<type>' . h($row['page_type']) . '</type>' .
                    '<archived>' . $folder_archived . '</archived>' .
                '</page>' . "\n";
        }

        // get files
        $query = "SELECT
                    files.id,
                    files.name,
                    files.type,
                    files.design,
                    folder.folder_archived
                 FROM files
                 LEFT JOIN folder ON files.folder = folder.folder_id
                 WHERE files.folder = '" . escape($parent_folder_id) . "'
                 ORDER BY files.name";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        while ($row = mysqli_fetch_assoc($result)) {
            $design = $row['design'] == '1' ? 'true' : 'false';
            $access = ($row['design'] == 0 || $user['role'] <= 1) ? 'true' : 'false';
            $folder_archived = $row['folder_archived'] == '1' ? 'true' : 'false';
         

            $output .=
                '<file>' .
                    '<id>' . $row['id'] . '</id>' .
                    '<name>' . h($row['name']) . '</name>' .
                    '<design>' . $design . '</design>' .
                    '<type>' . h($row['type']) . '</type>' .
                    '<access>' . $access . '</access>' .
                    '<archived>' . $folder_archived . '</archived>' .
                '</file>' . "\n";
        }
    }

    return $output;
}
?>
