<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Commerce - the One-Stop Shop Report: a quarter's or a month's sales to EU
 * buyers by member state and VAT rate, goods and services apart, in euro at
 * the period's last day, with a CSV file. Worked out in
 * includes/fn/tax_oss_report.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('oss_report');

// The period comes from the address; a value sent as an array counts as not
// given.
$range = (($_GET['range'] ?? '') === 'month') ? 'month' : 'quarter';
$period = pg_oss_period($range, is_string($_GET['period'] ?? null) ? $_GET['period'] : '', date('Y-m-d'));

if ($period['error'] !== '') {
    $liveform->add_warning(h(lang('The period could not be read; the current one is shown.')));
}

$report = pg_oss_report_read($period['from'], $period['to']);
$euro = pg_oss_euro_rate($period['to']);
$base = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
// Without a euro rate the figures stay in the base currency, and the screen
// says so.
$currency = ($euro !== false) ? 'EUR' : $base;
$rate = ($euro !== false) ? $euro['rate'] : 1.0;
$stored = pg_oss_report_rows($report['stored'], $rate);
$apart = pg_oss_report_rows($report['apart'], $rate);
$names = pg_report_view_country_names();
$supplies = array('goods' => lang('Goods'), 'services' => lang('Services'));

// ------------------------------------------------------------------ export
if (($_GET['export'] ?? '') === 'csv') {
    $labels = array(
        'part' => lang('Member state from'),
        'country' => lang('Member state'),
        'supply' => lang('Supply'),
        'rate' => lang('Rate'),
        'taxable' => lang(array('string' => 'Taxable amount ({var:1})', 'vars' => $currency)),
        'tax' => lang(array('string' => 'VAT ({var:1})', 'vars' => $currency)),
        'lines' => lang('Order lines'),
        'goods' => $supplies['goods'],
        'services' => $supplies['services'],
        'stored_part' => lang('Stored with the order'),
        'apart_part' => lang('Taken from the addresses'),
    );

    header('Cache-Control: private, no-store');
    pg_csv_download_start('oss_' . $period['value'] . '.csv');

    $out = fopen('php://output', 'w');
    foreach (pg_oss_report_csv_rows($stored, $apart, $labels) as $row) {
        fputcsv($out, $row, ',', '"', '');
    }
    fclose($out);
    exit();
}

// ------------------------------------------------------------------ display
$money = function ($cents) use ($currency) {
    $cents = (int) $cents;

    return '<span class="text-nowrap">' . h(number_format($cents / 100, 2) . ' ' . $currency) . '</span>';
};

// One part's table: member state, supply, rate, lines, taxable amount, VAT,
// and the part's total.
$table = function ($figures, $id) use ($money, $names, $supplies) {
    $rows = '';
    $previous = '';

    foreach ($figures['rows'] as $row) {
        $name = isset($names[$row['country']]) ? $names[$row['country']] : $row['country'];
        $rows .= '
            <tr' . ((($previous !== '') && ($previous !== $row['country'])) ? ' class="border-top"' : '') . '>
                <th scope="row" class="fw-normal">' . (($previous !== $row['country']) ? h($name) . ' <span class="small fg-secondary font-monospace">' . h($row['country']) . '</span>' : '<span class="visually-hidden">' . h($name) . '</span>') . '</th>
                <td>' . h($supplies[$row['supply']]) . '</td>
                <td class="text-end">' . h(pg_oss_rate_text($row['rate'])) . '%</td>
                <td class="text-end">' . (int) $row['lines'] . '</td>
                <td class="text-end">' . $money($row['taxable']) . '</td>
                <td class="text-end fw-semibold">' . $money($row['tax']) . '</td>
            </tr>';
        $previous = $row['country'];
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="6" class="text-center fg-secondary py-7">' . lang('No sales to EU buyers in this period.') . '</td></tr>';
    } else {
        $rows .= '
            <tr class="pg-report-total">
                <th scope="row">' . lang('Total') . '</th>
                <td></td>
                <td></td>
                <td></td>
                <td class="text-end">' . $money($figures['taxable']) . '</td>
                <td class="text-end">' . $money($figures['tax']) . '</td>
            </tr>';
    }

    return '
        <table class="pg-report-table" id="' . h($id) . '">
            <thead>
                <tr>
                    <th>' . lang('Member state') . '</th>
                    <th>' . lang('Supply') . '</th>
                    <th class="text-end">' . lang('Rate') . '</th>
                    <th class="text-end">' . lang('Order lines') . '</th>
                    <th class="text-end">' . lang('Taxable Amount') . '</th>
                    <th class="text-end">' . lang('VAT') . '</th>
                </tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>';
};

$output_apart = '';
if ($apart['rows']) {
    $output_apart = '
            <section class="pg-card pg-report-frame">
                <div class="pg-report-frame-head">
                    <h3 class="pg-report-frame-title">' . lang('Lines without a stored member state') . '</h3>
                    <span class="pg-report-frame-note">' . lang('Their member state is taken from the recipient\'s address, else the billing address: orders placed before the state was stored, counter sales, renewals and marketplace orders.') . '</span>
                </div>
                <div class="pg-report-frame-body table-responsive">' . $table($apart, 'oss_apart') . '</div>
            </section>';
}

// The rate the figures were converted at.
if ($euro === false) {
    $output_rate = '<div class="alert theme-warning pg-report-note"><i class="bi bi-currency-exchange" aria-hidden="true"></i>' . h(lang(array(
        'string' => 'No euro rate is recorded on or before {var:1}, so the figures are in {var:2}.',
        'vars' => array(get_absolute_time(array('timestamp' => strtotime($period['to']), 'type' => 'date', 'format' => 'plain_text')), $base),
    ))) . '</div>';
} elseif ($base !== 'EUR') {
    $output_rate = '<p class="small fg-secondary">' . h(lang(array(
        'string' => 'Converted at 1 EUR = {var:1} {var:2}, the rate of {var:3}.',
        'vars' => array(rtrim(rtrim(number_format($euro['rate'], 6, '.', ''), '0'), '.'), $base, get_absolute_time(array('timestamp' => strtotime($euro['rate_date']), 'type' => 'date', 'format' => 'plain_text'))),
    ))) . '</p>';
} else {
    $output_rate = '';
}

// The period picker.
$range_options = '';
foreach (array('quarter' => lang('Quarter'), 'month' => lang('Month')) as $key => $label) {
    $range_options .= '<option value="' . $key . '"' . (($range === $key) ? ' selected' : '') . '>' . h($label) . '</option>';
}

$period_options = '';
foreach (pg_oss_period_values($range, date('Y-m-d'), ($range === 'month') ? 24 : 12) as $value) {
    $label = ($range === 'month')
        ? get_month_name_from_number(substr($value, 5, 2)) . ' ' . substr($value, 0, 4)
        : lang(array('string' => 'Q{var:1} {var:2}', 'vars' => array(substr($value, 6, 1), substr($value, 0, 4))));
    $period_options .= '<option value="' . h($value) . '"' . (($value === $period['value']) ? ' selected' : '') . '>' . h($label) . '</option>';
}

$export_url = 'oss_report.php?' . h(http_build_query(array('range' => $range, 'period' => $period['value'], 'export' => 'csv')));

echo
pg_page_shell([
    'title' => lang('One-Stop Shop Report'),
    'extra classes' => 'oss_report pg-measure-report',
    'icon' => 'orders',
    'heading' => lang('One-Stop Shop Report'),
    'heading_description' => h(lang('Your sales to buyers in the EU by member state and VAT rate, for the one-stop-shop (OSS) return.')),
    'cancel' => false,
    'help' => 'commerce-cont#oss-report',
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form method="get" action="oss_report.php" class="pg-report-tools" id="oss_period">
                <span class="pg-report-field">
                    <label class="pg-report-label" for="range">' . lang('Period') . '</label>
                    <select class="form-control" id="range" name="range">' . $range_options . '</select>
                </span>
                <span class="pg-report-field">
                    <label class="visually-hidden" for="period">' . lang('Period') . '</label>
                    <select class="form-control" id="period" name="period">' . $period_options . '</select>
                </span>
                <button type="submit" class="pg-button pg-button-quiet"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Show') . '</button>
                <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . pg_report_view_range(get_absolute_time(array('timestamp' => strtotime($period['from']), 'type' => 'date')), get_absolute_time(array('timestamp' => strtotime($period['to']), 'type' => 'date'))) . '</span>
                <a class="pg-button pg-button-quiet ms-auto" href="' . $export_url . '"><i class="bi bi-filetype-csv" aria-hidden="true"></i>' . lang('CSV') . '</a>
            </form>

            ' . $output_rate . '

            <section class="pg-card pg-report-frame">
                <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('By member state and rate') . '</h3></div>
                <div class="pg-report-frame-body table-responsive">' . $table($stored, 'oss_stored') . '</div>
            </section>
            ' . $output_apart . '

            <p class="small fg-secondary my-5">' . lang('A summary of completed orders by the day they were placed, not a filed return: check it before you file. The amounts are the order lines after the order discount, without shipping; exempt orders and refunds are not in them, and a correction goes into a later return. Sales in your own country belong in your national return.') . '</p>
        </div>
    </div>
</main>
<script>
(function () {
    var form = document.getElementById("oss_period");
    var range = document.getElementById("range");
    var period = document.getElementById("period");
    if (form && range && period) {
        // A new range lists its own periods: the current one is asked for.
        range.addEventListener("change", function () { period.disabled = true; form.submit(); });
        period.addEventListener("change", function () { form.submit(); });
    }
})();
</script>
' . output_footer();

$liveform->remove_form();
