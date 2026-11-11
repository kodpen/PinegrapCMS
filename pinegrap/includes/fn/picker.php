<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The picker's registry and the answers of its endpoint (picker.php). A kind
 * names what is chosen (files of some types, pictures, pages), who may choose
 * it and what a pick stores; the browser names a kind and never a condition.
 * The requests are list (one folder's items), search (a folded name match
 * across the folders the user may use), tree (the folder tree's branches) and
 * resolve (the stored values a field already holds, with their state).
 *
 * The pure helpers build conditions, validate requests, match names and page
 * the answers without the database; the pg_picker_*_answer() functions read
 * it. file_picker_search.php answers through the same helpers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The kinds a picker may ask for, by name.
 *
 *   source    'files' or 'pages': the table the items come from
 *   types     files: files.type values listed; pages: page.page_type values;
 *             empty lists every type
 *   public    files: only files every visitor may open
 *             (pg_public_image_files_where()), as what is chosen is shown to
 *             everyone
 *   design    files: design files listed too
 *   archived  pages: pages in an archived folder listed too, marked and not
 *             choosable (pg_picker_page_mark())
 *   value     what a pick stores: 'name' (files.name) or 'id'
 *   role      the highest role that may ask (0 Administrator ... 3 User); a
 *             role 3 user only with edit rights, within the folders they may
 *             edit
 *   tree      the dialog shows the folder tree
 *   pictures  items are drawn as pictures
 *   upload    the dialog may upload into the open folder
 *
 * site_icon and public_image are the Site Settings pictures (Site Icon; App
 * Icon, Default Share Image, Organization Logo, Fediverse avatar), behind the
 * settings gate; file is a file by id (short link destination); image is a
 * picture by name (content and product pictures); page is a page by id.
 *
 * @return array name => definition
 */
function pg_picker_kinds()
{
    static $kinds = null;

    if ($kinds !== null) {
        return $kinds;
    }

    $file = array('source' => 'files', 'types' => array(), 'public' => false, 'design' => true, 'archived' => true, 'value' => 'id', 'role' => 3, 'tree' => true, 'pictures' => false, 'upload' => true);

    $kinds = array(
        'file' => $file,
        'image' => array_merge($file, array('types' => pg_picker_image_types(), 'value' => 'name', 'pictures' => true)),
        'public_image' => array_merge($file, array('types' => pg_public_image_file_types(), 'public' => true, 'value' => 'name', 'role' => 2, 'pictures' => true)),
        'site_icon' => array_merge($file, array('types' => array('png', 'svg'), 'public' => true, 'value' => 'name', 'role' => 2, 'pictures' => true)),
        'page' => array('source' => 'pages', 'types' => array(), 'public' => false, 'design' => false, 'archived' => true, 'value' => 'id', 'role' => 3, 'tree' => true, 'pictures' => false, 'upload' => false),
    );

    return $kinds;
}

/** The picture types the image kind lists: the editor's image browser's. */
function pg_picker_image_types()
{
    return array('gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'svg', 'webp', 'avif');
}

/** The requests the endpoint answers; upload is the one sent by POST. */
function pg_picker_requests()
{
    return array('list', 'search', 'tree', 'resolve', 'upload');
}

/**
 * The paging bounds: the page size when none is asked, the largest one, the
 * highest page number, the most values one resolve and branches one tree
 * request take.
 *
 * @return array default, max, max_page, max_values, max_nodes
 */
function pg_picker_bounds()
{
    return array('default' => 50, 'max' => 100, 'max_page' => 1000, 'max_values' => 50, 'max_nodes' => 25);
}

/** The most rows one search reads before the folded match filters them. */
function pg_picker_scan_limit()
{
    return 400;
}

/**
 * Whether a user may ask for a kind. Pure.
 *
 * @param mixed      $kind        the requested kind name
 * @param mixed      $role        user.user_role
 * @param bool       $edit_rights a role 3 user may edit at least one folder (no_acl_check())
 * @param array|null $kinds       the kinds to look in; pg_picker_kinds() when null
 * @return string 'ok', 'unknown' (no such kind) or 'denied'
 */
function pg_picker_access_verdict($kind, $role, $edit_rights, $kinds = null)
{
    if ($kinds === null) {
        $kinds = pg_picker_kinds();
    }

    if (!is_string($kind) || !isset($kinds[$kind])) {
        return 'unknown';
    }

    $role = (int) $role;

    if ($role > (int) $kinds[$kind]['role']) {
        return 'denied';
    }

    // A User without edit rights has no panel screen to pick from.
    if (($role >= 3) && !$edit_rights) {
        return 'denied';
    }

    return 'ok';
}

/**
 * A whole number of 0 or more read from a request value, or null. Pure.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_picker_whole($value)
{
    if (is_int($value)) {
        return ($value >= 0) ? $value : null;
    }

    if (is_string($value) && ($value !== '') && (strlen($value) <= 10) && ctype_digit($value)) {
        return (int) $value;
    }

    return null;
}

/**
 * The request read from the query string, checked. The kind and the request
 * must be named exactly; every other value falls back to its default when it
 * is missing or not of its shape, and the page size is held within its
 * bounds. Pure.
 *
 * @param array $input $_GET
 * @return array error ('' or 'kind', 'request'), kind, request, q, folder,
 *               page, per_page, values (resolve), node_ids (tree)
 */
function pg_picker_request($input)
{
    $input = is_array($input) ? $input : array();
    $bounds = pg_picker_bounds();
    $kinds = pg_picker_kinds();

    $request = array(
        'error' => '',
        'kind' => (isset($input['kind']) && is_string($input['kind'])) ? $input['kind'] : '',
        'request' => (isset($input['request']) && is_string($input['request'])) ? $input['request'] : '',
        'q' => (isset($input['q']) && is_string($input['q'])) ? mb_substr(trim($input['q']), 0, 100, 'UTF-8') : '',
        'folder' => 0,
        'page' => 1,
        'per_page' => $bounds['default'],
        'values' => array(),
        'node_ids' => array(),
    );

    if (!isset($kinds[$request['kind']])) {
        $request['error'] = 'kind';
        return $request;
    }

    if (!in_array($request['request'], pg_picker_requests(), true)) {
        $request['error'] = 'request';
        return $request;
    }

    $folder = isset($input['folder']) ? pg_picker_whole($input['folder']) : null;
    $request['folder'] = ($folder !== null) ? $folder : 0;

    $page = isset($input['page']) ? pg_picker_whole($input['page']) : null;
    $request['page'] = (($page !== null) && ($page >= 1)) ? min($page, $bounds['max_page']) : 1;

    $per_page = isset($input['per_page']) ? pg_picker_whole($input['per_page']) : null;
    $request['per_page'] = (($per_page !== null) && ($per_page >= 1)) ? min($per_page, $bounds['max']) : $bounds['default'];

    $raw = isset($input['values']) ? $input['values'] : (isset($input['value']) ? array($input['value']) : array());
    $request['values'] = pg_picker_values($raw, $kinds[$request['kind']]['value']);

    $nodes = array();

    foreach ((isset($input['node_ids']) ? (array) $input['node_ids'] : array(0)) as $node) {
        $node = pg_picker_whole($node);

        if (($node !== null) && !in_array($node, $nodes, true)) {
            $nodes[] = $node;
        }

        if (count($nodes) >= $bounds['max_nodes']) {
            break;
        }
    }

    $request['node_ids'] = $nodes;

    return $request;
}

/**
 * The stored values a resolve asks about: whole ids of 1 or more for an id
 * kind, names of at most 255 characters for a name kind; each once, at most
 * max_values. Pure.
 *
 * @param mixed  $raw
 * @param string $value 'id' or 'name'
 * @return array
 */
function pg_picker_values($raw, $value)
{
    $bounds = pg_picker_bounds();
    $values = array();

    foreach ((is_array($raw) ? $raw : array($raw)) as $item) {
        if ($value === 'id') {
            $item = pg_picker_whole($item);

            if (($item === null) || ($item < 1)) {
                continue;
            }
        } else {
            if (!is_string($item) || (trim($item) === '') || (mb_strlen($item, 'UTF-8') > 255)) {
                continue;
            }
        }

        if (!in_array($item, $values, true)) {
            $values[] = $item;
        }

        if (count($values) >= $bounds['max_values']) {
            break;
        }
    }

    return $values;
}

/**
 * Whether a user may use a folder in a picker: never the Recycle Bin or a
 * folder inside it, and for a role 3 user only a folder they may edit. Pure.
 *
 * @param mixed      $folder_id
 * @param int[]      $bin_ids the Recycle Bin and its sub-folders
 * @param int[]|null $granted the folders a role 3 user may edit; null for all
 * @return bool
 */
function pg_picker_folder_allowed($folder_id, $bin_ids, $granted)
{
    $folder_id = (int) $folder_id;

    if (($folder_id < 1) || in_array($folder_id, array_map('intval', (array) $bin_ids), true)) {
        return false;
    }

    return ($granted === null) || in_array($folder_id, array_map('intval', (array) $granted), true);
}

/**
 * Distinct whole ids. Pure.
 *
 * @param mixed $ids
 * @return int[]
 */
function pg_picker_ids($ids)
{
    return array_values(array_unique(array_map('intval', (array) $ids)));
}

/**
 * The plain types of a kind (extensions or page types); anything that is not
 * a plain word is dropped, though the types come from the registry. Pure.
 *
 * @param array $kind
 * @return string[]
 */
function pg_picker_kind_types($kind)
{
    $types = array();

    foreach ((array) ($kind['types'] ?? array()) as $type) {
        $type = strtolower((string) $type);

        if (preg_match('/^[a-z0-9 _]{1,40}$/', $type) && !in_array($type, $types, true)) {
            $types[] = $type;
        }
    }

    return $types;
}

/**
 * The `files` condition of a file kind. Pure.
 *
 * @param array      $kind         one of pg_picker_kinds()
 * @param string     $public_where pg_public_image_files_where() for a public kind
 * @param int[]      $bin_ids      the Recycle Bin and its sub-folders
 * @param int[]|null $folders      the folders a role 3 user may edit; null for all
 * @param int|null   $folder       one folder to list; null for every folder
 * @return string
 */
function pg_picker_files_where($kind, $public_where, $bin_ids, $folders, $folder = null)
{
    $where = array("(name != '')", '(attachment = 0)');

    if (empty($kind['design'])) {
        $where[] = '(design = 0)';
    }

    $types = array();

    foreach (pg_picker_kind_types($kind) as $type) {
        if (preg_match('/^[a-z0-9]{1,10}$/', $type)) {
            $types[] = $type;
        }
    }

    if ($types) {
        $where[] = "(type IN ('" . implode("', '", $types) . "'))";
    }

    // A public kind without its condition lists nothing.
    if (!empty($kind['public'])) {
        $where[] = (trim((string) $public_where) !== '') ? '(' . $public_where . ')' : '(folder IN (-1))';
    }

    $bin = pg_picker_ids($bin_ids);

    if ($bin) {
        $where[] = '(folder NOT IN (' . implode(', ', $bin) . '))';
    }

    if ($folders !== null) {
        $folders = pg_picker_ids($folders);
        $where[] = '(folder IN (' . ($folders ? implode(', ', $folders) : '-1') . '))';
    }

    if ($folder !== null) {
        $where[] = "(folder = '" . (int) $folder . "')";
    }

    return implode(' AND ', $where);
}

/**
 * The `page LEFT JOIN folder` condition of a page kind. Pure.
 *
 * @param array      $kind    one of pg_picker_kinds()
 * @param int[]      $bin_ids the Recycle Bin and its sub-folders
 * @param int[]|null $folders the folders a role 3 user may edit; null for all
 * @param int|null   $folder  one folder to list; null for every folder
 * @return string
 */
function pg_picker_pages_where($kind, $bin_ids, $folders, $folder = null)
{
    $where = array("(page.page_name != '')");

    if (empty($kind['archived'])) {
        $where[] = "(folder.folder_archived = '0')";
    }

    $types = pg_picker_kind_types($kind);

    if ($types) {
        $where[] = "(page.page_type IN ('" . implode("', '", $types) . "'))";
    }

    $bin = pg_picker_ids($bin_ids);

    if ($bin) {
        $where[] = '(page.page_folder NOT IN (' . implode(', ', $bin) . '))';
    }

    if ($folders !== null) {
        $folders = pg_picker_ids($folders);
        $where[] = '(page.page_folder IN (' . ($folders ? implode(', ', $folders) : '-1') . '))';
    }

    if ($folder !== null) {
        $where[] = "(page.page_folder = '" . (int) $folder . "')";
    }

    return implode(' AND ', $where);
}

/**
 * A name or a typed text folded for matching: lowercase, every i-letter a
 * plain i, accents dropped. Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_picker_fold($text)
{
    return fold_keyword_for_matching(trim((string) $text));
}

/**
 * The LIKE term (escaped for LIKE, not yet for SQL) the rows are read with:
 * the folded text, each i a one-letter wildcard. The column's collation
 * ignores case and accents but keeps i and ı apart, so "isik" would miss
 * "ışık"; the wildcard reads both and pg_picker_matches() keeps only the real
 * matches. Pure.
 *
 * @param mixed $query
 * @return string
 */
function pg_picker_like_term($query)
{
    return implode('_', array_map('escape_like', explode('i', pg_picker_fold($query))));
}

/**
 * The rows whose folded name holds the folded text, those starting with it
 * first, each group in the order read. Pure.
 *
 * @param array  $rows
 * @param mixed  $query    the typed text
 * @param string $name_key the column holding the name
 * @return array the matching rows
 */
function pg_picker_matches($rows, $query, $name_key = 'name')
{
    $needle = pg_picker_fold($query);

    if ($needle === '') {
        return array();
    }

    $first = array();
    $rest = array();

    foreach ((array) $rows as $row) {
        $name = (string) ($row[$name_key] ?? '');

        if ($name === '') {
            continue;
        }

        $at = mb_strpos(pg_picker_fold($name), $needle, 0, 'UTF-8');

        if ($at === 0) {
            $first[] = $row;
        } elseif ($at !== false) {
            $rest[] = $row;
        }
    }

    return array_merge($first, $rest);
}

/**
 * One page of a list read with one row more than the page holds. Pure.
 *
 * @param array $items    the rows from the page's offset on
 * @param int   $per_page
 * @return array items (at most $per_page), more (a next page exists)
 */
function pg_picker_page_slice($items, $per_page)
{
    $items = array_values((array) $items);
    $per_page = max(1, (int) $per_page);

    return array('items' => array_slice($items, 0, $per_page), 'more' => (count($items) > $per_page));
}

/**
 * The first row of a page. Pure.
 *
 * @param int $page     1 or more
 * @param int $per_page
 * @return int
 */
function pg_picker_offset($page, $per_page)
{
    return (max(1, (int) $page) - 1) * max(1, (int) $per_page);
}

/**
 * One item as the endpoint answers it. Pure.
 *
 * @param array $kind one of pg_picker_kinds()
 * @param array $row  files: id, name, type, folder; pages: page_id,
 *                    page_name, page_type, page_folder, page_timestamp,
 *                    folder_archived, binned (pg_picker_binned_page_row())
 * @return array id, value (what a pick stores), text, type, folder; a page
 *               also modified and mark (pg_picker_page_mark())
 */
function pg_picker_item($kind, $row)
{
    if ($kind['source'] === 'pages') {
        return array(
            'id' => (int) ($row['page_id'] ?? 0),
            'value' => (int) ($row['page_id'] ?? 0),
            'text' => (string) ($row['page_name'] ?? ''),
            'type' => (string) ($row['page_type'] ?? ''),
            'folder' => (int) ($row['page_folder'] ?? 0),
            'modified' => (int) ($row['page_timestamp'] ?? 0),
            'mark' => pg_picker_page_mark((string) ($row['folder_archived'] ?? '0') !== '0', !empty($row['binned'])),
        );
    }

    return array(
        'id' => (int) ($row['id'] ?? 0),
        'value' => ($kind['value'] === 'id') ? (int) ($row['id'] ?? 0) : (string) ($row['name'] ?? ''),
        'text' => (string) ($row['name'] ?? ''),
        'type' => (string) ($row['type'] ?? ''),
        'folder' => (int) ($row['folder'] ?? 0),
        'size' => (int) ($row['size'] ?? 0),
        'width' => (int) ($row['image_width'] ?? 0),
        'height' => (int) ($row['image_height'] ?? 0),
        'modified' => (int) ($row['timestamp'] ?? 0),
    );
}

/**
 * The mark a page carries in the dialog, which keeps a marked page from being
 * chosen: a page in an archived folder is offline, a binned one is on its way
 * out. The Recycle Bin is itself an archived folder, so the bin wins. Pure.
 *
 * @param bool $archived its folder is archived
 * @param bool $binned   it is in the Recycle Bin
 * @return string '', 'archived' or 'binned'
 */
function pg_picker_page_mark($archived, $binned)
{
    if ($binned) {
        return 'binned';
    }

    return $archived ? 'archived' : '';
}

/**
 * A binned page's row as the dialog lists it: under the name it had before
 * the bin parked it, in the folder it came from (recycle_bin
 * .original_parent_id), flagged binned. Pure.
 *
 * @param array $row page_id, page_name, ..., original_parent_id
 * @return array the row with page_name, page_folder and binned set
 */
function pg_picker_binned_page_row($row)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_names.php');

    $original = pg_recycle_parked_original((string) ($row['page_name'] ?? ''), (int) ($row['page_id'] ?? 0));

    if ($original !== '') {
        $row['page_name'] = $original;
    }

    $row['page_folder'] = (int) ($row['original_parent_id'] ?? 0);
    $row['binned'] = true;

    return $row;
}

/**
 * The groups the dialog's type chips offer, in the order they are drawn,
 * with their labels. Every page type falls in one
 * (pg_picker_page_type_group()).
 *
 * @return array key => label
 */
function pg_picker_page_groups()
{
    return array(
        'standard' => lang('Standard'),
        'form' => lang('Forms'),
        'catalog' => lang('Catalog'),
        'calendar' => lang('Calendars'),
        'checkout' => lang('Cart and checkout'),
        'search' => lang('Search Results'),
        'account' => lang('Sign-in and account'),
        'other' => lang('Other'),
    );
}

/**
 * The type chip group of a page type (get_page_type_names()); a type no
 * group names is Other. Pure.
 *
 * @param string $type page.page_type
 * @return string a key of pg_picker_page_groups()
 */
function pg_picker_page_type_group($type)
{
    static $groups = array(
        'standard' => array('standard'),
        'form' => array('custom form', 'custom form confirmation', 'form list view', 'form item view', 'form view directory'),
        'catalog' => array('catalog', 'catalog detail'),
        'calendar' => array('calendar view', 'calendar event view'),
        'checkout' => array('express order', 'order form', 'shopping cart', 'shipping address and arrival', 'shipping method', 'billing information', 'order preview', 'order receipt'),
        'search' => array('search results'),
        'account' => array(
            'login', 'logout', 'change password', 'set password', 'forgot password', 'my account', 'my account profile',
            'email preferences', 'view order', 'update address book', 'membership confirmation', 'membership entrance',
            'registration confirmation', 'registration entrance', 'affiliate sign up form', 'affiliate sign up confirmation',
            'affiliate welcome'),
    );

    foreach ($groups as $group => $types) {
        if (in_array((string) $type, $types, true)) {
            return $group;
        }
    }

    return 'other';
}

/**
 * What the dialog draws beside a page item: its address (none for a binned
 * page, which no visitor reaches), its type's name and chip group, and with
 * $paths the folders down to it.
 *
 * @param array $items pg_picker_item() of a page kind
 * @param bool  $paths
 * @return array
 */
function pg_picker_page_details($items, $paths = false)
{
    if ($paths) {
        require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
    }

    $map = $paths ? pg_explorer_folder_map() : array();

    foreach ($items as $index => $item) {
        $label = get_page_type_name($item['type']);
        $item['address'] = ($item['mark'] === 'binned') ? '' : PATH . $item['text'];
        $item['type_label'] = ($label !== null) ? $label : $item['type'];
        $item['type_group'] = pg_picker_page_type_group($item['type']);

        if ($paths) {
            $item['path'] = pg_picker_folder_path($item['folder'], $map);
        }

        $items[$index] = $item;
    }

    return $items;
}

/**
 * The binned pages that came from the folders a user may use, as
 * pg_picker_binned_page_row() tells them: those from one folder, or those
 * whose name holds a typed text (at most pg_picker_scan_limit()).
 *
 * @param array    $user   validate_user()
 * @param int|null $folder the folder they came from
 * @param string   $query  the typed text; '' for no name match
 * @return array rows
 */
function pg_picker_binned_page_rows($user, $folder = null, $query = '')
{
    $bin = pg_picker_ids(pg_recycle_bin_folder_ids());

    if (!$bin) {
        return array();
    }

    $where = "(recycle_bin.item_type = 'page') AND (page.page_folder IN (" . implode(', ', $bin) . "))";

    if ($folder !== null) {
        $where .= " AND (recycle_bin.original_parent_id = '" . (int) $folder . "')";
    }

    if ($query !== '') {
        $where .= " AND (LOWER(page.page_name) LIKE '%" . e(pg_picker_like_term($query)) . "%')";
    }

    $rows = db_items(
        "SELECT page.page_id, page.page_name, page.page_type, page.page_folder, page.page_timestamp, folder.folder_archived, recycle_bin.original_parent_id
        FROM recycle_bin
        INNER JOIN page ON page.page_id = recycle_bin.item_id
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        WHERE " . $where . "
        ORDER BY page.page_name
        LIMIT " . pg_picker_scan_limit());

    $granted = pg_picker_granted_folders($user);
    $out = array();

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        $row = pg_picker_binned_page_row($row);

        if (pg_picker_folder_allowed($row['page_folder'], $bin, $granted)) {
            $out[] = $row;
        }
    }

    return ($query !== '') ? pg_picker_matches($out, $query, 'page_name') : $out;
}

/**
 * The folders from the top down to a folder, as the dialog's breadcrumb and
 * a search result's folder line show them. Pure.
 *
 * @param int   $folder_id
 * @param array $map folder_id => row with folder_name and folder_parent
 *                   (pg_explorer_folder_map())
 * @return array [{id, name}], empty for a folder the map does not hold
 */
function pg_picker_folder_path($folder_id, $map)
{
    $path = array();
    $seen = array();
    $id = (int) $folder_id;

    // A parent loop in damaged data must not hang the request.
    while (($id > 0) && isset($map[$id]) && !isset($seen[$id])) {
        $seen[$id] = true;
        array_unshift($path, array('id' => $id, 'name' => (string) $map[$id]['folder_name']));
        $id = (int) $map[$id]['folder_parent'];
    }

    return $path;
}

/**
 * Whether the dialog draws an item's own picture: a picture kind's raster
 * or vector file. Pure.
 *
 * @param array  $kind one of pg_picker_kinds()
 * @param string $name the file name
 * @return bool
 */
function pg_picker_has_picture($kind, $name)
{
    if (empty($kind['pictures'])) {
        return false;
    }

    $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

    return in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp'), true);
}

/**
 * File items with what the dialog shows beside the name: the size as text,
 * the access of the folder (inherited down the tree), the address, the 320
 * px picture (pg_image_panel_thumb_url()) for a picture kind and, with
 * $paths, the folders down to the item's own. Page items get
 * pg_picker_page_details().
 *
 * @param array $kind
 * @param array $items pg_picker_item() of the kind
 * @param bool  $paths
 * @return array
 */
function pg_picker_file_details($kind, $items, $paths = false)
{
    if ($kind['source'] !== 'files') {
        return pg_picker_page_details($items, $paths);
    }

    require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

    $map = $paths ? pg_explorer_folder_map() : array();
    $access = array();

    foreach ($items as $index => $item) {
        $folder = (int) $item['folder'];

        if (!isset($access[$folder])) {
            $access[$folder] = (string) get_access_control_type($folder);
        }

        $url = PATH . $item['text'];
        $item['size_label'] = convert_bytes_to_string($item['size'], 'auto');
        $item['access'] = $access[$folder];
        $item['url'] = $url;
        $item['thumb'] = pg_picker_has_picture($kind, $item['text'])
            ? pg_image_panel_thumb_url($url . ($item['modified'] ? '?v=' . $item['modified'] : ''))
            : '';

        if ($paths) {
            $item['path'] = pg_picker_folder_path($folder, $map);
        }

        $items[$index] = $item;
    }

    return $items;
}

/**
 * Each folder's count with the counts of the folders under it added, as the
 * tree shows a branch's total. Pure.
 *
 * @param int[] $direct folder id => items directly in it
 * @param array $map    folder_id => row with folder_parent
 * @return int[] folder id => items in it and under it
 */
function pg_picker_subtree_counts($direct, $map)
{
    $totals = array();

    foreach ($direct as $folder => $count) {
        $seen = array();
        $id = (int) $folder;

        // A parent loop in damaged data must not hang the request.
        while (($id > 0) && !isset($seen[$id])) {
            $seen[$id] = true;
            $totals[$id] = ($totals[$id] ?? 0) + (int) $count;
            $id = isset($map[$id]) ? (int) $map[$id]['folder_parent'] : 0;
        }
    }

    return $totals;
}

/**
 * How many of a kind's items each folder and the folders under it hold, for
 * the tree's counts. Read once per request.
 *
 * @param string $kind_name
 * @param array  $user
 * @return int[] folder id => count
 */
function pg_picker_folder_counts($kind_name, $user)
{
    static $counts = array();

    if (isset($counts[$kind_name])) {
        return $counts[$kind_name];
    }

    $kinds = pg_picker_kinds();
    $kind = $kinds[$kind_name];
    $column = ($kind['source'] === 'pages') ? 'page.page_folder' : 'folder';
    $from = ($kind['source'] === 'pages') ? 'page LEFT JOIN folder ON page.page_folder = folder.folder_id' : 'files';

    $rows = db_items("SELECT " . $column . " AS folder_id, COUNT(*) AS items FROM " . $from . " WHERE " . pg_picker_where_for($kind, $user) . " GROUP BY " . $column);
    $direct = array();

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        $direct[(int) $row['folder_id']] = (int) $row['items'];
    }

    require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
    $counts[$kind_name] = pg_picker_subtree_counts($direct, pg_explorer_folder_map());

    return $counts[$kind_name];
}

/**
 * The state of a stored value. Pure.
 *
 * @param bool $found   a row carries it
 * @param bool $visible the user may use its folder (role 3 folder rights)
 * @param bool $binned  it is in the Recycle Bin
 * @param bool $offline a public kind's file visitors cannot open, or a page
 *                      kind's page in an archived folder
 * @return string 'ok', 'missing', 'hidden' (its name is not told), 'binned'
 *                or 'offline'
 */
function pg_picker_value_state($found, $visible, $binned, $offline)
{
    if (!$found) {
        return 'missing';
    }

    if (!$visible) {
        return 'hidden';
    }

    if ($binned) {
        return 'binned';
    }

    return $offline ? 'offline' : 'ok';
}

/**
 * The folders a role 3 user may edit, null for a higher role (every folder).
 *
 * @param array $user validate_user()
 * @return int[]|null
 */
function pg_picker_granted_folders($user)
{
    return ((int) $user['role'] >= 3) ? pg_picker_ids(get_folders_that_user_has_access_to($user['id'])) : null;
}

/**
 * The SELECT of a kind's items under a condition.
 *
 * @param array  $kind
 * @param string $where
 * @return string
 */
function pg_picker_select($kind, $where)
{
    if ($kind['source'] === 'pages') {
        return "SELECT page.page_id, page.page_name, page.page_type, page.page_folder, page.page_timestamp, folder.folder_archived
            FROM page
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            WHERE " . $where;
    }

    return "SELECT id, name, type, folder, size, image_width, image_height, timestamp FROM files WHERE " . $where;
}

/**
 * A kind's condition for a user, optionally kept to one folder.
 *
 * @param array    $kind
 * @param array    $user
 * @param int|null $folder
 * @return string
 */
function pg_picker_where_for($kind, $user, $folder = null)
{
    if ($kind['source'] === 'pages') {
        return pg_picker_pages_where($kind, pg_recycle_bin_folder_ids(), pg_picker_granted_folders($user), $folder);
    }

    return pg_picker_files_where(
        $kind,
        !empty($kind['public']) ? pg_public_image_files_where() : '',
        pg_recycle_bin_folder_ids(),
        pg_picker_granted_folders($user),
        $folder);
}

/**
 * The rows of a kind whose name matches the typed text, read with the LIKE
 * term (at most pg_picker_scan_limit()) and kept by the folded match.
 *
 * @param string $kind_name
 * @param mixed  $query
 * @param array  $user validate_user()
 * @return array
 */
function pg_picker_search_rows($kind_name, $query, $user)
{
    $kinds = pg_picker_kinds();
    $query = mb_substr(trim((string) $query), 0, 100, 'UTF-8');

    if (!isset($kinds[$kind_name]) || (pg_picker_fold($query) === '')) {
        return array();
    }

    $kind = $kinds[$kind_name];
    $column = ($kind['source'] === 'pages') ? 'page.page_name' : 'name';

    $rows = db_items(
        pg_picker_select($kind, pg_picker_where_for($kind, $user))
        . " AND (LOWER(" . $column . ") LIKE '%" . e(pg_picker_like_term($query)) . "%')
        ORDER BY " . $column . "
        LIMIT " . pg_picker_scan_limit());

    if ($kind['source'] !== 'pages') {
        return pg_picker_matches(is_array($rows) ? $rows : array(), $query, 'name');
    }

    // Binned pages are found too, under their own names, after the others:
    // marked, so the person sees why the page they look for cannot be chosen.
    return array_merge(pg_picker_matches(is_array($rows) ? $rows : array(), $query, 'page_name'), pg_picker_binned_page_rows($user, null, $query));
}

/**
 * The answer to a search request.
 *
 * @param array $request pg_picker_request()
 * @param array $user
 * @return array items, page, per_page, more
 */
function pg_picker_search_answer($request, $user)
{
    $kinds = pg_picker_kinds();
    $kind = $kinds[$request['kind']];
    $rows = array_slice(pg_picker_search_rows($request['kind'], $request['q'], $user), pg_picker_offset($request['page'], $request['per_page']), $request['per_page'] + 1);
    $slice = pg_picker_page_slice($rows, $request['per_page']);

    return array(
        'items' => pg_picker_file_details($kind, array_map(function ($row) use ($kind) {
            return pg_picker_item($kind, $row);
        }, $slice['items']), true),
        'page' => $request['page'],
        'per_page' => $request['per_page'],
        'more' => $slice['more'],
    );
}

/**
 * The answer to a list request: one folder's items by name. A folder the
 * user may not use lists nothing.
 *
 * @param array $request pg_picker_request()
 * @param array $user
 * @return array folder, items, page, per_page, more; for a page kind's
 *               first page also binned (the pages binned from the folder)
 */
function pg_picker_list_answer($request, $user)
{
    $kinds = pg_picker_kinds();
    $kind = $kinds[$request['kind']];
    $answer = array('folder' => $request['folder'], 'crumbs' => array(), 'items' => array(), 'page' => $request['page'], 'per_page' => $request['per_page'], 'more' => false);

    if (!pg_picker_folder_allowed($request['folder'], pg_recycle_bin_folder_ids(), pg_picker_granted_folders($user))) {
        return $answer;
    }

    require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

    // The folders above a role 3 user's own stay unnamed, as in their tree.
    $granted = pg_picker_granted_folders($user);

    foreach (pg_picker_folder_path($request['folder'], pg_explorer_folder_map()) as $crumb) {
        if (($granted === null) || in_array($crumb['id'], $granted, true)) {
            $answer['crumbs'][] = $crumb;
        }
    }

    $column = ($kind['source'] === 'pages') ? 'page.page_name' : 'name';

    $rows = db_items(
        pg_picker_select($kind, pg_picker_where_for($kind, $user, $request['folder']))
        . " ORDER BY " . $column . "
        LIMIT " . pg_picker_offset($request['page'], $request['per_page']) . ", " . ($request['per_page'] + 1));

    $slice = pg_picker_page_slice(is_array($rows) ? $rows : array(), $request['per_page']);

    $answer['items'] = pg_picker_file_details($kind, array_map(function ($row) use ($kind) {
        return pg_picker_item($kind, $row);
    }, $slice['items']));
    $answer['more'] = $slice['more'];

    // The pages binned from this folder come with its first page, apart
    // from the paged items, marked.
    if (($kind['source'] === 'pages') && ($request['page'] === 1)) {
        $answer['binned'] = pg_picker_page_details(array_map(function ($row) use ($kind) {
            return pg_picker_item($kind, $row);
        }, pg_picker_binned_page_rows($user, $request['folder'])));
    }

    // Whether the dialog offers Upload in this folder, and if not, why.
    if ($kind['source'] === 'files') {
        $verdict = pg_picker_folder_upload_verdict($kind, $request['folder'], $user);
        $answer['upload'] = array('ok' => ($verdict === ''), 'reason' => pg_picker_upload_reason($verdict));
    }

    return $answer;
}

/**
 * The answer to a tree request: the branches asked for (0 is the top), each
 * the folders under it the user may use, as the File Manager's tree in the
 * menu draws them (pg_explorer_side_folder_row()). A branch the user may not
 * use has no rows; the Recycle Bin is never one.
 *
 * @param array $request pg_picker_request()
 * @param array $user
 * @return array nodes: [{id, rows}]
 */
function pg_picker_tree_answer($request, $user)
{
    // check_folder_access_in_array() reads the user through `global`.
    global $folders_that_user_has_access_to;

    require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

    $granted = pg_picker_granted_folders($user);
    $folders_that_user_has_access_to = ($granted !== null) ? $granted : array();
    $bin_ids = pg_recycle_bin_folder_ids();

    // Which folders hold a folder this person may use.
    $under = array();

    foreach (pg_explorer_folder_map() as $id => $row) {
        if (pg_picker_folder_allowed($id, $bin_ids, $granted)) {
            $under[(int) $row['folder_parent']] = true;
        }
    }

    $nodes = array();

    foreach ($request['node_ids'] as $node) {
        $rows = array();

        if (($node === 0) || pg_picker_folder_allowed($node, $bin_ids, $granted)) {
            foreach ((array) pg_explorer_child_folders($node, $user, $folders_that_user_has_access_to) as $row) {
                $id = (int) $row['folder_id'];

                if (pg_picker_folder_allowed($id, $bin_ids, $granted)) {
                    $counts = pg_picker_folder_counts($request['kind'], $user);
                    $rows[] = pg_explorer_side_folder_row($row, get_access_control_type($id), isset($under[$id])) + array('count' => $counts[$id] ?? 0);
                }
            }
        }

        $nodes[] = array('id' => $node, 'rows' => $rows);
    }

    return array('nodes' => $nodes);
}

/**
 * The answer to a resolve request: each stored value with its name and
 * state (pg_picker_value_state()), in the order asked. A value in a folder a
 * role 3 user may not edit is answered without its name.
 *
 * @param array $request pg_picker_request()
 * @param array $user
 * @return array items: [{value, text, state, id, type, folder}]
 */
function pg_picker_resolve_answer($request, $user)
{
    $kinds = pg_picker_kinds();
    $kind = $kinds[$request['kind']];
    $items = array();

    if (!$request['values']) {
        return array('items' => $items);
    }

    $bin_ids = pg_recycle_bin_folder_ids();
    $granted = pg_picker_granted_folders($user);
    $found = array();

    if ($kind['source'] === 'pages') {
        $rows = db_items(
            "SELECT page.page_id, page.page_name, page.page_type, page.page_folder, page.page_timestamp, folder.folder_archived, recycle_bin.original_parent_id
            FROM page
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            LEFT JOIN recycle_bin ON (recycle_bin.item_type = 'page') AND (recycle_bin.item_id = page.page_id)
            WHERE page.page_id IN (" . implode(', ', pg_picker_ids($request['values'])) . ")");

        foreach ((is_array($rows) ? $rows : array()) as $row) {
            $found[(int) $row['page_id']] = $row;
        }
    } else {
        $column = ($kind['value'] === 'id') ? 'id' : 'name';
        $list = ($kind['value'] === 'id')
            ? implode(', ', pg_picker_ids($request['values']))
            : "'" . implode("', '", array_map('e', $request['values'])) . "'";

        $rows = db_items("SELECT id, name, type, folder, size, image_width, image_height, timestamp FROM files WHERE (" . $column . " IN (" . $list . ")) AND (attachment = 0) AND (name != '')");
        $public = !empty($kind['public']) ? pg_public_folder_ids() : array();

        foreach ((is_array($rows) ? $rows : array()) as $row) {
            $key = ($kind['value'] === 'id') ? (int) $row['id'] : (string) $row['name'];

            if (!isset($found[$key])) {
                $row['offline'] = !empty($kind['public']) && !in_array((int) $row['folder'], $public, true);
                $found[$key] = $row;
            }
        }
    }

    foreach ($request['values'] as $value) {
        $row = $found[$value] ?? null;
        $folder = ($row === null) ? 0 : (int) (($kind['source'] === 'pages') ? $row['page_folder'] : $row['folder']);
        $binned = ($row !== null) && in_array($folder, array_map('intval', $bin_ids), true);
        $visible = ($row !== null) && (($granted === null) || in_array($folder, $granted, true));
        $offline = ($row !== null) && (($kind['source'] === 'pages') ? ((string) ($row['folder_archived'] ?? '0') !== '0') : !empty($row['offline']));
        $state = pg_picker_value_state($row !== null, $visible, $binned, $offline);

        $item = array('value' => $value, 'text' => ($kind['value'] === 'name') ? (string) $value : '', 'state' => $state);

        if (in_array($state, array('ok', 'binned', 'offline'), true)) {
            // A page binned by itself is told in the folder it came from;
            // one inside a binned folder has no record of its own.
            if (($kind['source'] === 'pages') && $binned) {
                $row = isset($row['original_parent_id']) ? pg_picker_binned_page_row($row) : array_merge($row, array('binned' => true));
            }

            $item = array_merge(pg_picker_item($kind, $row), $item);
            $item['text'] = ($kind['source'] === 'pages') ? (string) $row['page_name'] : (string) $row['name'];

            // A binned item is told under the name it had before the bin
            // parked it.
            if ($binned) {
                require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
                $original = pg_recycle_parked_original($item['text'], $item['id']);
                $item['text'] = ($original !== '') ? $original : $item['text'];
            }
        }

        $items[] = $item;
    }

    // Only found items carry a size and a folder to describe.
    foreach ($items as $index => $item) {
        if (isset($item['id'])) {
            $items[$index] = pg_picker_file_details($kind, array($item), true)[0];
        }
    }

    return array('items' => $items);
}

/**
 * Why a kind may not take an upload into a folder, '' when it may. Pure.
 *
 * @param array $kind       one of pg_picker_kinds()
 * @param int   $folder     the folder the files go into
 * @param bool  $usable     the folder exists and the user may pick from it (pg_picker_folder_allowed())
 * @param bool  $edit_right the user may add to what the folder holds (pg_folder_edit_access())
 * @param bool  $public     every visitor may open the folder (pg_public_folder_ids())
 * @return string '', 'kind' (the kind takes no upload), 'folder' (no folder
 *                or no right to it) or 'public' (a public kind's file would
 *                land where visitors cannot open it)
 */
function pg_picker_upload_verdict($kind, $folder, $usable, $edit_right, $public)
{
    if (empty($kind['upload']) || (($kind['source'] ?? '') !== 'files')) {
        return 'kind';
    }

    if (((int) $folder < 1) || !$usable || !$edit_right) {
        return 'folder';
    }

    // The list of a public kind leaves such a file out, so it would vanish
    // from the dialog the moment it was stored.
    if (!empty($kind['public']) && !$public) {
        return 'public';
    }

    return '';
}

/**
 * What the dialog says beside a disabled Upload.
 *
 * @param string $verdict pg_picker_upload_verdict()
 * @return string '' when Upload is offered
 */
function pg_picker_upload_reason($verdict)
{
    switch ($verdict) {
        case 'kind':
            return lang('Files cannot be uploaded here.');

        case 'folder':
            return lang('You do not have the right to add files to this folder.');

        case 'public':
            return lang('Files in this folder are not public. Upload into a public folder.');
    }

    return '';
}

/**
 * Whether a file name has one of a kind's types; any name passes a kind
 * without types. Pure.
 *
 * @param array  $kind
 * @param string $name the name as it was sent
 * @return bool
 */
function pg_picker_upload_type_ok($kind, $name)
{
    $types = pg_picker_kind_types($kind);

    if (!$types) {
        return true;
    }

    return in_array(strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION)), $types, true);
}

/**
 * Whether the token a POST carries is the session's, compared in constant
 * time as validate_token_field() does. Pure.
 *
 * @param mixed $session $_SESSION['software']['token']
 * @param mixed $sent    $_POST['token']
 * @return bool
 */
function pg_picker_token_valid($session, $sent)
{
    return is_string($session) && ($session !== '') && is_string($sent) && hash_equals($session, $sent);
}

/**
 * pg_picker_upload_verdict() for a user and a folder of the database.
 *
 * @param array $kind
 * @param int   $folder
 * @param array $user validate_user()
 * @return string
 */
function pg_picker_folder_upload_verdict($kind, $folder, $user)
{
    $folder = (int) $folder;

    if (empty($kind['upload']) || (($kind['source'] ?? '') !== 'files') || ($folder < 1)) {
        return pg_picker_upload_verdict($kind, $folder, false, false, false);
    }

    $usable = pg_picker_folder_allowed($folder, pg_recycle_bin_folder_ids(), pg_picker_granted_folders($user))
        && ((string) db_value("SELECT folder_id FROM folder WHERE folder_id = '" . $folder . "'") !== '');

    return pg_picker_upload_verdict(
        $kind,
        $folder,
        $usable,
        $usable && pg_folder_edit_access($folder, (int) $user['id'], (int) $user['role']),
        !empty($kind['public']) && in_array($folder, pg_public_folder_ids(), true));
}

/**
 * The answer to an upload: one file stored into the folder by
 * pg_file_store_upload(), under the rules of Upload File, and handed back as
 * the dialog's item. The caller has checked the method, the body and the
 * token.
 *
 * @param array $request pg_picker_request()
 * @param array $user
 * @param mixed $file    $_FILES['file']
 * @return array [status, payload]: payload item, or error
 */
function pg_picker_upload_answer($request, $user, $file)
{
    $kinds = pg_picker_kinds();
    $kind = $kinds[$request['kind']];

    $verdict = pg_picker_folder_upload_verdict($kind, $request['folder'], $user);

    if ($verdict !== '') {
        return array(403, array('error' => pg_picker_upload_reason($verdict)));
    }

    // One file per request: the dialog sends them one after another, so a
    // selection never meets max_file_uploads or post_max_size all at once.
    if (!is_array($file) || !isset($file['error']) || is_array($file['error']) || ((int) $file['error'] === UPLOAD_ERR_NO_FILE)) {
        return array(400, array('error' => lang('Please select a file.')));
    }

    $name = pg_basename((string) ($file['name'] ?? ''));
    $limits = pg_upload_limits();
    $refusal = pg_upload_error_message($name, $file['error'], $limits['file_max']);

    if ($refusal !== '') {
        return array(400, array('error' => $refusal));
    }

    if (!pg_picker_upload_type_ok($kind, $name)) {
        return array(415, array('error' => lang(array(
            'string' => 'Only {var:1} files can be chosen here.',
            'vars'   => array(strtoupper(implode(', ', pg_picker_kind_types($kind))))))));
    }

    $stored = pg_file_store_upload((string) $file['tmp_name'], $name, (int) ($file['size'] ?? 0), array(
        'folder'         => $request['folder'],
        'description'    => '',
        'design'         => null,
        'image_profile'  => '',
        'image_settings' => pg_image_settings(),
        'file_max'       => $limits['file_max']));

    if (!$stored['ok']) {
        return array(400, array('error' => $stored['message']));
    }

    $row = db_item("SELECT id, name, type, folder, size, image_width, image_height, timestamp FROM files WHERE id = '" . (int) $stored['file']['id'] . "'");

    return array(200, array('item' => pg_picker_file_details($kind, array(pg_picker_item($kind, is_array($row) ? $row : array())), true)[0]));
}

/**
 * The answer to a checked request (pg_picker_request() without an error) of
 * a user the caller has let through pg_picker_access_verdict().
 *
 * @param array $request
 * @param array $user
 * @return array
 */
function pg_picker_answer($request, $user)
{
    $kinds = pg_picker_kinds();
    $answer = array('kind' => $request['kind'], 'request' => $request['request']);

    switch ($request['request']) {
        case 'list':
            $answer += pg_picker_list_answer($request, $user);
            break;

        case 'search':
            $answer += pg_picker_search_answer($request, $user);
            break;

        case 'tree':
            // The dialog's title line names the kind's file types.
            $answer['types'] = pg_picker_kind_types($kinds[$request['kind']]);
            $answer += $kinds[$request['kind']]['tree'] ? pg_picker_tree_answer($request, $user) : array('nodes' => array());
            break;

        case 'resolve':
            $answer += pg_picker_resolve_answer($request, $user);
            break;
    }

    return $answer;
}

/**
 * The words of the picker dialog (assets/js/pg_picker.js), by the English
 * text the script asks for.
 *
 * @return array
 */
function pg_picker_texts()
{
    $texts = array();

    foreach (array(
        'Choose a file', 'Choose a picture', 'Choose a folder', 'Close', 'Up', 'Folders', 'Files', 'Folder', 'Search',
        'Clear the search', 'Search results', 'Sort', 'View', 'Grid', 'List', 'Name', 'Modified', 'Size', 'Type', 'Access', 'Best match',
        'Cancel', 'Back', 'Choose', 'Loading…', 'Searching…', 'No results found', 'There is nothing to choose in this folder',
        'The list could not be loaded.', '1 item', '{n} items', '{folders} folders, {files} files', '{n} results for “{q}”',
        'in every folder', 'Only in “{folder}”', 'Capital letters and Turkish letters do not matter',
        'Only the first 2,000 are shown; search to find the rest.', 'Esc clears the search', 'A column heading sorts',
        'Double-click or Enter chooses', 'Nothing chosen yet', 'Tapping a folder shows its files', 'Folders in {folder}',
        'Public', 'Private', 'Guest', 'Registration', 'Membership',
        'Upload', 'Upload File', 'Recently used', 'Uploading · {done} / {total}', 'Uploaded · {done} / {total}',
        'Drop to upload into “{folder}”', '1 file · at most {size} per file', '{n} files · at most {size} per file',
        'Drag files here or choose them from your computer. An uploaded file is saved in this folder and chosen.',
        'uploaded', 'uploaded, chosen', 'Not uploaded', 'Only {types} files can be chosen here.',
        'Larger than {size}, the biggest single file this server accepts.', 'The upload failed.',
        'Open a folder to upload into it.', 'Files you choose appear here.', 'Your last picks in this browser',
        '{n} chosen', 'A click adds or removes, Shift+click adds a row',
        'Choose a page', 'Pages', 'Page', 'Address', 'Archived', 'In the Recycle Bin', 'All', 'Page types', 'Not saved yet',
        'Pages you choose appear here.', 'Archived pages and pages in the Recycle Bin cannot be chosen.',
    ) as $text) {
        $texts[$text] = lang($text);
    }

    return $texts;
}

/**
 * The picker dialog's script and its settings, once per request: the
 * address of picker.php and the texts as JSON the script reads when it
 * opens, so the pair also works in markup a screen injects with jQuery.
 *
 * @return string
 */
function pg_picker_script()
{
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;

    $limits = pg_upload_limits();

    // One file goes in one request, so the request's own ceiling holds it
    // too.
    $file_max = (int) $limits['file_max'];

    if (((int) $limits['request_max'] > 0) && (($file_max === 0) || ((int) $limits['request_max'] < $file_max))) {
        $file_max = (int) $limits['request_max'];
    }

    // The token goes with an upload, as in every panel form; the user names
    // whose Recent list this browser keeps; a file over the server's largest
    // is refused before it is sent. The page kinds and the type chips' groups
    // let the dialog draw pages as pages.
    $page_kinds = array();

    foreach (pg_picker_kinds() as $name => $kind) {
        if ($kind['source'] === 'pages') {
            $page_kinds[] = $name;
        }
    }

    $groups = array();

    foreach (pg_picker_page_groups() as $key => $label) {
        $groups[] = array('key' => $key, 'label' => $label);
    }

    $config = array(
        'endpoint' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/picker.php',
        'texts' => pg_picker_texts(),
        'token' => (string) ($_SESSION['software']['token'] ?? ''),
        'user' => defined('USER_ID') ? (int) USER_ID : 0,
        'file_max' => $file_max,
        'file_max_label' => ($file_max > 0) ? convert_bytes_to_string($file_max, 1) : '',
        'page_kinds' => $page_kinds,
        'page_groups' => $groups);

    return '<script type="application/json" id="pg_picker_config">' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>'
        . pg_js_bundle_tags('assets/js/pg_picker.min.js');
}

/**
 * The Browse button that opens the picker dialog for a field and writes the
 * pick into it.
 *
 * @param string $kind     a kind of pg_picker_kinds()
 * @param string $field_id the id of the field (a select or an input)
 * @param string $title    the dialog's title
 * @return string
 */
function pg_picker_browse_button($kind, $field_id, $title)
{
    return '<button type="button" class="btn btn-outline theme-secondary pg-picker-browse" data-pg-picker-browse data-pg-picker-kind="' . h($kind) . '" data-pg-picker-for="' . h($field_id) . '" data-pg-picker-title="' . h($title) . '" aria-label="' . h(lang('Browse…') . ': ' . $title) . '"><span class="bi bi-folder2-open" aria-hidden="true"></span><span class="pg-btn-label">' . lang('Browse…') . '</span></button>';
}
