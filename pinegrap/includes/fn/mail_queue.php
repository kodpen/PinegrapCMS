<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the outgoing mail queue (mail_outbox), the retry
// schedule shared by the queue and the e-mail campaign job, and the
// List-Unsubscribe headers of commercial campaigns.
//
// Loaded by functions.php through require_once, never on its own.
//
// Why a queue: email() talks to an SMTP server inside the request that asked
// for the message. A slow or unreachable server then holds the visitor's
// request - and its database connection - for as long as PHPMailer waits,
// and a message that fails is lost. A caller that passes 'queue' => true
// gets a row here instead, and the general job sends it, retrying on the
// same schedule as the webhook deliveries.
//
// Nothing here uses PHPMailer: the 'use' aliases of mail.php do not reach
// this file, so an unqualified Exception here would be the global one.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Seconds to wait before attempt number $attempts + 1. Six attempts over
// about a day and a half: long enough to ride out an SMTP outage or a full
// mailbox, short enough that a dead address stops being retried. 0 means no
// further attempt. The same ladder as api_webhook_backoff(), kept as a
// separate function so mail does not depend on the API module being loaded.
function pg_mail_backoff($attempts)
{
    $schedule = array(60, 300, 1800, 7200, 21600, 86400);

    $index = (int) $attempts - 1;

    if ($index < 0) {
        $index = 0;
    }

    if ($index >= count($schedule)) {
        return 0;
    }

    return $schedule[$index];
}

function pg_mail_max_attempts()
{
    return 6;
}

// The reason the last email() call in this request failed, '' after one that
// succeeded. email() returns a bare boolean; the queue and the campaign job
// read this to store what went wrong next to the row they will retry.
function pg_mail_last_error($set = null)
{
    static $error = '';

    if ($set !== null) {
        $error = (string) $set;
    }

    return $error;
}

// Whether the last email() call in this request failed in a way that no
// later attempt can change: the message as stored has no content, no
// recipient, or an address PHPMailer refuses. Set next to
// pg_mail_last_error(); the queue gives such a message up at once instead of
// trying it again over a day and a half.
function pg_mail_last_error_permanent($set = null)
{
    static $permanent = false;

    if ($set !== null) {
        $permanent = (bool) $set;
    }

    return $permanent;
}

// What rules a message out before anything is built from it, read from the
// email() properties alone: 'no_recipient' when 'to' holds no address,
// 'empty_body' when the body is empty or only white space, '' otherwise.
// The recipient comes first: a message to nobody is wrong whatever it says.
// Whether an address is well formed is left to PHPMailer, which knows
// internationalised domains; email() marks its refusal permanent as well.
function pg_mail_permanent_problem(array $properties)
{
    $to = isset($properties['to']) ? $properties['to'] : '';

    $recipients = is_array($to) ? $to : array($to);

    $has_recipient = false;

    foreach ($recipients as $recipient) {
        if (is_string($recipient) && (trim($recipient) !== '')) {
            $has_recipient = true;
            break;
        }
    }

    if (!$has_recipient) {
        return 'no_recipient';
    }

    $body = isset($properties['body']) ? $properties['body'] : '';

    if (!is_scalar($body) || (trim((string) $body) === '')) {
        return 'empty_body';
    }

    return '';
}

// Whether the queue stops trying a message that failed on attempt number
// $attempt: at once when the failure is permanent, otherwise once the
// attempts run out or the retry schedule has no further step.
function pg_mail_queue_gives_up($attempt, $max_attempts, $permanent)
{
    if ($permanent) {
        return true;
    }

    return ((int) $attempt >= (int) $max_attempts) || (pg_mail_backoff($attempt) === 0);
}

// Whether a mail_outbox row was given up because of a permanent failure: a
// failed row that did not use up its attempts. The retry button resets the
// count, so the reading holds after a manual retry too.
function pg_mail_failed_permanently($status, $attempts)
{
    return ((string) $status === 'failed') && ((int) $attempts < pg_mail_max_attempts());
}

// The panel notifications for the messages one queue run gave up on, as
// create_notification() properties. Each message is told once: one row per
// message, recipient in order_total, the outbox id in form_id and the reason
// in title. More than $limit in one run (an SMTP server down for a day lets a
// whole day's mail run out of attempts together) become a single row whose
// title is the count and whose form_id is empty, so the bell is not flooded.
function pg_mail_given_up_notices(array $given_up, $limit = 3)
{
    if (!$given_up) {
        return array();
    }

    if (count($given_up) > (int) $limit) {
        return array(array(
            'action' => 'mail_failed',
            'type'   => 'error',
            'title'  => (string) count($given_up),
            'user'   => 'system',
        ));
    }

    $notices = array();

    foreach ($given_up as $message) {
        $reason = isset($message['reason']) ? trim((string) $message['reason']) : '';

        $notices[] = array(
            'action'      => 'mail_failed',
            'type'        => 'error',
            'title'       => pg_mail_clip(($reason !== '') ? $reason : '-', 255),
            'order_total' => pg_mail_clip(isset($message['recipient']) ? (string) $message['recipient'] : '', 100),
            'form_id'     => (int) $message['id'],
            'user'        => 'system',
        );
    }

    return $notices;
}

// Cut a string for a VARCHAR column without splitting a UTF-8 sequence: a
// broken sequence makes a strict-mode INSERT fail outright.
function pg_mail_clip($text, $length)
{
    $text = (string) $text;

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length, 'UTF-8');
    }

    return substr($text, 0, $length);
}

// Whether the 2026.4.8 upgrade has created mail_outbox.
function pg_mail_queue_ready()
{
    return pg_schema_has('mail_outbox');
}

// email() properties as stored in mail_outbox.properties.
//
// 'queue' is dropped, so that the job's own email() call sends instead of
// queueing the message again. Attachment bytes are carried base64-encoded:
// a PDF or an image is not valid UTF-8, and json_encode() refuses such a
// string by returning false for the whole document. Attachments given by
// path travel as the path; the file is read when the message is sent.
//
// False when the properties cannot be encoded at all; the caller then sends
// the message at once instead of losing it.
function pg_mail_properties_encode(array $properties)
{
    unset($properties['queue']);

    if (isset($properties['attachments']) && is_array($properties['attachments'])) {
        $attachments = array();

        foreach ($properties['attachments'] as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            if (array_key_exists('content', $attachment)) {
                $attachment['content_base64'] = base64_encode((string) $attachment['content']);
                unset($attachment['content']);
            }

            $attachments[] = $attachment;
        }

        $properties['attachments'] = $attachments;
    }

    // JSON_INVALID_UTF8_SUBSTITUTE exists from PHP 7.2; on 7.1 a stray byte
    // in the body makes json_encode() return false and the message is sent
    // synchronously, as before.
    $flags = JSON_UNESCAPED_UNICODE;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE; // phpcs:ignore PHPCompatibility.Constants.NewConstants.json_invalid_utf8_substituteFound -- guarded by defined() above
    }

    $json = json_encode($properties, $flags);

    if (!is_string($json)) {
        return false;
    }

    return $json;
}

// The inverse of pg_mail_properties_encode(). False when the stored value is
// not a JSON object.
function pg_mail_properties_decode($json)
{
    $properties = json_decode((string) $json, true);

    if (!is_array($properties)) {
        return false;
    }

    if (isset($properties['attachments']) && is_array($properties['attachments'])) {
        foreach ($properties['attachments'] as $key => $attachment) {
            if (is_array($attachment) && array_key_exists('content_base64', $attachment)) {
                $attachment['content'] = (string) base64_decode((string) $attachment['content_base64']);
                unset($attachment['content_base64']);
                $properties['attachments'][$key] = $attachment;
            }
        }
    }

    return $properties;
}

// Whether a queued message would be picked up soon: a job that works the
// queue (the general job on every run, or mail_job.php on a cron entry of its
// own) finished within the last fifteen minutes. A site without a cron
// schedule never gets there, and its mail keeps going out synchronously
// instead of waiting for a job that will not come.
function pg_mail_should_queue($last_job_run_at, $now)
{
    $last_job_run_at = (int) $last_job_run_at;

    return ($last_job_run_at > 0) && (((int) $now - $last_job_run_at) <= 900);
}

// pg_mail_should_queue() for this site, asked once per request. Either job
// counts: a site may schedule only mail_job.php and leave job.php off cron.
function pg_mail_job_alive()
{
    static $alive = null;

    if ($alive !== null) {
        return $alive;
    }

    $alive = false;

    if (!pg_mail_queue_ready() || !pg_schema_has('cron_runs')) {
        return $alive;
    }

    $alive = pg_mail_should_queue((int) db_value("SELECT MAX(last_run_at) FROM cron_runs WHERE job_name IN ('job', 'mail_job')"), time());

    return $alive;
}

// Write a message to the queue. Returns the row id, or 0 when it could not be
// written - the caller then sends the message itself.
//
// mysqli_query() rather than db(): db() ends the request on a failed query,
// and a request that only wanted to send an e-mail must not die because the
// queue refused a row.
function pg_mail_enqueue(array $properties)
{
    $json = pg_mail_properties_encode($properties);

    if ($json === false) {
        return 0;
    }

    $recipient = isset($properties['to']) ? $properties['to'] : '';

    if (is_array($recipient)) {
        $recipient = (string) reset($recipient);
    }

    $type = (isset($properties['type']) && ($properties['type'] !== '')) ? (string) $properties['type'] : 'system';

    $subject = isset($properties['subject']) ? (string) $properties['subject'] : '';

    $result = mysqli_query(db::$con,
        "INSERT INTO mail_outbox (created_at, send_after, status, mail_type, recipient, subject, properties)
        VALUES (
            UNIX_TIMESTAMP(),
            0,
            'queued',
            '" . e(pg_mail_clip($type, 16)) . "',
            '" . e(pg_mail_clip((string) $recipient, 255)) . "',
            '" . e(pg_mail_clip($subject, 255)) . "',
            '" . e($json) . "')");

    if ($result === false) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

// Send what is due in the queue. Called by job.php on every run and by
// mail_job.php. Bounded by both a row count and wall-clock time: an SMTP
// server that has stopped answering costs PHPMailer its whole timeout per
// message, and this must not hold the general job past its next tick.
//
// Returns array('sent' => n, 'failed' => n), failed counting attempts that
// did not get through in this run, whether or not they will be retried.
function pg_mail_queue_run($limit = 25, $seconds = 20)
{
    $result = array('sent' => 0, 'failed' => 0);

    if (!pg_mail_queue_ready()) {
        return $result;
    }

    $deadline = microtime(true) + $seconds;

    $max_attempts = pg_mail_max_attempts();

    // The messages given up on in this run, told on the panel bell at the end.
    $given_up = array();

    // A run that died while sending (fatal error, killed process) leaves its
    // row in 'sending'. Fifteen minutes is far longer than any send takes, so
    // such a row goes back to the queue. If the message did leave before the
    // process died it is sent twice; losing it would be worse.
    db(
        "UPDATE mail_outbox
        SET status = 'queued', claimed_at = 0
        WHERE
            (status = 'sending')
            AND (claimed_at < " . (time() - 900) . ")");

    for ($looked = 0; ($looked < (int) $limit) && (microtime(true) < $deadline); $looked++) {

        $id = (int) db_value(
            "SELECT id
            FROM mail_outbox
            WHERE
                (status = 'queued')
                AND (send_after <= UNIX_TIMESTAMP())
            ORDER BY send_after, id
            LIMIT 1");

        if (!$id) {
            break;
        }

        // Claimed before it is sent. The condition on status makes the UPDATE
        // the arbiter: InnoDB locks the row for the statement, so when two
        // runs overlap exactly one of them changes it, and the other sees no
        // affected row and moves on.
        db(
            "UPDATE mail_outbox
            SET status = 'sending', claimed_at = UNIX_TIMESTAMP()
            WHERE
                (id = '" . $id . "')
                AND (status = 'queued')");

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        $row = db_item("SELECT id, attempts, recipient, properties FROM mail_outbox WHERE id = '" . $id . "'");

        $attempt = (int) $row['attempts'] + 1;

        $properties = pg_mail_properties_decode($row['properties']);

        if ($properties === false) {
            $error = lang('The stored message could not be read.');

            db(
                "UPDATE mail_outbox
                SET
                    status = 'failed',
                    attempts = '" . $attempt . "',
                    last_error = '" . e($error) . "',
                    claimed_at = 0
                WHERE id = '" . $id . "'");

            $result['failed']++;

            $given_up[] = array('id' => $id, 'recipient' => $row['recipient'], 'reason' => $error);

            continue;
        }

        unset($properties['queue']);

        // The sender hears about a failure once, when the message is given
        // up on, rather than after every attempt. Before the last attempt
        // email() is asked to tell only a permanent failure, which ends the
        // message as well.
        $notify_sender = array_key_exists('notify_sender', $properties) ? (bool) $properties['notify_sender'] : true;

        if (!$notify_sender) {
            $properties['notify_sender'] = false;
        } else {
            $properties['notify_sender'] = ($attempt >= $max_attempts) ? true : 'permanent';
        }

        if (email($properties)) {
            db(
                "UPDATE mail_outbox
                SET
                    status = 'sent',
                    attempts = '" . $attempt . "',
                    last_error = '',
                    sent_at = UNIX_TIMESTAMP(),
                    claimed_at = 0
                WHERE id = '" . $id . "'");

            $result['sent']++;

            continue;
        }

        $result['failed']++;

        $error = pg_mail_clip(pg_mail_last_error(), 500);

        $permanent = pg_mail_last_error_permanent();

        $retry_in = pg_mail_backoff($attempt);

        if (pg_mail_queue_gives_up($attempt, $max_attempts, $permanent)) {
            db(
                "UPDATE mail_outbox
                SET
                    status = 'failed',
                    attempts = '" . $attempt . "',
                    last_error = '" . e($error) . "',
                    claimed_at = 0
                WHERE id = '" . $id . "'");

            log_activity(lang(array(
                'string' => 'An e-mail to {var:1} was given up after {var:2} attempts: {var:3}',
                'vars'   => array($row['recipient'], $attempt, $error),
            )), 'UNKNOWN');

            $given_up[] = array('id' => $id, 'recipient' => $row['recipient'], 'reason' => $error);

        } else {
            db(
                "UPDATE mail_outbox
                SET
                    status = 'queued',
                    attempts = '" . $attempt . "',
                    last_error = '" . e($error) . "',
                    send_after = " . (time() + $retry_in) . ",
                    claimed_at = 0
                WHERE id = '" . $id . "'");
        }
    }

    foreach (pg_mail_given_up_notices($given_up) as $notice) {
        create_notification($notice);
    }

    // Sent rows are kept a week and failed ones a month, so the mail queue
    // screen can show what happened, then swept.
    db("DELETE FROM mail_outbox WHERE (status = 'sent') AND (sent_at < " . (time() - 604800) . ")");
    db("DELETE FROM mail_outbox WHERE (status = 'failed') AND (created_at < " . (time() - 2592000) . ")");

    return $result;
}

// List-Unsubscribe headers for a commercial message (RFC 2369, RFC 8058).
// The URL is the one-click address: a mail client POSTs
// "List-Unsubscribe=One-Click" to it, without a page or a confirmation,
// which is what List-Unsubscribe-Post announces. Without a URL there is
// nothing a client could post to, and no header is sent.
function pg_mail_list_unsubscribe_headers($mailto, $url)
{
    $mailto = (string) $mailto;
    $url = (string) $url;

    if ($url === '') {
        return array();
    }

    $value = '<' . $url . '>';

    if ($mailto !== '') {
        $value = '<mailto:' . $mailto . '?subject=unsubscribe>, ' . $value;
    }

    return array(
        'List-Unsubscribe'      => $value,
        'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
    );
}

// The headers for one recipient. The URL carries the same id and signature
// as the preferences link in the footer, so a site without ENCRYPTION_KEY -
// which cannot sign - sends no header rather than a link that cannot work,
// and neither does an empty address or one without "@".
function pg_mail_list_unsubscribe_for($email_address, $mailto)
{
    $url = pg_email_preferences_url($email_address);

    if ($url === '') {
        return array();
    }

    return pg_mail_list_unsubscribe_headers($mailto, $url . '&unsubscribe=1');
}
