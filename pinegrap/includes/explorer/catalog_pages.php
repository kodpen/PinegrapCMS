<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Catalog pages and copy: where a product group or a product is shown on the
 * site (catalog pages and catalog widgets), the search pages of groups and
 * their tag clouds, and the recursive copy of a group. Loaded by
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
