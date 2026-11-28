<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The cart in its market's currency. A visitor in a market fills an order
 * whose amounts are kept in the market's currency from the first line on:
 * a line's price is the base price converted at the market's rate and
 * rounded by its rule, an offer's amounts and thresholds and a shipping
 * rate are converted at that rate, and a tax is a rate of those amounts.
 * The order row names the market (orders.market_id), the currency
 * (orders.charged_currency, '' for the base one) and the rate
 * (orders.charged_rate, base units for one unit), so the order is written
 * with them when it is placed.
 *
 * The order row is the record: pg_market_cart_sync() points an incomplete
 * order at the visitor's market from the cart's own writers (a line added,
 * the prices refreshed), and every converting function reads the order's
 * market, never the visitor's, so a payment's return or callback works the
 * order out as the cart did. A site without a market never writes the row.
 *
 * Loaded by markets.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * How a cart in a market converts: null for a cart that is in no market or
 * whose currency has no usable rate (it is worked in base, as before).
 * Pure.
 *
 * @param int          $market_id     orders.market_id
 * @param string       $code          the order's currency, '' or the base code for base
 * @param float|string $exchange_rate units of the currency for one base unit (currencies.exchange_rate)
 * @param string       $rounding      the market's rule
 * @param string       $base          BASE_CURRENCY_CODE
 * @return array|null  market_id, code ('' for base), rate, rounding
 */
function pg_market_cart_state_for($market_id, $code, $exchange_rate, $rounding, $base)
{
    if ((int) $market_id < 1) {
        return null;
    }

    $code = pg_order_currency_code($code);

    if ($code === pg_order_currency_code($base)) {
        $code = '';
    }

    $rate = ($code === '') ? 1.0 : (is_numeric($exchange_rate) ? (float) $exchange_rate : 0.0);

    if ($rate <= 0) {
        return null;
    }

    return array(
        'market_id' => (int) $market_id,
        'code'      => $code,
        'rate'      => $rate,
        'rounding'  => pg_market_rounding_code($rounding),
    );
}

/**
 * A product's base price as a line of the cart charges it: converted and
 * rounded by the market's rule. As it is without a market. Pure.
 *
 * @param array|null $state  pg_market_cart_state_for()
 * @param int|string $base_minor
 * @return int
 */
function pg_market_cart_price_for($state, $base_minor)
{
    if ($state === null) {
        return (int) round(is_numeric($base_minor) ? (float) $base_minor : 0.0);
    }

    return pg_market_price_minor($base_minor, $state['rate'], $state['rounding']);
}

/**
 * Any other base amount in the cart's currency: an offer's amount or
 * threshold, a shipping rate, a member's agreed price. Converted at the
 * rate and rounded half away from zero to minor units, never by the
 * market's rule. As it is without a market. Pure.
 *
 * @param array|null $state
 * @param int|string $base_minor
 * @return int
 */
function pg_market_cart_amount_for($state, $base_minor)
{
    $base_minor = is_numeric($base_minor) ? (float) $base_minor : 0.0;

    if ($state === null) {
        return (int) round($base_minor);
    }

    return (int) round($base_minor * $state['rate']);
}

/**
 * An amount of the cart's currency back in base minor units, to compare it
 * with a base threshold (a shipping rate's subtotal band). Pure.
 *
 * @param array|null $state
 * @param int|string $minor
 * @return int
 */
function pg_market_cart_base_for($state, $minor)
{
    $minor = is_numeric($minor) ? (float) $minor : 0.0;

    if ($state === null) {
        return (int) round($minor);
    }

    return (int) round($minor / $state['rate']);
}

/**
 * The keys of an offer row read with its products that hold a product's
 * list price (converted as a line is) and those that hold an amount or a
 * threshold (converted at the rate).
 *
 * @return array  'price' => keys, 'amount' => keys
 */
function pg_market_cart_offer_keys()
{
    return array(
        'price' => array('product_price', 'add_product_price'),
        'amount' => array(
            'discount_order_amount',
            'discount_product_amount',
            'add_product_discount_amount',
            'offer_action_add_product_discount_amount',
            'required_subtotal',
            'offer_rule_required_subtotal',
            'upsell_trigger_subtotal',
        ),
    );
}

/**
 * An offer row (offers, rules, actions and the products they name) with its
 * money in the cart's currency. A key that is not in the row, or is null
 * (no product joined), stays as it is. Pure.
 *
 * @param array|null $state
 * @param array      $row
 * @return array
 */
function pg_market_cart_offer_row_for($state, $row)
{
    if (($state === null) || !is_array($row)) {
        return $row;
    }

    $keys = pg_market_cart_offer_keys();

    foreach ($keys['price'] as $key) {
        if (isset($row[$key]) && is_numeric($row[$key])) {
            $row[$key] = pg_market_cart_price_for($state, $row[$key]);
        }
    }

    foreach ($keys['amount'] as $key) {
        if (isset($row[$key]) && is_numeric($row[$key])) {
            $row[$key] = pg_market_cart_amount_for($state, $row[$key]);
        }
    }

    return $row;
}

/**
 * An order discount's ladder ("<cents>:<percent>") with its thresholds in
 * the cart's currency. Pure.
 *
 * @param array|null $state
 * @param array      $tiers  pg_offer_tiers()
 * @return array
 */
function pg_market_cart_tiers_for($state, $tiers)
{
    if ($state === null) {
        return $tiers;
    }

    foreach ($tiers as $key => $tier) {
        $tiers[$key]['min'] = pg_market_cart_amount_for($state, $tier['min']);
    }

    return $tiers;
}

/**
 * What an incomplete order's row should say for a visitor's market: the
 * market, the currency ('' for the base one) and the rate (base units for
 * one unit, eight decimals). A visitor in no market: 0, '' and 0. Pure.
 *
 * @param int          $market_id      VISITOR_MARKET_ID
 * @param string       $code           VISITOR_CURRENCY_CODE
 * @param float|string $exchange_rate  VISITOR_CURRENCY_EXCHANGE_RATE
 * @param string       $base           BASE_CURRENCY_CODE
 * @return array  market_id, currency, rate
 */
function pg_market_cart_target($market_id, $code, $exchange_rate, $base)
{
    $market_id = max(0, (int) $market_id);
    $exchange_rate = is_numeric($exchange_rate) ? (float) $exchange_rate : 0.0;

    if (($market_id === 0) || ($exchange_rate <= 0)) {
        return array('market_id' => 0, 'currency' => '', 'rate' => '0.00000000');
    }

    $pair = pg_order_charged_pair($code, $base, 1 / $exchange_rate, 0);

    return array('market_id' => $market_id, 'currency' => $pair['currency'], 'rate' => $pair['rate']);
}

/**
 * Whether the cart's order row can name a market: the markets' and the
 * charged currency's columns are there.
 *
 * @return bool
 */
function pg_market_cart_ready()
{
    return function_exists('pg_order_charged_ready') && pg_order_charged_ready()
        && pg_markets_ready() && pg_schema_has('orders', 'market_id');
}

/**
 * The state of an order's cart (pg_market_cart_state_for()), read from the
 * order row: its market and currency, the market's rule and the currency's
 * rate. Read once a request per order; pg_market_cart_forget() drops it.
 *
 * @param int|null $order_id  the session's cart when omitted
 * @return array|null
 */
function pg_market_cart($order_id = null)
{
    $order_id = ($order_id === null) ? (int) ($_SESSION['ecommerce']['order_id'] ?? 0) : (int) $order_id;
    $states = pg_market_cart_states();

    if ($order_id < 1) {
        return null;
    }

    if (!array_key_exists($order_id, $states)) {
        $state = null;

        if (pg_market_cart_ready()) {
            $row = db_item("SELECT orders.market_id, orders.charged_currency, markets.rounding
                FROM orders
                LEFT JOIN markets ON markets.id = orders.market_id
                WHERE orders.id = '" . $order_id . "'");

            if (!empty($row) && ((int) $row['market_id'] > 0)) {
                $code = pg_order_currency_code($row['charged_currency']);
                $rate = ($code === '') ? 1 : db_value("SELECT exchange_rate FROM currencies WHERE UPPER(code) = '" . e($code) . "' ORDER BY id LIMIT 1");
                $state = pg_market_cart_state_for($row['market_id'], $code, $rate, (string) $row['rounding'], defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '');
            }
        }

        $states = pg_market_cart_states($order_id, $state);
    }

    return $states[$order_id];
}

/**
 * The request's kept states, order id => state; a call with an order id
 * keeps (or with $forget drops) that order's.
 *
 * @param int|null   $order_id
 * @param array|null $state
 * @param bool       $forget
 * @return array
 */
function pg_market_cart_states($order_id = null, $state = null, $forget = false)
{
    static $states = array();

    if ($order_id !== null) {
        if ($forget) {
            unset($states[(int) $order_id]);
        } else {
            $states[(int) $order_id] = $state;
        }
    }

    return $states;
}

/**
 * Drops an order's kept state, after its row changed.
 *
 * @param int $order_id
 * @return void
 */
function pg_market_cart_forget($order_id)
{
    pg_market_cart_states((int) $order_id, null, true);
}

/**
 * Points the session's incomplete order at the visitor's market, once a
 * request, from the cart's writers (add_order_item(),
 * update_order_item_prices(), a typed amount) and the cart's pages. When
 * the market or its currency changes, the lines, the recipients' shipping
 * and the offers are worked out again in the new currency. Nothing
 * on a site whose order rows have no market, and nothing for an order that
 * never had a market on a site without one.
 *
 * @return bool  whether the order's market or currency changed
 */
function pg_market_cart_sync()
{
    static $done = array();

    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);

    if (($order_id < 1) || isset($done[$order_id]) || !pg_market_cart_ready()) {
        return false;
    }

    $done[$order_id] = true;

    $row = db_item("SELECT market_id, charged_currency, status FROM orders WHERE id = '" . $order_id . "'");

    if (empty($row) || ((string) $row['status'] !== 'incomplete')) {
        return false;
    }

    $target = pg_market_cart_target(
        defined('VISITOR_MARKET_ID') ? VISITOR_MARKET_ID : 0,
        defined('VISITOR_CURRENCY_CODE') ? VISITOR_CURRENCY_CODE : '',
        defined('VISITOR_CURRENCY_EXCHANGE_RATE') ? VISITOR_CURRENCY_EXCHANGE_RATE : 0,
        defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : ''
    );

    if (((int) $row['market_id'] === $target['market_id'])
        && (pg_order_currency_code($row['charged_currency']) === $target['currency'])) {
        return false;
    }

    db_q("UPDATE orders SET market_id = ?, charged_currency = ?, charged_rate = ? WHERE id = ? AND status = 'incomplete'",
        array((string) $target['market_id'], $target['currency'], $target['rate'], (string) $order_id));

    pg_market_cart_forget($order_id);

    // Every amount of the cart was worked out in the old currency: the
    // lines, the recipients' shipping, then the offers on both.
    update_order_item_prices();

    if (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING) {
        require_once(PG_FUNCTIONS_DIR . '/shipping.php');

        foreach ((array) db_values("SELECT id FROM ship_tos WHERE order_id = '" . $order_id . "' AND complete = '1'") as $ship_to_id) {
            update_shipping_cost_for_ship_to($ship_to_id);
        }
    }

    apply_offers_to_cart();

    return true;
}

/**
 * A product's base price as the session's cart charges it: the product's
 * fixed price in the cart's market when it has one (market_prices), else
 * converted and rounded by the market's rule.
 *
 * @param int|string $base_minor
 * @param int        $product_id 0 when the line names no product
 * @return int
 */
function pg_market_cart_price($base_minor, $product_id = 0)
{
    $state = pg_market_cart();
    $fixed = ($state === null) ? null : pg_market_fixed_price($state['market_id'], $product_id);

    return ($fixed !== null) ? $fixed : pg_market_cart_price_for($state, $base_minor);
}

/**
 * A base amount (offer, shipping, member price) in the session's cart
 * currency.
 *
 * @param int|string $base_minor
 * @param int|null   $order_id  the session's cart when omitted
 * @return int
 */
function pg_market_cart_amount($base_minor, $order_id = null)
{
    return pg_market_cart_amount_for(pg_market_cart($order_id), $base_minor);
}

/**
 * An amount of the cart's currency in base minor units.
 *
 * @param int|string $minor
 * @param int|null   $order_id
 * @return int
 */
function pg_market_cart_base($minor, $order_id = null)
{
    return pg_market_cart_base_for(pg_market_cart($order_id), $minor);
}

/**
 * An offer row with its money in the session's cart currency.
 *
 * @param array|null $row
 * @return array|null
 */
function pg_market_cart_offer_row($row)
{
    return pg_market_cart_offer_row_for(pg_market_cart(), $row);
}

/**
 * Minor units a visitor typed into the cart (a donation) mean: in a market
 * cart, the cart's own currency, kept as typed; otherwise the visitor's
 * display currency, divided back into base as before.
 *
 * @param float|int|string $typed_major
 * @return int
 */
function pg_market_cart_typed_minor($typed_major)
{
    $typed_major = is_numeric($typed_major) ? (float) $typed_major : 0.0;

    pg_market_cart_sync();

    if (pg_market_cart() !== null) {
        return (int) round($typed_major * 100);
    }

    $rate = (defined('VISITOR_CURRENCY_EXCHANGE_RATE') && ((float) VISITOR_CURRENCY_EXCHANGE_RATE > 0)) ? (float) VISITOR_CURRENCY_EXCHANGE_RATE : 1.0;

    return (int) round($typed_major / $rate * 100);
}

/**
 * A member's agreed prices (pg_erp_member_prices(), base) in the cart's
 * currency, at its rate. Pure.
 *
 * @param array|null $state
 * @param array      $member_prices  product id => row with 'price'
 * @return array
 */
function pg_market_cart_member_prices_for($state, $member_prices)
{
    if ($state === null) {
        return $member_prices;
    }

    foreach ((array) $member_prices as $product_id => $row) {
        if (is_array($row) && isset($row['price'])) {
            $member_prices[$product_id]['price'] = pg_market_cart_amount_for($state, $row['price']);
        }
    }

    return $member_prices;
}

/**
 * A member's agreed prices in the session's cart currency.
 *
 * @param array $member_prices
 * @return array
 */
function pg_market_cart_member_prices($member_prices)
{
    return pg_market_cart_member_prices_for(pg_market_cart(), $member_prices);
}

/**
 * Writes the rate a market's order is placed at: base units for one unit
 * of its currency, from the rate its lines were priced at in this request.
 * Run by submit_order.php before the payment, never later, so a payment's
 * return keeps the rate the buyer was charged at. Nothing for an order in
 * no market or in the base currency.
 *
 * @param int $order_id
 * @return void
 */
function pg_market_cart_seal($order_id)
{
    $state = pg_market_cart((int) $order_id);

    if (($state === null) || ($state['code'] === '')) {
        return;
    }

    $pair = pg_order_charged_pair($state['code'], defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', 1 / $state['rate'], 0);

    db_q("UPDATE orders SET charged_rate = ? WHERE id = ? AND status = 'incomplete'", array($pair['rate'], (string) (int) $order_id));
}

/**
 * Starts drawing the session's cart: the order follows the visitor's
 * market first, then (pg_order_display_begin()) the site's money functions
 * print its amounts as they are, in its own currency, while it is in a
 * market. A cart in no market prints as before.
 *
 * @return array|null  the display before, for pg_order_display_end()
 */
function pg_market_cart_display_begin()
{
    pg_market_cart_sync();

    return pg_order_display_begin((int) ($_SESSION['ecommerce']['order_id'] ?? 0));
}

/**
 * Draws a part of the cart (a cart widget) inside
 * pg_market_cart_display_begin() and pg_order_display_end().
 *
 * @param callable $draw  returns the HTML
 * @return string
 */
function pg_market_cart_drawn($draw)
{
    $display = pg_market_cart_display_begin();
    $output = (string) call_user_func($draw);
    pg_order_display_end($display);

    return $output;
}
