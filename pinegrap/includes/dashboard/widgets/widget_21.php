<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 21 - Firewall, event feed: the first half of the Firewall card.
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

function pg_dashboard_widget_21($request, $user)
{
    // ── Firewall: event feed ────────────────────────────────
    // Staff roles only (administrator, manager, designer).
    // Contributors are excluded — firewall events expose raw
    // attack payloads and visitor addresses.
    if ($user['role'] < 3) {

        // Piggyback for the AI bot range lists, the same ride the
        // visitor backfill takes on the dashboard: staff traffic
        // keeps them fresh on sites where the cron job was never
        // switched on. Throttled inside to one attempt per six
        // hours, so this is a no-op on almost every load.
        if (function_exists('pg_waf_refresh_ai_ranges')) {
            pg_waf_refresh_ai_ranges();
        }

        $waf_available = (mysqli_num_rows(mysqli_query(db::$con, "SHOW TABLES LIKE 'waf_log'")) > 0);
        $waf_current_mode = function_exists('waf_mode') ? waf_mode() : 'off';

        // Schema not upgraded yet — say so rather than render an
        // empty widget the operator cannot interpret.
        if (!$waf_available) {
            $output_data = '
            <div class="card-body d-flex align-items-center justify-content-center text-center">
                <div>
                    <i class="bi bi-database-exclamation d-block mb-2" style="font-size:22px;opacity:.4"></i>
                    <p class="text-muted mb-0" style="font-size:12px">' . lang('The firewall tables do not exist yet. Please run the software upgrade to create them.') . '</p>
                </div>
            </div>';

            $response = array(
                'status'  => 'success',
                'message' => 'Action Success',
                'data'    => $output_data,
            );
            return $response;
        }

        $waf_day_ago = time() - 86400;

        // SUM(hit_count), never COUNT(*): identical events are
        // folded into five-minute buckets, so counting rows would
        // report a flood of ten thousand requests as one event.
        $waf_totals = mysqli_fetch_assoc(mysqli_query(
            db::$con,
            "SELECT
                COALESCE(SUM(hit_count), 0) AS requests,
                COALESCE(SUM(CASE WHEN action IN ('block','rate','ban') THEN hit_count ELSE 0 END), 0) AS blocked,
                COALESCE(SUM(CASE WHEN action IN ('would-block','would-rate') THEN hit_count ELSE 0 END), 0) AS would_block,
                COUNT(DISTINCT ip_address) AS addresses
             FROM waf_log
             WHERE log_timestamp >= " . (int) $waf_day_ago
        ));

        $waf_requests    = (int) $waf_totals['requests'];
        $waf_blocked     = (int) $waf_totals['blocked'];
        $waf_would_block = (int) $waf_totals['would_block'];
        $waf_addresses   = (int) $waf_totals['addresses'];

        // Active automatic bans, if the columns are present.
        $waf_active_bans = 0;

        if (function_exists('waf_table_has_column')
            && waf_table_has_column('banned_ip_addresses', 'source')
        ) {
            $waf_active_bans = (int) db_value(
                "SELECT COUNT(*) FROM banned_ip_addresses
                 WHERE source = 'auto'
                   AND (expires_at = 0 OR expires_at > " . time() . ")"
            );
        }

        // Mode strip. Monitor is the state that needs explaining:
        // the numbers below it are what blocking WOULD have
        // stopped, not what it did stop.
        if ($waf_current_mode === 'off') {
            $waf_mode_class = 'secondary';
            $waf_mode_icon  = 'bi-shield-slash';
            $waf_mode_label = lang('Off');
        } elseif ($waf_current_mode === 'monitor') {
            $waf_mode_class = 'info';
            $waf_mode_icon  = 'bi-eye';
            $waf_mode_label = lang('Monitor');
        } else {
            $waf_mode_class = 'success';
            $waf_mode_icon  = 'bi-shield-check';
            $waf_mode_label = lang('Block');
        }

        // The headline number depends on the mode: in monitor
        // mode nothing was actually blocked, so leading with
        // "0 blocked" would read as "no attacks".
        if ($waf_current_mode === 'monitor') {
            $waf_headline_value = $waf_would_block;
            $waf_headline_label = lang('Would block');
            $waf_headline_color = 'warning';
        } else {
            $waf_headline_value = $waf_blocked;
            $waf_headline_label = lang('Blocked');
            $waf_headline_color = 'danger';
        }

        // Recent events: action, requests, address, score.
        //
        // The score is the anomaly score — the sum of every rule
        // the request matched. A single unambiguous rule (a UNION
        // SELECT, a traversal sequence) scores 10 and blocks on
        // its own; deliberately weak rules score 4-6 and have to
        // corroborate each other. The blocking line is the
        // sensitivity threshold, so the bar is drawn as a share
        // of THAT rather than of some arbitrary maximum: a full
        // bar means "this request crossed the line", which is the
        // only reading of the number an operator actually needs.
        $waf_threshold_value = function_exists('waf_threshold') ? waf_threshold() : 10;

        if ($waf_threshold_value < 1) {
            $waf_threshold_value = 10;
        }

        $waf_action_badges = array(
            'block'       => array('danger',            lang('Blocked')),
            'rate'        => array('danger',            lang('Rate limited')),
            'ban'         => array('dark',              lang('Banned')),
            'would-block' => array('warning text-dark', lang('Would block')),
            'would-rate'  => array('warning text-dark', lang('Would rate limit')),
            'log'         => array('secondary',         lang('Recorded')),
        );

        $waf_rows = '';

        $waf_event_result = mysqli_query(
            db::$con,
            "SELECT ip_address, action, rule_id, score, hit_count, last_seen,
                    target, matched
             FROM waf_log
             WHERE log_timestamp >= " . (int) $waf_day_ago . "
             ORDER BY last_seen DESC, id DESC
             LIMIT 8"
        );

        if ($waf_event_result) {
            while ($waf_event = mysqli_fetch_assoc($waf_event_result)) {

                $waf_event_action = $waf_event['action'];

                $waf_badge = isset($waf_action_badges[$waf_event_action])
                    ? $waf_action_badges[$waf_event_action]
                    : array('secondary', $waf_event_action);

                $waf_event_score = (int) $waf_event['score'];
                $waf_event_hits  = (int) $waf_event['hit_count'];

                // Capped at 100: a score of 30 is not three times
                // more blocked than a score of 10.
                $waf_score_percent = (int) round(100 * $waf_event_score / $waf_threshold_value);

                if ($waf_score_percent > 100) {
                    $waf_score_percent = 100;
                }

                if ($waf_score_percent < 0) {
                    $waf_score_percent = 0;
                }

                // Colour encodes the same decision as the bar
                // length, so the row reads at a glance.
                if ($waf_event_score >= $waf_threshold_value) {
                    $waf_bar_class = 'bg-danger';
                } elseif ($waf_event_score >= ($waf_threshold_value / 2)) {
                    $waf_bar_class = 'bg-warning';
                } else {
                    $waf_bar_class = 'bg-secondary';
                }

                // Not every event has a client address — a rule
                // can fire on the user agent or on a script name
                // with no address to report. Leaving the cell
                // blank made the widget look broken, so the
                // matched target stands in for it.
                if ($waf_event['ip_address'] !== '') {
                    $waf_identity = '<span class="font-monospace">' . h($waf_event['ip_address']) . '</span>';
                } elseif ($waf_event['target'] !== '') {
                    $waf_identity = '<span class="fst-italic">' . h($waf_event['target']) . '</span>';
                } else {
                    $waf_identity = '<span class="fst-italic">' . lang('no address') . '</span>';
                }

                // What the rule actually caught: the crawler name,
                // the payload fragment, the limit that was passed.
                // Without it a row says something was blocked but
                // never what.
                $waf_evidence = ($waf_event['matched'] !== '')
                    ? $waf_event['matched']
                    : $waf_event['rule_id'];

                $waf_rows .= '
                <div class="px-2 py-2 border-bottom">
                    <div class="d-flex align-items-center justify-content-between" style="gap:6px">
                        <span class="badge bg-' . h($waf_badge[0]) . ' flex-shrink-0" style="font-size:9px">' . h($waf_badge[1]) . '</span>
                        <span class="text-truncate text-muted" style="font-size:11px" title="' . h($waf_event['rule_id']) . '">' . $waf_identity . '</span>
                        <span class="badge rounded-pill flex-shrink-0" style="background:rgba(0,0,0,.06);color:inherit;font-size:10px" title="' . lang('Requests') . '">&times;' . pg_format_number($waf_event_hits, 0) . '</span>
                    </div>
                    <div class="d-flex align-items-center mt-1" style="gap:6px">
                        <span class="text-truncate text-muted flex-shrink-0" style="font-size:10px;max-width:45%" title="' . h($waf_event['target']) . '">' . h($waf_evidence) . '</span>
                        <div class="progress flex-fill" style="height:4px;background:rgba(0,0,0,.06)" title="' . lang('Score') . ': ' . $waf_event_score . ' / ' . (int) $waf_threshold_value . '">
                            <div class="progress-bar ' . $waf_bar_class . '" style="width:' . $waf_score_percent . '%"></div>
                        </div>
                        <span class="text-muted flex-shrink-0" style="font-size:10px;min-width:1.4rem;text-align:right">' . $waf_event_score . '</span>
                    </div>
                </div>';
            }
        }

        // Whether the feed had anything in it, asked before the
        // empty state takes its place. The mode strip below needs
        // to know: an off firewall wants the same nudge either way,
        // but where it goes depends on whether there is a list to
        // put it under.
        $waf_had_rows = ($waf_rows !== '');

        // Off, and nothing recorded at all. Both halves of this card
        // read waf_log, so an empty event feed means an empty threat
        // digest too -- there is no second panel to show and no
        // figures to put above it.
        //
        // What the card was drawing instead: a mode strip, then
        // Blocked 0 / Addresses 0 / Bans 0, then the message, then a
        // divider, then a second empty panel, then a link to a log
        // with nothing in it. Every one of those says the same thing
        // the badge already said, and three zeros under an Off badge
        // are not a measurement -- nothing counted them.
        //
        // So the card collapses to the one thing worth saying and
        // the one thing worth doing.
        if (($waf_current_mode === 'off') && (!$waf_had_rows)) {

            $output_data = '
            <div class="card-body p-0 d-flex flex-column">'
                . pg_widget_empty(
                    'bi-shield-slash',
                    lang('The firewall is off, so nothing is being watched or recorded.'),
                    '',
                    lang('Turn on the firewall'),
                    OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_link('firewall', 'pgset-waf')) . '
            </div>';

            $response = array(
                'status' => 'success',
                'message' => 'Action Success',
                'data' => $output_data,
            );
            return $response;
        }

        if ($waf_rows === '') {

            // An empty feed means two different things and the card
            // has to say which. With the firewall on, nothing
            // happened -- that is the good outcome and the card
            // reports it. With the firewall off, nothing was
            // WATCHING, and an empty feed under a grey "Off" badge
            // reads as the quiet one unless the card says
            // otherwise. So the off state names the cause and
            // offers the switch, rather than leaving the operator
            // to work out that the reassuring empty list is the
            // symptom.
            // Only reachable with the firewall on: off with an
            // empty feed returned above.
            $waf_rows = pg_widget_empty(
                'bi-shield-check',
                lang('No firewall events were recorded in this period.'),
                'good');
        }

        // Reassurance, but only when it is true.
        //
        // The claim is made ONLY in blocking mode. In Monitor the
        // firewall watches and lets everything through, and telling
        // an operator they are protected while nothing is being
        // stopped is the kind of false comfort that stops them
        // finishing the setup. Off says nothing at all.
        $waf_shield = '';

        if ($waf_current_mode === 'block') {
            $waf_shield = '
            <div class="d-flex align-items-center px-2 py-2 border-bottom" style="gap:8px">
                <i class="bi bi-shield-fill-check text-success" style="font-size:20px"></i>
                <div class="text-success" style="font-size:12px;line-height:1.25">'
                    . lang('Your website is protected against threats.')
                . '</div>
            </div>';
        }

        // Off with events to show: the empty state is not on
        // screen to carry the nudge, so it rides the mode strip
        // instead -- right beside the badge that says Off, which is
        // the thing it answers. Off with nothing to show puts it in
        // the empty state instead, so only ever one of the two.
        $waf_turn_on = '';

        if (($waf_current_mode === 'off') && ($waf_had_rows)) {

            $waf_turn_on = '<a class="btn btn-sm btn-outline-secondary position-relative py-0 px-2" style="font-size:10px" href="'
                . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_link('firewall', 'pgset-waf') . '">'
                . lang('Turn on') . '</a>';
        }

        $waf_panel = '
            <div class="pg-split-half d-flex flex-column">
                ' . $waf_shield . '
                <div class="d-flex align-items-center justify-content-between px-2 py-2 border-bottom">
                    <span class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill bg-' . h($waf_mode_class) . '-subtle text-' . h($waf_mode_class) . '-emphasis border border-' . h($waf_mode_class) . '-subtle" style="font-size:10px">
                            <i class="bi ' . h($waf_mode_icon) . ' me-1"></i>' . h($waf_mode_label) . '
                        </span>
                        ' . $waf_turn_on . '
                    </span>
                    <span class="text-muted" style="font-size:10px">' . lang('Last 24 hours') . '</span>
                </div>
                <div class="row g-0 text-center border-bottom">
                    <div class="col-4 py-2 border-end">
                        <div class="fw-semibold text-' . h($waf_headline_color) . '" style="font-size:17px">' . pg_format_number($waf_headline_value, 0) . '</div>
                        <div class="text-muted text-truncate" style="font-size:10px">' . h($waf_headline_label) . '</div>
                    </div>
                    <div class="col-4 py-2 border-end">
                        <div class="fw-semibold" style="font-size:17px">' . pg_format_number($waf_addresses, 0) . '</div>
                        <div class="text-muted text-truncate" style="font-size:10px">' . lang('Addresses') . '</div>
                    </div>
                    <div class="col-4 py-2">
                        <div class="fw-semibold" style="font-size:17px">' . pg_format_number($waf_active_bans, 0) . '</div>
                        <div class="text-muted text-truncate" style="font-size:10px">' . lang('Bans') . '</div>
                    </div>
                </div>
                ' . $waf_rows . '
            </div>';

        // Handed on to widget 22.
        //
        // The two used to be separate cards asking related questions
        // - what happened, and who is generating it - and reading one
        // without the other was half an answer. They are the two
        // halves of one card now: this widget builds the event half
        // and passes it to widget 22, which builds the threat half
        // and returns both. A direct request for widget 22 answers
        // with the threat half alone. Only one query pass either way;
        // nothing is computed twice.
        require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_22.php');
        return pg_dashboard_widget_22($request, $user, $waf_panel);
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
