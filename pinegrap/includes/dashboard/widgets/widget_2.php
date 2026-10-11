<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 2 - System Status: the health score and the actions an operator can take.
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

function pg_dashboard_widget_2($request, $user)
{
    // ── System status ───────────────────────────────────────
    //
    // Two panels across a double-width card. On the left one score
    // for "is this installation healthy", drawn as a gauge, over a
    // tile per reading. On the right the things an operator can
    // actually do about it, one to a line.
    //
    // The split is the point. This was a single grid in which
    // "SSL · OK" and "Cache · Clear" were the same shape --
    // a reading and a button drawn identically, four characters
    // wide. A reading is read; a job is pressed, and a job needs
    // room for a verb and for the sentence that says what pressing
    // it will do.
    //
    // Order on the right is by state, not by run order: whatever
    // has a problem is the first line. Anything that opens -- a
    // per-job run list, the answer from a sweep -- opens directly
    // under its own line and pushes the rest down, instead of in a
    // box at the foot of the card that several rows pointed at.
    //
    // The checks themselves are in functions.php and are cached
    // there for ten minutes -- several of them stat the filesystem
    // or open a socket. This widget only renders.
    //
    // Role gate matches the screens the rows link to: backups.php
    // and software_update.php both require manager or above.
    if ($user['role'] < 3) {

        $status = get_system_status_checks();

        $health_score = isset($status['score']) ? (int) $status['score'] : 0;
        $health_checks = isset($status['checks']) && is_array($status['checks'])
            ? $status['checks']
            : array();

        // Arc geometry. A half circle of r=52 about (70,70) --
        // the same shape and radius the performance widget draws,
        // so the two gauges on one dashboard read as one idea.
        // Circumference is 326.73 and half of it is 163.36; the
        // rest is the opening at the bottom, which is what leaves
        // room for the score to sit inside the arc rather than
        // under it.
        //
        // Drawn as a dashed circle rather than a path, so filling
        // it is one number instead of two arc endpoints in PHP.
        $health_arc = 163.36;

        // ── Colour band ─────────────────────────────────────
        //
        // Same gauge, same three-stop gradient, same glow. What
        // moves with the score is the hue: an arc that is the
        // identical violet at 12% and at 98% makes the reader work
        // the verdict out from the digits, and the colour is the
        // half that can be read across a room.
        //
        // Bands: red under 25, amber under 50, blue under 80,
        // green from 90. Eighty to ninety is the crossing between
        // the last two and is drawn as teal rather than lumped in
        // with either -- a site at 85 is neither "still wrong" nor
        // "finished".
        //
        // Each band is three stops of one family, light to dark,
        // plus one flat ink that the score, the caption and the
        // matrix behind them all take. The ink is an RGB triple
        // rather than a hex string because the matrix needs it at
        // several alphas.
        // ── Colour band, and where the gradient is laid ─────
        //
        // Four hues a band, and every band is a neon sweep in its
        // own right. What the score moves is the weight, not the
        // idea: the healthy end is cool -- lime into emerald into
        // cyan -- and the failing end is hot -- orange into rose
        // into violet. Deliberately not a traffic light. A flat
        // green arc and a flat red one would say what the number
        // already says, and would say it by throwing the gradient
        // away.
        //
        // All four sit at the same weight, and it matters. A pastel
        // first stop (#a7f3d0 was one) reads as washed-out white on
        // a dark card and a 600-weight last stop sinks into it, so
        // the bar came out bleached at one end and swallowed at the
        // other -- lightness doing the travelling instead of hue,
        // which is the one thing a gradient this small cannot
        // afford. Every stop is a 400: bright, saturated, and only
        // its hue different from its neighbour's.
        //
        // TWO sets of the four, because the same colours cannot
        // serve both themes. On a black card the arc has to be
        // bright to be seen; those same stops on a white one are
        // pastel, and a lime-into-mint sweep on white is barely a
        // sweep at all -- the four hues collapse into one wash.
        //
        // The light set is NOT the dark one darkened. Darkening
        // alone keeps the four hues as close together as they were
        // and merely makes them all deep, which on white reads as
        // one navy arc with a slight lean at each end. It is the
        // same journey travelled WIDER -- cyan to blue to violet to
        // fuchsia rather than cyan to sky to indigo to violet -- at
        // the 600/700 weights, where every stop clears three to one
        // against the page and no two neighbours are the same hue.
        //
        // Both are handed to CSS and the stylesheet picks; PHP has
        // no idea which theme it is rendering into.
        //
        // The ink is the flat colour the number takes, and it has
        // the same problem: a 32px amber number on white is under
        // three to one against the page.
        if ($health_score < 25) {
            $health_ink       = '236, 72, 153';
            $health_ink_text  = '190, 24, 93';
            $health_stops     = array('#fb923c', '#f43f5e', '#e879f9', '#a78bfa');
            $health_stops_lt  = array('#c2410c', '#be123c', '#a21caf', '#6d28d9');
        } elseif ($health_score < 50) {
            $health_ink       = '251, 146, 60';
            $health_ink_text  = '180, 83, 9';
            $health_stops     = array('#fde047', '#fb923c', '#fb7185', '#e879f9');
            $health_stops_lt  = array('#a16207', '#c2410c', '#be123c', '#9333ea');
        } elseif ($health_score < 80) {
            $health_ink       = '59, 130, 246';
            $health_ink_text  = '29, 78, 216';
            $health_stops     = array('#22d3ee', '#38bdf8', '#818cf8', '#c084fc');
            $health_stops_lt  = array('#0e7490', '#1d4ed8', '#6d28d9', '#a21caf');
        } elseif ($health_score < 90) {
            $health_ink       = '45, 212, 191';
            $health_ink_text  = '13, 148, 136';
            $health_stops     = array('#4ade80', '#2dd4bf', '#22d3ee', '#818cf8');
            $health_stops_lt  = array('#0d9488', '#0891b2', '#2563eb', '#6d28d9');
        } else {
            $health_ink       = '52, 211, 153';
            $health_ink_text  = '4, 120, 87';
            $health_stops     = array('#a3e635', '#34d399', '#22d3ee', '#818cf8');
            $health_stops_lt  = array('#65a30d', '#059669', '#0891b2', '#3730a3');
        }

        // The gradient is laid ALONG the drawn arc, not across the
        // circle's box. Across the box a site at 20% saw only the
        // first stop -- one flat colour, on exactly the card that
        // most needs to say something -- and even at 70% the last
        // hue never reached the bar. Anchored to the bar, the whole
        // sweep is on it at every score.
        //
        // Worked in the path's own space: an SVG circle starts at
        // three o'clock and runs clockwise, and the half turn in the
        // stylesheet is what puts the visible start at nine. So the
        // ends are computed here untransformed and rotate with
        // everything else.
        //
        // Clamped at a quarter turn: below it the two ends close on
        // each other, and a gradient whose axis has no length paints
        // as a single flat stop -- the very thing this prevents.
        $health_span  = max($health_score / 100, 0.25);
        $health_angle = M_PI * $health_span;
        $health_x2    = round(70 + (52 * cos($health_angle)), 2);
        $health_y2    = round(70 + (52 * sin($health_angle)), 2);

        // ── Where the stops go ──────────────────────────────
        //
        // Not evenly. A linear gradient changes colour evenly along
        // its AXIS, and the axis is the straight line between the
        // two ends of the arc -- so the arc is read by projecting
        // it onto that chord, and the projection is not even at
        // all. Near the ends of a half turn the arc runs almost
        // parallel to the chord and barely advances along it, so a
        // long stretch of bar gets a sliver of the gradient; at the
        // top it advances fastest and gets most of it. On a nearly
        // full arc that is exactly what you see: two flat legs and
        // every hue crammed into the crown.
        //
        // So each stop is placed at the axis position its own point
        // on the arc actually projects to. The four then arrive at
        // equal steps of ARC LENGTH, which is the thing being
        // looked at.
        $health_bx    = $health_x2 - 122;
        $health_by    = $health_y2 - 70;
        $health_len2  = ($health_bx * $health_bx) + ($health_by * $health_by);
        $health_marks = array();

        foreach (array(0, 1 / 3, 2 / 3, 1) as $health_u) {

            $health_t = $health_angle * $health_u;
            $health_qx = 70 + (52 * cos($health_t));
            $health_qy = 70 + (52 * sin($health_t));

            $health_o = ($health_len2 > 0)
                ? (((($health_qx - 122) * $health_bx) + (($health_qy - 70) * $health_by)) / $health_len2)
                : $health_u;

            if ($health_o < 0) { $health_o = 0; }
            if ($health_o > 1) { $health_o = 1; }

            $health_marks[] = round($health_o * 100, 2);
        }

        // The same axis mirrored through the centre, for the layer
        // that is written already turned. Rotating a point half a
        // turn about (70,70) is (140 - x, 140 - y).
        $health_mx2 = round(140 - $health_x2, 2);
        $health_my2 = round(140 - $health_y2, 2);

        // Everything the gauge is coloured from, in one place.
        $health_vars = '--pg-health-ink:' . $health_ink
            . ';--pg-health-ink-text:' . $health_ink_text;

        foreach (array(1, 2, 3, 4) as $health_stop) {
            $health_vars .= ';--pg-health-d' . $health_stop . ':' . $health_stops[$health_stop - 1]
                . ';--pg-health-l' . $health_stop . ':' . $health_stops_lt[$health_stop - 1];
        }


        // ── Which side a check lands on ─────────────────────
        //
        // functions.php marks the four that are jobs rather than
        // readings ($job_titles there): the rules file, the backup,
        // the update and the scheduled tasks. Each has somewhere to
        // go or something to press, so each becomes a line on the
        // right. Everything else is a reading and stays a tile.
        $reading_checks = array();
        $job_checks     = array();

        foreach ($health_checks as $health_check) {
            if (!empty($health_check['job'])) {
                $job_checks[] = $health_check;
            } else {
                $reading_checks[] = $health_check;
            }
        }

        // ── Readings ────────────────────────────────────────
        //
        // A chip each, not a tile each. Twelve tiles across half a
        // card put the label at nine pixels with the value at nine
        // more underneath, and at that size "Database" and
        // "Update status" were both an ellipsis -- a grid of boxes
        // whose labels had to be hovered to be read.
        //
        // The chip gets that width back by dropping the half that
        // was not information. A check that is fine says so with
        // its colour; printing "Tamam" nine times under nine green
        // labels is the colour said twice, in the space the label
        // needed. Only a check with something to report keeps its
        // value, which is also what makes those rows the ones the
        // eye lands on.
        //
        // Order is by state and then by weight: what is broken,
        // what is uncertain, then the three security checks that
        // lead when nothing is wrong, then the rest. usort() is
        // stable only from PHP 8.0, so position is carried into the
        // comparison -- without it the chips could reshuffle
        // between two draws of the same card.
        $check_ranks = array('fail' => 0, 'warn' => 1, 'info' => 3, 'ok' => 3);
        $check_order = array();

        foreach ($reading_checks as $check_index => $reading_check) {

            $check_rank = isset($check_ranks[$reading_check['state']])
                ? $check_ranks[$reading_check['state']]
                : 3;

            if (($check_rank === 3) && !empty($reading_check['priority'])) {
                $check_rank = 2;
            }

            $check_order[] = array($check_rank, (int) $check_index);
        }

        usort($check_order, function ($a, $b) {
            if ($a[0] === $b[0]) {
                return ($a[1] < $b[1]) ? -1 : 1;
            }
            return ($a[0] < $b[0]) ? -1 : 1;
        });

        // Bootstrap's own four, so a chip is the same red as every
        // other red on the screen.
        $check_tones = array('ok' => 'success', 'warn' => 'warning', 'fail' => 'danger', 'info' => 'secondary');

        $output_checks = '';
        $check_slot = 0;

        foreach ($check_order as $check_entry) {

            $health_check = $reading_checks[$check_entry[1]];
            $check_slot++;

            $check_tone = isset($check_tones[$health_check['state']])
                ? $check_tones[$health_check['state']]
                : 'secondary';

            $check_detail = (isset($health_check['detail']) && is_array($health_check['detail']))
                ? $health_check['detail']
                : array();

            // The value only when the check said it. "OK",
            // "Warning", "Problem" and "Not applicable" are the four words
            // functions.php puts in a check's mouth when it has
            // none of its own -- they are the colour spelled out,
            // and a red chip does not need to be told it is red.
            // What survives is what the check actually reported:
            // "3 eksik", "2026.4.4", "4.2 MB".
            $check_value = !empty($health_check['generic'])
                ? ''
                : '<span class="pg-check-value">' . h($health_check['value']) . '</span>';

            $check_body = '
                <i class="bi ' . h($health_check['icon']) . '"></i>
                <span class="pg-check-name">' . h($health_check['label']) . '</span>' . $check_value;

            $check_panel = '';

            if ($check_detail) {

                // Rows the check brought with it. They exist nowhere
                // else, so the chip has to be able to show them
                // rather than point at a screen that does not have
                // the answer. The panel is a full-width child of the
                // same wrapping row, so it opens on the line under
                // its own chip instead of in a box at the foot of
                // the card that several chips would point at.
                //
                // Bootstrap's collapse data-api is delegated from
                // document, so it binds to markup this widget
                // injects after page load. Widget 2 is deliberately
                // absent from welcome.php's periodic refresh list,
                // so nothing re-renders the card and closes the
                // panel under the operator's hand.
                $check_id = 'system_status_detail_' . $check_slot;

                $check_rows = '';

                foreach ($check_detail as $detail_row) {
                    $check_rows .= '
                    <div class="pg-job-panel-row">
                        <span class="text-truncate"><i class="bi bi-circle-fill pg-health-dot text-' . h(($detail_row['state'] == 'ok') ? 'success' : (($detail_row['state'] == 'fail') ? 'danger' : 'secondary')) . '"></i>' . h($detail_row['label']) . '</span>
                        <span class="text-muted flex-shrink-0">' . h($detail_row['when']) . '</span>
                    </div>';
                }

                // The popover would fire on the same hover that
                // opens the panel, so a chip that expands carries
                // the collapse attributes instead and its
                // explanation is the panel.
                $output_checks .= '
                <button type="button" class="pg-check pg-check-' . $check_tone . '"
                        data-bs-toggle="collapse" data-bs-target="#' . $check_id . '"
                        aria-expanded="false" aria-controls="' . $check_id . '"
                        title="' . h($health_check['title']) . '">' . $check_body . '
                    <i class="bi bi-chevron-down pg-check-caret"></i>
                </button>
                <div class="collapse pg-job-slot" id="' . $check_id . '">
                    <div class="pg-job-panel">' . $check_rows . '</div>
                </div>';

            } else {

                // The popover is the only place the full
                // explanation fits, and welcome.php binds it by
                // delegation from document, so it survives the
                // markup being injected after page load.
                $check_open = ($health_check['href'] !== '')
                    ? '<a href="' . h($health_check['href']) . '"'
                    : '<div';

                $output_checks .= $check_open . ' class="pg-check pg-check-' . $check_tone . ' status-popover"'
                    . ' title="' . h($health_check['title']) . '"'
                    . ' data-bs-toggle="popover"'
                    . ' data-bs-trigger="hover focus"'
                    . ' data-bs-content="' . h($health_check['message']) . '">' . $check_body
                    . (($health_check['href'] !== '') ? '</a>' : '</div>');
            }
        }

        // ── The jobs column ─────────────────────────────────
        //
        // Four checks that are jobs, plus three tools that are
        // always available. One record each, so that sorting them
        // by state is one pass over one list rather than a decision
        // repeated at every point a row is emitted.
        //
        // 'rank' orders the column: a failing check first, then a
        // warning, then the tools, then whatever has nothing to
        // report, and last the storage readings. Tools sit above
        // the healthy checks because a tool is why the operator
        // opened the column; a green backup row is confirmation and
        // can wait. Storage is last because it is the only row here
        // with nothing to act on at all.
        $health_jobs = array();

        $job_ranks = array('fail' => 0, 'warn' => 1, 'ok' => 3, 'info' => 3);

        // Read once for the row and for the button beside it. The
        // ten-minute status cache is deliberately not the source
        // here: the operator presses Fix and expects the next draw
        // to reflect what they just did. pg_server_config_scan()
        // memoizes per request, so the check above and this share
        // one read of a file a few kilobytes long.
        $server_rules_scan = pg_server_config_scan();
        $server_rules_todo = count($server_rules_scan['missing'])
            + ($server_rules_scan['stale_path'] ? 1 : 0);

        foreach ($job_checks as $job_check) {

            $job_key = isset($job_check['key']) ? $job_check['key'] : '';
            $job_action = '';
            $job_panel = '';

            // Rows the check brought with it -- per-job run times,
            // the list of rules that are not in the file. They exist
            // nowhere else, which is why the row expands instead of
            // pointing at a screen.
            $job_rows = '';

            if (isset($job_check['detail']) && is_array($job_check['detail'])) {
                foreach ($job_check['detail'] as $detail_row) {
                    $job_rows .= '
                    <div class="pg-job-panel-row">
                        <span class="text-truncate"><i class="bi bi-circle-fill pg-health-dot text-' . h(($detail_row['state'] == 'ok') ? 'success' : (($detail_row['state'] == 'fail') ? 'danger' : 'secondary')) . '"></i>' . h($detail_row['label']) . '</span>
                        <span class="text-muted flex-shrink-0">' . h($detail_row['when']) . '</span>
                    </div>';
                }
            }

            if ($job_key === 'Web Server Rules') {

                // The repair the row is about. Offered only when
                // there is something to write: the file has a
                // finite list of blocks and is finished once they
                // are all in it, so leaving the button afterwards
                // would be a control whose only answer is "nothing
                // to do".
                //
                // Administrator only, matching the endpoint. This
                // writes the file that decides what the whole site
                // will and will not hand out, which is not the same
                // authority as clearing a cache.
                if (($server_rules_todo > 0) && $server_rules_scan['valid'] && ((int) $user['role'] === 0)) {

                    $job_action = '
                    <button type="button" class="pg-job-btn pg-job-btn-fix" id="server_config_repair"
                            title="' . h(lang('Adds the missing rules to the web server configuration file in the web root. Nothing already in the file is changed, and a copy is kept first.')) . '"
                            data-busy-label="' . h(lang('Writing')) . '"
                            data-idle-label="' . h(lang('Fix')) . '"
                            data-confirm-content="' . h(lang('The missing rules will be added to the web server configuration file. A copy of the current file is kept first.')) . '"
                            data-failed-label="' . h(lang('The rules could not be written.')) . '">
                        <i class="bi bi-wrench-adjustable"></i><span id="server_config_repair_state">' . h(lang('Fix')) . '</span>
                    </button>';

                    $job_panel = '
                    <div class="pg-job-panel pg-job-result d-none" id="server_config_repair_result">
                        <div class="pg-job-panel-row"><span id="server_config_repair_message"></span></div>
                    </div>';
                }

            } elseif ($job_key === 'CA Bundle Setting') {

                // One line in data/config.php. Offered to an
                // administrator only, and only while the bundled
                // file is there and the configuration can be
                // written; otherwise the row's text says what to
                // add by hand.
                if (((int) $user['role'] === 0) && is_file(pg_ca_bundle_path()) && is_writable(PG_FUNCTIONS_DIR . '/data/config.php')) {

                    $job_action = '
                    <button type="button" class="pg-job-btn pg-job-btn-fix" id="ca_bundle_config_repair"
                            title="' . h(lang('Writes define(\'CURL_CA_BUNDLE\', dirname(__FILE__) . \'/cacert.pem\'); into data/config.php, replacing the current CURL_CA_BUNDLE line. Nothing else in the file is changed.')) . '"
                            data-busy-label="' . h(lang('Writing')) . '"
                            data-idle-label="' . h(lang('Fix')) . '"
                            data-confirm-content="' . h(lang('CURL_CA_BUNDLE in data/config.php will be pointed at the bundled data/cacert.pem.')) . '"
                            data-failed-label="' . h(lang('The setting could not be written.')) . '">
                        <i class="bi bi-wrench-adjustable"></i><span id="ca_bundle_config_repair_state">' . h(lang('Fix')) . '</span>
                    </button>';

                    $job_panel = '
                    <div class="pg-job-panel pg-job-result d-none" id="ca_bundle_config_repair_result">
                        <div class="pg-job-panel-row"><span id="ca_bundle_config_repair_message"></span></div>
                    </div>';
                }

            } elseif ($job_key === 'Write Permissions') {

                // Offered only while something refuses, and only to
                // an administrator: it changes who may write into the
                // software directory. The confirmation says what the
                // modes will be, because 0777 on a shared server is a
                // decision the operator makes, not the button.
                if (($job_check['state'] !== 'ok') && ((int) $user['role'] === 0)) {

                    $job_action = '
                    <button type="button" class="pg-job-btn pg-job-btn-fix" id="write_permissions_repair"
                            title="' . h(lang('Sets every folder the web server cannot write into to 0777 and every such file to 0666, so that both the web server and your FTP or file manager user can replace them during an update. Entries that belong to another system user cannot be changed from here and are listed afterwards.')) . '"
                            data-busy-label="' . h(lang('Fixing')) . '"
                            data-idle-label="' . h(lang('Fix')) . '"
                            data-confirm-content="' . h(lang('The folders and files the web server cannot write to will be set to 0777 / 0666. On a server shared with other accounts this lets them write there too; on a server that is yours alone it costs nothing.')) . '"
                            data-failed-label="' . h(lang('The permissions could not be changed.')) . '">
                        <i class="bi bi-wrench-adjustable"></i><span id="write_permissions_repair_state">' . h(lang('Fix')) . '</span>
                    </button>';

                    $job_panel = '
                    <div class="pg-job-panel pg-job-result d-none" id="write_permissions_repair_result">
                        <div class="pg-job-panel-row"><span id="write_permissions_repair_message"></span></div>
                    </div>';
                }

            } elseif (isset($job_check['href']) && ($job_check['href'] !== '')) {

                // The verb belongs to the state, not to the row.
                // "Software Update · 2026.4.4 · Update" says
                // an update is waiting when the middle of that line
                // says the opposite -- the button is the loudest
                // part of a row and it was contradicting the row.
                // With nothing to do, the row still opens its
                // screen, and the word for that is neutral.
                if (($job_check['state'] == 'ok') || ($job_check['state'] == 'info')) {
                    $job_label = lang('View');
                } elseif ($job_key === 'Last Backup') {
                    $job_label = lang('Backup');
                } elseif ($job_key === 'Software Update') {
                    $job_label = lang('Update');
                } else {
                    $job_label = lang('Go');
                }

                $job_action = '
                <a href="' . h($job_check['href']) . '" class="pg-job-btn">
                    <span>' . h($job_label) . '</span><i class="bi bi-arrow-right"></i>
                </a>';
            }

            $health_jobs[] = array(
                'rank'   => isset($job_ranks[$job_check['state']]) ? $job_ranks[$job_check['state']] : 3,
                'icon'   => $job_check['icon'],
                'color'  => $job_check['color'],
                // The full title, not the tile's short label. The
                // column has the width for "Web Server Rules"
                // and the point of moving these rows here was that
                // "Server rules" in nine pixels was not telling
                // anybody what the row was about.
                'name'   => $job_check['title'],
                'note'   => $job_check['value'],
                'hint'   => $job_check['message'],
                'detail' => $job_rows,
                'action' => $job_action,
                'panel'  => $job_panel,
            );
        }

        // ── Tools ───────────────────────────────────────────
        //
        // Three jobs rather than three readings. They used to be
        // reachable only through settings.php: the table scan was a
        // tile that screen injected into this card after load, cache
        // purge and clean-up were rows in the Settings menu. The
        // card is no longer on that screen, so the widget renders
        // them itself -- which is what makes them permanent: every
        // screen that draws widget 2 gets them, and nothing has to
        // know to inject anything.
        //
        // No extra role gate. purge_cache.php and clean_up.php both
        // call validate_area_access($user, 'manager'), and the two
        // endpoints refuse role >= 3, which is the same bar this
        // widget already stands behind.
        //
        // Labels ride on the control as data-* rather than going
        // into the global `translate` object: the only script that
        // needs them is the one handling that control, and it has
        // the control.
        $health_jobs[] = array(
            'rank'   => 2,
            'icon'   => 'bi-database-gear',
            'color'  => 'text-primary',
            'name'   => lang('Database table scan'),
            'note'   => lang('All tables'),
            'hint'   => lang('A full scan of every table. This can take several minutes on a large database.'),
            'detail' => '',
            'action' => '
                <button type="button" class="pg-job-btn" id="database_deep_check"
                        data-busy-label="' . h(lang('Running')) . '"
                        data-idle-label="' . h(lang('Run')) . '"
                        data-failed-label="' . h(lang('The deep check could not be completed.')) . '">
                    <i class="bi bi-arrow-repeat"></i><span id="database_deep_check_state">' . h(lang('Run')) . '</span>
                </button>',
            'panel'  => '
                <div class="pg-job-panel pg-job-result d-none" id="database_deep_check_result">
                    <div class="pg-job-panel-row"><span id="database_deep_check_message"></span></div>
                </div>',
        );

        // The purge answers in place. It used to be a link to
        // purge_cache.php, which clears the caches and then lands
        // the operator on settings.php -- so pressing a button on a
        // dashboard card took the card away and left the one number
        // the purge had just invalidated unread. api.php runs the
        // same pg_purge_caches() and the widget redraws itself.
        $health_jobs[] = array(
            'rank'   => 2,
            'icon'   => 'bi-trash3',
            'color'  => 'text-primary',
            'name'   => lang('Server caches'),
            'note'   => lang('OPcache and file caches'),
            'hint'   => lang('All server-side caches will be cleared.'),
            'detail' => '',
            'action' => '
                <button type="button" class="pg-job-btn" id="purge_cache"
                        data-busy-label="' . h(lang('Clearing')) . '"
                        data-idle-label="' . h(lang('Clear')) . '"
                        data-confirm-content="' . h(lang('All server-side caches will be cleared.')) . '"
                        data-failed-label="' . h(lang('The cache could not be cleared.')) . '">
                    <i class="bi bi-trash3"></i><span id="purge_cache_state">' . h(lang('Clear')) . '</span>
                </button>',
            'panel'  => '
                <div class="pg-job-panel pg-job-result d-none" id="purge_cache_result">
                    <div class="pg-job-panel-row"><span id="purge_cache_message"></span></div>
                </div>',
        );

        // The CA bundle. data/cacert.pem is the Mozilla root list an
        // operator points CURL_CA_BUNDLE at when the host's own store
        // is stale; Mozilla revises it several times a year, and a
        // list that falls behind is why "cURL error 60" appears on a
        // site that changed nothing. The row says what is installed
        // and whether the running configuration actually reads it;
        // the button fetches the current list from curl.se (or the
        // CA_BUNDLE_SOURCE_URL mirror) and swaps it in. The library
        // under includes/iyzipay-php/ keeps its own copy, which is
        // integrity-hashed and only changes with a release, so the
        // panel says so rather than leaving the operator to wonder
        // why two files carry two dates.
        $ca_bundle = pg_ca_bundle_status();

        if (!$ca_bundle['exists']) {
            $ca_bundle_note = lang('Missing');
        } elseif ($ca_bundle['stamp'] > 0) {
            $ca_bundle_note = pg_ca_bundle_date($ca_bundle['stamp']);
        } else {
            $ca_bundle_note = lang('No header');
        }

        if ($ca_bundle['mode'] === 'this') {
            $ca_bundle_use = lang('CURL_CA_BUNDLE points at this file.');
        } elseif ($ca_bundle['mode'] === 'other') {
            $ca_bundle_use = lang(array(
                'string' => 'CURL_CA_BUNDLE points at another file ({var:1}); the running configuration does not read this one.',
                'vars'   => array($ca_bundle['configured']),
            ));
        } else {
            $ca_bundle_use = lang('CURL_CA_BUNDLE is not set; connections are verified against the server\'s own certificate store.');
        }

        $ca_bundle_rows = '
            <div class="pg-job-panel-row">
                <span class="text-truncate">' . h(lang('File')) . '</span>
                <span class="text-muted flex-shrink-0">' . h(SOFTWARE_DIRECTORY . '/data/cacert.pem') . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="text-truncate">' . h(lang('Mozilla data')) . '</span>
                <span class="text-muted flex-shrink-0">' . h(($ca_bundle['stamp'] > 0) ? pg_ca_bundle_date($ca_bundle['stamp']) : $ca_bundle_note) . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="text-truncate">' . h(lang('Root certificates')) . '</span>
                <span class="text-muted flex-shrink-0">' . h(pg_format_number((int) $ca_bundle['count'], 0)) . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="text-truncate">' . h(lang('Source')) . '</span>
                <span class="text-muted flex-shrink-0">' . h(($ca_bundle['source'] !== '') ? $ca_bundle['source'] : lang('CA_BUNDLE_SOURCE_URL is not an https address')) . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="text-muted">' . h($ca_bundle_use) . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="text-muted">' . h(lang('The payment library keeps its own copy of the bundle; that one is refreshed with software releases and is not touched here.')) . '</span>
            </div>';

        $ca_bundle_action = '';
        $ca_bundle_panel = '';

        if ((int) $user['role'] === 0) {
            $ca_bundle_action = '
                <button type="button" class="pg-job-btn" id="ca_bundle_update"
                        data-busy-label="' . h(lang('Updating')) . '"
                        data-idle-label="' . h(lang('Update')) . '"
                        data-confirm-content="' . h(lang(array(
                            'string' => 'The current Mozilla root certificate list will be downloaded from {var:1} and will replace data/cacert.pem. A file that is older than the installed one, or that is not a complete bundle, is refused.',
                            'vars'   => array(($ca_bundle['source'] !== '') ? $ca_bundle['source'] : 'CA_BUNDLE_SOURCE_URL'),
                        ))) . '"
                        data-failed-label="' . h(lang('The CA bundle could not be updated.')) . '">
                    <i class="bi bi-arrow-repeat"></i><span id="ca_bundle_update_state">' . h(lang('Update')) . '</span>
                </button>';

            $ca_bundle_panel = '
                <div class="pg-job-panel pg-job-result d-none" id="ca_bundle_update_result">
                    <div class="pg-job-panel-row"><span id="ca_bundle_update_message"></span></div>
                </div>';
        }

        $health_jobs[] = array(
            'rank'   => 2,
            'icon'   => 'bi-shield-lock',
            'color'  => 'text-primary',
            'name'   => lang('CA certificate bundle'),
            'note'   => $ca_bundle_note,
            'hint'   => lang('The Mozilla root certificate list in data/cacert.pem, which outbound connections are verified against when CURL_CA_BUNDLE points at it. Update downloads the current list and replaces the file.'),
            'detail' => $ca_bundle_rows,
            'action' => $ca_bundle_action,
            'panel'  => $ca_bundle_panel,
        );

        // ── Storage ─────────────────────────────────────────
        //
        // Three figures that are readings and not checks: there is
        // no size at which a database, a backup folder or a file
        // library is wrong -- a busy shop's are large because the
        // shop works. Database size used to sit among the status
        // chips as the one entry that could be neither right nor
        // wrong, and the two figures it belongs with were nowhere
        // on the card.
        //
        // The total is on the line and the breakdown is under it,
        // because the question is nearly always "how much is this
        // installation holding" and only sometimes "which part of
        // it". Ranked last: it is the one row on this side with
        // nothing to act on.
        //
        // Cached for six hours inside pg_storage_usage(), on its
        // own clock rather than the status cache's ten minutes --
        // the backup folder is a recursive walk and the dashboard
        // must not pay for it dozens of times a day.
        $storage = (isset($status['storage']) && is_array($status['storage']))
            ? $status['storage']
            : array();

        $storage_lines = array(
            'database' => lang('Database'),
            'files'    => lang('Files'),
            'backups'  => lang('Backups'),
        );

        $storage_rows = '';

        foreach ($storage_lines as $storage_key => $storage_label) {

            // A null is "could not be read" -- information_schema
            // closed on this host, no backup folder, a files table
            // older than its size column. Printing a confident zero
            // for any of those would be worse than the line being
            // absent.
            if (!isset($storage[$storage_key]) || ($storage[$storage_key] === null)) {
                continue;
            }

            $storage_rows .= '
            <div class="pg-job-panel-row">
                <span class="text-truncate">' . h($storage_label) . '</span>
                <span class="text-muted flex-shrink-0">' . h(convert_bytes_to_string((float) $storage[$storage_key], 1)) . '</span>
            </div>';
        }

        if ($storage_rows !== '') {
            $health_jobs[] = array(
                'rank'   => 4,
                'icon'   => 'bi-hdd-stack',
                'color'  => 'text-primary',
                'name'   => lang('Storage'),
                'note'   => convert_bytes_to_string((float) $storage['total'], 1),
                'hint'   => lang('How much this installation is holding: the database, the file library and the backup folder.'),
                'detail' => $storage_rows,
                'action' => '',
                'panel'  => '',
            );
        }

        // Clean-up only lists what it found and waits for a second
        // press on its own screen, so this row asks nothing.
        $health_jobs[] = array(
            'rank'   => 2,
            'icon'   => 'bi-eraser',
            'color'  => 'text-primary',
            'name'   => lang('Clean Up'),
            'note'   => lang('Obsolete files'),
            'hint'   => lang('Tool to remove obsolete files and folders inside the software folder.'),
            'detail' => '',
            'action' => '
                <a href="' . h(PATH . SOFTWARE_DIRECTORY . '/clean_up.php') . '" class="pg-job-btn">
                    <span>' . h(lang('Go')) . '</span><i class="bi bi-arrow-right"></i>
                </a>',
            'panel'  => '',
        );

        // Stable sort by rank. usort() is only guaranteed stable
        // from PHP 8.0 and this file runs on 7.0, so the original
        // position is carried into the comparison: without it the
        // three tools -- which share a rank -- could swap places
        // between two draws of the same card.
        $job_order = array();

        foreach ($health_jobs as $job_index => $health_job) {
            $job_order[] = array((int) $health_job['rank'], (int) $job_index);
        }

        usort($job_order, function ($a, $b) {
            if ($a[0] === $b[0]) {
                return ($a[1] < $b[1]) ? -1 : 1;
            }
            return ($a[0] < $b[0]) ? -1 : 1;
        });

        $output_jobs = '';
        $job_slot = 0;

        foreach ($job_order as $job_entry) {

            $health_job = $health_jobs[$job_entry[1]];
            $job_slot++;

            // A row that can expand is a button and the whole name
            // side of it is the target; a row that cannot is a plain
            // box. The action beside it is a sibling, never a child:
            // a control inside the collapse toggle would fire the
            // toggle on its way out, and the fix button would open a
            // panel every time it was pressed.
            if ($health_job['detail'] !== '') {

                $job_id = 'system_status_job_' . $job_slot;

                $job_open = '<button type="button" class="pg-job-open" data-bs-toggle="collapse"'
                    . ' data-bs-target="#' . $job_id . '" aria-expanded="false" aria-controls="' . $job_id . '"'
                    . ' title="' . h($health_job['hint']) . '">';
                $job_close = '</button>';
                $job_caret = '<i class="bi bi-chevron-down pg-job-caret"></i>';
                $job_detail = '
                    <div class="collapse pg-job-slot" id="' . $job_id . '">
                        <div class="pg-job-panel">' . $health_job['detail'] . '</div>
                    </div>';

            } else {
                $job_open = '<div class="pg-job-open" title="' . h($health_job['hint']) . '">';
                $job_close = '</div>';
                $job_caret = '';
                $job_detail = '';
            }

            $output_jobs .= '
            <div class="pg-job">
                ' . $job_open . '
                    <i class="bi ' . h($health_job['icon']) . ' pg-job-icon ' . h($health_job['color']) . '"></i>
                    <span class="pg-job-name">' . h($health_job['name']) . '</span>
                    <span class="pg-job-note text-muted">' . h($health_job['note']) . '</span>
                    ' . $job_caret . '
                ' . $job_close . '
                ' . $health_job['action'] . '
                ' . $job_detail . '
                ' . $health_job['panel'] . '
            </div>';
        }

        // Turning notifications on is the browser's business, not
        // the site's: two operators looking at this dashboard on two
        // computers get two different answers, and the server has no
        // way to know either of them. So the row is written once,
        // hidden, and the panel fills it in and reveals it - the
        // same code that draws the button under the bell.
        $output_jobs .= '
            <div class="pg-job d-none" id="push_widget_row">
                <div class="pg-job-open" title="' . h(lang('Notifications reach this browser even while the panel is closed. Every device decides for itself.')) . '">
                    <i class="bi bi-bell pg-job-icon text-primary"></i>
                    <span class="pg-job-name">' . h(lang('Notifications on this device')) . '</span>
                    <span class="pg-job-note text-muted" id="push_widget_note"></span>
                </div>
                <button type="button" class="pg-job-btn pg-push-toggle" id="push_widget_toggle">
                    <i class="bi bi-bell"></i><span class="pg-push-label">' . h(lang('Turn on')) . '</span>
                </button>
            </div>';

        // ── The cells ───────────────────────────────────────
        //
        // Real elements in the drawing, animated where they are
        // drawn. Nothing about the animation lives in <defs>: a
        // browser rasterises pattern and mask CONTENT into a cached
        // texture and stops refreshing it, so an animation put in
        // there goes on running while the picture sits still. What
        // IS in <defs> here is the clip, and a clip that never
        // changes is free to cache.
        //
        // Written in FINAL coordinates -- the half turn that used to
        // be done with a CSS transform on the group is done here, in
        // the numbers. The transform was not wrong, but it made the
        // group a different user space from everything else on the
        // gauge, and a clip or a mask on a transformed element is
        // resolved in the space the element was WRITTEN in, not the
        // space it ends up in. That cost two rounds of a bar with
        // squares only at its tips. With the rotation folded into
        // the coordinates there is one space and no question. The
        // gradient comes along: pg_health_gradient_m is the same
        // gradient with its axis mirrored through the centre, which
        // is what the rotation used to do to it.
        //
        // The squares are CUT by the bar rather than fitted inside
        // it. Whole squares chosen by their centres read as tiles
        // laid on top of an arc; squares clipped by the arc read as
        // a field of them showing THROUGH it, which is the picture
        // this is after -- and it lets the grid run right to both
        // rims instead of stopping a square short of each.
        $health_step = 2.6;
        $health_size = 2.05;
        $health_half = $health_size / 2;

        // The drawn half, in final coordinates: an SVG circle starts
        // at three o'clock and the stylesheet turns the arcs half a
        // turn, so the fill runs from nine o'clock over the top.
        $health_reach = M_PI * ($health_score / 100);
        $health_t0    = M_PI;
        $health_t1    = M_PI + $health_reach;

        $health_p0x = 70 + (52 * cos($health_t0));
        $health_p0y = 70 + (52 * sin($health_t0));
        $health_p1x = 70 + (52 * cos($health_t1));
        $health_p1y = 70 + (52 * sin($health_t1));

        // Wider than the ten units the arc is drawn with, so the
        // clip has whole squares to cut into halves at both rims.
        // The halo layer is NOT clipped, so this margin is also how
        // far the light spills past the bar.
        $health_edge = 6.6;

        $health_cells      = '';
        $health_glow_cells = '';
        $health_index      = 0;

        for ($health_gy = 8.0; $health_gy <= 76.0; $health_gy += $health_step) {
            for ($health_gx = 6.0; $health_gx <= 134.0; $health_gx += $health_step) {

                $health_cx = $health_gx + $health_half;
                $health_cy = $health_gy + $health_half;
                $health_dx = $health_cx - 70;
                $health_dy = $health_cy - 70;
                $health_rr = sqrt(($health_dx * $health_dx) + ($health_dy * $health_dy));

                // atan2 answers between -pi and pi; the fill runs
                // from pi to pi + reach, so the negative half is
                // brought round first.
                $health_ang = atan2($health_dy, $health_dx);
                if ($health_ang < 0) {
                    $health_ang += 2 * M_PI;
                }

                if (($health_ang >= $health_t0) && ($health_ang <= $health_t1)) {

                    $health_on = (abs($health_rr - 52) <= $health_edge);

                } else {

                    // Past an end: inside the half disc the round
                    // cap paints there.
                    $health_c0x = $health_cx - $health_p0x;
                    $health_c0y = $health_cy - $health_p0y;
                    $health_c1x = $health_cx - $health_p1x;
                    $health_c1y = $health_cy - $health_p1y;

                    $health_on = ((($health_c0x * $health_c0x) + ($health_c0y * $health_c0y)) <= ($health_edge * $health_edge))
                        || ((($health_c1x * $health_c1x) + ($health_c1y * $health_c1y)) <= ($health_edge * $health_edge));
                }

                if (!$health_on) {
                    continue;
                }

                $health_index++;

                // Slow, and no two squares the same length, so the
                // field never comes back round to a configuration it
                // has already been in.
                //
                // Two and a half to five and a half seconds for one
                // breath. It was four to nine, then three to seven:
                // both ends have come down each time the layers
                // under the squares got quieter, because the slower
                // a square breathes the more contrast it needs for
                // the change to be seen at all, and there is no
                // reason to spend contrast on it now that the
                // squares are the bar. Still slow enough that the
                // eye reads a surface quietly alive rather than a
                // thing blinking at it.
                $health_cell_time = round(2.6 + (fmod($health_index * 0.75488, 1) * 3.0), 2);

                // NEGATIVE delay. A positive one is a wait: until it
                // elapses the square has no animated value and sits
                // at full opacity, so the first seconds after a draw
                // were a cascade of squares dropping in one after
                // another -- a burst that has nothing to do with the
                // animation, and that made everything after it look
                // like the animation had died down. A negative delay
                // starts the cycle already part-way through: every
                // square is mid-breath from the first frame, and the
                // phases are spread from the first frame too.
                $health_cell_phase = round(fmod($health_index * 0.61803, 1) * $health_cell_time, 2);

                $health_box = ' x="' . round($health_gx, 2) . '" y="' . round($health_gy, 2) . '"'
                    . ' width="' . $health_size . '" height="' . $health_size . '" rx="0.45"';

                // No class on the square: the animation is selected
                // through the group, which is a class name saved on
                // each of three hundred elements.
                $health_cells .= '<rect' . $health_box
                    . ' style="animation-delay:-' . $health_cell_phase . 's;animation-duration:' . $health_cell_time . 's"/>';

                // The same square again for the halo below, without
                // the style, so the animation selector cannot reach
                // it -- see the note on that group.
                $health_glow_cells .= '<rect' . $health_box . '/>';
            }
        }

        // ── The shape that cuts them ────────────────────────
        //
        // The exact outline of the drawn bar: the outer rim, the
        // round cap at the far end, the inner rim back, the round
        // cap at the near end. A clipPath clips to the FILL of its
        // contents, so a stroked circle is no use here -- that would
        // clip to the disc, not to the band -- and the band has to be
        // written out as a closed path.
        $health_ax = round(70 + (57 * cos($health_t0)), 3);
        $health_ay = round(70 + (57 * sin($health_t0)), 3);
        $health_bx = round(70 + (57 * cos($health_t1)), 3);
        $health_by = round(70 + (57 * sin($health_t1)), 3);
        $health_ix = round(70 + (47 * cos($health_t1)), 3);
        $health_iy = round(70 + (47 * sin($health_t1)), 3);
        $health_jx = round(70 + (47 * cos($health_t0)), 3);
        $health_jy = round(70 + (47 * sin($health_t0)), 3);

        $health_clip = 'M' . $health_ax . ' ' . $health_ay
            . 'A57 57 0 0 1 ' . $health_bx . ' ' . $health_by
            . 'A5 5 0 0 1 ' . $health_ix . ' ' . $health_iy
            . 'A47 47 0 0 0 ' . $health_jx . ' ' . $health_jy
            . 'A5 5 0 0 1 ' . $health_ax . ' ' . $health_ay . 'Z';

        // ── The groove ─────────────────────────────────────
        //
        // The unfilled half only. It used to be the WHOLE half turn,
        // with the fill drawn over the top of it, and on a dark card
        // that is invisible -- black under a lit bar is nothing. On a
        // white one it is the theme's pale grey under every square,
        // and on white, opacity is also loss of SATURATION: a square
        // at the bottom of its breath was settling onto grey instead
        // of onto the page and giving up its colour, taking the
        // quieter half of the animation with it.
        //
        // A negative dash offset walks the pattern along the path, so
        // the groove starts where the fill stops. The round cap it
        // starts with reaches back under the fill's own cap, which
        // covers it.
        //
        // At a hundred there is nothing to groove, and a zero-length
        // dash with a round cap paints a dot -- so nothing is drawn.
        $health_dash   = round($health_arc * $health_score / 100, 2);
        $health_empty  = round($health_arc - $health_dash, 2);
        $health_groove = ($health_empty > 0.5)
            ? '<circle class="pg-health-track" cx="70" cy="70" r="52" stroke-width="10"'
                . ' stroke-dasharray="' . $health_empty . ' 326.73"'
                . ' stroke-dashoffset="-' . $health_dash . '"></circle>'
            : '';

        $output_data = '
        <div class="card-body p-0 pg-split">
            <div class="pg-split-half pg-split-pinned">
                <div class="pg-health" style="' . $health_vars . '">
                    <div class="pg-health-dial">
                        <svg class="pg-health-gauge" viewBox="8 8 124 72" role="img" aria-label="' . h(lang('Overall System Health')) . ' ' . (int) $health_score . '%">
                            <defs>
                                <!--
                                    userSpaceOnUse so the axis can sit
                                    on the drawn arc rather than on
                                    the circle, which is what keeps
                                    the whole sweep on the bar at
                                    every score. Every arc below --
                                    the wash, the two blurs, the
                                    hairline and the masked cells --
                                    is stroked with this one
                                    gradient, so all of them agree
                                    about what colour the bar is at
                                    any point along it.

                                    The stops arrive through custom
                                    properties instead of being
                                    written here: the same score
                                    needs different colours on a
                                    black card and a white one, and
                                    12P does not know which one it is
                                    rendering into. Both sets are
                                    declared on .pg-health and the
                                    stylesheet picks.
                                -->
                                <linearGradient id="pg_health_gradient" gradientUnits="userSpaceOnUse"
                                                x1="122" y1="70" x2="' . $health_x2 . '" y2="' . $health_y2 . '">
                                    <stop offset="' . $health_marks[0] . '%" stop-color="var(--pg-health-s1)"/>
                                    <stop offset="' . $health_marks[1] . '%" stop-color="var(--pg-health-s2)"/>
                                    <stop offset="' . $health_marks[2] . '%" stop-color="var(--pg-health-s3)"/>
                                    <stop offset="' . $health_marks[3] . '%" stop-color="var(--pg-health-s4)"/>
                                </linearGradient>
                                <!--
                                    The same gradient with its axis
                                    mirrored through the centre of
                                    the dial. The arcs are turned
                                    half a turn by the stylesheet and
                                    take their gradient round with
                                    them; the squares are written
                                    already turned, so theirs has to
                                    be turned here instead. Same
                                    colours, same stops, same
                                    direction on screen.
                                -->
                                <linearGradient id="pg_health_gradient_m" gradientUnits="userSpaceOnUse"
                                                x1="18" y1="70" x2="' . $health_mx2 . '" y2="' . $health_my2 . '">
                                    <stop offset="' . $health_marks[0] . '%" stop-color="var(--pg-health-s1)"/>
                                    <stop offset="' . $health_marks[1] . '%" stop-color="var(--pg-health-s2)"/>
                                    <stop offset="' . $health_marks[2] . '%" stop-color="var(--pg-health-s3)"/>
                                    <stop offset="' . $health_marks[3] . '%" stop-color="var(--pg-health-s4)"/>
                                </linearGradient>
                                <!--
                                    The bar itself, as a shape rather
                                    than as a stroke, so the grid of
                                    squares can be cut by it.
                                -->
                                <clipPath id="pg_health_cell_clip" clipPathUnits="userSpaceOnUse">
                                    <path d="' . $health_clip . '"/>
                                </clipPath>
                                <!--
                                    The glow is a blurred copy of the arc
                                    rather than a drop-shadow, because a
                                    drop-shadow takes one colour and the arc
                                    has four: blurring the stroke itself
                                    keeps the glow the colour of whatever it
                                    is under. .pg-health-gauge is
                                    overflow:visible so the blur is not
                                    clipped at the viewBox edge.
                                -->
                                <filter id="pg_health_glow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feGaussianBlur stdDeviation="4.5"/>
                                </filter>
                                <!--
                                    And a wider one under it. One blur
                                    gives an outline; two, at different
                                    radii, give the falloff that reads
                                    as light.
                                -->
                                <filter id="pg_health_bloom" x="-70%" y="-70%" width="240%" height="240%">
                                    <feGaussianBlur stdDeviation="9"/>
                                </filter>
                                <!--
                                    And a tight one for the squares
                                    themselves. The two above are the
                                    light AROUND the bar and are cut
                                    off by the panel; this is the
                                    light BETWEEN the squares, which
                                    is what makes a lit panel of them
                                    rather than a row of tiles with
                                    the card showing through the
                                    gaps. Small radius on purpose: at
                                    the radius the arc glows use, the
                                    squares merge and the matrix is
                                    gone.
                                -->
                                <filter id="pg_health_cell_glow" x="-40%" y="-40%" width="180%" height="180%">
                                    <feGaussianBlur stdDeviation="2.1"/>
                                </filter>
                            </defs>
                            ' . $health_groove . '
                            <circle class="pg-health-bloom" cx="70" cy="70" r="52" stroke-width="10" stroke="url(#pg_health_gradient)"
                                    filter="url(#pg_health_bloom)"
                                    stroke-dasharray="' . round($health_arc * $health_score / 100, 2) . ' 326.73"></circle>
                            <circle class="pg-health-glow" cx="70" cy="70" r="52" stroke-width="10" stroke="url(#pg_health_gradient)"
                                    filter="url(#pg_health_glow)"
                                    stroke-dasharray="' . round($health_arc * $health_score / 100, 2) . ' 326.73"></circle>
                            <!--
                                The halo, and it is NOT clipped: this is
                                the light the squares throw PAST the bar,
                                which is what makes them read as a field
                                showing through it rather than as tiles
                                sitting on it.

                                It does not breathe with them either. A
                                filter is recomputed whenever what it
                                filters changes, so a blurred copy of
                                three hundred animating squares would
                                re-run a blur over the whole bar every
                                frame. Held still, the blur is computed
                                once and reused, and what it gives --
                                light in the gaps -- is ambient anyway:
                                it is the panel being lit, not the pixel.
                            -->
                            <g class="pg-health-cell-glow" fill="url(#pg_health_gradient_m)"
                               filter="url(#pg_health_cell_glow)">' . $health_glow_cells . '</g>
                            <g class="pg-health-cells" fill="url(#pg_health_gradient_m)"
                               clip-path="url(#pg_health_cell_clip)">' . $health_cells . '</g>
                            <!--
                                A second, hairline arc inside the thick one.
                                Its own radius means its own circumference,
                                so the fraction is recomputed rather than
                                reused: 2*pi*42 = 263.89, half of it 131.95.
                            -->
                            <circle class="pg-health-inner" cx="70" cy="70" r="42" stroke-width="2" stroke="url(#pg_health_gradient)"
                                    stroke-dasharray="' . round(131.95 * $health_score / 100, 2) . ' 263.89"></circle>
                        </svg>
                        <div class="pg-health-readout">
                            <div class="pg-health-score">' . (int) $health_score . '<span>%</span></div>
                            <div class="pg-health-label">' . lang('Overall System Health') . '</div>
                        </div>
                    </div>
                </div>
                <div class="pg-checks pg-split-scroll">
                    ' . $output_checks . '
                </div>
            </div>
            <div class="pg-split-half pg-split-pinned">
                <div class="pg-jobs-heading">' . lang('Maintenance and tools') . '</div>
                <div class="pg-jobs pg-split-scroll">
                    ' . $output_jobs . '
                </div>
            </div>
        </div>';

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
