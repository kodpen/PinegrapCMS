<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the System Status widget's jobs.
 *
 *   database_deep_check       the full CHECK TABLE sweep
 *   server_config_repair      writes the missing web.config / .htaccess rules
 *   ca_bundle_config_repair   points CURL_CA_BUNDLE at the bundled cacert.pem
 *   write_permissions_repair  opens the folders and files the server cannot
 *                             write
 *   purge_cache               clears the caches without leaving the dashboard
 *   ca_bundle_update          replaces data/cacert.pem with the current list
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_database_deep_check($request, $action)
{
    // The full CHECK TABLE sweep, on request instead of on every dashboard
    // load. The routine sweep behind the System Status widget uses the
    // cheap MyISAM flags and leaves alone the engines that ignore them;
    // the thorough pass lives here, where an operator decides when the
    // site can afford it.
    $user = validate_user();

    // Same gate as the widget that reports the result.
    if ((int) $user['role'] >= 3) {
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied.'),
        ));
    }

    // A write from the panel session needs the session token like every
    // other one; a password-authenticated API request is waived inside
    // validate_token().
    validate_token();

    // This reads every row and every index of every table. On a large
    // database that is minutes, so it releases the session lock first --
    // otherwise the operator's own next page load queues behind it -- and
    // lifts the execution limit, because a sweep killed halfway leaves the
    // report it was building unwritten.
    session_write_close();

    if (function_exists('set_time_limit')) { // disable_functions on some hosts
        @set_time_limit(0);
    }

    $deep_report = check_and_repair_database_tables(true);

    $deep_issues = 0;
    $deep_repairs = 0;

    foreach ($deep_report as $deep_messages) {
        foreach ($deep_messages as $deep_message) {
            if ($deep_message === 'error') {
                $deep_issues++;
            }
            if ($deep_message === 'repaired') {
                $deep_repairs++;
            }
        }
    }

    // The status widget renders from a cache of its own, and would keep
    // showing what the routine sweep found until it expired.
    $deep_status_cache = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';

    if (file_exists($deep_status_cache)) {
        @unlink($deep_status_cache);
    }

    respond(array(
        'status' => 'success',
        'message' => lang(array(
            'string' => '{var:1} table(s) checked, {var:2} issue(s) found, {var:3} repaired.',
            'vars' => array(
                pg_format_number(count($deep_report), 0),
                pg_format_number($deep_issues, 0),
                pg_format_number($deep_repairs, 0),
            ),
        )),
        // The tile that starts this is one of the health tiles and has a
        // health tile's room -- a couple of words. The sentence above goes
        // in the row beneath it; this is what fits on the tile itself.
        'summary' => lang(array(
            'string' => '{var:1} issue(s)',
            'vars' => array(pg_format_number($deep_issues, 0)),
        )),
    ));
}

function pg_panel_server_config_repair($request, $action)
{
    // Write the missing rules into the web.config / .htaccess in the web
    // root. What gets written and how is in includes/server_config.php;
    // this is the door.
    //
    // Administrator only. The general gate in api.php lets a designer through,
    // and a designer is trusted with the look of the site, not with the
    // file that decides what the whole server will and will not hand out.
    // The rest of the widget stands behind role < 3, so the tile that
    // starts this is drawn for role 0 alone rather than being offered to
    // people it would refuse.
    $user = validate_user();

    if ((int) $user['role'] !== 0) {
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied.'),
        ));
    }

    // A write from the panel session needs the session token like every
    // other one; a password-authenticated API request is waived inside
    // validate_token().
    validate_token();

    $server_config_result = pg_server_config_repair(true);

    // The status widget renders from a ten-minute cache and would keep
    // reporting the rules as missing until it expired -- which reads as
    // the button having done nothing.
    $server_config_cache = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';

    if (file_exists($server_config_cache)) {
        @unlink($server_config_cache);
    }

    if ($server_config_result['status'] !== 'success') {
        respond(array(
            'status' => 'error',
            'message' => $server_config_result['message'],
            'summary' => lang('Failed'),
        ));
    }

    // Shown relative to the web root: an absolute path names the account and
    // the folder the site lives in, and the operator going after the file
    // over FTP starts at the web root anyway. Separators are normalised
    // first -- on Windows dirname() answers in backslashes while the backup
    // path was built with forward ones, and the two never match.
    $server_config_backup = str_replace('\\', '/', $server_config_result['backup']);
    $server_config_root   = rtrim(str_replace('\\', '/', dirname(PG_FUNCTIONS_DIR)), '/') . '/';

    if (strpos($server_config_backup, $server_config_root) === 0) {
        $server_config_backup = substr($server_config_backup, strlen($server_config_root));
    }

    respond(array(
        'status' => 'success',
        'message' => $server_config_result['message']
            . ($server_config_result['backup'] !== ''
                ? ' ' . lang(array(
                    'string' => 'The previous file was kept as {var:1}.',
                    'vars'   => $server_config_backup,
                ))
                : ''),
        'summary' => lang('Done'),
    ));
}

function pg_panel_ca_bundle_config_repair($request, $action)
{
    // Point CURL_CA_BUNDLE in data/config.php at the bundled
    // data/cacert.pem, from the System Status widget. What is written and
    // how is pg_ca_bundle_config_repair() in includes/fn/update.php.
    //
    // Administrator only: this writes the configuration file, which
    // holds the database password.
    $user = validate_user();

    if ((int) $user['role'] !== 0) {
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied.'),
        ));
    }

    // A write from the panel session needs the session token like every
    // other one; a password-authenticated API request is waived inside
    // validate_token().
    validate_token();

    $ca_config_result = pg_ca_bundle_config_repair();

    if ($ca_config_result['status'] === 'success') {
        log_activity(lang('CURL_CA_BUNDLE was pointed at data/cacert.pem from the dashboard.'), $_SESSION['sessionusername']);
    }

    // The widget renders from a ten-minute cache and would keep the row
    // red until it expired.
    $ca_config_cache = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';

    if (file_exists($ca_config_cache)) {
        @unlink($ca_config_cache);
    }

    respond(array(
        'status' => ($ca_config_result['status'] === 'error') ? 'error' : 'success',
        'message' => $ca_config_result['message'],
        'summary' => ($ca_config_result['status'] === 'error') ? lang('Failed') : lang('Done'),
    ));
}

function pg_panel_write_permissions_repair($request, $action)
{
    // Open the folders and files of the software the web server cannot
    // write to, from the System Status widget. The scan and the chmod are
    // pg_write_permission_scan() / pg_write_permission_repair() in
    // functions.php; this is the door.
    //
    // Administrator only, like the rules file: this changes who may write
    // into the software directory, which is not the same authority as
    // clearing a cache.
    $user = validate_user();

    if ((int) $user['role'] !== 0) {
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied.'),
        ));
    }

    // A write from the panel session needs the session token like every
    // other one; a password-authenticated API request is waived inside
    // validate_token().
    validate_token();

    $permissions_result = pg_write_permission_repair();

    log_activity(
        lang(array('string' => 'Write permissions repaired from the dashboard ({var:1}).', 'vars' => array($permissions_result['message']))),
        $_SESSION['sessionusername']
    );

    // The widget renders from a ten-minute cache and would keep reporting
    // the folders as closed until it expired -- which reads as the button
    // having done nothing.
    $permissions_cache = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';

    if (file_exists($permissions_cache)) {
        @unlink($permissions_cache);
    }

    respond(array(
        'status' => ($permissions_result['status'] === 'error') ? 'error' : 'success',
        'message' => $permissions_result['message'],
        'summary' => ($permissions_result['status'] === 'success') ? lang('Done') : (($permissions_result['status'] === 'partial') ? lang('Partly') : lang('Failed')),
    ));
}

function pg_panel_purge_cache($request, $action)
{
    // Clearing the caches from the System Status widget, without leaving
    // the dashboard.
    //
    // purge_cache.php does the same work and then redirects to
    // settings.php. That is the right ending for a link pressed on the
    // settings screen and the wrong one for a tile on a card: the operator
    // pressed a button on the dashboard and landed on another page, with
    // the widget they were reading left behind. Both doors call
    // pg_purge_caches(), so the two cannot come to clear different things.
    //
    // Manager and above, matching purge_cache.php's own
    // validate_area_access($user, 'manager') -- written here as a role test
    // because that function answers in HTML, and an HTML refusal reaches a
    // caller expecting JSON as "unexpected token <".
    $user = validate_user();

    if ((int) $user['role'] >= 3) {
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied.'),
        ));
    }

    // A write from the panel session needs the session token like every
    // other one; a password-authenticated API request is waived inside
    // validate_token().
    validate_token();

    $purge_result = pg_purge_caches();

    log_activity(
        lang(array('string' => 'Cache purged ({var:1}).', 'vars' => array($purge_result['message']))),
        $_SESSION['sessionusername']
    );

    respond(array(
        'status'  => 'success',
        'message' => lang(array('string' => 'Cache cleared: {var:1}', 'vars' => array($purge_result['message']))),
        // Two words is what the row's own state line holds; the sentence
        // above goes in the panel that opens under it.
        'summary' => lang('Cleared'),
    ));
}

function pg_panel_ca_bundle_update($request, $action)
{
    // Replace data/cacert.pem with the current Mozilla root list, from the
    // System Status widget. The download, the checks and the atomic swap
    // are pg_ca_bundle_update() in includes/fn/update.php; this is the
    // door.
    //
    // Administrator only, and the token is checked as well as the
    // session: this writes the file that decides which certificates every
    // outbound connection will trust, which is the same authority as the
    // web server rules file, not the same as clearing a cache. The row is
    // drawn for every role that sees the widget, its button for role 0
    // alone, so nobody is offered a control that would refuse them.
    $user = validate_user();

    if ((int) $user['role'] !== 0) {
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied.'),
        ));
    }

    validate_token();

    // The download may take a while on a slow link; the operator's own
    // next page load should not queue behind it.
    session_write_close();

    $ca_bundle_result = pg_ca_bundle_update();

    log_activity(
        lang(array('string' => 'CA bundle update from the dashboard ({var:1}).', 'vars' => array($ca_bundle_result['message']))),
        $_SESSION['sessionusername']
    );

    if ($ca_bundle_result['status'] === 'error') {
        respond(array(
            'status'  => 'error',
            'message' => $ca_bundle_result['message'],
            'summary' => lang('Failed'),
        ));
    }

    respond(array(
        'status'  => 'success',
        'message' => $ca_bundle_result['message'],
        // Two words for the row's own state line; the sentence above goes
        // in the panel that opens under it.
        'summary' => ($ca_bundle_result['status'] === 'unchanged') ? lang('Already current') : lang('Updated'),
    ));
}
