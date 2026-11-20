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
 * Whether the column "uses the prices of" is there (2026.11.0, step
 * 11.0.235).
 *
 * @return bool
 */
function erp_shared_prices_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_accounts', 'price_account_id');
    }

    return $ready;
}

/**
 * Whose prices an account buys at: the account it points at, or its own.
 * A pointer at itself is read as none. Pure.
 *
 * @param int $account_id
 * @param int $pointer    erp_accounts.price_account_id, 0 for none
 * @return int
 */
function erp_account_price_source_id($account_id, $pointer)
{
    $account_id = (int) $account_id;
    $pointer = (int) $pointer;

    if ($account_id <= 0) {
        return 0;
    }

    return (($pointer > 0) && ($pointer !== $account_id)) ? $pointer : $account_id;
}

/**
 * Whose prices an account buys at, read from its row. One hop only: the
 * save refuses a pointer at an account that points on
 * (erp_account_price_pointer_refusal()), so the pointer is followed once.
 *
 * @param int $account_id
 * @return int
 */
function erp_account_price_source($account_id)
{
    static $sources = array();

    $account_id = (int) $account_id;

    if (($account_id <= 0) || !erp_shared_prices_ready()) {
        return max(0, $account_id);
    }

    if (!isset($sources[$account_id])) {
        $sources[$account_id] = erp_account_price_source_id($account_id, (int) db_value("SELECT price_account_id FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1"));
    }

    return $sources[$account_id];
}

/**
 * The terms one account's rows and discount give a product. Pure.
 *
 * @param array $known      ['entries' => product_id => ['price', 'discount_rate'], 'discount_rate' => float]
 * @param int   $product_id
 * @return array|null as erp_account_price_terms()
 */
function erp_account_price_terms_pick(array $known, $product_id)
{
    $entries = $known['entries'] ?? array();

    if (isset($entries[(int) $product_id])) {
        return $entries[(int) $product_id] + array('source' => 'product');
    }

    $rate = (float) ($known['discount_rate'] ?? 0);

    if ($rate > 0) {
        return array('price' => 0, 'discount_rate' => $rate, 'source' => 'account');
    }

    return null;
}

/**
 * The terms an account has for a product, or null when it has none. An
 * account that uses another account's prices gets that account's rows and
 * discount instead of its own.
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

    $source_id = erp_account_price_source($account_id);

    if (!isset($accounts[$source_id])) {
        $entries = array();
        foreach ((array) db_items("SELECT product_id, price, discount_rate FROM erp_account_prices WHERE account_id = '" . $source_id . "'") as $row) {
            $entries[(int) $row['product_id']] = array('price' => (int) $row['price'], 'discount_rate' => (float) $row['discount_rate']);
        }

        $accounts[$source_id] = array(
            'entries' => $entries,
            'discount_rate' => (float) db_value("SELECT discount_rate FROM erp_accounts WHERE id = '" . $source_id . "' LIMIT 1"),
        );
    }

    return erp_account_price_terms_pick($accounts[$source_id], $product_id);
}

/**
 * What the store weighs for an account (pg_erp_member_prices()): the
 * products the account whose prices it buys at has a row for, and whether
 * that account has a discount for everything else.
 *
 * @param int $account_id
 * @return array ['listed' => int[], 'account_wide' => bool]
 */
function erp_account_storefront_scope($account_id)
{
    $source_id = erp_account_price_source($account_id);
    $listed = array();

    if ($source_id <= 0) {
        return array('listed' => $listed, 'account_wide' => false);
    }

    foreach ((array) db_items("SELECT product_id FROM erp_account_prices WHERE account_id = '" . $source_id . "'") as $row) {
        $listed[] = (int) $row['product_id'];
    }

    return array(
        'listed' => $listed,
        'account_wide' => ((float) db_value("SELECT discount_rate FROM erp_accounts WHERE id = '" . $source_id . "' LIMIT 1") > 0),
    );
}

/**
 * Why an account may not point at another, or '' when it may. One hop
 * only: the chosen account may not use another's prices itself, and an
 * account whose prices others use may not use another's. Letting go (0) is
 * always allowed. Pure.
 *
 * @param int  $account_id
 * @param int  $target_id      the account chosen, 0 for none
 * @param bool $target_exists
 * @param int  $target_pointer the chosen account's own pointer
 * @param int  $pointed_at_by  how many accounts use this account's prices
 * @return string
 */
function erp_account_price_pointer_refusal($account_id, $target_id, $target_exists, $target_pointer, $pointed_at_by)
{
    $target_id = (int) $target_id;

    if ($target_id <= 0) {
        return '';
    }

    if ($target_id === (int) $account_id) {
        return lang('An account cannot use its own prices.');
    }

    if (!$target_exists) {
        return lang('Choose an existing account.');
    }

    if (((int) $target_pointer > 0) && ((int) $target_pointer !== $target_id)) {
        return lang('That account uses another account\'s prices itself. Choose the account whose prices it uses.');
    }

    if ((int) $pointed_at_by > 0) {
        return lang('Other accounts use this account\'s prices, so it cannot use another account\'s.');
    }

    return '';
}

/**
 * The accounts that use an account's prices.
 *
 * @param int $account_id
 * @return int[]
 */
function erp_account_price_users($account_id)
{
    if (((int) $account_id <= 0) || !erp_shared_prices_ready()) {
        return array();
    }

    return array_map('intval', (array) db_values("SELECT id FROM erp_accounts WHERE price_account_id = '" . (int) $account_id . "' AND id != '" . (int) $account_id . "' ORDER BY id"));
}

/**
 * The accounts that use an account's prices as its screen lists them: how
 * many there are, and the first $limit by title, read in one query.
 *
 * @param int $account_id
 * @param int $limit
 * @return array ['count' => int, 'rows' => [['id' => int, 'title' => string], ...]]
 */
function erp_account_price_users_shown($account_id, $limit = 10)
{
    $out = array('count' => 0, 'rows' => array());

    if (((int) $account_id <= 0) || !erp_shared_prices_ready()) {
        return $out;
    }

    // The count rides on every row (a subquery, as MySQL 5.7 has no window
    // functions), so one query gives both.
    $where = "price_account_id = '" . (int) $account_id . "' AND id != '" . (int) $account_id . "'";
    $rows = (array) db_items("SELECT id, title, (SELECT COUNT(*) FROM erp_accounts WHERE " . $where . ") AS total
        FROM erp_accounts
        WHERE " . $where . "
        ORDER BY title ASC, id ASC
        LIMIT " . max(1, (int) $limit));

    return erp_account_price_users_lines($rows, $limit);
}

/**
 * Bounds the list of the accounts that use an account's prices: at most
 * $limit rows, the count read from the rows' total. Pure.
 *
 * @param array $rows  id, title, total (every row carries the full count)
 * @param int   $limit
 * @return array ['count' => int, 'rows' => [...], 'more' => int]
 */
function erp_account_price_users_lines(array $rows, $limit)
{
    $limit = max(1, (int) $limit);
    $count = $rows ? max(count($rows), (int) ($rows[0]['total'] ?? 0)) : 0;
    $shown = array();

    foreach (array_slice($rows, 0, $limit) as $row) {
        $shown[] = array('id' => (int) $row['id'], 'title' => (string) $row['title']);
    }

    return array('count' => $count, 'rows' => $shown, 'more' => max(0, $count - count($shown)));
}

/**
 * Why an account may not be deleted for its prices' sake, or '' when it
 * may: accounts that use its prices must let go first. Accounts have no
 * delete today (they are set to passive); a delete path asks this. Pure.
 *
 * @param int $pointed_at_by how many accounts use this account's prices
 * @return string
 */
function erp_account_delete_refusal($pointed_at_by)
{
    return ((int) $pointed_at_by > 0)
        ? lang('Other accounts use this account\'s prices. Let them use their own or another account\'s first.')
        : '';
}

/**
 * The accounts the "Uses the prices of" picker offers: customer accounts
 * (customer or both), passive ones included since a price list need not
 * buy, but not the account itself and not an account that uses another's
 * prices (one hop). The account already chosen stays, so the screen can
 * show it. Pure.
 *
 * @param array $accounts   rows with id, title, kind, price_account_id, in the order to show
 * @param int   $account_id the account whose screen it is
 * @param int   $current    its pointer, 0 for none
 * @return array id => title
 */
function erp_account_price_pointer_options(array $accounts, $account_id, $current)
{
    $options = array();

    foreach ($accounts as $row) {
        $id = (int) $row['id'];
        $offered = ($id !== (int) $account_id)
            && ((string) ($row['kind'] ?? '') !== 'supplier')
            && ((int) ($row['price_account_id'] ?? 0) <= 0);

        if ($offered || (($id === (int) $current) && ($id > 0))) {
            $options[$id] = (string) $row['title'];
        }
    }

    return $options;
}

/**
 * The one-hop check of a pointer, with the chosen account and the accounts
 * that use this one's prices read from the table: why it is refused, or ''.
 * The panel's save and the API's account update both ask it before writing.
 *
 * @param int $account_id
 * @param int $target_id 0 to use its own prices
 * @return string
 */
function erp_account_price_pointer_check($account_id, $target_id)
{
    $account_id = (int) $account_id;
    $target_id = max(0, (int) $target_id);

    $target = ($target_id > 0) ? db_item("SELECT id, price_account_id FROM erp_accounts WHERE id = '" . $target_id . "' LIMIT 1") : null;

    return erp_account_price_pointer_refusal(
        $account_id,
        $target_id,
        is_array($target) && !empty($target),
        is_array($target) ? (int) ($target['price_account_id'] ?? 0) : 0,
        ($target_id > 0) ? count(erp_account_price_users($account_id)) : 0
    );
}

/**
 * Sets whose prices an account buys at, or lets go with 0, after the
 * one-hop check.
 *
 * @param int $account_id
 * @param int $target_id 0 to use its own prices
 * @return array ['success' => bool, 'error' => string]
 */
function erp_account_price_pointer_save($account_id, $target_id)
{
    $account_id = (int) $account_id;
    $target_id = max(0, (int) $target_id);

    if (($account_id <= 0) || !erp_shared_prices_ready()) {
        return array('success' => false, 'error' => lang('The account was not found.'));
    }

    $error = erp_account_price_pointer_check($account_id, $target_id);

    if ($error !== '') {
        return array('success' => false, 'error' => $error);
    }

    if (erp_query("UPDATE erp_accounts SET price_account_id = '" . $target_id . "', updated_at = '" . time() . "' WHERE id = '" . $account_id . "'") === false) {
        return array('success' => false, 'error' => erp_db_error());
    }

    erp_event_account($account_id);

    return array('success' => true, 'error' => '');
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
 * The account-wide discount as typed: digits with at most one decimal mark,
 * from 0 to 100, nothing for none. A minus, a percent sign or a typo is
 * refused rather than read around (erp_fx_rate_in() dropped the minus, so
 * -5 saved 5 and 'abc' saved 0).
 *
 * @param string $text
 * @return float|null  The rate, 0.0 for an empty box, null when refused
 */
function erp_account_discount_rate_parse($text)
{
    $text = trim((string) $text);

    if ($text === '') {
        return 0.0;
    }

    if (preg_match('/^[0-9]+([.,][0-9]+)?$/', $text) !== 1) {
        return null;
    }

    $rate = (float) str_replace(',', '.', $text);

    return ($rate > 100) ? null : $rate;
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
    $rate = erp_account_discount_rate_parse($text);

    if ($rate === null) {
        return array('success' => false, 'error' => lang(array('string' => 'Enter a discount from 0 to 100, such as {var:1}.', 'vars' => array(pg_format_number(12.5, 1)))));
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
        // A percentage, not an amount: refused as "takes a number".
        'discount' => array('type' => 'decimal', 'label' => lang('Discount %'), 'empty' => true),
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
    $none = '<span class="fg-secondary">—</span>';

    return array(
        'product' => array(
            'html'  => h($name)
                . ((($sku !== '') && ($sku !== trim($name))) ? '<div class="small fg-secondary font-monospace">' . h($sku) . '</div>' : '')
                . (((string) $row['enabled'] !== '1') ? ' <span class="badge theme-secondary">' . lang('Not on sale') . '</span>' : '')
                . ((array_key_exists('on_list', $row) && empty($row['on_list'])) ? ' <span class="badge theme-warning">' . lang('Taken off the list') . '</span>' : ''),
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
