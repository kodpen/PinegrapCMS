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
            // copy applies, and the signing key is never copied; the rest of
            // the folder still comes across.
            if ((($file['design'] == 1) && ($user['role'] > 1)) || pg_file_withheld($file['name'])) {
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

            pg_audit_record('create', 'files', (int) mysqli_insert_id(db::$con));

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

// Archives: the backup browser, zip creation and zip extraction.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/archives.php');

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
        // Kept off the site in the private drafts folder (2026.4.7).
        'draft' => function_exists('pg_page_is_draft') && pg_page_is_draft($page['page_folder']),
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

// The language the file window's code editor (PgCodeEditor) opens a file
// in, by extension: one of the four grammars the facade carries, or 'text'
// for Markdown, YAML and the rest, which are shown with the HTML colours
// and no lint rather than named after a grammar that is not there.
function pg_explorer_editor_language($extension)
{
    switch ($extension) {
        case 'css':
        case 'scss':
        case 'less':
            return 'css';
        case 'js':
            return 'javascript';
        case 'json':
        case 'webmanifest':
            return 'json';
        case 'svg':
        case 'xml':
        case 'html':
        case 'htm':
            return 'html';
    }

    return 'text';
}

// The largest file the text editor is handed. Beyond it the window shows the
// file's details and says why the text is not there: a log or a data dump the
// size of a video is not something to edit in a browser text box, and loading
// one into the code editor freezes the tab.
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

// The kind of a file as the screen works with it: its extension, lower-cased.
//
// files.type is four characters wide, so a longer extension is stored cut:
// a .webmanifest as "webm" (which the file window then plays as a video) and
// a .markdown as "mark" (which loses its icon and its editor). The name is
// stored whole and the rename action writes both, so the extension of the
// name is the one to go by; the column is only read for a name without one.
function pg_explorer_file_type($file)
{
    $extension = mb_strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    return ($extension !== '') ? $extension : mb_strtolower((string) ($file['type'] ?? ''));
}

function pg_explorer_file_payload($file, $user)
{
    $access_control_type = get_access_control_type($file['folder']);
    $map = pg_explorer_folder_map();
    $folder_id = (int) $file['folder'];
    $type = pg_explorer_file_type($file);
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

// Recycle bin: readiness, contents, retention and the permanent deletes.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');

// Recycle bin names: parking, the home flag and the daily purge.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_names.php');

// Pages and page styles deleted from the other screens.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_pages_styles.php');

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

// Short links: the name, bin, listing and request helpers.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/short_links.php');

// The short-link sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_short_links.php');

// The backup sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_backups.php');

// The zip sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_zip.php');

// The Office preview, rotate, optimize and WebP sub-actions of
// pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_files_media.php');

// The upload sub-action of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_upload.php');

// The "Where used" sub-action of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');

// The tree, new folder, new file, rename and move sub-actions of
// pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_items.php');

// The paste, delete and design flag sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_files.php');

// The upload folder choices and folder settings sub-actions of
// pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_folders.php');

// The file window sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_window.php');

// The "Shared" sub-action of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_shared.php');

// The bulk page and bulk file edit sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_bulk_edit.php');

// The folder access panel sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_folder_access.php');

// ── Catalog: product groups and products through the explorer ────────────

// Access, the group tree, statistics, breadcrumbs and the tile payloads.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/catalog.php');

// Where groups and products are shown, search pages and the group copy.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/catalog_pages.php');

// The catalog listing, tree, new group, rename, membership, pages, paste,
// recycle, restore, purge and publish sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog.php');

// The catalog quick edit and commerce access sub-actions of
// pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');

// The bulk product edit sub-actions of pg_explorer_handle(): the choices
// the panel can offer and the apply.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');

// ── ERP documents ───────────────────────────────────────────────────────
//
// The file-id sub-actions, the ERP module's seam and the virtual folder and
// file rows.
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/erp.php');

// The ERP documents sub-action of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_erp.php');

// The recursive delete pre-flight and the recycle bin's delete, restore and
// permanent delete sub-actions of pg_explorer_handle().
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_recycle.php');

function pg_explorer_handle($request, $user, $folders_that_user_has_access_to)
{
    $type = isset($request['type']) ? $request['type'] : '';

    // Every short link action, the listing included, is a right of roles
    // 0-2: short links sit outside the folder-based rights model, so a user
    // (role 3) is refused here rather than filtered further down.
    if ((strpos($type, 'explorer_short_link') === 0) && ($user['role'] == 3)) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    // A document the ERP module keeps is a `files` row like any other, and it
    // is read-only here: an issued invoice keeps its PDF, its name and its
    // place. The ERP folders never offer these actions on one; this is the
    // answer to a request that names one by id anyway.
    if (in_array($type, pg_explorer_file_id_actions(), true) && pg_files_include_erp_document(pg_explorer_request_file_ids($type, $request))) {
        respond(array('status' => 'error', 'request' => $type, 'message' => lang('ERP documents are read-only in the file manager.')));
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
                $recycle['count'] = (int) db_value("SELECT COUNT(*) FROM recycle_bin WHERE item_type IN (" . pg_recycle_item_types_sql($user) . ")");
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
            break;

        // ── Catalog listing: one group's subgroups and its own products ──
        case 'explorer_catalog_list':
            pg_explorer_handle_catalog_list($type, $request, $user);
            break;

        // ── Catalog tree (lazy children, same contract as explorer_tree) ──
        case 'explorer_catalog_tree':
            pg_explorer_handle_catalog_tree($type, $request, $user);
            break;

        // ── Create a product group where you are standing ────────────────
        case 'explorer_catalog_create_group':
            pg_explorer_handle_catalog_create_group($type, $request, $user);
            break;

        // ── Rename a group or a product ──────────────────────────────────
        case 'explorer_catalog_rename':
            pg_explorer_handle_catalog_rename($type, $request, $user);
            break;

        // ── Cut / copy and paste ─────────────────────────────────────────
        case 'explorer_catalog_paste':
            pg_explorer_handle_catalog_paste($type, $request, $user);
            break;
        // ── Move a group or a product to the recycle bin ─────────────────
        case 'explorer_catalog_recycle':
            pg_explorer_handle_catalog_recycle($type, $request, $user);
            break;

        // ── Put one back ────────────────────────────────────────────────
        case 'explorer_catalog_restore':
            pg_explorer_handle_catalog_restore($type, $request, $user);
            break;

        // ── Empty the catalog bin, or part of it ─────────────────────────
        case 'explorer_catalog_purge':
            pg_explorer_handle_catalog_purge($type, $request, $user);
            break;

        // ── Publish or unpublish groups and products ─────────────────────
        case 'explorer_catalog_enable':
            pg_explorer_handle_catalog_enable($type, $request, $user);
            break;

        // ── Bulk product edit: the choices the panel can offer ──────────
        case 'explorer_bulk_product_options':
            pg_explorer_handle_bulk_product_options($type, $request, $user);
            break;

        // ── Bulk product edit: apply ────────────────────────────────────
        case 'explorer_products_bulk_edit':
            pg_explorer_handle_products_bulk_edit($type, $request, $user);
            break;

        // ── A Word or Excel file, read for a preview ────────────────────
        case 'explorer_office_preview':
            pg_explorer_handle_office_preview($type, $request, $user);
            break;

        // ── Where a file is used ────────────────────────────────────────
        case 'explorer_file_usage':
            pg_explorer_handle_file_usage($type, $request, $user, $folders_that_user_has_access_to);
            break;

        // ── One field of one product ────────────────────────────────────
        case 'explorer_catalog_quick_edit':
            pg_explorer_handle_catalog_quick_edit($type, $request, $user);
            break;

        // ── Commerce access: read ───────────────────────────────────────
        case 'explorer_catalog_access_get':
            pg_explorer_handle_catalog_access_get($type, $request, $user);
            break;

        // ── Commerce access: write ──────────────────────────────────────
        case 'explorer_catalog_access_set':
            pg_explorer_handle_catalog_access_set($type, $request, $user);
            break;

        // ── Take a product out of one group ──────────────────────────────
        case 'explorer_catalog_membership_remove':
            pg_explorer_handle_catalog_membership_remove($type, $request, $user);
            break;

        // ── Where this group or product is visible on the site ───────────
        case 'explorer_catalog_pages':
            pg_explorer_handle_catalog_pages($type, $request, $user);
            break;

        // ── Tree sidebar (lazy children) ────────────────────────────────
        case 'explorer_tree':
            pg_explorer_handle_tree($type, $request, $user, $folders_that_user_has_access_to);
            break;

        // ── Create folder ───────────────────────────────────────────────
        case 'explorer_create_folder':
            pg_explorer_handle_create_folder($type, $request, $user);
            break;

        // ── Create an empty file (named in place, edited later) ─────────
        case 'explorer_create_file':
            pg_explorer_handle_create_file($type, $request, $user);
            break;

        // ── Rename ──────────────────────────────────────────────────────
        case 'explorer_rename':
            pg_explorer_handle_rename($type, $request, $user);
            break;

        // ── Move (drag & drop, cut + paste) ─────────────────────────────
        case 'explorer_move':
            pg_explorer_handle_move($type, $request, $user);
            break;

        // ── Paste (copy of files and pages) ─────────────────────────────
        case 'explorer_paste':
            pg_explorer_handle_paste($type, $request, $user);
            break;

        // ── Delete files (bulk) ─────────────────────────────────────────
        case 'explorer_delete_files':
            pg_explorer_handle_delete_files($type, $request, $user);
            break;

        // ── The folders an upload may land in ────────────────────────────
        case 'explorer_folder_options':
            pg_explorer_handle_folder_options($type, $request, $user, $folders_that_user_has_access_to);
            break;

        // ── Upload (drag files from the computer into the folder) ───────
        case 'explorer_upload':
            pg_explorer_handle_upload($type, $request, $user);
            break;

        // ── Bulk optimize selected images ───────────────────────────────
        case 'explorer_optimize':
            pg_explorer_handle_optimize($type, $request, $user);
            break;

        // ── Convert selected images to WebP ─────────────────────────────
        case 'explorer_webp':
            pg_explorer_handle_webp($type, $request, $user);
            break;

        // ── Folder settings: order, archive, page styles ────────────────
        case 'explorer_folder_settings_get':
            pg_explorer_handle_folder_settings_get($type, $request, $user);
            break;

        case 'explorer_folder_settings_set':
            pg_explorer_handle_folder_settings_set($type, $request, $user);
            break;

        // ── Turn the design flag on or off for a set of files ───────────
        case 'explorer_files_design':
            pg_explorer_handle_files_design($type, $request, $user);
            break;

        // ── The file window ─────────────────────────────────────────────
        case 'explorer_file_get':
            pg_explorer_handle_file_get($type, $request, $user);
            break;

        case 'explorer_file_save':
            pg_explorer_handle_file_save($type, $request, $user);
            break;

        // ── Turn a picture a quarter to the right ────────────────────────
        case 'explorer_rotate':
            pg_explorer_handle_rotate($type, $request, $user);
            break;

        // ── ERP documents, read-only ─────────────────────────────────────
        case 'explorer_erp_tree':
        case 'explorer_erp_list':
            pg_explorer_handle_erp_tree_list($type, $request, $user);
            break;

        // ── Short links: the names that stand for something else ─────────
        case 'explorer_short_links_list':
            pg_explorer_handle_short_links_list($type, $request, $user);
            break;

        // ── What the wizard offers, built from the classic screen's lists ─
        case 'explorer_short_link_options':
            pg_explorer_handle_short_link_options($type, $request, $user);
            break;

        // ── Create ──────────────────────────────────────────────────────
        case 'explorer_short_link_create':
            pg_explorer_handle_short_link_create($type, $request, $user);
            break;

        // ── Rename ──────────────────────────────────────────────────────
        case 'explorer_short_link_rename':
            pg_explorer_handle_short_link_rename($type, $request, $user);
            break;

        // ── Change where an existing one points, and what it is called ───
        case 'explorer_short_link_update':
            pg_explorer_handle_short_link_update($type, $request, $user);
            break;

        // ── Duplicate, which is also what paste does here ────────────────
        case 'explorer_short_link_duplicate':
            pg_explorer_handle_short_link_duplicate($type, $request, $user);
            break;

        // ── Delete ──────────────────────────────────────────────────────
        case 'explorer_short_link_delete':
            pg_explorer_handle_short_link_delete($type, $request, $user);
            break;

        // ── Backups: a read-only browser over data/backups ──────────────
        case 'explorer_backups_list':
            pg_explorer_handle_backups_list($type, $request, $user);
            break;

        // ── Change permissions on a backup entry ────────────────────────
        case 'explorer_backup_chmod':
            pg_explorer_handle_backup_chmod($type, $request, $user);
            break;

        // ── Extract an archive inside the backup directory ──────────────
        case 'explorer_backup_extract':
            pg_explorer_handle_backup_extract($type, $request, $user);
            break;

        // ── Upload into the backup directory ────────────────────────────
        case 'explorer_backup_upload':
            pg_explorer_handle_backup_upload($type, $request, $user);
            break;

        // ── Rename, copy and delete inside the backup directory ─────────
        case 'explorer_backup_rename':
        case 'explorer_backup_copy':
        case 'explorer_backup_delete':
            pg_explorer_handle_backup_rename_copy_delete($type, $request, $user);
            break;

        // ── Compress a backup folder, ready to download ─────────────────
        case 'explorer_backup_zip':
            pg_explorer_handle_backup_zip($type, $request, $user);
            break;

        // ── Compress a selection into a zip file in this folder ─────────
        case 'explorer_zip_create':
            pg_explorer_handle_zip_create($type, $request, $user);
            break;

        // ── Extract a zip file into this folder ─────────────────────────
        case 'explorer_zip_extract':
            pg_explorer_handle_zip_extract($type, $request, $user);
            break;

        // ── Shared: folders that were opened to specific users ──────────
        case 'explorer_shared_list':
            pg_explorer_handle_shared_list($type, $request, $user, $folders_that_user_has_access_to);
            break;

        // ── Bulk page edit: the choices the panel can offer ─────────────
        case 'explorer_bulk_page_options':
            pg_explorer_handle_bulk_page_options($type, $request, $user);
            break;

        // ── Bulk page edit: apply ───────────────────────────────────────
        case 'explorer_pages_bulk_edit':
            pg_explorer_handle_pages_bulk_edit($type, $request, $user);
            break;

        // ── Bulk file edit: the choices the panel can offer ─────────────
        case 'explorer_bulk_file_options':
            pg_explorer_handle_bulk_file_options($type, $request, $user);
            break;

        // ── Bulk file edit: apply ───────────────────────────────────────
        case 'explorer_files_bulk_edit':
            pg_explorer_handle_files_bulk_edit($type, $request, $user);
            break;

        // ── Folder access panel: read ───────────────────────────────────
        case 'explorer_folder_access_get':
            pg_explorer_handle_folder_access_get($type, $request, $user);
            break;

        // ── Folder access panel: write ──────────────────────────────────
        case 'explorer_folder_access_set':
            pg_explorer_handle_folder_access_set($type, $request, $user);
            break;

        // ── Recursive delete pre-flight ─────────────────────────────────
        case 'explorer_delete_check':
            pg_explorer_handle_delete_check($type, $request, $user);
            break;

        // ── Move items into the recycle bin ─────────────────────────────
        case 'explorer_recycle_delete':
            pg_explorer_handle_recycle_delete($type, $request, $user);
            break;

        // ── Restore items from the recycle bin ──────────────────────────
        case 'explorer_recycle_restore':
            pg_explorer_handle_recycle_restore($type, $request, $user);
            break;

        // ── Permanent delete (bin entries, empty-the-bin, or the fallback
        //    when the bin schema is not installed yet) ────────────────────
        case 'explorer_hard_delete':
            pg_explorer_handle_hard_delete($type, $request, $user);
            break;
    }

    respond(array('status' => 'error', 'message' => lang('Invalid request.')));
}
