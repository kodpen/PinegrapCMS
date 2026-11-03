<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site search report: what visitors searched for on the site over a period
 * (site_search_daily, includes/fn/site_search_stats.php), the terms that
 * found nothing, and how often a result was clicked. A term that found
 * nothing can be promoted: the Search Tuning card opens with a line for it
 * (includes/fn/site_search_tuning.php). Opened from the Visitors menu; the
 * gate is the visitor reports' one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_visitors_access($user);

$ready = pg_site_search_stats_ready();
$days = pg_site_search_period($_GET['days'] ?? 30);
$from = pg_site_search_period_start($days);

// The Search Tuning card is a site setting: managers and up.
$may_tune = ((int) $user['role'] < 3);

$shell = array(
    'title'               => lang('Site Search'),
    'extra classes'       => 'page',
    'icon'                => 'statistics',
    'heading'             => lang('Site Search'),
    'heading_description' => lang('What visitors searched for on the site, the searches that found nothing and how often a result was clicked. Each visit counts a term once a day; bots are not counted. Days are kept for 400 days.'),
    'breadcrumb'          => array(
        array('label' => lang('Visitors'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_visitor_reports.php'),
        array('label' => lang('Site Search')),
    ),
);

if (!$ready) {
    echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'grow' => '<span class="fg-warning"><i class="bi bi-exclamation-triangle me-5" aria-hidden="true"></i>' . h(lang('The site search statistics table does not exist yet. Please run the software upgrade to create it.')) . '</span>',
    ), array('class' => 'my-5')) . '
</main>' . output_footer();
    exit();
}

// Rows of a statement with the period's first day bound.
$read = function ($sql) use ($from) {
    $rows = array();
    $result = db_q($sql, array($from));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return $rows;
};

$summary = $read(pg_site_search_summary_sql());
$summary = $summary ? $summary[0] : array('searches' => 0, 'zero_results' => 0, 'clicks' => 0, 'terms' => 0);
$top = $read(pg_site_search_terms_sql('top', 100));
$zero = $read(pg_site_search_terms_sql('zero', 100));

$rate_text = function ($clicks, $searches) {
    $rate = pg_site_search_click_rate($clicks, $searches);

    return ($rate === null) ? '<span class="fg-secondary">-</span>' : h(pg_format_number($rate, 1)) . '%';
};

$promote_url = function ($term) {
    return OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/settings_features.php?search_promote=' . rawurlencode((string) $term) . '#pgset-search-tuning';
};

// ---- Tables ----------------------------------------------------------------

$top_rows = '';

foreach ($top as $row) {
    $searches = (int) $row['searches'];
    $top_rows .= '<tr>
            <td class="align-middle text-break">' . h($row['term']) . '</td>
            <td class="align-middle" data-order="' . $searches . '">' . pg_format_number($searches, 0) . '</td>
            <td class="align-middle" data-order="' . (int) $row['zero_results'] . '">' . pg_format_number((int) $row['zero_results'], 0) . '</td>
            <td class="align-middle" data-order="' . (int) $row['clicks'] . '">' . pg_format_number((int) $row['clicks'], 0) . '</td>
            <td class="align-middle text-nowrap">' . $rate_text($row['clicks'], $searches) . '</td>
        </tr>';
}

$zero_rows = '';

foreach ($zero as $row) {
    $action = $may_tune
        ? '<a class="m-1 btn-data-control btn btn-outline theme-primary border-2" href="' . h($promote_url($row['term'])) . '" title="' . h(lang('Promote a result')) . '" aria-label="' . h(lang('Promote a result')) . '"><i class="bi bi-pin-angle" aria-hidden="true"></i></a>'
        : '';
    $zero_rows .= '<tr>
            <td class="align-middle text-break">' . h($row['term']) . '</td>
            <td class="align-middle" data-order="' . (int) $row['zero_results'] . '">' . pg_format_number((int) $row['zero_results'], 0) . '</td>
            <td class="align-middle" data-order="' . (int) $row['searches'] . '">' . pg_format_number((int) $row['searches'], 0) . '</td>
            ' . ($may_tune ? '<td class="align-middle text-end">' . $action . '</td>' : '') . '
        </tr>';
}

$card = function ($title, $head, $rows, $empty) {
    return '<div class="card my-7">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h($title) . '</div>
            <div class="card-body p-0">'
        . (($rows === '')
            ? '<p class="fg-secondary m-7">' . h($empty) . '</p>'
            : '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>')
        . '</div>
        </div>';
};

$searches_total = (int) $summary['searches'];

$tiles = '';

foreach (array(
    array(lang('Searches'), pg_format_number($searches_total, 0)),
    array(lang('Terms'), pg_format_number((int) $summary['terms'], 0)),
    array(lang('No results'), pg_format_number((int) $summary['zero_results'], 0)),
    array(lang('Result clicks'), pg_format_number((int) $summary['clicks'], 0)),
    array(lang('Clicks per search'), strip_tags($rate_text($summary['clicks'], $searches_total))),
) as $tile) {
    $tiles .= '<div class="col-6 md:col">
            <div class="card h-100"><div class="card-body">
                <div class="small fg-secondary">' . h($tile[0]) . '</div>
                <div class="h3 mb-0">' . h($tile[1]) . '</div>
            </div></div>
        </div>';
}

$period_options = array();

foreach (pg_site_search_periods() as $period) {
    $period_options[(string) $period] = lang(array('string' => 'Last {var:1} days', 'vars' => array($period)));
}

echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'grow'   => '<span class="small fg-secondary">' . h(lang(array('string' => 'Since {var:1}', 'vars' => array(prepare_form_data_for_output($from, 'date', false))))) . '</span>',
        'search' => array(
            pg_filter_select('days', $period_options, (string) $days, array('label' => lang('Period'), 'icon' => 'calendar', 'action' => 'visitor_search.php')),
        ),
    ), array('class' => 'my-5')) . '
    <div class="row g-7">' . $tiles . '</div>
    <div class="row">
        <div class="col-12 xl:col-7">
            ' . $card(lang('Top Searches'),
                '<th>' . h(lang('Term')) . '</th><th>' . h(lang('Searches')) . '</th><th>' . h(lang('No results')) . '</th><th>' . h(lang('Result clicks')) . '</th><th>' . h(lang('Clicks per search')) . '</th>',
                $top_rows, lang('Nothing was searched for in this period.')) . '
        </div>
        <div class="col-12 xl:col-5">
            ' . $card(lang('Searches Without Results'),
                '<th>' . h(lang('Term')) . '</th><th>' . h(lang('No results')) . '</th><th>' . h(lang('Searches')) . '</th>' . ($may_tune ? '<th class="text-end">' . h(lang('Action')) . '</th>' : ''),
                $zero_rows, lang('Every search in this period found something.')) . '
        </div>
    </div>
</main>
' . output_footer();
