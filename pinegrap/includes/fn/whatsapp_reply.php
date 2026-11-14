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
 * outcome is. Inside the 24 hours an answer may carry one file (a picture,
 * a video or a document), uploaded to Meta when the queue sends it; after
 * them only an approved template goes, its values typed in the drawer.
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

// The longest caption of a picture, a video or a document.
define('PG_WHATSAPP_CAPTION_MAX', 1024);

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
 * The kinds of file an answer can carry, by name ending: WhatsApp's type,
 * the MIME type Meta is told and the most bytes. Meta takes documents up to
 * 100 MB, but the queue reads the file into memory to upload it, so a
 * document stops at 16 MB as a template header's sample does. Pure.
 *
 * @return array ending => type, mime, max
 */
function pg_whatsapp_reply_media_kinds()
{
    $mb = 1048576;
    $document = function ($mime) use ($mb) {
        return array('type' => 'document', 'mime' => $mime, 'max' => 16 * $mb);
    };

    return array(
        'jpg'  => array('type' => 'image', 'mime' => 'image/jpeg', 'max' => 5 * $mb),
        'jpeg' => array('type' => 'image', 'mime' => 'image/jpeg', 'max' => 5 * $mb),
        'png'  => array('type' => 'image', 'mime' => 'image/png', 'max' => 5 * $mb),
        'mp4'  => array('type' => 'video', 'mime' => 'video/mp4', 'max' => 16 * $mb),
        '3gp'  => array('type' => 'video', 'mime' => 'video/3gpp', 'max' => 16 * $mb),
        'pdf'  => $document('application/pdf'),
        'txt'  => $document('text/plain'),
        'doc'  => $document('application/msword'),
        'docx' => $document('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        'xls'  => $document('application/vnd.ms-excel'),
        'xlsx' => $document('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        'ppt'  => $document('application/vnd.ms-powerpoint'),
        'pptx' => $document('application/vnd.openxmlformats-officedocument.presentationml.presentation'),
    );
}

/**
 * How a file goes in an answer, checked before it is queued: its type and
 * MIME type, or why Meta would not take it. Pure.
 *
 * @param string $name
 * @param int    $size bytes
 * @return array type, mime, error ('' | file_type | file_size)
 */
function pg_whatsapp_reply_media($name, $size)
{
    $kinds = pg_whatsapp_reply_media_kinds();
    $ending = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

    if (!isset($kinds[$ending])) {
        return array('type' => '', 'mime' => '', 'error' => 'file_type');
    }

    if (((int) $size <= 0) || ((int) $size > $kinds[$ending]['max'])) {
        return array('type' => '', 'mime' => '', 'error' => 'file_size');
    }

    return array('type' => $kinds[$ending]['type'], 'mime' => $kinds[$ending]['mime'], 'error' => '');
}

/**
 * The queue row of an answer, from the number, the text, its file and the
 * window; no database. A row not sent before the window closes expires
 * instead of reaching Meta, which would refuse it (131047). Pure.
 *
 * @param string $number     E.164 digits
 * @param string $text       the answer, or the file's caption
 * @param array  $options    reference, contact_id, window_ends (when the
 *                           window closes), file (id, name, type of
 *                           pg_whatsapp_reply_media(); none for text)
 * @param int    $now
 * @return array the columns; error alone when it is refused
 */
function pg_whatsapp_reply_row($number, $text, $options, $now)
{
    $number = function_exists('pg_sms_number') ? pg_sms_number('+' . (string) $number) : '';
    $text = trim(str_replace(array("\r\n", "\r"), "\n", (string) $text));
    $ends = (int) ($options['window_ends'] ?? 0);
    $file = (isset($options['file']) && is_array($options['file'])) ? $options['file'] : null;
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);

    if ($number === '') {
        return array('error' => 'number');
    }

    if ($file === null) {
        if ($text === '') {
            return array('error' => 'empty');
        }

        if ($length > PG_WHATSAPP_TEXT_MAX) {
            return array('error' => 'too_long');
        }

        $payload = array('type' => 'text', 'text' => $text);
    } else {
        if ((int) ($file['id'] ?? 0) <= 0) {
            return array('error' => 'empty');
        }

        if (!in_array((string) ($file['type'] ?? ''), array('image', 'video', 'document'), true)) {
            return array('error' => 'file_type');
        }

        if ($length > PG_WHATSAPP_CAPTION_MAX) {
            return array('error' => 'caption_long');
        }

        $payload = array('type' => (string) $file['type'], 'file_id' => (int) $file['id'], 'name' => (string) ($file['name'] ?? ''), 'caption' => $text);
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
        'payload'           => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

/**
 * Sends a queued answer's file: read from Files when the queue sends it,
 * checked again, uploaded to Meta and sent with its caption. A file gone
 * or grown past Meta's limit fails the row for good.
 *
 * @param array $app
 * @param string $number
 * @param array $payload pg_whatsapp_reply_row()'s file payload
 * @return array pg_whatsapp_cloud_send_parse()
 */
function pg_whatsapp_reply_send_media($app, $number, $payload)
{
    $name = (string) ($payload['name'] ?? '');
    $path = function_exists('pg_file_local_copy') ? pg_file_local_copy((int) ($payload['file_id'] ?? 0)) : '';
    $bytes = ($path !== '') ? @file_get_contents($path) : false;
    $media = pg_whatsapp_reply_media($name, is_string($bytes) ? strlen($bytes) : 0);

    if (!is_string($bytes) || ($media['error'] !== '') || ($media['type'] !== (string) ($payload['type'] ?? ''))) {
        return array('ok' => false, 'id' => '', 'code' => '', 'error' => lang('The answer\'s file could not be read, or WhatsApp does not take it.'), 'retry' => false, 'uncertain' => false);
    }

    return pg_whatsapp_cloud_send_media($app, $number, $media['type'], $media['mime'], $name, $bytes, (string) ($payload['caption'] ?? ''));
}

/**
 * The approved templates an answer after the 24 hours can use: those whose
 * every variable can be typed by hand (pg_whatsapp_message_offered(): no
 * media header, no button needing more than a text), each with its text and
 * one field per variable named as the designer named it. Pure.
 *
 * @param array $rows whatsapp_templates rows: name, language, category,
 *                    status, parameter_format, components, samples, offer
 * @return array[] key, name, language, category, marketing, text, slots
 *                 (key, label), and the parts the drawer's preview draws
 *                 (pg_whatsapp_reply_template_parts())
 */
function pg_whatsapp_reply_templates_offered($rows)
{
    $offered = pg_whatsapp_message_offered($rows);
    $list = array();

    foreach ((array) $rows as $row) {
        $key = is_array($row) ? pg_whatsapp_message_template_key($row['name'] ?? '', $row['language'] ?? '') : '';

        if (!isset($offered[$key])) {
            continue;
        }

        $components = is_string($row['components']) ? json_decode($row['components'], true) : $row['components'];
        $slots = array();

        foreach ($offered[$key]['slots'] as $slot) {
            $where = ($slot['part'] === 'header') ? lang('in the header')
                : (($slot['part'] === 'button') ? lang(array('string' => 'in the link of button {var:1}', 'vars' => array((int) $slot['index'] + 1))) : '');
            $label = $offered[$key]['labels'][$slot['key']] ?? lang(array('string' => 'Value {var:1}', 'vars' => array($slot['name'])));
            $slots[] = array('key' => $slot['key'], 'label' => ($where === '') ? $label : $label . ' (' . $where . ')');
        }

        $list[] = array(
            'key'       => $key,
            'name'      => $offered[$key]['name'],
            'language'  => $offered[$key]['language'],
            'category'  => $offered[$key]['category'],
            'marketing' => ($offered[$key]['category'] === 'MARKETING'),
            'text'      => pg_whatsapp_reply_template_text(is_array($components) ? $components : array(), null),
            'slots'     => $slots,
        ) + pg_whatsapp_reply_template_parts(is_array($components) ? $components : array());
    }

    return $list;
}

/**
 * The parts of a template the drawer's preview draws as a WhatsApp message:
 * the text header, the body, the footer and the buttons' labels, their
 * variables as Meta stores them. Pure.
 *
 * @param array $components as Meta reads them
 * @return array header, body, footer, buttons (labels)
 */
function pg_whatsapp_reply_template_parts($components)
{
    $parts = array('header' => '', 'body' => '', 'footer' => '', 'buttons' => array());

    foreach ((array) $components as $component) {
        $type = (is_array($component) && isset($component['type'])) ? strtoupper((string) $component['type']) : '';

        if (($type === 'HEADER') && (strtoupper((string) ($component['format'] ?? 'TEXT')) === 'TEXT')) {
            $parts['header'] = (string) ($component['text'] ?? '');
        } elseif (($type === 'BODY') || ($type === 'FOOTER')) {
            $parts[strtolower($type)] = (string) ($component['text'] ?? '');
        } elseif (($type === 'BUTTONS') && isset($component['buttons']) && is_array($component['buttons'])) {
            foreach ($component['buttons'] as $button) {
                if (is_array($button) && isset($button['text']) && is_scalar($button['text'])) {
                    $parts['buttons'][] = (string) $button['text'];
                }
            }
        }
    }

    return $parts;
}

/**
 * The account's approved templates for the drawer.
 *
 * @return array[] pg_whatsapp_reply_templates_offered()
 */
function pg_whatsapp_reply_templates()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_messages.php');

    if (!pg_whatsapp_templates_ready()) {
        return array();
    }

    return pg_whatsapp_reply_templates_offered((array) db_items(
        "SELECT id, name, language, category, status, parameter_format, components, samples, offer
        FROM whatsapp_templates
        WHERE status = 'APPROVED'
        ORDER BY name, language"));
}

/**
 * A template's text header and body as the customer reads them, its
 * variables filled from the values; with no values the variables stay
 * shown, and an offer code's place says so. Pure.
 *
 * @param array      $components the template's, as Meta reads them
 * @param array|null $values     slot key => value
 * @return string
 */
function pg_whatsapp_reply_template_text($components, $values)
{
    $parts = array();

    foreach ((array) $components as $component) {
        $type = (is_array($component) && isset($component['type'])) ? strtoupper((string) $component['type']) : '';
        $format = strtoupper((string) ($component['format'] ?? 'TEXT'));

        if ((($type === 'HEADER') && ($format === 'TEXT')) || ($type === 'BODY')) {
            $part = ($type === 'HEADER') ? 'header' : 'body';
            $parts[] = preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/', function ($match) use ($values, $part) {
                if ($values === null) {
                    return $match[0];
                }

                $key = $part . ':' . $match[1];

                return isset($values[$key]) ? pg_whatsapp_message_value($values[$key], $part) : '[' . lang('offer code') . ']';
            }, (string) ($component['text'] ?? ''));
        }
    }

    return trim(implode("\n", $parts));
}

/**
 * An approved template filled with the values typed in the drawer: the
 * components the queue sends (pg_whatsapp_message_components(), each
 * variable from its own field) and the text the card keeps. Every variable
 * needs a value. Pure.
 *
 * @param array $template whatsapp_templates row, components decoded
 * @param array $values   slot key => value
 * @return array ok, components, text, error, slot (the field left empty)
 */
function pg_whatsapp_reply_template_fill($template, $values)
{
    $values = is_array($values) ? $values : array();
    $read = pg_whatsapp_message_slots(is_array($template) ? ($template['components'] ?? array()) : array(), is_array($template) ? ($template['offer'] ?? '') : '');
    $vars = array();
    $taken = array();

    foreach ($read['slots'] as $slot) {
        $value = (isset($values[$slot['key']]) && is_scalar($values[$slot['key']])) ? trim((string) $values[$slot['key']]) : '';

        if ($value === '') {
            return array('ok' => false, 'components' => array(), 'text' => '', 'slot' => $slot['key'],
                'error' => lang(array('string' => 'Fill {var:1}.', 'vars' => array('{{' . $slot['name'] . '}}'))));
        }

        $vars[$slot['key']] = $slot['key'];
        $taken[$slot['key']] = $value;
    }

    $filled = pg_whatsapp_message_components($template, $vars, $taken);

    if (!$filled['ok']) {
        return array('ok' => false, 'components' => array(), 'text' => '', 'slot' => '', 'error' => $filled['error']);
    }

    return array('ok' => true, 'components' => $filled['components'], 'slot' => '', 'error' => '',
        'text' => pg_whatsapp_reply_template_text($template['components'], $taken));
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
