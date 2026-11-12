<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Home Services", its estimate requests: the free
 * estimate form with photos, the request's own page with its messages, My
 * Estimates and the staff's list, the e-mails of the form and the widgets
 * that draw them. Included after parts.php, mail.php, services.php and
 * visits.php in the same scope (it reuses the record pieces of
 * visits.php); returns array('pages' => ..., 'widgets' => ...).
 *
 * An estimate request is a record of the Estimate Requests form, never a
 * price the site works out. A customer without an account gets one
 * (auto-registration), so the request's page and My Estimates show it at
 * once. Its Status is an office-use-only field the company changes in the
 * control panel; the estimate itself is a staff message under the request
 * (the page's comments), mailed to the customer. The photos sent with the
 * form are kept in the template's private folder: the request's page
 * links them for its sender and the staff only. More photos can follow as
 * a message's attachment.
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

$estimate_pages = array();

// The sample request the canvas shows.
$estimate_service = $services['doors_windows']['name'];
$estimate_type = $property_types[0];
$estimate_size = $property_sizes[1];
$estimate_urgency = $urgency_choices[1];
$estimate_contact = $contact_choices[0][1];
$estimate_details = lang('The balcony door no longer closes and the bedroom window frame has warped. We would like both repaired, or replaced if that is better; the photos show the frames and the gaps.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the request page: what happens next, then what to
// send and the way to book a visit instead.
$estimate_aside = function () use ($el, $heading, $link, $check_list) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('We look at your photos and write the estimate on that page within two working days.'),
        lang('If we need to see the job first, we offer a free survey visit.'),
        lang('Accept the estimate under the request and we book the work with you.'),
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
                lang('An estimate is free and you are under no obligation.'),
                lang('Clear photos of the whole area and of the detail make the estimate closer to the final price.'),
                lang('A small listed job? Book a visit straight away instead.'),
            ), 'list-unstyled small mb-3'),
            $el('div', 'd-flex flex-column gap-1', lang('Links'), array(
                $link(lang('See the price guide'), '{{page:prices}}', 'fw-semibold link-primary text-decoration-none small'),
                $link(lang('Book a Visit'), '{{page:book}}', 'fw-semibold link-primary text-decoration-none small'),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The estimate request. What is sent opens the request's own page.
$estimate_pages[] = array(
    'key'              => 'estimate',
    'name'             => lang('free-estimate'),
    'folder'           => 'public',
    'title'            => lang('Get a Free Estimate'),
    'meta_description' => lang('Tell us about the job and send a few photos: we write you a free estimate within two working days, with no obligation.'),
    'form'             => array(
        'form_name'            => lang('Estimate Requests'),
        'confirmation_message' => lang('Thank you, your estimate request has reached us. We write the estimate within two working days.'),
        'confirmation_page_id' => '{{tab:estimate_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New estimate request: ^^service^^'),
        'notify_page_id'       => '{{tab:email_estimate_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We have your estimate request'),
        'confirm_page_id'      => '{{tab:email_estimate_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Get a Free Estimate'),
            lang('Tell us about the job and send a few photos. We write you an estimate within two working days, free and with no obligation.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('estimate_form'))),
                        )),
                    ), '', '8'),
                    $col($estimate_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One estimate request: the request, its photos, its status and the
// messages under it, the estimate among them.
$estimate_pages[] = array(
    'key'      => 'estimate_view',
    'name'     => lang('estimate-request'),
    'folder'   => 'registration',
    'title'    => lang('Estimate Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your estimate request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('estimate_detail'),
        $comments_section(lang('Messages'), 'estimate_messages'),
    ), false),
);

// My Estimates: the customer's own requests (for the staff, everybody's).
$estimate_pages[] = array(
    'key'     => 'my_estimates',
    'name'    => lang('my-estimates'),
    'folder'  => 'registration',
    'title'   => lang('My Estimates'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Estimates'), lang('Your estimate requests, their status and our estimates, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_estimates_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-camera', 'text-primary'),
                                $span(lang('Another job to price?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for another estimate; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Get a Free Estimate'), '{{page:estimate}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every estimate request, newest first, with a search.
$estimate_pages[] = array(
    'key'     => 'staff_estimates',
    'name'    => lang('all-estimate-requests'),
    'folder'  => 'private',
    'title'   => lang('Estimate Requests'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Estimate Requests'), lang('Every estimate request, newest first: look at the photos, write the estimate under each and set the status.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_estimates_list'))),
        )),
    )),
);

// The e-mails: what the company receives and the reply to the customer.
$estimate_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_estimate_new',
    'name'  => lang('email-new-estimate-request'),
    'title' => lang('New Estimate Request E-mail'),
    'tree'  => $mail_page(array($widget('email_estimate_admin'))),
));
$estimate_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_estimate_received',
    'name'  => lang('email-estimate-request-received'),
    'title' => lang('Estimate Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_estimate_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// An estimate request in a list: the service, the status, the property,
// the urgency and the area, linked to its page. The staff's row starts
// with the customer's name and adds the phone; the customer's own adds the
// messages.
$estimate_row = function ($staff = false) use ($el, $span, $icon, $bind, $customer_name, $estimate_service, $estimate_type, $estimate_urgency, $areas, $estimate_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-house', lang('Property Type'), array($span($estimate_type, '', lang('Property Type'), $bind('property_type')))),
        $item('bi-hourglass-split', lang('When'), array($span($estimate_urgency, '', lang('When'), $bind('urgency')))),
        $item('bi-geo-alt', lang('Area'), array($span($areas['northside']['name'], '', lang('Area'), $bind('area')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Customer'), array_merge($customer_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($estimate_service, 'text-body-secondary fw-normal', lang('Service Name'), $bind('service')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($estimate_service, 'fw-semibold', lang('Service Name'), $bind('service'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Estimate Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($estimate_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The lines of an estimate request, for its e-mails.
$mail_estimate = function () use ($mail_box, $mail_line, $span, $bind, $estimate_service, $areas, $estimate_type, $estimate_size, $estimate_urgency) {
    return $mail_box(array(
        $mail_line(lang('Service Name'), array($span($estimate_service, '', lang('Service Name'), $bind('service')))),
        $mail_line(lang('Area'), array($span($areas['northside']['name'], '', lang('Area'), $bind('area')))),
        $mail_line(lang('Property Type'), array($span($estimate_type, '', lang('Property Type'), $bind('property_type')))),
        $mail_line(lang('Property Size'), array($span($estimate_size, '', lang('Property Size'), $bind('property_size')))),
        $mail_line(lang('When'), array($span($estimate_urgency, '', lang('When'), $bind('urgency')))),
    ), lang('Estimate Request'));
};
// The customer's lines of the staff e-mail.
$mail_estimate_customer = function () use ($mail_box, $mail_line, $span, $bind, $customer_name, $estimate_contact, $estimate_details) {
    return $mail_box(array(
        $mail_line(lang('Customer'), $customer_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Contact by'), array($span($estimate_contact, '', lang('Contact by'), $bind('contact_method')))),
        $mail_line(lang('About the job'), array($span($estimate_details, '', lang('About the job'), $bind('details')))),
    ), lang('Customer'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the request page's
// widget (the customer's own, and the staff's).
$estimate_detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:estimate}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of estimate requests: the customer's own (the staff see them
// all), or the staff's with a search.
$estimate_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $estimate_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:estimate}}',
        'detail_page_id'      => '{{tab:estimate_view}}',
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($estimate_row($staff))))),
        )),
    );
};

$estimate_missing = lang('This estimate request could not be found, or it is not yours to see.');

$estimate_widgets = array(

    // The estimate request: the fields are the page's form. The service and
    // the area start with what a link sends (?service=, ?area=). The photos
    // go to the private folder. The Status is the company's (office use
    // only): every request starts as Estimate requested.
    'estimate_form' => array(
        'page'   => 'estimate',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The job')),
            $row(array(
                $col(array($field('hs-es-service', lang('Service Name'), $select('hs-es-service', 'service', lang('Choose a service…'), $service_choices, true, '', array('url_param' => 'service')), 'mb-3',
                    lang('Not on the list? Choose "Something else" and tell us about the job below.'))), '6'),
                $col(array($field('hs-es-area', lang('Area'), $select('hs-es-area', 'area', lang('Choose your area…'), $area_names, true, '', array('url_param' => 'area')), 'mb-3',
                    lang('Further away? Choose the nearest area and say where you are below.'))), '6'),
            ), '3'),
            $field('hs-es-details', lang('About the job'), $textarea('hs-es-details', 'details', 5, true), 'mb-3',
                lang('What needs doing, the sizes if you know them, the materials you would like and anything we should know about access.')),
            $field('hs-es-photos', lang('Photos'), $file('hs-es-photos', 'photos', 'private', '.jpg,.jpeg,.png,.heic,.pdf'), 'mb-3',
                lang('A photo of the whole area and one of the detail (a picture or a PDF). More can follow under your request.')),
            $form_step(2, lang('The property')),
            $row(array(
                $col(array($field('hs-es-type', lang('Property Type'), $select('hs-es-type', 'property_type', '', $property_types, true, $property_types[0]))), '4'),
                $col(array($field('hs-es-size', lang('Property Size'), $select('hs-es-size', 'property_size', '', $property_sizes, false, $property_sizes[1]))), '4'),
                $col(array($field('hs-es-urgency', lang('When'), $select('hs-es-urgency', 'urgency', '', $urgency_choices, true, $urgency_choices[1]))), '4'),
            ), '3'),
            $form_step(3, lang('How to reach you')),
            $row(array(
                $col(array($field('hs-es-first-name', lang('First Name'), $input('hs-es-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('hs-es-last-name', lang('Last Name'), $input('hs-es-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('hs-es-email', lang('Email'), $input('hs-es-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('hs-es-phone', lang('Phone'), $input('hs-es-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $radios('hs-es-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[0][0]),
            $field('hs-es-status', lang('Status'), $select('hs-es-status', 'status', '', $estimate_statuses, false, $estimate_statuses[0], array('office_use_only' => true))),
            $agree('hs-es-consent', 'consent', lang('I agree that the company keeps these details and photos to write my estimate, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Ask for the Estimate'), 'btn btn-primary btn-lg px-4'),
                $para(lang('Free and with no obligation. We answer within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One estimate request: the request, its status, what the customer wrote
    // and the photos; the estimate follows as the company's message.
    'estimate_detail' => $estimate_detail_widget('estimate_view', $estimate_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $hs_not_found('bi-file-earmark-x', lang('Estimate request not found'), $estimate_missing, lang('My Estimates'), 'my_estimates'),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached us. We write the estimate here within two working days; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                            $hs_toolbar('my_estimates', lang('My Estimates')),
                            $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Request Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Estimate Request'), 'phs-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $estimate_service, 'phs-display h3 mb-0', $bind('service')),
                                        )),
                                        $span($estimate_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Area'), '', lang('Area'), $bound($areas['northside']['name'], 'area')),
                                        $detail(lang('Property Type'), '', lang('Property Type'), $bound($estimate_type, 'property_type')),
                                        $detail(lang('Property Size'), '', lang('Property Size'), $bound($estimate_size, 'property_size')),
                                        $detail(lang('When'), '', lang('When'), $bound($estimate_urgency, 'urgency')),
                                        $detail(lang('Customer'), '', lang('Customer'), $customer_name(), true),
                                        $detail(lang('Contact by'), '', lang('Contact by'), $bound($estimate_contact, 'contact_method')),
                                        $detail(lang('Photos'), 'text-break', lang('Photos'), array(
                                            $link('balcony-door.jpg', '#', 'link-body-emphasis', array(), array('target' => '_blank', 'rel' => 'noopener',
                                                '_bindings' => array('href' => 'photos', 'text' => 'photos'))),
                                        ), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('About the job'), array(
                                $para(lang('About the job'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($estimate_details, 'details')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('More photos or a question? Write to us below and attach them; our estimate comes here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page, with attachments for more photos. No Reply, Share or follow
    // box: the customer is e-mailed every answer and the company every
    // message.
    'estimate_messages' => array(
        'page'   => 'estimate_view',
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
            'empty'     => lang('No messages yet. Our estimate comes here; you can write to us and send more photos too.'),
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

    'my_estimates_list' => $estimate_list('my_estimates', false,
        lang('No estimate requests yet. Tell us about a job and send a few photos; we write the estimate within two working days.')),

    'staff_estimates_list' => $estimate_list('staff_estimates', true, lang('No estimate requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_estimate_admin' => $estimate_detail_widget('email_estimate_new', lang('This estimate request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new estimate request')),
            $mail_para(lang('It came from the estimate form. Look at the photos on the request\'s page, write the estimate under it and set the status; what you write there reaches the customer by e-mail.')),
            $mail_estimate(),
            $mail_estimate_customer(),
            $mail_button(lang('Open the estimate request'), '{{page:estimate_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_estimate_client' => $estimate_detail_widget('email_estimate_received', lang('This estimate request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We have your estimate request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We look at your photos and write the estimate within two working days, on the request\'s page and by e-mail. It is free and you are under no obligation.')),
            $mail_estimate(),
            $mail_para(lang('More photos or a question? Write to us on the request\'s page; everything about the job stays there.')),
            $mail_button(lang('Open my estimate request'), '{{page:estimate_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $estimate_pages, 'widgets' => $estimate_widgets);
