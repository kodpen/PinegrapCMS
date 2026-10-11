<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 5 - Visitor Summaries: visitors and page views over time.
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

function pg_dashboard_widget_5($request, $user)
{
    if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
        $now = time();
        $today_start = strtotime(date('Y-m-d'));
        $yesterday_start = $today_start - 86400;
        $month_start = mktime(0, 0, 0, (int) date('n'), 1, (int) date('Y'));
        $week_ago = $now - 7 * 86400;
        $two_weeks_ago = $now - 14 * 86400;

        // Get current hour (0-23) to limit today's data display
        $current_hour = (int) date('G');

        $vs5_no_data = pg_widget_empty('bi-graph-up', lang('There is not enough data yet.'));

        // Every figure below is read from the hourly rollups
        // rather than counted out of the raw visitors table.
        //
        // The old version ran eleven aggregates over `visitors`,
        // grouping on HOUR(FROM_UNIXTIME(start_timestamp)) — an
        // expression, so no index applied and each one built a
        // temporary table. One of them covered twelve months. At
        // 100,000-200,000 visits a day that is tens of millions of
        // rows scanned to draw three small charts, which is where
        // the twenty to thirty second load came from. Worse, on
        // MyISAM those scans hold a read lock, so every visitor
        // arriving on the site queued behind an open dashboard.
        //
        // The rollups hold 24 rows per day whatever the traffic.
        $today_date     = date('Y-m-d');
        $yesterday_date = date('Y-m-d', $yesterday_start);

        // Carry the backfill forward a slice at a time. This is
        // the one screen that wants the historical summaries, it
        // is reached by administrators only, and the budget is
        // small enough not to be felt. On a fresh upgrade whose
        // backfill was cut short by a server request timeout, the
        // history fills in over the next few dashboard loads
        // instead of needing anyone to restart anything.
        $vs5_backfill = false;
        if (function_exists('pg_visitor_backfill_step')) {
            $vs5_backfill = pg_visitor_backfill_step(3);
        }

        // ── PANEL 1 : Hourly peaks — today vs yesterday ────────────────────────
        $stats_2d = pg_visitor_stats_range($yesterday_date, $today_date);

        $h_today = array_fill(0, 24, 0);
        $h_yest  = array_fill(0, 24, 0);

        // Page views are carried alongside the visitor counts.
        //
        // The plotted line counts sessions that STARTED in an
        // hour, which is not the same as activity during that
        // hour: someone who arrives at 14:00 and reads ten pages
        // at 15:00 leaves 15:00 with no new session but ten views.
        // The tooltip names the busiest content of the hour, so
        // without this figure the reader sees a named article
        // with ten views sitting above a chart value of zero and
        // reasonably concludes something is broken.
        $h_today_views = array_fill(0, 24, 0);
        $h_yest_views  = array_fill(0, 24, 0);

        if (isset($stats_2d[$today_date])) {
            foreach ($stats_2d[$today_date] as $hh => $vals) {
                $h_today[(int) $hh]       = $vals['visitors'];
                $h_today_views[(int) $hh] = $vals['page_views'];
            }
        }
        if (isset($stats_2d[$yesterday_date])) {
            foreach ($stats_2d[$yesterday_date] as $hh => $vals) {
                $h_yest[(int) $hh]       = $vals['visitors'];
                $h_yest_views[(int) $hh] = $vals['page_views'];
            }
        }

        $kpi_today = array_sum($h_today);
        $kpi_yesterday = array_sum($h_yest);

        // Separate labels for today (limited to current hour) and yesterday (full 24 hours)
        $h_today_labels_js = '';
        $h_today_js = '';
        $h_today_views_js = '';
        for ($h = 0; $h <= $current_hour; $h++) {
            $h_today_labels_js .= '"' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00",';
            $h_today_js .= $h_today[$h] . ',';
            $h_today_views_js .= $h_today_views[$h] . ',';
        }

        $h_yest_labels_js = '';
        $h_yest_js = '';
        $h_yest_views_js = '';
        for ($h = 0; $h < 24; $h++) {
            $h_yest_labels_js .= '"' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00",';
            $h_yest_js .= $h_yest[$h] . ',';
            $h_yest_views_js .= $h_yest_views[$h] . ',';
        }

        // Busiest content per hour, for the chart tooltip.
        //
        // This is what the widget was asked to show and could not:
        // the tooltip now names the article or product, not the
        // page template that displayed it. Rows recorded before
        // this change still show the page name, because the item
        // identity was never written down and cannot be recovered.
        $vs5_hour_label = function ($row) {
            if (!$row) return null;
            return array('name' => h($row['name']), 'cnt' => (int) $row['cnt']);
        };

        $h_today_pages = array_map($vs5_hour_label, pg_visitor_top_content_by_hour($today_date));
        $h_yest_pages  = array_map($vs5_hour_label, pg_visitor_top_content_by_hour($yesterday_date));

        // Slice today pages to match current hour
        $h_today_pages = array_slice($h_today_pages, 0, $current_hour + 1);

        // Top content rows under each chart.
        $vs5_top = function ($from, $to) {
            $rows = pg_visitor_top_content($from, $to, 1);
            if (empty($rows)) return null;
            return array('name' => h($rows[0]['label']), 'cnt' => (int) $rows[0]['views']);
        };

        $tp_today = $vs5_top($today_date, $today_date);
        $tp_yest  = $vs5_top($yesterday_date, $yesterday_date);

        // ── PANEL 2 : Daily peaks — this 7 days vs previous 7 days ───────────
        // Daily totals folded up from the hourly rollup.
        $vs5_daily = function ($from_ts, $to_ts) {
            $out  = array();
            $rows = pg_visitor_stats_range(date('Y-m-d', $from_ts), date('Y-m-d', $to_ts));
            foreach ($rows as $d => $hours) {
                $sum = 0;
                foreach ($hours as $vals) $sum += $vals['visitors'];
                $out[$d] = $sum;
            }
            return $out;
        };

        // Bounded to exactly the seven dates each chart plots.
        //
        // The rollup is keyed by date where the old query filtered
        // on a timestamp, so a range expressed as "the last seven
        // times 86,400 seconds" would pull in part of an eighth
        // day and the headline figure would not match the bars
        // underneath it.
        $w_tw_map = $vs5_daily($now - 6 * 86400, $now);
        $w_lw_map = $vs5_daily($week_ago - 6 * 86400, $week_ago);

        $w_tw_labels = $w_tw_data = $w_lw_labels = $w_lw_data = '';
        $has_tw = $has_lw = false;
        for ($i = 6; $i >= 0; $i--) {
            $ts_tw = $now - $i * 86400;
            $ts_lw = $week_ago - $i * 86400;
            $d_tw = date('Y-m-d', $ts_tw);
            $d_lw = date('Y-m-d', $ts_lw);
            $c_tw = isset($w_tw_map[$d_tw]) ? $w_tw_map[$d_tw] : 0;
            $c_lw = isset($w_lw_map[$d_lw]) ? $w_lw_map[$d_lw] : 0;
            if ($c_tw > 0)
                $has_tw = true;
            if ($c_lw > 0)
                $has_lw = true;
            $w_tw_labels .= '"' . date('d', $ts_tw) . ' ' . lang(date('M', $ts_tw)) . '",';
            $w_lw_labels .= '"' . date('d', $ts_lw) . ' ' . lang(date('M', $ts_lw)) . '",';
            $w_tw_data .= $c_tw . ',';
            $w_lw_data .= $c_lw . ',';
        }
        $kpi_tw = array_sum($w_tw_map);
        $kpi_lw = array_sum($w_lw_map);

        // Top content — this week / last week
        $tp_tw = $vs5_top(date('Y-m-d', $now - 6 * 86400), date('Y-m-d', $now));
        $tp_lw = $vs5_top(date('Y-m-d', $week_ago - 6 * 86400), date('Y-m-d', $week_ago));

        // ── PANEL 3 : Monthly peaks — this month (daily) vs prev 12 months ───
        $m_tm_map = $vs5_daily($month_start, $now);

        $days_in_month = (int) date('t');
        $m_tm_labels = $m_tm_data = '';
        $has_tm = false;
        for ($day = 1; $day <= $days_in_month; $day++) {
            $d_ts = mktime(0, 0, 0, (int) date('n'), $day, (int) date('Y'));
            $d = date('Y-m-d', $d_ts);
            $cnt = isset($m_tm_map[$d]) ? $m_tm_map[$d] : 0;
            if ($cnt > 0)
                $has_tm = true;
            $m_tm_labels .= '"' . $day . '",';
            $m_tm_data .= $cnt . ',';
        }
        $kpi_tm = array_sum($m_tm_map);

        // Previous 12 months — monthly totals.
        //
        // This was the single most expensive query on the screen:
        // a year of raw visitor rows read and grouped on a
        // formatted date. Against the rollup it reads at most
        // 8,760 rows.
        $twelve_months_ago = mktime(0, 0, 0, (int) date('n') - 12, 1, (int) date('Y'));

        $pm_map = array();
        $res    = @mysqli_query(
            db::$con,
            "SELECT DATE_FORMAT(stat_date, '%Y-%m') AS ym, SUM(new_visitors) AS cnt
             FROM visitor_stats_hourly
             WHERE stat_date >= '" . e(date('Y-m-d', $twelve_months_ago)) . "'
               AND stat_date <  '" . e(date('Y-m-d', $month_start)) . "'
             GROUP BY ym"
        );
        if ($res) {
            while ($r = @mysqli_fetch_assoc($res))
                $pm_map[$r['ym']] = (int) $r['cnt'];
        }

        $m_pm_labels = $m_pm_data = '';
        $has_pm = false;
        for ($i = 12; $i >= 1; $i--) {
            $m_ts = mktime(0, 0, 0, (int) date('n') - $i, 1, (int) date('Y'));
            $ym = date('Y-m', $m_ts);
            $cnt = isset($pm_map[$ym]) ? $pm_map[$ym] : 0;
            if ($cnt > 0)
                $has_pm = true;
            $m_pm_labels .= '"' . lang(date('M', $m_ts)) . ' \'' . date('y', $m_ts) . '",';
            $m_pm_data .= $cnt . ',';
        }
        $kpi_pm = array_sum($pm_map);

        // Top content — this month
        $tp_tm = $vs5_top(date('Y-m-d', $month_start), date('Y-m-d', $now));

        // ── Like-for-like comparisons ─────────────────────────────────────────
        //
        // Today is a part-day and yesterday is a whole one. Setting
        // one against the other says traffic collapsed every
        // morning and recovered every midnight, which is the clock
        // talking, not the site. Each comparison below is cut to
        // the same point in its own period.
        $kpi_yesterday_same = 0;
        for ($h = 0; $h <= $current_hour; $h++) {
            $kpi_yesterday_same += $h_yest[$h];
        }

        // Previous month day by day, so the month card can overlay
        // like with like and its figure can be cut at today's date.
        // One more read of the rollup, which is 24 rows per day
        // whatever the traffic -- the same reason the rest of this
        // widget stopped touching the visitors table.
        $prev_month_start = mktime(0, 0, 0, (int) date('n') - 1, 1, (int) date('Y'));
        $prev_month_end   = mktime(0, 0, 0, (int) date('n'), 0, (int) date('Y'));
        $prev_month_days  = (int) date('t', $prev_month_start);
        $pm_daily_map     = $vs5_daily($prev_month_start, $prev_month_end);

        $today_day = (int) date('j');
        $kpi_pm_prev_same = 0;
        $m_pmd_data = '';

        for ($day = 1; $day <= $days_in_month; $day++) {
            // A 31 day month laid over a 30 day one leaves the last
            // slot with nothing behind it. null, not zero: zero
            // draws a line to the floor and reads as "no traffic
            // that day".
            if ($day > $prev_month_days) {
                $m_pmd_data .= 'null,';
                continue;
            }
            $d = date('Y-m-d', mktime(0, 0, 0, (int) date('n') - 1, $day, (int) date('Y')));
            $cnt = isset($pm_daily_map[$d]) ? $pm_daily_map[$d] : 0;
            $m_pmd_data .= $cnt . ',';
            if ($day <= $today_day) {
                $kpi_pm_prev_same += $cnt;
            }
        }

        // Today's hours run to the current hour, but the axis keeps
        // all 24 so the shape sits under yesterday's at the same
        // clock position. The hours that have not happened are null
        // rather than absent, which is what stops the line instead
        // of stretching it across the day.
        $h_today_full_js = '';
        for ($h = 0; $h < 24; $h++) {
            $h_today_full_js .= (($h <= $current_hour) ? $h_today[$h] : 'null') . ',';
        }
        $h_today_views_full_js = '';
        for ($h = 0; $h < 24; $h++) {
            $h_today_views_full_js .= (($h <= $current_hour) ? $h_today_views[$h] : 'null') . ',';
        }

        // Same treatment for the month: days after today are null.
        $m_tmd_data = '';
        for ($day = 1; $day <= $days_in_month; $day++) {
            if ($day > $today_day) {
                $m_tmd_data .= 'null,';
                continue;
            }
            $d = date('Y-m-d', mktime(0, 0, 0, (int) date('n'), $day, (int) date('Y')));
            $m_tmd_data .= (isset($m_tm_map[$d]) ? $m_tm_map[$d] : 0) . ',';
        }

        // Percentage rather than the raw difference: 242 against
        // 214 and 24,200 against 21,400 are the same news, and only
        // one of the two fits in a badge.
        $vs5_pct_change = function ($current, $previous) {
            if ($previous <= 0) {
                return null;
            }
            return (int) round((($current - $previous) / $previous) * 100);
        };

        // ── Helper: inline top-page row HTML ─────────────────────────────────
        // The badge counts page views, while the figure above the
        // chart counts visitors. Two different units sitting one
        // above the other, so the badge says which it is — a top
        // item can honestly show more views than the panel shows
        // visitors, and unlabelled that reads as a bug.
        $vs5_views_label = lang('page views');

        $vs5_tp = function ($tp, $rgb) use ($vs5_views_label) {
            if (!$tp)
                return '';
            return '<div class="d-flex align-items-center gap-1" style="font-size:11px">'
                . '<i class="bi bi-window text-muted flex-shrink-0"></i>'
                . '<span class="text-truncate flex-grow-1 text-muted" title="' . $tp['name'] . '">' . $tp['name'] . '</span>'
                . '<span class="badge rounded-pill flex-shrink-0" style="background:rgba(' . $rgb . ',.12);color:rgb(' . $rgb . ');font-size:10px" title="' . h($vs5_views_label) . '">' . pg_format_number($tp['cnt'], 0) . ' <span style="opacity:.75;font-weight:400">' . h($vs5_views_label) . '</span></span>'
                . '</div>';
        };

        // While the historical summaries are still being built,
        // say so. Older periods legitimately read low until the
        // backfill finishes, and an unexplained dip in a traffic
        // chart is the kind of thing that gets investigated as a
        // real problem.
        $vs5_progress = '';
        if (is_array($vs5_backfill) && empty($vs5_backfill['done']) && $vs5_backfill['max_id'] > 0) {
            $vs5_pct = floor(($vs5_backfill['cursor'] / $vs5_backfill['max_id']) * 100);
            $vs5_progress = '
            <div class="px-3 pt-2">
              <div class="alert alert-info py-1 px-2 mb-0 d-flex align-items-center gap-2" style="font-size:11px">
                <i class="bi bi-hourglass-split flex-shrink-0"></i>
                <span class="flex-grow-1">' . lang('Historical visitor summaries are still being built. Figures for earlier periods will be incomplete until this finishes.') . '</span>
                <span class="badge bg-info-subtle text-info-emphasis flex-shrink-0">' . (int) $vs5_pct . '%</span>
              </div>
            </div>';
        }

        // ── Assemble output ───────────────────────────────────────────────────
        //
        // One chart, not three. The three panels each got a third
        // of the card for a day that has 24 points in it, and the
        // period buttons swapped the series rather than showing
        // both, so seeing whether today beat yesterday meant
        // holding the other shape in your head. Here they share an
        // axis: today drawn, yesterday dashed behind it.
        //
        // The strip underneath promotes a period into the chart
        // when clicked, so all of the same series are still
        // reachable -- they are just not all drawn at postage
        // stamp size at once.
        $vs5_day_pct = $vs5_pct_change($kpi_today, $kpi_yesterday_same);
        $vs5_week_pct = $vs5_pct_change($kpi_tw, $kpi_lw);
        $vs5_month_pct = $vs5_pct_change($kpi_tm, $kpi_pm_prev_same);

        $vs5_badge = function ($pct) {
            if ($pct === null) {
                return '';
            }
            $direction = ($pct >= 0) ? 'up' : 'down';
            $arrow = ($pct >= 0) ? '&#9650;' : '&#9660;';
            return '<span class="pg-vs-pill ' . $direction . '">' . $arrow . ' %' . abs($pct) . '</span>';
        };

        // Today's busiest page, under the period's. Two different
        // questions -- "what carries this site" and "what is
        // happening right now" -- and the second one was not
        // answerable anywhere on the dashboard.
        $vs5_today_top = '';
        if ($tp_today) {
            $vs5_today_top = '<span class="pg-vs-today">' . lang('Today') . ': <b>'
                . $tp_today['name'] . '</b> &middot; '
                . pg_format_number($tp_today['cnt'], 0) . ' ' . h($vs5_views_label) . '</span>';
        }

        $vs5_cell = function ($key, $label, $value, $pct_badge, $active = false) {
            return '<button type="button" class="pg-vs-cell' . ($active ? ' is-active' : '') . '" data-vs5="' . $key . '">'
                . '<span class="pg-vs-cell-label">' . $label . '</span>'
                . '<span class="pg-vs-cell-value">' . $value . ' ' . $pct_badge . '</span>'
                // Chart.js sizes a responsive canvas from its
                // parent, so the box owns the dimensions and the
                // canvas fills it. Sizing the canvas directly made
                // the two fight and drew the line as a smear.
                . '<span class="pg-vs-spark"><canvas data-spark="' . $key . '"></canvas></span>'
                . '</button>';
        };

        $output_data = '
        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
          ' . $vs5_progress . '
          <div class="pg-vs">

            <div class="pg-vs-head">
              <div>
                <div class="pg-vs-eyebrow" id="vs5_eyebrow">' . lang('Daily Traffic') . '</div>
                <div class="pg-vs-big"><span id="vs5_kpi">' . pg_format_number($kpi_today, 0) . '</span><span class="pg-vs-unit">' . lang('visitors') . '</span></div>
                <div class="pg-vs-compare" id="vs5_compare">
                  ' . $vs5_badge($vs5_day_pct) . '
                  <span class="pg-vs-cmp">' . lang(array(
                      'string' => 'by this time yesterday {var:1}',
                      'vars' => '<b>' . pg_format_number($kpi_yesterday_same, 0) . '</b>',
                  )) . '</span>
                </div>
                <div class="pg-vs-sub" id="vs5_sub">' . lang(array(
                    'string' => 'all of yesterday {var:1}',
                    'vars' => pg_format_number($kpi_yesterday, 0),
                )) . '</div>
              </div>
              <div class="pg-vs-legend" id="vs5_legend">
                <span class="pg-vs-key"><i class="pg-vs-sw now"></i><span id="vs5_leg_now">' . lang('Today') . '</span></span>
                <span class="pg-vs-key"><i class="pg-vs-sw prev"></i><span id="vs5_leg_prev">' . lang('Yesterday') . '</span></span>
              </div>
            </div>

            <div class="pg-vs-chart">
              ' . (($kpi_today > 0 || $kpi_yesterday > 0 || $has_tw || $has_tm) ? '<canvas id="vs5_canvas"></canvas>' : $vs5_no_data) . '
            </div>

            <div class="pg-vs-strip">
              ' . $vs5_cell('w', lang('This Week'), pg_format_number($kpi_tw, 0), $vs5_badge($vs5_week_pct)) . '
              ' . $vs5_cell('m', lang('This Month'), pg_format_number($kpi_tm, 0), $vs5_badge($vs5_month_pct)) . '
              ' . $vs5_cell('y', lang('Last 12 Months'), pg_format_number($kpi_pm, 0), '') . '
              <div class="pg-vs-cell pg-vs-top">
                <span class="pg-vs-cell-label">' . lang('Most Visited') . '</span>
                ' . ($tp_tm
                    ? '<span class="pg-vs-page">' . $tp_tm['name'] . '</span>'
                      . '<span class="pg-vs-views">' . pg_format_number($tp_tm['cnt'], 0) . ' ' . h($vs5_views_label) . '</span>'
                    : '<span class="pg-vs-views">&mdash;</span>') . '
                ' . $vs5_today_top . '
              </div>
            </div>

          </div>
        </div>

        <script>(function(){
          var tc = getPreferredThemeColor();
          var fmtN = function(n){ return Number(n).toLocaleString(); };
          var VIEWS_LABEL = ' . json_encode($vs5_views_label) . ';
          var L = {
            visitors: ' . json_encode(lang('New Visitors')) . ',
            views:    ' . json_encode(lang('Page Views')) . ',
            daily:    ' . json_encode(lang('Daily Traffic')) . ',
            weekly:   ' . json_encode(lang('Weekly Traffic')) . ',
            monthly:  ' . json_encode(lang('Monthly Traffic')) . ',
            yearly:   ' . json_encode(lang('Last 12 Months')) . ',
            today:    ' . json_encode(lang('Today')) . ',
            yesterday:' . json_encode(lang('Yesterday')) . ',
            thisWeek: ' . json_encode(lang('This Week')) . ',
            lastWeek: ' . json_encode(lang('Last Week')) . ',
            thisMonth:' . json_encode(lang('This Month')) . ',
            prevMonth:' . json_encode(lang('Previous Month')) . ',
            total:    ' . json_encode(lang('Total')) . '
          };

          // The day view has wording of its own -- "by this time
          // yesterday" is the whole point of the comparison and
          // "Yesterday: 214" does not say it. Sent as templates so
          // the sentence stays in tr.json rather than being
          // assembled from fragments here, which is what makes a
          // translation impossible to word naturally.
          var T = {
            byThisTime:   ' . json_encode(lang(array('string' => 'by this time yesterday {var:1}', 'vars' => '%N%'))) . ',
            allYesterday: ' . json_encode(lang(array('string' => 'all of yesterday {var:1}', 'vars' => '%N%'))) . '
          };

          // Each period carries both series against one label set,
          // which is what lets them be drawn on top of each other.
          // "prev" of null means there is nothing to compare with,
          // and the legend drops its second key accordingly.
          var P = {
            d: { eyebrow:L.daily,   type:"line", labels:[' . $h_yest_labels_js . '],
                 now:{ label:L.today,     data:[' . $h_today_full_js . '], views:[' . $h_today_views_full_js . '], pages:' . json_encode(array_values($h_today_pages)) . ' },
                 prev:{ label:L.yesterday, data:[' . $h_yest_js . '],       views:[' . $h_yest_views_js . '],      pages:' . json_encode(array_values($h_yest_pages)) . ' },
                 kpi:' . $kpi_today . ', cmp:' . $kpi_yesterday_same . ', full:' . $kpi_yesterday . ', nowIndex:' . $current_hour . ' },
            w: { eyebrow:L.weekly,  type:"line", labels:[' . $w_tw_labels . '],
                 now:{ label:L.thisWeek, data:[' . $w_tw_data . '] },
                 prev:{ label:L.lastWeek, data:[' . $w_lw_data . '] },
                 kpi:' . $kpi_tw . ', cmp:' . $kpi_lw . ', full:null, nowIndex:null },
            m: { eyebrow:L.monthly, type:"line", labels:[' . $m_tm_labels . '],
                 now:{ label:L.thisMonth, data:[' . $m_tmd_data . '] },
                 prev:{ label:L.prevMonth, data:[' . $m_pmd_data . '] },
                 kpi:' . $kpi_tm . ', cmp:' . $kpi_pm_prev_same . ', full:null, nowIndex:' . ($today_day - 1) . ' },
            y: { eyebrow:L.yearly,  type:"bar",  labels:[' . $m_pm_labels . '],
                 now:{ label:L.total, data:[' . $m_pm_data . '] },
                 prev:null,
                 kpi:' . $kpi_pm . ', cmp:null, full:null, nowIndex:null }
          };

          // The line simply stopping is ambiguous -- it reads as
          // traffic falling to nothing rather than as the day not
          // being over. This shades what has not happened yet and
          // rules off where the data ends.
          var nowMarker = {
            id: "vs5NowMarker",
            afterDatasetsDraw: function (c) {
              var p = P[mode];
              if (p.nowIndex === null || p.nowIndex >= p.labels.length - 1) return;
              var x = c.scales.x.getPixelForValue(p.nowIndex);
              var a = c.chartArea;
              var ctx = c.ctx;
              ctx.save();
              ctx.fillStyle = "rgba(128,128,128,.07)";
              ctx.fillRect(x, a.top, a.right - x, a.bottom - a.top);
              ctx.setLineDash([2, 3]);
              ctx.strokeStyle = "rgba(128,128,128,.5)";
              ctx.lineWidth = 1;
              ctx.beginPath();
              ctx.moveTo(x, a.top);
              ctx.lineTo(x, a.bottom);
              ctx.stroke();
              ctx.restore();
            }
          };

          var NOW_RGB  = "59,130,246";
          var PREV_RGB = "148,163,184";

          var el = document.getElementById("vs5_canvas");
          var chart = null;
          var mode = "d";

          function datasets(p) {
            var out = [];
            // Previous first so the current series draws over it.
            if (p.prev) {
              out.push({
                label: p.prev.label, data: p.prev.data,
                borderColor: "rgba("+PREV_RGB+",.9)", backgroundColor: "rgba("+PREV_RGB+",.10)",
                borderWidth: 1.6, borderDash: p.type === "line" ? [4,4] : [],
                fill: false, tension: 0.35, pointRadius: 0, pointHoverRadius: 4,
                spanGaps: false
              });
            }
            out.push({
              label: p.now.label, data: p.now.data,
              borderColor: "rgba("+NOW_RGB+",1)", backgroundColor: "rgba("+NOW_RGB+",.14)",
              borderWidth: 2.2, fill: p.type === "line",
              tension: 0.35, pointRadius: 0, pointHoverRadius: 5,
              pointBackgroundColor: "rgba("+NOW_RGB+",1)",
              borderRadius: 3, borderSkipped: false,
              // false, so the hours that have not happened end the
              // line where the data ends instead of jumping the gap
              // to nothing.
              spanGaps: false
            });
            return out;
          }

          function build() {
            if (!el) return;
            var p = P[mode];
            if (chart) { chart.destroy(); }
            chart = new Chart(el.getContext("2d"), {
              type: p.type,
              data: { labels: p.labels, datasets: datasets(p) },
              plugins: [nowMarker],
              options: {
                animation: { duration: 220 },
                plugins: {
                  legend: { display: false },
                  tooltip: {
                    callbacks: {
                      label: function(ctx) {
                        return ctx.dataset.label + " · " + L.visitors + ": " + fmtN(ctx.parsed.y);
                      },
                      afterLabel: function(ctx) {
                        // Only the day view carries per-hour detail;
                        // the others have nothing to add and an
                        // empty line in a tooltip looks like a bug.
                        var p = P[mode];
                        var series = (p.prev && ctx.datasetIndex === 0) ? p.prev : p.now;
                        if (!series.views && !series.pages) return "";
                        var out = [];
                        var vw = series.views ? series.views[ctx.dataIndex] : null;
                        if (vw !== null && vw !== undefined) {
                          out.push(L.views + ": " + fmtN(vw));
                        }
                        var pg = series.pages ? series.pages[ctx.dataIndex] : null;
                        if (pg) { out.push("↳ " + pg.name + "  ·  " + fmtN(pg.cnt)); }
                        return out.join("\n");
                      }
                    }
                  }
                },
                responsive: true, maintainAspectRatio: false,
                scales: {
                  x: { ticks:{ color:tc, font:{size:9}, maxRotation:0, autoSkip:true, maxTicksLimit:8 }, grid:{ display:false } },
                  y: { ticks:{ color:tc, precision:0, font:{size:9} }, beginAtZero:true, grid:{ color:"rgba(128,128,128,.1)" } }
                },
                interaction: { mode:"index", intersect:false }
              }
            });
          }

          function head() {
            var p = P[mode];
            document.getElementById("vs5_eyebrow").textContent = p.eyebrow;
            document.getElementById("vs5_kpi").textContent = fmtN(p.kpi);
            document.getElementById("vs5_leg_now").textContent = p.now.label;

            var legend = document.getElementById("vs5_legend");
            legend.classList.toggle("no-prev", !p.prev);
            if (p.prev) { document.getElementById("vs5_leg_prev").textContent = p.prev.label; }

            var compare = document.getElementById("vs5_compare");
            var sub = document.getElementById("vs5_sub");
            if (p.cmp === null) { compare.innerHTML = ""; sub.textContent = ""; return; }

            var pct = (p.cmp > 0) ? Math.round(((p.kpi - p.cmp) / p.cmp) * 100) : null;
            var pill = "";
            if (pct !== null) {
              pill = \'<span class="pg-vs-pill \' + (pct >= 0 ? "up" : "down") + \'">\'
                   + (pct >= 0 ? "▲" : "▼") + " %" + Math.abs(pct) + "</span>";
            }
            var text = (mode === "d")
              ? T.byThisTime.replace("%N%", "<b>" + fmtN(p.cmp) + "</b>")
              : (p.prev.label + ": <b>" + fmtN(p.cmp) + "</b>");
            compare.innerHTML = pill + \'<span class="pg-vs-cmp">\' + text + "</span>";
            sub.innerHTML = (p.full !== null) ? T.allYesterday.replace("%N%", fmtN(p.full)) : "";
          }

          // Sparklines. Same series the chart would draw, at the
          // size a cell can hold: no axes, no interaction, just the
          // shape, because the number beside it is the reading.
          function sparks() {
            var colors = { w:"16,185,129", m:"139,92,246", y:"245,158,11" };
            document.querySelectorAll("[data-spark]").forEach(function(c){
              var key = c.getAttribute("data-spark");
              var p = P[key];
              if (!p) return;
              new Chart(c.getContext("2d"), {
                type: p.type,
                data: { labels: p.labels, datasets: [{
                  data: p.now.data,
                  borderColor: "rgba("+colors[key]+",1)",
                  backgroundColor: "rgba("+colors[key]+",.18)",
                  borderWidth: 1.6, fill: p.type === "line", tension: 0.35,
                  pointRadius: 0, spanGaps: false, borderRadius: 2
                }]},
                options: {
                  animation: false, responsive: true, maintainAspectRatio: false,
                  plugins: { legend:{display:false}, tooltip:{enabled:false} },
                  scales: { x:{display:false}, y:{display:false, beginAtZero:true} },
                  events: []
                }
              });
            });
          }

          document.querySelectorAll("[data-vs5]").forEach(function(btn){
            btn.addEventListener("click", function(){
              var next = btn.getAttribute("data-vs5");
              // Clicking the period already showing returns to the
              // day, so the strip toggles rather than trapping the
              // reader in a period with no way back.
              mode = (mode === next) ? "d" : next;
              document.querySelectorAll("[data-vs5]").forEach(function(b){
                b.classList.toggle("is-active", b.getAttribute("data-vs5") === mode);
              });
              build(); head();
            });
          });

          build(); head(); sparks();
        })();</script>';

        $response = array(
            'status' => 'success',
            'message' => lang('Data Received successfully.'),
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
