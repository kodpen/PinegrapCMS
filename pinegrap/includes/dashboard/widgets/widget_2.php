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

        // The gauge -- its colour band, the gradient laid along the
        // arc, the pixel matrix and the glow layers -- is drawn by
        // pg_health_gauge() (includes/fn/health_gauge.php), which the
        // new dashboard's System Status card draws too.

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
        // One chip a reading, drawn by pg_system_status_chips()
        // (includes/fn/health_gauge.php), which the new dashboard's
        // System Status card draws too.
        $output_checks = pg_system_status_chips($reading_checks, $user['role']);

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
                    $job_rows .= pg_system_status_detail_row($detail_row);
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
            'color'  => 'fg-primary',
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
            'color'  => 'fg-primary',
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
                <span class="fg-secondary flex-shrink-0">' . h(SOFTWARE_DIRECTORY . '/data/cacert.pem') . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="text-truncate">' . h(lang('Mozilla data')) . '</span>
                <span class="fg-secondary flex-shrink-0">' . h(($ca_bundle['stamp'] > 0) ? pg_ca_bundle_date($ca_bundle['stamp']) : $ca_bundle_note) . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="text-truncate">' . h(lang('Root certificates')) . '</span>
                <span class="fg-secondary flex-shrink-0">' . h(pg_format_number((int) $ca_bundle['count'], 0)) . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="text-truncate">' . h(lang('Source')) . '</span>
                <span class="fg-secondary flex-shrink-0">' . h(($ca_bundle['source'] !== '') ? $ca_bundle['source'] : lang('CA_BUNDLE_SOURCE_URL is not an https address')) . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="fg-secondary">' . h($ca_bundle_use) . '</span>
            </div>
            <div class="pg-job-panel-row">
                <span class="fg-secondary">' . h(lang('The payment library keeps its own copy of the bundle; that one is refreshed with software releases and is not touched here.')) . '</span>
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
            'color'  => 'fg-primary',
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

        // The image derivatives say which formats they are made in: written
        // by this server and switched on in the settings
        // (pg_image_delivery_formats(), a stored answer).
        $image_formats = array();
        $image_format_names = array('webp' => 'WebP', 'avif' => 'AVIF');

        if (function_exists('pg_image_delivery_formats')) {
            foreach (pg_image_delivery_formats() as $image_format => $image_writes) {
                if ($image_writes && isset($image_format_names[$image_format])) $image_formats[] = $image_format_names[$image_format];
            }
        }

        $storage_lines = array(
            'database' => lang('Database'),
            'files'    => lang('Files'),
            'images'   => $image_formats
                ? lang(array('string' => 'Image derivatives ({var:1})', 'vars' => array(implode(', ', $image_formats))))
                : lang('Image derivatives'),
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
                <span class="fg-secondary flex-shrink-0">' . h(convert_bytes_to_string((float) $storage[$storage_key], 1)) . '</span>
            </div>';
        }

        if ($storage_rows !== '') {
            $health_jobs[] = array(
                'rank'   => 4,
                'icon'   => 'bi-hdd-stack',
                'color'  => 'fg-primary',
                'name'   => lang('Storage'),
                'note'   => convert_bytes_to_string((float) $storage['total'], 1),
                'hint'   => lang('How much this installation is holding: the database, the file library, the image derivatives made from it and the backup folder.'),
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
            'color'  => 'fg-primary',
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
                    <span class="pg-job-note fg-secondary">' . h($health_job['note']) . '</span>
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
                    <i class="bi bi-bell pg-job-icon fg-primary"></i>
                    <span class="pg-job-name">' . h(lang('Notifications on this device')) . '</span>
                    <span class="pg-job-note fg-secondary" id="push_widget_note"></span>
                </div>
                <button type="button" class="pg-job-btn pg-push-toggle" id="push_widget_toggle">
                    <i class="bi bi-bell"></i><span class="pg-push-label">' . h(lang('Turn on')) . '</span>
                </button>
            </div>';

        $output_data = '
        <div class="card-body p-0 pg-split">
            <div class="pg-split-half pg-split-pinned">
                ' . pg_health_gauge($health_score) . '
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
