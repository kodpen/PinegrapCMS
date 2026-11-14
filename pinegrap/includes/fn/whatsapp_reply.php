<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * WhatsApp answers written in the Workspace: free text to a customer inside
 * the 24 hours after their own message, the one time WhatsApp takes a
 * message that is not an approved template. An answer only queues
 * (whatsapp_outbox, kind 'reply', a payload of type text) and the general
 * job sends it as the queue's other messages; how it went is written back on
 * the Workspace's item (pg_whatsapp_reply_outcome()), as an e-mail answer's
 * outcome is.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The customer service window: free text is taken for this long after the
// customer's last message.
define('PG_WHATSAPP_WINDOW', 86400);

// The longest text WhatsApp takes in one message.
define('PG_WHATSAPP_TEXT_MAX', 4096);

/**
 * Seconds left of the window a customer's last message opened; 0 once it
 * closed or when they never wrote. Pure.
 *
 * @param int $last_in_at the time of their last message
 * @param int $now
 * @return int
 */
function pg_whatsapp_window_left($last_in_at, $now)
{
    if ((int) $last_in_at <= 0) {
        return 0;
    }

    return max(0, (int) $last_in_at + PG_WHATSAPP_WINDOW - (int) $now);
}

/**
 * The time left of a window as the drawer says it: hours and minutes. Pure.
 *
 * @param int $seconds pg_whatsapp_window_left()
 * @return string
 */
function pg_whatsapp_window_text($seconds)
{
    $seconds = max(0, (int) $seconds);

    if ($seconds <= 0) {
        return lang('The 24 hours after the customer\'s last message have passed: WhatsApp takes only an approved template now.');
    }

    $hours = intdiv($seconds, 3600);
    $minutes = max(1, intdiv($seconds % 3600, 60));

    return ($hours > 0)
        ? lang(array('string' => 'WhatsApp takes a written answer for {var:1} h {var:2} min more.', 'vars' => array($hours, $minutes)))
        : lang(array('string' => 'WhatsApp takes a written answer for {var:1} min more.', 'vars' => array($minutes)));
}

/**
 * The reference an answer's queue row carries back to its item: the item
 * and the row the item named when this attempt was queued (0 for the
 * first), as an e-mail answer's (ws_mail_send_ref()). Pure.
 *
 * @param int $item_id
 * @param int $previous_outbox_id
 * @return string
 */
function pg_whatsapp_reply_ref($item_id, $previous_outbox_id)
{
    return 'wsr:' . (int) $item_id . ':' . (int) $previous_outbox_id;
}

/**
 * pg_whatsapp_reply_ref() read back. Pure.
 *
 * @param string $ref
 * @return array item, previous (both 0 when it is not one)
 */
function pg_whatsapp_reply_ref_parse($ref)
{
    return preg_match('/^wsr:([0-9]{1,10}):([0-9]{1,10})$/D', (string) $ref, $match)
        ? array('item' => (int) $match[1], 'previous' => (int) $match[2])
        : array('item' => 0, 'previous' => 0);
}

/**
 * The queue row of an answer, from the number, the text and the window;
 * no database. A row not sent before the window closes expires instead of
 * reaching Meta, which would refuse it (131047). Pure.
 *
 * @param string $number     E.164 digits
 * @param string $text
 * @param array  $options    reference, contact_id, window_ends (when the
 *                           window closes)
 * @param int    $now
 * @return array the columns; error alone when it is refused
 */
function pg_whatsapp_reply_row($number, $text, $options, $now)
{
    $number = function_exists('pg_sms_number') ? pg_sms_number('+' . (string) $number) : '';
    $text = trim(str_replace(array("\r\n", "\r"), "\n", (string) $text));
    $ends = (int) ($options['window_ends'] ?? 0);

    if ($number === '') {
        return array('error' => 'number');
    }

    if ($text === '') {
        return array('error' => 'empty');
    }

    if ((function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text)) > PG_WHATSAPP_TEXT_MAX) {
        return array('error' => 'too_long');
    }

    if ($ends <= (int) $now) {
        return array('error' => 'window');
    }

    return array(
        'created_at'        => (int) $now,
        'send_after'        => (int) $now,
        'expires_at'        => $ends,
        'status'            => 'queued',
        'kind'              => 'reply',
        'recipient'         => $number,
        'reference'         => (string) ($options['reference'] ?? ''),
        'contact_id'        => max(0, (int) ($options['contact_id'] ?? 0)),
        'template_id'       => 0,
        'template_name'     => '',
        'template_language' => '',
        'category'          => 'SERVICE',
        'payload'           => json_encode(array('type' => 'text', 'text' => $text), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

/**
 * Queues an answer for the general job. Refused on a demo site, before the
 * upgrade and while WhatsApp is not set up.
 *
 * @param string $number
 * @param string $text
 * @param array  $options pg_whatsapp_reply_row()
 * @return array ok, row (the queue's id), error (number, empty, too_long,
 *               window, demo, not_ready, off, not_queued)
 */
function pg_whatsapp_enqueue_reply($number, $text, $options)
{
    $row = pg_whatsapp_reply_row($number, $text, $options, time());

    if (isset($row['error'])) {
        return array('ok' => false, 'row' => 0, 'error' => $row['error']);
    }

    if (function_exists('pg_demo') && pg_demo()) {
        return array('ok' => false, 'row' => 0, 'error' => 'demo');
    }

    if (!pg_whatsapp_queue_ready()) {
        return array('ok' => false, 'row' => 0, 'error' => 'not_ready');
    }

    if (!pg_whatsapp_ready()) {
        return array('ok' => false, 'row' => 0, 'error' => 'off');
    }

    $columns = array_keys($row);
    $written = db_q("INSERT INTO whatsapp_outbox (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")",
        array_values($row));

    return ($written !== false)
        ? array('ok' => true, 'row' => (int) mysqli_insert_id(db::$con), 'error' => '')
        : array('ok' => false, 'row' => 0, 'error' => 'not_queued');
}

/**
 * The queue's outcome of an answer, written on its Workspace item: sent, or
 * not sent with the reason (its writer is told), by the e-mail answer's own
 * rules (ws_mail_send_outcome_change()). A message whose outcome is unknown
 * reads as not sent with the reason saying it may have arrived; it is sent
 * again only when someone presses Send again.
 *
 * @param int    $outbox_id
 * @param string $ref    pg_whatsapp_reply_ref()
 * @param string $status sent | failed | uncertain
 * @param string $error
 */
function pg_whatsapp_reply_outcome($outbox_id, $ref, $status, $error)
{
    $parsed = pg_whatsapp_reply_ref_parse($ref);

    if (($parsed['item'] <= 0) || !defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED) {
        return;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    if (!function_exists('ws_mail_send_ready') || !ws_mail_send_ready()) {
        return;
    }

    $item = db_item("SELECT * FROM ws_mail_items WHERE id = '" . $parsed['item'] . "'");
    $change = ws_mail_send_outcome_change(is_array($item) ? $item : null, (int) $outbox_id, $parsed['previous'],
        ($status === 'sent') ? 'sent' : 'failed', (string) $error, time());

    if (!$change) {
        return;
    }

    $sets = array();
    $values = array();

    foreach ($change['set'] as $column => $value) {
        $sets[] = $column . ' = ?';
        $values[] = is_int($value) ? $value : (string) $value;
    }

    $values[] = (int) $item['id'];
    $values[] = (string) $item['send_state'];
    $values[] = (int) $item['outbox_id'];

    // Written only over the state it was read in: two outcomes at once
    // change the item once.
    db_q("UPDATE ws_mail_items SET " . implode(', ', $sets) . " WHERE id = ? AND send_state = ? AND outbox_id = ?", $values);

    if (mysqli_affected_rows(db::$con) <= 0) {
        return;
    }

    if ((int) $item['message_id'] > 0) {
        ws_message_touch((int) $item['message_id']);
    }

    if ($change['notify'] && ((int) $item['user_id'] > 0) && ((int) $item['message_id'] > 0)) {
        $channel_id = (int) db_value("SELECT channel_id FROM ws_messages WHERE id = '" . (int) $item['message_id'] . "'");

        ws_notify((int) $item['user_id'], 'mail_unsent', array('channel_id' => $channel_id, 'message_id' => (int) $item['message_id'], 'actor_id' => 0));
    }
}
