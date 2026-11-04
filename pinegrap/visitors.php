<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Visitors overview: one screen with a period bar and the sections
 * (alerts, traffic, conversions, sources, content, site search, Google
 * search, pages joined with Google, speed, AI visibility, places and
 * devices). The period comes from the query string only
 * (includes/fn/visitor_periods.php); each section is a file under
 * includes/visitors/ taken in by pg_visitors_render_sections()
 * (includes/visitors/shell.php). The Visitors menu entry opens it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_visitors_access($user);

define('PG_VISITORS_SCREEN', true);

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');

// The calendar fields send the site's date order; the period reads Y-m-d.
$request = $_GET;

foreach (array('from', 'to') as $name) {
    if (isset($request[$name]) && is_string($request[$name]) && ($request[$name] !== '')) {
        $request[$name] = prepare_form_data_for_input(trim($request[$name]), 'date');
    }
}

$period = pg_visitor_period($request);

$sections = pg_visitors_render_sections($period, $user);

if ($sections === '') {
    $sections = '<p class="pg-visitors-coming">' . h(lang('The overview\'s sections are on their way; until then the saved reports and the screens in the Visitors menu show the figures.')) . '</p>';
}

$base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;

echo pg_page_shell(array(
    'title'               => lang('Visitors'),
    'extra classes'       => 'page',
    'icon'                => 'statistics',
    'heading'             => lang('Visitors'),
    'heading_description' => lang('How the site is doing: visits, where they come from, what they read and what they did, against the period before.'),
    'head'                => '<link rel="stylesheet" href="' . h(pg_visitors_style_url()) . '">',
)) . '
<main id="content" class="container-fluid pg-visitors">
    ' . pg_screen_toolbar(array(
        'secondary' => array(
            array('label' => lang('Saved reports'), 'url' => $base . '/view_visitor_reports.php', 'icon' => 'bi-clipboard2-data'),
        ),
        'grow'      => pg_visitors_period_text($period),
        'search'    => pg_visitors_period_bar($period),
    ), array('class' => 'my-5', 'label' => lang('Period'))) . '
    ' . $sections . '
</main>
' . pg_fields_script() . '
' . ((strpos($sections, 'data-pg-chart') !== false) ? '<script src="' . h($base . '/assets/lib/chartjs/chart.umd.min.js') . '" defer></script>' : '') . '
' . pg_js_bundle_tags('assets/js/pg_visitors.min.js') . '
' . output_footer();
