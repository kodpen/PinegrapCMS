<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Coworking", its requests: the tour request, the
 * request's own page with its messages, My Tours and the staff's list; the
 * office enquiry, its page and the staff's list; the privacy page the
 * consent boxes and the booking widget link; the e-mails of both forms and
 * the new message e-mail every thread of the site sends, and the widgets
 * that draw them. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A tour is a request, never a held slot: a record of the Tour Requests
 * form. The front desk answers each request and sets its office-use-only
 * Status (Tour booked, Visited, Membership started); its answers are the
 * page's comments, mailed to the visitor and to the space. A visitor
 * without an account gets one (auto-registration), so the request's page
 * and My Tours show it at once.
 *
 * The tour's plan starts with what a link sends (?plan=: a plan's title,
 * the free trial day or a private office, as parts.php writes them); the
 * office enquiry's office with ?space= (an office's name).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$request_pages = array();

// The offices an enquiry can name: the spaces of the private office kind.
$office_names = array();
foreach ($spaces as $s) {
    if ($s['kind'] === 'office') $office_names[] = $s['name'];
}

// The sample requests the canvas shows. The date is words, never a date:
// a fixed day would read as a past week a year later.
$sample_tour_plan = $tour_choices[0];
$sample_tour_date = lang('Tour date');
$sample_tour_time = $tour_times[1];
$sample_work = $work_choices[1];
$sample_tour_notes = lang('I work from home as a developer and would like to see the quiet library and try a day at a hot desk before I choose a plan.');
$sample_office = $office_names[0];
$sample_team = $team_sizes[1];
$sample_start = $start_choices[1];
$sample_company = lang('Northwind Studio');
$sample_enquiry_notes = lang('We are a team of three designers and need an office from next month, with a meeting room now and then for clients.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the front
// desk's hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $check_list, $opening_hours) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    $hours = array();
    foreach ($opening_hours as $h) $hours[] = $h['days'] . ': ' . $h['hours'];
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pcw-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'pcw-display h5 mb-3'),
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

// The tour request. What is sent opens the request's own page.
$request_pages[] = array(
    'key'              => 'tour',
    'name'             => lang('book-a-tour'),
    'folder'           => 'public',
    'title'            => lang('Book a Tour'),
    'meta_description' => lang('Choose a plan or the free trial day and a time: the front desk shows you round and you stay to work for the rest of the day.'),
    'form'             => array(
        'form_name'            => lang('Tour Requests'),
        'confirmation_message' => lang('Thank you, your tour request has reached us. The front desk confirms the time within a few hours.'),
        'confirmation_page_id' => '{{tab:tour_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New tour request: ^^plan^^'),
        'notify_page_id'       => '{{tab:email_tour_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your tour request'),
        'confirm_page_id'      => '{{tab:email_tour_received}}',
    ),
    'tree'             => $request_page(lang('Book a Tour'),
        lang('Choose a plan or the free trial day and a time that suits you. The front desk shows you round, and your first day at a hot desk is on us.'),
        'tour_form',
        array_merge($request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The front desk confirms the time there within a few hours, or offers another one.'),
            lang('Bring your laptop: after the tour you can stay and work for the rest of the day.'),
            lang('Cannot come? Write to us on the request\'s page and we find another time.'),
        ), lang('Compare the plans'), '{{page:plans}}'), array(
            // A team looking for its own room asks about an office instead.
            $el('div', 'p-4 rounded-3 border mt-4', lang('Private Offices'), array(
                $heading('h2', lang('Looking for an office for your team?'), 'pcw-display h5 mb-2'),
                $para(lang('Tell us how many you are and when you want to move in; we send an offer for a furnished office.'), 'small text-body-secondary mb-3'),
                $link(lang('Ask About an Office'), '{{page:office_enquiry}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
            // A meeting for a few hours needs no tour: the room is booked at
            // a free time.
            $el('div', 'p-4 rounded-3 border mt-4', lang('Meeting Rooms'), array(
                $heading('h2', lang('Only need a meeting room?'), 'pcw-display h5 mb-2'),
                $para(lang('Book a meeting room by the hour at a free time.'), 'small text-body-secondary mb-3'),
                $link(lang('Book a meeting room'), '{{page:book_online}}', 'btn btn-sm btn-outline-primary px-3'),
            )),
        ))),
);

// One tour request: the request, its status and the messages.
$request_pages[] = $request_view('tour_view', lang('tour-request'), lang('Tour Request'),
    'tour_detail', 'tour_messages', lang('There is a new message about your tour'));

// My Tours: the visitor's own requests (for the staff, everybody's).
$request_pages[] = array(
    'key'     => 'my_tours',
    'name'    => lang('my-tours'),
    'folder'  => 'registration',
    'title'   => lang('My Tours'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Tours'), lang('Your tour requests, their status and the front desk\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_tours_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Bringing a colleague?'), '', lang('Text')),
                            )),
                            $para(lang('Book another tour; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Tour'), '{{page:tour}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$request_pages[] = $staff_list_page('staff_tours', lang('all-tour-requests'), lang('Tour Requests'),
    lang('Every tour request, newest first: confirm the time, set the status and write to the visitor under each.'),
    'staff_tours_list');

// The office enquiry: the office, the team's size, when it moves in. What
// is sent opens the enquiry's own page.
$request_pages[] = array(
    'key'              => 'office_enquiry',
    'name'             => lang('office-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Ask About an Office'),
    'meta_description' => lang('Tell us how many you are and when you want to move in: we send an offer for a furnished private office within two working days.'),
    'form'             => array(
        'form_name'            => lang('Office Enquiries'),
        'confirmation_message' => lang('Thank you, your office enquiry has reached us. We send you an offer within two working days.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New office enquiry: ^^team_size^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your office enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $request_page(lang('Ask About an Office'),
        lang('Tell us how many you are and when you want to move in. We send an offer for a furnished office with a door that locks; nothing is signed now.'),
        'enquiry_form',
        $request_aside(array(
            lang('Your enquiry opens its own page, and an account for you if you have none.'),
            lang('We send you an offer there within two working days, with the offices that fit your team.'),
            lang('Come and see the office before you decide; we keep it for you for a week.'),
            lang('Nothing is signed now: the office is yours from the day you choose.'),
        ), lang('See the private offices'), '{{page:spaces}}?' . $query(array('kind' => $kinds['office']['title'])))),
);

// One office enquiry: the enquiry, its status and the messages.
$request_pages[] = $request_view('enquiry_view', lang('office-enquiry-request'), lang('Office Enquiry'),
    'enquiry_detail', 'enquiry_messages', lang('There is a new message about your office enquiry'));

$request_pages[] = $staff_list_page('staff_enquiries', lang('all-office-enquiries'), lang('Office Enquiries'),
    lang('Every office enquiry, newest first: send the offer, set the status and write to the team under each.'),
    'staff_enquiries_list');

// The privacy notice the consent boxes, the booking widget and the footer
// link to: sample text with a note to adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the coworking space keeps about you when you book a tour, a room or an event or ask about an office, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the coworking space keeps about you when you book a tour, a room or an event or ask about an office, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pcw-display h4 mb-3'),
                $para(lang('When you book a tour, a meeting room or an event, ask about an office, buy a membership, join the newsletter or open an account, we keep your name, your contact details, your company and what you tell us about your work, to answer you, keep your booking and let you into the building.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('cw-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the front desk receives and the reply to the sender of
// each form, and the new message e-mail.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_tour_new',
    'name'  => lang('email-new-tour-request'),
    'title' => lang('New Tour Request E-mail'),
    'tree'  => $mail_page(array($widget('email_tour_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_tour_received',
    'name'  => lang('email-tour-request-received'),
    'title' => lang('Tour Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_tour_client'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-office-enquiry'),
    'title' => lang('New Office Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-office-enquiry-received'),
    'title' => lang('Office Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));
// A new message or reply: sent to the sender of a tour request, an office
// enquiry or an event sign-up, the sender of a conversation and the
// visitors watching a page. Its Comments widget draws the comment in the
// card of mail.php; the System region after it keeps the fixed comment for
// an e-mail the widget does not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your tour, your office enquiry, your event sign-up, a conversation or a blog post. You can read it below and answer it on the page.')),
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
// When the tour is: the date, then the time.
$tour_when = function () use ($span, $bind, $sample_tour_date, $sample_tour_time) {
    return array(
        $span($sample_tour_date, '', lang('Date'), $bind('tour_date')),
        $span(' · ', '', lang('Text')),
        $span($sample_tour_time, '', lang('Time'), $bind('tour_time')),
    );
};

// A request in a list, linked to its page: its title and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the visitor's own adds the messages.
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
$tour_row = function ($staff = false) use ($el, $icon, $request_row, $tour_when, $sample_tour_plan, $tour_statuses) {
    return $request_row($staff, $sample_tour_plan, 'plan', lang('Plan'), $tour_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('When'), array_merge(array($icon('bi-calendar-event')), $tour_when())),
    ));
};
$enquiry_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_team, $sample_start, $enquiry_statuses) {
    return $request_row($staff, $sample_team, 'team_size', lang('Team Size'), $enquiry_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Move-in'), array($icon('bi-calendar-event'), $span($sample_start, '', lang('Move-in'), $bind('start')))),
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

// The lines of a tour request and of an office enquiry, for their e-mails.
$mail_tour = function () use ($mail_box, $mail_line, $span, $bind, $tour_when, $sample_tour_plan, $sample_work, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Plan'), array($span($sample_tour_plan, '', lang('Plan'), $bind('plan')))),
        $mail_line(lang('When'), $tour_when()),
        $mail_line(lang('Work'), array($span($sample_work, '', lang('Work'), $bind('work')))),
        $mail_line(lang('Reply by'), array($span($contact_choices[0][1], '', lang('Reply by'), $bind('reply_by')))),
    ), lang('Tour Request'));
};
$mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $sample_office, $sample_team, $sample_start, $sample_company, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Office'), array($span($sample_office, '', lang('Office'), $bind('space')))),
        $mail_line(lang('Team Size'), array($span($sample_team, '', lang('Team Size'), $bind('team_size')))),
        $mail_line(lang('Move-in'), array($span($sample_start, '', lang('Move-in'), $bind('start')))),
        $mail_line(lang('Company'), array($span($sample_company, '', lang('Company'), $bind('company')))),
        $mail_line(lang('Reply by'), array($span($contact_choices[0][1], '', lang('Reply by'), $bind('reply_by')))),
    ), lang('Office Enquiry'));
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
// A list of requests: the visitor's own (the staff see them all), or the
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
                                        $para($eyebrow, 'pcw-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'pcw-display h3 mb-0', $bind($title_field)),
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

$tour_missing = lang('This tour request could not be found, or it is not yours to see.');
$enquiry_missing = lang('This office enquiry could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));

$request_widgets = array(

    // The tour request: the fields are the page's form. The plan starts
    // with what a link sends (?plan=). The Status is the front desk's
    // (office use only): every request starts as Request received.
    'tour_form' => array(
        'page'   => 'tour',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your tour')),
            $field('cw-tr-plan', lang('Plan'), $select('cw-tr-plan', 'plan', lang('Choose a plan…'), $tour_choices, true, '', array('url_param' => 'plan')), 'mb-3',
                lang('Not sure yet? Start with the free trial day.')),
            $row(array(
                $col(array($field('cw-tr-date', lang('Date'), $input('cw-tr-date', 'date', 'tour_date', 'off', true, $not_before_today))), '6'),
                $col(array($field('cw-tr-time', lang('Time'), $select('cw-tr-time', 'tour_time', lang('Choose a time…'), $tour_times, true))), '6'),
            ), '3'),
            $field('cw-tr-work', lang('Work'), $select('cw-tr-work', 'work', lang('What do you do?'), $work_choices, false)),
            $field('cw-tr-notes', lang('Notes'), $textarea('cw-tr-notes', 'notes', 3, false), 'mb-3',
                lang('What you would like to see, a colleague coming with you, the days you would come.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('cw-tr'),
            $radios('cw-tr-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('cw-tr-status', lang('Status'), $select('cw-tr-status', 'status', '', $tour_statuses, false, $tour_statuses[0], array('office_use_only' => true))),
            $agree('cw-tr-consent', 'consent', lang('I agree that the coworking space keeps these details to answer my request and show me round, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The front desk confirms the time within a few hours.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One tour request; the front desk's answers follow as comments.
    'tour_detail' => $detail_widget('tour_view', 'tour', $tour_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Tours'), 'my_tours', $tour_missing, lang('Tour request not found'),
            lang('Tour Request'), $sample_tour_plan, 'plan', $tour_statuses[0],
            lang('Thank you, your request has reached us. The front desk confirms the time here within a few hours; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('When'), '', lang('When'), $tour_when(), true),
                $detail(lang('Work'), '', lang('Work'), $bound($sample_work, 'work')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $member_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_tour_notes,
            lang('Another time, a question or cannot come? Write to us below; we answer here and by e-mail.')),
    ),

    'tour_messages' => $request_messages('tour_view',
        lang('No messages yet. The front desk\'s answer comes here; you can write to us about your tour too.')),

    'my_tours_list' => $request_list('my_tours', 'tour', 'tour_view', $tour_row, array(), '',
        lang('No tour requests yet. Book a tour and your first day is free.')),

    'staff_tours_list' => $request_list('staff_tours', 'tour', 'tour_view', $tour_row,
        array('first_name', 'last_name', 'email', 'plan', 'tour_date', 'status'),
        lang('Search by name, e-mail, plan, date or status...'), lang('No tour requests yet.')),

    // The office enquiry: the office starts with what a link sends
    // (?space=); left empty, the front desk offers what fits the team.
    'enquiry_form' => array(
        'page'   => 'office_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your team')),
            $row(array(
                $col(array($field('cw-oe-team', lang('Team Size'), $select('cw-oe-team', 'team_size', lang('How many are you?'), $team_sizes, true))), '6'),
                $col(array($field('cw-oe-start', lang('Move-in'), $select('cw-oe-start', 'start', lang('When do you want to move in?'), $start_choices, true))), '6'),
            ), '3'),
            $field('cw-oe-space', lang('Office'), $select('cw-oe-space', 'space', lang('Any office that fits'), $office_names, false, '', array('url_param' => 'space')), 'mb-3',
                lang('Leave it open and we offer the offices that fit your team.')),
            $field('cw-oe-company', lang('Company'), $input('cw-oe-company', 'text', 'company', 'organization', false, array('contact_field' => 'company'))),
            $field('cw-oe-work', lang('Work'), $select('cw-oe-work', 'work', lang('What do you do?'), $work_choices, false)),
            $field('cw-oe-notes', lang('Notes'), $textarea('cw-oe-notes', 'notes', 3, false), 'mb-3',
                lang('Meeting rooms, storage, the hours you work, anything your team needs.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('cw-oe'),
            $radios('cw-oe-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('cw-oe-status', lang('Status'), $select('cw-oe-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('cw-oe-consent', 'consent', lang('I agree that the coworking space keeps these details to answer my enquiry and send an offer, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg px-4'),
                $para(lang('We send an offer within two working days. Nothing is signed now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    'enquiry_detail' => $detail_widget('enquiry_view', 'office_enquiry', $enquiry_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('See the private offices'), 'spaces', $enquiry_missing, lang('Office enquiry not found'),
            lang('Office Enquiry'), $sample_team, 'team_size', $enquiry_statuses[0],
            lang('Thank you, your enquiry has reached us. We send you an offer here within two working days; you can write to us below.'),
            array_merge(
                $detail(lang('Office'), '', lang('Office'), $bound($sample_office, 'space')),
                $detail(lang('Move-in'), '', lang('Move-in'), $bound($sample_start, 'start')),
                $detail(lang('Company'), '', lang('Company'), $bound($sample_company, 'company')),
                $detail(lang('Work'), '', lang('Work'), $bound($sample_work, 'work')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $member_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_enquiry_notes,
            lang('Another office, another date or a question? Write to us below; we answer here and by e-mail.')),
    ),

    'enquiry_messages' => $request_messages('enquiry_view',
        lang('No messages yet. Our offer comes here; you can write to us about the office too.')),

    'staff_enquiries_list' => $request_list('staff_enquiries', 'office_enquiry', 'enquiry_view', $enquiry_row,
        array('first_name', 'last_name', 'email', 'company', 'team_size', 'status'),
        lang('Search by name, e-mail, company, team size or status...'), lang('No office enquiries yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_tour_admin' => $detail_widget('email_tour_new', 'tour', lang('The tour request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new tour request')),
            $mail_para(lang('It came from the tour form. Confirm the time under the request on its page, or offer another one, and set the status; what you write there reaches the visitor by e-mail.')),
            $mail_tour(),
            $mail_sender($sample_tour_notes),
            $mail_button(lang('Open the request'), '{{page:tour_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_tour_client' => $detail_widget('email_tour_received', 'tour', lang('The tour request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your tour request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The front desk confirms the time within a few hours, on the request\'s page and by e-mail. Bring your laptop: after the tour you can stay and work for the rest of the day.')),
            $mail_tour(),
            $mail_para(lang('Another time or cannot come? Write to us on the request\'s page and we find another time.')),
            $mail_button(lang('Open my request'), '{{page:tour_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_enquiry_admin' => $detail_widget('email_enquiry_new', 'office_enquiry', lang('The office enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new office enquiry')),
            $mail_para(lang('It came from the office enquiry form. Send the offer under the enquiry on its page and set the status; what you write there reaches the team by e-mail.')),
            $mail_enquiry(),
            $mail_sender($sample_enquiry_notes),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $detail_widget('email_enquiry_received', 'office_enquiry', lang('The office enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your office enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We send you an offer within two working days, with the offices that fit your team. Nothing is signed now.')),
            $mail_enquiry(),
            $mail_para(lang('A question before then? Write to us on the enquiry\'s page; everything about your office stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
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

return array('pages' => $request_pages, 'widgets' => $request_widgets);
