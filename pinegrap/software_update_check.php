<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/**
 * Ask the update server whether a newer version exists.
 *
 * Returns a boolean by default, which is what the scheduled check expects.
 * With $return_details the whole outcome comes back instead, so a screen
 * that needs the decoded response (the version number, the download
 * information) can reuse this request rather than repeating it:
 *   array(
 *     'available'  => bool,        // a newer version was announced
 *     'response'   => array|null,  // decoded server response, null on failure
 *     'error'      => string,      // '', 'checks_off', 'curl_missing', 'curl_error'
 *                                  // or 'invalid_response'
 *     'curl_errno' => int,
 *     'curl_error' => string,
 *     'http_status' => int)
 * With update checks off in data/config.php (SOFTWARE_UPDATE_CHECK false)
 * nothing is asked: the answer is the checks_off error. A failed check
 * writes one Site Log line (pg_update_check_error_text()). An answer adds
 * 'decision' (pg_vendor_release_offer()): what it offers, blocked or refused
 * releases included.
 */
function software_update_check($return_details = false)
{
    $details = array(
        'available' => false,
        'response' => null,
        'error' => '',
        'curl_errno' => 0,
        'curl_error' => '',
        'http_status' => 0);

    if (defined('SOFTWARE_UPDATE_CHECK') && (SOFTWARE_UPDATE_CHECK != true)) {
        $details['error'] = 'checks_off';
        return $return_details ? $details : false;
    }

    // Update the config table to remember that the check has been completed today.
    db("UPDATE config SET last_software_update_check_timestamp = UNIX_TIMESTAMP()");

    if (!function_exists('curl_init')) {
        $details['error'] = 'curl_missing';
        log_activity(pg_update_check_error_text($details));
        return $return_details ? $details : false;
    }

    // The Kodpen API (includes/fn/vendor_api.php). A release that is an
    // update, or one blocked behind a stepping stone, raises the flag and the
    // notification; an answer that offers nothing clears them; an unusable
    // answer takes the error path of an unanswered request. The screen reads
    // the decision (pg_vendor_release_offer()).
    $server = pg_update_server_version('update');

    $details['curl_errno'] = $server['curl_errno'];
    $details['curl_error'] = $server['curl_error'];

    if ($server['error'] !== '') {
        pg_update_notice_set(false);
        $details['error'] = $server['error'];
        log_activity(pg_update_check_error_text($details));

        return $return_details ? $details : false;
    }

    $decision = $server['decision'];
    $flag = in_array($decision['outcome'], array('update', 'blocked'), true);

    // One row while the update waits: a check that finds it again keeps
    // the row and its read marks, so it does not come back unread.
    pg_update_notice_set($flag);

    db("UPDATE config SET software_update_available = '" . ($flag ? 1 : 0) . "'");
    // The tile of System Status reads the answer, not the attempt stamped above.
    pg_update_check_answered();

    $details['decision'] = $decision;
    $details['available'] = ($decision['outcome'] === 'update');

    if (is_array($decision['release'])) {
        $details['response'] = array('version' => $decision['release']['version'] . (($decision['release']['build'] !== '') ? ' (' . $decision['release']['build'] . ')' : ''));
    }

    return $return_details ? $details : $details['available'];
}