<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the Security Scan section of Site Settings > Firewall and
 * HTTPS (includes/fn/security_scan_section.php).
 *   security_scan_step   Scan now: works on the scan for a few seconds a
 *                        request; the section asks again until the answer
 *                        says done, then draws the list it returns.
 *   security_scan_act    Quarantine, Restore, Ignore or Reopen on one
 *                        finding; answers with the list drawn again.
 *
 * Both are open to the roles the Firewall settings are (Manager and above):
 * the general gate checks the session, these the role and the token.
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

// Seconds of one Scan now request.
define('PG_SECURITY_SCAN_STEP_SECONDS', 8);

/**
 * The session user when the role may use the Firewall settings; answers 403
 * in JSON otherwise. The handler checks the token after it.
 *
 * @return array
 */
function pg_panel_security_scan_user()
{
    $user = validate_user();

    if ((int) $user['role'] > 2) {
        http_response_code(403);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    return $user;
}

/**
 * The section's run line and list as the answer carries them.
 *
 * @param array $state Run state
 * @return array
 */
function pg_panel_security_scan_view(array $state)
{
    return array(
        'line' => pg_security_section_run_line($state),
        'list' => pg_security_section_list(pg_security_section_rows()),
    );
}

function pg_panel_security_scan_step($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = pg_panel_security_scan_user();
    validate_token();

    if (!pg_security_finding_ready()) {
        http_response_code(409);
        respond(array('status' => 'error', 'message' => lang('The scan is ready once the upgrade has run.')));
    }

    // The operator's next page must not queue behind the scan.
    session_write_close();

    $run = pg_security_scan_run(PG_SECURITY_SCAN_STEP_SECONDS);
    $answer = array('status' => 'success', 'done' => $run['done'], 'busy' => $run['busy']);

    if ($run['done']) {
        // A full scan ran now: the scheduled task need not run another today.
        pg_cron_ran('security_scan_job');

        return $answer + pg_panel_security_scan_view($run['state']);
    }

    return $answer + array('line' => h(pg_security_scan_progress($run['state'])));
}

function pg_panel_security_scan_act($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    $user = pg_panel_security_scan_user();
    validate_token();
    $act = isset($request['act']) && is_string($request['act']) ? $request['act'] : '';
    $result = pg_security_finding_act((int) ($request['id'] ?? 0), $act, $request['note'] ?? '', (int) $user['id']);

    if (!$result['ok']) {
        http_response_code(409);
    }

    return array('status' => $result['ok'] ? 'success' : 'error', 'message' => $result['message'])
        + pg_panel_security_scan_view(pg_security_scan_stored_state());
}
