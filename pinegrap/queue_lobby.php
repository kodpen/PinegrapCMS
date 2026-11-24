<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A walk-in queue place's lobby screen, for a TV or monitor in the waiting
 * room (includes/fn/walk_in_queue_screens.php). Opened by the place's
 * signed display link, ?place=<id>&k=<token>, with no sign-in and no menu:
 * the numbers now served as figure cards with their desks, the last five
 * calls under them, and a chime on each call once the sound is turned on.
 * Numbers only, never a name. The page reads the board every few seconds
 * (&read=1, JSON); that read has the firewall's queue status bucket
 * (waf_queue_status_read()). No session is started: nothing here is a form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The screen runs all day and reads every few seconds: no session file per
// read. $_SESSION stays an array so the shared code finds nothing in it.
define('PG_NO_SESSION', true);
$_SESSION = array();

include('init.php');

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$ql_place = pg_waiting_screen_place($_GET, 'lobby');
$ql_read = !empty($_GET['read']);

if (!$ql_place) {
    set_response_code(404);

    if ($ql_read) {
        header('Content-Type: application/json; charset=utf-8');
        print json_encode(array('ok' => false));
        exit;
    }

    print output_header_secure(array('title' => lang('Lobby screen')))
        . pg_secure_card('bi-display', lang('Lobby screen'), '<p>' . h(lang('This link cannot be used. Ask for the lobby screen link on the place\'s Desks tab.')) . '</p>')
        . output_footer_secure();
    exit;
}

$ql_board = pg_waiting_lobby_read($ql_place);

if ($ql_read) {
    header('Content-Type: application/json; charset=utf-8');
    print json_encode(array('ok' => true) + $ql_board, JSON_UNESCAPED_UNICODE);
    exit;
}

$ql_now = '';

foreach ($ql_board['now'] as $ql_call) {
    $ql_now .= '<div class="pg-card pg-figure"><span class="pg-figure-value" style="font-size:clamp(2.5rem,7vw,6rem)">' . h($ql_call['number']) . '</span>'
        . '<span class="pg-figure-label" style="font-size:clamp(1.125rem,2.2vw,2rem)">' . h($ql_call['desk']) . '</span></div>';
}

$ql_past = '';

foreach ($ql_board['past'] as $ql_call) {
    $ql_past .= '<div class="pg-card-inner-row"><strong class="pg-tabular flex-grow-1 text-nowrap" style="font-size:clamp(1.25rem,2.5vw,2.25rem)">' . h($ql_call['number']) . '</strong>'
        . '<span class="pg-text-2" style="font-size:clamp(1rem,2vw,1.75rem)">' . h($ql_call['desk']) . '</span></div>';
}

$ql_self = pg_waiting_screen_url($ql_place['id'], 'lobby');

print output_header_secure(array('title' => $ql_place['name']))
    . '<main class="container-fluid py-7 px-5 md:px-9" data-pg-lobby-read="' . h($ql_self . '&read=1') . '" data-pg-lobby-seconds="' . PG_WAITING_LOBBY_READ_SECONDS . '" data-pg-lobby-stamp="' . h($ql_board['stamp']) . '">'
    . '<header class="d-flex flex-wrap align-items-center gap-3 mb-7">'
    . '<h1 class="mb-0 flex-grow-1" style="font-size:clamp(1.75rem,3.5vw,3rem)">' . h($ql_place['name']) . '</h1>'
    . '<span class="pg-pill pg-tone-gray" data-pg-lobby-closed' . ($ql_board['open'] ? ' hidden' : '') . '>' . h(lang('Closed')) . '</span>'
    . '<button type="button" class="pg-button pg-button-quiet" data-pg-lobby-sound><i class="bi bi-volume-up" aria-hidden="true"></i>' . h(lang('Turn the sound on')) . '</button>'
    . '<button type="button" class="pg-button pg-button-quiet" data-pg-lobby-full><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>' . h(lang('Full screen')) . '</button>'
    . '</header>'
    . '<section aria-labelledby="ql_now_title" class="mb-9"><h2 class="pg-title-section mb-5" id="ql_now_title" style="font-size:clamp(1.25rem,2.5vw,2.25rem)">' . h(lang('Now serving')) . '</h2>'
    . '<div class="pg-figures" style="--pg-figures:3" role="status" aria-live="polite" data-pg-lobby-now>' . $ql_now . '</div>'
    . '<p class="pg-text-2" style="font-size:clamp(1.125rem,2.2vw,2rem)" data-pg-lobby-empty' . ($ql_board['now'] ? ' hidden' : '') . '>' . h(lang('No number is being served now.')) . '</p></section>'
    . '<section aria-labelledby="ql_past_title" data-pg-lobby-past-wrap' . ($ql_board['past'] ? '' : ' hidden') . '><h2 class="pg-title-section mb-5" id="ql_past_title" style="font-size:clamp(1.125rem,2.2vw,2rem)">' . h(lang('Called before')) . '</h2>'
    . '<div class="pg-card-inner" data-pg-lobby-past>' . $ql_past . '</div></section>'
    . '</main>'
    . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/queue_lobby.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/queue_lobby.js') . '"></script>'
    . output_footer_secure();
