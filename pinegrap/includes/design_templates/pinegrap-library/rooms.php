<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Public Library", its room booking requests: the request
 * for a room or a space, the request's own page with its messages, My Room
 * Bookings and the staff's list, and the e-mails of the request. Included
 * after parts.php, mail.php, catalogue.php and events.php in the same
 * scope (it uses the request pieces of events.php); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A room booking is a request, never a booked slot: a record of the Room
 * Bookings form. The template keeps no calendar or capacity, so the staff
 * answer each request, set its office-use-only Status and write under it;
 * their answers are mailed to the reader. A reader without an account gets
 * one (auto-registration), so the request's page and My Room Bookings show
 * it at once. The room starts with what a room card sends (?room=, the
 * room's title as parts.php writes it), one of the Room list's answers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$room_pages = array();

// The sample request the canvas shows. The preferred date is never a date
// in the sample: the field shows what it holds.
$sample_room = $rooms['meeting']['title'];
$sample_purpose = lang('Monthly meeting of the town\'s gardening club');
$sample_group = $group_sizes[1];
$sample_room_date = lang('Preferred date');
$sample_room_time = $time_choices[2];
$sample_room_notes = lang('We would like to use the projector for a short slide show, and make tea in the kitchenette.');

// ── Pages ───────────────────────────────────────────────────────────────

// The room booking request. What is sent opens the request's own page.
$room_pages[] = array(
    'key'              => 'room_booking',
    'name'             => lang('book-a-room'),
    'folder'           => 'public',
    'title'            => lang('Book a Room'),
    'meta_description' => lang('Ask to book a study room, meeting room, the community hall or the activity room: the library answers within two working days.'),
    'form'             => array(
        'form_name'            => lang('Room Bookings'),
        'confirmation_message' => lang('Thank you, your room booking request has reached us. We answer within two working days.'),
        'confirmation_page_id' => '{{tab:room_booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New room booking request: ^^room^^'),
        'notify_page_id'       => '{{tab:email_room_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your room booking request'),
        'confirm_page_id'      => '{{tab:email_room_booking_received}}',
    ),
    'tree'             => $request_page(lang('Book a Room'),
        lang('Choose the room, tell us what it is for, how many are coming and when suits you. The library answers within two working days; nothing is paid now.'),
        'room_booking_form',
        $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We check the room is free and answer there within two working days, or suggest another time or room.'),
            lang('Once it is booked, give your name at the desk when you arrive; we open the room for you.'),
            lang('Plans change? Write to us on the request\'s page so the room goes to someone else.'),
        ), lang('See the rooms'), '{{page:spaces}}')),
);

// One room booking request: the request, its status and the messages.
$room_pages[] = $request_view('room_booking_view', lang('room-booking-request'), lang('Room Booking Request'),
    'room_booking_detail', 'room_booking_messages', lang('There is a new message about your room booking'));

// My Room Bookings: the reader's own requests (for the staff,
// everybody's).
$room_pages[] = array(
    'key'     => 'my_room_bookings',
    'name'    => lang('my-room-bookings'),
    'folder'  => 'registration',
    'title'   => lang('My Room Bookings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Room Bookings'), lang('Your room booking requests, their status and the library\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_room_bookings_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-door-open', 'text-primary'),
                                $span(lang('Another room?'), '', lang('Text')),
                            )),
                            $para(lang('Choose one from the rooms and spaces; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the rooms'), '{{page:spaces}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$room_pages[] = $staff_list_page('staff_room_bookings', lang('all-room-booking-requests'), lang('Room Booking Requests'),
    lang('Every room booking request, newest first: check the room is free, set the status and write to the reader under each.'),
    'staff_room_bookings_list');

// The e-mails: what the library receives and the reply to the reader.
$room_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_room_booking_new',
    'name'  => lang('email-new-room-booking-request'),
    'title' => lang('New Room Booking Request E-mail'),
    'tree'  => $mail_page(array($widget('email_room_booking_admin'))),
));
$room_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_room_booking_received',
    'name'  => lang('email-room-booking-request-received'),
    'title' => lang('Room Booking Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_room_booking_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The request's room, purpose, group and time, for its e-mails.
$mail_room_booking = function () use ($mail_box, $mail_line, $span, $bind, $sample_room, $sample_purpose, $sample_group, $sample_room_date, $sample_room_time) {
    return $mail_box(array(
        $mail_line(lang('Room'), array($span($sample_room, '', lang('Room'), $bind('room')))),
        $mail_line(lang('Purpose'), array($span($sample_purpose, '', lang('Purpose'), $bind('purpose')))),
        $mail_line(lang('People'), array($span($sample_group, '', lang('People'), $bind('group_size')))),
        $mail_line(lang('Preferred date'), array($span($sample_room_date, '', lang('Preferred date'), $bind('preferred_date')))),
        $mail_line(lang('Best time'), array($span($sample_room_time, '', lang('Best time'), $bind('preferred_time')))),
    ), lang('Room Booking Request'));
};
$room_booking_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_room, $sample_group, $sample_room_time, $room_statuses) {
    return $request_row($staff, $sample_room, 'room', lang('Room'), $room_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('People'), array(
            $icon('bi-people'), $span($sample_group, '', lang('People'), $bind('group_size')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Best time'), array(
            $icon('bi-clock'), $span($sample_room_time, '', lang('Best time'), $bind('preferred_time')),
        )),
    ));
};
$room_booking_missing = lang('This room booking request could not be found, or it is not yours to see.');

$room_widgets = array(

    // The room booking request: the fields are the page's form. The room
    // starts with what a room card sends (?room=). The date cannot be
    // before today. The Status is the library's (office use only): every
    // request starts as Request received.
    'room_booking_form' => array(
        'page'   => 'room_booking',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The room')),
            $row(array(
                $col(array($field('lib-rb-room', lang('Room'), $select('lib-rb-room', 'room', lang('Choose a room…'), $room_titles, true, '', array('url_param' => 'room')))), '6'),
                $col(array($field('lib-rb-group', lang('People'), $select('lib-rb-group', 'group_size', '', $group_sizes, true, $group_sizes[0]))), '6'),
            ), '3'),
            $field('lib-rb-purpose', lang('Purpose'), $input('lib-rb-purpose', 'text', 'purpose', 'off'), 'mb-3',
                lang('A study group, a club meeting, a class, a talk or a children\'s party.')),
            $row(array(
                $col(array($field('lib-rb-date', lang('Preferred date'), $input('lib-rb-date', 'date', 'preferred_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('lib-rb-time', lang('Best time'), $select('lib-rb-time', 'preferred_time', '', $time_choices, true, $time_choices[0]))), '6'),
            ), '3'),
            $field('lib-rb-notes', lang('Notes'), $textarea('lib-rb-notes', 'notes', 3, false), 'mb-3',
                lang('How long you need it, equipment you would like to use, access needs.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('lib-rb'),
            $radios('lib-rb-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('lib-rb-status', lang('Status'), $select('lib-rb-status', 'status', '', $room_statuses, false, $room_statuses[0], array('office_use_only' => true))),
            $agree('lib-rb-consent', 'consent', lang('I agree that the library keeps these details to answer my request and book the room.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The library answers within two working days. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One room booking request; the library's answers follow as comments.
    'room_booking_detail' => $detail_widget('room_booking_view', 'room_booking', $room_booking_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Room Bookings'), 'my_room_bookings', $room_booking_missing, lang('Room booking request not found'),
            lang('Room Booking Request'), $sample_room, 'room', $room_statuses[0],
            lang('Thank you, your request has reached us. The library answers here within two working days; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Purpose'), '', lang('Purpose'), $bound($sample_purpose, 'purpose')),
                $detail(lang('People'), '', lang('People'), $bound($sample_group, 'group_size')),
                $detail(lang('Preferred date'), '', lang('Preferred date'), $bound($sample_room_date, 'preferred_date')),
                $detail(lang('Best time'), '', lang('Best time'), $bound($sample_room_time, 'preferred_time')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_room_notes,
            lang('Another day, more people or a question? Write to us below; we answer here and by e-mail.')),
    ),

    'room_booking_messages' => $request_messages('room_booking_view',
        lang('No messages yet. The library\'s answer comes here; you can write to us about the room too.')),

    'my_room_bookings_list' => $request_list('my_room_bookings', 'room_booking', 'room_booking_view', $room_booking_row, array(), '',
        lang('No room booking requests yet. Choose a room and send a request.')),

    'staff_room_bookings_list' => $request_list('staff_room_bookings', 'room_booking', 'room_booking_view', $room_booking_row,
        array('first_name', 'last_name', 'email', 'room', 'purpose', 'status'),
        lang('Search by name, e-mail, room, purpose or status...'), lang('No room booking requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_room_booking_admin' => $detail_widget('email_room_booking_new', 'room_booking', lang('The room booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new room booking request')),
            $mail_para(lang('It came from the room booking form. Check the room is free, answer under the request on its page and set the status; what you write there reaches the reader by e-mail.')),
            $mail_room_booking(),
            $mail_sender($sample_room_notes),
            $mail_button(lang('Open the request'), '{{page:room_booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_room_booking_client' => $detail_widget('email_room_booking_received', 'room_booking', lang('The room booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your room booking request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We check the room is free and answer within two working days, on the request\'s page and by e-mail.')),
            $mail_room_booking(),
            $mail_para(lang('Plans change? Write to us on the request\'s page so the room goes to someone else.')),
            $mail_button(lang('Open my request'), '{{page:room_booking_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $room_pages, 'widgets' => $room_widgets);
