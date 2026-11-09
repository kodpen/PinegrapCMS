<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Gym", its requests: the class booking request, the
 * request's own page with its messages, My Class Bookings and the staff's
 * list; the join request, its page and the staff's list; the privacy page
 * the consent boxes link; the e-mails of both forms and the new message
 * e-mail every thread of the site sends, and the widgets that draw them.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A class booking is a request, never a held place: a record of the Class
 * Bookings form. The template keeps no class capacity, so the front desk
 * answers each request and sets its office-use-only Status (Place
 * confirmed, Class is full); its answers are the page's comments, mailed
 * to the member and to the gym. A visitor without an account gets one
 * (auto-registration), so the request's page and My Class Bookings show it
 * at once.
 *
 * The class, the day and the time start with what a link sends (?class=,
 * ?day=, ?time=: the timetable's words, as parts.php writes them); the
 * join request's plan with ?plan= (a plan's title or the free trial pass).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$class_pages = array();

// The sample requests the canvas shows. The date is words, never a date:
// a fixed day would read as a past week a year later.
$sample_class = $classes['barbell']['name'];
$sample_day = $days['mon']['title'];
$sample_time = '18:30';
$sample_date = lang('Class date');
$sample_class_notes = lang('My first class with a barbell: I had a sore knee last year, so I would like a coach to check my squat.');
$sample_join_plan = $join_choices[0];
$sample_goal = $goal_choices[0];
$sample_join_notes = lang('I would like to try a morning class before I choose a plan, and to know whether there is parking near the door.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the gym's
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
            $heading('h2', lang('What happens next'), 'pgy-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'pgy-display h5 mb-3'),
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
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array($widget($widget_key))),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The class booking request. What is sent opens the request's own page.
$class_pages[] = array(
    'key'              => 'class_booking',
    'name'             => lang('book-a-class'),
    'folder'           => 'public',
    'title'            => lang('Book a Class'),
    'meta_description' => lang('Choose a class, a day and a time from the timetable: the front desk confirms your place within a few hours.'),
    'form'             => array(
        'form_name'            => lang('Class Bookings'),
        'confirmation_message' => lang('Thank you, your class booking request has reached us. The front desk confirms your place within a few hours.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New class booking request: ^^class^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your class booking request'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $request_page(lang('Book a Class'),
        lang('Choose a class, a day and a time from the timetable. The front desk confirms your place within a few hours; your first class is free.'),
        'class_booking_form',
        array_merge($request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The front desk confirms your place there within a few hours, or offers another time if the class is full.'),
            lang('Come ten minutes early and tell the coach if anything hurts.'),
            lang('Cannot come? Write to us on the request\'s page so the place goes to someone else.'),
        ), lang('See the whole timetable'), '{{page:timetable}}'), array(
            // One to one instead of a group: the personal training page books
            // a coach at a free time.
            $el('div', 'p-4 rounded-3 border mt-4', lang('Personal Training'), array(
                $heading('h2', lang('Rather train one to one?'), 'pgy-display h5 mb-2'),
                $para(lang('Book a personal training session with a coach at one of the free times.'), 'small text-body-secondary mb-3'),
                $link(lang('Book a session'), '{{page:book_online}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
        ))),
);

// One class booking request: the request, its status and the messages.
$class_pages[] = $request_view('booking_view', lang('class-booking-request'), lang('Class Booking Request'),
    'booking_detail', 'booking_messages', lang('There is a new message about your class booking'));

// My Class Bookings: the member's own requests (for the staff, everybody's).
$class_pages[] = array(
    'key'     => 'my_bookings',
    'name'    => lang('my-class-bookings'),
    'folder'  => 'registration',
    'title'   => lang('My Class Bookings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Class Bookings'), lang('Your class booking requests, their status and the front desk\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_bookings_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another class?'), '', lang('Text')),
                            )),
                            $para(lang('Pick one from the timetable; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the Timetable'), '{{page:timetable}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$class_pages[] = $staff_list_page('staff_bookings', lang('all-class-booking-requests'), lang('Class Booking Requests'),
    lang('Every class booking request, newest first: confirm the place, set the status and write to the member under each.'),
    'staff_bookings_list');

// The join request: a plan or the free trial pass, the member's goal and
// experience. What is sent opens the request's own page.
$class_pages[] = array(
    'key'              => 'join',
    'name'             => lang('join'),
    'folder'           => 'public',
    'title'            => lang('Join the Gym'),
    'meta_description' => lang('Ask for a free trial pass or a membership plan: the front desk books your first visit and a welcome session with a coach.'),
    'form'             => array(
        'form_name'            => lang('Join Requests'),
        'confirmation_message' => lang('Thank you, your request to join has reached us. The front desk writes to you within a day to book your first visit.'),
        'confirmation_page_id' => '{{tab:join_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New join request: ^^plan^^'),
        'notify_page_id'       => '{{tab:email_join_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your request to join'),
        'confirm_page_id'      => '{{tab:email_join_received}}',
    ),
    'tree'             => $request_page(lang('Join the Gym'),
        lang('Ask for a free trial pass or the plan you like. The front desk books your first visit and a welcome session with a coach; nothing is paid now.'),
        'join_form',
        $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The front desk writes to you there within a day to book your first visit.'),
            lang('A coach shows you round and plans your first weeks with you.'),
            lang('Nothing is paid now: the membership starts on the day you choose.'),
        ), lang('Compare the plans'), '{{page:plans}}')),
);

// One join request: the request, its status and the messages.
$class_pages[] = $request_view('join_view', lang('join-request'), lang('Join Request'),
    'join_detail', 'join_messages', lang('There is a new message about your request to join'));

$class_pages[] = $staff_list_page('staff_joins', lang('all-join-requests'), lang('Join Requests'),
    lang('Every join request, newest first: book the first visit, set the status and write to the visitor under each.'),
    'staff_joins_list');

// The privacy notice the consent boxes, the booking widget and the footer
// link to: sample text with a note to adapt it.
$class_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the gym keeps about you when you book a class, ask to join or train with a coach, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the gym keeps about you when you book a class, ask to join or train with a coach, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pgy-display h4 mb-3'),
                $para(lang('When you book a class, ask to join, book a session with a trainer, join the newsletter or open an account, we keep your name, your contact details, the classes you book and what you tell us about your training and your health, to answer you, keep your place and coach you safely.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('gy-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the front desk receives and the reply to the sender of
// each form, and the new message e-mail.
$class_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_new',
    'name'  => lang('email-new-class-booking-request'),
    'title' => lang('New Class Booking Request E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_admin'))),
));
$class_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_received',
    'name'  => lang('email-class-booking-request-received'),
    'title' => lang('Class Booking Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_client'))),
));
$class_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_join_new',
    'name'  => lang('email-new-join-request'),
    'title' => lang('New Join Request E-mail'),
    'tree'  => $mail_page(array($widget('email_join_admin'))),
));
$class_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_join_received',
    'name'  => lang('email-join-request-received'),
    'title' => lang('Join Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_join_client'))),
));
// A new message or reply: sent to the sender of a class booking or join
// request, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the System
// region after it keeps the fixed comment for an e-mail the widget does not
// draw.
$class_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your class booking, your request to join, a conversation or a Training Notes post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The sender's name.
$member_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// When the class is: the date, then the day and the time.
$class_when = function () use ($span, $bind, $sample_date, $sample_day, $sample_time) {
    return array(
        $span($sample_date, '', lang('Date'), $bind('class_date')),
        $span(' · ', '', lang('Text')),
        $span($sample_day, '', lang('Day'), $bind('day')),
        $span(' ', '', lang('Text')),
        $span($sample_time, '', lang('Time'), $bind('time')),
    );
};

// A request in a list, linked to its page: its title and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the member's own adds the messages.
$request_row = function ($staff, $title_sample, $title_field, $title_name, $status, $items) use ($el, $span, $icon, $bind, $member_name) {
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')),
        ));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($member_name(), array(
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
$booking_row = function ($staff = false) use ($el, $icon, $request_row, $class_when, $sample_class, $booking_statuses) {
    return $request_row($staff, $sample_class, 'class', lang('Class'), $booking_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('When'), array_merge(array($icon('bi-calendar-event')), $class_when())),
    ));
};
$join_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_join_plan, $sample_goal, $join_statuses) {
    return $request_row($staff, $sample_join_plan, 'plan', lang('Plan'), $join_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Goal'), array($icon('bi-bullseye'), $span($sample_goal, '', lang('Goal'), $bind('goal')))),
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

// The lines of a class booking and of a join request, for their e-mails.
$mail_class = function () use ($mail_box, $mail_line, $span, $bind, $class_when, $sample_class, $experience_choices) {
    return $mail_box(array(
        $mail_line(lang('Class'), array($span($sample_class, '', lang('Class'), $bind('class')))),
        $mail_line(lang('When'), $class_when()),
        $mail_line(lang('Experience'), array($span($experience_choices[0][1], '', lang('Experience'), $bind('experience')))),
    ), lang('Class Booking Request'));
};
$mail_join = function () use ($mail_box, $mail_line, $span, $bind, $sample_join_plan, $sample_goal, $experience_choices, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Plan'), array($span($sample_join_plan, '', lang('Plan'), $bind('plan')))),
        $mail_line(lang('Goal'), array($span($sample_goal, '', lang('Goal'), $bind('goal')))),
        $mail_line(lang('Experience'), array($span($experience_choices[0][1], '', lang('Experience'), $bind('experience')))),
        $mail_line(lang('Preferred first visit'), array($span(lang('Preferred first visit'), '', lang('Preferred first visit'), $bind('first_visit')))),
        $mail_line(lang('Reply by'), array($span($contact_choices[0][1], '', lang('Reply by'), $bind('reply_by')))),
    ), lang('Join Request'));
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
    ), array_merge($mail_style('border-top:1px solid #e5e7eb;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail.
$mail_sender = function ($notes_sample) use ($mail_box, $mail_line, $span, $bind, $member_name) {
    return $mail_box(array(
        $mail_line(lang('Name'), $member_name()),
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
// A list of requests: the member's own (the staff see them all), or the
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
    use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $link, $span, $bind, $show_when, $attr, $edit_button, $bound) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                        $heading('h2', $not_found_title, 'h3 fw-semibold mt-3'),
                        $para($missing, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary px-4'),
                    ), $show_when('record_not_found')),
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
                                        $para($eyebrow, 'pgy-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'pgy-display h3 mb-0', $bind($title_field)),
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
// the front desk every message.
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

// The sender's fields at the end of both forms.
$contact_fields = function ($p) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

$booking_missing = lang('This class booking request could not be found, or it is not yours to see.');
$join_missing = lang('This join request could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));

$class_widgets = array(

    // The class booking request: the fields are the page's form. The class,
    // the day and the time start with what a link sends (?class=, ?day=,
    // ?time=). The Status is the front desk's (office use only): every
    // request starts as Request received.
    'class_booking_form' => array(
        'page'   => 'class_booking',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your class')),
            $field('gy-cb-class', lang('Class'), $select('gy-cb-class', 'class', lang('Choose a class…'), $class_names, true, '', array('url_param' => 'class'))),
            $row(array(
                $col(array($field('gy-cb-day', lang('Day'), $select('gy-cb-day', 'day', lang('Choose a day…'), $day_titles, true, '', array('url_param' => 'day')))), '4'),
                $col(array($field('gy-cb-time', lang('Time'), $select('gy-cb-time', 'time', lang('Choose a time…'), $time_choices, true, '', array('url_param' => 'time')))), '4'),
                $col(array($field('gy-cb-date', lang('Date'), $input('gy-cb-date', 'date', 'class_date', 'off', true, $not_before_today))), '4'),
            ), '3'),
            $para(lang('The timetable shows which day and time each class runs; the date is the day you come.'), 'form-text mt-n2 mb-3'),
            $radios('gy-cb-experience', 'experience', lang('Experience'), $experience_choices, $experience_choices[0][0]),
            $field('gy-cb-notes', lang('Notes'), $textarea('gy-cb-notes', 'notes', 3, false), 'mb-3',
                lang('An injury, a first class, a friend coming with you.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('gy-cb'),
            $field('gy-cb-status', lang('Status'), $select('gy-cb-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
            $agree('gy-cb-consent', 'consent', lang('I agree that the gym keeps these details to answer my request and keep my place, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The front desk confirms your place within a few hours.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One class booking request; the front desk's answers follow as
    // comments.
    'booking_detail' => $detail_widget('booking_view', 'class_booking', $booking_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Class Bookings'), 'my_bookings', $booking_missing, lang('Class booking request not found'),
            lang('Class Booking Request'), $sample_class, 'class', $booking_statuses[0],
            lang('Thank you, your request has reached us. The front desk confirms your place here within a few hours; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('When'), '', lang('When'), $class_when(), true),
                $detail(lang('Experience'), '', lang('Experience'), $bound($experience_choices[0][1], 'experience')),
                $detail(lang('Name'), '', lang('Name'), $member_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_class_notes,
            lang('Another time, a question or cannot come? Write to us below; we answer here and by e-mail.')),
    ),

    'booking_messages' => $request_messages('booking_view',
        lang('No messages yet. The front desk\'s answer comes here; you can write to us about your class too.')),

    'my_bookings_list' => $request_list('my_bookings', 'class_booking', 'booking_view', $booking_row, array(), '',
        lang('No class booking requests yet. Pick a class from the timetable.')),

    'staff_bookings_list' => $request_list('staff_bookings', 'class_booking', 'booking_view', $booking_row,
        array('first_name', 'last_name', 'email', 'class', 'day', 'status'),
        lang('Search by name, e-mail, class, day or status...'), lang('No class booking requests yet.')),

    // The join request: the plan starts with what a link sends (?plan=).
    // A health note is optional and kept in the private folder.
    'join_form' => array(
        'page'   => 'join',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your plan')),
            $field('gy-jn-plan', lang('Plan'), $select('gy-jn-plan', 'plan', lang('Choose a plan…'), $join_choices, true, '', array('url_param' => 'plan')), 'mb-3',
                lang('Not sure yet? Start with the free trial pass.')),
            $row(array(
                $col(array($field('gy-jn-goal', lang('Goal'), $select('gy-jn-goal', 'goal', lang('Choose a goal…'), $goal_choices, false))), '6'),
                $col(array($field('gy-jn-first-visit', lang('Preferred first visit'), $input('gy-jn-first-visit', 'date', 'first_visit', 'off', false, $not_before_today))), '6'),
            ), '3'),
            $radios('gy-jn-experience', 'experience', lang('Experience'), $experience_choices, $experience_choices[0][0]),
            $field('gy-jn-notes', lang('Notes'), $textarea('gy-jn-notes', 'notes', 3, false), 'mb-3',
                lang('An injury, a condition your coach should know about, the times you can train.')),
            $field('gy-jn-health', lang('Health note (optional)'), $file('gy-jn-health', 'health_note', 'private'), 'mb-3',
                lang('A note from your doctor or physiotherapist, if you have one.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('gy-jn'),
            $radios('gy-jn-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('gy-jn-status', lang('Status'), $select('gy-jn-status', 'status', '', $join_statuses, false, $join_statuses[0], array('office_use_only' => true))),
            $agree('gy-jn-consent', 'consent', lang('I agree that the gym keeps these details, my health note among them, to answer my request and coach me safely, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The front desk writes to you within a day. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    'join_detail' => $detail_widget('join_view', 'join', $join_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('Compare the plans'), 'plans', $join_missing, lang('Join request not found'),
            lang('Join Request'), $sample_join_plan, 'plan', $join_statuses[0],
            lang('Thank you, your request has reached us. The front desk writes to you here within a day to book your first visit; you can write to us below.'),
            array_merge(
                $detail(lang('Goal'), '', lang('Goal'), $bound($sample_goal, 'goal')),
                $detail(lang('Experience'), '', lang('Experience'), $bound($experience_choices[0][1], 'experience')),
                $detail(lang('Preferred first visit'), '', lang('Preferred first visit'), $bound(lang('Preferred first visit'), 'first_visit')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $member_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_join_notes,
            lang('Another plan, another day or a question? Write to us below; we answer here and by e-mail.')),
    ),

    'join_messages' => $request_messages('join_view',
        lang('No messages yet. The front desk\'s answer comes here; you can write to us about joining too.')),

    'staff_joins_list' => $request_list('staff_joins', 'join', 'join_view', $join_row,
        array('first_name', 'last_name', 'email', 'plan', 'goal', 'status'),
        lang('Search by name, e-mail, plan, goal or status...'), lang('No join requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_booking_admin' => $detail_widget('email_booking_new', 'class_booking', lang('The class booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new class booking request')),
            $mail_para(lang('It came from the class booking form. Confirm the place under the request on its page, or offer another time, and set the status; what you write there reaches the member by e-mail.')),
            $mail_class(),
            $mail_sender($sample_class_notes),
            $mail_button(lang('Open the request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_booking_client' => $detail_widget('email_booking_received', 'class_booking', lang('The class booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your class booking request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The front desk confirms your place within a few hours, on the request\'s page and by e-mail. Come ten minutes early and bring a towel and water.')),
            $mail_class(),
            $mail_para(lang('Another time or cannot come? Write to us on the request\'s page so the place goes to someone else.')),
            $mail_button(lang('Open my request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_join_admin' => $detail_widget('email_join_new', 'join', lang('The join request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new join request')),
            $mail_para(lang('It came from the join form. Book the first visit under the request on its page and set the status; what you write there reaches the visitor by e-mail.')),
            $mail_join(),
            $mail_sender($sample_join_notes),
            $mail_button(lang('Open the request'), '{{page:join_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_join_client' => $detail_widget('email_join_received', 'join', lang('The join request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your request to join')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The front desk writes to you within a day to book your first visit and a welcome session with a coach. Nothing is paid now.')),
            $mail_join(),
            $mail_para(lang('A question before you come? Write to us on the request\'s page; everything about joining stays there.')),
            $mail_button(lang('Open my request'), '{{page:join_view}}?r=^^reference_code^^'),
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

return array('pages' => $class_pages, 'widgets' => $class_widgets);
