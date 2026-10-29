<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Cart quotes: a list of products and quantities staff prepare for a
 * customer and send as a link (cart_quote.php). The link puts the lines into
 * the visitor's cart at the catalogue's prices of that moment: no price is
 * kept with a quote, so the cart, the offers and the checkout price it as
 * any other cart.
 *
 * The link's token is the credential, kept only as its SHA-256
 * (cart_quotes.token_hash) with its first characters (token_hint) for the
 * panel, as the approval links of the Workspace keep theirs. Opening the
 * link changes nothing (a mail scanner or a preview spends nothing); the
 * visitor's POST fills the cart. A quote is open until valid_until passes,
 * staff cancel it or the order it went into completes (status ordered,
 * order_id that order). The link shows products and quantities only, never
 * whom the quote was made for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most lines one cart quote holds. */
define('PG_CART_QUOTE_MAX_LINES', 100);

/** The largest quantity of one line. */
define('PG_CART_QUOTE_MAX_QUANTITY', 100000);

/**
 * Are the tables there (2026.11.0, 11.0.124)?
 *
 * @return bool
 */
function pg_cart_quotes_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('cart_quotes', 'token_hash') && pg_schema_has('cart_quote_items', 'quantity');
    }

    return $ready;
}

/* ---------------------------------------------------------------------------
   Pure rules
   --------------------------------------------------------------------------- */

/**
 * The statuses a quote is stored with.
 *
 * @return string[]
 */
function pg_cart_quote_statuses()
{
    return array('open', 'ordered', 'expired', 'cancelled');
}

/**
 * Is this the shape of a link's token (pg_short_link_new_token(): 43
 * characters of base64url)? Pure.
 *
 * @param mixed $token
 * @return bool
 */
function pg_cart_quote_token_shape($token)
{
    return is_string($token) && (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1);
}

/**
 * What is kept of a token: its SHA-256 and its first six characters. Pure.
 *
 * @param string $token
 * @return array hash, hint
 */
function pg_cart_quote_token_parts($token)
{
    return array('hash' => hash('sha256', (string) $token), 'hint' => substr((string) $token, 0, 6));
}

/**
 * Where a quote stands now: its stored status, except that an open quote
 * past valid_until is expired (0 never expires). Pure.
 *
 * @param array $quote cart_quotes row
 * @param int   $now
 * @return string open | ordered | expired | cancelled
 */
function pg_cart_quote_state(array $quote, $now)
{
    $status = (string) ($quote['status'] ?? '');

    if (!in_array($status, pg_cart_quote_statuses(), true)) {
        return 'cancelled';
    }

    $until = (int) ($quote['valid_until'] ?? 0);

    if (($status === 'open') && ($until > 0) && ($until < (int) $now)) {
        return 'expired';
    }

    return $status;
}

/**
 * The lines a form or the API sends, made fit to keep: a product id and a
 * whole quantity of at least one, each product once (quantities of a
 * repeated product added up, up to the largest quantity), at most
 * PG_CART_QUOTE_MAX_LINES lines. Pure.
 *
 * @param mixed $given list of [product_id, quantity]
 * @return array lines (list of product_id, quantity), problem: '' | empty | quantity | many
 */
function pg_cart_quote_lines_clean($given)
{
    $lines = array();

    foreach (is_array($given) ? $given : array() as $row) {
        if (!is_array($row)) {
            return array('lines' => array(), 'problem' => 'quantity');
        }

        $product = $row['product_id'] ?? '';
        $quantity = $row['quantity'] ?? '';

        if ((!is_int($product) && !(is_string($product) && preg_match('/^\d+$/', $product))) || ((int) $product <= 0)) {
            return array('lines' => array(), 'problem' => 'quantity');
        }

        if ((!is_int($quantity) && !(is_string($quantity) && preg_match('/^\d+$/', $quantity))) || ((int) $quantity < 1)) {
            return array('lines' => array(), 'problem' => 'quantity');
        }

        $product = (int) $product;
        $sum = (isset($lines[$product]) ? $lines[$product] : 0) + (int) $quantity;
        $lines[$product] = min($sum, PG_CART_QUOTE_MAX_QUANTITY);
    }

    if (!$lines) {
        return array('lines' => array(), 'problem' => 'empty');
    }

    if (count($lines) > PG_CART_QUOTE_MAX_LINES) {
        return array('lines' => array(), 'problem' => 'many');
    }

    $out = array();

    foreach ($lines as $product => $quantity) {
        $out[] = array('product_id' => $product, 'quantity' => $quantity);
    }

    return array('lines' => $out, 'problem' => '');
}

/**
 * What the link puts into the cart: each line of the quote held against its
 * product as the catalogue has it now. A product that is gone or disabled,
 * a donation (its amount is the visitor's to give) and a product out of
 * stock without backorder are left out; a quantity is brought within the
 * product's minimum and maximum and, without backorder, within the stock.
 * Pure.
 *
 * @param array $lines    list of product_id, quantity (cart_quote_items)
 * @param array $products product id => products row (enabled, selection_type,
 *                        inventory, inventory_quantity, backorder,
 *                        minimum_quantity, maximum_quantity)
 * @return array add (list of product_id, quantity), skipped (list of
 *               product_id, reason: missing | unavailable | stock)
 */
function pg_cart_quote_plan(array $lines, array $products)
{
    $add = array();
    $skipped = array();

    foreach ($lines as $line) {
        $id = (int) ($line['product_id'] ?? 0);
        $quantity = max(1, (int) ($line['quantity'] ?? 1));
        $product = isset($products[$id]) ? $products[$id] : null;

        if (!is_array($product)) {
            $skipped[] = array('product_id' => $id, 'reason' => 'missing');
            continue;
        }

        if (((string) ($product['enabled'] ?? '0') !== '1') || ((string) ($product['selection_type'] ?? '') === 'donation')) {
            $skipped[] = array('product_id' => $id, 'reason' => 'unavailable');
            continue;
        }

        $minimum = (int) ($product['minimum_quantity'] ?? 0);
        $maximum = (int) ($product['maximum_quantity'] ?? 0);

        if ($minimum > 0) {
            $quantity = max($quantity, $minimum);
        }

        if ($maximum > 0) {
            $quantity = min($quantity, $maximum);
        }

        if (((int) ($product['inventory'] ?? 0) === 1) && ((int) ($product['backorder'] ?? 0) !== 1)) {
            $stock = (int) ($product['inventory_quantity'] ?? 0);

            if (($stock <= 0) || ($stock < $minimum)) {
                $skipped[] = array('product_id' => $id, 'reason' => 'stock');
                continue;
            }

            $quantity = min($quantity, $stock);
        }

        $add[] = array('product_id' => $id, 'quantity' => $quantity);
    }

    return array('add' => $add, 'skipped' => $skipped);
}

/* ---------------------------------------------------------------------------
   Rows
   --------------------------------------------------------------------------- */

/**
 * Makes a quote: its lines checked (pg_cart_quote_lines_clean()) and a new
 * link. The token is returned once and never kept.
 *
 * @param int   $contact_id  whom it is for (0 for nobody named)
 * @param array $lines       list of product_id, quantity
 * @param int   $valid_until unix seconds, 0 for no end
 * @param int   $user_id     who made it
 * @return array ok, error, id, token
 */
function pg_cart_quote_create($contact_id, $lines, $valid_until, $user_id)
{
    $fail = array('ok' => false, 'error' => lang('The cart quote could not be saved.'), 'id' => 0, 'token' => '');

    if (!pg_cart_quotes_ready()) {
        return $fail;
    }

    $clean = pg_cart_quote_lines_clean($lines);

    if ($clean['problem'] !== '') {
        $fail['error'] = ($clean['problem'] === 'many')
            ? lang(array('string' => 'A cart quote holds at most {var:1} products.', 'vars' => PG_CART_QUOTE_MAX_LINES))
            : lang('Add at least one product with a quantity of one or more.');

        return $fail;
    }

    $now = time();
    $token = pg_short_link_new_token();
    $parts = pg_cart_quote_token_parts($token);

    db_q("INSERT INTO cart_quotes (token_hash, token_hint, contact_id, valid_until, status, order_id, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'open', 0, ?, ?, ?)",
        array($parts['hash'], $parts['hint'], (string) max(0, (int) $contact_id), (string) max(0, (int) $valid_until), (string) (int) $user_id, (string) $now, (string) $now))
        or output_error(lang('Query failed.'));

    $id = (int) mysqli_insert_id(db::$con);

    if ($id <= 0) {
        return $fail;
    }

    foreach ($clean['lines'] as $line) {
        db_q("INSERT INTO cart_quote_items (cart_quote_id, product_id, quantity) VALUES (?, ?, ?)",
            array((string) $id, (string) $line['product_id'], (string) $line['quantity'])) or output_error(lang('Query failed.'));
    }

    return array('ok' => true, 'error' => '', 'id' => $id, 'token' => $token);
}

/**
 * The quote a token opens, if any.
 *
 * @param string $token
 * @return array|null
 */
function pg_cart_quote_for_token($token)
{
    if (!pg_cart_quotes_ready() || !pg_cart_quote_token_shape($token)) {
        return null;
    }

    $parts = pg_cart_quote_token_parts($token);
    $row = db_item("SELECT * FROM cart_quotes WHERE token_hash = '" . e($parts['hash']) . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * A quote's lines, in the order they were made.
 *
 * @param int $quote_id
 * @return array list of product_id, quantity
 */
function pg_cart_quote_lines($quote_id)
{
    return (array) db_items("SELECT product_id, quantity FROM cart_quote_items WHERE cart_quote_id = '" . (int) $quote_id . "' ORDER BY id");
}

/**
 * The catalogue rows of a quote's products, by id.
 *
 * @param array $lines list of product_id, quantity
 * @return array product id => products row
 */
function pg_cart_quote_products(array $lines)
{
    $ids = array();

    foreach ($lines as $line) {
        $ids[] = (int) $line['product_id'];
    }

    $ids = array_values(array_unique(array_filter($ids)));

    if (!$ids) {
        return array();
    }

    $products = array();

    foreach ((array) db_items("SELECT id, name, short_description, enabled, selection_type, inventory, inventory_quantity, backorder, minimum_quantity, maximum_quantity
        FROM products WHERE id IN (" . implode(',', $ids) . ")") as $row) {
        $products[(int) $row['id']] = $row;
    }

    return $products;
}

/**
 * What the link's page shows: where the quote stands and, while it is open,
 * the products and quantities the cart will get. Nothing about whom the
 * quote was made for. Reads only.
 *
 * @param string $token
 * @return array state (open | ordered | expired | cancelled | invalid),
 *               lines (list of name, quantity), skipped (count), valid_until
 */
function pg_cart_quote_view($token)
{
    $quote = pg_cart_quote_for_token($token);

    if ($quote === null) {
        return array('state' => 'invalid', 'lines' => array(), 'skipped' => 0, 'valid_until' => 0);
    }

    $state = pg_cart_quote_state($quote, time());
    $view = array('state' => $state, 'lines' => array(), 'skipped' => 0, 'valid_until' => (int) $quote['valid_until']);

    if ($state !== 'open') {
        return $view;
    }

    $lines = pg_cart_quote_lines((int) $quote['id']);
    $products = pg_cart_quote_products($lines);
    $plan = pg_cart_quote_plan($lines, $products);

    foreach ($plan['add'] as $line) {
        $product = $products[$line['product_id']];
        $name = trim((string) $product['short_description']);

        $view['lines'][] = array(
            'name'     => ($name !== '') ? $name : (string) $product['name'],
            'quantity' => (int) $line['quantity'],
        );
    }

    $view['skipped'] = count($plan['skipped']);

    return $view;
}

/**
 * Puts an open quote's lines into the visitor's cart through
 * add_order_item(), then prices the cart and applies the offers as any
 * addition does. The quote remembers the cart (order_id) so its completion
 * marks the quote ordered; a second POST into the same cart adds nothing.
 *
 * @param string $token
 * @return array ok, error, added (lines added), again (the cart already had them)
 */
function pg_cart_quote_apply($token)
{
    $fail = array('ok' => false, 'error' => lang('This link is not valid.'), 'added' => 0, 'again' => false);
    $quote = pg_cart_quote_for_token($token);

    if ($quote === null) {
        return $fail;
    }

    if (pg_cart_quote_state($quote, time()) !== 'open') {
        $fail['error'] = lang('This cart quote can no longer be used.');

        return $fail;
    }

    $cart = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);

    if (($cart > 0) && ((int) $quote['order_id'] === $cart)) {
        return array('ok' => true, 'error' => '', 'added' => 0, 'again' => true);
    }

    $lines = pg_cart_quote_lines((int) $quote['id']);
    $plan = pg_cart_quote_plan($lines, pg_cart_quote_products($lines));

    if (!$plan['add']) {
        $fail['error'] = lang('None of the products of this cart quote can be ordered now.');

        return $fail;
    }

    initialize_order();

    $added = 0;

    foreach ($plan['add'] as $line) {
        if (add_order_item($line['product_id'], $line['quantity'], 0, 'myself', '')) {
            $added++;
        }
    }

    update_order_item_prices();
    apply_offers_to_cart();

    db_q("UPDATE cart_quotes SET order_id = ?, updated_at = ? WHERE id = ? AND status = 'open'",
        array((string) (int) ($_SESSION['ecommerce']['order_id'] ?? 0), (string) time(), (string) (int) $quote['id'])) or output_error(lang('Query failed.'));

    log_activity(lang(array('string' => 'cart quote {var:1} was added to a cart ({var:2} products)', 'vars' => array('#' . (int) $quote['id'], $added))));

    return array('ok' => true, 'error' => '', 'added' => $added, 'again' => false);
}

/**
 * Marks the open quote that filled this order's cart as ordered. Called by
 * pg_order_complete() once the order is complete, from the visitor's
 * checkout and from a payment service's message alike.
 *
 * @param int $order_id
 * @return void
 */
function pg_cart_quote_order_completed($order_id)
{
    if (((int) $order_id <= 0) || !pg_cart_quotes_ready()) {
        return;
    }

    // The order is complete by now: a failed write leaves the quote open
    // rather than stopping the rest of the completion.
    db_q("UPDATE cart_quotes SET status = 'ordered', updated_at = ? WHERE order_id = ? AND status = 'open'",
        array((string) time(), (string) (int) $order_id));
}
