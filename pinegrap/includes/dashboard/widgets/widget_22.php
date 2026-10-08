<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 22 - Firewall, threat digest: the second half of the Firewall card.
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

function pg_dashboard_widget_22($request, $user, $waf_panel = '')
{
    // ── Firewall: threat digest ─────────────────────────────
    //
    // Companion to widget 21, deliberately a different question.
    // 21 answers "what happened, most recently"; this answers
    // "who is generating the load", ranked. On a normal site the
    // top of this list is four or five commercial crawlers, and
    // seeing them ranked is what tells an operator whether
    // turning blocking on is worth it.
    if ($user['role'] < 3) {

        $td_available = (mysqli_num_rows(mysqli_query(db::$con, "SHOW TABLES LIKE 'waf_log'")) > 0);

        if (!$td_available) {
            $output_data = '
            <div class="card-body d-flex align-items-center justify-content-center text-center">
                <div>
                    <i class="bi bi-database-exclamation d-block mb-2" style="font-size:22px;opacity:.4"></i>
                    <p class="text-muted mb-0" style="font-size:12px">' . lang('The firewall tables do not exist yet. Please run the software upgrade to create them.') . '</p>
                </div>
            </div>';

            $response = array('status' => 'success', 'message' => 'Action Success', 'data' => $output_data);
            return $response;
        }

        $td_mode = function_exists('waf_mode') ? waf_mode() : 'off';
        $td_day_ago = time() - 86400;
        $td_threshold = function_exists('waf_threshold') ? waf_threshold() : 10;

        if ($td_threshold < 1) {
            $td_threshold = 10;
        }

        $td_totals = mysqli_fetch_assoc(mysqli_query(
            db::$con,
            "SELECT
                COALESCE(SUM(CASE WHEN action IN ('block','rate','ban') THEN hit_count ELSE 0 END), 0) AS blocked,
                COALESCE(SUM(CASE WHEN action IN ('would-block','would-rate') THEN hit_count ELSE 0 END), 0) AS would_block,
                COUNT(DISTINCT ip_address) AS addresses
             FROM waf_log
             WHERE log_timestamp >= " . (int) $td_day_ago
        ));

        if ($td_mode === 'monitor') {
            $td_headline = (int) $td_totals['would_block'];
            $td_headline_label = lang('Would block');
            $td_headline_color = 'warning';
        } else {
            $td_headline = (int) $td_totals['blocked'];
            $td_headline_label = lang('Blocked');
            $td_headline_color = 'danger';
        }

        $td_category_names = array(
            'sqli'     => lang('SQL Injection'),
            'xss'      => lang('Cross-site Scripting'),
            'lfi'      => lang('Path Traversal'),
            'rce'      => lang('Command Injection'),
            'protocol' => lang('Protocol Abuse'),
            'bot'      => lang('Bots'),
            'tool'     => lang('Scanners'),
            'rate'     => lang('Rate Limit'),
            'iplist'   => lang('IP List'),
            'ban'      => lang('Bans'),
        );

        // Grouping key depends on the category. For bots and
        // scanner tooling the matched value IS the useful name
        // ("bytespider", "dotbot"), and collapsing those into one
        // "Bots" bar would throw away the only detail worth
        // showing. Everything else groups by category, because
        // there the matched value is a payload fragment that
        // differs on every request.
        $td_result = mysqli_query(
            db::$con,
            "SELECT
                CASE WHEN category IN ('bot','tool') AND matched <> ''
                     THEN matched ELSE category END AS source,
                category,
                SUM(hit_count) AS hits,
                COUNT(DISTINCT ip_address) AS ips,
                MAX(score) AS top_score
             FROM waf_log
             WHERE log_timestamp >= " . (int) $td_day_ago . "
             GROUP BY source, category
             ORDER BY hits DESC
             LIMIT 6"
        );

        $td_sources = $td_result ? mysqli_fetch_items($td_result) : array();

        // Scale the bars against the busiest source, not against
        // the grand total: with one dominant crawler every other
        // bar would round to nothing.
        $td_peak = 0;

        foreach ($td_sources as $td_source) {
            if ((int) $td_source['hits'] > $td_peak) {
                $td_peak = (int) $td_source['hits'];
            }
        }

        $td_rows = '';

        foreach ($td_sources as $td_source) {
            $td_hits = (int) $td_source['hits'];
            $td_label = $td_source['source'];

            if (in_array($td_source['category'], array('bot', 'tool'), true)) {
                // Stored bot tokens are lower case; title-case them
                // for display, but leave anything that already has
                // capitals alone so "(forged)" style notes survive.
                if ($td_label === mb_strtolower($td_label, 'UTF-8')) {
                    $td_label = mb_convert_case($td_label, MB_CASE_TITLE, 'UTF-8');
                }
            } elseif (isset($td_category_names[$td_label])) {
                $td_label = $td_category_names[$td_label];
            }

            $td_width = ($td_peak > 0) ? (int) round(100 * $td_hits / $td_peak) : 0;

            if ($td_width < 3) {
                $td_width = 3;
            }

            if ((int) $td_source['top_score'] >= $td_threshold) {
                $td_bar = 'bg-danger';
                $td_text = ' text-danger';
            } elseif ((int) $td_source['top_score'] >= ($td_threshold / 2)) {
                $td_bar = 'bg-warning';
                $td_text = '';
            } else {
                $td_bar = 'bg-secondary';
                $td_text = '';
            }

            $td_rows .= '
            <div class="mb-2">
                <div class="d-flex align-items-center justify-content-between" style="gap:6px">
                    <span class="text-truncate' . $td_text . '" style="font-size:12px" title="' . h($td_source['category']) . '">' . h($td_label) . '</span>
                    <span class="text-muted flex-shrink-0" style="font-size:11px">' . pg_format_number($td_hits, 0) . '</span>
                </div>
                <div class="progress mt-1" style="height:4px;background:rgba(0,0,0,.06)" title="' . lang(array(
                    'string' => '{var:1} address{suffix:1}',
                    'vars'   => pg_format_number((int) $td_source['ips'], 0),
                    'suffix' => ((int) $td_source['ips'] == 1 ? '' : 'es'),
                )) . '">
                    <div class="progress-bar ' . $td_bar . '" style="width:' . $td_width . '%"></div>
                </div>
            </div>';
        }

        if ($td_rows === '') {
            $td_rows = '
            <div class="text-center py-4">
                <i class="bi bi-shield-check d-block mb-2" style="font-size:22px;opacity:.35"></i>
                <p class="text-muted mb-0" style="font-size:12px">' . lang('No firewall events were recorded in this period.') . '</p>
            </div>';
        }

        $td_footer = '';

        if ($user['role'] < 3) {
            $td_footer = '
            <div class="card-footer border-0 bg-reset py-1 text-center">
                <a href="view_waf_log.php" class="text-decoration-none" style="font-size:11px">'
                . lang('Firewall Log') . ' <i class="bi bi-arrow-right-short"></i></a>
            </div>';
        }

        // No protection banner here. Widget 21 already carries it,
        // and the same reassurance twice on one dashboard reads as
        // filler rather than information. This widget answers a
        // different question — who is generating the load — and
        // the ranked list is the answer.
        // No totals row. The firewall half beside this one already
        // carries blocked, addresses and bans across the top, and the
        // same three figures twice on one card reads as a rendering
        // fault rather than as emphasis. This half answers the other
        // question - who is generating the load - so the ranked list
        // starts straight away.
        $td_panel = '
            <div class="pg-split-half d-flex flex-column">
                <div class="px-3 pt-2 pb-1 text-muted" style="font-size:11px">' . lang('Top sources') . '</div>
                <div class="px-3 pb-2">' . $td_rows . '</div>
            </div>';

        // $waf_panel is filled only when widget 21 hands its event
        // half over, which is how the dashboard asks for this card. A
        // direct request for widget 22 leaves it empty and still
        // answers with the threat half alone rather than an error.
        $output_data = '
            <div class="card-body p-0 pg-split">'
                . $waf_panel
                . $td_panel . '
            </div>' . $td_footer;

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
