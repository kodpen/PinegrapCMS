<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Architecture & Interiors Studio", its enquiries: the
 * project enquiry and the consultation request, each with its own page and
 * messages, the client's list and the staff's list; the privacy page the
 * consent boxes link, the e-mails of both forms and the new message e-mail
 * every thread of the site sends, and the widgets that draw them. Included
 * after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A project enquiry is a request, never a contract: a record of the
 * Project Enquiries form. A client without an account gets one
 * (auto-registration), so the enquiry's page and My Enquiries show it at
 * once. Its Status is an office-use-only field the studio changes in the
 * control panel (received, call booked, proposal sent, appointed,
 * declined); the call and the proposal are written under it as messages
 * (the page's comments), mailed to the client and to the studio. Drawings
 * and photos of the site are kept in the template's private folder: the
 * record's page links them for whoever may read the record.
 *
 * A consultation request is the smaller form: the service, where to meet
 * (the studio, a video call or the site) and the preferred days in words.
 * There is no calendar: the studio answers under the request with a time.
 *
 * Both forms start their service with what a link sends (?service=: the
 * service's title, as parts.php writes it on the service pages and the
 * project page).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$enquiry_pages = array();

// The sample enquiry and consultation the canvas shows. Sizes, starts and
// days are words, never a date.
$sample_service = $services['adaptive_reuse']['title'];
$sample_kind = $project_kinds[4];
$sample_location = $locations['istanbul'];
$sample_site = $site_choices[0];
$sample_size = lang('A two-storey warehouse of about 600 m²');
$sample_budget = $budget_choices[2];
$sample_start = $start_choices[1];
$sample_details = lang('We own an old tobacco warehouse by the water and would like to turn it into a café downstairs and three flats upstairs, keeping the brick walls and the timber roof.');
$sample_consult_service = $services['interiors']['title'];
$sample_place = $consultation_places[0];
$sample_days = lang('Tuesday or Thursday afternoon');
$sample_consult_details = lang('We have bought a flat with high ceilings and would like to talk about opening up the kitchen and a study corner.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a form page: what happens next, a reassurance box and
// the studio's hours.
$form_aside = function ($steps, $box_title, $box_items) use ($el, $heading, $para, $icon, $span, $check_list, $studio) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pai-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 border mb-4', $box_title, array(
            $heading('h2', $box_title, 'pai-display h5 mb-3'),
            $check_list($box_items, 'list-unstyled small mb-0'),
        )),
        $el('div', 'p-4 border', lang('Studio Hours'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-clock'),
                $span(lang('Studio Hours'), '', lang('Text')),
            )),
            $para($studio['hours'], 'small text-body-secondary mb-1'),
            $para($studio['phone'], 'small fw-semibold mb-0'),
        )),
    );
};

// A form page: the header, the form in a bordered box, the side column.
$form_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 border', lang('Form Card'), array($widget($widget_key))),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A record page: the request, then the messages under it.
$record_page_comments = function ($subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
        'attachments' => true, 'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject, 'notify_email' => '{{site_email}}');
};

// A client's own list: the list, and a box to send another request.
$own_list_page = function ($title, $lead, $widget_key, $box_title, $box_text, $button, $href) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget($widget_key)), '', '8'),
                    $col(array(
                        $el('div', 'p-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-lg'),
                                $span($box_title, '', lang('Text')),
                            )),
                            $para($box_text, 'small text-body-secondary mb-3'),
                            $link($button, $href, 'btn btn-sm btn-primary rounded-0 px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A staff list page.
$staff_list_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($widget_key))),
        )),
    ));
};

// ── Pages: project enquiries ────────────────────────────────────────────

// The project enquiry. What is sent opens the enquiry's own page.
$enquiry_pages[] = array(
    'key'              => 'enquiry',
    'name'             => lang('start-a-project'),
    'folder'           => 'public',
    'title'            => lang('Start a Project'),
    'meta_description' => lang('Tell the studio about your site, your plans and your budget; we answer every project enquiry within three working days.'),
    'form'             => array(
        'form_name'            => lang('Project Enquiries'),
        'confirmation_message' => lang('Thank you, your project enquiry has reached us. We answer within three working days.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New project enquiry: ^^service^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your project enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $form_page(lang('Start a Project'),
        lang('Tell us about your site, your plans and your budget in a few lines. An architect reads every project enquiry and answers within three working days.'),
        'enquiry_form',
        $form_aside(array(
            lang('Your project enquiry opens its own page, and an account for you if you have none.'),
            lang('An architect reads it and calls you within three working days.'),
            lang('After the first consultation we send a written proposal with the scope, the stages and our fee.'),
            lang('More drawings or a question? Write under your project enquiry.'),
        ), lang('No obligation'), array(
            lang('The first consultation is free.'),
            lang('Our proposal is yours to accept or not.'),
            lang('Your drawings and photos are seen only by the studio.'),
        ))),
);

// One project enquiry: the enquiry, its status and the messages under it.
$enquiry_pages[] = array(
    'key'      => 'enquiry_view',
    'name'     => lang('project-enquiry'),
    'folder'   => 'registration',
    'title'    => lang('Project Enquiry'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $record_page_comments(lang('There is a new message about your project enquiry')),
    'tree'     => $page(array(
        $widget('enquiry_detail'),
        $comments_section(lang('Messages'), 'enquiry_messages'),
    ), false),
);

// My Enquiries: the client's own project enquiries (for the staff,
// everybody's).
$enquiry_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-project-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Project Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $own_list_page(lang('My Project Enquiries'), lang('Your project enquiries, their status and our answers, newest first.'),
        'my_enquiries_list', lang('Another project?'),
        lang('Send a project enquiry for another site; your details are filled in for you.'), lang('Start a Project'), '{{page:enquiry}}'),
);

// The staff's list of every project enquiry, newest first, with a search.
$enquiry_pages[] = array(
    'key'     => 'staff_enquiries',
    'name'    => lang('all-project-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Project Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('Project Enquiries'),
        lang('Every project enquiry, newest first: book the call, send the proposal, set the status and write to the client under each.'), 'staff_enquiries_list'),
);

// ── Pages: consultation requests ────────────────────────────────────────

$enquiry_pages[] = array(
    'key'              => 'consultation',
    'name'             => lang('book-a-consultation'),
    'folder'           => 'public',
    'title'            => lang('Book a Consultation'),
    'meta_description' => lang('Ask for a free first consultation at the studio, on a video call or on your site; we answer with a time within two working days.'),
    'form'             => array(
        'form_name'            => lang('Consultation Requests'),
        'confirmation_message' => lang('Thank you, your consultation request has reached us. We answer with a time within two working days.'),
        'confirmation_page_id' => '{{tab:consultation_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New consultation request: ^^service^^'),
        'notify_page_id'       => '{{tab:email_consultation_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your request for a consultation'),
        'confirm_page_id'      => '{{tab:email_consultation_received}}',
    ),
    'tree'             => $form_page(lang('Book a Consultation'),
        lang('The first consultation is free: an hour at the studio, on a video call or on your site to talk about what you need.'),
        'consultation_form',
        $form_aside(array(
            lang('Your consultation request opens its own page, and an account for you if you have none.'),
            lang('Within two working days we write a time under it that suits the days you gave.'),
            lang('You confirm the time, or ask for another, on the same page.'),
        ), lang('The first consultation is free'), array(
            lang('About an hour with an architect or an interior designer.'),
            lang('Bring photos, a plan or just an idea.'),
            lang('No obligation: you decide afterwards.'),
        ))),
);

$enquiry_pages[] = array(
    'key'      => 'consultation_view',
    'name'     => lang('consultation-request'),
    'folder'   => 'registration',
    'title'    => lang('Consultation Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $record_page_comments(lang('There is a new message about your consultation request')),
    'tree'     => $page(array(
        $widget('consultation_detail'),
        $comments_section(lang('Messages'), 'consultation_messages'),
    ), false),
);

$enquiry_pages[] = array(
    'key'     => 'my_consultations',
    'name'    => lang('my-consultation-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Consultation Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $own_list_page(lang('My Consultation Requests'), lang('Your consultation requests, the time we proposed and our answers, newest first.'),
        'my_consultations_list', lang('Another conversation?'),
        lang('Ask for another consultation; your details are filled in for you.'), lang('Book a Consultation'), '{{page:consultation}}'),
);

$enquiry_pages[] = array(
    'key'     => 'staff_consultations',
    'name'    => lang('all-consultation-requests'),
    'folder'  => 'private',
    'title'   => lang('Consultation Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('Consultation Requests'),
        lang('Every consultation request, newest first: propose a time under each, then set the status.'), 'staff_consultations_list'),
);

// ── Pages: privacy and the e-mails ──────────────────────────────────────

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$enquiry_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the studio keeps about you when you send a project enquiry, book a consultation or apply for a job, and why.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the studio keeps about you when you send a project enquiry, book a consultation or apply for a job, and why.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary rounded-0 d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pai-display h4 mb-3'),
                $para(lang('When you send a project enquiry, book a consultation, apply for a job, join the newsletter or open an account, we keep your name, your contact details, where your site is, what you tell us about your project and the drawings, photos, portfolio or CV you send, to answer you, prepare a proposal or consider your application.'), 'mb-3'),
                $para(lang('We pass to the engineers and consultants we work with only what they need for your project. We keep the rest as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('ai-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-project-enquiry'),
    'title' => lang('New Project Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-project-enquiry-received'),
    'title' => lang('Project Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_consultation_new',
    'name'  => lang('email-new-consultation-request'),
    'title' => lang('New Consultation Request E-mail'),
    'tree'  => $mail_page(array($widget('email_consultation_admin'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_consultation_received',
    'name'  => lang('email-consultation-request-received'),
    'title' => lang('Consultation Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_consultation_client'))),
));
// A new message or reply: sent to the client of a project enquiry or a
// consultation, the applicant of a job, the sender of a conversation and
// the visitors watching a page. Its Comments widget draws the comment in
// the card of mail.php; the System region after it keeps the fixed comment
// for an e-mail the widget does not draw.
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your project enquiry, your consultation request, your application, a conversation or a journal article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The client's name.
$client_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A small detail of a list row: an icon, then its words.
$row_item = function ($icon_name, $name, $sample, $token) use ($el, $icon, $span, $bind) {
    return $el('span', 'd-inline-flex align-items-center gap-1', $name, array(
        $icon($icon_name),
        $span($sample, '', $name, $bind($token)),
    ));
};

// A request in a list: the service and the status on top, the details
// under them, linked to its page. The staff's row starts with the client's
// name and adds the phone; the client's own adds the messages.
$request_row = function ($staff, $service, $items, $status, $name, $field = 'service') use ($el, $span, $bind, $client_name, $row_item) {
    if ($staff) {
        $items[] = $row_item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($client_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($service, 'text-body-secondary fw-normal', lang('Service'), $bind($field)),
        )));
    } else {
        $items[] = $row_item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($service, 'fw-semibold', lang('Service'), $bind($field));
    }
    return $el('a', 'list-group-item list-group-item-action px-0 py-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-0 text-bg-primary', lang('Status'), $bind('status')),
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
    ), $mail_style('font-size:13px;color:#52525b;margin:24px 0 0'));
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
    ), array_merge($mail_style('border-top:1px solid #e4e4e7;padding-top:16px'), $show_when('has_new_account')));
};
// The client's lines of a staff e-mail; $details is the client's own words.
$mail_client = function ($details_label, $details_sample) use ($mail_box, $mail_line, $span, $bind, $client_name) {
    return $mail_box(array(
        $mail_line(lang('Client'), $client_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Contact by'), array($span(lang('Phone'), '', lang('Contact by'), $bind('contact_method')))),
        $mail_line($details_label, array($span($details_sample, '', $details_label, $bind('details')))),
    ), lang('Client'));
};
// Lines of details for an e-mail box: each array(label, sample, field).
$mail_lines = function ($lines, $name) use ($mail_box, $mail_line, $span, $bind) {
    $children = array();
    foreach ($lines as $l) $children[] = $mail_line($l[0], array($span($l[1], '', $l[0], $bind($l[2]))));
    return $mail_box($children, $name);
};

// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the record page's
// widget (the client's own, and the staff's).
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

// A list of requests of one form: the client's own (the staff see them
// all), or the staff's with a search over $search.
$request_list = function ($page_key, $form_key, $view_key, $staff, $empty, $search, $search_label, $row_tree) use ($widget_root, $el, $loop) {
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
            'search_fields'  => $search,
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
            $el('div', 'list-group list-group-flush border-top border-bottom', lang('Request List'), array($loop(array($row_tree)))),
        )),
    );
};

// The record page's tree: the not-found note, the sent and new-account
// notices, the card with the details, the client's words and a note on
// the messages under it. $c carries the words and fields of one form;
// the title binds $c['title_field'] and the words $c['words_field'] when
// given (the service and the details otherwise).
$record_tree = function ($c) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button, $bound, $detail) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-file-earmark-x', 'display-5 text-body-tertiary'),
                        $heading('h2', $c['not_found_title'], 'pai-display h3 mt-3'),
                        $para($c['missing'], 'text-body-secondary mb-4', $bind('not_found')),
                        $link($c['list_title'], '{{page:' . $c['list_key'] . '}}', 'btn btn-primary rounded-0 px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success rounded-0 d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($c['sent'], '', lang('Text')),
                        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                        $el('div', 'alert alert-secondary rounded-0', lang('New Account'), array(
                            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                            $el('p', 'mb-1', lang('Sign-in Email'), array(
                                $span(lang('Email') . ': ', '', lang('Label')),
                                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                            )),
                            // The single-use set password link, for the person who sent the form
                            // while it is live; the sentence below covers a used or expired link.
                            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary rounded-0', array(),
                                array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
                            $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
                        ), $show_when('has_new_account')),
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                            $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-body-emphasis text-decoration-none', lang('Link'), array(
                                $icon('bi-arrow-left'),
                                $span($c['list_title'], '', lang('Link Text')),
                            ), array('href' => '{{page:' . $c['list_key'] . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'border p-4 mb-4', lang('Request Card'), array(
                            $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                $el('div', '', lang('Words'), array(
                                    $para($c['eyebrow'], 'pai-eyebrow small text-uppercase fw-semibold text-body-secondary mb-1'),
                                    $heading('h1', $c['service'], 'pai-display h3 mb-0', $bind($c['title_field'] ?? 'service')),
                                )),
                                $span($c['status'], 'badge rounded-0 text-bg-primary fs-6', lang('Status'), $bind('status')),
                            )),
                            $el('dl', 'row small mb-0', lang('Details'), $c['details']),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 bg-body-tertiary mb-4', $c['words_label'], array(
                            $para($c['words_label'], 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($c['words'], $c['words_field'] ?? 'details')),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'mt-1'),
                            $span($c['note'], '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the client is e-mailed
// every answer and the studio every message.
$messages_widget = function ($page_key, $empty) use ($comment_thread) {
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

// The contact part both forms end with: name, e-mail, phone, how to reach
// the client. $p is the id prefix.
$contact_fields = function ($p) use ($row, $col, $field, $input, $radios, $contact_choices) {
    return array(
        $row(array(
            $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
        ), '3'),
        $radios($p . '-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[0][0]),
    );
};

$enquiry_missing = lang('This project enquiry could not be found, or it is not yours to see.');
$consultation_missing = lang('This consultation request could not be found, or it is not yours to see.');

// The lines of each request for its e-mails.
$mail_enquiry = $mail_lines(array(
    array(lang('Service'), $sample_service, 'service'),
    array(lang('Project Type'), $sample_kind, 'project_type'),
    array(lang('Location'), $sample_location, 'location'),
    array(lang('The Site'), $sample_site, 'site'),
    array(lang('Project Size'), $sample_size, 'size'),
    array(lang('Budget'), $sample_budget, 'budget'),
    array(lang('Start'), $sample_start, 'start'),
), lang('Project Enquiry'));
$mail_consultation = $mail_lines(array(
    array(lang('Service'), $sample_consult_service, 'service'),
    array(lang('Where to Meet'), $sample_place, 'place'),
    array(lang('Preferred Days'), $sample_days, 'days'),
), lang('Consultation Request'));

// The rows of the lists.
$enquiry_row = function ($staff) use ($request_row, $row_item, $sample_service, $sample_kind, $sample_location, $sample_start, $enquiry_statuses) {
    return $request_row($staff, $sample_service, array(
        $row_item('bi-house', lang('Project Type'), $sample_kind, 'project_type'),
        $row_item('bi-geo-alt', lang('Location'), $sample_location, 'location'),
        $row_item('bi-calendar-event', lang('Start'), $sample_start, 'start'),
    ), $enquiry_statuses[0], lang('Project Enquiry'));
};
$consultation_row = function ($staff) use ($request_row, $row_item, $sample_consult_service, $sample_place, $sample_days, $consultation_statuses) {
    return $request_row($staff, $sample_consult_service, array(
        $row_item('bi-pin-map', lang('Where to Meet'), $sample_place, 'place'),
        $row_item('bi-calendar-week', lang('Preferred Days'), $sample_days, 'days'),
    ), $consultation_statuses[0], lang('Consultation Request'));
};

$enquiry_widgets = array(

    // The project enquiry: the fields are the page's form. The service
    // starts with what a link sends (?service=). The Status is the
    // studio's (office use only): every enquiry starts as Enquiry received.
    'enquiry_form' => array(
        'page'   => 'enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your project')),
            $row(array(
                $col(array($field('ai-enq-service', lang('Service'), $select('ai-enq-service', 'service', lang('Choose a service…'), $enquiry_service_choices, true, '', array('url_param' => 'service')))), '6'),
                $col(array($field('ai-enq-kind', lang('Project Type'), $select('ai-enq-kind', 'project_type', lang('Choose a type…'), $project_kinds))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('ai-enq-location', lang('Location'), $input('ai-enq-location', 'text', 'location', 'address-level2'), 'mb-3',
                    lang('The district or town of the site; the full address can follow in a message.'))), '6'),
                $col(array($field('ai-enq-site', lang('The Site'), $select('ai-enq-site', 'site', lang('Choose one…'), $site_choices))), '6'),
            ), '3'),
            $field('ai-enq-size', lang('Project Size'), $input('ai-enq-size', 'text', 'size', 'off', false), 'mb-3',
                sprintf(lang('In words, for example %s.'), $sample_size)),
            $row(array(
                $col(array($field('ai-enq-budget', lang('Budget'), $select('ai-enq-budget', 'budget', '', $budget_choices, false, $budget_choices[4]))), '6'),
                $col(array($field('ai-enq-start', lang('Start'), $select('ai-enq-start', 'start', '', $start_choices, false, $start_choices[4]))), '6'),
            ), '3'),
            $field('ai-enq-details', lang('Your project'), $textarea('ai-enq-details', 'details', 5), 'mb-3',
                lang('A few lines are enough: what you would like to build or change, who will use it and what matters most to you.')),
            $field('ai-enq-drawings', lang('Drawings and Photos'), $file('ai-enq-drawings', 'drawings', 'private'), 'mb-3',
                lang('A survey, existing drawings or photos of the site (PDF, Word or a picture). More can follow under your enquiry.')),
            $form_step(2, lang('How to reach you')),
        ), $contact_fields('ai-enq'), array(
            $field('ai-enq-status', lang('Status'), $select('ai-enq-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('ai-enq-consent', 'consent', lang('I agree that the studio keeps these details to answer my project enquiry and prepare a proposal, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-0 px-4'),
                $para(lang('We answer within three working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    // One project enquiry: the enquiry, its status and what the client
    // wrote; the studio's answers (the call and the proposal) follow as
    // comments.
    'enquiry_detail' => $detail_widget('enquiry_view', 'enquiry', $enquiry_missing, 'submitter_only') + array(
        'tree' => $record_tree(array(
            'not_found_title' => lang('Project enquiry not found'),
            'missing'         => $enquiry_missing,
            'list_title'      => lang('My Project Enquiries'),
            'list_key'        => 'my_enquiries',
            'sent'            => lang('Thank you, your project enquiry has reached us. An architect calls you within three working days; this page shows the status, and you can write to us below.'),
            'eyebrow'         => lang('Project Enquiry'),
            'service'         => $sample_service,
            'status'          => $enquiry_statuses[0],
            'details'         => array_merge(
                $detail(lang('Project Type'), '', lang('Project Type'), $bound($sample_kind, 'project_type')),
                $detail(lang('Location'), '', lang('Location'), $bound($sample_location, 'location')),
                $detail(lang('The Site'), '', lang('The Site'), $bound($sample_site, 'site')),
                $detail(lang('Project Size'), '', lang('Project Size'), $bound($sample_size, 'size')),
                $detail(lang('Budget'), '', lang('Budget'), $bound($sample_budget, 'budget')),
                $detail(lang('Start'), '', lang('Start'), $bound($sample_start, 'start')),
                $detail(lang('Client'), '', lang('Client'), $client_name(), true),
                $detail(lang('Contact by'), '', lang('Contact by'), $bound($contact_choices[0][1], 'contact_method')),
                $detail(lang('Drawings and Photos'), 'text-break', lang('Drawings and Photos'), array(
                    $link('site-survey.pdf', '#', 'link-body-emphasis', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'drawings', 'text' => 'drawings'))),
                ), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('Your project'),
            'words'           => $sample_details,
            'note'            => lang('More drawings, photos or a question? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'enquiry_messages' => $messages_widget('enquiry_view',
        lang('No messages yet. The day of our call and our proposal come here; you can write to us about your project too.')),

    'my_enquiries_list' => $request_list('my_enquiries', 'enquiry', 'enquiry_view', false,
        lang('No project enquiries yet. Tell us about your site and your plans, and an architect answers within three working days.'),
        array(), '', $enquiry_row(false)),

    'staff_enquiries_list' => $request_list('staff_enquiries', 'enquiry', 'enquiry_view', true, lang('No project enquiries yet.'),
        array('first_name', 'last_name', 'email', 'service', 'project_type', 'location', 'status'),
        lang('Search by client, service, type, location or status...'), $enquiry_row(true)),

    // The consultation request: the service (?service=), where to meet,
    // the preferred days in words and a few lines. The Status is the
    // studio's: every request starts as Request received.
    'consultation_form' => array(
        'page'   => 'consultation',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('The consultation')),
            $field('ai-con-service', lang('Service'), $select('ai-con-service', 'service', lang('Choose a service…'), $enquiry_service_choices, true, '', array('url_param' => 'service'))),
            $radios('ai-con-place', 'place', lang('Where to Meet'), array_map(function ($p) {
                return array($p, $p);
            }, $consultation_places), $consultation_places[0]),
            $field('ai-con-days', lang('Preferred Days'), $input('ai-con-days', 'text', 'days', 'off', false), 'mb-3',
                sprintf(lang('In words, for example %s. We answer with a time.'), $sample_days)),
            $field('ai-con-details', lang('What would you like to talk about?'), $textarea('ai-con-details', 'details', 3), 'mb-3',
                lang('A few lines about your home, your workplace or your site.')),
            $form_step(2, lang('How to reach you')),
        ), $contact_fields('ai-con'), array(
            $field('ai-con-status', lang('Status'), $select('ai-con-status', 'status', '', $consultation_statuses, false, $consultation_statuses[0], array('office_use_only' => true))),
            $agree('ai-con-consent', 'consent', lang('I agree that the studio keeps these details to arrange my consultation, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Ask for a Consultation'), 'btn btn-primary btn-lg rounded-0 px-4'),
                $para(lang('We answer with a time within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    'consultation_detail' => $detail_widget('consultation_view', 'consultation', $consultation_missing, 'submitter_only') + array(
        'tree' => $record_tree(array(
            'not_found_title' => lang('Consultation request not found'),
            'missing'         => $consultation_missing,
            'list_title'      => lang('My Consultation Requests'),
            'list_key'        => 'my_consultations',
            'sent'            => lang('Thank you, your consultation request has reached us. Within two working days we write a time below; this page shows the status, and you can write to us here.'),
            'eyebrow'         => lang('Consultation Request'),
            'service'         => $sample_consult_service,
            'status'          => $consultation_statuses[0],
            'details'         => array_merge(
                $detail(lang('Where to Meet'), '', lang('Where to Meet'), $bound($sample_place, 'place')),
                $detail(lang('Preferred Days'), '', lang('Preferred Days'), $bound($sample_days, 'days')),
                $detail(lang('Client'), '', lang('Client'), $client_name(), true),
                $detail(lang('Contact by'), '', lang('Contact by'), $bound($contact_choices[0][1], 'contact_method')),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('What would you like to talk about?'),
            'words'           => $sample_consult_details,
            'note'            => lang('The time we propose comes below. Confirm it, or ask for another, in a message.'),
        )),
    ),

    'consultation_messages' => $messages_widget('consultation_view',
        lang('No messages yet. The time we propose for your consultation comes here.')),

    'my_consultations_list' => $request_list('my_consultations', 'consultation', 'consultation_view', false,
        lang('No consultation requests yet. The first consultation is free: ask for one, and we answer with a time.'),
        array(), '', $consultation_row(false)),

    'staff_consultations_list' => $request_list('staff_consultations', 'consultation', 'consultation_view', true, lang('No consultation requests have been sent yet.'),
        array('first_name', 'last_name', 'email', 'service', 'place', 'status'),
        lang('Search by client, service, place or status...'), $consultation_row(true)),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $detail_widget('email_enquiry_new', 'enquiry', lang('This project enquiry was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new project enquiry')),
            $mail_para(lang('It came from the enquiry form. Call the client, write the day of the call under the enquiry on its page and set the status; what you write there reaches the client by e-mail.')),
            $mail_enquiry,
            $mail_client(lang('Your project'), $sample_details),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $detail_widget('email_enquiry_received', 'enquiry', lang('This project enquiry was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your project enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. An architect reads your project enquiry and calls you within three working days; after the first consultation we send you a written proposal, on the enquiry\'s page and by e-mail.')),
            $mail_enquiry,
            $mail_para(lang('More drawings or a question? Write to us on the enquiry\'s page; everything about your project stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_consultation_admin' => $detail_widget('email_consultation_new', 'consultation', lang('This consultation request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new request for a consultation')),
            $mail_para(lang('It came from the consultation form. Write a time that suits the client\'s days under the request on its page and set the status; what you write there reaches the client by e-mail.')),
            $mail_consultation,
            $mail_client(lang('What would you like to talk about?'), $sample_consult_details),
            $mail_button(lang('Open the request'), '{{page:consultation_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_consultation_client' => $detail_widget('email_consultation_received', 'consultation', lang('This consultation request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your request for a consultation')),
            $mail_greeting(),
            $mail_para(lang('Thank you. Within two working days we propose a time that suits the days you gave, on the request\'s page and by e-mail.')),
            $mail_consultation,
            $mail_button(lang('Open my request'), '{{page:consultation_view}}?r=^^reference_code^^'),
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

return array('pages' => $enquiry_pages, 'widgets' => $enquiry_widgets);
