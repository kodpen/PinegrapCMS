<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's listing sub-action: one folder's folders, pages and
 * files (the recycle bin's entries with their restore details when the
 * folder is the bin), or the flat Files, Pictures and Pages views across
 * folders. Each handler answers through respond(), which ends the request.
 * Called by pg_explorer_handle() and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The grid or list view the listing is drawn in; anything else is the grid.
function pg_explorer_list_view_type($view_type)
{
    return in_array($view_type, array('grid', 'list'), true) ? $view_type : 'grid';
}

// What the flat view across folders shows. The pictures entry is the same
// flat view narrowed to image types — the list pg_explorer_file_payload()
// treats as images. The pages entry swaps the file list for every page on
// the site instead. The Files view is narrowed by a scope; pictures and
// pages carry no narrowing of their own.
function pg_explorer_list_filter($request)
{
    $images_only = (isset($request['file_filter']) && ($request['file_filter'] == 'images'));
    $pages_only = (isset($request['file_filter']) && ($request['file_filter'] == 'pages'));

    $scope = (isset($request['file_scope']) && in_array($request['file_scope'], pg_explorer_file_scopes(), true)) ? $request['file_scope'] : '';

    if ($images_only || $pages_only) {
        $scope = '';
    }

    return array('images_only' => $images_only, 'pages_only' => $pages_only, 'scope' => $scope);
}

// The restore details of one recycle_bin row: when it was deleted, the days
// it has left before the daily purge removes it, and the name of the folder
// it came from ('' when that folder is gone). $map is
// pg_explorer_folder_map().
function pg_explorer_bin_entry($row, $retention_days, $map, $now)
{
    $age_days = (int) floor(($now - (int) $row['deleted_at']) / 86400);
    $origin_id = (int) $row['original_parent_id'];

    return array(
        'deleted_at' => (int) $row['deleted_at'],
        'days_left' => max(0, $retention_days - $age_days),
        'origin_name' => isset($map[$origin_id]) ? $map[$origin_id]['folder_name'] : '');
}

// The flat all-files view: every file in every visible, unarchived folder
// outside the recycle bin, folder shown per row. The browse position in the
// session stays untouched.
function pg_explorer_handle_list_all_files($type, $request, $user, $folders_that_user_has_access_to)
{
    $filter = pg_explorer_list_filter($request);
    $images_only = $filter['images_only'];
    $pages_only = $filter['pages_only'];
    $scope = $filter['scope'];

    $archived_only = ($scope == 'archived');

    if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
        $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
    }

    $view_type = pg_explorer_list_view_type($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

    // The view is the last thing this request writes to the session.
    pg_explorer_release_session();

    $map = pg_explorer_folder_map();
    $bin_id = pg_recycle_folder_id(false);

    // Folders whose files stay out of this view: the bin subtree,
    // and the workspace's folder (the files of its channels and
    // notes, includes/workspace/files.php), which staff open
    // folder by folder instead.
    $excluded = array();
    $workspace_id = 0;

    if ((int) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config' AND COLUMN_NAME = 'ws_folder_id'") > 0) {
        $workspace_id = (int) db_value("SELECT ws_folder_id FROM config");
    }

    if (($bin_id > 0) || ($workspace_id > 0)) {
        foreach ($map as $row) {
            if ((($bin_id > 0) && pg_explorer_is_self_or_descendant($bin_id, $row['folder_id']))
                || (($workspace_id > 0) && pg_explorer_is_self_or_descendant($workspace_id, $row['folder_id']))) {
                $excluded[] = (int) $row['folder_id'];
            }
        }
    }

    $files = array();
    $all_pages = array();

    if ($pages_only) {

        require_once(PG_FUNCTIONS_DIR . '/seo.php');

        $rows = db_items(
            "SELECT
                page.page_id,
                page.page_name,
                page.page_folder,
                page.page_style,
                " . (pg_multi_page_design_ready() ? "(page.page_tree_json IS NOT NULL AND page.page_tree_json <> '') AS has_tree," : "'0' AS has_tree,") . "
                page.mobile_style_id,
                page.page_home,
                page.page_type,
                page.page_timestamp,
                page.seo_score,
                page.seo_analysis_current,
                page.sitemap,
                page.page_search,
                page.comments,
                " . (pg_seo_schema_ready() ? "page.seo_flags, page.seo_checked_at," : "'0' AS seo_flags, '0' AS seo_checked_at,") . "
                " . pg_seo_impact_select('page.seo_score', 'page.seo_checked_at') . ",
                style.style_name,
                folder.folder_archived,
                user.user_username AS user_username
            FROM page
            LEFT JOIN style ON page.page_style = style.style_id
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            LEFT JOIN user ON page.page_user = user.user_id
            " . pg_seo_traffic_join('page', 'page.page_id') . "
            WHERE folder.folder_archived = '0'"
            . ((count($excluded) > 0) ? " AND page.page_folder NOT IN (" . implode(',', $excluded) . ")" : "")
            . " ORDER BY page.page_name");

        if ($rows) {
            foreach ($rows as $row) {
                if (pg_explorer_folder_visible($row['page_folder'], $folders_that_user_has_access_to)) {
                    $all_pages[] = pg_explorer_page_payload($row, $user);
                }
            }
        }

    } else {

        $rows = db_items(
            "SELECT
                files.id,
                files.name,
                files.folder,
                files.description,
                files.type,
                files.size,
                files.design,
                files.optimized,
                files.image_width,
                files.image_height,
                files.timestamp,
                folder.folder_archived,
                user.user_username AS user_username
            FROM files
            LEFT JOIN folder ON files.folder = folder.folder_id
            LEFT JOIN user ON files.user = user.user_id
            WHERE folder.folder_archived = '" . ($archived_only ? '1' : '0') . "'"
            . ((count($excluded) > 0) ? " AND files.folder NOT IN (" . implode(',', $excluded) . ")" : "")
            . ($images_only ? " AND LOWER(files.type) IN ('" . implode("', '", pg_explorer_image_types()) . "')" : "")
            . (($scope == 'media') ? " AND LOWER(files.type) IN ('" . implode("', '", pg_explorer_media_types()) . "')" : "")
            . (($scope == 'documents') ? " AND LOWER(files.type) NOT IN ('" . implode("', '", array_merge(pg_explorer_image_types(), pg_explorer_media_types())) . "')" : "")
            . (($scope == 'attachments') ? " AND files.attachment = '1'" : "")
            . " ORDER BY files.name");

        // An access rule is inherited down the folder tree, so it
        // is read off the resolved folder rather than the row --
        // the same way view_files.php filtered by it.
        $access_scope = in_array($scope, array('public', 'guest', 'registration', 'membership', 'private'), true) ? $scope : '';

        if ($rows) {
            foreach ($rows as $row) {
                if (pg_explorer_folder_visible($row['folder'], $folders_that_user_has_access_to)) {

                    $payload = pg_explorer_file_payload($row, $user);

                    if (($access_scope != '') && ($payload['access_control_type'] != $access_scope)) {
                        continue;
                    }

                    $files[] = $payload;
                }
            }
        }
    }

    $recycle = array(
        'available' => pg_recycle_ready(),
        'folder_id' => pg_recycle_folder_id(false),
        'retention_days' => pg_recycle_ready() ? pg_recycle_retention_days() : 0,
        'inside' => false,
        'count' => pg_recycle_ready() ? (int) db_value("SELECT COUNT(*) FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql($user) . ")") : 0);

    // The upload travels inside a json request, so the form settings do not apply to it.
    $upload_limits = pg_upload_limits();
    $upload_max_bytes = $upload_limits['json_max'];

    respond(array(
        'status' => 'success',
        'request' => $type,
        'mode' => 'all_files',
        'view_type' => $view_type,
        'scope' => $scope,
        'current' => array(
            'id' => 0,
            'name' => $pages_only ? lang('Pages') : ($images_only ? lang('Pictures') : (($scope != '') ? pg_explorer_file_scope_label($scope) : lang('Files'))),
            'is_root' => true,
            'own_access_control_type' => '',
            'access_control_type' => 'public',
            'archived' => false,
            'can_edit' => false),
        'breadcrumb' => array(),
        'recycle' => $recycle,
        'bin_entries' => array(),
        'folders' => array(),
        'pages' => $all_pages,
        'files' => $files,
        'capabilities' => array(
            'role' => (int) $user['role'],
            'is_manager' => ($user['role'] <= 2),
            'is_designer' => ($user['role'] <= 1),
            'can_create_pages' => (($user['role'] < 3) || ($user['create_pages'] == true)),
            'can_delete_pages' => (($user['role'] < 3) || ($user['delete_pages'] == true)),
            'upload_max_bytes' => $upload_max_bytes,
            'upload_max_label' => convert_bytes_to_string($upload_max_bytes),
            'resize_trigger' => (int) pg_image_settings()['file_resize_trigger'],
            'resize_target' => (int) pg_image_settings()['file_max_dimension'],
            // The upload rule's lists, so the upload window can say
            // no to "shell.php" while it can still be taken out of
            // the list. The rule is the server's; this is a copy.
            'blocked_names' => pg_blocked_upload_names(),
            'blocked_extensions' => pg_blocked_upload_extensions(),
            // Where a file created from one of the views across folders lands.
            // The top folder is the one every page and folder can reach.
            'show_product_images' => (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES,
            'root_folder_id' => (int) pg_explorer_root_folder_id()),
        'disk_usage' => convert_bytes_to_string(db_value("SELECT SUM(size) FROM files"), 2)));
}

// One folder's folders, pages and files, the folder's summary for the
// header and the breadcrumb, and the recycle bin's summary; inside the bin,
// the restore details of its entries and the short links and page styles
// that wait there without a folder.
function pg_explorer_handle_list($type, $request, $user, $folders_that_user_has_access_to)
{
    // Opportunistic daily purge of expired recycle bin entries.
    pg_recycle_purge_if_due($user);

    // The flat views across folders answer on their own.
    if (isset($request['view_mode']) && ($request['view_mode'] == 'all_files')) {
        pg_explorer_handle_list_all_files($type, $request, $user, $folders_that_user_has_access_to);
    }

    $folder_id = pg_explorer_resolve_folder(
        isset($request['folder_id']) ? $request['folder_id'] : ($_SESSION['software']['explorer']['folder']['folder_id'] ?? ''),
        $user,
        $folders_that_user_has_access_to);

    // Remember position and view so the screen reopens where it was.
    $_SESSION['software']['explorer']['folder']['folder_id'] = $folder_id;

    if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
        $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
    }

    $view_type = pg_explorer_list_view_type($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

    // Position and view are recorded; the rest of this request only reads.
    pg_explorer_release_session();

    $map = pg_explorer_folder_map();
    $counts = pg_explorer_folder_counts();

    $folders = array();

    foreach (pg_explorer_child_folders($folder_id, $user, $folders_that_user_has_access_to) as $row) {
        $folders[] = pg_explorer_folder_payload($row, $counts, $user, $folders_that_user_has_access_to);
    }

    $pages = array();
    $files = array();
    $current_visible = (($folder_id > 0) && pg_explorer_folder_visible($folder_id, $folders_that_user_has_access_to));

    if ($current_visible) {

        require_once(PG_FUNCTIONS_DIR . '/seo.php');

        $rows = db_items(
            "SELECT
                page.page_id,
                page.page_name,
                page.page_folder,
                page.page_style,
                " . (pg_multi_page_design_ready() ? "(page.page_tree_json IS NOT NULL AND page.page_tree_json <> '') AS has_tree," : "'0' AS has_tree,") . "
                page.mobile_style_id,
                page.page_home,
                page.page_type,
                page.page_timestamp,
                page.seo_score,
                page.seo_analysis_current,
                page.sitemap,
                page.page_search,
                page.comments,
                " . (pg_seo_schema_ready() ? "page.seo_flags, page.seo_checked_at," : "'0' AS seo_flags, '0' AS seo_checked_at,") . "
                " . pg_seo_impact_select('page.seo_score', 'page.seo_checked_at') . ",
                style.style_name,
                folder.folder_archived,
                user.user_username AS user_username
            FROM page
            LEFT JOIN style ON page.page_style = style.style_id
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            LEFT JOIN user ON page.page_user = user.user_id
            " . pg_seo_traffic_join('page', 'page.page_id') . "
            WHERE page.page_folder = '" . e($folder_id) . "'
            ORDER BY page.page_name");

        if ($rows) {
            foreach ($rows as $row) {
                $pages[] = pg_explorer_page_payload($row, $user);
            }
        }

        $rows = db_items(
            "SELECT
                files.id,
                files.name,
                files.folder,
                files.description,
                files.type,
                files.size,
                files.design,
                files.optimized,
                files.image_width,
                files.image_height,
                files.timestamp,
                folder.folder_archived,
                user.user_username AS user_username
            FROM files
            LEFT JOIN folder ON files.folder = folder.folder_id
            LEFT JOIN user ON files.user = user.user_id
            WHERE files.folder = '" . e($folder_id) . "'
            ORDER BY files.name");

        if ($rows) {
            foreach ($rows as $row) {
                $files[] = pg_explorer_file_payload($row, $user);
            }
        }
    }

    // Current folder summary for the header, the breadcrumb and the
    // paste/upload permission checks on the client.
    if (($folder_id > 0) && isset($map[$folder_id])) {
        $current = array(
            'id' => $folder_id,
            'name' => $map[$folder_id]['folder_name'],
            'is_root' => ((int) $map[$folder_id]['folder_parent'] == 0),
            'own_access_control_type' => (string) $map[$folder_id]['folder_access_control_type'],
            'access_control_type' => get_access_control_type($folder_id),
            'archived' => ($map[$folder_id]['folder_archived'] == '1'),
            'can_edit' => check_edit_access($folder_id));
    } else {
        $current = array(
            'id' => 0,
            'name' => lang('My Folders'),
            'is_root' => true,
            'own_access_control_type' => '',
            'access_control_type' => 'public',
            'archived' => false,
            'can_edit' => false);
    }

    // The upload travels inside a json request, so the form settings do not apply to it.
    $upload_limits = pg_upload_limits();

    $upload_max_bytes = $upload_limits['json_max'];

    // Recycle bin summary for the toolbar, plus restore metadata for
    // the items of this listing when the listing IS the bin.
    $recycle = array(
        'available' => pg_recycle_ready(),
        'folder_id' => pg_recycle_folder_id(false),
        'retention_days' => pg_recycle_ready() ? pg_recycle_retention_days() : 0,
        'inside' => false,
        'count' => 0);

    if ($recycle['available']) {
        $recycle['count'] = (int) db_value("SELECT COUNT(*) FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql($user) . ")");
        $recycle['inside'] = (($folder_id > 0) && pg_recycle_is_inside($folder_id));
    }

    $bin_entries = array();

    if ($recycle['available'] && $recycle['inside']) {
        $map_for_names = pg_explorer_folder_map();
        $rows = db_items("SELECT item_type, item_id, original_parent_id, deleted_at FROM recycle_bin");

        if ($rows) {
            foreach ($rows as $row) {
                $bin_entries[$row['item_type'] . ':' . $row['item_id']] = pg_explorer_bin_entry($row, $recycle['retention_days'], $map_for_names, time());
            }
        }

        // A parked row is listed under the name it will get back, not
        // under the parking name. The parking name is machinery; the
        // operator is looking for the thing they deleted.
        $unpark = function (&$rows_to_fix) {

            foreach ($rows_to_fix as &$row_to_fix) {

                $original = pg_recycle_parked_original($row_to_fix['name'], $row_to_fix['id']);

                if ($original != '') {
                    $row_to_fix['name'] = $original;
                }
            }

            unset($row_to_fix);
        };

        $unpark($folders);
        $unpark($pages);
        $unpark($files);

        // Short links have no folder, so nothing moved them in here --
        // they carry a flag instead. They are listed with the rest all
        // the same: one bin is one bin, and an operator emptying it
        // expects everything they deleted to be in it.
        foreach (pg_short_link_visible_rows($user, true) as $binned_short_link) {
            $files[] = pg_short_link_item($binned_short_link);
        }

        // Page styles have no folder either; their rows wait in their
        // restore records.
        foreach (pg_recycle_style_items($user) as $binned_style) {
            $files[] = $binned_style;
        }
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'view_type' => $view_type,
        'current' => $current,
        'breadcrumb' => pg_explorer_breadcrumb($folder_id, $folders_that_user_has_access_to),
        'recycle' => $recycle,
        'bin_entries' => $bin_entries,
        'folders' => $folders,
        'pages' => $pages,
        'files' => $files,
        'capabilities' => array(
            'role' => (int) $user['role'],
            'is_manager' => ($user['role'] <= 2),
            'is_designer' => ($user['role'] <= 1),
            'can_create_pages' => (($user['role'] < 3) || ($user['create_pages'] == true)),
            'can_delete_pages' => (($user['role'] < 3) || ($user['delete_pages'] == true)),
            'upload_max_bytes' => $upload_max_bytes,
            'upload_max_label' => convert_bytes_to_string($upload_max_bytes),
            'resize_trigger' => (int) pg_image_settings()['file_resize_trigger'],
            'resize_target' => (int) pg_image_settings()['file_max_dimension'],
            // The upload rule's lists, so the upload window can say no
            // to "shell.php" while it can still be taken out of the
            // list. The rule is the server's; this is a copy.
            'blocked_names' => pg_blocked_upload_names(),
            'blocked_extensions' => pg_blocked_upload_extensions(),
            'show_product_images' => (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES,
            'root_folder_id' => (int) pg_explorer_root_folder_id()),
        'disk_usage' => convert_bytes_to_string(db_value("SELECT SUM(size) FROM files"), 2)));
}
