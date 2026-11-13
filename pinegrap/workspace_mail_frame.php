<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a mail's kept HTML in the guarded frame, the mail mode
 * (includes/fn/guarded_frame.php), as the E-mails screen's reading pane
 * frames it:
 *
 *   workspace_mail_frame.php?item=<id>             its own pictures only
 *   workspace_mail_frame.php?item=<id>&pictures=1  pictures from outside too
 *
 * No script of the mail runs and nothing of it reaches the panel; a link
 * opens a new tab. The mail must belong to a conversation the reader may
 * read (ws_mail_frame()). Anything else, and a request that says it is not
 * a frame's, is answered 404 with nothing of the mail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

/**
 * Answers 404 with a short line and stops.
 */
function workspace_mail_frame_refuse()
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo lang('That page could not be found.');
    exit();
}

if (!ws_ready() || !ws_enabled() || !pg_guarded_frame_request_framed($_SERVER)) {
    workspace_mail_frame_refuse();
}

$viewer = ws_viewer($user);
$item_id = pg_guarded_frame_page_id($_GET['item'] ?? null);
$frame = (($item_id > 0) && $viewer['member']) ? ws_mail_frame($viewer, $item_id) : null;

if (!$frame) {
    workspace_mail_frame_refuse();
}

session_write_close();

pg_guarded_frame_send(pg_mail_html_document($frame['html']), 'mail', lang('E-mail'), array(
    'files'    => $frame['files'],
    'pictures' => (($_GET['pictures'] ?? '') === '1'),
));
