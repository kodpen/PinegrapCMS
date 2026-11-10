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
 *   archived  pages: pages in an archived folder listed too
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
        'page' => array('source' => 'pages', 'types' => array(), 'public' => false, 'design' => false, 'archived' => false, 'value' => 'id', 'role' => 3, 'tree' => true, 'pictures' => false, 'upload' => false),
    );

    return $kinds;
}

/** The picture types the image kind lists: the editor's image browser's. */
function pg_picker_image_types()
{
    return array('gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'svg', 'webp', 'avif');
}

/** The requests the endpoint answers. */
function pg_picker_requests()
{
    return array('list', 'search', 'tree', 'resolve');
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
 *                    page_name, page_type, page_folder
 * @return array id, value (what a pick stores), text, type, folder
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
        );
    }

    return array(
        'id' => (int) ($row['id'] ?? 0),
        'value' => ($kind['value'] === 'id') ? (int) ($row['id'] ?? 0) : (string) ($row['name'] ?? ''),
        'text' => (string) ($row['name'] ?? ''),
        'type' => (string) ($row['type'] ?? ''),
        'folder' => (int) ($row['folder'] ?? 0),
    );
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
        return "SELECT page.page_id, page.page_name, page.page_type, page.page_folder
            FROM page
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            WHERE " . $where;
    }

    return "SELECT id, name, type, folder FROM files WHERE " . $where;
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

    return pg_picker_matches(is_array($rows) ? $rows : array(), $query, ($kind['source'] === 'pages') ? 'page_name' : 'name');
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
        'items' => array_map(function ($row) use ($kind) {
            return pg_picker_item($kind, $row);
        }, $slice['items']),
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
 * @return array folder, items, page, per_page, more
 */
function pg_picker_list_answer($request, $user)
{
    $kinds = pg_picker_kinds();
    $kind = $kinds[$request['kind']];
    $answer = array('folder' => $request['folder'], 'items' => array(), 'page' => $request['page'], 'per_page' => $request['per_page'], 'more' => false);

    if (!pg_picker_folder_allowed($request['folder'], pg_recycle_bin_folder_ids(), pg_picker_granted_folders($user))) {
        return $answer;
    }

    $column = ($kind['source'] === 'pages') ? 'page.page_name' : 'name';

    $rows = db_items(
        pg_picker_select($kind, pg_picker_where_for($kind, $user, $request['folder']))
        . " ORDER BY " . $column . "
        LIMIT " . pg_picker_offset($request['page'], $request['per_page']) . ", " . ($request['per_page'] + 1));

    $slice = pg_picker_page_slice(is_array($rows) ? $rows : array(), $request['per_page']);

    $answer['items'] = array_map(function ($row) use ($kind) {
        return pg_picker_item($kind, $row);
    }, $slice['items']);
    $answer['more'] = $slice['more'];

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
                    $rows[] = pg_explorer_side_folder_row($row, get_access_control_type($id), isset($under[$id]));
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
            "SELECT page.page_id, page.page_name, page.page_type, page.page_folder, folder.folder_archived
            FROM page
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            WHERE page.page_id IN (" . implode(', ', pg_picker_ids($request['values'])) . ")");

        foreach ((is_array($rows) ? $rows : array()) as $row) {
            $found[(int) $row['page_id']] = $row;
        }
    } else {
        $column = ($kind['value'] === 'id') ? 'id' : 'name';
        $list = ($kind['value'] === 'id')
            ? implode(', ', pg_picker_ids($request['values']))
            : "'" . implode("', '", array_map('e', $request['values'])) . "'";

        $rows = db_items("SELECT id, name, type, folder FROM files WHERE (" . $column . " IN (" . $list . ")) AND (attachment = 0) AND (name != '')");
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

    return array('items' => $items);
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
            $answer += $kinds[$request['kind']]['tree'] ? pg_picker_tree_answer($request, $user) : array('nodes' => array());
            break;

        case 'resolve':
            $answer += pg_picker_resolve_answer($request, $user);
            break;
    }

    return $answer;
}
