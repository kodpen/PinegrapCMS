<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's zip sub-actions: compress a selection into a zip file
 * in its folder, and extract a zip file into a new folder beside it. Each
 * handler answers through respond(), which ends the request. Called by
 * pg_explorer_handle() and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The name a new archive is given: the one asked for, trimmed, "archive" when
// none was, with ".zip" added unless it already ends in it (in any case).
function pg_explorer_zip_archive_name($suggested)
{
    $suggested = trim((string) $suggested);

    if ($suggested == '') {
        $suggested = lang('archive');
    }

    if (mb_strtolower(pathinfo($suggested, PATHINFO_EXTENSION)) != 'zip') {
        $suggested .= '.zip';
    }

    return $suggested;
}

// The file name an archive entry is extracted under: its last segment, either
// separator. A folder entry (one ending in "/") gives an empty name.
function pg_explorer_zip_entry_file_name($entry)
{
    if (substr($entry, -1) == '/') {
        return '';
    }

    return basename(str_replace('\\', '/', $entry));
}

// ── Compress a selection into a zip file in this folder ─────────
function pg_explorer_handle_zip_create($type, $request, $user)
{
    if (class_exists('ZipArchive') == false) {
        respond(array('status' => 'error', 'message' => lang('This server cannot create zip archives (the zip extension is missing).')));
    }

    $target_id = (int) (isset($request['target_folder_id']) ? $request['target_folder_id'] : 0);
    $items = isset($request['items']) ? $request['items'] : array();

    if (($target_id <= 0) || (is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    if ((check_edit_access($target_id) == false) || pg_recycle_is_inside($target_id)) {
        respond(array('status' => 'error', 'message' => lang('You do not have access to modify this folder.')));
    }

    @set_time_limit(600);
    @ini_set('memory_limit', '-1');

    // The archive is an ordinary file in the folder, so it downloads,
    // moves and deletes like everything else the manager holds.
    $suggested = pg_explorer_zip_archive_name(isset($request['name']) ? (string) $request['name'] : '');

    $zip_name = get_unique_name(array('name' => prepare_file_name($suggested), 'type' => 'file'));
    $zip_path = FILE_DIRECTORY_PATH . '/' . $zip_name;

    $zip = new ZipArchive();

    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        respond(array('status' => 'error', 'message' => lang('The zip archive could not be created.')));
    }

    $added = 0;
    $skipped_pages = 0;
    $errors = array();

    foreach ($items as $item) {

        $item_kind = isset($item['kind']) ? $item['kind'] : '';
        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        if ($item_kind == 'file') {

            $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($item_id) . "'");

            if (!$file) {
                continue;
            }

            if (
                (check_edit_access($file['folder']) == false)
                || (($file['design'] == 1) && ($user['role'] > 1))
                || pg_file_withheld($file['name'])
            ) {
                $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                continue;
            }

            $source = FILE_DIRECTORY_PATH . '/' . $file['name'];

            if (is_file($source) && is_readable($source)) {
                $zip->addFile($source, $file['name']);
                $added++;
            } else {
                $errors[] = lang(array('string' => 'The file does not exist on the file system ({var:1}).', 'vars' => $file['name']));
            }

        } elseif ($item_kind == 'folder') {

            $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($item_id) . "'");

            if (!$folder) {
                continue;
            }

            if (check_edit_access($item_id) == false) {
                $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $folder['folder_name']));
                continue;
            }

            $added += pg_explorer_zip_add_folder($zip, $item_id, pg_ascii_file_name($folder['folder_name']) . '/', $user, $skipped_pages);

        } elseif ($item_kind == 'page') {
            $skipped_pages++;
        }
    }

    $zip->close();

    if (($added == 0) || (file_exists($zip_path) == false)) {
        @unlink($zip_path);
        respond(array(
            'status' => 'error',
            'message' => (count($errors) > 0) ? implode(' ', $errors) : lang('There was nothing to compress.')));
    }

    db(
        "INSERT INTO files (name, folder, description, type, size, design, optimized, user, timestamp)
        VALUES (
            '" . e($zip_name) . "',
            '" . e($target_id) . "',
            '',
            'zip',
            '" . e(filesize($zip_path)) . "',
            '0',
            '0',
            '" . e($user['id']) . "',
            UNIX_TIMESTAMP())");

    $new_file_id = (int) mysqli_insert_id(db::$con);

    pg_audit_record('create', 'files', $new_file_id);

    log_activity(lang(array('string' => 'file ({var:1}) was created', 'vars' => $zip_name)), $_SESSION['sessionusername']);

    $message = lang(array('string' => '{var:1} files were compressed', 'vars' => $added));

    if ($skipped_pages > 0) {
        $message .= ' ' . lang(array('string' => '{var:1} page(s) were left out: a page is a record, not a file.', 'vars' => $skipped_pages));
    }

    respond(array(
        'status' => (count($errors) == 0) ? 'success' : 'partial',
        'request' => $type,
        'created' => array(array('kind' => 'file', 'id' => $new_file_id)),
        'name' => $zip_name,
        'errors' => $errors,
        'message' => (count($errors) > 0) ? (implode(' ', $errors) . ' ' . $message) : $message));
}

// ── Extract a zip file into this folder ─────────────────────────
function pg_explorer_handle_zip_extract($type, $request, $user)
{
    if (class_exists('ZipArchive') == false) {
        respond(array('status' => 'error', 'message' => lang('This server cannot create zip archives (the zip extension is missing).')));
    }

    $file_id = (int) (isset($request['item_id']) ? $request['item_id'] : 0);

    $file = db_item("SELECT id, name, folder, type, design FROM files WHERE id = '" . e($file_id) . "'");

    if (!$file) {
        respond(array('status' => 'error', 'message' => lang('Sorry, we could not find that file.')));
    }

    if (mb_strtolower($file['type']) != 'zip') {
        respond(array('status' => 'error', 'message' => lang('Sorry, that file is not a zip archive.')));
    }

    $target_id = (int) $file['folder'];

    if ((check_edit_access($target_id) == false) || pg_recycle_is_inside($target_id)) {
        respond(array('status' => 'error', 'message' => lang('You do not have access to modify this folder.')));
    }

    $archive_path = FILE_DIRECTORY_PATH . '/' . $file['name'];

    if (is_file($archive_path) == false) {
        respond(array('status' => 'error', 'message' => lang(array('string' => 'The file does not exist on the file system ({var:1}).', 'vars' => $file['name']))));
    }

    @set_time_limit(600);

    $zip = new ZipArchive();

    if ($zip->open($archive_path) !== true) {
        respond(array('status' => 'error', 'message' => lang('The zip archive could not be opened.')));
    }

    // The listing is checked before anything is inflated: a zip bomb
    // declares its size in its directory, and this screen is open to
    // a role-3 user with edit rights on a folder. The archive may
    // hold at most 10,000 entries and 2 GB uncompressed, and that
    // room has to be free on the disk; each file is then streamed to
    // disk rather than read into memory.
    $archive_size = pg_explorer_archive_size($zip);

    if ($archive_size === false) {
        $zip->close();
        respond(array('status' => 'error', 'message' => lang('The archive could not be opened. It might be damaged.')));
    }

    if (($archive_size[1] > 10000) || ($archive_size[0] > 2147483648)) {
        $zip->close();
        respond(array('status' => 'error', 'message' => lang('Sorry, that archive is too large to extract here.')));
    }

    if (!pg_explorer_disk_has_room($archive_size[0])) {
        $zip->close();
        respond(array('status' => 'error', 'message' => lang('There is not enough free disk space to extract the archive.')));
    }

    // Everything lands in one new folder named after the archive, so
    // an archive with fifty loose files cannot bury the folder it was
    // extracted in.
    $base_name = pathinfo($file['name'], PATHINFO_FILENAME);
    $folder_name = get_unique_name(array('name' => ($base_name != '') ? $base_name : lang('archive'), 'type' => 'folder'));
    $parent_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($target_id) . "'");

    db(
        "INSERT INTO folder (folder_name, folder_parent, folder_level, folder_order, folder_access_control_type, folder_archived, folder_style, mobile_style_id, folder_user, folder_timestamp)
        VALUES (
            '" . e($folder_name) . "',
            '" . e($target_id) . "',
            '" . e($parent_level + 1) . "',
            '0',
            NULL,
            '0',
            '0',
            '0',
            '" . e(USER_ID) . "',
            UNIX_TIMESTAMP())");

    $extract_folder_id = (int) mysqli_insert_id(db::$con);

    $extracted = 0;
    $blocked = array();

    for ($index = 0; $index < $zip->numFiles; $index++) {

        $entry = $zip->getNameIndex($index);

        if ($entry === false) {
            continue;
        }

        // Directories inside the archive are flattened: the manager's
        // folder names are unique platform-wide, so mirroring a deep
        // archive would rename every level anyway and the result
        // would not resemble the archive either way.
        $entry_name = pg_explorer_zip_entry_file_name($entry);

        if ($entry_name == '') {
            continue;
        }

        if (pg_explorer_unsafe_archive_name($entry_name)) {
            $blocked[] = $entry_name;
            continue;
        }

        $new_name = get_unique_name(array('name' => prepare_file_name($entry_name), 'type' => 'file'));

        // prepare_file_name() cannot invent a safe name out of an
        // unsafe one, so the result is checked again rather than
        // trusted: "x.php" sanitises to "x.php".
        if (pg_explorer_unsafe_archive_name($new_name)) {
            $blocked[] = $entry_name;
            continue;
        }

        $new_path = FILE_DIRECTORY_PATH . '/' . $new_name;

        if (pg_restore_archive_copy($zip, $index, $new_path) !== '') {
            continue;
        }

        db(
            "INSERT INTO files (name, folder, description, type, size, design, optimized, user, timestamp)
            VALUES (
                '" . e($new_name) . "',
                '" . e($extract_folder_id) . "',
                '',
                '" . e(mb_strtolower(pathinfo($new_name, PATHINFO_EXTENSION))) . "',
                '" . e(filesize($new_path)) . "',
                '0',
                '0',
                '" . e($user['id']) . "',
                UNIX_TIMESTAMP())");

        pg_audit_record('create', 'files', (int) mysqli_insert_id(db::$con));

        $extracted++;
    }

    $zip->close();

    // An archive that turned out to hold nothing usable leaves no
    // empty folder behind.
    if ($extracted == 0) {
        db("DELETE FROM folder WHERE folder_id = '" . e($extract_folder_id) . "'");
    }

    $message = lang(array('string' => '{var:1} files were extracted', 'vars' => $extracted));

    if (count($blocked) > 0) {
        $message .= ' ' . lang(array('string' => '{var:1} file(s) were not extracted because their type is not allowed on a web site.', 'vars' => count($blocked)));
        log_activity(lang(array('string' => '{var:1} file(s) were blocked while extracting {var:2}', 'vars' => array(count($blocked), $file['name']))), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => ($extracted > 0) ? 'success' : 'error',
        'request' => $type,
        'extracted' => $extracted,
        'blocked' => array_slice($blocked, 0, 20),
        'created' => ($extracted > 0) ? array(array('kind' => 'folder', 'id' => $extract_folder_id)) : array(),
        'message' => ($extracted > 0) ? $message : lang('There was nothing in this archive that can be stored on a web site.')));
}
