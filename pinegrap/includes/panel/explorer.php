<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the file manager.
 *
 *   file_explorer  the folder, page and file explorer of view_folders.php;
 *                  every explorer_* sub-action is answered by
 *                  pg_explorer_handle() in view_folder_and_files_f.php
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_file_explorer($request, $action)
{
    // The session user and the folder list are global on purpose:
    // check_folder_access_in_array() (includes/fn/auth.php), the short-link
    // option builders pg_explorer_handle() calls, and the
    // get_folder_breadcrumb() / get_folder_table() functions declared below
    // read them through `global`. As locals they would be null there, the
    // access checks would fail closed and an administrator would see no
    // folders at all.
    global $user, $folders_that_user_has_access_to;

    $user = validate_user();
    validate_token();

    // The catalog rides this same action but is not part of the folder
    // tree, so it is gated on commerce rights instead of folder edit
    // rights.  Asking a basic user for folder rights here would turn away
    // exactly the person "manage all commerce" was granted to, at the door
    // of a store the menu had just offered them.  Every explorer_catalog_*
    // sub-action re-checks the same rule for itself in
    // view_folder_and_files_f.php; this only keeps the shared preamble
    // from answering first.
    $explorer_catalog_request = (strpos((string) ($request['type'] ?? ''), 'explorer_catalog_') === 0);

    if ($explorer_catalog_request == true) {
        if (($user['role'] > 2) && ($user['manage_ecommerce'] != true)) {
            log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
            respond(array(
                'status' => 'error',
                'request' => (string) ($request['type'] ?? ''),
                'message' => lang('Access denied')));
        }
    } else {
        validate_area_access($user, 'user');
    }


    if (isset($request['folder_id']) && ($_SESSION['software']['explorer']['folder']['folder_id'] ?? '') != $request['folder_id']) {
        $_SESSION['software']['explorer']['folder']['folder_id'] = $request['folder_id'];
    }

    $folder_id = ($_SESSION['software']['explorer']['folder']['folder_id'] ?? '');
    if (!isset($folder_id)) {
        $folder_id = db("SELECT folder_id FROM folder WHERE folder.folder_parent = '0'");
    }


    if (isset($request['view_type']) && ($_SESSION['software']['explorer']['folder']['view_type'] ?? '') != $request['view_type']) {
        $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
    }

    $folder_table_view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? '');

    if (!isset($folder_table_view_type)) {
        $folder_table_view_type = 'list';
    }

    // A catalog request never carries a folder, so the folder the session
    // happens to have open is none of its business.
    if (($explorer_catalog_request == false) && (check_view_access($folder_id) == false)) {
        $response = array(
            'status' => 'error',
            'request' => $request['type'],
            'message' => lang('Access denied'),
        );
        echo encode_json($response);
        exit();
    }

    $folders_that_user_has_access_to = array();
    // prepare expanded folders array from cookie
    $expanded_folders = isset($_COOKIE['software']['view_folders']['expanded_folders']) ? explode(',', $_COOKIE['software']['view_folders']['expanded_folders']) : array();

    // if user is a basic user, then get folders that user has access to
    if ($user['role'] == 3) {
        $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
    }

    switch ($request['type']) {

        // Combined folder/page/file explorer (view_folder_and_files.php).
        // These sub-actions return structured JSON and live in their own
        // include; pg_explorer_handle() responds and exits.
        case 'explorer_list':
        case 'explorer_tree':
        case 'explorer_create_folder':
        case 'explorer_create_file':
        case 'explorer_rename':
        case 'explorer_move':
        case 'explorer_paste':
        case 'explorer_delete_files':
        case 'explorer_upload':
        case 'explorer_folder_options':
        case 'explorer_folder_access_get':
        case 'explorer_folder_access_set':
        case 'explorer_delete_check':
        case 'explorer_recycle_delete':
        case 'explorer_recycle_restore':
        case 'explorer_hard_delete':
        case 'explorer_optimize':
        case 'explorer_webp':
        case 'explorer_folder_settings_get':
        case 'explorer_folder_settings_set':
        case 'explorer_bulk_page_options':
        case 'explorer_pages_bulk_edit':
        case 'explorer_bulk_file_options':
        case 'explorer_files_bulk_edit':
        case 'explorer_shared_list':
        case 'explorer_files_design':
        case 'explorer_file_get':
        case 'explorer_file_usage':
        case 'explorer_file_save':
        case 'explorer_rotate':
        case 'explorer_backups_list':
        case 'explorer_backup_zip':
        case 'explorer_backup_rename':
        case 'explorer_backup_copy':
        case 'explorer_backup_delete':
        case 'explorer_backup_upload':
        case 'explorer_backup_extract':
        case 'explorer_backup_chmod':
        case 'explorer_zip_create':
        case 'explorer_erp_tree':
        case 'explorer_erp_list':
        case 'explorer_zip_extract':
        case 'explorer_short_links_list':
        case 'explorer_short_link_options':
        case 'explorer_short_link_create':
        case 'explorer_short_link_rename':
        case 'explorer_short_link_update':
        case 'explorer_short_link_duplicate':
        case 'explorer_short_link_delete':
        case 'explorer_catalog_list':
        case 'explorer_catalog_tree':
        case 'explorer_catalog_pages':
        case 'explorer_catalog_recycle':
        case 'explorer_catalog_restore':
        case 'explorer_catalog_purge':
        case 'explorer_catalog_enable':
        case 'explorer_bulk_product_options':
        case 'explorer_products_bulk_edit':
        case 'explorer_catalog_quick_edit':
        case 'explorer_catalog_access_get':
        case 'explorer_catalog_access_set':
        case 'explorer_catalog_membership_remove':
        case 'explorer_catalog_create_group':
        case 'explorer_catalog_rename':
        case 'explorer_catalog_paste':
            require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
            pg_explorer_handle($request, $user, $folders_that_user_has_access_to);
            break;
        case 'delete_file':
            $query =
                "SELECT 
                files.id,
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
            WHERE files.id = '" . escape($request['file_id']) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_array($result);

            // A document the ERP module keeps is read-only here, whoever
            // asks (see pg_files_include_erp_document()).
            if (is_array($row) && pg_files_include_erp_document(array($row['id']))) {
                respond(array(
                    'status' => 'error',
                    'request' => $request['type'],
                    'message' => lang('ERP documents are read-only in the file manager.')));
            }

            $file_id = $row['id'];
            $file_design = $row['design'];
            $file_folder = $row['folder'];
            $file_name = $row['name'];

            // if the user does not have edit rights to this file's folder,
            // or this file is a design file and the user is not a designer or administrator,
            // response error
            if (
                (check_edit_access($file_folder) == false)
                ||
                (
                    ($file_design == 1)
                    && ($user['role'] > 1)
                )
            ) {
                $response = array(
                    'status' => 'error',
                    'request' => $request['type'],
                    'message' => lang('Access denied'),
                );
                echo encode_json($response);
                exit();
            }

            $result = mysqli_query(db::$con, "DELETE FROM files WHERE id = '" . escape($file_id) . "'") or output_error('Query failed');
            // delete file's system css properties in case any exist
            $query = "DELETE FROM system_theme_css_rules WHERE file_id = '" . escape($file_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            db("DELETE FROM preview_styles WHERE theme_id = '" . escape($file_id) . "'");

            // Delete file on file system.
            @unlink(FILE_DIRECTORY_PATH . '/' . $file_name);

            log_activity(lang(array('string' => 'file ({var:1}) was deleted', 'vars' => $file_name)), $_SESSION['sessionusername']);

            $response = array(
                'status' => 'success',
                'request' => $request['type'],
                'deleted_file_id' => $file_id,
            );

            echo encode_json($response);
            exit();
            break;

        case 'get_folder_id':
            $response = array(
                'status' => 'success',
                'request' => $request['type'],
                'folder_id' => $folder_id,
            );
            echo encode_json($response);
            exit();
            break;

        case 'get_breadcrumb':
            function get_folder_breadcrumb($parent_folder_id)
            {
                global $user;
                global $folders_that_user_has_access_to;
                $output_parent_folder_name = '';

                $current_folder_name = db("SELECT folder_name FROM folder WHERE folder.folder_id = '" . escape($parent_folder_id) . "'");
                if (db("SELECT folder_level FROM folder WHERE folder.folder_id = '" . escape($parent_folder_id) . "'") > 0) {
                    $parent_id = $parent_folder_id;
                    for (
                        $current_folder_level = db("SELECT folder_level FROM folder WHERE folder.folder_id = '" . escape($parent_folder_id) . "'");
                        $current_folder_level >= 0;
                        $current_folder_level--
                    ) {
                        $parent_id = db("SELECT folder_parent FROM folder WHERE folder.folder_id = '" . escape($parent_id) . "'");
                        $parent_folder_name = db("SELECT folder_name FROM folder WHERE folder.folder_id = '" . escape($parent_id) . "'");
                        if ($parent_folder_name) {
                            $output_parent_folder_name = '<li class="breadcrumb-item"><a class="text-body-secondary text-decoration-none btn btn-sm btn-link py-0" href="#!" onclick="get_file_explorer({folder_id:\'' . (int) $parent_id . '\'});">' . h($parent_folder_name) . '</a></li>' . $output_parent_folder_name;
                        }

                    }

                }

                return
                    '<nav class="overflow-auto" style="--bs-border-opacity: 0.05;--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'8\' height=\'8\'%3E%3Cpath d=\'M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z\' fill=\'%236c757d\'/%3E%3C/svg%3E&#34;);">
                    <ol class="breadcrumb mb-0">
                        ' . $output_parent_folder_name . '
                        <li class="breadcrumb-item active text-body" aria-current="page">' . h($current_folder_name) . '</li>
                    </ol>
                </nav>';
            }

            $response = array(
                'status' => 'success',
                'request' => $request['type'],
                'content' => get_folder_breadcrumb($folder_id),
            );

            echo encode_json($response);
            exit();
            break;


        case 'get_tables':

            function get_folder_table($parent_folder_id, $folder_table_view_type)
            {
                global $user;
                global $folders_that_user_has_access_to;
                function get_access_control_icon_classes($access_control_type)
                {
                    switch ($access_control_type) {
                        case 'public':
                            $output = ' bi-people-fill public ';
                            break;
                        case 'guest':
                            $output = ' bi-incognito guest ';
                            break;
                        case 'registration':
                            $output = ' bi-person-fill registration ';
                            break;
                        case 'membership':
                            $output = ' bi-person-vcard-fill membership ';
                            break;
                        case 'private':
                            $output = ' bi-lock-fill private ';
                            break;
                    }
                    return $output;
                }

                function get_file_icon($file_type)
                {
                    $file_class = ' bi-file-earmark ';

                    switch (mb_strtolower($file_type)) {
                        case 'css':
                            $file_class = ' bi-filetype-css ';
                            break;
                        case 'js':
                            $file_class = ' bi-filetype-js ';
                            break;
                        case 'jpg':
                        case 'jpeg':
                        case 'png':
                        case 'gif':
                        case 'svg':
                        case 'webp':
                            $file_class = ' bi-file-earmark-image ';
                            break;
                        case 'pdf':
                            $file_class = ' bi-file-earmark-pdf ';
                            break;
                        case 'zip':
                            $file_class = ' bi-file-earmark-zip ';
                            break;
                        case 'mp4':
                            $file_class = ' bi-file-earmark-play ';
                            break;
                        case 'mp3':
                            $file_class = ' bi-file-earmark-music ';
                            break;
                    }

                    return $file_class;
                }



                if (!isset($parent_folder_id)) {
                    $parent_folder_id = db("SELECT folder_id FROM folder WHERE folder.folder_level = '0'");
                }

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
                            folder.folder_user,
                            style.style_id,
                            style.style_name,
                            user.user_username as user_username
                         FROM folder
                         LEFT JOIN style ON folder.folder_style = style.style_id
                         LEFT JOIN user ON folder.folder_user = user.user_id
                         WHERE folder.folder_parent = '" . escape($parent_folder_id) . "'
                         ORDER BY folder.folder_order, folder.folder_name";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $output = '';

                while ($folder = mysqli_fetch_assoc($result)) {
                    // if user has access to folder
                    if (check_folder_access_in_array($folder['folder_id'], $folders_that_user_has_access_to) == true) {
                        $folder_access = true;
                    } else {
                        $folder_access = false;
                    }

                    // if user has access to folder
                    if ($folder_access == true) {
                        $access_control_type = get_access_control_type($folder['folder_id']);
                        $style = '';
                        // If the folder style is not set to zero then set the page style name.
                        if ($folder['folder_style'] != '0') {
                            $style = '<span class="fs-5 bi bi-palette" title="' . lang('Style') . ': ' . h($folder['style_name']) . '"></span>';
                        }

                        $folder_archived = '';
                        if ($folder['folder_archived'] == '1') {
                            $folder_archived = '<span class="fs-5 bi bi-archive" title="' . lang('Archived') . '"></span>';
                        }

                        $folder_user = '';
                        if ($folder['user_username'] != NULL) {
                            $folder_user = h($folder['user_username']);
                        }

                        $parent_folder_pages_size = 0;
                        $parent_folder_file_size = 0;

                        //get size of parent folder or this folder
                        $parent_folder_query = "SELECT
                        folder_id
                        FROM folder
                        WHERE folder.folder_parent = '" . e($folder["folder_id"]) . "' OR folder.folder_id = '" . e($folder["folder_id"]) . "'";
                        $parent_folder_result = mysqli_query(db::$con, $parent_folder_query) or output_error('Query failed.');
                        while ($parent_folder = mysqli_fetch_assoc($parent_folder_result)) {


                            //get all page sizes from this and parent folders.
                            $parent_folder_page_query = "SELECT
                            page_id
                            FROM page
                            LEFT JOIN folder ON page.page_folder = folder.folder_id
                            WHERE folder.folder_id = '" . e($parent_folder["folder_id"]) . "'";
                            $parent_folder_page_result = mysqli_query(db::$con, $parent_folder_page_query) or output_error('Query failed.');

                            while ($parent_folder_page_rows = mysqli_fetch_assoc($parent_folder_page_result)) {
                                $parent_folder_pages_size = $parent_folder_pages_size + db("SELECT sum(char_length(pregion_content)) FROM pregion WHERE pregion_page = '" . e($parent_folder_page_rows["page_id"]) . "'");
                            }

                            //get all files sizes from this and parent folders.
                            $parent_folder_file_query = "SELECT
                            size
                            FROM files
                            LEFT JOIN folder ON files.folder = folder.folder_id
                            WHERE files.folder = '" . e($parent_folder["folder_id"]) . "'";
                            $parent_folder_file_result = mysqli_query(db::$con, $parent_folder_file_query) or output_error('Query failed.');

                            while ($parent_folder_file_rows = mysqli_fetch_assoc($parent_folder_file_result)) {
                                $parent_folder_file_size = $parent_folder_file_size + $parent_folder_file_rows["size"];
                            }

                        }

                        //Page size from pregions.
                        $size = '';
                        if ($parent_folder_pages_size > 0 || $parent_folder_file_size > 0) {
                            $size = h(convert_bytes_to_string($parent_folder_pages_size + $parent_folder_file_size));
                        }

                        if (isset($folder_table_view_type) && $folder_table_view_type == 'grid') {
                            // output folder as grid
                            $output .=
                                '<div class="col-6 col-sm-4 col-md-3 col-lg-3 col-xl-2 col-xxl-2">
                                    <div style="min-height:130px;" class="card h-100 hoverable border-0 bg-transparent shadow-none pointer user-select-none " folder_id="' . $folder['folder_id'] . '"  onclick="get_file_explorer({folder_id:\'' . $folder['folder_id'] . '\'});">
                                        <div class="card-header border-0 bg-transparent p-1 d-flex">
                                            <input class="d-none form-check-input show-on-hovered" type="checkbox" name="folders[]" value="' . $folder['folder_id'] . '" class="checkbox" />
                                        </div>
                                        <div class="card-body text-center position-relative overflow-hidden p-0">
                                            <div class="text-center position-relative">
                                                <i class="bi display-3 bi-folder ' . $access_control_type . '"></i>
                                                <i class="bi fs-5 position-absolute top-50 start-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></i>

                                            </div>
                                            <div class="d-none">' . h($style) . '</div>
                                            <div class="d-none">' . $access_control_type . '</div>
                                            <div class="d-none">' . $folder_archived . '</div>
                                        </div>
                                        <div class="card-footer border-0 p-1 text-center bg-transparent">
                                            <div class="text-truncate">' . h($folder['folder_name']) . '</div>
                                        </div>
                                    </div>
                                </div>';

                        } else {
                            // output folder as table
                            $output .=
                                '<tr type="folder" folder_id="' . $folder['folder_id'] . '" class="unselectable pointer " onclick="get_file_explorer({folder_id:\'' . $folder['folder_id'] . '\'});">' .
                                '<td class="position-relative"></td>' .
                                '<td class="d-none select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="folders[]" value="' . $folder['folder_id'] . '" class="checkbox" /></td>' .
                                '<td class="position-relative">
                                        <span class="fs-5 bi bi-folder position-relative overflow-hidden ' . $access_control_type . '" title="' . lang(ucwords($access_control_type)) . '">
                                            <span style="font-size:40%" class="bi position-absolute start-50 top-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></span>
                                        </span>
                                        ' . $folder_archived . '
                                        ' . $style . '
                                    </td>' .
                                '<td >' . h($folder['folder_name']) . '</td>' .
                                '<td >' . $size . '</td>' .
                                '<td>' . $folder_user . '</td>
                                </tr>';
                        }
                    }
                }



                // if user has access to folder
                if (check_folder_access_in_array($parent_folder_id, $folders_that_user_has_access_to) == true) {
                    // get pages
                    $query = "SELECT
                                page.page_id,
                                page.page_name,
                                page.page_folder,
                                page.page_style,
                                page.page_home,
                                page.page_type,
                                page.page_user,
                                style.style_id,
                                style.style_name,
                                folder.folder_archived,
                                folder.folder_id,
                                user.user_username as user_username
                             FROM page
                             LEFT JOIN style ON page.page_style = style.style_id
                             LEFT JOIN folder ON page.page_folder = folder.folder_id
                             LEFT JOIN user ON page.page_user = user.user_id
                             WHERE page.page_folder = '" . escape($parent_folder_id) . "'
                             ORDER BY page.page_name";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $access_control_type = '';
                    while ($page = mysqli_fetch_assoc($result)) {

                        $access_control_type = get_access_control_type($page['folder_id']);

                        $style = '';
                        // If the folder style is not set to zero then set the page style name.
                        if ($page['page_style'] != '0') {
                            $style = '<span class="fs-5 bi bi-palette" title="' . lang('Style') . ': ' . h($page['style_name']) . '"></span>';
                        }

                        //Check if page is homepage, if its output icon.
                        $home = '';
                        if ($page['page_home'] == 'yes') {
                            $home = '<span class="fs-5 bi bi-house" title="' . lang('Homepage') . '"></span>';
                        }

                        //Check if page is in archived folder.
                        $folder_archived = '';
                        if ($page['folder_archived'] == '1') {
                            $folder_archived = '<span class="fs-5 bi bi-archive" title="' . lang('Archived') . '"></span>';
                        }

                        //Page size from pregions.
                        $size = '';
                        if (db("SELECT sum(char_length(pregion_content)) FROM pregion WHERE pregion_page = '" . e($page["page_id"]) . "'") > 0) {
                            $size = h(convert_bytes_to_string(db("SELECT sum(char_length(pregion_content)) FROM pregion WHERE pregion_page = '" . e($page["page_id"]) . "'")));
                        }

                        //Modifier user.
                        $page_user = '';
                        if ($page['user_username'] != NULL) {
                            $page_user = h($page['user_username']);
                        }


                        if (isset($folder_table_view_type) && $folder_table_view_type == 'grid') {
                            // output page as grid
                            $output .=
                                '<div type="page" page_id="' . $page['page_id'] . '" class="col-6 col-sm-4 col-md-3 col-lg-3 col-xl-2 col-xxl-2 pointer custom-contextmenu explorer-contextmenu" onclick="preview_page({page_id:\'' . $page['page_id'] . '\',page_name:\'' . h($page['page_name']) . '\'})">
                                    <div style="min-height:130px;" class="card h-100 hoverable border-0 bg-transparent shadow-none" >
                                       <div class="card-header border-0 bg-transparent p-1 d-flex">
                                            <input class="d-none form-check-input show-on-hovered" type="checkbox" name="pages[]" value="' . $page['page_id'] . '" class="checkbox" />
                                        </div>
                                        <div class="card-body text-center position-relative overflow-hidden p-0">
                                            <div class="text-center position-relative">
                                                <i class="bi display-3 bi-window-fullscreen ' . $access_control_type . '"></i>
                                                <i style="top:58%;" class="bi fs-5 position-absolute start-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></i>
                                            </div>
                                            <div class="d-none">' . h($style) . '</div>
                                            <div class="d-none">' . $home . '</div>
                                            <div class="d-none">' . h($page['page_type']) . '</div>
                                            <div class="d-none">' . $access_control_type . '</div>
                                            <div class="d-none">' . $folder_archived . '</div>
                                        </div>
                                        <div class="card-footer border-0 p-1 text-center bg-transparent">
                                            <div class="text-truncate">' . h($page['page_name']) . '</div>
                                        </div>
                                    </div>
                                </div>';

                        } else {
                            // output page as table
                            $output .=
                                '<tr type="page" page_id="' . $page['page_id'] . '" class="unselectable pointer custom-contextmenu explorer-contextmenu" onclick="preview_page({page_id:\'' . $page['page_id'] . '\',page_name:\'' . h($page['page_name']) . '\'})">' .
                                '<td class="position-relative"></td>' .
                                '<td class="d-none select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="pages[]" value="' . $page['page_id'] . '" class="checkbox" /></td>' .
                                '<td class="position-relative">
                                        <span class="fs-5 position-relative  overflow-hidden bi bi-window-fullscreen ' . $access_control_type . '" title="' . $access_control_type . '">
                                            <span style="font-size:40%" class="bi position-absolute start-50 top-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></span>
                                        </span>
                                        ' . $folder_archived . '
                                        ' . $style . '
                                        ' . $home . '
                                    </td>' .
                                '<td title="page type: ' . h($page['page_type']) . ' ">' . h($page['page_name']) . '</td>' .
                                '<td>' . $size . '</td>' .
                                '<td>' . $page_user . '</td>' .
                                '</tr>';
                        }
                    }

                    // get files
                    $query = "SELECT
                                files.id,
                                files.name,
                                files.design,
                                files.type,
                                files.size,
                                files.user,
                                files.timestamp,
                                folder.folder_archived,
                                folder.folder_id,
                                user.user_username as user_username
                             FROM files
                             LEFT JOIN folder ON files.folder = folder.folder_id
                             LEFT JOIN user ON files.user = user.user_id
                             WHERE files.folder = '" . escape($parent_folder_id) . "'
                             ORDER BY files.name";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $access_control_type = '';

                    while ($file = mysqli_fetch_assoc($result)) {

                        // if the user does not have edit rights to this file's folder,
                        // or this file is a design file and the user is not a designer or administrator,
                        if (
                            (check_edit_access($file['folder_id']) == false)
                            ||
                            (
                                ($file['design'] == 1)
                                && ($user['role'] > 1)
                            )
                        ) {

                        } else {

                            $design = 'false';
                            $access_control_type = get_access_control_type($file['folder_id']);

                            // if the file is a design file, then set design to true
                            if ($file['design'] == '1') {
                                $design = 'true';
                            }
                            $file_time_before_upload = time() - $file['timestamp'];
                            $new_file_icon = '';
                            if ($file_time_before_upload < 900) {
                                $new_file_icon = '<i class="bi bi-clock-history" title="' . lang('New file') . '"></i>';
                            }

                            $access = '';
                            // if this is not a design file or if the user has access to design files,
                            // then the user has access so send that
                            if (
                                ($file['design'] == 0)
                                || ($user['role'] <= 1)
                            ) {
                                $access = 'true';
                            }



                            $file_user = '';
                            if ($file['user_username'] != NULL) {
                                $file_user = h($file['user_username']);
                            }





                            $folder_archived = '';
                            if ($file['folder_archived'] == '1') {
                                $folder_archived = '<span class="fs-5 bi bi-archive" title="' . lang('Archived') . '"></span>';
                            }



                            $size = '';
                            if ($file['size'] != '' && $file['size'] != 0) {
                                $size = h(convert_bytes_to_string($file['size']));
                            }




                            if (isset($folder_table_view_type) && $folder_table_view_type == 'grid') {
                                // If the file is an image.
                                if (
                                    (mb_strtolower($file['type']) == 'bmp')
                                    || (mb_strtolower($file['type']) == 'gif')
                                    || (mb_strtolower($file['type']) == 'jpg')
                                    || (mb_strtolower($file['type']) == 'jpeg')
                                    || (mb_strtolower($file['type']) == 'png')
                                    || (mb_strtolower($file['type']) == 'tif')
                                    || (mb_strtolower($file['type']) == 'tiff')
                                ) {

                                    // Get the dimensions of the image.
                                    $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $file['name']);
                                    $image_width = $image_size[0];
                                    $image_height = $image_size[1];

                                    // Output the image dimension to the table.
                                    $output_image_dimensions = lang('width') . ': ' . $image_width . ' px ' . lang('height') . ': ' . $image_height . ' px';

                                    // Set the maximum dimension size for the image.
                                    $max_dimension = 75;
                                    $output_image_style = '';

                                    if ($image_width >= $image_height) {
                                        $output_image_style = 'style="max-width:100%;max-height:auto;" ';
                                    } else {
                                        $output_image_style = 'style="max-width:auto;max-height:100%;" ';
                                    }

                                    // Call function to resize image.
                                    $thumbnail_dimensions = get_thumbnail_dimensions($image_width, $image_height, $max_dimension);
                                    $output_thumbnail = '<img ' . $output_image_style . ' title="' . $output_image_dimensions . '" class="position-absolute no-popover start-50 top-50 translate-middle " src="' . PATH . $file['name'] . '" />';
                                    $output_file_access_icon = '<i class="bi ' . get_access_control_icon_classes($access_control_type) . ' "></i>';
                                } else {
                                    $output_thumbnail = '
                                        <div class="text-center position-relative">
                                            <i class="bi display-3 ' . get_file_icon($file['type']) . ' ' . $access_control_type . '"></i>
                                            <i style="top:50%;" class="bi fs-5 position-absolute start-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></i>
                                        </div>';
                                    $output_image_dimensions = '';
                                    $output_file_access_icon = '';
                                }

                                // output file as grid
                                $output .=
                                    '<div type="file" class="col-6 col-sm-4 col-md-3 col-lg-3 col-xl-2 col-xxl-2 pointer custom-contextmenu explorer-contextmenu" file_id="' . $file['id'] . '"  onclick="preview_file({file_name:\'' . $file['name'] . '\',file_id:\'' . $file['id'] . '\',file_type:\'' . $file['type'] . '\'})">
                                        <div style="min-height:130px;" class="card h-100 hoverable border-0 bg-transparent shadow-none">
                                            <div class="card-header border-0 bg-transparent p-1 d-flex">
                                                <input class="d-none form-check-input show-on-hovered" type="checkbox" name="files[]" value="' . $file['id'] . '" class="checkbox" />
                                                <div class="ms-auto d-inline-block">
                                                    ' . $new_file_icon . '
                                                    ' . $output_file_access_icon . '
                                                </div>
                                            </div>
                                            <div class="card-body text-center position-relative overflow-hidden p-0">
                                                ' . $output_thumbnail . '
                                                <div class="d-none">' . $design . '</div>
                                                <div class="d-none">' . $access . '</div>
                                                <div class="d-none">' . $access_control_type . '</div>
                                                <div class="d-none">' . $folder_archived . '</div>
                                            </div>
                                            <div class="card-footer border-0 p-1 text-center bg-transparent">
                                                <div class="text-truncate">' . h($file['name']) . '</div>
                                            </div>
                                        </div>
                                    </div>';

                            } else {

                                // output file as table
                                $output .=
                                    '<tr type="file" file_id="' . $file['id'] . '" class="unselectable pointer custom-contextmenu explorer-contextmenu" onclick="preview_file({file_name:\'' . $file['name'] . '\',file_id:\'' . $file['id'] . '\',file_type:\'' . $file['type'] . '\'})">' .
                                    '<td class="position-relative"></td>' .
                                    '<td  class="d-none select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="files[]" value="' . $file['id'] . '" class="checkbox" /></td>' .
                                    '<td class="position-relative">
                                        <span class="fs-5 position-relative overflow-hidden bi ' . get_file_icon($file['type']) . ' ' . $access_control_type . '" title="' . $access_control_type . '">
                                            <span style="font-size:40%" class="bi position-absolute top-50 start-50 translate-middle' . get_access_control_icon_classes($access_control_type) . ' "></span>
                                        </span>
                                        ' . $new_file_icon . '
                                        ' . $folder_archived . '
                                    </td>' .
                                    '<td title="design:' . $design . ' |access: ' . $access . ' ">' . h($file['name']) . '</td>' .
                                    '<td>' . $size . '</td>' .
                                    '<td>' . $file_user . '</td>' .
                                    '</tr>';
                            }
                        }
                    }
                }

                if (isset($folder_table_view_type) && $folder_table_view_type == 'grid') {
                    if ($output == '') {
                        $output = '
                        <div class="container-fluid">
                        <div class="row my-5 row-cols-1 g-3">
                            <div class="col-12 text-center">
                                <i class="bi display-3 bi-folder2-open ' . $access_control_type . '"></i>
                                <p>' . lang('This folder is a bit quiet.') . '</p>
                            </div>
                        </div>
                        </div>';
                    } else {
                        $output = '<div class="container-fluid"><div class="row p-2 g-3">' . $output . '</div></div>';
                    }
                    //defualt
                } else {
                    $output_table_classes = '';
                    if (isset($folder_table_view_type) && $folder_table_view_type == 'minimal') {
                        //minimal table view
                        $output_table_classes = 'chart table table-hover table-sm table-borderless table-condensed datatable-restricted-mode datatable-no-info datatable-click-to-select';
                    } else {
                        //normal table view
                        $output_table_classes = 'chart table-condensed table-hover table datatable-restricted-mode datatable-no-info datatable-click-to-select ';
                    }

                    $output = '
                    <table class="' . $output_table_classes . '" style="width:100%" >
                        <thead>
                            <tr>
                                <th class="noVis"></th>
                                <th class="noVis d-none">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" title="' . lang(array('string' => 'Select/Deselect All')) . '" type="checkbox" id="select_all">
                                    </div>
                                </th>
                                <th class="noVis"><i class="bi bi-file-earmark"></i></th>
                                <th class="noVis">' . lang('Name') . '</th>
                                <th>' . lang('Size') . '</th>
                                <th>' . lang('Last Modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $output . '
                        </tbody>
                    </table>';
                }

                return $output;
            }
            $response = array(
                'status' => 'success',
                'request' => $request['type'],
                'view_type' => $folder_table_view_type,
                'content' => get_folder_table($folder_id, $folder_table_view_type),
            );
            echo encode_json($response);
            break;


    }
}
