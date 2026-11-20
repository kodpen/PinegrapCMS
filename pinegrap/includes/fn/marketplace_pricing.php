<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The price a marketplace account sells a product at: the site's price with
 * VAT, moved by the account's rules (2026.11.0, 11.0.256).
 *
 * marketplace_price_rules keeps one row per account and scope: the account's
 * own row, a category's (product_groups) or a brand's (products.brand). A row
 * says a percent (signed hundredths of a percent), a fixed amount (signed
 * minor units) and a rounding ending. The most specific row a product falls
 * under wins: the deepest category it sits in, else its brand, else the
 * account's row. An account with no row sends the site's price as before.
 *
 * The profit floor is the lowest price with VAT at which, after VAT, the
 * marketplace's commission (the listing's rate, else the rate typed for the
 * account in Accounting), a payment fee and shipping, the product's cost
 * leaves the account's minimum margin of the net. A product with no cost has
 * no worked-out floor; a floor typed on the listing (marketplace_price_state)
 * only raises it. A rule's price is never under the floor: it moves to the
 * next price with its ending over it.
 *
 * Everything above the "readers" line is pure and reads nothing; the readers
 * below it load an account's rules once a request and the figures of one
 * product, and mp_product_row() (connectors/base.php) sends what
 * mp_pricing_product_price() answers. The site's own price is never written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** marketplace_price_rules.scope of the account's own row. */
define('MP_PRICING_SCOPE_ACCOUNT', 0);

/** marketplace_price_rules.scope of a category's row (product_groups.id). */
define('MP_PRICING_SCOPE_CATEGORY', 1);

/** marketplace_price_rules.scope of a brand's row (products.brand). */
define('MP_PRICING_SCOPE_BRAND', 2);

/** The minimum margin of an account that has not set one: 10.00%. */
define('MP_PRICING_DEFAULT_MARGIN', 1000);

/** marketplace_price_rules.robot: the robot is off. */
define('MP_PRICING_ROBOT_OFF', 0);

/** marketplace_price_rules.robot: the robot logs what it would send and sends nothing. */
define('MP_PRICING_ROBOT_TRY', 1);

/** marketplace_price_rules.robot: the robot sends its prices. */
define('MP_PRICING_ROBOT_ON', 2);

/** marketplace_price_rules.step_unit: the step is an amount in minor units. */
define('MP_PRICING_STEP_AMOUNT', 0);

/** marketplace_price_rules.step_unit: the step is hundredths of a percent. */
define('MP_PRICING_STEP_PERCENT', 1);

/** product_price_history.source of a channel price from the rules. */
define('MP_PRICING_HISTORY_SOURCE', 'rule');

/**
 * The rounding endings a rule can name: none, a whole unit, ends in ,90 or
 * ends in ,99.
 *
 * @return string[]
 */
function mp_pricing_roundings()
{
    return array('none', 'whole', '90', '99');
}

/**
 * A stored or posted rounding as one of mp_pricing_roundings(); anything
 * else is 'none'.
 *
 * @param mixed $rounding
 * @return string
 */
function mp_pricing_rounding($rounding)
{
    $rounding = is_scalar($rounding) ? trim((string) $rounding) : '';

    return in_array($rounding, mp_pricing_roundings(), true) ? $rounding : 'none';
}

/**
 * The minor units a rounding ends in, or null for none.
 *
 * @param string $rounding
 * @return int|null
 */
function mp_pricing_ending($rounding)
{
    switch (mp_pricing_rounding($rounding)) {
        case 'whole':
            return 0;
        case '90':
            return 90;
        case '99':
            return 99;
    }

    return null;
}

/**
 * A price at the nearest amount with the rounding's ending, a tie upwards,
 * never under zero.
 *
 * @param int    $price     minor units
 * @param string $rounding
 * @return int
 */
function mp_pricing_round($price, $rounding)
{
    $price = (int) $price;
    $ending = mp_pricing_ending($rounding);

    if ($ending === null) {
        return max(0, $price);
    }

    $below = (int) floor(($price - $ending) / 100) * 100 + $ending;
    $above = $below + 100;

    if ($below < 0) {
        return $above;
    }

    return (($price - $below) < ($above - $price)) ? $below : $above;
}

/**
 * The first amount at or over a price with the rounding's ending.
 *
 * @param int    $price     minor units
 * @param string $rounding
 * @return int
 */
function mp_pricing_round_up($price, $rounding)
{
    $price = max(0, (int) $price);
    $ending = mp_pricing_ending($rounding);

    if ($ending === null) {
        return $price;
    }

    $up = (int) ceil(($price - $ending) / 100) * 100 + $ending;

    return ($up < 0) ? $ending : $up;
}

/**
 * The robot's step in minor units from the price it moves from (the buybox
 * price for Win, the listing's own price for Grow while winning): an amount
 * as stored, a percent of that price rounded half away from zero, never
 * under one minor unit.
 *
 * @param int $from  minor units
 * @param int $step  marketplace_price_rules.step
 * @param int $unit  marketplace_price_rules.step_unit
 * @return int
 */
function mp_pricing_step($from, $step, $unit)
{
    if ((int) $unit === MP_PRICING_STEP_PERCENT) {
        return max(1, (int) pg_percent_of(max(0, (int) $from), (int) $step / 100));
    }

    return max(1, (int) $step);
}

/**
 * Whether marketplace_price_rules has its step_unit column (2026.11.0,
 * 11.0.259).
 *
 * @return bool
 */
function mp_pricing_step_unit_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('marketplace_price_rules', 'step_unit');
    }

    return $ready;
}

/**
 * The rule a product takes from an account's rows.
 *
 * The deepest category the product sits in comes first (a tie at the same
 * depth: the lower group id), then its brand, compared without case or
 * outer spaces, then the account's own row.
 *
 * @param array  $rules   marketplace_price_rules rows of one account: scope, scope_id, scope_key, ...
 * @param array  $chains  per group the product sits in, the group ids from it up to the top
 * @param string $brand   products.brand
 * @return array|null  the row, or null when none applies
 */
function mp_pricing_pick_rule($rules, $chains, $brand)
{
    $categories = array();
    $brands = array();
    $account = null;

    foreach ((array) $rules as $rule) {
        $scope = (int) ($rule['scope'] ?? -1);

        if ($scope === MP_PRICING_SCOPE_CATEGORY) {
            $categories[(int) ($rule['scope_id'] ?? 0)] = $rule;
        } elseif ($scope === MP_PRICING_SCOPE_BRAND) {
            $brands[mp_pricing_brand_key($rule['scope_key'] ?? '')] = $rule;
        } elseif ($scope === MP_PRICING_SCOPE_ACCOUNT) {
            $account = $rule;
        }
    }

    if ($categories) {
        $best = null;

        foreach ((array) $chains as $chain) {
            foreach (array_values((array) $chain) as $depth => $group_id) {
                $group_id = (int) $group_id;

                if (!isset($categories[$group_id])) {
                    continue;
                }

                // Depth 0 is the group the product sits in; the nearer the
                // product, the more specific the rule.
                if (($best === null) || ($depth < $best[0]) || (($depth === $best[0]) && ($group_id < $best[1]))) {
                    $best = array($depth, $group_id);
                }

                break;
            }
        }

        if ($best !== null) {
            return $categories[$best[1]];
        }
    }

    $key = mp_pricing_brand_key($brand);

    if (($key !== '') && isset($brands[$key])) {
        return $brands[$key];
    }

    return $account;
}

/**
 * A brand as rules compare it: trimmed, lower case.
 *
 * @param mixed $brand
 * @return string
 */
function mp_pricing_brand_key($brand)
{
    return is_scalar($brand) ? mb_strtolower(trim((string) $brand), 'UTF-8') : '';
}

/**
 * A rule's price: the site price with VAT plus the rule's percent of it and
 * its fixed amount, rounded to its ending, never under zero.
 *
 * @param int   $site_price  minor units, VAT included (mp_product_price())
 * @param array $rule        percent (hundredths of a percent), fixed (minor units), rounding
 * @return int
 */
function mp_pricing_rule_price($site_price, $rule)
{
    $site_price = (int) $site_price;
    $percent = (int) ($rule['percent'] ?? 0);

    $price = $site_price + (int) pg_percent_of($site_price, $percent / 100) + (int) ($rule['fixed'] ?? 0);

    return mp_pricing_round(max(0, $price), (string) ($rule['rounding'] ?? 'none'));
}

/**
 * The price an account sends: the site price when no rule applies, else the
 * rule's price, lifted to the first price with the rule's ending at or over
 * the floor when it falls under it.
 *
 * @param int        $site_price  minor units, VAT included
 * @param array|null $rule        mp_pricing_pick_rule()
 * @param int|null   $floor       mp_pricing_floor_final()
 * @return int
 */
function mp_pricing_channel_price($site_price, $rule, $floor)
{
    if (!is_array($rule)) {
        return (int) $site_price;
    }

    $price = mp_pricing_rule_price($site_price, $rule);

    if (($floor !== null) && ($price < (int) $floor)) {
        $price = mp_pricing_round_up((int) $floor, (string) ($rule['rounding'] ?? 'none'));
    }

    return $price;
}

/**
 * The commission a listing's floor counts: the listing's own rate when it has
 * one above zero (no fixed part, as the marketplace reports none), else the
 * account's rate typed in Accounting (percent and fixed), else none.
 *
 * @param float|null $listing_percent  marketplace_product_map.commission
 * @param array|null $account_rate     erp_order_profit_rates()['marketplace'][account id]
 * @return array ['commission_percent' => float, 'commission_fixed' => int]
 */
function mp_pricing_floor_rates($listing_percent, $account_rate)
{
    if (($listing_percent !== null) && ((float) $listing_percent > 0)) {
        return array('commission_percent' => (float) $listing_percent, 'commission_fixed' => 0);
    }

    if (is_array($account_rate)) {
        return array(
            'commission_percent' => (float) ($account_rate['percent'] ?? 0),
            'commission_fixed' => (int) ($account_rate['fixed'] ?? 0),
        );
    }

    return array('commission_percent' => 0.0, 'commission_fixed' => 0);
}

/**
 * The lowest price with VAT that keeps the margin, or null when there is no
 * cost or no price can keep it.
 *
 * At a price P the net is P less its VAT, rounded; the commission and the
 * payment fee are their percent of P (what the buyer pays) plus their fixed
 * parts; the profit is the net less those, the shipping and the cost, and
 * it has to be at least the margin's share of the net. The estimate from the
 * exact fractions is walked in minor units to the lowest P that holds.
 *
 * @param array $input
 *   cost                int, minor units (erp_product_costs.avg_cost); 0 or null is no cost
 *   vat_rate            float, percent inside the price
 *   commission_percent  float, percent of the price
 *   commission_fixed    int, minor units
 *   fee_percent         float, percent of the price
 *   fee_fixed           int, minor units
 *   shipping            int, minor units
 *   margin              int, hundredths of a percent of the net
 * @return int|null
 */
function mp_pricing_floor($input)
{
    $cost = isset($input['cost']) ? (int) $input['cost'] : 0;

    if ($cost <= 0) {
        return null;
    }

    $parts = array(
        'vat' => max(0.0, (float) ($input['vat_rate'] ?? 0)),
        'commission' => (float) ($input['commission_percent'] ?? 0),
        'fee' => (float) ($input['fee_percent'] ?? 0),
        'margin' => max(0, (int) ($input['margin'] ?? 0)),
        'fixed' => (int) ($input['commission_fixed'] ?? 0) + (int) ($input['fee_fixed'] ?? 0)
            + (int) ($input['shipping'] ?? 0) + $cost,
    );

    $share = (1 - $parts['margin'] / 10000) / (1 + $parts['vat'] / 100)
        - ($parts['commission'] + $parts['fee']) / 100;

    if (($share <= 0) || ($parts['fixed'] <= 0)) {
        return null;
    }

    $price = (int) ceil($parts['fixed'] / $share);

    // Each part is rounded to a minor unit, so the estimate is off by a few
    // at most; the walks are bounded all the same.
    for ($i = 0; ($i < 1000) && !mp_pricing_floor_holds($price, $parts); $i++) {
        $price++;
    }

    if (!mp_pricing_floor_holds($price, $parts)) {
        return null;
    }

    for ($i = 0; ($i < 1000) && ($price > 0) && mp_pricing_floor_holds($price - 1, $parts); $i++) {
        $price--;
    }

    return $price;
}

/**
 * Whether a price keeps the margin, in whole minor units.
 *
 * @param int   $price
 * @param array $parts  vat, commission, fee (percents), margin (hundredths), fixed (minor units)
 * @return bool
 */
function mp_pricing_floor_holds($price, $parts)
{
    $net = mp_pricing_net($price, $parts['vat']);
    $profit = $net - (int) pg_percent_of($price, $parts['commission']) - (int) pg_percent_of($price, $parts['fee'])
        - $parts['fixed'];

    return ($profit * 10000) >= ($net * $parts['margin']);
}

/**
 * A price less the VAT inside it, rounded half away from zero.
 *
 * @param int   $price  minor units, VAT included
 * @param float $rate   percent
 * @return int
 */
function mp_pricing_net($price, $rate)
{
    $rate_units = (int) pg_decimal_units($rate, 3);

    if ($rate_units <= 0) {
        return (int) $price;
    }

    return (int) pg_multiply_units((int) $price, 100000, 100000 + $rate_units);
}

/**
 * The floor the robot and the rules keep: the higher of the worked-out floor
 * and the one typed on the listing, or null when there is neither.
 *
 * @param int|null $computed  mp_pricing_floor()
 * @param int|null $typed     marketplace_price_state.own_floor, 0 for none
 * @return int|null
 */
function mp_pricing_floor_final($computed, $typed)
{
    $typed = (int) $typed;
    $computed = ($computed === null) ? 0 : (int) $computed;
    $floor = max($computed, $typed);

    return ($floor > 0) ? $floor : null;
}

/* ------------------------------------------------------------------ readers */

/**
 * Whether the repricer's tables are there (2026.11.0, 11.0.256).
 *
 * @return bool
 */
function mp_pricing_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('marketplace_price_rules', 'scope_key')
            && pg_schema_has('marketplace_price_state', 'own_floor');
    }

    return $ready;
}

/**
 * An account's rule rows, read once a request.
 *
 * @param int $account_id
 * @return array  the rows; empty when the account has none or the table is not there
 */
function mp_pricing_account_rules($account_id)
{
    static $cache = array();

    $account_id = (int) $account_id;

    if (!isset($cache[$account_id])) {
        $cache[$account_id] = ($account_id > 0) && mp_pricing_ready()
            ? (array) db_items("SELECT scope, scope_id, scope_key, percent, fixed, rounding, robot, step,
                " . (mp_pricing_step_unit_ready() ? 'step_unit' : '0 AS step_unit') . ", min_margin, grow_cap
                FROM marketplace_price_rules WHERE account_id = '" . $account_id . "'")
            : array();
    }

    return $cache[$account_id];
}

/**
 * The account's own row among its rules, or null.
 *
 * @param array $rules  mp_pricing_account_rules()
 * @return array|null
 */
function mp_pricing_account_row($rules)
{
    foreach ((array) $rules as $rule) {
        if ((int) ($rule['scope'] ?? -1) === MP_PRICING_SCOPE_ACCOUNT) {
            return $rule;
        }
    }

    return null;
}

/**
 * Every product group's parent, read once a request.
 *
 * @return array  group id => parent id
 */
function mp_pricing_group_parents()
{
    static $parents = null;

    if ($parents === null) {
        $parents = array();

        foreach ((array) db_items("SELECT id, parent_id FROM product_groups") as $row) {
            $parents[(int) $row['id']] = (int) $row['parent_id'];
        }
    }

    return $parents;
}

/**
 * Per group, the ids from it up to the top.
 *
 * @param int[] $group_ids
 * @param array $parents  group id => parent id
 * @return array
 */
function mp_pricing_group_chains($group_ids, $parents)
{
    $chains = array();

    foreach ((array) $group_ids as $group_id) {
        $chain = array();
        $seen = array();
        $group_id = (int) $group_id;

        // A loop in the parents (a group set under its own child) ends the
        // walk instead of running for ever.
        while (($group_id > 0) && !isset($seen[$group_id])) {
            $chain[] = $group_id;
            $seen[$group_id] = true;
            $group_id = isset($parents[$group_id]) ? (int) $parents[$group_id] : 0;
        }

        if ($chain) {
            $chains[] = $chain;
        }
    }

    return $chains;
}

/**
 * The typed cost rates of Accounting, read once a request through the
 * order-profit reader, or null when Accounting is off or has no rates table.
 *
 * @return array|null  erp_order_profit_rates()
 */
function mp_pricing_cost_rates()
{
    static $rates = false;

    if ($rates === false) {
        $rates = null;

        if (defined('ERP_ENABLED') && ERP_ENABLED) {
            // The gate every file under includes/erp/ checks; the ERP
            // bootstrap defines it the same way.
            if (!defined('PG_ERP_ENTRY')) {
                define('PG_ERP_ENTRY', true);
            }

            require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit.php');

            if (erp_order_profit_ready()) {
                $rates = erp_order_profit_rates();
            }
        }
    }

    return $rates;
}

/**
 * The VAT a floor takes out of the price on a shop with tax off: the stored
 * price is then final with the VAT inside, at the rate the drivers declare,
 * the product's own, else the zone's, else none.
 *
 * @param array $product  products row: tax_rate
 * @return float
 */
function mp_pricing_tax_off_rate($product)
{
    $zone = function_exists('get_default_tax_rate') ? get_default_tax_rate() : false;
    $own = $product['tax_rate'] ?? null;

    if (($own === null) || ($own === '')) {
        return ($zone === false) ? 0.0 : (float) $zone;
    }

    return (float) $own;
}

/**
 * The price an account sends for a product, with what it was worked out
 * from.
 *
 * A price typed on the listing comes first (the account's typed prices are
 * read once a request). Otherwise reads only when the account has a rule:
 * its groups when a category rule is among them, and its cost, listing
 * commission and typed floor in one statement.
 *
 * @param array $account     marketplace_accounts row
 * @param array $product     products row: id, brand
 * @param int   $site_price  mp_product_price()
 * @param float $vat_rate    the rate inside the site price
 * @return array ['price' => int, 'rule' => bool, 'own' => bool, 'floor' => int|null]
 */
function mp_pricing_product_price($account, $product, $site_price, $vat_rate)
{
    $answer = array('price' => (int) $site_price, 'rule' => false, 'own' => false, 'floor' => null);
    $account_id = (int) ($account['id'] ?? 0);
    $product_id = (int) ($product['id'] ?? 0);

    // A price typed on the listing wins over the rules (R2) and is sent as
    // typed: the person chose it, floor or not.
    $own_prices = mp_pricing_account_own_prices($account_id);

    if (($product_id > 0) && isset($own_prices[$product_id])) {
        return array('price' => $own_prices[$product_id], 'rule' => false, 'own' => true, 'floor' => null);
    }

    $rules = mp_pricing_account_rules($account_id);

    if (!$rules || ($product_id <= 0)) {
        return $answer;
    }

    $chains = array();

    foreach ($rules as $rule) {
        if ((int) $rule['scope'] === MP_PRICING_SCOPE_CATEGORY) {
            $groups = array_map('intval', (array) db_values("SELECT product_group FROM products_groups_xref
                WHERE product = '" . $product_id . "'"));
            $chains = mp_pricing_group_chains($groups, mp_pricing_group_parents());
            break;
        }
    }

    $rule = mp_pricing_pick_rule($rules, $chains, (string) ($product['brand'] ?? ''));

    if ($rule === null) {
        return $answer;
    }

    $floor = mp_pricing_product_floor($account_id, $rules, mp_pricing_product_figures($account_id, $product_id), $vat_rate);

    return array(
        'price' => mp_pricing_channel_price($site_price, $rule, $floor),
        'rule' => true,
        'own' => false,
        'floor' => $floor,
    );
}

/**
 * A listing's floor: the one worked out from its cost (Accounting on and a
 * cost known), raised by the one typed on it; null when neither is there.
 *
 * @param int   $account_id
 * @param array $rules     mp_pricing_account_rules()
 * @param array $figures   mp_pricing_product_figures()
 * @param float $vat_rate  the rate inside the site price
 * @return int|null
 */
function mp_pricing_product_floor($account_id, $rules, $figures, $vat_rate)
{
    $rates = mp_pricing_cost_rates();
    $computed = null;

    if (($rates !== null) && ($figures['cost'] > 0)) {
        $own = mp_pricing_account_row($rules);
        $margin = is_array($own) ? (int) $own['min_margin'] : MP_PRICING_DEFAULT_MARGIN;

        // A marketplace collects the money itself, so no payment method's fee
        // applies (erp_order_profit_compute()'s rule); the account's fixed
        // part stands for its per-sale deductions, shipping among them.
        $computed = mp_pricing_floor(array_merge(array(
            'cost' => $figures['cost'],
            'vat_rate' => (float) $vat_rate,
            'fee_percent' => 0,
            'fee_fixed' => 0,
            'shipping' => 0,
            'margin' => $margin,
        ), mp_pricing_floor_rates($figures['commission'], $rates['marketplace'][(string) (int) $account_id] ?? null)));
    }

    return mp_pricing_floor_final($computed, $figures['own_floor']);
}

/**
 * The prices typed on an account's listings, read once a request.
 *
 * @param int $account_id
 * @return array  product id => own price, minor units
 */
function mp_pricing_account_own_prices($account_id)
{
    static $cache = array();

    $account_id = (int) $account_id;

    if (!isset($cache[$account_id])) {
        $cache[$account_id] = array();

        if (($account_id > 0) && mp_pricing_ready()) {
            foreach ((array) db_items("SELECT product_id, own_price FROM marketplace_price_state
                WHERE (account_id = '" . $account_id . "') AND (own_price > 0)") as $row) {
                $cache[$account_id][(int) $row['product_id']] = (int) $row['own_price'];
            }
        }
    }

    return $cache[$account_id];
}

/**
 * A listing's cost, commission, typed floor, typed price and strategy, in
 * one statement.
 *
 * @param int $account_id
 * @param int $product_id
 * @return array ['cost' => int, 'commission' => float|null, 'own_floor' => int, 'own_price' => int, 'strategy' => int]
 */
function mp_pricing_product_figures($account_id, $product_id)
{
    $select = array("0 AS cost", "NULL AS commission", "0 AS own_floor", "0 AS own_price", "0 AS strategy");
    $joins = array();

    if (pg_schema_has('erp_product_costs', 'avg_cost')) {
        $select[0] = "COALESCE(costs.avg_cost, 0) AS cost";
        $joins[] = "LEFT JOIN erp_product_costs AS costs ON costs.product_id = map.product_id";
    }

    if (pg_schema_has('marketplace_product_map', 'commission')) {
        $select[1] = "map.commission AS commission";
    }

    $select[2] = "COALESCE(state.own_floor, 0) AS own_floor";
    $select[3] = "COALESCE(state.own_price, 0) AS own_price";
    $select[4] = "COALESCE(state.strategy, 0) AS strategy";
    $joins[] = "LEFT JOIN marketplace_price_state AS state
            ON (state.account_id = map.account_id) AND (state.product_id = map.product_id)";

    $row = db_item("SELECT " . implode(', ', $select) . "
        FROM marketplace_product_map AS map
        " . implode("\n        ", $joins) . "
        WHERE (map.account_id = '" . (int) $account_id . "') AND (map.product_id = '" . (int) $product_id . "')");

    return array(
        'cost' => is_array($row) ? (int) $row['cost'] : 0,
        'commission' => (is_array($row) && ($row['commission'] !== null)) ? (float) $row['commission'] : null,
        'own_floor' => is_array($row) ? (int) $row['own_floor'] : 0,
        'own_price' => is_array($row) ? (int) $row['own_price'] : 0,
        'strategy' => is_array($row) ? (int) $row['strategy'] : 0,
    );
}

/**
 * Write a product_price_history row on the account's channel for each
 * channel price that differs from the channel's newest row: what the price
 * shield of the sales watch and the reference price read. Never stops the
 * send: a failure goes to the error log and the next send catches up.
 *
 * @param int    $account_id
 * @param array  $prices  product id => channel price, minor units
 * @param string $source  'rule', or 'panel' for prices typed on the listing
 * @return int  the rows written
 */
function mp_pricing_history_record($account_id, $prices, $source = MP_PRICING_HISTORY_SOURCE)
{
    $account_id = (int) $account_id;

    if (($account_id <= 0) || !$prices || !function_exists('pg_price_history_ready')
        || !class_exists('db', false) || empty(db::$con) || !pg_price_history_ready()) {
        return 0;
    }

    $channel = 'marketplace:' . $account_id;
    $currency = pg_price_history_currency();
    $sql = pg_price_history_newest_sql(array_keys($prices), $channel);

    if ($sql === '') {
        return 0;
    }

    $result = @mysqli_query(db::$con, $sql);

    if (!$result) {
        pg_price_history_log_failure('reading the marketplace channel');
        return 0;
    }

    $newest = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $newest[(int) $row['product_id']] = $row;
    }

    $changes = pg_price_history_changes($prices, $newest, $currency);
    $insert = pg_price_history_insert_sql($changes, $channel, $currency, pg_price_history_source($source), time());

    if ($insert === '') {
        return 0;
    }

    if (!@mysqli_query(db::$con, $insert)) {
        pg_price_history_log_failure('writing the marketplace channel');
        return 0;
    }

    return count($changes);
}
