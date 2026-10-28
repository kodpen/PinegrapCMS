<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Recycle bin helpers of the file manager: whether the bin is ready, what it
 * holds, the retention window, the bin folder and the permanent deletes of
 * pages, files, folders and binned items. Loaded by
 * view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Recycle bin ─────────────────────────────────────────────────────────
//
// Deleting from the explorer moves items into a dedicated, private, archived
// "Recycle Bin" folder instead of destroying them. Rows stay in their own
// tables, which keeps clean_up.php honest: that screen deletes disk files
// with no matching files row, so a binned file keeps its row and its bytes.
// A small recycle_bin table remembers where each top-level item came from
// and when it was binned; entries older than the retention window are
// purged permanently.

// True when the schema for the bin exists (table + config columns). The
// screen works without the upgrade: deletes then fall back to permanent
// deletion with the typed-name confirmation.
function pg_recycle_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = (pg_schema_has('recycle_bin') && pg_schema_has('config', 'recycle_retention_days'));
    }

    return $ready;
}

// What the Recycle Bin holds.
//
// One list, because the count, the listing, the restore and the emptying all
// have to agree: a bin whose badge counts three kinds and whose "empty" clears
// two is a bin that cannot be emptied. The store's rows are in the same table
// but not in this list -- they belong to the catalog screen, which has a bin
// of its own in its own area.
function pg_recycle_item_types()
{
    $types = array('folder', 'page', 'file');

    // Short links joined the bin in 2026.4.4; before it they are still deleted
    // outright, so they must not be counted as though they were waiting here.
    if (pg_short_link_recycle_ready()) {
        $types[] = 'short_link';
    }

    // Page styles joined it in 2026.4.10, on the same terms.
    if (pg_recycle_style_ready()) {
        $types[] = 'style';
    }

    return $types;
}

// The same list as a SQL fragment: 'folder', 'page', 'file', 'short_link'.
//
// With $user, only what that person is shown: page styles are listed to a
// designer alone, so for anybody else they are neither counted in the badge
// nor taken by "Empty Recycle Bin", which would only answer "Access denied"
// about rows the person cannot see.
function pg_recycle_item_types_sql($user = null)
{
    $types = pg_recycle_item_types();

    if (is_array($user) && ((int) $user['role'] > 1)) {
        $types = array_values(array_diff($types, array('style')));
    }

    return "'" . implode("', '", $types) . "'";
}

function pg_recycle_retention_days()
{
    $days = (int) db_value("SELECT recycle_retention_days FROM config");
    return ($days > 0) ? $days : 30;
}

// The bin folder id from config; created on first use. Private so the
// front end never serves binned pages or files to visitors, archived so the
// classic screens show its contents struck out of the everyday views.
function pg_recycle_folder_id($create = false)
{
    static $cached = null;

    // Before the 2026.4.7 upgrade the config columns do not exist; the
    // explorer then simply has no bin.
    if (pg_recycle_ready() == false) {
        return 0;
    }

    if (($cached !== null) && ($cached > 0)) {
        return $cached;
    }

    // One reader for "which folder is the bin", shared with the folder
    // pickers that hide it (functions.php). Only the creating half below is
    // this screen's own business.
    $id = pg_recycle_bin_folder_id();

    if ($id > 0) {
        $cached = $id;
        return $id;
    }

    if ($create == false) {
        return 0;
    }

    $root_id = pg_explorer_root_folder_id();
    $root_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($root_id) . "'");

    db(
        "INSERT INTO folder (
            folder_name,
            folder_parent,
            folder_level,
            folder_order,
            folder_access_control_type,
            folder_archived,
            folder_style,
            mobile_style_id,
            folder_timestamp,
            folder_user)
        VALUES (
            '" . e(lang('Recycle Bin')) . "',
            '" . e($root_id) . "',
            '" . e($root_level + 1) . "',
            '9999',
            'private',
            '1',
            '0',
            '0',
            UNIX_TIMESTAMP(),
            '" . e(USER_ID) . "')");

    $id = (int) mysqli_insert_id(db::$con);
    db("UPDATE config SET recycle_folder_id = '" . e($id) . "'");
    $cached = $id;

    // The shared reader cached "no bin" a moment ago; it has one now.
    pg_recycle_bin_folder_id(true);

    log_activity(lang(array('string' => 'folder ({var:1}) was created', 'vars' => array(lang('Recycle Bin')))), $_SESSION['sessionusername']);

    return $id;
}

// True when the folder is the bin itself or lives anywhere below it.
function pg_recycle_is_inside($folder_id)
{
    $bin_id = pg_recycle_folder_id(false);

    if ($bin_id <= 0) {
        return false;
    }

    return pg_explorer_is_self_or_descendant($bin_id, $folder_id);
}

// Delete one files row together with everything that hangs off it — the
// same side effects the files screen bulk delete performs.
function pg_delete_file_record($file)
{
    $audit_before = pg_audit_fetch('files', $file['id']);

    db("DELETE FROM files WHERE id = '" . e($file['id']) . "'");

    pg_audit_record('delete', 'files', (int) $file['id'], $audit_before);
    db("DELETE FROM system_theme_css_rules WHERE file_id = '" . e($file['id']) . "'");
    db("DELETE FROM preview_styles WHERE theme_id = '" . e($file['id']) . "'");
    @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);
}

// Delete one page with the full cleanup the pages screen performs. This is
// a faithful port of the delete branch in edit_pages.php so the recycle bin
// purge can run without a browser round trip; the submitted-forms refusal
// is preserved — a custom form page with stored submissions is never
// deleted from here.
//
// Returns array('status' => 'success'|'error', 'message' => ...).
function pg_delete_page_record($page_id, $user)
{
    $page = db_item(
        "SELECT
            page_id,
            page_folder,
            page_type,
            page_name,
            page_search,
            page_search_keywords
        FROM page
        WHERE page_id = '" . e($page_id) . "'");

    if (!$page) {
        return array('status' => 'success', 'message' => '');
    }

    // The folder right is decided for the user this deletion is made for,
    // not for whoever the session says is signed in. check_edit_access() reads
    // the session constants, which an entry point without a session - the
    // external API, a job - does not have, and answers no to everyone there.
    // For a panel caller the two are the same test on the same user.
    if (pg_folder_edit_access($page['page_folder'], $user['id'], $user['role']) == false) {
        return array('status' => 'error', 'message' => lang(array('string' => 'Access denied for {var:1}.', 'vars' => $page['page_name'])));
    }

    if (($user['role'] == 3) && ($user['delete_pages'] == false)) {
        return array('status' => 'error', 'message' => lang('You do not have access to delete pages.'));
    }

    $page_type = $page['page_type'];
    $page_name = $page['page_name'];

    // A custom form page with stored submissions must keep its page row;
    // the submissions reference it.
    if ($page_type == 'custom form') {
        if (db_value("SELECT COUNT(*) FROM forms WHERE page_id = '" . e($page_id) . "'") > 0) {
            return array('status' => 'error', 'message' => lang(array(
                'string' => 'These pages contain submitted forms and cannot be deleted: {var:1}',
                'vars' => $page_name)));
        }
    }

    $audit_before = pg_audit_fetch('page', $page_id);

    db("DELETE FROM page WHERE page_id = '" . e($page_id) . "'");

    pg_audit_record('delete', 'page', (int) $page_id, $audit_before);

    // Stored SEO findings for this page; links matter in both directions.
    if (pg_schema_has('seo_issue')) {
        db("DELETE FROM seo_issue WHERE (entity_type = 'page') AND (entity_id = '" . (int) $page_id . "')");
    }

    if (pg_schema_has('seo_link')) {
        db("DELETE FROM seo_link WHERE (from_type = 'page') AND (from_id = '" . (int) $page_id . "')");
        db("DELETE FROM seo_link WHERE (to_type = 'page') AND (to_id = '" . (int) $page_id . "')");
    }

    update_tag_cloud_keywords_for_page($page_id, 0, '', $page['page_search'], $page['page_search_keywords']);

    if ($page_type == 'search results') {
        delete_tag_cloud_keywords_for_search_results_page($page_id);
    }

    db("DELETE FROM pregion WHERE pregion_page = '" . e($page_id) . "'");
    db("DELETE FROM form_fields WHERE page_id = '" . e($page_id) . "'");
    db("DELETE FROM form_field_options WHERE page_id = '" . e($page_id) . "'");
    db("DELETE FROM target_options WHERE page_id = '" . e($page_id) . "'");

    if ($page_type == 'form list view') {
        db("DELETE FROM form_list_view_filters WHERE page_id = '" . e($page_id) . "'");
        db("DELETE FROM form_list_view_browse_fields WHERE page_id = '" . e($page_id) . "'");
        db("DELETE FROM form_view_directories_form_list_views_xref WHERE form_list_view_page_id = '" . e($page_id) . "'");
    }

    if ($page_type == 'form view directory') {
        db("DELETE FROM form_view_directories_form_list_views_xref WHERE form_view_directory_page_id = '" . e($page_id) . "'");
    }

    pg_sfv_delete_views('page_id', $page_id);

    db("DELETE FROM calendar_views_calendars_xref WHERE page_id = '" . e($page_id) . "'");
    db("DELETE FROM calendar_event_views_calendars_xref WHERE page_id = '" . e($page_id) . "'");

    if (check_for_page_type_properties($page_type) == true) {
        $page_type_table_name = str_replace(' ', '_', $page_type) . '_pages';
        db("DELETE FROM " . $page_type_table_name . " WHERE page_id = '" . e($page_id) . "'");
    }

    // Comment attachments die with the page unless another comment still
    // points at the same file (duplicated pages share attachments).
    $attachments = db_items(
        "SELECT
            comments.id AS comment_id,
            files.id,
            files.name
        FROM comments
        LEFT JOIN files ON comments.file_id = files.id
        WHERE
            (comments.page_id = '" . e($page_id) . "')
            AND (files.id IS NOT NULL)");

    if ($attachments) {
        foreach ($attachments as $attachment) {
            $still_used = db_value(
                "SELECT COUNT(*)
                FROM comments
                WHERE (file_id = '" . e($attachment['id']) . "') AND (id != '" . e($attachment['comment_id']) . "')");

            if ($still_used == 0) {
                db("DELETE FROM files WHERE id = '" . e($attachment['id']) . "'");
                @unlink(FILE_DIRECTORY_PATH . '/' . $attachment['name']);
                log_activity('file attachment (' . $attachment['name'] . ') for a comment was deleted because the page (' . $page_name . ') was deleted', $_SESSION['sessionusername']);
            }
        }
    }

    db("DELETE FROM comments WHERE page_id = '" . e($page_id) . "'");
    db("DELETE FROM submitted_form_info WHERE page_id = '" . e($page_id) . "'");
    db("DELETE FROM allow_new_comments_for_items WHERE page_id = '" . e($page_id) . "'");
    db("DELETE FROM watchers WHERE page_id = '" . e($page_id) . "'");
    db("DELETE FROM short_links WHERE (destination_type = 'page') AND (page_id = '" . e($page_id) . "')");
    db("DELETE FROM preview_styles WHERE page_id = '" . e($page_id) . "'");

    if (file_exists(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php')) {
        unlink(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php');
    }

    return array('status' => 'success', 'message' => '');
}

// Permanently delete a folder with everything below it, deepest first.
// Collects errors instead of stopping: a refused page (submitted forms)
// leaves its folder chain in place and everything else still goes.
function pg_delete_folder_recursive($folder_id, $user, &$errors)
{
    // Children first.
    $children = db_values("SELECT folder_id FROM folder WHERE folder_parent = '" . e($folder_id) . "'");

    if ($children) {
        foreach ($children as $child_id) {
            pg_delete_folder_recursive($child_id, $user, $errors);
        }
    }

    // Files in this folder.
    $files = db_items("SELECT id, name, design FROM files WHERE folder = '" . e($folder_id) . "'");

    if ($files) {
        foreach ($files as $file) {
            if (($file['design'] == 1) && ($user['role'] > 1)) {
                $errors[] = lang('Design files in this folder can only be deleted by a designer or administrator.');
                continue;
            }
            pg_delete_file_record($file);
        }
    }

    // Pages in this folder.
    $pages = db_values("SELECT page_id FROM page WHERE page_folder = '" . e($folder_id) . "'");

    if ($pages) {
        foreach ($pages as $page_id) {
            $result = pg_delete_page_record($page_id, $user);

            if ($result['status'] != 'success') {
                $errors[] = $result['message'];
            }
        }
    }

    // The folder itself goes only once it is really empty, so anything a
    // blocker kept alive keeps its folder chain too.
    $still_has_children =
        db_value("SELECT COUNT(*) FROM folder WHERE folder_parent = '" . e($folder_id) . "'")
        + db_value("SELECT COUNT(*) FROM page WHERE page_folder = '" . e($folder_id) . "'")
        + db_value("SELECT COUNT(*) FROM files WHERE folder = '" . e($folder_id) . "'");

    if ($still_has_children == 0) {
        db("DELETE FROM aclfolder WHERE aclfolder_folder = '" . e($folder_id) . "'");
        db("DELETE FROM folder WHERE folder_id = '" . e($folder_id) . "'");

        if (pg_recycle_ready()) {
            db("DELETE FROM recycle_bin WHERE (item_type = 'folder') AND (item_id = '" . e($folder_id) . "')");
        }
    }
}

// Permanently delete one bin entry (or any item, when the bin fallback is
// off). Returns array('status', 'message').
function pg_recycle_hard_delete_item($item_kind, $item_id, $user)
{
    switch ($item_kind) {

        case 'file':

            $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($item_id) . "'");

            if (!$file) {
                break;
            }

            if (
                (check_edit_access($file['folder']) == false)
                || (($file['design'] == 1) && ($user['role'] > 1))
            ) {
                return array('status' => 'error', 'message' => lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name'])));
            }

            pg_delete_file_record($file);
            log_activity(lang(array('string' => 'file ({var:1}) was deleted', 'vars' => $file['name'])), $_SESSION['sessionusername']);
            break;

        case 'page':

            $result = pg_delete_page_record($item_id, $user);

            if ($result['status'] != 'success') {
                return $result;
            }
            break;

        // A short link is one row and an address. Nothing on disk, nothing
        // pointing at it -- the row is the whole of it.
        case 'short_link':

            $short_link = pg_short_link_by_id($user, $item_id, true);

            if (!$short_link) {
                break;
            }

            db("DELETE FROM short_links WHERE id = '" . e($short_link['id']) . "'");

            log_activity(lang(array('string' => 'short link ({var:1}) was deleted', 'vars' => $short_link['name'])), $_SESSION['sessionusername']);
            break;

        // A binned style's rows were deleted on the way in; its restore record
        // is all that is left of it, and the line below removes that. The pages
        // that went in with it have records of their own and go on their own.
        case 'style':

            $kept_style = pg_recycle_style_record($item_id);

            if ($kept_style === null) {
                break;
            }

            if ((int) $user['role'] > 1) {
                return array('status' => 'error', 'message' => lang('Access denied'));
            }

            log_activity(lang(array('string' => 'style ({var:1}) was deleted', 'vars' => array($kept_style['style']['style_name']))), $_SESSION['sessionusername']);
            break;

        case 'folder':

            $folder = db_item("SELECT folder_id, folder_name, folder_parent FROM folder WHERE folder_id = '" . e($item_id) . "'");

            if (!$folder) {
                break;
            }

            if ((int) $folder['folder_parent'] == 0) {
                return array('status' => 'error', 'message' => lang('The root folder cannot be moved.'));
            }

            if (check_edit_access($item_id) == false) {
                return array('status' => 'error', 'message' => lang(array('string' => 'Access denied for {var:1}.', 'vars' => $folder['folder_name'])));
            }

            $errors = array();
            pg_delete_folder_recursive($item_id, $user, $errors);

            if (count($errors) > 0) {
                return array('status' => 'error', 'message' => implode(' ', array_unique($errors)));
            }

            log_activity(lang(array('string' => 'folder ({var:1}) was deleted', 'vars' => array($folder['folder_name']))), $_SESSION['sessionusername']);
            break;

        // The catalog is binned by a flag rather than by being moved, so its
        // rows are still in their own tables and the delete has to be the one
        // the edit screens use -- a group and a product each touch a dozen
        // tables, and a second copy of that list would drift from the first.
        //
        // The 'recycled' guard is what keeps this safe: the daily purge reads
        // recycle_bin, and a stale row there must never be able to delete a
        // group or product that is live again.
        case 'product_group':

            require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

            $group = db_item("SELECT id, name FROM product_groups WHERE (id = '" . e($item_id) . "') AND (recycled = '1')");

            if (!$group) {
                break;
            }

            // Deepest first, so a parent is never deleted while a child still
            // points at it. The whole subtree went into the bin together and it
            // leaves together.
            //
            // Without their products: binning a group never binned them, so they
            // are still on sale wherever else they are listed.
            // The walk is breadth first, so parents come before children and
            // reversing it puts the deepest rows first.
            $subtree = array_reverse(pg_catalog_recycled_subtree_ids($item_id));

            $deleted_any = false;

            foreach ($subtree as $subtree_id) {

                $outcome = pg_pb_delete_variant_set($subtree_id, FALSE);

                if ((int) $outcome['group'] == 1) {
                    $deleted_any = true;
                    db("DELETE FROM recycle_bin WHERE (item_type = 'product_group') AND (item_id = '" . e((int) $subtree_id) . "')");
                }
            }

            if ($deleted_any == false) {
                return array('status' => 'error', 'message' => lang(array('string' => 'Access denied for {var:1}.', 'vars' => $group['name'])));
            }

            log_activity(lang(array('string' => 'product group ({var:1}) was deleted', 'vars' => array($group['name']))), $_SESSION['sessionusername']);
            break;

        case 'product':

            require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

            $product = db_item("SELECT id, name FROM products WHERE (id = '" . e($item_id) . "') AND (recycled = '1')");

            if (!$product) {
                break;
            }

            if (!pg_pb_delete_product($item_id)) {
                return array('status' => 'error', 'message' => lang(array('string' => '{var:1} is part of a bundle. Take it out of the bundle before deleting it.', 'vars' => $product['name'])));
            }

            log_activity(lang(array('string' => 'product ({var:1}) was deleted', 'vars' => array($product['name']))), $_SESSION['sessionusername']);
            break;
    }

    if (pg_recycle_ready()) {
        db("DELETE FROM recycle_bin WHERE (item_type = '" . e($item_kind) . "') AND (item_id = '" . e($item_id) . "')");
    }

    return array('status' => 'success', 'message' => '');
}
