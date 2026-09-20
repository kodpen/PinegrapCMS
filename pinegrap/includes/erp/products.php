<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the catalogue as the document editors see it.
 *
 * A product on an invoice or a delivery note line is picked from the store's
 * own catalogue: found by its name (short_description - the name column is
 * the SKU), its SKU or its barcode, and it brings its price, its VAT rate,
 * what is in stock, and the automatic campaign the store is running on it.
 * Nothing here writes; the editors read and the operator decides.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * The VAT rate a product without one of its own is sold at from the store:
 * the tax zone of the store's own country, the way the checkout would tax a
 * buyer standing at the counter. Read once per request.
 *
 * @return float
 */
function erp_default_tax_rate()
{
    static $rate = null;

    if ($rate === null) {
        $rate = 0.0;

        if (function_exists('get_tax_rate_for_address')) {
            $country = erp_default_country_code();
            $rate = ($country !== '') ? (float) get_tax_rate_for_address($country, '') : 0.0;
        }
    }

    return $rate;
}

/**
 * The columns a line needs from a product row.
 *
 * @return string
 */
function erp_product_columns($alias = 'p')
{
    return $alias . ".id, " . $alias . ".name, " . $alias . ".short_description, " . $alias . ".price, " . $alias . ".tax_rate,
        " . $alias . ".enabled, " . $alias . ".inventory, " . $alias . ".inventory_quantity, " . $alias . ".out_of_stock";
}

/**
 * Products matching what was typed.
 *
 * The name (short_description) is what people know a product by; the SKU
 * (name) is what the shelf label says; a barcode is what the scanner reads.
 * All three are searched, exact barcode hits first. Disabled products are
 * offered too - a bill for a product no longer on sale is still a bill -
 * marked so the editor can say so.
 *
 * @param string $query
 * @param int    $limit
 * @return array  See erp_product_for_line()
 */
function erp_product_search($query, $limit = 20)
{
    $query = trim((string) $query);

    if ($query === '') {
        return array();
    }

    $like = escape(escape_like(mb_substr($query, 0, 100)));
    $limit = max(1, min(50, (int) $limit));

    $results = array();
    $seen = array();

    // A scanner types the whole code and nothing else; an exact barcode is
    // the product, before any name that happens to contain the digits.
    $by_barcode = erp_product_by_barcode($query);

    if (is_array($by_barcode)) {
        $results[] = erp_product_for_line($by_barcode);
        $seen[(int) $by_barcode['id']] = true;
    }

    $rows = (array) db_items("SELECT " . erp_product_columns('p') . "
        FROM products p
        WHERE p.short_description LIKE '%" . $like . "%' OR p.name LIKE '%" . $like . "%'
        ORDER BY p.enabled DESC, p.short_description ASC, p.name ASC, p.id ASC
        LIMIT " . $limit);

    foreach ($rows as $row) {
        if (isset($seen[(int) $row['id']])) {
            continue;
        }
        $results[] = erp_product_for_line($row);
    }

    return array_slice($results, 0, $limit);
}

/**
 * The product a barcode names, or null.
 *
 * With the barcode feature on, the product_barcodes table; without it the
 * SKU doubles as the code, the way the local-sale screen reads a scan.
 *
 * @param string $barcode
 * @return array|null  A products row
 */
function erp_product_by_barcode($barcode)
{
    $barcode = trim((string) $barcode);

    if (($barcode === '') || (mb_strlen($barcode) > 100)) {
        return null;
    }

    $product_id = 0;

    if (defined('BARCODE_ENABLED') && BARCODE_ENABLED && function_exists('waf_table_has_column') && waf_table_has_column('product_barcodes', 'barcode')) {
        $product_id = (int) db_value("SELECT product_id FROM product_barcodes WHERE barcode = '" . escape($barcode) . "' LIMIT 1");
    }

    if ($product_id <= 0) {
        $product_id = (int) db_value("SELECT id FROM products WHERE name = '" . escape($barcode) . "' LIMIT 1");
    }

    if ($product_id <= 0) {
        return null;
    }

    $row = db_item("SELECT " . erp_product_columns('p') . " FROM products p WHERE p.id = '" . $product_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * One product, by id, in the line shape.
 *
 * @param int $product_id
 * @return array|null
 */
function erp_product_line_data($product_id)
{
    $row = db_item("SELECT " . erp_product_columns('p') . " FROM products p WHERE p.id = '" . (int) $product_id . "' LIMIT 1");

    return is_array($row) ? erp_product_for_line($row) : null;
}

/**
 * A products row as the editor wants it.
 *
 * price is kurus, the way the editor works with money. tax_rate is the rate
 * the line starts with: the product's own, or the store's zone rate when the
 * product leaves it to the zone (tax_rate_source says which). stock is null
 * when the product is not stock-tracked. offer is the automatic campaign on
 * the product, or null.
 *
 * @param array $row
 * @return array
 */
function erp_product_for_line($row)
{
    $price = (int) $row['price'];
    $tracked = ((int) ($row['inventory'] ?? 0) === 1);

    $own_rate = (isset($row['tax_rate']) && ($row['tax_rate'] !== null) && ($row['tax_rate'] !== ''));

    return array(
        'id' => (int) $row['id'],
        'name' => (trim((string) ($row['short_description'] ?? '')) !== '') ? trim((string) $row['short_description']) : (string) $row['name'],
        'sku' => (string) $row['name'],
        'price' => $price,
        'tax_rate' => $own_rate ? (float) $row['tax_rate'] : erp_default_tax_rate(),
        'tax_rate_source' => $own_rate ? 'product' : 'zone',
        'enabled' => ((int) ($row['enabled'] ?? 1) === 1),
        'stock' => $tracked ? (int) $row['inventory_quantity'] : null,
        'out_of_stock' => $tracked ? (((int) ($row['out_of_stock'] ?? 0) === 1) || ((int) $row['inventory_quantity'] <= 0)) : false,
        'offer' => erp_product_offer((int) $row['id'], $price),
    );
}

/**
 * The automatic campaign the store is running on a product, as a discount
 * rate, or null.
 *
 * Only what can be known from the product alone counts: an enabled offer
 * within its dates that needs no code, is not an upsell, has no cart rule
 * (minimum subtotal or quantity) and no customer conditions, with a
 * 'discount product' action aimed at this product or at a group it is in. An
 * offer that depends on the rest of the cart or on who is buying is the
 * checkout's to judge, not a typed invoice's. The biggest discount wins, the
 * way the cart applies the best offer.
 *
 * An amount discount is turned into a rate to three decimals so the line can
 * carry one figure; on a price under a thousand lira the difference is under
 * a kurus.
 *
 * @param int $product_id
 * @param int $price  Kurus, for turning an amount into a rate
 * @return array|null  ['id' => int, 'code' => string, 'rate' => float, 'label' => string]
 */
function erp_product_offer($product_id, $price)
{
    static $cache = array();

    $product_id = (int) $product_id;
    $price = (int) $price;

    if (($product_id <= 0) || ($price <= 0)) {
        return null;
    }

    if (!isset($cache[$product_id])) {
        $cache[$product_id] = null;

        if (!function_exists('pg_offer_action_targets_product') || !function_exists('pg_offer_action_target')) {
            return null;
        }

        $has_group_column = function_exists('waf_table_has_column') && waf_table_has_column('offer_actions', 'discount_product_group_id');
        $has_conditions = function_exists('waf_table_has_column') && waf_table_has_column('offer_conditions', 'offer_id');

        $rows = (array) db_items("SELECT o.id AS offer_id, o.code, o.description,
                a.id AS action_id, a.discount_product_product_id, a.discount_product_amount, a.discount_product_percentage"
                . ($has_group_column ? ", a.discount_product_group_id, a.discount_product_target" : "") . "
            FROM offers o
            INNER JOIN offers_offer_actions_xref x ON x.offer_id = o.id
            INNER JOIN offer_actions a ON a.id = x.offer_action_id
            WHERE o.status = 'enabled'
                AND o.start_date <= CURRENT_DATE()
                AND (o.end_date = '0000-00-00' OR CURRENT_DATE() <= o.end_date)
                AND o.require_code = 0
                AND o.upsell = 0
                AND o.offer_rule_id = 0
                AND a.type = 'discount product'
                AND (a.discount_product_amount <> 0 OR a.discount_product_percentage <> 0)");

        $best = null;

        foreach ($rows as $row) {
            // The cheapest-line target cannot be answered from the product.
            if (pg_offer_action_target($row) === 'cheapest') {
                continue;
            }
            if (!pg_offer_action_targets_product($row, $product_id)) {
                continue;
            }
            if ($has_conditions && ((int) db_value("SELECT COUNT(*) FROM offer_conditions WHERE offer_id = '" . (int) $row['offer_id'] . "'") > 0)) {
                continue;
            }

            if ((int) $row['discount_product_amount'] > 0) {
                $discount = min($price, (int) $row['discount_product_amount']);
                $rate = round(($discount * 100) / $price, 3);
            } else {
                $rate = round((float) $row['discount_product_percentage'], 3);
                $discount = erp_apply_rate($price, $rate);
            }

            if (($rate <= 0) || ($rate > 100)) {
                continue;
            }

            if (($best === null) || ($discount > $best['discount'])) {
                $best = array(
                    'id' => (int) $row['offer_id'],
                    'code' => trim((string) $row['code']),
                    'rate' => $rate,
                    'discount' => $discount,
                    'label' => (trim((string) $row['description']) !== '') ? trim((string) $row['description']) : trim((string) $row['code']),
                );
            }
        }

        if ($best !== null) {
            unset($best['discount']);
        }

        $cache[$product_id] = $best;
    }

    return $cache[$product_id];
}
