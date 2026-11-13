<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Rental", its business and long-term rentals: the
 * business page with the rental plans, the enquiry, the enquiry's own page
 * with its messages, My Enquiries and the staff's list, the enquiry's
 * e-mails and the widgets that draw them. Included after parts.php,
 * mail.php and bookings.php in the same scope (it reads $detail, $bound,
 * $renter_name, $checkboxes, $mail_reference and $mail_new_account of
 * bookings.php); returns array('pages' => ..., 'widgets' => ...).
 *
 * An enquiry is a record of the Business Enquiries form, like a booking
 * request: an account for a sender who has none, an office-use-only
 * Status, the quote written under it as a message (a PDF may go with it),
 * mailed to the sender and to the company. The fleet size and the term are
 * bands; no tax number or ID is asked. The Rental Plan starts with what a
 * plan card sends (?plan=: the plan's title, as parts.php writes it).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$business_pages = array();

// The sample enquiry the canvas shows. Its start is words, never a date.
$sample_plan = $business_plans['lease']['title'];
$sample_company = lang('Sample Company');
$sample_start = lang('Start date');
$sample_classes = $classes['compact']['title'] . ', ' . $classes['van']['title'];
$sample_needs = lang('Four cars for our sales team from next month, mostly motorway driving, and a people carrier for the site visits.');
$business_tel = 'tel:' . preg_replace('/[^0-9+]/', '', $company['phone']);

// What every business rental includes, as the business page lists it.
$business_included = array(
    array('bi-tools', lang('Servicing and tyres'), lang('Every service, seasonal tyre and repair is booked and paid by us.')),
    array('bi-shield-check', lang('Insurance'), lang('Comprehensive cover in the monthly price, with a low excess.')),
    array('bi-arrow-repeat', lang('Replacement car'), lang('A car of the same class while yours is at the garage, at no charge.')),
    array('bi-receipt', lang('One invoice a month'), lang('All your cars and rentals on one invoice, ready for your accountant.')),
    array('bi-telephone', lang('A named contact'), lang('One person who knows your fleet answers your calls and messages.')),
    array('bi-sign-turn-right', lang('Roadside assistance'), lang('Help on the road around the clock, anywhere in the country.')),
);

// ── Pages ───────────────────────────────────────────────────────────────

$business_plan_cards = array();
foreach (array_keys($business_plans) as $key) $business_plan_cards[] = $business_card($key);
$business_included_cols = array();
foreach ($business_included as $i) {
    $business_included_cols[] = $col(array(
        $el('div', 'd-flex gap-3', $i[1], array(
            $icon_tile($i[0], 'flex-shrink-0'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $i[1], 'h6 fw-bold mb-1'),
                $para($i[2], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
}
$business_contact = $team[2];

// The business page: the plans, what they include, how a quote is made and
// the person who looks after company accounts.
$business_pages[] = array(
    'key'              => 'business',
    'name'             => lang('business-rentals'),
    'folder'           => 'public',
    'title'            => lang('Business Rentals'),
    'meta_description' => lang('Monthly rentals, long-term leases and company accounts with servicing, insurance and one invoice a month: ask for a quote.'),
    'tree'             => $page(array(
        $page_header(lang('Business Rentals'),
            lang('A car for a month, a new car for three years or a whole team on the road: one monthly price with servicing, insurance and a replacement car.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Ask for a quote'), '{{page:business_enquiry}}', 'btn btn-primary px-4'),
                $link($company['phone'], $business_tel, 'btn btn-outline-secondary px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Rental Plans'), array(
            $container(array(
                $section_header(lang('For companies'), lang('Choose how long you need the cars')),
                $row($business_plan_cards, '4'),
            )),
        ), $searchable('cr-business-plans', lang('monthly rental, long-term lease, company account, fleet, corporate'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What Is Included'), array(
            $container(array(
                $section_header(lang('In every plan'), lang('What the monthly price includes')),
                $row($business_included_cols, '4'),
            )),
        ), $searchable('cr-business-included', lang('servicing, tyres, insurance, replacement car, invoice'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('From enquiry to keys'), lang('How a business rental starts')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Tell us what you need'), lang('The plan, how many cars, which classes and for how long. No tax number or ID is asked here.')),
                            $step(2, lang('Receive a quote'), lang('We answer under your enquiry within two working days, with the monthly price for each car.')),
                            $step(3, lang('Sign at the office'), lang('Agree the quote in a message, then sign the contract at our city centre office.')),
                            $step(4, lang('Collect or have them delivered'), lang('Collect the cars at a desk, or we bring them to your company.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4 pcr-lift', lang('Contact Card'), array(
                            $el('div', 'card-body p-4 text-center', lang('Card Body'), array(
                                $image($portrait($business_contact['name'], 192), $business_contact['name'], 'rounded-circle mb-3',
                                    array('width' => '96', 'height' => '96', '_attrs' => array($attr('loading', 'lazy')))),
                                $heading('h3', $business_contact['name'], 'h5 fw-semibold mb-0'),
                                $para($business_contact['role'], 'small text-primary fw-semibold mb-2'),
                                $para($business_contact['bio'], 'small text-body-secondary mb-3'),
                                $link(lang('Ask for a quote'), '{{page:business_enquiry}}', 'btn btn-primary w-100'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('cr-business-how', lang('quote, contract, delivery, company cars'))),
    )),
);

// The enquiry. What is sent opens the enquiry's own page.
$business_pages[] = array(
    'key'              => 'business_enquiry',
    'name'             => lang('business-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Business Enquiry'),
    'meta_description' => lang('Ask for a quote for a monthly rental, a long-term lease or a company account: we answer within two working days.'),
    'form'             => array(
        'form_name'            => lang('Business Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached us. We answer within two working days with a quote.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New business enquiry: ^^company^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your business enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Business Enquiry'),
            lang('Tell us how many cars you need and for how long. We answer within two working days with a quote; nothing is signed or paid now.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('enquiry_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('What Is Included'), array(
                            $heading('h2', lang('In every plan'), 'h5 fw-semibold mb-3'),
                            $check_list(array($business_included[0][1], $business_included[1][1], $business_included[2][1], $business_included[3][1]),
                                'list-unstyled small mb-3'),
                            $link(lang('See the plans'), '{{page:business}}', 'fw-semibold link-primary text-decoration-none small'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One enquiry: what was asked, its status and the messages under it.
$business_pages[] = array(
    'key'      => 'enquiry_view',
    'name'     => lang('business-enquiry-request'),
    'folder'   => 'registration',
    'title'    => lang('Business Enquiry'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your business enquiry'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('enquiry_detail'),
        $comments_section(lang('Messages'), 'enquiry_messages'),
    ), false),
);

// My Enquiries: the sender's own enquiries (for the staff, everybody's).
$business_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-business-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Business Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Business Enquiries'), lang('Your business rental enquiries, their status and our quotes, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_enquiries_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Enquiry'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-briefcase', 'text-primary'),
                                $span(lang('More cars?'), '', lang('Text')),
                            )),
                            $para(lang('Ask about another plan or more cars; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Ask for a quote'), '{{page:business_enquiry}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every enquiry, newest first, with a search.
$business_pages[] = array(
    'key'     => 'staff_enquiries',
    'name'    => lang('all-business-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Business Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Business Enquiries'), lang('Every business rental enquiry, newest first: send the quote, set the status and write to the company under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_enquiries_list'))),
        )),
    )),
);

// The e-mails: what the company receives and the reply to the sender.
$business_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-business-enquiry'),
    'title' => lang('New Business Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$business_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-business-enquiry-received'),
    'title' => lang('Business Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// An enquiry in a list: the plan, the status, the fleet size, the term and
// the start, linked to its page. The staff's row starts with the company
// and the sender and adds the phone; the sender's own adds the messages.
$enquiry_row = function ($staff = false) use ($el, $span, $icon, $bind, $renter_name, $sample_plan, $sample_company, $sample_start,
        $fleet_size_choices, $rental_term_choices, $enquiry_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-car-front', lang('Fleet size'), array($span($fleet_size_choices[1], '', lang('Fleet size'), $bind('fleet_size')))),
        $item('bi-hourglass-split', lang('Rental term'), array($span($rental_term_choices[1], '', lang('Rental term'), $bind('rental_term')))),
        $item('bi-calendar-event', lang('Start date'), array($span($sample_start, '', lang('Start date'), $bind('start_date')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Company'), array_merge(
            array($span($sample_company, '', lang('Company'), $bind('company')), $span(' · ', 'text-body-secondary', lang('Text'))),
            $renter_name()
        ));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_plan, 'fw-semibold', lang('Rental Plan'), $bind('plan'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Business Enquiry'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($enquiry_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The lines of an enquiry, for its e-mails.
$mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $sample_plan, $sample_company, $sample_start, $sample_classes,
        $fleet_size_choices, $rental_term_choices, $location_titles) {
    return $mail_box(array(
        $mail_line(lang('Company'), array($span($sample_company, '', lang('Company'), $bind('company')))),
        $mail_line(lang('Rental Plan'), array($span($sample_plan, '', lang('Rental Plan'), $bind('plan')))),
        $mail_line(lang('Fleet size'), array($span($fleet_size_choices[1], '', lang('Fleet size'), $bind('fleet_size')))),
        $mail_line(lang('Rental term'), array($span($rental_term_choices[1], '', lang('Rental term'), $bind('rental_term')))),
        $mail_line(lang('Car classes'), array($span($sample_classes, '', lang('Car classes'), $bind('car_classes')))),
        $mail_line(lang('Start date'), array($span($sample_start, '', lang('Start date'), $bind('start_date')))),
        $mail_line(lang('Nearest desk'), array($span($location_titles[1], '', lang('Nearest desk'), $bind('location')))),
    ), lang('Business Enquiry'));
};
// The sender's lines of the staff e-mail.
$mail_sender = function () use ($mail_box, $mail_line, $span, $bind, $renter_name, $sample_needs) {
    return $mail_box(array(
        $mail_line(lang('Contact person'), $renter_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Best way to reach you'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
        $mail_line(lang('Details of your needs'), array($span($sample_needs, '', lang('Details of your needs'), $bind('needs')))),
    ), lang('Contact person'));
};
// The form detail settings of an e-mail page's widget (any reader) and of
// the enquiry page's widget (the sender's own, and the staff's).
$enquiry_detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:business_enquiry}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of enquiries: the sender's own (the staff see them all), or the
// staff's with a search.
$enquiry_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $enquiry_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:business_enquiry}}',
        'detail_page_id'      => '{{tab:enquiry_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('company', 'first_name', 'last_name', 'email', 'plan', 'fleet_size', 'rental_term', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by company, name, e-mail, plan, fleet size, term or status...'),
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
            $el('div', 'list-group', lang('Enquiry List'), array($loop(array($enquiry_row($staff))))),
        )),
    );
};

$enquiry_missing = lang('This enquiry could not be found, or it is not yours to see.');

$business_widgets = array(

    // The enquiry: the fields are the page's form. The Rental Plan starts
    // with what a plan card sends (?plan=). The Status is the staff's
    // (office use only): every enquiry starts as Request received.
    'enquiry_form' => array(
        'page'   => 'business_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your company\'s needs')),
            $field('cr-be-plan', lang('Rental Plan'), $select('cr-be-plan', 'plan', '', $plan_choices, true, $plan_choices[0], array('url_param' => 'plan'))),
            $row(array(
                $col(array($field('cr-be-size', lang('Fleet size'), $select('cr-be-size', 'fleet_size', lang('Choose a fleet size…'), $fleet_size_choices))), '6'),
                $col(array($field('cr-be-term', lang('Rental term'), $select('cr-be-term', 'rental_term', lang('Choose a term…'), $rental_term_choices))), '6'),
                $col(array($field('cr-be-start', lang('Start date'), $input('cr-be-start', 'date', 'start_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('cr-be-location', lang('Nearest desk'), $select('cr-be-location', 'location', lang('Delivery to our address'), $location_titles, false))), '6'),
            ), '3'),
            $checkboxes('cr-be-classes', 'car_classes', lang('Car classes'), $class_titles),
            $field('cr-be-needs', lang('Details of your needs'), $textarea('cr-be-needs', 'needs', 4, false), 'mb-3',
                lang('Who drives, how far, and anything the cars must have: a tow bar, winter tyres, room for samples.')),
            $form_step(2, lang('How to reach you')),
            $field('cr-be-company', lang('Company'), $input('cr-be-company', 'text', 'company', 'organization', true, array('contact_field' => 'company'))),
            $row(array(
                $col(array($field('cr-be-first-name', lang('First Name'), $input('cr-be-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('cr-be-last-name', lang('Last Name'), $input('cr-be-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('cr-be-email', lang('Email'), $input('cr-be-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('cr-be-phone', lang('Phone'), $input('cr-be-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $radios('cr-be-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('E-mail')),
            $field('cr-be-status', lang('Status'), $select('cr-be-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('cr-be-consent', 'consent', lang('I agree that the rental company keeps these details to prepare a quote and answer me, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We answer within two working days with a quote.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One enquiry: what was asked, its status and what the sender wrote;
    // the quote and the messages follow as comments.
    'enquiry_detail' => $enquiry_detail_widget('enquiry_view', $enquiry_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-briefcase', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Enquiry not found'), 'h3 fw-semibold mt-3'),
                            $para($enquiry_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Business Enquiries'), '{{page:my_enquiries}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your enquiry has reached us. We answer here within two working days with a quote; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Business Enquiries'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_enquiries}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Enquiry Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Business Enquiry'), 'pcr-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_company, 'pcr-display h3 mb-0', $bind('company')),
                                        )),
                                        $span($enquiry_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Rental Plan'), '', lang('Rental Plan'), $bound($sample_plan, 'plan')),
                                        $detail(lang('Fleet size'), '', lang('Fleet size'), $bound($fleet_size_choices[1], 'fleet_size')),
                                        $detail(lang('Rental term'), '', lang('Rental term'), $bound($rental_term_choices[1], 'rental_term')),
                                        $detail(lang('Car classes'), 'pg-hide-if-empty', lang('Car classes'), $bound($sample_classes, 'car_classes')),
                                        $detail(lang('Start date'), '', lang('Start date'), $bound($sample_start, 'start_date')),
                                        $detail(lang('Nearest desk'), 'pg-hide-if-empty', lang('Nearest desk'), $bound($location_titles[1], 'location')),
                                        $detail(lang('Contact person'), '', lang('Contact person'), $renter_name(), true),
                                        $detail(lang('Best way to reach you'), '', lang('Contact by'), $bound(lang('E-mail'), 'contact_method')),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Details of your needs'), array(
                                $para(lang('Details of your needs'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_needs, 'needs')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('More cars, another term or a question about the quote? Write to us below; the quote comes here too, and every answer by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under an enquiry: the page's own Comments widget, shown
    // only to whoever may read the enquiry above it. Oldest first, all on
    // one page, with a file each (the quote as a PDF). No Reply, Share or
    // follow box: the sender is e-mailed every answer and the company every
    // message.
    'enquiry_messages' => array(
        'page'   => 'enquiry_view',
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
            'empty'     => lang('No messages yet. Our quote comes here; you can write to us about your enquiry too.'),
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

    'my_enquiries_list' => $enquiry_list('my_enquiries', false,
        lang('No business enquiries yet. Tell us how many cars you need and for how long.')),

    'staff_enquiries_list' => $enquiry_list('staff_enquiries', true, lang('No business enquiries yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $enquiry_detail_widget('email_enquiry_new', lang('The enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new business enquiry')),
            $mail_para(lang('It came from the business enquiry form. Answer under the enquiry on its page with the quote (a PDF may go with it) and set the status; what you write there reaches the sender by e-mail.')),
            $mail_enquiry(),
            $mail_sender(),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $enquiry_detail_widget('email_enquiry_received', lang('The enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your business enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We look at what your company needs and answer within two working days with a quote, on the enquiry\'s page and by e-mail. Nothing is signed or paid now.')),
            $mail_enquiry(),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $business_pages, 'widgets' => $business_widgets);
