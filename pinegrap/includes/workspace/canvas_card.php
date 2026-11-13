<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the joined card of a canvas with two parts (a drawing and a
 * live page, canvas_parts.php): what the conversation's payload carries for
 * it (file.joined), the one list of versions over both parts, and a chosen
 * version of a part put back as the newest one.
 *
 * The card itself is drawn by assets/js/workspace_page_card.js (joined());
 * the page part keeps the page card's frame, bridge and first-write
 * question, read through the page part's file.
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
 * The versions of both parts as the card's drawer lists them: newest
 * first, each with its part, number, how it was made, by whom and when,
 * and whether it is the part's newest. Pure.
 *
 * @param array[] $parts part => file_versions rows oldest first (version_no,
 *                       source, user_id, created_at, and for an agent's
 *                       version its maker as by, the handle)
 * @return array[] part, version, source, by, user_id, created_at, current
 */
function ws_canvas_card_versions($parts)
{
    $list = array();

    foreach ($parts as $part => $rows) {
        $rows = array_values((array) $rows);
        $last = count($rows) - 1;

        foreach ($rows as $i => $row) {
            $list[] = array(
                'part'       => (string) $part,
                'version'    => (int) $row['version_no'],
                'source'     => (string) $row['source'],
                'by'         => (string) ($row['by'] ?? ''),
                'user_id'    => (int) $row['user_id'],
                'created_at' => (int) $row['created_at'],
                'current'    => ($i === $last),
            );
        }
    }

    // Newest first; versions of one second keep their part's order.
    usort($list, function ($a, $b) {
        if ($a['created_at'] !== $b['created_at']) {
            return ($a['created_at'] > $b['created_at']) ? -1 : 1;
        }

        if ($a['part'] !== $b['part']) {
            return strcmp($a['part'], $b['part']);
        }

        return ($a['version'] === $b['version']) ? 0 : (($a['version'] > $b['version']) ? -1 : 1);
    });

    return $list;
}

/**
 * The version rows of a drawing with each agent version's handle.
 *
 * @param int $file_id
 * @return array[]
 */
function ws_canvas_card_drawing_rows($file_id)
{
    $rows = pg_file_versions_rows((int) $file_id);
    $by = array();

    foreach ((array) db_items("SELECT version_no, SUBSTRING(doc_json, LOCATE('\"by\":{', doc_json), 300) AS by_part FROM file_versions
        WHERE file_id = '" . (int) $file_id . "' AND source = 'agent'") as $row) {
        $by[(int) $row['version_no']] = ws_canvas_by_handle((string) $row['by_part']);
    }

    foreach ($rows as $i => $row) {
        $rows[$i]['by'] = $by[(int) $row['version_no']] ?? '';
    }

    return $rows;
}

/**
 * The version rows of a page with each version's maker.
 *
 * @param int $file_id
 * @return array[]
 */
function ws_canvas_card_page_rows($file_id)
{
    $rows = ws_page_versions((int) $file_id);

    foreach ($rows as $i => $row) {
        $by = ws_page_version_by($row['doc_json'] ?? '');
        $rows[$i]['by'] = $by ? $by['handle'] : '';
    }

    return $rows;
}

/**
 * What the payload carries for a canvas with two parts, or null for a
 * canvas with one (its own card stays): the drawing's picture and state,
 * the page card's state read from the page part, the versions over both,
 * and whether this reader may put a version back.
 *
 * @param array $message  ws_messages row
 * @param bool  $can_post may the reader write in the channel
 * @param array $viewer
 * @return array|null
 */
function ws_canvas_joined_card($message, $can_post, $viewer)
{
    if (!ws_canvas_part_rows((int) $message['id'])) {
        return null;
    }

    $drawing_id = ws_canvas_part_file($message, 'drawing');
    $page_id = ws_canvas_part_file($message, 'page');
    $page = ($page_id > 0) ? ws_page_card((int) $message['id'], $page_id, $can_post, $viewer) : null;
    $stored = ($drawing_id > 0) ? (string) db_value("SELECT name FROM files WHERE id = '" . $drawing_id . "'") : '';
    $state = ($stored !== '') ? ws_canvas_message_state($drawing_id) : null;

    if (!$page || !$state) {
        return null;
    }

    return array(
        'name'     => (string) preg_replace('/\.[a-z0-9]{2,5}$/i', '', (string) $message['file_name']),
        'drawing'  => array(
            'url'     => ws_canvas_picture_url(PATH . encode_url_path($stored), $state),
            'version' => (int) $state['version_no'],
            // The version a browser draws and posts, 0 for none.
            'pending' => (($state['pending'] || $state['server']) && $can_post) ? (int) $state['version_no'] : 0,
        ),
        'page'     => $page,
        'versions' => ws_canvas_card_versions(array(
            'drawing' => ws_canvas_card_drawing_rows($drawing_id),
            'page'    => ws_canvas_card_page_rows($page_id),
        )),
        'can_restore' => (bool) $can_post,
    );
}

/**
 * Puts a chosen version of a part back as its newest one, as a new version
 * (source restore), so every version stays. Only for a member who may
 * write in the channel; the newest version is already there.
 *
 * @param array  $viewer
 * @param array  $message ws_messages row
 * @param string $part    drawing | page
 * @param int    $version the version_no to put back
 * @return array ok, error
 */
function ws_canvas_part_restore($viewer, $message, $part, $version)
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error);
    };

    $channel = ws_channel($message['channel_id']);

    if (!$channel || ((int) $message['deleted_at'] > 0) || !ws_can_post_channel($viewer, $channel)) {
        return $refuse(lang('You cannot write in this channel.'));
    }

    $target = in_array($part, ws_canvas_part_names(), true) ? ws_canvas_part_message($message, $part) : null;
    $file_id = $target ? (int) $target['file_id'] : 0;
    $row = ($file_id > 0) ? db_item("SELECT * FROM file_versions WHERE file_id = '" . $file_id . "' AND version_no = '" . (int) $version . "' LIMIT 1") : null;
    $newest = ($file_id > 0) ? pg_file_versions_newest($file_id) : null;

    if (!$row || !$newest || !is_file(pg_file_versions_path($row))) {
        return $refuse(lang('That version could not be read.'));
    }

    if ((int) $row['version_no'] === (int) $newest['version_no']) {
        return $refuse(lang('That is the version shown now.'));
    }

    $data = @file_get_contents(pg_file_versions_path($row));

    if (!is_string($data) || ($data === '')) {
        return $refuse(lang('That version could not be read.'));
    }

    if ($part === 'page') {
        $by = ws_page_version_by($row['doc_json'] ?? '');

        return ws_page_write($target, $data, $by ?: array('id' => 0, 'kind' => 'ai', 'handle' => ''), (int) $viewer['id'], ws_page_version_libraries($row['doc_json'] ?? ''), 'restore')
            ? array('ok' => true, 'error' => '') : $refuse(lang('The file could not be saved.'));
    }

    if (ws_canvas_doc_pending(json_decode((string) $row['doc_json'], true), $row['source'])) {
        return $refuse(lang('That version has no picture yet. Try again in a moment.'));
    }

    // Written in the file directory, as the picture's take writes it.
    $path = FILE_DIRECTORY_PATH . '/' . basename((string) db_value("SELECT name FROM files WHERE id = '" . $file_id . "'"));

    if (!pg_image_editor_put($path, $data) || !pg_file_versions_add($file_id, $path, (string) $row['doc_json'], 'restore', (int) $viewer['id'], (int) $row['redacted'] === 1)) {
        return $refuse(lang('The file could not be saved.'));
    }

    pg_file_versions_prune($file_id);
    db_q("UPDATE files SET size = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array(strlen($data), $file_id));

    if (function_exists('pg_file_cache_forget')) {
        pg_file_cache_forget($file_id);
    }

    ws_message_touch((int) $message['id']);

    return array('ok' => true, 'error' => '');
}
