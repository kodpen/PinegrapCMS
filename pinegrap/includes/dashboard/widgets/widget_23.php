<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 23 - Performance: page timing from the hourly summary.
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

function pg_dashboard_widget_23($request, $user)
{
    // ── Performance ─────────────────────────────────────────
    //
    // Reads the hourly summary, never the raw rows: the point of
    // this widget is to be cheap enough to sit on a dashboard that
    // refreshes every minute. The old per-request table would have
    // made it the second most expensive thing on the page.
    //
    // FRONT END ONLY. Back-end screens are still measured and are
    // in the full report, where the operator can filter by area —
    // but they do not belong on this widget. A settings page that
    // one administrator opens twice a day would otherwise sit in
    // the same average as the product page thousands of customers
    // load, and could set the health grade on its own. The number
    // a shop owner needs at a glance is what their visitors are
    // waiting for.
    if ($user['role'] < 3) {

        // Monitoring off: nothing is being recorded and the tables
        // were emptied when it was switched off, so say that rather
        // than render zeroes that look like a broken site.
        if (defined('PERF_MONITOR_ENABLED') && !PERF_MONITOR_ENABLED) {
            $output_data = '
            <div class="card-body d-flex align-items-center justify-content-center text-center">
                <div>
                    <i class="bi bi-speedometer2 d-block mb-2" style="font-size:22px;opacity:.35"></i>
                    <p class="text-muted mb-0" style="font-size:12px">' . lang('Performance monitoring is turned off.') . '</p>
                </div>
            </div>';

            $response = array('status' => 'success', 'message' => 'Action Success', 'data' => $output_data);
            return $response;
        }

        $pf_available = (mysqli_num_rows(mysqli_query(db::$con, "SHOW TABLES LIKE 'perf_stats'")) > 0);

        if (!$pf_available) {
            $output_data = '
            <div class="card-body d-flex align-items-center justify-content-center text-center">
                <div>
                    <i class="bi bi-database-exclamation d-block mb-2" style="font-size:22px;opacity:.4"></i>
                    <p class="text-muted mb-0" style="font-size:12px">' . lang('The performance tables do not exist yet. Please run the software upgrade to create them.') . '</p>
                </div>
            </div>';

            $response = array('status' => 'success', 'message' => 'Action Success', 'data' => $output_data);
            return $response;
        }

        $pf_day_ago = time() - 86400;
        $pf_slow_ms = defined('PERF_MONITOR_SLOW_MS') ? (int) PERF_MONITOR_SLOW_MS : 1000;

        // Averages are computed from the sums. Averaging the
        // per-bucket averages would weight a quiet hour the same
        // as a busy one and quietly give the wrong number.
        $pf_totals = mysqli_fetch_assoc(mysqli_query(
            db::$con,
            "SELECT
                COALESCE(SUM(hits), 0)      AS hits,
                COALESCE(SUM(slow_hits), 0) AS slow_hits,
                COALESCE(SUM(total_ms), 0)  AS total_ms,
                COALESCE(MAX(max_ms), 0)    AS max_ms,
                COALESCE(SUM(total_kb), 0)  AS total_kb
             FROM perf_stats
             WHERE hour_start >= " . (int) $pf_day_ago . "
               AND area = 'frontend'"
        ));

        $pf_hits = (int) $pf_totals['hits'];
        $pf_slow = (int) $pf_totals['slow_hits'];
        $pf_avg  = $pf_hits > 0 ? (int) round($pf_totals['total_ms'] / $pf_hits) : 0;
        $pf_max  = (int) $pf_totals['max_ms'];

        // Colour the average against what a page should feel like,
        // not against its own history: 200 ms is fine, 500 ms is
        // noticeable, beyond that a visitor is waiting.
        if ($pf_avg >= 500) {
            $pf_avg_color = 'danger';
        } elseif ($pf_avg >= 200) {
            $pf_avg_color = 'warning';
        } else {
            $pf_avg_color = 'success';
        }

        $pf_slow_color = ($pf_slow > 0) ? 'warning' : 'success';

        // ── Health ───────────────────────────────────────────
        //
        // Graded on the slowest page that gets real traffic, NOT
        // on the site average.
        //
        // The average is the wrong number for a verdict because it
        // is dominated by whatever is cheapest and most frequent.
        // A site whose pages are all fast except an eight-second
        // checkout averages well under 100 ms and would be shown a
        // green badge while losing sales on the one page that
        // pays for everything. A visitor never experiences the
        // average; they experience the page they opened.
        //
        // Graded on the page's FASTEST run, not its average.
        //
        // An outlier inflates an average but cannot touch a
        // minimum — that is what a minimum is. So a product page
        // that normally answers in 200 ms and once took 101
        // seconds because a crawler caught a lock still reads as
        // 200 ms, while a checkout that takes eight seconds every
        // single time reads as eight seconds. The first is not a
        // broken page; the second is.
        //
        // The alternative suggested itself — ignore anything over a
        // second as probably bogus — would have hidden exactly the
        // page worth finding, because a consistently slow checkout
        // is over that line on every request.
        //
        // The trade-off, stated plainly: a page that is slow only
        // half the time grades on its good half. Understating is
        // the safer error for a badge that has to be trusted, and
        // the average is still visible in the list underneath.
        $pf_worst = mysqli_fetch_assoc(mysqli_query(
            db::$con,
            "SELECT label, SUM(hits) AS hits, MIN(min_ms) AS floor_ms,
                    SUM(total_ms) / GREATEST(SUM(hits), 1) AS avg_ms
             FROM perf_stats
             WHERE hour_start >= " . (int) $pf_day_ago . "
               AND area = 'frontend'
             GROUP BY label
             HAVING SUM(hits) >= 3
             ORDER BY floor_ms DESC
             LIMIT 1"
        ));

        // Quiet site: nothing opened three times yet.
        if (!$pf_worst) {
            $pf_worst = mysqli_fetch_assoc(mysqli_query(
                db::$con,
                "SELECT label, SUM(hits) AS hits, MIN(min_ms) AS floor_ms,
                        SUM(total_ms) / GREATEST(SUM(hits), 1) AS avg_ms
                 FROM perf_stats
                 WHERE hour_start >= " . (int) $pf_day_ago . "
                   AND area = 'frontend'
                 GROUP BY label
                 ORDER BY floor_ms DESC
                 LIMIT 1"
            ));
        }

        $pf_worst_ms = $pf_worst ? (int) round($pf_worst['floor_ms']) : 0;
        $pf_worst_label = $pf_worst ? $pf_worst['label'] : '';

        // Below this there is not enough traffic to judge anything,
        // and a confident verdict from four requests is worse than
        // admitting the sample is too small.
        $pf_enough = ($pf_hits >= 10);

        if (!$pf_enough) {
            $pf_grade = lang('Not enough data');
            $pf_grade_class = 'secondary';
        } elseif ($pf_worst_ms < 300) {
            $pf_grade = lang('Very good');
            $pf_grade_class = 'success';
        } elseif ($pf_worst_ms < 800) {
            $pf_grade = lang('Good');
            $pf_grade_class = 'success';
        } elseif ($pf_worst_ms < 2000) {
            $pf_grade = lang('Weak');
            $pf_grade_class = 'warning';
        } else {
            $pf_grade = lang('Poor');
            $pf_grade_class = 'danger';
        }

        // Needle position. Duration has no upper bound, so a linear
        // scale would leave every healthy site pinned at zero and
        // every unhealthy one pinned at maximum. The scale is
        // piecewise instead, stretched across the range where the
        // difference actually changes what a visitor feels.
        $pf_points = array(
            array(0, 0.0), array(300, 0.25), array(800, 0.5),
            array(2000, 0.75), array(5000, 1.0),
        );

        $pf_fraction = 1.0;

        for ($i = 1; $i < count($pf_points); $i++) {
            if ($pf_worst_ms <= $pf_points[$i][0]) {
                $pf_span = $pf_points[$i][0] - $pf_points[$i - 1][0];
                $pf_into = $pf_worst_ms - $pf_points[$i - 1][0];
                $pf_fraction = $pf_points[$i - 1][1]
                    + (($pf_span > 0 ? $pf_into / $pf_span : 0)
                       * ($pf_points[$i][1] - $pf_points[$i - 1][1]));
                break;
            }
        }

        if (!$pf_enough) {
            $pf_fraction = 0;
        }

        // Semicircle: 180° on the left through to 0° on the right.
        $pf_angle = 180 - ($pf_fraction * 180);
        $pf_rad = $pf_angle * M_PI / 180;
        $pf_nx = 70 + (44 * cos($pf_rad));
        $pf_ny = 70 - (44 * sin($pf_rad));

        // Band arcs, drawn once. Kept as flat strokes with no
        // gradient so they render identically in both themes.
        $pf_arc = '';
        $pf_bands = array(
            array(0.00, 0.25, 'var(--bs-success)'),
            array(0.25, 0.50, 'var(--bs-success)'),
            array(0.50, 0.75, 'var(--bs-warning)'),
            array(0.75, 1.00, 'var(--bs-danger)'),
        );

        foreach ($pf_bands as $pf_band) {
            $a1 = (180 - ($pf_band[0] * 180)) * M_PI / 180;
            $a2 = (180 - ($pf_band[1] * 180)) * M_PI / 180;
            $x1 = 70 + (52 * cos($a1));
            $y1 = 70 - (52 * sin($a1));
            $x2 = 70 + (52 * cos($a2));
            $y2 = 70 - (52 * sin($a2));

            $pf_arc .= '<path d="M ' . round($x1, 2) . ' ' . round($y1, 2)
                . ' A 52 52 0 0 1 ' . round($x2, 2) . ' ' . round($y2, 2) . '"'
                . ' fill="none" stroke="' . $pf_band[2] . '" stroke-width="9"'
                . ' stroke-linecap="butt" opacity="' . ($pf_enough ? '0.85' : '0.25') . '"/>';
        }

        $pf_worst_display = $pf_worst_label;

        if (mb_strlen($pf_worst_display) > 26) {
            $pf_worst_display = '…' . mb_substr($pf_worst_display, -25);
        }

        $pf_gauge = '
        <div class="d-flex align-items-center border-bottom px-2 py-2" style="gap:8px">
            <svg viewBox="0 0 140 84" style="width:104px;height:62px;flex-shrink:0" role="img" aria-label="' . h($pf_grade) . '">
                <path d="M 18 70 A 52 52 0 0 1 122 70" fill="none" stroke="rgba(128,128,128,.15)" stroke-width="9"/>
                ' . $pf_arc . '
                <line x1="70" y1="70" x2="' . round($pf_nx, 2) . '" y2="' . round($pf_ny, 2) . '"
                      stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="70" cy="70" r="4" fill="currentColor"/>
            </svg>
            <div class="flex-fill overflow-hidden">
                <div class="fw-semibold text-' . h($pf_grade_class) . '" style="font-size:15px;line-height:1.1">' . h($pf_grade) . '</div>'
                . ($pf_enough && $pf_worst_label !== ''
                    ? '<div class="text-truncate text-muted" style="font-size:11px" title="' . h($pf_worst_label) . '">' . h($pf_worst_display) . '</div>
                       <div class="text-muted" style="font-size:11px">' . lang(array(
                            'string' => '{var:1} ms at its fastest',
                            'vars'   => pg_format_number($pf_worst_ms, 0),
                        )) . '</div>'
                    : '<div class="text-muted" style="font-size:11px">' . lang(array(
                            'string' => '{var:1} request{suffix:1} recorded',
                            'vars'   => pg_format_number($pf_hits, 0),
                            'suffix' => ($pf_hits == 1 ? '' : 's'),
                        )) . '</div>')
            . '</div>
        </div>';

        // Slowest pages by average. Ordered by average rather than
        // by worst case, because one freak request says less than a
        // page that is consistently slow for everyone who opens it.
        //
        // No minimum hit count. An earlier version required three
        // hits to keep one-off flukes out, and on a quiet site that
        // silently hid the entire front end: product and blog pages
        // get a visit or two a day, while the admin screens the
        // operator keeps refreshing sail past the threshold. The
        // widget then disagreed with the report next to it, which
        // is worse than showing an occasional outlier. The hit
        // count is in the bar's tooltip for context.
        $pf_rows = '';

        $pf_result = mysqli_query(
            db::$con,
            "SELECT
                label,
                area,
                SUM(hits)                              AS hits,
                SUM(total_ms) / GREATEST(SUM(hits), 1) AS avg_ms,
                MAX(max_ms)                            AS max_ms
             FROM perf_stats
             WHERE hour_start >= " . (int) $pf_day_ago . "
               AND area = 'frontend'
             GROUP BY label, area
             ORDER BY avg_ms DESC
             LIMIT 5"
        );

        $pf_pages = $pf_result ? mysqli_fetch_items($pf_result) : array();

        // Scale the bars against the slowest entry on the list, not
        // against some absolute ceiling: with one page at 40 seconds
        // every other bar would round to nothing.
        $pf_peak = 0;

        foreach ($pf_pages as $pf_page) {
            if ((int) $pf_page['avg_ms'] > $pf_peak) {
                $pf_peak = (int) $pf_page['avg_ms'];
            }
        }

        foreach ($pf_pages as $pf_page) {
            $pf_page_avg = (int) $pf_page['avg_ms'];
            $pf_width = ($pf_peak > 0) ? (int) round(100 * $pf_page_avg / $pf_peak) : 0;

            if ($pf_width < 3) {
                $pf_width = 3;
            }

            if ($pf_page_avg >= $pf_slow_ms) {
                $pf_bar = 'bg-danger';
            } elseif ($pf_page_avg >= 500) {
                $pf_bar = 'bg-warning';
            } else {
                $pf_bar = 'bg-secondary';
            }

            // Front-end labels are URLs and can be very long; show
            // the tail, which is the part that identifies the page.
            $pf_label = $pf_page['label'];

            if (mb_strlen($pf_label) > 34) {
                $pf_label = '…' . mb_substr($pf_label, -33);
            }

            $pf_rows .= '
            <div class="mb-2">
                <div class="d-flex align-items-center justify-content-between" style="gap:6px">
                    <span class="text-truncate" style="font-size:12px" title="' . h($pf_page['label']) . '">' . h($pf_label) . '</span>
                    <span class="text-muted flex-shrink-0" style="font-size:11px">' . pg_format_number($pf_page_avg, 0) . ' ms</span>
                </div>
                <div class="progress mt-1" style="height:4px;background:rgba(0,0,0,.06)" title="' . lang(array(
                    'string' => '{var:1} request{suffix:1} · peak {var:2} ms',
                    'vars'   => array(pg_format_number((int) $pf_page['hits'], 0), pg_format_number((int) $pf_page['max_ms'], 0)),
                    'suffix' => ((int) $pf_page['hits'] == 1 ? '' : 's'),
                )) . '">
                    <div class="progress-bar ' . $pf_bar . '" style="width:' . $pf_width . '%"></div>
                </div>
            </div>';
        }

        if ($pf_rows === '') {
            $pf_rows = '
            <div class="text-center py-4">
                <i class="bi bi-speedometer2 d-block mb-2" style="font-size:22px;opacity:.35"></i>
                <p class="text-muted mb-0" style="font-size:12px">' . lang('No data yet for the selected period.') . '</p>
            </div>';
        }

        $pf_footer = '';

        if ($user['role'] < 3) {
            $pf_footer = '
            <div class="card-footer border-0 bg-reset py-1 text-center">
                <a href="view_performance_log.php" class="text-decoration-none" style="font-size:11px">'
                . lang('Performance Log') . ' <i class="bi bi-arrow-right-short"></i></a>
            </div>';
        }

        $output_data = '
            <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
                ' . $pf_gauge . '
                <div class="d-flex border-bottom">
                    <div class="flex-fill px-3 py-2">
                        <div class="fw-semibold text-' . h($pf_avg_color) . '" style="font-size:17px;line-height:1">' . pg_format_number($pf_avg, 0) . ' <span style="font-size:11px">ms</span></div>
                        <div class="text-muted text-truncate" style="font-size:11px">' . lang('Average') . '</div>
                    </div>
                    <div class="flex-fill px-3 py-2 border-start">
                        <div class="fw-semibold text-' . h($pf_slow_color) . '" style="font-size:17px;line-height:1">' . pg_format_number($pf_slow, 0) . '</div>
                        <div class="text-muted text-truncate" style="font-size:11px">' . lang(array(
                            'string' => 'Slower than {var:1} ms',
                            'vars'   => pg_format_number($pf_slow_ms, 0),
                        )) . '</div>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
                    <span class="text-muted" style="font-size:11px">' . lang('Slowest pages') . '</span>
                    <span class="text-muted" style="font-size:10px">' . lang('Front end') . ' · ' . lang('Last 24 hours') . '</span>
                </div>
                <div class="px-3 pb-2">' . $pf_rows . '</div>
            </div>' . $pf_footer;

        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
            'data' => $output_data,
        );
        return $response;
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
