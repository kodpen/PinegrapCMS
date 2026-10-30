<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Catalog Manager's listing, tree, new group, rename, membership, pages,
 * paste, recycle, restore, purge and publish sub-actions: product groups and
 * products shown through the file manager. Each handler answers through respond(), which ends the request.
 * Called by pg_explorer_handle() and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How long each binned row has left, keyed the way the grid keys its rows --
// the screen calls them group and product, recycle_bin calls them
// product_group and product. Whole days from the retention period, never
// below zero.
function pg_explorer_catalog_bin_entries($rows, $retention, $now)
{
    $bin_entries = array();

    foreach ((array) $rows as $row) {

        $age_days = (int) floor(($now - (int) $row['deleted_at']) / 86400);
        $kind = ($row['item_type'] == 'product_group') ? 'group' : 'product';

        $bin_entries[$kind . ':' . (int) $row['item_id']] = array(
            'deleted_at' => (int) $row['deleted_at'],
            'days_left' => max(0, $retention - $age_days),
            'origin_name' => '');
    }

    return $bin_entries;
}

// Whether a group's rename reaches its short description. The short
// description is what the site prints, so a rename has to reach it --
// otherwise the group the operator just renamed keeps its old name everywhere
// a visitor looks.
//
// Only while the two still agree, though: a short description that was
// written by hand says something the name does not, and a rename here must
// not overwrite it. Creation sets them the same, so the create-then-rename
// that this screen does lands correctly, and an edited one is left alone.
function pg_explorer_catalog_short_description_follows_name($group_row)
{
    return (is_array($group_row))
        && ((trim((string) $group_row['short_description']) == '')
            || (trim((string) $group_row['short_description']) == trim((string) $group_row['name'])));
}

// ── Catalog listing: one group's subgroups and its own products ──
//
// Three shapes come out of here.  A group id lists what is inside that
// group; view_mode 'all_products' lists every product regardless of
// group, which is the store's answer to the Files and Pictures views;
// view_mode 'variant_sets' lists the groups that hold variants, the
// same set view_products.php?mode=variant_sets prints.
function pg_explorer_handle_catalog_list($type, $request, $user)
{
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

    // How long each binned row has left.
    $bin_entries = array();

    if (($view_mode == 'recycled') && (pg_catalog_recycle_ready())) {

        $rows = db_items("SELECT item_type, item_id, deleted_at FROM recycle_bin WHERE item_type IN ('product_group', 'product')");

        $bin_entries = pg_explorer_catalog_bin_entries($rows, pg_recycle_retention_days(), time());
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
}

// ── Catalog tree (lazy children, same contract as explorer_tree) ──
function pg_explorer_handle_catalog_tree($type, $request, $user)
{
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
}

// ── Create a product group where you are standing ────────────────
//
// The same shape as creating a folder: a row appears with a working
// name, the screen puts the cursor in it, and the full edit screen is
// one click away for everything else the group can carry.
function pg_explorer_handle_catalog_create_group($type, $request, $user)
{
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

    // name and short_description are varchar(100).
    if (pg_name_too_long($name)) {
        respond(array('status' => 'error', 'message' => lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(pg_name_max_length())))));
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

    log_activity(lang(array('string' => 'The product group, {var:1}, was added.', 'vars' => $name)), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'group' => array('id' => (int) $group_id, 'name' => $name),
        'message' => lang(array('string' => 'The product group, {var:1}, was added.', 'vars' => $name))));
}

// ── Rename a group or a product ──────────────────────────────────
//
// The name only. address_name is the row's address on the site, and a
// rename that silently moved a live URL would break links the operator
// never touched; the edit screen is where an address is changed on
// purpose.
function pg_explorer_handle_catalog_rename($type, $request, $user)
{
    if (pg_catalog_access($user) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $item_kind = isset($request['item_kind']) ? (string) $request['item_kind'] : '';
    $item_id = (int) (isset($request['item_id']) ? $request['item_id'] : 0);
    $name = trim(isset($request['name']) ? $request['name'] : '');

    if (($item_id <= 0) || ($name == '')) {
        respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
    }

    // products.name, product_groups.name and short_description are
    // varchar(100).
    if (pg_name_too_long($name)) {
        respond(array('status' => 'error', 'message' => lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(pg_name_max_length())))));
    }

    if ($item_kind == 'group') {

        // The short description follows the name while the two agree.
        $group_row = db_item("SELECT name, short_description FROM product_groups WHERE id = '" . e($item_id) . "'");

        $sql_short_description = '';

        if (pg_explorer_catalog_short_description_follows_name($group_row)) {

            $sql_short_description = "short_description = '" . e($name) . "',";
        }

        db("UPDATE product_groups SET name = '" . e($name) . "', " . $sql_short_description . " timestamp = UNIX_TIMESTAMP() WHERE id = '" . e($item_id) . "'");

    } elseif ($item_kind == 'product') {

        $audit_before = pg_audit_fetch('products', $item_id);
        db("UPDATE products SET name = '" . e($name) . "', timestamp = UNIX_TIMESTAMP() WHERE id = '" . e($item_id) . "'");
        pg_audit_record('update', 'products', $item_id, $audit_before);

    } else {

        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'name' => $name,
        'message' => lang('The name was changed.')));
}

// ── Take a product out of one group ──────────────────────────────
//
// Not a delete. A product belongs to several groups at once, so leaving
// this one changes nothing else about it: it stays on sale, and it stays
// wherever else it is listed. The screen says so in those words, because
// "delete" in a folder means something the operator would not want here.
function pg_explorer_handle_catalog_membership_remove($type, $request, $user)
{
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

    log_activity(lang(array('string' => 'The product, {var:1}, was removed from a product group.', 'vars' => isset($product['name']) ? $product['name'] : '')), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'remaining' => $remaining,
        'message' => ($remaining > 0)
            ? lang(array('string' => 'Removed from this group. It is still in {var:1} other group(s).', 'vars' => $remaining))
            : lang('Removed from this group. It is now in no group, and only the All Products view will show it.')));
}

// ── Where this group or product is visible on the site ───────────
//
// Asked for only when the preview panel is open, because both routes
// scan text columns and neither belongs in a listing that redraws on
// every click.  A single "view on site" button would have to pick one
// of these for the operator; the panel lists them and lets them pick.
function pg_explorer_handle_catalog_pages($type, $request, $user)
{
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
}

// The recycle_bin item type of a grid kind: the screen calls them group and
// product, recycle_bin calls them product_group and product. Any other kind
// has no type.
function pg_explorer_catalog_bin_item_type($kind)
{
    if ($kind == 'group') {
        return 'product_group';
    }

    return ($kind == 'product') ? 'product' : '';
}

// The recycle answer with the refusals added, each reason once: after the
// count when something was binned, instead of it when nothing was.
function pg_explorer_catalog_recycle_message($message, $binned, $errors)
{
    if (count($errors) > 0) {
        $message = ($binned > 0)
            ? ($message . ' ' . implode(' ', array_unique($errors)))
            : implode(' ', array_unique($errors));
    }

    return $message;
}

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
function pg_explorer_handle_catalog_paste($type, $request, $user)
{
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
}

// ── Move a group or a product to the recycle bin ─────────────────
//
// Binning sets the flag AND switches the row off, keeping what it was
// before. The flag hides it from these screens; 'enabled' is what the
// storefront already reads, and relying on it means a binned product
// cannot keep selling because one query somewhere forgot the new
// column. Restoring puts the old value back rather than publishing
// something that was switched off before it was deleted.
function pg_explorer_handle_catalog_recycle($type, $request, $user)
{
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
            //
            // One restore record, for the group that was actually
            // deleted. The bin lists it alone and restoring it brings
            // the whole subtree back with it. Replaced rather than added
            // to, so a group that arrives here twice in one batch does
            // not leave two records behind. The same statements every
            // other delete of a group writes (pg_catalog_bin_statements()).
            foreach (pg_catalog_bin_statements('product_group', $item_id, $subtree, (int) $map[$item_id]['parent_id'], $user['id']) as $statement) {
                db($statement);
            }

            log_activity(lang(array('string' => 'The product group, {var:1}, was moved to the Recycle Bin.', 'vars' => $map[$item_id]['name'])), $_SESSION['sessionusername']);

            $binned++;

        } elseif ($item_kind == 'product') {

            // The same move every screen that deletes a product makes,
            // product.deleted with recycled: true included. False for a
            // product that is gone or already in the bin.
            $audit_before = pg_audit_fetch('products', $item_id);

            $product_name = pg_catalog_bin_product($item_id, $user['id']);

            if ($product_name === false) {
                continue;
            }

            pg_audit_record('recycle', 'products', $item_id, $audit_before);

            log_activity(lang(array('string' => 'The product, {var:1}, was moved to the Recycle Bin.', 'vars' => $product_name)), $_SESSION['sessionusername']);

            $binned++;
        }
    }

    $message = ($binned > 0)
        ? lang(array('string' => '{var:1} item(s) were moved to the Recycle Bin.', 'vars' => $binned))
        : lang('Sorry, we could not accept your request.');

    $message = pg_explorer_catalog_recycle_message($message, $binned, $errors);

    respond(array(
        'status' => ($binned > 0) ? 'success' : 'error',
        'request' => $type,
        'count' => $binned,
        'skipped' => count($errors),
        'message' => $message));
}

// ── Put one back ────────────────────────────────────────────────
function pg_explorer_handle_catalog_restore($type, $request, $user)
{
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

            // A parent deleted outright while the group sat in the bin
            // would leave it under a group that no longer exists, out
            // of the catalog tree; it comes back under the root group.
            $restore_parent_id = (int) db_value("SELECT parent_id FROM product_groups WHERE id = '" . e($item_id) . "'");

            if (($restore_parent_id > 0) && ((int) db_value("SELECT COUNT(*) FROM product_groups WHERE id = '" . e($restore_parent_id) . "'") == 0)) {

                $restore_root_id = (int) db_value("SELECT id FROM product_groups WHERE parent_id = '0' ORDER BY id LIMIT 1");

                if (($restore_root_id > 0) && ($restore_root_id != $item_id)) {
                    db("UPDATE product_groups SET parent_id = '" . e($restore_root_id) . "' WHERE id = '" . e($item_id) . "'");
                }
            }

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

            // A variant set deleted from the product list took its
            // variants with it, each recorded with the set as the place
            // it came from (pg_pb_bin_variant_set()). They come back
            // with the set, as they went. A product binned on its own
            // is recorded with 0 and is not touched here.
            $variant_ids = array();

            foreach ((array) db_items("SELECT item_id FROM recycle_bin WHERE (item_type = 'product') AND (original_parent_id IN (" . $sql_ids . "))") as $row) {
                $variant_ids[] = (int) $row['item_id'];
            }

            if (count($variant_ids) > 0) {

                $sql_variant_ids = implode(',', $variant_ids);

                $audit_before = pg_audit_fetch_many('products', $variant_ids);

                db(
                    "UPDATE products
                    SET recycled = '0',
                        enabled = recycled_enabled
                    WHERE (id IN (" . $sql_variant_ids . ")) AND (recycled = '1')");

                pg_audit_record_many('restore', 'products', $audit_before);

                db("DELETE FROM recycle_bin WHERE (item_type = 'product') AND (item_id IN (" . $sql_variant_ids . "))");
            }

            $restored++;

        } elseif ($item_kind == 'product') {

            $audit_before = pg_audit_fetch('products', $item_id);
            db(
                "UPDATE products
                SET recycled = '0',
                    enabled = recycled_enabled
                WHERE id = '" . e($item_id) . "'");
            pg_audit_record('restore', 'products', $item_id, $audit_before);

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
}

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
function pg_explorer_handle_catalog_purge($type, $request, $user)
{
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
        $item_type = pg_explorer_catalog_bin_item_type($item_kind);

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
}

// ── Publish or unpublish groups and products ─────────────────────
//
// 'enabled' is the gate the whole storefront already reads, so this is
// the same switch the edit screens carry -- reachable from the grid and
// the tree without opening a screen to flip one field.
//
// A row in the bin is left alone: binning switched it off and kept what
// it was, and turning it back on here would put a deleted product on
// sale while it still sits in the bin.
function pg_explorer_handle_catalog_enable($type, $request, $user)
{
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

            log_activity(lang(array('string' => 'product group ({var:1}) was updated', 'vars' => array($map[$item_id]['name']))), $_SESSION['sessionusername']);

            $changed++;

        } elseif ($item_kind == 'product') {

            $product = db_item("SELECT id, name FROM products WHERE (id = '" . e($item_id) . "')" . pg_catalog_live_filter('products'));

            if (is_array($product) == false) {
                continue;
            }

            $audit_before = pg_audit_fetch('products', $item_id);
            db(
                "UPDATE products
                SET enabled = '" . e($enabled) . "',
                    timestamp = UNIX_TIMESTAMP(),
                    user = '" . e($user['id']) . "'
                WHERE id = '" . e($item_id) . "'");
            pg_audit_record('update', 'products', $item_id, $audit_before);

            log_activity(lang(array('string' => 'product ({var:1}) was updated', 'vars' => array($product['name']))), $_SESSION['sessionusername']);

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
}
