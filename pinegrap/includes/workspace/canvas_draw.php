<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an agent's canvas drawn by the server at once. A canvas an
 * agent writes (api_canvas.php, canvas_ai.php) is kept with a blank picture
 * marked pending until a member's browser draws it. Where this server's
 * Imagick draws the whole document as the image editor would
 * (includes/fn/image_draw.php), the picture is drawn here right after the
 * version is kept and the mark becomes "server": the canvas is seen at
 * once, its API read says drawn, and it can be saved into Files. A member's
 * browser that shows it still draws it with the editor's own renderer, and
 * that picture replaces the server's (ws_canvas_picture_take()). A document
 * the server does not draw stays pending, as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_draw.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_split.php');

/**
 * The local file of a picture layer's address, when every reader of the
 * canvas may see that picture: a file a message of the channel carries, a
 * file any visitor may fetch, or a picture kept in the canvas's own folder
 * (a picture from a member's computer, ws_canvas_pictures_keep()). '' for
 * any other address: the canvas then waits for a browser, which loads the
 * address with its reader's own rights. Nothing is fetched.
 *
 * @param string $src
 * @param int    $channel_id
 * @param int    $folder_id the canvas's folder, 0 for none
 * @return string
 */
function ws_canvas_draw_picture_path($src, $channel_id, $folder_id)
{
    $name = ws_canvas_split_file_name($src, defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '', defined('PATH') ? PATH : '/');

    if ($name === '') {
        return '';
    }

    $found = ws_canvas_split_file_row("files.name = '" . escape($name) . "'", (int) $channel_id, true);
    $id = $found ? (int) $found['id'] : 0;

    if (!$id && ((int) $folder_id > 0)) {
        $id = (int) db_value("SELECT id FROM files WHERE name = '" . escape($name) . "' AND folder = '" . (int) $folder_id . "' LIMIT 1");
    }

    return ($id > 0) ? pg_file_local_copy($id) : '';
}

/**
 * Draws the picture of a message's canvas on the server, when its newest
 * version is an agent's, still pending, the file still that version's
 * picture, and this server draws all of the document. The picture is
 * written over the message's file and the version's copy as a browser's
 * is, claimed by the same conditional UPDATE, and the version's mark
 * becomes "server". Any failure leaves the canvas pending for a browser.
 *
 * @param array $message ws_messages row: id, channel_id, file_id
 * @return bool drawn
 */
function ws_canvas_draw_now($message)
{
    try {
        $file_id = (int) ($message['file_id'] ?? 0);

        if (($file_id <= 0) || !pg_file_versions_ready() || empty(pg_image_draw_can()['shapes'])) {
            return false;
        }

        $file = db_item("SELECT name, folder FROM files WHERE id = '" . $file_id . "'");
        $newest = $file ? pg_file_versions_newest($file_id) : null;
        $doc = $newest ? json_decode((string) $newest['doc_json'], true) : null;

        if (!$newest || !ws_canvas_doc_pending($doc, $newest['source']) || !pg_file_versions_matches_file($newest, pg_file_local_copy($file_id))) {
            return false;
        }

        $before = @file_get_contents(pg_file_versions_path($newest));
        $channel_id = (int) $message['channel_id'];
        $folder_id = (int) $file['folder'];
        $png = pg_image_draw_document($doc, function ($src) use ($channel_id, $folder_id) {
            return ws_canvas_draw_picture_path($src, $channel_id, $folder_id);
        });

        if ($png === '') {
            return false;
        }

        // Made smaller where that keeps every pixel, as a browser's
        // picture is (ws_canvas_picture()).
        $temporary = PG_FUNCTIONS_DIR . '/data/ws_canvas_' . bin2hex(random_bytes(8)) . '.png';

        if (@file_put_contents($temporary, $png) === strlen($png)) {
            pg_process_image_file($temporary);
            $smaller = @file_get_contents($temporary);
            $png = (is_string($smaller) && ($smaller !== '') && (strlen($smaller) < strlen($png))) ? $smaller : $png;
        }

        @unlink($temporary);

        $object = json_decode((string) $newest['doc_json']);
        $object->picture = 'server';
        $json = ws_canvas_doc_json($object);

        if ($json === '') {
            return false;
        }

        $claimed = db_q("UPDATE file_versions SET doc_json = ?, doc_hash = ?, size = ? WHERE version_id = ? AND doc_hash = ?", array(
            $json, sha1($json), strlen($png), (int) $newest['version_id'], (string) $newest['doc_hash']));

        if (!$claimed || (mysqli_affected_rows(db::$con) !== 1)) {
            return false;
        }

        // Written in the file directory, as a browser's picture is.
        if (!pg_image_editor_put(pg_file_versions_path($newest), $png) || !pg_image_editor_put(FILE_DIRECTORY_PATH . '/' . basename((string) $file['name']), $png)) {
            db_q("UPDATE file_versions SET doc_json = ?, doc_hash = ?, size = ? WHERE version_id = ?", array(
                (string) $newest['doc_json'], (string) $newest['doc_hash'], (int) $newest['size'], (int) $newest['version_id']));

            // The version's picture back where it was already written over.
            if (is_string($before) && ($before !== '')) {
                pg_image_editor_put(pg_file_versions_path($newest), $before);
                pg_image_editor_put(FILE_DIRECTORY_PATH . '/' . basename((string) $file['name']), $before);
            }

            return false;
        }

        db_q("UPDATE files SET size = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array(strlen($png), $file_id));

        if (function_exists('pg_file_cache_forget')) {
            pg_file_cache_forget($file_id);
        }

        ws_message_touch((int) $message['id']);

        return true;
    } catch (Throwable $error) {
        return false;
    }
}
