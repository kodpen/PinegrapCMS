<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a poll question's own picture. The question is a message, and
 * a message carries one file (ws_messages.file_id): the picture is that
 * file, sent with the question in one call. It is held to a channel upload's
 * checks (ws_upload_check()) and has to be an image the channel's viewer
 * shows. A question refused after the picture was kept leaves no file
 * behind, as ws_message_send_file_bytes() does.
 *
 * The asker replaces or removes the picture while the question is open and
 * nobody has answered; asked again, the new question carries it unless a new
 * one is sent; taken back, the question keeps it.
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
 * Why a file cannot be a question's picture, '' when it can: an image the
 * channel's viewer shows (ws_file_kind() image), no larger than a channel
 * takes. Pure.
 *
 * @param string $name     the file's name with its extension
 * @param int    $size     bytes
 * @param int    $max_size the largest file a channel takes
 * @return string
 */
function ws_poll_picture_refusal($name, $size, $max_size)
{
    if (ws_file_kind((string) $name) !== 'image') {
        return lang('A question\'s picture has to be an image: JPG, PNG, GIF, WebP, AVIF or BMP.');
    }

    if ((int) $size <= 0) {
        return lang('The picture is empty.');
    }

    if ((int) $size > (int) $max_size) {
        return lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor((int) $max_size / 1048576)));
    }

    return '';
}

/**
 * The picture a call sent, as the rest of this file takes it: new bytes
 * (name, bytes), or one already kept (file_id, file_name) when a question
 * asked again carries its old one. null for none. Pure.
 *
 * @param mixed $picture
 * @return array|null
 */
function ws_poll_picture_normal($picture)
{
    if (!is_array($picture)) {
        return null;
    }

    if ((int) ($picture['file_id'] ?? 0) > 0) {
        return array('file_id' => (int) $picture['file_id'], 'file_name' => (string) ($picture['file_name'] ?? ''));
    }

    if (isset($picture['name'], $picture['bytes']) && is_string($picture['bytes'])) {
        return array('name' => (string) $picture['name'], 'bytes' => $picture['bytes']);
    }

    return null;
}

/**
 * May this picture go with a question in this channel? Its own rule, then
 * the checks of any channel upload, with nothing kept. A picture already
 * kept (an ask-again's) passes.
 *
 * @param array      $viewer
 * @param array      $channel
 * @param array|null $picture ws_poll_picture_normal()
 * @return array ok, error, name (cleaned), kind, size
 */
function ws_poll_picture_check($viewer, $channel, $picture)
{
    if ($picture === null || isset($picture['file_id'])) {
        return array('ok' => true, 'error' => '', 'name' => (string) ($picture['file_name'] ?? ''), 'kind' => 'image', 'size' => 0);
    }

    $refused = ws_poll_picture_refusal($picture['name'], strlen($picture['bytes']), ws_upload_max_bytes());

    if ($refused !== '') {
        return array('ok' => false, 'error' => $refused, 'name' => '', 'kind' => '', 'size' => 0);
    }

    $checked = ws_upload_check($viewer, $channel, $picture['name'], $picture['bytes']);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'name' => '', 'kind' => '', 'size' => 0);
    }

    return array('ok' => true, 'error' => '', 'name' => mb_substr($checked['name'], 0, 200), 'kind' => 'image', 'size' => strlen($picture['bytes']));
}

/**
 * Keeps a picture and hands back the message options that carry it; a
 * picture already kept is carried as it is.
 *
 * @param array      $viewer
 * @param array      $channel
 * @param array|null $picture ws_poll_picture_normal()
 * @return array ok, error, file_id, file_name, stored (kept by this call)
 */
function ws_poll_picture_store($viewer, $channel, $picture)
{
    if ($picture === null) {
        return array('ok' => true, 'error' => '', 'file_id' => 0, 'file_name' => '', 'stored' => false);
    }

    if (isset($picture['file_id'])) {
        return array('ok' => true, 'error' => '', 'file_id' => (int) $picture['file_id'], 'file_name' => (string) $picture['file_name'], 'stored' => false);
    }

    $stored = ws_store_upload_bytes($viewer, $channel, $picture['name'], $picture['bytes']);

    if (!$stored['ok']) {
        return array('ok' => false, 'error' => $stored['error'], 'file_id' => 0, 'file_name' => '', 'stored' => false);
    }

    return array('ok' => true, 'error' => '', 'file_id' => (int) $stored['file_id'], 'file_name' => (string) $stored['name'], 'stored' => true);
}

/**
 * Writes a question's message, with its picture when it has one. A message
 * refused after the picture was kept takes the file back with it.
 *
 * @param array      $viewer
 * @param array      $channel
 * @param string     $question
 * @param array|null $picture ws_poll_picture_normal()
 * @param array      $options as for ws_message_send()
 * @return array ws_message_send()'s answer
 */
function ws_poll_picture_send($viewer, $channel, $question, $picture, $options = array())
{
    if ($picture === null) {
        return ws_message_send($viewer, $channel, $question, $options);
    }

    // The file it is about to carry stands in for its id.
    $message = ws_message_send_check($viewer, $channel, $question, array_merge($options, array('file_id' => PHP_INT_MAX)));

    if (!$message['ok']) {
        return array('ok' => false, 'error' => $message['error'], 'message_id' => 0);
    }

    $kept = ws_poll_picture_store($viewer, $channel, $picture);

    if (!$kept['ok']) {
        return array('ok' => false, 'error' => $kept['error'], 'message_id' => 0);
    }

    $sent = ws_message_send($viewer, $channel, $question, array_merge($options, array('file_id' => $kept['file_id'], 'file_name' => $kept['file_name'])));

    if (!$sent['ok'] && $kept['stored']) {
        ws_upload_discard($kept['file_id']);
    }

    return $sent;
}

/**
 * The picture a question's message carries, as an ask-again carries it:
 * file_id and file_name, null when it carries none or no image. Pure.
 *
 * @param array|null $message a ws_messages row
 * @return array|null
 */
function ws_poll_picture_of_message($message)
{
    if (!is_array($message) || ((int) ($message['file_id'] ?? 0) <= 0) || (ws_file_kind((string) ($message['file_name'] ?? '')) !== 'image')) {
        return null;
    }

    return array('file_id' => (int) $message['file_id'], 'file_name' => (string) $message['file_name']);
}

/**
 * Why the asker may not change a question's picture now, '' when they may:
 * only while it is open, not taken back and nobody has answered. Pure.
 *
 * @param bool $open     neither closed nor past its closing moment
 * @param bool $withdrawn
 * @param int  $votes    answers given
 * @return string
 */
function ws_poll_picture_change_refusal($open, $withdrawn, $votes)
{
    if ($withdrawn) {
        return lang('The poll was taken back.');
    }

    if (!$open) {
        return lang('The poll is closed.');
    }

    if ((int) $votes > 0) {
        return lang('Somebody has answered, so the question keeps the picture it was asked with.');
    }

    return '';
}

/**
 * May the asker replace or remove the question's picture? The asker's
 * rule of the poll's other changes, then ws_poll_picture_change_refusal(),
 * then the new picture's own checks.
 *
 * @param array      $viewer
 * @param array      $poll
 * @param array|null $picture ws_poll_picture_normal(); null removes it
 * @param int        $as_app
 * @return array ok, error, field, message, channel, picture (checked)
 */
function ws_poll_picture_change_check($viewer, $poll, $picture, $as_app = 0)
{
    $fail = function ($error, $field = '') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'message' => null, 'channel' => null, 'picture' => null);
    };

    $message = ws_message($poll['message_id']);

    if (!is_array($message) || ((int) $message['deleted_at'] > 0)) {
        return $fail(lang('That poll could not be found.'));
    }

    if (!ws_poll_is_asker($poll, $message, (int) $viewer['id'], $as_app)) {
        return $fail(lang('Only the person who asked can change the poll.'));
    }

    $open = ((int) $poll['closed_at'] === 0) && (((int) $poll['closes_at'] === 0) || ((int) $poll['closes_at'] > time()));
    $votes = (int) db_value("SELECT COUNT(*) FROM ws_poll_votes WHERE poll_id = '" . (int) $poll['id'] . "'");
    $refused = ws_poll_picture_change_refusal($open, (int) ($poll['withdrawn_at'] ?? 0) > 0, $votes);

    if ($refused !== '') {
        return $fail($refused, 'picture');
    }

    $channel = ws_channel($poll['channel_id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        return $fail(lang('You cannot post in that channel.'));
    }

    $checked = ws_poll_picture_check($viewer, $channel, $picture);

    if (!$checked['ok']) {
        return $fail($checked['error'], 'picture');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'message' => $message, 'channel' => $channel, 'picture' => $checked);
}

/**
 * Replaces or removes the question's picture. The file it carried before
 * stays in the channel's folder, as a deleted message's file does.
 *
 * @param array      $viewer
 * @param array      $poll
 * @param array|null $picture ws_poll_picture_normal(); null removes it
 * @param int        $as_app
 * @return array ok, error, field
 */
function ws_poll_picture_change($viewer, $poll, $picture, $as_app = 0)
{
    $checked = ws_poll_picture_change_check($viewer, $poll, $picture, $as_app);

    if (!$checked['ok']) {
        return array('ok' => false, 'error' => $checked['error'], 'field' => $checked['field']);
    }

    $kept = ws_poll_picture_store($viewer, $checked['channel'], $picture);

    if (!$kept['ok']) {
        return array('ok' => false, 'error' => $kept['error'], 'field' => 'picture');
    }

    db("UPDATE ws_messages SET file_id = '" . (int) $kept['file_id'] . "', file_name = '" . e(mb_substr($kept['file_name'], 0, 255)) . "', edited_at = '" . time() . "'
        WHERE id = '" . (int) $checked['message']['id'] . "'");
    ws_message_touch($checked['message']['id']);

    return array('ok' => true, 'error' => '', 'field' => '');
}
