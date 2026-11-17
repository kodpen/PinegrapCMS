<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customers report: repeat purchase, value per customer, first-time against
 * returning customers by month, cohorts, the groups by fixed bounds and the
 * top customers. Everything is read from the summary the general job
 * rebuilds (includes/fn/customer_values.php); this screen sums no order.
 * Names are read from the orders when shown, and only for a reader who may
 * read contacts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

validate_ecommerce_report_access();

if (!ECOMMERCE) {
    output_error(lang('Commerce is not enabled on this site.'));
}

$period = pg_customer_value_period_pick(
    isset($_GET['period']) ? $_GET['period'] : null,
    $_SESSION['software']['ecommerce']['view_customer_report']['period'] ?? null
);
$_SESSION['software']['ecommerce']['view_customer_report']['period'] = $period;

$summary = pg_customer_value_summary();
$symbol = BASE_CURRENCY_SYMBOL;
$may_see_names = (bool) validate_contacts_access($user, true);

$output_periods = '';

foreach (pg_customer_value_periods() as $key => $label) {
    // Numeric keys ('12') are integers in a PHP array.
    $current = ((string) $key === $period);
    $output_periods .= '<a class="pg-seg-item' . ($current ? ' is-active" aria-current="true"' : '"') . ' href="view_customer_report.php?period=' . h($key) . '">' . h($label) . '</a>';
}

$output_notices = '';

// Until the first rebuild has finished the screen says how far it is.
if ($summary === null) {
    list(, $pass) = pg_customer_value_pass_read();

    if ((int) $pass['started'] > 0) {
        $output_notices .= '<div class="alert theme-info">' . h(lang(array('string' => 'The figures are being worked out from your orders: {var:1} of {var:2} orders read. They appear here when every order has been read.', 'vars' => array(pg_format_number((int) $pass['done'], 0), pg_format_number((int) $pass['total'], 0))))) . '</div>';
    } else {
        $output_notices .= '<div class="alert theme-info">' . h(lang('The figures are worked out from your orders by the scheduled jobs. They appear here after the next run.')) . '</div>';
    }
}

$output_as_of = '';
$output_sections = '';
$report_view = null;

if ($summary !== null) {
    $output_as_of = h(lang(array('string' => 'As of {var:1}', 'vars' => strip_tags(get_absolute_time(array('timestamp' => (int) $summary['at']))))));

    if (pg_customer_value_too_little($summary)) {
        $output_notices .= '<div class="alert theme-warning">' . h(lang('There are too few customers yet for these figures to say much. They fill in as orders come in.')) . '</div>';
    }

    $tile = isset($summary['tiles'][$period]) ? $summary['tiles'][$period] : array(0, 0, 0, 0, 0.0);
    $months = pg_customer_value_period_months($summary, $period);

    $report_view = array(
        'figures' => pg_customer_value_figures($tile, $symbol),
        'chart' => pg_customer_value_month_chart($months, $symbol),
        'strings' => pg_report_view_strings(),
    );

    // By month: first-time against returning customers.
    $rows = '';
    $foot = array(0, 0, 0, 0);

    foreach (array_reverse($months, true) as $key => $row) {
        $rows .= '<tr>
            <th scope="row" class="fw-normal">' . h(pg_customer_value_month_name($key)) . '</th>
            <td class="text-end">' . pg_format_number($row[0], 0) . '</td>
            <td class="text-end">' . h(pg_report_view_money($row[1], $symbol)) . '</td>
            <td class="text-end">' . pg_format_number($row[2], 0) . '</td>
            <td class="text-end">' . h(pg_report_view_money($row[3], $symbol)) . '</td>
            <td class="text-end">' . h(pg_report_view_money($row[1] + $row[3], $symbol)) . '</td>
        </tr>';

        foreach ($foot as $i => $sum) {
            $foot[$i] = $sum + $row[$i];
        }
    }

    $output_sections .= '
        <section class="pg-card pg-report-frame" aria-labelledby="pg_customer_months_title">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_customer_months_title">' . h(lang('By month')) . '</h3><span class="pg-report-frame-note">' . h(lang('A first-time order is a customer\'s first order ever; every later order is a returning one.')) . '</span></div>
            <div class="pg-report-frame-body">
                <table class="pg-report-table">
                    <thead><tr>
                        <th>' . h(lang('Month')) . '</th>
                        <th class="text-end">' . h(lang('First-time orders')) . '</th>
                        <th class="text-end">' . h(lang('First-time value')) . '</th>
                        <th class="text-end">' . h(lang('Returning orders')) . '</th>
                        <th class="text-end">' . h(lang('Returning value')) . '</th>
                        <th class="text-end">' . h(lang('Total')) . '</th>
                    </tr></thead>
                    <tbody>' . $rows . '</tbody>
                    <tfoot><tr class="pg-report-total">
                        <th scope="row">' . h(lang('Total')) . '</th>
                        <td class="text-end">' . pg_format_number($foot[0], 0) . '</td>
                        <td class="text-end">' . h(pg_report_view_money($foot[1], $symbol)) . '</td>
                        <td class="text-end">' . pg_format_number($foot[2], 0) . '</td>
                        <td class="text-end">' . h(pg_report_view_money($foot[3], $symbol)) . '</td>
                        <td class="text-end">' . h(pg_report_view_money($foot[1] + $foot[3], $symbol)) . '</td>
                    </tr></tfoot>
                </table>
            </div>
        </section>';

    // Cohorts: of the customers who first ordered in a month, the share who
    // ordered again in each of the twelve months after it.
    $heads = '';

    for ($k = 1; $k <= 12; $k++) {
        $heads .= '<th class="text-end">' . $k . '</th>';
    }

    $rows = '';

    foreach (pg_customer_value_cohort_rows($summary, 12) as $row) {
        $cells = '';

        foreach ($row['shares'] as $share) {
            $cells .= '<td class="text-end">' . (($share === null) ? '' : h($share)) . '</td>';
        }

        $rows .= '<tr><th scope="row" class="fw-normal">' . h(pg_customer_value_month_name($row['month'])) . '</th><td class="text-end">' . pg_format_number($row['size'], 0) . '</td>' . $cells . '</tr>';
    }

    $output_sections .= '
        <section class="pg-card pg-report-frame" aria-labelledby="pg_customer_cohorts_title">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_customer_cohorts_title">' . h(lang('Cohorts')) . '</h3><span class="pg-report-frame-note">' . h(lang('The share of each month\'s new customers who ordered again, 1 to 12 months after their first order.')) . '</span></div>
            <div class="pg-report-frame-body">
                <table class="pg-report-table">
                    <thead><tr><th>' . h(lang('First order')) . '</th><th class="text-end">' . h(lang('Customers')) . '</th>' . $heads . '</tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </section>';

    // Groups by fixed bounds: the last order's age against the number of
    // orders, all time.
    $ages = array(lang('Within 30 days'), lang('31 to 90 days'), lang('91 to 180 days'), lang('181 to 365 days'), lang('More than 365 days'));
    $group_names = pg_customer_value_group_names();
    $band_labels = array(lang('1 order'), lang('2 to 3 orders'), lang('4 or more orders'));
    $rows = '';

    foreach ($ages as $age => $label) {
        $cells = '';

        for ($band = 0; $band <= 2; $band++) {
            $cell = isset($summary['cells'][$age][$band]) ? $summary['cells'][$age][$band] : array(0, 0);
            $cells .= '<td class="text-end" data-label="' . h($band_labels[$band]) . '"><span class="d-block">' . h(lang(array('string' => '{var:1} customer{suffix:1}', 'vars' => pg_format_number((int) $cell[0], 0), 'suffix' => ((int) $cell[0] == 1) ? '' : 's'))) . '</span>'
                . '<small class="fg-secondary">' . h(pg_report_view_money((int) $cell[1], $symbol)) . ' · ' . h($group_names[pg_customer_value_cell_group($age, $band)]) . '</small></td>';
        }

        $rows .= '<tr><th scope="row" class="fw-normal">' . h($label) . '</th>' . $cells . '</tr>';
    }

    $legend = array();

    foreach (pg_customer_value_group_bounds() as $key => $bounds) {
        $legend[] = '<span><strong>' . h($group_names[$key]) . '</strong>: ' . h($bounds) . '</span>';
    }

    $output_sections .= '
        <section class="pg-card pg-report-frame" aria-labelledby="pg_customer_groups_title">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_customer_groups_title">' . h(lang('Customer groups')) . '</h3><span class="pg-report-frame-note">' . h(lang('Every customer of all time, by the age of their last order and their number of orders. The bounds are fixed: 30, 90, 180 and 365 days; 1, 2 to 3, and 4 or more orders.')) . '</span></div>
            <div class="pg-report-frame-body">
                <table class="pg-report-table pg-customer-groups">
                    <thead><tr><th>' . h(lang('Last order')) . '</th><th class="text-end">' . h($band_labels[0]) . '</th><th class="text-end">' . h($band_labels[1]) . '</th><th class="text-end">' . h($band_labels[2]) . '</th></tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
                <p class="small fg-secondary m-3 pg-customer-group-terms">' . implode('<span class="pg-customer-group-sep"> · </span>', $legend) . '</p>
            </div>
        </section>';

    // Top customers: names are read from their last order, and only for a
    // reader who may read contacts.
    $names = array();

    if ($may_see_names) {
        $names = pg_customer_value_order_names(array_merge(
            array_column((array) $summary['top']['all'], 0),
            array_column((array) $summary['top']['year'], 0)
        ));
    }

    $can_open_orders = $may_see_names && defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE;

    foreach (array('all' => lang('Top customers of all time'), 'year' => lang('Top customers of the last 12 months')) as $key => $title) {
        $rows = '';

        foreach (pg_customer_value_top_rows($summary['top'][$key], $names, $may_see_names) as $row) {
            $name = ($row['name'] !== '') ? h($row['name']) : '<span class="fg-secondary">' . h(lang('Name not shown')) . '</span>';

            if ($may_see_names && $can_open_orders && ($row['order_id'] > 0)) {
                $name = '<a href="' . h('view_order.php?id=' . $row['order_id']) . '">' . $name . '</a>';
            }

            $rows .= '<tr>
                <td class="text-end">' . (int) $row['rank'] . '</td>
                ' . ($may_see_names ? '<th scope="row" class="fw-normal">' . $name . '</th>' : '') . '
                <td class="text-end">' . pg_format_number($row['orders'], 0) . '</td>
                <td class="text-end">' . h(pg_report_view_money($row['value'], $symbol)) . '</td>
            </tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="' . ($may_see_names ? 4 : 3) . '" class="fg-secondary">' . h(lang('No customer yet.')) . '</td></tr>';
        }

        $output_sections .= '
            <section class="pg-card pg-report-frame" aria-labelledby="pg_customer_top_' . $key . '">
                <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_customer_top_' . $key . '">' . h($title) . '</h3>'
                    . (!$may_see_names ? '<span class="pg-report-frame-note">' . h(lang('Names are shown to users who may manage contacts.')) . '</span>' : '') . '</div>
                <div class="pg-report-frame-body">
                    <table class="pg-report-table">
                        <thead><tr><th class="text-end">#</th>' . ($may_see_names ? '<th>' . h(lang('Customer')) . '</th>' : '') . '<th class="text-end">' . h(lang('Orders')) . '</th><th class="text-end">' . h(lang('Value')) . '</th></tr></thead>
                        <tbody>' . $rows . '</tbody>
                    </table>
                </div>
            </section>';
    }

    // What the figures leave out.
    $left_out = array();

    foreach ((array) $summary['apart'] as $unit => $row) {
        $left_out[] = lang(array('string' => '{var:1} marketplace order{suffix:1} in {var:2}', 'vars' => array(pg_format_number((int) $row[0], 0), $unit), 'suffix' => ((int) $row[0] == 1) ? '' : 's'));
    }

    if ((int) $summary['unplaced'] > 0) {
        $left_out[] = lang(array('string' => '{var:1} order{suffix:1} with no e-mail and no contact', 'vars' => pg_format_number((int) $summary['unplaced'], 0), 'suffix' => ((int) $summary['unplaced'] == 1) ? '' : 's'));
    }

    if (!empty($left_out)) {
        $output_sections .= '<p class="fg-secondary small">' . h(lang('Not counted in these figures:')) . ' ' . h(implode('; ', $left_out)) . '.</p>';
    }

    $output_sections .= '<p class="fg-secondary small">' . h(lang('Completed and exported orders count, less what was refunded; cancelled orders and orders refunded in full do not. A customer is a billing e-mail address. The figures are worked out again every day.')) . '</p>';
}

print
    pg_page_shell(
        array(
            'title' => lang('Customers Report'),
            'extra classes' => 'store pg-measure-report',
            'icon' => 'store',
            'heading' => lang('Customers Report'),
            'heading_description' => lang('See how many customers order again, what a customer is worth and which customers you are losing.'),
            'help' => 'commerce-cont#customers-report',
            'cancel' => false
        )
    ) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'search' => (($summary !== null) ? '<div class="pg-seg pg-customer-periods" role="group" aria-label="' . h(lang('Period')) . '">' . $output_periods . '</div>' : '')
            . (($output_as_of !== '') ? '<span class="pg-report-range"><i class="bi bi-clock-history" aria-hidden="true"></i>' . $output_as_of . '</span>' : ''),
    ), array('class' => 'pg-report-tools')) . '
    <div class="row">
        <div class="col-12">
            ' . $output_notices . '
            ' . (($report_view !== null) ? '<section class="pg-report-view" id="pg_report_view" aria-label="' . h(lang('Summary')) . '">
                <div class="pg-report-view-figures"></div>
                <div class="pg-report-view-chart"></div>
                <script type="application/json" id="pg_report_view_data">' . (string) json_encode($report_view, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>
            </section>
            ' . pg_js_bundle_tags('assets/js/pg_report_view.min.js') : '') . '
            ' . $output_sections . '
        </div>
    </div>
</main>
' .
output_footer();
