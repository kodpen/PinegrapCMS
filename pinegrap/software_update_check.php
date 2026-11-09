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
 *     'error'      => string,      // '', 'checks_off', 'curl_missing', 'curl_error',
 *                                  // 'http_error' or 'invalid_response'
 *     'curl_errno' => int,
 *     'curl_error' => string,
 *     'http_status' => int)        // the status of an http_error answer
 * With update checks off in data/config.php (SOFTWARE_UPDATE_CHECK false)
 * nothing is asked: the answer is the checks_off error. A failed check
 * writes one Site Log line (pg_update_check_error_text()).
 * and, from the Kodpen API, 'decision' (pg_vendor_release_offer()): what the
 * answer offers, blocked or refused releases included.
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

    // The Kodpen API once PG_VENDOR_API_V1 is on (includes/fn/vendor_api.php).
    // A release that is an update, or one blocked behind a stepping stone,
    // raises the flag and the notification; an answer that offers nothing
    // clears them; an unusable answer takes the error path of an unanswered
    // request. The screen reads the decision (pg_vendor_release_offer()).
    if (PG_VENDOR_API_V1 === true) {
        $server = pg_update_server_version('update');

        $details['curl_errno'] = $server['curl_errno'];
        $details['curl_error'] = $server['curl_error'];

        db("DELETE FROM notifications WHERE action = 'software_update'");

        if ($server['error'] !== '') {
            $details['error'] = $server['error'];
            log_activity(pg_update_check_error_text($details));

            return $return_details ? $details : false;
        }

        $decision = $server['decision'];
        $flag = in_array($decision['outcome'], array('update', 'blocked'), true);

        if ($flag) {
            create_notification(array('action' => 'software_update', 'type' => 'success', 'user' => '[SOFTWARE]', 'title' => 'available'));
        }

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

    $software_update_available = false;

    $request = array();

    $request['hostname'] = HOSTNAME_SETTING;
    $request['url'] = URL_SCHEME . HOSTNAME_SETTING . PATH;
    $request['version'] = VERSION;
    $request['edition'] = EDITION;
    $request['uname'] = function_exists('php_uname') ? php_uname() : PHP_OS; // disable_functions on some hosts
    $request['os'] = PHP_OS;
    $request['web_server'] = $_SERVER['SERVER_SOFTWARE'];
    $request['php_version'] = phpversion();
    $request['mysql_version'] = db("SELECT VERSION()");
    $request['installer'] = INSTALLER;
    $request['private_label'] = PRIVATE_LABEL;


    $data = encode_json($request);
    // Beta sites ask their own question; see pg_update_channel().
    $REQUEST = function_exists('pg_update_request_key') ? pg_update_request_key() : 'latest_version';

    // Identify this installation (pg_curl_run() sends pinegrap_user_agent()).
    // Sent with no User-Agent, a licence or update request looks like an
    // anonymous client to the receiving server's own firewall and gets
    // rejected. The certificate is verified with pg_curl_tls(); see there for
    // why this matters most on the update and licence channel.
    $answer = pg_curl_run('POST', 'https://www.kodpen.com/api2?REQUEST=' . $REQUEST, array(
        'body'            => $data,
        'headers'         => array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ),
        'timeout'         => 10,
        'connect_timeout' => 10,
        'forbid_reuse'    => true,
    ));
    $details['curl_errno'] = $answer['errno'];
    $details['curl_error'] = $answer['error'];

    // A failed request, an error page (a 500 with an HTML or text body) and an
    // answer that names no version each say so in the Site Log with their
    // own reason.
    $response = null;

    if ($answer['errno'] !== 0) {
        $details['error'] = 'curl_error';
    } elseif (!$answer['ok']) {
        $details['error'] = 'http_error';
        $details['http_status'] = (int) $answer['status'];
    } else {
        $response = decode_json($answer['body']);

        if (!is_array($response) || !isset($response['version'])) {
            $details['error'] = 'invalid_response';
        }
    }

    if ($details['error'] !== '') {
        db("DELETE FROM notifications WHERE action = 'software_update'");
        log_activity(pg_update_check_error_text($details));

        return $return_details ? $details : false;
    }

    $details['response'] = $response;

    // The answer replaces the "update available" notification: without this
    // every check, and every opening of the Software Updater, added one more
    // row to the bell while an update waited, and an answer that offers
    // nothing left the old one standing.
    db("DELETE FROM notifications WHERE action = 'software_update'");

    // Numerically, part by part, and without a warning when either version
    // has fewer than three parts (pg_update_version_newer()).
    $new_version_is_greater_than_old_version = pg_update_version_newer($response['version'], VERSION);

    // assume that there is not an available software update until we find out otherwise
    $software_update_available = 0;

    // if the new version is greater than the old version, then there is an available software update
    if ($new_version_is_greater_than_old_version == TRUE) {
        $software_update_available = 1;
        create_notification(array('action' => 'software_update', 'type' => 'success', 'user' => '[SOFTWARE]', 'title' => 'available'));
    }

    // update database to remember whether there is a software update available or not
    db("UPDATE config SET software_update_available = '" . $software_update_available . "'");
    // The tile of System Status reads the answer, not the attempt stamped above.
    pg_update_check_answered();

    // Set value to boolean so we can return it later.
    settype($software_update_available, 'boolean');


    // If messages were included in the response, then deal with them.
    if (isset($response['messages']) == true) {
        $messages = $response['messages'];

        // If the messages data is valid and formed a proper array, then continue.
        if (is_array($messages) == true) {
            // Delete current messages for this site, so we can later add new ones.
            db("DELETE FROM messages");

            $sql_exceptions = "";

            // Loop through new messages in order to add them to this site.
            foreach ($messages as $message) {

                db(
                    "INSERT INTO messages (
                        id,
                        title,
                        frequency,
                        url,
                        width,
                        height)
                    VALUES (
                        '" . e($message['id']) . "',
                        '" . e($message['title']) . "',
                        '" . e($message['frequency']) . "',
                        '" . e($message['url']) . "',
                        '" . e($message['width']) . "',
                        '" . e($message['height']) . "')"
                );

                // Prepare sql exception statement so that we don't delete
                // user records for this message further below.

                if ($sql_exceptions != '') {
                    $sql_exceptions .= " AND ";
                }

                $sql_exceptions .= "(message_id != '" . e($message['id']) . "')";
            }

            $sql_where = "";

            // If there was at least one new message, then prepare where
            // clause to prevent deletion of records for active messages.
            if ($sql_exceptions != '') {
                $sql_where = "WHERE $sql_exceptions";
            }

            // Delete records of users viewing messages for messages
            // that no longer exist, because they are not needed anymore,
            // and we don't want that db table to get huge.
            db("DELETE FROM users_messages_xref $sql_where");
        }
    }

    $details['available'] = (bool) $software_update_available;

    return $return_details ? $details : $software_update_available;

}