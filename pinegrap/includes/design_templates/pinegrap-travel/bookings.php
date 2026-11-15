<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Travel Agency", its bookings: the booking request, the
 * request's own page with its messages, My Bookings and the staff's list;
 * the privacy page the consent boxes link, the e-mails of the booking form
 * and the new message e-mail every thread of the site sends, and the
 * widgets that draw them. Included after parts.php and mail.php in the
 * same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A booking is a request, never a reservation: a record of the Booking
 * Requests form. A traveller without an account gets one
 * (auto-registration), so the request's page and My Bookings show it at
 * once. Its Status is an office-use-only field the agency changes in the
 * control panel; the price and the places held are written under it as
 * messages (the page's comments), mailed to the traveller and to the
 * agency. The template keeps no seat count per departure and no prices per
 * date.
 *
 * The tour and the deal start with what a link sends (?tour=, ?deal=: the
 * tour's name and the deal's title, as parts.php writes them).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$booking_pages = array();

// The sample request the canvas shows. Its date is words, never a date: a
// fixed day would read as a past season a year later.
$sample_tour = $tours['cappadocia']['name'];
$sample_deal = $deal_choices[1];
$sample_room = $room_choices[0][1];
$sample_departure = lang('Departure date');
$sample_wishes = lang('It is our honeymoon: a quiet room if one is free, and we would love the balloon flight on the first morning.');
$deposit_words = $money($deposit);

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the booking page: what happens next, then the
// deposit, the deals and the travel information link.
$booking_aside = function () use ($el, $heading, $link, $check_list, $deposit_words) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('We check the places on your departure and answer there within a day, with the price.'),
        lang('Agree in a message; the status shows Confirmed once your places are held.'),
        sprintf(lang('Nothing is paid now: a deposit of %s per traveller holds your places once we confirm them.'), $deposit_words),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Good to know'), array(
            $heading('h2', lang('Good to know'), 'h5 fw-semibold mb-3'),
            $check_list(array(
                lang('Small groups: twelve travellers at most.'),
                lang('Choose a deal and we take it off the price when we confirm.'),
                lang('More than six weeks before departure only the deposit is kept if you cancel.'),
            ), 'list-unstyled small mb-3'),
            $link(lang('Read the travel information'), '{{page:travel_info}}', 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The booking request. What is sent opens the request's own page.
$booking_pages[] = array(
    'key'              => 'book',
    'name'             => lang('book'),
    'folder'           => 'public',
    'title'            => lang('Book a Tour'),
    'meta_description' => lang('Choose a tour and a departure: we check the places and answer within a day with the price. Nothing is paid now.'),
    'form'             => array(
        'form_name'            => lang('Booking Requests'),
        'confirmation_message' => lang('Thank you, your booking request has reached us. We answer within a day.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New booking request: ^^tour^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We have your booking request'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book a Tour'),
            lang('Choose a tour and a departure. We check the places and answer within a day with the price; nothing is paid now.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('book_form'))),
                        )),
                    ), '', '8'),
                    $col($booking_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One booking request: the request, its status and the messages under it.
$booking_pages[] = array(
    'key'      => 'booking_view',
    'name'     => lang('booking-request'),
    'folder'   => 'registration',
    'title'    => lang('Booking Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => false,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your booking request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('booking_detail'),
        $comments_section(lang('Messages'), 'booking_messages'),
    ), false),
);

// My Bookings: the traveller's own requests (for the staff, everybody's).
$booking_pages[] = array(
    'key'     => 'my_bookings',
    'name'    => lang('my-bookings'),
    'folder'  => 'registration',
    'title'   => lang('My Bookings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Bookings'), lang('Your booking requests, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_bookings_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-airplane', 'text-primary'),
                                $span(lang('Another journey?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for another tour or departure; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Tour'), '{{page:book}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every booking request, newest first, with a search.
$booking_pages[] = array(
    'key'     => 'staff_bookings',
    'name'    => lang('all-booking-requests'),
    'folder'  => 'private',
    'title'   => lang('Booking Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Booking Requests'), lang('Every booking request, newest first: answer with the price, set the status and write to the traveller under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_bookings_list'))),
        )),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$booking_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the agency keeps about you when you book a tour or ask for a journey, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the agency keeps about you when you book a tour or ask for a journey, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you book a tour, ask for a tailor-made journey, buy a gift card, join the newsletter or open an account, we keep your name, your contact details, your travel dates and what you tell us about your journey, to answer you, hold your places and arrange your trip.'), 'mb-3'),
                $para(lang('We pass to the hotels, guides and carriers of your tour only what they need to welcome you. We keep the rest as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('tv-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the agency receives and the reply to the traveller,
// and the new message e-mail.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_new',
    'name'  => lang('email-new-booking-request'),
    'title' => lang('New Booking Request E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_admin'))),
));
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_received',
    'name'  => lang('email-booking-request-received'),
    'title' => lang('Booking Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_client'))),
));
// A new message or reply: sent to the traveller of a booking request or a
// tailor-made enquiry, the sender of a conversation and the visitors
// watching a page, and to the agency for a new review. Its Comments widget
// draws the comment in the card of mail.php; the System region after it
// keeps the fixed comment for an e-mail the widget does not draw.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your booking request, your tailor-made enquiry, a conversation, a tour\'s reviews or a Travel Journal post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The traveller's name.
$traveller_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A booking request in a list: the tour, the status, the departure, the
// travellers and the room, linked to its page. The staff's row starts with
// the traveller's name and adds the phone; the traveller's own adds the
// messages.
$booking_row = function ($staff = false) use ($el, $span, $icon, $bind, $traveller_name, $sample_tour, $sample_departure, $sample_room, $booking_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar-event', lang('Departure'), array($span($sample_departure, '', lang('Departure'), $bind('departure_date')))),
        $item('bi-people', lang('Travellers'), array($span('2', '', lang('Travellers'), $bind('travellers')))),
        $item('bi-door-closed', lang('Room'), array($span($sample_room, '', lang('Room'), $bind('room')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Traveller'), array_merge($traveller_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_tour, 'text-body-secondary fw-normal', lang('Tour'), $bind('tour')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_tour, 'fw-semibold', lang('Tour'), $bind('tour'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Booking Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($booking_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
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

// The lines of a booking request, for its e-mails.
$mail_booking = function () use ($mail_box, $mail_line, $span, $bind, $sample_tour, $sample_departure, $sample_deal, $sample_room) {
    return $mail_box(array(
        $mail_line(lang('Tour'), array($span($sample_tour, '', lang('Tour'), $bind('tour')))),
        $mail_line(lang('Departure'), array($span($sample_departure, '', lang('Departure'), $bind('departure_date')))),
        $mail_line(lang('Travellers'), array($span('2', '', lang('Travellers'), $bind('travellers')))),
        $mail_line(lang('Room'), array($span($sample_room, '', lang('Room'), $bind('room')))),
        $mail_line(lang('Deal'), array($span($sample_deal, '', lang('Deal'), $bind('deal')))),
    ), lang('Booking Request'));
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
    ), array_merge($mail_style('border-top:1px solid #e0f2fe;padding-top:16px'), $show_when('has_new_account')));
};
// The traveller's lines of the staff e-mail.
$mail_traveller = function () use ($mail_box, $mail_line, $span, $bind, $traveller_name, $sample_wishes) {
    return $mail_box(array(
        $mail_line(lang('Traveller'), $traveller_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Wishes'), array($span($sample_wishes, '', lang('Wishes'), $bind('wishes')))),
    ), lang('Traveller'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the request page's
// widget (the traveller's own, and the staff's).
$detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:book}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of booking requests: the traveller's own (the staff see them
// all), or the staff's with a search.
$booking_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $booking_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:book}}',
        'detail_page_id'      => '{{tab:booking_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'tour', 'deal', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by traveller, e-mail, tour, deal or status...'),
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($booking_row($staff))))),
        )),
    );
};

$booking_missing = lang('This booking request could not be found, or it is not yours to see.');

$booking_widgets = array(

    // The booking request: the fields are the page's form. The tour and the
    // deal start with what a link sends (?tour=, ?deal=). The Status is the
    // agency's (office use only): every request starts as Request received.
    'book_form' => array(
        'page'   => 'book',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your tour')),
            $field('tv-bk-tour', lang('Tour'), $select('tv-bk-tour', 'tour', lang('Choose a tour…'), $booking_tour_choices, true, '', array('url_param' => 'tour')), 'mb-3',
                lang('Not sure yet? Choose "Not decided yet" and tell us what you like in your wishes.')),
            $row(array(
                $col(array($field('tv-bk-departure', lang('Departure'), $input('tv-bk-departure', 'date', 'departure_date', 'off', true, array('rules' => array('min_date' => 'today'))), 'mb-3',
                    lang('The day you would like to leave; we tell you the nearest departure.'))), '6'),
                $col(array($field('tv-bk-travellers', lang('Travellers'), $input('tv-bk-travellers', 'number', 'travellers', 'off', true, array(),
                    array($attr('min', '1'), $attr('max', '12'), $attr('step', '1'), $attr('value', '2'))))), '6'),
            ), '3'),
            $field('tv-bk-deal', lang('Deal'), $select('tv-bk-deal', 'deal', '', $deal_choices, false, $deal_choices[0], array('url_param' => 'deal'))),
            $form_step(2, lang('Your room')),
            $radios('tv-bk-room', 'room', lang('Room'), $room_choices, $room_choices[0][0]),
            $field('tv-bk-wishes', lang('Wishes'), $textarea('tv-bk-wishes', 'wishes', 3, false), 'mb-3',
                lang('An occasion, a diet, the ages of children, who you are travelling with.')),
            $form_step(3, lang('How to reach you')),
            $row(array(
                $col(array($field('tv-bk-first-name', lang('First Name'), $input('tv-bk-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('tv-bk-last-name', lang('Last Name'), $input('tv-bk-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('tv-bk-email', lang('Email'), $input('tv-bk-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('tv-bk-phone', lang('Phone'), $input('tv-bk-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('tv-bk-status', lang('Status'), $select('tv-bk-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
            $agree('tv-bk-consent', 'consent', lang('I agree that the agency keeps these details to answer my request and arrange my journey, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We answer within a day. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One booking request: the request, its status and what the traveller
    // wrote; the agency's answers (the price among them) follow as
    // comments.
    'booking_detail' => $detail_widget('booking_view', $booking_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Booking request not found'), 'h3 fw-semibold mt-3'),
                            $para($booking_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Bookings'), '{{page:my_bookings}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached us. We answer here within a day with the price; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Bookings'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_bookings}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Request Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Booking Request'), 'ptv-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_tour, 'ptv-display h3 mb-0', $bind('tour')),
                                        )),
                                        $span($booking_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Departure'), '', lang('Departure'), $bound($sample_departure, 'departure_date')),
                                        $detail(lang('Travellers'), '', lang('Travellers'), $bound('2', 'travellers')),
                                        $detail(lang('Room'), '', lang('Room'), $bound($sample_room, 'room')),
                                        $detail(lang('Deal'), '', lang('Deal'), $bound($sample_deal, 'deal')),
                                        $detail(lang('Traveller'), '', lang('Traveller'), $traveller_name(), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Wishes'), array(
                                $para(lang('Wishes'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_wishes, 'wishes')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another departure, a question about the price or something to add? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the traveller is e-mailed
    // every answer and the agency every message.
    'booking_messages' => array(
        'page'   => 'booking_view',
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
            'empty'     => lang('No messages yet. Our answer comes here; you can write to us about your journey too.'),
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
    ),

    'my_bookings_list' => $booking_list('my_bookings', false,
        lang('No booking requests yet. Choose a tour and a departure, and we answer within a day.')),

    'staff_bookings_list' => $booking_list('staff_bookings', true, lang('No booking requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_booking_admin' => $detail_widget('email_booking_new', lang('This booking request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new tour booking request')),
            $mail_para(lang('It came from the booking form. Check the places on the departure, answer under the request on its page with the price, and set the status; what you write there reaches the traveller by e-mail.')),
            $mail_booking(),
            $mail_traveller(),
            $mail_button(lang('Open the request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_booking_client' => $detail_widget('email_booking_received', lang('This booking request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We have your booking request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We check the places on your departure and answer within a day with the price, on the request\'s page and by e-mail. Nothing is paid now.')),
            $mail_booking(),
            $mail_para(lang('Another departure or a question? Write to us on the request\'s page; everything about your journey stays there.')),
            $mail_button(lang('Open my request'), '{{page:booking_view}}?r=^^reference_code^^'),
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

return array('pages' => $booking_pages, 'widgets' => $booking_widgets);
