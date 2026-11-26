<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Delivery tower: the pure rules. Delivery times measured per carrier and
 * destination province from the parcel rows (shipping_tracking_numbers), the
 * on-time judgement against checkout's estimate (ship_tos.delivery_date), and
 * the four problem kinds found on a parcel with how a later status closes
 * them. Nothing here reads or writes the database: the nightly summary and
 * the hourly problem pass hand these functions the rows they read.
 *
 * - A province is the address's state, else its city, folded to one key.
 * - A figure is the median and the 90th percentile of the days from shipped
 *   to delivered, in calendar days and in working days (Monday to Friday).
 *   A province needs PG_DELIVERY_TOWER_MIN_PARCELS parcels for its own
 *   figure; fewer, and the carrier's figure across the country is used;
 *   fewer in all, and the carrier has no figure (so nothing is "stuck").
 * - Problem kinds: stuck (in transit past the 90th percentile plus the
 *   setting's days), not_handed_over (a label still "created" after the
 *   setting's working days, only where the status is read at all), problem
 *   (status exception), returned (status returned).
 * - One open row per parcel and kind; a delivery closes every open kind, any
 *   other status closes the kinds it no longer belongs to.
 * - The link segments under the head of Delivery problems and the Carrier
 *   scorecard, each screen drawn only for the person who may open it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Parcels a province (or a carrier across the country) needs for a figure.
define('PG_DELIVERY_TOWER_MIN_PARCELS', 20);

/**
 * The problem kinds and the parcel statuses each belongs to. A kind stays
 * open while the parcel's status is one of its own.
 *
 * @return array kind => list of statuses
 */
function pg_delivery_tower_kinds()
{
    return array(
        'stuck' => array('in_transit', 'out_for_delivery'),
        'not_handed_over' => array('created'),
        'problem' => array('exception'),
        'returned' => array('returned'),
    );
}

/**
 * The settings with their defaults: each kind on or off, the working days
 * a label may wait for its first scan, the days added to the 90th
 * percentile before a parcel is stuck. Unknown keys are dropped.
 *
 * @param array $raw stored settings, possibly partial
 * @return array
 */
function pg_delivery_tower_settings_clean($raw)
{
    $raw = is_array($raw) ? $raw : array();
    $settings = array();
    foreach (array_keys(pg_delivery_tower_kinds()) as $kind) {
        $settings[$kind] = !isset($raw[$kind]) || (bool) $raw[$kind];
    }
    $settings['not_handed_over_days'] = _pg_delivery_tower_setting_days($raw, 'not_handed_over_days', 1, 1, 10);
    $settings['stuck_extra_days'] = _pg_delivery_tower_setting_days($raw, 'stuck_extra_days', 1, 0, 30);
    return $settings;
}

/**
 * One whole-day setting: the default when missing or not a number, clamped.
 */
function _pg_delivery_tower_setting_days($raw, $key, $default, $min, $max)
{
    if (!isset($raw[$key]) || !is_numeric($raw[$key])) {
        return $default;
    }
    return max($min, min($max, (int) $raw[$key]));
}

/**
 * The province key of an address: its state, else its city, trimmed, spaces
 * collapsed, lower case with every Turkish i folded to a plain "i", so
 * "İstanbul", "ISTANBUL" and "istanbul" are one province.
 *
 * @param string $state
 * @param string $city
 * @return string '' when neither is given
 */
function pg_delivery_tower_province_key($state, $city)
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) $state));
    if ($name === '') {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $city));
    }
    if ($name === '') {
        return '';
    }
    $name = str_replace(array('İ', 'I', 'ı'), 'i', $name);
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

/**
 * A percentile with linear interpolation between ranks (the spreadsheet's
 * PERCENTILE.INC).
 *
 * @param array $values numbers
 * @param float $percent 0 to 100
 * @return float|null null for no values
 */
function pg_delivery_tower_percentile($values, $percent)
{
    if (!is_array($values) || !$values) {
        return null;
    }
    $values = array_map('floatval', array_values($values));
    sort($values);
    $position = (count($values) - 1) * max(0, min(100, $percent)) / 100;
    $lower = (int) floor($position);
    $upper = (int) ceil($position);
    $value = $values[$lower] + ($values[$upper] - $values[$lower]) * ($position - $lower);
    return round($value, 2);
}

/**
 * The median.
 *
 * @param array $values numbers
 * @return float|null
 */
function pg_delivery_tower_median($values)
{
    return pg_delivery_tower_percentile($values, 50);
}

/**
 * The local date of a unix time.
 */
function _pg_delivery_tower_date($time, $timezone)
{
    $date = new DateTime('@' . (int) $time);
    $date->setTimezone(new DateTimeZone($timezone ? $timezone : date_default_timezone_get()));
    return new DateTime($date->format('Y-m-d'), new DateTimeZone('UTC'));
}

/**
 * Calendar days between two moments, counted by local date: shipped on
 * Monday and delivered on Tuesday is one day, whatever the hours.
 *
 * @param int $from unix time
 * @param int $to unix time
 * @param string $timezone the site's time zone; '' = PHP's default
 * @return int 0 when $to is not after $from's date
 */
function pg_delivery_tower_calendar_days($from, $to, $timezone = '')
{
    $start = _pg_delivery_tower_date($from, $timezone);
    $end = _pg_delivery_tower_date($to, $timezone);
    if ($end <= $start) {
        return 0;
    }
    return (int) $start->diff($end)->days;
}

/**
 * Working days between two moments: the dates after $from's date up to and
 * including $to's date that fall Monday to Friday. Public holidays are not
 * known and count as working days.
 *
 * @param int $from unix time
 * @param int $to unix time
 * @param string $timezone the site's time zone; '' = PHP's default
 * @return int
 */
function pg_delivery_tower_working_days($from, $to, $timezone = '')
{
    $days = pg_delivery_tower_calendar_days($from, $to, $timezone);
    if ($days === 0) {
        return 0;
    }
    $start = _pg_delivery_tower_date($from, $timezone);
    // Whole weeks hold five working days each; the rest is walked.
    $working = intdiv($days, 7) * 5;
    $weekday = (int) $start->format('N');
    for ($i = 1; $i <= $days % 7; $i++) {
        $day = ($weekday + $i - 1) % 7 + 1;
        if ($day <= 5) {
            $working++;
        }
    }
    return $working;
}

/**
 * Whether a parcel arrived on time: delivered on or before checkout's
 * estimated delivery date.
 *
 * @param int $delivered_at unix time, 0 = not delivered
 * @param string $estimate ship_tos.delivery_date (Y-m-d, '0000-00-00' = none)
 * @param string $timezone
 * @return bool|null null when it is not judged (no estimate, not delivered)
 */
function pg_delivery_tower_on_time($delivered_at, $estimate, $timezone = '')
{
    $estimate = (string) $estimate;
    if ((int) $delivered_at <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $estimate) || $estimate === '0000-00-00') {
        return null;
    }
    return _pg_delivery_tower_date($delivered_at, $timezone)->format('Y-m-d') <= $estimate;
}

/**
 * The measured figures of delivered parcels, per carrier: across the
 * country, and per province with enough parcels. A row that cannot be
 * measured (no carrier, no ship date, delivered before shipped) is left out.
 * Marketplace parcels are measured like any other.
 *
 * @param array $rows each: carrier, state, city, shipped_at, delivered_at
 * @param int $min parcels needed for a figure
 * @param string $timezone
 * @return array carrier => array('country' => figure|null, 'provinces' => array(key => figure))
 */
function pg_delivery_tower_figures($rows, $min = PG_DELIVERY_TOWER_MIN_PARCELS, $timezone = '')
{
    $days = array();
    foreach ((array) $rows as $row) {
        $carrier = isset($row['carrier']) ? (string) $row['carrier'] : '';
        $shipped = isset($row['shipped_at']) ? (int) $row['shipped_at'] : 0;
        $delivered = isset($row['delivered_at']) ? (int) $row['delivered_at'] : 0;
        if ($carrier === '' || $shipped <= 0 || $delivered < $shipped) {
            continue;
        }
        $province = pg_delivery_tower_province_key(isset($row['state']) ? $row['state'] : '', isset($row['city']) ? $row['city'] : '');
        $pair = array(
            pg_delivery_tower_calendar_days($shipped, $delivered, $timezone),
            pg_delivery_tower_working_days($shipped, $delivered, $timezone),
        );
        $days[$carrier]['country'][] = $pair;
        if ($province !== '') {
            $days[$carrier]['provinces'][$province][] = $pair;
        }
    }

    $figures = array();
    foreach ($days as $carrier => $parts) {
        $figures[$carrier] = array(
            'country' => _pg_delivery_tower_figure($parts['country'], $min),
            'provinces' => array(),
        );
        if (isset($parts['provinces'])) {
            foreach ($parts['provinces'] as $province => $pairs) {
                $figure = _pg_delivery_tower_figure($pairs, $min);
                if ($figure !== null) {
                    $figures[$carrier]['provinces'][$province] = $figure;
                }
            }
        }
    }
    return $figures;
}

/**
 * One figure from (calendar, working) day pairs, or null under the minimum.
 */
function _pg_delivery_tower_figure($pairs, $min)
{
    if (count($pairs) < max(1, (int) $min)) {
        return null;
    }
    $calendar = array();
    $working = array();
    foreach ($pairs as $pair) {
        $calendar[] = $pair[0];
        $working[] = $pair[1];
    }
    return array(
        'count' => count($pairs),
        'median' => pg_delivery_tower_median($calendar),
        'p90' => pg_delivery_tower_percentile($calendar, 90),
        'median_working' => pg_delivery_tower_median($working),
        'p90_working' => pg_delivery_tower_percentile($working, 90),
    );
}

/**
 * The figure a parcel is judged by: its carrier's province figure, else the
 * carrier's country figure, else none.
 *
 * @param array $figures from pg_delivery_tower_figures()
 * @param string $carrier
 * @param string $province a province key
 * @return array|null
 */
function pg_delivery_tower_figure_for($figures, $carrier, $province)
{
    $carrier = (string) $carrier;
    if (!isset($figures[$carrier])) {
        return null;
    }
    if ($province !== '' && isset($figures[$carrier]['provinces'][$province])) {
        return $figures[$carrier]['provinces'][$province];
    }
    return $figures[$carrier]['country'];
}

/**
 * Whether anything reads a parcel's status, so "still created" means
 * something: a carrier or a marketplace wrote it, or a tracking driver
 * follows its carrier.
 *
 * @param string $source shipping_tracking_numbers.source
 * @param bool $has_driver the carrier has a tracking driver or a webhook
 * @return bool
 */
function pg_delivery_tower_status_is_read($source, $has_driver)
{
    return $has_driver || in_array((string) $source, array('carrier', 'marketplace'), true);
}

/**
 * The problem kinds a parcel shows now.
 *
 * @param array $parcel carrier, state, city, status, shipped_at, label_at
 *   (when its label was made, 0 = none), status_read (bool)
 * @param array $figures from pg_delivery_tower_figures()
 * @param int $now unix time
 * @param array $settings from pg_delivery_tower_settings_clean()
 * @param string $timezone
 * @return array list of kinds
 */
function pg_delivery_tower_problems_found($parcel, $figures, $now, $settings, $timezone = '')
{
    $status = isset($parcel['status']) ? (string) $parcel['status'] : '';
    $kinds = pg_delivery_tower_kinds();
    $found = array();

    if (!empty($settings['stuck']) && in_array($status, $kinds['stuck'], true) && !empty($parcel['shipped_at'])) {
        $province = pg_delivery_tower_province_key(isset($parcel['state']) ? $parcel['state'] : '', isset($parcel['city']) ? $parcel['city'] : '');
        $figure = pg_delivery_tower_figure_for($figures, isset($parcel['carrier']) ? $parcel['carrier'] : '', $province);
        if ($figure !== null) {
            $days = pg_delivery_tower_calendar_days($parcel['shipped_at'], $now, $timezone);
            if ($days > $figure['p90'] + $settings['stuck_extra_days']) {
                $found[] = 'stuck';
            }
        }
    }

    if (!empty($settings['not_handed_over']) && in_array($status, $kinds['not_handed_over'], true)
        && !empty($parcel['status_read']) && !empty($parcel['label_at'])) {
        // Working days fully passed since the label: today is still running.
        $passed = pg_delivery_tower_working_days($parcel['label_at'], $now, $timezone);
        $today = (int) _pg_delivery_tower_date($now, $timezone)->format('N');
        if ($today <= 5 && $passed > 0) {
            $passed--;
        }
        if ($passed >= $settings['not_handed_over_days']) {
            $found[] = 'not_handed_over';
        }
    }

    foreach (array('problem', 'returned') as $kind) {
        if (!empty($settings[$kind]) && in_array($status, $kinds[$kind], true)) {
            $found[] = $kind;
        }
    }
    return $found;
}

/**
 * What a pass does on one parcel: the kinds to open (found and not open
 * yet, so a pass can run again) and the open kinds to close with their
 * reason. A delivery closes every open kind ("delivered"); otherwise an
 * open kind closes once the status left that kind's statuses: "scanned"
 * for a label that moved, "status_changed" for the rest. A kind whose
 * status still holds stays open even when no longer found (a figure moved),
 * so the desk keeps its record. A found kind that is held (closed by hand
 * since the last status change, pg_delivery_tower_held_kinds()) is not
 * opened again.
 *
 * @param string $status the parcel's status now
 * @param array $open kinds open on the parcel
 * @param array $found kinds from pg_delivery_tower_problems_found()
 * @param array $held kinds from pg_delivery_tower_held_kinds()
 * @return array array('open' => list, 'close' => array(kind => reason))
 */
function pg_delivery_tower_pass($status, $open, $found, $held = array())
{
    $status = (string) $status;
    $kinds = pg_delivery_tower_kinds();
    $result = array('open' => array(), 'close' => array());

    foreach ((array) $open as $kind) {
        if ($status === 'delivered') {
            $result['close'][$kind] = 'delivered';
        } elseif (isset($kinds[$kind]) && !in_array($status, $kinds[$kind], true)) {
            $result['close'][$kind] = $kind === 'not_handed_over' ? 'scanned' : 'status_changed';
        }
    }
    if ($status !== 'delivered') {
        foreach ((array) $found as $kind) {
            if (!in_array($kind, (array) $open, true) && !in_array($kind, (array) $held, true) && !in_array($kind, $result['open'], true)) {
                $result['open'][] = $kind;
            }
        }
    }
    return $result;
}

/**
 * The kinds staff closed by hand that stay closed: a kind whose latest hand
 * close (closed_by > 0, any of the desk's reasons) is at or after the
 * parcel's last status change. status_at moves only when the status changes,
 * so a parcel whose status held since the close is not judged again for that
 * kind; a status_at of 0 (a label never scanned) holds as well. A status read
 * late and dated before the close keeps the kind held.
 *
 * @param array $hand_closed kind => the latest closed_at of a hand close
 * @param int   $status_at   shipping_tracking_numbers.status_at
 * @return array list of kinds
 */
function pg_delivery_tower_held_kinds($hand_closed, $status_at)
{
    $held = array();
    foreach ((array) $hand_closed as $kind => $closed_at) {
        if ((int) $closed_at > 0 && (int) $closed_at >= (int) $status_at) {
            $held[] = (string) $kind;
        }
    }
    return $held;
}

/**
 * The screens the link segments under the head of Delivery problems and the
 * Carrier scorecard lead to, in their order, each as key => array(label,
 * file): only those the person may open. Problems takes the orders right
 * (validate_ecommerce_access()), the scorecard the commerce reports right
 * (validate_ecommerce_report_access()) and shipping switched on.
 *
 * @param array $user     a validate_user() row: role, manage_ecommerce, manage_ecommerce_reports
 * @param bool  $shipping commerce and its shipping are on
 * @return array
 */
function pg_delivery_tower_segments(array $user, $shipping)
{
    $role = isset($user['role']) ? (int) $user['role'] : 3;
    $segments = array();

    if (($role < 3) || !empty($user['manage_ecommerce'])) {
        $segments['problems'] = array(lang('Problems'), 'view_delivery_problems.php');
    }

    if ($shipping && (($role < 3) || !empty($user['manage_ecommerce_reports']))) {
        $segments['scorecard'] = array(lang('Carrier scorecard'), 'view_carrier_scorecard.php');
    }

    return $segments;
}

/**
 * The link segments themselves (an area strip: the segmented control of
 * links, printed first in #content): the open screen's segment selected,
 * the other one its link. Empty when the person may open only one of the
 * two screens, as one segment leads nowhere.
 *
 * @param string $current  'problems' or 'scorecard'
 * @param array  $user     a validate_user() row
 * @param bool   $shipping commerce and its shipping are on
 * @return string
 */
function pg_delivery_tower_segments_html($current, array $user, $shipping)
{
    $segments = pg_delivery_tower_segments($user, $shipping);

    if (count($segments) < 2) {
        return '';
    }

    $items = '';

    foreach ($segments as $key => $segment) {
        $active = ($key === $current);
        $items .= '<a class="pg-seg-item' . ($active ? ' is-active' : '') . '" href="' . h($segment[1]) . '"' . ($active ? ' aria-current="page"' : '') . '>' . h($segment[0]) . '</a>';
    }

    return '<nav class="pg-area-tabs mb-3" aria-label="' . h(lang('Delivery')) . '"><div class="pg-seg">' . $items . '</div></nav>';
}
