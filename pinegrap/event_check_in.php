<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ticket check-in at the door. The page is drawn once, built for a phone;
 * every action after that (a scan, a typed code, a name search, a check-in
 * from the search, an undo) is a POST to this same file that answers in
 * JSON. The camera is read through the browser's own barcode reader
 * (assets/js/event_check_in.js); without one the typed code is the way.
 * The work itself is in includes/fn/event_check_in.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_check_in.php');

$request = (isset($_POST['request']) && is_string($_POST['request'])) ? $_POST['request'] : '';

/**
 * Sends one JSON answer and stops.
 *
 * @param array $data
 * @param int   $status
 */
function event_check_in_json(array $data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

// A script waiting for JSON gets its refusal in JSON, not the login page.
if (($request !== '') && !USER_LOGGED_IN) {
    event_check_in_json(array('ok' => false, 'code' => 'session', 'message' => lang('Your session has expired. Reload the page and sign in again.')), 401);
}

$user = validate_user();
$event_access = 'validate_calendar_event_access';

// No right of its own: an event's tickets for whoever may manage a calendar,
// a product's for whoever manages commerce.
$may_enter = ((int) $user['role'] < 3)
    || !empty($user['manage_ecommerce'])
    || (!empty($user['manage_calendars']) && validate_calendars_access($user, true));

if (!$may_enter) {
    log_activity(lang('access denied to ticket check-in'), $_SESSION['sessionusername'] ?? '');

    if ($request !== '') {
        event_check_in_json(array('ok' => false, 'code' => 'denied', 'message' => lang('Access denied.')), 403);
    }

    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

if ($request !== '') {
    $post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';
    $session_token = (string) ($_SESSION['software']['token'] ?? '');

    if (($session_token === '') || !hash_equals($session_token, $post_token)) {
        event_check_in_json(array('ok' => false, 'code' => 'token', 'message' => lang('Your session expired. Reload the page and try again.')), 403);
    }

    if (!pg_event_tickets_ready()) {
        event_check_in_json(array('ok' => false, 'code' => 'off', 'message' => lang('Tickets are not set up on this site.')), 409);
    }

    $pick = pg_check_in_pick_parse($_POST['pick'] ?? null);

    if (($pick === null) || !pg_check_in_allowed((int) $pick['event_id'], $user, $event_access)) {
        event_check_in_json(array('ok' => false, 'code' => 'pick', 'message' => lang('Choose an event or a ticket product first.')), 400);
    }

    $ticket_id = (isset($_POST['ticket_id']) && is_string($_POST['ticket_id']) && ctype_digit($_POST['ticket_id'])) ? (int) $_POST['ticket_id'] : 0;
    $counts_out = function () use ($pick) {
        $counts = pg_check_in_counts($pick);

        return $counts + array('text' => lang(array('string' => '{var:1} of {var:2} checked in', 'vars' => array($counts['checked_in'], $counts['sold']))));
    };

    if ($request === 'counts') {
        event_check_in_json(array('ok' => true, 'counts' => $counts_out()));
    }

    if ($request === 'search') {
        $q = (isset($_POST['q']) && is_string($_POST['q'])) ? $_POST['q'] : '';

        event_check_in_json(array('ok' => true, 'results' => array_map('pg_check_in_ticket_out', pg_check_in_search($pick, $q))));
    }

    if (($request === 'scan') || ($request === 'check_in')) {
        if ($request === 'scan') {
            $hash = pg_event_ticket_code_hash((isset($_POST['code']) && is_string($_POST['code'])) ? $_POST['code'] : '');
            $find = function () use ($hash) {
                return pg_check_in_ticket_find('code_hash', $hash);
            };
        } else {
            $find = function () use ($ticket_id) {
                return pg_check_in_ticket_find('id', $ticket_id);
            };
        }

        $result = pg_check_in_run($find, $pick, $user, $event_access);
        $ticket = $result['ticket'];

        // "valid" only when this request's UPDATE marked the ticket used, so
        // two phones scanning one ticket announce it once.
        if (($result['answer'] === 'valid') && is_array($ticket)) {
            pg_announce('ticket.checked_in', pg_check_in_ticket_event($ticket));
        }

        event_check_in_json(array(
            'ok'     => true,
            'answer' => $result['answer'],
            'text'   => pg_check_in_answer_text($result['answer'], $ticket,
                ($result['answer'] === 'other') ? pg_check_in_ticket_for($ticket) : ''),
            'ticket' => is_array($ticket) && pg_check_in_ticket_matches($ticket, $pick)
                ? array('id' => (int) $ticket['id'], 'state' => (string) $ticket['state'])
                : null,
            'counts' => $counts_out(),
        ));
    }

    if ($request === 'undo') {
        $ticket = pg_check_in_ticket_find('id', $ticket_id);

        if (!pg_check_in_may_undo($user) || !is_array($ticket) || !pg_check_in_ticket_matches($ticket, $pick)) {
            event_check_in_json(array('ok' => false, 'code' => 'denied', 'message' => lang('Only a manager can undo a check-in.')), 403);
        }

        $undone = pg_check_in_undo($ticket_id);

        event_check_in_json(array(
            'ok'      => true,
            'undone'  => $undone,
            'message' => $undone ? lang('Check-in undone.') : lang('This ticket was not checked in.'),
            'counts'  => $counts_out(),
        ));
    }

    event_check_in_json(array('ok' => false, 'code' => 'request', 'message' => lang('Unknown request.')), 400);
}

$picks = pg_event_tickets_ready() ? pg_check_in_picks($user, $event_access) : array();
$selected = pg_check_in_pick_parse($_GET['pick'] ?? null);
$selected_value = ($selected !== null) ? pg_check_in_pick_value($selected) : '';

$options = '<option value="">' . h(lang('Choose…')) . '</option>';

foreach ($picks as $pick) {
    $options .= '<option value="' . h($pick['value']) . '"' . (($pick['value'] === $selected_value) ? ' selected' : '') . '>'
        . h($pick['label'] . (($pick['detail'] !== '') ? ' — ' . $pick['detail'] : '')) . '</option>';
}

$texts = array(
    'scan'        => lang('Scan with camera'),
    'stop'        => lang('Stop camera'),
    'aim'         => lang('Hold the ticket\'s QR code in front of the camera.'),
    'no_reader'   => lang('This browser cannot read QR codes with the camera. Type the code instead.'),
    'no_camera'   => lang('The camera could not be opened. Allow it for this site, or type the code.'),
    'failed'      => lang('No answer came. Check the connection; the ticket may already be checked in, so scan it again to see.'),
    'none'        => lang('No guest found.'),
    'check_in'    => lang('Check in'),
    'checked_in'  => lang('Checked in'),
    'undo'        => lang('Undo check-in'),
);

if (!pg_event_tickets_ready()) {
    $body = '<div class="pg-card p-5"><p class="pg-text-2 mb-0">' . h(lang('Tickets are not set up on this site.')) . '</p></div>';
} elseif (!$picks) {
    $body = '<div class="pg-card p-5"><h2 class="pg-title-section">' . h(lang('No tickets sold yet')) . '</h2>'
        . '<p class="pg-text-2 mb-0">' . h(lang('Event dates and ticket products appear here once you can check in at least one of their tickets.')) . '</p></div>';
} else {
    $body = '
    <div class="pg-card p-5 mb-4">
        <label class="pg-field-label" for="check_in_pick">' . h(lang('Event or ticket product')) . '</label>
        <select class="pg-input" id="check_in_pick">' . $options . '</select>
        <p class="pg-text-2 mt-3 mb-0" id="check_in_counts" aria-live="polite"></p>
    </div>
    <div id="check_in_work" class="d-none">
        <div class="pg-card p-5 mb-4">
            <video id="check_in_video" class="w-100 d-none mb-3" style="aspect-ratio:1;object-fit:cover;border-radius:var(--pg-radius-card);" muted playsinline aria-label="' . h(lang('Camera')) . '"></video>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="pg-button pg-button-fill flex-grow-1" id="check_in_camera"><span class="bi bi-qr-code-scan" aria-hidden="true"></span> <span class="pg-btn-label">' . h($texts['scan']) . '</span></button>
            </div>
            <form id="check_in_code_form" class="d-flex gap-2 align-items-end" autocomplete="off">
                <div class="flex-grow-1">
                    <label class="pg-field-label" for="check_in_code">' . h(lang('Ticket code')) . '</label>
                    <input type="text" class="pg-input" id="check_in_code" placeholder="XXXX-XXXX-XXXX" autocapitalize="characters" spellcheck="false" inputmode="text">
                </div>
                <button type="submit" class="pg-button pg-button-quiet">' . h(lang('Check')) . '</button>
            </form>
            <p class="pg-field-help mt-2 mb-0" id="check_in_hint" aria-live="polite"></p>
        </div>
        <div class="pg-card p-5 mb-4 d-none" id="check_in_answer" role="status" aria-live="assertive">
            <span class="pg-pill" id="check_in_answer_pill"></span>
            <h2 class="pg-title-section mt-2 mb-1" id="check_in_answer_title"></h2>
            <p class="pg-text-2 mb-0" id="check_in_answer_detail"></p>
            ' . (pg_check_in_may_undo($user) ? '<button type="button" class="pg-button pg-button-ghost pg-button-sm mt-3 d-none" id="check_in_undo">' . h($texts['undo']) . '</button>' : '') . '
        </div>
        <div class="pg-card p-5 mb-4">
            <label class="pg-field-label" for="check_in_search">' . h(lang('Find a guest by name')) . '</label>
            <input type="search" class="pg-input" id="check_in_search" placeholder="' . h(lang('Name or order number')) . '" autocomplete="off">
            <div id="check_in_results" class="mt-3"></div>
        </div>
    </div>';
}

echo
pg_page_shell(array(
        'title'               => lang('Ticket check-in'),
        'extra classes'       => 'pg-check-in',
        'icon'                => 'store',
        'heading'             => lang('Ticket check-in'),
        'heading_description' => lang('Pick the event\'s date or the ticket product, then scan each ticket\'s QR code or type its code.'),
        'breadcrumb'          => array(array('label' => lang('Ticket check-in'))),
    )) . '
<main id="content" class="container-fluid">
    <div id="check_in" class="mx-auto" style="max-width:36rem;"
        data-token="' . h((string) ($_SESSION['software']['token'] ?? '')) . '"
        data-texts="' . h(json_encode($texts, JSON_UNESCAPED_UNICODE)) . '">
        ' . $body . '
    </div>
</main>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/event_check_in.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/event_check_in.js') . '"></script>
' .
output_footer();
