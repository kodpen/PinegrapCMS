<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the card of a live page in the conversation (page.php keeps
 * the page): what the browser needs to draw it (name, maker, "Edited by",
 * Undo, the frame's address and sandbox), a version's source for View
 * source, and Undo of an agent's edit. The card itself is
 * assets/js/workspace_page_card.js; the frame is answered by live_page.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page.php');

/**
 * The card's state from a page's versions, oldest first: the newest
 * version, who made the page (the first version's agent), who made the
 * newest version when it is an agent's edit, and whether this reader may
 * undo it. Null for no versions. Pure.
 *
 * @param array[] $rows     ws_page_versions()
 * @param bool    $may_undo may the reader write in the channel
 * @return array|null version, by, edited_by, can_undo, versions
 */
function ws_page_card_state($rows, $may_undo)
{
    $rows = array_values((array) $rows);

    if (!$rows) {
        return null;
    }

    $first = ws_page_version_by($rows[0]['doc_json'] ?? '');
    $newest = $rows[count($rows) - 1];
    $edited = '';

    // An edit is an agent's version after the first; an Undo (restore)
    // ends the mark, as a canvas's does.
    if ((count($rows) > 1) && ((string) $newest['source'] === 'agent')) {
        $by = ws_page_version_by($newest['doc_json'] ?? '');
        $edited = $by ? $by['handle'] : '';
    }

    return array(
        'version'   => (int) $newest['version_no'],
        'by'        => $first ? $first['handle'] : '',
        'edited_by' => $edited,
        'can_undo'  => ($edited !== '') && (bool) $may_undo,
        'versions'  => count($rows),
    );
}

/**
 * What the conversation's payload carries for a message whose file is a
 * page, or null for any other file: the card's state, the frame's address
 * and its sandbox (pg_guarded_frame_panel_sandbox()).
 *
 * @param int        $message_id
 * @param int        $file_id
 * @param bool       $may_undo   may the reader write in the channel
 * @param array|null $viewer     given: the tables the canvas uses, as the
 *                               reader sees them (ws_canvas_tables_card())
 * @return array|null
 */
function ws_page_card($message_id, $file_id, $may_undo, $viewer = null)
{
    $state = ws_page_card_state(ws_page_versions($file_id), $may_undo);

    if (!$state) {
        return null;
    }

    // live_page.php sits in the software's folder, beside the panel.
    $state['frame'] = PATH . SOFTWARE_DIRECTORY . '/live_page.php?message=' . (int) $message_id;
    $state['sandbox'] = pg_guarded_frame_panel_sandbox('script');

    if (is_array($viewer) && function_exists('ws_canvas_tables_card')) {
        $state += ws_canvas_tables_card($viewer, array('id' => (int) $message_id), $may_undo);
    }

    return $state;
}

/**
 * A version of a page as View source shows it: its HTML (what the agent
 * wrote, without the site's libraries) and the versions to choose from.
 * $version 0 is the newest.
 *
 * @param array $message ws_messages row the reader may read
 * @param int   $version
 * @return array ok, error, html, version, versions
 */
function ws_page_source($message, $version)
{
    $rows = ws_page_versions((int) $message['file_id']);

    if (!$rows) {
        return array('ok' => false, 'error' => lang('That message carries no page.'));
    }

    $picked = null;

    foreach ($rows as $row) {
        if (((int) $version === 0) || ((int) $row['version_no'] === (int) $version)) {
            $picked = $row;
        }
    }

    $html = $picked ? @file_get_contents(pg_file_versions_path($picked), false, null, 0, PG_GUARDED_FRAME_MAX_BYTES + 1) : false;

    if (!is_string($html)) {
        return array('ok' => false, 'error' => lang('That version could not be read.'));
    }

    return array(
        'ok'       => true,
        'error'    => '',
        'html'     => $html,
        'version'  => (int) $picked['version_no'],
        // created_at as a Unix time: the browser writes it in the reader's own.
        'versions' => ws_page_versions_read($rows),
    );
}

/**
 * Undo of an agent's edit on a page: the version before comes back as the
 * newest one, a new version (source restore) with the bytes and the
 * document (maker, libraries) of the version before, so the edit stays
 * among the versions. Only for a member who may write in the channel, and
 * only while the newest version is still the agent's edit the card showed
 * ($version; 409 otherwise).
 *
 * @param array $viewer
 * @param array $message ws_messages row
 * @param int   $version the version_no of the agent's edit
 * @return array ok, status (HTTP), error
 */
function ws_page_undo($viewer, $message, $version)
{
    $refuse = function ($error, $status) {
        return array('ok' => false, 'status' => $status, 'error' => $error);
    };

    $channel = ws_channel($message['channel_id']);

    if (!$channel || ((int) $message['deleted_at'] > 0) || !ws_can_post_channel($viewer, $channel)) {
        return $refuse(lang('You cannot write in this channel.'), 403);
    }

    $rows = ws_page_versions((int) $message['file_id']);
    $state = ws_page_card_state($rows, true);

    if (!$state || ($state['edited_by'] === '') || ($state['version'] !== (int) $version)) {
        return $refuse(lang('The page changed since. Look at it again.'), 409);
    }

    $before = $rows[count($rows) - 2];
    $html = @file_get_contents(pg_file_versions_path($before));
    $by = ws_page_version_by($before['doc_json'] ?? '');

    if (!is_string($html) || ($html === '') || !$by) {
        return $refuse(lang('There is no version before this edit.'), 409);
    }

    if (!ws_page_write($message, $html, $by, (int) $viewer['id'], ws_page_version_libraries($before['doc_json'] ?? ''), 'restore')) {
        return $refuse(lang('The file could not be saved.'), 500);
    }

    return array('ok' => true, 'status' => 200, 'error' => '');
}

/**
 * The texts of the page card (assets/js/workspace_page_card.js).
 *
 * @return array key => text
 */
function ws_page_card_js_strings()
{
    return array(
        'page_by'          => ws_js_template('Live page by @{var:1}. It is the agent\'s work, not the panel\'s.', 1),
        // The button, a verb: the shared lang('Open') is the state word.
        'page_open'        => lang('Open page'),
        'page_close'       => lang('Close'),
        'page_full_screen' => lang('Full screen'),
        'page_source'      => lang('View source'),
        'page_download'    => lang('Download'),
        'page_versions'    => lang('Versions'),
        'page_version'     => ws_js_template('Version {var:1}', 1),
        'page_restored'    => lang('Put back'),
        'page_copy'        => lang('Copy'),
        'page_copied'      => lang('Copied.'),
        'page_edited_by'   => ws_js_template('Edited by @{var:1}', 1),
        'page_undo'        => lang('Undo'),
        'page_undo_ask'    => lang('Put back the page as it was before this edit? The edit stays among the versions.'),
        'page_undone'      => lang('The page is back as it was.'),
    );
}
