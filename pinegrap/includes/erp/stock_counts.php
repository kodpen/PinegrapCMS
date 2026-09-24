<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - stock counts.
 *
 * The shelf counted by scanning: each barcode read adds one to that
 * product's count (or the quantity typed with it), the counts can be
 * corrected by hand, and applying the count sets the store's stock to what
 * was counted. What the store had before is kept on the count line, so the
 * difference is on record after the count is applied.
 *
 * A count is its own document (erp_stock_counts, 4.100) and not a stock move
 * of the invoice ledger (erp_stock_moves): a correction of the shelf is not
 * a purchase or a sale and carries no cost. Only products whose stock is
 * tracked can be counted; products not scanned are left as they are.
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
 * Whether the 4.100 tables are there.
 *
 * @return bool
 */
function erp_stock_counts_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_stock_counts', 'status')
            && waf_table_has_column('erp_stock_count_items', 'counted');
    }

    return $ready;
}

/**
 * @return array  status => [label, badge colour]
 */
function erp_stock_count_statuses()
{
    return array(
        'open' => array(lang('Counting'), 'primary'),
        'applied' => array(lang('Applied'), 'success'),
        'cancelled' => array(lang('Cancelled'), 'secondary'),
    );
}

/**
 * Start a count.
 *
 * @param string $title
 * @param int    $user_id
 * @return int  The count, 0 when it could not be written
 */
function erp_stock_count_create($title, $user_id)
{
    if (!erp_stock_counts_ready()) {
        return 0;
    }

    $title = mb_substr(trim((string) $title), 0, 100);
    if ($title === '') {
        $title = lang(array('string' => 'Count of {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d'), 'date', false)));
    }

    $ok = db("INSERT INTO erp_stock_counts SET title = '" . escape($title) . "', status = 'open',
        created_by = '" . (int) $user_id . "', created_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP()");

    return ($ok === false) ? 0 : (int) mysqli_insert_id(db::$con);
}

/**
 * One count, or null.
 *
 * @param int $count_id
 * @return array|null
 */
function erp_stock_count($count_id)
{
    if (!erp_stock_counts_ready()) {
        return null;
    }

    $row = db_item("SELECT c.*, u.user_username AS created_by_name
        FROM erp_stock_counts c
        LEFT JOIN user u ON u.user_id = c.created_by
        WHERE c.id = '" . (int) $count_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The counts, newest first.
 *
 * @return array
 */
function erp_stock_count_list()
{
    if (!erp_stock_counts_ready()) {
        return array();
    }

    return (array) db_items("SELECT c.*, u.user_username AS created_by_name,
            (SELECT COUNT(*) FROM erp_stock_count_items i WHERE i.count_id = c.id) AS line_count
        FROM erp_stock_counts c
        LEFT JOIN user u ON u.user_id = c.created_by
        ORDER BY c.id DESC
        LIMIT 200");
}

/**
 * A count's lines, with each product's name and its stock now.
 *
 * @param int $count_id
 * @return array
 */
function erp_stock_count_items($count_id)
{
    return (array) db_items("SELECT i.*, p.name AS product_sku, p.short_description AS product_name,
            p.inventory, p.inventory_quantity
        FROM erp_stock_count_items i
        INNER JOIN products p ON p.id = i.product_id
        WHERE i.count_id = '" . (int) $count_id . "'
        ORDER BY i.updated_at DESC, i.product_id ASC");
}

/**
 * A barcode read: add to the product's count, or set it.
 *
 * @param int    $count_id
 * @param string $code      Barcode or SKU
 * @param int    $quantity  How many were read (1 for one scan)
 * @param bool   $set       true sets the count to $quantity instead of adding
 * @return array ['success' => bool, 'error' => string, 'product' => string, 'counted' => int]
 */
function erp_stock_count_scan($count_id, $code, $quantity = 1, $set = false)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'product' => '', 'counted' => 0);
    };

    $count = erp_stock_count($count_id);

    if (($count === null) || ((string) $count['status'] !== 'open')) {
        return $fail(lang('Only an open count can be changed.'));
    }

    $product = erp_product_by_barcode($code);

    if (!is_array($product)) {
        return $fail(lang(array('string' => 'No product carries the code {var:1}.', 'vars' => mb_substr(trim((string) $code), 0, 100))));
    }

    $name = (trim((string) ($product['short_description'] ?? '')) !== '') ? (string) $product['short_description'] : (string) $product['name'];

    if ((int) $product['inventory'] !== 1) {
        return $fail(lang(array('string' => '{var:1} does not track stock, so it is not counted.', 'vars' => $name)));
    }

    $quantity = (int) $quantity;
    if (($quantity < 0) || ($quantity > 1000000) || (!$set && ($quantity === 0))) {
        return $fail(lang('Enter a quantity from 1 up.'));
    }

    db("INSERT INTO erp_stock_count_items (count_id, product_id, counted, updated_at)
        VALUES ('" . (int) $count_id . "', '" . (int) $product['id'] . "', '" . $quantity . "', UNIX_TIMESTAMP())
        ON DUPLICATE KEY UPDATE counted = " . ($set ? "VALUES(counted)" : "counted + VALUES(counted)") . ", updated_at = VALUES(updated_at)");

    db("UPDATE erp_stock_counts SET updated_at = UNIX_TIMESTAMP() WHERE id = '" . (int) $count_id . "'");

    $counted = (int) db_value("SELECT counted FROM erp_stock_count_items WHERE count_id = '" . (int) $count_id . "' AND product_id = '" . (int) $product['id'] . "'");

    return array('success' => true, 'error' => '', 'product' => $name, 'counted' => $counted);
}

/**
 * Corrections typed on the count's lines: a number sets the count, an empty
 * box takes the product off the count.
 *
 * @param int   $count_id
 * @param array $values  product_id => text
 * @return array ['success' => bool, 'error' => string]
 */
function erp_stock_count_save($count_id, $values)
{
    $count = erp_stock_count($count_id);

    if (($count === null) || ((string) $count['status'] !== 'open')) {
        return array('success' => false, 'error' => lang('Only an open count can be changed.'));
    }

    foreach ((array) $values as $product_id => $text) {
        $text = trim((string) $text);

        if ($text === '') {
            db("DELETE FROM erp_stock_count_items WHERE count_id = '" . (int) $count_id . "' AND product_id = '" . (int) $product_id . "'");
            continue;
        }

        if (!preg_match('/^\d{1,7}$/', $text)) {
            return array('success' => false, 'error' => lang('A count is a whole number of pieces.'));
        }

        db("UPDATE erp_stock_count_items SET counted = '" . (int) $text . "', updated_at = UNIX_TIMESTAMP()
            WHERE count_id = '" . (int) $count_id . "' AND product_id = '" . (int) $product_id . "'");
    }

    return array('success' => true, 'error' => '');
}

/**
 * Apply the count: every product on it gets the stock that was counted, and
 * what it had before is kept on the line. Products not on the count are left.
 *
 * @param int $count_id
 * @param int $user_id
 * @return array ['success' => bool, 'error' => string, 'changed' => int, 'lines' => int]
 */
function erp_stock_count_apply($count_id, $user_id)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'changed' => 0, 'lines' => 0);
    };

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $count = db_item("SELECT id, status FROM erp_stock_counts WHERE id = '" . (int) $count_id . "' LIMIT 1 FOR UPDATE");

    if (!is_array($count) || ((string) $count['status'] !== 'open')) {
        erp_tx_rollback();
        return $fail(lang('Only an open count can be applied.'));
    }

    $items = (array) db_items("SELECT i.product_id, i.counted, p.inventory_quantity
        FROM erp_stock_count_items i
        INNER JOIN products p ON p.id = i.product_id
        WHERE i.count_id = '" . (int) $count_id . "'
        FOR UPDATE");

    if (empty($items)) {
        erp_tx_rollback();
        return $fail(lang('Nothing has been counted yet.'));
    }

    $changed = 0;
    $touched = array();

    foreach ($items as $item) {
        $before = (int) $item['inventory_quantity'];
        $counted = (int) $item['counted'];

        $ok = erp_query("UPDATE erp_stock_count_items SET system_before = '" . $before . "'
            WHERE count_id = '" . (int) $count_id . "' AND product_id = '" . (int) $item['product_id'] . "'");

        if (($ok !== false) && ($before !== $counted)) {
            $ok = erp_query("UPDATE products SET inventory_quantity = '" . $counted . "',
                    out_of_stock = '" . (($counted <= 0) ? 1 : 0) . "'"
                . (($counted <= 0) ? ", out_of_stock_timestamp = UNIX_TIMESTAMP()" : "") . "
                WHERE id = '" . (int) $item['product_id'] . "'");
            $changed++;
            $touched[] = (int) $item['product_id'];
        }

        if ($ok === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    if (erp_query("UPDATE erp_stock_counts SET status = 'applied', applied_by = '" . (int) $user_id . "', applied_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $count_id . "'") === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    // A marketplace that lists these products hears about the new counts.
    if (function_exists('pg_marketplace_product_changed')) {
        foreach ($touched as $product_id) {
            pg_marketplace_product_changed($product_id);
        }
    }

    return array('success' => true, 'error' => '', 'changed' => $changed, 'lines' => count($items));
}

/**
 * Leave a count without applying it.
 *
 * @param int $count_id
 * @return bool
 */
function erp_stock_count_cancel($count_id)
{
    db("UPDATE erp_stock_counts SET status = 'cancelled', updated_at = UNIX_TIMESTAMP() WHERE id = '" . (int) $count_id . "' AND status = 'open'");

    return (mysqli_affected_rows(db::$con) === 1);
}
