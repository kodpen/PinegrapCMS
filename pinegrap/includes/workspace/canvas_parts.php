<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the parts of a canvas (ws_canvas_parts, 2026.11.0 step
 * 11.0.194). A canvas is the message that carries it, and the message's own
 * file is its first part: a drawing (a canvas document in its versions) or
 * a live page (html versions). The other part, added later, is a file of
 * the channel named by a row here; the message never changes.
 *
 * Whatever reads or writes a part asks for that part's file here, so a page
 * added beside a drawing is read, written and bound to tables as the page
 * a message carries itself.
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
 * Has the step that brings the parts run?
 *
 * @return bool
 */
function ws_canvas_parts_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_canvas_parts', 'made_by_kind');
    }

    return $ready;
}

/**
 * The parts a canvas may have beside its data. Pure.
 *
 * @return string[]
 */
function ws_canvas_part_names()
{
    return array('drawing', 'page');
}

/**
 * Which file holds a part: the message's own when it is of that kind, else
 * the file a row names; 0 when the canvas has not got the part. Pure.
 *
 * @param string $part     drawing | page
 * @param int    $own_id   the message's file
 * @param string $own_kind what the message's file is: drawing, page or ''
 * @param array  $rows     part => file id, from ws_canvas_parts
 * @return int
 */
function ws_canvas_part_file_pick($part, $own_id, $own_kind, $rows)
{
    if (((int) $own_id > 0) && ($own_kind === $part)) {
        return (int) $own_id;
    }

    // A row never stands for the kind the message's own file is: the
    // message's file is that part, whatever a row says.
    if ($own_kind === $part) {
        return 0;
    }

    return (int) ($rows[$part] ?? 0);
}

/**
 * What the message's own file is.
 *
 * @param int $file_id
 * @return string drawing, page or ''
 */
function ws_canvas_own_kind($file_id)
{
    if ((int) $file_id <= 0) {
        return '';
    }

    if (ws_page_is_page((int) $file_id)) {
        return 'page';
    }

    return ws_canvas_open_doc((int) $file_id) ? 'drawing' : '';
}

/**
 * The part rows of a canvas.
 *
 * @param int $message_id
 * @return array part => file id
 */
function ws_canvas_part_rows($message_id)
{
    if (!ws_canvas_parts_ready() || ((int) $message_id <= 0)) {
        return array();
    }

    $rows = array();

    foreach ((array) db_items("SELECT part, file_id FROM ws_canvas_parts WHERE message_id = '" . (int) $message_id . "'") as $row) {
        $rows[(string) $row['part']] = (int) $row['file_id'];
    }

    return $rows;
}

/**
 * The file of a part of a canvas, 0 when it has not got the part.
 *
 * @param array  $message ws_messages row
 * @param string $part    drawing | page
 * @return int
 */
function ws_canvas_part_file($message, $part)
{
    if (!is_array($message) || ((int) ($message['id'] ?? 0) <= 0)) {
        return 0;
    }

    $own = (int) ($message['file_id'] ?? 0);

    return ws_canvas_part_file_pick($part, $own, ws_canvas_own_kind($own), ws_canvas_part_rows((int) $message['id']));
}

/**
 * The message as a part's reader takes it: the same row with the part's
 * file as its file, or null when the canvas has not got the part.
 *
 * @param array  $message ws_messages row
 * @param string $part    drawing | page
 * @return array|null
 */
function ws_canvas_part_message($message, $part)
{
    $file_id = ws_canvas_part_file($message, $part);

    return ($file_id > 0) ? array('file_id' => $file_id) + $message : null;
}

/**
 * Names a part's file for a canvas. Two writers at once keep one row (the
 * unique key); the second learns it lost and drops its file.
 *
 * @param int    $message_id
 * @param string $part    drawing | page
 * @param int    $file_id
 * @param array  $by      kind (app|user), id
 * @return bool the row is this file's
 */
function ws_canvas_part_add($message_id, $part, $file_id, $by)
{
    if (!ws_canvas_parts_ready() || !in_array($part, ws_canvas_part_names(), true) || ((int) $file_id <= 0)) {
        return false;
    }

    $kind = ((string) ($by['kind'] ?? '') === 'app') ? 'app' : 'user';

    db("INSERT IGNORE INTO ws_canvas_parts (message_id, part, file_id, made_by_kind, made_by_id, created_at)
        VALUES ('" . (int) $message_id . "', '" . escape($part) . "', '" . (int) $file_id . "', '" . $kind . "', '" . (int) ($by['id'] ?? 0) . "', UNIX_TIMESTAMP())");

    $rows = ws_canvas_part_rows((int) $message_id);

    return (int) ($rows[$part] ?? 0) === (int) $file_id;
}
