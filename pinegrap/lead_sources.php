<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Lead sources: the contacts that became a lead (a first form or booking)
 * or a customer (a first order or paid invoice) in a month or the last 12
 * months, counted by the channel, campaign or landing page of their first
 * or last visit (includes/fn/lead_touch.php). Only visits of browsers that
 * accepted marketing cookies are tied to a contact, so only those count.
 * Excel gives the table as shown. Nothing is changed here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_visitors_access($user);

$now = time();
$month = pg_delivery_scorecard_month($_GET['month'] ?? '', $now);
$year = (($_GET['view'] ?? '') === 'year');
$touch = pg_lead_sources_choice($_GET['touch'] ?? '', array('first', 'last'));
$by = pg_lead_sources_choice($_GET['by'] ?? '', array('channel', 'campaign', 'landing_page'));
$revenue = (ECOMMERCE === true) && defined('USER_MANAGE_ECOMMERCE_REPORTS') && USER_MANAGE_ECOMMERCE_REPORTS;
$symbol = defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '';

$months = pg_delivery_scorecard_months($month);
$bounds = array(pg_delivery_scorecard_bounds($year ? $months[0] : $month)[0], pg_delivery_scorecard_bounds($month)[1]);
$groups = pg_lead_sources_group(pg_lead_sources_read($bounds[0], $bounds[1], $touch), $by);
$totals = pg_lead_sources_totals($groups);

$by_labels = array('channel' => lang('Channel'), 'campaign' => lang('Campaign'), 'landing_page' => lang('Landing page'));
$by_titles = array('channel' => lang('By channel'), 'campaign' => lang('By campaign'), 'landing_page' => lang('By landing page'));
$touch_labels = array('first' => lang('First touch'), 'last' => lang('Last touch'));
$period = $year
    ? pg_customer_value_month_name($months[0]) . ' – ' . pg_customer_value_month_name($month)
    : pg_customer_value_month_name($month);

// The address of this screen with some of its choices changed.
$link = function ($changes) use ($month, $year, $touch, $by) {
    $query = array_merge(array('month' => $month, 'view' => $year ? 'year' : '', 'touch' => $touch, 'by' => $by), $changes);

    return 'lead_sources.php?' . http_build_query(array_filter($query, 'strlen'));
};

// ------------------------------------------------------------------ export
if (($_GET['export'] ?? '') === 'xlsx') {
    $columns = array($by_labels[$by], lang('Leads'), lang('Customers'), lang('Became customers (%)'));
    $rows = array();

    if ($revenue) {
        $columns[] = lang('Revenue');
    }

    foreach ($groups as $group) {
        $line = array(pg_lead_sources_label($by, $group['key']), $group['leads'], $group['customers'], $group['share']);

        if ($revenue) {
            $line[] = array('money' => $group['revenue'] / 100);
        }

        $rows[] = $line;
    }

    $path = tempnam(sys_get_temp_dir(), 'pgl');

    if (($path === false) || !pg_xlsx_write_plain($columns, $rows, $path, lang('Lead sources'))) {
        if ($path !== false) {
            @unlink($path);
        }
        output_error(lang('The workbook could not be written.'));
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="lead_sources_' . $touch . '_' . $by . '_' . ($year ? $months[0] . '_' : '') . $month . '.xlsx"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit();
}

// ------------------------------------------------------------------ display
$money = function ($minor) use ($symbol) {
    return pg_money_text($minor / 100, $symbol);
};

$figures = array(
    pg_report_view_figure(lang('Leads'), pg_format_number($totals['leads'], 0), 'bi-person-plus', 'blue'),
    pg_report_view_figure(lang('Customers'), pg_format_number($totals['customers'], 0), 'bi-person-check', 'teal',
        ($totals['share'] !== null) ? lang(array('string' => '{var:1}% of leads became customers', 'vars' => array($totals['share']))) : ''),
);

if ($revenue) {
    $figures[] = pg_report_view_figure(lang('Revenue'), $money($totals['revenue']), 'bi-cash-stack', 'violet');
}

$output_rows = '';

foreach ($groups as $group) {
    $output_rows .= '<tr><th scope="row" class="text-break">' . h(pg_lead_sources_label($by, $group['key'])) . '</th>'
        . '<td class="text-end">' . h(pg_format_number($group['leads'], 0)) . '</td>'
        . '<td class="text-end">' . h(pg_format_number($group['customers'], 0)) . '</td>'
        . '<td class="text-end">' . (($group['share'] !== null) ? h(lang(array('string' => '{var:1}%', 'vars' => array($group['share'])))) : '<span class="fg-secondary">–</span>') . '</td>'
        . ($revenue ? '<td class="text-end">' . h($money($group['revenue'])) . '</td>' : '')
        . '</tr>';
}

$columns = $revenue ? 5 : 4;

if ($output_rows === '') {
    $output_rows = '<tr><td colspan="' . $columns . '" class="fg-secondary">' . h(lang('No contact became a lead or a customer in this period.')) . '</td></tr>';
}

// A segmented choice of links, the active one marked.
$seg = function ($label, $choices, $active, $param) use ($link) {
    $html = '<span class="pg-seg" role="group" aria-label="' . h($label) . '">';

    foreach ($choices as $key => $text) {
        $on = ($key === $active);
        $html .= '<a class="pg-seg-item' . ($on ? ' is-active' : '') . '" href="' . h($link(array($param => $key))) . '"' . ($on ? ' aria-current="page"' : '') . '>' . h($text) . '</a>';
    }

    return $html . '</span>';
};

$output_tools = '
    <div class="pg-seg" role="group" aria-label="' . h(lang('Month')) . '">
        <a class="pg-seg-item pg-report-step" href="' . h($link(array('month' => pg_delivery_scorecard_month_step($month, -1)))) . '" title="' . h(lang('Previous')) . '" aria-label="' . h(lang('Previous')) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
        <a class="pg-seg-item' . (!$year ? ' is-active' : '') . '" href="' . h($link(array('view' => ''))) . '"' . (!$year ? ' aria-current="page"' : '') . '>' . h(pg_customer_value_month_name($month)) . '</a>
        <a class="pg-seg-item pg-report-step" href="' . h($link(array('month' => pg_delivery_scorecard_month_step($month, 1)))) . '" title="' . h(lang('Next')) . '" aria-label="' . h(lang('Next')) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
    </div>
    <div class="pg-seg" role="group" aria-label="' . h(lang('Period')) . '">
        <a class="pg-seg-item' . ($year ? ' is-active' : '') . '" href="' . h($link(array('view' => 'year'))) . '"' . ($year ? ' aria-current="page"' : '') . '>' . h(lang('Last 12 months')) . '</a>
    </div>
    <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . h($period) . '</span>
    ' . $seg(lang('Touch'), $touch_labels, $touch, 'touch') . '
    <a class="pg-button pg-button-quiet ms-auto" href="' . h($link(array('export' => 'xlsx'))) . '"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>' . h(lang('Excel')) . '</a>';

print
    pg_page_shell(
        array(
            'title' => lang('Lead sources'),
            'extra classes' => 'pg-measure-report',
            'icon' => 'statistics',
            'heading' => lang('Lead sources'),
            'heading_description' => lang('The figures count only visitors who accepted marketing cookies.'),
            'cancel' => false
        )
    ) . '
<main id="content" class="container-fluid">
    <div class="pg-report-tools">' . $output_tools . '
    </div>
    <div class="row">
        <div class="col-12">
            <div class="mb-3">' . pg_report_figures_html($figures) . '</div>
            <div class="mb-3">' . $seg(lang('Group by'), $by_labels, $by, 'by') . '</div>
            <section class="pg-card pg-report-frame" aria-labelledby="pg_lead_sources_title">
                <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_lead_sources_title">' . h($by_titles[$by]) . '</h3><span class="pg-report-frame-note">' . h($period) . ' · ' . h($touch_labels[$touch]) . '</span></div>
                <div class="pg-report-frame-body">
                    <table class="pg-report-table">
                        <thead><tr><th>' . h($by_labels[$by]) . '</th><th class="text-end">' . h(lang('Leads')) . '</th><th class="text-end">' . h(lang('Customers')) . '</th><th class="text-end">' . h(lang('Became customers')) . '</th>' . ($revenue ? '<th class="text-end">' . h(lang('Revenue')) . '</th>' : '') . '</tr></thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </section>
            <p class="fg-secondary small">' . h(lang('A lead is a contact\'s first form or booking, a customer its first order or paid invoice. Became customers is the share of the leads that bought since.')) . ($revenue ? ' ' . h(lang('Revenue is the total of each new customer\'s first order.')) : '') . '</p>
        </div>
    </div>
</main>
' .
output_footer();
