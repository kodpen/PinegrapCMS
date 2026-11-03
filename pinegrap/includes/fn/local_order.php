<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A local (counter) order completed from an incomplete order: the checks, the
 * claim, the number, the figures, the stock, order.created and, with the ERP
 * on, the invoice and the money. Nothing here reads or writes the session,
 * so the counter screen (includes/local_sale.php, add_order.php) and the
 * external API's POST /orders run the same steps and meet the same
 * refusals. The cart readers and the ERP helpers it uses live in
 * includes/local_sale.php, loaded with it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/local_sale.php');

/**
 * Why a line cannot be sold as it stands, by line id: a product switched off,
 * or more units of a stock-tracked product than its stock holds. The units of
 * a product on several lines are counted together, and every one of those
 * lines is named.
 *
 * @param array $lines  from local_sale_lines(): id, product_id, quantity,
 *                      enabled, inventory, inventory_quantity
 * @return array  line id => message
 */
function pg_local_order_line_errors($lines)
{
    $line_errors = array();
    $wanted = array();

    foreach ($lines as $line) {
        if ((int) $line['enabled'] !== 1) {
            $line_errors[(int) $line['id']] = lang('This product has been switched off; remove it from the cart.');
        } elseif ((int) $line['inventory'] === 1) {
            $wanted[(int) $line['product_id']] = ($wanted[(int) $line['product_id']] ?? 0) + (int) $line['quantity'];
        }
    }

    foreach ($lines as $line) {
        $product_id = (int) $line['product_id'];
        if (isset($wanted[$product_id]) && !isset($line_errors[(int) $line['id']])) {
            $stock_error = local_sale_stock_error($line, $wanted[$product_id], true);
            if ($stock_error !== '') {
                $line_errors[(int) $line['id']] = $stock_error;
            }
        }
    }

    return $line_errors;
}

/**
 * The way of paying a sale is recorded with: one of local_sale_methods(),
 * anything else 'later' (no money recorded).
 *
 * @param mixed $raw
 * @return string
 */
function pg_local_order_pay_method($raw)
{
    $method = is_string($raw) ? $raw : '';

    return isset(local_sale_methods()[$method]) ? $method : 'later';
}

/**
 * Everything a completion checks before it writes: an order with lines, the
 * stock (another till may have sold the last units since they were added),
 * cash received short of the total, and a split payment's parts. A dry run
 * stops after it.
 *
 * @param array $user
 * @param int   $order_id  the incomplete order, 0 while the lines are not written yet
 * @param array $lines     local_sale_lines($order_id), or pg_local_order_lines() of the rows to be written
 * @param array $input     pay_method, pay_till, received (kuruş), pay_parts
 * @return array  local_sale_ok() with split (the parts, or null), or a refusal
 */
function pg_local_order_check($user, $order_id, $lines, $input)
{
    // No order holds no lines (local_sale_lines() of 0 is empty); the API
    // checks lines it has not written yet.
    if (empty($lines)) {
        return local_sale_fail(lang('The cart is empty.'), array('level' => 'warning'));
    }

    // 1. Stock, again.
    $line_errors = pg_local_order_line_errors($lines);

    if (!empty($line_errors)) {
        return local_sale_fail(lang('Some lines no longer fit the stock. Correct them and complete the sale again.'), array('line_errors' => $line_errors));
    }

    // Cash received short of the total is refused before the sale is
    // claimed: the receipt is written for the whole total, so the till would
    // record money that is not in the drawer. An empty box is the whole total.
    $pay_method_in = (string) ($input['pay_method'] ?? '');
    $total = (int) local_sale_totals($lines)['gross'];
    $short = local_sale_cash_short($pay_method_in, (int) ($input['received'] ?? 0), $total);

    if ($short > 0) {
        return local_sale_fail(lang(array('string' => 'The amount received is {var:1} short of the total. Correct it or split the payment.', 'vars' => array(local_sale_money_text($short)))), array('field' => 'received', 'level' => 'warning'));
    }

    // A split payment is checked before the sale is claimed: a part that
    // could not be recorded is put right on the screen, not after the fact.
    $split = null;

    if ((string) ($input['pay_method'] ?? '') === 'split') {
        if (!local_sale_erp_on() || !local_sale_can_cash($user)) {
            return local_sale_fail(lang('A split payment is recorded in the tills, which needs the ERP cash right.'), array('level' => 'warning'));
        }

        $split = local_sale_split_parts($input['pay_parts'] ?? '', $total);

        if ($split['error'] !== '') {
            return local_sale_fail($split['error'], array('level' => 'warning'));
        }
    }

    return local_sale_ok('', array('split' => $split));
}

/**
 * Complete an incomplete order as a local sale.
 *
 * In order: pg_local_order_check(), the order is claimed with a conditional
 * update so a double click, a second tab or a retried API call cannot
 * complete it twice, it is numbered, the stock goes down, order.created is
 * announced, and - with the ERP on - the invoice is issued and the money
 * recorded.
 *
 * Once the order is claimed it stays completed: a failed invoice or receipt
 * comes back as a warning on a completed sale, never as a sale undone.
 *
 * @param array      $user      the operator (id, role, ERP rights)
 * @param int        $order_id  the incomplete order holding the lines
 * @param array|null $customer  contact_id, account_id, label; null for a walk-in
 * @param array      $input     pay_method, pay_till, received (kuruş, display only), pay_parts
 * @return array  local_sale_ok() with result, or a refusal; code 'completed'
 *                when the order was no longer incomplete
 */
function pg_local_order_complete($user, $order_id, $customer, $input)
{
    $order_id = (int) $order_id;
    $lines = local_sale_lines($order_id);
    $checked = pg_local_order_check($user, $order_id, $lines, $input);

    if (!$checked['ok']) {
        return $checked;
    }

    $split = $checked['split'];

    // 2. Claim the order. Only one request can move it out of 'incomplete'.
    $now = time();
    db("UPDATE orders SET status = 'complete', type = 'local', last_modified_timestamp = '" . $now . "'
        WHERE id = '" . $order_id . "' AND status = 'incomplete'");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return local_sale_fail(lang('This sale has already been completed.'), array('code' => 'completed', 'level' => 'warning'));
    }

    // 3. Number it.
    $order_number = local_sale_next_order_number();

    if ($order_number === false) {
        db("UPDATE orders SET status = 'incomplete' WHERE id = '" . $order_id . "'");
        return local_sale_fail(lang('The order number could not be taken. Try again.'));
    }

    // 4. The figures. tax_total is the line's VAT; tax stays 0 (see
    // update_order_item_taxes()); tax_rate the rate it was taken at (11.0.59).
    $totals = local_sale_totals($lines);
    $with_rate = pg_schema_has('order_items', 'tax_rate');

    foreach ($lines as $line) {
        db("UPDATE order_items SET tax_total = '" . (int) $line['tax'] . "', tax = '0'"
            . ($with_rate ? ", tax_rate = '" . number_format((float) $line['rate'], 3, '.', '') . "'" : "")
            . " WHERE id = '" . (int) $line['id'] . "'");
    }

    // Who the sale is for: the picked contact and account, or nobody (a
    // walk-in), billed to the walk-in account while the ERP is on.
    $erp = local_sale_erp_on();
    $customer = is_array($customer) ? $customer : null;
    $contact_id = $customer ? (int) ($customer['contact_id'] ?? 0) : 0;
    $account_id = $customer ? (int) ($customer['account_id'] ?? 0) : 0;

    if ($erp && ($account_id <= 0) && ($contact_id <= 0)) {
        $account_id = local_sale_walkin_account((int) $user['id']);
    }

    $sql_account = waf_table_has_column('orders', 'erp_account_id') ? "erp_account_id = '" . (int) $account_id . "'," : '';

    // The buyer's name on the order, so the order list, its name filter and
    // search find the sale: the picked contact's card, else the picked
    // account's title as the company. A walk-in sale keeps no name.
    $billing = local_sale_billing_name($customer);

    db("UPDATE orders SET
            order_number = '" . (int) $order_number . "',
            subtotal = '" . (int) $totals['net'] . "',
            tax = '" . (int) $totals['tax'] . "',
            total = '" . (int) $totals['gross'] . "',
            contact_id = '" . (int) $contact_id . "',
            " . $sql_account . "
            user_id = '" . (int) $user['id'] . "',
            billing_first_name = '" . e($billing['first_name']) . "',
            billing_last_name = '" . e($billing['last_name']) . "',
            billing_company = '" . e($billing['company']) . "',
            order_date = '" . $now . "',
            last_modified_timestamp = '" . $now . "',
            ip_address = IFNULL(INET_ATON('" . escape($_SERVER['REMOTE_ADDR'] ?? '') . "'), 0)
        WHERE id = '" . $order_id . "'");

    // A counter sale is handed over at the till: the recipient the cart
    // writer opened ('myself', no address) is dropped, so the order screen
    // draws no empty shipping card, date or tracking fields.
    db("UPDATE order_items SET ship_to_id = '0' WHERE order_id = '" . $order_id . "'");
    db("DELETE FROM ship_tos WHERE order_id = '" . $order_id . "'");

    // 5. Stock goes down for tracked products. Each one then tells the
    //    outside the same way a web sale does: the stamp for updated_since
    //    listings, inventory.changed (stock.low on the crossing) and the
    //    marketplace queue. The first line of a product reads its stock
    //    again right before the decrement, so another till's sale since the
    //    cart was read is not counted from the old level; later lines carry
    //    on from what the line above left ($stock_left, only set for a
    //    product that tracks stock).
    $stock_left = array();
    $bundle_ids = pg_bundle_ids_among(array_column($lines, 'product_id'));

    foreach ($lines as $line) {
        $product_id = (int) $line['product_id'];

        // A bundle takes its components' stock (includes/fn/bundles.php).
        if (isset($bundle_ids[$product_id])) {
            pg_bundle_take_stock($line['id'], $product_id, $line['quantity'], 'local_sale', $stock_left);
            continue;
        }

        $tracked = ((int) $line['inventory'] === 1);
        $stock_before = (int) $line['inventory_quantity'];

        if (isset($stock_left[$product_id])) {
            $tracked = true;
            $stock_before = $stock_left[$product_id];
        } else {
            $stock_now = db_item("SELECT inventory, inventory_quantity FROM products WHERE id = '" . $product_id . "' LIMIT 1");

            if ($stock_now) {
                $tracked = ((int) $stock_now['inventory'] === 1);
                $stock_before = (int) $stock_now['inventory_quantity'];
            }
        }

        if ($tracked && ($stock_before > 0)) {
            db("UPDATE products SET inventory_quantity = (inventory_quantity - '" . (int) $line['quantity'] . "') WHERE id = '" . $product_id . "'");

            // The flag comes from the stock left, as in the checkout: one
            // line's quantity against the stock read before the loop missed
            // a product on two lines (2 + 3 of 5). The column is unsigned
            // and the session sql_mode is not strict, so selling through
            // the stock leaves 0; the out_of_stock guard raises the bell
            // once per product.
            db("UPDATE products SET out_of_stock = '1', out_of_stock_timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . $product_id . "' AND inventory_quantity <= 0 AND out_of_stock != '1'");

            if (mysqli_affected_rows(db::$con) > 0) {
                create_notification(array(
                    'action' => 'out_stock',
                    'type' => 'warning',
                    'title' => $line['name'] . ' - ' . $line['short_description'],
                    'product_id' => $product_id));
            }

            $stock_left[$product_id] = pg_product_stock_changed($product_id, $stock_before, null, 'local_sale');
        }
    }

    // 6. Tell whoever is subscribed, the same event a web order raises.
    if (function_exists('pg_announce')) {
        pg_announce('order.created', array('id' => $order_id, 'order_number' => (int) $order_number));
    }

    $pay_method = pg_local_order_pay_method($input['pay_method'] ?? 'cash');
    $pay_till = (int) ($input['pay_till'] ?? 0);

    $result = array(
        'order_id' => $order_id,
        'order_number' => (int) $order_number,
        'total' => (int) $totals['gross'],
        'items' => (int) $totals['count'],
        'method' => $pay_method,
        'till_name' => '',
        'received' => max(0, (int) ($input['received'] ?? 0)),
        'who' => $customer ? (string) ($customer['label'] ?? '') : '',
        'invoice' => null,
        'receipt' => null,
        'receipts' => array(),
        'rest' => 0,
        'warnings' => array(),
        'retry_invoice' => false,
    );

    if (!$erp) {
        return local_sale_ok('', array('result' => $result));
    }

    // 7. The invoice, for every counter sale.
    $made = erp_invoice_from_order($order_id, array('created_by' => (int) $user['id']));

    if (empty($made['success'])) {
        $result['warnings'][] = lang(array('string' => 'The sale is complete, but the invoice was not issued: {var:1}', 'vars' => array((string) $made['error'])));
        $result['retry_invoice'] = true;
        return local_sale_ok('', array('result' => $result, 'level' => 'warning'));
    }

    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . (int) $made['invoice_id'] . "' LIMIT 1");
    $result['invoice'] = array(
        'id' => (int) $made['invoice_id'],
        'number' => (string) $made['full_number'],
        'type' => local_sale_doc_type_label(is_array($invoice) ? (int) $invoice['account_id'] : 0),
    );

    // What the e-document still needs is the ERP manager's to put right; a
    // cashier without the ERP right is not shown a card they cannot open.
    if (is_array($invoice) && local_sale_can_erp($user) && function_exists('erp_edoc_invoice_party_missing')) {
        $missing = erp_edoc_invoice_party_missing($invoice);
        if (!empty($missing['fields'])) {
            $result['warnings'][] = lang(array('string' => 'The invoice was issued, but it cannot go out as an e-document until the account has: {var:1}.', 'vars' => array(implode(', ', $missing['fields']))));
        }
    }

    // 8. The money, when it was taken now and this operator may record it.
    // A split payment is one receipt per part, each closing its share of the
    // invoice; what the parts leave stays open on the account.
    if (($pay_method === 'split') && is_array($invoice) && is_array($split) && ((int) $invoice['grand_total'] > 0)) {
        $paid = 0;

        foreach ($split['parts'] as $number => $part) {
            $till = db_item("SELECT id, name FROM erp_cash_accounts WHERE id = '" . (int) $part['till'] . "' AND is_active = 1 LIMIT 1");
            $posted = is_array($till) ? erp_post_receipt(array(
                'direction' => 'collection',
                'account_id' => (int) $invoice['account_id'],
                'cash_account_id' => (int) $till['id'],
                'amount' => (int) $part['amount'],
                'doc_date' => date('Y-m-d'),
                'currency' => (string) $invoice['currency'],
                'payment_method' => (string) $part['method'],
                'description' => lang(array('string' => 'Counter sale #{var:1}, part {var:2}', 'vars' => array($order_number, $number + 1))),
                'invoice_id' => (int) $invoice['id'],
                'created_by' => (int) $user['id'],
            )) : array('success' => false, 'error' => lang('Choose a till or bank account for each part.'));

            if (empty($posted['success'])) {
                $result['warnings'][] = lang(array('string' => 'Part {var:1} of the payment was not recorded: {var:2}', 'vars' => array($number + 1, (string) $posted['error'])));
                continue;
            }

            $paid += (int) $part['amount'];
            $result['receipts'][] = array(
                'id' => (int) $posted['cash_id'],
                'till' => (string) $till['name'],
                'method' => (string) $part['method'],
                'amount' => (int) $part['amount'],
            );
        }

        $result['rest'] = max(0, (int) $invoice['grand_total'] - $paid);
    } elseif (($pay_method !== 'later') && ($pay_method !== 'split') && is_array($invoice) && ((int) $invoice['grand_total'] > 0)) {
        if (!local_sale_can_cash($user)) {
            $result['warnings'][] = lang('The payment was not recorded: you do not hold the ERP cash right. The invoice stays open for the ERP manager.');
        } else {
            $till = ($pay_till > 0) ? db_item("SELECT id, name FROM erp_cash_accounts WHERE id = '" . $pay_till . "' AND is_active = 1 LIMIT 1") : null;

            if (!is_array($till)) {
                $result['warnings'][] = lang('The payment was not recorded: choose a till or bank account. The invoice stays open.');
            } else {
                $posted = erp_post_receipt(array(
                    'direction' => 'collection',
                    'account_id' => (int) $invoice['account_id'],
                    'cash_account_id' => (int) $till['id'],
                    'amount' => (int) $invoice['grand_total'],
                    'doc_date' => date('Y-m-d'),
                    'currency' => (string) $invoice['currency'],
                    'payment_method' => $pay_method,
                    'description' => lang(array('string' => 'Counter sale #{var:1}', 'vars' => array($order_number))),
                    'invoice_id' => (int) $invoice['id'],
                    'created_by' => (int) $user['id'],
                ));

                if (empty($posted['success'])) {
                    $result['warnings'][] = lang(array('string' => 'The payment was not recorded: {var:1}', 'vars' => array((string) $posted['error'])));
                } else {
                    $result['till_name'] = (string) $till['name'];
                    $result['receipt'] = array('id' => (int) $posted['cash_id'], 'till' => (string) $till['name']);
                }
            }
        }
    }

    return local_sale_ok('', array('result' => $result, 'level' => empty($result['warnings']) ? 'success' : 'warning'));
}
