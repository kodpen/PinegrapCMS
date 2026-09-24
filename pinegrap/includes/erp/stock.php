<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - stock and cost from documents.
 *
 * A purchase invoice brings goods in and a typed sales invoice takes them
 * out; a return and a cancellation move them back. Every such movement is a
 * row in erp_stock_moves, written in the same transaction as the document,
 * so a document and its effect on stock cannot part company. A sales invoice
 * raised from an order changes no count: the order took the goods out of
 * stock when it was placed, and a second movement would count them twice. Its
 * lines still get a move, marked as not wanting to change stock, so what the
 * goods cost is known for the margins (includes/erp/profit.php).
 *
 * products is a MyISAM table, so its quantity cannot be changed inside the
 * document's transaction - a rollback would leave the stock changed and the
 * document gone. The move is therefore written first, marked as wanting to
 * change stock, and applied once the transaction has committed
 * (erp_stock_apply_pending()). A move that was never applied, because the
 * request died between the two, is applied by the next caller.
 *
 * Cost is kept per product in erp_product_costs: the weighted average of what
 * the goods on hand were bought for, and the last purchase price. The average
 * moves only with the purchase side - purchases, returns to the supplier and
 * their cancellations. A sale takes goods out at the average, which leaves
 * the average where it was, so the sales side records its cost on the move
 * (for margins later) and leaves the product's cost alone. For a product that
 * tracks stock the average is taken over the quantity on hand; for one that
 * does not, over everything bought and not returned. Stock that was on hand
 * before the product's first purchase invoice has no cost of its own and is
 * valued at that first purchase.
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
 * Whether the stock tables are there (2026.4.4, 4.77).
 *
 * @return bool
 */
function erp_stock_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('erp_stock_moves', 'stock_applied')
            && waf_table_has_column('erp_product_costs', 'avg_cost');
    }

    return $ready;
}

/**
 * Whether documents change the stock count (config.erp_stock_documents).
 * Off, moves are still written with their cost, and nothing is counted.
 *
 * @return bool
 */
function erp_stock_documents_on()
{
    static $on = null;

    if ($on === null) {
        $on = erp_stock_ready() && waf_table_has_column('config', 'erp_stock_documents')
            && ((int) db_value("SELECT erp_stock_documents FROM config LIMIT 1") === 1);
    }

    return $on;
}

/**
 * The kinds of movement, as the screens name them.
 *
 * @return array  kind => label
 */
function erp_stock_kinds()
{
    return array(
        'purchase' => lang('Purchase'),
        'sale' => lang('Sale'),
        'purchase_return' => lang('Return to supplier'),
        'sales_return' => lang('Sales return'),
        'cancel' => lang('Cancellation'),
    );
}

/**
 * Whether a quantity can change a stock count: Pinegrap counts whole units.
 *
 * @param float $quantity
 * @return bool
 */
function erp_stock_whole($quantity)
{
    $quantity = (float) $quantity;

    return ($quantity > 0) && (abs($quantity - round($quantity)) < 0.00005);
}

/**
 * A product's cost row, or null before the upgrade or before its first
 * purchase. Read once per request and product.
 *
 * @param int $product_id
 * @return array|null  avg_cost, last_cost, last_cost_date, costed_quantity
 */
function erp_stock_cost($product_id)
{
    static $cache = array();

    $product_id = (int) $product_id;

    if (($product_id <= 0) || !erp_stock_ready()) {
        return null;
    }

    if (!array_key_exists($product_id, $cache)) {
        $row = db_item("SELECT avg_cost, last_cost, last_cost_date, costed_quantity FROM erp_product_costs
            WHERE product_id = '" . $product_id . "' LIMIT 1");
        $cache[$product_id] = is_array($row) ? $row : null;
    }

    return $cache[$product_id];
}

/**
 * The quantity the average is taken over, as it stands in this transaction:
 * the count on hand for a product that tracks stock (with the moves written
 * but not yet applied), everything costed for one that does not.
 *
 * @param array $product  products row: id, inventory, inventory_quantity
 * @param array $cost     erp_product_costs row
 * @return float
 */
function erp_stock_basis($product, $cost)
{
    if ((int) $product['inventory'] !== 1) {
        return max(0.0, (float) $cost['costed_quantity']);
    }

    $pending = (float) db_value("SELECT COALESCE(SUM(IF(direction = 'in', quantity, -quantity)), 0)
        FROM erp_stock_moves
        WHERE product_id = '" . (int) $product['id'] . "' AND stock_wanted = 1 AND stock_applied = 0");

    return max(0.0, (float) $product['inventory_quantity'] + $pending);
}

/**
 * The last purchase price again, from the purchases still standing, after the
 * one that set it has been cancelled.
 *
 * @param int $product_id
 * @return array  last_cost, last_cost_date, last_move_id
 */
function erp_stock_last_purchase($product_id)
{
    $row = db_item("SELECT m.id, m.unit_cost, m.doc_date FROM erp_stock_moves m
        WHERE m.product_id = '" . (int) $product_id . "' AND m.kind = 'purchase'
          AND NOT EXISTS (SELECT 1 FROM erp_stock_moves r WHERE r.reverses_id = m.id)
        ORDER BY m.doc_date DESC, m.id DESC
        LIMIT 1");

    return is_array($row)
        ? array('last_cost' => (int) $row['unit_cost'], 'last_cost_date' => (string) $row['doc_date'], 'last_move_id' => (int) $row['id'])
        : array('last_cost' => 0, 'last_cost_date' => '0000-00-00', 'last_move_id' => 0);
}

/**
 * Write one movement and move the product's cost with it. Runs inside the
 * caller's transaction; the stock count itself changes later, in
 * erp_stock_apply_pending().
 *
 * A second write of the same line under the same kind is not an error and
 * changes nothing: the move is already there.
 *
 * @param array $move  product_id, invoice_id, line_id, kind, direction (in|out),
 *                     quantity, cost_total (base kurus; null for a sale, which
 *                     leaves at the average), stock_wanted, reverses_id,
 *                     doc_date, created_by, purchase_side (bool)
 * @return bool  false on a database error
 */
function erp_stock_record($move)
{
    $product_id = (int) $move['product_id'];
    $quantity = (float) $move['quantity'];

    if (($product_id <= 0) || ($quantity <= 0)) {
        return true;
    }

    $product = db_item("SELECT id, inventory, inventory_quantity FROM products WHERE id = '" . $product_id . "' LIMIT 1");

    if (!is_array($product)) {
        return true;
    }

    $in = ((string) $move['direction'] === 'in');
    $purchase_side = !empty($move['purchase_side']);

    // Only the purchase side keeps a cost row; a sale reads the average and
    // leaves it, so a product sold but never bought gets no row of zeros.
    if ($purchase_side) {
        if (erp_query("INSERT IGNORE INTO erp_product_costs (product_id, updated_at) VALUES ('" . $product_id . "', '" . time() . "')") === false) {
            return false;
        }

        $cost = db_item("SELECT product_id, avg_cost, costed_quantity, last_cost, last_cost_date, last_move_id
            FROM erp_product_costs WHERE product_id = '" . $product_id . "' LIMIT 1 FOR UPDATE");

        if (!is_array($cost)) {
            return false;
        }

        // Read before this move is written: the moves still waiting to be
        // counted are part of the quantity on hand, this one is not yet.
        $basis = erp_stock_basis($product, $cost);
    } else {
        $cost = db_item("SELECT avg_cost FROM erp_product_costs WHERE product_id = '" . $product_id . "' LIMIT 1");
        $basis = 0.0;
    }

    $cost_total = ($move['cost_total'] === null)
        ? (int) round((is_array($cost) ? (int) $cost['avg_cost'] : 0) * $quantity)
        : (int) $move['cost_total'];
    $unit_cost = (int) round($cost_total / $quantity);
    $wanted = !empty($move['stock_wanted']) ? 1 : 0;
    $doc_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($move['doc_date'] ?? '')) ? (string) $move['doc_date'] : date('Y-m-d');

    $ok = erp_query("INSERT IGNORE INTO erp_stock_moves SET
            product_id = '" . $product_id . "',
            invoice_id = '" . (int) $move['invoice_id'] . "',
            line_id = '" . (int) $move['line_id'] . "',
            kind = '" . escape((string) $move['kind']) . "',
            direction = '" . ($in ? 'in' : 'out') . "',
            quantity = '" . number_format($quantity, 4, '.', '') . "',
            cost_total = '" . $cost_total . "',
            unit_cost = '" . $unit_cost . "',
            stock_wanted = '" . $wanted . "',
            stock_applied = 0,
            reverses_id = '" . (int) ($move['reverses_id'] ?? 0) . "',
            doc_date = '" . escape($doc_date) . "',
            created_by = '" . (int) ($move['created_by'] ?? 0) . "',
            created_at = '" . time() . "'");

    if ($ok === false) {
        return false;
    }

    if (mysqli_affected_rows(db::$con) !== 1) {
        return true;
    }

    $move_id = (int) mysqli_insert_id(db::$con);

    if (!$purchase_side) {
        return true;
    }

    // The average over what is on hand. Taken out of a quantity that goes
    // to nothing, it is left as it was: there is nothing left to average.
    $average = (int) $cost['avg_cost'];

    if ($in) {
        $after = $basis + $quantity;

        // Goods counted in before any document gave them a cost take the
        // cost of the first purchase, rather than being averaged in at nothing.
        if (($average <= 0) && ((int) $cost['last_move_id'] === 0)) {
            $average = $unit_cost;
        } else {
            $average = ($after > 0) ? (int) round((($basis * $average) + $cost_total) / $after) : $average;
        }
    } else {
        $after = $basis - $quantity;
        if ($after > 0) {
            $average = max(0, (int) round((($basis * $average) - $cost_total) / $after));
        }
    }

    $sets = "avg_cost = '" . $average . "',
            costed_quantity = '" . number_format(max(0, $after), 4, '.', '') . "',";

    // The last purchase price: a purchase dated on or after the one that set
    // it, or - once that one is cancelled - the latest purchase still standing.
    if (((string) $move['kind'] === 'purchase') && ($doc_date >= (string) $cost['last_cost_date'])) {
        $sets .= "
            last_cost = '" . $unit_cost . "',
            last_cost_date = '" . escape($doc_date) . "',
            last_move_id = '" . $move_id . "',";
    } elseif (((int) ($move['reverses_id'] ?? 0) > 0) && ((int) $move['reverses_id'] === (int) $cost['last_move_id'])) {
        $last = erp_stock_last_purchase($product_id);
        $sets .= "
            last_cost = '" . (int) $last['last_cost'] . "',
            last_cost_date = '" . escape($last['last_cost_date']) . "',
            last_move_id = '" . (int) $last['last_move_id'] . "',";
    }

    return (erp_query("UPDATE erp_product_costs SET " . $sets . "
            updated_at = '" . time() . "'
        WHERE product_id = '" . $product_id . "'") !== false);
}

/**
 * The moves an issued invoice makes: goods in on a purchase invoice, out on a
 * sales invoice. The moves of an invoice raised from an order record the cost
 * and change no count (the order moved the stock); a line with no product
 * behind it makes none. Runs inside the caller's transaction.
 *
 * @param int $invoice_id
 * @param int $created_by
 * @return bool  false on a database error
 */
function erp_stock_post_invoice($invoice_id, $created_by = 0)
{
    if (!erp_stock_ready()) {
        return true;
    }

    $invoice = db_item("SELECT id, direction, doc_type, order_id, currency, exchange_rate, issue_date
        FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if (!is_array($invoice) || ((string) $invoice['doc_type'] !== 'invoice')) {
        return true;
    }

    $purchase = ((string) $invoice['direction'] === 'purchase');
    $from_order = ((int) $invoice['order_id'] > 0);
    $foreign = (strtoupper((string) $invoice['currency']) !== erp_base_currency());

    foreach ((array) db_items("SELECT i.id, i.product_id, i.quantity, i.line_total, i.discount_amount, p.inventory
        FROM erp_invoice_items i
        INNER JOIN products p ON p.id = i.product_id
        WHERE i.invoice_id = '" . (int) $invoice['id'] . "' AND i.product_id > 0 AND i.quantity > 0
        ORDER BY i.line_no ASC, i.id ASC") as $line) {

        $net = (int) $line['line_total'] - (int) $line['discount_amount'];

        $ok = erp_stock_record(array(
            'product_id' => (int) $line['product_id'],
            'invoice_id' => (int) $invoice['id'],
            'line_id' => (int) $line['id'],
            'kind' => $purchase ? 'purchase' : 'sale',
            'direction' => $purchase ? 'in' : 'out',
            'quantity' => (float) $line['quantity'],
            // What the goods cost the store, in its own currency and without
            // the VAT it takes back; a sale leaves at the average.
            'cost_total' => $purchase ? ($foreign ? erp_to_base($net, (float) $invoice['exchange_rate']) : $net) : null,
            'stock_wanted' => !$from_order && erp_stock_documents_on() && ((int) $line['inventory'] === 1) && erp_stock_whole($line['quantity']),
            'doc_date' => (string) $invoice['issue_date'],
            'created_by' => (int) $created_by,
            'purchase_side' => $purchase,
        ));

        if (!$ok) {
            return false;
        }
    }

    return true;
}

/**
 * The moves a return makes: each returned line takes back its share of the
 * move the invoice line made, at that move's cost, and changes the stock only
 * if that move did. A line of an invoice that made no move (issued before
 * stock was kept) makes none when it comes back. Runs inside the caller's
 * transaction.
 *
 * @param int $return_id
 * @param int $created_by
 * @return bool  false on a database error
 */
function erp_stock_post_return($return_id, $created_by = 0)
{
    if (!erp_stock_ready()) {
        return true;
    }

    $return = db_item("SELECT id, issue_date FROM erp_invoices WHERE id = '" . (int) $return_id . "' LIMIT 1");

    if (!is_array($return)) {
        return true;
    }

    foreach ((array) db_items("SELECT id, parent_line_id, quantity FROM erp_invoice_items
        WHERE invoice_id = '" . (int) $return['id'] . "' AND parent_line_id > 0 AND quantity > 0") as $line) {

        $parent = db_item("SELECT id, product_id, kind, quantity, cost_total, stock_wanted FROM erp_stock_moves
            WHERE line_id = '" . (int) $line['parent_line_id'] . "' AND kind IN ('purchase', 'sale')
            LIMIT 1");

        if (!is_array($parent) || ((float) $parent['quantity'] <= 0)) {
            continue;
        }

        $purchase = ((string) $parent['kind'] === 'purchase');
        $quantity = (float) $line['quantity'];

        $ok = erp_stock_record(array(
            'product_id' => (int) $parent['product_id'],
            'invoice_id' => (int) $return['id'],
            'line_id' => (int) $line['id'],
            'kind' => $purchase ? 'purchase_return' : 'sales_return',
            'direction' => $purchase ? 'out' : 'in',
            'quantity' => $quantity,
            'cost_total' => (int) round((int) $parent['cost_total'] * $quantity / (float) $parent['quantity']),
            'stock_wanted' => ((int) $parent['stock_wanted'] === 1) && erp_stock_whole($quantity),
            'doc_date' => (string) $return['issue_date'],
            'created_by' => (int) $created_by,
            'purchase_side' => $purchase,
        ));

        if (!$ok) {
            return false;
        }
    }

    return true;
}

/**
 * The moves a cancellation makes: every move the document made, turned
 * round, at the same quantity and cost. Runs inside the caller's transaction.
 *
 * @param int $invoice_id  The invoice or return being cancelled
 * @param int $created_by
 * @return bool  false on a database error
 */
function erp_stock_post_cancel($invoice_id, $created_by = 0)
{
    if (!erp_stock_ready()) {
        return true;
    }

    foreach ((array) db_items("SELECT m.* FROM erp_stock_moves m
        WHERE m.invoice_id = '" . (int) $invoice_id . "' AND m.kind <> 'cancel'
          AND NOT EXISTS (SELECT 1 FROM erp_stock_moves r WHERE r.reverses_id = m.id)
        ORDER BY m.id ASC") as $move) {

        $ok = erp_stock_record(array(
            'product_id' => (int) $move['product_id'],
            'invoice_id' => (int) $move['invoice_id'],
            'line_id' => (int) $move['line_id'],
            'kind' => 'cancel',
            'direction' => ((string) $move['direction'] === 'in') ? 'out' : 'in',
            'quantity' => (float) $move['quantity'],
            'cost_total' => (int) $move['cost_total'],
            'stock_wanted' => ((int) $move['stock_wanted'] === 1),
            'reverses_id' => (int) $move['id'],
            'doc_date' => date('Y-m-d'),
            'created_by' => (int) $created_by,
            'purchase_side' => in_array((string) $move['kind'], array('purchase', 'purchase_return'), true),
        ));

        if (!$ok) {
            return false;
        }
    }

    return true;
}

/**
 * Change the stock counts the committed moves ask for. Does nothing inside a
 * transaction: products is MyISAM, and a count changed there would survive
 * a rollback of the document it belongs to.
 *
 * Each move is claimed before its count is changed, so two requests cannot
 * apply the same move. The count moves in the statement itself and stops at
 * zero, the way the API writes stock; a product that has stopped tracking
 * stock since is left alone and the move says so (stock_applied 2).
 *
 * @param int $limit
 * @return int  Moves applied
 */
function erp_stock_apply_pending($limit = 200)
{
    if (!erp_stock_ready() || (erp_tx_depth() > 0)) {
        return 0;
    }

    $applied = 0;
    $touched = array();

    foreach ((array) db_items("SELECT id, product_id, direction, quantity FROM erp_stock_moves
        WHERE stock_wanted = 1 AND stock_applied = 0
        ORDER BY id ASC
        LIMIT " . max(1, (int) $limit)) as $move) {

        if ((erp_query("UPDATE erp_stock_moves SET stock_applied = 1 WHERE id = '" . (int) $move['id'] . "' AND stock_applied = 0") === false)
            || (mysqli_affected_rows(db::$con) !== 1)) {
            continue;
        }

        if ((int) db_value("SELECT inventory FROM products WHERE id = '" . (int) $move['product_id'] . "' LIMIT 1") !== 1) {
            erp_query("UPDATE erp_stock_moves SET stock_applied = 2 WHERE id = '" . (int) $move['id'] . "'");
            continue;
        }

        $delta = (int) round((float) $move['quantity']) * (((string) $move['direction'] === 'in') ? 1 : -1);
        $quantity_sql = "GREATEST(0, CAST(inventory_quantity AS SIGNED) + (" . $delta . "))";

        // out_of_stock first: MySQL applies the assignments left to right, and
        // the flag has to read the quantity as it stands.
        erp_query("UPDATE products
            SET out_of_stock = IF((" . $quantity_sql . ") <= 0, '1', '0'),
                inventory_quantity = " . $quantity_sql . ",
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . (int) $move['product_id'] . "' AND inventory = '1'
            LIMIT 1");

        $stock = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . (int) $move['product_id'] . "' LIMIT 1");
        erp_query("UPDATE erp_stock_moves SET stock_after = '" . $stock . "' WHERE id = '" . (int) $move['id'] . "'");

        $applied++;
        $touched[(int) $move['product_id']] = $stock;
    }

    // The same announcements the stock API makes, so a marketplace and a
    // webhook subscriber hear of a purchase the way they hear of an API write.
    foreach ($touched as $product_id => $stock) {
        if (function_exists('pg_marketplace_product_changed')) {
            pg_marketplace_product_changed((int) $product_id);
        }

        if (function_exists('pg_announce')) {
            pg_announce('inventory.changed', array(
                'product_id' => (int) $product_id,
                'quantity' => (int) $stock,
                'out_of_stock' => ((int) $stock <= 0),
            ));
        }
    }

    return $applied;
}

/**
 * The moves a document made, with the product each one moved.
 *
 * @param int $invoice_id
 * @return array
 */
function erp_stock_document_moves($invoice_id)
{
    if (!erp_stock_ready()) {
        return array();
    }

    return (array) db_items("SELECT m.*, p.name AS product_sku, p.short_description AS product_name, p.inventory
        FROM erp_stock_moves m
        LEFT JOIN products p ON p.id = m.product_id
        WHERE m.invoice_id = '" . (int) $invoice_id . "'
        ORDER BY m.id ASC");
}

/**
 * What a move did to the count, in words for the screens.
 *
 * @param array $move  erp_stock_moves row
 * @return string
 */
function erp_stock_effect_text($move)
{
    $sign = ((string) $move['direction'] === 'in') ? '+' : '−';
    $quantity = $sign . erp_quantity_text($move['quantity']);

    if ((int) $move['stock_wanted'] !== 1) {
        return lang(array('string' => '{var:1}, stock count not changed', 'vars' => $quantity));
    }

    switch ((int) $move['stock_applied']) {
        case 1:
            return lang(array('string' => '{var:1}, stock now {var:2}', 'vars' => array($quantity, erp_quantity_text($move['stock_after']))));
        case 2:
            return lang(array('string' => '{var:1}, the product no longer tracks stock', 'vars' => $quantity));
    }

    return lang(array('string' => '{var:1}, waiting to be counted', 'vars' => $quantity));
}

/**
 * The products that have a cost, with their stock, for the stock screen.
 *
 * @return array
 */
function erp_stock_costed_products()
{
    if (!erp_stock_ready()) {
        return array();
    }

    return (array) db_items("SELECT c.product_id, c.avg_cost, c.costed_quantity, c.last_cost, c.last_cost_date,
            p.name AS product_sku, p.short_description AS product_name, p.inventory, p.inventory_quantity, p.enabled
        FROM erp_product_costs c
        INNER JOIN products p ON p.id = c.product_id
        ORDER BY COALESCE(NULLIF(p.short_description, ''), p.name) ASC, p.id ASC");
}

/**
 * How many products count stock but have no cost yet: nothing has been bought
 * for them on a purchase invoice.
 *
 * @return int
 */
function erp_stock_uncosted_count()
{
    if (!erp_stock_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM products p
        LEFT JOIN erp_product_costs c ON c.product_id = p.id
        WHERE p.inventory = '1' AND c.product_id IS NULL");
}

/**
 * The latest movements, newest first, with their product and document.
 *
 * @param int $product_id  0 for every product
 * @param int $limit
 * @return array
 */
function erp_stock_recent_moves($product_id = 0, $limit = 200)
{
    if (!erp_stock_ready()) {
        return array();
    }

    return (array) db_items("SELECT m.*, p.name AS product_sku, p.short_description AS product_name, p.inventory,
            i.full_number, i.direction AS document_direction, i.doc_type AS document_type
        FROM erp_stock_moves m
        LEFT JOIN products p ON p.id = m.product_id
        LEFT JOIN erp_invoices i ON i.id = m.invoice_id
        " . (((int) $product_id > 0) ? "WHERE m.product_id = '" . (int) $product_id . "'" : "") . "
        ORDER BY m.id DESC
        LIMIT " . max(1, (int) $limit));
}
