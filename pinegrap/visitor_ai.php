<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * AI visibility: which AI assistant or AI search indexer fetched which page
 * and how often (ai_fetch_daily), what the site tells each of them, the
 * visits an assistant's answer sent and what those visitors did, and the
 * sitemap pages none of them fetched. The section is drawn by
 * pg_visitor_ai_section() (includes/fn/visitor_insights.php). Opened from
 * the Visitors menu; the gate is the visitor reports' one.
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

$shell = array(
    'title'               => lang('AI Visibility'),
    'extra classes'       => 'page',
    'icon'                => 'statistics',
    'heading'             => lang('AI Visibility'),
    'heading_description' => lang('Which AI assistants and AI search indexers read the site\'s pages, the pages they read most, the visits their answers sent and the sitemap pages none of them read. Fetches are counted by day for bots the firewall verified; days are kept for 400 days.'),
    'breadcrumb'          => array(
        array('label' => lang('Visitors'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_reports.php'),
        array('label' => lang('AI Visibility')),
    ),
);

if (!pg_ai_visibility_ready()) {
    echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'grow' => '<span class="fg-warning"><i class="bi bi-exclamation-triangle me-5" aria-hidden="true"></i>' . h(lang('The AI fetch table does not exist yet. Please run the software upgrade to create it.')) . '</span>',
    ), array('class' => 'my-5')) . '
</main>' . output_footer();
    exit();
}

echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'secondary' => array(
            array('label' => lang('Page Speed'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/visitor_vitals.php?p=' . rawurlencode($window['key']), 'icon' => 'bi-speedometer2'),
        ),
        'grow'      => '<span class="small fg-secondary">' . h(lang(array('string' => 'Since {var:1}', 'vars' => array(prepare_form_data_for_output($window['from'], 'date', false))))) . '</span>',
        'search'    => array(
            pg_filter_select('p', pg_visitor_insight_period_options(), $window['key'], array('label' => lang('Period'), 'icon' => 'calendar', 'action' => 'visitor_ai.php')),
        ),
    ), array('class' => 'my-5')) . '
    ' . pg_visitor_ai_section($window, $user) . '
</main>
' . output_footer();
