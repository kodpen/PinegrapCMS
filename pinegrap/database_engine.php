<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Database Engine: what the database holds and how it is doing. The findings
 * worth acting on, the visitor records with their retention period and a
 * cleanup to start by hand, the tables still on MyISAM with a button to move
 * each of them to InnoDB, the result of the table health check, every table
 * with its size and free space and an OPTIMIZE button, and the facts of the
 * database server.
 *
 * The 2026.4.8 upgrade converts every table it can within its limits; a table
 * over them, or one an earlier attempt did not finish, is left here. Moving a
 * large table copies it whole while writes to it wait, which can take minutes,
 * so the operator chooses the moment: one table per click, at a quiet hour,
 * after a backup. The work is pg_innodb_convert_table() (includes/fn/innodb.php),
 * the same function the upgrade uses, without the size limits. OPTIMIZE, CHECK
 * and REPAIR TABLE go through includes/fn/db_maintenance.php in the same way:
 * one table per request, never two statements on one table at once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('database_engine');

$database_engine_url = PATH . SOFTWARE_DIRECTORY . '/database_engine.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $engine_action = (isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';
    $engine_table = (isset($_POST['table']) && is_string($_POST['table'])) ? $_POST['table'] : '';

    if ($engine_action === 'convert') {

        // Only the tables the software itself converts. The name also goes
        // into the ALTER statement, which accepts nothing outside [a-z0-9_]
        // anyway.
        if (!in_array($engine_table, pg_innodb_core_tables(), true)) {
            $liveform->add_error(lang('That table is not one of the tables this screen converts.'));
            go($database_engine_url);
        }

    } elseif (in_array($engine_action, array('optimize', 'check', 'repair'), true)) {

        // Any table of this database, but only a name the database itself
        // lists: it goes into the statement between backticks.
        if ((preg_match('/^[a-z0-9_]+$/', $engine_table) !== 1) || !array_key_exists($engine_table, pg_db_table_catalog())) {
            $liveform->add_error(lang('That table is not in this database.'));
            go($database_engine_url);
        }

    } elseif (!in_array($engine_action, array('check_all', 'visitor_cleanup', 'visitor_move'), true)) {

        $liveform->add_error(lang('Unknown action.'));
        go($database_engine_url);

    }

    // The visitor cleanup is driven from the screen a step at a time: each
    // request answers with JSON and the page asks for the next step until no
    // old rows are left.
    $engine_ajax = (isset($_POST['ajax']) && ($_POST['ajax'] === '1'));

    // The work can take minutes. The session file stays locked for as long
    // as this request holds it, which would freeze every other tab of the
    // panel; it is reopened afterwards to carry the result to the next page.
    // A closed tab must not end the request halfway through either: the
    // server would finish the statement regardless, and nothing would remove
    // the marker that says an attempt is under way.
    session_write_close();

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    // The distinct lines the server answered with, as one sentence.
    $engine_messages = function ($result) {
        $texts = array();
        foreach ($result['messages'] as $message) {
            if ($message['Msg_text'] !== '') {
                $texts[] = $message['Msg_text'];
            }
        }
        return implode('; ', array_unique($texts));
    };

    // The answers OPTIMIZE, CHECK and REPAIR share.
    $engine_common_text = function ($statement, $result) {
        switch ($result['state']) {
            case 'missing':
                return lang(array('string' => '{var:1}: table {var:2} does not exist', 'vars' => array($statement, $result['table'])));
            case 'running':
                return lang(array('string' => '{var:1}: another maintenance statement on {var:2} has been running for {var:3} s; wait for it to finish', 'vars' => array($statement, $result['table'], (int) $result['running_seconds'])));
            case 'busy':
                return lang(array('string' => '{var:1}: {var:2} is in use (lock wait timeout); try again when the site is quiet', 'vars' => array($statement, $result['table'])));
            case 'unsupported':
                return (string) $result['error'];
            default:
                if ($result['errno'] > 0) {
                    return lang(array('string' => '{var:1} on {var:2} failed (MySQL {var:3}: {var:4})', 'vars' => array($statement, $result['table'], (int) $result['errno'], $result['error'])));
                }
                return lang(array('string' => '{var:1} on {var:2} failed: {var:3}', 'vars' => array($statement, $result['table'], $result['error'])));
        }
    };

    $engine_ok = false;
    $engine_text = '';

    switch ($engine_action) {

        case 'convert':
            $engine_result = pg_innodb_convert_table($engine_table, array('retry' => true));
            $engine_text = pg_innodb_state_text($engine_result);
            $engine_ok = ($engine_result['state'] === 'converted');
            break;

        case 'optimize':
            $engine_result = pg_db_optimize_table($engine_table);
            if ($engine_result['state'] === 'done') {
                $engine_ok = true;
                $engine_text = lang(array(
                    'string' => '{var:1} optimized in {var:2} s: {var:3} → {var:4} ({var:5} freed). The server said: {var:6}',
                    'vars' => array(
                        $engine_table,
                        pg_format_number($engine_result['seconds'], 1),
                        pg_innodb_size_label($engine_result['bytes_before']),
                        pg_innodb_size_label($engine_result['bytes_after']),
                        pg_innodb_size_label(max(0, $engine_result['free_before'] - $engine_result['free_after'])),
                        $engine_messages($engine_result),
                    ),
                ));
            } else {
                $engine_text = $engine_common_text('OPTIMIZE TABLE', $engine_result);
            }
            break;

        case 'check':
            $engine_result = pg_db_check_table($engine_table);
            if ($engine_result['state'] === 'ok') {
                $engine_ok = true;
                $engine_text = lang(array('string' => '{var:1} checked in {var:2} s: no problems found.', 'vars' => array($engine_table, pg_format_number($engine_result['seconds'], 1))));
            } elseif ($engine_result['state'] === 'issue') {
                $engine_text = lang(array('string' => '{var:1} checked in {var:2} s, the server found a problem: {var:3}', 'vars' => array($engine_table, pg_format_number($engine_result['seconds'], 1), $engine_messages($engine_result))));
            } else {
                $engine_text = $engine_common_text('CHECK TABLE', $engine_result);
            }
            break;

        case 'repair':
            $engine_result = pg_db_repair_table($engine_table);
            if ($engine_result['state'] === 'repaired') {
                $engine_ok = true;
                $engine_text = lang(array('string' => '{var:1} repaired in {var:2} s. The server said: {var:3}', 'vars' => array($engine_table, pg_format_number($engine_result['seconds'], 1), $engine_messages($engine_result))));
            } else {
                $engine_text = $engine_common_text('REPAIR TABLE', $engine_result);
            }
            break;

        case 'visitor_cleanup':
            $visitor_run = pg_visitor_cleanup_run(20);
            $engine_ok = in_array($visitor_run['state'], array('done', 'more'), true);
            $engine_text = pg_visitor_cleanup_result_text($visitor_run);

            if ($engine_ajax) {

                if (function_exists('session_status') && (session_status() === PHP_SESSION_NONE)) {
                    @session_start();
                }

                if (($visitor_run['deleted'] > 0) || ($visitor_run['summarized'] > 0)) {
                    log_activity(lang(array('string' => 'Database Engine: {var:1}', 'vars' => array($engine_text))), $_SESSION['sessionusername']);
                }

                // The figures of the earlier steps come back from the page,
                // only to be added up in the sentence it shows.
                $visitor_deleted = $visitor_run['deleted'] + (isset($_POST['deleted_so_far']) ? max(0, (int) $_POST['deleted_so_far']) : 0);
                $visitor_summarized = $visitor_run['summarized'] + (isset($_POST['summarized_so_far']) ? max(0, (int) $_POST['summarized_so_far']) : 0);

                $visitor_progress = lang(array(
                    'string' => 'So far {var:1} visitor record(s) removed and {var:2} day(s) added to the daily summary.',
                    'vars' => array(pg_format_number($visitor_deleted, 0), pg_format_number($visitor_summarized, 0)),
                ));

                // The last step leaves its sentence for the page that loads
                // next.
                if ($visitor_run['state'] !== 'more') {
                    if ($engine_ok) {
                        $liveform->add_notice($visitor_progress . ' ' . lang('No older records are left.'));
                    } else {
                        $liveform->add_error($engine_text);
                    }
                }

                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');

                echo json_encode(array(
                    'ok' => $engine_ok,
                    'state' => $visitor_run['state'],
                    'deleted' => $visitor_run['deleted'],
                    'summarized' => $visitor_run['summarized'],
                    'text' => ($visitor_run['state'] === 'more') ? $visitor_progress : $engine_text,
                ));

                exit;
            }
            break;

        case 'visitor_move':
            // Asked for here, the move starts without the checks the general
            // job waits for on its own (the period, free disk it can read);
            // the request is in the marker, so the general job carries it on
            // after this step, or starts it when this step finds the lock
            // taken.
            $visitor_move_last = pg_visitor_move_last();
            $visitor_move_last['requested'] = true;
            pg_visitor_move_save($visitor_move_last);

            $visitor_move = pg_visitor_move_run(20);
            $engine_ok = !in_array($visitor_move['state'], array('waiting', 'failed'), true);

            if ($visitor_move['text'] !== '') {
                $engine_text = $visitor_move['text'];
            } elseif ($visitor_move['state'] === 'waiting') {
                $engine_text = pg_visitor_move_reason_text(pg_visitor_move_last());
            } elseif ($visitor_move['state'] === 'none') {
                $engine_text = lang('The visitors table is not on MyISAM; there is nothing to move.');
            } else {
                $engine_text = lang('The visitors table is being moved to InnoDB. The general job carries the move on a few thousand records at a time; the Visitor records card shows how far it has come.');
            }
            break;

        case 'check_all':
            // The deep sweep of System Status (pg_panel_database_deep_check()),
            // started from here: every table, InnoDB included.
            $engine_report = check_and_repair_database_tables(true);

            $engine_issues = 0;
            $engine_repairs = 0;

            foreach ($engine_report as $engine_report_messages) {
                foreach ($engine_report_messages as $engine_report_message) {
                    if ($engine_report_message === 'error') {
                        $engine_issues++;
                    }
                    if ($engine_report_message === 'repaired') {
                        $engine_repairs++;
                    }
                }
            }

            // The status widget renders from a cache of its own.
            $engine_status_cache = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';

            if (file_exists($engine_status_cache)) {
                @unlink($engine_status_cache);
            }

            $engine_ok = ($engine_issues == 0);
            $engine_text = lang(array(
                'string' => '{var:1} table(s) checked, {var:2} issue(s) found, {var:3} repaired.',
                'vars' => array(
                    pg_format_number(count($engine_report), 0),
                    pg_format_number($engine_issues, 0),
                    pg_format_number($engine_repairs, 0),
                ),
            ));
            break;
    }

    if (function_exists('session_status') && (session_status() === PHP_SESSION_NONE)) {
        @session_start();
    }

    log_activity(lang(array('string' => 'Database Engine: {var:1}', 'vars' => array($engine_text))), $_SESSION['sessionusername']);

    if ($engine_ok) {
        $liveform->add_notice($engine_text);
    } else {
        $liveform->add_error($engine_text);
    }

    go($database_engine_url);
}

$engine_capability = pg_innodb_capability();

$engine_status = pg_innodb_table_status(pg_innodb_core_tables());

$engine_on_innodb = 0;

foreach ($engine_status as $engine_row) {
    if ($engine_row['engine'] === 'innodb') {
        $engine_on_innodb++;
    }
}

$engine_pending = pg_innodb_pending_tables();

// The list is short and each estimate is three information_schema reads.
foreach ($engine_pending as $engine_table => $engine_row) {
    $engine_pending[$engine_table]['row_estimate'] = pg_innodb_row_estimate($engine_table);
}

$engine_catalog = pg_db_table_catalog();

$engine_facts = pg_db_server_facts();

$engine_health = pg_db_health_report();

$engine_findings = pg_db_table_findings($engine_catalog, $engine_pending, $engine_health, $engine_facts);

$engine_candidates = array_flip(pg_db_optimize_candidates($engine_catalog, $engine_facts));

// An ALTER, OPTIMIZE, CHECK or REPAIR TABLE that another connection is still
// running (a request the web server gave up on, the upgrade, another tab).
// Starting another statement now would only queue behind it, so the buttons
// wait.
$engine_running = pg_db_running_statement('');

// A conversion marker with nothing running behind it: an attempt that never
// finished.
$engine_marker = @json_decode((string) @file_get_contents(pg_innodb_temp_marker()), true);
$engine_marker_table = (($engine_running === null) && is_array($engine_marker) && isset($engine_marker['table']) && isset($engine_pending[$engine_marker['table']]))
    ? (string) $engine_marker['table']
    : '';

// The same for OPTIMIZE. Half a minute of grace: the request that wrote the
// marker may be about to start its statement. Said once, then forgotten.
$engine_optimize_file = pg_db_optimize_marker();
$engine_optimize_marker = @json_decode((string) @file_get_contents($engine_optimize_file), true);
$engine_optimize_table = '';

if (($engine_running === null) && is_array($engine_optimize_marker) && isset($engine_optimize_marker['table']) && is_string($engine_optimize_marker['table'])
    && isset($engine_optimize_marker['started']) && (((int) $engine_optimize_marker['started'] + 30) <= time())) {
    $engine_optimize_table = $engine_optimize_marker['table'];
    @unlink($engine_optimize_file);
}

$engine_buttons = ($engine_capability['ok'] && ($engine_running === null));

$engine_tools = ($engine_running === null);

$engine_repairable = array('myisam', 'aria', 'mrg_myisam', 'isam');

// One action button. The row's own form would nest inside the page's; the
// buttons fill in the single hidden form below instead.
$engine_button = function ($action, $table, $label, $icon, $class, $confirm, $enabled, $title = '') {
    return '<button type="button" class="btn btn-sm ' . $class . ' pg-engine-action text-nowrap" data-action="' . h($action) . '" data-table="' . h($table) . '" data-confirm="' . h($confirm) . '"'
        . (($title !== '') ? ' title="' . h($title) . '"' : '')
        . ($enabled ? '' : ' disabled') . '><i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . h($label) . '</button>';
};

$engine_confirm_convert = lang('Convert {var:1} to InnoDB now? Writes to the table wait until the conversion ends.');
$engine_confirm_optimize = lang('Optimize {var:1} now? The table is rewritten; on a large table that takes minutes.');
$engine_confirm_check = lang('Check {var:1} now? Every row of the table is read; on a large table that takes minutes.');
$engine_confirm_repair = lang('Repair {var:1} now? Writes to the table wait until the repair ends. Take a backup first.');

$engine_optimize_button = function ($table, $enabled) use ($engine_button, $engine_candidates, $engine_confirm_optimize) {
    $candidate = isset($engine_candidates[$table]);
    return $engine_button('optimize', $table, lang('Optimize table'), 'bi-arrow-repeat', $candidate ? 'btn-outline-warning' : 'btn-outline-secondary', $engine_confirm_optimize, $enabled, $candidate ? lang('Free space worth giving back') : '');
};

$engine_alerts = '';

if (!$engine_capability['ok']) {
    $engine_alerts .= '
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <div>' . h(lang(array('string' => 'The tables cannot be moved to InnoDB on this database server: {var:1}', 'vars' => array($engine_capability['reason'])))) . '</div>
            </div>';
}

if ($engine_running !== null) {

    $engine_running_statement = 'ALTER TABLE';
    $engine_running_table = '';

    if (preg_match('/^\s*(ALTER|OPTIMIZE|REPAIR|CHECK)\s+TABLE\s+`?([A-Za-z0-9_$]+)`?/i', (string) $engine_running['INFO'], $engine_running_match)) {
        $engine_running_statement = strtoupper($engine_running_match[1]) . ' TABLE';
        $engine_running_table = $engine_running_match[2];
    }

    $engine_alerts .= '
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <div>' . h(lang(array('string' => '{var:1} on {var:2} has been running for {var:3} s', 'vars' => array($engine_running_statement, $engine_running_table, (int) $engine_running['TIME'])))) . '
                    <div class="small text-body-secondary">' . h(lang('The buttons come back when it has finished. Refresh this screen in a while.')) . '</div>
                </div>
            </div>';
}

if ($engine_marker_table !== '') {
    $engine_alerts .= '
            <div class="alert alert-info d-flex gap-2 align-items-start">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <div>' . h(pg_innodb_state_text(array('state' => 'aborted', 'table' => $engine_marker_table))) . '</div>
            </div>';
}

if ($engine_optimize_table !== '') {
    $engine_alerts .= '
            <div class="alert alert-info d-flex gap-2 align-items-start">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <div>' . h(lang(array('string' => 'An earlier OPTIMIZE of {var:1} did not finish.', 'vars' => array($engine_optimize_table)))) . '</div>
            </div>';
}

// -- Findings -----------------------------------------------------------------

if (count($engine_findings) == 0) {

    $findings_list = '
                    <div class="alert alert-success d-flex gap-2 align-items-start mb-0">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <div>' . h(lang('Nothing to report.')) . '</div>
                    </div>';

} else {

    $findings_icons = array(
        'danger' => 'bi-x-circle-fill text-danger',
        'warning' => 'bi-exclamation-triangle-fill text-warning',
        'info' => 'bi-info-circle-fill text-info',
    );

    $findings_rows = '';

    foreach ($engine_findings as $finding) {

        $finding_actions = '';

        if (($finding['kind'] === 'fragmented') && isset($engine_catalog[$finding['table']])) {

            $finding_actions = $engine_optimize_button($finding['table'], $engine_tools);

        } elseif (($finding['kind'] === 'health') && isset($engine_catalog[$finding['table']])) {

            $finding_actions = $engine_button('check', $finding['table'], lang('Check'), 'bi-clipboard-check', 'btn-outline-secondary', $engine_confirm_check, $engine_tools);

            if (in_array($engine_catalog[$finding['table']]['engine'], $engine_repairable, true)) {
                $finding_actions .= ' ' . $engine_button('repair', $finding['table'], lang('Repair'), 'bi-wrench-adjustable', 'btn-outline-warning', $engine_confirm_repair, $engine_tools);
            }
        }

        $findings_rows .= '
                        <li class="list-group-item d-flex gap-2 align-items-start">
                            <i class="bi ' . $findings_icons[$finding['level']] . ' mt-1" aria-hidden="true"></i>
                            <div class="flex-grow-1">' . h($finding['text']) . '</div>' . (($finding_actions !== '') ? '
                            <div class="d-flex gap-1 flex-shrink-0">' . $finding_actions . '</div>' : '') . '
                        </li>';
    }

    $findings_list = '
                    <ul class="list-group list-group-flush">' . $findings_rows . '
                    </ul>';
}

// -- Visitor records ----------------------------------------------------------

// The visitors table grows by a row per visit and was never trimmed before
// the retention period: on a busy site it is the largest table of the
// database by far. Its figures, what the period would remove now, and the
// button that removes it.
$visitor_catalog = isset($engine_catalog['visitors']) ? $engine_catalog['visitors'] : null;
$visitor_settings = pg_visitor_retention_settings();

if (($visitor_settings === false) || ($visitor_catalog === null)) {

    $visitor_card = '
                    <div class="alert alert-secondary d-flex gap-2 align-items-start mb-0">
                        <i class="bi bi-hourglass" aria-hidden="true"></i>
                        <div>' . h(lang('The period for keeping visitor records arrives with the upgrade steps of this version. Run them again to see the cleanup here.')) . '</div>
                    </div>';

} else {

    $visitor_preview = pg_visitor_cleanup_preview($visitor_catalog);
    $visitor_last = pg_visitor_cleanup_last();
    $visitor_through = $visitor_settings['summary'] ? pg_visitor_summary_through() : '';
    $visitor_settings_link = '<a href="settings_seo.php#pgset-analytics">' . h(lang('Change')) . '</a>';

    $visitor_alert = '';

    if (pg_visitor_table_is_large($visitor_catalog)) {

        $visitor_size = lang(array(
            'string' => 'The visitors table holds about {var:1} records and takes {var:2}.',
            'vars' => array(pg_format_number($visitor_catalog['rows'], 0), pg_innodb_size_label($visitor_catalog['bytes'])),
        ));

        if ($visitor_settings['days'] <= 0) {
            $visitor_advice = lang('Visitor records are kept without a limit. Set a period under Settings › SEO › Visitor Tracking and the general job removes the older ones a little at a time.');
        } elseif ($visitor_preview['rows'] > 0) {
            $visitor_advice = lang(array(
                'string' => '{var:1} of them are older than {var:2} days. The general job removes them a little at a time; the button below starts on them now.',
                'vars' => array(pg_format_number($visitor_preview['rows'], 0), $visitor_settings['days']),
            ));
        } else {
            $visitor_advice = lang(array(
                'string' => 'All of them are from the last {var:1} days; a shorter period makes the table smaller.',
                'vars' => array($visitor_settings['days']),
            ));
        }

        $visitor_alert = '
                    <div class="alert alert-warning d-flex gap-2 align-items-start">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div>' . h($visitor_size . ' ' . $visitor_advice) . '</div>
                    </div>';
    }

    // On MyISAM every write to the table waits for the statement in front of
    // it, a cleanup step included. The general job moves it to InnoDB once
    // the cleanup is through (pg_visitor_move_run()), which also gives the
    // room of the removed rows back to the disk; this says where that stands.
    $visitor_move = pg_visitor_move_last();
    $visitor_moving = in_array($visitor_move['state'], array('copying', 'swapped'), true);
    $visitor_move_text = '';
    $visitor_move_level = 'info';

    if ($visitor_catalog['engine'] === 'myisam') {

        if ($visitor_moving) {
            $visitor_move_text = lang(array(
                'string' => 'The general job is moving this table to InnoDB a few thousand records at a time while the site keeps running: {var:1} of about {var:2} records copied so far. At the end the copy takes the place of the old table in a moment, and the old table is removed.',
                'vars' => array(pg_format_number($visitor_move['copied'], 0), pg_format_number($visitor_catalog['rows'], 0)),
            ));
        } elseif ($visitor_move['state'] === 'failed') {
            $visitor_move_level = 'warning';
            $visitor_move_text = pg_visitor_move_result_text($visitor_move) . ' ' . lang('The general job tries again in a week; Move to InnoDB now tries now.');
        } else {
            $visitor_move_reason = pg_visitor_move_reason_text($visitor_move);
            $visitor_move_text = lang('This table is on MyISAM, which locks the whole table for each statement: the cleanup works in small steps so visits wait only briefly.') . ' '
                . (($visitor_move_reason !== '') ? $visitor_move_reason : pg_visitor_move_reason_text(array('reason' => 'backlog') + $visitor_move));
        }

    } elseif (isset($engine_catalog[PG_VISITOR_MOVE_OLD]) && ($visitor_move['state'] === 'failed')) {

        $visitor_move_level = 'warning';
        $visitor_move_text = pg_visitor_move_result_text($visitor_move) . ' ' . lang(array(
            'string' => 'The old table is kept as {var:1}; once you have looked at it, it can be dropped.',
            'vars' => array(PG_VISITOR_MOVE_OLD),
        ));

    } elseif (($visitor_move['state'] === 'done') && ($visitor_move['finished_at'] > (time() - 30 * 86400))) {

        $visitor_move_level = 'success';
        $visitor_move_text = lang(array(
            'string' => 'The visitors table was moved to InnoDB on {var:1}: {var:2} before, {var:3} now.',
            'vars' => array(
                get_absolute_time(array('timestamp' => $visitor_move['finished_at'], 'type' => 'date', 'format' => 'plain_text')),
                pg_innodb_size_label($visitor_move['bytes_before']),
                pg_innodb_size_label($visitor_move['bytes_after']),
            ),
        ));
    }

    if ($visitor_move_text !== '') {
        $visitor_alert .= '
                    <div class="alert alert-' . $visitor_move_level . ' d-flex gap-2 align-items-start">
                        <i class="bi ' . (($visitor_move_level === 'success') ? 'bi-check-circle-fill' : (($visitor_move_level === 'warning') ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill')) . '" aria-hidden="true"></i>
                        <div>' . h($visitor_move_text) . '</div>
                    </div>';
    }

    // Removed rows leave their room inside the table file, which keeps its
    // size on disk. Said when that room is a large share of the file, with
    // the button that gives it back.
    if (($visitor_catalog['engine'] !== 'myisam') && ($visitor_catalog['free'] >= 134217728) && ($visitor_catalog['free'] >= ($visitor_catalog['bytes'] / 2))) {
        $visitor_alert .= '
                    <div class="alert alert-info d-flex gap-2 align-items-start">
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        <div>' . h(lang(array(
                            'string' => '{var:1} inside the table file is free space, left by removed records. New visits reuse it; Optimize table gives it back to the disk.',
                            'vars' => array(pg_innodb_size_label($visitor_catalog['free'])),
                        ))) . '</div>
                    </div>';
    }

    if ($visitor_settings['days'] > 0) {
        $visitor_kept = lang(array('string' => '{var:1} days', 'vars' => array($visitor_settings['days'])));
    } else {
        $visitor_kept = lang('Without a limit');
    }

    if (!$visitor_settings['summary']) {
        $visitor_summary = lang('Off');
    } elseif ($visitor_through !== '') {
        $visitor_summary = lang(array('string' => 'On, through {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => strtotime($visitor_through . ' 00:00:00'), 'type' => 'date', 'format' => 'plain_text')))));
    } else {
        $visitor_summary = lang('On, nothing added up yet');
    }

    if ($visitor_last['checked_at'] > 0) {
        $visitor_last_text = lang(array(
            'string' => '{var:1}: {var:2} removed',
            'vars' => array(
                get_relative_time(array('timestamp' => $visitor_last['checked_at'], 'format' => 'plain_text')),
                pg_format_number($visitor_last['deleted'], 0),
            ),
        ));
        if ($visitor_last['backlog']) {
            $visitor_last_text .= '; ' . lang('older records still left, the general job carries on');
        }
    } else {
        $visitor_last_text = lang('Not run yet');
    }

    $visitor_facts = array(
        lang('Records') => pg_format_number($visitor_catalog['rows'], 0),
        lang('Size') => pg_innodb_size_label($visitor_catalog['bytes']),
        lang('Free space') => pg_innodb_size_label($visitor_catalog['free']),
        lang('Oldest record') => ($visitor_preview['oldest'] > 0) ? get_absolute_time(array('timestamp' => $visitor_preview['oldest'], 'type' => 'date', 'format' => 'plain_text')) : '—',
        lang('Kept for') => $visitor_kept,
        lang('Daily summary') => $visitor_summary,
        lang('Last cleanup') => $visitor_last_text,
    );

    $visitor_fact_list = '';

    foreach ($visitor_facts as $visitor_label => $visitor_value) {
        $visitor_fact_list .= '
                            <dt class="col-5 col-md-4 fw-normal text-body-secondary">' . h($visitor_label) . '</dt>
                            <dd class="col-7 col-md-8 mb-1">' . h($visitor_value) . (($visitor_label === lang('Kept for')) ? ' · ' . $visitor_settings_link : '') . '</dd>';
    }

    // What the button would remove, counted now.
    if ($visitor_settings['days'] <= 0) {
        $visitor_will = lang('Nothing is removed while records are kept without a limit.');
    } elseif ($visitor_preview['rows'] > 0) {
        $visitor_will = lang(array(
            'string' => 'Older than {var:1}: {var:2} records, about {var:3}.',
            'vars' => array(
                get_absolute_time(array('timestamp' => $visitor_preview['cutoff'], 'type' => 'date', 'format' => 'plain_text')),
                pg_format_number($visitor_preview['rows'], 0),
                pg_innodb_size_label($visitor_preview['bytes']),
            ),
        ));
    } else {
        $visitor_will = lang(array('string' => 'No record is older than {var:1} days.', 'vars' => array($visitor_settings['days'])));
    }

    $visitor_confirm = lang(array(
        'string' => 'Remove {var:1} visitor records older than {var:2} days now? They cannot be brought back. The work goes in small steps while this page stays open; the site keeps running meanwhile.',
        'vars' => array(pg_format_number($visitor_preview['rows'], 0), $visitor_settings['days']),
    ));

    if ($visitor_settings['summary']) {
        $visitor_confirm .= ' ' . lang('Each day is added to the daily summary first.');
    } else {
        $visitor_confirm .= ' ' . lang('The daily summary is off: those days will be gone from the visitor report.');
    }

    $visitor_enabled = ($engine_tools && ($visitor_settings['days'] > 0) && ($visitor_preview['rows'] > 0));

    $visitor_card = $visitor_alert . '
                    <dl class="row small mb-3">' . $visitor_fact_list . '
                    </dl>
                    <p class="mb-2">' . h($visitor_will) . '</p>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" id="pg_visitor_cleanup" class="btn btn-sm btn-outline-danger text-nowrap" data-confirm="' . h($visitor_confirm) . '"' . ($visitor_enabled ? '' : ' disabled') . '><i class="bi bi-trash3 me-1" aria-hidden="true"></i>' . h(lang('Clean up visitor records now')) . '</button>
                        ' . $engine_button('optimize', 'visitors', lang('Optimize table'), 'bi-arrow-repeat', isset($engine_candidates['visitors']) ? 'btn-outline-warning' : 'btn-outline-secondary', lang('Optimize {var:1} now? The whole table is rewritten: it needs free disk space about the size of the table and takes minutes on a large one. Do it at a quiet hour, after a backup.'), ($engine_tools && ($visitor_catalog['engine'] !== '') && !$visitor_moving)) . (($visitor_catalog['engine'] === 'myisam') && !$visitor_moving ? '
                        ' . $engine_button('visitor_move', 'visitors', lang('Move to InnoDB now'), 'bi-database-gear', 'btn-outline-warning', lang(array(
                            'string' => 'Move the visitors table to InnoDB now? A copy is built next to it a few thousand records at a time while the site keeps running, so the database server needs about {var:1} of free disk until the old table is removed. The general job carries the move on.',
                            'vars' => array(pg_innodb_size_label(pg_visitor_move_disk_needed($visitor_catalog))),
                        )), ($engine_tools && $engine_capability['ok'])) : '') . '
                    </div>
                    <div id="pg_visitor_cleanup_progress" class="small mt-2" role="status" aria-live="polite"></div>
                    <div class="small text-body-secondary mt-2">' . h(lang('Removing records does not make the file on disk smaller by itself: the room is reused by new visits. Optimize table gives it back to the disk; that is a separate, optional step.')) . '</div>';
}

// -- Tables on MyISAM ---------------------------------------------------------

if (count($engine_pending) == 0) {

    $engine_list = '
                    <div class="alert alert-success d-flex gap-2 align-items-start mb-0">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <div>' . h(lang('All tables are on InnoDB.')) . '</div>
                    </div>';

} else {

    $engine_rows = '';

    foreach ($engine_pending as $engine_table => $engine_row) {

        $engine_estimate = $engine_row['row_estimate'];
        $engine_too_wide = (is_array($engine_estimate) && ($engine_estimate['fits'] === false));

        if (!is_array($engine_estimate)) {

            $engine_note = '<span class="small text-body-secondary">—</span>';

        } elseif ($engine_too_wide) {

            $engine_charsets = array();

            foreach ($engine_estimate['charsets'] as $engine_charset => $engine_charset_columns) {
                $engine_charsets[] = lang(array('string' => '{var:1} = {var:2} columns', 'vars' => array($engine_charset, pg_format_number($engine_charset_columns, 0))));
            }

            $engine_note = '<span class="badge text-bg-warning">' . h(lang('Too wide for InnoDB')) . '</span>
                                    <div class="small text-body-secondary">' . h(lang(array(
                                        'string' => '{var:1} / {var:2} bytes, {var:3} of {var:4} columns inline; {var:5}',
                                        'vars' => array(
                                            pg_format_number($engine_estimate['bytes'], 0),
                                            pg_format_number($engine_estimate['limit'], 0),
                                            pg_format_number($engine_estimate['inline_columns'], 0),
                                            pg_format_number($engine_estimate['columns'], 0),
                                            implode(', ', $engine_charsets),
                                        ),
                                    ))) . '</div>';

        } else {

            $engine_note = '<span class="small text-body-secondary">' . h(lang(array(
                'string' => '{var:1} / {var:2} bytes',
                'vars' => array(pg_format_number($engine_estimate['bytes'], 0), pg_format_number($engine_estimate['limit'], 0)),
            ))) . '</span>';

        }

        $engine_rows .= '
                            <tr>
                                <td><code>' . h($engine_table) . '</code></td>
                                <td class="text-end">' . h(pg_format_number($engine_row['rows'], 0)) . '</td>
                                <td class="text-end">' . h(pg_innodb_size_label($engine_row['bytes'])) . '</td>
                                <td>' . h(($engine_row['engine'] !== '') ? $engine_row['engine'] : '-') . '</td>
                                <td>' . $engine_note . '</td>
                                <td class="text-end">
                                    ' . $engine_button('convert', $engine_table, lang('Convert'), 'bi-database-gear', 'btn-outline-warning', $engine_confirm_convert, ($engine_buttons && !$engine_too_wide)) . '
                                </td>
                            </tr>';
    }

    $engine_list = '
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>' . h(lang('Table')) . '</th>
                                    <th class="text-end">' . h(lang('Rows')) . '</th>
                                    <th class="text-end">' . h(lang('Size')) . '</th>
                                    <th>' . h(lang('Engine')) . '</th>
                                    <th>' . h(lang('Note')) . '</th>
                                    <th class="text-end">' . h(lang('Action')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $engine_rows . '
                            </tbody>
                        </table>
                    </div>';
}

// -- Table health -------------------------------------------------------------

$health_when = ($engine_health['checked_at'] > 0)
    ? get_relative_time(array('timestamp' => $engine_health['checked_at'], 'format' => 'plain_text'))
    : '';

$health_header_note = ($health_when !== '')
    ? lang(array('string' => 'checked {var:1}', 'vars' => array($health_when)))
    : lang('not checked yet');

if (count($engine_health['issues']) == 0) {

    $health_list = ($health_when !== '')
        ? '
                    <div class="alert alert-success d-flex gap-2 align-items-start mb-2">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <div>' . h(lang(array('string' => 'All tables are healthy ({var:1} tables, checked {var:2})', 'vars' => array(pg_format_number($engine_health['tables'], 0), $health_when)))) . '</div>
                    </div>'
        : '
                    <div class="alert alert-secondary d-flex gap-2 align-items-start mb-2">
                        <i class="bi bi-hourglass" aria-hidden="true"></i>
                        <div>' . h(lang('The tables have not been checked yet.')) . '</div>
                    </div>';

    $health_list .= '
                    <div class="small text-body-secondary">' . h(lang('The hourly check reads the MyISAM tables; "Check all tables" reads every table, InnoDB included.')) . '</div>';

} else {

    $health_badges = array(
        'error' => '<span class="badge text-bg-danger">' . h(lang('Error')) . '</span>',
        'check_failed' => '<span class="badge text-bg-secondary">' . h(lang('Check failed')) . '</span>',
        'repaired' => '<span class="badge text-bg-warning">' . h(lang('Repaired')) . '</span>',
    );

    $health_rows = '';

    foreach ($engine_health['issues'] as $health_table => $health_states) {

        $health_engine = isset($engine_catalog[$health_table]) ? $engine_catalog[$health_table]['engine'] : '';

        $health_status = '';

        foreach ($health_states as $health_state) {
            $health_status .= $health_badges[$health_state] . ' ';
        }

        $health_actions = '';

        if (isset($engine_catalog[$health_table])) {

            $health_actions = $engine_button('check', $health_table, lang('Check'), 'bi-clipboard-check', 'btn-outline-secondary', $engine_confirm_check, $engine_tools);

            if (in_array($health_engine, $engine_repairable, true)) {
                $health_actions .= ' ' . $engine_button('repair', $health_table, lang('Repair'), 'bi-wrench-adjustable', 'btn-outline-warning', $engine_confirm_repair, $engine_tools);
            }
        }

        $health_rows .= '
                            <tr>
                                <td><code>' . h($health_table) . '</code></td>
                                <td>' . h(($health_engine !== '') ? $health_engine : '-') . '</td>
                                <td>' . $health_status . '</td>
                                <td class="text-end text-nowrap">' . $health_actions . '</td>
                            </tr>';
    }

    $health_list = '
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>' . h(lang('Table')) . '</th>
                                    <th>' . h(lang('Engine')) . '</th>
                                    <th>' . h(lang('Status')) . '</th>
                                    <th class="text-end">' . h(lang('Action')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $health_rows . '
                            </tbody>
                        </table>
                    </div>';
}

// -- All tables ---------------------------------------------------------------

// The only DataTable of the screen: backend.src.js sets up one table.chart
// per page. Rows come largest first; the column order attributes are for
// DataTables' own sorting where it is enabled.
$catalog_rows = '';
$catalog_bytes = 0.0;
$catalog_free = 0.0;

foreach ($engine_catalog as $catalog_table => $catalog_row) {

    $catalog_bytes += $catalog_row['bytes'];
    $catalog_free += $catalog_row['free'];

    if ($catalog_row['engine'] === '') {
        $catalog_engine = '<span class="badge text-bg-danger">' . h(lang('unreadable')) . '</span>';
    } elseif ($catalog_row['engine'] === 'myisam') {
        $catalog_engine = '<span class="badge text-bg-secondary">MyISAM</span>';
    } elseif ($catalog_row['engine'] === 'innodb') {
        $catalog_engine = 'InnoDB';
    } else {
        $catalog_engine = h($catalog_row['engine']);
    }

    $catalog_candidate = isset($engine_candidates[$catalog_table]);

    $catalog_rows .= '
                            <tr>
                                <td>' . $engine_optimize_button($catalog_table, ($engine_tools && ($catalog_row['engine'] !== ''))) . '</td>
                                <td><code>' . h($catalog_table) . '</code></td>
                                <td>' . $catalog_engine . '</td>
                                <td class="text-end" data-order="' . (int) $catalog_row['rows'] . '">' . h(pg_format_number($catalog_row['rows'], 0)) . '</td>
                                <td class="text-end" data-order="' . h(sprintf('%.0f', $catalog_row['bytes'])) . '">' . h(pg_innodb_size_label($catalog_row['bytes'])) . '</td>
                                <td class="text-end' . ($catalog_candidate ? ' text-warning fw-semibold' : '') . '" data-order="' . h(sprintf('%.0f', $catalog_row['free'])) . '">' . h(pg_innodb_size_label($catalog_row['free'])) . '</td>
                                <td class="small text-body-secondary">' . h($catalog_row['collation']) . '</td>
                            </tr>';
}

// -- Server -------------------------------------------------------------------

$fact = function ($value) {
    return (($value === null) || ($value === '')) ? '—' : (string) $value;
};

$fact_switch = function ($value) {
    if ($value === null) {
        return '—';
    }
    return in_array(strtoupper(trim((string) $value)), array('1', 'ON'), true) ? lang('On') : lang('Off');
};

$fact_size = function ($value) {
    return ($value === null) ? '—' : pg_innodb_size_label($value);
};

$fact_uptime = '—';

if ($engine_facts['uptime'] !== null) {
    $fact_uptime = lang(array('string' => '{var:1} d {var:2} h', 'vars' => array(
        pg_format_number(floor((int) $engine_facts['uptime'] / 86400), 0),
        pg_format_number(floor(((int) $engine_facts['uptime'] % 86400) / 3600), 0),
    )));
}

$fact_charset = $fact($engine_facts['charset']);

if ($engine_facts['collation'] !== null) {
    $fact_charset .= ' / ' . $engine_facts['collation'];
}

$fact_rows = array(
    lang('Version') => $fact($engine_facts['version']),
    lang('Database') => $fact($engine_facts['database']),
    lang('Character set') => $fact_charset,
    lang('Connection character set') => $fact($engine_facts['connection_charset']),
    lang('Default engine') => $fact($engine_facts['default_engine']),
    lang('InnoDB row format') => $fact($engine_facts['row_format']),
    'innodb_file_per_table' => $fact_switch($engine_facts['file_per_table']),
    'innodb_strict_mode' => $fact_switch($engine_facts['strict_mode']),
    lang('Buffer pool') => $fact_size($engine_facts['buffer_pool']),
    lang('Page size') => $fact_size($engine_facts['page_size']),
    'max_allowed_packet' => $fact_size($engine_facts['max_allowed_packet']),
    lang('Isolation level') => $fact($engine_facts['isolation']),
    lang('Uptime') => $fact_uptime,
    lang('Connections') => $fact($engine_facts['threads_connected']) . ' / ' . $fact($engine_facts['max_connections']),
);

$fact_list = '';

foreach ($fact_rows as $fact_label => $fact_value) {
    $fact_list .= '
                                    <dt class="col-6 fw-normal text-body-secondary text-break">' . h($fact_label) . '</dt>
                                    <dd class="col-6 mb-1 text-break">' . h($fact_value) . '</dd>';
}

echo pg_page_shell(array(
    'title' => lang('Database Engine'),
    'extra classes' => 'setting',
    'icon' => 'setting',
    'heading' => lang('Database Engine'),
));

echo '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                ' . $engine_button('check_all', '', lang('Check all tables'), 'bi-clipboard-check', 'btn-ghost', lang('A full CHECK TABLE of every table; minutes on a large database.'), $engine_tools) . '
                <a class="btn btn-sm btn-ghost" href="backups.php"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Backup Manager')) . '</a>
                <a class="btn btn-sm btn-ghost" href="welcome.php"><i class="bi bi-heart-pulse me-1" aria-hidden="true"></i>' . h(lang('System Status')) . '</a>' . (((int) $user['role'] === 0) ? '
                <a class="btn btn-sm btn-ghost" href="software_repair.php#rerun"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Run upgrade steps again')) . '</a>' : '') . '
            </nav>

            ' . $engine_alerts . '

            <div class="row g-3 mb-5">
                <div class="col-12 col-lg-8 col-xxl-9 order-1">
                    <div class="card mb-5">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Findings')) . '</span>
                        </div>
                        <div class="card-body">' . $findings_list . '
                        </div>
                    </div>
                    <div class="card mb-5" id="visitor-records">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Visitor records')) . '</span>
                            <code class="small">visitors</code>
                        </div>
                        <div class="card-body">' . $visitor_card . '
                        </div>
                    </div>
                    <div class="card mb-5">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Tables on MyISAM')) . '</span>
                            <a class="btn btn-sm btn-outline-secondary" href="backups.php"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Backup Manager')) . '</a>
                        </div>
                        <div class="card-body">' . $engine_list . '
                        </div>
                    </div>
                    <div class="card mb-5">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Table health')) . '</span>
                            <span class="small text-body-secondary">' . h($health_header_note) . '</span>
                        </div>
                        <div class="card-body">' . $health_list . '
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('All tables')) . '</span>
                        </div>
                        <div class="card-body p-0 position-relative">
                            <table class="chart table-hover table" style="width:100%;display:none;" data-order=\'[[4,"desc"]]\' data-page-length="100">
                                <thead>
                                    <tr>
                                        <th class="noVis">' . h(lang('Action')) . '</th>
                                        <th>' . h(lang('Table')) . '</th>
                                        <th>' . h(lang('Engine')) . '</th>
                                        <th class="text-end">' . h(lang('Rows')) . '</th>
                                        <th class="text-end">' . h(lang('Size')) . '</th>
                                        <th class="text-end">' . h(lang('Free space')) . '</th>
                                        <th>' . h(lang('Collation')) . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $catalog_rows . '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4 col-xxl-3 order-2">
                    <div class="position-sticky" style="top:3.3rem;">
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="small text-body-secondary">' . h(lang('Tables')) . '</div>
                                <div class="h4 mb-2 text-body-emphasis">' . h(pg_format_number(count($engine_catalog), 0)) . '</div>
                                <div class="small text-body-secondary">' . h(lang('Total size')) . '</div>
                                <div class="h4 mb-2 text-body-emphasis">' . h(pg_innodb_size_label($catalog_bytes)) . '</div>
                                <div class="small text-body-secondary">' . h(lang('Free space')) . '</div>
                                <div class="h4 mb-2 text-body-emphasis">' . h(pg_innodb_size_label($catalog_free)) . '</div>
                                <div class="small text-body-secondary">' . h(lang('InnoDB')) . '</div>
                                <div class="h4 mb-2 text-body-emphasis">' . h(pg_format_number($engine_on_innodb, 0)) . ' / ' . h(pg_format_number(count($engine_status), 0)) . '</div>
                                <div class="small text-body-secondary">' . h(lang('MyISAM')) . '</div>
                                <div class="h4 mb-0 text-body-emphasis">' . h(pg_format_number(count($engine_pending), 0)) . '</div>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Server')) . '</span>
                            </div>
                            <div class="card-body">
                                <dl class="row small mb-0">' . $fact_list . '
                                </dl>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body small">
                                <p>' . h(lang('While a table is converted it is copied whole, and every write to it waits until the copy ends. On a large table that takes minutes.')) . '</p>
                                <p>' . h(lang('Take a backup of the database first, and convert one table at a time when the site is quiet.')) . '</p>
                                <p>' . h(lang('The site works on either engine; a table left on MyISAM is not an error.')) . '</p>
                                <p class="mb-0">' . h(lang('OPTIMIZE TABLE rewrites the table. On InnoDB it needs free disk space the size of the table and the site keeps writing to it meanwhile; on MyISAM writes wait.')) . '</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form id="pg_engine_form" action="database_engine.php" method="post" class="d-none disable_shortcut">
                ' . get_token_field() . '
                <input type="hidden" name="action" value="">
                <input type="hidden" name="table" value="">
            </form>
        </div>
    </div>
</main>
<script>
    // One form outside the tables, filled by the button that was clicked.
    document.addEventListener("click", function (event) {
        var button = event.target.closest ? event.target.closest(".pg-engine-action") : null;
        if (!button || button.disabled) {
            return;
        }
        var table = button.getAttribute("data-table") || "";
        var question = (button.getAttribute("data-confirm") || "").replace("{var:1}", table);
        if ((question !== "") && !window.confirm(question)) {
            return;
        }
        var form = document.getElementById("pg_engine_form");
        form.elements["action"].value = button.getAttribute("data-action") || "";
        form.elements["table"].value = table;
        var buttons = document.querySelectorAll(".pg-engine-action");
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = true;
        }
        form.submit();
    });

    // The visitor cleanup: one step per request, as many as it takes, with
    // the running count in the card. The last step leaves its notice for the
    // page that loads afterwards.
    (function () {
        var button = document.getElementById("pg_visitor_cleanup");
        if (!button) {
            return;
        }
        var progress = document.getElementById("pg_visitor_cleanup_progress");
        var failed = ' . json_encode(lang('The cleanup stopped:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
        var starting = ' . json_encode(lang('Cleaning up; keep this page open…'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
        button.addEventListener("click", function () {
            if (button.disabled || !window.confirm(button.getAttribute("data-confirm") || "")) {
                return;
            }
            var form = document.getElementById("pg_engine_form");
            var buttons = document.querySelectorAll(".pg-engine-action, #pg_visitor_cleanup");
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].disabled = true;
            }
            form.elements["action"].value = "visitor_cleanup";
            form.elements["table"].value = "visitors";
            if (!window.fetch || !window.FormData) {
                form.submit();
                return;
            }
            var deleted = 0;
            var summarized = 0;
            var step = function () {
                var data = new FormData(form);
                data.append("ajax", "1");
                data.append("deleted_so_far", String(deleted));
                data.append("summarized_so_far", String(summarized));
                window.fetch(form.getAttribute("action"), {method: "POST", body: data, credentials: "same-origin", headers: {"X-Requested-With": "XMLHttpRequest"}})
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error("HTTP " + response.status);
                        }
                        return response.json();
                    })
                    .then(function (answer) {
                        deleted += answer.deleted;
                        summarized += answer.summarized;
                        progress.textContent = answer.text;
                        if (answer.state === "more") {
                            step();
                        } else {
                            window.location.reload();
                        }
                    })
                    .catch(function (error) {
                        progress.textContent = failed + " " + error.message;
                        progress.className = "small mt-2 text-danger";
                        button.disabled = false;
                    });
            };
            progress.textContent = starting;
            step();
        });
    })();
</script>' . output_footer();

$liveform->remove_form();
