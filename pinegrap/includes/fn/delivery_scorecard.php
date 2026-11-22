<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Delivery tower: the carrier scorecard and the two suggestions.
 *
 * - The scorecard (view_carrier_scorecard.php): per carrier, and under it
 *   per province, the parcels shipped in a month, the share delivered on
 *   time against checkout's estimate, the median days from shipped to
 *   delivered, the share with a delivery problem and the share returned.
 *   "Last 12 months" gives one of those figures per carrier and month.
 *   A carrier or province with fewer than PG_DELIVERY_TOWER_MIN_PARCELS
 *   parcels has no figures.
 * - The arrival suggestion: a shipping method whose typed transit days
 *   differ by a day or more from its carrier's measured median (the nightly
 *   summary of includes/fn/delivery_tower_passes.php). Nothing is changed.
 * - The carrier order: carrier options ordered by the province's on-time
 *   share, then by a quoted price. A suggestion only.
 *
 * The fold and the orderings are pure; only the *_read() functions query.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Parcel rows read per query by the scorecard.
define('PG_DELIVERY_SCORECARD_PAGE', 2000);

/**
 * The figures a scorecard cell can show, in the table's order.
 *
 * @return array key => English heading (passed through lang() by the screen)
 */
function pg_delivery_scorecard_figure_keys()
{
    return array(
        'parcels' => 'Parcels',
        'on_time' => 'On time',
        'median' => 'Median days',
        'problem' => 'With a problem',
        'returned' => 'Returned',
    );
}

/**
 * A month key (YYYY-MM) checked, else the current month's. Pure.
 *
 * @param mixed  $key The requested key; anything but text is a broken key
 * @param int    $now
 * @return string
 */
function pg_delivery_scorecard_month($key, $now)
{
    // A key sent as a list (?month[]=x) is a broken key.
    $key = is_scalar($key) ? (string) $key : '';
    if (preg_match('/^(\d{4})-(\d{2})$/', $key, $match) && (int) $match[2] >= 1 && (int) $match[2] <= 12 && (int) $match[1] >= 2000) {
        $current = date('Y-m', (int) $now);
        return strcmp($key, $current) > 0 ? $current : $key;
    }
    return date('Y-m', (int) $now);
}

/**
 * The month $step months away from $key. Pure.
 *
 * @param string $key YYYY-MM
 * @param int    $step
 * @return string
 */
function pg_delivery_scorecard_month_step($key, $step)
{
    $index = (int) substr($key, 0, 4) * 12 + (int) substr($key, 5, 2) - 1 + (int) $step;
    return sprintf('%04d-%02d', intdiv($index, 12), $index % 12 + 1);
}

/**
 * The twelve month keys ending with $key, oldest first. Pure.
 *
 * @param string $key
 * @return array
 */
function pg_delivery_scorecard_months($key)
{
    $keys = array();
    for ($step = -11; $step <= 0; $step++) {
        $keys[] = pg_delivery_scorecard_month_step($key, $step);
    }
    return $keys;
}

/**
 * The first moment of a month and of the month after it, in PHP's time zone
 * (the site's).
 *
 * @param string $key YYYY-MM
 * @return array array(from, to)
 */
function pg_delivery_scorecard_bounds($key)
{
    $next = pg_delivery_scorecard_month_step($key, 1);
    return array(
        mktime(0, 0, 0, (int) substr($key, 5, 2), 1, (int) substr($key, 0, 4)),
        mktime(0, 0, 0, (int) substr($next, 5, 2), 1, (int) substr($next, 0, 4)),
    );
}

/**
 * An empty tally.
 */
function _pg_delivery_scorecard_tally()
{
    return array('parcels' => 0, 'judged' => 0, 'on_time' => 0, 'days' => array(), 'problem' => 0, 'returned' => 0);
}

/**
 * Adds parcel rows to a scorecard state. Pure.
 *
 * The state is month => carrier => array('all' => tally, 'provinces' =>
 * key => tally, 'names' => key => the province as first written). Provinces
 * are kept only when $provinces is true (the month view).
 *
 * @param array  $state
 * @param array  $rows each: carrier, state, city, shipped_at, delivered_at,
 *                     delivery_date, status, has_problem
 * @param bool   $provinces
 * @param string $timezone
 * @return void
 */
function pg_delivery_scorecard_add(&$state, $rows, $provinces = true, $timezone = '')
{
    foreach ((array) $rows as $row) {
        $shipped = isset($row['shipped_at']) ? (int) $row['shipped_at'] : 0;
        if ($shipped <= 0) {
            continue;
        }
        $month = _pg_delivery_tower_date($shipped, $timezone)->format('Y-m');
        $carrier = isset($row['carrier']) ? trim((string) $row['carrier']) : '';
        $delivered = isset($row['delivered_at']) ? (int) $row['delivered_at'] : 0;
        $status = isset($row['status']) ? (string) $row['status'] : '';

        $mark = array(
            'days' => ($delivered >= $shipped) ? pg_delivery_tower_calendar_days($shipped, $delivered, $timezone) : null,
            'on_time' => ($delivered >= $shipped) ? pg_delivery_tower_on_time($delivered, isset($row['delivery_date']) ? $row['delivery_date'] : '', $timezone) : null,
            'problem' => !empty($row['has_problem']),
            'returned' => $status === 'returned',
        );

        if (!isset($state[$month][$carrier])) {
            $state[$month][$carrier] = array('all' => _pg_delivery_scorecard_tally(), 'provinces' => array(), 'names' => array());
        }
        _pg_delivery_scorecard_count($state[$month][$carrier]['all'], $mark);

        if ($provinces) {
            $state_text = isset($row['state']) ? (string) $row['state'] : '';
            $city_text = isset($row['city']) ? (string) $row['city'] : '';
            $key = pg_delivery_tower_province_key($state_text, $city_text);
            if ($key !== '') {
                if (!isset($state[$month][$carrier]['provinces'][$key])) {
                    $state[$month][$carrier]['provinces'][$key] = _pg_delivery_scorecard_tally();
                    $state[$month][$carrier]['names'][$key] = preg_replace('/\s+/u', ' ', trim(trim($state_text) !== '' ? $state_text : $city_text));
                }
                _pg_delivery_scorecard_count($state[$month][$carrier]['provinces'][$key], $mark);
            }
        }
    }
}

/**
 * Counts one parcel's marks into a tally.
 */
function _pg_delivery_scorecard_count(&$tally, $mark)
{
    $tally['parcels']++;
    if ($mark['days'] !== null) {
        $tally['days'][] = $mark['days'];
    }
    if ($mark['on_time'] !== null) {
        $tally['judged']++;
        if ($mark['on_time']) {
            $tally['on_time']++;
        }
    }
    if ($mark['problem']) {
        $tally['problem']++;
    }
    if ($mark['returned']) {
        $tally['returned']++;
    }
}

/**
 * A tally's figures. Pure.
 *
 * @param array $tally
 * @param int   $min parcels needed for figures
 * @return array parcels, enough, on_time (0-100 or null when nothing was
 *               judged), median (days or null when nothing arrived),
 *               problem and returned (0-100)
 */
function pg_delivery_scorecard_figures($tally, $min = PG_DELIVERY_TOWER_MIN_PARCELS)
{
    $parcels = (int) $tally['parcels'];
    $share = function ($count, $of) {
        return $of > 0 ? round($count * 100 / $of, 1) : null;
    };
    return array(
        'parcels' => $parcels,
        'enough' => $parcels >= max(1, (int) $min),
        'on_time' => $share((int) $tally['judged'] > 0 ? (int) $tally['on_time'] : 0, (int) $tally['judged']),
        'median' => pg_delivery_tower_median($tally['days']),
        'problem' => $share((int) $tally['problem'], $parcels),
        'returned' => $share((int) $tally['returned'], $parcels),
    );
}

/**
 * Sums tallies.
 */
function _pg_delivery_scorecard_merge($tallies)
{
    $sum = _pg_delivery_scorecard_tally();
    foreach ($tallies as $tally) {
        foreach (array('parcels', 'judged', 'on_time', 'problem', 'returned') as $field) {
            $sum[$field] += (int) $tally[$field];
        }
        foreach ($tally['days'] as $days) {
            $sum['days'][] = $days;
        }
    }
    return $sum;
}

/**
 * One month of the scorecard: the period's figures across carriers, and a
 * row per carrier (most parcels first) with its provinces (most parcels
 * first). Pure.
 *
 * @param array  $state pg_delivery_scorecard_add()'s
 * @param string $month YYYY-MM
 * @param int    $min
 * @return array total => figures, carriers => list of array(carrier,
 *               figures, provinces => list of array(key, name, figures))
 */
function pg_delivery_scorecard_month_view($state, $month, $min = PG_DELIVERY_TOWER_MIN_PARCELS)
{
    $carriers = isset($state[$month]) ? $state[$month] : array();
    $rows = array();
    $all = array();
    foreach ($carriers as $carrier => $parts) {
        $all[] = $parts['all'];
        $provinces = array();
        foreach ($parts['provinces'] as $key => $tally) {
            $provinces[] = array('key' => (string) $key, 'name' => $parts['names'][$key], 'figures' => pg_delivery_scorecard_figures($tally, $min));
        }
        usort($provinces, '_pg_delivery_scorecard_by_parcels');
        $rows[] = array('carrier' => (string) $carrier, 'figures' => pg_delivery_scorecard_figures($parts['all'], $min), 'provinces' => $provinces);
    }
    usort($rows, '_pg_delivery_scorecard_by_parcels');
    return array('total' => pg_delivery_scorecard_figures(_pg_delivery_scorecard_merge($all), 1), 'carriers' => $rows);
}

/**
 * Most parcels first, then by name.
 */
function _pg_delivery_scorecard_by_parcels($a, $b)
{
    if ($a['figures']['parcels'] !== $b['figures']['parcels']) {
        return $b['figures']['parcels'] - $a['figures']['parcels'];
    }
    return strcmp(isset($a['name']) ? $a['name'] : $a['carrier'], isset($b['name']) ? $b['name'] : $b['carrier']);
}

/**
 * Twelve months of the scorecard: a row per carrier (most parcels over the
 * months first), each with its figures per month, and the period's figures
 * across carriers and months. Pure.
 *
 * @param array $state
 * @param array $months pg_delivery_scorecard_months()
 * @param int   $min
 * @return array total => figures, carriers => list of array(carrier,
 *               months => month => figures)
 */
function pg_delivery_scorecard_year_view($state, $months, $min = PG_DELIVERY_TOWER_MIN_PARCELS)
{
    $carriers = array();
    $all = array();
    foreach ($months as $month) {
        if (!isset($state[$month])) {
            continue;
        }
        foreach ($state[$month] as $carrier => $parts) {
            $carriers[$carrier][$month] = $parts['all'];
            $all[] = $parts['all'];
        }
    }
    $rows = array();
    foreach ($carriers as $carrier => $tallies) {
        $cells = array();
        foreach ($months as $month) {
            $cells[$month] = pg_delivery_scorecard_figures(isset($tallies[$month]) ? $tallies[$month] : _pg_delivery_scorecard_tally(), $min);
        }
        $rows[] = array('carrier' => (string) $carrier, 'figures' => pg_delivery_scorecard_figures(_pg_delivery_scorecard_merge($tallies), $min), 'months' => $cells);
    }
    usort($rows, '_pg_delivery_scorecard_by_parcels');
    return array('total' => pg_delivery_scorecard_figures(_pg_delivery_scorecard_merge($all), 1), 'carriers' => $rows);
}

/**
 * One figure as a cell prints it: a share with a percent sign, days with
 * one decimal, parcels as a whole number; '' when there is no value. Pure.
 *
 * @param array  $figures pg_delivery_scorecard_figures()
 * @param string $key
 * @return string
 */
function pg_delivery_scorecard_cell($figures, $key)
{
    $value = isset($figures[$key]) ? $figures[$key] : null;
    if ($value === null) {
        return '';
    }
    if ($key === 'parcels') {
        return pg_format_number($value, 0);
    }
    if ($key === 'median') {
        return pg_format_number($value, 1);
    }
    return lang(array('string' => '{var:1}%', 'vars' => pg_format_number($value, 1)));
}

/**
 * A carrier's name as the panel shows it: the known carrier's name, else
 * the key as it was typed.
 *
 * @param string $carrier
 * @return string '' for none
 */
function pg_delivery_scorecard_carrier_name($carrier)
{
    $carriers = pg_shipping_carriers();
    $carrier = (string) $carrier;
    return isset($carriers[$carrier]['name']) ? $carriers[$carrier]['name'] : $carrier;
}

/**
 * Reads the parcels shipped from $from up to $to into a scorecard state, by
 * id a page at a time. Parcels of incomplete or cancelled orders are left
 * out, as in the problem pass.
 *
 * @param int  $from
 * @param int  $to
 * @param bool $provinces
 * @return array
 */
function pg_delivery_scorecard_read($from, $to, $provinces)
{
    $state = array();
    $after = 0;
    $problems = pg_delivery_tower_ready()
        ? "EXISTS (SELECT 1 FROM shipment_problems WHERE shipment_problems.shipment_id = shipping_tracking_numbers.id)"
        : "0";
    while (true) {
        $rows = db_items(
            "SELECT
                shipping_tracking_numbers.id,
                shipping_tracking_numbers.carrier,
                shipping_tracking_numbers.status,
                shipping_tracking_numbers.shipped_at,
                shipping_tracking_numbers.delivered_at,
                ship_tos.state,
                ship_tos.city,
                ship_tos.delivery_date,
                " . $problems . " AS has_problem
            FROM shipping_tracking_numbers
            INNER JOIN orders ON orders.id = shipping_tracking_numbers.order_id
            LEFT JOIN ship_tos ON ship_tos.id = shipping_tracking_numbers.ship_to_id
            WHERE
                (shipping_tracking_numbers.id > '" . (int) $after . "')
                AND (shipping_tracking_numbers.shipped_at >= '" . (int) $from . "')
                AND (shipping_tracking_numbers.shipped_at < '" . (int) $to . "')
                AND (orders.status NOT IN ('incomplete', 'cancelled'))
            ORDER BY shipping_tracking_numbers.id
            LIMIT " . PG_DELIVERY_SCORECARD_PAGE);
        if (!is_array($rows) || !$rows) {
            break;
        }
        pg_delivery_scorecard_add($state, $rows, $provinces);
        $after = (int) $rows[count($rows) - 1]['id'];
        if (count($rows) < PG_DELIVERY_SCORECARD_PAGE) {
            break;
        }
    }
    return $state;
}

/**
 * The arrival check of one shipping method: its carrier's measured median
 * against the typed transit days. Working days are compared when the method
 * counts no weekend day as transit, calendar days otherwise. Pure.
 *
 * @param array $method   base_transit_days, carrier (the key it ships
 *                        with), transit_on_saturday, transit_on_sunday
 * @param array $figures  the summary's figures (carrier => country, provinces)
 * @return array|null measured (days, one decimal), set (days); null when
 *                    the carrier has no figure or the gap is under a day
 */
function pg_delivery_tower_arrival_check($method, $figures)
{
    $carrier = isset($method['carrier']) ? (string) $method['carrier'] : '';
    $figure = ($carrier !== '' && isset($figures[$carrier]['country'])) ? $figures[$carrier]['country'] : null;
    if (!is_array($figure)) {
        return null;
    }
    $weekend = !empty($method['transit_on_saturday']) || !empty($method['transit_on_sunday']);
    $measured = $weekend ? $figure['median'] : $figure['median_working'];
    if ($measured === null) {
        return null;
    }
    $set = isset($method['base_transit_days']) ? (int) $method['base_transit_days'] : 0;
    if (abs((float) $measured - $set) < 1) {
        return null;
    }
    return array('measured' => round((float) $measured, 1), 'set' => $set);
}

/**
 * The arrival check's sentence: "Standard delivery: measured 2.6 days, set 1".
 *
 * @param string $name  the method's name
 * @param array  $check pg_delivery_tower_arrival_check()
 * @return string plain text
 */
function pg_delivery_tower_arrival_sentence($name, $check)
{
    return lang(array(
        'string' => '{var:1}: measured {var:2} days, set {var:3}',
        'vars' => array((string) $name, pg_format_number($check['measured'], 1), pg_format_number($check['set'], 0)),
    ));
}

/**
 * The carrier a shipping method ships with: its own carrier, else what its
 * code spells.
 *
 * @param array $method carrier, code
 * @return string
 */
function pg_delivery_tower_method_carrier($method)
{
    return pg_shipment_label_carrier(isset($method['carrier']) ? $method['carrier'] : '', isset($method['code']) ? $method['code'] : '');
}

/**
 * The enabled shipping methods whose transit days differ from what their
 * carrier measured, by name.
 *
 * @return array list of array(id, name, check)
 */
function pg_delivery_tower_arrival_checks_read()
{
    $summary = pg_delivery_tower_summary();
    if ($summary === null) {
        return array();
    }
    $methods = db_items("SELECT id, name, code, carrier, base_transit_days, transit_on_saturday, transit_on_sunday FROM shipping_methods WHERE status = 'enabled' ORDER BY name");
    $checks = array();
    foreach ((array) $methods as $method) {
        $method['carrier'] = pg_delivery_tower_method_carrier($method);
        $check = pg_delivery_tower_arrival_check($method, $summary['figures']);
        if ($check !== null) {
            $checks[] = array('id' => (int) $method['id'], 'name' => (string) $method['name'], 'check' => $check);
        }
    }
    return $checks;
}

/**
 * The on-time share a carrier reached for a province, else across the
 * country; null when nothing was judged. Pure.
 *
 * @param array  $on_time the summary's on_time (carrier => country, provinces)
 * @param string $carrier
 * @param string $province key
 * @return float|null 0-100
 */
function pg_delivery_tower_on_time_share($on_time, $carrier, $province)
{
    $part = null;
    if ($province !== '' && isset($on_time[$carrier]['provinces'][$province])) {
        $part = $on_time[$carrier]['provinces'][$province];
    } elseif (isset($on_time[$carrier]['country'])) {
        $part = $on_time[$carrier]['country'];
    }
    if (!is_array($part) || (int) $part['judged'] <= 0) {
        return null;
    }
    return round((int) $part['on_time'] * 100 / (int) $part['judged'], 1);
}

/**
 * Carrier options in the order they are suggested: the highest on-time
 * share for the province first, an unmeasured carrier after the measured
 * ones; equal shares by the lower quoted price, a price before none; then
 * as given. Pure.
 *
 * @param array  $options  list of array(carrier, price: minor units or null, ...)
 * @param array  $on_time  the summary's on_time
 * @param string $province key
 * @return array the options in order, each with share (0-100 or null) added
 */
function pg_delivery_tower_carrier_order($options, $on_time, $province)
{
    $list = array();
    foreach (array_values((array) $options) as $index => $option) {
        $option['share'] = pg_delivery_tower_on_time_share($on_time, isset($option['carrier']) ? (string) $option['carrier'] : '', (string) $province);
        $option['_index'] = $index;
        $list[] = $option;
    }
    usort($list, function ($a, $b) {
        if (($a['share'] === null) !== ($b['share'] === null)) {
            return $a['share'] === null ? 1 : -1;
        }
        if ($a['share'] !== null && $a['share'] != $b['share']) {
            return $a['share'] < $b['share'] ? 1 : -1;
        }
        $pa = isset($a['price']) ? $a['price'] : null;
        $pb = isset($b['price']) ? $b['price'] : null;
        if (($pa === null) !== ($pb === null)) {
            return $pa === null ? 1 : -1;
        }
        if ($pa !== null && $pa != $pb) {
            return $pa < $pb ? -1 : 1;
        }
        return $a['_index'] - $b['_index'];
    });
    foreach ($list as $index => $option) {
        unset($list[$index]['_index']);
    }
    return $list;
}
