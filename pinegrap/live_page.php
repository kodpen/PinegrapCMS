<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a stored page shown in the guarded frame
 * (includes/fn/guarded_frame.php). The panel frames this address; it answers
 * the shell with the page inside it:
 *
 *   live_page.php?message=<id>              the page with its script
 *   live_page.php?message=<id>&mode=static  the same, no script at all
 *
 * The message must be one the reader may read, not deleted, carrying a
 * live page (ws_page_is_page(): an .html file with versions the page routes
 * wrote) of at most 500 KB. Anything else, and a request that
 * says it is not a frame's, is answered 404 with nothing of the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page.php');

/**
 * Answers 404 with a short line and stops.
 */
function live_page_refuse()
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo lang('That page could not be found.');
    exit();
}

if (!ws_ready() || !ws_enabled() || !pg_guarded_frame_request_framed($_SERVER)) {
    live_page_refuse();
}

$viewer = ws_viewer($user);
$message_id = pg_guarded_frame_page_id($_GET['message'] ?? null);
$message = (($message_id > 0) && $viewer['member']) ? ws_message($message_id) : null;
$channel = $message ? ws_channel($message['channel_id']) : null;

if (!$message || !$channel || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, $channel)) {
    live_page_refuse();
}

session_write_close();

$file = ((int) $message['file_id'] > 0)
    ? db_item("SELECT name, size FROM files WHERE id = '" . (int) $message['file_id'] . "'")
    : null;

// Only a page the routes wrote (includes/workspace/page.php): an .html file
// that reached a channel any other way is never run.
if (!is_array($file) || !pg_guarded_frame_is_page_name((string) $file['name']) || ((int) $file['size'] > PG_GUARDED_FRAME_MAX_BYTES)
    || !ws_page_is_page((int) $message['file_id'])) {
    live_page_refuse();
}

$path = pg_file_reader_path((string) $file['name']);
$html = ($path !== '') ? @file_get_contents($path, false, null, 0, PG_GUARDED_FRAME_MAX_BYTES + 1) : false;

if (!is_string($html) || (strlen($html) > PG_GUARDED_FRAME_MAX_BYTES)) {
    live_page_refuse();
}

pg_guarded_frame_send($html, pg_guarded_frame_mode($_GET['mode'] ?? null), lang('Live page'));
