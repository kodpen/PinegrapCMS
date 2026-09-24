<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - editing a picture or a text file posted in a conversation.
 *
 * The person who posted the file may edit it: the edited file takes the old
 * one's place in the message, the message reads "edited", and the file it
 * replaced stays in the file manager, listed in ws_file_edits as one of the
 * message's earlier versions. Anybody else who may write in the channel edits
 * a copy instead, which is posted as their own message in reply to the
 * original; the original message and its file stay exactly as they were.
 *
 * Pictures are edited in the panel's image editor (Pintura, through
 * workspace_file_save.php); text files - Markdown, plain text, CSV and the
 * like - in the text window of the channel screen, through ws_file_text and
 * ws_file_text_save.
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
 * The largest text file opened for reading or editing, in bytes.
 */
define('WS_FILE_TEXT_MAX', 512 * 1024);

/**
 * Is the history of edited files installed (4.111)?
 *
 * @return bool
 */
function ws_file_edits_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ws_file_edits'") > 0);
    }

    return $ready;
}

/**
 * The extensions of the files opened as text.
 *
 * @return string[]
 */
function ws_file_text_extensions()
{
    return array('txt', 'md', 'markdown', 'csv', 'tsv', 'log', 'json', 'yaml', 'yml', 'ini');
}

/**
 * Is this a file the text window opens?
 *
 * @param string $name
 * @return bool
 */
function ws_file_is_text($name)
{
    return in_array(strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION)), ws_file_text_extensions(), true);
}

/**
 * Is this a Markdown file (drawn the way a message is)?
 *
 * @param string $name
 * @return bool
 */
function ws_file_is_markdown($name)
{
    return in_array(strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION)), array('md', 'markdown'), true);
}

/**
 * The headings of a Markdown file ("# Title") as bold lines, outside blocks
 * of code, so the file reads the way it was meant to in a message's markup.
 *
 * @param string $text
 * @return string
 */
function ws_markdown_headings($text)
{
    $inside = false;
    $lines = preg_split('/\R/', (string) $text);

    foreach ($lines as $index => $line) {
        if (preg_match('/^\s*```/', $line)) {
            $inside = !$inside;
            continue;
        }

        if (!$inside && preg_match('/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/u', $line, $match)) {
            $lines[$index] = '**' . str_replace('*', '\\*', $match[1]) . '**';
        }
    }

    return implode("\n", $lines);
}

/**
 * The words of a text file in the file manager, as UTF-8.
 *
 * @param int  $file_id
 * @param bool $text_only only for the files the text window opens
 * @return string|null null when it is missing, too large or not text
 */
function ws_file_text_read($file_id, $text_only = false)
{
    $row = db_item("SELECT name FROM files WHERE id = '" . (int) $file_id . "'");

    if (!is_array($row) || ($text_only && !ws_file_is_text($row['name']))) {
        return null;
    }

    $path = FILE_DIRECTORY_PATH . '/' . $row['name'];

    if (!is_file($path) || ((int) @filesize($path) > WS_FILE_TEXT_MAX)) {
        return null;
    }

    $text = @file_get_contents($path);

    if (!is_string($text)) {
        return null;
    }

    // A byte order mark is not part of the text; a file written by an older
    // Windows program in Turkish is read as what it was written in.
    if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
        $text = substr($text, 3);
    }

    if (!mb_check_encoding($text, 'UTF-8')) {
        $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1254');
        $text = is_string($converted) ? $converted : '';
    }

    // Anything with NUL bytes in it is not text.
    if (strpos($text, "\0") !== false) {
        return null;
    }

    return $text;
}

/**
 * The message a file edit is about, checked: readable, not deleted, with a
 * file of the right kind.
 *
 * @param array  $viewer
 * @param int    $message_id
 * @param string $kind image | text
 * @return array|string [message, channel], or the error
 */
function ws_file_edit_target($viewer, $message_id, $kind)
{
    $message = ws_message($message_id);
    $channel = $message ? ws_channel($message['channel_id']) : null;

    if (!$message || !$channel || !ws_can_read_channel($viewer, $channel) || ((int) $message['deleted_at'] > 0)) {
        return lang('That message could not be found.');
    }

    if (((int) $message['file_id'] <= 0) || ((string) db_value("SELECT name FROM files WHERE id = '" . (int) $message['file_id'] . "'") === '')) {
        return lang('The file could not be found.');
    }

    $fits = ($kind === 'image') ? (ws_file_kind($message['file_name']) === 'image') : ws_file_is_text($message['file_name']);

    if (!$fits) {
        return lang('This file cannot be edited here.');
    }

    return array($message, $channel);
}

/**
 * May the person put an edited file in place of this one? Only in their own
 * message, while they may still write in the channel.
 *
 * @param array $viewer
 * @param array $message
 * @param array $channel
 * @return bool
 */
function ws_file_can_replace($viewer, $message, $channel)
{
    return ($message['sender_kind'] === 'user') && ((int) $message['sender_id'] === (int) $viewer['id'])
        && empty($message['locked']) && ws_can_post_channel($viewer, $channel);
}

/**
 * The name the edited file goes by: the old name, with the extension of what
 * it now is.
 *
 * @param string $name
 * @param string $extension
 * @return string
 */
function ws_file_edit_name($name, $extension)
{
    $base = (string) pathinfo((string) $name, PATHINFO_FILENAME);

    return (($base !== '') ? $base : 'file') . '.' . $extension;
}

/**
 * What a picture out of the editor is, from its data: URL.
 *
 * @param string $data
 * @return string jpg | png | webp | gif, or '' for anything else
 */
function ws_file_image_extension($data)
{
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp|gif);base64,#i', substr((string) $data, 0, 40), $match)) {
        return '';
    }

    $type = strtolower($match[1]);

    return ($type === 'jpeg') ? 'jpg' : $type;
}

/**
 * Puts an edited file in the place of the one a message carries. The old
 * file stays, as the message's earlier version.
 *
 * @param array  $viewer
 * @param array  $message
 * @param array  $channel
 * @param string $name  the name the file goes by
 * @param string $data  base64, as the panel sends files
 * @return array ok, error
 */
function ws_message_file_replace($viewer, $message, $channel, $name, $data)
{
    if (!ws_file_can_replace($viewer, $message, $channel)) {
        return array('ok' => false, 'error' => lang('Only the one who posted a file can replace it. Send an edited copy instead.'));
    }

    if (!ws_file_edits_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'));
    }

    $stored = ws_store_upload($viewer, $channel, $name, $data);

    if (!$stored['ok']) {
        return array('ok' => false, 'error' => $stored['error']);
    }

    $now = time();

    db("INSERT INTO ws_file_edits (message_id, original_file_id, original_file_name, edited_file_id, edited_by, created_at)
        VALUES ('" . (int) $message['id'] . "', '" . (int) $message['file_id'] . "', '" . e(mb_substr((string) $message['file_name'], 0, 255)) . "',
            '" . (int) $stored['file_id'] . "', '" . (int) $viewer['id'] . "', '" . $now . "')");

    db("UPDATE ws_messages SET file_id = '" . (int) $stored['file_id'] . "', file_name = '" . e($stored['name']) . "', edited_at = '" . $now . "'
        WHERE id = '" . (int) $message['id'] . "'");

    ws_message_touch($message['id']);

    return array('ok' => true, 'error' => '');
}

/**
 * Posts an edited copy of somebody's file as the person's own message, in
 * reply to the original.
 *
 * @param array  $viewer
 * @param array  $message
 * @param array  $channel
 * @param string $name
 * @param string $data base64
 * @return array ok, error, message_id
 */
function ws_message_file_send($viewer, $message, $channel, $name, $data)
{
    if (!ws_can_post_channel($viewer, $channel)) {
        return array('ok' => false, 'error' => lang('You cannot post in that channel.'), 'message_id' => 0);
    }

    $stored = ws_store_upload($viewer, $channel, $name, $data);

    if (!$stored['ok']) {
        return array('ok' => false, 'error' => $stored['error'], 'message_id' => 0);
    }

    return ws_message_send($viewer, $channel, '', array(
        'file_id'   => $stored['file_id'],
        'file_name' => $stored['name'],
        'parent_id' => (int) $message['id'],
    ));
}

/**
 * The earlier versions of a message's file, newest first.
 *
 * @param int $message_id
 * @return array[] name, url, by, time
 */
function ws_file_history($message_id)
{
    if (!ws_file_edits_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT e.*, f.name AS stored_name FROM ws_file_edits e
        LEFT JOIN files f ON f.id = e.original_file_id
        WHERE e.message_id = '" . (int) $message_id . "'
        ORDER BY e.id DESC LIMIT 50");

    $people = ws_people(array_map(function ($row) { return (int) $row['edited_by']; }, $rows));
    $out = array();

    foreach ($rows as $row) {
        $out[] = array(
            'name' => (string) $row['original_file_name'],
            'url'  => ((string) $row['stored_name'] !== '') ? PATH . encode_url_path($row['stored_name']) : '',
            'by'   => (string) ($people[(int) $row['edited_by']]['name'] ?? ''),
            'time' => ws_time_label($row['created_at']),
        );
    }

    return $out;
}

/**
 * How many earlier versions each message's file has.
 *
 * @param int[] $message_ids
 * @return array message id => count
 */
function ws_file_versions_map($message_ids)
{
    $message_ids = array_filter(array_map('intval', (array) $message_ids));

    if (empty($message_ids) || !ws_file_edits_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT message_id, COUNT(*) AS versions FROM ws_file_edits
        WHERE message_id IN (" . implode(',', array_unique($message_ids)) . ")
        GROUP BY message_id") as $row) {
        $out[(int) $row['message_id']] = (int) $row['versions'];
    }

    return $out;
}

/**
 * The texts of the file windows.
 *
 * @return array key => text
 */
function ws_file_edit_js_strings()
{
    return array(
        'file_edit_image'      => lang('Edit the picture'),
        'file_edit_text'       => lang('Open the text'),
        'file_edit_put'        => lang('Put the edited picture in its place'),
        'file_edit_send'       => lang('Edit and send'),
        'file_text_save'       => lang('Save'),
        'file_text_send'       => lang('Send the edited copy'),
        'file_text_write'      => lang('Text'),
        'file_text_view'       => lang('As it reads'),
        'file_text_readonly'   => lang('Read only: you cannot write in this channel.'),
        'file_text_mine_help'  => lang('The edited file takes this one\'s place; the earlier version stays in the history.'),
        'file_text_copy_help'  => lang('This file is somebody else\'s: your edited copy is sent as your message in reply, the original stays as it is.'),
        'file_text_unchanged'  => lang('Nothing was changed.'),
        'file_replaced'        => lang('The file was replaced. The earlier version stays in the history.'),
        'file_copy_sent'       => lang('Your edited copy was sent.'),
        'file_versions'        => ws_js_template('{var:1} earlier version(s)', 1),
        'file_versions_title'  => lang('Earlier versions'),
        'file_versions_by'     => ws_js_template('replaced by {var:1}', 1),
        'file_editor_loading'  => lang('The image editor is loading…'),
        'file_editor_missing'  => lang('The image editor could not be loaded.'),
    );
}
