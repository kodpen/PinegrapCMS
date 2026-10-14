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

/**
 * An account price as its input box shows it: the store's decimal mark, no
 * grouping (1250,00 or 1250.00), and nothing for none.
 *
 * @param int $kurus
 * @return string
 */
function erp_account_price_input($kurus)
{
    return ((int) $kurus > 0) ? number_format(((int) $kurus) / 100, 2, erp_decimal_comma() ? ',' : '.', '') : '';
}

/**
 * A discount rate as its input box and the list show it: the store's decimal
 * mark and no trailing zeros (12,5), and nothing for none.
 *
 * @param float|string $rate
 * @return string
 */
function erp_account_rate_input($rate)
{
    if ((float) $rate <= 0) {
        return '';
    }

    $mark = erp_decimal_comma() ? ',' : '.';

    return rtrim(rtrim(number_format((float) $rate, 3, $mark, ''), '0'), $mark);
}

/**
 * The account price list's columns that change in place
 * (pg_list_edit_table()): the product's price and its discount, either one
 * left empty for none. Both are read as amounts here and by the screen's own
 * readers on save (erp_kurus(), erp_fx_rate_in()).
 *
 * @return array field => definition
 */
function erp_account_price_edit_columns()
{
    return array(
        'price'    => array('type' => 'money', 'label' => lang('Account price'), 'empty' => true),
        'discount' => array('type' => 'money', 'label' => lang('Discount %'), 'empty' => true),
    );
}

/**
 * The account price list's cells, drawn the one way the list and the answer
 * to a quick edit both use: the product with its code and badges, the price
 * and the discount, a dash for none. 'sort' is what the browser sorts the
 * column by (data-sort). A row whose 'on_list' is empty was taken off the
 * list a moment ago; it keeps its place until the page is reloaded and says
 * so, and typing a price or a discount puts it back.
 *
 * @param array $row product_name, product_sku, enabled, price, discount_rate
 *                   and, from erp_account_price_quick_save(), on_list
 * @return array 'product', 'price' and 'discount' => array('html', 'class'[, 'sort'])
 */
function erp_account_price_cells($row)
{
    $name = (trim((string) $row['product_name']) !== '') ? (string) $row['product_name'] : (string) $row['product_sku'];
    $sku = trim((string) $row['product_sku']);
    $price = (int) $row['price'];
    $rate = (float) $row['discount_rate'];
    $none = '<span class="text-body-secondary">—</span>';

    return array(
        'product' => array(
            'html'  => h($name)
                . ((($sku !== '') && ($sku !== trim($name))) ? '<div class="small text-body-secondary font-monospace">' . h($sku) . '</div>' : '')
                . (((string) $row['enabled'] !== '1') ? ' <span class="badge text-bg-secondary">' . lang('Not on sale') . '</span>' : '')
                . ((array_key_exists('on_list', $row) && empty($row['on_list'])) ? ' <span class="badge text-bg-warning">' . lang('Taken off the list') . '</span>' : ''),
            'class' => '',
        ),
        'price' => array(
            'html'  => ($price > 0) ? h(erp_money_out($price)) : $none,
            'class' => 'text-end text-nowrap',
            'sort'  => $price,
        ),
        'discount' => array(
            'html'  => ($rate > 0) ? h(erp_account_rate_input($rate)) : $none,
            'class' => 'text-end text-nowrap',
            'sort'  => number_format($rate, 3, '.', ''),
        ),
    );
}

/**
 * One of an account's terms changed on its price list, saved by
 * erp_account_prices_save() like the rest, and the answer to the quick edit:
 * the cell, and the product's cell, which says when the product left the
 * list.
 *
 * erp_account_prices_save() takes a product off the list when it is left
 * with neither a price nor a discount, and the list keeps that rule: clearing
 * the last term takes the product off, and typing one again puts it back.
 * The other term is passed on as stored, so only the typed one changes.
 *
 * @param int    $account_id
 * @param int    $product_id
 * @param string $field   'price' or 'discount'
 * @param string $value   the amount as typed, or '' for none
 * @param int    $user_id
 * @return array the answer for pg_list_edit_reply()
 */
function erp_account_price_quick_save($account_id, $product_id, $field, $value, $user_id)
{
    $account_id = (int) $account_id;
    $product_id = (int) $product_id;
    $columns = erp_account_price_edit_columns();

    if (!erp_price_lists_ready()) {
        return array('ok' => false, 'code' => 'not_ready', 'message' => lang('Account prices come with the software update; run the update to use them.'));
    }

    if (!isset($columns[$field])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('This column cannot be changed here.'));
    }

    $account = erp_account($account_id);

    if (!is_array($account)) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('The account could not be found.'));
    }

    $read = function () use ($account_id, $product_id) {
        return db_item("SELECT p.id, p.name AS product_sku, p.short_description AS product_name, p.enabled,
                (e.product_id IS NOT NULL) AS on_list, COALESCE(e.price, 0) AS price, COALESCE(e.discount_rate, 0) AS discount_rate
            FROM products p
            LEFT JOIN erp_account_prices e ON e.product_id = p.id AND e.account_id = '" . $account_id . "'
            WHERE p.id = '" . $product_id . "'");
    };

    $before = $read();

    if (!$before) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Product not found.'));
    }

    // The stored terms as erp_account_prices_save() reads them back exactly:
    // a point before the cents, three places for the rate.
    $stored_price = (int) $before['price'];
    $terms = array(
        'price'    => ($stored_price > 0) ? intdiv($stored_price, 100) . '.' . str_pad((string) ($stored_price % 100), 2, '0', STR_PAD_LEFT) : '',
        'discount' => ((float) $before['discount_rate'] > 0) ? number_format((float) $before['discount_rate'], 3, '.', '') : '',
    );
    $terms[$field] = trim((string) $value);

    $price = ($terms['price'] === '') ? 0 : erp_kurus($terms['price']);
    $rate = ($terms['discount'] === '') ? 0.0 : erp_fx_rate_in($terms['discount']);

    // The column is a BIGINT; a price is held to what a product's own price
    // can be (products.price, a signed INT).
    if ($price > 2147483647) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang(array('string' => '{var:1} is too large.', 'vars' => array($columns['price']['label']))));
    }

    // The same terms again write nothing and log nothing; neither does
    // clearing a product that is already off the list.
    $same = !empty($before['on_list'])
        ? (($price === $stored_price) && (abs($rate - (float) $before['discount_rate']) < 0.0005))
        : (($price === 0) && ($rate <= 0));

    if (!$same) {
        $saved = erp_account_prices_save($account_id, array($product_id => $terms), $user_id);

        if (!$saved['success']) {
            return array('ok' => false, 'code' => 'invalid', 'message' => $saved['error']);
        }
    }

    $after = $read();

    if (!$same) {
        if (!empty($before['on_list']) && empty($after['on_list'])) {
            log_activity(lang(array(
                'string' => 'erp product ({var:1}) was taken off the price list of account ({var:2})',
                'vars'   => array((string) $before['product_sku'], (string) $account['title']),
            )), $_SESSION['sessionusername'] ?? '');
        } else {
            $shown = function ($row) use ($field) {
                if (empty($row['on_list'])) {
                    return '—';
                }

                $text = ($field === 'price') ? erp_account_price_input($row['price']) : erp_account_rate_input($row['discount_rate']);

                return ($text !== '') ? $text : '—';
            };

            log_activity(lang(array(
                'string' => 'erp price of product ({var:1}) for account ({var:2}) was changed: {var:3} from "{var:4}" to "{var:5}"',
                'vars'   => array((string) $before['product_sku'], (string) $account['title'], $columns[$field]['label'], $shown($before), $shown($after)),
            )), $_SESSION['sessionusername'] ?? '');
        }
    }

    $cells = erp_account_price_cells($after);

    return array('ok' => true, 'value' => ($field === 'price') ? erp_account_price_input($after['price']) : erp_account_rate_input($after['discount_rate']))
        + $cells[$field]
        + array('cells' => array('product' => $cells['product']));
}
