<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customer rhythm rules: how long a unit of a product lasts (days per unit),
 * a customer's own interval between orders, the store's chance curve, the
 * stages a customer moves through between orders and the products due for
 * a reorder. Every function here is pure and works on plain arrays; the
 * nightly pass reads the orders and hands them over.
 *
 * Which orders count and who the customer is come from the Customers
 * Report's own rules (pg_customer_value_order_counts(),
 * pg_customer_value_key()); nothing here copies them. A marketplace order
 * counts for a product's rhythm only: its buyer is never a reorder target
 * and never has a rhythm of their own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Days back a repeat purchase is read for a product's rhythm (24 months).
define('PG_CUSTOMER_RHYTHM_WINDOW_DAYS', 730);

// A product's own rhythm needs this many pairs from this many customers.
define('PG_CUSTOMER_RHYTHM_MIN_PAIRS', 10);
define('PG_CUSTOMER_RHYTHM_MIN_CUSTOMERS', 5);

// Under this many customers with two orders the fixed curve is used.
define('PG_CUSTOMER_RHYTHM_CURVE_CUSTOMERS', 200);

// A band of the store curve needs this many customers who reached it.
define('PG_CUSTOMER_RHYTHM_BAND_MIN', 20);

// Highest band worked out (20 times the interval); above it the last one.
define('PG_CUSTOMER_RHYTHM_BAND_TOP', 40);

// Under this chance a customer past three intervals is Lost.
define('PG_CUSTOMER_RHYTHM_LOST_CHANCE', 0.10);

// Days before a due day the reorder window opens.
define('PG_CUSTOMER_RHYTHM_LEAD_DAYS', 3);

/**
 * The median of a list of numbers as a float; null for an empty list. Pure.
 *
 * @param array $values
 * @return float|null
 */
function pg_customer_rhythm_median($values)
{
    $values = array_values($values);
    $count = count($values);

    if ($count === 0) {
        return null;
    }

    sort($values);
    $middle = (int) floor($count / 2);

    if ($count % 2) {
        return (float) $values[$middle];
    }

    return ($values[$middle - 1] + $values[$middle]) / 2.0;
}

/**
 * Days between two times, as a float rounded to two places. Pure.
 *
 * @param int $from
 * @param int $to
 * @return float
 */
function pg_customer_rhythm_days_between($from, $to)
{
    return round(((int) $to - (int) $from) / 86400, 2);
}

/**
 * Each product's purchases per customer: one entry per purchase time, the
 * quantities of one order added up, in time order. Purchases less than a day
 * apart are one visit. Pure.
 *
 * @param array $lines  customer, product_id, quantity, time
 * @param array $skip   product ids left out
 * @return array        product_id => customer => list of array(time, quantity)
 */
function pg_customer_rhythm_purchases($lines, $skip = array())
{
    $skip = array_flip(array_map('intval', $skip));
    $by = array();

    foreach ($lines as $line) {
        $product = (int) $line['product_id'];
        $customer = (string) $line['customer'];
        $time = (int) $line['time'];

        if (($customer === '') || ($time <= 0) || isset($skip[$product])) {
            continue;
        }

        $by[$product][$customer][$time] = (isset($by[$product][$customer][$time]) ? $by[$product][$customer][$time] : 0) + (int) $line['quantity'];
    }

    $out = array();

    foreach ($by as $product => $customers) {
        foreach ($customers as $customer => $times) {
            ksort($times);
            $list = array();

            foreach ($times as $time => $quantity) {
                $last = count($list) - 1;

                if (($last >= 0) && (($time - $list[$last][0]) < 86400)) {
                    $list[$last][1] += $quantity;
                    continue;
                }

                $list[] = array($time, $quantity);
            }

            $out[$product][$customer] = $list;
        }
    }

    return $out;
}

/**
 * Each product's rhythm (H1, H2): over the last 24 months, every time a
 * customer bought a product again, the days between the two purchases
 * divided by the units of the first. The median of those, from at least 10
 * pairs of 5 customers; with fewer, the median of its product group's pairs
 * (the group with the most pairs that has enough); with none, no rhythm. A
 * subscription product runs on its own clock and is left out, also from its
 * groups. Pure.
 *
 * @param array $lines          customer, product_id, quantity, time; only
 *                              orders that count, marketplace ones included
 * @param array $groups         product_id => group id or list of group ids
 * @param array $subscriptions  product ids sold as a subscription
 * @param int   $now
 * @return array  product_id => days_per_unit, pairs, customers, from
 *                ('product' or 'group'), ordered by product id
 */
function pg_customer_rhythm_products($lines, $groups, $subscriptions, $now)
{
    $since = (int) $now - (PG_CUSTOMER_RHYTHM_WINDOW_DAYS * 86400);
    $skip = array_flip(array_map('intval', $subscriptions));
    $pairs = array();

    foreach (pg_customer_rhythm_purchases($lines, $subscriptions) as $product => $customers) {
        foreach ($customers as $customer => $list) {
            for ($i = 1, $n = count($list); $i < $n; $i++) {
                if (($list[$i - 1][0] < $since) || ($list[$i - 1][1] <= 0)) {
                    continue;
                }

                $pairs[$product][] = array(pg_customer_rhythm_days_between($list[$i - 1][0], $list[$i][0]) / $list[$i - 1][1], (string) $customer);
            }
        }
    }

    $products = array();
    $pooled = array();

    foreach ($groups as $product => $ids) {
        if (isset($skip[(int) $product])) {
            continue;
        }

        $products[(int) $product] = array_map('intval', is_array($ids) ? $ids : array($ids));

        foreach ($products[(int) $product] as $group) {
            if (!isset($pooled[$group])) {
                $pooled[$group] = array();
            }

            if (isset($pairs[(int) $product])) {
                foreach ($pairs[(int) $product] as $pair) {
                    $pooled[$group][] = $pair;
                }
            }
        }
    }

    foreach (array_keys($pairs) as $product) {
        if (!isset($products[$product])) {
            $products[$product] = array();
        }
    }

    $out = array();

    foreach ($products as $product => $ids) {
        $rhythm = pg_customer_rhythm_from_pairs(isset($pairs[$product]) ? $pairs[$product] : array(), 'product');

        if ($rhythm === null) {
            foreach ($ids as $group) {
                $candidate = pg_customer_rhythm_from_pairs($pooled[$group], 'group');

                if (($candidate !== null) && (($rhythm === null) || ($candidate['pairs'] > $rhythm['pairs']))) {
                    $rhythm = $candidate;
                }
            }
        }

        if ($rhythm !== null) {
            $out[$product] = $rhythm;
        }
    }

    ksort($out);

    return $out;
}

/**
 * A rhythm from a list of pairs, or null with fewer than 10 pairs of 5
 * customers. Pure.
 *
 * @param array  $pairs  list of array(days per unit, customer)
 * @param string $from   'product' or 'group'
 * @return array|null
 */
function pg_customer_rhythm_from_pairs($pairs, $from)
{
    $customers = array();
    $values = array();

    foreach ($pairs as $pair) {
        $values[] = $pair[0];
        $customers[$pair[1]] = true;
    }

    if ((count($values) < PG_CUSTOMER_RHYTHM_MIN_PAIRS) || (count($customers) < PG_CUSTOMER_RHYTHM_MIN_CUSTOMERS)) {
        return null;
    }

    return array(
        'days_per_unit' => round(pg_customer_rhythm_median($values), 2),
        'pairs' => count($values),
        'customers' => count($customers),
        'from' => $from,
    );
}

/**
 * One customer's rhythm (H3): orders, first, last, the gaps between them in
 * days, the own interval (median of the gaps), the days since the last
 * order, the average order and the best 365 days. Only orders that count
 * and are not marketplace orders are read; orders less than a day apart are
 * one visit, so they make no gap. With no gap the interval is the store's
 * median days to a second order ('own' false); null when the store has
 * none either. Pure.
 *
 * @param array      $orders        order_date, total, refunded_amount,
 *                                  status, cancelled_at, type
 * @param float|null $store_median  pg_customer_rhythm_store_median()
 * @param int        $now
 * @return array
 */
function pg_customer_rhythm_customer($orders, $store_median, $now)
{
    $times = array();
    $amounts = array();

    foreach ($orders as $order) {
        $time = isset($order['order_date']) ? (int) $order['order_date'] : 0;

        if (($time <= 0) || (isset($order['type']) && ((string) $order['type'] === 'marketplace')) || !pg_customer_value_order_counts($order)) {
            continue;
        }

        $times[] = $time;
        $amounts[] = array($time, pg_customer_value_order_amount($order));
    }

    sort($times);
    $gaps = array();

    for ($i = 1, $n = count($times); $i < $n; $i++) {
        if (($times[$i] - $times[$i - 1]) >= 86400) {
            $gaps[] = pg_customer_rhythm_days_between($times[$i - 1], $times[$i]);
        }
    }

    $own = ($gaps !== array());
    $interval = $own ? round(pg_customer_rhythm_median($gaps), 2) : (($store_median === null) ? null : (float) $store_median);
    $last = $times ? $times[count($times) - 1] : 0;
    $total = 0;

    foreach ($amounts as $amount) {
        $total += $amount[1];
    }

    return array(
        'orders' => count($times),
        'first' => $times ? $times[0] : 0,
        'last' => $last,
        'gaps' => $gaps,
        'first_gap' => $own ? $gaps[0] : null,
        'interval' => $interval,
        'own' => $own,
        'days' => $last ? (int) floor(((int) $now - $last) / 86400) : 0,
        'average' => $times ? (int) round($total / count($times)) : 0,
        'best_year' => pg_customer_rhythm_best_year($amounts),
    );
}

/**
 * The most a customer spent in any 365 days, in minor units. Pure.
 *
 * @param array $amounts  list of array(time, amount)
 * @return int
 */
function pg_customer_rhythm_best_year($amounts)
{
    usort($amounts, function ($a, $b) {
        return $a[0] - $b[0];
    });

    $best = 0;
    $sum = 0;
    $start = 0;

    foreach ($amounts as $i => $amount) {
        $sum += $amount[1];

        while (($amount[0] - $amounts[$start][0]) >= (365 * 86400)) {
            $sum -= $amounts[$start][1];
            $start++;
        }

        $best = max($best, $sum);
    }

    return (int) $best;
}

/**
 * The store's median days to a second order, for one-order customers (H3);
 * null when no customer has ordered twice. Pure.
 *
 * @param array $customers  pg_customer_rhythm_customer() results
 * @return float|null
 */
function pg_customer_rhythm_store_median($customers)
{
    $gaps = array();

    foreach ($customers as $customer) {
        if ($customer['first_gap'] !== null) {
            $gaps[] = $customer['first_gap'];
        }
    }

    $median = pg_customer_rhythm_median($gaps);

    return ($median === null) ? null : round($median, 2);
}

/**
 * The band of a silence: its multiple of the interval in steps of 0.5. Pure.
 *
 * @param float $multiple
 * @return int
 */
function pg_customer_rhythm_band($multiple)
{
    return min(PG_CUSTOMER_RHYTHM_BAND_TOP + 1, max(0, (int) floor(($multiple / 0.5) + 1e-9)));
}

/**
 * The fixed curve, used under 200 customers with two orders (H4): the
 * chance a customer still orders again by band, falling from 90% below one
 * interval to 8% from six intervals on. Pure.
 *
 * @param int $band
 * @return float
 */
function pg_customer_rhythm_fixed_chance($band)
{
    $curve = array(0.90, 0.90, 0.80, 0.70, 0.55, 0.45, 0.35, 0.28, 0.22, 0.17, 0.13, 0.11);

    return isset($curve[$band]) ? $curve[$band] : 0.08;
}

/**
 * The store's chance curve (H4): for each band, among the customers whose
 * silence once reached it, the share who ordered again. A band reached by
 * fewer than 20 customers ends the curve; it never rises from one band to
 * the next. Fixed when fewer than 200 customers have two orders. Pure.
 *
 * @param array $customers  pg_customer_rhythm_customer() results
 * @return array  fixed (bool), bands (band => chance)
 */
function pg_customer_rhythm_curve($customers)
{
    $repeat = 0;
    $reached = array_fill(0, PG_CUSTOMER_RHYTHM_BAND_TOP + 2, 0);
    $returned = $reached;

    foreach ($customers as $customer) {
        if ($customer['orders'] >= 2) {
            $repeat++;
        }

        if (($customer['interval'] === null) || ($customer['interval'] <= 0) || !$customer['orders']) {
            continue;
        }

        $back = -1;

        foreach ($customer['gaps'] as $gap) {
            $back = max($back, pg_customer_rhythm_band($gap / $customer['interval']));
        }

        $top = max($back, pg_customer_rhythm_band($customer['days'] / $customer['interval']));

        // Counted once per customer and band, added up from the top below.
        $reached[$top]++;

        if ($back >= 0) {
            $returned[$back]++;
        }
    }

    if ($repeat < PG_CUSTOMER_RHYTHM_CURVE_CUSTOMERS) {
        return array('fixed' => true, 'bands' => array());
    }

    $bands = array();
    $reach = 0;
    $back = 0;
    $counts = array();

    for ($band = PG_CUSTOMER_RHYTHM_BAND_TOP + 1; $band >= 0; $band--) {
        $reach += $reached[$band];
        $back += $returned[$band];
        $counts[$band] = array($reach, $back);
    }

    $floor = 1.0;

    for ($band = 0; $band <= PG_CUSTOMER_RHYTHM_BAND_TOP + 1; $band++) {
        if ($counts[$band][0] < PG_CUSTOMER_RHYTHM_BAND_MIN) {
            break;
        }

        $floor = min($floor, $counts[$band][1] / $counts[$band][0]);
        $bands[$band] = $floor;
    }

    return array('fixed' => false, 'bands' => $bands);
}

/**
 * The chance a customer silent for $days at their interval still orders
 * again (H4). Past the store curve's last band: the fixed curve, never above
 * that last band. 0 without an interval. Pure.
 *
 * @param array      $curve     pg_customer_rhythm_curve()
 * @param int        $days
 * @param float|null $interval
 * @return float
 */
function pg_customer_rhythm_chance($curve, $days, $interval)
{
    if (($interval === null) || ($interval <= 0)) {
        return 0.0;
    }

    $band = pg_customer_rhythm_band($days / $interval);

    if (!empty($curve['fixed']) || empty($curve['bands'])) {
        return pg_customer_rhythm_fixed_chance($band);
    }

    if (isset($curve['bands'][$band])) {
        return (float) $curve['bands'][$band];
    }

    return (float) min(end($curve['bands']), pg_customer_rhythm_fixed_chance($band));
}

/**
 * Expected spend in the next 12 months (H5): chance x 365 / interval x the
 * average order, at most twice the best 365 days; minor units. Pure.
 *
 * @param float      $chance
 * @param float|null $interval
 * @param int        $average
 * @param int        $best_year
 * @return int
 */
function pg_customer_rhythm_expected($chance, $interval, $average, $best_year)
{
    if (($interval === null) || ($interval <= 0)) {
        return 0;
    }

    return (int) round(min($chance * 365 / $interval * $average, 2 * $best_year));
}

/**
 * A stage's place in the order a customer moves through between orders.
 * Regular and Won back are both on time. Pure.
 *
 * @param string $stage
 * @return int  -1 for no stage
 */
function pg_customer_rhythm_stage_rank($stage)
{
    $ranks = array('regular' => 0, 'won_back' => 0, 'due' => 1, 'late' => 2, 'slipping' => 3, 'lost' => 4);

    return isset($ranks[$stage]) ? $ranks[$stage] : -1;
}

/**
 * The stage for $days since the last order at interval i (H6): Regular
 * (d < i), Due (i <= d < 2i), Late (2i <= d < 3i), Slipping away (d >= 3i,
 * chance 10% or more), Lost (d >= 3i, chance under 10%); Won back instead of
 * Regular after a return from Late or later. '' without an interval. Pure.
 *
 * @param int        $days
 * @param float|null $interval
 * @param float      $chance
 * @param bool       $won_back  the last order came after Late, Slipping away or Lost
 * @return string  regular, won_back, due, late, slipping, lost or ''
 */
function pg_customer_rhythm_stage($days, $interval, $chance, $won_back)
{
    if (($interval === null) || ($interval <= 0)) {
        return '';
    }

    if ($days < $interval) {
        return $won_back ? 'won_back' : 'regular';
    }

    if ($days < 2 * $interval) {
        return 'due';
    }

    if ($days < 3 * $interval) {
        return 'late';
    }

    return ($chance >= PG_CUSTOMER_RHYTHM_LOST_CHANCE) ? 'slipping' : 'lost';
}

/**
 * Between orders a stage only moves forward: the later of the stored and
 * the worked-out stage. Pure.
 *
 * @param string $stored
 * @param string $computed
 * @return string
 */
function pg_customer_rhythm_stage_forward($stored, $computed)
{
    return (pg_customer_rhythm_stage_rank($computed) > pg_customer_rhythm_stage_rank($stored)) ? $computed : (string) $stored;
}

/**
 * A customer's stage after a rebuild (H6, H7). With no order since the
 * stored one the stored interval is kept and the stage only moves forward;
 * a new order fixes the bounds again from the customer's interval, and the
 * stage the silence before it had reached decides Won back. Pure.
 *
 * @param array|null $stored    stage, interval, last; null for a new customer
 * @param array      $customer  pg_customer_rhythm_customer()
 * @param array      $curve     pg_customer_rhythm_curve()
 * @return array  stage, interval, last, from (the stage before), moved
 */
function pg_customer_rhythm_step($stored, $customer, $curve)
{
    $from = ($stored && isset($stored['stage'])) ? (string) $stored['stage'] : '';
    $days = (int) $customer['days'];

    if ($stored && ($from !== '') && ((int) $customer['last'] <= (int) $stored['last'])) {
        $interval = ($stored['interval'] === null) ? $customer['interval'] : (float) $stored['interval'];
        $computed = pg_customer_rhythm_stage($days, $interval, pg_customer_rhythm_chance($curve, $days, $interval), $from === 'won_back');
        $stage = pg_customer_rhythm_stage_forward($from, $computed);
    } else {
        $interval = $customer['interval'];
        $won_back = false;

        if ($customer['gaps']) {
            // The silence before the latest order, at the bounds it ran under.
            $gap = $customer['gaps'][count($customer['gaps']) - 1];
            $before_interval = ($stored && !empty($stored['interval'])) ? (float) $stored['interval'] : $interval;
            $before = pg_customer_rhythm_stage($gap, $before_interval, pg_customer_rhythm_chance($curve, $gap, $before_interval), false);
            $won_back = pg_customer_rhythm_stage_rank(pg_customer_rhythm_stage_forward($from, $before)) >= pg_customer_rhythm_stage_rank('late');
        }

        $stage = pg_customer_rhythm_stage($days, $interval, pg_customer_rhythm_chance($curve, $days, $interval), $won_back);
    }

    return array(
        'stage' => $stage,
        'interval' => $interval,
        'last' => (int) $customer['last'],
        'from' => $from,
        'moved' => ($stage !== $from),
    );
}

/**
 * The products due for a reorder for one customer (H8): for each product
 * with a rhythm, its last purchase's day plus days per unit x units; due
 * once the window has opened, 3 days before. A product bought again since
 * is judged by the new purchase. Marketplace purchases, products off sale
 * or out of stock (false in $available) are left out. Pure.
 *
 * @param array      $lines      customer, product_id, quantity, time, type
 * @param array      $rhythms    pg_customer_rhythm_products()
 * @param int        $now
 * @param array|null $available  product_id => bool; a missing id is available
 * @param int        $lead_days
 * @return array  list of product_id, quantity, last, due; by product id
 */
function pg_customer_rhythm_due_products($lines, $rhythms, $now, $available = null, $lead_days = PG_CUSTOMER_RHYTHM_LEAD_DAYS)
{
    $own = array();

    foreach ($lines as $line) {
        if (isset($line['type']) && ((string) $line['type'] === 'marketplace')) {
            continue;
        }

        $line['customer'] = 'self';
        $own[] = $line;
    }

    $due = array();

    foreach (pg_customer_rhythm_purchases($own) as $product => $customers) {
        if (!isset($rhythms[$product]) || (is_array($available) && isset($available[$product]) && !$available[$product])) {
            continue;
        }

        $last = $customers['self'][count($customers['self']) - 1];

        if ($last[1] <= 0) {
            continue;
        }

        $day = $last[0] + (int) round($rhythms[$product]['days_per_unit'] * $last[1] * 86400);

        if ((int) $now >= ($day - ((int) $lead_days * 86400))) {
            $due[$product] = array('product_id' => (int) $product, 'quantity' => (int) $last[1], 'last' => (int) $last[0], 'due' => $day);
        }
    }

    ksort($due);

    return array_values($due);
}
