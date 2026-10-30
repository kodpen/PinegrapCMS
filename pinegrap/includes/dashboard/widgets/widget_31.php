<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 31 - Google Search: the clicks and impressions of the last
 * 28 days with figures against the 28 before, the week of clicks as bars and
 * the searches that brought the most visitors (includes/fn/search_console.php).
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_31($request, $user)
{
    if (($user['role'] >= 3) && empty($user['manage_visitors'])) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');

    if (!pg_gsc_linked()) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    $latest = pg_gsc_latest_day();

    $footer = '
    <div class="card-footer border-0 bg-reset py-1 text-center">
        <a href="search_console.php" class="text-decoration-none" style="font-size:11px">'
        . lang('Google Search Console') . ' <i class="bi bi-arrow-right-short"></i></a>
    </div>';

    if ($latest === '') {
        return array(
            'status'  => 'success',
            'message' => 'Action Success',
            'data'    => '
    <div class="card-body p-0 d-flex flex-column">
        ' . pg_widget_empty('bi-google', lang('No figures yet. Google publishes them two or three days late; the daily job downloads them as they come.')) . '
    </div>' . $footer,
        );
    }

    $period = pg_gsc_period($latest, 28);
    $now = pg_gsc_totals($period['from'], $period['to']);
    $before = pg_gsc_totals($period['previous_from'], $period['previous_to']);

    $week = array();

    foreach (pg_gsc_daily(date('Y-m-d', strtotime($latest . ' 12:00:00') - (6 * 86400)), $latest) as $values) {
        $week[] = $values['clicks'];
    }

    $change = function ($now, $before) {
        $moved = pg_gsc_change($now, $before);

        if ($moved === null) {
            return '';
        }

        $class = (abs($moved) < 0.005) ? 'fg-secondary' : (($moved > 0) ? 'fg-success' : 'fg-danger');

        // The sign stays outside the translated percentage, so Turkish
        // reads +%12 rather than %+12.
        $sign = (round(abs($moved) * 100) == 0) ? '' : (($moved > 0) ? '+' : '-');

        return '<span class="' . $class . '" style="font-size:11px" title="' . h(lang('Against the 28 days before')) . '">'
            . $sign . h(lang(array('string' => '{var:1}%', 'vars' => pg_format_number(abs($moved) * 100, 0))))
            . '<span class="visually-hidden"> ' . h(lang('Against the 28 days before')) . '</span></span>';
    };

    $figure = function ($label, $value, $moved) {
        return '
            <div class="flex-fill">
                <div class="fg-secondary" style="font-size:11px">' . h($label) . '</div>
                <div class="d-flex align-items-baseline gap-3">
                    <span class="fw-semibold" style="font-size:18px">' . h(pg_format_number($value, 0)) . '</span>
                    ' . $moved . '
                </div>
            </div>';
    };

    $rows = '';

    foreach (pg_gsc_top('query', $period['from'], $period['to'], 5) as $query) {
        $rows .= pg_widget_row(array(
            'small' => true,
            'badge' => '<i class="bi bi-search"></i>',
            'name'  => h($query['name']),
            'aside' => pg_format_number($query['clicks'], 0),
            'meta'  => h(lang(array(
                'string' => '{var:1} impressions, position {var:2}',
                'vars'   => array(pg_format_number($query['impressions'], 0), pg_format_number($query['position'], 1)),
            ))),
        ));
    }

    if ($rows === '') {
        $rows = '<p class="fg-secondary text-center mb-0 py-5" style="font-size:12px">' . h(lang('No search brought a click in these 28 days.')) . '</p>';
    }

    $output_data = '
    <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
        <div class="d-flex align-items-end gap-5 px-5 pt-3 pb-3">
            ' . $figure(lang('Clicks'), $now['clicks'], $change($now['clicks'], $before['clicks'])) . '
            ' . $figure(lang('Impressions'), $now['impressions'], $change($now['impressions'], $before['impressions'])) . '
            <span title="' . h(lang('Clicks in the last 7 days with figures')) . '">' . pg_widget_bars($week, lang('Clicks')) . '</span>
        </div>
        <div class="fg-secondary px-5 pb-1" style="font-size:10px">' . h(prepare_form_data_for_output($period['from'], 'date', false) . ' – ' . prepare_form_data_for_output($period['to'], 'date', false)) . '</div>
        <div class="pg-list">
            ' . pg_widget_row_heading(lang('Top searches'), lang('clicks')) . '
            ' . $rows . '
        </div>
    </div>' . $footer;

    return array(
        'status'  => 'success',
        'message' => 'Action Success',
        'data'    => $output_data,
    );
}
