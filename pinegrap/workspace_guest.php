<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guest's side of a conversation in the workspace: the page a guest link
 * leads to (router.php sends the token here behind a "#"). Somebody with no
 * account reads their room and writes in it, and nothing else of the site
 * opens to them from here.
 *
 * GET draws the page; its script takes the token from the address, claims it
 * with a POST and asks for news every few seconds. Every POST is JSON with an
 * action (includes/workspace/guests.php, ws_guest_handle()). The guest's own
 * cookie is all this page reads; a panel session opens nothing here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Nothing of the conversation is kept by a browser or a proxy, indexed,
// framed by another site or passed on as the referrer.
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');

if (!function_exists('ws_guests_ready') || !ws_guests_ready()) {
    output_error(lang('This link is not valid.'), 404);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $request = json_decode((string) file_get_contents('php://input'), true);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(ws_guest_handle(is_array($request) ? (string) ($request['action'] ?? '') : '', is_array($request) ? $request : array()), JSON_UNESCAPED_UNICODE);
    exit;
}

// A file or a picture staff put in the room, for the guest of that room.
if (isset($_GET['file'])) {
    ws_guest_file_send(ws_guest_context(), (int) $_GET['file']);
}

$software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
$version = function ($path) {
    return (string) @filemtime(PG_FUNCTIONS_DIR . $path);
};

$config = array(
    'endpoint' => PATH . SOFTWARE_DIRECTORY . '/workspace_guest.php',
    'emoji'    => explode(',', WS_GUEST_EMOJI),
    'max'      => WS_GUEST_MESSAGE_MAX,
    'site'     => (string) ORGANIZATION_NAME,
    'strings'  => ws_guest_page_strings(),
);

$title = h(lang('Conversation') . (((string) ORGANIZATION_NAME !== '') ? ' · ' . ORGANIZATION_NAME : ''));

echo '<!DOCTYPE html>
<html lang="' . lang(array('info' => '')) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . $title . '</title>
<link rel="stylesheet" href="' . $software . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css?v=' . $version('/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . $version('/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/css/workspace_guest.css?v=' . $version('/assets/css/workspace_guest.css') . '">
</head>
<body class="pg-guest">
<div class="pg-guest-app" id="pg-guest-app">
    <header class="pg-guest-head">
        <div class="pg-guest-head-text">
            <div class="pg-guest-site">' . h((string) ORGANIZATION_NAME) . '</div>
            <h1 class="pg-guest-title" id="pg-guest-title">' . h(lang('Conversation')) . '</h1>
        </div>
        <div class="pg-guest-staff" id="pg-guest-staff"></div>
    </header>
    <main class="pg-guest-log" id="pg-guest-log" role="log" aria-live="polite" aria-busy="true">
        <div class="pg-guest-note" id="pg-guest-note">' . h(lang('Opening the conversation…')) . '</div>
    </main>
    <footer class="pg-guest-foot d-none" id="pg-guest-foot">
        <div class="pg-guest-reply d-none" id="pg-guest-reply"></div>
        <div class="pg-guest-composer">
            <div class="pg-guest-tools" role="toolbar">
                <button type="button" class="btn btn-sm btn-link" data-wrap="**" title="' . h(lang('Bold')) . '" aria-label="' . h(lang('Bold')) . '"><i class="bi bi-type-bold" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-sm btn-link" data-wrap="_" title="' . h(lang('Italic')) . '" aria-label="' . h(lang('Italic')) . '"><i class="bi bi-type-italic" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-sm btn-link" data-wrap="~~" title="' . h(lang('Strikethrough')) . '" aria-label="' . h(lang('Strikethrough')) . '"><i class="bi bi-type-strikethrough" aria-hidden="true"></i></button>
            </div>
            <textarea class="form-control" id="pg-guest-text" rows="1" maxlength="' . (int) WS_GUEST_MESSAGE_MAX . '" placeholder="' . h(lang('Write a message')) . '" aria-label="' . h(lang('Write a message')) . '"></textarea>
            <button type="button" class="btn btn-primary pg-guest-send" id="pg-guest-send" title="' . h(lang('Send')) . '" aria-label="' . h(lang('Send')) . '"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
        </div>
        <div class="pg-guest-hint">' . h(lang('Only the people of the site in this conversation read what you write here.')) . '</div>
    </footer>
</div>
<script type="application/json" id="pg-guest-config">' . str_replace('</', '<\/', json_encode($config, JSON_UNESCAPED_UNICODE)) . '</script>
<script src="' . $software . '/assets/js/workspace_guest.js?v=' . $version('/assets/js/workspace_guest.js') . '"></script>
</body>
</html>';
