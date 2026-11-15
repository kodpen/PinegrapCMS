<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Construction Firm", its quote requests: the quote
 * request with the free site visit asked in the same form, the request's
 * own page with its messages, My Quote Requests and the staff's list; the
 * privacy page the consent boxes link, the e-mails of the quote form and
 * the new message e-mail every thread of the site sends, and the widgets
 * that draw them. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A quote request is a request, never a contract: a record of the Quote
 * Requests form. A client without an account gets one (auto-registration),
 * so the request's page and My Quote Requests show it at once. Its Status
 * is an office-use-only field the firm changes in the control panel
 * (received, site visit booked, quote sent, accepted, declined); the visit
 * day and the quote itself are written under it as messages (the page's
 * comments), mailed to the client and to the firm. Plans and photos of the
 * site are kept in the template's private folder: the record's page links
 * them for whoever may read the record.
 *
 * The service starts with what a link sends (?service=: the service's
 * title, as parts.php writes it on the service pages and the projects).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$quote_pages = array();

// The answers of the site visit question: the value first, then the words
// shown beside the radio.
$visit_choices = array(
    array(lang('Yes, please visit the site'), lang('Yes, please visit the site')),
    array(lang('Not yet, a first price is enough'), lang('Not yet, a first price is enough')),
);
$visit_times = array(lang('Weekday morning'), lang('Weekday afternoon'), lang('Saturday morning'), lang('Any time'));

// The sample request the canvas shows. Its start is words, never a date.
$sample_service = $services['renovation']['title'];
$sample_kind = $project_kinds[2];
$sample_location = lang('Riverside Old Town');
$sample_budget = $budget_choices[1];
$sample_start = $start_choices[1];
$sample_details = lang('A seventy-year-old two-storey stone house: we would like to strengthen it, open the ground floor into one room and add a bathroom upstairs. We live in it during the work.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the quote page: what happens next, then why the site
// visit is free and the office hours.
$quote_aside = function () use ($el, $heading, $para, $icon, $span, $check_list, $firm) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('An engineer calls you within two working days to agree a day for the site visit.'),
        lang('Within ten working days of the visit you receive a fixed-price quote with a programme.'),
        lang('Questions, plans or more photos? Write under your request.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pcn-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border mb-4', lang('Free Site Visit'), array(
            $heading('h2', lang('The site visit is free'), 'pcn-display h5 mb-3'),
            $check_list(array(
                lang('An engineer looks at the ground, the access and the building.'),
                lang('No obligation: the quote is yours to accept or not.'),
                lang('The price we quote is fixed for the work we agree.'),
            ), 'list-unstyled small mb-0'),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Office Hours'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-clock', 'text-primary'),
                $span(lang('Office Hours'), '', lang('Text')),
            )),
            $para($firm['hours'], 'small text-body-secondary mb-1'),
            $para($firm['phone'], 'small fw-semibold mb-0'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The quote request. What is sent opens the request's own page.
$quote_pages[] = array(
    'key'              => 'quote',
    'name'             => lang('request-a-quote'),
    'folder'           => 'public',
    'title'            => lang('Request a Quote'),
    'meta_description' => lang('Tell us about your project and ask for a free site visit; you receive a fixed-price quote within ten working days of the visit.'),
    'form'             => array(
        'form_name'            => lang('Quote Requests'),
        'confirmation_message' => lang('Thank you, your quote request has reached us. An engineer calls you within two working days.'),
        'confirmation_page_id' => '{{tab:quote_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New quote request: ^^service^^'),
        'notify_page_id'       => '{{tab:email_quote_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your quote request'),
        'confirm_page_id'      => '{{tab:email_quote_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Request a Quote'),
            lang('Tell us about your project in a few lines. An engineer visits the site free of charge, and you receive a fixed-price quote.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('quote_form'))),
                        )),
                    ), '', '8'),
                    $col($quote_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One quote request: the request, its status and the messages under it.
$quote_pages[] = array(
    'key'      => 'quote_view',
    'name'     => lang('quote-request'),
    'folder'   => 'registration',
    'title'    => lang('Quote Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your quote request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('quote_detail'),
        $comments_section(lang('Messages'), 'quote_messages'),
    ), false),
);

// My Quote Requests: the client's own requests (for the staff,
// everybody's).
$quote_pages[] = array(
    'key'     => 'my_quotes',
    'name'    => lang('my-quote-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Quote Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Quote Requests'), lang('Your quote requests, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_quotes_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-circle', 'text-primary'),
                                $span(lang('Another project?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for a quote for another project; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Request a Quote'), '{{page:quote}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every quote request, newest first, with a search.
$quote_pages[] = array(
    'key'     => 'staff_quotes',
    'name'    => lang('all-quote-requests'),
    'folder'  => 'private',
    'title'   => lang('Quote Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Quote Requests'), lang('Every quote request, newest first: book the site visit, send the quote, set the status and write to the client under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_quotes_list'))),
        )),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$quote_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the firm keeps about you when you ask for a quote or apply for a job, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the firm keeps about you when you ask for a quote or apply for a job, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you ask for a quote, apply for a job, join the newsletter or open an account, we keep your name, your contact details, the address of your site, what you tell us about your project and the plans, photos or CV you send, to answer you, visit the site and prepare your quote or consider your application.'), 'mb-3'),
                $para(lang('We pass to our designers and subcontractors only what they need to price and build your project. We keep the rest as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('cn-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the firm receives and the reply to the client, and the
// new message e-mail.
$quote_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_quote_new',
    'name'  => lang('email-new-quote-request'),
    'title' => lang('New Quote Request E-mail'),
    'tree'  => $mail_page(array($widget('email_quote_admin'))),
));
$quote_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_quote_received',
    'name'  => lang('email-quote-request-received'),
    'title' => lang('Quote Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_quote_client'))),
));
// A new message or reply: sent to the client of a quote request, the
// applicant of a job, the sender of a conversation and the visitors
// watching a page. Its Comments widget draws the comment in the card of
// mail.php; the System region after it keeps the fixed comment for an
// e-mail the widget does not draw.
$quote_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your quote request, your application, a conversation or a news post. You can read it below and answer it on the page.')),
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

// A quote request in a list: the service, the status, the kind of work,
// the location and the start, linked to its page. The staff's row starts
// with the client's name and adds the phone and the site visit; the
// client's own adds the messages.
$quote_row = function ($staff = false) use ($el, $span, $icon, $bind, $client_name, $sample_service, $sample_kind, $sample_location, $sample_start, $visit_choices, $quote_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-tools', lang('Project Type'), array($span($sample_kind, '', lang('Project Type'), $bind('project_type')))),
        $item('bi-geo-alt', lang('Location'), array($span($sample_location, '', lang('Location'), $bind('location')))),
        $item('bi-calendar-event', lang('Start'), array($span($sample_start, '', lang('Start'), $bind('start')))),
    );
    if ($staff) {
        $items[] = $item('bi-clipboard-check', lang('Site Visit'), array($span($visit_choices[0][1], '', lang('Site Visit'), $bind('site_visit'))));
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($client_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_service, 'text-body-secondary fw-normal', lang('Service Type'), $bind('service')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_service, 'fw-semibold', lang('Service Type'), $bind('service'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Quote Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($quote_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
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

// The lines of a quote request, for its e-mails.
$mail_quote = function () use ($mail_box, $mail_line, $span, $bind, $sample_service, $sample_kind, $sample_location, $sample_budget, $sample_start, $visit_choices, $visit_times) {
    return $mail_box(array(
        $mail_line(lang('Service Type'), array($span($sample_service, '', lang('Service Type'), $bind('service')))),
        $mail_line(lang('Project Type'), array($span($sample_kind, '', lang('Project Type'), $bind('project_type')))),
        $mail_line(lang('Location'), array($span($sample_location, '', lang('Location'), $bind('location')))),
        $mail_line(lang('Budget'), array($span($sample_budget, '', lang('Budget'), $bind('budget')))),
        $mail_line(lang('Start'), array($span($sample_start, '', lang('Start'), $bind('start')))),
        $mail_line(lang('Site Visit'), array($span($visit_choices[0][1], '', lang('Site Visit'), $bind('site_visit')))),
        $mail_line(lang('Best time for the visit'), array($span($visit_times[0], '', lang('Visit Time'), $bind('visit_time')))),
    ), lang('Quote Request'));
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
    ), array_merge($mail_style('border-top:1px solid #fde68a;padding-top:16px'), $show_when('has_new_account')));
};
// The client's lines of the staff e-mail.
$mail_client = function () use ($mail_box, $mail_line, $span, $bind, $client_name, $sample_details) {
    return $mail_box(array(
        $mail_line(lang('Client'), $client_name()),
        $mail_line(lang('Company'), array($span(lang('Sample Holding Inc.'), '', lang('Company'), $bind('company')))),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Contact by'), array($span(lang('Phone'), '', lang('Contact by'), $bind('contact_method')))),
        $mail_line(lang('Your project'), array($span($sample_details, '', lang('Project Details'), $bind('details')))),
    ), lang('Client'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the request page's
// widget (the client's own, and the staff's).
$quote_detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:quote}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of quote requests: the client's own (the staff see them all), or
// the staff's with a search.
$quote_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $quote_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:quote}}',
        'detail_page_id'      => '{{tab:quote_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'company', 'email', 'service', 'location', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by client, company, service, location or status...'),
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($quote_row($staff))))),
        )),
    );
};

$quote_missing = lang('This quote request could not be found, or it is not yours to see.');

$quote_widgets = array(

    // The quote request: the fields are the page's form. The service starts
    // with what a link sends (?service=). The Status is the firm's (office
    // use only): every request starts as Request received.
    'quote_form' => array(
        'page'   => 'quote',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your project')),
            $row(array(
                $col(array($field('cn-qt-service', lang('Service Type'), $select('cn-qt-service', 'service', lang('Choose a service…'), $quote_service_choices, true, '', array('url_param' => 'service')))), '6'),
                $col(array($field('cn-qt-kind', lang('Project Type'), $select('cn-qt-kind', 'project_type', lang('Choose a type…'), $project_kinds))), '6'),
            ), '3'),
            $field('cn-qt-location', lang('Location'), $input('cn-qt-location', 'text', 'location', 'address-level2'), 'mb-3',
                lang('The district or town of the site; the full address can follow in a message.')),
            $row(array(
                $col(array($field('cn-qt-budget', lang('Budget'), $select('cn-qt-budget', 'budget', '', $budget_choices, false, $budget_choices[4]))), '6'),
                $col(array($field('cn-qt-start', lang('Start'), $select('cn-qt-start', 'start', '', $start_choices, false, $start_choices[4]))), '6'),
            ), '3'),
            $field('cn-qt-details', lang('Your project'), $textarea('cn-qt-details', 'details', 5), 'mb-3',
                lang('A few lines are enough: what you would like to build or change, the size and anything we should know about the site.')),
            $field('cn-qt-plans', lang('Plans and Photos'), $file('cn-qt-plans', 'plans', 'private'), 'mb-3',
                lang('Drawings, a survey or photos of the site (PDF, Word or a picture). More can follow under your request.')),
            $form_step(2, lang('The site visit')),
            $radios('cn-qt-visit', 'site_visit', lang('Would you like a free site visit?'), $visit_choices, $visit_choices[0][0]),
            $field('cn-qt-visit-time', lang('Best time for the visit'), $select('cn-qt-visit-time', 'visit_time', '', $visit_times, false, $visit_times[3]), 'mb-3',
                lang('We call you to agree the day.')),
            $form_step(3, lang('How to reach you')),
            $row(array(
                $col(array($field('cn-qt-first-name', lang('First Name'), $input('cn-qt-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('cn-qt-last-name', lang('Last Name'), $input('cn-qt-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('cn-qt-email', lang('Email'), $input('cn-qt-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('cn-qt-phone', lang('Phone'), $input('cn-qt-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('cn-qt-company', lang('Company'), $input('cn-qt-company', 'text', 'company', 'organization', false), 'mb-3',
                lang('Only when you ask for a company.')),
            $radios('cn-qt-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[1][0]),
            $field('cn-qt-status', lang('Status'), $select('cn-qt-status', 'status', '', $quote_statuses, false, $quote_statuses[0], array('office_use_only' => true))),
            $agree('cn-qt-consent', 'consent', lang('I agree that the firm keeps these details to answer my request, visit the site and prepare my quote, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('An engineer calls you within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One quote request: the request, its status and what the client
    // wrote; the firm's answers (the visit day and the quote) follow as
    // comments.
    'quote_detail' => $quote_detail_widget('quote_view', $quote_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-clipboard-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Quote request not found'), 'h3 fw-semibold mt-3'),
                            $para($quote_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Quote Requests'), '{{page:my_quotes}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached us. An engineer calls you within two working days to agree the site visit; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Quote Requests'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_quotes}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Request Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Quote Request'), 'pcn-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_service, 'pcn-display h3 mb-0', $bind('service')),
                                        )),
                                        $span($quote_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Project Type'), '', lang('Project Type'), $bound($sample_kind, 'project_type')),
                                        $detail(lang('Location'), '', lang('Location'), $bound($sample_location, 'location')),
                                        $detail(lang('Budget'), '', lang('Budget'), $bound($sample_budget, 'budget')),
                                        $detail(lang('Start'), '', lang('Start'), $bound($sample_start, 'start')),
                                        $detail(lang('Site Visit'), '', lang('Site Visit'), $bound($visit_choices[0][1], 'site_visit')),
                                        $detail(lang('Best time for the visit'), '', lang('Visit Time'), $bound($visit_times[0], 'visit_time')),
                                        $detail(lang('Client'), '', lang('Client'), $client_name(), true),
                                        $detail(lang('Plans and Photos'), 'text-break', lang('Plans and Photos'), array(
                                            $link('site-plan.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                                '_bindings' => array('href' => 'plans', 'text' => 'plans'))),
                                        ), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('Your project'), array(
                                $para(lang('Your project'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_details, 'details')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('More plans, photos or a question? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page, with a file each. No Reply, Share or follow box: the client
    // is e-mailed every answer and the firm every message.
    'quote_messages' => array(
        'page'   => 'quote_view',
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
            'empty'     => lang('No messages yet. The day of the site visit and your quote come here; you can write to us about your project too.'),
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

    'my_quotes_list' => $quote_list('my_quotes', false,
        lang('No quote requests yet. Tell us about your project, and an engineer calls you within two working days.')),

    'staff_quotes_list' => $quote_list('staff_quotes', true, lang('No quote requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_quote_admin' => $quote_detail_widget('email_quote_new', lang('This quote request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new quote request')),
            $mail_para(lang('It came from the quote form. Call the client to agree the site visit, write the day under the request on its page and set the status; what you write there reaches the client by e-mail.')),
            $mail_quote(),
            $mail_client(),
            $mail_button(lang('Open the request'), '{{page:quote_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_quote_client' => $quote_detail_widget('email_quote_received', lang('This quote request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your quote request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. An engineer calls you within two working days to agree the site visit, and you receive a fixed-price quote within ten working days of it, on the request\'s page and by e-mail.')),
            $mail_quote(),
            $mail_para(lang('More plans or a question? Write to us on the request\'s page; everything about your project stays there.')),
            $mail_button(lang('Open my request'), '{{page:quote_view}}?r=^^reference_code^^'),
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

return array('pages' => $quote_pages, 'widgets' => $quote_widgets);
