<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Rental", its bookings: the booking request, the
 * request's own page with its messages, My Bookings and the staff's list;
 * the privacy page the consent boxes link, the e-mails of the booking form
 * and the new message e-mail every thread of the site sends, and the
 * widgets that draw them. Included after parts.php and mail.php in the
 * same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A booking is a request, never a reservation: a record of the Booking
 * Requests form. A renter without an account gets one (auto-registration),
 * so the request's page and My Bookings show it at once. Its Status is an
 * office-use-only field the desk changes in the control panel; the price
 * of the rental and the car held are written under it as messages (the
 * page's comments), mailed to the renter and to the company. The template
 * keeps no fleet stock per date and calculates no price.
 *
 * The car, the class, the pick-up desk and the deal start with what a
 * link sends (?car=, ?class=, ?location=, ?deal=: the car's title, the
 * class's, the desk's and the deal's, as parts.php writes them).
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

// The sample request the canvas shows. Its dates are words, never a
// date: a fixed day would read as a past season a year later.
$sample_car = $car_title('lumo-five');
$sample_class = $classes['compact']['title'];
$sample_pickup = $locations['airport']['title'];
$sample_return = $locations['city']['title'];
$sample_deal = $deal_choices[0];
$sample_extras = $extras['child']['title'] . ', ' . $extras['driver']['title'];
$sample_pickup_date = lang('Pick-up day');
$sample_return_date = lang('Return day');
$sample_notes = lang('We land late in the evening with two children; please have the child seat fitted, we will call from the arrivals hall.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the booking page: what happens next, then the
// rental terms a renter asks about first, and the link to all of them.
$booking_aside = function () use ($el, $heading, $link, $check_list, $terms) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('The desk answers there within a few hours with the price of the rental and the car held for you.'),
        lang('Confirm it in a message; the status shows Confirmed once the car is yours.'),
        lang('Nothing is paid now: the rental is paid at the desk when you collect the car.'),
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
            $check_list(array($terms['age']['text'], $terms['deposit']['text'], $terms['cancel']['text']), 'list-unstyled small mb-3'),
            $link(lang('Read the rental terms'), '{{page:terms}}', 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The booking request. What is sent opens the request's own page.
$booking_pages[] = array(
    'key'              => 'book',
    'name'             => lang('book-a-car'),
    'folder'           => 'public',
    'title'            => lang('Book a Car'),
    'meta_description' => lang('Tell us where and when you need a car and the class you like: the desk answers within a few hours with the price and the car held for you.'),
    'form'             => array(
        'form_name'            => lang('Booking Requests'),
        'confirmation_message' => lang('Thank you, your booking request has reached us. The desk answers within a few hours.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New booking request: ^^car_class^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your booking request'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book a Car'),
            lang('Tell us where and when you need a car and the class you like. The desk answers within a few hours with the price and the car held for you; nothing is paid now.')),
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

// My Bookings: the renter's own requests (for the staff, everybody's).
$booking_pages[] = array(
    'key'     => 'my_bookings',
    'name'    => lang('my-bookings'),
    'folder'  => 'registration',
    'title'   => lang('My Bookings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Bookings'), lang('Your car booking requests, their status and the desk\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_bookings_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-key', 'text-primary'),
                                $span(lang('Another trip?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for other dates, another desk or another class; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Car'), '{{page:book}}', 'btn btn-sm btn-primary rounded-pill px-3'),
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
        $page_header(lang('Booking Requests'), lang('Every car booking request, newest first: answer with the price, set the status and write to the renter under each.')),
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
    'meta_description' => lang('What the rental company keeps about you when you book a car or ask about a business rental, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the rental company keeps about you when you book a car or ask about a business rental, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you book a car, ask about a business rental, join the newsletter or open an account, we keep your name, your contact details, your rental dates and desks and what you tell us about your trip, to answer you, hold a car for you and hand it over.'), 'mb-3'),
                $para(lang('At the desk we see your driving licence and hold a deposit on your card for the rental contract; the site never asks for them. We keep your data as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the desk receives and the reply to the renter, and the
// new message e-mail.
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
// A new message or reply: sent to the renter of a booking request or a
// business enquiry, the sender of a conversation and the visitors watching
// a page. Its Comments widget draws the comment in the card of mail.php;
// the System region after it keeps the fixed comment for an e-mail the
// widget does not draw.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your booking request, your business enquiry, a conversation or a Road Trip Journal post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The renter's name.
$renter_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The rental's dates: pick-up, an arrow, return.
$rental_dates = function () use ($span, $bind, $sample_pickup_date, $sample_return_date) {
    return array(
        $span($sample_pickup_date, '', lang('Pick-up day'), $bind('pickup_date')),
        $span(' → ', '', lang('Text')),
        $span($sample_return_date, '', lang('Return day'), $bind('return_date')),
    );
};
// A desk and the time at it: "Airport Desk, 10:00".
$desk_time = function ($desk_field, $time_field, $sample_desk, $sample_time) use ($span, $bind) {
    return array(
        $span($sample_desk, '', lang('Rental desk'), $bind($desk_field)),
        $span(', ', '', lang('Text')),
        $span($sample_time, '', lang('Time of day'), $bind($time_field)),
    );
};

// A booking request in a list: the class, the status, the dates, the
// pick-up desk and the car asked for, linked to its page. The staff's row
// starts with the renter's name and adds the phone; the renter's own adds
// the messages.
$booking_row = function ($staff = false) use ($el, $span, $icon, $bind, $renter_name, $rental_dates, $sample_class, $sample_pickup, $sample_car, $booking_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar-range', lang('Rental dates'), $rental_dates()),
        $item('bi-geo-alt', lang('Pick-up desk'), array($span($sample_pickup, '', lang('Pick-up desk'), $bind('pickup_location')))),
        $item('bi-car-front', lang('Car asked for'), array($span($sample_car, 'pg-hide-if-empty', lang('Car asked for'), $bind('car')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Renter'), array_merge($renter_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_class, 'text-body-secondary fw-normal', lang('Car class'), $bind('car_class')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_class, 'fw-semibold', lang('Car class'), $bind('car_class'));
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
$mail_booking = function () use ($mail_box, $mail_line, $span, $bind, $rental_dates, $desk_time, $sample_class, $sample_car, $sample_pickup, $sample_return,
        $sample_deal, $sample_extras, $time_choices, $age_choices) {
    return $mail_box(array(
        $mail_line(lang('Car class'), array($span($sample_class, '', lang('Car class'), $bind('car_class')))),
        $mail_line(lang('Car asked for'), array($span($sample_car, '', lang('Car asked for'), $bind('car')))),
        $mail_line(lang('Rental dates'), $rental_dates()),
        $mail_line(lang('Pick-up desk and time'), $desk_time('pickup_location', 'pickup_time', $sample_pickup, $time_choices[3])),
        $mail_line(lang('Return desk and time'), $desk_time('return_location', 'return_time', $sample_return, $time_choices[3])),
        $mail_line(lang('Extras'), array($span($sample_extras, '', lang('Extras'), $bind('extras')))),
        $mail_line(lang('Deal chosen'), array($span($sample_deal, '', lang('Deal chosen'), $bind('deal')))),
        $mail_line(lang('Driver age'), array($span($age_choices[1], '', lang('Driver age'), $bind('driver_age')))),
        $mail_line(lang('Flight number'), array($span('TK 2318', '', lang('Flight number'), $bind('flight')))),
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
    ), array_merge($mail_style('border-top:1px solid #e7e5e4;padding-top:16px'), $show_when('has_new_account')));
};
// The renter's lines of the staff e-mail.
$mail_renter = function () use ($mail_box, $mail_line, $span, $bind, $renter_name, $sample_notes) {
    return $mail_box(array(
        $mail_line(lang('Renter'), $renter_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Notes'), array($span($sample_notes, '', lang('Notes'), $bind('notes')))),
    ), lang('Renter'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the request page's
// widget (the renter's own, and the staff's).
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
// A list of booking requests: the renter's own (the staff see them all),
// or the staff's with a search.
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
            'search_fields'  => array('first_name', 'last_name', 'email', 'car_class', 'car', 'pickup_location', 'deal', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by renter, e-mail, class, car, desk, deal or status...'),
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

// A group of boxes that may all be ticked, under its legend: the legend
// names the field (the extras of a rental). Each answer is its own words.
$checkboxes = function ($id, $name, $legend, $options) use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $children[] = $el('div', 'form-check', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => array(
                $attr('type', 'checkbox'), $attr('name', $name), $attr('value', $o),
            ))),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o, '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', 'mb-3', $legend, $children);
};

$booking_missing = lang('This booking request could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));
$age_radios = array();
foreach ($age_choices as $a) $age_radios[] = array($a, $a);
$extra_choices = array();
foreach (array_keys($extras) as $key) $extra_choices[] = $extras[$key]['title'] . ' (' . $extra_price($key) . ')';

$booking_widgets = array(

    // The booking request: the fields are the page's form. The pick-up
    // desk, the class, the car and the deal start with what a link sends
    // (?location=, ?class=, ?car=, ?deal=). The Status is the desk's
    // (office use only): every request starts as Request received.
    'book_form' => array(
        'page'   => 'book',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Where and when')),
            $row(array(
                $col(array($field('cr-bk-pickup', lang('Pick-up desk'), $select('cr-bk-pickup', 'pickup_location', lang('Choose a desk…'), $location_titles, true, '', array('url_param' => 'location')))), '6'),
                $col(array($field('cr-bk-return', lang('Return desk'), $select('cr-bk-return', 'return_location', lang('Same as pick-up'), $location_titles, false), 'mb-3',
                    lang('Any desk takes the car back at no charge.'))), '6'),
                $col(array($field('cr-bk-pickup-date', lang('Pick-up day'), $input('cr-bk-pickup-date', 'date', 'pickup_date', 'off', true, $not_before_today))), '6', '3'),
                $col(array($field('cr-bk-pickup-time', lang('Pick-up time'), $select('cr-bk-pickup-time', 'pickup_time', '', $time_choices, true, $time_choices[3]))), '6', '3'),
                $col(array($field('cr-bk-return-date', lang('Return day'), $input('cr-bk-return-date', 'date', 'return_date', 'off', true, $not_before_today))), '6', '3'),
                $col(array($field('cr-bk-return-time', lang('Return time'), $select('cr-bk-return-time', 'return_time', '', $time_choices, true, $time_choices[3]))), '6', '3'),
            ), '3'),
            $field('cr-bk-flight', lang('Flight number'), $input('cr-bk-flight', 'text', 'flight', 'off', false), 'mb-3',
                lang('For a pick-up at the airport: we follow your flight if it is late.')),
            $form_step(2, lang('Your car')),
            $row(array(
                $col(array($field('cr-bk-class', lang('Car class'), $select('cr-bk-class', 'car_class', lang('Choose a car class…'), $class_titles, true, '', array('url_param' => 'class')))), '6'),
                $col(array($field('cr-bk-car', lang('Car asked for'), $select('cr-bk-car', 'car', lang('Any car of the class'), $car_titles, false, '', array('url_param' => 'car')))), '6'),
            ), '3'),
            $para(lang('We promise the class: you get the car you asked for or a similar one with the same seats, gearbox and boot.'), 'small text-body-secondary mb-3'),
            $field('cr-bk-deal', lang('Deal chosen'), $select('cr-bk-deal', 'deal', '', $deal_choices, false, $deal_choices[0], array('url_param' => 'deal'))),
            $checkboxes('cr-bk-extras', 'extras', lang('Extras'), $extra_choices),
            $radios('cr-bk-age', 'driver_age', lang('Driver age'), $age_radios, $age_choices[1]),
            $field('cr-bk-notes', lang('Notes'), $textarea('cr-bk-notes', 'notes', 3, false), 'mb-3',
                lang('A late arrival, a child\'s age for the seat, a ferry or a border on your route.')),
            $form_step(3, lang('How to reach you')),
            $row(array(
                $col(array($field('cr-bk-first-name', lang('First Name'), $input('cr-bk-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('cr-bk-last-name', lang('Last Name'), $input('cr-bk-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('cr-bk-email', lang('Email'), $input('cr-bk-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('cr-bk-phone', lang('Phone'), $input('cr-bk-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('cr-bk-status', lang('Status'), $select('cr-bk-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
            $agree('cr-bk-consent', 'consent', lang('I agree that the rental company keeps these details to answer my request and prepare my rental, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The desk answers within a few hours. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One booking request: the request, its status and what the renter
    // wrote; the desk's answers (the price among them) follow as comments.
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
                                $span(lang('Thank you, your request has reached us. The desk answers here within a few hours with the price; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Booking Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Booking Request'), 'pcr-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_class, 'pcr-display h3 mb-0', $bind('car_class')),
                                        )),
                                        $span($booking_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Car asked for'), 'pg-hide-if-empty', lang('Car asked for'), $bound($sample_car, 'car')),
                                        $detail(lang('Rental dates'), '', lang('Rental dates'), $rental_dates(), true),
                                        $detail(lang('Pick-up desk and time'), '', lang('Pick-up desk and time'), $desk_time('pickup_location', 'pickup_time', $sample_pickup, $time_choices[3]), true),
                                        $detail(lang('Return desk and time'), '', lang('Return desk and time'), $desk_time('return_location', 'return_time', $sample_return, $time_choices[3]), true),
                                        $detail(lang('Flight number'), 'pg-hide-if-empty', lang('Flight number'), $bound('TK 2318', 'flight')),
                                        $detail(lang('Extras'), 'pg-hide-if-empty', lang('Extras'), $bound($sample_extras, 'extras')),
                                        $detail(lang('Deal chosen'), '', lang('Deal chosen'), $bound($sample_deal, 'deal')),
                                        $detail(lang('Driver age'), '', lang('Driver age'), $bound($age_choices[1], 'driver_age')),
                                        $detail(lang('Renter'), '', lang('Renter'), $renter_name(), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
                                $para(lang('Notes'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_notes, 'notes')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Other dates, another class or a question about the price? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the renter is e-mailed every
    // answer and the desk every message.
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
            'empty'     => lang('No messages yet. The desk\'s answer comes here; you can write to us about your rental too.'),
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
        lang('No booking requests yet. Tell us where and when you need a car.')),

    'staff_bookings_list' => $booking_list('staff_bookings', true, lang('No booking requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_booking_admin' => $detail_widget('email_booking_new', lang('The booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new booking request')),
            $mail_para(lang('It came from the booking form. Answer under the request on its page with the price of the rental and the car you hold, and set the status; what you write there reaches the renter by e-mail.')),
            $mail_booking(),
            $mail_renter(),
            $mail_button(lang('Open the request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_booking_client' => $detail_widget('email_booking_received', lang('The booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your booking request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The desk looks at your dates and answers within a few hours with the price of the rental and the car held for you, on the request\'s page and by e-mail. Nothing is paid now.')),
            $mail_booking(),
            $mail_para(lang('Bring your driving licence and a credit card in the main driver\'s name to the desk: the deposit is held on it at pick-up.')),
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
