<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Archives: the backup browser's paths, copies, permissions and deletes, the
 * names an extraction or a hand-placed backup file may not carry, the room an
 * archive needs, and zip creation from a folder. Loaded by
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

// data/backups, beside data/files. Returned as a realpath so every path
// check below compares resolved paths and a symlink cannot point out.
function pg_explorer_backup_root()
{
    $path = realpath(dirname(FILE_DIRECTORY_PATH) . '/backups');

    return ($path === false) ? '' : $path;
}

// Resolve a relative path inside the backup directory, or '' when it points
// anywhere else. Everything the browser accepts comes through here: the one
// rule is that the resolved path still starts with the resolved root.
function pg_explorer_backup_path($relative)
{
    $root = pg_explorer_backup_root();

    if ($root == '') {
        return '';
    }

    $relative = str_replace('\\', '/', (string) $relative);
    $relative = trim($relative, '/');

    if ($relative == '') {
        return $root;
    }

    // A segment of ".." is refused outright rather than resolved, so a path
    // can never climb out even on a system where realpath() is permissive.
    foreach (explode('/', $relative) as $segment) {
        if (($segment == '..') || ($segment == '.') || ($segment == '')) {
            return '';
        }
    }

    $path = realpath($root . '/' . $relative);

    if (($path === false) || (strpos($path, $root) !== 0)) {
        return '';
    }

    return $path;
}

// Copy a file, or a directory with everything in it, on disk.
function pg_explorer_backup_copy_path($source, $destination)
{
    if (is_dir($source) == false) {
        return @copy($source, $destination);
    }

    if ((is_dir($destination) == false) && (@mkdir($destination, 0755, true) == false)) {
        return false;
    }

    $names = @scandir($source);

    if ($names === false) {
        return false;
    }

    foreach ($names as $name) {

        if (($name == '.') || ($name == '..')) {
            continue;
        }

        if (pg_explorer_backup_copy_path($source . '/' . $name, $destination . '/' . $name) == false) {
            return false;
        }
    }

    return true;
}

// Apply permissions to a path, and to everything under it when asked.
// Directories and files get their own mode: a directory needs its execute
// bit to be enterable at all, and a file almost never wants one.
//
// The result is read back rather than trusted. On several shared hosts
// chmod() returns true and changes nothing - the mount ignores it, or the
// files belong to another user - and reporting "changed" there would leave
// the operator believing a permission problem was fixed when it was not.
// $applied counts what actually moved; $attempted counts what was tried.
function pg_explorer_backup_chmod_path($path, $folder_mode, $file_mode, $recursive, &$attempted)
{
    $changed = 0;

    if (is_dir($path)) {

        $attempted++;

        if (pg_explorer_backup_chmod_one($path, $folder_mode)) {
            $changed++;
        }

        if ($recursive) {

            $names = @scandir($path);

            if ($names) {
                foreach ($names as $name) {

                    if (($name == '.') || ($name == '..')) {
                        continue;
                    }

                    $changed += pg_explorer_backup_chmod_path($path . '/' . $name, $folder_mode, $file_mode, true, $attempted);
                }
            }
        }

        return $changed;
    }

    $attempted++;

    if (pg_explorer_backup_chmod_one($path, $file_mode)) {
        $changed++;
    }

    return $changed;
}

// One chmod, confirmed against the file afterwards.
function pg_explorer_backup_chmod_one($path, $mode)
{
    $before = @fileperms($path);

    if (@chmod($path, $mode) == false) {
        return false;
    }

    @clearstatcache(true, $path);

    $after = @fileperms($path);

    if ($after === false) {
        return false;
    }

    // Equal to what was asked for is a success; merely different from what it
    // was is one too, since some filesystems round a mode off.
    return ((($after & 0777) == ($mode & 0777)) || (($after & 0777) != ($before & 0777)));
}

// Remove a file, or a directory with everything in it, from disk.
//
// A directory has to be writable for its entries to be removed, and backups
// are routinely written by another process with a restrictive mask - so a
// write bit is asked for first. Where the server allows the chmod this turns
// a failure into a success; where it does not, nothing is worse than before.
function pg_explorer_backup_delete_path($path)
{
    if (is_dir($path) == false) {
        return @unlink($path);
    }

    @chmod($path, 0755);
    @clearstatcache(true, $path);

    $names = @scandir($path);

    if ($names === false) {
        return false;
    }

    foreach ($names as $name) {

        if (($name == '.') || ($name == '..')) {
            continue;
        }

        if (pg_explorer_backup_delete_path($path . '/' . $name) == false) {
            return false;
        }
    }

    return @rmdir($path);
}

// The names and extensions an upload may never carry into a place the web
// server can reach. The rule itself is the platform's -- pg_blocked_upload_*()
// in functions.php, the same one every upload door applies -- and these two
// only add what an extraction or a hand-placed backup file must also never
// be: dkim.key, the key that signs the site's mail, which a manager may
// upload on purpose through the file screens but a stranger's archive may
// not smuggle in. Three things read them: what an extraction may write, what
// may be put into the backup folder by hand, and the copy the file manager
// is given so it can say no before sending rather than after.
function pg_explorer_blocked_upload_names()
{
    return array_merge(pg_blocked_upload_names(), array('dkim.key'));
}

function pg_explorer_blocked_upload_extensions()
{
    return pg_blocked_upload_extensions();
}

// What a manager may place in data/backups by hand.
//
// The rule an extraction follows, with one exception: .htaccess. That file is
// the backup folder's own protection -- it is what keeps the archives
// unreachable from the web -- and an operator who has lost it, or who is
// setting the folder up on a new host, has nowhere else to put it back from.
// Everything else the rule refuses is still refused, and only a manager
// reaches this screen at all.
function pg_explorer_backup_upload_allowed_names()
{
    return array('.htaccess');
}

// $segments is the path the upload carries inside the backup folder, $root
// the resolved backup folder. The .htaccess exception is for the file that
// protects the folder: it is accepted at the folder's root only, and only
// while there is none - a second one, or one in a sub-folder, would override
// the protection (a child .htaccess resets the inherited deny and rewrite
// rules on Apache) rather than restore it.
function pg_explorer_backup_upload_blocked($name, $segments = array(), $root = '')
{
    $base = mb_strtolower(pg_basename(trim(str_replace('\\', '/', (string) $name))));

    if (in_array($base, pg_explorer_backup_upload_allowed_names(), true)) {
        return (count($segments) > 0) || ($root === '') || is_file($root . '/' . $base);
    }

    return pg_explorer_unsafe_archive_name($base);
}

// Whether an entry of a backup archive may be written where it says.
//
// A backup comes out whole - sql.sql, files/ and layouts/ - and a layout is
// PHP, so the web-root rule cannot apply as it is. What is refused is what
// changes how the folder is served or what would run outside layouts/: a
// dotfile or web.config anywhere (the folder's own .htaccess is what keeps
// the archives unreachable), and an executable name outside layouts/.
function pg_explorer_backup_entry_blocked($segments)
{
    $base = mb_strtolower((string) end($segments));

    if (($base === '') || (mb_substr($base, 0, 1) === '.') || in_array($base, pg_explorer_blocked_upload_names(), true)) {
        return true;
    }

    // layouts/ at the root of the archive, or under the one wrapper folder a
    // hand-made archive may carry (the restore accepts both shapes).
    $folders = array_map('mb_strtolower', array_slice($segments, 0, -1));

    if (in_array('layouts', array_slice($folders, 0, 2), true)) {
        return false;
    }

    return pg_explorer_unsafe_archive_name($base);
}

// The room an archive needs on disk, from its directory: every entry's
// uncompressed size, so a bomb is refused from its listing before a byte of
// it is inflated. Answers array(bytes, entries) or false when a size is not
// known (a damaged directory is not extracted at all).
function pg_explorer_archive_size($zip)
{
    $total = 0.0;

    for ($index = 0; $index < $zip->numFiles; $index++) {

        $stat = $zip->statIndex($index);

        if (($stat === false) || !isset($stat['size'])) {
            return false;
        }

        $total += (float) $stat['size'];
    }

    return array($total, (int) $zip->numFiles);
}

// pg_explorer_disk_has_room() is in includes/fn/files.php: the ZIP importers
// ask it too (pg_zip_import_open()).

// Names that must never be written into the web root by an extraction.
//
// A zip is a stranger's directory listing: whoever made it chose the names.
// Letting one write .php, .htaccess or web.config would turn "extract here"
// into "run my code on your server", so the check is a whitelist-shaped
// blacklist - extensions that execute or reconfigure, plus every dotfile,
// plus anything without a name in front of its extension.
function pg_explorer_unsafe_archive_name($name)
{
    $name = trim(str_replace('\\', '/', (string) $name));
    $base = pg_basename($name);

    if (($base == '') || (mb_substr($base, 0, 1) == '.')) {
        return true;
    }

    if (in_array(mb_strtolower($base), pg_explorer_blocked_upload_names(), true)) {
        return true;
    }

    $blocked_extensions = pg_explorer_blocked_upload_extensions();
    $extension = mb_strtolower(pathinfo($base, PATHINFO_EXTENSION));

    if (in_array($extension, $blocked_extensions, true)) {
        return true;
    }

    // "shell.php.jpg" is served as PHP by a misconfigured Apache, and the
    // last extension is not the whole story, so every extension in the name
    // has to pass, not only the final one.
    $parts = explode('.', mb_strtolower($base));
    array_shift($parts);

    foreach ($parts as $part) {
        if (in_array($part, $blocked_extensions, true)) {
            return true;
        }
    }

    return false;
}

// Add a manager folder's files to an open archive, recursing into
// sub-folders. Pages are database records with no file of their own, so they
// cannot go in - the caller reports how many were left out.
function pg_explorer_zip_add_folder($zip, $folder_id, $prefix, $user, &$skipped_pages, $depth = 0)
{
    if ($depth > 20) {
        return 0;
    }

    $added = 0;

    $files = db_items("SELECT id, name, design FROM files WHERE folder = '" . e($folder_id) . "' ORDER BY name");

    if ($files) {
        foreach ($files as $file) {

            if (($file['design'] == 1) && ($user['role'] > 1)) {
                continue;
            }

            // The archive is served like any other file, so the signing
            // key stays out of it.
            if (pg_file_withheld($file['name'])) {
                continue;
            }

            $path = FILE_DIRECTORY_PATH . '/' . $file['name'];

            if (is_file($path) && is_readable($path)) {
                $zip->addFile($path, $prefix . $file['name']);
                $added++;
            }
        }
    }

    $skipped_pages += (int) db_value("SELECT COUNT(*) FROM page WHERE page_folder = '" . e($folder_id) . "'");

    $children = db_items("SELECT folder_id, folder_name FROM folder WHERE folder_parent = '" . e($folder_id) . "' ORDER BY folder_order, folder_name");

    if ($children) {
        foreach ($children as $child) {

            if (pg_explorer_folder_visible($child['folder_id'], array()) == false) {
                continue;
            }

            if (check_edit_access($child['folder_id']) == false) {
                continue;
            }

            $added += pg_explorer_zip_add_folder(
                $zip,
                (int) $child['folder_id'],
                $prefix . pg_ascii_file_name($child['folder_name']) . '/',
                $user,
                $skipped_pages,
                $depth + 1);
        }
    }

    return $added;
}
