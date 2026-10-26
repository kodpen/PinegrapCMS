<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Short-link helpers of the file manager: the names, the recycle bin step,
 * the listing rows and the request reader shared by the explorer, the classic
 * short-link screens and the external API. Loaded by
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

// The name is an address on the live site, typed or pasted the way an old
// address is (pg_short_link_name_from_address(): the path of a full address,
// no slash at either end), and may hold what the shared rule allows
// (pg_short_link_name_problem(), includes/fn/short_link_redirects.php): the
// classic screens and the external API ask the same functions.
//
// Square brackets are in the set, for the same reason pg_ascii_file_name()
// keeps them: they are this software's own suffix for a name that is taken.
// pg_short_link_free_name() hands out "new_short_link[4]", so a rule that
// rejected them refused the very name the create had just given the row --
// the operator was told the format was illegal by the screen that wrote it.
//
// A name posted as a list (name[]=x) counts as not given: it is no name. The
// rename and the change ask pg_short_link_name_read() instead, so their error
// says what is wrong rather than repeating the whole rule.
function pg_short_link_clean_name($name)
{
    $read = pg_short_link_name_read($name);

    return ($read['problem'] === '') ? $read['name'] : '';
}

// A free name, Windows style: the wanted one, then the wanted one with [1],
// [2] and so on.  The namespace being checked is pages, files and short links
// together, so a short link can never shadow a page. A numbered name keeps to
// the column's length (pg_short_link_numbered_name()): the end of a long name
// gives way to the suffix.
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

        $candidate = pg_short_link_numbered_name($wanted, $index);

        if (($candidate === '') || (pg_short_link_name_problem($candidate) !== '')) {
            continue;
        }

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
        && pg_schema_has('short_links', 'recycled');

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

// Move one short link to the Recycle Bin: the row stays, flagged, and one
// restore record remembers it. The File Manager's delete and the classic edit
// screen's both come here; the caller has asked pg_short_link_recycle_ready()
// first and writes its own activity line.
function pg_short_link_to_bin($short_link_id, $user)
{
    $short_link_id = (int) $short_link_id;

    db("UPDATE short_links SET recycled = '1' WHERE id = '" . e($short_link_id) . "'");

    // Replaced rather than added to, so a link that reaches here twice does
    // not leave two restore records behind.
    db("DELETE FROM recycle_bin WHERE (item_type = 'short_link') AND (item_id = '" . e($short_link_id) . "')");

    db(
        "INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
        VALUES ('short_link', '" . e($short_link_id) . "', '0', UNIX_TIMESTAMP(), '" . e($user['id']) . "')");
}

// $where is the caller's own condition; the recycled state is added to it here
// so no listing can forget it.
function pg_short_link_rows($where = '', $recycled = false)
{
    // A guest's way into the workspace is made and ended there, never here.
    $where = ($where == '') ? "WHERE short_links.destination_type <> 'workspace_guest'" : ($where . " AND short_links.destination_type <> 'workspace_guest'");

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
            " . (pg_short_link_modes_ready() ? "short_links.link_mode, short_links.token_hint, short_links.expires_at, short_links.used_at, short_links.use_count, short_links.last_used_at," : "") . "
            " . (pg_short_link_redirects_ready() ? "short_links.redirect_code, short_links.is_pattern," : "") . "
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

// An id posted as a list (item_id[]=3) is no id: cast, it read as 1 and the
// rename, the change, the copy and the delete all landed on link 1.
function pg_short_link_by_id($user, $id, $recycled = false)
{
    $id = is_scalar($id) ? (int) $id : 0;

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

    // A one-time link has a token for its address, shown once when it was
    // made and kept nowhere: the list knows it by the first characters.
    $token = ((string) $row['name'] === '') && ((string) ($row['token_hint'] ?? '') !== '');
    $state = pg_short_link_state($row);

    // A name with a * stands for many addresses and has none to open; a link
    // that redirects says so beside where it leads. The label is the answer
    // visitors get; redirect_code is the one stored, which the window reopens
    // with (a timed link saved with 301 answers 302).
    $pattern = !empty($row['is_pattern']);
    $answer = pg_short_link_answer_label(pg_short_link_status($row));

    return array(
        'kind' => 'file',
        'id' => (int) $row['id'],
        'short_link' => true,
        'token' => $token,
        'link_mode' => (string) ($row['link_mode'] ?? 'permanent'),
        'link_state' => $state['status'],
        'link_note' => $state['label'],
        'use_count' => (int) ($row['use_count'] ?? 0),
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
        'redirect_code' => (int) ($row['redirect_code'] ?? 0),
        'is_pattern' => $pattern,
        'answer' => $answer,
        'name' => $token ? ((string) $row['token_hint'] . '…') : (string) $row['name'],
        'folder_id' => 0,
        'folder_name' => '',
        'type' => 'short_link',
        'is_image' => false,
        'size' => 0,
        'size_label' => $destination . (($answer !== '') ? ' · ' . $answer : '') . (($state['label'] !== '') ? ' · ' . $state['label'] : ''),
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
        'url' => ($token || $pattern) ? '' : OUTPUT_PATH . encode_url_path((string) $row['name']),
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
//
// $current is the stored link when one is being changed. The product, the
// group, the page or the file it already points at may stay while it is in the
// Recycle Bin, the way the classic edit screen keeps it selected; anything else
// in the bin is refused, since a list drawn before it was deleted can still
// post it.
function pg_short_link_read_request($request, $user, $current = null)
{
    // A value posted as a list (product_id[]=x) counts as not given: cast, it
    // logged "Array to string conversion" and the link was saved as "Array".
    $value = function ($key) use ($request) {
        return (isset($request[$key]) && is_scalar($request[$key])) ? trim((string) $request[$key]) : '';
    };

    $type = (isset($request['destination_type']) && is_scalar($request['destination_type'])) ? (string) $request['destination_type'] : '';

    if (in_array($type, pg_short_link_types(), true) == false) {
        return array('error' => 'destination_type', 'message' => lang('The destination type is not valid.'));
    }

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

            if (($group_id !== (int) ($current['product_group_id'] ?? 0)) && pg_catalog_group_binned($group_id)) {
                return array('error' => 'product_group_id', 'message' => lang('The product group is in the Recycle Bin. Restore it in the Catalog Manager or choose another product group.'));
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

            if (($product_id !== (int) ($current['product_id'] ?? 0)) && pg_catalog_product_binned($product_id)) {
                return array('error' => 'product_id', 'message' => lang('The product is in the Recycle Bin. Restore it in the Catalog Manager or choose another product.'));
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

            // The column holds this many characters; a longer address would
            // be cut short in silence. The classic screens refuse it too.
            if (mb_strlen($url, 'UTF-8') > pg_short_link_url_max()) {
                return array('error' => 'url', 'message' => lang(array('string' => 'The address can be at most {var:1} characters long.', 'vars' => array(pg_short_link_url_max()))));
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

            if (($file_id !== (int) ($current['file_id'] ?? 0)) && pg_recycle_file_binned($file_id)) {
                return array('error' => 'file_id', 'message' => lang('The file is in the Recycle Bin. Restore it in the File Manager or choose another file.'));
            }

            $columns['file_id'] = $file_id;

            break;
    }

    // The page has to exist.
    if (($page_id > 0) && (db_value("SELECT page_folder FROM page WHERE page_id = '" . e($page_id) . "'") === null)) {
        return array('error' => $page_field, 'message' => lang('The page does not exist.'));
    }

    if (($page_id > 0) && ($page_id !== (int) ($current['page_id'] ?? 0)) && pg_recycle_page_binned($page_id)) {
        return array('error' => $page_field, 'message' => lang('The page is in the Recycle Bin. Restore it in the File Manager or choose another page.'));
    }

    return array(
        'type' => $type,
        'page_id' => $page_id,
        'tracking_code' => $value('tracking_code'),
        'columns' => $columns);
}
