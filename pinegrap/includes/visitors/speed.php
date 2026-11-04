<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Page Speed section of the Visitors overview: the site's 75th
 * percentile of LCP, INP and CLS over the overview's period, the slowest
 * pages and the trend, drawn by pg_visitor_speed_section()
 * (includes/fn/visitor_insights.php). A device and an order filter sit at
 * the heading's end as their own query parameters (speed_device,
 * speed_order), so another section's filters never read them. Nothing is
 * drawn before the page speed table exists (pg_vitals_ready()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

/**
 * The orders of the slowest pages list, key => label.
 *
 * @return array
 */
function pg_visitors_speed_orders()
{
    return array(
        'lcp'   => lang('Slowest by LCP'),
        'inp'   => lang('Slowest by INP'),
        'cls'   => lang('Most layout shift'),
        'views' => lang('Most measured'),
    );
}

/**
 * A filter's value from the request when it is one of the options, else the
 * default. Pure.
 *
 * @param array  $request the query
 * @param string $name    the parameter
 * @param array  $options value => label
 * @param string $default
 * @return string
 */
function pg_visitors_speed_choice(array $request, $name, array $options, $default)
{
    if (isset($request[$name]) && is_scalar($request[$name]) && isset($options[(string) $request[$name]])) {
        return (string) $request[$name];
    }

    return (string) $default;
}

/**
 * The section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML; '' before the page speed table exists
 */
function pg_visitors_section_speed(array $period, array $user)
{
    if (!pg_vitals_ready()) {
        return '';
    }

    $devices = pg_vitals_device_options();
    $orders = pg_visitors_speed_orders();
    $device = pg_visitors_speed_choice($_GET, 'speed_device', $devices, '');
    $order = pg_visitors_speed_choice($_GET, 'speed_order', $orders, 'lcp');

    // The filters keep the period and each other, and come back to the section.
    $keep = array('p', 'from', 'to', 'compare', 'speed_device', 'speed_order');
    $aside = '<div class="pg-visitors-speed-filters">'
        . pg_filter_select('speed_device', $devices, $device, array('label' => lang('Device'), 'icon' => 'phone', 'action' => 'visitors.php#speed', 'keep' => $keep, 'id' => 'pg_visitors_speed_device'))
        . pg_filter_select('speed_order', $orders, $order, array('label' => lang('Order'), 'icon' => 'sort-down', 'action' => 'visitors.php#speed', 'keep' => $keep, 'id' => 'pg_visitors_speed_order'))
        . '</div>';

    $sections = pg_visitors_sections();

    return pg_visitors_section('speed', $sections['speed'], pg_visitor_speed_section($period, $device, $order), $aside);
}
