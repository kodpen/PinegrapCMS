<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ferry", its booking requests and group enquiries: the
 * booking request, the request's own page with its messages, My Bookings
 * and the staff's list; the group enquiry, the enquiry's own page, My
 * Group Enquiries and the staff's list; the privacy page the consent boxes
 * link, the e-mails of both forms and the new message e-mail every thread
 * of the site sends, and the widgets that draw them. Included after
 * parts.php, mail.php and notices.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A booking request is a request, never a ticket: a record of the Booking
 * Requests form. A traveller without an account gets one
 * (auto-registration), so the request's page and My Bookings show it at
 * once. Its Status is an office-use-only field the booking office changes
 * in the control panel; the sailing confirmed and the fare are written
 * under it as messages (the page's comments), mailed to the traveller and
 * to the line. The template keeps no seat plan and computes no fare.
 *
 * The route starts with what a link sends (?route= from the route pages,
 * the timetables and the travel notices), the fare with ?fare= from the
 * fares table, each the answer's own words as parts.php writes them. A
 * notice for every route sends "All Routes", no answer of the list, so
 * the traveller chooses. A group enquiry is a record of its own form the
 * same way; its route starts with ?route=.
 *
 * The staff's lists narrow by route and status from the address, with
 * the filter bar of notices.php above them.
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

// The sample records the canvas shows. Their dates are words, never a
// date: a fixed day would read as a past season a year later.
$bk_sample_route = $routes['bay_cedar']['title'];
$bk_sample_fare = $fare_kinds['car']['title'];
$bk_sample_notes = lang('We travel with our two children and the dog, and would like the morning sailing if there is room; our car is a small estate with a roof box.');
$bk_missing = lang('This booking request could not be found, or it is not yours to see.');
$gr_sample_route = $routes['bay_gull']['title'];
$gr_sample_note = lang('A class of 28 pupils and three teachers on a day trip to the lighthouse, out in the morning and back in the late afternoon.');
$gr_missing = lang('This group enquiry could not be found, or it is not yours to see.');
$gr_coach_choices = array(lang('No coach'), lang('One coach'), lang('Two coaches or more'));
$fy_sample_out = lang('Outward date');
$fy_sample_back = lang('Return date');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a form: what happens next, then what is good to
// know and a link.
$fy_aside = function ($steps, $good, $link_text, $link_key) use ($el, $heading, $link, $check_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pfy-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Good to know'), array(
            $heading('h2', lang('Good to know'), 'pfy-display h5 mb-3'),
            $check_list($good, 'list-unstyled small mb-3'),
            $link($link_text, '{{page:' . $link_key . '}}', 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// The card of a My… list that starts another record.
$fy_new_card = function ($icon_name, $title, $text, $button, $page_key) use ($el, $icon, $span, $para, $link) {
    return $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
            $icon($icon_name, 'text-primary'),
            $span($title, '', lang('Text')),
        )),
        $para($text, 'small text-body-secondary mb-3'),
        $link($button, '{{page:' . $page_key . '}}', 'btn btn-sm btn-primary rounded-pill px-3'),
    ));
};

// A form page: the header, the form's widget in a card and the side column.
$fy_form_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($widget_key))),
                        )),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A record page: the record's widget and the messages under it. $subject
// is the subject of the e-mail a new message sends.
$fy_record_page = function ($key, $name, $title, $detail_key, $messages_key, $subject) use ($page, $widget, $comments_section) {
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

// The traveller's own list, with a card that starts another record.
$fy_mine_page = function ($key, $name, $title, $lead, $widget_key, $card) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'registration',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array($widget($widget_key)), '', '8'),
                        $col(array($card), '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
        ), false),
    );
};

// The staff's list of every record, newest first, with the filter bar
// (route and status) and a search. $statuses are the form's status answers.
$fy_staff_page = function ($key, $name, $title, $lead, $widget_key, $statuses) use ($staff_page, $page_header, $el, $container, $widget, $fy_filter_bar, $route_titles) {
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
                $container(array(
                    $fy_filter_bar($key, array(
                        array('label' => lang('Route'), 'param' => 'route', 'values' => $route_titles),
                        array('label' => lang('Status'), 'param' => 'status', 'values' => $statuses),
                    )),
                    $widget($widget_key),
                )),
            )),
        )),
    );
};

// An e-mail page that draws one widget.
$fy_mail_page = function ($key, $name, $title, $widget_key) use ($mail_settings, $mail_page, $widget) {
    return array_merge($mail_settings, array(
        'key'   => $key,
        'name'  => $name,
        'title' => $title,
        'tree'  => $mail_page(array($widget($widget_key))),
    ));
};

// ── Pages: booking requests ─────────────────────────────────────────────

// The booking request. What is sent opens the request's own page.
$booking_pages[] = array(
    'key'              => 'booking_request',
    'name'             => lang('request-a-booking'),
    'folder'           => 'public',
    'title'            => lang('Request a Booking'),
    'meta_description' => lang('Tell us your route, your day and who is travelling: the booking office confirms your place on board within a day.'),
    'form'             => array(
        'form_name'            => lang('Booking Requests'),
        'confirmation_message' => lang('Thank you, your booking request has reached us. The booking office answers within a day.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New booking request: ^^route^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your ferry booking request'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $fy_form_page(lang('Request a Booking'),
        lang('Tell us your route, your day and who is travelling. The booking office confirms your place on board within a day; nothing is paid now.'),
        'booking_form', $fy_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The booking office answers there within a day, with the sailing it confirms and the fare.'),
            lang('The status shows Booking confirmed once your place on board is yours.'),
            lang('Nothing is paid now: you pay at the port\'s booking office before you board.'),
        ), array($line['checkin'], $fare_note, $line['hours']), lang('See the Fares'), 'fares')),
);

// One booking request: the request, its status and the messages under it.
$booking_pages[] = $fy_record_page('booking_view', lang('booking-request'), lang('Booking Request'), 'booking_detail', 'booking_messages',
    lang('There is a new message about your booking request'));

// My Bookings: the traveller's own requests (for the staff, everybody's).
$booking_pages[] = $fy_mine_page('my_bookings', lang('my-bookings'), lang('My Bookings'),
    lang('Your booking requests, their status and the booking office\'s answers, newest first.'), 'my_bookings_list',
    $fy_new_card('bi-calendar-plus', lang('Another crossing?'),
        lang('Ask for another route or another day; your details are filled in for you.'), lang('Request a Booking'), 'booking_request'));

$booking_pages[] = $fy_staff_page('staff_bookings', lang('all-booking-requests'), lang('Booking Requests'),
    lang('Every booking request, newest first: confirm the sailing, set the status and write to the traveller under each.'), 'staff_bookings_list',
    $booking_statuses);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$booking_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the ferry line keeps about you when you ask for a booking or send a group enquiry, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the ferry line keeps about you when you ask for a booking or send a group enquiry, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pfy-display h4 mb-3'),
                $para(lang('When you ask for a booking or send a group enquiry, join the newsletter or open an account, we keep your name, your contact details, your route and dates, your vehicle\'s details and what you tell us, to answer you, keep your place on board and welcome you.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('fy-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails of the booking request: what the booking office receives
// and the reply to the traveller.
$booking_pages[] = $fy_mail_page('email_booking_new', lang('email-new-booking-request'), lang('New Booking Request E-mail'), 'email_booking_admin');
$booking_pages[] = $fy_mail_page('email_booking_received', lang('email-booking-request-received'), lang('Booking Request Received E-mail'), 'email_booking_client');

// A new message or reply: sent to the traveller of a booking request or a
// group enquiry, the sender of a conversation and the visitors watching a
// page. Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your booking request, your group enquiry, a conversation or a news article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Pages: group enquiries ──────────────────────────────────────────────

// The group enquiry. What is sent opens the enquiry's own page.
$booking_pages[] = array(
    'key'              => 'group_enquiry',
    'name'             => lang('group-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Group Enquiry'),
    'meta_description' => lang('Travelling as a school, a coach tour, a club or a company? Tell us your route, your day and how many you are: the group desk sends an offer.'),
    'form'             => array(
        'form_name'            => lang('Group Enquiries'),
        'confirmation_message' => lang('Thank you, your group enquiry has reached us. The group desk answers within two working days with an offer.'),
        'confirmation_page_id' => '{{tab:group_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New group enquiry: ^^route^^'),
        'notify_page_id'       => '{{tab:email_group_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your group enquiry'),
        'confirm_page_id'      => '{{tab:email_group_received}}',
    ),
    'tree'             => $fy_form_page(lang('Group Enquiry'),
        lang('Tell us your route, your day and how many you are. The group desk answers within two working days with an offer; nothing is paid now.'),
        'group_form', $fy_aside(array(
            lang('Your enquiry opens its own page, and an account for you if you have none.'),
            lang('The group desk answers there within two working days, with the sailings it holds for you and the group fare.'),
            lang('Confirm it in a message; the status shows Group confirmed once the places are yours.'),
            lang('Nothing is paid now: the group desk explains how to pay with its offer.'),
        ), array(lang('Groups of ten or more travel on a group fare.'), $line['checkin'], $line['hours']), lang('See the Timetables'), 'timetables')),
);

// One group enquiry: what was asked, its status and the messages under it.
$booking_pages[] = $fy_record_page('group_view', lang('group-enquiry-view'), lang('Group Enquiry'), 'group_detail', 'group_messages',
    lang('There is a new message about your group enquiry'));

$booking_pages[] = $fy_mine_page('my_groups', lang('my-group-enquiries'), lang('My Group Enquiries'),
    lang('Your group enquiries, their status and the group desk\'s offers, newest first.'), 'my_groups_list',
    $fy_new_card('bi-people', lang('Another group trip?'),
        lang('Ask about another route or another day; your details are filled in for you.'), lang('Send a Group Enquiry'), 'group_enquiry'));

$booking_pages[] = $fy_staff_page('staff_groups', lang('all-group-enquiries'), lang('Group Enquiries'),
    lang('Every group enquiry, newest first: send the offer, set the status and answer under each.'), 'staff_groups_list',
    $group_statuses);

$booking_pages[] = $fy_mail_page('email_group_new', lang('email-new-group-enquiry'), lang('New Group Enquiry E-mail'), 'email_group_admin');
$booking_pages[] = $fy_mail_page('email_group_received', lang('email-group-enquiry-received'), lang('Group Enquiry Received E-mail'), 'email_group_client');

// ── Widget pieces ───────────────────────────────────────────────────────

// The traveller's name.
$fy_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The outward date, then the return date where there is one.
$fy_dates = function () use ($el, $span, $bind, $fy_sample_out, $fy_sample_back) {
    return array(
        $span($fy_sample_out, '', lang('Outward'), $bind('outward_date')),
        $el('span', 'pg-hide-if-empty', lang('Return'), array(
            $span(' → ', '', lang('Text')),
            $span($fy_sample_back, 'pg-field-value', lang('Return'), $bind('return_date')),
        )),
    );
};
// A detail of a record's card: the label, then the value. $value is either
// the value's props (its sample text and binding) or, with $children, the
// nodes it is made of.
$fy_detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$fy_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// A record in a list: the title, the status, the details, linked to its
// page. The staff's row starts with the traveller's name and adds the
// phone; the traveller's own adds the messages. $items: array(icon, label,
// sample, field) each.
$fy_row = function ($name, $who, $title_sample, $title_label, $title_field, $status, $items, $staff) use ($el, $span, $icon, $bind, $fy_person) {
    $nodes = array();
    if ($staff) $items[] = array('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
    else $items[] = array('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
    foreach ($items as $i) {
        $nodes[] = $el('span', 'd-inline-flex align-items-center gap-1', $i[1], array($icon($i[0]), $span($i[2], '', $i[1], $bind($i[3]))));
    }
    if ($staff) {
        $title = $el('span', 'fw-semibold', $who, array_merge($fy_person(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $title_label, $bind($title_field)),
        )));
    } else {
        $title = $span($title_sample, 'fw-semibold', $title_label, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $nodes),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$bk_row = function ($staff = false) use ($fy_row, $bk_sample_route, $fy_sample_out, $journey_choices, $vehicle_choices, $booking_statuses) {
    return $fy_row(lang('Booking Request'), lang('Traveller'), $bk_sample_route, lang('Route'), 'route', $booking_statuses[0], array(
        array('bi-calendar-event', lang('Outward'), $fy_sample_out, 'outward_date'),
        array('bi-arrow-left-right', lang('Journey'), $journey_choices[1][1], 'journey'),
        array('bi-people', lang('Adults'), '2', 'adults'),
        array('bi-car-front', lang('Vehicle'), $vehicle_choices[1], 'vehicle'),
    ), $staff);
};
$gr_row = function ($staff = false) use ($fy_row, $gr_sample_route, $group_kind_choices, $group_statuses) {
    return $fy_row(lang('Group Enquiry'), lang('Organiser'), $gr_sample_route, lang('Route'), 'route', $group_statuses[0], array(
        array('bi-calendar-event', lang('Travel date'), lang('Travel date'), 'travel_date'),
        array('bi-people', lang('Group size'), '31', 'group_size'),
        array('bi-flag', lang('Kind of group'), $group_kind_choices[0], 'group_kind'),
    ), $staff);
};

// The form detail settings of a widget: the e-mail pages read the record
// they are sent for (public), the record's page only the traveller's own.
$fy_detail_config = function ($page_key, $form_key, $missing, $access) {
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
// A list of records: the traveller's own (the staff see them all), or the
// staff's with the route and status from the address and a search.
$fy_list = function ($page_key, $form_key, $detail_key, $row_node, $empty, $search_fields = array(), $search_label = '') use ($widget_root, $el, $loop, $fy_filter) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $detail_key . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($search_fields) {
        $config += array(
            'filters'        => array(
                $fy_filter('route', 'is equal to', '', 'route'),
                $fy_filter('status', 'is equal to', '', 'status'),
            ),
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($row_node)))),
        )),
    );
};

// The record's page: the not-found notice, the sent and new-account
// notices, the toolbar, the card with the details, the traveller's words
// and a note on the messages under it.
$fy_record = function ($w) use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $span, $link, $attr, $bind, $show_when, $edit_button, $fy_bound) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                        $heading('h2', $w['not_found_title'], 'h3 fw-semibold mt-3'),
                        $para($w['missing'], 'text-body-secondary mb-4', $bind('not_found')),
                        $link($w['list_title'], '{{page:' . $w['list_key'] . '}}', 'btn btn-primary rounded-pill px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($w['sent'], '', lang('Text')),
                        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                        $el('div', 'alert alert-info', lang('New Account'), array(
                            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                            $el('p', 'mb-1', lang('Sign-in Email'), array(
                                $span(lang('Email') . ': ', '', lang('Label')),
                                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                            )),
                            // The single-use set password link, for the traveller who
                            // sent the form while it is live; the sentence below covers
                            // a used or expired link.
                            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary', array(),
                                array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
                            $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
                        ), $show_when('has_new_account')),
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                            $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                $icon('bi-arrow-left'),
                                $span($w['list_title'], '', lang('Link Text')),
                            ), array('href' => '{{page:' . $w['list_key'] . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', $w['card'], array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($w['eyebrow'], 'pfy-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $w['title_sample'], 'pfy-display h3 mb-0', $bind($w['title_field'])),
                                    )),
                                    $span($w['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $w['details']),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $w['words_label'], array(
                            $para($w['words_label'], 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $fy_bound($w['words_sample'], $w['words_field'])),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($w['note'], '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a record: the page's own Comments widget, shown only
// to whoever may read the record above it. Oldest first, all on one page.
// No Reply, Share or follow box: the traveller is e-mailed every answer
// and the line every message.
$fy_messages = function ($page_key, $empty) use ($comment_thread) {
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

// The lines of a booking request, for its e-mails.
$bk_mail_request = function () use ($mail_box, $mail_line, $span, $bind, $fy_dates, $bk_sample_route, $bk_sample_fare, $journey_choices, $vehicle_choices, $cabin_choices) {
    return $mail_box(array(
        $mail_line(lang('Route'), array($span($bk_sample_route, '', lang('Route'), $bind('route')))),
        $mail_line(lang('Journey'), array($span($journey_choices[1][1], '', lang('Journey'), $bind('journey')))),
        $mail_line(lang('Dates'), $fy_dates()),
        $mail_line(lang('Preferred sailing'), array($span(lang('The morning sailing'), '', lang('Preferred sailing'), $bind('sailing')))),
        $mail_line(lang('Fare'), array($span($bk_sample_fare, '', lang('Fare'), $bind('fare')))),
        $mail_line(lang('Adults'), array($span('2', '', lang('Adults'), $bind('adults')))),
        $mail_line(lang('Children'), array($span('2', '', lang('Children'), $bind('children')))),
        $mail_line(lang('Vehicle'), array($span($vehicle_choices[1], '', lang('Vehicle'), $bind('vehicle')))),
        $mail_line(lang('Registration'), array($span('34 ABC 123', '', lang('Registration'), $bind('registration')))),
        $mail_line(lang('Vehicle length'), array($span(lang('4.5 metres'), '', lang('Vehicle length'), $bind('vehicle_length')))),
        $mail_line(lang('Cabin'), array($span($cabin_choices[0], '', lang('Cabin'), $bind('cabin')))),
        $mail_line(lang('Assistance'), array($span(lang('A wheelchair space on the car deck lift'), '', lang('Assistance'), $bind('assistance')))),
    ), lang('Booking Request'));
};
// The lines of a group enquiry, for its e-mails.
$gr_mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $gr_sample_route, $group_kind_choices, $gr_coach_choices) {
    return $mail_box(array(
        $mail_line(lang('Route'), array($span($gr_sample_route, '', lang('Route'), $bind('route')))),
        $mail_line(lang('Travel date'), array($span(lang('Travel date'), '', lang('Travel date'), $bind('travel_date')))),
        $mail_line(lang('Kind of group'), array($span($group_kind_choices[0], '', lang('Kind of group'), $bind('group_kind')))),
        $mail_line(lang('Name of the group'), array($span(lang('Pine Bay Primary School'), '', lang('Name of the group'), $bind('group_name')))),
        $mail_line(lang('Group size'), array($span('31', '', lang('Group size'), $bind('group_size')))),
        $mail_line(lang('Coach'), array($span($gr_coach_choices[0], '', lang('Coach'), $bind('coach')))),
        $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Group Enquiry'));
};
// The traveller's lines of a staff e-mail; $words is the field of what the
// traveller wrote and its sample.
$fy_mail_person = function ($who, $words_label, $words_sample, $words_field) use ($mail_box, $mail_line, $span, $bind, $fy_person) {
    return $mail_box(array(
        $mail_line($who, $fy_person()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line($words_label, array($span($words_sample, '', $words_label, $bind($words_field)))),
    ), $who);
};
// The reference code under a staff e-mail.
$fy_mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account a form opened for a traveller who had none, in the reply
// e-mail.
$fy_mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #cfe6e7;padding-top:16px'), $show_when('has_new_account')));
};

$fy_not_before_today = array('rules' => array('min_date' => 'today'));
$fy_count = function ($id, $name, $min, $max, $value) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'),
        array($attr('min', (string)$min), $attr('max', (string)$max), $attr('step', '1'), $attr('value', (string)$value)));
};
// The traveller's name, e-mail and phone, the same four fields on every form.
$fy_contact_fields = function ($prefix) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};
// The route, starting with what a link sends (?route=). Its prompt stays
// chosen for a value that is no route, so "All Routes" leaves the choice
// to the traveller.
$fy_route_field = function ($id) use ($field, $select, $route_titles) {
    return $field($id, lang('Route'), $select($id, 'route', lang('Choose a route…'), $route_titles, true, '', array('url_param' => 'route')));
};
// The end of a form: the consent with the privacy link, the CAPTCHA, the
// button and a line beside it.
$fy_form_end = function ($consent_id, $consent_text, $button, $line_text) use ($el, $agree, $captcha, $submit, $para, $loop) {
    return array(
        $agree($consent_id, 'consent', $consent_text, true),
        $captcha(),
        $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
            $submit($button, 'btn btn-primary btn-lg rounded-pill px-4'),
            $para($line_text, 'small text-body-secondary mb-0'),
        )),
        $loop(array()),
    );
};

$booking_widgets = array(

    // The booking request: the fields are the page's form. The route and
    // the fare start with what a link sends (?route=, ?fare=). The Status
    // is the booking office's (office use only): every request starts as
    // Request received.
    'booking_form' => array(
        'page'   => 'booking_request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your crossing')),
            $fy_route_field('fy-bk-route'),
            $radios('fy-bk-journey', 'journey', lang('Journey'), $journey_choices, $journey_choices[0][0]),
            $row(array(
                $col(array($field('fy-bk-out', lang('Outward date'), $input('fy-bk-out', 'date', 'outward_date', 'off', true, $fy_not_before_today))), '6'),
                $col(array($field('fy-bk-back', lang('Return date'), $input('fy-bk-back', 'date', 'return_date', 'off', false, $fy_not_before_today),
                    'mb-3', lang('For a return crossing only.'))), '6'),
            ), '3'),
            $field('fy-bk-sailing', lang('Preferred sailing'), $input('fy-bk-sailing', 'text', 'sailing', 'off', false), 'mb-3',
                lang('In words, for example the morning sailing or the 15:00 from Pine Bay; the timetables list every sailing.')),
            $form_step(2, lang('Who and what is travelling')),
            $row(array(
                $col(array($field('fy-bk-adults', lang('Adults'), $fy_count('fy-bk-adults', 'adults', 1, 50, 1), 'mb-3', lang('Ages 12 and over, the driver included.'))), '6'),
                $col(array($field('fy-bk-children', lang('Children'), $fy_count('fy-bk-children', 'children', 0, 50, 0), 'mb-3', lang('Ages 4 to 11; under 4 travel free.'))), '6'),
                $col(array($field('fy-bk-fare', lang('Fare'), $select('fy-bk-fare', 'fare', lang('Not sure yet'), $fare_titles, false, '', array('url_param' => 'fare')),
                    'mb-3', lang('The fare you looked at, if any; the booking office confirms the fare.'))), '6'),
                $col(array($field('fy-bk-vehicle', lang('Vehicle'), $select('fy-bk-vehicle', 'vehicle', '', $vehicle_choices, false, $vehicle_choices[0]))), '6'),
                $col(array($field('fy-bk-registration', lang('Registration'), $input('fy-bk-registration', 'text', 'registration', 'off', false))), '6'),
                $col(array($field('fy-bk-length', lang('Vehicle length'), $input('fy-bk-length', 'text', 'vehicle_length', 'off', false), 'mb-3',
                    sprintf(lang('In words, for example %s.'), lang('4.5 metres')))), '6'),
                $col(array($field('fy-bk-cabin', lang('Cabin'), $select('fy-bk-cabin', 'cabin', '', $cabin_choices, false, $cabin_choices[0]), 'mb-3',
                    lang('Cabins are on the long crossing only.'))), '6'),
            ), '3'),
            $field('fy-bk-assistance', lang('Assistance'), $input('fy-bk-assistance', 'text', 'assistance', 'off', false), 'mb-3',
                lang('A wheelchair, help with boarding or a pet travelling with you.')),
            $field('fy-bk-notes', lang('Notes'), $textarea('fy-bk-notes', 'notes', 3, false), 'mb-3',
                lang('Anything the booking office should know: a tall vehicle, a roof box or a late arrival at the port.')),
            $form_step(3, lang('How to reach you')),
            $fy_contact_fields('fy-bk'),
            $field('fy-bk-status', lang('Status'), $select('fy-bk-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
        ), $fy_form_end('fy-bk-consent',
            lang('I agree that the ferry line keeps these details to answer my request and keep my place on board, as its privacy notice explains.'),
            lang('Send the Request'), lang('The booking office answers within a day. Nothing is paid now.')))),
    ),

    // One booking request: the request, its status and what the traveller
    // wrote; the booking office's answers (the sailing confirmed among
    // them) follow as comments.
    'booking_detail' => $fy_detail_config('booking_view', 'booking_request', $bk_missing, 'submitter_only') + array(
        'tree' => $fy_record(array(
            'not_found_title' => lang('Booking request not found'),
            'missing'         => $bk_missing,
            'list_title'      => lang('My Bookings'),
            'list_key'        => 'my_bookings',
            'sent'            => lang('Thank you, your request has reached us. The booking office answers here within a day with the sailing it confirms; this page shows the status, and you can write to us below.'),
            'card'            => lang('Request Card'),
            'eyebrow'         => lang('Booking Request'),
            'title_sample'    => $bk_sample_route,
            'title_field'     => 'route',
            'status'          => $booking_statuses[0],
            'details'         => array_merge(
                $fy_detail(lang('Journey'), '', lang('Journey'), $fy_bound($journey_choices[1][1], 'journey')),
                $fy_detail(lang('Dates'), '', lang('Dates'), $fy_dates(), true),
                $fy_detail(lang('Preferred sailing'), 'pg-hide-if-empty', lang('Preferred sailing'), $fy_bound(lang('The morning sailing'), 'sailing')),
                $fy_detail(lang('Fare'), 'pg-hide-if-empty', lang('Fare'), $fy_bound($bk_sample_fare, 'fare')),
                $fy_detail(lang('Adults'), '', lang('Adults'), $fy_bound('2', 'adults')),
                $fy_detail(lang('Children'), '', lang('Children'), $fy_bound('2', 'children')),
                $fy_detail(lang('Vehicle'), '', lang('Vehicle'), $fy_bound($vehicle_choices[1], 'vehicle')),
                $fy_detail(lang('Registration'), 'pg-hide-if-empty', lang('Registration'), $fy_bound('34 ABC 123', 'registration')),
                $fy_detail(lang('Vehicle length'), 'pg-hide-if-empty', lang('Vehicle length'), $fy_bound(lang('4.5 metres'), 'vehicle_length')),
                $fy_detail(lang('Cabin'), '', lang('Cabin'), $fy_bound($cabin_choices[0], 'cabin')),
                $fy_detail(lang('Assistance'), 'pg-hide-if-empty', lang('Assistance'), $fy_bound(lang('A wheelchair space on the car deck lift'), 'assistance')),
                $fy_detail(lang('Traveller'), '', lang('Traveller'), $fy_person(), true),
                $fy_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $fy_bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('Notes'),
            'words_sample'    => $bk_sample_notes,
            'words_field'     => 'notes',
            'note'            => lang('Another day, another sailing or something to add? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'booking_messages' => $fy_messages('booking_view',
        lang('No messages yet. The sailing the booking office confirms comes here; you can write to us about your crossing too.')),

    'my_bookings_list' => $fy_list('my_bookings', 'booking_request', 'booking_view', $bk_row(),
        lang('No booking requests yet. Tell us your route and your day.')),

    'staff_bookings_list' => $fy_list('staff_bookings', 'booking_request', 'booking_view', $bk_row(true),
        lang('No booking request matches these choices.'),
        array('first_name', 'last_name', 'email', 'route', 'vehicle', 'registration', 'status'),
        lang('Search by traveller, e-mail, route, vehicle, registration or status...')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_booking_admin' => $fy_detail_config('email_booking_new', 'booking_request', lang('The ferry booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new ferry booking request')),
            $mail_para(lang('It came from the booking request form. Answer under the request on its page with the sailing you confirm and the fare, and set the status; what you write there reaches the traveller by e-mail.')),
            $bk_mail_request(),
            $fy_mail_person(lang('Traveller'), lang('Notes'), $bk_sample_notes, 'notes'),
            $mail_button(lang('Open the request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $fy_mail_reference(),
        )),
    ),
    'email_booking_client' => $fy_detail_config('email_booking_received', 'booking_request', lang('The ferry booking request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your ferry booking request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The booking office looks at your route, your day and who is travelling, and answers within a day with the sailing it confirms and the fare, on the request\'s page and by e-mail. Nothing is paid now.')),
            $bk_mail_request(),
            $mail_para(lang('Another day or a question? Write to us on the request\'s page; everything about your crossing stays there.')),
            $mail_button(lang('Open my request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $fy_mail_new_account(),
        )),
    ),

    // The group enquiry: the fields are the page's form. The route starts
    // with what a link sends (?route=). The Status is the group desk's
    // (office use only): every enquiry starts as Enquiry received.
    'group_form' => array(
        'page'   => 'group_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your group')),
            $fy_route_field('fy-gr-route'),
            $row(array(
                $col(array($field('fy-gr-date', lang('Travel date'), $input('fy-gr-date', 'date', 'travel_date', 'off', true, $fy_not_before_today))), '6'),
                $col(array($field('fy-gr-size', lang('Group size'), $fy_count('fy-gr-size', 'group_size', 10, 500, 20), 'mb-3', lang('Everyone travelling, the leaders included.'))), '6'),
                $col(array($field('fy-gr-kind', lang('Kind of group'), $select('fy-gr-kind', 'group_kind', lang('Choose one…'), $group_kind_choices, true))), '6'),
                $col(array($field('fy-gr-coach', lang('Coach'), $select('fy-gr-coach', 'coach', '', $gr_coach_choices, false, $gr_coach_choices[0]))), '6'),
            ), '3'),
            $field('fy-gr-name', lang('Name of the group'), $input('fy-gr-name', 'text', 'group_name', 'organization', false)),
            $field('fy-gr-details', lang('About your trip'), $textarea('fy-gr-details', 'details', 4, false), 'mb-3',
                lang('Out and back on the same day, the sailings you have in mind, wheelchair users in the group or anything we should know.')),
            $form_step(2, lang('How to reach you')),
            $radios('fy-gr-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('E-mail')),
            $fy_contact_fields('fy-gr'),
            $field('fy-gr-status', lang('Status'), $select('fy-gr-status', 'status', '', $group_statuses, false, $group_statuses[0], array('office_use_only' => true))),
        ), $fy_form_end('fy-gr-consent',
            lang('I agree that the ferry line keeps these details to answer my group enquiry, as its privacy notice explains.'),
            lang('Send the Enquiry'), lang('The group desk answers within two working days.')))),
    ),

    // One group enquiry: what was asked, its status and what the organiser
    // wrote; the offer and the messages follow as comments.
    'group_detail' => $fy_detail_config('group_view', 'group_enquiry', $gr_missing, 'submitter_only') + array(
        'tree' => $fy_record(array(
            'not_found_title' => lang('Group enquiry not found'),
            'missing'         => $gr_missing,
            'list_title'      => lang('My Group Enquiries'),
            'list_key'        => 'my_groups',
            'sent'            => lang('Thank you, your group enquiry has reached us. The group desk answers within two working days with an offer; this page shows the status, and you can write to us below.'),
            'card'            => lang('Enquiry Card'),
            'eyebrow'         => lang('Group Enquiry'),
            'title_sample'    => $gr_sample_route,
            'title_field'     => 'route',
            'status'          => $group_statuses[0],
            'details'         => array_merge(
                $fy_detail(lang('Travel date'), '', lang('Travel date'), $fy_bound(lang('Travel date'), 'travel_date')),
                $fy_detail(lang('Kind of group'), '', lang('Kind of group'), $fy_bound($group_kind_choices[0], 'group_kind')),
                $fy_detail(lang('Name of the group'), 'pg-hide-if-empty', lang('Name of the group'), $fy_bound(lang('Pine Bay Primary School'), 'group_name')),
                $fy_detail(lang('Group size'), '', lang('Group size'), $fy_bound('31', 'group_size')),
                $fy_detail(lang('Coach'), '', lang('Coach'), $fy_bound($gr_coach_choices[0], 'coach')),
                $fy_detail(lang('Best way to reach you'), '', lang('Contact by'), $fy_bound(lang('E-mail'), 'contact_method')),
                $fy_detail(lang('Organiser'), '', lang('Organiser'), $fy_person(), true),
                $fy_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $fy_bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('About your trip'),
            'words_sample'    => $gr_sample_note,
            'words_field'     => 'details',
            'note'            => lang('Another day, more travellers or a question about the offer? Write to us below; the offer comes here too, and every answer by e-mail.'),
        )),
    ),

    'group_messages' => $fy_messages('group_view',
        lang('No messages yet. The group desk\'s offer comes here; you can write to us about your trip too.')),

    'my_groups_list' => $fy_list('my_groups', 'group_enquiry', 'group_view', $gr_row(),
        lang('No group enquiries yet. Tell us your route, your day and how many you are.')),

    'staff_groups_list' => $fy_list('staff_groups', 'group_enquiry', 'group_view', $gr_row(true),
        lang('No group enquiry matches these choices.'),
        array('first_name', 'last_name', 'email', 'route', 'group_kind', 'group_name', 'status'),
        lang('Search by organiser, e-mail, route, kind of group, group name or status...')),

    'email_group_admin' => $fy_detail_config('email_group_new', 'group_enquiry', lang('The group enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new group enquiry')),
            $mail_para(lang('It came from the group enquiry form. Check the route, the day and the group size, send the offer under the enquiry and set its status; what you write there reaches the organiser by e-mail.')),
            $gr_mail_enquiry(),
            $fy_mail_person(lang('Organiser'), lang('About your trip'), $gr_sample_note, 'details'),
            $mail_button(lang('Open the enquiry'), '{{page:group_view}}?r=^^reference_code^^'),
            $fy_mail_reference(),
        )),
    ),
    'email_group_client' => $fy_detail_config('email_group_received', 'group_enquiry', lang('The group enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your group enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you for planning your group trip with us. The group desk answers within two working days with an offer for the route and the day, on the enquiry\'s page and by e-mail.')),
            $gr_mail_enquiry(),
            $mail_para(lang('A change or a question? Write to us on the enquiry\'s page; everything about your trip stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:group_view}}?r=^^reference_code^^'),
            $fy_mail_new_account(),
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
