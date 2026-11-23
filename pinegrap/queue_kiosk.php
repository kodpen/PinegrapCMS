<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A walk-in queue place's kiosk, for a tablet at the entrance
 * (includes/fn/walk_in_queue_screens.php). Opened by the place's signed
 * kiosk link, ?place=<id>&k=<token>, with no sign-in and no menu; it works
 * while the place's "Kiosk" switch is on and the queue is open. The
 * services as cards, two a row (one on a phone), each with how many wait;
 * then the priority group when the place has some, name and phone
 * optional, and "Take a number". The number is shown large with a QR code
 * of the person's page, and the kiosk goes back to the start after a
 * minute untouched. Every POST carries the form token. The link is the
 * kiosk's credential, so the per-address limit of the public page does not
 * hold it back: everyone at the entrance shares the tablet's address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$qk_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$qk_place = pg_waiting_screen_place($qk_post ? $_POST : $_GET, 'kiosk');
$qk_title = lang('Kiosk');
$qk_shell = function ($content) use ($qk_title) {
    return output_header_secure(array('title' => $qk_title))
        . '<main class="container py-7 md:py-9" style="max-width:48rem">' . $content . '</main>'
        . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/queue_kiosk.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/queue_kiosk.js') . '"></script>'
        . output_footer_secure();
};

if (!$qk_place) {
    set_response_code(404);
    print output_header_secure(array('title' => $qk_title))
        . pg_secure_card('bi-tablet', $qk_title, '<p>' . h(lang('This link cannot be used. Ask for the kiosk link on the place\'s Desks tab.')) . '</p>')
        . output_footer_secure();
    exit;
}

$qk_self = pg_waiting_screen_url($qk_place['id'], 'kiosk');
$qk_idle = ' data-pg-kiosk-start="' . h($qk_self) . '" data-pg-kiosk-idle="' . PG_WAITING_KIOSK_IDLE_SECONDS . '"';
$qk_head = '<h1 class="text-center mb-7" style="font-size:clamp(1.75rem,4vw,2.5rem)">' . h($qk_place['name']) . '</h1>';

// The number just taken, kept in the session for one showing: a reload of
// the page after the POST never takes a second number.
$qk_shown = $_SESSION['software']['queue_kiosk'][$qk_place['id']] ?? null;

if (!$qk_post && !empty($_GET['done']) && is_array($qk_shown) && (count($qk_shown) === 2)) {
    unset($_SESSION['software']['queue_kiosk'][$qk_place['id']]);
    $qk_ticket = pg_waiting_ticket_find($qk_shown[0], $qk_shown[1]);

    if ($qk_ticket) {
        $qk_status = pg_waiting_ticket_status($qk_ticket, $qk_place);
        $qk_link = pg_waiting_ticket_link(pg_waiting_page_url(), $qk_ticket['id'], $qk_shown[1]);

        print $qk_shell('<div class="text-center"' . $qk_idle . '>' . $qk_head
            . '<p class="pg-text-2 mb-2">' . h(lang('Your number')) . '</p>'
            . '<p class="pg-display-4 fw-bold mb-5">' . h($qk_status['number']) . '</p>'
            . '<p class="h5 mb-2">' . h($qk_status['headline']) . '</p>'
            . '<p class="mb-7">' . h($qk_status['line']) . '</p>'
            . '<div class="mx-auto mb-3" style="max-width:12rem">' . pg_qr_svg($qk_link, 0, array('level' => 'M', 'label' => lang('QR code of your number\'s page'))) . '</div>'
            . '<p class="pg-field-help mb-7">' . h(lang('Scan to follow your turn on your phone.')) . '</p>'
            . '<a class="pg-button pg-button-fill" href="' . h($qk_self) . '">' . h(lang('Done')) . '</a></div>');
        exit;
    }
}

$qk_error = '';
$qk_values = array('service' => '', 'name' => '', 'phone' => '', 'email' => '', 'party_size' => 1, 'priority' => '');

if ($qk_post && (($_POST['action'] ?? '') === 'take')) {
    validate_token_field();
    // The third argument asks for the priority group the kiosk offers.
    $qk_join = pg_waiting_join_input($_POST, $qk_place, true);

    foreach (array_keys($qk_values) as $qk_key) {
        $qk_values[$qk_key] = is_scalar($_POST[$qk_key] ?? null) ? (string) $_POST[$qk_key] : '';
    }

    if ($qk_join['error'] !== '') {
        $qk_error = $qk_join['error'];
    } else {
        $qk_taken = pg_waiting_take($qk_place, $qk_join['values'], 'kiosk');

        if ($qk_taken['ok']) {
            $_SESSION['software']['queue_kiosk'][$qk_place['id']] = array($qk_taken['id'], $qk_taken['token']);
            go($qk_self . '&done=1');
        }

        $qk_error = ($qk_taken['error'] === 'write') ? lang('The number could not be given. Please try again.') : pg_waiting_refusal_text($qk_taken['error'], $qk_place);
    }
}

if (!pg_waiting_way_open($qk_place, 'kiosk')) {
    print $qk_shell('<div class="text-center"' . $qk_idle . '>' . $qk_head
        . '<p class="h5">' . nl2br(h(pg_waiting_refusal_text('closed', $qk_place))) . '</p></div>');
    exit;
}

// Each service's card says how many wait for it, with the estimate once
// there is one.
$qk_waiting = pg_waiting_place_waiting($qk_place['id'], pg_waiting_today());
$qk_counts = pg_waiting_count_by_service($qk_waiting);
$qk_single = (count($qk_place['services']) === 1);
$qk_cards = '';

foreach ($qk_place['services'] as $qk_service) {
    $qk_count = $qk_counts[$qk_service['key']] ?? 0;
    $qk_minutes = $qk_count ? pg_waiting_estimate_minutes(pg_waiting_estimate($qk_place, $qk_service['key'], $qk_count, time())) : null;
    $qk_id = 'qk_service_' . h($qk_service['key']);
    $qk_cards .= '<div class="col-12 sm:col-6"><div class="form-field form-field-card h-100">'
        . '<input class="radio" type="radio" name="service" value="' . h($qk_service['key']) . '" id="' . $qk_id . '" aria-describedby="' . $qk_id . '_line" required'
        . (($qk_single || ($qk_values['service'] === $qk_service['key'])) ? ' checked' : '') . '>'
        . '<label class="pg-start-head" for="' . $qk_id . '"><span class="pg-icon-tile pg-icon-tile-lg" aria-hidden="true">' . h($qk_service['letter']) . '</span>'
        . '<span class="fw-bold">' . h($qk_service['name']) . '</span></label>'
        . '<div class="form-text mt-1" id="' . $qk_id . '_line">' . h(pg_waiting_kiosk_line($qk_count, $qk_minutes)) . '</div></div></div>';
}

$qk_field = function ($name, $type, $label, $value, $extra = '') {
    return '<div class="mb-5"><label class="pg-field-label" for="qk_' . $name . '">' . h($label) . '</label>'
        . '<input type="' . $type . '" class="pg-input" id="qk_' . $name . '" name="' . $name . '" value="' . h((string) $value) . '"' . $extra . '></div>';
};

$qk_priority = '';

if (!empty($qk_place['settings']['priority'])) {
    $qk_priority = '<option value="">' . h(lang('No priority group')) . '</option>';

    foreach ($qk_place['settings']['priority'] as $qk_group) {
        $qk_priority .= '<option value="' . h($qk_group['key']) . '"' . (($qk_values['priority'] === $qk_group['key']) ? ' selected' : '') . '>' . h($qk_group['name']) . '</option>';
    }

    $qk_priority = '<div class="mb-5"><label class="pg-field-label" for="qk_priority">' . h(lang('Priority')) . '</label>'
        . '<select class="pg-input" id="qk_priority" name="priority" aria-describedby="qk_priority_help">' . $qk_priority . '</select>'
        . '<p class="pg-field-help mt-1 mb-0" id="qk_priority_help">' . h(lang('Called first. The desk sees the reason.')) . '</p></div>';
}

print $qk_shell('<div' . $qk_idle . '>' . $qk_head
    . (($qk_error !== '') ? '<div class="alert theme-danger" role="alert">' . h($qk_error) . '</div>' : '')
    . '<form method="post" action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/queue_kiosk.php') . '">' . get_token_field()
    . '<input type="hidden" name="place" value="' . (int) $qk_place['id'] . '"><input type="hidden" name="k" value="' . h(pg_waiting_screen_token($qk_place['id'], 'kiosk', pg_booking_key())) . '">'
    . '<fieldset class="mb-7"><legend class="pg-field-label">' . h(lang('What do you come for?')) . '</legend><div class="row g-3">' . $qk_cards . '</div></fieldset>'
    . (empty($qk_place['settings']['party_size']) ? '' : $qk_field('party_size', 'number', lang('How many people come'), $qk_values['party_size'], ' min="1" max="' . PG_WAITING_PARTY_MAX . '" step="1"'))
    . $qk_priority
    . $qk_field('name', 'text', lang('Name (optional)'), $qk_values['name'], ' maxlength="100" autocomplete="off"')
    . $qk_field('phone', 'tel', lang('Phone (optional)'), $qk_values['phone'], ' maxlength="40" autocomplete="off"')
    . (empty($qk_place['settings']['notices']['email']) ? '' : $qk_field('email', 'email', lang('E-mail (optional)'), $qk_values['email'], ' maxlength="190" autocomplete="off"'))
    . ((pg_waiting_notice_channel($qk_place['settings']['notices'], '1', '') === '') ? '' : '<p class="pg-field-help mb-5">' . h(lang('Give a phone number to get a message when your turn comes.')) . '</p>')
    . '<button type="submit" name="action" value="take" class="pg-button pg-button-fill w-100">' . h(lang('Take a number')) . '</button></form></div>');
