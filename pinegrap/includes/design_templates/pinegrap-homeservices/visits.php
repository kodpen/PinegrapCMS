<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Home Services", its visit bookings: the visit booking
 * form, the booking's own page with its messages, My Visits and the
 * staff's list; the privacy page the consent boxes link, the e-mails of
 * the booking form and the new message e-mail every thread of the site
 * sends, and the widgets that draw them. Included after parts.php,
 * mail.php and services.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A visit booking is a request, never a confirmed slot: a record of the
 * Visit Bookings form. A customer without an account gets one
 * (auto-registration), so the booking's page and My Visits show it at
 * once. Its Status is an office-use-only field the company changes in the
 * control panel; the technician, the arrival time and the price are
 * written under it as messages (the page's comments), mailed to the
 * customer and to the company. The template keeps no technician calendar
 * and no free slots per day.
 *
 * The service and the area start with what a link sends (?service=,
 * ?area=: the service's name and the area's name, as parts.php and
 * services.php write them).
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

// The sample booking the canvas shows. Its day is words, never a date: a
// fixed day would read as a past one a year later.
$sample_service = $services['leak_repair']['name'];
$sample_area = $areas['northside']['name'];
$sample_day = lang('Visit day');
$sample_window = $time_windows[0];
$sample_access = $access_choices[0][1];
$sample_address = lang('Garden Street 8, Flat 3');
$sample_notes = lang('The kitchen tap drips all night and there is a damp patch under the sink. The building has no lift; parking is in the street.');
$call_out_words = $money($call_out);

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the booking page: what happens next, then the
// call-out charge, the areas and the estimate link.
$visit_aside = function () use ($el, $heading, $link, $check_list, $call_out_words) {
    $steps = array(
        lang('Your booking opens its own page, and an account for you if you have none.'),
        lang('We confirm the day and the time window within a working day, and name the technician.'),
        lang('The price is agreed before any work starts; the status shows Confirmed once the visit is set.'),
        lang('The technician arrives within your time window and calls if they are running late.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'phs-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Good to know'), array(
            $heading('h2', lang('Good to know'), 'phs-display h5 mb-3'),
            $check_list(array(
                sprintf(lang('Outside the core areas a call-out charge of %s is added to the visit.'), $call_out_words),
                lang('Nothing is paid now: you pay once the work is done.'),
                lang('Not sure what the job needs? Ask for a free estimate with photos first.'),
            ), 'list-unstyled small mb-3'),
            $el('div', 'd-flex flex-column gap-1', lang('Links'), array(
                $link(lang('See the areas we cover'), '{{page:areas}}', 'fw-semibold link-primary text-decoration-none small'),
                $link(lang('Get a Free Estimate'), '{{page:estimate}}', 'fw-semibold link-primary text-decoration-none small'),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The visit booking. What is sent opens the booking's own page.
$visit_pages[] = array(
    'key'              => 'book',
    'name'             => lang('book-a-visit'),
    'folder'           => 'public',
    'title'            => lang('Book a Visit'),
    'meta_description' => lang('Choose a service, a day and a time window: we confirm your visit within a working day. Nothing is paid now.'),
    'form'             => array(
        'form_name'            => lang('Visit Bookings'),
        'confirmation_message' => lang('Thank you, your visit booking has reached us. We confirm it within a working day.'),
        'confirmation_page_id' => '{{tab:visit_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New visit booking: ^^service^^'),
        'notify_page_id'       => '{{tab:email_visit_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We have your visit booking'),
        'confirm_page_id'      => '{{tab:email_visit_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book a Visit'),
            lang('Choose a service, a day and a time window. We confirm your visit within a working day; nothing is paid now.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('book_form'))),
                        )),
                    ), '', '8'),
                    $col($visit_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One visit booking: the booking, its status and the messages under it.
$visit_pages[] = array(
    'key'      => 'visit_view',
    'name'     => lang('visit-booking'),
    'folder'   => 'registration',
    'title'    => lang('Visit Booking'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => false,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your visit booking'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('visit_detail'),
        $comments_section(lang('Messages'), 'visit_messages'),
    ), false),
);

// My Visits: the customer's own bookings (for the staff, everybody's).
$visit_pages[] = array(
    'key'     => 'my_visits',
    'name'    => lang('my-visits'),
    'folder'  => 'registration',
    'title'   => lang('My Visits'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Visits'), lang('Your visit bookings, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_visits_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-tools', 'text-primary'),
                                $span(lang('Another job at home?'), '', lang('Text')),
                            )),
                            $para(lang('Book another visit; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every visit booking, newest first, with a search.
$visit_pages[] = array(
    'key'     => 'staff_visits',
    'name'    => lang('all-visit-bookings'),
    'folder'  => 'private',
    'title'   => lang('Visit Bookings'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Visit Bookings'), lang('Every visit booking, newest first: confirm the day, set the status and write to the customer under each.')),
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
    'meta_description' => lang('What the company keeps about you when you book a visit or ask for an estimate, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the company keeps about you when you book a visit or ask for an estimate, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'phs-display h4 mb-3'),
                $para(lang('When you book a visit, ask for an estimate, buy a care plan, join the newsletter or open an account, we keep your name, your contact details, your address, how we get in and what you tell us about the job, to answer you, send a technician and do the work.'), 'mb-3'),
                $para(lang('The photos you send are seen only by our staff. We keep your data as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('hs-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the company receives and the reply to the customer,
// and the new message e-mail.
$visit_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_visit_new',
    'name'  => lang('email-new-visit-booking'),
    'title' => lang('New Visit Booking E-mail'),
    'tree'  => $mail_page(array($widget('email_visit_admin'))),
));
$visit_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_visit_received',
    'name'  => lang('email-visit-booking-received'),
    'title' => lang('Visit Booking Received E-mail'),
    'tree'  => $mail_page(array($widget('email_visit_client'))),
));
// A new message or reply: sent to the customer of a visit booking or an
// estimate request, the sender of a conversation and the visitors watching
// a page, and to the company for a new review. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$visit_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your visit booking, your estimate request, a conversation, the reviews of a service or a Home Care Tips article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The customer's name.
$customer_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A visit booking in a list: the service, the status, the day, the time
// window and the area, linked to its page. The staff's row starts with
// the customer's name and adds the phone; the customer's own adds the
// messages.
$visit_row = function ($staff = false) use ($el, $span, $icon, $bind, $customer_name, $sample_service, $sample_day, $sample_window, $sample_area, $visit_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar-event', lang('Day'), array($span($sample_day, '', lang('Day'), $bind('visit_date')))),
        $item('bi-clock', lang('Time Window'), array($span($sample_window, '', lang('Time Window'), $bind('time_window')))),
        $item('bi-geo-alt', lang('Area'), array($span($sample_area, '', lang('Area'), $bind('area')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Customer'), array_merge($customer_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_service, 'text-body-secondary fw-normal', lang('Service Name'), $bind('service')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_service, 'fw-semibold', lang('Service Name'), $bind('service'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Visit Booking'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($visit_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
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

// The lines of a visit booking, for its e-mails.
$mail_visit = function () use ($mail_box, $mail_line, $span, $bind, $sample_service, $sample_area, $sample_day, $sample_window, $sample_access) {
    return $mail_box(array(
        $mail_line(lang('Service Name'), array($span($sample_service, '', lang('Service Name'), $bind('service')))),
        $mail_line(lang('Area'), array($span($sample_area, '', lang('Area'), $bind('area')))),
        $mail_line(lang('Day'), array($span($sample_day, '', lang('Day'), $bind('visit_date')))),
        $mail_line(lang('Time Window'), array($span($sample_window, '', lang('Time Window'), $bind('time_window')))),
        $mail_line(lang('Access'), array($span($sample_access, '', lang('Access'), $bind('access')))),
    ), lang('Visit Booking'));
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
    ), array_merge($mail_style('border-top:1px solid #e2e8f0;padding-top:16px'), $show_when('has_new_account')));
};
// The customer's lines of the staff e-mail.
$mail_customer = function () use ($mail_box, $mail_line, $span, $bind, $customer_name, $sample_address, $sample_notes) {
    return $mail_box(array(
        $mail_line(lang('Customer'), $customer_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Address'), array($span($sample_address, '', lang('Address'), $bind('address')))),
        $mail_line(lang('About the job'), array($span($sample_notes, '', lang('About the job'), $bind('notes')))),
    ), lang('Customer'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the booking page's
// widget (the customer's own, and the staff's).
$visit_detail_widget = function ($page_key, $missing, $access) {
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
// A list of visit bookings: the customer's own (the staff see them all),
// or the staff's with a search.
$visit_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $visit_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:book}}',
        'detail_page_id'      => '{{tab:visit_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'phone', 'service', 'area', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by customer, e-mail, phone, service, area or status...'),
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

$visit_missing = lang('This visit booking could not be found, or it is not yours to see.');

$visit_widgets = array(

    // The visit booking: the fields are the page's form. The service and the
    // area start with what a link sends (?service=, ?area=). The Status is
    // the company's (office use only): every booking starts as Request
    // received.
    'book_form' => array(
        'page'   => 'book',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The job')),
            $row(array(
                $col(array($field('hs-bk-service', lang('Service Name'), $select('hs-bk-service', 'service', lang('Choose a service…'), $service_choices, true, '', array('url_param' => 'service')), 'mb-3',
                    lang('Not on the list? Choose "Something else" and tell us about the job below.'))), '6'),
                $col(array($field('hs-bk-area', lang('Area'), $select('hs-bk-area', 'area', lang('Choose your area…'), $area_names, true, '', array('url_param' => 'area')), 'mb-3',
                    lang('Some areas are visited on set days; the areas page lists them.'))), '6'),
            ), '3'),
            $field('hs-bk-notes', lang('About the job'), $textarea('hs-bk-notes', 'notes', 4, true), 'mb-3',
                lang('What needs doing, since when, and anything the technician should bring or know.')),
            $form_step(2, lang('When we come')),
            $row(array(
                $col(array($field('hs-bk-day', lang('Day'), $input('hs-bk-day', 'date', 'visit_date', 'off', true, array('rules' => array('min_date' => 'today'))), 'mb-3',
                    lang('The day that suits you; we confirm it or offer the nearest free one.'))), '6'),
                $col(array($field('hs-bk-window', lang('Time Window'), $select('hs-bk-window', 'time_window', '', $time_windows, true, $time_windows[0]), 'mb-3',
                    lang('The technician arrives within this window.'))), '6'),
            ), '3'),
            $field('hs-bk-address', lang('Address'), $input('hs-bk-address', 'text', 'address', 'street-address', true)),
            $radios('hs-bk-access', 'access', lang('Access'), $access_choices, $access_choices[0][0]),
            $form_step(3, lang('How to reach you')),
            $row(array(
                $col(array($field('hs-bk-first-name', lang('First Name'), $input('hs-bk-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('hs-bk-last-name', lang('Last Name'), $input('hs-bk-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('hs-bk-email', lang('Email'), $input('hs-bk-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('hs-bk-phone', lang('Phone'), $input('hs-bk-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('hs-bk-status', lang('Status'), $select('hs-bk-status', 'status', '', $visit_statuses, false, $visit_statuses[0], array('office_use_only' => true))),
            $agree('hs-bk-consent', 'consent', lang('I agree that the company keeps these details to answer my booking and do the work, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Booking'), 'btn btn-primary btn-lg px-4'),
                $para(lang('We confirm within a working day. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One visit booking: the booking, its status and what the customer
    // wrote; the company's answers (the technician and the price among
    // them) follow as comments.
    'visit_detail' => $visit_detail_widget('visit_view', $visit_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $hs_not_found('bi-calendar-x', lang('Visit booking not found'), $visit_missing, lang('My Visits'), 'my_visits'),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your booking has reached us. We confirm the visit here within a working day; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                            $hs_toolbar('my_visits', lang('My Visits')),
                            $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Request Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Visit Booking'), 'phs-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_service, 'phs-display h3 mb-0', $bind('service')),
                                        )),
                                        $span($visit_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Day'), '', lang('Day'), $bound($sample_day, 'visit_date')),
                                        $detail(lang('Time Window'), '', lang('Time Window'), $bound($sample_window, 'time_window')),
                                        $detail(lang('Area'), '', lang('Area'), $bound($sample_area, 'area')),
                                        $detail(lang('Address'), '', lang('Address'), $bound($sample_address, 'address')),
                                        $detail(lang('Access'), '', lang('Access'), $bound($sample_access, 'access')),
                                        $detail(lang('Customer'), '', lang('Customer'), $customer_name(), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('About the job'), array(
                                $para(lang('About the job'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_notes, 'notes')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another day, a question about the price or a photo of the job? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a booking: the page's own Comments widget, shown
    // only to whoever may read the booking above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the customer is e-mailed
    // every answer and the company every message.
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
            'empty'     => lang('No messages yet. We confirm your visit here; you can write to us about the job too.'),
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

    'my_visits_list' => $visit_list('my_visits', false,
        lang('No visit bookings yet. Choose a service and a day, and we confirm your visit within a working day.')),

    'staff_visits_list' => $visit_list('staff_visits', true, lang('No visit bookings yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_visit_admin' => $visit_detail_widget('email_visit_new', lang('This visit booking was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new visit booking')),
            $mail_para(lang('It came from the booking form. Check the day and the area, confirm the visit under the booking on its page and set the status; what you write there reaches the customer by e-mail.')),
            $mail_visit(),
            $mail_customer(),
            $mail_button(lang('Open the visit booking'), '{{page:visit_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_visit_client' => $visit_detail_widget('email_visit_received', lang('This visit booking was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We have your visit booking')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We check the day and the time window and confirm your visit within a working day, on the booking\'s page and by e-mail. Nothing is paid now.')),
            $mail_visit(),
            $mail_para(lang('Another day or a question? Write to us on the booking\'s page; everything about the job stays there.')),
            $mail_button(lang('Open my visit booking'), '{{page:visit_view}}?r=^^reference_code^^'),
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
