<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Referrals report: the referral programme's rewards of the last twelve
 * months, read from its commissions rows (includes/fn/referral_report.php).
 * By month the referred orders, the rewards kept and taken back, the credit
 * given and the referred orders' value; the top referrers, named only for a
 * reader who may read contacts.
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

$now = time();
$symbol = BASE_CURRENCY_SYMBOL;
$may_see_names = (bool) validate_contacts_access($user, true);
$keys = pg_referral_report_month_keys($now);
$rows = pg_referral_report_rows(pg_referral_report_start($now));
$months = pg_referral_report_months($rows, $keys);
$totals = pg_referral_report_totals($months);
$top = pg_referral_report_top($rows, pg_referral_key());

$output_notices = '';

if (!pg_referral_settings()['on']) {
    $output_notices .= '<div class="alert theme-info">' . h(lang('The referral programme is off. Rewards given while it was on are still counted here.')) . '</div>';
}

$report_view = array(
    'figures' => pg_referral_report_figures($totals, $symbol),
    'chart' => pg_referral_report_chart($months, $symbol),
    'strings' => pg_report_view_strings(),
);

// By month, newest first.
$output_rows = '';

foreach (array_reverse($months, true) as $key => $row) {
    $output_rows .= '<tr>
        <th scope="row" class="fw-normal">' . h(pg_customer_value_month_name($key)) . '</th>
        <td class="text-end">' . pg_format_number($row['orders'], 0) . '</td>
        <td class="text-end">' . pg_format_number($row['kept'], 0) . '</td>
        <td class="text-end">' . pg_format_number($row['taken'], 0) . '</td>
        <td class="text-end">' . h(pg_report_view_money($row['credit'], $symbol)) . '</td>
        <td class="text-end">' . h(pg_report_view_money($row['value'], $symbol)) . '</td>
    </tr>';
}

$output_sections = '
    <section class="pg-card pg-report-frame" aria-labelledby="pg_referral_months_title">
        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_referral_months_title">' . h(lang('By month')) . '</h3><span class="pg-report-frame-note">' . h(lang('A referred order is a friend\'s order that earned its referrer a reward. A reward taken back by a cancel or a refund counts neither its credit nor its order\'s value.')) . '</span></div>
        <div class="pg-report-frame-body">
            <table class="pg-report-table">
                <thead><tr>
                    <th>' . h(lang('Month')) . '</th>
                    <th class="text-end">' . h(lang('Referred orders')) . '</th>
                    <th class="text-end">' . h(lang('Rewards kept')) . '</th>
                    <th class="text-end">' . h(lang('Taken back')) . '</th>
                    <th class="text-end">' . h(lang('Store credit given')) . '</th>
                    <th class="text-end">' . h(lang('Referred orders\' value')) . '</th>
                </tr></thead>
                <tbody>' . $output_rows . '</tbody>
                <tfoot><tr class="pg-report-total">
                    <th scope="row">' . h(lang('Total')) . '</th>
                    <td class="text-end">' . pg_format_number($totals['orders'], 0) . '</td>
                    <td class="text-end">' . pg_format_number($totals['kept'], 0) . '</td>
                    <td class="text-end">' . pg_format_number($totals['taken'], 0) . '</td>
                    <td class="text-end">' . h(pg_report_view_money($totals['credit'], $symbol)) . '</td>
                    <td class="text-end">' . h(pg_report_view_money($totals['value'], $symbol)) . '</td>
                </tr></tfoot>
            </table>
        </div>
    </section>';

// The top referrers: names only for a reader who may read contacts, a link
// to the contact for one who may also open it.
$names = $may_see_names ? pg_referral_report_names(array_column($top, 'contact_id')) : array();
$output_rows = '';

foreach ($top as $row) {
    $name = '';

    if ($may_see_names) {
        $name = (($names[$row['contact_id']] ?? '') !== '') ? h($names[$row['contact_id']]) : '<span class="fg-secondary">' . h(lang('Name not shown')) . '</span>';
        $name = '<th scope="row" class="fw-normal"><a href="' . h('edit_contact.php?id=' . $row['contact_id']) . '">' . $name . '</a></th>';
    }

    $output_rows .= '<tr>
        <td class="text-end">' . (int) $row['rank'] . '</td>
        ' . $name . '
        <td class="text-end">' . pg_format_number($row['kept'], 0) . '</td>
        <td class="text-end">' . h(pg_report_view_money($row['credit'], $symbol)) . '</td>
        <td class="text-end">' . h(pg_report_view_money($row['value'], $symbol)) . '</td>
    </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr><td colspan="' . ($may_see_names ? 5 : 4) . '" class="fg-secondary">' . h(lang('No referral has been rewarded in these months.')) . '</td></tr>';
}

$output_sections .= '
    <section class="pg-card pg-report-frame" aria-labelledby="pg_referral_top_title">
        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_referral_top_title">' . h(lang('Top referrers')) . '</h3>'
            . (!$may_see_names ? '<span class="pg-report-frame-note">' . h(lang('Names are shown to users who may manage contacts.')) . '</span>' : '') . '</div>
        <div class="pg-report-frame-body">
            <table class="pg-report-table">
                <thead><tr><th class="text-end">#</th>' . ($may_see_names ? '<th>' . h(lang('Referring customer')) . '</th>' : '') . '<th class="text-end">' . h(lang('Rewards kept')) . '</th><th class="text-end">' . h(lang('Store credit given')) . '</th><th class="text-end">' . h(lang('Referred orders\' value')) . '</th></tr></thead>
                <tbody>' . $output_rows . '</tbody>
            </table>
        </div>
    </section>';

$output_sections .= '<p class="fg-secondary small">' . h(lang('The value of a referred order is its total less what was refunded. Rewards are counted in the month their order was completed.')) . '</p>';

$output_range = pg_report_view_range(
    get_absolute_time(array('timestamp' => pg_referral_report_start($now), 'type' => 'date')),
    get_absolute_time(array('timestamp' => $now, 'type' => 'date'))
);

print
    pg_page_shell(
        array(
            'title' => lang('Referrals Report'),
            'extra classes' => 'store pg-measure-report',
            'icon' => 'store',
            'heading' => lang('Referrals Report'),
            'heading_description' => lang('See the orders your customers\' friends placed through a referral link and the store credit they earned.'),
            'help' => 'commerce-cont#referrals-report',
            'cancel' => false
        )
    ) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'search' => '<span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . $output_range . '</span>',
    ), array('class' => 'pg-report-tools')) . '
    <div class="row">
        <div class="col-12">
            ' . $output_notices . '
            <section class="pg-report-view" id="pg_report_view" aria-label="' . h(lang('Summary')) . '">
                <div class="pg-report-view-figures"></div>
                <div class="pg-report-view-chart"></div>
                <script type="application/json" id="pg_report_view_data">' . (string) json_encode($report_view, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>
            </section>
            ' . pg_js_bundle_tags('assets/js/pg_report_view.min.js') . '
            ' . $output_sections . '
        </div>
    </div>
</main>
' .
output_footer();
