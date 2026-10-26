<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Records a visitor's answer to the cookie notice in the consent log.
 *
 * The notice's script (assets/js/pg_consent.js) posts here after Save,
 * Accept all or Reject all, with the pg_consent value it wrote (answer) and
 * the one before it (before). One consent_log row is written per category
 * whose answer changed (pg_consent_answer_changes()): channel cookie, source
 * banner. A signed-in visitor's rows carry their user id and person token;
 * an anonymous visitor's carry neither. The stored answer a page re-reads on
 * every view is never posted.
 *
 * Anonymous by design, so there is no form token (a cached page has none):
 * posts from other sites are refused by pg_consent_answer_same_origin().
 * Scripts are not stopped by that, and the firewall refuses nothing while it
 * is off or in monitor mode, so each address may record only
 * PG_CONSENT_ANSWER_WRITE_LIMIT answers an hour (pg_consent_answer_throttled());
 * past it the answer is 429 and nothing is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

$status = 200;
$reply = array('written' => 0);
$changes = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $status = 405;
    $reply = array('error' => 'method');
} elseif (!pg_consent_answer_same_origin($_SERVER)) {
    $status = 403;
    $reply = array('error' => 'origin');
} elseif (pg_post_body_discarded()) {
    $status = 413;
    $reply = array('error' => 'size');
} else {
    $changes = pg_consent_answer_changes($_POST['before'] ?? '', $_POST['answer'] ?? '');

    if ($changes === null) {
        $status = 400;
        $reply = array('error' => 'answer');
    }
}

// A site that no longer asks, or has not run the 11.0.16 step, records nothing.
if ($changes && pg_consent_enabled() && pg_consent_log_ready() && pg_consent_answer_throttled()) {
    $status = 429;
    $reply = array('error' => 'rate');
} elseif ($changes && pg_consent_enabled() && pg_consent_log_ready()) {
    $person = array();

    // While staff browse as a customer the answer is the staff member's, not
    // the customer's: it is recorded without a person. The user comes from
    // initialize_user(), loaded by id, so a renamed account still matches.
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && empty($_SESSION['software']['ghost'])) {
        $person = array('user_id' => (int) USER_ID, 'email' => (string) USER_EMAIL_ADDRESS);
    }

    foreach ($changes as $change) {
        if (pg_consent_log_record($change + array('source' => 'banner') + $person) > 0) {
            $reply['written']++;
        }
    }
}

http_response_code($status);
echo json_encode($reply);
