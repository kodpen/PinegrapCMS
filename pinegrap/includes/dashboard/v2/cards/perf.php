<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Performance card: the front end's last day from the hourly summary,
 * read by widget 23's own helpers (pg_dashboard_perf_summary(),
 * pg_dashboard_perf_grade()): the share of requests that were not slow as a
 * ring with the grade, the average, slow requests and requests as tiles,
 * and the slowest pages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * The ring's share and the average's tone. Pure.
 *
 * @param int $hits
 * @param int $slow requests over the slow line
 * @param int $avg  average milliseconds
 * @return array fast (0-1, share of requests under the slow line), tone
 *               (good under 200 ms, warn under 500, else bad)
 */
function pg_dashboard_v2_perf_shape($hits, $slow, $avg)
{
    $hits = max(0, (int) $hits);

    return array(
        'fast' => ($hits > 0) ? max(0, min(1, ($hits - max(0, (int) $slow)) / $hits)) : 0,
        'tone' => ($avg >= 500) ? 'bad' : (($avg >= 200) ? 'warn' : 'good'),
    );
}

function pg_dashboard_v2_card_perf($user, $period, $compare)
{
    if ((int) $user['role'] >= 3) {
        return array('status' => 'denied');
    }

    if (defined('PERF_MONITOR_ENABLED') && !PERF_MONITOR_ENABLED) {
        return array('status' => 'success', 'data' => array('empty' => lang('Performance monitoring is turned off.')));
    }

    if (!pg_schema_has('perf_stats')) {
        return array('status' => 'success', 'data' => array('empty' => lang('The performance tables do not exist yet. Please run the software upgrade to create them.')));
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_23.php');

    $summary = pg_dashboard_perf_summary(time() - 86400);
    $hits = (int) ($summary['totals']['hits'] ?? 0);

    if ($hits <= 0) {
        return array('status' => 'success', 'data' => array('empty' => lang('No data yet for the selected period.')));
    }

    $slow = (int) ($summary['totals']['slow_hits'] ?? 0);
    $avg = (int) round(((float) ($summary['totals']['total_ms'] ?? 0)) / $hits);
    $worst = $summary['worst'];
    $grade = pg_dashboard_perf_grade($hits, $worst ? (string) $worst['label'] : '', $worst ? (int) round($worst['floor_ms']) : 0);
    $shape = pg_dashboard_v2_perf_shape($hits, $slow, $avg);
    $slow_ms = defined('PERF_MONITOR_SLOW_MS') ? (int) PERF_MONITOR_SLOW_MS : 1000;
    $tones = array('secondary' => '', 'success' => 'good', 'warning' => 'warn', 'danger' => 'bad');
    $fast = pg_format_number($shape['fast'] * 100, 0);

    $rows = array();

    foreach ($summary['pages'] as $page) {
        $rows[] = array(
            'text'  => pg_perf_label_display($page['label']),
            'note'  => pg_format_number((int) $page['avg_ms'], 0) . ' ms',
            'value' => (int) $page['avg_ms'],
        );
    }

    return array('status' => 'success', 'data' => array(
        'ring'       => $shape['fast'],
        'ring_text'  => $fast . '%',
        'ring_label' => lang(array('string' => '{var:1}% of requests under {var:2} ms', 'vars' => array($fast, pg_format_number($slow_ms, 0)))),
        'grade'      => $grade['grade'],
        'grade_tone' => $tones[$grade['class']],
        'grade_note' => $grade['enough']
            ? lang(array('string' => 'graded on {var:1}, {var:2} ms at its fastest', 'vars' => array(pg_perf_label_display($worst['label']), pg_format_number((int) round($worst['floor_ms']), 0))))
            : lang(array('string' => '{var:1} request{suffix:1} recorded', 'vars' => pg_format_number($hits, 0), 'suffix' => array(($hits === 1) ? '' : 's'))),
        'tiles'      => array(
            array('label' => lang('Average'), 'text' => pg_format_number($avg, 0) . ' ms', 'note' => lang('Last 24 hours'), 'tone' => $shape['tone']),
            array('label' => lang('Slow requests'), 'text' => pg_format_number($slow, 0), 'note' => lang(array('string' => 'over {var:1} ms', 'vars' => pg_format_number($slow_ms, 0))), 'tone' => ($slow > 0) ? 'warn' : 'good'),
            array('label' => lang('Requests'), 'text' => pg_format_number($hits, 0), 'note' => lang('Front end'), 'tone' => ''),
        ),
        'rows_title' => lang('Slowest pages'),
        'rows'       => $rows,
        'url'        => 'view_performance_log.php',
        'link'       => lang('Performance Log'),
    ));
}
