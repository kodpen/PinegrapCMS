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

            // The signing key is shown as a file with no preview: its
            // content never leaves the server (pg_file_withheld()).
            $payload['editable'] = in_array($extension, pg_explorer_editable_formats(), true);
            $payload['viewable'] = in_array($extension, pg_explorer_viewable_formats(), true)
                && (pg_file_withheld($file['name']) == false);
            $payload['editor_language'] = pg_explorer_editor_language($extension);
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

            // Under another name the signing key would be served.
            if (($name != $file['name']) && pg_file_withheld($file['name'])) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
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
            $size_sql = '';

            // Cell values changed in a workbook's preview, or paragraph texts
            // in a document's (includes/fn/office_edit.php). They go into the
            // file before anything else moves, so a refused edit -- the file
            // changed since it was opened, a cell or a paragraph that may not
            // be changed -- leaves the file, its name and its record exactly
            // as they were.
            if (isset($request['cells']) || isset($request['paragraphs'])) {

                $office_kind = pg_office_kind($file['name']);
                $office_changes = ($office_kind === 'xlsx') ? ($request['cells'] ?? null) : (($office_kind === 'docx') ? ($request['paragraphs'] ?? null) : null);

                if (!is_array($office_changes)) {
                    respond(array('status' => 'error', 'message' => lang('This file cannot be edited here.')));
                }

                // A large sheet takes a few seconds to rewrite.
                @set_time_limit(120);

                $office_version = isset($request['office_version']) ? (string) $request['office_version'] : '';
                $office_saved = ($office_kind === 'xlsx')
                    ? pg_office_xlsx_save($old_path, $office_changes, $office_version)
                    : pg_office_docx_save($old_path, $office_changes, $office_version);

                if (!$office_saved['ok']) {
                    respond(array('status' => 'error', 'message' => $office_saved['error']));
                }

                $size_sql = "size = '" . e((int) $office_saved['size']) . "',";
            }

            // The record's name is the address, so the disk file moves first
            // and the row is only told if it did -- a row pointing at nothing
            // is exactly the state this order exists to prevent.
            if ($name != $file['name']) {
                if ((file_exists($old_path) == true) && (@rename($old_path, $new_path) == false)) {
                    respond(array('status' => 'error', 'message' => lang('The file could not be renamed on the file system. Check write permission for the file directory.')));
                }
            }

            $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

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

            $audit_before = pg_audit_fetch('files', $file_id);
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
            pg_audit_record('update', 'files', $file_id, $audit_before);

            log_activity(lang(array('string' => 'file ({var:1}) was modified', 'vars' => $name)), $_SESSION['sessionusername']);

            respond(array(
                'status' => 'success',
                'request' => $type,
                'file' => pg_explorer_file_payload(pg_explorer_file_row($file_id), $user),
                'message' => lang('The file was edited successfully.')));
            break;

        // ── Turn a picture a quarter to the right ────────────────────────
        case 'explorer_rotate':
            pg_explorer_handle_rotate($type, $request, $user);
            break;

        // ── ERP documents, read-only ─────────────────────────────────────
        //
        // The tree alone (for the sidebar), or one listing: the virtual
        // folders at the top, one folder's documents, or a search. At the top
        // a search runs across every folder, the way the module's own lists
        // cannot. Nothing is generated here: a document is rendered or fetched
        // from the provider only when it is opened.
        case 'explorer_erp_tree':
        case 'explorer_erp_list':

            if (pg_explorer_erp_ready($user) == false) {
                respond(array('status' => 'error', 'request' => $type, 'message' => lang('Access denied')));
            }

            $erp_tree = pg_explorer_erp_tree();

            if ($type == 'explorer_erp_tree') {
                respond(array('status' => 'success', 'request' => $type, 'tree' => $erp_tree));
            }

            if (isset($request['view_type']) && in_array($request['view_type'], array('grid', 'list'), true)) {
                $_SESSION['software']['explorer']['folder']['view_type'] = $request['view_type'];
            }

            $view_type = ($_SESSION['software']['explorer']['folder']['view_type'] ?? 'grid');

            if (in_array($view_type, array('grid', 'list'), true) == false) {
                $view_type = 'grid';
            }

            // Counting and listing do not touch the session; let the next
            // request of this screen in while they run.
            pg_explorer_release_session();

            $erp_labels = array();
            $erp_notes = array();

            foreach ($erp_tree as $node) {
                $erp_labels[$node['key']] = $node['label'];
                $erp_notes[$node['key']] = $node['note'];
            }

            $erp_folder = isset($request['folder']) ? (string) $request['folder'] : '';

            if (isset($erp_labels[$erp_folder]) == false) {
                $erp_folder = '';
            }

            $erp_search = isset($request['search']) ? trim((string) $request['search']) : '';

            if (mb_strlen($erp_search) > 100) {
                $erp_search = mb_substr($erp_search, 0, 100);
            }

            $erp_offset = max(0, (int) ($request['offset'] ?? 0));
            $erp_page = 200;
            $erp_folders = array();
            $erp_files = array();
            $erp_total = 0;
            $index = 0;

            if ($erp_folder != '') {

                $found = erp_files_list($erp_folder, array('search' => $erp_search, 'limit' => $erp_page, 'offset' => $erp_offset));
                $erp_total = (int) $found['total'];
                $index = $erp_offset;

                foreach ($found['items'] as $entry) {
                    $erp_files[] = pg_explorer_erp_file_item($entry, $erp_folder, $erp_labels[$erp_folder], ++$index, $user);
                }

            } elseif ($erp_search != '') {

                // Every folder that holds a match, up to fifty from each; the
                // item says which folder it was found in.
                foreach (erp_files_search($erp_search, 50) as $entry) {
                    $entry_folder = (string) ($entry['folder'] ?? '');
                    $erp_files[] = pg_explorer_erp_file_item($entry, $entry_folder, $erp_labels[$entry_folder] ?? '', ++$index, $user);
                }

                $erp_total = count($erp_files);

            } else {

                foreach ($erp_tree as $node) {
                    $erp_folders[] = pg_explorer_erp_folder_item($node, ++$index);
                }

                $erp_total = count($erp_folders);
            }

            respond(array(
                'status' => 'success',
                'request' => $type,
                'folder' => $erp_folder,
                'folder_label' => ($erp_folder != '') ? $erp_labels[$erp_folder] : '',
                'folder_note' => ($erp_folder != '') ? $erp_notes[$erp_folder] : '',
                'search' => $erp_search,
                'offset' => $erp_offset,
                'total' => $erp_total,
                'view_type' => $view_type,
                'tree' => $erp_tree,
                'folders' => $erp_folders,
                'files' => $erp_files,
                'capabilities' => array(
                    'role' => (int) $user['role'],
                    'is_manager' => ($user['role'] <= 2),
                    'is_designer' => ($user['role'] <= 1),
                    'show_product_images' => (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES)));
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

            $audit_before = (count($allowed) > 0) ? pg_audit_fetch_many('page', $allowed) : array();

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

            pg_audit_record_many('update', 'page', $audit_before);

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
                $audit_before = pg_audit_fetch_many('files', $allowed);

                db(
                    "UPDATE files
                    SET
                        " . implode(",\n                        ", $assignments) . ",
                        timestamp = UNIX_TIMESTAMP(),
                        user = '" . e($user['id']) . "'
                    WHERE id IN (" . implode(',', $allowed) . ")");

                pg_audit_record_many('update', 'files', $audit_before);

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
