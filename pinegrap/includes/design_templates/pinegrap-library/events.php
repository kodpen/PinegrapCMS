<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Public Library", its events and reading clubs and the
 * place requests: the staff page that adds an event (the Events form, a
 * collection, with the sample events written in when it is made), the
 * list of every event with its kind filter, the event's own page and the
 * list the home page places; the place request, the request's own page
 * with its messages, My Bookings and the staff's list; the privacy page
 * the consent boxes link; the e-mails of the request and the new message
 * e-mail every thread of the site sends. Included after parts.php,
 * mail.php and catalogue.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An event is a record of the Events form. Its kind, branch and age group
 * are pick lists whose answers are the words parts.php gives, so a link
 * with ?kind= (the filter row, the public pages, the event page) filters
 * the list by the words a visitor sees. When is words: the library writes
 * its own in each record, the sample never names a year. Featured is the
 * library's choice (office use only): the home page shows the featured
 * events.
 *
 * A place is a request, never a held seat: a record of the Event Bookings
 * form. The template keeps no capacity, so the library answers each
 * request and sets its office-use-only Status; its answers are the page's
 * comments, mailed to the reader and to the library. A reader without an
 * account gets one (auto-registration), so the request's page and My
 * Bookings show it at once. The event starts with what the event page
 * sends (?event=, the event's title).
 *
 * The request pieces under "Request pieces" ($request_aside,
 * $request_page, $request_view, $staff_list_page, $visitor_name,
 * $request_row, $detail, $bound, $detail_widget, $request_list,
 * $request_detail, $request_messages, $contact_fields, $mail_reference,
 * $mail_new_account, $mail_sender) serve the membership, reservation and
 * room booking requests too (membership.php, rooms.php), included after
 * this file in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$event_pages = array();

// The sample request the canvas shows.
$sample_event_key = 'book_club';
$sample_event = $events[$sample_event_key]['title'];
$sample_places = $place_counts[1];
$sample_booking_notes = lang('It will be my first time at the club: should I have read the whole book before I come?');

// ── Request pieces ──────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the Central
// Library's opening hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $hours_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'plb-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Opening Hours'), array(
            $heading('h2', lang('Central Library'), 'plb-display h5 mb-3'),
            $hours_list('central', 'mb-3'),
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

// The sender's name.
$visitor_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list, linked to its page: its title and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the reader's own adds the messages.
$request_row = function ($staff, $title_sample, $title_field, $title_name, $status, $items) use ($el, $span, $icon, $bind, $visitor_name) {
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')),
        ));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($visitor_name(), array(
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
$mail_sender = function ($notes_sample) use ($mail_box, $mail_line, $span, $bind, $visitor_name) {
    return $mail_box(array(
        $mail_line(lang('Name'), $visitor_name()),
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
// A list of requests: the reader's own (the staff see them all), or the
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
                                        $para($eyebrow, 'plb-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'plb-display h3 mb-0', $bind($title_field)),
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
// the library every message.
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

// ── Event pieces ────────────────────────────────────────────────────────

// A list of events: the Events form, the event page, the newest first, and
// the words shown when nothing matches.
$lb_event_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'detail_page_id'      => '{{tab:event}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};
// The event cards of a list, in rows of three.
$event_grid = function () use ($widget_root, $row, $loop, $event_card) {
    return $widget_root(array($row(array($loop(array($event_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every event, narrowed by kind from the address.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events and Reading Clubs'),
    'meta_description' => lang('Reading clubs, author talks, story times, workshops, digital help and exhibitions at our libraries, and how to book a place.'),
    'tree'             => $page(array(
        $page_header(lang('Events and Reading Clubs'), lang('Clubs, talks, story times and workshops in our three libraries, almost all free. Choose a kind to narrow the list, and ask for a place from the event\'s page.')),
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $lb_filter_bar('events', lang('Show'), 'kind', $event_kind_titles),
                $el('div', 'mt-4', lang('List'), array($widget('events_list'))),
            )),
        ), $searchable('lib-events', lang('what\'s on, events, reading club, book club, author talk, story time, workshop, digital help, exhibition, children'))),
    )),
);

// One event: its record.
$event_pages[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event'),
    'meta_description' => lang('An event at the library: when and where it is, who it is for, what happens and how to ask for a place.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

// The staff page that adds an event. The form is the Events collection;
// the sample events are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
$sample_events = array();
foreach ($events as $key => $e) {
    $sample_events[] = array(
        'title'     => $e['title'],
        'kind'      => $event_kind($key),
        'branch'    => $event_branch($key),
        'when'      => $e['when'],
        'age_group' => $event_age($key),
        'summary'   => $e['summary'],
        'details'   => $details_text($key),
        'photo_1'   => $photo('event-' . $key, 1200, 675),
        'featured'  => $e['featured'] ? $lb_yes_no[0] : $lb_yes_no[1],
    );
}
$event_pages[] = array(
    'key'     => 'event_new',
    'name'    => lang('new-event'),
    'folder'  => 'private',
    'title'   => lang('New Event'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Events'),
        'collection'           => 1,
        'confirmation_message' => lang('The event is published.'),
        'confirmation_page_id' => '{{tab:events}}',
        'sample_records'       => $sample_events,
        // The photo is the event's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Event'), lang('Add the event with its branch, when it is in words and a photo; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded border', lang('Form Card'), array($widget('event_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The place request. What is sent opens the request's own page.
$event_pages[] = array(
    'key'              => 'event_booking',
    'name'             => lang('book-an-event'),
    'folder'           => 'public',
    'title'            => lang('Ask for a Place'),
    'meta_description' => lang('Ask for a place at a reading club, an author talk or a workshop: the library confirms it by e-mail and on your request\'s page.'),
    'form'             => array(
        'form_name'            => lang('Event Bookings'),
        'confirmation_message' => lang('Thank you, your request has reached us. The library confirms your place within a day.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New place request: ^^event^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your place request'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $request_page(lang('Ask for a Place'),
        lang('Choose the event and how many places you need. The library confirms your place within a day; our events are free.'),
        'event_booking_form',
        $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The library confirms your place there within a day, or puts you on the waiting list when the event is full.'),
            lang('Come ten minutes early and give your name at the library desk.'),
            lang('Cannot come? Write to us on the request\'s page so the place goes to someone else.'),
        ), lang('See all events'), '{{page:events}}')),
);

// One place request: the request, its status and the messages.
$event_pages[] = $request_view('booking_view', lang('place-request'), lang('Place Request'),
    'booking_detail', 'booking_messages', lang('There is a new message about your place request'));

// My Bookings: the reader's own requests (for the staff, everybody's).
$event_pages[] = array(
    'key'     => 'my_bookings',
    'name'    => lang('my-bookings'),
    'folder'  => 'registration',
    'title'   => lang('My Bookings'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Bookings'), lang('Your place requests for events, their status and the library\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_bookings_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another event?'), '', lang('Text')),
                            )),
                            $para(lang('Choose one from the events; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('See all events'), '{{page:events}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$event_pages[] = $staff_list_page('staff_bookings', lang('all-place-requests'), lang('Place Requests'),
    lang('Every place request, newest first: confirm the place, set the status and write to the reader under each.'),
    'staff_bookings_list');

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$event_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the library keeps about you when you join, reserve a title, book a place or a room, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the library keeps about you when you join, reserve a title, book a place or a room, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'plb-display h4 mb-3'),
                $para(lang('When you ask for a library card, reserve a title, ask for a place at an event, book a room, join the newsletter or open an account, we keep your name, your contact details and what you tell us, such as your address or access needs, to answer you, issue your card and keep your place.'), 'mb-3'),
                $para(lang('We never keep a list of what you borrow or read beyond what lending needs, never sell your data and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('lib-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the library receives and the reply to the sender of a
// place request, and the new message e-mail.
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_new',
    'name'  => lang('email-new-place-request'),
    'title' => lang('New Place Request E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_admin'))),
));
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_received',
    'name'  => lang('email-place-request-received'),
    'title' => lang('Place Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_client'))),
));
// A new message or reply: sent to the sender of a request, the sender of a
// conversation and the readers watching a page. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: one of your requests to the library, a conversation or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The request's event, its places and its notes, for its e-mails.
$mail_booking = function () use ($mail_box, $mail_line, $span, $bind, $sample_event, $sample_places) {
    return $mail_box(array(
        $mail_line(lang('Event'), array($span($sample_event, '', lang('Event'), $bind('event')))),
        $mail_line(lang('Places'), array($span($sample_places, '', lang('Places'), $bind('places')))),
    ), lang('Place Request'));
};
$booking_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_event, $sample_places, $booking_statuses) {
    return $request_row($staff, $sample_event, 'event', lang('Event'), $booking_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Places'), array(
            $icon('bi-people'), $span($sample_places, '', lang('Places'), $bind('places')),
        )),
    ));
};
$booking_missing = lang('This place request could not be found, or it is not yours to see.');

$event_widgets = array(

    // The staff's Events form: one event. The branch starts as the Central
    // Library and the age group as everyone; featured is the library's
    // choice (office use only) and starts as No.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('lib-event-title', lang('Event Title'), pg_cf_with_role($input('lib-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('lib-event-kind', lang('Kind'), pg_cf_with_role($select('lib-event-kind', 'kind', lang('Choose a kind'), $event_kind_titles), 'category'))), '6'),
                $col(array($field('lib-event-branch', lang('Branch'), $select('lib-event-branch', 'branch', '', $branch_titles, true, $branch_titles[0]))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('lib-event-when', lang('When'), $input('lib-event-when', 'text', 'when', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $events['book_club']['when']))), '6'),
                $col(array($field('lib-event-age', lang('Age Group'), $select('lib-event-age', 'age_group', '', $age_groups, true, $age_groups[0]))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('lib-event-summary', lang('Summary'), pg_cf_with_role($textarea('lib-event-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('lib-event-details', lang('What Happens'), $textarea('lib-event-details', 'details', 5, false), 'mb-3', lang('One point on each line.')),
            $form_step(3, lang('Photo')),
            $field('lib-event-photo-1', lang('Photo'), pg_cf_with_role($file('lib-event-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $field('lib-event-featured', lang('Featured'), $select('lib-event-featured', 'featured', '', $lb_yes_no, false, $lb_yes_no[1], array('office_use_only' => true)),
                'mb-3', lang('A featured event is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every event, filtered by the address (kind), the newest first, with
    // a search over the words of the events.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $lb_event_list(lang('No event of this kind yet. Choose another, or see all events.'), array(
            'items_per_page' => 12,
            'filters'        => array($lb_filter('kind', 'is equal to', '', 'kind')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'kind', 'branch', 'when', 'age_group', 'summary', 'details'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the events: a club, a talk, a branch...'),
        )),
        'tree'   => $event_grid(),
    ),

    // The home page's featured events, three at most.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $lb_event_list(lang('No featured event just now. See all events.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($lb_filter('featured', 'is equal to', $lb_yes_no[0])),
        )),
        'tree'   => $event_grid(),
    ),

    // The place request: the fields are the page's form. The event starts
    // with what the event page sends (?event=, the event's title): a text
    // field, so an event the library adds later is taken as well. The
    // Status is the library's (office use only): every request starts as
    // Request received.
    'event_booking_form' => array(
        'page'   => 'event_booking',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your event')),
            $row(array(
                $col(array($field('lib-eb-event', lang('Event'), $input('lib-eb-event', 'text', 'event', 'off', true, array('url_param' => 'event')), 'mb-3',
                    lang('Filled in from the event\'s page; write the event\'s name if it is empty.'))), '8'),
                $col(array($field('lib-eb-places', lang('Places'), $select('lib-eb-places', 'places', '', $place_counts, true, $place_counts[0]))), '4'),
            ), '3'),
            $field('lib-eb-notes', lang('Notes'), $textarea('lib-eb-notes', 'notes', 3, false), 'mb-3',
                lang('Access needs, a child coming with you, a question for the speaker.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('lib-eb'),
            $radios('lib-eb-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('lib-eb-status', lang('Status'), $select('lib-eb-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
            $agree('lib-eb-consent', 'consent', lang('I agree that the library keeps these details to answer my request and keep my place.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The library confirms your place within a day. Our events are free.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One place request; the library's answers follow as comments.
    'booking_detail' => $detail_widget('booking_view', 'event_booking', $booking_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Bookings'), 'my_bookings', $booking_missing, lang('Place request not found'),
            lang('Place Request'), $sample_event, 'event', $booking_statuses[0],
            lang('Thank you, your request has reached us. The library confirms your place here within a day; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Places'), '', lang('Places'), $bound($sample_places, 'places')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_booking_notes,
            lang('More places, a question or cannot come? Write to us below; we answer here and by e-mail.')),
    ),

    'booking_messages' => $request_messages('booking_view',
        lang('No messages yet. The library\'s answer comes here; you can write to us about the event too.')),

    'my_bookings_list' => $request_list('my_bookings', 'event_booking', 'booking_view', $booking_row, array(), '',
        lang('No place requests yet. Choose an event and ask for a place.')),

    'staff_bookings_list' => $request_list('staff_bookings', 'event_booking', 'booking_view', $booking_row,
        array('first_name', 'last_name', 'email', 'event', 'status'),
        lang('Search by name, e-mail, event or status...'), lang('No place requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_booking_admin' => $detail_widget('email_booking_new', 'event_booking', lang('The place request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new place request')),
            $mail_para(lang('It came from the place request form. Confirm the place under the request on its page, or put it on the waiting list, and set the status; what you write there reaches the reader by e-mail.')),
            $mail_booking(),
            $mail_sender($sample_booking_notes),
            $mail_button(lang('Open the request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_booking_client' => $detail_widget('email_booking_received', 'event_booking', lang('The place request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your place request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The library confirms your place within a day, on the request\'s page and by e-mail. Come ten minutes early and give your name at the library desk.')),
            $mail_booking(),
            $mail_para(lang('Cannot come? Write to us on the request\'s page so the place goes to someone else.')),
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

// One event: the kind, the title, the summary, its facts, the photo, what
// happens and beside them the way to ask for a place, the opening hours
// and the other events of the same kind.
$event_sample = $events[$sample_event_key];
$event_not_found = lang('This event could not be found. It may have passed or been taken off the list.');
$event_widgets['event_detail'] = array(
    'page'   => 'event',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $event_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Event not found'), 'h3 fw-semibold mt-3'),
                $para($event_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('All Events'), '{{page:events}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Event'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Events'), '', lang('Link Text')),
                    ), array('href' => '{{page:events}}')),
                    $edit_button(),
                )),
                $heading('h1', $event_sample['title'], 'plb-display display-5 mb-2', $bind('title')),
                $para($event_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $event_facts($sample_event_key, 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-4'),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'object-fit-cover', 'ratio ratio-16x9 mb-4',
                            $photo('event-' . $sample_event_key, 1200, 675), lang('Photo')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('What Happens'), array(
                            $heading('h2', lang('What happens'), 'plb-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $details_text($sample_event_key)), $bind('details'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary', lang('Booking Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Join us'), 'plb-display h5 mb-2'),
                                $para(lang('Ask for a place: the library confirms it within a day. Our events are free.'), 'small text-body-secondary mb-3'),
                                // Opens the request with this event chosen
                                // (?event=).
                                $link(lang('Ask for a Place'), '#', 'btn btn-primary w-100 mb-3', array(),
                                    $lb_page_link('event_booking', array('event' => 'title'))),
                                $link(lang('Opening Hours and Branches'), '{{page:visit}}', 'btn btn-outline-secondary w-100 mb-2'),
                                // Opens the list on this event's kind (?kind=).
                                $link(lang('More events like this'), '#', 'btn btn-link w-100', array(),
                                    $lb_page_link('events', array('kind' => 'kind'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
