<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Restaurant", its table reservations: the reservation
 * request, the request's own page with its messages, My Reservations and
 * the staff's list; the chef's table on the booking widget; the privacy
 * page the consent boxes link; the e-mails of the reservation form and the
 * new message e-mail every thread of the site sends, and the widgets that
 * draw them. Included after parts.php, mail.php and menu.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...). The request
 * pieces (page shells, rows, record card, e-mail lines) stay in scope for
 * the private dining enquiry.
 *
 * A table reservation is a request, never a held table: a record of the
 * Reservations form. The template keeps no floor plan, so the front of
 * house answers each request and sets its office-use-only Status (Table
 * confirmed, Fully booked); its answers are the page's comments, mailed to
 * the guest and to the restaurant. A visitor without an account gets one
 * (auto-registration), so the request's page and My Reservations show it
 * at once.
 *
 * The menu, the event, the time and the occasion start with what a link
 * sends (?tasting=, ?event=, ?time=, ?occasion=): a tasting menu's title,
 * an event's title (the Event field is text, so a new event needs no new
 * answer), a sitting's time.
 *
 * The chef's table is one table with one sitting an evening: the booking
 * widget (a service of capacity 1) books it at a free time, beside a link
 * to the request form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$reservation_pages = array();

// The sample request the canvas shows. The date is words, never a date: a
// fixed day would read as a past week a year later.
$sample_tasting = $tastings['seasons']['title'];
$sample_date = lang('Reservation date');
$sample_time = '19:30';
$sample_guests = '2';
$sample_occasion = $occasion_choices[2];
$sample_reservation_notes = lang('It is our anniversary. One of us does not eat shellfish; a quiet table by the window would be lovely.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the opening
// hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $check_list, $opening_hours) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    $hours = array();
    foreach ($opening_hours as $h) $hours[] = $h['days'] . ': ' . $h['hours'];
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'prt-display h4 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'prt-display h4 mb-3'),
            $check_list($hours, 'list-unstyled small mb-3'),
            $link($link_text, $link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// A form page: the header, the form in a card and the side column.
$request_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($widget_key))),
                        )),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A request's own page: the request and the messages under it.
$request_view = function ($key, $name, $title, $detail_key, $messages_key, $subject) use ($page, $widget, $comments_section) {
    return array(
        'key'      => $key,
        'name'     => $name,
        'folder'   => 'registration',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                            'attachments' => false,
                            'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
                            'notify_email' => '{{site_email}}'),
        'tree'     => $page(array(
            $widget($detail_key),
            $comments_section(lang('Messages'), $messages_key),
        ), false),
    );
};

// A staff list page.
$staff_list_page = function ($key, $name, $title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'noindex' => true,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array($widget($widget_key))),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The reservation request. What is sent opens the request's own page.
$reservation_pages[] = array(
    'key'              => 'reserve',
    'name'             => lang('reserve-a-table'),
    'folder'           => 'public',
    'title'            => lang('Reserve a Table'),
    'meta_description' => lang('Ask for a table: the day, the time, how many you are and the menu. We confirm your reservation by e-mail the same day.'),
    'form'             => array(
        'form_name'            => lang('Reservations'),
        'confirmation_message' => lang('Thank you, your reservation request has reached us. We confirm your table by e-mail the same day.'),
        'confirmation_page_id' => '{{tab:reservation_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New reservation request: ^^guests^^ guests, ^^time^^'),
        'notify_page_id'       => '{{tab:email_reservation_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your reservation request'),
        'confirm_page_id'      => '{{tab:email_reservation_received}}',
    ),
    'tree'             => $request_page(lang('Reserve a Table'),
        lang('Tell us the day, the time and how many you are. We confirm your table by e-mail the same day; for more than eight guests, ask about private dining.'),
        'reservation_form',
        array_merge($request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We confirm your table there and by e-mail the same day, or offer another time if we are full.'),
            lang('Tell us about allergies and the occasion; the kitchen and the floor prepare for you.'),
            lang('Plans change? Write to us on the request\'s page so the table goes to someone else.'),
        ), lang('How to find us'), '{{page:location}}'), array(
            // One table in the kitchen with a sitting each evening: booked at
            // a free time on the booking page.
            $el('div', 'p-4 rounded-3 border mt-4', lang('The Chef\'s Table'), array(
                $heading('h2', lang('Dinner at the chef\'s table?'), 'prt-display h4 mb-2'),
                $para(lang('One table in the kitchen, one sitting an evening: pick a free evening and it is yours at once.'), 'small text-body-secondary mb-3'),
                $link(lang('Book the chef\'s table'), '{{page:book_online}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
        ))),
);

// One reservation request: the request, its status and the messages.
$reservation_pages[] = $request_view('reservation_view', lang('reservation-request'), lang('Reservation Request'),
    'reservation_detail', 'reservation_messages', lang('There is a new message about your reservation'));

// My Reservations: the guest's own requests (for the staff, everybody's).
$reservation_pages[] = array(
    'key'     => 'my_reservations',
    'name'    => lang('my-reservations'),
    'folder'  => 'registration',
    'title'   => lang('My Reservations'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Reservations'), lang('Your reservation requests, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_reservations_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another evening with us?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for a table; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Reserve a Table'), '{{page:reserve}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$reservation_pages[] = $staff_list_page('staff_reservations', lang('all-reservation-requests'), lang('Reservation Requests'),
    lang('Every reservation request, newest first: confirm the table, set the status and write to the guest under each.'),
    'staff_reservations_list');

// The chef's table: the booking widget's starter (the service, the day,
// the free time, the guest's details) beside the way to ask for an
// ordinary table instead.
$reservation_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('The Chef\'s Table'),
    'meta_description' => lang('Book the chef\'s table online: one table in the kitchen, one sitting an evening, the Seasons Menu cooked in front of you.'),
    'tree'             => $page(array(
        $page_header(lang('The Chef\'s Table'), lang('One table in the kitchen, one sitting an evening: the Seasons Menu cooked in front of you. Pick a free evening and it is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('Dining Room'), array(
                            $heading('h2', lang('Rather a table in the dining room?'), 'prt-display h4 mb-2'),
                            $para(lang('Ask for a table at lunch or dinner; we confirm it by e-mail the same day.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Reserve a Table'), '{{page:reserve}}', 'btn btn-sm btn-outline-primary px-3'),
                                $link(lang('See the tasting menus'), '{{page:tasting}}', 'btn btn-sm btn-outline-primary px-3'),
                            )),
                        )),
                        $el('div', 'p-4 rounded-3 border', lang('Opening Hours'), array(
                            $heading('h2', lang('Opening hours'), 'prt-display h4 mb-3'),
                            $check_list(array_map(function ($h) {
                                return $h['days'] . ': ' . $h['hours'];
                            }, $opening_hours), 'list-unstyled small mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('rt-chefs-table', lang('chef\'s table, kitchen table, tasting, special dinner'))),
    ), false),
);

// The privacy notice the consent boxes, the booking widget and the footer
// link to: sample text with a note to adapt it.
$reservation_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the restaurant keeps about you when you reserve a table, send an enquiry or buy a voucher, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the restaurant keeps about you when you reserve a table, send an enquiry or buy a voucher, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'prt-display h3 mb-3'),
                $para(lang('When you reserve a table, send a private dining enquiry, buy a voucher, join the newsletter or open an account, we keep your name, your contact details, your reservations and what you tell us about allergies and diets, to answer you, keep your table and cook for you safely.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('rt-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the restaurant receives and the reply to the guest,
// and the new message e-mail.
$reservation_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reservation_new',
    'name'  => lang('email-new-reservation-request'),
    'title' => lang('New Reservation Request E-mail'),
    'tree'  => $mail_page(array($widget('email_reservation_admin'))),
));
$reservation_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reservation_received',
    'name'  => lang('email-reservation-request-received'),
    'title' => lang('Reservation Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_reservation_client'))),
));
// A new message or reply: sent to the sender of a reservation or an
// enquiry, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the System
// region after it keeps the fixed comment for an e-mail the widget does not
// draw.
$reservation_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your reservation, your private dining enquiry, a conversation or a Kitchen Journal post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The sender's name.
$guest_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// When the table is: the date, then the time and the guests.
$table_when = function () use ($span, $bind, $sample_date, $sample_time, $sample_guests) {
    return array(
        $span($sample_date, '', lang('Date'), $bind('reservation_date')),
        $span(' · ', '', lang('Text')),
        $span($sample_time, '', lang('Time'), $bind('time')),
        $span(' · ', '', lang('Text')),
        $span($sample_guests, '', lang('Guests'), $bind('guests')),
        $span(' ' . lang('guests'), '', lang('Text')),
    );
};

// A request in a list, linked to its page: its title and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the guest's own adds the messages.
$request_row = function ($staff, $title_sample, $title_field, $title_name, $status, $items) use ($el, $span, $icon, $bind, $guest_name) {
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')),
        ));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($guest_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $title_name, $bind($title_field)),
        )));
    } else {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array(
            $icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments')),
        ));
        $title = $span($title_sample, 'fw-semibold', $title_name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$reservation_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $table_when, $sample_tasting, $sample_occasion, $reservation_statuses) {
    return $request_row($staff, $sample_tasting, 'tasting', lang('Menu'), $reservation_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('When'), array_merge(array($icon('bi-calendar-event')), $table_when())),
        $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-1', lang('Occasion'), array(
            $icon('bi-balloon'), $span($sample_occasion, 'pg-field-value', lang('Occasion'), $bind('occasion')),
        )),
    ));
};

// A detail of the record's card: the label, then the value. $value is
// either the value's props (its sample text and binding) or, with
// $children, the nodes it is made of.
$detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// The lines of a reservation, for its e-mails.
$mail_reservation = function () use ($mail_box, $mail_line, $span, $bind, $table_when, $sample_tasting, $sample_occasion, $seating_choices) {
    return $mail_box(array(
        $mail_line(lang('When'), $table_when()),
        $mail_line(lang('Menu'), array($span($sample_tasting, '', lang('Menu'), $bind('tasting')))),
        $mail_line(lang('Event'), array($span(lang('Event'), '', lang('Event'), $bind('event')))),
        $mail_line(lang('Occasion'), array($span($sample_occasion, '', lang('Occasion'), $bind('occasion')))),
        $mail_line(lang('Seating'), array($span($seating_choices[0], '', lang('Seating'), $bind('seating')))),
    ), lang('Reservation Request'));
};
// A reference code line under a staff e-mail.
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account a form opened for a sender who had none, in the reply e-mail.
$mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #e7e5e4;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail.
$mail_sender = function ($notes_sample) use ($mail_box, $mail_line, $span, $bind, $guest_name) {
    return $mail_box(array(
        $mail_line(lang('Name'), $guest_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Notes'), array($span($notes_sample, '', lang('Notes'), $bind('notes')))),
    ), lang('Sender'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of a request page's
// widget (the sender's own, and the staff's).
$detail_widget = function ($page_key, $form_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form_key . '}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of requests: the guest's own (the staff see them all), or the
// staff's with a search.
$request_list = function ($page_key, $form_key, $view_key, $row, $search_fields, $search_label, $empty) use ($widget_root, $el, $loop) {
    $staff = $search_fields !== array();
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $view_key . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => $search_fields,
            'search_width'   => 'half',
            'search_label'   => $search_label,
        );
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
    }
    $config['empty_message'] = $empty;
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($row($staff))))),
        )),
    );
};

// The record page of a request: not found, the notices of a request just
// sent, the toolbar, the card of details, the notes and a closing line.
$request_detail = function ($back_text, $back_key, $missing, $not_found_title, $eyebrow, $title_sample, $title_field, $status, $sent, $details, $notes_sample, $closing)
    use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $link, $span, $bind, $show_when, $attr, $edit_button, $bound, $not_found) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $not_found('bi-calendar-x', $not_found_title, $missing, $back_text, $back_key),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($sent, '', lang('Text')),
                        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                        $el('div', 'alert alert-info', lang('New Account'), array(
                            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                            $el('p', 'mb-1', lang('Sign-in Email'), array(
                                $span(lang('Email') . ': ', '', lang('Label')),
                                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                            )),
                            // The single-use set password link, for the person who sent the form
                            // while it is live; the sentence below covers a used or expired link.
                            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary', array(),
                                array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
                            $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
                        ), $show_when('has_new_account')),
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                            $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                $icon('bi-arrow-left'),
                                $span($back_text, '', lang('Link Text')),
                            ), array('href' => '{{page:' . $back_key . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Request Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($eyebrow, 'prt-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'prt-display h3 mb-0', $bind($title_field)),
                                    )),
                                    $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $details),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('Notes'), array(
                            $para(lang('Notes'), 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($notes_sample, 'notes')),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($closing, '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page.
// No Reply, Share or follow box: the sender is e-mailed every answer and
// the restaurant every message.
$request_messages = function ($page_key, $empty) use ($comment_thread) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Messages'),
            'empty'     => $empty,
            'heading'   => lang('Write to us'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to us.'),
            'submit'    => lang('Send message'),
            'watch_box' => '',
            'follow'    => '',
            'unfollow'  => '',
            'close'     => lang('Stop new messages'),
            'reopen'    => lang('Allow new messages'),
            'reply'     => false,
            'watch'     => false,
        )),
    );
};

// The sender's fields at the end of a request form.
$contact_fields = function ($p) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

$reservation_missing = lang('This reservation request could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));

$reservation_widgets = array(

    // The reservation request: the fields are the page's form. The menu,
    // the event, the time and the occasion start with what a link sends
    // (?tasting=, ?event=, ?time=, ?occasion=). The Status is the front of
    // house's (office use only): every request starts as Request received.
    'reservation_form' => array(
        'page'   => 'reserve',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your table')),
            $row(array(
                $col(array($field('rt-rs-date', lang('Date'), $input('rt-rs-date', 'date', 'reservation_date', 'off', true, $not_before_today))), '4'),
                $col(array($field('rt-rs-time', lang('Hour'), $select('rt-rs-time', 'time', lang('Choose a time…'), $time_choices, true, '', array('url_param' => 'time')))), '4'),
                $col(array($field('rt-rs-guests', lang('Guests'), $select('rt-rs-guests', 'guests', lang('How many?'), $guest_choices, true))), '4'),
            ), '3'),
            $para(lang('Lunch from Friday to Sunday, dinner from Tuesday to Saturday; we are closed on Monday.'), 'form-text mt-n2 mb-3'),
            $row(array(
                $col(array($field('rt-rs-tasting', lang('Menu'), $select('rt-rs-tasting', 'tasting', '', $tasting_choices, false, $tasting_choices[0], array('url_param' => 'tasting')))), '6'),
                $col(array($field('rt-rs-occasion', lang('Occasion'), $select('rt-rs-occasion', 'occasion', '', $occasion_choices, false, $occasion_choices[0], array('url_param' => 'occasion')))), '6'),
            ), '3'),
            $field('rt-rs-event', lang('Event'), $input('rt-rs-event', 'text', 'event', 'off', false, array('url_param' => 'event')), 'mb-3',
                lang('Coming for one of our events? Its name is filled in from the event page.')),
            $field('rt-rs-seating', lang('Seating'), $select('rt-rs-seating', 'seating', '', $seating_choices, false, $seating_choices[0])),
            $field('rt-rs-notes', lang('Allergies and wishes'), $textarea('rt-rs-notes', 'notes', 3, false), 'mb-3',
                lang('An allergy or a diet, a high chair, a wheelchair, a surprise we can help with.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('rt-rs'),
            $field('rt-rs-status', lang('Status'), $select('rt-rs-status', 'status', '', $reservation_statuses, false, $reservation_statuses[0], array('office_use_only' => true))),
            $agree('rt-rs-consent', 'consent', lang('I agree that the restaurant keeps these details, my allergies among them, to answer my request and keep my table, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('We confirm your table by e-mail the same day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One reservation request; the restaurant's answers follow as comments.
    'reservation_detail' => $detail_widget('reservation_view', 'reserve', $reservation_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Reservations'), 'my_reservations', $reservation_missing, lang('Reservation request not found'),
            lang('Reservation Request'), $sample_tasting, 'tasting', $reservation_statuses[0],
            lang('Thank you, your request has reached us. We confirm your table here and by e-mail the same day; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('When'), '', lang('When'), $table_when(), true),
                $detail(lang('Event'), 'pg-hide-if-empty', lang('Event'), $bound(lang('Event'), 'event')),
                $detail(lang('Occasion'), '', lang('Occasion'), $bound($sample_occasion, 'occasion')),
                $detail(lang('Seating'), '', lang('Seating'), $bound($seating_choices[0], 'seating')),
                $detail(lang('Name'), '', lang('Name'), $guest_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_reservation_notes,
            lang('Another time, more guests or cannot come? Write to us below; we answer here and by e-mail.')),
    ),

    'reservation_messages' => $request_messages('reservation_view',
        lang('No messages yet. Our answer comes here; you can write to us about your reservation too.')),

    'my_reservations_list' => $request_list('my_reservations', 'reserve', 'reservation_view', $reservation_row, array(), '',
        lang('No reservation requests yet. Ask for a table and it shows here.')),

    'staff_reservations_list' => $request_list('staff_reservations', 'reserve', 'reservation_view', $reservation_row,
        array('first_name', 'last_name', 'email', 'reservation_date', 'tasting', 'event', 'status'),
        lang('Search by name, e-mail, date, menu, event or status...'), lang('No reservation requests yet.')),

    // The chef's table: the booking widget's own starter layout.
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_reservation_admin' => $detail_widget('email_reservation_new', 'reserve', lang('The reservation request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new reservation request')),
            $mail_para(lang('It came from the reservation form. Confirm the table under the request on its page, or offer another time, and set the status; what you write there reaches the guest by e-mail.')),
            $mail_reservation(),
            $mail_sender($sample_reservation_notes),
            $mail_button(lang('Open the request'), '{{page:reservation_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_reservation_client' => $detail_widget('email_reservation_received', 'reserve', lang('The reservation request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your reservation request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We confirm your table the same day, on the request\'s page and by e-mail. Tell us there about any allergy we should know before you come.')),
            $mail_reservation(),
            $mail_para(lang('Plans change? Write to us on the request\'s page so the table goes to someone else.')),
            $mail_button(lang('Open my request'), '{{page:reservation_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),

    // The comment of the reply e-mail, drawn for the comment the e-mail is
    // about, flat and alone.
    'email_comment' => array(
        'page'   => 'email_reply',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $mail_comment_tree,
    ),
);

return array('pages' => $reservation_pages, 'widgets' => $reservation_widgets);
