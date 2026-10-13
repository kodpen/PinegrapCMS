<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - writing delivery notes, stock counts and minimum stock through the
 * external API.
 *
 * A delivery note is written the way the panel writes one: from an issued
 * invoice, from an order, or typed for an account. What the caller sends
 * takes the place of what the invoice or the order gives, the way an
 * operator edits the form the panel opens with it filled in. The note is then
 * invoiced or cancelled with the functions its screen calls (waybills.php).
 * Stock counts and minimums go through stock_counts.php and stock_levels.php
 * the same way, so an application changes stock only as an operator could.
 *
 * Every check is made before the first write, which is where X-Dry-Run stops
 * (includes/api/dry_run.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Delivery notes
   --------------------------------------------------------------------------- */

/**
 * The parameters of a note's header, by the column each one fills.
 *
 * @return array  parameter => erp_waybill_header_build() key
 */
function erp_api_waybill_header_params()
{
    return array(
        'issue_date' => 'issue_date',
        'ship_date' => 'ship_date',
        'ship_time' => 'ship_time',
        'carrier_title' => 'carrier_title',
        'carrier_tax_number' => 'carrier_vkn',
        'plate' => 'plate',
        'driver_name' => 'driver_name',
        'driver_id_number' => 'driver_tckn',
        'ship_to_title' => 'ship_to_title',
        'ship_to_address' => 'ship_to_address',
        'ship_to_district' => 'ship_to_district',
        'ship_to_city' => 'ship_to_city',
        'ship_to_state' => 'ship_to_state',
        'ship_to_country' => 'ship_to_country',
        'notes' => 'notes',
    );
}

/**
 * The parameter a refusal of erp_waybill_header_build() is about, read from
 * its message; null for one it does not name.
 *
 * @param string $error
 * @return string|null
 */
function erp_api_waybill_field($error)
{
    $error = (string) $error;

    $fields = array(
        lang('Choose an account.') => 'account_id',
        lang('Please enter a valid date.') => 'issue_date',
        lang('Please enter a valid shipment date.') => 'ship_date',
        lang('Please enter the shipment time as HH:MM.') => 'ship_time',
        lang('The carrier tax number has to be 10 or 11 digits.') => 'carrier_tax_number',
        lang('The driver ID number has to be 11 digits.') => 'driver_id_number',
    );

    if (isset($fields[$error])) {
        return $fields[$error];
    }

    if (strpos($error, lang('The carrier tax number:')) === 0) {
        return 'carrier_tax_number';
    }

    return null;
}

/**
 * The lines of a delivery note as sent, checked and shaped the way
 * erp_waybill_lines_build() reads them.
 *
 * Each line has to be an object carrying only the fields the route names. A
 * line tied to a product takes the product's name when the description is
 * left out; without a product the description is required.
 *
 * @param mixed $raw  The lines parameter
 * @return array
 */
function erp_api_waybill_lines_in($raw)
{
    $known = array('product_id', 'description', 'quantity', 'unit');
    $lines = array();
    $product_ids = array();

    foreach (array_values((array) $raw) as $index => $line) {
        $field = 'lines[' . $index . '].';

        if (!is_array($line) || (!empty($line) && (array_keys($line) === range(0, count($line) - 1)))) {
            api_fail_validation(lang('Each line must be an object.'), 'lines[' . $index . ']');
        }

        foreach (array_keys($line) as $key) {
            if (!in_array((string) $key, $known, true)) {
                api_fail_validation(lang(array('string' => 'A line does not take a field called {var:1}.', 'vars' => mb_substr((string) $key, 0, 60))), $field . mb_substr((string) $key, 0, 60));
            }
        }

        $given = function ($name) use ($line) {
            return array_key_exists($name, $line) && ($line[$name] !== null) && ($line[$name] !== '');
        };

        $product_id = 0;

        if ($given('product_id')) {
            if (is_bool($line['product_id']) || !preg_match('/^\d+$/', (string) $line['product_id'])) {
                api_fail_validation(lang(array('string' => '{var:1} must be a whole number.', 'vars' => $field . 'product_id')), $field . 'product_id');
            }

            $product_id = (int) $line['product_id'];
        }

        $quantity = 1.0;

        if ($given('quantity')) {
            $quantity = erp_api_line_number($line['quantity']);

            // Kept to four decimals, so what rounds to nothing is nothing.
            if (($quantity === null) || (round($quantity, 4) <= 0) || ($quantity > 999999999)) {
                api_fail_validation(lang(array('string' => '{var:1} must be a number greater than zero.', 'vars' => $field . 'quantity')), $field . 'quantity');
            }
        }

        $text = array();

        foreach (array('description' => 255, 'unit' => 10) as $name => $max) {
            $text[$name] = '';

            if ($given($name)) {
                if (!is_string($line[$name])) {
                    api_fail_validation(lang(array('string' => '{var:1} must be text.', 'vars' => $field . $name)), $field . $name);
                }

                $text[$name] = trim($line[$name]);

                if (mb_strlen($text[$name]) > $max) {
                    api_fail_validation(lang(array('string' => '{var:1} is longer than the {var:2} characters accepted.', 'vars' => array($field . $name, $max))), $field . $name);
                }
            }
        }

        $lines[$index] = array(
            'product_id' => $product_id,
            'description' => $text['description'],
            // A point for the decimals, which erp_quantity_in() reads as one.
            'quantity' => number_format($quantity, 4, '.', ''),
            'unit_code' => strtoupper($text['unit']),
        );

        if ($product_id > 0) {
            $product_ids[$product_id] = true;
        }
    }

    $products = array();

    if (!empty($product_ids)) {
        foreach ((array) db_items("SELECT " . erp_product_columns('p') . " FROM products p WHERE p.id IN (" . implode(',', array_keys($product_ids)) . ")") as $row) {
            $products[(int) $row['id']] = $row;
        }
    }

    $out = array();

    foreach ($lines as $index => $line) {
        $field = 'lines[' . $index . '].';

        if ($line['product_id'] > 0) {
            if (!isset($products[$line['product_id']])) {
                api_fail_validation(lang('That product could not be found.'), $field . 'product_id');
            }

            if ($line['description'] === '') {
                $line['description'] = mb_substr((string) erp_product_for_line($products[$line['product_id']])['name'], 0, 255);
            }
        }

        if ($line['description'] === '') {
            api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => $field . 'description')), $field . 'description');
        }

        $out[] = $line;
    }

    return $out;
}

function erp_api_waybills_create($params)
{
    $sent = function ($name) use ($params) {
        return array_key_exists($name, $params);
    };

    $invoice_id = (int) ($params['invoice_id'] ?? 0);
    $order_id = (int) ($params['order_id'] ?? 0);
    $owner = (int) erp_api_owner()['id'];

    if (($invoice_id > 0) && ($order_id > 0)) {
        api_fail_validation(lang('Send invoice_id or order_id, not both.'), 'order_id');
    }

    if ((($invoice_id > 0) || ($order_id > 0)) && $sent('account_id')) {
        api_fail_validation(lang('The account comes from the invoice or the order, so account_id is not sent with them.'), 'account_id');
    }

    // What the invoice or the order gives, as the panel fills the form with
    // it. Read first without writing anything; a customer who has no account
    // yet gets one only once the rehearsal is over.
    $read = function ($open_account) use ($invoice_id, $order_id, $owner) {
        if ($invoice_id > 0) {
            $from = erp_waybill_data_from_invoice($invoice_id, $owner, $open_account);

            if (!$from['success']) {
                api_fail(409, 'invoice_not_shippable', $from['error']);
            }

            return $from['data'];
        }

        if ($order_id > 0) {
            $from = erp_waybill_data_from_order($order_id, $owner, $open_account);

            if (!$from['success']) {
                api_fail_validation($from['error'], 'order_id');
            }

            return $from['data'];
        }

        return array('account_id' => 0, 'order_id' => 0, 'invoice_id' => 0, 'lines' => array());
    };

    // What was sent, over what was read. Lines sent replace the goods of the
    // invoice or the order: a part of them going out.
    $compose = function ($data) use ($params, $sent) {
        foreach (erp_api_waybill_header_params() as $param => $key) {
            if ($sent($param)) {
                $data[$key] = in_array($param, array('issue_date', 'ship_date'), true)
                    ? erp_api_day($params[$param])
                    : (string) $params[$param];
            }
        }

        if ($sent('account_id')) {
            $data['account_id'] = (int) $params['account_id'];
        }

        if ($sent('lines')) {
            $data['lines'] = erp_api_waybill_lines_in($params['lines']);
        }

        return erp_waybill_ship_to_default($data);
    };

    // The order the note would ship: the one sent, or the one the invoice
    // was written from, which then ships as that order's note.
    $ships_order = $order_id;

    if ($invoice_id > 0) {
        $invoice = db_item("SELECT id, full_number, order_id FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

        if (!is_array($invoice)) {
            api_fail_validation(lang('The invoice could not be found.'), 'invoice_id');
        }

        $shipped = erp_waybills_for_invoice($invoice_id);

        if ($shipped) {
            api_fail(409, 'invoice_already_shipped', lang(array('string' => 'Invoice {var:1} already has delivery note {var:2}.', 'vars' => array((string) $invoice['full_number'], (string) $shipped[0]['full_number']))));
        }

        $ships_order = (int) $invoice['order_id'];
    } elseif ($order_id > 0) {
        if ((int) db_value("SELECT id FROM orders WHERE id = '" . $order_id . "' LIMIT 1") <= 0) {
            api_fail_validation(lang('Order not found.'), 'order_id');
        }
    } else {
        if (!$sent('account_id')) {
            api_fail_validation(lang('Send invoice_id, order_id, or account_id with the lines.'), 'account_id');
        }

        if (!$sent('lines')) {
            api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => 'lines')), 'lines');
        }
    }

    if ($ships_order > 0) {
        $shipped = erp_waybills_for_order($ships_order);

        if ($shipped) {
            $order_number = (string) db_value("SELECT order_number FROM orders WHERE id = '" . $ships_order . "' LIMIT 1");

            api_fail(409, 'order_already_shipped', lang(array('string' => 'Order #{var:1} already has delivery note {var:2}.', 'vars' => array(($order_number !== '') ? $order_number : (string) $ships_order, (string) $shipped[0]['full_number']))));
        }
    }

    $data = $compose($read(false));
    $to_open = ((int) $data['account_id'] <= 0);

    $header = erp_waybill_header_build($data, !$to_open);

    if ($header['error'] !== '') {
        api_fail_validation($header['error'], erp_api_waybill_field($header['error']));
    }

    $built = erp_waybill_lines_build($data['lines']);

    if ($built['error'] !== '') {
        api_fail_validation($built['error'], 'lines');
    }

    if (empty($built['lines'])) {
        api_fail_validation(lang('Enter at least one line.'), 'lines');
    }

    api_dry_run_stop('created', 'erp_waybill', array(
        'account_id' => (int) $data['account_id'],
        'order_id' => (int) ($data['order_id'] ?? 0),
        'invoice_id' => (int) ($data['invoice_id'] ?? 0),
        'lines' => count($built['lines']),
    ));

    if ($to_open) {
        $data = $compose($read(true));
    }

    $data['created_by'] = $owner;

    $made = erp_waybill_create($data);

    if (!$made['success']) {
        api_fail_validation($made['error'], erp_api_waybill_field($made['error']));
    }

    erp_api_log(lang(array(
        'string' => 'ERP delivery note {var:1} was created through the API.',
        'vars' => (string) $made['full_number'],
    )));

    erp_api_waybill_answer((int) $made['waybill_id'], 201);
}

/**
 * The delivery note a write is about, or a 404.
 *
 * @param int $waybill_id
 * @return array  erp_waybill() row
 */
function erp_api_waybill_for_write($waybill_id)
{
    $waybill = erp_waybill((int) $waybill_id);

    if (!is_array($waybill) || ((string) $waybill['status'] === 'draft')) {
        api_fail_not_found(lang('Delivery Note'));
    }

    return $waybill;
}

function erp_api_waybills_cancel($params)
{
    $waybill = erp_api_waybill_for_write((int) $params['id']);

    if ((string) $waybill['status'] === 'cancelled') {
        api_fail(409, 'waybill_cancelled', lang('This delivery note is already cancelled.'));
    }

    if (erp_waybill_invoice_state($waybill) !== 'none') {
        api_fail(409, 'waybill_invoiced', lang('This delivery note has been invoiced. Cancel or return the invoice instead.'));
    }

    api_dry_run_stop('cancelled', 'erp_waybill', array('id' => (int) $waybill['id'], 'number' => (string) $waybill['full_number']));

    $cancelled = erp_waybill_cancel((int) $waybill['id']);

    if (!$cancelled['success']) {
        api_fail_validation((string) $cancelled['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP delivery note {var:1} was cancelled through the API.',
        'vars' => (string) $waybill['full_number'],
    )));

    erp_api_waybill_answer((int) $waybill['id']);
}

function erp_api_waybills_invoice($params)
{
    $waybill = erp_api_waybill_for_write((int) $params['id']);

    if ((string) $waybill['status'] === 'cancelled') {
        api_fail(409, 'waybill_cancelled', lang('A cancelled delivery note cannot be invoiced.'));
    }

    if (erp_waybill_invoice_state($waybill) !== 'none') {
        api_fail(409, 'waybill_invoiced', lang('This delivery note has been invoiced already.'));
    }

    $order_id = (int) $waybill['order_id'];
    $linked = 0;

    if ($order_id > 0) {
        // An order already invoiced only ties the note to that invoice; one
        // that is not is invoiced the way POST /erp/orders/{order_id}/invoice
        // does it, and refused for the same reasons.
        $linked = (int) erp_invoice_for_order($order_id);

        if ($linked <= 0) {
            $order = db_item("SELECT * FROM orders WHERE id = '" . $order_id . "' LIMIT 1");

            if (!is_array($order)) {
                api_fail_validation(lang('Order not found.'), 'id');
            }

            erp_api_order_invoice_check($order);
        }
    } elseif (!is_array(erp_account((int) $waybill['account_id']))) {
        api_fail_validation(lang('That account could not be found.'), 'id');
    }

    api_dry_run_stop(($linked > 0) ? 'linked' : 'created', 'erp_invoice', array(
        'waybill_id' => (int) $waybill['id'],
        'order_id' => $order_id,
        'invoice_id' => $linked,
        'draft' => ($order_id <= 0),
    ));

    $made = erp_waybill_invoice((int) $waybill['id'], erp_api_owner()['id']);

    if (!$made['success']) {
        api_fail_validation((string) $made['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP delivery note {var:1} was invoiced through the API.',
        'vars' => (string) $waybill['full_number'],
    )));

    erp_api_invoice_answer((int) $made['invoice_id'], ($linked > 0) ? 200 : 201);
}

/* ---------------------------------------------------------------------------
   Stock counts
   --------------------------------------------------------------------------- */

/**
 * The count a write is about, open, or a 404 or a 409.
 *
 * @param int $count_id
 * @return array  erp_stock_count() row
 */
function erp_api_stock_count_for_write($count_id)
{
    erp_api_stock_counts_ready();

    $count = erp_stock_count((int) $count_id);

    if ($count === null) {
        api_fail_not_found(lang('Stock count'));
    }

    if ((string) $count['status'] !== 'open') {
        api_fail(409, 'stock_count_not_open', lang('Only an open count can be changed.'));
    }

    return $count;
}

function erp_api_stock_counts_create($params)
{
    erp_api_stock_counts_ready();

    $title = trim((string) ($params['title'] ?? ''));

    api_dry_run_stop('created', 'erp_stock_count', array('title' => $title));

    $count_id = erp_stock_count_create($title, erp_api_owner()['id']);

    if ($count_id <= 0) {
        api_fail_server();
    }

    erp_api_log(lang(array(
        'string' => 'ERP stock count #{var:1} was started through the API.',
        'vars' => $count_id,
    )));

    erp_api_stock_count_answer($count_id, 201);
}

function erp_api_stock_counts_items($params)
{
    $count = erp_api_stock_count_for_write((int) $params['id']);
    $known = array('product_id', 'code', 'quantity', 'mode');
    $steps = array();

    foreach (array_values((array) $params['items']) as $index => $item) {
        $field = 'items[' . $index . '].';

        if (!is_array($item) || (!empty($item) && (array_keys($item) === range(0, count($item) - 1)))) {
            api_fail_validation(lang('Each item must be an object.'), 'items[' . $index . ']');
        }

        foreach (array_keys($item) as $key) {
            if (!in_array((string) $key, $known, true)) {
                api_fail_validation(lang(array('string' => 'An item does not take a field called {var:1}.', 'vars' => mb_substr((string) $key, 0, 60))), $field . mb_substr((string) $key, 0, 60));
            }
        }

        $mode = isset($item['mode']) ? $item['mode'] : 'add';

        if (!in_array($mode, array('add', 'set', 'remove'), true)) {
            api_fail_validation(lang(array('string' => '{var:1} must be one of: {var:2}.', 'vars' => array($field . 'mode', 'add, set, remove'))), $field . 'mode');
        }

        $has_id = isset($item['product_id']) && ($item['product_id'] !== '');
        $has_code = isset($item['code']) && ($item['code'] !== '');

        if ($has_id === $has_code) {
            api_fail_validation(lang('Name the product with product_id or with code, one of the two.'), $field . 'product_id');
        }

        if ($has_id) {
            if (is_bool($item['product_id']) || !preg_match('/^\d+$/', (string) $item['product_id'])) {
                api_fail_validation(lang(array('string' => '{var:1} must be a whole number.', 'vars' => $field . 'product_id')), $field . 'product_id');
            }

            $product = db_item("SELECT " . erp_product_columns('p') . " FROM products p WHERE p.id = '" . (int) $item['product_id'] . "' LIMIT 1");

            if (!is_array($product)) {
                api_fail_validation(lang('That product could not be found.'), $field . 'product_id');
            }
        } else {
            if (!is_string($item['code'])) {
                api_fail_validation(lang(array('string' => '{var:1} must be text.', 'vars' => $field . 'code')), $field . 'code');
            }

            $product = erp_product_by_barcode($item['code']);

            if (!is_array($product)) {
                api_fail_validation(lang(array('string' => 'No product carries the code {var:1}.', 'vars' => mb_substr(trim($item['code']), 0, 100))), $field . 'code');
            }
        }

        $quantity = 0;

        if ($mode !== 'remove') {
            if ((int) $product['inventory'] !== 1) {
                $name = (trim((string) ($product['short_description'] ?? '')) !== '') ? (string) $product['short_description'] : (string) $product['name'];

                api_fail_validation(lang(array('string' => '{var:1} does not track stock, so it is not counted.', 'vars' => $name)), $field . ($has_id ? 'product_id' : 'code'));
            }

            // One piece for a scan; a count that is set has to say what to.
            $raw = $item['quantity'] ?? (($mode === 'add') ? 1 : null);
            $min = ($mode === 'add') ? 1 : 0;

            if (($raw === null) || is_bool($raw) || !preg_match('/^\d{1,7}$/', (string) $raw) || ((int) $raw < $min) || ((int) $raw > 1000000)) {
                api_fail_validation(lang(array('string' => '{var:1} must be a whole number from {var:2} to {var:3}.', 'vars' => array($field . 'quantity', $min, 1000000))), $field . 'quantity');
            }

            $quantity = (int) $raw;
        }

        $steps[] = array('mode' => $mode, 'product' => $product, 'quantity' => $quantity);
    }

    if (empty($steps)) {
        api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => 'items')), 'items');
    }

    api_dry_run_stop('changed', 'erp_stock_count', array('id' => (int) $count['id'], 'items' => count($steps)));

    // All the items or none of them: a request replayed after a failure
    // must not count a piece twice.
    if (!erp_tx_begin()) {
        api_fail_server(lang('Could not start a database transaction.'));
    }

    foreach ($steps as $step) {
        if ($step['mode'] === 'remove') {
            $done = erp_stock_count_save((int) $count['id'], array((int) $step['product']['id'] => ''));
        } else {
            $done = erp_stock_count_add((int) $count['id'], $step['product'], $step['quantity'], ($step['mode'] === 'set'));
        }

        if (!$done['success']) {
            erp_tx_rollback();
            api_fail(409, 'stock_count_not_open', (string) $done['error']);
        }
    }

    if (!erp_tx_commit()) {
        erp_tx_rollback();
        api_fail_server();
    }

    erp_api_log(lang(array(
        'string' => 'ERP stock count {var:1} was changed through the API: {var:2} item(s).',
        'vars' => array((string) $count['title'], count($steps)),
    )));

    erp_api_stock_count_answer((int) $count['id']);
}

function erp_api_stock_counts_apply($params)
{
    $count = erp_api_stock_count_for_write((int) $params['id']);

    $lines = db_item("SELECT COUNT(*) AS line_count, SUM(i.counted <> p.inventory_quantity) AS changes
        FROM erp_stock_count_items i
        INNER JOIN products p ON p.id = i.product_id
        WHERE i.count_id = '" . (int) $count['id'] . "'");

    if ((int) ($lines['line_count'] ?? 0) <= 0) {
        api_fail(409, 'stock_count_empty', lang('Nothing has been counted yet.'));
    }

    api_dry_run_stop('applied', 'erp_stock_count', array(
        'id' => (int) $count['id'],
        'lines' => (int) $lines['line_count'],
        'changes' => (int) $lines['changes'],
    ));

    $applied = erp_stock_count_apply((int) $count['id'], erp_api_owner()['id']);

    if (!$applied['success']) {
        api_fail_validation((string) $applied['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP stock count {var:1} was applied through the API: {var:2} of {var:3} product(s) changed.',
        'vars' => array((string) $count['title'], (int) $applied['changed'], (int) $applied['lines']),
    )));

    erp_api_stock_count_answer((int) $count['id']);
}

function erp_api_stock_counts_cancel($params)
{
    $count = erp_api_stock_count_for_write((int) $params['id']);

    api_dry_run_stop('cancelled', 'erp_stock_count', array('id' => (int) $count['id']));

    if (!erp_stock_count_cancel((int) $count['id'])) {
        api_fail(409, 'stock_count_not_open', lang('Only an open count can be changed.'));
    }

    erp_api_log(lang(array(
        'string' => 'ERP stock count {var:1} was cancelled through the API.',
        'vars' => (string) $count['title'],
    )));

    erp_api_stock_count_answer((int) $count['id']);
}

/* ---------------------------------------------------------------------------
   Minimum stock
   --------------------------------------------------------------------------- */

function erp_api_stock_minimums_save($params)
{
    erp_api_stock_minimums_ready();

    $values = array();
    $fields = array();

    foreach (array_values((array) $params['minimums']) as $index => $item) {
        $field = 'minimums[' . $index . '].';

        if (!is_array($item) || (!empty($item) && (array_keys($item) === range(0, count($item) - 1)))) {
            api_fail_validation(lang('Each item must be an object.'), 'minimums[' . $index . ']');
        }

        foreach (array_keys($item) as $key) {
            if (!in_array((string) $key, array('product_id', 'minimum'), true)) {
                api_fail_validation(lang(array('string' => 'An item does not take a field called {var:1}.', 'vars' => mb_substr((string) $key, 0, 60))), $field . mb_substr((string) $key, 0, 60));
            }
        }

        foreach (array('product_id' => 1, 'minimum' => 0) as $name => $min) {
            $raw = $item[$name] ?? null;
            $max = ($name === 'minimum') ? ERP_STOCK_MINIMUM_MAX : 4294967295;

            if (($raw === null) || is_bool($raw) || !preg_match('/^\d{1,10}$/', (string) $raw) || ((int) $raw < $min) || ((int) $raw > $max)) {
                api_fail_validation(lang(array('string' => '{var:1} must be a whole number from {var:2} to {var:3}.', 'vars' => array($field . $name, $min, $max))), $field . $name);
            }
        }

        $product_id = (int) $item['product_id'];

        if (isset($values[$product_id])) {
            api_fail_validation(lang('The same product is named twice.'), $field . 'product_id');
        }

        $values[$product_id] = (int) $item['minimum'];
        $fields[$product_id] = $field . 'product_id';
    }

    if (empty($values)) {
        api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => 'minimums')), 'minimums');
    }

    // erp_stock_minimums_save() passes over a product that does not track
    // stock without a word; the caller is told instead.
    $products = array();

    foreach ((array) db_items("SELECT id, name, short_description, inventory FROM products WHERE id IN (" . implode(',', array_keys($values)) . ")") as $row) {
        $products[(int) $row['id']] = $row;
    }

    foreach ($values as $product_id => $minimum) {
        if (!isset($products[$product_id])) {
            api_fail_validation(lang('That product could not be found.'), $fields[$product_id]);
        }

        if ((int) $products[$product_id]['inventory'] !== 1) {
            api_fail_validation(lang(array('string' => '{var:1} does not track stock, so it takes no minimum.', 'vars' => erp_stock_product_label(array(
                'product_name' => $products[$product_id]['short_description'],
                'product_sku' => $products[$product_id]['name'],
            )))), $fields[$product_id]);
        }
    }

    api_dry_run_stop('changed', 'erp_stock_minimum', array('products' => count($values)));

    $saved = erp_stock_minimums_save(array_map('strval', $values), erp_api_owner()['id']);

    if (!$saved['success']) {
        api_fail_validation((string) $saved['error'], 'minimums');
    }

    erp_api_log(lang(array(
        'string' => 'ERP minimum stock was changed through the API for {var:1} product(s).',
        'vars' => (int) $saved['changed'],
    )));

    $levels = array();

    foreach ((array) db_items("SELECT p.id, p.name AS product_sku, p.short_description AS product_name,
            p.inventory_quantity, p.enabled, COALESCE(m.min_quantity, 0) AS min_quantity
        FROM products p
        LEFT JOIN erp_stock_minimums m ON m.product_id = p.id
        WHERE p.id IN (" . implode(',', array_keys($values)) . ")
        ORDER BY p.id ASC") as $row) {
        $levels[] = erp_api_stock_level_present($row);
    }

    api_ok(array('changed' => (int) $saved['changed'], 'levels' => $levels));
}
