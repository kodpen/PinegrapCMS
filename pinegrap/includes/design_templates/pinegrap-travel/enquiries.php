<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Travel Agency", its tailor-made enquiries: the enquiry
 * form, the enquiry's own page with its messages, My Enquiries and the
 * staff's list, the two e-mails of the enquiry form, and the widgets that
 * draw them. Included after parts.php, mail.php and bookings.php in the
 * same scope (it uses bookings.php's $detail, $bound, $traveller_name,
 * $mail_reference and $mail_new_account); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An enquiry is a record of the Tailor-made Enquiries form, as a booking
 * request is a record of its own form: a wish for a journey, never a
 * booking. A traveller without an account gets one (auto-registration), so
 * the enquiry's page and My Enquiries show it at once. Its Status is an
 * office-use-only field the agency changes in the control panel; the
 * proposal and the messages under it are the page's comments, mailed to
 * the traveller and to the agency.
 *
 * The destination starts with what a link sends (?destination=, the
 * destination's name as parts.php writes it).
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

// The sample enquiry the canvas shows. Its time of travel is words, never
// a date.
$eq_sample_destination = $destination_names[0];
$eq_sample_when = lang('In the spring, about two weeks');
$eq_sample_note = lang('Our honeymoon: slow days, a few nights by the sea and one special hotel. We love food markets and do not want long drives.');
$eq_missing = lang('This enquiry could not be found, or it is not yours to see.');

// The side column of the enquiry page: what happens next, then the
// agency's direct line.
$eq_aside = function () use ($el, $heading, $para, $link, $icon, $agency) {
    $steps = array(
        lang('Your enquiry opens its own page, and an account for you if you have none.'),
        lang('A travel planner answers within two working days with a first route and the price.'),
        lang('We shape it with you under your enquiry until it is the journey you want.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Travel Planners'), array(
            $heading('h2', lang('Rather talk it through?'), 'h5 fw-semibold mb-2'),
            $para($agency['hours'], 'small text-body-secondary mb-3'),
            $el('p', 'd-flex align-items-center gap-2 small mb-0', lang('Phone'), array(
                $icon('bi-telephone', 'text-primary'),
                $link($agency['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $agency['phone']), 'link-body-emphasis text-decoration-none'),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The tailor-made enquiry. What is sent opens the enquiry's own page.
$enquiry_pages[] = array(
    'key'              => 'enquiry',
    'name'             => lang('tailor-made-trip'),
    'folder'           => 'public',
    'title'            => lang('Plan a Tailor-made Trip'),
    'meta_description' => lang('Tell us where you dream of going, when and with whom: a travel planner answers within two working days with a first route.'),
    'form'             => array(
        'form_name'            => lang('Tailor-made Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached us. A travel planner answers within two working days.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New tailor-made enquiry: ^^destination^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We have your tailor-made enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Plan a Tailor-made Trip'),
            lang('Tell us where, when and with whom. A travel planner answers within two working days with a first route and the price.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('enquiry_form'))),
                        )),
                    ), '', '8'),
                    $col($eq_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One enquiry: what was asked, its status and the messages under it.
$enquiry_pages[] = array(
    'key'      => 'enquiry_view',
    'name'     => lang('enquiry'),
    'folder'   => 'registration',
    'title'    => lang('Tailor-made Enquiry'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your journey'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('enquiry_detail'),
        $comments_section(lang('Messages'), 'enquiry_messages'),
    ), false),
);

// My Enquiries: the traveller's own enquiries (for the staff, everybody's).
$enquiry_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Enquiries'), lang('Your tailor-made enquiries, their status and our proposals, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_enquiries_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Enquiry'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-compass', 'text-primary'),
                                $span(lang('Another journey?'), '', lang('Text')),
                            )),
                            $para(lang('Start a new enquiry; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Plan a Tailor-made Trip'), '{{page:enquiry}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every enquiry, newest first, with a search.
$enquiry_pages[] = array(
    'key'     => 'staff_enquiries',
    'name'    => lang('all-tailor-made-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Tailor-made Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Tailor-made Enquiries'), lang('Every tailor-made enquiry, newest first: send the route and the price, set the status and answer under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_enquiries_list'))),
        )),
    )),
);

// The e-mails of the enquiry form: what the agency receives and the reply
// to the traveller. A message under an enquiry goes out with the new
// message e-mail (email_reply) the record page names.
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-tailor-made-enquiry'),
    'title' => lang('New Tailor-made Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-tailor-made-enquiry-received'),
    'title' => lang('Tailor-made Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// An enquiry in a list: the destination, the status, the kind of trip,
// when and the travellers, linked to its page. The staff's row names the
// traveller and the phone; the traveller's own shows the messages.
$eq_row = function ($staff = false) use ($el, $span, $icon, $bind, $traveller_name, $trip_kinds, $enquiry_statuses, $eq_sample_destination, $eq_sample_when) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $span, $icon, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array($icon($icon_name), $span($sample, '', $label, $bind($field))));
    };
    $items = array(
        $item('bi-stars', lang('Kind of trip'), $trip_kinds[0], 'trip_kind'),
        $item('bi-calendar-range', lang('When'), $eq_sample_when, 'travel_time'),
        $item('bi-people', lang('Travellers'), '2', 'travellers'),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', lang('Traveller'), array_merge($traveller_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($eq_sample_destination, 'text-body-secondary fw-normal', lang('Where to'), $bind('destination')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($eq_sample_destination, 'fw-semibold', lang('Where to'), $bind('destination'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Enquiry'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($enquiry_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The form detail settings of a widget: the e-mail pages read the record
// they are sent for (public), the enquiry's page only the traveller's own.
$eq_detail_widget = function ($page_key, $access) use ($eq_missing) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:enquiry}}',
            'access_control'      => $access,
            'not_found_message'   => $eq_missing,
        ),
    );
};

// A list of enquiries: the traveller's own (the staff see them all), or the
// staff's with a search.
$eq_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $eq_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:enquiry}}',
        'detail_page_id'      => '{{tab:enquiry_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'destination', 'trip_kind', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by traveller, e-mail, destination, kind of trip or status...'),
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
            $el('div', 'list-group', lang('Enquiry List'), array($loop(array($eq_row($staff))))),
        )),
    );
};

// The lines of an enquiry, for its e-mails.
$eq_mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $trip_kinds, $length_choices, $budget_choices, $eq_sample_destination, $eq_sample_when) {
    return $mail_box(array(
        $mail_line(lang('Where to'), array($span($eq_sample_destination, '', lang('Where to'), $bind('destination')))),
        $mail_line(lang('Kind of trip'), array($span($trip_kinds[0], '', lang('Kind of trip'), $bind('trip_kind')))),
        $mail_line(lang('When'), array($span($eq_sample_when, '', lang('When'), $bind('travel_time')))),
        $mail_line(lang('How long'), array($span($length_choices[1], '', lang('How long'), $bind('trip_length')))),
        $mail_line(lang('Travellers'), array($span('2', '', lang('Travellers'), $bind('travellers')))),
        $mail_line(lang('Budget'), array($span($budget_choices[1], '', lang('Budget'), $bind('budget')))),
        $mail_line(lang('Contact by'), array($span(lang('E-mail'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Enquiry'));
};
// The traveller's lines of the staff e-mail.
$eq_mail_traveller = function () use ($mail_box, $mail_line, $span, $bind, $traveller_name, $eq_sample_note) {
    return $mail_box(array(
        $mail_line(lang('Traveller'), $traveller_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Your dream journey'), array($span($eq_sample_note, '', lang('Text'), $bind('details')))),
    ), lang('Traveller'));
};

$enquiry_widgets = array(

    // The tailor-made enquiry: the fields are the page's form. The
    // destination starts with what a link sends (?destination=). The Status
    // is the agency's (office use only): every enquiry starts as Enquiry
    // received.
    'enquiry_form' => array(
        'page'   => 'enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your journey')),
            $row(array(
                $col(array($field('tv-eq-destination', lang('Where to'), $select('tv-eq-destination', 'destination', '', $destination_choices, true, lang('Not sure yet'), array('url_param' => 'destination')))), '6'),
                $col(array($field('tv-eq-kind', lang('Kind of trip'), $select('tv-eq-kind', 'trip_kind', lang('Choose a kind of trip…'), $trip_kinds))), '6'),
                $col(array($field('tv-eq-when', lang('When'), $input('tv-eq-when', 'text', 'travel_time', 'off', true), 'mb-3',
                    lang('A month, a season or the dates you have in mind.'))), '6'),
                $col(array($field('tv-eq-length', lang('How long'), $select('tv-eq-length', 'trip_length', lang('Choose a length…'), $length_choices))), '6'),
                $col(array($field('tv-eq-travellers', lang('Travellers'), $input('tv-eq-travellers', 'number', 'travellers', 'off', true, array(),
                    array($attr('min', '1'), $attr('max', '30'), $attr('step', '1'), $attr('value', '2'))))), '6'),
                $col(array($field('tv-eq-budget', lang('Budget'), $select('tv-eq-budget', 'budget', '', $budget_choices, false, lang('Not sure yet')))), '6'),
            ), '3'),
            $field('tv-eq-details', lang('Your dream journey'), $textarea('tv-eq-details', 'details', 4, false), 'mb-3',
                lang('What you love to do, how you like to stay, an occasion, the ages of children: anything that helps us plan.')),
            $form_step(2, lang('How to reach you')),
            $radios('tv-eq-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('E-mail')),
            $row(array(
                $col(array($field('tv-eq-first-name', lang('First Name'), $input('tv-eq-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('tv-eq-last-name', lang('Last Name'), $input('tv-eq-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('tv-eq-email', lang('Email'), $input('tv-eq-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('tv-eq-phone', lang('Phone'), $input('tv-eq-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('tv-eq-status', lang('Status'), $select('tv-eq-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('tv-eq-consent', 'consent', lang('I agree that the agency keeps these details to answer my request and arrange my journey, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('A travel planner answers within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One enquiry: what was asked, its status and what the traveller
    // wrote; the proposal and the messages follow as comments.
    'enquiry_detail' => $eq_detail_widget('enquiry_view', 'submitter_only') + array(
        'tree' => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-compass', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Enquiry not found'), 'h3 fw-semibold mt-3'),
                            $para($eq_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Enquiries'), '{{page:my_enquiries}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your enquiry has reached us. A travel planner answers here within two working days; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Enquiries'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_enquiries}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Enquiry Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Tailor-made Enquiry'), 'ptv-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $eq_sample_destination, 'ptv-display h3 mb-0', $bind('destination')),
                                        )),
                                        $span($enquiry_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Kind of trip'), '', lang('Kind of trip'), $bound($trip_kinds[0], 'trip_kind')),
                                        $detail(lang('When'), '', lang('When'), $bound($eq_sample_when, 'travel_time')),
                                        $detail(lang('How long'), '', lang('How long'), $bound($length_choices[1], 'trip_length')),
                                        $detail(lang('Travellers'), '', lang('Travellers'), $bound('2', 'travellers')),
                                        $detail(lang('Budget'), 'pg-hide-if-empty', lang('Budget'), $bound($budget_choices[1], 'budget')),
                                        $detail(lang('Best way to reach you'), '', lang('Contact by'), $bound(lang('E-mail'), 'contact_method')),
                                        $detail(lang('Traveller'), '', lang('Traveller'), $traveller_name(), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Your dream journey'), array(
                                $para(lang('Your dream journey'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($eq_sample_note, 'details')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Something to change or add? Write to us below; the route and the price come here too, and every answer by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The proposal and the messages under an enquiry: the page's own
    // Comments widget, shown only to whoever may read the enquiry above
    // it. Oldest first, all on one page, a file allowed (a route or a
    // map). No Reply, Share or follow box: the traveller is e-mailed every
    // answer and the agency every message.
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
            'empty'     => lang('No messages yet. Our proposal comes here; you can write to us about your journey too.'),
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

    'my_enquiries_list' => $eq_list('my_enquiries', false,
        lang('No enquiries yet. Tell us about the journey you dream of, and a travel planner answers.')),

    'staff_enquiries_list' => $eq_list('staff_enquiries', true, lang('No enquiries yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $eq_detail_widget('email_enquiry_new', 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new tailor-made enquiry')),
            $mail_para(lang('It came from the tailor-made form. Answer under the enquiry on its page with a first route and the price, and set the status; what you write there reaches the traveller by e-mail.')),
            $eq_mail_enquiry(),
            $eq_mail_traveller(),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $eq_detail_widget('email_enquiry_received', 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We have your tailor-made enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. A travel planner reads your wishes and answers within two working days with a first route and the price, on the enquiry\'s page and by e-mail.')),
            $eq_mail_enquiry(),
            $mail_para(lang('Something to add? Write to us on the enquiry\'s page; everything about your journey stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $enquiry_pages, 'widgets' => $enquiry_widgets);
