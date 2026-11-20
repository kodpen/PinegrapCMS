<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - profitability: each product's net profit by channel (the web store,
 * each marketplace account) over a period, after the product cost, payment
 * fees, marketplace deductions and shipping, from the order profit rows the
 * general job keeps; the Excel workbook of it; and the settings card for the
 * typed cost rates and the target margin. The figures are worked out in
 * includes/erp/profitability.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

// A sent form is refused here for the accountant's read-only right.
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/profitability.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_profitability');

$self_url = PATH . SOFTWARE_DIRECTORY . '/erp_profitability.php';
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$ready = erp_order_profit_ready();

// One value each; one sent as an array counts as not given. The rate rows
// are a list, read with their types checked (erp_profitability_settings_save()).
pg_request_single_values(array('erp_profitability_settings', 'target_margin', 'channel', 'export'));

// ------------------------------------------------------------------ settings
if ($_POST && ((string) ($_POST['erp_profitability_settings'] ?? '') === '1')) {
    validate_token_field();

    if ($ready) {
        $saved = erp_profitability_settings_save($_POST, (int) $user['id']);

        if ($saved['success']) {
            log_activity(lang('erp profitability settings were saved'), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The cost rates and the target margin were saved. The general job works out again the orders a changed rate applies to.'));
        } else {
            $liveform->mark_error($saved['field'], h($saved['error']));
        }
    }

    go($self_url . '#erp_profitability_settings');
}

if (!$ready) {
    $liveform->mark_error('', lang('Profitability comes with the software update; run the update to use it.'));
}

// The period comes from the address, as on the profit and loss screen; a
// value sent as an array counts as not given.
$input = array(
    'range' => is_string($_GET['range'] ?? null) ? $_GET['range'] : 'month',
    'month' => is_string($_GET['month'] ?? null) ? $_GET['month'] : date('Y-m'),
    'quarter' => is_string($_GET['quarter'] ?? null) ? $_GET['quarter'] : (date('Y') . '-Q' . (int) ceil(date('n') / 3)),
    'year' => is_string($_GET['year'] ?? null) ? $_GET['year'] : date('Y'),
    'from' => is_string($_GET['from'] ?? null) ? $_GET['from'] : '',
    'to' => is_string($_GET['to'] ?? null) ? $_GET['to'] : '',
);

$period = erp_profit_period($input);

if ($period['error'] !== '') {
    $liveform->add_warning(h($period['error']));
}

$channel = erp_profitability_channel_in($_GET['channel'] ?? '');
$accounts = $ready ? erp_profitability_accounts() : array();
$report = $ready ? erp_profitability_report($period['from'], $period['to'], $channel) : array(
    'groups' => array(), 'totals' => erp_profitability_totals(array()), 'orders' => 0, 'capped' => false,
    'products' => array(), 'uncomputed' => 0, 'from' => $period['from'], 'to' => $period['to'], 'channel' => $channel,
);
$target = $ready ? erp_profitability_target() : null;
$all = $report['totals']['all'];

// ------------------------------------------------------------------ export
if ($ready && (($_GET['export'] ?? '') === 'xlsx')) {
    $path = tempnam(sys_get_temp_dir(), 'pgp');

    if (($path === false) || !erp_profitability_workbook($report, $accounts, $path)) {
        if ($path !== false) {
            @unlink($path);
        }
        $liveform->mark_error('_error', lang('The workbook could not be written.'));
        go($self_url);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . erp_accountant_file_part(lang('Profitability')) . '_' . $period['from'] . '_' . $period['to'] . '.xlsx"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit();
}

// Each product's break-even price on its channel, from the last 90 days
// whatever the period chosen: shown only, no price changes from it.
$evens = $ready ? erp_profitability_break_evens(date('Y-m-d'), $target) : array();

// ------------------------------------------------------------------ display
$money = function ($kurus, $class = '') {
    $kurus = (int) $kurus;

    return '<span class="text-nowrap' . (($kurus < 0) ? ' fg-danger' : '') . ($class !== '' ? ' ' . $class : '') . '">' . h(erp_money_out($kurus)) . '</span>';
};
$margin = function ($value) use ($target) {
    if ($value === null) {
        return '<span class="fg-secondary">—</span>';
    }

    return erp_profitability_below_target($value, $target)
        ? '<span class="fg-emphasis-warning" title="' . h(lang('Below the target margin')) . '">' . h(erp_profit_margin_text($value)) . '</span>'
        : h(erp_profit_margin_text($value));
};
$costs_of = function ($figures) {
    return (int) $figures['cost_product'] + (int) $figures['cost_payment'] + (int) $figures['cost_marketplace']
        + (int) $figures['cost_shipping'] + (int) $figures['cost_other'];
};
$cost_cells = function ($figures) use ($money) {
    $html = '';

    foreach (array('cost_product', 'cost_payment', 'cost_marketplace', 'cost_shipping', 'cost_other') as $kind) {
        $html .= '<td class="text-end" data-sort="' . (int) $figures[$kind] . '">' . $money($figures[$kind]) . '</td>';
    }

    return $html;
};
$cost_heads = '
    <th class="text-end">' . lang('Product cost') . '</th>
    <th class="text-end">' . lang('Payment fees') . '</th>
    <th class="text-end">' . lang('Marketplace deductions') . '</th>
    <th class="text-end">' . lang('Shipping cost') . '</th>
    <th class="text-end">' . lang('Other costs') . '</th>';

// By channel.
$output_channels = '';

foreach ($report['totals']['channels'] as $key => $figures) {
    $output_channels .= '
        <tr>
            <td><a class="pg-link-body-emphasis" href="erp_profitability.php?' . h(http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to'], 'channel' => $key))) . '">' . h(erp_profitability_channel_label($key, $accounts)) . '</a></td>
            <td class="text-end" data-sort="' . h((string) $figures['units']) . '">' . h(erp_quantity_text($figures['units'])) . '</td>
            <td class="text-end" data-sort="' . (int) $figures['revenue'] . '">' . $money($figures['revenue']) . '</td>
            ' . $cost_cells($figures) . '
            <td class="text-end fw-semibold" data-sort="' . (int) $figures['net'] . '">' . $money($figures['net']) . '</td>
            <td class="text-end" data-sort="' . h((string) ($figures['margin'] ?? '')) . '">' . $margin($figures['margin']) . '</td>
        </tr>';
}

if ($output_channels === '') {
    $output_channels = '<tr data-pg-sort-fixed><td colspan="10" class="text-center fg-secondary py-7">' . lang('No order of this period has its profit worked out yet.') . '</td></tr>';
} else {
    $output_channels .= '
        <tr class="pg-report-total" data-pg-sort-fixed>
            <td>' . lang('Total') . '</td>
            <td class="text-end">' . h(erp_quantity_text($all['units'])) . '</td>
            <td class="text-end">' . $money($all['revenue']) . '</td>
            ' . $cost_cells($all) . '
            <td class="text-end">' . $money($all['net']) . '</td>
            <td class="text-end">' . $margin($all['margin']) . '</td>
        </tr>';
}

// A break-even cell: the price, the price at the target margin under it.
$even_cell = function ($even) use ($money, $target) {
    if (!is_array($even) || ($even['price'] === null)) {
        return '<td class="text-end fg-secondary" data-sort="">' . (is_array($even) ? h(lang('Out of reach')) : '—') . '</td>';
    }

    $under = '';

    if ($target !== null) {
        $under = '<div class="pg-text-meta fg-secondary">' . (($even['target_price'] === null)
            ? h(lang('Target margin out of reach'))
            : h(lang(array('string' => 'At the target margin: {var:1}', 'vars' => erp_money_out($even['target_price'], false))))) . '</div>';
    }

    return '<td class="text-end" data-sort="' . (int) $even['price'] . '">' . $money($even['price']) . $under . '</td>';
};

// By product and channel: the most profitable first, sortable.
$shown_groups = 1000;
$output_products = '';

foreach (array_slice($report['groups'], 0, $shown_groups) as $group) {
    $name = erp_profitability_product_name($group, $report['products']);
    $state = '';

    if ((int) $group['incomplete'] > 0) {
        $state = '<div class="pg-text-meta fg-emphasis-warning">' . h(lang(array('string' => '{var:1} order(s) with no product cost known', 'vars' => (int) $group['incomplete']))) . '</div>';
    } elseif ((int) $group['estimate'] > 0) {
        $state = '<div class="pg-text-meta fg-secondary">' . h(lang(array('string' => '{var:1} order(s) with an estimate', 'vars' => (int) $group['estimate']))) . '</div>';
    }

    $output_products .= '
        <tr>
            <td>' . h($name['title'])
                . ((($name['sku'] !== '') && ($name['sku'] !== $name['title'])) ? '<div class="pg-text-meta fg-secondary font-monospace">' . h($name['sku']) . '</div>' : '')
                . $state . '</td>
            <td data-sort="' . h(erp_profitability_channel_label($group['channel'], $accounts)) . '">' . h(erp_profitability_channel_label($group['channel'], $accounts)) . '</td>
            <td class="text-end" data-sort="' . (int) $group['orders'] . '">' . (int) $group['orders'] . '</td>
            <td class="text-end" data-sort="' . h((string) $group['units']) . '">' . h(erp_quantity_text($group['units'])) . '</td>
            <td class="text-end" data-sort="' . (int) $group['revenue'] . '">' . $money($group['revenue']) . '</td>
            ' . $cost_cells($group) . '
            <td class="text-end fw-semibold" data-sort="' . (int) $group['net'] . '">' . $money($group['net']) . '</td>
            <td class="text-end" data-sort="' . h((string) ($group['margin'] ?? '')) . '">' . $margin($group['margin']) . '</td>
            ' . $even_cell($evens[erp_profitability_key($group['product_id'], $group['channel'])] ?? null) . '
        </tr>';
}

if ($output_products === '') {
    $output_products = '<tr data-pg-sort-fixed><td colspan="13" class="text-center fg-secondary py-7">' . lang('No order of this period has its profit worked out yet.') . '</td></tr>';
}

// What the reader should know about the figures.
$notes = array();

if ((int) $report['uncomputed'] > 0) {
    $notes[] = h(lang(array(
        'string' => '{var:1} finished order(s) of this period are not worked out yet; the general job adds them within the hour.',
        'vars' => (int) $report['uncomputed'],
    )));
}

if ((int) $all['incomplete'] > 0) {
    $notes[] = h(lang('Some orders have a product with no cost known, so their profit is overstated.')) . ' <a href="erp_stock.php">' . lang('Stock and cost') . '</a>';
}

if ((int) $all['estimate'] > 0) {
    $notes[] = h(lang('Some costs are estimates: a typed rate, or a marketplace\'s listed commission until its deductions are read.'));
}

if ($report['capped']) {
    $notes[] = h(lang(array('string' => 'Only the first {var:1} orders of this period are counted; choose a shorter period.', 'vars' => ERP_PROFITABILITY_MAX_ORDERS)));
}

if (count($report['groups']) > $shown_groups) {
    $notes[] = h(lang(array('string' => 'The {var:1} most profitable rows are shown; the workbook has them all.', 'vars' => $shown_groups)));
}

$output_notes = empty($notes) ? '' : '<div class="alert theme-info small my-5"><ul class="mb-0 ps-5"><li>' . implode('</li><li>', $notes) . '</li></ul></div>';

// The summary figures.
$all_costs = $costs_of($all);
$output_figures = pg_report_figures_html(array(
    pg_report_view_figure(lang('Net sales'), erp_money_out((int) $all['revenue']), 'bi-bar-chart', 'teal', lang(array('string' => '{var:1} order(s)', 'vars' => (int) $report['orders']))),
    pg_report_view_figure(lang('Costs'), erp_money_out($all_costs), 'bi-receipt-cutoff', 'rose', lang('Product, fees, deductions and shipping')),
    pg_report_view_figure(lang('Net profit'), erp_money_out((int) $all['net']), 'bi-cash-stack', 'violet', '', ((int) $all['net'] < 0) ? 'bad' : '') + array('class' => ((int) $all['net'] < 0) ? 'fg-danger' : ''),
    pg_report_view_figure(lang('Profit margin'), ($all['margin'] === null) ? '—' : erp_profit_margin_text($all['margin']), 'bi-percent', 'blue',
        ($target === null) ? lang('No target margin set') : lang(array('string' => 'Target {var:1}', 'vars' => erp_profit_margin_text($target))),
        erp_profitability_below_target($all['margin'], $target) ? 'bad' : ''),
));

// The period picker and the channel.
$range = $period['range'];
$period_options = erp_profit_period_options($period);
$range_options = '';

foreach (array('month' => lang('Month'), 'quarter' => lang('Quarter'), 'year' => lang('Year'), 'dates' => lang('From - to')) as $key => $label) {
    $range_options .= '<option value="' . $key . '"' . (($range === $key) ? ' selected' : '') . '>' . h($label) . '</option>';
}

$channel_options = '<option value="">' . lang('All channels') . '</option><option value="web"' . (($channel === 'web') ? ' selected' : '') . '>' . h(erp_profitability_channel_label('web', $accounts)) . '</option>';

foreach ($accounts as $id => $name) {
    $key = 'marketplace:' . (int) $id;
    $channel_options .= '<option value="' . h($key) . '"' . (($channel === $key) ? ' selected' : '') . '>' . h($name) . '</option>';
}

$export_url = 'erp_profitability.php?' . http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to'], 'channel' => $channel, 'export' => 'xlsx'));

// The settings card: a rate per payment method, shipping method and
// marketplace account, used when the service gave no real figure, and the
// target margin.
$output_settings = '';

if ($ready) {
    $rate_rows = erp_profitability_rate_rows();
    $disabled = $readonly ? ' disabled' : '';
    $groups = array(
        'payment' => array(lang('Payment methods'), lang('On the order total, for the shop\'s own orders, when the payment service gave no fee.'), lang('No order has been paid yet.')),
        'shipping' => array(lang('Shipping methods'), lang('On the shipping charged, when no label price was read.'), lang('No shipping method is set up.')),
        'marketplace' => array(lang('Marketplace accounts'), lang('On each line, tax included, when the listing has no commission and no deduction was read.'), lang('No marketplace account is connected.')),
    );

    foreach ($groups as $kind => $texts) {
        $rows_html = '';

        foreach ($rate_rows[$kind] as $index => $row) {
            $id = 'rate_' . $kind . '_' . (int) $index;
            $rows_html .= '
                <tr>
                    <th scope="row" class="fw-normal text-wrap">' . h($row['label']) . '<input type="hidden" name="rate[' . $kind . '][' . (int) $index . '][key]" value="' . h($row['key']) . '" /></th>
                    <td><label class="visually-hidden" for="' . $id . '_percent">' . lang('Percent') . '</label><input type="text" inputmode="decimal" class="form-control text-end" id="' . $id . '_percent" name="rate[' . $kind . '][' . (int) $index . '][percent]" value="' . h($row['percent']) . '" maxlength="8"' . $disabled . ' /></td>
                    <td><label class="visually-hidden" for="' . $id . '_fixed">' . lang('Fixed') . '</label><input type="text" inputmode="decimal" class="form-control text-end" id="' . $id . '_fixed" name="rate[' . $kind . '][' . (int) $index . '][fixed]" value="' . h($row['fixed']) . '" maxlength="16"' . $disabled . ' /></td>
                </tr>';
        }

        if ($rows_html === '') {
            $rows_html = '<tr><td colspan="3" class="fg-secondary">' . h($texts[2]) . '</td></tr>';
        }

        $output_settings .= '
            <h4 class="pg-report-frame-title mt-5">' . h($texts[0]) . '</h4>
            <p class="pg-text-meta fg-secondary mb-3">' . h($texts[1]) . '</p>
            <table class="pg-report-table">
                <thead>
                    <tr>
                        <th>' . lang('Name') . '</th>
                        <th class="text-end">' . lang('Percent') . ' (%)</th>
                        <th class="text-end">' . h(lang('Fixed') . ' (' . erp_base_currency() . ')') . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows_html . '</tbody>
            </table>';
    }

    $target_text = ($target === null) ? '' : erp_decimal_text($target);

    $output_settings = '
        <section class="pg-card pg-report-frame mt-7 mb-0" id="erp_profitability_settings">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('Cost rates and target margin') . '</h3></div>
            <div class="pg-report-frame-body">
                <form method="post" action="' . h($self_url) . '">
                    ' . get_token_field() . '
                    <input type="hidden" name="erp_profitability_settings" value="1" />
                    <p class="pg-text-meta fg-secondary">' . lang('A rate is a percent plus a fixed amount, used only where the service gave no real figure; such a cost is marked as an estimate. Leave both empty for no rate.') . '</p>
                    ' . $output_settings . '
                    <h4 class="pg-report-frame-title mt-5"><label for="target_margin">' . lang('Target margin') . ' (%)</label></h4>
                    <p class="pg-text-meta fg-secondary mb-3">' . lang('Net profit as a percent of revenue without tax. Margins below it are marked; leave it empty for none.') . '</p>
                    <input type="text" inputmode="decimal" class="form-control" id="target_margin" name="target_margin" value="' . h($target_text) . '" maxlength="5" style="max-width:8rem"' . $disabled . ' />
                    ' . ($readonly ? '' : '<div class="mt-5"><button type="submit" class="pg-button pg-button-fill">' . lang('Save') . '</button></div>') . '
                </form>
            </div>
        </section>';
}

$output_toolbar = pg_screen_toolbar(array(
    'secondary' => array(
        $ready ? array('label' => lang('Excel'), 'url' => $export_url, 'icon' => 'bi-file-earmark-spreadsheet') : '',
        $ready ? array('label' => lang('Cost rates and target margin'), 'url' => '#erp_profitability_settings', 'icon' => 'bi-sliders') : '',
        array('label' => lang('Profit and loss'), 'url' => 'erp_profit.php', 'icon' => 'bi-graph-up'),
    ),
));

echo
pg_page_shell([
    'title' => lang('Profitability'),
    'extra classes' => 'erp erp_profitability pg-measure-report',
    'icon' => 'erp',
    'heading' => lang('Profitability'),
    'heading_description' => h(lang(array('string' => 'Each product\'s net profit by channel, after product cost, payment fees, marketplace deductions and shipping, in {var:1} and without tax.', 'vars' => erp_base_currency()))),
    'cancel' => false,
]) . '
<main id="content" class="container-fluid">
    ' . $output_toolbar . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form method="get" action="erp_profitability.php" class="pg-report-tools" id="erp_profitability_period">
                <span class="pg-report-field">
                    <label class="pg-report-label" for="range">' . lang('Period') . '</label>
                    <select class="form-control" id="range" name="range">' . $range_options . '</select>
                </span>
                <span class="pg-report-field" data-range="month"' . (($range === 'month') ? '' : ' hidden') . '>
                    <label class="visually-hidden" for="month">' . lang('Month') . '</label>
                    <select class="form-control" id="month" name="month">' . $period_options['month'] . '</select>
                </span>
                <span class="pg-report-field" data-range="quarter"' . (($range === 'quarter') ? '' : ' hidden') . '>
                    <label class="visually-hidden" for="quarter">' . lang('Quarter') . '</label>
                    <select class="form-control" id="quarter" name="quarter">' . $period_options['quarter'] . '</select>
                </span>
                <span class="pg-report-field" data-range="year"' . (($range === 'year') ? '' : ' hidden') . '>
                    <label class="visually-hidden" for="year">' . lang('Year') . '</label>
                    <select class="form-control" id="year" name="year">' . $period_options['year'] . '</select>
                </span>
                <span class="pg-report-field" data-range="dates"' . (($range === 'dates') ? '' : ' hidden') . '>
                    <label class="pg-report-label" for="from">' . lang('Start') . '</label>
                    <input type="date" data-pg-date class="form-control" id="from" name="from" min="' . ERP_PROFIT_FIRST_YEAR . '-01-01" max="' . ERP_PROFIT_LAST_YEAR . '-12-31" value="' . h($period['from']) . '" />
                </span>
                <span class="pg-report-field" data-range="dates"' . (($range === 'dates') ? '' : ' hidden') . '>
                    <label class="pg-report-label" for="to">' . lang('End') . '</label>
                    <input type="date" data-pg-date class="form-control" id="to" name="to" min="' . ERP_PROFIT_FIRST_YEAR . '-01-01" max="' . ERP_PROFIT_LAST_YEAR . '-12-31" value="' . h($period['to']) . '" />
                </span>
                <span class="pg-report-field">
                    <label class="pg-report-label" for="channel">' . lang('Channel') . '</label>
                    <select class="form-control" id="channel" name="channel">' . $channel_options . '</select>
                </span>
                <button type="submit" class="pg-button pg-button-quiet"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Show') . '</button>
                <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . h(prepare_form_data_for_output($period['from'], 'date', false) . ' – ' . prepare_form_data_for_output($period['to'], 'date', false)) . '</span>
            </form>

            <section class="pg-report-view" aria-label="' . h(lang('Summary')) . '">
                <div class="pg-report-view-figures">' . $output_figures . '</div>
            </section>

            ' . $output_notes . '

            <section class="pg-card pg-report-frame mb-7">
                <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('By channel') . '</h3></div>
                <div class="pg-report-frame-body table-responsive">
                    <table class="pg-report-table" data-pg-sort id="erp_profitability_channels">
                        <thead>
                            <tr>
                                <th>' . lang('Channel') . '</th>
                                <th class="text-end">' . lang('Units') . '</th>
                                <th class="text-end">' . lang('Net sales') . '</th>
                                ' . $cost_heads . '
                                <th class="text-end">' . lang('Net profit') . '</th>
                                <th class="text-end">' . lang('Profit margin') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_channels . '</tbody>
                    </table>
                </div>
            </section>

            <section class="pg-card pg-report-frame mb-0">
                <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('By product and channel') . '</h3></div>
                <div class="pg-report-frame-body table-responsive" style="max-height:40rem">
                    <table class="pg-report-table" data-pg-sort id="erp_profitability_products">
                        <thead class="sticky-top">
                            <tr>
                                <th>' . lang('Product') . '</th>
                                <th>' . lang('Channel') . '</th>
                                <th class="text-end">' . lang('Orders') . '</th>
                                <th class="text-end">' . lang('Units') . '</th>
                                <th class="text-end">' . lang('Net sales') . '</th>
                                ' . $cost_heads . '
                                <th class="text-end">' . lang('Net profit') . '</th>
                                <th class="text-end">' . lang('Profit margin') . '</th>
                                <th class="text-end" title="' . h(lang('The lowest unit price without tax that makes no loss, from the last 90 days\' fees and costs. Prices never change by themselves.')) . '">' . lang('Break-even price') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_products . '</tbody>
                    </table>
                </div>
            </section>

            ' . $output_settings . '

            <p class="pg-text-meta fg-secondary my-5">' . lang('Orders by the date they were placed, finished orders only. An order\'s revenue and costs are split over its lines by each line\'s share of the order; a line with its own product cost keeps it. Refunds lower revenue.') . '</p>
        </div>
    </div>
</main>
<script>
(function () {
    var form = document.getElementById("erp_profitability_period");
    var range = document.getElementById("range");
    if (form && range) {
        range.addEventListener("change", function () {
            form.querySelectorAll("[data-range]").forEach(function (el) { el.hidden = (el.getAttribute("data-range") !== range.value); });
            if (range.value !== "dates") { form.submit(); }
        });
        ["month", "quarter", "year", "channel"].forEach(function (name) {
            var select = document.getElementById(name);
            if (select) { select.addEventListener("change", function () { form.submit(); }); }
        });
    }
})();
</script>
' . get_date_picker_format() . pg_fields_script() .
output_footer();

$liveform->remove_form();
