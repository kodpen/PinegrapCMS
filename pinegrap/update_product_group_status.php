<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

function update_product_group_status($properties) {

    $id = $properties['id'];
    $status = $properties['status'];

    // Who the change is recorded as. The screens leave this out and the
    // signed-in operator is used; an entry point without a session - the
    // external API - passes the account it is acting for, because USER_ID is
    // empty there and every row below would be stamped with nobody.
    $user_id = isset($properties['user']) ? (int) $properties['user'] : (int) USER_ID;

    if ($status == 'enabled') {
        $enabled = 1;
    } else {
        $enabled = 0;
    }

    // Get all the product groups that need to be updated, which includes this
    // product group and all child product groups.
    $product_groups = get_product_groups(array('id' => $id));

    $items = array();

    $sql_product_groups = "";
    $sql_not_in_product_groups = "";

    // The groups' enabled as it is now. The SEO analysis reads a group's
    // enabled as "searchable", so a group whose flag really changes needs a
    // fresh score; one already in the asked state keeps its own.
    $group_ids = array();

    foreach ($product_groups as $product_group) {
        $group_ids[] = "'" . e($product_group['id']) . "'";
    }

    $group_enabled = array();

    if ($group_ids) {
        foreach ((array) db_items(
            "SELECT id, enabled FROM product_groups
            WHERE id IN (" . implode(',', $group_ids) . ")") as $row) {
            $group_enabled[(int) $row['id']] = (int) $row['enabled'];
        }
    }

    // Loop through the product groups in order to update the status for each.
    foreach ($product_groups as $product_group) {

        $sql_seo_stale = "";

        if (
            !isset($group_enabled[(int) $product_group['id']])
            or ($group_enabled[(int) $product_group['id']] !== $enabled)
        ) {
            $sql_seo_stale = "seo_analysis_current = '0',";
        }

        // Update product group status.
        db(
            "UPDATE product_groups
            SET
                enabled = '$enabled',
                $sql_seo_stale
                user = '" . $user_id . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($product_group['id']) . "'");

        $items[] = array(
            'id' => $product_group['id'],
            'type' => 'product_group',
            'status' => $status);

        // Add this product group to the SQL that we will use below to get
        // products in the scope of this operation.

        if ($sql_product_groups != '') {
            $sql_product_groups .= " OR ";
        }

        $sql_product_groups .= "(product_group = '" . e($product_group['id']) . "')";

        // Add this product group to the SQL that we will use further below
        // to check if a product is enabled in a different product group scope.
        $sql_not_in_product_groups .= " AND (products_groups_xref.product_group != '" . e($product_group['id']) . "')";

    }

    // Get all the products in this group and child groups, so we can update them.
    $products = db_items(
        "SELECT DISTINCT(product) AS id
        FROM products_groups_xref
        WHERE $sql_product_groups");

    // The rows as they were, so the products the loop below changes each get
    // an audit line.
    $audit_before = pg_audit_fetch_many('products', array_column((array) $products, 'id'));
    $audit_changed = array();

    // The products' enabled and keywords as they are now, read here rather
    // than taken from the audit snapshot, which is empty while auditing is
    // off. Only a product whose enabled really changes gets the follow-ups.
    $product_ids = array();

    foreach ((array) $products as $product) {
        $product_ids[] = "'" . e($product['id']) . "'";
    }

    $product_rows = array();

    if ($product_ids) {
        foreach ((array) db_items(
            "SELECT id, enabled, keywords FROM products
            WHERE id IN (" . implode(',', $product_ids) . ")") as $row) {
            $product_rows[(int) $row['id']] = $row;
        }
    }

    // The tag cloud sync lives with the product screen's writer, a function
    // library with an include guard that draws nothing.
    if ($product_rows and !function_exists('pg_pb_sync_tag_cloud_keywords')) {
        require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    }

    // Loop through the products in order to update the status for each.
    foreach ((array) $products as $product) {

        // If we are disabling products, and this product is enabled
        // in an enabled product group in a different area of the catalog,
        // then skip this product, because we don't want to disable a product
        // that is enabled in a different area of the catalog.
        if (
            $status == 'disabled'
            and db_value(
                "SELECT products_groups_xref.product
                FROM products_groups_xref
                LEFT JOIN products ON products_groups_xref.product = products.id
                LEFT JOIN product_groups ON products_groups_xref.product_group = product_groups.id
                WHERE
                    (products_groups_xref.product = '" . e($product['id']) . "')
                    AND (products.enabled = '1')
                    AND (product_groups.enabled = '1')
                    $sql_not_in_product_groups
                LIMIT 1")

        ) {
            continue;
        }

        // Only a product whose enabled really changes gets the follow-ups
        // below; one already in the asked state is written as before.
        $current = isset($product_rows[(int) $product['id']]) ? $product_rows[(int) $product['id']] : null;
        $changed = ($current !== null) && ((int) $current['enabled'] !== $enabled);

        // The SEO analysis reads enabled as "searchable", so a changed
        // product's stored score is marked stale.
        $sql_seo_stale = $changed ? "seo_analysis_current = '0'," : "";

        // Update product status.
        db(
            "UPDATE products
            SET
                enabled = '$enabled',
                $sql_seo_stale
                user = '" . $user_id . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($product['id']) . "'");

        if ($changed) {

            // A disabled product's keywords leave the tag cloud; an enabled
            // one's come back where it is in a cloud.
            pg_pb_sync_tag_cloud_keywords((int) $product['id'], $current['keywords'], ($enabled === 1));

            // A connector sends quantity 0 for a disabled product, but only
            // for one the queue was told about.
            if (function_exists('pg_marketplace_product_changed')) {
                pg_marketplace_product_changed((int) $product['id']);
            }

        }

        $audit_changed[(int) $product['id']] = true;

        $items[] = array(
            'id' => $product['id'],
            'type' => 'product',
            'status' => $status);

    }

    pg_audit_record_many('update', 'products', array_intersect_key($audit_before, $audit_changed));

    $product_group = db_item(
        "SELECT name
        FROM product_groups
        WHERE id = '" . e($id) . "'");

    if ($status == 'enabled') {
        log_activity(lang(array('string' => 'product group ({var:1}) and all items in it were enabled', 'vars' => array($product_group['name']))));
    } else {
        log_activity(lang(array('string' => 'product group ({var:1}) and all appropriate items in it were disabled', 'vars' => array($product_group['name']))));
    }

    return $items;

}