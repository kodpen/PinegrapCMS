<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// JSON sub-actions for the combined folder/page/file explorer
// (view_folder_and_files.php). Included on demand by the "file_explorer"
// action in api.php, which has already validated the user, the CSRF token
// and the "user" area before any function here runs.
//
// Unlike the older "get_tables" sub-action, everything here returns
// structured data and lets the client render it, so one endpoint serves the
// grid view, the list view, the tree sidebar and the breadcrumb at once.

// Return the id of the root folder (the single folder without a parent).
function pg_explorer_root_folder_id()
{
    static $root_id = null;

    if ($root_id === null) {
        $root_id = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    }

    return $root_id;
}

// Let go of the session before the long or read-only part of a request.
//
// PHP holds a user's session file exclusively from session_start() to the
// end of the script, so every request the same browser sends meanwhile --
// the folder listing after a click, the tree, the notification poll -- waits
// behind it. A move of a thousand files or a run of image recompressions is
// exactly the kind of request that makes that wait visible, and on a server
// with a gateway in front the queued listing can outlive the gateway's
// patience. get_file.php releases its lock the same way for the same reason.
//
// $_SESSION stays readable after session_write_close(); only writes stop.
// Every caller below has finished writing by then: the shared preamble in
// api.php has recorded the folder and view it was sent, the listing has
// stored the position it is about to show, and the handlers that follow only
// read who is asking.
function pg_explorer_release_session()
{
    if (function_exists('session_status') && (session_status() === PHP_SESSION_ACTIVE)) {
        session_write_close();
    }
}

// Load the whole folder table once per request. Every helper below walks
// parent chains or child lists, and one in-memory map replaces what would
// otherwise be a recursive query per row (same reasoning as the cache inside
// get_access_control_type()).
function pg_explorer_folder_map()
{
    static $map = null;

    if ($map === null) {
        $map = array();

        $rows = db_items(
            "SELECT
                folder_id,
                folder_name,
                folder_parent,
                folder_level,
                folder_order,
                folder_access_control_type,
                folder_archived,
                folder_style,
                mobile_style_id
            FROM folder");

        if ($rows) {
            foreach ($rows as $row) {
                $map[(int) $row['folder_id']] = $row;
            }
        }
    }

    return $map;
}

// True when the folder is visible to the current backend user. Managers and
// above see everything; basic users see only the subtrees they were granted
// edit rights to (the same rule every view_* screen applies).
function pg_explorer_folder_visible($folder_id, $folders_that_user_has_access_to)
{
    return check_folder_access_in_array($folder_id, $folders_that_user_has_access_to);
}

// Resolve the folder the client asked for to one this user may stand in.
// Falls back to the root folder (or, for a basic user, to the virtual root)
// when the request names a folder that does not exist or is off limits.
function pg_explorer_resolve_folder($requested_id, $user, $folders_that_user_has_access_to)
{
    $map = pg_explorer_folder_map();
    $requested_id = (int) $requested_id;

    if (($requested_id > 0) && isset($map[$requested_id]) && pg_explorer_folder_visible($requested_id, $folders_that_user_has_access_to)) {
        return $requested_id;
    }

    // A basic user without a grant on the root folder starts at the virtual
    // root (id 0), which lists the top of each granted subtree.
    if (($user['role'] == 3) && (pg_explorer_folder_visible(pg_explorer_root_folder_id(), $folders_that_user_has_access_to) == false)) {
        return 0;
    }

    return pg_explorer_root_folder_id();
}

// Breadcrumb from the root (or the top of the user's granted subtree) down
// to the given folder. Returns oldest first.
function pg_explorer_breadcrumb($folder_id, $folders_that_user_has_access_to)
{
    $map = pg_explorer_folder_map();
    $trail = array();
    $current = (int) $folder_id;
    $guard = array();

    while (($current > 0) && isset($map[$current]) && (isset($guard[$current]) == false)) {
        $guard[$current] = true;

        // Stop before folders the user may not see, so a basic user's
        // breadcrumb starts at the top of the granted subtree.
        if (pg_explorer_folder_visible($current, $folders_that_user_has_access_to) == false) {
            break;
        }

        array_unshift($trail, array(
            'id' => $current,
            'name' => $map[$current]['folder_name']));

        $current = (int) $map[$current]['folder_parent'];
    }

    return $trail;
}

// True when $candidate_id equals $folder_id or lives anywhere below it.
// Used to refuse moving a folder into itself or into its own subtree.
function pg_explorer_is_self_or_descendant($folder_id, $candidate_id)
{
    $map = pg_explorer_folder_map();
    $folder_id = (int) $folder_id;
    $current = (int) $candidate_id;
    $guard = array();

    while (($current > 0) && isset($map[$current]) && (isset($guard[$current]) == false)) {
        $guard[$current] = true;

        if ($current == $folder_id) {
            return true;
        }

        $current = (int) $map[$current]['folder_parent'];
    }

    return false;
}

// Recompute folder_level for every folder below $parent_id after a move.
// Same walk edit_folder.php does; kept local so the endpoint has no
// dependency on that screen being loaded.
function pg_explorer_change_level($parent_id, $parent_level)
{
    $rows = db_items("SELECT folder_id FROM folder WHERE folder_parent = '" . e($parent_id) . "'");

    if ($rows) {
        foreach ($rows as $row) {
            db("UPDATE folder SET folder_level = '" . e($parent_level + 1) . "' WHERE folder_id = '" . e($row['folder_id']) . "'");
            pg_explorer_change_level($row['folder_id'], $parent_level + 1);
        }
    }
}

// Map a resolved access control type to the small badge icon the client
// layers over the item icon (same glyphs the older get_tables view used).
// Deliberately no person or sharing glyphs here (a file sharing feature is
// planned), and no bi-gem (the software's premium marker) — access icons
// must not read as either.
// Whether a table row may carry a product picture.
//
// The grid is made of pictures by nature; a table row is a line of text with a
// mark at the front of it, and on a catalog of ten thousand a thumbnail per
// row is ten thousand requests for an image forty pixels wide.
//
// The switch for that already existed -- "Show Product Images in Tables" in
// the eCommerce settings, which view_products.php, add_product_group.php,
// edit_product_group.php and api.php have all read for years. This screen
// briefly carried a second one of its own, which is exactly the wrong number:
// two switches with the same label, each ignored by half the software. There
// is nothing to read here any more, the constant is read where it is sent.

function pg_explorer_access_icon($access_control_type)
{
    switch ($access_control_type) {
        case 'guest':
            return 'bi-door-open-fill';
        case 'registration':
            return 'bi-key-fill';
        case 'membership':
            return 'bi-ticket-perforated-fill';
        case 'private':
            return 'bi-lock-fill';
        case 'public':
        default:
            return 'bi-globe2';
    }
}

// One page row for the client.
// Style names by id, fetched once per request for the preview panel rows.
function pg_explorer_style_names()
{
    static $names = null;

    if ($names === null) {
        $names = array();

        $rows = db_items("SELECT style_id, style_name FROM style");

        if ($rows) {
            foreach ($rows as $row) {
                $names[(int) $row['style_id']] = (string) $row['style_name'];
            }
        }
    }

    return $names;
}

// The style a folder resolves to for one device — the walk get_style() does
// with queries, done against the in-memory folder map instead.
function pg_explorer_effective_style($folder_id, $device)
{
    $map = pg_explorer_folder_map();
    $column = ($device == 'mobile') ? 'mobile_style_id' : 'folder_style';
    $current = (int) $folder_id;
    $guard = array();

    while (($current > 0) && isset($map[$current]) && (isset($guard[$current]) == false)) {
        $guard[$current] = true;

        $style_id = (int) $map[$current][$column];

        if ($style_id > 0) {
            return $style_id;
        }

        $current = (int) $map[$current]['folder_parent'];
    }

    return 0;
}

// {name, inherited} for one record's style on one device: the record's own
// style when set, otherwise whatever the folder chain resolves to.
function pg_explorer_style_info($own_style_id, $inherit_folder_id, $device)
{
    $names = pg_explorer_style_names();
    $own_style_id = (int) $own_style_id;

    if ($own_style_id > 0) {
        return array(
            'name' => isset($names[$own_style_id]) ? $names[$own_style_id] : '',
            'inherited' => false);
    }

    $effective = pg_explorer_effective_style($inherit_folder_id, $device);

    return array(
        'name' => (($effective > 0) && isset($names[$effective])) ? $names[$effective] : '',
        'inherited' => true);
}

// SEO chip data for a page row: the score, the shared color band and the
// worst flag labels. Colors come from the seo.php helpers so this screen
// cannot drift from the bands every other screen draws.
function pg_explorer_seo_payload($row)
{
    require_once(dirname(__FILE__) . '/seo.php');

    if (pg_seo_row_scored($row) == false) {
        return array('scored' => false);
    }

    $score = (int) $row['seo_score'];
    $color = pg_seo_score_color($score);

    return array(
        'scored' => true,
        'score' => $score,
        'class' => (string) $color['class'],
        'style' => (string) $color['style'],
        'hex' => pg_seo_score_hex($score),
        'labels' => pg_seo_flag_labels((int) ($row['seo_flags'] ?? 0), 3));
}

// Copy a folder into another folder, with everything inside it.
//
// The platform's own duplicate_folder() copies one folder's pages and stops:
// no sub-folders, no files. That is enough for "make me another one of these"
// on the folder screen, but a file manager promising copy & paste has to
// deliver the whole subtree, so this walks it.
//
// What each kind costs is different, and that is why this is not one query:
// a folder is a row, a page is duplicate_page() (settings, regions, layout
// file), and a file is bytes on disk that have to be copied under a new name
// because files.name IS the address the front end serves it from.
//
// Names are unique platform-wide for folders and across the page/file web
// root, so every copy is renamed on the way in - "Products" becomes
// "Products[1]" even three levels down, the same rule the rest of the
// software applies.
//
// $depth stops a pathological tree (or a cycle introduced by hand-edited
// data) from recursing forever; the caller has already refused the one
// legitimate way to build one, pasting a folder into its own subtree.
function pg_explorer_copy_folder($source_id, $target_parent_id, $user, &$errors, &$created, $depth = 0)
{
    if ($depth > 20) {
        $errors[] = lang('Sorry, the folder structure is too deep to copy.');
        return 0;
    }

    $folder = db_item(
        "SELECT
            folder_id,
            folder_name,
            folder_parent,
            folder_style,
            mobile_style_id,
            folder_order,
            folder_access_control_type,
            folder_archived
        FROM folder
        WHERE folder_id = '" . e($source_id) . "'");

    if (!$folder) {
        $errors[] = lang('Sorry, the folder could not be found.');
        return 0;
    }

    if ((int) $folder['folder_parent'] == 0) {
        $errors[] = lang('Sorry, you may not duplicate the root folder.');
        return 0;
    }

    if (check_edit_access($source_id) == false) {
        $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $folder['folder_name']));
        return 0;
    }

    $parent_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($target_parent_id) . "'");
    $new_name = get_unique_name(array('name' => $folder['folder_name'], 'type' => 'folder'));

    db(
        "INSERT INTO folder (
            folder_name,
            folder_parent,
            folder_level,
            folder_style,
            mobile_style_id,
            folder_order,
            folder_access_control_type,
            folder_archived,
            folder_user,
            folder_timestamp)
        VALUES (
            '" . e($new_name) . "',
            '" . e($target_parent_id) . "',
            '" . e($parent_level + 1) . "',
            '" . e($folder['folder_style']) . "',
            '" . e($folder['mobile_style_id']) . "',
            '" . e($folder['folder_order']) . "',
            '" . e($folder['folder_access_control_type']) . "',
            '" . e($folder['folder_archived']) . "',
            '" . e(USER_ID) . "',
            UNIX_TIMESTAMP())");

    $new_folder_id = (int) mysqli_insert_id(db::$con);

    // Only the top-level copy is reported for undo: binning it takes the
    // whole subtree with it, which is exactly what undoing a paste means.
    if ($depth == 0) {
        $created[] = array('kind' => 'folder', 'id' => $new_folder_id);
    }

    log_activity(lang(array('string' => '{var:1} ({var:2}) was duplicated', 'vars' => array(lang('folder'), $folder['folder_name']))), $_SESSION['sessionusername']);

    $copied = 1;

    // Pages.
    $pages = db_items("SELECT page_id AS id FROM page WHERE page_folder = '" . e($source_id) . "'");

    if ($pages) {

        require_once(dirname(__FILE__) . '/duplicate_page_f.php');

        foreach ($pages as $page) {

            $response = duplicate_page(array(
                'page' => array('id' => (int) $page['id']),
                'folder' => array('id' => $new_folder_id, 'name' => $new_name),
                'find_replace_keywords' => ''));

            if (isset($response['status']) && ($response['status'] == 'success')) {
                $copied++;
            } else {
                $errors[] = isset($response['message']) ? $response['message'] : lang('Sorry, the page could not be found.');
            }
        }
    }

    // Files.
    $files = db_items(
        "SELECT id, name, description, type, design, optimized, image_width, image_height, optimization_percent
        FROM files
        WHERE folder = '" . e($source_id) . "'");

    if ($files) {
        foreach ($files as $file) {

            // Design files stay designer-only, the same rule the single file
            // copy applies; the rest of the folder still comes across.
            if (($file['design'] == 1) && ($user['role'] > 1)) {
                $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                continue;
            }

            $source_path = FILE_DIRECTORY_PATH . '/' . $file['name'];

            if (file_exists($source_path) == false) {
                $errors[] = lang(array('string' => 'The file does not exist on the file system ({var:1}).', 'vars' => $file['name']));
                continue;
            }

            $new_file_name = get_unique_name(array('name' => prepare_file_name($file['name']), 'type' => 'file'));
            $new_path = FILE_DIRECTORY_PATH . '/' . $new_file_name;

            if (@copy($source_path, $new_path) == false) {
                $errors[] = lang(array('string' => 'The file could not be copied ({var:1}).', 'vars' => $file['name']));
                continue;
            }

            db(
                "INSERT INTO files (
                    name,
                    folder,
                    description,
                    type,
                    size,
                    design,
                    optimized,
                    image_width,
                    image_height,
                    optimization_percent,
                    user,
                    timestamp)
                VALUES (
                    '" . e($new_file_name) . "',
                    '" . e($new_folder_id) . "',
                    '" . e($file['description']) . "',
                    '" . e(mb_strtolower(pathinfo($new_file_name, PATHINFO_EXTENSION))) . "',
                    '" . e(filesize($new_path)) . "',
                    '" . e($file['design']) . "',
                    '" . e($file['optimized']) . "',
                    " . (($file['image_width'] !== null && $file['image_width'] !== '') ? "'" . e($file['image_width']) . "'" : "NULL") . ",
                    " . (($file['image_height'] !== null && $file['image_height'] !== '') ? "'" . e($file['image_height']) . "'" : "NULL") . ",
                    " . (($file['optimization_percent'] !== null && $file['optimization_percent'] !== '') ? "'" . e($file['optimization_percent']) . "'" : "NULL") . ",
                    '" . e($user['id']) . "',
                    UNIX_TIMESTAMP())");

            $copied++;
        }
    }

    // Sub-folders, bottom of the recursion.
    $children = db_items("SELECT folder_id FROM folder WHERE folder_parent = '" . e($source_id) . "' ORDER BY folder_order, folder_name");

    if ($children) {
        foreach ($children as $child) {
            $copied += pg_explorer_copy_folder((int) $child['folder_id'], $new_folder_id, $user, $errors, $created, $depth + 1);
        }
    }

    return $copied;
}

// ── Archives: the backup browser, zip creation and zip extraction ───────

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

function pg_explorer_backup_upload_blocked($name)
{
    $base = mb_strtolower(basename(trim(str_replace('\\', '/', (string) $name))));

    if (in_array($base, pg_explorer_backup_upload_allowed_names(), true)) {
        return false;
    }

    return pg_explorer_unsafe_archive_name($base);
}

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
    $base = basename($name);

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

// A contact's display name, assembled the way view_users.php assembles it:
// the nickname when there is one, otherwise salutation, first, last, suffix.
function pg_explorer_contact_name($row)
{
    if ((string) $row['contact_nickname'] != '') {
        return (string) $row['contact_nickname'];
    }

    $parts = array();

    foreach (array('contact_salutation', 'contact_first_name', 'contact_last_name', 'contact_suffix') as $field) {
        if ((string) $row[$field] != '') {
            $parts[] = (string) $row[$field];
        }
    }

    return implode(' ', $parts);
}

// Backup entries: folders first, then newest first inside each group.
function pg_explorer_compare_backup_entries($a, $b)
{
    if ($a['is_dir'] != $b['is_dir']) {
        return $a['is_dir'] ? -1 : 1;
    }

    if ($a['timestamp'] == $b['timestamp']) {
        return strcasecmp($a['name'], $b['name']);
    }

    return ($a['timestamp'] > $b['timestamp']) ? -1 : 1;
}

// Shared groups sort by their path so a subtree reads together.
function pg_explorer_compare_shared_groups($a, $b)
{
    return strcasecmp($a['path'], $b['path']);
}

function pg_explorer_page_payload($page, $user)
{
    $access_control_type = get_access_control_type($page['page_folder']);
    $map = pg_explorer_folder_map();
    $page_folder_id = (int) $page['page_folder'];

    $url = PATH . $page['page_name'];

    // Some system page types error out without their reference data unless
    // they know the visit comes from the control panel.
    if (check_if_page_type_requires_from_control_panel($page['page_type'])) {
        $url .= '?from=control_panel';
    }

    return array(
        'kind' => 'page',
        'id' => (int) $page['page_id'],
        'name' => $page['page_name'],
        'folder_id' => $page_folder_id,
        'folder_name' => isset($map[$page_folder_id]) ? $map[$page_folder_id]['folder_name'] : '',
        'type' => $page['page_type'],
        'home' => ($page['page_home'] == 'yes'),
        'style_name' => ($page['page_style'] != '0') ? (string) $page['style_name'] : '',
        'style_desktop' => pg_explorer_style_info($page['page_style'], $page['page_folder'], 'desktop'),
        'style_mobile' => pg_explorer_style_info(isset($page['mobile_style_id']) ? $page['mobile_style_id'] : 0, $page['page_folder'], 'mobile'),
        'sitemap' => (isset($page['sitemap']) && ($page['sitemap'] == '1')),
        'searchable' => (isset($page['page_search']) && ($page['page_search'] == 1)),
        'comments' => (isset($page['comments']) && ($page['comments'] == '1')),
        'impact' => (isset($page['seo_impact']) && ($page['seo_impact'] !== null)) ? (int) $page['seo_impact'] : null,
        'views' => (int) (isset($page['seo_views']) ? $page['seo_views'] : 0),
        'seo' => pg_explorer_seo_payload($page),
        'access_control_type' => $access_control_type,
        'access_icon' => pg_explorer_access_icon($access_control_type),
        'archived' => ($page['folder_archived'] == '1'),
        'timestamp' => (int) $page['page_timestamp'],
        'modified' => get_relative_time(array('timestamp' => $page['page_timestamp'])),
        'username' => (string) $page['user_username'],
        'url' => $url,
        'edit_url' => PATH . SOFTWARE_DIRECTORY . '/' . pg_page_edit_url($page));
}

// One file row for the client.
// The narrowings of the Files view.
//
// view_files.php offered these from a select; the file manager offers them
// from a button on the same view. Pictures is not among them because it is a
// view of its own, one entry up. Documents is what is left once pictures and
// media are taken out, so the two lists below are the whole of the rule.
function pg_explorer_file_scopes()
{
    return array('documents', 'media', 'attachments', 'archived', 'public', 'guest', 'registration', 'membership', 'private');
}

function pg_explorer_image_types()
{
    return array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg');
}

function pg_explorer_media_types()
{
    return array(
        'mp4', 'm4v', 'webm', 'ogv', 'mov', 'avi', 'mkv', 'mpg', 'mpeg', 'wmv', 'flv', '3gp', 'swf', 'rm', 'ram',
        'mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'flac', 'wav', 'wma', 'aiff', 'au', 'mid', 'snd');
}

// What the file window opens in a text editor, and what it is allowed to
// write back.
//
// One list for both, for the reason edit_file.php gave: two lists is how a
// format ends up editable on screen and silently discarded on save. .key and
// .pub are shown but never written -- a signing key is not something to hand
// a text box for. Everything here is plain text a person can be trusted to
// edit by hand; the formats a web server would run are not among them.
function pg_explorer_editable_formats()
{
    return array(
        'txt', 'md', 'markdown', 'html', 'htm', 'xml', 'svg', 'json', 'webmanifest', 'css', 'scss', 'less', 'js',
        'csv', 'tsv', 'yml', 'yaml', 'log', 'vtt', 'srt', 'ics');
}

function pg_explorer_viewable_formats()
{
    return array_merge(pg_explorer_editable_formats(), array('key', 'pub'));
}

// The editor mode CodeMirror is told about, by extension.
function pg_explorer_editor_mode($extension)
{
    switch ($extension) {
        case 'css':
        case 'scss':
        case 'less':
            return 'text/css';
        case 'js':
            return 'javascript';
        case 'json':
        case 'webmanifest':
            return 'application/json';
        case 'svg':
        case 'xml':
            return 'application/xml';
        case 'html':
        case 'htm':
            return 'htmlmixed';
    }

    // Markdown, YAML and the rest: the bundle carries no mode for them, so
    // they are edited as plain text rather than named after a mode that
    // would quietly fall back to it anyway.
    return 'text/plain';
}

// The largest file the text editor is handed. Beyond it the window shows the
// file's details and says why the text is not there: a log or a data dump the
// size of a video is not something to edit in a browser text box, and loading
// one into CodeMirror freezes the tab.
function pg_explorer_editor_max_bytes()
{
    return 2 * 1024 * 1024;
}

// The row the file window is built from -- the listing's columns, so the same
// payload builder can dress it.
function pg_explorer_file_row($file_id)
{
    return db_item(
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
        WHERE files.id = '" . e((int) $file_id) . "'");
}

// Whether this operator may change this file: edit rights on its folder, and
// designer rank if it is a design file. The rule every file action applies.
function pg_explorer_file_editable_by($file, $user)
{
    if (!$file) {
        return false;
    }

    if (check_edit_access($file['folder']) == false) {
        return false;
    }

    if (($file['design'] == 1) && ($user['role'] > 1)) {
        return false;
    }

    return true;
}

function pg_explorer_file_scope_label($scope)
{
    switch ($scope) {
        case 'documents':   return lang('Documents');
        case 'media':       return lang('Media');
        case 'attachments': return lang('Attachments');
        case 'archived':    return lang('Archived files');
        case 'public':      return lang('Public');
        case 'guest':       return lang('Guest');
        case 'registration':return lang('Registration');
        case 'membership':  return lang('Membership');
        case 'private':     return lang('Private');
    }

    return lang('Files');
}

function pg_explorer_file_payload($file, $user)
{
    $access_control_type = get_access_control_type($file['folder']);
    $map = pg_explorer_folder_map();
    $folder_id = (int) $file['folder'];
    $type = mb_strtolower($file['type']);
    $image_types = pg_explorer_image_types();

    // Design files stay visible but locked for anyone below designer, the
    // same rule the folder tree and the files screen apply.
    $can_edit = (($file['design'] == 0) || ($user['role'] <= 1));

    return array(
        'kind' => 'file',
        'id' => (int) $file['id'],
        'name' => $file['name'],
        'folder_id' => $folder_id,
        'folder_name' => isset($map[$folder_id]) ? $map[$folder_id]['folder_name'] : '',
        'type' => $type,
        'is_image' => in_array($type, $image_types, true),
        'size' => (int) $file['size'],
        'size_label' => convert_bytes_to_string($file['size']),
        'design' => ($file['design'] == '1'),
        'optimized' => ($file['optimized'] == '1'),
        'image_width' => (int) $file['image_width'],
        'image_height' => (int) $file['image_height'],
        'description' => (string) $file['description'],
        'access_control_type' => $access_control_type,
        'access_icon' => pg_explorer_access_icon($access_control_type),
        'archived' => ($file['folder_archived'] == '1'),
        'timestamp' => (int) $file['timestamp'],
        'modified' => get_relative_time(array('timestamp' => $file['timestamp'])),
        'username' => (string) $file['user_username'],
        'url' => PATH . $file['name'],
        'can_edit' => $can_edit,
        'edit_url' => PATH . SOFTWARE_DIRECTORY . '/edit_file.php?id=' . (int) $file['id']);
}

// One folder row for the client, including child counts so the UI can show
// them and block deleting a non-empty folder before the server would.
function pg_explorer_folder_payload($folder, $counts, $user, $folders_that_user_has_access_to)
{
    $folder_id = (int) $folder['folder_id'];
    $access_control_type = get_access_control_type($folder_id);

    $count = isset($counts[$folder_id]) ? $counts[$folder_id] : array('folders' => 0, 'pages' => 0, 'files' => 0);

    return array(
        'kind' => 'folder',
        'id' => $folder_id,
        'name' => $folder['folder_name'],
        'own_access_control_type' => (string) $folder['folder_access_control_type'],
        'access_control_type' => $access_control_type,
        'access_icon' => pg_explorer_access_icon($access_control_type),
        'archived' => ($folder['folder_archived'] == '1'),
        'is_root' => ((int) $folder['folder_parent'] == 0),
        'parent_id' => (int) $folder['folder_parent'],
        'order' => (int) $folder['folder_order'],
        'style_name' => (isset($folder['style_name']) && ($folder['folder_style'] != '0')) ? (string) $folder['style_name'] : '',
        'style_desktop' => pg_explorer_style_info($folder['folder_style'], (int) $folder['folder_parent'], 'desktop'),
        'style_mobile' => pg_explorer_style_info(isset($folder['mobile_style_id']) ? $folder['mobile_style_id'] : 0, (int) $folder['folder_parent'], 'mobile'),
        'counts' => $count,
        'empty' => (($count['folders'] + $count['pages'] + $count['files']) == 0),
        'timestamp' => (int) $folder['folder_timestamp'],
        'modified' => ($folder['folder_timestamp'] > 0) ? get_relative_time(array('timestamp' => $folder['folder_timestamp'])) : '',
        'username' => (string) $folder['user_username'],
        'can_edit' => check_edit_access($folder_id));
}

// Child counts for every folder in three grouped queries instead of three
// queries per row.
function pg_explorer_folder_counts()
{
    $counts = array();

    $rows = db_items("SELECT folder_parent AS id, COUNT(*) AS total FROM folder WHERE folder_parent != '0' GROUP BY folder_parent");
    if ($rows) {
        foreach ($rows as $row) {
            $counts[(int) $row['id']]['folders'] = (int) $row['total'];
        }
    }

    $rows = db_items("SELECT page_folder AS id, COUNT(*) AS total FROM page GROUP BY page_folder");
    if ($rows) {
        foreach ($rows as $row) {
            $counts[(int) $row['id']]['pages'] = (int) $row['total'];
        }
    }

    $rows = db_items("SELECT folder AS id, COUNT(*) AS total FROM files GROUP BY folder");
    if ($rows) {
        foreach ($rows as $row) {
            $counts[(int) $row['id']]['files'] = (int) $row['total'];
        }
    }

    foreach ($counts as $id => $count) {
        $counts[$id] = array_merge(array('folders' => 0, 'pages' => 0, 'files' => 0), $count);
    }

    return $counts;
}

// The subfolders a listing should show for $folder_id. For the virtual root
// of a basic user this is the top of each granted subtree; otherwise the
// direct children the user may see.
function pg_explorer_child_folders($folder_id, $user, $folders_that_user_has_access_to)
{
    $folder_id = (int) $folder_id;

    if (($folder_id == 0) && ($user['role'] == 3)) {
        // Granted folders whose parent is not granted are the roots of the
        // user's forest.
        $ids = array();

        foreach ($folders_that_user_has_access_to as $granted_id) {
            $map = pg_explorer_folder_map();

            if (isset($map[$granted_id]) == false) {
                continue;
            }

            $parent_id = (int) $map[$granted_id]['folder_parent'];

            if (in_array($parent_id, $folders_that_user_has_access_to) == false) {
                $ids[] = (int) $granted_id;
            }
        }

        if (count($ids) == 0) {
            return array();
        }

        $sql_ids = implode(',', array_map('intval', $ids));

        return db_items(
            "SELECT
                folder.*,
                style.style_name,
                user.user_username AS user_username
            FROM folder
            LEFT JOIN style ON folder.folder_style = style.style_id
            LEFT JOIN user ON folder.folder_user = user.user_id
            WHERE folder.folder_id IN (" . $sql_ids . ")
            ORDER BY folder.folder_order, folder.folder_name");
    }

    $rows = db_items(
        "SELECT
            folder.*,
            style.style_name,
            user.user_username AS user_username
        FROM folder
        LEFT JOIN style ON folder.folder_style = style.style_id
        LEFT JOIN user ON folder.folder_user = user.user_id
        WHERE folder.folder_parent = '" . e($folder_id) . "'
        ORDER BY folder.folder_order, folder.folder_name");

    $visible = array();
    $bin_id = pg_recycle_folder_id(false);

    if ($rows) {
        foreach ($rows as $row) {

            // The bin folder never shows up as an ordinary folder; the
            // explorer reaches it through its own toolbar entry.
            if (($bin_id > 0) && ((int) $row['folder_id'] == $bin_id)) {
                continue;
            }

            if (pg_explorer_folder_visible($row['folder_id'], $folders_that_user_has_access_to)) {
                $visible[] = $row;
            }
        }
    }

    return $visible;
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
        $ready = (db_item("SHOW TABLES LIKE 'recycle_bin'") && db_item("SHOW COLUMNS FROM config LIKE 'recycle_retention_days'")) ? true : false;
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

    return $types;
}

// The same list as a SQL fragment: 'folder', 'page', 'file', 'short_link'.
function pg_recycle_item_types_sql()
{
    return "'" . implode("', '", pg_recycle_item_types()) . "'";
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
    db("DELETE FROM files WHERE id = '" . e($file['id']) . "'");
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

    db("DELETE FROM page WHERE page_id = '" . e($page_id) . "'");

    // Stored SEO findings for this page; links matter in both directions.
    if (db_item("SHOW TABLES LIKE 'seo_issue'")) {
        db("DELETE FROM seo_issue WHERE (entity_type = 'page') AND (entity_id = '" . (int) $page_id . "')");
    }

    if (db_item("SHOW TABLES LIKE 'seo_link'")) {
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

            require_once(dirname(__FILE__) . '/product_builder.php');

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

            require_once(dirname(__FILE__) . '/product_builder.php');

            $product = db_item("SELECT id, name FROM products WHERE (id = '" . e($item_id) . "') AND (recycled = '1')");

            if (!$product) {
                break;
            }

            pg_pb_delete_product($item_id);

            log_activity(lang(array('string' => 'product ({var:1}) was deleted', 'vars' => array($product['name']))), $_SESSION['sessionusername']);
            break;
    }

    if (pg_recycle_ready()) {
        db("DELETE FROM recycle_bin WHERE (item_type = '" . e($item_kind) . "') AND (item_id = '" . e($item_id) . "')");
    }

    return array('status' => 'success', 'message' => '');
}


// ── The bin should not hold a name hostage ──────────────────────────────
//
// Pages and files share one namespace with the web root, and folder names are
// unique across the site, so a row sitting in the bin still answers to its
// name. Upload the same file again and it comes back as index[1].html, and the
// only way to get the plain name back is to empty the bin -- which is not what
// a bin is for, and not what any desktop does.
//
// So a binned row is parked under a name nobody types: "pgbin_<id>_<name>".
// The original travels inside the parked name, so nothing has to be written
// down anywhere else and no column has to be added; the id keeps it unique even
// against a second parked copy of the same name. Restoring strips the prefix
// and asks for the original back. If something has taken it in the meantime,
// get_unique_name() hands back the "[1]" form -- the suffix the rest of the
// software already uses, and the one Windows shows for the same situation.
//
// Only the row that was binned is parked. What lives inside a binned folder
// keeps its name: those rows have no bin record of their own, and renaming a
// whole subtree on the way to the bin would mean hundreds of disk renames on
// an operation that has to be reliable above all.
define('PG_RECYCLE_PARK_PREFIX', 'pgbin_');

// The name a parked row was called before it was parked, or '' when the name
// is not parked (or belongs to some other row, which cannot happen but is not
// worth trusting).
function pg_recycle_parked_original($name, $item_id)
{
    $prefix = PG_RECYCLE_PARK_PREFIX . (int) $item_id . '_';

    if (strpos((string) $name, $prefix) !== 0) {
        return '';
    }

    return (string) substr($name, strlen($prefix));
}

// Park the name of a row on its way into the bin. Best effort by design: if the
// disk rename fails the row keeps the name it had, which is the state the whole
// screen worked in before this existed.
function pg_recycle_park_name($item_kind, $item_id, $user)
{
    $item_id = (int) $item_id;

    if ($item_id <= 0) {
        return;
    }

    $prefix = PG_RECYCLE_PARK_PREFIX . $item_id . '_';

    // A parked name that does not fit gets cut short, and a cut short name
    // cannot be handed back. Those rows keep the name they had -- which is
    // exactly how the bin behaved before any of this, so nothing is lost.
    $limit = ($item_kind == 'folder') ? 200 : 100;

    if ($item_kind == 'folder') {

        $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($item_id) . "'");

        if ((!$folder) || (pg_recycle_parked_original($folder['folder_name'], $item_id) != '')) {
            return;
        }

        if (mb_strlen($prefix . $folder['folder_name']) > $limit) {
            return;
        }

        db(
            "UPDATE folder SET folder_name = '" . e($prefix . $folder['folder_name']) . "'
            WHERE folder_id = '" . e($item_id) . "'");

        return;
    }

    if ($item_kind == 'page') {

        $page = db_item("SELECT page_id, page_name FROM page WHERE page_id = '" . e($item_id) . "'");

        if ((!$page) || (pg_recycle_parked_original($page['page_name'], $item_id) != '')) {
            return;
        }

        if (mb_strlen($prefix . $page['page_name']) > $limit) {
            return;
        }

        db(
            "UPDATE page SET page_name = '" . e($prefix . $page['page_name']) . "'
            WHERE page_id = '" . e($item_id) . "'");

        return;
    }

    if ($item_kind == 'file') {

        $file = db_item("SELECT id, name FROM files WHERE id = '" . e($item_id) . "'");

        if ((!$file) || (pg_recycle_parked_original($file['name'], $item_id) != '')) {
            return;
        }

        if (mb_strlen($prefix . $file['name']) > $limit) {
            return;
        }

        // The record's name is the address, so the disk file moves first and
        // the row is only updated if it did.
        $parked = $prefix . $file['name'];
        $old_path = FILE_DIRECTORY_PATH . '/' . $file['name'];
        $new_path = FILE_DIRECTORY_PATH . '/' . $parked;

        if ((file_exists($old_path) == true) && (@rename($old_path, $new_path) == false)) {
            return;
        }

        db("UPDATE files SET name = '" . e($parked) . "' WHERE id = '" . e($item_id) . "'");
    }
}

// Give a restored row its name back, or the next free "[1]" form of it.
function pg_recycle_release_name($item_kind, $item_id, $user)
{
    $item_id = (int) $item_id;

    if ($item_id <= 0) {
        return;
    }

    if ($item_kind == 'folder') {

        $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($item_id) . "'");

        if (!$folder) {
            return;
        }

        $original = pg_recycle_parked_original($folder['folder_name'], $item_id);

        if ($original == '') {
            return;
        }

        $name = get_unique_name(array('name' => $original, 'type' => 'folder'));

        db("UPDATE folder SET folder_name = '" . e($name) . "' WHERE folder_id = '" . e($item_id) . "'");

        return;
    }

    if ($item_kind == 'page') {

        $page = db_item("SELECT page_id, page_name FROM page WHERE page_id = '" . e($item_id) . "'");

        if (!$page) {
            return;
        }

        $original = pg_recycle_parked_original($page['page_name'], $item_id);

        if ($original == '') {
            return;
        }

        $name = get_unique_name(array('name' => $original, 'type' => 'page'));

        db("UPDATE page SET page_name = '" . e($name) . "' WHERE page_id = '" . e($item_id) . "'");

        return;
    }

    if ($item_kind == 'file') {

        $file = db_item("SELECT id, name FROM files WHERE id = '" . e($item_id) . "'");

        if (!$file) {
            return;
        }

        $original = pg_recycle_parked_original($file['name'], $item_id);

        if ($original == '') {
            return;
        }

        $name = prepare_file_name(get_unique_name(array('name' => $original, 'type' => 'file')));

        $old_path = FILE_DIRECTORY_PATH . '/' . $file['name'];
        $new_path = FILE_DIRECTORY_PATH . '/' . $name;

        if ((file_exists($old_path) == true) && (@rename($old_path, $new_path) == false)) {
            return;
        }

        $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

        db("UPDATE files SET name = '" . e($name) . "', type = '" . e($extension) . "' WHERE id = '" . e($item_id) . "'");
    }
}

// Purge bin entries older than the retention window. Claimed once a day by
// whichever explorer visit comes first; capped per run so a big bin never
// stalls the screen that triggered it.
function pg_recycle_purge_if_due($user)
{
    if (pg_recycle_ready() == false) {
        return;
    }

    $last = (int) db_value("SELECT recycle_last_purge FROM config");

    if ((time() - $last) < 86400) {
        return;
    }

    // Claim the run before doing the work, so two overlapping requests do
    // not both purge.
    db("UPDATE config SET recycle_last_purge = '" . e(time()) . "'");

    $cutoff = time() - (pg_recycle_retention_days() * 86400);

    $entries = db_items(
        "SELECT id, item_type, item_id
        FROM recycle_bin
        WHERE deleted_at < '" . e($cutoff) . "'
        ORDER BY deleted_at
        LIMIT 30");

    if (!$entries) {
        return;
    }

    $purged = 0;

    foreach ($entries as $entry) {
        $result = pg_recycle_hard_delete_item($entry['item_type'], $entry['item_id'], $user);

        if ($result['status'] == 'success') {
            db("DELETE FROM recycle_bin WHERE id = '" . e($entry['id']) . "'");
            $purged++;
        }
    }

    if ($purged > 0) {
        log_activity(lang(array('string' => '{var:1} item(s) were purged from the recycle bin', 'vars' => $purged)), $_SESSION['sessionusername']);
    }
}

// Entry point. api.php routes every "explorer_*" sub-action here.
// Work out how many bytes the base64 body of a data URL will decode to, without
// decoding it. Four base64 characters carry three bytes, and each "=" on the end
// stands for a byte that is not there. Knowing the size up front is what lets an
// oversized upload be turned away before anything is allocated or written.
function pg_explorer_base64_length($data)
{
    $offset = strpos($data, ',');

    // A data URL carries "data:<mime>;base64," in front of the payload. A bare
    // base64 body has no comma at all.
    $offset = ($offset === false) ? 0 : ($offset + 1);

    $encoded_length = strlen($data) - $offset;

    if ($encoded_length <= 4) {
        return 0;
    }

    $padding = 0;

    if (substr($data, -1) == '=') {
        $padding++;
    }

    if (substr($data, -2, 1) == '=') {
        $padding++;
    }

    return (int) ((floor($encoded_length / 4) * 3) - $padding);
}

// Write the base64 body of a data URL to a file without ever holding the decoded
// bytes whole.
//
// The explorer sends uploads as JSON, so the payload arrives as one long base64
// string. base64_decode() on the whole of it needs a second buffer the size of the
// string, on top of the raw request body and the decoded JSON, and three copies of
// a 30 MB upload is exactly what used to exhaust a 128 MB memory_limit - the upload
// died in the middle with "Allowed memory size exhausted" and the screen only ever
// saw a failed request. Decoding 64 KB at a time keeps the extra cost flat, whatever
// the size of the file.
//
// $data is passed by value on purpose: PHP shares the string until something writes
// to it, and nothing here does, so no copy is made. Taking it by reference would
// force one, which is the very thing this function exists to avoid.
function pg_explorer_write_base64($data, $destination)
{
    $offset = strpos($data, ',');
    $offset = ($offset === false) ? 0 : ($offset + 1);

    $length = strlen($data);

    $handle = @fopen($destination, 'wb');

    if ($handle === false) {
        return false;
    }

    $written = 0;

    // A multiple of four, so that every chunk is a whole number of base64 groups
    // and only the last one can carry padding.
    $chunk_size = 65532;

    while ($offset < $length) {

        $chunk = base64_decode(substr($data, $offset, $chunk_size), true);

        if (($chunk === false) || (fwrite($handle, $chunk) === false)) {
            fclose($handle);
            @unlink($destination);
            return false;
        }

        $written = $written + strlen($chunk);
        $offset  = $offset + $chunk_size;
    }

    fclose($handle);

    return $written;
}

// ── Short links ─────────────────────────────────────────────────────────
//
// A short link is a name at the root of the site that stands for something
// else: a page, a product group, a product, a file, or an address off the
// site.  router.php resolves them as requests arrive rather than from rules
// written into the rewrite file, so making one is a single INSERT -- which is
// what lets them sit in the file manager beside the pages they point at.
//
// Their names share one namespace with pages and files, and
// check_name_availability() is the single place that knows the whole of it.
// Nothing here re-implements that check.

function pg_short_link_types()
{
    return array('page', 'product_group', 'product', 'url', 'file');
}

// The name is a path segment on the live site, so it may only hold what a path
// segment may hold.  Same rule add_short_link.php applies, in one place.
//
// Square brackets are in the set, for the same reason pg_ascii_file_name()
// keeps them: they are this software's own suffix for a name that is taken.
// pg_short_link_free_name() hands out "new_short_link[4]", so a rule that
// rejected them refused the very name the create had just given the row --
// the operator was told the format was illegal by the screen that wrote it.
function pg_short_link_clean_name($name)
{
    $name = str_replace(' ', '_', trim((string) $name));

    return (preg_match('/[^A-Za-z0-9._\-\/\[\]]/', $name) == 1) ? '' : $name;
}

// A free name, Windows style: the wanted one, then the wanted one with [1],
// [2] and so on.  The namespace being checked is pages, files and short links
// together, so a short link can never shadow a page.
function pg_short_link_free_name($wanted)
{
    $wanted = pg_short_link_clean_name($wanted);

    if ($wanted == '') {
        $wanted = 'new_short_link';
    }

    if (check_name_availability(array('name' => $wanted)) == true) {
        return $wanted;
    }

    for ($index = 1; $index < 500; $index++) {

        $candidate = $wanted . '[' . $index . ']';

        if (check_name_availability(array('name' => $candidate)) == true) {
            return $candidate;
        }
    }

    return '';
}

// Every short link, with the joins the destination column needs.  One row per
// link; the access filter below decides which of them this person may see.
// Whether short links can go to the bin at all.
//
// Guarded the same way the catalog's is: the flag column and the shared bin
// table both come from 2026.4.4, and before it runs the screen simply has no
// bin for short links -- a delete is still a delete, as it always was.
function pg_short_link_recycle_ready()
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    $ready = (pg_recycle_ready() == true)
        && (db_item("SHOW COLUMNS FROM short_links LIKE 'recycled'") ? true : false);

    return $ready;
}

// The condition that keeps binned rows out of a listing, or keeps everything
// else out of the bin's. Empty before the upgrade, so the same queries run
// either way.
function pg_short_link_live_filter($recycled = false)
{
    if (pg_short_link_recycle_ready() == false) {
        return '';
    }

    return "short_links.recycled = '" . ($recycled ? '1' : '0') . "'";
}

// $where is the caller's own condition; the recycled state is added to it here
// so no listing can forget it.
function pg_short_link_rows($where = '', $recycled = false)
{
    $live = pg_short_link_live_filter($recycled);

    if ($live != '') {
        $where = ($where == '') ? ('WHERE ' . $live) : ($where . ' AND ' . $live);
    }

    return db_items(
        "SELECT
            short_links.id,
            short_links.name,
            short_links.destination_type,
            short_links.page_id,
            short_links.product_group_id,
            short_links.product_id,
            short_links.url,
            short_links.file_id,
            short_links.tracking_code,
            short_links.created_user_id,
            short_links.created_timestamp,
            short_links.last_modified_timestamp,
            page.page_name,
            page.page_folder AS folder_id,
            product_groups.address_name AS product_group_address_name,
            products.address_name AS product_address_name,
            files.name AS file_name,
            created_user.user_username AS created_username,
            last_modified_user.user_username AS last_modified_username
        FROM short_links
        LEFT JOIN page ON short_links.page_id = page.page_id
        LEFT JOIN product_groups ON short_links.product_group_id = product_groups.id
        LEFT JOIN products ON short_links.product_id = products.id
        LEFT JOIN files ON short_links.file_id = files.id
        LEFT JOIN user AS created_user ON short_links.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON short_links.last_modified_user_id = last_modified_user.user_id
        " . $where . "
        ORDER BY short_links.name ASC");
}

// The rule view_short_links.php applies, in a function so the listing, the
// rename, the duplicate and the delete cannot drift apart from each other:
// above a basic user everything is visible; a basic user sees a link whose
// page sits in a folder they may edit, and the ones they made themselves.
// Short links belong to no folder, so the folder-based rights that decide
// what a plain user may see do not reach them: the area is a right of roles
// 0-2 and a user (role 3) sees none of them.
function pg_short_link_visible($user)
{
    return ($user['role'] < 3);
}

// Every short link this person may see, live or binned. The listing, the bin
// and "empty the bin" all read it, so none of them can be looking at a
// different set than the others.
function pg_short_link_visible_rows($user, $recycled = false)
{
    $out = array();

    foreach (pg_short_link_rows('', $recycled) as $row) {

        if (pg_short_link_visible($user) == false) {
            continue;
        }

        $out[] = $row;
    }

    return $out;
}

function pg_short_link_by_id($user, $id, $recycled = false)
{
    $id = (int) $id;

    if ($id <= 0) {
        return null;
    }

    $rows = pg_short_link_rows("WHERE short_links.id = '" . e($id) . "'", $recycled);

    if (!$rows) {
        return null;
    }

    return (pg_short_link_visible($user) == true) ? $rows[0] : null;
}

// Where the link lands, written the way a person would read it.
function pg_short_link_destination($row)
{
    switch ($row['destination_type']) {

        case 'url':
            return (string) $row['url'];

        case 'file':
            return (string) $row['file_name'];

        case 'product_group':
            return encode_url_path((string) $row['page_name']) . '/' . encode_url_path((string) $row['product_group_address_name']);

        case 'product':
            return encode_url_path((string) $row['page_name']) . '/' . encode_url_path((string) $row['product_address_name']);
    }

    return encode_url_path((string) $row['page_name']);
}

// Dressed as a file, the way a backup entry is dressed as one: the grid, the
// list, the sorter, the search box and the selection then work on it with the
// code they already have.  The short_link flag is what the few places that do
// have to tell the difference look at.
function pg_short_link_item($row)
{
    $timestamp = (int) $row['last_modified_timestamp'];
    $destination = pg_short_link_destination($row);

    return array(
        'kind' => 'file',
        'id' => (int) $row['id'],
        'short_link' => true,
        'destination_type' => (string) $row['destination_type'],
        'destination' => $destination,
        'tracking_code' => (string) $row['tracking_code'],
        // What the wizard needs to reopen this link as it stands. Sent with
        // the row rather than fetched again when the window opens: it is five
        // small numbers, and the alternative is a round trip per click.
        'page_id' => (int) $row['page_id'],
        'product_group_id' => (int) $row['product_group_id'],
        'product_id' => (int) $row['product_id'],
        'file_id' => (int) $row['file_id'],
        'link_url' => (string) $row['url'],
        'name' => (string) $row['name'],
        'folder_id' => 0,
        'folder_name' => '',
        'type' => 'short_link',
        'is_image' => false,
        'size' => 0,
        'size_label' => $destination,
        'design' => false,
        'optimized' => false,
        'image_width' => 0,
        'image_height' => 0,
        'description' => '',
        'access_control_type' => 'public',
        'access_icon' => pg_explorer_access_icon('public'),
        'archived' => false,
        'timestamp' => $timestamp,
        'modified' => get_relative_time(array('timestamp' => $timestamp)),
        'username' => (string) $row['last_modified_username'],
        'permissions' => '',
        'url' => OUTPUT_PATH . encode_url_path((string) $row['name']),
        'can_edit' => true,
        'edit_url' => 'edit_short_link.php?id=' . (int) $row['id']);
}

// The option lists the wizard needs.  They come from the same functions the
// classic add screen uses, so a page or a product that is offered there is
// offered here and nowhere else has to learn the rules.
function pg_short_link_options_list($options)
{
    $out = array();

    foreach ($options as $label => $value) {

        // Two shapes arrive here. Most of these helpers return a label => value
        // map; get_product_group_options() asked for 'array' returns a list of
        // {label, value} pairs instead. Read blind, that second shape puts the
        // row number in the label and the row itself in the value, which is
        // why the group list came out as numbers.
        if (is_array($value)) {

            $label = isset($value['label']) ? $value['label'] : '';
            $value = isset($value['value']) ? $value['value'] : '';
        }

        // The helpers put their own "- Select … -" entry first; the wizard
        // draws its own placeholder and would otherwise show two.
        if ((string) $value === '') {
            continue;
        }

        // Those labels are built for an <option> written by PHP, so they are
        // already escaped and carry &nbsp; for the tree indent. This one is
        // written by the browser from JSON, which escapes it again -- so it is
        // decoded once here and the entities do not end up on screen.
        $out[] = array(
            'v' => (string) $value,
            't' => html_entity_decode((string) $label, ENT_QUOTES, 'UTF-8'));
    }

    return $out;
}

// Checks a wizard submission and hands back either the columns to write or the
// field that is wrong.  Shared by create; the classic edit screen keeps its
// own copy because it also has to deal with liveform's session round trip.
function pg_short_link_read_request($request, $user)
{
    $type = isset($request['destination_type']) ? (string) $request['destination_type'] : '';

    if (in_array($type, pg_short_link_types(), true) == false) {
        return array('error' => 'destination_type', 'message' => lang('The destination type is not valid.'));
    }

    $value = function ($key) use ($request) {
        return isset($request[$key]) ? trim((string) $request[$key]) : '';
    };

    $page_id = 0;
    $page_field = '';
    $columns = array();

    switch ($type) {

        case 'page':
            $page_field = 'page_id';
            $page_id = (int) $value('page_id');

            if ($page_id <= 0) {
                return array('error' => 'page_id', 'message' => lang('Page is required.'));
            }

            break;

        case 'product_group':
            $catalog = (int) $value('catalog_page_id');
            $detail = (int) $value('catalog_detail_page_id');

            if (($catalog <= 0) && ($detail <= 0)) {
                return array('error' => 'catalog_page_id', 'message' => lang('Catalog Page or Catalog Detail Page is required.'));
            }

            if (($catalog > 0) && ($detail > 0)) {
                return array('error' => 'catalog_page_id', 'message' => lang('Please select either a Catalog Page or a Catalog Detail Page, not both.'));
            }

            $page_field = ($catalog > 0) ? 'catalog_page_id' : 'catalog_detail_page_id';
            $page_id = ($catalog > 0) ? $catalog : $detail;

            $group_id = (int) $value('product_group_id');

            if ($group_id <= 0) {
                return array('error' => 'product_group_id', 'message' => lang('Product Group is required.'));
            }

            if (db_value("SELECT COUNT(*) FROM product_groups WHERE id = '" . e($group_id) . "'") == 0) {
                return array('error' => 'product_group_id', 'message' => lang('The product group does not exist.'));
            }

            $columns['product_group_id'] = $group_id;

            break;

        case 'product':
            $page_field = 'catalog_detail_page_id';
            $page_id = (int) $value('catalog_detail_page_id');

            if ($page_id <= 0) {
                return array('error' => 'catalog_detail_page_id', 'message' => lang('Catalog Detail Page is required.'));
            }

            $product_id = (int) $value('product_id');

            if ($product_id <= 0) {
                return array('error' => 'product_id', 'message' => lang('Product is required.'));
            }

            if (db_value("SELECT COUNT(*) FROM products WHERE id = '" . e($product_id) . "'") == 0) {
                return array('error' => 'product_id', 'message' => lang('The product does not exist.'));
            }

            $columns['product_id'] = $product_id;

            break;

        case 'url':
            $url = $value('url');

            if ($url == '') {
                return array('error' => 'url', 'message' => lang('Destination URL is required.'));
            }

            // A space here would reach the rewrite file and take every URL on
            // the site down with it.
            if (preg_match('/\s/', $url) == 1) {
                return array('error' => 'url', 'message' => lang('Sorry, you may not enter a space in the URL.'));
            }

            $columns['url'] = $url;

            break;

        case 'file':
            $file_id = (int) $value('file_id');

            if ($file_id <= 0) {
                return array('error' => 'file_id', 'message' => lang('Destination URL is required.'));
            }

            if (db_value("SELECT COUNT(*) FROM files WHERE id = '" . e($file_id) . "'") == 0) {
                return array('error' => 'file_id', 'message' => lang('Sorry, the file could not be found.'));
            }

            $columns['file_id'] = $file_id;

            break;
    }

    // The page has to exist.
    if (($page_id > 0) && (db_value("SELECT page_folder FROM page WHERE page_id = '" . e($page_id) . "'") === null)) {
        return array('error' => $page_field, 'message' => lang('The page does not exist.'));
    }

    return array(
        'type' => $type,
        'page_id' => $page_id,
        'tracking_code' => $value('tracking_code'),
        'columns' => $columns);
}

// ── Catalog: product groups and products through the explorer ────────────
//
// The catalog is a folder tree wearing different words. product_groups has
// parent_id, sort_order, name, image_name and seo_score, exactly the columns
// the folder tree runs on, and products hang off groups through
// products_groups_xref the way pages hang off folders. So the explorer takes
// it as a fifth mode rather than a screen of its own -- the same route the
// backups mode took, which feeds this shell from the filesystem.
//
// One rule is not shared with folders, and it is the one that produces bugs
// when it is forgotten: browsing a group lists its DIRECT products, while the
// counts and the price range on a group tile describe its WHOLE subtree. A
// store root usually holds no products of its own, so a direct-membership
// count there reads as an empty catalog while the screen is still full of
// category tiles.

function pg_catalog_access($user)
{
    return (($user['role'] < 3) || ($user['manage_ecommerce'] == true));
}

// Whether the 2026.4.9 columns are in place. Before that upgrade the catalog
// simply has no bin, the same way the file manager had none before 2026.4.7,
// and nothing here pretends otherwise.
function pg_catalog_recycle_ready()
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    // The flag columns are only half of it: the catalog writes its deletions
    // into recycle_bin like everything else, and the countdown and the daily
    // purge both read the retention setting that lives beside it.
    $ready = (pg_recycle_ready() == true)
        && (db_item("SHOW COLUMNS FROM product_groups LIKE 'recycled'") ? true : false)
        && (db_item("SHOW COLUMNS FROM products LIKE 'recycled'") ? true : false);

    return $ready;
}

// The WHERE fragment that keeps binned rows out of a listing. Empty before the
// upgrade, so the same queries run either way.
function pg_catalog_live_filter($table, $recycled = false)
{
    if (pg_catalog_recycle_ready() == false) {
        return '';
    }

    return ' AND (' . $table . '.recycled = ' . ($recycled ? "'1'" : "'0'") . ')';
}

// Every group, once, keyed by id. The table is small -- a few dozen rows on a
// large store -- so the whole tree is walked in memory rather than through a
// recursive CTE, which is also what the catalog widget settled on.
function pg_catalog_group_map($recycled = false)
{
    static $maps = array();

    $key = $recycled ? 'recycled' : 'live';

    if (isset($maps[$key])) {
        return $maps[$key];
    }

    $map = array();

    $rows = db_items(
        "SELECT
            id,
            name,
            parent_id,
            sort_order,
            enabled,
            display_type,
            image_name,
            short_description,
            address_name,
            seo_score,
            seo_analysis_current,
            featured,
            timestamp,
            user
        FROM product_groups
        WHERE 1 = 1" . pg_catalog_live_filter('product_groups', $recycled) . "
        ORDER BY sort_order, name");

    foreach ((array) $rows as $row) {
        $map[(int) $row['id']] = $row;
    }

    $maps[$key] = $map;

    return $map;
}

function pg_catalog_child_groups($parent_id)
{
    $children = array();

    foreach (pg_catalog_group_map() as $id => $row) {
        if ((int) $row['parent_id'] == (int) $parent_id) {
            $children[] = $row;
        }
    }

    return $children;
}

// The group itself plus everything under it. Guarded against a parent_id loop,
// which a hand-edited database can produce and which would otherwise hang the
// request rather than fail it.
function pg_catalog_subtree_ids($group_id)
{
    static $cache = array();

    $group_id = (int) $group_id;

    if (isset($cache[$group_id])) {
        return $cache[$group_id];
    }

    $map = pg_catalog_group_map();
    $ids = array();
    $queue = array($group_id);
    $guard = 5000;

    while ((count($queue) > 0) && ($guard-- > 0)) {

        $current = (int) array_shift($queue);

        if (isset($ids[$current])) {
            continue;
        }

        $ids[$current] = true;

        foreach ($map as $id => $row) {
            if (((int) $row['parent_id'] == $current) && (!isset($ids[(int) $id]))) {
                $queue[] = (int) $id;
            }
        }
    }

    $cache[$group_id] = array_keys($ids);

    return $cache[$group_id];
}

// The same walk over the binned side of the catalog.
//
// pg_catalog_subtree_ids() reads the live map, which is what binning needs: it
// collects what is still on the site. Restoring and purging need the other
// half -- what is in the bin under this group -- so the walk is repeated
// against the recycled map rather than filtering the live one.
function pg_catalog_recycled_subtree_ids($group_id)
{
    $map = pg_catalog_group_map(true);
    $ids = array();
    $queue = array((int) $group_id);
    $guard = 5000;

    while ((count($queue) > 0) && ($guard-- > 0)) {

        $current = (int) array_shift($queue);

        if ((isset($ids[$current])) || (isset($map[$current]) == false)) {
            continue;
        }

        $ids[$current] = true;

        foreach ($map as $id => $row) {
            if (((int) $row['parent_id'] == $current) && (!isset($ids[(int) $id]))) {
                $queue[] = (int) $id;
            }
        }
    }

    return array_keys($ids);
}

// How many products live under a group, and what they cost.
//
// COUNT(DISTINCT) rather than a sum of per-group counts: a product may sit in
// two groups of the same subtree ("Pens" and "New arrivals"), and summing would
// count it twice. Called only for the groups actually on screen, so it stays a
// handful of indexed queries per request, and memoized because the tree asks
// for the same group the grid just asked about.
function pg_catalog_group_stats($group_id)
{
    static $cache = array();

    $group_id = (int) $group_id;

    if (isset($cache[$group_id])) {
        return $cache[$group_id];
    }

    $ids = pg_catalog_subtree_ids($group_id);

    $stats = array('products' => 0, 'price_min' => null, 'price_max' => null, 'out_of_stock' => 0);

    // A group in the bin is not part of the live tree, so its subtree walk finds
    // only itself; the count below is then simply its own direct members.


    if (count($ids) > 0) {

        // Sold out rides along in the query that was already being run for the
        // count and the price range, so a group knowing it holds an empty shelf
        // costs nothing extra. Sold out is the storefront's own rule: stock is
        // tracked, none is left, and back orders are off -- a product on back
        // order is still orderable, so it is not flagged here.
        //
        // COUNT(DISTINCT ...) rather than SUM(CASE ...) because a product may be
        // listed by two groups inside the same subtree and would be counted
        // twice.
        $row = db_item(
            "SELECT
                COUNT(DISTINCT products_groups_xref.product) AS total,
                MIN(products.price) AS price_min,
                MAX(products.price) AS price_max,
                COUNT(DISTINCT CASE
                    WHEN ((products.inventory = '1') AND (products.inventory_quantity <= 0) AND (products.backorder = '0'))
                    THEN products_groups_xref.product
                END) AS out_of_stock
            FROM products_groups_xref
            INNER JOIN products ON products.id = products_groups_xref.product
            WHERE (products_groups_xref.product_group IN (" . implode(',', array_map('intval', $ids)) . "))" . pg_catalog_live_filter('products'));

        if (is_array($row)) {
            $stats['products'] = (int) $row['total'];
            $stats['price_min'] = ($row['price_min'] === null) ? null : (int) $row['price_min'];
            $stats['price_max'] = ($row['price_max'] === null) ? null : (int) $row['price_max'];
            $stats['out_of_stock'] = (int) $row['out_of_stock'];
        }
    }

    $cache[$group_id] = $stats;

    return $stats;
}

// Products that belong to this group itself. The grid lists these; the subtree
// belongs to the counts, not to the listing.
function pg_catalog_direct_product_count($group_id)
{
    return (int) db_value(
        "SELECT COUNT(*)
        FROM products_groups_xref
        INNER JOIN products ON products.id = products_groups_xref.product
        WHERE (products_groups_xref.product_group = '" . e((int) $group_id) . "')" . pg_catalog_live_filter('products'));
}

function pg_catalog_breadcrumb($group_id)
{
    $map = pg_catalog_breadcrumb_trail($group_id);
    $crumbs = array();

    foreach ($map as $row) {
        $crumbs[] = array('id' => (int) $row['id'], 'name' => $row['name']);
    }

    return $crumbs;
}

// Root first, the group itself last.
function pg_catalog_breadcrumb_trail($group_id)
{
    $map = pg_catalog_group_map();
    $trail = array();
    $current = (int) $group_id;
    $guard = 200;

    while (($current > 0) && (isset($map[$current])) && ($guard-- > 0)) {
        array_unshift($trail, $map[$current]);
        $current = (int) $map[$current]['parent_id'];
    }

    return $trail;
}

// display_type is not a display detail, it is what the row IS.
//
//   browse  -> a category. Holds subcategories and products; you walk into it.
//   select  -> a variant set. One sellable thing whose members are its variants,
//              which is why the storefront links it to the product detail page
//              and strips Add to Cart from it. view_products.php?mode=variant_sets
//              lists exactly these rows.
//
// Both are product_groups and take the same operations -- rename, move, enable,
// reorder -- so they stay one kind and the screen tells them apart by this.
function pg_catalog_group_role($display_type)
{
    return ($display_type == 'select') ? 'variant_set' : 'category';
}

// One group as the explorer draws it. Same envelope the folder rows use, so
// selection, sorting, renaming and the clipboard need no special case.
function pg_catalog_group_item($row)
{
    $id = (int) $row['id'];
    $stats = pg_catalog_group_stats($id);
    $children = pg_catalog_child_groups($id);
    $direct = pg_catalog_direct_product_count($id);

    return array(
        'kind' => 'group',
        'id' => $id,
        'parent_id' => (int) $row['parent_id'],
        'name' => (string) $row['name'],
        'type' => 'group',
        'size' => 0,
        'size_label' => '',
        'timestamp' => (int) $row['timestamp'],
        'order' => (int) $row['sort_order'],
        'enabled' => ($row['enabled'] == '1'),
        'display_type' => (string) $row['display_type'],
        'catalog_role' => pg_catalog_group_role($row['display_type']),
        'featured' => ($row['featured'] == '1'),
        'image_name' => (string) $row['image_name'],
        'short_description' => (string) $row['short_description'],
        'address_name' => (string) $row['address_name'],
        'seo_score' => (int) $row['seo_score'],
        'seo' => pg_catalog_seo_payload($row),
        'counts' => array(
            'groups' => count($children),
            'products' => $direct,
            'products_deep' => $stats['products']),
        'price_min' => $stats['price_min'],
        'price_max' => $stats['price_max'],
        'out_of_stock' => $stats['out_of_stock'],
        'has_children' => (count($children) > 0),
        'empty' => ((count($children) == 0) && ($direct == 0)),
        'edit_url' => 'edit_product_group.php?id=' . $id,
        'can_edit' => true);
}

function pg_catalog_product_item($row)
{
    $id = (int) $row['id'];

    return array(
        'kind' => 'product',
        'id' => $id,
        'name' => (string) $row['name'],
        'type' => 'product',
        'size' => 0,
        'size_label' => '',
        'timestamp' => (int) $row['timestamp'],
        'order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
        'enabled' => ($row['enabled'] == '1'),
        'price' => (int) $row['price'],
        'image_name' => (string) $row['image_name'],
        // The storefront prints this, not the name: the name is the ID / SKU.
        // The grid keeps calling rows by their name, so this rides along beside
        // it rather than replacing it.
        'short_description' => (string) $row['short_description'],
        'address_name' => (string) $row['address_name'],
        'seo_score' => (int) $row['seo_score'],
        // Stock is only a number when the product is actually tracked; an
        // untracked product is always available and saying "0 in stock" about
        // it would be a lie the operator then chases.
        'inventory' => ($row['inventory'] == '1'),
        'quantity' => (int) $row['inventory_quantity'],
        'backorder' => ($row['backorder'] == '1'),
        'group_id' => isset($row['product_group']) ? (int) $row['product_group'] : 0,
        'group_name' => isset($row['group_name']) ? (string) $row['group_name'] : '',
        // Filled in by pg_catalog_attach_memberships(). A product is not a page:
        // it can sit in several groups at once, so "which group is it in" has no
        // single answer and the screen has to be able to say so.
        'groups' => array(),
        'group_count' => 0,
        'seo' => pg_catalog_seo_payload($row),
        'edit_url' => 'edit_product.php?id=' . $id,
        'can_edit' => true);
}

// The groups every listed product belongs to, in one query for the whole screen.
//
// Pages and files live in exactly one folder, so the file manager never had to
// ask this. Products are a many-to-many through products_groups_xref, and that
// difference reaches the surface: a product shown here may be reachable from
// three categories, removing it from this one does not delete it, and dragging
// it elsewhere is a membership change rather than a move.
function pg_catalog_attach_memberships(&$products)
{
    if (count($products) == 0) {
        return;
    }

    $ids = array();

    foreach ($products as $product) {
        $ids[] = (int) $product['id'];
    }

    $rows = db_items(
        "SELECT
            products_groups_xref.product,
            products_groups_xref.product_group,
            product_groups.name,
            product_groups.display_type
        FROM products_groups_xref
        INNER JOIN product_groups ON product_groups.id = products_groups_xref.product_group
        WHERE products_groups_xref.product IN (" . implode(',', $ids) . ")
        ORDER BY product_groups.name");

    $by_product = array();

    foreach ((array) $rows as $row) {

        $product_id = (int) $row['product'];

        if (isset($by_product[$product_id]) == false) {
            $by_product[$product_id] = array();
        }

        $by_product[$product_id][] = array(
            'id' => (int) $row['product_group'],
            'name' => (string) $row['name'],
            'catalog_role' => pg_catalog_group_role($row['display_type']));
    }

    foreach ($products as $index => $product) {

        $product_id = (int) $product['id'];
        $groups = isset($by_product[$product_id]) ? $by_product[$product_id] : array();

        $products[$index]['groups'] = $groups;
        $products[$index]['group_count'] = count($groups);

        // In the flat view the row has no group of its own to show; the first
        // one by name stands in, and group_count says how many more there are.
        if (($products[$index]['group_name'] == '') && (count($groups) > 0)) {
            $products[$index]['group_name'] = $groups[0]['name'];
        }
    }
}

// The score ring the page tiles draw, for rows that carry a score but not the
// flag bitmap pages have. Same colours, so the three screens cannot drift.
function pg_catalog_seo_payload($row)
{
    require_once(dirname(__FILE__) . '/seo.php');

    $score = isset($row['seo_score']) ? (int) $row['seo_score'] : 0;
    $current = isset($row['seo_analysis_current']) ? ($row['seo_analysis_current'] == '1') : ($score > 0);

    if (($score <= 0) || ($current == false)) {
        return array('scored' => false);
    }

    $color = pg_seo_score_color($score);

    return array(
        'scored' => true,
        'score' => $score,
        'class' => (string) $color['class'],
        'style' => (string) $color['style'],
        'hex' => pg_seo_score_hex($score),
        'labels' => array());
}

// Where a group or a product is actually visible on the site.
//
// There is no single answer, which is why the screen offers a list instead of
// a link: a group can be the subject of a legacy catalog page and, at the same
// time, be listed by a catalog widget that lives in a page style several pages
// share. Both routes are followed here.
//
// Route two costs a scan of two text columns, so it is done ONCE per request
// for every group at once and kept. Asking per group meant re-scanning
// shared_components and every page style again for each of a product's groups
// and each of their ancestors, which on a real catalog never came back.
function pg_catalog_widget_pages()
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    $map = array();

    // Which widgets are pinned to a group, and which group each one means.
    $widgets = db_items(
        "SELECT id, system_region_config
        FROM shared_components
        WHERE system_region_config LIKE '%\"product_group_id\"%'
        ORDER BY id");

    $group_of_widget = array();

    foreach ((array) $widgets as $widget) {

        $config = json_decode((string) $widget['system_region_config'], true);

        if ((is_array($config)) && (isset($config['product_group_id'])) && ((int) $config['product_group_id'] > 0)) {
            $group_of_widget[(int) $widget['id']] = (int) $config['product_group_id'];
        }
    }

    if (count($group_of_widget) == 0) {
        return $map;
    }

    // One query per widget, and the style trees stay in the database. Matching
    // in PHP would mean shipping every page's whole tree over for a handful of
    // substring checks; a site has only a few catalog widgets, so a few scans
    // cost far less than that transfer. The delimiter after the id keeps 12
    // from matching 120.
    foreach ($group_of_widget as $widget_id => $group_id) {

        $rows = db_items(
            "SELECT page.page_id, page.page_name
            FROM page
            INNER JOIN style ON style.style_id = page.page_style
            WHERE (" . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . e($widget_id) . ",%')
               OR (" . pg_page_tree_sql_expr() . " LIKE '%\"sharedId\":" . e($widget_id) . "}%')
            ORDER BY page.page_name");

        foreach ((array) $rows as $row) {

            if (isset($map[$group_id]) == false) {
                $map[$group_id] = array();
            }

            $map[$group_id][(int) $row['page_id']] = array(
                'id' => (int) $row['page_id'],
                'name' => (string) $row['page_name'],
                'source' => 'widget');
        }
    }

    return $map;
}

function pg_catalog_pages_for_group($group_id)
{
    static $cache = array();

    $group_id = (int) $group_id;

    if (isset($cache[$group_id])) {
        return $cache[$group_id];
    }

    $pages = array();

    if ($group_id <= 0) {
        return $pages;
    }

    // Route one: the legacy catalog page type, one row per page.
    $rows = db_items(
        "SELECT page.page_id, page.page_name
        FROM catalog_pages
        INNER JOIN page ON page.page_id = catalog_pages.page_id
        WHERE catalog_pages.product_group_id = '" . e($group_id) . "'
        ORDER BY page.page_name");

    foreach ((array) $rows as $row) {
        $pages[(int) $row['page_id']] = array(
            'id' => (int) $row['page_id'],
            'name' => (string) $row['page_name'],
            'source' => 'catalog');
    }

    // Route two: a catalog widget configured for this group.
    $widget_pages = pg_catalog_widget_pages();

    if (isset($widget_pages[$group_id])) {
        foreach ($widget_pages[$group_id] as $page_id => $page) {
            if (isset($pages[$page_id]) == false) {
                $pages[$page_id] = $page;
            }
        }
    }

    $cache[$group_id] = array_values($pages);

    return $cache[$group_id];
}

// A product is reachable through every page that lists any group it belongs
// to, and through a page listing an ancestor of one of those groups once the
// visitor has walked down into it.
function pg_catalog_pages_for_product($product_id)
{
    $product_id = (int) $product_id;
    $pages = array();

    if ($product_id <= 0) {
        return $pages;
    }

    $groups = db_values(
        "SELECT product_group
        FROM products_groups_xref
        WHERE product = '" . e($product_id) . "'");

    $map = pg_catalog_group_map();
    $wanted = array();

    foreach ((array) $groups as $group_id) {

        $current = (int) $group_id;
        $guard = 200;

        while (($current > 0) && (isset($map[$current])) && ($guard-- > 0)) {
            $wanted[$current] = true;
            $current = (int) $map[$current]['parent_id'];
        }
    }

    foreach (array_keys($wanted) as $group_id) {
        foreach (pg_catalog_pages_for_group($group_id) as $page) {
            $pages[$page['id']] = $page;
        }
    }

    return array_values($pages);
}

// Copy a product group into another one, with everything under it.
//
// A group is a row plus three kinds of link: the products listed in it, its
// pictures, and its attributes. All three are copied, and so are its subgroups,
// because a category copied without what it contains is not a copy of anything
// an operator recognises -- the same reason the folder paste walks the subtree.
//
// The products themselves are NOT duplicated. A product belongs to as many
// groups as it likes, so the copy lists the same products; duplicating them
// would leave two of every item in the shop.
// Which search results pages index any of these groups.
//
// A search page is pointed at one product group and indexes the whole tree under
// it, so moving, copying or removing a group changes what that page covers. The
// legacy edit, duplicate and delete screens all drop the page's keyword cloud
// before the change and build it again afterwards; doing less here would leave a
// search page offering keywords for a group that is no longer under it.
function pg_catalog_search_pages_for_groups($group_ids)
{
    $wanted = array();

    foreach ((array) $group_ids as $group_id) {

        if ((int) $group_id > 0) {
            $wanted[] = (int) $group_id;
        }
    }

    $pages = array();

    if (count($wanted) == 0) {
        return $pages;
    }

    foreach ((array) db_items("SELECT page_id, product_group_id FROM search_results_pages WHERE search_catalog_items = '1'") as $page) {

        foreach ((array) get_product_groups_in_product_group_tree($page['product_group_id']) as $tree_group) {

            if (in_array((int) $tree_group['id'], $wanted)) {
                $pages[(int) $page['page_id']] = $page;
                break;
            }
        }
    }

    return $pages;
}

function pg_catalog_rebuild_search_tag_clouds($pages)
{
    foreach ((array) $pages as $page) {
        delete_tag_cloud_keywords_for_search_results_page($page['page_id']);
        update_tag_cloud_keywords_for_search_results_page_product_group($page['page_id'], $page['product_group_id']);
    }
}

function pg_catalog_copy_group($group_id, $target_id, $user, $depth = 0, &$created = null)
{
    // The tree cannot nest deeper than this in practice, and a malformed
    // parent_id must not turn a copy into a runaway.
    if ($depth > 30) {
        return 0;
    }

    $row = db_item("SELECT * FROM product_groups WHERE id = '" . e((int) $group_id) . "'");

    if (is_array($row) == false) {
        return 0;
    }

    // The address is the row's place on the site and two rows cannot share one,
    // so the copy takes the next free "[n]" -- the same suffix the rest of the
    // catalog uses for a duplicate.
    $suffix = get_duplicate_catalog_item_address_name_number($row['address_name']);
    $address_name = prepare_catalog_item_address_name($row['address_name'] . '[' . $suffix . ']');

    // The copy carries that number in its name as well, because the operator
    // reads names and not addresses: duplicating a group four times otherwise
    // leaves five tiles with one name between them and no way to tell which is
    // which. Only the group actually being copied is renamed -- the children
    // under it are already told apart by the parent they sit in, the way a
    // copied folder's contents keep their names.
    $name = ($depth == 0) ? ($row['name'] . ' [' . $suffix . ']') : $row['name'];

    db(
        "INSERT INTO product_groups (
            name, enabled, parent_id, sort_order, display_type, address_name,
            short_description, full_description, details, code, keywords,
            image_name, title, meta_description, meta_keywords, attributes,
            featured, featured_sort_order, new_date, user, timestamp)
        VALUES (
            '" . e($name) . "',
            '" . e($row['enabled']) . "',
            '" . e((int) $target_id) . "',
            '" . e((int) $row['sort_order']) . "',
            '" . e($row['display_type']) . "',
            '" . e($address_name) . "',
            '" . e($row['short_description']) . "',
            '" . e($row['full_description']) . "',
            '" . e($row['details']) . "',
            '" . e($row['code']) . "',
            '" . e($row['keywords']) . "',
            '" . e($row['image_name']) . "',
            '" . e($row['title']) . "',
            '" . e($row['meta_description']) . "',
            '" . e($row['meta_keywords']) . "',
            '" . e((int) $row['attributes']) . "',
            '" . e((int) $row['featured']) . "',
            '" . e((int) $row['featured_sort_order']) . "',
            '" . e($row['new_date']) . "',
            '" . e($user['id']) . "',
            UNIX_TIMESTAMP())");

    $new_id = mysqli_insert_id(db::$con);
    $copied = 1;

    if (is_array($created)) {
        $created[] = (int) $new_id;
    }

    // The products it lists, by reference rather than by duplication. Every
    // column of the listing is carried, the way duplicate_product_group.php
    // carries it: 'featured' and the two orderings are what decide where a
    // product shows up on the storefront, and a copy that dropped them would
    // look right in this screen and wrong on the site.
    $members = db_items(
        "SELECT product, sort_order, featured, featured_sort_order, new_date
        FROM products_groups_xref
        WHERE product_group = '" . e((int) $group_id) . "'");

    foreach ((array) $members as $member) {
        db(
            "INSERT INTO products_groups_xref (product, product_group, sort_order, featured, featured_sort_order, new_date)
            VALUES (
                '" . e((int) $member['product']) . "',
                '" . e($new_id) . "',
                '" . e((int) $member['sort_order']) . "',
                '" . e((int) $member['featured']) . "',
                '" . e((int) $member['featured_sort_order']) . "',
                '" . e($member['new_date']) . "')");
    }

    foreach ((array) db_values("SELECT file_name FROM product_groups_images_xref WHERE product_group = '" . e((int) $group_id) . "'") as $file_name) {
        db("INSERT INTO product_groups_images_xref (product_group, file_name) VALUES ('" . e($new_id) . "', '" . e($file_name) . "')");
    }

    // The columns here are attribute_id and sort_order, not the
    // product_attribute_id this once guessed at -- that guess made the whole
    // copy fail on a group that had any attribute at all.
    foreach ((array) db_items("SELECT attribute_id, sort_order FROM product_groups_attributes_xref WHERE product_group_id = '" . e((int) $group_id) . "'") as $attribute) {
        db(
            "INSERT INTO product_groups_attributes_xref (product_group_id, attribute_id, sort_order)
            VALUES ('" . e($new_id) . "', '" . e((int) $attribute['attribute_id']) . "', '" . e((int) $attribute['sort_order']) . "')");
    }

    // The children, under the copy rather than the original.
    $children = db_items(
        "SELECT id
        FROM product_groups
        WHERE parent_id = '" . e((int) $group_id) . "'
        ORDER BY sort_order, name");

    foreach ((array) $children as $child) {
        $copied += pg_catalog_copy_group((int) $child['id'], $new_id, $user, $depth + 1, $created);
    }

    return $copied;
}

function pg_explorer_handle($request, $user, $folders_that_user_has_access_to)
{
    $type = isset($request['type']) ? $request['type'] : '';

    // Every short link action, the listing included, is a right of roles
    // 0-2: short links sit outside the folder-based rights model, so a user
    // (role 3) is refused here rather than filtered further down.
    if ((strpos($type, 'explorer_short_link') === 0) && ($user['role'] == 3)) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    switch ($type) {

        // ── Listing ─────────────────────────────────────────────────────
        case 'explorer_list':

            // Opportunistic daily purge of expired recycle bin entries.
            pg_recycle_purge_if_due($user);

            // The flat all-files view: every file in every visible,
            // unarchived folder outside the recycle bin, folder shown per
            // row. The browse position in the session stays untouched.
            if (isset($request['view_mode']) && ($request['view_mode'] == 'all_files')) {

                // The pictures entry is the same flat view narrowed to image
                // types — the list pg_explorer_file_payload() treats as
                // images. The pages entry swaps the file list for every page
                // on the site instead.
                $images_only = (isset($request['file_filter']) && ($request['file_filter'] == 'images'));
                $pages_only = (isset($request['file_filter']) && ($request['file_filter'] == 'pages'));

                // The Files view, narrowed. Pictures and pages carry no
                // narrowing of their own.
                $scope = (isset($request['file_scope']) && in_array($request['file_scope'], pg_explorer_file_scopes(), true)) ? $request['file_scope'] : '';

                if ($images_only || $pages_only) {
                    $scope = '';
                }

                $archived_only = ($scope == 'archived');

                if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
                    $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
                }

                $view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

                if (in_array($view_type, array('grid', 'list'), true) == false) {
                    $view_type = 'grid';
                }

                // The view is the last thing this request writes to the session.
                pg_explorer_release_session();

                $map = pg_explorer_folder_map();
                $bin_id = pg_recycle_folder_id(false);

                // Folders whose files stay out of this view: the bin subtree.
                $excluded = array();

                if ($bin_id > 0) {
                    foreach ($map as $row) {
                        if (pg_explorer_is_self_or_descendant($bin_id, $row['folder_id'])) {
                            $excluded[] = (int) $row['folder_id'];
                        }
                    }
                }

                $files = array();
                $all_pages = array();

                if ($pages_only) {

                    require_once(dirname(__FILE__) . '/seo.php');

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
                    'count' => pg_recycle_ready() ? (int) db_value("SELECT COUNT(*) FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql() . ")") : 0);

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

            $folder_id = pg_explorer_resolve_folder(
                isset($request['folder_id']) ? $request['folder_id'] : ($_SESSION['software']['explorer']['folder']['folder_id'] ?? ''),
                $user,
                $folders_that_user_has_access_to);

            // Remember position and view so the screen reopens where it was.
            $_SESSION['software']['explorer']['folder']['folder_id'] = $folder_id;

            if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
                $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
            }

            $view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

            if (in_array($view_type, array('grid', 'list'), true) == false) {
                $view_type = 'grid';
            }

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

                require_once(dirname(__FILE__) . '/seo.php');

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
                $recycle['count'] = (int) db_value("SELECT COUNT(*) FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql() . ")");
                $recycle['inside'] = (($folder_id > 0) && pg_recycle_is_inside($folder_id));
            }

            $bin_entries = array();

            if ($recycle['available'] && $recycle['inside']) {
                $map_for_names = pg_explorer_folder_map();
                $rows = db_items("SELECT item_type, item_id, original_parent_id, deleted_at FROM recycle_bin");

                if ($rows) {
                    foreach ($rows as $row) {
                        $age_days = (int) floor((time() - (int) $row['deleted_at']) / 86400);
                        $origin_id = (int) $row['original_parent_id'];

                        $bin_entries[$row['item_type'] . ':' . $row['item_id']] = array(
                            'deleted_at' => (int) $row['deleted_at'],
                            'days_left' => max(0, $recycle['retention_days'] - $age_days),
                            'origin_name' => isset($map_for_names[$origin_id]) ? $map_for_names[$origin_id]['folder_name'] : '');
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
            break;

        // ── Catalog listing: one group's subgroups and its own products ──
        //
        // Three shapes come out of here.  A group id lists what is inside that
        // group; view_mode 'all_products' lists every product regardless of
        // group, which is the store's answer to the Files and Pictures views;
        // view_mode 'variant_sets' lists the groups that hold variants, the
        // same set view_products.php?mode=variant_sets prints.
        case 'explorer_catalog_list':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            // The bin empties itself once a day, claimed by whichever explorer
            // visit comes first. The store is one of those visits: an operator
            // who only ever opens this screen still gets the sweep.
            pg_recycle_purge_if_due($user);

            $view_mode = isset($request['view_mode']) ? (string) $request['view_mode'] : '';
            $group_id = (int) (isset($request['group_id']) ? $request['group_id'] : 0);

            $groups = array();
            $products = array();
            $current = null;
            $breadcrumb = array();

            if ($view_mode == 'recycled') {

                // What is in the bin, both kinds together, newest first. The
                // rows are built by the same two builders, so the grid, the
                // preview panel and the status bar need nothing new.
                if (pg_catalog_recycle_ready()) {

                    // A group that went into the bin inside another one is not
                    // listed on its own: the operator deleted the parent, and
                    // restoring the parent brings it back. Only the tops of the
                    // binned subtrees are shown, the way the folder bin shows a
                    // deleted folder rather than everything that was inside it.
                    $recycled_map = pg_catalog_group_map(true);

                    foreach ($recycled_map as $row) {

                        if (isset($recycled_map[(int) $row['parent_id']])) {
                            continue;
                        }

                        $groups[] = pg_catalog_group_item($row);
                    }

                    $rows = db_items(
                        "SELECT products.*
                        FROM products
                        WHERE products.recycled = '1'
                        ORDER BY products.name");

                    foreach ((array) $rows as $row) {
                        $products[] = pg_catalog_product_item($row);
                    }

                    pg_catalog_attach_memberships($products);
                }

            } elseif ($view_mode == 'variant_sets') {

                // A group whose display type is 'select' is not a category but
                // one product in several forms, so the store lists those on
                // their own the way view_products.php?mode=variant_sets does.
                // Same rows, same builder -- the grid needs nothing new.
                foreach (pg_catalog_group_map() as $row) {

                    if ($row['display_type'] == 'select') {
                        $groups[] = pg_catalog_group_item($row);
                    }
                }

            } elseif ($view_mode == 'all_products') {

                // The product carries the name of one group only -- the deepest
                // one it belongs to reads best in a flat list, the same choice
                // the catalog feed makes when it prints a product path.
                $rows = db_items(
                    "SELECT
                        products.*,
                        products_groups_xref.product_group,
                        product_groups.name AS group_name
                    FROM products
                    LEFT JOIN products_groups_xref ON products_groups_xref.product = products.id
                    LEFT JOIN product_groups ON product_groups.id = products_groups_xref.product_group
                    WHERE 1 = 1" . pg_catalog_live_filter('products') . "
                    GROUP BY products.id
                    ORDER BY products.name");

                foreach ((array) $rows as $row) {
                    $products[] = pg_catalog_product_item($row);
                }

                pg_catalog_attach_memberships($products);

            } else {

                $map = pg_catalog_group_map();

                // Open the root group rather than the level above it.  Listing
                // level zero put a single tile on the screen that the operator
                // had to click before seeing anything, while the folder side
                // opens the root folder itself.  A catalog with several roots
                // still gets the list, because there the choice is real.
                if ($group_id <= 0) {

                    $roots = pg_catalog_child_groups(0);

                    if (count($roots) == 1) {
                        $group_id = (int) $roots[0]['id'];
                    }
                }

                if (($group_id > 0) && (isset($map[$group_id]))) {
                    $current = pg_catalog_group_item($map[$group_id]);
                    $breadcrumb = pg_catalog_breadcrumb($group_id);
                }

                foreach (pg_catalog_child_groups($group_id) as $row) {
                    $groups[] = pg_catalog_group_item($row);
                }

                // Direct members only.  Opening the whole subtree here would
                // pile every product in the store next to the category tiles
                // that exist to lead the way to them.
                if ($group_id > 0) {

                    $rows = db_items(
                        "SELECT
                            products.*,
                            products_groups_xref.product_group,
                            products_groups_xref.sort_order
                        FROM products_groups_xref
                        INNER JOIN products ON products.id = products_groups_xref.product
                        WHERE (products_groups_xref.product_group = '" . e($group_id) . "')" . pg_catalog_live_filter('products') . "
                        ORDER BY products_groups_xref.sort_order, products.name");

                    foreach ((array) $rows as $row) {
                        $products[] = pg_catalog_product_item($row);
                    }

                    pg_catalog_attach_memberships($products);
                }
            }

            // How long each binned row has left, keyed the way the grid keys
            // its rows -- the screen calls them group and product, recycle_bin
            // calls them product_group and product.
            $bin_entries = array();

            if (($view_mode == 'recycled') && (pg_catalog_recycle_ready())) {

                $retention = pg_recycle_retention_days();

                $rows = db_items("SELECT item_type, item_id, deleted_at FROM recycle_bin WHERE item_type IN ('product_group', 'product')");

                foreach ((array) $rows as $row) {

                    $age_days = (int) floor((time() - (int) $row['deleted_at']) / 86400);
                    $kind = ($row['item_type'] == 'product_group') ? 'group' : 'product';

                    $bin_entries[$kind . ':' . (int) $row['item_id']] = array(
                        'deleted_at' => (int) $row['deleted_at'],
                        'days_left' => max(0, $retention - $age_days),
                        'origin_name' => '');
                }
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'view_mode' => $view_mode,
                'group_id' => $group_id,
                'current' => $current,
                'breadcrumb' => $breadcrumb,
                'groups' => $groups,
                'products' => $products,
                'capabilities' => array(
                    'role' => (int) $user['role'],
                    'is_manager' => ($user['role'] <= 2),
                    'show_product_images' => (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES,
                    'can_edit_catalog' => pg_catalog_access($user)),
                // The toolbar draws the bin button from this, exactly as the
                // folder listing does; the store has no retention period yet,
                // and says zero rather than a number it does not honour.
                'recycle' => array(
                    'available' => pg_catalog_recycle_ready(),
                    'folder_id' => 0,
                    'retention_days' => pg_recycle_ready() ? pg_recycle_retention_days() : 0,
                    'inside' => ($view_mode == 'recycled'),
                    'count' => pg_catalog_recycle_ready()
                        ? ((int) db_value("SELECT COUNT(*) FROM product_groups WHERE recycled = '1'")
                            + (int) db_value("SELECT COUNT(*) FROM products WHERE recycled = '1'"))
                        : 0),
                'bin_entries' => $bin_entries,
                'catalog_totals' => array(
                    'groups' => count(pg_catalog_group_map()),
                    'variant_sets' => count(array_filter(pg_catalog_group_map(), function ($row) { return ($row['display_type'] == 'select'); })),
                    'products' => (int) db_value("SELECT COUNT(*) FROM products" . (pg_catalog_recycle_ready() ? " WHERE recycled = '0'" : "")))));
            break;

        // ── Catalog tree (lazy children, same contract as explorer_tree) ──
        case 'explorer_catalog_tree':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $node_id = (int) (isset($request['node_id']) ? $request['node_id'] : 0);
            $children = array();

            foreach (pg_catalog_child_groups($node_id) as $row) {

                $id = (int) $row['id'];
                $stats = pg_catalog_group_stats($id);
                $grandchildren = pg_catalog_child_groups($id);

                $children[] = array(
                    'id' => $id,
                    'parent_id' => (int) $row['parent_id'],
                    'name' => (string) $row['name'],
                    'enabled' => ($row['enabled'] == '1'),
                    'display_type' => (string) $row['display_type'],
                    'catalog_role' => pg_catalog_group_role($row['display_type']),
                    'count' => $stats['products'],
                    'has_children' => (count($grandchildren) > 0),
                    'is_root' => ((int) $row['parent_id'] == 0),
                    'empty' => ((count($grandchildren) == 0) && ($stats['products'] == 0)),
                    'can_edit' => true);
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'node_id' => $node_id,
                'children' => $children));
            break;

        // ── Create a product group where you are standing ────────────────
        //
        // The same shape as creating a folder: a row appears with a working
        // name, the screen puts the cursor in it, and the full edit screen is
        // one click away for everything else the group can carry.
        case 'explorer_catalog_create_group':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $parent_id = (int) (isset($request['group_id']) ? $request['group_id'] : 0);
            $map = pg_catalog_group_map();

            if (($parent_id <= 0) || (isset($map[$parent_id]) == false)) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $name = trim(isset($request['name']) ? $request['name'] : '');

            if ($name == '') {
                respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
            }

            // The address is derived the way add_product_group.php derives it,
            // and made unique the same way, so a group created here and one
            // created there are the same kind of row.
            //
            // The name is written to short_description as well. On the site a
            // product group is printed by its short description -- the name is
            // the operator's own handle for it and never reaches a visitor --
            // so a group created with only a name would be nameless out there.
            $address_name = prepare_catalog_item_address_name($name);

            if (db_value("SELECT id FROM product_groups WHERE address_name = '" . e($address_name) . "'")) {
                $address_name = prepare_catalog_item_address_name($address_name . '[' . get_duplicate_catalog_item_address_name_number($address_name) . ']');
            }

            $sort_order = (int) db_value(
                "SELECT MAX(sort_order)
                FROM product_groups
                WHERE parent_id = '" . e($parent_id) . "'");

            db(
                "INSERT INTO product_groups (
                    name,
                    enabled,
                    parent_id,
                    sort_order,
                    display_type,
                    address_name,
                    short_description,
                    full_description,
                    details,
                    code,
                    keywords,
                    image_name,
                    title,
                    meta_description,
                    meta_keywords,
                    user,
                    timestamp)
                VALUES (
                    '" . e($name) . "',
                    '1',
                    '" . e($parent_id) . "',
                    '" . e($sort_order + 1) . "',
                    'browse',
                    '" . e($address_name) . "',
                    '" . e($name) . "',
                    '', '', '', '', '', '', '', '',
                    '" . e($user['id']) . "',
                    UNIX_TIMESTAMP())");

            $group_id = mysqli_insert_id(db::$con);

            log_activity(lang(array('string' => 'The product group, {var:1}, was added.', 'vars' => h($name))), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'group' => array('id' => (int) $group_id, 'name' => $name),
                'message' => lang(array('string' => 'The product group, {var:1}, was added.', 'vars' => h($name)))));
            break;

        // ── Rename a group or a product ──────────────────────────────────
        //
        // The name only. address_name is the row's address on the site, and a
        // rename that silently moved a live URL would break links the operator
        // never touched; the edit screen is where an address is changed on
        // purpose.
        case 'explorer_catalog_rename':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $item_kind = isset($request['item_kind']) ? (string) $request['item_kind'] : '';
            $item_id = (int) (isset($request['item_id']) ? $request['item_id'] : 0);
            $name = trim(isset($request['name']) ? $request['name'] : '');

            if (($item_id <= 0) || ($name == '')) {
                respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
            }

            if ($item_kind == 'group') {

                // The short description is what the site prints, so a rename has
                // to reach it -- otherwise the group the operator just renamed
                // keeps its old name everywhere a visitor looks.
                //
                // Only while the two still agree, though: a short description
                // that was written by hand says something the name does not, and
                // a rename here must not overwrite it. Creation sets them the
                // same, so the create-then-rename that this screen does lands
                // correctly, and an edited one is left alone.
                $group_row = db_item("SELECT name, short_description FROM product_groups WHERE id = '" . e($item_id) . "'");

                $sql_short_description = '';

                if ((is_array($group_row))
                    && ((trim((string) $group_row['short_description']) == '')
                        || (trim((string) $group_row['short_description']) == trim((string) $group_row['name'])))) {

                    $sql_short_description = "short_description = '" . e($name) . "',";
                }

                db("UPDATE product_groups SET name = '" . e($name) . "', " . $sql_short_description . " timestamp = UNIX_TIMESTAMP() WHERE id = '" . e($item_id) . "'");

            } elseif ($item_kind == 'product') {

                db("UPDATE products SET name = '" . e($name) . "', timestamp = UNIX_TIMESTAMP() WHERE id = '" . e($item_id) . "'");

            } else {

                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'name' => $name,
                'message' => lang('The name was changed.')));
            break;

        // ── Cut / copy and paste ─────────────────────────────────────────
        //
        // The two kinds mean different things here, and the difference is the
        // whole reason this is not the folder paste:
        //
        //   A group is somewhere. Cutting one and pasting it changes its parent;
        //   copying one duplicates it, and everything under it, into the target.
        //
        //   A product is in several places at once. Cutting one and pasting it
        //   moves its membership out of the group it was shown in and into the
        //   target; copying one adds a membership and leaves the first alone.
        //   Nothing duplicates the product itself -- two categories listing the
        //   same product is what the catalog is built to do.
        case 'explorer_catalog_paste':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $target_id = (int) (isset($request['target_group_id']) ? $request['target_group_id'] : 0);
            $source_id = (int) (isset($request['source_group_id']) ? $request['source_group_id'] : 0);
            $mode = ((isset($request['mode'])) && ($request['mode'] == 'cut')) ? 'cut' : 'copy';
            $items = isset($request['items']) ? $request['items'] : array();

            $map = pg_catalog_group_map();

            if (($target_id <= 0) || (isset($map[$target_id]) == false)) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            if ((is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $moved = 0;

            // What it would take to put this back, built as we go and handed to
            // the screen so Ctrl+Z can undo a paste with the endpoints that are
            // already here rather than a second set that could drift from them.
            $undo_steps = array();

            // Search pages that cover any group involved, read before the tree
            // moves. Read again afterwards, because the destination side only
            // becomes involved once the group is actually under it.
            $tag_cloud_groups = array($target_id);

            foreach ($items as $item) {

                if ((isset($item['kind'])) && ($item['kind'] == 'group')) {
                    $tag_cloud_groups[] = (int) (isset($item['id']) ? $item['id'] : 0);
                }
            }

            $tag_cloud_pages = pg_catalog_search_pages_for_groups($tag_cloud_groups);

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);
                $item_kind = isset($item['kind']) ? (string) $item['kind'] : '';

                if ($item_id <= 0) {
                    continue;
                }

                if ($item_kind == 'group') {

                    if (isset($map[$item_id]) == false) {
                        continue;
                    }

                    if ($mode == 'cut') {

                        // A group cannot be put inside itself or inside one of
                        // its own descendants: the tree would close into a ring
                        // and every walk over it would run until its guard.
                        if (($item_id == $target_id) || (in_array($target_id, pg_catalog_subtree_ids($item_id)))) {
                            respond(array('status' => 'error', 'message' => lang('A product group cannot be moved into itself.')));
                        }

                        if ((int) $map[$item_id]['parent_id'] == 0) {
                            respond(array('status' => 'error', 'message' => lang('This product group could not be deleted because it is the root product group.')));
                        }

                        $undo_steps[] = array(
                            'type' => 'paste',
                            'mode' => 'cut',
                            'target_group_id' => (int) $map[$item_id]['parent_id'],
                            'source_group_id' => $target_id,
                            'items' => array(array('kind' => 'group', 'id' => $item_id)));

                        db("UPDATE product_groups SET parent_id = '" . e($target_id) . "' WHERE id = '" . e($item_id) . "'");

                        $moved++;

                    } else {

                        $created = array();
                        $moved += pg_catalog_copy_group($item_id, $target_id, $user, 0, $created);

                        // A copied group is undone by putting the copies in the
                        // bin, deepest first, so a parent never goes before the
                        // children it still holds.
                        foreach (array_reverse($created) as $created_id) {
                            $undo_steps[] = array(
                                'type' => 'recycle',
                                'items' => array(array('kind' => 'group', 'id' => (int) $created_id)));
                        }
                    }

                } elseif ($item_kind == 'product') {

                    // Already here: nothing to add, and a cut from the same
                    // group would only remove and re-add the same row.
                    $exists = db_value(
                        "SELECT COUNT(*)
                        FROM products_groups_xref
                        WHERE (product = '" . e($item_id) . "') AND (product_group = '" . e($target_id) . "')");

                    if (($mode == 'cut') && ($source_id > 0) && ($source_id != $target_id)) {

                        db(
                            "DELETE FROM products_groups_xref
                            WHERE (product = '" . e($item_id) . "') AND (product_group = '" . e($source_id) . "')");

                        $undo_steps[] = array(
                            'type' => 'paste',
                            'mode' => 'cut',
                            'target_group_id' => $source_id,
                            'source_group_id' => $target_id,
                            'items' => array(array('kind' => 'product', 'id' => $item_id)));
                    }

                    if ($exists == 0) {

                        $sort_order = (int) db_value(
                            "SELECT MAX(sort_order)
                            FROM products_groups_xref
                            WHERE product_group = '" . e($target_id) . "'");

                        db(
                            "INSERT INTO products_groups_xref (product, product_group, sort_order)
                            VALUES ('" . e($item_id) . "', '" . e($target_id) . "', '" . e($sort_order + 1) . "')");

                        // A product that was only listed here is undone by
                        // un-listing it; nothing was duplicated, so nothing has
                        // to be deleted.
                        if ($mode != 'cut') {
                            $undo_steps[] = array(
                                'type' => 'membership_remove',
                                'product_id' => $item_id,
                                'group_id' => $target_id);
                        }
                    }

                    $moved++;
                }
            }

            if ($moved > 0) {
                pg_catalog_rebuild_search_tag_clouds(
                    $tag_cloud_pages + pg_catalog_search_pages_for_groups($tag_cloud_groups));
            }

            respond(array(
                'status' => ($moved > 0) ? 'success' : 'error',
                'request' => $type,
                'count' => $moved,
                'undo_steps' => $undo_steps,
                'message' => ($moved > 0)
                    ? (($mode == 'cut')
                        ? lang(array('string' => '{var:1} item(s) were moved.', 'vars' => $moved))
                        : lang(array('string' => '{var:1} item(s) were pasted.', 'vars' => $moved)))
                    : lang('Sorry, we could not accept your request.')));
            break;
        // ── Move a group or a product to the recycle bin ─────────────────
        //
        // Binning sets the flag AND switches the row off, keeping what it was
        // before. The flag hides it from these screens; 'enabled' is what the
        // storefront already reads, and relying on it means a binned product
        // cannot keep selling because one query somewhere forgot the new
        // column. Restoring puts the old value back rather than publishing
        // something that was switched off before it was deleted.
        case 'explorer_catalog_recycle':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            if (pg_catalog_recycle_ready() == false) {
                respond(array('status' => 'error', 'message' => lang('The Recycle Bin is not available yet. Please run the upgrade.')));
            }

            $items = isset($request['items']) ? $request['items'] : array();

            if ((is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $binned = 0;

            // A refusal takes that one row out of the batch rather than the
            // batch out of the operator's hands: selecting a whole grid and
            // pressing Delete must not be undone by one group that still holds
            // groups. What was refused is said afterwards, once per reason.
            $errors = array();

            $map = pg_catalog_group_map();

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);
                $item_kind = isset($item['kind']) ? (string) $item['kind'] : '';

                if ($item_id <= 0) {
                    continue;
                }

                if ($item_kind == 'group') {

                    if (isset($map[$item_id]) == false) {
                        continue;
                    }

                    // The root is what everything else hangs from; edit_product_group.php
                    // refuses to delete it too.
                    if ((int) $map[$item_id]['parent_id'] == 0) {
                        $errors[] = lang('This product group could not be deleted because it is the root product group.');
                        continue;
                    }

                    // A group goes to the bin with everything under it, the
                    // way a folder does. Refusing until the operator had emptied
                    // it by hand was the permanent-delete rule of
                    // edit_product_group.php, and that rule is right there --
                    // where the delete cannot be taken back. This one can.
                    //
                    // The children have to travel with it either way: a group
                    // whose parent is hidden but which is still on the site is
                    // an orphan the tree cannot draw.
                    $subtree = pg_catalog_subtree_ids($item_id);

                    // "AND recycled = '0'" is not decoration. Select a group and
                    // one of its children and the loop reaches the child after
                    // the parent already binned it; without this the second pass
                    // would write recycled_enabled = enabled with enabled
                    // already forced to 0, and the child would come back out of
                    // the bin unpublished no matter what it was.
                    db(
                        "UPDATE product_groups
                        SET recycled = '1',
                            recycled_enabled = enabled,
                            enabled = '0'
                        WHERE (id IN (" . implode(',', array_map('intval', $subtree)) . ")) AND (recycled = '0')");

                    // One restore record, for the group that was actually
                    // deleted. The bin lists it alone and restoring it brings
                    // the whole subtree back with it. Replaced rather than added
                    // to, so a group that arrives here twice in one batch does
                    // not leave two records behind.
                    db("DELETE FROM recycle_bin WHERE (item_type = 'product_group') AND (item_id = '" . e($item_id) . "')");

                    db(
                        "INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
                        VALUES ('product_group', '" . e($item_id) . "', '" . e((int) $map[$item_id]['parent_id']) . "', UNIX_TIMESTAMP(), '" . e($user['id']) . "')");

                    log_activity(lang(array('string' => 'The product group, {var:1}, was moved to the Recycle Bin.', 'vars' => h($map[$item_id]['name']))), $_SESSION['sessionusername']);

                    $binned++;

                } elseif ($item_kind == 'product') {

                    $product = db_item("SELECT id, name FROM products WHERE id = '" . e($item_id) . "'");

                    if (is_array($product) == false) {
                        continue;
                    }

                    db(
                        "UPDATE products
                        SET recycled = '1',
                            recycled_enabled = enabled,
                            enabled = '0'
                        WHERE id = '" . e($item_id) . "'");

                    db(
                        "INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
                        VALUES ('product', '" . e($item_id) . "', '0', UNIX_TIMESTAMP(), '" . e($user['id']) . "')");

                    log_activity(lang(array('string' => 'The product, {var:1}, was moved to the Recycle Bin.', 'vars' => h($product['name']))), $_SESSION['sessionusername']);

                    $binned++;
                }
            }

            $message = ($binned > 0)
                ? lang(array('string' => '{var:1} item(s) were moved to the Recycle Bin.', 'vars' => $binned))
                : lang('Sorry, we could not accept your request.');

            if (count($errors) > 0) {
                $message = ($binned > 0)
                    ? ($message . ' ' . implode(' ', array_unique($errors)))
                    : implode(' ', array_unique($errors));
            }

            respond(array(
                'status' => ($binned > 0) ? 'success' : 'error',
                'request' => $type,
                'count' => $binned,
                'skipped' => count($errors),
                'message' => $message));
            break;

        // ── Put one back ────────────────────────────────────────────────
        case 'explorer_catalog_restore':

            if ((pg_catalog_access($user) == false) || (pg_catalog_recycle_ready() == false)) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $items = isset($request['items']) ? $request['items'] : array();
            $restored = 0;

            foreach ((array) $items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);
                $item_kind = isset($item['kind']) ? (string) $item['kind'] : '';

                if ($item_id <= 0) {
                    continue;
                }

                if ($item_kind == 'group') {

                    // Everything that went in with it comes back out with it.
                    $subtree = pg_catalog_recycled_subtree_ids($item_id);

                    if (count($subtree) == 0) {
                        continue;
                    }

                    $sql_ids = implode(',', array_map('intval', $subtree));

                    db(
                        "UPDATE product_groups
                        SET recycled = '0',
                            enabled = recycled_enabled
                        WHERE id IN (" . $sql_ids . ")");

                    // The descendants carry no record of their own, but one may
                    // have been binned in its own right before its parent was;
                    // it is out of the bin now either way, so the stale record
                    // goes with it.
                    db("DELETE FROM recycle_bin WHERE (item_type = 'product_group') AND (item_id IN (" . $sql_ids . "))");

                    $restored++;

                } elseif ($item_kind == 'product') {

                    db(
                        "UPDATE products
                        SET recycled = '0',
                            enabled = recycled_enabled
                        WHERE id = '" . e($item_id) . "'");

                    db("DELETE FROM recycle_bin WHERE (item_type = 'product') AND (item_id = '" . e($item_id) . "')");

                    $restored++;
                }
            }

            respond(array(
                'status' => ($restored > 0) ? 'success' : 'error',
                'request' => $type,
                'count' => $restored,
                'message' => ($restored > 0)
                    ? lang(array('string' => '{var:1} item(s) were restored.', 'vars' => $restored))
                    : lang('Sorry, we could not accept your request.')));
            break;

        // ── Empty the catalog bin, or part of it ─────────────────────────
        //
        // The last step, and the only one that cannot be taken back. It runs
        // through the same two functions the edit screens delete with -- the
        // group and product deletes each touch a dozen tables, and a second
        // copy of that list here would drift from the first the day either
        // screen gains a table.
        //
        // A group is purged without its products: binning a group never binned
        // them, so they are still on sale in whatever other groups list them.
        // Only what is actually in the bin can be purged; a live id sent here
        // is skipped rather than obeyed.
        case 'explorer_catalog_purge':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            if (pg_catalog_recycle_ready() == false) {
                respond(array('status' => 'error', 'message' => lang('The Recycle Bin is not available yet. Please run the upgrade.')));
            }

            $purge_all = ((isset($request['all'])) && ($request['all'] == true));
            $items = isset($request['items']) ? $request['items'] : array();

            if ($purge_all == true) {

                $items = array();

                foreach (db_items("SELECT id FROM product_groups WHERE recycled = '1'") as $row) {
                    $items[] = array('kind' => 'group', 'id' => (int) $row['id']);
                }

                foreach (db_items("SELECT id FROM products WHERE recycled = '1'") as $row) {
                    $items[] = array('kind' => 'product', 'id' => (int) $row['id']);
                }
            }

            if ((is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $purged = 0;

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);
                $item_kind = isset($item['kind']) ? (string) $item['kind'] : '';

                if ($item_id <= 0) {
                    continue;
                }

                // The same call the daily purge makes, so emptying the bin by
                // hand and letting it expire cannot come apart.
                $item_type = ($item_kind == 'group') ? 'product_group' : (($item_kind == 'product') ? 'product' : '');

                if ($item_type == '') {
                    continue;
                }

                // Only what is really in the bin is counted. The delete itself
                // refuses a live row either way, but without this check the
                // screen would report a row it never touched as deleted -- the
                // sweep is allowed to clear a stale bin entry quietly, an
                // operator pressing Delete is not told a lie about it.
                $binned_row = ($item_type == 'product_group')
                    ? db_item("SELECT id FROM product_groups WHERE (id = '" . e($item_id) . "') AND (recycled = '1')")
                    : db_item("SELECT id FROM products WHERE (id = '" . e($item_id) . "') AND (recycled = '1')");

                if (is_array($binned_row) == false) {
                    continue;
                }

                $outcome = pg_recycle_hard_delete_item($item_type, $item_id, $user);

                if ($outcome['status'] == 'success') {
                    $purged++;
                }
            }

            respond(array(
                'status' => ($purged > 0) ? 'success' : 'error',
                'request' => $type,
                'count' => $purged,
                'message' => ($purged > 0)
                    ? lang(array('string' => '{var:1} item(s) were permanently deleted', 'vars' => $purged))
                    : lang('Sorry, we could not accept your request.')));
            break;

        // ── Publish or unpublish groups and products ─────────────────────
        //
        // 'enabled' is the gate the whole storefront already reads, so this is
        // the same switch the edit screens carry -- reachable from the grid and
        // the tree without opening a screen to flip one field.
        //
        // A row in the bin is left alone: binning switched it off and kept what
        // it was, and turning it back on here would put a deleted product on
        // sale while it still sits in the bin.
        case 'explorer_catalog_enable':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $enabled = ((isset($request['enabled'])) && ($request['enabled'])) ? '1' : '0';
            $items = isset($request['items']) ? $request['items'] : array();

            if ((is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $changed = 0;
            $map = pg_catalog_group_map();

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);
                $item_kind = isset($item['kind']) ? (string) $item['kind'] : '';

                if ($item_id <= 0) {
                    continue;
                }

                if ($item_kind == 'group') {

                    // The map holds live groups only, so a binned one is not in
                    // it and is skipped without a second query.
                    if (isset($map[$item_id]) == false) {
                        continue;
                    }

                    db(
                        "UPDATE product_groups
                        SET enabled = '" . e($enabled) . "',
                            timestamp = UNIX_TIMESTAMP(),
                            user = '" . e($user['id']) . "'
                        WHERE id = '" . e($item_id) . "'" . pg_catalog_live_filter('product_groups'));

                    log_activity(lang(array('string' => 'product group ({var:1}) was updated', 'vars' => array(h($map[$item_id]['name'])))), $_SESSION['sessionusername']);

                    $changed++;

                } elseif ($item_kind == 'product') {

                    $product = db_item("SELECT id, name FROM products WHERE (id = '" . e($item_id) . "')" . pg_catalog_live_filter('products'));

                    if (is_array($product) == false) {
                        continue;
                    }

                    db(
                        "UPDATE products
                        SET enabled = '" . e($enabled) . "',
                            timestamp = UNIX_TIMESTAMP(),
                            user = '" . e($user['id']) . "'
                        WHERE id = '" . e($item_id) . "'");

                    log_activity(lang(array('string' => 'product ({var:1}) was updated', 'vars' => array(h($product['name'])))), $_SESSION['sessionusername']);

                    $changed++;
                }
            }

            respond(array(
                'status' => ($changed > 0) ? 'success' : 'error',
                'request' => $type,
                'count' => $changed,
                'message' => ($changed > 0)
                    ? (($enabled == '1')
                        ? lang(array('string' => '{var:1} item(s) were published.', 'vars' => $changed))
                        : lang(array('string' => '{var:1} item(s) were unpublished.', 'vars' => $changed)))
                    : lang('Sorry, we could not accept your request.')));
            break;

        // ── Bulk product edit: the choices the panel can offer ──────────
        //
        // Which fields the panel draws depends on what this installation
        // actually has: shipping zones exist only with shipping switched on,
        // barcodes only with the barcode feature enabled. Asking here rather
        // than guessing in the browser keeps one answer to "is this on".
        case 'explorer_bulk_product_options':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $bulk_zones = array();
            $bulk_shipping = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING);

            if ($bulk_shipping) {
                foreach ((array) db_items("SELECT id, name FROM zones ORDER BY name") as $zone_row) {
                    $bulk_zones[] = array('id' => (int) $zone_row['id'], 'name' => (string) $zone_row['name']);
                }
            }

            // Every live group, in tree order with a depth, so the two group
            // pickers read as the tree the operator is standing in rather than
            // as a flat alphabetical list.
            $bulk_groups = array();
            $bulk_group_map = pg_catalog_group_map();

            $collect_groups = function ($parent_id, $depth) use (&$collect_groups, &$bulk_groups, $bulk_group_map) {

                if ($depth > 50) {
                    return;
                }

                foreach ($bulk_group_map as $group_row) {

                    if (((int) $group_row['parent_id']) !== $parent_id) {
                        continue;
                    }

                    $bulk_groups[] = array(
                        'id' => (int) $group_row['id'],
                        'name' => (string) $group_row['name'],
                        'depth' => $depth);

                    $collect_groups((int) $group_row['id'], $depth + 1);
                }
            };

            $collect_groups(0, 0);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'shipping' => $bulk_shipping,
                'zones' => $bulk_zones,
                'barcode_ready' => (defined('BARCODE_ENABLED') && BARCODE_ENABLED),
                // Decoded on the way out: the setting is stored HTML-encoded
                // ("&#8378;"), and the panel escapes what it prints, so a
                // symbol sent as an entity would be shown as its own source.
                'currency_symbol' => html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES, 'UTF-8'),
                'groups' => $bulk_groups));
            break;

        // ── Bulk product edit: apply ────────────────────────────────────
        //
        // What edit_products.php did to a checked list, on the selection.
        // Every field is optional; an untouched one is not written.
        //
        // Two rules carried over from that screen because they are about
        // money rather than about code:
        //
        //   A decrease that would take a price to zero or below is refused for
        //   that product rather than clamped -- a zero-priced article in a shop
        //   is a giveaway, and silently making one is worse than skipping it.
        //   The response says how many were left alone.
        //
        //   Prices are integer kuruş, and a percentage produces fractions of
        //   one. round() rather than a cast: the cast truncates, and the card
        //   and the feed then disagree by a kuruş.
        case 'explorer_products_bulk_edit':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $items = isset($request['items']) ? $request['items'] : array();
            $set = (isset($request['set']) && is_array($request['set'])) ? $request['set'] : array();

            if ((is_array($items) == false) || (count($items) == 0) || (count($set) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $product_ids = array();

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($item_id <= 0) {
                    continue;
                }

                // Only live products: a binned one is restored before it is
                // edited, the same way the rest of this screen treats the bin.
                $product_row = db_item(
                    "SELECT id, name, price
                    FROM products
                    WHERE id = '" . e($item_id) . "'" . pg_catalog_live_filter('products'));

                if ($product_row) {
                    $product_ids[$item_id] = $product_row;
                }
            }

            if (count($product_ids) == 0) {
                respond(array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.')));
            }

            // What the panel asked for, read once.
            $bulk_enabled = (isset($set['enabled']) && in_array((string) $set['enabled'], array('0', '1'), true)) ? (string) $set['enabled'] : '';
            $bulk_price_method = isset($set['price_method']) ? (string) $set['price_method'] : '';
            $bulk_price_value = isset($set['price_value']) ? str_replace(',', '.', trim((string) $set['price_value'])) : '';
            $bulk_inventory = (isset($set['inventory']) && in_array((string) $set['inventory'], array('0', '1'), true)) ? (string) $set['inventory'] : '';
            $bulk_quantity_mode = isset($set['quantity_mode']) ? (string) $set['quantity_mode'] : '';
            $bulk_quantity_value = isset($set['quantity_value']) ? trim((string) $set['quantity_value']) : '';
            $bulk_tax_method = isset($set['tax_method']) ? (string) $set['tax_method'] : '';
            $bulk_allow_zones = (isset($set['allow_zones']) && is_array($set['allow_zones'])) ? $set['allow_zones'] : array();
            $bulk_disallow_zones = (isset($set['disallow_zones']) && is_array($set['disallow_zones'])) ? $set['disallow_zones'] : array();
            $bulk_add_group = (int) (isset($set['add_group']) ? $set['add_group'] : 0);
            $bulk_remove_group = (int) (isset($set['remove_group']) ? $set['remove_group'] : 0);
            $bulk_barcodes = (isset($set['assign_barcodes']) && ($set['assign_barcodes'] == '1'));

            if (in_array($bulk_price_method, array('increase', 'decrease', 'increase_percent', 'decrease_percent'), true) == false) {
                $bulk_price_method = '';
            }

            if ($bulk_price_method !== '') {

                if (($bulk_price_value === '') || (is_numeric($bulk_price_value) == false) || ((float) $bulk_price_value <= 0)) {
                    respond(array('status' => 'error', 'message' => lang('Enter an amount for the price change.')));
                }

                $bulk_price_value = (float) $bulk_price_value;

                // A money amount is typed in the currency and stored in kuruş;
                // a percentage is a percentage and is not converted.
                if (($bulk_price_method === 'increase') || ($bulk_price_method === 'decrease')) {
                    $bulk_price_value = $bulk_price_value * 100;
                }
            }

            if (in_array($bulk_quantity_mode, array('value', 'increase', 'decrease'), true) == false) {
                $bulk_quantity_mode = '';
            }

            if ($bulk_quantity_mode !== '') {

                if (($bulk_quantity_value === '') || (is_numeric($bulk_quantity_value) == false)) {
                    respond(array('status' => 'error', 'message' => lang('Enter an inventory quantity.')));
                }

                $bulk_quantity_value = (int) $bulk_quantity_value;
            }

            $bulk_tax_sql = '';

            if ($bulk_tax_method === 'zone') {

                // NULL rather than zero: "follow the zone" and "zero-rated"
                // are different statements about an article.
                $bulk_tax_sql = "tax_rate = NULL,";

            } elseif ($bulk_tax_method === 'set') {

                $bulk_tax_rate = parse_tax_rate(isset($set['tax_value']) ? $set['tax_value'] : '');

                if ($bulk_tax_rate === NULL) {
                    // Asking to set a rate and giving none is a mistake, not an
                    // instruction to clear: clearing has its own option.
                    respond(array('status' => 'error', 'message' => lang('Enter a tax rate, or choose to follow the tax zone.')));
                }

                $bulk_tax_sql = "tax_rate = '" . e($bulk_tax_rate) . "',";
            }

            if ($bulk_remove_group > 0) {
                // Taking a product out of every group it is in would leave it
                // reachable only from All Products, which no operator means by
                // "remove from this group".
                $bulk_remove_group = isset(pg_catalog_group_map()[$bulk_remove_group]) ? $bulk_remove_group : 0;
            }

            if (($bulk_add_group > 0) && (isset(pg_catalog_group_map()[$bulk_add_group]) == false)) {
                $bulk_add_group = 0;
            }

            $anything =
                ($bulk_enabled !== '')
                || ($bulk_price_method !== '')
                || ($bulk_inventory !== '')
                || ($bulk_quantity_mode !== '')
                || ($bulk_tax_sql !== '')
                || (count($bulk_allow_zones) > 0)
                || (count($bulk_disallow_zones) > 0)
                || ($bulk_add_group > 0)
                || ($bulk_remove_group > 0)
                || $bulk_barcodes;

            if ($anything == false) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $updated = 0;
            $price_changed = 0;
            $price_skipped = 0;
            $barcodes_assigned = 0;
            $notes = array();

            foreach ($product_ids as $product_id => $product_row) {

                $sql_parts = array();

                if ($bulk_enabled !== '') {

                    // The tag cloud is rebuilt from the product's keywords when
                    // it goes on sale and dropped when it comes off, which is
                    // what edit_products.php does and what the storefront's
                    // keyword cloud reads.
                    db("DELETE FROM tag_cloud_keywords WHERE (item_id = '" . e($product_id) . "') AND (item_type = 'product')");

                    if (
                        ($bulk_enabled === '1')
                        && (db_value("SELECT COUNT(*) FROM tag_cloud_keywords_xref WHERE (item_id = '" . e($product_id) . "') AND (item_type = 'product')") > 0)
                    ) {
                        $keywords = explode(',', (string) db_value("SELECT keywords FROM products WHERE id = '" . e($product_id) . "'"));
                        $keywords = array_unique(array_filter(array_map('trim', $keywords), 'strlen'));

                        foreach ($keywords as $keyword) {
                            db(
                                "INSERT INTO tag_cloud_keywords (keyword, item_id, item_type)
                                VALUES ('" . e($keyword) . "', '" . e($product_id) . "', 'product')");
                        }
                    }

                    $sql_parts[] = "enabled = '" . e($bulk_enabled) . "'";
                }

                if ($bulk_price_method !== '') {

                    $old_price = (int) $product_row['price'];
                    $new_price = $old_price;

                    if ($bulk_price_method === 'increase') {
                        $new_price = $old_price + $bulk_price_value;

                    } elseif ($bulk_price_method === 'decrease') {
                        $new_price = $old_price - $bulk_price_value;

                    } elseif ($bulk_price_method === 'increase_percent') {
                        $new_price = ((100 + $bulk_price_value) / 100) * $old_price;

                    } elseif ($bulk_price_method === 'decrease_percent') {
                        $new_price = ((100 - $bulk_price_value) / 100) * $old_price;
                    }

                    $new_price = (int) round($new_price);

                    if ($new_price <= 0) {
                        $price_skipped++;
                    } else {
                        $sql_parts[] = "price = '" . e($new_price) . "'";
                        $price_changed++;
                    }
                }

                $new_quantity = null;

                if ($bulk_quantity_mode !== '') {

                    $current_quantity = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . e($product_id) . "'");

                    if ($bulk_quantity_mode === 'value') {
                        $new_quantity = $bulk_quantity_value;

                    } elseif ($bulk_quantity_mode === 'increase') {
                        $new_quantity = $current_quantity + $bulk_quantity_value;

                    } else {
                        $new_quantity = $current_quantity - $bulk_quantity_value;
                    }

                    // Stock does not go negative: a shelf holds none or some.
                    if ($new_quantity < 0) {
                        $new_quantity = 0;
                    }

                    $sql_parts[] = "inventory_quantity = '" . e($new_quantity) . "'";
                }

                if ($bulk_inventory !== '') {
                    $sql_parts[] = "inventory = '" . e($bulk_inventory) . "'";
                }

                // Out of stock is a consequence, not a field the panel offers:
                // stock tracking switched off means nothing can be out of it,
                // and a quantity above zero means this one no longer is.
                if (($bulk_inventory === '0') || (($new_quantity !== null) && ($new_quantity > 0))) {
                    $sql_parts[] = "out_of_stock = '0'";
                }

                foreach ($bulk_allow_zones as $zone_id) {

                    $zone_id = (int) $zone_id;

                    if ($zone_id <= 0) {
                        continue;
                    }

                    if (db_value("SELECT COUNT(*) FROM products_zones_xref WHERE (product_id = '" . e($product_id) . "') AND (zone_id = '" . e($zone_id) . "')") == 0) {
                        db("INSERT INTO products_zones_xref (product_id, zone_id) VALUES ('" . e($product_id) . "', '" . e($zone_id) . "')");
                    }
                }

                foreach ($bulk_disallow_zones as $zone_id) {

                    $zone_id = (int) $zone_id;

                    if ($zone_id <= 0) {
                        continue;
                    }

                    db("DELETE FROM products_zones_xref WHERE (product_id = '" . e($product_id) . "') AND (zone_id = '" . e($zone_id) . "')");
                }

                // A product sits in as many groups as it likes, so adding is an
                // insert if it is not already there and removing takes it out
                // of that one group only -- it keeps selling and keeps the
                // others, which is what the row menu's "remove from group"
                // already means here.
                if ($bulk_add_group > 0) {

                    if (db_value("SELECT COUNT(*) FROM products_groups_xref WHERE (product = '" . e($product_id) . "') AND (product_group = '" . e($bulk_add_group) . "')") == 0) {

                        $next_order = (int) db_value("SELECT MAX(sort_order) FROM products_groups_xref WHERE product_group = '" . e($bulk_add_group) . "'") + 1;

                        db(
                            "INSERT INTO products_groups_xref (product, product_group, sort_order)
                            VALUES ('" . e($product_id) . "', '" . e($bulk_add_group) . "', '" . e($next_order) . "')");
                    }
                }

                if ($bulk_remove_group > 0) {
                    db(
                        "DELETE FROM products_groups_xref
                        WHERE (product = '" . e($product_id) . "') AND (product_group = '" . e($bulk_remove_group) . "')");
                }

                if ($bulk_barcodes) {
                    // Skips a product that already carries one, and says so by
                    // returning nothing: relabelling an article that is already
                    // on a shelf is not what "assign barcodes" means.
                    if (pg_assign_product_barcode($product_id) !== '') {
                        $barcodes_assigned++;
                    }
                }

                if ((count($sql_parts) > 0) || ($bulk_tax_sql !== '')) {

                    db(
                        "UPDATE products
                        SET
                            " . $bulk_tax_sql . "
                            " . implode(",\n                            ", $sql_parts) . (count($sql_parts) > 0 ? "," : "") . "
                            timestamp = UNIX_TIMESTAMP(),
                            user = '" . e($user['id']) . "'
                        WHERE id = '" . e($product_id) . "'");
                }

                // Whatever wrote a price or a stock figure has to tell the
                // marketplaces, and this panel writes both. edit_products.php
                // -- the screen this one replaced -- has always done it; the
                // line did not come across, so a bulk change was the one way
                // to move a price that never reached n11.
                if (
                    (($bulk_price_method !== '') || ($bulk_quantity_mode !== '') || ($bulk_inventory !== ''))
                    && function_exists('pg_marketplace_product_changed')
                ) {
                    pg_marketplace_product_changed($product_id);
                }

                $updated++;
            }

            // What was actually done, in the operator's words rather than a
            // bare count: a run that changed the price and the stock of eleven
            // products should say so.
            if ($bulk_enabled !== '') {
                $notes[] = ($bulk_enabled === '1') ? lang('published') : lang('unpublished');
            }

            if ($price_changed > 0) {
                $notes[] = lang('price changed');
            }

            if ($bulk_inventory !== '') {
                $notes[] = ($bulk_inventory === '1') ? lang('stock tracking on') : lang('stock tracking off');
            }

            if ($bulk_quantity_mode !== '') {
                $notes[] = lang('stock quantity changed');
            }

            if ($bulk_tax_sql !== '') {
                $notes[] = ($bulk_tax_method === 'zone') ? lang('tax rate cleared') : lang('tax rate changed');
            }

            if ((count($bulk_allow_zones) > 0) || (count($bulk_disallow_zones) > 0)) {
                $notes[] = lang('shipping zones changed');
            }

            if ($bulk_add_group > 0) {
                $notes[] = lang('added to a product group');
            }

            if ($bulk_remove_group > 0) {
                $notes[] = lang('removed from a product group');
            }

            $message = lang(array(
                'string' => '{var:1} products were updated ({var:2}).',
                'vars' => array($updated, implode(', ', $notes))));

            if (count($notes) == 0) {
                $message = lang(array('string' => '{var:1} products were updated', 'vars' => $updated));
            }

            // Everything below is appended as its own sentence, so the count
            // has to end like one.
            if (mb_substr($message, -1) !== '.') {
                $message .= '.';
            }

            if ($bulk_barcodes) {
                $message .= ' ' . lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($barcodes_assigned)));
            }

            if ($price_skipped > 0) {
                $message .= ' ' . lang(array(
                    'string' => 'The price was left alone on {var:1} of them, because the change would have taken it to zero or below.',
                    'vars' => $price_skipped));
            }

            log_activity($message, $_SESSION['sessionusername']);

            respond(array(
                'status' => ($price_skipped > 0) ? 'partial' : 'success',
                'request' => $type,
                'updated' => $updated,
                'price_skipped' => $price_skipped,
                'barcodes' => $barcodes_assigned,
                'message' => $message));
            break;

        // ── Where a file is used ────────────────────────────────────────
        //
        // "Can I delete this?" is the question the file manager could not
        // answer, and the way it was answered instead was by deleting it and
        // waiting to see what broke.
        //
        // files.name IS the address -- every reader resolves a file as
        // FILE_DIRECTORY_PATH . '/' . name -- so the name is what the content
        // carries, and a LIKE over the places content lives finds it.
        //
        // On demand only. This walks several text columns without an index
        // that could help, which is fine for a button somebody pressed and
        // would not be fine on a listing.
        case 'explorer_file_usage':

            $usage_file = pg_explorer_file_row((int) (isset($request['file_id']) ? $request['file_id'] : 0));

            if (!$usage_file) {
                respond(array('status' => 'error', 'message' => lang('Sorry, we could not find the file.')));
            }

            // The same rule the listing applies: a file is only a question
            // for someone who can see its folder.
            if (pg_explorer_folder_visible($usage_file['folder'], $folders_that_user_has_access_to) == false) {
                log_activity(lang('access denied because user does not have access to file'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $usage_name = (string) $usage_file['name'];
            $usage_like = '%' . escape_like($usage_name) . '%';
            $usage_groups = array();
            $usage_total = 0;
            $usage_cap = 40;
            $usage_truncated = false;

            // Columns are asked for rather than assumed: half of these arrived
            // with the visual designer and are simply absent on a database that
            // has taken the code but not the upgrade.
            $usage_columns = function ($table, $wanted) {

                $rows = db_items("SHOW COLUMNS FROM " . $table);

                if (!$rows) {
                    return array();
                }

                $have = array();

                foreach ($rows as $row) {
                    $have[$row['Field']] = true;
                }

                $out = array();

                foreach ($wanted as $column) {
                    if (isset($have[$column])) {
                        $out[] = $column;
                    }
                }

                return $out;
            };

            // One source: a table, the columns worth scanning, and how to name
            // and reach what was found.
            $usage_scan = function ($table, $columns, $select, $label, $link) use ($usage_like, &$usage_groups, &$usage_total, &$usage_truncated, $usage_cap) {

                if (count($columns) == 0) {
                    return;
                }

                $where = array();

                foreach ($columns as $column) {
                    $where[] = $column . " LIKE '" . e($usage_like) . "'";
                }

                $rows = db_items(
                    "SELECT " . $select . "
                    FROM " . $table . "
                    WHERE (" . implode(' OR ', $where) . ")
                    LIMIT " . ($usage_cap + 1));

                if (!$rows) {
                    return;
                }

                if (count($rows) > $usage_cap) {
                    $usage_truncated = true;
                    $rows = array_slice($rows, 0, $usage_cap);
                }

                $items = array();

                foreach ($rows as $row) {
                    $items[] = $link($row);
                }

                $usage_total += count($items);
                $usage_groups[] = array('label' => $label, 'items' => $items);
            };

            // Page regions, named by the page they belong to: a region id means
            // nothing to anybody, and the page is where it is edited from.
            $usage_scan(
                'pregion LEFT JOIN page ON pregion.pregion_page = page.page_id',
                $usage_columns('pregion', array('pregion_content')),
                'pregion.pregion_id, pregion.pregion_name, page.page_id, page.page_name',
                lang('Page Regions'),
                function ($row) {
                    return array(
                        'name' => ($row['page_name'] !== null) ? (string) $row['page_name'] : (string) $row['pregion_name'],
                        'note' => (string) $row['pregion_name'],
                        'url' => ($row['page_id'] > 0) ? ('edit_page.php?id=' . (int) $row['page_id']) : '');
                });

            $usage_scan(
                'cregion',
                $usage_columns('cregion', array('cregion_content')),
                'cregion_id, cregion_name, cregion_designer_type',
                lang('Common Regions'),
                function ($row) {
                    return array(
                        'name' => (string) $row['cregion_name'],
                        'note' => '',
                        'url' => (($row['cregion_designer_type'] == 'yes') ? 'edit_designer_region.php?id=' : 'edit_common_region.php?id=') . (int) $row['cregion_id']);
                });

            $usage_scan(
                'dregion',
                $usage_columns('dregion', array('dregion_code')),
                'dregion_id, dregion_name',
                lang('Dynamic Regions'),
                function ($row) {
                    return array('name' => (string) $row['dregion_name'], 'note' => '', 'url' => 'edit_dynamic_region.php?id=' . (int) $row['dregion_id']);
                });

            // The visual designer keeps a page's markup on the page itself and
            // the design's assets on the style; both columns arrived in 4.4.
            $usage_scan(
                'page',
                $usage_columns('page', array('page_tree_code', 'page_tree_json')),
                'page_id, page_name',
                lang('Pages'),
                function ($row) {
                    return array('name' => (string) $row['page_name'], 'note' => '', 'url' => 'edit_page.php?id=' . (int) $row['page_id']);
                });

            $usage_scan(
                'style',
                $usage_columns('style', array('style_code', 'style_head', 'style_tree_json', 'style_custom_css', 'style_custom_js')),
                'style_id, style_name, style_layout',
                lang('Styles'),
                function ($row) {
                    return array(
                        'name' => (string) $row['style_name'],
                        'note' => '',
                        'url' => (($row['style_layout'] == 'visual_designer') ? 'edit_system_style.php?id=' : 'edit_custom_style.php?id=') . (int) $row['style_id']);
                });

            // Shared components and system widgets have no screen of their own
            // -- they are edited inside whichever design uses them -- so they
            // are reported by name and without a link rather than with one that
            // goes nowhere.
            $usage_scan(
                'shared_components',
                $usage_columns('shared_components', array('tree_json')),
                'id, name',
                lang('Shared Components'),
                function ($row) {
                    return array('name' => (string) $row['name'], 'note' => '', 'url' => '');
                });

            if (defined('ECOMMERCE') && ECOMMERCE === true) {

                $usage_scan(
                    'products',
                    $usage_columns('products', array('image_name', 'full_description', 'details', 'short_description')),
                    'id, name, short_description',
                    lang('Products'),
                    function ($row) {
                        return array(
                            'name' => (string) $row['name'],
                            'note' => (string) $row['short_description'],
                            'url' => 'edit_product.php?id=' . (int) $row['id']);
                    });

                $usage_scan(
                    'product_groups',
                    $usage_columns('product_groups', array('image_name', 'description', 'short_description')),
                    'id, name',
                    lang('Product Groups'),
                    function ($row) {
                        return array('name' => (string) $row['name'], 'note' => '', 'url' => 'edit_product_group.php?id=' . (int) $row['id']);
                    });
            }

            if (defined('ADS') && ADS === true) {

                $usage_scan(
                    'ads',
                    $usage_columns('ads', array('content')),
                    'id, name',
                    lang('Ads'),
                    function ($row) {
                        return array('name' => (string) $row['name'], 'note' => '', 'url' => 'edit_ad.php?id=' . (int) $row['id']);
                    });
            }

            // Settings that name a file outright. Matched whole rather than by
            // LIKE: these hold one file name, not a document that mentions one.
            $usage_settings = array(
                'og_default_image' => lang('Default Share Image'),
                'organization_logo' => lang('Organization Logo'),
                'app_icon' => lang('Application Icon'));

            $usage_setting_columns = $usage_columns('config', array_keys($usage_settings));
            $usage_setting_hits = array();

            if (count($usage_setting_columns) > 0) {

                $usage_config = db_item("SELECT " . implode(', ', $usage_setting_columns) . " FROM config LIMIT 1");

                if ($usage_config) {
                    foreach ($usage_setting_columns as $usage_column) {
                        if (((string) $usage_config[$usage_column]) === $usage_name) {
                            $usage_setting_hits[] = array('name' => $usage_settings[$usage_column], 'note' => '', 'url' => '');
                        }
                    }
                }
            }

            if (count($usage_setting_hits) > 0) {
                $usage_total += count($usage_setting_hits);
                $usage_groups[] = array('label' => lang('Settings'), 'items' => $usage_setting_hits);
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'file' => $usage_name,
                'total' => $usage_total,
                'truncated' => $usage_truncated,
                'groups' => $usage_groups));
            break;

        // ── One field of one product ────────────────────────────────────
        //
        // The three figures a shop changes all day -- price, stock and the name
        // the storefront prints -- edited where they are already being read.
        // The bulk panel exists for a selection and the product screen for
        // everything else; neither is the right amount of ceremony for "this
        // one is two lira more than it says".
        //
        // Deliberately three fields and no more. A general "set any column"
        // endpoint would have to re-implement every rule the product screen
        // carries (addresses, variant attributes, tax, zones) or quietly skip
        // them, and quietly skipping them is how a catalog drifts.
        case 'explorer_catalog_quick_edit':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $quick_id = (int) (isset($request['item_id']) ? $request['item_id'] : 0);
            $quick_field = isset($request['field']) ? (string) $request['field'] : '';
            $quick_value = isset($request['value']) ? trim((string) $request['value']) : '';

            if (($quick_id <= 0) || (in_array($quick_field, array('price', 'quantity', 'short_description'), true) == false)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            // Live rows only, the same rule the rest of this screen follows: a
            // product in the bin is restored before it is edited.
            $quick_row = db_item(
                "SELECT id, name, price, inventory, inventory_quantity, short_description
                FROM products
                WHERE id = '" . e($quick_id) . "'" . pg_catalog_live_filter('products'));

            if (!$quick_row) {
                respond(array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.')));
            }

            $quick_sql = '';
            $quick_note = '';

            if ($quick_field === 'price') {

                // Typed in the currency and stored in kuruş. round() rather
                // than a cast: the cast truncates and the card and the feed
                // then disagree by a kuruş.
                $quick_number = str_replace(',', '.', $quick_value);

                if (($quick_number === '') || (is_numeric($quick_number) == false)) {
                    respond(array('status' => 'error', 'message' => lang('Enter an amount for the price change.')));
                }

                $quick_number = (int) round(((float) $quick_number) * 100);

                // Zero is allowed and negative is not: a shop really does carry
                // free articles -- donations, account payments -- but nothing
                // costs less than nothing.
                if ($quick_number < 0) {
                    respond(array('status' => 'error', 'message' => lang('Enter an amount for the price change.')));
                }

                $quick_sql = "price = '" . e($quick_number) . "'";
                $quick_note = lang('price changed');

            } elseif ($quick_field === 'quantity') {

                // A number written against a product that does not count its
                // stock is a number nobody will ever read. Saying so is better
                // than storing it and letting the operator believe the shelf
                // is now being watched.
                if ($quick_row['inventory'] != '1') {
                    respond(array('status' => 'error', 'message' => lang('Stock is not tracked for this product.')));
                }

                if (($quick_value === '') || (is_numeric($quick_value) == false)) {
                    respond(array('status' => 'error', 'message' => lang('Enter an inventory quantity.')));
                }

                $quick_number = (int) $quick_value;

                // Stock does not go negative: a shelf holds none or some.
                if ($quick_number < 0) {
                    $quick_number = 0;
                }

                // Out of stock is a consequence rather than a field: putting
                // something back on the shelf takes the flag off it.
                $quick_sql = "inventory_quantity = '" . e($quick_number) . "'" . (($quick_number > 0) ? ", out_of_stock = '0'" : '');
                $quick_note = lang('stock quantity changed');

            } else {

                // Cut to what the column holds rather than letting MySQL do it
                // without saying so. Asked of the schema instead of assumed:
                // the width has changed before now.
                $quick_column = db_item("SHOW COLUMNS FROM products WHERE Field = 'short_description'");
                $quick_limit = 0;

                if (($quick_column) && (preg_match('/\((\d+)\)/', (string) $quick_column['Type'], $quick_matches))) {
                    $quick_limit = (int) $quick_matches[1];
                }

                if (($quick_limit > 0) && (mb_strlen($quick_value) > $quick_limit)) {
                    respond(array(
                        'status' => 'error',
                        'message' => lang(array('string' => 'This can be at most {var:1} characters long.', 'vars' => $quick_limit))));
                }

                $quick_sql = "short_description = '" . e($quick_value) . "'";
                $quick_note = lang('short description changed');
            }

            // address_name is left alone on purpose. It is derived from the
            // short description when a product is created, but by the time it
            // is being edited here it is a published address -- rewriting it
            // from a typo correction would break every link to the page.
            db(
                "UPDATE products
                SET " . $quick_sql . ",
                    timestamp = UNIX_TIMESTAMP(),
                    user = '" . e($user['id']) . "'
                WHERE id = '" . e($quick_id) . "'");

            // Price and stock are what the marketplaces are told about, and
            // this is now one of the places they change. Queue only -- the
            // scheduled job does the sending.
            if ((($quick_field === 'price') || ($quick_field === 'quantity')) && function_exists('pg_marketplace_product_changed')) {
                pg_marketplace_product_changed($quick_id);
            }

            $quick_message = lang(array(
                'string' => '{var:1} was updated ({var:2}).',
                'vars' => array(h($quick_row['name']), $quick_note)));

            log_activity($quick_message, $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'message' => $quick_message));
            break;

        // ── Commerce access: read ───────────────────────────────────────
        //
        // The store's counterpart to the folder access panel, with one real
        // difference: a folder's rights belong to that folder, while commerce
        // rights belong to the user and cover the whole store. There is no
        // per-group permission to set here, and inventing one would promise a
        // separation the storefront does not enforce.
        //
        // The three switches are the ones the user screen carries under
        // "Commerce Management Rights", written to the same columns, so a
        // change here and a change there are the same change. Basic users only:
        // every other role manages commerce by virtue of its role, which is why
        // the folder panel lists the same set.
        case 'explorer_catalog_access_get':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $response = array(
                'status' => 'success',
                'request' => $type,
                'can_manage_users' => ($user['role'] <= 2),
                'offline_payment' => (defined('ECOMMERCE_OFFLINE_PAYMENT') && (ECOMMERCE_OFFLINE_PAYMENT == true)),
                'users' => array());

            if ($user['role'] <= 2) {

                $rows = db_items(
                    "SELECT
                        user_id,
                        user_username,
                        user_manage_ecommerce,
                        manage_ecommerce_reports,
                        user_set_offline_payment
                    FROM user
                    WHERE user_role = '3'
                    ORDER BY user_username");

                foreach ((array) $rows as $row) {

                    $response['users'][] = array(
                        'id' => (int) $row['user_id'],
                        'username' => (string) $row['user_username'],
                        'manage' => ($row['user_manage_ecommerce'] == 'yes'),
                        'reports' => ($row['manage_ecommerce_reports'] == '1'),
                        'offline_payment' => ($row['user_set_offline_payment'] == '1'));
                }
            }

            respond($response);
            break;

        // ── Commerce access: write ──────────────────────────────────────
        case 'explorer_catalog_access_set':

            if ((pg_catalog_access($user) == false) || ($user['role'] > 2)) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $items = isset($request['users']) ? $request['users'] : array();

            if ((is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $saved = 0;

            foreach ($items as $item) {

                $row_user_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($row_user_id <= 0) {
                    continue;
                }

                // Basic users only, checked against the table rather than taken
                // from the request: the id arrives from a browser, and a manager
                // is not something this panel may hand rights to.
                $target = db_item("SELECT user_id FROM user WHERE (user_id = '" . e($row_user_id) . "') AND (user_role = '3')");

                if (is_array($target) == false) {
                    continue;
                }

                $manage = ((isset($item['manage'])) && ($item['manage'])) ? 'yes' : 'no';
                $reports = ((isset($item['reports'])) && ($item['reports'])) ? '1' : '0';
                $offline = ((isset($item['offline_payment'])) && ($item['offline_payment'])) ? '1' : '0';

                db(
                    "UPDATE user SET
                        user_manage_ecommerce = '" . e($manage) . "',
                        manage_ecommerce_reports = '" . e($reports) . "',
                        user_set_offline_payment = '" . e($offline) . "'
                    WHERE user_id = '" . e($row_user_id) . "'");

                $saved++;
            }

            if ($saved > 0) {
                log_activity(lang('Commerce Management Rights'), $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => ($saved > 0) ? 'success' : 'error',
                'request' => $type,
                'count' => $saved,
                'message' => ($saved > 0) ? lang('The changes were saved.') : lang('Sorry, we could not accept your request.')));
            break;

        // ── Take a product out of one group ──────────────────────────────
        //
        // Not a delete. A product belongs to several groups at once, so leaving
        // this one changes nothing else about it: it stays on sale, and it stays
        // wherever else it is listed. The screen says so in those words, because
        // "delete" in a folder means something the operator would not want here.
        case 'explorer_catalog_membership_remove':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $product_id = (int) (isset($request['product_id']) ? $request['product_id'] : 0);
            $group_id = (int) (isset($request['group_id']) ? $request['group_id'] : 0);

            if (($product_id <= 0) || ($group_id <= 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            db(
                "DELETE FROM products_groups_xref
                WHERE (product = '" . e($product_id) . "')
                    AND (product_group = '" . e($group_id) . "')");

            $remaining = (int) db_value(
                "SELECT COUNT(*)
                FROM products_groups_xref
                WHERE product = '" . e($product_id) . "'");

            $product = db_item("SELECT name FROM products WHERE id = '" . e($product_id) . "'");

            log_activity(lang(array('string' => 'The product, {var:1}, was removed from a product group.', 'vars' => h(isset($product['name']) ? $product['name'] : ''))), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'remaining' => $remaining,
                'message' => ($remaining > 0)
                    ? lang(array('string' => 'Removed from this group. It is still in {var:1} other group(s).', 'vars' => $remaining))
                    : lang('Removed from this group. It is now in no group, and only the All Products view will show it.')));
            break;
        // ── Where this group or product is visible on the site ───────────
        //
        // Asked for only when the preview panel is open, because both routes
        // scan text columns and neither belongs in a listing that redraws on
        // every click.  A single "view on site" button would have to pick one
        // of these for the operator; the panel lists them and lets them pick.
        case 'explorer_catalog_pages':

            if (pg_catalog_access($user) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $item_kind = isset($request['item_kind']) ? (string) $request['item_kind'] : '';
            $item_id = (int) (isset($request['item_id']) ? $request['item_id'] : 0);

            if ($item_id <= 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $pages = ($item_kind == 'product')
                ? pg_catalog_pages_for_product($item_id)
                : pg_catalog_pages_for_group($item_id);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'item_kind' => $item_kind,
                'item_id' => $item_id,
                'pages' => $pages));
            break;
        // ── Tree sidebar (lazy children) ────────────────────────────────
        case 'explorer_tree':

            // Read-only: nothing below writes to the session.
            pg_explorer_release_session();

            $node_id = (int) (isset($request['node_id']) ? $request['node_id'] : 0);
            $counts = pg_explorer_folder_counts();
            $children = array();

            foreach (pg_explorer_child_folders($node_id, $user, $folders_that_user_has_access_to) as $row) {
                $id = (int) $row['folder_id'];
                $count = isset($counts[$id]) ? $counts[$id] : array('folders' => 0, 'pages' => 0, 'files' => 0);

                $children[] = array(
                    'id' => $id,
                    'parent_id' => (int) $row['folder_parent'],
                    'name' => $row['folder_name'],
                    'access_control_type' => get_access_control_type($id),
                    'archived' => ($row['folder_archived'] == '1'),
                    'has_children' => ($count['folders'] > 0),
                    'is_root' => ((int) $row['folder_parent'] == 0),
                    'empty' => (($count['folders'] + ($count['pages'] ?? 0) + ($count['files'] ?? 0)) == 0),
                    'can_edit' => check_edit_access($id));
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'node_id' => $node_id,
                'children' => $children));
            break;

        // ── Create folder ───────────────────────────────────────────────
        case 'explorer_create_folder':

            $parent_id = (int) (isset($request['folder_id']) ? $request['folder_id'] : 0);

            if (($parent_id <= 0) || (check_edit_access($parent_id) == false) || pg_recycle_is_inside($parent_id)) {
                log_activity(lang('access denied because user does not have access to create folder in parent folder'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $name = trim(isset($request['name']) ? $request['name'] : '');

            if ($name == '') {
                respond(array('status' => 'error', 'message' => lang('The folder must have a name. Please type in a name for the folder.')));
            }

            $name = get_unique_name(array('name' => $name, 'type' => 'folder'));

            $parent_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($parent_id) . "'");

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
                    '" . e($name) . "',
                    '" . e($parent_id) . "',
                    '" . e($parent_level + 1) . "',
                    '0',
                    NULL,
                    '0',
                    '0',
                    '0',
                    UNIX_TIMESTAMP(),
                    '" . e($user['id']) . "')");

            $new_folder_id = mysqli_insert_id(db::$con);

            log_activity(lang(array('string' => 'folder ({var:1}) was created', 'vars' => array($name))), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'folder' => array('id' => (int) $new_folder_id, 'name' => $name),
                'message' => lang('The folder was created successfully.')));
            break;

        // ── Create an empty file (named in place, edited later) ─────────
        case 'explorer_create_file':

            $parent_id = (int) (isset($request['folder_id']) ? $request['folder_id'] : 0);

            if (($parent_id <= 0) || (check_edit_access($parent_id) == false) || pg_recycle_is_inside($parent_id)) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $name = trim(isset($request['name']) ? $request['name'] : '');

            if ($name == '') {
                respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
            }

            // A bare name gets a text extension, the way desktop file
            // managers create "New file.txt".
            if (mb_strrpos($name, '.') === false) {
                $name .= '.txt';
            }

            // Creating "shell.php" is uploading it with an extra step.
            if (pg_upload_name_blocked($name)) {
                respond(array('status' => 'error', 'message' => pg_upload_blocked_message($name)));
            }

            $name = prepare_file_name($name);
            $name = get_unique_name(array('name' => $name, 'type' => 'file'));

            $file_path = FILE_DIRECTORY_PATH . '/' . $name;

            if (@file_put_contents($file_path, '') === false) {
                respond(array('status' => 'error', 'message' => lang('The file could not be saved. Check write permission for the file directory.')));
            }

            $file_extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

            db(
                "INSERT INTO files (
                    name,
                    folder,
                    type,
                    size,
                    design,
                    optimized,
                    user,
                    timestamp)
                VALUES (
                    '" . e($name) . "',
                    '" . e($parent_id) . "',
                    '" . e($file_extension) . "',
                    '0',
                    '0',
                    '0',
                    '" . e($user['id']) . "',
                    UNIX_TIMESTAMP())");

            $file_id = mysqli_insert_id(db::$con);

            log_activity(lang(array('string' => 'file ({var:1}) was created', 'vars' => $name)), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'file' => array('id' => (int) $file_id, 'name' => $name),
                'message' => lang('The file was created successfully.')));
            break;

        // ── Rename ──────────────────────────────────────────────────────
        case 'explorer_rename':

            $item_kind = isset($request['item_kind']) ? $request['item_kind'] : '';
            $item_id = (int) (isset($request['item_id']) ? $request['item_id'] : 0);
            $name = trim(isset($request['name']) ? $request['name'] : '');

            if (($item_id <= 0) || ($name == '')) {
                respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
            }

            switch ($item_kind) {

                case 'folder':

                    $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($item_id) . "'");

                    if (!$folder) {
                        respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
                    }

                    if (check_edit_access($item_id) == false) {
                        log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
                        respond(array('status' => 'error', 'message' => lang('Access denied')));
                    }

                    db(
                        "UPDATE folder SET
                            folder_name = '" . e($name) . "',
                            folder_timestamp = UNIX_TIMESTAMP(),
                            folder_user = '" . e($user['id']) . "'
                        WHERE folder_id = '" . e($item_id) . "'");

                    log_activity(lang(array('string' => 'folder ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);

                    respond(array('status' => 'success', 'request' => $type, 'name' => $name));
                    break;

                case 'page':

                    $page = db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . e($item_id) . "'");

                    if (!$page) {
                        respond(array('status' => 'error', 'message' => lang('Sorry, the page could not be found.')));
                    }

                    if (check_edit_access($page['page_folder']) == false) {
                        log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
                        respond(array('status' => 'error', 'message' => lang('Access denied')));
                    }

                    if ($name != $page['page_name']) {

                        // Pages and files share the web root namespace, so a
                        // page may not take a name that any page or file
                        // already answers to.
                        if (check_name_availability(array('name' => $name, 'ignore_item_id' => $item_id, 'ignore_item_type' => 'page')) == false) {
                            respond(array('status' => 'error', 'message' => lang('That name is already in use. Please choose a different name.')));
                        }

                        db(
                            "UPDATE page SET
                                page_name = '" . e($name) . "',
                                page_timestamp = UNIX_TIMESTAMP(),
                                page_user = '" . e($user['id']) . "'
                            WHERE page_id = '" . e($item_id) . "'");

                        log_activity(lang(array('string' => 'page ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);
                    }

                    respond(array('status' => 'success', 'request' => $type, 'name' => $name));
                    break;

                case 'file':

                    $file = db_item(
                        "SELECT id, name, folder, design
                        FROM files
                        WHERE id = '" . e($item_id) . "'");

                    if (!$file) {
                        respond(array('status' => 'error', 'message' => lang('Sorry, the file could not be found.')));
                    }

                    if (
                        (check_edit_access($file['folder']) == false)
                        || (($file['design'] == 1) && ($user['role'] > 1))
                    ) {
                        log_activity(lang('access denied to modify files because user does not have access to file'), $_SESSION['sessionusername']);
                        respond(array('status' => 'error', 'message' => lang('Access denied')));
                    }

                    // Renaming "photo.jpg" to "photo.php" is uploading a
                    // PHP file with an extra step, so the rename keeps the
                    // upload rule.
                    if (($name != $file['name']) && pg_upload_name_blocked($name)) {
                        respond(array('status' => 'error', 'message' => pg_upload_blocked_message($name)));
                    }

                    // Same preparation the file edit screen applies: ASCII
                    // conversion, reserved names, length and character rules.
                    $name = prepare_file_name($name);

                    if ($name == $file['name']) {
                        respond(array('status' => 'success', 'request' => $type, 'name' => $name));
                    }

                    if (check_name_availability(array('name' => $name, 'ignore_item_id' => $item_id, 'ignore_item_type' => 'file')) == false) {
                        respond(array('status' => 'error', 'message' => lang('That name is already in use. Please choose a different name.')));
                    }

                    // The record's name is the address, so the disk file must
                    // move first — and the row is only updated if it did.
                    $old_file_path = FILE_DIRECTORY_PATH . '/' . $file['name'];
                    $new_file_path = FILE_DIRECTORY_PATH . '/' . $name;

                    if ((file_exists($old_file_path) == true) && (@rename($old_file_path, $new_file_path) == false)) {
                        respond(array('status' => 'error', 'message' => lang('The file could not be renamed on the file system. Check write permission for the file directory.')));
                    }

                    $file_extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

                    db(
                        "UPDATE files SET
                            name = '" . e($name) . "',
                            type = '" . e($file_extension) . "',
                            timestamp = UNIX_TIMESTAMP(),
                            user = '" . e($user['id']) . "'
                        WHERE id = '" . e($item_id) . "'");

                    log_activity(lang(array('string' => 'file ({var:1}) was modified', 'vars' => $name)), $_SESSION['sessionusername']);

                    respond(array('status' => 'success', 'request' => $type, 'name' => $name));
                    break;
            }

            respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            break;

        // ── Move (drag & drop, cut + paste) ─────────────────────────────
        case 'explorer_move':

            // A thousand rows may be about to move; nothing below writes to
            // the session, so the lock is not held for the duration.
            pg_explorer_release_session();

            $target_id = (int) (isset($request['target_folder_id']) ? $request['target_folder_id'] : 0);
            $items = isset($request['items']) ? $request['items'] : array();

            if (($target_id <= 0) || (is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            if (check_edit_access($target_id) == false) {
                respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
            }

            // The bin is only fed by the delete action, so restore metadata
            // always exists for what sits inside it.
            if (pg_recycle_is_inside($target_id)) {
                respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
            }

            $moved = 0;
            $errors = array();

            foreach ($items as $item) {

                $item_kind = isset($item['kind']) ? $item['kind'] : '';
                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($item_id <= 0) {
                    continue;
                }

                switch ($item_kind) {

                    case 'file':

                        $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($item_id) . "'");

                        if (!$file) {
                            $errors[] = lang('Sorry, the file could not be found.');
                            break;
                        }

                        if (
                            (check_edit_access($file['folder']) == false)
                            || (($file['design'] == 1) && ($user['role'] > 1))
                        ) {
                            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                            break;
                        }

                        if ((int) $file['folder'] != $target_id) {

                            db(
                                "UPDATE files SET
                                    folder = '" . e($target_id) . "',
                                    timestamp = UNIX_TIMESTAMP(),
                                    user = '" . e($user['id']) . "'
                                WHERE id = '" . e($item_id) . "'");

                            $moved++;
                        }
                        break;

                    case 'page':

                        $page = db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . e($item_id) . "'");

                        if (!$page) {
                            $errors[] = lang('Sorry, the page could not be found.');
                            break;
                        }

                        if (check_edit_access($page['page_folder']) == false) {
                            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $page['page_name']));
                            break;
                        }

                        if ((int) $page['page_folder'] != $target_id) {

                            db(
                                "UPDATE page SET
                                    page_folder = '" . e($target_id) . "',
                                    page_timestamp = UNIX_TIMESTAMP(),
                                    page_user = '" . e($user['id']) . "'
                                WHERE page_id = '" . e($item_id) . "'");

                            $moved++;
                        }
                        break;

                    case 'folder':

                        $folder = db_item("SELECT folder_id, folder_name, folder_parent, folder_level FROM folder WHERE folder_id = '" . e($item_id) . "'");

                        if (!$folder) {
                            $errors[] = lang('Sorry, the folder could not be found.');
                            break;
                        }

                        if ((int) $folder['folder_parent'] == 0) {
                            $errors[] = lang('The root folder cannot be moved.');
                            break;
                        }

                        if (check_edit_access($item_id) == false) {
                            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $folder['folder_name']));
                            break;
                        }

                        // A folder cannot move into itself or its own subtree,
                        // that would detach the branch from the tree.
                        if (pg_explorer_is_self_or_descendant($item_id, $target_id)) {
                            $errors[] = lang(array('string' => 'A folder cannot be moved into itself ({var:1}).', 'vars' => $folder['folder_name']));
                            break;
                        }

                        if ((int) $folder['folder_parent'] != $target_id) {

                            $target_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($target_id) . "'");

                            db(
                                "UPDATE folder SET
                                    folder_parent = '" . e($target_id) . "',
                                    folder_level = '" . e($target_level + 1) . "',
                                    folder_timestamp = UNIX_TIMESTAMP(),
                                    folder_user = '" . e($user['id']) . "'
                                WHERE folder_id = '" . e($item_id) . "'");

                            // Levels below the moved folder shift with it.
                            pg_explorer_change_level($item_id, $target_level + 1);

                            $moved++;
                        }
                        break;
                }
            }

            if ($moved > 0) {
                $target_name = db_value("SELECT folder_name FROM folder WHERE folder_id = '" . e($target_id) . "'");
                log_activity($moved . ' ' . lang('item(s)') . ' ' . lang(array('string' => 'were moved to {var:1}', 'vars' => $target_name)), $_SESSION['sessionusername']);

                // An item dragged out of the bin is restored: forget it.
                if (pg_recycle_ready()) {
                    foreach ($items as $item) {
                        $item_kind = isset($item['kind']) ? $item['kind'] : '';
                        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                        if (($item_id > 0) && in_array($item_kind, array('folder', 'page', 'file'), true)) {
                            db("DELETE FROM recycle_bin WHERE (item_type = '" . e($item_kind) . "') AND (item_id = '" . e($item_id) . "')");
                        }
                    }
                }
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((($moved > 0)) ? 'partial' : 'error'),
                'request' => $type,
                'moved' => $moved,
                'errors' => $errors,
                'message' => (count($errors) > 0)
                    ? implode(' ', $errors)
                    : lang(array('string' => '{var:1} item(s) were moved.', 'vars' => $moved))));
            break;

        // ── Paste (copy of files and pages) ─────────────────────────────
        case 'explorer_paste':

            $target_id = (int) (isset($request['target_folder_id']) ? $request['target_folder_id'] : 0);
            $items = isset($request['items']) ? $request['items'] : array();

            if (($target_id <= 0) || (is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            if ((check_edit_access($target_id) == false) || pg_recycle_is_inside($target_id)) {
                respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
            }

            $target_folder = db_item("SELECT folder_id AS id, folder_name AS name FROM folder WHERE folder_id = '" . e($target_id) . "'");

            $copied = 0;
            $errors = array();
            $created = array();

            foreach ($items as $item) {

                $item_kind = isset($item['kind']) ? $item['kind'] : '';
                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($item_id <= 0) {
                    continue;
                }

                switch ($item_kind) {

                    case 'file':

                        $file = db_item(
                            "SELECT id, name, folder, description, design, optimized, image_width, image_height, optimization_percent
                            FROM files
                            WHERE id = '" . e($item_id) . "'");

                        if (!$file) {
                            $errors[] = lang('Sorry, the file could not be found.');
                            break;
                        }

                        if (
                            (check_edit_access($file['folder']) == false)
                            || (($file['design'] == 1) && ($user['role'] > 1))
                        ) {
                            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                            break;
                        }

                        $source_path = FILE_DIRECTORY_PATH . '/' . $file['name'];

                        if (file_exists($source_path) == false) {
                            $errors[] = lang(array('string' => 'The file does not exist on the file system ({var:1}).', 'vars' => $file['name']));
                            break;
                        }

                        $new_name = get_unique_name(array('name' => prepare_file_name($file['name']), 'type' => 'file'));
                        $new_path = FILE_DIRECTORY_PATH . '/' . $new_name;

                        if (@copy($source_path, $new_path) == false) {
                            $errors[] = lang(array('string' => 'The file could not be copied ({var:1}).', 'vars' => $file['name']));
                            break;
                        }

                        $file_extension = mb_strtolower(pathinfo($new_name, PATHINFO_EXTENSION));

                        db(
                            "INSERT INTO files (
                                name,
                                folder,
                                description,
                                type,
                                size,
                                design,
                                optimized,
                                image_width,
                                image_height,
                                optimization_percent,
                                user,
                                timestamp)
                            VALUES (
                                '" . e($new_name) . "',
                                '" . e($target_id) . "',
                                '" . e($file['description']) . "',
                                '" . e($file_extension) . "',
                                '" . e(filesize($new_path)) . "',
                                '" . e($file['design']) . "',
                                '" . e($file['optimized']) . "',
                                " . (($file['image_width'] !== null && $file['image_width'] !== '') ? "'" . e($file['image_width']) . "'" : "NULL") . ",
                                " . (($file['image_height'] !== null && $file['image_height'] !== '') ? "'" . e($file['image_height']) . "'" : "NULL") . ",
                                " . (($file['optimization_percent'] !== null && $file['optimization_percent'] !== '') ? "'" . e($file['optimization_percent']) . "'" : "NULL") . ",
                                '" . e($user['id']) . "',
                                UNIX_TIMESTAMP())");

                        $created[] = array('kind' => 'file', 'id' => (int) mysqli_insert_id(db::$con));

                        log_activity(lang(array('string' => '{var:1} ({var:2}) was duplicated', 'vars' => array(lang('file'), $file['name']))), $_SESSION['sessionusername']);

                        $copied++;
                        break;

                    case 'folder':

                        // A folder copy is the whole subtree. Pasting one
                        // into itself (or into something it contains) would
                        // be a copy with no end, so that is refused rather
                        // than depth-capped.
                        if (pg_explorer_is_self_or_descendant($item_id, $target_id)) {
                            $errors[] = lang('Sorry, a folder cannot be copied into itself.');
                            break;
                        }

                        if (pg_recycle_is_inside($item_id)) {
                            $errors[] = lang('Sorry, the folder could not be found.');
                            break;
                        }

                        $copied += pg_explorer_copy_folder($item_id, $target_id, $user, $errors, $created);
                        break;

                    case 'page':

                        // duplicate_page() carries the full copy: settings,
                        // regions, layout file, and it validates the page
                        // creation right and source folder access itself.
                        require_once(dirname(__FILE__) . '/duplicate_page_f.php');

                        $response = duplicate_page(array(
                            'page' => array('id' => $item_id),
                            'folder' => $target_folder,
                            'find_replace_keywords' => ''));

                        if (isset($response['status']) && ($response['status'] == 'success')) {
                            $copied++;

                            if (isset($response['page']['id'])) {
                                $created[] = array('kind' => 'page', 'id' => (int) $response['page']['id']);
                            }
                        } else {
                            $errors[] = isset($response['message']) ? $response['message'] : lang('Sorry, the page could not be found.');
                        }
                        break;
                }
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((($copied > 0)) ? 'partial' : 'error'),
                'request' => $type,
                'copied' => $copied,
                'created' => $created,
                'errors' => $errors,
                'message' => (count($errors) > 0)
                    ? implode(' ', $errors)
                    : lang(array('string' => '{var:1} item(s) were pasted.', 'vars' => $copied))));
            break;

        // ── Delete files (bulk) ─────────────────────────────────────────
        case 'explorer_delete_files':

            $ids = isset($request['file_ids']) ? $request['file_ids'] : array();

            if ((is_array($ids) == false) || (count($ids) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $deleted = 0;
            $errors = array();

            foreach ($ids as $file_id) {

                $file_id = (int) $file_id;

                $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($file_id) . "'");

                if (!$file) {
                    continue;
                }

                if (
                    (check_edit_access($file['folder']) == false)
                    || (($file['design'] == 1) && ($user['role'] > 1))
                ) {
                    log_activity(lang('access denied to delete files because user does not have access to file'), $_SESSION['sessionusername']);
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                    continue;
                }

                db("DELETE FROM files WHERE id = '" . e($file_id) . "'");

                // A file can carry theme css rules and preview styles; those
                // rows die with it, the same way the files screen deletes.
                db("DELETE FROM system_theme_css_rules WHERE file_id = '" . e($file_id) . "'");
                db("DELETE FROM preview_styles WHERE theme_id = '" . e($file_id) . "'");

                @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);

                $deleted++;
            }

            if ($deleted > 0) {
                log_activity(lang(array('string' => '{var:1} file(s) were deleted', 'vars' => $deleted)), $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((($deleted > 0)) ? 'partial' : 'error'),
                'request' => $type,
                'deleted' => $deleted,
                'errors' => $errors,
                'message' => (count($errors) > 0)
                    ? implode(' ', $errors)
                    : lang(array('string' => '{var:1} file(s) were deleted', 'vars' => $deleted))));
            break;

        // ── The folders an upload may land in ────────────────────────────
        //
        // Asked for when the upload window opens rather than baked into the
        // page. The page is loaded once and then lived in: a folder made five
        // minutes ago was not in a list rendered at load, so the window fell
        // back to the top folder and quietly uploaded into the wrong place.
        //
        // Walked with pg_explorer_child_folders(), which is what draws the
        // tree in the sidebar, so the list and the tree can never disagree
        // about which folders exist or what order they come in. Each one is
        // then held to check_edit_access() -- the same gate explorer_upload
        // applies below, so nothing is offered that the upload would refuse.
        case 'explorer_folder_options':

            $folder_choices = array();

            $collect_folders = function ($parent_id, $depth) use (&$collect_folders, &$folder_choices, $user, $folders_that_user_has_access_to) {

                // Deep enough for any real site; a folder table with a loop in
                // it would otherwise take the request down with it.
                if ($depth > 50) {
                    return;
                }

                foreach (pg_explorer_child_folders($parent_id, $user, $folders_that_user_has_access_to) as $folder_row) {

                    $folder_row_id = (int) $folder_row['folder_id'];

                    // The bin is not a destination, and skipping it skips what
                    // is inside it: a binned folder has the bin as its parent.
                    if (pg_recycle_is_inside($folder_row_id)) {
                        continue;
                    }

                    if (check_edit_access($folder_row_id)) {
                        $folder_choices[] = array(
                            'id' => $folder_row_id,
                            'name' => (string) $folder_row['folder_name'],
                            'depth' => $depth,
                            'archived' => ($folder_row['folder_archived'] == '1'));
                    }

                    $collect_folders($folder_row_id, $depth + 1);
                }
            };

            $collect_folders(0, 0);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'folders' => $folder_choices));

            break;

        // ── Upload (drag files from the computer into the folder) ───────
        case 'explorer_upload':

            $folder_id = (int) (isset($request['target_folder_id']) ? $request['target_folder_id'] : 0);

            if (($folder_id <= 0) || (check_edit_access($folder_id) == false) || pg_recycle_is_inside($folder_id)) {
                respond(array('status' => 'error', 'message' => lang('You do not have access to upload files to this folder.')));
            }

            $name = isset($request['name']) ? $request['name'] : '';

            // The payload stays inside $request the whole way through. Lifting it into
            // a local first, and then trimming the data URL prefix off that local, made
            // two more copies of a file that is already megabytes long.
            if (($name == '') || (isset($request['data']) == false) || ($request['data'] == '')) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            // A name the web server would run or read as its own settings is
            // refused, not renamed: the operator should know it did not arrive.
            // Checked before a byte of it is decoded.
            if (pg_upload_name_blocked($name)) {
                log_activity(lang(array('string' => 'upload of {var:1} was refused because files of that type are not allowed', 'vars' => $name)), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => pg_upload_blocked_message($name)));
            }

            $upload_limits = pg_upload_limits();

            // Measured from the length of the base64 text, so a file that is over the
            // limit is refused before a single byte of it is decoded.
            $expected_size = pg_explorer_base64_length($request['data']);

            if (($upload_limits['json_max'] > 0) && ($expected_size > $upload_limits['json_max'])) {
                respond(array(
                    'status' => 'error',
                    'message' => lang(array(
                        'string' => 'The file is too large. The maximum allowed size is {var:1}.',
                        'vars' => convert_bytes_to_string($upload_limits['json_max'])))));
            }

            // Same rules the upload screen applies: URL-safe ASCII name and
            // a unique slot in the shared page/file namespace.
            $name = prepare_file_name($name);
            $name = get_unique_name(array('name' => $name, 'type' => 'file'));

            $file_path = FILE_DIRECTORY_PATH . '/' . $name;

            if (pg_explorer_write_base64($request['data'], $file_path) === false) {
                respond(array('status' => 'error', 'message' => lang('The file could not be saved. Check write permission for the file directory.')));
            }

            $file_extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $image_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp');
            $is_image = in_array($file_extension, $image_types, true);

            // What the upload window asked for. Both are off unless somebody
            // turned them on: a file that arrives is the file that was sent,
            // and re-encoding it is not something to do quietly.
            $to_webp = (isset($request['to_webp']) && ($request['to_webp'] == true));
            $optimize = (isset($request['optimize']) && ($request['optimize'] == true));

            $image_report = null;

            if ($is_image && ($to_webp || $optimize)) {

                // A 24 megapixel photo takes a couple of seconds to resample,
                // and an upload is a queue of them. The same allowance
                // add_file.php gives itself for the same work.
                @set_time_limit(120);

                $image_settings = pg_image_settings();

                // Optimize means the site's own picture profile -- the same
                // ceiling and quality the upload screen applies -- so a file
                // that comes in through the file manager and one that comes in
                // through add_file.php end up the same size.
                //
                // WebP on its own only changes the format: nothing is scaled,
                // because "convert to webp" is not a request to make the
                // picture smaller on the page.
                $image_options = array();

                if ($optimize) {
                    $image_options['max_dimension'] = $image_settings['product_max_dimension'];
                    $image_options['min_dimension'] = $image_settings['product_min_dimension'];
                    $image_options['quality'] = $image_settings['resize_quality'];
                } elseif (isset($image_settings['resize_quality'])) {
                    $image_options['quality'] = $image_settings['resize_quality'];
                }

                if ($to_webp && ($file_extension != 'webp')) {
                    $image_options['format'] = 'webp';
                }

                $image_report = pg_process_image_file($file_path, $image_options);

                // pg_process_image_file() writes back over the path it was
                // given, so after a format change the bytes are webp and the
                // name still says jpg. The name is the address this file is
                // served from, so it has to follow -- and it goes back through
                // the same namespace check, because ".webp" may be taken by
                // something else even though ".jpg" was free.
                if (($image_report['status'] === 'success')
                    && (isset($image_options['format']))
                    && ($image_report['changed'])) {

                    $webp_name = get_unique_name(array(
                        'name' => prepare_file_name(pathinfo($name, PATHINFO_FILENAME) . '.webp'),
                        'type' => 'file'));

                    $webp_path = FILE_DIRECTORY_PATH . '/' . $webp_name;

                    if (@rename($file_path, $webp_path)) {
                        $name = $webp_name;
                        $file_path = $webp_path;
                        $file_extension = 'webp';
                    }
                }

                @clearstatcache(true, $file_path);
            }

            // Cache image dimensions on insert; getimagesize() reads only the
            // header, and it saves the lazy backfill a full pass later.
            $sql_image_fields = '';
            $sql_image_values = '';

            if ($is_image) {

                // The report already measured what it produced, so an image
                // that has just been through it is not measured twice.
                if (($image_report) && ($image_report['status'] === 'success') && ($image_report['width'] > 0)) {
                    $sql_image_fields = 'image_width, image_height,';
                    $sql_image_values = "'" . e((int) $image_report['width']) . "', '" . e((int) $image_report['height']) . "',";
                } else {

                    $image_size = @getimagesize($file_path);

                    if (is_array($image_size)) {
                        $sql_image_fields = 'image_width, image_height,';
                        $sql_image_values = "'" . e((int) $image_size[0]) . "', '" . e((int) $image_size[1]) . "',";
                    }
                }
            }

            // Flagged optimized so the Files screen does not offer to do it
            // again -- the badge there would promise a saving already taken.
            // Only for a real optimize: a plain format change has not been
            // held to the site's quality profile.
            $optimized_flag = (($optimize) && ($image_report) && ($image_report['status'] === 'success')) ? '1' : '0';

            // A designer's flag, and only a designer's: role 2 and 3 have no
            // say over which files the design owns. Same gate add_file.php has.
            $design_flag = ((isset($request['design']) && ($request['design'] == true)) && ($user['role'] <= 1)) ? '1' : '0';

            $description = isset($request['description']) ? trim((string) $request['description']) : '';

            db(
                "INSERT INTO files (
                    name,
                    folder,
                    description,
                    type,
                    size,
                    design,
                    optimized,
                    " . $sql_image_fields . "
                    user,
                    timestamp)
                VALUES (
                    '" . e($name) . "',
                    '" . e($folder_id) . "',
                    '" . e($description) . "',
                    '" . e($file_extension) . "',
                    '" . e(filesize($file_path)) . "',
                    '" . e($design_flag) . "',
                    '" . e($optimized_flag) . "',
                    " . $sql_image_values . "
                    '" . e($user['id']) . "',
                    UNIX_TIMESTAMP())");

            $file_id = mysqli_insert_id(db::$con);

            log_activity(lang(array('string' => 'The file, {var:1}, has been uploaded.', 'vars' => h($name))), $_SESSION['sessionusername']);

            // What was done to the picture is worth its own line: the file on
            // the site is no longer byte for byte the one that was sent, and
            // the only place that is recorded is here.
            if (($image_report) && ($image_report['status'] === 'success') && ($image_report['changed'])) {
                log_activity(lang(array(
                    'string' => 'image ({var:1}) was optimized on upload ({var:2} -> {var:3})',
                    'vars' => array(
                        h($name),
                        convert_bytes_to_string($image_report['bytes_before']),
                        convert_bytes_to_string($image_report['bytes_after'])))),
                    $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'file' => array(
                    'id' => (int) $file_id,
                    'name' => $name,
                    'size_label' => convert_bytes_to_string(filesize($file_path))),
                'message' => lang(array('string' => 'The file, {var:1}, has been uploaded.', 'vars' => h($name)))));
            break;

        // ── Bulk optimize selected images ───────────────────────────────
        case 'explorer_optimize':

            // Recompressing images is the slow part of this screen; nothing
            // below writes to the session, so the lock is not held for it.
            pg_explorer_release_session();

            $items = isset($request['items']) ? $request['items'] : array();

            if ((is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            // Recompressing many images takes a while; the same allowance
            // the files screen gives itself.
            @ini_set('max_execution_time', '600');
            @ini_set('memory_limit', '-1');

            require_once(dirname(__FILE__) . '/optimize_image.php');

            // 'resize' also scales the longest edge down to the configured
            // ceiling — and, unlike plain optimize, is offered to images
            // that are already optimized (compressed but still oversized is
            // the exact case it exists for).
            $optimize_mode = ((isset($request['mode']) && ($request['mode'] == 'resize')) ? 'resize' : 'optimize');

            $optimizable_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp');
            $optimized_count = 0;
            $errors = array();

            // What each file had to say for itself. A run over one file
            // reports that one file's figures -- the sizes before and after
            // and the percentage -- rather than "1 images were optimized",
            // which is what the old optimize.php screen told the operator
            // and what the file manager now tells them in its place.
            $reports = array();
            $skipped = array();

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($item_id <= 0) {
                    continue;
                }

                $file = db_item("SELECT id, name, folder, type, design, optimized FROM files WHERE id = '" . e($item_id) . "'");

                if (!$file) {
                    continue;
                }

                if (
                    (check_edit_access($file['folder']) == false)
                    || (($file['design'] == 1) && ($user['role'] > 1))
                ) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                    continue;
                }

                if (in_array(mb_strtolower($file['type']), $optimizable_types, true) == false) {
                    $skipped[] = lang(array('string' => 'Sorry, we don\'t support optimizing that type of file ({var:1}). The following types are supported: jpg, jpeg, png, gif, bmp, tiff.', 'vars' => $file['name']));
                    continue;
                }

                if (($optimize_mode != 'resize') && ($file['optimized'] == 1)) {
                    $skipped[] = lang(array('string' => 'Sorry, that image ({var:1}) has already been optimized.', 'vars' => $file['name']));
                    continue;
                }

                $result = optimize_image($file['id'], $optimize_mode);

                if ($result['status'] == 'success') {
                    $optimized_count++;
                    $reports[] = $result['message'];
                } else {
                    $errors[] = $result['message'];
                }
            }

            // One file, one answer: its own figures, or the reason it was left
            // alone. Several files get the count, as before.
            if ((count($items) == 1) && (count($errors) == 0)) {

                if (($optimized_count == 1) && (count($reports) == 1)) {
                    respond(array(
                        'status' => 'success',
                        'request' => $type,
                        'optimized' => 1,
                        'errors' => array(),
                        'message' => $reports[0]));
                }

                if (($optimized_count == 0) && (count($skipped) == 1)) {
                    respond(array(
                        'status' => 'error',
                        'request' => $type,
                        'optimized' => 0,
                        'errors' => array($skipped[0]),
                        'message' => $skipped[0]));
                }
            }

            if ($optimized_count > 0) {
                if ($optimized_count > 1) {
                    $message = lang(array('string' => '{var:1} images were optimized', 'vars' => $optimized_count));
                } else {
                    $message = lang('1 image was optimized');
                }

                log_activity($message, $_SESSION['sessionusername']);
            } else {
                $message = lang(array('string' => '{var:1} images were optimized', 'vars' => 0));
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((($optimized_count > 0)) ? 'partial' : 'error'),
                'request' => $type,
                'optimized' => $optimized_count,
                'errors' => $errors,
                'message' => (count($errors) > 0) ? implode(' ', $errors) : $message));
            break;

        // ── Convert selected images to WebP ─────────────────────────────
        //
        // The row's name is the address, so the .webp extension means a new
        // address. Disk first, row second, old file last: the new file is
        // written (temp + rename), then the row is pointed at it, and only
        // then is the old file removed — a crash between the steps leaves an
        // orphan on disk for clean_up.php, never a row without its file.
        case 'explorer_webp':

            $items = isset($request['items']) ? $request['items'] : array();

            if ((is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            @ini_set('max_execution_time', '600');
            @ini_set('memory_limit', '-1');

            // webp is deliberately not a source: converting webp to webp is
            // the optimize button's job.
            $source_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff');
            $converted_count = 0;
            $errors = array();

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($item_id <= 0) {
                    continue;
                }

                $file = db_item("SELECT id, name, folder, type, design FROM files WHERE id = '" . e($item_id) . "'");

                if (!$file) {
                    continue;
                }

                if (
                    (check_edit_access($file['folder']) == false)
                    || (($file['design'] == 1) && ($user['role'] > 1))
                ) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                    continue;
                }

                if (in_array(mb_strtolower($file['type']), $source_types, true) == false) {
                    continue;
                }

                $old_path = FILE_DIRECTORY_PATH . '/' . $file['name'];

                $report = pg_process_image_file($old_path, array('write' => false, 'format' => 'webp'));

                if (($report['status'] !== 'success') || ($report['data'] === null)) {

                    if ($report['reason'] === 'animated') {
                        $errors[] = lang(array('string' => 'Sorry, {var:1} is an animated image. Optimizing it would leave only the first frame, so it was left alone.', 'vars' => $file['name']));
                    } elseif ($report['reason'] === 'missing') {
                        $errors[] = lang(array('string' => 'Sorry, {var:1} is recorded here but is not on the disk.', 'vars' => $file['name']));
                    } elseif ($report['reason'] === 'no_webp') {
                        $errors[] = lang('This server cannot produce WebP images (neither Imagick nor GD supports it).');
                        break;
                    } elseif ($report['reason'] === 'no_library') {
                        $errors[] = lang('No image library (Imagick or GD) is installed on this server, so images cannot be optimized or resized.');
                        break;
                    } else {
                        $errors[] = lang(array('string' => 'Sorry, we could not convert that image ({var:1}).', 'vars' => $file['name']));
                    }

                    continue;
                }

                // New address: the old base name with .webp, made unique
                // across the shared page/file namespace.
                $dot = mb_strrpos($file['name'], '.');
                $base = ($dot === false) ? $file['name'] : mb_substr($file['name'], 0, $dot);
                $new_name = get_unique_name(array('name' => prepare_file_name($base . '.webp'), 'type' => 'file'));
                $new_path = FILE_DIRECTORY_PATH . '/' . $new_name;

                $temporary_path = $new_path . '.pgwebp' . getmypid();

                if ((@file_put_contents($temporary_path, $report['data']) === false) || (@rename($temporary_path, $new_path) == false)) {
                    @unlink($temporary_path);
                    $errors[] = lang(array('string' => 'The WebP file could not be written to the file system ({var:1}).', 'vars' => $file['name']));
                    continue;
                }

                @clearstatcache(true, $new_path);

                // The engine already recompressed, so the optimized flag is
                // set — running the optimize button over the result would
                // only degrade it a second time.
                db(
                    "UPDATE files
                    SET
                        name = '" . e($new_name) . "',
                        type = 'webp',
                        size = '" . e(strlen($report['data'])) . "',
                        optimized = '1',
                        optimization_percent = '0',
                        image_width = '" . (int) $report['width'] . "',
                        image_height = '" . (int) $report['height'] . "',
                        timestamp = UNIX_TIMESTAMP(),
                        user = '" . USER_ID . "'
                    WHERE id = '" . e($file['id']) . "'");

                @unlink($old_path);

                log_activity(lang(array('string' => 'file ({var:1}) was converted to {var:2}', 'vars' => array($file['name'], 'WEBP'))), $_SESSION['sessionusername']);

                $converted_count++;
            }

            if ($converted_count > 1) {
                $message = lang(array('string' => '{var:1} images were converted to WebP', 'vars' => $converted_count));
            } elseif ($converted_count == 1) {
                $message = lang('1 image was converted to WebP');
            } else {
                $message = lang(array('string' => '{var:1} images were converted to WebP', 'vars' => 0));
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((($converted_count > 0)) ? 'partial' : 'error'),
                'request' => $type,
                'converted' => $converted_count,
                'errors' => $errors,
                'message' => (count($errors) > 0) ? implode(' ', $errors) : $message));
            break;

        // ── Folder settings: order, archive, page styles ────────────────
        //
        // The everyday subset of edit_folder.php, with its rules: order and
        // archive for anyone who can edit the folder, the two style fields
        // only for managers and above — exactly the roles that screen gives
        // the dropdowns to.
        case 'explorer_folder_settings_get':

            $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

            $folder = db_item("SELECT folder_id, folder_name, folder_order, folder_style, mobile_style_id, folder_archived FROM folder WHERE folder_id = '" . e($folder_id) . "'");

            if (!$folder) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
            }

            if (check_edit_access($folder_id) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $styles = null;

            if ($user['role'] < 3) {
                $styles = array(
                    'desktop' => select_style($folder['folder_style']),
                    'mobile' => get_mobile_style_options($folder['mobile_style_id']));
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'folder' => array(
                    'id' => (int) $folder['folder_id'],
                    'name' => $folder['folder_name'],
                    'order' => (int) $folder['folder_order'],
                    'archived' => ($folder['folder_archived'] == '1'),
                    'access_control_type' => get_access_control_type($folder_id)),
                'styles' => $styles));
            break;

        case 'explorer_folder_settings_set':

            $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

            $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($folder_id) . "'");

            if (!$folder) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
            }

            if (check_edit_access($folder_id) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $order = (int) (isset($request['order']) ? $request['order'] : 0);
            $archived = ((isset($request['archived']) ? (string) $request['archived'] : '') == '1') ? '1' : '';

            $sql_style_fields = '';

            if ($user['role'] < 3) {
                $style = (int) (isset($request['style']) ? $request['style'] : 0);
                $mobile_style_id = (int) (isset($request['mobile_style_id']) ? $request['mobile_style_id'] : 0);

                $sql_style_fields =
                    "folder_style = '" . e($style) . "',
                    mobile_style_id = '" . e($mobile_style_id) . "',";
            }

            db(
                "UPDATE folder
                SET
                    folder_order = '" . e($order) . "',
                    folder_archived = '" . e($archived) . "',
                    $sql_style_fields
                    folder_timestamp = UNIX_TIMESTAMP(),
                    folder_user = '" . USER_ID . "'
                WHERE folder_id = '" . e($folder_id) . "'");

            log_activity(lang(array('string' => 'folder ({var:1}) was modified', 'vars' => array($folder['folder_name']))), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'message' => lang('The folder was edited successfully.')));
            break;

        // ── Turn the design flag on or off for a set of files ───────────
        //
        // Design files are the theme's own assets: everybody sees them, only
        // designers may touch them. The flag itself is therefore a designer
        // decision, which is the rule edit_files.php applies too.
        case 'explorer_files_design':

            if ($user['role'] > 1) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $items = isset($request['items']) ? $request['items'] : array();
            $design = ((isset($request['design']) ? (string) $request['design'] : '') == '1') ? '1' : '0';

            if ((is_array($items) == false) || (count($items) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $allowed = array();
            $errors = array();

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($item_id <= 0) {
                    continue;
                }

                $file = db_item("SELECT id, name, folder FROM files WHERE id = '" . e($item_id) . "'");

                if (!$file) {
                    continue;
                }

                if (check_edit_access($file['folder']) == false) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                    continue;
                }

                $allowed[] = (int) $file['id'];
            }

            if (count($allowed) > 0) {
                db(
                    "UPDATE files
                    SET
                        design = '" . e($design) . "',
                        timestamp = UNIX_TIMESTAMP(),
                        user = '" . USER_ID . "'
                    WHERE id IN (" . implode(',', $allowed) . ")");

                log_activity(lang(array('string' => '{var:1} files were updated', 'vars' => count($allowed))), $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((count($allowed) > 0) ? 'partial' : 'error'),
                'request' => $type,
                'updated' => count($allowed),
                'errors' => $errors,
                'message' => (count($errors) > 0)
                    ? implode(' ', $errors)
                    : lang(array('string' => '{var:1} files were updated', 'vars' => count($allowed)))));
            break;

        // ── The file window ─────────────────────────────────────────────
        //
        // What edit_file.php was: a file's name, folder, description and
        // design flag, and for a text format its contents, in one place --
        // now a window on the file manager rather than a screen of its own.
        // The text is read here and written by explorer_file_save; every
        // other change to a picture goes through the image editor, and the
        // conversions go through the actions that already exist for them.
        case 'explorer_file_get':

            $file_id = (int) (isset($request['id']) ? $request['id'] : 0);
            $file = pg_explorer_file_row($file_id);

            if (!$file) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the file could not be found.')));
            }

            if (pg_explorer_file_editable_by($file, $user) == false) {
                log_activity(lang('access denied because user does not have access to file'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $payload = pg_explorer_file_payload($file, $user);
            $extension = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $path = FILE_DIRECTORY_PATH . '/' . $file['name'];

            $payload['editable'] = in_array($extension, pg_explorer_editable_formats(), true);
            $payload['viewable'] = in_array($extension, pg_explorer_viewable_formats(), true);
            $payload['editor_mode'] = pg_explorer_editor_mode($extension);
            $payload['content'] = null;
            $payload['content_too_large'] = false;
            $payload['on_disk'] = is_file($path);

            if ($payload['viewable'] && $payload['on_disk']) {

                if (filesize($path) > pg_explorer_editor_max_bytes()) {
                    $payload['content_too_large'] = true;
                } else {
                    $payload['content'] = (string) @file_get_contents($path);
                }
            }

            $payload['editor_max_label'] = convert_bytes_to_string(pg_explorer_editor_max_bytes());

            respond(array('status' => 'success', 'request' => $type, 'file' => $payload));
            break;

        case 'explorer_file_save':

            $file_id = (int) (isset($request['id']) ? $request['id'] : 0);
            $file = pg_explorer_file_row($file_id);

            if (!$file) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the file could not be found.')));
            }

            if (pg_explorer_file_editable_by($file, $user) == false) {
                log_activity(lang('access denied to modify files because user does not have access to file'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $requested_name = trim(isset($request['name']) ? (string) $request['name'] : $file['name']);

            // Renaming "photo.jpg" to "photo.php" is uploading a PHP file
            // with an extra step, so the window keeps the upload rule.
            if (($requested_name != $file['name']) && pg_upload_name_blocked($requested_name)) {
                respond(array('status' => 'error', 'message' => pg_upload_blocked_message($requested_name)));
            }

            // The same preparation every other way of naming a file applies:
            // ASCII conversion, reserved names, length and character rules.
            $name = prepare_file_name($requested_name);

            if ($name == '') {
                respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
            }

            if (($name != $file['name']) && (check_name_availability(array('name' => $name, 'ignore_item_id' => $file_id, 'ignore_item_type' => 'file')) == false)) {
                respond(array('status' => 'error', 'message' => lang('That name is already in use. Please choose a different name.')));
            }

            $folder_id = (int) (isset($request['folder_id']) ? $request['folder_id'] : $file['folder']);

            if ($folder_id != (int) $file['folder']) {

                if (($folder_id <= 0) || (check_edit_access($folder_id) == false)) {
                    respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
                }

                // The bin is only fed by the delete action, so restore
                // metadata always exists for what sits inside it.
                if (pg_recycle_is_inside($folder_id)) {
                    respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
                }
            }

            $description = isset($request['description']) ? (string) $request['description'] : (string) $file['description'];

            // The design flag is a designer's decision, and the switch is not
            // drawn for anyone else -- so for anyone else the value is the
            // one the file already carries.
            $design = ($user['role'] <= 1)
                ? ((isset($request['design']) && ((string) $request['design'] == '1')) ? '1' : '0')
                : (($file['design'] == 1) ? '1' : '0');

            $old_path = FILE_DIRECTORY_PATH . '/' . $file['name'];
            $new_path = FILE_DIRECTORY_PATH . '/' . $name;

            // The record's name is the address, so the disk file moves first
            // and the row is only told if it did -- a row pointing at nothing
            // is exactly the state this order exists to prevent.
            if ($name != $file['name']) {
                if ((file_exists($old_path) == true) && (@rename($old_path, $new_path) == false)) {
                    respond(array('status' => 'error', 'message' => lang('The file could not be renamed on the file system. Check write permission for the file directory.')));
                }
            }

            $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $size_sql = '';

            // Text is written only for a format the editor may write, and only
            // when the window sent some: a window opened on a picture sends
            // none, and a file too large for the editor was never shown.
            if (isset($request['content']) && is_string($request['content']) && in_array($extension, pg_explorer_editable_formats(), true)) {

                if (@file_put_contents($new_path, $request['content']) === false) {
                    respond(array('status' => 'error', 'message' => lang('The file could not be written to the file system.')));
                }

                clearstatcache(true, $new_path);
                $size_sql = "size = '" . e((int) filesize($new_path)) . "',";
            }

            db(
                "UPDATE files SET
                    name = '" . e($name) . "',
                    folder = '" . e($folder_id) . "',
                    description = '" . e($description) . "',
                    type = '" . e($extension) . "',
                    design = '" . e($design) . "',
                    " . $size_sql . "
                    timestamp = UNIX_TIMESTAMP(),
                    user = '" . e($user['id']) . "'
                WHERE id = '" . e($file_id) . "'");

            log_activity(lang(array('string' => 'file ({var:1}) was modified', 'vars' => $name)), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'file' => pg_explorer_file_payload(pg_explorer_file_row($file_id), $user),
                'message' => lang('The file was edited successfully.')));
            break;

        // ── Turn a picture a quarter to the right ────────────────────────
        //
        // A photo taken with the phone on its side is the reason this exists:
        // one press per quarter turn, from the file window, with no editor to
        // open and nothing to save afterwards. To the right only -- a second
        // press finishes a half turn, a third the other direction -- because a
        // direction switch for a rare need is a control most people would
        // never touch.
        //
        // The pixels go through the same engine as optimize and webp, which
        // bakes the EXIF orientation in first, so the turn is a turn of what
        // the operator was looking at.
        case 'explorer_rotate':

            $file_id = (int) (isset($request['id']) ? $request['id'] : 0);
            $file = pg_explorer_file_row($file_id);

            if (!$file) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the file could not be found.')));
            }

            if (pg_explorer_file_editable_by($file, $user) == false) {
                log_activity(lang('access denied to modify files because user does not have access to file'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $extension = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // The formats the engine can decode and encode; svg has no pixels
            // to turn and is not offered the button either.
            if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp'), true) == false) {
                respond(array('status' => 'error', 'message' => lang('Sorry, that type of file cannot be rotated here.')));
            }

            $path = FILE_DIRECTORY_PATH . '/' . $file['name'];

            if (is_file($path) == false) {
                respond(array('status' => 'error', 'message' => lang('The file is recorded here but is not on the disk.')));
            }

            // A large photo takes a couple of seconds to re-encode.
            @set_time_limit(120);

            // Encoded at a high quality rather than the optimize ladder: the
            // operator asked for the picture the other way up, not for a
            // lighter file, and each turn would otherwise cost another
            // generation of compression.
            $report = pg_process_image_file($path, array('rotate' => 90, 'quality' => 92));

            if ($report['status'] !== 'success') {

                if ($report['reason'] === 'animated') {
                    $message = lang(array('string' => 'Sorry, {var:1} is an animated image. Optimizing it would leave only the first frame, so it was left alone.', 'vars' => $file['name']));
                } elseif ($report['reason'] === 'missing') {
                    $message = lang(array('string' => 'Sorry, {var:1} is recorded here but is not on the disk.', 'vars' => $file['name']));
                } elseif ($report['reason'] === 'no_library') {
                    $message = lang('No image library (Imagick or GD) is installed on this server, so images cannot be optimized or resized.');
                } else {
                    $message = lang(array('string' => 'Sorry, we could not rotate that image ({var:1}).', 'vars' => $file['name']));
                }

                respond(array('status' => 'error', 'message' => $message));
            }

            @clearstatcache(true, $path);

            // Its edges swapped and its bytes changed; and it has been
            // re-encoded outside the site's picture profile, so the optimized
            // mark no longer holds and the button is offered again.
            db(
                "UPDATE files SET
                    size = '" . e((int) filesize($path)) . "',
                    image_width = '" . e((int) $report['width']) . "',
                    image_height = '" . e((int) $report['height']) . "',
                    optimized = '0',
                    timestamp = UNIX_TIMESTAMP(),
                    user = '" . e($user['id']) . "'
                WHERE id = '" . e($file_id) . "'");

            log_activity(lang(array('string' => 'image ({var:1}) was rotated', 'vars' => $file['name'])), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'file' => pg_explorer_file_payload(pg_explorer_file_row($file_id), $user),
                'message' => lang('The image was rotated.')));
            break;

        // ── Short links: the names that stand for something else ─────────
        case 'explorer_short_links_list':

            if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
                $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
            }

            $view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

            if (in_array($view_type, array('grid', 'list'), true) == false) {
                $view_type = 'grid';
            }

            $short_link_files = array();

            foreach (pg_short_link_visible_rows($user, false) as $short_link_row) {
                $short_link_files[] = pg_short_link_item($short_link_row);
            }

            // A binned short link is in the Recycle Bin with the folders, the
            // pages and the files, so the button on this screen carries the
            // same number the folder screens carry and leads to the same place.
            $short_link_recycle = array(
                'available' => pg_recycle_ready(),
                'folder_id' => pg_recycle_folder_id(false),
                'inside' => false,
                'retention_days' => pg_recycle_ready() ? pg_recycle_retention_days() : 0,
                'count' => pg_recycle_ready()
                    ? (int) db_value("SELECT COUNT(*) FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql() . ")")
                    : 0);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'view_type' => $view_type,
                // The role travels with it because this view can be opened
                // straight from a link, with no folder listing before it to
                // have said who is looking -- and the Recycle Bin button is
                // drawn from the role.
                'capabilities' => array(
                    'role' => (int) $user['role'],
                    'show_product_images' => (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES),
                'recycle' => $short_link_recycle,
                'folders' => array(),
                'files' => $short_link_files,
                'total' => count($short_link_files)));

            break;

        // ── What the wizard offers, built from the classic screen's lists ─
        case 'explorer_short_link_options':

            respond(array(
                'status' => 'success',
                'request' => $type,
                'options' => array(
                    'page' => pg_short_link_options_list(get_page_options()),
                    'catalog_page' => pg_short_link_options_list(get_page_options('', 'catalog')),
                    'catalog_detail_page' => pg_short_link_options_list(get_page_options('', 'catalog detail')),
                    'product_group' => pg_short_link_options_list(get_product_group_options(0, 0, 0, 0, array(), TRUE, 'array', TRUE)),
                    'product' => pg_short_link_options_list(get_product_options()),
                    'file' => pg_short_link_options_list(get_file_options(true)))));

            break;

        // ── Create ──────────────────────────────────────────────────────
        //
        // The wizard settles where the link goes; the name is settled after,
        // in place, the way a new folder is.  So the row is written with a
        // free placeholder name and the screen walks straight into renaming
        // it.  Nothing is written until the wizard is finished: close it and
        // this action is never called.
        case 'explorer_short_link_create':

            $short_link_read = pg_short_link_read_request($request, $user);

            if (isset($short_link_read['error'])) {
                respond(array(
                    'status' => 'error',
                    'field' => $short_link_read['error'],
                    'message' => $short_link_read['message']));
            }

            $short_link_name = pg_short_link_free_name(isset($request['name']) ? $request['name'] : '');

            if ($short_link_name == '') {
                respond(array('status' => 'error', 'message' => lang('The name that you entered is already in use, so please enter a different name.')));
            }

            $short_link_fields = '';
            $short_link_values = '';

            foreach ($short_link_read['columns'] as $short_link_column => $short_link_value) {
                $short_link_fields .= $short_link_column . ', ';
                $short_link_values .= "'" . e($short_link_value) . "', ";
            }

            db("INSERT INTO short_links (
                    name,
                    destination_type,
                    page_id,
                    " . $short_link_fields . "
                    tracking_code,
                    created_user_id,
                    created_timestamp,
                    last_modified_user_id,
                    last_modified_timestamp)
                VALUES (
                    '" . e($short_link_name) . "',
                    '" . e($short_link_read['type']) . "',
                    '" . e($short_link_read['page_id']) . "',
                    " . $short_link_values . "
                    '" . e($short_link_read['tracking_code']) . "',
                    '" . e($user['id']) . "',
                    UNIX_TIMESTAMP(),
                    '" . e($user['id']) . "',
                    UNIX_TIMESTAMP())");

            $short_link_id = (int) mysqli_insert_id(db::$con);

            log_activity(lang(array('string' => 'short link ({var:1}) was created', 'vars' => $short_link_name)), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'id' => $short_link_id,
                'name' => $short_link_name,
                'message' => lang('The short link has been created.')));

            break;

        // ── Rename ──────────────────────────────────────────────────────
        case 'explorer_short_link_rename':

            $short_link_row = pg_short_link_by_id($user, isset($request['item_id']) ? $request['item_id'] : 0);

            if (!$short_link_row) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $short_link_name = pg_short_link_clean_name(isset($request['name']) ? $request['name'] : '');

            if ($short_link_name == '') {
                respond(array('status' => 'error', 'message' => lang('The name may only contain letters, numbers, periods, underscores, hyphens, forward slashes and square brackets.')));
            }

            if (check_name_availability(array(
                    'name' => $short_link_name,
                    'ignore_item_id' => $short_link_row['id'],
                    'ignore_item_type' => 'short_link')) == false) {

                respond(array('status' => 'error', 'message' => lang('The name that you entered is already in use, so please enter a different name.')));
            }

            db("UPDATE short_links
                SET name = '" . e($short_link_name) . "',
                    last_modified_user_id = '" . e($user['id']) . "',
                    last_modified_timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . e($short_link_row['id']) . "'");

            log_activity(lang(array('string' => 'short link ({var:1}) was renamed', 'vars' => $short_link_name)), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'id' => (int) $short_link_row['id'],
                'name' => $short_link_name,
                'message' => lang('The short link has been updated.')));

            break;

        // ── Change where an existing one points, and what it is called ───
        //
        // The same reading of the same fields the create uses, so the two can
        // never disagree about what a valid destination is.
        //
        // The name is renamed in place in the list as well, the way a folder
        // is, and that action stays. This one takes a name too because the
        // window that says where a link goes is where somebody looks for what
        // it is called; being able to rename in only one of the two places is
        // the kind of thing an operator reads as the feature being broken.
        case 'explorer_short_link_update':

            $short_link_row = pg_short_link_by_id($user, isset($request['item_id']) ? $request['item_id'] : 0);

            if (!$short_link_row) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            // Checked before anything is written: a name that cannot be used
            // must not leave the destination half saved behind it.
            $short_link_sql_name = '';

            if (array_key_exists('name', $request)) {

                $short_link_name = pg_short_link_clean_name($request['name']);

                if ($short_link_name == '') {
                    respond(array(
                        'status' => 'error',
                        'field' => 'name',
                        'message' => lang('The name may only contain letters, numbers, periods, underscores, hyphens, forward slashes and square brackets.')));
                }

                if ($short_link_name != $short_link_row['name']) {

                    if (check_name_availability(array(
                            'name' => $short_link_name,
                            'ignore_item_id' => $short_link_row['id'],
                            'ignore_item_type' => 'short_link')) == false) {

                        respond(array(
                            'status' => 'error',
                            'field' => 'name',
                            'message' => lang('The name that you entered is already in use, so please enter a different name.')));
                    }

                    $short_link_sql_name = "name = '" . e($short_link_name) . "',";
                }
            }

            $short_link_read = pg_short_link_read_request($request, $user);

            if (isset($short_link_read['error'])) {
                respond(array(
                    'status' => 'error',
                    'field' => $short_link_read['error'],
                    'message' => $short_link_read['message']));
            }

            // Every column that belongs to a destination is written on every
            // save, the ones this type does not use included. A link changed
            // from a product to an address would otherwise keep the product id
            // it no longer means, and the next screen to read the row would
            // believe it.
            $short_link_columns = array(
                'product_group_id' => 0,
                'product_id' => 0,
                'url' => '',
                'file_id' => 0);

            foreach ($short_link_read['columns'] as $short_link_column => $short_link_value) {
                $short_link_columns[$short_link_column] = $short_link_value;
            }

            db("UPDATE short_links
                SET " . $short_link_sql_name . "
                    destination_type = '" . e($short_link_read['type']) . "',
                    page_id = '" . e($short_link_read['page_id']) . "',
                    product_group_id = '" . e($short_link_columns['product_group_id']) . "',
                    product_id = '" . e($short_link_columns['product_id']) . "',
                    url = '" . e($short_link_columns['url']) . "',
                    file_id = '" . e($short_link_columns['file_id']) . "',
                    tracking_code = '" . e($short_link_read['tracking_code']) . "',
                    last_modified_user_id = '" . e($user['id']) . "',
                    last_modified_timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . e($short_link_row['id']) . "'");

            // The log says what the link is called now, not what it was: the
            // name is the address, and the old one no longer answers.
            $short_link_logged = ($short_link_sql_name != '') ? $short_link_name : $short_link_row['name'];

            log_activity(lang(array('string' => 'short link ({var:1}) was updated', 'vars' => $short_link_logged)), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'id' => (int) $short_link_row['id'],
                'name' => $short_link_logged,
                'message' => lang('The short link has been updated.')));

            break;

        // ── Duplicate, which is also what paste does here ────────────────
        //
        // A short link belongs to no folder, so there is nowhere to paste one
        // into.  Copy then paste therefore means the same thing duplicate
        // does: a second link to the same place, under a free name.
        case 'explorer_short_link_duplicate':

            $short_link_made = array();
            $short_link_failed = array();

            foreach ((isset($request['items']) && is_array($request['items'])) ? $request['items'] : array() as $short_link_entry) {

                $short_link_row = pg_short_link_by_id($user, isset($short_link_entry['id']) ? $short_link_entry['id'] : 0);

                if (!$short_link_row) {
                    $short_link_failed[] = lang('Access denied');
                    continue;
                }

                $short_link_name = pg_short_link_free_name($short_link_row['name']);

                if ($short_link_name == '') {
                    $short_link_failed[] = lang('The name that you entered is already in use, so please enter a different name.');
                    continue;
                }

                db("INSERT INTO short_links (
                        name, destination_type, page_id, product_group_id, product_id,
                        url, file_id, tracking_code,
                        created_user_id, created_timestamp,
                        last_modified_user_id, last_modified_timestamp)
                    SELECT
                        '" . e($short_link_name) . "', destination_type, page_id, product_group_id, product_id,
                        url, file_id, tracking_code,
                        '" . e($user['id']) . "', UNIX_TIMESTAMP(),
                        '" . e($user['id']) . "', UNIX_TIMESTAMP()
                    FROM short_links
                    WHERE id = '" . e($short_link_row['id']) . "'");

                $short_link_made[] = $short_link_name;

                log_activity(lang(array('string' => 'short link ({var:1}) was created', 'vars' => $short_link_name)), $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => (count($short_link_made) > 0) ? 'success' : 'error',
                'request' => $type,
                'names' => $short_link_made,
                'errors' => $short_link_failed,
                'message' => (count($short_link_made) > 0)
                    ? lang('The short link has been created.')
                    : (count($short_link_failed) > 0 ? $short_link_failed[0] : lang('Invalid request.'))));

            break;

        // ── Delete ──────────────────────────────────────────────────────
        //
        // Straight out, with no bin behind it.  The bin stores an item type
        // it does not know short links in, and a short link holds nothing:
        // making one again is the wizard and a name, which is less work than
        // widening the schema would be.
        case 'explorer_short_link_delete':

            $short_link_deleted = 0;
            $short_link_failed = array();

            foreach ((isset($request['items']) && is_array($request['items'])) ? $request['items'] : array() as $short_link_entry) {

                $short_link_row = pg_short_link_by_id($user, isset($short_link_entry['id']) ? $short_link_entry['id'] : 0);

                if (!$short_link_row) {
                    $short_link_failed[] = lang('Access denied');
                    continue;
                }

                // Before the upgrade there is nowhere to put it, so it goes
                // the way it always went.
                if (pg_short_link_recycle_ready() == false) {

                    db("DELETE FROM short_links WHERE id = '" . e($short_link_row['id']) . "'");

                    $short_link_deleted++;

                    log_activity(lang(array('string' => 'short link ({var:1}) was deleted', 'vars' => $short_link_row['name'])), $_SESSION['sessionusername']);

                    continue;
                }

                db("UPDATE short_links SET recycled = '1' WHERE id = '" . e($short_link_row['id']) . "'");

                // Replaced rather than added to, so a link that reaches here
                // twice does not leave two restore records behind.
                db("DELETE FROM recycle_bin WHERE (item_type = 'short_link') AND (item_id = '" . e($short_link_row['id']) . "')");

                db(
                    "INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
                    VALUES ('short_link', '" . e($short_link_row['id']) . "', '0', UNIX_TIMESTAMP(), '" . e($user['id']) . "')");

                $short_link_deleted++;

                log_activity(lang(array('string' => 'The short link, {var:1}, was moved to the Recycle Bin.', 'vars' => h($short_link_row['name']))), $_SESSION['sessionusername']);
            }

            $short_link_binned = pg_short_link_recycle_ready();

            respond(array(
                'status' => ($short_link_deleted > 0) ? 'success' : 'error',
                'request' => $type,
                'deleted' => $short_link_deleted,
                'errors' => $short_link_failed,
                'message' => ($short_link_deleted > 0)
                    ? ($short_link_binned
                        ? lang(array('string' => '{var:1} item(s) were moved to the Recycle Bin', 'vars' => $short_link_deleted))
                        : lang(array('string' => '{var:1} item(s) were permanently deleted', 'vars' => $short_link_deleted)))
                    : (count($short_link_failed) > 0 ? $short_link_failed[0] : lang('Invalid request.'))));

            break;

        // ── Backups: a read-only browser over data/backups ──────────────
        //
        // Not part of the site's folder tree: these are files on disk with no
        // database records, outside the web root, and nothing here writes to
        // them. Manager area, the same gate backups.php uses.
        case 'explorer_backups_list':

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

            $crumbs = array();
            $walk = '';

            if ($relative != '') {
                foreach (explode('/', $relative) as $segment) {
                    $walk = ($walk == '') ? $segment : ($walk . '/' . $segment);
                    $crumbs[] = array('name' => $segment, 'path' => $walk);
                }
            }

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
            break;

        // ── Change permissions on a backup entry ────────────────────────
        //
        // Backups are written by whichever process happened to make them -
        // the web server on one host, a cron user on another - and an
        // operator who cannot read or replace yesterday's backup has a
        // broken backup. chmod is the fix, and it is only reachable here,
        // for paths inside data/backups, by a manager.
        case 'explorer_backup_chmod':

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
            break;

        // ── Extract an archive inside the backup directory ──────────────
        //
        // Faithful, unlike the extraction into the web root: a backup that
        // arrives as a zip has to come out whole or it cannot restore
        // anything. Safe because of where it lands - data/backups sits
        // outside the web root behind its own .htaccess, so nothing written
        // here is reachable by a visitor, let alone executable. The rule
        // that does apply is structural: every entry must resolve inside the
        // folder being extracted into.
        case 'explorer_backup_extract':

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
            @ini_set('memory_limit', '-1');

            $zip = new ZipArchive();

            if ($zip->open($path) !== true) {
                respond(array('status' => 'error', 'message' => lang('The zip archive could not be opened.')));
            }

            // Its own folder, named after the archive, so extracting twice
            // does not merge two backups into one heap.
            $base = pathinfo($path, PATHINFO_FILENAME);
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
                $segments = array();
                $escapes = false;

                foreach (explode('/', $entry) as $segment) {

                    if (($segment == '') || ($segment == '.')) {
                        continue;
                    }

                    // A "../" entry is the classic zip-slip; the whole entry
                    // is dropped rather than repaired, so nothing lands in a
                    // place the archive was not entitled to name.
                    if ($segment == '..') {
                        $escapes = true;
                        break;
                    }

                    $segments[] = $segment;
                }

                if ($escapes || (count($segments) == 0)) {
                    $skipped++;
                    continue;
                }

                $is_directory = (substr($entry, -1) == '/');
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

                $contents = $zip->getFromIndex($index);

                if (($contents === false) || (@file_put_contents($target, $contents) === false)) {
                    $skipped++;
                    continue;
                }

                $extracted++;
            }

            $zip->close();

            if ($extracted == 0) {
                @rmdir($destination_real);
            }

            log_activity(lang(array('string' => '{var:1} files were extracted', 'vars' => $extracted)), $_SESSION['sessionusername']);

            respond(array(
                'status' => ($extracted > 0) ? 'success' : 'error',
                'request' => $type,
                'extracted' => $extracted,
                'skipped' => $skipped,
                'name' => basename($destination_real),
                'message' => ($extracted > 0)
                    ? lang(array('string' => '{var:1} files were extracted', 'vars' => $extracted))
                    : lang('The zip archive could not be opened.')));
            break;

        // ── Upload into the backup directory ────────────────────────────
        //
        // Dropped from the computer, one file per request, each carrying the
        // path it had inside the dropped folder so the shape survives the
        // trip. The only rule enforced here is the structural one - no
        // segment may climb out of the backup directory - because a backup
        // has to keep whatever it holds, .php layouts included.
        case 'explorer_backup_upload':

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
            if (pg_explorer_backup_upload_blocked($file_name)) {
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
            break;

        // ── Rename, copy and delete inside the backup directory ─────────
        //
        // Real files on disk with no database record, so these three are
        // filesystem operations rather than table writes. Every path goes
        // through pg_explorer_backup_path(), which is what keeps them inside
        // data/backups, and every new name goes through the same
        // unsafe-name check an extraction uses - a backup directory is not a
        // place to park a .php file either.
        case 'explorer_backup_rename':
        case 'explorer_backup_copy':
        case 'explorer_backup_delete':

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
                $name = basename(str_replace('\\', '/', $name));

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
            break;

        // ── Compress a backup folder, ready to download ─────────────────
        case 'explorer_backup_zip':

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
                'message' => lang(array('string' => '{var:1} files were compressed', 'vars' => $added))));
            break;

        // ── Compress a selection into a zip file in this folder ─────────
        case 'explorer_zip_create':

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
            $suggested = trim(isset($request['name']) ? (string) $request['name'] : '');

            if ($suggested == '') {
                $suggested = lang('archive');
            }

            if (mb_strtolower(pathinfo($suggested, PATHINFO_EXTENSION)) != 'zip') {
                $suggested .= '.zip';
            }

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
            break;

        // ── Extract a zip file into this folder ─────────────────────────
        case 'explorer_zip_extract':

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
            @ini_set('memory_limit', '-1');

            $zip = new ZipArchive();

            if ($zip->open($archive_path) !== true) {
                respond(array('status' => 'error', 'message' => lang('The zip archive could not be opened.')));
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
                $entry_name = basename(str_replace('\\', '/', $entry));

                if (($entry_name == '') || (substr($entry, -1) == '/')) {
                    continue;
                }

                if (pg_explorer_unsafe_archive_name($entry_name)) {
                    $blocked[] = $entry_name;
                    continue;
                }

                $contents = $zip->getFromIndex($index);

                if ($contents === false) {
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

                if (@file_put_contents($new_path, $contents) === false) {
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
            break;

        // ── Shared: folders that were opened to specific users ──────────
        //
        // A virtual listing, not a folder: the manager's answer to "who can
        // reach what". Only closed folders can be shared in any meaningful
        // sense, so the list is membership and private folders that carry at
        // least one rights row, each with the people it was opened to.
        //
        // Manager area, like every other per-user rights screen: a basic
        // user is the subject of these rows, not their audience.
        case 'explorer_shared_list':

            if ($user['role'] > 2) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            $map = pg_explorer_folder_map();
            $bin_id = pg_recycle_folder_id(false);

            $rows = db_items(
                "SELECT
                    aclfolder.aclfolder_folder AS folder_id,
                    aclfolder.aclfolder_user AS user_id,
                    aclfolder.aclfolder_rights AS rights,
                    aclfolder.expiration_date AS expiration_date,
                    user.user_username AS username,
                    user.user_contact AS contact_id,
                    contacts.salutation AS contact_salutation,
                    contacts.first_name AS contact_first_name,
                    contacts.last_name AS contact_last_name,
                    contacts.nickname AS contact_nickname,
                    contacts.suffix AS contact_suffix
                FROM aclfolder
                LEFT JOIN user ON aclfolder.aclfolder_user = user.user_id
                LEFT JOIN contacts ON user.user_contact = contacts.id
                WHERE
                    (aclfolder.aclfolder_rights > '0')
                    AND (user.user_role = '3')
                ORDER BY user.user_username");

            $groups = array();
            $today = date('Y-m-d');

            if ($rows) {
                foreach ($rows as $row) {

                    $row_folder_id = (int) $row['folder_id'];

                    if (isset($map[$row_folder_id]) == false) {
                        continue;
                    }

                    // A rights row whose user is gone is dead data: it opens
                    // the folder to nobody and would list a nameless person.
                    if ((string) $row['username'] == '') {
                        continue;
                    }

                    // Binned folders keep their rights rows so a restore puts
                    // them back intact; they are not shared with anybody
                    // while they sit in the bin.
                    if (($bin_id > 0) && pg_explorer_is_self_or_descendant($bin_id, $row_folder_id)) {
                        continue;
                    }

                    if (pg_explorer_folder_visible($row_folder_id, $folders_that_user_has_access_to) == false) {
                        continue;
                    }

                    // Only closed folders: rights over a public folder grant
                    // nothing that everybody does not already have.
                    $access_control_type = get_access_control_type($row_folder_id);

                    if (in_array($access_control_type, array('membership', 'private'), true) == false) {
                        continue;
                    }

                    if (isset($groups[$row_folder_id]) == false) {

                        $crumbs = array();

                        foreach (pg_explorer_breadcrumb($row_folder_id, $folders_that_user_has_access_to) as $crumb) {
                            $crumbs[] = $crumb['name'];
                        }

                        $groups[$row_folder_id] = array(
                            'id' => $row_folder_id,
                            'name' => $map[$row_folder_id]['folder_name'],
                            'path' => implode(' / ', $crumbs),
                            'archived' => ($map[$row_folder_id]['folder_archived'] == '1'),
                            'access_control_type' => $access_control_type,
                            'access_icon' => pg_explorer_access_icon($access_control_type),
                            'can_edit' => check_edit_access($row_folder_id),
                            'grants' => array());
                    }

                    // The expiration date only exists for view rights; edit
                    // rights do not expire, and an empty date means no end.
                    $expiration_date = (string) $row['expiration_date'];

                    if (($expiration_date == '0000-00-00') || ($expiration_date == '')) {
                        $expiration_date = '';
                    }

                    $groups[$row_folder_id]['grants'][] = array(
                        'user_id' => (int) $row['user_id'],
                        'username' => (string) $row['username'],
                        'contact_id' => (int) $row['contact_id'],
                        'contact_name' => pg_explorer_contact_name($row),
                        'rights' => (int) $row['rights'],
                        'expiration_date' => $expiration_date,
                        'expired' => (($expiration_date != '') && ($expiration_date < $today)));
                }
            }

            // Path order, so a subtree reads together.
            $ordered = array_values($groups);

            usort($ordered, 'pg_explorer_compare_shared_groups');

            respond(array(
                'status' => 'success',
                'request' => $type,
                'groups' => $ordered));
            break;

        // ── Bulk page edit: the choices the panel can offer ─────────────
        case 'explorer_bulk_page_options':

            $styles = null;

            if ($user['role'] < 3) {
                $styles = array(
                    'desktop' => select_style(''),
                    'mobile' => get_mobile_style_options(''));
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'noindex_ready' => pg_page_noindex_ready(),
                'styles' => $styles));
            break;

        // ── Bulk page edit: apply ───────────────────────────────────────
        //
        // Every field is optional; only what the panel actually set gets
        // written. Style fields follow the edit screens' role rule, the
        // noindex/nofollow pair follows its schema probe, and each page is
        // let through only with edit access to its folder. Whatever changed
        // marks the SEO analysis stale, the same write the edit screens do.
        case 'explorer_pages_bulk_edit':

            $items = isset($request['items']) ? $request['items'] : array();
            $set = (isset($request['set']) && is_array($request['set'])) ? $request['set'] : array();

            if ((is_array($items) == false) || (count($items) == 0) || (count($set) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $assignments = array();

            foreach (array('sitemap' => 'sitemap', 'search' => 'page_search', 'comments' => 'comments') as $field => $column) {
                if (isset($set[$field]) && in_array((string) $set[$field], array('0', '1'), true)) {
                    $assignments[] = $column . " = '" . e($set[$field]) . "'";
                }
            }

            if (pg_page_noindex_ready()) {
                foreach (array('noindex', 'nofollow') as $field) {
                    if (isset($set[$field]) && in_array((string) $set[$field], array('0', '1'), true)) {
                        $assignments[] = $field . " = '" . e($set[$field]) . "'";
                    }
                }
            }

            // The two style columns are held apart from the rest, because they
            // are not written to every page in the selection -- see below.
            $style_assignments = array();

            if ($user['role'] < 3) {
                if (isset($set['style']) && ($set['style'] !== '')) {
                    $style_assignments[] = "page_style = '" . e((int) $set['style']) . "'";
                }

                if (isset($set['mobile_style_id']) && ($set['mobile_style_id'] !== '')) {
                    $style_assignments[] = "mobile_style_id = '" . e((int) $set['mobile_style_id']) . "'";
                }
            }

            if ((count($assignments) == 0) && (count($style_assignments) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $allowed = array();
            $errors = array();

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($item_id <= 0) {
                    continue;
                }

                $page = db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . e($item_id) . "'");

                if (!$page) {
                    continue;
                }

                if (check_edit_access($page['page_folder']) == false) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $page['page_name']));
                    continue;
                }

                $allowed[] = (int) $page['page_id'];
            }

            // A page the visual editor opens keeps its style out of this.
            //
            // Since the multi-page design swap, the style is not a skin that
            // can be exchanged underneath such a page: the page carries its own
            // layout tree and the style it points at owns the assets that tree
            // was built against. Pointing a batch of them at another style
            // leaves each one rendering a tree whose blocks, fonts and custom
            // CSS live somewhere else -- which is not "restyled", it is broken,
            // and nothing in a bulk panel can put it back.
            //
            // Two shapes count as such a page: one that carries a tree of its
            // own, and one whose style was made by the editor. The rest -- the
            // classic pages the style picker was written for -- are updated as
            // before.
            $style_targets = $allowed;
            $style_skipped = 0;

            if ((count($style_assignments) > 0) && (count($allowed) > 0)) {

                $own_tree = pg_multi_page_design_ready()
                    ? " OR ((page.page_tree_json IS NOT NULL) AND (page.page_tree_json <> ''))"
                    : '';

                $visual_ids = (array) db_values(
                    "SELECT page.page_id
                    FROM page
                    LEFT JOIN style ON page.page_style = style.style_id
                    WHERE (page.page_id IN (" . implode(',', $allowed) . "))
                        AND ((style.style_layout = 'visual_designer')" . $own_tree . ")");

                $visual_ids = array_map('intval', $visual_ids);
                $style_targets = array_values(array_diff($allowed, $visual_ids));
                $style_skipped = count($allowed) - count($style_targets);
            }

            if ((count($assignments) > 0) && (count($allowed) > 0)) {
                db(
                    "UPDATE page
                    SET
                        " . implode(",\n                        ", $assignments) . ",
                        seo_analysis_current = '0',
                        page_timestamp = UNIX_TIMESTAMP(),
                        page_user = '" . USER_ID . "'
                    WHERE page_id IN (" . implode(',', $allowed) . ")");
            }

            if ((count($style_assignments) > 0) && (count($style_targets) > 0)) {
                db(
                    "UPDATE page
                    SET
                        " . implode(",\n                        ", $style_assignments) . ",
                        seo_analysis_current = '0',
                        page_timestamp = UNIX_TIMESTAMP(),
                        page_user = '" . USER_ID . "'
                    WHERE page_id IN (" . implode(',', $style_targets) . ")");
            }

            if (count($allowed) > 0) {
                log_activity(lang(array('string' => '{var:1} pages were updated', 'vars' => count($allowed))), $_SESSION['sessionusername']);
            }

            $bulk_pages_message = lang(array('string' => '{var:1} pages were updated', 'vars' => count($allowed)));

            // The skip note below is appended as its own sentence, so the
            // count has to end like one.
            if (mb_substr($bulk_pages_message, -1) !== '.') {
                $bulk_pages_message .= '.';
            }

            // Said rather than left to be noticed: an operator who picked a
            // style and saw nothing change on half the list would go looking
            // for a bug.
            if ($style_skipped > 0) {
                $bulk_pages_message .= ' ' . lang(array(
                    'string' => 'The style was left alone on {var:1} of them, because a page the visual editor opens carries its own layout.',
                    'vars' => $style_skipped));
            }

            respond(array(
                'status' => (count($errors) == 0) ? (($style_skipped > 0) ? 'partial' : 'success') : ((count($allowed) > 0) ? 'partial' : 'error'),
                'request' => $type,
                'updated' => count($allowed),
                'style_skipped' => $style_skipped,
                'errors' => $errors,
                'message' => (count($errors) > 0) ? implode(' ', $errors) : $bulk_pages_message));
            break;

        // ── Bulk file edit: the choices the panel can offer ─────────────
        //
        // The folder list is not here: explorer_folder_options already builds
        // it, filtered to what this operator may write to, and the upload
        // window and the file window both read it. One list, three panels.
        case 'explorer_bulk_file_options':

            $bulk_image_settings = pg_image_settings();

            respond(array(
                'status' => 'success',
                'request' => $type,
                // The design flag is a designer's decision, the rule
                // edit_files.php applies and the one the right-click menu
                // already follows.
                'is_designer' => ($user['role'] <= 1),
                'resize_trigger' => (int) $bulk_image_settings['file_resize_trigger'],
                'resize_target' => (int) $bulk_image_settings['file_max_dimension']));
            break;

        // ── Bulk file edit: apply ───────────────────────────────────────
        //
        // What edit_files.php did to a checked list, on the selection: move to
        // a folder, set or clear the design flag, and write a description.
        // Every field is optional and untouched fields are not written.
        //
        // Optimizing is deliberately not here. The screen already has it --
        // one endpoint, the progress bar and the per-file report the operator
        // knows -- so the panel runs that afterwards rather than growing a
        // second copy of it on this side.
        case 'explorer_files_bulk_edit':

            $items = isset($request['items']) ? $request['items'] : array();
            $set = (isset($request['set']) && is_array($request['set'])) ? $request['set'] : array();

            if ((is_array($items) == false) || (count($items) == 0) || (count($set) == 0)) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $assignments = array();
            $target_folder_id = 0;

            if (isset($set['folder_id']) && ((string) $set['folder_id'] !== '')) {

                $target_folder_id = (int) $set['folder_id'];

                // Checked once for the whole run rather than per file: the
                // destination is the same for all of them, and a refusal is
                // about the folder, not about any one file.
                if (($target_folder_id <= 0) || (check_edit_access($target_folder_id) == false) || pg_recycle_is_inside($target_folder_id)) {
                    respond(array('status' => 'error', 'message' => lang('You do not have access to move files to the folder that you selected')));
                }

                $assignments[] = "folder = '" . e($target_folder_id) . "'";
            }

            // Only a designer may change it, and the switch is not drawn for
            // anyone else -- so for anyone else it is simply not read.
            if (($user['role'] <= 1) && isset($set['design']) && in_array((string) $set['design'], array('0', '1'), true)) {
                $assignments[] = "design = '" . e($set['design']) . "'";
            }

            // Three states, not two: leave it alone, write this text, or empty
            // it. "Replace with nothing" and "do not touch" are different
            // instructions and a blank box cannot tell them apart on its own.
            if (isset($set['description_mode'])) {

                if ((string) $set['description_mode'] === 'clear') {
                    $assignments[] = "description = ''";

                } elseif ((string) $set['description_mode'] === 'set') {
                    $assignments[] = "description = '" . e(isset($set['description']) ? (string) $set['description'] : '') . "'";
                }
            }

            if (count($assignments) == 0) {
                respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $allowed = array();
            $errors = array();

            foreach ($items as $item) {

                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if ($item_id <= 0) {
                    continue;
                }

                $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($item_id) . "'");

                if (!$file) {
                    continue;
                }

                // The two rules every file action applies: edit rights on the
                // folder it sits in, and designer rank for a design file.
                if (
                    (check_edit_access($file['folder']) == false)
                    || (($file['design'] == 1) && ($user['role'] > 1))
                ) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                    continue;
                }

                $allowed[] = (int) $file['id'];
            }

            if (count($allowed) > 0) {
                db(
                    "UPDATE files
                    SET
                        " . implode(",\n                        ", $assignments) . ",
                        timestamp = UNIX_TIMESTAMP(),
                        user = '" . e($user['id']) . "'
                    WHERE id IN (" . implode(',', $allowed) . ")");

                log_activity(lang(array('string' => '{var:1} files were updated', 'vars' => count($allowed))), $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((count($allowed) > 0) ? 'partial' : 'error'),
                'request' => $type,
                'updated' => count($allowed),
                'errors' => $errors,
                'message' => (count($errors) > 0)
                    ? implode(' ', $errors)
                    : lang(array('string' => '{var:1} files were updated', 'vars' => count($allowed)))));
            break;

        // ── Folder access panel: read ───────────────────────────────────
        case 'explorer_folder_access_get':

            $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

            $folder = db_item("SELECT folder_id, folder_name, folder_parent, folder_access_control_type FROM folder WHERE folder_id = '" . e($folder_id) . "'");

            if (!$folder) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
            }

            if (check_edit_access($folder_id) == false) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            // Where the effective type comes from when this folder inherits.
            $map = pg_explorer_folder_map();
            $inherited_from = '';
            $current = (int) $folder['folder_parent'];
            $guard = array();

            if ((string) $folder['folder_access_control_type'] == '') {
                while (($current > 0) && isset($map[$current]) && (isset($guard[$current]) == false)) {
                    $guard[$current] = true;

                    if ((string) $map[$current]['folder_access_control_type'] != '') {
                        $inherited_from = $map[$current]['folder_name'];
                        break;
                    }

                    $current = (int) $map[$current]['folder_parent'];
                }
            }

            $response = array(
                'status' => 'success',
                'request' => $type,
                'folder' => array(
                    'id' => (int) $folder['folder_id'],
                    'name' => $folder['folder_name'],
                    'own_access_control_type' => (string) $folder['folder_access_control_type'],
                    'access_control_type' => get_access_control_type($folder_id),
                    'inherited_from' => $inherited_from),
                'can_manage_users' => ($user['role'] <= 2),
                'users' => array());

            // Per-user rights are a manager concern, the same area rule the
            // user edit screen enforces.
            if ($user['role'] <= 2) {

                // Ancestor chain for inherited rights.
                $chain = array();
                $current = (int) $folder['folder_parent'];
                $guard = array();

                while (($current > 0) && isset($map[$current]) && (isset($guard[$current]) == false)) {
                    $guard[$current] = true;
                    $chain[] = $current;
                    $current = (int) $map[$current]['folder_parent'];
                }

                $direct_rights = array();
                $inherited_rights = array();

                $rows = db_items(
                    "SELECT aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date
                    FROM aclfolder
                    WHERE aclfolder_folder IN ('" . e($folder_id) . "'" . ((count($chain) > 0) ? ", " . implode(',', array_map('intval', $chain)) : "") . ")
                        AND aclfolder_rights > 0");

                if ($rows) {
                    foreach ($rows as $row) {
                        $row_user_id = (int) $row['aclfolder_user'];

                        if ((int) $row['aclfolder_folder'] == $folder_id) {
                            $direct_rights[$row_user_id] = array(
                                'rights' => (int) $row['aclfolder_rights'],
                                'expiration_date' => (string) $row['expiration_date']);
                        } else {
                            $existing = isset($inherited_rights[$row_user_id]) ? $inherited_rights[$row_user_id] : 0;
                            $inherited_rights[$row_user_id] = max($existing, (int) $row['aclfolder_rights']);
                        }
                    }
                }

                $users = db_items(
                    "SELECT user_id, user_username
                    FROM user
                    WHERE user_role = '3'
                    ORDER BY user_username");

                if ($users) {
                    foreach ($users as $row) {
                        $row_user_id = (int) $row['user_id'];

                        $response['users'][] = array(
                            'id' => $row_user_id,
                            'username' => $row['user_username'],
                            'rights' => isset($direct_rights[$row_user_id]) ? $direct_rights[$row_user_id]['rights'] : 0,
                            'expiration_date' => isset($direct_rights[$row_user_id]) ? $direct_rights[$row_user_id]['expiration_date'] : '',
                            'inherited_rights' => isset($inherited_rights[$row_user_id]) ? $inherited_rights[$row_user_id] : 0);
                    }
                }
            }

            respond($response);
            break;

        // ── Folder access panel: write ──────────────────────────────────
        case 'explorer_folder_access_set':

            $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

            $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($folder_id) . "'");

            if (!$folder) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
            }

            if (check_edit_access($folder_id) == false) {
                log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            // The access control type: '' means inherit from the parent.
            if (isset($request['access_control_type'])) {

                $access_control_type = $request['access_control_type'];

                if (in_array($access_control_type, array('', 'public', 'guest', 'registration', 'membership', 'private'), true) == false) {
                    respond(array('status' => 'error', 'message' => lang('Invalid request.')));
                }

                db(
                    "UPDATE folder SET
                        folder_access_control_type = '" . e($access_control_type) . "',
                        folder_timestamp = UNIX_TIMESTAMP(),
                        folder_user = '" . e($user['id']) . "'
                    WHERE folder_id = '" . e($folder_id) . "'");

                log_activity(lang(array('string' => 'folder ({var:1}) was modified', 'vars' => array($folder['folder_name']))), $_SESSION['sessionusername']);
            }

            // Per-user rights: managers and above only, and only for basic
            // users — the same boundary the user edit screen draws.
            if (isset($request['user_rights']) && is_array($request['user_rights'])) {

                if ($user['role'] > 2) {
                    respond(array('status' => 'error', 'message' => lang('Access denied')));
                }

                foreach ($request['user_rights'] as $entry) {

                    $target_user_id = (int) (isset($entry['user_id']) ? $entry['user_id'] : 0);
                    $rights = (int) (isset($entry['rights']) ? $entry['rights'] : 0);
                    $expiration_date = trim(isset($entry['expiration_date']) ? $entry['expiration_date'] : '');

                    if (($target_user_id <= 0) || (in_array($rights, array(0, 1, 2), true) == false)) {
                        continue;
                    }

                    // Only basic users are governed by folder rights.
                    $target_role = db_value("SELECT user_role FROM user WHERE user_id = '" . e($target_user_id) . "'");

                    if ($target_role === false || (int) $target_role != 3) {
                        continue;
                    }

                    // An expiration date only applies to view access, the
                    // same way the user edit screen stores it.
                    $sql_expiration_date = '';

                    if (($rights == 1) && ($expiration_date != '')) {
                        $expiration_date_parts = explode('-', $expiration_date);

                        if (
                            (count($expiration_date_parts) == 3)
                            && is_numeric($expiration_date_parts[0])
                            && is_numeric($expiration_date_parts[1])
                            && is_numeric($expiration_date_parts[2])
                            && checkdate((int) $expiration_date_parts[1], (int) $expiration_date_parts[2], (int) $expiration_date_parts[0])
                        ) {
                            $sql_expiration_date = $expiration_date;
                        }
                    }

                    $existing = db_value(
                        "SELECT COUNT(*)
                        FROM aclfolder
                        WHERE (aclfolder_user = '" . e($target_user_id) . "') AND (aclfolder_folder = '" . e($folder_id) . "')");

                    if ($existing > 0) {
                        db(
                            "UPDATE aclfolder SET
                                aclfolder_rights = '" . e($rights) . "',
                                expiration_date = '" . e($sql_expiration_date) . "'
                            WHERE (aclfolder_user = '" . e($target_user_id) . "') AND (aclfolder_folder = '" . e($folder_id) . "')");
                    } else {
                        db(
                            "INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date)
                            VALUES ('" . e($target_user_id) . "', '" . e($folder_id) . "', '" . e($rights) . "', '" . e($sql_expiration_date) . "')");
                    }
                }

                log_activity(lang(array('string' => 'folder access rights were updated for folder ({var:1})', 'vars' => array($folder['folder_name']))), $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'message' => lang('The folder was edited successfully.')));
            break;

        // ── Recursive delete pre-flight ─────────────────────────────────
        // Reports what deleting a folder together with its contents would
        // remove, and what blocks it. The deletions themselves run through
        // the proven handlers (edit_pages.php for pages, this API for files,
        // edit_folder.php per emptied folder) so every safety check — the
        // submitted-forms rule above all — stays where it has always lived.
        case 'explorer_delete_check':

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
            break;

        // ── Move items into the recycle bin ─────────────────────────────
        case 'explorer_recycle_delete':

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
                }
            }

            if ($binned > 0) {
                log_activity(lang(array('string' => '{var:1} item(s) were moved to the recycle bin', 'vars' => $binned)), $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((($binned > 0)) ? 'partial' : 'error'),
                'request' => $type,
                'binned' => $binned,
                'errors' => $errors,
                'message' => (count($errors) > 0)
                    ? implode(' ', $errors)
                    : lang(array('string' => '{var:1} item(s) were moved to the recycle bin', 'vars' => $binned))));
            break;

        // ── Restore items from the recycle bin ──────────────────────────
        case 'explorer_recycle_restore':

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

            foreach ($items as $item) {

                $item_kind = isset($item['kind']) ? $item['kind'] : '';
                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if (($item_id <= 0) || (in_array($item_kind, pg_recycle_item_types(), true) == false)) {
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
                $restored++;
            }

            if ($restored > 0) {
                log_activity(lang(array('string' => '{var:1} item(s) were restored from the recycle bin', 'vars' => $restored)), $_SESSION['sessionusername']);
            }

            respond(array(
                'status' => (count($errors) == 0) ? 'success' : ((($restored > 0)) ? 'partial' : 'error'),
                'request' => $type,
                'restored' => $restored,
                'errors' => $errors,
                'message' => (count($errors) > 0)
                    ? implode(' ', $errors)
                    : lang(array('string' => '{var:1} item(s) were restored from the recycle bin', 'vars' => $restored))));
            break;

        // ── Permanent delete (bin entries, empty-the-bin, or the fallback
        //    when the bin schema is not installed yet) ────────────────────
        case 'explorer_hard_delete':

            $items = isset($request['items']) ? $request['items'] : array();

            // all:true empties the recycle bin.
            if (isset($request['all']) && ($request['all'] == true) && pg_recycle_ready()) {
                $items = array();
                // Only what this bin holds: the store's rows live in the
                // same table and are emptied from its own screen.
                $rows = db_items("SELECT item_type, item_id FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql() . ")");

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
                'status' => (count($errors) == 0) ? 'success' : ((($deleted > 0)) ? 'partial' : 'error'),
                'request' => $type,
                'deleted' => $deleted,
                'errors' => array_values(array_unique($errors)),
                'message' => (count($errors) > 0)
                    ? implode(' ', array_unique($errors))
                    : lang(array('string' => '{var:1} item(s) were permanently deleted', 'vars' => $deleted))));
            break;
    }

    respond(array('status' => 'error', 'message' => lang('Invalid request.')));
}
