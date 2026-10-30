<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's backup sub-actions over data/backups: the listing,
 * permissions, extract, upload, rename, copy, delete and compress. Each
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

// The breadcrumb of a path inside the backup directory: one crumb per folder,
// each carrying the path up to it. The backup root itself has none.
function pg_explorer_backup_breadcrumb($relative)
{
    $crumbs = array();
    $walk = '';

    if ($relative != '') {
        foreach (explode('/', $relative) as $segment) {
            $walk = ($walk == '') ? $segment : ($walk . '/' . $segment);
            $crumbs[] = array('name' => $segment, 'path' => $walk);
        }
    }

    return $crumbs;
}

// The segments an archive entry is written under, empty and "." segments left
// out. A "../" entry is the classic zip-slip; the whole entry is dropped
// (an empty array) rather than repaired, so nothing lands in a place the
// archive was not entitled to name.
function pg_explorer_backup_entry_segments($entry)
{
    $segments = array();

    foreach (explode('/', str_replace('\\', '/', $entry)) as $segment) {

        if (($segment == '') || ($segment == '.')) {
            continue;
        }

        if ($segment == '..') {
            return array();
        }

        $segments[] = $segment;
    }

    return $segments;
}

// ── Backups: a read-only browser over data/backups ──────────────
//
// Not part of the site's folder tree: these are files on disk with no
// database records, outside the web root, and nothing here writes to
// them. Manager area, the same gate backups.php uses.
function pg_explorer_handle_backups_list($type, $request, $user)
{
    if ($user['role'] > 2) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $relative = isset($request['path']) ? (string) $request['path'] : '';
    $root = pg_explorer_backup_root();
    $path = pg_explorer_backup_path($relative);

    if (($root == '') || ($path == '')) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the backup folder could not be found.')));
    }

    if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
        $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
    }

    $view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

    if (in_array($view_type, array('grid', 'list'), true) == false) {
        $view_type = 'grid';
    }

    $relative = trim(str_replace('\\', '/', substr($path, strlen($root))), '/');

    // The entries are dressed as ordinary folders and files so the
    // manager can draw, sort, search and select them with the code it
    // already has. They carry a path instead of a database id, and
    // every action on them is addressed by that path.
    $folders = array();
    $files = array();
    $names = @scandir($path);
    $index = 0;

    if ($names) {
        foreach ($names as $name) {

            // Dotfiles here are the directory's own protection
            // (.htaccess and friends), not backups.
            if (mb_substr($name, 0, 1) == '.') {
                continue;
            }

            $entry_path = $path . '/' . $name;
            $entry_relative = ($relative == '') ? $name : ($relative . '/' . $name);
            $timestamp = (int) @filemtime($entry_path);
            $index++;

            if (is_dir($entry_path)) {

                $folders[] = array(
                    'kind' => 'folder',
                    'id' => $index,
                    'backup' => true,
                    'path' => $entry_relative,
                    'name' => $name,
                    'own_access_control_type' => '',
                    'access_control_type' => 'private',
                    'access_icon' => pg_explorer_access_icon('private'),
                    'archived' => false,
                    'is_root' => false,
                    'parent_id' => 0,
                    'order' => 0,
                    'style_name' => '',
                    'counts' => array('folders' => 0, 'pages' => 0, 'files' => 0),
                    'empty' => false,
                    'timestamp' => $timestamp,
                    'modified' => get_relative_time(array('timestamp' => $timestamp)),
                    'username' => '',
                    'permissions' => substr(sprintf('%o', @fileperms($entry_path)), -4),
                    'can_edit' => true);

            } else {

                $size = (int) @filesize($entry_path);

                $files[] = array(
                    'kind' => 'file',
                    'id' => $index,
                    'backup' => true,
                    'path' => $entry_relative,
                    'name' => $name,
                    'folder_id' => 0,
                    'folder_name' => ($relative == '') ? lang('Backups') : basename($path),
                    'type' => mb_strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                    'is_image' => false,
                    'size' => $size,
                    'size_label' => convert_bytes_to_string($size),
                    'design' => false,
                    'optimized' => false,
                    'image_width' => 0,
                    'image_height' => 0,
                    'description' => '',
                    'access_control_type' => 'private',
                    'access_icon' => pg_explorer_access_icon('private'),
                    'archived' => false,
                    'timestamp' => $timestamp,
                    'modified' => get_relative_time(array('timestamp' => $timestamp)),
                    'username' => '',
                    'permissions' => substr(sprintf('%o', @fileperms($entry_path)), -4),
                    'url' => '',
                    'can_edit' => true,
                    'edit_url' => '');
            }
        }
    }

    $crumbs = pg_explorer_backup_breadcrumb($relative);

    $upload_limits = pg_upload_limits();

    respond(array(
        'status' => 'success',
        'request' => $type,
        'path' => $relative,
        'view_type' => $view_type,
        'breadcrumb' => $crumbs,
        'folders' => $folders,
        'files' => $files,
        'zip_available' => class_exists('ZipArchive'),
        'capabilities' => array(
            'role' => (int) $user['role'],
            'is_manager' => ($user['role'] <= 2),
            'is_designer' => ($user['role'] <= 1),
            'upload_max_bytes' => $upload_limits['json_max'],
            'show_product_images' => (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES,
            // So the upload window can refuse a file while it can still
            // be taken out of the list, rather than after it was sent.
            // The rule is the server's; this is a copy of its lists, not
            // a second rule written out again in JavaScript.
            'backup_blocked_names' => pg_explorer_blocked_upload_names(),
            'backup_blocked_extensions' => pg_explorer_blocked_upload_extensions(),
            'backup_allowed_names' => pg_explorer_backup_upload_allowed_names(),
            'upload_max_label' => convert_bytes_to_string($upload_limits['json_max'])),
        'total_label' => convert_bytes_to_string(folderSize($root), 2)));
}

// ── Change permissions on a backup entry ────────────────────────
//
// Backups are written by whichever process happened to make them -
// the web server on one host, a cron user on another - and an
// operator who cannot read or replace yesterday's backup has a
// broken backup. chmod is the fix, and it is only reachable here,
// for paths inside data/backups, by a manager.
function pg_explorer_handle_backup_chmod($type, $request, $user)
{
    if ($user['role'] > 2) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $root = pg_explorer_backup_root();
    $path = pg_explorer_backup_path(isset($request['path']) ? $request['path'] : '');

    if (($root == '') || ($path == '')) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the backup folder could not be found.')));
    }

    $folder_mode = trim(isset($request['folder_mode']) ? (string) $request['folder_mode'] : '');
    $file_mode = trim(isset($request['file_mode']) ? (string) $request['file_mode'] : '');
    $recursive = (isset($request['recursive']) && ($request['recursive'] == '1'));

    // Three octal digits, nothing else. The setuid, setgid and sticky
    // bits are not offered: none of them has a use on a backup, and
    // handing them out through a web screen is how a small
    // convenience becomes a privilege escalation.
    foreach (array($folder_mode, $file_mode) as $mode) {
        if (preg_match('/^[0-7]{3}$/', $mode) == 0) {
            respond(array('status' => 'error', 'message' => lang('Permissions must be three digits, for example 755.')));
        }
    }

    @set_time_limit(600);

    $attempted = 0;
    $changed = pg_explorer_backup_chmod_path($path, octdec($folder_mode), octdec($file_mode), $recursive, $attempted);

    if ($changed == 0) {
        respond(array('status' => 'error', 'message' => lang('The permissions could not be changed. The server may not allow it.')));
    }

    log_activity(lang(array('string' => 'permissions were changed for {var:1}', 'vars' => basename($path))), $_SESSION['sessionusername']);

    $message = lang(array('string' => 'Permissions were changed for {var:1} item(s).', 'vars' => $changed));

    // Partial success is worth saying out loud: the operator came
    // here to fix an access problem and half a fix is not one.
    if ($changed < $attempted) {
        $message .= ' ' . lang(array('string' => '{var:1} item(s) were left unchanged by the server.', 'vars' => ($attempted - $changed)));
    }

    respond(array(
        'status' => ($changed < $attempted) ? 'partial' : 'success',
        'request' => $type,
        'changed' => $changed,
        'attempted' => $attempted,
        'message' => $message));
}

// ── Extract an archive inside the backup directory ──────────────
//
// Nearly faithful: a backup that arrives as a zip has to come out
// whole or it cannot restore anything, so .php layouts are written.
// data/backups is kept from visitors by its own .htaccess and the
// root rewrite rule, and the entries that could undo that (a dotfile,
// web.config) or run outside layouts/ are the ones refused
// (pg_explorer_backup_entry_blocked()). Every entry must resolve
// inside the folder being extracted into, the archive's listed size
// must fit on the disk, and each file is streamed to disk rather
// than inflated into memory.
function pg_explorer_handle_backup_extract($type, $request, $user)
{
    if ($user['role'] > 2) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    if (class_exists('ZipArchive') == false) {
        respond(array('status' => 'error', 'message' => lang('This server cannot create zip archives (the zip extension is missing).')));
    }

    $root = pg_explorer_backup_root();
    $path = pg_explorer_backup_path(isset($request['path']) ? $request['path'] : '');

    if (($root == '') || ($path == '') || (is_file($path) == false)) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the backup folder could not be found.')));
    }

    if (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) != 'zip') {
        respond(array('status' => 'error', 'message' => lang('Sorry, that file is not a zip archive.')));
    }

    @set_time_limit(600);

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        respond(array('status' => 'error', 'message' => lang('The zip archive could not be opened.')));
    }

    $archive_size = pg_explorer_archive_size($zip);

    if ($archive_size === false) {
        $zip->close();
        respond(array('status' => 'error', 'message' => lang('The archive could not be opened. It might be damaged.')));
    }

    if (!pg_explorer_disk_has_room($archive_size[0])) {
        $zip->close();
        respond(array('status' => 'error', 'message' => lang('There is not enough free disk space to extract the archive.')));
    }

    // Its own folder, named after the archive, so extracting twice
    // does not merge two backups into one heap.
    $base = pg_file_name_stem($path);
    $destination = dirname($path) . '/' . $base;

    for ($number = 1; (is_dir($destination) || is_file($destination)) && ($number < 200); $number++) {
        $destination = dirname($path) . '/' . $base . '[' . $number . ']';
    }

    if (@mkdir($destination, 0755) == false) {
        $zip->close();
        respond(array('status' => 'error', 'message' => lang('The file could not be written to the file system.')));
    }

    $destination_real = realpath($destination);
    $extracted = 0;
    $skipped = 0;

    for ($index = 0; $index < $zip->numFiles; $index++) {

        $entry = $zip->getNameIndex($index);

        if ($entry === false) {
            continue;
        }

        $entry = str_replace('\\', '/', $entry);
        $segments = pg_explorer_backup_entry_segments($entry);

        if (count($segments) == 0) {
            $skipped++;
            continue;
        }

        $is_directory = (substr($entry, -1) == '/');

        if (!$is_directory && pg_explorer_backup_entry_blocked($segments)) {
            $skipped++;
            continue;
        }

        $target = $destination_real . '/' . implode('/', $segments);
        $directory = $is_directory ? $target : dirname($target);

        if ((is_dir($directory) == false) && (@mkdir($directory, 0755, true) == false)) {
            $skipped++;
            continue;
        }

        // Belt and braces: the resolved directory is checked against
        // the destination even after the segment filter above.
        $directory_real = realpath($directory);

        if (($directory_real === false) || (strpos($directory_real, $destination_real) !== 0)) {
            $skipped++;
            continue;
        }

        if ($is_directory) {
            continue;
        }

        // Streamed and checksummed, the way the restore reads a backup.
        if (pg_restore_archive_copy($zip, $index, $target) !== '') {
            $skipped++;
            continue;
        }

        $extracted++;
    }

    $zip->close();

    if ($extracted == 0) {
        @rmdir($destination_real);
    }

    log_activity((($extracted == 1) ? lang(array('string' => '{var:1} file was extracted', 'vars' => 1)) : lang(array('string' => '{var:1} files were extracted', 'vars' => $extracted))), $_SESSION['sessionusername']);

    respond(array(
        'status' => ($extracted > 0) ? 'success' : 'error',
        'request' => $type,
        'extracted' => $extracted,
        'skipped' => $skipped,
        'name' => basename($destination_real),
        'message' => ($extracted > 0)
            ? (($extracted == 1) ? lang(array('string' => '{var:1} file was extracted', 'vars' => 1)) : lang(array('string' => '{var:1} files were extracted', 'vars' => $extracted)))
            : lang('The zip archive could not be opened.')));
}

// ── Upload into the backup directory ────────────────────────────
//
// Dropped from the computer, one file per request, each carrying the
// path it had inside the dropped folder so the shape survives the
// trip. The only rule enforced here is the structural one - no
// segment may climb out of the backup directory - because a backup
// has to keep whatever it holds, .php layouts included.
function pg_explorer_handle_backup_upload($type, $request, $user)
{
    if ($user['role'] > 2) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $root = pg_explorer_backup_root();
    $path = pg_explorer_backup_path(isset($request['path']) ? $request['path'] : '');

    if (($root == '') || ($path == '') || (is_dir($path) == false)) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the backup folder could not be found.')));
    }

    $relative = trim(str_replace('\\', '/', (string) (isset($request['relative']) ? $request['relative'] : '')), '/');

    if ($relative == '') {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $segments = explode('/', $relative);
    $file_name = array_pop($segments);

    foreach (array_merge($segments, array($file_name)) as $segment) {
        if (($segment == '') || ($segment == '.') || ($segment == '..')) {
            respond(array('status' => 'error', 'message' => lang('Sorry, that name is not allowed here.')));
        }
    }

    // What may be written here is what an extraction may write, plus
    // .htaccess. Without this the drag-and-drop that fills this folder
    // was a way to put a .php file on disk inside the site.
    if (pg_explorer_backup_upload_blocked($file_name, $segments, realpath($path))) {
        respond(array('status' => 'error', 'message' => lang('Sorry, that name is not allowed here.')));
    }

    // Sub-folders are created as they are needed, each one re-checked
    // against the root so a crafted segment cannot walk out.
    $target = $path;

    foreach ($segments as $segment) {

        $target .= '/' . $segment;

        if ((is_dir($target) == false) && (@mkdir($target, 0755) == false)) {
            respond(array('status' => 'error', 'message' => lang('The file could not be written to the file system.')));
        }
    }

    $resolved = realpath($target);

    if (($resolved === false) || (strpos($resolved, $root) !== 0)) {
        respond(array('status' => 'error', 'message' => lang('Sorry, that name is not allowed here.')));
    }

    if ((isset($request['data']) == false) || ($request['data'] == '')) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $destination = $resolved . '/' . $file_name;

    // Decoded straight to disk in chunks. A backup archive is the largest thing
    // that goes through this screen, and holding it whole was what pushed the
    // request past memory_limit.
    if (pg_explorer_write_base64($request['data'], $destination) === false) {
        respond(array('status' => 'error', 'message' => lang('The file could not be written to the file system.')));
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'name' => $file_name,
        'message' => lang(array('string' => 'file, {var:1}, was uploaded', 'vars' => $file_name))));
}

// ── Rename, copy and delete inside the backup directory ─────────
//
// Real files on disk with no database record, so these three are
// filesystem operations rather than table writes. Every path goes
// through pg_explorer_backup_path(), which is what keeps them inside
// data/backups, and every new name goes through the same
// unsafe-name check an extraction uses - a backup directory is not a
// place to park a .php file either.
function pg_explorer_handle_backup_rename_copy_delete($type, $request, $user)
{
    if ($user['role'] > 2) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $root = pg_explorer_backup_root();
    $path = pg_explorer_backup_path(isset($request['path']) ? $request['path'] : '');

    if (($root == '') || ($path == '') || ($path == $root)) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the backup folder could not be found.')));
    }

    @set_time_limit(600);

    $parent = dirname($path);

    if ($type == 'explorer_backup_rename') {

        $name = trim(isset($request['name']) ? (string) $request['name'] : '');
        $name = pg_basename(str_replace('\\', '/', $name));

        if (($name == '') || (mb_substr($name, 0, 1) == '.') || pg_explorer_unsafe_archive_name($name)) {
            respond(array('status' => 'error', 'message' => lang('Sorry, that name is not allowed here.')));
        }

        if ($name == basename($path)) {
            respond(array('status' => 'success', 'request' => $type, 'name' => $name, 'message' => lang('The name was changed.')));
        }

        if (file_exists($parent . '/' . $name)) {
            respond(array('status' => 'error', 'message' => lang(array('string' => '{var:1} already exists. Please choose a different file name.', 'vars' => array($name)))));
        }

        if (@rename($path, $parent . '/' . $name) == false) {
            respond(array('status' => 'error', 'message' => lang('The file could not be renamed on the file system. Check write permission for the file directory.')));
        }

        log_activity(lang(array('string' => '{var:1} was renamed to {var:2}', 'vars' => array(basename($path), $name))), $_SESSION['sessionusername']);

        respond(array('status' => 'success', 'request' => $type, 'name' => $name, 'message' => lang('The name was changed.')));
    }

    if ($type == 'explorer_backup_copy') {

        $base = basename($path);
        $extension = is_dir($path) ? '' : pathinfo($base, PATHINFO_EXTENSION);
        $stem = ($extension == '') ? $base : mb_substr($base, 0, mb_strlen($base) - mb_strlen($extension) - 1);
        $copy_name = '';

        for ($number = 1; $number < 200; $number++) {
            $candidate = $stem . '[' . $number . ']' . (($extension == '') ? '' : ('.' . $extension));

            if (file_exists($parent . '/' . $candidate) == false) {
                $copy_name = $candidate;
                break;
            }
        }

        if ($copy_name == '') {
            respond(array('status' => 'error', 'message' => lang(array('string' => 'The file could not be copied ({var:1}).', 'vars' => basename($path)))));
        }

        if (pg_explorer_backup_copy_path($path, $parent . '/' . $copy_name) == false) {
            respond(array('status' => 'error', 'message' => lang(array('string' => 'The file could not be copied ({var:1}).', 'vars' => basename($path)))));
        }

        log_activity(lang(array('string' => '{var:1} ({var:2}) was duplicated', 'vars' => array(lang('file'), basename($path)))), $_SESSION['sessionusername']);

        respond(array('status' => 'success', 'request' => $type, 'name' => $copy_name, 'message' => lang('The name was changed.')));
    }

    // Delete. There is no recycle bin out here - the bin is a folder
    // inside the site, and these files are not in the site - so this
    // is permanent, and the screen asks before calling it.
    if (pg_explorer_backup_delete_path($path) == false) {
        respond(array('status' => 'error', 'message' => lang('Some items could not be deleted.')));
    }

    log_activity(lang(array('string' => '{var:1} item(s) were permanently deleted', 'vars' => 1)), $_SESSION['sessionusername']);

    respond(array('status' => 'success', 'request' => $type, 'message' => lang(array('string' => '{var:1} item(s) were permanently deleted', 'vars' => 1))));
}

// ── Compress a backup folder, ready to download ─────────────────
function pg_explorer_handle_backup_zip($type, $request, $user)
{
    if ($user['role'] > 2) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    if (class_exists('ZipArchive') == false) {
        respond(array('status' => 'error', 'message' => lang('This server cannot create zip archives (the zip extension is missing).')));
    }

    $root = pg_explorer_backup_root();
    $path = pg_explorer_backup_path(isset($request['path']) ? $request['path'] : '');

    if (($root == '') || ($path == '') || ($path == $root) || (is_dir($path) == false)) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the backup folder could not be found.')));
    }

    @set_time_limit(600);
    @ini_set('memory_limit', '-1');

    // The archive is written beside the folder it describes, which is
    // where backups.php has always put it - so a zip made there and a
    // zip made here are the same file, not two copies.
    $zip_path = $path . '.zip';

    $zip = new ZipArchive();

    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        respond(array('status' => 'error', 'message' => lang('The zip archive could not be created.')));
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST);

    $added = 0;

    foreach ($iterator as $item) {

        $item_path = $item->getRealPath();

        if (($item_path === false) || (strpos($item_path, $path) !== 0)) {
            continue;
        }

        $relative_name = ltrim(str_replace('\\', '/', substr($item_path, strlen($path))), '/');

        if ($relative_name == '') {
            continue;
        }

        if ($item->isDir()) {
            $zip->addEmptyDir($relative_name);
        } elseif ($item->isFile() && is_readable($item_path)) {
            $zip->addFile($item_path, $relative_name);
            $added++;
        }
    }

    $zip->close();

    if ((file_exists($zip_path) == false) || (filesize($zip_path) == 0)) {
        respond(array('status' => 'error', 'message' => lang('The zip archive could not be created.')));
    }

    log_activity(lang(array('string' => '{var:1} was compressed', 'vars' => basename($path))), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'name' => basename($zip_path),
        'path' => trim(str_replace('\\', '/', substr($zip_path, strlen($root))), '/'),
        'size_label' => convert_bytes_to_string(filesize($zip_path)),
        'files' => $added,
        'message' => (($added == 1) ? lang(array('string' => '{var:1} file was compressed', 'vars' => 1)) : lang(array('string' => '{var:1} files were compressed', 'vars' => $added)))));
}
