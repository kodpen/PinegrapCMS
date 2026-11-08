<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Planetarium", its school and group visits: the visit
 * request, the request's own page with its messages, My Visits and the
 * staff's list, the privacy notice the consent boxes link to, and the
 * e-mails of a request with the reply e-mail every message is sent with.
 * Included after parts.php, cards.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A visit is a request, never a booking: a record of the Visit Requests
 * form. The education team answers each request and sets its
 * office-use-only Status (Received, Confirmed, Waiting List, Cancelled);
 * its answers are the page's comments, mailed to the teacher and to the
 * planetarium. A teacher without an account gets one (auto-registration),
 * so the request's page and My Visits show it at once. The visit and the
 * grade start with what a visit card or a grade link sends (?visit=,
 * ?grade=, each in its list's own words).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$visit_pages = array();

// The sample request the canvas shows.
$sample_visit = $visits['dome']['title'];
$sample_grade = $grades['primary'];
$sample_pupils = $pupil_counts[1];
$sample_show = $shows['solar_tour']['title'];
$sample_access = lang('One pupil uses a wheelchair.');
$sample_visit_notes = lang('Two classes of the same year. We are studying the planets this term and would like the workshop to follow on from the show.');
$sample_school = lang('Riverside Primary School');

// ── Request pieces ──────────────────────────────────────────────────────

// The side column of the request page: what happens next, then the
// opening hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $hours_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-primary-subtle text-primary-emphasis mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'ppm-display h5 mb-3'),
            $el('ol', 'small ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'ppm-display h5 mb-3'),
            $hours_list('mb-3'),
            $link($link_text, $link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
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

// The status of a request as a pill.
$visit_status_pill = function ($class = '') use ($span, $bind, $visit_statuses) {
    return $span($visit_statuses[0], trim('badge rounded-pill bg-primary-subtle text-primary-emphasis ' . $class), lang('Status'), $bind('status'));
};

// A request in a list, linked to its page: its visit and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the teacher's own adds the messages.
$visit_row = function ($staff = false) use ($el, $span, $icon, $bind, $visitor_name, $visit_status_pill, $sample_visit, $sample_grade, $sample_pupils) {
    $items = array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Visit date'), array(
            $icon('bi-calendar-event'), $span(lang('Visit date'), 'ppm-time', lang('Visit date'), $bind('visit_date')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Grade'), array(
            $icon('bi-mortarboard'), $span($sample_grade, '', lang('Grade'), $bind('grade')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Pupils'), array(
            $icon('bi-people'), $span($sample_pupils, '', lang('Pupils'), $bind('pupils')),
        )),
    );
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')),
        ));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($visitor_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_visit, 'text-body-secondary fw-normal', lang('Kind of visit'), $bind('visit')),
        )));
    } else {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array(
            $icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments')),
        ));
        $title = $span($sample_visit, 'fw-semibold', lang('Kind of visit'), $bind('visit'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $visit_status_pill(),
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

// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the request page's
// widget (the sender's own, and the staff's).
$detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:visit_request}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};

// A list of requests: the teacher's own (the staff see them all), or the
// staff's with a search.
$visit_list = function ($page_key, $search_fields, $search_label, $empty) use ($widget_root, $el, $loop, $visit_row) {
    $staff = $search_fields !== array();
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:visit_request}}',
        'detail_page_id'      => '{{tab:visit_view}}',
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($visit_row($staff))))),
        )),
    );
};

// A reference code line under a staff e-mail.
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account a form opened for a sender who had none, in the e-mail.
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
// The sender's lines of the staff e-mail.
$mail_sender = function () use ($mail_box, $mail_line, $span, $bind, $visitor_name, $sample_school, $sample_access, $sample_visit_notes) {
    return $mail_box(array(
        $mail_line(lang('Name'), $visitor_name()),
        $mail_line(lang('School or group'), array($span($sample_school, '', lang('School or group'), $bind('organisation')))),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Accessibility needs'), array($span($sample_access, '', lang('Accessibility needs'), $bind('accessibility')))),
        $mail_line(lang('Notes'), array($span($sample_visit_notes, '', lang('Notes'), $bind('notes')))),
    ), lang('Sender'));
};
// The request's visit, class and day, for its e-mails.
$mail_visit = function () use ($mail_box, $mail_line, $span, $bind, $sample_visit, $sample_grade, $sample_pupils, $sample_show) {
    return $mail_box(array(
        $mail_line(lang('Kind of visit'), array($span($sample_visit, '', lang('Kind of visit'), $bind('visit')))),
        $mail_line(lang('Grade'), array($span($sample_grade, '', lang('Grade'), $bind('grade')))),
        $mail_line(lang('Visit date'), array($span(lang('Visit date'), '', lang('Visit date'), $bind('visit_date')))),
        $mail_line(lang('Pupils'), array($span($sample_pupils, '', lang('Pupils'), $bind('pupils')))),
        $mail_line(lang('Show wanted'), array($span($sample_show, '', lang('Show wanted'), $bind('show_wanted')))),
    ), lang('Visit Request'));
};

$visit_missing = lang('This visit request could not be found, or it is not yours to see.');

// ── Pages ───────────────────────────────────────────────────────────────

// The school and group visit request, a day page: the kinds of visit,
// then the form beside the grade links. What is sent opens the request's
// own page.
$grade_link_nodes = array();
foreach ($grade_titles as $title) {
    $grade_link_nodes[] = $el('li', '', lang('Item'), array(
        $link($title, '{{page:visit_request}}?' . $query(array('grade' => $title)), 'link-primary fw-semibold text-decoration-none'),
    ));
}
$visit_pages[] = array(
    'key'              => 'visit_request',
    'name'             => lang('school-visits'),
    'folder'           => 'public',
    'title'            => lang('School Visits'),
    'meta_description' => lang('Ask for a dome show and workshop, an observatory day, a star party or teacher training: the education team writes back with a date.'),
    'form'             => array(
        'form_name'            => lang('Visit Requests'),
        'confirmation_message' => lang('Thank you, your visit request has reached the education team. They write to you within two working days.'),
        'confirmation_page_id' => '{{tab:visit_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New visit request: ^^visit^^'),
        'notify_page_id'       => '{{tab:email_visit_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your visit request'),
        'confirm_page_id'      => '{{tab:email_visit_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('School Visits'),
            lang('Choose a visit, tell us the grade, the number of pupils and the day that suits you. The education team writes back with a date and the price; nothing is paid now.'), array(), false),
        $el('section', 'py-5', lang('Kinds of Visit'), array(
            $container(array(
                $section_header(lang('Ways to visit together'), lang('Each one is planned with your class; the price is per pupil or per group.')),
                $rise($row(array_map($visit_card, array_keys($visits)), '4')),
            )),
        ), $searchable('plm-visit-kinds', lang('school trip, class visit, dome show, workshop, observatory day, star party, teacher training'))),
        $el('section', 'pb-5', lang('Request'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
                                $heading('h2', lang('Ask for a visit'), 'ppm-display h3 mb-4'),
                                $widget('visit_form'),
                            )),
                        )),
                    ), '', '8'),
                    $col(array_merge(array(
                        $el('div', 'p-4 rounded-3 border mb-4', lang('Grades'), array(
                            $heading('h2', lang('Which grade?'), 'ppm-display h5 mb-2'),
                            $para(lang('Start from your class; the form is filled in for you.'), 'small text-body-secondary mb-3'),
                            $el('ul', 'list-unstyled d-grid gap-2 small mb-0', lang('List'), $grade_link_nodes),
                        )),
                    ), $request_aside(array(
                        lang('Your request opens its own page, and an account for you if you have none.'),
                        lang('The education team writes to you there within two working days with a date.'),
                        lang('A school visit is paid on the day or by invoice to the school.'),
                        lang('Plans change? Write to us on the request\'s page.'),
                    ), lang('See the dome shows'), '{{page:shows}}')), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), array('id' => 'plm-visit-form')),
    )),
);

// One visit request: the request, its status and the messages.
$visit_pages[] = $request_view('visit_view', lang('visit-request'), lang('Visit Request'),
    'visit_detail', 'visit_messages', lang('There is a new message about your visit'));

// My Visits: the teacher's own requests (for the staff, everybody's).
$visit_pages[] = array(
    'key'     => 'my_visits',
    'name'    => lang('my-visits'),
    'folder'  => 'registration',
    'title'   => lang('My Visits'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Visits'), lang('Your school visit requests, their status and the education team\'s answers, newest first.'), array(), false),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_visits_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-primary-subtle text-primary-emphasis', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-stars'),
                                $span(lang('Another visit?'), '', lang('Text')),
                            )),
                            $para(lang('Choose a visit; your details are filled in for you.'), 'small mb-3'),
                            $link(lang('School Visits'), '{{page:visit_request}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The staff's list of every request.
$visit_pages[] = array(
    'key'     => 'staff_visits',
    'name'    => lang('all-visit-requests'),
    'folder'  => 'private',
    'title'   => lang('Visit Requests'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Visit Requests'), lang('Every school visit request, newest first: offer a date, set the status and write to the teacher under each.'), array(), false),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_visits_list'))),
        )),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$visit_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the planetarium keeps about you when you ask for a school visit, buy a ticket or a membership or open an account, and why.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the planetarium keeps about you when you ask for a school visit, buy a ticket or a membership or open an account, and why.'), array(), false),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'ppm-display h4 mb-3'),
                $para(lang('When you ask for a school visit, buy a ticket or a membership, join the newsletter or open an account, we keep your name, your contact details and what you tell us, such as the grade of your class and its needs, to answer you and plan your visit to the planetarium.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('plm-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the planetarium receives and the reply to the teacher.
$visit_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_visit_new',
    'name'  => lang('email-new-visit-request'),
    'title' => lang('New Visit Request E-mail'),
    'tree'  => $mail_page(array($widget('email_visit_admin'))),
));
$visit_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_visit_received',
    'name'  => lang('email-visit-request-received'),
    'title' => lang('Visit Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_visit_client'))),
));
// A new message or reply: sent to the sender of a request, the sender of a
// conversation and the visitors watching a page. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$visit_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your visit request, a conversation or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$visit_widgets = array(

    // The visit request: the fields are the page's form. The visit and the
    // grade start with what a visit card or a grade link sends (?visit=,
    // ?grade=). The Status is the education team's (office use only):
    // every request starts as Request received.
    'visit_form' => array(
        'page'   => 'visit_request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your visit')),
            $field('plm-vr-visit', lang('Kind of visit'), $select('plm-vr-visit', 'visit', lang('Choose a visit…'), $visit_choices, true, '', array('url_param' => 'visit'))),
            $row(array(
                $col(array($field('plm-vr-grade', lang('Grade'), $select('plm-vr-grade', 'grade', lang('Choose…'), $grade_titles, true, '', array('url_param' => 'grade')))), '6'),
                $col(array($field('plm-vr-date', lang('Visit date'), $input('plm-vr-date', 'date', 'visit_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('plm-vr-pupils', lang('Pupils'), $select('plm-vr-pupils', 'pupils', '', $pupil_counts, true, $pupil_counts[0]))), '6'),
                $col(array($field('plm-vr-show', lang('Show wanted'), $input('plm-vr-show', 'text', 'show_wanted', 'off', false))), '6'),
            ), '3'),
            $field('plm-vr-access', lang('Accessibility needs'), $textarea('plm-vr-access', 'accessibility', 2, false), 'mb-3',
                lang('Wheelchairs, a seat near the door, sensitivity to the dark dome: we plan the visit with you.')),
            $field('plm-vr-notes', lang('Notes'), $textarea('plm-vr-notes', 'notes', 3, false), 'mb-3',
                lang('What the class is studying, a topic for the workshop, your lunch plans.')),
            $form_step(2, lang('How to reach you')),
            $field('plm-vr-org', lang('School or group'), $input('plm-vr-org', 'text', 'organisation', 'organization', false)),
            $row(array(
                $col(array($field('plm-vr-first-name', lang('First Name'), $input('plm-vr-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('plm-vr-last-name', lang('Last Name'), $input('plm-vr-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('plm-vr-email', lang('Email'), $input('plm-vr-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('plm-vr-phone', lang('Phone'), $input('plm-vr-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $radios('plm-vr-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('plm-vr-status', lang('Status'), $select('plm-vr-status', 'status', '', $visit_statuses, false, $visit_statuses[0], array('office_use_only' => true))),
            $agree('plm-vr-consent', 'consent', lang('I agree that the planetarium keeps these details to plan our visit.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The education team writes to you within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One request; the education team's answers follow as comments.
    'visit_detail' => $detail_widget('visit_view', $visit_missing, 'submitter_only') + array(
        'tree' => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-calendar-x', 'display-5 text-body-secondary'),
                            $heading('h2', lang('Visit request not found'), 'h3 fw-semibold mt-3'),
                            $para($visit_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Visits'), '{{page:my_visits}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached the education team. They write to you here within two working days; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Visits'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_visits}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Request Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Visit Request'), 'small text-uppercase fw-semibold text-primary-emphasis mb-1'),
                                            $heading('h1', $sample_visit, 'ppm-display h3 mb-0', $bind('visit')),
                                        )),
                                        $visit_status_pill('fs-6'),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Grade'), '', lang('Grade'), $bound($sample_grade, 'grade')),
                                        $detail(lang('Visit date'), 'ppm-time', lang('Visit date'), $bound(lang('Visit date'), 'visit_date')),
                                        $detail(lang('Pupils'), '', lang('Pupils'), $bound($sample_pupils, 'pupils')),
                                        $detail(lang('Show wanted'), 'pg-hide-if-empty', lang('Show wanted'), $bound($sample_show, 'show_wanted')),
                                        $detail(lang('School or group'), 'pg-hide-if-empty', lang('School or group'), $bound($sample_school, 'organisation')),
                                        $detail(lang('Name'), '', lang('Name'), $visitor_name(), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-3', lang('Accessibility needs'), array(
                                $para(lang('Accessibility needs'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_access, 'accessibility')),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('Notes'), array(
                                $para(lang('Notes'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_visit_notes, 'notes')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another date, more pupils or a question? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the teacher is e-mailed
    // every answer and the planetarium every message.
    'visit_messages' => array(
        'page'   => 'visit_view',
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
            'empty'     => lang('No messages yet. The education team\'s answer comes here; you can write to us about the visit too.'),
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

    'my_visits_list' => $visit_list('my_visits', array(), '',
        lang('No visit requests yet. Choose a visit for your class.')),

    'staff_visits_list' => $visit_list('staff_visits',
        array('first_name', 'last_name', 'email', 'organisation', 'visit', 'grade', 'status'),
        lang('Search by name, e-mail, school, visit, grade or status...'), lang('No visit requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_visit_admin' => $detail_widget('email_visit_new', lang('The visit request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new visit request')),
            $mail_para(lang('It came from the school visits form. Offer a date under the request on its page and set the status; what you write there reaches the teacher by e-mail.')),
            $mail_visit(),
            $mail_sender(),
            $mail_button(lang('Open the request'), '{{page:visit_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_visit_client' => $detail_widget('email_visit_received', lang('The visit request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your visit request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The education team writes to you within two working days with a date, on the request\'s page and by e-mail.')),
            $mail_visit(),
            $mail_para(lang('Plans change? Write to us on the request\'s page; everything about the visit stays there.')),
            $mail_button(lang('Open my request'), '{{page:visit_view}}?r=^^reference_code^^'),
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

return array('pages' => $visit_pages, 'widgets' => $visit_widgets);
