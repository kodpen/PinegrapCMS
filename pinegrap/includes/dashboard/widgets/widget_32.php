<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 32 - Google Analytics: active users, sessions, views, key
 * events and the engagement rate of the last 28 days against the 28 before,
 * the last week of active users as bars, the most viewed pages and the
 * sources that brought the most sessions (includes/fn/google_analytics.php).
 *
 * Draws only the report google_analytics_job.php kept; Google is never
 * called while the dashboard loads.
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

function pg_dashboard_widget_32($request, $user)
{
    if (($user['role'] >= 3) && empty($user['manage_visitors'])) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');

    $state = pg_ga_state();

    if (!pg_ga_linked($state)) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    $report = $state['report'];

    // Settings are for managers and above; below that the link is left out.
    $footer = ($user['role'] < 3) ? '
    <div class="card-footer border-0 bg-reset py-1 text-center">
        <a href="' . h(pg_settings_link('seo', 'pgset-analytics')) . '" class="text-decoration-none" style="font-size:.6875rem">'
        . h($state['property_name']) . ' <i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>
    </div>' : '';

    if (!$report) {
        $text = ($state['fetch_error'] !== '')
            ? lang(array('string' => 'The Google Analytics figures could not be read: {var:1}', 'vars' => $state['fetch_error']))
            : lang('No figures yet. They are read from Google Analytics within the hour.');

        return array(
            'status'  => 'success',
            'message' => 'Action Success',
            'data'    => '
    <div class="card-body p-0 d-flex flex-column">
        ' . pg_widget_empty('bi-bar-chart-line', $text) . '
    </div>' . $footer,
        );
    }

    $against = h(lang('Against the 28 days before'));

    $change = function ($figure) use ($against) {
        if ($figure['change'] === null) {
            return '';
        }

        $moved = (float) $figure['change'];
        $rate = ($figure['kind'] === 'rate');
        $shown = $rate ? round(abs($moved), 1) : round(abs($moved) * 100);
        $class = ($shown == 0) ? 'fg-secondary' : (($moved > 0) ? 'fg-success' : 'fg-danger');

        // The sign stays outside the translated figure, so Turkish reads
        // +%12 rather than %+12.
        $sign = ($shown == 0) ? '' : (($moved > 0) ? '+' : '-');

        $text = $rate
            ? lang(array('string' => '{var:1} points', 'vars' => pg_format_number($shown, 1)))
            : lang(array('string' => '{var:1}%', 'vars' => pg_format_number($shown, 0)));

        return '<span class="' . $class . '" style="font-size:.6875rem" title="' . $against . '">'
            . $sign . h($text) . '<span class="visually-hidden"> ' . $against . '</span></span>';
    };

    $labels = array(
        'activeUsers'     => lang('Active Users'),
        'sessions'        => lang('Sessions'),
        'screenPageViews' => lang('Views'),
        'keyEvents'       => lang('Key events'),
        'engagementRate'  => lang('Engagement rate'),
    );

    $figures = '';

    foreach (pg_ga_card_figures($report) as $metric => $figure) {
        $value = ($figure['kind'] === 'rate')
            ? lang(array('string' => '{var:1}%', 'vars' => pg_format_number($figure['value'], 1)))
            : pg_format_number($figure['value'], 0);

        $figures .= '
            <div style="min-width:7rem">
                <div class="fg-secondary" style="font-size:.6875rem">' . h($labels[$metric]) . '</div>
                <div class="d-flex align-items-baseline gap-3">
                    <span class="fw-semibold" style="font-size:1.125rem">' . h($value) . '</span>
                    ' . $change($figure) . '
                </div>
            </div>';
    }

    // The week as bars, and the same days as a table a screen reader reads.
    $week = array_slice($report['daily'], -7, 7, true);
    $week_rows = '';

    foreach ($week as $day => $count) {
        $week_rows .= '<tr><th scope="row">' . h(prepare_form_data_for_output($day, 'date', false)) . '</th><td>' . h(pg_format_number($count, 0)) . '</td></tr>';
    }

    $list = function ($heading, $aside, $rows, $icon) {
        $out = '';

        foreach ($rows as $row) {
            $out .= pg_widget_row(array(
                'small' => true,
                'badge' => '<i class="bi ' . $icon . '" aria-hidden="true"></i>',
                'name'  => h($row['name']),
                'aside' => h(pg_format_number($row['value'], 0)),
            ));
        }

        if ($out === '') {
            $out = '<p class="fg-secondary text-center mb-0 py-3" style="font-size:.75rem">' . h(lang('Nothing counted in these 28 days.')) . '</p>';
        }

        return pg_widget_row_heading(h($heading), h($aside)) . $out;
    };

    $output_data = '
    <div class="card-body p-0 d-flex flex-column lg:flex-row" style="overflow-x:hidden;overflow-y:auto">
        <div class="flex-fill">
            <div class="d-flex flex-wrap align-items-end gap-5 px-5 pt-3 pb-3">
                ' . $figures . '
            </div>
            <div class="d-flex align-items-end gap-3 px-5 pb-1">
                <span class="fg-secondary" style="font-size:.6875rem">' . h(lang('Active users, last 7 days')) . '</span>
                ' . pg_widget_bars(array_values($week)) . '
            </div>
            <table class="visually-hidden">
                <caption>' . h(lang('Active users, last 7 days')) . '</caption>
                <tr><th scope="col">' . h(lang('Date')) . '</th><th scope="col">' . h(lang('Active Users')) . '</th></tr>
                ' . $week_rows . '
            </table>
            <div class="fg-secondary px-5 pb-3" style="font-size:.625rem">' . h(prepare_form_data_for_output($report['from'], 'date', false) . ' – ' . prepare_form_data_for_output($report['to'], 'date', false)) . '</div>
        </div>
        <div class="flex-fill pg-list">
            ' . $list(lang('Top pages'), lang('Views'), $report['pages'], 'bi-file-earmark-text') . '
            ' . $list(lang('Top sources'), lang('Sessions'), $report['sources'], 'bi-signpost-split') . '
        </div>
    </div>' . $footer;

    return array(
        'status'  => 'success',
        'message' => 'Action Success',
        'data'    => $output_data,
    );
}
