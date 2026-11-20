<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The holder's side of the event tickets (includes/fn/event_tickets.php):
 * the tickets page tickets.php, opened by the signed link of the tickets
 * mail (?o=<order id>&t=<token>) or from the my account widget's tickets
 * section (widgets_event_tickets.php).
 *
 * The page draws every ticket of the order: its QR (pg_qr_svg(), inline
 * only), its code written out, the event's name, date and place or the
 * product's name, the guest's name the product form asked for that unit
 * (else the buyer's), "Add to calendar" for an event, and Print. A code is
 * drawn again from the row under the tickets key; when its hash is not the
 * row's, or the ticket is void, no QR is drawn.
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
 * Whether one address opened tickets pages too often (the firewall's
 * counters, which work with the firewall off): 120 in five minutes.
 *
 * @return int 0, or the window in seconds to wait
 */
function pg_event_tickets_page_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return 0;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || (function_exists('waf_ip_is_counted') && !waf_ip_is_counted($ip))) {
        return 0;
    }

    if (!waf_rate_exceeded(waf_ip_subject($ip), 'event_tickets_page', 120, 300)) {
        return 0;
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-event-tickets-page'));
    }

    return 300;
}

/**
 * The order a tickets link opens: the link's token checked first, then the
 * order read; null for a wrong token, an order that is gone or one that
 * holds no tickets.
 *
 * @param mixed $order_id ?o=
 * @param mixed $token    ?t=
 * @return array|null id, order_number, order_date, status, billing_first_name, billing_last_name
 */
function pg_event_tickets_page_find($order_id, $token)
{
    if (!is_scalar($order_id) || !preg_match('/^\d{1,10}$/', (string) $order_id) || !is_string($token)) {
        return null;
    }

    if (!pg_event_tickets_ready()) {
        return null;
    }

    $key = pg_event_tickets_key();

    if (!pg_event_tickets_link_token_valid((int) $order_id, $token, $key)) {
        return null;
    }

    $order = db_item(
        "SELECT id, order_number, order_date, status, billing_first_name, billing_last_name
        FROM orders
        WHERE (id = '" . (int) $order_id . "')
            AND EXISTS (SELECT 1 FROM event_tickets WHERE event_tickets.order_id = orders.id)");

    return is_array($order) ? $order : null;
}

/**
 * An order's tickets with their line's product, in line and unit order.
 *
 * @param int $order_id
 * @return array rows of event_tickets with product_title
 */
function pg_event_tickets_page_rows($order_id)
{
    return (array) db_items(
        "SELECT
            event_tickets.id,
            event_tickets.order_item_id,
            event_tickets.unit,
            event_tickets.product_id,
            event_tickets.calendar_event_id,
            event_tickets.recurrence_number,
            event_tickets.code_salt,
            event_tickets.code_hash,
            event_tickets.state,
            event_tickets.used_at,
            COALESCE(NULLIF(products.short_description, ''), order_items.product_name, '') AS product_title
        FROM event_tickets
        LEFT JOIN order_items ON order_items.id = event_tickets.order_item_id
        LEFT JOIN products ON products.id = event_tickets.product_id
        WHERE event_tickets.order_id = '" . (int) $order_id . "'
        ORDER BY event_tickets.order_item_id, event_tickets.unit");
}

/**
 * The variable names of a product form field that holds the guest's name,
 * when the form maps no field to the contact's first and last name: a
 * regular expression that PHP (preg_match()) and MySQL (REGEXP) both read,
 * on the name in lower case.
 */
define('PG_EVENT_TICKET_GUEST_FIELD_PATTERN', '(^|_)(full_?name|name|guest|attendee|participant|ad_?soyad|isim)($|_)');

/**
 * The guest's name a product form answer gives: the first and last name
 * fields mapped to the contact, else a field whose variable name says it
 * holds a name. Pure.
 *
 * @param array $fields rows of name (the variable name), data, contact_field
 * @return string '' when the form asked for no name
 */
function pg_event_ticket_guest_name(array $fields)
{
    $first = '';
    $last = '';
    $named = '';

    foreach ($fields as $field) {
        $data = trim((string) ($field['data'] ?? ''));

        if ($data === '') {
            continue;
        }

        $contact = (string) ($field['contact_field'] ?? '');
        $name = strtolower((string) ($field['name'] ?? ''));

        if (($contact === 'first_name') && ($first === '')) {
            $first = $data;
        } elseif (($contact === 'last_name') && ($last === '')) {
            $last = $data;
        } elseif (($named === '') && preg_match('/' . PG_EVENT_TICKET_GUEST_FIELD_PATTERN . '/', $name)) {
            $named = $data;
        }
    }

    $contact_name = trim($first . ' ' . $last);

    return ($contact_name !== '') ? $contact_name : $named;
}

/**
 * The guests' names of an order's lines, from their product forms:
 * order item id => unit => name, a unit without a form of its own reading
 * nothing (the buyer's name stands in).
 *
 * @param int[] $order_item_ids
 * @return array
 */
function pg_event_tickets_page_guests(array $order_item_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $order_item_ids))));

    if (!$ids) {
        return array();
    }

    return pg_event_tickets_guest_names_from_rows((array) db_items(
        "SELECT form_data.order_item_id, form_data.quantity_number, form_data.name, form_data.data, form_fields.contact_field
        FROM form_data
        LEFT JOIN form_fields ON form_fields.id = form_data.form_field_id
        WHERE form_data.order_item_id IN (" . implode(',', $ids) . ")
        ORDER BY form_data.id"));
}

/**
 * The guests' names in the product form answers of some order lines, by
 * pg_event_ticket_guest_name(): order item id => unit => name, a unit whose
 * form names nobody left out. Pure.
 *
 * @param array $rows form_data order_item_id, quantity_number, name, data,
 *                    with the field's contact_field, in form_data id order
 * @return array
 */
function pg_event_tickets_guest_names_from_rows(array $rows)
{
    $forms = array();

    foreach ($rows as $row) {
        $forms[(int) $row['order_item_id']][(int) $row['quantity_number']][] = $row;
    }

    $names = array();

    foreach ($forms as $item_id => $units) {
        foreach ($units as $unit => $fields) {
            $name = pg_event_ticket_guest_name($fields);

            if ($name !== '') {
                $names[$item_id][$unit] = $name;
            }
        }
    }

    return $names;
}

/**
 * One ticket as its page shows it. The code is drawn again from the row
 * and shown only when its hash is the row's; a void ticket shows none.
 * Pure.
 *
 * @param array  $row     a pg_event_tickets_page_rows() row
 * @param string $key     the tickets key
 * @param array  $context event (name, when, place, ics_url), guest, buyer,
 *                        number and count (this ticket's place in the order),
 *                        used_on (the check-in's date written out)
 * @return array state, code, show_code, title, when, place, name, number,
 *               count, used_on, ics_url
 */
function pg_event_ticket_view(array $row, $key, array $context)
{
    $state = in_array((string) ($row['state'] ?? ''), array('valid', 'used', 'void'), true) ? (string) $row['state'] : 'void';
    $code = pg_event_ticket_code((int) ($row['order_item_id'] ?? 0), (int) ($row['unit'] ?? 0), (string) ($row['code_salt'] ?? ''), (string) $key);
    $matches = ($code !== '') && hash_equals((string) ($row['code_hash'] ?? ''), pg_event_ticket_code_hash($code));
    $event = (array) ($context['event'] ?? array());
    $guest = trim((string) ($context['guest'] ?? ''));

    return array(
        'state'     => $state,
        'code'      => ($matches && ($state !== 'void')) ? $code : '',
        'show_code' => $matches && ($state !== 'void'),
        'title'     => ((string) ($event['name'] ?? '') !== '') ? (string) $event['name'] : (string) ($row['product_title'] ?? ''),
        'when'      => (string) ($event['when'] ?? ''),
        'place'     => (string) ($event['place'] ?? ''),
        'name'      => ($guest !== '') ? $guest : trim((string) ($context['buyer'] ?? '')),
        'number'    => (int) ($context['number'] ?? 1),
        'count'     => (int) ($context['count'] ?? 1),
        'used_on'   => ($state === 'used') ? (string) ($context['used_on'] ?? '') : '',
        'ics_url'   => ($state === 'void') ? '' : (string) ($event['ics_url'] ?? ''),
    );
}

/**
 * When an event's occurrence takes place, written in the site's date
 * format, which is the page's language's (numbers, so no English day or
 * month name on a page in another language): the start's date, its time
 * when shown, and the end's time (with its date when it ends on another
 * day). An all-day event gives its days only. Pure.
 *
 * @param array  $event       all_day, start_date_and_time, end_date_and_time,
 *                            show_start_time, show_end_time (get_calendar_event())
 * @param string $date_format a date() format
 * @param string $time_format a date() format
 * @return string
 */
function pg_event_ticket_when(array $event, $date_format, $time_format)
{
    $start = strtotime((string) ($event['start_date_and_time'] ?? ''));
    $end = strtotime((string) ($event['end_date_and_time'] ?? ''));

    if ($start === false) {
        return '';
    }

    $end = (($end === false) || ($end < $start)) ? $start : $end;
    $other_day = date('Y-m-d', $end) !== date('Y-m-d', $start);
    $all_day = ((string) ($event['all_day'] ?? '0') === '1');
    $show_start = !$all_day && !empty($event['show_start_time']);
    $show_end = !$all_day && !empty($event['show_end_time']);

    $when = date($date_format, $start) . ($show_start ? ' ' . date($time_format, $start) : '');

    if ($other_day || $show_end) {
        $until = trim(($other_day ? date($date_format, $end) : '') . ($show_end ? ' ' . date($time_format, $end) : ''));
        $when .= ' – ' . $until;
    }

    return $when;
}

/**
 * The event a ticket is for, as its page writes it: name, the date and
 * time range, the place; null when the event is gone. The pair is the
 * ticket's event and occurrence.
 *
 * @param int $event_id
 * @param int $recurrence_number
 * @return array|null name, when, place, event (get_calendar_event())
 */
function pg_event_tickets_page_event($event_id, $recurrence_number)
{
    if ((int) $event_id <= 0) {
        return null;
    }

    $event = get_calendar_event((int) $event_id, (int) $recurrence_number);

    if (!is_array($event)) {
        return null;
    }

    // Whether the times are shown: kept on the event, not returned above.
    $event += (array) db_item("SELECT show_start_time, show_end_time FROM calendar_events WHERE id = '" . (int) $event_id . "'");

    return array(
        'name'  => trim((string) $event['name']),
        'when'  => pg_event_ticket_when($event, pg_sw_default_date_format('date'),
            (defined('TIME_FORMAT') && (TIME_FORMAT == 'twelve_hours')) ? 'g:i A' : 'H:i'),
        'place' => trim(html_entity_decode(strip_tags((string) $event['location']), ENT_QUOTES, 'UTF-8')),
        'event' => $event,
    );
}

/**
 * The views of an order's tickets for its page: each with its event, its
 * guest and its place in the order.
 *
 * @param array  $order    pg_event_tickets_page_find()
 * @param string $base_url the page's signed address (for "Add to calendar")
 * @return array pg_event_ticket_view() rows
 */
function pg_event_tickets_page_views(array $order, $base_url)
{
    $rows = pg_event_tickets_page_rows((int) $order['id']);
    $key = pg_event_tickets_key();
    $guests = pg_event_tickets_page_guests(array_column($rows, 'order_item_id'));
    $buyer = trim((string) $order['billing_first_name'] . ' ' . (string) $order['billing_last_name']);
    $date_format = pg_sw_default_date_format('date and time');
    $events = array();
    $views = array();
    $count = count($rows);

    foreach (array_values($rows) as $i => $row) {
        $event = array();
        $event_id = (int) $row['calendar_event_id'];

        if ($event_id > 0) {
            $pair = $event_id . '-' . (int) $row['recurrence_number'];

            if (!array_key_exists($pair, $events)) {
                $events[$pair] = pg_event_tickets_page_event($event_id, (int) $row['recurrence_number']);
            }

            if ($events[$pair] !== null) {
                $event = $events[$pair];
                $event['ics_url'] = $base_url . '&ics=' . $pair;
            }
        }

        $views[] = pg_event_ticket_view($row, $key, array(
            'event'   => $event,
            'guest'   => $guests[(int) $row['order_item_id']][(int) $row['unit']] ?? '',
            'buyer'   => $buyer,
            'number'  => $i + 1,
            'count'   => $count,
            'used_on' => ((int) $row['used_at'] > 0) ? date($date_format, (int) $row['used_at']) : '',
        ));
    }

    return $views;
}

/**
 * The .ics file of one event of an order's tickets ("&ics=<event>-<date
 * number>"), '' when the order holds no valid or used ticket for it.
 *
 * @param array  $order
 * @param string $pair
 * @return string
 */
function pg_event_tickets_page_ics(array $order, $pair)
{
    if (!is_string($pair) || !preg_match('/^(\d{1,10})-(\d{1,5})$/', $pair, $match)) {
        return '';
    }

    $held = db_value(
        "SELECT COUNT(*) FROM event_tickets
        WHERE (order_id = '" . (int) $order['id'] . "')
            AND (calendar_event_id = '" . (int) $match[1] . "')
            AND (recurrence_number = '" . (int) $match[2] . "')
            AND (state <> 'void')");

    $event = ((int) $held > 0) ? pg_event_tickets_page_event((int) $match[1], (int) $match[2]) : null;

    if ($event === null) {
        return '';
    }

    // One occurrence: its own UID, its own times, no repeat rule.
    $ics = $event['event'];
    $ics['id'] = $ics['id'] . '-' . (int) $match[2];

    return pg_calendar_event_ics($ics, (string) HOSTNAME_SETTING, defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '', '');
}

/**
 * One ticket's card. Pure.
 *
 * @param array $view pg_event_ticket_view()
 * @return string HTML
 */
function pg_event_ticket_card_html(array $view)
{
    $badges = array(
        'valid' => array('teal', lang('Valid')),
        'used'  => array('gray', lang('Used')),
        'void'  => array('rose', lang('Voided')),
    );
    $badge = $badges[$view['state']] ?? $badges['void'];

    $html = '<article class="pg-card mb-7 pg-ticket' . (($view['state'] === 'void') ? ' pg-ticket-void' : '') . '">'
        . '<div class="p-7"><div class="d-flex flex-wrap gap-7 align-items-start">';

    if ($view['show_code']) {
        $html .= '<div class="pg-ticket-qr flex-shrink-0">'
            . pg_qr_svg($view['code'], 0, array('label' => lang(array('string' => 'QR code of ticket {var:1}', 'vars' => array($view['code'])))))
            . '</div>';
    }

    $html .= '<div class="flex-grow-1">'
        . '<div class="d-flex flex-wrap align-items-center gap-3 mb-3">'
        . '<span class="pg-pill pg-tone-' . $badge[0] . '">' . h($badge[1]) . '</span>'
        . '<span class="small fg-secondary">' . h(lang(array('string' => 'Ticket {var:1} of {var:2}', 'vars' => array($view['number'], $view['count'])))) . '</span>'
        . '</div>'
        . '<h2 class="h5 mb-3">' . h($view['title']) . '</h2>'
        . '<dl class="mb-0">';

    $facts = array(
        lang('Date')  => $view['when'],
        lang('Place') => $view['place'],
        lang('Name')  => $view['name'],
    );

    foreach ($facts as $label => $value) {
        if ($value !== '') {
            $html .= '<dt class="small fg-secondary">' . h($label) . '</dt><dd class="mb-3">' . h($value) . '</dd>';
        }
    }

    if ($view['show_code']) {
        $html .= '<dt class="small fg-secondary">' . h(lang('Ticket code')) . '</dt>'
            . '<dd class="mb-3 font-monospace fs-lg">' . h($view['code']) . '</dd>';
    }

    $html .= '</dl>';

    if ($view['state'] === 'void') {
        $html .= '<p class="mb-0 mt-3">' . h(lang('This ticket is void: its order was cancelled. It cannot be used at the entrance.')) . '</p>';
    } elseif (!$view['show_code']) {
        $html .= '<p class="mb-0 mt-3">' . h(lang('This ticket cannot be shown here. Please contact us.')) . '</p>';
    } elseif ($view['used_on'] !== '') {
        $html .= '<p class="mb-0 mt-3 fg-secondary">' . h(lang(array('string' => 'Checked in on {var:1}.', 'vars' => array($view['used_on'])))) . '</p>';
    }

    if ($view['ics_url'] !== '') {
        $html .= '<p class="mb-0 mt-5 pg-print-none"><a class="pg-button pg-button-sm pg-button-quiet" href="' . h($view['ics_url']) . '">'
            . '<i class="bi bi-calendar-plus me-3" aria-hidden="true"></i>' . h(lang('Add to calendar')) . '</a></p>';
    }

    return $html . '</div></div></div></article>';
}

/**
 * The tickets page's body: the order, Print, then a card per ticket. Pure.
 *
 * @param array $order pg_event_tickets_page_find()
 * @param array $views pg_event_tickets_page_views()
 * @return string HTML
 */
function pg_event_tickets_page_html(array $order, array $views)
{
    $valid = count(array_filter($views, function ($view) {
        return $view['state'] === 'valid';
    }));

    $html = '<main class="container py-7 md:py-12 pg-tickets" style="max-width: 44rem;">'
        . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-5 mb-7">'
        . '<div><h1 class="h4 mb-3"><i class="bi bi-ticket-perforated me-3" aria-hidden="true"></i>' . h(lang('Your tickets')) . '</h1>'
        . '<p class="mb-0 fg-secondary">' . h(lang(array('string' => 'Order #{var:1}', 'vars' => array((string) $order['order_number']))))
        . ' · ' . h(lang(array('string' => '{var:1} tickets', 'vars' => array(count($views))))) . '</p></div>'
        . '<button type="button" class="pg-button pg-button-quiet pg-print-none" onclick="window.print()">'
        . '<i class="bi bi-printer me-3" aria-hidden="true"></i>' . h(lang('Print')) . '</button>'
        . '</div>';

    if ($valid > 0) {
        $html .= '<p class="pg-print-none">' . h(lang('Show the QR code of each ticket at the entrance. Keep this link to yourself: whoever holds it can open your tickets.')) . '</p>';
    }

    foreach ($views as $view) {
        $html .= pg_event_ticket_card_html($view);
    }

    return $html . '</main>';
}

/**
 * The page's own rules: the QR's size, a void ticket faded, and one ticket
 * never split over two printed pages.
 *
 * @return string a <style> element
 */
function pg_event_tickets_page_style()
{
    return '<style>'
        . '.pg-ticket-qr{width:11rem;max-width:100%}.pg-ticket-qr svg{display:block;width:100%;height:auto;border-radius:.25rem}'
        . '.pg-ticket-void>div{opacity:.75}'
        . '@media print{.pg-tickets{max-width:none!important;padding:0!important}'
        . '.pg-ticket{break-inside:avoid;page-break-inside:avoid;box-shadow:none!important}}'
        . '</style>';
}
