<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Page speed as visitors' browsers measured it (page_vitals_hourly): the
 * site's 75th percentile of LCP, INP and CLS with the share of good
 * measurements, the slowest pages and the trend by day. The section is drawn
 * by pg_visitor_speed_section() (includes/fn/visitor_insights.php). Opened
 * from the Visitors menu; the gate is the visitor reports' one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_visitors_access($user);

$window = pg_visitor_insight_window($_GET['p'] ?? '');
$devices = pg_vitals_device_options();
$device = (isset($_GET['device']) && is_scalar($_GET['device']) && isset($devices[(string) $_GET['device']])) ? (string) $_GET['device'] : '';
$orders = array(
    'lcp'   => lang('Slowest by LCP'),
    'inp'   => lang('Slowest by INP'),
    'cls'   => lang('Most layout shift'),
    'views' => lang('Most measured'),
);
$order = (isset($_GET['order']) && is_scalar($_GET['order']) && isset($orders[(string) $_GET['order']])) ? (string) $_GET['order'] : 'lcp';

$shell = array(
    'title'               => lang('Page Speed'),
    'extra classes'       => 'page',
    'icon'                => 'statistics',
    'heading'             => lang('Page Speed'),
    'heading_description' => lang('How fast pages loaded and answered in visitors\' browsers: the 75th percentile of Largest Contentful Paint, Interaction to Next Paint and Cumulative Layout Shift, by Google\'s good and poor limits. Only figures per page and hour are kept, never a visitor; days are kept for 400 days.'),
    'breadcrumb'          => array(
        array('label' => lang('Visitors'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_reports.php'),
        array('label' => lang('Page Speed')),
    ),
);

if (!pg_vitals_ready()) {
    echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'grow' => '<span class="fg-warning"><i class="bi bi-exclamation-triangle me-5" aria-hidden="true"></i>' . h(lang('The page speed table does not exist yet. Please run the software upgrade to create it.')) . '</span>',
    ), array('class' => 'my-5')) . '
</main>' . output_footer();
    exit();
}

$keep = array('p', 'device', 'order');

echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'secondary' => array(
            array('label' => lang('AI Visibility'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/visitor_ai.php?p=' . rawurlencode($window['key']), 'icon' => 'bi-robot'),
        ),
        'grow'      => '<span class="small fg-secondary">' . h(lang(array('string' => 'Since {var:1}', 'vars' => array(prepare_form_data_for_output($window['from'], 'date', false))))) . '</span>',
        'search'    => array(
            pg_filter_select('p', pg_visitor_insight_period_options(), $window['key'], array('label' => lang('Period'), 'icon' => 'calendar', 'action' => 'visitor_vitals.php', 'keep' => $keep)),
            pg_filter_select('device', $devices, $device, array('label' => lang('Device'), 'icon' => 'phone', 'action' => 'visitor_vitals.php', 'keep' => $keep)),
            pg_filter_select('order', $orders, $order, array('label' => lang('Order'), 'icon' => 'sort-down', 'action' => 'visitor_vitals.php', 'keep' => $keep)),
        ),
    ), array('class' => 'my-5')) . '
    ' . pg_visitor_speed_section($window, $device, $order) . '
</main>
' . output_footer();
