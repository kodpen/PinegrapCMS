<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An order's tickets page, opened from the signed link of the tickets mail
 * (?o=<order id>&t=<token>) without an account, and from the tickets
 * section of the my_account widget: every ticket with its QR and code, a
 * void one marked and drawn without its QR, printable. &ics=<event>-<date
 * number> sends one event's .ics file. Opening the page changes nothing
 * (includes/fn/event_tickets_page.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets_page.php');

// A personal answer: never kept by a cache, never indexed, never framed,
// and the signed address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$tk_throttled = pg_event_tickets_page_throttled();

if ($tk_throttled) {
    header('Retry-After: ' . $tk_throttled);
    output_error(h(lang('Too many requests from your address. Please wait a moment and try again.')), 429);
}

$tk_order = pg_event_tickets_page_find($_GET['o'] ?? '', $_GET['t'] ?? '');
$tk_title = lang('Your tickets');

if ($tk_order === null) {
    set_response_code(404);
    print output_header_secure(array('title' => $tk_title))
        . pg_secure_card('bi-ticket-perforated', $tk_title, '<p>' . h(lang('This link cannot be used. Please open the link in your tickets e-mail again, or contact us.')) . '</p>')
        . output_footer_secure();
    exit;
}

$tk_link = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/tickets.php?o=' . (int) $tk_order['id'] . '&t=' . (string) $_GET['t'];

if (isset($_GET['ics'])) {
    $tk_ics = pg_event_tickets_page_ics($tk_order, $_GET['ics']);

    if ($tk_ics === '') {
        set_response_code(404);
        output_error(h(lang('This event is not on your tickets.')), 404);
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="event-' . preg_replace('/[^0-9\-]/', '', (string) $_GET['ics']) . '.ics"');
    print $tk_ics;
    exit;
}

print output_header_secure(array('title' => $tk_title))
    . pg_event_tickets_page_style()
    . pg_event_tickets_page_html($tk_order, pg_event_tickets_page_views($tk_order, $tk_link))
    . output_footer_secure();
