<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's recursive delete pre-flight and its recycle bin
 * sub-actions: move items into the bin, restore them, and delete them for
 * good (bin entries, empty-the-bin, or the fallback when the bin schema is
 * not installed yet). Each handler answers through respond(), which ends the
 * request.
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

// A folder and every folder below it, deepest first, so each one can be
// deleted after its children once it is emptied. $map is
// pg_explorer_folder_map(): folder rows keyed by folder id.
function pg_explorer_subtree_bottom_up($map, $folder_id)
{
    $bottom_up = array();

    $collect = function ($id) use (&$collect, &$bottom_up, $map) {
        foreach ($map as $row) {
            if ((int) $row['folder_parent'] == (int) $id) {
                $collect($row['folder_id']);
            }
        }
        $bottom_up[] = array('id' => (int) $id, 'name' => $map[$id]['folder_name']);
    };
    $collect($folder_id);

    return $bottom_up;
}

// The answer of a bin action over several items: success when nothing was
// refused, partial when some were refused next to some done, error when
// something was refused and nothing was done.
function pg_explorer_bin_status($done, $errors)
{
    return (count($errors) == 0) ? 'success' : ((($done > 0)) ? 'partial' : 'error');
}

// Reports what deleting a folder together with its contents would remove,
// and what blocks it. The deletions themselves run through the proven
// handlers (edit_pages.php for pages, this API for files, edit_folder.php
// per emptied folder) so every safety check — the submitted-forms rule above
// all — stays where it has always lived.
function pg_explorer_handle_delete_check($type, $request, $user)
{
    $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

    $map = pg_explorer_folder_map();

    if (($folder_id <= 0) || (isset($map[$folder_id]) == false)) {
        respond(array(
            'status' => 'error',
            'request' => $type,
            'missing' => true,
            'message' => lang('Sorry, the folder could not be found.')));
    }

    $folder = $map[$folder_id];

    if ((int) $folder['folder_parent'] == 0) {
        respond(array('status' => 'error', 'message' => lang('The root folder cannot be moved.')));
    }

    if (check_edit_access($folder_id) == false) {
        log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    // Collect the subtree deepest-first, so folders can be deleted
    // child before parent once they are emptied.
    $bottom_up = pg_explorer_subtree_bottom_up($map, $folder_id);

    $folder_ids = array();
    foreach ($bottom_up as $entry) {
        $folder_ids[] = (int) $entry['id'];
    }
    $sql_ids = implode(',', $folder_ids);

    $pages = db_items(
        "SELECT page_id, page_name, page_type
        FROM page
        WHERE page_folder IN (" . $sql_ids . ")");
    $pages = $pages ? $pages : array();

    $files = db_items(
        "SELECT id, name, design
        FROM files
        WHERE folder IN (" . $sql_ids . ")");
    $files = $files ? $files : array();

    $blockers = array();

    // A basic user needs the delete-pages right when pages are involved.
    if ((count($pages) > 0) && ($user['role'] == 3) && ($user['delete_pages'] == false)) {
        $blockers[] = lang('You do not have access to delete pages.');
    }

    // Custom form pages that have submitted forms are refused by the
    // page delete handler; surface them before anything is touched.
    $custom_form_page_ids = array();
    foreach ($pages as $page) {
        if ($page['page_type'] == 'custom form') {
            $custom_form_page_ids[] = (int) $page['page_id'];
        }
    }

    if (count($custom_form_page_ids) > 0) {
        $blocked_page_ids = db_values(
            "SELECT DISTINCT page_id
            FROM forms
            WHERE page_id IN (" . implode(',', $custom_form_page_ids) . ")");

        if ($blocked_page_ids) {
            $blocked_names = array();
            foreach ($pages as $page) {
                if (in_array($page['page_id'], $blocked_page_ids)) {
                    $blocked_names[] = $page['page_name'];
                }
            }
            $blockers[] = lang(array(
                'string' => 'These pages contain submitted forms and cannot be deleted: {var:1}',
                'vars' => implode(', ', $blocked_names)));
        }
    }

    // Design files fall under the designer rule.
    if ($user['role'] > 1) {
        foreach ($files as $file) {
            if ($file['design'] == 1) {
                $blockers[] = lang('Design files in this folder can only be deleted by a designer or administrator.');
                break;
            }
        }
    }

    $page_ids = array();
    foreach ($pages as $page) {
        $page_ids[] = (int) $page['page_id'];
    }

    $file_ids = array();
    foreach ($files as $file) {
        $file_ids[] = (int) $file['id'];
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'folder' => array(
            'id' => $folder_id,
            'name' => $folder['folder_name'],
            'parent_id' => (int) $folder['folder_parent']),
        'folders_bottom_up' => $bottom_up,
        'page_ids' => $page_ids,
        'file_ids' => $file_ids,
        'counts' => array(
            'folders' => count($folder_ids) - 1,
            'pages' => count($page_ids),
            'files' => count($file_ids)),
        'blockers' => $blockers));
}

// Moves files, pages and folders into the recycle bin, each with a restore
// record and its name parked.
function pg_explorer_handle_recycle_delete($type, $request, $user)
{
    if (pg_recycle_ready() == false) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $items = isset($request['items']) ? $request['items'] : array();

    if ((is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $bin_id = pg_recycle_folder_id(true);
    $bin_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($bin_id) . "'");
    $binned = 0;
    $errors = array();

    foreach ($items as $item) {

        $item_kind = isset($item['kind']) ? $item['kind'] : '';
        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        $original_parent_id = 0;

        // Files and pages are recorded in the audit trail once the
        // row has its bin folder and its parked name.
        $audit_table = ($item_kind === 'file') ? 'files' : (($item_kind === 'page') ? 'page' : '');
        $audit_before = ($audit_table !== '') ? pg_audit_fetch($audit_table, $item_id) : array();

        switch ($item_kind) {

            case 'file':

                $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($item_id) . "'");

                if (!$file) {
                    break;
                }

                if (
                    (check_edit_access($file['folder']) == false)
                    || (($file['design'] == 1) && ($user['role'] > 1))
                    || pg_recycle_is_inside($file['folder'])
                ) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                    break;
                }

                $original_parent_id = (int) $file['folder'];

                db(
                    "UPDATE files SET
                        folder = '" . e($bin_id) . "',
                        timestamp = UNIX_TIMESTAMP(),
                        user = '" . e($user['id']) . "'
                    WHERE id = '" . e($item_id) . "'");

                $binned++;
                break;

            case 'page':

                $page = db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . e($item_id) . "'");

                if (!$page) {
                    break;
                }

                if ((check_edit_access($page['page_folder']) == false) || pg_recycle_is_inside($page['page_folder'])) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $page['page_name']));
                    break;
                }

                // Sending a page to the bin is deleting it as far as
                // the operator is concerned; the basic user needs the
                // same right the pages screen demands.
                if (($user['role'] == 3) && ($user['delete_pages'] == false)) {
                    $errors[] = lang('You do not have access to delete pages.');
                    break;
                }

                $original_parent_id = (int) $page['page_folder'];

                db(
                    "UPDATE page SET
                        page_folder = '" . e($bin_id) . "',
                        page_timestamp = UNIX_TIMESTAMP(),
                        page_user = '" . e($user['id']) . "'
                    WHERE page_id = '" . e($item_id) . "'");

                $binned++;
                break;

            case 'folder':

                $folder = db_item("SELECT folder_id, folder_name, folder_parent FROM folder WHERE folder_id = '" . e($item_id) . "'");

                if (!$folder) {
                    break;
                }

                if (
                    ((int) $folder['folder_parent'] == 0)
                    || ($item_id == $bin_id)
                    || (check_edit_access($item_id) == false)
                    || pg_recycle_is_inside($item_id)
                ) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $folder['folder_name']));
                    break;
                }

                // A folder whose subtree still holds pages carries
                // them into the bin with it; without the delete-pages
                // right that is a delete the basic user may not do.
                if (($user['role'] == 3) && ($user['delete_pages'] == false)) {

                    $map_for_pages = pg_explorer_folder_map();
                    $subtree_ids = array((int) $item_id);

                    foreach ($map_for_pages as $map_row) {
                        if (pg_explorer_is_self_or_descendant($item_id, $map_row['folder_id'])) {
                            $subtree_ids[] = (int) $map_row['folder_id'];
                        }
                    }

                    $subtree_page_count = (int) db_value(
                        "SELECT COUNT(*) FROM page WHERE page_folder IN (" . implode(',', array_unique($subtree_ids)) . ")");

                    if ($subtree_page_count > 0) {
                        $errors[] = lang('You do not have access to delete pages.');
                        break;
                    }
                }

                $original_parent_id = (int) $folder['folder_parent'];

                db(
                    "UPDATE folder SET
                        folder_parent = '" . e($bin_id) . "',
                        folder_level = '" . e($bin_level + 1) . "',
                        folder_timestamp = UNIX_TIMESTAMP(),
                        folder_user = '" . e($user['id']) . "'
                    WHERE folder_id = '" . e($item_id) . "'");

                pg_explorer_change_level($item_id, $bin_level + 1);

                $binned++;
                break;

            default:
                continue 2;
        }

        if ($original_parent_id > 0) {

            // The name goes out of circulation with the row, so the
            // same file can be uploaded again without coming back as
            // "index[1].html" while the old one waits in the bin.
            pg_recycle_park_name($item_kind, $item_id, $user);

            // One restore record per top-level item; a second delete
            // of the same item replaces the old record.
            db("DELETE FROM recycle_bin WHERE (item_type = '" . e($item_kind) . "') AND (item_id = '" . e($item_id) . "')");

            db(
                "INSERT INTO recycle_bin (
                    item_type,
                    item_id,
                    original_parent_id,
                    deleted_at,
                    deleted_by)
                VALUES (
                    '" . e($item_kind) . "',
                    '" . e($item_id) . "',
                    '" . e($original_parent_id) . "',
                    UNIX_TIMESTAMP(),
                    '" . e($user['id']) . "')");

            if ($audit_table !== '') {
                pg_audit_record('recycle', $audit_table, $item_id, $audit_before);
            }
        }
    }

    if ($binned > 0) {
        log_activity(lang(array('string' => '{var:1} item(s) were moved to the recycle bin', 'vars' => $binned)), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => pg_explorer_bin_status($binned, $errors),
        'request' => $type,
        'binned' => $binned,
        'errors' => $errors,
        'message' => (count($errors) > 0)
            ? implode(' ', $errors)
            : lang(array('string' => '{var:1} item(s) were moved to the recycle bin', 'vars' => $binned))));
}

// Puts items back from the recycle bin: to the folder they came from, or to
// the root when that one is gone or is in the bin itself.
function pg_explorer_handle_recycle_restore($type, $request, $user)
{
    if (pg_recycle_ready() == false) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $items = isset($request['items']) ? $request['items'] : array();

    if ((is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $root_id = pg_explorer_root_folder_id();
    $restored = 0;
    $errors = array();

    // A page style comes back with the pages that went into the bin
    // with it. They are added to this restore right behind it, so they
    // travel the same path as any other page and are counted with the
    // rest; one restored already, or selected as well, is simply not
    // found a second time.
    $restore_items = array();

    foreach ($items as $item) {

        $restore_items[] = $item;

        if ((is_array($item)) && (isset($item['kind'])) && ($item['kind'] === 'style') && ((int) $user['role'] <= 1)) {
            foreach (pg_recycle_style_pages(isset($item['id']) ? (int) $item['id'] : 0) as $style_page_id) {
                $restore_items[] = array('kind' => 'page', 'id' => $style_page_id);
            }
        }
    }

    foreach ($restore_items as $item) {

        $item_kind = isset($item['kind']) ? $item['kind'] : '';
        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if (($item_id <= 0) || (in_array($item_kind, pg_recycle_item_types(), true) == false)) {
            continue;
        }

        // A style has no folder to go back to: its rows are written
        // back from its record.
        if ($item_kind == 'style') {

            if (pg_recycle_style_record($item_id) === null) {
                continue;
            }

            $restored_style = pg_recycle_restore_style($item_id, $user);

            if ($restored_style['status'] != 'success') {
                $errors[] = $restored_style['message'];
                continue;
            }

            $restored++;
            continue;
        }

        $entry = db_item(
            "SELECT id, original_parent_id
            FROM recycle_bin
            WHERE (item_type = '" . e($item_kind) . "') AND (item_id = '" . e($item_id) . "')");

        if (!$entry) {
            continue;
        }

        // A short link comes back by clearing its flag; there is no
        // folder to put it back into. Everything below this point is
        // about moving a row between folders, which is why it takes
        // its own way out here.
        if ($item_kind == 'short_link') {

            $restore_short_link = pg_short_link_by_id($user, $item_id, true);

            if (!$restore_short_link) {
                $errors[] = lang('Access denied');
                continue;
            }

            // The address may have been taken while it sat here. It is
            // renamed rather than refused -- a restore that fails on a
            // name leaves the operator with no way to get it back.
            $restore_short_link_name = $restore_short_link['name'];

            if (check_name_availability(array(
                    'name' => $restore_short_link_name,
                    'ignore_item_id' => $restore_short_link['id'],
                    'ignore_item_type' => 'short_link')) == false) {

                $restore_short_link_name = pg_short_link_free_name($restore_short_link['name']);

                if ($restore_short_link_name == '') {
                    $errors[] = lang('The name that you entered is already in use, so please enter a different name.');
                    continue;
                }
            }

            // Links saved while it sat here were checked without it,
            // so coming back it could close a loop or a chain too long
            // with them. Such a link stays in the bin and the reason
            // is reported.
            $restore_short_link_check = pg_short_link_redirect_columns(array(
                'id' => (int) $restore_short_link['id'],
                'name' => $restore_short_link_name,
                'destination_type' => $restore_short_link['destination_type'],
                'url' => $restore_short_link['url']), null, (int) ($restore_short_link['redirect_code'] ?? 0));

            if ($restore_short_link_check['problem'] !== null) {
                $errors[] = lang(array('string' => 'The short link {var:1} stays in the recycle bin: {var:2}', 'vars' => array($restore_short_link['name'], $restore_short_link_check['problem']['message'])));
                continue;
            }

            db(
                "UPDATE short_links
                SET recycled = '0',
                    name = '" . e($restore_short_link_name) . "'
                WHERE id = '" . e($restore_short_link['id']) . "'");

            db("DELETE FROM recycle_bin WHERE id = '" . e($entry['id']) . "'");

            log_activity(lang(array('string' => 'The short link, {var:1}, was restored.', 'vars' => h($restore_short_link_name))), $_SESSION['sessionusername']);

            $restored++;
            continue;
        }

        // Back to where it came from; to the root when that place is
        // gone or is itself in the bin by now.
        $target_id = (int) $entry['original_parent_id'];
        $map = pg_explorer_folder_map();

        if (($target_id <= 0) || (isset($map[$target_id]) == false) || pg_recycle_is_inside($target_id)) {
            $target_id = $root_id;
        }

        if (check_edit_access($target_id) == false) {
            $errors[] = lang('You do not have access to move items to the folder that you selected.');
            continue;
        }

        $audit_table = ($item_kind === 'file') ? 'files' : (($item_kind === 'page') ? 'page' : '');
        $audit_before = ($audit_table !== '') ? pg_audit_fetch($audit_table, $item_id) : array();

        switch ($item_kind) {

            case 'file':

                $design = (int) db_value("SELECT design FROM files WHERE id = '" . e($item_id) . "'");

                if (($design == 1) && ($user['role'] > 1)) {
                    $errors[] = lang('Design files in this folder can only be deleted by a designer or administrator.');
                    continue 2;
                }

                db("UPDATE files SET folder = '" . e($target_id) . "', timestamp = UNIX_TIMESTAMP(), user = '" . e($user['id']) . "' WHERE id = '" . e($item_id) . "'");
                break;

            case 'page':
                db("UPDATE page SET page_folder = '" . e($target_id) . "', page_timestamp = UNIX_TIMESTAMP(), page_user = '" . e($user['id']) . "' WHERE page_id = '" . e($item_id) . "'");

                // A page whose design is still in the bin would come
                // back with nothing to draw it; the design comes back
                // with it (without its other pages). Somebody who may
                // not restore a design gets the page alone.
                $page_style_in_bin = pg_recycle_style_of_page($item_id);

                if (($page_style_in_bin > 0) && ((int) $user['role'] <= 1)) {
                    pg_recycle_restore_style($page_style_in_bin, $user);
                }
                break;

            case 'folder':
                $target_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($target_id) . "'");
                db("UPDATE folder SET folder_parent = '" . e($target_id) . "', folder_level = '" . e($target_level + 1) . "', folder_timestamp = UNIX_TIMESTAMP(), folder_user = '" . e($user['id']) . "' WHERE folder_id = '" . e($item_id) . "'");
                pg_explorer_change_level($item_id, $target_level + 1);
                break;
        }

        // Its own name back, or the next free "[1]" form of it if
        // something has taken it while it was away.
        pg_recycle_release_name($item_kind, $item_id, $user);

        db("DELETE FROM recycle_bin WHERE id = '" . e($entry['id']) . "'");

        if ($audit_table !== '') {
            pg_audit_record('restore', $audit_table, $item_id, $audit_before);
        }

        $restored++;
    }

    if ($restored > 0) {
        log_activity(lang(array('string' => '{var:1} item(s) were restored from the recycle bin', 'vars' => $restored)), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => pg_explorer_bin_status($restored, $errors),
        'request' => $type,
        'restored' => $restored,
        'errors' => $errors,
        'message' => (count($errors) > 0)
            ? implode(' ', $errors)
            : lang(array('string' => '{var:1} item(s) were restored from the recycle bin', 'vars' => $restored))));
}

// Deletes bin entries for good; all:true empties the bin, and without the
// bin schema this is the only delete there is.
function pg_explorer_handle_hard_delete($type, $request, $user)
{
    $items = isset($request['items']) ? $request['items'] : array();

    // all:true empties the recycle bin.
    if (isset($request['all']) && ($request['all'] == true) && pg_recycle_ready()) {
        $items = array();
        // Only what this bin holds: the store's rows live in the
        // same table and are emptied from its own screen.
        $rows = db_items("SELECT item_type, item_id FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql($user) . ")");

        if ($rows) {
            foreach ($rows as $row) {
                $items[] = array('kind' => $row['item_type'], 'id' => $row['item_id']);
            }
        }
    }

    if ((is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $deleted = 0;
    $errors = array();

    foreach ($items as $item) {

        $item_kind = isset($item['kind']) ? $item['kind'] : '';
        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if (($item_id <= 0) || (in_array($item_kind, pg_recycle_item_types(), true) == false)) {
            continue;
        }

        $result = pg_recycle_hard_delete_item($item_kind, $item_id, $user);

        if ($result['status'] == 'success') {
            $deleted++;
        } else {
            $errors[] = $result['message'];
        }
    }

    if ($deleted > 0) {
        log_activity(lang(array('string' => '{var:1} item(s) were permanently deleted', 'vars' => $deleted)), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => pg_explorer_bin_status($deleted, $errors),
        'request' => $type,
        'deleted' => $deleted,
        'errors' => array_values(array_unique($errors)),
        'message' => (count($errors) > 0)
            ? implode(' ', array_unique($errors))
            : lang(array('string' => '{var:1} item(s) were permanently deleted', 'vars' => $deleted))));
}
