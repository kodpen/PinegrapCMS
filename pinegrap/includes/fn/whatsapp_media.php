<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A customer's WhatsApp picture, video, voice message or document brought
 * into the Workspace conversation it came in, as an e-mail's attachments
 * are: a file message under the conversation's card. The webhook keeps only
 * the media id (whatsapp_inbox.media_id); the general job asks Meta where the
 * file is and fetches it (pg_whatsapp_media_run()). A file Meta no longer
 * gives, of a type the Workspace does not keep, or larger than the job reads
 * is given up and named in the item's refused_files, so it is not asked for
 * again; anything else is asked for again on the next run, for a day.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most bytes of one received file: the job holds it in memory to keep it.
define('PG_WHATSAPP_MEDIA_MAX', 16 * 1048576);

// How long a received file is asked for before it is left.
define('PG_WHATSAPP_MEDIA_WINDOW', 86400);

/**
 * The file ending of a type WhatsApp delivers and the Workspace keeps; ''
 * for any other. Pure.
 *
 * @param string $mime Meta's type, parameters allowed (audio/ogg; codecs=opus)
 * @return string
 */
function pg_whatsapp_media_extension($mime)
{
    $types = array(
        'image/jpeg'         => 'jpg',
        'image/png'          => 'png',
        'image/webp'         => 'webp',
        'video/mp4'          => 'mp4',
        'video/3gpp'         => '3gp',
        'audio/ogg'          => 'ogg',
        'audio/mpeg'         => 'mp3',
        'audio/mp4'          => 'm4a',
        'audio/aac'          => 'aac',
        'audio/amr'          => 'amr',
        'application/pdf'    => 'pdf',
        'text/plain'         => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    );
    $type = strtolower(trim(explode(';', (string) $mime)[0]));

    return $types[$type] ?? '';
}

/**
 * The name a received file is kept under: a document's own name when the
 * message carried one with the type's ending, else its kind and the time it
 * came (UTC). '' for a type that is not kept. Pure.
 *
 * @param string $kind image, video, audio, document, sticker
 * @param string $mime
 * @param string $body the message's text: a document's name or a caption
 * @param int    $at   when it came
 * @return string
 */
function pg_whatsapp_media_file_name($kind, $mime, $body, $at)
{
    $extension = pg_whatsapp_media_extension($mime);

    if ($extension === '') {
        return '';
    }

    $name = trim((string) $body);

    if (($kind === 'document') && preg_match('/^[^\/\\\\\x00-\x1F]{1,150}$/uD', $name)
        && (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) === $extension) && (substr($name, 0, 1) !== '.')) {
        return $name;
    }

    $kind = preg_match('/^[a-z]{1,16}$/D', (string) $kind) ? (string) $kind : 'file';

    return 'whatsapp-' . $kind . '-' . gmdate('Ymd-His', (int) $at) . '.' . $extension;
}

/**
 * Why a received file is not fetched, from what Meta says of it: '' to fetch
 * it, type (not kept) or too_large. A size Meta did not give is checked as
 * the bytes come. Pure.
 *
 * @param string $mime
 * @param int    $size
 * @return string
 */
function pg_whatsapp_media_refusal($mime, $size)
{
    if (pg_whatsapp_media_extension($mime) === '') {
        return 'type';
    }

    return ((int) $size > PG_WHATSAPP_MEDIA_MAX) ? 'too_large' : '';
}

/**
 * Whether received files can be fetched here: the Workspace's phone
 * conversations and the WhatsApp inbox exist.
 *
 * @return bool
 */
function pg_whatsapp_media_ready()
{
    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_webhook.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    return pg_whatsapp_inbox_ready() && function_exists('ws_mail_phone_ready') && ws_enabled() && ws_mail_phone_ready();
}

/**
 * Fetches one received file from Meta.
 *
 * @param array  $app
 * @param string $media_id
 * @return array ok, bytes, mime, error, gone (given up for good)
 */
function pg_whatsapp_media_fetch($app, $media_id)
{
    pg_whatsapp_driver();

    $response = pg_whatsapp_http('GET', pg_whatsapp_cloud_media_info_request($app, $media_id));
    $info = pg_whatsapp_cloud_media_info_parse(pg_whatsapp_cloud_answer($response['status'], $response['body'], $response['error']));

    if (!$info['ok']) {
        return array('ok' => false, 'bytes' => '', 'mime' => '', 'error' => $info['error'], 'gone' => $info['gone']);
    }

    $refusal = pg_whatsapp_media_refusal($info['mime'], $info['size']);

    if ($refusal !== '') {
        return array('ok' => false, 'bytes' => '', 'mime' => $info['mime'], 'error' => $refusal, 'gone' => true);
    }

    $request = pg_whatsapp_cloud_media_file_request($app, $info['url']);
    $answer = pg_curl_run('GET', $request['url'], array('headers' => $request['headers'], 'timeout' => 60, 'connect_timeout' => 10,
        'cap' => PG_WHATSAPP_MEDIA_MAX + 1, 'user_agent' => 'Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (+whatsapp)'));

    if (!empty($answer['capped'])) {
        return array('ok' => false, 'bytes' => '', 'mime' => $info['mime'], 'error' => 'too_large', 'gone' => true);
    }

    if (((int) $answer['errno'] !== 0) || ((int) $answer['status'] !== 200) || ((string) $answer['body'] === '')) {
        return array('ok' => false, 'bytes' => '', 'mime' => $info['mime'], 'error' => 'fetch', 'gone' => false);
    }

    return array('ok' => true, 'bytes' => (string) $answer['body'], 'mime' => $info['mime'], 'error' => '', 'gone' => false);
}

/**
 * The general job's pass: received WhatsApp messages of the last day that
 * carry a file not fetched yet nor given up, a few each run. A fetched file
 * becomes a message under the conversation's card (as an e-mail's
 * attachment); one given up is named in the item's refused_files.
 *
 * @param int $limit
 * @param int $seconds
 * @return array fetched, given_up
 */
function pg_whatsapp_media_run($limit = 5, $seconds = 20)
{
    $result = array('fetched' => 0, 'given_up' => 0);

    if (!pg_whatsapp_media_ready() || !pg_whatsapp_ready() || (function_exists('pg_demo') && pg_demo())) {
        return $result;
    }

    $rows = db_items(
        "SELECT i.id, i.box_id, i.conversation_id, i.created_at, w.type, w.body, w.media_id, w.received_at
        FROM ws_mail_items i
        INNER JOIN ws_mail_boxes b ON b.id = i.box_id AND b.transport = 'whatsapp'
        INNER JOIN whatsapp_inbox w ON w.wamid = i.rfc_message_id AND w.media_id <> ''
        WHERE
            i.direction = 'in'
            AND i.created_at > '" . (time() - PG_WHATSAPP_MEDIA_WINDOW) . "'
            AND i.conversation_id > 0
            AND i.refused_files = ''
            AND NOT EXISTS (SELECT 1 FROM ws_messages m WHERE m.sender_kind = 'mail' AND m.sender_id = i.id AND m.file_id > 0)
        ORDER BY i.id
        LIMIT " . (int) $limit);

    $app = pg_whatsapp_app();
    $deadline = microtime(true) + $seconds;

    foreach ((array) $rows as $row) {
        if (microtime(true) > $deadline) {
            break;
        }

        $fetched = pg_whatsapp_media_fetch($app, (string) $row['media_id']);
        $name = pg_whatsapp_media_file_name((string) $row['type'], (string) $fetched['mime'], (string) $row['body'], (int) $row['received_at']);

        if ($fetched['ok'] && ($name !== '') && pg_whatsapp_media_keep($row, $name, $fetched['bytes'])) {
            $result['fetched']++;
            continue;
        }

        // Meta not reached or a passing failure: asked again on the next run.
        if (!$fetched['ok'] && !$fetched['gone']) {
            continue;
        }

        $given = ($name !== '') ? $name : ws_mail_phone_kind_text((string) $row['type']);
        db("UPDATE ws_mail_items SET refused_files = '" . e(mb_substr($given, 0, 255)) . "' WHERE id = '" . (int) $row['id'] . "' AND refused_files = ''");
        $result['given_up']++;
    }

    return $result;
}

/**
 * Keeps a fetched file under its conversation's card, checked as an
 * upload is.
 *
 * @param array  $row   the pass's row: id, box_id, conversation_id
 * @param string $name
 * @param string $bytes
 * @return bool false when the Workspace refused it
 */
function pg_whatsapp_media_keep($row, $name, $bytes)
{
    $conversation = db_item("SELECT channel_id, message_id FROM ws_mail_conversations WHERE id = '" . (int) $row['conversation_id'] . "'");
    $channel = is_array($conversation) ? ws_channel((int) $conversation['channel_id']) : null;
    $box = ws_mail_box((int) $row['box_id']);

    if (!$channel || ((int) $channel['archived_at'] > 0)) {
        return false;
    }

    $inspected = ws_upload_inspect($name, $bytes);
    $stored = $inspected['ok']
        ? ws_store_file_bytes($channel, $inspected['name'], $inspected['extension'], $bytes, (int) ($box['created_by'] ?? 0))
        : array('ok' => false);

    if (!$stored['ok']) {
        return false;
    }

    $card = ws_message((int) $conversation['message_id']);
    $parent_id = ($card && ((int) $card['channel_id'] === (int) $channel['id']) && ((int) $card['deleted_at'] === 0)) ? (int) $card['id'] : 0;

    if (ws_mail_message_write($channel['id'], $parent_id, (int) $row['id'], '', $stored['file_id'], $stored['name'], time()) <= 0) {
        ws_upload_discard($stored['file_id']);

        return false;
    }

    if ($parent_id > 0) {
        ws_message_touch($parent_id);
    }

    return true;
}
