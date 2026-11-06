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
 * The UPDATE that claims campaign recipients for one run, Send now or the
 * job. One statement, conditional on nobody having claimed or finished the
 * rows since they were read: it is atomic even on MyISAM, so when two runs
 * overlap each row goes to whichever statement reaches it first, and the
 * token says which rows a run won. Without it Send now and a job run that
 * read the same recipients both delivered to them. '' when there is no id.
 * Pure.
 *
 * @param array  $ids    email_recipients.id values
 * @param string $token  the run's claim token
 * @return string
 */
function pg_email_campaign_claim_query($ids, $token)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), function ($id) {
        return $id > 0;
    })));

    if (!$ids) {
        return '';
    }

    return "UPDATE email_recipients
        SET claimed_at = UNIX_TIMESTAMP(), claim_token = '" . e((string) $token) . "'
        WHERE
            (id IN (" . implode(',', $ids) . "))
            AND (complete = '0')
            AND (claimed_at = 0)
            AND (next_attempt_at <= UNIX_TIMESTAMP())";
}

/**
 * The UPDATE that gives back claims a run left behind (it died, or was
 * killed, between claiming its recipients and finishing with them) after
 * fifteen minutes, far longer than a run takes. With a campaign id only that
 * campaign's recipients: Send now frees its own campaign, so a claim left by
 * a crashed window does not hold a recipient forever while the job is off.
 * Pure.
 *
 * @param int $campaign_id  0 for every campaign
 * @return string
 */
function pg_email_campaign_stale_claim_query($campaign_id = 0)
{
    $campaign_id = (int) $campaign_id;

    return "UPDATE email_recipients
        SET claimed_at = 0, claim_token = ''
        WHERE
            (complete = '0')
            AND (claimed_at > 0)
            AND (claimed_at < (UNIX_TIMESTAMP() - 900))" . (($campaign_id > 0) ? "
            AND (email_campaign_id = '" . $campaign_id . "')" : "");
}

/**
 * A new claim token: 32 hex characters.
 *
 * @return string
 */
function pg_email_campaign_claim_token()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(16));
    }

    return bin2hex(openssl_random_pseudo_bytes(16));
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

    // A booking's mail carries the booking as a calendar file, read at the
    // send (includes/fn/booking_messages.php).
    $ics = function_exists('pg_booking_ics_attachment') ? pg_booking_ics_attachment($campaign) : null;

    if ($ics) {
        $properties['attachments'] = array($ics);
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
 * next_attempt_at. A run passes its claim token: the row is written only
 * while it holds the claim, and the claim is released.
 *
 * @param int         $recipient_id
 * @param string      $email_address    for the activity log line
 * @param int         $attempts_before  attempts stored before this one
 * @param string|null $claim_token      the run's claim, null without the retry columns
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
 * @param string|null $claim_token  the run's claim, released with the mark
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
 * @param bool $job_enabled  whether the campaign job retries waiting
 *                        recipients by itself; without it only another Send
 *                        now does, and the text says so
 * @param string $next_attempt  the earliest time a waiting recipient may be
 *                        tried again, formatted for the reader ('' for none)
 * @return array percent, all_sent, complete, heading, message
 */
function pg_email_campaign_send_summary($total, $delivered, $failed, $waiting, $suppressed = 0, $job_enabled = true, $next_attempt = '')
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

    if (($waiting > 0) && $job_enabled) {
        $message .= ' ' . lang(array(
            'string' => '{var:1} e-mail(s) could not be delivered yet and wait for another attempt.',
            'vars'   => array(pg_format_number($waiting, 0)),
        ));
    } elseif ($waiting > 0) {
        // Nothing retries them while the job is off: only another Send now,
        // once their backoff has passed.
        $message .= ' ' . lang(array(
            'string' => '{var:1} e-mail(s) could not be delivered yet. To try them again, press Send Campaign again.',
            'vars'   => array(pg_format_number($waiting, 0)),
        ));

        if ((string) $next_attempt !== '') {
            $message .= ' ' . lang(array(
                'string' => 'They can be tried again from {var:1}.',
                'vars'   => array((string) $next_attempt),
            ));
        }
    }

    return array(
        'percent' => $percent,
        'all_sent' => false,
        'complete' => ($waiting === 0),
        'heading' => lang('Some E-mails Could Not Be Delivered'),
        'message' => $message,
    );
}

/**
 * Why the send window does not send a campaign of this status: '' for a
 * ready campaign and for a complete one (the window shows its summary),
 * else the translated reason. The edit screen offers Send Campaign only for
 * a ready campaign; this keeps the window to the same rule for a link
 * opened earlier.
 *
 * @param string $status
 * @return string
 */
function pg_email_campaign_send_refusal($status)
{
    switch ((string) $status) {
        case 'ready':
        case 'complete':
            return '';

        case 'paused':
            return lang('The campaign is paused. To send it, set its status to Ready to Send.');

        case 'cancelled':
            return lang('The e-mail campaign may not be sent because it is cancelled.  Please update the status in order to send the e-mail campaign.');
    }

    return lang('The campaign may not be sent in its current status.');
}

/**
 * The line naming the most frequent delivery error of a campaign and how
 * many recipients it stopped; '' when there is none.
 *
 * @param string $error  email_recipients.last_error
 * @param int    $count
 * @return string plain text
 */
function pg_email_campaign_problem_line($error, $count)
{
    $error = trim((string) $error);

    if (($error === '') || ((int) $count < 1)) {
        return '';
    }

    return lang(array(
        'string' => 'Reason ({var:2} e-mail(s)): {var:1}',
        'vars'   => array($error, pg_format_number((int) $count, 0)),
    ));
}

/**
 * The most frequent error of the recipients still waiting or given up, with
 * its count, and the earliest time a waiting recipient may be tried again.
 * Needs the retry columns (pg_email_retry_ready()); without them all three
 * are empty.
 *
 * @param int $campaign_id
 * @return array error, count, next_attempt_at (0 when none waits)
 */
function pg_email_campaign_delivery_problem($campaign_id)
{
    $problem = array('error' => '', 'count' => 0, 'next_attempt_at' => 0);

    if (!pg_email_retry_ready()) {
        return $problem;
    }

    $campaign_id = (int) $campaign_id;

    $row = db_item(
        "SELECT last_error, COUNT(*) AS number
        FROM email_recipients
        WHERE " . pg_email_campaign_problem_where($campaign_id) . "
        GROUP BY last_error
        ORDER BY number DESC
        LIMIT 1");

    if ($row) {
        $problem['error'] = (string) $row['last_error'];
        $problem['count'] = (int) $row['number'];
    }

    $problem['next_attempt_at'] = (int) db_value(
        "SELECT MIN(next_attempt_at)
        FROM email_recipients
        WHERE
            (email_campaign_id = '" . $campaign_id . "')
            AND (complete = '0')");

    return $problem;
}

/**
 * The recipients whose delivery error the reason line counts: waiting or
 * given up with a stored error and at least one attempt. A recipient the
 * send gate skipped (pg_email_campaign_suppressed_set(): attempts = 0)
 * is left out: the summary counts it on its own line, and its "bounced or
 * complained" text outnumbered and hid the SMTP error the line offers to
 * fix. Pure.
 *
 * @param int $campaign_id
 * @return string SQL condition
 */
function pg_email_campaign_problem_where($campaign_id)
{
    return "(email_campaign_id = '" . (int) $campaign_id . "')
            AND (last_error != '')
            AND ((complete = '0') OR (failed = '1'))
            AND (attempts > 0)";
}
