<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - minimum stock levels.
 *
 * A product that tracks stock can be given a minimum (erp_stock_minimums,
 * 4.99): the lowest count it should have before it is bought again. A product
 * at or below its minimum is "low"; the stock screen and the ERP dashboard
 * list them, and the daily ERP notice can announce them.
 *
 * The count is the catalogue's own (products.inventory_quantity), the one the
 * store sells from; the ERP reads it and never writes it here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

if (!defined('ERP_STOCK_MINIMUM_MAX')) {
    define('ERP_STOCK_MINIMUM_MAX', 1000000);
}

/**
 * Whether the 4.99 table is there.
 *
 * @return bool
 */
function erp_stock_minimums_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_stock_minimums', 'min_quantity');
    }

    return $ready;
}

/**
 * The products that track stock, with their minimum.
 *
 * @param array $filters  search (string), show ('all' | 'set' | 'low'), limit
 * @return array
 */
function erp_stock_minimum_rows($filters = array())
{
    if (!erp_stock_minimums_ready()) {
        return array();
    }

    $where = array("p.inventory = '1'");
    $search = trim((string) ($filters['search'] ?? ''));

    if ($search !== '') {
        $like = "'%" . escape(addcslashes($search, '%_\\')) . "%'";
        $where[] = "(p.name LIKE " . $like . " OR p.short_description LIKE " . $like . ")";
    }

    $show = (string) ($filters['show'] ?? 'all');

    if ($show === 'set') {
        $where[] = "m.min_quantity > 0";
    } elseif ($show === 'low') {
        $where[] = "m.min_quantity > 0 AND p.inventory_quantity <= m.min_quantity";
    }

    $limit = max(1, min(2000, (int) ($filters['limit'] ?? 500)));

    return (array) db_items("SELECT p.id AS product_id, p.name AS product_sku, p.short_description AS product_name,
            p.inventory_quantity, p.enabled, COALESCE(m.min_quantity, 0) AS min_quantity
        FROM products p
        LEFT JOIN erp_stock_minimums m ON m.product_id = p.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(NULLIF(p.short_description, ''), p.name) ASC, p.id ASC
        LIMIT " . $limit);
}

/**
 * Save minimums as typed: a whole number sets it, empty or 0 clears it.
 * Only products that track stock take one.
 *
 * @param array $values   product_id => string
 * @param int   $user_id
 * @return array ['success' => bool, 'error' => string, 'changed' => int]
 */
function erp_stock_minimums_save($values, $user_id = 0)
{
    if (!erp_stock_minimums_ready()) {
        return array('success' => false, 'error' => lang('Minimum stock comes with the software update; run the update to use it.'), 'changed' => 0);
    }

    $ids = array();
    foreach ((array) $values as $product_id => $value) {
        if ((int) $product_id > 0) {
            $ids[] = (int) $product_id;
        }
    }

    if (empty($ids)) {
        return array('success' => true, 'error' => '', 'changed' => 0);
    }

    $tracked = array();
    foreach ((array) db_items("SELECT p.id, COALESCE(m.min_quantity, 0) AS min_quantity
        FROM products p LEFT JOIN erp_stock_minimums m ON m.product_id = p.id
        WHERE p.inventory = '1' AND p.id IN (" . implode(',', $ids) . ")") as $row) {
        $tracked[(int) $row['id']] = (int) $row['min_quantity'];
    }

    $changed = 0;

    foreach ((array) $values as $product_id => $value) {
        $product_id = (int) $product_id;

        if (!isset($tracked[$product_id])) {
            continue;
        }

        $value = trim((string) $value);

        if (($value !== '') && (preg_match('/^[0-9]{1,7}$/', $value) !== 1)) {
            return array('success' => false, 'error' => lang('A minimum is a whole number of pieces, or empty for none.'), 'changed' => $changed);
        }

        $minimum = min(ERP_STOCK_MINIMUM_MAX, (int) $value);

        if ($minimum === $tracked[$product_id]) {
            continue;
        }

        if ($minimum <= 0) {
            db("DELETE FROM erp_stock_minimums WHERE product_id = '" . $product_id . "'");
        } else {
            db("INSERT INTO erp_stock_minimums (product_id, min_quantity, updated_by, updated_at)
                VALUES ('" . $product_id . "', '" . $minimum . "', '" . (int) $user_id . "', '" . time() . "')
                ON DUPLICATE KEY UPDATE min_quantity = VALUES(min_quantity), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)");
        }

        $changed++;
    }

    return array('success' => true, 'error' => '', 'changed' => $changed);
}

/**
 * The products at or below their minimum, the furthest below first. Only
 * products on sale; a switched-off product is not bought again.
 *
 * @param int $limit
 * @return array
 */
function erp_stock_low_products($limit = 50)
{
    if (!erp_stock_minimums_ready()) {
        return array();
    }

    return (array) db_items("SELECT p.id AS product_id, p.name AS product_sku, p.short_description AS product_name,
            p.inventory_quantity, m.min_quantity, (m.min_quantity - p.inventory_quantity) AS short_by
        FROM erp_stock_minimums m
        INNER JOIN products p ON p.id = m.product_id
        WHERE m.min_quantity > 0 AND p.inventory = '1' AND p.enabled = '1' AND p.inventory_quantity <= m.min_quantity
        ORDER BY (m.min_quantity - p.inventory_quantity) DESC, p.id ASC
        LIMIT " . max(1, (int) $limit));
}

/**
 * How many products on sale are at or below their minimum.
 *
 * @return int
 */
function erp_stock_low_count()
{
    if (!erp_stock_minimums_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*)
        FROM erp_stock_minimums m
        INNER JOIN products p ON p.id = m.product_id
        WHERE m.min_quantity > 0 AND p.inventory = '1' AND p.enabled = '1' AND p.inventory_quantity <= m.min_quantity");
}

/**
 * The name a product is shown under.
 *
 * @param array $row  product_name, product_sku
 * @return string
 */
function erp_stock_product_label($row)
{
    return (trim((string) ($row['product_name'] ?? '')) !== '') ? (string) $row['product_name'] : (string) ($row['product_sku'] ?? '');
}
