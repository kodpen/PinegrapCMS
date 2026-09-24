<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The counter sale behind add_order.php.
 *
 * Every action the screen takes (add a product, change a quantity, pick the
 * customer, complete the sale) is a function here that returns a result
 * array, and every part of the screen that changes (cart rows, summary,
 * customer box, the card shown once the sale is done) is a function here
 * that returns HTML. The first render and the JSON answers call the same
 * functions, so the page drawn on load and the page redrawn after a scan
 * cannot drift apart.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------
   Who may do what
   --------------------------------------------------------------------- */

/**
 * Whether the ERP takes part in the sale: switched on and its columns in
 * place. Loads the module the first time it answers yes.
 *
 * @return bool
 */
function local_sale_erp_on()
{
    static $on = null;

    if ($on === null) {
        $on = defined('ERP_ENABLED') && ERP_ENABLED
            && waf_table_has_column('erp_accounts', 'contact_id')
            && waf_table_has_column('orders', 'erp_invoice_id');

        if ($on) {
            require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
        }
    }

    return $on;
}

/**
 * Whether this operator sees the ERP side of the sale: accounts, balances,
 * links to the documents, the quick account form.
 *
 * Invoicing itself does not depend on it. Every counter sale is invoiced
 * while the ERP is on, whoever rings it up; how the buyer's details are
 * completed afterwards is the ERP manager's business, not the cashier's.
 *
 * @param array $user
 * @return bool
 */
function local_sale_can_erp($user)
{
    // The ERP's read-only right (the accountant's) raises no invoice at the till.
    return local_sale_erp_on() && (((int) $user['role'] < 3) || (!empty($user['manage_erp']) && empty($user['manage_erp_readonly'])));
}

/**
 * Whether this operator may record the money: the ERP right and the cash
 * right under it, the same pair validate_erp_access($user, 'cash') asks for.
 *
 * @param array $user
 * @return bool
 */
function local_sale_can_cash($user)
{
    return local_sale_can_erp($user) && (((int) $user['role'] < 3) || !empty($user['manage_erp_cash']));
}

/* ---------------------------------------------------------------------
   Result arrays
   --------------------------------------------------------------------- */

function local_sale_ok($message = '', $extra = array())
{
    return array_merge(array(
        'ok' => true,
        'code' => '',
        'message' => (string) $message,
        'level' => 'success',
        'field' => '',
        'line_id' => 0,
        'line_errors' => array(),
        'customer_changed' => false,
        'result' => null,
    ), $extra);
}

function local_sale_fail($message, $extra = array())
{
    return array_merge(local_sale_ok($message, array('ok' => false, 'level' => 'danger')), $extra);
}

/* ---------------------------------------------------------------------
   The cart
   --------------------------------------------------------------------- */

/**
 * The order the cart lives in, while it is still a cart. An order that was
 * completed (in another tab, say) is dropped from the session.
 *
 * @return int
 */
function local_sale_order_id()
{
    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);

    if ($order_id <= 0) {
        return 0;
    }

    if ((string) db_value("SELECT status FROM orders WHERE id = '" . $order_id . "' LIMIT 1") !== 'incomplete') {
        unset($_SESSION['ecommerce']['order_id']);
        return 0;
    }

    return $order_id;
}

/**
 * A caption that names the tax, in the word the ERP settings give it
 * (erp_tax_label()); the sale screen's own VAT captions when no name is set
 * or the ERP is off.
 *
 * @param string $which  tax | amount | included | excluding | subtotal_excl | added
 * @return string
 */
function local_sale_tax_label($which = 'tax')
{
    if (local_sale_erp_on() && function_exists('erp_tax_name') && (erp_tax_name() !== '')) {
        return erp_tax_label($which);
    }

    switch ($which) {
        case 'amount':
            return lang('VAT amount');
        case 'included':
            return lang('VAT included');
        case 'excluding':
            return lang('Excluding VAT');
        case 'subtotal_excl':
            return lang('Subtotal (excl. VAT)');
        case 'added':
            return lang('VAT added at the total');
    }

    return lang('VAT');
}

/**
 * Whether the till shows prices without tax, adding it at the total - the
 * way a register reads where sales tax is charged on top (config
 * local_sale_prices = 'net'). By default it shows them with VAT included,
 * the way shelf prices are written where VAT applies. The sale itself is the
 * same either way: product prices are kept without tax and the tax is worked
 * out on the line; only what the till view shows changes.
 *
 * @return bool
 */
function local_sale_prices_net()
{
    static $net = null;

    if ($net === null) {
        $net = function_exists('waf_table_has_column') && waf_table_has_column('config', 'local_sale_prices')
            && ((string) db_value("SELECT local_sale_prices FROM config LIMIT 1") === 'net');
    }

    return $net;
}

/**
 * The label beside the till view's prices: tax included, or not included.
 *
 * @return string
 */
function local_sale_till_price_label()
{
    return local_sale_tax_label(local_sale_prices_net() ? 'excluding' : 'included');
}

/**
 * A line's rate the way the panel writes percentages.
 *
 * @param float $rate
 * @return string
 */
function local_sale_percent($rate)
{
    if (local_sale_erp_on() && function_exists('erp_percent_text')) {
        return erp_percent_text($rate);
    }

    return '%' . local_sale_rate_text($rate);
}

/**
 * The VAT rate of one cart line, the way the checkout works it out for a
 * buyer standing in the store's own tax zone: the product's rate when it has
 * one, the zone's otherwise. 0 when the product is not taxable or the store
 * is in no tax zone.
 *
 * @param array $item  keys: taxable, tax_rate
 * @return float
 */
function local_sale_line_rate($item)
{
    static $zone_rate = null;

    if ($zone_rate === null) {
        // The store's zone, its state included where the zones are drawn by
        // state; the country's zone when the ERP is off. The ERP is asked
        // first so every request of a sale answers the same way.
        if (local_sale_erp_on() && function_exists('erp_store_zone_rate')) {
            $zone_rate = erp_store_zone_rate();
        } else {
            $zone_rate = function_exists('get_default_tax_rate') ? get_default_tax_rate() : false;
        }
    }

    if (empty($item['taxable']) || ($zone_rate === false)) {
        return 0;
    }

    return (float) get_effective_tax_rate($item['tax_rate'], $zone_rate);
}

/**
 * The VAT on one cart line, on the whole line (not per unit), rounded once.
 * erp_order_lines() works it out the same way, which is what keeps the order
 * total and the invoice total equal.
 *
 * @param array $item  keys: price, quantity, taxable, tax_rate
 * @return int  kuruş
 */
function local_sale_line_tax($item)
{
    return (int) round(local_sale_line_rate($item) / 100 * ((int) $item['price'] * (int) $item['quantity']));
}

/**
 * The cart's lines with their figures worked out.
 *
 * net / tax / gross are the line's; unit_gross is the price with VAT a shelf
 * label would carry, for display only.
 *
 * @param int $order_id
 * @return array
 */
function local_sale_lines($order_id)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return array();
    }

    $rows = db_items(
        "SELECT oi.id, oi.product_id, oi.quantity, oi.price, oi.added_by_offer,
                p.name, p.short_description, p.image_name, p.enabled,
                p.inventory, p.inventory_quantity, p.taxable, p.tax_rate
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = '" . $order_id . "' AND oi.saved_for_later = '0'
         ORDER BY oi.id"
    );

    $lines = array();

    foreach ((array) $rows as $row) {
        $rate = local_sale_line_rate($row);
        $net = (int) $row['price'] * (int) $row['quantity'];
        $tax = (int) round($rate / 100 * $net);

        $row['rate'] = $rate;
        $row['net'] = $net;
        $row['tax'] = $tax;
        $row['gross'] = $net + $tax;
        $row['unit_gross'] = (int) round((int) $row['price'] * (100 + $rate) / 100);
        // What the till view shows (local_sale_prices_net()).
        $row['unit_till'] = local_sale_prices_net() ? (int) $row['price'] : $row['unit_gross'];
        $row['amount_till'] = local_sale_prices_net() ? $net : $row['gross'];
        $lines[] = $row;
    }

    return $lines;
}

/**
 * @param array $lines  from local_sale_lines()
 * @return array  count, units, net, tax, gross, rates (rate => VAT)
 */
function local_sale_totals($lines)
{
    $totals = array('count' => 0, 'units' => 0, 'net' => 0, 'tax' => 0, 'gross' => 0, 'rates' => array());

    foreach ($lines as $line) {
        $totals['count']++;
        $totals['units'] += (int) $line['quantity'];
        $totals['net'] += (int) $line['net'];
        $totals['tax'] += (int) $line['tax'];
        $totals['gross'] += (int) $line['gross'];

        $key = local_sale_rate_text($line['rate']);
        $totals['rates'][$key] = ($totals['rates'][$key] ?? 0) + (int) $line['tax'];
    }

    krsort($totals['rates'], SORT_NUMERIC);

    return $totals;
}

/**
 * A rate as people write it: 20, 1, 0.5 - no trailing zeros.
 *
 * @param float $rate
 * @return string
 */
function local_sale_rate_text($rate)
{
    return rtrim(rtrim(number_format((float) $rate, 3, '.', ''), '0'), '.');
}

/**
 * Units of a product already in the cart. Lines added by an offer are left
 * out: the shop did not choose to sell those, the offer gave them.
 *
 * @param int $order_id
 * @param int $product_id
 * @param int $except_item_id  a line to leave out, the one being changed
 * @return int
 */
function local_sale_cart_qty($order_id, $product_id, $except_item_id = 0)
{
    if ((int) $order_id <= 0) {
        return 0;
    }

    return (int) db_value(
        "SELECT COALESCE(SUM(quantity), 0) FROM order_items
         WHERE order_id = '" . (int) $order_id . "'
           AND product_id = '" . (int) $product_id . "'
           AND added_by_offer = '0'
           AND id <> '" . (int) $except_item_id . "'"
    );
}

/**
 * Why this many units cannot be in the cart, or '' when they can.
 *
 * @param array $product    keys: inventory, inventory_quantity
 * @param int   $wanted     units the cart would hold
 * @param bool  $completing the sale is being completed: the line is already
 *                          in the cart, so the fix is a lower quantity
 * @return string
 */
function local_sale_stock_error($product, $wanted, $completing = false)
{
    if ((int) $product['inventory'] !== 1) {
        return '';
    }

    $stock = (int) $product['inventory_quantity'];

    if ($stock <= 0) {
        return lang('This product is out of stock.');
    }

    if ($wanted > $stock) {
        return $completing
            ? lang(array('string' => 'Only {var:1} in stock; lower the quantity.', 'vars' => array($stock)))
            : lang(array('string' => 'Only {var:1} in stock; no more can be added.', 'vars' => array($stock)));
    }

    return '';
}

/**
 * The product a scanned or typed code stands for.
 *
 * Tried in this order: the barcode table (when barcodes are on), the stock
 * code, and last a single product whose name or code contains the text - so
 * "silikon" followed by Enter adds the one silicone the shop carries, and
 * two matches ask the operator to pick.
 *
 * @param string $code
 * @return array  product => row|null, matches => int
 */
function local_sale_find_product($code)
{
    $code = trim((string) $code);
    $columns = "id, name, short_description, enabled, inventory, inventory_quantity";

    if ($code === '') {
        return array('product' => null, 'matches' => 0);
    }

    if (defined('BARCODE_ENABLED') && BARCODE_ENABLED) {
        $product_id = (int) db_value("SELECT product_id FROM product_barcodes WHERE barcode = '" . escape($code) . "' LIMIT 1");
        if ($product_id > 0) {
            $product = db_item("SELECT " . $columns . " FROM products WHERE id = '" . $product_id . "' LIMIT 1");
            if ($product) {
                return array('product' => $product, 'matches' => 1);
            }
        }
    }

    $product = db_item("SELECT " . $columns . " FROM products WHERE name = '" . escape($code) . "' AND enabled = '1' LIMIT 1");
    if ($product) {
        return array('product' => $product, 'matches' => 1);
    }

    $like = escape(escape_like(mb_substr($code, 0, 100)));
    $found = db_items(
        "SELECT " . $columns . " FROM products
         WHERE enabled = '1' AND (name LIKE '%" . $like . "%' OR short_description LIKE '%" . $like . "%')
         LIMIT 2"
    );

    if (is_array($found) && (count($found) === 1)) {
        return array('product' => $found[0], 'matches' => 1);
    }

    return array('product' => null, 'matches' => is_array($found) ? count($found) : 0);
}

/**
 * The name people know a product by: the short description, else the code.
 *
 * @param array $row
 * @return string
 */
function local_sale_product_label($row)
{
    $label = trim((string) ($row['short_description'] ?? ''));

    return ($label !== '') ? $label : (string) ($row['name'] ?? '');
}

/* ---------------------------------------------------------------------
   Actions
   --------------------------------------------------------------------- */

/**
 * Add a product by scanned/typed code or by id.
 *
 * @param string $code
 * @param int    $product_id
 * @param int    $quantity
 * @return array
 */
function local_sale_add_item($code, $product_id, $quantity)
{
    $quantity = max(1, min(9999, (int) $quantity));
    $code = trim((string) $code);
    $product = null;

    if ((int) $product_id > 0) {
        $product = db_item("SELECT id, name, short_description, enabled, inventory, inventory_quantity FROM products WHERE id = '" . (int) $product_id . "' LIMIT 1");
    } elseif ($code !== '') {
        $found = local_sale_find_product($code);
        $product = $found['product'];

        if (!$product && ($found['matches'] > 1)) {
            return local_sale_fail(lang(array('string' => 'More than one product matches “{var:1}”. Pick one from the list.', 'vars' => array($code))), array('field' => 'product', 'level' => 'warning'));
        }
        if (!$product) {
            return local_sale_fail(preg_match('/^\d{6,}$/', $code)
                ? lang(array('string' => 'No product has the barcode “{var:1}”. Check the code or search by name.', 'vars' => array($code)))
                : lang(array('string' => 'No product matches “{var:1}”. Try another name or code.', 'vars' => array($code))), array('field' => 'product'));
        }
    } else {
        return local_sale_fail(lang('Scan a barcode or type a product name.'), array('field' => 'product', 'level' => 'warning'));
    }

    if (!$product || ((int) $product['enabled'] !== 1)) {
        return local_sale_fail(lang('Product not found or is disabled.'), array('field' => 'product'));
    }

    $order_id = local_sale_order_id();
    $in_cart = local_sale_cart_qty($order_id, $product['id']);
    $stock_error = local_sale_stock_error($product, $in_cart + $quantity);

    if ($stock_error !== '') {
        $line_id = ($in_cart > 0) ? (int) db_value("SELECT id FROM order_items WHERE order_id = '" . $order_id . "' AND product_id = '" . (int) $product['id'] . "' AND added_by_offer = '0' ORDER BY id LIMIT 1") : 0;

        return ($line_id > 0)
            ? local_sale_fail($stock_error, array('line_errors' => array($line_id => $stock_error), 'line_id' => $line_id))
            : local_sale_fail(local_sale_product_label($product) . ': ' . $stock_error, array('field' => 'product'));
    }

    initialize_order();

    if (add_order_item($product['id'], $quantity, 0, 'myself', '') === false) {
        return local_sale_fail(lang('Product not found or is disabled.'), array('field' => 'product'));
    }

    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
    $line_id = (int) db_value("SELECT id FROM order_items WHERE order_id = '" . $order_id . "' AND product_id = '" . (int) $product['id'] . "' AND added_by_offer = '0' ORDER BY id DESC LIMIT 1");

    return local_sale_ok(lang(array('string' => '{var:1} added to the cart.', 'vars' => array(local_sale_product_label($product)))), array('line_id' => $line_id));
}

/**
 * Set a line to an exact quantity; 0 removes it. An absolute figure rather
 * than +1/-1, so a request that arrives twice does no harm.
 *
 * @param int $item_id
 * @param int $quantity
 * @return array
 */
function local_sale_set_qty($item_id, $quantity)
{
    $order_id = local_sale_order_id();

    if ($order_id <= 0) {
        return local_sale_fail(lang('The cart is empty.'));
    }

    $item = db_item(
        "SELECT oi.id, oi.product_id, oi.quantity, p.name, p.short_description, p.inventory, p.inventory_quantity
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.id = '" . (int) $item_id . "' AND oi.order_id = '" . $order_id . "'
         LIMIT 1"
    );

    if (!$item) {
        return local_sale_fail(lang('That line is no longer in the cart.'));
    }

    $quantity = min(9999, (int) $quantity);

    if ($quantity <= 0) {
        db("DELETE FROM order_items WHERE id = '" . (int) $item['id'] . "' AND order_id = '" . $order_id . "'");
        return local_sale_ok(lang(array('string' => '{var:1} removed from the cart.', 'vars' => array(local_sale_product_label($item)))), array('level' => 'info'));
    }

    $others = local_sale_cart_qty($order_id, $item['product_id'], $item['id']);
    $stock_error = local_sale_stock_error($item, $others + $quantity);

    if ($stock_error !== '') {
        return local_sale_fail($stock_error, array('line_errors' => array((int) $item['id'] => $stock_error), 'line_id' => (int) $item['id']));
    }

    db("UPDATE order_items SET quantity = '" . $quantity . "' WHERE id = '" . (int) $item['id'] . "' AND order_id = '" . $order_id . "'");

    return local_sale_ok('', array('line_id' => (int) $item['id']));
}

/**
 * Empty the cart. The order stays open as an empty cart; nothing has left
 * the stock yet, so nothing is put back.
 *
 * @return array
 */
function local_sale_clear_cart()
{
    $order_id = local_sale_order_id();

    if ($order_id > 0) {
        db("DELETE FROM order_items WHERE order_id = '" . $order_id . "'");
    }

    return local_sale_ok(lang('The cart was emptied.'), array('level' => 'info'));
}

/**
 * Who the sale is for. Kept in the session, not on the order:
 * initialize_order() rewrites orders.contact_id to the operator's own
 * contact on every cart change, so the choice is applied once, when the sale
 * is completed. Nothing chosen means a walk-in sale.
 *
 * @return array|null  contact_id, account_id, label
 */
function local_sale_customer()
{
    $customer = $_SESSION['ecommerce']['local_sale_customer'] ?? null;

    if (!is_array($customer)) {
        return null;
    }

    $customer['contact_id'] = (int) ($customer['contact_id'] ?? 0);
    $customer['account_id'] = (int) ($customer['account_id'] ?? 0);
    $customer['label'] = (string) ($customer['label'] ?? '');

    if (($customer['contact_id'] <= 0) && ($customer['account_id'] <= 0)) {
        return null;
    }

    return $customer;
}

/**
 * The ERP account the sale would be billed to, without opening one: the
 * picked account, the account of the picked contact, or the walk-in account
 * when nobody is picked. 0 when none exists yet.
 *
 * @return int
 */
function local_sale_billing_account()
{
    if (!local_sale_erp_on()) {
        return 0;
    }

    $customer = local_sale_customer();

    if ($customer && ($customer['account_id'] > 0)) {
        return $customer['account_id'];
    }

    if ($customer && ($customer['contact_id'] > 0)) {
        return (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $customer['contact_id'] . "' ORDER BY id ASC LIMIT 1");
    }

    $walkin = defined('ERP_WALKIN_ACCOUNT_ID') ? (int) ERP_WALKIN_ACCOUNT_ID : 0;

    return (($walkin > 0) && ((int) db_value("SELECT COUNT(*) FROM erp_accounts WHERE id = '" . $walkin . "'") > 0)) ? $walkin : 0;
}

/**
 * @param int $contact_id
 * @param int $account_id
 * @return array
 */
function local_sale_set_customer($contact_id, $account_id)
{
    $contact_id = (int) $contact_id;
    $account_id = (int) $account_id;
    $erp = local_sale_erp_on();
    $label = '';

    if ($contact_id > 0) {
        $contact = db_item("SELECT id, first_name, last_name, company FROM contacts WHERE id = '" . $contact_id . "' LIMIT 1");
        if ($contact) {
            $person = trim(trim((string) $contact['first_name']) . ' ' . trim((string) $contact['last_name']));
            $label = ($person !== '') ? $person : ((trim((string) $contact['company']) !== '') ? trim((string) $contact['company']) : ('#' . $contact_id));
            // The account the contact is linked to, when it has one; the ERP
            // opens one from the card otherwise, at invoicing.
            $account_id = $erp ? (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' ORDER BY id ASC LIMIT 1") : 0;
        } else {
            $contact_id = 0;
        }
    } elseif (($account_id > 0) && $erp) {
        $account = db_item("SELECT id, title, contact_id FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");
        if ($account) {
            $label = (string) $account['title'];
            $contact_id = (int) $account['contact_id'];
        } else {
            $account_id = 0;
        }
    } else {
        $account_id = 0;
    }

    if (($contact_id <= 0) && ($account_id <= 0)) {
        return local_sale_fail(lang('That customer could not be found.'));
    }

    $_SESSION['ecommerce']['local_sale_customer'] = array('contact_id' => $contact_id, 'account_id' => $account_id, 'label' => $label);

    return local_sale_ok(lang(array('string' => 'Customer: {var:1}', 'vars' => array($label))), array('customer_changed' => true, 'level' => 'info'));
}

function local_sale_clear_customer()
{
    unset($_SESSION['ecommerce']['local_sale_customer']);

    return local_sale_ok(lang('Walk-in sale: no customer is recorded on the order.'), array('customer_changed' => true, 'level' => 'info'));
}

/**
 * Open an account from the counter with the little a cashier can ask for -
 * a name, and a phone, e-mail or tax number if the buyer offers one - and
 * pick it for the sale. The same format rules as the account form, so the
 * card is not born broken for e-documents.
 *
 * @param array $user
 * @param array $data  title, is_person, tax_number, phone, email
 * @return array
 */
function local_sale_quick_account($user, $data)
{
    if (!local_sale_can_erp($user)) {
        return local_sale_fail(lang('Access denied.'));
    }

    $title = trim(mb_substr((string) ($data['title'] ?? ''), 0, 255));
    $is_person = ((string) ($data['is_person'] ?? '1') === '1');
    // The account opens in the store's country, and that country's rules
    // apply (erp_tax_number_check()): a Turkish number is digits with check
    // digits (GİB), any other is taken as typed.
    $is_tr = !function_exists('erp_account_country') || (erp_account_country('') === 'TR');
    $tax = function_exists('erp_tax_number_check')
        ? erp_tax_number_check(mb_substr((string) ($data['tax_number'] ?? ''), 0, 64))
        : array('value' => preg_replace('/\D/', '', (string) ($data['tax_number'] ?? '')), 'error' => '');
    $tax_number = $tax['value'];
    $email = trim(mb_substr((string) ($data['email'] ?? ''), 0, 255));
    $phone = trim(mb_substr((string) ($data['phone'] ?? ''), 0, 50));
    $errors = array();

    if ($title === '') {
        $errors['title'] = lang(array('string' => '{var:1} is required', 'vars' => array(lang('Name'))));
    } elseif ($is_tr && $is_person && function_exists('erp_edoc_person_name') && (erp_edoc_person_name($title) === null)) {
        $errors['title'] = lang('A person needs a first name and a surname. If this is a company, choose company as the taxpayer type.');
    }

    if ($tax['error'] !== '') {
        $errors['tax_number'] = $tax['error'];
    } elseif ($tax_number !== '') {
        $existing = db_item("SELECT id, title FROM erp_accounts WHERE tax_number = '" . escape($tax_number) . "' LIMIT 1");
        if ($existing) {
            $errors['tax_number'] = lang(array('string' => 'This number already belongs to the account “{var:1}”. Search for it instead.', 'vars' => array($existing['title'])));
        }
    }

    if (($email !== '') && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = lang('Please enter a valid email address.');
    }

    if (!empty($errors)) {
        return local_sale_fail(reset($errors), array('field_errors' => $errors));
    }

    $saved = erp_account_save(array(
        'kind' => 'customer',
        'title' => $title,
        'is_person' => $is_person,
        'tax_number' => $tax_number,
        'email' => $email,
        'phone' => $phone,
        'created_by' => (int) $user['id'],
    ));

    if (empty($saved['success'])) {
        return local_sale_fail((string) $saved['error']);
    }

    $_SESSION['ecommerce']['local_sale_customer'] = array('contact_id' => 0, 'account_id' => (int) $saved['id'], 'label' => $title);

    return local_sale_ok(lang(array('string' => 'Account “{var:1}” opened and picked for this sale.', 'vars' => array($title))), array('customer_changed' => true));
}

/**
 * The account a walk-in sale is billed to. The one named on the ERP card
 * when it exists; otherwise one is opened ("Retail customer", or the
 * existing account of that name) and named on the card, so a counter sale is
 * never left without an invoice because a setting was never filled in.
 *
 * @param int $user_id
 * @return int  0 when the ERP cannot hold one
 */
function local_sale_walkin_account($user_id = 0)
{
    static $opened = 0;

    if ($opened > 0) {
        return $opened;
    }

    $walkin = defined('ERP_WALKIN_ACCOUNT_ID') ? (int) ERP_WALKIN_ACCOUNT_ID : 0;

    if (($walkin > 0) && ((int) db_value("SELECT COUNT(*) FROM erp_accounts WHERE id = '" . $walkin . "'") > 0)) {
        return $walkin;
    }

    if (!local_sale_erp_on() || !waf_table_has_column('config', 'erp_walkin_account_id')) {
        return 0;
    }

    $title = lang('Retail customer');
    $account_id = (int) db_value("SELECT id FROM erp_accounts WHERE title = '" . escape($title) . "' AND kind IN ('customer', 'both') AND status = 'active' ORDER BY id ASC LIMIT 1");

    if ($account_id <= 0) {
        $saved = erp_account_save(array('kind' => 'customer', 'title' => $title, 'is_person' => true, 'created_by' => (int) $user_id));
        if (empty($saved['success'])) {
            return 0;
        }
        $account_id = (int) $saved['id'];
    }

    db("UPDATE config SET erp_walkin_account_id = '" . $account_id . "'");
    log_activity(lang(array('string' => 'Walk-in sales account set to “{var:1}” by the first counter sale that needed one.', 'vars' => array($title))));

    $opened = $account_id;

    return $account_id;
}

/**
 * The next order number, taken the way submit_order.php takes it.
 *
 * @return int|false
 */
function local_sale_next_order_number()
{
    if (!mysqli_query(db::$con, "LOCK TABLES next_order_number WRITE")) {
        return false;
    }

    $number = false;
    $result = mysqli_query(db::$con, "SELECT next_order_number FROM next_order_number");

    if ($result && (mysqli_num_rows($result) > 0)) {
        $row = mysqli_fetch_assoc($result);
        $number = (int) $row['next_order_number'];
    } elseif ($result && mysqli_query(db::$con, "INSERT INTO next_order_number VALUES (1)")) {
        $number = 1;
    }

    if (($number !== false) && !mysqli_query(db::$con, "UPDATE next_order_number SET next_order_number = next_order_number + 1")) {
        $number = false;
    }

    mysqli_query(db::$con, "UNLOCK TABLES");

    return $number;
}

/**
 * The payment methods the counter offers. 'later' records no money.
 *
 * @return array  code => label
 */
function local_sale_methods()
{
    return array(
        'cash' => lang('Cash'),
        'card' => lang('Card'),
        'transfer' => lang('Bank transfer'),
        'cheque' => lang('Cheque'),
        'other' => lang('Other'),
        'later' => lang('Collect later'),
        'split' => lang('Split payment'),
    );
}

/**
 * The parts of a split payment as the screen sent them: two at most, each a
 * way of paying, a till and an amount in kurus. What is left of the total
 * stays on the account.
 *
 * @param mixed $raw    JSON text or an array
 * @param int   $total  The sale's total, kurus
 * @return array ['parts' => [['method', 'till', 'amount']], 'error' => string]
 */
function local_sale_split_parts($raw, $total)
{
    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
    $allowed = array('cash', 'card', 'transfer', 'cheque', 'other');
    $parts = array();
    $sum = 0;

    foreach (is_array($decoded) ? array_slice($decoded, 0, 2) : array() as $part) {
        $amount = (int) ($part['amount'] ?? 0);

        if ($amount <= 0) {
            continue;
        }

        $method = (string) ($part['method'] ?? '');
        $till = (int) ($part['till'] ?? 0);

        if (!in_array($method, $allowed, true)) {
            return array('parts' => array(), 'error' => lang('Choose how each part is paid.'));
        }

        if (($till <= 0) || ((int) db_value("SELECT COUNT(*) FROM erp_cash_accounts WHERE id = '" . $till . "' AND is_active = 1") === 0)) {
            return array('parts' => array(), 'error' => lang('Choose a till or bank account for each part.'));
        }

        $parts[] = array('method' => $method, 'till' => $till, 'amount' => $amount);
        $sum += $amount;
    }

    if (empty($parts)) {
        return array('parts' => array(), 'error' => lang('Enter the amount of at least one part.'));
    }

    if ($sum > (int) $total) {
        return array('parts' => array(), 'error' => lang('The parts add up to more than the total.'));
    }

    return array('parts' => $parts, 'error' => '');
}

/**
 * Tills and bank accounts money can go to.
 *
 * @return array
 */
function local_sale_tills()
{
    if (!local_sale_erp_on()) {
        return array();
    }

    return (array) db_items("SELECT id, name, kind FROM erp_cash_accounts WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
}

/**
 * Complete the sale.
 *
 * In order: the stock is checked again (another till may have sold the last
 * units since they were scanned here), the order is claimed with a
 * conditional update so a double click or a second tab cannot complete it
 * twice, it is numbered, the stock goes down, order.created is announced,
 * and - with the ERP on - the invoice is issued and the money recorded.
 *
 * Once the order is claimed it stays completed: a failed invoice or receipt
 * comes back as a warning on a completed sale, never as a sale undone.
 *
 * @param array $user
 * @param array $input  pay_method, pay_till, received (kuruş, display only)
 * @return array
 */
function local_sale_complete($user, $input)
{
    $order_id = local_sale_order_id();
    $lines = local_sale_lines($order_id);

    if (($order_id <= 0) || empty($lines)) {
        return local_sale_fail(lang('The cart is empty.'), array('level' => 'warning'));
    }

    // 1. Stock, again.
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

    if (!empty($line_errors)) {
        return local_sale_fail(lang('Some lines no longer fit the stock. Correct them and complete the sale again.'), array('line_errors' => $line_errors));
    }

    // A split payment is checked before the sale is claimed: a part that
    // could not be recorded is put right on the screen, not after the fact.
    $split = null;

    if ((string) ($input['pay_method'] ?? '') === 'split') {
        if (!local_sale_erp_on() || !local_sale_can_cash($user)) {
            return local_sale_fail(lang('A split payment is recorded in the tills, which needs the ERP cash right.'), array('level' => 'warning'));
        }

        $split = local_sale_split_parts($input['pay_parts'] ?? '', (int) local_sale_totals($lines)['gross']);

        if ($split['error'] !== '') {
            return local_sale_fail($split['error'], array('level' => 'warning'));
        }
    }

    // 2. Claim the order. Only one request can move it out of 'incomplete'.
    $now = time();
    db("UPDATE orders SET status = 'complete', type = 'local', last_modified_timestamp = '" . $now . "'
        WHERE id = '" . $order_id . "' AND status = 'incomplete'");

    if (mysqli_affected_rows(db::$con) !== 1) {
        unset($_SESSION['ecommerce']['order_id']);
        return local_sale_fail(lang('This sale has already been completed.'), array('code' => 'completed', 'level' => 'warning'));
    }

    // 3. Number it.
    $order_number = local_sale_next_order_number();

    if ($order_number === false) {
        db("UPDATE orders SET status = 'incomplete' WHERE id = '" . $order_id . "'");
        return local_sale_fail(lang('The order number could not be taken. Try again.'));
    }

    // 4. The figures. tax_total is the line's VAT; tax stays 0 (see
    // update_order_item_taxes()).
    $totals = local_sale_totals($lines);

    foreach ($lines as $line) {
        db("UPDATE order_items SET tax_total = '" . (int) $line['tax'] . "', tax = '0' WHERE id = '" . (int) $line['id'] . "'");
    }

    // Who the sale is for: the picked contact and account, or nobody (a
    // walk-in), billed to the walk-in account while the ERP is on.
    $erp = local_sale_erp_on();
    $customer = local_sale_customer();
    $contact_id = $customer ? $customer['contact_id'] : 0;
    $account_id = $customer ? $customer['account_id'] : 0;

    if ($erp && ($account_id <= 0) && ($contact_id <= 0)) {
        $account_id = local_sale_walkin_account((int) $user['id']);
    }

    $sql_account = waf_table_has_column('orders', 'erp_account_id') ? "erp_account_id = '" . (int) $account_id . "'," : '';

    db("UPDATE orders SET
            order_number = '" . (int) $order_number . "',
            subtotal = '" . (int) $totals['net'] . "',
            tax = '" . (int) $totals['tax'] . "',
            total = '" . (int) $totals['gross'] . "',
            contact_id = '" . (int) $contact_id . "',
            " . $sql_account . "
            user_id = '" . (int) $user['id'] . "',
            last_modified_timestamp = '" . $now . "',
            ip_address = IFNULL(INET_ATON('" . escape($_SERVER['REMOTE_ADDR'] ?? '') . "'), 0)
        WHERE id = '" . $order_id . "'");

    // 5. Stock goes down for tracked products.
    foreach ($lines as $line) {
        if (((int) $line['inventory'] === 1) && ((int) $line['inventory_quantity'] > 0)) {
            db("UPDATE products SET inventory_quantity = (inventory_quantity - '" . (int) $line['quantity'] . "') WHERE id = '" . (int) $line['product_id'] . "'");

            if ((int) $line['quantity'] >= (int) $line['inventory_quantity']) {
                db("UPDATE products SET out_of_stock = '1', out_of_stock_timestamp = UNIX_TIMESTAMP() WHERE id = '" . (int) $line['product_id'] . "'");
            }

            if (function_exists('pg_marketplace_product_changed')) {
                pg_marketplace_product_changed((int) $line['product_id']);
            }
        }
    }

    // 6. Tell whoever is subscribed, the same event a web order raises.
    if (function_exists('pg_announce')) {
        pg_announce('order.created', array('id' => $order_id, 'order_number' => (int) $order_number));
    }

    unset($_SESSION['ecommerce']['order_id']);
    unset($_SESSION['ecommerce']['local_sale_customer']);

    // What the operator picked is remembered for the next sale: the till and
    // the way people pay rarely change between two customers.
    $methods = local_sale_methods();
    $pay_method = (string) ($input['pay_method'] ?? 'cash');
    if (!isset($methods[$pay_method])) {
        $pay_method = 'later';
    }
    $pay_till = (int) ($input['pay_till'] ?? 0);
    // A split is this sale's; the next one starts from the till's usual way.
    $_SESSION['ecommerce']['local_sale_payment'] = array('method' => ($pay_method === 'split') ? 'cash' : $pay_method, 'till' => $pay_till);

    $result = array(
        'order_id' => $order_id,
        'order_number' => (int) $order_number,
        'total' => (int) $totals['gross'],
        'items' => (int) $totals['count'],
        'method' => $pay_method,
        'till_name' => '',
        'received' => max(0, (int) ($input['received'] ?? 0)),
        'who' => $customer ? $customer['label'] : '',
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

/**
 * Issue the invoice of a completed counter sale that has none - the retry
 * after a failed attempt, or an older sale from before every sale was
 * invoiced.
 *
 * @param array $user
 * @param int   $order_id
 * @return array
 */
function local_sale_issue_invoice($user, $order_id)
{
    if (!local_sale_erp_on()) {
        return local_sale_fail(lang('The ERP module is not switched on.'));
    }

    $order = db_item("SELECT id, type, status, order_number, contact_id, erp_account_id, erp_invoice_id FROM orders WHERE id = '" . (int) $order_id . "' LIMIT 1");

    if (!$order || ((string) $order['type'] !== 'local') || ((string) $order['status'] !== 'complete')) {
        return local_sale_fail(lang('Order not found.'));
    }

    if ((int) $order['erp_invoice_id'] > 0) {
        return local_sale_fail(lang('This order has already been invoiced.'), array('level' => 'info'));
    }

    if (((int) $order['erp_account_id'] <= 0) && ((int) $order['contact_id'] <= 0)) {
        $walkin = local_sale_walkin_account((int) $user['id']);
        if ($walkin > 0) {
            db("UPDATE orders SET erp_account_id = '" . $walkin . "' WHERE id = '" . (int) $order['id'] . "'");
        }
    }

    $made = erp_invoice_from_order((int) $order['id'], array('created_by' => (int) $user['id']));

    if (empty($made['success'])) {
        return local_sale_fail(lang(array('string' => 'The invoice was not issued: {var:1}', 'vars' => array((string) $made['error']))));
    }

    return local_sale_ok(lang(array('string' => 'Invoice {var:1} created.', 'vars' => array($made['full_number']))), array('invoice_id' => (int) $made['invoice_id']));
}

/* ---------------------------------------------------------------------
   HTML
   --------------------------------------------------------------------- */

/**
 * An amount for the screen, as HTML. With the ERP on the till writes it the
 * way the ERP's screens and documents do, in the panel's language
 * (erp_money_out(): 1.234,56 in Turkish, 1,234.56 in English), so the till
 * and the invoice it produces read alike; the store's e-commerce format
 * (prepare_amount()) otherwise.
 *
 * @param int $kurus
 * @return string
 */
function local_sale_money($kurus)
{
    if (local_sale_erp_on() && function_exists('erp_money_out')) {
        return h(erp_money_out((int) $kurus));
    }

    return prepare_amount(((int) $kurus) / 100);
}

/**
 * The separators local_sale_money() writes with, for the screen's script,
 * which formats the change and the quick amounts itself.
 *
 * @return array  decimal, thousands
 */
function local_sale_number_separators()
{
    if (local_sale_erp_on() && function_exists('erp_number_separators')) {
        return erp_number_separators();
    }

    // prepare_amount() writes with the site language's separators.
    return pg_number_separators();
}

/**
 * The same amount as plain text, for JSON a script puts in with
 * textContent: the currency symbol is stored as an HTML entity (&#8378;).
 *
 * @param int $kurus
 * @return string
 */
function local_sale_money_text($kurus)
{
    return html_entity_decode(local_sale_money($kurus), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * "e-Invoice" or "e-Archive" for the account the invoice goes to, or '' when
 * no e-document provider is set up and the word would promise nothing.
 *
 * @param int $account_id
 * @return string
 */
function local_sale_doc_type_label($account_id)
{
    if (!local_sale_erp_on() || !function_exists('erp_edoc_active') || (erp_edoc_active() === '')) {
        return '';
    }

    $einvoice = ((int) $account_id > 0) ? (int) db_value("SELECT einvoice_user FROM erp_accounts WHERE id = '" . (int) $account_id . "' LIMIT 1") : 0;

    return ($einvoice === 1) ? lang('e-Invoice') : lang('e-Archive');
}

/**
 * The rows of the cart table. Both views' figures are printed and the
 * stylesheet shows one set: the till view reads prices with VAT, the
 * document view without it.
 *
 * @param array $lines
 * @param array $line_errors  item id => message
 * @param int   $flash_id     the line that just changed
 * @return string
 */
function local_sale_render_rows($lines, $line_errors = array(), $flash_id = 0)
{
    if (empty($lines)) {
        return '<tr class="pg-sale-empty-row"><td colspan="9"><div class="pg-sale-empty">'
            . '<i class="bi bi-upc-scan" aria-hidden="true"></i>'
            . '<strong>' . lang('Cart is empty.') . '</strong>'
            . '<span>' . lang('Scan a barcode or type a product name. The scanner works without clicking the box first.') . '</span>'
            . '</div></td></tr>';
    }

    $output = '';
    $number = 0;

    foreach ($lines as $line) {
        $number++;
        $id = (int) $line['id'];
        $tracked = ((int) $line['inventory'] === 1);
        $at_limit = $tracked && ((int) $line['quantity'] >= (int) $line['inventory_quantity']);
        $error = isset($line_errors[$id]) ? (string) $line_errors[$id] : '';
        $label = local_sale_product_label($line);

        $image = (trim((string) $line['image_name']) !== '')
            ? '<img class="pg-sale-thumb" src="' . PATH . h($line['image_name']) . '" alt="" loading="lazy" width="40" height="40">'
            : '<span class="pg-sale-thumb" aria-hidden="true"><i class="bi bi-box-seam"></i></span>';

        // The code under the name, unless the code is the name.
        $sub = (trim((string) $line['name']) !== $label) ? '<span class="font-monospace">' . h($line['name']) . '</span>' : '';
        if ($tracked) {
            $sub .= (($sub !== '') ? ' · ' : '') . h(lang(array('string' => 'In stock: {var:1}', 'vars' => array((int) $line['inventory_quantity']))));
            if ($at_limit) {
                $sub .= ' · <span class="text-danger-emphasis">' . lang('at the limit') . '</span>';
            }
        }

        $classes = trim((($flash_id === $id) ? 'pg-sale-flash ' : '') . (($error !== '') ? 'pg-sale-line-error' : ''));

        $output .= '<tr data-line="' . $id . '"' . (($classes !== '') ? ' class="' . $classes . '"' : '') . '>'
            . '<td class="pg-sale-c-thumb pg-kasa">' . $image . '</td>'
            . '<td class="pg-sale-c-no pg-belge">' . $number . '</td>'
            . '<td class="pg-sale-c-name"><span class="pg-sale-pname">' . h($label) . '</span><span class="pg-sale-psub">' . $sub . '</span>'
                . '<span class="pg-sale-unit-inline"><span class="pg-kasa-inline">' . local_sale_money($line['unit_till']) . '</span><span class="pg-belge-inline">' . local_sale_money($line['price']) . '</span> / ' . lang('each') . '</span></td>'
            . '<td class="pg-sale-c-qty"><span class="pg-sale-step">'
                . '<button type="button" class="no-submit" data-sale-dec="' . $id . '" aria-label="' . h(lang('Decrease')) . '"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>'
                . '<input type="text" inputmode="numeric" value="' . (int) $line['quantity'] . '" data-sale-qty="' . $id . '" aria-label="' . h(lang(array('string' => 'Quantity of {var:1}', 'vars' => array($label)))) . '">'
                . '<button type="button" class="no-submit" data-sale-inc="' . $id . '" aria-label="' . h(lang('Increase')) . '"' . ($at_limit ? ' disabled' : '') . '><i class="bi bi-plus-lg" aria-hidden="true"></i></button>'
                . '</span>' . (($error !== '') ? '<span class="pg-sale-linemsg" role="alert">' . h($error) . '</span>' : '') . '</td>'
            . '<td class="pg-sale-c-unit text-end"><span class="pg-kasa-inline">' . local_sale_money($line['unit_till']) . '</span><span class="pg-belge-inline">' . local_sale_money($line['price']) . '</span></td>'
            . '<td class="pg-sale-c-rate text-end pg-belge">' . h(local_sale_percent($line['rate'])) . '</td>'
            . '<td class="pg-sale-c-tax text-end pg-belge">' . local_sale_money($line['tax']) . '</td>'
            . '<td class="pg-sale-c-amount text-end"><strong><span class="pg-kasa-inline">' . local_sale_money($line['amount_till']) . '</span><span class="pg-belge-inline">' . local_sale_money($line['net']) . '</span></strong></td>'
            . '<td class="pg-sale-c-rm"><button type="button" class="pg-sale-rm no-submit" data-sale-rm="' . $id . '" aria-label="' . h(lang(array('string' => 'Remove {var:1}', 'vars' => array($label)))) . '"><i class="bi bi-trash3" aria-hidden="true"></i></button></td>'
            . '</tr>';
    }

    return $output;
}

/**
 * The summary card's body: a large total for the till view, a VAT breakdown
 * for the document view.
 *
 * @param array $totals
 * @return string
 */
function local_sale_render_summary($totals)
{
    $count = ($totals['count'] > 0)
        ? lang(array('string' => '{var:1} lines · {var:2} units', 'vars' => array($totals['count'], $totals['units'])))
        : lang('Cart is empty.');

    $output = '<div class="pg-kasa pg-sale-total">'
        . '<div class="pg-sale-total-label">' . lang('Total') . '</div>'
        . '<div class="pg-sale-total-amount">' . local_sale_money($totals['gross']) . '</div>'
        . '<div class="pg-sale-total-sub">' . h($count) . (($totals['count'] > 0) ? ' · ' . h(local_sale_tax_label(local_sale_prices_net() ? 'added' : 'included')) : '') . '</div>'
        . '</div>'
        . '<dl class="pg-kasa pg-sale-rows">'
        . '<dt>' . h(local_sale_tax_label('excluding')) . '</dt><dd>' . local_sale_money($totals['net']) . '</dd>'
        . '<dt>' . h(local_sale_tax_label('tax')) . '</dt><dd>' . local_sale_money($totals['tax']) . '</dd>'
        . '</dl>';

    $output .= '<dl class="pg-belge pg-sale-rows pg-sale-rows-flush">'
        . '<dt>' . h(local_sale_tax_label('subtotal_excl')) . '</dt><dd>' . local_sale_money($totals['net']) . '</dd>';

    if (empty($totals['rates'])) {
        $output .= '<dt>' . h(local_sale_tax_label('tax')) . '</dt><dd>' . local_sale_money(0) . '</dd>';
    }

    foreach ($totals['rates'] as $rate => $tax) {
        // The rate the way the rest of the ERP writes it (%8,25 / 8.25%),
        // under the tax's own name when the settings give one.
        $caption = local_sale_erp_on()
            ? local_sale_tax_label('tax') . ' ' . local_sale_percent($rate)
            : lang(array('string' => 'VAT {var:1}%', 'vars' => array($rate)));
        $output .= '<dt>' . h($caption) . '</dt><dd>' . local_sale_money($tax) . '</dd>';
    }

    $output .= '<dt class="pg-sale-grand">' . lang('Grand total') . '</dt><dd class="pg-sale-grand">' . local_sale_money($totals['gross']) . '</dd></dl>';

    return $output;
}

/**
 * The customer box, closed (the search opens in place of it, in the script).
 *
 * @param array $user
 * @return string
 */
function local_sale_render_customer($user)
{
    $customer = local_sale_customer();

    if (!$customer) {
        return '<div class="pg-sale-cust">'
            . '<span class="pg-sale-av" aria-hidden="true"><i class="bi bi-shop"></i></span>'
            . '<span class="pg-sale-who"><b>' . lang('Walk-in sale') . '</b><small>' . lang('No customer is recorded on the order') . '</small></span>'
            . '<button type="button" class="btn btn-sm btn-ghost no-submit" data-sale-cust="change">' . lang('Pick') . ' <kbd>F4</kbd></button>'
            . '</div>';
    }

    $sub = array();

    if ($customer['contact_id'] > 0) {
        $contact = db_item("SELECT company, email_address, business_city FROM contacts WHERE id = '" . $customer['contact_id'] . "' LIMIT 1");
        if ($contact) {
            $sub = array(trim((string) $contact['company']), trim((string) $contact['email_address']), trim((string) $contact['business_city']));
        }
    } elseif (local_sale_erp_on()) {
        $account = db_item("SELECT city, phone, email FROM erp_accounts WHERE id = '" . $customer['account_id'] . "' LIMIT 1");
        $sub = array(lang('Ledger account'));
        if ($account) {
            $sub[] = trim((string) $account['city']);
            $sub[] = trim((string) (($account['phone'] !== '') ? $account['phone'] : $account['email']));
        }
    }

    $icon = ($customer['contact_id'] > 0) ? 'bi-person' : 'bi-journal-text';

    return '<div class="pg-sale-cust">'
        . '<span class="pg-sale-av is-picked" aria-hidden="true"><i class="bi ' . $icon . '"></i></span>'
        . '<span class="pg-sale-who"><b>' . h($customer['label']) . '</b><small>' . h(implode(' · ', array_filter($sub, 'strlen'))) . '</small></span>'
        . '<button type="button" class="btn btn-sm btn-ghost no-submit" data-sale-cust="change">' . lang('Change') . '</button>'
        . '<button type="button" class="btn btn-sm btn-ghost no-submit" data-sale-cust="clear" aria-label="' . h(lang('Remove the customer')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
        . '</div>';
}

/**
 * The invoice line of the payment card: who the invoice goes to and, for a
 * sale collected later, when it falls due.
 *
 * @param array $user
 * @return string
 */
function local_sale_render_invoice_note($user)
{
    if (!local_sale_erp_on()) {
        return '';
    }

    $customer = local_sale_customer();
    $account_id = local_sale_billing_account();
    $account = ($account_id > 0) ? db_item("SELECT id, title FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1") : null;

    if ($customer) {
        $party = $customer['label'];
    } elseif ($account) {
        $party = (string) $account['title'];
    } else {
        $party = lang('Retail customer');
    }

    $type = local_sale_doc_type_label($account_id);
    $due = erp_account_due_date($account_id, date('Y-m-d'));
    $due_text = ($due === date('Y-m-d'))
        ? lang('Due today; the amount is owed on the account.')
        : lang(array('string' => 'Due {var:1}; the amount is owed on the account.', 'vars' => array(get_absolute_time(array('timestamp' => strtotime($due), 'type' => 'date', 'format' => 'plain_text')))));

    return '<div class="pg-sale-inv"><i class="bi bi-receipt" aria-hidden="true"></i><span><b>' . lang('The invoice is issued') . '</b><small>' . h(trim($type . ' · ' . $party, ' ·')) . '</small></span></div>'
        . '<div class="pg-sale-note" data-sale-when="later"><i class="bi bi-calendar3" aria-hidden="true"></i><span>' . h($due_text) . '</span></div>';
}

/**
 * The account card of the document view: balance, identity, e-document
 * readiness. Shown to operators who hold the ERP right.
 *
 * @param array $user
 * @return string
 */
function local_sale_render_ledger($user)
{
    if (!local_sale_can_erp($user)) {
        return '';
    }

    $customer = local_sale_customer();

    if (!$customer) {
        return '';
    }

    $account_id = local_sale_billing_account();
    $header = '<div class="card-header"><h2 class="pg-sale-ch text-primary">' . lang('Ledger account') . '</h2>';

    if ($account_id <= 0) {
        return '<div class="card pg-belge-block">' . $header . '</div><div class="card-body"><div class="pg-sale-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>'
            . lang('This contact has no account yet; one is opened from the contact card when the sale is invoiced.') . '</span></div></div></div>';
    }

    $account = db_item("SELECT * FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");

    if (!$account) {
        return '';
    }

    $statement = erp_account_statement($account_id);
    $balance = (int) $statement['closing'];
    $balance_class = ($balance > 0) ? 'text-success' : (($balance < 0) ? 'text-danger' : 'text-body-secondary');
    $balance_side = ($balance > 0) ? lang('owes you') : (($balance < 0) ? lang('you owe') : lang('No balance'));

    $term = (int) $account['payment_days'];
    $facts = '<dt>' . h(function_exists('erp_tax_id_label') ? erp_tax_id_label((string) ($account['country_code'] ?? '')) : lang('VKN / TCKN')) . '</dt><dd>' . ((trim((string) $account['tax_number']) !== '') ? h($account['tax_number']) : '<span class="text-body-secondary">—</span>') . '</dd>';

    $type = local_sale_doc_type_label($account_id);
    if ($type !== '') {
        $facts .= '<dt>' . lang('e-Document') . '</dt><dd>' . (((int) $account['einvoice_user'] === 1)
            ? '<span class="badge text-bg-success">' . lang('e-Invoice taxpayer') . '</span>'
            : '<span class="badge text-bg-secondary">' . lang('e-Archive') . '</span>') . '</dd>';
    }

    $facts .= '<dt>' . lang('Payment term') . '</dt><dd>' . (($term > 0) ? h(lang(array('string' => '{var:1} days', 'vars' => array($term)))) : lang('Store default')) . '</dd>';

    $warning = '';
    if (function_exists('erp_edoc_invoice_party_missing')) {
        $missing = erp_edoc_invoice_party_missing(array('direction' => 'sales', 'doc_type' => 'invoice', 'status' => 'draft', 'account_id' => $account_id));
        if (!empty($missing['fields'])) {
            $warning = '<div class="alert alert-warning d-flex gap-2 mt-3 mb-0 small"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>'
                . h(lang(array('string' => 'For e-documents this account is missing: {var:1}. The invoice is issued, but it cannot go out until the card is completed.', 'vars' => array(implode(', ', $missing['fields'])))))
                . ' <a href="edit_erp_account.php?id=' . $account_id . '" class="alert-link">' . lang('Edit the account') . '</a></span></div>';
        }
    }

    return '<div class="card pg-belge-block">' . $header
        . '<button type="button" class="btn btn-sm btn-ghost no-submit" data-sale-ledger="' . $account_id . '"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . lang('Account summary') . '</button></div>'
        . '<div class="card-body">'
        . '<div class="pg-sale-balance"><b class="' . $balance_class . '">' . h(erp_money_out(abs($balance))) . '</b><span class="text-body-secondary">' . h($balance_side) . '</span></div>'
        . '<dl class="pg-sale-facts">' . $facts . '</dl>' . $warning
        . '</div></div>';
}

/**
 * The payment card, drawn once with the page. The method, till and amount
 * received are the operator's and stay in the page between requests; only
 * the invoice line inside it is redrawn when the customer changes.
 *
 * @param array $user
 * @return string
 */
function local_sale_render_pay($user)
{
    $erp = local_sale_erp_on();
    $can_cash = local_sale_can_cash($user);
    $tills = $can_cash ? local_sale_tills() : array();
    $remembered = (isset($_SESSION['ecommerce']['local_sale_payment']) && is_array($_SESSION['ecommerce']['local_sale_payment']))
        ? $_SESSION['ecommerce']['local_sale_payment']
        : array('method' => 'cash', 'till' => 0);
    $methods = local_sale_methods();
    $method = isset($methods[$remembered['method'] ?? '']) ? (string) $remembered['method'] : 'cash';
    $output = '';

    if ($erp && $can_cash && !empty($tills)) {
        $tiles = array('cash' => 'bi-cash-coin', 'card' => 'bi-credit-card', 'transfer' => 'bi-bank', 'later' => 'bi-hourglass-split');
        $output .= '<div class="pg-sale-methods" role="group" aria-label="' . h(lang('Payment Method')) . '">';
        foreach ($tiles as $code => $icon) {
            $output .= '<button type="button" class="pg-sale-method no-submit" data-sale-method="' . $code . '" aria-pressed="' . (($method === $code) ? 'true' : 'false') . '"><i class="bi ' . $icon . '" aria-hidden="true"></i>' . h($methods[$code]) . '</button>';
        }
        $output .= '</div>';

        $extra = in_array($method, array('cheque', 'other'), true);
        $output .= '<button type="button" class="pg-sale-link no-submit" data-sale-extra-show' . ($extra ? ' hidden' : '') . '>' . lang('Cheque or another method') . '</button>'
            . '<select class="form-select form-select-sm" id="pg_sale_extra" aria-label="' . h(lang('Other payment method')) . '"' . ($extra ? '' : ' hidden') . '>'
            . '<option value="">' . lang('Cheque or another method') . '</option>'
            . '<option value="cheque"' . (($method === 'cheque') ? ' selected' : '') . '>' . h($methods['cheque']) . '</option>'
            . '<option value="other"' . (($method === 'other') ? ' selected' : '') . '>' . h($methods['other']) . '</option>'
            . '</select>';

        $default_till = (int) ($remembered['till'] ?? 0);
        if (($default_till <= 0) && defined('ERP_DEFAULT_CASH_ACCOUNT_ID')) {
            $default_till = (int) ERP_DEFAULT_CASH_ACCOUNT_ID;
        }
        $options = '';
        foreach ($tills as $till) {
            $options .= '<option value="' . (int) $till['id'] . '" data-kind="' . h($till['kind']) . '"' . (((int) $till['id'] === $default_till) ? ' selected' : '') . '>' . h($till['name']) . '</option>';
        }
        $output .= '<div class="pg-sale-till" data-sale-when-not="later split"><label class="form-label" for="pg_sale_till">' . lang('Till or bank account') . '</label>'
            . '<select class="form-select" id="pg_sale_till">' . $options . '</select></div>';

        // Two ways of paying one sale - part in cash, part by card - with
        // what the parts leave kept open on the account.
        $part_methods = '';
        foreach (array('cash', 'card', 'transfer', 'cheque', 'other') as $code) {
            $part_methods .= '<option value="' . $code . '">' . h($methods[$code]) . '</option>';
        }
        $part_tills = '';
        foreach ($tills as $till) {
            $part_tills .= '<option value="' . (int) $till['id'] . '" data-kind="' . h($till['kind']) . '">' . h($till['name']) . '</option>';
        }
        $parts = '';
        foreach (array(1, 2) as $part) {
            $parts .= '<div class="row g-1 mb-1" data-sale-part="' . $part . '">'
                . '<div class="col-6"><select class="form-select form-select-sm" data-part-method aria-label="' . h(lang(array('string' => 'Part {var:1}: how it is paid', 'vars' => $part))) . '">' . $part_methods . '</select></div>'
                . '<div class="col-6"><input type="text" class="form-control form-control-sm text-end fw-semibold" data-part-amount inputmode="decimal" autocomplete="off" placeholder="0' . h(local_sale_number_separators()['decimal']) . '00" aria-label="' . h(lang(array('string' => 'Part {var:1}: amount', 'vars' => $part))) . '"></div>'
                . '<div class="col-12"><select class="form-select form-select-sm" data-part-till aria-label="' . h(lang(array('string' => 'Part {var:1}: till or bank account', 'vars' => $part))) . '">' . $part_tills . '</select></div>'
                . '</div>';
        }
        $output .= '<button type="button" class="pg-sale-link no-submit" data-sale-method="split" data-sale-when-not="split">' . lang('Split between two ways of paying') . '</button>'
            . '<div class="pg-sale-split" data-sale-when="split"><div class="d-flex flex-column gap-1">'
            . '<div class="form-label mb-0">' . lang('Split payment') . '</div>' . $parts
            . '<div class="small text-body-secondary" id="pg_sale_split_rest" aria-live="polite"></div>'
            . '<button type="button" class="pg-sale-link no-submit align-self-start" data-sale-method="cash">' . lang('One way of paying') . '</button>'
            . '</div></div>';
    } elseif ($erp && $can_cash) {
        $output .= '<div class="pg-sale-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>' . lang('No till or bank account is set up, so the money is not recorded; the invoice stays open.') . '</span></div>';
    } elseif ($erp) {
        $output .= '<div class="pg-sale-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>' . lang('The invoice is issued with the sale; the ERP manager records the payment.') . '</span></div>';
    }

    // The amount handed over and the change, for the till view. Nothing of
    // it is stored.
    $output .= '<div class="pg-kasa pg-sale-recv" data-sale-when="cash"><label class="form-label" for="pg_sale_received">' . lang('Received') . '</label>'
        . '<div class="input-group input-group-lg"><span class="input-group-text">' . h(html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</span>'
        . '<input type="text" class="form-control fw-semibold" id="pg_sale_received" inputmode="decimal" autocomplete="off" placeholder="0' . h(local_sale_number_separators()['decimal']) . '00"></div>'
        . '<div class="pg-sale-quick" id="pg_sale_quick"></div>'
        . '<div class="pg-sale-change" id="pg_sale_change" hidden><span></span><b></b></div>'
        . (!$erp ? '<div class="pg-sale-note mt-2"><i class="bi bi-info-circle" aria-hidden="true"></i><span>' . lang('This amount is not stored; it only works out the change.') . '</span></div>' : '')
        . '</div>';

    $output .= '<div id="pg_sale_invoice">' . local_sale_render_invoice_note($user) . '</div>';

    $output .= '<button type="button" class="btn btn-success pg-sale-go no-submit" id="pg_sale_complete" disabled>'
        . '<i class="bi bi-check2-circle" aria-hidden="true"></i><span>' . lang('Complete the sale') . '</span><kbd>F9</kbd></button>';

    return '<div class="card pg-sale-pay-card" id="pg_sale_pay"><div class="card-header"><h2 class="pg-sale-ch text-primary">' . ($erp ? lang('Payment') : lang('Change due')) . '</h2></div>'
        . '<div class="card-body pg-sale-pay" data-method="' . h($method) . '">' . $output . '</div></div>';
}

/**
 * The card shown in place of the summary once the sale is done.
 *
 * @param array $r     local_sale_complete()'s result
 * @param array $user
 * @return string
 */
function local_sale_render_done($r, $user)
{
    $methods = local_sale_methods();
    $can_erp = local_sale_can_erp($user);
    $how = ($r['method'] === 'later') ? $methods['later'] : ($methods[$r['method']] ?? '');
    if ($r['till_name'] !== '') {
        $how .= ' · ' . $r['till_name'];
    }
    if (!empty($r['rest'])) {
        $how .= ' · ' . lang(array('string' => '{var:1} left on the account', 'vars' => local_sale_money_text((int) $r['rest'])));
    }

    $output = '<div class="card pg-sale-done" id="pg_sale_done_card">'
        . '<div class="pg-sale-done-head"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><div><b>' . lang('Sale complete') . '</b><small>'
        . h(lang(array('string' => 'Order #{var:1} · {var:2}', 'vars' => array($r['order_number'], ($r['who'] !== '') ? $r['who'] : lang('Walk-in sale'))))) . '</small></div></div>'
        . '<div class="pg-sale-total pb-2"><div class="pg-sale-total-label">' . lang('Total') . '</div><div class="pg-sale-total-amount pg-sale-total-sm">' . local_sale_money($r['total']) . '</div><div class="pg-sale-total-sub">' . h($how) . '</div></div>';

    if (($r['method'] === 'cash') && ($r['received'] > $r['total'])) {
        $output .= '<div class="px-3 pb-3"><div class="pg-sale-change"><span>' . lang('Change due') . '</span><b>' . local_sale_money($r['received'] - $r['total']) . '</b></div></div>';
    }

    if (!empty($r['invoice']) || !empty($r['receipt']) || !empty($r['receipts'])) {
        $output .= '<ul class="pg-sale-done-list">';
        if (!empty($r['invoice'])) {
            $text = h(lang(array('string' => 'Invoice {var:1}', 'vars' => array($r['invoice']['number']))));
            $output .= '<li><i class="bi bi-receipt" aria-hidden="true"></i>'
                . ($can_erp ? '<a href="edit_erp_invoice.php?id=' . (int) $r['invoice']['id'] . '">' . $text . '</a>' : '<span>' . $text . '</span>')
                . (($r['invoice']['type'] !== '') ? '<span class="badge text-bg-info ms-auto">' . h($r['invoice']['type']) . '</span>' : '') . '</li>';
        }
        if (!empty($r['receipt'])) {
            $text = h(lang(array('string' => 'Receipt #{var:1}', 'vars' => array($r['receipt']['id']))));
            $output .= '<li><i class="bi bi-cash-coin" aria-hidden="true"></i>'
                . ($can_erp ? '<a href="erp_receipt.php?id=' . (int) $r['receipt']['id'] . '">' . $text . '</a>' : '<span>' . $text . '</span>')
                . '<span class="badge text-bg-success ms-auto">' . lang('invoice paid') . '</span></li>';
        }
        foreach ((array) ($r['receipts'] ?? array()) as $receipt) {
            $text = h(lang(array('string' => 'Receipt #{var:1}', 'vars' => array($receipt['id']))));
            $output .= '<li><i class="bi ' . (($receipt['method'] === 'card') ? 'bi-credit-card' : 'bi-cash-coin') . '" aria-hidden="true"></i>'
                . ($can_erp ? '<a href="erp_receipt.php?id=' . (int) $receipt['id'] . '">' . $text . '</a>' : '<span>' . $text . '</span>')
                . '<span class="small text-body-secondary">' . h(($methods[$receipt['method']] ?? '') . ' · ' . $receipt['till']) . '</span>'
                . '<span class="badge text-bg-success ms-auto">' . local_sale_money($receipt['amount']) . '</span></li>';
        }
        $output .= '</ul>';
    }

    foreach ($r['warnings'] as $warning) {
        $output .= '<div class="px-3 pb-2"><div class="alert alert-warning d-flex gap-2 mb-0 small"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>' . h($warning) . '</span></div></div>';
    }

    $output .= '<div class="pg-sale-done-actions">'
        . '<a class="btn btn-sm btn-outline-secondary" href="print_order.php?id=' . (int) $r['order_id'] . '" target="_blank" rel="noopener" data-sale-print><i class="bi bi-printer me-1" aria-hidden="true"></i>' . lang('Print') . '</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="view_order.php?id=' . (int) $r['order_id'] . '"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . lang('Open the order') . '</a>'
        . ($r['retry_invoice'] ? '<button type="button" class="btn btn-sm btn-outline-success no-submit" data-sale-invoice="' . (int) $r['order_id'] . '"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Try the invoice again') . '</button>' : '')
        . '</div>'
        . '<div class="pg-sale-next"><span>' . lang('Scanning the next product starts a new sale.') . '</span>'
        . '<button type="button" class="btn btn-sm btn-primary rounded-pill px-3 no-submit" data-sale-new>' . lang('New sale') . ' <kbd>Enter</kbd></button></div>'
        . '</div>';

    return $output;
}

/**
 * The drawer of recent counter sales.
 *
 * @param array $user
 * @return string
 */
function local_sale_render_recent($user)
{
    $erp = local_sale_erp_on();
    $can_erp = local_sale_can_erp($user);

    $rows = db_items(
        "SELECT o.id, o.order_number, o.order_date, o.total, o.contact_id,
                c.first_name, c.last_name, c.company,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count"
        . ($erp ? ", o.erp_invoice_id, o.erp_account_id, i.full_number AS invoice_number, a.title AS account_title" : "") . "
         FROM orders o
         LEFT JOIN contacts c ON c.id = o.contact_id"
        . ($erp ? "
         LEFT JOIN erp_invoices i ON i.id = o.erp_invoice_id
         LEFT JOIN erp_accounts a ON a.id = o.erp_account_id" : "") . "
         WHERE o.type = 'local' AND o.status = 'complete'
         ORDER BY o.order_date DESC, o.id DESC
         LIMIT 10"
    );

    if (empty($rows)) {
        return '<div class="p-4 text-center text-body-secondary">' . lang('No local orders yet.') . '</div>';
    }

    $output = '';

    foreach ($rows as $row) {
        $person = trim(trim((string) $row['first_name']) . ' ' . trim((string) $row['last_name']));
        $who = ($person !== '') ? $person : trim((string) $row['company']);
        if (($who === '') && $erp) {
            $who = trim((string) $row['account_title']);
        }
        if ($who === '') {
            $who = lang('Walk-in sale');
        }

        $invoice = '';
        if ($erp) {
            if ((int) $row['erp_invoice_id'] > 0) {
                $number = '<i class="bi bi-receipt me-1" aria-hidden="true"></i>' . h((string) $row['invoice_number']);
                $invoice = $can_erp
                    ? '<a class="badge text-bg-success text-decoration-none" href="edit_erp_invoice.php?id=' . (int) $row['erp_invoice_id'] . '">' . $number . '</a>'
                    : '<span class="badge text-bg-success">' . $number . '</span>';
            } else {
                $invoice = '<button type="button" class="btn btn-sm btn-outline-success no-submit" data-sale-invoice="' . (int) $row['id'] . '"><i class="bi bi-receipt me-1" aria-hidden="true"></i>' . lang('Issue the Invoice') . '</button>';
            }
        }

        $output .= '<div class="pg-sale-rs">'
            . '<div><b>#' . h($row['order_number']) . '</b> <small class="text-body-secondary">· ' . get_relative_time(array('timestamp' => $row['order_date'])) . '</small>'
            . '<br><small class="text-body-secondary">' . h(lang(array('string' => '{var:1} · {var:2} lines', 'vars' => array($who, (int) $row['item_count'])))) . '</small></div>'
            . '<div class="text-end fw-semibold">' . local_sale_money($row['total']) . '</div>'
            . '<div class="pg-sale-rs-acts">' . $invoice
            . '<a class="btn btn-sm btn-ghost ms-auto" href="view_order.php?id=' . (int) $row['id'] . '" title="' . h(lang('View')) . '" aria-label="' . h(lang('View')) . '"><i class="bi bi-eye" aria-hidden="true"></i></a></div>'
            . '</div>';
    }

    return $output;
}

/**
 * Everything the screen redraws after an action, as one JSON-ready array.
 *
 * @param array $user
 * @param array $res            the action's result
 * @param bool  $with_customer  also the customer box, invoice line and account card
 * @return array
 */
function local_sale_payload($user, $res, $with_customer)
{
    $order_id = local_sale_order_id();
    $lines = local_sale_lines($order_id);
    $totals = local_sale_totals($lines);

    $payload = array(
        'ok' => (bool) $res['ok'],
        'code' => (string) $res['code'],
        'message' => (string) $res['message'],
        'level' => (string) $res['level'],
        'field' => (string) $res['field'],
        'line_id' => (int) $res['line_id'],
        'field_errors' => isset($res['field_errors']) ? $res['field_errors'] : new stdClass(),
        'cart' => array(
            'count' => $totals['count'],
            'units' => $totals['units'],
            'total_cents' => $totals['gross'],
            'total_text' => local_sale_money_text($totals['gross']),
            'count_text' => ($totals['count'] > 0) ? lang(array('string' => '{var:1} lines · {var:2} units', 'vars' => array($totals['count'], $totals['units']))) : '',
            'rows' => local_sale_render_rows($lines, (array) $res['line_errors'], (int) $res['line_id']),
            'summary' => local_sale_render_summary($totals),
        ),
    );

    if ($with_customer || !empty($res['customer_changed'])) {
        $payload['customer'] = array(
            'html' => local_sale_render_customer($user),
            'invoice' => local_sale_render_invoice_note($user),
            'ledger' => local_sale_render_ledger($user),
        );
    }

    if (!empty($res['result'])) {
        $payload['result'] = array(
            'html' => local_sale_render_done($res['result'], $user),
            'order_id' => (int) $res['result']['order_id'],
        );
    }

    return $payload;
}
