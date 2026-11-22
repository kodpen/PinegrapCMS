<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's public page (includes/fn/walk_in_queue_tickets.php).
 *
 * ?place=<id>[&from=qr] takes a number: the place's services as large
 * choices, name, phone and e-mail optional, "Take a number". The QR poster
 * links with from=qr, the site with nothing; each way has its switch on the
 * place. Then the person's page, ?n=<id>&t=<token>, the number's signed
 * link: the number, how many are ahead, the estimate, the desk once called
 * and "Leave the queue". The page reads where the number stands every 15
 * seconds (&read=1, JSON) while it is shown. It shows numbers, never a
 * name. Every POST carries the form token. Counted on the firewall's
 * sensitive allowance (waf_sensitive_scripts()), but for the 15-second
 * read, which has a bucket of its own (waf_queue_status_read()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The 15-second read starts no session: the token is the credential, and a
// read every 15 seconds per open page must not open a session file each
// time. $_SESSION stays an array so the shared code that looks into it
// finds nothing.
if ((strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') && isset($_GET['n']) && !empty($_GET['read'])) {
    define('PG_NO_SESSION', true);
    $_SESSION = array();
}

include('init.php');

// A personal page: never kept by a cache, never indexed, and the signed
// address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$qu_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$qu_input = $qu_post ? $_POST : $_GET;
$qu_action = ($qu_post && is_string($_POST['action'] ?? null)) ? $_POST['action'] : '';
$qu_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/queue.php';
$qu_title = lang('Queue');
$qu_ready = pg_waiting_ready();
$qu_card = function ($icon, $title, $content) use ($qu_title) {
    return output_header_secure(array('title' => $qu_title)) . pg_secure_card($icon, $title, $content) . output_footer_secure();
};

// The person's page and its 15-second read.
if (isset($qu_input['n'])) {
    $qu_token = is_string($qu_input['t'] ?? null) ? $qu_input['t'] : '';
    $qu_ticket = $qu_ready ? pg_waiting_ticket_find($qu_input['n'], $qu_token) : null;
    $qu_place = $qu_ticket ? pg_waiting_place_get($qu_ticket['place_id']) : null;
    $qu_read = !$qu_post && !empty($_GET['read']);

    if (!$qu_ticket || !$qu_place) {
        set_response_code(404);

        if ($qu_read) {
            header('Content-Type: application/json; charset=utf-8');
            print json_encode(array('ok' => false));
            exit;
        }

        print $qu_card('bi-ticket', $qu_title, '<p>' . h(lang('This link cannot be used. The number may be from an earlier day.')) . '</p>');
        exit;
    }

    $qu_link = pg_waiting_ticket_link($qu_page, $qu_ticket['id'], $qu_token);

    if ($qu_action === 'leave') {
        validate_token_field();
        pg_waiting_leave($qu_ticket, $qu_place);
        go($qu_link);
    }

    $qu_status = pg_waiting_ticket_status($qu_ticket, $qu_place);

    if ($qu_read) {
        header('Content-Type: application/json; charset=utf-8');
        print json_encode(array('ok' => true) + $qu_status, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $qu_content = '<p class="pg-text-meta text-center mb-2">' . h($qu_status['place']) . '</p>'
        . '<p class="pg-display-4 fw-bold text-center mb-5">' . h($qu_status['number']) . '</p>'
        . '<div class="text-center mb-7" role="status" aria-live="polite">'
        . '<p class="h5 mb-2" data-pg-queue-field="headline">' . h($qu_status['headline']) . '</p>'
        . '<p class="mb-0" data-pg-queue-field="line">' . h($qu_status['line']) . '</p></div>';

    if ($qu_status['state'] === 'waiting') {
        $qu_content .= '<form method="post" action="' . h($qu_page) . '" class="text-center" data-pg-queue-leave="' . h(lang('Leave the queue? Your number is given up.')) . '">'
            . get_token_field() . '<input type="hidden" name="n" value="' . (int) $qu_ticket['id'] . '"><input type="hidden" name="t" value="' . h($qu_token) . '">'
            . '<button type="submit" name="action" value="leave" class="pg-button pg-button-quiet">' . h(lang('Leave the queue')) . '</button></form>';
    }

    if (!$qu_status['final']) {
        $qu_content .= '<div hidden data-pg-queue-read="' . h($qu_link . '&read=1') . '"></div>'
            . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/queue_page.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/queue_page.js') . '"></script>';
    }

    print $qu_card('bi-ticket-perforated', lang('Your number'), $qu_content);
    exit;
}

// Taking a number.
$qu_place = ($qu_ready && is_scalar($qu_input['place'] ?? null)) ? pg_waiting_place_get((int) $qu_input['place']) : null;

if (!$qu_place) {
    set_response_code(404);
    print $qu_card('bi-ticket', $qu_title, '<p>' . h(lang('This queue cannot be found.')) . '</p>');
    exit;
}

$qu_from = (($qu_input['from'] ?? '') === 'qr') ? 'qr' : '';
$qu_way = ($qu_from === 'qr') ? 'qr' : 'site';
$qu_error = '';
$qu_values = array('service' => '', 'name' => '', 'phone' => '', 'email' => '', 'party_size' => 1);

if ($qu_action === 'take') {
    validate_token_field();
    $qu_join = pg_waiting_join_input($_POST, $qu_place);

    foreach (array_keys($qu_values) as $qu_key) {
        $qu_values[$qu_key] = is_scalar($_POST[$qu_key] ?? null) ? (string) $_POST[$qu_key] : '';
    }

    if (pg_waiting_throttled()) {
        $qu_error = lang('Too many requests from your address. Please wait a moment and try again.');
    } elseif ($qu_join['error'] !== '') {
        $qu_error = $qu_join['error'];
    } else {
        $qu_taken = pg_waiting_take($qu_place, $qu_join['values'], $qu_way);

        if ($qu_taken['ok']) {
            $_SESSION['software']['queue_numbers'][$qu_place['id']] = array($qu_taken['id'], $qu_taken['token']);
            go(pg_waiting_ticket_link($qu_page, $qu_taken['id'], $qu_taken['token']));
        }

        $qu_error = ($qu_taken['error'] === 'write') ? lang('The number could not be given. Please try again.') : pg_waiting_refusal_text($qu_taken['error'], $qu_place);
    }
}

if (!pg_waiting_way_open($qu_place, $qu_way)) {
    print $qu_card('bi-clock', $qu_place['name'], '<p>' . nl2br(h(pg_waiting_refusal_text('closed', $qu_place))) . '</p>');
    exit;
}

$qu_content = ($qu_error !== '') ? '<div class="alert theme-danger" role="alert">' . h($qu_error) . '</div>' : '';

// A number this browser took here today and still waits with: its link
// first, so going back does not take a second one by mistake.
$qu_kept = $_SESSION['software']['queue_numbers'][$qu_place['id']] ?? null;
$qu_kept = (is_array($qu_kept) && (count($qu_kept) === 2)) ? array_values($qu_kept) : array(0, '');
$qu_mine = pg_waiting_ticket_find($qu_kept[0], $qu_kept[1]);

if ($qu_mine && in_array($qu_mine['state'], array('waiting', 'called'), true) && ((int) $qu_mine['day'] === pg_waiting_today())) {
    $qu_content .= '<div class="alert theme-info d-flex flex-wrap align-items-center gap-3"><span class="flex-grow-1">'
        . h(lang(array('string' => 'You have number {var:1}.', 'vars' => array(pg_waiting_number($qu_mine['letter'], $qu_mine['number']))))) . '</span>'
        . '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h(pg_waiting_ticket_link($qu_page, $qu_mine['id'], $qu_kept[1])) . '">' . h(lang('See your turn')) . '</a></div>';
}

$qu_choices = '';
$qu_single = (count($qu_place['services']) === 1);

foreach ($qu_place['services'] as $qu_service) {
    $qu_id = 'qu_service_' . h($qu_service['key']);
    $qu_choices .= '<div class="form-field form-field-card mb-3">'
        . '<input class="radio" type="radio" name="service" value="' . h($qu_service['key']) . '" id="' . $qu_id . '" required'
        . (($qu_single || ($qu_values['service'] === $qu_service['key'])) ? ' checked' : '') . '>'
        . '<label class="d-flex align-items-center gap-3" for="' . $qu_id . '"><span class="pg-icon-tile pg-icon-tile-lg" aria-hidden="true">' . h($qu_service['letter']) . '</span>'
        . '<span class="fw-bold">' . h($qu_service['name']) . '</span></label></div>';
}

$qu_field = function ($name, $type, $label, $value, $extra = '') {
    return '<div class="mb-5"><label class="pg-field-label" for="qu_' . $name . '">' . h($label) . '</label>'
        . '<input type="' . $type . '" class="pg-input" id="qu_' . $name . '" name="' . $name . '" value="' . h((string) $value) . '"' . $extra . '></div>';
};

$qu_content .= '<form method="post" action="' . h($qu_page) . '">' . get_token_field()
    . '<input type="hidden" name="place" value="' . (int) $qu_place['id'] . '"><input type="hidden" name="from" value="' . h($qu_from) . '">'
    . '<fieldset class="mb-5"><legend class="pg-field-label">' . h(lang('What do you come for?')) . '</legend>' . $qu_choices . '</fieldset>'
    . (empty($qu_place['settings']['party_size']) ? '' : $qu_field('party_size', 'number', lang('How many people come'), $qu_values['party_size'], ' min="1" max="' . PG_WAITING_PARTY_MAX . '" step="1"'))
    . $qu_field('name', 'text', lang('Name (optional)'), $qu_values['name'], ' maxlength="100" autocomplete="name"')
    . $qu_field('phone', 'tel', lang('Phone (optional)'), $qu_values['phone'], ' maxlength="40" autocomplete="tel"')
    . (empty($qu_place['settings']['notices']['email']) ? '' : $qu_field('email', 'email', lang('E-mail (optional)'), $qu_values['email'], ' maxlength="190" autocomplete="email"'))
    . ((pg_waiting_notice_channel($qu_place['settings']['notices'], '1', '') === '') ? '' : '<p class="pg-field-help mb-5">' . h(lang('Give a phone number to get a message when your turn comes.')) . '</p>')
    . '<button type="submit" name="action" value="take" class="pg-button pg-button-fill w-100">' . h(lang('Take a number')) . '</button></form>';

print $qu_card('bi-ticket-perforated', $qu_place['name'], $qu_content);
