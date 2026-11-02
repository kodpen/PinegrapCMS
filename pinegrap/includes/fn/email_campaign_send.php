<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sending an e-mail campaign to one recipient, shared by Send now
 * (send_email_campaign.php) and the campaign job (email_campaign_job.php):
 * the properties handed to email(), the record of a refused delivery, and
 * the finish counts of a Send now run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The email() properties of one campaign message. The purpose travels with
 * them: email() adds List-Unsubscribe and List-Unsubscribe-Post (RFC 8058)
 * only to a campaign whose purpose is commercial. The caller adds
 * notify_sender when it needs one. With the campaign's id (id or
 * email_campaign_id) the message carries bounce_ref for its return path.
 *
 * @param array  $campaign  from_name, from_email_address, reply_email_address,
 *                          bcc_email_address, format, purpose
 * @param string $to        the recipient's address
 * @param string $to_name   the name used for the recipient
 * @param string $subject   the merged subject
 * @param string $body      the merged body
 * @return array
 */
function pg_email_campaign_mail_properties($campaign, $to, $to_name, $subject, $body)
{
    $properties = array(
        'to' => (string) $to,
        'to_name' => (string) $to_name,
        'bcc' => (string) ($campaign['bcc_email_address'] ?? ''),
        'from_name' => (string) ($campaign['from_name'] ?? ''),
        'from_email_address' => (string) ($campaign['from_email_address'] ?? ''),
        'reply_to' => (string) ($campaign['reply_email_address'] ?? ''),
        'subject' => (string) $subject,
        'format' => (string) ($campaign['format'] ?? ''),
        'body' => (string) $body,
        'type' => 'campaign',
        'purpose' => (string) ($campaign['purpose'] ?? ''),
    );

    // The return path's reference (includes/fn/mail_bounces.php): a bounce
    // is recorded against the campaign. The job's rows name it
    // email_campaign_id, Send now's campaign row id.
    $campaign_id = (int) ($campaign['email_campaign_id'] ?? ($campaign['id'] ?? 0));

    if ($campaign_id > 0) {
        $properties['bounce_ref'] = pg_mail_bounce_ref('campaign', $campaign_id);
    }

    return $properties;
}

/**
 * What a refused delivery does to a recipient: one more attempt, the
 * reason, and either a wait of the mail backoff or, on the last attempt,
 * giving the recipient up.
 *
 * @param int    $attempts_before  attempts stored before this one
 * @param string $error            why email() refused
 * @return array attempts, given_up, retry_in (seconds), last_error
 */
function pg_email_campaign_failure($attempts_before, $error)
{
    $attempt = (int) $attempts_before + 1;
    $given_up = ($attempt >= pg_mail_max_attempts());

    return array(
        'attempts' => $attempt,
        'given_up' => $given_up,
        'retry_in' => $given_up ? 0 : (int) pg_mail_backoff($attempt),
        'last_error' => pg_mail_clip((string) $error, 500),
    );
}

/**
 * Stores a refused delivery on its email_recipients row (needs the retry
 * columns, pg_email_retry_ready()). A recipient given up is complete as far
 * as the campaign is concerned and marked failed; any other waits for
 * next_attempt_at. The job passes its claim token: the row is written only
 * while it holds the claim, and the claim is released.
 *
 * @param int         $recipient_id
 * @param string      $email_address    for the activity log line
 * @param int         $attempts_before  attempts stored before this one
 * @param string|null $claim_token      the job's claim, null for Send now
 * @param string      $user             the activity log user
 * @return bool true when the recipient was given up
 */
function pg_email_campaign_record_failure($recipient_id, $email_address, $attempts_before, $claim_token = null, $user = 'UNKNOWN')
{
    $failure = pg_email_campaign_failure($attempts_before, pg_mail_last_error());

    $sql_set = "attempts = '" . (int) $failure['attempts'] . "', last_error = '" . e($failure['last_error']) . "'";

    if ($failure['given_up']) {
        $sql_set .= ", complete = '1', failed = '1'";
    } else {
        $sql_set .= ", next_attempt_at = UNIX_TIMESTAMP() + " . (int) $failure['retry_in'];
    }

    $sql_where = "id = '" . (int) $recipient_id . "'";

    if ($claim_token !== null) {
        $sql_set .= ", claimed_at = 0, claim_token = ''";
        $sql_where = "(" . $sql_where . ") AND (claim_token = '" . e((string) $claim_token) . "')";
    }

    db("UPDATE email_recipients SET " . $sql_set . " WHERE " . $sql_where);

    if ($failure['given_up']) {
        log_activity(lang(array(
            'string' => 'A campaign e-mail to {var:1} was given up after {var:2} attempts: {var:3}',
            'vars'   => array($email_address, $failure['attempts'], $failure['last_error']),
        )), $user);
    }

    return $failure['given_up'];
}

/**
 * The columns that mark a campaign recipient the send gate stopped: done for
 * the campaign and counted with the undelivered ones, with no attempt made.
 * A given-up recipient always has at least one attempt, so attempts = 0
 * tells a suppressed one apart. Pure.
 *
 * @return array column => value
 */
function pg_email_campaign_suppressed_set()
{
    return array('complete' => 1, 'failed' => 1, 'attempts' => 0);
}

/**
 * Skip a recipient whose address bounced or complained
 * (includes/fn/mail_bounces.php). With the retry columns the recipient is
 * marked as pg_email_campaign_suppressed_set() says, so the campaign counts
 * it; without them it is dropped, as an opted-out recipient is.
 *
 * @param int         $id           email_recipients.id
 * @param string      $address      the recipient's address
 * @param bool        $retry_ready  pg_email_retry_ready()
 * @param string|null $claim_token  the job's claim, released with the mark
 * @return bool  whether the recipient was skipped
 */
function pg_email_campaign_skip_suppressed($id, $address, $retry_ready, $claim_token = null)
{
    if (!function_exists('pg_mail_address_blocked') || !pg_mail_address_blocked($address)) {
        return false;
    }

    $id = (int) $id;

    if (!$retry_ready) {
        db("DELETE FROM email_recipients WHERE id = '" . $id . "'");

        return true;
    }

    $set = array();

    foreach (pg_email_campaign_suppressed_set() as $column => $value) {
        $set[] = $column . " = '" . (int) $value . "'";
    }

    $set[] = "last_error = '" . e(lang('Not sent: the address bounced or complained.')) . "'";

    $where = "(id = '" . $id . "')";

    if ($claim_token !== null) {
        $set[] = "claimed_at = 0";
        $set[] = "claim_token = ''";
        $where .= " AND (claim_token = '" . e((string) $claim_token) . "')";
    }

    db("UPDATE email_recipients SET " . implode(', ', $set) . " WHERE " . $where);

    return true;
}

/**
 * The finish of a Send now run, counted from the campaign's recipients.
 * Only accepted deliveries count as sent, so the percent reaches 100 only
 * when every recipient got the message. The campaign is complete once no
 * recipient waits for another attempt; given-up recipients let it finish,
 * as in the job.
 *
 * @param int $total      recipients of the campaign
 * @param int $delivered  complete and not failed
 * @param int $failed     given up after the last attempt, suppressed ones
 *                        included
 * @param int $waiting    not complete: waiting for another attempt
 * @param int $suppressed of $failed, skipped by the send gate (the address
 *                        bounced or complained)
 * @return array percent, all_sent, complete, heading, message
 */
function pg_email_campaign_send_summary($total, $delivered, $failed, $waiting, $suppressed = 0)
{
    $total = (int) $total;
    $delivered = (int) $delivered;
    $suppressed = max(0, min((int) $suppressed, (int) $failed));
    $failed = (int) $failed - $suppressed;
    $waiting = (int) $waiting;

    $all_sent = ($total > 0) && ($delivered >= $total);

    $percent = ($total > 0) ? (int) round(($delivered / $total) * 100) : 0;

    if (!$all_sent && ($percent >= 100)) {
        $percent = 99;
    }

    if ($all_sent || ($total === 0)) {
        return array(
            'percent' => ($total === 0) ? 100 : $percent,
            'all_sent' => true,
            'complete' => true,
            'heading' => lang('Campaign Sent'),
            'message' => lang('The e-mail campaign has been sent to all recipients.'),
        );
    }

    $message = lang(array(
        'string' => 'Delivered to {var:1} of {var:2} recipients.',
        'vars'   => array(pg_format_number($delivered, 0), pg_format_number($total, 0)),
    ));

    if ($failed > 0) {
        $message .= ' ' . lang(array(
            'string' => '{var:1} e-mail(s) could not be delivered and will not be tried again.',
            'vars'   => array(pg_format_number($failed, 0)),
        ));
    }

    if ($suppressed > 0) {
        $message .= ' ' . lang(array(
            'string' => '{var:1} address(es) were skipped because they bounced or complained.',
            'vars'   => array(pg_format_number($suppressed, 0)),
        ));
    }

    if ($waiting > 0) {
        $message .= ' ' . lang(array(
            'string' => '{var:1} e-mail(s) could not be delivered yet and wait for another attempt.',
            'vars'   => array(pg_format_number($waiting, 0)),
        ));
    }

    return array(
        'percent' => $percent,
        'all_sent' => false,
        'complete' => ($waiting === 0),
        'heading' => lang('Some E-mails Could Not Be Delivered'),
        'message' => $message,
    );
}
