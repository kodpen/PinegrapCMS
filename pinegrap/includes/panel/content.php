<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - pages, folders, files, forms, common and dynamic regions,
 * menus and uploads.
 *
 *   upload_file            uploads a file into a folder
 *   get_common_regions     the common regions (LiveSite migration)
 *   get_dynamic_region     one dynamic region
 *   get_dynamic_regions    the dynamic regions
 *   get_file               one design file and its content
 *   get_folders            the folders (LiveSite migration)
 *   get_form               one custom form
 *   get_forms              the custom forms
 *   get_page               one page
 *   get_pages              the pages (LiveSite migration)
 *   test                   answers success; the LiveSite migration tests its
 *                          connection with it
 *   create_dynamic_region  creates a dynamic region
 *   update_dynamic_region  saves a dynamic region
 *   sort_menu_items        the order of a menu's items
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

function pg_panel_upload_file($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();
    $user = validate_user();
    $data = $request['data'];
    $file_name = isset($request['name']) ? trim((string) $request['name']) : '';
    if (isset($request['folder'])) {
        $folder = $request['folder'];
    } else {
        $folder = ($_SESSION['software']['explorer']['folder']['folder_id'] ?? '');
    }

    // This door is open to every backend role, so it keeps the same three
    // rules as the upload screens: a folder the caller may write to, no
    // name the web server would run or read as its own settings, and the
    // same name preparation every other upload gets. Before this it wrote
    // whatever name it was handed, "shell.php" included, into any folder.
    if (($file_name == '') || ($data == '')) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    if (((int) $folder <= 0) || (check_edit_access((int) $folder) == false)) {
        log_activity(lang('access denied to upload file because user does not have access to folder'), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('You do not have access to upload files to this folder.')));
    }

    if (pg_upload_name_blocked($file_name)) {
        log_activity(lang(array('string' => 'upload of {var:1} was refused because files of that type are not allowed', 'vars' => $file_name)), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => pg_upload_blocked_message($file_name)));
    }

    $file_name = prepare_file_name($file_name);

    // get file name with and without file extension
    $file_name_without_extension = mb_substr($file_name, 0, mb_strrpos($file_name, '.'));
    $file_extension = mb_substr($file_name, mb_strrpos($file_name, '.') + 1);

    $image_data = $data;
    $image_data = explode('base64', $image_data);
    $image_data = str_replace(' ', '+', $image_data);
    $image_data = str_replace(',', '', $image_data);
    $image_data = base64_decode(array_pop($image_data));



    // Check if file name is already in use and change it if necessary.
    $file_name = get_unique_name(array(
        'name' => $file_name,
        'type' => 'file'
    ));

    // save the file
    $handle = fopen(FILE_DIRECTORY_PATH . '/' . $file_name, 'w');
    fwrite($handle, $image_data);
    fclose($handle);

    // insert file data into files table
    $query =
        "INSERT INTO files (
                name,
                folder,
                type,
                size,
                user,
                design,
                optimized,
                timestamp) 
            VALUES (
                '" . escape($file_name) . "',
                '" . escape($folder) . "',
                '" . escape($file_extension) . "',
                '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $file_name)) . "',
                '" . $user['id'] . "',
                '0',
                '0',
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $file_id = mysqli_insert_id(db::$con);

    log_activity(lang(array('string' => 'The file, {var:1}, has been uploaded.', 'vars' => h($file_name))), $_SESSION['sessionusername']);

    //return success json output
    $response = array(
        'status' => 'success',
        'name' => $file_name,
        'filesize' => h(convert_bytes_to_string(filesize(FILE_DIRECTORY_PATH . '/' . $file_name))),
        'fileid' => $file_id,
        'message' => 'Upload Success'
    );
    echo encode_json($response);
    exit();
}

function pg_panel_get_common_regions($request, $action)
{
    $common_regions = db_items(
        "SELECT
                cregion_id AS id,
                cregion_name AS name,
                cregion_content AS content
            FROM cregion
            WHERE cregion_designer_type = 'no'
            ORDER BY cregion_name ASC"
    );

    $response = array(
        'status' => 'success',
        'common_regions' => $common_regions
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_dynamic_region($request, $action)
{
    $dynamic_region = db_item(
        "SELECT
                dregion_id AS id,
                dregion_name AS name,
                dregion_code AS content
            FROM dregion
            WHERE dregion_id = '" . escape($request['dynamic_region']['id']) . "'"
    );

    // If a dynamic region was not found then respond with an error.
    if (!$dynamic_region) {
        $response = array(
            'status' => 'error',
            'message' => 'Dynamic Region could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    $response = array(
        'status' => 'success',
        'dynamic_region' => $dynamic_region
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_dynamic_regions($request, $action)
{
    $sql_content = "";

    if ($request['content'] == true) {
        $sql_content = ", dregion_code AS content";
    }

    $sql_search = "";

    if ($request['search'] != '') {
        $sql_search = "WHERE dregion_name LIKE '%" . escape(escape_like($request['search'])) . "%'";
    }

    $dynamic_regions = db_items(
        "SELECT
                dregion_id AS id,
                dregion_name AS name
                $sql_content
            FROM dregion
            $sql_search
            ORDER BY dregion_timestamp DESC"
    );

    $response = array(
        'status' => 'success',
        'dynamic_regions' => $dynamic_regions
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_file($request, $action)
{
    $file = db_item(
        "SELECT
                id,
                name,
                size,
                theme
            FROM files
            WHERE id = '" . escape($request['file']['id']) . "'"
    );

    // If a file was not found then respond with an error.
    if (!$file) {
        $response = array(
            'status' => 'error',
            'message' => 'File could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    // The key that signs the site's mail is never read out, whoever asks
    // (pg_file_withheld()); get_file.php does not serve it either.
    if (pg_file_withheld($file['name'])) {
        echo encode_json(array(
            'status' => 'error',
            'message' => lang('Access denied')
        ));
        exit();
    }

    // If this file is a CSS theme, then determine what type of theme it is.
    if ($file['theme'] == 1) {
        // If this is a system theme, then set that.
        if (db_value("SELECT COUNT(*) FROM system_theme_css_rules WHERE file_id = '" . $file['id'] . "'") > 0) {
            $file['theme_type'] = 'system';

        } else {
            $file['theme_type'] = 'custom';
        }
    }
    $file['size'] = convert_bytes_to_string($file['size']);
    $file['content'] = file_get_contents(FILE_DIRECTORY_PATH . '/' . $file['name']);

    $response = array(
        'status' => 'success',
        'file' => $file
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_folders($request, $action)
{
    $folders = db_items(
        "SELECT
                folder_id AS id,
                folder_name AS name,
                folder_parent AS parent_folder_id,
                folder_level AS level,
                folder_style AS style_id,
                mobile_style_id,
                folder_order AS sort_order,
                folder_access_control_type AS access_control_type,
                folder_archived AS archived
            FROM folder
            ORDER BY
                folder_level ASC,
                folder_order ASC,
                folder_name ASC"
    );

    $response = array(
        'status' => 'success',
        'folders' => $folders
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_form($request, $action)
{
    require_once(PG_FUNCTIONS_DIR . '/forms.php');

    $request['check_access'] = true;

    respond(get_form($request));
}

function pg_panel_get_forms($request, $action)
{
    require_once(PG_FUNCTIONS_DIR . '/forms.php');

    $request['check_access'] = true;

    respond(get_forms($request));
}

function pg_panel_get_page($request, $action)
{
    if ($request['page']['id'] != '') {
        $where = "page_id = '" . e($request['page']['id']) . "'";

    } else {
        $where = "page_name = '" . e($request['page']['name']) . "'";
    }

    $page = db_item(
        "SELECT
                page_id AS id,
                page_name AS name,
                page_type AS type,
                layout_type
            FROM page
            WHERE $where"
    );

    // If a page was not found then respond with an error.
    if (!$page) {
        $response = array(
            'status' => 'error',
            'message' => 'Page could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    // If page type properties were requested, and this page has page type properties,
    // then get them.
    if (
        isset($request['page_type_properties']) &&
        $request['page_type_properties']
        && check_for_page_type_properties($page['type'])
    ) {
        $page_type_properties = get_page_type_properties($page['id'], $page['type']);

        if ($page_type_properties) {
            $page['page_type_properties'] = $page_type_properties;
        }
    }

    $response = array(
        'status' => 'success',
        'page' => $page
    );

    echo encode_json($response);
    exit();
}

function pg_panel_get_pages($request, $action)
{
    $pages = db_items(
        "SELECT
                page_id AS id,
                page_name AS name,
                page_folder AS folder_id,
                page_style AS style_id,
                mobile_style_id,
                page_home AS home,
                page_title AS title,
                page_search AS search,
                page_search_keywords AS search_keywords,
                page_meta_description AS meta_description,
                page_meta_keywords AS meta_keywords,
                page_type AS type,
                layout_type,
                layout_modified,
                comments,
                comments_label,
                comments_message,
                comments_rating,
                comments_allow_new_comments,
                comments_disallow_new_comment_message,
                comments_automatic_publish,
                comments_allow_user_to_select_name,
                comments_require_login_to_comment,
                comments_allow_file_attachments,
                comments_show_submitted_date_and_time,
                comments_administrator_email_to_email_address,
                comments_administrator_email_subject,
                comments_administrator_email_conditional_administrators,
                comments_submitter_email_page_id,
                comments_submitter_email_subject,
                comments_watcher_email_page_id,
                comments_watcher_email_subject,
                comments_watchers_managed_by_submitter,
                seo_score,
                seo_analysis,
                seo_analysis_current,
                sitemap,
                " . (pg_page_noindex_ready() ? "noindex,
                nofollow," : "'0' AS noindex,
                '0' AS nofollow,") . "
                system_region_header,
                system_region_footer
            FROM page
            ORDER BY page_name ASC"
    );

    // Loop through the pages in order to get page type properties and page regions.
    foreach ($pages as $key => $page) {
        // If this page's type has page type properties, then get them.
        if (check_for_page_type_properties($page['type']) == true) {
            $page_type_properties = get_page_type_properties($page['id'], $page['type']);

            // If properties were found, then add them.
            if (is_array($page_type_properties) == true) {
                $pages[$key]['page_type_properties'] = $page_type_properties;
            }
        }

        $page_regions = db_items(
            "SELECT
                    pregion_id AS id,
                    pregion_name AS name,
                    pregion_content AS content,
                    pregion_page AS page_id,
                    pregion_order AS sort_order,
                    collection
                FROM pregion
                WHERE pregion_page = '" . $page['id'] . "'"
        );

        $pages[$key]['page_regions'] = $page_regions;
    }

    $response = array(
        'status' => 'success',
        'pages' => $pages
    );

    echo encode_json($response);
    exit();
}

function pg_panel_test($request, $action)
{
    $response = array('status' => 'success');

    echo encode_json($response);
    exit();
}

function pg_panel_create_dynamic_region($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();
    $user = validate_user();
    validate_area_access($user, 'administrator');

    if (($user['role'] < 1) && ((defined('DYNAMIC_REGIONS') == true) && (DYNAMIC_REGIONS == true))) {
        $name = trim($request['dynamic_region']['name']);

        $query = "INSERT INTO dregion (dregion_name, dregion_code, dregion_user, dregion_timestamp) "
            . "VALUES ('" . escape($name) . "', '" . escape($request['dynamic_region']['code']) . "', " . USER_ID . ", UNIX_TIMESTAMP())";
        // insert row into region table
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        log_activity(lang(array('string' => '{var:1} ({var:2}) was created', 'vars' => array(lang('dynamic region'), $name))), $_SESSION['sessionusername']);

        $response = array(
            'status' => 'success',
            'name' => $name
        );
    } else {
        $response = array(
            'status' => 'error',
            'message' => lang('Access denied'),
        );
    }

    echo encode_json($response);
    exit();
}

function pg_panel_update_dynamic_region($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();

    // A dynamic region is PHP that runs on every page it is placed on.
    // Creating and editing one (add_dynamic_region.php,
    // edit_dynamic_region.php) stands at administrator, and the page
    // designer only offers the region editor to role 0, so the save
    // endpoint holds the same line rather than the general designer gate.
    $user = validate_user();

    if ((int) $user['role'] !== 0) {
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied.'),
        ));
    }

    $dynamic_region = db_item(
        "SELECT dregion_id AS id
            FROM dregion
            WHERE dregion_id = '" . escape($request['dynamic_region']['id']) . "'"
    );

    // If a dynamic region was not found then respond with an error.
    if (!$dynamic_region) {
        $response = array(
            'status' => 'error',
            'message' => 'Dynamic Region could not be found.'
        );

        echo encode_json($response);
        exit();
    }

    db(
        "UPDATE dregion
            SET
                dregion_code = '" . escape($request['dynamic_region']['content']) . "',
                dregion_timestamp = UNIX_TIMESTAMP(),
                dregion_user = '" . USER_ID . "'
            WHERE dregion_id = '" . escape($request['dynamic_region']['id']) . "'"
    );

    $response = array('status' => 'success');

    echo encode_json($response);
    exit();
}

function pg_panel_sort_menu_items($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();

    $user = validate_user();

    $menu_id = (int) $request['menu_id'];
    $groups = isset($request['groups']) ? $request['groups'] : array();

    if (!$menu_id || !is_array($groups)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    if (!db_value("SELECT 1 FROM menus WHERE id = '" . e($menu_id) . "'")) {
        respond(array('status' => 'error', 'message' => lang('Access denied.')));
    }

    if (($user['role'] == 3) && (in_array($menu_id, get_items_user_can_edit('menus', $user['id'])) == false)) {
        log_activity(lang(array('string' => 'access denied because user does not have access to edit menu ({var:1})', 'vars' => $menu_id)), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('Access denied.')));
    }

    foreach ($groups as $group) {
        $parent_id = (int) (isset($group['parent_id']) ? $group['parent_id'] : 0);
        $order = isset($group['order']) ? $group['order'] : array();
        $sort_order = 1;
        foreach ($order as $item_id) {
            $item_id = (int) $item_id;
            if ($item_id <= 0) {
                continue;
            }
            db("UPDATE menu_items
                    SET sort_order = '$sort_order', parent_id = '$parent_id'
                    WHERE id = '$item_id' AND menu_id = '$menu_id'");
            $sort_order++;
        }
    }

    db("UPDATE menus SET last_modified_user_id = '" . $user['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = '$menu_id'");

    log_activity(lang(array('string' => 'menu ({var:1}) items were reordered', 'vars' => $menu_id)), $_SESSION['sessionusername']);

    respond(array('status' => 'success'));
}
