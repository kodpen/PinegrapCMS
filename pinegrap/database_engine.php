<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Database Engine: the tables that are still on MyISAM, and a button to move
 * each of them to InnoDB.
 *
 * The 2026.4.8 upgrade converts every table it can within its limits; a table
 * over them, or one an earlier attempt did not finish, is left here. Moving a
 * large table copies it whole while writes to it wait, which can take minutes,
 * so the operator chooses the moment: one table per click, at a quiet hour,
 * after a backup. The work is pg_innodb_convert_table() (includes/fn/innodb.php),
 * the same function the upgrade uses, without the size limits.
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

    $engine_table = (isset($_POST['table']) && is_string($_POST['table'])) ? $_POST['table'] : '';

    // Only the tables the software itself converts. The name also goes into
    // the ALTER statement, which accepts nothing outside [a-z0-9_] anyway.
    if (!in_array($engine_table, pg_innodb_core_tables(), true)) {
        $liveform->add_error(lang('That table is not one of the tables this screen converts.'));
        go($database_engine_url);
    }

    // The copy can take minutes. The session file stays locked for as long
    // as this request holds it, which would freeze every other tab of the
    // panel; it is reopened afterwards to carry the result to the next page.
    // A closed tab must not end the request halfway through either: the
    // server would finish the ALTER regardless, and nothing would remove the
    // marker that says an attempt is under way.
    session_write_close();

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    $engine_result = pg_innodb_convert_table($engine_table, array('retry' => true));

    $engine_text = pg_innodb_state_text($engine_result);

    if (function_exists('session_status') && (session_status() === PHP_SESSION_NONE)) {
        @session_start();
    }

    log_activity(lang(array('string' => 'Database Engine: {var:1}', 'vars' => array($engine_text))), $_SESSION['sessionusername']);

    if ($engine_result['state'] === 'converted') {
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

// An ALTER on one of these tables that another connection is still running
// (a request the web server gave up on, the upgrade, another tab). Starting
// another conversion now would only queue behind it, so the buttons wait.
$engine_running = null;
$engine_running_table = '';

foreach ($engine_pending as $engine_table => $engine_row) {
    $engine_running = pg_innodb_running_alter($engine_table);
    if ($engine_running !== null) {
        $engine_running_table = $engine_table;
        break;
    }
}

// A marker with nothing running behind it: an attempt that never finished.
$engine_marker = @json_decode((string) @file_get_contents(pg_innodb_temp_marker()), true);
$engine_marker_table = (($engine_running === null) && is_array($engine_marker) && isset($engine_marker['table']) && isset($engine_pending[$engine_marker['table']]))
    ? (string) $engine_marker['table']
    : '';

$engine_buttons = ($engine_capability['ok'] && ($engine_running === null));

$engine_alerts = '';

if (!$engine_capability['ok']) {
    $engine_alerts .= '
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <div>' . h(lang(array('string' => 'The tables cannot be moved to InnoDB on this database server: {var:1}', 'vars' => array($engine_capability['reason'])))) . '</div>
            </div>';
}

if ($engine_running !== null) {
    $engine_alerts .= '
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <div>' . h(lang(array('string' => 'An ALTER TABLE on {var:1} has been running for {var:2} s', 'vars' => array($engine_running_table, (int) $engine_running['TIME'])))) . '
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

if (count($engine_pending) == 0) {

    $engine_list = '
                    <div class="alert alert-success d-flex gap-2 align-items-start mb-0">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <div>' . h(lang('All tables are on InnoDB.')) . '</div>
                    </div>';

} else {

    $engine_rows = '';

    foreach ($engine_pending as $engine_table => $engine_row) {
        $engine_rows .= '
                            <tr>
                                <td><code>' . h($engine_table) . '</code></td>
                                <td class="text-end">' . h(pg_format_number($engine_row['rows'], 0)) . '</td>
                                <td class="text-end">' . h(pg_innodb_size_label($engine_row['bytes'])) . '</td>
                                <td>' . h(($engine_row['engine'] !== '') ? $engine_row['engine'] : '-') . '</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-warning pg-engine-convert" data-table="' . h($engine_table) . '"' . ($engine_buttons ? '' : ' disabled') . '>
                                        <i class="bi bi-database-gear me-1" aria-hidden="true"></i>' . h(lang('Convert')) . '
                                    </button>
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
                                    <th class="text-end">' . h(lang('Action')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $engine_rows . '
                            </tbody>
                        </table>
                    </div>';
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
            ' . $engine_alerts . '

            <div class="row g-3 mb-5">
                <div class="col-12 col-lg-8 col-xxl-9 order-1">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Tables on MyISAM')) . '</span>
                            <a class="btn btn-sm btn-outline-secondary" href="backups.php"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Backup Manager')) . '</a>
                        </div>
                        <div class="card-body">' . $engine_list . '
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4 col-xxl-3 order-2">
                    <div class="position-sticky" style="top:3.3rem;">
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="small text-body-secondary">' . h(lang('InnoDB')) . '</div>
                                <div class="h4 mb-2 text-body-emphasis">' . h(pg_format_number($engine_on_innodb, 0)) . ' / ' . h(pg_format_number(count($engine_status), 0)) . '</div>
                                <div class="small text-body-secondary">' . h(lang('MyISAM')) . '</div>
                                <div class="h4 mb-0 text-body-emphasis">' . h(pg_format_number(count($engine_pending), 0)) . '</div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body small">
                                <p>' . h(lang('While a table is converted it is copied whole, and every write to it waits until the copy ends. On a large table that takes minutes.')) . '</p>
                                <p>' . h(lang('Take a backup of the database first, and convert one table at a time when the site is quiet.')) . '</p>
                                <p class="mb-0">' . h(lang('The site works on either engine; a table left on MyISAM is not an error.')) . '</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form id="pg_engine_form" action="database_engine.php" method="post" class="d-none disable_shortcut">
                ' . get_token_field() . '
                <input type="hidden" name="table" value="">
            </form>
        </div>
    </div>
</main>
<script>
    // One form outside the table, filled by the row that was clicked.
    document.addEventListener("click", function (event) {
        var button = event.target.closest ? event.target.closest(".pg-engine-convert") : null;
        if (!button || button.disabled) {
            return;
        }
        var table = button.getAttribute("data-table") || "";
        var question = "' . escape_javascript(lang('Convert {var:1} to InnoDB now? Writes to the table wait until the conversion ends.')) . '".replace("{var:1}", table);
        if (!window.confirm(question)) {
            return;
        }
        var form = document.getElementById("pg_engine_form");
        form.elements["table"].value = table;
        var buttons = document.querySelectorAll(".pg-engine-convert");
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = true;
        }
        form.submit();
    });
</script>' . output_footer();

$liveform->remove_form();
