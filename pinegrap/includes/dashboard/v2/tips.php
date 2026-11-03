<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The texts a new dashboard chart shows for each of its points on hover
 * and keyboard focus (pgDash.chartTips() in assets/js/pg_dash.js): the
 * point's label and figure, and the comparison's figure when a dashed line
 * is drawn beside it. Formatted here, so the script prints no number or
 * word of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * One text per point: "<label>: <figure>", then " · <compare name>:
 * <figure>" where the comparison has a value at that point. Pure.
 *
 * @param string[]   $labels       one per point
 * @param array      $values       the drawn line or bars
 * @param callable   $format       a value => its text with its unit
 * @param array|null $compare      the dashed line, null for none
 * @param string     $compare_name
 * @return string[]
 */
function pg_dashboard_v2_tips($labels, $values, $format, $compare = null, $compare_name = '')
{
    $values = array_values((array) $values);
    $compare = is_array($compare) ? array_values($compare) : array();
    $labels = array_values((array) $labels);
    $count = max(count($values), count($compare));
    $out = array();

    for ($i = 0; $i < $count; $i++) {
        $parts = array();

        if (array_key_exists($i, $values)) {
            $parts[] = $format((int) $values[$i]);
        }

        if (array_key_exists($i, $compare)) {
            $parts[] = (($compare_name !== '') ? $compare_name . ': ' : '') . $format((int) $compare[$i]);
        }

        $label = (string) ($labels[$i] ?? '');
        $out[] = (($label !== '') ? $label . ': ' : '') . implode(' · ', $parts);
    }

    return $out;
}

/**
 * The labels of evenly sized time slots from $from: the hour ("14:00") for
 * hourly slots, else the day in the site's words. Pure apart from the
 * clock's zone.
 *
 * @param int  $from   the first slot's start
 * @param int  $size   seconds per slot (3600 or 86400)
 * @param int  $count
 * @return string[]
 */
function pg_dashboard_v2_slot_labels($from, $size, $count)
{
    $out = array();

    for ($i = 0; $i < $count; $i++) {
        $at = (int) $from + ($i * (int) $size);

        if ((int) $size < 86400) {
            $out[] = date('H:00', $at);
        } else {
            // A day slot can drift an hour across a clock change: the noon
            // of its day names it.
            $out[] = get_absolute_time(array('timestamp' => $at + 43200, 'type' => 'date', 'year' => false, 'format' => 'plain_text', 'timezone_type' => 'site'));
        }
    }

    return $out;
}
