<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Catalog: product groups and products through the explorer. The access and
 * bin gates, the group tree and its subtrees, group statistics, breadcrumbs,
 * the tile payloads of groups and products, group memberships and the SEO
 * score ring. Loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

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
        && pg_schema_has('product_groups', 'recycled')
        && pg_schema_has('products', 'recycled');

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
    require_once(PG_FUNCTIONS_DIR . '/seo.php');

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
