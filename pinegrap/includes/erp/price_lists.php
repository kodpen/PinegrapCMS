<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - an account's own prices.
 *
 * A dealer buys at a price of its own, a contract customer at a discount:
 * terms agreed with one account. Two layers, both on sales only:
 *
 *   - a price per product (erp_account_prices.price), which replaces the list
 *     price, or a discount per product (discount_rate) on the list price;
 *   - a discount for everything else (erp_accounts.discount_rate).
 *
 * The line editor of invoices and quotes applies them when a product is
 * picked for that account (erp_product_for_line() carries them); the store's
 * campaign does not stack on top of agreed terms. Everything stays editable
 * on the line. Kept in the price basis of the catalogue, like the list price.
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
 * Whether the 4.94 schema is there.
 *
 * @return bool
 */
function erp_price_lists_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_account_prices', 'discount_rate')
            && waf_table_has_column('erp_accounts', 'discount_rate');
    }

    return $ready;
}

/**
 * The terms an account has for a product, or null when it has none.
 *
 * @param int $account_id
 * @param int $product_id
 * @return array|null ['price' => int kurus (0 for none), 'discount_rate' => float, 'source' => 'product'|'account']
 */
function erp_account_price_terms($account_id, $product_id)
{
    static $accounts = array();

    $account_id = (int) $account_id;

    if (($account_id <= 0) || !erp_price_lists_ready()) {
        return null;
    }

    if (!isset($accounts[$account_id])) {
        $entries = array();
        foreach ((array) db_items("SELECT product_id, price, discount_rate FROM erp_account_prices WHERE account_id = '" . $account_id . "'") as $row) {
            $entries[(int) $row['product_id']] = array('price' => (int) $row['price'], 'discount_rate' => (float) $row['discount_rate']);
        }

        $accounts[$account_id] = array(
            'entries' => $entries,
            'discount_rate' => (float) db_value("SELECT discount_rate FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1"),
        );
    }

    $known = $accounts[$account_id];

    if (isset($known['entries'][(int) $product_id])) {
        return $known['entries'][(int) $product_id] + array('source' => 'product');
    }

    if ($known['discount_rate'] > 0) {
        return array('price' => 0, 'discount_rate' => $known['discount_rate'], 'source' => 'account');
    }

    return null;
}

/**
 * An account's per-product terms, with the products' names and list prices.
 *
 * @param int $account_id
 * @return array
 */
function erp_account_price_rows($account_id)
{
    if (!erp_price_lists_ready()) {
        return array();
    }

    return (array) db_items("SELECT e.product_id, e.price, e.discount_rate, e.updated_at,
            p.name AS product_sku, p.short_description AS product_name, p.price AS list_price, p.enabled
        FROM erp_account_prices e
        INNER JOIN products p ON p.id = e.product_id
        WHERE e.account_id = '" . (int) $account_id . "'
        ORDER BY p.short_description ASC, p.name ASC
        LIMIT 1000");
}

/**
 * Save the terms typed for an account's products. A product left with no
 * price and no discount is taken off the list.
 *
 * @param int   $account_id
 * @param array $values  product_id => ['price' => text, 'discount' => text]
 * @param int   $user_id
 * @return array ['success' => bool, 'changed' => int, 'error' => string]
 */
function erp_account_prices_save($account_id, $values, $user_id)
{
    if (!erp_price_lists_ready()) {
        return array('success' => false, 'changed' => 0, 'error' => lang('Account prices come with the software update; run the update to use them.'));
    }

    $changed = 0;

    foreach ((array) $values as $product_id => $value) {
        $product_id = (int) $product_id;
        $value = (array) $value;

        if ($product_id <= 0) {
            continue;
        }

        $price_text = trim((string) ($value['price'] ?? ''));
        $discount_text = trim((string) ($value['discount'] ?? ''));
        $price = ($price_text === '') ? 0 : erp_kurus($price_text);
        $discount = ($discount_text === '') ? 0.0 : erp_fx_rate_in($discount_text);

        if (($price < 0) || ($discount < 0) || ($discount > 100)) {
            return array('success' => false, 'changed' => $changed, 'error' => lang('A price cannot be negative, and a discount runs from 0 to 100.'));
        }

        if (($price === 0) && ($discount <= 0)) {
            db("DELETE FROM erp_account_prices WHERE account_id = '" . (int) $account_id . "' AND product_id = '" . $product_id . "'");
        } else {
            db("INSERT INTO erp_account_prices (account_id, product_id, price, discount_rate, updated_by, updated_at)
                VALUES ('" . (int) $account_id . "', '" . $product_id . "', '" . (int) $price . "', '" . escape(number_format($discount, 3, '.', '')) . "', '" . (int) $user_id . "', UNIX_TIMESTAMP())
                ON DUPLICATE KEY UPDATE price = VALUES(price), discount_rate = VALUES(discount_rate), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)");
        }

        $changed++;
    }

    return array('success' => true, 'changed' => $changed, 'error' => '');
}

/**
 * Put a product on an account's list, at its list price for now.
 *
 * @param int $account_id
 * @param int $product_id
 * @param int $user_id
 * @return bool
 */
function erp_account_prices_add($account_id, $product_id, $user_id)
{
    $price = db_value("SELECT price FROM products WHERE id = '" . (int) $product_id . "' LIMIT 1");

    if (!erp_price_lists_ready() || ($price === null) || ($price === false)) {
        return false;
    }

    db("INSERT IGNORE INTO erp_account_prices (account_id, product_id, price, discount_rate, updated_by, updated_at)
        VALUES ('" . (int) $account_id . "', '" . (int) $product_id . "', '" . max(0, (int) $price) . "', 0, '" . (int) $user_id . "', UNIX_TIMESTAMP())");

    return true;
}

/**
 * The account's discount on everything without terms of its own.
 *
 * @param int    $account_id
 * @param string $text
 * @return array ['success' => bool, 'error' => string]
 */
function erp_account_discount_save($account_id, $text)
{
    $rate = (trim((string) $text) === '') ? 0.0 : erp_fx_rate_in($text);

    if (($rate < 0) || ($rate > 100)) {
        return array('success' => false, 'error' => lang('A price cannot be negative, and a discount runs from 0 to 100.'));
    }

    db("UPDATE erp_accounts SET discount_rate = '" . escape(number_format($rate, 3, '.', '')) . "' WHERE id = '" . (int) $account_id . "'");

    return array('success' => true, 'error' => '');
}

/**
 * How many products carry terms of their own for an account.
 *
 * @param int $account_id
 * @return int
 */
function erp_account_price_count($account_id)
{
    return erp_price_lists_ready() ? (int) db_value("SELECT COUNT(*) FROM erp_account_prices WHERE account_id = '" . (int) $account_id . "'") : 0;
}
