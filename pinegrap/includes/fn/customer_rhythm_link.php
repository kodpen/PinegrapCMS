<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customer rhythm: the reorder link a customer.reorder_due event carries
 * (reorder.php). The link names the due products and their quantities,
 * its end (30 days after the event) and a signature under the site's key;
 * nothing is stored for it. Opened, it shows the products and quantities;
 * sent, it fills the visitor's cart with them at the catalogue's price and
 * stock of that moment. It names no order, person or address and signs no
 * one in. An ended link points to the order history behind the sign-in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Days a reorder link works after the event that carries it.
define('PG_CUSTOMER_RHYTHM_LINK_DAYS', 30);

// Products one link names at most.
define('PG_CUSTOMER_RHYTHM_LINK_MAX', 20);

// Hex characters of the signature kept in the link (128 bits).
define('PG_CUSTOMER_RHYTHM_LINK_SIGNATURE', 32);

// Links a visitor's session remembers the cart of.
define('PG_CUSTOMER_RHYTHM_LINK_REMEMBERED', 20);

/**
 * The products of a link as its p value: "id-quantity" pairs joined by
 * dots, in the order given, one per product (a repeated product adds its
 * quantity), at most PG_CUSTOMER_RHYTHM_LINK_MAX. Pure.
 *
 * @param array $products  each with product_id and quantity
 * @return string  '' when none is left
 */
function pg_customer_rhythm_link_items($products)
{
    $items = array();

    foreach ((array) $products as $product) {
        $id = (int) ($product['product_id'] ?? 0);
        $quantity = (int) ($product['quantity'] ?? 0);

        if (($id <= 0) || ($id > 4294967295) || ($quantity <= 0)) {
            continue;
        }

        if (isset($items[$id])) {
            $items[$id] = min(99999, $items[$id] + $quantity);
        } elseif (count($items) < PG_CUSTOMER_RHYTHM_LINK_MAX) {
            $items[$id] = min(99999, $quantity);
        }
    }

    $pairs = array();

    foreach ($items as $id => $quantity) {
        $pairs[] = $id . '-' . $quantity;
    }

    return implode('.', $pairs);
}

/**
 * The link's signature over its products and its end, under its own prefix
 * so it never matches another HMAC made with the same key. Pure.
 *
 * @param string $items       pg_customer_rhythm_link_items()
 * @param int    $expires_at
 * @param string $key
 * @return string  '' when a part is missing
 */
function pg_customer_rhythm_link_signature($items, $expires_at, $key)
{
    if (((string) $items === '') || ((int) $expires_at <= 0) || ((string) $key === '')) {
        return '';
    }

    return substr(hash_hmac('sha256', 'customer-rhythm-reorder:' . (string) $items . ':' . (int) $expires_at, (string) $key), 0, PG_CUSTOMER_RHYTHM_LINK_SIGNATURE);
}

/**
 * The link's query string for these products, working for 30 days from
 * $now. Pure.
 *
 * @param array  $products  each with product_id and quantity
 * @param int    $now
 * @param string $key
 * @return string  p=…&e=…&s=…, '' without products or key
 */
function pg_customer_rhythm_link_query($products, $now, $key)
{
    $items = pg_customer_rhythm_link_items($products);
    $expires_at = (int) $now + (PG_CUSTOMER_RHYTHM_LINK_DAYS * 86400);
    $signature = pg_customer_rhythm_link_signature($items, $expires_at, $key);

    if ($signature === '') {
        return '';
    }

    return 'p=' . $items . '&e=' . $expires_at . '&s=' . $signature;
}

/**
 * The link a reorder event carries; links that leave the request take the
 * site's own host (HOSTNAME_SETTING).
 *
 * @param array $products
 * @param int   $now
 * @return string  '' without products or key
 */
function pg_customer_rhythm_link_url($products, $now)
{
    $query = pg_customer_rhythm_link_query($products, $now, pg_fs_key());

    if ($query === '') {
        return '';
    }

    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/reorder.php?' . $query;
}

/**
 * The link's values from a query string or a form. Pure.
 *
 * @param mixed $source
 * @return array|null  p (the items text), e (end), s (signature), lines
 *                     (product_id, quantity)
 */
function pg_customer_rhythm_link_parse($source)
{
    if (!is_array($source)) {
        return null;
    }

    $p = $source['p'] ?? null;
    $e = $source['e'] ?? null;
    $s = $source['s'] ?? null;
    $pair = '[1-9][0-9]{0,9}-[1-9][0-9]{0,4}';

    if (!is_string($p) || (preg_match('/^' . $pair . '(\.' . $pair . '){0,' . (PG_CUSTOMER_RHYTHM_LINK_MAX - 1) . '}$/', $p) !== 1)
        || !is_string($e) || (preg_match('/^[1-9][0-9]{0,10}$/', $e) !== 1)
        || !is_string($s) || (preg_match('/^[0-9a-f]{' . PG_CUSTOMER_RHYTHM_LINK_SIGNATURE . '}$/', $s) !== 1)) {
        return null;
    }

    $lines = array();

    foreach (explode('.', $p) as $item) {
        list($id, $quantity) = explode('-', $item);
        $lines[] = array('product_id' => (int) $id, 'quantity' => (int) $quantity);
    }

    return array('p' => $p, 'e' => (int) $e, 's' => $s, 'lines' => $lines);
}

/**
 * Where a link stands: open (signed by this site, within its 30 days),
 * ended (signed, past its end) or invalid (anything else). Pure.
 *
 * @param array|null $parsed  pg_customer_rhythm_link_parse()
 * @param string     $key
 * @param int        $now
 * @return string  open | ended | invalid
 */
function pg_customer_rhythm_link_state($parsed, $key, $now)
{
    if (!is_array($parsed)) {
        return 'invalid';
    }

    $expected = pg_customer_rhythm_link_signature($parsed['p'], $parsed['e'], $key);

    if (($expected === '') || !hash_equals($expected, (string) $parsed['s'])) {
        return 'invalid';
    }

    if ((int) $parsed['e'] > (int) $now + (PG_CUSTOMER_RHYTHM_LINK_DAYS * 86400)) {
        return 'invalid';
    }

    return ((int) $parsed['e'] > (int) $now) ? 'open' : 'ended';
}

/**
 * What an open link would put into the cart now: the cart quote's plan
 * against the catalogue (pg_cart_quote_plan(): gone, switched off, donation
 * and out of stock are left out; quantities kept within the product's
 * minimum, maximum and stock).
 *
 * @param array $parsed  pg_customer_rhythm_link_parse()
 * @return array  add (product_id, quantity), skipped, products (id => row)
 */
function pg_customer_rhythm_link_plan($parsed)
{
    $products = pg_cart_quote_products($parsed['lines']);

    return pg_cart_quote_plan($parsed['lines'], $products) + array('products' => $products);
}

/**
 * The cart a visitor already filled from this link, from their session.
 *
 * @param string $signature
 * @return int  the cart's order id, 0 for none
 */
function pg_customer_rhythm_link_cart($signature)
{
    return (int) ($_SESSION['ecommerce']['rhythm_links'][(string) $signature] ?? 0);
}

/**
 * Puts an open link's products into the visitor's cart at today's prices.
 * A second send into the same cart adds nothing.
 *
 * @param array $parsed  pg_customer_rhythm_link_parse(), state open
 * @return array  ok, error, added, again
 */
function pg_customer_rhythm_link_apply($parsed)
{
    $cart = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);

    if (($cart > 0) && (pg_customer_rhythm_link_cart($parsed['s']) === $cart)) {
        return array('ok' => true, 'error' => '', 'added' => 0, 'again' => true);
    }

    $plan = pg_customer_rhythm_link_plan($parsed);

    if (!$plan['add']) {
        return array('ok' => false, 'error' => lang('None of these products can be ordered now.'), 'added' => 0, 'again' => false);
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

    $remembered = (isset($_SESSION['ecommerce']['rhythm_links']) && is_array($_SESSION['ecommerce']['rhythm_links'])) ? $_SESSION['ecommerce']['rhythm_links'] : array();
    unset($remembered[$parsed['s']]);
    $remembered[$parsed['s']] = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
    $_SESSION['ecommerce']['rhythm_links'] = array_slice($remembered, -PG_CUSTOMER_RHYTHM_LINK_REMEMBERED, null, true);

    return array('ok' => true, 'error' => '', 'added' => $added, 'again' => false);
}
