<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Recycle bin steps for pages and page styles deleted outside the file
 * manager (the pages list, the page screen, the visual editor and the page
 * styles list): binning a page or a style, its restore record and the bin
 * listing of styles. Loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Pages and page styles deleted from the other screens ───────────────
//
// The explorer is not the only screen that deletes a page or a style: the
// pages list, the page screen and the visual editor delete pages, and the
// visual editor and the page styles list delete styles. They all come here,
// so that one bin holds what was deleted wherever it was deleted.

// Move one page into the bin the way the explorer's delete does: the row keeps
// everything that hangs off it (regions, forms, comments, short links, the
// language links) and only changes folder, its name is parked and one restore
// record remembers where it came from.
//
// Returns array('status' => 'binned' | 'inside' | 'error', 'message', 'home').
// 'inside' means the page is in the bin already, where a delete is the
// permanent one; the caller takes that path itself.
function pg_recycle_page($page_id, $user)
{
    $page = db_item("SELECT page_id, page_name, page_folder, page_home FROM page WHERE page_id = '" . e((int) $page_id) . "'");

    if (!$page) {
        return array('status' => 'error', 'message' => lang('Page not found.'), 'home' => false);
    }

    if (pg_recycle_is_inside($page['page_folder'])) {
        return array('status' => 'inside', 'message' => '', 'home' => false);
    }

    if (check_edit_access($page['page_folder']) == false) {
        return array('status' => 'error', 'message' => lang(array('string' => 'Access denied for {var:1}.', 'vars' => $page['page_name'])), 'home' => false);
    }

    if (($user['role'] == 3) && ($user['delete_pages'] == false)) {
        return array('status' => 'error', 'message' => lang('You do not have access to delete pages.'), 'home' => false);
    }

    $bin_id = (int) pg_recycle_folder_id(true);

    if ($bin_id <= 0) {
        return array('status' => 'error', 'message' => lang('The Recycle Bin is not available yet. Please run the upgrade.'), 'home' => false);
    }

    $audit_before = pg_audit_fetch('page', $page['page_id']);

    db(
        "UPDATE page SET
            page_folder = '" . e($bin_id) . "',
            page_timestamp = UNIX_TIMESTAMP(),
            page_user = '" . e($user['id']) . "'
        WHERE page_id = '" . e($page['page_id']) . "'");

    pg_recycle_park_name('page', $page['page_id'], $user);

    db("DELETE FROM recycle_bin WHERE (item_type = 'page') AND (item_id = '" . e($page['page_id']) . "')");

    db(
        "INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
        VALUES ('page', '" . e($page['page_id']) . "', '" . e((int) $page['page_folder']) . "', UNIX_TIMESTAMP(), '" . e($user['id']) . "')");

    pg_audit_record('recycle', 'page', (int) $page['page_id'], $audit_before);

    return array('status' => 'binned', 'message' => '', 'home' => ($page['page_home'] === 'yes'));
}

// Whether page styles can go to the bin: the bin itself, plus what 2026.4.10
// gave it for styles -- 'style' among its item types and the item_data column
// their rows are kept in. Before that upgrade a style is deleted the way it
// always was.
function pg_recycle_style_ready()
{
    static $ready = null;

    if ($ready === null) {

        $ready = false;

        if (pg_recycle_ready()) {
            $type = db_item("SHOW COLUMNS FROM recycle_bin WHERE Field = 'item_type'");
            $data = db_item("SHOW COLUMNS FROM recycle_bin WHERE Field = 'item_data'");

            $ready = (is_array($type) && is_array($data) && (strpos((string) $type['Type'], "'style'") !== false));
        }
    }

    return $ready;
}

// A list of ids as positive integers, each once. What goes into a restore
// record and what comes back out of one both pass through here.
function pg_recycle_id_list($ids)
{
    $list = array();

    foreach ((array) $ids as $id) {

        $id = (int) $id;

        if ($id > 0) {
            $list[$id] = $id;
        }
    }

    return array_values($list);
}

// The part of a kept row its table still has a column for. A style can sit in
// the bin across an upgrade that drops a column, and an INSERT naming that
// column would fail; a column added meanwhile simply takes its default.
function pg_recycle_row_for_columns($row, $columns)
{
    $kept = array();

    foreach ((array) $row as $column => $value) {
        if (in_array((string) $column, $columns, true)) {
            $kept[$column] = $value;
        }
    }

    return $kept;
}

// One kept row written back into its table.
function pg_recycle_insert_row($table, $row)
{
    $names = array();
    $values = array();

    foreach ($row as $column => $value) {
        $names[] = '`' . str_replace('`', '', (string) $column) . '`';
        $values[] = ($value === null) ? 'NULL' : "'" . e($value) . "'";
    }

    if (count($names) == 0) {
        return false;
    }

    return db("INSERT INTO `" . $table . "` (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")");
}

// Move a page style to the bin.
//
// A style is not a row in a folder, so it cannot be moved the way a page is.
// What it is -- the style row, its cells, its theme preview rows -- is written
// into its restore record, and only then are the rows deleted, which leaves
// the tables exactly as a permanent delete did. Keeping the rows and hiding
// them instead would mean teaching every screen and picker that lists styles
// to skip them, and the one that forgot would offer a deleted style to a page.
//
// What pointed at it is written down too, so a restore can put it back: the
// folders that had it as their default design (desktop or mobile), the pages
// that had it as their mobile style, the pages built on it, and the pages the
// caller is sending to the bin with it ($page_ids), which come back with it.
//
// The caller has made its own checks (designer access, a style in use); this
// only refuses what it cannot do. Returns array('status' => 'binned' |
// 'error', 'message', 'folders' => folders whose default was cleared).
function pg_recycle_style($style_id, $user, $page_ids = array())
{
    $style_id = (int) $style_id;
    $out = array('status' => 'error', 'message' => '', 'folders' => 0);

    if (pg_recycle_style_ready() == false) {
        $out['message'] = lang('The Recycle Bin is not available yet. Please run the upgrade.');
        return $out;
    }

    $style = ($style_id > 0) ? db_item("SELECT * FROM style WHERE style_id = '" . e($style_id) . "'") : null;

    if (is_array($style) == false) {
        $out['message'] = lang('The style could not be found.');
        return $out;
    }

    $kept = array(
        'style' => $style,
        'cells' => (array) db_items("SELECT * FROM system_style_cells WHERE style_id = '" . e($style_id) . "' ORDER BY id"),
        'previews' => (array) db_items("SELECT * FROM preview_styles WHERE style_id = '" . e($style_id) . "'"),
        'folder_style' => pg_recycle_id_list(db_values("SELECT folder_id FROM folder WHERE folder_style = '" . e($style_id) . "'")),
        'folder_mobile' => pg_recycle_id_list(db_values("SELECT folder_id FROM folder WHERE mobile_style_id = '" . e($style_id) . "'")),
        'page_mobile' => pg_recycle_id_list(db_values("SELECT page_id FROM page WHERE mobile_style_id = '" . e($style_id) . "'")),
        'page_style' => pg_recycle_id_list(db_values("SELECT page_id FROM page WHERE page_style = '" . e($style_id) . "'")),
        'pages' => pg_recycle_id_list($page_ids));

    $json = json_encode($kept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Nothing is deleted unless the record that brings it back can be
    // written. A failed INSERT stops the request before the deletes below.
    if (($json === false) || ($json === '')) {
        $out['message'] = lang('Sorry, we could not accept your request.');
        return $out;
    }

    db("DELETE FROM recycle_bin WHERE (item_type = 'style') AND (item_id = '" . e($style_id) . "')");

    db(
        "INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by, item_data)
        VALUES ('style', '" . e($style_id) . "', '0', UNIX_TIMESTAMP(), '" . e((int) $user['id']) . "', '" . e($json) . "')");

    db("UPDATE page SET mobile_style_id = 0 WHERE mobile_style_id = '" . e($style_id) . "'");

    // One statement, so a folder that had it as both counts once.
    db(
        "UPDATE folder
        SET folder_style    = IF(folder_style = '" . e($style_id) . "', 0, folder_style),
            mobile_style_id = IF(mobile_style_id = '" . e($style_id) . "', 0, mobile_style_id)
        WHERE (folder_style = '" . e($style_id) . "') OR (mobile_style_id = '" . e($style_id) . "')");

    $out['folders'] = max(0, (int) mysqli_affected_rows(db::$con));

    db("DELETE FROM style WHERE style_id = '" . e($style_id) . "'");
    db("DELETE FROM system_style_cells WHERE style_id = '" . e($style_id) . "'");
    db("DELETE FROM preview_styles WHERE style_id = '" . e($style_id) . "'");

    // Its prototype share links end here, not on restore: a link knows its
    // design by id alone, and a later design may be given the same id.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
    pg_proto_links_revoke_design($style_id, (int) $user['id']);

    log_activity(lang(array('string' => 'The page style, {var:1}, was moved to the Recycle Bin.', 'vars' => h($style['style_name']))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');

    $out['status'] = 'binned';

    return $out;
}

// What a style's restore record holds, or null when there is none.
function pg_recycle_style_record($style_id)
{
    if (pg_recycle_style_ready() == false) {
        return null;
    }

    $entry = db_item(
        "SELECT id, item_data
        FROM recycle_bin
        WHERE (item_type = 'style') AND (item_id = '" . e((int) $style_id) . "')
        ORDER BY id DESC
        LIMIT 1");

    if (is_array($entry) == false) {
        return null;
    }

    $kept = json_decode((string) $entry['item_data'], true);

    if ((is_array($kept) == false) || (isset($kept['style']) == false) || (is_array($kept['style']) == false)) {
        return null;
    }

    $kept['entry_id'] = (int) $entry['id'];

    return $kept;
}

// Bring a page style back from the bin.
//
// The rows go back as they were, under the id the style had while that is
// still free -- it nearly always is: an id is not handed out again while a
// higher one exists. When something has taken it, the style comes back under
// a new id and the pages built on it are pointed at that one. A name taken
// meanwhile gets the "[1]" form, as names coming out of the bin always do.
//
// The settings that used it come back only where nothing else was chosen
// since: a folder given another default design while this one was away keeps
// that one.
//
// The pages that went to the bin with it are not restored here: the explorer
// adds them to the restore it is running, so they come back through the same
// path as any other page and are counted with the rest.
//
// Returns array('status' => 'success' | 'error', 'message', 'id').
function pg_recycle_restore_style($style_id, $user)
{
    $style_id = (int) $style_id;
    $out = array('status' => 'error', 'message' => '', 'id' => 0);

    // A style is the designer's, in the bin as out of it.
    if ((int) $user['role'] > 1) {
        $out['message'] = lang('Access denied');
        return $out;
    }

    $kept = pg_recycle_style_record($style_id);

    if ($kept === null) {
        $out['message'] = lang('The style could not be found.');
        return $out;
    }

    $row = pg_recycle_row_for_columns($kept['style'], (array) db_values("SHOW COLUMNS FROM style"));

    $id_free = ((int) db_value("SELECT COUNT(*) FROM style WHERE style_id = '" . e($style_id) . "'") == 0);

    if ($id_free) {
        $row['style_id'] = $style_id;
    } else {
        unset($row['style_id']);
    }

    $name = isset($row['style_name']) ? (string) $row['style_name'] : '';
    $free_name = get_unique_name(array('name' => $name, 'type' => 'style'));

    if ($free_name !== $name) {

        $row['style_name'] = $free_name;

        // A system style carries its name as a body class in its code, the
        // way duplicate_style.php renames it.
        if ((isset($row['style_type'])) && ($row['style_type'] == 'system') && (isset($row['style_code']))) {
            $row['style_code'] = str_replace(get_class_name($name), get_class_name($free_name), (string) $row['style_code']);
        }
    }

    pg_recycle_insert_row('style', $row);

    $new_id = $id_free ? $style_id : (int) mysqli_insert_id(db::$con);

    if ($new_id <= 0) {
        $out['message'] = lang('Sorry, we could not accept your request.');
        return $out;
    }

    $cell_columns = (array) db_values("SHOW COLUMNS FROM system_style_cells");

    foreach ((isset($kept['cells']) ? (array) $kept['cells'] : array()) as $cell) {

        $cell = pg_recycle_row_for_columns($cell, $cell_columns);

        // The cell ids are nobody's reference; fresh ones keep a cell that
        // was written meanwhile from clashing.
        unset($cell['id']);
        $cell['style_id'] = $new_id;

        pg_recycle_insert_row('system_style_cells', $cell);
    }

    $preview_columns = (array) db_values("SHOW COLUMNS FROM preview_styles");

    foreach ((isset($kept['previews']) ? (array) $kept['previews'] : array()) as $preview) {

        $preview = pg_recycle_row_for_columns($preview, $preview_columns);
        $preview['style_id'] = $new_id;

        pg_recycle_insert_row('preview_styles', $preview);
    }

    $ids_sql = function ($key) use ($kept) {
        $ids = pg_recycle_id_list(isset($kept[$key]) ? $kept[$key] : array());
        return (count($ids) > 0) ? implode(',', $ids) : '';
    };

    // Under a new id, the pages built on the old one follow it -- in the bin
    // or out of it.
    if (($new_id != $style_id) && ($ids_sql('page_style') !== '')) {
        db("UPDATE page SET page_style = '" . e($new_id) . "' WHERE (page_id IN (" . $ids_sql('page_style') . ")) AND (page_style = '" . e($style_id) . "')");
    }

    if ($ids_sql('folder_style') !== '') {
        db("UPDATE folder SET folder_style = '" . e($new_id) . "' WHERE (folder_id IN (" . $ids_sql('folder_style') . ")) AND (folder_style = '0')");
    }

    if ($ids_sql('folder_mobile') !== '') {
        db("UPDATE folder SET mobile_style_id = '" . e($new_id) . "' WHERE (folder_id IN (" . $ids_sql('folder_mobile') . ")) AND (mobile_style_id = '0')");
    }

    if ($ids_sql('page_mobile') !== '') {
        db("UPDATE page SET mobile_style_id = '" . e($new_id) . "' WHERE (page_id IN (" . $ids_sql('page_mobile') . ")) AND (mobile_style_id = '0')");
    }

    db("DELETE FROM recycle_bin WHERE (item_type = 'style') AND (item_id = '" . e($style_id) . "')");

    log_activity(lang(array('string' => 'The page style, {var:1}, was restored.', 'vars' => h($row['style_name']))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');

    $out['status'] = 'success';
    $out['id'] = $new_id;

    return $out;
}

// The binned style a page was built on, or 0. Asked when a page comes out of
// the bin on its own: a page whose design is still in the bin would come back
// with nothing to draw it, so its style comes back with it. The record says
// which pages were built on it, which is what is asked rather than the page's
// page_style: that id may belong to another style by now.
function pg_recycle_style_of_page($page_id)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || (pg_recycle_style_ready() == false)) {
        return 0;
    }

    $style_id = (int) db_value("SELECT page_style FROM page WHERE page_id = '" . e($page_id) . "'");

    if ($style_id <= 0) {
        return 0;
    }

    $kept = pg_recycle_style_record($style_id);

    if (($kept === null) || (in_array($page_id, pg_recycle_id_list(isset($kept['page_style']) ? $kept['page_style'] : array()), true) == false)) {
        return 0;
    }

    return $style_id;
}

// The pages that went to the bin with a style and are still there.
function pg_recycle_style_pages($style_id)
{
    $kept = pg_recycle_style_record($style_id);

    if ($kept === null) {
        return array();
    }

    $ids = pg_recycle_id_list(isset($kept['pages']) ? $kept['pages'] : array());

    if (count($ids) == 0) {
        return array();
    }

    return pg_recycle_id_list(db_values(
        "SELECT item_id FROM recycle_bin
        WHERE (item_type = 'page') AND (item_id IN (" . implode(',', $ids) . "))"));
}

// The bin's rows for page styles, drawn with everything else it holds. Only a
// designer sees them: restoring a style and deleting it for good both refuse
// anybody else, and a row nobody can act on is clutter.
//
// Shaped like a file row, so the grid, the list and the sorting draw it with
// the code they have; kind 'style' is what keeps it from being taken for one.
function pg_recycle_style_items($user)
{
    if (((int) $user['role'] > 1) || (pg_recycle_style_ready() == false)) {
        return array();
    }

    $rows = db_items(
        "SELECT
            recycle_bin.item_id,
            recycle_bin.item_data,
            recycle_bin.deleted_at,
            user.user_username
        FROM recycle_bin
        LEFT JOIN user ON recycle_bin.deleted_by = user.user_id
        WHERE recycle_bin.item_type = 'style'
        ORDER BY recycle_bin.deleted_at DESC");

    $items = array();

    foreach ((array) $rows as $row) {

        $kept = json_decode((string) $row['item_data'], true);

        if ((is_array($kept) == false) || (isset($kept['style']) == false) || (is_array($kept['style']) == false)) {
            continue;
        }

        $pages = count(pg_recycle_id_list(isset($kept['pages']) ? $kept['pages'] : array()));
        $timestamp = (int) $row['deleted_at'];

        $items[] = array(
            'kind' => 'style',
            'id' => (int) $row['item_id'],
            'name' => (string) $kept['style']['style_name'],
            'folder_id' => 0,
            'folder_name' => '',
            'type' => 'style',
            'is_image' => false,
            'size' => 0,
            // What went in with it, in the column a file shows its size in.
            'size_label' => ($pages > 0) ? lang(array('string' => '{var:1} page(s)', 'vars' => $pages)) : '',
            'design' => false,
            'optimized' => false,
            'image_width' => 0,
            'image_height' => 0,
            'description' => '',
            'access_control_type' => 'private',
            'access_icon' => pg_explorer_access_icon('private'),
            'archived' => true,
            'timestamp' => $timestamp,
            'modified' => get_relative_time(array('timestamp' => $timestamp)),
            'username' => (string) $row['user_username'],
            'permissions' => '',
            'url' => '',
            'can_edit' => true,
            'edit_url' => '');
    }

    return $items;
}
