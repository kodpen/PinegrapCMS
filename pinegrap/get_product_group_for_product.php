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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

function get_product_group_for_product($request) {

    $product_group = $request['product_group'];
    $product = $request['product'];

    // If a product was not passed, then we don't know which product to look for.
    if (!$product) {
        return false;
    }

    // If a product group was not passed, then use root product group.
    if (!$product_group) {
        $product_group = db_item("SELECT id, display_type FROM product_groups WHERE parent_id = '0'");
    }

    // If the display type for the product group was not passed, then get it.
    if (!$product_group['display_type']) {
        $product_group = db_item(
            "SELECT id, display_type FROM product_groups WHERE id = '" . e($product_group['id']) . "'");
    }

    // If product is directly in this group, then return this group.
    if (db(
        "SELECT product_group FROM products_groups_xref
        WHERE
            product = '" . e($product['id']) . "'
            AND product_group = '" . e($product_group['id']) . "'")
    ) {
        return $product_group;
    }

    // If this group is a browse group, then look in child product groups.
    if ($product_group['display_type'] == 'browse') {

        $child_product_groups = db_items(
            "SELECT id, display_type FROM product_groups
            WHERE parent_id = '" . e($product_group['id']) . "' AND enabled = '1'
            ORDER BY sort_order, name");

        foreach ($child_product_groups as $child_product_group) {

            $response = get_product_group_for_product(array(
                'product_group' => $child_product_group,
                'product' => $product));

            // If the product was found in this child group, then return it.
            if ($response) {
                return $response;
            }
        }
    }

    // If we got here then we did not find the product.
    return false;
}